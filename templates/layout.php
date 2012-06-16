<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head profile="http://gmpg.org/xfn/11">
<meta http-equiv="content-type" content="text/html; charset=<?php echo $options->charset ?>" />
<title><?php echo $entry ? ($entry->title . ' - ') : ''; echo $options->title  ?></title>
<?php
	$this->css('/media/css/style.css', 'file');
	$this->render_headers();
?>
</head>

<body>
<div id="header" class="container_16 clearfix">
	<form id="search" method="post" action="/">
		<div><input type="text" name="s" class="text" size="20" /> <input type="submit" class="submit" value="<?php echo _t('搜索'); ?>" /></div>
    </form>
	<div id="logo">
	    <h1><a href="/">
        <?php if ($options->logoUrl): ?>
        <img height="60" src="<?php echo $options->logoUrl ?>" alt="<?php echo $options->title ?>" />
        <?php endif; ?>
        <?php echo $options->title ?>
        </a></h1>
	    <p class="description"><?php echo $options->description ?></p>
    </div>
</div><!-- end #header -->

<div id="nav_box" class="clearfix">
<ul class="container_16 clearfix" id="nav_menu">
    <li<?php if($requrl == '/'): ?> class="current"<?php endif; ?>>
		<a href="/"><?php echo _t('首页'); ?></a>
	</li>
    <?php foreach ($pages as $page): ?>
    <li<?php if($requrl == $page->url ): ?> class="current"<?php endif; ?>>
		<a href="<?php echo $page->url ?>" title="<?php echo $page->title ?>"><?php echo $page->title ?></a>
	</li>
    <?php endforeach; ?>
</ul>
</div>

<div class="container_16 clearfix">

	<?php
		if ( function_exists('block_list_content') ) { block_list_content($this, $entries, $paginate); }
		if ( function_exists('block_entry_content') ) { block_entry_content($this, $user, $entry, $comments, $tags); }
		if ( function_exists('block_siderbar') ) { block_siderbar($this, $user, $options); }
	?>

	<div class="grid_14" id="footer">
	<a href="<?php echo $options->siteurl ?>"><?php echo $options->title ?></a>
	<?php echo _t('is powered by'); ?> <a href="https://github.com/azhai/AutumnPHP">AutumnPHP</a><br />
	<a href="<?php echo $options->feedUrl ?>"><?php echo _t('文章'); ?> RSS</a> and
	<a href="<?php echo $options->commentsFeedUrl ?>"><?php echo _t('评论'); ?> RSS</a>
	</div><!-- end #footer -->
</div>
</body>
</html>

<?php
function block_siderbar($this, $user, $options) {
?>
    <div class="grid_4" id="sidebar">

        <?php if (empty($options->sidebarBlock) || in_array('ShowRecentPosts', $options->sidebarBlock)): ?>
	    <div class="widget">
			<h3><?php echo _t('最新文章'); ?></h3>
            <ul>
				<li><a href=""></a></li>
            </ul>
	    </div>
        <?php endif; ?>

        <?php if (empty($options->sidebarBlock) || in_array('ShowRecentComments', $options->sidebarBlock)): ?>
	    <div class="widget">
			<h3><?php echo _t('最近回复'); ?></h3>
            <ul>
				<li><a href=""></a></li>
            </ul>
	    </div>
        <?php endif; ?>

        <?php if (empty($options->sidebarBlock) || in_array('ShowCategory', $options->sidebarBlock)): ?>
        <div class="widget">
			<h3><?php echo _t('分类'); ?></h3>
            <ul>
				<li><a href=""></a></li>
            </ul>
		</div>
        <?php endif; ?>

        <?php if (empty($options->sidebarBlock) || in_array('ShowArchive', $options->sidebarBlock)): ?>
        <div class="widget">
			<h3><?php echo _t('归档'); ?></h3>
            <ul>
				<li><a href=""></a></li>
            </ul>
		</div>
        <?php endif; ?>

        <?php if (empty($options->sidebarBlock) || in_array('ShowOther', $options->sidebarBlock)): ?>
		<div class="widget">
			<h3><?php echo _t('其它'); ?></h3>
            <ul>
                <?php if($user->uid > 0): ?>
					<li class="last"><a href=""><?php echo _t('进入后台'); ?> (<?php echo $user->screenName ?>)</a></li>
                    <li><a href=""><?php echo _t('退出'); ?></a></li>
                <?php else: ?>
                    <li class="last"><a href=""><?php echo _t('登录'); ?></a></li>
                <?php endif; ?>
                <li><a href="http://validator.w3.org/check/referer">Valid XHTML</a></li>
            </ul>
		</div>
        <?php endif; ?>

    </div><!-- end #sidebar -->
<?php
}
?>
