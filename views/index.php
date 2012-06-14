<?php
require_once(VIEW_DIR . DS . 'filters' . DS . 'common.php');

class IndexView
{
	public function filters($action) {
		return array('option', 'sider');
	}

	public function indexAction($req) { #首页
		$db = an('db', 'default');
		$posts = $db::sql("SELECT * FROM t_contents WHERE type='post'");
		$create_obj = function($row) {
			$obj = new Content();
			$obj->accept($row);
			return $obj;
		};
		return array(
			'requrl' => $req->url,
			'template_name' => 'index.php',
			'entries' => array_map($create_obj, $posts),
			'paginate' => '',
		);
	}
}
