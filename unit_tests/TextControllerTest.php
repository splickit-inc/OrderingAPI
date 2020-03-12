<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class TextControllerTest extends PHPUnit_Framework_TestCase
{
	var $text_controller;
	var $ids;
	
	function setUp()
	{
		$this->text_controller = new TextController($mt, $u, $r,5);
		setProperty('use_primary_sms', 'true');
		$this->ids = $_SERVER['unit_test_ids'];
		
	}
	
	function tearDown() 
	{
       // delete your instance
       //$this->text_controller->failAllMonitorsSMSs
       
       unset($this->text_controller);
       unset($this->ids);
       setProperty('use_primary_sms', 'true');
	}
    
    private function createHeartbeatMessage($diff)
    {
    	// create the heart beat record in teh db;
    	$message_text = time()-$diff;
		$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
		$mmh_data['merchant_id'] = '0';
		$mmh_data['message_format'] = 'TM';
		$mmh_data['message_delivery_addr'] = '1234567890';
		$mmh_data['next_message_dt_tm'] = $message_text;
		$sent_dt_tm = date('Y-m-d H:i:s',$message_text);
		$mmh_data['sent_dt_tm'] = $sent_dt_tm;
		$mmh_data['locked'] = 'S';
		$mmh_data['viewed'] = 'N';
		$mmh_data['message_type'] = 'A';
		$mmh_data['info'] = "monitoring message sent with SMSSender object.  This is just a record";
		
		$mmh_data['message_text'] = ''.$message_text;
		$mmh_resource = Resource::factory($mmh_adapter,$mmh_data);
		$mmh_resource->save();
		return true;
    }

	function testTwillioSend()
	{
		setProperty('test_sms_messages_on','false');
		$result = SmsSender2::send_with_twilio(getProperty("test_sms_number"),"twillio message");
		$this->assertNotNull($result['response_id']);
		$this->assertEquals("queued",$result['response_text']);
		$this->assertEquals(2,count($result),"Should have been 2 fields returned");
	}

    function testCreateNewActivationTextFromGPRSmessage()
    {
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$gprs_message_resource = $mmha->createMessageReturnResource($this->ids['merchant_id'], 1000, "GUC", "1234567890", time()-300, "X", "Firmware=8.0", $message_text);
    	$this->assertEquals(0, $gprs_message_resource->tries);
    	$result = $this->text_controller->stageNewGPRSactiviationMessage($gprs_message_resource);
    	$this->assertEquals(true, $result);
    	$this->assertEquals(1, $gprs_message_resource->tries);
    }

    function testResendActivationText()
    {
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$map_id = $mmha->createMessage(0, $order_id, 'T', '3038844083', time()-10, 'A', $info, '***','S',time(),1);
    	$message_resource = Resource::find($mmha,"$map_id");
    	$this->assertEquals('S', $message_resource->locked);
    	$text_controller = new TextController($mt, $u, $r);
    	$result = $text_controller->restageGPRSactiviationAndAlertSupport($message_resource);
    	$message_resource2 = Resource::find($mmha,"$map_id");
    	$this->assertEquals('N', $message_resource2->locked);
    	$this->assertEquals("0000-00-00 00:00:00", $message_resource2->sent_dt_tm);
    }
    
    function testRecentProviderSwitch()
    {
    	$time = time()-100;
    	$sql = "UPDATE Property SET modified = $time WHERE name='use_primary_sms'";
    	$property_adapter = new PropertyAdapter($mimetypes);
    	$property_adapter->_query($sql);
    	
    	$result = TextController::checkRecentProviderResetWithinThisManySeconds(60);
    	$this->assertFalse($result);
    	$this->text_controller->switchProviders();
    	$result = TextController::checkRecentProviderResetWithinThisManySeconds(60);
    	$this->assertTrue($result);

    }
    
    function testCreateHeartBeatMessage()
    {
    	$text_controller = $this->text_controller;
    	$one_minute_ago = time() - 60;
    	$id = $text_controller->sendMonitorTextToHQPrinter();
    	$this->assertTrue($id > 1000,"a record of the SMS send shoudl have been created");
    	$mr = Resource::find(new MerchantMessageHistoryAdapter($mimetypes),"$id");
    	$this->assertNotNull($mr);
    	$this->assertEquals('N', $mr->viewed);
    	return $id;
    }
    
    /**
     * 
     * @depends testCreateHeartBeatMessage
     */
    function testCheckforlate($id) 
    {
    	$text_controller = $this->text_controller;
    	$message_resource = $text_controller->getLastUnreturnedHeartbeat();
    	$this->assertNotNull($message_resource,"should have found a late message");
    	$return = $text_controller->shouldWeWaitABitLongerForThisHeartbeatMessageToReturn($message_resource);
    	$this->assertTrue($return,"message should not have been late enought to trigger the reset");
    	
    	//now make is late enought
    	$message_resource->message_text = time()-320;
    	$message_resource->save();
    	
    	$message_resource = $text_controller->getLastUnreturnedHeartbeat();
    	$this->assertNotNull($message_resource,"should have found a late message");
    	$return = $text_controller->shouldWeWaitABitLongerForThisHeartbeatMessageToReturn($message_resource);
    	$this->assertFalse($return,"message should have been late enought to trigger the reset, but it wasn't");
    	$message_resource = Resource::find(new MerchantMessageHistoryAdapter($mimetypes),"$id");
    	$this->assertEquals('F', $message_resource->viewed,"message should have been set to failed");
    }
    
    function testSwitchProviders()
    {
    	$text_controller = $this->text_controller;
    	$result = $text_controller->switchProviders();
    	$this->assertEquals("setting use_primary_sms=false", $result);
    }
    
    function testHeartBeatVerifyCatergory1()
    {
    	$use_primary = getProperty('use_primary_sms');
    	$this->createHeartbeatMessage(15);
		// now simulate the printer calling in 
		$now = time();
		$return = $this->text_controller->verifySMSSpeedNoTextBody($now);
    	$this->assertEquals('category1', $return);
    	$this->assertEquals($use_primary,getProperty("use_primary_sms"));
    }
    
    function testHeartBeatVerifyCatergory2()
    {
    	$use_primary = getProperty('use_primary_sms');
    	$this->createHeartbeatMessage(95);
		// now simulate the printer calling in 
		$now = time();
		$return = $this->text_controller->verifySMSSpeedNoTextBody($now);
    	$this->assertEquals('category2', $return);
    	$this->assertEquals($use_primary,getProperty("use_primary_sms"));
    }
    
    function testHeartBeatVerifyCatergory3()
    {
    	$use_primary = getProperty('use_primary_sms');
    	$this->createHeartbeatMessage(145);
		// now simulate the printer calling in 
		$now = time();
		$return = $this->text_controller->verifySMSSpeedNoTextBody($now);
    	$this->assertEquals('category3', $return);
    	$this->assertEquals($use_primary,getProperty("use_primary_sms"));
    }
    
    function testHeartBeatVerifyCatergory4()
    {
    	$use_primary = getProperty('use_primary_sms');
    	$this->createHeartbeatMessage(200);
		// now simulate the printer calling in 
		$now = time();
		$return = $this->text_controller->verifySMSSpeedNoTextBody($now);
    	$this->assertEquals('category4', $return);
    	$this->assertNotEquals($use_primary,getProperty("use_primary_sms"));
    }
    
    function testForLateHeartbeat()
    {
     	$use_primary = getProperty('use_primary_sms');
    	$this->createHeartbeatMessage(179);
		$result = TextController::checkForLateHeartbeatSMS();
    	$this->assertFalse($result,"heart beat should not have been late yet but it was");
    	sleep(1);
    	$this->assertEquals($use_primary,getProperty("use_primary_sms"));
		$result2 = TextController::checkForLateHeartbeatSMS();
    	$this->assertTrue($result2);
    	$this->assertNotEquals($use_primary,getProperty("use_primary_sms"));
    }

   static function setUpBeforeClass()
    {
    	set_time_limit(30);
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;
    	
    	$merchant_resource = createNewTestMerchant($menu_id);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;
    	
    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;
    	
    }
    
	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
    	date_default_timezone_set($_SERVER['starting_tz']);
    }

	/* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    TextControllerTest::main();
}
    
?>