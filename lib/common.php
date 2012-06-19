<?php
defined('APPLICATION_ROOT') or die();

/**
 * 将下划线分割的单词转为驼峰表示（首字母大写）
 * USAGE:
 *  php > echo camelize('hello_world');
 *  'HelloWorld'
 **/
function camelize($underscored_word) {
    $humanize_word = ucwords(str_replace('_', ' ', $underscored_word));
    return str_replace(' ', '', $humanize_word);
}

/**
 * 导入文件，文件存放在APPLICATION_ROOT的相对路径
 * 可导入单文件、多文件、目录下的所有文件
 * USAGE:
 *  php > import('plugins/f3/f3.php');
 *  php > import('models.*');
 *  php > import( array('lib/database.php', 'lib/model.php') );
 **/
function import($import_path) {
	if ( is_array($import_path) ) {
		foreach ($import_path as $path) {
			import($path);
		}
	}
	else if ( substr($import_path, -2) == '.*' ) {
		$import_dir = APPLICATION_ROOT . DS . substr($import_path, 0, -2);
		if (file_exists($import_dir) && is_dir($import_dir)) {
			$import_files = glob($import_dir . DS . '*.php');
			foreach ($import_files as $import_file) {
				require_once $import_file;
			}
		}
	}
	else {
		$import_file = APPLICATION_ROOT . DS . $import_path;
		if (file_exists($import_file) && is_file($import_file)) {
			require_once $import_file;
		}
	}
}

/**
 * 函数内对象缓存
 * USAGE:
 *  php > cached('app');  #获取app单例对象
 *  php > cached('model.User', 1);  #获取id=1的用户对象
 *  php > cached('model.User', 1，null, $proc);  #同上，但当id=1的用户不存在时，使用过程$proc创建一个
 *  php > cached('model.User', 1，$user);  #存储$user为id=1的用户对象
 **/
function cached($ns, $id=0, $inst=null, $proc=null) {
	#TODO: backend
    static $objects = array(); //对象注册表
	if (! array_key_exists($ns, $objects)) {
        $objects[$ns] = array();
    }

	if (! is_null($inst)) { //存放对象
        $objects[$ns][$id] = & $inst;
	}
	else if ( array_key_exists($id, $objects[$ns]) ) { //获取对象
		$inst = & $objects[$ns][$id];
	}
	else { //不存在，尝试创建
		$inst = $proc instanceof IProcedure ? $proc->emit() : $proc;
		if (! is_null($inst)) { //成功创建
			$objects[$ns][$id] = & $inst;
		}
	}
    return $inst;
}


class ReadOnly
{
	protected $_data_ = array();  		#数据

	public function __construct(array $data=null) {
        if (! empty($data)) {
            $this->_data_ = $data;
        }
    }

	public function __isset($prop) {
		return array_key_exists($prop, $this->_data_);
	}

	public function __get($prop) {
		if (array_key_exists($prop, $this->_data_)) {
			return $this->_data_[$prop];
		}
	}
}


interface IProcedure
{
	public function set_subject($subject);
	public function set_args($args);
	public function emit();
}


/**
 * 生成一个值或对象的过程描述
 * 当调用emit()时执行生成工作，实现了Command模式
 **/
class Procedure implements IProcedure
{
    public $subject = '';
    public $args = array();
    public $imports = array();

    public function __construct($subject, $args=array(), $imports=array()) {
        $this->set_subject($subject);
        $this->set_args($args);
        $this->imports = $imports;
    }

	public function set_subject($subject) {
		$this->subject = $subject;
	}

	public function set_args($args) {
		$this->args = $args;
	}

    public function emit() {
		import($this->imports);
        return call_user_func_array($this->subject, $this->args);
    }
}


/**
 * 构造对象的过程描述
 **/
class Constructor extends Procedure
{
    public function emit() {
		$subject = $this->subject;
		if (! class_exists($klass, false)) {
			import($this->imports);
		}
		if ( empty($this->args) ) {
			return new $subject();
		}
		else {
			$class = new ReflectionClass($subject);
			return $class->newInstanceArgs($this->args);
		}
    }
}
