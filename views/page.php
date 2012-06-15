<?php
require_once(VIEW_DIR . DS . 'filters' . DS . 'common.php');

class PageView
{
	public function filters($action) {
		return array('option', 'sider');
	}

	public function indexAction($req) { #首页
		$slug = $req->args[0];
		$objs = $req->app->factory('Content')->all(
			"type='page' AND slug=:slug", array(':slug'=>$slug)
		);
		$entry = empty($objs) ? new Content() : $objs[0];
		return array(
			'requrl' => $req->url,
			'template_name' => 'entry.php',
			'entry' => $entry,
			'comments' => $entry->comments,
			'tags' => array(),
		);
	}
}
