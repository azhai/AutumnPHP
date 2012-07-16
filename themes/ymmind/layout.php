<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head profile="http://gmpg.org/xfn/11">
<meta http-equiv="content-type" content="text/html; charset=<?php echo $options->charset ?>" />
<title><?php echo $entry ? ($entry->title . ' - ') : ''; echo $options->title  ?></title>
<?php
    $this->css('/media/ymmind/css/style.css', 'file');
    $this->css('/media/ymmind/css/comment-style.css', 'file');
    $this->js('/media/ymmind/js/jquery.min.js', 'file');
    $this->js('/media/ymmind/js/html5.js', 'file');
    $this->js("//<![CDATA[
var TypechoComment = {
    dom : function (id) {
        return document.getElementById(id);
    },

    create : function (tag, attr) {
        var el = document.createElement(tag);

        for (var key in attr) {
            el.setAttribute(key, attr[key]);
        }

        return el;
    },

    reply : function (cid, coid) {
        var comment = this.dom(cid), parent = comment.parentNode,
            response = this.dom('respond-post-16'), input = this.dom('comment-parent'),
            form = 'form' == response.tagName ? response : response.getElementsByTagName('form')[0],
            textarea = response.getElementsByTagName('textarea')[0];

        if (null == input) {
            input = this.create('input', {
                'type' : 'hidden',
                'name' : 'parent',
                'id'   : 'comment-parent'
            });

            form.appendChild(input);
        }

        input.setAttribute('value', coid);

        if (null == this.dom('comment-form-place-holder')) {
            var holder = this.create('div', {
                'id' : 'comment-form-place-holder'
            });

            response.parentNode.insertBefore(holder, response);
        }

        comment.appendChild(response);
        this.dom('cancel-comment-reply-link').style.display = '';

        if (null != textarea && 'text' == textarea.name) {
            textarea.focus();
        }

        return false;
    },

    cancelReply : function () {
        var response = this.dom('respond-post-16'),
        holder = this.dom('comment-form-place-holder'), input = this.dom('comment-parent');

        if (null != input) {
            input.parentNode.removeChild(input);
        }

        if (null == holder) {
            return true;
        }

        this.dom('cancel-comment-reply-link').style.display = 'none';
        holder.parentNode.insertBefore(response, holder);
        return false;
    }
}
//]]>", 'inline');
    $this->render_scripts();
?>
</head>

<body>
<header>
  <div id="logo" class="action-left">
    <h1><a href="/"><?php echo $options->title ?></a></h1>
  </div>
</header>
<nav class="ufont">
  <ul>
    <li<?php if($requrl == '/'): ?> class="current"<?php endif; ?>>
        <a href="/"><?php echo _t('首页'); ?></a>
    </li>
    <?php foreach ($pages as $page): ?>
    <li<?php if($requrl == $page->url ): ?> class="current"<?php endif; ?>>
        <a href="<?php echo $page->url ?>" title="<?php echo $page->title ?>"><?php echo $page->title ?></a>
    </li>
    <?php endforeach; ?>
  </ul>
</nav><!-- end #header -->

<section id="container">
    <div id="html5"><a href="http://www.w3.org/html/logo/">
        <img src="<?php echo '/media/ymmind/imgs/html5logo.png'; ?>" width="70" height="70"
                                alt="HTML5 Powered" title="HTML5 Powered" class="action-1 hdown" />
    </a></div>
    <section id="content">
    <?php
        if ( function_exists('block_list_content') ) { block_list_content($this, $entries, $paginate); }
        if ( function_exists('block_entry_content') ) { block_entry_content($this, $user, $entry, $comments); }
    ?>
    </section>
    <?php
        if ( function_exists('block_siderbar') ) { block_siderbar($this, $user, $options, $siders); }
    ?>
</section>
<div class="clearfix"></div>

    <footer class="ufont">Copyright &copy; 2012. By <a href="https://github.com/azhai/AutumnPHP">AutumnPHP</a></footer>
    <?php
        $this->js('/media/ymmind/js/my.js', 'file');
    ?>
</body>
</html>


<?php
function block_siderbar($this, $user, $options, $siders) {
?>
    <aside id="side">
    <div id="sidebar">

        <?php if (empty($options->sidebarBlock) || in_array('ShowRecentPosts', $options->sidebarBlock)): ?>
        <div class="widget ufont fixed">
            <h3><?php echo _t('最新文章'); ?></h3>
            <ul>
                <?php foreach ($siders['posts'] as $item):
                    printf('<li%s><a href="%s">%s</a></li>', '', $item->url, $item->title);
                endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (empty($options->sidebarBlock) || in_array('ShowRecentComments', $options->sidebarBlock)): ?>
        <div class="widget ufont">
            <h3><?php echo _t('最近回复'); ?></h3>
            <ul>
                <?php foreach ($siders['comments'] as $item):
                    printf('<li%s><a href="/comment/%d">%s</a></li>', '', $item->coid, $item->text);
                endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (empty($options->sidebarBlock) || in_array('ShowCategory', $options->sidebarBlock)): ?>
        <div class="widget ufont">
            <h3><?php echo _t('分类'); ?></h3>
            <ul>
                <?php foreach ($siders['categories'] as $item):
                    printf('<li%s><a href="/category/%s">%s</a></li>', '', $item->slug, $item->name);
                endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (empty($options->sidebarBlock) || in_array('ShowArchive', $options->sidebarBlock)): ?>
        <div class="widget ufont">
            <h3><?php echo _t('归档'); ?></h3>
            <ul>
                <?php foreach ($siders['archives'] as $item):
                    printf('<li%s><a href="/archive/%s">%s(%d)</a></li>', '',
                           $item['year_month'], $item['year_month'], $item['count']);
                endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (empty($options->sidebarBlock) || in_array('ShowOther', $options->sidebarBlock)): ?>
        <div class="widget ufont">
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

    </div>
    <a href="#" id="toTop" title="回到顶部">Top</a>
    </aside><!-- end #sidebar -->
<?php
}
?>