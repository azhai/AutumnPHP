<?php
require_once('../lib/core.php');

$db = app()->db();

ob_start();
$user = $db->factory("users")->get(1);
var_export($user);
echo "<br />\n";

var_export($user->blogs->count());
echo "<br />\n";

$db->dump_all();
$blog = $user->blogs[0];
var_export($blog->author);
echo "<br />\n";

var_export($blog->tags->options('mid'));
echo "<br />\n";
echo "<br />\n";

$blogs = $db->factory("contents")->with('comments')->all();
$comments = $blogs[0]->comments;
var_export($comments);
echo "<br />\n";
echo "<br />\n";

app()->debug( app()->db()->dump_all(true) );
ob_end_flush();
