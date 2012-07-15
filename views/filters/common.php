<?php
defined('APPLICATION_ROOT') or die();

class optionFilter
{
    protected $view = null;

    public function __construct($view) {
        $this->view = $view;
    }

    public function before($req) {
        $user = $req->app->db()->factory('users')->get(1);
        $this->view->user = cached('user', 0, $user);
        return true;
    }

    public function after($result) {
        $result['options'] = Options::instance();
        $t = new AuTemplate( $result['template_name'] );
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
        $db = app()->db();
        $result['user'] = $this->view->user;
        $result['pages'] = $db->factory('contents')->filter_by(array('type'=>'page')
                )->order_by('created')->all();
        $result['siders'] = array();
        $result['siders']['posts'] = $db->factory('contents')->filter_by(array('type'=>'post')
                )->order_by('created DESC')->page(1, 10);
        $result['siders']['comments'] = $db->factory('comments')->order_by('created DESC'
                )->page(1, 10);
        $result['siders']['categories'] = $db->factory('metas')->filter_by(array('type'=>'category')
                )->order_by('`order`')->all();
        $year_month = "DATE_FORMAT(FROM_UNIXTIME(created), '%Y-%m')";
        $result['siders']['archives'] = $db->factory('contents')->filter_by(array('type'=>'post')
                )->group_by($year_month)->order_by('created DESC')->page(1, 10,
                $year_month . " AS `year_month`, COUNT(*) AS `count`");
    }
}
