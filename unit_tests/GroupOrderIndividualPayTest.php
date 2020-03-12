<?php
ini_set('max_execution_time', 300);
$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class GroupOrderIndividualPayTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $user;
    var $merchant_id;
    var $merchant;
    var $users;
    var $ids;

    function setUp()
    {
        // we dont want to call to inspirepay
        setContext("com.splickit.gotwo");
        $_SERVER['SKIN']['show_notes_fields'] = true;
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'] = '100.0.0';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__ . '-' . $_SERVER['STAMP'];
        $this->users = $_SERVER['users'];
        $this->ids = $_SERVER['unit_test_ids'];
        $user_id = $_SERVER['unit_test_ids']['user_id'];
        $user_resource = SplickitController::getResourceFromId($user_id, 'User');
        $this->user = $user_resource->getDataFieldsReally();

        $this->merchant_id = $_SERVER['unit_test_ids']['merchant_id'];
        $this->merchant = SplickitController::getResourceFromId($this->merchant_id, 'Merchant');
        setProperty('do_not_call_out_to_aws', 'true');
    }

    function tearDown()
    {
        $_SERVER['STAMP'] = $this->stamp;
        setProperty('do_not_call_out_to_aws', 'true');
    }

    function testDoNotLetGuestCreateGroupOrder()
    {
        setContext("com.splickit.gotwo");
        $user_resource = createGuestUser();
        $user = logTestUserResourceIn($user_resource);
        $note = "sum dum note";
        $emails = "sumdumemail1@dummy.com,sumdumemail2@dummy.com,sumdumemail3@dummy.com,sumdumemail4@dummy.com";

        $request = new Request();
        $autosend_ts = getTomorrowTwelveNoonTimeStampDenver() + 1800;
        $request->data = array("merchant_id" => $this->merchant_id, "note" => $note, "merchant_menu_type" => 'Pickup', "participant_emails" => $emails, "group_order_type" => 2, "submit_at_ts" => $autosend_ts);
        $request->url = "app2/apiv2/grouporders";
        $request->method = "POST";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $group_order_controller->current_time = getTomorrowTwelveNoonTimeStampDenver();
        $resource = $group_order_controller->processV2Request();
        $this->assertNotNull($resource->error,"Should have thrown an error since the user was a guest.");
        $this->assertEquals(GroupOrderController::GUEST_CANNOT_BE_ADMIN_FOR_GROUP_ORDER_ERROR,$resource->error);
        $this->assertEquals(403,$resource->http_code);

    }

    function testIncrementAutoSendTimeOfGroupOrder()
    {
        setContext("com.splickit.gotwo");
        $note = "sum dum note";
        $emails = "sumdumemail1@dummy.com,sumdumemail2@dummy.com,sumdumemail3@dummy.com,sumdumemail4@dummy.com";
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $request = new Request();
        $autosend_ts = getTomorrowTwelveNoonTimeStampDenver() + 1800;
        $request->data = array("merchant_id" => $this->merchant_id, "note" => $note, "merchant_menu_type" => 'Pickup', "participant_emails" => $emails, "group_order_type" => 2, "submit_at_ts" => $autosend_ts);
        $request->url = "app2/apiv2/grouporders";
        $request->method = "POST";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $group_order_controller->current_time = getTomorrowTwelveNoonTimeStampDenver();
        $resource = $group_order_controller->processV2Request();
        $this->assertNull($resource->error,"Should not have thrown an error.");
        $this->assertContains("12:30 pm",$resource->send_on_local_time_string);
        myerror_log("the time: ".$resource->pickup_dt_tm);
        $this->assertContains("13:15:00",$resource->pickup_dt_tm);
        $activity_id = $resource->auto_send_activity_id;

        $activity_record = getStaticRecord(array("activity_id"=>$activity_id),'ActivityHistoryAdapter');
        $this->assertEquals($autosend_ts,$activity_record['doit_dt_tm']);
        $group_order_token = $resource->group_order_token;

        //submit a bad increment request
        $request = createRequestObject("app2/apiv2/grouporders/$group_order_token/increment/700",'POST',$data,$m);
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $resource1b = $group_order_controller->processV2Request();
        $this->assertNotNull($resource1b->error);
        $this->assertEquals("Sorry. This merchant is closed at your requested order time.",$resource1b->error);

        //now submit a good increment request
        $request = createRequestObject("app2/apiv2/grouporders/$group_order_token/increment/10",'POST',$data,$m);
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $resource2 = $group_order_controller->processV2Request();
        $this->assertNull($resource2->error);

        $activity_record = getStaticRecord(array("activity_id"=>$activity_id),'ActivityHistoryAdapter');
        $this->assertEquals($autosend_ts+600,$activity_record['doit_dt_tm'],"It shoudl have the new time, which is the old time incremented by 10 minutes");

        $this->assertContains("12:40 pm",$resource2->send_on_local_time_string);
        $parent_order_resource = SplickitController::getResourceFromId($resource2->order_id,"Order");
        $this->assertContains("13:25:00",$parent_order_resource->pickup_dt_tm);

    }

    function testDoNotSendAnEmptyGroupOrder()
    {
        setContext("com.splickit.gotwo");
        $note = "sum dum note";
        $emails = "sumdumemail1@dummy.com,sumdumemail2@dummy.com,sumdumemail3@dummy.com,sumdumemail4@dummy.com";
        $user = logTestUserIn($this->users[2]);

        $request = new Request();
        $request->data = array("merchant_id" => $this->merchant_id, "note" => $note, "merchant_menu_type" => 'Pickup', "participant_emails" => $emails, "group_order_type" => 2, "submit_at_ts" => (getTomorrowTwelveNoonTimeStampDenver() + 1500));
        $request->url = "app2/apiv2/grouporders";
        $request->method = "POST";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $group_order_controller->current_time = getTomorrowTwelveNoonTimeStampDenver();
        $resource = $group_order_controller->processV2Request();
        $this->assertNull($resource->error,"Should not have thrown an error.");
        $activity_id = $resource->auto_send_activity_id;

        $send_group_order_activity = SplickitActivity::findActivityResourceAndReturnActivityObjectByActivityId($activity_id);
        $this->assertNotNull($send_group_order_activity);
        $class_name = get_class($send_group_order_activity);
        $this->assertEquals("SendGroupOrderActivity", $class_name);
        $send_group_order_activity->executeThisActivity();
        $activity_history_resource_adapter = new ActivityHistoryAdapter($mimetypes);
        $activity_history_resource = Resource::find($activity_history_resource_adapter,"$activity_id");
        $this->assertEquals('F',$activity_history_resource->locked,"Activity record should show as F");
        //$this->assertFalse($send_group_order_activity->doit(),'It should fail the activity cause no orders were added');
        return $resource->group_order_token;
    }

    /**
     * @depends testDoNotSendAnEmptyGroupOrder
     */
    function testAutosendOfEmptyGroupOrderShouldCancelTheGroupOrderAndSendEmail($group_order_token)
    {
        $group_order_record = GroupOrderAdapter::staticGetRecord(array("group_order_token"=>$group_order_token),'GroupOrderAdapter');
        $this->assertEquals('cancelled',$group_order_record['status'],'It Should have cancelled the group order');

        $order_record = getStaticRecord(array("order_id"=>$group_order_record['order_id']),'OrderAdapter');
        $this->assertEquals('C',$order_record['status'],'It should have cancelled the parent order');
    }

    function testCancelGroupOrder()
    {
        setContext("com.splickit.gotwo");
        $note = "sum dum note";
        $emails = "sumdumemail1@dummy.com,sumdumemail2@dummy.com,sumdumemail3@dummy.com,sumdumemail4@dummy.com";
        $user = logTestUserIn($this->users[2]);

        $request = new Request();
        $request->data = array("merchant_id" => $this->merchant_id, "note" => $note, "merchant_menu_type" => 'Pickup', "participant_emails" => $emails, "group_order_type" => 2, "submit_at_ts" => (getTomorrowTwelveNoonTimeStampDenver() + 1500));
        $request->url = "app2/apiv2/grouporders";
        $request->method = "POST";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $group_order_controller->current_time = getTomorrowTwelveNoonTimeStampDenver();
        $resource = $group_order_controller->processV2Request();
        $this->assertNull($resource->error,"Should not have thrown an error.");
        $group_order_token = $resource->group_order_token;
        $request->data = $order_data;
        $request->url = "app2/apiv2/grouporders/$group_order_token";
        $request->method = "DELETE";
        $group_order_controller = new GroupOrderController($mt, getLoggedInUser(), $request, 5);
        $go_resource = $group_order_controller->processV2request();
        $this->assertNull($go_resource->error,"should not have gotten an error");
        $group_order_record = GroupOrderAdapter::staticGetRecord(array("group_order_token"=>$group_order_token),'GroupOrderAdapter');
        $this->assertEquals('cancelled',$group_order_record['status'],"Group order should show as cancelled");
        $cart_record = CartsAdapter::staticGetRecordByPrimaryKey($group_order_token,'Carts');
        $this->assertEquals('C',$cart_record['status'],"Cart should show as cancelled");
        return $resource;
    }

    /**
     * @depends testCancelGroupOrder
     */
    function testActivityShouldBeCancelledToo($resource)
    {
        $activity_id = $resource->auto_send_activity_id;
        $activity = ActivityHistoryAdapter::staticGetRecordByPrimaryKey($activity_id,'ActivityHistoryAdapter');
        $this->assertNotNull($activity);
        $this->assertEquals('N',$activity['locked']);
    }

    /**
     * @depends testCancelGroupOrder
     */
    function testCorrectMessageWhenTryingToAccessACancelledGroupOrder($resource)
    {
        $user = logTestUserIn($resource->user_id);
        $group_order_token = $resource->group_order_token;
        $request = createRequestObject("app2/apiv2/grouporders/$group_order_token",'GET');
        $go_controller = new GroupOrderController($m,$user,$request);
        $response = $go_controller->processV2request();
        $this->assertEquals("Sorry! This group order has been cancelled by the admin.",$response->error);
    }

    function testGetGroupOrderAvailablePickupTimesDelivery()
    {
        $current_time = getTomorrowTwelveNoonTimeStampDenver()+(4*3600);
        //$current_time = time();
        $request = new Request();
        $request->url = "app2/apiv2/merchants/".$this->merchant_id."/grouporderavailabletimes/delivery";
        $request->method = "GET";
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $merchant_controller->setTheTime($current_time);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $submit_times_array  = $resource->submit_times_array;
        $first_time = date('Y-m-d H:i:s',$submit_times_array[0]);
        $this->assertEquals(date('Y-m-d',$current_time).' 16:15:00',$first_time,"first time shoudl have been 15 minutes from now");
        $last_time = date('Y-m-d H:i:s',array_pop($submit_times_array));
        $this->assertEquals(date('Y-m-d',$current_time+(24*3600)).' 18:45:00',$last_time,"last time should have been 45 minutes before 30 minutes before closing (aka 1 hour 15 minutes before closing");
    }

    function testGetGroupOrderAvailablePickupTimes()
    {
        $current_time = getTomorrowTwelveNoonTimeStampDenver()+(4*3600);
        $request = new Request();
        $request->url = "app2/apiv2/merchants/".$this->merchant_id."/grouporderavailabletimes/pickup";
        $request->method = "GET";
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $merchant_controller->setTheTime($current_time);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);
        $submit_times_array  = $resource->submit_times_array;
        $first_time = date('Y-m-d H:i:s',$submit_times_array[0]);
        $this->assertEquals(date('Y-m-d',$current_time).' 16:15:00',$first_time,"first time shoudl have been 15 minutes from now");
        $last_time = date('Y-m-d H:i:s',array_pop($submit_times_array));
        $this->assertEquals(date('Y-m-d',$current_time+(24*3600)).' 18:45:00',$last_time,"last time should have been 45 minutes before 30 minutes before closing (aka 1 hour 15 minutes before closing");
    }

    function testThrowErrorIfMismatchWithUserDeliveryLocationAndMerchantMenuType()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user_resource->first_name = 'Tom';
        $user_resource->last_name = 'Jones';
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);

        $request = new Request();
        $request->data = array("merchant_id" => $this->merchant_id, "merchant_menu_type"=>"delivery","note" => $note, "participant_emails" => $emails, "group_order_type" => 2, "submit_at_ts" => (getTomorrowTwelveNoonTimeStampDenver() + 900));
        $request->url = "app2/apiv2/grouporders";
        $request->method = "POST";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $resource = $group_order_controller->processV2Request();
        $this->assertNotNull($resource->error,"It should have returned an error due to the mismatch");
        $this->assertEquals(GroupOrderController::NO_ADDRESS_SUBMITTED_FOR_DELIVERY_GROUP_ORDER_MESSAGE,$resource->error);
        $this->assertEquals(500,$resource->http_code);
    }



    function testCreateGroupOrderWithDeliveryLocation()
    {
        // set delivery minimum to something high
        $merchant_id = $this->merchant_id;
        $merchant_delivery_info = MerchantDeliveryInfoAdapter::getFullMerchantDeliveryInfoAsResource($merchant_id);
        $merchant_delivery_info->minimum_order = '20.00';
        $merchant_delivery_info->save();

        //create the type 1 promo
        $promo_adapter = new PromoAdapter($m);
        $ids['promo_id_type_1'] = 201;
        $sql = "INSERT INTO `Promo` VALUES(201, 'The Type1 Promo', 'Get 25% off', 1, 'Y', 'N', 0, 2, 'N', 'N','all', '2010-01-01', '2020-01-01', 1, 0, 0, 0.00, 0, 0.00, 'Y', 'N',0, 300, NOW(), NOW(), 'N')";
        $promo_adapter->_query($sql);
        $sql = "INSERT INTO `Promo_Merchant_Map` VALUES(null, 201, $merchant_id, '2013-10-05', '2020-01-01', NULL, now())";
        $pmm_resource = Resource::createByData(new PromoMerchantMapAdapter($mimetypes), array("merchant_id"=>$merchant_id,"promo_id"=>201));
        $ids['promo_merchant_map_id_type_1'] = $pmm_resource->map_id;
        $sql = "INSERT INTO `Promo_Message_Map` VALUES(null, 201, 'Congratulations! You''re getting a 25% off your order!', NULL, NULL, NULL, NULL, now())";
        $promo_adapter->_query($sql);
        $sql = "INSERT INTO `Promo_Type1_Amt_Map` VALUES(null, 201, 1.00, 0.00, 25,50.00, NOW())";
        $promo_adapter->_query($sql);

        $pkwm_adapter = new PromoKeyWordMapAdapter($m);
        Resource::createByData($pkwm_adapter, array("promo_id"=>201,"promo_key_word"=>"type1promo","brand_id"=>300));



        $user_resource = createNewUserWithCC();
        $user_resource->first_name = 'Tom';
        $user_resource->last_name = 'Zombie';
        $user_resource->flags = '1C20000001';
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

        $request = new Request();
        $request->data = array("merchant_id" => $this->merchant_id, "note" => $note, "participant_emails" => $emails, "user_addr_id" => $user_address_id, "group_order_type" => 2, "submit_at_ts" => (getTomorrowTwelveNoonTimeStampDenver() + 900));
        $request->url = "app2/apiv2/grouporders";
        $request->method = "POST";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $resource = $group_order_controller->processV2Request();
        $group_order_token = $resource->group_order_token;
        $cart = CartsAdapter::staticGetRecordByPrimaryKey($group_order_token, "CartsAdapter");
        $order_id = $cart['order_id'];
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id, $m);
        $this->assertEquals('D', $complete_order['order_type'], "shoudl have created a delivery dummy order row");
        $this->assertEquals($user_address_id, $complete_order['user_delivery_location_id'], "Should have the user addr id on the order");
        $this->assertEquals(5.55, $complete_order['delivery_amt'], "should NOT have a delivery price on the dummy order");
        $this->assertEquals(getSkinIdForContext(),$complete_order['skin_id'],"skin id shoudl equal the context");
        //$this->assertEquals(0.00, $complete_order['delivery_amt'], "should NOT have a delivery price on the dummy order");
        $group_order_adapter = new GroupOrderAdapter($mimetypes);
        $group_order_record = $group_order_adapter->getRecordFromPrimaryKey($resource->group_order_id);
        $this->assertEquals($user_address_id,$group_order_record['user_addr_id'],'User address id should be on the group order record');
        $group_order_record['user_address_id'] = $user_address_id;
        //$group_order_record['delivery_cost'] = $complete_order['delivery_amt'];
        return $group_order_record;
    }

    /**
     * @depends testCreateGroupOrderWithDeliveryLocation
     */
    function testAddToDeliveryGroupOrderAsAdminSoShouldGetChargedDeliveryFee($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $group_order_id = $group_order_record['group_order_id'];

        $user = logTestUserIn($group_order_record['admin_user_id']);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'Delivery',"sum dum cart note");
        $order_data['group_order_token'] = $group_order_token;

        $cart_resource = $this->addtoCart($user,$order_data);
        $this->assertNotNull($cart_resource, "should have gotten a cart resource back");
        $this->assertNull($cart_resource->error);
        $this->assertEquals("Y",$cart_resource->status,"It shoudl have a status of 'Y'");


        // validate that the order record was added to mapping table
        $cart_order_resource = Resource::find(new CartsAdapter($m),$cart_resource->ucid);
        $gorma = new GroupOrderIndividualOrderMapsAdapter($m);
        $record = $gorma->getRecord(array("user_order_id"=>$cart_order_resource->order_id));
        $this->assertEquals($group_order_id,$record['group_order_id'],"It should have added the order to the mapping table.");

        // validate that the delivery charge was added to the order record since this is the admin
        $this->assertEquals($group_order_record['user_address_id'],$cart_order_resource->user_delivery_location_id,"It should have the user address id of the parent order");
        $this->assertEquals(5.55,$cart_order_resource->delivery_amt,"It should have the delivery charge on the order record");

        // get checkout data to make sure there is only 1 time choice
        //$checkout_data_resource = $this->getCheckoutForGroupOrder($user,$cart_resource->ucid,$group_order_token,getTomorrowTwelveNoonTimeStampDenver()-1800);
        $checkout_data_resource = $this->getCheckoutForGroupOrderWithPromoCode($user,$cart_resource->ucid,$group_order_token,getTomorrowTwelveNoonTimeStampDenver()-1800,'type1promo');
        $this->assertNull($checkout_data_resource->error);
        $this->assertCount(1,$checkout_data_resource->lead_times_array,"It should only have 1 available lead time");
        //$this->assertfalse($checkout_data_resource->show_lead_times,"It should have the flag set to to not show lead time");
        $this->assertEquals(date('Y-m-d H:i:s',getTomorrowTwelveNoonTimeStampDenver() + 3600),date('Y-m-d H:i:s',$checkout_data_resource->lead_times_array[0]));
        $this->assertTrue(count($checkout_data_resource->accepted_payment_types) == 1,"We should have some accepted payment types");
        $payment_array = createHashmapFromArrayOfArraysByFieldName($checkout_data_resource->accepted_payment_types,'name');
        $this->assertNull($payment_array['Cash'],"There should not be a cash option for the payment array");


        // now place the order
        $order_resource = $this->placeOrderToBePartOfTypeTwoGroupOrder($user,$this->ids['merchant_id'],$cart_resource->ucid,$group_order_token,$checkout_data_resource,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertEquals($cart_order_resource->order_id, $order_id, "should have created a valid order id on teh original cart record");

        $order_messages = MerchantMessageHistoryAdapter::getAllOrderMessages($order_id);
        $this->assertCount(1, $order_messages);
        $order_message = $order_messages[0];
        $this->assertEquals('Econf', $order_message->message_format);
        $this->assertEquals('G', $order_resource->status);

        $gorma = new GroupOrderIndividualOrderMapsAdapter($m);
        $record = $gorma->getRecord(array("user_order_id"=>$order_id));
        $this->assertEquals($group_order_id,$record['group_order_id']);

        //test to see if values get added to the parent order record
        $parent_order_record = OrderAdapter::staticGetRecord(array("ucid"=>$group_order_token),'OrderAdapter');
        $this->assertEquals($order_resource->order_amt,$parent_order_record['order_amt']);
        $this->assertEquals($order_resource->total_tax_amt, $parent_order_record['total_tax_amt']);
        $this->assertEquals($order_resource->order_quantity, $parent_order_record['order_quantity']);
        $this->assertEquals($order_resource->tip_amt, $parent_order_record['tip_amt']);
        $this->assertEquals($order_resource->grand_total, $parent_order_record['grand_total']);
        $this->assertEquals($order_resource->delivery_amt, $parent_order_record['delivery_amt']);
        $this->assertEquals($order_resource->promo_amt, $parent_order_record['promo_amt']);

        $complete_group_order = CompleteOrder::staticGetCompleteOrder($parent_order_record['order_id'],$m);
        $group_order_details = $complete_group_order['order_details'];


        $complete_individual_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
        $individual_order_details = $complete_individual_order['order_details'];

        $this->assertEquals($individual_order_details[0]['order_detail_complete_modifier_list_no_holds'],$group_order_details[0]['order_detail_complete_modifier_list_no_holds'],"since its the first order the mods shoudl be the same");
    }

    /**
     * @depends testCreateGroupOrderWithDeliveryLocation
     */
    function testAddToDeliveryGroupOrderAsParticipantSoShouldNOTGetChargedDeliveryFee($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $group_order_id = $group_order_record['group_order_id'];

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $ubpm_resource = Resource::find(new UserBrandPointsMapAdapter($m),null,array(3=>array("user_id"=>$user_resource->user_id)));
        $ubpm_resource->dollar_balance = 1.50;
        $ubpm_resource->points = 150;
        $ubpm_resource->save();


        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'Delivery',"sum dum participant cart note");
        $order_data['group_order_token'] = $group_order_token;
        $order_data["submitted_order_type"]= 'Delivery';
        $order_data['user_addr_id'] = null;

        $cart_resource = $this->addtoCart($user,$order_data);
        $this->assertNotNull($cart_resource, "should have gotten a cart resource back");
        $this->assertNull($cart_resource->error);
        $this->assertEquals("Y",$cart_resource->status,"It shoudl have a status of 'Y'");

        // validate that the order record was added to mapping table
        $cart_order_resource = Resource::find(new CartsAdapter($m),$cart_resource->ucid);
        $gorma = new GroupOrderIndividualOrderMapsAdapter($m);
        $record = $gorma->getRecord(array("user_order_id"=>$cart_order_resource->order_id));
        $this->assertEquals($group_order_id,$record['group_order_id'],"It should have added the order to the mapping table.");

        // validate that the delivery charge was NOT added to the order record since this is jsut a participant
        $this->assertEquals($group_order_record['user_address_id'],$cart_order_resource->user_delivery_location_id,"It should have the user address id of the parent order");
        $this->assertEquals(0.00,$cart_order_resource->delivery_amt,"It should NOT have the delivery charge on the order record since this is a participant and not the admin");

        // get checkout data to make sure there is only 1 time choice
        $checkout_data_resource = $this->getCheckoutForGroupOrder($user,$cart_resource->ucid,$group_order_token,getTomorrowTwelveNoonTimeStampDenver()-1800);
        $this->assertNull($checkout_data_resource->error);
        $this->assertCount(1,$checkout_data_resource->lead_times_array,"It should only have 1 available lead time");
        $this->assertEquals(0.00,$checkout_data_resource->delivery_amt,"There should not be a delivery fee");
        //$this->assertfalse($checkout_data_resource->show_lead_times,"It should have the flag set to to not show lead time");
        $this->assertEquals(date('Y-m-d H:i:s',getTomorrowTwelveNoonTimeStampDenver() + 3600),date('Y-m-d H:i:s',$checkout_data_resource->lead_times_array[0]));

        $payment_array_by_id = createHashmapFromArrayOfArraysByFieldName($checkout_data_resource->accepted_payment_types,'splickit_accepted_payment_type_id');
        $this->assertNotNull($payment_array_by_id['8000'],"It should have a cash plus loyalty option");
        $this->assertNull($payment_array_by_id['1000'],"It should not have a cash option");
        $this->assertNull($payment_array_by_id['9000'],"It Should not have a cash + loyalty option");
        $this->assertTrue(count($checkout_data_resource->accepted_payment_types) == 2,"We should have both Credit Card and Credit Card plus loyalty accepted payment types");

        // now place the order
        $order_resource = $this->placeOrderToBePartOfTypeTwoGroupOrder($user,$this->ids['merchant_id'],$cart_resource->ucid,$group_order_token,$checkout_data_resource,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertEquals($cart_order_resource->order_id, $order_id, "should have created a valid order id on teh original cart record");
        $this->assertEquals("D",$order_resource->order_type,"It should be listed as a delivery order");
        $this->assertEquals(0.00,$order_resource->delivery_amt,"There should not be a delivery fee");
        $this->assertEquals($group_order_record['user_addr_id'],$order_resource->user_delivery_location_id,"It should have the UDL of the parent order");

        $complete_order = CompleteOrder::getBaseOrderData($order_id,getM());

        $order_messages = MerchantMessageHistoryAdapter::getAllOrderMessages($order_id);
        $this->assertCount(1, $order_messages);
        $order_message = $order_messages[0];
        $this->assertEquals('Econf', $order_message->message_format);
        $this->assertEquals('G', $order_resource->status);

        $gorma = new GroupOrderIndividualOrderMapsAdapter($m);
        $record = $gorma->getRecord(array("user_order_id"=>$order_id));
        $this->assertEquals($group_order_id,$record['group_order_id']);
        return $group_order_record;
    }

    /**
     * @depends testAddToDeliveryGroupOrderAsParticipantSoShouldNOTGetChargedDeliveryFee
     */
    function testSubmitDeliveryGroupOrder($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $group_order_id = $group_order_record['group_order_id'];
        $user = logTestUserIn($group_order_record['admin_user_id']);

        $request = createRequestObject("/app2/apiv2/grouporders/$group_order_token/submit",'POST',$body,$m);
        $group_order_controller = new GroupOrderController($m,$user,$request,5);
        $response = $group_order_controller->processV2request();
        $this->assertNull($response->error);

        $group_order_token = $group_order_record['group_order_token'];
        $group_base_order = CompleteOrder::staticGetCompleteOrder($group_order_token);

        // check to make sure the messages were created
        $order_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($group_base_order['order_id'],'E');
        $this->assertEquals($group_base_order['order_id'],$order_message_resource->order_id,"the order id of the message should equal that of the parent group order");

        // all status should have been set to 'O'
        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_record['group_order_id']),'GroupOrderIndividualOrderMapsAdapter');
        $this->assertCount(2,$records,"There should be 2 orders");
        foreach($records as $record) {
            $base_order_info = CompleteOrder::getBaseOrderData($record['user_order_id']);
            $this->assertEquals('O',$base_order_info['status']);
        }

        $final_group_order_record = getStaticRecord(array("group_order_id"=>$group_order_record['group_order_id']),'GroupOrderAdapter');
        $this->assertEquals('Submitted',$final_group_order_record['status']);
        $e = explode(' ',$final_group_order_record['sent_ts']);
        $sent_date = $e[0];
        $this->assertEquals(date('Y-m-d'),$sent_date,"shoudl have today as sent");
        $mmha = new MerchantMessageHistoryAdapter();
        $message_resource = $mmha->getExactResourceFromData(array("order_id"=>$group_base_order['order_id'],"message_format"=>'Econf'));
        $message_text = $message_resource->message_text;
        $this->assertTrue(substr_count(strip_tags($message_text), 'Tom, your order will be') == 1,"should have name of group order admin");

        //check to make sure requested_delivery_time propogates to parent order
        $sql = "SELECT a.* from Orders a JOIN Group_Order_Individual_Order_Maps b ON a.order_id = b.user_order_id WHERE group_order_id = $group_order_id";
        $options[TONIC_FIND_BY_SQL] = $sql;
        $order_resources = Resource::findAll(new OrderAdapter(),null,$options);
        $requested_delivery_time = $order_resources[0]->requested_delivery_time;
        $this->assertNotNull($requested_delivery_time,"It should have a requested delivery time");
        $this->assertEquals($requested_delivery_time,$group_base_order['requested_delivery_time'],"It should have picked up the requested_delivery_time from the child order");

        return $group_order_record;
    }

    /**
     * @depends testSubmitDeliveryGroupOrder
     */
    function testSendGroupOrderExecutionMessage($group_order_record)
    {
        $order_id = $group_order_record['order_id'];
        $order_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'E');
        $message_controller = ControllerFactory::generateFromMessageResource($order_message_resource);
        $response = $message_controller->sendThisMessage($order_message_resource);

        // validate message
        $sent_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'E');
        $this->assertEquals('S',$sent_message_resource->locked,"It should have listed the messages as sent");
        $body = $sent_message_resource->message_text;
        myerror_log($body);
        $this->assertContains('1045 Pine Street',$body,"It should have the delivery addres on the message somewhere");

        // now check to see if the individual orders were set to 'E'
        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_record['group_order_id']),'GroupOrderIndividualOrderMapsAdapter');
        foreach ($records as $record) {
            $base_order_info = CompleteOrder::getBaseOrderData($record['user_order_id']);
            $this->assertEquals('E',$base_order_info['status']);
        }

        // check on parent order
        $order = getStaticRecord(array("order_id"=>$order_id),'OrderAdapter');
        $this->assertEquals('G',$order['status'],"The parent order should stay as 'G' so it doesn't get included in accounting");
        return $group_order_record['admin_user_id'];
    }

    function testCreateGroupOrderPickup()
    {
        setContext("com.splickit.gotwo");
        $note = "sum dum note";
        $emails = "sumdumemail1@dummy.com,sumdumemail2@dummy.com,sumdumemail3@dummy.com,sumdumemail4@dummy.com";
        $user = logTestUserIn($this->users[2]);

        $request = new Request();
        $request->data = array("merchant_id" => $this->merchant_id, "note" => $note, "participant_emails" => $emails, "group_order_type" => 2, "submit_at_ts" => (getTomorrowTwelveNoonTimeStampDenver() + (10*3600)));
        $request->url = "app2/apiv2/grouporders";
        $request->method = "POST";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $group_order_controller->current_time = getTomorrowTwelveNoonTimeStampDenver();
        $resource = $group_order_controller->processV2Request();
        $this->assertNotNull($resource->error,"Should have thrown an error because requested pickup time is after merchant is closed");
        $this->assertEquals("Sorry. This merchant is closed at your requested order time.",$resource->error);

        // now set the submit time to be 12:15 which will result in a pickup time of 1pm
        $request->data['submit_at_ts'] = getTomorrowTwelveNoonTimeStampDenver() + 900;
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $resource = $group_order_controller->processV2Request();
        $this->assertNull($resource->error);
        $this->assertNotNull($resource);

        $this->assertNotNull($resource->group_order_token);
        $this->assertTrue($resource->group_order_id > 1000, "shouljd have a group order id");
        $this->assertEquals($user['user_id'], $resource->admin_user_id);
        $this->assertTrue($resource->expires_at > (time() + (47 * 60 * 60)), "Should have an expiration timestamp that is greater then 47 hours from now");
        $this->assertTrue($resource->expires_at < (time() + (49 * 60 * 60)), "Should have an expiration timestamp that is less then 49 hours from now");
        $group_order_adapter = new GroupOrderAdapter($mimetypes);
        $group_order_record = $group_order_adapter->getRecordFromPrimaryKey($resource->group_order_id);
        $this->assertEquals($notes, $group_order_record['notes']);
        $this->assertEquals(2, $group_order_record['group_order_type']);
        $this->assertEquals('Pickup', $group_order_record['merchant_menu_type']);
        $this->assertEquals($emails, $group_order_record['participant_emails']);
        $base_order_data = OrderAdapter::staticGetRecordByPrimaryKey($resource->order_id, 'OrderAdapter');
        $this->assertEquals('R', $base_order_data['order_type']);
        $this->assertEquals('G', $base_order_data['status'],"Should have a status of G");
        $this->assertEquals(date('Y-m-d H:i:s',getTomorrowTwelveNoonTimeStampDenver()+3600),$base_order_data['pickup_dt_tm'],"pickup time should be set as 1pm");

        // now check the activity
        $activity_id = $resource->auto_send_activity_id;
        $this->assertTrue($activity_id>1000,"should have found a valid activity id");

        $activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
        $activity_resource = Resource::find($activity_history_adapter,"$activity_id");
        $this->assertNotNull($activity_resource);
        $group_order_record['activity_id'] = $activity_id;
        $group_order_record['order_id'] = $resource->order_id;

        return $group_order_record;
    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testGroupOrderTokenOnUserSession($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $user = logTestUserIn($this->users[2]);
        $user_resource = SplickitController::getResourceFromId($this->users[2], "User");
        $user_session_controller = new UsersessionController($mt, $user, $r, 5);
        $user_session_resource = $user_session_controller->getUserSession($user_resource);
        $this->assertEquals("$group_order_token", $user_session_resource->group_order_token, "there should have been a group order token on the user session");
        $this->assertEquals("2", $user_session_resource->group_order_type,"It should have the type of group order on the user session of the admin");
    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testValidateEmail($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $mmha = new MerchantMessageHistoryAdapter($mimetypes);
        $user = logTestUserIn($this->users[2]);
        $full_name = $user['first_name'] . ' ' . $user['last_name'];
        $orecords = $mmha->getRecords(array("order_id"=>$group_order_record['order_id']));
        $records = $mmha->getRecords(array("info" => "subject=Invitation To A Gotwo Group Order;from=Gotwo Group Ordering;","order_id"=>$group_order_record['order_id']));
        $this->assertCount(4, $records, "There should have been 4 emails created"); // JSB, 1/29/2015 - we should clean up the mmha records after these tests. This inflated count is confusing and dependent on other tests.

        $link = "https://gotwo.splickit.com/merchants/" . $this->merchant_id . "?order_type=pickup&group_order_token=$group_order_token";
        $this->assertContains($link, $records[0]['message_text'], "Should have found the link in the email");
    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testAddToGroupOrderUsingCart($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $group_order_id = $group_order_record['group_order_id'];

        $user_resource = createNewUserWithCC();
        $user_resource->first_name = 'Rob';
        $user_resource->last_name = 'Tombie';
        $user_resource->flags = '1C20000001';
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id']);
        $cart_note = "sum dum cart note";
        $order_data['note'] = $cart_note;
        $order_data['group_order_token'] = $group_order_token;

        $cart_resource = $this->addtoCart($user,$order_data);
        $this->assertNotNull($cart_resource, "should have gotten a cart resource back");
        $this->assertEquals("Y",$cart_resource->status,"It shoudl have a status of 'Y'");

        // validate that order records was added to mapping table
        $cart_order_resource = Resource::find(new CartsAdapter($m),$cart_resource->ucid);
        $gorma = new GroupOrderIndividualOrderMapsAdapter($m);
        $record = $gorma->getRecord(array("user_order_id"=>$cart_order_resource->order_id));
        $this->assertEquals($group_order_id,$record['group_order_id']);


        // validate note was added
        $ucid = $cart_resource->ucid;
        $submitted_cart_record = CartsAdapter::staticGetRecord(array("ucid" => $ucid), 'CartsAdapter');
        $individual_order_id = $submitted_cart_record['order_id'];

        // get checkout data to make sure there is only 1 time choice
        // checkout and place order should send the group order token

        $checkout_data_resource = $this->getCheckoutForGroupOrder($user,$ucid,$group_order_token,getTomorrowTwelveNoonTimeStampDenver()-1800);
        $this->assertNull($checkout_data_resource->error);
        $this->assertCount(1,$checkout_data_resource->lead_times_array,"It should only have 1 available lead time");
        //$this->assertfalse($checkout_data_resource->show_lead_times,"It should have the flag set to to not show lead time");
        $this->assertEquals(date('Y-m-d H:i:s',getTomorrowTwelveNoonTimeStampDenver() + 3600),date('Y-m-d H:i:s',$checkout_data_resource->lead_times_array[0]));
        $this->assertTrue(count($checkout_data_resource->accepted_payment_types) == 1,"We should have some accepted payment types");
        $payment_array = createHashmapFromArrayOfArraysByFieldName($checkout_data_resource->accepted_payment_types,'name');
        $this->assertNull($payment_array['Cash'],"There should not be a cash option for the payment array");


        // now place the order
        $order_resource = $this->placeOrderToBePartOfTypeTwoGroupOrder($user,$this->ids['merchant_id'],$ucid,$group_order_token,$checkout_data_resource,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertEquals($individual_order_id, $order_id, "should have created a valid order id on teh original cart record");

        $order_messages = MerchantMessageHistoryAdapter::getAllOrderMessages($order_id);
        $this->assertCount(1, $order_messages);
        $order_message = $order_messages[0];
        $this->assertEquals('Econf', $order_message->message_format);
        $this->assertEquals('G', $order_resource->status);

        //check email - user information
        $message_text = $order_message->message_text;
        $this->assertTrue(substr_count(strip_tags($message_text), "Rob, your order has been added to Rob's Group order.") == 1,"should have new format for participant email confirmation");

        $gorma = new GroupOrderIndividualOrderMapsAdapter($m);
        $record = $gorma->getRecord(array("user_order_id"=>$order_id));
        $this->assertEquals($group_order_id,$record['group_order_id']);

        //test to see if values get added to the parent order record
        $parent_order_record = OrderAdapter::staticGetRecord(array("ucid"=>$group_order_token),'OrderAdapter');
        $this->assertEquals($order_resource->order_amt,$parent_order_record['order_amt']);
        $this->assertEquals($order_resource->total_tax_amt, $parent_order_record['total_tax_amt']);
        $this->assertEquals($order_resource->order_quantity, $parent_order_record['order_quantity']);
        $this->assertEquals($order_resource->tip_amt, $parent_order_record['tip_amt']);
        $this->assertEquals($order_resource->grand_total, $parent_order_record['grand_total']);

        $complete_group_order = CompleteOrder::staticGetCompleteOrder($parent_order_record['order_id'],$m);
        $group_order_details = $complete_group_order['order_details'];


        $complete_individual_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
        $individual_order_details = $complete_individual_order['order_details'];

        $this->assertEquals($individual_order_details[0]['order_detail_complete_modifier_list_no_holds'],$group_order_details[0]['order_detail_complete_modifier_list_no_holds'],"since its the first order the mods shoudl be the same");
    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testAddAnotherOrderToGroupOrder($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $group_order_id = $group_order_record['group_order_id'];

        $user = logTestUserIn($this->users[2]);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'pickup','another note',2);
        $cart_note = "sum dum cart note";
        $order_data['note'] = $cart_note;
        $order_data['group_order_token'] = $group_order_token;

        $order_resource = $this->addToGroupOrderFullProcess($user,$this->ids['merchant_id'],$order_data,$group_order_token,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);

        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_id),'GroupOrderIndividualOrderMapsAdapter');
        $this->assertCount(2,$records,"there should have been two records in the group order map table");
    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testAThirdOrderToGroupOrder($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $group_order_id = $group_order_record['group_order_id'];

        $user_resource = createNewUserWithCC();
        $user_resource->first_name = 'third';
        $user_resource->last_name = 'group guy';
        $user_resource->flags = '1C20000001';
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'pickup','the third note',2);
        $cart_note = "sum dum third note";
        $order_data['note'] = $cart_note;
        $order_data['group_order_token'] = $group_order_token;

        $order_resource = $this->addToGroupOrderFullProcess($user,$this->ids['merchant_id'],$order_data,$group_order_token,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);

        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_id),'GroupOrderIndividualOrderMapsAdapter');
        $this->assertCount(3,$records,"there should have been three records in the group order map table");
    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testGetGroupOrderInfoForTypeTwo($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $user = logTestUserIn($this->users[2]);
        $group_order_controller = new GroupOrderController($m,$user,$r);
        $group_order_resource = $group_order_controller->getGroupOrderData($group_order_token);
        $group_order_info_resource = $group_order_controller->getTypeTwoGroupOrderInfoAsResource($group_order_resource->group_order_id);
        $summary = $group_order_info_resource->order_summary;
        $user_items = $summary['user_items'];
        $this->assertCount(3,$user_items);
        $this->assertEquals(5,$group_order_info_resource->total_items);
        $this->assertEquals(5,$group_order_info_resource->total_e_level_items);
        $this->assertEquals(3,$group_order_info_resource->total_submitted_orders);
        $this->assertEquals($this->merchant_id,$group_order_info_resource->merchant_id,"should have the merchant id on the group order info object");
        $user_items_as_hash_by_user_id = createHashmapFromArrayOfArraysByFieldName($user_items,"user_id");
        $user_item_record = $user_items_as_hash_by_user_id[$user['user_id']];
        $this->assertEquals('Rob Zmopolis',$user_item_record['full_name']);
        $this->assertEquals(2,$user_item_record['item_count']);

        //check for auto submit time on returned resource
        $expected_time_string = date('l',getTomorrowTwelveNoonTimeStampDenver()).' 12:15 pm';
        $this->assertEquals($expected_time_string,$group_order_info_resource->send_on_local_time_string);
        $this->assertTrue($group_order_resource->auto_send_activity_id > 0,"It should have a valid activity id on the group order resource");
    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testGetGroupOrderForSubmit($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $user_id = $group_order_record['admin_user_id'];
        $user = UserAdapter::staticGetRecordByPrimaryKey($user_id, 'UserAdapter');


        $request = new Request();
        $request->url = "app2/apiv2/grouporders/$group_order_token";
        $request->method = "GET";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $go_resource = $group_order_controller->processV2request();

        $this->assertNotNull($go_resource->group_order_token, "should have been a group order toekn on the response");
        $this->assertNotNull($go_resource->order_summary);
        $user_items = $go_resource->order_summary['user_items'];
        $this->assertCount(3,$user_items);
        $expected_time_string = date('l',getTomorrowTwelveNoonTimeStampDenver()).' 12:15 pm';
        $this->assertEquals($expected_time_string,$go_resource->send_on_local_time_string);
        $this->assertNull($go_resource->auto_send_activity_id,"It should not have the activity id on the group order resource");

        // we're going for auto submit so dont need this
//        $this->assertNotNull($go_resource->available_lead_times,"should have the available lead times on the object");
//        $this->assertEquals(true,false,"IT shoudl have the lead time appropriate for 5 e level items");
    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testAFourthOrderToGroupOrderWithoutSubmit($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $group_order_id = $group_order_record['group_order_id'];

        $user_resource = createNewUserWithCC();
        $user_resource->first_name = 'Reverand';
        $user_resource->last_name = 'Sasquatch';
        $user_resource->flags = '1C20000001';
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'pickup','the third note',2);
        $cart_note = "sum dum fourth note";
        $order_data['note'] = $cart_note;
        $order_data['group_order_token'] = $group_order_token;

        $cart_resource = $this->addtoCart($user,$order_data);
        $this->assertNull($cart_resource->error);
        $ucid = $cart_resource->ucid;
        $checkout_data_resource = $this->getCheckoutForGroupOrder($user,$ucid,$group_order_token,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_data_resource->error);

        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_id),'GroupOrderIndividualOrderMapsAdapter');
        $this->assertCount(4,$records,"there should have been three records in the group order map table");

        $submitted_records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_id,"status"=>"Submitted"),'GroupOrderIndividualOrderMapsAdapter');
        $this->assertCount(3,$submitted_records,"It should only have 3 submitted records");

        $inprocess_records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_id,"status"=>"In Process"),'GroupOrderIndividualOrderMapsAdapter');
        $this->assertCount(1,$inprocess_records,"It should only have 1 in process record");
    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testGetGroupOrderAsCompleteOrder($group_order_record)
    {
        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_record['group_order_id'],"status"=>'Submitted'),'GroupOrderIndividualOrderMapsAdapter');
        $this->assertCount(3,$records,"There should have been 3 records");
        $total_order_amt = 0.00;
        $total_tip_amt = 0.00;
        $total_tax_amt = 0.00;
        $total_items = 0;
        foreach ($records as $record) {
            $base_order_details = OrderAdapter::staticGetRecordByPrimaryKey($record['user_order_id'],'OrderAdapter');
            $total_order_amt += $base_order_details['order_amt'];
            $total_promo_amt += $base_order_details['promo_amt'];
            $total_tax_amt += $base_order_details['total_tax_amt'];
            $total_tip_amt += $base_order_details['tip_amt'];
            $total_grand_total += $base_order_details['grand_total'];
            $total_grand_total_to_merchant += $base_order_details['grand_total_to_merchant'];
            $total_items = $total_items + $base_order_details['order_qty'];

            $balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$record['user_order_id']),'BalanceChangeAdapter');

        }

        $group_order_token = $group_order_record['group_order_token'];
        $group_base_order = CompleteOrder::staticGetCompleteOrder($group_order_token);
        $this->assertEquals('G',$group_base_order['status']);
        $this->assertEquals($total_order_amt,$group_base_order['order_amt']);
        $this->assertEquals($total_tax_amt,$group_base_order['total_tax_amt']);
        $this->assertEquals($total_tip_amt,$group_base_order['tip_amt']);
        $this->assertEquals($total_grand_total,$group_base_order['grand_total']);
        $this->assertEquals($total_grand_total_to_merchant,$group_base_order['grand_total_to_merchant']);
        $this->assertEquals(5,$total_items);
        $this->assertEquals($total_items,$group_base_order['order_qty']);

        $this->assertCount(5,$group_base_order['order_details']);
    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testBillingSectionOfCompleteOrderForGroupOrder($group_order_record)
    {
        $group_order_records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_record['group_order_id'],"status"=>'Submitted'),'GroupOrderIndividualOrderMapsAdapter');
        foreach ($group_order_records as $group_order_records_map) {
            $balance_change_records[$group_order_records_map['user_order_id']] = BalanceChangeAdapter::staticGetRecord(array("order_id"=>$group_order_records_map['user_order_id'],"process"=>'CCpayment'),'BalanceChangeAdapter');
        }
        $this->assertCount(3,$balance_change_records,"there should have been 3 records of CC payments");

        // now get complete order for group order and we should see a payment section with CC info
        $group_order_token = $group_order_record['group_order_token'];
        $group_base_order = CompleteOrder::staticGetCompleteOrder($group_order_token);

        $this->assertNotNull($group_base_order['group_order_payments'],"It should have a payments section");
        $this->assertCount(3,$group_base_order['group_order_payments'],"it should have 3 payments");
        foreach ($group_base_order['group_order_payments'] as $payment_node) {
            $this->assertNotNull($payment_node['charge_amt'],"It needs to have the charge amount");
            $this->assertNotNull($payment_node['order_id'],"It should have the individual order_id");
            $this->assertNotNull($payment_node['ucid'],"It shoudl have the UCID of the order");
            $ucids[$payment_node['order_id']] = $payment_node['ucid'];
            $this->assertNotNull($payment_node['last_four'],"It should have the last 4 of the user as part of the payment node on the group order");
            $this->assertEquals('1234',$payment_node['last_four']);
        }

        //validate xoikos template

        $request = createRequestObject('/apiv2/message/getorderbyid?format=XD','GET');
        $xoikos_controller = new XoikosController(getM(),null,$request,5);
        $ready_to_send_message_resource = $xoikos_controller->getOrderById($group_order_record['order_id']);
        $message_text = $ready_to_send_message_resource->message_text;
        myerror_log($message_text);
        $message_text = cleanUpDoubleSpacesCRLFTFromString($message_text);



        foreach ($balance_change_records as $balance_change_record) {
            $amount = $balance_change_record['charge_amt'];
            $transaction_id = $ucids[$balance_change_record['order_id']];
            $expected_node = cleanUpDoubleSpacesCRLFTFromString("<Payment>
                                                                    <PaymentType>CreditCard</PaymentType>
                                                                    <Amount>$amount</Amount>
                                                                    <AccountNumber>XXXXXXXXXXXX1234</AccountNumber>
                                                                    <TransactionId>$transaction_id</TransactionId>
                                                                </Payment>");
            $this->assertContains($expected_node,$message_text);
        }
        return $group_order_record;

    }

    /**
     * @depends testBillingSectionOfCompleteOrderForGroupOrder
     */
    function testCancelOfGroupOrderFromRemote($group_order_record)
    {

    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testSubmitGroupOrderWithActivity($group_order_record)
    {
        $group_order_activity_id = $group_order_record['activity_id'];
        $send_group_order_activity = SplickitActivity::findActivityResourceAndReturnActivityObjectByActivityId($group_order_activity_id);
        $this->assertNotNull($send_group_order_activity);
        $class_name = get_class($send_group_order_activity);
        $this->assertEquals("SendGroupOrderActivity", $class_name);
        $this->assertTrue($send_group_order_activity->doit());
        return $group_order_record;
    }

    /**
     * @depends testSubmitGroupOrderWithActivity
     */
    function testDoNotFailActivityIfGOiSAlreadySubmitted($group_order_record)
    {
        $group_order_activity_id = $group_order_record['activity_id'];
        $activity_history_resource = Resource::find(new ActivityHistoryAdapter(),"$group_order_activity_id");
        $activity_history_resource->locked = 'N';
        $activity_history_resource->executed_dt_tm = "0000-00-00";
        $activity_history_resource->save();

        $activity_history_adapter = new ActivityHistoryAdapter();
        $send_group_order_activity = $activity_history_adapter->getActivityFromUnlockedActivityHistoryResource($activity_history_resource);
        $send_group_order_activity->executeThisActivity();

        // validate the activity was cancelled
        $after_activity_history_resource = Resource::find(new ActivityHistoryAdapter(),"$group_order_activity_id");
        $this->assertEquals("C",$after_activity_history_resource->locked);

    }

    /**
     * @depends testSubmitGroupOrderWithActivity
     */
    function testGetGroupOrderStatusAfterSubmitted($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $user_id = $group_order_record['admin_user_id'];
        $user = UserAdapter::staticGetRecordByPrimaryKey($user_id, 'UserAdapter');
//        $request = new Request();
//        $request->url = "app2/apiv2/grouporders/$group_order_token";
//        $request->method = "GET";
        $request = createRequestObject("app2/apiv2/grouporders/$group_order_token",'GET');
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $go_resource = $group_order_controller->processV2request();
        $this->assertNull($go_resource->error);

        $this->assertNotNull($go_resource->group_order_token, "should have been a group order toekn on the response");
        $this->assertNotNull($go_resource->order_summary);
        $user_items = $go_resource->order_summary['user_items'];
        $this->assertCount(3,$user_items);
        $this->assertEquals('Submitted',$go_resource->status,"It should have a submitted status");

    }

    /**
     * @depends testSubmitGroupOrderWithActivity
     */
    function testFinalGroupOrderShouldOnlyContainSubmittedOrders($group_order_record)
    {
        $complete_order = CompleteOrder::staticGetCompleteOrder($group_order_record['order_id']);
        $order_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($group_order_record['order_id'],'FUA');
        $email_controller = ControllerFactory::generateFromMessageResource($order_message_resource);
        $ready_to_send_message_resource = $email_controller->prepMessageForSending($order_message_resource);
        $message_text = $ready_to_send_message_resource->message_text;
        myerror_log($message_text);
       // die("we died");
       // $this->assertTrue(false,"check log to find out what message looks like");

    }

    /**
     * @depends testSubmitGroupOrderWithActivity
     */
    function testCheckStatusOfChildrenGroupOrdersAfterSubmit($group_order_record)
    {
        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_record['group_order_id']),'GroupOrderIndividualOrderMapsAdapter');
        foreach ($records as $record) {
            $order_resource = CompleteOrder::getBaseOrderData($record['user_order_id']);
            $status = $order_resource;
        }
    }


    /**
     * @depends testSubmitGroupOrderWithActivity
     */
    function testGroupOrderSubmitted($group_order_record)
    {
        // it should create the messages in the Merchant_Message_History table
        // it shoudl close out the group order and add the sent time to it
        $group_order_token = $group_order_record['group_order_token'];
        $group_base_order = CompleteOrder::staticGetCompleteOrder($group_order_token);

        // check to make sure the messages were created
        $order_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($group_base_order['order_id'],'E');
        $this->assertEquals($group_base_order['order_id'],$order_message_resource->order_id,"the order id of the message should equal that of the parent group order");

        // all order status for completed orders should have been set to 'O'
        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_record['group_order_id'],"status"=>'Submitted'),'GroupOrderIndividualOrderMapsAdapter');
        $this->assertCount(3,$records,"There should be 3 orders");
        foreach($records as $record) {
            $base_order_info = CompleteOrder::getBaseOrderData($record['user_order_id']);
            $this->assertEquals('O',$base_order_info['status']);
        }

        // order status for in progress order should have been set to cancelled 'C'
        //$records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_record['group_order_id']),'GroupOrderIndividualOrderMapsAdapter');
        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_record['group_order_id'],"status"=>'In Process'),'GroupOrderIndividualOrderMapsAdapter');
        $this->assertCount(1,$records,"There should be 1 order");
        foreach($records as $record) {
            $base_order_info = CompleteOrder::getBaseOrderData($record['user_order_id']);
            $this->assertEquals('C',$base_order_info['status']);
        }


        $final_group_order_record = getStaticRecord(array("group_order_id"=>$group_order_record['group_order_id']),'GroupOrderAdapter');
        $this->assertEquals('Submitted',$final_group_order_record['status']);
        $e = explode(' ',$final_group_order_record['sent_ts']);
        $sent_date = $e[0];
        $this->assertEquals(date('Y-m-d'),$sent_date,"shoudl have today as sent");
        return $group_base_order['order_id'];
    }

    /**
     * @depends testSubmitGroupOrderWithActivity
     */
    function testGroupOrderIsNotOnUserSessionAnymore($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $user = logTestUserIn($this->users[2]);
        $user_resource = SplickitController::getResourceFromId($this->users[2], "User");
        $user_session_controller = new UsersessionController($mt, $user, $r, 5);
        $user_session_resource = $user_session_controller->getUserSession($user_resource);
        $this->assertNotEquals($group_order_token,$user_session_resource->group_order_token);
        //$this->assertNull($user_session_resource->group_order_token, "there should NOT have been a group order token on the user session");
    }

    /**
     * @depends testSubmitGroupOrderWithActivity
     */
    function testSubmitAlreadySubmittedGroupOrder($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $group_order_controller = new GroupOrderController($m,$u,$r,5);
        $this->assertFalse($group_order_controller->sendGroupOrder($group_order_token),"It should returned a false since the group order was already submitted");
        $error_resource = $group_order_controller->error_resource;
        $this->assertEquals($group_order_controller->group_order_submitted_message,$error_resource->error);
    }

    /**
     * @depends testSubmitGroupOrderWithActivity
     */
    function testAddToSubmittedGroupOrder($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $group_order_id = $group_order_record['group_order_id'];

        $r = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_id),'GroupOrderIndividualOrderMapsAdapter');
        $this->assertCount(4,$r,"there should be three records in the group order map table");

        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'pickup','note',1);
        $order_data['group_order_token'] = $group_order_token;
        $order_resource = $this->addToGroupOrderFullProcess($user,$this->ids['merchant_id'],$order_data,$group_order_token,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNotNull($order_resource->error);
        $this->assertEquals('Sorry, this group order has already been submitted.',$order_resource->error);

        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_id),'GroupOrderIndividualOrderMapsAdapter');
        $this->assertCount(4,$records,"there should have been three records in the group order map table");
    }


    /**
     * @depends testGroupOrderSubmitted
     */
    function testValidateEConfEmailHasBeenCreatedWIthCorrectFormat($order_id)
    {
        $order_conf_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'Econf');
        $this->assertNotFalse($order_conf_message_resource,"ther should have been an order conf email sent to the admin");
    }

    /**
     * @depends testGroupOrderSubmitted
     */
    function testCreateTheCompeteOrderMessageForTheGroupOrder($order_id)
    {
        $group_order_record = GroupOrderAdapter::staticGetRecord(array("order_id"=>$order_id),'GroupOrderAdapter');
        $child_order_records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_record['group_order_id']),'GroupOrderIndividualOrderMapsAdapter');
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id);
        $this->assertNotNull($complete_order['group_order_info']);

        //create the order message from the 3 composit orders
        $order_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'FUA');
        $fax_controller = ControllerFactory::generateFromMessageResource($order_message_resource);
        $ready_to_send_message_resource = $fax_controller->prepMessageForSending($order_message_resource);
        $message_text = $ready_to_send_message_resource->message_text;
        myerror_log($message_text);

        $this->assertContains("For Third G.",$message_text);
        $this->assertContains("For Rob Z.",$message_text);
        $this->assertContains("For Rob T.",$message_text);
        $this->assertNotContains("Reverand",$message_text);
        $this->assertNotContains("Sasquatch",$message_text);

        $order_message_resource_email = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'E');
        $email_controller = ControllerFactory::generateFromMessageResource($order_message_resource_email);
        $ready_to_send_email_message_resource = $email_controller->prepMessageForSending($order_message_resource_email);
        $email_message_text = $ready_to_send_email_message_resource->message_text;



        foreach ($child_order_records as $child_order_record) {
            $child_order_id = $child_order_record['user_order_id'];
            $complete_order = CompleteOrder::staticGetCompleteOrder($child_order_id,$m);
            $oinfo_string = $complete_order['first_name']." ".substr($complete_order['last_name'],0,1)."  $child_order_id";
            if ($child_order_record['status'] == 'Submitted') {
                $this->assertContains($oinfo_string,$message_text);
                $this->assertContains("Promo Amt: $".-$complete_order['promo_amt'],$message_text);
                $this->assertContains("Grand Total: $".$complete_order['grand_total'],$message_text);

                $this->assertContains($oinfo_string,$email_message_text);
                $this->assertContains("Promo Amt: $".-$complete_order['promo_amt'],$email_message_text);
                $this->assertContains("Grand Total: $".$complete_order['grand_total'],$email_message_text);
            } else {
                $this->assertNotContains($oinfo_string,$message_text);
            }
        }
    }

    /**
     * @depends testSubmitGroupOrderWithActivity
     */
    function testSendGroupOrderMessage($group_order_record)
    {
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($group_order_record['order_id'],'E');
        $message_controller = ControllerFactory::generateFromMessageResource($message_resource,$m);
        $this->assertTrue($message_controller->sendThisMessage($message_resource));
        $order_record = CompleteOrder::getBaseOrderData($group_order_record['order_id'],$m);
        $this->assertEquals('G',$order_record['status']);

        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_record['group_order_id'],'status'=>'Submitted'),'GroupOrderIndividualOrderMapsAdapter');
        $this->assertCount(3,$records,"there should be 3 submitted group orders");
        foreach ( $records as $record) {
            $order_id = $record['user_order_id'];
            $child_order_record = CompleteOrder::getBaseOrderData($order_id);
            $this->assertEquals(OrderAdapter::ORDER_EXECUTED,$child_order_record['status'],"Submitted orders should all be Exectuted");
        }

        $cancelled_record = GroupOrderIndividualOrderMapsAdapter::staticGetRecord(array("group_order_id"=>$group_order_record['group_order_id'],'status'=>'In Process'),'GroupOrderIndividualOrderMapsAdapter');
        $cancelled_order_record = CompleteOrder::getBaseOrderData($cancelled_record['user_order_id']);
        $this->assertEquals(OrderAdapter::ORDER_CANCELLED,$cancelled_order_record['status'],"The order that did not make it in should be cancelled");

    }

    /**
     * @depends testSubmitGroupOrderWithActivity
     */
    function testOrderHistoryShouldNotContainTheGroupOrder($group_order_record)
    {
        // /app2/apiv2/users/4750-ms7g6-1er06-255mc/orderhistory
        $group_order_token = $group_order_record['group_order_token'];
        $group_order_id = $group_order_record['group_order_id'];
        $parent_order_id = $group_order_record['order_id'];
        // set the order to executed to simulate the message being sent out
//        OrderAdapter::updateOrderStatus(OrderAdapter::ORDER_EXECUTED,$parent_order_id);
//        $parent_order_record = CompleteOrder::getBaseOrderData($parent_order_id,$m);
//        $this->assertEquals(OrderAdapter::ORDER_EXECUTED,$parent_order_record['status']);
        $user_id = $group_order_record['admin_user_id'];
        $user = logTestUserIn($user_id);
        $uuid = $user['uuid'];
        $request = createRequestObject("/app2/apiv2/users/$uuid/orderhistory",'GET');
        $user_controller = new UserController($m,$user,$request,5);
        $response = $user_controller->processV2Request();
        $orders = $response->data['orders'];
        $this->assertCount(1,$orders);
        foreach ($orders as $hist_order) {
            $this->assertNotEquals($parent_order_id,$hist_order['order_id']);
        }

    }

    function testPreventCreationOfSecondGroupOrderByUser()
    {
        setContext("com.splickit.gotwo");
        $note = "sum dum note";
        $emails = "sumdumemail1@dummy.com,sumdumemail2@dummy.com,sumdumemail3@dummy.com,sumdumemail4@dummy.com";
        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);

        $request = new Request();
        $request->data = array("merchant_id" => $this->merchant_id, "note" => $note, "merchant_menu_type" => 'Pickup', "participant_emails" => $emails, "group_order_type" => 2, "submit_at_ts" => (getTomorrowTwelveNoonTimeStampDenver() + 7200));
        $request->url = "app2/apiv2/grouporders";
        $request->method = "POST";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $group_order_controller->current_time = getTomorrowTwelveNoonTimeStampDenver() - 3600;
        $resource = $group_order_controller->processV2Request();
        $this->assertNull($resource->error,"Should Not have thrown an error");
        $initial_group_order_id = $resource->group_order_id;
        // try to create a second
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $group_order_controller->current_time = getTomorrowTwelveNoonTimeStampDenver() ;
        $resource = $group_order_controller->processV2Request();
        $this->assertNotNull($resource->error,"Should have thrown an error because user should not be able to create a second group order");
        $this->assertEquals("Sorry, you can only have one active group order at a time. Please cancel the first one if you would like to create a new one.",$resource->error);

        // now set time to the future to see if it lets you crate after expired
        $future_time = getTomorrowTwelveNoonTimeStampDenver() + (3*24*60*60);
        $request = new Request();
        $request->data = array("merchant_id" => $this->merchant_id, "note" => $note, "merchant_menu_type" => 'Pickup', "participant_emails" => $emails, "group_order_type" => 2, "submit_at_ts" => ($future_time + 7200));
        $request->url = "app2/apiv2/grouporders";
        $request->method = "POST";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $group_order_controller->current_time = $future_time;
        $resource = $group_order_controller->processV2Request();
        $this->assertNull($resource->error,"Should Not have thrown an error");

        // now check that inital order was expired
        $initial_go_record = GroupOrderAdapter::staticGetRecordByPrimaryKey($initial_group_order_id,'GroupOrderAdapter');
        $this->assertEquals('Expired',$initial_go_record['status'],"It should have set the status to cancelled");
    }
    
    /********************  helper functions **************************/

    function addtoCart($user,$order_data)
    {
        $json_encoded_data = json_encode($order_data);
        $url = isset($order_data['ucid']) ? "/app2/apiv2/cart".$order_data['ucid'] : "/app2/apiv2/cart";
        $request = createRequestObject($url,"POST",$json_encoded_data,'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $cart_resource = $place_order_controller->processV2Request();
        return $cart_resource;
    }

    function getCheckoutForGroupOrderWithPromoCode($user,$ucid,$group_order_token,$time,$promo_code)
    {
//        $request = new Request();
//        $request->url = "http://localhost/app2/apiv2/cart/$ucid/checkout";
//        $request->method = "post";
//        $request->mimetype = 'application/json';
//        $request->body = json_encode(array("promo_code"=>$promo_code));
        $request = createRequestObject("http://localhost/app2/apiv2/cart/$ucid/checkout",'POST',json_encode(array("promo_code"=>$promo_code)),'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request,5);
        $place_order_controller->setCurrentTime($time);
        $checkout_data_resource = $place_order_controller->processV2Request();
        return $checkout_data_resource;
    }

    function getCheckoutForGroupOrder($user,$ucid,$group_order_token,$time)
    {
        $request = new Request();
        $request->url = "http://localhost/app2/apiv2/cart/$ucid/checkout";
        $request->method = "get";
        $request->mimetype = 'application/json';
        $place_order_controller = new PlaceOrderController($mt, $user, $request,5);
        $place_order_controller->setCurrentTime($time);
        $checkout_data_resource = $place_order_controller->processV2Request();
        return $checkout_data_resource;
    }

    function placeOrderToBePartOfTypeTwoGroupOrder($user,$merchant_id,$ucid,$group_order_token,$checkout_data_resource,$time)
    {
        $order_data = array();
        $order_data['merchant_id'] = $merchant_id;
        $order_data['user_id'] = $user['user_id'];
        $order_data['cart_ucid'] = $ucid;
        $order_data['tip'] = (rand(100, 1000)) / 100;
        $payment_array = $checkout_data_resource->accepted_payment_types;
        $order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
        $lead_times_array = $checkout_data_resource->lead_times_array;
        $order_data['actual_pickup_time'] = $lead_times_array[0];
        $order_data['requested_time'] = $lead_times_array[0];

        //$order_data['group_order_token'] = $group_order_token;
        $json_encoded_data = json_encode($order_data);
        $request = new Request();
        $request->url = "/apiv2/orders/$ucid";
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime($time);
        $order_resource = $place_order_controller->processV2Request();
        return $order_resource;
    }

    function addToGroupOrderFullProcess($user,$merchant_id,$order_data,$group_order_token,$time)
    {
        $cart_resource = $this->addtoCart($user,$order_data);
        if ($cart_resource->error) {
            return $cart_resource;
        }
        $ucid = $cart_resource->ucid;

        $checkout_data_resource = $this->getCheckoutForGroupOrder($user,$ucid,$group_order_token,$time);
        if ($checkout_data_resource->error) {
            return $checkout_data_resource;
        }
        $order_resource = $this->placeOrderToBePartOfTypeTwoGroupOrder($user,$merchant_id,$ucid,$group_order_token,$checkout_data_resource,$time);
        return $order_resource;
    }

    /********************  end helper functions **************************/

    static function setUpBeforeClass()
    {
        ini_set('max_execution_time', 300);

              SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
        getOrCreateSkinAndBrandIfNecessaryWithLoyalty("gotwo","gotwo",$skin_id,$brand_id);
        $skin_resource = setContext("com.splickit.gotwo");

        $blr_data['brand_id'] = $skin_resource->brand_id;
        $blr_data['loyalty_type'] = 'splickit_earn';
        $blr_data['earn_value_amount_multiplier'] = 1;
        $blr_data['cliff_value'] = 10;
        $brand_loyalty_rules_resource = Resource::factory(new BrandLoyaltyRulesAdapter($m),$blr_data);
        $brand_loyalty_rules_resource->save();

        $_SERVER['request_time1'] = microtime(true);
        $menu_id = $menu_id = createTestMenuWithNnumberOfItems(3);
        $ids['menu_id'] = $menu_id;

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 4);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);

        $merchant_resource = createNewTestMerchantDelivery($menu_id);
        $options[TONIC_FIND_BY_METADATA] = array("merchant_id" => $merchant_resource->merchant_id);
        $mdpd_resource = Resource::find(new MerchantDeliveryPriceDistanceAdapter($m), '', $options);
        $mdpd_resource->price = 5.55;
        $mdpd_resource->save();
        $merchant_resource->group_ordering_on = 1;
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        $ids['merchant_id'] = $merchant_id;

        $map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_resource->merchant_id,"message_format"=>'FUA',"delivery_addr"=>"1234567890","message_type"=>"X"));

        $billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);

        $merchant_payment_type_map_adapter = new MerchantPaymentTypeMapsAdapter($mimetypes);
        $cc_merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_id, 2000, $billing_entity_resource->id);
        $ids['cc_billing_entity_id'] = $cc_merchant_payment_type_resource->billing_entity_id;

        // create cash merchang payment type record
        $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_id, 1000, $billing_entity_id);

        //loyalty paymeent types
        $merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 8000, $billing_entity_id);
        $merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 9000, $billing_entity_id);


