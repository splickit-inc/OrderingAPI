<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class ApiCartStatusTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $code = generateCode(7);
        $_SERVER['STAMP'] = __CLASS__.'-'.$code;
        $_SERVER['RAW_STAMP'] = $code;
        $this->ids = $_SERVER['unit_test_ids'];

    }

    function tearDown()
    {
        //delete your instance
        unset($this->ids);
        unset($_SERVER['max_lead']);
    }

    function testUserIdMisMatchError()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'pickup','sum dum note');
        $order_data['items'][0]['note'] = 'item 1 note';
        $order_data['items'][0]['external_detail_id'] = '123456';
        $url = "/apiv2/cart";
        $request = createRequestObject($url,"POST",json_encode($order_data),'application/json');
        $placeorder_controller = new PlaceOrderController($mt, $user, $request);
        $placeorder_controller->setCurrentTime(getTodayTwelveNoonTimeStampDenver());
        $cart_resource = $placeorder_controller->processV2Request();

        $complete_order_1 = CompleteOrder::staticGetCompleteOrder($cart_resource->oid_test_only);
        $this->assertNull($cart_resource->error);
        $ucid = $cart_resource->ucid;


        $user_resource2 = createNewUserWithCCNoCVV();
        $user2 = logTestUserResourceIn($user_resource2);
        $order_data2 = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'pickup','sum dum note');
        $order_data2['items'][0]['note'] = 'item 1 note';
        $order_data2['items'][0]['external_detail_id'] = '567890';
        $order_data2['items'][] = $cart_resource->order_summary['order_data_as_ids_for_cart_update']['items'][0];

        $url = "/apiv2/cart/$ucid/checkout";
        $request = createRequestObject($url,"POST",json_encode($order_data2),'application/json');
        $placeorder_controller = new PlaceOrderController($mt, $user2, $request);
        $placeorder_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $placeorder_controller->processV2Request();
        $this->assertNull($checkout_resource->error);
        $complete_order_2 = CompleteOrder::staticGetCompleteOrder($checkout_resource->oid_test_only);
        $this->assertCount(2,$complete_order_2['order_details']);

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user2,null,0.00);
        $this->assertNull($order_resource->error);
        $this->assertCount(2,$order_resource->order_summary['cart_items'],'It should contain 2 items');
        $this->assertEquals(4.00,$order_resource->order_amt);
    }

    function testPromoType1WithRemovedItemsAfterPromoIsUsed()
    {
        setContext("com.splickit.worldhq");
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'pickup','sum dum note',6);

        $json_encoded_data = json_encode($order_data);
        $request = createRequestObject('/app2/apiv2/cart/checkout','POST',$json_encoded_data);
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertEquals(6,$checkout_resource->order_qty,"should be 6 items");
        $this->assertNull($checkout_resource->error);
        $ucid = $checkout_resource->ucid;

        // now enter promo
        $request = createRequestObject("/app2/apiv2/cart/$ucid/checkout?promo_code=type1promo","GET");
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $promo_resource_result = $place_order_controller->processV2Request();
        $this->assertNull($promo_resource_result->error);
        $this->assertEquals("-5.00", $promo_resource_result->promo_amt);
        $this->assertEquals("Congratulations! You're getting a 50% off your order!", $promo_resource_result->user_message);
        $this->assertEquals($checkout_resource->oid_test_only,$promo_resource_result->oid_test_only,"order id's should be the same for before and after promo applied.");
        $order_data_after_promo = CompleteOrder::getBaseOrderData($checkout_resource->oid_test_only);
        $this->assertEquals(strtolower('type1promo'),strtolower($order_data_after_promo['promo_code']));
        $this->assertEquals(201,$order_data_after_promo['promo_id']);


        //now remove items from the cart
        $order_data = $promo_resource_result->order_summary['order_data_as_ids_for_cart_update'];
        $second_item = $order_data['items'][1];
        $order_data['items'][0]['status'] = 'deleted';
        $order_data['items'][1]['status'] = 'deleted';
        $checkout_data_resource = $this->callCheckoutWithItemsAndCart($order_data,$ucid);
        $this->assertNull($checkout_data_resource->error);
        $this->assertEquals(4,$checkout_data_resource->order_qty,"should be 4 items");
        $this->assertEquals("-3.50", $checkout_data_resource->promo_amt,"promo should have been reduced");
        $this->assertEquals("-0.35", $checkout_data_resource->promo_tax_amt);

        $checkout_data_resource->accepted_payment_types = array($checkout_data_resource->accepted_payment_types[1]);
        $place_order_resource = placeOrderFromCheckoutResource($checkout_data_resource,$user,$merchant_id,1.00);
        $this->assertNull($place_order_resource->error);
        $order_id = $place_order_resource->order_id;

        //validate amounts
        $this->assertEquals(7.00,$place_order_resource->order_amt);
        $this->assertEquals(-3.50,$place_order_resource->promo_amt);
        $this->assertEquals(4.85,$place_order_resource->grand_total);
        $this->assertEquals(3.85,$place_order_resource->grand_total_to_merchant);


