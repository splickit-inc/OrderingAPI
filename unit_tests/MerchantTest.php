<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class MerchantTest extends PHPUnit_Framework_TestCase
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

    function testGetMerchantMetaData()
    {
        $new_merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $merchant_adapter = new MerchantAdapter(getM());
        $merchant_resource = $merchant_adapter->getMerchantMetadata($new_merchant_resource->merchant_id);
        $this->assertEquals($new_merchant_resource->merchant_id, $merchant_resource->merchant_id);
        $this->assertTrue(isset($merchant_resource->total_tax));
        $this->assertTrue(isset($merchant_resource->tax_rates));
        $this->assertTrue(isset($merchant_resource->hours));
        $this->assertTrue(isset($merchant_resource->delivery_hours));
    }


    function testSetAlphasAndLatLng()
    {
    	$_SERVER['NOSLEEP'] = true;
    	$merchant_resource = $this->ids['merchant_resource'];
    	$merchant_id = $merchant_resource->merchant_id;
    	$merchant_resource->lat = 0.00;
    	$merchant_resource->lng = 0.00;
    	$merchant_resource->numeric_id = "nullit";
    	$sql = "UPDATE Merchant SET lat = 0,lng = 0, numeric_id = NULL WHERE merchant_id = $merchant_id";
    	$merchant_adapter = new MerchantAdapter(getM());
    	$merchant_adapter->_query($sql);

//    	$this->assertTrue($merchant_resource->save());
//    	$record_resource = MerchantAdapter::getMerchantFromIdOrNumericId($this->ids['merchant_id']);
    	$this->assertEquals(0, $record_resource->lat);
    	$this->assertNull($record_resource->numeric_id);
    	
    	MerchantAdapter::setAlphaNumericAndLatLongsOfNewMerchants();
    	$record_resource = MerchantAdapter::getMerchantFromIdOrNumericId($this->ids['merchant_id']);
    	$this->assertEquals(45.204389, $record_resource->lat);
    	$this->assertTrue($record_resource->numeric_id > 1000);
    	
    }



    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	$ids['user_id'] = $user_id;
    	
    	$skin_resource = createWorldHqSkin();
    	$ids['skin_id'] = $skin_resource->skin_id;

    	$sql = "UPDATE Merchant SET logical_delete = 'Y' WHERE 1 = 1";
    	$merchant_adapter = new MerchantAdapter($mimetypes);
    	$merchant_adapter->_query($sql);
    	$merchant_resource = createNewTestMerchant($menu_id);
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$merchant_id = $merchant_resource->merchant_id;

    	$menu = Resource::createByData(new MenuAdapter($m), array());
    	$menu_id = $menu->menu_id;
    	$mt = Resource::createByData(new MenuTypeAdapter($m), array('menu_id' => $menu_id));
    	$item = Resource::createByData(new ItemAdapter($m), array('menu_type_id' => $mt->menu_type_id));
    	$specific_size = Resource::createByData(new SizeAdapter($m), array('size_name' => 'Super', 'active' => true, 'menu_type_id' => $mt->menu_type_id));
    	
    	$specific_item_size = Resource::createByData(new ItemSizeAdapter($m), array('merchant_id' => $merchant_id, 'item_id' => $item->item_id, 'size_id' => $specific_size->size_id, 'price' => 5.49));
    	
    	Resource::createByData(new MerchantMenuMapAdapter($m), array('merchant_id' => $merchant_id, 'menu_id' => $menu_id, 'merchant_menu_type' => 'pickup'));
        $ids['menu_id'] = $menu_id;
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	$ids['merchant_resource'] = $merchant_resource;
    	
    	$_SERVER['log_level'] = 5; 
		  $_SERVER['unit_test_ids'] = $ids;
    }
    
	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
    	date_default_timezone_set($_SERVER['starting_tz']);
    	setProperty('use_merchant_caching', false);
    }

    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    MerchantTest::main();
}

?>