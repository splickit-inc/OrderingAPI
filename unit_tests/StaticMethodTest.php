<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/functions.inc';
require_once 'lib/utilities/unit_test_functions.inc';

class StaticMethodTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;
	
	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->stamp);
		unset($this->ids);
    }
    
    function testStaticMethodsOnCronCron()
    {
    	$response = MenuAdapter::incrementAllMenus();
		$this->assertTrue($response);
		myerror_log("we have done 1");
		$response = MerchantMessageHistoryAdapter::createMorningSobMessages($this->ids['merchant_id']);
		$this->assertTrue($response);
		myerror_log("we have done 2");
		$response = ErrorsAdapter::clearOldErrorLog(3);
		$this->assertTrue($response);
		myerror_log("we have done 3");
		
/*		
		$response = MerchantMessageHistoryAdapter::generateMessageFailureRatesForToday();	
		$this->assertTrue($response);
		$response = HolidayHourAdapter::doTareksHolidayThing();
		$this->assertTrue($response);
*/		
		$response = MerchantMessageHistoryAdapter::failOldCobMessages();
		myerror_log("we have done 4");
		$this->assertTrue($response);
		$response = MenuAdapter::validateAllMenus();
				myerror_log("we have done 5");
		$this->assertTrue($response);
		$response = COBActivity::createCOBActivitiesForAllOpenActiveMerchants($this->ids['merchant_id']);
				myerror_log("we have done 6");
		$this->assertTrue($response);
		$response = MerchantMessageHistoryAdapter::failOldMessages();
				myerror_log("we have done 7");
		$this->assertTrue($response);
				myerror_log("we have bypassed 8");
		$this->assertTrue($response);
		$response = LateMessageAlerter::processUnviewedMessages();
    			myerror_log("we have done 9");
		$this->assertTrue($response);
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
  		
  		StaticMethodTest::closeOut();
 		}
    
 	static function closeOut() {}
}

if (false && !defined('PHPUnit_MAIN_METHOD')) {
    StaticMethodTest::main();
}

?>