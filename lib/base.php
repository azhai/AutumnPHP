<?php
defined('APPLICATION_ROOT') or define('APPLICATION_ROOT', dirname(dirname(__FILE__)));
define('DS', DIRECTORY_SEPARATOR);


function call_my_func_array($creator, $params) {
    if (is_array($creator) && $creator[1] == '__construct') {
        $class = new ReflectionClass($creator[0]);
        return $class->newInstanceArgs($params);
    }
    else {
        return call_user_func_array($creator, $params);
    }
}


function an($class, $id=0) {
    static $objects = array(); //对象注册表
    $arglen = func_num_args();
    //$singlon = $id == 0; //是否单例

    if (! array_key_exists($class, $objects)) {
        $objects[$class] = array();
    }
    if ($arglen == 3) { //存放对象
        $instance = func_get_arg(2);
        $objects[$class][$id] = $instance;
        return $instance;
    }

    if (! array_key_exists($id, $objects[$class])) {
        if ($arglen == 2) { //获取对象
            return;
        }
        else if ($arglen == 4) { //获取或创建对象
            $instance = call_my_func_array(func_get_arg(2), func_get_arg(3));
            $objects[$class][$id] = $instance;
            return $instance;
        }
    }
    else { //获取对象
        return $objects[$class][$id];
    }
}


class Application
{
    private static $plugins = array();
    public $config = array();

    public function __construct($config_filename) {
        $this->config = (include $config_filename);
    }

    public function load($name) {
    }

    public function run() {
        $req = new Request($this);
        echo $req->url . "<br />";
        an('req', 0, $req);
    }

    public static function autoload($klass)
    {
		if (isset(self::$plugins[$klass])) {
            //The object is exists!
		} else if (isset(self::$builtins[$klass])) {
			include(APPLICATION_ROOT . DS . self::$builtins[$klass]);
		} else { //自动加载models下的类
            $filenames = glob(APPLICATION_ROOT . DS . 'models' . DS . '*.php');
            if (empty($filenames)) {
                return false;
            }
            foreach ($filenames as $filename) {
                require_once $filename;
                if (class_exists($klass, false)) {
                    return true;
                }
            }
		}
		return true;
    }

    private static $builtins = array(
		'Model' => 'lib/model.php',
		'Request' => 'lib/request.php',
		'Template' => 'lib/template.php',
		'User' => 'lib/request.php',
	);
}

spl_autoload_register(array('Application','autoload'));

