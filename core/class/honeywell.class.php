<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
if (file_exists(dirname(__FILE__) . "/../../../../core/php/core.inc.php")) {
    require_once dirname(__FILE__) . "/../php/honeywell.inc.php";
}

class honeywell extends eqLogic
{
    /*     * *************************Attributs****************************** */
    /** @var \coolweb\honeywell\JeedomHelper */
    public static $_jeedomHelper;

    /** @var \coolweb\honeywell\HoneywellManager */
    public static $_honeywellManager;
    
    
    /*     * ***********************Methode static*************************** */
    
    /*
    * Fonction exécutée automatiquement toutes les minutes par Jeedom
    */
    public static function cron15()
    {        
        $container = DI\ContainerBuilder::buildDevContainer();
        
        /**
        * @var JeedomHelper
        */
        $jeedomHelper = $container->get("coolweb\honeywell\JeedomHelper");
        $honeywellManager = $container->get("coolweb\honeywell\HoneywellManager");
        
        $jeedomHelper->logDebug("Cron start, retrieve valves");
        $systemStatus = $honeywellManager->retrieveTemperatureSystem();

        if ($systemStatus == null) {
            $jeedomHelper->logError("Unable to retrieve temperature system status");
            return;
        }

        $valves = $systemStatus->valves;
            
        if ($valves == null) {
            $jeedomHelper->logError("Honeywell plugin class - cron, unable to get valves, " .
            "check user and password account");
        }
        
        $eqLogics = $jeedomHelper->loadEqLogic();
        $locationId = $jeedomHelper->loadPluginConfiguration("locationId");
        
        foreach ($eqLogics as $eqLogic) {
            if ($eqLogic->getLogicalId() == $locationId) {
                $jeedomHelper->logDebug("Found location into jeedom, update values...");
                $changed = false;

                $changed = $eqLogic->checkAndUpdateCmd(
                    "Mode",
                    $systemStatus->mode
                )
                    || $changed;

                if ($changed) {
                    $jeedomHelper->clearCacheAndUpdateWidget($eqLogic);
                }
            }

            foreach ($valves as $valve) {
                if ($eqLogic->getLogicalId() == $valve->honeywellId) {
                    $jeedomHelper->logDebug("Found valve " . $valve->name . " into jeedom, update values...");
                    $changed = false;
                        
                    $changed = $eqLogic->checkAndUpdateCmd(
                        "temperature",
                        $valve->indoorTemperature
                    )
                    || $changed;
                            
                    $changed = $eqLogic->checkAndUpdateCmd(
                        "wantedTemperature",
                        $valve->wantedTemperature
                    )
                    || $changed;

                    $changed = $eqLogic->checkAndUpdateCmd(
                        "mode",
                        $valve->mode
                    )
                    || $changed;

                    $changed = $eqLogic->checkAndUpdateCmd(
                        "until",
                        $valve->until == null ? "" : $valve->until->format("Y-m-d H:i:s")
                    )
                    || $changed;
                        
                    if ($changed) {
                        $jeedomHelper->clearCacheAndUpdateWidget($eqLogic);
                    }
                }
            }
        }
    }
    
    /*
    * Fonction exécutée automatiquement toutes les heures par Jeedom
    public static function cronHourly() {

    }
    */
    
    /*
    * Fonction exécutée automatiquement tous les jours par Jeedom
    public static function cronDayly() {

    }
    */
    
    
    
    /*     * *********************Méthodes d'instance************************* */
    
    public function preInsert()
    {
    }
    
    public function postInsert()
    {
    }
    
    public function preSave()
    {
    }
    
    public function postSave()
    {
    }
    
    public function preUpdate()
    {
    }
    
    public function postUpdate()
    {
    }
    
    public function preRemove()
    {
    }
    
    public function postRemove()
    {
    }
    
