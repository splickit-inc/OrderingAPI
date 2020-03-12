<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class LoyaltyCliffTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;
	
	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
        setContext($this->ids['context']);

	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->stamp);
		unset($this->ids);
    }

    function testReverseLoyaltyEarnOnCashOrderCancel()
    {
        $menu_id = $this->ids['menu_id'];

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        attachMerchantToSkin($merchant_resource->merchant_id, getSkinIdForContext());

        // create cash merchant payment type record
        $merchant_payment_type_map_adapter = new MerchantPaymentTypeMapsAdapter(getM());
        $cash_merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);

        // loyalty merchant payment type record
        $merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 8000, $billing_entity_id);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user_resource->user_id;
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session_controller->getUserSession($user_resource);

        $ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($order_data);
        $payment_hash = createHashmapFromArrayOfArraysByFieldName($checkout_resource->accepted_payment_types,'name');
        $checkout_resource->accepted_payment_types = [$payment_hash['Cash']];
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;

        //check to see that loyalty was earned
        $ubp_resource = $ubp_resource->refreshResource();
        $this->assertTrue($ubp_resource->points > 0);

        //validate that a cash record was created
        $bca = new BalanceChangeAdapter(getM());
        $balance_change_records = $bca->getRecordsByOrderId($order_id);
        $this->assertCount(1,$balance_change_records,'It should have 1 balance change record');
        $bcr_hash = createHashmapFromArrayOfArraysByFieldName($balance_change_records,'process');
        $this->assertNotNull($bcr_hash['Cash'],'There should be a cash record');


        //cancel cash order
        $request = new Request();
        $request->data['note'] = 'Sum dum note';
        $order_controller = new OrderController($mt, $user, $request, 5);
        $refund_results = $order_controller->issueOrderRefund($order_id, "0.00");

        //check to see that loyalty was reversed
        $ubp_resource = $ubp_resource->refreshResource();
        $this->assertEquals(0,$ubp_resource->points);

        // check to make sure messages were cancelled
        $order_messages = MerchantMessageHistoryAdapter::getAllOrderMessages($order_id);
        foreach ($order_messages as $order_message) {
            $this->assertEquals('C',$order_message->locked,'It should have cancelled all open messages');
        }

        $balance_change_records = $bca->getRecordsByOrderId($order_id);
        $this->assertCount(2,$balance_change_records,'It should have now have 2 balance change records');


    }

	function testAutoJoinAsDefaultForHomeGrown()
	{
		$user_resource = createNewUserWithCCNoCVV();
		$user = logTestUserResourceIn($user_resource);
		$user_session_controller = new UsersessionController($m,$user,$r,5);
		$user_session = $user_session_controller->getUserSession($user_resource);
		$user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
		$user_brand_points_record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
		$this->assertNotNull($user_brand_points_record,"Should have found a user brand loyalty record");
		$loyalty_number = $user_brand_points_record['loyalty_number'];
		$this->assertNotNull($loyalty_number,"Should have generated a loyalty number");
		$this->assertEquals(cleanAllNonNumericCharactersFromString($user_resource->contact_no), $loyalty_number,"It should be the users contact nubmer");
		$this->assertEquals(0,$user_brand_points_record['points'],'should have zero points');
		$this->assertEquals(0,$user_brand_points_record['dollar_balance'],'should have zero dollar value');
		return $user_resource;
	}

	/**
	 * @depends testAutoJoinAsDefaultForHomeGrown
	 */
	function testCliff($user_resource)
	{
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user_resource->user_id;
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours',4);
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000);
		$ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
		$this->assertTrue($order_resource->order_amt > 1.00);
		$expected_points = round($order_resource->order_amt);
		$ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
		$this->assertEquals($expected_points,$ubpm_record['points'],"Shouljd have the expected points");
		$this->assertEquals(0,$ubpm_record['dollar_balance'],'should have zero dollar value');

		$ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
		$ublh_records = $ublh_adapter->getRecords(array("order_id"=>$order_id));
		$this->assertCount(1,$ublh_records,"There should be 1 record for this order");
		$hash = createHashmapFromArrayOfArraysByFieldName($ublh_records,'process');
		$this->assertEquals($ubpm_record['points'],$hash['Order']['points_added'],'It should have the points earned');
		$this->assertEquals($ubpm_record['points'],$hash['Order']['current_points']);
		$this->assertEquals(0.00,$hash['Order']['current_dollar_balance']);


		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours',4);
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000);
		$ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
		$this->assertTrue($order_resource->order_amt > 1.00);
		$expected_points2 = round($order_resource->order_amt);
		$ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
		$this->assertEquals($expected_points+$expected_points2-10,$ubpm_record['points'],"Cliff shoujld have been reached so points would have been deducted");
		$this->assertEquals(1,$ubpm_record['dollar_balance'],'should have 1 dollar value since we got to 10 points which put us over the cliff');

		$ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
		$ublh_records = $ublh_adapter->getRecords(array("order_id"=>$order_id));
		$this->assertCount(1,$ublh_records,"There should be 1 record for this order");
		$hash = createHashmapFromArrayOfArraysByFieldName($ublh_records,'process');
		$this->assertEquals($expected_points2,$hash['Order']['points_added'],'It should have the points earned');
		$this->assertEquals($ubpm_record['points'],$hash['Order']['current_points']);
		$this->assertEquals(1.00,$hash['Order']['current_dollar_balance']);

		$ublh_records = $ublh_adapter->getRecords(array("user_id"=>$user_id));
		$this->assertCount(2,$ublh_records,"There should be two loyalty history records");

	}

	function testMultipleCliff()
	{
		$user_resource = createNewUserWithCCNoCVV();
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user_resource->user_id;
		$user_session_controller = new UsersessionController($m,$user,$r,5);
		$user_session_controller->getUserSession($user_resource);

		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours',30);
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000);
		$ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
		$this->assertTrue($order_resource->order_amt > 1.00);
		$expected_points = round($order_resource->order_amt) % 10;
		$this->assertTrue($expected_points < 10 && $expected_points > 0,"Point should be between 0 and 10");
		$ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
		$this->assertEquals($expected_points,$ubpm_record['points'],"Should have points equal to the remanider from devision by 10");
		$expected_dollar_value = (int)((round($order_resource->order_amt))/10);
		$this->assertEquals($expected_dollar_value,$ubpm_record['dollar_balance'],'should have a dollar value equal to the number of times 10 goes into the order amount');
        return $order_id;
	}

	/**
	 * @depends testMultipleCliff
	 */
	function testReverseLoyaltyEarnOnRefund($order_id)
    {
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
        $user_id = $complete_order['user_id'];
        $user = getStaticRecord(array("user_id"=>$user_id),'UserAdapter');
        $order_controller = new OrderController($mt, $user, $r, 5);
        $refund_results = $order_controller->issueOrderRefund($order_id, "0.00");
        $this->assertEquals("success", $refund_results['result']," should have gotten a success but: ".$refund_results['message']);
        $this->assertEquals(100,$refund_results['response_code']);

        $balance_change_resource = Resource::find(new BalanceChangeAdapter($mimetypes),''.$refund_results['balance_change_id']);
        $this->assertEquals($expected, $actual);
        $this->assertEquals($balance_change_resource->charge_amt, $complete_order['grand_total']);
        $this->assertEquals($balance_change_resource->process, 'CCvoid');

        // now check to make sure $ were put back on and points earned were taken off
        $ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));
        $this->assertEquals(0.00,$ubp_resource->dollar_balance,'It should have a zero dollar balance again');
        $this->assertEquals(0,$ubp_resource->points,"It should have a zero points balance");

        $ublha = new UserBrandLoyaltyHistoryAdapter($m);
        $history_transaction_resources = $ublha->getLoyaltyHistoryByOrderId($order_id);
        $this->assertCount(2,$history_transaction_resources,"there should be two histories");
        $this->assertContains('-REVERSED',$history_transaction_resources[1]->process);
        $this->assertContains('-45',$history_transaction_resources[1]->points_added);
        $this->assertEquals(0.00,$history_transaction_resources[1]->current_dollar_balance);
        $this->assertEquals(0,$history_transaction_resources[1]->current_points);

    }

    function testMultipleCliffWithSmallPointsBalanceAndThenRefund()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user_resource->user_id;
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session_controller->getUserSession($user_resource);

        $starting_points_value = 3;
        $starting_dollar_balance = 1.15;

        $ubpma = new UserBrandPointsMapAdapter($m);
        $user_brand_points_map_resource = Resource::find($ubpma,'',array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id)));
        $user_brand_points_map_resource->points = $starting_points_value;
        $user_brand_points_map_resource->dollar_balance = $starting_dollar_balance;
        $user_brand_points_map_resource->save();

        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours',17);
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000);
        $ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $this->assertTrue($order_resource->order_amt > 1.00);
        $expected_points = (round($order_resource->order_amt) % 10) + $starting_points_value;
        $this->assertTrue($expected_points < 10 && $expected_points > 0,"Point should be between 0 and 10");
        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals($expected_points,$ubpm_record['points'],"Should have points equal to the remanider from devision by 10");
        $expected_dollar_value = (int)((round($order_resource->order_amt))/10);
        $this->assertEquals($expected_dollar_value+$starting_dollar_balance,$ubpm_record['dollar_balance'],'should have a dollar value equal to the number of times 10 goes into the order amount');

        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
        $user_id = $complete_order['user_id'];
        $user = getStaticRecord(array("user_id"=>$user_id),'UserAdapter');
        $order_controller = new OrderController($mt, $user, $r, 5);
        $refund_results = $order_controller->issueOrderRefund($order_id, "0.00");
        $this->assertEquals("success", $refund_results['result']," should have gotten a success but: ".$refund_results['message']);
        $this->assertEquals(100,$refund_results['response_code']);

        $balance_change_resource = Resource::find(new BalanceChangeAdapter($mimetypes),''.$refund_results['balance_change_id']);
        $this->assertEquals($expected, $actual);
        $this->assertEquals($balance_change_resource->charge_amt, $complete_order['grand_total']);
        $this->assertEquals($balance_change_resource->process, 'CCvoid');

        // now check to make sure $ were put back on and points earned were taken off
        $ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));
        $this->assertEquals($starting_dollar_balance,$ubp_resource->dollar_balance,'It should have the starting dollar balance again');
        $this->assertEquals($starting_points_value,$ubp_resource->points,"It should have the starting points balance");

        $ublha = new UserBrandLoyaltyHistoryAdapter($m);
        $history_transaction_resources = $ublha->getLoyaltyHistoryByOrderId($order_id);
        $this->assertCount(2,$history_transaction_resources,"there should be two histories");
        $this->assertContains('-REVERSED',$history_transaction_resources[1]->process);
    }


    function testPayWithBalanceOptionOnCheckout()
	{
		$menu_id = $this->ids['menu_id'];

		$merchant_resource = createNewTestMerchant($menu_id);
		attachMerchantToSkin($merchant_resource->merchant_id, getSkinIdForContext());

		$billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);

		$merchant_payment_type_map_adapter = new MerchantPaymentTypeMapsAdapter($mimetypes);
		$cc_merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);

		// create cash merchant payment type record
		$cash_merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);

		// loyalty merchant payment type record
		$merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 8000, $billing_entity_id);

		$user_resource = createNewUserWithCCNoCVV();
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user_resource->user_id;
		$user_session_controller = new UsersessionController($m,$user,$r,5);
		$user_session_controller->getUserSession($user_resource);

		$ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));
		$ubp_resource->dollar_balance = 3.65;
		$ubp_resource->points = 7;
		$ubp_resource->save();

		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_resource->merchant_id, 'pickup', 'note');
		
		$request = createRequestObject('/app2/apiv2/cart','post',json_encode($order_data),'application/json');
		$place_order_controller = new PlaceOrderController($mt, $user, $request,5);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$cart_resource = $place_order_controller->processV2Request();
		$this->assertNull($cart_resource->error);
		$cart_ucid = $cart_resource->ucid;

		$request = createRequestObject("/app2/apiv2/cart/$cart_ucid/checkout",'get');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$checkout_resource = $place_order_controller->processV2Request();

		$payment_array = $checkout_resource->accepted_payment_types;
		$this->assertCount(3,$payment_array,'there should be 3 choices in the payment array');
		$payment_hash = createHashmapFromArrayOfArraysByFieldName($payment_array,'splickit_accepted_payment_type_id');
		$loyalty_payment_choice = $payment_hash['8000'];
		$this->assertEquals('Use $1.50 Loyalty Rewards ('.LoyaltyBalancePaymentService::BALANCE_ON_CARD_TEXT.')',$loyalty_payment_choice['name']);
		return $checkout_resource;
	}

	/**
	 * @depends testPayWithBalanceOptionOnCheckout
	 */
	function testPayWithBalanceOption($checkout_resource)
	{
		$base_order_data = CompleteOrder::getBaseOrderData($checkout_resource->oid_test_only);
		$user = logTestUserIn($base_order_data['user_id']);
		$new_cart_note = "the new cart note";
		$order_data['merchant_id'] = $base_order_data['merchant_id'];
		$order_data['note'] = $new_cart_note;
		$order_data['user_id'] = $base_order_data['user_id'];
		$order_data['cart_ucid'] = $base_order_data['ucid'];
		//$order_data['tip'] = (rand(100, 200))/100;
		$order_data['tip'] = 0.00;
		//$base_order_data['grand_total'] = $base_order_data['grand_total'] + $order_data['tip'];
		$payment_array = $checkout_resource->accepted_payment_types;
		$order_data['merchant_payment_type_map_id'] = $payment_array[2]['merchant_payment_type_map_id'];
		$lead_times_array = $checkout_resource->lead_times_array;
		$order_data['actual_pickup_time'] = $lead_times_array[0];
		// this should be ignored;
		$order_data['lead_time'] = 100000;

		$request = createRequestObject('/apiv2/orders','post',json_encode($order_data),'application/json');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$order_resource = $place_order_controller->processV2Request();
		$order_summary_json = json_encode($order_resource->order_summary);
		$this->assertNull($order_resource->error);
		$this->assertEquals(0.00,$order_resource->total_tax_amt,"there should have been tax equal to the difference");
		$this->assertEquals(0.00,$order_resource->grand_total,"there should have been a grand total equal to the difference");
		$this->assertEquals(0.00,$order_resource->grand_total_to_merchant,"there should have been a grand total to the merchant equal to the difference");
		$order_id = $order_resource->order_id;

		//check to see if all records were added to balance change table
		$balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),'BalanceChangeAdapter');
		$bcr_hash = createHashmapFromArrayOfArraysByFieldName($balance_change_records,'process');
		$this->assertEquals(1.50,$bcr_hash['LoyaltyBalancePayment']['charge_amt'],"It should have the loyalty charge");

		$complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
		//$expected_grand_total_to_merchant = $base_order_data['grand_total'] - 1.50 - $order_data['tip'];
		$expected_grand_total_to_merchant = 0.00;
		$this->assertEquals($expected_grand_total_to_merchant,$complete_order['grand_total_to_merchant']);
		$discount_order_detail = array_pop($complete_order['order_details']);
		$this->assertEquals(-1.50,$discount_order_detail['item_total_w_mods']);
		$this->assertEquals(-.15,$discount_order_detail['item_tax']);
		$this->assertEquals(LoyaltyBalancePaymentService::DISCOUNT_NAME,$discount_order_detail['item_name']);

		// check to see if loyalty record was updated
		$ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));
		$this->assertEquals(7,$ubp_resource->points,"It should not ahve affected the points");
		$this->assertEquals(2.15,$ubp_resource->dollar_balance,'It should hae reduced the dollar balance by 1.50');

		// make sure user's balane is set to 0
		$user_resource = UserAdapter::getUserResourceFromId($user['user_id']);
		$this->assertEquals(0.00, $user_resource->balance,'Users balance should  be 0.00 after the order');
		return array("user_id"=>$user['user_id'],"merchant_id"=>$base_order_data['merchant_id']);
	}

	/**
	 * @depends testPayWithBalanceOption
	 */
	function testPlaceOrdeWithLoyaltyPaymentForPart($data)
	{
		$user_id = $data['user_id'];
		$merchant_id = $data['merchant_id'];
		$user = logTestUserIn($user_id);
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'note',3);

		$request = createRequestObject('/app2/apiv2/cart','post',json_encode($order_data),'application/json');
		$place_order_controller = new PlaceOrderController($mt, $user, $request,5);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$cart_resource = $place_order_controller->processV2Request();
		$this->assertNull($cart_resource->error);
		$cart_ucid = $cart_resource->ucid;

		$request = createRequestObject("/app2/apiv2/cart/$cart_ucid/checkout",'get');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$checkout_resource = $place_order_controller->processV2Request();

		$new_cart_note = "the new cart note";
		$order_data = array();
		$order_data['merchant_id'] = $merchant_id;
		$order_data['note'] = $new_cart_note;
		$order_data['user_id'] = $user_id;
		$order_data['cart_ucid'] = $cart_ucid;
		//$order_data['tip'] = (rand(100, 200))/100;
		$order_data['tip'] = 0.00;
		//$base_order_data['grand_total'] = $base_order_data['grand_total'] + $order_data['tip'];
		$payment_array = $checkout_resource->accepted_payment_types;
		$order_data['merchant_payment_type_map_id'] = $payment_array[2]['merchant_payment_type_map_id'];
		$lead_times_array = $checkout_resource->lead_times_array;
		$order_data['actual_pickup_time'] = $lead_times_array[0];
		// this should be ignored;
		$order_data['lead_time'] = 100000;

		$request = createRequestObject('/apiv2/orders','post',json_encode($order_data),'application/json');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$order_resource = $place_order_controller->processV2Request();
		$order_summary_json = json_encode($order_resource->order_summary);
		$this->assertNull($order_resource->error);

		$payment_service = $place_order_controller->getPaymentService();
		$tax_adjustment = $payment_service->tax_adjustment;
		$total_adjustment = 2.15 + $tax_adjustment;

		$expected_grand_total = 4.95 - $total_adjustment;
		$this->assertEquals($expected_grand_total,$order_resource->grand_total);
		$this->assertEquals($order_resource->grand_total,$order_resource->grand_total_to_merchant,"there should have been a grand total to merchant that was the difference");
		$order_id = $order_resource->order_id;

		// check to see that summary has CC payment only since rewards is pre-tax
		$order_summary = $order_resource->order_summary;
		$payment_items = $order_summary['payment_items'];
		$this->assertNotNull($payment_items,'there should be the payment items section in the summary');
		$payment_items_hash = createHashmapFromArrayOfArraysByFieldName($payment_items,'title');
		//$this->assertEquals('$2.15',$payment_items_hash['Loyalty Balance']['amount']);
		$this->assertEquals('$'.number_format($expected_grand_total,2),$payment_items_hash[CompleteOrder::CC_CHARGED_LABEL]['amount']);
		$this->assertNull($payment_items_hash['Loyalty Balance'],"there should not be a loyalty payment section");
		$this->assertCount(1,$payment_items,"there should be one payemnt item");

		//check to see if all records were added to balance change table
		$balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),'BalanceChangeAdapter');
		$bcr_hash = createHashmapFromArrayOfArraysByFieldName($balance_change_records,'process');
		$this->assertEquals(2.15,$bcr_hash['LoyaltyBalancePayment']['charge_amt'],"It should have the loyalty charge");

		$complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
		$discount_order_detail = array_pop($complete_order['order_details']);
		$this->assertEquals(-2.15,$discount_order_detail['item_total_w_mods']);
		$this->assertEquals(LoyaltyBalancePaymentService::DISCOUNT_NAME,$discount_order_detail['item_name']);

		// check to see if loyalty record was updated
		$ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));
		$this->assertEquals(0.00,$ubp_resource->dollar_balance,'It should hae reduced the dollar balance to zero');
		$expected_points = 7 + round($order_resource->order_amt);
		$this->assertEquals($expected_points,$ubp_resource->points,"It should have affected the points with the amount that was run on the CC");

        // check to see if history was updated
        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$order_id));
        $loyalty_history_hash_by_process = createHashmapFromArrayOfArraysByFieldName($ublh_records,'process');
        $this->assertNotNull($loyalty_history_hash_by_process[LoyaltyBalancePaymentService::REDEEM_PROCESS_NAME]);
        $this->assertNotNull($loyalty_history_hash_by_process['Order']);

        // finally check to see if the loyalty payment is excluded from the order message

		$message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id, 'E');
		$this->assertNotNull($message_resource);
		$message_controller = ControllerFactory::generateFromMessageResource($message_resource);
		$message_to_send_resource = $message_controller->prepMessageForSending($message_resource);
		$message_text = $message_to_send_resource->message_text;
		$this->assertNotContains(LoyaltyBalancePaymentService::getDiscountDisplay(),$message_text,"It shouldn't contain the rewards item in the order message");
		$this->assertNotContains(LoyaltyBalancePaymentService::DISCOUNT_NAME,$message_text,"It shouldn't contain the Discount item in the order message");
        return $order_id;
	}

	/**
	 * @depends testPlaceOrdeWithLoyaltyPaymentForPart
	 */
	function testRefundWithLoyaltyAsPartOfPayment($order_id)
    {
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
        $user_id = $complete_order['user_id'];
        $user = getStaticRecord(array("user_id"=>$user_id),'UserAdapter');
        $order_controller = new OrderController($mt, $user, $r, 5);
        $refund_results = $order_controller->issueOrderRefund($order_id, "0.00");
        $this->assertEquals("success", $refund_results['result']," should have gotten a success but: ".$refund_results['message']);
        $this->assertEquals(100,$refund_results['response_code']);

        $balance_change_resource = Resource::find(new BalanceChangeAdapter($mimetypes),''.$refund_results['balance_change_id']);
        $this->assertEquals($expected, $actual);
        $this->assertEquals($balance_change_resource->charge_amt, $complete_order['grand_total']);
        $this->assertEquals($balance_change_resource->process, 'CCvoid');
        $this->assertEquals($balance_change_resource->notes, 'Issuing a VioPaymentService VOID from the API: ');

        // now check to make sure $ were put back on and points earned were taken off
        $ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));
        $this->assertEquals(2.15,$ubp_resource->dollar_balance,'It should put the 2.15 back on the account');
        $this->assertEquals(7,$ubp_resource->points,"It should have taken teh points off that were earned");

        $ublha = new UserBrandLoyaltyHistoryAdapter($m);
        $history_transaction_resources = $ublha->getLoyaltyHistoryByOrderId($order_id);
        $this->assertCount(4,$history_transaction_resources,"there should be four histories");
        $this->assertEquals('Redeem-REVERSED',$history_transaction_resources[2]->process);
        $this->assertEquals('Order-REVERSED',$history_transaction_resources[3]->process);
        // last one should contain the current values
        $this->assertEquals(7,$history_transaction_resources[3]->current_points);
        $this->assertEquals(2.15,$history_transaction_resources[3]->current_dollar_balance);
        $this->assertEquals(0,$history_transaction_resources[2]->current_points);
        $this->assertEquals(0.00,$history_transaction_resources[2]->current_dollar_balance);


    }

	function testLoyaltyEarnedPtsAndBalancePtsWithSplickitCliffLoyaltyProgram()
	{

		$skin_resource = getOrCreateSkinAndBrandIfNecessary("xcliff2", "cliffbrand2", $skin_id, $brand_id);
		$brand_id = $skin_resource->brand_id;
		$brand_resource = Resource::find(new BrandAdapter($mimetypes),"$brand_id");
		$brand_resource->loyalty = 'Y';
		$brand_resource->save();

		$blr_data['brand_id'] = $brand_id;
		$blr_data['loyalty_type'] = 'splickit_cliff';
		$blr_data['cliff_value'] = 20;
        $blr_data['cliff_award_dollar_value'] = 3.00;
		$brand_loyalty_rules_resource = Resource::factory(new BrandLoyaltyRulesAdapter($m),$blr_data);
		$brand_loyalty_rules_resource->save();

		$ids['blr_resource'] = $brand_loyalty_rules_resource->getRefreshedResource();
		setContext($skin_resource->external_identifier);


		$user_resource = createNewUserWithCCNoCVV();
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user_resource->user_id;
		$user_session_controller = new UsersessionController($m,$user,$r,5);
		$user_session_controller->getUserSession($user_resource);

		$user_id = $user_id;
		$merchant_id = $this->ids['merchant_id'];
		$user = logTestUserIn($user_id);
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'note',3);

		$request = createRequestObject('/app2/apiv2/cart','post',json_encode($order_data),'application/json');
		$place_order_controller = new PlaceOrderController($mt, $user, $request,5);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$cart_resource = $place_order_controller->processV2Request();
		$this->assertNull($cart_resource->error);
		$cart_ucid = $cart_resource->ucid;

		$request = createRequestObject("/app2/apiv2/cart/$cart_ucid/checkout",'get');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$checkout_resource = $place_order_controller->processV2Request();
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,getTomorrowTwelveNoonTimeStampDenver());

		$loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext($user);

		$this->assertNotNull($order_resource->loyalty_earned_label, "should have the loyalty earned field");
		$this->assertEquals($loyalty_controller->getLoyaltyEarnedLabel(),$order_resource->loyalty_earned_label,'should have a loyalty earned label value equal to the loyalty controller setting');

		$this->assertNotNull($order_resource->loyalty_balance_label, "should have the loyalty balance field");
		$this->assertEquals($loyalty_controller->getLoyaltyBalanceLabel(),$order_resource->loyalty_balance_label,'should have a loyalty balance label value equal to the loyalty controller setting');

		$this->assertEquals("45 Points", $order_resource->loyalty_earned_message );
		$this->assertEquals("5 Points and $6.00", $order_resource->loyalty_balance_message );
	}

    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;
    	$_SERVER['request_time1'] = microtime(true);    	

		$skin_resource = getOrCreateSkinAndBrandIfNecessary("xcliff", "cliffbrand", $skin_id, $brand_id);
    	$brand_id = $skin_resource->brand_id;
    	$brand_resource = Resource::find(new BrandAdapter($mimetypes),"$brand_id");
    	$brand_resource->loyalty = 'Y';
    	$brand_resource->save();

		$blr_data['brand_id'] = $brand_id;
		$blr_data['loyalty_type'] = 'splickit_cliff';
		$blr_data['earn_value_amount_multiplier'] = 1;
		$blr_data['cliff_value'] = 10;
		$brand_loyalty_rules_resource = Resource::factory(new BrandLoyaltyRulesAdapter($m),$blr_data);
		$brand_loyalty_rules_resource->save();
		$ids['blr_resource'] = $brand_loyalty_rules_resource->getRefreshedResource();
        setContext($skin_resource->external_identifier);
        $ids['context'] = $skin_resource->external_identifier;
        $menu_id = createTestMenuWithNnumberOfItems(5);
        $ids['menu_id'] = $menu_id;

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        $ids['merchant_id'] = $merchant_id;

        $map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_id,"message_format"=>'GUA',"delivery_addr"=>"gprs","message_type"=>"X","info"=>"firmware=7.0"));
        $map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_id,"message_format"=>'FUA',"delivery_addr"=>"1234567890","message_type"=>"O"));

        $_SERVER['log_level'] = 5;
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
    LoyaltyCliffTest::main();
}

?>