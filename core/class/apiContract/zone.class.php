<?php
namespace coolweb\honeywell\apiContract;

class Zone
{
    /** @var number */
    public $zoneId;
    
    /** @var string */
    public $modelType;
    
    /** @var string */
    public $zoneType;
    
    /** @var string */
    public $name;

    /** @var coolweb\honeywell\apiContract\TemperatureStatus */
    public $temperatureStatus;

    /** @var coolweb\honeywell\apiContract\HeatSetpointStatus */
    public $heatSetpointStatus;
}
