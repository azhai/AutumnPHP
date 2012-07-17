<?php
defined('AUTUMN_ODU4MTE3NTYX') or die();

return array(
    'basic' => array(
        'debug' => true,
        #'theme' => 'ymmind',
        'site_title' => 'Autumn PHP blog example',
    ),
    'db' => array(
        'class' => 'AuDatabase',
        'default' => array('mysql:host=localhost;dbname=db_blog', 'ryan', 'ryan', 't_'),
    ),
    'cache' => array(
        'class' => 'AuCache',
        'staticmethod' => 'instance',
    ),
    'logging' => array(
        'import' => 'plugins' . DS . 'KLogger',
        'class' => 'KLogger',
        'staticmethod' => 'instance',
        'default' => array(RUNTIME_DIR . DS . 'logs'),
    )
);

