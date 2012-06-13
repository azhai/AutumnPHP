<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head profile="http://gmpg.org/xfn/11">
<meta http-equiv="content-type" content="text/html; charset=<?php echo $options->charset ?>" />
<title>
	<?php echo $post ? ($post->title . ' - ') : ''; echo $options->title  ?>
</title>
<!-- 使用url函数转换相关路径 -->
<link rel="stylesheet" type="text/css" media="all" href="/media/css/style.css" />
</head>

<body>
<div id="header" class="container_16 clearfix">
	<form id="search" method="post" action="/">
		<div><input type="text" name="s" class="text" size="20" /> <input type="submit" class="submit" value="<?php echo _e('搜索'); ?>" /></div>
    </form>
	<div id="logo">
	    <h1><a href="<?php echo $options->siteUrl ?>">
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
    <li<?php if($req->url == $options->siteUrl): ?> class="current"<?php endif; ?>>
		<a href="<?php echo $options->siteUrl ?>"><?php echo _e('首页'); ?></a>
	</li>
    <?php foreach ($pages as $page): ?>
    <li<?php if($req->url == $page->url ): ?> class="current"<?php endif; ?>>
		<a href="<?php echo $page->url ?>" title="<?php echo $page->title ?>"><?php echo $page->title ?></a>
	</li>
    <?php endforeach; ?>
</ul>
</div>

<div class="container_16 clearfix">

	<?php
		$this->block('entry_list');
		$this->block('entry');
		$this->block('tags', array('tags'));
		$this->block('comments');

		$this->block('siderbar', array('options', 'user'));
	?>

	<div class="grid_14" id="footer">
	<a href="<?php echo $options->siteurl ?>"><?php echo $options->title ?></a>
	<?php echo _e('is powered by'); ?> <a href="https://github.com/azhai/AutumnPHP">AutumnPHP</a><br />
	<a href="<?php echo $options->feedUrl ?>"><?php echo _e('文章'); ?> RSS</a> and
	<a href="<?php echo $options->commentsFeedUrl ?>"><?php echo _e('评论'); ?> RSS</a>
	</div><!-- end #footer -->
</div>
</body>
</html>

<?php
function siderbar($options, $user) {
?>
    <div class="grid_4" id="sidebar">

        <?php if (empty($options->sidebarBlock) || in_array('ShowRecentPosts', $options->sidebarBlock)): ?>
	    <div class="widget">
			<h3><?php echo _e('最新文章'); ?></h3>
            <ul>
				<li><a href=""></a></li>
            </ul>
	    </div>
        <?php endif; ?>

        <?php if (empty($options->sidebarBlock) || in_array('ShowRecentComments', $options->sidebarBlock)): ?>
	    <div class="widget">
			<h3><?php echo _e('最近回复'); ?></h3>
            <ul>
				<li><a href=""></a></li>
            </ul>
	    </div>
        <?php endif; ?>

        <?php if (empty($options->sidebarBlock) || in_array('ShowCategory', $options->sidebarBlock)): ?>
        <div class="widget">
			<h3><?php echo _e('分类'); ?></h3>
            <ul>
				<li><a href=""></a></li>
            </ul>
		</div>
        <?php endif; ?>

        <?php if (empty($options->sidebarBlock) || in_array('ShowArchive', $options->sidebarBlock)): ?>
        <div class="widget">
			<h3><?php echo _e('归档'); ?></h3>
            <ul>
				<li><a href=""></a></li>
            </ul>
		</div>
        <?php endif; ?>

        <?php if (empty($options->sidebarBlock) || in_array('ShowOther', $options->sidebarBlock)): ?>
		<div class="widget">
			<h3><?php echo _e('其它'); ?></h3>
            <ul>
                <?php if($user->uid > 0): ?>
					<li class="last"><a href=""><?php echo _e('进入后台'); ?> (<?php echo $user->screenName ?>)</a></li>
                    <li><a href=""><?php echo _e('退出'); ?></a></li>
                <?php else: ?>
                    <li class="last"><a href=""><?php echo _e('登录'); ?></a></li>
                <?php endif; ?>
                <li><a href="http://validator.w3.org/check/referer">Valid XHTML</a></li>
            </ul>
		</div>
        <?php endif; ?>

    </div><!-- end #sidebar -->
<?php
}
?>
