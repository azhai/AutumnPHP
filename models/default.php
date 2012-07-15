<?php
defined('APPLICATION_ROOT') or die();


class Users extends AuRowObject
{
    protected $_behaviors_ = array(
        'blogs' => array('AuHasMany', 'contents', 'authorId',
                            array('filter'=>"type='post'")),
        'pages' => array('AuHasMany', 'contents', 'authorId',
                            array('filter'=>"type='page'")),
    );

    public static function create($row=array(), $schema=null) {
        $obj = parent::create($row, $schema);
        $obj->created = new AuLiteral('UNIX_TIMESTAMP()');
        return $obj;
    }
}


class Contents extends AuRowObject
{
    protected $_behaviors_ = array(
        'author' => array('AuBelongsTo', 'users', 'authorId'),
        'tags' => array('AuManyToMany', 'metas', 'relationships',
                            array('filter'=>"type='tag'", 'left'=>'cid', 'right'=>'mid')),
        //page 没有 category
        'categories' => array('AuManyToMany', 'metas', 'relationships',
                            array('filter'=>"type='category'", 'left'=>'cid', 'right'=>'mid')),
        'children' => array('AuHasMany', 'contents', 'parent'),
        'comments' => array('AuHasMany', 'comments', 'ownerId'),
    );

    public static function create($row=array(), $schema=null) {
        $obj = parent::create($row, $schema);
        $obj->created = new AuLiteral('UNIX_TIMESTAMP()');
        return $obj;
    }

    public function get_changes()
    {
        $row = parent::get_changes();
        $row['modified'] = new AuLiteral('UNIX_TIMESTAMP()');
        return $row;
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


class Comments extends AuRowObject
{
    protected $_behaviors_ = array(
        'author' => array('AuBelongsTo', 'users', 'authorId'),
        'topic' => array('AuBelongsTo', 'contents', 'ownerId'),
        'children' => array('AuHasMany', 'contents', 'parent'),
    );

    public static function create($row=array(), $schema=null) {
        $obj = parent::create($row, $schema);
        $obj->created = new AuLiteral('UNIX_TIMESTAMP()');
        return $obj;
    }
}


class Metas extends AuRowObject
{
    protected $_behaviors_ = array(
        //page 没有 category
        'contents' => array('AuManyToMany', 'contents', 'relationships',
                                array('left'=>'mid', 'right'=>'cid')),
    );
}


class Options extends AuRowObject
{
    private static $_instances_ = array();

    public static function instance($user=0) {
        if ( ! array_key_exists($user, self::$_instances_) ) {
            $query = app()->db()->factory('options');
            $rows = $query->filter_by( array('user'=>$user) )->select(null, 'name, value');
            $data = array();
            foreach ($rows as $row) {
                $data[ $row['name'] ] = $row['value'];
            }
            $obj = new Options($data);
            self::$_instances_[$user] = $obj;
        }
        return self::$_instances_[$user];
    }
}
