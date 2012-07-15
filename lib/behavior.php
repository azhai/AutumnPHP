<?php
defined('APPLICATION_ROOT') or die();


class AuFetchObject extends AuProcedure
{
    public function __construct($subject, $method, $schema)
    {
        parent::__construct($subject, $method);
        $this->schema = $schema;
    }

    public function emit($stmt)
    {
        $row = $stmt->fetch();
        if ( $row === false ) {
            return null;
        }
        else {
            $func = array($this->subject, $this->method);
            return call_user_func($func, $row, $this->schema);
        }
    }
}


class AuFetchAll extends AuConstructor
{
    public $add_row = null;
    public $add_pkey = 'id';

    public function __construct($subject, $schema, $add_row=null)
    {
        parent::__construct($subject);
        $this->schema = $schema;
        $this->add_row = $add_row;
    }

    public function emit($stmt)
    {
        $result = new $this->subject;
        $result->set_schema($this->schema);
        if ( is_null($this->add_row) ) {
            $this->add_row = array($result, 'add_row');
        }
        $i = 0;
        while ($row = $stmt->fetch()) {
            call_user_func($this->add_row, $row, $i++, & $result, $this->add_pkey);
        }
        return $result;
    }
}


class AuBehavior extends AuProcedure
{
    public $steps = array();
    public $db = null;
    public $foreign = '';
    public $extra = array();

    public function __construct($subject, $foreign=null, array $extra=null)
    {
        $this->subject = $subject;
        $this->foreign = is_null($foreign) ? $this->foreign : $foreign;
        if ( ! is_null($extra) ) {
            $this->extra = array_merge($this->extra, $extra);
        }
    }

    public function fill_step_args($primary)
    {
    }

    public function emit($primary)
    {
        $subject = $this->subject;
        if ( ! empty($this->extra['filter']) ) {
            $subject->filter( $this->extra['filter'] );
        }
        $this->fill_step_args($primary);
        foreach ($this->steps as $i => $method) {
            $args = isset($this->args[$i]) ? $this->args[$i] : array();
            $subject = call_user_func_array(array($subject, $method), $args);
        }
        $steplen = count($this->steps);
        $args = isset($this->args[$steplen]) ? $this->args[$steplen] : array();
        $result = call_user_func_array(array($subject, $this->method), $args);
        return $result;
    }
}


class AuBelongsTo extends AuBehavior
{
    public $method = 'get';
    public $steps = array();

    public function fill_step_args($primary)
    {
        if ( empty($this->foreign) ) {
            $this->foreign = $this->subject->get_schema()->tblname . '_id';
        }
        $fields = isset($this->extra['foreign_fields']) ? $this->extra['foreign_fields'] : '*';
        if ( is_array($primary) || $primary instanceof ArrayIterator ) {
            $this->method = 'all';
            $this->steps = array('assign_pkey');
            $pkey = $primary[0]->get_schema()->get_pkey();
            $vals = array();
            foreach ($primary as $pri) {
                $vals []= $pri->{$this->foreign};
            }

            $add_row = array('AuRowSet', 'id_row');
            $this->args = array(
                array($vals),
                array($fields, null, array(), $add_row),
            );
        }
        else {
            $this->args = array(
                array($primary->{$this->foreign}, $fields),
            );
        }
    }
}


class AuHasOne extends AuBehavior
{
    public $method = 'get';
    public $steps = array('filter_by');

    public function fill_step_args($primary)
    {
        $fields = isset($this->extra['foreign_fields']) ? $this->extra['foreign_fields'] : '*';
        if ( is_array($primary) || $primary instanceof ArrayIterator ) {
            $this->method = 'all';
            $schema = $primary[0]->get_schema();
            $pkey = $schema->get_pkey();
            $vals = array();
            foreach ($primary as $pri) {
                $vals []= $pri->$pkey;
            }

            $add_row = array('AuRowSet', 'field_row');
            if ( empty($this->foreign) ) {
                $this->foreign = $schema->tblname . '_id';
            }
            $single = 'AuHasOne' == get_class($this);
            $args = array($fields, null, array(), $add_row, $this->foreign, $single);
        }
        else {
            $schema = $primary->get_schema();
            $pkey = $schema->get_pkey();
            $vals = $primary->$pkey;
            if ( empty($this->foreign) ) {
                $this->foreign = $schema->tblname . '_id';
            }
            $args = $this->method == 'all' ? array($fields) : array(null, $fields);
        }
        $this->args = array(
            array( array($this->foreign => $vals) ),
            $args,
        );
    }
}


class AuHasMany extends AuHasOne
{
    public $method = 'all';
}


class AuManyToMany extends AuBehavior
{
    public $method = 'all';
    public $steps = array('assign_query');
    public $foreign = '';
    public $extra = array(
        'filter'=>'', 'midfilter'=>'', 'left'=>'', 'right'=>''
    );

    public function fill_step_args($primary)
    {
        $fields = isset($this->extra['foreign_fields']) ? $this->extra['foreign_fields'] : '*';
        if ( is_array($primary) || $primary instanceof ArrayIterator ) {
            $primary = $primary[0];
        }
        $subject = $this->subject;
        $pkey = $primary->get_schema()->get_pkey();
        $subpkey = $subject->schema->get_pkey();
        if ( empty($this->foreign) ) {
            $tblnames = array(
                $primary->get_schema()->tblname,
                $subject->get_schema()->tblname,
            );
            sort($tblnames);
            $this->foreign = implode('_', $tblnames);
        }
        $middle = $subject->db->factory( $this->foreign );
        if ( ! empty($this->extra['midfilter']) ) {
            $middle->filter( $this->extra['midfilter'] );
        }
        $query = $middle->filter_by( array($this->extra['left'] => $primary->$pkey) );
        $this->args = array(
            array($subpkey, $query, $this->extra['right']),
            array($fields),
        );
    }
}


class AuOrganization extends AuBehavior
{
    public $method = 'all';
    public $steps = array('filter_by');
}
