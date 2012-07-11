<?php
defined('APPLICATION_ROOT') or die();


class AuConfigure
{
    protected $_data_ = array();        #数据
    protected $_defaults_ = array();        #数据

    public function __construct(array $data=null)
    {
        if (! empty($data)) {
            $this->_data_ = $data;
        }
    }

    public function __isset($prop)
    {
        return array_key_exists($prop, $this->_data_);
    }

    public function __get($prop)
    {
        if (array_key_exists($prop, $this->_data_)) {
            $items = $this->_data_[$prop];
        }
        else {
            $items = array_key_exists($prop, $this->_defaults_) ?
                                $this->_defaults_[$prop] : array();
        }
        return $items;
    }
}


/**
 * 生成一个值或对象的过程描述
 * 当调用emit()时执行生成工作，实现了Command模式
 **/
class AuProcedure
{
    public $subject = null;
    public $method = '';
    public $args = array();

    public function __construct($subject, $method, $args=array())
    {
        $this->subject = $subject;
        $this->method = $method;
        $this->append_args($args);
    }

    public function append_args($args)
    {
        if ( ! empty($args) ) {
            $this->args = array_merge($this->args, $args);
        }
    }

    protected function _emit()
    {
        $func = empty($this->subject) ? $this->method : array($this->subject, $this->method);
        return call_user_func_array($func, $this->args);
    }

    public function emit()
    {
        $this->append_args( func_get_args() );
        return $this->_emit();
    }

    public function emit_array($args)
    {
        $this->append_args($args);
        return $this->_emit();
    }
}


/**
 * 构造对象的过程描述
 **/
class AuConstructor extends AuProcedure
{
    public function __construct($subject, $args=array())
    {
        $this->subject = $subject;
        $this->method = '__construct';
        $this->append_args($args);
    }

    protected function _emit()
    {
        $subject = $this->subject;
        if ( empty($this->args) ) {
            return new $this->subject;
        }
        else {
            $ref = new ReflectionClass($this->subject);
            return $ref->newInstanceArgs($this->args);
        }
    }
}


class AuApplication
{
    public $max_router_layer = 2; //view文件与目录最多两层
    public $routers = array();
    private $configs = null;
    private $logger = null;
    private static $plugins = array();

    public function __construct($config_filename=null, $logger_dirname=null)
    {
        $this->get_configs($config_filename);
    }

    public function get_configs($config_filename)
    {
        define('AUTUMN_ODU4MTE3NTYX', 1);
        //加载配置文件
        if ( is_null($config_filename) ) {
            $config_filename = APPLICATION_ROOT . DS . 'config.php';
        }
        if ( file_exists($config_filename) && is_file($config_filename) ) {
            $this->configs = new AuConfigure(include $config_filename);
        }
        //加载配置文件，将basic段的配置加载为实例的属性
        $basic_configs = empty($this->configs->basic) ? array() : $this->configs->basic;
        foreach ($basic_configs as $key=>$value) {
            if ($key != 'configs') { //防止覆盖了配置文件本身
                $this->$key = $value;
            }
        }
    }

    public function log($var='', $level=null)
    {
        $logger = $this->logging(); #先将KLogger类载入
        if ( is_null($level) ) {
            $level = KLogger::INFO;
        }
        $var = is_scalar($var) ? strval($var) : var_export($var, true);
        $logger->log($var, $level);
    }

    /*加载插件*/
    public function __call($name, $args)
    {
        $key = empty($args) ? 'default' : array_shift($args);
        if ( ! array_key_exists($name, self::$plugins) ) {
            self::$plugins[$name] = array();
        }
        else if ( array_key_exists($key, self::$plugins[$name]) ) {
            return self::$plugins[$name][$key];
        }

        $items = $this->configs->$name;
        if ( ! empty($items) ) {
            if ( isset($items['import']) ) {
                import( $items['import'] );
            }
            $class = isset($items['class']) ? $items['class'] : ucfirst($name);
            if ( isset($items['staticmethod']) ) {
                $constructor = new AuProcedure($class, $items['staticmethod'], $items[$key]);
            }
            else {
                $constructor = new AuConstructor($class, $items[$key]);
            }
            $obj = call_user_func_array(array($constructor, 'emit'), $args);
            if ( method_exists($obj, 'set_config_name') ) {
                $obj->set_config_name($key);
            }
            self::$plugins[$name][$key] = $obj;
            return $obj;
        }
    }

    public function run() {
        $req = cached('req', 0, new AuRequest($this));
        require_once APPLICATION_ROOT . DS . 'views' . $req->file;
        //调用对象方法
        $view = camelize(ltrim(substr($req->file, 0, -4), '/'));
        if (class_exists($view . 'View')) {
            $req->view = $view;
            $construct = new AuConstructor($req->view . 'View');
            $view_obj =  $construct->emit();
            invoke_view($view_obj, $req);
        }
    }
}
