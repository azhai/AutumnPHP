<?php
$this->extend('layout.php');

function content(){
?>
    <section id="container">
        <div id="html5"><a href="http://www.w3.org/html/logo/">
            <img src="<?php $this->options->themeUrl('images/html5logo.png'); ?>"
                    width="70" height="70" alt="HTML5 Powered" title="HTML5 Powered"
                    class="action-1 hdown" />
        </a></div>
        <section id="content">
        <article class="postlist clearfix">
          <div class="postdate">
            <div class="day ufont action-1"><?php echo date('d');?></div>
            <div class="m-y ufont"><?php echo date('m/Y');?></div>
          </div>
          <div class="postcontent">
            <h3 class="posttitle action-left">404 - <?php _e('页面没找到'); ?></h3>
          </div>
        </article>
        </section>
<?php
}
?>
