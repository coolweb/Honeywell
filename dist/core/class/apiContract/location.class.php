<?php

class Location{
    /** @var int */
    public $locationID;

    /** @var string */
    public $name;

    /** @var string */
    public $streetAddress;

    /** @var string */
    public $city;

    /** @var string */
    public $country;

    /** @var string */
    public $zipcode;

    /** @var string */
    public $type;

    /** @var bool */
    public $hasStation;

    /** @var bool */
    public $daylightSavingTimeEnabled;

    /** @var int */
    public $locationOwnerID;

    /** @var string */
    public $locationOwnerName;

    /** @var string */
    public $locationOwnerUserName;

    /** @var Device[] */
    public $devices = array();
}