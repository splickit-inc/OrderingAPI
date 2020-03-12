<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class FaxControllerTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $user;
	var $merchant;
	var $ids;
	
	function setUp()
	{
		// we dont want to call to inspirepay 
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];

		$this->ids = $_SERVER['unit_test_ids'];
		$this->user = logTestUserIn($_SERVER['unit_test_ids']['user_id']);
    	$this->merchant_id = $_SERVER['unit_test_ids']['merchant_id'];
    	$this->merchant = SplickitController::getResourceFromId($this->merchant_id, 'Merchant');
		setContext('com.splickit.worldhq');
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->user);
		unset($this->ids);
		unset($this->merchant);
    }
    
    /**
     * @expectedException NoMatchingFaxServiceRegisteredException
     */
    function testFaxServiceFactory()
    {
    	$faxage_fax_service = SplickitFaxService::faxServiceFactory('Faxage');
    	$this->assertTrue(is_a($faxage_fax_service, 'FaxageFaxService'));
    	
    	$phaxio_fax_service = SplickitFaxService::faxServiceFactory('Phaxio');
    	$this->assertTrue(is_a($phaxio_fax_service, 'PhaxioFaxService'));
    	
    	$sum_dum_service = SplickitFaxService::faxServiceFactory('SomDum');
    	
    }
    
    function testCallBackUrlDataExtraction()
    {
    	$callback_url = "http://localhost:8888/app2/messagemanager/81t11eb576oz/map_id.295556/service.Phaxio/fax/callback.txt?map_id=295556&service=Phaxio&log_level=5";
    	$url_data = SplickitFaxService::extractMapIdAndServiceFromCallBackUrl($callback_url);
    	$this->assertEquals("295556", $url_data['map_id']);
    	$this->assertEquals("Phaxio", $url_data['service']); 
    }
    
    function testPhaxioCallBackSuccess()
    {
    	$data['fax'] = '{"id":1624294,"num_pages":1,"cost":7,"direction":"sent","status":"success","is_test":true,"requested_at":1381336510,"completed_at":1381336546,"recipients":[{"number":"17204384799","status":"success","bitrate":"14400","resolution":"7700","completed_at":1381336547}]}';
    	$data['direction'] = "sent";
    	$data['success'] = true;
		$phaxio_service = new PhaxioFaxService($message_resource);
		$result = $phaxio_service->getCallBackResult($data);
		$this->assertTrue($result);    	
    }
    
    function testPhaxioCallBackFailure()
    {
    	$data['fax'] = '{"id":1624294,"num_pages":1,"cost":7,"direction":"sent","status":"failure","is_test":true,"requested_at":1381336510,"completed_at":1381336546,"recipients":[{"number":"17204384799","status":"success","bitrate":"14400","resolution":"7700","completed_at":1381336547}]}';
    	$data['direction'] = "sent";
    	$data['success'] = false;
		$phaxio_service = new PhaxioFaxService($message_resource);
		$result = $phaxio_service->getCallBackResult($data);
		$this->assertFalse($result);
		$this->assertEquals('Some Phaxio Failure', $phaxio_service->getCallbackFailReason());    	
    }
    
    function testFaxageCallBackSuccess()
    {
    	$body = "jobid=115893796&commid=788038&destname=adam&destnum=(208)743-5239&shortstatus=success&longstatus=Success&sendtime=2013-11-14 15:18:20&completetime=2013-11-14 15:19:13&xmittime=00:00:31&pagecount=1&xmitpages=1";
    	$request = new Request();
    	$request->mimetype = 'application/x-www-form-urlencoded';
    	$request->body = $body;
    	$request->_parseRequestBody();
    	$data = $request->data;
    	$faxage_service = new FaxageFaxService($message_resource);
    	$result = $faxage_service->getCallBackResult($data);
    	$this->assertTrue($result);
    }
    
    function testFaxageCallBackFailure()
    {
 		$body = "jobid=115797962&commid=798511&destname=adam&destnum=(419)725-2739&shortstatus=failure&longstatus=Totally messed up stuff&sendtime=2013-11-14 01:00:07&completetime=2013-11-14 01:07:04&xmittime=00:01:42&pagecount=1&xmitpages=0";
    	$request = new Request();
    	$request->mimetype = 'application/x-www-form-urlencoded';
    	$request->body = $body;
    	$request->_parseRequestBody();
    	$data = $request->data;
    	$faxage_service = new FaxageFaxService($message_resource);
    	$result = $faxage_service->getCallBackResult($data);
 		$this->assertFalse($result);
 		$this->assertEquals('Totally messed up stuff', $faxage_service->getCallbackFailReason());
    }

    function testFaxWithPhaxio()
    {
    	setProperty('fax_service_list', 'Phaxio,Faxage');
    	$order_adapter = new OrderAdapter($mimetypes);
    	// first get rid of open orders for 102237
    	
    	$merchant_id = $this->merchant->merchant_id;
    	$alpha_numeric = $this->merchant->alphanumeric_id;
    	$sql = "UPDATE Merchant_Message_History SET locked = 'F' WHERE merchant_id = $merchant_id AND locked = 'N'";
    	$order_adapter->_query($sql);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','dum note',1);
        $order_resource = placeOrderFromOrderData($order_data);
		$this->assertNotNull($order_resource);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000,"Bad order id of: ".$order_resource->order_id);
		$order_id = $order_resource->order_id;
		
		$message_data['order_id'] = $order_id;
		$message_data['message_format'] = "FUW";
		$options[TONIC_FIND_BY_METADATA] = 	$message_data;
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		$message_resource = Resource::find($mmha,'',$options);
		$map_id = $message_resource->map_id;
		$this->assertNotNull($message_resource);
		$fax_controller = new FaxController($mt, $u, $r,5);
		$fax_controller->sendThisMessage($message_resource);
		$this->assertEquals('Phaxio',$fax_controller->service_used);
		
		// now verify that the order was left as O.
		$order_resource = Resource::find($order_adapter,''.$order_id);
		$this->assertEquals("O", $order_resource->status);
		
		// get call back url
		$url = $fax_controller->callback_url;
		$this->assertEquals("http://localhost:8888/app2/messagemanager/$alpha_numeric/map_id.$map_id/service.Phaxio/fax/callback.txt?map_id=$map_id&service=Phaxio&log_level=5", $url);
		$call_back_request = new Request();
		$call_back_data['map_id'] = $map_id;
		$call_back_data['service'] = "Phaxio";
		$call_back_data['fax'] = '{"id":1624294,"num_pages":1,"cost":7,"direction":"sent","status":"success","requested_at":1381336510,"completed_at":1381336546,"recipients":[{"number":"17204384799","status":"success","bitrate":"14400","resolution":"7700","completed_at":1381336547}]}';
		$call_back_request->data = $call_back_data;
		$fax_controller2 = new FaxController($mt, $u, $call_back_request,5);
		$fax_controller2->callback("$alpha_numeric");
		$order_resource2 = Resource::find($order_adapter,''.$order_id);
		$this->assertEquals("E", $order_resource2->status);
		
		$message_resource2 = Resource::find($mmha,''.$map_id);
		$this->assertEquals('V',$message_resource2->viewed);
    }
    
    function testFaxWithFaxage()
    {
    	setProperty('fax_service_list', 'Faxage,Phaxio');
    	$order_adapter = new OrderAdapter($mimetypes);
    	// first get rid of open orders for 102237
    	$merchant_id = $this->merchant->merchant_id;
    	$alpha_numeric = $this->merchant->alphanumeric_id;
    	$sql = "UPDATE Merchant_Message_History SET locked = 'F' WHERE merchant_id = $merchant_id AND locked = 'N'";
    	$order_adapter->_query($sql);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','dum note',1);
        $order_resource = placeOrderFromOrderData($order_data);
        $this->assertNotNull($order_resource);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000,"Bad order id of: ".$order_resource->order_id);
		$order_id = $order_resource->order_id;
		
		//$sql = "SELECT * FROM Merchant_Message_History WHERE message_format LIKE 'F%' AND order_id = $order_id";
		$message_data['order_id'] = $order_id;
		$message_data['message_format'] = array("LIKE"=>"F%");
		$options[TONIC_FIND_BY_METADATA] = 	$message_data;
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		$message_resource = Resource::find($mmha,'',$options);
		$map_id = $message_resource->map_id;
		$this->assertNotNull($message_resource);
		$fax_controller = new FaxController($mt, $u, $r,5);
		$fax_controller->sendThisMessage($message_resource);
		$this->assertEquals('Faxage',$fax_controller->service_used);
		
		//veirfy the order is still open
		$order_resource = Resource::find($order_adapter,''.$order_id);
		$this->assertEquals('O', $order_resource->status);
		
		$url = $fax_controller->callback_url;
		$this->assertEquals("http://localhost:8888/app2/messagemanager/$alpha_numeric/map_id.$map_id/service.Faxage/fax/callback.txt?map_id=$map_id&service=Faxage&log_level=5", $url);
		
		// similute clipping by faxage
		$url = "http://localhost:8888/app2/messagemanager/$alpha_numeric/map_id.$map_id/service.Faxage/fax/callback.txt";
		$call_back_request = new Request();
		$call_back_request->url = $url;
		$call_back_data['jobid'] = "1234567890";
		$call_back_data['shortstatus'] = "success";
		$call_back_request->data = $call_back_data;
		$fax_controller2 = new FaxController($mt, $u, $call_back_request,5);
		$fax_controller2->callback("$alpha_numeric");
		$order_resource2 = Resource::find($order_adapter,''.$order_id);
		$this->assertEquals("E", $order_resource2->status);
		
		$message_resource2 = Resource::find($mmha,''.$map_id);
		$this->assertEquals('V',$message_resource2->viewed);
		
    }
    
    function testCallBackOnFCob()
    {
    	$merchant_resource = createNewTestMerchant();
    	$merchant_id = $merchant_resource->merchant_id;
    	$alpha_numeric = $merchant_resource->alphanumeric_id;
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$cob_message_resource = $mmha->createMessageReturnResource($merchant_id, $order_id, 'FCob', '1234567890', time()-50, 'I', $info, 'this is the COB','S',time()-5,1);
    	$cob_message_resource->viewed = 'N';
    	$cob_message_resource->save();
		$map_id = $cob_message_resource->map_id;
    	$url = "http://localhost:8888/app2/messagemanager/$alpha_numeric/map_id.$map_id/service.Faxage/fax/callback.txt";
		$call_back_request = new Request();
		$call_back_request->url = $url;
		$call_back_data['jobid'] = "1234567890";
		$call_back_data['shortstatus'] = "success";
		$call_back_request->data = $call_back_data;
		$fax_controller2 = new FaxController($mt, $u, $call_back_request,5);
		$this->assertTrue($fax_controller2->callback("$alpha_numeric"));

		$message_resource2 = Resource::find($mmha,''.$map_id);
		$this->assertEquals('V',$message_resource2->viewed);
    	
    }
    
	function testFailoverToFaxage()
	{
		setProperty('fax_service_list', 'Phaxio,Faxage');
		$_SERVER['PHAXIO_FORCE_FAIL'] = 'true';
    	$merchant_id = $this->merchant->merchant_id;
    	$sql = "UPDATE Merchant_Message_History SET locked = 'F' WHERE merchant_id = $merchant_id AND locked = 'N'";
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_adapter->_query($sql);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','dum note',1);
        $order_resource = placeOrderFromOrderData($order_data);

        $this->assertNotNull($order_resource);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000,"Bad order id of: ".$order_resource->order_id);
		$order_id = $order_resource->order_id;
		
		//$sql = "SELECT * FROM Merchant_Message_History WHERE message_format LIKE 'F%' AND order_id = $order_id";
		$message_data['order_id'] = $order_id;
		$message_data['message_format'] = array("LIKE"=>"F%");
		$options[TONIC_FIND_BY_METADATA] = 	$message_data;
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		$message_resource = Resource::find($mmha,'',$options);
		$this->assertNotNull($message_resource);
		$fax_controller = new FaxController($mt, $u, $r,5);
		$fax_controller->sendThisMessage($message_resource);
		$this->assertEquals('Faxage',$fax_controller->service_used);
		
		//veirfy the order is still open
		$order_resource = Resource::find($order_adapter,''.$order_id);
		$this->assertEquals('O', $order_resource->status);
		
		// now verify that the FC message was created
