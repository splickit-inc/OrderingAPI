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

require_once 'lib/utilities/functions.inc';
require_once 'lib/utilities/unit_test_functions.inc';

class APIDispatchCateringOrderTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;
    var $info;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        setContext($this->ids['context']);
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

    function testGetCateringOrderAvailablePickupTimes()
    {
        $merchant_resource = createNewTestMerchantWithCatering($this->ids['menu_id'],$data);
        $merchant_id = $merchant_resource->merchant_id;

        $mcir = $merchant_resource->merchant_catering_info_resource;
        $mcir->min_lead_time_in_hours_from_open_time = 4;
        $mcir->max_days_out = 2;
        $message = 'Hello! This is the catering message';
        $mcir->catering_message_to_user_on_create_order = $message;
        $mcir->save();

        $user_resource = createNewUserWithCCNoCVV();
        //$user = logTestUserResourceIn($user_resource);

        $userpassword = $user_resource->email.':welcome';
        $response = $this->makeRequest("http://127.0.0.1:".$this->api_port."app2/apiv2/merchants/$merchant_id/cateringorderavailabletimes/pickup",$userpassword,'GET');
        $this->assertEquals(200,$this->info['http_code']);
        $response_as_array = json_decode($response,true);
        $this->assertNull($response_as_array['error']);
        $submit_times_array  = $response_as_array['data']['available_catering_times'];
        $this->assertEquals($message,$response_as_array['data']['catering_message_to_user_on_create_order']);
        $this->assertEquals($mcir->minimum_pickup_amount,$response_as_array['data']['minimum_pickup_amount'],"minimum pickup amount should be on retuned data");
        $this->assertEquals($mcir->minimum_delivery_amount,$response_as_array['data']['minimum_delivery_amount'],"minimum delivery amount should be on retuned data");
        $first_time = $submit_times_array['daily_time'][0][0]['ts'];
        $this->assertTrue($first_time > 2*3600,"first time should be at least the minimum lead time in the future");
    }

    function testCreateCateringOrderPickup()
    {
        $user_resource = createNewUserWithCCNoCVV();
        //$user = logTestUserResourceIn($user_resource);
        $catering_data = $this->getCateringOrderData($this->ids['merchant_id']);
        unset($catering_data['order_type']);
        $timestamp_of_event = getTomorrowTwelveNoonTimeStampDenver()+(2*3600);
        $catering_data['timestamp_of_event'] = $timestamp_of_event;

        $userpassword = $user_resource->email.':welcome';
        $response = $this->makeRequest("http://127.0.0.1:".$this->api_port."/app2/apiv2/catering",$userpassword,'POST',$catering_data);
        $this->assertEquals(200,$this->info['http_code']);
        $response_as_array = json_decode($response,true);
        $this->assertNull($response_as_array['error']);

        $data = $response_as_array['data'];

        $ucid = $data['ucid'];
        $this->assertNotNull($ucid);
        $order_id = $data['order_id'];

        $this->assertTrue($order_id > 1000,"we have a valid order id");
        $catering_order_record = CateringOrdersAdapter::staticGetRecord(array("order_id"=>$order_id),'CateringOrdersAdapter');
        $this->assertNotNull($catering_order_record,"there shouljd be a catering order record");
        $this->assertEquals(10,$catering_order_record['number_of_people']);
        $this->assertEquals('business lunch',$catering_order_record['event']);
        $this->assertEquals('pickup',$catering_order_record['order_type']);
        $this->assertEquals('In Progress',$catering_order_record['status']);
        $this->assertEquals($timestamp_of_event,$catering_order_record['timestamp_of_event']);
        $this->assertEquals(date('Y-m-d',getTomorrowTwelveNoonTimeStampDenver()).' 14:00:00',$catering_order_record['date_tm_of_event']);

        $order = new Order($order_id);
        $this->assertFalse($order->isDeliveryOrder(),"It should not be a delivery order");
        $this->assertTrue($order->isCateringOrder(),"It should be a catering order");
        return $ucid;
    }

    /**
     * @depends testCreateCateringOrderPickup
     */
    function testOnlySeeCCAsPayemntTypeOnCheckout($ucid)
    {
        $base_order = CompleteOrder::getBaseOrderData($ucid);
        $user = logTestUserIn($base_order['user_id']);

        $request = createRequestObject("/apiv2/merchants/".$this->ids['merchant_id']."/catering",'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $menu = $resource->menu;

        $order_adapter = new OrderAdapter();
        $cart_data = $order_adapter->getCartArrayFromFullMenuWithOneModiferPerModifierGroup($menu,$this->ids['merchant_id'],"sum dum note",2);
        $cart_data['ucid'] = $ucid;

        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertNull($checkout_resource->error);
        $this->assertEquals($ucid,$checkout_resource->ucid,"cart should be the one that was created when the catering order was created");
        $payment_array = $checkout_resource->accepted_payment_types;
        $this->assertCount(1,$payment_array,"there should only be a CC payment type even though the merchant accepts cash");
        $this->assertEquals(getTomorrowTwelveNoonTimeStampDenver()+(2*3600),$checkout_resource->lead_times_array[0]);
//        $expected_time = date('Y-m-d H:i:s',getTomorrowTwelveNoonTimeStampDenver());
//        $actual_time = date('Y-m-d H:i:s',$checkout_resource->lead_times_array[0]);
//        $this->assertEquals($expected_time,$actual_time,"the only time in the lead times array should ahve been the time chosen when created");
       // $this->assertCount(1,$checkout_resource->lead_times_array);

        // check that tip minimum is working for catering
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,5.00,time());
        $this->assertNotNull($order_resource->error,"It should have gotten an error becuase tip minimum was not met");
        $order_amt = $checkout_resource->order_amt;
        $minimum_tip_string = '$'.number_format($order_amt * .1,2);
        $minimum_tip_error_text = str_replace('%%minimum_tip%%',$minimum_tip_string,CateringController::MINIMUM_TIP_NOT_MET_ERROR);
        $this->assertEquals($minimum_tip_error_text,$order_resource->error);
        $_SERVER['DO_NOT_RUN_CC'] = 'true';
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,15.00,time());
        $this->assertNull($order_resource->error);

        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000,"we have a valid order id");
        $catering_order_record = CateringOrdersAdapter::staticGetRecord(array("order_id"=>$order_id),'CateringOrdersAdapter');
        $this->assertEquals('Submitted',$catering_order_record['status']);

    }


    function testCateringActiveFlag()
    {
        $merchant_resource = createNewTestMerchantWithCatering($this->ids['menu_id'],$data);
        $merchant_id = $merchant_resource->merchant_id;

//        $mcir = $merchant_resource->merchant_catering_info_resource;
//        $mcir->min_lead_time_in_hours_from_open_time = 4;
//        $mcir->max_days_out = 2;
//        $message = 'Hello! This is the catering message';
//        $mcir->catering_message_to_user_on_create_order = $message;
//        $mcir->save();


        $sql = "UPDATE Merchant_Catering_Infos SET active = 'N'";
        $mci = new MerchantCateringInfosAdapter(getM());
        $result = $mci->_query($sql);


        $response = $this->makeRequest("http://127.0.0.1:".$this->api_port."/app2/apiv2/merchants",null,'GET');
        $this->assertEquals(200,$this->info['http_code']);
        $response_as_array = json_decode($response,true);
        $this->assertNull($response_as_array['error']);

        $data = $response_as_array['data'];
        foreach ($data['merchants'] as $merchant_catering_info) {
            $this->assertNotEquals("1",$merchant_catering_info['has_catering'],"There should not be any merchant that has catering turned on");
        }


        $user_resource = createNewUserWithCCNoCVV();
        //$user = logTestUserResourceIn($user_resource);

        $userpassword = $user_resource->email.':welcome';
        $response = $this->makeRequest("http://127.0.0.1:".$this->api_port."app2/apiv2/merchants/$merchant_id/cateringorderavailabletimes/pickup",$userpassword,'GET');
        $this->assertEquals(422,$this->info['http_code']);
        $response_as_array = json_decode($response,true);

        $this->assertNotNull($response_as_array['error']);
        $error_message = $response_as_array['error']['error'];

        $this->assertEquals(CateringController::CATERING_NOT_ACTIVE_FOR_THIS_MERCHANT,$error_message);
    }


    /*********  helper functions ***********/

    function getCateringOrderData($merchant_id)
    {
        $catering_data['number_of_people'] = 10;
        $catering_data['merchant_id'] = $merchant_id;
        $catering_data['event'] = 'business lunch';
        $catering_data['order_type'] = 'pickup';
        $catering_data['timestamp_of_event'] = getTomorrowTwelveNoonTimeStampDenver()+3600;
        $catering_data['contact_name'] = 'adam';
        $catering_data['contact_phone'] = '123 456 7890';
        $catering_data['notes'] = "Please make sure that there are plenty of napkins";
        return $catering_data;
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
        unset($this->info);
        $method = strtoupper($method);
        $curl = curl_init($url);
        if ($userpassword) {
            curl_setopt($curl, CURLOPT_USERPWD, $userpassword);
        }
        $external_id = $this->getExternalId();
        $headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:APIDispatchTest","NO_CC_CALL:true");
        if ($authentication_token = $data['splickit_authentication_token']) {
            $headers[] = "splickit_authentication_token:$authentication_token";
        }
        if ($data['headers']) {
            $headers = $data['headers'];
        }
        if ($method == 'POST') {
            $json = json_encode($data);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS,$json);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($json);
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

        $skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty("cateringskin","cateringbrand");
        $ids['skin_id'] = $skin_resource->skin_id;
        $ids['context'] = 'com.splickit.cateringskin';
        setContext('com.splickit.cateringskin');

        // create catering menu with single regular menu type
        $menu_id = createTestCateringMenuWithOneItem();
        $item_size_resources = CompleteMenu::getAllItemSizesAsResources($menu_id,0);
        $item_size_resource = $item_size_resources[0];
        $item_size_resource->price = 50.00;
        $item_size_resource->save();

        //now create a non catering menutype and item on the menu
        $menu_type_resource = createNewMenuType($menu_id, 'Test Menu Type 2', 'E');
        $size_resource = createNewSize($menu_type_resource->menu_type_id, 'Test Size 2');
        createItem($item_name, $size_resource->size_id, $menu_type_resource->menu_type_id);


        $ids['menu_id'] = $menu_id;

        $merchant_resource = createNewTestMerchantWithCatering($menu_id,$data);
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $ids['merchant_id'] = $merchant_resource->merchant_id;
        $payment_type_map_resouce = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);

        $non_catering_merchant_resource = createNewTestMerchant($menu_id);
        attachMerchantToSkin($non_catering_merchant_resource->merchant_id, $ids['skin_id']);
        $ids['non_catering_merchant_id'] = $non_catering_merchant_resource->merchant_id;


        $_SERVER['log_level'] = 5;
        $_SERVER['unit_test_ids'] = $ids;
        $tz = date_default_timezone_get();
    }

    static function tearDownAfterClass()
    {
        date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
        $suite = new PHPUnit_Framework_TestSuite( __CLASS__);
        PHPUnit_TextUI_TestRunner::run( $suite);
    }



}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    APIDispatchCateringOrderTest::main();
}

?>