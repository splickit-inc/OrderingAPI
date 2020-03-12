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


class AdminDispatchTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;
    var $info;
    var $api_port = "80";

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        //$_SERVER['DO_NOT_RUN_CC'] = true;
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        if (isset($_SERVER['XDEBUG_CONFIG'])) {
            $this->api_port = "10080";
        }
    }

    function tearDown()
    {
        //delete your instance
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
        unset($this->info);
    }

//    function testAdminPushMessage()
//    {
//        $user_resource = createNewUser();
//        $user_messaging_setting_map_adapter = new UserMessagingSettingMapAdapter($mimetypes);
//        $token = generateCode(40);
//        $device_id = generateCode(20);
//        $id = $user_messaging_setting_map_adapter->createRecord($user_resource->user_id, 13, "push", "iphone", "$device_id", $token, "Y");
//        $this->assertTrue($id > 0);
//        $message = "Hello World: ".rand(1000,9999);
//        $request = createRequestObject("/app2/admin/pushmessage/?users=".$user_resource->user_id."&message=".$message."&skin=com.splickit.pitapit", 'GET', $body, 'application/json');
//        $pmc = new PushMessageController($m,$u,$request);
//        $pmc->pushMessageToUserFromRequest();
//        $merchant_message_history_adapter = new MerchantMessageHistoryAdapter($mimetypes);
//        $merchant_message_history_options[TONIC_FIND_BY_METADATA] = array("message_format" => 'Y', 'message_text' => $message);
//        $merchant_message_history_resources = Resource::find($merchant_message_history_adapter, null, $merchant_message_history_options);
//        $this->assertNotNull($merchant_message_history_resources->map_id);
//        $this->assertEquals($message, $merchant_message_history_resources->message_text);
//
//    }

//    function testImportBrinkPitaPit()
//    {
//        //$this->assertTrue(false,"something is wrong with this test, takes 5 minutes to run so am bypassing now. Its for Brink menu importing");
//        setContext('com.splickit.pitapit');
//        $merchant_external_id = 'brink-pitapit-88888';
//        $merchant_resource = getOrCreateNewTestMerchantBasedOnExternalId($merchant_external_id);
//        Resource::createByData(new MerchantBrinkInfoMapsAdapter($m), array("merchant_id" => $merchant_resource->merchant_id, "brink_location_token" => getProperty('brink_test_location_token')));
//
//        $url = "http://127.0.0.1:" . $this->api_port . "/app2/admin/importbrinkmerchant?log_level=5&merchant_id=$merchant_external_id";
//        $response = $this->makeRequest($url, $user, 'GET', $data);
//        $this->assertEquals(200, $this->info['http_code']);
//        $request_result_as_array = json_decode($response, true);
//        $this->assertNotNull($request_result_as_array);
//        $menu_id = $request_result_as_array['menu_id'];
//        $complete_menu = CompleteMenu::getCompleteMenu($menu_id, 'Y', $merchant_resource->merchant_id, 2);
//        $this->assertCount(9, $complete_menu['menu_types']);
//        $meat_pitas = $complete_menu['menu_types'][0];
//        $this->assertEquals("Meat Pitas", $meat_pitas['menu_type_name']);
//        $meat_item = $meat_pitas['menu_items'][0];
//        $this->assertCount(2, $meat_item['size_prices']);
//        $this->assertCount(6, $meat_item['modifier_groups']);
//        $modifier_group_pita = $meat_item['modifier_groups'][0];
//        $this->assertEquals('Pita Choice', $modifier_group_pita['modifier_group_display_name']);
//        $this->assertCount(3, $modifier_group_pita['modifier_items']);
//    }



    function testBadUrlError()
    {
        // /mysql/dbadmin/
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/mysql/dbadmin";
        $response = $this->makeRequest($url,null);

        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $this->assertEquals(422,$this->info['http_code']);

        $this->assertNotNull($response_array['error'],"there should have been an error returned because the endpoint does not exist");
        $this->assertEquals("Unknown Endpoint.",$response_array['error']['error']);

    }


    function testGetErrorResponseFromBadOrderId()
    {
        $bad_ucid = "5198-y7c1p-h5qai-uhy76";
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/orders/$bad_ucid/resendanorder";
        $response = $this->makeRequest($url, NULL,'POST');

        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $this->assertNotNull($response_array['error'],"there should have been an error returned because the order ucid does not exist");
        $this->assertEquals(OrderController::ORDER_ID_DOES_NOT_EXIST_ERROR_MESSAGE." $bad_ucid",$response_array['error']['error_message']);
        $data = $response_array['data'];
        $this->assertEquals("failure",$data['result']);

    }

    function testJsonResponseUpdateOrderStatus()
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

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/orders/$ucid/updateorderstatus";
        $data['status'] = 'E';
        $response = $this->makeRequest($url, $user,'POST',$data);

        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $data = $response_array['data'];
        $this->assertEquals("success",$data['result']);

        $db_order  = Resource::find(new OrderAdapter(),$order_id);
        $this->assertEquals('E',$db_order->status);

    }


    function testResendAnOrderNewDestinationWithJSONResponse()
    {
        setContext('com.splickit.vtwoapi');
        $user_resource = createNewUserWithCCNoCVV();
        $user_resource->balance = 100;
        $user_resource->save();
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

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/orders/$ucid/resendanorder";

        $data['order_id'] = $order_id;
        $new_destination = 'dummy@dummy.com';
        $data['new_destination_address'] = $new_destination;
        $response = $this->makeRequest($url, $user,'POST',$data);

        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $data = $response_array['data'];
        $this->assertEquals("success",$data['result']);
        $this->assertEquals(OrderController::ORDER_SENT_TO_NEW_DESTINATION_MESSAGE,$data['message']);

        $old_message_resource = Resource::find(new MerchantMessageHistoryAdapter(),$executed_message->map_id);
        $this->assertEquals('S',$old_message_resource->locked,"Locked should stay Sent for the 'X' message");


        $mdata['order_id'] = $order_id;
        $mdata['message_format'] = 'E';
        $mdata['message_delivery_addr'] = $new_destination;
        $options[TONIC_FIND_BY_METADATA] = $mdata;
        $new_message_resource = Resource::find(new MerchantMessageHistoryAdapter(),null,$options);
        $this->assertEquals('N',$new_message_resource->locked,"Locked should stay Sent for the 'X' message");
        $this->assertTrue($new_message_resource->next_message_dt_tm <= time(),"next message should be now or earlier for the new messages");
    }

    function testResendOrderWithJSONResponse()
    {
        setContext('com.splickit.vtwoapi');
        $user_resource = createNewUserWithCCNoCVV();
        $user_resource->balance = 100;
        $user_resource->save();
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

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/orders/$ucid/resendanorder";
        $response = $this->makeRequest($url, $user,'POST');

        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $data = $response_array['data'];
        $this->assertEquals("success",$data['result']);
        $this->assertEquals(OrderController::ORDER_HAS_BEEN_RESENT_TO_THE_DESTINATION,$data['message']);

        $executed_pulled_message = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'SUW');
        $this->assertEquals('P',$executed_pulled_message->locked,"Locked shojld be set back to 'P'");
        $this->assertEquals(null,$executed_pulled_message->viewed,"Viewed should be back to null");
        $this->assertTrue($executed_pulled_message->next_message_dt_tm <= time(),"next message should be now or earlier");

        $executed_message = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'E');
        $this->assertEquals('N',$executed_message->locked);
        $this->assertTrue($executed_message->next_message_dt_tm <= time(),"next message should be now or earlier");


    }

    function testJSONResponseRefundOrder()
    {
        setContext('com.splickit.vtwoapi');
        $user_resource = createNewUserWithCCNoCVV();
        $user_resource->balance = 100;
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);

        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $ucid = $order_resource->ucid;

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/orders/$ucid/refund";

        $data['order_id'] = $order_id;
        $data['user_id'] = $user['user_id'];
        $data['refund_amt'] = 0.00;
        $data['note'] = 'test refund';
        $data['employee_name'] = 'Bob Roberts';
        $response = $this->makeRequest($url, $user,'POST',$data);

        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $data = $response_array['data'];
        $this->assertEquals("success",$data['result']);


    }




