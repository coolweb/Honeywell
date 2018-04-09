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
    
    public function __construct(
        HoneywellProxyV1 $honeywellProxy,
        UserSessionManager $userSessionManager,
        JeedomHelper $jeedomHelper
    ) {
        $this->honeywellProxy = $honeywellProxy;
        $this->userSessionManager = $userSessionManager;
        $this->jeedomHelper = $jeedomHelper;
    }
    
    /**
    * Retrieve all locations with attached devices and values
    * Save the location id and system id into plugin configuration
    * @return JeedomLocation[] null if unable to login
    */
    public function retrieveLocations()
    {
        $sessionId = $this->userSessionManager->retrieveSessionId();
        if ($sessionId == null) {
            $this->jeedomHelper->logWarning("Retrieving locations:" .
            "No session id retrieved, probably bad user/password");
            return null;
        }
        
        $userId = $this->userSessionManager->retrieveUserIdInConfiguration();
        
        $locations = $this->honeywellProxy->retrieveLocations($userId);
        $result = array();
        foreach ($locations as $location) {
            /** @var $location Location */
            $jeedomLoc = new JeedomLocation();
            $jeedomLoc->name = $location->locationInfo->name;
            $jeedomLoc->honeywellId = $location->locationInfo->locationId;
            $this->jeedomHelper->savePluginConfiguration("locationId", $jeedomLoc->honeywellId);

            foreach ($location->gateways as $gateway) {
                foreach ($gateway->temperatureControlSystems as $temperatureSys) {
                    $this->jeedomHelper->savePluginConfiguration("systemId", $temperatureSys->systemId);
            
                    foreach ($temperatureSys->zones as $zone) {
                        if ($zone->modelType == "HeatingZone") {
                            $valve = new JeedomThermostaticValve();
                            
                            $valve->honeywellId = $zone->zoneId;
                            $valve->name = $zone->name . " (" . $jeedomLoc->name . ")";
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
     * @return JeedomThermostaticValve[] The loaded valves
     */
    public function retrieveValves()
    {
        $this->jeedomHelper->logInfo("Retrieve zones from honeywell server...");

        $sessionId = $this->userSessionManager->retrieveSessionId();
        if ($sessionId == null) {
            $this->jeedomHelper->logWarning("Retrieving valves:" .
            "No session id retrieved, probably bad user/password");
            return null;
        }

        $locationId = $this->jeedomHelper->loadPluginConfiguration("locationId");
        if (empty($locationId)) {
            $errorMessage = "No location id in configuration, unable to retrieve zones";
            $this->jeedomHelper->logError($errorMessage);
            throw new \Exception($errorMessage);
        }

        $apiZones = $this->honeywellProxy->retrieveZones($locationId);
        $result = [];

        foreach ($apiZones as $apiZone) {
            $valve = new JeedomThermostaticValve();
            $valve->honeywellId = $apiZone->zoneId;
            $valve->name = $apiZone->name;
            $valve->indoorTemperature = $apiZone->temperatureStatus->temperature;
            $valve->wantedTemperature = $apiZone->heatSetpointStatus->targetTemperature;

            \array_push($result, $valve);
        }

        return $result;
    }

    /**
     * Retrieve temperature system status, system mode with all devices
     *
     * @return JeedomTemperatureSystem
     */
    public function retrieveTemperatureSystem()
    {
        $this->jeedomHelper->logInfo("Retrieve temperature system status from honeywell server...");

        $sessionId = $this->userSessionManager->retrieveSessionId();
        if ($sessionId == null) {
            $this->jeedomHelper->logWarning("Retrieving valves:" .
            "No session id retrieved, probably bad user/password");
            return null;
        }

        $tempSystem = $this->honeywellProxy->retrieveTemperatureSystemStatus();
        $apiZones = $tempSystem->zones;
        $result = new JeedomTemperatureSystem();

        $this->jeedomHelper->logDebug("Number of zones retrieved: " . \sizeof($apiZones));

        foreach ($apiZones as $apiZone) {
            $valve = new JeedomThermostaticValve();
            $valve->honeywellId = $apiZone->zoneId;
            $valve->name = $apiZone->name;
            $valve->indoorTemperature = $apiZone->temperatureStatus->temperature;
            $valve->wantedTemperature = $apiZone->heatSetpointStatus->targetTemperature;
            $valve->mode = $apiZone->heatSetpointStatus->setpointMode;

            if (is_string($apiZone->heatSetpointStatus->until)) {
                $valve->until = new \DateTime(
                    $apiZone->heatSetpointStatus->until,
                    new \DateTimeZone("UTC")
                );
            }

            \array_push($result->valves, $valve);
        }

        $result->honeywellId = $tempSystem->systemId;
        $result->mode = $tempSystem->systemModeStatus->mode;

        return $result;
    }
    
    /**
    * Create eq logic object into jeedom from locations of honeywell
    * @param JeedomLocation[] $locations Array of locations from which to create devices
    */
    public function createEqLogic($locations)
    {
        $this->jeedomHelper->logDebug("HoneywellManager - createEqLogic: start");
        $eqLogics = array();
        
        /** @var Location $location */
        foreach ($locations as $location) {
            $this->jeedomHelper->logDebug("HoneywellManager - createEqLogic: take valve for " .
                $location->name);
            
            $this->jeedomHelper->logDebug("HoneywellManager - createEqLogic: check to create location");
            $eqLogic = $this->jeedomHelper->getEqLogicByLogicalId($location->honeywellId);
            if (!is_object($eqLogic)) {
                $this->jeedomHelper->logDebug("HoneywellManager - createEqLogic:" .
                "eqLogic doesn\'t exist, create it");
                $configurations = array("deviceType" => "0");
                $eqLogic = $this->jeedomHelper->createAndSaveEqLogic(
                    $location->honeywellId,
                    $location->name,
                    $configurations
                );
                array_push($eqLogics, $eqLogic);
            }
            
            /** @var JeedomThermostaticValve[] $valves */
            $valves = $location->valves;
            
            foreach ($valves as $valve) {
                $this->jeedomHelper->logDebug("HoneywellManager - createEqLogic: create eq Logic for " .
                    $valve->name);
                
                $eqLogic = $this->jeedomHelper->getEqLogicByLogicalId($valve->honeywellId);
                
                if (!is_object($eqLogic)) {
                    $this->jeedomHelper->logDebug("HoneywellManager - createEqLogic:" .
                    "eqLogic doesn\'t exist, create it");
                    
                    $configurations = array("deviceType" => "128");
                    $eqLogicCreated = $this->jeedomHelper->createAndSaveEqLogic(
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
        
        $this->jeedomHelper->logDebug("HoneywellManager - createEqLogic: end");
        
        return $eqLogics;
    }
    
    /**
    * Create all necessary commands for a valve
    *
    * @param eqLogic $eqLogic The device from jeedom
    * @param JeedomThermostaticValve $valve
    * @return void
    */
    public function createCommandForValve($eqLogic, $valve)
    {
        $this->jeedomHelper->logDebug("HoneywellManager - createCommandForValve: start");
        $this->jeedomHelper->logDebug("HoneywellManager - createCommandForValve:" .
        "Create temperature cmd for " . $valve->name);
            
        if (!\is_object($this->jeedomHelper->getCmd($eqLogic, "Temperature"))) {
            $this->jeedomHelper->createCmd(
                $eqLogic,
                "Temperature",
                __("Température", __FILE__),
                "info",
                "numeric",
                true
            );
        }
        
        if (!\is_object($this->jeedomHelper->getCmd($eqLogic, "WantedTemperature"))) {
            $this->jeedomHelper->createCmd(
                $eqLogic,
                "WantedTemperature",
                __("Température programmée", __FILE__),
                "info",
                "numeric",
                false
            );
        }
        
        if (!\is_object($this->jeedomHelper->getCmd($eqLogic, "ChangeTemperature"))) {
            $this->jeedomHelper->createCmd(
                $eqLogic,
                "ChangeTemperature",
                __("Changer température", __FILE__),
                "action",
                "message",
                true
            );
        }
        
        if (!\is_object($this->jeedomHelper->getCmd($eqLogic, "temperatureUp"))) {
            $this->jeedomHelper->createCmd(
                $eqLogic,
                "temperatureUp",
                __("Monter température", __FILE__),
                "action",
                "other",
                true
            );
        }
        
        if (!\is_object($this->jeedomHelper->getCmd($eqLogic, "temperatureDown"))) {
            $this->jeedomHelper->createCmd(
                $eqLogic,
                "temperatureDown",
                __("Descendre température", __FILE__),
                "action",
                "other",
                true
            );
        }

        if (!\is_object($this->jeedomHelper->getCmd($eqLogic, "Mode"))) {
            $this->jeedomHelper->createCmd(
                $eqLogic,
                "Mode",
                __("Mode", __FILE__),
                "info",
                "string",
                true
            );
        }

        if (!\is_object($this->jeedomHelper->getCmd($eqLogic, "Until"))) {
            $this->jeedomHelper->createCmd(
                $eqLogic,
                "Until",
                __("Jusqu'à", __FILE__),
                "info",
                "string",
                true
            );
        }

        $this->jeedomHelper->logDebug("HoneywellManager - createCommandForValve: end");
    }
    
    /**
    * Create all commands for a location
    *
    * @param EqLogic $eqLogic
    * @param JeedomLocation $location
    */
    public function createCommandsForLocation($eqLogic, JeedomLocation $location)
    {
        $this->jeedomHelper->logDebug("HoneywellManager - createCommandsForLocation: start");
        $this->jeedomHelper->logDebug("HoneywellManager - createCommandsForLocation:" .
        "Create auto cmd for " . $location->name);
            
        if (!\is_object($this->jeedomHelper->getCmd($eqLogic, "Auto"))) {
            $this->jeedomHelper->createCmd(
                $eqLogic,
                "Auto",
                __("Automatique", __FILE__),
                "action",
                "other",
                true
            );
        }
        
        $this->jeedomHelper->logDebug("HoneywellManager - createCommandsForLocation:" .
        "Create custom cmd for " . $location->name);

        if (!\is_object($this->jeedomHelper->getCmd($eqLogic, "Custom"))) {
            $this->jeedomHelper->createCmd(
                $eqLogic,
                "Custom",
                __("Personnalisé", __FILE__),
                "action",
                "other",
                true
            );
        }

        $this->jeedomHelper->logDebug("HoneywellManager - createCommandsForLocation:" .
        "Create auto with eco cmd for " . $location->name);

        if (!\is_object($this->jeedomHelper->getCmd($eqLogic, "AutoWithEco"))) {
            $this->jeedomHelper->createCmd(
                $eqLogic,
                "AutoWithEco",
                __("Economique", __FILE__),
                "action",
                "other",
                true
            );
        }
        
        $this->jeedomHelper->logDebug("HoneywellManager - createCommandsForLocation:" .
        "Create away cmd for " . $location->name);

        if (!\is_object($this->jeedomHelper->getCmd($eqLogic, "Away"))) {
            $this->jeedomHelper->createCmd(
                $eqLogic,
                "Away",
                __("Absent", __FILE__),
                "action",
                "other",
                true
            );
        }
        
        $this->jeedomHelper->logDebug("HoneywellManager - createCommandsForLocation:" .
        "Create day off cmd for " . $location->name);

        if (!\is_object($this->jeedomHelper->getCmd($eqLogic, "DayOff"))) {
            $this->jeedomHelper->createCmd(
                $eqLogic,
                "DayOff",
                __("Journée Off", __FILE__),
                "action",
                "other",
                true
            );
        }
        
        $this->jeedomHelper->logDebug("HoneywellManager - createCommandsForLocation:" .
        "Create heating off cmd for " . $location->name);

        if (!\is_object($this->jeedomHelper->getCmd($eqLogic, "HeatingOff"))) {
            $this->jeedomHelper->createCmd(
                $eqLogic,
                "HeatingOff",
                __("Chauffage Off", __FILE__),
                "action",
                "other",
                true
            );
        }

        $this->jeedomHelper->logDebug("HoneywellManager - createCommandsForLocation:" .
        "Create current mode cmd for " . $location->name);

        $this->jeedomHelper->createCmd(
            $eqLogic,
            "Mode",
            __("Mode", __FILE__),
            "info",
            "string",
            true
        );
        
        $this->jeedomHelper->logDebug("HoneywellManager - createCommandsForLocation: end");
    }
    
    /**
    * Change permanentely a valve temperature
    *
    * @param string $valveHoneywellId The id of the valve in honeywell
    * @param number $temperature The desired temperature
    */
    public function setTemperaturePermanent($valveHoneywellId, $temperature)
    {
        $sessionId = $this->userSessionManager->retrieveSessionId();
        if ($sessionId == null) {
            $this->jeedomHelper->logWarning(
                "Retrieving locations: No session id retrieved, probably bad user/password"
            );
            return null;
        }
        
        $this->honeywellProxy->changeTemperature($sessionId, $valveHoneywellId, $temperature);
    }
    
    /**
    * Change a valve temperature until a time
    *
    * @param string $valveHoneywellId The id of the valve in honeywell
    * @param number $temperature The desired temperature
    * @param date $until Until the temperature should be set
    */
    public function setTemperatureUntil($valveHoneywellId, $temperature, $until)
    {
        $sessionId = $this->userSessionManager->retrieveSessionId();
        if ($sessionId == null) {
            $this->jeedomHelper->logWarning(
                "Retrieving locations: No session id retrieved, probably bad user/password"
            );
            return null;
        }
        
        $this->honeywellProxy->changeTemperature($sessionId, $valveHoneywellId, $temperature, "Temporary", $until);
    }

    /**
     * Up temperature by 0.5
     *
     * @param string $valveHoneywellId The id of the valve in honeywell
     * @param number $actualTemperature The actual temperature
     * @return The new temperature
     */
    public function temperatureUp($valveHoneywellId, $actualTemperature)
    {
        $tempToSet = floor($actualTemperature) + 0.5;
        if ($tempToSet == $actualTemperature) {
            $tempToSet = $tempToSet + 0.5;
        }

        if ($tempToSet < $actualTemperature) {
            $tempToSet = ceil($actualTemperature);
        }

        $this->setTemperaturePermanent($valveHoneywellId, $tempToSet);

        return $tempToSet;
    }

    /**
     * Set a valve to the sceduled mode
     *
     * @param string $valveHoneywellId The id of the valve in honeywell
     */
    public function setTemperatureToScheduleMode($valveHoneywellId)
    {
        $sessionId = $this->userSessionManager->retrieveSessionId();
        if ($sessionId == null) {
            $this->jeedomHelper->logWarning(
                "Retrieving locations: No session id retrieved, probably bad user/password"
            );
            return null;
        }
        
        $taskId = $this->honeywellProxy->changeTemperature($sessionId, $valveHoneywellId, null, "Scheduled");

        $this->waitForTaskDone($taskId);
    }

    /**
     * Down temperature by 0.5
     *
     * @param string $valveHoneywellId The id of the valve in honeywell
     * @param number $actualTemperature The actual temperature
     * @return The new temperature
     */
    public function temperatureDown($valveHoneywellId, $actualTemperature)
    {
        $tempToSet = ceil($actualTemperature) - 0.5;
        if ($tempToSet >= $actualTemperature) {
            $tempToSet = floor($actualTemperature);
        }

        $this->setTemperaturePermanent($valveHoneywellId, $tempToSet);

        return $tempToSet;
    }

    /**
     * Set a location quick action
     *
     * @param string $locationId The identifier of the location where to set the quick action
     * @param string $mode The quick action, values: Auto - Custom - AutoWithEco - Away - DayOff - HeatingOff
     * @param date $until To which time to set the quick action
     */
    public function setQuickAction($locationId, $mode, $until = null)
    {
        $sessionId = $this->userSessionManager->retrieveSessionId();
        if ($sessionId == null) {
            $this->jeedomHelper->logWarning(
                "Set quick action: No session id retrieved, probably bad user/password"
            );
            return null;
        }

        $this->honeywellProxy->setLocationQuickAction($sessionId, $locationId, $mode, $until);
    }

    /**
     * Retrieve the actual quick action of the system
     *
     * @return JeedomTemperatureSystem The system
     */
    public function getQuickAction()
    {
        $sessionId = $this->userSessionManager->retrieveSessionId();
        if ($sessionId == null) {
            $this->jeedomHelper->logWarning(
                "Get quick action: No session id retrieved, probably bad user/password"
            );
            return null;
        }

        $quickActionResponse = $this->honeywellProxy->getLocationQuickAction();

        $tempSystem = new JeedomTemperatureSystem();
        $tempSystem->mode = $quickActionResponse->Mode;
        $tempSystem->honeywellId = $this->jeedomHelper->loadPluginConfiguration("systemId");

        return $tempSystem;
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
        $this->jeedomHelper->logDebug("Execute cmd " . $cmdName);
        $this->jeedomHelper->logDebug("Options " . print_r($cmdOptions, true));

        $heatSetpoint = 0;
        $mode = "permanent";
        $until;
        if (is_array($cmdOptions)) {
            if (array_key_exists("heatSetpoint", $cmdOptions)) {
                $heatSetpoint = $cmdOptions["heatSetpoint"];
                $mode = $cmdOptions["status"];
                if ($mode == "temporary") {
                    $until = date_create($cmdOptions["until"]);
                }
            }

            if (array_key_exists("message", $cmdOptions)) {
                if ($cmdOptions["message"] == "scheduled") {
                    $mode = "scheduled";
                } else {
                    $heatSetpoint = $cmdOptions["message"];
                }
            }
        } else {
            $heatSetpoint = $cmdOptions;
        }

        switch ($cmdName) {
            case 'ChangeTemperature':
                if ($mode == "scheduled") {
                    $this->setTemperatureToScheduleMode($honeywellDeviceId);
                } else {
                    if ($mode == "permanent") {
                        $this->setTemperaturePermanent($honeywellDeviceId, $heatSetpoint);
                    } else {
                        $this->setTemperatureUntil($honeywellDeviceId, $heatSetpoint, $until);
                    }
                }
                break;
            
            case 'temperatureUp':
                if ($mode == "scheduled") {
                    $this->setTemperatureToScheduleMode($honeywellDeviceId);
                } else {
                    $this->temperatureUp($honeywellDeviceId);
                }
                break;

            case 'temperatureDown':
                if ($mode == "scheduled") {
                    $this->setTemperatureToScheduleMode($honeywellDeviceId);
                } else {
                    $this->temperatureDown($honeywellDeviceId);
                }
                break;

            case 'Auto':
            case 'AutoWithEco':
            case 'Away':
            case 'DayOff':
            case 'HeatingOff':
            case 'Custom':
                $this->setQuickAction($honeywellDeviceId, $cmdName);
                break;

            default:
                $this->jeedomHelper->logWarning("Unknown command name to execute :"
                . $cmdName);
                break;
        }
    }

    /**
     * Get task status
     * @param string taskId The identifier of the task for which to retrieve the status.
     * @return A string containing the status of the taks:
     * Created, Running, Repeated, Succeeded, Failed
     * @throws Exception The task is not found with the specified identifier.
     */
    public function getTaskStatus($taskId, $sessionId = null)
    {
        $this->jeedomHelper->logDebug("HoneywellManager - getTaskStatus taskId:" . $taskId);

        if ($sessionId == null) {
            $sessionId = $this->userSessionManager->retrieveSessionId();
            if ($sessionId == null) {
                $this->jeedomHelper->logWarning(
                    "HoneywellManager - getTaskStatus: No session id retrieved, probably bad user/password"
                );
                return null;
            }
        }

        $taskStatus = $this->honeywellProxy->getTaskStatus($sessionId, $taskId);

        return $taskStatus->state;
    }

    /**
     * Wait for a task to complete.
     * @param string $taskId The identifier of the taks to wait to complete.
     */
    public function waitForTaskDone($taskId)
    {
        $sessionId = $this->userSessionManager->retrieveSessionId();
        if ($sessionId == null) {
            $this->jeedomHelper->logWarning(
                "HoneywellManager - waitForTaskDone: No session id retrieved, probably bad user/password"
            );
            return null;
        }

        $taskCompleted = false;

        while (!$taskCompleted) {
            $taskStatus = $this->getTaskStatus($taskId, $sessionId);

            if ($taskStatus == "Succeeded" || $taskStatus == "Failed") {
                $taskCompleted = true;
            } else {
                sleep(2);
            }
        }
    }
}
