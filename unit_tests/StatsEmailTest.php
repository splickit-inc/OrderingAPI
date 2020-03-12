<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class StatsEmailTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		setProperty('do_not_call_out_to_aws','false');
		
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
		setProperty('do_not_call_out_to_aws','true');
		unset($_SERVER['FORCE_PROD']);
    }
	
//	function testGetEmailForPitaPit()
//	{
//		$brand_id = 282;
//		$_SERVER['FORCE_PROD'] = 'true';
//		$stats_email_activity = new StatsEmailActivity();
//		$stats_email_activity->date_of_end = '2016-05-01 00:01:00';
//
//		$email_body = $stats_email_activity->processBrand($brand_id);
//		$_SERVER['FORCE_PROD'] = 'false';
//	}
    
    function testGetTotalMobileCustomersForBrand()
    {
    	$activity = new StatsEmailActivity($resource);
    	$number_of_mobile = $activity->getTotalMobileCustomers(324);
    	$this->assertEquals(3, $number_of_mobile);
    }
    
    function testGetTotalWebCustomers()
    {
    	$user_resource = createNewUser();
    	$user_resource->device_id = 'nullit';
    	$user_resource->skin_id = $this->ids['skin_id'];
    	$user_resource->save();
    	$activity = new StatsEmailActivity($resource);
    	$number_of_web = $activity->getTotalWebCustomers(324);
    	$this->assertEquals(1, $number_of_web);
    }
    
    function testGetTotalNewCustomers()
    {
    	$activity = new StatsEmailActivity($resource);
    	$activity->number_of_days_back = 30;
    	$number_of_new = $activity->getNewCustomers(324);
    	$this->assertEquals(4, $number_of_new);
    }
    
    function testGetAverageTicketSize()
    {
    	$sql = "UPDATE Orders SET status = 'E' WHERE skin_id = 325";
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_adapter->_query($sql);
    	
    	$activity = new StatsEmailActivity($resource);
    	$activity->number_of_days_back = 30;
    	$average_ticket_size = $activity->getAverageTicketSize(324);
    	$this->assertTrue($average_ticket_size > 1.00);
    }
    
    function testGetLifetimeTransactions()
    {
    	$activity = new StatsEmailActivity($resource);
    	$lifetime_transaction_total = $activity->getLifeTimeTransactedDollars(324);
    	$this->assertTrue($lifetime_transaction_total > 1.00,"lifetime transaction total shoudl be greater than $1.00");
    }
    
    function testGetTopLocations()
    {
    	
    	$activity = new StatsEmailActivity($resource);
    	$activity->number_of_days_back = 30;
    	$top_locations = $activity->getTopLocations(324);
    	$this->assertTrue(is_array($top_locations));
    	$top_location = $top_locations[0];
    	$this->assertNotNull($top_location['weekly_order_total']);
    	$this->assertNotNull($top_location['weekly_revenue_total']);	
    }
    
    function testTopCustomers()
    {
    	$activity = new StatsEmailActivity($resource);
    	$activity->number_of_days_back = 30;
    	$customers = $activity->getTopOrderingCustomersForPeriod(324);
    	$this->assertTrue(is_array($customers));
    	$customer = $customers[0];
    	$this->assertNotNull($customer['number_of_orders']);
    	$this->assertNotNull($customer['period_total']);	
    }
    
    function testGetTemplateFromS3()
    {
    	$email_service = new EmailService();
    	$email = $email_service->getCrmTemplate();
    	$this->assertNotNull($email);
    }
    
    function testSaveWeeklyBrandData()
    {
    	$activity = new StatsEmailActivity($resource);
    	$activity->brands_to_send = array("324"=>1);
    	$data = $activity->getAllBrandData(324);
    	$this->assertNotNull($data);
    	$stats_resource = $activity->saveStats(324,$data);
    	$this->assertNotNull($stats_resource);
    	$this->assertTrue($stats_resource->insert_id > 999);
    	$bwsea = new BrandStatsEmailWeeklyRecordAdapter($mimetypes);
    	$record = $bwsea->getRecord(array("brand_id"=>324), $options);
    	$this->assertEquals(date('Y-m-d'), $record['for_week_ending']);
    	$json = $record['data_as_json'];
    	myerror_log($json);
    	$data_array = json_decode($json,true);
    	$this->assertTrue(isset($data_array['average_ticket_size']),"should have found an average ticket size field");
    	$sql = "DELETE FROM brand_stats_email_weekly_record where 1=1";
    	$bwsea->_query($sql);
    }
    
    function testGetEmail()
    {
    	$activity = new StatsEmailActivity($resource);
    	$activity->brands_to_send = array("324"=>1);
    	$result = $activity->doit();
    	$this->assertTrue($result);
    	$lifetime_transaction_total = $activity->getLifeTimeTransactedDollars(324);
    	$merchant_resource = SplickitController::getResourceFromId($this->ids['merchant_id'], 'Merchant');
    	$mmh_id = $activity->merchant_message_history_id;
    	$this->assertTrue($mmh_id > 1000);
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$mmh_record = $mmha->getRecord(array("map_id"=>$mmh_id), $options);
    	$email_body = $mmh_record['message_text'];
    	
    	myerror_log($email_body);
    	//echo ($email_body);
    	$this->assertNotNull($email_body);
    	$email_body = cleanUpCRLFTFromString($email_body);
    	$email_body = str_replace("  ", '', $email_body);
    	$this->assertContains($lifetime_transaction_total, $email_body);
    	$this->assertContains($merchant_resource->address1,$email_body);
    	$this->assertContains('Customer1', $email_body);
    	$this->assertContains('Customer2', $email_body);
    	
    	$bwsea = new BrandStatsEmailWeeklyRecordAdapter($mimetypes);
    	$record = $bwsea->getRecord(array("brand_id"=>324), $options);
    	$this->assertEquals(date('Y-m-d'), $record['for_week_ending']);
    	$json = $record['data_as_json'];
    	myerror_log($json);
    	$data_array = json_decode($json,true);
    	$this->assertTrue(isset($data_array['average_ticket_size']),"should have found an average ticket size field");
    	
    }
    
    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',0);
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',324);
        SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;
    	$skin_resource = getOrCreateSkinAndBrandIfNecessary('dummy', 'dummy', 325, 324);
    	//$skin_resource = createWorldHqSkin();
    	$skin_resource->in_production = 'Y';
    	$skin_resource->save();
    	$ids['skin_id'] = $skin_resource->skin_id;
    	$_SERVER['HTTP_NO_CC_CALL'] = 'true';
    	
    	setContext("com.splickit.dummy");
    	
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(1);
    	$ids['menu_id'] = $menu_id;

    	$merchant_resource = createNewTestMerchant($menu_id);
    	$merchant_resource->brand_id = 324;
    	$merchant_resource->save();
    	$merchant_id = $merchant_resource->merchant_id;
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
    	$user_resource1 = createNewUser(array("flags"=>"1C20000001","skin_id"=>$skin_resource->skin_id,"first_name"=>"customer1"));
    	
    	$user_resource2 = createNewUser(array("flags"=>"1C20000001","skin_id"=>$skin_resource->skin_id,"first_name"=>"customer2"));
    	$user_resource3 = createNewUser(array("flags"=>"1C20000001","skin_id"=>$skin_resource->skin_id));
    	$user = logTestUserIn($user_resource1->user_id);
    	$ids['user_id'] = $user_resource1->user_id;

    	$order_adapter = new OrderAdapter($mimetypes);
    	
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
    	$order_resource = placeOrderFromOrderData($order_data, getTodayTwelveNoonTimeStampDenver());
    	$order_data['tip'] = (rand(100, 1000))/100;
    	$order_resource = placeOrderFromOrderData($order_data, getTodayTwelveNoonTimeStampDenver());
    	
    	$user = logTestUserIn($user_resource2->user_id);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
    	$order_resource = placeOrderFromOrderData($order_data, getTodayTwelveNoonTimeStampDenver());
    	$order_data['tip'] = (rand(100, 1000))/100;
    	$order_resource = placeOrderFromOrderData($order_data, getTodayTwelveNoonTimeStampDenver());

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
    StatsEmailTest::main();
}

?>