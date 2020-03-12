<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class ComboTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }

	function testRejectAddToCart()
	{
		$user_resource = createNewUserWithCCNoCVV();
		$user = logTestUserResourceIn($user_resource);
		$merchant_id = $this->ids['merchant_id'];

		$order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);

		$json_encoded_data = json_encode($order_data);
		$request = new Request();
		$request->url = '/app2/apiv2/cart';
		$request->method = "post";
		$request->body = $json_encoded_data;
		$request->mimetype = 'application/json';
		$request->_parseRequestBody();
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$cart_resource = $place_order_controller->processV2Request();
		$this->assertNotNull($cart_resource->error,"Should have thrown an error");
		$this->assertEquals("Sorry, you must choose both combo items to get the combo price on your Test Item 1.  A-la-carte items are available from the main menu. Thanks!",$cart_resource->error);
		$this->assertEquals(422,$cart_resource->http_code);

	}
    
    function testComboRejectAcceptOrder()
    {
    	//$mga = new ModifierGroupAdapter($mimetypes);
    	//$modifier_group2 = $mga->getRecord(array("modifier_group_id"=>$this->ids['modifier_group_id2']));

    	$menu_id = $this->ids['menu_id'];
    	$cm = new CompleteMenu($this->ids['menu_id']);
    	$modifier_items = $cm->getAllModifierItemsForMenu($menu_id, 'Y');
    	
    	$modifier_item_id = $modifier_items[$this->ids['modifier_group_id']][0]['modifier_item_id'];
    	$modifier_item_resource = SplickitController::getResourceFromId($modifier_item_id, 'ModifierItem');
    	$modifier_item_resource->modifier_item_name = "Test Meal Deal Item 1";
    	$modifier_item_resource->modifier_item_print_name = "Test Meal Deal Item 1";
    	$modifier_item_resource->save();
    	
    	$modifier_item_id2 = $modifier_items[$this->ids['modifier_group_id2']][0]['modifier_item_id'];
    	$modifier_item_resource2 = SplickitController::getResourceFromId($modifier_item_id2, 'ModifierItem');
    	$modifier_item_resource2->modifier_item_name = "Test Meal Deal Item 2";
    	$modifier_item_resource2->modifier_item_print_name = "Test Meal Deal Item 2";
    	$modifier_item_resource2->save();

    	$modifier_size = ModifierSizeMapAdapter::getModifierSizeRecord($modifier_item_id, 0, 0);
    	$modifier_size_id = $modifier_size['modifier_size_id'];
    	$modifier_size2 = ModifierSizeMapAdapter::getModifierSizeRecord($modifier_item_id2, 0, 0);
    	$modifier_size_id2 = $modifier_size2['modifier_size_id'];
    	$mod1 = array("mod_quantity"=>1,"mod_sizeprice_id"=>$modifier_size_id);
    	$mod2 = array("mod_quantity"=>1,"mod_sizeprice_id"=>$modifier_size_id2);

    	$merchant_id = $this->ids['merchant_id'];
    	$user_resource = createNewUserWithCC();
    	$user = logTestUserResourceIn($user_resource);
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
    	//$order_data['items'][0]['mods'][0] = $mod1;
    	$order_response = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNotNull($order_response->error);
    	$this->assertEquals("Sorry, you must choose both combo items to get the combo price on your Test Item 1.  A-la-carte items are available from the main menu. Thanks!", $order_response->error);
    	
    	unset($order_response);
    	//now add the other combo item to it
    	$order_data['items'][0]['mods'][] = $mod2;
    	$order_response = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNull($order_response->error);
    	$order_id = $order_response->order_id;
    	$this->assertTrue($order_id > 1000);
    	
    	// now need to verfy that the combos are in the order_conf, email_conf, and order delivery templates.
    	$order_summary = $order_response->order_summary;
    	$modifiers_on_item = $order_summary['cart_items'][0]['item_description'];
        $this->assertContains("Test Meal Deal Item 1", $modifiers_on_item);
        $this->assertContains("Test Meal Deal Item 2", $modifiers_on_item);

    	// email conf
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$message_resource = $mmha->getRecord(array("order_id"=>$order_id,"message_format"=>"Econf"));
    	myerror_log($message_resource['message_text']);
    	$this->assertContains("Test Meal Deal Item 1", $message_resource['message_text']);
    	$this->assertContains("Test Meal Deal Item 2", $message_resource['message_text']);
    	
    	// email order delivery
    	$email_controller = new EmailController($mt, $u, $r,5);
    	$email_message_text = $email_controller->getFormattedMessageTextByOrderIdAndMessageFormat($order_id, 'E');
    	myerror_log($email_message_text);
    	$this->assertContains("Test Meal Deal Item 1", $email_message_text);
    	$this->assertContains("Test Meal Deal Item 2", $email_message_text);
    	
    	$gprs_controller = new GprsController($mt, $u, $r);
    	$gprs_message_text = $gprs_controller->getFormattedMessageTextByOrderIdAndMessageFormat($order_id, 'GUC');
    	myerror_log($gprs_message_text);
    	$this->assertContains("Test Meal Deal Item 1", $gprs_message_text);
    	$this->assertContains("Test Meal Deal Item 2", $gprs_message_text);
    	
    	$gprs_controller = new GprsController($mt, $u, $r);
    	$gprs_message_text = $gprs_controller->getFormattedMessageTextByOrderIdAndMessageFormat($order_id, 'GUW');
    	myerror_log($gprs_message_text);
    	$this->assertContains("Test Meal Deal Item 1", $gprs_message_text);
    	$this->assertContains("Test Meal Deal Item 2", $gprs_message_text);
    	
    	$gprs_controller = new GprsController($mt, $u, $r);
    	$gprs_message_text = $gprs_controller->getFormattedMessageTextByOrderIdAndMessageFormat($order_id, 'GUE');
    	myerror_log($gprs_message_text);
    	$this->assertContains("Test Meal Deal Item 1", $gprs_message_text);
    	$this->assertContains("Test Meal Deal Item 2", $gprs_message_text);
    	
    	$winapp_controller = new WindowsServiceController($mt, $u, $r);
    	$win_message_text = $winapp_controller->getFormattedMessageTextByOrderIdAndMessageFormat($order_id, 'WUC');
    	myerror_log($win_message_text);
    	$this->assertContains("Test Meal Deal Item 1", $win_message_text);
    	$this->assertContains("Test Meal Deal Item 2", $win_message_text);
    	
    	$fax_controler = new FaxController($mt, $u, $r);
    	$fax_message_text = $fax_controler->getFormattedMessageTextByOrderIdAndMessageFormat($order_id, 'FP');
    	myerror_log($fax_message_text);
    	$this->assertContains("Test Meal Deal Item 1", $fax_message_text);
    	$this->assertContains("Test Meal Deal Item 2", $fax_message_text);

    }
    
    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	
    	$skin_resource = createWorldHqSkin();
    	$ids['skin_id'] = $skin_resource->skin_id;
    	
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(1);
    	$ids['menu_id'] = $menu_id;
    	
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1);
    	$modifier_group_resource->modifier_type = 'I2';
    	$modifier_group_resource->save();
    	
    	$modifier_group_resource2 = createModifierGroupWithNnumberOfItems($menu_id, 1);
    	$modifier_group_resource2->modifier_type = 'I2';
    	$modifier_group_resource2->save();
    	
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$modifier_group_id2 = $modifier_group_resource2->modifier_group_id;
    	$ids['modifier_group_id'] = $modifier_group_id;
    	$ids['modifier_group_id2'] = $modifier_group_id2;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	$imgm_resource = assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 0);
		$imgm_resource->priority = 100;
		$imgm_resource->save();
		$imgm_resource2 = assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id2, 0);
		$imgm_resource2->priority = 50;
		$imgm_resource2->save();


		$merchant_resource = createNewTestMerchant($menu_id);
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;

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
    ComboTest::main();
}

?>