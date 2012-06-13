<?php
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('APPLICATION_ROOT') or define('APPLICATION_ROOT', dirname(dirname(__FILE__)));
defined('MODEL_DIR') or define('MODEL_DIR', APPLICATION_ROOT . DS . 'models');
defined('VIEW_DIR') or define('VIEW_DIR', APPLICATION_ROOT . DS . 'views');
defined('TEMPLATE_DIR') or define('TEMPLATE_DIR', APPLICATION_ROOT . DS . 'templates');
defined('PLUGIN_DIR') or define('PLUGIN_DIR', APPLICATION_ROOT . DS . 'plugins');


function call_my_func_array($creator, $params=array()) {
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


function camelize($underscored_word) {
    $humanize_word = ucwords(str_replace('_', ' ', $underscored_word));
    return str_replace(' ', '', $humanize_word);
}


function invoke_view($view_obj, $req) {
	if (! method_exists($view_obj, $req->action . 'Action')) {
		array_unshift($req->args, $req->action);
		$req->action = 'index';
	}
	$filter_objects = array();
	$filters = null;
	if (method_exists($view_obj, 'filters')) {
		$filters = $view_obj->filters($req->action);
	}
	$filters = empty($filters) ? array() : $filters;

	foreach($filters as $filter) {
		$filter_obj = call_my_func_array(
			array($filter . 'Filter', '__construct'), array(& $view_obj)
		);
		if (method_exists($filter_obj, 'before') && ! $filter_obj->before(& $req)) {
			return;
		}
		array_push($filter_objects, $filter_obj);
	}
	$result = call_user_func(array($view_obj, $req->action . 'Action'), & $req);
	while ($filter_obj = array_pop($filter_objects)) {
		if (method_exists($filter_obj, 'after')) {
			$filter_obj->after(& $result);
		}
	}
	return $result;
}


function load_plugin($file, $creator=null, $params=array(), $imports=array()) {
	foreach ($imports as $import_file) {
		require_once PLUGIN_DIR . DS . $import_file;
	}
	$plugin_file = PLUGIN_DIR . DS . $file;
	if ($file && file_exists($plugin_file) && is_file($plugin_file)) {
		require_once $plugin_file;
	}
	$creator = empty($creator) ? ucfirst(basename($file, '.php')) : $creator;
	return is_string($creator) ? $creator : call_my_func_array($creator, $params);
}


class Application
{
    public static $view_dir = '';
    public $configs = array();
    public $routers = array();
    public $site_title = '';
    public $max_router_layer = 2; //view文件与目录最多两层

    public function __construct($config_filename) {
        $this->configs = new ReadOnly(include $config_filename);
		$basic_configs = empty($this->configs->basic) ? array() : $this->configs->basic;
		foreach ($basic_configs as $key=>$value) {
			if ($key != 'configs') {
				$this->$key = $value;
			}
		}
    }

    public function run() {
        $req = an('req', 0, new Request($this));
        require_once VIEW_DIR . $req->file;

        if (function_exists($req->action . 'Action')) {
            $req->view = '';
            return call_user_func($req->action . 'Action', $req);
        }

        $view = camelize(ltrim(substr($req->file, 0, -4), '/'));
        if (class_exists($view . 'View')) {
            $req->view = $view;
            $view_obj = call_my_func_array(
                array($req->view . 'View', '__construct')
            );
			invoke_view($view_obj, $req);
        }
    }

    public static function autoload($klass)
    {
        if (isset(self::$builtins[$klass])) {
            require_once(APPLICATION_ROOT . DS . self::$builtins[$klass]);
        } else { //自动加载models下的类
            $filenames = glob(MODEL_DIR . DS . '*.php');
            if (empty($filenames)) {
                return false;
            }
            foreach ($filenames as $filename) {
                require_once($filename);
                if (class_exists($klass, false)) {
                    return true;
                }
            }
        }
        return true;
    }

    private static $builtins = array(
        'ReadOnly' => 'lib/model.php',
        'Model' => 'lib/model.php',
        'Request' => 'lib/request.php',
        'Curl' => 'lib/request.php',
        'Template' => 'lib/template.php',
        'User' => 'lib/request.php',
    );
}

spl_autoload_register(array('Application','autoload'));
#an('app', 0, new Application('config.php'))->run();
