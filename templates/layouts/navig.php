<?php
$this->extend('layouts/default.php');

function nav($menus, $current=null){
	$is_first = true;
	foreach ($menus as $url => $title) {
		if ($is_first == true) {
			$is_first = false;
		}
		else {
			echo ' | ';
		}
		if ($url == $current) {
			echo '<span>' . $title . '</span>';
		}
		else {
			echo '<a href="' . $url . '">' . $title . '</a>';
		}
	}
}
?>
