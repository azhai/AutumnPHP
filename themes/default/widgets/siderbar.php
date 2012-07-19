<?php
function widget_siderbar($user, $options, $siders) {
?>
    <div class="grid_4" id="sidebar">

        <?php if (empty($options->sidebarBlock) || in_array('ShowRecentPosts', $options->sidebarBlock)): ?>
        <div class="widget">
            <h3><?php echo _t('最新文章'); ?></h3>
            <ul>
                <?php foreach ($siders['posts'] as $item):
                    printf('<li%s><a href="%s">%s</a></li>', '', $item->url, $item->title);
                endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (empty($options->sidebarBlock) || in_array('ShowRecentComments', $options->sidebarBlock)): ?>
        <div class="widget">
            <h3><?php echo _t('最近回复'); ?></h3>
            <ul>
                <?php foreach ($siders['comments'] as $item):
                    printf('<li%s><a href="/comment/%d">%s</a></li>', '', $item->coid, $item->text);
                endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (empty($options->sidebarBlock) || in_array('ShowCategory', $options->sidebarBlock)): ?>
        <div class="widget">
            <h3><?php echo _t('分类'); ?></h3>
            <ul>
                <?php foreach ($siders['categories'] as $item):
                    printf('<li%s><a href="/category/%s">%s</a></li>', '', $item->slug, $item->name);
                endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (empty($options->sidebarBlock) || in_array('ShowArchive', $options->sidebarBlock)): ?>
        <div class="widget">
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