//        $user_resource = createNewUser(array('flags' => '1C20000001', 'first_name' => 'adam', 'last_name' => 'zmopolis', 'last_four'=>'1234'));
        $user_resource = createNewUserWithCCNoCVV(array('first_name' => 'adam', 'last_name' => 'zmopolis'));
        $ids['user_id'] = $user_resource->user_id;
        $users[1] = $user_resource->user_id;
        $user_resource2 = createNewUserWithCCNoCVV(array('first_name' => 'rob', 'last_name' => 'zmopolis'));
        $user_resource3 = createNewUserWithCCNoCVV(array('first_name' => 'ty', 'last_name' => 'zmopolis'));
        $user_resource4 = createNewUserWithCCNoCVV(array('first_name' => 'ty', 'last_name' => 'zmopolis'));
//        $user_resource3 = createNewUser(array('flags' => '1C20000001', 'first_name' => 'ty', 'last_name' => 'zmopolis', 'last_four'=>'1234'));
//        $user_resource4 = createNewUser(array('flags' => '1C20000001', 'first_name' => 'jason', 'last_name' => 'zmopolis', 'last_four'=>'1234'));
        $users[2] = $user_resource2->user_id;
        $users[3] = $user_resource3->user_id;
        $users[4] = $user_resource4->user_id;

        $_SERVER['log_level'] = 5;
        $_SERVER['unit_test_ids'] = $ids;
        $_SERVER['users'] = $users;

    }

    static function tearDownAfterClass()
    {
        SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
    }

    static function main()
    {
        $suite = new PHPUnit_Framework_TestSuite(__CLASS__);
        PHPUnit_TextUI_TestRunner::run($suite);
    }
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    GroupOrderIndividualPayTest::main();
}

?>