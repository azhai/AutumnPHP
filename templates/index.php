<?php
$this->extend('layout.php');

function content($posts, $paginate){
?>
<div class="grid_10" id="content">
	<?php foreach($posts as $post): ?>
        <div class="post">
			<h2 class="entry_title"><a href="<?php echo $post->url ?>"><?php echo $post->title ?></a></h2>
			<p class="entry_data">
				<span><?php _e('作者：'); ?><?php echo $post->author ?></span>
				<span><?php _e('发布时间：'); ?><?php echo date('F j, Y', $post->created) ?></span>
				<span><?php _e('分类：'); ?><?php echo $post->h_categories(',') ?></span>
				<a href="<?php echo $post->url ?>#comments"><?php echo $post->h_num_comment('No Comments', '1 Comment', '%d Comments'); ?></a>
			</p>
			<?php echo $post->h_content('阅读剩余部分...'); ?>
        </div>
	<?php endforeach; ?>

	<?php echo $paginate ?>
</div><!-- end #content-->
<?php
}
?>
