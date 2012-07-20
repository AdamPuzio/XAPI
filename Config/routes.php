<?php
include_once('xapi.php');
Router::parseExtensions();
Router::connect('/api/*', array('plugin' => 'api', 'controller' => 'api', 'action' => 'index'));
//Router::connect('/', array('controller' => 'api', 'action' => 'index'));
/*Router::connect('/api/v1/*', array('plugin' => 'api', 'controller' => 'v1', 'action' => 'index'));
Router::connect('/api/client/docs', array('plugin' => 'api', 'controller' => 'client', 'action' => 'docs'));
Router::connect('/api/client/js', array('plugin' => 'api', 'controller' => 'client', 'action' => 'js'));
Router::connect('/api/client/*', array('plugin' => 'api', 'controller' => 'client', 'action' => 'index'));*/