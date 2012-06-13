<?php
require_once(VIEW_DIR . DS . 'filters' . DS . 'common.php');

class IndexView
{
	public function filters($action) {
		return array('option');
	}

	public function indexAction($req) { #首页
		$db = an('db', 'default');
		$pages = $db::sql("SELECT * FROM t_contents WHERE type='page'");
		$posts = $db::sql("SELECT * FROM t_contents WHERE type='post'");
		$create_obj = function($row) {
			$obj = new Content();
			$obj->accept($row);
			return $obj;
		};
		return array(
			'template_name' => 'index.php',
			'user' => $this->user,
			'pages' => array_map($create_obj, $pages),
			'posts' => array_map($create_obj, $posts),
			'paginate' => '',
		);
	}
}
