<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class NewMessageControllerTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $mmha;
	var $ids;

	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->mmha = new MerchantMessageHistoryAdapter($mimetypes);
		$this->ids = $_SERVER['unit_test_ids']; 
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		setSessionProperty('new_shadow_device_on', 'false');
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->mmha);
		unset($this->stamp);
		unset($this->ids);
    }

	function testNextMessageDateTimeForLargeOrder()
	{
		setContext('com.splickit.worldhq');
		$menu_id = createTestMenuWithNnumberOfItems(1);
		$merchant_resource = createNewTestMerchant($menu_id);
		$merchant_id = $merchant_resource->merchant_id;

		$user = logTestUserIn($this->ids['user_id']);

		$order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'pickup','the note',30);
		$json_encoded_data = json_encode($order_data);

		$url = '/app2/apiv2/cart/checkout';
		$request = createRequestObject($url,'post',$json_encoded_data,'application/json');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$checkout_resource = $place_order_controller->processV2Request();
		$cart_ucid = $checkout_resource->ucid;
		$this->assertNull($checkout_resource->error);
		$this->assertNotNull($checkout_resource,"should have gotten a cart resource back");

		$order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$time);
		$this->assertNull($order_resource->error);
		$order_id = $order_resource->order_id;

		$message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'E');
		$next_message_dt_tm = $message_resource->next_message_dt_tm;
		$next_message_string = date("Y-m-d H:i:s",$next_message_dt_tm);
		$pickup_time_string = date("Y-m-d H:i:s",$order_resource->pickup_dt_tm);
		$message_lead_time = $order_resource->pickup_dt_tm - $next_message_dt_tm;

		$diff = $next_message_dt_tm - getTomorrowTwelveNoonTimeStampDenver();
		$minutes = $diff/60;
		$this->assertTrue($diff >= 0 && $diff < 60,"It should schedule the execution message within one minute of the current time for a large order that picks first available time. actualy scheduled at $minutes minutes in the future");
