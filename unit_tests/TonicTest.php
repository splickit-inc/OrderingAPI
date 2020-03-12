<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class TonicTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		$this->test_adapter = new MySQLAdapter($mimetypes,
			'Test_Table',
			'%([0-9]{1,10})%',
			'%d',
			array('id'));
		$resource = Resource::createByData($this->test_adapter, $data);
		$this->ids['resource'] = $resource;	

	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
		unset($this->test_adapter);
    }
    
    function testZeroInt()
    {
		$adapter = $this->test_adapter;
		$data['theint'] = 0;
		$test_resource = Resource::createByData($adapter, $data);
		$this->assertEquals(0, $test_resource->theint,"should have set the int to zero");
    }
    
    function testZeroDecimal()
    {
		$adapter = $this->test_adapter;
		$data['thedecimal'] = 0.00;
		$test_resource = Resource::createByData($adapter, $data);
		$this->assertEquals(0.00, $test_resource->thedecimal,"should have set the decimal to zero");
    }
    
    function testZeroFloat()
    {
		$adapter = $this->test_adapter;
		$data['thefloat'] = 0.00;
		$test_resource = Resource::createByData($adapter, $data);
		$this->assertEquals(0.00, $test_resource->thefloat,"should have set the float to zero");
    }

    function testZeroIntUpdate()
    {
    	$value = 0;
    	$the_field = 'theint';
    	
    	$resource = $resource = Resource::createByData($this->test_adapter, $data);
    	$this->assertTrue($resource->$the_field > 0,"default value should have been greater than zero");
    	$resource->$the_field = $value;
    	$resource->save();
    	$resource2 = $resource->getRefreshedResource();
    	$this->assertEquals($value, $resource2->$the_field,"Should have updated it to zero");
    }
    
    function testZeroDecimalUpdate()
    {
    	$value = 0.00;
    	$the_field = 'thedecimal';
    	
    	$resource = $resource = Resource::createByData($this->test_adapter, $data);
    	$this->assertTrue($resource->$the_field > 0,"default value should have been greater than zero");
    	$resource->$the_field = $value;
    	$resource->save();
    	$resource2 = $resource->getRefreshedResource();
    	$this->assertEquals($value, $resource2->$the_field,"Should have updated it to zero");
    }
    
    function testZeroFloatUpdate()
    {
    	$value = 0.00;
    	$the_field = 'thefloat';
    	
    	$resource = $resource = Resource::createByData($this->test_adapter, $data);
    	$this->assertTrue($resource->$the_field > 0,"default value should have been greater than zero");
    	$resource->$the_field = $value;
    	$resource->save();
    	$resource2 = $resource->getRefreshedResource();
    	$this->assertEquals($value, $resource2->$the_field,"Should have updated it to zero");
    }
    
    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
    	$mysql_adapter = new MySQLAdapter($mimetypes);
    	$sql = "DROP TABLE `Test_Table`";
    	$mysql_adapter->_query($sql);
    	$sql = "CREATE TABLE IF NOT EXISTS `Test_Table` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `theint` int(11) NOT NULL DEFAULT 1,
  `thedecimal` decimal(10,6) NOT NULL DEFAULT 111.111111,
  `thefloat` float(5,2) NOT NULL DEFAULT 222.22,
  `thenull` VARCHAR(255) NULL DEFAULT 'string',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` CHAR NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB";

    	$mysql_adapter->_query($sql);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	
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

if (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'use_main'  && !defined('PHPUnit_MAIN_METHOD')) {
    TonicTest::main();
}

?>