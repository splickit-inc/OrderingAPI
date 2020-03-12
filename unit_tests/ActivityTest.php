<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class ActivityTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $merchant_id;
	var $ids;

	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		$this->merchant_id = $_SERVER['unit_test_ids']['merchant_id'];
		$sql = "UPDATE Activity_History SET locked = 'F' WHERE locked IN ('N','Y')";
		$aha = new ActivityHistoryAdapter($mimetypes);
		$aha->_query($sql);
	}
	
	function tearDown() 
	{
		//delete your instance
		unset($this->ids);
		unset($this->merchant_id);
		$_SERVER['STAMP'] = $this->stamp;
    }

//    function testTareksTemplate()
//    {
//        $doit_ts = time()-2;
//        $info = 'period=week;destination=file;test=true';
//        $activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
//        $activity_history_resource = $activity_history_adapter->createActivityReturnActivityResource('SendMerchantStatement', $doit_ts, $info, $activity_text);
//
//
//        $locked_activity_resource = LockedActivityRetriever::returnLockedActivityResource($activity_history_resource);
//        $activity = SplickitActivity::getActivity($locked_activity_resource);
//
//        $result = $activity->doIt();
//    }
    
    function createDummyActivity($seconds_in_the_past)
    {
    	$doit_ts = time()-$seconds_in_the_past;
		$info = 'object=DummyObject;method=DummyMethod';
		$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
		$activity_resource = $activity_history_adapter->createActivityReturnActivityResource('ExecuteObjectFunction', $doit_ts, $info, $activity_text);
    	return $activity_resource;
    }
    
    function testGetUnlockedActivities()
    {
    	// create 3 activities ready to send
		for ($i=2;$i<5;$i++)
			$this->createDummyActivity($i);
			
		// create 2 not ready to send
		$this->createDummyActivity(-60);
    	$this->createDummyActivity(-120);
    	
    	$aha = new ActivityHistoryAdapter($mimetypes);
		$unlocked_activity_history_resources = $aha->getAvailableActivityResourcesArray($aha_options);
		$this->assertEquals(3, count($unlocked_activity_history_resources));
    }

    function testGetLockedActivity()
    {
    	// create 3 activities ready to send
		for ($i=2;$i<5;$i++)
			$this->createDummyActivity($i);
			
		// create 2 not ready to send
		$this->createDummyActivity(-60);
    	$this->createDummyActivity(-120);
    	
    	$aha = new ActivityHistoryAdapter($mimetypes);
    	$unlocked_activity_history_resources = $aha->getAvailableActivityResourcesArray($aha_options);
    	$unlocked_activity_history_resource = $unlocked_activity_history_resources[0];
    	$locked_activity_resource = $aha->getLockedActivityResourceFromUnlockedActivityResource($unlocked_activity_history_resource);
    	$this->assertTrue(is_a($locked_activity_resource, 'Resource'));
    	$this->assertEquals('Y', $locked_activity_resource->locked);
    	$this->assertEquals(1,$locked_activity_resource->tries);
    	
    	$unlocked_activity_history_resources2 = $aha->getAvailableActivityResourcesArray($aha_options);
    	$this->assertEquals(2, count($unlocked_activity_history_resources2));
    	
    }
    
    function testGetLockedOnLockedActivity()
    {
    	$activity_resource = $this->createDummyActivity(60);
    	$activity_resource->locked = 'Y';
    	$activity_resource->save();
    	
    	$aha = new ActivityHistoryAdapter($mimetypes);
    	$locked_resource = $aha->getLockedActivityResourceFromUnlockedActivityResource($activity_resource);
    	$this->assertFalse($locked_resource);
    	
    }
    
    function testGetDummyActivity()    
    {
    	$aha = new ActivityHistoryAdapter($mimetypes);
    	$id = $aha->createActivity("BadName", time() - 5, $info, $activity_text);
    	$activity_history_resource = Resource::find($aha,"$id");
    	$activity = SplickitActivity::getActivity($activity_history_resource);
    	$this->assertEquals("DummyActivity", get_class($activity));
    	//return $id;
    }
    
    function testFailFromNoListedActivity()
    {
    	$aha = new ActivityHistoryAdapter($mimetypes);
    	$id = $aha->createActivity("BadName", time() - 5, $info, $activity_text);
    	
    	$activity = $aha->getNextActivityToDo($aha_options);
    	$this->assertEquals($id, $activity->getActivityHistoryId());
    	if ($activity->doit() === false)
			$activity->markActivityFailed();
		else
			$activity->markActivityExecuted();
			
		$activity_history_resource = SplickitController::getResourceFromId($id, "ActivityHistory");
    	$this->assertEquals('F', $activity_history_resource->locked);
    }

    function testGetNewDoItTime()
    {
    	$the_time = time();
    	$original_doit = time()-570;
    	$splickit_activity = new ExecuteObjectFunctionActivity($activity_history_resource);
    	$new_doit_time_stamp = $splickit_activity->getNextDoItTimeForRepeatingActivity($original_doit, 60);
    	$diff = $new_doit_time_stamp - $the_time;
    	myerror_log("the new doit time is $diff seconds in the future");
    	$this->assertTrue($new_doit_time_stamp > $the_time,"new doit time: $new_doit_time_stamp,  should have been greater than the current time:  $the_time");
    	
    }
    
    function testRepeatingActivity()
    {
    	$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
    	
    	$merchant_id = $this->merchant_id;
		$doit_ts = time()-2;
		$info = 'object=GprsPrinterCallInHistoryAdapter;method=hasPrinterCalledInRecently;thefunctiondatastring='.$merchant_id.',300';
		$original_activity_history_resource = $activity_history_adapter->createActivityReturnActivityResource('ExecuteObjectFunction', $doit_ts, $info, $activity_text,3600);
		$original_activity_history_resource->tries = 3;
		$original_activity_history_resource->save();
		$id = $original_activity_history_resource->activity_id;
		
		$activity_history_resource = Resource::find($activity_history_adapter,"$id");
		$activity = $activity_history_adapter->getActivityFromUnlockedActivityHistoryResource($activity_history_resource);
		$this->assertNotNull($activity);
		
		$original_activity_history_resource2 = Resource::find($activity_history_adapter,''.$id);
		$this->assertEquals(4, $original_activity_history_resource2->tries);
		
		$this->assertEquals('ExecuteObjectFunctionActivity', get_class($activity));
		
		$activity->executeThisActivity();
		$new_id = $activity->rescheduled_activity_id;

		$rescheduled_activity_history_resource = Resource::find($activity_history_adapter,''.$new_id);
		$this->assertNotNull($rescheduled_activity_history_resource);
		$new_doit_time_stamp = $doit_ts + 3600;
		$this->assertEquals('N', $rescheduled_activity_history_resource->locked);
		$this->assertEquals(0, $rescheduled_activity_history_resource->tries);
		$this->assertEquals($new_doit_time_stamp, $rescheduled_activity_history_resource->doit_dt_tm);
    }
  
    function testDuplicationTest()
    {
    	$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
    	$sql = "UPDATE Activity_History SET locked = 'X' WHERE 1=1";
    	$activity_history_adapter->_query($sql);
    	
    	$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
    	$merchant_id = $this->merchant_id;
		$doit_ts = time()-30;
		$future_doit_ts = time()+30;
		$info = 'object=GprsPrinterCallInHistoryAdapter;method=hasPrinterCalledInRecently;thefunctiondatastring='.$merchant_id.',300';
		$ready_to_be_executed_activity_history_resource = $activity_history_adapter->createActivityReturnActivityResource('ExecuteObjectFunction', $doit_ts, $info, $activity_text,3600);
		$ready_to_be_executed_activity_history_resource->locked = 'Y';
		$ready_to_be_executed_activity_history_resource->save();
		$dummy_activity = new DummyActivity($activity_history_resource);
		$result = $dummy_activity->hasThisBeenRescheduledAlready($ready_to_be_executed_activity_history_resource);
		$this->assertFalse($result);
    	// now create the duplicate
		$in_the_future_activity_history_resource = $activity_history_adapter->createActivityReturnActivityResource('ExecuteObjectFunction', $future_doit_ts, $info, $activity_text,3600);
    	
		// now do the check for duplicate
		$result2 = $dummy_activity->hasThisBeenRescheduledAlready($ready_to_be_executed_activity_history_resource);
		$this->assertTrue($result2);
		
    }
    
    function testRepeatingActivityNoDuplication()
    {
    	$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
    	$sql = "UPDATE Activity_History SET locked = 'X' WHERE 1=1";
    	$activity_history_adapter->_query($sql);
    	
    	$merchant_id = $this->merchant_id;
		$doit_ts = time()-5;
		$future_do_it = time()+3500;
		$info = 'object=GprsPrinterCallInHistoryAdapter;method=hasPrinterCalledInRecently;thefunctiondatastring='.$merchant_id.',300';
		$original_activity_history_resource = $activity_history_adapter->createActivityReturnActivityResource('ExecuteObjectFunction', $doit_ts, $info, $activity_text,3600);
		
		// now create the duplicate
		$duplicate_activity_history_resource = $activity_history_adapter->createActivityReturnActivityResource('ExecuteObjectFunction', $future_do_it, $info, $activity_text,3600);
		$original_activity_history_resource->tries = 3;
		$original_activity_history_resource->save();
		$id = $original_activity_history_resource->activity_id;
		$activity_history_resource = Resource::find($activity_history_adapter,"$id");
		$activity = $activity_history_adapter->getActivityFromUnlockedActivityHistoryResource($activity_history_resource);
		$this->assertNotNull($activity);
		$original_activity_history_resource2 = Resource::find($activity_history_adapter,''.$id);
		$this->assertEquals(4, $original_activity_history_resource2->tries);
		$this->assertEquals('ExecuteObjectFunctionActivity', get_class($activity));
		
		$activity->executeThisActivity();
		// should nat have rescheduled an activity since one exists already
		$new_id = $activity->rescheduled_activity_id;
		$this->assertNull($new_id);
    }

    function testRepeatingActivityRestartWithDoitTimeDeepInThePast()
    {
   		$merchant_id = $this->merchant_id;
		$doit_ts = time()-5000;
		$info = 'object=GprsPrinterCallInHistoryAdapter;method=hasPrinterCalledInRecently;thefunctiondatastring='.$merchant_id.',300';
		$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);    	
    	$id = $activity_history_adapter->createActivity('ExecuteObjectFunction', $doit_ts, $info, $activity_text,3600);

		//now get the activity and see if it rescheduled
		$activity = $activity_history_adapter->getNextActivityToDo($aha_options);
		$this->assertNotNull($activity);
		$this->assertEquals('ExecuteObjectFunctionActivity', get_class($activity));
		
		$activity->executeThisActivity();
		$new_id = $activity->rescheduled_activity_id;
		
		$rescheduled_activity_history_resource = Resource::find($activity_history_adapter,''.$new_id);
		$this->assertNotNull($rescheduled_activity_history_resource);
		$new_doit_time_stamp = $doit_ts + 7200;
		$this->assertEquals('N', $rescheduled_activity_history_resource->locked);
		$this->assertEquals($new_doit_time_stamp, $rescheduled_activity_history_resource->doit_dt_tm);

    }
   /* 
    function testBuildLetterActivity()
    {
    	// first get activity resource 5586
    	$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
    	$activity_resource = Resource::find($activity_history_adapter,'5586');
    	$activity = new BuildLetterActivity($activity_resource);
    	$activity->doit();
    	
    	// now what?
    	// check to see if email was created.
    	// send email?
    }
    */
        
    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);
        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;
    	$_SERVER['request_time1'] = microtime(true);    	
    	$merchant_resource = createNewTestMerchant();
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;
    	
    }
    
	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
    }
    
    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    ActivityTest::main();
}

?>