//		$this->assertEquals(2100,$message_lead_time,"it shoudl have a lead time equal to 2100 seconds");
	}

	function testResendOfXoikosMessageSettingMessageTextToNull()
	{
		$user = logTestUserIn($this->ids['user_id']);
		$order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($this->ids['merchant_id'],'pickup','skip hours',1);
		$order_resource = placeOrderFromOrderData($order_data);
		$order_id = $order_resource->order_id;
		$mmha = new MerchantMessageHistoryAdapter($m);
		$mmh_resource = $mmha->createMessageReturnResource($this->ids['merchant_id'],$order_id,'X','Xoikos',date('Y-m-d H:i:s'),'X','my info','SOME DUMB MESSAGE TEXT','S',date('Y-m-d H:i:s'),1);
		$this->assertEquals('SOME DUMB MESSAGE TEXT',$mmh_resource->message_text);
		$oc = new OrderController($m,$u,$r,5);
		$result = $oc->resendOrder($order_id);
		$new_map_resource = $mmha->getRecord(array("map_id"=>$mmh_resource->map_id));
		$this->assertNull($new_map_resource['message_text']);
	}
    
    function testRecordPullCallIn()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	GprsPrinterCallInHistoryAdapter::recordPullCallIn($merchant_id);
    	
    	$gpciha = new GprsPrinterCallInHistoryAdapter($mimetypes);
    	$record = $gpciha->getRecord(array("merchant_id"=>$merchant_id), $options);
    	$this->assertNotNull($record,"shoujld have found a record");
    	$this->assertTrue($record['last_call_in'] > (time() - 2),"call in recorded time is too far in the past");
    	$this->assertTrue($record['last_call_in'] < (time() + 2),"call in recorded time is too far in the future"); 
    }
    
   function testEmailOrderExecutionFormat()
   {
   		$ids = $this->ids;
    	$merchant_resource = createNewTestMerchant($ids['menu_id']);
    	$user_resource = createNewUserWithCC();
    	$user = logTestUserResourceIn($user_resource);
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_resource->merchant_id, 'pickup', 'skip hours');
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNull($order_resource->error);
    	$order_id = $order_resource->order_id;
    	$email_controller = new EmailController($mt, $u, $r);
    	$message_text = $email_controller->getFormattedMessageTextByOrderIdAndMessageFormat($order_id, 'E');
    	error_log("$message_text");
		$this->assertContains('Store Info', $message_text);
    	
   }

    function testProdTesterSendingOfMessages()
    {
    	$ids = $this->ids;
    	$merchant_resource = createNewTestMerchant($ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	$map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_id,"message_format"=>'FUW',"delivery_addr"=>"1234567890","message_type"=>"O"));
    	//$map_resource2 = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_id,"message_format"=>'E',"delivery_addr"=>"adam@sumdumplace.com","message_type"=>"O"));
    	$map_resource3 = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_id,"message_format"=>'WUE',"delivery_addr"=>"winapp","message_type"=>"O"));
    	
    	setContext("com.splickit.order");
    	$user = logTestUserIn(2);
    	$order_adapter = new OrderAdapter();
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_resource->merchant_id, 'Pickup', 'skip hours');
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNull($order_resource->error);
    	$order_id = $order_resource->order_id;
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$options[TONIC_FIND_BY_METADATA] = array("order_id"=>$order_id);
    	$message_resources = Resource::findAll($mmha, $url, $options);
    	$this->assertCount(0, $message_resources);
    	
    }

    function testFCobNotBeingInterpretedAsFaxCheck()
    {
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$unlocked_message_resource = $mmha->createMessageReturnResource($this->ids['merchant_id'], null, 'FCob', '3038844083', time()-2, 'I', $info, $message_text);
		$message_resource = $mmha->getLockedMessageResourceForSending($unlocked_message_resource);
		$this->assertNotNull($message_resource,"should have found the FCob message resource");			
		$message_controller = ControllerFactory::generateFromMessageResource($message_resource, $mimetypes, $user, $request, 5);
		$result = $message_controller->sendThisMessage($message_resource);
		$this->assertTrue($result);
    }
    
 /*   
    function testGprsSingleMessageFunctionality()
    {
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$sql = "UPDATE Merchant_Message_History SET locked = 'F' where locked = 'N'";
    	$mmha->_query($sql);
    	
    	$map_id = $mmha->createMessage(1234567, null, 'G', 3038844083, time()-10, 'X', 'firmware=7.0', 'This is a test of the emergency system','N');

		if ($message_resources = $mmha->getAvailableMessageResourcesArray($mmha_options))
		{
			myerror_logging(3,"Worker has grabbed ".sizeof($message_resources)." messages ready to be sent.  now try to get a lock on them one at a time to send");
			// we have available messages so we cycle through and try to grab it with a lock, if its unable to grab the mesage goto the next one.
			$sent_messages = 0;
			foreach ($message_resources as $unlocked_message_resource)
			{
				if ($message_resource = $mmha->getLockedMessageResourceForSending($unlocked_message_resource))
				{
					if (! $fixed_controller)
						$message_controller = ControllerFactory::generateFromMessageResource($message_resource, $mimetypes, $user, $request, $log_level);
					
					if ($message_controller)	
						$message_controller->sendThisMessage($message_resource);
					else
					{
						myerror_log("ERROR! Serious problem with message sending message_id: ".$message_resource->map_id.".  No controller matching message type of: ".$message_resource->message_format);
						MailIt::sendErrorEmailSupport("MESSAGE SENDING ERROR!", "Serious problem with message sending message_id: ".$message_resource->map_id.".  No controller matching message type of: ".$message_resource->message_format);						
					}
				}
				$message_resource = null;
			}
			myerror_logging(3,"Worker has finished this set of messages, worker will now try to grab up to 10 more");
		}

    }
    
  */      
    
    function testNewCurlController()
    {
		//$order_id = rand(1111111, 9999999);
		$order_id = rand(1,1000);
		$soap_xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?><soap12:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap12=\"http://www.w3.org/2003/05/soap-envelope\"><soap12:Header><UserCredentials xmlns=\"http://tempuri.org/\"><userName>splickit</userName><password>Pcn9CCCr</password> </UserCredentials></soap12:Header><soap12:Body><AddPoints xmlns=\"http://tempuri.org/\"><MerchantRockCommID>TES92130</MerchantRockCommID><CustomerCardNumber>900011062412</CustomerCardNumber><NumPoints>10.50</NumPoints><CardAction>CardKeyed</CardAction><ClerkID>27</ClerkID><CheckNumber>".$order_id."</CheckNumber><AccountType>CardNumber</AccountType> <SurveyFlag>false</SurveyFlag><responseFormat>json</responseFormat> </AddPoints></soap12:Body></soap12:Envelope>";
		$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
		$mmh_adapter->createMessage($this->ids['merchant_id'], $order_id, 'C', getProperty('fpn_loyalty_url'), time(), 'I', "service=FpnLoyalty", $soap_xml);
		$id = $mmh_adapter->_insertId();
		$this->assertTrue($id > 1000);
		$message_resource = Resource::find($mmh_adapter,"".$id);
		$curl_controller = new CurlController($mt, $u, $r,5);
		$curl_controller->sendThisMessage($message_resource);
		
		$message_resource2 = Resource::find($mmh_adapter,"".$id);
		$response = $message_resource2->response;
		$this->assertNotNull($response,"Should have saved a response");
    } 
    
