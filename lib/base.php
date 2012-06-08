<?php
defined('APPLICATION_ROOT') or define('APPLICATION_ROOT', dirname(dirname(__FILE__)));
define('DS', DIRECTORY_SEPARATOR);


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


class Application
{
    private static $plugins = array();
    public static $view_dir = '';
    public $configs = array();
    public $routers = array();

    public function __construct($config_filename) {
        if (empty(self::$view_dir)) {
            self::$view_dir = APPLICATION_ROOT . DS . 'views';
        }
        $this->configs = (include $config_filename);
        if (! isset($this->configs['MAX_VIEW_LAYER'])) {
            $this->configs['MAX_VIEW_LAYER'] = 2; //view文件与目录最多两层
        }
    }

    public function load($name) {
    }

    public function parse($req) {
        /* #TODO: 先检查$this->routers中缓存的正则URL对应的结果
         if ($req->url MATCH A KEY IN $this->routers) {
            foreach ($this->routers[KEY] as $prop => $value) {
                $req->$prop = $value;
            }
            return true;
         }*/

        $limit = $this->configs['MAX_VIEW_LAYER'] + 1;
        $pics = preg_split('/\//', $req->url, $limit + 1,
                           PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);
        if (count($pics) > $limit) {
            array_pop($pics);
        }

        $limit = count($pics) - 1;
        while ($limit >= 0) {
            $pos = $pics[$limit][1] + strlen($pics[$limit][0]);
            $dir = substr($req->url, 0, $pos);
            if (file_exists(self::$view_dir . $dir . DS)) { //目录存在
                if (file_exists(self::$view_dir . $dir . $req->file)) {
                    $req->file = $dir . $req->file;
                    $req->args = explode('/', substr($req->url, $pos + 1));
                    if (! empty($req->args) && $req->args != array('')) {
                        $req->action = array_shift($req->args);
                    }
                    return true;
                }
            }
            else if (file_exists(self::$view_dir . $dir . '.php')) { //文件存在
                $req->file = $dir . '.php';
                $req->args = explode('/', substr($req->url, $pos + 1));
                if (! empty($req->args) && $req->args != array('')) {
                    $req->action = array_shift($req->args);
                }
                return true;
            }
            $limit --;
        }
        if (file_exists(self::$view_dir . $req->file)) {
            return true; //默认文件/index.php存在
        }
    }

    public function run() {
        $req = an('req', 0, new Request($this));
        $this->parse($req);
        require_once self::$view_dir . $req->file;

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
            if (method_exists($view_obj, $req->action . 'Action')) {
                return call_user_func(array($view_obj, $req->action . 'Action'), $req);
            }

        }
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
#an('app', 0, new Application('config.php'))->run();
