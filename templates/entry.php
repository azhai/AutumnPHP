<?php
$this->extend('layout.php');

function entry($entry) {
?>
    <div class="grid_10" id="content">
        <div class="post">
			<h2 class="entry_title"><a href="<?php echo $entry->url ?>"><?php echo $entry->title ?></a></h2>
			<p class="entry_data">
				<span><?php echo _e('作者：'); echo $entry->author ?></span>
				<?php echo _e('发布时间：'); echo date('F j, Y', $entry->created);
				if ($entry->type == 'post') { ?>
				<span><?php echo _e('分类：'); echo $entry->h_categories(',') ?></span>
				<a href="<?php echo $entry->url ?>#comments"><?php echo $entry->h_num_comment('No Comments', '1 Comment', '%d Comments'); ?></a>
				<?php } ?>
			</p>
			<?php echo $entry->text;
}

function tags($tags) {
?>
	<p class="tags">
		<?php if (count($tags) > 0) {
			echo _e('标签') . ':';
			foreach ($tags as $tag) {
				echo '';
			}
		} ?>
	</p>
	</div>
<?php
}

function comments($comments, $entry, $user) {
	//contain('comments.php');
?>
	</div><!-- end #content-->
<?php
}
?>
