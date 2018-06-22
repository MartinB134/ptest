<?php
/**
 * Frmwrk - the lightweight framework for the Netresearch programming test
 */

namespace Frmwrk;


/** Martin:
Workaround to MySQL Error: 
in my.ini, under the [mysqld] section, add a log command, like log="C:\Program Files\MySQL\MySQL Server 5.1\data\mysql.log"
Restart MySQL.
It will start logging every query in that file.


/**
 * The base class for your models - currently you can do the following stuff:
 * (NOTE that except of with the remove/delete methods nothing will happen on
 * the database unless you called {@see save()}
 * 
 * <code title="Classes for the following examples">
 * class Project extends Frmwrk\Model {
 *     $hasMany = array(
 *         'issues' // $project->issues is rowset of App\Model\Issue
 *     );
 * }
 * 
 * class Issue extends Frmwrk\Model {
 *     $belongsTo = array(
 *         'project' // $issue->project is row of Project
 *     );
 *     $hasOne = array(
 *         'image' // $project->image is row of Image
 *     );
 * }
 * </code>
 * 
 * <code title="SELECT something">
 * // This:
 * $myProjects = Project::select('user_id=?', 5)->order('title');
 * // Is the same as:
 * $myProjects = Project::select()->where('user_id=?', 5)->order('title');
 * 
 * // Get a specific project (with id 10251):
 * // (Get the rowset and the first row from it - otherwise __get-Data will
 * // alway be arrays)
 * $myProject = Project::select(10251)->first();
 * </code>
 * 
 * <code title="Get related models">
 * foreach ($myProject->issues as $issue) {
 *     echo '<img src="'.$issue->image->source.'"/>';
 *     echo '<span title="Project: '.$issue->project->title.'">'.$issue->title.'</span>';
 * }
 * </code>
 * 
 * <code title="Connect related models">
 * $project = Project::select(10251)->first();
 * $issue = Issue::select(542155)->first();
 * 
 * echo $issue->project_id; // null
 * 
 * $issue->project = $project;
 * echo $issue->project_id; // 10251
 * echo $issue->project->id; // 10251
 * echo $issue->project === $project ? 1 : 0; // 1
 * 
 * $issue->project_id = 5103;
 * echo $issue->project_id; // 5103
 * echo $issue->project->id; // 5103
 * echo $issue->project === $project ? 1 : 0; // 0 (project property was recreated)
 * 
 * $project->issues = array($issue);
 * $issue->project = $project;
 * echo $issue->project_id; // 10251
 * echo $issue->project->id; // 10251
 * echo $issue->project === $project ? 1 : 0; // 1
 * echo $project->issues->first() === $issue ? 1 : 0; // 1
 * </code>
 * 
 * <code title="Create, modify and save rows">
 * $project = new \App\Model\Project(array(
 *     'title' => 'My Project',
 *     'issues' => array(
 *         array(
 *             'type' => 1,
 *             'title' => 'My Issue',
 *             'description' => 'My issue description'
 *         )
 *     )
 * ));
 * $project->save();
 * $project->issues[0]->title = 'Updated!';
 * $project->save();
 * </code>
 *
 * @package    Frmwrk
 * @subpackage Model
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 */
abstract class Model implements \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * The primary key field name
     * @var string
     */
    const PRIMARY_KEY = 'id';
    
    /**
     * SELECT parts like "where", "order" etc.
     * @var array
     */
    protected $parts = array(
        'limit' => array(0, 50)
    );

    /**
     * The data - either of all rows in rowset mode or of the current row
     * in row mode
     * @var array
     */
    protected $data;
    
    /**
     * A copy of {@see $data}, set before some new data is {@see __set()}
     * @var array
     */
    protected $origData;

    /**
     * The models behind property names used on {@see __get()} and {@see __set()}
     * Eg. $project->issues
     * @var array
     */
    protected $relatedModels = array();
    
    /**
     * When you remove rows from a rowset via {@see offsetUnset()}, theyr IDs
     * will be collected in this array to be deleted when you call {@see save()}
     * on the rowset (unless you added them to the rowset again).
     * @var array
     */
    protected $removedRowIds = array();

    /**
     * Points to the parent rowset when $this is a row
     * (also indicates that $this is a row when set)
     * @var Model
     */
    protected $rowSet;

    /**
     * Contains information about each column retrieved from the rows
     * (set from the rows on the rowset)
     * @var array
     */
    protected $propertyInfo = array();

    /**
     * The real (mysql) name of the table - automatically derived from class
     * name if not explicetly set by implementation model
     * @var string
     */
    protected $name;
    
    /**
     * Array of properties leading to models, of which this model has MANY:
     * <example>
     * class App\Model\Project extends Frmwrk\Model {
     *     $hasMany = array(
     *         'issues' // $project->issues is rowset of App\Model\Issue
     *     );
     * }
     * </example>
     * @var array
     */
    protected $hasMany = array();
    
    /**
     * Array of properties leading to models, of which this model has ONE:
     * <example>
     * class App\Model\Project extends Frmwrk\Model {
     *     $hasOne = array(
     *         'image' // $project->image is row of App\Model\Image
     *     );
     * }
     * </example>
     * @var array
     */
    protected $hasOne = array();
    
    /**
     * Array of properties leading to models, this model BELONGS to:
     * <example>
     * class App\Model\Issue extends Frmwrk\Model {
     *     $belongsTo = array(
     *         'project' // $issue->project is row of App\Model\Project
     *     );
     * }
     * </example>
     * @var array
     */
    protected $belongsTo = array();
    
    /**
     * @var \PDO
     */
    protected static $adapter;

    //
    // Table stuff
    //
    
    /**
     * Constructor - derive table name from class name
     * If $data is passed, $this is a new row, otherwise a select/rowset
     * 
     * @param array $data
     */
    public function __construct(array $data = array())
    {
        if (!$this->name) {
            $this->name = Inflector::pluralize(Inflector::tableize($this));
        }
        
        if ($data) {
            $this->rowSet = clone $this;
            $this->rowSet->data = array($this);
            $this->data = array();
            $this->setFromArray($data);
        }
    }
    
    /**
     * Get/set adapter
     * 
     * @param \Frmwrk\Application|null $app If passed, adapter is created and set
     * @return \PDO
     * @throws Exception
     */
    public static function getAdapter(Application $app = null)
    {
        if ($app) {
            $dsn = $app->getSetting('db.driver', 'mysql').':';
            $dsn .= 'host='.$app->getSetting('db.host', 'localhost').';';
            $dsn .= 'dbname='.$app->getSetting('db.name');
            self::$adapter = new \PDO(
                $dsn,
                $app->getSetting('db.user'),
                $app->getSetting('db.pass', ''),
                $app->getSetting('db.options', null)
            );
        }
        if (!self::$adapter) {
            throw new Exception('No adapter configured yet');
        }
        return self::$adapter;
    }

    /**
     * Shortcut to create an instance of a model and set the WHERE part for the 
     * SELECT
     *
     * @param string $where
     * @param boolean $withFromPart
     * @return Model
     */
    public static function select($where = null)
    {
        $class = get_called_class();
        if ($class == __CLASS__) {
            throw new Exception(
                __METHOD__.' must be called on a concrete model implementation'
            );
        }
        $instance = new $class;
        if ($where) {
            $args = func_get_args();
            call_user_func_array(array($instance, 'where'), $args);
        }
        return $instance;
    }

    /**
     * Retrieve some information about the model
     * 
     * @param string $name
     * @return string|NULL
     */
    public function info($name)
    {
        switch($name) {
            case 'name':
                return $this->name;
                break;
            // Todo: provide more info
        }
        return null;
    }

    //
    // SELECT Stuff
    //

    /**
     * Reset all SELECT parts or a specific one (eg. "where")
     * @param string $part
     * @return \Frmwrk\Model
     */
    public function reset($part = null)
    {
        if ($part == 'data') {
            $this->data = null;
        } elseif ($part === null) {
            $this->data = null;
            $this->parts = array();
        } elseif (isset($this->parts[$part])) {
            unset($this->parts[$part]);
        }
        return $this;
    }

    /**
     * Get all SELECT parts or a specific one (eg. "where")
     * 
     * @param string $part
     * @return mixed
     */
    public function getPart($part = null)
    {
        return $part ? (array_key_exists($part, $this->parts) ? $this->parts[$part] : null) : $this->parts;
    }

    /**
     * Push SELECT part to it's array
     *
     * @param string $name
     * @param mixed $value
     */
    protected function pushPart($name, $value)
    {
        if (!isset($this->parts[$name])) {
            $this->parts[$name] = array();
        }
        $this->parts[$name][] = $value;
    }

    /**
     * Add a where clause (all WHERE clauses will be imploded by 'AND')
     *
     * @param string $sql
     * @return Ips_Active_Record
     */
    public function where($sql)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            array_shift($args);
            $sql = $this->quoteInto($sql, $args);
        } elseif (is_numeric($sql)) {
            $sql = $this->quoteInto(self::PRIMARY_KEY.'=?', $sql);
        } elseif (is_array($sql)) {
            foreach ($sql as $id) {
                if (!is_numeric($id)) {
                    throw new Exception('Only ids can be passed within array');
                }
            }
            $sql = self::PRIMARY_KEY.'IN ('.implode(',', $sql).')';
        }
        $this->reset('data');
        $this->pushPart('where', $sql);
        return $this;
    }

    /**
     * Generates a simple mysql LIKE statement to use in WHERE clauses
     *
     * @param string $keys The keys (searchstring) to search for
     * @param string|array $columns The columns in which to search
     * @param string $quot The quotation symbol to use
     * @param string $operator
     * @return Ips_Active_Record
     */
    public function like($keys, $columns = null, $quot = "'", $operator = 'OR')
    {
        // Replace wildcards with MySQL/PostgreSQL wildcards.
        $keys = strtolower(preg_replace('!\*+!', '%', $keys));
        $slug = "$quot%%$keys%%$quot";
        if (!$columns) {
            return $slug;
        }
        $columns = (array) $columns;
        $before = 'LOWER(';
        $after = ') LIKE '.$slug;

        return $this->where($before.implode($after.' '.trim($operator).' '.$before, $columns).$after);
    }

    /**
     * Quote a value for DB usage
     *
     * @param mixed $value
     * @return string
     */
    public function quote($value)
    {
        if (is_int($value)) {
            return $value;
        } elseif (is_float($value)) {
            return sprintf('%F', $value);
        }
        return "'" . addcslashes($value, "\000\n\r\\'\"\032") . "'";
    }

    /**
     * Quote $values into $sql string (replace question marks by values)
     * 
     * @param string $sql
     * @param string $values
     * @return string
     */
    public function quoteInto($sql, $values)
    {
        $values = (array) $values;
        foreach ($values as $i => $value) {
            $values[$i] = $this->quote($value);
        }
        return str_replace('?', count($values) > 1 ? $values : array_shift($values), $sql);
    }

    /**
     * Add an ORDER BY statement
     *
     * @param string $sql
     * @return Ips_Active_Record
     */
    public function order($sql)
    {
        $this->reset('data');
        $this->pushPart('order', $sql);
        return $this;
    }

    /**
     * LIMIT the results (default LIMIT is 0, 50)
     *
     * @param int $limit
     * @param int $offset
     * @return Ips_Active_Record
     */
    public function limit($limit, $offset = 0)
    {
        $this->reset('data');
        $this->parts['limit'] = array($offset, $limit);
        return $this;
    }

    /**
     * Assemble the SQL and fetch the results to $this->data
     * @throws Exception
     */
    protected function fetch()
    {
        if (is_array($this->data) || $this->rowSet) {
            return;
        }

        $sql = $this->assemble();
        $res = $this->getAdapter()->query($sql);
        if (!$res) {
            $info = $this->getAdapter()->errorInfo();
            var_dump($sql);
            throw new Exception('['.$info[1].']: '.$info[2]);
        }

        $data = array();
        foreach ($res as $row) {
            $data[] = $row;
        }
        $this->data = $data;
    }

    /**
     * Assemble the SQL query
     *
     * @throws Exception
     * @return string
     */
    public function assemble($ommitSelect = false)
    {
        $tableSql = '`'.$this->name.'` ';
        $sql = (!$ommitSelect) ? 'SELECT '.$tableSql.'.* FROM ' : '';
        $sql .= $tableSql;
        $sql .= 'WHERE '.(isset($this->parts['where']) ? implode(' AND ', $this->parts['where']) : 1);
        if (isset($this->parts['order'])) {
            $sql .= ' ORDER BY '.implode(', ', $this->parts['order']);
        }
        if (isset($this->parts['limit'])) {
            $sql .= ' LIMIT '.$this->parts['limit'][0].', '.$this->parts['limit'][1];
        }

        return $sql;
    }

    //
    // Rowset/Row stuff
    //

    /**
     * Create a row from given offset (when in rowset mode)
     *
     * @param int $offset
     * @throws Exception
     * @return Ips_Active_Record
     */
    protected function createRow($offset)
    {
        if ($this->rowSet) {
            throw new Exception('Can\'t create rows from row');
        }
        if (!array_key_exists($offset, $this->data)) {
            throw new Exception('No row found at '.$offset);
        }

        $tempData = $this->data;
        $this->rowSet = $this;
        $this->data = $this->data[$offset];

        $row = clone $this;

        $this->rowSet = null;
        $this->data = $tempData;

        return $row;
    }

    /**
     * Unset a column on the current row or all rows when $this->isRowset()
     * This makes the column disappear from the Models but not from the DB and
     * also doesn't set anything
     * 
     * @param string $column
     */
    public function __unset($column) {
        if (!$this->rowSet) {
            foreach ($this as $row) {
                $row->__unset($column);
            }
        } else {
            if ($this->__isset($column)) {
                unset($this->data[$column]);
            }
        }
    }

    /**
     * Checks if all rows or the current row have a $column
     * 
     * @param string $column
     * @return boolean
     */
    public function __isset($column) {
        if (!$this->rowSet) {
            foreach ($this as $row) {
                return $row->__isset($column);
            }
        } else {
            try {
                $this->__get($column);
                return true;
            } catch (Exception $e) {
                return false;
            }
        }
    }

    /**
     * Set the $value of $column on this row or all rows when $this->isRowset()
     * 
     * 1. If your model has a method named set.ucfirst($column) it will be called
     *    and has to care for the rest.
     * 2. If there is a relation named $column on your model, this will be set.
     *    Pass them as \Frmwrk\Model instances - except you want to create the
     *    related rows on the fly - then pass them as array:
     *    belongsTo/hasOne columns as column/value arrays, hasMany columns as
     *    array of those that arrays.
     * 3. Simply set the underscored $column in $this->data
     * 
     * @param string $column
     * @param string|int|Model|array $value
     * @throws Exception
     */
    public function __set($column, $value)
    {
        if (!$this->rowSet) {
            foreach ($this as $row) {
                $row->__set($column, $value);
            }
            return;
        }
        
        if ($column == self::PRIMARY_KEY) {
            throw new Exception('Primary key may not be set');
        }
        
        if ($this->origData === null) {
            $this->origData = $this->data;
        }
        
        $info = $this->getPropertyInfo($column);
        
        if ($info['setter']) {
            $this->{$info['setter']}($value);
        } elseif ($info['relation']) {
            $modelClass = $info['relation']['class'];
            $type = $info['relation']['type'];
            if ($value instanceof $modelClass) {
                $model = $value;
            } else {
                // Validate the data format
                if (!is_array($value)) {
                    throw new Exception('Invalid value for relation');
                }
                if ($type == 'hasMany') {
                    foreach ($value as $k => $v) {
                        if (!is_numeric($k) || !is_array($v) && !$v instanceof $modelClass) {
                            throw new Exception('Invalid formated relation array');
                        }
                    }
                }
                
                if ($type == 'belongsTo') {
                    $model = new $modelClass($value);
                } else {
                    if ($type == 'hasOne') {
                        $value = array($value);
                    }
                    if ($this->isNew()) {
                        $rowset = new $modelClass();
                        $rowset->data = array();
                    } else {
                        $rowset = $this->__get($column)->getRowset();
                        // Remove all current rows
                        for ($i = 0; $i < count($rowset); $i++) {
                            $rowset[$i]->offsetUnset($i);
                        }
                    }
                    foreach (array_values($value) as $i => $row) {
                        $rowset->offsetSet($i, $row);
                    }
                    $model = ($type == 'hasOne') ? $rowset->first() : $rowset;
                }
            }
            
            if ($type == 'belongsTo') {
                $this->data[Inflector::foreignKey($modelClass)] = $model->{self::PRIMARY_KEY};
            } else {
                $model->__set(
                    Inflector::propertyfy($this),
                    $this
                );
            }
            
            $this->relatedModels[$column] = $model;
        } elseif ($info['column']) {
            if ($info['related'] && $this->data[$info['column']] != $value) {
                // Force recreation of the model from {@see __get()}
                unset($this->relatedModels[$info['related']]);
            }
            $this->data[$info['column']] = $value;
        } else {
            $underscored = Inflector::underscore($column);
            $this->data[$underscored] = $value;
            $this->propertyInfo[$column]['column'] = $underscored;
        }
    }

    /**
     * Retrieve data from the model
     * 
     * 1. If your model has a method named get.ucfirst($column) it will be called
     *    and has to return the value.
     * 2. If your model has a relation named as the $column, that will be queried
     *    and returned as Model instance (as rowset when it is a hasMany, as row
     *    when it's a belongsTo or hasOne relation)
     * 3. The column from the database will be returned
     * 
     * @param string $column
     * @throws Exception When column could not be found
     * @return string|int|Model
     */
    public function __get($column)
    {
        if (!$this->rowSet) {
            $data = array();
            foreach ($this as $i => $row) {
                $data[$i] = $row->__get($column);
            }
            return $data;
        }
        
        if ($column == self::PRIMARY_KEY) {
            return $this->isNew() ? null : $this->data[self::PRIMARY_KEY];
        }

        $info = $this->getPropertyInfo($column);

        if ($info['getter']) {
            return $this->{$info['getter']}();
        } elseif ($info['relation']) {
            $type = $info['relation']['type'];
            if (!array_key_exists($column, $this->relatedModels)) {
                $modelClass = $info['relation']['class'];
                // TODO: Avoid unnecessary selects when $this->isNew()
                if ($type == 'belongsTo') {
                    $select = $modelClass::select(
                        (int) $this->__get(Inflector::foreignKey($modelClass))
                    );
                } else {
                    $select = $modelClass::select(
                        Inflector::foreignKey($this).'='.$this->__get(self::PRIMARY_KEY)
                    );
                }
                $this->relatedModels[$column] = $type == 'hasMany' ? $select : $select->first();
            }
            return $this->relatedModels[$column];
        } elseif ($info['column']) {
            return $this->data[$info['column']];
        } else {
            throw new Exception($column.' is not in the row');
        }
    }

    /**
     * Get the info for a property ($this->column) 
     * 
     * @param string $column
     * @throws Exception
     * @return array
     */
    protected function getPropertyInfo($column)
    {
        if (!array_key_exists($column, $this->propertyInfo)) {
            $info = array();
            
            // Determine getter/setter
            $camelCase = Inflector::camelize($column);
            foreach (array('get', 'set') as $md) {
                $fn = $md.$camelCase;
                $info[$md.'ter'] = is_callable(array($this, $fn)) ? $fn : null;
            }
            
            // Find relation information
            $info['relation'] = null;
            foreach (array('hasMany', 'hasOne', 'belongsTo') as $type) {
                if (!is_array($this->{$type})) {
                    throw new Exception('Relations must be defined as arrays');
                }
                if (in_array($column, $this->{$type})) {
                    if ($info['relation']) {
                        throw new Exception(
                            $column.' can\'t be in more than one relation '.
                            'array  (found in '.$info['relation']['type'].
                            ' and '.$type.')'
                        );
                    }
                    if ($type == 'hasMany') {
                        $modelClass = Inflector::classify(
                            Inflector::singularize($column), 
                            $this
                        );
                    } else {
                        $modelClass = Inflector::classify($column, $this);
                    }
                    $info['relation'] = array(
                        'type' => $type, 
                        'class' => $modelClass
                    );
                }
            }
            
            // Find column
            if (array_key_exists($column, $this->data)) {
                $info['column'] = $column;
            } else {
                $underscored = Inflector::underscore($column);
                if (array_key_exists($underscored, $this->data)) {
                    $info['column'] = $underscored;
                } else {
                    $info['column'] = null;
                }
            }
            
            // Find related property for foreign key column
            $info['related'] = null;
            $column = $info['column'] ? $info['column'] : $underscored;
            if (!$info['relation'] && substr($column, -3) == '_id') {
                // Probably this is a foreign key column (like project_id)
                $relatedProperty = substr($column, 0, -3);
                $relatedPropertyInfo = $this->getPropertyInfo($relatedProperty);
                if ($relatedPropertyInfo['relation']) {
                    $info['related'] = $relatedProperty;
                    $info['column'] = $column;
                }
            }
            
            $this->rowSet->propertyInfo[$column] = $info;
        }
        return $this->rowSet->propertyInfo[$column];
    }

    /**
     * If this row is already in the database or not
     * (returns NULL, when this couldn't be detected)
     * 
     * @return boolean|NULL
     */
    protected function isNew()
    {
        if (!is_array($this->data)) {
            return null;
        }
        if ($this->isRowset()) {
            $isNew = null;
            foreach ($this as $row) {
                if ($isNew === null) {
                    $isNew = $row->isNew();
                }
                if ($row->isNew() !== $isNew) {
                    return null;
                }
            }
            return $isNew;
        }
        return !array_key_exists(self::PRIMARY_KEY, $this->data);
    }

    /**
     * DELETE a set of rows
     * 
     * @todo Also delete the rows of the related tables (hasOne, hasMany)
     * 
     * @param string $where
     * @throws Exception
     * @return boolean If the 
     */
    public static function delete($where)
    {
        $class = get_called_class();
        if ($class == __CLASS__) {
            throw new Exception(
                __METHOD__.' must be called on a concrete model implementation'
            );
        }
        /* @var $instance Model */
        $instance = new $class;
        $args = func_get_args();
        call_user_func_array(array($instance, 'where'), $args);
        $instance->reset('limit');
        $sql = 'DELETE FROM ';
        $sql .= $instance->assemble(true);
        $adapter = $instance->getAdapter();
        $adapter->beginTransaction();
        $res = $adapter->exec($sql);
        if ($res === false) {
            $adapter->rollBack();
            $info = $adapter->errorInfo();
            throw new Exception('['.$info[1].']: '.$info[2]);
        }
        $adapter->commit();
        
        return $res;
    }
    
    /**
     * Immediately DELETE the current row or all rows in rowset when 
     * $this->isRowset()
     * 
     * @return \Frmwrk\Model
     */
    public function remove()
    {
        if (!$this->rowSet) {
            foreach ($this as $i => $row) {
                $this->offsetUnset($i);
                if (!$row->isNew()) {
                    $row->__unset(self::PRIMARY_KEY);
                }
            }
            return $this->save();
        }
        if (!$this->isNew()) {
            $class = get_class($this);
            $class::delete($this->{self::PRIMARY_KEY});
            $this->__unset(self::PRIMARY_KEY);
        }
        foreach ($this->rowSet as $i => $row) {
            if ($row === $this) {
                $this->rowSet->offsetUnset($i);
            }
        }
        return $this;
    }

    /**
     * Save all changes to the current model to the database:
     * 1. Save all rows that are still in the rowset when $this->isRowset()
     * 2. DELETE all rows that were removed when $this->isRowset()
     * 3. Save the belongsTo-Relations to get the primary IDs for current row
     * 4. Save (UPDATE/INSERT) the current row
     * 5. Save hasOne/hasMany relations
     * 
     * @throws Exception
     * @return \Frmwrk\Model
     */
    public function save()
    {
        if ($this->isRowset()) {
            $keepRowIds = array();
            foreach ($this as $row) {
                if (!$row->isNew()) {
                    $keepRowIds[] = $row->{self::PRIMARY_KEY};
                }
                $row->save();
            }
            $deleteRowIds = array_diff(array_unique($this->removedRowIds), $keepRowIds);
            if (count($deleteRowIds)) {
                $class = get_class($this);
                $class::delete(array_unique($deleteRowIds));
            }
            return $this;
        }
        
        $dependentModels = array();
        foreach ($this->relatedModels as $column => $model) {
            $info = $this->getPropertyInfo($column);
            if ($info['relation']['type'] == 'belongsTo') {
                if ($model->isNew()) {
                    $model->save();
                }
                $id = $model->{self::PRIMARY_KEY};
                $this->data[Inflector::foreignKey($model)] = $id;
            } else {
                $dependentModels[] = $model;
            }
        }
        
        if ($this->origData !== null) {
            $data = array_diff_assoc($this->data, $this->origData);
            if (array_key_exists(self::PRIMARY_KEY, $data)) {
                unset($data[self::PRIMARY_KEY]);
            }
            if (count($data)) {
                foreach ($data as $column => $value) {
                    $data[$column] = $this->quote($value);
                }
                if ($this->isNew()) {
                    $sql = 'INSERT INTO '.$this->name.' ('.
                        implode(',', array_keys($data)).
                        ') VALUES ('.implode(',', $data).');';
                } else {
                    $sql = 'UPDATE '.$this->name.' SET ';
                    foreach ($data as $column => $value) {
                        $sql .= $column.'='.$value;
                    }
                    $sql .= ' WHERE '.self::PRIMARY_KEY.'='.$this->{self::PRIMARY_KEY}.';';
                }
                
                $adapter = $this->getAdapter();
                $adapter->beginTransaction();
                $res = $adapter->query($sql);
                if ($res === false) {
                    $adapter->rollBack();
                    $info = $adapter->errorInfo();
                    throw new Exception('['.$info[1].']: '.$info[2]);
                }
                if ($this->isNew()) {
                    // lastInsertId returns 0 after commit - otherwise the
                    // transaction stuff could be in a separate method
                    $this->data[self::PRIMARY_KEY] = $this->getAdapter()->lastInsertId();
                }
                $adapter->commit();
            }
            $this->origData = null;
        }
        
        $id = $this->{self::PRIMARY_KEY};
        foreach ($dependentModels as $model) {
            $model->{Inflector::foreignKey($this)} = $id;
            $model->getRowset()->save();
        }
        
        return $this;
    }

    /**
     * Get the first row from a rowset
     *
     * @return Model|NULL
     */
    public function first()
    {
        if ($this->rowSet) {
            return $this->rowSet->first();
        }
        return $this->offsetExists(0) ? $this->offsetGet(0) : null;
    }
    
    /**
     * Get the rowset
     * 
     * @return Model
     */
    public function getRowset()
    {
        return $this->isRowset() ? $this : $this->rowSet;
    }
    
    /**
     * If the current model instance is in rowset mode
     * 
     * @return boolean
     */
    public function isRowset()
    {
        return !$this->rowSet;
    }

    /* (non-PHPdoc)
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($offset)
    {
        if ($this->rowSet) {
            return $this->__isset($offset);
        }
        $this->fetch();
        return array_key_exists($offset, $this->data);
    }

    /* (non-PHPdoc)
     * @see ArrayAccess::offsetGet()
     */
    public function offsetGet($offset)
    {
        if ($this->rowSet) {
            return $this->__get($offset);
        }
        $this->fetch();
        if (!$this->offsetExists($offset)) {
            throw new Exception('No data at offset '.$offset);
        }
        if (!$this->data[$offset] instanceof self) {
            $this->data[$offset] = $this->createRow($offset);
        }
        return $this->data[$offset];
    }

    /* (non-PHPdoc)
     * @see ArrayAccess::offsetSet()
     */
    public function offsetSet($offset, $value)
    {
        if ($this->rowSet) {
            $this->__set($offset, $value);
            return;
        }
        $this->fetch();
        if ($this->offsetExists($offset)) {
            $this->offsetUnset($offset);
        }
        $this->data[$offset] = $value;
    }

    /* (non-PHPdoc)
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($offset)
    {
        if (!$this->rowSet) {
            $this->fetch();
            if (array_key_exists(self::PRIMARY_KEY, $this->data[$offset])) {
                $this->removedRowIds[] = $this->data[$offset][self::PRIMARY_KEY];
            }
            unset($this->data[$offset]);
        } else {
            $this->__unset($offset);
        }
    }

    /* (non-PHPdoc)
     * @see \IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        $this->fetch();
        if (!$this->rowSet) {
            foreach ($this->data as $offset => $row) {
                if (!$row instanceof self) {
                    $this->data[$offset] = $this->createRow($offset);
                }
            }
        }
        return new \ArrayIterator($this->data);
    }

    /* (non-PHPdoc)
     * @see Countable::count()
     */
    public function count()
    {
        $this->fetch();
        return count($this->data);
    }
    
    /**
     * Set data on a row from array
     * 
     * @param array $data
     * @throws Exception
     * @return \Frmwrk\Model
     */
    public function setFromArray(array $data)
    {
        if (!$this->rowSet) {
            throw new Exception('Can not set from array on rowset');
        }
        foreach ($data as $key => $value) {
            $this->__set($key, $value);
        }
        return $this;
    }

    /**
     * Get array representation of current model
     * @return array
     */
    public function toArray()
    {
        $this->fetch();
        return $this->data;
    }
}