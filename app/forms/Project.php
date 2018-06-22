<?php
/**
 * The Netresearch programming test
 */

namespace App\Form;

/**
 * The projects form
 *
 * @package    App
 * @subpackage Form
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 */
class Project extends \Frmwrk\Form
{
    /* (non-PHPdoc)
     * @see Frmwrk.Form::init()
     */
    public function init() {
        $this->addElement('title', 'input', array(
            'label' => 'project_title',
            'placeholder' => 'project_title_placeholder',
            'required' => true,
            'size' => 20 
        ));
    }
}
