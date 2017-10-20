<?php
use PHPUnit\Framework\TestCase;
use coolweb\honeywell as honeywell;
use coolweb\honeywell\apiContract;

include_once('test.inc.php');

class HoneywellProxyTest extends TestCase
{
    /** @var JeedomHelper */
    private $jeedomHelper;

    protected function setUp()
    {
        $this->jeedomHelper = $this->getMockBuilder(\coolweb\honeywell\JeedomHelper::class)
        ->setMethods([
        'logDebug',
        'logWarning',
        'logInfo',
        'logError',
        'LoadPluginConfiguration',
        'SavePluginConfiguration'])
        ->getMock();
    }

    public function testWhenLogonOk_ItShouldReturnTheResponseData()
    {
        $target = $this->getMockBuilder(\coolweb\honeywell\HoneywellProxyV1::class)
        ->setMethods([        
        'doJsonCall'])
        ->setConstructorArgs(array($this->jeedomHelper))
        ->getMock();

        $responseData = new stdClass();
        @$responseData->access_token = "123";

        $target->method('doJsonCall')
        ->willReturn(array('200', $responseData));
        $result = $target->OpenSession('xxx', '1234');
        $this->assertEquals($responseData, $result);
    }

    public function testWhenBadUserPasswordOk_ItShouldReturnNull()
    {
        $target = $this->getMockBuilder(\coolweb\honeywell\HoneywellProxyV1::class)
        ->setMethods([        
        'doJsonCall'])
        ->setConstructorArgs(array($this->jeedomHelper))
        ->getMock();

        $target->method('doJsonCall')
        ->willReturn(array('401'));

        $result = $target->OpenSession('xxx', '1234');
        $this->assertNull($result);
    }

    public function testWhenUnwantedHttpCode_ItShouldThrowException()
    {
        $target = $this->getMockBuilder(\coolweb\honeywell\HoneywellProxyV1::class)
        ->setMethods([        
        'doJsonCall'])
        ->setConstructorArgs(array($this->jeedomHelper))
        ->getMock();

        $target->method('doJsonCall')
        ->willReturn(array('503'));

        try {
            $result = $target->OpenSession('xxx', '1234');
        } catch (Exception $e) {
            $this->assertTrue(true);
            return;
        }

        $this->assertTrue(false, 'Exception should be throw');
    }
}
