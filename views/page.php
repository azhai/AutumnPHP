<?php
require_once(APPLICATION_ROOT . DS . 'views' . DS . 'filters' . DS . 'common.php');

class PageView
{
    public function filters($action) {
        return array('option', 'sider');
    }

    public function indexAction($req) { #首页
        $slug = $req->args[0];
        $query = $req->app->db()->factory('contents');
        $entry = $query->filter_by(array('type'=>'page', 'slug'=>$slug))->get();
        return array(
            'requrl' => $req->url,
            'template_name' => 'entry.php',
            'entry' => $entry,
            'comments' => $entry->comments,
            'tags' => array(),
        );
    }
}
