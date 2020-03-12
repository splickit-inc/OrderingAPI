<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class DeliveryMultiTypeTest extends PHPUnit_Framework_TestCase
{

    var $stamp;
    var $ids;
    var $door_dash_delivery_price_resource;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $code = generateCode(7);
        $_SERVER['STAMP'] = __CLASS__ . '-' . $code;
        $_SERVER['RAW_STAMP'] = $code;
        $this->ids = $_SERVER['unit_test_ids'];
        $_SERVER['force_doordash_estimate_fail'] = false;
        setContext('com.splickit.worldhq');
        //setContext('com.splickit.fuddruckers');

    }

    function tearDown()
    {
        //delete your instance
        unset($this->ids);
        unset($_SERVER['max_lead']);
    }

    function testDeliveryPriceBasedOnDoorDashOnly()
    {

//        $time = strtotime('2019-09-17T18:40:20.000000Z');
//        $string = date('Y-m-d H:i:s',$time);
//
//        $time2 = strtotime('2019-09-17T12:41:00-06:00');
//        $string2 = date('Y-m-d H:i:s',$time2);

        $merchant_resource = createNewTestMerchant(0,['long_hours'=>true]);
        $merchant_resource->delivery = 'Y';
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;

        MerchantMessageMapAdapter::createMerchantMessageMap($merchant_id,'FUC','1234567890','X');



        $data = array("merchant_id" => $merchant_resource->merchant_id);

        //$prep_resource = Resource::createByData(new MerchantPreptimeInfoAdapter(getM()), array("merchant_id"=>$merchant_resource->merchant_id,"entree_preptime_seconds"=>120,"delivery_throttling_on"=>'N'));


        // set merchant delivery info
        $mdia = new MerchantDeliveryInfoAdapter(getM());
        $mdia_resource = $mdia->getExactResourceFromData($data);
        $mdia_resource->minimum_order = 0.01;
        $mdia_resource->delivery_price_type = 'Doordash';
        $mdia_resource->delivery_cost = 1.00;
        $mdia_resource->delivery_increment = 15;
        $mdia_resource->max_days_out = 3;
        $mdia_resource->save();

        //map it to a menu
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $this->ids['menu_id'], 'pickup');
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $this->ids['menu_id'], 'delivery');

        // now edit the distance price records.
        $mdpd = new MerchantDeliveryPriceDistanceAdapter(getM());
        $mdpd_resource = $mdpd->getExactResourceFromData($data);
        $mdpd_resource->name = 'Doordash';
        $mdpd_resource->distance_up_to = 100;
        $mdpd_resource->price = 5.00;
        $mdpd_resource->save();

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        //outside
        $udla = new UserDeliveryLocationAdapter(getM());
        $data = array("user_id"=>$user_id,"address1"=>"450 main street","city"=>"Lyons","state"=>"CO","zip"=>"80540","phone_no"=>"1234567890","lat"=>40.224877,"lng"=> -105.271122);
        $udla_resource = Resource::createByData($udla, $data);
        $udl_id = $udla_resource->user_addr_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$udl_id?log_level=5",'GET');
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertTrue($resource->is_in_delivery_range," the is in delivery range should be true");
        $this->assertEquals(DeliveryController::DOORDASH_ESTIMATE_USER_MESSAGE,$resource->user_message);

        //make sure cache didn't happen
        $mudc_adatper = new UserDeliveryLocationMerchantPriceMapsAdapter(getM());
        $record = $mudc_adatper->getRecord(['user_delivery_location_id'=>$udl_id]);
        $this->assertNull($record,'A record should not have been created');


        $udl_id = $udla_resource->user_addr_id;

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'pickup','the note',2);
        $cart_data['user_addr_id'] = $udl_id;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertNull($checkout_resource->error);
        $this->assertEquals(5.60,$checkout_resource->delivery_amt);


        // since less than 5 items, delivery shoudl be the min lead time + 45 min
        $lead_times_array = $checkout_resource->lead_times_array;
        $expected_first_time_stamp = time() + 4200;
        $expected_first_time_string = date('Y-m-d H:i',$expected_first_time_stamp);
        $actual_first_time_string = date('Y-m-d H:i',$lead_times_array[0]);
        $this->assertEquals($expected_first_time_string,$actual_first_time_string,"first time should be 50 minutes after preptime");
        $this->assertCount(1,$lead_times_array);

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,time());
        $this->assertNull($order_resource->error);

        $ready_timestamp = $order_resource->ready_timestamp;
        $ready_time_string = date('H:i',$ready_timestamp);
        $expected_ready_time_string = date('H:i',time() + 20*60); // now plus lead time for merchant
        $this->assertEquals($expected_ready_time_string,$ready_time_string);

        $full_ready_time_string = date('Y-m-d H:i:s',$ready_timestamp);
        $this->assertEquals($full_ready_time_string,$order_resource->pickup_dt_tm);




    }

    function testDeliveryPriceBasedOnMultiType()
    {
        $merchant_resource = createNewTestMerchant(0,['long_hours'=>true]);
        $merchant_resource->delivery = 'Y';
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;

        MerchantMessageMapAdapter::createMerchantMessageMap($merchant_id,'FUC','1234567890','X');



        $data = array("merchant_id" => $merchant_resource->merchant_id);

        //$prep_resource = Resource::createByData(new MerchantPreptimeInfoAdapter(getM()), array("merchant_id"=>$merchant_resource->merchant_id,"entree_preptime_seconds"=>120,"delivery_throttling_on"=>'N'));


        // set merchant delivery info
        $mdia = new MerchantDeliveryInfoAdapter(getM());
        $mdia_resource = $mdia->getExactResourceFromData($data);
        $mdia_resource->minimum_order = 0.01;
        $mdia_resource->delivery_price_type = 'mixed';
        $mdia_resource->delivery_cost = 1.00;
        $mdia_resource->delivery_increment = 15;
        $mdia_resource->max_days_out = 3;
        $mdia_resource->save();

        //map it to a menu
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $this->ids['menu_id'], 'pickup');
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $this->ids['menu_id'], 'delivery');

        // now create the distance price records.
        $mdpd = new MerchantDeliveryPriceDistanceAdapter(getM());
        $mdpd_resource = $mdpd->getExactResourceFromData($data);
        $mdpd_resource->distance_up_to = 1;
        $mdpd_resource->price = 2.00;
        $mdpd_resource->save();

        $mdpd_resource->_exists = false;
        unset($mdpd_resource->map_id);
        $mdpd_resource->polygon_coordinates = "40.063990 -105.296964, 40.050002 -105.194972, 39.997704 -105.218730, 39.995049, -105.302277";
        unset($mdpd_resource->distance_up_to);
        $mdpd_resource->price = 4.00;
        $mdpd_resource->save();
        $user_for_test_mdpd_id = $mdpd_resource->map_id;

        $mdpd_resource->_exists = false;
        unset($mdpd_resource->map_id);
        unset($mdpd_resource->polygon_coordinates);
        unset($mdpd_resource->distance_up_to);
        $mdpd_resource->name = 'Doordash';
        $mdpd_resource->price = 100.00;
        $mdpd_resource->save();
        $this->door_dash_delivery_price_resource = $mdpd_resource;
        $this->assertTrue(true);
        return $merchant_id;
    }

    /**
     * @depends testDeliveryPriceBasedOnMultiType
     */
    function testCloseIn($merchant_id)
    {
        // do close in first
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];
        $udla = new UserDeliveryLocationAdapter(getM());
        $data = array("user_id" => $user_id, "address1" => "1633 18th street", "city" => "Boulder", "state" => "CO", "zip" => "80302", "phone_no" => "1234567890", "lat" => 40.016101, "lng" => -105.271900);
        $udla_resource = Resource::createByData($udla, $data);
        $udl_id = $udla_resource->user_addr_id;
        $mdia = new MerchantDeliveryInfoAdapter(getM());
        $results = $mdia->getDeliveryPriceFromIds($udl_id, $merchant_id);
        $this->assertEquals(2.00, $results);
    }

    /**
     * @depends testDeliveryPriceBasedOnMultiType
     */
    function testInSecondZoneAsPolygon($merchant_id)
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];
        $udla = new UserDeliveryLocationAdapter(getM());
        $data = array("user_id" => $user_id, "address1" => "4670 North Broadway", "city" => "Boulder", "state" => "CO", "zip" => "80304", "phone_no" => "1234567890", "lat" => 40.059391, "lng" => -105.281901);
        $udla_resource = Resource::createByData($udla, $data);
        $udl_id = $udla_resource->user_addr_id;
        $mdia = new MerchantDeliveryInfoAdapter(getM());
        $results = $mdia->getDeliveryPriceFromIds($udl_id, $merchant_id);
        $this->assertEquals(4.00, $results);

    }

    /**
     * @depends testDeliveryPriceBasedOnMultiType
     */
    function testOutsideSecondZoneGiveToDoorDash($merchant_id)
    {

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        //outside
        $udla = new UserDeliveryLocationAdapter($mimetypes);
        $data = array("user_id"=>$user_id,"address1"=>"450 main street","city"=>"Lyons","state"=>"CO","zip"=>"80540","phone_no"=>"1234567890","lat"=>40.224877,"lng"=> -105.271122);
        $udla_resource = Resource::createByData($udla, $data);
        $udl_id = $udla_resource->user_addr_id;
        $mdia = new MerchantDeliveryInfoAdapter(getM());
        $results = $mdia->getDeliveryPriceFromIds($udl_id, $merchant_id);
        $this->assertEquals(8.00, $results);

    }

    /**
     * @depends testDeliveryPriceBasedOnMultiType
     */
    function testRejectDeliveryLocationByDoorDash($merchant_id)
    {

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        //outside
        $udla = new UserDeliveryLocationAdapter(getM());
        $data = array("user_id"=>$user_id,"address1"=>"450 main street","city"=>"Lyons","state"=>"CO","zip"=>"80540","phone_no"=>"1234567890","lat"=>40.224877,"lng"=> -105.271122);
        $udla_resource = Resource::createByData($udla, $data);
        $udl_id = $udla_resource->user_addr_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$udl_id?log_level=5",'GET');
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $_SERVER['force_doordash_estimate_fail'] = true;
        $resource = $merchant_controller->processV2Request();

        $this->assertFalse($resource->is_in_delivery_range," the is in delivery range should be false");
        $this->assertEquals(DeliveryController::DOORDASH_CANNOT_DELIVER_MESSAGE,$resource->user_message);

    }

    /**
     * @depends testDeliveryPriceBasedOnMultiType
     */
    function testGetMessageOnIsInDeliveryRangeWithDoorDash($merchant_id)
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        //outside
        $udla = new UserDeliveryLocationAdapter(getM());
        $data = array("user_id"=>$user_id,"address1"=>"450 main street","city"=>"Lyons","state"=>"CO","zip"=>"80540","phone_no"=>"1234567890","lat"=>40.224877,"lng"=> -105.271122);
        $udla_resource = Resource::createByData($udla, $data);
        $udl_id = $udla_resource->user_addr_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$udl_id?log_level=5",'GET');
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue($resource->is_in_delivery_range," the is in delivery range should be true");

        //make sure cache didn't happen
        $mudc_adatper = new UserDeliveryLocationMerchantPriceMapsAdapter(getM());
        $record = $mudc_adatper->getRecord(['user_delivery_location_id'=>$udl_id]);
        $this->assertNull($record,'A record should not have been created');
        $this->assertEquals(DeliveryController::DOORDASH_ESTIMATE_USER_MESSAGE,$resource->user_message);

    }

    /**
     * @depends testDeliveryPriceBasedOnMultiType
     */
    function testPlaceOrderWithDoorDashAsDelivery($merchant_id)
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        //outside
        $udla = new UserDeliveryLocationAdapter(getM());
        $data = array("user_id"=>$user_id,"address1"=>"450 main street","city"=>"Lyons","state"=>"CO","zip"=>"80540","phone_no"=>"1234567890","lat"=>40.224877,"lng"=> -105.271122);
        $udla_resource = Resource::createByData($udla, $data);
        $udl_id = $udla_resource->user_addr_id;

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $cart_data['user_addr_id'] = $udl_id;
        $cart_resource = getCartResourceFromOrderData($cart_data);
        $this->assertNull($cart_resource->error);
        $this->assertEquals(8.00,$cart_resource->delivery_amt,'Since its not a checkout we expect the delivery price to be the defaulty amount based on a 15.00 order');

        // now add more items and see if the delivery fee changes.
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $cart_data['ucid'] = $cart_resource->ucid;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $this->assertEquals(5.60,$checkout_resource->delivery_amt);

        // now add more items and see if the delivery fee changes.
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $cart_data['ucid'] = $checkout_resource->ucid;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $this->assertEquals(5.90,$checkout_resource->delivery_amt);

        // since less than 5 items, delivery shoudl be the min lead time + 45 min
        $lead_times_array = $checkout_resource->lead_times_array;
        $expected_first_time_stamp = getTomorrowTwelveNoonTimeStampDenver() + 4200;
        $expected_first_time_string = date('Y-m-d H:i',$expected_first_time_stamp);
        $actual_first_time_string = date('Y-m-d H:i',$lead_times_array[0]);
        $this->assertEquals($expected_first_time_string,$actual_first_time_string,"first time should be 50 minutes after preptime");
        $this->assertCount(1,$lead_times_array);

        $complete_order = CompleteOrder::getBaseOrderData($checkout_resource->ucid);
        $ready_time_string = date(DATE_ATOM,$complete_order['ready_timestamp']);

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);

        $ready_timestamp = $order_resource->ready_timestamp;
        $ready_time_string = date('H:i:s',$ready_timestamp);
        $this->assertEquals('12:20:00',$ready_time_string);

    }

    /**
     * @depends testDeliveryPriceBasedOnMultiType
     */
    function testWhatToDoWhenDoorDashRejectsOrderOnCheckout($merchant_id)
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $udla = new UserDeliveryLocationAdapter(getM());
        $data = array("user_id"=>$user_id,"address1"=>"450 main street","city"=>"Lyons","state"=>"CO","zip"=>"80540","phone_no"=>"1234567890","lat"=>40.224877,"lng"=> -105.271122);
        $udla_resource = Resource::createByData($udla, $data);
        $udl_id = $udla_resource->user_addr_id;

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $cart_data['user_addr_id'] = $udl_id;
        $_SERVER['force_doordash_estimate_fail'] = true;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNotNull($checkout_resource->error);
        $this->assertEquals(DeliveryController::DOORDASH_CANNOT_DELIVER_MESSAGE,$checkout_resource->error);
        $_SERVER['force_doordash_estimate_fail'] = false;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $_SERVER['force_doordash_estimate_fail'] = true;
        $order_resource = placeOrderFromCheckoutResource($checkout_resource);
        $this->assertNotNull($order_resource->error);
        $this->assertEquals(DeliveryController::DOORDASH_CANNOT_DELIVER_MESSAGE,$order_resource->error);

        $_SERVER['force_doordash_estimate_fail'] = false;
        $order_resource = placeOrderFromCheckoutResource($checkout_resource);
        $this->assertNull($order_resource->error);
        $this->assertContains('---Doordash Delivery Id:',$order_resource->note);
        return $order_resource->order_id;
    }

    /**
     * @depends testWhatToDoWhenDoorDashRejectsOrderOnCheckout
     */
    function testDeliveryToMerchant($order_id)
    {
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id);
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'FUC');
        $next_message_dt_tm = $message_resource->next_message_dt_tm;
        $actual_time_string = date('Y-m-d H:i:s',$next_message_dt_tm);
        $expected_time_string = date('Y-m-d H:i:s',getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals($expected_time_string,$actual_time_string,'It should schedule the message immediately');
        $fax_controller = ControllerFactory::generateFromMessageResource($message_resource);
        $message_to_send = $fax_controller->prepMessageForSending($message_resource);
        $message_text = $message_to_send->message_text;
        myerror_log($message_text);
        $this->assertContains('----- DOORDASH ------',$message_text);

        $ready_timestamp = $complete_order['ready_timestamp'];
        $ready_time_string = date('H:i:s',$ready_timestamp);
        $this->assertContains("Doordash Pickup Time: $ready_time_string",$message_text);
        $order_day = date('D',getTomorrowTwelveNoonTimeStampDenver());
        $order_date = date('m/d',getTomorrowTwelveNoonTimeStampDenver());
        $this->assertContains("Expected Delivery Time: $order_day $order_date 1:10 PM",$message_text);
    }


    function testToCanceOrderWithDoorDashonCCfail()
    {
        $merchant_resource = createNewTestMerchant();
        $merchant_resource->delivery = 'Y';
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;

        $data = array("merchant_id" => $merchant_resource->merchant_id);

        //$prep_resource = Resource::createByData(new MerchantPreptimeInfoAdapter(getM()), array("merchant_id"=>$merchant_resource->merchant_id,"entree_preptime_seconds"=>120,"delivery_throttling_on"=>'N'));


        // set merchant delivery info
        $mdia = new MerchantDeliveryInfoAdapter(getM());
        $mdia_resource = $mdia->getExactResourceFromData($data);
        $mdia_resource->minimum_order = 0.01;
        $mdia_resource->delivery_price_type = 'Doordash';
        $mdia_resource->delivery_cost = 0.00;
        $mdia_resource->delivery_increment = 15;
        $mdia_resource->max_days_out = 3;
        $mdia_resource->save();

        //map it to a menu
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $this->ids['menu_id'], 'pickup');
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $this->ids['menu_id'], 'delivery');

        // now create the distance price records.
        $mdpd = new MerchantDeliveryPriceDistanceAdapter(getM());
        $mdpd_resource = $mdpd->getExactResourceFromData($data);
        $mdpd_resource->distance_up_to = 0;
        $mdpd_resource->name = 'Doordash';
        $mdpd_resource->price = 100.00;
        $mdpd_resource->save();

        $mdpd_resource->distance_up_to = 200;
        $mdpd_resource->price = 0.00;
        $mdpd_resource->save();

        $user_resource = createNewUserWithCCNoCVV();
        $user_resource->uuid = substr($user_resource->uuid,0,18).'DECL';
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $udla = new UserDeliveryLocationAdapter(getM());
        $data = array("user_id"=>$user_id,"address1"=>"450 main street","city"=>"Lyons","state"=>"CO","zip"=>"80540","phone_no"=>"1234567890","lat"=>40.224877,"lng"=> -105.271122);
        $udla_resource = Resource::createByData($udla, $data);
        $udl_id = $udla_resource->user_addr_id;

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $cart_data['user_addr_id'] = $udl_id;

        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);

        $checkout_resource->note = 'sum dum note';
        $order_resouce = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,1.50,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNotNull($order_resouce->error);

        $order_data = CompleteOrder::getBaseOrderData($checkout_resource->ucid,getM());
        $this->assertNotContains('---Doordash Delivery Id:',$order_data['note']);
        return $merchant_id;

    }

    /**
     * @depends testToCanceOrderWithDoorDashonCCfail
     */

    function testAfterHoursOrderingWithDoorDash($merchant_id)
    {
        // do not let someone pleace delivery order with door dash if its after hours
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];
        $udla = new UserDeliveryLocationAdapter(getM());
        $data = array("user_id" => $user_id, "address1" => "1633 18th street", "city" => "Boulder", "state" => "CO", "zip" => "80302", "phone_no" => "1234567890", "lat" => 40.016101, "lng" => -105.271900);
        $udla_resource = Resource::createByData($udla, $data);
        $udl_id = $udla_resource->user_addr_id;


        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$udl_id?log_level=5",'GET');
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $merchant_controller->setTheTime(getTomorrowTwelveNoonTimeStampDenver() - (8*3600));
        $resource = $merchant_controller->processV2Request();

        $this->assertEquals(422,$resource->http_code);
        $this->assertEquals(DeliveryController::DOORDASH_STORE_CLOSED_MESSAGE,$resource->error);
    }

    function testCancelOrderWithDoordashAlreadyScheduled()
    {
        $this->assertTrue(false,"write the test: testCancelOrderWithDoordashAlreadyScheduled");
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

        $skin_resource = getOrCreateSkinAndBrandIfNecessary('fuddruckers','fuddruckers',114,372);

        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(3);
        $ids['menu_id'] = $menu_id;

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
    DeliveryMultiTypeTest::main();
}

?>