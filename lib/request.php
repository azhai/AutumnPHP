<?php

function url_for() {
    return '/' . implode('/', func_get_args() );
}


class Request
{
	public $app = null;
	public $url = '';
	public $method = 'GET';

	public function __construct($app) {
		$this->app = $app;
		$this->url = self::get_current_url();
		$this->method = strtoupper($_SERVER['REQUEST_METHOD']);
	}

    public static function get_current_url() {
		$request_url = trim($_SERVER['REQUEST_URI'], ' /');
        if ('/index.php?'==substr($request_url, 0, 11)) {
            return '/' . trim(substr($request_url, 11), ' /');
        }
		else {
			return '/' . $request_url;
		}
    }

    public function redirect() {
		if(! headers_sent()) {
			$params = func_get_args();
			$next_url = call_user_func_array('url_for', $params);
			header('Location: ' . $next_url);
			exit;
		}
    }

    public function error($code=404) {
    }
}


class User
{
}


class Form
{
    public function begin() {
    }

    public function end() {
    }
}