/*		$message_data['message_format'] = 'FC';
		$options[TONIC_FIND_BY_METADATA] = 	$message_data;
		$message_resource2 = Resource::find($mmha,'',$options);
		$this->assertNotNull($message_resource2);
*/			
	}
    
/*    function testTimeOutResponse()
    {
		if ($curl = curl_init("http://localhost:8888/smaw/phone/testtimeout?temp=asdf"))
		{		
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_VERBOSE, 0);
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			curl_setopt($curl, CURLOPT_SSLVERSION, 3);
			if (isLaptop())
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_HEADER, 0);
			curl_setopt($curl, CURLOPT_TIMEOUT, 1);
			//$headers = array('Content-Type: multipart/form-data', 'Accept-Charset: UTF-8');
			//curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			$success = false;
			if ($result = curl_exec($curl))
			{
				myerror_log("success"); // shouldn't have gotten here	
			} else {
				// now what
				$error = curl_error($curl);
				$error_number = curl_errno($curl);
				myerror_log("failure");
			}
			curl_close($curl);
		}
		$this->assertEquals("Operation timed out after 1000 milliseconds with 0 bytes received", $error);
    }
*/    
    function testFaxWithPhaxioTimeout()
    {
    	setProperty('fax_service_list', 'Phaxio,Faxage');
    	$order_adapter = new OrderAdapter($mimetypes);
    	// first get rid of open orders for 102237
    	
    	$merchant_id = $this->merchant->merchant_id;
    	$sql = "UPDATE Merchant_Message_History SET locked = 'F' WHERE merchant_id = $merchant_id AND locked = 'N'";
    	$order_adapter->_query($sql);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','dum note',1);
        $order_resource = placeOrderFromOrderData($order_data);

        $this->assertNotNull($order_resource);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000,"Bad order id of: ".$order_resource->order_id);
		$order_id = $order_resource->order_id;
		
		$message_data['order_id'] = $order_id;
		$message_data['message_format'] = "FUW";
		$options[TONIC_FIND_BY_METADATA] = 	$message_data;
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		$message_resource = Resource::find($mmha,'',$options);
		$map_id = $message_resource->map_id;
		$this->assertNotNull($message_resource);
		$fax_controller = new FaxController($mt, $u, $r,5);
		
		// this will force the timeout url in the phaxio send method
		$_SERVER['TEST_TIMEOUT'] = 'true';
		
		$send_result = $fax_controller->sendThisMessage($message_resource);
		$this->assertTrue($send_result);
		
		// now check to see if the info got updated with a job id meaning it was sent with faxage due to the timeout
		$message_resource_updated = Resource::find($mmha,"".$map_id);
		$info = $message_resource_updated->info;
		$s = explode("=", $info);
		$this->assertEquals("JOBID", $s[0]);		
    }
    
    function testFaxWithPhaxioTimeoutAndNoOtherServices()
    {
    	setProperty('fax_service_list', 'Phaxio');
    	$order_adapter = new OrderAdapter($mimetypes);
    	// first get rid of open orders for 102237
    	
    	$merchant_id = $this->merchant->merchant_id;
    	$sql = "UPDATE Merchant_Message_History SET locked = 'F' WHERE merchant_id = $merchant_id AND locked = 'N'";
    	$order_adapter->_query($sql);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','dum note',1);
        $order_resource = placeOrderFromOrderData($order_data);
        $this->assertNotNull($order_resource);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000,"Bad order id of: ".$order_resource->order_id);
		$order_id = $order_resource->order_id;
		
		$message_data['order_id'] = $order_id;
		$message_data['message_format'] = "FUW";
		$options[TONIC_FIND_BY_METADATA] = 	$message_data;
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		$message_resource = Resource::find($mmha,'',$options);
		$locked_message_resource = $mmha->getLockedMessageResourceForSending($message_resource);
		$map_id = $message_resource->map_id;
		$this->assertNotNull($locked_message_resource);
		$fax_controller = new FaxController($mt, $u, $r,5);
		
		// this will force the timeout url in the phaxio send method
		$_SERVER['TEST_TIMEOUT'] = 'true';
		
		$send_result = $fax_controller->sendThisMessage($locked_message_resource);
		$this->assertTrue($send_result);
		$this->assertEquals('Timeout', $fax_controller->getErrorMessage());
		$this->assertEquals(105, $fax_controller->getErrorCode());
		
		//now get the message again
		$message_resource2 = Resource::find($mmha, "$map_id", $options);
		$locked_message_resource2 = $mmha->getLockedMessageResourceForSending($message_resource2);
		$this->assertNotNull($locked_message_resource2);
		
		$send_result2 = $fax_controller->sendThisMessage($locked_message_resource2);
		$this->assertTrue($send_result2);
		$this->assertEquals('Timeout', $fax_controller->getErrorMessage());
		$this->assertEquals(105, $fax_controller->getErrorCode());
		
		$message_resource3 = Resource::find($mmha, "$map_id", $options);
		$locked_message_resource3 = $mmha->getLockedMessageResourceForSending($message_resource3);
		$this->assertNotNull($locked_message_resource3);
		
		$send_result3 = $fax_controller->sendThisMessage($locked_message_resource3);
		$this->assertTrue($send_result3);
		$this->assertEquals('Timeout', $fax_controller->getErrorMessage());
		$this->assertEquals(100, $fax_controller->getErrorCode());
		
    }
    
    function testForcedFaxServiceOnMerchantMessageMapRecord()
    {
    	setProperty('fax_service_list', 'Faxage,Phaxio');
    	$ids = $this->ids;
    	$merchant_resource = createNewTestMerchant($ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	$alpha_numeric = $merchant_resource->alphanumeric_id;
    	$map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_id,"message_format"=>'FUW',"delivery_addr"=>"1234567890","message_type"=>"O","info"=>"service=Phaxio"));
    	
    	$order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','dum note',1);
        $order_resource = placeOrderFromOrderData($order_data);

        $this->assertNotNull($order_resource);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000,"Bad order id of: ".$order_resource->order_id);
		$order_id = $order_resource->order_id;
		
		$message_data['order_id'] = $order_id;
		$message_data['message_format'] = "FUW";
		$options[TONIC_FIND_BY_METADATA] = 	$message_data;
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		$message_resource = Resource::find($mmha,'',$options);
		$map_id = $message_resource->map_id;
		$this->assertNotNull($message_resource);
		$fax_controller = new FaxController($mt, $u, $r,5);
		$fax_controller->sendThisMessage($message_resource);
		$this->assertEquals('Phaxio',$fax_controller->service_used,"should have sent with phaxio since it was listed on the MMM record");
    }

    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	$_SERVER['request_time1'] = microtime(true);

		setContext('com.splickit.worldhq');
    	$menu_id = createTestMenuWithOneItem("Test Item 1");
    	$ids['menu_id'] = $menu_id;
    	
    	$merchant_resource = createNewTestMerchant($menu_id);
    	$merchant_id = $merchant_resource->merchant_id;
    	$ids['merchant_id'] = $merchant_id;
    	
    	$map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_id,"message_format"=>'FUW',"delivery_addr"=>"1234567890","message_type"=>"O"));
    	
    	$user_resource = createNewUser(array('flags'=>'1C20000001'));
    	$ids['user_id'] = $user_resource->user_id;
    	
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

if (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'use_main'  && !defined('PHPUnit_MAIN_METHOD')) {
    FaxControllerTest::main();
}

?>