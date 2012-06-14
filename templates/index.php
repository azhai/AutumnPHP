<?php
$this->extend('layout.php');

function block_list_content($entries, $paginate) {
?>
<div class="grid_10" id="content">
	<?php foreach($entries as $entry): ?>
	<div class="post">
		<h2 class="entry_title"><a href="<?php echo $entry->url ?>"><?php echo $entry->title ?></a></h2>
		<p class="entry_data">
			<span><?php echo _t('作者：'); echo $entry->author ?></span>
			<span><?php echo _t('发布时间：'); echo date('Y-m-d', $entry->created) ?></span>
			<span><?php echo _t('分类：'); echo $entry->h_categories(',') ?></span>
			<a href="<?php echo $entry->url ?>#comments"><?php echo $entry->h_num_comment('No Comments', '1 Comment', '%d Comments'); ?></a>
		</p>
		<?php echo $entry->h_content('阅读剩余部分...'); ?>
	</div>
	<?php endforeach;

	echo $paginate ?>
</div><!-- end #content-->
<?php
}

function block_paginate($paginate) { ?>
	<ol class="page-navigator">
		<li class="current">
			<a href="/page/1/">1</a>
		</li>
	</ol>
<?php } ?>