//
//
//        $json_encoded_data = json_encode($order_data);
//        $request = new Request();
//        $request->url = "/app2/apiv2/cart/$ucid";
//        $request->method = "post";
//        $request->body = $json_encoded_data;
//        $request->mimetype = 'application/json';
//        $request->_parseRequestBody();
//        $place_order_controller = new PlaceOrderController($mt, $user, $request);
//        $new_cart_resource = $place_order_controller->processV2Request();
//        $this->assertNull($new_cart_resource->error);
//        $this->assertEquals("Congratulations! You're getting $3.00 off your order!", $new_cart_resource->user_message);
//
//        // should promo stuff get updated on add to cart or only on checkout?????????
//        $new_cart_order_resource = SplickitController::getResourceFromId($new_cart_resource->oid_test_only, 'Order');
//        $complete_order = CompleteOrder::staticGetCompleteOrder($new_cart_resource->oid_test_only);
//        $this->assertEquals($order_data_after_promo['order_id'],$new_cart_order_resource->order_id,"order id's should have stayed the same");
//        $this->assertEquals(strtolower($duplicate_promo_key_word),strtolower($new_cart_order_resource->promo_code));
//        $this->assertEquals(-3.00,$new_cart_order_resource->promo_amt);
//
//
//        $request = new Request();
//        $request->url = "/apiv2/cart/$ucid/checkout";
//        $request->method = "get";
//        $request->mimetype = 'application/json';
//
//        $placeorder_controller = new PlaceOrderController($mt, $user, $request);
//        $placeorder_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
//        $new_checkout_data_resource = $placeorder_controller->processV2Request();
//        $this->assertNull($new_checkout_data_resource->error);
//        $this->assertEquals("-3.00", $new_checkout_data_resource->promo_amt);
//        $this->assertEquals("3.00",$new_checkout_data_resource->discount_amt);
//        //$this->assertEquals("Congratulations! You're getting $3.00 off your order!", $new_checkout_data_resource->user_message);
//
//        // now place the order
//        $order_data = array();
//        $order_data['merchant_id'] = $this->ids['merchant_id2'];
//        $order_data['note'] = "the new cart note";
//        $order_data['user_id'] = $user['user_id'];
//        $order_data['cart_ucid'] = $new_cart_resource->ucid;
//        $order_data['tip'] = (rand(100, 1000))/100;
//        $payment_array = $new_checkout_data_resource->accepted_payment_types;
//        $order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
//        $lead_times_array = $new_checkout_data_resource->lead_times_array;
//        $order_data['actual_pickup_time'] = $lead_times_array[0];
//        // this should be ignored;
//        $order_data['lead_time'] = 1000000;
//
//        $json_encoded_data = json_encode($order_data);
//        $request = new Request();
//        $request->url = '/apiv2/orders';
//        $request->method = "post";
//        $request->body = $json_encoded_data;
//        $request->mimetype = 'application/json';
//        $request->_parseRequestBody();
//        $place_order_controller = new PlaceOrderController($mt, $user, $request);
//        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
//        $order_resource = $place_order_controller->processV2Request();
//        $this->assertNull($order_resource->error);
//        $order_id = $order_resource->order_id;
//        $this->assertTrue($order_id > 1000,"should have created a valid order id");
//        $this->assertEquals(-3.00,$order_resource->promo_amt);
//


    }


    function callCheckoutWithItemsAndCart($order_data,$ucid)
    {
        $json_encoded_data = json_encode($order_data);
//        $request = new Request();

        $url = $ucid == null ? '/app2/apiv2/cart/checkout' : "/app2/apiv2/cart/$ucid/checkout";
        $request = createRequestObject($url,'POST',$json_encoded_data,'application/json');
        $place_order_controller = new PlaceOrderController($mt, getLoggedInUser(), $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_data_resource = $place_order_controller->processV2Request();
        return $checkout_data_resource;
    }

    function testCreateRawOrderDataWithStatus()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'pickup','sum dum note');
        $order_data['items'][0]['note'] = 'item 1 note';
        $order_data['items'][0]['external_detail_id'] = '123456';
        $url = "/apiv2/cart";
        $request = createRequestObject($url,"POST",json_encode($order_data),'application/json');
        $placeorder_controller = new PlaceOrderController($mt, $user, $request);
        $placeorder_controller->setCurrentTime(getTodayTwelveNoonTimeStampDenver());
        $cart_resource = $placeorder_controller->processV2Request();
        $this->assertNull($cart_resource->error);
        $complete_order = CompleteOrder::staticGetCompleteOrder($cart_resource->oid_test_only);
        $this->assertEquals('123456',$complete_order['order_details'][0]['external_detail_id'],"it should have saved the external detail id");

        $complete_order_class = new CompleteOrder($m);
        $recreated_order_data = $complete_order_class->createOrderDataAsIdsWithStatusField($complete_order_class->getCompleteOrder($cart_resource->oid_test_only,$m));
        $recreated_order_data_items = $recreated_order_data['items'];
        $this->assertCount(1,$recreated_order_data_items);
        $this->assertEquals($order_data['items'][0]['item_id'],$recreated_order_data_items[0]['item_id'],'It should have the same item_id');
        $this->assertEquals($order_data['items'][0]['size_id'],$recreated_order_data_items[0]['size_id'],'It should have the same size_id');
        $this->assertEquals($order_data['items'][0]['quantity'],$recreated_order_data_items[0]['quantity'],'It should have the same quantity');
        $this->assertEquals('item 1 note',$recreated_order_data_items[0]['note'],'It should have the same note');
        $this->assertEquals($order_data['items'][0]['mods'],$recreated_order_data_items[0]['mods'],'It should have the same mods array');
        $this->assertEquals('saved',$recreated_order_data_items[0]['status'],'It should have the order status section');
        $this->assertTrue(isset($recreated_order_data_items[0]['order_detail_id']),'It should have the order detail section');
        $this->assertEquals('123456',$recreated_order_data_items[0]['external_detail_id'],'It should have the external_detail section');
    }

    function testAddToCartWithSessionIdsMaintained()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'pickup','sum dum note',3);
        foreach ($order_data['items'] as &$item) {
            $item['order_detail_id'] = null;
            $item['status'] = 'new';
        }
        $order_data['items'][0]['external_detail_id'] = '111111';
        $order_data['items'][0]['note'] = 'note 1';
        $order_data['items'][1]['external_detail_id'] = '222222';
        $order_data['items'][1]['note'] = 'note 2';
        $order_data['items'][2]['external_detail_id'] = '333333';
        $order_data['items'][2]['note'] = 'note 3';
        $checkout_data_resource = $this->callCheckoutWithItemsAndCart($order_data,$ucid);
        $complete_order = CompleteOrder::staticGetCompleteOrder($checkout_data_resource->oid_test_only);
        $this->assertNotNull($checkout_data_resource->order_summary['order_data_as_ids_for_cart_update'],"it Should have the cart resubmit update section");
        $order_data_as_ids_for_cart_update = $checkout_data_resource->order_summary['order_data_as_ids_for_cart_update'];
        $hash = createHashmapFromArrayOfArraysByFieldName($order_data_as_ids_for_cart_update['items'],'note');
        $this->assertEquals('333333',$hash['note 3']['external_detail_id'],'It should have maintained the relationship between session id and item');
        $this->assertEquals('222222',$hash['note 2']['external_detail_id'],'It should have maintained the relationship between session id and item');
        $this->assertEquals('111111',$hash['note 1']['external_detail_id'],'It should have maintained the relationship between session id and item');
    }

    function testAddItemsToCartUsingStatus()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'pickup','sum dum note',5);
        $items = $order_data['items'];
        foreach ($items as &$item) {
            $item['order_detail_id'] = null;
            $item['status'] = 'new';
        }
        $order_data['items'] = array();
        // add the first item
        $order_data['items'][] = $items[0];
        $checkout_data_resource = $this->callCheckoutWithItemsAndCart($order_data,$ucid);
        $this->assertCount(1,$checkout_data_resource->order_summary['cart_items'],"It should have 1 item in the cart");
        $this->assertNotNull($checkout_data_resource->lead_times_array);
        $this->assertNotNull($checkout_data_resource->accepted_payment_types);
        $this->assertNotNull($checkout_data_resource->tip_array);
        $ucid = $checkout_data_resource->cart_ucid;
        $this->assertNotNull($checkout_data_resource->order_summary['order_data_as_ids_for_cart_update'],"it Should have the cart resubmit update section");

        // now add a second item
        $order_data = $checkout_data_resource->order_summary['order_data_as_ids_for_cart_update'];
        $order_data['items'][] = $items[1];
        $checkout_data_resource = $this->callCheckoutWithItemsAndCart($order_data,$ucid);
        $this->assertCount(2,$checkout_data_resource->order_summary['cart_items'],"It should now have 2 items in the cart");
        $this->assertNotNull($checkout_data_resource->lead_times_array);
        $this->assertNotNull($checkout_data_resource->accepted_payment_types);
        $this->assertNotNull($checkout_data_resource->tip_array);


        // now add a third item
        $order_data = $checkout_data_resource->order_summary['order_data_as_ids_for_cart_update'];
        $order_data['items'][] = $items[2];
        $checkout_data_resource = $this->callCheckoutWithItemsAndCart($order_data,$ucid);
        $this->assertCount(3,$checkout_data_resource->order_summary['cart_items'],"It should now have 3 items in the cart");

        // now add a forth but delete the second
        $order_data = $checkout_data_resource->order_summary['order_data_as_ids_for_cart_update'];
        $second_item = $order_data['items'][1];
        $order_data['items'][1]['status'] = 'deleted';
        $order_data['items'][] = $items[3];
        $checkout_data_resource = $this->callCheckoutWithItemsAndCart($order_data,$ucid);
        $this->assertCount(3,$checkout_data_resource->order_summary['cart_items'],"It should still have 3 items in the cart since we deleted one");
        $this->assertNotNull($checkout_data_resource->lead_times_array);
        $this->assertNotNull($checkout_data_resource->accepted_payment_types);
        $this->assertNotNull($checkout_data_resource->tip_array);

        $data_hash_of_order_ids = createHashmapFromArrayOfArraysByFieldName($checkout_data_resource->order_summary['order_data_as_ids_for_cart_update']['items'],'order_detail_id');
        $this->assertNotContains($data_hash_of_order_ids[$second_item['order_detail_id']],"It sould not containt the deleted order detail id");

        // now try updating an item
        $order_data = $checkout_data_resource->order_summary['order_data_as_ids_for_cart_update'];
        $new_second_item = $order_data['items'][1];
        $order_data['items'][1]['status'] = 'updated';
        $checkout_data_resource = $this->callCheckoutWithItemsAndCart($order_data,$ucid);
        $this->assertCount(3,$checkout_data_resource->order_summary['cart_items'],"It should still have 3 items in the cart");

        $data_hash_of_order_ids = createHashmapFromArrayOfArraysByFieldName($checkout_data_resource->order_summary['order_data_as_ids_for_cart_update']['items'],'order_detail_id');

        $this->assertFalse(isset($data_hash_of_order_ids["".$new_second_item['order_detail_id']]),"We shoudl not have that order detail id anymore since the update causes a delete and then insert");

        //try deleting a deleted item
        $order_data = $checkout_data_resource->order_summary['order_data_as_ids_for_cart_update'];
        $second_item['status'] = 'deleted';
        $order_data['items'][] = $second_item;
        $checkout_data_resource = $this->callCheckoutWithItemsAndCart($order_data,$ucid);
        $this->assertCount(3,$checkout_data_resource->order_summary['cart_items'],"It should still have 3 items in the cart");

        //make no changes
        $order_data = $checkout_data_resource->order_summary['order_data_as_ids_for_cart_update'];
        $checkout_data_resource = $this->callCheckoutWithItemsAndCart($order_data,$ucid);
        $this->assertCount(3,$checkout_data_resource->order_summary['cart_items'],"It should still have 3 items in the cart");

        //placeorder
        $place_order_data['merchant_id'] = $merchant_id;
        $place_order_data['note'] = "the new cart note";
        $place_order_data['user_id'] = $user_id;
        $place_order_data['cart_ucid'] = $ucid;
        $place_order_data['tip'] = 0.00;

        //accepted_payment_types[0]['id']
        $place_order_data['merchant_payment_type_map_id'] = $checkout_data_resource->accepted_payment_types[0]['merchant_payment_type_map_id'];
        $place_order_data['requested_time'] = $checkout_data_resource->lead_times_array[0];
        $request = createRequestObject("/apiv2/orders/$ucid",'post',json_encode($place_order_data),'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $order_resource = $place_order_controller->processV2Request();
        $this->assertNull($order_resource->error);

        $this->assertEquals($checkout_data_resource->order_summary['cart_items'],$order_resource->order_summary['cart_items'],"the summary should be same as the last checkout call");
        $this->assertEquals($checkout_data_resource->order_summary['receipt_items'][0],$order_resource->order_summary['receipt_items'][0],"Subtotal should not have changed");
        $this->assertEquals($checkout_data_resource->order_summary['receipt_items'][1],$order_resource->order_summary['receipt_items'][1],"Tax should not have changed");

    }


    static function setUpBeforeClass()
    {
        ini_set('max_execution_time',0);
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        ini_set('max_execution_time',300);
              SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;

        $skin_resource = createWorldHqSkin();
        $brand_id = $skin_resource->brand_id;
        $ids['skin_id'] = $skin_resource->skin_id;

        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(5);
        $ids['menu_id'] = $menu_id;

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 3);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);

