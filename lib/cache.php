<?php
defined('APPLICATION_ROOT') or die();


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
