<?php
defined('IN_CAKEPHP') or define('IN_CAKEPHP', true);

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


class CakeApplication extends AuApplication
{
    public function __construct($configs=null)
    {
        if ( empty($configs) ) { //加载配置文件
            $config_cache = new AuCacheFile(APPLICATION_ROOT, array(CONFIG_NAME), true, 0755);
            $configs = $config_cache->get(CONFIG_NAME, '', array());
        }
        $configs = get_cakephp_dbs($configs);
        parent::__construct($configs);
    }
}
