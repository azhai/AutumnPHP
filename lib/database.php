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


class Database
{
	private $dsn = '';
    private $user = '';
    private $password = '';
    private $prefix = '';
	protected $conn = null;

    public function __construct($dsn, $user='', $password='', $prefix='') {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
        $this->prefix = $prefix;
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
				trigger_error("DB connect failed:" . $e->getMessage(), E_USER_ERROR);
			}
			$this->conn = $conn;
		}
        return $this->conn;
    }

	public function escape_table($table) {
		return sprintf('`%s%s`', $this->prefix, $table);
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
			trigger_error("DB execute failed:" . $e->getMessage(), E_USER_ERROR);
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
		try {
			$result = $stmt->$fetch(); #fetchAll / fetch / fetchColumn
		} catch(PDOException $e) {
			trigger_error("DB query failed:" . $e->getMessage(), E_USER_ERROR);
        }
        $stmt->closeCursor();
        return $result;
    }
}


class DbFactory
{
	public $model = null;
	public $table = '';
	public $db = null;
	public $fields = '*';
	public $conds = array();
	public $or_conds = array();
	public $params = array();
	public $extra = '';

	public function __construct($model) {
		$this->model = $model;
	}

	public static function init($table, $db) {
		$class = __CLASS__;
		$obj = new $class('Model');
		$obj->table = $table;
		$obj->db = $db;
		return $obj;
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

	public function sql($action="") {
		$model = $this->model;
		if ( empty($this->table) ) {
			$this->table = $this->db->escape_table($model::$table);
		}
		$conds = "";
		if (! empty($this->conds) ) {
			$conds = implode(" AND ", $this->conds);
		}
		if (! empty($this->or_conds) ) {
			$conds = "(" . $conds . implode(") OR (", $this->or_conds) . ")";
		}
		$conds = empty($conds) ? "" : "WHERE " . $conds;
		if ( empty($action) ) {
			$action = "SELECT " . $this->fields . " FROM %s";
		}
		$sql = rtrim(sprintf($action . " %s %s", $this->table, $conds, $this->extra));
		return $sql;
	}

	public function get() { //传对应各主键的值
		if(func_num_args() > 0) {
			$model = $this->model;
			$pkeys = $model::$pkeys;
			$ids = func_get_args();
			foreach ($ids as $i => $id) {
				if (! is_null($id)) {
					$this->_in($pkeys[$i], $id);
				}
			}
		}
		$row = $this->db->query($this->sql(), $this->params, 'fetch');
		return $this->fields == "*" ? $this->wrap($row) : $row;
	}

	public function all($fields="*") {
		if (! empty($fields) && $fields != "*") {
			$this->fields = $fields;
		}
		$rows = $this->db->query($this->sql(), $this->params, 'fetchAll');
		if ($this->fields == "*") {
			$objs = array();
			foreach ($rows as $row) {
				$objs[] = $this->wrap($row);
			}
			return $objs;
		}
		else {
			return $rows;
		}
	}

	public function __call($name, $args) {
		$scopes = $this->model->scopes();
		if (! empty($scopes) && array_key_exists($name, $scopes)) {
			$this->filter($scopes[$name], $args);
			return $this;
		}
		else {
			$action = "SELECT " . $name . "(" . implode(", ",$args) . ") FROM %s";
			$value = $this->db->query($this->sql($action), $this->params, 'fetchColumn');
			return $value;
		}
	}

	public function filter($condition, array $params=null) {
		$this->conds []= $condition;
		if (! empty($params)) {
			$this->params += $params;
		}
		return $this;
	}

	public function filter_by($data) {
		foreach ($data as $field => $value) {
			$this->_in($field, $value);
		}
		return $this;
	}

	public function union($condition, array $params=null) {
		$this->or_conds []= $condition;
		if (! empty($params)) {
			$this->params += $params;
		}
		return $this;
	}

	public function select($fields) {
		$this->fields = $fields;
		return $this;
	}

	public function extra($extra) {
		$this->extra = $extra;
		return $this;
	}

	public function _in($field, $value) {
		if ( is_array($params) ) {
			$arrlen = count($params);
			if ( $arrlen == 0 ) {
				$value = null;
			}
			else if ( $arrlen == 1 ) {
				$value = array_pop($value);
			}
			else {
				$mask = rtrim( str_repeat('?,', $arrlen), ',' );
				$this->conds []= $field . ' IN ('. $mask .')';
				$this->params += $value;
				return;
			}
		}
		else if ( is_object($value) ) {
			$this->conds []= $field . ' IN ('. $value->sql() .')';
			$this->params += $value->params;
			return;
		}

		if ( is_null($value) ) {
			$this->conds []= $field . '=NULL';
		}
		else {
			$value = is_bool($value) ? intval($value) : $value;
			$this->conds []= $field . '=?';
			$this->params []= $value;
		}
		return;
	}
}
