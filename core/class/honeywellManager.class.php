<?php
namespace coolweb\honeywell;

use coolweb\honeywell\apiContract as apiContract;

class HoneywellManager
{
    /** @var HoneywellProxyV1 */
    private $honeywellProxy;
    
    /** @var UserSessionManager */
    private $userSessionManager;
    
    /** @var JeedomHelper */
    private $jeedomHelper;
    
    public function __construct(HoneywellProxyV1 $honeywellProxy, UserSessionManager $userSessionManager, JeedomHelper $jeedomHelper)
    {
        $this->honeywellProxy = $honeywellProxy;
        $this->userSessionManager = $userSessionManager;
        $this->jeedomHelper = $jeedomHelper;
    }
    
    /**
    * Retrieve all locations with attached devices and values
    * @return JeedomLocation[] null if unable to login
    */
    public function RetrieveLocations()
    {
        $sessionId = $this->userSessionManager->RetrieveSessionId();
        if ($sessionId == null) {
            $this->jeedomHelper->logWarning('Retrieving locations: No session id retrieved, probably bad user/password');
            return null;
        }
        
        $userId = $this->userSessionManager->RetrieveUserIdInConfiguration();
        
        $locations = $this->honeywellProxy->RetrieveLocations($userId);
        $result = array();
        foreach ($locations as $location) {
            /** @var $location Location */
            $jeedomLoc = new JeedomLocation();
            $jeedomLoc->name = $location->locationInfo->name;
            $jeedomLoc->honeywellId = $location->locationInfo->locationId;
            $this->jeedomHelper->SavePluginConfiguration('locationId', $jeedomLoc->honeywellId);

            foreach ($location->gateways as $gateway) {
                foreach ($gateway->temperatureControlSystems as $temperatureSys) {
                    foreach ($temperatureSys->zones as $zone) {
                        if ($zone->modelType == 'HeatingZone') {
                            $valve = new JeedomThermostaticValve();
                            
                            $valve->honeywellId = $zone->zoneId;
                            $valve->name = $zone->name . ' (' . $jeedomLoc->name . ')';
                            //$valve->indoorTemperature = $device->thermostat->indoorTemperature;
                            //$valve->wantedTemperature = $device->thermostat->changeableValues->heatSetpoint->value;
                            
                            array_push($jeedomLoc->valves, $valve);
                        }
                    }
                }
            }
            
            array_push($result, $jeedomLoc);
        }
        
        return $result;
    }

    /**
     * Retrieve all zones for current location
     * Read locationId plugin configuration
     * @return [zone[]] The loaded zones
     */
    public function RetrieveZones()
    {
    }
    
    /**
    * Create eq logic object into jeedom from locations of honeywell
    * @param JeedomLocation[] $locations Array of locations from which to create devices
    */
    public function CreateEqLogic($locations)
    {
        $this->jeedomHelper->logDebug('HoneywellManager - CreateEqLogic: start');
        $eqLogics = array();
        
        /** @var Location $location */
        foreach ($locations as $location) {
            $this->jeedomHelper->logDebug('HoneywellManager - CreateEqLogic: take valve for ' .
                $location->name);
            
            $this->jeedomHelper->logDebug('HoneywellManager - CreateEqLogic: check to create location');
            $eqLogic = $this->jeedomHelper->getEqLogicByLogicalId($location->honeywellId);
            if (!is_object($eqLogic)) {
                $this->jeedomHelper->logDebug('HoneywellManager - CreateEqLogic:' .
                'eqLogic doesn\'t exist, create it');
                $configurations = array('deviceType' => '0');
                $eqLogic = $this->jeedomHelper->CreateAndSaveEqLogic(
                $location->honeywellId,
                $location->name,
                $configurations
                );
                array_push($eqLogics, $eqLogic);
            }
            
            /** @var JeedomThermostaticValve[] $valves */
            $valves = $location->valves;
            
            foreach ($valves as $valve) {
                $this->jeedomHelper->logDebug('HoneywellManager - CreateEqLogic: create eq Logic for ' .
                    $valve->name);
                
                $eqLogic = $this->jeedomHelper->getEqLogicByLogicalId($valve->honeywellId);
                
                if (!is_object($eqLogic)) {
                    $this->jeedomHelper->logDebug('HoneywellManager - CreateEqLogic:' .
                    'eqLogic doesn\'t exist, create it');
                    
                    $configurations = array('deviceType' => '128');
                    $eqLogicCreated = $this->jeedomHelper->CreateAndSaveEqLogic(
                    $valve->honeywellId,
                    $valve->name,
                    $configurations
                    );
                    
                    array_push($eqLogics, $eqLogicCreated);
                } else {
                    array_push($eqLogics, $eqLogic);
                }
            }
        }
        
        $this->jeedomHelper->logDebug('HoneywellManager - CreateEqLogic: end');
        
        return $eqLogics;
    }
    
