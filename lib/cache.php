<?php
defined('APPLICATION_ROOT') or die();


/**
 * 缓存类
 **/
class AuCache
{
    private static $_instance_ = null;
    protected static $_storage_ = array(); //对象注册表
    public $backends = array();
    public $namespaces = array('schema'); //允许的名称空间

    protected function __construct()
    {
    }

    public static function instance()
    {
        if ( is_null(self::$_instance_) ) {
            self::$_instance_ = new AuCache();
        }
        return self::$_instance_;
    }

    public function __get($key)
    {
        if ( array_key_exists($key, $this->backends) ) {
            return $this->backends[$key];
        }
        $obj = $this;
        switch ($key) {
            case 'file':
                $obj = new AuCacheFile(RUNTIME_DIR . DS . 'caches');
                break;
            case 'apc':
                if ( extension_loaded('apc') && ini_get('apc.enabled') == '1' ) {
                    $obj = new AuCacheApc();
                }
                break;
            case 'memcached':
                break;
        }
        $this->backends[$key] = $obj;
        return $obj;
    }

    public function check($ns)
    {
        if ( in_array($ns, $this->namespaces) ) {
            return true;
        }
        else if ( strpos($ns, '.') !== false ) {
            $ns = array_shift( explode('.', $ns) );
            if ( in_array($ns, $this->namespaces) ) {
                return true;
            }
        }
    }

    public function key($ns, $key)
    {
        return $ns . '.' . $key;
    }

    public function put($ns, $key, $value, $ttl=3600)
    {
        if ( ! array_key_exists($ns, self::$_storage_) ) {
            self::$_storage_[$ns] = array();
        }
        self::$_storage_[$ns][$key] = array(
            'value' => $value, 'expire' => is_null($ttl) ? null : time() + $ttl
        );
    }

    public function get($ns, $key, $default=null)
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

    public function retrieve($ns, $key, $proc, $ttl=3600)
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
        if ( array_key_exists($ns, self::$_storage_) ) {
            if ( array_key_exists($key, self::$_storage_[$ns]) ) {
                unset( self::$_storage_[$ns][$key] );
            }
        }
    }

    public function clean($ns=null)
    {
        if ( is_null($ns) ) {
            self::$_storage_ = array();
        }
        else {
            self::$_storage_[$ns] = array();
        }
    }
}


class AuCacheFile extends AuCache
{
    public $cache_dir = '';

    protected function __construct($cache_dir)
    {
        $this->cache_dir = $cache_dir;
    }

    public function put($ns, $key, $value, $ttl=3600)
    {
        if ( $this->check($ns) ) {
            $filename = $this->cache_dir . DS . $this->key($ns, $key) . '.php';
            $cell = array(
                'value' => $value, 'expire' => is_null($ttl) ? null : time() + $ttl
            );
            $content = "<?php \nreturn " . var_export($cell, true) . ";\n";
            touch($filename);
            chmod($filename, 0777);
            file_put_contents($filename, $content, LOCK_EX);
        }
        parent::put($ns, $key, $value, $ttl);
    }

    public function get($ns, $key, $default=null)
    {
        $value = parent::get($ns, $key);
        if ( is_null($value) ) {
            $value = $default;
            $filename = $this->cache_dir . DS . $this->key($ns, $key) . '.php';
            if ( file_exists($filename) ) {
                $cell = (include $filename);
                if ( ! isset($cell['expire']) || $cell['expire'] > time() ) {
                    $value = $cell['value'];
                }
            }
        }
        return $value;
    }

    public function delete($ns, $key)
    {
        $filename = $this->cache_dir . DS . $this->key($ns, $key) . '.php';
        if ( file_exists($filename) ) {
            unlink($filename);
        }
        parent::delete($ns, $key);
    }

    public function clean($ns=null)
    {
        $ns = is_null($ns) ? '*' : $ns;
        $files = glob($this->cache_dir . DS . $this->key($ns, '*') . '.php');
        foreach ($files as $filename) {
            unlink($filename);
        }
        parent::clean($ns);
    }
}


class AuCacheApc extends AuCache
{
    public function put($ns, $key, $value, $ttl=3600)
    {
        if ( $this->check($ns) ) {
            apc_store($this->key($ns, $key), $value, $ttl);
        }
        parent::put($ns, $key, $value, $ttl);
    }

    public function get($ns, $key, $default=null)
    {
        $value = parent::get($ns, $key);
        if ( is_null($value) ) {
            $value = apc_fetch($this->key($ns, $key), $success);
            if ( $success === false ) {
                $value = $default;
            }
        }
        return $value;
    }

    public function delete($ns, $key)
    {
        apc_delete( $this->key($ns, $key) );
        parent::delete($ns, $key);
    }

    public function clean($ns=null)
    {
        if ( is_null($ns) ) {
            apc_clear_cache('user');
        }
        else if ( array_key_exists($ns, self::$_storage_) ) {
            foreach (self::$_storage_[$ns] as $key => $value) {
                apc_delete( $this->key($ns, $key) );
            }
        }
        parent::clean($ns);
    }
}


class AuCacheMemcached extends AuCache
{
    private $_conn; // Holds the memcached object
    protected $_conf = array(
        'default' => array(
            'default_host'      => '127.0.0.1',
            'default_port'      => 11211,
            'default_weight'    => 1
        )
    );

    public function put($ns, $key, $value, $ttl=3600)
    {
        if ( $this->check($ns) ) {
            if (get_class($this->_conn) == 'Memcached') {
                return $this->_conn->set($this->key($ns, $key), $value, $ttl);
            }
            else if (get_class($this->_conn) == 'Memcache') {
                return $this->_conn->set($this->key($ns, $key), $value, 0, $ttl);
            }
        }
        parent::put($ns, $key, $value, $ttl);
    }

    public function get($ns, $key, $default=null)
    {
        $value = parent::get($ns, $key);
        if ( is_null($value) ) {
            $value = $this->_conn->get( $this->key($ns, $key) );
            if ( is_null($value) || $value === false ) {
                $value = $default;
            }
        }
        return $value;
    }

    public function delete($ns, $key)
    {
        $this->_conn->delete( $this->key($ns, $key) );
        parent::delete($ns, $key);
    }

    public function clean($ns=null)
    {
        if ( is_null($ns) ) {
            $this->_conn->flush();
        }
        else if ( array_key_exists($ns, self::$_storage_) ) {
            foreach (self::$_storage_[$ns] as $key => $value) {
                $this->_conn->delete( $this->key($ns, $key) );
            }
        }
        parent::clean($ns);
    }
}
