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
    'scopes' => array(
    ),
    'cache_file' => array(
        'class' => 'AuCacheFile',
        'default' => array(RUNTIME_DIR . DS . 'caches', array('schema'), true),
        'fragment' => array(RUNTIME_DIR . DS . 'fragments', array('block', 'widget')),
    ),
    'logging' => array(
        'import' => 'plugins' . DS . 'klogger.php',
        'class' => 'KLogger',
        'staticmethod' => 'instance',
        'default' => array(RUNTIME_DIR . DS . 'logs'),
    ),
);

