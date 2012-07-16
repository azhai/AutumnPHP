<?php
defined('APPLICATION_ROOT') or die();


class AuLiteral
{
    public $text = '';

    public function __construct($text)
    {
        $this->text = $text;
    }

    public function __toString()
    {
        return $this->text;
    }
}


/**
 * 使用PDO的数据库连接
 */
class AuDatabase
{
    public $dbname = 'default';
    private $conn = null;
    private $dsn = '';
    private $user = '';
    private $password = '';
    public $prefix = '';

    public static $objects = array();
    public static $sql_history = array();

    public function __construct($dsn, $user='', $password='', $prefix='')
    {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
        $this->prefix = $prefix;
    }

    public function set_config_name($key) {
        $this->dbname = $key;
    }

    public function factory($tblname, $schema=null, $queryclass='AuQuery')
    {
        if ( is_null($schema) ) {
            $schema = AuSchema::instance($tblname, $this->dbname);
        }
        $constructor = new AuConstructor($queryclass, array($this, $schema));
        return $constructor->emit();
    }

    /*连接数据库*/
    public function connect()
    {
        if ( is_null($this->conn) ) {
            $opts = array(
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //错误模式，默认PDO::ERRMODE_SILENT
                PDO::ATTR_ORACLE_NULLS => PDO::NULL_TO_STRING, //将空值转为空字符串
            );
            try {
                $conn = new PDO($this->dsn, $this->user, $this->password, $opts);
            }
            catch (PDOException $e) {
                trigger_error("DB connect failed:" . $e->getMessage(), E_USER_ERROR);
            }

            if ( strtolower(substr($this->dsn, 0, 6)) == 'mysql:' ) {
                try {
                    $conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true); //使用MySQL查询缓冲
                    $conn->exec("SET NAMES 'UTF8'; SET TIME_ZONE = '+8:00'");
                }
                catch (PDOException $e) {
                }
            }
            $this->conn = $conn;
        }
        return $this->conn;
    }

    public function quote($args)
    {
        $conn = $this->connect();
        if ( is_array($args) ) {
            $qargs = array();
            foreach ($args as $arg) {
                $qargs []= $arg instanceof AuLiteral ? $arg : $conn->quote($arg, PDO::PARAM_STR);
            }
            return $qargs;
        }
        else {
            return $arg instanceof AuLiteral ? $arg : $conn->quote($args, PDO::PARAM_STR);
        }
    }

    /*执行修改操作*/
    public function execute($sql, $args=array(), $insert_id=false)
    {
        $result = true;
        $conn = $this->connect();
        $sql = $this->dump($sql, $args);
        self::$sql_history []= array($sql, array());
        try {
            $conn->beginTransaction();
            $conn->exec($sql);
            if ($insert_id) { //Should use lastInsertId BEFORE you commit
                $result = $conn->lastInsertId();
            }
            $conn->commit();
        } catch(PDOException $e) {
            $conn->rollBack();
            trigger_error("DB execute failed:" . $e->getMessage(), E_USER_ERROR);
        }
        return $result;
    }

    /*执行查询操作*/
    public function query($sql, $args=array(), $fetch=null)
    {
        $conn = $this->connect();
        self::$sql_history []= array($sql, $args);
        $stmt = $conn->prepare($sql);
        $stmt->execute($args);
        try {
            if ( is_null($fetch) ) {
                $result = $stmt->fetchAll();
            }
            else {
                $result = $fetch->emit($stmt);
            }
        } catch(PDOException $e) {
            trigger_error("DB query failed:" . $e->getMessage(), E_USER_ERROR);
        }
        $stmt->closeCursor();
        return $result;
    }

    /*生成可打印的SQL语句*/
    public function dump($sql, $args=array())
    {
        if (strpos($sql, "%")) {
            $sql = str_replace("%%", "%", $sql);
            $sql = str_replace("%", "%%", $sql);
        }
        $sql = str_replace("?", "%s", $sql);
        return vsprintf($sql, $this->quote($args));
    }

    public function dump_all($return=false)
    {
        @ob_start();
        foreach (self::$sql_history as $i => $line) {
            list($sql, $args) = $line;
            echo ($i + 1) . ": ";
            echo empty($args) ? $sql : $this->dump($sql, $args);
            echo "; <br />\n";
        }
        return $return ? ob_get_clean() : ob_end_flush();
    }

    public function log_all()
    {
        app()->log( $this->dump_all(true) );
    }

    public static function & get_collection($schema)
    {
        if ( ! array_key_exists($schema->dbname, self::$objects) ) {
            self::$objects[ $schema->dbname ] = array();
        }
        if ( ! array_key_exists($schema->tblname, self::$objects[ $schema->dbname ]) ) {
            self::$objects[ $schema->dbname ][ $schema->tblname ] = array();
        }
        $collection = self::$objects[ $schema->dbname ][ $schema->tblname ];
        return $collection;
    }

    public static function get($collection, $id)
    {
        $id = is_array($id) ? implode(':', $id) : $id;
        if ( array_key_exists($id, $collection) ) {
            $obj = $collection[$id];
            if ( $obj->state == 'DELETED' ) {
                unset($collection[$id]);
                return null;
            }
            else {
                return $obj;
            }
        }
    }

    public function get_or_create($tblname, $id=null, $withes=array())
    {
        $obj = null;
        if ( $id === 0 || ! empty($id) ) {
            $obj = $this->factory($tblname)->get($id);
        }
        if ( is_null($obj) ) {
            $schema = AuSchema::instance($tblname, $this->dbname);
            $model = $schema->get_model('AuRowObject');
            $obj = call_user_func(array($model,'create'), array(), $schema);
        }
        foreach ($withes as $with) {
            $obj->$with;
        }
        return $obj;
    }

    public function save($obj)
    {
        $schema = $obj->get_schema();
        $pkey_vals = $obj->get_ids();
        $data = $obj->get_changes();
        $query = $this->factory($schema->tblname);
        if ( $obj->get_state() == 'NEWBIE' ) {
            $result = $query->insert($data);
        }
        else if ( ! empty($pkey_vals) ) {
            $result = $query->filter_by($pkey_vals)->update($data);
        }
        return $result;
    }

    public function remove($obj)
    {
        $schema = $obj->get_schema();
        $pkey_vals = $obj->get_ids();
        if ( ! empty($pkey_vals) ) {
            $query = $this->factory($schema->tblname)->filter_by($pkey_vals);
            $query->delete();
            $id = implode(':', array_values($pkey_vals));
            unset(self::$objects[ $schema->dbname ][ $schema->tblname ][$id]);
        }
    }
}


