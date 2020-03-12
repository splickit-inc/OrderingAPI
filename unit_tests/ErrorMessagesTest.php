<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class ErrorMessagesTest extends PHPUnit_Framework_TestCase
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
    
    function testCreditMessageForUser()
    {
    	$ids = $this->ids;
    	$user_resource = createNewUser();
    	$user_resource->balance = 20.00;
    	$user_resource->save();
    	setContext("com.splickit.order");
    	logTestUserIn($user_resource->user_id);
    	
    	$merchant_id = $ids['merchant_id'];
    	$request = new Request();
    	$request->url = "/merchants/$merchant_id";
		$merchant_controller = new MerchantController($mt, $user, $request,5);
		$resource = $merchant_controller->getMerchant();
		$this->assertNotNull($resource->user_message,"Should have found a message to the user that they have a credit");
		$this->assertContains("You have a credit of $20.00. Credit will be applied on billing of your credit card after purchase. Please check email reciept for verification.", $resource->user_message);

		// now set balance to 0.00 and see if it goes away
		$user_resource->balance = 0;
    	$user_resource->save();
    	$user = logTestUserIn($user_resource->user_id);
    	$request = new Request();
    	$request->url = "/merchants/$merchant_id";
		$merchant_controller = new MerchantController($mt, $user, $request,5);
		$resource = $merchant_controller->getMerchant();
		if ($resource->user_message) {
			// there may be closed message
			$this->assertNotContains('You have a credit of $20.00', $resource->user_message);
		}
		
    }
    
    function testDoesMerchantHaveMessagesSetUp()
    {
    	$ids = $this->ids;
    	$merchant_resource = createNewTestMerchant($ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	$this->assertTrue(MerchantMessageMapAdapter::doesMerchantHaveMessagesSetUp($merchant_id));
    	
    	$mmm_adapter = new MerchantMenuMapAdapter($mimetypes);
    	$sql = "DELETE FROM Merchant_Message_Map WHERE merchant_id = $merchant_id";
    	$mmm_adapter->_query($sql);
    	$this->assertFalse(MerchantMessageMapAdapter::doesMerchantHaveMessagesSetUp($merchant_id));
    }
    
    function testNoMessagesError()
    {
    	$ids = $this->ids;
    	$merchant_resource = createNewTestMerchant($ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	$mmm_adapter = new MerchantMenuMapAdapter($mimetypes);
    	$sql = "DELETE FROM Merchant_Message_Map WHERE merchant_id = $merchant_id";
    	$mmm_adapter->_query($sql);
    	
    	// now try to get a menu and we should have a message to the user and a message to support/account management
    	setContext("com.splickit.order");
    	$user = logTestUserIn(101);
    	$request = new Request();
		$request->url = "/merchants/$merchant_id";
		$merchant_controller = new MerchantController($mt, $user, $request,5);
		$resource = $merchant_controller->getMerchant();
		$this->assertNotNull($resource->user_message,"Should have found a message to the user that the mercahnt is not set up correctly");
		$this->assertEquals("Admin user message: please set up merchant message map, there are no messages for order delivery.", $resource->user_message);
		$this->assertNull($resource->error_message_map_id,"Should not have found a message");
    	
    	$user = logTestUserIn($ids['user_id']);
    	$request = new Request();
		$request->url = "/merchants/$merchant_id";
		$merchant_controller = new MerchantController($mt, $user, $request,5);
		$resource = $merchant_controller->getMerchant();
		$this->assertNotNull($resource->user_message,"Should have found a message to the user that the mercahnt is not set up correctly");
		$this->assertEquals("Sorry, there is a problem with this merchants set up and they cannot receive orders at this time. Support has been alerted, we apologize for the inconvenience.", $resource->user_message);
		$this->assertNotNull($resource->error_message_map_id,"the map id of the error message to support should be in the return data");
		
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'Pickup', 'skip hours');
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$this->assertNotNull($order_resource->error,"Should have been an order error since there are no messages for this merchant");
		$this->assertEquals("We're sorry but there is a problem with this merchants set up. Support has now been alerted. Please try again soon.", $order_resource->error);
		$this->assertNotNull($order_resource->error_message_map_id);
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
    ErrorMessagesTest::main();
}

?>