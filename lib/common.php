<?php
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('LIBRARY_DIR') or define('LIBRARY_DIR', dirname(__FILE__));
defined('APPLICATION_ROOT') or define('APPLICATION_ROOT', dirname(LIBRARY_DIR));
defined('RUNTIME_DIR') or define('RUNTIME_DIR', APPLICATION_ROOT . DS . 'runtime');
defined('CONFIG_FILENAME') or define('CONFIG_FILENAME', APPLICATION_ROOT . DS . 'config.php');

error_reporting(E_ALL & ~E_DEPRECATED);


function autoload($klass)
{
    static $builtins = array(
        'AuApplication' => 'core.php',
        'AuBehavior' => 'behavior.php',
        'AuBelongsTo' => 'behavior.php',
        'AuCache' => 'cache.php',
        'AuConfigure' => 'core.php',
        'AuConnection' => 'database.php',
        'AuConstructor' => 'core.php',
        'AuDatabase' => 'database.php',
        'AuFetchObject' => 'behavior.php',
        'AuFetchAll' => 'behavior.php',
        'AuHasMany' => 'behavior.php',
        'AuHasOne' => 'behavior.php',
        'AuLiteral' => 'database.php',
        'AuManyToMany' => 'behavior.php',
        'AuOrganization' => 'behavior.php',
        'AuProcedure' => 'core.php',
        'AuQuery' => 'database.php',
        'AuRequest' => 'request.php',
        'AuRowObject' => 'model.php',
        'AuRowSet' => 'model.php',
        'AuSchema' => 'model.php',
        'AuTemplate' => 'template.php',
    );
    if ( isset($builtins[$klass]) ) {
        require_once(LIBRARY_DIR . DS . $builtins[$klass]);
    } else { //自动加载models下的类
        $filenames = glob(APPLICATION_ROOT . DS . 'models' . DS . '*.php');
        foreach ($filenames as $filename) {
            require_once($filename);
            if (class_exists($klass, false)) {
                return true;
            }
        }
    }
    return true;
}

spl_autoload_register('autoload');


function app()
{
    static $app;
    if ( is_null($app) ) {
        $app = new AuApplication(CONFIG_FILENAME);
    }
    return $app;
}


/**
 * 将下划线分割的单词转为驼峰表示（首字母大写）
 * USAGE:
 *  php > echo camelize('hello_world');
 *  'HelloWorld'
 **/
function camelize($underscored_word)
{
    $humanize_word = ucwords(str_replace('_', ' ', $underscored_word));
    return str_replace(' ', '', $humanize_word);
}


/**
 * 从关联数组中取出键属于$keys的部分
 **/
function slice_assoc($arr, $keys) {
    $result = array();
    foreach ($keys as $k) {
        if ( isset($arr[$k]) ) {
            $result[$k] = $arr[$k];
        }
    }
    return $result;
}


/**
 * 导入文件，文件存放在APPLICATION_ROOT的相对路径
 * 可导入单文件、多文件、目录下的所有文件
 * USAGE:
 *  php > import('plugins/f3/f3.php');
 *  php > import('models.*');
 *  php > import( array('lib/database.php', 'lib/model.php') );
 **/
function import($import_path)
{
    if ( is_array($import_path) ) {
        foreach ($import_path as $path) {
            import($path);
        }
    }
    else if ( substr($import_path, -2) == '.*' ) {
        $import_dir = APPLICATION_ROOT . DS . substr($import_path, 0, -2);
        if (file_exists($import_dir) && is_dir($import_dir)) {
            $import_files = glob($import_dir . DS . '*.php');
            foreach ($import_files as $import_file) {
                require_once $import_file;
            }
        }
    }
    else {
        $import_file = APPLICATION_ROOT . DS . $import_path;
        if (file_exists($import_file) && is_file($import_file)) {
            require_once $import_file;
        }
    }
}


/**
 * 函数内对象缓存
 * USAGE:
 *  php > cached('app');  #获取app单例对象
 *  php > cached('model.User', 1);  #获取id=1的用户对象
 *  php > cached('model.User', 1，null, $proc);  #同上，但当id=1的用户不存在时，使用过程$proc创建一个
 *  php > cached('model.User', 1，$user);  #存储$user为id=1的用户对象
 **/
function cached($ns, $id=null, $inst=null, $proc=null)
{
    #TODO: backend
    static $objects = array(); //对象注册表
    if (! array_key_exists($ns, $objects)) {
        $objects[$ns] = array();
    }

    if (! is_null($inst)) { //存放对象
        $objects[$ns][$id] = & $inst;
    }
    else if ( array_key_exists($id, $objects[$ns]) ) { //获取对象
        $inst = & $objects[$ns][$id];
    }
    else { //不存在，尝试创建
        $inst = $proc instanceof AuProcedure ? $proc->emit() : $proc;
        if (! is_null($inst)) { //成功创建
            $objects[$ns][$id] = & $inst;
        }
    }
    return $inst;
}


function invoke_view($view_obj, $req) {
    //当$view不存在$action动作时，执行默认的index动作，并将$action作为动作的第一个参数
    if (! method_exists($view_obj, $req->action . 'Action')) {
        array_unshift($req->args, $req->action);
        $req->action = 'index';
    }
    //找出当前action对应哪些Filters
    $filter_objects = array();
    $filters = null;
    if (method_exists($view_obj, 'filters')) {
        $filters = $view_obj->filters($req->action);
    }
    $filters = empty($filters) ? array() : $filters;
    //按顺序执行Filters的before检查，未通过跳转到404错误页面
    foreach($filters as $filter) {
        $construct = new AuConstructor($filter . 'Filter', array(& $view_obj));
        $filter_obj = $construct->emit();
        if (method_exists($filter_obj, 'before') && ! $filter_obj->before(& $req)) {
            return $req->error(404);
        }
        array_push($filter_objects, $filter_obj);
    }
    //执行action动作，再按逆序执行Filters的after包装，修改返回的结果$result
    $result = call_user_func(array($view_obj, $req->action . 'Action'), & $req);
    while ($filter_obj = array_pop($filter_objects)) {
        if (method_exists($filter_obj, 'after')) {
            $filter_obj->after(& $result);
        }
    }
    return $result;
}
