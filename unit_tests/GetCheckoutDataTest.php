<?php
ini_set('max_execution_time', 300);
$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class GetCheckoutDataTest extends PHPUnit_Framework_TestCase
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
    
    function testCheckForNonActiveOrOrderingOffMessageByMerchantObject()
    {
    	$merchant['active'] = 'Y';
    	$merchant['ordering_on'] = 'Y';
    	$place_order_controller = new PlaceOrderController($mt, $u, $r);
    	$message = $place_order_controller->checkForNonActiveOrOrderingOffMessageByMerchantObject($merchant);
    	$this->assertNull($message);
    	
    	$merchant	['ordering_on'] = 'N';
    	$message = $place_order_controller->checkForNonActiveOrOrderingOffMessageByMerchantObject($merchant);
    	$this->assertEquals("Sorry, this merchant is not currently accepting mobile/online orders. Please try again soon.", $message);
    	
    	$merchant['ordering_on'] = 'Y';
    	$merchant['active'] = 'N';
    	$message = $place_order_controller->checkForNonActiveOrOrderingOffMessageByMerchantObject($merchant);
    	$this->assertEquals("Sorry, something has changed with this merchant. Please reload the merchant from the merchant list. Sorry for the confusion.", $message);
    	
    }
    
    function testErrorWithCartOnCheckoutDataCall()
    {
        $item_name = 'Time Limit Item One';
    	$simple_menu_id = createTestMenuWithOneItem($item_name);
    	$menu_type_adapter = new MenuTypeAdapter($mimetypes);
    	$menu_type_resource = $menu_type_adapter->getExactResourceFromData(array("menu_id"=>$simple_menu_id));
    	$menu_type_resource->end_time = '03:15:00';
    	$menu_type_resource->save();
    	
		$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	$user = logTestUserIn($user_id);
    	
		$merchant_resource = createNewTestMerchant($simple_menu_id);
		$merchant_id = $merchant_resource->merchant_id;
		$order_adapter = new OrderAdapter($mimetypes);
        $order_adapter->old_style = true;
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'some note');
    	
    	$place_order_controller = new PlaceOrderController($mt, $user, $r,5);
    	$time_stamp = getTodayTwelveNoonTimeStampDenver();
    	$place_order_controller->setCurrentTime($time_stamp);
    	$checkout_data_resource = $place_order_controller->getCheckoutDataFromOrderData($order_data);
    	$this->assertNotNull($checkout_data_resource->error);
    	$this->assertEquals("Sorry the $item_name is not available after 3:15. Please remove it from your cart before placing your order.", $checkout_data_resource->error);
    }

    function testCreateTipArrayNoValues()
    {
    	$place_order_controller = new PlaceOrderController($mt, $u, $r, 5);
    	$tip_array = $place_order_controller->createTipArray(0, 0, 10.00);
    	$this->assertCount(29, $tip_array);
    	$first = $tip_array[0];
    	$last = array_pop($tip_array);
    	$this->assertEquals(0.00, $first['No Tip']);
    	$this->assertEquals(25.00, $last['$25.00']);
    }
    
    function testCreateTipArrayWithMinimumTipNoTriggerAmount()
    {
    	$place_order_controller = new PlaceOrderController($mt, $u, $r, 5);
    	// 20% minimum with no trigger
    	$tip_array = $place_order_controller->createTipArray(0, 20, 10.00);
    	//$no_tip = $tip_array[0];
    	$first = $tip_array[0];
    	$second = $tip_array[1];
    	$last = array_pop($tip_array);
    	//$this->assertEquals(0.00, $no_tip['No Tip']);
    	$this->assertEquals(2.00, $first['20%']);
    	$this->assertEquals(1.00, $second['$1.00']);
    	$this->assertEquals(25.00, $last['$25.00']);
    }
    
    function testCreateTipArrayWithMinimumTipAndTriggerAmountEqualToSubtotal()
    {
    	$place_order_controller = new PlaceOrderController($mt, $u, $r, 5);
    	// 20% minimum with $10 trigger
    	$tip_array = $place_order_controller->createTipArray(10.00, 20, 10.00);
    	//$no_tip = $tip_array[0];
    	$first = $tip_array[0];
    	$second = $tip_array[1];
    	$last = array_pop($tip_array);
    	//$this->assertEquals(0.00, $no_tip['No Tip']);
    	$this->assertEquals(2.00, $first['20%']);
    	$this->assertEquals(1.00, $second['$1.00']);
    	$this->assertEquals(25.00, $last['$25.00']);
    }
    
    function testCreateTipArrayWithMinimumTipAndTriggerAmountGreaterThanSubtotal()
    {
    	$place_order_controller = new PlaceOrderController($mt, $u, $r, 5);
    	// 20% minimum with $20 trigger
    	$tip_array = $place_order_controller->createTipArray(20.00, 20, 10.00);
    	$no_tip = $tip_array[0];
    	$first = $tip_array[1];
    	$fourth = $tip_array[3];
    	$fifth = $tip_array[4];
    	$last = array_pop($tip_array);
    	$this->assertEquals(0.00, $no_tip['No Tip']);
    	$this->assertEquals(1.00, $first['10%']);
    	$this->assertEquals(2.00, $fourth['20%']);
    	$this->assertEquals(1.00, $fifth['$1.00']);
    	$this->assertEquals(25.00, $last['$25.00']);
    }
    
    function testValidateTip()
    {
    	$place_order_controller = new PlaceOrderController($mt, $u, $r, 5);

    	$this->assertTrue($place_order_controller->isTipValidAgainstSubtotalAndMinimums(5.00, 0.00, 0.00, 0));
    	$this->assertTrue($place_order_controller->isTipValidAgainstSubtotalAndMinimums(5.00, 0.00, 5.00, 0));
    	$this->assertFalse($place_order_controller->isTipValidAgainstSubtotalAndMinimums(5.00, 0.00, 5.00, 5));
    	$this->assertFalse($place_order_controller->isTipValidAgainstSubtotalAndMinimums(5.00, 0.00, 0.00, 5));
    	$this->assertTrue($place_order_controller->isTipValidAgainstSubtotalAndMinimums(5.00, 0.25, 0.00, 5));
    	
    	$this->assertTrue($place_order_controller->isTipValidAgainstSubtotalAndMinimums(10.00, 1.00, 0.00, 0));
    	$this->assertTrue($place_order_controller->isTipValidAgainstSubtotalAndMinimums(10.00, 0.00, 20.00, 20));
    	$this->assertTrue($place_order_controller->isTipValidAgainstSubtotalAndMinimums(10.00, 2.00, 20.00, 20));
    	$this->assertFalse($place_order_controller->isTipValidAgainstSubtotalAndMinimums(20.00, 1.00, 20.00, 20));
    	$this->assertTrue($place_order_controller->isTipValidAgainstSubtotalAndMinimums(20.00, 4.00, 20.00, 20));    
    }

	function testTipMinimumNotMet()
    {
    	$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);

    	$merchant_resource->tip_minimum_percentage = 20;
    	$merchant_resource->tip_minimum_trigger_amount = 0;
    	$merchant_resource->save();
        $merchant_resource = $merchant_resource->refreshResource();
    	
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
        $order_adapter->old_style = true;
    	//create an order with 10 items (x1.50) = $15.00
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'dum note',10);
    	$checkout_data_resource = getCheckoutDataWithThrottling($order_data, $merchant_id, getTomorrowTwelveNoonTimeStampDenver());
    	
    	$this->assertEquals("Please note, this merchant has a minimum tip of 20% on all orders.", $checkout_data_resource->user_message);
    	
    	//now check with a trigger amout of 10.00
    	$merchant_resource->tip_minimum_trigger_amount = 10.00;
    	$merchant_resource->save();
    	
    	$checkout_data_resource = getCheckoutDataWithThrottling($order_data, $merchant_id, getTomorrowTwelveNoonTimeStampDenver());
    	$this->assertEquals("Please note, this merchant has a minimum tip of 20% on orders over $10.00", $checkout_data_resource->user_message);

    	$order_data['tip'] = 1.00;

		$response = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
		$this->assertNotNull($response->error,"Should have thrown an error for minimum tip requirement not met");
		$this->assertEquals("We're sorry, but this merchant requires a gratuity of 20% on orders over $10.00. Please set tip to at least 20%.", $response->error);
		
		$order_data['tip'] = 4.00;
		$response = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
		$this->assertNull($response->error);
		$this->assertTrue($response->order_id > 1000);
		
		// now test with an order that is below the trigger amount
		$order_data2 = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',1);
    	$order_data2['tip'] = 1.00;
		$response2 = placeOrderFromOrderData($order_data2,getTomorrowTwelveNoonTimeStampDenver());
		$this->assertNull($response2->error);
		$this->assertTrue($response2->order_id > 1000);
    }
    
    function testHaveTipArrayInGetCheckoutDataNoMinimum()
    {
    	$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	
		$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	$user = logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
        $order_adapter->old_style = true;
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',10);
    	$place_order_controller = new PlaceOrderController($mt, $user, $r,5);
    	$checkout_data_resource = $place_order_controller->getCheckoutDataFromOrderData($order_data);
    	$this->assertNotNull($checkout_data_resource->tip_array,"Should have found the tip array");
    	$this->assertCount(29, $checkout_data_resource->tip_array);
    	$this->assertNull($checkout_data_resource->user_message);    	    	
    }

	function testShouldHaveLastFourInGetCheckoutData()
	{
		$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);

		$user_resource = createNewUser(array("flags"=>"1C20000001"));
		$user_resource->last_four = "1234";
		$user_resource->save();
		$user = logTestUserIn($user_resource->user_id);

		$order_adapter = new OrderAdapter($mimetypes);
        $order_adapter->old_style = true;
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_resource->merchant_id, 'pickup', 'skip hours',10);
		$place_order_controller = new PlaceOrderController($mt, $user, $r,5);
		$checkout_data_resource = $place_order_controller->getCheckoutDataFromOrderData($order_data);
		$this->assertNotNull($checkout_data_resource);
		$this->assertEquals("1234", $checkout_data_resource->user_info['last_four']);
	}

	function testShouldNotHaveLastFourInGetCheckoutData()
	{
		$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);
		$user_resource = createNewUser(array("flags"=>"1C20000001"));
		$user = logTestUserIn($user_resource->user_id);

		$order_adapter = new OrderAdapter($mimetypes);
        $order_adapter->old_style = true;
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_resource->merchant_id, 'pickup', 'skip hours',10);
		$place_order_controller = new PlaceOrderController($mt, $user, $r,5);
		$checkout_data_resource = $place_order_controller->getCheckoutDataFromOrderData($order_data);
		$this->assertNotNull($checkout_data_resource);
		$this->assertNull($checkout_data_resource->user_info['last_four']);
	}
    
    function testHaveTipArrayInGetCheckoutDataWithMinimumSetAndSubtotalUnderMinimum()
    {
    	$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);
    	$merchant_resource->tip_minimum_percentage = 20;
    	$merchant_resource->tip_minimum_trigger_amount = 10.00;
    	$merchant_resource->save();
    	$merchant_id = $merchant_resource->merchant_id;
    	
		$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	$user = logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
        $order_adapter->old_style = true;
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',1);
    	$place_order_controller = new PlaceOrderController($mt, $user, $r,5);
    	$checkout_data_resource = $place_order_controller->getCheckoutDataFromOrderData($order_data);
    	$this->assertNotNull($checkout_data_resource->tip_array,"Should have found the tip array");
    	$tip_array = $checkout_data_resource->tip_array;
    	$first_tip_value = $tip_array[0];
    	$this->assertEquals(0.00,$first_tip_value['0%']);
    	$this->assertCount(29, $tip_array);
    	$this->assertNull($checkout_data_resource->user_message);
    }
   
    function testHaveTipArrayInGetCheckoutDataWithMinimumSetAndSubtotalOverMinimum()
    {
    	$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);
    	$merchant_resource->tip_minimum_percentage = 20;
    	$merchant_resource->tip_minimum_trigger_amount = 10.00;
    	$merchant_resource->save();
    	$merchant_id = $merchant_resource->merchant_id;
    	
		$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	$user = logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
        $order_adapter->old_style = true;
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',10);
    	$place_order_controller = new PlaceOrderController($mt, $user, $r,5);
    	$checkout_data_resource = $place_order_controller->getCheckoutDataFromOrderData($order_data);
    	$this->assertNotNull($checkout_data_resource->tip_array,"Should have found the tip array");
    	$tip_array = $checkout_data_resource->tip_array;
    	$first_tip_value = $tip_array[0];
    	$this->assertEquals(.2*$order_data['sub_total'],$first_tip_value['20%']);
    	$this->assertEquals("Please note, this merchant has a minimum tip of 20% on orders over $10.00",$checkout_data_resource->user_message);
    }
    
    function testHaveTimeZoneInformationInCheckoutData()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$merchant_resource = SplickitController::getResourceFromId($merchant_id, 'Merchant');
    	$merchant_resource->state = 'CA';
    	$merchant_resource->time_zone = -8;
    	$merchant_resource->save();
    	
		$user = logTestUserIn($this->ids['user_id']);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
        $order_adapter->old_style = true;
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
    	$place_order_controller = new PlaceOrderController($mt, $user, $r,5);
    	$checkout_data_resource = $place_order_controller->getCheckoutDataFromOrderData($order_data);
    	$this->assertEquals("America/Los_Angeles", $checkout_data_resource->time_zone_string);
    	$this->assertEquals(-8 + date("I"),$checkout_data_resource->time_zone_offset);    	    	
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
    	
    	$simple_menu_id = createTestMenuWithOneItem("item_one");
    	$ids['simple_menu_id'] = $simple_menu_id;
    	
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;
    	
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);

    	$merchant_resource = createNewTestMerchant($menu_id);
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
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
    GetCheckoutDataTest::main();
}

?>