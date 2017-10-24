<?php
namespace coolweb\honeywell\apiContract;

class Session
{
    /** @var string */
    public $sessionId;
    
    /** @var UserInfo */
    public $userInfo;

    /** @var string */
    public $access_token;
}
