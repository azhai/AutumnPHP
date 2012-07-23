<?php
defined('APPLICATION_ROOT') or die();


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
