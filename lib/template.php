<?php
defined('APPLICATION_ROOT') or die();
defined('TEMPLATE_DIR') or define('TEMPLATE_DIR', APPLICATION_ROOT . DS . 'templates');


function _t($word) { //I18N翻译函数
    return $word;
}


/**
 * 模板类，带有布局Layout和板块Block
 */
class AuTemplate
{
    public $filename = '';
    public $tops = array();
    protected $context = array();
    protected $headers = array(
        'css_file' => array(),  'css_inline' => array(),
        'js_file' => array(),  'js_inline' => array(),
    );

    public function __construct($filename) {
        $this->filename = TEMPLATE_DIR . DS . $filename;
    }

    public function extend($filename) { //模板继承
        $filename = TEMPLATE_DIR . DS . $filename;
        if (! in_array($filename, $this->tops) && file_exists($filename)) {
            $this->filename = $filename;
        }
    }

    public function widget() {
        $args = func_get_args();
        $widget_name = array_shift($args);
        if ( function_exists($widget_name) ) {
            ob_start();
            call_user_func_array($widget_name, $args);
            return ob_end_flush(); //输出HTML内容
        }
    }

    public function render(array $context=null, $encoding='UTF-8') {
        if(! headers_sent()) {
            header('Content-Type: text/html; charset='.$encoding);
            if (! empty($context)) {
                $this->context = array_merge($this->context, $context);
            }
            extract($this->context, EXTR_SKIP | EXTR_REFS);
            ob_start();
            while ($this->filename) {
                array_push($this->tops, $this->filename);
                $filename = $this->filename;
                $this->filename = '';
                @include($filename);
            }
            return ob_end_flush(); //输出HTML内容
        }
    }

    public function css($css, $type='file') {
        $this->headers[ 'css_' . $type ] []= $css;
    }

    public function js($js, $type='inline') {
        $this->headers[ 'js_' . $type ] []= $js;
    }

    public function render_headers() {
        ob_start();
        foreach ($this->headers['css_file'] as $css_file) {
            printf('<link type="text/css" rel="stylesheet" href="%s" />', $css_file);
            echo "\n";
        }
        foreach ($this->headers['js_file'] as $js_file) {
            printf('<script type="text/javascript" src="%s"></script>', $js_file);
            echo "\n";
        }
        if ( count($this->headers['css_inline']) > 0 ) {
            echo '<style rel="stylesheet">';
            foreach ($this->headers['css_inline'] as $css_inline) {
                echo $css_inline;
                echo "\n";
            }
            echo '</style>';
        }
        if ( count($this->headers['js_inline']) > 0 ) {
            echo '<script type="text/javascript">';
            foreach ($this->headers['js_inline'] as $js_inline) {
                echo $js_inline;
                echo "\n";
            }
            echo '</script>';
        }
        return ob_end_flush(); //输出HTML内容
    }

    public static function json(array $context=null, $encoding='UTF-8') {
        if(! headers_sent()) {
            header('Content-Type: application/json; charset='.$encoding);
        }
        $context = empty($context) ? $this->context : array_merge($this->context, $context);
        echo json_encode($context); //输出JSON
    }
}
