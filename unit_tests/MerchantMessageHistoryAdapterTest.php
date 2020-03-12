<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class MerchantMessageHistoryAdapterTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;
	var $mmha;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		$this->mmha = new MerchantMessageHistoryAdapter($mimetypes);
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
		unset($this->mmha);
    }
    
    function testMarkMessageViewedOnRestagedMessage()
    {
    	$merchant_resource = createNewTestMerchant();
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$message_resource = $this->mmha->createMessageReturnResource($merchant_id, $order_id, 'GUC', '1234567890', time()+30, 'X', 'firmware=8.0', $message_text);
    	$message_resource->viewed = 'N';
    	$message_resource->save();

    	$this->mmha->markMessageAsViewedById($message_resource->map_id);
    	
    	$refreshed = $message_resource->refreshResource($message_resource->map_id);
    	$this->assertEquals('S', $refreshed->locked);
    	$this->assertEquals('V', $refreshed->viewed);
    	$stamp = $refreshed->stamp;
    	$this->assertEquals(getRawStamp().'-callback;', $stamp);

    }
    
    static function setUpBeforeClass()
    {
    	set_time_limit(30);
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
    MerchantMessageHistoryAdapterTest::main();
}

?>