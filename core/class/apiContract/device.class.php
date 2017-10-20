<?php
namespace coolweb\honeywell\apiContract;

class Device
{
    /** @var int */
    public $deviceID;
        
    /** @var string */
    public $name;
        
    /** @var number */
    public $deviceType;
        
    /**
    * thermostat
    *
    * @var Thermostat
    */
    public $thermostat;
}
