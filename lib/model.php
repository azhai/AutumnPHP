<?php
defined('APPLICATION_ROOT') or die();


/**
 * 数据表的描述
 **/
class AuSchema extends AuConfigure
{
    protected static $_pool_ = null;
    protected $_defaults_ = array(
        'table'=>'', 'pkey_array'=>array('id'),
        'types'=>array(), 'defaults'=>array()
    );
    public $dbname = 'default';
    public $tblname = '';

    protected function __construct($tblname, $dbname='default', $desc=array())
    {
        $this->tblname = $tblname;
        $this->dbname = $dbname;
        parent::__construct($desc);
    }

    public static function instance($tblname, $dbname='default')
    {
        if ( is_null(self::$_pool_) ) {
            self::$_pool_ = app()->cache();
        }
        $ns = 'schema.db.' . $dbname;
        $obj = self::$_pool_->get($ns, $tblname);
        if ( is_null($obj) ) {
            $db = app()->db($dbname);
            $structs = self::describe_structs($dbname, $db);
            $models = self::describe_models($dbname, array_keys($structs));
            if ( array_key_exists($tblname, $structs) ) {
                $desc = $structs[$tblname];
                if ( isset( $models[$dbname][$tblname] ) ) {
                    $desc['model'] = $models[$dbname][$tblname];
                }
                else {
                    $desc['model'] = '';
                }
            }
            else {
                $desc = array('table' => $db->prefix . $tblname);
            }
            $obj = new AuSchema($tblname, $dbname, $desc);
            self::$_pool_->put($ns, $tblname, $obj);
        }
        return $obj;
    }

    public function get_model($default='AuLazyRow')
    {
        return empty($this->model) ? $default : $this->model;
    }

    public function get_pkey()
    {
        $pkey_arr = $this->pkey_array;
        $pkey = count($pkey_arr) <= 1 ? implode('', $pkey_arr) : $pkey_arr;
        return $pkey;
    }

    public static function describe_structs($dbname, $db)
    {
        $cache = app()->cache('', 'file');
        $structs = $cache->get('schema.struct', $dbname, array());
        if ( empty($structs) ) {
            $prefix = str_replace('_', '\_', $db->prefix);
            $strip_lenth = empty($prefix) ? 0 : strlen($prefix) - 1;
            $sql = "SHOW TABLES LIKE '" . $prefix . "%'";
            $tables = array_map('array_pop', $db->query($sql));

            foreach ($tables as $table) {
                $tblname = strtolower( substr($table, $strip_lenth) );
                $structs[$tblname] = self::parse_table($table, $db);
            }
            $cache->put('schema.struct', $dbname, $structs);
        }
        return $structs;
    }

    public static function describe_models($dbname, $tblnames)
    {
        $cache = app()->cache('', 'file');
        $models = $cache->get('schema', 'model', array());
        if ( ! isset($models[$dbname]) ) {
            $models[$dbname] = array();
            import(MODEL_DIR_NAME . '.*');
            foreach ($tblnames as $tblname) {
                $model = camelize($tblname);
                if ( class_exists($model) && is_subclass_of($model, 'ArrayObject') ) {
                    $models[$dbname][$tblname] = $model;
                }
            }
            $cache->put('schema', 'model', $models);
        }
        return $models;
    }

    public static function parse_table($table, $db) {
        $pkey_array = array();
        $types = array();
        $defaults = array();
        $result = $db->query("SHOW COLUMNS FROM `" . $table ."`");
        foreach ($result as $field) {
            if ( $field['Key'] == 'PRI') {
                array_push($pkey_array, $field['Field']);
            }
            $types[ $field['Field'] ] = $field['Type'];
            $defaults[ $field['Field'] ] = $field['Default'];
        }
        return array('table'=>$table, 'pkey_array'=>$pkey_array,
                     'types'=>$types, 'defaults'=>$defaults);
    }
}


/**
 * 结果对象，自动查询关联对象
 **/
class AuLazyRow extends ArrayObject
{
    private $_state_ = '';
    protected $_factory_ = null;
    protected $_behaviors_ = array();
    protected $_virtuals_ = array();