    /**
    * Create all necessary commands for a valve
    *
    * @param eqLogic $eqLogic The device from jeedom
    * @param JeedomThermostaticValve $valve
    * @return void
    */
    public function CreateCommandForValve($eqLogic, $valve)
    {
        $this->jeedomHelper->logDebug('HoneywellManager - CreateCommandForValve: start');
        $this->jeedomHelper->logDebug('HoneywellManager - CreateCommandForValve:' .
        'Create temperature cmd for ' . $valve->name);
            
        $this->jeedomHelper->CreateCmd(
        $eqLogic,
        'Temperature',
        __('Température', __FILE__),
        'info',
        'numeric',
        true
            
        );
        
        $this->jeedomHelper->CreateCmd(
        $eqLogic,
        'WantedTemperature',
        __('Température programmée', __FILE__),
        'info',
        'numeric',
        false
        
        );
        
        $this->jeedomHelper->CreateCmd(
        $eqLogic,
        'ChangeTemperature',
        __('Changer température', __FILE__),
        'action',
        'other',
        true
        
        );
        
        $this->jeedomHelper->CreateCmd(
        $eqLogic,
        'TemperatureUp',
        __('Monter température', __FILE__),
        'action',
        'other',
        true
        
        );
        
        $this->jeedomHelper->CreateCmd(
        $eqLogic,
        'TemperatureDown',
        __('Descendre température', __FILE__),
        'action',
        'other',
        true
        
        );

        $this->jeedomHelper->logDebug('HoneywellManager - CreateCommandForValve: end');
    }
    
    /**
    * Create all commands for a location
    *
    * @param EqLogic $eqLogic
    * @param JeedomLocation $location
    */
    public function createCommandsForLocation($eqLogic, JeedomLocation $location)
    {
        $this->jeedomHelper->logDebug('HoneywellManager - createCommandsForLocation: start');
        $this->jeedomHelper->logDebug('HoneywellManager - createCommandsForLocation:' .
        'Create quick action auto cmd for ' . $location->name);
            
        $this->jeedomHelper->CreateCmd(
        $eqLogic,
        'Auto',
        __('Automatique', __FILE__),
        'action',
        'other',
        true
            
        );
        
        $this->jeedomHelper->logDebug('HoneywellManager - createCommandsForLocation:' .
        'Create quick action custom cmd for ' . $location->name);
        $this->jeedomHelper->CreateCmd(
        $eqLogic,
        'Custom',
        __('Personnalisé', __FILE__),
        'action',
        'other',
        true
            );
        
        $this->jeedomHelper->logDebug('HoneywellManager - createCommandsForLocation:' .
        'Create quick action eco cmd for ' . $location->name);
        $this->jeedomHelper->CreateCmd(
        $eqLogic,
        'AutoWithEco',
        __('Economique', __FILE__),
        'action',
        'other',
        true
            );
        
        $this->jeedomHelper->logDebug('HoneywellManager - createCommandsForLocation:' .
        'Create quick action away cmd for ' . $location->name);
        $this->jeedomHelper->CreateCmd(
        $eqLogic,
        'Away',
        __('Absent', __FILE__),
        'action',
        'other',
        true
            );
        
        $this->jeedomHelper->logDebug('HoneywellManager - createCommandsForLocation:' .
        'Create quick action day off cmd for ' . $location->name);
        $this->jeedomHelper->CreateCmd(
        $eqLogic,
        'DayOff',
        __('Journée Off', __FILE__),
        'action',
        'other',
        true
            );
        
        $this->jeedomHelper->logDebug('HoneywellManager - createCommandsForLocation:' .
        'Create quick action heating off cmd for ' . $location->name);
        $this->jeedomHelper->CreateCmd(
        $eqLogic,
        'HeatingOff',
        __('Chauffage Off', __FILE__),
        'action',
        'other',
        true
            );
        
        $this->jeedomHelper->logDebug('HoneywellManager - createCommandsForLocation: end');
    }
    
    /**
    * Change permanentely a valve temperature
    *
    * @param string $valveHoneywellId The id of the valve in honeywell
    * @param number $temperature The desired temperature
    */
    public function SetTemperaturePermanent($valveHoneywellId, $temperature)
    {
        $sessionId = $this->userSessionManager->RetrieveSessionId();
        if ($sessionId == null) {
            $this->jeedomHelper->logWarning('Retrieving locations: No session id retrieved, probably bad user/password');
            return null;
        }
        
        $this->honeywellProxy->ChangeTemperature($sessionId, $valveHoneywellId, $temperature);
    }
    
    /**
    * Change a valve temperature until a time
    *
    * @param string $valveHoneywellId The id of the valve in honeywell
    * @param number $temperature The desired temperature
    * @param date $until Until the temperature should be set
    */
    public function SetTemperatureUntil($valveHoneywellId, $temperature, $until)
    {
        $sessionId = $this->userSessionManager->RetrieveSessionId();
        if ($sessionId == null) {
            $this->jeedomHelper->logWarning('Retrieving locations: No session id retrieved, probably bad user/password');
            return null;
        }
        
        $this->honeywellProxy->ChangeTemperature($sessionId, $valveHoneywellId, $temperature, 'Temporary', $until);
    }

