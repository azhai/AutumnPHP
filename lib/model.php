<?php

class ReadOnly
{
	protected $_changes_ = array();

	public function __construct(array $data=null) {
        if (! empty($data)) {
            $this->_changes_ = $data;
        }
    }

	public function __isset($prop) {
		return array_key_exists($prop, $this->_changes_);
	}

	public function __get($prop) {
		if (array_key_exists($prop, $this->_changes_)) {
			return $this->_changes_[$prop];
		}
	}
}


class Model extends ReadOnly
{
	public static $_schema_ = 'default';  #数据库连接
	protected $_table_ = ''; 			#数据表名
	public static $pkeys = array('id');  #主键名
	public static $relations = array();  #关系
	protected $_data_ = array();  		#数据
	protected $_relation_ = array();  	#关系

    public function __construct(array $row=null) {
        parent::__construct($row);
    }

	public function accept(array $row=null) {
        if (! empty($row)) {
            $this->_data_ = array_merge($this->_data_, $row);
        }
    }

	public function __get($prop) {
		if (method_exists($this, 'get_' . $prop)) {
			return call_user_func(array($this, 'get_' . $prop));
		}
		if (array_key_exists($prop, $this->_changes_)) {
			return $this->_changes_[$prop];
		}
		if (array_key_exists($prop, $this->_data_)) {
			return $this->_data_[$prop];
		}
		if (array_key_exists($prop, self::$relations)) {
			return $this->get_relation($prop, self::$relations[$prop]);
		}
	}

	public function __set($prop, $value) {
		if (in_array($prop, self::$pkeys)) {
			return;
		}
		if (method_exists($this, 'set_' . $prop)) {
			return call_user_func(array($this, 'set_' . $prop), $value);
		}
		if (array_key_exists($prop, self::$relations)) {
			return $this->set_relation($prop, $value, self::$relations[$prop]);
		}
		return $this->_changes_[$prop] = $value;
	}

	public function is_dirty() {
		return ! empty($this->_changes_);
	}

    public function save() {
        if ($this->_state_ == 'NEW') { //新增
        } elseif ($this->_state_ == 'DIRTY') { //修改
        }
		$this->_state_ = '';
    }

    public function delete() {
        if ($this->_state_ != 'DELETED') { //删除
        }
        $this->_state_ == 'DELETED';
    }

	public function get_relation($prop, $relation) {
		return;
	}

	public function set_relation($prop, $value, $relation) {
		return;
	}
}