/*
    function testImportGoodcentsBadMerchantId()
    {
        setContext('com.splickit.goodcentssubs');
        $url = "http://localhost/app2/admin/pos/import/xoikos/1234567890";
        $response = $this->makeRequest($url, $user,'GET',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals('No matching merchant external id: 1234567890',$response_array['ERROR']);
    }

    function testImportBrinkPitaPitBadMerchantId()
    {
        $url = "http://localhost/app2/admin/importbrinkmerchant?log_level=5&merchant_id=rtyuyt567ujhtyuj";
        $response = $this->makeRequest($url, $user,'GET',$data);
        $this->assertEquals(422, $this->info['http_code']);
        $request_result_as_array = json_decode($response,true);
        $this->assertNotNull($request_result_as_array);
        $this->assertEquals("Merchant does not appear to be a brink merchant",$request_result_as_array['error']);

    }

    function testImportBrinkPitaPit()
    {
        $this->assertTrue(false,"something is wrong with this test, takes 5 minutes to run so am bypassing now. Its for Brink menu importing");
        setContext('com.splickit.pitapit');
        $merchant_external_id = 'brink-pitapit-88888';
        $merchant_resource = getOrCreateNewTestMerchantBasedOnExternalId($merchant_external_id);
        Resource::createByData(new MerchantBrinkInfoMapsAdapter($m), array("merchant_id" => $merchant_resource->merchant_id, "brink_location_token" => getProperty('brink_test_location_token')));

        $url = "http://localhost/app2/admin/importbrinkmerchant?log_level=5&merchant_id=$merchant_external_id";
        $response = $this->makeRequest($url, $user, 'GET', $data);
        $this->assertEquals(200, $this->info['http_code']);
        $request_result_as_array = json_decode($response, true);
        $this->assertNotNull($request_result_as_array);
        $menu_id = $request_result_as_array['menu_id'];
        $complete_menu = CompleteMenu::getCompleteMenu($menu_id, 'Y', $merchant_resource->merchant_id, 2);
        $this->assertCount(9, $complete_menu['menu_types']);
        $meat_pitas = $complete_menu['menu_types'][0];
        $this->assertEquals("Meat Pitas", $meat_pitas['menu_type_name']);
        $meat_item = $meat_pitas['menu_items'][0];
        $this->assertCount(2, $meat_item['size_prices']);
        $this->assertCount(6, $meat_item['modifier_groups']);
        $modifier_group_pita = $meat_item['modifier_groups'][0];
        $this->assertEquals('Pita Choice', $modifier_group_pita['modifier_group_display_name']);
        $this->assertCount(3, $modifier_group_pita['modifier_items']);
    }
*/
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

        $skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty("vtwoapi","vtwoapi",252, 101);
        setContext('com.splickit.vtwoapi');


        $menu_id = createTestMenuWithOneItem('item_one');
        $ids['menu_id'] = $menu_id;
        $user_resource = createNewUser(array("flags"=>"1C20000001"));
        $ids['user_id'] = $user_resource->user_id;
        $ids['user'] = $user_resource->getDataFieldsReally();
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
    AdminDispatchTest::main();
}

?>