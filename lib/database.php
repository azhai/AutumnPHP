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
				trigger_error("DB Connect failed:" . $e->getMessage(), E_USER_ERROR);
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
	public $params = array();
	public $clause = array(
		'conds' => array(),  'extra' => '',
		'or' => array(), 'join' => array(),
	);

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

	public function sql() {
		$model = $this->model;
		$table = $this->db->escape_table($model::$table);
		$sql = sprintf("SELECT * FROM %s", $table);
		return $sql;
	}

	public function get($id) {
		$model = $this->model;
		$table = $this->db->escape_table($model::$table);
		$expr = create_function('$x', 'return "$x=?";');
		$where = implode(" AND ", array_map($expr, $model::$pkeys));
		$sql = sprintf("SELECT * FROM %s WHERE %s", $table, $where);
		$row = $this->db->query($sql, is_array($id) ? $id : array($id), 'fetch');
		return $this->wrap($row);
	}

	public function all($where, $args=array()) {
		$model = $this->model;
		$sql = sprintf("SELECT * FROM %s", $this->db->escape_table($model::$table));
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

	public function __call($name, $args) {
		$model = $this->model;
		$table = $this->db->escape_table($model::$table);
		$sql = sprintf("SELECT %s(%s) FROM `%s`", $name, implode(', ',$args), $table);
		$value = $this->db->query($sql, 'fetchColumn');
		return $value;
	}

	public function filter($condition, array $params=null) {
		$this->clause['conds'] []= $condition;
		if (! empty($params)) {
			$this->params += $params;
		}
		return $this;
	}

	public function filter_by($data) {
		$conds = array();
		foreach ($data as $field => $value) {
			$conds []= $filed . '=?';
			$this->params []= $params;
		}
		$this->clause['conds'] []= implode(' AND ', $conds);
		return $this;
	}

	public function filter_or($condition, array $params=null) {
		$this->clause['or'] []= $condition;
		if (! empty($params)) {
			$this->params += $params;
		}
		return $this;
	}

	public function filter_in($field, $params) {
		if ( is_string($params) ) {
			$this->clause['conds'] []= $field . 'IN ('. $params .')';
		}
		else if ( is_array($params) ) {
			if ( count($params) <= 1) {
				$condition =  $field . '=?';
			}
			else {
				$mask = rtrim( str_repeat('?,', count($params)), ',' );
				$condition = $field . 'IN ('. $mask .')';
			}
			$this->clause['conds'] []= $condition;
			$this->params += $params;
		}
		else if ( is_object($params) ) {
			$this->clause['conds'] []= $field . 'IN ('. $params->sql() .')';
			$this->params += $params->params;
		}
		return $this;
	}

	public function join() {
		#TODO:...
		return $this;
	}

	public function extra($extra) {
		$this->clause['extra'] = $extra;
		return $this;
	}
}
