<?php

function get_row_id($row, $pkeys) {
	if ( count($pkeys) == 1 ) {
		$id = $row[ $pkeys[0] ];
	}
	else {
		$id = '.';
		foreach ($pkeys as $pk) {
			$id .= isset($row[$pk]) ? strval($row[$pk]) : '';
			$id .= '.';
		}
		$id = trim($id, '.');
	}
	return $id;
}


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


class Database
{
	private $dsn = '';
    private $user = '';
    private $password = '';
	protected $conn = null;

    public function __construct($dsn, $user='', $password='') {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
    }

    /*连接数据库*/
    public function connect() {
		if ( is_null($this->conn) ) {
			try {
				$conn = new PDO($this->dsn, $this->user, $this->password);
				$conn->setAttribute(PDO::ATTR_PERSISTENT, false);
				$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
				$conn->exec("SET NAMES 'UTF8'; SET TIME_ZONE = '+8:00'");
				//错误模式，默认PDO::ERRMODE_SILENT
				$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				//将空值转为空字符串
				$conn->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_TO_STRING);
				if ( strtolower(substr($this->dsn, 0, 6)) == 'mysql:' ) { //使用MySQL查询缓冲
					$conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
				}
			} catch (PDOException $e) {
				trigger_error("DB Connect failed:" . $e->getMessage(), E_USER_ERROR);
			}
			$this->conn = $conn;
		}
        return $this->conn;
    }

    /*执行修改操作*/
    public function execute($sql, $args=array(), $affected='') {
        $conn = $this->connect();
		$stmt = $conn->prepare($sql);
		try {
			$conn->beginTransaction();
			$stmt->execute($args);
			$conn->commit();
		} catch(PDOException $e) {
            $conn->rollBack();
        }
        if (! empty($affected)) {
            return $conn->$affected();  #lastInsertId / rowCount
        }
    }

    /*执行查询操作*/
    public function query($sql, $args=array(), $fetch='fetchAll') {
        $conn = $this->connect();
		$stmt = $conn->prepare($sql);
        $stmt->execute($args);
        $result = $stmt->$fetch(); #fetchAll / fetch / fetchColumn
        $stmt->closeCursor();
        return $result;
    }
}


class DbFactory
{
	public $model = null;
	public $db = null;

	public function __construct($model) {
		$this->model = $model;
	}

	public function wrap($row) {
		$model = $this->model;
		$id = get_row_id($row, $model::$pkeys);
		$obj = cached('model'.$model, $id);
		if ( is_null($obj) ) {
			$obj = new $model();
			$obj->accept($row);
			cached('model'.$model, $id, $obj);
		}
		return $obj;
	}

	public function get($id) {
		$model = $this->model;
		$expr = create_function('$x', 'return "$x=?";');
		$where = implode(" AND ", array_map($expr, $model::$pkeys));
		$sql = sprintf("SELECT * FROM `%s` WHERE %s", $model::$table, $where);
		$row = $this->db->query($sql, is_array($id) ? $id : array($id), 'fetch');
		return $this->wrap($row);
	}

	public function all($where, $args=array()) {
		$model = $this->model;
		$sql = sprintf("SELECT * FROM `%s`", $model::$table);
		if (! empty($where)) {
			$sql .= " WHERE ";
			$sql .= $where;
		}
		$rows = $this->db->query($sql, is_array($args) ? $args : array($args), 'fetchAll');
		$objs = array();
		foreach ($rows as $row) {
			$objs[] = $this->wrap($row);
		}
		return $objs;
	}
}


class Model extends ReadOnly
{
	public static $schema = 'default';  #数据库连接
	public static $table = ''; 			#数据表名
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
