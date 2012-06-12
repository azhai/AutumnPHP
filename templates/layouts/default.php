<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN"
   "http://www.w3.org/TR/html4/strict.dtd">

<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title><?php echo $req->app->site_title ?></title>
    <meta name="generator" content="TextMate http://macromates.com/">
    <meta name="author" content="Fabrice Luraine">
    <!-- Date: 2009-06-25 -->
</head>
<body>
  <h1><?php echo $req->app->site_title ?></h1>
  <h3>Current URL: <?php echo $req->url ?></h3>
  <div id="content">
    <?php $this->block('content', array('posts')) ?>
  </div>
<hr>
<p id="nav">
  <?php $this->block('nav', array('menus', 'req')) ?>
</p>
</body>
</html>
