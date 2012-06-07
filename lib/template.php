<?php
/**
 * 模板类，带有布局Layout和板块Block
 */
class Template
{
    public static $template_dir = '';
    public $filename = '';
    public $layout_filename = '';
    protected $cxt = array();

    public function __construct($filename) {
        if (empty(self::$template_dir)) {
            self::$template_dir = APPLICATION_ROOT . DS . 'templates';
        }
        $this->filename = self::$template_dir . DS . $filename;
    }

    public function layout($layout_filename) {
        $this->layout_filename = self::$template_dir . DS . $layout_filename;
    }

    public function block($block_name, array $arg_keys=null) {
        $block_args = array();
        if (! empty($arg_keys)) {
            foreach ($arg_keys as $key) {
                $block_args[] = array_key_exists($key, $this->cxt) ? $this->cxt[$key] : null;
            }
        }
        ob_start();
        call_user_func_array($block_name, $block_args);
        return ob_end_flush(); //输出内容
    }

    public function _render(array $cxt=null) {
        if (! empty($cxt)) {
            $this->cxt = array_merge($this->cxt, $cxt);
        }
        extract($this->cxt, EXTR_SKIP | EXTR_REFS);
        ob_start();
        @include($this->filename);
        $layout_exists = file_exists($this->layout_filename);
        if ($layout_exists) {
            @include($this->layout_filename);
        }
        return ob_get_clean(); //返回内容
    }

    public function render(array $cxt=null, $encoding='utf-8') {
        $template_exists = file_exists($this->filename);
        if (! $template_exists) { //输出JSON
            if(! headers_sent()) {
                header('Content-Type: application/json; charset='.$encoding);
            }
            $cxt = empty($cxt) ? $this->cxt : array_merge($this->cxt, $cxt);
            echo json_encode($cxt);
        }
        else { //输出HTML
            if(! headers_sent()) {
                header('Content-Type: text/html; charset='.$encoding);
            }
            echo $this->_render($cxt);
        }
    }
}
