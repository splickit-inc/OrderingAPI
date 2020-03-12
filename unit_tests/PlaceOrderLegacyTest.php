<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class PlaceOrderLegacyTest extends PHPUnit_Framework_TestCase
{
    var $ids;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
        setContext("com.splickit.legtestskin");
        $this->ids = $_SERVER['unit_test_ids'];
    }

    function tearDown()
    {
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->stamp);
    }


    function testPlaceOrderType1JsonFormat()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($this->ids['merchant_id']);
        $order_data['actual_pickup_time'] = getTomorrowTwelveNoonTimeStampDenver()+(20*60);
        $order_data['merchant_payment_type_map_id'] = $this->ids['merchant_payment_type_map_id'];
        $order_data['lead_time'] = 20;
        $order_data['loyalty_number'] = str_ireplace(' ','',$user_resource->contact_no);
        $json_wrapper['jsonVal'] = $order_data;
        $request = createRequestObject("/phone/placeorder/","POST",json_encode($json_wrapper));
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $order_resource = $place_order_controller->placeOrderFromRequest();
        $this->assertNull($order_resource->error);
        $this->assertNotNull($order_resource->order_id);
        $this->assertNotNull($order_resource->user_message);
        $this->assertNotNull($order_resource->user_message_title);
        $this->assertNotNull($order_resource->payment_service_used);
        $this->assertNotNull($order_resource->order_summary);
        $this->assertNotNull($order_resource->loyalty_message);
        $this->assertNotNull($order_resource->loyalty_earned_label);
        $this->assertNotNull($order_resource->loyalty_earned_message);
        $this->assertNotNull($order_resource->loyalty_balance_label);
        $this->assertNotNull($order_resource->loyalty_balance_message);
    }

    function testPlaceOrderWithPromoCode()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($this->ids['merchant_id'],'pickup','the note',4);
        $order_data['promo_code'] = 'type1promo';
        $order_data['actual_pickup_time'] = getTomorrowTwelveNoonTimeStampDenver()+(20*60);
        $order_data['merchant_payment_type_map_id'] = $this->ids['merchant_payment_type_map_id'];
        $order_data['lead_time'] = 20;
        $order_data['loyalty_number'] = str_ireplace(' ','',$user_resource->contact_no);
        $json_wrapper['jsonVal'] = $order_data;
        $request = createRequestObject("/phone/placeorder/","POST",json_encode($json_wrapper));
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $order_resource = $place_order_controller->placeOrderFromRequest();
        $this->assertNull($order_resource->error);
        $expected_promo_amt = round(.25 * $order_resource->order_amt,2);
        $this->assertEquals(-$expected_promo_amt,$order_resource->promo_amt,"Promo amt shoudl be set.");
        $this->assertEquals(round($order_resource->item_tax_amt + $order_resource->promo_tax_amt,2),$order_resource->total_tax_amt,"Total tax shoudl be the addtiiona of both taxes");
        $expected_grand_total = $order_resource->order_amt+$order_resource->promo_amt+$order_resource->promo_tax_amt+$order_resource->item_tax_amt;
        $this->assertEquals($expected_grand_total,$order_resource->grand_total,"it should equal the expected grand total");
    }

    function testPlaceOrderType2JsonFormat()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($this->ids['merchant_id']);
        $order_data['merchant_payment_type_map_id'] = $this->ids['merchant_payment_type_map_id'];
        $order_data['lead_time'] = "20";
        $order_data['total_points_used'] = "0";
        $order_data['delivery'] = 'no';
        $order_data['tip'] = "1.50";
        $request = createRequestObject("/phone/placeorder/","POST",json_encode($order_data));
        $place_order_controller = new PlaceOrderController($mt, $user, $request,5);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $order_resource = $place_order_controller->placeOrderFromRequest();
        $this->assertNull($order_resource->error);
        $this->assertNotNull($order_resource->order_id);
        $this->assertNotNull($order_resource->user_message);
        $this->assertNotNull($order_resource->user_message_title);
        $this->assertNotNull($order_resource->payment_service_used);
        $this->assertNotNull($order_resource->order_summary);
        $this->assertNotNull($order_resource->loyalty_message);
        $this->assertNotNull($order_resource->loyalty_earned_label);
        $this->assertNotNull($order_resource->loyalty_earned_message);
        $this->assertNotNull($order_resource->loyalty_balance_label);
        $this->assertNotNull($order_resource->loyalty_balance_message);
    }

    function testPlaceOrderType3JsonFormat()
    {
        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        $mdia = new MerchantDeliveryPriceDistanceAdapter($mimetypes);
        $mdia_resource = $mdia->getExactResourceFromData(array("merchant_id"=>$merchant_id));
        $mdia_resource->distance_up_to = 100;
        $mdia_resource->price = 1.00;
        $mdia_resource->save();

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
        $request = createRequestObject("/users/" . $user['uuid'] . "/userdeliverylocation","POST",$json,'application/json');
        $user_controller = new UserController($mt, $user, $request, 5);

        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;

        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id);
        $order_data['merchant_payment_type_map_id'] = $this->ids['merchant_payment_type_map_id'];
        $order_data['lead_time'] = 0;
        $order_data['delivery'] = 'yes';
        $order_data['delivery_time'] = 'As Soon As Possible';
        $order_data['user_addr_id'] = $user_address_id;
        $order_data['loyalty_number'] = str_ireplace(' ','',$user_resource->contact_no);
        $order_data['total_points_used'] = "0";
        $json_wrapper['jsonVal'] = $order_data;
        $request = createRequestObject("/phone/placeorder/","POST",json_encode($json_wrapper));
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $order_resource = $place_order_controller->placeOrderFromRequest();
        $this->assertNull($order_resource->error);
        $this->assertNotNull($order_resource->order_id);
        $this->assertNotNull($order_resource->user_message);
        $this->assertNotNull($order_resource->user_message_title);
        $this->assertNotNull($order_resource->payment_service_used);
        $this->assertNotNull($order_resource->order_summary);
        $this->assertNotNull($order_resource->loyalty_message);
        $this->assertNotNull($order_resource->loyalty_earned_label);
        $this->assertNotNull($order_resource->loyalty_earned_message);
        $this->assertNotNull($order_resource->loyalty_balance_label);
        $this->assertNotNull($order_resource->loyalty_balance_message);
        $this->assertNotNull($order_resource->delivery_tax_amount);
        $order = new Order($order_resource->order_id);
        $this->assertEquals('As Soon As Possible',$order->get('requested_delivery_time'));
    }



    static function setUpBeforeClass()
    {
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['default_tz'] = $tz;
        date_default_timezone_set("America/Denver");
        ini_set('max_execution_time',300);
        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;

        $skin_resource = getOrCreateSkinAndBrandIfNecessary("legtestskin", "legtestbrand", $skin_id, $brand_id);
        $brand_id = $skin_resource->brand_id;
        $brand_resource = Resource::find(new BrandAdapter($mimetypes),"$brand_id");
        $brand_resource->loyalty = 'Y';
        $brand_resource->save();

        $blr_data['brand_id'] = $brand_id;
        $blr_data['loyalty_type'] = 'splickit_earn';
        $brand_loyalty_rules_resource = Resource::factory(new BrandLoyaltyRulesAdapter($m),$blr_data);
        $brand_loyalty_rules_resource->save();
        $ids['skin_id'] = $skin_resource->skin_id;

        $menu_id = createTestMenuWithNnumberOfItems(5);
        $ids['menu_id'] = $menu_id;

        $simple_menu_id = createTestMenuWithOneItem("item_one");
        $ids['simple_menu_id'] = $simple_menu_id;

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);

        $merchant_resource = createNewTestMerchant($menu_id);
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $ids['merchant_id'] = $merchant_resource->merchant_id;
        $ids['merchant_resource'] = $merchant_resource;
        $ids['merchant_payment_type_map_id'] = $merchant_resource->merchant_payment_type_map_id;

        $merchant_resource2 = createNewTestMerchant($simple_menu_id);
        attachMerchantToSkin($merchant_resource2->merchant_id, $ids['skin_id']);
        $ids['simple_merchant_id'] = $merchant_resource2->merchant_id;

        $promo_adapter = new PromoAdapter();
        $ids['promo_id_type_1'] = 201;
        $sql = "INSERT INTO `Promo` VALUES(201, 'The Type1 Promo', 'Get 25% off', 1, 'Y', 'N', 0, 2, 'N', 'N','all', '2010-01-01', '2020-01-01', 1, 0, 0, 0.00, 0, 0.00, 'Y', 'N', 0,$brand_id, NOW(), NOW(), 'N')";
        $promo_adapter->_query($sql);
        $sql = "INSERT INTO `Promo_Merchant_Map` VALUES(null, 201, $merchant_resource->merchant_id, '2013-10-05', '2020-01-01', NULL, now())";
        $pmm_resource = Resource::createByData(new PromoMerchantMapAdapter($mimetypes), array("merchant_id"=>$merchant_resource->merchant_id,"promo_id"=>201));
        $ids['promo_merchant_map_id_type_1'] = $pmm_resource->map_id;
        $sql = "INSERT INTO `Promo_Message_Map` VALUES(null, 201, 'Congratulations! You''re getting a 25% off your order!', NULL, NULL, NULL, NULL, now())";
        $promo_adapter->_query($sql);
        $sql = "INSERT INTO `Promo_Type1_Amt_Map` VALUES(null, 201, 1.00, 0.00, 25,50.00, NOW())";
        $promo_adapter->_query($sql);


        Resource::createByData(new PromoKeyWordMapAdapter(), array("promo_id"=>201,"promo_key_word"=>"type1promo","brand_id"=>$brand_id));




        $_SERVER['log_level'] = 5;
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
    PlaceOrderLegacyTest::main();
}

?>