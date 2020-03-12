<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class CompleteOrderTest extends PHPUnit_Framework_TestCase
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
  	$_SERVER['STAMP'] = $this->stamp;
  	unset($this->ids);
  }
    
  function testGetModifierStringFromOrderDetail() {
    $order_detail = array("order_detail_complete_modifier_list_no_holds" => array(array("mod_name" => "Turquoise Sauce"), array("mod_name" => "Lemon Ketchup"), array("mod_name" => "Beetles", "mod_quantity" => 44),array("mod_name" => "Licorice"), array("mod_name" => "Styrofoam")));
    $co = new CompleteOrder($m);
    $output = $co->getModifierStringFromOrderDetail($order_detail);
    $this->assertEquals("Turquoise Sauce, Lemon Ketchup, Beetles(x44), Licorice, Styrofoam", $output, "The modifiers should be comma/space separated and include quantities where specified, and should strip the terminal comma.");    
  }
    
    /**
     * @expectedException Exception
     */
    function testNoMatchingOrderId()
    {
    	$complete_order = new CompleteOrder($mimetypes);
    	$order = $complete_order->getCompleteOrder(345345345, $mimetypes);
    }

    /**
     * @expectedException MissingOrderDetailsException
     */
    function testDataCorruptionOnOrderNoOrderDetails()
    {
        $user = logTestUserIn($this->ids['user_id']);
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($this->ids['merchant_id']);
        $time_stamp = getTodayTwelveNoonTimeStampDenver();
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $sql = "UPDATE Order_Detail set logical_delete = 'Y' WHERE order_id = $order_id";
        $order_adapter = new OrderAdapter($m);
        $order_adapter->_query($sql);
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
    }


    function testIgnoreOldMissingDetails()
    {
        $user = logTestUserIn($this->ids['user_id']);
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($this->ids['merchant_id']);
        $time_stamp = getTodayTwelveNoonTimeStampDenver();
        $resource = placeOrderFromOrderData($order_data, $time_stamp);
        $this->assertNull($resource->error);
        $order_id = $resource->order_id;
        $sql = "UPDATE Order_Detail set logical_delete = 'Y' WHERE order_id = $order_id";
        $order_adapter = new OrderAdapter($m);
        $order_adapter->_query($sql);

        $order_resource = Resource::find($order_adapter,"$order_id");
        $order_resource->order_dt_tm = date('Y-m-d H:i:s',time()-(31*24*60*60));
        $order_resource->save();
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
        $this->assertNotNull($complete_order,"It should have returned the complete order");
    }

    function testNoItemsForOrderIdAfterLastItemDeletedFromCart()
    {
        $user = logTestUserIn($this->ids['user_id']);
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($this->ids['merchant_id']);
        $time_stamp = getTodayTwelveNoonTimeStampDenver();
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $sql = "UPDATE Order_Detail set logical_delete = 'Y' WHERE order_id = $order_id";
        $order_adapter = new OrderAdapter($m);
        $order_adapter->_query($sql);

        $order_resource = Resource::find(new OrderAdapter($m),"$order_id");
        $order_resource->order_qty = 0;
        $order_resource->save();
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
        $this->assertNotNull($complete_order,"should still have a complete order object");
        $this->assertNull($complete_order['order_summary'],"should be no order summary since there are no items. just an empty cart orders");
        $base_order = CompleteOrder::getBaseOrderData($order_id,$m);
        $this->assertEquals($base_order,$complete_order,"base order should be the same as complete order");
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
    CompleteOrderTest::main();
}

?>