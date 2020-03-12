<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class PlaceOrderTest extends PHPUnit_Framework_TestCase
{
	var $menu;
	var $merchant;
	var $user;
	var $stamp;
	var $user_resource;
	var $time_stamp_for_noon_on_feb14_denver;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		setContext("com.splickit.worldhq");
		$this->ids = $_SERVER['unit_test_ids'];

		$this->user = logTestUserIn($this->ids['user_id']);
		$this->user_resource = SplickitController::getResourceFromId($this->ids['user_id'], "User");
		$this->merchant_resource = SplickitController::getResourceFromId($this->ids['merchant_id'], "Merchant");
		
		$time_stamp_for_noon_on_feb14_denver = getTimeStampForDateTimeAndTimeZone(12, 0, 0, 2, 14, 2013, 'America/Denver');
		$this->time_stamp_for_noon_on_feb14_denver = $time_stamp_for_noon_on_feb14_denver;
		$this->ids = $_SERVER['unit_test_ids'];
	}
	
	function tearDown() 
	{
		//delete your instance
		unset($this->menu);
		unset($this->merchant);
		unset($this->user_resource);
		unset($this->user);
		unset($this->ids);
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->stamp);
        setProperty('fpn_ordering_on', 'true');
        setProperty('mercury_pay_ordering_on', 'true');
        setProperty('inspire_pay_ordering_on', 'true');

    }

	function testGetLowestConvienceFeeFixed()
	{
		$merchant['trans_fee_rate'] = .50;
		$merchant['trans_fee_type'] = 'f';
		$user['trans_fee_override'] = null;
		$place_order_controller = new PlaceOrderController($m,$u,$r,5);
		$this->assertEquals(.50,$place_order_controller->getLowestValidConvenienceFeeFromMerchantUserCombination(10.00,$merchant,$user,$array));
	}

	function testGetLowestConvienceFeePercentage()
	{
		$merchant['trans_fee_rate'] = 10;
		$merchant['trans_fee_type'] = 'p';
		$user['trans_fee_override'] = null;
		$place_order_controller = new PlaceOrderController($m,$u,$r,5);
		$this->assertEquals(1.00,$place_order_controller->getLowestValidConvenienceFeeFromMerchantUserCombination(10.00,$merchant,$user,$array));
	}



	function testConvenienceFeeByPercentage()
	{
        setContext("com.splickit.worldhq");
		$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);
		$merchant_resource->trans_fee_type = 'P';
		$merchant_resource->trans_fee_rate = 10.0;
		$merchant_resource->save();
		$merchant_id = $merchant_resource->merchant_id;

		$user_resource = createNewUser(array("flags"=>"1C20000001"));
		$user_id = $user_resource->user_id;
		logTestUserIn($user_id);

		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours', 10);
		$order_data['tip'] = 0.00;
        $checkout_data_response = getCheckoutResourceFromOrderData($order_data);
		$checkout_receipt_items = $checkout_data_response->order_summary['receipt_items'];
		$checkout_convenience_fee = $checkout_receipt_items[3];
		$this->assertEquals('$1.50',$checkout_convenience_fee['amount']);


		$response = placeOrderFromOrderDataAPIV1($order_data, $time);
		$receipt_items = $response->order_summary['receipt_items'];
		$convenience_fee = $receipt_items[3];
		$this->assertEquals('$1.50',$convenience_fee['amount']);
		$total = $receipt_items[4];
		$this->assertEquals('Total',$total['title']);

	}

	function testConvenienceFeeByPercentageBadAmount()
	{
        setContext("com.splickit.worldhq");
		$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);
		$merchant_resource->trans_fee_type = 'P';
		$merchant_resource->trans_fee_rate = 10.0;
		$merchant_resource->save();
		$merchant_id = $merchant_resource->merchant_id;

		$user_resource = createNewUser(array("flags"=>"1C20000001"));
		$user_id = $user_resource->user_id;
		logTestUserIn($user_id);

		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours', 10);
		$order_data['tip'] = 0.00;
		$order_data['sub_total'] = 1.00;
        $checkout_data_response = getCheckoutResourceFromOrderData($order_data);
		$checkout_receipt_items = $checkout_data_response->order_summary['receipt_items'];
		$checkout_convenience_fee = $checkout_receipt_items[3];
		$this->assertEquals('$1.50',$checkout_convenience_fee['amount']);


		$response = placeOrderFromOrderDataAPIV1($order_data, $time);
		$order = CompleteOrder::getBaseOrderData($response->order_id);

		$receipt_items = $response->order_summary['receipt_items'];
		$convenience_fee = $receipt_items[3];
		$this->assertEquals('$1.50',$convenience_fee['amount']);
		$this->assertEquals(15.00,$order['order_amt']);
		$this->assertEquals(1.50,$order['trans_fee_amt']);
		$this->assertEquals(18.00,$order['grand_total']);
		$this->assertEquals(16.50,$order['grand_total_to_merchant']);

	}

	function testStripNotesOnSkinWhichIgnoresThem() {
	    $skin_resource = getOrCreateSkinAndBrandIfNecessary('nonotes','nonotes',null,null);
	    $skin_resource->show_notes_fields = 0;
	    $skin_resource->save();

        setContext('com.splickit.nonotes');
        $skin_adapter = new SkinAdapter(getM());
        $skin_record = $skin_adapter->getRecordFromPrimaryKey(getSkinIdForContext());

      $user_resource = createNewUser(array("flags"=>"1C20000001"));
      $user_id = $user_resource->user_id;
      logTestUserIn($user_id);

      $order_adapter = new OrderAdapter(getM());
      $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id = $this->ids['merchant_id'], 'pickup', 'skip hours', 3);
      
      $order_data['note'] = "This is an order.skip hours";
      foreach($order_data['items'] as &$item) {
        $item['note'] = 'This is an item.';
      }
      
      $response = placeOrderFromOrderDataAPIV1($order_data, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($response->error);
      $complete_order = CompleteOrder::getCompleteOrderAsResource($response->order_id, $mimetypes);
      
      $this->assertEquals('', $complete_order->note, "The order note should be stripped from an order placed on this skin.");
      foreach($complete_order->order_details as $complete_item) {
        $this->assertEquals('', $complete_item['note'], "The item note should be stripped from an ordered item placed on this skin.");
      }
      
      $_SERVER['SKIN']['show_notes_fields'] = true;
    }
    
    function testPointsCurrentAndLifeTime()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours', 3);

		$response = placeOrderFromOrderDataAPIV1($order_data, $time);
		$this->assertNull($reponse->error);
		$order_id = $response->order_id;
		$this->assertTrue($order_id > 1000,"should have created a valid order");
    }
    
    function testTimeoutToVaultErrorForUser()
    {
    	$user_resource = createNewUserWithCCNoCVV();

    	$user_resource->uuid = "1234-".rand(11111,99999)."-ttttt-ttttt";
    	$user_resource->save();
    	$user = logTestUserResourceIn($user_resource);
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', $note);
    	$order_resource = placeOrderFromOrderDataAPIV1($order_data, getTodayTwelveNoonTimeStampDenver());
    	$this->assertNotNull($order_resource->error);
    	$this->assertEquals("We're sorry but there was an connection problem reaching the credit card processing facility and your order did not go through. Please try again.", $order_resource->error);

		$order_record = OrderAdapter::staticGetRecordByPrimaryKey($order_resource->cancelled_order_id,"OrderAdapter");
		$this->assertEquals('N',$order_record['status']);



    }
    
    function testMakeSureAdminUsersCanOrder()
    {
    	$user = logTestUserIn(2);
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', $note);
    	$order_resource = placeOrderFromOrderDataAPIV1($order_data, getTodayTwelveNoonTimeStampDenver());
    	$this->assertNull($order_resource->error);
    	$this->assertTrue($order_resource->order_id > 1000);
    }
    
    function testNoCCError() {
      $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
      $merchant_id = $merchant_resource->merchant_id;
       
      $user_resource = createNewUser();
        $user_resource->flags = '1000000001';
        $user_resource->save();
      $order_adapter = new OrderAdapter($mimetypes);
      $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours', 3);
      $order_resource = placeOrderFromOrderDataAPIV1($order_data, $time_stamp);
      $this->assertEquals($order_resource->http_code, 400, "Sending an order with no cc should respond with an HTTP 400.");
      $this->assertEquals($order_resource->error, "Please enter your credit card info", "Sending an order with no cc should respond with an error message.");
      $this->assertEquals($order_resource->error_code, 150, "Sending an order with no cc should respond with a SplickIt error code of 150.");      
    }
    
    function testDoNotAllowTempUsersToOrder()
    {
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUserWithCC();
    	$this->assertFalse(isUserResourceATempUser($user_resource));
    	$user_resource->first_name = 'Temp';
    	$user_resource->last_name = 'User';
    	$code = generateCode(5);
    	$user_resource->email = "456-wert-2345-ert-$code@splickit.dum";
    	$user_resource->save();
    	$user_id = $user_resource->user_id;
    	
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours', 3);
		$order_resource = placeOrderFromOrderDataAPIV1($order_data, $time_stamp);
		$this->assertNotNull($order_resource->error,"Should have found an order error due to temp user");
		$this->assertEquals("We're sorry but you do not appear to be logged in. Please log in and try again. We apologize for the inconvenience.",$order_resource->error);
		$this->assertEquals(500, $order_resource->http_code);
    	
    }
    
	function testGoodErrorMessagseOnBadJSON()
    {
    	setContext("com.splickit.order");
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours', 3);
		$json_encoded_data = "{ ";
		$response = placeTheOrder($json_encoded_data);
		$this->assertNotNull($response->error);
		$this->assertEquals("There was a transmission error and we could not understand your request, please try again.",$response->error);
    }
    
    function testGetSendOnTimeStamp()
    {
    	$send_on_time_stamp = 12345;
    	$this->assertEquals(time(), MailIt::getCorrctSendOnTimeStamp($send_on_time_stamp));
    	
    	$send_on_time_stamp = "123sdfg45";
    	$this->assertEquals(time(), MailIt::getCorrctSendOnTimeStamp($send_on_time_stamp));
    	
    	$send_on_time_stamp = 12345678900;
    	$this->assertEquals($send_on_time_stamp, MailIt::getCorrctSendOnTimeStamp($send_on_time_stamp));
    }
    
    function testStageOrderConfirmationEmail()
    {
    	$map_id = MailIt::stageOrderConfirmationEmail(12345,'adam@dummy.com', "hello world", "test message", "Moes", $send_on_time_stamp);
    	$message_resource = Resource::find(new MerchantMessageHistoryAdapter($mimetypes),"$map_id");
    	$this->assertNotNull;
    	$this->assertEquals(12345, $message_resource->order_id);
    	$this->assertEquals('adam@dummy.com', $message_resource->message_delivery_addr);
    	$this->assertEquals('hello world', $message_resource->message_text);
    	$this->assertEquals('subject=test message;from=Moes;',$message_resource->info);
    	$this->assertEquals('Econf',$message_resource->message_format);
    	$this->assertEquals('I', $message_resource->message_type);
    }
    
    function testGetOrderConfirmationTemplatePath()
    {
    	setContext("com.splickit.order");
    	$placeorder_controller = new PlaceOrderController($mt, $u, $r);
    	$path = $placeorder_controller->getOrderConfirmationTemplateFilePath();
    	$this->assertEquals('/email_templates/order_confirmation_templates/order_confirmation.html', $path);
    }
    
    function testGetOrderConfirmationTemplatePathBrandedSkinNoCustome()
    {
    	setContext("com.splickit.pitapit");
    	$placeorder_controller = new PlaceOrderController($mt, $u, $r);
    	$path = $placeorder_controller->getOrderConfirmationTemplateFilePath();
    	$this->assertEquals('/email_templates/order_confirmation_templates/order_confirmation.html', $path);
    }

    function testGetOrderConfirmationTemplatePathCustom()
    {
    	setContext("com.splickit.moes");
    	$placeorder_controller = new PlaceOrderController($mt, $u, $r);
    	$path = $placeorder_controller->getOrderConfirmationTemplateFilePath();
    	$this->assertEquals('/email_templates/order_confirmation_templates/moes_order_confirmation.html', $path);
    }

    function testCreateOrderConfirmationFromPlaceOrder()
    {
    	setContext("com.splickit.order");
		$_SERVER['SKIN']['show_notes_fields'] = true;
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001","last_four"=>"1234"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours', 3);
		$order_data['items'][0]['note'] = "Here is the item note";
		$response = placeOrderFromOrderDataAPIV1($order_data, $time);
		$order_id = $response->order_id;
		$this->assertTrue($order_id > 1000,"should have created a valid order");
		$mmha = new MerchantMessageHistoryAdapter();
		$message_resource = $mmha->getExactResourceFromData(array("order_id"=>$order_id,"message_format"=>'Econf'));
		$this->assertTrue(is_a($message_resource, 'Resource'));
		$message_text = $message_resource->message_text;
		myerror_log("email conf: ".$message_text);
		$this->assertTrue(substr_count($message_text, 'default splickit order confirmation template') > 0);
		$this->assertContains("Here is the item note",$message_text);
    }
        
    function testCreateOrderConfirmationFromPlaceOrderCustom()
    {
    	setContext("com.splickit.moes");
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_resource->branch_id = 112;
    	$merchant_resource->save();
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours', 3);

		$response = placeOrderFromOrderDataAPIV1($order_data, $time);
		$order_id = $response->order_id;
		$this->assertTrue($order_id > 1000,"should have created a valid order");
		
		$mmha = new MerchantMessageHistoryAdapter();
		$records = $mmha->getRecords(array("order_id"=>$order_id));
		$message_resource = $mmha->getExactResourceFromData(array("order_id"=>$order_id,"message_format"=>'Econf'));
		$this->assertTrue(is_a($message_resource, 'Resource'));
		$message_text = $message_resource->message_text;
		//myerror_log("email conf: ".$message_text);
		//file_put_contents('moes_confirmation.html', $message_text);
		$this->assertTrue(substr_count($message_text, 'Subtotal') == 1,"should have only found 1 subtotal");
		$this->assertTrue(substr_count($message_text, 'default splickit order confirmation template') < 1);
		$this->assertTrue(substr_count($message_text, 'this is the moes template') > 0);
    }
    
    function testCreateOrderDisplay()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours', 3);

		$response = placeOrderFromOrderDataAPIV1($order_data, $time);
		$order_id = $response->order_id;
		$this->assertTrue($order_id > 1000,"should have created a valid order");
		$new_order = CompleteOrder::staticGetCompleteOrder($order_id, $mimetypes);
		$placeorder_controller = new PlaceOrderController($mt, $u, $r, 5);
		$order_summary = $placeorder_controller->createOrderSummary($new_order);
		$this->assertCount(PlaceOrderController::ORDER_SUMMARY_SIZE, $order_summary);
		$this->assertNotNull($order_summary['cart_items']);
		$this->assertNotNull($order_summary['receipt_items']);
		$item = $order_summary['cart_items'][0];
		$this->assertNotNull($item['item_name'],"should have found an item name");
		$this->assertEquals("Test Item 1", $item['item_name'],"should print item name not item print name");
		$this->assertNotNull($item['item_price'],"shoudl have found an item price");
		$this->assertNotNull($item['item_quantity'],"should have found an item quantity");
		$this->assertNotNull($item['item_description'],"should have found the list of mods");
    }

    function testNewOrderResourceResponse()
    {
    	$merchant_id = $this->ids['merchant_id'];
        //$merchant_resource = MerchantAdapter::staticGetRecordByPrimaryKey($merchant_id,'MerchantAdapter');
        $merchant_resource = Resource::find(new MerchantAdapter($m),$merchant_id,$options);
        $merchant_resource->trans_fee_type = 'F';
        $merchant_resource->trans_fee_rate = 0.25;
        $merchant_resource->save();
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	$user = logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours', 3);
        $checkout_data_response = getCheckoutResourceFromOrderData($order_data);
        $checkout_receipt_items = $checkout_data_response->order_summary['receipt_items'];
        $checkout_convenience_fee = $checkout_receipt_items[3];
        $this->assertEquals('Convenience Fee',$checkout_convenience_fee['title']);

        $response = placeOrderFromCheckoutResource($checkout_data_response,$user,$merchant_id,1.00,$time);
		$order_id = $response->order_id;
		$this->assertTrue($order_id > 1000,"should have created a valid order");
		$this->assertNotNull($response->order_summary,"should have an order_summary field");
		$order_summary = $response->order_summary['cart_items'];
		$this->assertCount(3, $order_summary);
		$item = $order_summary[0];
		$this->assertNotNull($item['item_name'],"should have found an item name");
		$this->assertNotNull($item['item_price'],"shoudl have found an item price");
		$this->assertNotNull($item['item_quantity'],"should have found an item quantity");
		$this->assertNotNull($item['item_description'],"should have found the list of mods");
		$receipt_items = $response->order_summary['receipt_items'];
		$sub_total = $receipt_items[0];
		$this->assertEquals('Subtotal', $sub_total['title']);
		$tax = $receipt_items[1];
		$this->assertEquals('Tax',$tax['title']);
		$total = $receipt_items[2];
		$this->assertEquals('Tip',$total['title']);
        $convenience_fee = $receipt_items[3];
        $this->assertEquals('Convenience Fee',$convenience_fee['title']);
        $total = $receipt_items[4];
        $this->assertEquals('Total',$total['title']);

        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id, 'Econf');
		$this->assertNotNull($message_resource);
		$message_text = $message_resource->message_text;
		myerror_log($message_text);
		$this->assertTrue(substr_count($message_text, 'Subtotal') == 1);
    }
    
    function testShadowDevice()
    {
        $merchant_id = $this->ids['simple_merchant_id'];
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
		
		$actual_pickup_ts = getTimeStampForDateTimeAndTimeZone(12, 23, 0, 2, 14, 2013, 'America/Denver');
		$order_data['actual_pickup_time'] = $actual_pickup_ts;

        setProperty('new_shadow_device_on','true');
        setProperty('new_shadow_message_frequency',1);
        $response = placeOrderFromOrderDataAPIV1($order_data, $this->time_stamp_for_noon_on_feb14_denver);
        setProperty('new_shadow_device_on','false');
        $order_id = $response->order_id;
		$this->assertTrue($order_id > 1000,"should have created a valid order for shaddow device test");
		// check to see if shadow message was created
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		$shadow_message_format = getProperty('shadow_device_message_format');
		$record = $mmha->getRecord(array("order_id"=>$order_id,"merchant_id"=>10,"message_format"=>$shadow_message_format));
		$this->assertNotNull($record);
		//$this->assert
		
		$order_resource = SplickitController::getResourceFromId($order_id, "Order");
		$this->assertEquals("2013-02-14 12:23:00", $order_resource->pickup_dt_tm);
    	
    }
   
    function testGetPickupTimeStampFromSubmittedData()
    {
    	
    	$poa = new PlaceOrderAdapter($mimetypes);
    	$resource = Resource::factory(new OrderAdapter($mimetypes), array("actual_pickup_time"=>$this->time_stamp_for_noon_on_feb14_denver,"lead_time"=>40));
    	$result = $poa->getPickupTimeStampFromSubmittedData($resource);
    	$this->assertEquals($this->time_stamp_for_noon_on_feb14_denver, $result);
    	
    	$resource = Resource::factory(new OrderAdapter($mimetypes), array("lead_time"=>40));
    	$result = $poa->getPickupTimeStampFromSubmittedData($resource);
    	$this->assertEquals(40, $result/60);
    	
    	$resource = new Resource();
    	$resource->base_minimum_lead_time_without_order_data = 5;
    	$result = $poa->getPickupTimeStampFromSubmittedData($resource);
    	$this->assertEquals(5, $result/60);

    }
    
    function testPlaceOrderWithExactPickupTimeStamp()
    {
    	//$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);
    	//$merchant_id = $merchant_resource->merchant_id;
    	$merchant_id = $this->ids['simple_merchant_id'];
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
		$order_data['lead_time'] = 25;
		
		$actual_pickup_ts = getTimeStampForDateTimeAndTimeZone(12, 23, 0, 2, 14, 2013, 'America/Denver');
		$order_data['actual_pickup_time'] = $actual_pickup_ts;
		
		$response = placeOrderFromOrderDataAPIV1($order_data, $this->time_stamp_for_noon_on_feb14_denver);
		$order_id = $response->order_id;
		$order_resource = SplickitController::getResourceFromId($order_id, "Order");
		$this->assertEquals("2013-02-14 12:23:00", $order_resource->pickup_dt_tm);

		$this->assertNotNull($response->requested_time_string);
		$this->assertNotEmpty($response->requested_time_string);
    }

    function testBadLeadTime()
    {
    	$merchant_id = $this->ids['simple_merchant_id'];
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
		$order_data['lead_time'] = 25;
		
		// set pickupt times stamp to be 62 days in the future
		$actual_pickup_ts = time() + (62*24*60*60);
		$order_data['actual_pickup_time'] = $actual_pickup_ts;
		
		$response = placeOrderFromOrderDataAPIV1($order_data, $the_time);
		$this->assertEquals('Sorry, there was an error with your submitted data, please try again', $response->error,'should have gotten an error with a 62 day lead time');
	}

	function testUserHasSatOnCheckoutScreenSoLongPickupTimeIsInThePast()
	{
    	$merchant_id = $this->ids['simple_merchant_id'];
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', "some note",3);
		
		$actual_pickup_ts = getTimeStampForDateTimeAndTimeZone(12, 23, 0, 2, 14, 2013, 'America/Denver');
		$order_data['actual_pickup_time'] = $actual_pickup_ts;
		$order_data['lead_time'] = -3;
 		
		//merchant should have a lead time of 20 so set now to be 12:03 or reated
		$time_stamp_of_order_submission = getTimeStampForDateTimeAndTimeZone(12, 26, 0, 2, 14, 2013, 'America/Denver');
		
		$response = placeOrderFromOrderDataAPIV1($order_data, $time_stamp_of_order_submission);
		$this->assertNotNull($response->error,"We should have gotten an error because the chosen time was in the past");
		$this->assertEquals("ORDER ERROR! Your pickup time has expired. Please select a new pickup time and proceed to check out.", $response->error);
		
	}
	
	function testUserHasSatOnCheckoutScreenForGreaterThan5Minutes()
	{
    	$merchant_id = $this->ids['simple_merchant_id'];

    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', "some note");
		
		$actual_pickup_ts = getTimeStampForDateTimeAndTimeZone(12, 23, 0, 2, 14, 2013, 'America/Denver');
		$order_data['actual_pickup_time'] = $actual_pickup_ts;
		
		//merchant should have a lead time of 20 so set now to be 12:03 or reated
		$time_stamp_of_order_submission = getTimeStampForDateTimeAndTimeZone(12, 9, 0, 2, 14, 2013, 'America/Denver');
		
		$response = placeOrderFromOrderDataAPIV1($order_data, $time_stamp_of_order_submission);
		$this->assertNotNull($response->error,"We should have gotten an error because the chosen time was too close to now");
		$this->assertEquals("ORDER ERROR! Your pickup time has expired. Please select a new pickup time and proceed to check out.", $response->error);
		
	}
	
	function testUserHasSatOnCheckoutScreenForLessThan4Minutes()
	{
    	$merchant_id = $this->ids['simple_merchant_id'];

    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	$user = logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', "some note");
        $checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $response = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,getTomorrowTwelveNoonTimeStampDenver()+120);
		$this->assertNull($response->error,"We should NOT have gotten an error because the chosen time was minimum, minimum lead was default of merchant, and time was stale by less than 5 minutes");
		$this->assertTrue($response->order_id > 1000);
		// and new pickupt ttime should be 20 minutes ahead of the new time 
		$order_id = $response->order_id;
		$order_resource = SplickitController::getResourceFromId($order_id, "Order");
        $expected_pickup = date('Y-m-d H:i:s',(getTomorrowTwelveNoonTimeStampDenver() + (22*60)));
		$this->assertEquals($expected_pickup, $order_resource->pickup_dt_tm);
		
	}
	
	function testUserHasSatOnCheckoutScreenTooLongButWithSkipHours()
	{
	  $_SERVER['SKIN']['show_notes_fields'] = true;
    	$merchant_id = $this->ids['simple_merchant_id'];

    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
		
		$actual_pickup_ts = getTimeStampForDateTimeAndTimeZone(12, 23, 0, 2, 14, 2013, 'America/Denver');
		$order_data['actual_pickup_time'] = $actual_pickup_ts;
		
		//merchant should have a lead time of 20 so set now to be 12:03 or reated
		$time_stamp_of_order_submission = getTimeStampForDateTimeAndTimeZone(12, 10, 0, 2, 14, 2013, 'America/Denver');
		
		$response = placeOrderFromOrderDataAPIV1($order_data, $time_stamp_of_order_submission);
		$this->assertNull($response->error,"We should not have gotten an error because skip hours was used");
		$order_id = $response->order_id;
		$this->assertTrue($order_id > 1000);
		
	}
	
    function testMessageAreScheduledCorrectlyWithNewActualPickupTimeUsed()
    {
    	//$the_time = getTimeStampForDateTimeAndTimeZone(date('H'),date('i'),date('s'),date('m'),date('d'), date('Y'), 'America/Denver');
        $the_time = getTomorrowTwelveNoonTimeStampDenver();
    	$merchant_id = $this->ids['simple_merchant_id'];
    	$user_resource = createNewUserWithCCNoCVV();
    	$user_id = $user_resource->user_id;
    	$user = logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'dum note');
		$order_data['lead_time'] = 40;
		
		$actual_pickup_ts = $the_time+(35*60);
		// have to set seconds to 00 since the SP strips them off
		$pickup_time_string = date('Y-m-d H:i:00',$actual_pickup_ts);
		$order_data['actual_pickup_time'] = $actual_pickup_ts;
		
		$response = placeOrderFromOrderDataAPIV1($order_data,$the_time);
		$order_id = $response->order_id;
		$order_resource = SplickitController::getResourceFromId($order_id, "Order");
		$this->assertEquals($pickup_time_string, $order_resource->pickup_dt_tm);
		
		// now check to see the X message was scheduled correctly
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		$order_messages = $mmha->getAllOrderMessages($order_id);
		foreach ($order_messages as $message_resource) {
			if ($message_resource->message_type == 'X')	{
				$order_message_resource = $message_resource;
			}
		}
    	$this->assertNotNull($order_message_resource);
    	$scheduled_string = date('Y-m-d H:i:s',$order_message_resource->next_message_dt_tm);
    	$expected_scheduled_string = date('Y-m-d H:i:s',$actual_pickup_ts-1200);
    	$this->assertTrue(($actual_pickup_ts-1198 > $order_message_resource->next_message_dt_tm) && ($order_message_resource->next_message_dt_tm > $actual_pickup_ts-1202),"Should have been scheduled at $expected_scheduled_string but was scheduled at $scheduled_string");

    }

    function testPlaceOrderWithCredit()
    {
    	$this->user_resource->balance = 50.00;
    	$this->user_resource->save();
    	$user = logTestUserIn($this->user_resource->user_id);
    	$time_stamp = getTodayTwelveNoonTimeStampDenver();
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['simple_merchant_id'], 'pickup', 'skip hours');
		$order_resource = placeOrderFromOrderDataAPIV1($order_data, $time_stamp);
    	$this->assertNull($order_resource->error,"should not have gotten an error");
    	
    	//check for no double bill
    	$grand_total = $order_resource->grand_total;
		$new_balance = 50 - $grand_total;
		$new_user_resource = Resource::find(new UserAdapter($mimetypes),''.$this->user_resource->user_id);
		$this->assertEquals($new_balance,$new_user_resource->balance);

    }
    
    function testAfterMidnightOrder()
    {
    	$time_stamp = getTimeStampForDateTimeAndTimeZone(01, 0, 0, 10, 24, 2013, 'America/Denver');
    	
		$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);
		$merchant_id = $merchant_resource->merchant_id;
    	
		//set the hours for the date
		$hours_data['merchant_id'] = $merchant_id;
		$hours_data['hour_type'] = 'R';
    	$hours_options[TONIC_FIND_BY_METADATA] = $hours_data;
    	$hours_options[TONIC_SORT_BY_METADATA] = " day_of_week ASC ";
    	$hour_adapter = new HourAdapter($mimetypes);
    	$hours_resources = Resource::findAll($hour_adapter,'',$hours_options);
    	
    	//set weds hours
    	$hours_resources[3]->open = "09:00";
    	$hours_resources[3]->close = "00:00";
    	$hours_resources[3]->save();
    	//set thurs hours
    	$hours_resources[4]->open = "08:45";
    	$hours_resources[4]->close = "02:10";
    	$hours_resources[4]->save();
    	//set fris hours
    	$hours_resources[5]->open = "09:00";
    	$hours_resources[5]->close = "03:15";
    	$hours_resources[5]->second_close = "17:00";
    	$hours_resources[5]->save();
				
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours');
    	$order_data['lead_time'] = 20;
		$checkout_data_resource = getCheckoutDataWithThrottling($order_data, $merchant_id, $time_stamp);
		$this->assertNotNull($checkout_data_resource->lead_times_array);
		$lead_times_array = $checkout_data_resource->lead_times_array;
		$this->assertTrue(count($lead_times_array) > 10);
		$first_pickup = getTimeStampForDateTimeAndTimeZone(01, 20, 0, 10, 24, 2013, 'America/Denver');
		$actual_pickup_string = date('Y-m-d H:i:s',$lead_times_array[0]);
		$expected = '2013-10-24 01:20:00';
		$this->assertEquals($first_pickup, $lead_times_array[0]);
		$count = count($lead_times_array);
		$last_pickup = getTimeStampForDateTimeAndTimeZone(02, 05, 0, 10, 24, 2013, 'America/Denver');
		$actual_last_pickup_string = date('Y-m-d H:i:s',$lead_times_array[$count-1]);
		$expected = '2013-10-24 02:10:00';
		$this->assertEquals($expected, $actual_last_pickup_string);
		
    }
    
    function testArizonaStoreNoDayLightSavings()
    {
    	$time_stamp = getTodayTwelveNoonTimeStampDenver();
    	
		$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);
		$merchant_resource->state = 'AZ';
		$merchant_resource->save();
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours');
    	$order_data['lead_time'] = 20;
		$response = placeOrderFromOrderDataAPIV1($order_data, $this->time_stamp_for_noon_on_feb14_denver);
		$order_id = $response->order_id;
		$order_resource = SplickitController::getResourceFromId($order_id, "Order");
		$boolena = date("I");
		$this->assertEquals("2013-02-14 12:20:00", $order_resource->pickup_dt_tm);
    }

    function testArizonaStoreDayLightSavings()
    {
    	$time_stamp = getTodayTwelveNoonTimeStampDenver();
    	
		$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);
		$merchant_resource->state = 'AZ';
		$merchant_resource->save();
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours');
    	$order_data['lead_time'] = 20;
    	$time_stamp_for_noon_on_may14_denver = getTimeStampForDateTimeAndTimeZone(12, 0, 0, 5, 14, 2013, 'America/Denver');
		$response = placeOrderFromOrderDataAPIV1($order_data, $time_stamp_for_noon_on_may14_denver);
		$order_id = $response->order_id;
		$order_resource = SplickitController::getResourceFromId($order_id, "Order");
		$boolena = date("I");
		$this->assertEquals("2013-05-14 11:20:00", $order_resource->pickup_dt_tm);
    }

    function testLocalStore()
    {
    	$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours');
		$order_data['lead_time'] = 20;
		$response = placeOrderFromOrderDataAPIV1($order_data, $this->time_stamp_for_noon_on_feb14_denver);
		$order_id = $response->order_id;
		$order_resource = SplickitController::getResourceFromId($order_id, "Order");
		$this->assertEquals("2013-02-14 12:20:00", $order_resource->pickup_dt_tm);
    }
    
    function testEastCoastStore()
    {
		$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);
		$merchant_resource->state = 'NY';
		$merchant_resource->time_zone = -5;
		$merchant_resource->save();
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours');
		$order_data['lead_time'] = 20;
		$response = placeOrderFromOrderDataAPIV1($order_data, $this->time_stamp_for_noon_on_feb14_denver);
		$order_id = $response->order_id;
		$order_resource = SplickitController::getResourceFromId($order_id, "Order");
		$this->assertEquals("2013-02-14 14:20:00", $order_resource->pickup_dt_tm);
    }

    function testLargeOrderRejectionFromSmallLeadTime()
    {
    	$time_stamp = getTomorrowTwelveNoonTimeStampDenver();

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
    	
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','no note',10);
        $checkout_resource = getCheckoutResourceFromOrderData($order_data,$time_stamp);
        $checkout_resource->lead_times_array = array($time_stamp+1200);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$time_stamp);
    	$this->assertNotNull($order_resource->error);
		$this->assertEquals("ORDER ERROR! We're sorry, but the size of your order requires a minimum preptime of 37 minutes. Please choose a pickup time of 12:37 pm or later.",$order_resource->error);
    	// need a 43 minute lead time for this order
        $checkout_resource->lead_times_array = array($time_stamp+(43*61));
        $order_resource2 = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$time_stamp);
    	$this->assertNull($order_resource2->error,"should not have gotten an error: ".$order_resource2->error);
    	$this->assertTrue($order_resource2->order_id > 1000);
    	
    }
    
    function testLargeOrderAcceptanceBecauseAllCookiesAndCookiesHaveModifiersButTheyAreNotEntres()
    {
    	$time_stamp = getTodayTwelveNoonTimeStampDenver();
    	
    	$menu_id = createTestMenuWithNnumberOfItems(1);
    	$menu = CompleteMenu::getCompleteMenu($menu_id);
    	$menu_type = $menu['menu_types'][0];
    	$menu_type_resource = SplickitController::getResourceFromId($menu_type['menu_type_id'], "MenuType");
    	$menu_type_resource->cat_id = 'D';
    	$menu_type_resource->save();
    	
    	$merchant_resource = createNewTestMerchant($menu_id);

    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_resource->merchant_id,'pickup','no note',15);
    	$order_data['lead_time'] = 20;
    	
    	$checkout_data_resource = getCheckoutDataWithThrottling($order_data, $merchant_resource->merchant_id, $time_stamp);
    	$order_resource = placeOrderFromOrderDataAPIV1($order_data, $time_stamp);
    	// should not need a long lead time for this order since its all cookies
    	$this->assertNull($order_resource->error);
    	$this->assertTrue($order_resource->order_id > 1000);
    	
    	$this->assertEquals($checkout_data_resource->total_tax_amt, $order_resource->total_tax_amt);
    }

    function testPlaceOrderDayClosed()
    {
        // set all days closed and try to order
        $sql = "UPDATE Hour SET day_open = 'N' WHERE merchant_id = ".$this->ids['merchant_id'];
        $hour_adapter = new HourAdapter($mimetypes);
        $hour_adapter->_query($sql);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'pickup','the note');
        $checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNotNull($checkout_resource->error);
        $this->assertEquals("We're sorry, this merchant is closed for mobile ordering right now.", $checkout_resource->error);

    }

    function testIsCCProcessorShutDown()
    {
    	$place_order_controller = new PlaceOrderController($mt, $u, $r,t);
    	$this->assertFalse($place_order_controller->testForCCProcessorShutDown('I'));
    	$this->assertFalse($place_order_controller->testForCCProcessorShutDown('M'));
    	$this->assertFalse($place_order_controller->testForCCProcessorShutDown('F'));
    	setProperty('inspire_pay_ordering_on', 'false');
    	$this->assertTrue($place_order_controller->testForCCProcessorShutDown('I'));
    	$this->assertFalse($place_order_controller->testForCCProcessorShutDown('M'));
    	$this->assertFalse($place_order_controller->testForCCProcessorShutDown('F'));
    	setProperty('inspire_pay_ordering_on', 'true');
    	setProperty('mercury_pay_ordering_on', 'false');
    	$this->assertFalse($place_order_controller->testForCCProcessorShutDown('I'));
    	$this->assertTrue($place_order_controller->testForCCProcessorShutDown('M'));
    	$this->assertFalse($place_order_controller->testForCCProcessorShutDown('F'));
    	setProperty('fpn_ordering_on', 'false');
    	setProperty('mercury_pay_ordering_on', 'true');
    	$this->assertFalse($place_order_controller->testForCCProcessorShutDown('I'));
    	$this->assertFalse($place_order_controller->testForCCProcessorShutDown('M'));
    	$this->assertTrue($place_order_controller->testForCCProcessorShutDown('F'));
    }
    
    function testOrderingShutDownForBrandAndCCProcessor()
    {
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
		$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	setProperty('inspire_pay_ordering_on', 'false');
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
    	$checkout_data = getCheckoutDataWithThrottling($order_data, $merchant_id, $time_stamp);
    	$this->assertNotNull($checkout_data->error);
    	$this->assertEquals(getProperty('cc_processor_ordering_off_message'), $checkout_data->error);

		$order_resource = placeOrderFromOrderDataAPIV1($order_data, $time_stamp);
    	$this->assertNotNull($order_resource->error);
    	$this->assertEquals(getProperty('cc_processor_ordering_off_message'), $checkout_data->error);
    	
    	//assign merchant to mercury pay and try again
    	$merchant_resource->cc_processor = 'M';
    	$merchant_resource->save();
    	
		$checkout_data = getCheckoutDataWithThrottling($order_data, $this->ids['merchant_id'], $time_stamp);
    	$this->assertNull($checkout_data->error);
    	
		$order_resource = placeOrderFromOrderDataAPIV1($order_data, $time_stamp);
    	$this->assertNull($order_resource->error);
    	$order_id = $order_resource->order_id;
    	$this->assertTrue($order_id > 1000);
    }

