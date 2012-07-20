<?php
error_reporting(E_ALL & ~E_DEPRECATED);
defined('IN_CAKEPHP') or define('IN_CAKEPHP', true);
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('APPLICATION_ROOT') or define('APPLICATION_ROOT', dirname( dirname(__FILE__) ));
defined('RUNTIME_DIR') or define('RUNTIME_DIR', APPLICATION_ROOT . DS . 'runtime');
if ( IN_CAKEPHP === false ) {
    defined('CONFIG_NAME') or define('CONFIG_NAME', 'config');
    defined('MODEL_DIR_NAME') or define('MODEL_DIR_NAME', 'models');
}
else {
    defined('CONFIG_NAME') or define('CONFIG_NAME', 'libs' . DS . 'config');
    defined('MODEL_DIR_NAME') or define('MODEL_DIR_NAME', 'libs' . DS . 'models');

    function get_cakephp_dbs($configs)
    {
        import('config/database.php');
        $dbs = get_class_vars('DATABASE_CONFIG');
        if ( ! isset( $configs['db'] ) ) {
            $configs['db'] = array();
        }
        foreach ($dbs as $key => $db) {
            if ( isset($db['driver']) && strtolower($db['driver']) == 'mysql' ) {
                $dsn = sprintf('mysql:host=%s;dbname=%s', $db['host'], $db['database']);
                $configs['db'][$key] = array($dsn, $db['login'], $db['password']);
            }
        }
        return $configs;
    }
}


function app()
{
    static $app;
    if ( is_null($app) ) {
        $app = new AuApplication(CONFIG_NAME);
    }
    return $app;
}


function autoload($klass)
{
    static $_builtins_ = array(
        'AuApplication' => 'core.php',
        'AuBehavior' => 'behavior.php',
        'AuBelongsTo' => 'behavior.php',
        'AuCache' => 'cache.php',
        'AuCacheFile' => 'cache.php',
        'AuConfigure' => 'core.php',
        'AuConstructor' => 'core.php',
        'AuCurl' => 'request.php',
        'AuDatabase' => 'database.php',
        'AuFactory' => 'database.php',
        'AuHasMany' => 'behavior.php',
        'AuHasOne' => 'behavior.php',
        'AuLazyRow' => 'model.php',
        'AuLazySet' => 'model.php',
        'AuLiteral' => 'database.php',
        'AuManyToMany' => 'behavior.php',
        'AuProcedure' => 'core.php',
        'AuQuery' => 'database.php',
        'AuRequest' => 'request.php',
        'AuSchema' => 'model.php',
        'AuTemplate' => 'template.php',
    );
    if ( isset($_builtins_[$klass]) ) {
        $lib_dir_name = IN_CAKEPHP ? 'libs' : 'lib';
        require_once(APPLICATION_ROOT . DS . $lib_dir_name . DS . $_builtins_[$klass]);
    } else { //自动加载models下的类
        $filenames = glob(APPLICATION_ROOT . DS . MODEL_DIR_NAME . DS . '*.php');
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


/**
 * 当前PHP版本低于$ver
 * @assert ('5.0.0') === false
 **/
function php_ver_lt($ver)
{
    return strnatcmp(phpversion() , $ver) < 0;
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
        return array_map('import', $import_path);
    }
    else if ( substr($import_path, -2) == '.*' ) {
        $import_dir = APPLICATION_ROOT . DS . substr($import_path, 0, -2);
        if (file_exists($import_dir) && is_dir($import_dir)) {
            $import_files = glob($import_dir . DS . '*.php');
            $result = array();
            foreach ($import_files as $import_file) {
                require_once $import_file; //不需要再检查文件是否存在
                $result []= $import_file;
            }
            return $result;
        }
    }
    else {
        $import_file = APPLICATION_ROOT . DS . $import_path;
        if (file_exists($import_file) && is_file($import_file)) {
            require_once $import_file;
            return $import_file;
        }
    }
}


/**
 * 将下划线分割的单词转为驼峰表示（首字母大写）
 * @assert ('hello_world') == 'HelloWorld'
 **/
function camelize($underscored_word)
{
    $humanize_word = ucwords(str_replace('_', ' ', $underscored_word));
    return str_replace(' ', '', $humanize_word);
}


/**
 * 从关联数组中取出键属于$keys的部分
 * @assert (array('a'=>1, 'b'=>2, 'c'=>3), array('c', 'a')) == array('c'=>3, 'a'=>1)
 **/
function slice_within($arr, $keys) {
    $result = array();
    foreach ($keys as $k) {
        if ( isset($arr[$k]) ) {
            $result[$k] = $arr[$k];
        }
    }
    return $result;
}


/**
 * 从关联数组中取出键不属于$keys的部分
 * @assert (array('a'=>1, 'b'=>2, 'c'=>3), array('c', 'a')) == array('b'=>2)
 **/
function slice_without($arr, $keys) {
    $result = array();
    foreach ($arr as $k => $v) {
        if ( ! in_array($k, $keys, true) ) {
            $result[$k] = $v;
        }
    }
    return $result;
}
