<?php
$this->extend('layout.php');

function block_entry_content($this, $user, $entry, $comments, $tags) {
?>
<div class="grid_10" id="content">
	<div class="post">
		<h2 class="entry_title"><a href="<?php echo $entry->url ?>"><?php echo $entry->title ?></a></h2>
		<p class="entry_data">
			<span><?php echo _t('作者：'); echo $entry->author ?></span>
			<span><?php echo _t('发布时间：'); echo date('Y-m-d', $entry->created) ?></span>
			<?php if ($entry->type == 'post') { ?>
			<span><?php echo _t('分类：'); echo $entry->h_categories(',') ?></span>
			<a href="<?php echo $entry->url ?>#comments"><?php echo $entry->h_num_comment('No Comments', '1 Comment', '%d Comments'); ?></a>
			<?php } ?>
		</p>
		<?php
			echo $entry->text;
			if ( function_exists('block_tags') ) { block_tags($this, $tags); }
		?>
	</div>
	<?php if ( function_exists('block_comments') ) { block_comments($this, $user, $entry, $comments); } ?>
</div><!-- end #content-->
<?php
}

function block_tags($this, $tags) {
?>
<p class="tags">
	<?php if (count($tags) > 0) {
		echo _t('标签') . ':';
		foreach ($tags as $tag) {
			printf('<a href="/tag/%s">%s</a>', $tag->slug, $tag->name);
		}
	} ?>
</p>
<?php
}

function block_comments($this, $user, $entry, $comments) {
	include('comments.php');
}
?>
