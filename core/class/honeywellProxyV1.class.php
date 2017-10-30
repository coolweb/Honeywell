<?php
namespace coolweb\honeywell;

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

class HoneywellProxyV1
{
    private $honeywellAuthUrl = "https://tccna.honeywell.com/Auth/OAuth/Token";
    private $honeywellApiUrl = "https://tccna.honeywell.com/WebAPI/emea/api/v1";
    private $appId = "b013aa26-9724-4dbd-8897-048b9aada249";
    private $sessionId = null;
    
    /** @var JeedomHelper */
    private $jeedomHelper;
    
    public function __construct(JeedomHelper $jeedomHelper)
    {
        $this->jeedomHelper = $jeedomHelper;
    }
    
    /**
    * Do a request and get json result.
    * @param $requestUrl string The request url to call
    * @return Array, index 0 is the http code, index 1 is the returned data.
    */
    public function doJsonCall($requestUrl, $data, $method = "POST", $headers = null)
    {
        if (is_null($headers)) {
            if (!empty($this->sessionId)) {
                $headers = ["Authorization: bearer " . $this->sessionId,
                "applicationId: " . $this->appId,
                "Accept: application/json, application/xml, text/json, text/x-json, text/javascript, text/xml",
                "Content-Type: application/json"];
            }
        }
        
        $result = array(null, null);
        $this->jeedomHelper->logDebug("Do http call " . $method . " " . $requestUrl);
        
        $options = array(
        "http" => array(
        "header"  => $headers,
        "method"  => $method,
        "content" => $data,
        "ignore_errors" => true,
        "follow_location" => 0
        ),
        "ssl"=>array(
        "verify_peer"=>false,
        "verify_peer_name"=>false,
        )
        );
        
        $context  = stream_context_create($options);
        $this->jeedomHelper->logDebug("with data: " . print_r($options, true));
        
        try {
            $json = file_get_contents($requestUrl, false, $context);
            if (isset($http_response_header) && array_key_exists(0, $http_response_header)) {
                $matches = array();
                preg_match("#HTTP/\d+\.\d+ (\d+)#", $http_response_header[0], $matches);
                $result[0] = $matches[1];
            }
            
            if (is_string($json)) {
                $this->jeedomHelper->logDebug("Status:" . $result[0] . " Result: " . $json);
            } else {
                $this->jeedomHelper->logDebug("Http call result " . $result[0]);
            }
            
            $jsonObj = json_decode($json);
            $result[1] = $jsonObj;
            
            return $result;
        } catch (\Exception $e) {
            if (isset($http_response_header) && array_key_exists(0, $http_response_header)) {
                $matches = array();
                preg_match("#HTTP/\d+\.\d+ (\d+)#", $http_response_header[0], $matches);
                $result[0] = $matches[1];
                return $result;
            } else {
                throw $e;
            }
        }
    }
    
    public function retrieveUser()
    {
        $userUrl = $this->honeywellApiUrl . "/userAccount";
        
        $result = $this->doJsonCall($userUrl, null, "GET");

        return $result[1];
    }
    
    /**
    * Do a logon to the honeywell api and retrieve the token for futher calls.
    * @param userName The user name to do the logon
    * @param password The password to do the logon
    * @return Session The response of the session request
    */
    public function openSession($userName, $password)
    {
        $sessionUrl = $this->honeywellAuthUrl;
        
        $sessionRequest = new \StdClass();
        @$sessionRequest->Username = $userName;
        @$sessionRequest->Password = $password;
        @$sessionRequest->Connection = "Keep-Alive";
        @$sessionRequest->scope = "EMEA-V1-Basic EMEA-V1-Anonymous EMEA-V1-Get-Current-User-Account";
        @$sessionRequest->{'grant_type'} = "password";
        @$sessionRequest->Pragma = "no-cache";
        @$sessionRequest->{'Cache-Control'} = "no-store no-cache";
        @$sessionReques->{'Content-Type'} = "application/x-www-form-urlencoded; charset=utf-8";
        @$sessionRequest->Host = "rs.alarmnet.com/";
        
        $query = http_build_query($sessionRequest);
        
        $header = ["Authorization: Basic YjAxM2FhMjYtOTcyNC00ZGJkLTg4OTctMDQ4YjlhYWRhMjQ5OnRlc3Q=",
        "Accept: application/json, application/xml, text/json, text/x-json, text/javascript, text/xml",
        "Content-Type: application/x-www-form-urlencoded; charset=utf-8",
        "Content-Length: ".strlen($query)];
        
        $result = $this->doJsonCall($sessionUrl, $query, "POST", $header);
        
        switch ($result[0]) {
            case '200':
                $this->sessionId = $result[1]->access_token;
                return $result[1];
                
            case '401':
                return null;
                
            default:
                throw new \Exception("Unexpected http code while doing logon: " . $result[0]);
        }
    }
            
    /**
    * Retrieve locations with devices attached to it
    * @param sessionId The session id
    * @param userId The id of the user retrive after opening a session
    * @return array of Location
    */
    public function retrieveLocations($userId)
    {
        $locationsUrl = $this->honeywellApiUrl .
                "/location/installationInfo?userId=" . $userId . "&includeTemperatureControlSystems=true";
                
        $result = $this->doJsonCall($locationsUrl, null, "GET");
                
        return $result[1];
    }

