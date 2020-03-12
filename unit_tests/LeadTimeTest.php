<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class LeadTimeTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $noon_friday_feb_14;
	var $near_closing_friday_feb_14;
	var $user_id;
	var $user;
	var $merchant_id;
	var $menu_id;
	var $noonish_feb_12_denver;

	function setUp()
	{
		setProperty('do_not_call_out_to_aws','true');
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->merchant_id = $_SERVER['unit_test_ids']['merchant_id'];
		$this->user_id = $_SERVER['unit_test_ids']['user_id'];
		$this->menu_id = $_SERVER['unit_test_ids']['menu_id'];
		setProperty("global_throttling", "true");
		setContext("com.splickit.order");

		// we dont want to call to inspirepay 
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->user = logTestUserIn($this->user_id);
		$this->noonish_friday_feb_14 = 1360868336;
		myerror_log("2ish friday east coast: ".date("l F j, Y, g:i a",$this->noonish_friday_feb_14));
		
		$this->noonish_feb_12_denver =  $_SERVER['unit_test_ids']['noonish_denver_ts'];
	}

	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->stamp);
		unset($this->noon_friday_feb_14);
		unset($this->near_closing_friday_feb_14);
		unset($this->user);
		unset($this->merchant_id);
		unset($this->user_id);
		unset($this->menu_id);
    }

    function testGetCheckoutDataForDeliveryStartTimes()
    {
        $merchant_resource = createNewTestMerchantDelivery($this->menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        $mdi_adapter = new MerchantDeliveryInfoAdapter(getM());
        $mdi_resource = $mdi_adapter->getFullMerchantDeliveryInfoAsResource($merchant_id);
        $this->assertNotNull($mdi_resource);

        //9750	20000		1405 Arapahoe Ave		boulder	Co	80302	40.014594	-105.275990	3038844083		2012-04-23 16:59:25	2012-04-23 16:59:24	N
        $json = '{"user_id":"'.$this->user['user_id'].'","name":"","address1":"1405 Arapohoe Ave","address2":"","city":"Boulder","state":"CO","zip":"80302","phone_no":"1234567890","lat":40.014594,"lng":-105.275990}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "Application/json";
        $request->_parseRequestBody();
        $user_controller = new UserController($mt, $this->user, $request,5);
        $response = $user_controller->setDeliveryAddr();
        $this->assertNull($response->error,"should not have gotten a delivery save error but did");
        $user_address_id = $response->user_addr_id;

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'delivery');
        $cart_data['user_addr_id'] = $user_address_id;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data);
        $lead_times = $checkout_resource->lead_times_array;
        $day = date('d',$lead_times[0]);
        foreach ($lead_times as $lead_time) {
            //determin the first time on the next day
            if ($day != date('d',$lead_time)) {
                $first_ts_on_new_day = $lead_time;
                break;
            }
        }
        $first_time_on_new_day = date("H:i",$first_ts_on_new_day);
        $this->assertEquals('07:05',$first_time_on_new_day);

        $checkout_data = $checkout_resource->getDataFieldsReally();

        $this->assertNotNull($lead_times,"lead times array should not be null. error: ".$checkout_data['ERROR']);
        $new_first_time = $lead_times[0];
        $new_diff = $new_first_time - $ts;
        $this->assertTrue($new_diff > 4490);
        $this->assertNotNull($checkout_data['total_tax_amt']);
        $this->assertNull($checkout_data['error']);
    }

    function testGetLeadTimesWithThrottlingXX()
    {
    }

    function testGetLeadTimeNearOpenLessThanLeadTime()
    {
        $merchant_resource = createNewTestMerchant($this->menu_id);
        $merchant_resource->lead_time = 15;
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        $merchant_resource = $merchant_resource->refreshResource($merchant_id);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $time_stamp = getTimeStampForDateTimeAndTimeZone(6, 46, 0, date('m'), date('d'), date('Y'), "America/Denver");
        $time_string = date("Y-m-d H:i:s",$time_stamp);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,$time_stamp);
        $this->assertNull($checkout_resource->error);
        $first_time = $checkout_resource->lead_times_array[0];
        $first_time_string = date("Y-m-d H:i:s",$first_time);
        $expected_first_time =  getTimeStampForDateTimeAndTimeZone(7, 01, 0, date('m'), date('d'), date('Y'), "America/Denver");
        $expected_first_time_string = date("Y-m-d H:i:s",$expected_first_time);
        $this->assertEquals($expected_first_time_string,$first_time_string);
    }

    function testGetLeadTimeNearOpenMoreThanLeadTime()
    {
        $merchant_resource = createNewTestMerchant($this->menu_id);
        $merchant_resource->lead_time = 15;
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        $merchant_resource = $merchant_resource->refreshResource($merchant_id);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $time_stamp = getTimeStampForDateTimeAndTimeZone(6, 44, 0, date('m'), date('d'), date('Y'), "America/Denver");
        $time_string = date("Y-m-d H:i:s",$time_stamp);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,$time_stamp);
        $this->assertNull($checkout_resource->error);
        $first_time = $checkout_resource->lead_times_array[0];
        $first_time_string = date("Y-m-d H:i:s",$first_time);
        $expected_first_time =  getTimeStampForDateTimeAndTimeZone(7, 01, 0, date('m'), date('d'), date('Y'), "America/Denver");
        $expected_first_time_string = date("Y-m-d H:i:s",$expected_first_time);
        $this->assertEquals($expected_first_time_string,$first_time_string);
    }

    function testGetLeadTime()
    {
        $merchant_resource = createNewTestMerchant($this->menu_id);
        $merchant_resource->lead_time = 15;
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;

        $mysql_day = date('w')+2;
        $created_resource = Resource::createByData(new LeadTimeByDayPartMapsAdapter(),["merchant_id"=>$merchant_id,"day_of_week"=>$mysql_day,"start_time"=>'11:00:00',"end_time"=>'13:00:00',"lead_time"=>30]);

        $lead_time_object = new LeadTime($merchant_resource);
        $lead_time = $lead_time_object->getPickupLeadtime(getTodayTwelveNoonTimeStampDenver());
        $this->assertEquals(15,$lead_time);

        $lead_time2 = $lead_time_object->getPickupLeadtime(getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals(30,$lead_time2);

        $lead_time3 = $lead_time_object->getPickupLeadtime(getTomorrowTwelveNoonTimeStampDenver()-(61*60));
        $this->assertEquals(15,$lead_time3);

        $lead_time4 = $lead_time_object->getPickupLeadtime(getTomorrowTwelveNoonTimeStampDenver()-(59*60));
        $this->assertEquals(30,$lead_time4);

        $lead_time5 = $lead_time_object->getPickupLeadtime(getTomorrowTwelveNoonTimeStampDenver()+(44*60));
        $this->assertEquals(30,$lead_time5);

        $lead_time6 = $lead_time_object->getPickupLeadtime(getTomorrowTwelveNoonTimeStampDenver()+(46*60));
        $this->assertEquals(30,$lead_time6);

        $lead_time6 = $lead_time_object->getPickupLeadtime(getTomorrowTwelveNoonTimeStampDenver()+(61*60));
        $this->assertEquals(15,$lead_time6);

        return $merchant_resource;
    }

    /**
     * @depends testGetLeadTime
     */
    function testLeadTimeByDayPart($merchant_resource)
    {

        $merchant_id = $merchant_resource->merchant_id;
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleCartArrayByMerchantId($this->merchant_id, 'pickup', 'the note');
        $cart_resource = $this->createCartFromOrderData($order_data,$user);
        //$json_encoded_data = json_encode($order_data);
        setProperty("global_throttling", "false");

        $lead_time = new LeadTime($merchant_resource);
        $lead_time->setPickupMaxLead(240);

        $tomorrow_1029 = getTomorrowTwelveNoonTimeStampDenver() - (61*60);
        $lead_times_array = $lead_time->getLeadTimesArrayFromOrderObjectWithThrottling(new Order($cart_resource->ucid),$tomorrow_1029);
        $this->assertEquals(date("l F j, Y, g:i a",$tomorrow_1029+(15*60)),date("l F j, Y, g:i a",$lead_times_array[0]));


        $tomorrow_1031 = getTomorrowTwelveNoonTimeStampDenver() - (59*60);
        $lead_times_array = $lead_time->getLeadTimesArrayFromOrderObjectWithThrottling(new Order($cart_resource->ucid),$tomorrow_1031);
        $this->assertEquals(date("l F j, Y, g:i a",$tomorrow_1031+(30*60)),date("l F j, Y, g:i a",$lead_times_array[0]));

    }

    /**
     * @depends testGetLeadTime
     */
    function testLeadTimeByDayPartDelivery($merchant_resource)
    {

        $merchant_id = $merchant_resource->merchant_id;

        $mysql_day = date('w')+2;
        $created_resource = Resource::createByData(new LeadTimeByDayPartMapsAdapter(),["merchant_id"=>$merchant_id,"day_of_week"=>$mysql_day,"start_time"=>'11:59:00',"end_time"=>'15:00:00',"lead_time"=>90,'hour_type'=>'D']);


        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $json = '{"user_id":"'.$user['user_id'].'","name":"","address1":"1405 Arapohoe Ave","address2":"","city":"Boulder","state":"CO","zip":"80302","phone_no":"1234567890","lat":40.014594,"lng":-105.275990}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "Application/json";
        $request->_parseRequestBody();
        $user_controller = new UserController($mt, $user, $request,5);
        $response = $user_controller->setDeliveryAddr();
        $this->assertNull($response->error,"should not have gotten a delivery save error but did");
        $user_address_id = $response->user_addr_id;





        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note');
        $order_data['user_addr_id'] = $user_address_id;
        $cart_resource = $this->createCartFromOrderData($order_data,$user);
        //$json_encoded_data = json_encode($order_data);
        setProperty("global_throttling", "false");

        $lead_time = new LeadTime($merchant_resource);

        $order = new Order($cart_resource->ucid);
        $this->assertTrue($order->isDeliveryOrder(),'It should have created a delivery order');

        $tomorrow_1000 = getTomorrowTwelveNoonTimeStampDenver() - (120*60);
        $lead_times_array = $lead_time->getLeadTimesArrayFromOrderObjectWithThrottling($order,$tomorrow_1000);
        $this->assertEquals(date("l F j, Y, g:i a",$tomorrow_1000+(45*60)),date("l F j, Y, g:i a",$lead_times_array[0]));

        $lead_time = new LeadTime($merchant_resource);
        $lead_times_array = $lead_time->getLeadTimesArrayFromOrderObjectWithThrottling($order,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals(date("l F j, Y, g:i a",getTomorrowTwelveNoonTimeStampDenver()+(90*60)),date("l F j, Y, g:i a",$lead_times_array[0]));


        $lead_time = new LeadTime($merchant_resource);
        $tomorrow_1130 = getTomorrowTwelveNoonTimeStampDenver() - (30*60);
        $lead_times_array = $lead_time->getLeadTimesArrayFromOrderObjectWithThrottling($order,$tomorrow_1130);
        $this->assertEquals(date("l F j, Y, g:i a",$tomorrow_1130+(45*60)),date("l F j, Y, g:i a",$lead_times_array[0]));

    }




    /**
     * @depends testGetLeadTime
     */
    function testCreateMessagesWithDayPartLeadTime($merchant_resource)
    {
        $merchant_id = $merchant_resource->merchant_id;
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'pickup','the note');
        $checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);

        $lead_times_array = $checkout_resource->lead_times_array;
        $this->assertEquals(getTomorrowTwelveNoonTimeStampDenver()+1800,$lead_times_array[0],"first time should be 12:30 but is was: ".date("H:i:s",$lead_times_array[0]));

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);

        $order_message = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_resource->order_id,'E');
        $next_message_dt_tm = date("Y-m-d H:i:s",$order_message->next_message_dt_tm);
        $expected_next_message_dt_tm = date("Y-m-d H:i:s",getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals($expected_next_message_dt_tm,$next_message_dt_tm);

    }

    /**
     * @depends testGetLeadTime
     */
    function testCreateMessagesWithDayPartLeadTimeForLargeOrder($merchant_resource)
    {
        $merchant_id = $merchant_resource->merchant_id;
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'pickup','the note',6);
        $checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);

        $lead_times_array = $checkout_resource->lead_times_array;
        $this->assertEquals(getTomorrowTwelveNoonTimeStampDenver()+(37*60),$lead_times_array[0],"first time should be 12:37 but is was: ".date("H:i:s",$lead_times_array[0]));

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);

        $order_message = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_resource->order_id,'E');
        $next_message_dt_tm = date("Y-m-d H:i:s",$order_message->next_message_dt_tm);
        $expected_next_message_dt_tm = date("Y-m-d H:i:s",getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals($expected_next_message_dt_tm,$next_message_dt_tm);

    }


    function testThottleingOnLargeDeliveryOrdersShouldNotShow45MinuteLeadTime()
	{
		setProperty("global_throttling", "true");
		$merchant_resource = createNewTestMerchantDelivery($this->menu_id);
		$merchant_id = $merchant_resource->merchant_id;

		$mdi_adapter = new MerchantDeliveryInfoAdapter(getM());
		$mdi_resource = $mdi_adapter->getFullMerchantDeliveryInfoAsResource($merchant_id);

		//9750	20000		1405 Arapahoe Ave		boulder	Co	80302	40.014594	-105.275990	3038844083		2012-04-23 16:59:25	2012-04-23 16:59:24	N
		$user_resource = createNewUserWithCCNoCVV();
		$user = logTestUserResourceIn($user_resource);
		$json = '{"user_id":"'.$user['user_id'].'","name":"","address1":"1405 Arapohoe Ave","address2":"","city":"Boulder","state":"CO","zip":"80302","phone_no":"1234567890","lat":40.014594,"lng":-105.275990}';
		$request = new Request();
		$request->body = $json;
		$request->mimetype = "Application/json";
		$request->_parseRequestBody();
		$user_controller = new UserController($mt, $user, $request,5);
		$response = $user_controller->setDeliveryAddr();
		$this->assertNull($response->error,"should not have gotten a delivery save error but did");
		$user_address_id = $response->user_addr_id;

		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', 'no note');
		$item = $order_data['items'][0];
		for ($i=0;$i<50;$i++) {
			$order_data['items'][] = $item;
		}
		$order_data['delivery'] = 'Y';
		$order_data['delivery_amt'] = 0.00;
		$order_data['delivery_tax_amount'] = 0.00;
		$order_data['user_addr_id'] = $user_address_id;
		$json = json_encode($order_data);


		//$json = $order_adapter->getSimpleOrderJSONByMerchantId(1080, 'delivery', 'no note');
		$request = createRequestObject("/apiv2/cart/checkout","POST",$json,'application/json');
		$place_order_controller = new PlaceOrderController($mimetypes,$user,$request,5);
		$place_order_controller->setForcedTs(getTomorrowTwelveNoonTimeStampDenver());
		$resource = $place_order_controller->processV2Request();
		$this->assertEquals("Please note, your first available delivery time for this order is over 2 hours from now.", $resource->user_message);
		$lead_times = $resource->lead_times_array;
		$checkout_data = $resource->getDataFieldsReally();

		$this->assertNotNull($lead_times,"lead times array should not be null. error: ".$checkout_data['ERROR']);
		$new_first_time = $lead_times[0];
		$date_string = date('Y-m-d H:i:s',$new_first_time);
		$diff_in_minutes = ($new_first_time - getTomorrowTwelveNoonTimeStampDenver())/60;
		$this->assertEquals(150,$diff_in_minutes,"It should a first lead time 130 minutes in the future");
	}

	function testGetCateringLeadTime()
	{
		$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
		$merchant_id = $merchant_resource->merchant_id;
		$lead_time = new LeadTime($merchant_resource);
		$this->assertEquals(720,$lead_time->getCateringMinLeadForMerchant($merchant_id));
		$data['merchant_id'] = $merchant_id;
		$data['catering_minimum_lead_time'] = 180;
		$resource = Resource::createByData(new MerchantAdvancedOrderingInfoAdapter($m),$data);
		$this->assertEquals(180,$lead_time->getCateringMinLeadForMerchant($merchant_id));
	}

    function testAdvancedOrderingPickupTimes()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $merchant_resource = createNewTestMerchant($this->menu_id);
        $merchant_resource->advanced_ordering = 'Y';
        $merchant_resource->lead_time = 15;
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        $mao_data = array("merchant_id"=>$merchant_id);
        $mao_data['max_days_out'] = 6;
        $maoia = new MerchantAdvancedOrderingInfoAdapter($m);
        $maoi_resource = Resource::createByData($maoia,$mao_data);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'pickup','the note');
        $json_encoded_data = json_encode($order_data);

        $request = createRequestObject('/app2/apiv2/cart/checkout','post',$json_encoded_data,'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNotNull($checkout_resource,"should have gotten a cart resource back");
        $this->assertNull($checkout_resource->error);
        $cart_ucid = $checkout_resource->ucid;
        $leadtimes_array = $checkout_resource->lead_times_array;
        // get first time of each day
        $starting_day = date('D',$leadtimes_array[0]);
        //$starting_ts[$starting_day] = $leadtimes_array[0];
        $starting_tss = array();
        foreach ($leadtimes_array as $ts) {
            if ($starting_day != date('D',$ts)) {
                $starting_tss[] = array('day'=>date('D',$ts),'ts'=>$ts,'time'=>date('h:i',$ts));
                $starting_day = date('D',$ts);
            }
        }
        $this->assertCount(6,$starting_tss,"there should be 6 days in advance");
        // since min lead time is less than 16 minutes, first order should always be 15 minutes after opening
        // NO LONGER BUFFERING!!!!!!!!!  back to opening time now
        $this->assertEquals('07:00',$starting_tss[0]['time'],"first day in advance should start 15 minutes after opening");
        $this->assertEquals('07:00',$starting_tss[1]['time'],"2nd day in advance should start 15 minutes after opening");
        $this->assertEquals('07:00',$starting_tss[2]['time'],"3rd day in advance should start 15 minutes after opening");
        $this->assertEquals('07:00',$starting_tss[3]['time'],"4th day in advance should start 15 minutes after opening");
        $this->assertEquals('07:00',$starting_tss[4]['time'],"5th day in advance should start 15 minutes after opening");
        $this->assertEquals('07:00',$starting_tss[5]['time'],"6th day in advance should start 15 minutes after opening");

    }

    function testAdvancedOrderingPickupTimesLargeOrder()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $merchant_resource = createNewTestMerchant($this->menu_id);
        $merchant_resource->advanced_ordering = 'Y';
        $merchant_resource->lead_time = 15;
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        $mao_data = array("merchant_id"=>$merchant_id);
        $mao_data['max_days_out'] = 6;
        $maoia = new MerchantAdvancedOrderingInfoAdapter($m);
        $maoi_resource = Resource::createByData($maoia,$mao_data);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'pickup','the note',15);
        $json_encoded_data = json_encode($order_data);

        $request = createRequestObject('/app2/apiv2/cart/checkout','post',$json_encoded_data,'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNotNull($checkout_resource,"should have gotten a cart resource back");
        $this->assertNull($checkout_resource->error);
        $cart_ucid = $checkout_resource->ucid;
        $leadtimes_array = $checkout_resource->lead_times_array;
        // get first time of each day
        $starting_day = date('D',$leadtimes_array[0]);
        //$starting_ts[$starting_day] = $leadtimes_array[0];
        $starting_tss = array();
        foreach ($leadtimes_array as $ts) {
            if ($starting_day != date('D',$ts)) {
                $starting_tss[] = array('day'=>date('D',$ts),'ts'=>$ts,'time'=>date('h:i',$ts));
                $starting_day = date('D',$ts);
            }
        }
        $this->assertCount(6,$starting_tss,"there should be 6 days in advance");
        // since min lead time is less than 16 minutes, first order should always be 15 minutes after opening
        $this->assertEquals('07:00',$starting_tss[0]['time'],"first day in advance should start at opening");
        $this->assertEquals('07:00',$starting_tss[1]['time'],"2nd day in advance should start at opening");
        $this->assertEquals('07:00',$starting_tss[2]['time'],"3rd day in advance should start at opening");
        $this->assertEquals('07:00',$starting_tss[3]['time'],"4th day in advance should start at opening");
        $this->assertEquals('07:00',$starting_tss[4]['time'],"5th day in advance should start at opening");
        $this->assertEquals('07:00',$starting_tss[5]['time'],"6th day in advance should start at opening");

    }


    function testAdvancedOrderingPickupMaxDaysOut()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $merchant_resource = createNewTestMerchant($this->menu_id);
        $merchant_resource->advanced_ordering = 'Y';
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        $mao_data = array("merchant_id"=>$merchant_id);
        $mao_data['max_days_out'] = 3;
        $maoia = new MerchantAdvancedOrderingInfoAdapter($m);
        $maoi_resource = Resource::createByData($maoia,$mao_data);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'pickup','the note');
        $json_encoded_data = json_encode($order_data);

        $request = createRequestObject('/app2/apiv2/cart/checkout','post',$json_encoded_data,'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNotNull($checkout_resource,"should have gotten a cart resource back");
        $this->assertNull($checkout_resource->error);

        // make sure last time is 3 days out.
        $last_time = array_pop($checkout_resource->lead_times_array);
        $day = date('D',$last_time);
        $expected_day = date('D',getTomorrowTwelveNoonTimeStampDenver()+(3*24*60*60));
        $this->assertEquals($expected_day,$day,"it should be 3 days out");
    }



  function testGetNumberOfEntreLevelItemsInOrder()
  {
    $order_adapter = new OrderAdapter($mimetypes);
    $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->merchant_id, 'pickup','skip hours',5);
    $merchant_resource = SplickitController::getResourceFromId($this->merchant_id, 'Merchant');
    $lead_time = new LeadTime($merchant_resource);
    $number_of_e_leve_items = $lead_time->getNumberOfEntreLevelItemsInOrderItemsData($order_data['items']);
    $this->assertEquals(5, $number_of_e_leve_items);

    $menu_id = createTestMenu('D', 5);
    $order_data2 = $order_adapter->getSimpleOrderArray($menu_id, $merchant_id, $note,10);
    $items2 = $order_data2['items'];
    $this->assertEquals(10, count($items2));
    $number_of_e_leve_items = $lead_time->getNumberOfEntreLevelItemsInOrderItemsData($items2);
    $this->assertEquals(0, $number_of_e_leve_items);
  }
    
  function testGetAvailableTimesArrayWithCustomMaxLead()
  {
    $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    $leadtime = new LeadTime($merchant_resource);
    $time_stamp = getTimeStampForDateTimeAndTimeZone(12, 0, 0, 6, 1, 2014, "America/Denver");
    $first_time_stamp = getTimeStampForDateTimeAndTimeZone(12, 15, 0, 6, 1, 2014, "America/Denver");
    $expected_last_time = getTimeStampForDateTimeAndTimeZone(20, 0, 0, 6, 30, 2014, "America/Denver");
    $leadtime->setCurrentTime($time_stamp);
    $thirty_days_in_minutes = 60*24*30;
    $leadtime->setPickupMaxLead($thirty_days_in_minutes);
    $leadtime->setMinMaxAndIncrement(1, 'R');
    $hour_adapter = new HourAdapter($mimetypes);
    $open_close_ts_array = $hour_adapter->getNextOpenAndCloseTimeStamps($merchant_resource->merchant_id, 'R', 30,$time_stamp);
    $available_times = $leadtime->getAvailableTimesArray($open_close_ts_array, $first_time_stamp, $time_stamp, 'R');
    $last_time = array_pop($available_times);
    $expected_last_time_formatted = getMySqlFormattedDateTimeFromTimeStampAndTimeZone($expected_last_time, "America/Denver");
    $last_time_formatted = getMySqlFormattedDateTimeFromTimeStampAndTimeZone($last_time, "America/Denver");
    $this->assertEquals($expected_last_time_formatted, $last_time_formatted);

  }
    
  function testGetAvailableTimesArrayFromOpenCloseTimeStamp()
  {
    $tz = date_default_timezone_get();
    date_default_timezone_set("America/Denver");
    //tuesday the 12th
    $early_morning_ts = mktime( 10, 00, 0, 2  , 12, 2013);
    $open_ts = mktime( 10 , 30, 0, 2  , 12, 2013);
    $noon_ts = mktime(12, 30, 0, 2  , 12, 2013);
    $close_ts = mktime( 20 , 30, 0, 2  , 12, 2013);
    $late_night_ts = mktime(22, 30, 0, 2  , 12, 2013);
    date_default_timezone_set($tz);

    $open_close_ts_array[0] = array('open'=>$open_ts,'close'=>$close_ts);

    $lead_time = new LeadTime($this->merchant_id);
    $lead_time->setMinMaxAndIncrement(1, 'R');

             //$lead_time->getAvailableTimesArray($open_close_ts_array, $first_time,            $the_time,     $hour_type)
    $available_times = $lead_time->getAvailableTimesArray($open_close_ts_array, $early_morning_ts+1200, $early_morning_ts,'R');
    $this->assertNotNull($available_times);
    //first time should be minumum lead from opening because its the first day
    $expected = 'Tuesday February 12, 2013, 10:31 am';
    $actual = date("l F j, Y, g:i a",$available_times[0]);
    $this->assertEquals($expected, $actual);
    // second time should be 1 minute after the first
    $second_time = date("l F j, Y, g:i a",$available_times[1]);
    $first_time_plus_60_seconds = date("l F j, Y, g:i a",$available_times[0]+60);
    $this->assertEquals($first_time_plus_60_seconds, $second_time);

    $available_times = $lead_time->getAvailableTimesArray($open_close_ts_array, $noon_ts+1200, $noon_ts,'R');
    $this->assertNotNull($available_times);
    $expected = 'Tuesday February 12, 2013, 12:50 pm';
    $actual = date("l F j, Y, g:i a",$available_times[0]);
    $this->assertEquals($expected, $actual);
    $this->assertEquals($noon_ts+1200, $available_times[0]);

    $available_times = $lead_time->getAvailableTimesArray($open_close_ts_array, $late_night_ts+1200, $late_night_ts,'R');
    $this->assertEquals(0, count($available_times));
  }

  function testGetAvailableTimesArrayFromOpenCloseTimeStampWithIntervalNotFallingOnClosingTime()
  {
    $tz = date_default_timezone_get();
    date_default_timezone_set("America/Denver");
    //tuesday the 12th
    $open_ts = mktime( 10 , 30, 0, 2  , 12, 2013);
    $late_afternoon_near_closing_ts = mktime(19, 07, 38, 2  , 12, 2013);
    $close_ts = mktime( 20 , 30, 0, 2  , 12, 2013);
    $late_night_ts = mktime(22, 30, 0, 2  , 12, 2013);
    date_default_timezone_set($tz);

    $open_close_ts_array[1] = array('open'=>$open_ts,'close'=>$close_ts);

    $lead_time = new LeadTime($this->merchant_id);
    $lead_time->setMinMaxAndIncrement(1, 'R');

             //$lead_time->getAvailableTimesArray($open_close_ts_array, $first_time,            $the_time,     $hour_type)
    $available_times = $lead_time->getAvailableTimesArray($open_close_ts_array, $late_afternoon_near_closing_ts+1200, $late_afternoon_near_closing_ts,'R');
    $this->assertNotNull($available_times);
    $expected = 'Tuesday February 12, 2013, 8:30 pm';
    $last_time = array_pop($available_times);
    $actual = date("l F j, Y, g:i a",$last_time);
    $this->assertEquals($expected, $actual);
    $this->assertEquals($close_ts, $last_time);
  }
    
  function testGetLeadTimesArrayFromOrderData()
  {
      $user_resource = createNewUserWithCCNoCVV();
      $user = logTestUserResourceIn($user_resource);
    $order_adapter = new OrderAdapter($mimetypes);
    $order_data = $order_adapter->getSimpleCartArrayByMerchantId($this->merchant_id, 'pickup', 'the note');
    $cart_resource = $this->createCartFromOrderData($order_data,$user);

    $merchant_resource = SplickitController::getResourceFromId($this->merchant_id, 'Merchant');
    $lead_time = new LeadTime($merchant_resource);
    $tz = date_default_timezone_get();
    date_default_timezone_set("America/Denver");
    //tuesday the 12th
    $ts = mktime(12, 30, 0, 2  , 12, 2013);
    date_default_timezone_set($tz);

    $lead_time_array = $lead_time->getLeadTimesArrayFromOrder(new Order($cart_resource->ucid),$ts);
    $this->assertNotNull($lead_time_array);
    $this->assertEquals($lead_time_array[0], $ts+1200);
  }
    
  function testGetCheckoutData()
  {
      $merchant_id = $this->merchant_id;
      $user = logTestUserIn($this->user['user_id']);
      $order_adapter = new OrderAdapter($mimetypes);
      $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','dum note',1);

      $tz = date_default_timezone_get();
    date_default_timezone_set("America/Denver");
    //tuesday the 12th
    $ts = mktime(12, 30, 0, 2  , 12, 2013);
    date_default_timezone_set($tz);


    $resource = getCheckoutResourceFromOrderData($order_data,$ts);
    $checkout_data = $resource->getDataFieldsReally();
    $this->assertNotNull($checkout_data['lead_times_array'],"lead times array should not be null. error: ".$checkout_data['error']);
    $this->assertEquals('Tuesday February 12, 2013, 1:00 pm',date("l F j, Y, g:i a",$checkout_data['lead_times_array'][10]));
    $this->assertEquals('Tuesday February 12, 2013, 2:20 pm',date("l F j, Y, g:i a",array_pop($checkout_data['lead_times_array'])));
    $this->assertEquals(1360699200,$checkout_data['lead_times_array'][10]);
    $this->assertNotNull($checkout_data['total_tax_amt']);
    $this->assertNull($checkout_data['error']);

  }
    
  function testGetCheckoutDataForDelivery()
  {
    $merchant_id = $this->merchant_id;
    $merchant_resource = SplickitController::getResourceFromId($merchant_id, "Merchant");
    $merchant_resource->delivery = 'Y';
    $merchant_resource->save();

    $mdi_adapter = new MerchantDeliveryInfoAdapter($mimetypes);
    $mdi_resource = $mdi_adapter->getFullMerchantDeliveryInfoAsResource($merchant_id);
    $this->assertNotNull($mdi_resource);
    $mdi_resource->minimum_delivery_time = 75;
    $mdi_resource->save();

    //9750	20000		1405 Arapahoe Ave		boulder	Co	80302	40.014594	-105.275990	3038844083		2012-04-23 16:59:25	2012-04-23 16:59:24	N
    $json = '{"user_id":"'.$this->user['user_id'].'","name":"","address1":"1405 Arapohoe Ave","address2":"","city":"Boulder","state":"CO","zip":"80302","phone_no":"1234567890","lat":40.014594,"lng":-105.275990}';
    $request = new Request();
    $request->body = $json;
    $request->mimetype = "Application/json";
    $request->_parseRequestBody();
    $user_controller = new UserController($mt, $this->user, $request,5);
    $response = $user_controller->setDeliveryAddr();
    $this->assertNull($response->error,"should not have gotten a delivery save error but did");
    $user_address_id = $response->user_addr_id;

    $order_adapter = new OrderAdapter($mimetypes);
    $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', 'no note');
    $order_data['delivery'] = 'Y';
    $order_data['delivery_amt'] = 0.00;
    $order_data['delivery_tax_amount'] = 0.00;
    $order_data['user_addr_id'] = $user_address_id;
    $json = json_encode($order_data);

    $tz = date_default_timezone_get();
    date_default_timezone_set("America/Denver");
    $ts = mktime(12, 30, 0, 2  , 12, 2013);
    date_default_timezone_set($tz);

    //$json = $order_adapter->getSimpleOrderJSONByMerchantId(1080, 'delivery', 'no note');
      $request = createRequestObject("/apiv2/cart/checkout","POST",$json,'application/json');
    $user = $this->user;
    $place_order_controller = new PlaceOrderController($mimetypes,$user,$request,5);
    $place_order_controller->setForcedTs($ts);
      $resource = $place_order_controller->processV2Request();
    $this->assertEquals("Please note, your first available delivery time for this order is 75 minutes from now.", $resource->user_message);
    $lead_times = $resource->lead_times_array;
    $checkout_data = $resource->getDataFieldsReally();

    $this->assertNotNull($lead_times,"lead times array should not be null. error: ".$checkout_data['ERROR']);
    $new_first_time = $lead_times[0];
    $new_diff = $new_first_time - $ts;
    $this->assertTrue($new_diff > 4490);
    $this->assertNotNull($checkout_data['total_tax_amt']);
    $this->assertNull($checkout_data['error']);
  }
    
