<?php

function newAction($req) { #/post/new调用
	$menus = array(
		url_for() => 'Home',
		url_for('post') => 'List all posts',
		url_for('post','new') => 'Create a new post',
		url_for('post','edit') => 'Edit a new post',
	);

	/*
	$db = an('db', 'default', 'load_plugin', array(
		'f3/db.php',
		array('DB', '__construct'),
		$req->app->configs->databases['default'],
		array('f3/base.php')
	));
	$rs = $db::sql('SELECT * FROM t_user LIMIT 1');
	$post = new Post();
	$post->accept($rs[0]);
	var_dump($post);
	*/

	$t = new Template('posts/index.php');
	$t->render(array(
		'req' => $req, 'menus' => $menus,
		'posts' => array(new Post(), new Post()),
	));
}

class PostView
{
	public function indexAction($req) { #/post调用
		return newAction($req);
	}
}


if ($req->action == 'edit') { #/post/edit调用
	newAction($req);
}

?>
