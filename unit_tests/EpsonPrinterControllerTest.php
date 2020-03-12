<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class EpsonPrinterControllerTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		setProperty("new_shadow_device_on", "false");
		
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
		setProperty("new_shadow_device_on", "true");
    }

    function testNewFormat()
    {
        $menu_id = createTestMenuWithNnumberOfItems(5);

        $item_sizes = CompleteMenu::getAllItemSizesAsResources($menu_id,0);
        foreach ($item_sizes as $index=>$item_size_resource) {
            if ($index == 3) {
                $item_size_resource->price = 11.88;
                $item_size_resource->save();
            } else if ($index == 4) {
                $item_size_resource->price = .05;
                $item_size_resource->save();
            }
        }
        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 5);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 0);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 3);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[3]['item_id'], $modifier_group_id, 1);

        $bad_chars = array(';', '*', '#', '@','&','<','>');

        //set reasonable names
        $modifier_item_resources = Resource::findAll(new ModifierItemAdapter(getM()),null,[3=>['modifier_group_id'=>$modifier_group_id]]);
        foreach ($modifier_item_resources as $index=>$modifier_item_resource) {
            $modifier_item_resource->modifier_item_name = 'ModifierName '.($index+1);
            $modifier_item_resource->modifier_item_print_name = 'ModifierPrintName '.($index+1);
            $modifier_item_resource->save();
        }

        // set bad characters on item names
        $item_resources = CompleteMenu::getAllMenuItemsAsResources($menu_id);
        foreach ($item_resources as $index=>$item_resource) {
            $num = $index+1;
            $item_resource->item_name = "Bad".$bad_chars[$index]."Item $index";
            $item_resource->item_print_name = "Bad".$bad_chars[$index]."Item $index";
            $item_resource->save();
        }



        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_numeric_id = $merchant_resource->numeric_id;
        $merchant_id = $merchant_resource->merchant_id;
        attachMerchantToSkin($merchant_resource->merchant_id, $this->ids['skin_id']);
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_resource->merchant_id,'SA','Epson','X');

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'pickup','some dumb note',5);
        $cart_data['items'][0]['mods'] = [];
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource->note = 'we <3 it!';
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;

        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'SA');
        $message_resource->next_message_dt_tm = time() - 10;
        $message_resource->save();

        $url = "/messagemanager/getnextmessagebymerchantid/$merchant_numeric_id/epsonprinter/message.txt";
        $request = createRequestObject($url,'GET');
        $epson_printer_controller = ControllerFactory::generateFromUrl($url,$m,$u,$request);
        $response = $epson_printer_controller->pullNextMessageResourceByMerchant($merchant_numeric_id);
        $mmh_id = $response->message_id;
        $message_text = $response->message_text;
        myerror_log($message_text);
        foreach($bad_chars as $bad_char) {
            $this->assertNotContains("Bad$bad_char",$message_text);
        }

        $this->assertNotContains('<3',$message_text,"it should have stripped the bad character '<'");
        $this->assertNotContains('it!',$message_text,"it should have stripped the bad character '!'");
        $epson_printer_controller->message_resource->message_text = $response->message_text;
        $epson_printer_controller->markMessageDelivered();

        $final_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'SA');
        $output = $final_message_resource->message_text;

    }


    function testPlaceDeliveryOrderWithCartSimple()
    {
        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        $merchant_numeric_id = $merchant_resource->numeric_id;

        $data = array("merchant_id" => $merchant_resource->merchant_id);
        $mdpd = new MerchantDeliveryPriceDistanceAdapter(getM());
        $mdpd_resource = $mdpd->getExactResourceFromData($data);
        $this->assertNotNull($mdpd_resource, "should have found a merchant delivery price distance resource");
        $mdpd_resource->distance_up_to = 50.0;
        $delivery_charge = 8.88;
        $mdpd_resource->price = $delivery_charge;
        $mdpd_resource->save();



        attachMerchantToSkin($merchant_resource->merchant_id, $this->ids['skin_id']);
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_resource->merchant_id,'SA','Epson','X');

        //MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);

        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'delivery');
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'pickup');

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $json = '{"user_addr_id":null,"user_id":"' . $user_id . '","name":"this & that","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "Application/json";
        $request->_parseRequestBody();
        $request->method = 'POST';
        $request->url = "/users/" . $user['uuid'] . "/userdeliverylocation";
        $user_controller = new UserController(getM(), $user, $request, 5);
        //$response = $user_controller->setDeliveryAddr();
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id", 'GET', $body, $mimetype);
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range), "should have found the 'is in delivery range' field");
        $this->assertTrue($resource->is_in_delivery_range, " the is in delivery range should be true");

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note', 2);
        $cart_data['user_addr_id'] = $user_address_id;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $order_resoure = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resoure->error);
        $order_id = $order_resoure->order_id;

        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'SA');
        $message_resource->next_message_dt_tm = time() - 10;
        $message_resource->save();

        $url = "/messagemanager/getnextmessagebymerchantid/$merchant_numeric_id/epsonprinter/message.txt";
        $request = createRequestObject($url,'GET');
        $epson_printer_controller = ControllerFactory::generateFromUrl($url,$m,$u,$request);
        $response = $epson_printer_controller->pullNextMessageResourceByMerchant($merchant_numeric_id);
        $mmh_id = $response->message_id;
        $message_text = $response->message_text;
        myerror_log($message_text);

    }



    function testSetViewed()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_numeric_id = $merchant_resource->numeric_id;
        $merchant_id = $merchant_resource->merchant_id;
        attachMerchantToSkin($merchant_resource->merchant_id, $this->ids['skin_id']);
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_resource->merchant_id,'SUW','Epson','X');

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource->note = 'we <3 it!';
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;

        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'SUW');
        $message_resource->next_message_dt_tm = time() - 10;
        $message_resource->save();

        $url = "/messagemanager/getnextmessagebymerchantid/$merchant_numeric_id/epsonprinter/message.txt";
        $request = createRequestObject($url,'GET');
        $epson_printer_controller = ControllerFactory::generateFromUrl($url,$m,$u,$request);
        $response = $epson_printer_controller->pullNextMessageResourceByMerchant($merchant_numeric_id);
        $mmh_id = $response->message_id;
        $message_text = $response->message_text;
        $this->assertNotContains('something with & and all',$message_text);
        $this->assertNotContains('<3',$message_text,"it should have stripped the bad character '<'");
        $this->assertNotContains('it!',$message_text,"it should have stripped the bad character '!'");
        $epson_printer_controller->markMessageDelivered();

        $body = "ConnectionType=GetRequest&ID=99";
        $request2 = createRequestObject($url,'POST',$body,"multipart/form-data");
        $epson_printer_controller2 = ControllerFactory::generateFromUrl($url,$m,$u,$request2);

        $message_resource2 = $epson_printer_controller2->pullNextMessageResourceByMerchant($merchant_numeric_id);
        $epson_printer_controller2->markMessageDelivered();
        $message_text = $message_resource2->message_text;

        // now check to see that viewed was set to 'N'
        $db_message_resource2 = SplickitController::getResourceFromId($mmh_id, 'MerchantMessageHistory');
        $this->assertEquals('S',$db_message_resource2->locked);
        $this->assertNotEquals('0000-00-00 00:00:00',$db_message_resource2->sent_dt_tm);
        $this->assertEquals('N',$db_message_resource2->viewed,"Viewed should be set to 'N'");
        return $order_id;
    }

    /**
     * @depends testSetViewed
     */
    function testCallback($order_id)
    {
        $order_resource = Resource::find(new OrderAdapter($m),"$order_id");
        $merchant_resource = Resource::find(new MerchantAdapter(),"".$order_resource->merchant_id);
        $merchant_numeric_id = $merchant_resource->numeric_id;
        $merchant_id = $merchant_resource->merchant_id;
        $body = 'ConnectionType=SetResponse&ID=&ResponseFile=<?xml version="1.0" encoding="UTF-8"?><PrintResponseInfo Version="2.00"><ePOSPrint><Parameter><devid>local_printer</devid><printjobid>'.$order_id.'</printjobid></Parameter><PrintResponse><response xmlns="http://www.epson-pos.com/schemas/2011/03/epos-print" success="true" code="" status="251854870" battery="0"/></PrintResponse></ePOSPrint></PrintResponseInfo>';

        //$body = 'ConnectionType=SetResponse&ID=&Name=X3EL000754&ResponseFile=%3C%3Fxml%20version%3D%221.0%22%20encoding%3D%22UTF-8%22%3F%3E%0D%0A%3CPrintResponseInfo%20Version%3D%222.00%22%3E%0D%0A%20%20%3CePOSPrint%3E%0D%0A%20%20%20%20%3CParameter%3E%0D%0A%20%20%20%20%20%20%3Cdevid%3Elocal_printer%3C%2Fdevid%3E%0D%0A%20%20%20%20%20%20%3Cprintjobid%3E18087478%3C%2Fprintjobid%3E%0D%0A%20%20%20%20%3C%2FParameter%3E%0D%0A%20%20%20%20%3CPrintResponse%3E%0D%0A%20%20%20%20%20%20%3Cresponse%20xmlns%3D%22http%3A%2F%2Fwww.epson-pos.com%2Fschemas%2F2011%2F03%2Fepos-print%22%20success%3D%22true%22%20code%3D%22%22%20status%3D%22251658262%22%20battery%3D%220%22%2F%3E%0D%0A%20%20%20%20%3C%2FPrintResponse%3E%0D%0A%20%20%3C%2FePOSPrint%3E%0D%0A%3C%2FPrintResponseInfo%3E%0D%0A';
        $url = "/messagemanager/getnextmessagebymerchantid/$merchant_numeric_id/epsonprinter/message.txt";
        $request = createRequestObject($url,'POST',$body,"multipart/form-data");
        $epson_printer_controller = ControllerFactory::generateFromUrl($url,$m,$u,$request);

        $message_resource = $epson_printer_controller->pullNextMessageResourceByMerchant($merchant_numeric_id);

        // now check to see that viewed was set to 'V'
        $db_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'SUW');
        $this->assertEquals('S',$db_message_resource->locked);
        $this->assertNotEquals('0000-00-00 00:00:00',$db_message_resource->sent_dt_tm);
        $this->assertEquals('V',$db_message_resource->viewed,"Viewed should be set to 'V'");
    }
    
    function testEpsonControllerFactory()
    {
    	$url = "http://localhost:8888/app2/messagemanager/getnextmessagebymerchantid/1080/epsonprinter/message.txt";
    	$message_controller = ControllerFactory::generateFromUrl($url, $mimetypes, $user, $request, 5);
    	$this->assertTrue(is_a($message_controller, 'EpsonPrinterController'), 'Should have created an EpsonPrinterController');
    }
    
    function testNoPulledMessageAvailable()
    {
    	$epsonprinter_controller = new EpsonPrinterController($mt, $u, $r,5);
    	$response = $epsonprinter_controller->getNoPulledMessageAvailableResponse();
    	$this->assertEquals(200, $response->statusCode);
    	$headers = $response->headers;
    	$this->assertCount(2, $headers);
    	$this->assertEquals('application/xml; charset=utf-8', $headers['Content-Type']);
    	$this->assertEquals('0',$headers['Content-Length']);
    }
    
    function testEpsonFormat()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$user_id = $this->ids['user_id'];
    	logTestUserIn($user_id);
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'Here is my note');
    	$time_stamp = getTodayTwelveNoonTimeStampDenver();
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$order_id = $order_resource->order_id;
    	$this->assertTrue($order_id > 1000,'should have created an order but got: '.$order_resource->error);
    	
    	$request = new Request();
    	//$request->data['format'] = 'SUC';
    	$epsonprinter_controller = new EpsonPrinterController($mt, $u, $request, 5);
    	$resource = $epsonprinter_controller->getOrderById($order_id);
    	$string = $resource->message_text;
    	myerror_log($string);
    	$this->assertTrue(substr_count($string, '<text>Instructions: Here is my note&#10;</text>') == 1);
    	$this->assertTrue(substr_count($string, '<text reverse="false" ul="false" em="true" color="color_1"/>') == 1);
    }

    function testPullForEpsonPrinter()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$merchant_resource = Resource::find(new MerchantAdapter($mimetypes),"$merchant_id");
    	$merchant_numeric_id = $merchant_resource->numeric_id;
    	//create epson record
    	$map_id = Resource::createByData(new MerchantMessageMapAdapter($mimetypes), array("merchant_id"=>$merchant_id,"message_format"=>"SUW","delivery_addr"=>"epson","message_type"=>"O"));

    	$user_id = $this->ids['user_id'];
    	logTestUserIn($user_id);
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$order_id = $order_resource->order_id;
    	$this->assertTrue($order_id > 1000,'should have created an order but got: '.$order_resource->error);
    	
    	$request = new Request();
    	$request->url = "/messagemanager/getnextmessagebymerchantid/$merchant_numeric_id/epsonprinter/message.txt";
    	$epson_printer_controller = new EpsonPrinterController($mt, $u, $request, 5);
    	
    	$message_resource = $epson_printer_controller->pullNextMessageResourceByMerchant($merchant_numeric_id);
    	$mmh_id = $message_resource->message_id;
    	$message_text = $message_resource->message_text;
    	$epson_printer_controller->markMessageDelivered();
    	
    	// now verify that the message is still primed to be sent
    	$db_message_resource = SplickitController::getResourceFromId($mmh_id, 'MerchantMessageHistory');
    	$this->assertEquals('P',$db_message_resource->locked);
    	$this->assertEquals('0000-00-00 00:00:00',$db_message_resource->sent_dt_tm);
    	
    	$request2 = new Request();
    	$request2->body = "ConnectionType=GetRequest&ID=99";
    	
    	$request2->url = "/messagemanager/getnextmessagebymerchantid/$merchant_numeric_id/epsonprinter/message.txt";
    	$epson_printer_controller2 = new EpsonPrinterController($mt, $u, $request2, 5);
    	
    	$message_resource2 = $epson_printer_controller2->pullNextMessageResourceByMerchant($merchant_numeric_id);
    	$message_text = $message_resource2->message_text;
    	
    	$epson_printer_controller2->markMessageDelivered();
    	$db_message_resource2 = SplickitController::getResourceFromId($mmh_id, 'MerchantMessageHistory');
    	$this->assertEquals('S',$db_message_resource2->locked);
    	$this->assertNotEquals('0000-00-00 00:00:00',$db_message_resource2->sent_dt_tm);
    	
    }





    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;

    	$skin_resource = createWorldHqSkin();
    	$ids['skin_id'] = $skin_resource->skin_id;
    	
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(2);
    	$items = CompleteMenu::getAllMenuItemsAsArray($menu_id);
    	$item_one_resource = Resource::find(new ItemAdapter(getM()),$items[0]['item_id']);
    	$item_one_resource->item_name = 'something with & and all';
    	$item_one_resource->item_print_name = 'something with & and all';
    	$item_one_resource->save();
    	$ids['menu_id'] = $menu_id;
    	
//    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id,1);
//    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
//    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
//    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 1);

        $sql = "INSERT INTO `Lookup` VALUES(null, 'message_template', 'SA', '/order_templates/epson/epson_item_all.txt', 'Y', now(), now(), 'N');";
        $lua = new LookupAdapter(getM());
        $lua->_query($sql);

        $merchant_resource = createNewTestMerchant($menu_id);
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_resource->merchant_id,'SUW','Epson','X');
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;
    	
    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;
    }
    
	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();         $db = DataBase::getInstance();
    	$mysqli = $db->getConnection();
    	$mysqli->rollback();
    	date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
	EpsonPrinterControllerTest::main();
}

?>