<?php
use PHPUnit\Framework\TestCase;

include_once('test.inc.php');

class HoneywellManagerTest extends TestCase{
    /** @var UserSessionManager */
    private $userSessionManager;

    /** @var HoneywellProxy */
    private $honeylwellProxy;

    /** @var JeedomHelper */
    private $jeedomHelper;

    /** @var HoneywellManager */
    private $target;

    protected function setUp(){
        $this->userSessionManager = $this->getMockBuilder(UserSessionManager::class)
        ->setMethods([
        'RetrieveSessionId',
        'RetrieveUserIdInConfiguration'])
        ->disableOriginalConstructor()
        ->getMock();

        $this->honeylwellProxy = $this->getMockBuilder(HoneywellProxy::class)
        ->setMethods([
        'RetrieveLocations'])
        ->disableOriginalConstructor()
        ->getMock();

        $this->jeedomHelper = $this->getMockBuilder(JeedomHelper::class)
        ->setMethods([
        'logDebug',
        'logWarning',
        'logInfo',
        'logError',
        'LoadPluginConfiguration',
        'SavePluginConfiguration',
        'getEqLogicByLogicalId',
        'CreateAndSaveEqLogic',
        'CreateCmd'])
        ->getMock();

        $this->target = new HoneywellManager(
            $this->honeylwellProxy, 
            $this->userSessionManager,
            $this->jeedomHelper);
    }

    private function SetSessionId($sessionId){
        $this->userSessionManager
        ->method('RetrieveSessionId')
        ->willReturn($sessionId);        
    }

    private function SetLocations($locations)
    {
        $this->honeylwellProxy
        ->method('RetrieveLocations')
        ->willReturn($locations);
    }

    private function SetLogicalIdInJeedom($logicalId, $logicalId2 = null)
    {
        if(isset($logicalId2)){
            $this->jeedomHelper
            ->method('getEqLogicByLogicalId')
            ->withConsecutive([$this->equalTo($logicalId)], [$this->equalTo($logicalId2)])
            ->willReturnOnConsecutiveCalls(new stdClass(), new stdClass());
        } else {
            $this->jeedomHelper
            ->method('getEqLogicByLogicalId')
            ->with($this->equalTo($logicalId))
            ->willReturn(new stdClass());
        }
    }

    public function testWhenRetrieveLocationsAndRetrieveSessionIdReturnNull_ItShouldReturnNull()
    {
        $this->SetSessionId(null);
        $this->SetLocations(array());

        $result = $this->target->RetrieveLocations();

        $this->assertNull($result);
    }

    public function testWhenRetrieveLocationsAndOneValve_ItShouldReturnLocationsWithValve()
    {
        $loc1 = new Location();
        $loc1->name = 'house';
        $loc1->locationID = '123';
        $valve1 = new Device();
        $valve1->name = 'kitchen';
        $valve1->deviceID = 666;
        $valve1->deviceType = 128;
        $valve1->thermostat = new Thermostat();
        $valve1->thermostat->indoorTemperature = 12.2;
        $valve1->thermostat->changeableValues = new stdClass();
        @$valve1->thermostat->changeableValues->heatSetpoint = new stdClass();
        @$valve1->thermostat->changeableValues->heatSetpoint->value = 15;

        array_push($loc1->devices, $valve1);
        $otherDevice = new Device();
        $otherDevice->deviceType = 1;
        array_push($loc1->devices, $otherDevice);

        $locations = array($loc1);

        $this->SetSessionId('1234');
        $this->SetLocations($locations);

        $result = $this->target->RetrieveLocations();
        
        $this->assertEquals(1, sizeof($result));
        $this->assertEquals(1, sizeof($result[0]->valves));
    }

    public function testCreateEqLogicWhenDeviceExistsInJeedom_ItShouldDoNothing()
    {
        $logicalId = '123';
        $logicalId2 = '456';
        $this->SetLogicalIdInJeedom($logicalId, $logicalId2);
        
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
        ->method('CreateAndSaveEqLogic');

        $this->target->CreateEqLogic($locations);        
    }

    public function testCreateEqLogicWhenDeviceNotExistsInJeedom_ItShouldCreateEqLogic()
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
        ->method('CreateAndSaveEqLogic');

        $this->target->CreateEqLogic($locations);        
    }

    public function testCreateCommandForValve_ShouldCreateCommands(){
        $eqLogic = new stdClass();
        $valve = new JeedomThermostaticValve();
        $valve->name = "test";

        $this->jeedomHelper
        ->expects($this->exactly(5))
        ->method('CreateCmd');

        $this->target->CreateCommandForValve($eqLogic, $valve);        
    }

    public function testCreateCommandsForLocation_ShouldCreateCommands(){
        $eqLogic = new stdClass();
        $location = new JeedomLocation();
        $location->name = 'test';

        $this->jeedomHelper
        ->expects($this->exactly(6))
        ->method('CreateCmd');

        $this->target->createCommandsForLocation($eqLogic, $location);
    }

    public function testTemperatureUpWhen19_3ItShouldSet19_5(){
        $result = $this->target->TemperatureUp("xxx", 19.3);
        $this->assertEquals(19.5, $result);
    }

    public function testTemperatureUpWhen19_5ItShouldSet20(){
        $result = $this->target->TemperatureUp("xxx", 19.5);
        $this->assertEquals(20, $result);
    }

    public function testTemperatureUpWhen19_6ItShouldSet20(){
        $result = $this->target->TemperatureUp("xxx", 19.6);
        $this->assertEquals(20, $result);
    }

    public function testTemperatureDownWhen19_3ItShouldSet19(){
        $result = $this->target->TemperatureDown("xxx", 19.3);
        $this->assertEquals(19, $result);
    }

    public function testTemperatureDownWhen19_6ItShouldSet19_5(){
        $result = $this->target->TemperatureDown("xxx", 19.6);
        $this->assertEquals(19.5, $result);
    }

    public function testTemperatureDownWhen19_5ItShouldSet19(){
        $result = $this->target->TemperatureDown("xxx", 19.5);
        $this->assertEquals(19, $result);
    }
}