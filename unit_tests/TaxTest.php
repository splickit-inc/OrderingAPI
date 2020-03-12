<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class TaxTest extends PHPUnit_Framework_TestCase
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
    
    function testregularTaxRate()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours', 1);
		$tip = $order_data['tip'];
		$response = placeOrderFromOrderData($order_data, $time);
		$order_id = $response->order_id;
		$this->assertTrue($order_id > 1000,"should have created a valid order");
		$this->assertNotNull($response->order_summary,"should have an order_summary field");
		$this->assertEquals(.15, $response->total_tax_amt);
		$total = 1.65+$tip;
		$this->assertEquals($total, $response->grand_total);    	
    }
    
    function testGetFixedTaxList()
    {
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	FixedTaxAdapter::createTaxRecord($merchant_id, "The Big Bag Tax", .25);
    	FixedTaxAdapter::createTaxRecord($merchant_id, "The Other Tax", .55);
    	$fixed_tax_list = FixedTaxAdapter::staticGetFixedTaxRecordsHashMappedByName($merchant_id);
    	$this->assertCount(2, $fixed_tax_list);
    	$this->assertEquals(.25, $fixed_tax_list['The Big Bag Tax']);
    	$this->assertEquals(.55, $fixed_tax_list['The Other Tax']);
    }
    
    function testFixedTaxRate()
    {
    	$bag_tax = .33;
    	$merchant_id = $this->ids['merchant_id'];
    	FixedTaxAdapter::createTaxRecord($merchant_id, "Bag Tax", $bag_tax);
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	logTestUserIn($user_id);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours', 1);
		$tip = $order_data['tip'];
		$response = placeOrderFromOrderData($order_data, $time);
		$order_id = $response->order_id;
		$this->assertTrue($order_id > 1000,"should have created a valid order");
		$this->assertNotNull($response->order_summary,"should have an order_summary field");
		$this->assertEquals(.15+$bag_tax, $response->total_tax_amt);
		$total = 1.65 + $bag_tax + $tip;    	
    	$this->assertEquals($total, $response->grand_total);
    	$order_summary = $response->order_summary;
    	$receipt_items = $order_summary['receipt_items'];
    	$better_receipt_items = createHashOfRecieptItemsByTitle($receipt_items);
    	$this->assertNotNull($better_receipt_items['Bag Tax'],"Should have found a reciept item for bag tax");
    	$this->assertEquals("$0.33", $better_receipt_items['Bag Tax']);
    	$this->assertEquals("$0.15", $better_receipt_items['Tax']);
    	
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$message_resource = $mmha->getRecord(array("order_id"=>$order_id,"message_format"=>"Econf"));
    	myerror_log($message_resource['message_text']);
    	$this->assertContains("Bag Tax", $message_resource['message_text']);
    	$this->assertContains("$0.33", $message_resource['message_text']);

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
    	
/*    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);
*/
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
    TaxTest::main();
}

?>