<?php
/**
 * Frmwrk - the lightweight framework for the Netresearch programming test
 */

namespace Frmwrk;

/**
 * Base exception for Frmwrk
 *
 * @package    Frmwrk
 * @subpackage Exception
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 */
class FlashMessages
{
    const INFO = 1;
    
    const WARNING = 2;
    
    const ERROR = 3;
    
    const SUCCESS = 4;
    
    protected static $sessionKey = 'FlashMessage::$messages';
    
    public static function add($message, $severity = self::INFO)
    {
        if (!array_key_exists(self::$sessionKey, $_SESSION)) {
            $_SESSION[self::$sessionKey] = array();
        }
        
        $classes = array(
            self::INFO => 'info',
            self::WARNING => 'warning',
            self::ERROR => 'error',
            self::SUCCESS => 'success'
        );
        
        $_SESSION[self::$sessionKey][] = array(
            'message' => $message,
            'severity' => $severity,
            'class' => array_key_exists($severity, $classes) ? $classes[$severity] : null
        );
    }
    
    public static function getAll($clear = true)
    {
        if (!array_key_exists(self::$sessionKey, $_SESSION)) {
            return array();
        }
        $messages = $_SESSION[self::$sessionKey];
        if ($clear) {
            unset($_SESSION[self::$sessionKey]);
        }
        return $messages;
    }
}