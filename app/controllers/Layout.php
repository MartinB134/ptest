<?php
/**
 * The Netresearch programming test
 */

namespace App\Controller;

/**
 * The layout controller - provides some vars for the layout view script
 *
 * @package    App
 * @subpackage Controller
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 */
class Layout extends \Frmwrk\Controller
{
    /**
     * The main menu items
     * @var array
     */
    protected $menu = array(
        'index' => array(
            'title' => 'Home',
            'actions' => array()
        ),
        'projects' => array(
            'title' => 'Projects',
            'actions' => array()
        ),
    );
    
    /**
     * Action for default layout
     */
    public function defaultAction()
    {
        $this->view->content = $this->getResponse()->getBody();
        $this->getResponse()->clearBody();
        
        $this->completeMenu();
        $this->findActive();
        
        $this->view->menu = $this->menu;
        $this->view->basePath = $this->getRequest()->getBasePath();
    }
    
    /**
     * Find active menu item
     */
    protected function findActive()
    {
        $history = $this->getApplication()->getHistory();
        if (array_key_exists($history->controller, $this->menu)) {
            $controller = $history->controller;
        } else {
            $controller = 'index';
        }
    
        foreach ($this->menu as $c => &$item) {
            $item['active'] = ($c == $controller);
        }
        
        if (!array_key_exists($controller, $this->menu)) {
            return;
        }
        
        $title = $this->menu[$controller]['title'];
        if (array_key_exists($history->action, $this->menu[$controller]['actions'])) {
            $title .= ' &gt; '.$this->menu[$controller]['actions'][$history->action];
        }
        $paramsPath = implode('/', $history->params);
        if (array_key_exists($paramsPath, $this->menu[$controller]['actions'])) {
            $title .= ' &gt; '.$this->menu[$controller]['actions'][$paramsPath];
        }
        
        $this->view->title = $title;
    }
    
    /**
     * Add projects to the menu
     */
    protected function completeMenu()
    {
        foreach (\App\Model\Project::select() as $project) {
            $this->menu['projects']['actions']['show/project/'.$project->id] = $project->title;
        }
        $this->menu['projects']['actions'][] = '--';
        $this->menu['projects']['actions']['create'] = \Frmwrk\Translator::translate('project_create');
    }
}