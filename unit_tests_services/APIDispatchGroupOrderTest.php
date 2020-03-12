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

class ApiDispatchGroupOrderTest extends PHPUnit_Framework_TestCase
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
        setContext("com.splickit.vtwoapi");
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



    // "https://worldhq.splickit.com/merchants/" . $this->merchant_id . "?menu_type=delivery&group_order_token=$group_order_token";

    function testCreateGroupOrderWithDeliveryLocation()
    {
        $user_resource = createNewUser();
        $user_resource->first_name = 'Tom';
        $user_resource->last_name = 'Zombie';
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);

        $json = '{"user_id":"' . $user['user_id'] . '","name":"","address1":"1045 Pine Street","address2":"","city":"Boulder","state":"CO","zip":"80302","phone_no":"1234567890","lat":40.019785,"lng":-105.282509}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "Application/json";
        $request->_parseRequestBody();
        $request->method = 'POST';
        $request->url = "/users/" . $user['uuid'] . "/userdeliverylocation";
        $user_controller = new UserController($mt, $user, $request, 5);
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;
        $this->user_addr_id = $user_address_id;



        $data = array("merchant_id" => $this->ids['merchant_id'], "note" => "sumdumnote", "merchant_menu_type" => 'Delivery', "participant_emails" => '', "user_addr_id" => $user_address_id, "group_order_type" => 2, "submit_at_ts" => (getTomorrowTwelveNoonTimeStampDenver() + 900));
        $response = $this->makeRequest("http://127.0.0.1:".$this->api_port."/app2/apiv2/grouporders",$user['email'].':welcome','POST',$data);
        $response_as_array = json_decode($response,true);
        $group_order_token = $response_as_array['data']['group_order_token'];
        $cart = CartsAdapter::staticGetRecordByPrimaryKey($group_order_token, "CartsAdapter");
        $order_id = $cart['order_id'];
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id, $m);
        $this->assertEquals('D', $complete_order['order_type'], "shoudl have created a delivery dummy order row");
        $this->assertEquals($user_address_id, $complete_order['user_delivery_location_id'], "Should have the user addr id on the order");
        //$this->assertEquals(5.55, $complete_order['delivery_amt'], "should have a delivery price on the dummy order");
        $this->assertEquals(0.00, $complete_order['delivery_amt'], "should NOT have a delivery price on the dummy order");
        $group_order_adapter = new GroupOrderAdapter($mimetypes);
        $group_order_record = $group_order_adapter->getRecordFromPrimaryKey($response_as_array['data']['group_order_id']);
        return $group_order_record;
    }


    /*********  helper functions ***********/


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
        ini_set('max_execution_time',0);
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        ini_set('max_execution_time',300);

        $skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty("vtwoapi","vtwoapi",252, 101);
        $ids['skin_id'] = $skin_resource->skin_id;

        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(5);
        $ids['menu_id'] = $menu_id;
        $menu_status_key = rand(11111111,99999999);
        $menu_resource = SplickitController::getResourceFromId($menu_id,'Menu');
        $menu_resource->last_menu_change = $menu_status_key;
        $menu_resource->save();
        $ids['menu_status_key'] = $menu_status_key;

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 3);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_resource->group_ordering_on = 1;
        $merchant_resource->save();
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $ids['merchant_id'] = $merchant_resource->merchant_id;

        $merchant_id_key = generateCode(10);
        $merchant_id_number = generateCode(5);
        $data['vio_selected_server'] = 'sage';
        $data['vio_merchant_id'] = $merchant_resource->merchant_id;
        $data['name'] = "Test Billing Entity";
        $data['description'] = 'An entity to test with';
        $data['merchant_id_key'] = $merchant_id_key;
        $data['merchant_id_number'] = $merchant_id_number;
        $data['identifier'] = $merchant_resource->alphanumeric_id;
        $data['brand_id'] = $merchant_resource->brand_id;

        $card_gateway_controller = new CardGatewayController($mt, $u, $r);
        $resource = $card_gateway_controller->createPaymentGateway($data);
        $payment_type_map_resouce = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);
        $ids['merchant_payment_type_map_id_for_cash'] = $payment_type_map_resouce->id;
        $user_resource = createNewUser(array("flags"=>"1C20000001"));
        $ids['user_id'] = $user_resource->user_id;
        $ids['user'] = $user_resource->getDataFieldsReally();
        $_SERVER['log_level'] = 5;
        $_SERVER['unit_test_ids'] = $ids;
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
    ApiDispatchGroupOrderTest::main();
}

?>