<?php
/**
 * Frmwrk - the lightweight framework for the Netresearch programming test
 */

namespace Frmwrk;

/**
 * A very simple validator - capable to run multiple validations and collect the
 * error codes grouped by the validation type. See Validator::prepend for docu
 * on how to add/configure the validations. 
 *
 * @package    Frmwrk
 * @subpackage Validator
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 */
class Validator extends \ArrayObject
{
    /**
     * Error codes from the validations
     * @var array
     */
    protected $errorCodes = array();
    
    /**
     * The current validation type used to group error codes while validating
     * @var string
     */
    protected $currentValidation;
    
    /**
     * If the last run validation had set an error message itself or if the
     * general error code 0 should be added when it failed
     * @var boolean
     */
    protected $lastValidationSetMessage = false;

    /**
     * Override parent constructor to cast the argument to array
     * 
     * @param array $validations
     */
    public function __construct($validations) {
        parent::__construct((array) $validations);
    }
    
    /**
     * Same as ArrayObject::append - but prepends the validation
     * 
     * @param array|string $validation 1) Validation config as array where first
     *                                 value is the type, second is an array
     *                                 with options for the validation method
     *                                 and the optional third is if validation
     *                                 chain should be broken when this validat
     *                                 ion fails (false by default). Or
     *                                 2) Validation type as string
     */
    public function prepend($validation)
    {
        $array = $this->getArrayCopy();
        array_unshift($array, $validation);
        $this->exchangeArray($array);
    }
    
    /**
     * Run all validations and collect the error codes
     * 
     * @throws Exception    On unknown validation methods     * 
     * @param  mixed $value The value to validate
     * @return boolean      True when ALL validations passed, false otherwise
     */
    public function validate($value)
    {
        $result = true;
        foreach ($this as $i => $config) {
            if (is_array($config)) {
                $this->currentValidation = array_shift($config);
                $properties = (array) array_shift($config);
                $breakChainOnFailure = array_shift($config);
            } else {
                $this->currentValidation = $config;
                $properties = array();
                $breakChainOnFailure = false;
            }
            array_unshift($properties, $value);
            
            $callback = array(
                $this, 
                'validate'.ucfirst($this->currentValidation)
            );
            if (!is_callable($callback)) {
                throw new Exception(
                    'Unknown validation "'.$this->currentValidation.'" '.
                    'at index '.$i
                );
            }
            
            $this->lastValidationSetMessage = false;
            
            if (!call_user_func_array($callback, $properties)) {
                if (!$this->lastValidationSetMessage) {
                    $this->addErrorCode(0);
                }
                if ($breakChainOnFailure) {
                    return false;
                } else {
                    $result = false;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Get all error codes
     * 
     * @return array
     */
    public function getErrorCodes()
    {
        return $this->errorCodes;
    }


    /**
     * Helper method for validation methods to add an error code
     * 
     * @param int|string $code Depends on the validation method
     */
    protected function addErrorCode($code)
    {
        if (!array_key_exists($this->currentValidation, $this->errorCodes)) {
            $this->errorCodes[$this->currentValidation] = array();
        }
        $this->errorCodes[$this->currentValidation][$code] = $code;
        $this->lastValidationSetMessage = true;
    }

    /**
     * Validate not empty values
     * 
     * @param mixed $value
     * @return boolean
     */
    protected function validateNotEmpty($value)
    {
        return $value !== null && $value !== '';
    }

    /**
     * Validate that value is numeric
     * 
     * @param mixed $value
     * @return boolean
     */
    protected function validateIsNumeric($value)
    {
        return is_numeric($value);
    }
}