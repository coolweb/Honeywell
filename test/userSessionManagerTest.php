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
    /** @var JeedomHelper  * */
    private $jeedomHelper;

    /** @var HoneywellProxy */
    private $honeywellProxy;

    /** @var UserSessionManager */
    private $target;

    private $user = "";

    private $password = "";

    private $userId = "";
    private $userIdInCache = "";

    private $sessionId = "";

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

    private function setupContext()
    {
        $this->jeedomHelper->method('loadPluginConfiguration')
        ->will($this->returnCallBack(array($this, 'loadPluginConfiguration')));

        $this->jeedomHelper->method('savePluginConfiguration')
        ->will($this->returnCallBack(array($this, 'savePluginConfiguration')));

        $this->honeywellProxy->method('openSession')
        ->will($this->returnCallBack(array($this, 'openSession')));

        $this->honeywellProxy->method('retrieveUser')
        ->will($this->returnCallBack(array($this, 'retrieveUser')));
    }

    public function savePluginConfiguration($key, $value)
    {
        switch ($key) {
            case 'userId':
                $this->userIdInCache = $value;
                break;

            default:
                break;
        }
    }

    public function retrieveUser()
    {
        $userInfo = new \stdClass();
        @$userInfo->userId = $this->userId;

        return $userInfo;
    }

    public function openSession()
    {
        $session = new \stdClass();
        @$session->access_token = $this->sessionId;
        return $session;
    }

    public function loadPluginConfiguration($key)
    {
        switch ($key) {
            case 'username':
                return $this->user;

            case 'password':
                return $this->password;

            case 'userId':
                return $this->userIdInCache;

            default:
                return '';
        }
    }

    public function testWhenRetrieveSessionIdAndUserAndPasswordExistAndNoUserIDStoredInConfItShouldOpenASession()
    {
        UserSessionManager::$sessionValidity = "";

        $this->user = "xxx";
        $this->password = "1234";

        $this->sessionId = "yyyy";
        $this->userId = "1234";

        $this->setupContext();

        $returnSessionId = $this->target->retrieveSessionId();

        $this->assertEquals("yyyy", $returnSessionId);
    }

    public function testWhenRetrieveSessionIdAndUserAndPwdExistAndUserIDStoredInConfNotSameStoreUserID()
    {
        UserSessionManager::$sessionValidity = "";

        $this->user = "xxx";
        $this->password = "1234";
        $this->userIdInCache = "4321";

        $this->sessionId = "yyyy";
        $this->userId  = "456";
        
        $this->setupContext();

        $returnSessionId = $this->target->retrieveSessionId();

        $this->assertEquals($this->sessionId, $returnSessionId);
        $this->assertEquals($this->userIdInCache, "456");
    }

    public function testWhenRetrieveSessionIdAndUserNotSetItShouldThrowAnError()
    {
        UserSessionManager::$sessionValidity = "";
        
        $this->expectException(\Exception::class);

        $this->target->retrieveSessionId();
    }
}
