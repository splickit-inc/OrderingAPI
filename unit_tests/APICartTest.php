<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class ApiCartTest extends PHPUnit_Framework_TestCase
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

    function testMenuTypeTimeRangeWithAfterMidnightClose()
    {
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $menu_type_adapter = new MenuTypeAdapter(getM());
        $menu_type_resource = $menu_type_adapter->getExactResourceFromData(array("menu_id" => $menu_id));
        $menu_type_resource->start_time = '14:00:00';
        $menu_type_resource->end_time = '03:00:00';
        $menu_type_resource->save();

        $user_resource = createNewUser(array("flags" => "1C20000001"));
        $user_id = $user_resource->user_id;
        $user = logTestUserIn($user_id);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        $sql = "UPDATE Hour SET open = '10:00', close = '3:00' WHERE merchant_id = $merchant_id";
        $ha = new HourAdapter(getM());
        $ha->_query($sql);
        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'some note');

        $json_encoded_data = json_encode($order_data);
        $request = createRequestObject('/app2/apiv2/cart/checkout','POST',$json_encoded_data);
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $response = makeMockRequest($place_order_controller);
        $response_array = json_decode($response,true);
        $this->assertContains('until 2:00 pm',$response_array['message']);
        $first_available_time_stamp = $response_array['data']['lead_times_array'][0];
        $first_available_time_string = date('Y-m-d H:i:s',$first_available_time_stamp);
        $expected_first_time_string = date('Y-m-d H:i:s',getTomorrowTwelveNoonTimeStampDenver()+(2*3600));
        $this->assertEquals($expected_first_time_string,$first_available_time_string,"It should have 2pm tomorrow as first available");

        // now lets try after midnight
        $json_encoded_data = json_encode($order_data);
        $request = createRequestObject('/app2/apiv2/cart/checkout','POST',$json_encoded_data);
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);

        //set current time to 1am tomorrow
        $one_am_time = getTodayTwelveNoonTimeStampDenver()+(13*3600);
        $place_order_controller->setCurrentTime($one_am_time);
        $response = makeMockRequest($place_order_controller);
        $response_array = json_decode($response,true);

        $first_available_time_stamp = $response_array['data']['lead_times_array'][0];
        $first_available_time_string = date('Y-m-d H:i:s',$first_available_time_stamp);
        $expected_first_time_string = date('Y-m-d H:i:s',$one_am_time+(20*60));
        $this->assertEquals($expected_first_time_string,$first_available_time_string,"It should have 1:20am as first available");

    }


    function testMaxEntrePerOrder()
    {
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $menu_type_id = createNewMenuTypeWithNNumberOfItems($menu_id,'Drinks','D',1);

        $complete_menu = CompleteMenu::getCompleteMenu($menu_id);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $merchant_menu_map_resource = Resource::find(new MerchantMenuMapAdapter(getM()),'',[3=>['merchant_id'=>$merchant_id,'merchant_menu_type'=>'pickup']]);
        $merchant_menu_map_resource->max_entres_per_order = 2;
        $merchant_menu_map_resource->default_tip_percentage = 15;
        $merchant_menu_map_resource->allows_dine_in_orders = 1;
        $merchant_menu_map_resource->save();


        $order_adapter = new OrderAdapter(getM());
        $cart_data = getEmptyCart($user,$merchant_id);
        $items = $order_adapter->getItemsForCartWithOneModifierPerModifierGroup($complete_menu,6);
        $cart_data['items'] = $items;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNotNull($checkout_resource->error);
        $expected_message = str_replace('%%max%%',2,PlaceOrderController::MAX_NUMBER_OF_ENTRES_EXCEEDED_ERROR_MESSAGE);
        $expected_message = str_replace('%%diff%%',1,$expected_message);
        $this->assertEquals($expected_message,$checkout_resource->error);

        $merchant_menu_map_resource->max_entres_per_order = 3;
        $merchant_menu_map_resource->save();

        $checkout_resource2 = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource2->error);

        $this->assertEquals('15%',$checkout_resource2->pre_selected_tip_value);
        $this->assertTrue($checkout_resource2->allows_dine_in_orders,"It should have the flag on for dine in");



    }

    function testQuantityModifier()
    {
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $modifier_group_resource = createQuantityModifierGroup($menu_id);
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_resource->modifier_group_id);
        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $order_adapter = new OrderAdapter(getM());
        $cart_data = $order_adapter->getCartArrayWithOneModierPerModifierGroup($merchant_id,'pickup',2);

        $cart_data['items'][1]['mods'][0]['mod_quantity'] = 4;
        $cart_data['items'][0]['mods'][0]['mod_quantity'] = 0;

        $checkout_resource = getCheckoutResourceFromOrderData($cart_data);
        $this->assertEquals(4,$checkout_resource->order_qty,'It should have a quantity of 4');

        $complete_order = CompleteOrder::staticGetCompleteOrder($checkout_resource->ucid);
        $this->assertCount(1,$complete_order['order_details'],"It should only have 1 order detail row");

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);

        $this->assertEquals(4,$order_resource->order_qty);

    }


    function testRoundUpfunctionality()
    {
        $skin_resource = getOrCreateSkinAndBrandIfNecessary("donationskin","donationbrand");
        $skin_resource->donation_active = 'Y';
        $skin_resource->donation_organization = "My Org";
        $skin_resource->save();
        setContext($skin_resource->external_identifier);
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        $user_resource = createNewUserWithCCNoCVV();

        $user_skin_donation_adapter = new UserSkinDonationAdapter(getM());
        $roundup_resource = Resource::createByData($user_skin_donation_adapter,['user_id'=>$user_resource->user_id,'skin_id'=>getSkinIdForContext()]);

        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals(.00,$checkout_resource->customer_donation_amt);
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);
        $this->assertEquals(.80,$order_resource->customer_donation_amt);
        $this->assertEquals(3.00,$order_resource->grand_total);
        $this->assertEquals(2.20,$order_resource->grand_total_to_merchant);


    }

    function testLeadTimeWithHoleHoursForDeliveryOrderApiV1too()
    {
        $_SERVER['max_lead'] = 240;
        $user_resource = createNewUser(array("flags" => "1C20000001"));
        $user = logTestUserResourceIn($user_resource);

        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"1045 Pine Street","address2":"","city":"boulder","state":"co","zip":"80302","phone_no":"9709262121","lat":40.0197891,"lng":-105.284703}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "Application/json";
        $request->_parseRequestBody();
        $request->method = 'POST';
        $request->url = "/users/" . $user['uuid'] . "/userdeliverylocation";
        $user_controller = new UserController(getM(), $user, $request, 5);
        //$response = $user_controller->setDeliveryAddr();
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;


        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $merchant_delivery_info_resource = Resource::find(new MerchantDeliveryInfoAdapter(getM()),null,[3=>["merchant_id"=>$merchant_resource->merchant_id]]);
        $merchant_delivery_info_resource->max_days_out = 10;
        $merchant_delivery_info_resource->save();
        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);
        $merchant_id = $merchant_resource->merchant_id;

        $holehours_adapter = new HoleHoursAdapter($mt);
        $start = strtotime("12:00:00");
        $end = strtotime("14:00:00");

        $hole_hours_per_day = array();

        $day = date("w", getTomorrowTwelveNoonTimeStampDenver()) + 1;
        $holehour_data = array(
            'merchant_id' => $merchant_id,
            'day_of_week' => $day,
            'order_type' => 'Delivery',
            'start_time' => date("G:i:s", $start),
            'end_time' => date("G:i:s", $end)
        );

        $hh_resource = Resource::factory($holehours_adapter, $holehour_data);
        $hh_resource->save();
        $hole_hours_per_day[$day] = $holehour_data;
        $next_day = $day == 7 ? 1 : $day+1;
        $holehour_data['day_of_week'] = $next_day;
        $holehour_data['start_time'] = '15:00:00';
        $holehour_data['end_time'] = '17:00:00';
        $hh_resource = Resource::factory($holehours_adapter, $holehour_data);
        $hh_resource->save();
        $hole_hours_per_day[$next_day] = $holehour_data;



        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', 'some note');
        $order_data['user_addr_id'] = $user_address_id;

        $resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver() - (85 * 60));

//        $request = createRequestObject("/apiv2/cart/checkout","POST",json_encode($order_data),'application/json');
//        $place_order_controller = new PlaceOrderController(getM(), $user, $request, $log_level);
//        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver() - (85 * 60));
//        $resource = $place_order_controller->processV2Request();
        $leadtime_array = $resource->lead_times_array;
        foreach ($leadtime_array as $ts) {
            $time_string = date('Y-m-d H:i:s', $ts);
            myerror_log("available time: $time_string");
            $this->assertFalse($ts > getTomorrowTwelveNoonTimeStampDenver() && $ts < getTomorrowTwelveNoonTimeStampDenver() + (2 * 60 * 60), "There should have been no times in the donut hole. Failed time was: $time_string");
            if ($ts > getTomorrowTwelveNoonTimeStampDenver() + (2 * 60 * 60)) {
                $there_are_available_times_outside_of_donut_hole_after = true;
            }
            if ($ts < getTomorrowTwelveNoonTimeStampDenver()) {
                $there_are_available_times_outside_of_donut_hole_before = true;
            }
        }
        $this->assertTrue($there_are_available_times_outside_of_donut_hole_before, "It should have available times before donut hole");
        $this->assertTrue($there_are_available_times_outside_of_donut_hole_after, "It should have available times after donut hole");

        // now check the next days hours
        $next_day_10am = getTomorrowTwelveNoonTimeStampDenver() + (22*3600);
        $next_day_3pm = getTomorrowTwelveNoonTimeStampDenver() + (27*3600);
        $next_day_5pm = getTomorrowTwelveNoonTimeStampDenver() + (29*3600);
        foreach ($leadtime_array as $ts) {
            $time_string = date('Y-m-d H:i:s', $ts);
            myerror_log("available time: $time_string");
            $this->assertFalse($ts > $next_day_3pm && $ts < $next_day_5pm, "There should have been no times in the donut hole on the second day. Failed time was: $time_string");
            if ($ts > $next_day_5pm) {
                $there_are_available_times_outside_of_donut_hole_after_on_day_2 = true;
            }
            if ($ts > $next_day_10am && $ts < $next_day_3pm) {
                $there_are_available_times_outside_of_donut_hole_before_on_day_2 = true;
            }
        }

        $this->assertTrue($there_are_available_times_outside_of_donut_hole_before_on_day_2, "It should have available times before donut hole on day 2");
        $this->assertTrue($there_are_available_times_outside_of_donut_hole_after_on_day_2, "It should have available times after donut hole on day 2");

        //now check following weeks hours for the advanced ordering
        $next_week_10am = getTomorrowTwelveNoonTimeStampDenver() + (7*24*3600) - 7200;
        $next_week_12pm = getTomorrowTwelveNoonTimeStampDenver() + (7*24*3600);
        $next_week_2pm = getTomorrowTwelveNoonTimeStampDenver() + (7*24*3600) + 7200;
        foreach ($leadtime_array as $ts) {
            $time_string = date('Y-m-d H:i:s', $ts);
            myerror_log("available time: $time_string");
            $this->assertFalse($ts > $next_week_12pm && $ts < $next_week_2pm, "There should have been no times in the donut hole on the second week. Failed time was: $time_string");
            if ($ts > $next_week_2pm) {
                $there_are_available_times_outside_of_donut_hole_after_on_week_2 = true;
            }
            if ($ts > $next_week_10am && $ts < $next_week_12pm) {
                $there_are_available_times_outside_of_donut_hole_before_on_week_2 = true;
            }
        }

        $this->assertTrue($there_are_available_times_outside_of_donut_hole_before_on_week_2, "It should have available times before donut hole on day 2");
        $this->assertTrue($there_are_available_times_outside_of_donut_hole_after_on_week_2, "It should have available times after donut hole on day 2");


        // now try checkout inside hole hours
        $checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver() + 300);

