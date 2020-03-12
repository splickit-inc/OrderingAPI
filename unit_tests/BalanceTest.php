<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class BalanceTest extends PHPUnit_Framework_TestCase
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
    
    function testBalanceNormal()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$balance_before = 0.00;
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_resource->balance = $balance_before;
    	$user_resource->save();
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',3);
		$tip = $order_data['tip']; 
		$order_resource = placeOrderFromOrderData($order_data, $time);
		$this->assertNull($order_resource->error);
		$order_id = $order_resource->order_id;
		$new_order_resource = SplickitController::getResourceFromId($order_id, 'Order');
		$this->assertEquals(4.50, $new_order_resource->order_amt);
		$this->assertEquals(4.95+$tip, $new_order_resource->grand_total);
		$this->assertEquals(4.95, $new_order_resource->grand_total_to_merchant);
		
		$new_user_resource = Resource::find(new UserAdapter($mimetypes), "$user_id", $options);
		$this->assertTrue($new_user_resource->_exists);
		$this->assertEquals(0.00, $new_user_resource->balance);
		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
			$balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
		}
		$this->assertCount(2, $balance_change_rows_by_user_id);
		$this->assertEquals($balance_before, $balance_change_rows_by_user_id["$user_id-Order"]['balance_before']);
		$this->assertEquals($order_resource->grand_total, -$balance_change_rows_by_user_id["$user_id-Order"]['charge_amt']);
		$this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-Order"]['balance_after']);
		
		$this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_before']);
		$this->assertEquals($balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_before'], -$balance_change_rows_by_user_id["$user_id-CCpayment"]['charge_amt']);
		$this->assertEquals($new_user_resource->balance, $balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_after']);    	
    }
    
    function testBalanceExistingSmallNegativeBalance()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$balance_before = -0.57;
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_resource->balance = $balance_before;
    	$user_resource->save();
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',3);
		$tip = $order_data['tip']; 
		$order_resource = placeOrderFromOrderData($order_data, $time);
		$this->assertNull($order_resource->error);
		$order_id = $order_resource->order_id;
		$new_order_resource = SplickitController::getResourceFromId($order_id, 'Order');
		$this->assertEquals(4.50, $new_order_resource->order_amt);
		$this->assertEquals(4.95+$tip, $new_order_resource->grand_total);
		$this->assertEquals(4.95, $new_order_resource->grand_total_to_merchant);
		
		$new_user_resource = Resource::find(new UserAdapter($mimetypes), "$user_id", $options);
		$this->assertTrue($new_user_resource->_exists);
		$this->assertEquals(0.00, $new_user_resource->balance);
		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
			$balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
		}
		$this->assertCount(2, $balance_change_rows_by_user_id);
		$this->assertEquals($balance_before, $balance_change_rows_by_user_id["$user_id-Order"]['balance_before']);
		$this->assertEquals($order_resource->grand_total, -$balance_change_rows_by_user_id["$user_id-Order"]['charge_amt']);
		$this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-Order"]['balance_after']);
		
		$this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_before']);
		$this->assertEquals($balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_before'], -$balance_change_rows_by_user_id["$user_id-CCpayment"]['charge_amt']);
		$this->assertEquals(0, $balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_after']);    	
    }
    
    function testBalanceWithLargeCredit()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$balance_before = 35.00;
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_resource->balance = $balance_before;
    	$user_resource->save();
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',3);
		$tip = $order_data['tip']; 
		$order_resource = placeOrderFromOrderData($order_data, $time);
		$this->assertNull($order_resource->error);
		$order_id = $order_resource->order_id;
		$new_order_resource = SplickitController::getResourceFromId($order_id, 'Order');
		$this->assertEquals(4.50, $new_order_resource->order_amt);
		$this->assertEquals(4.95+$tip, $new_order_resource->grand_total);
		$this->assertEquals(4.95, $new_order_resource->grand_total_to_merchant);
		
		$new_user_resource = Resource::find(new UserAdapter($mimetypes), "$user_id", $options);
		$this->assertTrue($new_user_resource->_exists);
		$this->assertEquals($balance_before-$order_resource->grand_total, $new_user_resource->balance);
		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
			$balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
		}
		$this->assertCount(1, $balance_change_rows_by_user_id);
		$this->assertEquals($balance_before, $balance_change_rows_by_user_id["$user_id-Order"]['balance_before']);
		$this->assertEquals($order_resource->grand_total, -$balance_change_rows_by_user_id["$user_id-Order"]['charge_amt']);
		$this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-Order"]['balance_after']);
		
		$this->assertNull($balance_change_rows_by_user_id["$user_id-CCpayment"],"should not have found a CC entry for this order");
    }
    
    function testBalanceWithSmallCredit()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$balance_before = 1.50;
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_resource->balance = $balance_before;
    	$user_resource->save();
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',3);
		$tip = $order_data['tip']; 
		$order_resource = placeOrderFromOrderData($order_data, $time);
		$this->assertNull($order_resource->error);
		$order_id = $order_resource->order_id;
		$new_order_resource = SplickitController::getResourceFromId($order_id, 'Order');
		$this->assertEquals(4.50, $new_order_resource->order_amt);
		$this->assertEquals(4.95+$tip, $new_order_resource->grand_total);
		$this->assertEquals(4.95, $new_order_resource->grand_total_to_merchant);
		
		$new_user_resource = Resource::find(new UserAdapter($mimetypes), "$user_id", $options);
		$this->assertTrue($new_user_resource->_exists);
		$this->assertEquals(0.00, $new_user_resource->balance);
		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
			$balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
		}
		$this->assertCount(2, $balance_change_rows_by_user_id);
		$this->assertEquals($balance_before, $balance_change_rows_by_user_id["$user_id-Order"]['balance_before']);
		$this->assertEquals($order_resource->grand_total, -$balance_change_rows_by_user_id["$user_id-Order"]['charge_amt']);
		$this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-Order"]['balance_after']);
		
		$this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_before']);
		$this->assertEquals($balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_before'], -$balance_change_rows_by_user_id["$user_id-CCpayment"]['charge_amt']);
		$this->assertEquals($new_user_resource->balance, $balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_after']);    	
    }

    function testBalanceWithSmallCreditButOrderDoesNotTakeUserPastLimit()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$balance_before = 1.50;
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_resource->balance = $balance_before;
    	$user_resource->save();
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',1);
    	$tip = 0.01;
    	$order_data['tip'] = $tip;
		$order_resource = placeOrderFromOrderData($order_data, $time);
		$this->assertNull($order_resource->error);
		$order_id = $order_resource->order_id;
		$new_order_resource = SplickitController::getResourceFromId($order_id, 'Order');
		$this->assertEquals(1.50, $new_order_resource->order_amt);
		$this->assertEquals(1.65+$tip, $new_order_resource->grand_total);
		$this->assertEquals(1.65, $new_order_resource->grand_total_to_merchant);
		
		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		$balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options);
		$this->assertCount(2, $balance_change_records);
		$balance_change_record = $balance_change_records[0];
		$this->assertEquals($balance_before, $balance_change_record['balance_before']);
		$this->assertEquals($order_resource->grand_total, -$balance_change_record['charge_amt']);
		$this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_record['balance_after']);
		$this->assertEquals("Order",$balance_change_record['process']);

		$balance_change_record = $balance_change_records[1];
		$this->assertEquals(-.16, $balance_change_record['balance_before']);
		$this->assertEquals(.16, $balance_change_record['charge_amt']);
		$this->assertEquals("CCpayment",$balance_change_record['process']);

		$new_user_resource = Resource::find(new UserAdapter($mimetypes), "$user_id", $options);
		$this->assertTrue($new_user_resource->_exists);
		$this->assertEquals(0.00, $new_user_resource->balance);
		
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
    BalanceTest::main();
}

?>