<?php
defined('APPLICATION_ROOT') or define('APPLICATION_ROOT', dirname(__FILE__));
require_once(APPLICATION_ROOT . '/lib/common.php');
app()->run();
echo "-----------------------------------------------------------------------<br />\n";
app()->db()->dump_all();
?>
