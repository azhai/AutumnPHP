<?php
$this->extend('layout.php');
//$this->css('/media/css/960.gs.css', 'file');

function block_list_content($this, $entries, $paginate, $tags) {
    foreach($entries as $entry):
?>
<article class="postlist clearfix">
  <div class="postdate">
    <div class="day ufont action-1"><?php echo date('d', $entry->created);?></div>
    <div class="m-y ufont"><?php echo date('m/Y', $entry->created);?></div>
    <div class="tags ufont"><?php if ( function_exists('block_tags') ) { block_tags($this, $entry->tags); } ?></div>
  </div>
  <div class="postcontent">
    <h3 class="posttitle action-left"><a href="<?php echo $entry->url ?>"><?php echo $entry->title ?></a></h3>
    <div class="post"><p><?php echo $entry->h_content('阅读剩余部分...'); ?></p></div>
  </div>
</article>
<?php
    endforeach;
    if ( function_exists('block_paginate') ) { block_paginate($this, $paginate); }
}

function block_tags($this, $tags) {
    if (count($tags) > 0) {
        foreach ($tags as $tag) {
            printf('<a href="/tag/%s">%s</a>', $tag->slug, $tag->name);
        }
    }
}

function block_paginate($this, $paginate) { ?>
    <ol class="page-navigator">
        <li class="current">
            <a href="/home/page/1/">1</a>
        </li>
    </ol>
<?php } ?>