<?php
session_start();

set_include_path(realpath(__DIR__.'/library').PATH_SEPARATOR.get_include_path());
require_once 'Frmwrk/Loader.php';

Frmwrk\Loader::registerNamespace('Frmwrk');
Frmwrk\Loader::registerNamespace('App', array(
    'Model' => 'app/models',
    'Controller' => 'app/controllers',
    'Form' => 'app/forms'
));

$app = new Frmwrk\Application(array(
    'view' => array(
        'path' => 'app/views'
    ),
    'db' => array(
        'user' => 'root',
        'pass' => '',
        'name' => 'ptest',
        'host' => '127.0.0.1:3307'
    )
));

Frmwrk\Model::getAdapter($app);
Frmwrk\Translator::setDirectory('app/language');

$app->run();
echo $app->run('layout/default');