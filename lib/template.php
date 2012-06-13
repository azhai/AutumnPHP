<?php
defined('APPLICATION_ROOT') or die();


function _e($word) { //I18N翻译函数
	return $word;
}

function contain($filename) { //模板包含
	$filename = TEMPLATE_DIR . DS . $filename;
	include($filename);
}


/**
 * 模板类，带有布局Layout和板块Block
 */
class Template
{
    public $filename = '';
    public $tops = array();
    protected $context = array();

    public function __construct($filename) {
        $this->filename = TEMPLATE_DIR . DS . $filename;
    }

    public function extend($filename) { //模板继承
		$filename = TEMPLATE_DIR . DS . $filename;
		if (! in_array($filename, $this->tops) && file_exists($filename)) {
			$this->filename = $filename;
		}
    }

    public function block($block_name, array $arg_keys=null) { //生成板块
        $block_args = array();
		if (function_exists($block_name)) {
			if (empty($arg_keys)) {
				$func = new ReflectionFunction($block_name);
				$params = $func->getParameters();
				foreach ($params as $param) {
					$block_args[] = array_key_exists($param->name, $this->context) ? $this->context[$param->name] : null;
				}
			}
			else {
				foreach ($arg_keys as $key) {
					$block_args[] = array_key_exists($key, $this->context) ? $this->context[$key] : null;
				}
			}
			ob_start();
			call_user_func_array($block_name, $block_args);
			return ob_end_flush(); //输出BLOCK内容
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

	public static function json(array $context=null, $encoding='UTF-8') {
		if(! headers_sent()) {
			header('Content-Type: application/json; charset='.$encoding);
		}
		$context = empty($context) ? $this->context : array_merge($this->context, $context);
		echo json_encode($context); //输出JSON
    }
}
