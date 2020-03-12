<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class ErrorsAdapterTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		$sql = "DELETE FROM Errors WHERE 1=1";
		$ea = new ErrorsAdapter($mimetypes);
		$ea->_query($sql);
		$sql = "UPDATE Merchant_Message_History SET locked = 'F' where locked = 'N'";
		$ea->_query($sql);
		setProperty("jersey_mikes_ordering_on", "true");
		
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }
    
    function testCheckForNewErrors()
    {
    	$sql = "DELETE FROM Errors WHERE 1=1";
    	$errors_adapter = new ErrorsAdapter($mimetypes);
    	$errors_adapter->_query($sql);
    	$sql = "UPDATE Merchant_Message_History_Adapter SET locked = 'S' WHERE locked = 'N' and message_format = 'E'";
    	$errors_adapter->_query($sql);
    	
    	recordError("LONG QUERY ERROR", "test pf the long query error code");
    	$result = $errors_adapter->checkForNewErrors(1);
    	$this->assertEquals(1, $result);
    	//should not have created a record
    	$mmha = new MerchantMessageHistoryAdapter();
    	$records = $mmha->getRecords(array("message_format"=>'E',"locked"=>'N'), $options);
    	$this->assertEquals(0, count($records));

    	recordError($m2, "This is a TEST of the Error System", "Realy its just a test");
    	$result = $errors_adapter->checkForNewErrors(1);
    	$this->assertEquals(2, $result);
    	
    	// check to see if an email was created
    	$mmha = new MerchantMessageHistoryAdapter();
    	$records = $mmha->getRecords(array("message_format"=>'E',"locked"=>'N'), $options);
    	$this->assertEquals(1, count($records));
    }
    
    function testNewCheckForNewLoggedErrors()
    {
    	$errors_adapter = new ErrorsAdapter($mimetypes);
    	$this->assertEquals(0,$errors_adapter->checkForNewLoggedErrors('Offline Jersey Mikes Store', 1, 1,false));
    	
    	// now lets add some errors
    	logError($m2,"Offline Jersey Mikes Store 12345", "Setting store to innactive. merchant_id: 44567");
    	logError($m2,"Offline Jersey Mikes Store 12345", "Setting store to innactive. merchant_id: 44567");
    	$count = $errors_adapter->checkForNewLoggedErrors('Offline Jersey Mikes Store', 1, 1,false);
    	$this->assertEquals(2, $count);
    	
    	$count2 = $errors_adapter->checkForNewLoggedErrors('Offline Jersey Mikes Store', 1, 1,true);
    	$this->assertEquals(1, $count2);
    }
    
    function testClearOutOldErrorLogs()
    {
    	$this->assertFalse(ErrorsAdapter::clearOldErrorLog('F'));
    	$this->assertTrue(ErrorsAdapter::clearOldErrorLog(0));
    }
    
    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
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

if (false && !defined('PHPUnit_MAIN_METHOD')) {
    ErrorsAdapterTest::main();
}

?>