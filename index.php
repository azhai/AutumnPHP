<?php
require_once('lib/base.php');
require_once('lib/template.php');

function url_for() {
    return '/' . implode('/', func_get_args() );
}


class Post
{
    public $id = 0;
    public $title = '';
}

$t = new Template('posts/index.php');
$t->render(array(
    'posts' => array(new Post(), new Post()),
));

?>
