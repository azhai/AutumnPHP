<?php
defined('APPLICATION_ROOT') or die();


class AuBehavior extends AuProcedure
{
	public $steps = array();
	public $db = null;
	public $fkey = '';
	public $filter = '';

    public function __construct($subject, $fkey='', $filter='')
	{
        $this->subject = $subject;
        $this->fkey = $fkey;
        $this->filter = $filter;
    }

	public function fill_step_args($primary)
	{
	}

	public function emit($primary)
	{
		$subject = $this->subject;
		if ( ! empty($this->filter) ) {
			$subject->filter( $this->filter );
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
		$this->args = array(
			array( $primary->{$this->fkey} ),
		);
	}
}


class AuHasOne extends AuBehavior
{
    public $method = 'get';
	public $steps = array('filter_by');

	public function fill_step_args($primary)
	{
		$pkey = $primary->get_schema()->get_pkey();
		$this->args = array(
			array( array($this->fkey => $primary->$pkey) ),
			array(),
		);
	}
}


class AuHasMany extends AuBehavior
{
    public $method = 'all';
	public $steps = array('filter_by');

	public function fill_step_args($primary)
	{
		$pkey = $primary->get_schema()->get_pkey();
		$this->args = array(
			array( array($this->fkey => $primary->$pkey) ),
			array(),
		);
	}
}


class AuManyToMany extends AuBehavior
{
    public $method = 'all';
	public $steps = array('assign_query');
	public $middle = '';
	public $left = '';
	public $right = '';

    public function __construct($subject, $filter='', $middle='', $midfilter='', $left='', $right='')
	{
        $this->subject = $subject;
        $this->middle = $middle;
        $this->filter = $filter;
        $this->midfilter = $midfilter;
        $this->left = $left;
        $this->right = $right;
    }

	public function fill_step_args($primary)
	{
		$subject = $this->subject;
		$pkey = $primary->get_schema()->get_pkey();
		$middle = $subject->db->factory( $this->middle );
		if ( ! empty($this->midfilter) ) {
			$middle->filter( $this->midfilter );
		}
		$query = $middle->filter_by( array($this->left => $primary->$pkey) );
		$fkey = $subject->schema->get_pkey();
		$this->args = array(
			array($fkey, $query, $this->right),
			array(),
		);
	}
}


class AuOrganization extends AuBehavior
{
    public $method = 'all';
	public $steps = array('filter_by');
}
