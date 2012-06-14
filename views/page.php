<?php
require_once(VIEW_DIR . DS . 'filters' . DS . 'common.php');

class PageView
{
	public function filters($action) {
		return array('option', 'sider');
	}

	public function indexAction($req) { #首页
		$slug = $req->args[0];
		$db = an('db', 'default');
		$rows = $db::sql("SELECT * FROM t_contents WHERE type='page' AND slug=:slug",
						 array(':slug'=>$slug));
		$obj = new Content();
		$obj->accept($rows[0]);
		return array(
			'requrl' => $req->url,
			'template_name' => 'entry.php',
			'entry' => $obj,
			'comments' => $obj->comments,
			'tags' => array(),
		);
	}
}
