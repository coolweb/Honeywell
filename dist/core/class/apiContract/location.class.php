<?php
class Zone{
    /** @var number */
    public $zoneId;
    
    /** @var string */
    public $modelType;

    /** @var string */
    public $zoneType;

    /** @var string */
    public $name;
}

class TemperatureControlSystem{
    /** @var number */
    public $systemId;

    /** @var string */
    public $modelType;

    /** @var Zone[] */
    public $zones;    

    public function __construct()
    {
        $this->zones = Array();
    }
}

class Gateway{
    /** @var TemperatureControlSystem[] */
    public $temperatureControlSystems;

    public function __construct()
    {
        $this->temperatureControlSystems = Array();
    }
}

class Location{
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
        $this->gateways = Array();
    }
}

class LocationInfo{
    /** @var int */
    public $locationId;

    /** @var string */
    public $name;

    /** @var string */
    public $streetAddress;

    /** @var string */
    public $city;

    /** @var string */
    public $country;

    /** @var string */
    public $postcode;

    /** @var string */
    public $locationType;
}