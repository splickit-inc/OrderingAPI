<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class DeliveryThrottlingTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $code = generateCode(7);
        $_SERVER['STAMP'] = __CLASS__ . '-' . $code;
        $_SERVER['RAW_STAMP'] = $code;
        $this->ids = $_SERVER['unit_test_ids'];

    }

    function tearDown()
    {
        //delete your instance
        unset($this->ids);
        unset($_SERVER['max_lead']);
    }

    function testAlwaysLandOn5MinuteInterval()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_delivery_location = $this->createUserDeliveryAddress($user);
        $user_address_id = $user_delivery_location->user_addr_id;
        $merchant_id = $this->ids['merchant_id'];

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'delivery');
        $cart_data['user_addr_id'] = $user_address_id;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver()+75);
        $lead_times = $checkout_resource->lead_times_array;

        $expected_time = date("H:i",getTomorrowTwelveNoonTimeStampDenver() + (50*60));
        $this->assertEquals($expected_time,date("H:i",$lead_times[0]));
    }


    function testSetUpDeliveryThrottlingTest()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_delivery_location = $this->createUserDeliveryAddress($user);
        $user_address_id = $user_delivery_location->user_addr_id;
        $merchant_id = $this->ids['merchant_id'];

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'delivery');
        $cart_data['user_addr_id'] = $user_address_id;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);
        $this->createOrderRecords($order_resource->order_id,10, 5);

        $order_records = OrderAdapter::staticGetRecords(["merchant_id"=>$merchant_id],'OrderAdapter');

        //now create a new order
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'delivery','the note',3);
        $cart_data['user_addr_id'] = $user_address_id;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        return $checkout_resource;


    }

    /**
     * @depends testSetUpDeliveryThrottlingTest
     */
    function testFirstTime($checkout_resource)
    {
        $first_time = date("H:i:s",$checkout_resource->lead_times_array[0]);
        $expected_first_time = "13:15:00";
        $this->assertEquals($expected_first_time,$first_time);
    }

    /**
     * @depends testSetUpDeliveryThrottlingTest
     */
    function testSecondTime($checkout_resource)
    {
        $second_time = date("H:i:s",$checkout_resource->lead_times_array[1]);
        $expected_second_time = "13:45:00";
        $this->assertEquals($expected_second_time,$second_time);
    }

    /**
     * @depends testSetUpDeliveryThrottlingTest
     */
    function testThirdTime($checkout_resource)
    {
        $third = date("H:i:s",$checkout_resource->lead_times_array[2]);
        $expected_third = "13:50:00";
        $this->assertEquals($expected_third,$third);
    }

    function testSetUpDeliveryThrottlingTest10MinBuffer()
    {
        $merchant_id = $this->ids['merchant_id'];
        $sql = "UPDATE Orders SET logical_delete = 'Y' WHERE merchant_id = $merchant_id";
        $order_adapter = new OrderAdapter(getM());
        $order_adapter->_query($sql);

        $prep_resource_id = $this->ids['prep_resource_id'];
        $prep_resource = Resource::find(new MerchantPreptimeInfoAdapter(getM()),$prep_resource_id);
        $prep_resource->delivery_order_buffer_in_minutes = 10;
        $prep_resource->save();

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_delivery_location = $this->createUserDeliveryAddress($user);
        $user_address_id = $user_delivery_location->user_addr_id;




        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'delivery');
        $cart_data['user_addr_id'] = $user_address_id;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);
        $this->createOrderRecords($order_resource->order_id,10,10);

        $order_records = OrderAdapter::staticGetRecords(["merchant_id"=>$merchant_id],'OrderAdapter');

        //now create a new order
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'delivery','the note',3);
        $cart_data['user_addr_id'] = $user_address_id;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        return $checkout_resource;


    }

    /**
     * @depends testSetUpDeliveryThrottlingTest10MinBuffer
     */
    function testFirstTimefor10($checkout_resource)
    {
        $first_time = date("H:i:s",$checkout_resource->lead_times_array[0]);
        $expected_first_time = "14:50:00";
        $this->assertEquals($expected_first_time,$first_time);
    }

    /**
     * @depends testSetUpDeliveryThrottlingTest10MinBuffer
     */
    function testSecondTimefor10($checkout_resource)
    {
        $second_time = date("H:i:s",$checkout_resource->lead_times_array[1]);
        $expected_second_time = "15:50:00";
        $this->assertEquals($expected_second_time,$second_time);
    }

    /**
     * @depends testSetUpDeliveryThrottlingTest10MinBuffer
     */
    function testThirdTimefor10($checkout_resource)
    {
        $third = date("H:i:s",$checkout_resource->lead_times_array[2]);
        $expected_third = "16:00:00";
        $this->assertEquals($expected_third,$third);
    }


    function testDeliveryThrottlingOff()
    {
        $merchant_id = $this->ids['merchant_id'];
        $sql = "UPDATE Orders SET logical_delete = 'Y' WHERE merchant_id = $merchant_id";
        $order_adapter = new OrderAdapter(getM());
        $order_adapter->_query($sql);

        $prep_resource_id = $this->ids['prep_resource_id'];
        $prep_resource = Resource::find(new MerchantPreptimeInfoAdapter(getM()),$prep_resource_id);
        $prep_resource->delivery_order_buffer_in_minutes = 5;
        $prep_resource->delivery_throttling_on = 'N';
        $prep_resource->save();

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_delivery_location = $this->createUserDeliveryAddress($user);
        $user_address_id = $user_delivery_location->user_addr_id;

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'delivery');
        $cart_data['user_addr_id'] = $user_address_id;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);
        $this->createOrderRecords($order_resource->order_id,10,5);

        $order_records = OrderAdapter::staticGetRecords(["merchant_id"=>$merchant_id],'OrderAdapter');

        //now create a new order
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'delivery','the note',3);
        $cart_data['user_addr_id'] = $user_address_id;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);

        $first_time = date("H:i:s",$checkout_resource->lead_times_array[0]);
        $expected_first_time = "12:45:00";
        $this->assertEquals($expected_first_time,$first_time);

        $second_time = date("H:i:s",$checkout_resource->lead_times_array[1]);
        $expected_second_time = "12:50:00";
        $this->assertEquals($expected_second_time,$second_time);

    }


    function createOrderRecords($order_id,$number_of_orders,$increment_in_minutes)
    {
        $increment_in_seconds = $increment_in_minutes * 60;
        $order_resource = Resource::find(new OrderAdapter(getM()),$order_id);
        for($i=0;$i<$number_of_orders;$i++) {
            $order_resource->order_id = null;
            $order_resource->ucid = generateUUID();
            $order_resource->_exists = false;
            $pickup_dt_tm = strtotime($order_resource->pickup_dt_tm);
            if ($i==5) {
                $inc = 2*$increment_in_seconds;
            } else {
                $inc = $increment_in_seconds;
            }
            $order_resource->pickup_dt_tm = date('Y-m-d H:i:s',$pickup_dt_tm + $inc);
            $order_resource->save();
        }
    }

    function createUserDeliveryAddress($user)
    {
        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"1045 Pine Street","address2":"","city":"boulder","state":"co","zip":"80302","phone_no":"9709262121","lat":40.0197891,"lng":-105.284703}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "Application/json";
        $request->_parseRequestBody();
        $request->method = 'POST';
        $request->url = "/users/" . $user['uuid'] . "/userdeliverylocation";
        $user_controller = new UserController($mt, $user, $request, 5);
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error,"Error thrown creating the user delivery location");
        return $response;
    }

    function placeOrder($submit_order_data, $ucid, $time)
    {
        $json_encoded_data = json_encode($submit_order_data);
        $request = createRequestObject("/apiv2/orders/$ucid", "POST", $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), getAuthenticatedUser(), $request);
        $place_order_controller->setCurrentTime($time);
        $order_resource = $place_order_controller->processV2Request();
        return $order_resource;
    }

    static function setUpBeforeClass()
    {
        ini_set('max_execution_time', 0);
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;


        $skin_resource = createWorldHqSkin();
        $ids['skin_id'] = $skin_resource->skin_id;

        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(3);
        $ids['menu_id'] = $menu_id;

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 3);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);

//*
        $sides_modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1, 'Sides', 'S');
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $sides_modifier_group_resource->modifier_group_id, 0);

        $mealdeal_modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1, 'Meal Deal', 'I');
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $mealdeal_modifier_group_resource->modifier_group_id, 0);
//*/
        $merchant_resource = createNewTestMerchantDelivery($menu_id);
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $ids['merchant_id'] = $merchant_resource->merchant_id;

        //set throttling for merchant
        $prep_resource = Resource::createByData(new MerchantPreptimeInfoAdapter(getM()), array("merchant_id"=>$merchant_resource->merchant_id,"entree_preptime_seconds"=>120));
        $ids['prep_resource_id'] = $prep_resource->merchant_preptime_info_id;

        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, null);
//        $billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);
//        $merchant_payment_type_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);


        $_SERVER['log_level'] = 5;
        $_SERVER['unit_test_ids'] = $ids;
    }

    static function tearDownAfterClass()
    {
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->rollback();
        date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main()
    {
        $suite = new PHPUnit_Framework_TestSuite(__CLASS__);
        PHPUnit_TextUI_TestRunner::run($suite);
    }

}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    DeliveryThrottlingTest::main();
}

?>