    /**
     * Retrieve all zones for a location
     *
     * @param [string] $locationId The identifier of the location
     * @return Array of zones
     */
    public function retrieveZones($locationId)
    {
        $zoneUrl = $this->honeywellApiUrl . "/temperatureZone/status?locationId=" . $locationId;

        $result = $this->doJsonCall($zoneUrl, null, "GET");
                
        return $result[1];
    }

    /**
     * Retrieve temperature system status, system mode with all devices
     * Read system id in plugin configuration
     *
     * @return \coolweb\honeywell\apiContract\TemperatureControlSystemStatus
     */
    public function retrieveTemperatureSystemStatus()
    {
        $systemId = $this->jeedomHelper->loadPluginConfiguration("systemId");
        if (empty($systemId)) {
            $errorMsg = "No system id in plugin configuration, unable to set location quick action";
            $this->jeedomHelper->logError($errorMsg);
            throw new \Exception($errorMsg);
        }

        $temperatureSystemUrl = $this->honeywellApiUrl . "/temperatureControlSystem/"
        . $systemId . "/status";

        $result = $this->doJsonCall($temperatureSystemUrl, null, "GET");

        return $result[1];
    }
            
    /**
    * Change temperature for a valve
    *
    * @param string $sessionId The id of the session
    * @param number $deviceId The id of the valve
    * @param number $temperature The temperature to set
    * @param string $status Hold | Temporary | Scheduled
    * @param date $nextTime To which time to change the temperature
    */
    public function changeTemperature($sessionId, $deviceId, $temperature, $status = "Hold", $nextTime = null)
    {
        $this->sessionId = $sessionId;

        $temperatureUrl = $this->honeywellApiUrl . "/temperatureZone/" .
                $deviceId . "/heatSetpoint";
                
        $data = new \stdClass();
        @$data->heatSetpointValue = $temperature;

        switch ($status) {
            case "Hold":
                @$data->setpointMode = "PermanentOverride";
                break;

            case "Temporary":
                @$data->setpointMode = "TemporaryOverride";
                break;

            case "Scheduled":
                @$data->setpointMode = "FollowSchedule";
                break;
            
            default:
                $this->jeedomHelper->logWarning("Change temperature received with unknow status:" . $status);
                $this->jeedomHelper->logWarning("Set status to permanent by default");
                @$data->setpointMode = "PermanentOverride";
                break;
        }
                
        if ($nextTime !== null) {
            @$data->timeUntil = date_format($nextTime, "Y-m-d")."T".date_format($nextTime, "H:i:s")."Z";
        } else {
            @$data->timeUntil = null;
        }
                
        $result = $this->doJsonCall($temperatureUrl, json_encode($data), "PUT");
        if (is_string($result) === false) {
            $this->jeedomHelper->logWarning("No task id retrieved change temperature no executed");
        } else {
            $taskId = json_decode($result)->id;
            $this->jeedomHelper->logDebug("Task id received:" . $taskId);
        }
    }
            
    /**
    * Set a location quick action
    *
    * @param string $sessionId The id of the session
    * @param string $locationId The id of the location where to set the quick action
    * @param string $action The quick action, values: Auto - Custom - AutoWithEco - Away - DayOff - HeatingOff
    * @param date $nextTime To which time to set the quick action
    */
    public function setLocationQuickAction($sessionId, $locationId, $action, $nextTime = null)
    {
        $systemId = $this->jeedomHelper->loadPluginConfiguration("systemId");
        if (empty($systemId)) {
            $errorMsg = "No system id in plugin configuration, unable to set location quick action";
            $this->jeedomHelper->logError($errorMsg);
            throw new \Exception($errorMsg);
        }

        $quickActionUrl = $this->honeywellApiUrl . "/temperatureControlSystem/"
                . $systemId . "/mode";
                
        $data = new \stdClass();
        @$data->systemMode = $action;

        if ($nextTime !== null) {
            @$data->timeUntil = date_format($nextTime, "Y-m-d")."T".date_format($nextTime, "H:i:s")."Z";
        } else {
            @$data->timeUntil = null;
        }

        @$data->permanent = \is_null($nextTime) ? true : false;
                
        $this->doJsonCall($quickActionUrl, json_encode($data), "PUT");
    }

    /**
     * Retrieve the actual quick action of the system
     *
     * @return void
     */
    public function getLocationQuickAction()
    {
        $systemId = $this->jeedomHelper->loadPluginConfiguration("systemId");
        if (empty($systemId)) {
            $errorMsg = "No system id in plugin configuration, unable to get location quick action";
            $this->jeedomHelper->logError($errorMsg);
            throw new \Exception($errorMsg);
        }

        $quickActionUrl = $this->honeywellApiUrl . "/temperatureControlSystem/"
                . $systemId . "/status";

        $this->doJsonCall($quickActionUrl, null, "GET");

        return $result[1];
    }
}
