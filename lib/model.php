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
	protected $_data_ = array();  		#数据
	protected $_reldata_ = array();  	#关系数据
	public static $schema = 'default';  #数据库连接
	public static $table = ''; 			#数据表名
	public static $pkeys = array('id');  #主键名

    public function __construct(array $row=null) {
        parent::__construct($row);
    }

	public function accept(array $row=null) {
        if (! empty($row)) {
            $this->_data_ = array_merge($this->_data_, $row);
        }
    }

	public function scopes() { #快捷查询
		return array();
	}

	public function relations() { #关系
		return array();
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
		if (array_key_exists($prop, $this->_reldata_)) {
			return $this->_reldata_[$prop];
		}
		$relations = $this->relations();
		if (array_key_exists($prop, $relations)) {
			return $this->relate_with($prop, $relations[$prop]);
		}
	}

	public function __set($prop, $value) {
		$class = get_class($this);
		if (in_array($prop, $class::$pkeys)) {
			return;
		}
		if (method_exists($this, 'set_' . $prop)) {
			return call_user_func(array($this, 'set_' . $prop), $value);
		}
		$relations = $this->relations();
		if (array_key_exists($prop, $relations)) {
			return $this->_reldata_[$prop] = $value;
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

	public function relate_with($prop, $relation) {
		$model = $relation['model'];
		$factory = cached('app')->factory($model);
		if ( array_key_exists('extra', $relation) ) {
			$factory = call_user_func_array(array($factory, 'filter'), $relation['extra']);
		}
		switch (strtolower($relation['type'])) {
			case 'belongs_to':
				$fkey = $relation['fkey'];
				$data = $factory->get($this->$fkey);
				break;
			case 'has_many':
				$class = get_class($this);
				$fkey = isset($relation['fkey']) ? $relation['fkey'] : $class::$pkeys[0];
				$field = $relation['field'];
				$data = $factory->filter_by(array($field=>$this->$fkey))->all();
				break;
			case 'many_many':
				$middle = $relation['middle'];
				$query = DbFactory::init($middle, $this->db);
				if ( array_key_exists('mid_extra', $relation) ) {
					$query = call_user_func_array(array($query, 'filter'), $relation['mid_extra']);
				}
				$lkey = isset($relation['lkey']) ? $relation['lkey'] : strtolower(get_class($this)) . '_id';
				$rkey = isset($relation['rkey']) ? $relation['rkey'] : strtolower(get_class($factory->model)) . '_id';
				$class = get_class($this);
				$pkey = $class::$pkeys[0];
				$query = $query->filter_by(array($lkey=>$this->$pkey))->select($rkey);
				$data = $factory->filter_by(array($model::$pkeys[0]=>$query))->all();
				break;
		}
		return $this->$prop = $data;
	}
}
