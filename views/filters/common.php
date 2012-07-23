<?php
defined('APPLICATION_ROOT') or die();


class SafeFilter
{
    protected $view = null;
    protected $template = null;

    public function __construct($view) {
        $this->view = $view;
        $this->template = new AuTemplate();
    }

    public function before($req) {
        $user = $this->app->db()->factory('users')->get(1);
        $user->theme = Options::instance()->theme;
        $this->view->user = $user;
        if ( isset($_REQUEST['theme']) ) {
            $this->template->theme = $_REQUEST['theme'];
        }
        else if ( isset($this->view->user->theme) ) {
            $this->template->theme = $this->view->user->theme;
        }
        else if ( isset($this->app->theme) ) {
            $this->template->theme = $this->app->theme;
        }
        return true;
    }

    public function after($result) {
        $result['options'] = Options::instance();
        $this->template->extend( $result['template_name'] );
        $this->template->render($result);
    }
}


class SiderFilter
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
