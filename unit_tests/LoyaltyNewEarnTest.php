<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class LoyaltyNewEarnTest extends PHPUnit_Framework_TestCase
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

    function testFailAuthAndDoAuthReversalWithFailure()
    {
        setProperty('check_cvv',"true");
        setProperty("force_void_fail","true");

        $menu_id = $this->ids['menu_id'];

        $merchant_resource = createNewTestMerchant($menu_id,array("authorize"=>true));
        $merchant_id = $merchant_resource->merchant_id;
        attachMerchantToSkin($merchant_resource->merchant_id, getSkinIdForContext());

        //$billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);
        $billing_entity_resource = Resource::find(new BillingEntitiesAdapter(),$merchant_resource->cc_billing_entity_id);

        $merchant_payment_type_map_adapter = new MerchantPaymentTypeMapsAdapter($mimetypes);
        $cc_merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);

        // create cash merchant payment type record
        $cash_merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, null);

        // loyalty merchant payment type record
        $merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 8000, $billing_entity_resource->id);

        $user_resource = createNewUserWithCC();
        $user_resource->uuid = substr($user_resource->uuid,0,17).'NOCVV';
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user_resource->user_id;
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session_controller->getUserSession($user_resource);

        $ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));
        $ubp_resource->dollar_balance = .50;
        $ubp_resource->save();

        $cart_data = OrderAdapter::getSuperSimpleCartArrayByMerchantId($merchant_id,'pickup','note',1);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $loyalty_payment_plus_card = $checkout_resource->accepted_payment_types[2];
        $checkout_resource->accepted_payment_types = array($loyalty_payment_plus_card);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$time);
        $this->assertNotNull($order_resource->error);

        $ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));
        $this->assertEquals(.50,$ubp_resource->dollar_balance);

        return $checkout_resource->oid_test_only;
