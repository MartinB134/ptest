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
class Exception extends \Exception
{
    /**
     * Construct the exception
     *
     * @param  string $msg
     * @param  int $code
     * @param  Exception $previous
     * @return void
     */
    public function __construct($msg, $code = 0, Exception $previous = null)
    {
        if (!$code) {
            $nsL = strpos(__CLASS__, '\\') + 1;
            $ns = substr(__CLASS__, 0, $nsL);
            foreach (debug_backtrace() as $i => $trace) {
                if ($i > 0 && substr($trace['class'], 0, $nsL) == $ns) {
                    $code = crc32(substr($trace['class'], $nsL));
                    break;
                }
            }
        }
        parent::__construct($msg, (int) $code, $previous);
    }
}