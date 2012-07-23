<?php
defined('APPLICATION_ROOT') or die();


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


class AuBehavior extends AuProcedure
{
    public $procs = array();
    public $primary = null;
    public $primary_set = array();
    public $foreign = '';
    public $foreign_fields = '*';
    public $extra = array();

    public function __construct($subject, $foreign=null, array $extra=null)
    {
        $this->subject = $subject;
        $this->foreign = is_null($foreign) ? $this->foreign : $foreign;
        if ( ! is_null($extra) ) {
            $this->extra = array_merge($this->extra, $extra);
        }
    }

    public function init_procs($primary)
    {
        if ( is_array($primary) || $primary instanceof ArrayIterator ) {
            $this->primary_set = $primary;
            $this->primary = $primary[0];
        }
        else {
            $this->primary = $primary;
        }
        $schema = $this->primary->get_schema();
        $this->retrieve_foreign($schema);
        if ( ! empty($this->extra['filter']) ) {
            $this->procs []= new AuProcedure(null, 'filter', array($this->extra['filter']));
        }
        return $schema;
    }

    public function retrieve_foreign($schema)
    {
        if ( empty($this->foreign) ) {
            $this->foreign = $schema->tblname . '_id';
        }
        if ( isset($this->extra['foreign_fields']) ) {
            $this->foreign_fields = $this->extra['foreign_fields'];
        }
    }

    public function emit($primary)
    {
        $schema = $this->init_procs($primary);
        $subject = app()->db( $schema->dbname )->factory($this->subject);
        foreach ($this->procs as $proc) {
            $proc->subject = $subject;
            $subject = $proc->emit();
        }
        //
        if ( ! empty($this->method) ) {
            $this->subject = $subject;
            return parent::emit();
        }
        else {
            return $subject;
        }
    }
}


class AuBelongsTo extends AuBehavior
{
    public $method = 'get';

    public function init_procs($primary) {
        $schema = parent::init_procs($primary);
        if ( ! empty($this->primary_set) ) {
            $this->method = 'all';
            $vals = array();
            foreach ($this->primary_set as $primary) {
                $vals []= $primary->{$this->foreign};
            }
            $this->procs []= new AuProcedure(null, 'assign_pkey', array($vals));
            $this->args = array($this->foreign_fields, 'with_unique');
        }
        else {
            $this->args = array($primary->{$this->foreign}, $this->foreign_fields);
        }
        return $schema;
    }
}


class AuHasOne extends AuBehavior
{
    public $method = 'get';

    public function init_procs($primary) {
        $schema = parent::init_procs($primary);
        $pkey = $this->primary->get_schema()->get_pkey();
        if ( ! empty($this->primary_set) ) {
            $this->method = 'all';
            $vals = array();
            foreach ($this->primary_set as $primary) {
                $vals []= $primary->$pkey;
            }
            $method = 'AuHasOne' == get_class($this) ? 'with_unique' : 'with_field';
            $this->args = array($this->foreign_fields, $method, array($this->foreign));
        }
        else {
            $vals = $this->primary->$pkey;
            $this->args = $this->method == 'all' ? array($this->foreign_fields) :
                                                array(null, $this->foreign_fields);
        }
        $this->procs []= new AuProcedure(null, 'filter_by', array(
            array($this->foreign => $vals) )
        );
        return $schema;
    }
}


class AuHasMany extends AuHasOne
{
    public $method = 'all';
}


class AuManyToMany extends AuBehavior
{
    public $method = 'all';
    public $foreign = '';
    public $extra = array(
        'filter'=>'', 'midfilter'=>'', 'left'=>'', 'right'=>''
    );

    public function retrieve_foreign($schema)
    {
        if ( empty($this->foreign) ) {
            $tblnames = array($schema->tblname, $this->subject);
            sort($tblnames);
            $this->foreign = implode('_', $tblnames);
        }
    }

    public function init_procs($primary) {
        $schema = parent::init_procs($primary);
        $extra = array();
        if ( ! empty($this->extra['midfilter']) ) {
            $extra['filter'] = $this->extra['midfilter'];
        }
        $query = new AuHasMany($this->foreign, $this->extra['left'], $extra);
        $query->method = null;
        $query = $query->emit($primary);

        $this->args = array($this->foreign_fields);
        $this->procs []= new AuProcedure(null, 'assign_query', array(
            null, $query, $this->extra['right']
        ));
        return $schema;
    }
}
