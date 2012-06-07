<?php
require_once('lib/base.php');
$app = new Application('config.php');
an('app', 0, $app)->run();


$menus = array(
  url_for() => 'Home',
  url_for('posts') => 'List all posts',
  url_for('posts','new') => 'Create a new post',
);


$t = new Template('posts/index.php');
$t->render(array(
	'menus' => $menus, 'current' => an('req')->url,
    'posts' => array(new Post(), new Post()),
));

?>
