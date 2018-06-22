<?php
/**
 * The Netresearch programming test
 */

namespace App\Model;

/**
 * The projects model
 *
 * @package    App
 * @subpackage Model
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 */
class Project extends \Frmwrk\Model
{
    /**
     * Enter description here ...
     * @var unknown_type
     */
    protected $hasMany = array(
        'issues'
    );
}