<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class PushNotificationTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
    var $ids;

	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
        setContext('com.splickit.pitapit');
        $this->ids =  $_SERVER['unit_test_ids'];
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->stamp);
    }

    function testCreatePushRecord()
    {
        $user_id = $this->ids['user_id'];

        $umsma = new UserMessagingSettingMapAdapter($mt);
        $records = $umsma->getRecords(array('user_id'=>$user_id));

        $message = "SumDumMessage".rand(1000,9999);
        $push_message_controller = new PushMessageController($m,$u,$r);
        $push_message_controller->stagePush($records,$message);
        return $message;
    }

    /**
     * @depends testCreatePushRecord
     * @param $message
     */
    function testSendAndroidNotification($message){
        $token = $this->ids['token'][PushMessageController::ANDROID];

        $mmha = new MerchantMessageHistoryAdapter($m);
        $options[TONIC_FIND_BY_METADATA] = array("message_text"=>$message,"message_delivery_addr"=>$token);
        $push_message_resource = Resource::find($mmha,null,$options);
        $this->assertNotNull($push_message_resource);
        $push_controller = ControllerFactory::generateFromMessageResource($push_message_resource);
        //$push_controller->setTestDeliveryAddress(null);
        $result = $push_controller->sendThisMessage($push_message_resource);
        $new_push_message_resource = Resource::find($mmha, null, $options);
        $this->assertNotNull($new_push_message_resource);
        $this->assertEquals( $message, $new_push_message_resource->message_text);
        $this->assertEquals($push_controller->push_message_services[PushMessageController::ANDROID]->curl_response['raw_result'],$new_push_message_resource->response);
    }

    /**
     * @depends testCreatePushRecord
     * @param $message
     */
    function testSendAppleNotification($message){
        $token = $this->ids['token'][PushMessageController::APPLE];

        $mmha = new MerchantMessageHistoryAdapter($m);
        $options[TONIC_FIND_BY_METADATA] = array("message_text"=>$message,"message_delivery_addr"=>$token);
        $push_message_resource = Resource::find($mmha,null,$options);
        $this->assertNotNull($push_message_resource);
        $push_controller = ControllerFactory::generateFromMessageResource($push_message_resource);
        //$push_controller->setTestDeliveryAddress(null);
        $result = $push_controller->sendThisMessage($push_message_resource);
        $new_push_message_resource = Resource::find($mmha, null, $options);
        $this->assertNotNull($new_push_message_resource);
        $this->assertEquals( $message, $new_push_message_resource->message_text);
        $this->assertEquals($push_controller->push_message_services[PushMessageController::APPLE]->curl_response['raw_result'],$new_push_message_resource->response);
    }

    function testCreatePushRecordWithFailure()
    {
        $user_id = $this->ids['user_id'];
        

        $umsma = new UserMessagingSettingMapAdapter($mt);
        $records = $umsma->getRecords(array('user_id'=>$user_id));
        $message = "failthismessage";
        $push_message_controller = new PushMessageController($m,$u,$r);
        $push_message_controller->stagePush($records,$message);
        
        return $message;

        
    }

    /**
     * @depends testCreatePushRecordWithFailure
     * @param $message
     */
    function testSendAndroidMessageWithFailure($message){
        $token = $this->ids['token'][PushMessageController::ANDROID];
        $mmha = new MerchantMessageHistoryAdapter($m);
        $options[TONIC_FIND_BY_METADATA] = array("message_text"=>$message,"message_delivery_addr"=>$token);
        $push_message_resource = Resource::find($mmha,null,$options);
        $this->assertNotNull($push_message_resource);
        $push_controller = ControllerFactory::generateFromMessageResource($push_message_resource);
        $result = $push_controller->sendThisMessage($push_message_resource);
        $new_push_message_resource = Resource::find($mmha, null, $options);

        $this->assertNotNull($push_controller->getErrorCode(), 'have a code error');
        $this->assertNotNull($push_controller->getErrorMessage(), 'have a error');
        $this->assertNotNull($new_push_message_resource);
        $this->assertEquals('F',$new_push_message_resource->locked,"status are failure");
        $this->assertContains('error', $push_controller->push_message_services[PushMessageController::ANDROID]->curl_response['raw_result']);
    }

    /**
     * @depends testCreatePushRecordWithFailure
     * @param $message
     */
    function testSendAppleMessageWithFailure($message){
        $token = $this->ids['token'][PushMessageController::APPLE];
        $mmha = new MerchantMessageHistoryAdapter($m);
        $options[TONIC_FIND_BY_METADATA] = array("message_text"=>$message,"message_delivery_addr"=>$token);
        $push_message_resource = Resource::find($mmha,null,$options);
        $this->assertNotNull($push_message_resource);
        $push_controller = ControllerFactory::generateFromMessageResource($push_message_resource);
        $result = $push_controller->sendThisMessage($push_message_resource);
        $new_push_message_resource = Resource::find($mmha, null, $options);

        $this->assertNotNull($push_controller->getErrorCode(), 'have a code error');
        $this->assertNotNull($push_controller->getErrorMessage(), 'have a error');
        $this->assertNotNull($new_push_message_resource);
        $this->assertEquals('F',$new_push_message_resource->locked,"status are failure");
        $this->assertContains('error', $push_controller->push_message_services[PushMessageController::APPLE]->curl_response['raw_result']);
    }
    
    function testStagePushMessageToUserFromRequest(){
        $user_id = $this->ids['user_id'];

        $message = "Hello World: ".rand(1000,9999);
        $request = createRequestObject("/app2/admin/pushmessage/?users=".$user_id."&message=".$message."&skin=com.splickit.pitapit", 'GET', $body, 'application/json');
        $pmc = new PushMessageController($m,$u,$request);
        $pmc->pushMessageToUserFromRequest();
        $merchant_message_history_adapter = new MerchantMessageHistoryAdapter($mimetypes);
        $merchant_message_history_options[TONIC_FIND_BY_METADATA] = array("message_format" => 'Y', 'message_text' => $message);
        $merchant_message_history_resources = Resource::findAll($merchant_message_history_adapter, null, $merchant_message_history_options);
        $this->assertCount(2, $merchant_message_history_resources, 'Stage two message, one for gcm and one for iphone');
        $this->assertEquals($message, $merchant_message_history_resources[0]->message_text);
        $this->assertEquals($message, $merchant_message_history_resources[1]->message_text);
        $this->assertEquals($merchant_message_history_resources[PushMessageController::ANDROID]->message_text, $merchant_message_history_resources[PushMessageController::ANDROID]->message_text, 'Send the same message');
    }

    function testStagePushMessageToUserFromRequestWithTwoOrMoreDevices(){
        $user_id = $this->ids['user_id'];
        $skin_id =  $this->ids['skin_id'];

        $new_android_device_id = generateCode(20);
        $new_android_token = generateCode(40);

        $umsma = new UserMessagingSettingMapAdapter($mimetypes);
        $map_id = $umsma->createRecord($user_id, $skin_id, "push", 'gcm', $new_android_device_id, $new_android_token, "Y");


        $message = "Hello World: ".rand(1000,9999);
        $request = createRequestObject("/app2/admin/pushmessage/?users=".$user_id."&message=".$message."&skin=com.splickit.pitapit", 'GET', $body, 'application/json');
        $pmc = new PushMessageController($m,$u,$request);
        $pmc->pushMessageToUserFromRequest();
        $merchant_message_history_adapter = new MerchantMessageHistoryAdapter($mimetypes);
        $merchant_message_history_options[TONIC_FIND_BY_METADATA] = array("message_format" => 'Y', 'message_text' => $message);
        $merchant_message_history_resources = Resource::findAll($merchant_message_history_adapter, null, $merchant_message_history_options);
        $this->assertCount(2, $merchant_message_history_resources, 'Stage two message, one for gcm and one for iphone');
        $this->assertEquals($message, $merchant_message_history_resources[0]->message_text);
        $this->assertEquals($message, $merchant_message_history_resources[1]->message_text);
        $this->assertEquals($merchant_message_history_resources[0]->message_text, $merchant_message_history_resources[1]->message_text, 'Send the same message');

        $this->assertContains("user_messaging_setting_map_id=$map_id", $merchant_message_history_resources[0]->info, 'Using the last active android device');
    }


    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);
        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;
        $user_resource = createNewUser();
        $ids['user_id'] = $user_resource->user_id;
        $ids['skin_id'] = 13; // pitapit skin id
        $android_device_id = generateCode(20);
        $ios_device_id = generateCode(20);
        $android_token = generateCode(40);
        $ios_token = generateCode(40);

        $umsma = new UserMessagingSettingMapAdapter($mimetypes);
        $umsma->createRecord($ids['user_id'], $ids['skin_id'], "push", 'gcm', $android_device_id, $android_token, "Y");
        $umsma->createRecord($ids['user_id'], $ids['skin_id'], "push", 'iphone', $ios_device_id, $ios_token, "Y");

        $ids['token'][PushMessageController::ANDROID] = $android_token;
        $ids['token'][PushMessageController::APPLE] = $ios_token;

        $_SERVER['unit_test_ids'] = $ids;
    }
    
	static function tearDownAfterClass()
    {
        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->rollback();
    }

    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    PushNotificationTest::main();
}

?>