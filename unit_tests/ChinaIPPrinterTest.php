<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class ChinaIPPrinterTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $user;
	var $merchant_id;
	var $menu_id;
	var $ids;

	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		
		// we dont want to call to inspirepay 
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		
		setContext("com.splickit.chinaip");
		
		$user_resource = SplickitController::getResourceFromId($_SERVER['unit_test_ids']['user_id'], 'User');
		$this->user = $user_resource->getDataFieldsReally();
    	$this->merchant_id = $_SERVER['unit_test_ids']['merchant_id'];
    	$this->menu_id = $_SERVER['unit_test_ids']['menu_id'];
    	$this->ids = $_SERVER['unit_test_ids'];
    	
    	logTestUserIn($user_resource->user_id);
	}
	
	function tearDown() 
	{
		//delete your instance
		unset($this->user);
		unset($this->merchant_id);
		unset($this->menu_id);
		unset($this->ids);
		$_SERVER['STAMP'] = $this->stamp;
		setProperty("gprs_tunnel_merchant_shutdown", "false");
    }
    
    function testCallBackNoOrderId()
    {
    	$request = new Request();
    	$data['o'] = 700;
    	$request->data = $data;
	 	$cipc = new ChinaIPPrinterController($mt, $u, $request);
    	$this->assertTrue($cipc->callBack($this->merchant_id));
    }
    
    function testNoMerchantID()
    {
    	$cipc = new ChinaIPPrinterController($mt, $u, $request);
    	$this->assertFalse($cipc->callBack(456787654));
    	$this->assertFalse($cipc->pullNextMessageResourceByMerchant(456787654));    	
    }
    
    function testSendMethod()
    {
    	$cipc = new ChinaIPPrinterController($mt, $u, $request);
    	$this->assertTrue($cipc->send("some text"));
    }
    
    function testRecordCallIn()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;

        $map_resource = Resource::createByData(new MerchantMessageMapAdapter(getM()),array("merchant_id"=>$merchant_id,"message_format"=>'HUA',"delivery_addr"=>"ChinaIp","message_type"=>"X","info"=>"firmware=7.0"));


        setProperty('chinaipprinter_log_level',5,true);
    	$request = new Request();
		$request->url = "https://pweb01.splickit.com/app2/messagemanager/getnextmessagebymerchantid/$merchant_id/chinaipprinter/f2.txt";
    	$message_controller = ControllerFactory::generateFromUrl('/chinaipprinter/', getM(), null, $request, 1);
		$this->assertEquals(5,$message_controller->getLogLevel(),"should have set the log level to 5");

    	
    	$gpciha = new DeviceCallInHistoryAdapter(getM());
    	$this->assertNull($gpciha->getRecord(array("merchant_id"=>$merchant_id)),'there should not be a record in the table yet');
    	
    	$message_controller->pullNextMessageResourceByMerchant($merchant_id);
    	
    	//check to see if a record was made in the call in table
        $record = $gpciha->getRecord(array("merchant_id"=>$merchant_id));
    	$this->assertNotNull($record,"There should be a record");
        $this->assertEquals(0,$record['auto_turned_off'],"auto turned off shoujld be false");
        $this->assertTrue($record['last_call_in_as_integer'] == time() || $record['last_call_in_as_integer'] == time()+1,"Call in shoudl be the integer value of now but it was: ".$record['last_call_in_as_integer']);
        $this->assertEquals('H',$record['device_base_format'],"Should be listed as a CHINA IP type, or 'H' as we call it");
        return $record;
    }

    /**
     * @depends testRecordCallIn
     */
    function testAutoTurnOff($record)
    {
        $doit_ts = time()-2;
        $activity_history_adapter = new ActivityHistoryAdapter(getM());
        $activity_resource = $activity_history_adapter->createActivityReturnActivityResource('CheckDeviceCallIn', $doit_ts,null, null);

        $late_call_in_activity = SplickitActivity::getActivity($activity_resource);
        $this->assertEquals('CheckDeviceCallInActivity',get_class($late_call_in_activity),"It should be a CheckDeviceCallInActivity");

        $ma = new MerchantAdapter(getM());
        $gciha = new DeviceCallInHistoryAdapter(getM());
        $resource = Resource::find($gciha,$record['merchant_id']);

        $results = $gciha->getNonRecentlyCalledInDevicesOtherThanGPRS(5);
        $this->assertCount(0,$results);

        $late_call_in_activity->doit();


        $merchant_id = $record['merchant_id'];
        $merchant_record = $ma->getRecordFromPrimaryKey($merchant_id);
        $this->assertEquals('Y',$merchant_record['active'],"Merchant should be active");
        $this->assertEquals('Y',$merchant_record['ordering_on'],"Merchant should be on");

        $resource->last_call_in_as_integer = time()-301;
        $resource->save();

        $results = $gciha->getNonRecentlyCalledInDevicesOtherThanGPRS(5);
        $this->assertCount(1,$results);

        $china_ip_results = $results['china_ip'];
        $this->assertCount(1,$china_ip_results);

        $late_call_in_activity->doit();

        $merchant_record = $ma->getRecordFromPrimaryKey($merchant_id);
        $this->assertEquals('Y',$merchant_record['active'],"Merchant should be active");
        $this->assertEquals('N',$merchant_record['ordering_on'],"Merchant should be off");
        $this->assertEquals('F',$merchant_record['inactive_reason'],"It should have set the innactive reason to 'F'");

        $call_in_record = $gciha->getRecordFromPrimaryKey($merchant_id);
        $this->assertEquals(1,$call_in_record['auto_turned_off']);

        $results_after = $gciha->getNonRecentlyCalledInDevicesOtherThanGPRS(5);
        $this->assertCount(0,$results_after,"It should not have any results because the flag is off");


        $request = new Request();
        $request->url = "https://pweb01.splickit.com/app2/messagemanager/getnextmessagebymerchantid/$merchant_id/chinaipprinter/f2.txt";
        $message_controller = ControllerFactory::generateFromUrl('/chinaipprinter/', getM(), null, $request, 1);

        $message_controller->pullNextMessageResourceByMerchant($merchant_id);

        $call_in_record_after = $gciha->getRecordFromPrimaryKey($record['merchant_id']);
        $this->assertEquals(0,$call_in_record_after['auto_turned_off']);

        $merchant_record_after = $ma->getRecordFromPrimaryKey($record['merchant_id']);
        $this->assertEquals('Y',$merchant_record_after['active'],"Merchant should be active");
        $this->assertEquals('Y',$merchant_record_after['ordering_on'],"Merchant should be ON");

        return $merchant_id;
    }

    /**
     * @depends testAutoTurnOff
     */
    function testResetFlagWhenMerchantHasOrderingOnAlready($merchant_id)
    {
        $ma = new MerchantAdapter(getM());
        //now set flag to off again, but set merchant ordering on to 'Y' to make sure flag gets reset
        $merchant_resource = Resource::find($ma,"$merchant_id");
        $merchant_resource->ordering_on = 'Y';
        $merchant_resource->save();

        $dciha = new DeviceCallInHistoryAdapter(getM());
        $call_in_resource = Resource::find($dciha,"$merchant_id");
        $call_in_resource->auto_turned_off = 1;
        $call_in_resource->save();

        $call_in_record = $dciha->getRecordFromPrimaryKey($merchant_id);
        $this->assertEquals(1,$call_in_record['auto_turned_off']);

        $request = new Request();
        $request->url = "https://pweb01.splickit.com/app2/messagemanager/getnextmessagebymerchantid/$merchant_id/chinaipprinter/f2.txt";
        $message_controller = ControllerFactory::generateFromUrl('/chinaipprinter/', getM(), null, $request, 1);

        $message_controller->pullNextMessageResourceByMerchant($merchant_id);

        $call_in_record_after = $dciha->getRecordFromPrimaryKey($merchant_id);
        $this->assertEquals(0,$call_in_record_after['auto_turned_off']);

        return $merchant_id;
    }

    /**
     * @depends testResetFlagWhenMerchantHasOrderingOnAlready
     */
    function testDoNotAutoShutOffIfMerchantMessageMapIsNotActive($merchant_id)
    {
        $gciha = new DeviceCallInHistoryAdapter(getM());
        $resource = Resource::find($gciha,$merchant_id);
        $resource->last_call_in_as_integer = time()-301;
        $resource->save();

        $results = $gciha->getNonRecentlyCalledInDevicesOtherThanGPRS(5);
        $this->assertCount(1,$results);

        $china_ip_results = $results['china_ip'];
        $this->assertCount(1,$china_ip_results);

        // disable MMM
        $mmma = new MerchantMessageMapAdapter(getM());
        $options[TONIC_FIND_BY_METADATA] = ['merchant_id'=>$merchant_id,'message_format'=>'HUA'];
        $mmm_resource = Resource::find($mmma,null,$options);
        $this->assertEquals('N',$mmm_resource->logical_delete);
        $mmm_resource->logical_delete = 'Y';
        $mmm_resource->save();


        $doit_ts = time()-2;
        $activity_history_adapter = new ActivityHistoryAdapter(getM());
        $activity_resource = $activity_history_adapter->createActivityReturnActivityResource('CheckDeviceCallIn', $doit_ts,null, null);

        $late_call_in_activity = SplickitActivity::getActivity($activity_resource);
        $this->assertEquals('CheckDeviceCallInActivity',get_class($late_call_in_activity),"It should be a CheckDeviceCallInActivity");

        $late_call_in_activity->doit();

        $ma = new MerchantAdapter(getM());
        $merchant_record = $ma->getRecordFromPrimaryKey($merchant_id);
        $this->assertEquals('Y',$merchant_record['active'],"Merchant should be active");
        $this->assertEquals('Y',$merchant_record['ordering_on'],"Merchant should be still be on since map record is not active");

        $device_call_in_resource_after = Resource::find($gciha,$merchant_id);
        $this->assertNull($device_call_in_resource_after,"It should have deleted the device call in record");
    }
         
    function testCheckForIPMessageInStateReadyToBeSent()
    {
    	$merchant_resource = createNewTestMerchant();
    	// first create GPRS message not ready to be sent
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$message_resource = $mmha->createMessageReturnResource($merchant_resource->merchant_id, 1000, 'HUC', 'ip', time()+5, 'X', 'firmware=1.1.0.9ip', "here is the message text",'P');
    	$message_controller = ControllerFactory::generateFromMessageResource($message_resource, $mimetypes, $user, $request, $log_level);
    	$this->assertFalse($message_controller->pullNextMessageResourceByMerchant($merchant_resource->merchant_id));
    	
    	// now changet the send time
    	$message_resource->next_message_dt_tm = time()-5;
    	$message_resource->save();
    	$pulled_message_resource = $message_controller->pullNextMessageResourceByMerchant($merchant_resource->merchant_id);
    	$this->assertTrue(is_a($pulled_message_resource, 'Resource'));
    }
    
    function testPrepMessageForSending()
    {
		$merchant_resource = createNewTestMerchant($this->menu_id);
    	$merchant_id = $merchant_resource->merchant_id;
    	$map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_id,"message_format"=>'HUA',"delivery_addr"=>"Internet Printer","message_type"=>"X","info"=>"firmware=1.1.0.9ip"));
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','skip hours');
    	
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$cpc = new ChinaIPPrinterController($mt, $u, $r, 5);
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		$options[TONIC_FIND_BY_METADATA] = array('merchant_id'=>$merchant_id,'message_format'=>'HUA','locked'=>'P'); 
		$message_resource = $mmha->getNextMessageResourceForSend($options);
    	$this->assertTrue(is_a($message_resource, 'Resource'));
    	$resource = $cpc->prepMessageForSending($message_resource);

    }
    
    function testGetNextMessage()
    {
    	$merchant_resource = createNewTestMerchant($this->menu_id);
    	$merchant_id = $merchant_resource->merchant_id;
    	$map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_id,"message_format"=>'HUA',"delivery_addr"=>"Internet Printer","message_type"=>"X","info"=>"firmware=1.1.0.9ip"));
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','skip hours');
    	
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		$records = $mmha->getRecords(array("merchant_id"=>$merchant_id));
    	    	
		$this->assertNotNull($order_resource);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000,"Bad order id of: ".$order_resource->order_id);  
		
		$request = new Request();
    	$request->url = "/messagemanager/getnextmessagebymerchantid/$merchant_id/chinaipprinter/f2.txt";
    	$message_controller = ControllerFactory::generateFromUrl($request->url, $mimetypes, $user, $request, $log_level);
    	
    	$message_resource = $message_controller->pullNextMessageResourceByMerchant($merchant_id);
    	myerror_log("pulled message is: ".$message_resource->message_text);
    	//sleep(2);
        //#104558*1*280912***;;;::::Order For: First Last::Loyalty No: 6590757688::Order Id:  280912
    	$this->assertContains('#'.$merchant_id.'*1*'.$order_resource->order_id.'***;;;::::Order For: First Last::Loyalty No: '.$this->ids['user_loyalty_number'].'::Order Id:  '.$order_resource->order_id.'::', $message_resource->message_text);
    }

    function testCleanMessageForSending() {
      $message_resource = Resource::createByData(new MySQLAdapter($m),$data);
      $order_details = array();
      $order_detail = array('note' => "Boo#gers@", 'item_print_name' => "Bo@ogers", 'size_print_name' => "Boo*@gers");
      $order_detail['order_detail_modifiers'] = array(array('mod_print_name' => "In# My*; Nose@"));
      $order_detail['order_detail_hold_it_modifiers'] = array(array('mod_print_name' => "In# My*; Nose@"));
      $order_detail['order_detail_mealdeal'] = array(array('mod_print_name' => "In# My*; Nos@e"));
      $order_detail['order_detail_sides'] = array(array('mod_print_name' => "In# My*; No@se"));
      $order_detail['order_detail_added_modifiers'] = array(array('mod_print_name' => "In@# My*; Nose"));
      $order_detail['order_detail_comeswith_modifiers'] = array(array('mod_print_name' => "I@n# My*; Nose"));
      $order_details[] = $order_detail;
      $message_resource->order_details = $order_details;
        $message_resource->delivery_info->address1 = "push *star #pound @at";
        $message_resource->delivery_info->address2 = "push *star #pound @at";
        $message_resource->delivery_info->phone_no = "1*23@456$789#0";
        $message_resource->delivery_info->instructions = "push *star #pound @at";

        $china_controller = new ChinaIPPrinterController($m,$u,$r);
    
      $cleaned_resource = $china_controller->cleanMessageForSending($message_resource);
      foreach($cleaned_resource->order_details as $cleaned_item) {
        $this->assertEquals($cleaned_item['note'], "Boogers", "We should be stripping characters from the item notes.");
        $this->assertEquals($cleaned_item['item_print_name'], "Boogers", "We should be stripping characters from item print name.");
        $this->assertEquals($cleaned_item['size_print_name'], "Boogers", "We should be stripping characters from the size print name.");
    
        foreach($cleaned_item['order_detail_modifiers'] as $mod) {
          $this->assertEquals($mod['mod_print_name'], "In My Nose", "We should be stripping characters from the modifier print name.");
        }
         
        foreach($cleaned_item['order_detail_hold_it_modifiers'] as $mod) {
          $this->assertEquals($mod['mod_print_name'], "In My Nose", "We should be stripping characters from the modifier print name.");
        }
         
        foreach($cleaned_item['order_detail_sides'] as $mod) {
          $this->assertEquals($mod['mod_print_name'], "In My Nose", "We should be stripping characters from the modifier print name.");
        }
         
        foreach($cleaned_item['order_detail_mealdeal'] as $mod) {
          $this->assertEquals($mod['mod_print_name'], "In My Nose", "We should be stripping characters from the modifier print name.");
        }
         
        foreach($cleaned_item['order_detail_comeswith_modifiers'] as $mod) {
          $this->assertEquals($mod['mod_print_name'], "In My Nose", "We should be stripping characters from the modifier print name.");
        }
         
        foreach($cleaned_item['order_detail_added_modifiers'] as $mod) {
          $this->assertEquals($mod['mod_print_name'], "In My Nose", "We should be stripping characters from the modifier print name.");
        }
      }

        $this->assertEquals("push star pound at",$cleaned_resource->delivery_info->address1);
        $this->assertEquals("push star pound at",$cleaned_resource->delivery_info->address2);
        $this->assertEquals("1234567890",$cleaned_resource->delivery_info->phone_no);
        $this->assertEquals("push star pound at",$cleaned_resource->delivery_info->instructions);

    }
    
	function testNewPrinterReceiptFormat()
    {
    	$merchant_resource = createNewTestMerchant($this->menu_id);
    	$merchant_resource->immediate_message_delivery = 'Y';
    	$merchant_resource->save();
    	$merchant_id = $merchant_resource->merchant_id;
    	$map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_id,"message_format"=>'HUA',"delivery_addr"=>"Internet Printer","message_type"=>"X","info"=>"firmware=1.1.0.9ip"));
    	
    	$bag_tax = .10;
    	FixedTaxAdapter::createTaxRecord($merchant_id, "Bag Tax", $bag_tax);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','sum bum note');
    	$order_data['tip'] = 5.00;
    	
		$order_resource = placeOrderFromOrderData($order_data, getTomorrowTwelveNoonTimeStampDenver());
		
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		$records = $mmha->getRecords(array("merchant_id"=>$merchant_id));
    	    	
		$this->assertNotNull($order_resource);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000,"Bad order id of: ".$order_resource->order_id);  
		
		$request = new Request();
    	$request->url = "/messagemanager/getnextmessagebymerchantid/$merchant_id/chinaipprinter/f2.txt";
    	$message_controller = ControllerFactory::generateFromUrl($request->url, $mimetypes, $user, $request, $log_level);

    	$message_resource = $message_controller->pullNextMessageResourceByMerchant($merchant_id);
    	myerror_log("pulled message is: ".$message_resource->message_text);
    	
    	$expected_message_reciept = "::Subtotal: $1.50::Bag Tax: $0.10::Tax: $0.15::Total: $1.75::Tip: $5.00::Grand Total: $6.75::::;*#";
    	$this->assertContains($expected_message_reciept, $message_resource->message_text);
    }

    function testResendOfOrderToIPprinter()
    {
		$merchant_resource = createNewTestMerchant($this->menu_id);
    	$merchant_id = $merchant_resource->merchant_id;    	
    	$map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_id,"message_format"=>'HUA',"delivery_addr"=>"Internet Printer","message_type"=>"X","info"=>"firmware=1.1.0.9ip"));
		
		$order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup',"note",1);
		$order_resource = placeOrderFromOrderData($order_data);
    	    	
		$this->assertNotNull($order_resource);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000,"Bad order id of: ".$order_resource->order_id);  
		$order_id = $order_resource->order_id;
		
		// now get the order
    	//sleep(1);  	
    	$request = new Request();
    	$request->url = "/messagemanager/getnextmessagebymerchantid/$merchant_id/chinaipprinter/f2.txt";
    	$message_controller = ControllerFactory::generateFromUrl($request->url, $mimetypes, $user, $request, $log_level);
    	
    	$message_resource = $message_controller->pullNextMessageResourceByMerchant($merchant_id);
    	$message_text = $message_resource->message_text;
    	myerror_log("pulled message is: ".$message_resource->message_text);
    	myerror_log("trimmed: ".substr($message_text, 0,16));
    	$this->assertEquals("#".$merchant_id."*1*".$order_id."***", substr($message_text, 0,19));
    	$message_controller->markMessageDelivered();

    	// now call the resend
    	$order_controller = new OrderController($mt, $u, $r,5);
    	$order_controller->resendOrder($order_id);
    	//sleep(1);
    	
    	$message_resource2 = $message_controller->pullNextMessageResourceByMerchant($merchant_id);
    	//Resource::encodeResourceIntoTonicFormat($message_resource2);
    	myerror_log("We have pulled the next message");
    	$this->assertNotNull($message_resource2);
    	$message_text = $message_resource2->message_text;
    	myerror_log("pulled message is: ".$message_text);
 		$this->assertEquals("#".$merchant_id."*1*".$order_id."-", substr($message_text, 0,17));
 		$append = substr($message_text, 17,2);
 		$append2 = substr($message_text, 19,3);
 		myerror_log("the append is: ".$append);
 		myerror_log("the append2 is: ".$append2);
 		$this->assertTrue(1 == preg_match("/[a-z]{2}/", $append,$matches1),"should have found a two letter string but found: ".$append);
 		$this->assertFalse(1 == preg_match("/[a-z]{2}/", $append2,$matches2),"should not have found two letter string: ".$append2);
 		$this->assertEquals("***", $append2);
    }

   	function testCallBack()
    {
    	//create sent message
    	$merchant_resource = createNewTestMerchant();
    	$merchant_id = $merchant_resource->merchant_id;
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$picked_up_message_resource = $mmha->createMessageReturnResource($merchant_id, 1001, 'HUC', 'ip', time()-10, 'X', 'firmware=1.1.0.9ip', $message_text,'S',time()-1,1);
    	$picked_up_message_resource->viewed = 'N';
    	$picked_up_message_resource->save();

    	// now do callback
    	$request = new Request();
    	$data['o'] = 1001;
    	$request->data = $data; 
    	$chinaipprintercontroller = new ChinaIPPrinterController($mt, $u, $request,5);
    	
    	$chinaipprintercontroller->callBack($merchant_resource->numeric_id);
    	$refreshed = $picked_up_message_resource->refreshResource($picked_up_message_resource->map_id);
    	$this->assertEquals('V', $refreshed->viewed);

    }

    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	$_SERVER['request_time1'] = microtime(true);    	
		$_SERVER['log_level'] = 5;

		$skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty('chinaip','chinaip',$skin_id,$brand_id);
        setContext("com.splickit.chinaip");
		$menu_id = createTestMenuWithNnumberOfItems(1);
    	
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 10);

    	$ids['menu_id'] = $menu_id;
    	$merchant_resource = createNewTestMerchant($menu_id);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
    	$map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_resource->merchant_id,"message_format"=>'GUA',"delivery_addr"=>"1234567890","message_type"=>"X","info"=>"firmware=7.0"));

		$user_resource = createNewUser(array('flags'=>'1C20000001'));
    	$ids['user_id'] = $user_resource->user_id;
    	$ids['user_loyalty_number'] = str_replace('-','',$user_resource->contact_no);
    	    	
    	$_SERVER['unit_test_ids'] = $ids;

    }
    
	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
    }

    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    ChinaIPPrinterTest::main();
}

?>