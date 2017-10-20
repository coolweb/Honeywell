<?php
namespace coolweb\honeywell\apiContract;

class UserInfo
{
    /** @var int */
    public $userID;
    
    /** @var string */
    public $username;
    
    /** @var string */
    public $firstname;
    
    /** @var string */
    public $lastname;
    
    /** @var string */
    public $streetAddress;
    
    /** @var string */
    public $city;
    
    /** @var string */
    public $zipcode;
    
    /** @var string */
    public $country;
    
    /** @var string */
    public $telephone;
    
    /** @var string */
    public $userLanguage;
    
    /** @var bool */
    public $isActivated;
    
    /** @var int */
    public $deviceCount;
    
    /** @var int */
    public $tenantID;
    
    /** @var string */
    public $securityQuestion1;
    
    /** @var string */
    public $securityQuestion2;
    
    /** @var string */
    public $securityQuestion3;
    
    /** @var bool */
    public $latestEulaAccepted;
}
