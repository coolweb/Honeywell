<?php
namespace coolweb\honeywell\apiContract;

class Gateway
{
    /** @var TemperatureControlSystem[] */
    public $temperatureControlSystems;
    
    public function __construct()
    {
        $this->temperatureControlSystems = array();
    }
}