    /**
     * Up temperature by 0.5
     *
     * @param string $valveHoneywellId The id of the valve in honeywell
     * @param number $actualTemperature The actual temperature
     * @return The new temperature
     */
    public function TemperatureUp($valveHoneywellId, $actualTemperature)
    {
        $tempToSet = floor($actualTemperature) + 0.5;
        if ($tempToSet == $actualTemperature) {
            $tempToSet = $tempToSet + 0.5;
        }

        if ($tempToSet < $actualTemperature) {
            $tempToSet = ceil($actualTemperature);
        }

        $this->SetTemperaturePermanent($valveHoneywellId, $tempToSet);

        return $tempToSet;
    }

    /**
     * Set a valve to the sceduled mode
     *
     * @param string $valveHoneywellId The id of the valve in honeywell
     */
    public function SetTemperatureToScheduleMode($valveHoneywellId)
    {
        $sessionId = $this->userSessionManager->RetrieveSessionId();
        if ($sessionId == null) {
            $this->jeedomHelper->logWarning('Retrieving locations: No session id retrieved, probably bad user/password');
            return null;
        }
        
        $this->honeywellProxy->ChangeTemperature($sessionId, $valveHoneywellId, null, 'Scheduled');
    }

    /**
     * Down temperature by 0.5
     *
     * @param string $valveHoneywellId The id of the valve in honeywell
     * @param number $actualTemperature The actual temperature
     * @return The new temperature
     */
    public function TemperatureDown($valveHoneywellId, $actualTemperature)
    {
        $tempToSet = ceil($actualTemperature) - 0.5;
        if ($tempToSet >= $actualTemperature) {
            $tempToSet = floor($actualTemperature);
        }

        $this->SetTemperaturePermanent($valveHoneywellId, $tempToSet);

        return $tempToSet;
    }

    /**
     * Set a location quick action
     *
     * @param string $locationId The identifier of the location where to set the quick action
     * @param string $mode The quick action, values: Auto - Custom - AutoWithEco - Away - DayOff - HeatingOff
     * @param date $until To which time to set the quick action
     */
    public function SetQuickAction($locationId, $mode, $until = null)
    {
        $sessionId = $this->userSessionManager->RetrieveSessionId();
        if ($sessionId == null) {
            $this->jeedomHelper->logWarning('Retrieving locations: No session id retrieved, probably bad user/password');
            return null;
        }

        $this->honeywellProxy->SetLocationQuickAction($sessionId, $locationId, $mode, $until);
    }

    /**
     * Execute a jeedom command
     *
     * @param string $honeywellDeviceId The id of the device on honeywell site
     * @param string $cmdName The name of the cmd to execute
     * @param mixed $cmdOptions Parameters passed to the cmd
     */
    public function execCommand($honeywellDeviceId, $cmdName, $cmdOptions)
    {
        $this->jeedomHelper->logDebug('Execute cmd ' . $cmdName);
        $this->jeedomHelper->logDebug('Options ' . print_r($cmdOptions, true));

        $heatSetpoint = 0;
        $mode = 'permanent';
        $until;
        if (is_array($cmdOptions) && array_key_exists('heatSetpoint', $cmdOptions)) {
            $heatSetpoint = $cmdOptions['heatSetpoint'];
            $mode = $cmdOptions['status'];
            if ($mode == 'temporary') {
                $until = date_create($cmdOptions['until']);
            }
        } else {
            $heatSetpoint = $cmdOptions;
        }

        switch ($cmdName) {
            case 'ChangeTemperature':
                if ($mode == 'scheduled') {
                    $this->SetTemperatureToScheduleMode($honeywellDeviceId);
                } else {
                    if ($mode == 'permanent') {
                        $this->SetTemperaturePermanent($honeywellDeviceId, $heatSetpoint);
                    } else {
                        $this->SetTemperatureUntil($honeywellDeviceId, $heatSetpoint, $until);
                    }
                }
                break;
            
            case 'TemperatureUp':
                if ($mode == 'scheduled') {
                    $this->SetTemperatureToScheduleMode($honeywellDeviceId);
                } else {
                    $this->TemperatureUp($honeywellDeviceId);
                }
                break;

            case 'TemperatureDown':
                if ($mode == 'scheduled') {
                    $this->SetTemperatureToScheduleMode($honeywellDeviceId);
                } else {
                    $this->TemperatureDown($honeywellDeviceId);
                }
                break;

            case 'Auto':
            case 'AutoWithEco':
            case 'Away':
            case 'DayOff':
            case 'HeatingOff':
            case 'Custom':
                $this->SetQuickAction($honeywellDeviceId, $cmdName);
                break;

            default:
                $this->jeedomHelper->logWarning('Unknown command name to execute :'
                . $cmdName);
                break;
        }
    }
}
