<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';
use phpFastCache\CacheManager;

class AdapterTest extends PHPUnit_Framework_TestCase
{
	var $adapter;

	function setUp()
	{
		;
	}
	
	function tearDown() 
	{
       ;
    }

    function testIsErrorADeadLock()
    {
        $error_text = "Deadlock found when trying to get lock; try restarting transaction";
        $sql_adapter = new SQLAdapter(getM());

        $this->assertFalse($sql_adapter->isErrorADeadLock("sum dum Error"));
        $this->assertTrue($sql_adapter->isErrorADeadLock($error_text));
    }

    function testMemecache()
    {
        $value_to_save = 'sum dum value';
        $key = createCode(10);
        $splickit_cache = new SplickitCache();
        if ($value = $splickit_cache->getCache($key)) {
            myerror_log("WE HAVE THE value: $value");
            $this->assertTrue(false,"It should NOT have found the value");
        } else {
            myerror_log("we are goig to set the value");
            $splickit_cache->setCache($key,$value_to_save,65);
        }

        $sc = new SplickitCache();
        if ($value = $sc->getCache($key)) {
            myerror_log("WE HAVE THE value: $value");
            $this->assertEquals($value_to_save,$value);
        } else {
            myerror_log("we are goig to set the value");
            $this->assertTrue(false,"It shold NOT have to save the value again");
        }
    }

    function testCreateUser()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $this->assertNotNull($user_resource,"It Should have created a user resource");
        $uuid = $user_resource->uuid;
        $new_uuid = '1234-5678-9012-3456';
        $user_resource->uuid = $new_uuid;
        $user_resource->save();
        $user_resource = $user_resource->refreshResource($user_resource->user_id);
        $this->assertEquals($new_uuid,$user_resource->uuid,"IT shoudl have set the UUID to the new value");
        $this->assertNotEquals($uuid,$new_uuid,"THe UUID's should not be the smae");

    }

    function testMoreThenOneMatchingRowException()
    {
        try {
            $skin = SkinAdapter::staticGetRecord(array("logical_delete"=>'N'),'SkinAdapter');
            $this->assertTrue(false,"It should have thrown an error on the select");
        } catch (MoreThanOneMatchingRecordException $e) {
            $message = $e->getMessage();
            $this->assertContains('FROM Skin  WHERE (Skin.`logical_delete` = "N")',$message,"Error message should have the SQL as part of it");
        }
    }

    function testSendBackCorrectInsertId()
    {
    	$menu_adapter = new MenuAdapter($mimetypes);
    	
    	$data['name'] = "test insert id";
    	$data['description'] = "test insert id";
    	$data['last_menu_change'] = time();
    	$resource = Resource::createByData($menu_adapter, $data);
    	$first_id = $resource->menu_id;
    	
    	$code = generateCode(10);
    	$data['external_id'] = $code;
    	setSessionProperty('long_query_threshold', "0");
    	$resource2 = Resource::createByData($menu_adapter, $data);
    	$second_id = $first_id + 1;
    	$this->assertEquals($second_id,$resource2->menu_id);
    	setSessionProperty('long_query_threshold', "5");
    }

    /*
     * choose a table that does NOT allow full table scan.  Orders
     */
    function testSelectNoWHereClause()
    {
    	// set a dummy field
    	$options[TONIC_FIND_BY_METADATA]['testing'] = 123445;
    	$order_adapter = new OrderAdapter($mimetypes);
    	$results = $order_adapter->select('',$options);
    	$this->assertEquals(0, sizeof($results), "should not have returned any results");
    	
    }
    
    function testAllowFullTableScan()
    {
    	// set a dummy field
    	$options[TONIC_FIND_BY_METADATA]['testing'] = 123445;
    	$property_adapter = new PropertyAdapter($mimetypes);
    	$results = $property_adapter->select('',$options);
    	
    	$this->assertNotNull($results);
    	$this->assertTrue(sizeof($results) > 10);
    	
    }
    
    function testSelectPrimaryId()
    {

    	$order_adapter = new OrderAdapter($mimetypes);
    	$sql = "Select * From Property ORDER BY id desc limit 1";
   		$result_set = $order_adapter->_query($sql);
   		$row = mysqli_fetch_array($result_set);
   		$property_id = $row['id'];

   		$property_adapter = new PropertyAdapter($mimetypes);
    	$results = $property_adapter->select($property_id);
    	$this->assertEquals(1, sizeof($results));
    	$property_row = $results[0];
    	
    	$property_resource = Resource::find($property_adapter,''.$property_id);
    	
    	$this->assertNotNull($property_resource);
    	$property_resource_row = $property_resource->getDataFieldsReally();
    	
    	$this->assertEquals($property_row['id'], $property_resource_row['id']);
    	$this->assertEquals($property_row['name'], $property_resource_row['name']);
    	$this->assertEquals($property_row['value'], $property_resource_row['value']);
    	
    }
    
    function testVarcharAsIntProblem()
    {
    	$device_id = 1234565677;
    	$data['device_id'] = $device_id;
    	$data['first_name'] = 'SpTemp';
    	$data['last_name'] = 'User';
    	$user_adapter = new UserAdapter($mimetypes);
    	$record = $user_adapter->getRecord($data);
    	$sql = $user_adapter->getLastRunSQL();
    	$this->assertContains("\"$device_id\"", $sql);
    	
    	$data['device_id'] = "$device_id";
    	$record = $user_adapter->getRecord($data);
    	$sql = $user_adapter->getLastRunSQL();
    	$this->assertContains("\"$device_id\"", $sql);
    }
    
   	static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['default_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
        clearAuthenticatedUserParametersForSession();

        SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();
        $mysqli->begin_transaction();


    }
    
	static function tearDownAfterClass()
    {
        SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();
        $mysqli->rollback();

    	setSessionProperty('long_query_threshold', "5");
    	setProperty('long_query_threshold', "5");
    }    
	
	/* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    AdapterTest::main();
}

?>



