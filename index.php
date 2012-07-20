<?php
require_once('lib/common.php');
app()->run();
app()->debug( app()->db()->dump_all(true) );