/* have no idea what this test is supposed to be doing*/    
    function testRecordMessageIntoHistory()
    {
    	$sql = "UPDATE Merchant_Message_History SET locked = 'F' WHERE locked = 'N' AND message_format = 'T'";
    	$this->mmha->_query($sql);
    	$sql = "UPDATE Merchant_Message_History SET locked = 'F' WHERE merchant_id = 1234567 AND message_format = 'T'";
    	$this->mmha->_query($sql);
    	setProperty("use_primary_sms", "true");
    	$map_id = $this->mmha->createMessage(1234567, $order_id, 'T', 3038844083, time(), 'A', 'firmware=7.0', 'cdyne');
    	$message_resource = Resource::find($this->mmha,''.$map_id);
    	$text_controller = new TextController($mt, $u, $r,5);
    	$result = $text_controller->sendThisMessage($message_resource);
    	$this->assertTrue($result);
    	$new_message_resource = Resource::find($this->mmha,''.$map_id);
    	$this->assertEquals("S", $new_message_resource->locked);
    	
    	setProperty("use_primary_sms", "false");
    	$sql = "UPDATE Merchant_Message_History SET locked = 'F' WHERE merchant_id = 1234568 AND message_format = 'T'";
    	$this->mmha->_query($sql);
    	$map_id2 = $this->mmha->createMessage(1234568, $order_id, 'T', 3038844083, time(), 'A', 'firmware=7.0', 'twilio');
    	$message_resource2 = Resource::find($this->mmha,''.$map_id2);
    	$text_controller = new TextController($mt, $u, $r,5);
    	$result2 = $text_controller->sendThisMessage($message_resource2);
    	$this->assertTrue($result2);
    	$new_message_resource2 = Resource::find($this->mmha,''.$map_id2);
    	$this->assertEquals("S", $new_message_resource2->locked);
    }
    
    function testControllerFactoryFromUrl()
    {
    	$controller = ControllerFactory::generateFromUrl('/fax/', $mimetypes, $user, $request, $log_level);
    	$this->assertTrue(is_a($controller, 'FaxController'));
    	
    	// verify that the message_format array is loaded
    	$message_format_array = $controller->getFormatArray();
    	$this->assertNotNull($message_format_array);
    	$this->assertTrue(sizeof($message_format_array, $mode) > 0);
    	return $message_format_array;
    }

    function testControllerFactoryFromMessageResource()
    {
    	$map_id = $this->mmha->createMessage(123456, 123456, "GUC", "gprs", time(), "X", $info, $message_text);
    	$message_resource = Resource::find($this->mmha,"$map_id");
    	$controller = ControllerFactory::generateFromMessageResource($message_resource, $mimetypes, $user, $request, $log_level);
    	$this->assertTrue(is_a($controller, 'GprsController'));
    }
    
    function testGetMessageArray()
    {
    	$merchant_resource = createNewTestMerchant();
    	$this->createMessages(10,$merchant_resource->merchant_id);
		$mess_options[TONIC_FIND_BY_METADATA] = array("merchant_id"=>$merchant_resource->merchant_id);
    	$resources = $this->mmha->getAvailableMessageResourcesArray($mmha_options);
    	$this->assertTrue(is_array($resources));
    	$this->assertEquals(10, sizeof($resources, $mode));
    	$orders_first = true;
    	foreach ($resources as $resource)
    	{
    		$order_id = $resource->order_id;
    		myerror_log("on this cycle the order_id = ".$order_id);
    		if ($order_id == null)
    			$orders_first = false;
    		if ($orders_first)
    			$this->assertTrue($order_id > 0);
    		else
    			$this->assertNull($order_id);
    	}
    	return $resources;
    }
    
    /**
     * @depends testGetMessageArray
     */
    function testSendMessages($resources)
    {
			foreach ($resources as $unlocked_message_resource)
			{
				if ($message_resource = $this->mmha->getLockedMessageResourceForSending($unlocked_message_resource))
				{
					if ($message_controller = ControllerFactory::generateFromMessageResource($message_resource, $mimetypes, $user, $request, $log_level))
					{
						$response[] = $message_controller->sendTheMessage();
					}
				}
				$message_resource = null;
			}
			for ($i=0;$i<10;$i++)
				$this->assertTrue($response[$i]);
    	
    }
    
    function testPingController()
    {
    	logTestUserIn($this->ids['user_id']);
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'Pickup', 'Skip hours');
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNull($order_resource->error);
    	$order_id = $order_resource->order_id;
    	$this->assertTrue($order_id > 1000);
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$message_resource = $mmha->createMessageReturnResource($merchant_id, $order_id, 'P', "127.0.0.1", time()-3, "A", $info, $message_text);
    	$message_controller = ControllerFactory::generateFromMessageResource($message_resource, $mimetypes, $user, $request, 5);
    	$response = $message_controller->sendTheMessage();
    	$this->assertTrue($response);

    }
    
