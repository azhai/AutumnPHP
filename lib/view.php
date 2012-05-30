<?php

class View
{
    public $req = null;
    public $user = null;

    public function init() {
        if (is_null($this->req)) {
            $this->req = Request();
        }
        if (is_null($this->user)) {
            $this->user = User();
        }
    }
}


class Request
{
}


class User
{
}
