<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class CateringOrderTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;
    var $info;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        //$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__.'-'.generateCode(7);
        $this->ids = $_SERVER['unit_test_ids'];
        setContext($this->ids['context']);
        $tz = date_default_timezone_get();
        myerror_log("set up the time zone is: ".$tz);
    }

    function tearDown()
    {
        $tz = date_default_timezone_get();
        myerror_log("tear down the time zone is: ".$tz);
        //delete your instance
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
        unset($this->info);
    }

    function testIncrementSettingOfPossiblePickupTimes()
    {
        $merchant_resource = createNewTestMerchantWithCatering($this->ids['menu_id'], $data);
        $merchant_id = $merchant_resource->merchant_id;
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $catering_data = $this->getCateringOrderData($merchant_id);
        unset($catering_data['order_type']);

        $request = createRequestObject("app2/apiv2/merchants/$merchant_id/cateringorderavailabletimes/pickup", 'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $merchant_controller->setTheTime(getTomorrowTwelveNoonTimeStampDenver()-(1.3*3600));
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $available_times_array = $resource->catering_place_order_times;
        foreach ($available_times_array as $time) {
            myerror_log(  date("Y-m-d H:i:s",$time));
        }
        $first_time = date("Y-m-d H:i:s",$available_times_array[0]);
        $expected_first_time = date("Y-m-d H:i:s",getTomorrowTwelveNoonTimeStampDenver()+4*3600);
        $this->assertEquals($expected_first_time,$first_time,"first time should be on the hours 5+ hours from now");

        // now set increment to 15
        $mcir = $merchant_resource->merchant_catering_info_resource;
        $mcir->time_increment_in_minutes = 15;
        $mcir->save();


        $request = createRequestObject("app2/apiv2/merchants/$merchant_id/cateringorderavailabletimes/pickup", 'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $merchant_controller->setTheTime(getTomorrowTwelveNoonTimeStampDenver()-(1.3*3600));
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $available_times_array = $resource->catering_place_order_times;
        $first_time = date("Y-m-d H:i:s",$available_times_array[0]);
        $expected_first_time = date("Y-m-d H:i:s",getTomorrowTwelveNoonTimeStampDenver()+(4*3600-900));
        $this->assertEquals($expected_first_time,$first_time,"first time should be on the hours 5+ hours from now");



        $merchant_resource->logical_delete = 'Y';
        $merchant_resource->save();
    }

    function testAllowDeliveryCateringButNotRetailDelivery()
    {
        $merchant_resource = createNewTestMerchantWithCatering($this->ids['menu_id'],$data);
        $merchant_catering_info_resource = $merchant_resource->merchant_catering_info_resource;
        $merchant_catering_info_resource->minimum_delivery_amount = 200.00;
        $merchant_catering_info_resource->save();

        $merchant_id = $merchant_resource->merchant_id;

        $url = "/app2/apiv2/merchants";
        $request = createRequestObject($url,'GET');
        $merchant_controller = new MerchantController(getM(),null,$request);
        $resource = $merchant_controller->processV2Request();
        $merchants_by_id = createHashmapFromArrayOfArraysByFieldName($resource->merchants,'merchant_id');
        $this->assertEquals('Y',$merchants_by_id[$merchant_id]['delivery']);


        $merchant_resource->delivery = 'N';
        $merchant_resource->save();


        $resource = $merchant_controller->processV2Request();
        $merchants_by_id = createHashmapFromArrayOfArraysByFieldName($resource->merchants,'merchant_id');
        $this->assertEquals('N',$merchants_by_id[$merchant_id]['delivery']);






        $mmm_adapter = new MerchantMenuMapAdapter();
        $mmm_resource = Resource::findOrCreateIfNotExistsByData($mmm_adapter,['merchant_id'=>$merchant_id,"merchant_menu_type"=>'delivery']);
        $mmm_resource->logical_delete = 'Y';
        //$mmm_resource->save();

        $mdiadapter = new MerchantDeliveryInfoAdapter(getM());
        $mdi_resource = Resource::findOrCreateIfNotExistsByData($mdiadapter,['merchant_id'=>$merchant_id]);
        $mdi_resource->active = 'N';
        $mdi_resource->save();
//        $merchant_catering_info_resource = $merchant_resource->merchant_catering_info_resource;
//        $merchant_catering_info_resource->minimum_delivery_amount = 50.00;
//        $merchant_catering_info_resource->save();

        $mdpdr = MerchantDeliveryPriceDistanceAdapter::staticGetMerchantPriceRecordsAsResourcesByMerchantId($merchant_resource->merchant_id);
        foreach ($mdpdr as $merchant_delivery_price_distance_resource) {
            if ($merchant_delivery_price_distance_resource->delivery_type == 'Catering') {
                $merchant_delivery_price_distance_resource->price = 8.88;
            } else {
                $merchant_delivery_price_distance_resource->price = 2.22;
                //$merchant_delivery_price_distance_resource->logical_delete = 'Y';
            }
            $merchant_delivery_price_distance_resource->save();
        }

        $options[TONIC_FIND_BY_METADATA] = array("merchant_id"=>$merchant_resource->merchant_id,"delivery_type"=>'Catering');
        $mdpd_resource = Resource::find(new MerchantDeliveryPriceDistanceAdapter(),null,$options);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        // first validate that regualr delivery is working
        //$json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"1045 Pine Street","address2":"","city":"boulder","state":"co","zip":"80302","phone_no":"9709262121","lat":40.0197891,"lng":-105.284703}';
        $request = createRequestObject("/users/" . $user['uuid'] . "/userdeliverylocation","POST",$json,'application/json');
        $user_controller = new UserController($mt, $user, $request, 5);

        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $boulder_user_address_id = $response->user_addr_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/delivery",'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNotNull($resource->error."It shoudl have recieved an error since there is no retail delivery turned on");

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'delivery');
        $cart_data['user_addr_id'] = $boulder_user_address_id;

        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertNotNull($checkout_resource->error,"Should have recieved an error because delivery is not turned on");




        $json = '{"user_id":"' . $user['user_id'] . '","name":"","address1":"201 Price Road","address2":"","city":"Longmont","state":"CO","zip":"80501","phone_no":"1234567890","lat":40.163665,"lng":-105.097701}';
        $request = createRequestObject("/users/" . $user['uuid'] . "/userdeliverylocation",'POST',$json);
        $user_controller = new UserController($mt, $user, $request, 5);
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;


        $number_of_people = 8;
        $catering_data['number_of_people'] = $number_of_people;
        $catering_data['merchant_id'] = $merchant_id;
        $catering_data['event'] = 'business lunch';
        $catering_data['order_type'] = 'delivery';
        $catering_data['user_addr_id'] = $user_address_id;
        $catering_data['timestamp_of_event'] = getTomorrowTwelveNoonTimeStampDenver();

        $request = createRequestObject("/apiv2/catering","POST",json_encode($catering_data));
        $catering_controller = new CateringController(getM(),$user,$request);
        $response = $catering_controller->processV2Request();
        $this->assertNotNull($response->error,"We should have gotten a delivery error since its too far away");
        // so now set miles to high value
        $mdpd_resource->distance_up_to = 100.00;
//        $mdpd_resource->delivery_type = 'Regular';
        $mdpd_resource->save();

        $response = $catering_controller->processV2Request();
        $this->assertNull($response->error,"should not have gotten an error now since catering distance is far");
        $ucid = $response->ucid;
        $this->assertNotNull($ucid);
        $order_id = $response->order_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id/catering", 'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range),"should have found the 'is in delivery range' field");
        $this->assertTrue($resource->is_in_delivery_range," the is in delivery range should now be true");




        $this->assertTrue($order_id > 1000,"we have a valid order id");
        $catering_order_record = CateringOrdersAdapter::staticGetRecord(array("order_id"=>$order_id),'CateringOrdersAdapter');
        $this->assertNotNull($catering_order_record,"there shouljd be a catering order record");
        $this->assertEquals($number_of_people,$catering_order_record['number_of_people']);
        $this->assertEquals('business lunch',$catering_order_record['event']);
        $this->assertEquals('delivery',$catering_order_record['order_type']);
        $this->assertEquals('In Progress',$catering_order_record['status']);

        $order = new Order($order_id);
        $this->assertTrue($order->isDeliveryOrder(),"It should be a delivery order");
        $this->assertTrue($order->isCateringOrder(),"It Should be a catering order");
        $this->assertEquals(8.88,$order->get('delivery_amt'),"price should be the special catering delivery price");

        // now create and place the order
        $request = createRequestObject("/apiv2/merchants/$merchant_id/catering",'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $menu = $resource->menu;

        $order_adapter = new OrderAdapter();
        $cart_data = $order_adapter->getCartArrayFromFullMenuWithOneModiferPerModifierGroup($menu,$merchant_id,"sum dum catering note",2);
        $cart_data['user_addr_id'] = $user_address_id;
        $cart_data['ucid'] = $order->getUcid();

        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertNotNull($checkout_resource->error,"we should have gotten an error because the minimum delivery amount has not been met");
        //$this->assertEquals("We're sorry, this merchant has a $200 mimumim catering order amount for delivery",$checkout_resource->error);
        $this->assertEquals("Minimum order required! You have not met the minimum subtotal of $200.00 for your deliver area.",$checkout_resource->error);

        $merchant_catering_info_resource->minimum_delivery_amount = 50.00;
        $merchant_catering_info_resource->save();

        unset($cart_data['items']);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertNull($checkout_resource->error,"should not have gotten an error now");

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$time);
        $this->assertEquals("We're sorry, this merchant requires a mimimum tip of $10.00 for this order.",$order_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,11.00,$time);
        $this->assertNull($order_resource->error);
        $this->assertEquals(8.88,$order_resource->delivery_amt,"should have the regular delivery price");

    }

    function testAllowGuestUserToCreateCateringOrder()
    {
        $user_resource = createGuestUser();
        $user_resource->flags = "1C200000021";
        $user_resource->last_four = '1234';
        $user_resource->save();

        $user = logTestUserResourceIn($user_resource);

        $catering_data = $this->getCateringOrderData($this->ids['merchant_id']);
        unset($catering_data['order_type']);
        $timestamp_of_event = getTomorrowTwelveNoonTimeStampDenver()+(2*3600);
        $catering_data['timestamp_of_event'] = $timestamp_of_event;
        $info = $catering_data['contact_name'].' '.$catering_data['contact_phone'];
        $notes = $catering_data['notes'];

        $json = json_encode($catering_data);
        $request = createRequestObject("/apiv2/catering","POST",$json);
        $catering_controller = new CateringController($m,$user,$request);
        $response = $catering_controller->processV2Request();
        $this->assertTrue(is_a($response,'Resource'),"The response should be a resource");
        $this->assertNull($response->error);
        $ucid = $response->ucid;
        $this->assertNotNull($ucid);
        $order_id = $response->order_id;

        $this->assertTrue($order_id > 1000,"we have a valid order id");
        $catering_order_record = CateringOrdersAdapter::staticGetRecord(array("order_id"=>$order_id),'CateringOrdersAdapter');
        $this->assertNotNull($catering_order_record,"there shouljd be a catering order record");
        $this->assertEquals(10,$catering_order_record['number_of_people']);
        $this->assertEquals('business lunch',$catering_order_record['event']);
        $this->assertEquals('pickup',$catering_order_record['order_type']);
        $this->assertEquals($info, $catering_order_record['contact_info']);
        $this->assertEquals($notes,$catering_order_record['notes']);
        $this->assertEquals('In Progress',$catering_order_record['status']);
        $this->assertEquals($timestamp_of_event,$catering_order_record['timestamp_of_event']);
        $this->assertEquals(date('Y-m-d',getTomorrowTwelveNoonTimeStampDenver()).' 13:00:00',$catering_order_record['date_tm_of_event']);

        $order = new Order($order_id);
        $this->assertFalse($order->isDeliveryOrder(),"It should not be a delivery order");
        $this->assertTrue($order->isCateringOrder(),"It should be a catering order");

        $request = createRequestObject("/apiv2/merchants/".$this->ids['merchant_id']."/catering",'GET');
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $menu = $resource->menu;

        $order_adapter = new OrderAdapter();
        $cart_data = $order_adapter->getCartArrayFromFullMenuWithOneModiferPerModifierGroup($menu,$this->ids['merchant_id'],"sum dum note",2);
        $cart_data['ucid'] = $ucid;


        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertNull($checkout_resource->error);
        myerror_log($checkout_resource->user_message);
        $this->assertContains("Please note, this merchant has a required minimum tip of 10% of your order.",$checkout_resource->user_message);
        $this->assertEquals($ucid,$checkout_resource->ucid,"cart should be the one that was created when the catering order was created");
        $payment_array = $checkout_resource->accepted_payment_types;
        $this->assertCount(1,$payment_array,"there should only be a CC payment type even though the merchant accepts cash");
        $this->assertEquals(getTomorrowTwelveNoonTimeStampDenver()+(2*3600),$checkout_resource->lead_times_array[0]);
//        $expected_time = date('Y-m-d H:i:s',getTomorrowTwelveNoonTimeStampDenver());
//        $actual_time = date('Y-m-d H:i:s',$checkout_resource->lead_times_array[0]);
//        $this->assertEquals($expected_time,$actual_time,"the only time in the lead times array should ahve been the time chosen when created");
        $this->assertEquals(getTomorrowTwelveNoonTimeStampDenver()+(2*3600),$checkout_resource->lead_times_array[0]);

        // check that tip minimum is working for catering
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$this->ids['merchant_id'],5.00,time());
        $this->assertNotNull($order_resource->error,"It should have gotten an error becuase tip minimum was not met");
        $order_amt = $checkout_resource->order_amt;
        $minimum_tip_string = '$'.number_format($order_amt * .1,2);
        $minimum_tip_error_text = str_replace('%%minimum_tip%%',$minimum_tip_string,CateringController::MINIMUM_TIP_NOT_MET_ERROR);
        $this->assertEquals($minimum_tip_error_text,$order_resource->error);

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$this->ids['merchant_id'],15.00,time());
        $this->assertNull($order_resource->error);

        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000,"we have a valid order id");
        $catering_order_record = CateringOrdersAdapter::staticGetRecord(array("order_id"=>$order_id),'CateringOrdersAdapter');
        $this->assertEquals('Submitted',$catering_order_record['status']);
    }

    function testGetMerchantsSingleMerchantIdInList()
    {
        $merchant_resource = createNewTestMerchantWithCatering($this->ids['menu_id'],$data);
        $merchant_id = $merchant_resource->merchant_id;
        $request = createRequestObject("app2/apiv2/merchants?limit=10&merchantlist=$merchant_id&minimum_merchant_count=5&range=100",'GET');
        $merchant_controller = new MerchantController(getM(), null, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNotNull($resource->merchants);
        $merchants = $resource->merchants;
        $this->assertTrue(count($merchants) > 0);
        $merchant = $merchants[0];
        $this->assertEquals(1,$merchant['has_catering'],"it should have the has_catering fields and it should be true");

        // now change the catering active flag to no
        $mcir = $merchant_resource->merchant_catering_info_resource;
        $mcir->active = 'N';
        $mcir->save();

        $request = createRequestObject("app2/apiv2/merchants?limit=10&merchantlist=$merchant_id&minimum_merchant_count=5&range=100",'GET');
        $merchant_controller = new MerchantController(getM(), null, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNotNull($resource->merchants);
        $merchants = $resource->merchants;
        $merchant = $merchants[0];
        $this->assertEquals(0,$merchant['has_catering'],"it should have the has_catering fields and it should be false");


    }


    function testMinimumCateringOrderMessageAndEnforcement()
    {
        $merchant_resource = createNewTestMerchantWithCatering($this->ids['menu_id'],$data);
        $merchant_id = $merchant_resource->merchant_id;
        $mcir = $merchant_resource->merchant_catering_info_resource;
        $mcir->minimum_pickup_amount = 101;
        $mcir->save();

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $catering_data = $this->getCateringOrderData($merchant_id);
        unset($catering_data['order_type']);
        $timestamp_of_event = getTomorrowTwelveNoonTimeStampDenver()+(2*3600);
        $catering_data['timestamp_of_event'] = $timestamp_of_event;

        $json = json_encode($catering_data);
        $request = createRequestObject("/apiv2/catering","POST",$json);
        $catering_controller = new CateringController($m,$user,$request);
        $response = $catering_controller->processV2Request();
        $this->assertTrue(is_a($response,'Resource'),"The response should be a resource");
        $this->assertNull($response->error);
        $ucid = $response->ucid;
        $this->assertNotNull($ucid);
        $order_id = $response->order_id;

        $this->assertTrue($order_id > 1000,"we have a valid order id");
        $catering_order_record = CateringOrdersAdapter::staticGetRecord(array("order_id"=>$order_id),'CateringOrdersAdapter');
        $request = createRequestObject("/apiv2/merchants/".$merchant_id."/catering",'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $menu = $resource->menu;

        $order_adapter = new OrderAdapter();
        $cart_data = $order_adapter->getCartArrayFromFullMenuWithOneModiferPerModifierGroup($menu,$this->ids['merchant_id'],"sum dum note",2);
        $cart_data['ucid'] = $ucid;

        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertNotNull($checkout_resource->error,"It should have found an error due to minimum pickup amount");
        $this->assertEquals("Sorry, this merchant has a minimum pickup catering order amount of $101.00",$checkout_resource->error);

        $mcir->minimum_pickup_amount = 99;
        $mcir->save();

        $url = "/app2/apiv2/cart/$ucid/checkout";
        $request = createRequestObject($url, 'GET');
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime($time_stamp);
        $checkout_resource = $place_order_controller->processV2Request();

        $this->assertNull($checkout_resource->error);

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,20.00);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'E');
        $message_controller = ControllerFactory::generateFromMessageResource($message_resource);
        $message_to_send = $message_controller->prepMessageForSending($message_resource);
        $message_text = $message_to_send->message_text;
        $this->assertContains('Contact Info: adam 123 456 7890',$message_text);
    }

    function testGetFullListOfPossiblePickupTimesOnCheckoutWithChosenTimeAsFirstOne()
    {
        $merchant_resource = createNewTestMerchantWithCatering($this->ids['menu_id'],$data);
        $merchant_id = $merchant_resource->merchant_id;
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $catering_data = $this->getCateringOrderData($merchant_id);
        unset($catering_data['order_type']);

        $request = createRequestObject("app2/apiv2/merchants/$merchant_id/cateringorderavailabletimes/pickup",'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $available_times_array  = $resource->catering_place_order_times;
        $chosen_time = $available_times_array[4];


        //$timestamp_of_event = getTomorrowTwelveNoonTimeStampDenver()+(2*3600);
        //$catering_data['timestamp_of_event'] = $timestamp_of_event;
        $catering_data['timestamp_of_event'] = $chosen_time;

        $json = json_encode($catering_data);
        $request = createRequestObject("/apiv2/catering","POST",$json);
        $catering_controller = new CateringController($m,$user,$request);
        $response = $catering_controller->processV2Request();
        $this->assertTrue(is_a($response,'Resource'),"The response should be a resource");
        $this->assertNull($response->error);
        $ucid = $response->ucid;
        $this->assertNotNull($ucid);
        $order_id = $response->order_id;

        $this->assertTrue($order_id > 1000,"we have a valid order id");
        $catering_order_record = CateringOrdersAdapter::staticGetRecord(array("order_id"=>$order_id),'CateringOrdersAdapter');
        $request = createRequestObject("/apiv2/merchants/".$merchant_id."/catering",'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $menu = $resource->menu;

        $order_adapter = new OrderAdapter();
        $cart_data = $order_adapter->getCartArrayFromFullMenuWithOneModiferPerModifierGroup($menu,$this->ids['merchant_id'],"sum dum note",2);
        $cart_data['ucid'] = $ucid;

        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertNull($checkout_resource->error);
        //validate full list of times with chosen as first
        $lead_times = $checkout_resource->lead_times_array;
        $count = sizeof($lead_times);
        $this->assertEquals(sizeof($available_times_array)+1,$count,"the returned leadtimes should be one greater than the default since we add the first one as top choice");
        $this->assertTrue($lead_times[0] == $chosen_time,"first time should be the chosen time");
        $this->assertTrue($lead_times[1] < $chosen_time);
        $this->assertTrue($lead_times[2] < $chosen_time);
        $this->assertTrue($lead_times[3] < $chosen_time);
        $this->assertTrue($lead_times[4] < $chosen_time);
        $this->assertEquals(date("Y-m-d H:i",$chosen_time),date("Y-m-d H:i",$lead_times[5]),"It should still have the chosen time in the list");
        $this->assertTrue($lead_times[6] > $chosen_time);
    }

    function testGetLeadTimesWithMinimumLeadTimeEnforced()
    {
        $merchant_resource = createNewTestMerchantWithCatering($this->ids['menu_id'],$data);
        $merchant_id = $merchant_resource->merchant_id;
        $sql = "UPDATE Hour SET `open`='10:00' WHERE merchant_id = $merchant_id";
        $ha = new HourAdapter();
        $ha->_query($sql);
        $mcir = $merchant_resource->merchant_catering_info_resource;

        $request = createRequestObject("app2/apiv2/merchants/$merchant_id/cateringorderavailabletimes/pickup",'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);

        // set time to tomorrow 9am
        $tomorrow_10am = getTomorrowTwelveNoonTimeStampDenver()-(3*3600);
        $merchant_controller->setTheTime($tomorrow_10am);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $submit_times_array  = $resource->catering_place_order_times;
        foreach ($submit_times_array as $time) {
            myerror_log($time['display']);
        }
        $datetime_for_merchant = getSplickitDateTimeObjectFromMerchant($merchant_resource->getDataFieldsReally());
        $first_time_string = $datetime_for_merchant->setTimestamp($submit_times_array[0]['ts'])->format("Y-m-d H:i:s");
        myerror_log("first time is: $first_time_string");
        $first_ts = $submit_times_array[0];
        $this->assertEquals($tomorrow_10am+(5*3600),$first_ts,"first time should be at 2pm tomorrow but is was $first_time_string");
        $this->assertEquals(date('Y-m-d H:i:s',getTomorrowTwelveNoonTimeStampDenver()+(24*3600)),date('Y-m-d H:i:s',$submit_times_array[7]),"first time on teh second day shoudl be 2 hours after open which is 12pm but is was: ".$datetime_for_merchant->setTimestamp($submit_times_array[7])->format("Y-m-d H:i:s"));
    }



    function testGetAdvancedCateringOrderAvailablePickupTimes()
    {
        $data['time_zone'] = 'pacific';
        $merchant_resource = createNewTestMerchantWithCatering($this->ids['menu_id'],$data);
        myerror_log("set up the time zone is: ".$tz);
        $merchant_id = $merchant_resource->merchant_id;

        $mcir = $merchant_resource->merchant_catering_info_resource;
        $mcir->min_lead_time_in_hours_from_open_time = 4;
        $mcir->max_days_out = 2;
        $message = 'Hello! This is the advanced catering message';
        $mcir->catering_message_to_user_on_create_order = $message;
        $mcir->save();
        // get tomorrow 4pm
        $current_time = getTimeStampForDateTimeAndTimeZone(12, 0, 0, date('m'), date('d'), date('Y'), "America/Los_Angeles") + (28*60*60);

        $request = createRequestObject("app2/apiv2/merchants/$merchant_id/cateringorderavailabletimes/pickup",'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $merchant_controller->setTheTime($current_time);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $submit_times_array  = $resource->catering_place_order_times;
        foreach ($submit_times_array as $time) {
            myerror_log($time['display']);
        }

        $datetime_for_merchant = getSplickitDateTimeObjectFromMerchant($merchant_resource->getDataFieldsReally());
        $this->assertEquals(date('Y-m-d',getTimeStampDaysFromNow(2)).' 11:00:00',$datetime_for_merchant->setTimestamp($submit_times_array[0])->format("Y-m-d H:i:s"),"first time should be 11am the next day");
        $this->assertEquals(date('Y-m-d',getTimeStampDaysFromNow(2)).' 12:00:00',$datetime_for_merchant->setTimestamp($submit_times_array[1])->format("Y-m-d H:i:s"),"second time should be 12 noon the next day");
        $this->assertEquals(date('Y-m-d',getTimeStampDaysFromNow(2)).' 13:00:00',$datetime_for_merchant->setTimestamp($submit_times_array[2])->format("Y-m-d H:i:s"),"next time should be 1pm the next day");
        $this->assertEquals(date('Y-m-d',getTimeStampDaysFromNow(2)).' 14:00:00',$datetime_for_merchant->setTimestamp($submit_times_array[3])->format("Y-m-d H:i:s"),"next time should be 2pm the next day");
        $this->assertEquals(date('Y-m-d',getTimeStampDaysFromNow(2)).' 15:00:00',$datetime_for_merchant->setTimestamp($submit_times_array[4])->format("Y-m-d H:i:s"),"next time should be 3pm the next day");
        $this->assertEquals(date('Y-m-d',getTimeStampDaysFromNow(2)).' 16:00:00',$datetime_for_merchant->setTimestamp($submit_times_array[5])->format("Y-m-d H:i:s"),"next time should be 4pm the next day");
        $last_time = $datetime_for_merchant->setTimestamp(array_pop($submit_times_array))->format("Y-m-d H:i:s");
        $this->assertEquals(date('Y-m-d',getTimeStampDaysFromNow(3)).' 20:00:00',$last_time,"last time should have been 8pm on 2nd day from ordering time which is 3 days from now (since ordering time is tomorrow)");

        // check display
        $expected_display_for_1_pm = date('D',getTimeStampDaysFromNow(2)).' 2:00 pm';
        $this->assertEquals($expected_display_for_1_pm,date("D g:i a",$submit_times_array[2]));

    }

    function testForceCateringForLubysBrand()
    {
        $skin_resource = getOrCreateSkinAndBrandIfNecessary('Lubys','Lubys',144,434);
        setContext('com.splickit.lubys');
        $menu_id = createTestCateringMenuWithOneItem('catering item 1');
        $merchant_resource = createNewTestMerchantWithCatering($menu_id);
        attachMerchantToSkin($merchant_resource->merchant_id, $skin_resource->skin_id);
        $request = new Request();
        $request->data['lat'] = 40.014000;
        $request->data['long'] = -105.200000;
        $merchant_controller = new MerchantController($mt, $u, $request,5);
        $resource = $merchant_controller->getMerchantList($skin_resource->skin_id);
        $this->assertNull($resource->error);
        $this->assertEquals(1, sizeof($resource->data),'there should be 1 merchant');

        $data = $resource->data;

        $merchant = $data[0];
        $this->assertEquals(1,$merchant['force_catering'],"merchant should have the force catering flag set to true or 1 in this case");
    }

    function testGetCateringOrderAvailablePickupTimes()
    {
        $data['time_zone'] = 'pacific';
        $merchant_resource = createNewTestMerchantWithCatering($this->ids['menu_id'],$data);
        $tz = date_default_timezone_get();
        myerror_log("set up the time zone is: ".$tz);
        $merchant_id = $merchant_resource->merchant_id;

        $mcir = $merchant_resource->merchant_catering_info_resource;
        $mcir->min_lead_time_in_hours_from_open_time = 4;
        $mcir->max_days_out = 2;
        $message = 'Hello! This is the catering message';
        $mcir->catering_message_to_user_on_create_order = $message;
        $mcir->save();

        // use tomorrow at 4pm pacific
        $current_time = getTimeStampForDateTimeAndTimeZone(12, 0, 0, date('m'), date('d'), date('Y'), "America/Los_Angeles") + (28*60*60);
        $request = createRequestObject("app2/apiv2/merchants/$merchant_id/cateringorderavailabletimes/pickup",'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $merchant_controller->setTheTime($current_time);
        $this->assertEquals('America/Denver',date_default_timezone_get());
        $resource = $merchant_controller->processV2Request();
        $this->assertEquals('America/Denver',date_default_timezone_get());
        $this->assertNull($resource->error);
        $submit_times_array  = $resource->catering_place_order_times;
        $this->assertEquals($message,$resource->catering_message_to_user_on_create_order);
        $this->assertEquals($mcir->minimum_pickup_amount,$resource->minimum_pickup_amount,"minimum pickup amount should be on retuned data");
        $this->assertEquals($mcir->minimum_delivery_amount,$resource->minimum_delivery_amount,"minimum delivery amount should be on retuned data");

        $datetime_for_pacific_merchant = getSplickitDateTimeObjectFromMerchant($merchant_resource->getDataFieldsReally());
        $this->assertEquals(date('Y-m-d',getTimeStampDaysFromNow(2)).' 11:00:00',$datetime_for_pacific_merchant->setTimestamp($submit_times_array[0])->format("Y-m-d H:i:s"),"first time should be 11am the next day");
        $this->assertEquals(date('Y-m-d',getTimeStampDaysFromNow(2)).' 12:00:00',$datetime_for_pacific_merchant->setTimestamp($submit_times_array[1])->format("Y-m-d H:i:s"),"second time should be 12 noon the next day");
        $this->assertEquals(date('Y-m-d',getTimeStampDaysFromNow(2)).' 13:00:00',$datetime_for_pacific_merchant->setTimestamp($submit_times_array[2])->format("Y-m-d H:i:s"),"next time should be 1pm the next day");
        $this->assertEquals(date('Y-m-d',getTimeStampDaysFromNow(2)).' 14:00:00',$datetime_for_pacific_merchant->setTimestamp($submit_times_array[3])->format("Y-m-d H:i:s"),"next time should be 2pm the next day");
        $this->assertEquals(date('Y-m-d',getTimeStampDaysFromNow(2)).' 15:00:00',$datetime_for_pacific_merchant->setTimestamp($submit_times_array[4])->format("Y-m-d H:i:s"),"next time should be 3pm the next day");
        $this->assertEquals(date('Y-m-d',getTimeStampDaysFromNow(2)).' 16:00:00',$datetime_for_pacific_merchant->setTimestamp($submit_times_array[5])->format("Y-m-d H:i:s"),"next time should be 4pm the next day");
        $last_time = $datetime_for_pacific_merchant->setTimestamp(array_pop($submit_times_array))->format("Y-m-d H:i:s");
        $this->assertEquals(date('Y-m-d',getTimeStampDaysFromNow(3)).' 20:00:00',$last_time,"last time should have been 8pm on 2nd day from ordering time which is 3 days from now (since ordering time is tomorrow)");

        // check display
        $expected_display_for_1_pm = date('D',getTimeStampDaysFromNow(2)).' 2:00 pm';
        $this->assertEquals($expected_display_for_1_pm,date("D g:i a",$submit_times_array[2]));

    }

    function testDoesMerchantParticipateInCateringById()
    {
        $merchant_controller = new MerchantController($mt, $u, $r);
        $this->assertTrue($merchant_controller->doesMerchantParticipateInCatering($this->ids['merchant_id']),"should have returned a true for catering");
    }

    function testReturnCateringOnMerchantsWithCatering()
    {
        $request = new Request();
        $request->data['lat'] = 33.757800;
        $request->data['long'] = -84.393700;
        $merchant_controller = new MerchantController($mt, $u, $request,5);
        $resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
        $this->assertNull($resource->error);
        $this->assertEquals(9, sizeof($resource->data),'there should be two merchants total');
        $number_of_catering_merchants = 0;
        $number_of_non_catering_merchants = 0;
        foreach ($resource->data as $merchant) {
            if ($merchant['has_catering'] == true) {
                $number_of_catering_merchants = $number_of_catering_merchants + 1;
                $catering_merchant = $merchant;
            } else {
                $number_of_non_catering_merchants = $number_of_non_catering_merchants + 1;
            }
        }
        $this->assertEquals(7,$number_of_catering_merchants,"THere shoujld be 7 catering merchant");
        $this->assertEquals(2,$number_of_non_catering_merchants,"there should be 2 non catering merchant");
        $this->assertFalse(isset($catering_merchant['force_catering']),"Should not a forced catering flag");
    }

    function testGetMerchantWithCateringOrderFlag()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        myerror_log("starting testGetMerchantWithCateringOrderFlag");
        $request = createRequestObject('/apiv2/merchants/'.$this->ids['merchant_id']);
        $merchant_controller = new MerchantController(getM(),$user, $request);
        $merchant_resource = $merchant_controller->getMerchant();
        $this->assertNull($merchant_resource->error,"should not have recieved an error getting merchant");
        $this->assertTrue(1 == $merchant_resource->has_catering,"should have a flag field for catering and it should be set to 1");
    }

    function testGetMerchantCateringMenuItemsOnly()
    {
        myerror_log("starting testGetMerchantCateringMenuItemsOnly");

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $request = createRequestObject("/apiv2/merchants/".$this->ids['merchant_id']."/catering",'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $menu = $resource->menu;
        foreach ($menu['menu_types'] as $menu_type) {
            $this->assertEquals('C',$menu_type['cat_id'],"There should only be catering menu types");
        }
        $this->assertCount(1,$menu['menu_types']);
        //$this->assertTrue(false,"STOP");
    }

    function testGetMerchantRegulatMenuItemsOnly()
    {
        myerror_log("starting testGetMerchantRegulatMenuItemsOnly");
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $request = createRequestObject("/apiv2/merchants/".$this->ids['merchant_id'],'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $menu = $resource->menu;
        foreach ($menu['menu_types'] as $menu_type) {
            $this->assertEquals('E',$menu_type['cat_id'],"There should only be regular menu types");
        }
        $this->assertCount(1,$menu['menu_types']);
    }

    function getCateringOrderData($merchant_id)
    {
        $catering_data['number_of_people'] = 10;
        $catering_data['merchant_id'] = $merchant_id;
        $catering_data['event'] = 'business lunch';
        $catering_data['timestamp_of_event'] = getTomorrowTwelveNoonTimeStampDenver()+3600;
        $catering_data['contact_name'] = 'adam';
        $catering_data['contact_phone'] = '123 456 7890';
        $catering_data['notes'] = "Please make sure that there are plenty of napkins";
        return $catering_data;
    }

    function testCreateCateringOrderPickup()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $catering_data = $this->getCateringOrderData($this->ids['merchant_id']);
        unset($catering_data['order_type']);
        $timestamp_of_event = getTomorrowTwelveNoonTimeStampDenver()+(2*3600);
        $catering_data['timestamp_of_event'] = $timestamp_of_event;
        $info = $catering_data['contact_name'].' '.$catering_data['contact_phone'];
        $notes = $catering_data['notes'];

        $json = json_encode($catering_data);
        $request = createRequestObject("/apiv2/catering","POST",$json);
        $catering_controller = new CateringController($m,$user,$request);
        $response = $catering_controller->processV2Request();
        $this->assertTrue(is_a($response,'Resource'),"The response should be a resource");
        $this->assertNull($response->error);
        $ucid = $response->ucid;
        $this->assertNotNull($ucid);
        $order_id = $response->order_id;

        $this->assertTrue($order_id > 1000,"we have a valid order id");
        $catering_order_record = CateringOrdersAdapter::staticGetRecord(array("order_id"=>$order_id),'CateringOrdersAdapter');
        $this->assertNotNull($catering_order_record,"there shouljd be a catering order record");
        $this->assertEquals(10,$catering_order_record['number_of_people']);
        $this->assertEquals('business lunch',$catering_order_record['event']);
        $this->assertEquals('pickup',$catering_order_record['order_type']);
        $this->assertEquals($info, $catering_order_record['contact_info']);
        $this->assertEquals($notes,$catering_order_record['notes']);
        $this->assertEquals('In Progress',$catering_order_record['status']);
        $this->assertEquals($timestamp_of_event,$catering_order_record['timestamp_of_event']);
        $this->assertEquals(date('Y-m-d',getTomorrowTwelveNoonTimeStampDenver()).' 13:00:00',$catering_order_record['date_tm_of_event']);

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
        myerror_log($checkout_resource->user_message);
        $this->assertContains("Please note, this merchant has a required minimum tip of 10% of your order.",$checkout_resource->user_message);
        $this->assertEquals($ucid,$checkout_resource->ucid,"cart should be the one that was created when the catering order was created");
        $payment_array = $checkout_resource->accepted_payment_types;
        $this->assertCount(1,$payment_array,"there should only be a CC payment type even though the merchant accepts cash");
        $this->assertEquals(getTomorrowTwelveNoonTimeStampDenver()+(2*3600),$checkout_resource->lead_times_array[0]);
//        $expected_time = date('Y-m-d H:i:s',getTomorrowTwelveNoonTimeStampDenver());
//        $actual_time = date('Y-m-d H:i:s',$checkout_resource->lead_times_array[0]);
//        $this->assertEquals($expected_time,$actual_time,"the only time in the lead times array should ahve been the time chosen when created");
        $this->assertEquals(getTomorrowTwelveNoonTimeStampDenver()+(2*3600),$checkout_resource->lead_times_array[0]);

        // check that tip minimum is working for catering
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,5.00,time());
        $this->assertNotNull($order_resource->error,"It should have gotten an error becuase tip minimum was not met");
        $order_amt = $checkout_resource->order_amt;
        $minimum_tip_string = '$'.number_format($order_amt * .1,2);
        $minimum_tip_error_text = str_replace('%%minimum_tip%%',$minimum_tip_string,CateringController::MINIMUM_TIP_NOT_MET_ERROR);
        $this->assertEquals($minimum_tip_error_text,$order_resource->error);

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,15.00,time());
        $this->assertNull($order_resource->error);

        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000,"we have a valid order id");
        $catering_order_record = CateringOrdersAdapter::staticGetRecord(array("order_id"=>$order_id),'CateringOrdersAdapter');
        $this->assertEquals('Submitted',$catering_order_record['status']);

    }

    function testCreateCateringOrderDelivery()
    {
        $merchant_resource = createNewTestMerchantWithCatering($this->ids['menu_id'],$data);
        $merchant_id = $merchant_resource->merchant_id;
        $merchant_catering_info_resource = $merchant_resource->merchant_catering_info_resource;
        $merchant_catering_info_resource->minimum_delivery_amount = 200.00;
        $merchant_catering_info_resource->save();

        attachMerchantToSkin($merchant_resource->merchant_id, $this->ids['skin_id']);

        $mdpdr = MerchantDeliveryPriceDistanceAdapter::staticGetMerchantPriceRecordsAsResourcesByMerchantId($merchant_resource->merchant_id);
        foreach ($mdpdr as $merchant_delivery_price_distance_resource) {
            if ($merchant_delivery_price_distance_resource->delivery_type == 'Catering') {
                $merchant_delivery_price_distance_resource->price = 8.88;
            } else {
                $merchant_delivery_price_distance_resource->price = 2.22;
            }
            $merchant_delivery_price_distance_resource->save();
        }

        $options[TONIC_FIND_BY_METADATA] = array("merchant_id"=>$merchant_resource->merchant_id,"delivery_type"=>'Catering');
        $mdpd_resource = Resource::find(new MerchantDeliveryPriceDistanceAdapter(),null,$options);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        // first validate that regualr delivery is working
        //$json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"1045 Pine Street","address2":"","city":"boulder","state":"co","zip":"80302","phone_no":"9709262121","lat":40.0197891,"lng":-105.284703}';
        $request = createRequestObject("/users/" . $user['uuid'] . "/userdeliverylocation","POST",$json,'application/json');
        $user_controller = new UserController($mt, $user, $request, 5);

        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $boulder_user_address_id = $response->user_addr_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$boulder_user_address_id", 'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range),"should have found the 'is in delivery range' field");
        $this->assertTrue($resource->is_in_delivery_range," the is in delivery range should be true");
        $this->assertEquals(2.22,$resource->price,"should have the regular price");

        $request = createRequestObject("/apiv2/merchants/$merchant_id/delivery",'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $menu = $resource->menu;

        $order_adapter = new OrderAdapter();
        $cart_data = $order_adapter->getCartArrayFromFullMenuWithOneModiferPerModifierGroup($menu,$merchant_id,"sum dum note",2);
        $cart_data['user_addr_id'] = $boulder_user_address_id;

        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());

        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);
        $this->assertEquals(2.22,$order_resource->delivery_amt,"should have the regular delivery price");




        $json = '{"user_id":"' . $user['user_id'] . '","name":"","address1":"201 Price Road","address2":"","city":"Longmont","state":"CO","zip":"80501","phone_no":"1234567890","lat":40.163665,"lng":-105.097701}';
        $request = createRequestObject("/users/" . $user['uuid'] . "/userdeliverylocation",'POST',$json);
        $user_controller = new UserController($mt, $user, $request, 5);
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;


        $number_of_people = 8;
        $catering_data['number_of_people'] = $number_of_people;
        $catering_data['merchant_id'] = $merchant_id;
        $catering_data['event'] = 'business lunch';
        $catering_data['order_type'] = 'delivery';
        $catering_data['user_addr_id'] = $user_address_id;
        $catering_data['timestamp_of_event'] = getTomorrowTwelveNoonTimeStampDenver();

        $request = createRequestObject("/apiv2/catering","POST",json_encode($catering_data));
        $catering_controller = new CateringController(getM(),$user,$request);
        $response = $catering_controller->processV2Request();
        $this->assertNotNull($response->error,"We shoujld have gotten a delivery error since its too far away");
        // so now set miles to high value
        $mdpd_resource->distance_up_to = 100.00;
//        $mdpd_resource->delivery_type = 'Regular';
        $mdpd_resource->save();

        $response = $catering_controller->processV2Request();
        $this->assertNull($response->error,"should not have gotten an error now since catering distance is far");
        $ucid = $response->ucid;
        $this->assertNotNull($ucid);
        $order_id = $response->order_id;

//        $mdpd_resource->delivery_type = 'Catering';
//        $mdpd_resource->save();
//
//        $sql = "DELETE FROM User_Delivery_Location_Merchant_Price_Maps WHERE user_delivery_location_id = $user_address_id";
//        $order_adapter->_query($sql);
//
//        $sql = "DELETE FROM User_Delivery_Location_Merchant_Price_Maps WHERE user_delivery_location_id = $boulder_user_address_id";
//        $order_adapter->_query($sql);

        //now check original address and see if its still outside regular delivery address
        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id", 'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range),"should have found the 'is in delivery range' field");
        $this->assertFalse($resource->is_in_delivery_range," the is in delivery range should be false");

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id/catering", 'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range),"should have found the 'is in delivery range' field");
        $this->assertTrue($resource->is_in_delivery_range," the is in delivery range should now be true");




        $this->assertTrue($order_id > 1000,"we have a valid order id");
        $catering_order_record = CateringOrdersAdapter::staticGetRecord(array("order_id"=>$order_id),'CateringOrdersAdapter');
        $this->assertNotNull($catering_order_record,"there shouljd be a catering order record");
        $this->assertEquals($number_of_people,$catering_order_record['number_of_people']);
        $this->assertEquals('business lunch',$catering_order_record['event']);
        $this->assertEquals('delivery',$catering_order_record['order_type']);
        $this->assertEquals('In Progress',$catering_order_record['status']);

        $order = new Order($order_id);
        $this->assertTrue($order->isDeliveryOrder(),"It should be a delivery order");
        $this->assertTrue($order->isCateringOrder(),"It Should be a catering order");
        $this->assertEquals(8.88,$order->get('delivery_amt'),"price should be the special catering delivery price");

        // now create and place the order
        $request = createRequestObject("/apiv2/merchants/$merchant_id/catering",'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $menu = $resource->menu;

        $order_adapter = new OrderAdapter();
        $cart_data = $order_adapter->getCartArrayFromFullMenuWithOneModiferPerModifierGroup($menu,$merchant_id,"sum dum catering note",2);
        $cart_data['user_addr_id'] = $user_address_id;
        $cart_data['ucid'] = $order->getUcid();

        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertNotNull($checkout_resource->error,"we should have gotten an error because the minimum delivery amount has not been met");
        //$this->assertEquals("We're sorry, this merchant has a $200 mimumim catering order amount for delivery",$checkout_resource->error);
        $this->assertEquals("Minimum order required! You have not met the minimum subtotal of $200.00 for your deliver area.",$checkout_resource->error);

        $merchant_catering_info_resource->minimum_delivery_amount = 50.00;
        $merchant_catering_info_resource->save();

        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertNull($checkout_resource->error,"should not have gotten an error now");

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$time);
        $this->assertEquals("We're sorry, this merchant requires a mimimum tip of $20.00 for this order.",$order_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,21.00,$time);
        $this->assertNull($order_resource->error);
        $this->assertEquals(8.88,$order_resource->delivery_amt,"should have the regular delivery price");
        return $user['user_id'];
    }

    /**
     * @depends testCreateCateringOrderDelivery
     */
    function testUserDeliveryLocationMerchantMapForDeliveryTypes($user_id)
    {
        $udlmm = new UserDeliveryLocationMerchantPriceMapsAdapter($m);
        $sql = "Select a.* from User_Delivery_Location_Merchant_Price_Maps a JOIN User_Delivery_Location b ON a.user_delivery_location_id = b.user_addr_id WHERE b.user_id = $user_id";
        $options[TONIC_FIND_BY_SQL] = $sql;
        $records = $udlmm->select("",$options);
        $this->assertCount(2,$records,"there should be 2 records in the table now");
        $records_hash = createHashmapFromArrayOfArraysByFieldName($records,'delivery_type');
        $this->assertNotNull($records_hash['Catering'],"there should be a catering record");
        $merchant_delivery_price_distance_map_id = $records_hash['Regular']['merchant_delivery_price_distance_map_id'];
        $mdpdmr = Resource::find(new MerchantDeliveryPriceDistanceAdapter($m),$merchant_delivery_price_distance_map_id);
        $this->assertEquals(2.22,$mdpdmr->price);

        $merchant_delivery_price_distance_map_id = $records_hash['Catering']['merchant_delivery_price_distance_map_id'];
        $mdpdmr = Resource::find(new MerchantDeliveryPriceDistanceAdapter($m),$merchant_delivery_price_distance_map_id);
        $this->assertEquals(8.88,$mdpdmr->price);
    }

    function testCancelCateringOrder()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $catering_data = $this->getCateringOrderData($this->ids['merchant_id']);
        $timestamp_of_event = getTomorrowTwelveNoonTimeStampDenver()+(2*3600);
        $catering_data['timestamp_of_event'] = $timestamp_of_event;

        $request = createRequestObject("/apiv2/catering","POST",json_encode($catering_data));
        $catering_controller = new CateringController($m,$user,$request);
        $response = $catering_controller->processV2Request();
        $this->assertTrue(is_a($response,'Resource'),"The response should be a resource");
        $this->assertNull($response->error);
        $ucid = $response->ucid;
        $this->assertNotNull($ucid);
        $order_id = $response->order_id;

        $this->assertTrue($order_id > 1000,"we have a valid order id");

        $request = createRequestObject("/apiv2/catering/$ucid","DELETE");
        $catering_controller = new CateringController($m,$user,$request);
        $response = $catering_controller->processV2Request();
        $this->assertNull($response->error);
        $this->assertTrue($response->success,"It should return a success = true");

        $complete_order = CompleteOrder::getBaseOrderData($ucid);
        $this->assertEquals(OrderAdapter::ORDER_CANCELLED,$complete_order['status'],"It should show cancelled");

        $catering_orders_adapter = new CateringOrdersAdapter();
        $catering_order = $catering_orders_adapter->getRecord(array("order_id"=>$complete_order['order_id']));
        $this->assertEquals('Cancelled',$catering_order['status'],"the catering order should show as cancelled");
    }

    function testDeliverCateringOrderMessgeToDifferentDestination()
    {
        $merchant_resource = createNewTestMerchantWithCatering($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        attachMerchantToSkin($merchant_id, $this->ids['skin_id']);

        $merchant_message_map = getStaticRecord(array("merchant_id"=>$merchant_id),'MerchantMessageMapAdapter');
        $original_address_destination = $merchant_message_map['delivery_addr'];

        $merchant_catering_info_resource = $merchant_resource->merchant_catering_info_resource;
        $new_destination = 'cateringemail@dummy.com';
        $merchant_catering_info_resource->special_merchant_message_destination = $new_destination;
        $merchant_catering_info_resource->save();

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $catering_data = $this->getCateringOrderData($merchant_id);

        $request = createRequestObject("/apiv2/catering","POST",json_encode($catering_data));
        $catering_controller = new CateringController($m,$user,$request);
        $response = $catering_controller->processV2Request();
        $this->assertNull($response->error);
        $ucid = $response->ucid;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/catering",'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $menu = $resource->menu;

        $order_adapter = new OrderAdapter();
        $cart_data = $order_adapter->getCartArrayFromFullMenuWithOneModiferPerModifierGroup($menu,$merchant_id,"sum dum note",2);
        $cart_data['ucid'] = $ucid;

        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertNull($checkout_resource->error);

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,15.00,time());
        $this->assertNull($order_resource->error);

        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000,"we have a valid order id");

        $order_message = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'E');
        $order_message_destination = $order_message->message_delivery_addr;

        $this->assertEquals($new_destination,$order_message_destination,"it should have used the new message destination");
        $this->assertNotEquals($original_address_destination,$new_destination);
    }

    function testNumberOfCateringOrdersPerDayPart()
    {
        $merchant_resource = createNewTestMerchantWithCatering($this->ids['menu_id'],$data);
        $merchant_id = $merchant_resource->merchant_id;
        $mcir = $merchant_resource->merchant_catering_info_resource;
        $mcir->maximum_number_of_catering_orders_per_day_part = 2;
        $mcir->max_days_out = 2;
        $mcir->save();

        // first create an existing catering order tomorrow morning
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $catering_data = $this->getCateringOrderData($merchant_id);
        $timestamp_of_event = getTomorrowTwelveNoonTimeStampDenver()-3600;
        $catering_data['timestamp_of_event'] = $timestamp_of_event;

        $request = createRequestObject("/apiv2/catering","POST",json_encode($catering_data));
        $catering_controller = new CateringController($m,$user,$request);
        $response = $catering_controller->processV2Request();
        $this->assertTrue(is_a($response,'Resource'),"The response should be a resource");
        $this->assertNull($response->error);



        // use today at 8pm
        $current_time = getTimeStampForDateTimeAndTimeZone(20, 0, 0, date('m'), date('d'), date('Y'), "America/Denver");
        $request = createRequestObject("app2/apiv2/merchants/$merchant_id/cateringorderavailabletimes/pickup",'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $merchant_controller->setTheTime($current_time);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $submit_times_array  = $resource->catering_place_order_times;

        // first available time should be tomorrow morning 9am since this merchant allows 2 catering orders per day part
        $expected_first_ts = getTomorrowTwelveNoonTimeStampDenver() - (3*3600);
        $this->assertEquals(date('Y-m-d H:i:s',$expected_first_ts),date('Y-m-d H:i:s',$submit_times_array[0]));

        // now add a second catering order to tomorrow morning
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $catering_data = $this->getCateringOrderData($merchant_id);
        $timestamp_of_event = getTomorrowTwelveNoonTimeStampDenver();
        $catering_data['timestamp_of_event'] = $timestamp_of_event;

        $request = createRequestObject("/apiv2/catering","POST",json_encode($catering_data));
        $catering_controller = new CateringController($m,$user,$request);
        $response = $catering_controller->processV2Request();
        $this->assertTrue(is_a($response,'Resource'),"The response should be a resource");
        $this->assertNull($response->error);

        // now get available times again and first time should be 2pm tomorrow use today at 8pm
        $current_time = getTimeStampForDateTimeAndTimeZone(20, 0, 0, date('m'), date('d'), date('Y'), "America/Denver");
        $request = createRequestObject("app2/apiv2/merchants/$merchant_id/cateringorderavailabletimes/pickup",'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $merchant_controller->setTheTime($current_time);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $submit_times_array  = $resource->catering_place_order_times;

        // first available time should be tomorrow afternoon 2pm since this merchant allows only 2 catering orders per day part and there are now 2 before 1pm tomorrow
        $expected_first_ts = getTomorrowTwelveNoonTimeStampDenver() + (2*3600);
        $this->assertEquals(date('Y-m-d H:i:s',$expected_first_ts),date('Y-m-d H:i:s',$submit_times_array[5]),"First available time should be 2pm tomorrow since there are already 2 catering orders tomorrow morning");

    }

    static function setUpBeforeClass()
    {
        ini_set('max_execution_time',0);
        SplickitCache::flushAll();
        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction();

        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        ini_set('max_execution_time',300);

        $skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty("cateringskintwo","cateringbrandtwo");
        $ids['skin_id'] = $skin_resource->skin_id;
        $ids['context'] = 'com.splickit.cateringskintwo';
        setContext('com.splickit.cateringskintwo');

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

        $data['time_zone'] = 'pacific';
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
        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->rollback();
        date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
        $suite = new PHPUnit_Framework_TestSuite( __CLASS__);
        PHPUnit_TextUI_TestRunner::run( $suite);
    }



}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    CateringOrderTest::main();
}

?>