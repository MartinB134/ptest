<?php
/**
 * Frmwrk - the lightweight framework for the Netresearch programming test
 */

namespace Frmwrk;

/**
 * A very simple translator
 *
 * @package    Frmwrk
 * @subpackage Translator
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 */
class Translator
{
    /**
     * The language to use
     * @var string
     */
    protected static $language = 'en';
    
    /**
     * The directory, where the language files reside
     * @var string
     */
    protected static $directory;
    
    /**
     * The loaded translations
     * @var array
     */
    protected static $translations = array();

    /**
     * Set the language code to use
     * 
     * @param string $language
     */
    public static function setLanguage($language)
    {
        self::$language = $language;
    }
    
    /**
     * Set the directory, where the language files reside
     * 
     * @param string $dir
     * @throws Exception
     */
    public static function setDirectory($dir)
    {
        if (!file_exists($dir)) {
            throw new Exception('Directory doesn\'t exist');
        }
        self::$directory = $dir;
    }
    
    /**
     * Translate a key - falls back to the key itself when no translation is
     * found
     * 
     * @param string $key
     * @throws Exception
     * @return string
     */
    public static function translate($key)
    {
        if (!self::$language) {
            throw new Exception('No language set');
        }
        if (!array_key_exists(self::$language, self::$translations)) {
            if (!self::$directory) {
                throw new Exception('No directory set');
            }
            $path = realpath(self::$directory.DIRECTORY_SEPARATOR.self::$language.'.php');
            if (!$path) {
                throw new Exception('Translation file '.$path.' missing');
            }
            self::$translations[self::$language] = include_once $path;
            if (!is_array(self::$translations[self::$language])) {
                throw new Exception($path.' must return translation array');
            }
        }
        return array_key_exists($key, self::$translations[self::$language]) ? self::$translations[self::$language][$key] : $key;
    }
}