//*
        $sides_modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1,'Sides','S');
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $sides_modifier_group_resource->modifier_group_id, 0);

        $mealdeal_modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1,'Meal Deal','I');
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $mealdeal_modifier_group_resource->modifier_group_id, 0);
//*/
        $merchant_resource = createNewTestMerchant($menu_id);
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $ids['merchant_id'] = $merchant_resource->merchant_id;
        $merchant_id = $merchant_resource->merchant_id;

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
        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);

        $user_resource = createNewUser(array("flags"=>"1C20000001"));
        $ids['user_id'] = $user_resource->user_id;

        //create the type 1 promo
        $promo_adapter = new PromoAdapter($m);
        $ids['promo_id_type_1'] = 201;
        $sql = "INSERT INTO `Promo` VALUES(201, 'The Type1 Promo', 'Get 50% off', 1, 'Y', 'N', 0, 2, 'N', 'N','all', '2010-01-01', '2020-01-01', 1, 0, 0, 0.00, 0, 0.00, 'Y', 'N',0,$brand_id, NOW(), NOW(), 'N')";
        $promo_adapter->_query($sql);
        $sql = "INSERT INTO `Promo_Merchant_Map` VALUES(null, 201, $merchant_id, '2013-10-05', '2020-01-01', NULL, now())";
        $pmm_resource = Resource::createByData(new PromoMerchantMapAdapter($mimetypes), array("merchant_id"=>$merchant_id,"promo_id"=>201));
        $ids['promo_merchant_map_id_type_1'] = $pmm_resource->map_id;
        $sql = "INSERT INTO `Promo_Message_Map` VALUES(null, 201, 'Congratulations! You''re getting a 50% off your order!', NULL, NULL, NULL, NULL, now())";
        $promo_adapter->_query($sql);
        $sql = "INSERT INTO `Promo_Type1_Amt_Map` VALUES(null, 201, 1.00, 0.00, 50,5.00, NOW())";
        $promo_adapter->_query($sql);

        $pkwm_adapter = new PromoKeyWordMapAdapter($m);
        Resource::createByData($pkwm_adapter, array("promo_id"=>201,"promo_key_word"=>"type1promo","brand_id"=>$skin_resource->brand_id));

        $_SERVER['log_level'] = 5;
        $_SERVER['unit_test_ids'] = $ids;
    }

    static function tearDownAfterClass()
    {
        SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
        date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
        $suite = new PHPUnit_Framework_TestSuite( __CLASS__);
        PHPUnit_TextUI_TestRunner::run( $suite);
    }

}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    ApiCartStatusTest::main();
}

?>