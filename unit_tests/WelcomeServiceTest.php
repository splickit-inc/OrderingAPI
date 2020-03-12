<?php
require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class EmailServiceTest extends PHPUnit_Framework_TestCase {

	private $aws_mock;
	private $welcome_service;

	function testGetExistingTemplate() {
		$mock = $this->getMock("AWSService", array('getKey'));
		$mock->expects($this->any())
				 ->method("getKey")
				 ->with($this->anything(), "exists-welcome-email.html")
				 ->willReturn(Guzzle\Http\EntityBody::factory("here"));
		
		$this->welcome_service = new EmailService($mock);
		
		$template = $this->welcome_service->getWelcomeEmail("seaweed", "exists");
		$this->assertTrue($template == "here");
	}	
	
	function testGetNullOnError() {
		$mock = $this->getMock("AWSService", array('getKey'));
		$mock->expects($this->any())
				 ->method("getKey")
				 ->with($this->anything(), $this->anything())
				 ->willReturn(null);
				
		$ws_mock = $this->getMockBuilder("EmailService")
									  ->setConstructorArgs(array($mock))
									  ->setMethods(array("sendErrorEmail"))
									  ->getMock();
				
		$this->welcome_service = $ws_mock;
		
		$template = $this->welcome_service->getWelcomeEmail("", "");
		$this->assertNull($template);
	}
	
	function testGetUserTemplate() {
	  $mock = $this->getMock("AWSService", array('getKey'));
	  $mock->expects($this->any())
	  ->method("getKey")
	  ->with($this->stringContains('user'), "exists-welcome-email.html")
	  ->willReturn(Guzzle\Http\EntityBody::factory("user here"));
	  
	  $this->welcome_service = new EmailService($mock);
	  
	  $template = $this->welcome_service->getUserWelcomeTemplate("exists");
	  $this->assertTrue($template == "user here");
	}
	
	function testGetMerchantTemplate() {
	  $mock = $this->getMock("AWSService", array('getKey'));
	  $mock->expects($this->any())
	  ->method("getKey")
	  ->with($this->stringContains('merchant'), "exists-welcome-email.html")
	  ->willReturn(Guzzle\Http\EntityBody::factory("merchant here"));
	   
	  $this->welcome_service = new EmailService($mock);
	   
	  $template = $this->welcome_service->getMerchantWelcomeTemplate("exists");
	  $this->assertTrue($template == "merchant here");
	}
}