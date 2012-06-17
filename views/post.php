<?php
require_once(VIEW_DIR . DS . 'filters' . DS . 'common.php');

class PostView
{
	public function filters($action) {
		return array('option', 'sider');
	}

	public function indexAction($req) { #首页
		$slug = $req->args[0];
		$entry = $req->app->factory('Content')->filter_by(array('type'=>'post', 'slug'=>$slug))->get();
		return array(
			'requrl' => $req->url,
			'template_name' => 'entry.php',
			'entry' => $entry,
			'comments' => $entry->comments,
			'tags' => $entry->tags,
		);
	}
}
