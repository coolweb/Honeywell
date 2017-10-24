<?php
namespace coolweb\honeywell\test;

use PHPUnit\Framework\TestCase;
use coolweb\honeywell as honeywell;
use coolweb\honeywell\apiContract;

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
        'loadPluginConfiguration',
        'SavePluginConfiguration'])
        ->getMock();
    }

    public function testWhenLogonOkItShouldReturnTheResponseData()
    {
        $target = $this->getMockBuilder(\coolweb\honeywell\HoneywellProxyV1::class)
        ->setMethods([
        'doJsonCall'])
        ->setConstructorArgs(array($this->jeedomHelper))
        ->getMock();

        $responseData = new \stdClass();
        @$responseData->access_token = "123";

        $target->method('doJsonCall')
        ->willReturn(array('200', $responseData));
        $result = $target->openSession('xxx', '1234');
        $this->assertEquals($responseData, $result);
    }

    public function testWhenBadUserPasswordOkItShouldReturnNull()
    {
        $target = $this->getMockBuilder(\coolweb\honeywell\HoneywellProxyV1::class)
        ->setMethods([
        'doJsonCall'])
        ->setConstructorArgs(array($this->jeedomHelper))
        ->getMock();

        $target->method('doJsonCall')
        ->willReturn(array('401'));

        $result = $target->openSession('xxx', '1234');
        $this->assertNull($result);
    }

    public function testWhenUnwantedHttpCodeItShouldThrowException()
    {
        $target = $this->getMockBuilder(\coolweb\honeywell\HoneywellProxyV1::class)
        ->setMethods([
        'doJsonCall'])
        ->setConstructorArgs(array($this->jeedomHelper))
        ->getMock();

        $target->method('doJsonCall')
        ->willReturn(array('503'));

        $this->expectException(\Exception::class);
        
        $result = $target->openSession('xxx', '1234');

        $this->assertTrue(false, 'Exception should be throw');
    }
}
