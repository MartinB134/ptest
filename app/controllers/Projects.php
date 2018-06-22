<?php
/**
 * The Netresearch programming test
 */

namespace App\Controller;

/**
 * The projects controller
 *
 * @package    App
 * @subpackage Controller
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 */
class Projects extends \Frmwrk\Controller
{
    /**
     * Get the currently requested project
     *
     * @throws \Frmwrk\Exception
     * @return \App\Model\Project
     */
    protected function getProject()
    {
        $projectId = (int) $this->getRequest()->getParam('project');
        $project = \App\Model\Project::select('id='.$projectId)->first();

        if (!$project) {
            throw new \Frmwrk\Exception('Project not found');
        }

        return $project;
    }

    /**
     * Show the project
     */
    public function showAction()
    {
        $this->view->project = $this->getProject();
    }

    /**
     * Action to create a project
     */
    public function createAction()
    {
        $form = $this->view->form = new \App\Form\Project;
        $request = $this->getRequest();
        $this->handleSave($form);
    }

    /**
     * Action to edit/update a project
     */
    public function editAction()
    {
        $project = $this->view->project = $this->getProject();
        $form = $this->view->form = new \App\Form\Project;
        $form->setDefaults($project);
        $this->handleSave($form, $project);
    }

    /**
     * Action to delete the project and its issues
     */
    public function deleteAction()
    {
        $project = $this->getProject();
        // After removal, the primary key is reset to mark the row as new
        $projectId = $project->id;
        $project->remove();
        // Remove the issues (Currently Model::remove doesn't delete from
        // dependent tables - feel free to implement that ;))
        \App\Model\Issue::delete('project_id = ?', $projectId);

        \Frmwrk\FlashMessages::add('project_removed');
        $this->redirect(array(), array('reset' => true));
    }

    /**
     * Check if project can be saved and save it
     *
     * @param \App\Form\Project $form
     * @param \App\Model\Project $project
     * @return NULL|boolean
     */
    protected function handleSave(
        \App\Form\Project $form,
        \App\Model\Project $project = null
    ) {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return null;
        }
        $form->setValues($request->getPost());
        if (!$form->isValid()) {
            return false;
        }

        if ($project) {
            $type = 'update';
            $project->setFromArray($form->getValues());
        } else {
            $type = 'create';
            $project = new \App\Model\Project($form->getValues());
        }
        $project->save();

        \Frmwrk\FlashMessages::add(
            'project_'.$type.'d',
            \Frmwrk\FlashMessages::SUCCESS
         );

        $this->redirect(array(
            'action' => 'show',
            'project' => $project->id
        ));
    }
}