<?php
defined('APPLICATION_ROOT') or define('APPLICATION_ROOT', dirname(dirname(__file__)));

class Application
{
    private static $app = null;
    public $config = array();

    private function __construct($config_filename) {
        $this->config = (include $config_filename);
    }

    public static function init($config_filename = '') {
        if (is_null(self::$app)) {
            self::$app = new Application($config_filename);
        }
        return self::$app;
    }

    public function load() {
        $args = func_get_args();
        $plugin_name = array_shift($args);
    }
}

global $app = Application::init(APPLICATION_ROOT . '/config.php');
