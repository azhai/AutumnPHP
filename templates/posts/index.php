<?php
$this->extend('layouts/navig.php');

function content($posts){
?>
<div id="posts">
  <?php if(empty($posts)) { ?>
  <p>No posts</p>
  <?php } else {
      foreach($posts as $post) {
         echo $post->id . ' ' . $post->title . '<br />';
      }
  } ?>
</div>
<?php
}
?>
