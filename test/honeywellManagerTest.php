<?php
namespace coolweb\honeywell\test;

use PHPUnit\Framework\TestCase;
use coolweb\honeywell\HoneywellManager;
use coolweb\honeywell\HoneywellProxyV1;
use coolweb\honeywell\userSessionManager;
use coolweb\honeywell\jeedomHelper;
use coolweb\honeywell\JeedomLocation;
use coolweb\honeywell\JeedomThermostaticValve;
use coolweb\honeywell\apiContract\Location;
use coolweb\honeywell\apiContract\Zone;
use coolweb\honeywell\apiContract\Gateway;
use coolweb\honeywell\apiContract\TemperatureControlSystemStatus;
use coolweb\honeywell\apiContract\TemperatureControlSystem;
use coolweb\honeywell\apiContract\TemperatureStatus;
use coolweb\honeywell\apiContract\TemperatureModeStatus;
use coolweb\honeywell\apiContract\HeatSetpointStatus;

class HoneywellManagerTest extends TestCase
{
    /** @var UserSessionManager */
    private $userSessionManager;

    /** @var HoneywellProxy */
    private $honeylwellProxy;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $jeedomHelper;

    /** @var HoneywellManager */
    private $target;

    protected function setUp()
    {
        $this->userSessionManager = $this->getMockBuilder(UserSessionManager::class)
        ->setMethods([
        'retrieveSessionId',
        'retrieveUserIdInConfiguration'])
        ->disableOriginalConstructor()
        ->getMock();

        $this->honeylwellProxy = $this->getMockBuilder(HoneywellProxyV1::class)
        ->setMethods([
        "retrieveLocations",
        "retrieveZones",
        "retrieveTemperatureSystemStatus"])
        ->disableOriginalConstructor()
        ->getMock();

        $this->jeedomHelper = $this->getMockBuilder(JeedomHelper::class)
        ->setMethods([
        'logDebug',
        'logWarning',
        'logInfo',
        'logError',
        'loadPluginConfiguration',
        'savePluginConfiguration',
        'getEqLogicByLogicalId',
        'createAndSaveEqLogic',
        'createCmd'])
        ->getMock();

        $this->target = new HoneywellManager(
            $this->honeylwellProxy,
            $this->userSessionManager,
            $this->jeedomHelper
        );
    }

    private function setLocationIdInConfiguration($locationId)
    {
        $this->jeedomHelper
        ->method("loadPluginConfiguration")
        ->willReturn($locationId);
    }

    private function setSessionId($sessionId)
    {
        $this->userSessionManager
        ->method('retrieveSessionId')
        ->willReturn($sessionId);
    }

    private function setLocations($locations)
    {
        $this->honeylwellProxy
        ->method('retrieveLocations')
        ->willReturn($locations);
    }
    
    private function setZones($zones)
    {
        $this->honeylwellProxy
        ->method("retrieveZones")
        ->willReturn($zones);
    }

    private function setTemperatureSystemStatus($tempSysStatus)
    {
        $this->honeylwellProxy
        ->method("retrieveTemperatureSystemStatus")
        ->willReturn($tempSysStatus);
    }

    private function setLogicalIdInJeedom($logicalId, $logicalId2 = null)
    {
        if (isset($logicalId2)) {
            $this->jeedomHelper
            ->method('getEqLogicByLogicalId')
            ->withConsecutive([$this->equalTo($logicalId)], [$this->equalTo($logicalId2)])
            ->willReturnOnConsecutiveCalls(new \stdClass(), new \stdClass());
        } else {
            $this->jeedomHelper
            ->method('getEqLogicByLogicalId')
            ->with($this->equalTo($logicalId))
            ->willReturn(new \stdClass());
        }
    }

    public function testWhenRetrieveLocationsAndRetrieveSessionIdReturnNullItShouldReturnNull()
    {
        $this->setSessionId(null);
        $this->setLocations(array());

        $result = $this->target->RetrieveLocations();

        $this->assertNull($result);
    }

    public function testWhenRetrieveTempSystemAndOneValveItShouldReturnSystemWithValve()
    {
        $sys = new TemperatureControlSystemStatus();
        $sys->systemId = 123;
        $valve1 = new Zone();
        $valve1->name = 'kitchen';
        $valve1->zoneId = 666;
        $valve1->modelType = 'HeatingZone';
        $tempStatus = new TemperatureStatus();
        $tempStatus->temperature = 12;
        $valve1->temperatureStatus = $tempStatus;
        $heatSetpoint = new HeatSetpointStatus();
        $heatSetpoint->targetTemperature = 15;
        $valve1->heatSetpointStatus = $heatSetpoint;
    
        $sysMode = new TemperatureModeStatus();
        $sysMode->isPermanent = false;
        $sysMode->mode = "Away";
        $sys->systemModeStatus = $sysMode;

        array_push($sys->zones, $valve1);

        $this->setSessionId('1234');
        $this->setTemperatureSystemStatus($sys);

        $result = $this->target->retrieveTemperatureSystem();
        
        $this->assertEquals(1, sizeof($result->valves));
        $this->assertEquals("Away", $result->mode);
    }