/*    
    function testGetNextMessageByMerchantId()
    {
    	$this->createMessages(4,1083,'G','P');
	   	$request = new Request();
    	$request->url = "/m/g/96305055/gprs/f.txt";
    	$gprs_controller = new GprsController($mt, $u, $request,5);
    	$previous_send_time = 0;
    	$previous_order_id = 1000;
    	$i = 0;
    	while ($message_resource = $gprs_controller->pullNextMessageResourceByMerchant(96305055))
    	{
    		$order_id = $message_resource->order_id;
    		$send_time = $message_resource->next_message_dt_tm;
    		// recalibrate because we're switching to the null order id's
    		if ($previous_order_id > 0 && $order_id == null)
    			$previous_send_time = 0;
			$this->assertTrue($previous_send_time < $send_time,"previous send time should be less than this send time:   ".$previous_send_time.' - '.$send_time);
			$this->assertEquals('Y', $message_resource->locked);
			$i++;
			$previous_send_time = $send_time;
			$previous_order_id = $order_id;    		
    	}
    	$this->assertEquals(4, $i);
    }
*/    
     
    function testDbLogController()
    {
    	$this->createMessages(10,123456,'D','N',true);
    	$message_resources = $this->mmha->getAvailableMessageResourcesArray();
    			
		myerror_logging(3, "there were ".sizeof($message_resources)." message ready for sending");
		foreach ($message_resources as $unlocked_message_resource)
		{
			myerror_logging(3, "trying to get lock on message id: ".$unlocked_message_resource->map_id);
			if ($message_resource = $this->mmha->getLockedMessageResourceForSending($unlocked_message_resource,false))
			{
				if ($controller = ControllerFactory::generateFromMessageResource($message_resource, $mimetypes, $user, $request, $log_level))
					$controller->sendTheMessage();
			}			
		}
		
		$dbml_adapter = new DbMessageLogAdapter($mimetypes);
		$dbl_resources = Resource::findAll($dbml_adapter,'');
		$this->assertEquals(10, sizeof($dbl_resources, $mode));
    }
    
 /*   function testConcurrantWorkers()
    {
    	$sql = 'TRUNCATE TABLE `db_message_log`';
    	$this->mmha->_query($sql);
    	$this->createMessages(1000,1083,'D','N',true);
    	
    }
   */     
    function createMessages($number_of_messages_to_create,$merchant_id,$message_format = 'E',$locked = 'N',$erase = true)
    {
    	$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
		if ($erase)
		{
    		$sql = "UPDATE Merchant_Message_History SET locked = 'F' WHERE (locked = 'N' OR locked = 'P' OR locked = 'Y')";
    		$mmh_adapter->_query($sql);
		}
    	$mmh_data['merchant_id'] = $merchant_id;
    	
    	$mmh_data['message_format'] = $message_format;
    	$mmh_data['locked'] = $locked;
    	$mmh_data['message_delivery_addr'] = 'adam@dummy.com';
    	if ($locked == 'P')
    		$mmh_data['message_delivery_addr'] = 'pulled';
    	if ($message_format == 'P')
    		$mmh_data['message_delivery_addr'] = '127.0.0.1';
    	$used_num = array();
    	for($i=0;$i<$number_of_messages_to_create;$i++) {
            do {
                $num = mt_rand(10, 90);
            } while ($used_num[$num]);
            $used_num[$num] = 1;
            $mmh_data['message_text'] = 'Hello World ' . $num;
            if ($message_format == 'T')
                $mmh_data['message_text'] = '*** ' . $num;
            $mmh_data['next_message_dt_tm'] = time() - $num;
            $num = rand(1, 3);
            if ($num == 2) {
                unset($mmh_data['order_id']);
            } else {
                //$mmh_data['order_id'] = 12345;
                $mmh_data['order_id'] = rand(1,1000);
            }
    		$message_resource = Resource::factory($mmh_adapter,$mmh_data);
    		$message_resource->save();
    	}
    }
    
    static function setUpBeforeClass()
    {
    	set_time_limit(30);
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['default_tz'] = $tz;
    	date_default_timezone_set("America/Denver");
    	$sql = 'TRUNCATE TABLE `db_message_log`';
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$mmha->_query($sql);
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	
    	$skin_resource = createWorldHqSkin();
    	$ids['skin_id'] = $skin_resource->skin_id;
    	
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;
    	
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);

    	$merchant_resource = createNewTestMerchant($menu_id);
    	attachMerchantToSkin($merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;
    	
    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;
		myerror_log("ABOUT TO START THE TESTS ON NewMessageControllerTest");
    }
    
	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
    	//need this right now becuase of pesimistic logging commits the data
    	myerror_log("about to delete the worldhq skin");
    	$sa = new SkinAdapter($mimetypes);
    	$sql = "DELETE FROM Skin_Merchant_Map WHERE skin_id = 250";
    	$sa->_query($sql);
    	$sql = "DELETE FROM Skin WHERE external_identifier = 'com.splickit.worldhq' LIMIT 1";
    	$sa->_query($sql);
    	
    }

    /* main method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    NewMessageControllerTest::main();
}

?>