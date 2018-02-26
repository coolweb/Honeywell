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
    private $sessionIdInCache = "";
    private $sessionIdValidity = "";

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

            case 'sessionId':
                $this->sessionIdInCache = $value;
                break;

            case 'sessionIdValidity':
                $this->sessionIdValidity = $value;
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

            case 'sessionIdValidity':
                return $this->sessionIdValidity;

            case 'sessionId':
                return $this->sessionIdInCache;

            default:
                return '';
        }
    }

    public function testWhenRetrieveSessionIdAndUserAndPasswordExistAndNoUserIDStoredInConfItShouldOpenASession()
    {
        $this->user = "xxx";
        $this->password = "1234";
        $this->userIdInCache = "1234";

        $this->sessionId = "yyyy";
        $this->userId = "1234";

        $this->setupContext();

        $returnSessionId = $this->target->retrieveSessionId();

        $this->assertEquals($this->sessionId, $returnSessionId);
        $this->assertEquals($this->sessionIdInCache, $returnSessionId);
    }

    public function testWhenRetrieveSessionIdAndUserAndPwdExistAndUserIDStoredInConfNotSameStoreUserID()
    {
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
        $this->expectException(\Exception::class);

        $this->target->retrieveSessionId();
    }

    public function testWhenNoSessionIdValidityItShouldOpenNewSession()
    {
        $this->user = "456";
        $this->password = "1234";
        $this->userIdInCache = "456";

        $this->sessionId = "yyyy";
        $this->userId  = "456";
        
        $this->setupContext();

        $returnSessionId = $this->target->retrieveSessionId();

        $this->assertEquals($this->sessionId, $returnSessionId);
        $this->assertEquals($this->userIdInCache, "456");
    }

    public function testWhenSessionIdValidityExpiredItShouldOpenNewSession()
    {
        $this->user = "456";
        $this->password = "1234";
        $this->userIdInCache = "456";
        $this->sessionIdInCache = "xxx";

        $this->sessionId = "yyyy";
        $this->userId  = "456";
        $this->sessionIdValidity = time() - 60;
        
        $this->setupContext();

        $returnSessionId = $this->target->retrieveSessionId();

        $this->assertEquals("yyyy", $returnSessionId);
        $this->assertEquals($this->userIdInCache, "456");
    }

    public function testWhenSessionIdValidityIsNotExpiredItShouldReturnSessionIdInCache()
    {
        $this->user = "456";
        $this->password = "1234";
        $this->userIdInCache = "456";
        $this->sessionIdInCache = "xxx";

        $this->sessionId = "yyyy";
        $this->userId  = "456";
        $this->sessionIdValidity = time() + 60;
        
        $this->setupContext();

        $returnSessionId = $this->target->retrieveSessionId();

        $this->assertEquals("xxx", $returnSessionId);
        $this->assertEquals($this->userIdInCache, "456");
    }
}
