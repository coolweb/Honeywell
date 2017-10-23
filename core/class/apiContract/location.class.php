<?php
namespace coolweb\honeywell\apiContract;

class Location
{
    /**
    * @var LocationInfo
    */
    public $locationInfo;
    
    /**
    * @var Gateway[]
    */
    public $gateways;
    
    public function __construct()
    {
        $this->locationInfo = new LocationInfo();
        $this->gateways = array();
    }
}
