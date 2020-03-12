<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class MenuSingleVersionTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		// to make sure menu caching is off
		logTestUserIn(2);
		$_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'] = 10;
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }
    
	function testSingleVersionForPickup()
	{
		$menu_id = $this->ids['menu_id'];
		$complete_menu_object = new CompleteMenu($menu_id);
		$complete_menu = CompleteMenu::getCompleteMenu($menu_id);
		$this->assertCount(2, $complete_menu['menu_types']);
		
		// now set one of the item size records to delivery
		$item_size_resources = $complete_menu_object->getAllMenuItemSizeMapResources($menu_id, 'Y', $merchant_id);
		$item_size_resource = $item_size_resources[0];
		$item_size_resource->included_merchant_menu_types = 'Delivery';
		$item_size_resource->save();

		$complete_menu_pickup = CompleteMenu::getCompletePickupMenu($menu_id);
		$this->assertCount(1, $complete_menu_pickup['menu_types'],'Should have only found 1 menu type in the pickup menu');
		$pickup_menu_type_id = $complete_menu_pickup['menu_types'][0]['menu_type_id'];
		
		$item_size_price_id = $complete_menu_pickup['menu_types'][0]['menu_items'][0]['size_prices'][0]['item_size_id'];
		$this->assertEquals($item_size_resources[1]->item_size_id, $item_size_price_id,"Should have found the pickup item_size");
		
		// now get the delivery menu and see if it contains the oposite item
		$complete_menu_delivery = CompleteMenu::getCompleteDeliveryMenu($menu_id);
		$this->assertCount(2, $complete_menu_delivery['menu_types'],"Should have found 2 menu types for delivery since the other one is still 'ALL'");
		
		$other_item_size_resource = $item_size_resources[1];
		$other_item_size_resource->included_merchant_menu_types = 'Pickup';
		$other_item_size_resource->save();
		
		$complete_menu_delivery = CompleteMenu::getCompleteDeliveryMenu($menu_id);
		$this->assertCount(1, $complete_menu_delivery['menu_types'],"Should have found 1 menu type for delivery");
		$delivery_menu_type_id = $complete_menu_delivery['menu_types'][0]['menu_type_id'];
		
		$delivery_item_size_price_id = $complete_menu_delivery['menu_types'][0]['menu_items'][0]['size_prices'][0]['item_size_id'];
		$this->assertEquals($item_size_resources[0]->item_size_id, $delivery_item_size_price_id,"Should have found the delivery item_size");
		
		// now make sure the two returned delivery types were different
		$this->assertNotEquals($pickup_menu_type_id, $delivery_menu_type_id,"Delivery menu type and Pickup menu type should be different");
	}
	
	function testGetMerchantWithSingleMenu()
	{
		$complete_merchant = new CompleteMerchant($this->ids['merchant_id']);
		$merchant_resource_pickup = $complete_merchant->getCompleteMerchant('pickup');
		$pickup_menu = $merchant_resource_pickup->menu;
		$this->assertCount(1, $pickup_menu['menu_types'],'Should have only found 1 menu type in the pickup menu');
		$pickup_menu_type_id = $pickup_menu['menu_types'][0]['menu_type_id'];
			
		$merchant_resource_delivery = $complete_merchant->getCompleteMerchant('Delivery');
		$delivery_menu = $merchant_resource_delivery->menu;
		$this->assertCount(1, $delivery_menu['menu_types'],'Should have only found 1 menu type in the pickup menu');
		$delivery_menu_type_id = $delivery_menu['menu_types'][0]['menu_type_id'];
		
		$this->assertNotEquals($pickup_menu_type_id, $delivery_menu_type_id);
	}

	static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',0);
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	
    	$skin_resource = createWorldHqSkin();
    	$ids['skin_id'] = $skin_resource->skin_id;
    	
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItemsAndMenuTypes(1,$menu_id,2);
   	    $complete_menu = CompleteMenu::getCompleteMenu($menu_id);
    	$ids['menu_id'] = $menu_id;

    	$merchant_resource = createNewTestMerchantDelivery($menu_id);
    	MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $menu_id, 'pickup');
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

if (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'use_main'  && !defined('PHPUnit_MAIN_METHOD')) {
    MenuSingleVersionTest::main();
}

?>