<div id="comments">
	<h4><?php echo $entry->h_num_comment('当前暂无评论', '仅有一条评论', '已有 %d 条评论'); ?> &raquo;</h4>

	<?php foreach ($comments as $comment) {
		echo $comment->text;
	} ?>

	<?php if($entry->allowComment): ?>
	<div id="" class="respond">

	<div class="cancel-comment-reply">
	</div>

	<h4 id="response"><?php echo _e('添加新评论'); ?> &raquo;</h4>
	<form method="post" action="" id="comment_form">
		<?php if($user->uid > 0): ?>
		<p>Logged in as <a href="<?php echo $options->profileUrl ?>"><?php echo $user->screenName ?></a>.
		<a href="<?php echo $options->logoutUrl ?>" title="Logout"><?php echo _e('退出'); ?> &raquo;</a></p>
		<?php else: ?>
		<p>
			<label for="author"><?php echo _e('称呼'); ?><span class="required">*</span></label>
			<input type="text" name="author" id="author" class="text" size="15" value="<?php echo $_POST['author']; ?>" />
		</p>
		<p>
			<label for="mail"><?php echo _e('电子邮件'); ?><?php if ($options->commentsRequireMail): ?><span class="required">*</span><?php endif; ?></label>
			<input type="text" name="mail" id="mail" class="text" size="15" value="<?php echo $_POST['mail']; ?>" />
		</p>
		<p>
			<label for="url"><?php echo _e('网站'); ?><?php if ($options->commentsRequireURL): ?><span class="required">*</span><?php endif; ?></label>
			<input type="text" name="url" id="url" class="text" size="15" value="<?php echo $_POST['url']; ?>" />
		</p>
		<?php endif; ?>
		<p><textarea rows="5" cols="50" name="text" class="textarea"><?php echo $_POST['text']; ?></textarea></p>
		<p><input type="submit" value="<?php echo _e('提交评论'); ?>" class="submit" /></p>
	</form>
	</div>
	<?php else: ?>
	<h4><?php echo _e('评论已关闭'); ?></h4>
	<?php endif; ?>
</div>
