<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class RebillingTest extends PHPUnit_Framework_TestCase
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
    
    function createNewOrderWithNoBalanceChangeRecords()
    {
    	$item_count = rand(1, 5);
    	$ids = $this->ids;
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user = logTestUserIn($user_resource->user_id);
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours', $item_count);
		$response = placeOrderFromOrderData($order_data, $time);
		$order_id = $response->order_id;
		if ($order_id > 1000) {
			$order_adapter->updateOrderStatus('E', $order_id);
			// now delete balance change rows
			$sql = "DELETE FROM Balance_Change WHERE order_id = $order_id";
			$order_adapter->_query($sql);
			return $order_id;
		}
    }
    
    function testRetroBillOrderByOrderResource()
    {
    	$order_id = $this->createNewOrderWithNoBalanceChangeRecords();
    	
    	$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
    	$records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options);
    	$this->assertCount(0, $records);
    	
    	$order_resource = SplickitController::getResourceFromId($order_id, 'Order');
    	$result = OrderController::retroBillOrderByOrderResource($order_resource);
    	$this->assertTrue($result);
    	
    	$records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options);
    	$this->assertCount(2, $records);
    	foreach ($records as $bc_record) {
    		if ($bc_record['process'] == 'Order') {
    			$order_record = $bc_record;
    		} else if ($bc_record['process'] == 'CCpayment') {
    			$cc_record = $bc_record;
    		}
    	}
    	$this->assertNotNull($order_record);
    	$this->assertEquals($order_resource->grand_total,-$order_record['charge_amt']);
    	$this->assertNotNull($cc_record);
    	$this->assertEquals($order_resource->grand_total,$cc_record['charge_amt']);
    	
    	$new_order_resource = SplickitController::getResourceFromId($order_id, 'Order');
    	$this->assertEquals("re-billed", $new_order_resource->payment_file);
    }
    
/*	function testRetroBillOrdersBYSQLquery()
    {
    	$this->createNewOrderWithNoBalanceChangeRecords();	
    	$this->createNewOrderWithNoBalanceChangeRecords();	
    	$this->createNewOrderWithNoBalanceChangeRecords();	
    	$this->createNewOrderWithNoBalanceChangeRecords();	
    	$this->createNewOrderWithNoBalanceChangeRecords();	
    	
    	// there should be 5 orders

    }
*/    
    function testRetroBillOrdersBYSQLquery()
    {
    	$this->createNewOrderWithNoBalanceChangeRecords();	
    	$this->createNewOrderWithNoBalanceChangeRecords();	
    	$this->createNewOrderWithNoBalanceChangeRecords();	
    	$this->createNewOrderWithNoBalanceChangeRecords();	
    	$this->createNewOrderWithNoBalanceChangeRecords();	
    	$merchant_id = $this->ids['merchant_id'];
    	
    	// there should be 5 orders
     	//$sql = "select * from Orders where user_id > 19999 AND date(order_dt_tm) = date(now()) and status = 'E' and skin_id != 72 and order_id < 2617592 and order_id > 2614655 and cash = 'N' and payment_file IS NULL order by order_id asc";
    	$sql = "Select * from Orders where user_id > 19999 AND date(order_dt_tm) = date(now()) and status = 'E' and merchant_id = $merchant_id and cash = 'N' and payment_file IS NULL order by order_id asc";
    	
    	$results = OrderController::retroBillOrdersBySQLQuery($sql);
    	$this->assertCount(4, $results);
    	$successes = $results['successes'];
    	$this->assertEquals(5, $successes);
    	$this->assertEquals(5, $results['total_orders_run']);
    	
    	$results = OrderController::retroBillOrdersBySQLQuery($sql);
    	$this->assertCount(4, $results);
    	$successes = $results['successes'];
    	$this->assertEquals(0, $successes);
    	$this->assertEquals(0, $results['total_orders_run']);

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

if (false && !defined('PHPUnit_MAIN_METHOD')) {
    RebillingTest::main();
}

?>