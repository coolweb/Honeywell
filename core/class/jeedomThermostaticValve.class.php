<?php
namespace coolweb\honeywell;

class JeedomThermostaticValve
{
    /** @var string */
    public $name;

    /** @var int */
    public $honeywellId;

    /** @var int */
    public $indoorTemperature;

    /** @var int */
    public $wantedTemperature;

    /** @var string */
    public $mode;

    /** @var \DateTime */
    public $until;
}