/*    function testGetCheckoutDataFromDispatchTweb()
    {
		$json =  "{\"jsonVal\":{\"merchant_id\":\"1105\",\"items\":[{\"quantity\":1,\"note\":null,\"sizeprice_id\":\"361377\",\"mods\":[{\"mod_sizeprice_id\":\"2514193\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4007285\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"2513253\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4043975\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"3942327\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"2513263\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"2513303\",\"mod_quantity\":1}]}],\"lead_time\":\"0\",\"promo_code\":\"\",\"tip\":\"0.97\",\"sub_total\":\"1.00\",\"note\":\"skip hours\",\"user_id\":\"438786\"}}";
    	$url = "https://test.splickit.com/app2/phone/getcheckoutdata?log_level=5";
    	$curl = curl_init($url);
    	curl_setopt($curl, CURLOPT_USERPWD, "radamnyc@gmail.com:welcome");
    	curl_setopt($curl, CURLOPT_HTTPHEADER, array("X_SPLICKIT_CLIENT_ID:com.splickit.order","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:CheckoutDataTest","NO_CC_CALL:true")); 
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_VERBOSE,0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		$headers = array('Content-Type: application/json','Content-Length: ' . strlen($json));
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS,$json);
		$result = curl_exec($curl);
		curl_close($curl);
		$checkout_data = json_decode($result,true);
		$this->assertNotNull($checkout_data['lead_times_array'],"lead times array is null, probably due to store closed. error: ".$checkout_data['ERROR']);
		$this->assertNotNull($checkout_data['total_tax_amt']);
		$this->assertNull($checkout_data['error']);    	
    }
*/       
	function testGetLeadTimesArizona()
  {
    $merchant_resource = createNewTestMerchant($this->menu_id);
    $merchant_resource->state = 'AZ';
    $merchant_resource->save();

    $merchant_id = $merchant_resource->merchant_id;
    $order_adapter = new OrderAdapter($mimetypes);

    $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','notes');

    $tz = date_default_timezone_get();
    date_default_timezone_set("America/Denver");
    $ts = mktime(18, 52, 00, 5 , 9 , 2013);
    $arizona_afternoon_string = date("l F j, Y, g:i a",$ts);
    date_default_timezone_set($tz);

    myerror_log("starting test with afternoon string:  ".$arizona_afternoon_string);
    $json_encoded_data = json_encode($order_data);
    setProperty("global_throttling", "true");
      $checkout_resource = getCheckoutResourceFromOrderData($order_data,$ts);
    $lead_times_array = $checkout_resource->lead_times_array;
    $this->assertEquals(31,sizeof($lead_times_array, $mode));
    $tz = date_default_timezone_get();
    date_default_timezone_set("America/Phoenix");
    $this->assertEquals('Thursday May 9, 2013, 6:12 pm',date("l F j, Y, g:i a",$lead_times_array[0]));
    date_default_timezone_set($tz);
    $this->assertEquals($ts+1200,$lead_times_array[0]);

    //first ts should be 20 minutes from the ts set above since denver is an hour ahead of pheonix
    $diff = $lead_times_array[0] - $ts;
    $this->assertEquals(1200, $diff);
  }
    
  function testGetLeadTimesArrayForCateringBefore9pm()
  {
      setContext("com.splickit.worldhq");
    $menu_id = createTestCateringMenuWithOneItem("Test Item 1");

    $merchant_resource = createNewTestMerchant($menu_id);
    $merchant_id = $merchant_resource->merchant_id;
      $order_adapter = new OrderAdapter($mimetypes);
      $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','dum note',1);

    $tz = date_default_timezone_get();
    date_default_timezone_set("America/Denver");
    $current_time = mktime(13, 30, '0', date("m")  , date("d"), date("Y"));
    date_default_timezone_set($tz);

    setProperty("global_throttling", "false");
    $checkout_data_resource = getCheckoutResourceFromOrderData($order_data,$current_time);
    $lead_times_array = $checkout_data_resource->lead_times_array;
    $this->assertNotNull($lead_times_array);
    $expected_first_pickup = mktime(9, 0, '0', date("m")  , date("d")+1, date("Y"));
    $this->assertEquals(date('Y-m-d h:i:s',$expected_first_pickup),date('Y-m-d h:i:s',$lead_times_array[0]));
    $this->assertEquals($expected_first_pickup,$lead_times_array[0]);
    $this->assertEquals("Please note, your first available pickup time for this order is over 19 hours from now.",$checkout_data_resource->user_message);
    return $merchant_id;
  }
    
  /**
   * @depends testGetLeadTimesArrayForCateringBefore9pm
   */
  function testGetLeadTimesArrayForCateringAfter9pm($merchant_id)
  {

/*   	$current_time = mktime(21, 30, '0', date("m")  , date("d"), date("Y"));
    $tip = rand(100, 1000)/100;
    $pickup_time = time()+900;
    //$json_encoded_data = "{\"jsonVal\":{\"merchant_id\":\"103478\",\"items\":[{\"quantity\":1,\"note\":\"\",\"sizeprice_id\":\"751844\",\"mods\":[{\"mod_sizeprice_id\":\"4063418\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063392\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4066775\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063362\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063363\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063364\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063369\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063448\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063415\",\"mod_quantity\":1}]}],\"note\":\"\",\"lead_time\":15,\"promo_code\":\"\",\"tip\":\"$tip\",\"user_id\":\"20000\",\"loyalty_no\":\"\",\"sub_total\":\"5.95\",\"grand_total\":\"6.42\",\"actual_pickup_time\":\"$pickup_time\"}}";
    $json_encoded_data = "{\"jsonVal\":{\"merchant_id\":\"103478\",\"items\":[{\"quantity\":1,\"note\":\"\",\"mods\":[{\"mod_quantity\":5,\"modifier_item_id\":\"2261182\",\"size_id\":\"0\"},{\"mod_quantity\":1,\"modifier_item_id\":\"2258945\",\"size_id\":\"0\"},{\"mod_quantity\":1,\"modifier_item_id\":\"2261199\",\"size_id\":\"0\"},{\"mod_quantity\":1,\"modifier_item_id\":\"2258930\",\"size_id\":\"0\"},{\"mod_quantity\":1,\"modifier_item_id\":\"2258931\",\"size_id\":\"0\"},{\"mod_quantity\":1,\"modifier_item_id\":\"2258932\",\"size_id\":\"0\"},{\"mod_quantity\":1,\"modifier_item_id\":\"2258933\",\"size_id\":\"0\"},{\"mod_quantity\":1,\"modifier_item_id\":\"2258934\",\"size_id\":\"0\"},{\"mod_quantity\":1,\"modifier_item_id\":\"2258935\",\"size_id\":\"0\"},{\"mod_quantity\":1,\"modifier_item_id\":\"2258936\",\"size_id\":\"0\"},{\"mod_quantity\":1,\"modifier_item_id\":\"2261206\",\"size_id\":\"0\"},{\"mod_quantity\":1,\"modifier_item_id\":\"2271915\",\"size_id\":\"0\"}],\"item_id\":\"277705\",\"size_id\":\"89971\"}],\"note\":\"notes\",\"lead_time\":15,\"promo_code\":\"\",\"tip\":\"$tip\",\"user_id\":\"20000\",\"loyalty_no\":\"\",\"sub_total\":\"8.45\",\"grand_total\":\"9.11\",\"actual_pickup_time\":\"$pickup_time\"}}";//$json_encoded_data = "{\"jsonVal\":{\"merchant_id\":\"103478\",\"items\":[{\"quantity\":1,\"note\":\"\",\"sizeprice_id\":\"751843\",\"mods\":[{\"mod_sizeprice_id\":\"4063418\",\"mod_quantity\":5},{\"mod_sizeprice_id\":\"4063391\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063434\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063358\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063359\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063360\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063361\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063362\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063363\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063364\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063449\",\"mod_quantity\":1},{\"mod_sizeprice_id\":\"4063417\",\"mod_quantity\":1}]}],\"note\":\"skip hours\",\"lead_time\":15,\"promo_code\":\"\",\"tip\":\"$tip\",\"user_id\":\"20000\",\"loyalty_no\":\"\",\"sub_total\":\"8.45\",\"grand_total\":\"9.11\",\"actual_pickup_time\":\"$pickup_time\"}}";
*/
      $order_adapter = new OrderAdapter($mimetypes);
      $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','dum note',1);

    $tz = date_default_timezone_get();
    date_default_timezone_set("America/Denver");
    $current_time = mktime(21, 30, '0', date("m")  , date("d"), date("Y"));
    date_default_timezone_set($tz);

    setProperty("global_throttling", "false");
    $checkout_data_resource = getCheckoutResourceFromOrderData($order_data,$current_time);
    $lead_times_array = $checkout_data_resource->lead_times_array;
    $this->assertNotNull($lead_times_array);
    $expected_first_pickup = mktime(9, 30, '0', date("m")  , date("d")+1, date("Y"));
    $this->assertEquals($expected_first_pickup,$lead_times_array[0]);
    $this->assertEquals("Please note, your first available pickup time for this order is over 12 hours from now.",$checkout_data_resource->user_message);

  }

  function testGetLeadTimesWithThrottling()
  {
      $user_resource = createNewUserWithCCNoCVV();
      $user = logTestUserResourceIn($user_resource);

      $the_time = getTomorrowTwelveNoonTimeStampDenver();

      $merchant_id = $this->merchant_id;
      $lead_time_minutes = 30;
      $number_of_items = 4;

      $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'pickup','note',$number_of_items);
      //$json_encoded_data = json_encode($order_data);
      //$order_resource = placeTheOrder($json_encoded_data);$
      $checkout_resource = getCheckoutResourceFromOrderData($order_data);
      $this->assertNull($checkout_resource->error);
      $pickup_ts = $checkout_resource->lead_times_array[16];
      $pickup_string = date('g:ia',$pickup_ts);
      $pickup_ts_string = date("Y-m-d h:i:s");
      $checkout_resource->lead_times_array = [$checkout_resource->lead_times_array[16]];
      $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
      $order_id = $order_resource->order_id;
      $this->assertTrue($order_id > 1000);

      $user_resource = createNewUserWithCCNoCVV();
      $user = logTestUserResourceIn($user_resource);
      $order_adapter = new OrderAdapter(getM());
      $order_data = $order_adapter->getSimpleCartArrayByMerchantId($this->merchant_id, 'pickup', 'the note');
      $cart_resource = getCartResourceFromOrderData($order_data);

      $lead_time = new LeadTime($this->merchant_id);

      $lead_time->setElevelTimeForMerchant(120);
      $lead_time->setConcurrentlyAbleToMake(1);
      $lead_time->setNoThrottlingForThisMerchant(false);

      setProperty("global_throttling", "true");
      $lead_times_array = $lead_time->getLeadTimesArrayFromOrderObjectWithThrottling(new Order($cart_resource->ucid),$the_time);
      setProperty("global_throttling", "false");

      // now we need to check if there are no lead times available for the 8 minutes leading up to the order at
      $low_end_of_bad_zone = $pickup_ts - (8 *60);
      $low_end_of_bad_zone_string = date("Y-m-d h:i:s");
      myerror_log("there should be no pickup times between $low_end_of_bad_zone_string  and   $pickup_ts_string");
      foreach ($lead_times_array as $available_lead_ts)
      {
          $this_available_ts_string = date("Y-m-d H:i:s",$available_lead_ts);
          myerror_log("verifying  $this_available_ts_string");
          if ($available_lead_ts > $low_end_of_bad_zone && $available_lead_ts < $pickup_ts) {
              $this->assertTrue(false,"we have a time that should NOT be in the pickup time array: ".$this_available_ts_string);
          }
      }
  }
	
  function testGetLeadTimesArrayNearClosing()
  {
    $merchant_resource = createNewTestMerchant($this->menu_id);
    $merchant_id = $merchant_resource->merchant_id;
    $order_adapter = new OrderAdapter($mimetypes);
      $order_adapter->old_style = true;
    $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','the note');
    $json_encoded_data = json_encode($order_data);
    $hour_adapter = new HourAdapter($mimetypes);
    $hours = $hour_adapter->getAllMerchantHourRecords($merchant_id);
    $friday_close = $hours['pickup'][5]['close'];
    $h = explode(":", $friday_close);
    $hour = $h[0];
    $minute = $h[1];
    $tz = date_default_timezone_get();
    date_default_timezone_set("America/Denver");
    $near_closing = mktime($hour, $minute, 0, 2 , 15 , 2013)-(40*60);
    $near_closing_string = date("Y-m-d H:i:s",$near_closing);
    date_default_timezone_set($tz);
    setProperty("global_throttling", "false");
      $checkout_resource = getCheckoutResourceFromOrderData($order_data,$near_closing);
    $lead_times_array = $checkout_resource->lead_times_array;
    $this->assertEquals(sizeof(16,$lead_times_array, $mode));
    $last_time = array_pop($lead_times_array);
    $this->assertEquals('Friday February 15, 2013, 8:00 pm',date("l F j, Y, g:i a",$last_time));
    return $merchant_id;
  }
    
  /**
   *
   * @depends testGetLeadTimesArrayNearClosing
   */
  function testGetLeadTimesArrayAfterClosing($merchant_id)
  {
    $order_adapter = new OrderAdapter($mimetypes);
    $order_adapter->old_style = true;
      $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','the note');
    $json_encoded_data = json_encode($order_data);
    $hour_adapter = new HourAdapter($mimetypes);
    $hours = $hour_adapter->getAllMerchantHourRecords($merchant_id);
    $friday_close = $hours['pickup'][5]['close'];
    $h = explode(":", $friday_close);
    $hour = $h[0];
    $minute = $h[1];
    $tz = date_default_timezone_get();
    date_default_timezone_set("America/Denver");
    $near_closing = mktime($hour, $minute-10, 0, 2 , 15 , 2013);
    date_default_timezone_set($tz);
    setProperty("global_throttling", "false");

    $checkout_data_resource = $this->getCheckoutDataWithThrottling($json_encoded_data, $merchant_id, $near_closing);
    $this->assertEquals("We're sorry, this merchant is closed for mobile ordering right now.", $checkout_data_resource->error);
  }
    
  function testGetLeadTimesArray()
  {
    $merchant_resource = createNewTestMerchant($this->menu_id);
    $merchant_id = $merchant_resource->merchant_id;

      $user_resource = createNewUserWithCCNoCVV();
      $user = logTestUserResourceIn($user_resource);
      $order_adapter = new OrderAdapter($mimetypes);
      $order_data = $order_adapter->getSimpleCartArrayByMerchantId($this->merchant_id, 'pickup', 'the note');
      $cart_resource = $this->createCartFromOrderData($order_data,$user);
    //$json_encoded_data = json_encode($order_data);
    setProperty("global_throttling", "false");

    $lead_time = new LeadTime($merchant_resource);
    $lead_time->setPickupMaxLead(240);
      $lead_times_array = $lead_time->getLeadTimesArrayFromOrderObjectWithThrottling(new Order($cart_resource->ucid),$this->noonish_feb_12_denver);
    $this->assertEquals(41,sizeof($lead_times_array));
    $this->assertEquals('Tuesday February 12, 2013, 12:50 pm',date("l F j, Y, g:i a",$lead_times_array[0]));
    $last_time = array_pop($lead_times_array);
    $this->assertEquals('Tuesday February 12, 2013, 4:40 pm',date("l F j, Y, g:i a",$last_time));
    return $merchant_id;
  }

	function testGetLeadTimesArrayWithAllowAsap()
	{
		setProperty("global_throttling", "true");

		$merchant_id = $this->merchant_id;
		$merchant_resource = SplickitController::getResourceFromId($merchant_id, "Merchant");
		$merchant_resource->delivery = 'Y';
		$merchant_resource->save();

		$mdi_adapter = new MerchantDeliveryInfoAdapter($mimetypes);
		$mdi_resource = $mdi_adapter->getFullMerchantDeliveryInfoAsResource($merchant_id);
		$this->assertNotNull($mdi_resource);
		$mdi_resource->minimum_delivery_time = 50;
		$mdi_resource->allow_asap_on_delivery = 'Y';
		$mdi_resource->save();

		$mpi_data['merchant_id'] = $merchant_id;
		$mpi_data['entree_preptime_seconds'] = 120;
		$mpi_data['concurrently_able_to_make'] = 10;

		$mpi_resource  = Resource::factory(new MerchantPreptimeInfoAdapter($mimetypes), $mpi_data);
		$mpi_resource->save();

		$json = '{"user_id":"'.$this->user['user_id'].'","name":"","address1":"1405 Arapohoe Ave","address2":"","city":"Boulder","state":"CO","zip":"80302","phone_no":"1234567890","lat":40.014594,"lng":-105.275990}';
		$request = new Request();
		$request->body = $json;
		$request->mimetype = "Application/json";
		$request->_parseRequestBody();
		$user_controller = new UserController($mt, $this->user, $request,5);
		$response = $user_controller->setDeliveryAddr();
		$user_address_id = $response->user_addr_id;

		$order_adapter = new OrderAdapter($mimetypes);
        $order_adapter->old_style = true;
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', 'no note');
		$order_data['delivery'] = 'Y';
		$order_data['delivery_amt'] = 0.00;
		$order_data['delivery_tax_amount'] = 0.00;
		$order_data['user_addr_id'] = $user_address_id;
		$json = json_encode($order_data);

		$tz = date_default_timezone_get();
		date_default_timezone_set("America/Denver");
		$ts = mktime(12, 30, 0, 2  , 12, 2013);
		date_default_timezone_set($tz);

		$request = new Request();
		$request->body = $json;
		$request->mimetype = 'Application/json';
		$user = $this->user;
		$place_order_controller = new PlaceOrderController($mimetypes,$user,$request,5);
		$place_order_controller->setForcedTs($ts);
		$resource = $place_order_controller->getCheckoutDataFromOrderRquest();
		$lead_times = $resource->lead_times_array;
		$checkout_data = $resource->getDataFieldsReally();

		$this->assertNotNull($lead_times,"lead times array should not be null. error: ".$checkout_data['ERROR']);
		$this->assertEquals('As soon as possible', $lead_times[0],  "the asap value is first time of lead times");
		$this->assertEquals('As soon as possible',$resource->lead_times_by_day_array['Today'][0],"It should have the asap value as its first time of lead times by day");
	}

	function testGetLeadTimesArrayWithAllowAsapForMobile()
	{
		setProperty("global_throttling", "true");
		$_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'] = 'IPHONE';

		$merchant_id = $this->merchant_id;
		$merchant_resource = SplickitController::getResourceFromId($merchant_id, "Merchant");
		$merchant_resource->delivery = 'Y';
		$merchant_resource->save();

		$mdi_adapter = new MerchantDeliveryInfoAdapter($mimetypes);
		$mdi_resource = $mdi_adapter->getFullMerchantDeliveryInfoAsResource($merchant_id);
		$this->assertNotNull($mdi_resource);
		$mdi_resource->minimum_delivery_time = 50;
		$mdi_resource->allow_asap_on_delivery = 'Y';
		$mdi_resource->save();

		$mpi_data['merchant_id'] = $merchant_id;
		$mpi_data['entree_preptime_seconds'] = 120;
		$mpi_data['concurrently_able_to_make'] = 10;

		$mpi_resource  = Resource::factory(new MerchantPreptimeInfoAdapter($mimetypes), $mpi_data);
		$mpi_resource->save();

		$json = '{"user_id":"'.$this->user['user_id'].'","name":"","address1":"1405 Arapohoe Ave","address2":"","city":"Boulder","state":"CO","zip":"80302","phone_no":"1234567890","lat":40.014594,"lng":-105.275990}';
		$request = new Request();
		$request->body = $json;
		$request->mimetype = "Application/json";
		$request->_parseRequestBody();
		$user_controller = new UserController($mt, $this->user, $request,5);
		$response = $user_controller->setDeliveryAddr();
		$user_address_id = $response->user_addr_id;

		$order_adapter = new OrderAdapter($mimetypes);
        $order_adapter->old_style = true;
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', 'no note');
		$order_data['delivery'] = 'Y';
		$order_data['delivery_amt'] = 0.00;
		$order_data['delivery_tax_amount'] = 0.00;
		$order_data['user_addr_id'] = $user_address_id;
		$json = json_encode($order_data);

		$tz = date_default_timezone_get();
		date_default_timezone_set("America/Denver");
		$ts = mktime(12, 30, 0, 2  , 12, 2013);
		date_default_timezone_set($tz);

		$request = new Request();
		$request->body = $json;
		$request->mimetype = 'Application/json';
		$user = $this->user;
		$place_order_controller = new PlaceOrderController($mimetypes,$user,$request,5);
		$place_order_controller->setForcedTs($ts);
		$resource = $place_order_controller->getCheckoutDataFromOrderRquest();
		$lead_times = $resource->lead_times_array;
		$checkout_data = $resource->getDataFieldsReally();

		$this->assertNotNull($lead_times,"lead times array should not be null. error: ".$checkout_data['ERROR']);
		$this->assertEquals("As soon as possible", $lead_times[0],  "the asap value is the first time of lead times");
	}

 	/**
 	 * 
 	 * @depends testGetLeadTimesArray
 	 */
  function testGetLeadTimeArrayLargeOrder($merchant_id)
  {
      $user_resource = createNewUserWithCCNoCVV();
      $user = logTestUserResourceIn($user_resource);
      $order_adapter = new OrderAdapter($mimetypes);
      $order_data = $order_adapter->getSimpleCartArrayByMerchantId($this->merchant_id, 'pickup', 'the note',7);
      $cart_resource = $this->createCartFromOrderData($order_data,$user);

    setProperty("global_throttling", "false");
    $lead_time = new LeadTime($merchant_id);
    $lead_time->setPickupMaxLead(240);
      $lead_times_array = $lead_time->getLeadTimesArrayFromOrderObjectWithThrottling(new Order($cart_resource->ucid),$this->noonish_feb_12_denver);
    $this->assertEquals(41,sizeof($lead_times_array));
    $this->assertEquals('Tuesday February 12, 2013, 12:59 pm',date("l F j, Y, g:i a",$lead_times_array[0]));
    $last_time = array_pop($lead_times_array);
    $this->assertEquals('Tuesday February 12, 2013, 4:49 pm',date("l F j, Y, g:i a",$last_time));

  }
    
	function testGetCheckoutDataForDeliveryWith7DayMax()
  {
    $merchant_resource = createNewTestMerchantDelivery($this->menu_id);
    $merchant_id = $merchant_resource->merchant_id;
    $mdi_adapter = new MerchantDeliveryInfoAdapter($mimetypes);
    $mdi_resource = $mdi_adapter->getFullMerchantDeliveryInfoAsResource($merchant_id);
    $this->assertNotNull($mdi_resource);
    $mdi_resource->minimum_delivery_time = 75;
    $mdi_resource->max_days_out = 7;
    $mdi_resource->save();

    //9750	20000		1405 Arapahoe Ave		boulder	Co	80302	40.014594	-105.275990	3038844083		2012-04-23 16:59:25	2012-04-23 16:59:24	N
    $user_resource = createNewUser(array("flags"=>'1C20000001'));
    $user_id = $user_resource->user_id;
    $user = logTestUserIn($user_id);
    $json = '{"user_id":"'.$user_id.'","name":"","address1":"1405 Arapohoe Ave","address2":"","city":"Boulder","state":"CO","zip":"80302","phone_no":"1234567890","lat":40.014594,"lng":-105.275990}';
    $request = new Request();
    $request->body = $json;
    $request->mimetype = "Application/json";
    $request->_parseRequestBody();
    $user_controller = new UserController($mt, $user, $request,5);
    $response = $user_controller->setDeliveryAddr();
    $this->assertNull($response->error,"should not have gotten a delivery save error but did");
    $user_address_id = $response->user_addr_id;

    $order_adapter = new OrderAdapter($mimetypes);
    $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', 'no note');
		$order_data['delivery'] = 'Y';
		$order_data['delivery_amt'] = 0.00;
		$order_data['delivery_tax_amount'] = 0.00;
		$order_data['user_addr_id'] = $user_address_id;

    $tz = date_default_timezone_get();
    date_default_timezone_set("America/Denver");
    $ts = mktime(12, 30, 0, 2  , 12, 2013);
    date_default_timezone_set($tz);

    $checkout_data_resource = getCheckoutDataWithThrottling($order_data, $merchant_id, $ts);

    $this->assertEquals("Please note, your first available delivery time for this order is 75 minutes from now.", $checkout_data_resource->user_message);
		$lead_times = $checkout_data_resource->lead_times_array;
		$checkout_data = $checkout_data_resource->getDataFieldsReally();
		
		$this->assertNotNull($lead_times,"lead times array should not be null. error: ".$checkout_data['ERROR']);
		$size = count($lead_times);
		$new_first_time = $lead_times[0];
		$last_time = $lead_times[$size-1];
		$last_date = date('Y-m-d',$last_time);
		$this->assertEquals('2013-02-18', $last_date);
    	
  }

  /****  functions ******/
    
  function getCheckoutDataLeadTimesArrayOnly($json_encoded_data,$merchant_id,$ts)
  {
    $checkout_data_resource = $this->getCheckoutDataWithThrottling($json_encoded_data,$merchant_id,$ts);
    if ($checkout_data_resource->error)
      return array($checkout_data_resource->error);

    $lead_times_array = $checkout_data_resource->lead_times_array;
    return $lead_times_array;
  }
    
  function getCheckoutDataWithThrottling($json_encoded_data,$merchant_id,$ts)
  {
    $request = new Request();
    $request->url = '/app2/phone/getcheckoutdata/';
    $request->method = "post";
    $request->body = $json_encoded_data;
    $request->mimetype = 'Applicationjson';
    $place_order_controller = new PlaceOrderController($mt, $this->user, $request,5);
    if ($ts)
      $place_order_controller->setForcedTs($ts);
    $checkout_data_resource = $place_order_controller->getCheckoutDataFromOrderRquest();
    return $checkout_data_resource;
  /*
        if ($checkout_data_resource->error)
          return array($checkout_data_resource->error);
        $lead_times_array = $checkout_data_resource->lead_times;
          return $lead_times_array;
  /*
        $request->_parseRequestBody();
        $lead_time_object = new LeadTime($merchant_id);
      $lead_times_array = $lead_time_object->getLeadTimesArrayFromOrderWithThrottling($request,$ts);
      return $lead_times_array;
  */
  }

  function createCartFromOrderData($order_data,$user)
  {
      $json_encoded_data = json_encode($order_data);

      $url = '/app2/apiv2/cart';
      $request = createRequestObject($url,'POST',$json_encoded_data,'application/json');
      $place_order_controller = new PlaceOrderController($mt, $user, $request);
      $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
      $cart_resource = $place_order_controller->processV2Request();
      return $cart_resource;
  }
    
  static function setUpBeforeClass()
  {
      setProperty('do_not_call_out_to_aws','true');
    ini_set('max_execution_time',300);
    SplickitCache::flushAll();
    $db = DataBase::getInstance();
    $mysqli = $db->getConnection();
    $mysqli->begin_transaction();
      setContext("com.splickit.order");
      $brand_id = getBrandIdFromCurrentContext();
      $brand_resource = Resource::find(new BrandAdapter(getM()),$brand_id);
      $brand_resource->loyalty = 'Y';
      $brand_resource->save();

      $_SERVER['request_time1'] = microtime(true);
		$menu_id = createTestMenuWithOneItem("Test Item 1");
    $ids['menu_id'] = $menu_id;

    $merchant_resource = createNewTestMerchant($menu_id);
    $merchant_id = $merchant_resource->merchant_id;
    $ids['merchant_id'] = $merchant_id;
      $prep_resource = Resource::createByData(new MerchantPreptimeInfoAdapter(getM()), array("merchant_id"=>$merchant_resource->merchant_id,"entree_preptime_seconds"=>120));

    $user_resource = createNewUser(array('flags'=>'1C20000001'));
    $ids['user_id'] = $user_resource->user_id;

    $_SERVER['log_level'] = 5;
		
		$tz = date_default_timezone_get();
    date_default_timezone_set("America/Denver");
    //tuesday the 12th
    $noon_ts = mktime(12, 30, 0, 2  , 12, 2013);
    date_default_timezone_set($tz);
    $ids['noonish_denver_ts'] = $noon_ts;
		
		$_SERVER['unit_test_ids'] = $ids;

  }
    
	static function tearDownAfterClass()
  {
    SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
  }
    
  /* mail method for testing */
  static function main() {
    $suite = new PHPUnit_Framework_TestSuite( __CLASS__);
    PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
  LeadTimeTest::main();
}

?>