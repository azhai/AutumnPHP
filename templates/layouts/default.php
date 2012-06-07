<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN"
   "http://www.w3.org/TR/html4/strict.dtd">

<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Autumn PHP blog example</title>
    <meta name="generator" content="TextMate http://macromates.com/">
    <meta name="author" content="Fabrice Luraine">
    <!-- Date: 2009-06-25 -->
</head>
<body>
  <h1>Autumn PHP blog example</h1>
  <div id="content">
    <?php $this->block('content', array('posts')) ?>
  </div>
<hr>
<p id="nav">
  <?php $this->block('nav', array('menus', 'current')) ?>
</p>
</body>
</html>