/**
 * 构造对象的过程描述
 **/
class AuQuery
{
    public $db = null;
    public $schema = null;

    public $conds = array();
    public $or_conds = array();
    public $params = array();

    public $orders = array();
    public $groups = array();

    public $withes = array();

    public function __construct($db, $schema)
    {
        $this->db = $db;
        $this->schema = $schema;
    }

    public function __call($name, $args)
    {
        $scopes = array();
        if (! empty($scopes) && array_key_exists($name, $scopes)) {
            $this->filter($scopes[$name], $args);
            return $this;
        }
        else {
            $name = strtoupper($name);
            if ('COUNT' == $name && empty($args)) { //纠错
                $fields = 'COUNT(*)';
            }
            else {
                $fields = sprintf("%s(%s)", $name, implode(", ",$args));
            }
            $fetch = new AuProcedure('', create_function('$stmt', 'return $stmt->fetchColumn();'));
            $result = $this->select($fetch, $fields);
            return $result;
        }
    }

    public function where()
    {
        if (! empty($this->conds) ) {
            $sql = implode(" AND ", $this->conds);
        }
        if (! empty($this->or_conds) ) {
            $sql = "(" . $sql . implode(") OR (", $this->or_conds) . ")";
        }
        return empty($sql) ? "" : "WHERE " . $sql;
    }

