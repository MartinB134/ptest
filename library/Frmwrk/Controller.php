<?php
/**
 * Frmwrk - the lightweight framework for the Netresearch programming test
 */

namespace Frmwrk;

/**
 * Base Controller Class - based on Zend_Controller_Action
 *
 * @package    Frmwrk
 * @subpackage Controller
 */
abstract class Controller
{
    /**
     * @var array of existing class methods
     */
    protected $classMethods;

    /**
     * Word delimiters (used for normalizing view script paths)
     * @var array
     */
    protected $delimiters;

    /**
     * Array of arguments provided to the constructor, minus the
     * {@link $request Request object}.
     * @var array
     */
    protected $invokeArgs = array();

    /**
     * Front controller instance
     * @var Application
     */
    protected $application;

    /**
     * Request object wrapping the request environment
     * @var Request
     */
    protected $request = null;

    /**
     * Response object wrapping the response
     * @var Response
     */
    protected $response = null;

    /**
     * View script suffix; defaults to 'phtml'
     * @see {render()}
     * @var string
     */
    public $viewSuffix = 'phtml';

    /**
     * View object
     * @var View
     */
    public $view;

    /**
     * @var boolean
     */
    private static $_neverRender = false;

    /**
     * @var boolean
     */
    private $_noRender = false;

    /**
     * @var string
     */
    private $action;

    /**
     * Class constructor - no need to override: Override init() instead!
     *
     * @param Request $request
     * @param Response $response
     * @param Application $application
     * @return void
     */
    public function __construct(
        Request $request,
        Response $response,
        Application $application)
    {
        $this->view = new View($application);
        if (self::$_neverRender) {
            $this->_noRender = true;
        }
        $this
        ->setRequest($request)
        ->setResponse($response)
        ->setApplication($application)
        ->init();

    }

    /**
     * Initialize object
     *
     * Called from {@link __construct()} as final step of object instantiation.
     *
     * @return void
     */
    public function init()
    {
    }

    /**
     * Render a view
     *
     * Renders a view. By default, views are found in the view script path as
     * <controller>/<action>.phtml. You may change the script suffix by
     * resetting {@link $viewSuffix}. You may omit the controller directory
     * prefix by specifying boolean true for $noController.
     *
     * By default, the rendered contents are appended to the response. You may
     * specify the named body content segment to set by specifying a $name.
     *
     * @see Response::appendBody()
     * @param  string|null $action Defaults to action registered in request
     *                             object
     * @param  string|null $name   Response object named path segment to use; 
     *                             defaults to null
     * @param  bool $noController  Defaults to false; i.e. use controller name 
     *                             as subdir in which to search for view script
     * @return Controller
     */
    public function render($action = null, $name = null, $noController = false)
    {
        $script = $this->getViewScript($action, $noController);
        $this->getResponse()->appendBody($this->view->render($script), $name);
        return $this;
    }

    /**
     * Render a given view script
     *
     * Similar to {@link render()}, this method renders a view script. Unlike 
     * render(), however, it does not autodetermine the view script via 
     * {@link getViewScript()}, but instead renders the script passed to it. Use 
     * this if you know the exact view script name and path you wish to use, or 
     * if using paths that do not conform to the spec defined with 
     * getViewScript().
     *
     * By default, the rendered contents are appended to the response. You may
     * specify the named body content segment to set by specifying a $name.
     *
     * @param  string $script
     * @param  string $name
     * @return Controller
     */
    public function renderScript($script, $name = null)
    {
        $this->getResponse()->appendBody($this->view->render($script), $name);
        return $this;
    }

    /**
     * Construct view script path
     *
     * Used by render() to determine the path to the view script.
     *
     * @param  string $action     Defaults to action registered in request object
     * @param  bool $noController Defaults to false; i.e. use controller name as 
     *                            subdir in which to search for view script
     * @return string
     */
    public function getViewScript($action = null, $noController = null)
    {
        if (is_string($noController)) {
            $path = $noController.'/';
        } elseif (!$noController) {
            $class = get_class($this);
            $controllerNs = $this->application->getControllerNamespace();
            $l = strlen($controllerNs);
            if (substr($class, 0, $l) != $controllerNs) {
                throw new Exception(
                     $class.' is not within configured controller namespace'
                );
            }
            $parts = explode('\\', substr($class, $l + 1));
            foreach ($parts as &$part) {
                $part = strtolower(substr($part, 0, 1)) . substr($part, 1);
            }
            $path = implode('/', $parts).'/';
        } else {
            $path = '';
        }
        if (!$action) {
            $action = $this->action;
        }

        return $path.$action.'.'.$this->viewSuffix;
    }

    /**
     * Return the Request object
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Set the Request object
     *
     * @param Request $request
     * @return Controller
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Return the Response object
     *
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Set the Response object
     *
     * @param Response $response
     * @return Controller
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * Pre-dispatch routines
     *
     * Called before action method. may modify the {@link $_request Request}
     * object and reset its dispatched flag in order to skip processing the 
     * current action.
     *
     * @return void
     */
    public function preDispatch()
    {
    }

    /**
     * Post-dispatch routines
     *
     * Called after action method execution. If using class with
     * {@link Application}, it may modify the {@link $_request Request object} 
     * and reset its dispatched flag in order to process an additional action.
     *
     * Common usages for postDispatch() include rendering content in a sitewide
     * template, link url correction, setting headers, etc.
     *
     * @return void
     */
    public function postDispatch()
    {
    }

