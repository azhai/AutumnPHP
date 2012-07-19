<?php
$this->extend('layout.php');

function block_entry_content($this, $user, $entry, $comments) {
?>
<article class="postlist clearfix">
  <div class="postdate">
    <div class="day ufont action-1"><?php echo date('d', $entry->created);?></div>
    <div class="m-y ufont"><?php echo date('m/Y', $entry->created);?></div>
  </div>
  <div class="postcontent">
    <h3 class="posttitle action-left"><a href="<?php echo $entry->url ?>"><?php echo $entry->title ?></a></h3>
    <div class="post"><p><?php echo $entry->h_content('阅读剩余部分...'); ?></p></div>
  </div>
</article>

<div id="comments_wrapper" style="border:0">
  <?php if ( function_exists('block_comments') ) { block_comments($this, $user, $entry, $comments); } ?>
</div>
<?php
}

function block_comments($this, $user, $entry, $comments) {
    include('../default/comments.php');
}
?>
