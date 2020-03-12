<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class COBTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		$_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'] = "unit testing";
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }
    
    function testDocumentAdapter()
    {
		$whole_file = "Hello World";
		$file_name = "MyFileName";
		$document_adapter = new DocumentAdapter($mimetypes);
		$da_data['file_type'] = 'Text';
		$da_data['process_type'] = 'Daily_Report';
		$da_data['file_name'] = $file_name;
		$da_data['file_size'] = strlen($whole_file);
		$da_data['file_content'] = $whole_file;
		$da_data['file_extension'] = 'txt';
		$da_data['stamp'] = getRawStamp();
		$da_data['created'] = time();
		$da_resource = Resource::factory($document_adapter,$da_data);
		$this->assertTrue($da_resource->save());
		
		$document_resource = Resource::find(new DocumentAdapter($mimetypes),''.$da_resource->id);
		return $da_resource->id;
    }
    
    /**
     * @depends testDocumentAdapter
     */
    function testCreateFileOnFileSystem($id)
    {
    	$file_name = DocumentAdapter::createRecordOnFileSystem($id);
    	//$this->assertEquals("/usr/local/splickit/httpd/htdocs/prod/app2/reportfiles/MyFileName",$file_name);
        $this->assertEquals("./reportfiles/MyFileName",$file_name);
    	$this->assertTrue(file_exists($file_name));
    }
    
    function testCreateRecordOnFileSystemBadId()
    {
    	$this->assertFalse(DocumentAdapter::createRecordOnFileSystem(12345));
    }
		    
    function testCreateCOBactivities()
    {
    	$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
    	$sql = "UPDATE Activity_History SET locked='X' WHERE locked='N' AND activity='CreateCOB'";
    	$activity_history_adapter->_query($sql);
    	COBActivity::createCOBActivitiesForAllOpenActiveMerchants();
    	
    	$data['activity'] = 'CreateCOB';
    	$data['locked'] = 'N';
    	$options[TONIC_FIND_BY_METADATA] = $data;
    	$activity_history_resources = Resource::findAll($activity_history_adapter,'',$options);
    	$this->assertEquals(10, sizeof($activity_history_resources, $mode)); 
    	$activity_history_resource = $activity_history_resources[0];
    	$activity = new COBActivity($activity_history_resource);
    	$result = $activity->doIt();
    	$this->assertTrue($result);
    }
    
    function testGetCOBListForMerchant()
    {
		$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
		$merchant_id = $this->ids['merchant_id-1'];
		$resource = $mmh_adapter->getCOBListForMerchantId($merchant_id);	
		$this->assertEquals(10,sizeof($resource->reports, $mode));
		return $resource;
    }
    
    /**
     * @depends testGetCOBListForMerchant
     */
    
    function testResendCOBmessage($resource)
    {
    	$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
    	$cob_report_array = $resource->reports;
    	$cob_report_resource = $cob_report_array[0];
    	$this->assertNotNull($cob_report_resource);
    	$message_id = $cob_report_resource->map_id;
    	$this->assertTrue($mmh_adapter->resendMessage($message_id));
    	
    	// now get message resource from db and make sure its correctly restaged
    	$restaged_message_resource = Resource::find($mmh_adapter,''.$message_id);
    	$next_message_dt_tm = $restaged_message_resource->next_message_dt_tm;
    	myerror_log("restaged next_message_dt_tm: ".$next_message_dt_tm);
    	myerror_log("time-10: ".(time()-10));
    	$this->assertTrue($next_message_dt_tm > (time()-10));
    	$this->assertEquals("0000-00-00 00:00:00", $restaged_message_resource->sent_dt_tm);
    	$format = substr($restaged_message_resource->message_format, 0,1);
    	if ($format == 'O' || $format == 'G' || $format == 'W')
    		$this->assertEquals("P", $restaged_message_resource->locked);
    	else
    		$this->assertEquals("N", $restaged_message_resource->locked);
    		
    	//now set message to failed
    	$restaged_message_resource->locked = 'F';
    	$restaged_message_resource->save();
    	
    }
    
    function testCreateCOBReportForFaxMerchant()
    {
    	$merchant_id = $this->ids['merchant_id-2'];
    	//create 5 orders from yesterday
    	$yesterday_noon = getTodayTwelveNoonTimeStampDenver()-(60*60*24);
    	logTestUserIn($this->ids['user_id']);
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'the note');
    	for ($z=0;$z<5;$z++)
    	{
    		$tip = rand(100, 1000)/100;
    		$order_data['tip'] = $tip;
    		$order_resource = placeOrderFromOrderData($order_data, $yesterday_noon);
            $this->assertNull($order_resource->error);
    		$total_tips = $total_tips + $order_resource->tip_amt;
    		$total_amt = $total_amt + $order_resource->order_amt;
    		$total_grand_total = $total_grand_total + $order_resource->grand_total;
    		$total_tax = $total_tax + $order_resource->total_tax_amt;
    		$total_promo = $total_promo + (-$order_resource->promo_amt);
    		$yesterday_noon = $yesterday_noon + 600;
    		$order_adapter->updateOrderStatus('E', $order_resource->order_id);
    	}
    	
    	$orders = $order_adapter->getRecords(array("merchant_id"=>$merchant_id), $options); 
    	
    	$cob_activity = new COBActivity($activity_history_resource);
    	    	//create data
    	$todays_date = date('Y-m-d'); 
    	$yesterdays_date = date('Y-m-d',$yesterday_noon);	
    	$cob_activity->createAndStageReport($yesterdays_date, $merchant_id); 
      	
    	// check to see if FCob record was created in DB
    	$fax_controller = new FaxController($mt, $u, $r);
    	$mmha_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
    	$mmha_options[TONIC_FIND_BY_METADATA]['message_format'] = "FCob";
    	//sleep(1);
    	$message_resource = $fax_controller->getNextMessageResourceForSend($mmha_options);
    	$this->assertNotNull($message_resource);
    	$heystack = $message_resource->message_text;
    	myerror_log($heystack);
    	$this->assertTrue(substr_count($heystack, 'Total Amt:    $'.number_format($total_amt,2)) == 1,"should have found total amount of ".number_format($total_amt,2));
    											// Total Amt:    $27.50
    	$this->assertTrue(substr_count($heystack, 'Grand Total:  $'.number_format($total_grand_total,2)) == 1);
    	$this->assertTrue(substr_count($heystack, 'Total Taxes:  $'.number_format($total_tax,2)) == 1);
    	$this->assertTrue(substr_count($heystack, 'FOR DATE: '.$yesterdays_date) == 1);
    	$this->assertTrue(substr_count($heystack, 'Total Tips:   $'.number_format($total_tips,2)) == 1);
    	$this->assertTrue(substr_count($heystack, 'Total Promo:  $'.number_format($total_promo,2)) == 1);

    }
    
    function testCreateCOBReportForChinaIpPrinterMerchant()
    {
    	$ids = $this->ids;
    	$menu_id = $ids['menu_id'];
		$merchant_resource = createNewTestMerchant($menu_id);
		$merchant_id = $merchant_resource->merchant_id;
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_resource->merchant_id;
    	$options[TONIC_FIND_BY_METADATA]['message_format'] = 'E';
    	$email_message_resource = Resource::find(new MerchantMessageMapAdapter($mimetypes),null,$options);
    	$email_message_resource->message_format = 'HUA';
    	$email_message_resource->deliverty_addr = 'IP Printer';
    	$email_message_resource->save();
    	//create 5 orders from yesterday
    	$yesterday_noon = getTodayTwelveNoonTimeStampDenver()-(60*60*24);
    	logTestUserIn($this->ids['user_id']);
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'the note');
    	for ($z=0;$z<5;$z++)
    	{
    		$tip = rand(100, 1000)/100;
    		$order_data['tip'] = $tip;
    		$order_resource = placeOrderFromOrderData($order_data, $yesterday_noon);
    		$total_tips = $total_tips + $order_resource->tip_amt;
    		$total_amt = $total_amt + $order_resource->order_amt;
    		$total_grand_total = $total_grand_total + $order_resource->grand_total;
    		$total_tax = $total_tax + $order_resource->total_tax_amt;
    		$total_promo = $total_promo + (-$order_resource->promo_amt);
    		$yesterday_noon = $yesterday_noon + 600;
    		
    		$order_adapter->updateOrderStatus('E', $order_resource->order_id);
    	}
    	$sql = "UPDATE Merchant_Message_History SET locked = 'F' WHERE merchant_id = $merchant_id AND message_format = 'HUA'";
    	$order_adapter->_query($sql);
    	
    	$orders = $order_adapter->getRecords(array("merchant_id"=>$merchant_id), $options); 
    	
    	$cob_activity = new COBActivity($activity_history_resource);
    	    	//create data
    	$todays_date = date('Y-m-d'); 
    	$yesterdays_date = date('Y-m-d',$yesterday_noon);	
    	$cob_activity->createAndStageReport($yesterdays_date, $merchant_id); 
      	
    	// check to see if HCob record was created in DB
    	$cip_controller = new ChinaIPPrinterController($mt, $u, $r);
    	$mmha_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
    	$mmha_options[TONIC_FIND_BY_METADATA]['message_format'] = "HCob";
    	//sleep(1);
    	
    	$message_resource = $cip_controller->pullNextMessageResourceByMerchant($merchant_id);
    	$this->assertTrue(is_a($message_resource, 'Resource'),"Should have found an Hcob message");
    	$heystack = cleanUpDoubleSpacesCRLFTFromString($message_resource->message_text);
    	myerror_log($heystack);
    	$expected_result = "#".$merchant_id."*1*".$cob_activity->china_printer_cob_id."**:: ::FOR DATE: ".$cob_activity->for_date.":: ::ID: ".$merchant_resource->numeric_id."::Name: Unit Test Merchant::Addr: 1505 Arapaho Ave::City: boulder::Tax Rate: Tax Group: 1 Rate: 10.0000:: ::Total Amt: ".number_format($total_amt,2)."::Total Items: 5::Total Taxes: ".number_format($total_tax,2)."::Total Tips: ".number_format($total_tips,2)."::Total Deliv: 0.00::Total Promo: 0.00::Grand Total: ".number_format($total_grand_total,2).":::: ::If any of the above information is not correct please call us at::1.888.775.4254::;*#";
    	$this->assertEquals($expected_result, $heystack);
    }

    function testCreateCOBSingleMerchantGPRS()
    {
    	$yesterday_noon = getTodayTwelveNoonTimeStampDenver()-(60*60*24);
		$merchant_id = $this->ids['merchant_id-5'];
		$merchant_resource = SplickitController::getResourceFromId($merchant_id, "Merchant");
    	logTestUserIn($this->ids['user_id']);
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'the note');
    	$order_resource = placeOrderFromOrderData($order_data, $yesterday_noon);
    	$order_adapter->updateOrderStatus('E', $order_resource->order_id);
    	$cob_activity = new COBActivity(NULL);
		$date_yesterday = date('Y-m-d',$yesterday_noon);
    	//create data
    	$todays_date = date('Y-m-d');   	
    	$cob_activity->createAndStageReport($date_yesterday,$merchant_id); 
    	
    	// check to see if COB record was created in DB
    	$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
    	$mmh_data['merchant_id'] = $merchant_id;
    	$mmh_data['message_format'] = 'GCob';
    	$mmh_data['created'] = array(">"=>time()-10);
    	$mmh_options[TONIC_FIND_BY_METADATA] = $mmh_data;
    	$mmh_resource = Resource::find($mmh_adapter,null,$mmh_options);
    	$cob_text = $mmh_resource->message_text;
    	myerror_log("cob test text: ".$cob_text);
    	
    	// get body of message
    	$index = strpos($cob_text, "FOR DATE");
    	$body = substr($cob_text, $index);
    	
    	$merchant_numeric_id = $merchant_resource->numeric_id;
    	$total_amt = $order_resource->order_amt;
    	$total_tips = number_format($order_resource->tip_amt,2);
    	$grand_total = $order_resource->grand_total;
        $total_tax = $order_resource->total_tax_amt;
    	//$expected_body = "FOR DATE: 2012-08-15:: ::ID: 96305055::Name: Snarf's::Addr: 2128 Pearl Street::City: Boulder::Tax Rate: Tax Group: 1 Rate: 8.3600:: ::Total Amt: 21.75::Total Items: 3::Total Taxes: 1.83::Total Tips: 23.43::Total Promo: 0.00::Grand Total: 47.01:::: ::If any of the above information is not correct please call us at::1.888.775.4254::";
    	$expected_body = "FOR DATE: $date_yesterday:: ::ID: $merchant_numeric_id::Name: Unit Test Merchant::Addr: 1505 Arapaho Ave::City: boulder::Tax Rate: Tax Group: 1 Rate: 10.0000:: ::Total Amt: $total_amt::Total Items: 1::Total Taxes: $total_tax::Total Tips: $total_tips::Total Deliv: 0.00::Total Promo: 0.00::Grand Total: $grand_total:::: ::If any of the above information is not correct please call us at::1.888.775.4254::";
    	//FOR DATE: 2013-10-06:: ::ID: 86890657::Name: Unit Test Merchant::Addr: 1505 Arapaho Ave::City: boulder::There were no orders today:: ::If any of the above information is not correct please call us at::1.888.775.4254::
    	$this->assertNotNull($mmh_resource);
    	$this->assertEquals($expected_body, cleanUpDoubleSpacesCRLFTFromString($body));
    	
    	// do a daily report now
		$dr_activity = new DailyReportActivity($activity_history_resource);
    	$dr_activity->createAndStageReport($date_yesterday, $merchant_id); 
      	
    	// check to see if ED record was created in DB
    	$email_controller = new EmailController($mt, $u, $r);
    	$mmha_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
    	$mmha_options[TONIC_FIND_BY_METADATA]['message_format'] = "ED";
    	$message_resource = $email_controller->getNextMessageResourceForSend($mmha_options);
    	$resource = $email_controller->populateMessageData($message_resource);
    	$message_data = $resource->info_data;
    	
    	$this->assertEquals($date_yesterday, $message_data['for_date']);
    	$this->assertEquals('1', $message_data['count']);
    	$this->assertEquals($grand_total, $message_data['grand_total']);
    	$this->assertEquals($total_tax, $message_data['tax_total']);
    	$this->assertEquals($total_tips, $message_data['tip_total']);
    	//$this->assertEquals('/Users/radamnyc/code/smaw/reportfiles/2012-08-15_1083_dailyreport2.csv',$message_data['attachment']);
    	$this->assertEquals($date_yesterday.'_'.$merchant_id.'_dailyreport2.csv', $message_data['file_names']);
    	$this->assertEquals('Daily_Report', $message_data['process']);
    	
    	// now check if file is in the db
    	$document_adapter = new DocumentAdapter($mimetypes);
    	$document_resource = Resource::find($document_adapter,''.$message_data['document_ids']);
    	$this->assertNotNull($document_resource);
    	$content = $document_resource->file_content;
    	myerror_log($content);
    	$content_length = strlen($content);
    	$this->assertEquals($document_resource->file_size, $content_length);    	
    }
    
    function testCreateCOBSingleMerchantFAXnoOrders()
    {
    	// get the fax merchant
    	$merchant_id = $this->ids['merchant_id-2'];
    	$merchant_resource = SplickitController::getResourceFromId($merchant_id, "Merchant");
    	$merchant_numeric_id = $merchant_resource->numeric_id;
		
    	//create data
    	$cob_activity = new COBActivity(NULL);
    	$todays_date = date('Y-m-d');
    	$activity_data['merchant_id'] = $merchant_id;
    	// some random day
    	$activity_data['local_open_dt_tm'] = '2012-08-15 04:00:00';
    	$activity_data['local_close_dt_tm'] = '2012-08-15 23:00:00';
    	$activity_data['local_open_date'] = '2012-08-15';
    	$cob_activity->setData($activity_data);
    	
    	$cob_activity->doit();
    	
    	// check to see if COB record was created in DB
    	$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
    	$mmh_data['merchant_id'] = $merchant_id;
    	$mmh_data['message_format'] = 'FCob';
    	$mmh_data['locked'] = 'N';
    	$mmh_data['created'] = array(">"=>time()-10);
    	$mmh_options[TONIC_FIND_BY_METADATA] = $mmh_data;
    	$mmh_resource = Resource::find($mmh_adapter,null,$mmh_options);
    	$cob_text = $mmh_resource->message_text;
    	myerror_log("cob test text: ".$cob_text);
    	   	
    	$expected_body = "Splickit Close Of Business Report\n\nFOR DATE: 2012-08-15\n\nID: $merchant_numeric_id\n\nName: Unit Test Merchant\nAddr: 1505 Arapaho Ave\nCity: boulder\n\nThere were no orders today\n\nIf any of the above information is not correct please call us at: 1.888.775.4254";
    	$this->assertNotNull($mmh_resource);
    	$this->assertEquals(cleanUpDoubleSpacesCRLFTFromString($expected_body), cleanUpDoubleSpacesCRLFTFromString($cob_text));
    	
    }
    
    /*********  the daily report tests  **********/
    
    function testCreateDailyReportFromDate()
    {
    	$yesterday_noon = getTodayTwelveNoonTimeStampDenver()-(60*60*24);
    	$date_yesterday = date('Y-m-d',$yesterday_noon);
    	$the_starting_date = "2012-10-15"; //monday
    	$merchant_id = $this->ids['merchant_id-1'];
    	// first get local opening and closing times
    	
    	$hour_adapter = new HourAdapter($mimetypes);
		$local_open_close_data = $hour_adapter->getLocalOpenAndCloseDtTmForDate($merchant_id, $date_yesterday, 'R');

        $order_adapter = new OrderAdapter($m);
        $orders = $order_adapter->getRecords(array("merchant_id"=>$merchant_id), $options);

		$dr_activity = new DailyReportActivity($activity_history_resource);
		$dr_activity->setData($local_open_close_data);
    	$dr_activity->doit();
    	
    	// check to see if ED record was created in DB
    	$email_controller = new EmailController($mt, $u, $r);
    	$mmha_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
    	$mmha_options[TONIC_FIND_BY_METADATA]['message_format'] = "ED";
    	$message_resource = $email_controller->getNextMessageResourceForSend($mmha_options);
    	$resource = $email_controller->populateMessageData($message_resource);
    	$message_info_data = $resource->info_data;
    	$this->assertEquals("***TEST*** Daily Reports ".$merchant_id, $message_info_data['subject']);
    	$this->assertEquals($date_yesterday,$message_info_data['for_date']);
    	$this->assertEquals("support@dummy.com",$message_info_data['from']);
    	$this->assertEquals(1, $message_info_data['count']);

    	$expected_message_data = "subject=***TEST*** Daily Reports 104905;for_date=2013-10-06;from=support@dummy.com;count=1;grand_total=10.67;tax_total=0.00;promo_total=0.00;tip_total=5.17;file_names=2013-10-06_104905_dailyreport2.csv;process=Daily_Report;document_ids=2570";
    	
    }

    static function setUpBeforeClass()
    {
    	set_time_limit(30);
    	$merchant_adapter = new MerchantAdapter($mimetypes);
    	$sql = "UPDATE Merchant SET brand_id = 299 WHERE brand_id = 300";
    	$merchant_adapter->_query($sql);
    	$_SERVER['HTTP_NO_CC_CALL'] = 'true';
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

    	//create 10 merchants
    	for ($i=1;$i<11;$i++)
    	{
	    	$merchant_resource = createNewTestMerchant($menu_id);
	    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
	    	$options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_resource->merchant_id;
	    	$options[TONIC_FIND_BY_METADATA]['message_format'] = 'E';
	    	$email_message_resource = Resource::find(new MerchantMessageMapAdapter($mimetypes),null,$options);
	    	if ($i == 2)
	    	{
		    	$email_message_resource->message_format = 'FUA';
		    	$email_message_resource->deliverty_addr = '1234567890';
	    	} else {
		    	$email_message_resource->message_format = 'GUA';
		    	$email_message_resource->deliverty_addr = 'gprs';
	    	}
	    	$email_message_resource->save();
	    	//$map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_id,"message_format"=>'FUW',"delivery_addr"=>"1234567890","message_type"=>"O"));
	    	
	    	$ids['merchant_id-'.$i] = $merchant_resource->merchant_id;
	    	$merchants[$i] = $merchant_resource;
    	}
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;
    	logTestUserIn($ids['user_id']);
    	
    	//create some history for merchant1
    	$cob_activity = new COBActivity($activity_history_resource);
    	$merchant_id = $ids['merchant_id-1'];
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'sum dum note');
    	$starting_time_stamp = getTodayTwelveNoonTimeStampDenver();
    	// place one order a day for previous 30 days and create COB report for that day
    	for ($j=0;$j<10;$j++)
    	{
    		$time_stamp = $starting_time_stamp - ($j*86400);
    		myerror_log("placing order on day: ".date('Y-m-d',$time_stamp));
    		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    		$order_adapter->updateOrderStatus("E", $order_resource->order_id);
    		$date_string = date('Y-m-d H:i:s',$time_stamp);
    		$response = $cob_activity->createAndStageReport($date_string, $merchant_id);
    	}

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
    COBTest::main();
}
    
?>