    public function extra()
    {
        $sql = "";
        if (! empty($this->groups) ) {
            $sql .= " GROUP BY " . implode(", ", $this->groups);
        }
        if (! empty($this->orders) ) {
            $sql .= " ORDER BY " . implode(", ", $this->orders);
        }
        return ltrim($sql);
    }

    public function select($fetch=null, $fields='*', $limit=array())
    {
        $sql = sprintf("SELECT %s FROM `%s` %s %s", $fields,
                       $this->schema->table, $this->where(), $this->extra());
        if ( ! empty($limit) ) {
            $sql = rtrim($sql) . " LIMIT " . implode(",", $limit);
        }
        $result = $this->db->query(rtrim($sql), $this->params, $fetch);
        return $result;
    }

    public function update($data)
    {
        $mask = "";
        $params = array();
        foreach ($data as $key => $val) {
            $mask .= $key . "=?, ";
            $params []= $val;
        }
        $mask = substr($mask,-2) == ", " ? substr($mask, 0, -2) : $mask;
        $params = array_merge($params, $this->params);
        $sql = sprintf("UPDATE `%s` SET %s %s", $this->schema->table, $mask, $this->where());
        $result = $this->db->execute(rtrim($sql), $params);
        return $result;
    }

    public function insert($data, $replace=false)
    {
        $mask = "";
        $params = array();
        foreach ($data as $key => $val) {
            $mask .= $key . ", ";
            $params []= $val;
        }
        $mask = substr($mask,-2) == ", " ? substr($mask, 0, -2) : $mask;
        $placeholder = rtrim( str_repeat( '?,', count($params) ), ',');
        $sql = sprintf("%s INTO `%s` (%s) VALUES (%s)", $replace ? "REPLACE" : "INSERT",
                       $this->schema->table, $mask, $placeholder);
        $result = $this->db->execute(rtrim($sql), $params, true);
        return $result;
    }

    public function delete()
    {
        $sql = sprintf("DELETE FROM `%s` %s", $this->schema->table, $this->where());
        $result = $this->db->execute(rtrim($sql), $this->params);
        return $result;
    }

    public function all($fields='*', $fetch=null, $limit_params=array(),
                        $add_row=null, $add_pkey='id')
    {
        if ( is_null($fetch) ) {
            $fetch = new AuFetchAll('AuRowSet', $this->schema);
        }
        if ( ! is_null($add_row) ) {
            $fetch->add_row = $add_row;
            $fetch->add_pkey = $add_pkey;
        }
        $result = $this->select($fetch, $fields, $limit_params);
        $count = $result instanceof ArrayIterator ? $result->count() : count($result);
        if ( $count > 0 && ! empty($this->withes) ) {
            foreach ($this->withes as $with) {
                $this->with_relation($result, $with);
            }
        }
        return $result;
    }

    /* 不会进行上溢出检查 */
    public function page($page, $limit=10, $fields='*', $fetch=null,
                        $add_row=null, $add_pkey='id')
    {
        $page = intval($page);
        if ( $page > 1 ) {
            $limit_params = array(($page - 1) * $limit, $limit);
        }
        else {
            $limit_params = array($limit);
        }
        return $this->all($fields, $fetch, $limit_params, $add_row, $add_pkey);
    }

    public function get($id=null, $fields='*')
    {
        if ( ! is_null($id) ) {
            if ( $fields == '*' ) {
                $collection = AuDatabase::get_collection($this->schema);
                $obj = AuDatabase::get($collection, $id);
                if ( ! is_null($obj) ) {
                    return $obj;
                }
            }
            $pkey = $this->schema->get_pkey();
            $this->assign_pkey($id, $pkey);
        }
        $model = $this->schema->get_model('AuRowObject');
        $fetch = new AuFetchObject($model, 'wrap', $this->schema);
        $obj = $this->select($fetch, $fields);
        return $obj;
    }

