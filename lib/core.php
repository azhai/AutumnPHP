<?php
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'base.php');
defined('MODEL_DIR_NAME') or define('MODEL_DIR_NAME', 'models');


class AuApplication extends AuBaseApplication
{
    protected static $_plugins_ = array();
    protected $_cache_ = null;
    public $routers = array();

    public function __construct(array $configs=null)
    {
        $this->_cache_ = new AuCache();
        if ( empty($configs) ) { //加载配置文件
            $config_cache = new AuCacheFile(APPLICATION_ROOT, array(CONFIG_NAME), true, 0755);
            $configs = $config_cache->get(CONFIG_NAME, '', array());
        }
        parent::__construct($configs);
    }

    public static function autoload($klass)
    {
        if ( ! parent::autoload($klass) ) { //自动加载models下的类
            $filenames = glob(APPLICATION_ROOT . DS . MODEL_DIR_NAME . DS . '*.php');
            foreach ($filenames as $filename) {
                require_once($filename);
                if (class_exists($klass, false)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function get_scopes()
    {
        return isset($this->_configs_->scopes) ? $this->_configs_->scopes : array();
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

    /*缓存使用过的URL*/
    public function has_seen($req)
    {
        return false;
        //先检查$this->app->routers中缓存的正则URL对应的结果
        foreach ($this->routers as $pattern => $router) {
            if ( preg_match($pattern, $req->url, $matches) ) {
                foreach ($router as $prop => $value) {
                    $req->$prop = $value;
                }
                array_shift($matches);
                $req->args = array_merge($matches, $req->args);
                return true;
             }
        }
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


/**
 * 缓存类
 **/
abstract class AuCacheBase
{
    const KEY_SEPERATOR = '.';
    public $backend = null;
    public $namespaces = '*'; //允许的名称空间

    public function __construct(array $namespaces=null)
    {
        if ( ! empty($namespaces) ) {
            if ( is_array($this->namespaces) ) { //扩充
                $this->namespaces = array_merge($this->namespaces, $namespaces);
            }
            else { //限制
                $this->namespaces = $namespaces;
            }
        }
    }

    abstract protected function _get($ns, $key, $default);
    abstract protected function _put($ns, $key, $value, $ttl);
    abstract protected function _delete($ns, $key);
    abstract protected function _clean($ns=null);

    public function key($ns, $key='')
    {
        $ns = empty($ns) ? 'global' : $ns;
        return $key === '' ? $ns : $ns . self::KEY_SEPERATOR . $key;
    }

    public function check($ns)
    {
        if ('*' === $this->namespaces) {
            return true;
        }
        else if ( in_array($ns, $this->namespaces, true) ) {
            return true;
        }
        else {
            if ( strpos($ns, self::KEY_SEPERATOR) !== false ) {
                $pre = array_shift( explode(self::KEY_SEPERATOR, $ns) );
                if ( in_array($pre, $this->namespaces, true) ) {
                    return true;
                }
            }
        }
        return false;
    }

    public function get($ns, $key, $default=null)
    {
        $value = $this->_get($ns, $key);
        if ( is_null($value) && ! is_null($this->backend) ) {
            $value = $this->backend->get($ns, $key);
        }
        $value = is_null($value) ? $default : $value;
        return $value;
    }

    public function put($ns, $key, $value, $ttl=0)
    {
        if ( ! is_null($this->backend) ) {
            $this->backend->put($ns, $key, $value, $ttl);
        }
        $this->_put($ns, $key, $value, $ttl);
    }

    public function retrieve($ns, $key, $proc, $ttl=0)
    {
        $value = $this->get($ns, $key);
        if ( is_null($value) ) { //不存在，尝试创建
            $value = $proc instanceof AuProcedure ? $proc->emit() : $proc;
            if ( ! is_null($value) ) { //成功创建
                $this->put($ns, $key, $value, $ttl);
            }
        }
        return $value;
    }

    public function delete($ns, $key)
    {
        if ( ! is_null($this->backend) ) {
            $this->backend->delete($ns, $key);
        }
        $this->_delete($ns, $key);
    }

    public function clean($ns=null)
    {
        if ( ! is_null($this->backend) ) {
            $this->backend->clean($ns);
        }
        $this->_clean($ns);
    }
}


class AuCache extends AuCacheBase
{
    protected static $_storage_ = array(); //对象注册表

    protected function _get($ns, $key, $default=null)
    {
        if ( array_key_exists($ns, self::$_storage_) ) {
            if ( array_key_exists($key, self::$_storage_[$ns]) ) {
                $cell = self::$_storage_[$ns][$key];
                if ( ! isset($cell['expire']) || $cell['expire'] > time() ) {
                    return $cell['value'];
                }
            }
            self::$_storage_[$ns] = array();
        }
        return $default;
    }

    protected function _put($ns, $key, $value, $ttl=0)
    {
        if ( ! array_key_exists($ns, self::$_storage_) ) {
            self::$_storage_[$ns] = array();
        }
        self::$_storage_[$ns][$key] = array(
            'value' => $value, 'expire' => is_null($ttl) ? null : time() + $ttl
        );
    }

    protected function _delete($ns, $key)
    {
        if ( array_key_exists($ns, self::$_storage_) ) {
            if ( array_key_exists($key, self::$_storage_[$ns]) ) {
                unset( self::$_storage_[$ns][$key] );
            }
        }
    }

    protected function _clean($ns=null)
    {
        if ( is_null($ns) ) {
            self::$_storage_ = array();
        }
        else {
            self::$_storage_[$ns] = array();
        }
    }
}


class AuCacheFile extends AuCacheBase
{
    public $cache_dir = '';
    public $forever = false; //$forever === true时不将expire时间写入文件
    public $file_mode = 0777;

    public function __construct($cache_dir, array $namespaces=null,
                                $forever=false, $file_mode=0777)
    {
        $this->cache_dir = $cache_dir;
        $this->forever = $forever;
        $this->file_mode = $file_mode;
        parent::__construct($namespaces);
    }

    protected function _read($filename)
    {
        return (include $filename);
    }

    protected function _write($filename, $cell)
    {
        @touch($filename);
        @chmod($filename, $this->file_mode);
        $content = "<?php \nreturn " . var_export($cell, true) . ";\n";
        file_put_contents($filename, $content, LOCK_EX);
    }

    protected function _get($ns, $key, $default=null)
    {
        $value = $default;
        $filename = $this->cache_dir . DS . $this->key($ns, $key) . '.php';
        if ( file_exists($filename) ) {
            $cell = $this->_read($filename);
            if ($this->forever === true) {
                $value = $cell;
            }
            else if ( empty($cell['expire']) || $cell['expire'] > time() ) {
                $value = $cell['value'];
            }
        }
        return $value;
    }

    protected function _put($ns, $key, $value, $ttl=0)
    {
        if ( $this->check($ns) ) {
            $filename = $this->cache_dir . DS . $this->key($ns, $key) . '.php';
            if ($this->forever === true) {
                $cell = $value;
            }
            else {
                $cell = array(
                    'expire' => $ttl === 0 ? 0 : time() + $ttl, 'value' => $value
                );
            }
            $this->_write($filename, $cell);
        }
    }

    protected function _delete($ns, $key)
    {
        $filename = $this->cache_dir . DS . $this->key($ns, $key) . '.php';
        if ( file_exists($filename) ) {
            unlink($filename);
        }
    }

    protected function _clean($ns=null)
    {
        $ns = is_null($ns) ? '*' : $ns;
        $files = glob($this->cache_dir . DS . $this->key($ns, '*') . '.php');
        foreach ($files as $filename) {
            unlink($filename);
        }
    }
}


class AuLiteral
{
    public $text = '';

    public function __construct($text)
    {
        $this->text = $text;
    }

    public function __toString()
    {
        return $this->text;
    }
}


/**
 * 使用PDO的数据库连接
 */
class AuDatabase
{
    public $dbname = 'default';
    private $conn = null;
    private $dsn = '';
    private $user = '';
    private $password = '';
    public $prefix = '';
    public static $sql_history = array();

    public function __construct($dsn, $user='', $password='', $prefix='')
    {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
        $this->prefix = $prefix;
    }

    public function set_config_name($key) {
        $this->dbname = $key;
    }

    /*连接数据库*/
    public function connect()
    {
        if ( is_null($this->conn) ) {
            $opts = array(
                PDO::ATTR_PERSISTENT => false,
                //PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //错误模式，默认PDO::ERRMODE_SILENT
                PDO::ATTR_ORACLE_NULLS => PDO::NULL_TO_STRING, //将空值转为空字符串
            );
            try {
                $conn = new PDO($this->dsn, $this->user, $this->password, $opts);
            }
            catch (PDOException $e) {
                trigger_error("DB connect failed:" . $e->getMessage(), E_USER_ERROR);
            }

            if ( strtolower(substr($this->dsn, 0, 6)) == 'mysql:' ) {
                try {
                    $conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true); //使用MySQL查询缓冲
                    $conn->exec("SET NAMES 'UTF8'; SET TIME_ZONE = '+8:00'");
                }
                catch (PDOException $e) {
                }
            }
            $this->conn = $conn;
        }
        return $this->conn;
    }

    public function quote($args)
    {
        if ( is_array($args) ) {
            return array_map(array($this, 'quote'), $args);
        }
        else if ( $args instanceof AuLiteral ) {
            return $args->text;
        }
        else {
            $conn = $this->connect();
            return $conn->quote($args, PDO::PARAM_STR);
        }
    }

    /*执行修改操作*/
    public function execute($sql, $args=array(), $insert_id=false)
    {
        $result = true;
        $conn = $this->connect();
        $sql = $this->dump($sql, $args);
        self::$sql_history []= array($sql, array());
        try {
            $conn->beginTransaction();
            $conn->exec($sql);
            if ($insert_id) { //Should use lastInsertId BEFORE you commit
                $result = $conn->lastInsertId();
            }
            $conn->commit();
        } catch(PDOException $e) {
            $conn->rollBack();
            trigger_error("DB execute failed:" . $e->getMessage(), E_USER_ERROR);
        }
        return $result;
    }

    /*执行查询操作*/
    public function query($sql, $args=array(), $fetch='all')
    {
        $conn = $this->connect();
        self::$sql_history []= array($sql, $args);
        $stmt = $conn->prepare($sql);
        $stmt->execute($args);
        try {
            if ( empty($fetch) || $fetch == 'all' ) {
                $result = $stmt->fetchAll( PDO::FETCH_ASSOC );
            }
            else if ( $fetch == 'one' ) {
                $result = $stmt->fetch( PDO::FETCH_ASSOC );
            }
            else if ( $fetch == 'column' ) {
                $result = $stmt->fetchColumn();
            }
            else {
                $result = $fetch->emit_prepend($stmt);
            }
        } catch(PDOException $e) {
            trigger_error("DB query failed:" . $e->getMessage(), E_USER_ERROR);
        }
        $stmt->closeCursor();
        return $result;
    }

    /*生成可打印的SQL语句*/
    public function dump($sql, $args=array())
    {
        if (strpos($sql, "%")) {
            $sql = str_replace("%%", "%", $sql);
            $sql = str_replace("%", "%%", $sql);
        }
        $sql = str_replace("?", "%s", $sql);
        return vsprintf($sql, $this->quote($args));
    }

    public function dump_all($return=false)
    {
        @ob_start();
        foreach (self::$sql_history as $i => $line) {
            list($sql, $args) = $line;
            echo ($i + 1) . ": ";
            echo empty($args) ? $sql : $this->dump($sql, $args);
            echo "; <br />\n";
        }
        return $return ? ob_get_clean() : ob_end_flush();
    }

    public function factory($tblname, $schema=null, $rowclass='Object', $setclass='Array')
    {
        if ( is_null($schema) ) {
            $schema = AuSchema::instance($tblname, $this->dbname);
        }
        $obj = new AuQuery($this, $schema->table, $schema->get_pkey());
        $obj->factory = AuFactory::instance($schema, $rowclass, $setclass);
        return $obj;
    }
}
