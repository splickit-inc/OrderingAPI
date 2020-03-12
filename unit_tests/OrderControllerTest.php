<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';
require_once 'lib/utilities/dispatch_functions.inc';

class OrderControllerTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;
    var $order_resource;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }

    function testGetOrder()
    {
        setContext('com.splickit.vtwoapi');
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $new_merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $new_merchant_id = $new_merchant_resource->merchant_id;
        MerchantMessageMapAdapter::createMerchantMessageMap($new_merchant_id,'SUW','Epson','O');

        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $ucid = $order_resource->ucid;

        $url = "/app2/portal/orders/$ucid";

        $request = createRequestObject($url);
        $order_controller = new OrderController(getM(),null,$request,5);
        $response_resource = $order_controller->processV2Request();
        $this->validateV2Response($response_resource,'GET');

    }

    function testReassignOrder()
    {
        setContext('com.splickit.vtwoapi');
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $new_merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $new_merchant_id = $new_merchant_resource->merchant_id;
        MerchantMessageMapAdapter::createMerchantMessageMap($new_merchant_id,'SUW','Epson','O');

        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $ucid = $order_resource->ucid;

        $original_order_messages = MerchantMessageHistoryAdapter::getAllOrderMessages($order_id);

        $url = "/app2/portal/orders/$ucid/reassignorder";

        $data['new_merchant_id'] = $new_merchant_id;
        $request = createRequestObject($url,'POST',json_encode($data));
        $order_controller = new OrderController(getM(),$user,$request,5);
        $response_resource = $order_controller->processV2Request();
        $this->assertEquals("success",$response_resource->result);
        $this->validateV2Response($response_resource);

        // check to see if order got reaassigned
        $sql = "SELECT * FROM Merchant_Message_History WHERE order_id = $order_id and merchant_id = $new_merchant_id";
        $options[TONIC_FIND_BY_SQL] = $sql;
        $order_message_resources = Resource::findAll(new MerchantMessageHistoryAdapter(),null,$options);
        $this->assertCount(2,$order_message_resources,"there should have been 2 messages reassigned");
    }

    function testCaptureOrderResponseForPortal()
    {
        setContext('com.splickit.vtwoapi');
        $merchant_resource = createNewTestMerchant($this->ids['menu_id'],array("authorize"=>true));
        $merchant_resource->merchant_external_id = 88888;
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,null,0.00,time());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $ucid = $order_resource->ucid;

        $url = "/app2/portal/orders/$ucid/captureauthorizedpayment";
        $request = createRequestObject($url,'POST');
        $order_controller = new OrderController(getM(),null,$request,5);
        $response_resource = $order_controller->processV2Request();
        $this->assertEquals("success",$response_resource->result);
        $this->validateV2Response($response_resource);
    }

    function testRefundAnOrder()
    {
        setContext('com.splickit.vtwoapi');
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $ucid = $order_resource->ucid;

        $url = "/app2/portal/orders/$ucid/refund";

        $data['order_id'] = $order_id;
        $data['user_id'] = $user['user_id'];
        $data['refund_amount'] = 0.00;
        $data['note'] = 'test refund';
        $data['employee_name'] = 'Bob Roberts';
        $request = createRequestObject($url,'POST',json_encode($data));
        $order_controller = new OrderController(getM(),$user,$request,5);
        $response_resource = $order_controller->processV2Request();
        $this->assertEquals("success",$response_resource->result);
        $this->validateV2Response($response_resource);
    }

    function validateV2Response($resource,$method = 'POST')
    {
        $portal_response = getPortalResponseWithJsonFromResource($resource);
        $json = $portal_response->body;
        if ($array = json_decode($json,true)) {
            $this->assertTrue(isset($array['http_code']),"No http code set");
            $this->assertTrue(isset($array['stamp']),"No stamp field set");
            $this->assertTrue(isset($array['data']),"No data field set");
            if ($method == 'POST') {
                $this->assertTrue(isset($array['data']['result']),"No result field set on response data array");
            }
        } else {
            $this->assertTrue(false,"Body is not valid json: $json");
        }
    }

    function testUpdateOrderStatus()
    {
        setContext('com.splickit.vtwoapi');
        $user_resource = createNewUserWithCCNoCVV();
        $user_resource->balance = 100.00;
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);

        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;

        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id);
        $order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;

        $this->assertEquals('O',$order_resource->status);
        $ucid = $order_resource->ucid;
        $request = createRequestObject("/app2/portal/orders/$ucid/updateorderstatus",'POST',json_encode(array("status"=>'E')));
        $order_controller = new OrderController(getM(),null,$request,5);
        $resource = $order_controller->processV2Request();

        $this->assertEquals("success",$resource->result);
        $this->validateV2Response($resource);

        $db_order  = Resource::find(new OrderAdapter(),$order_id);
        $this->assertEquals('E',$db_order->status);
    }

    function testResendOrder()
    {
        setContext('com.splickit.vtwoapi');
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $ucid = $order_resource->ucid;

        $order_messages = MerchantMessageHistoryAdapter::getAllOrderMessages($order_id);
        foreach ($order_messages as $message_resource) {
            $message_resource->locked = 'S';
            $message_resource->sent = time();
            $message_resource->stamp = getStamp();
            if ($message_resource->message_format == 'SUW') {
                $message_resource->viewed = 'V';
            }
            $message_resource->save();
        }

        $executed_message = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'E');
        $this->assertEquals('S',$executed_message->locked);

        $executed_pulled_message = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'SUW');
        $this->assertEquals('S',$executed_pulled_message->locked);
        $this->assertEquals('V',$executed_pulled_message->viewed);

        $url = "/app2/portal/orders/$ucid/resendanorder";

        $request = createRequestObject($url,"POST");
        $order_controller = new OrderController(getM(),null,$request,5);
        $resource = $order_controller->processV2Request();


        $this->assertEquals("success",$resource->result);

        $executed_pulled_message = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'SUW');
        $this->assertEquals('P',$executed_pulled_message->locked,"Locked shojld be set back to 'P'");
        $this->assertEquals(null,$executed_pulled_message->viewed,"Viewed should be back to null");
        $this->assertTrue($executed_pulled_message->next_message_dt_tm <= time(),"next message should be now or earlier");

        $executed_message = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'E');
        $this->assertEquals('N',$executed_message->locked);
        $this->assertTrue($executed_message->next_message_dt_tm <= time(),"next message should be now or earlier");
    }

    function testGetErrorResponseFromBadOrderId()
    {
        $bad_ucid = "5198-y7c1p-h5qai-uhy76";
        $url = "/app2/portal/orders/$bad_ucid/resendanorder";
        $request = createRequestObject($url,"POST");
        $order_controller = new OrderController(getM(),null,$request,5);
        $resource = $order_controller->processV2Request();
        $this->assertNotNull($resource->error_message);
        $this->assertEquals("failure",$resource->result);

        //validate json response
        $response = getPortalResponseWithJsonFromResource($resource,null);
        $json = $response->body;
        $json_array = json_decode($json,true);
        $this->assertEquals('failure',$json_array['data']['result']);
        $this->assertEquals(OrderController::ORDER_ID_DOES_NOT_EXIST_ERROR_MESSAGE." $bad_ucid",$json_array['error']['error_message']);
    }

    static function setUpBeforeClass()
    {
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        ini_set('max_execution_time',300);

        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;


        $skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty("vtwoapi","vtwoapi",252, 101);
        setContext('com.splickit.vtwoapi');


        $menu_id = createTestMenuWithOneItem('item_one');
        $ids['menu_id'] = $menu_id;
        $_SERVER['log_level'] = 5;
        $_SERVER['unit_test_ids'] = $ids;
    }
    
	static function tearDownAfterClass()
    {
        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->rollback();
        date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    OrderControllerTest::main();
}

?>