<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class OrderingOnFlagTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $merchant_resource;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		setContext("com.splickit.order");
		$this->ids = $_SERVER['unit_test_ids'];
		$this->merchant_resource = Resource::find(new MerchantAdapter($mimetypes),''.$this->ids['merchant_id']);
	}	
	
	function tearDown() 
	{
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
		unset($this->merchant_resource);
		unset($this->stamp);
    }

    /*
     * test active flag 'N'  with store_tester.  should get menus and be able to order
     */
    
    function testActiveNMenuRetrievalandOrderForUserStoreTester()
    {
    	//set data
    	$this->merchant_resource->active = 'N';
		$this->merchant_resource->save();
		$merchant_id = $this->merchant_resource->merchant_id;
    	// first do tests as store tester
		$user = logTestUserIn(101);
		
		// now try placing an order
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
		$order_resource = placeOrderFromOrderDataAPIV1($order_data, $time_stamp);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000);
    }
    
    /*
     * test active flag 'N' with regular user.  should not be able to get menu or order
     */
    
    function testActiveNMenuRetrievalandOrderForRegularUser()
    {
    	$merchant_id = $this->merchant_resource->merchant_id;
    	// now do tests as a regular user
		$user = logTestUserIn($this->ids['user_id']);
		$request = new Request();
		$request->url = "merchants/".$this->ids['merchant_id']."/";
		$merchant_controller = new MerchantController($mt, $user, $request);
		$merchant_result = $merchant_controller->getMerchant();
		$this->assertNotNull($merchant_result->error);
		$this->assertEquals("We're sorry, this merchant appears to be offline at the moment.", $merchant_result->error);
    
		// now try placing an order
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$this->assertNotNull($order_resource->error);
		$this->assertEquals("Sorry, something has changed with this merchant. Please reload the merchant from the merchant list. Sorry for the confusion.", $order_resource->error);
    }
    
/*
 *  ok now lets test the ordering_on flag with an active store.  
 * store tester and regular user should both get the message on menu but 
 * store tester should be able to order and regular user should not   
 */

    /*
     * store tester
     */
    function testActiveStoreWithOrderingOnSetToNstoretester()
    {
    	//set data
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_resource->active = 'Y';
    	$merchant_resource->ordering_on = 'N';
		$merchant_resource->save();
		$merchant_id = $merchant_resource->merchant_id;
    	
    	// first do tests as store tester
		$user = logTestUserIn(101);
		$merchant_controller = new MerchantController($mt, $user, $request,5);
		$merchant_controller->setMerchantId($merchant_id);
		$the_time = getTodayTwelveNoonTimeStampDenver();
		$merchant_controller->setTheTime($the_time);
		$merchant_result = $merchant_controller->getMerchant();
		$this->assertNull($merchant_result->error);
		$ordering_off_message = PlaceOrderController::ORDERING_OFFLINE_MESSAGE;
		$this->assertEquals($ordering_off_message, $merchant_result->user_message);
		
		// now try placing an order
		// should go through fine since this is store tester
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000);
		    	
    }
    
    /*
     * regular user
     * Sorry, this merchant is not currently accepting mobile/online orders. This may be due to a temporary technical problem, or they may not have turned it on yet. Please check with the merchant.
     */
    function testActiveStoreWithOrderingOnSetToNregularuser()
    {
    	//set data
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_resource->active = 'Y';
    	$merchant_resource->ordering_on = 'N';
		$merchant_resource->save();
		$merchant_id = $merchant_resource->merchant_id;
		
		$user = logTestUserIn($this->ids['user_id']);
		$merchant_controller = new MerchantController($mt, $user, $request,5);
		$merchant_controller->setMerchantId($merchant_id);
		$the_time = getTodayTwelveNoonTimeStampDenver();
		$merchant_controller->setTheTime($the_time);
		$merchant_result = $merchant_controller->getMerchant();
		$this->assertNull($merchant_result->error);
		$ordering_off_message = PlaceOrderController::ORDERING_OFFLINE_MESSAGE;
		$actual_message = $merchant_result->user_message;
		$this->assertEquals($ordering_off_message, $actual_message);
		
		// now try placing an order
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$this->assertNotNull($order_resource->error);
		$this->assertEquals("Sorry, this merchant is not currently accepting mobile/online orders. Please try again soon.", $order_resource->error);
		    	
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
    	
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);

    	$merchant_resource = createNewTestMerchant($menu_id);
        $merchant_resource->time_zone = -8;
        $merchant_resource->save();
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
    }

    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    OrderingOnFlagTest::main();
}

?>