<?php
/**
 * Frmwrk - the lightweight framework for the Netresearch programming test
 */

namespace Frmwrk;

/**
 * Something similar to rails' model inflector utility
 *
 * @package    Frmwrk
 * @subpackage Inflector
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 */
class Inflector
{
    /**
     * Singular to plural map (keys are singular, values plural)
     * @var array
     */
    protected static $pluralMap = array();
    
    /**
     * Patterns to find the singular from plurals
     * @var array
     */
    protected static $singularPatterns = array(
        '/ies$/' => 'y'
    );
    
    /**
     * Patterns to find the plural from singulars
     * @var array
     */
    protected static $pluralPatterns = array(
        '/y$/' => 'ies'
    );
    
    /**
     * Get the plural of a singular noun
     * 
     * @param string $singular
     * @return string
     */
    public static function pluralize($singular)
    {
        if (array_key_exists($singular, self::$pluralMap)) {
            return self::$pluralMap[$singular];
        }
    
        foreach (array_reverse(self::$singularPatterns) as $pattern => $replacement) {
            $plural = preg_replace($pattern, $replacement, $singular);
            if ($plural != $singular) {
                self::$pluralMap[$singular] = $plural;
                return $plural;
            }
        }
        
        return self::$pluralMap[$singular] = $singular.'s';
    }
    
    /**
     * Get the singular of noun in plural
     * 
     * @param string $plural
     * @return string
     */
    public static function singularize($plural)
    {
        $singular = array_search($plural, self::$pluralMap);
        if ($singular !== false) {
            return $singular;
        }
    
        foreach (array_reverse(self::$pluralPatterns) as $pattern => $replacement) {
            $singular = preg_replace($pattern, $replacement, $plural);
            if ($plural != $singular) {
                self::$pluralMap[$singular] = $plural;
                return $singular;
            }
        }
        
        $singular = substr($plural, -1) == 's' ? substr($plural, 0, -1) : $plural;
        
        self::$pluralMap[$singular] = $plural;
        return $singular;
    }
    
    /**
     * Set the plural for an irregular noun
     * 
     * @param string $singular
     * @param string $plural
     */
    public static function irregular($singular, $plural)
    {
        self::$pluralMap[$singular] = $plural;
    }
    
    /**
     * Mark a noun uncountable (eg. "people")
     * 
     * @param string $singularAndPlural
     */
    public static function uncountable($singularAndPlural)
    {
        self::$pluralMap[$singularAndPlural] = $singularAndPlural;
    }
    
    /**
     * Add a pattern to get the singular from a plural
     * 
     * @param string $pluralPattern       eg. '/ies$/'
     * @param string $singularReplacement eg. 'y'
     */
    public static function singular($pluralPattern, $singularReplacement)
    {
        self::$singularPatterns[$pluralPattern] = $singularReplacement;
    }
    
    /**
     * Add a pattern to get the plural of a singular
     * 
     * @param string $singularPattern   eg. '/y$/'
     * @param string $pluralReplacement eg.  'ies'
     */
    public static function plural($singularPattern, $pluralReplacement)
    {
        self::$pluralPatterns[$singularPattern] = $pluralReplacement;
    }
    
    /**
     * Get the namespace portion of an string or objects class
     * 
     * @param string|object $str
     * @return string
     */
    public static function getNamespace($str)
    {
        if (is_object($str)) {
            $str = get_class($str);
        }
        return substr($str, 0, strrpos($str, '\\') + 1);
    }
    
    /**
     * Removes the namespace part from the expression in the string.
     * 
     * @param string|object $str
     * @return string
     */
    public static function removeNamespace($str)
    {
        if (is_object($str)) {
            $str = get_class($str);
        }
        $pos = strrpos($str, '\\') + 1;
        return $pos !== false ? substr($str, strrpos($str, '\\') + 1) : $str;
    }
    
    /**
     * Add the namespace of $ns to the $str
     * 
     * @param string        $str
     * @param string|object $ns
     * @return string
     */
    public static function addNamespace($str, $ns = null)
    {
        if (is_object($ns)) {
            return self::getNamespace($ns).$str;
        } elseif ($ns) {
            return rtrim($ns, '\\').'\\'.$str;
        } else {
            return $str;
        }
    }
    
    /**
     * Get the foreign key for the $table in another table
     * 
     * @param string|object $table
     * @param string        $idPostfix
     * @return string
     */
    public static function foreignKey($table, $idPostfix = Model::PRIMARY_KEY)
    {
        return self::tableize($table).'_'.$idPostfix;
    }


    /**
     * Get the tablename representation (NOT YET PLURALIZED) of $object
     * 
     * @param string|object $object
     * @return string
     */
    public static function tableize($object)
    {
        return self::underscore(self::removeNamespace($object));
    }
    
    /**
     * Get the (optionally namespaced) class representation of a string
     * 
     * @param string        $str
     * @param string|object $ns
     * @return string
     */
    public static function classify($str, $ns = null)
    {
        return self::addNamespace(self::camelize($str), $ns);
    }
    
    /**
     * Get the property/variable representation of a string
     * 
     * @param string|object $str
     * @return string
     */
    public static function propertyfy($str)
    {
        return self::camelize(self::removeNamespace($str), false);
    }
    
    /**
     * CamelCase or camelCase a string
     * 
     * @param string  $str
     * @param boolean $uppercaseFirstLetter
     * @return string
     */
    public static function camelize($str, $uppercaseFirstLetter = true)
    {
        $str = str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
        if (!$uppercaseFirstLetter) {
            return strtolower(substr($str, 0, 1)).substr($str, 1);
        }
        return $str;
    }
    
    /**
     * Makes an underscored, lowercase form from the expression in the string.
     * 
     * @param string $str
     * @return string
     */
    public static function underscore($str)
    {
        return strtolower(preg_replace('/(?!^)([A-Z ])/', '_$1', $str));
    }
} 