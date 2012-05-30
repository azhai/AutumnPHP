<?php

class Template
{
    public $filename = '';

    public function __construct($filename) {
        $this->filename = $filename;
    }

    public function render(array $ctx=null) {
        if (! empty($ctx)) {
            extract($ctx);
        }
        include $this->filename;
    }
}
