<?php
require_once(VIEW_DIR . DS . 'filters' . DS . 'common.php');

class IndexView
{
	public function filters($action) {
		return array('option', 'sider');
	}

	public function indexAction($req) { #首页
		$entries = $req->app->factory('Content')->all("type='post'");
		return array(
			'requrl' => $req->url,
			'template_name' => 'index.php',
			'entries' => $entries,
			'paginate' => '',
		);
	}
}
