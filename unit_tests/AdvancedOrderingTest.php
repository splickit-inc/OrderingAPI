<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class AdvancedOrderingTest extends PHPUnit_Framework_TestCase
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
        setContext('com.splickit.worldhq');
    }

    function tearDown()
    {
        //delete your instance
        unset($this->ids);
        unset($_SERVER['max_lead']);
    }

    function testRepackageWithAfterMidnightClose()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_resource->advanced_ordering = 'Y';
        $merchant_resource->lead_time = 15;
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        $sql = "UPDATE Hour SET close = '03:00:00' WHERE merchant_id = $merchant_id";
        $hour_adapter = new HourAdapter(getM());
        $hour_adapter->_query($sql);

        $mao_data = array("merchant_id"=>$merchant_id);
        $mao_data['max_days_out'] = 6;
        $maoia = new MerchantAdvancedOrderingInfoAdapter($m);
        $maoi_resource = Resource::createByData($maoia,$mao_data);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $this->assertNotNull($checkout_resource->lead_times_by_day_array,'it should have the lead times by day array');
        $lead_times_by_day_array = $checkout_resource->lead_times_by_day_array;
        foreach ($lead_times_by_day_array as $day=>$day_times) {
            $last_ts_of_day = array_pop($day_times);
            $last_time_day = date('h:i',$last_ts_of_day);
            $this->assertEquals('03:00',$last_time_day,"Wrong last time for $day");
        }


    }

    function testRepackageOfAdvancedTimesOnCheckout()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_resource->advanced_ordering = 'Y';
        $merchant_resource->lead_time = 15;
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        $mao_data = array("merchant_id"=>$merchant_id);
        $mao_data['max_days_out'] = 6;
        $maoia = new MerchantAdvancedOrderingInfoAdapter($m);
        $maoi_resource = Resource::createByData($maoia,$mao_data);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $this->assertNotNull($checkout_resource->lead_times_by_day_array,'it should have the lead times by day array');
        $lead_times_by_day_array = $checkout_resource->lead_times_by_day_array;
        $this->assertCount(7,$lead_times_by_day_array,'It Should have 7 days of times');

    }

    function testPlaceAdvancedOrder()
    {

//        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
//        $merchant_id = $merchant_resource->merchant_id;
//        attachMerchantToSkin($merchant_resource->merchant_id, $this->ids['skin_id']);
        $merchant_id = $this->ids['merchant_id'];

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $eight_days_from_now = getTomorrowTwelveNoonTimeStampDenver() + (8*24*3600);
        $checkout_resource->lead_times_array = [$eight_days_from_now];
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $order_messages = MerchantMessageHistoryAdapter::getAllOrderMessages($order_id);
        $order_messages_hash = createHashmapFromArrayOfResourcesByFieldName($order_messages,'message_format');
        $pickup_date = date('m/d',$eight_days_from_now);
        foreach ($order_messages_hash as $order_message) {
            if ($order_message->message_format == 'Econf') {
                continue;
            }
            $message_controller = ControllerFactory::generateFromMessageResource($order_message);
            $message_to_send = $message_controller->prepMessageForSending($order_message);
            myerror_log($message_to_send->message_text);
            $message_text = $message_to_send->message_text;
            $this->assertContains($pickup_date,$message_text,'It should have the pickup date');
        }
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
       // $mysqli->begin_transaction(); ;


        $skin_resource = createWorldHqSkin();
        $ids['skin_id'] = $skin_resource->skin_id;

        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(3);
        $ids['menu_id'] = $menu_id;

        $merchant_resource = createNewTestMerchant($menu_id);
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $ids['merchant_id'] = $merchant_resource->merchant_id;

        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_resource->merchant_id,'HUC','ChinaIP','O');
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_resource->merchant_id,'FUC','1234567890','O');

        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);
//        $billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);
//        $merchant_payment_type_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);

        $user_resource = createNewUser(array("flags" => "1C20000001"));
        $ids['user_id'] = $user_resource->user_id;

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
    AdvancedOrderingTest::main();
}

?>