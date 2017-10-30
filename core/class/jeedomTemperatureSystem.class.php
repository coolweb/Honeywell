<?php
namespace coolweb\honeywell;

class JeedomTemperatureSystem
{
    public function __construct()
    {
        $this->valves = [];
    }

    /** @var int */
    public $honeywellId;

    /** @var string */
    public $mode;

    /** @var JeedomThermostaticValve[] */
    public $valves;
}
