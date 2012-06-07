<?php
defined('APPLICATION_ROOT') or define('APPLICATION_ROOT', dirname(dirname(__FILE__)));
define('DS', DIRECTORY_SEPARATOR);

class Application
{
    private static $app = null;
    public $config = array();

    private function __construct() {
    }

    public static function init($config_filename='') {
        if (is_null(self::$app)) {
            $app = new Application();
            $app->config = (include $config_filename);
            self::$app = $app;
        }
        return self::$app;
    }

    public function load($plugin_name) {
        $args = func_get_args();
        $plugin_name = array_shift($args);
        $plugin_class = new ReflectionClass($plugin_name);
        $plugin = $plugin_class->newInstanceArgs($args);
    }
}

global $app;
$app = Application::init(APPLICATION_ROOT . DS . 'config.php');

