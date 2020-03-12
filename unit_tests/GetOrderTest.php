<?php
ini_set("max_execution_time",300);
$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class GetOrderTest extends PHPUnit_Framework_TestCase
{
	var $user;
	var $merchant_id;
	var $menu_id;
	var $ids;
	
	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];

		setContext("com.splickit.order");
		
		// we dont want to call to inspirepay 
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		
		$user_resource = SplickitController::getResourceFromId($_SERVER['unit_test_ids']['user_id'], 'User');
		$this->user = $user_resource->getDataFieldsReally();
    	$this->merchant_id = $_SERVER['unit_test_ids']['merchant_id'];
    	$this->menu_id = $_SERVER['unit_test_ids']['menu_id'];
    	$this->ids = $_SERVER['unit_test_ids'];

    	logTestUserIn($this->user['user_id']);		
		
	}
	
	function tearDown() 
	{
		//delete your instance
		unset($this->user);
		unset($this->mdi_resource);
		unset($this->ids);
		unset($this->merchant_id);
		unset($this->menu_id);
		$_SERVER['STAMP'] = $this->stamp;
    }

    function testCheckMenu()
    {
    	$menu = CompleteMenu::getCompleteMenu($this->menu_id);
    	$this->assertNotNull($menu);
    	$menu_types = $menu['menu_types'];
    	$modifier_groups = $menu['modifier_groups'];
    	$this->assertEquals(1, sizeof($menu_types, $mode));
    	$this->assertEquals(5,sizeof($menu_types[0]['menu_items'], $mode));
    	$this->assertEquals(1,sizeof($menu_types[0]['menu_items'][0]['allowed_modifier_groups'], $mode));
    	$this->assertEquals(2,sizeof($menu_types[0]['menu_items'][0]['comes_with_modifier_items'], $mode));
    	$this->assertEquals(10,sizeof($modifier_groups[0]['modifier_items'], $mode));
    }
    
    function testFailedOrderMessageOnCompleteOrderObject()
    {
    	$merchant_adapter = new MerchantAdapter($mimetypes);
    	$merchant = $merchant_adapter->getRecord(array("merchant_id"=>$this->ids['merchant_id']));
    	$order_adapter = new OrderAdapter($mimetypes);
    	logTestUserIn($this->user['user_id']);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours');
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	
    	$failed_order_message = CompleteOrder::createFailedOrderMessageSMSFromOrderId($order_resource->order_id);
    	
    	$this->assertNotNull($failed_order_message);
    	$expected_failed_order_message = "merchant: Unit Test Merchant\nmerchant_id: ".$order_resource->merchant_id."\nphone: 1234567890\norder_id: ".$order_resource->order_id."\n";
    	$this->assertEquals($expected_failed_order_message, $failed_order_message);

    }
    
    function testGetOldOpenOrders()
	{
		
		$order_adapter = new OrderAdapter($mimetypes);
		$sql = "UPDATE Orders SET status = 'F' where status = 'O'";
		$order_adapter->_query($sql);
		$sql = "UPDATE Orders SET logical_delete = 'Y' WHERE merchant_id = ".$this->merchant_id;
		$order_adapter->_query($sql);
		
		// place 4 orders
		for ($i=0;$i<4;$i++) {
            $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->merchant_id,'pickup','dum note',1);
            $order_resource = placeOrderFromOrderData($order_data);

        }
		
		$old_timestamp = time() - 3600;
		$old_dt_tm = date("Y-m-d H:i:s",$old_timestamp);
		$orders_data['merchant_id'] = $this->merchant_id;
		$options[TONIC_FIND_BY_METADATA] = $orders_data;
		$order_resources = Resource::findAll($order_adapter,'',$options);
		$this->assertEquals(4, sizeof($order_resources, $mode));
		$i = 15;
		$default_timezone_string = date_default_timezone_get();
		$order_array = array();
		$new_created_ts = time() - 6000;
		foreach ($order_resources as $order_resource)
		{
			// make it easy, set order to open and merchant id to ones that are in colorado
			date_default_timezone_set('America/Denver');
			$new_pickup_timestamp = time() - ($i*60);
			$new_pickup_dt_time = date ("Y-m-d H:i:s",$new_pickup_timestamp);
			$order_resource->pickup_dt_tm = $new_pickup_dt_time;
			//$new_created_ts = $new_pickup_timestamp-(15*60);
			$order_resource->save();
			
			// now need to force created.
			$sql = "UPDATE Orders SET created = ".$new_created_ts." WHERE order_id = ".$order_resource->order_id;
			$order_adapter->_query($sql);
			//$order_resource->created = date("Y-m-d H:i:s",$new_created_ts); 

			$i = $i + 10;
			$order_array[$order_resource->order_id] = $order_resource->user_id;
			
		}
		date_default_timezone_set($default_timezone_string);
		
		// now get the late orders
		$old_orders = $order_adapter->getOldOpenOrders(10);
		$this->assertEquals(4, sizeof($old_orders, $mode));
		
		$order_adapter = new OrderAdapter($mimetypes);
		$old_orders = $order_adapter->getOldOpenOrders(20);
		$this->assertEquals(3, sizeof($old_orders, $mode));
		
		$order_adapter = new OrderAdapter($mimetypes);
		$old_orders = $order_adapter->getOldOpenOrders(30);
		$this->assertEquals(2, sizeof($old_orders, $mode));
		
		$order_adapter = new OrderAdapter($mimetypes);
		$old_orders = $order_adapter->getOldOpenOrders(40);
		$this->assertEquals(1, sizeof($old_orders, $mode));
		
		return $order_array;
		
	}
	
	/** 
     * @depends testGetOldOpenOrders
     */
	
	function testSetStatusOfStaleOrders($order_array)
	{
		$order_adapter = new OrderAdapter($mimetypes);
		$order_adapter->setStatusOfStaleOrders(10);
		$this->assertEquals(4,sizeof($order_array, $mode));
		foreach($order_array as $order_id=>$user_id)
		{
			$order_resource = Resource::find($order_adapter,''.$order_id);
			$this->assertEquals('E', $order_resource->status);
		}

	}
	
	function testGetOrder()
	{
		// first create the order
		$tip = rand(100,1000)/100;
    	$tip = number_format($tip,2);
    	
    	$user_id = $this->user['user_id'];
		$order_adapter = new OrderAdapter($mimetypes);
   		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->merchant_id, 'pickup', 'skip hours',4);
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$new_order_id = $order_resource->order_id;
		$this->assertTrue($new_order_id > 1000);
		
		$order_detail_adapter = new OrderDetailAdapter($mimetypes);
		$order_detail_data['order_id'] = $new_order_id;
		$new_order_options[TONIC_FIND_BY_METADATA] = $order_detail_data;
		$new_order_detail_resources = Resource::findAll($order_detail_adapter,null,$new_order_options);
		foreach ($new_order_detail_resources as $detail_resource)
		{
			if ($detail_resource->item_name == 'Test Item 1')
				$item_with_2_comeswith_id = $detail_resource->order_detail_id;
			else if ($detail_resource->item_name == 'Test Item 2')
				$item_with_4_comeswith_id = $detail_resource->order_detail_id;
			else if ($detail_resource->item_name == 'Test Item 3')
				$item_with_1_comeswith_id = $detail_resource->order_detail_id;
		}
		$order_id = $new_order_id;
		$complete_order = CompleteOrder::staticGetCompleteOrder($order_id, $mimetypes);
		$this->assertNotNull($complete_order);
		$order_details = $complete_order['order_details'];
		$this->assertEquals(4, sizeof($order_details, $mode));
		foreach ($order_details as $order_detail)
			$better_order_details[$order_detail['order_detail_id']] = $order_detail;
		
		$this->assertNotNull($better_order_details[$item_with_2_comeswith_id]);
		$this->assertEquals(10, sizeof($better_order_details[$item_with_2_comeswith_id]['order_detail_modifiers']));
		$this->assertEquals(2, sizeof($better_order_details[$item_with_2_comeswith_id]['order_detail_comeswith_modifiers']));
		$this->assertEquals(8, sizeof($better_order_details[$item_with_2_comeswith_id]['order_detail_added_modifiers']));
		
		// check for failed order message
		$failed_order_message = $complete_order['late_order_message_sms'];
		$this->assertNotNull($failed_order_message);
		$expected_failed_order_message = "merchant: Unit Test Merchant\nmerchant_id: ".$order_resource->merchant_id."\nphone: 1234567890\norder_id: ".$order_resource->order_id."\n";
		$this->assertEquals($expected_failed_order_message, $failed_order_message);

		//$this->assertEquals(2, sizeof($better_order_details[$item_with_2_comeswith_id]['order_detail_hold_it_modifiers']));
		
