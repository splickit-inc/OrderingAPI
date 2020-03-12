<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class WorkerSelectionTest extends PHPUnit_Framework_TestCase
{
	var $stamp;

	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
    }
    
    function testEmptyStringSTampProblem()
    {
    	$sql = "UPDATE Merchant_Message_History SET locked='C' where locked = 'N'";
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$mmha->_query($sql);
    	
    	$map_id = $mmha->createMessage(1004, null, 'E', "dummy@dummy.com", time()-10, 'I', $info, "hello world");
    	$sql = "UPDATE Merchant_Message_History SET stamp = '' WHERE map_id = $map_id";
        mysqli_query(Database::getInstance(),$sql);
    	
    	$message_resource = SplickitController::getResourceFromId($map_id, 'MerchantMessageHistory');
    	$this->assertNotNull($message_resource);
    	$locked_message_resource = $mmha->getLockedMessageResourceForSending($message_resource);
    	
    	$raw_stamp = getRawStamp();
    	$this->assertEquals($raw_stamp.';', $locked_message_resource->stamp);
    	$this->assertEquals(1, $locked_message_resource->tries);
    	$this->assertEquals('Y', $locked_message_resource->locked);
    	
    }
    
    function testMessageSelection()
    {
    	$sql = "UPDATE Merchant_Message_History SET locked='C' where locked = 'N'";
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$mmha->_query($sql);
    	
    	setProperty('worker_message_load', 20);
    	
    	createMessages(5,1004,'E','N',false);
    	$mmha->createMessage(1004, null, 'E', "dummy@dummy.com", time()-100, 'I', $info, "hello world");
    	$mmha->createMessage(1004, null, 'E', "dummy@dummy.com", time()-101, 'I', $info, "hello world");
    	$mmha->createMessage(1004, null, 'E', "dummy@dummy.com", time()-102, 'I', $info, "hello world");
    	createMessages(5,1004,'E','N',false);
    	
    	$message_resources = $mmha->getAvailableMessageResourcesArray($mmha_options);
    	$this->assertEquals(13, sizeof($message_resources, $mode));
    	$this->assertTrue($message_resources[0]->order_id > 1000);
    	$this->assertTrue($message_resources[12]->order_id == null);
    	$this->assertTrue($message_resources[11]->order_id == null);
    	$this->assertTrue($message_resources[10]->order_id == null);
    	
    	setProperty('worker_message_load', 10);
    	$message_resources = $mmha->getAvailableMessageResourcesArray($mmha_options);
    	$this->assertEquals(10, sizeof($message_resources, $mode));
    	foreach ($message_resources as $message_resource)
    		$this->assertTrue($message_resource->order_id > 1000);
    		
    	$message_resource = $message_resources[0];
    	$locked_message_resource = $mmha->getLockedMessageResourceForSending($message_resource);
    	
    	$this->assertEquals(getRawStamp().';', $locked_message_resource->stamp);
    	$this->assertEquals(1, $locked_message_resource->tries);
    	$this->assertEquals('Y', $locked_message_resource->locked);
    }
    
    static function setUpBeforeClass()
    {
    	//ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    }
    
	static function tearDownAfterClass()
    {
    	//SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
    }

    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (false && !defined('PHPUnit_MAIN_METHOD')) {
    WorkerSelectionTest::main();
}

?>