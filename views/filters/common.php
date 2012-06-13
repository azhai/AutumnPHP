<?php
require_once(MODEL_DIR . DS . 'default.php');

class optionFilter
{
	protected $view = null;

	public function __construct($view) {
		$this->view = $view;
	}

	public function before($req) {
		$db = an('db', 'default', 'load_plugin', array(
			'f3/db.php',
			array('DB', '__construct'),
			$req->app->configs->databases['default'],
			array('f3/base.php')
		)); //连接数据库
		$rs = $db::sql('SELECT * FROM t_users LIMIT 1');
		$this->view->user = an('user', 0, new User($rs[0]));
		return true;
	}

	public function after($result) {
		$result['options'] = new Option();
		$t = new Template( $result['template_name'] );
		$t->render($result);
	}
}
