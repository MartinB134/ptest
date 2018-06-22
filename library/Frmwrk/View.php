<?php
/**
 * Frmwrk - the lightweight framework for the Netresearch programming test
 */

namespace Frmwrk;

/**
 * A very simple view:
 * - Helpers are simply methods of $this (see appropriate section below)
 * - Assign vars by magic setters/getters ($this->view->var = 'value';)
 * - Renders by including an phtml-file
 *
 * @package    Frmwrk
 * @subpackage View
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 */
class View
{
    /**
     * The paths to look up for the script to render
     * @var array
     */
    protected $viewScriptPaths = array();

    /**
     * Application instance
     * @var Application
     */
    protected $application;
    
    /**
     * The variables
     * @var array
     */
    protected $vars = array();

    /**
     * Constructor - set front controller
     *
     * @param Application $application
     */
    public function __construct(Application $application)
    {
        $this->application = $application;
        $this->setViewScriptPaths((array) $application->getSetting('view.path'));
    }

    /**
     * Get values
     *
     * @param  string $key
     * @return null
     */
    public function __get($key)
    {
        return $this->__isset($key) ? $this->vars[$key] : null;
    }

    /**
     * Allows testing with empty() and isset() inside templates.
     *
     * @param  string $key
     * @return boolean
     */
    public function __isset($key)
    {
        return array_key_exists($key, $this->vars);
    }

    /**
     * Directly assigns a variable to the view script.
     *
     * Checks first to ensure that the caller is not attempting to set a
     * class property
     *
     * @param string $key The variable name.
     * @param mixed $val The variable value.
     * @return void
     * @throws Exception if an attempt to set a private or protected
     * member is detected
     */
    public function __set($key, $val)
    {
        if (array_key_exists($key, get_object_vars($this))) {
            throw new Exception('Names of class properties are reserved');
        }
        $this->vars[$key] = $val;
    }

    /**
     * Allows unset() on object properties to work
     *
     * @param string $key
     * @return void
     */
    public function __unset($key)
    {
        if ($this->__isset($key)) {
            unset($this->vars[$key]);
        }
    }

    /**
     * Assigns variables to the view script via differing strategies.
     *
     * @see    __set()
     * @param  string|array The assignment strategy to use.
     * @param  mixed (Optional) If assigning a named variable, use this
     * as the value.
     * @return View
     * @throws Exception if $spec is neither a string nor an array,
     * or if an attempt to set a private or protected member is detected
     */
    public function assign($spec, $value = null)
    {
        // which strategy to use?
        if (is_string($spec)) {
            // assign by name and value
            $this->__set($spec, $value);
        } elseif (is_array($spec)) {
            // assign from associative array
            foreach ($spec as $key => $val) {
                $this->__set($key, $val);
            }
        } else {
            throw new Exception('assign() expects a string or array, received ' . gettype($spec));
        }

        return $this;
    }

    /**
     * Render a script (given with relative path)
     * 
     * @param string $script
     * @throws Exception
     * @return string
     */
    public function render($script)
    {
        $script = ltrim($script, '\\/');
        $scriptPath = null;
        foreach ($this->getViewScriptPaths() as $path) {
            $path = rtrim($path, '\\/').DIRECTORY_SEPARATOR.$script;
            if (file_exists($path)) {
                $scriptPath = $path;
                break;
            }
        }
        if (!$scriptPath) {
            throw new Exception($script.' not found');
        }
        ob_start();
        include $scriptPath;
        return ob_get_clean();
    }

    /**
     * @return array
     */
    public function getViewScriptPaths()
    {
        return $this->viewScriptPaths;
    }

    /**
     * @param array $_viewScriptPaths
     * @return View
     */
    public function setViewScriptPaths($_viewScriptPaths)
    {
        $this->viewScriptPaths = (array) $_viewScriptPaths;
        return $this;
    }

    //
    // "View Helpers" - The very basic way:
    //
    
    /**
     * Helper: Avoid XSS by escaping data before echoing it
     * 
     * @param string $value
     * @return string
     */
    public function escape($value)
    {
        return htmlspecialchars($value);
    }
    
    /**
     * Helper: Shortcut for escape method
     * 
     * @param string $string
     * @return string
     */
    protected function e($string)
    {
        return $this->escape($string);
    }

