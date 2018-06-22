<?php
/**
 * Frmwrk - the lightweight framework for the Netresearch programming test
 */

namespace Frmwrk;

/**
 * The auto loader for Frmwrk
 * 
 * <example title="Common initialization and configuration">
 * require_once 'Frmwrk/Loader.php';
 * Frmwrk\Loader::registerNamespace('Frmwrk');
 * Frmwrk\Loader::registerNamespace('App', array(
 *     'Model' => 'app/models',
 *     'Controller' => 'app/controllers',
 *     'Form' => 'app/forms'
 * ));
 * </example>
 *
 * @package    Frmwrk
 * @subpackage Loader
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 */
class Loader
{
    /**
     * The namespaces that this Loader cares for
     * @var array
     */
    protected static $_namespaces = array();

    /**
     * If autoloader is already registered
     * @var boolean
     */
    protected static $_initialized = false;

    /**
     * The autoloading method - checks is in one of the registered namespaces
     * and tries to load it from the calculated path. If the class is not found
     * after unsuccesfull inclusion it's generated on the fly and throws an
     * exception in its constructor method.
     *
     * @param string $className The name of a class to load
     * @return void
     */
    public static function autoload($className)
    {
        $parts = explode('\\', $className);

        if (!array_key_exists($parts[0], self::$_namespaces)) {
            return;
        }

        if (self::$_namespaces[$parts[0]]) {
            if (array_key_exists($parts[1], self::$_namespaces[$parts[0]])) {
                array_unshift($parts, self::$_namespaces[$parts[0]][$parts[1]]);
                unset($parts[1], $parts[2]);
            }
        }
        
        $file = implode('/',$parts).'.php';
        
        if (function_exists('stream_resolve_include_path')) {
            $path = stream_resolve_include_path($file);
        } else {
            $path = false;
            foreach(explode(PATH_SEPARATOR, get_include_path()) as $p) {
                $fullname = $p.DIRECTORY_SEPARATOR.$file;
                if(is_file($fullname)) {
                    $path = $fullname;
                    break;
                }
            }
        }
        
        if ($path !== false) {
            include_once($file);
        }
    }

    /**
     * (Initialize) and register a namespace that should be used for autoloading
     *
     * @param string     $namespace
     * @param array|null $resources Resources
     */
    public static function registerNamespace($namespace, $resources = null)
    {
        if (!self::$_initialized) {
            spl_autoload_register(array(__CLASS__, 'autoload'));
            self::$_initialized = true;
        }
        self::$_namespaces[$namespace] = $resources ? (array) $resources : null;
    }

    /**
     * Get the registered namespaces
     * 
     * @return array
     */
    public static function getNamespaces()
    {
        return self::$_namespaces;
    }
}