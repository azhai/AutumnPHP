<?php
defined('APPLICATION_ROOT') or die();
defined('VIEW_DIR') or define('VIEW_DIR', APPLICATION_ROOT . DS . 'views');


class AuRequest
{
    public $app = null;
    public $url = '/';
    public $file = '/index.php';
    public $view = '';
    public $action = 'index';
    public $method = 'GET';

    public function __construct($app) {
        $this->app = $app;
        $this->url = self::get_current_url();
        $this->method = strtoupper($_SERVER['REQUEST_METHOD']);
        $this->parse();
    }

    public function parse() {
        //先检查$this->app->routers中缓存的正则URL对应的结果
        foreach ($this->app->routers as $pattern => $router) {
            if ( preg_match($pattern, $this->url, $matches) ) {
                foreach ($router as $prop => $value) {
                    $this->$prop = $value;
                }
                array_shift($matches);
                $this->args = array_merge($matches, $this->args);
                return true;
             }
        }

        $limit = $this->app->max_router_layer + 1;
        $pics = preg_split('/\//', $this->url, $limit + 1,
                           PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);
        if (count($pics) > $limit) {
            array_pop($pics);
        }

        $limit = count($pics) - 1;
        while ($limit >= 0) {
            $pos = $pics[$limit][1] + strlen($pics[$limit][0]);
            $dir = substr($this->url, 0, $pos);
            if (file_exists(VIEW_DIR . $dir . DS)) { //目录存在
                if (file_exists(VIEW_DIR . $dir . $this->file)) {
                    $this->file = $dir . $this->file;
                    $this->args = explode('/', substr($this->url, $pos + 1));
                    if (! empty($this->args) && $this->args != array('')) {
                        $this->action = array_shift($this->args);
                    }
                    return true;
                }
            }
            else if (file_exists(VIEW_DIR . $dir . '.php')) { //文件存在
                $this->file = $dir . '.php';
                $this->args = explode('/', substr($this->url, $pos + 1));
                if (! empty($this->args) && $this->args != array('')) {
                    $this->action = array_shift($this->args);
                }
                return true;
            }
            $limit --;
        }
        if (file_exists(VIEW_DIR . $this->file)) {
            return true; //默认文件/index.php存在
        }
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
        $t = new AuTemplate( sprintf('errors/%d.php', $code) );
        $t->render();
        exit;
    }
}



class AuCurl
{
    public $host_url = '';
    public $agent = 'Mozilla/5.0 (Windows NT 5.1; rv:13.0) Gecko/20100101 Firefox/13.0.1';
    public $proxy = array();
    public $verbose = false;

    public function __construct($host_url, $agent=null, array $proxy=null, $verbose=false) {
        $this->host_url = $host_url;
        if (! empty($agent)) {
            $this->agent = $agent;
        }
        if (! empty($proxy)) { #$proxy = array("www.test.com:8080", "SOCKS5", "user:pass");
            $this->proxy = $proxy;
        }
        $this->verbose = $verbose;
    }

    public function init() {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_AUTOREFERER => 1,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 60,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_HTTPPROXYTUNNEL => 1,
            CURLOPT_VERBOSE => $this->verbose,
            CURLOPT_USERAGENT => $this->agent
        ));
        if (! empty($this->proxy)) {
            @list($host_port, $sock_type, $user_pass) = $this->proxy;
            curl_setopt_array($ch, array(
                CURLOPT_PROXY => $host_port,
                CURLOPT_PROXYTYPE => $sock_type,
                CURLOPT_PROXYUSERPWD => $user_pass
            ));
        }
        return $ch;
    }

    public function get($url, $data='') {
        $ch = $this->init();
        $data = is_array($data) ? http_build_query($data) : ltrim($data, '?');
        curl_setopt($ch, CURLOPT_URL, $this->host_url . $url . '?' . $data);
        $result = curl_exec($ch);
        curl_close($ch);
        #$info = curl_getinfo($ch);
        #$info['http_code'] == 200;
        return $result;
    }

    public function post($url, $data=array()) {
        $ch = $this->init();
        curl_setopt($ch, CURLOPT_URL, $this->host_url . $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);
        #$info = curl_getinfo($ch);
        #$info['http_code'] == 200;
        return $result;
    }

    public function __call($name, $args) {
        $method = 'get';
        if (substr($name, 0, 5) == 'post_') {
            $method = 'post';
            $name = substr($name, 5);
        }
        $url = '/' . str_replace('_', '/', $name);
        $data = empty($args) ? array() : $args[0];
        return call_user_func(
            array($this, $method), $url, $data
        );
    }
}
