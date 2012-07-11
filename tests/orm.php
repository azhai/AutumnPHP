<?php
require_once(dirname(__FILE__) . '/lib/common.php');

$db = app()->db();
try {
	$user = $db->factory("users")->get(1);
	var_export($user);
	echo "<br />\n";

	var_export($user->blogs->count());
	echo "<br />\n";

	$blog = $user->blogs[0];
	var_export($blog->author);
	echo "<br />\n";

	var_export($blog->tags->options('mid'));
	echo "<br />\n";
}
catch (Exception $e) {
	echo "-----------------------------------------------------------------------<br />\n";
	echo $e->getMessage();
	echo "<br />\n";
}
echo "-----------------------------------------------------------------------<br />\n";
$db->dump_all();