//        $json_encoded_data = json_encode($order_data);
//        $url = '/app2/apiv2/cart/checkout';
//        $request = createRequestObject($url, 'post', $json_encoded_data, 'application/json');
//        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
//        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
//        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);
        $cart_ucid = $checkout_resource->cart_ucid;
        $leadtime_array = $checkout_resource->lead_times_array;
        $first_time = $leadtime_array[0];
        $first_time_string = date('Y-m-d H:i:s', $first_time);
        $day = date('Y-m-d', getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals("$day 14:00:00", $first_time_string, "first time should have been 2 oclock");


        // now check for ASAP failure if its within donut hole
        $order_data = array();
        $order_data['tip'] = 0.00;
        $order_data['delivery_time'] = "As soon as possible";
        $order_data['delivery'] = 'yes';
        $payment_array = $checkout_resource->accepted_payment_types;
        $order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
        //$lead_times_array = $checkout_resource->lead_times_array;
        $order_data['actual_pickup_time'] = "As soon as possible";

        $json_encoded_data = json_encode($order_data);
        $request = createRequestObject("/apiv2/orders/$cart_ucid", "post", $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $current_time = $time == null ? getTomorrowTwelveNoonTimeStampDenver() : $time;
        $place_order_controller->setCurrentTime($current_time);
        $order_resource = $place_order_controller->processV2Request();


        //$order_resource = placeOrderFromOrderData($order_data, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNotNull($order_resource->error);
        $this->assertEquals("We're sorry, this merchant is currently out of delivery hours, and cannot deliver 'As soon as possible'. Please choose a delivery time from the drop down list.", $order_resource->error);

    }

    function testDuplicateTransactionDoNotCancelCartOrder()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id'],["authorize"=>true]);
        $merchant_id = $merchant_resource->merchant_id;
        attachMerchantToSkin($merchant_id, $this->ids['skin_id']);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'the note');
        $json_encoded_data = json_encode($order_data);

        $url = '/app2/apiv2/cart/checkout';
        $request = createRequestObject($url, 'post', $json_encoded_data);
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);
        //$checkout_resource->note = 'Fail Credit Card';
        $order_response_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_response_resource->error);
        $order_id = $order_response_resource->order_id;
        $order_resource = Resource::find(new OrderAdapter(getM()),"$order_id");
        $order_resource->status = 'Y';
        $order_resource->save();

        $user_resource->uuid = substr($user_resource->uuid,0,17).'DUPLI';
        $user_resource->save();
        $order_response_resource2 = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNotNull($order_response_resource2->error);

        $order_resource_after = Resource::find(new OrderAdapter(getM()),"$order_id");
        $this->assertEquals('Y',$order_resource_after->status,"Status shoudl have been left unchanged becuase duplicate");



    }

    function testUserCCStatusOnCheckout()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        attachMerchantToSkin($merchant_id, $this->ids['skin_id']);

        $user_resource = createNewUser();
        $this->assertEquals("1000000001",$user_resource->flags);
        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'the note');
        $json_encoded_data = json_encode($order_data);

        $url = '/app2/apiv2/cart/checkout';
        $request = createRequestObject($url, 'post', $json_encoded_data);
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();

        $this->assertTrue(isset($checkout_resource->user_info['user_has_cc']),"there shoudl be a users cc status on the checkout resource");
        $this->assertFalse($checkout_resource->user_info['user_has_cc'],"the value should be false");

        $user_resource->flags = '1C20000001';
        $user_resource->last_four = '8888';
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);

        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource2 = $place_order_controller->processV2Request();
        $this->assertTrue($checkout_resource2->user_info['user_has_cc'],"the value of the cc status should be true");
        $this->assertEquals('8888',$checkout_resource2->user_info['last_four']);


    }

    function testValidateTipLogic()
    {
        $_SERVER['device_type'] = 'web-unit-testing';
        $tip_skin = getOrCreateSkinAndBrandIfNecessary("tipskin","tipbrand",null,null);
        setContext("com.splickit.tipskin");
        $brand_resource = Resource::find(new BrandAdapter(),$tip_skin->brand_id);
        $brand_resource->allows_tipping = 'N';
        $brand_resource->save();
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_resource->show_tip = 'Y';

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_resource->merchant_id);
        $_SERVER['device_type'] = 'web-unit-testing';
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $ucid = $checkout_resource->ucid;
        $this->assertNull($checkout_resource->tip_array,"it Should not have a tip array");

        $url = "apiv2/carts/$ucid/checkout";
        $request = createRequestObject($url,'GET');
        $placeorder_controller = new PlaceOrderController($m,$user,$request,5);
        $_SERVER['device_type'] = 'android';
        $placeorder_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $placeorder_controller->processV2Request();
        $this->assertNotNull($checkout_resource->tip_array,"It should have a tip array");

        $brand_resource->allows_tipping = 'Y';
        $brand_resource->save();
        setContext("com.splickit.tipskin");

        $_SERVER['device_type'] = 'web-unit-testing';
        $checkout_resource = $placeorder_controller->processV2Request();
        $this->assertNotNull($checkout_resource->tip_array,"It should have a tip array");

        $_SERVER['device_type'] = 'android';
        $checkout_resource = $placeorder_controller->processV2Request();
        $this->assertNotNull($checkout_resource->tip_array,"It should have a tip array");

        $merchant_resource->show_tip = 'N';
        $merchant_resource->save();
        $placeorder_controller = new PlaceOrderController($m,$user,$request,5);
        $_SERVER['device_type'] = 'web-unit-testing';
        $placeorder_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $placeorder_controller->processV2Request();
        $this->assertNull($checkout_resource->tip_array,"It should not have a tip array");

        $_SERVER['device_type'] = 'android';
        $checkout_resource = $placeorder_controller->processV2Request();
        $this->assertNotNull($checkout_resource->tip_array,"It should have a tip array");

    }

    function testValidate30MenuPriceThings()
    {
        $menu_id = createTestMenuWithNnumberOfItems(3);
        $menu_resource = Resource::find(new MenuAdapter(),"$menu_id");
        $menu_resource->version = 3.0;
        $menu_resource->save();
        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 3);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id);

        // add aditional size records
        $complete_menu = new CompleteMenu($menu_id);
        $item_size_resources = $complete_menu->getAllMenuItemSizeMapResources($menu_id, 'Y', 0);
        $size_id = $item_size_resources[0]->size_id;

        $modifier_item_size_resources = $complete_menu->getAllModifierItemSizeResources($menu_id, 'Y', 0);
        myerror_log("we found this many modifier item size maps: " . count($modifier_item_size_resources));
        foreach ($modifier_item_size_resources as $modifieritem_size_resource) {
            $modifieritem_size_resource->modifier_price = 0.00;
            $modifieritem_size_resource->save();
            $modifieritem_size_resource->_exists = false;
            unset($modifieritem_size_resource->modifier_size_id);
            $modifieritem_size_resource->size_id = $size_id;
            $modifieritem_size_resource->modifier_price = 5.00;
            $modifieritem_size_resource->save();
        }

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        attachMerchantToSkin($merchant_id, $this->ids['skin_id']);

        //now create a second merchant so there are multiple price records
        $merchant_resource2 = createNewTestMerchant($menu_id);
        attachMerchantToSkin($merchant_resource2->merchant_id, $this->ids['skin_id']);

        $user_resource = createNewUser();
        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'the note');
        $json_encoded_data = json_encode($order_data);

        $url = '/app2/apiv2/cart/checkout';
        $request = createRequestObject($url, 'post', $json_encoded_data);
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);
        $complete_order = CompleteOrder::staticGetCompleteOrder($checkout_resource->oid_test_only);
        $this->assertCount(3,$complete_order['order_details'][0]['order_detail_modifiers']);
        $this->assertEquals(16.50,$checkout_resource->order_amt,"should have the higher modifier prices");

        //now set mods to innactive
        $sql = "UPDATE Modifier_Size_Map SET active = 'N' WHERE merchant_id = $merchant_id AND size_id = $size_id";
        $msm = new ModifierSizeMapAdapter();
        $msm->_query($sql);

        $user_resource = createNewUser();
        $user = logTestUserResourceIn($user_resource);

        $url = '/app2/apiv2/cart/checkout';
        $request = createRequestObject($url, 'post', $json_encoded_data);
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);
        $complete_order = CompleteOrder::staticGetCompleteOrder($checkout_resource->oid_test_only);
        $this->assertCount(3,$complete_order['order_details'][0]['order_detail_modifiers']);
        $this->assertEquals(1.50,$checkout_resource->order_amt,"should have the free modifier prices");
    }

    function testNoCCForUserAndNoCashOption()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        $user_resource = createNewUser();
        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'the note');
        $json_encoded_data = json_encode($order_data);

        $url = '/app2/apiv2/cart/checkout';
        $request = createRequestObject($url, 'post', $json_encoded_data);
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);
        $cc_payment_type = array_pop($checkout_resource->accepted_payment_types);
        $cc_payment_type['merchant_payment_type_map_id'] = null;
        $checkout_resource->accepted_payment_types = array($cc_payment_type);
        $order = new Order($checkout_resource->oid_test_only);
        $user = getUserFromId($order->get('user_id'));
        $order_resource = placeOrderFromCheckoutResource($checkout_resource, $user, $order->get('merchant_id'), 1.50, $time);
        $this->assertNotNull($order_resource->error);
        $this->assertEquals('Please enter your credit card info',$order_resource->error);

    }

    function testAmountsForCart()
    {
        $merchant_id = $this->ids['merchant_id'];
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'the note');
        $json_encoded_data = json_encode($order_data);

        $url = '/app2/apiv2/cart';
        $request = createRequestObject($url, 'post', $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $cart_resource = $place_order_controller->processV2Request();
        $this->assertNull($cart_resource->error);
        $cart_ucid = $cart_resource->ucid;
        $order_id = $cart_resource->oid_test_only;

        $quantity = 1;
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id);
        $this->assertEquals($quantity, $complete_order['order_qty']);
        $this->assertCount($quantity, $complete_order['order_details']);
        $this->assertEquals($quantity * 2.00, $complete_order['order_amt']);

        $url = "/app2/apiv2/cart/$cart_ucid";
        $request = createRequestObject($url, 'post', $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $cart_resource = $place_order_controller->processV2Request();
        $this->assertNull($cart_resource->error);

        $quantity = 2;
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id);
        $this->assertEquals($quantity, $complete_order['order_qty']);
        $this->assertCount($quantity, $complete_order['order_details']);
        $this->assertEquals($quantity * 2.00, $complete_order['order_amt']);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'the note', 3);
        $json_encoded_data = json_encode($order_data);

        $url = "/app2/apiv2/cart/$cart_ucid";
        $request = createRequestObject($url, 'post', $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $cart_resource = $place_order_controller->processV2Request();
        $this->assertNull($cart_resource->error);

        $quantity = 5;
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id);
        $this->assertEquals($quantity, $complete_order['order_qty']);
        $this->assertCount($quantity, $complete_order['order_details']);
        $this->assertEquals($quantity * 2.00, $complete_order['order_amt']);

        $url = "/app2/apiv2/cart/$cart_ucid/checkout";
        $request = createRequestObject($url, 'GET');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);

        $this->assertEquals($quantity, $checkout_resource->order_qty);
        $this->assertEquals($quantity * 2.00, $checkout_resource->order_amt);
        return $checkout_resource;
    }

    /**
     * @depends testAmountsForCart
     */
    function testPlaceOrderFromCheckoutResource($checkout_resource)
    {
        $cc_payment_type = array_pop($checkout_resource->accepted_payment_types);
        $checkout_resource->accepted_payment_types = array($cc_payment_type);
        $order = new Order($checkout_resource->oid_test_only);
        $user = getUserFromId($order->get('user_id'));
        $order_resource = placeOrderFromCheckoutResource($checkout_resource, $user, $order->get('merchant_id'), 1.50, $time);
        $this->assertNull($order_resource->error);
        $this->assertTrue($order_resource->order_id > 999, "It Should have a valid order id");

        $this->assertEquals(12.50, $order_resource->grand_total);
        $expected_pickup_dt_tm = date('Y-m-d H:i:s', getTomorrowTwelveNoonTimeStampDenver() + 25 * 60);
        $this->assertEquals($expected_pickup_dt_tm, $order_resource->pickup_dt_tm);
        $this->assertEquals(date('Y-m-d H:i:s', getTomorrowTwelveNoonTimeStampDenver()), $order_resource->order_dt_tm);

        // check balance change table
        $balance_change_records = getStaticRecords(array("order_id"=>$order_resource->order_id),'BalanceChangeAdapter');
        $bcrhash = createHashmapFromArrayOfArraysByFieldName($balance_change_records,'process');
        $this->assertEquals($order_resource->grand_total,-$bcrhash['Order']['charge_amt']);
        $this->assertEquals($order_resource->grand_total,$bcrhash['CCpayment']['charge_amt']);

    }

    function testMiniumPickupTimeSetOnCartRecordOnCheckout()
    {
        $merchant_id = $this->ids['merchant_id'];
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'the note', 10);
        $json_encoded_data = json_encode($order_data);

        $url = '/app2/apiv2/cart/checkout';
        $request = createRequestObject($url, 'post', $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $cart_ucid = $checkout_resource->cart_ucid;
        $this->assertNull($checkout_resource->error);
        $this->assertEquals(35, $checkout_resource->minimum_leadtime_for_this_order, "It shoul have set the minimum lead time on the checkout resource");
    }

    function testUserHasSatOnCheckoutScreenForGreaterThan5Minutes()
    {

        $merchant_id = $this->ids['merchant_id'];
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'the note');
        $json_encoded_data = json_encode($order_data);

        $url = '/app2/apiv2/cart/checkout';
        $request = createRequestObject($url, 'post', $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $cart_ucid = $checkout_resource->cart_ucid;
        $this->assertNull($checkout_resource->error);

        $order_resource = placeOrderFromCheckoutResource($checkout_resource, $user, $merchant_id, 0.00, getTomorrowTwelveNoonTimeStampDenver() + 310);
        $this->assertNotNull($order_resource->error, "We should have gotten an error because the chosen time was too close to now");
        $this->assertEquals("ORDER ERROR! Your pickup time has expired. Please select a new pickup time and proceed to check out.", $order_resource->error);

    }


    function testPlaceDeliveryOrderWithCartSimple()
    {
        $merchant_resource = createNewTestMerchant();
        $merchant_resource->delivery = 'Y';
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);

        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'delivery');
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'pickup');

        $data = array("merchant_id" => $merchant_resource->merchant_id);

        // set merchant delivery info
        $mdia = new MerchantDeliveryInfoAdapter(getM());
        $mdia_resource = $mdia->getExactResourceFromData($data);
        $mdia_resource->minimum_order = 10.00;
        $mdia_resource->delivery_cost = 1.00;
        $mdia_resource->delivery_increment = 15;
        $mdia_resource->max_days_out = 4;
        $mdia_resource->minimum_delivery_time = 45;
        $mdia_resource->save();

        $mdpd = new MerchantDeliveryPriceDistanceAdapter(getM());
        $mdpd_resource = $mdpd->getExactResourceFromData($data);
        $this->assertNotNull($mdpd_resource, "should have found a merchant delivery price distance resource");
        $mdpd_resource->distance_up_to = 20.0;
        $delivery_charge = 8.88;
        $mdpd_resource->price = $delivery_charge;
        $mdpd_resource->save();


        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $json = '{"user_addr_id":null,"user_id":"' . $user_id . '","name":"","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "Application/json";
        $request->_parseRequestBody();
        $request->method = 'POST';
        $request->url = "/users/" . $user['uuid'] . "/userdeliverylocation";
        $user_controller = new UserController(getM(), $user, $request, 5);
        //$response = $user_controller->setDeliveryAddr();
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id", 'GET', $body, $mimetype);
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range), "should have found the 'is in delivery range' field");
        $this->assertTrue($resource->is_in_delivery_range, " the is in delivery range should be true");
        $this->assertEquals($mdpd_resource->price, $resource->price);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note', 2);
        $order_data['user_addr_id'] = $user_address_id;
        $json_encoded_data = json_encode($order_data);

        $url = '/app2/apiv2/cart';
        $request = createRequestObject($url, 'post', $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        //$cart_resource = $place_order_controller->createNewCart();
        $cart_resource = $place_order_controller->processV2Request();
        $this->assertNull($cart_resource->error);
        $cart_ucid = $cart_resource->ucid;

        //validate delivery stuff
        $this->assertEquals('D', $cart_resource->order_type);
        $this->assertEquals($delivery_charge, $cart_resource->delivery_amt, "It should have the delivery amount");

        $url = "/app2/apiv2/cart/$cart_ucid/checkout";
        $request = createRequestObject($url, 'get');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNotNull($checkout_resource->error);
        $this->assertEquals("Minimum order required! You have not met the minimum subtotal of $10.00 for your deliver area.", $checkout_resource->error);
    }


    function testPlaceDeliveryOrderWithCartSwitchUserDeliveryAddress()
    {
        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, null);

        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'delivery');
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'pickup');

        $data = array("merchant_id" => $merchant_resource->merchant_id);

        // set merchant delivery info
        $mdia = new MerchantDeliveryInfoAdapter(getM());
        $mdia_resource = $mdia->getExactResourceFromData($data);
        $mdia_resource->minimum_order = 1.00;
        $mdia_resource->delivery_cost = 1.00;
        $mdia_resource->delivery_increment = 15;
        $mdia_resource->max_days_out = 4;
        $mdia_resource->minimum_delivery_time = 45;
        $mdia_resource->save();

        $mdpd = new MerchantDeliveryPriceDistanceAdapter(getM());
        $mdpd_resource = $mdpd->getExactResourceFromData($data);
        $this->assertNotNull($mdpd_resource, "should have found a merchant delivery price distance resource");
        $mdpd_resource->distance_up_to = 5.0;
        $delivery_charge = 8.88;
        $mdpd_resource->price = $delivery_charge;
        $mdpd_resource->save();


        unset($mdpd_resource->map_id);
        $mdpd_resource->_exists = false;
        $mdpd_resource->distance_up_to = 220.0;
        $delivery_charge2 = 11.11;
        $mdpd_resource->price = $delivery_charge2;
        $mdpd_resource->save();



        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $json = '{"user_addr_id":null,"user_id":"' . $user_id . '","name":"","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
        $request = createRequestObject("/users/" . $user['uuid'] . "/userdeliverylocation",'POST',$json);
        $user_controller = new UserController(getM(), $user, $request, 5);
        //$response = $user_controller->setDeliveryAddr();
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id", 'GET', $body, $mimetype);
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range), "should have found the 'is in delivery range' field");
        $this->assertTrue($resource->is_in_delivery_range, " the is in delivery range should be true");
        $this->assertEquals($delivery_charge, $resource->price);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note', 2);
        $order_data['user_addr_id'] = $user_address_id;
        $json_encoded_data = json_encode($order_data);

        $url = '/app2/apiv2/cart';
        $request = createRequestObject($url, 'post', $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        //$cart_resource = $place_order_controller->createNewCart();
        $cart_resource = $place_order_controller->processV2Request();
        $this->assertNull($cart_resource->error);
        $cart_ucid = $cart_resource->ucid;

        //validate delivery stuff
        $this->assertEquals('D', $cart_resource->order_type);
        $this->assertEquals($delivery_charge, $cart_resource->delivery_amt, "It should have the delivery amount");

        $url = "/app2/apiv2/cart/$cart_ucid/checkout";
        $request = createRequestObject($url, 'get');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);


        // now create second delivery address
        $json = '{"user_addr_id":null,"user_id":"' . $user_id . '","name":"","address1":"303 Main Street","address2":"apt 2","city":"Lyons","state":"co","zip":"80540","phone_no":"9709262121","lat":40.224328,"lng":-105.2707817}';
        $request = createRequestObject("/users/" . $user['uuid'] . "/userdeliverylocation",'POST',$json);
        $user_controller = new UserController(getM(), $user, $request, 5);
        //$response = $user_controller->setDeliveryAddr();
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $new_user_address_id = $response->user_addr_id;


        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$new_user_address_id", 'GET', $body, $mimetype);
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range), "should have found the 'is in delivery range' field");
        $this->assertTrue($resource->is_in_delivery_range, " the is in delivery range should be true");
        $this->assertEquals($delivery_charge2, $resource->price);



        $new_data = ["user_addr_id"=>$new_user_address_id,"submitted_order_type"=>"delivery","user_id"=>$user['uuid']];
        $url = "/app2/apiv2/cart/$cart_ucid/checkout";
        $request = createRequestObject($url, 'POST',json_encode($new_data));
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $checkout_resource2 = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource2->error);

        $complete_order = CompleteOrder::getBaseOrderData($cart_ucid);
        $this->assertEquals($new_user_address_id,$complete_order['user_delivery_location_id'],"It should have the new user_address_id");
        $this->assertEquals($delivery_charge2,$checkout_resource2->delivery_amt);

    }

    function testPlaceDeliveryOrderWithOutCart()
    {
        $merchant_resource = createNewTestMerchant();
        $merchant_resource->delivery = 'Y';
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);

        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'delivery');
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'pickup');

        $data = array("merchant_id" => $merchant_resource->merchant_id);

        // set merchant delivery info
        $mdia = new MerchantDeliveryInfoAdapter(getM());
        $mdia_resource = $mdia->getExactResourceFromData($data);
        $mdia_resource->minimum_order = 5.00;
        $mdia_resource->delivery_cost = 1.00;
        $mdia_resource->delivery_increment = 15;
        $mdia_resource->max_days_out = 4;
        $mdia_resource->minimum_delivery_time = 45;
        $mdia_resource->save();

        $mdpd = new MerchantDeliveryPriceDistanceAdapter(getM());
        $mdpd_resource = $mdpd->getExactResourceFromData($data);
        $this->assertNotNull($mdpd_resource, "should have found a merchant delivery price distance resource");
        $mdpd_resource->distance_up_to = 20.0;
        $mdpd_resource->price = 8.88;
        $mdpd_resource->save();


        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "Application/json";
        $request->_parseRequestBody();
        $request->method = 'POST';
        $request->url = "/users/" . $user['uuid'] . "/userdeliverylocation";
        $user_controller = new UserController(getM(), $user, $request, 5);
        //$response = $user_controller->setDeliveryAddr();
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id", 'GET', $body, $mimetype);
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range), "should have found the 'is in delivery range' field");
        $this->assertTrue($resource->is_in_delivery_range, " the is in delivery range should be true");
        $this->assertEquals($mdpd_resource->price, $resource->price);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note', 10);
        $order_data['user_addr_id'] = $user_address_id;
        $json_encoded_data = json_encode($order_data);

        $url = '/app2/apiv2/cart';
        $request = createRequestObject($url, 'post', $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        //$cart_resource = $place_order_controller->createNewCart();
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);
        $cart_ucid = $checkout_resource->ucid;


    }

    function testPlaceDeliveryOrderWithCartAfterStartingWithPickupOrder()
    {
        $merchant_resource = createNewTestMerchant();
        $merchant_resource->delivery = 'Y';
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);

        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'delivery');
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'pickup');

        $data = array("merchant_id" => $merchant_resource->merchant_id);

        // set merchant delivery info
        $mdia = new MerchantDeliveryInfoAdapter(getM());
        $mdia_resource = $mdia->getExactResourceFromData($data);
        $mdia_resource->minimum_order = 10.00;
        $mdia_resource->delivery_cost = 1.00;
        $mdia_resource->delivery_increment = 15;
        $mdia_resource->max_days_out = 4;
        $mdia_resource->minimum_delivery_time = 45;
        $mdia_resource->save();

        $mdpd = new MerchantDeliveryPriceDistanceAdapter(getM());
        $mdpd_resource = $mdpd->getExactResourceFromData($data);
        $mdpd_resource->distance_up_to = 10.0;
        $mdpd_resource->price = 8.88;
        $mdpd_resource->save();

        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "Application/json";
        $request->_parseRequestBody();
        $request->method = 'POST';
        $request->url = "/users/" . $user['uuid'] . "/userdeliverylocation";
        $user_controller = new UserController(getM(), $user, $request, 5);
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'the note');
        $order_data['submitted_order_type'] = 'pickup';
        $json_encoded_data = json_encode($order_data);

        $url = '/app2/apiv2/cart/checkout';
        $request = createRequestObject($url, 'post', $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $cart_resource = $place_order_controller->processV2Request();
        $cart_ucid = $cart_resource->cart_ucid;
        $this->assertNull($cart_resource->error);
        $this->assertNotNull($cart_resource, "should have gotten a cart resource back");

        $full_cart_resource = SplickitController::getResourceFromId($cart_ucid, 'Carts');

        $cart_order_id = $full_cart_resource->order_id;
        $base_order_data = CompleteOrder::getBaseOrderData($cart_order_id, getM());
        $expected_order_amt = $base_order_data['order_qty'] * 2.00;
        $this->assertEquals($expected_order_amt, $base_order_data['order_amt'], 'One item should be $' . $expected_order_amt);

        $this->assertEquals(OrderAdapter::PICKUP_ORDER, $base_order_data['order_type']);
        $this->assertEquals(0.00, $base_order_data['delivery_amt']);


        // now add more to the cart and see if it transfers and adds the delivery stuff
        $order_data['user_addr_id'] = $user_address_id;
        $order_data['submitted_order_type'] = 'delivery';
        $request = createRequestObject("/app2/apiv2/cart/$cart_ucid/checkout", 'post', json_encode($order_data), 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $new_cart_resource = $place_order_controller->processV2Request();

        $base_order_data = CompleteOrder::getBaseOrderData($cart_order_id, getM());
        $expected_order_amt = $base_order_data['order_qty'] * 2.00;
        $this->assertEquals($expected_order_amt, $base_order_data['order_amt'], $base_order_data['order_qty'] . ' items should be $' . $expected_order_amt);


        $this->assertNotNull($new_cart_resource->error, "should have thrown an error becuase delivery mininum has not been met");
        $this->assertEquals("Minimum order required! You have not met the minimum subtotal of $10.00 for your deliver area.", $new_cart_resource->error);
        $this->assertEquals(422, $new_cart_resource->http_code, "Should return a 422 http code");

        $new_order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note', 4);
        $order_data['items'] = array_merge($order_data['items'], $new_order_data['items']);

        $new_request = createRequestObject("/app2/apiv2/cart/$cart_ucid/checkout", 'post', json_encode($order_data), 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $new_request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $new_cart_resource = $place_order_controller->processV2Request();

        $base_order_data = CompleteOrder::getBaseOrderData($cart_order_id, getM());
        $expected_order_amt = $base_order_data['order_qty'] * 2.00;
        $this->assertEquals($expected_order_amt, $base_order_data['order_amt'], $base_order_data['order_qty'] . ' items should be $' . $expected_order_amt);

        $this->assertEquals($new_cart_resource->cart_ucid, $cart_ucid, "cart ucid should have stayed the same after the add to cart");
        $new_cart_record = CartsAdapter::staticGetRecordByPrimaryKey($new_cart_resource->cart_ucid, "CartsAdapter");
        $this->assertEquals(OrderAdapter::DELIVERY_ORDER, $new_cart_record['order_type'], "Should have switched the ordertype to delivery");
        $this->assertEquals(8.88, $new_cart_record['delivery_amt'], 'Should have added the delivery price');

        $new_cart_order_id = $new_cart_record['order_id'];
        $this->assertEquals($cart_order_id, $new_cart_order_id, "cart order id shoudl have stayed the same");
        $new_base_order_data = CompleteOrder::getBaseOrderData($new_cart_order_id, getM());
        $this->assertEquals($user_address_id, $new_base_order_data['user_delivery_location_id'], " user address id should have been ported to the new cart but it appears not to have.");
        $this->assertNotNull($new_base_order_data['delivery_amt'], "delivery amount should be on checkout data");
        $this->assertEquals(8.88, $new_base_order_data['delivery_amt'], "delivery amount should hav been 8.88");

        $lead_times_array = $new_cart_resource->lead_times_array;
        $first_time = $lead_times_array[0];
        $diff = $first_time - getTomorrowTwelveNoonTimeStampDenver();
        $diff_in_minutes = $diff / 60;
        $this->assertEquals(45, $diff_in_minutes);

        $last_time = array_pop($lead_times_array);
        $last_time_string = date("Y-m-d H:i:s", $last_time);
        $expected_time = getTomorrowTwelveNoonTimeStampDenver() + (3 * 24 * 60 * 60);
        $expected_last_time_string = date('Y-m-d', $expected_time) . ' 20:00:00';
        $this->assertEquals($expected_last_time_string, $last_time_string, "last available time should have been on 3rd day out.");

        $order_data = array();
        $order_data['note'] = "the new cart note";
        $order_data['tip'] = 0.00;
        $payment_array = $new_cart_resource->accepted_payment_types;
        $order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
        $order_data['requested_time'] = $first_time;

        $request = createRequestObject("/apiv2/orders/$cart_ucid", 'post', json_encode($order_data), 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $order_resource = $place_order_controller->processV2Request();

        $base_order_data = CompleteOrder::getBaseOrderData($cart_order_id, getM());
        $expected_order_amt = 7 * 2.00;
        $this->assertEquals($expected_order_amt, $base_order_data['order_amt'], '7 items should be $14.00');


        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;

        $this->assertTrue($order_id > 1000, "should have created a valid order id");
        $this->assertEquals(8.88, $order_resource->delivery_amt, " delivery fee should have been 8.88 but was: " . $order_resource->delivery_amt);
        $this->assertEquals($user_address_id, $order_resource->user_delivery_location_id, " user delivery location id should have been on the order but it was not or it was wrong");

        // format Tue 11:30 AM
        $expected_delivery_time_string = date('D m/d g:i A', $first_time);
        $this->assertEquals($expected_delivery_time_string, $order_resource->requested_delivery_time);

        $this->assertEquals(OrderAdapter::DELIVERY_ORDER, $order_resource->order_type, "It Should be a delivery order");
        $test_data['user'] = $user;
        $test_data['merchant_id'] = $merchant_id;
        $test_data['user_address_id'] = $user_address_id;

        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $order_resource2 = $place_order_controller->processV2Request();
        $this->assertNotNull($order_resource2->error, "It should have thrown an error if we try to submit the order again");
        $this->assertEquals(422, $order_resource2->http_code);
        $this->assertEquals("Sorry, this order has already been submitted. Check your email for order confirmation.", $order_resource2->error);

        return $test_data;
    }

    /**
     * @depends testPlaceDeliveryOrderWithCartAfterStartingWithPickupOrder
     */
    function testAsSoonAsPossibleWithApiV2($test_data)
    {
        $user_id = $test_data['user']['user_id'];
        $merchant_id = $test_data['merchant_id'];
        $user_address_id = $test_data['user_address_id'];
        $user = logTestUserIn($user_id);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note', 5);
        $order_data['user_addr_id'] = $user_address_id;
        $json_encoded_data = json_encode($order_data);

        $request = createRequestObject('/app2/apiv2/cart', 'post', $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $cart_resource = $place_order_controller->processV2Request();
        $this->assertNull($cart_resource->error);
        $this->assertNotNull($cart_resource, "should have gotten a cart resource back");
        $cart_ucid = $cart_resource->ucid;
        $request = createRequestObject('/app2/apiv2/cart/' . $cart_resource->ucid . '/checkout', 'get', $obyd, $m);

        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_data_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_data_resource->error, "should NOT have thrown an error");
        $this->assertNotNull($checkout_data_resource->delivery_amt, "delivery amount should be on checkout data");
        $this->assertEquals(8.88, $checkout_data_resource->delivery_amt, "delivery amount shoudl hav been 8.88");
        $asap_string = 'As soon as possible';
        $checkout_data_resource->lead_times_array[0] = 'As soon as possible';
        $order_resource = placeOrderFromCheckoutResource($checkout_data_resource, $user, $merchant_id, 0.00);

        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000, "should have created a valid order id");
        $this->assertEquals(8.88, $order_resource->delivery_amt, " delivery fee should have been 8.88 but was: " . $order_resource->delivery_amt);
        $this->assertEquals($user_address_id, $order_resource->user_delivery_location_id, " user delivery location id should have been on the order but it was not or it was wrong");

        $this->assertEquals($asap_string, $order_resource->requested_delivery_time);
        $this->assertContains("Your order to Unit Test Merchant has been scheduled for delivery as soon as possible.", $order_resource->user_message);


        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id);
        // check that merchant gets as soon as possible
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id, 'E');
        $email_controller = new EmailController($m, $u, $r, 5);
        $message_to_send_resource = $email_controller->prepMessageForSending($message_resource);
        $body = $message_to_send_resource->message_text;
        $this->assertContains('as soon as possible', strtolower($body));

        //make sure that pickup time shows the order minimum
        $pickup_time_string = $complete_order['pickup_date3'];
        $date_string = date('n/j', getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals("$date_string 12:45PM", $pickup_time_string);
        return $test_data;
    }

    /**
     * @depends testAsSoonAsPossibleWithApiV2
     */
    function testPlaceAdvancedDeliveryOrder($test_data)
    {
        $user_id = $test_data['user']['user_id'];
        $merchant_id = $test_data['merchant_id'];
        $user_address_id = $test_data['user_address_id'];
        $user = logTestUserIn($user_id);

        $hours = getStaticRecords(array("merchant_id" => $merchant_id), 'HourAdapter');
        foreach ($hours as $hour_record) {
            $this->assertEquals("07:00:00", $hour_record['open']);
            $this->assertEquals("20:00:00", $hour_record['close']);
        }
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note', 5);
        $order_data['user_addr_id'] = $user_address_id;
        $json_encoded_data = json_encode($order_data);

        $request = createRequestObject('/app2/apiv2/cart/checkout', 'post', $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);
        $this->assertNotNull($checkout_resource, "should have gotten a cart resource back");
        $ucid = $checkout_resource->ucid;

        //make sure that every time is betweeb 7am and 8pm
        foreach ($checkout_resource->lead_times_array as $ts) {
            $hour = date('H', $ts);
            $this->assertTrue($hour > 6 && $hour < 21, "THere should be no delivery times in the closed times but we found one at: " . date("H:m", $ts));
        }

        $order_data = array();
        $order_data['tip'] = 0.00;
        $payment_array = $checkout_resource->accepted_payment_types;
        $order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
        $lead_times_array = $checkout_resource->lead_times_array;

        // set time for tomorrow.
        $order_data['requested_time'] = $lead_times_array[0] + (3600 * 24);

        $json_encoded_data = json_encode($order_data);
        $request = createRequestObject("/apiv2/orders/$ucid", "post", $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $order_resource = $place_order_controller->processV2Request();
        $this->assertNull($order_resource->error);
        $complete_order = CompleteOrder::getBaseOrderData($order_resource->order_id, $m);
        $requested_delivery_time = $complete_order['requested_delivery_time'];
    }

    function testCreateDeliveryOrder()
    {
        $user_resource = createNewUser(array("flags" => "1C20000001"));
        $user = logTestUserResourceIn($user_resource);

        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"1045 Pine Street","address2":"","city":"boulder","state":"co","zip":"80302","phone_no":"9709262121","lat":40.0197891,"lng":-105.284703}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "Application/json";
        $request->_parseRequestBody();
        $request->method = 'POST';
        $request->url = "/users/" . $user['uuid'] . "/userdeliverylocation";
        $user_controller = new UserController(getM(), $user, $request, 5);
        //$response = $user_controller->setDeliveryAddr();
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;

        $merchant_resource = createNewTestMerchant();
        $merchant_resource->delivery = 'Y';
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);

        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'delivery');
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'pickup');

        $data = array("merchant_id" => $merchant_resource->merchant_id);

        // set merchant delivery info
        $mdia = new MerchantDeliveryInfoAdapter(getM());
        $mdia_resource = $mdia->getExactResourceFromData($data);
        $mdia_resource->minimum_order = 1.00;
        $mdia_resource->delivery_cost = 1.00;
        $mdia_resource->delivery_increment = 15;
        $mdia_resource->max_days_out = 4;
        $mdia_resource->minimum_delivery_time = 45;
        $mdia_resource->save();

        $mdpd = new MerchantDeliveryPriceDistanceAdapter(getM());
        $mdpd_resource = $mdpd->getExactResourceFromData($data);
        $mdpd_resource->distance_up_to = 1000.0;
        $mdpd_resource->price = 1.00;
        $mdpd_resource->save();

        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'some note', 1);
        $order_data['user_addr_id'] = $user_address_id;

        $request = createRequestObject('apiv2/cart/checkout', 'POST', json_encode($order_data), 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request, $log_level);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $resource = $place_order_controller->processV2Request();
        $this->assertNull($resource->error);

        $order_data = array();
        $order_data['tip'] = 0.00;
        $payment_array = $resource->accepted_payment_types;
        $order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
        $lead_times_array = $resource->lead_times_array;
        $order_data['requested_time'] = $lead_times_array[0];
        $order_data['delivery_time'] = "12:45 PM";

        $json_encoded_data = json_encode($order_data);
        $request = createRequestObject("/apiv2/orders/" . $resource->cart_ucid, "post", $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $current_time = getTomorrowTwelveNoonTimeStampDenver();
        $place_order_controller->setCurrentTime($current_time);
        $order_resource = $place_order_controller->processV2Request();
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $base_order_data = CompleteOrder::getBaseOrderData($order_id, $m);
        $day = date('D m/d', $current_time);
        $this->assertEquals("$day 12:45 PM", $base_order_data['requested_delivery_time']);
    }

    function testGetCorrectLoginAdapterForContextWithoutCustomAdapter()
    {
        setContext('com.splickit.worldhq');
        $login_adapter = LoginAdapterFactory::getLoginAdapterForContext();
        $this->assertEquals('LoginAdapter', get_class($login_adapter));
    }

    function testGoodError()
    {
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $user = logTestUserIn($this->ids['user_id']);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;

        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'some note');

        $merchant_resource->ordering_on = 'N';
        $merchant_resource->save();

        $json_encoded_data = json_encode($order_data);
        $request = new Request();
        $request->url = '/app2/apiv2/cart/checkout';
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();

        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $today_1155_pm = getTodayTwelveNoonTimeStampDenver() + (12 * 60 * 60) - 300;
        $time_string = date('Y-m-d H:i:s', $today_1155_pm);
        $place_order_controller->setCurrentTime($today_1150_pm);
        $checkout_data_resource = $place_order_controller->processV2Request();
        $this->assertNotNull($checkout_data_resource->error);
        $this->assertEquals(500, $checkout_data_resource->http_code);
        $this->assertEquals("Sorry, this merchant is not currently accepting mobile/online orders. Please try again soon.", $checkout_data_resource->error);

    }

    function testMenuTypeTimeRangeNearMidnight()
    {
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $user = logTestUserIn($this->ids['user_id']);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;

        $hour_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
        $hours_resources = Resource::findAll(new HourAdapter($m), null, $hour_options);
        foreach ($hours_resources as $hours_resource) {
            $hours_resource->close = '02:00:00';
            $hours_resource->save();
        }

        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'some note');

        $json_encoded_data = json_encode($order_data);
        $request = new Request();
        $request->url = '/app2/apiv2/cart';
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $today_1155_pm = getTodayTwelveNoonTimeStampDenver() + (12 * 60 * 60) - 300;
        $time_string = date('Y-m-d H:i:s', $today_1155_pm);
        $place_order_controller->setCurrentTime($today_1150_pm);
        $cart_resource = $place_order_controller->processV2Request();
        $this->assertNull($cart_resource->error);
        $cart_ucid = $cart_resource->ucid;

        $request = createRequestObject("/apiv2/cart/$cart_ucid/checkout", 'GET');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime($today_1155_pm);
        $checkout_data_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_data_resource->error, "Should NOT have gotten an error");
        $available_times_array = $checkout_data_resource->lead_times_array;
        $this->assertTrue(count($available_times_array) > 0, "It should have a full lead times array but it was empty");
        $expected_first_time_available = '' . date("Y-m-d", getTomorrowTwelveNoonTimeStampDenver()) . ' 00:15:00';
        $actual_first_time_available = date("Y-m-d H:i:s", $available_times_array[0]);
        $this->assertEquals($expected_first_time_available, $actual_first_time_available, "the first time should have been 12:15 am");
        $actual_last_time_available = date("Y-m-d H:i:s", array_pop($available_times_array));
        $expected_last_time_available = '' . date("Y-m-d", getTomorrowTwelveNoonTimeStampDenver()) . ' 01:45:00';
        $this->assertEquals($expected_last_time_available, $actual_last_time_available, "the last time available should be close to 2am");
    }

    function testHoleHoursCorrectDay()
    {
        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;

        $holehours_adapter = new HoleHoursAdapter($mt);
        $start = strtotime("12:00:00");
        $end = strtotime("14:00:00");
        $day = date("w", getTomorrowTwelveNoonTimeStampDenver());
        $holehour_data = array(
            'merchant_id' => $merchant_id,
            'day_of_week' => $day + 1,
            'order_type' => 'Delivery',
            'start_time' => date("G:i:s", $start),
            'end_time' => date("G:i:s", $end)
        );

        $hh_resource = Resource::factory($holehours_adapter, $holehour_data);
        $hh_resource->save();

        $holehours_object = new HoleHoursAdapter($m);
        $holehours = $holehours_object->getByMerchantIdAndOrderType($merchant_resource->merchant_id, 'D');

        $lead_time = new LeadTime($merchant_resource);
        $lead_time->setHoleHours($holehours, getTomorrowTwelveNoonTimeStampDenver() - (3600));

        $set_hole_hours = $lead_time->getCurrentHoleHours();
        $set_hole_hour = array_pop($set_hole_hours);
        $expected_hole_time_start = date('Y-m-d l H:i:s', getTomorrowTwelveNoonTimeStampDenver());
        $actual_hole_time_start = date('Y-m-d l H:i:s', $set_hole_hour['start']);
        $this->assertEquals($expected_hole_time_start, $actual_hole_time_start, "hole start should have been tomorrom 12 noon");
    }

    function testLeadTimeWithHoleHoursForDeliveryOrderApiV1()
    {
        $_SERVER['max_lead'] = 240;
        $user_resource = createNewUser(array("flags" => "1C20000001"));
        $user = logTestUserResourceIn($user_resource);

        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"1045 Pine Street","address2":"","city":"boulder","state":"co","zip":"80302","phone_no":"9709262121","lat":40.0197891,"lng":-105.284703}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "Application/json";
        $request->_parseRequestBody();
        $request->method = 'POST';
        $request->url = "/users/" . $user['uuid'] . "/userdeliverylocation";
        $user_controller = new UserController(getM(), $user, $request, 5);
        //$response = $user_controller->setDeliveryAddr();
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;


        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $merchant_delivery_info_resource = Resource::find(new MerchantDeliveryInfoAdapter(getM()),null,[3=>["merchant_id"=>$merchant_resource->merchant_id]]);
        $merchant_delivery_info_resource->max_days_out = 7;
        $merchant_delivery_info_resource->save();
        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);
        $merchant_id = $merchant_resource->merchant_id;

        $holehours_adapter = new HoleHoursAdapter($mt);
        $start = strtotime("12:00:00");
        $end = strtotime("14:00:00");

        $hole_hours_per_day = array();

        $day = date("w", getTomorrowTwelveNoonTimeStampDenver()) + 1;
        $holehour_data = array(
            'merchant_id' => $merchant_id,
            'day_of_week' => $day,
            'order_type' => 'Delivery',
            'start_time' => date("G:i:s", $start),
            'end_time' => date("G:i:s", $end)
        );

        $hh_resource = Resource::factory($holehours_adapter, $holehour_data);
        $hh_resource->save();
        $hole_hours_per_day[$day] = $holehour_data;


        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', 'some note');
        $order_data['user_addr_id'] = $user_address_id;

        $request = createRequestObject("/apiv2/cart/checkout","POST",json_encode($order_data),'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request, $log_level);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver() - (85 * 60));
        $resource = $place_order_controller->processV2Request();
        $leadtime_array = $resource->lead_times_array;
        foreach ($leadtime_array as $ts) {
            $time_string = date('Y-m-d H:i:s', $ts);
            myerror_log("available time: $time_string");
            $this->assertFalse($ts > getTomorrowTwelveNoonTimeStampDenver() && $ts < getTomorrowTwelveNoonTimeStampDenver() + (2 * 60 * 60), "There should have been no times in the donut hole. Failed time was: $time_string");
            if ($ts > getTomorrowTwelveNoonTimeStampDenver() + (2 * 60 * 60)) {
                $there_are_available_times_outside_of_donut_hole_after = true;
            }
            if ($ts < getTomorrowTwelveNoonTimeStampDenver()) {
                $there_are_available_times_outside_of_donut_hole_before = true;
            }
        }
        $this->assertTrue($there_are_available_times_outside_of_donut_hole_before, "It should have available times before donut hole");
        $this->assertTrue($there_are_available_times_outside_of_donut_hole_after, "It should have available times after donut hole");

        // now try checkout inside hole hours
        $json_encoded_data = json_encode($order_data);
        $url = '/app2/apiv2/cart/checkout';
        $request = createRequestObject($url, 'post', $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);
        $cart_ucid = $checkout_resource->cart_ucid;
        $leadtime_array = $checkout_resource->lead_times_array;
        $first_time = $leadtime_array[0];
        $first_time_string = date('Y-m-d H:i:s', $first_time);
        $day = date('Y-m-d', getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals("$day 14:00:00", $first_time_string, "first time should have been 2 oclock");


        // now check for ASAP failure if its within donut hole
        $order_data = array();
        $order_data['tip'] = 0.00;
        $order_data['delivery_time'] = "As soon as possible";
        $order_data['delivery'] = 'yes';
        $payment_array = $checkout_resource->accepted_payment_types;
        $order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
        //$lead_times_array = $checkout_resource->lead_times_array;
        $order_data['actual_pickup_time'] = "As soon as possible";

        $json_encoded_data = json_encode($order_data);
        $request = createRequestObject("/apiv2/orders/$cart_ucid", "post", $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $current_time = $time == null ? getTomorrowTwelveNoonTimeStampDenver() : $time;
        $place_order_controller->setCurrentTime($current_time);
        $order_resource = $place_order_controller->processV2Request();


        //$order_resource = placeOrderFromOrderData($order_data, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNotNull($order_resource->error);
        $this->assertEquals("We're sorry, this merchant is currently out of delivery hours, and cannot deliver 'As soon as possible'. Please choose a delivery time from the drop down list.", $order_resource->error);

    }

    function testLeadTimeWithHoleHoursForPickupOrderApiV1()
    {
        $_SERVER['max_lead'] = 240;
        $user_resource = createNewUser(array("flags" => "1C20000001"));
        $user = logTestUserResourceIn($user_resource);
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;

        $holehours_adapter = new HoleHoursAdapter($mt);
        $start = strtotime("12:00:00");
        $end = strtotime("14:00:00");
        $hole_hours_per_day = array();
        for ($day = 1; $day < 8; $day++) {
            $holehour_data = array(
                'merchant_id' => $merchant_id,
                'day_of_week' => $day,
                'order_type' => 'Pickup',
                'start_time' => date("G:i:s", $start),
                'end_time' => date("G:i:s", $end)
            );

            $hh_resource = Resource::factory($holehours_adapter, $holehour_data);
            $hh_resource->save();
            $hole_hours_per_day[$day] = $holehour_data;
        }

        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'some note');
        $request = createRequestObject("/apiv2/cart/checkout","POST",json_encode($order_data),'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request, $log_level);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver() - (60 * 60));
        $resource = $place_order_controller->processV2Request();
        $leadtime_array = $resource->lead_times_array;
        foreach ($leadtime_array as $ts) {
            myerror_log("available time: " . date('Y-m-d H:i:s', $ts));
            $this->assertFalse($ts > getTomorrowTwelveNoonTimeStampDenver() && $ts < getTomorrowTwelveNoonTimeStampDenver() + (2 * 60 * 60), "There shoudl have been no times in teh donut hole");
            if ($ts > getTomorrowTwelveNoonTimeStampDenver() + (2 * 60 * 60)) {
                $there_are_available_times_outside_of_donut_hole_after = true;
            }
            if ($ts < getTomorrowTwelveNoonTimeStampDenver()) {
                $there_are_available_times_outside_of_donut_hole_before = true;
            }
        }

        $this->assertTrue($there_are_available_times_outside_of_donut_hole_before, "It should have available times before donut hole");
        $this->assertTrue($there_are_available_times_outside_of_donut_hole_after, "It should have available times after donut hole");
    }

    function testFailedCreditCardShouldSetStatusToNandDoNotCreateNewCart()
    {
        $user_adapter = new UserAdapter();
        $options[TONIC_FIND_BY_METADATA]['uuid'] = '1234-5678-9012-3456';
        if ($user_resource = Resource::find($user_adapter, null, $options)) {
            $user = logTestUserResourceIn($user_resource);
        } else {
            $user_resource = createNewUserWithCCNoCVV();
            $user_resource->uuid = '1234-5678-9012-3456';
            if ($user_resource->save()) {
                $user = logTestUserResourceIn($user_resource);
            } else {
                throw new Exception("cant create cc fail user");
            }
        }


        $order_data_cart = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'], 'pickup', null, 1);

        $json_encoded_data = json_encode($order_data_cart);
        $request = new Request();
        $request->url = '/app2/apiv2/cart/checkout';
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_data_resource = $place_order_controller->processV2Request();
        $starting_cart_id = $checkout_data_resource->cart_ucid;
        $this->assertNull($checkout_data_resource->error);

        $cart_record_intial = CartsAdapter::staticGetRecordByPrimaryKey($checkout_data_resource->cart_ucid, 'CartsAdapter');

        $code = generateCode(7);
        $_SERVER['STAMP'] = __CLASS__ . '-' . $code;
        $_SERVER['RAW_STAMP'] = $code;

        $declined_stamp = $code;

        $order_data = array();
        $new_cart_note = "the bad one baby";
        $order_data['note'] = $new_cart_note;
        $order_data['tip'] = (rand(100, 1000)) / 100;
        $payment_array = $checkout_data_resource->accepted_payment_types;
        $order_data['merchant_payment_type_map_id'] = $payment_array[1]['merchant_payment_type_map_id'];
        $lead_times_array = $checkout_data_resource->lead_times_array;
        $order_data['actual_pickup_time'] = $lead_times_array[5];
        // this should be ignored;
        $order_data['lead_time'] = 100000;

        $order_resource = $this->placeOrder($order_data, $checkout_data_resource->cart_ucid, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals("We're sorry but your credit card was declined.", $order_resource->error);

        $code = generateCode(7);
        $_SERVER['STAMP'] = __CLASS__ . '-' . $code;
        $_SERVER['RAW_STAMP'] = $code;

        //Cart should have a status of 'N'
        $cart_record = CartsAdapter::staticGetRecord(array("ucid" => $starting_cart_id), 'CartsAdapter');
        $this->assertEquals('N', $cart_record['status'], "cart should show a delined payment");
        $stamps = explode(';', $cart_record['stamp']);
        $this->assertCount(2, $stamps);
        $last_stamp = $stamps[0];
        $this->assertContains($declined_stamp, $last_stamp);

        //now change the uuid to something that passes
        $user_resource->uuid = createUUID();
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);

        $code = generateCode(7);
        $_SERVER['STAMP'] = __CLASS__ . '-' . $code;
        $_SERVER['RAW_STAMP'] = $code;

        $new_order_resource = $this->placeOrder($order_data, $checkout_data_resource->cart_ucid, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($new_order_resource->error);

        $cart_record = CartsAdapter::staticGetRecordByPrimaryKey($new_order_resource->ucid, 'Carts');
        $this->assertEquals('O', $cart_record['status']);
        $this->assertNull($cart_record['payment_file']);
        // check amounts becuase of the fail
        $expected_grand_total = $new_order_resource->order_amt+$new_order_resource->promo_amt+$new_order_resource->total_tax_amt+$new_order_resource->trans_fee_amt+$new_order_resource->delivery_amt+$new_order_resource->tip_amt;
        $this->assertEquals($expected_grand_total,$new_order_resource->grand_total,"Totals should be equal after the credit card fail");

        // check to make sure note is there
        $this->assertEquals($new_cart_note, $cart_record['note'], "note should have been added to the order");

        // check to make sure we have all 4 stamps
        $st = explode(';', $cart_record['stamp']);
        $this->assertCount(3, $st, "there should be 4 stamps on the order");

    }

    function testCartStuff()
    {
        $data['user_id'] = $this->ids['user_id'];
        $data['merchant_id'] = $this->ids['merchant_id'];
        $cart1 = CartsAdapter::createCart($data);

        $user_resource = createNewUser();
        $data['user_id'] = $user_resource->user_id;
        $cart2 = CartsAdapter::createCart($data);

        $rc1 = Resource::find(new CartsAdapter($m), "" . $cart1->ucid);
        unset($cart1->insert_id);
        $this->assertEquals($cart1->cleanResource(), $rc1->cleanResource());

        $rc2 = Resource::find(new CartsAdapter($m), "" . $cart2->ucid);
        unset($cart2->insert_id);
        $this->assertEquals($cart2->cleanResource(), $rc2->cleanResource());

        $this->assertNull(Resource::find(new CartsAdapter($m), generateUUID()));
    }

    function testDeleteLastItemFromCart()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'sum dum note');
        $r = createRequestObject("/apiv2/cart/","POST",json_encode($order_data),'application/json');
        $placeorder_controller = new PlaceOrderController(getM(), $user, $r);
        $placeorder_controller->setCurrentTime(getTodayTwelveNoonTimeStampDenver());
        $cart_resource = $placeorder_controller->processV2Request();
        $cart_record = CartsAdapter::staticGetRecordByPrimaryKey($cart_resource->ucid, 'CartsAdapter');
        $order_id_before_delete = $cart_record['order_id'];

        // now lets try to delete the item.
        $order_summary = $cart_resource->order_summary;
        $first_item = $order_summary['cart_items'][0];
        $this->assertNotNull($first_item);

        // now lets delete it from the cart
        $cart_ucid = $cart_resource->ucid;
        $first_item_order_detail_id = $first_item['order_detail_id'];

        $request = createRequestObject("/apiv2/cart/$cart_ucid/cartitem/$first_item_order_detail_id", 'DELETE');
        $placeorder_controller = new PlaceOrderController(getM(), $user, $request);
        $placeorder_controller->setCurrentTime(getTodayTwelveNoonTimeStampDenver());
        $cart_resource = $placeorder_controller->processV2Request();
        $this->assertNull($cart_resource->error);
        $cart_record = CartsAdapter::staticGetRecordByPrimaryKey($cart_resource->ucid, 'CartsAdapter');
        $this->assertNotNull($cart_record, "should still have a valid cart");
        $this->assertNull($cart_resource->order_summary);
    }

    function testGetCartItemsFromOrderId()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $menu_id = createTestMenuWithNnumberOfItems(3);

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 3);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        // add an item note
        $order_data['items'][0]['note'] = "this is the item note";

        //create one hold it
        //array_shift($order_data['items'][0]['mods']);
        unset($order_data['items'][0]['mods'][1]);
        $order_resource = placeOrderFromOrderData($order_data, getTodayTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $complete_order = CompleteOrder::getCompleteOrderAsResource($order_id, getM());
        $order_details = $complete_order->order_details;
        $order_detail = $order_details[0];
        $mods = $order_detail['order_detail_modifiers'];
        $this->assertCount(2, $mods);
        $hold_it_mods = $order_detail['order_detail_hold_it_modifiers'];
        $this->assertCount(1, $hold_it_mods);

        $cart_items = CompleteOrder::getCartItemsFromOrderId($order_id);
        $this->assertNotNull($cart_items, 'should have gotten cart items back');
        $this->assertCount(1, $cart_items, 'there should be one cart item');
        $item = $cart_items[0];
        $this->assertEquals("this is the item note", $item['note']);
        $this->assertNotNull($item['order_detail_id']);
        $this->assertNotNull($item['item_id']);
        $this->assertNotNull($item['size_id']);
        $this->assertEquals(1, $item['quantity']);
        $mods = $item['mods'];
        $this->assertCount(2, $mods);
        $mod = $mods[0];
        $this->assertNotNull($mod['mod_quantity']);
        $this->assertNotNull($mod['modifier_item_id']);
    }

    function testPlaceCartOrderWithTransactionFee()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_resource->trans_fee_type = 'F';
        $merchant_resource->trans_fee_rate = .25;
        $merchant_resource->save();
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);
        $merchant_payment_type_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);

        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);

        $order_data1 = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_resource->merchant_id);

        $json_encoded_data = json_encode($order_data1);
        $request = new Request();
        $request->url = '/app2/apiv2/cart/checkout';
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $cart_resource = $place_order_controller->processV2Request();

        $order_record = OrderAdapter::staticGetRecordByPrimaryKey($cart_resource->oid_test_only, "OrderAdapter");
        $this->assertEquals(.25, $order_record['trans_fee_amt'], 'Should have included a transaction fee of .25');
        $reciept_items = createHashmapFromArrayOfArraysByFieldName($cart_resource->order_summary['receipt_items'], 'title');
        $this->assertEquals("$0.25", $reciept_items['Convenience Fee']['amount']);

        $new_cart_note = "the new cart note";
        $order_data['note'] = $new_cart_note;
        $order_data['tip'] = (rand(100, 1000)) / 100;
        $payment_array = $cart_resource->accepted_payment_types;
        $order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
        $lead_times_array = $cart_resource->lead_times_array;
        $order_data['actual_pickup_time'] = $lead_times_array[0];
        // this should be ignored;
        $order_data['lead_time'] = 100000;

        $order_resource = $this->placeOrder($order_data, $cart_resource->cart_ucid, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id_new = $order_resource->order_id;

        $order_record_new = OrderAdapter::staticGetRecordByPrimaryKey($order_id_new, "OrderAdapter");
        $this->assertEquals(.25, $order_record_new['trans_fee_amt'], "should have a .25 transaction fee");

        $new_reciept_items = createHashmapFromArrayOfArraysByFieldName($order_resource->order_summary['receipt_items'], 'title');
        $this->assertEquals("$0.25", $new_reciept_items['Convenience Fee']['amount']);

    }

    function testCreateCart()
    {
        $user_resource = createNewUser();
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'], 'pickup', $note);
        $request = new Request();
        $request->url = '/app2/apiv2/cart';
        $request->method = "post";
        $request->mimetype = 'application/json';
        $request->body = json_encode($order_data);
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $cart_resource = $place_order_controller->processV2Request();
        $this->assertNotNull($cart_resource, "should have created a cart");
        //$this->assertTrue($cart_resource->insert_id > 999,"should have a valid cart id");
        $this->assertNotNull($cart_resource->ucid, "cart should have a unique identifier");
        $data['user'] = $user;
        $data['ucid'] = $cart_resource->ucid;
        return $data;
    }

    /**
     * @depends testCreateCart
     */
    function testAddPromoCodeToCartBadCode($data)
    {
        $ucid = $data['ucid'];
        $request = new Request();
        $request->url = "http://localhost/app2/apiv2/cart/$ucid/checkout?promo_code=badpromocode";
        $request->method = "get";
        $request->mimetype = 'application/json';
        $request->data = array("promo_code" => 'badpromocode');
        $place_order_controller = new PlaceOrderController(getM(), $data['user'], $request);
        $promo_resource_result = $place_order_controller->processV2Request();
        $this->assertEquals("Sorry!  The promo code you entered, badpromocode, is not valid.", $promo_resource_result->error);
        $this->assertEquals("promo", $promo_resource_result->error_type);
        $this->assertEquals(422, $promo_resource_result->http_code);

    }

    /**
     * @expectedException NoDataPassedInForCartCreationException
     */
    function testCreateCartNoData()
    {
        $user_resource = createNewUser();
        $user = logTestUserResourceIn($user_resource);
        $request = new Request();
        $request->url = '/app2/apiv2/cart';
        $request->method = "post";
        $request->mimetype = 'application/json';
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $cart_resource = $place_order_controller->processV2Request();
    }

    function testAddToCart()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id']);

        $json_encoded_data = json_encode($order_data);
        $request = new Request();
        $request->url = '/app2/apiv2/cart';
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        //$cart_resource = $place_order_controller->createNewCart();
        $cart_resource = $place_order_controller->processV2Request();

        $this->assertNotNull($cart_resource, "should have gotten a cart resource back");
        //$this->assertTrue($cart_resource->insert_id > 999,"should have a valid cart id");
        $this->assertNotNull($cart_resource->ucid, "cart should have a unique identifier");
        $order_summary = $cart_resource->order_summary['cart_items'];
        $this->assertCount(1, $order_summary);
        $item = $order_summary[0];
        $this->assertNotNull($item['item_name'], "should have found an item name");
        $this->assertNotNull($item['item_price'], "shoudl have found an item price");
        $this->assertNotNull($item['item_quantity'], "should have found an item quantity");
        $this->assertNotNull($item['item_description'], "should have found the list of mods");
        $this->assertNotNull($item['order_detail_id'], 'should have found an order detail id on the cart summary hash');
        $receipt_items_array = $cart_resource->order_summary['receipt_items'];
        $receipt_items = createHashOfRecieptItemsByTitle($receipt_items_array);
        $sub_total = $receipt_items['Subtotal'];
        $this->assertEquals('$2.00', $sub_total);
        $tax = $receipt_items['Tax'];
        $this->assertEquals('$0.20', $tax);

        $full_cart_resource = SplickitController::getResourceFromId($cart_resource->ucid, 'Carts');

        $order_record = OrderAdapter::staticGetRecordByPrimaryKey($full_cart_resource->order_id, 'Order');
        $status = $order_record['status'];
        $this->assertEquals('Y', $status, 'Order status should be set to Y so cart does not expire');
        return $full_cart_resource;

    }

    /**
     *
     * @depends testAddToCart
     */
    function testAddToExistingCart($cart_resource)
    {
        $user = logTestUserIn($cart_resource->user_id);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id']);

        $json_encoded_data = json_encode($order_data);
        $request = new Request();
        $request->url = '/apiv2/cart/' . $cart_resource->ucid;
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        //$new_cart_resource = $placeorder_controller->addToCart($order_data, $cart_resource->ucid);
        $new_cart_resource = $place_order_controller->processV2Request();
        $new_cart_record = CartsAdapter::staticGetRecordByPrimaryKey($new_cart_resource->ucid, 'CartsAdapter');
        $this->assertNull($new_cart_resource->error);
        $this->assertEquals($cart_resource->order_id, $new_cart_record['order_id'], "cart should have the same order id");
        $order_summary = $new_cart_resource->order_summary['cart_items'];
        $this->assertCount(2, $order_summary, "order summary should now have two items");
        $item = $order_summary[1];
        $this->assertNotNull($item['item_name'], "should have found an item name");
        $this->assertNotNull($item['item_price'], "shoudl have found an item price");
        $this->assertNotNull($item['item_quantity'], "should have found an item quantity");
        $this->assertNotNull($item['item_description'], "should have found the list of mods");
        $receipt_items_array = $new_cart_resource->order_summary['receipt_items'];
        $receipt_items = createHashOfRecieptItemsByTitle($receipt_items_array);
        $sub_total = $receipt_items['Subtotal'];
        $this->assertEquals('$4.00', $sub_total);
        $tax = $receipt_items['Tax'];
        $this->assertEquals('$0.40', $tax);

        $full_cart_resource = SplickitController::getResourceFromId($new_cart_resource->ucid, 'Carts');

        $order_record = OrderAdapter::staticGetRecordByPrimaryKey($full_cart_resource->order_id, 'Order');
        $status = $order_record['status'];
        $this->assertEquals('Y', $status, 'Order status should be set to Y so cart does not expire');
        return $full_cart_resource;
    }

    /**
     * @depends testAddToExistingCart
     */
    function testGetCheckoutDataForExistingCart($cart_resource)
    {
        $_SERVER['device_type'] = 'web-unit-testing';
        $cart_ucid = $cart_resource->ucid;
        $starting_order_id = $cart_resource->order_id;
        $user = logTestUserIn($cart_resource->user_id);

        $request = new Request();
        $request->url = "/apiv2/cart/$cart_ucid/checkout";
        $request->method = "get";
        $request->mimetype = 'application/json';

        $placeorder_controller = new PlaceOrderController(getM(), $user, $request);
        $placeorder_controller->setCurrentTime(getTodayTwelveNoonTimeStampDenver());
        //$checkout_data_resource = $placeorder_controller->getCheckoutDataFromCartId($cart_resource->ucid);
        $checkout_data_resource = $placeorder_controller->processV2Request();
        $this->assertNull($checkout_data_resource->error);
        $this->assertNotNull($checkout_data_resource->lead_times_array . "Should have found a lead times array");
        $this->assertNotNull($checkout_data_resource->tip_array, "Should have found the tip array");
        $this->assertEquals(4.00, $checkout_data_resource->order_amt);
        $this->assertEquals(0.40, $checkout_data_resource->total_tax_amt);
        $this->assertEquals($cart_ucid, $checkout_data_resource->cart_ucid, "should have found the cart ucid on the checkout data response");
        $this->assertNotNull($checkout_data_resource->order_summary, "Should have found an order summary");
        // check to make sure we used the exising cart rather than create a new order
        $options[TONIC_FIND_BY_SQL] = "SELECT * FROM Orders WHERE user_id = " . $user['user_id'] . " ORDER BY order_id DESC LIMIT 1";
        $order_resource = Resource::find(new OrderAdapter($m), null, $options);
        $this->assertEquals($starting_order_id, $order_resource->order_id, 'should not have created a new order id, shoudl have used the existing record');
        $this->assertEquals('Y', $order_resource->status, "Shouldn't have Z records anymore");
        $payment_array = $checkout_data_resource->accepted_payment_types;
        $this->assertCount(2, $payment_array);
        return $cart_resource->getRefreshedResource();
    }

    /**
     * @depends testGetCheckoutDataForExistingCart
     */
    function testSubmitExistingCart($cart_resource)
    {
        $user = logTestUserIn($cart_resource->user_id);
        $user_id = $user['user_id'];
        $ucid = $cart_resource->ucid;
        $request = createRequestObject("/apiv2/cart/$ucid/checkout",'GET');
        $placeorder_controller = new PlaceOrderController(getM(), $user, $request);
        $placeorder_controller->setCurrentTime(getTodayTwelveNoonTimeStampDenver());
        $checkout_data_resource = $placeorder_controller->processV2Request();

        $cart_record = CartsAdapter::staticGetRecordByPrimaryKey($cart_resource->ucid, 'CartsAdapter');
        $this->assertEquals($cart_resource->order_id, $cart_record['order_id'], "checkout data should not have created a new order id");

        $new_cart_note = "the new cart note";
        $order_data['merchant_id'] = $this->ids['merchant_id'];
        $order_data['note'] = $new_cart_note;
        $order_data['user_id'] = $user_id;
        $order_data['cart_ucid'] = $cart_resource->ucid;
        $order_data['tip'] = (rand(100, 1000)) / 100;
        $payment_array = $checkout_data_resource->accepted_payment_types;
        $order_data['merchant_payment_type_map_id'] = $payment_array[1]['merchant_payment_type_map_id'];
        $lead_times_array = $checkout_data_resource->lead_times_array;
        $order_data['actual_pickup_time'] = $lead_times_array[0];
        // this should be ignored;
        $order_data['lead_time'] = 100000;

        $order_resource = $this->placeOrder($order_data, $checkout_data_resource->cart_ucid, getTodayTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000, "should have created a valid order id");
        $this->assertEquals($cart_resource->order_id, $order_id, "new order id should have been the same as the cart");

        $cart_record = CartsAdapter::staticGetRecordByPrimaryKey($order_resource->ucid, 'Carts');
        $this->assertEquals('O', $cart_record['status']);
        $submitted_ts = $cart_record['order_dt_tm'];
        //$submitted_date_time = date('Y-m-d H:i',$submitted_ts);
        $this->assertEquals($submitted_ts, date('Y-m-d H:i:00', getTodayTwelveNoonTimeStampDenver()));
        $this->assertEquals($checkout_data_resource->total_tax_amt, $order_resource->total_tax_amt);
        $this->assertEquals($checkout_data_resource->order_amt, $order_resource->order_amt);

        // check to make sure note is there
        $base_order_data = OrderAdapter::staticGetRecordByPrimaryKey($order_id, 'OrderAdapter');
        $this->assertEquals($new_cart_note, $base_order_data['note'], "note should have been added to the order");

        // check to make sure we have all 4 stamps
        $st = explode(';', $base_order_data['stamp']);
        $this->assertCount(4, $st, "there should be 4 stamps on the order");

        return $cart_resource->getRefreshedResource();
    }

    /**
     * @depends testSubmitExistingCart
     */
    function testAddToSubmittedCart($cart_resource)
    {
        $user = logTestUserIn($cart_resource->user_id);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id']);

        $json_encoded_data = json_encode($order_data);
        $request = new Request();
        $request->url = '/apiv2/cart/' . $cart_resource->ucid;
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        //$new_cart_resource = $placeorder_controller->addToCart($order_data, $cart_resource->ucid);
        $new_cart_resource = $place_order_controller->processV2Request();

        $this->assertNotNull($new_cart_resource->error, "Should have gotten an error because the cart has already been submitted");
        $this->assertEquals("Sorry, this order has already been submitted. Check your email for order confirmation.", $new_cart_resource->error);

    }

    function testLeadTimeWithHoleHoursForPickopOrders()
    {
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $user_resource = createNewUser(array("flags" => "1C20000001"));
        $user_id = $user_resource->user_id;
        $user = logTestUserIn($user_id);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;

        $holehours_adapter = new HoleHoursAdapter($mt);
        $start = strtotime("10:00:00");
        $end = strtotime("14:00:00");
        $hole_hours_per_day = array();
        for ($day = 1; $day < 8; $day++) {
            $holehour_data = array(
                'merchant_id' => $merchant_id,
                'day_of_week' => $day,
                'order_type' => 'Pickup',
                'start_time' => date("G:i:s", $start + (rand(15, 30) * 60)),
                'end_time' => date("G:i:s", $end + (rand(15, 30) * 60))
            );

            $hh_resource = Resource::factory($holehours_adapter, $holehour_data);
            $hh_resource->save();
            $hole_hours_per_day[$day] = $holehour_data;
        }

        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'some note');

        $json_encoded_data = json_encode($order_data);
        $request = new Request();
        $request->url = '/app2/apiv2/cart';
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);

        $cart_resource = $place_order_controller->processV2Request();
        $this->assertNull($cart_resource->error);
        $cart_ucid = $cart_resource->ucid;

        $request = new Request();
        $request->url = "/apiv2/cart/$cart_ucid/checkout";
        $request->method = "get";
        $request->mimetype = 'application/json';

        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_data_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_data_resource->error);
        $this->assertEquals("Please note, your first available pickup time for this order is over 2 hours from now.", $checkout_data_resource->user_message);

        $lead_time = new LeadTime($merchant_resource);
        $holehours = $holehours_adapter->getByMerchantIdAndOrderType($merchant_id, $cart_resource->order_type);

        $lead_time->setHoleHours($holehours, $place_order_controller->current_time);

        $checkout_lead_time_values = $checkout_data_resource->lead_times_array;
        $merchat_hole_hours_value = $lead_time->getCurrentHoleHours();

        $holehour_selected = $merchat_hole_hours_value[date('N', $place_order_controller->current_time)];
        $holehour_selected_per_day = $hole_hours_per_day[date('N', $place_order_controller->current_time)];

        $this->assertEquals($holehour_selected_per_day['start_time'], date("G:i:s", $holehour_selected['start']));
        $this->assertEquals($holehour_selected_per_day['end_time'], date("G:i:s", $holehour_selected['end']));

        $count = array();
        foreach ($merchat_hole_hours_value as $day => $holehour) {
            $count[$day] = array_filter($checkout_lead_time_values, function ($lead_time) {
                return $lead_time > $holehour['start'] && $lead_time < $holehour['end'];
            });
        }
        $count = array_filter($count, function ($e) {
            return count($e) > 0;
        });
        $this->assertTrue(0 == count($count));
    }

    function testMenuTypeTimeRange()
    {
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $menu_type_adapter = new MenuTypeAdapter(getM());
        $menu_type_resource = $menu_type_adapter->getExactResourceFromData(array("menu_id" => $menu_id));
        $menu_type_resource->end_time = '03:15:00';
        $menu_type_resource->save();

        $user_resource = createNewUser(array("flags" => "1C20000001"));
        $user_id = $user_resource->user_id;
        $user = logTestUserIn($user_id);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'some note');

        $json_encoded_data = json_encode($order_data);
        $request = new Request();
        $request->url = '/app2/apiv2/cart';
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $cart_resource = $place_order_controller->processV2Request();
        $this->assertNull($cart_resource->error);
        $cart_ucid = $cart_resource->ucid;

        $request = new Request();
        $request->url = "/apiv2/cart/$cart_ucid/checkout";
        $request->method = "get";
        $request->mimetype = 'application/json';

        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_data_resource = $place_order_controller->processV2Request();
        $this->assertNotNull($checkout_data_resource->error, "Should have gotten an error because the item is not availble anymore");
        $this->assertEquals("Sorry the Test Item 1 is not available after 3:15 am. Please remove it from your cart before placing your order.", $checkout_data_resource->error);

        $menu_type_resource->end_time = '12:30:00';
        $menu_type_resource->save();

        $checkout_data_resource = $place_order_controller->processV2Request();
        $last_time_available = array_pop($checkout_data_resource->lead_times_array);
        $expected_last_time_available = getTomorrowTwelveNoonTimeStampDenver() + 1800;
        $this->assertNull($checkout_data_resource->error);
        $this->assertEquals(date("Y-m-d H:i", $expected_last_time_available), date("Y-m-d H:i", $last_time_available), 'Last time available should have been 12:30');
        $this->assertEquals("Please Note: Your available times are limited by the Test Item 1 in your cart. Its not available after 12:30 pm.", $checkout_data_resource->user_message);

        // make test for not available untill
        $menu_type_resource->start_time = '14:00:00';
        $menu_type_resource->end_time = '23:59:00';
        $menu_type_resource->save();

        $checkout_data_resource = $place_order_controller->processV2Request();
        $first_time_available = $checkout_data_resource->lead_times_array[0];
        $expected_first_time_available = getTomorrowTwelveNoonTimeStampDenver() + 7200; // this shoudl be 2:00pm
        $this->assertNull($checkout_data_resource->error);
        $this->assertEquals(date("Y-m-d H:i", $expected_first_time_available), date("Y-m-d H:i", $first_time_available), 'First time available should have been 2:00pm');
        $this->assertEquals("Please Note: Your available times are limited by the Test Item 1 in your cart. Its not available until 2:00 pm.", $checkout_data_resource->user_message);

    }

    function testAllModifierTypesWithCart()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $menu_id = createTestMenuWithNnumberOfItems(3);

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 3);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);

        $sides_modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1, 'Sides', 'S');
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $sides_modifier_group_resource->modifier_group_id, 0);

        $mealdeal_modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1, 'Meal Deal', 'I');
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $mealdeal_modifier_group_resource->modifier_group_id, 0);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'pickup','sum dum note',3);

        //$order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'sum dum note', 3);
        $order_data['items'][0]['note'] = "first";
        $order_data['items'][1]['note'] = "second";
        $order_data['items'][2]['note'] = "third";
        $r = createRequestObject('/apiv2/cart','POST',json_encode($order_data),'application/json');
        $placeorder_controller = new PlaceOrderController(getM(), $user, $r);
        $placeorder_controller->setCurrentTime(getTodayTwelveNoonTimeStampDenver());
        $cart_resource = $placeorder_controller->processV2Request();
        $this->assertNull($cart_resource->error);
        $this->assertNotNull($cart_resource->ucid);
        return $cart_resource;
    }

    /**
     * @depends  testAllModifierTypesWithCart
     */
    function testCartSummaryWithSideModifierItem($cart_resource)
    {
        $order_summary = $cart_resource->order_summary;
        $cart_items_hash = createHashmapFromArrayOfArraysByFieldName($order_summary['cart_items'], 'item_name');
        $side_item = $cart_items_hash['Test Item 2'];
        $this->assertEquals('Sides Item 1', $side_item['item_description']);
    }

    /**
     * @depends  testAllModifierTypesWithCart
     */
    function testCartSummaryWithMealDealModifierItem($cart_resource)
    {
        $order_summary = $cart_resource->order_summary;
        $cart_items_hash = createHashmapFromArrayOfArraysByFieldName($order_summary['cart_items'], 'item_name');
        $meal_deal_item = $cart_items_hash['Test Item 3'];
        $this->assertEquals('Meal Deal Item 1', $meal_deal_item['item_description']);
    }

    /**
     * @depends  testAllModifierTypesWithCart
     */
    function testGetCartWithAllTypesOfModifiers($cart_resource)
    {
        $cart_record = CartsAdapter::staticGetRecordByPrimaryKey($cart_resource->ucid, 'CartsAdapter');
        $order_id = $cart_record['order_id'];
        $items = CompleteOrder::getCartItemsFromOrderId($order_id);
        $item_hash = createHashmapFromArrayOfArraysByFieldName($items, 'note');
        $side_item = $item_hash['second'];
        $this->assertEquals(1, sizeof($side_item['mods']), "should have a mods list of 1");
        $meal_deal_item = $item_hash['third'];
        $this->assertEquals(1, sizeof($meal_deal_item['mods']), "should have a mods list of 1");
    }

    function testDeleteItemFromCart()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'sum dum note', 3);
        $order_data['items'][0]['note'] = "first";
        $order_data['items'][1]['note'] = "second";
        $order_data['items'][2]['note'] = "third";
        $r = createRequestObject('/apiv2/cart','POST',json_encode($order_data),'application/json');
        $placeorder_controller = new PlaceOrderController(getM(), $user, $r);
        $placeorder_controller->setCurrentTime(getTodayTwelveNoonTimeStampDenver());
        $cart_resource = $placeorder_controller->processV2Request();
        $cart_record = CartsAdapter::staticGetRecordByPrimaryKey($cart_resource->ucid, 'CartsAdapter');
        $order_id_before_delete = $cart_record['order_id'];

        // now lets try to delete the first item.
        $order_summary = $cart_resource->order_summary;
        $this->assertCount(3, $order_summary['cart_items']);
        foreach ($order_summary['cart_items'] as $cart_item) {
            if ($cart_item['item_note'] == 'first') {
                $first_item = $cart_item;
            }
        }
        $this->assertNotNull($first_item);

        // now lets delete it from the cart then place the order
        $cart_ucid = $cart_resource->ucid;
        $first_item_order_detail_id = $first_item['order_detail_id'];
        $request = new Request();
        $request->url = "/apiv2/cart/$cart_ucid/cartitem/$first_item_order_detail_id";
        $request->method = "delete";
        $request->mimetype = 'application/json';

        //$cart_resource = $placeorder_controller->deleteItemFromCart($cart_resource->ucid, $first_item['order_detail_id']);
        $placeorder_controller = new PlaceOrderController(getM(), $user, $request);
        $placeorder_controller->setCurrentTime(getTodayTwelveNoonTimeStampDenver());
        $cart_resource = $placeorder_controller->processV2Request();
        $cart_record = CartsAdapter::staticGetRecordByPrimaryKey($cart_resource->ucid, 'CartsAdapter');
        $order_id_after_delete = $cart_record['order_id'];

        $order_summary_after_delete = $cart_resource->order_summary;
        $this->assertCount(2, $order_summary_after_delete['cart_items']);
        foreach ($order_summary_after_delete['cart_items'] as $cart_item) {
            if ($cart_item['item_note'] == 'first') {
                $new_first_item = $cart_item;
            }
        }
        $this->assertNull($new_first_item, "item should no longer be present since it was deleted");
        $this->assertEquals($order_id_after_delete, $order_id_before_delete, "order id's should be the same after a delete since we dont need a new cart");

        $co_request = createRequestObject('/apiv2/cart/'.$cart_resource->ucid.'/checkout',"GET");
        $placeorder_controller = new PlaceOrderController($m,$user,$co_request);
        $placeorder_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_data_resource = $placeorder_controller->processV2Request();
        $order_summary_on_checkout = $checkout_data_resource->order_summary;
        $this->assertEquals($order_summary_after_delete['receipt_items'], $order_summary_on_checkout['receipt_items'], "order summaries should be the same after delet and get checkout");
        $this->assertNull($checkout_data_resource->error);
        $new_order_data['merchant_id'] = $this->ids['merchant_id'];
        $new_order_data['note'] = "the new cart note";
        $new_order_data['user_id'] = $user_id;
        $new_order_data['cart_ucid'] = $cart_resource->ucid;
        $new_order_data['tip'] = (rand(100, 1000)) / 100;
        $payment_array = $checkout_data_resource->accepted_payment_types;
        $new_order_data['merchant_payment_type_map_id'] = $payment_array[0]['id'];
        $lead_times_array = $checkout_data_resource->lead_times_array;
        $order_data['actual_pickup_time'] = $lead_times_array[0];
        // this should be ignored;
        //$order_data['lead_time'] = 100000;
        $order_resource = placeOrderFromOrderData($new_order_data, getTodayTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $ordered_cart_items = CompleteOrder::getCartItemsFromOrderId($order_id);
        $this->assertCount(2, $ordered_cart_items);

        foreach ($ordered_cart_items as $ordered_cart_item) {
            if ($ordered_cart_item['item_note'] == 'first') {
                $new_ordered_first_item = $ordered_cart_item;
            }
        }
        $this->assertNull($new_ordered_first_item);
        $this->assertEquals($order_id_before_delete, $order_id, "order id should have been the same thoughout the entire process");

    }

    function testSubmitBadCartId()
    {
        $user_resource = createNewUser();
        $user = logTestUserResourceIn($user_resource);

        $request = createRequestObject("/apiv2/cart/ert45ert345dr/checkout","GET");
        $placeorder_controller = new PlaceOrderController(getM(), $user, $request);
        $checkout_data_resource = $placeorder_controller->processV2Request();
        $this->assertNotNull($checkout_data_resource->error);
        $this->assertEquals("Sorry, we cannot find your cart, it may have expired. Sorry for the inconvenience.", $checkout_data_resource->error);
    }

    function testCreateCartAddtoCartGetCheckoutForCartInsingleCall()
    {
        $_SERVER['device_type'] = 'web-unit-testing';
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours', 3);
        $order_data['items'][0]['note'] = 'skip hours';

        $json_encoded_data = json_encode($order_data);
        $request = new Request();
        $request->url = '/app2/apiv2/cart/checkout';
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_data_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_data_resource->error);
        $this->assertNotNull($checkout_data_resource->lead_times_array . "Should have found a lead times array");
        $this->assertNotNull($checkout_data_resource->tip_array, "Should have found the tip array");
        $this->assertEquals(6.00, $checkout_data_resource->order_amt);
        $this->assertEquals(0.60, $checkout_data_resource->total_tax_amt);
        $this->assertNotNull($checkout_data_resource->cart_ucid, "should have found the cart ucid on the checkout data response");

        $payment_array = $checkout_data_resource->accepted_payment_types;
        $this->assertCount(2, $payment_array);
    }

    function testMakeSureLargeOrderHasCorrectMessageSendTime()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $order_data_cart = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours', 10);

        $json_encoded_data = json_encode($order_data_cart);
        $request = new Request();
        $request->url = '/app2/apiv2/cart/checkout';
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_data_resource = $place_order_controller->processV2Request();

        $new_cart_note = "the new cart note";
        $order_data['merchant_id'] = $this->ids['merchant_id'];
        $order_data['note'] = $new_cart_note;
        $order_data['user_id'] = $user['user_id'];
        $order_data['cart_ucid'] = $checkout_data_resource->cart_ucid;
        $order_data['tip'] = (rand(100, 1000)) / 100;
        $payment_array = $checkout_data_resource->accepted_payment_types;
        $order_data['merchant_payment_type_map_id'] = $payment_array[1]['merchant_payment_type_map_id'];
        $lead_times_array = $checkout_data_resource->lead_times_array;
        $order_data['actual_pickup_time'] = $lead_times_array[0];
        // this should be ignored;
        $order_data['lead_time'] = 100000;

        $order_resource = $this->placeOrder($order_data, $checkout_data_resource->cart_ucid, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $order_record = OrderAdapter::staticGetRecordByPrimaryKey($order_id, 'OrderAdapter');

        $expected_pickup_time_string = date("Y-m-d H:i:s", getTomorrowTwelveNoonTimeStampDenver() + (41 * 60));
        $tomorow_date_string = date('Y-m-d', getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals("$tomorow_date_string 12:35:00", $order_record['pickup_dt_tm'], "FIrst Pickup time should have been 12:35");

        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id, 'E');
        $message_send_time = date('Y-m-d H:i:s', $message_resource->next_message_dt_tm);
        $this->assertEquals("$tomorow_date_string 12:00:00", $message_send_time, 'Messages send time should have been set to current time.');
    }

    function testPlaceDeliveryOrderWithCart()
    {
        $merchant_resource = createNewTestMerchant();
        $merchant_resource->delivery = 'Y';
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);
        $billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);
        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);


        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'delivery');
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'pickup');

        $data = array("merchant_id" => $merchant_resource->merchant_id);

        // set merchant delivery info
        $mdia = new MerchantDeliveryInfoAdapter(getM());
        $mdia_resource = $mdia->getExactResourceFromData($data);
        $mdia_resource->minimum_order = 10.00;
        $mdia_resource->delivery_cost = 1.00;
        $mdia_resource->delivery_increment = 15;
        $mdia_resource->max_days_out = 4;
        $mdia_resource->minimum_delivery_time = 45;
        $mdia_resource->save();

        $mdpd = new MerchantDeliveryPriceDistanceAdapter(getM());
        $mdpd_resource = $mdpd->getExactResourceFromData($data);
        $this->assertNotNull($mdpd_resource, "should have found a merchant delivery price distance resource");

        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "Application/json";
        $request->_parseRequestBody();
        $request->method = 'POST';
        $request->url = "/users/" . $user['uuid'] . "/userdeliverylocation";
        $user_controller = new UserController(getM(), $user, $request, 5);
        //$response = $user_controller->setDeliveryAddr();
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id", 'GET', $body, $mimetype);
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range), "should have found the 'is in delivery range' field");
        $this->assertFalse($resource->is_in_delivery_range, " the is in delivery range should be false");

        // change distance to be 10 miles
        $mdpd_resource->distance_up_to = 10.0;
        $mdpd_resource->price = 8.88;
        $mdpd_resource->save();
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range), "should have found the 'is in delivery range' field");
        $this->assertTrue($resource->is_in_delivery_range, " the is in delivery range should be true");
        $this->assertEquals($mdpd_resource->price, $resource->price);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note');
        $order_data['user_addr_id'] = $user_address_id;
        $json_encoded_data = json_encode($order_data);

        $url = '/app2/apiv2/cart';
        $request = createRequestObject($url, 'post', $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        //$cart_resource = $place_order_controller->createNewCart();
        $cart_resource = $place_order_controller->processV2Request();
        $cart_ucid = $cart_resource->ucid;
        $this->assertNull($cart_resource->error);
        $this->assertNotNull($cart_resource, "should have gotten a cart resource back");
        //$this->assertTrue($cart_resource->insert_id > 999,"should have a valid cart id");

        $full_cart_resource = SplickitController::getResourceFromId($cart_ucid, 'Carts');

        $cart_order_id = $full_cart_resource->order_id;
        $base_order_data = CompleteOrder::getBaseOrderData($cart_order_id, getM());
        $this->assertEquals($user_address_id, $base_order_data['user_delivery_location_id']);
        $this->assertEquals(8.88, $base_order_data['delivery_amt']);

        // now add more to the cart and see if it transfers
        unset($order_data['user_addr_id']);
        $request = createRequestObject("/app2/apiv2/cart/$cart_ucid", 'post', json_encode($order_data), 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $new_cart_resource = $place_order_controller->processV2Request();
        //$new_cart_resource = $place_order_controller->addToCart($order_data, $cart_ucid);
        $this->assertEquals($new_cart_resource->ucid, $cart_ucid, "cart ucid should have stayed the same after the add to cart");
        $new_cart_record = CartsAdapter::staticGetRecordByPrimaryKey($new_cart_resource->ucid, "CartsAdapter");

        $new_cart_order_id = $new_cart_record['order_id'];
        $this->assertEquals($cart_order_id, $new_cart_order_id, "cart order id shoudl have stayed the same");
        $new_base_order_data = CompleteOrder::getBaseOrderData($new_cart_order_id, getM());
        $this->assertEquals($user_address_id, $new_base_order_data['user_delivery_location_id'], " user address id should have been ported to the new cart but it appears not to have.");
        $this->assertEquals(8.88, $new_base_order_data['delivery_amt']);

        $request = createRequestObject('/app2/apiv2/cart/' . $cart_resource->ucid . '/checkout', 'get', $obyd, $m);

        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_data_resource = $place_order_controller->processV2Request();
        $this->assertNotNull($checkout_data_resource->error, "should have thrown an error becuase delivery mininum has not been met");
        $this->assertEquals("Minimum order required! You have not met the minimum subtotal of $10.00 for your deliver area.", $checkout_data_resource->error);
        $this->assertEquals(422, $checkout_data_resource->http_code, "Should return a 422 http code");

        //reset minimum
        $mdia_resource->minimum_order = 1.00;
        $mdia_resource->save();

        $checkout_data_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_data_resource->error, "should NOT have thrown an error becuase delivery mininum has NOW been met");
        $this->assertNotNull($checkout_data_resource->delivery_amt, "delivery amount should be on checkout data");
        $this->assertEquals(8.88, $checkout_data_resource->delivery_amt, "delivery amount shoudl hav been 8.88");

        $lead_times_array = $checkout_data_resource->lead_times_array;
        $first_time = $lead_times_array[0];
        $diff = $first_time - getTomorrowTwelveNoonTimeStampDenver();
        $diff_in_minutes = $diff / 60;
        $this->assertEquals(45, $diff_in_minutes);

        $last_time = array_pop($lead_times_array);
        $last_time_string = date("Y-m-d H:i:s", $last_time);
        $expected_time = getTomorrowTwelveNoonTimeStampDenver() + (3 * 24 * 60 * 60);
        $expected_last_time_string = date('Y-m-d', $expected_time) . ' 20:00:00';
        $this->assertEquals($expected_last_time_string, $last_time_string, "last available time should have been on 3rd day out.");

        $order_data['merchant_id'] = $merchant_id;
        $order_data['note'] = "the new cart note";
        $order_data['user_id'] = $user_id;
        $order_data['cart_ucid'] = $cart_resource->ucid;
        $order_data['tip'] = (rand(100, 1000)) / 100;
        $payment_array = $checkout_data_resource->accepted_payment_types;
        $order_data['merchant_payment_type_map_id'] = $payment_array[1]['merchant_payment_type_map_id'];
        $order_data['requested_time'] = $first_time;

        $order_resource = $this->placeOrder($order_data, $checkout_data_resource->cart_ucid, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000, "should have created a valid order id");
        $this->assertEquals(8.88, $order_resource->delivery_amt, " delivery fee should have been 8.88 but was: " . $order_resource->delivery_amt);
        $this->assertEquals($user_address_id, $order_resource->user_delivery_location_id, " user delivery location id should have been on the order but it was not or it was wrong");

        // format Tue 11:30 AM
        $expected_delivery_time_string = date('D m/d g:i A', $first_time);
        $this->assertEquals($expected_delivery_time_string, $order_resource->requested_delivery_time);
        $test_data['user'] = $user;
        $test_data['merchant_id'] = $merchant_id;
        $test_data['user_address_id'] = $user_address_id;

        return $test_data;
    }

    /**
     * @depends testPlaceDeliveryOrderWithCart
     */
    function testSwitchOrderTypePickupToDelivery($data)
    {
        $merchant_id = $data['merchant_id'];
        $user = $data['user'];
        logTestUserIn($user['user_id']);

        //first checkout pickup order type
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'the note', 4);
        $order_data['submitted_order_type'] = 'pickup';
        $json_encoded_data = json_encode($order_data);
        $pickup_request = createRequestObject('/app2/apiv2/cart/checkout', 'POST', $json_encoded_data, 'application/json');

        $place_order_controller = new PlaceOrderController(getM(), $user, $pickup_request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $cart_resource = $place_order_controller->processV2Request();
        $cart_id = $cart_resource->cart_ucid;
        $order_record = OrderAdapter::staticGetRecord(array("ucid" => $cart_id), 'OrderAdapter');

        $this->assertEquals(0,$cart_resource->delivery_amt, "not have delivery amt because is pickup order");
        $this->assertEquals(OrderAdapter::PICKUP_ORDER, $order_record['order_type'], "saved pickup order");
        $this->assertEquals("0.00", $order_record['delivery_amt'], "not have delivery amt because is pickup order");

        $order_data['user_addr_id'] = $data['user_address_id'];
        $order_data['submitted_order_type'] = 'delivery';

        $delivery_request = createRequestObject("/app2/apiv2/cart/$cart_id/checkout", 'POST', json_encode($order_data), 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $delivery_request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $updated_cart_resource = $place_order_controller->processV2Request();
        $updated_order_record = OrderAdapter::staticGetRecord(array("ucid" => $cart_id), 'OrderAdapter');

        $this->assertEquals($cart_id, $updated_cart_resource->cart_ucid, "is same order");

        $this->assertEquals(OrderAdapter::DELIVERY_ORDER, $updated_order_record['order_type'], "saved delivery order");
        $this->assertNotNull($updated_cart_resource->delivery_amt, "have delivery amt because is delivery order");
        $this->assertNotNull($updated_order_record['delivery_amt'], "have delivery amt because is delivery order");
        $this->assertEquals($updated_cart_resource->delivery_amt, $updated_order_record['delivery_amt'], "have delivery amt because");

        unset($order_data['user_addr_id']);
        $order_data['submitted_order_type'] = 'pickup';
        $request = createRequestObject("/app2/apiv2/cart/$cart_id/checkout", 'POST', json_encode($order_data), 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $last_cart_resource = $place_order_controller->processV2Request();
        $last_order_record = OrderAdapter::staticGetRecord(array("ucid" => $cart_id), 'OrderAdapter');

        $this->assertEquals($cart_id, $last_cart_resource->cart_ucid, "is same order");

        $this->assertEquals(OrderAdapter::PICKUP_ORDER, $last_order_record['order_type'], "saved pickup order");
        $this->assertEquals(0,$last_cart_resource->delivery_amt, "not have delivery amt because is pickup order");
        $this->assertNotNull($last_order_record['delivery_amt'], "not have delivery amt because is pickup order");
        $this->assertEquals("0.00", $last_order_record['delivery_amt'], "not have delivery amt because is pickup order");

        $lead_times_array = $last_cart_resource->lead_times_array;
        $first_time = $lead_times_array[0];

        $order_data['note'] = "the new cart note";
        $order_data['user_id'] = $user['user_id'];
        $order_data['cart_ucid'] = $last_cart_resource->cart_ucid;
        $order_data['tip'] = 0.00;
        $payment_array = $last_cart_resource->accepted_payment_types;
        $order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
        $order_data['requested_time'] = $first_time;

        $order_resource = $this->placeOrder($order_data, $last_cart_resource->cart_ucid, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals("0.00", "0.00", "not have delivery amt because is pickup order");
    }

    /**
     * @depends testPlaceDeliveryOrderWithCart
     */
    function testSwitchOrderTypeDeliveryToPickup($data)
    {
        $merchant_id = $data['merchant_id'];
        $user = $data['user'];
        logTestUserIn($user['user_id']);

        //first checkout delivery order type
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note', 4);
        $order_data['user_addr_id'] = $data['user_address_id'];
        $order_data['submitted_order_type'] = 'delivery';

        $json_encoded_data = json_encode($order_data);
        $pickup_request = createRequestObject('/app2/apiv2/cart/checkout', 'POST', $json_encoded_data, 'application/json');

        $place_order_controller = new PlaceOrderController(getM(), $user, $pickup_request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $cart_resource = $place_order_controller->processV2Request();
        $cart_id = $cart_resource->cart_ucid;
        $order_record = OrderAdapter::staticGetRecord(array("ucid" => $cart_id), 'OrderAdapter');

        $this->assertEquals(OrderAdapter::DELIVERY_ORDER, $order_record['order_type'], "saved delivery order");
        $this->assertNotNull($cart_resource->delivery_amt, "have delivery amt because is delivery order");
        $this->assertNotNull($order_record['delivery_amt'], "have delivery amt because is delivery order");
        $this->assertEquals($cart_resource->delivery_amt, $order_record['delivery_amt'], "have delivery amt because");

        unset($order_data['user_addr_id']);
        $order_data['submitted_order_type'] = 'pickup';

        $delivery_request = createRequestObject("/app2/apiv2/cart/$cart_id/checkout", 'POST', json_encode($order_data), 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $delivery_request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $updated_cart_resource = $place_order_controller->processV2Request();
        $updated_order_record = OrderAdapter::staticGetRecord(array("ucid" => $cart_id), 'OrderAdapter');

        $this->assertEquals($cart_id, $updated_cart_resource->cart_ucid, "is same order");

        $this->assertEquals(0,$updated_cart_resource->delivery_amt, "not have delivery amt because is pickup order");
        $this->assertEquals(OrderAdapter::PICKUP_ORDER, $updated_order_record['order_type'], "saved pickup order");
        $this->assertEquals("0.00", $updated_order_record['delivery_amt'], "not have delivery amt because is pickup order");

        $order_data['user_addr_id'] = $data['user_address_id'];
        $order_data['submitted_order_type'] = 'delivery';

        $request = createRequestObject("/app2/apiv2/cart/$cart_id/checkout", 'POST', json_encode($order_data), 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $last_cart_resource = $place_order_controller->processV2Request();
        $last_order_record = OrderAdapter::staticGetRecord(array("ucid" => $cart_id), 'OrderAdapter');

        $this->assertEquals($cart_id, $last_cart_resource->cart_ucid, "is same order");

        $this->assertEquals(OrderAdapter::DELIVERY_ORDER, $last_order_record['order_type'], "saved delivery order");
        $this->assertNotNull($last_cart_resource->delivery_amt, "have delivery amt because is delivery order");
        $this->assertNotNull($last_order_record['delivery_amt'], "have delivery amt because is delivery order");
        $this->assertEquals($last_cart_resource->delivery_amt, $last_order_record['delivery_amt'], "have delivery amt because is delivery order");
    }


    /**
     * @depends testPlaceDeliveryOrderWithCart
     */
    function testFreeDeliveryPromo($test_data)
    {
        $merchant_id = $test_data['merchant_id'];
        $brand_id = getBrandIdFromCurrentContext();
        $promo_adapter = new PromoAdapter($m);
        $promo_id = 801;
        $sql = "INSERT INTO `Promo` VALUES($promo_id, 'free delivery test promo', 'Free Delivery', 300, 'Y', 'N', 0, 2, 'N', 'N','delivery','2010-01-01', '2020-01-01', 2, 0, 0, 0.00, 0, 0.00, 'Y', 'N',0, $brand_id,NOW(), NOW(), 'N')";
        $promo_adapter->_query($sql);
        $pmm_resource = Resource::createByData(new PromoMerchantMapAdapter(getM()), array("merchant_id" => $merchant_id, "promo_id" => $promo_id));
        $sql = "INSERT INTO `Promo_Message_Map` VALUES(null, $promo_id, 'Congratulations! You''re getting free delivery!', 'Congratulations! You''re getting free delivery!', NULL, NULL, NULL, now())";
        $promo_adapter->_query($sql);

        $pkwm_adapter = new PromoKeyWordMapAdapter($m);
        Resource::createByData($pkwm_adapter, array("promo_id" => $promo_id, "promo_key_word" => "freedelivery", "brand_id" => getBrandIdFromCurrentContext()));


        $user = $test_data['user'];
        $user_address_id = $test_data['user_address_id'];
        logTestUserIn($user['user_id']);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note', 4);
        $order_data['user_addr_id'] = $user_address_id;
        $json_encoded_data = json_encode($order_data);
        $request = new Request();
        $request->url = '/app2/apiv2/cart';
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        //$cart_resource = $place_order_controller->createNewCart();
        $cart_resource = $place_order_controller->processV2Request();
        $cart_ucid = $cart_resource->ucid;

        $request = createRequestObject('/app2/apiv2/cart/' . $cart_resource->ucid . '/checkout','GET');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_data_resource_before_promo = $place_order_controller->processV2Request();
        $this->assertNotNull($checkout_data_resource_before_promo->delivery_amt, "delivery amount should be on checkout data");
        $this->assertEquals(8.88, $checkout_data_resource_before_promo->delivery_amt, "delivery amount shoudl hav been 8.88");

        $request = createRequestObject("/app2/apiv2/cart/$cart_ucid/checkout?promo_code=freedelivery", 'GET');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $promo_resource_result = $place_order_controller->processV2Request();
        $this->assertNull($promo_resource_result->error);
        $this->assertEquals("-8.88", $promo_resource_result->promo_amt);
        $this->assertEquals("Congratulations! You're getting free delivery!", $promo_resource_result->user_message);
        $this->assertEquals("8.88", $promo_resource_result->delivery_amt);
        $receipt_items = $promo_resource_result->order_summary['receipt_items'];
        $receipt_items_hash = createHashmapFromArrayOfArraysByFieldName($receipt_items, 'title');

        $payment_array = $promo_resource_result->accepted_payment_types;
        $promo_resource_result->accepted_payment_types = array($payment_array[1]);

        $order_resource = placeOrderFromCheckoutResource($promo_resource_result,$user,$merchant_id,0.00);

       // $order_resource = $this->placeOrder($submit_order_data, $cart_ucid, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000, "should have created a valid order id");
        $this->assertEquals(8.88, $order_resource->delivery_amt, " delivery fee should have been 0.00 but was: " . $order_resource->delivery_amt);
        $this->assertEquals($user_address_id, $order_resource->user_delivery_location_id, " user delivery location id should have been on the order but it was not or it was wrong");
        return $test_data;

    }

    /**
     * @depends testFreeDeliveryPromo
     */
    function testUserPromoRecordWasAdded($test_data)
    {
        $user = $test_data['user'];
        $record = getStaticRecord(array("user_id" => $user['user_id']), 'PromoUserMapAdapter');
        $this->assertNotNull($record);
        $this->assertEquals(1, $record['times_used'], 'should show times used as 1');
        $this->assertEquals(2, $record['times_allowed'], 'should show times used as 2 since thats what is in the promo record');

    }

    /**
     * @depends testFreeDeliveryPromo
     */
    function testFreeDeliveryPromoNonDeliveryOrder($test_data)
    {
        $merchant_id = $test_data['merchant_id'];
        $user = $test_data['user'];
        logTestUserIn($user['user_id']);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'the note', 4);
        $json_encoded_data = json_encode($order_data);
        $request = new Request();
        $request->url = '/app2/apiv2/cart';
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $cart_resource = $place_order_controller->processV2Request();
        $cart_ucid = $cart_resource->ucid;

        $request = createRequestObject('/app2/apiv2/cart/' . $cart_resource->ucid . '/checkout','GET');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_data_resource_before_promo = $place_order_controller->processV2Request();

        $request = new Request();
        $request->url = "/app2/apiv2/cart/$cart_ucid/checkout?promo_code=freedelivery";
        $request->method = "get";
        $request->mimetype = 'application/json';
        $request->data = array("promo_code" => "freedelivery");
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $promo_resource_result = $place_order_controller->processV2Request();
        $this->assertNotNull($promo_resource_result->error);
        $this->assertEquals("Sorry! This promo is only valid on delivery orders.", $promo_resource_result->error);
        $this->assertEquals(422, $promo_resource_result->http_code);
    }

    function testDeliveryPromoFixedAmount()
    {
        $brand_id = getBrandIdFromCurrentContext();
        $merchant_id = $this->ids['merchant_id'];
        $user_id = $this->ids['user_id'];

        $mdpd = new MerchantDeliveryPriceDistanceAdapter($mt);
        $mdpd_resource = $mdpd->getExactResourceFromData(array('merchant_id' => $merchant_id));
        $mdpd_resource->distance_up_to = 10.0;
        $mdpd_resource->price = 8.88;
        $mdpd_resource->save();

        $user = logTestUserIn($user_id);

        $json = '{"user_addr_id":null,"user_id":"' . $user_id . '","name":"","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
        $request = createRequestObject("/users/" . $user['uuid'] . "/userdeliverylocation", 'POST', $json, "application/json");

        $user_controller = new UserController(getM(), $user, $request, 5);
        //$response = $user_controller->setDeliveryAddr();
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id", 'GET', $body, $mimetype);
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $promo_adapter = new PromoAdapter($mt);
        $promo_id = 802;
        $sql = "INSERT INTO `Promo` VALUES($promo_id, 'delivery discount promo', '$2 Discount on Delivery', 300, 'Y', 'N', 0, 2, 'N', 'N','delivery', '2010-01-01', '2020-01-01', 2, 0, 0, 0.00, 0, 0.00, 'Y', 'N',0,$brand_id, NOW(), NOW(), 'N')";
        $promo_adapter->_query($sql);

        Resource::createByData(new PromoMerchantMapAdapter($mt), array("merchant_id" => $merchant_id, "promo_id" => $promo_id));

//        $sql = "INSERT INTO `Promo_Message_Map` VALUES(null, $promo_id, 'Congratulations! You''re getting $2 discount on delivery!', NULL, NULL, NULL, NULL, now())";
//        $promo_adapter->_query($sql);
        $sql = "INSERT INTO `Promo_Message_Map` VALUES(null, $promo_id, 'Sum dum place holder', NULL, NULL, NULL, NULL, now())";
        $promo_adapter->_query($sql);


        $pkwm_adapter = new PromoKeyWordMapAdapter($m);
        Resource::createByData($pkwm_adapter, array("promo_id" => $promo_id, "promo_key_word" => "deliverydiscount$2", "brand_id" => getBrandIdFromCurrentContext()));

        Resource::createByData(new PromoDeliveryAmountMapAdapter($mt), array('promo_id' => $promo_id, 'percent_off' => 0.00, 'fixed_off' => 2.00));

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note', 4);
        $order_data['user_addr_id'] = $user_address_id;
        $order_data['submitted_order_type'] = 'delivery';

        $json_encoded_data = json_encode($order_data);
        $request = createRequestObject('/app2/apiv2/cart/checkout', 'POST', $json_encoded_data, 'application/json');

        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $cart_resource = $place_order_controller->processV2Request();
        $cart_ucid = $cart_resource->cart_ucid;

        $this->assertNotNull($cart_resource->delivery_amt, "delivery amount should be on checkout data");
        $this->assertEquals(8.88, $cart_resource->delivery_amt, "delivery amount should hav been 8.88");

        $request = createRequestObject('/app2/apiv2/cart/'.$cart_ucid.'/checkout?promo_code=deliverydiscount$2', 'GET', $bd, $mt);
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $promo_resource_result = $place_order_controller->processV2Request();
        $this->assertNull($promo_resource_result->error);
        $this->assertEquals("-2.00", $promo_resource_result->promo_amt);
        $this->assertEquals("Congratulations! You're getting $2.00 off of your delivery charge!", $promo_resource_result->user_message);
        $this->assertEquals("8.88", $promo_resource_result->delivery_amt);
        $receipt_items = $promo_resource_result->order_summary['receipt_items'];
        $receipt_items_hash = createHashmapFromArrayOfArraysByFieldName($receipt_items, 'title');

        $submit_order_data['merchant_id'] = $merchant_id;
        $submit_order_data['note'] = "the new cart note";
        $submit_order_data['user_id'] = $user['user_id'];
        $submit_order_data['cart_ucid'] = $cart_ucid;
        $submit_order_data['tip'] = (rand(100, 1000)) / 100;
        $payment_array = $promo_resource_result->accepted_payment_types;
        $submit_order_data['merchant_payment_type_map_id'] = $payment_array[1]['merchant_payment_type_map_id'];

        $lead_times_array = $promo_resource_result->lead_times_array;
        $first_time = $lead_times_array[0];

        $submit_order_data['requested_time'] = $first_time;

        $order_resource = $this->placeOrder($submit_order_data, $cart_ucid, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000, "should have created a valid order id");
        $this->assertEquals(8.88, $order_resource->delivery_amt, " delivery fee should have been 6.88 but was: " . $order_resource->delivery_amt);
        $this->assertEquals($user_address_id, $order_resource->user_delivery_location_id, " user delivery location id should have been on the order but it was not or it was wrong");

    }

    function testDeliveryPromoPercentAmount()
    {
        $merchant_id = $this->ids['merchant_id'];
        $user_id = $this->ids['user_id'];

        $mdpd = new MerchantDeliveryPriceDistanceAdapter($mt);
        $mdpd_resource = $mdpd->getExactResourceFromData(array('merchant_id' => $merchant_id));
        $mdpd_resource->distance_up_to = 10.0;
        $mdpd_resource->price = 8.88;
        $mdpd_resource->save();

        $user = logTestUserIn($user_id);

        $json = '{"user_addr_id":null,"user_id":"' . $user_id . '","name":"","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
        $request = createRequestObject("/users/" . $user['uuid'] . "/userdeliverylocation", 'POST', $json, "application/json");

        $user_controller = new UserController(getM(), $user, $request, 5);
        //$response = $user_controller->setDeliveryAddr();
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id", 'GET', $body, $mimetype);
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $brand_id = getBrandIdFromCurrentContext();
        $promo_adapter = new PromoAdapter($mt);
        $promo_id = 803;
        $sql = "INSERT INTO `Promo` VALUES($promo_id, 'delivery discount promo', '20% Discount on Delivery', 300, 'Y', 'N', 0, 2, 'N', 'N','delivery', '2010-01-01', '2020-01-01', 2, 0, 0, 0.00, 0, 0.00, 'Y', 'N',0,$brand_id, NOW(), NOW(), 'N')";
        $promo_adapter->_query($sql);

        Resource::createByData(new PromoMerchantMapAdapter($mt), array("merchant_id" => $merchant_id, "promo_id" => $promo_id));

        $sql = "INSERT INTO `Promo_Message_Map` VALUES(null, $promo_id, 'Congratulations! You''re getting 20% discount on delivery!', NULL, NULL, NULL, NULL, now())";
        $promo_adapter->_query($sql);


        $pkwm_adapter = new PromoKeyWordMapAdapter($m);
        Resource::createByData($pkwm_adapter, array("promo_id" => $promo_id, "promo_key_word" => "deliverydiscount20%off", "brand_id" => getBrandIdFromCurrentContext()));

        Resource::createByData(new PromoDeliveryAmountMapAdapter($mt), array('promo_id' => $promo_id, 'percent_off' => 20.00, 'fixed_off' => 0.00));

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note', 4);
        $order_data['user_addr_id'] = $user_address_id;
        $order_data['submitted_order_type'] = 'delivery';

        $json_encoded_data = json_encode($order_data);
        $request = createRequestObject('/app2/apiv2/cart/checkout', 'POST', $json_encoded_data, 'application/json');

        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $cart_resource = $place_order_controller->processV2Request();
        $cart_ucid = $cart_resource->cart_ucid;

        $this->assertNotNull($cart_resource->delivery_amt, "delivery amount should be on checkout data");
        $this->assertEquals(8.88, $cart_resource->delivery_amt, "delivery amount should hav been 8.88");

        $request = createRequestObject("/app2/apiv2/cart/$cart_ucid/checkout?promo_code=deliverydiscount20%off", 'GET', $bd, $mt);
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $promo_resource_result = $place_order_controller->processV2Request();
        $this->assertNull($promo_resource_result->error);
        $this->assertEquals("-1.78", $promo_resource_result->promo_amt);
        $this->assertEquals("Congratulations! You're getting $".-$promo_resource_result->promo_amt." off of your delivery charge!", $promo_resource_result->user_message);
        $this->assertEquals("8.88", $promo_resource_result->delivery_amt);
        $receipt_items = $promo_resource_result->order_summary['receipt_items'];
        $receipt_items_hash = createHashmapFromArrayOfArraysByFieldName($receipt_items, 'title');

        $this->assertNotNull($receipt_items_hash['Delivery Fee']);
        $this->assertNotNull($receipt_items_hash['Promo Discount']);
        $this->assertEquals('$-1.78', $receipt_items_hash['Promo Discount']['amount']);

        $submit_order_data['merchant_id'] = $merchant_id;
        $submit_order_data['note'] = "the new cart note";
        $submit_order_data['user_id'] = $user['user_id'];
        $submit_order_data['cart_ucid'] = $cart_ucid;
        $submit_order_data['tip'] = (rand(100, 1000)) / 100;
        $payment_array = $promo_resource_result->accepted_payment_types;
        $submit_order_data['merchant_payment_type_map_id'] = $payment_array[1]['merchant_payment_type_map_id'];

        $lead_times_array = $promo_resource_result->lead_times_array;
        $first_time = $lead_times_array[0];

        $submit_order_data['requested_time'] = $first_time;

        $order_resource = $this->placeOrder($submit_order_data, $cart_ucid, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000, "should have created a valid order id");
        $this->assertEquals(8.88, $order_resource->delivery_amt, " delivery fee should have been 6.88 but was: " . $order_resource->delivery_amt);
        $this->assertEquals($user_address_id, $order_resource->user_delivery_location_id, " user delivery location id should have been on the order but it was not or it was wrong");

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
        $merchant_resource = createNewTestMerchant($menu_id);
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $ids['merchant_id'] = $merchant_resource->merchant_id;

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
    ApiCartTest::main();
}

?>