<?php
require_once(MODEL_DIR . DS . 'default.php');

class optionFilter
{
	protected $view = null;

	public function __construct($view) {
		$this->view = $view;
	}

	public function before($req) {
		$user = $req->app->factory('User')->get(1);
		$this->view->user = cached('user', 0, $user);
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
		$app = cached('app');
		$result['user'] = $this->view->user;
		$result['pages'] = $app->factory('Content')->filter_by(
					array('type'=>'page'))->extra('ORDER BY created')->all();
		$result['siders'] = array();
		$result['siders']['posts'] = $app->factory('Content')->filter_by(
					array('type'=>'post'))->extra('ORDER BY created DESC LIMIT 10')->all();
		$result['siders']['comments'] = $app->factory('Comment')->extra(
					'ORDER BY created DESC LIMIT 10')->all();
		$result['siders']['categories'] = $app->factory('Meta')->filter_by(
					array('type'=>'category'))->extra('ORDER BY `order`')->all();
		$result['siders']['archives'] = $app->factory('Content')->filter_by(array('type'=>'post'))->extra(
					"GROUP BY DATE_FORMAT(FROM_UNIXTIME(created), '%%Y-%%m') ORDER BY created DESC LIMIT 10")->select(
					"DATE_FORMAT(FROM_UNIXTIME(created), '%%Y-%%m') AS `year_month`, COUNT(*) AS `count`")->all();
	}
}
