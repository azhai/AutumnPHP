<?php
defined('APPLICATION_ROOT') or die();


function _t($word) { //I18N翻译函数
    return $word;
}


/**
 * 模板类，带有布局Layout和板块Block
 */
class AuTemplate
{
    public $theme = 'default';
    public $filename = '';
    public $tops = array();
    protected $context = array();
    protected $scripts = array(
        'css_file' => array(),  'css_inline' => array(),
        'js_file' => array(),  'js_inline' => array(),
    );

    public function __construct($filename='') {
        if ( ! empty($filename) ) {
            $this->extend($filename);
        }
    }

    public function extend($filename) { //模板继承
        $theme_dir = APPLICATION_ROOT . DS . 'themes';
        if ( file_exists($theme_dir . DS . $this->theme . DS . $filename) ) {
            $filename = $theme_dir . DS . $this->theme . DS . $filename;
        }
        else {
            $filename = $theme_dir . DS . 'default' . DS . $filename;
        }
        if (! in_array($filename, $this->tops)) {
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
        //相同css文件不重复载入
        if ('inline' == $type || ! in_array($css, $this->scripts['css_file'])) {
            $this->scripts[ 'css_' . $type ] []= $css;
        }
    }

    public function js($js, $type='inline') {
        //同上
        if ('inline' == $type || ! in_array($js, $this->scripts['js_file'])) {
            $this->scripts[ 'js_' . $type ] []= $js;
        }
    }

    public function render_scripts() {
        ob_start();
        foreach ($this->scripts['css_file'] as $css_file) {
            printf('<link type="text/css" rel="stylesheet" href="%s" />', $css_file);
            echo "\n";
        }
        foreach ($this->scripts['js_file'] as $js_file) {
            printf('<script type="text/javascript" src="%s"></script>', $js_file);
            echo "\n";
        }
        if ( count($this->scripts['css_inline']) > 0 ) {
            echo '<style rel="stylesheet">';
            foreach ($this->scripts['css_inline'] as $css_inline) {
                echo $css_inline;
                echo "\n";
            }
            echo '</style>';
        }
        if ( count($this->scripts['js_inline']) > 0 ) {
            echo '<script type="text/javascript">';
            foreach ($this->scripts['js_inline'] as $js_inline) {
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
