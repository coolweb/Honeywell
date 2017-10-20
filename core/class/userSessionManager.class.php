<?php
namespace coolweb\honeywell;

class UserSessionManager
{
    /** @var JeedomHelper */
    private $jeedomHelper;

    /** @var HoneywellProxyV1 */
    private $honeywellProxy;

    /**
     * @param JeedomHelper $jeedomHelper The jeedom helper class
     * @param HoneywellProxyV1 $honeywellProxy The proxy class for honeywell api
     */
    public function __construct(JeedomHelper $jeedomHelper, HoneywellProxyV1 $honeywellProxy)
    {
        $this->jeedomHelper = $jeedomHelper;
        $this->honeywellProxy = $honeywellProxy;
    }

    /**
     * If user and password exists, it'll open a new session on honeywell api
     * and store the user id into the jeedom configuration for the plugin
     * @throws Exception If user or password are not in configuration
     * @return string The session Id, null if bad user/password
     */
    public function RetrieveSessionId()
    {
        $user = $this->jeedomHelper->LoadPluginConfiguration('username');
        $password = $this->jeedomHelper->LoadPluginConfiguration('password');
        $userId = $this->jeedomHelper->LoadPluginConfiguration('userId');

        if ($user == null || $password == null) {
            $message = 'User or password not found in configuration plugin';
            $this->jeedomHelper->logError($message);
            throw new \Exception($message);
        }

        $sessionResponse = $this->honeywellProxy->OpenSession($user, $password);
        $token = $sessionResponse->access_token;
        if (is_string($token)) {
            $userInfo = $this->honeywellProxy->RetrieveUser($token);

            if ($userInfo->userId !== $userId) {
                $this->jeedomHelper->logDebug('New user id stored: ' . $userInfo->userId);
                $this->jeedomHelper->SavePluginConfiguration('userId', $userInfo->userId);
            }
        }
        
        return $sessionResponse->access_token;
    }

    /**
     * Retrieve the user id stored in configuration
     * @return string The user id
     */
    public function RetrieveUserIdInConfiguration()
    {
        return $this->jeedomHelper->LoadPluginConfiguration('userId');
    }
}