    public function testWhenRetrieveLocationsAndOneValveItShouldReturnLocationsWithValveAndSaveLocationId()
    {
        $loc1 = new Location();
        $loc1->locationInfo->name = 'house';
        $loc1->locationInfo->locationId = '123';
        $valve1 = new Zone();
        $valve1->name = 'kitchen';
        $valve1->zoneId = 666;
        $valve1->modelType = 'HeatingZone';
        $temperatureSys = new TemperatureControlSystem();
        $temperatureSys->systemId = 999;
        $gateway = new Gateway();

        array_push($loc1->gateways, $gateway);
        array_push($gateway->temperatureControlSystems, $temperatureSys);
        array_push($temperatureSys->zones, $valve1);

        $otherDevice = new Zone();
        $otherDevice->modelType = 'unknow';
        array_push($temperatureSys->zones, $otherDevice);

        $locations = array($loc1);

        $this->setSessionId('1234');
        $this->setLocations($locations);

        $this->jeedomHelper
        ->expects($this->exactly(2))
        ->method('savePluginConfiguration')
        ->withConsecutive(
            [$this->equalTo('locationId'), $this->equalTo('123')],
            [$this->equalTo('systemId'), $this->equalTo(999)]
        );

        $result = $this->target->retrieveLocations();
        
        $this->assertEquals(1, sizeof($result));
        $this->assertEquals(1, sizeof($result[0]->valves));
    }

    public function testCreateEqLogicWhenDeviceExistsInJeedomItShouldDoNothing()
    {
        $logicalId = '123';
        $logicalId2 = '456';
        $this->setLogicalIdInJeedom($logicalId, $logicalId2);
        
        $loc1 = new JeedomLocation();
        $loc1->name = 'house';
        $loc1->honeywellId = $logicalId;
        $valve1 = new JeedomThermostaticValve();
        $valve1->name = 'kitchen';
        $valve1->honeywellId = $logicalId2;
        array_push($loc1->valves, $valve1);
        $locations = array($loc1);

        $this->jeedomHelper
        ->expects($this->never())
        ->method('createAndSaveEqLogic');

        $this->target->createEqLogic($locations);
    }

    public function testCreateEqLogicWhenDeviceNotExistsInJeedomItShouldCreateEqLogic()
    {
        $logicalId = '123';
        
        $loc1 = new JeedomLocation();
        $loc1->name = 'house';
        $valve1 = new JeedomThermostaticValve();
        $valve1->name = 'kitchen';
        $valve1->honeywellId = $logicalId;
        array_push($loc1->valves, $valve1);
        $locations = array($loc1);

        $this->jeedomHelper
        ->expects($this->exactly(2))
        ->method('createAndSaveEqLogic');

        $this->target->createEqLogic($locations);
    }

    public function testCreateCommandForValveShouldCreateCommands()
    {
        $eqLogic = new \stdClass();
        $valve = new JeedomThermostaticValve();
        $valve->name = "test";

        $this->jeedomHelper
        ->expects($this->exactly(5))
        ->method('createCmd');

        $this->target->CreateCommandForValve($eqLogic, $valve);
    }

    public function testCreateCommandsForLocationShouldCreateCommands()
    {
        $eqLogic = new \stdClass();
        $location = new JeedomLocation();
        $location->name = 'test';

        $this->jeedomHelper
        ->expects($this->exactly(6))
        ->method('createCmd');

        $this->target->createCommandsForLocation($eqLogic, $location);
    }

    public function testTemperatureUpWhen193ItShouldSet195()
    {
        $result = $this->target->temperatureUp("xxx", 19.3);
        $this->assertEquals(19.5, $result);
    }

    public function testTemperatureUpWhen195ItShouldSet20()
    {
        $result = $this->target->temperatureUp("xxx", 19.5);
        $this->assertEquals(20, $result);
    }

    public function testTemperatureUpWhen196ItShouldSet20()
    {
        $result = $this->target->temperatureUp("xxx", 19.6);
        $this->assertEquals(20, $result);
    }

    public function testTemperatureDownWhen193ItShouldSet19()
    {
        $result = $this->target->temperatureDown("xxx", 19.3);
        $this->assertEquals(19, $result);
    }

    public function testTemperatureDownWhen196ItShouldSet195()
    {
        $result = $this->target->temperatureDown("xxx", 19.6);
        $this->assertEquals(19.5, $result);
    }

    public function testTemperatureDownWhen195ItShouldSet19()
    {
        $result = $this->target->temperatureDown("xxx", 19.5);
        $this->assertEquals(19, $result);
    }

    public function testRetrieveValvesWhenRetrieveShouldReturnZones()
    {
        $zone1 = new Zone();
        $zone1->zoneId = 1;
        $tempStatus = new TemperatureStatus();
        $tempStatus->temperature = 19;
        $zone1->temperatureStatus = $tempStatus;
        $heatSetpoint = new HeatSetpointStatus();
        $heatSetpoint->targetTemperature = 20;
        $zone1->heatSetpointStatus = $heatSetpoint;

        $this->setSessionId('1234');
        
        $this->setZones([$zone1]);
        $this->setLocationIdInConfiguration("1234");

        $result = $this->target->retrieveValves();

        $this->assertEquals(1, \sizeof($result));
    }

    public function testWhenRetrieveValvesNoLocationIdInConfigItShouldThrowAnException()
    {
        $this->setSessionId('1234');
        
        $this->setZones([]);

        $this->expectException(\Exception::class);

        $result = $this->target->retrieveValves();
    }
}
