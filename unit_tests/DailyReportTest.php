<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/functions.inc';

//class DailyReportTest extends PHPUnit_Framework_TestCase
class DUMMY
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
	   
    function testGetDailyReportListForMerchant()
    {
		$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
		$merchant_id = 1086;
		$resource = $mmh_adapter->getDailyReportListForMerchantId($merchant_id);	
		$this->assertTrue(sizeof($resource->reports, $mode) > 0);
		return $resource;
    }
    
    /**
     * @depends testGetDailyReportListForMerchant
     */
    
    function testResendDailyReportMessage($resource)
    {
    	$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
    	$daily_report_array = $resource->reports;
    	$daily_report_resource = $daily_report_array[0];
    	$this->assertNotNull($daily_report_resource);
    	$message_id = $daily_report_resource->map_id;
    	$this->assertTrue($mmh_adapter->resendMessage($message_id));
    	
    	// now get message resource from db and make sure its correctly restaged
    	$restaged_message_resource = Resource::find($mmh_adapter,''.$message_id);
    	$next_message_dt_tm = $restaged_message_resource->next_message_dt_tm;
    	myerror_log("restaged next_message_dt_tm: ".$next_message_dt_tm);
    	myerror_log("time-10: ".(time()-10));
    	$this->assertTrue($next_message_dt_tm > (time()-10));
    	$this->assertEquals("0000-00-00 00:00:00", $restaged_message_resource->sent_dt_tm);
    	$this->assertEquals("N", $restaged_message_resource->locked);
    		
    	//now set message to failed
    	$restaged_message_resource->locked = 'F';
    	$restaged_message_resource->save();
    	
    }
    
    function testCreateDailyReprtSingleMerchant()
    {
    	// this is now done in teh COBTest since the data was there.
    	$this->assertTrue(true);
    }

    function testCreateDailyReportFromDate()
    {
    	
    	$the_starting_date = "2012-10-15"; //monday
    	$merchant_id = 1054;
    	// first get local opening and closing times
    	
    	$hour_adapter = new HourAdapter($mimetypes);
		$local_open_close_data = $hour_adapter->getLocalOpenAndCloseDtTmForDate($merchant_id, $the_starting_date, 'R');

		$dr_activity = new DailyReportActivity($activity_history_resource);
		$dr_activity->setData($local_open_close_data);
    	$dr_activity->doit();
    	
    	// check to see if ED record was created in DB
    	$email_controller = new EmailController($mt, $u, $r);
    	$mmha_options[TONIC_FIND_BY_METADATA]['merchant_id'] = 1054;
    	sleep(1);
    	$message_resource = $email_controller->getNextMessageResourceForSend($mmha_options);
    	$resource = $email_controller->populateMessageData($message_resource);
    	$message_data = $resource->info_data;

    }
    
	function testCreateDailyMultipleMerchants()
    {
    	$dr_activity = new DailyReportActivity($activity_history_resource);
		
    	//create data
    	$todays_date = date('Y-m-d');
    	$activity_data['merchant_id'] = '1083,1105,1103,1086';
//    	$activity_data['local_open_dt_tm'] = $todays_date.' 04:00:00';
 //   	$activity_data['local_close_dt_tm'] = $todays_date.' 23:00:00';
    	$activity_data['local_open_dt_tm'] = '2012-08-15 04:00:00';
    	$activity_data['local_close_dt_tm'] = '2012-08-15 23:00:00';
    	$activity_data['local_open_date'] = '2012-08-15';
    	$dr_activity->setData($activity_data);
    	
    	$dr_activity->doit();
    	
    	// check to see if Report records were created in DB
    	$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
    	$mmh_data['message_format'] = 'ED';
    	$test_time = time()-20;
    	$test_date = date('Y-m-d H:i:s',time()-20);
    	//$mmh_data['next_message_dt_tm'] = array(">"=>time()-20);
    	$mmh_data['next_message_dt_tm'] = array(">"=>$test_date);
    	$mmh_data['locked'] = 'N';
    	$mmh_options[TONIC_FIND_BY_METADATA] = $mmh_data;
    	$mmh_resources = Resource::findAll($mmh_adapter,null,$mmh_options);
    	// there should have been 3 records created
    	$this->assertNotNull($mmh_resources);
    	$this->assertEquals(3, sizeof($mmh_resources));
    	foreach ($mmh_resources as $mmh_resource)
    	{
    		$message_data = $mmh_adapter->getMesageInfoData($mmh_resource);
    		if ($mmh_resource->merchant_id == 1083) {
		    	// now check if file is in the db
		    	$document_adapter = new DocumentAdapter($mimetypes);
		    	$document_resource = Resource::find($document_adapter,''.$message_data['document_ids']);
		    	$this->assertNotNull($document_resource);
		    	$content = $document_resource->file_content;
		    	myerror_log($content);
		    	$content_length = strlen($content);
		    	$this->assertEquals($document_resource->file_size, $content_length);
		    	//$document_adapter->delete("".$message_data['document_id']);
		    	$document_resource = null;
       		} else {
    			// verify that there is no data
    			$this->assertEquals("0", $message_data['count']);
    		}
    		
    		if ($document_id = $message_data['document_id'])
    		{
    			
    		}
    		$mmh_resource->locked = 'T';
    		$mmh_resource->save();
    	}	
   		foreach(glob('reportfiles/2012-08-15_*') as $file){
     		unlink($file);
		}
    	
    }
    
    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	$_SERVER['request_time1'] = microtime(true);    	
		
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

if (false && !defined('PHPUnit_MAIN_METHOD')) {
    // DailyReportTest::main();
}
    
?>