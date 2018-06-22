<?php
/**
 * The Netresearch programming test
 */

namespace App\Model;

/**
 * The issue model
 *
 * @package    App
 * @subpackage Model
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 */
class Issue extends \Frmwrk\Model
{
    /**
     * Issues belong to projects...
     * @var array
     */
    protected $belongsTo = array(
        'project'
    );
}