    /**
     * Helper: Render a partial view script (separate value model - use render
     * when the partial should have the same vars as the current script)
     * 
     * @param string $script
     * @param array $model
     * @return string
     */
    public function partial($script, $model = array())
    {
        $view = new self($this->application);
        $view->assign((array) $model);
        return $view->render($script);
    }

    /**
     * Helper: Render a pagination control for a rowset model - current page
     * and items per page is taken from LIMIT select part.
     * 
     * Options can be an array with following options:
     * - quantity:  Number of page links ([1] [2] [3] [4] ...) - defaults to 5
     * - siteParam: The parameter name for the page for URL generation from the
     *              view partial that actually renders the control
     * 
     * @param Model $items
     * @param array $options
     * @return View
     */
    protected function paginationControl(Model $items, $options = array())
    {
        if (!$items->isRowset()) {
            throw new Exception('Model must be a rowset');
        }
        
        $queryLimit = $items->getPart('limit');
        if (is_array($queryLimit)) {
            $itemsPerPage = array_pop($queryLimit);
            $current = array_shift($queryLimit) / $itemsPerPage + 1;
        } else {
            $itemsPerPage = 10000;
            $current = 1;
        }

        $options = (object) array_merge(array(
            'quantity' => 5,
            'siteParam' => 'page'
        ), $options);

        $itemsCount = clone $items;
        $itemsCount
        ->reset('columns')
        ->reset('limit')
        ->columns(array('c' => 'COUNT(*)'));
        $last = ceil($itemsCount[0]->c / $itemsPerPage);

        // Calculate various markers within this pager piece:
        // Middle is used to "center" pages around the current page.
        $middlePageInRange = ceil($options->quantity / 2);
        // first is the first page listed by this pager piece (re quantity)
        $firstPageInRange = $current - $middlePageInRange + 1;
        // last is the last page listed by this pager piece (re quantity)
        $lastPageInRange = $current + $options->quantity - $middlePageInRange;
        // End of marker calculations.
        // Prepare for generation loop.
        $i = $firstPageInRange;
        if ($lastPageInRange > $last) {
            // Adjust "center" if at end of query.
            $i = $i + ($last - $lastPageInRange);
            $lastPageInRange = $last;
        }
        if ($i <= 0) {
            // Adjust "center" if at start of query.
            $lastPageInRange = $lastPageInRange + (1 - $i);
            $i = 1;
        }

        $pagesInRange = array();
        // Now generate the actual pager piece.
        for (; $i <= $lastPageInRange && $i <= $last; $i++) {
            $pagesInRange[] = $i;
        }

        // End of generation loop preparation.
        $view = new self($this->application);
        $view->assign(array(
            'previous' => $current > 1 ? $current - 1 : 0,
            'next' => $current < $last ? $current + 1 : 0,
            'last' => $last,
            'firstPageInRange' => $firstPageInRange,
            'lastPageInRange' => $lastPageInRange,
            'pagesInRange' => $pagesInRange,
            'current' => $current,
            'itemsPerPage' => $itemsPerPage,
            'pageCount' => $last,
            'siteParam' => $options->siteParam
        ));

        return $view;
    }

    /**
     * Helper: Create an URL 
     * 
     * @param array   $params Params for the URL
     * @param boolean $reset  Whether to reset current params
     * @param boolean $encode 
     */
    public function url(array $params = array(), $reset = false, $encode = true)
    {
        $history = $this->application->getHistory();
        if (!$reset) {
            $defaultParams = $history->params;
            $defaultParams['controller'] = $history->controller;
            $defaultParams['action'] = $history->action;
            $params = array_merge($defaultParams, $params);
        }
        $path = $this->application->getRequest()->getBasePath();
        foreach (array('controller', 'action') as $key) {
            if (array_key_exists($key, $params)) {
                if ($params[$key] && $params[$key] != 'index') {
                    $path .= '/'.$params[$key];
                }
                unset($params[$key]);
            }
        }
        foreach ($params as $key => $value) {
            if ($value !== null) {
                $path .= '/'.$key.'/'.urlencode($value);
            }
        }
        
        return $path ?: $path.'/';
    }
    
    /**
     * Helper: Translate a string
     * 
     * @param string $key
     * @return string
     */
    protected function translate($key)
    {
        return \Frmwrk\Translator::translate($key);
    }
}