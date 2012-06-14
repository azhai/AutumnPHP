<?php
$this->extend('layout.php');

function block_list_content($entries, $paginate) {
?>
<div class="grid_10" id="content">
	<?php if (count($entries) > 0): ?>
	<?php foreach ($entries as $entry): ?>
		<div class="post">
			<h2 class="entry_title"><a href="<?php echo $entry->url ?>"><?php echo $entry->title ?></a></h2>
			<p class="entry_data">
				<span><?php echo _t('作者：'); ?><?php echo $entry->author ?></span>
				<span><?php echo _t('发布时间：'); ?><?php echo date('Y-m-d', $entry->created) ?></span>
				<?php echo _t('分类：'); ?><?php echo $entry->h_categories(',') ?>
			</p>
			<?php echo $entry->h_content('阅读剩余部分...') ?>
		</div>
	<?php endforeach;
		else: ?>
		<div class="post">
			<h2 class="entry_title"><?php echo _t('没有找到内容'); ?></h2>
		</div>
	<?php endif; ?>

	<ol class="pages clearfix">
		<?php echo $paginate ?>
	</ol>
</div><!-- end #content-->
<?php
}
?>
