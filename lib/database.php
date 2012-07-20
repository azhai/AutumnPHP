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

    /*连接数据库*/
    public function connect()
    {
        if ( is_null($this->conn) ) {
            $opts = array(
                PDO::ATTR_PERSISTENT => false,
                //PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
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
        if ( is_array($args) ) {
            return array_map(array($this, 'quote'), $args);
        }
        else if ( $args instanceof AuLiteral ) {
            return $args->text;
        }
        else {
            $conn = $this->connect();
            return $conn->quote($args, PDO::PARAM_STR);
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
    public function query($sql, $args=array(), $fetch='all')
    {
        $conn = $this->connect();
        self::$sql_history []= array($sql, $args);
        $stmt = $conn->prepare($sql);
        $stmt->execute($args);
        try {
            if ( empty($fetch) || $fetch == 'all' ) {
                $result = $stmt->fetchAll( PDO::FETCH_ASSOC );
            }
            else if ( $fetch == 'one' ) {
                $result = $stmt->fetch( PDO::FETCH_ASSOC );
            }
            else if ( $fetch == 'column' ) {
                $result = $stmt->fetchColumn();
            }
            else {
                $result = $fetch->emit_prepend($stmt);
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

    public function factory($tblname, $schema=null, $rowclass='Object', $setclass='Array')
    {
        if ( is_null($schema) ) {
            $schema = AuSchema::instance($tblname, $this->dbname);
        }
        $obj = new AuQuery($this, $schema->table, $schema->get_pkey());
        $obj->factory = AuFactory::instance($schema, $rowclass, $setclass);
        return $obj;
    }
}


/**
 * 构造对象的过程描述
 **/
class AuQuery
{
    public $factory = null;
    public $db = null;
    public $table = '';
    public $pkey = 'id';

    //public $pvals = array();
    public $conds = array();
    public $or_conds = array();
    public $params = array();

    public $orders = array();
    public $groups = array();
    public $withes = array();

    public function __construct($db, $table='', $pkey='id')
    {
        $this->db = $db;
        $this->table = $table;
        $this->pkey = $pkey;
    }

    public function where()
    {
        if (! empty($this->conds) ) {
            $sql = implode(" AND ", $this->conds);
        }
        /*if (! empty($this->pvals) ) {
            if ( count($this->pvals) <= 1) {
                $cond = $func( $this->pvals[0] );
                $sql = $cond . " AND " . $sql;
            }
            else {
                $conds = array_map($func, $this->pvals);
                $sql = "(" . implode(" OR ", $conds) . ") AND " . $sql;
            }
        }*/
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

    public function reset()
    {
        $this->conds = array();
        $this->or_conds = array();
        $this->params = array();
        $this->orders = array();
        $this->groups = array();
        $this->withes = array();
        return $this;
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
        $sql = sprintf("UPDATE `%s` SET %s %s", $this->table, $mask, $this->where());
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
                       $this->table, $mask, $placeholder);
        $result = $this->db->execute(rtrim($sql), $params, true);
        return $result;
    }

    public function delete()
    {
        $sql = sprintf("DELETE FROM `%s` %s", $this->table, $this->where());
        $result = $this->db->execute(rtrim($sql), $this->params);
        return $result;
    }

    public function select($fields='*', $fetch='all', array $limit=array())
    {
        $sql = sprintf("SELECT %s FROM `%s` %s %s", $fields,
                       $this->table, $this->where(), $this->extra());
        if ( ! empty($limit) ) {
            $sql = rtrim($sql) . " LIMIT " . implode(",", $limit);
        }
        $result = $this->db->query(rtrim($sql), $this->params, $fetch);
        return $result;
    }

    public function get($id=null, $fields='*', $method='fetch_row')
    {
        $factory = $this->factory;
        if ( ! is_null($id) ) {
            if ( $fields == '*' && ! is_null($factory) ) {
                $obj = $factory->get_object($id);
                if ( ! is_null($obj) ) {
                    return $obj;
                }
            }
            $this->assign_pkey($id);
        }
        $fetch = is_null($factory) ? 'one' : new AuProcedure($factory, $method);
        $obj = $this->select($fields, $fetch);
        return $obj;
    }

    public function all($fields='*', $method='fetch_all',
                            $fetch_args=array(), $limit_params=array())
    {
        $count = 0;
        $factory = $this->factory;
        if ( substr($method, 0, 5) == 'with_' ) {
            $result = $factory->$method($this, $fields, $fetch_args, $limit_params);
        }
        else {
            $fetch = new AuProcedure($factory, $method, $fetch_args);
            $result = $this->select($fields, $fetch, $limit_params);
        }
        if ( ! empty($this->withes) ) {
            $count = $result instanceof ArrayIterator ? $result->count() : count($result);
            if ( $count > 0 ) {
                foreach ($this->withes as $with) {
                    $factory->attach_relation($result, $with);
                }
            }
        }
        return $result;
    }

    /* 不会进行上溢出检查 */
    public function page($page, $limit=10, $fields='*',
                            $method='fetch_all', $fetch_args=array())
    {
        $page = intval($page);
        if ( $page > 1 ) {
            $limit_params = array(($page - 1) * $limit, $limit);
        }
        else {
            $limit_params = array($limit);
        }
        return $this->all($fields, $method, $fetch_args, $limit_params);
    }

    public function __call($name, $args)
    {
        $scopes = app()->get_scopes();
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
            $result = $this->select($fields, 'column');
            return $result;
        }
    }

    public function get_or_create($id=null, $withes=array())
    {
        $obj = null;
        if ( $id === 0 || ! empty($id) ) { //是否缓存
            $obj = $this->factory->get_object($id);
        }
        if ( is_null($obj) ) { //是否存在于数据库
            $obj = $this->get($id);
            if ( is_null($obj) ) { //创建一个新的
                $obj = $this->create();
                app()->debug($obj);
            }
        }
        foreach ($withes as $with) {
            $obj->$with;
        }
        return $obj;
    }

    public function save($obj)
    {
        $pkey_vals = $obj->get_id();
        $data = $obj->get_changes();
        if ( $obj instanceof AuLazyRow && $obj->get_state() == 'NEWBIE' ) {
            $id = $this->insert($data);
        }
        else if ( ! empty($pkey_vals) ) {
            $result = $this->reset()->filter_by($pkey_vals)->update($data);
            $id = implode(':', array_values($pkey_vals));
        }
        $this->factory->objects->put($this->name, $id, $obj);
        return $result;
    }

    public function remove($obj)
    {
        $pkey_vals = $obj->get_id();
        if ( ! empty($pkey_vals) ) {
            $this->reset()->filter_by($pkey_vals)->delete();
            $id = implode(':', array_values($pkey_vals));
            $this->factory->objects->delete($this->name, $id);
        }
    }

    public function assign($field, $value) {
        $this->assign_field($value, $field);
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
        $pkey = is_null($pkey) ? $this->pkey : $pkey;
        if ( is_array($pkey) ) {
            if ( is_array($value) ) {
                foreach ($pkey as $pk) {
                    if ( isset($value[$pk]) ) {
                        $this->assign_field($pk, $value[$pk]);
                    }
                }
            }
            else {
                $this->assign_field($pkey[0], $value);
            }
        }
        else {
            $this->assign_field($pkey, $value);
        }
        return $this;
    }

    public function assign_query($field, $query, $qfield='')
    {
        $field = empty($field) ? $this->pkey : $field;
        $qfield = empty($qfield) ? $field : $qfield;
        $sql = sprintf("SELECT %s FROM `%s` %s %s", $qfield,
                       $query->table, $query->where(), $query->extra());
        $this->conds []= $field . ' IN ('. $sql .')';
        $this->params = array_merge($this->params, $query->params);
        return $this;
    }

    public function filter_by($data)
    {
        array_walk($data, array($this, 'assign'));
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
}


class AuFactory
{
    public static $cache = null;
    public $objects = null;
    public $name = '';
    public $schema = null;
    public $rowclass = 'Object';
    public $setclass = 'Array';

    private function __construct($schema, $rowclass='Object', $setclass='Array')
    {
        $this->schema = $schema;
        $this->rowclass = $rowclass;
        $this->setclass = $setclass;
        $this->name = 'object.' . $schema->dbname . '.' . $schema->tblname;
    }

    public static function instance($schema, $rowclass='Object', $setclass='Array')
    {
        if ( is_null(self::$cache) ) {
            self::$cache = app()->cache();
        }
        if ( ! is_null($schema) ) {
            $rowclass = $schema->get_model($rowclass);
        }
        if ( is_subclass_of($rowclass, 'AuLazyRow') ) {
            $setclass = 'AuLazySet';
        }
        $ns = 'model.' . $schema->dbname;
        $obj = self::$cache->get($ns, $schema->tblname);
        if ( is_null($obj) ) {
            $obj = new AuFactory($schema, $rowclass, $setclass);
            self::$cache->put($ns, $schema->tblname, $obj);
        }
        return $obj;
    }

    public function to_id($obj)
    {
        if ( $obj instanceof AuLazyRow ) {
            $id = $obj->get_id();
        }
        else if ( ! is_scalar($obj) ) {
            $pkey_arr = $this->schema->pkey_array;
            $id = slice_within((array)$obj, $pkey_arr);
        }
        else {
            $id = $obj;
        }
        return $id;
    }

    public function get_object($id, $proc=null)
    {
        if ( is_null($this->objects) ) {
            $this->objects = app()->cache();
        }
        $id = is_array($id) ? implode(':', $id) : $id;

        if ( is_null($proc) ) {
            $obj = $this->objects->get($this->name, $id);
        }
        else {
            $obj = $this->objects->retrieve($this->name, $id, $proc);
        }
        if ( ! is_null($obj) && $obj instanceof AuLazyRow ) {
            if ( $obj->get_state() == 'DELETED' ) {
                $this->objects->delete($this->name, $id);
                $obj = null;
            }
        }
        return $obj;
    }

    public function create($row=array()) {
        if ( $row instanceof ArrayObject ) {
            return $row;
        }
        $row = empty($row) ? $this->schema->defaults : $row;
        $model = $this->schema->get_model( $this->rowclass );
        $constructor = new AuConstructor($model, array($row));
        $obj = $constructor->emit();
        if ( is_subclass_of($model, 'AuLazyRow') ) {
            $obj->set_state('NEWBIE');
            $obj->set_factory($this);
        }
        return $obj;
    }

    public function wrap($row)
    {
        if ( $row instanceof ArrayObject ) {
            return $row;
        }
        $model = $this->schema->get_model( $this->rowclass );
        $proc = new AuProcedure($this, 'create', array($row));
        $obj = $this->get_object($this->to_id($row), $proc);
        if ( $obj instanceof AuLazyRow ) {
            $obj->set_state('');
        }
        return $obj;
    }

    public function fetch_row($stmt)
    {
        if ( $this->rowclass == 'Object' ) {
            return $stmt->fetch( PDO::FETCH_OBJ );
        }
        else {
            $row = $stmt->fetch( PDO::FETCH_ASSOC );
            return $this->wrap($row);
        }
    }

    public function fetch_all($stmt)
    {
        if ( $this->setclass == 'Array' && $this->rowclass == 'Object' ) {
            return $stmt->fetchAll( PDO::FETCH_OBJ );
        }
        $rows = $stmt->fetchAll( PDO::FETCH_ASSOC );
        if ( $this->rowclass == 'Array' ) {
            return $rows;
        }
        else if ( $this->setclass == 'Array' ) {
            $count = count($rows);
            return $count == 0 ? array() : array_map(array($this, 'wrap'), $rows);
        }
        else {
            $constructor = new AuConstructor($this->setclass);
            $result = $constructor->emit($rows, $this);
            return $result;
        }
    }

    public function fetch_group($stmt, $unique=false)
    {
        if ( $unique ) {
            $mode = PDO::FETCH_ASSOC | PDO::FETCH_GROUP | PDO::FETCH_UNIQUE;
        }
        else {
            $mode = PDO::FETCH_ASSOC | PDO::FETCH_GROUP;
        }
        $rows = $stmt->fetchAll($mode);
        return $rows;
    }

    public function with_field($query, $fields='*', $fetch_args=array(), $limit_params=array())
    {
        @list($key, $unique) = $fetch_args;
        if ($fields == '*') {
            $fields = sprintf('%s, `%s`.*', $key, $query->table);
        }
        else {
            $fields = sprintf('%s, %s', $key, $fields);
        }
        $fetch = new AuProcedure($this, 'fetch_group', array($unique));
        $rows = $query->select($fields, $fetch, $limit_params);
        return new AuLazySet($rows, $this);
    }

    public function with_unique($query, $fields='*', $fetch_args=array(), $limit_params=array())
    {
        $key = empty($fetch_args) ? $query->pkey : $fetch_args[0];
        return $this->with_field($query, $fields, array($key, true), $limit_params);
    }

    public function attach_relation(& $primary, $prop)
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