    public function toHtml($_version = "dashboard")
    {
        $replace = $this->preToHtml($_version);
        $version = jeedom::versionAlias($_version);
        if (!is_array($replace)) {
            return $replace;
        }
        
        $deviceType = $this->getConfiguration("deviceType");
        
        // valve
        if ($deviceType == "128") {
            $temperature = $this->getCmd(null, "Temperature");
            $replace["#temperature#"] = is_object($temperature) ? $temperature->execCmd() : "";
            
            $wantedTemperature = $this->getCmd(null, "WantedTemperature");
            $replace["#wantedTemperature#"] = is_object($wantedTemperature) ? $wantedTemperature->execCmd() : "";
            
            $setTemperature = $this->getCmd(null, "ChangeTemperature");
            $replace["#SetTemperature_id#"] = is_object($setTemperature) ? $setTemperature->getId() : "";

            $until = $this->getCmd(null, "Until");
            $replace["#until#"] = is_object($until) ? $until->execCmd() : "";

            $mode = $this->getCmd(null, "Mode");
            $replace["#mode#"] = is_object($mode) ? $mode->execCmd() : "";
            
            return template_replace($replace, getTemplate("core", $version, "valve", "honeywell"));
        }

        // thermostat tablet evohome
        if ($deviceType == "0") {
            $autoCmd = $this->getCmd(null, "Auto");
            $replace["#autoCmdId"] = is_object($autoCmd) ? $autoCmd->getId() : "";

            $customCmd = $this->getCmd(null, "Custom");
            $replace["#customCmdId"] = is_object($customCmd) ? $customCmd->getId() : "";

            $autoWithEcoCmd = $this->getCmd(null, "AutoWithEco");
            $replace["#ecoCmdId"] = is_object($autoWithEcoCmd) ? $autoWithEcoCmd->getId() : "";

            $awayCmd = $this->getCmd(null, "Away");
            $replace["#awayCmdId"] = is_object($awayCmd) ? $awayCmd->getId() : "";

            $dayCmd = $this->getCmd(null, "DayOff");
            $replace["#dayOffCmdId"] = is_object($dayCmd) ? $dayCmd->getId() : "";

            $heatingOffCmd = $this->getCmd(null, "HeatingOff");
            $replace["#heatingOffCmdId"] = is_object($heatingOffCmd) ? $heatingOffCmd->getId() : "";

            return template_replace($replace, getTemplate("core", $version, "station", "honeywell"));
        }
    }
    
    public function SyncDevices()
    {
        $container = DI\ContainerBuilder::buildDevContainer();
        
        $jeedomHelper = $container->get("coolweb\honeywell\JeedomHelper");
        $jeedomHelper->logDebug("Instantiate new objects from DI container.");

        $honeywellManager = $container->get("coolweb\honeywell\HoneywellManager");
        
        $jeedomHelper->logDebug("Honeywell plugin: sync devices...");
        $jeedomHelper->logInfo("Getting devices from honeywell");
        $locations = $honeywellManager->retrieveLocations();
        
        if ($locations == null) {
            throw new Exception("Vérifiez que vous avez mis le bon nom" .
            "d\'utilisateur et mot de passe");
        }
        
        $jeedomHelper->logInfo("Create device into Jeedom");
        $eqLogics = $honeywellManager->createEqLogic($locations);
        $i = 0;
        
        foreach ($locations as $location) {
            foreach ($eqLogics as $eqLogic) {
                if ($eqLogic->getLogicalId() == $location->honeywellId) {
                    break;
                }
            }
            $honeywellManager->createCommandsForLocation($eqLogic, $location);
        
            foreach ($location->valves as $valve) {
                foreach ($eqLogics as $eqLogic) {
                    if ($eqLogic->getLogicalId() == $valve->honeywellId) {
                        break;
                    }
                }
        
                $honeywellManager->createCommandForValve($eqLogic, $valve);
            }
        }
    }
    /*     * **********************Getteur Setteur*************************** */
}

class honeywellCmd extends cmd
{
    /*     * *************************Attributs****************************** */
    
    
    /*     * ***********************Methode static*************************** */
    
    
    /*     * *********************Methode d'instance************************* */
    
    /*
    * Non obligatoire permet de demander de ne pas supprimer
    les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
    public function dontRemoveCmd() {
    return true;
    }
    */
    
    public function execute($_options = array())
    {
        $container = DI\ContainerBuilder::buildDevContainer();
        
        $jeedomHelper = $container->get("coolweb\honeywell\JeedomHelper");
        $honeywellManager = $container->get("coolweb\honeywell\HoneywellManager");
        
        if ($this->getType() == "info") {
            return;
        }
        
        $eqLogic = $this->getEqLogic();
        $honeywellId = $eqLogic->getLogicalId();
        $cmdLogicalId = $this->getLogicalId();
        $cmdValue = $this->getValue();
                        
        $honeywellManager->execCommand($honeywellId, $cmdLogicalId, $_options);
    }
    
    /*     * **********************Getteur Setteur*************************** */
}
