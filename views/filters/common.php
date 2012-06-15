<?php
require_once(MODEL_DIR . DS . 'default.php');

class optionFilter
{
	protected $view = null;

	public function __construct($view) {
		$this->view = $view;
	}

	public function before($req) {
		$rs = $req->app->db()->query('SELECT * FROM t_users LIMIT 1');
		$this->view->user = cached('user', 0, new User($rs[0]));
		return true;
	}

	public function after($result) {
		$result['options'] = new Option();
		$t = new Template( $result['template_name'] );
		$t->render($result);
	}
}


class siderFilter
{
	protected $view = null;

	public function __construct($view) {
		$this->view = $view;
	}

	public function after($result) {
		$pages = cached('app')->db()->query("SELECT * FROM t_contents WHERE type='page'");
		$create_obj = function($row) {
			$obj = new Content();
			$obj->accept($row);
			return $obj;
		};
		$result['user'] = $this->view->user;
		$result['pages'] = array_map($create_obj, $pages);
	}
}
