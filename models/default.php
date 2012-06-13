<?php

class User extends Model
{
	protected $_table_ = 't_users';
    public static $pkeys = array('uid');
}

class Content extends Model
{
	protected $_table_ = 't_contents';
    public static $pkeys = array('uid');

	public function h_categories() {
		return '';
	}

	public function h_num_comment() {
		return '';
	}

	public function h_content() {
		return '';
	}
}

class Option extends Model
{
	protected $_table_ = 't_options';
    public static $pkeys = array('uid');

	public function __get($prop) {
		$db = an('db', 'default');
		$rs = $db::sql(
			"SELECT * FROM t_options WHERE user=0 AND name=:name",
			array(':name' => $prop)
		);
		if ($prop == 'siteUrl') {
			return str_replace('99', '90', $rs[0]['value']);
		}
		return empty($rs) ? '' : $rs[0]['value'];
	}
}
