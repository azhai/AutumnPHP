<?php
/**
 * 模板类，带有布局Layout和板块Block
 */
class Template
{
    public static $template_dir = '';
    public $filename = '';
    public $tops = array();
    protected $context = array();

    public function __construct($filename) {
        if (empty(self::$template_dir)) {
            self::$template_dir = APPLICATION_ROOT . DS . 'templates';
        }
        $this->filename = self::$template_dir . DS . $filename;
    }

    public function extend($filename) { //模板继承
		$filename = self::$template_dir . DS . $filename;
		if (! in_array($filename, $this->tops) && file_exists($filename)) {
			$this->filename = $filename;
		}
    }

    public function block($block_name, array $arg_keys=null) { //生成板块
        $block_args = array();
        if (! empty($arg_keys)) {
            foreach ($arg_keys as $key) {
                $block_args[] = array_key_exists($key, $this->context) ? $this->context[$key] : null;
            }
        }
		if (function_exists($block_name)) {
			ob_start();
			call_user_func_array($block_name, $block_args);
			return ob_end_flush(); //输出内容
		}
    }

    public function _render(array $context=null) {
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
        return ob_get_clean(); //返回内容
    }

    public function render(array $context=null, $encoding='utf-8') {
        $template_exists = $this->filename && file_exists($this->filename);
        if (! $template_exists) { //输出JSON
            if(! headers_sent()) {
                header('Content-Type: application/json; charset='.$encoding);
            }
            $context = empty($context) ? $this->context : array_merge($this->context, $context);
            echo json_encode($context);
        }
        else { //输出HTML
            if(! headers_sent()) {
                header('Content-Type: text/html; charset='.$encoding);
            }
            echo $this->_render($context);
        }
    }
}
