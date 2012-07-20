<?php
defined('APPLICATION_ROOT') or die();


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
        $construct = new AuConstructor(ucfirst($filter) . 'Filter', array(& $view_obj));
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


class AuConfigure
{
    protected $_data_ = array();        #数据
    protected $_defaults_ = array();    #默认数据

    protected function __construct(array $data=null)
    {
        if (! empty($data)) {
            $this->_data_ = $data;
        }
    }

    public static function instance(array $data=null)
    {
        $obj = new AuConfigure($data);
        return $obj;
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

    public function __construct($subject, $method, array $args=array())
    {
        $this->subject = $subject;
        $this->method = $method;
        $this->append_args($args);
    }

    public function append_args(array $args)
    {
        if ( ! empty($args) ) {
            $this->args = array_merge($this->args, $args);
        }
    }

    public function prepend_args(array $args)
    {
        if ( ! empty($args) ) {
            $this->args = array_merge($args, $this->args);
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

    public function emit_prepend()
    {
        $this->prepend_args( func_get_args() );
        return $this->_emit();
    }

    public function emit_array(array $args)
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
    public function __construct($subject, array $args=array())
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
    private $_cache_ = null;
    private $_configs_ = null;
    public $max_router_layer = 2; //view文件与目录最多两层
    public $routers = array();
    private static $_plugins_ = array();

    public function __construct($config_name)
    {
        define('AUTUMN_ODU4MTE3NTYX', 1);
        $this->_cache_ = new AuCache();
        //加载配置文件
        $config_cache = new AuCacheFile(APPLICATION_ROOT, array($config_name), true, 0755);
        $configs = $config_cache->get($config_name, '', array());
        $this->_configs_ = AuConfigure::instance( IN_CAKEPHP ? get_cakephp_dbs($configs) : $configs );
        //加载配置文件，将basic段的配置加载为实例的属性
        $basic_configs = empty($this->_configs_->basic) ? array() : $this->_configs_->basic;
        foreach ($basic_configs as $key=>$value) {
            if ($key != '_configs_') { //防止覆盖了配置文件本身
                $this->$key = $value;
            }
        }
    }

    public function get_scopes()
    {
        return isset($this->_configs_->scopes) ? $this->_configs_->scopes : array();
    }

    public function log($var='', $level=null)
    {
        $logger = $this->logging(); #先将KLogger类载入
        if ( is_null($level) ) {
            $level = KLogger::INFO;
        }
        //$var = is_object($var) ? get_object_vars($var) : $var;
        $var = is_string($var) ? $var : strval($var);
        $logger->log($var, $level);
    }

    public function debug($var='', $level=null)
    {
        $debuger = $this->debuger(); #先将ChromePhp类载入
        if ( is_null($level) ) {
            $level = ChromePhp::LOG;
        }
        $var = is_scalar($var) ? strval($var) : var_export($var, true);
        call_user_func(array($debuger, 'log'), $var);
    }

    public function cache()
    {
        if (func_num_args() == 0) {
            return new AuCache();
        }
        import('lib/cache.php');
        $keys = func_get_args();
        foreach ($keys as $key) {
            if ( empty($key) ) {
                $obj = new AuCache();
            }
            else {
                $items = $this->_configs_->cache[$key];
                $obj = $this->load($items['class'], $key, $items);
            }

            if ( isset($chain) ) {
                $chain->backend = $obj;
            }
            else {
                $chain = $obj;
            }
        }
        return $chain;
    }

    /*加载插件*/
    public function load($class, $key='default', $items=array(), $args=array())
    {
        if ( isset($items['import']) ) {
            import( $items['import'] );
        }
        if ( isset($items[$key]) ) {
            $item_args = $items[$key];
        }
        else if ( isset($items['args']) ) {
            $item_args = $items['args'];
        }
        else {
            $item_args = array();
        }
        if ( isset($items['staticmethod']) ) {
            $constructor = new AuProcedure($class, $items['staticmethod'], $item_args);
        }
        else {
            $constructor = new AuConstructor($class, $item_args);
        }
        $obj = $constructor->emit_array($args);
        if ( method_exists($obj, 'set_config_name') ) {
            $obj->set_config_name($key);
        }
        return $obj;
    }

    public function __call($name, $args)
    {
        $key = empty($args) ? 'default' : array_shift($args);
        $obj = $this->_cache_->get('plugin', $name);
        if ( is_null($obj) ) {
            $items = $this->_configs_->$name;
            if ( ! empty($items) ) {
                $class = isset($items['class']) ? $items['class'] : ucfirst($name);
                $obj = $this->load($class, $key, $items, $args);
                $this->_cache_->put('plugin', $name, $obj);
            }
        }
        return $obj;
    }

    public function run() {
        $req = new AuRequest($this);
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
