<?php
require_once(APPLICATION_ROOT . DS . 'views' . DS . 'filters' . DS . 'common.php');


class PostView
{
    public function filters($action) {
        return array('safe', 'sider');
    }

    public function indexAction($req) { #首页
        $slug = $req->args[0];
        $query = $this->app->db()->factory('contents');
        $entry = $query->filter_by(array('type'=>'post', 'slug'=>$slug))->get();
        return array(
            'requrl' => $req->url,
            'template_name' => 'entry.php',
            'entry' => $entry,
            'comments' => $entry->comments,
            'tags' => $entry->tags,
        );
    }
}
