<?php
require_once(APPLICATION_ROOT . DS . 'views' . DS . 'filters' . DS . 'common.php');

class IndexView
{
    public function filters($action) {
        return array('safe', 'sider');
    }

    public function indexAction($req) { #首页
        $query = $req->app->db()->factory('contents');
        $entries = $query->filter_by(array('type'=>'post'))->all();
        return array(
            'requrl' => $req->url,
            'template_name' => 'index.php',
            'entries' => $entries,
            'paginate' => '',
        );
    }
}