    public function assign($field, $value) {
        $this->assign_field($field, $value);
        return $this;
    }
    public function assign_field($field, $value)
    {
        if ( is_array($value) ) {
            $value = array_unique($value);
            $arrlen = count($value);
            if ( $arrlen > 1 ) {
                $mask = rtrim( str_repeat('?,', $arrlen), ',' );
                $this->conds []= $field . ' IN ('. $mask .')';
                $this->params = array_merge($this->params, $value);
                return $this;
            }
            else {
                $value = $arrlen == 1 ? array_pop($value) : null;
            }
        }
        $this->conds []= $field . '=?';
        $this->params []= $value;
        return $this;
    }

    public function assign_pkey($value, $pkey=null)
    {
        $pkey = is_null($pkey) ? $this->schema->get_pkey() : $pkey;
        if ( is_array($pkey) ) {
            if ( is_array($value) ) {
                foreach ($pkey as $pk) {
                    if ( isset($value[$pk]) ) {
                        $this->assign($pk, $value[$pk]);
                    }
                }
            }
            else {
                $this->assign($pkey[0], $value);
            }
        }
        else {
            $this->assign($pkey, $value);
        }
        return $this;
    }

    public function assign_query($field, $query, $qfield='')
    {
        $field = empty($field) ? $this->schema->get_pkey() : $field;
        $qfield = empty($qfield) ? $field : $qfield;
        $sql = sprintf("SELECT %s FROM `%s` %s %s", $qfield,
                       $query->schema->table, $query->where(), $query->extra());
        $this->conds []= $field . ' IN ('. $sql .')';
        $this->params = array_merge($this->params, $query->params);
        return $this;
    }

    public function filter_by($data)
    {
        foreach ($data as $field => $value) {
            $this->assign($field, $value);
        }
        return $this;
    }

    public function filter($condition, array $params=null)
    {
        $condition = trim($condition);
        if ( false !== stripos($condition, 'OR')
            && '(' != substr($condition, 0, 1) && ')' != substr($condition, -1)) { //纠错
            $condition = '(' . $condition . ')';
        }
        $this->conds []= $condition;
        if (! empty($params)) {
            $this->params = array_merge($this->params, $params);
        }
        return $this;
    }

    public function union($condition, array $params=null)
    {
        $this->or_conds []= $condition;
        if (! empty($params)) {
            $this->params = array_merge($this->params, $params);
        }
        return $this;
    }

    public function order_by($field)
    {
        array_push($this->orders, $field);
        return $this;
    }

    public function group_by($field)
    {
        array_push($this->groups, $field);
        return $this;
    }

    public function with()
    {
        $this->withes = func_get_args();
        return $this;
    }

    public function with_relation(& $primary, $prop)
    {
        $pri = $primary[0];
        @list($behavior, $model, $foreign, $extra) = $pri->get_behavior($prop);
        $constructor = new AuConstructor($behavior, array( 
            $model, $foreign, $extra
        ));
        $result = $constructor->emit()->emit($primary);

        if ($behavior == 'AuBelongsTo') {
            foreach ($primary as $i => $pri) {
                $fval = $pri->$foreign;
                $pri->offsetSet($prop, $result[$fval]);
                $primary->offsetSet($i, $pri);
            }
        }
        else if ($behavior == 'AuHasOne' || $behavior == 'AuHasMany') {
            $pkey = $this->schema->get_pkey();
            foreach ($primary as $i => $pri) {
                $rel_result = $result[ $pri->$pkey ];
                if ($behavior == 'AuHasMany') {
                    $rel_result = $rel_result ? $rel_result : array();
                }
                $pri->offsetSet($prop, $rel_result);
                $primary->offsetSet($i, $pri);
            }
        }
        return $primary;
    }
}
