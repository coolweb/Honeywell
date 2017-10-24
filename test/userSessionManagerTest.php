<?php
namespace coolweb\honeywell\test;

use PHPUnit\Framework\TestCase;
use coolweb\honeywell\UserSessionManager;
use coolweb\honeywell\jeedomHelper;
use coolweb\honeywell\HoneywellProxyV1;
use coolweb\honeywell\apiContract\Session;
use coolweb\honeywell\apiContract\UserInfo;

class UserSessionManagerTest extends TestCase
{
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

        $this->honeywellProxy = $this->getMockBuilder(HoneywellProxyV1::class)
        ->setMethods([
        "openSession",
        "retrieveUser"])
        ->disableOriginalConstructor()
        ->getMock();

        $this->target = $this->getMockBuilder(UserSessionManager::class)        
        ->setConstructorArgs([$this->jeedomHelper, $this->honeywellProxy])        
        ->setMethods([
            "retrieveUser"
        ])
        ->getMock();
    }

    private function setUsernameAndPassword($username, $password)
    {
        $this->jeedomHelper->method('loadPluginConfiguration')
        ->withConsecutive(
            [$this->equalTo('username')],
            [$this->equalTo('password')]
        )
        ->willReturnOnConsecutiveCalls($username, $password);
    }

    private function setUsernameAndPasswordAndUserId($username, $password, $userId)
    {
        $this->jeedomHelper->method('loadPluginConfiguration')
        ->withConsecutive(
            [$this->equalTo('username')],
            [$this->equalTo('password')],
            [$this->equalTo('userId')]
        )
        ->willReturnOnConsecutiveCalls($username, $password, $userId);
    }

    private function setSessionId($session)
    {
        $this->honeywellProxy->method('openSession')
        ->willReturn($session);
    }

    private function setUserId($userId){
        $userInfo = new \stdClass();
        @$userInfo->userId = $userId;
        $this->honeywellProxy->method('retrieveUser')
        ->willReturn($userInfo);
    }

    public function testWhenRetrieveSessionIdAndUserAndPasswordExistAndNoUserIDStoredInConfItShouldOpenASession()
    {
        $this->setUsernameAndPassword("xxx", "1234", "1234");
        $sessionId = "yyyy";
        $session = new Session();
        $session->access_token = $sessionId;
        $session->userInfo = new UserInfo();
        $session->userInfo->userID = "1234";
        $this->setSessionId($session);
        $this->setUserId("1234");

        $returnSessionId = $this->target->retrieveSessionId();

        $this->assertEquals($sessionId, $returnSessionId);
    }

    public function testWhenRetrieveSessionIdAndUserAndPwdExistAndUserIDStoredInConfNotSameItShouldOpenASessionAndStoreUserID()
    {
        $this->setUsernameAndPasswordAndUserId('xxx', '1234', "4321");
        $sessionId = 'yyyy';
        $session = new Session();
        $session->access_token = $sessionId;
        $this->setSessionId($session);
        $this->setUserId("456");
        
        $this->jeedomHelper->expects($this->once())
        ->method('savePluginConfiguration')
        ->with($this->equalTo('userId'), $this->equalTo("456"));

        $returnSessionId = $this->target->retrieveSessionId();

        $this->assertEquals($sessionId, $returnSessionId);
    }

    public function testWhenRetrieveSessionIdAndUserNotSetItShouldThrowAnError()
    {
        $this->expectException(\Exception::class);

        $this->target->retrieveSessionId();
    }
}
