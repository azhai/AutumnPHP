<?php
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('APPLICATION_ROOT') or define('APPLICATION_ROOT', dirname(dirname(__FILE__)));
defined('MODEL_DIR') or define('MODEL_DIR', APPLICATION_ROOT . DS . 'models');
defined('VIEW_DIR') or define('VIEW_DIR', APPLICATION_ROOT . DS . 'views');
defined('TEMPLATE_DIR') or define('TEMPLATE_DIR', APPLICATION_ROOT . DS . 'templates');
require_once(APPLICATION_ROOT . DS . 'lib' . DS . 'common.php');

error_reporting(E_ALL & ~E_NOTICE);


function invoke_view($view_obj, $req) {
	//当$view不存在$action动作时，执行默认的index动作，并将$action作为动作的第一个参数
	if (! method_exists($view_obj, $req->action . 'Action')) {
		array_unshift($req->args, $req->action);
		$req->action = 'index';
	}
	//找出当前action对应哪些Filters
	$filter_objects = array();
	$filters = null;
	if (method_exists($view_obj, 'filters')) {
		$filters = $view_obj->filters($req->action);
	}
	$filters = empty($filters) ? array() : $filters;
	//按顺序执行Filters的before检查，未通过跳转到404错误页面
	foreach($filters as $filter) {
		$construct = new Constructor($filter . 'Filter', array(& $view_obj));
		$filter_obj = $construct->emit();
		if (method_exists($filter_obj, 'before') && ! $filter_obj->before(& $req)) {
			return $req->error(404);
		}
		array_push($filter_objects, $filter_obj);
	}
	//执行action动作，再按逆序执行Filters的after包装，修改返回的结果$result
	$result = call_user_func(array($view_obj, $req->action . 'Action'), & $req);
	while ($filter_obj = array_pop($filter_objects)) {
		if (method_exists($filter_obj, 'after')) {
			$filter_obj->after(& $result);
		}
	}
	return $result;
}


class Application
{
    public static $view_dir = '';
    public $configs = array();
    public $routers = array();
    public $site_title = '';
    public $max_router_layer = 2; //view文件与目录最多两层

    public function __construct($config_filename) {
		//加载配置文件，将basic段的配置加载为实例的属性
        $this->configs = new ReadOnly(include $config_filename);
		$basic_configs = empty($this->configs->basic) ? array() : $this->configs->basic;
		foreach ($basic_configs as $key=>$value) {
			if ($key != 'configs') { //防止覆盖了配置文件本身
				$this->$key = $value;
			}
		}
    }

    public function db($schema='default') {
        if ( array_key_exists($schema, $this->configs->databases) ) {
            $config = $this->configs->databases[$schema];
			$construct = new Constructor('Database', $config);
            return cached('db', $schema, null, $construct);
        }
    }

    public function factory($model, $schema='') {
		$factory = new DbFactory($model);
		if ( empty($schema) ) {
			$model = $factory->model;
			$schema = $model::$schema;
		}
        if ( array_key_exists($schema, $this->configs->databases) ) {
            $factory->db = $this->db($schema);
        }
		return $factory;
    }

    public function plugin($file, $name='', $args=array(), $imports=array()) {
		array_unshift($imports, $file);
		if ( empty($name) ) {
			$name = ucfirst( basename($name, '.php') );
		}
		$construct = new Constructor($name, $args, $imports);
		return cached('plugin', $name, null, $construct);
	}

    public function run() {
        $req = cached('req', 0, new Request($this));
        require_once VIEW_DIR . $req->file;

        if (function_exists($req->action . 'Action')) {
            $req->view = '';
            return call_user_func($req->action . 'Action', $req);
        }

        $view = camelize(ltrim(substr($req->file, 0, -4), '/'));
        if (class_exists($view . 'View')) {
            $req->view = $view;
			$construct = new Constructor($req->view . 'View');
            $view_obj =  $construct->emit();
			invoke_view($view_obj, $req);
        }
		if ($this->debug) { //输出执行过的SQL语句
			$this->db()->verbose();
		}
    }

    public static function autoload($klass)
    {
        if (isset(self::$builtins[$klass])) {
            require_once(APPLICATION_ROOT . DS . self::$builtins[$klass]);
        } else { //自动加载models下的类
            $filenames = glob(MODEL_DIR . DS . '*.php');
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
        'Curl' => 'lib/request.php',
        'Database' => 'lib/database.php',
        'DbFactory' => 'lib/database.php',
        'Model' => 'lib/model.php',
        'Request' => 'lib/request.php',
        'Template' => 'lib/template.php',
    );
}

spl_autoload_register(array('Application','autoload'));
