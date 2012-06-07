<?php

class Model
{
	protected $_state_ = '';     #NEW:新增 DIRTY:与数据库不一致 DELETED:已删除
	protected $_schema_ = 'default';  #数据库连接
	protected $_table_ = '';  #数据表

    public $id = 0;
    public $title = '';

    public function __construct($row=array()) {
        foreach ($row as $name=>$value) {
            $this->$name = $value;
        }
        $this->_state_ = 'NEW';
    }

    public function save() {
        if ($this->_state_ == 'NEW') { //新增
        } elseif ($this->_state_ == 'DIRTY') { //修改
        }
		$this->_state_ = '';
    }

    public function delete() {
        if ($this->_state_ != 'DELETED') { //删除
        }
        $this->_state_ == 'DELETED';
    }
}
