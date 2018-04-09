<?php
namespace coolweb\honeywell;

class UserSessionManager
{
    /** @var JeedomHelper */
    private $jeedomHelper;

    /** @var HoneywellProxyV1 */
    private $honeywellProxy;

    /** @var DateTime */
    public static $sessionValidity = "0";

    /** @var String */
    public static $authToken;

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
    public function retrieveSessionId()
    {
        $user = $this->jeedomHelper->loadPluginConfiguration("username");
        $password = $this->jeedomHelper->loadPluginConfiguration("password");
        $userId = $this->jeedomHelper->loadPluginConfiguration("userId");

        if ($user == null || $password == null) {
            $message = "User or password not found in configuration plugin";
            $this->jeedomHelper->logError($message);
            throw new \Exception($message);
        }

        $sessionValidity = \coolweb\honeywell\UserSessionManager::$sessionValidity;

        if (time() > intval($sessionValidity)) {
            $this->jeedomHelper->logDebug("Session expired, get new token.");

            $sessionResponse = $this->honeywellProxy->openSession($user, $password);
            $token = $sessionResponse->access_token;
            if (is_string($token)) {
                $userInfo = $this->honeywellProxy->retrieveUser($token);

                if ($userInfo->userId !== $userId) {
                    $this->jeedomHelper->logDebug("New user id stored: " . $userInfo->userId);
                    $this->jeedomHelper->savePluginConfiguration("userId", $userInfo->userId);
                }

                \coolweb\honeywell\UserSessionManager::$authToken = $sessionResponse->access_token;
                \coolweb\honeywell\UserSessionManager::$sessionValidity = time() + (15 * 60);
            
                return $sessionResponse->access_token;
            }
        } else {
            $this->jeedomHelper->logDebug("Session valid, use token in cache.");
            $tokenInCache = \coolweb\honeywell\UserSessionManager::$authToken;

            return $tokenInCache;
        }
    }

    /**
     * Retrieve the user id stored in configuration
     * @return string The user id
     */
    public function retrieveUserIdInConfiguration()
    {
        return $this->jeedomHelper->loadPluginConfiguration("userId");
    }
}
