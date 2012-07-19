<?php
defined('APPLICATION_ROOT') or die();


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


class AuCacheSerialize extends AuCacheFile
{

    protected function _read($filename)
    {
        return unserialize( file_get_contents($filename) );
    }

    protected function _write($filename, $cell)
    {
        @touch($filename);
        @chmod($filename, $this->file_mode);
        $content = serialize($cell);
        file_put_contents($filename, $content, LOCK_EX);
    }
}


class AuCacheApc extends AuCacheBase
{
    private $_active_ = false;

    public function __construct(array $namespaces=null)
    {
        if ( extension_loaded('apc') && ini_get('apc.enabled') == '1' ) {
            $this->_active_ = true;
        }
        parent::__construct($namespaces);
    }

    public function check($ns)
    {
        return $this->_active_ && parent::check($ns);
    }

    protected function _ns_exists($exists_key)
    {
        $exists = apc_fetch($exists_key, $success);
        if ( $success === false ) {
            $exists = array();
        }
        return $exists;
    }

    protected function _get($ns, $key, $default=null)
    {
        if ($this->_active_ === false) {
            return $default;
        }
        $value = apc_fetch($this->key($ns, $key), $success);
        if ( $success === false ) {
            $value = $default;
        }
        return $value;
    }

    protected function _put($ns, $key, $value, $ttl=0)
    {
        if ( $this->check($ns) ) {
            $exists_key = $this->key('__exists', $ns);
            $exists = $this->_ns_exists($exists_key);
            $exists[$key] = 1;
            apc_store($exists_key, $exists);
            apc_store($this->key($ns, $key), $value, $ttl);
        }
    }

    protected function _delete($ns, $key)
    {
        if ($this->_active_ === false) {
            return;
        }
        apc_delete( $this->key($ns, $key) );
    }

    protected function _clean($ns=null)
    {
        if ($this->_active_ === false) {
            return;
        }
        if ( is_null($ns) ) {
            apc_clear_cache('user');
        }
        else {
            $exists_key = $this->key('__exists', $ns);
            $exists = $this->_ns_exists($exists_key);
            if ( ! empty($exists) ) {
                foreach ($exists as $key => $bool) {
                    apc_delete( $this->key($ns, $key) );
                }
                apc_delete($exists_key);
            }
        }
    }
}


class AuCacheMemcached extends AuCacheBase
{
    private $_conn_; // Holds the memcached object
    protected $_configs_ = array(
        'default' => array(
            'host'      => '127.0.0.1',
            'port'      => 11211,
            'weight'    => 1
        )
    );

    public function __construct(array $configs=null, array $namespaces=null)
    {
        if ( ! empty($configs) ) {
            $this->_configs_ = $configs;
        }
        parent::__construct($namespaces);
    }

    public function connect()
    {
        if ( is_null($this->_conn_) && extension_loaded('memcached') ) {
            $conn = new Memcached();
            foreach ($this->_configs_ as $c) {
                $conn->addServer($c['host'], $c['port'], $c['weight']);
            }
            $this->_conn_ = $conn;
        }
        return $this->_conn_;
    }

    protected function _ns_exists($exists_key)
    {
        $exists = $conn->get($exists_key);
        if ( is_null($exists) || $exists === false ) {
            $exists = array();
        }
        return $exists;
    }

    protected function _get($ns, $key, $default=null)
    {
        if ( is_null($conn) ) {
            return $default;
        }
        $value = $this->connect()->get( $this->key($ns, $key) );
        if ( is_null($value) || $value === false ) {
            $value = $default;
        }
        return $value;
    }

    protected function _put($ns, $key, $value, $ttl=0)
    {
        $conn = $this->connect();
        if ( ! is_null($conn) && $this->check($ns) ) {
            $exists_key = $this->key('__exists', $ns);
            $exists = $this->_ns_exists($exists_key);
            $exists[$key] = 1;
            $conn->set($exists_key, $exists);
            $nskey = $this->key($ns, $key);
            $conn->set($nskey, $value, $ttl);
        }
    }

    protected function _delete($ns, $key)
    {
        $conn = $this->connect();
        if ( ! is_null($conn) ) {
            $conn->delete( $this->key($ns, $key) );
        }
    }

    protected function _clean($ns=null)
    {
        $conn = $this->connect();
        if ( is_null($conn) ) {
            return;
        }
        if ( is_null($ns) ) {
            $conn->flush();
        }
        else {
            $exists_key = $this->key('__exists', $ns);
            $exists = $this->_ns_exists($exists_key);
            if ( ! empty($exists) ) {
                foreach ($exists as $key => $bool) {
                    $conn->delete( $this->key($ns, $key) );
                }
                $conn->delete($exists_key);
            }
        }
    }
}
