<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class EmailControllerTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $user1_email;
	var $merchant_id;
	
	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		setProperty("test_mandril_fail", "false");
		$this->merchant_id = $_SERVER['unit_test_ids']['merchant_id'];
       	
	}
	
	function tearDown() 
	{
		setProperty("test_mandril_fail", "false");
		unset($this->merchant);
		$_SERVER['STAMP'] = $this->stamp;
    }


    
    function testEmailControllerSend()
    {
    	$merchant_id = $this->merchant_id;
    	
    	// first get message resource
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$map_id = $mmha->createMessage($merchant_id, $order_id, 'E', 'dummy@dummy.com', time()-5, 'I', $info, 'Hello World');
    	$message_resource = Resource::find($mmha,''.$map_id);
    	$this->assertNotNull($message_resource);
    	
    	//get message controller
    	$email_controller = new EmailController($mt, $u, $r);
    	$result = $email_controller->sendThisMessage($message_resource);
    	$this->assertEquals(true, $result);
    	
    	// check for mock send
    	$m_resource = Resource::find($mmha,''.$map_id);
    	$response = $m_resource->response;
    	$this->assertEquals("[{\"email\":\"mock.dummy@dummy.com\",\"status\":\"sent\"}]", $response);
    }
    
    function testSendEmailWithZeroForMerchantId()
    {
    	$message_text = 'srlz!O:22:"Guzzle\Http\EntityBody":6:{s:18:" * contentEncoding";b:0;s:17:" * rewindFunction";N;s:9:" * stream";i:0;s:7:" * size";N;s:8:" * cache";a:9:{s:12:"wrapper_type";s:3:"PHP";s:11:"stream_type";s:4:"TEMP";s:4:"mode";s:3:"w+b";s:12:"unread_bytes";i:0;s:8:"seekable";b:1;s:3:"uri";s:10:"php://temp";s:8:"is_local";b:1;s:11:"is_readable";b:1;s:11:"is_writable";b:1;}s:13:" * customData";a:1:{s:7:"default";b:1;}}';
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$map_id = $mmha->createMessage(0, $order_id, 'Ewel', 'dummy@dummy.com', time()-5, 'I', $info,$message_text);
    	$message_resource = Resource::find($mmha,''.$map_id);
    	$this->assertNotNull($message_resource);
    	
    	//get message controller
    	$email_controller = new EmailController($mt, $u, $r);
    	$result = $email_controller->sendThisMessage($message_resource);
    	$this->assertEquals(true, $result);
    	
    }

    function testMandrilError()
    {
    	$merchant_id = $this->merchant_id;
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$sql = "UPDATE Mercahnt_Message_History SET locked = 'C' WHERE locked = 'N'";
    	$mmha->_query($sql);
    	$send_time = time()-2;
    	$map_id = $mmha->createMessage($merchant_id, $order_id, 'E', 'dummy@dummy.com',$send_time, 'Z', "subject=mandril monitor", 'dummy send');
    	$this->assertTrue($map_id > 1000);
    	setProperty("test_mandril_fail", "true");
    	$message_resource = Resource::find($mmha,''.$map_id);
    	$this->assertEquals('Z', $message_resource->message_type);

    	$email_controller = new EmailController($mt, $u, $r,5);
    	$email_controller->sendThisMessage($message_resource);
		$message_resource = Resource::find($mmha,''.$map_id);
		$this->assertEquals('F', $message_resource->locked);
		$info_string = $message_resource->info;
		//error_message=Error sending email in email controller. COULD NOT CONNECT!
		$info_data = $email_controller->extractInfoData($info_string);
		$this->assertEquals("Error sending email in email controller. COULD NOT CONNECT!", $info_data['error_message']);
   }

    function testReplyEmail()
    {
        setContext('com.splickit.worldhq');
        $brand_id = getBrandIdFromCurrentContext();
        $brand_resource = Resource::find(new BrandAdapter(getM()),$brand_id);
        //$brand_resource->support_email = 'sumdumaddress@sumdumserver.com';
        $brand_resource->support_email = 'xxxxxxxx@sumdumservxx';
        $brand_resource->save();
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);

        $conf_message = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_resource->order_id, 'Econf');
        $email_controller = ControllerFactory::generateFromMessageResource($conf_message);
        $email_controller->sendThisMessage($conf_message);
        $email_fields = $email_controller->getEmailFields();
        $this->assertEquals('support@dummy.com',$email_fields['reply_to']);

        $good_email = 'sumdumaddress@sumdumserver.com';
        $brand_resource->support_email = $good_email;
        $brand_resource->save();

        $conf_message->locked = 'N';
        $conf_message->save();

        $email_controller = ControllerFactory::generateFromMessageResource($conf_message);
        $email_controller->sendThisMessage($conf_message);
        $email_fields = $email_controller->getEmailFields();
        $this->assertEquals($good_email,$email_fields['reply_to']);


        $brand_resource->support_email = '';
        $brand_resource->save();

        $conf_message->locked = 'N';
        $conf_message->save();

        $email_controller = ControllerFactory::generateFromMessageResource($conf_message);
        $email_controller->sendThisMessage($conf_message);
        $email_fields = $email_controller->getEmailFields();
        $this->assertEquals($merchant_resource->shop_email,$email_fields['reply_to']);

    }

   	static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);
    	SplickitCache::flushAll();
    	$db = DataBase::getInstance();
    	$mysqli = $db->getConnection();
    	$mysqli->begin_transaction(); ;
    	createWorldHqSkin();
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
    EmailControllerTest::main();
}
    
?>