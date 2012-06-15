<?php

require_once('lib/base.php');

cached('app', 0, new Application('config.php'))->run();

?>
