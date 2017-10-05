<?php

class JeedomLocation{
    /** @var JeedomThermostaticValve[] */
    public $valves = array();

    /**
     * The name of the location
     *
     * @var string
     */
    public $name = '';

    /**
     * The id of the location in honeywell server
     *
     * @var number
     */
    public $honeywellId = 0;
}