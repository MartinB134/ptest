<?php
/**
 * Frmwrk - the lightweight framework for the Netresearch programming test
 */

namespace Frmwrk;

/**
 * The central application class
 *
 * @package    Frmwrk
 * @subpackage Application
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 */
class Application
{    
    /**
     * Settings array
     * @see getSetting()
     * @var array
     */
    protected $settings;

    /**
     * Namespace of application classes (controllers, models, forms etc)
     * @var string
     */
    protected $appNamespace = 'App';

    /**
     * Whether to throw exceptions while running
     * @var boolean
     */
    protected $throwExceptions = true;
    
    /**
     * @var Request
     */
    protected $request;
    
    /**
     * @var Response
     */
    protected $response;
    
    /**
     * controller/action history
     * @var \ArrayObject
     */
    protected $history;

    /**
     * Constructor
     * 
     * @param array $settings
     */
    public function __construct(array $settings = array())
    {
        $this->settings = $settings;
        $this->appNamespace = $this->getSetting('application.namespace', 'App');  
        $this->history = new \ArrayObject(array());     
    }

    /**
     * Get a setting from settings array - access nested arrays by dot separated
     * path:
     * array('view' => 'helper' => array('url' => array('escape' => true)))
     * "view.helper.url.escape"
     * 
     * @param string $path
     * @param mixed  $default
     * @throws Exception When setting not found and no default provided
     * @return mixed
     */
    public function getSetting($path)
    {
        try {
            return $this->findSetting(explode('.', $path), $this->settings);
        } catch (Exception $e) {
            if (func_num_args() > 1) {
                return func_get_arg(1);
            }
            throw new Exception('Could not find setting '.$path);
        }
    }

    /**
     * Find a setting by an exploded path in an associative nested array
     * 
     * @param array $pathArray
     * @param array $settings
     * @throws Exception
     * @return Ambiguous
     */
    protected function findSetting($pathArray, $settings)
    {
        $key = array_shift($pathArray);
        if (!array_key_exists($key, $settings)) {
            throw new Exception('Could not find setting');
        }
        return count($pathArray) ? $this->findSetting($pathArray, $settings[$key]) : $settings[$key];
    }

    
    /**
     * Dispatch the request and run the resulting controller actions
     * 
     * @param string $path The param path
     * @throws Exception When one happens and throwExceptions is true
     * @return Response
     */
    public function run($path= NULL)
    {
        if ($path !== null) {
            $this->getRequest()->setPathInfo($path);
            var_dump($path);
        }
        do {
            $this->getRequest()->setDispatched(true);

            $route = $this->route($this->getRequest()->getPathInfo());
            if (!$route) {
                var_dump($route);
                var_dump($path);
		
		$stmt = $path->prepare('bogus sql'); 
	if (!$path) { 
    		echo "\nPDO::errorInfo():\n"; 
    		print_r($path->errorInfo()); 
		} 
                throw new Exception('No matching controller found');
            }
            
            $this->history->append($route);
            $this->getRequest()->setParams($route->params);
            $controllerClass = $route->classname;
            $controller = new $controllerClass($this->getRequest(), $this->getResponse(), $this);

            try {
                $controller->dispatch($route->action);
            } catch (Exception $e) {
                if ($this->throwExceptions()) {
                    throw $e;
                }
                $this->getResponse()->setException($e);
            }
            
        } while (!$this->getRequest()->isDispatched());

        return $this->getResponse();
    }

    /**
     * Get the controller namespace
     * 
     * @return string
     */
    public function getControllerNamespace()
    {
        return $this->appNamespace.'\\Controller';
    }

    /**
     * Try to translate a param path to a parameter array 
     * 
     * @param string $path
     * @return NULL|StdClass
     */
    protected function route($path)
    {
        $path = trim($path, '/');
        $parts = explode('/', $path);
        $classname = $this->getControllerNamespace();
        $paramStart = null;
        $controller = null;

        foreach ($parts as $i => $part) {
            if (!$part && $i > 0) {
                // Empty part inbetween
                break;
            }
            if (class_exists($classname.'\\'.ucfirst($part))) {
                $classname .= '\\'.ucfirst($part);
                $controller .= $controller ? '/'.$part : $part;
                $paramStart = $i + 1;
            } elseif (
                (!$path || $paramStart) && 
                class_exists($classname.'\\Index')
            ) {
                $classname .= '\\Index';
                $controller .= $controller ? '/index' : 'index';
                $paramStart = $i;
                break;
            }
        }

        if ($paramStart === null) {
            return null;
        }

        if (
            array_key_exists($paramStart, $parts) && 
            method_exists($classname, $parts[$paramStart].'Action')
        ) {
            $action = $parts[$paramStart];
            $paramStart++;
        } else {
            $action = 'index';
        }

        $params = array();
        for ($i = $paramStart; $i < count($parts); $i = $i + 2) {
            $key = urldecode($parts[$i]);
            $val = isset($parts[$i + 1]) ? urldecode($parts[$i + 1]) : null;
            $params[$key] = isset($params[$key]) ? 
                array_merge((array) $params[$key], array($val)) : $val;
        }

        return (object) compact('classname', 'controller', 'action', 'params');
    }
    
    /**
     * Get a route object from the history
     * 
     * @see route()
     * @param int $index
     * @return mixed
     */
    public function getHistory($index = 1)
    {
        $key = max($this->history->count() - 1 - $index, 0);
        return $this->history->offsetGet($key);
    }

    /**
     * Set/Get whether to throw exceptions when they occure while run()
     * 
     * @param boolean $flag
     * @return \Frmwrk\Application|\Frmwrk\unknown_type
     */
    public function throwExceptions($flag = null)
    {
        if ($flag !== null) {
            $this->throwExceptions = $flag;
            return $this;
        }
        return $this->throwExceptions;
    }
    
    /**
     * Getter for $request
     *
     * @return Request $request
     */
    public function getRequest()
    {
        if (!$this->request) {
            $this->request = new Request();
        }
        return $this->request;
    }

    /**
     * Getter for $response
     *
     * @return Response $response
     */
    public function getResponse()
    {
        if (!$this->response) {
            $this->response = new Response();
        }
        return $this->response;
    }

    /**
     * Setter for $request
     *
     * @param Request $request
     * @return Application
     */
    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Setter for $response
     *
     * @param Response $response
     * @return Application
     */
    public function setResponse($response)
    {
        $this->response = $response;
        return $this;
    }
}