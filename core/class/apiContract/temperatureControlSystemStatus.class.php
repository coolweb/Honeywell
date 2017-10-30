<?php

namespace coolweb\honeywell\apiContract;

class TemperatureControlSystemStatus
{
    public function __construct()
    {
        $this->zones = [];
    }

    /** @var int */
    public $systemId;

    /** @var coolweb\honeywell\apiContract\Zone */
    public $zones;

    /** @var coolweb\honeywell\apiContract\TemperatureModeStatus */
    public $systemModeStatus;
}