/*		$item_with_2_comeswith_added_mods = $better_order_details[$item_with_2_comeswith_id]['order_detail_added_modifiers'];
		foreach ($item_with_2_comeswith_added_mods as $the_added_mod)
		{
			if ($the_added_mod['modifier_item_id'] == 4030)
				$anchovie_sauce_added_mod_record = $the_added_mod;
		}
		
		$this->assertEquals(1, $anchovie_sauce_added_mod_record['mod_quantity'],"quantity should have been reduced by one for added modifiers since it is a comes with item");
		$this->assertEquals('Y',$anchovie_sauce_added_mod_record['comes_with'],"comes with should be 'Y' even though this is an added modifier since it is a quantity of 2");

		$hold_it_mods = $better_order_details[$philly_steak_id]['order_detail_hold_it_modifiers'];
		
		foreach ($hold_it_mods as $hold_it_mod)
		{
			if ($hold_it_mod['modifier_item_id'] == 4006)
				$this->assertEquals("Mushrooms", $hold_it_mod['mod_name']);
			else if ($hold_it_mod['modifier_item_id'] == 4013)
				$this->assertEquals("Provolone", $hold_it_mod['mod_name']);
			else
				$this->assertTrue(false,"WE HAVE AN UNEXPECTED MODIFIER ITEM");	
		}
		
		$added_mods = $better_order_details[$philly_steak_id]['order_detail_added_modifiers'];
		
		foreach ($added_mods as $added_mod)
		{
			if ($added_mod['modifier_item_id'] == 4011)
				$this->assertEquals("Tzatziki ($)", $added_mod['mod_name']);
			else if ($added_mod['modifier_item_id'] == 990583)
				$this->assertEquals("Fork Style", $added_mod['mod_name']);
			else if ($added_mod['modifier_item_id'] == 4015)
				$this->assertEquals("Parmesean ", $added_mod['mod_name']);
			else if ($added_mod['modifier_item_id'] == 4048)
				$this->assertEquals("Hash Browns", $added_mod['mod_name']);
			else if ($added_mod['modifier_item_id'] == 4045)
				$this->assertEquals("Grilled Mushrooms", $added_mod['mod_name']);
			else if ($added_mod['modifier_item_id'] == 4030)
				$this->assertEquals("Ancho Chipotle", $added_mod['mod_name']);  //actually a comes with but since it has a quantity of 2 it is also an add modifier with a quntity of 1.  test done above.
			else
				$this->assertTrue(false,"WE HAVE AN UNEXPECTED MODIFIER ITEM");	
		}

		$comeswith_mods = $better_order_details[$philly_steak_id]['order_detail_comeswith_modifiers'];
		
		foreach ($comeswith_mods as $comeswith_mod)
		{
			if ($comeswith_mod['modifier_item_id'] == 3998)
				$this->assertEquals("Onions", $comeswith_mod['mod_name']);
			else if ($comeswith_mod['modifier_item_id'] == 3999)
				$this->assertEquals("Green Peppers", $comeswith_mod['mod_name']);
			else if ($comeswith_mod['modifier_item_id'] == 4030)
				$this->assertEquals("Ancho Chipotle", $comeswith_mod['mod_name']);
			else
				$this->assertTrue(false,"WE HAVE AN UNEXPECTED MODIFIER ITEM");	
		}
		
*/		
		
		$this->assertNotNull($better_order_details[$item_with_4_comeswith_id]);
		
		$this->assertEquals(10, sizeof($better_order_details[$item_with_4_comeswith_id]['order_detail_modifiers']));
		$this->assertEquals(4, sizeof($better_order_details[$item_with_4_comeswith_id]['order_detail_comeswith_modifiers']));
		$this->assertEquals(6, sizeof($better_order_details[$item_with_4_comeswith_id]['order_detail_added_modifiers']));
		$this->assertEquals(0, sizeof($better_order_details[$item_with_4_comeswith_id]['order_detail_hold_it_modifiers']));
		
		//$this->assertEquals('Test Modifier 1', $better_order_details[$item_with_4_comeswith_id]['order_detail_modifiers'][0]['mod_name']);
		
		$this->assertNotNull($better_order_details[$item_with_1_comeswith_id]);
	}

    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	$_SERVER['request_time1'] = microtime(true);    	
		
		$merchant_resource = createNewTestMerchant();
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;
    	
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);
    	
    	MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $menu_id, 'pickup');
    	    	
    	$user_resource = createNewUser(array('flags'=>'1C20000001'));
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

if (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'use_main'  && !defined('PHPUnit_MAIN_METHOD')) {
    GetOrderTest::main();
}

?>