    /**
     * Proxy for undefined methods.  Default behavior is to throw an
     * exception on undefined methods, however this function can be
     * overridden to implement magic (dynamic) actions, or provide run-time
     * dispatching.
     *
     * @param  string $methodName
     * @param  array  $args
     * @return void
     * @throws Exception
     */
    public function __call($methodName, $args = null)
    {
        if ('Action' == substr($methodName, -6)) {
            $action = substr($methodName, 0, strlen($methodName) - 6);
            throw new Exception(sprintf(
                'Action "%s" does not exist and was not trapped in __call()', 
                $action
            ), 404);
        }

        throw new Exception(sprintf(
            'Method "%s" does not exist and was not trapped in __call()',
            $methodName
        ), 500);
    }

    /**
     * Dispatch the requested action
     *
     * @param  string $action Method name of action
     * @return void
     */
    public function dispatch($action)
    {
        $this->action = $action;
        $this->getRequest()->setDispatched(true);

        $this->preDispatch();

        if ($this->getRequest()->isDispatched()) {
            if (null === $this->classMethods) {
                $this->classMethods = get_class_methods($this);
            }
            // If pre-dispatch hooks introduced a redirect then stop dispatch
            // @see ZF-7496
            if (!($this->getResponse()->isRedirect())) {
                $this->{$action.'Action'}();
            }
            $this->postDispatch();
        }

        if (!$this->_noRender && !$this->response->isRedirect()) {
            $this->render($action);
        }
    }

    /**
     * Gets a parameter from the {@link $_request Request object}.  If the
     * parameter does not exist, NULL will be returned.
     *
     * If the parameter does not exist and $default is set, then
     * $default will be returned instead of NULL.
     *
     * @param string $paramName
     * @param mixed $default
     * @return mixed
     */
    protected function getParam($paramName, $default = null)
    {
        $value = $this->getRequest()->getParam($paramName);
         if ((null === $value || '' === $value) && (null !== $default)) {
            $value = $default;
        }

        return $value;
    }

    /**
     * Set a parameter in the {@link $_request Request object}.
     *
     * @param string $paramName
     * @param mixed $value
     * @return Controller
     */
    protected function setParam($paramName, $value)
    {
        $this->getRequest()->setParam($paramName, $value);

        return $this;
    }

    /**
     * Determine whether a given parameter exists in the
     * {@link $_request Request object}.
     *
     * @param string $paramName
     * @return boolean
     */
    protected function hasParam($paramName)
    {
        return null !== $this->getRequest()->getParam($paramName);
    }

    /**
     * Return all parameters in the {@link $_request Request object}
     * as an associative array.
     *
     * @return array
     */
    protected function getAllParams()
    {
        return $this->getRequest()->getParams();
    }


    /**
     * Forward to another controller/action.
     *
     * It is important to supply the unformatted names, i.e. "article"
     * rather than "ArticleController".  The dispatcher will do the
     * appropriate formatting when the request is received.
     *
     * If only an action name is provided, forwards to that action in this
     * controller.
     *
     * If an action and controller are specified, forwards to that action and
     * controller in this module.
     *
     * Specifying an action, controller, and module is the most specific way to
     * forward.
     *
     * A fourth argument, $params, will be used to set the request parameters.
     * If either the controller or module are unnecessary for forwarding,
     * simply pass null values for them before specifying the parameters.
     *
     * @param string $action
     * @param string $controller
     * @param array $params
     * @return void
     */
    final protected function forward($action, $controller = null, array $params = null)
    {
        $request = $this->getRequest();

        $params['controller'] = $controller ? $controller : $this->application->getHistory()->controller;
        $params['action'] = $action;
        $request
        ->setPathInfo($this->view->url($params))
        ->setDispatched(false);
    }

    /**
     * Redirect to another URL
     *
     * @param string|array $url An URL as string or an array with params to generate one
     * @param boolean|array $options Whether to exit or an array with options
     * @return void
     */
    protected function redirect($url, $options = array())
    {
        if (is_bool($options)) {
            $options = array('exit' => $options);
        }
        $options = (object) array_merge(array(
            'exit' => false,
            'code' => 302,
            'absoluteUri' => false,
            'reset' => false
        ), $options);
        if (is_array($url)) {
            $url = $this->view->url($url, $options->reset);
        }
        if ($options->absoluteUri && !preg_match('#^(https?|ftp)://#', $url)) {
            $host  = (isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:'');
            $proto = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=="off") ? 'https' : 'http';
            $port  = (isset($_SERVER['SERVER_PORT'])?$_SERVER['SERVER_PORT']:80);
            $uri   = $proto . '://' . $host;
            if ((('http' == $proto) && (80 != $port)) || (('https' == $proto) && (443 != $port))) {
                // do not append if HTTP_HOST already contains port
                if (strrchr($host, ':') === false) {
                    $uri .= ':' . $port;
                }
            }
            $url = $uri . '/' . ltrim($url, '/');
        }
        $this->getResponse()->setRedirect($url, $options->code);
        if ($options->exit) {
            $this->getResponse()->sendHeaders();
            exit;
        }
    }
    /**
     * @return boolean
     */
    public function getNeverRender()
    {
        return self::$_neverRender;
    }

    /**
     * @return boolean
     */
    public function getNoRender()
    {
        return $this->_noRender;
    }

    /**
     * En/Disable rendering of the current and all subsequent actions
     *
     * @param boolean $_neverRender
     * @return Controller
     */
    public function setNeverRender($_neverRender = true)
    {
        self::$_neverRender = $this->_noRender = $_neverRender;
        return $this;
    }

    /**
     * En/Disable rendering of the current action
     *
     * @param boolean $_noRender
     * @return Controller
     */
    public function setNoRender($_noRender = true)
    {
        $this->_noRender = $_noRender;
        return $this;
    }

    /**
     * @return Application
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @param Application $_application
     * @return Controller
     */
    public function setApplication($_application)
    {
        $this->application = $_application;
        return $this;
    }
}