    public function __construct(array $data=array())
    {
        parent::__construct($data, parent::ARRAY_AS_PROPS);
    }

    public function get_state()
    {
        return $this->offsetGet('_state_');
    }

    public function set_state($state='')
    {
        parent::offsetSet('_state_', $state);
    }

    public function get_schema()
    {
        return $this->factory()->schema;
    }

    public function set_factory($factory)
    {
        parent::offsetSet('_factory_', $factory);
    }

    public function factory($rowclass='', $setclass='')
    {
        if ( ! empty($rowclass) ) {
            $this->offsetGet('_factory_')->rowclass = $rowclass;
        }
        if ( ! empty($setclass) ) {
            $this->offsetGet('_factory_')->setclass = $setclass;
        }
        return $this->offsetGet('_factory_');
    }

    public function offsetGet($prop)
    {
        if ( $this->offsetExists($prop) ) {
            return parent::offsetGet($prop);
        }
        else if ( method_exists($this, 'get_' . $prop) ) {
            return $this->{'get_' . $prop}();
        }
        else if ( array_key_exists($prop, $this->_behaviors_) ) {
            return $this->exec_behavior($prop);
        }
    }

    public function offsetSet($prop, $value)
    {
        if ( method_exists($this, 'set_' . $prop) ) {
            return $this->{'set_' . $prop}($value);
        }
        else if ( $this->offsetExists($prop)
            || array_key_exists($prop, $this->_behaviors_)
            || array_key_exists($prop, $this->_virtuals_) ) {
            return parent::offsetSet($prop, $value);
        }
    }

    public function get_id()
    {
        $pkey_arr = $this->factory()->schema->pkey_array;
        return slice_within($this->getArrayCopy(), $pkey_arr);
    }

    public function get_changes()
    {
        $pkey_arr = $this->factory()->schema->pkey_array;
        return slice_without($this->getArrayCopy(), $pkey_arr);
    }

    public function update($data)
    {
        $pkey_arr = $this->factory()->schema->pkey_array;
        foreach ($data as $key => $val) {
            if ( ! in_array($key, $pkey_arr, true) ) {
                $this->offsetSet($key, $val);
            }
        }
    }

    public function add_behavior($name, $behavior)
    {
        $this->_behaviors_[$name] = $behavior;
    }

    public function get_behavior($name)
    {
        return isset($this->_behaviors_[$name]) ? $this->_behaviors_[$name] : array();
    }

    public function exec_behavior($prop)
    {
        @list($behavior, $model, $foreign, $extra) = $this->_behaviors_[$prop];
        $constructor = new AuConstructor($behavior, array(
            $model, $foreign, $extra
        ));
        $result = $constructor->emit()->emit($this);
        parent::offsetSet($prop, $result);
        return $result;
    }
}


/**
 * 数据集，自动将row封装成obj
 * NOTICE: 在json_encode输出前，要用(array)将它强制转化为索引数组
 */
class AuLazySet extends ArrayIterator
{
    protected $_factory_ = 'AuLazyRow';

    public function __construct(array $data=array(), $factory=null)
    {
        parent::__construct($data);
        if ( ! is_null($factory) ) {
            $this->_factory_ = $factory;
        }
    }

    public function get_rowclass()
    {
        return $this->_factory_->rowclass;
    }

    public function set_rowclass($rowclass)
    {
        $this->_factory_->rowclass = $rowclass;
    }

    public function get_schema()
    {
        return $this->_factory_->schema;
    }

    public function wrap_row($row=null)
    {
        if ($row) {
            return $this->_factory_->wrap($row);
        }
        return $row;
    }

    public function current()
    {
        $row = parent::current();
        return $this->wrap_row($row);
    }

    public function offsetGet($index)
    {
        $row = $this->offsetExists($index) ? parent::offsetGet($index) : null;
        $obj = $this->wrap_row($row);
        return $obj;
    }

    public function options($val='id', $text='name', $blank=false)
    {
        $opts = $blank ? array('' => '（空）') : array();
        foreach ($this as $obj) {
            $opts[ $obj->$val ] = $obj->$text;
        }
        return $opts;
    }
}
