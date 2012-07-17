<?php
defined('APPLICATION_ROOT') or die();


class AuSchema extends AuConfigure
{
    protected $_defaults_ = array(
        'table'=>'', 'pkey_array'=>array('id'),
        'types'=>array(), 'defaults'=>array()
    );
    public $dbname = 'default';
    public $tblname = '';
    public static $all_descs = array();
    public static $models = array();
    public static $tables = array();

    public function __construct($tblname, $dbname='default')
    {
        $this->tblname = $tblname;
        $this->dbname = $dbname;
        if ( array_key_exists($tblname, self::$all_descs[$dbname]) ) {
            $desc = self::$all_descs[$dbname][$tblname];
            $dbtbl = $dbname . '.' . $tblname;
            $desc['model'] = isset( self::$models[$dbtbl] ) ? self::$models[$dbtbl] : '';
        }
        else {
            $desc = array();
            $db = app()->db($dbname);
            $this->_defaults_['table'] = $db->prefix . $tblname;
        }
        parent::__construct($desc);
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

    public static function instance($tblname, $dbname='default')
    {
        if ( ! array_key_exists($tblname, self::$tables) ) {
            if ( ! array_key_exists($dbname, self::$all_descs) ) {
                $describe = self::describe($dbname);
                self::$all_descs[$dbname] = $describe['desc'];
                self::$models = array_merge(self::$models, $describe['model']);
            }
            self::$tables[$tblname] = new AuSchema($tblname, $dbname);
        }
        return self::$tables[$tblname];
    }

    public static function describe($dbname, $db=null)
    {
        $cache = app()->cache()->file;
        $describe = $cache->get('schema', $dbname);
        if ( is_null($describe) ) {
            if ( is_null($db) ) {
                $db = app()->db($dbname);
            }
            $describe = array('model'=>array(), 'desc'=>array());
            $prefix = str_replace('_', '\_', $db->prefix);
            $strip_lenth = empty($prefix) ? 0 : strlen($prefix) - 1;
            $sql = "SHOW TABLES LIKE '" . $prefix . "%'";
            $tables = array_map('array_pop', $db->query($sql));

            import('libs.models.*');
            foreach ($tables as $table) {
                $tblname = strtolower( substr($table, $strip_lenth) );
                $describe['desc'][$tblname] = self::parse_table($table, $db);
                $model = camelize($tblname);
                if ( class_exists($model) && is_subclass_of($model, 'ArrayObject') ) {
                    $dbtbl = $dbname . '.' . $tblname;
                    $describe['model'][$dbtbl] = $model;
                }
            }
            $cache->put('schema', $dbname, $describe);
        }
        return $describe;
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
 * 结果对象
 **/
class AuLazyRow extends ArrayObject
{
    private $_state_ = '';
    protected $_schema_ = null;
    protected $_behaviors_ = array();
    protected $_virtuals_ = array();

    public function __construct(array $data=array(), $schema=null)
    {
        parent::__construct($data, parent::ARRAY_AS_PROPS);
        $this->set_schema($schema);
    }

    public static function create($row=array(), $schema=null) {
        $row = empty($row) ? $schema->defaults : $row;
        $model = $schema->get_model('AuLazyRow');
        $constructor = new AuConstructor($model, array($row, $schema));
        $obj = $constructor->emit();
        $obj->set_state('NEWBIE');
        return $obj;
    }

    public static function wrap($row, $schema)
    {
        $collection = AuDatabase::get_collection($schema);
        $pkey_arr = $schema->pkey_array;
        $id = slice_assoc($row, $pkey_arr);
        $obj = AuDatabase::get($collection, $id);
        if ( is_null($obj) ) {
            $model = $schema->get_model('AuLazyRow');
            $constructor = new AuConstructor($model, array($row, $schema));
            $obj = $constructor->emit();
            $id = is_array($id) ? implode(':', $id) : $id;
            //$collection[$id] = & $obj;
            AuDatabase::$objects[ $schema->dbname ][ $schema->tblname ][$id] = & $obj;
        }
        return $obj;
    }

    public function get_state()
    {
        return $this->_state_;
    }

    public function set_state($state='')
    {
        return $this->_state_ = $state;
    }

    public function get_schema()
    {
        return $this->_schema_;
    }

    public function set_schema($schema)
    {
        return $this->_schema_ = $schema;
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

    public function get_ids()
    {
        $pkey_arr = $this->get_schema()->pkey_array;
        $row = $this->getArrayCopy();
        return slice_assoc($row, $pkey_arr);
    }

    public function get_changes()
    {
        $pkey_arr = $this->get_schema()->pkey_array;
        $row = $this->getArrayCopy();
        foreach ($pkey_arr as $pk) {
            unset( $row[$pk] );
        }
        return $row;
    }

    public function update($data)
    {
        $pkey_arr = $this->get_schema()->pkey_array;
        foreach ($data as $key => $val) {
            if ( ! in_array($key, $pkey_arr) ) {
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
    protected $_schema_ = null;
    protected $_rowclass_ = 'AuLazyRow';

    public function __construct(array $data=array(), $schema=null)
    {
        parent::__construct($data);
        $this->set_schema($schema);
        if ( ! is_null($schema) ) {
            $this->_rowclass_ = $schema->get_model('AuLazyRow');
        }
    }

    public function get_schema()
    {
        return $this->_schema_;
    }

    public function set_schema($schema)
    {
        return $this->_schema_ = $schema;
    }

    public function get_rowclass()
    {
        return $this->_rowclass_;
    }

    public function set_rowclass($rowclass)
    {
        return $this->_rowclass_ = $rowclass;
    }

    public function add_row($row, $index)
    {
        $this->append($row);
    }

    public static function id_row($row, $index, & $result, $pkey='id')
    {
        $result->offsetSet($row[$pkey], $row);
    }

    public static function field_row($row, $index, & $result, $field, $single=false)
    {
        $key = $row[$field];
        if ( $single === false ) {
            $result->set_rowclass('AuLazySet');
            if ( ! $result->offsetExists($key) ) {
                $rowset = new AuLazySet(array(), $result->get_schema());
                $rowset->set_rowclass('AuLazyRow');
                $result->offsetSet($key, $rowset);
            }
            $result->offsetGet($key)->append($row);
        }
        else {
            if ( ! $result->offsetExists($key) ) {
                $result->offsetSet($key, $row);
            }
        }
    }

    public function wrap_row($row=null)
    {
        if ($row) {
            $rowclass = $this->get_rowclass();
            if ($rowclass == 'AuLazyRow') {
                $func = array($rowclass, 'wrap');
                $obj = call_user_func($func, $row, $this->get_schema());
                return $obj;
            }
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
        return $this->wrap_row($row);
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
