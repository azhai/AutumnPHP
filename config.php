<?php
return array(
	'basic' => array(
		'site_title' => 'Autumn PHP blog example',
		'debug' => true,
		'max_router_layer' => 2,
	),
    'databases' => array(
        'default' => array('mysql:host=localhost;dbname=db_blog', 'ryan', 'ryan', 't_'),
    ),
);
