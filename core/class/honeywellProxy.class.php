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

class HoneywellProxy
{
    private $honeywellApiUrl = 'https://tccna.honeywell.com/WebAPI/api';
    private $appId = '91db1612-73fd-4500-91b2-e63b069b185c';
    
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
    public function doJsonCall($requestUrl, $data, $method = 'POST', $headers = 'Content-Type: application/json')
    {
        $result = array(null, null);
        $this->jeedomHelper->logDebug('Do http call ' . $method . ' ' . $requestUrl);
        
        $options = array(
            'http' => array(
            'header'  => $headers,
            'method'  => $method,
            'content' => $data,
            'ignore_errors' => true,
            'follow_location' => 0
        ),
            'ssl'=>array(
            'verify_peer'=>false,
            'verify_peer_name'=>false,
        )
        );
       
        $context  = stream_context_create($options);
        $this->jeedomHelper->logDebug('with data: ' . print_r($options, true));
        
        try {
            $json = file_get_contents($requestUrl, false, $context);
            if (isset($http_response_header) && array_key_exists(0, $http_response_header)) {
                $matches = array();
                preg_match('#HTTP/\d+\.\d+ (\d+)#', $http_response_header[0], $matches);
                $result[0] = $matches[1];
            }

            if (is_string($json)) {
                $this->jeedomHelper->logDebug('Status:' . $result[0] . ' Result: ' . $json);
            } else {
                $this->jeedomHelper->logDebug('Http call result ' . $result[0]);
            }

            $jsonObj = json_decode($json);
            $result[1] = $jsonObj;
            
            return $result;
        } catch (Exception $e) {
            if (isset($http_response_header) && array_key_exists(0, $http_response_header)) {
                $matches = array();
                preg_match('#HTTP/\d+\.\d+ (\d+)#', $http_response_header[0], $matches);
                $result[0] = $matches[1];
                return $result;
            } else {
                throw $e;
            }
        }
    }
    
    /**
    * Do a logon to the honeywell api and retrieve the token for futher calls.
    * @param userName The user name to do the logon
    * @param password The password to do the logon
    * @return Session The response of the session request
    */
    public function OpenSession($userName, $password)
    {
        $sessionUrl = $this->honeywellApiUrl . '/Session';
        
        $sessionRequest = new StdClass();
        @$sessionRequest->username = $userName;
        @$sessionRequest->password = $password;
        @$sessionRequest->applicationId = $this->appId;
        
        $result = $this->doJsonCall($sessionUrl, json_encode($sessionRequest));
        
        switch ($result[0]) {
            case '200':
                return $result[1];
                
                case '401':
                    return null;
                    
                    default:
                        throw new Exception('Unexpected http code while doing logon: ' . $result[0]);
                }
    }
            
    /**
    * Retrieve locations with devices attached to it
    * @param sessionId The session id
    * @param userId The id of the user retrive after opening a session
    * @return array of Location
    */
    public function RetrieveLocations($sessionId, $userId)
    {
        $locationsUrl = $this->honeywellApiUrl . '/locations?userId=' . $userId . '&allData=True';
        $header = ['sessionId: '  . $sessionId];
                
        $result = $this->doJsonCall($locationsUrl, null, 'GET', $header);
                
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
    public function ChangeTemperature($sessionId, $deviceId, $temperature, $status = 'Hold', $nextTime = null)
    {
        $temperatureUrl = $this->honeywellApiUrl . '/devices/' .
                $deviceId . '/thermostat/changeableValues/heatSetpoint';
        $header = ['sessionId: ' . $sessionId ,
                            'Content-Type: application/json'];
                
        $data = new stdClass();
        @$data->Value = $temperature;
        @$data->Status = $status;

        if ($nextTime !== null) {
            @$data->NextTime = date_format($nextTime, 'Y-m-d').'T'.date_format($nextTime, 'H:i:s').'Z';
        } else {
            @$data->NextTime = null;
        }
                
        $result = $this->doJsonCall($temperatureUrl, json_encode($data), 'PUT', $header);
        if (is_string($result) === false) {
            $this->jeedomHelper->logWarning('No task id retrieved change temperature no executed');
        } else {
            $taskId = json_decode($result)->id;
            $this->jeedomHelper->logDebug('Task id received:' . $taskId);
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
    public function SetLocationQuickAction($sessionId, $locationId, $action, $nextTime = null)
    {
        $quickActionUrl = $this->honeywellApiUrl . '/evoTouchSystems?locationId='
                . $locationId;
        $header = ['sessionId: ' . $sessionId ,
                'Content-Type: application/json'];
                
        $data = new stdClass();
        @$data->QuickAction = $action;
        @$data->QuickActionNextTime = $nextTime;
                
        $this->doJsonCall($quickActionUrl, json_encode($data), 'PUT', $header);
    }
}
