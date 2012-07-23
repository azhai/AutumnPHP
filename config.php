<?php
defined('APPLICATION_ROOT') or die();

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
    'scopes' => array(
    ),
    'cache' => array(
        'file' => array(
            'class' => 'AuCacheFile',
            'args' => array(RUNTIME_DIR . DS . 'caches', array('schema'), true),
        ),
        'fragment' => array(
            'class' => 'AuCacheFile',
            'args' => array(RUNTIME_DIR . DS . 'fragments', array('block', 'widget')),
        ),
    ),
);
