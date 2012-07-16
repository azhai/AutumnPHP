
    <h4><?php echo $entry->h_num_comment('当前暂无评论', '仅有一条评论', '已有 %d 条评论'); ?> &raquo;</h4>

    <ol class="comment-list">
    <?php foreach ($comments as $i => $comment) { ?>
    <li id="comment-<?php echo $i ?>" class="comment-body comment-parent comment-odd">
        <div class="comment-author">
            <img class="avatar" src="/media/imgs/none.jpg" alt="Typecho" width="32" height="32">
            <cite class="fn"><a href="<?php echo $comment->url ?>" rel="external nofollow"><?php echo $comment->author ?></a></cite>
        </div>
        <div class="comment-meta">
            <a href="/archives/<?php echo $entry->cid ?>/#comment-1"><?php echo date('Y-m-d h:i a', $comment->created) ?></a>
        </div>
        <p><?php echo $comment->text ?></p>
        <div class="comment-reply">
            <a href="/archives/<?php echo $entry->cid ?>/?replyTo=<?php echo $i ?>#respond-post-1" rel="nofollow"
                                    onclick="return TypechoComment.reply('comment-1', <?php echo $i ?>);">回复</a>
        </div>
    </li>
    <?php } ?>
    </ol>

    <?php if($entry->allowComment): ?>
    <div id="" class="respond">

    <div class="cancel-comment-reply">
    </div>

    <h4 id="response"><?php echo _t('添加新评论'); ?> &raquo;</h4>
    <form method="post" action="" id="comment_form">
        <?php if($user->uid > 0): ?>
        <p>Logged in as <a href="<?php echo $options->profileUrl ?>"><?php echo $user->screenName ?></a>.
        <a href="<?php echo $options->logoutUrl ?>" title="Logout"><?php echo _t('退出'); ?> &raquo;</a></p>
        <?php else: ?>
        <p>
            <label for="author"><?php echo _t('称呼'); ?><span class="required">*</span></label>
            <input type="text" name="author" id="author" class="text" size="15" value="<?php echo $_POST['author']; ?>" />
        </p>
        <p>
            <label for="mail"><?php echo _t('电子邮件'); ?><?php if ($options->commentsRequireMail): ?><span class="required">*</span><?php endif; ?></label>
            <input type="text" name="mail" id="mail" class="text" size="15" value="<?php echo $_POST['mail']; ?>" />
        </p>
        <p>
            <label for="url"><?php echo _t('网站'); ?><?php if ($options->commentsRequireURL): ?><span class="required">*</span><?php endif; ?></label>
            <input type="text" name="url" id="url" class="text" size="15" value="<?php echo $_POST['url']; ?>" />
        </p>
        <?php endif; ?>
        <p><textarea rows="5" cols="50" name="text" class="textarea"><?php echo $_POST['text']; ?></textarea></p>
        <p><input type="submit" value="<?php echo _t('提交评论'); ?>" class="submit" /></p>
    </form>
    </div>
    <?php else: ?>
    <h4><?php echo _t('评论已关闭'); ?></h4>
    <?php endif; ?>

