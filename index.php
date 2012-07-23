<?php
require_once('lib/core.php');
app()->run();
app()->debug( app()->db()->dump_all(true) );
