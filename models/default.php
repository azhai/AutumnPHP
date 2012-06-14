<?php

class User extends Model
{
	protected $_table_ = 't_users';
    public static $pkeys = array('uid');
}


class Meta extends Model
{
	protected $_table_ = 't_metas';
    public static $pkeys = array('uid');
}


class Comment extends Model
{
	protected $_table_ = 't_comments';
    public static $pkeys = array('uid');
}


class Content extends Model
{
	protected $_table_ = 't_contents';
    public static $pkeys = array('uid');

	public function get_url() {
		return sprintf('/%s/%s', $this->type, $this->slug);
	}

	public function get_author() {
		$db = $this->get_db();
		$rows = $db::sql("SELECT * FROM t_users WHERE uid=:uid",
						 array(':uid'=>$this->authorId));
		return empty($rows) ? '' : $rows[0]['screenName'];
	}

	public function get_tags() {
		if ($this->type == 'page') {
			return array();
		}
		else {
			$objs = array();
			$db = $this->get_db();
			$rows = $db::sql("SELECT * FROM t_metas WHERE mid IN
							 (SELECT mid FROM t_relationships WHERE type='tag' AND cid=:cid)",
							 array(':cid'=>$this->cid));
			foreach ($rows as $row) {
				$obj = new Meta();
				$obj->accept($row);
				$objs[] = $obj;
			}
			return $objs;
		}
	}

	public function get_categories() {
		$objs = array();
		$db = $this->get_db();
		$rows = $db::sql("SELECT * FROM t_metas WHERE mid IN
						 (SELECT mid FROM t_relationships WHERE type='category' AND cid=:cid)",
						 array(':cid'=>$this->cid));
		foreach ($rows as $row) {
			$obj = new Meta();
			$obj->accept($row);
			$objs[] = $obj;
		}
		return $objs;
	}

	public function get_comments() {
		$objs = array();
		$db = $this->get_db();
		$rows = $db::sql("SELECT * FROM t_comments WHERE cid=:cid",
						 array(':cid'=>$this->cid));
		foreach ($rows as $row) {
			$obj = new Comment();
			$obj->accept($row);
			$objs[] = $obj;
		}
		return $objs;
	}

	public function h_categories($sep=',') {
		$categories = $this->categories;
		$cates = array();
		foreach ($categories as $cate) {
			$cates[] = sprintf('<a href="/category/%s">%s</a>', $cate->slug, $cate->name);
		}
		return implode($sep, $cates);
	}

	public function h_num_comment($no='No Comments', $one='1 Comment', $many='%d Comments') {
		$num = $this->commentsNum > 1 ? 2 : $this->commentsNum;
		return _t(sprintf(func_get_arg($num), $num));
	}

	public function h_content($more='More', $max=100) {
		if (strlen($this->text) > $max) {
			$more_link = sprintf('<a href="%s">%s</a>', $this->url, _t($more));
			return substr($this->text, 0, $max) . ' &nbsp;&nbsp; ' . $more_link;
		}
		else {
			return $this->text;
		}
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
			return str_replace('81', '80', $rs[0]['value']);
		}
		return empty($rs) ? '' : $rs[0]['value'];
	}
}
