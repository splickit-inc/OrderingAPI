<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class LineBusterTest extends PHPUnit_Framework_TestCase
{
	var $merchant_controller;
	var $user;
	var $merchant_id;
	var $menu_id;
	var $stamp;
	
	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		
		setContext("com.splickit.worldhq");

		$this->user = logTestUserIn($_SERVER['unit_test_ids']['user_id']);
		$this->merchant_id = $_SERVER['unit_test_ids']['merchant_id'];
		$this->menu_id = $_SERVER['unit_test_ids']['menu_id'];
		$this->merchant_controller = new MerchantController($mt, $this->user, $r,5);
	}
	
	function tearDown()
	{
		unset($this->merchant_controller);
		unset($this->user);
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->stamp);
	}
	
	function testGetLineBusterMerchantList()
	{
		myerror_log("starting get merchant list for line buster");
		$resource = $this->merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		
		//myerror_log("resource: ".$resource->__toString());
		$this->assertNull($resource->error);
		$this->assertEquals(1, sizeof($resource->data));
		$merchant = $resource->data[0];
		$this->assertEquals($this->merchant_id,$merchant['merchant_id']);
		return $merchant['merchant_id'];
	}
	
	/** 
     * @depends testGetLineBusterMerchantList
     */
	function testGetLineBusterMerchantMenu($merchant_id)
	{
		$request = new Request();
		$request->url = "/merchants/".$merchant_id;
		$this->merchant_controller->setRequest($request);
		$resource = $this->merchant_controller->getMerchant();
		//myerror_log("resource: ".$resource->__toString());
		$this->assertNull($resource->error,"there was an error returned on the get Menu call");
		$this->assertEquals(3,$resource->lead_time);
		$this->assertEquals($this->menu_id, $resource->menu['menu_id']);
		$this->assertNotNull($resource->menu['modifier_groups'],"ERROR no modifier groups found");
		$this->assertNotNull($resource->menu['menu_types'],"ERROR no menu types found");
		
		return $resource;
	}
	
	/**
	 * @depends testGetLineBusterMerchantMenu
	 */
	function testPlaceLineBusterOrder($merchant_menu_resource)
	{
		// pull menu out from resource
		$menu = $merchant_menu_resource->menu;
		
		// get order json
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayFromFullMenu($menu, $merchant_menu_resource->merchant_id, 'skip hours');
		$order_data['tip'] = 0.00;
		//$json['jsonVal'] = $order_data;
    	$json_encoded_data = json_encode($order_data);
    	$order_resource = placeOrderFromOrderData($order_data,$the_time);
		$this->assertNotNull($order_resource);
		$this->assertNull($order_resource->error,"we got an error when we shouldn't have");
		$this->assertTrue($order_resource->order_id > 1000,"Bad order id of: ".$order_resource->order_id);  
		
	}
	
    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	$_SERVER['request_time1'] = microtime(true);    	
		
    	$skin_resource = createWorldHqSkin();
    	$ids['skin_id'] = $skin_resource->skin_id;
		$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);
    	
    	$merchant_resource1 = createNewTestMerchant($menu_id);
    	$merchant_resource2 = createNewTestMerchant($menu_id);
    	$merchant_resource3 = createNewTestMerchant($menu_id);
    	$merchant_resource4 = createNewTestMerchant($menu_id);
    	$merchant_resource5 = createNewTestMerchant($menu_id);
    	$ids['merchant_id'] = $merchant_resource5->merchant_id;
    	    	    	
    	$user_resource = createNewUser(array('flags'=>'1C20000001'));
    	$user_resource->email = $merchant_resource5->numeric_id.'_manager@dummy.com';
    	$user_resource->save();
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

if (false && !defined('PHPUnit_MAIN_METHOD')) {
    LineBusterTest::main();
}
		
