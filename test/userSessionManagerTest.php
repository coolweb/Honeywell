<?php
use PHPUnit\Framework\TestCase;

include_once('test.inc.php');

class UserSessionManagerTest extends TestCase{
    /** @var JeedomHelper */
    private $jeedomHelper;

    /** @var HoneywellProxy */
    private $honeywellProxy;

    /** @var UserSessionManager */
    private $target;

    protected function setUp()
    {
        $this->jeedomHelper = $this->getMockBuilder(JeedomHelper::class)
        ->setMethods([
        'logDebug',
        'logWarning',
        'logInfo',
        'logError',
        'loadPluginConfiguration',
        'savePluginConfiguration'])
        ->getMock();

        $this->honeywellProxy = $this->getMockBuilder(HoneywellProxy::class)
        ->setMethods([
        'OpenSession'])
        ->disableOriginalConstructor()
        ->getMock();

        $this->target = new UserSessionManager($this->jeedomHelper, $this->honeywellProxy);
    }

    private function SetUsernameAndPassword($username, $password){
        $this->jeedomHelper->method('loadPluginConfiguration')
        ->withConsecutive(
            [$this->equalTo('username')],
            [$this->equalTo('password')]
        )
        ->willReturnOnConsecutiveCalls($username, $password);
    }

    private function SetUsernameAndPasswordAndUserId($username, $password, $userId){
        $this->jeedomHelper->method('loadPluginConfiguration')
        ->withConsecutive(
            [$this->equalTo('username')],
            [$this->equalTo('password')],
            [$this->equalTo('userId')]
        )
        ->willReturnOnConsecutiveCalls($username, $password, $userId);
    }

    private function SetSessionId($session){
        $this->honeywellProxy->method('openSession')
        ->willReturn($session);
    }

    public function testWhenRetrieveSessionIdAndUserAndPasswordExistAndNoUserIDStoredInConfiguration_ItShouldOpenASession(){
        $this->SetUsernameAndPassword('xxx', '1234');
        $sessionId = 'yyyy';
        $session = new Session();
        $session->sessionId = $sessionId;
        $session->userInfo = new UserInfo();
        $session->userInfo->userID = '1234';
        $this->SetSessionId($session);

        $returnSessionId = $this->target->retrieveSessionId();

        $this->assertEquals($sessionId, $returnSessionId);
    }

    public function testWhenRetrieveSessionIdAndUserAndPasswordExistAndUserIDStoredInConfigurationNotSame_ItShouldOpenASessionAndStoreUserID(){
        $this->SetUsernameAndPasswordAndUserId('xxx', '1234', '4321');
        $sessionId = 'yyyy';
        $session = new Session();
        $session->sessionId = $sessionId;
        $session->userInfo = new UserInfo();
        $session->userInfo->userID = '1234';
        $this->SetSessionId($session);

        $this->jeedomHelper->expects($this->once())
        ->method('savePluginConfiguration')
        ->with($this->equalTo('userId'), $this->equalTo('1234'));

        $returnSessionId = $this->target->retrieveSessionId();

        $this->assertEquals($sessionId, $returnSessionId);
    }

    public function testWhenRetrieveSessionIdAndUserNotSet_ItShouldThrowAnError(){
        $this->expectException(Exception::class);

        $this->target->retrieveSessionId();
    }    
}