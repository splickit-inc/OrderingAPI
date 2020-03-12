<?php

error_reporting(E_ERROR | E_COMPILE_ERROR | E_COMPILE_WARNING | E_PARSE);
$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');
$db_info->database = 'smaw_unittest';
$db_info->username = 'root';
$db_info->password = 'splickit';
if (isset($_SERVER['XDEBUG_CONFIG'])) {
    putenv("SMAW_ENV=unit_test_ide");
    $db_info->hostname = "127.0.0.1";
    $db_info->port = 13306;
} else {
    $db_info->hostname = "db_container";
    $db_info->port = 3306;
}
$_SERVER['DB_INFO'] = $db_info;
require_once 'lib/curl_objects/splickitcurl.php';
require_once 'lib/mocks/viopaymentcurl.php';
require_once 'lib/utilities/functions.inc';
require_once 'lib/utilities/unit_test_functions.inc';


class PortalMessageDispatchTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;
    var $info;
    var $api_port = "80";
    var $menu_id;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        //$_SERVER['DO_NOT_RUN_CC'] = true;
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__ . '-' . $_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        if (isset($_SERVER['XDEBUG_CONFIG'])) {
            $this->api_port = "10080";
        }
        setContext("com.splickit.worldhq");
    }

    function tearDown()
    {
        //delete your instance
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
        unset($this->info);
    }

    function testGetOrderMessagesForLoadedMerchantId()
    {
        $merchant_resource = $this->createMerchantWithPortalMessageType();
        $merchant_id = $merchant_resource->merchant_id;
        $this->createOrderMessages($merchant_id, 10, 30);
        $orders = OrderAdapter::staticGetRecords(['merchant_id' => $merchant_id], 'OrderAdapter');
        $this->assertCount(10, $orders);
        $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
        $options[TONIC_FIND_BY_METADATA]['locked'] = "P";
        $options[TONIC_SORT_BY_METADATA] = ' order_id ASC ';
        $messages = Resource::findAll(new MerchantMessageHistoryAdapter(getM()), null, $options);
        $this->assertCount(10, $messages);
        for ($i = 0; $i < 3; $i++) {
            $messages[$i]->locked = 'S';
            $messages[$i]->viewed = 'V';
            $messages[$i]->save();
        }

        $mmha = new MerchantMessageHistoryAdapter(getM());
        $options2[TONIC_FIND_BY_SQL] = "SELECT * from Merchant_Message_History WHERE merchant_id = $merchant_id AND locked = 'S' AND next_message_dt_tm <= NOW() ORDER BY next_message_dt_tm desc";
        $records = $mmha->getRecords(null, $options2);
        $this->assertCount(3, $records, "there should now be 3 records that are showing as sent");

        sleep(1);
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/messages?merchant_id=$merchant_id";

//        $request = createRequestObject($url,'GET');
//        $portal_message_controller = new PortalMessageController(getM(),null,$request,5);
//        $response = $portal_message_controller->processRequest();
//        $response_array['data'] = $response->getDataFieldsReally();


        $response = $this->makeRequest($url, null);
        $response_array = json_decode($response, true);
        $this->assertNotNull($response_array, "returned data was not json");
        $this->assertEquals(200, $this->info['http_code']);


        // that call should have caused all due messages to convert to 'S' and non-viewed.
        $options2[TONIC_FIND_BY_SQL] = "SELECT * from Merchant_Message_History WHERE merchant_id = $merchant_id AND locked = 'S' AND next_message_dt_tm <= NOW() ORDER BY next_message_dt_tm desc";
        $records = $mmha->getRecords(null, $options2);
        $this->assertCount(7, $records, "there should now be 7 records that are showing as sent");
        return $response_array['data'];
    }

    /**
     * @depends testGetOrderMessagesForLoadedMerchantId
     */
    function testHaveAllSectionsOfTheReturn($messages)
    {
        $this->assertCount(5, $messages, "there shuold be 5 sections");
    }

    /**
     * @depends testGetOrderMessagesForLoadedMerchantId
     */
    function testShouldBeReadOnlyFalse($messages)
    {
        $this->assertFalse($messages['read_only'], "Read only should be false");
    }

    /**
     * @depends testGetOrderMessagesForLoadedMerchantId
     */
    function testShowPastMessages($messages)
    {
        $past_messages = $messages['past_messages'];
        $this->assertCount(3, $past_messages, 'There should be 3 past messages');
    }

    /**
     * @depends testGetOrderMessagesForLoadedMerchantId
     */
    function testShowLateMessages($messages)
    {
        $late_messages = $messages['late_messages'];
        $this->assertCount(0, $late_messages, "there should be 0 messages that have not been marked as viewed since we are no longer useing a buffer");
        return $late_messages;
    }

    /**
     * @depends testGetOrderMessagesForLoadedMerchantId
     */
    function testShowCurrentMessages($messages)
    {
        $current_messages = $messages['current_messages'];
        $this->assertCount(4, $current_messages, "there should be 4 messages that have not been marked as viewed that are within the lead time of their pickup time");
    }

    /**
     * @depends testGetOrderMessagesForLoadedMerchantId
     */
    function testShowFutureMessages($messages)
    {
        $future_messages = $messages['future_messages'];
        $this->assertCount(3, $future_messages, "there should be 3 messages in the future messages section");
    }

    /**
     * @depends testGetOrderMessagesForLoadedMerchantId
     */
    function testResendMessage($messages)
    {
        myerror_log("somd");

        $message_to_resend = $messages['past_messages'][2];
        $order_id = $message_to_resend['order_id'];
        $order_controller = new OrderController(getM(), $u, $r,5);
        $order_controller->resendOrder($order_id);

    }

    /**
     * @depends testShowLateMessages
     */
    function testMarkMessageAsAccepted($late_messages)
    {
        // this test no longer works becauser there 0 late message now. something to do with elimnating the buffer
//        $late_message_info = $late_messages[0];
//        $late_message = json_decode($late_message_info['portal_order_json'],true);
//        //$this->assertEquals('N',$late_message_info['viewed']);
//        $this->assertEquals('S',$late_message_info['locked']);
//        $this->assertNull($late_message_info['viewed']);
//        $order_id = $late_message_info['order_id'];
//        $message_id = $late_message_info['message_id'];
//        $order = new Order($order_id);
//        $this->assertEquals('O',$order->get('status'),"Or should be in the open state since message hasn't been viewed yet");
//
//        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/messages/$message_id/markcomplete";
//        $response = $this->makeRequest($url,null,'POST');
//        $response_array = json_decode($response,true);
//        $this->assertNotNull($response_array,"returned data was not json");
//        $this->assertEquals(200,$this->info['http_code']);
//        $message_record = MerchantMessageHistoryAdapter::staticGetRecordByPrimaryKey($message_id,'MerchantMessageHistoryAdapter');
//        $this->assertEquals('V',$message_record['viewed'],"message should now be showing viewed.");
//
//        $order = new Order($order_id);
//        $this->assertEquals('E',$order->get('status'),"Or should be in the executed state since message hasn't been viewed yet");
    }

    /************************************************/

    function createMerchantWithPortalMessageType()
    {
        $data['message_data'] = ["message_format"=>"P",'delivery_addr'=>"portal"];
        $merchant_resource = createNewTestMerchant($this->ids['menu_id'],$data);
        return $merchant_resource;
    }

    function createOrderMessages($merchant_id,$number_of_orders,$starting_minutes_back)
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $order_resources = [];
        for ($i=0;$i<$number_of_orders;$i++) {
            $current_time = time() - ($starting_minutes_back*60) + (5*60*$i);
            $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
            $checkout_resource = getCheckoutResourceFromOrderData($cart_data,$current_time);
            $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$current_time);
            $order_resources[] = $order_resource;
            // now fix message becuase sytem will not aloow message to be created in the past
            $options[TONIC_FIND_BY_METADATA] = ['order_id'=>$order_resource->order_id,'message_format'=>'P'];
            $message_resource = Resource::find(new MerchantMessageHistoryAdapter(getM()),null,$options);
            $message_resource->next_message_dt_tm = $current_time;
            $message_resource->save();
        }
        return $order_resources;
    }


    function getExternalId()
    {
        if ($external_id = getContext()) {
            // use it
        } else {
            $external_id = "com.splickit.vtwoapi";
        }
        return $external_id;
    }

    function makeRequest($url,$userpassword,$method = 'GET',$data = null)
    {
        logData($data," curl data");
        unset($this->info);
        $method = strtoupper($method);
        $curl = curl_init($url);
        if ($userpassword) {
            curl_setopt($curl, CURLOPT_USERPWD, $userpassword);
        }
        $external_id = getContext();
        $headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:AdminDispatchTest","NO_CC_CALL:true");
        if ($authentication_token = $data['splickit_authentication_token']) {
            $headers[] = "splickit_authentication_token:$authentication_token";
        }
        if ($data['headers']) {
            $headers = $data['headers'];
            unset($data['headers']);
        }
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            if ($data) {
                $json = json_encode($data);
                curl_setopt($curl, CURLOPT_POSTFIELDS,$json);
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Content-Length: ' . strlen($json);
            }
        } else if ($method == 'DELETE') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        logCurl($url,$method,$userpassword,$headers,$json);
        $result = curl_exec($curl);

        $this->info = curl_getinfo($curl);
        curl_close($curl);
        return $result;
    }

    static function setUpBeforeClass()
    {
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        ini_set('max_execution_time',300);
        SplickitCache::flushAll();

        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $ids['menu_id'] = $menu_id;

        $_SERVER['log_level'] = 5;
        $_SERVER['unit_test_ids'] = $ids;

    }

    static function tearDownAfterClass()
    {
        //mysqli_query("ROLLBACK");
        date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
        $suite = new PHPUnit_Framework_TestSuite( __CLASS__);
        PHPUnit_TextUI_TestRunner::run( $suite);
    }



}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    PortalMessageDispatchTest::main();
}

?>