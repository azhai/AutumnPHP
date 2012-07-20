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
    'logging' => array(
        'import' => 'plugins' . DS . 'klogger.php',
        'class' => 'KLogger',
        'staticmethod' => 'instance',
        'default' => array(RUNTIME_DIR . DS . 'logs'),
    ),
    'debuger' => array(
        #在Chrome浏览器上安装ChromePhp插件，到Chrome开发人员工具/Console/All中查看
        'import' => 'plugins' . DS . 'chromephp.php',
        'class' => 'ChromePhp',
        'staticmethod' => 'getInstance',
        'default' => array(),
    ),
);