//        $bcrs = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$checkout_resource->oid_test_only),'BalanceChangeAdapter');
//        $bcr_hash = createHashmapFromArrayOfArraysByFieldName($bcrs,'process');
//        $this->assertFalse(isset($bcr_hash['CCrefund']),"We should never do a CC refund on an authorization reversal failure");
    }

    /**
     * @depends testFailAuthAndDoAuthReversalWithFailure
     */
    function testBalanceChangeRecordsOnAuthReversalFailure($order_id)
    {
        $bcrs = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),'BalanceChangeAdapter');
        $this->assertCount(2,$bcrs,"there should only be 2 bc records for the order");
    }

    /**
     * @depends testFailAuthAndDoAuthReversalWithFailure
     */
    function testNoCCrefundOnAuthReversalFailure($order_id)
    {
        $bcrs = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),'BalanceChangeAdapter');
        $bcr_hash = createHashmapFromArrayOfArraysByFieldName($bcrs,'process');
        $this->assertFalse(isset($bcr_hash['CCrefund']),"We should never do a CC refund on an authorization reversal failure");
    }

    /**
     * @depends testFailAuthAndDoAuthReversalWithFailure
     */
    function testCancelAuthorizationAfterAuthorizationRefersalFailure($order_id)
    {
        $bcrs = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),'BalanceChangeAdapter');
        $bcr_hash = createHashmapFromArrayOfArraysByFieldName($bcrs,'process');
        $this->assertEquals('authreversal-fail',$bcr_hash['Authorize']['notes']);
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
	function testEarn($user_resource)
	{
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user_resource->user_id;
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours',4);
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $this->assertNull($order_resource->error);
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000);
		$ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
		$this->assertTrue($order_resource->order_amt > 1.00);
		$first_order_amt = $order_resource->order_amt;
		$ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
		$this->assertEquals(round(10*$first_order_amt),$ubpm_record['points'],"Shouljd have points");
		$this->assertEquals(($order_resource->order_amt)/10,$ubpm_record['dollar_balance'],'should have a dollar value equal to 10% of what was spent');

		$ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
		$ublh_records = $ublh_adapter->getRecords(array("order_id"=>$order_id));
		$this->assertCount(1,$ublh_records,"There should be 1 record for this order");
		$hash = createHashmapFromArrayOfArraysByFieldName($ublh_records,'process');
		$this->assertEquals($ubpm_record['points'],$hash['Order']['points_added'],'It should have the points earned');
		$this->assertEquals($ubpm_record['points'],$hash['Order']['current_points']);
		$this->assertEquals($ubpm_record['dollar_balance'],$hash['Order']['current_dollar_balance']);
		

		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours',4);
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$second_order_amt = $order_resource->order_amt;
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000);
		$ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
		$this->assertTrue($order_resource->order_amt > 1.00);
		$ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
		$this->assertEquals(round(10*$first_order_amt)+round(10*$second_order_amt),$ubpm_record['points'],"Should have 10xOrderAmt points");
		$total_expected_dollar_amount = ($first_order_amt+$second_order_amt)/10;
		$this->assertEquals($total_expected_dollar_amount,$ubpm_record['dollar_balance'],'Shoul have the expected dollare balance from the 2 orders');
		
		$this->assertEquals(120,$ubpm_record['points'],"Should have points");
		$total_expected_dollar_amount = ($first_order_amt/10)+($second_order_amt/10);
		$this->assertEquals($total_expected_dollar_amount,$ubpm_record['dollar_balance'],'should have dollar value equal to the two orders together');
		$expected_earn = number_format(($order_resource->order_amt)*10,0);
		$this->assertEquals("$expected_earn Points",$order_resource->loyalty_earned_message);
		$this->assertEquals("$".number_format($total_expected_dollar_amount, 2),$order_resource->loyalty_balance_message);

		return $user_id;
	}

	/**
	 * @depends testEarn
	 */
	function testLoyaltyHistoryDisplayValues($user_id)
	{
		$user = logTestUserIn($user_id);
		$lc = new LoyaltyController($m,$user,$request);
		$history = $lc->getLoyaltyHistory();
		foreach ($history as $record) {
			$this->assertCount(sizeof($lc->getLoyaltyHistoryHeadings()),$record);
		}
	}

	function testMultipleEarn()
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
		$ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
		$expected_dollar_value = $order_resource->order_amt/10;
		$this->assertEquals($expected_dollar_value,$ubpm_record['dollar_balance'],'should have a dollar value equal to the number of times 10 goes into the order amount');
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
		$ubp_resource->save();

		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_resource->merchant_id, 'pickup', 'the note');
		
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
		$user_resource = Resource::find(new UserAdapter($m),''.$base_order_data['user_id']);
		$user_resource->flags = '1000000001';
		$user_resource->save();
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

		$request = createRequestObject('/apiv2/orders/'.$base_order_data['ucid'],'post',json_encode($order_data),'application/json');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$order_resource = $place_order_controller->processV2Request();
		$this->assertNotNull($order_resource->error,"It should throw an error because no CC on file");
        $this->assertEquals(LoyaltyBalancePaymentService::NO_CC_ON_FILE_FOR_LOYALTY_PAYMENT_MESSAGE,$order_resource->error);


		$user_resource->flags = '1C20000001';
		$user_resource->save();
		$user = logTestUserIn($user_resource->user_id);

		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$order_resource = $place_order_controller->processV2Request();
		$this->assertNull($order_resource->error);

		$order_summary = $order_resource->order_summary;
		$cart_items = $order_summary['cart_items'];
		$cart_items_hash = createHashmapFromArrayOfArraysByFieldName($cart_items,'item_name');
		$this->assertNotNull($cart_items_hash[LoyaltyBalancePaymentService::DISCOUNT_NAME],"it should have had the rewards used row");
		
		
		$this->assertEquals(0.00,$order_resource->total_tax_amt,"there should not have been tax");
		$this->assertEquals(0.00,$order_resource->grand_total,"there should have been a grand total equal to the subtotal only");
		$this->assertEquals(0.00,$order_resource->grand_total_to_merchant,"there should not have been a gPlacerand total to merchant");
		$order_id = $order_resource->order_id;

		//check to see if all records were added to balance change table
		$balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),'BalanceChangeAdapter');
		$bcr_hash = createHashmapFromArrayOfArraysByFieldName($balance_change_records,'process');
		$this->assertEquals(1.50,$bcr_hash['LoyaltyBalancePayment']['charge_amt'],"It should have the loyalty charge");
		$this->assertNull($bcr_hash['CCpayment'],"There shoudl not have been a CC row");

		$user_record = UserAdapter::staticGetRecordByPrimaryKey($user['user_id'],'UserAdapter');
		$this->assertEquals(0.00,$user_record['balance'],"balance shouldp be at zero");


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
		$this->assertEquals(2.15,$ubp_resource->dollar_balance,'It should hae reduced the dollar balance by 1.50');
		$this->assertEquals(215,$ubp_resource->points);


		$ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
		$ublh_records = $ublh_adapter->getRecords(array("order_id"=>$order_id));
		$this->assertCount(1,$ublh_records,"There should be 2 records for this order");
		$hash = createHashmapFromArrayOfArraysByFieldName($ublh_records,'process');
		$this->assertEquals(150,$hash[LoyaltyBalancePaymentService::REDEEM_PROCESS_NAME]['points_redeemed'],'It should have the redeem balance transaction');
		$this->assertEquals(215,$hash[LoyaltyBalancePaymentService::REDEEM_PROCESS_NAME]['current_points']);
		$this->assertEquals(2.15,$hash[LoyaltyBalancePaymentService::REDEEM_PROCESS_NAME]['current_dollar_balance']);

		return array("user_id"=>$user['user_id'],"merchant_id"=>$base_order_data['merchant_id']);
	}

	/**
	 * @depends testPayWithBalanceOption
	 */
	function testLoyaltyHistoryRedeem($data)
	{
		$user = logTestUserIn($data['user_id']);
		$lc = new LoyaltyController($m,$user,$request);
		$history = $lc->getLoyaltyHistory();
		$this->assertCount(1,$history);
		$this->assertEquals("Spent 150 points.",$history[0]['description']);
		$this->assertEquals('$2.15',$history[0]['amount']);
	}

	/**
	 * @depends testPayWithBalanceOption
	 */
	function testPlaceOrdeWithLoyaltyPaymentForPart($data)
	{
		$user_id = $data['user_id'];

        $starting_ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));

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
		$order_data['note'] = $new_cart_note;
		//$order_data['tip'] = (rand(100, 200))/100;
		$order_data['tip'] = 0.00;
		//$base_order_data['grand_total'] = $base_order_data['grand_total'] + $order_data['tip'];
		$payment_array = $checkout_resource->accepted_payment_types;
		$order_data['merchant_payment_type_map_id'] = $payment_array[2]['merchant_payment_type_map_id'];
		$lead_times_array = $checkout_resource->lead_times_array;
		$order_data['actual_pickup_time'] = $lead_times_array[0];
		// this should be ignored;
		$order_data['lead_time'] = 100000;

		$request = createRequestObject("/apiv2/orders/$cart_ucid",'post',json_encode($order_data),'application/json');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$order_resource = $place_order_controller->processV2Request();
		$this->assertNull($order_resource->error);

		$order_summary = $order_resource->order_summary;
		$receipt_items_hash = createHashmapFromArrayOfArraysByFieldName($order_summary['receipt_items'],'title');
		$this->assertEquals("$2.35",$receipt_items_hash['Subtotal']['amount']);
		$this->assertEquals("$0.24",$receipt_items_hash['Tax']['amount']);
		$this->assertEquals("$2.59",$receipt_items_hash['Total']['amount']);

		$payment_items = $order_summary['payment_items'];
		$this->assertNotNull($payment_items,'there should be the payment items section in the summary');
		$payment_items_hash = createHashmapFromArrayOfArraysByFieldName($payment_items,'title');
		//$this->assertEquals('$2.15',$payment_items_hash['Loyalty Balance']['amount']);
		$this->assertEquals('$'.number_format($order_resource->grand_total,2),$payment_items_hash[CompleteOrder::CC_CHARGED_LABEL]['amount']);
		$this->assertCount(1,$payment_items,"there should be one payemnt item");

		$loyalty_payment_service = $place_order_controller->getPaymentService();
		$expected_grand_total_to_merchant = $checkout_resource->order_amt + $checkout_resource->total_tax_amt - 2.15 - $loyalty_payment_service->tax_adjustment;
		$this->assertEquals($expected_grand_total_to_merchant,$order_resource->grand_total_to_merchant,"there should have been a grand total to merchant that was the difference");
		$order_id = $order_resource->order_id;

		//check to see if all records were added to balance change table
		$balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),'BalanceChangeAdapter');
		$bcr_hash = createHashmapFromArrayOfArraysByFieldName($balance_change_records,'process');
		$this->assertEquals(2.15,$bcr_hash['LoyaltyBalancePayment']['charge_amt'],"It should have the loyalty charge");

		$complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
		$discount_order_detail = array_pop($complete_order['order_details']);
		$this->assertEquals(-2.15,$discount_order_detail['item_total_w_mods']);
		$this->assertEquals(LoyaltyBalancePaymentService::DISCOUNT_NAME,$discount_order_detail['item_name']);
		$this->assertEquals($complete_order['total_tax_amt'],number_format($complete_order['item_tax_amt'],2),"the item tax amount should include the loyalty tax discount");
		$this->assertEquals(.235,$complete_order['item_tax_amt']);

		// check to see if loyalty record was updated
		$ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));
		$this->assertEquals(0.24,$ubp_resource->dollar_balance,'It should hae reduced the dollar balance to zero bu then added the .24 for the 2.40 spend on the CC');
		$this->assertEquals(24,$ubp_resource->points,"It should have 24 points.");

		return $order_id;
	}

	/**
	 * @depends testPlaceOrdeWithLoyaltyPaymentForPart
	 */
	function testLoyaltyHistory($order_id)
	{
		$ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
		$ublh_records = $ublh_adapter->getRecords(array("order_id"=>$order_id));
		$this->assertCount(2,$ublh_records,"There should be 2 records for this order");
		$hash = createHashmapFromArrayOfArraysByFieldName($ublh_records,'process');
		$this->assertEquals(215.00,$hash[LoyaltyBalancePaymentService::REDEEM_PROCESS_NAME]['points_redeemed'],'It should have the redeem balance transaction');
		$this->assertEquals(0,$hash[LoyaltyBalancePaymentService::REDEEM_PROCESS_NAME]['current_points_balance']);
		$this->assertEquals(0.00,$hash[LoyaltyBalancePaymentService::REDEEM_PROCESS_NAME]['current_dollar_balance']);
		$this->assertEquals(24,$hash['Order']['points_added'],'It should have the 24 points earned');
		$this->assertEquals(24,$hash['Order']['current_points']);
		$this->assertEquals(0.24,$hash['Order']['current_dollar_balance']);
        return $order_id;
	}

    /**
     * @depends testLoyaltyHistory
     */
    function testRefundOrder($order_id)
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
        $this->assertEquals(2.15,$ubp_resource->dollar_balance,'It should go back to the starting balance of 2.15');
        $this->assertEquals(215,$ubp_resource->points,"It should go back to the starting points balance of 215");

        $ublha = new UserBrandLoyaltyHistoryAdapter($m);
        $history_transaction_resources = $ublha->getLoyaltyHistoryByOrderId($order_id);
        $this->assertCount(4,$history_transaction_resources,"there should be four histories");
        $this->assertCount(4,$history_transaction_resources,"there should be four histories");
        $this->assertEquals('Redeem-REVERSED',$history_transaction_resources[2]->process);
        $this->assertEquals('Order-REVERSED',$history_transaction_resources[3]->process);
        // last one should contain the current values
        $this->assertEquals(215,$history_transaction_resources[3]->current_points);
        $this->assertEquals(2.15,$history_transaction_resources[3]->current_dollar_balance);
        //intermediate should show 0's
        $this->assertEquals(0,$history_transaction_resources[2]->current_points);
        $this->assertEquals(0.00,$history_transaction_resources[2]->current_dollar_balance);

    }


	/**
	 * @depends testPayWithBalanceOption
	 */
	function testPlaceOrdeWithLoyaltyPaymentForPartButFailCCpayment($data)
	{
		$merchant_id = $data['merchant_id'];
		$user_resource = createNewUserWithCCNoCVV();
		$user_resource->uuid = '12340-56789-INVLD-NUMBR';
		$user_resource->save();
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user['user_id'];
		$user_session_controller = new UsersessionController($m,$user,$r,5);
		$user_session = $user_session_controller->getUserSession($user_resource);
		$user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
		$ubpr = Resource::find($user_brand_points_map_adapter,null,array(TONIC_FIND_BY_METADATA => array("user_id"=>$user['id'],"brand_id"=>getBrandIdFromCurrentContext())));
		$ubpr->dollar_balance = 3.00;
		$ubpr->save();


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
		$this->assertNotNull($order_resource->error);

		$ubpm_record = $user_brand_points_map_adapter->getRecordFromPrimaryKey($ubpr->map_id);
		$this->assertEquals(3.00,$ubpm_record['dollar_balance']);

		$records = UserBrandLoyaltyHistoryAdapter::staticGetRecords(array("user_id"=>$user_id),'UserBrandLoyaltyHistoryAdapter');
		$this->assertCount(0, $records);
	}

	function testCreateOrderConfirmationEmailDefaultTemplate()
	{
		$menu_id = $this->ids['menu_id'];

		$merchant_resource = createNewTestMerchant($menu_id);
		attachMerchantToSkin($merchant_resource->merchant_id, getSkinIdForContext());

		$billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);

		$merchant_payment_type_map_adapter = new MerchantPaymentTypeMapsAdapter($mimetypes);
		$merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);

		// create cash merchant payment type record
		$merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);

		// loyalty merchant payment type record
		$merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 8000, $billing_entity_id);

		$user_resource = createNewUserWithCCNoCVV();
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user_resource->user_id;
		$user_session_controller = new UsersessionController($m,$user,$r,5);
		$user_session_controller->getUserSession($user_resource);

		$ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));
		$ubp_resource->dollar_balance = 3.65;
		$ubp_resource->save();

		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_resource->merchant_id, 'pickup', 'the note', 5);
		$order_data['lead_time'] = 60;
		$response = placeOrderFromOrderData($order_data, getTomorrowTwelveNoonTimeStampDenver());
		$order_id = $response->order_id;
		$this->assertTrue($order_id > 1000,"should have created a valid order");

		$mmha = new MerchantMessageHistoryAdapter();
		$message_resource = $mmha->getExactResourceFromData(array("order_id"=>$order_id,"message_format"=>'Econf'));
		$this->assertTrue(is_a($message_resource, 'Resource'));
		$message_text = $message_resource->message_text;
		$this->assertTrue(substr_count($message_text, 'Subtotal') == 1,"should have only found 1 subtotal");
		$this->assertTrue(substr_count($message_text, 'default splickit order confirmation template') == 1,"It should be the defaul message template");
		$this->assertTrue(substr_count($message_text, 'Congratulations! You just earned') == 1,"It Should have the message 'Congratulations! You just earned'");
	}


	function testCreateOrderConfirmationEmailDefaultTemplateWithPromo()
	{
		$menu_id = $this->ids['menu_id'];

		$merchant_resource = createNewTestMerchant($menu_id);
		$brand_id = getBrandIdFromCurrentContext();
		attachMerchantToSkin($merchant_resource->merchant_id, getSkinIdForContext());

		$billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);

		$merchant_payment_type_map_adapter = new MerchantPaymentTypeMapsAdapter($mimetypes);
		$merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);

		// create cash merchant payment type record
		$merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);

		// loyalty merchant payment type record
		$merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 8000, $billing_entity_id);

		$promo_adapter = new PromoAdapter($mimetypes);
		$pkwm_adapter = new PromoKeyWordMapAdapter($mimetypes);
		$sql = "INSERT INTO `Promo` VALUES(201, 'The Type1 Promo', 'Get 25% off', 1, 'Y', 'N', 0, 2, 'N', 'N','all', '2010-01-01', '2020-01-01', 1, 0, 0, 0.00, 0, 0.00, 'Y', 'N',0,$brand_id, NOW(), NOW(), 'N')";
		$promo_adapter->_query($sql);

		Resource::createByData(new PromoMerchantMapAdapter($mimetypes), array("merchant_id"=>$merchant_resource->merchant_id,"promo_id"=>201));
		$sql = "INSERT INTO `Promo_Message_Map` VALUES(null, 201, 'Congratulations! You''re getting a 25% off your order!', NULL, NULL, NULL, NULL, now())";
		$promo_adapter->_query($sql);
		$sql = "INSERT INTO `Promo_Type1_Amt_Map` VALUES(null, 201, 1.00, 0.00, 25,50.00, NOW())";
		$promo_adapter->_query($sql);

		Resource::createByData($pkwm_adapter, array("promo_id"=>201,"promo_key_word"=>"type1promo","brand_id"=>$merchant_resource->brand_id));


		$user_resource = createNewUserWithCCNoCVV();
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user_resource->user_id;
		$user_session_controller = new UsersessionController($m,$user,$r,5);
		$user_session_controller->getUserSession($user_resource);

		$ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));
		$ubp_resource->dollar_balance = 3.65;
		$ubp_resource->save();

		$order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_resource->merchant_id,'pickup','the note',4);
		$order_data['submitted_order_type'] = 'pickup';
		$json_encoded_data = json_encode($order_data);
		$request = createRequestObject('/app2/apiv2/cart/checkout', "POST", $json_encoded_data, 'application/json');

		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$response = $place_order_controller->processV2Request();

		$promo_request = createRequestObject("/app2/apiv2/cart/$response->cart_ucid/checkout?promo_code=type1promo", "GET", $bd, 'application/json');
		$promo_place_order_controller = new PlaceOrderController($mt, $user, $promo_request);
		$promo_place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$promo_resource_result = $promo_place_order_controller->processV2Request();

		$this->assertNull($promo_resource_result->error);

		$submit_order_data['merchant_id'] = $merchant_resource->merchant_id;
		$submit_order_data['note'] = "the new cart note";
		$submit_order_data['user_id'] = $user['user_id'];
		$submit_order_data['cart_ucid'] = $response->cart_ucid;
		$submit_order_data['tip'] = 0;
		$payment_array = $promo_resource_result->accepted_payment_types;
		$submit_order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];

		$lead_times_array = $promo_resource_result->lead_times_array;
		$first_time = $lead_times_array[0];

		$submit_order_data['requested_time'] = $first_time;

		$json_encoded_data = json_encode($submit_order_data);
		$submit_request = createRequestObject("/apiv2/orders/$response->cart_ucid","POST",$json_encoded_data,'application/json');
		$place_order_controller = new PlaceOrderController($mt, getAuthenticatedUser(), $submit_request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$order_resource = $place_order_controller->processV2Request();

		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000,"should have created a valid order");

		$mmha = new MerchantMessageHistoryAdapter();
		$message_resource = $mmha->getExactResourceFromData(array("order_id"=>$order_id,"message_format"=>'Econf'));
		$this->assertTrue(is_a($message_resource, 'Resource'));
		$message_text = $message_resource->message_text;
		$this->assertTrue(substr_count($message_text, 'Subtotal') == 1,"should have only found 1 subtotal");
		$subtotal_pos = strpos($message_text, 'Subtotal');
		$discount_pos = strpos($message_text, 'Promo Discount');
		$this->assertTrue($discount_pos > $subtotal_pos, "Promo discount should show after the subtotal");
		$this->assertTrue(substr_count($message_text, 'default splickit order confirmation template') == 1,"It should be the defaul message template");
		$this->assertTrue(substr_count($message_text, 'Congratulations! You just earned') == 1,"It Should have the message 'Congratulations! You just earned'");
	}


	function testCreateOrderConfirmationEmailCustomTemplate()
	{
		$brand_id = 112;
		$brand_resource = Resource::find(new BrandAdapter($mimetypes),"$brand_id");
		$brand_resource->loyalty = 'Y';
		$brand_resource->save();
		setContext("com.splickit.moes");
		Resource::createByData(new BrandLoyaltyRulesAdapter($m),array("brand_id"=>getBrandIdFromCurrentContext(),"loyalty_type"=>'remote'));

		$menu_id = $this->ids['menu_id'];

		$merchant_resource = createNewTestMerchant($menu_id);
		attachMerchantToSkin($merchant_resource->merchant_id, getSkinIdForContext());

		$billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);
		$billing_entity_id = $billing_entity_resource->id;

		$merchant_payment_type_map_adapter = new MerchantPaymentTypeMapsAdapter($mimetypes);

		$merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_id);

		// create cash merchant payment type record
		$merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);

		// loyalty merchant payment type record
		$merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 8000, $billing_entity_id);

		$user_resource = createNewUserWithCCNoCVV();
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user_resource->user_id;
		$user_session_controller = new UsersessionController($m,$user,$r,5);
		$user_session_controller->getUserSession($user_resource);
		$user_brand_points_map = new UserBrandPointsMapAdapter($m);
		$user_brand_points_map->createUserLoyaltyAccount($user_id, $brand_id);

		$ubp_resource = Resource::find($user_brand_points_map,"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));
		$ubp_resource->dollar_balance = 13.65;
		$ubp_resource->save();

		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_resource->merchant_id, 'pickup', 'the note', 5);
		$order_data['lead_time'] = 60;

		$response = placeOrderFromOrderData($order_data, getTomorrowTwelveNoonTimeStampDenver());
		$order_id = $response->order_id;
		$this->assertTrue($order_id > 1000,"should have created a valid order");

		$mmha = new MerchantMessageHistoryAdapter();
		$records = $mmha->getRecords(array("order_id"=>$order_id));
		$message_resource = $mmha->getExactResourceFromData(array("order_id"=>$order_id,"message_format"=>'Econf'));
		$this->assertTrue(is_a($message_resource, 'Resource'));
		$message_text = $message_resource->message_text;
		$this->assertTrue(substr_count($message_text, 'Subtotal') == 1,"should have only found 1 subtotal");
		$this->assertTrue(substr_count($message_text, 'default splickit order confirmation template') < 1, "not coantains default template name");
		$this->assertTrue(substr_count($message_text, 'this is the moes template') == 1, "Contains moes name template");
		//$this->assertTrue(substr_count($message_text, 'Congratulations! You just earned') == 1, "It should show point earned message");
	}

	function testLoyaltyEarnedPtsAndBalancePtsWithSplickitEarnLoyaltyProgram()
	{
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
		$this->assertEquals("$0.45", $order_resource->loyalty_balance_message );
	}

	function testLoyaltyAndPromoPartialAmount()
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
		$ubp_resource->save();

		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_resource->merchant_id, 'pickup', 'the note');

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

		//now apply promo
		$request = createRequestObject("/app2/apiv2/cart/$cart_ucid/checkout?promo_code=Alternatekeyword",'get');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$checkout_resource_after_promo = $place_order_controller->processV2Request();
		$this->assertEquals(0.00,$checkout_resource_after_promo->order_amt + $checkout_resource_after_promo->promo_amt,"there should be a zero balance due after the promor");
		$payment_array = $checkout_resource_after_promo->accepted_payment_types;
		$this->assertCount(2,$payment_array,'there should be 2 choices in the payment array');
		$payment_hash = createHashmapFromArrayOfArraysByFieldName($payment_array,'splickit_accepted_payment_type_id');
		$this->assertNull($payment_hash['8000']);

		//now add more things to the cart
		$order_data = $order_adapter->getSimpleCartArrayByMerchantId($merchant_resource->merchant_id,'pickup','the note',4);
		$request = createRequestObject("/app2/apiv2/cart/$cart_ucid",'post',json_encode($order_data),'application/json');
		$place_order_controller = new PlaceOrderController($mt, $user, $request,5);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$cart_resource = $place_order_controller->processV2Request();

		$request = createRequestObject("/app2/apiv2/cart/$cart_ucid/checkout",'get');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$checkout_resource = $place_order_controller->processV2Request();

		$payment_array = $checkout_resource->accepted_payment_types;
		$this->assertCount(3,$payment_array,'there should be 3 choices in the payment array');
		$payment_hash = createHashmapFromArrayOfArraysByFieldName($payment_array,'splickit_accepted_payment_type_id');
		$loyalty_payment_choice = $payment_hash['8000'];
		$this->assertEquals('Use $3.65 Loyalty Rewards ('.LoyaltyBalancePaymentService::BALANCE_ON_CARD_TEXT.')',$loyalty_payment_choice['name']);
        // force use of card
        $checkout_resource->accepted_payment_types = array(array_pop($payment_array));
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_resource->merchant_id,0.00,$tip);
        $this->assertNull($order_resource->error);

        $this->assertEquals("-3.00",$order_resource->promo_amt,"promo amount should be full");
        $this->assertEquals("0.94",$order_resource->grand_total_to_merchant,"Should be a .94 grand total");
        $order_summary_total = $order_resource->order_summary['receipt_items'][3];
        $this->assertEquals("$0.94",$order_summary_total['amount'],"Should be a .94 total");

        $complete_order = CompleteOrder::staticGetCompleteOrder($order_resource->order_id,$m);
        $loyalty_redemption_record = array_pop($complete_order['order_details']);
        $this->assertEquals("-3.65",$loyalty_redemption_record['item_total_w_mods'],"there it shoudl  have used the entire 3.65 of the loyalty");
        $this->assertEquals("-0.365",$loyalty_redemption_record['item_tax']);
        $this->assertEquals(3.85,$complete_order['order_amt'],"Order subtotal should now be the 3.85");

        $balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_resource->order_id),'BalanceChangeAdapter');
        $this->assertCount(3,$balance_change_records,"It should have 3 balance change records");
        $lp_hash = createHashmapFromArrayOfArraysByFieldName($balance_change_records,'process');
        $this->assertNotNull($lp_hash['LoyaltyBalancePayment'],"It should have a LoyaltyBalancePayment row");
        $this->assertEquals("3.65",$lp_hash['LoyaltyBalancePayment']['charge_amt']);
        $this->assertNotNull($lp_hash['CCpayment'],"It should have a cc payment row");
        $this->assertEquals("0.94",$lp_hash['CCpayment']['charge_amt']);
        return $merchant_resource;
	}

    /**
     * @depends testLoyaltyAndPromoPartialAmount
     */
    function testLoyaltyAndPromoFullAmount($merchant_resource)
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user_resource->user_id;
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session_controller->getUserSession($user_resource);

        $ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));
        $ubp_resource->dollar_balance = 5.00;
        $ubp_resource->save();

        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_resource->merchant_id, 'pickup', 'the note');

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

        //now apply promo
        $request = createRequestObject("/app2/apiv2/cart/$cart_ucid/checkout?promo_code=Alternatekeyword",'get');
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource_after_promo = $place_order_controller->processV2Request();
        $this->assertEquals(0.00,$checkout_resource_after_promo->order_amt + $checkout_resource_after_promo->promo_amt,"there should be a zero balance due after the promor");
        $payment_array = $checkout_resource_after_promo->accepted_payment_types;
        $this->assertCount(2,$payment_array,'there should be 2 choices in the payment array');
        $payment_hash = createHashmapFromArrayOfArraysByFieldName($payment_array,'splickit_accepted_payment_type_id');
        $this->assertNull($payment_hash['8000']);

        //now add more things to the cart
        $order_data = $order_adapter->getSimpleCartArrayByMerchantId($merchant_resource->merchant_id,'pickup','the note',4);
        $request = createRequestObject("/app2/apiv2/cart/$cart_ucid",'post',json_encode($order_data),'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request,5);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $cart_resource = $place_order_controller->processV2Request();

        $request = createRequestObject("/app2/apiv2/cart/$cart_ucid/checkout",'get');
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();

        $payment_array = $checkout_resource->accepted_payment_types;
        $this->assertCount(3,$payment_array,'there should be 3 choices in the payment array');
        $payment_hash = createHashmapFromArrayOfArraysByFieldName($payment_array,'splickit_accepted_payment_type_id');
        $loyalty_payment_choice = $payment_hash['8000'];
        $this->assertEquals('Use $4.50 Loyalty Rewards ('.LoyaltyBalancePaymentService::BALANCE_ON_CARD_TEXT.')',$loyalty_payment_choice['name']);
        // force use of card
        $checkout_resource->accepted_payment_types = array(array_pop($payment_array));
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_resource->merchant_id,0.00,$tip);
        $this->assertNull($order_resource->error);

        $this->assertEquals("-3.00",$order_resource->promo_amt,"promo amount should be full");
        $this->assertEquals("0.00",$order_resource->grand_total_to_merchant,"Should be a zero grand total");
        $order_summary_total = $order_resource->order_summary['receipt_items'][3];
        $this->assertEquals("$0.00",$order_summary_total['amount'],"Should be a zero total");

        $complete_order = CompleteOrder::staticGetCompleteOrder($order_resource->order_id,$m);
        $loyalty_redemption_record = array_pop($complete_order['order_details']);
        $this->assertEquals("-4.50",$loyalty_redemption_record['item_total_w_mods'],"there it shoudl only have used 4.50 of the loyalty");
        $this->assertEquals("-0.45",$loyalty_redemption_record['item_tax'],"there should be no tax reduction because balance is now zero");
        $this->assertEquals(3,$complete_order['order_amt'],"Order subtotal should now be the 3.00 that will be negated by the promo");

        $balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_resource->order_id),'BalanceChangeAdapter');
        $this->assertCount(2,$balance_change_records,"It should have 2 balance change records");
        $lp_hash = createHashmapFromArrayOfArraysByFieldName($balance_change_records,'process');
        $this->assertNotNull($lp_hash['LoyaltyBalancePayment'],"It should have a LoyaltyBalancePayment row");
        $this->assertEquals("4.50",$lp_hash['LoyaltyBalancePayment']['charge_amt']);
        return $merchant_resource;
    }

	/**
	 * @depends testLoyaltyAndPromoPartialAmount
	 */
	function testLoyaltyEarnWithPromo($merchant_resource)
	{
		$user_resource = createNewUserWithCCNoCVV();
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user_resource->user_id;
		$user_session_controller = new UsersessionController($m,$user,$r,5);
		$user_session_controller->getUserSession($user_resource);

		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_resource->merchant_id, 'pickup', 'the note',5);

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

		//now apply promo
		$request = createRequestObject("/app2/apiv2/cart/$cart_ucid/checkout?promo_code=Alternatekeyword",'get');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$checkout_resource_after_promo = $place_order_controller->processV2Request();

		$order_resource = placeOrderFromCheckoutResource($checkout_resource_after_promo,$user,$merchant_resource->merchant_id,0.00,$time);
		$this->assertNull($order_resource->error);
		$this->assertEquals('45 Points',$order_resource->loyalty_earned_message);

		$ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));
		$this->assertEquals(45,$ubp_resource->points,"It should have 45 points");

	}


	/**
	 * @depends testLoyaltyAndPromoPartialAmount
	 */
	function testLoyaltyPlusCash($merchant_resource)
	{
		$merchant_id = $merchant_resource->merchant_id;

		// loyalty + cash merchant payment type record
		$merchant_payment_type_map_adapter = new MerchantPaymentTypeMapsAdapter($m);
		$merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_id, 9000, $billing_entity_id);

		//$user_resource = createNewUserWithCCNoCVV();
        $user_resource = createNewUser();
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user_resource->user_id;
		$user_session_controller = new UsersessionController($m,$user,$r,5);
		$user_session_controller->getUserSession($user_resource);

		$ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m),"",array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext())));
		$ubp_resource->dollar_balance = 1.95;
		$ubp_resource->save();

		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleCartArrayByMerchantId($merchant_resource->merchant_id, 'pickup', 'the note',3);

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
		$ucid = $checkout_resource->cart_ucid;
		$payment_array = $checkout_resource->accepted_payment_types;
		$this->assertCount(4,$payment_array,'there should be 4 choices in the payment array');
		$payment_hash = createHashmapFromArrayOfArraysByFieldName($payment_array,'splickit_accepted_payment_type_id');
		$loyalty_payment_choice = $payment_hash['9000'];
		$this->assertEquals('Use $1.95 Loyalty Rewards ('.LoyaltyBalancePaymentService::BALANCE_WITH_CASH_TEXT.')',$loyalty_payment_choice['name']);
		$loyalty_payment_choice = $payment_hash['8000'];
		$this->assertEquals('Use $1.95 Loyalty Rewards ('.LoyaltyBalancePaymentService::BALANCE_ON_CARD_TEXT.')',$loyalty_payment_choice['name']);

		$new_cart_note = "the new cart note";
		$order_data = array();
		$order_data['merchant_id'] = $merchant_id;
		$order_data['note'] = $new_cart_note;
		$order_data['user_id'] = $user_id;
		$order_data['cart_ucid'] = $ucid;
		//$order_data['tip'] = (rand(100, 200))/100;
		$order_data['tip'] = 8.88;
		$payment_array_hash = createHashmapFromArrayOfArraysByFieldName($checkout_resource->accepted_payment_types,"splickit_accepted_payment_type_id");
		$order_data['merchant_payment_type_map_id'] = $payment_array_hash[9000]['merchant_payment_type_map_id'];
		$lead_times_array = $checkout_resource->lead_times_array;
		$order_data['actual_pickup_time'] = $lead_times_array[0];
		// this should be ignored;
		$order_data['lead_time'] = 100000;

		$request = createRequestObject("/apiv2/orders/$ucid",'post',json_encode($order_data),'application/json');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$order_resource = $place_order_controller->processV2Request();
		$this->assertNotNull($order_resource->error,"it shoudl have an error");
		$this->assertEquals($place_order_controller->cant_tip_because_cash_message,$order_resource->error);
		$order_data['tip'] = 0.00;

		$request = createRequestObject("/apiv2/orders/$ucid",'post',json_encode($order_data),'application/json');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$order_resource = $place_order_controller->processV2Request();

		$payment_service = $place_order_controller->getPaymentService();
		$tax_adjustment = $payment_service->tax_adjustment;
		$total_adjustment = 1.95 + $tax_adjustment;
		$this->assertNull($order_resource->error);
		$this->assertEquals((0.45-$tax_adjustment),$order_resource->total_tax_amt,"there should have been tax equal to the difference");
		$this->assertEquals(4.95-$total_adjustment,$order_resource->grand_total,"there should have been a grand total equal to the difference");
		$this->assertEquals($order_resource->grand_total,$order_resource->grand_total_to_merchant,"there should have been a grand total to merchant equal to the difference");
		$this->assertEquals('Y',$order_resource->cash,"It should be listed as a cash order");
		$order_id = $order_resource->order_id;

		$balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),'BalanceChangeAdapter');
		$this->assertCount(3,$balance_change_records,"There should only be 3 payments for this order");
		$bcr_hash = createHashmapFromArrayOfArraysByFieldName($balance_change_records,'process');
		$this->assertEquals(1.95,$bcr_hash['LoyaltyBalancePayment']['charge_amt'],"It should have the loyalty charge");
		$this->assertEquals(round(4.95-$total_adjustment,2),$bcr_hash['Cash']['charge_amt'],"It should have the remainder payed for with cash");

        $base_order = CompleteOrder::getBaseOrderData($order_resource->order_id,$m);
        $this->assertEquals('Y',$base_order['cash'],"It should show a cash order");

        // write test to make sure cash on pickup is listed on the order message
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'E');
        $email_controller = ControllerFactory::generateFromMessageResource($message_resource);
        $ready_to_send_message_resource = $email_controller->prepMessageForSending($message_resource);
        $body = $ready_to_send_message_resource->message_text;
        myerror_log($body);
        $this->assertContains("CASH ORDER",$body,"It should state that its a cash order");
        //$this->assertTrue(false,"write test to make sure cash on pickup is listed on the order message");

	}

	function testSummaryForPostTaxLoyaltyPayment()
	{
		$blr_id = $this->ids['blr_resource']->brand_loyalty_rules_id;
		$resource = Resource::find(new BrandLoyaltyRulesAdapter($m),"$blr_id");
		$resource->charge_tax = '1';
		$resource->save();

		$user_resource = createNewUserWithCCNoCVV();
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user_resource->user_id;
		$user_session_controller = new UsersessionController($m, $user, $r, 5);
		$user_session_controller->getUserSession($user_resource);

		$ubp_resource = Resource::find(new UserBrandPointsMapAdapter($m), "", array(TONIC_FIND_BY_METADATA => array("user_id" => $user_id, "brand_id" => getBrandIdFromCurrentContext())));
		$ubp_resource->dollar_balance = 1.00;
		$ubp_resource->save();

		$menu_id = $this->ids['menu_id'];

		$merchant_resource = createNewTestMerchant($menu_id);
		attachMerchantToSkin($merchant_resource->merchant_id, getSkinIdForContext());

		$billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);

		$merchant_payment_type_map_adapter = new MerchantPaymentTypeMapsAdapter($mimetypes);
		$cc_merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);

		// loyalty merchant payment type record
		$merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 8000, $billing_entity_id);

		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_resource->merchant_id, 'pickup', 'the note', 2);

		$request = createRequestObject('/app2/apiv2/cart', 'post', json_encode($order_data), 'application/json');
		$place_order_controller = new PlaceOrderController($mt, $user, $request, 5);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$cart_resource = $place_order_controller->processV2Request();
		$this->assertNull($cart_resource->error);
		$cart_ucid = $cart_resource->ucid;

		$request = createRequestObject("/app2/apiv2/cart/$cart_ucid/checkout", 'get');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$checkout_resource = $place_order_controller->processV2Request();


		$base_order_data = CompleteOrder::getBaseOrderData($checkout_resource->oid_test_only);
		$new_cart_note = "the new cart note";
		$order_data = array();
		$order_data['merchant_id'] = $base_order_data['merchant_id'];
		$order_data['note'] = $new_cart_note;
		$order_data['user_id'] = $base_order_data['user_id'];
		$order_data['cart_ucid'] = $base_order_data['ucid'];
		$order_data['tip'] = 0.00;

		$payment_array = $checkout_resource->accepted_payment_types;
		$order_data['merchant_payment_type_map_id'] = $payment_array[1]['merchant_payment_type_map_id'];
		$lead_times_array = $checkout_resource->lead_times_array;
		$order_data['actual_pickup_time'] = $lead_times_array[0];
		// this should be ignored;
		$order_data['lead_time'] = 100000;

		$request = createRequestObject('/apiv2/orders/' . $base_order_data['ucid'], 'post', json_encode($order_data), 'application/json');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$order_resource = $place_order_controller->processV2Request();
		$order_summary_json = json_encode($order_resource->order_summary);
		$this->assertNull($order_resource->error);

		$order_summary = $order_resource->order_summary;
		$cart_items = $order_summary['cart_items'];
		$this->assertCount(2,$cart_items,"there should only be 2 cart items");
		$cart_items_hash = createHashmapFromArrayOfArraysByFieldName($cart_items, 'item_name');
		$this->assertNull($cart_items_hash[LoyaltyBalancePaymentService::getDiscountDisplay()], "it should NOT have had the rewards used row in the items section");
		$this->assertCount(2,$order_summary['payment_items'],"there should be 2 payment items");
		$payment_items_hash = createHashmapFromArrayOfArraysByFieldName($order_summary['payment_items'],'title');
		$discount_display = LoyaltyBalancePaymentService::getDiscountDisplay();
		$this->assertEquals('$1.00',$payment_items_hash[$discount_display]['amount']);
		$this->assertEquals('$2.30',$payment_items_hash[CompleteOrder::CC_CHARGED_LABEL]['amount']);
	}

    function testLoadUserWithStartingPoints()
    {
        $blr_id = $this->ids['blr_resource']->brand_loyalty_rules_id;
        $resource = Resource::find(new BrandLoyaltyRulesAdapter($m),"$blr_id");
        $resource->starting_point_value = '100';
        $resource->save();

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);
        $user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $user_brand_points_record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertNotNull($user_brand_points_record,"Should have found a user brand loyalty record");
        $this->assertEquals(100,$user_brand_points_record['points'],'should have 100 points');
        $this->assertEquals(1.00,$user_brand_points_record['dollar_balance'],'should have .10 dollar value');

        $ulhr = UserBrandLoyaltyHistoryAdapter::staticGetRecords(array("user_id"=>$user['user_id']),'UserBrandLoyaltyHistoryAdapter');
        $this->assertCount(1,$ulhr);
        $this->assertEquals('Join Bonus',$ulhr[0]['process']);
    }

    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);

    	SplickitCache::flushAll();
    	$db = DataBase::getInstance();
    	$mysqli = $db->getConnection();
    	$mysqli->begin_transaction(); ;
    	$_SERVER['request_time1'] = microtime(true);    	

		$skin_resource = getOrCreateSkinAndBrandIfNecessary("xearn", "earnbrand", null, null);
    	$brand_id = $skin_resource->brand_id;
    	$brand_resource = Resource::find(new BrandAdapter($mimetypes),"$brand_id");
    	$brand_resource->loyalty = 'Y';
    	$brand_resource->save();

		$blr_data['brand_id'] = $brand_id;
		$blr_data['loyalty_type'] = 'splickit_earn';
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

		//promo
		$promo_adapter = new PromoAdapter($m);
		$ids['promo_id_type_1'] = 202;
		$sql = "INSERT INTO `Promo` VALUES(202, 'The Type1 Promo', 'Get $3 off', 1, 'Y', 'N', 0, 2, 'N', 'N','all', '2010-01-01', '2020-01-01', 1, 0, 0, 0.00, 0, 0.00, 'Y', 'N',0, $brand_id,NOW(), NOW(), 'N')";
		$promo_adapter->_query($sql);
		$sql = "INSERT INTO `Promo_Merchant_Map` VALUES(null, 202, $merchant_id2, '2013-10-05', '2020-01-01', NULL, now())";
		$pmm_resource2 = Resource::createByData(new PromoMerchantMapAdapter($mimetypes), array("merchant_id"=>$merchant_id2,"promo_id"=>202));
		$ids['promo_merchant_map_id_type_1_alternate'] = $pmm_resource2->map_id;
		$sql = "INSERT INTO `Promo_Message_Map` VALUES(null, 202, 'Congratulations! You''re getting $%%amt%% off your order!', NULL, NULL, NULL, NULL, now())";
		$promo_adapter->_query($sql);
		$sql = "INSERT INTO `Promo_Type1_Amt_Map` VALUES(null, 202, 1.00, 3.00, 0.00,50.00, NOW())";
		$promo_adapter->_query($sql);

		//$duplicate_promo_key_word = "somedumkeyword";
		$duplicate_promo_key_word = "AlternateKeyWord";
		$ids['duplicate_promo_key_word'] = $duplicate_promo_key_word;
		$pkwm_adapter = new PromoKeyWordMapAdapter($m);
		Resource::createByData($pkwm_adapter, array("promo_id"=>202,"promo_key_word"=>"type1promo","brand_id"=>$brand_id));
		Resource::createByData($pkwm_adapter, array("promo_id"=>202,"promo_key_word"=>"$duplicate_promo_key_word","brand_id"=>$brand_id));



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
	LoyaltyNewEarnTest::main();
}

?>