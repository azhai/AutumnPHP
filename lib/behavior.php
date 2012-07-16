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
            $add_row = array('AuRowSet', 'id_row');
            $this->args = array($this->foreign_fields, array(), null, $add_row);
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
            $single = 'AuHasOne' == get_class($this);
            $add_row = array('AuRowSet', 'field_row');
            $this->args = array($this->foreign_fields, array(), null, 
                                $add_row, $this->foreign, $single);
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


class AuOrganization extends AuBehavior
{
    public $method = 'all';
    public $steps = array('filter_by');
}
