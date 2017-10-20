<?php
namespace coolweb\honeywell\apiContract;

class TemperatureControlSystem
{
    /** @var number */
    public $systemId;
    
    /** @var string */
    public $modelType;
    
    /** @var Zone[] */
    public $zones;
    
    public function __construct()
    {
        $this->zones = array();
    }
}
