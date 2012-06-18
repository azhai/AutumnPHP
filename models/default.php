<?php

class User extends Model
{
	public static $table = 'users';
    public static $pkeys = array('uid');
}


class Meta extends Model
{
	public static $table = 'metas';
    public static $pkeys = array('mid');

    public function relations() {
		return array(
			'posts' => array('type'=>'many_many', 'model'=>'Content',
							'middle'=>'t_relationships', 'lkey'=>'mid', 'rkey'=>'cid'),
		);
	}
}


class Comment extends Model
{
	public static $table = 'comments';
    public static $pkeys = array('coid');
}


class Content extends Model
{
	public static $table = 'contents';
    public static $pkeys = array('cid');

    public function relations() {
		return array(
			'author' => array('type'=>'belongs_to', 'model'=>'User',
							'fkey'=>'authorId'),
			'comments' => array('type'=>'has_many', 'model'=>'Comment',
							'fkey'=>'cid', 'field'=>'cid'),
			'categories' => array('type'=>'many_many', 'model'=>'Meta',
							'middle'=>'t_relationships', 'lkey'=>'cid', 'rkey'=>'mid',
							'extra'=>array("type='category'")),
			'tags' => array('type'=>'many_many', 'model'=>'Meta',
							'middle'=>'t_relationships', 'lkey'=>'cid', 'rkey'=>'mid',
							'extra'=>array("type='tag'")),
		);
	}

	public function get_url() {
		return sprintf('/%s/%s', $this->type, $this->slug);
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
	public static $table = 'options';
    public static $pkeys = array('name', 'user');

	public function __construct($user=0) {
		$factory = cached('app')->factory( get_class($this) );
		$table = $factory->db->escape_table(self::$table);
        $sql = sprintf("SELECT * FROM %s WHERE user=?", $table);
		$rows = $factory->db->query($sql, array($user));
		foreach ($rows as $row) {
			$this->_data_[ $row['name'] ] = $row['value'];
		}
    }
}