// */

    function testConvertItemsToOrderDataForOrderProcessing()
    {
    	$menu_id = createTestMenuWithNnumberOfItems(1);
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 1);
    	$complete_menu = CompleteMenu::getCompleteMenu($menu_id);
    	$size_id = $complete_menu['menu_types'][0]['sizes'][0]['size_id'];
    	$modifier_id = $complete_menu['modifier_groups'][0]['modifier_items'][0]['modifier_item_id'];
    	$zero_size_modifier_size_price_record = $complete_menu['modifier_groups'][0]['modifier_items'][0]['modifier_size_maps'][0];
    	$zero_size_modifier_size_price_record_id = $zero_size_modifier_size_price_record['modifier_size_id'];
    	$this->assertEquals(0, $zero_size_modifier_size_price_record['size_id']);
    	
    	$merchant_resource = createNewTestMerchant($menu_id);
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_resource->merchant_id, 'pickup', 'skip hours');
    	$items = $order_data['items'];
    	$original_modifier = $items[0]['mods'][0];
    	$this->assertNull($original_modifier['mod_sizeprice_id']);
    	
    	$place_order_controller = new PlaceOrderController($mt, $u, $r);
    	$converted_items = $place_order_controller->convertCartItemsFromItemIdAndSizeIdToItemSizeIdAKASizePriceId($items, 0);
    	$converted_modifier = $converted_items[0]['mods'][0];
    	$this->assertNotNull($converted_modifier['mod_sizeprice_id']);
    	$this->assertEquals($zero_size_modifier_size_price_record_id, $converted_modifier['mod_sizeprice_id']);
    	
    	// now add a price at a certain size and see if it picks it up.
    	$modifier_size_map_adapter = new ModifierSizeMapAdapter($mimetypes);
    	$data['size_id'] = $size_id;
    	$data['modifier_item_id'] = $modifier_id;
    	$data['merchant_id'] = 0;
    	$data['modifier_price'] = 1.10;
    	$resource = Resource::factory($modifier_size_map_adapter, $data);
    	$resource->save();
    	$new_modifier_price_record_id = $modifier_size_map_adapter->_insertId();
    	$converted_items2 = $place_order_controller->convertCartItemsFromItemIdAndSizeIdToItemSizeIdAKASizePriceId($items, 0);
    	$converted_modifier2 = $converted_items2[0]['mods'][0];
    	$this->assertNotNull($converted_modifier2['mod_sizeprice_id']);
    	$this->assertEquals($new_modifier_price_record_id, $converted_modifier2['mod_sizeprice_id']);
    }
    
    /**
     * @expectedException MenuNotCurrentException
     */
    function testConvertItemsToOrderDataWithOutOfDataMenu()
    {
		$complete_menu = CompleteMenu::getCompleteMenu($this->ids['menu_id']);
    	//$size_id = $complete_menu['menu_types'][0]['sizes'][0]['size_id'];
    	//$modifier_id = $complete_menu['modifier_groups'][0]['modifier_items'][0]['modifier_item_id'];
		
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours');
    	$items = $order_data['items'];
    	// set it to some dum number
    	$items[0]['mods'][0]['modifier_item_id'] = 1234;
    	$place_order_controller = new PlaceOrderController($mt, $u, $r);
    	$converted_items = $place_order_controller->convertCartItemsFromItemIdAndSizeIdToItemSizeIdAKASizePriceId($items, 0);

    }

	function testFeeLabelToPitapitInConfirmationMailAndItemName()
	{
		$skin_resource = getOrCreateSkinAndBrandIfNecessary("Pitapit", "Pita Pit", 13, 282);
		setContext("com.splickit.pitapit");

		$menu_resource = createNewMenu();
		$menu_id = $menu_resource->menu_id;
		$menu_type_resource = createNewMenuType($menu_id, 'Test Menu Pitapit');
		$size_resource = createNewSize($menu_type_resource->menu_type_id, 'Test Size Pitapit');
		$item_resource = createItem("Item Complete Name", $size_resource->size_id, $menu_type_resource->menu_type_id, 200, "Item Print Name");
		$this->assertNotNull($item_resource->item_id);
		$this->assertEquals("Item Print Name", $item_resource->item_print_name);

		$merchant_resource = createNewTestMerchant($menu_id, 282);//395 Amicis brand_id
		attachMerchantToSkin($merchant_resource->merchant_id, $skin_resource->skin_id);
		$merchant_id = $merchant_resource->merchant_id;
		$merchant_resource = Resource::find(new MerchantAdapter($m), $merchant_id, $options);
		$merchant_resource->trans_fee_type = 'P';
		$merchant_resource->trans_fee_rate = 0.25;
		$merchant_resource->save();

		$user_resource = createNewUser(array("flags" => "1C20000001"));
		$user_id = $user_resource->user_id;
		$user = logTestUserIn($user_id);

		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours', 3);
		$response = placeOrderFromOrderDataAPIV1($order_data, $time);
		$order_id = $response->order_id;
		$this->assertTrue($order_id > 1000, "should have created a valid order to Amicis");
		$mmha = new MerchantMessageHistoryAdapter();

		$message_resource = $mmha->getExactResourceFromData(array("order_id" => $order_id, "message_type" => 'I')); //gets message template from databa base merchant message history
		$this->assertTrue(is_a($message_resource, 'Resource'));
		$message_text = $message_resource->message_text;
		myerror_log("email confirmation: " . $message_text);
		$this->assertContains("Convenience Fee", $message_text);
		$this->assertContains("Item Complete Name", $message_text);
		$this->assertNotContains("Item Print Name", $message_text);
	}

	function testAmicisFeeLabelInConfirmationMailAndItemName()
	{
		$skin_resource = getOrCreateSkinAndBrandIfNecessary("Amicis", "Amici's", 104, 395);
		setContext("com.splickit.amicis");

		$menu_resource = createNewMenu();
		$menu_id = $menu_resource->menu_id;
		$menu_type_resource = createNewMenuType($menu_id, 'Test Menu Amicis');
		$size_resource = createNewSize($menu_type_resource->menu_type_id, 'Test Size Amicis');
		$item_resource = createItem("Item Complete Name", $size_resource->size_id, $menu_type_resource->menu_type_id, 200, "Item Print Name");
		$this->assertNotNull($item_resource->item_id);
		$this->assertEquals("Item Print Name", $item_resource->item_print_name);

		$merchant_resource = createNewTestMerchant($menu_id, 395);//395 Amicis brand_id
		attachMerchantToSkin($merchant_resource->merchant_id, $skin_resource->skin_id);
		$merchant_id = $merchant_resource->merchant_id;
		$merchant_resource = Resource::find(new MerchantAdapter($m), $merchant_id, $options);
		$merchant_resource->trans_fee_type = 'P';
		$merchant_resource->trans_fee_rate = 0.25;
		$merchant_resource->save();

		$user_resource = createNewUser(array("flags" => "1C20000001"));
		$user_id = $user_resource->user_id;
		$user = logTestUserIn($user_id);

		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours', 3);
		$response = placeOrderFromOrderDataAPIV1($order_data, $time);
		$order_id = $response->order_id;
		$this->assertTrue($order_id > 1000, "should have created a valid order to Amicis");
		$mmha = new MerchantMessageHistoryAdapter();

		$message_resource = $mmha->getExactResourceFromData(array("order_id" => $order_id, "message_type" => 'I')); //gets message template from databa base merchant message history
		$this->assertTrue(is_a($message_resource, 'Resource'));
		$message_text = $message_resource->message_text;
		myerror_log("email confirmation: " . $message_text);
		$this->assertContains("SF Surcharge", $message_text);
		$this->assertContains("Item Complete Name", $message_text);
		$this->assertNotContains("Item Print Name", $message_text);
	}

	function testAmicisFeeLabelInCheckoutAndConfirmation()
	{
		$skin_resource = getOrCreateSkinAndBrandIfNecessary("Amicis", "Amici's", 104, 395);
		setContext("com.splickit.amicis");
		$menu_id = createTestMenuWithNnumberOfItems(5);
		$merchant_resource = createNewTestMerchant($menu_id, 395);
		attachMerchantToSkin($merchant_resource->merchant_id, $skin_resource->skin_id);
		$merchant_id = $merchant_resource->merchant_id;
		//$merchant_resource = MerchantAdapter::staticGetRecordByPrimaryKey($merchant_id,'MerchantAdapter');
		$merchant_resource = Resource::find(new MerchantAdapter($m), $merchant_id, $options);
		$merchant_resource->trans_fee_type = 'F';
		$merchant_resource->trans_fee_rate = 0.25;
		$merchant_resource->save();

		$user_resource = createNewUser(array("flags" => "1C20000001"));
		$user_id = $user_resource->user_id;
		$user = logTestUserIn($user_id);

		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours', 3);
		$checkout_data_response = getCheckoutResourceFromOrderData($order_data);
		$checkout_receipt_items = $checkout_data_response->order_summary['receipt_items'];
		$checkout_convenience_fee = $checkout_receipt_items[2];
		$this->assertEquals('SF Surcharge', $checkout_convenience_fee['title']);

		$response = placeOrderFromCheckoutResource($checkout_data_response, $user, $merchant_id, 1.00, $time);
		$order_id = $response->order_id;
		$this->assertTrue($order_id > 1000, "should have created a valid order");
		$this->assertNotNull($response->order_summary, "should have an order_summary field");
		$order_summary = $response->order_summary['receipt_items'];
		$this->assertCount(4, $order_summary);
		$this->assertEquals('SF Surcharge', $order_summary[2]['title']);
		$this->assertEquals(104, $response->skin_id);//104 is the amicis skin id
	}
    
 	static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['default_tz'] = $tz;
    	date_default_timezone_set("America/Denver");
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	
    	$skin_resource = createWorldHqSkin();

    	$ids['skin_id'] = $skin_resource->skin_id;
    	
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;
    	
    	$simple_menu_id = createTestMenuWithOneItem("item_one");
    	$ids['simple_menu_id'] = $simple_menu_id;
    	
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);

    	$merchant_resource = createNewTestMerchant($menu_id);
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
    	$merchant_resource2 = createNewTestMerchant($simple_menu_id);
    	attachMerchantToSkin($merchant_resource2->merchant_id, $ids['skin_id']);
    	$ids['simple_merchant_id'] = $merchant_resource2->merchant_id;

    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;
    	
    	$_SERVER['log_level'] = 5; 
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
	PlaceOrderTest::main();
}

?>