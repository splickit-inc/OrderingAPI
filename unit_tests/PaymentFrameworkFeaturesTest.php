<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class PaymentFrameworkFeaturesTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		$sql = "SET FOREIGN_KEY_CHECKS=1;";
    	mysqli_query(Database::getInstance()->getConnection(),$sql);
	}
	
	function tearDown() 
	{
		unset($_SERVER['TEST_VIO_TIMEOUT']);
        unset($_SERVER['TEST_TIMEOUT']);
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }

//    function testCustomRefundActivity()
//    {
//        setContext('com.splickit.worldhq');
//        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
//        $merchant_id = $merchant_resource->merchant_id;
//        $mpta = new MerchantPaymentTypeAdapter($m);
//        $sql = "DELETE FROM Merchant_Payment_Type_Maps WHERE merchant_id = $merchant_id";
//        $mpta->_query($sql);
//
//        //$merchant_resource = SplickitController::getResourceFromId($merchant_id, 'Merchant');
//        $merchant_id_key = generateCode(10);
//        $merchant_id_number = generateCode(5);
//        $data['vio_selected_server'] = 'sage';
//        $data['vio_merchant_id'] = $merchant_id;
//        $data['name'] = "Test Billing Entity";
//        $data['description'] = 'An entity to test with';
//        $data['merchant_id_key'] = $merchant_id_key;
//        $data['merchant_id_number'] = $merchant_id_number;
//        $data['identifier'] = $merchant_resource->alphanumeric_id;
//        $data['brand_id'] = $merchant_resource->brand_id;
//        $data['process_type'] = "purchase";
//
//        $card_gateway_controller = new CardGatewayController($mt, $u, $r);
//        $resource = $card_gateway_controller->createPaymentGateway($data);
//        $billing_entity_external_id = $resource->external_id;
//        $this->assertTrue(is_a($resource, 'Resource'),'Should have returned a resource');
//        $this->assertNull($resource->error);
//        $this->assertNotNull($resource->id);
//        $this->assertNotNull($resource->merchant_payment_type_map);
//        $expected_string = "merchant_id_key=$merchant_id_key|merchant_id_number=$merchant_id_number";
//        $this->assertEquals($expected_string,$resource->credentials);
//        $created_merchant_payment_type_map_id = $resource->merchant_payment_type_map->id;
//
//        $user = logTestUserIn($this->ids['user_id']);
//        $user_id = $user['user_id'];
//        $balance_before = $user['balance'];
//        $order_adapter = new OrderAdapter($mimetypes);
//        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
//        $checkout_resource = getCheckoutResourceFromOrderData($order_data);
//        $this->assertNull($checkout_resource->error);
//        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
//        $order_id = $order_resource->order_id;
//        $this->assertNull($order_resource->error);
//        $this->assertTrue($order_resource->order_id > 1000);
//        $this->assertEquals('VioPaymentService', $order_resource->payment_service_used);
//
//        $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
//        if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
//            $balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
//        }
//        $this->assertCount(2, $balance_change_rows_by_user_id);
//        $this->assertEquals($balance_before, $balance_change_rows_by_user_id["$user_id-Order"]['balance_before']);
//        $this->assertEquals($order_resource->grand_total, -$balance_change_rows_by_user_id["$user_id-Order"]['charge_amt']);
//        $this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-Order"]['balance_after']);
//
//        $map_id = $balance_change_rows_by_user_id["$user_id-CCpayment"]['id'];
//
//        $time = getMySqlFormattedDateTimeFromTimeStampAndTimeZone(time() - (96*3600));
//
//        $fake_sql = "UPDATE Balance_Change SET created = '$time' where id = $map_id";
//        $balance_change_adapter->_query($fake_sql);
//
//
//        // now create the activity
//        $activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
//        $brand_id = getBrandIdFromCurrentContext();
//        $doit_ts = time()-2;
//        //$info = 'object=VioPaymentService;method=executeCapturesFromActivityForBrandId;thefunctiondatastring='.$brand_id.'';
//        $activity_history_resource = $activity_history_adapter->createActivityReturnActivityResource('Custom', $doit_ts, '', '');
//        $activity = $activity_history_adapter->getActivityFromUnlockedActivityHistoryResource($activity_history_resource);
//        $activity->executeThisActivity();
//
//
//        $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
//        if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
//            $balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
//        }
//        $this->assertEquals($map_id,$balance_change_rows_by_user_id["$user_id-CCpaymentREFUNDED"]['id']);
//    }

    function testAccountCreditPaymentServiceNotEnoughBalance()
    {
        setContext("com.splickit.snarfs");
        $merchant_resource = createNewTestMerchant($this->ids['menu_id'],array("authorize"=>true));
        $merchant_id = $merchant_resource->merchant_id;
        $mptm_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_id,1000,null);

        $user_resource = createNewUserWithCCNoCVV();
        $user_resource->balance = 2.00;
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);
        $balance_before = $user['balance'];
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $accepted_payment_types = createHashmapFromArrayOfArraysByFieldName($checkout_resource->accepted_payment_types,'name');
        $this->assertNull($accepted_payment_types['Cash'],'There should not be a cash option since the user has credit balance');

//        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$time);
//        $order_id = $order_resource->order_id;
//        $this->assertNull($order_resource->error);
//        $this->assertTrue($order_resource->order_id > 1000);
//        $this->assertEquals('AccountCreditPaymentService', $order_resource->payment_service_used);
    }


    function testFailRefundAttemptOfCancelledAuthorize()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id'],array("authorize"=>true));
        $merchant_id = $merchant_resource->merchant_id;
        $mpta = new MerchantPaymentTypeAdapter($m);
        $sql = "DELETE FROM Merchant_Payment_Type_Maps WHERE merchant_id = $merchant_id";
        $mpta->_query($sql);

        //$merchant_resource = SplickitController::getResourceFromId($merchant_id, 'Merchant');
        $merchant_id_key = generateCode(10);
        $merchant_id_number = generateCode(5);
        $data['vio_selected_server'] = 'sage';
        $data['vio_merchant_id'] = $merchant_id;
        $data['name'] = "Test Billing Entity";
        $data['description'] = 'An entity to test with';
        $data['merchant_id_key'] = $merchant_id_key;
        $data['merchant_id_number'] = $merchant_id_number;
        $data['identifier'] = $merchant_resource->alphanumeric_id;
        $data['brand_id'] = $merchant_resource->brand_id;
        $data['process_type'] = "Authorize";

        $card_gateway_controller = new CardGatewayController($mt, $u, $r);
        $resource = $card_gateway_controller->createPaymentGateway($data);
        $billing_entity_external_id = $resource->external_id;
        $this->assertTrue(is_a($resource, 'Resource'),'Should have returned a resource');
        $this->assertNull($resource->error);
        $this->assertNotNull($resource->id);
        $this->assertNotNull($resource->merchant_payment_type_map);
        $expected_string = "merchant_id_key=$merchant_id_key|merchant_id_number=$merchant_id_number";
        $this->assertEquals($expected_string,$resource->credentials);
        $created_merchant_payment_type_map_id = $resource->merchant_payment_type_map->id;

        $user = logTestUserIn($this->ids['user_id']);
        $user_id = $user['user_id'];
        $balance_before = $user['balance'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
        $order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertNull($order_resource->error);
        $this->assertTrue($order_resource->order_id > 1000);
        $this->assertEquals('VioPaymentService', $order_resource->payment_service_used);

        $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
        if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
            $balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
        }
        $authorize_resource = Resource::find($balance_change_adapter,$balance_change_rows_by_user_id["$user_id-Authorize"]['id']);
        $authorize_resource->notes = 'cancelled';
        $authorize_resource->save();

        $order_resource = SplickitController::getResourceFromId($order_id, "Order");
        $user = logTestUserIn($order_resource->user_id);
        $order_controller = new OrderController(getM(), $user, $r, 5);
        $refund_results = $order_controller->issueOrderRefund($order_id, "0.00");
        $this->assertEquals("failure", $refund_results['result']," should have gotten a failure but: ".$refund_results['message']);
    }

    function testRefundForSplickitCredit()
    {
        setContext("com.splickit.snarfs");
        //setContext($this->ids['context']);
        $merchant_id = $this->ids['merchant_id'];
        $user_resource = createNewUserWithCCNoCVV();
        $user_resource->balance = 10.40;
        $user_resource->flags = '1000000001';
        //$user_resource->credit_limit = -1.00;
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);
        $balance_before = $user['balance'];
        $order_adapter = new OrderAdapter($m);
        $cart_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',2);
        $mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
        $mptm_record = $mptma->getRecord(array("merchant_id"=>$merchant_id));
        //$order_data['merchant_payment_type_map_id'] = $mptm_record['id'];
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource->accepted_payment_types[0]['merchant_payment_type_map_id'] = 1000;
        $checkout_resource->accepted_payment_types[0]['name'] = 'Account Credit';
        $checkout_resource->accepted_payment_types[0]['splickit_accepted_payment_type_id'] = 5000;
        $checkout_resource->accepted_payment_types[0]['billing_entity_id'] = null;
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$time);
        $user_after_order = getUserFromId($user['user_id']);
        $this->assertEquals(-.60,$user_after_order['balance'],"It should show the users balance as a small negative value");


        $order_id = $order_resource->order_id;
        $this->assertNull($order_resource->error);
        $this->assertTrue($order_resource->order_id > 1000);
        $this->assertEquals('AccountCreditPaymentService', $order_resource->payment_service_used);

        $request = new Request();
        $request->data['note'] = 'Sum dum note';
        $order_controller = new OrderController($mt, $user, $request, 5);
        //$order_controller->updateOrderStatusById($order_id,'E');
        $refund_results = $order_controller->issueOrderRefund($order_id, "1.50");

        $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
        $balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options);

        $user_after = getUserFromId($user['user_id']);
        $this->assertEquals(.90,$user_after['balance']);

        $this->assertEquals("success",$refund_results['result']);
        $this->assertEquals('The order has been refunded',$refund_results['message']);


    }

    function testFailAuthAndDoAuthReversalWithFailure()
    {
        setProperty('check_cvv',"true");
        setProperty("force_void_fail","true");
        setContext("com.splickit.snarfs");
        $merchant_id = $this->ids['auth_merchant_id'];
        $user_resource = createNewUserWithCC();
        $user_resource->uuid = substr($user_resource->uuid,0,17).'NOCVV';
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSuperSimpleCartArrayByMerchantId($merchant_id,'pickup','note',1);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$time);
        $this->assertNotNull($order_resource->error);

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


    function testAddTipToAuthorizedOrderThatIsMoreThanDoubleAuthAmount()
    {
        //setContext("com.splickit.snarfs");
        setContext($this->ids['context']);

        $merchant_id = $this->ids['auth_merchant_id'];
        $billing_entity_external_id = $this->ids['auth_billing_entity_external'];
        $created_merchant_payment_type_map_id = $this->ids['auth_merchant_payment_type_map_id'];

        $user = logTestUserIn($this->ids['user_id']);
        $user_id = $user['user_id'];
        $balance_before = $user['balance'];
        $order_adapter = new OrderAdapter($mimetypes);
//        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
//        $order_data['tip'] = 0.00;
//        $order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
//        $order_result_resource = placeOrderFromOrderData($order_data, $time_stamp);

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $order_result_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,getTomorrowTwelveNoonTimeStampDenver());

        $pre_tip_grand_total = $order_result_resource->grand_total;
        $this->assertNull($order_result_resource->error);
        $order_id = $order_result_resource->order_id;
        $ucid = $order_result_resource->ucid;

        $order_resource = Resource::find($order_adapter,"$order_id");
        $this->assertEquals(0.00,$order_resource->tip_amt);
//        $vio_payment_service = new VioPaymentService($data);
//        $results = $vio_payment_service->processOrderCaptureOfPreviousAuthorization($order_resource,$order_resource->grand_total);

        $balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),"BalanceChangeAdapter");
        $this->assertCount(2,$balance_change_records,"There should be 2 balance change records");
        $bc_hash_by_process = createHashmapFromArrayOfArraysByFieldName($balance_change_records,"process");
        $auth_record = $bc_hash_by_process['Authorize'];
        $this->assertEquals('PENDING',$auth_record['notes']);

//        $cc_charge_record = $bc_hash_by_process['CCpayment'];
//        $this->assertEquals($order_resource->grand_total,$cc_charge_record['charge_amt']);

        // now add a large tip

        $tip_amt = $auth_record['charge_amt'];
        $_SERVER['SOAPAction'] = "http://www.xoikos.com/webservices/ApplyTipToOrder";
        $xml_body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/"><OrderID>'.$ucid.'</OrderID><Amount>'.$tip_amt.'</Amount></ApplyTipToOrder></soap:Body></soap:Envelope>';
        $request = new Request();
        $request->url = "/pos/xoikos";
        $request->method = "POST";
        $request->body = $xml_body;
        $request->mimetype = 'Applicationxml';

        $pos_controller = new PosController($mt,$user,$request,5);
        $resource = $pos_controller->processV2request();
        $this->assertNull($resource->error);
        $balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),"BalanceChangeAdapter");
        $this->assertCount(4,$balance_change_records,"There should have been 4 balance change records");

        $new_grand_total = $order_resource->grand_total + $tip_amt;
        $new_order_record = OrderAdapter::staticGetRecord(array("ucid"=>$ucid),'OrderAdapter');
        $this->assertEquals($tip_amt,$new_order_record['tip_amt']);
        $this->assertEquals($new_grand_total,$new_order_record['grand_total']);

        // now test for the charges
        $main_cc_payment_bc_row = $balance_change_records[2];
        $this->assertEquals($pre_tip_grand_total,$main_cc_payment_bc_row['charge_amt']);

        $tip_cc_payment_row = $balance_change_records[3];
        $this->assertEquals($tip_amt,$tip_cc_payment_row['charge_amt']);
    }

    function testAccountCreditPaymentService()
    {
        setContext("com.splickit.snarfs");
        $merchant_id = $this->ids['merchant_id'];
        $user_resource = createNewUserWithCCNoCVV();
        $user_resource->balance = 100.00;
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);
        $balance_before = $user['balance'];
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$time);
        $order_id = $order_resource->order_id;
        $this->assertNull($order_resource->error);
        $this->assertTrue($order_resource->order_id > 1000);
        $this->assertEquals('AccountCreditPaymentService', $order_resource->payment_service_used);
    }

    function testFailCCThenSwitchToCashRemoveTip()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user_resource->uuid = substr($user_resource->uuid,0,17).'DECL';
        $user_resource->save();

        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;


        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'the note');
        $json_encoded_data = json_encode($order_data);

        $url = '/app2/apiv2/cart/checkout';
        $request = createRequestObject($url, 'post', $json_encoded_data);
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource, $user, $merchant_id, 1.50, $time);
        $this->assertNotNull($order_resource->error);

        $order = new Order($order_resource->cancelled_order_id);
        $this->assertEquals(1.50,$order->get('tip_amt'));

        $mptm_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_id,1000,$billing_entity_id);


        $order_data = array();
        $order_data['tip'] = null;
        $order_data['merchant_payment_type_map_id'] = $mptm_resource->id;
        $lead_times_array = $checkout_resource->lead_times_array;
        $order_data['actual_pickup_time'] = $lead_times_array[0];
        $order_data['requested_time'] = $lead_times_array[0];

        $json_encoded_data = json_encode($order_data);

        $request = createRequestObject("/apiv2/orders/".$checkout_resource->cart_ucid, "post", $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $order_resource = $place_order_controller->processV2Request();
        $this->assertNull($order_resource->error);

        $order = new Order($order_resource->order_id);
        $this->assertEquals(0.00,$order->get('tip_amt'));
    }

    function testPartialRefundForClosedOrder()
    {
        setContext("com.splickit.snarfs");
        $merchant_id = $this->ids['merchant_id'];
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $balance_before = $user['balance'];

        // clear tha activity list
        $order_adapter = new OrderAdapter($mimetypes);
        $sql = "UPDATE Activity_History SET locked = 'E' WHERE 1=1";
        $order_adapter->_query($sql);

        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',2);
        $mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
        $mptm_record = $mptma->getRecord(array("merchant_id"=>$merchant_id));
        $order_data['merchant_payment_type_map_id'] = $mptm_record['id'];
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertNull($order_resource->error);
        $this->assertTrue($order_resource->order_id > 1000);
        //$this->assertEquals('VioPaymentService', $order_resource->payment_service_used);

        $sql = "UPDATE Balance_Change SET created = DATE_SUB(now(),INTERVAL 1 day) WHERE order_id = $order_id";
        $order_adapter->_query($sql);

        $request = new Request();
        $request->data['note'] = 'Sum dum note';
        $order_controller = new OrderController($mt, $user, $request, 5);
        $order_controller->updateOrderStatusById($order_id,'E');
        $refund_results = $order_controller->issueOrderRefund($order_id, "1.01");
        $bcrecords = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),'BalanceChangeAdapter');
        $hash = createHashmapFromArrayOfArraysByFieldName($bcrecords,'process');
        $this->assertEquals(1.01,$hash['CCrefund']['charge_amt']);
    }

	function testSmallCharge()
	{
		$menu_id = createTestMenuWithOneItem("smallcharge");
		$merchant_resource = createNewTestMerchant($menu_id);
		$user_resource = createNewUserWithCCNoCVV();
		$user = logTestUserResourceIn($user_resource);
		$complete_items = CompleteMenu::getAllMenuItemsAsArray($menu_id);
		$isa = new ItemSizeAdapter($m);
		$itemsize_resource = Resource::findAll($isa,null,array(TONIC_FIND_BY_METADATA=>array("item_id"=>$complete_items[0]['item_id'])));
		foreach ($itemsize_resource as $isr) {
			$isr->price = 0.50;
			$isr->save();
		}
		$order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_resource->merchant_id);
		$order_data['tip'] = 0.00;
		$order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());

		$bcrecords = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_resource->order_id),'BalanceChangeAdapter');
		$hash = createHashmapFromArrayOfArraysByFieldName($bcrecords,'process');
		$this->assertCount(2,$hash,'there should be 2 records');
		$cc_payment = $hash['CCpayment'];
		$this->assertEquals(.55,$cc_payment['charge_amt'],"Should have run CC for .55 cents");
		$user_resource = $user_resource->getRefreshedResource();
		$this->assertEquals(0.00,$user_resource->balance);
	}

	function testCaptureAllPendingBalanceChangeRecordsFromActivity()
	{
		$sql = "SET FOREIGN_KEY_CHECKS=0;";
        mysqli_query(Database::getInstance()->getConnection(),$sql);

		$skin = getOrCreateSkinAndBrandIfNecessary('testoneskin','testonebrand',$skin_id,$brand_id);
		setContext('com.splickit.testoneskin');

		$user_id = rand(111111,999999);
		$brand_id = getBrandIdFromCurrentContext();
		$billing_entity_resource = createSageBillingEntity($brand_id);
		$billing_entity_resource2 = createSageBillingEntity($brand_id);

		$balance_change_adapter = new BalanceChangeAdapter($m);
		$bcar = array ();
		for ($i=0;$i<11;$i++) {
			$order_resource = $this->createBaseOrder($user_id);
			$order_id = $order_resource->order_id;
			$grand_total = rand(1001,2000)/100;
			if ($i==6) {
				$grand_total = 10.00; // this will fail the capture
				$failed_order_id = $order_id;
			}
			$simulated_transaction_idetifier = generateUUID();
			if ($i == 1 ||$i == 3 ||$i == 5 ||$i == 7) {
				$bei = $billing_entity_resource->external_id;
			} else {
				$bei = $billing_entity_resource2->external_id;
			}
			if ($i == 10) {
				$user_id = 101;
			}
			$bcar[] = $balance_change_adapter->addAuthorizeRow($user_id,$grand_total,$grand_total,$bei,$order_id,$simulated_transaction_idetifier,"PENDING");
			if ($i == 5) {
                $simulated_transaction_idetifier = $simulated_transaction_idetifier.'-dup';
                $balance_change_adapter->addAuthorizeRow($user_id,$grand_total,$grand_total,$bei,$order_id,$simulated_transaction_idetifier,"PENDING");
                $duplicate_order_id = $order_id;
            }
		}

		$data['user_id'] = $this->ids['user_id'];
		$data['merchant_id'] = $this->ids['auth_merchant_id'];
		$cart_resource = CartsAdapter::createCart($data);
		$cart_resource->order_dt_tm = "2016-07-04 12:20:33";
		$cart_resource->grand_total = 8.88;
		$cart_resource->order_id = $failed_order_id;
		$cart_resource->save();

		$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
		$brand_id = getBrandIdFromCurrentContext();
		$doit_ts = time()-2;
		$info = 'object=VioPaymentService;method=executeCapturesFromActivityForBrandId;thefunctiondatastring='.$brand_id.'';
		$activity_history_resource = $activity_history_adapter->createActivityReturnActivityResource('ExecuteObjectFunction', $doit_ts, $info, $activity_text,3600);
		$activity = $activity_history_adapter->getActivityFromUnlockedActivityHistoryResource($activity_history_resource);
		$activity->executeThisActivity();

		$mmh_records = MerchantMessageHistoryAdapter::staticGetRecords(array("message_format"=>'E'),'MerchantMessageHistoryAdapter');
		$support_email = array_pop($mmh_records);
		$message_text = $support_email['message_text'];
		$this->assertContains("subject=Process capture for brand: testonebrand",$support_email['info']);
		$this->assertContains("There were 9 successful captures",$support_email['message_text']);
		$this->assertContains("There were 1 failed captures",$support_email['message_text']);
		$this->assertContains("there was an unknown error trying to capture the authorized charge. order_id: $failed_order_id",$support_email['message_text']);
		//$this->assertContains("There was a duplicate on order_id: $duplicate_order_id. second capture was skipped");

        $bca = new BalanceChangeAdapter(getM());
        $records = $bca->getRecords(array("order_id"=>$duplicate_order_id));
        $records_hash = createHashmapFromArrayOfArraysByFieldName($records,'notes');
        $this->assertNotNull($records_hash['duplicate_cancelled'],"It should have a duplicate cancelled row");
        return true;
	}

	function createBaseOrder($user_id)
	{
		$data['merchant_id'] = rand(111111,999999);
		$data['user_id'] = $user_id;
		$order_resource = CartsAdapter::createCart($data);
		if ($data['merchant_id'] % 2 == 0) {
			$order_resource->status = OrderAdapter::ORDER_EXECUTED;
		} else {
			$order_resource->status = OrderAdapter::ORDER_SUBMITTED;
		}
		$order_resource->save();
		return $order_resource;
	}

    function testVoidOfAuthorizeOnOpenOrder()
    {
        setContext("com.splickit.snarfs");
        $merchant_id = $this->ids['auth_merchant_id'];
        $billing_entity_external_id = $this->ids['auth_billing_entity_external'];
        $created_merchant_payment_type_map_id = $this->ids['auth_merchant_payment_type_map_id'];

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
        $order_data['tip'] = 0.00;
        //$order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertNull($order_resource->error);
        $this->assertTrue($order_resource->order_id > 1000);
        $this->assertEquals('VioPaymentService', $order_resource->payment_service_used);

        // now do refund
        $order_resource = SplickitController::getResourceFromId($order_id, "Order");
        $order_controller = new OrderController($mt, $user, $r, 5);
        $refund_results = $order_controller->issueOrderRefund($order_id, "0.00");
        $this->assertEquals("success", $refund_results['result']," should have gotten a success but: ".$refund_results['message']);
        $this->assertEquals(100,$refund_results['response_code']);

        // check to see if order was changed to cancelled
        $order_adapter = new OrderAdapter($mimetypes);
        $new_order_resource = Resource::find($order_adapter,''.$order_id);
        $this->assertEquals('C', $new_order_resource->status);

        // now check the balance change table
        $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
        if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
            $balance_change_hash_by_process = createHashmapFromArrayOfArraysByFieldName($balance_change_records,'process');
        }
        $this->assertEquals('CANCELED',$balance_change_hash_by_process['AuthCancelled']['notes']);
        $void_record = $balance_change_hash_by_process['CCvoid'];
        $this->assertNotNull($void_record,"It should have found the void record");
        $this->assertEquals('Issuing a VioPaymentService VOID from the API: ',$void_record['notes']);
        $this->assertCount(3,$balance_change_hash_by_process);

        // now check to see that a row was NOT added to the balance change table. sasquatch
        $adm_reversal_record = getStaticRecord(array("order_id"=>$order_resource->order_id),'AdmOrderReversalAdapter');
        $this->assertNull($adm_reversal_record);
    }

    function testVoidOfAuthorizeOnExecutedOrder()
    {
        setContext("com.splickit.snarfs");
        $merchant_id = $this->ids['auth_merchant_id'];
        $billing_entity_external_id = $this->ids['auth_billing_entity_external'];
        $created_merchant_payment_type_map_id = $this->ids['auth_merchant_payment_type_map_id'];

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
        $order_data['tip'] = 0.00;
        //$order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertNull($order_resource->error);
        $this->assertTrue($order_resource->order_id > 1000);
        $this->assertEquals('VioPaymentService', $order_resource->payment_service_used);
        $order_controller = new OrderController($m,$u,$r);
        $this->assertTrue($order_controller->updateOrderStatusById($order_resource->order_id,OrderAdapter::ORDER_EXECUTED));



        // now do refund
        $order_resource = SplickitController::getResourceFromId($order_id, "Order");
        $order_controller = new OrderController($mt, $user, $r, 5);
        $refund_results = $order_controller->issueOrderRefund($order_id, "0.00");
        $this->assertEquals("success", $refund_results['result']," should have gotten a success but: ".$refund_results['message']);
        $this->assertEquals(100,$refund_results['response_code']);

        // check to see if order stayed as executed
        $order_adapter = new OrderAdapter($mimetypes);
        $new_order_resource = Resource::find($order_adapter,''.$order_id);
        $this->assertEquals('E', $new_order_resource->status);

        // now check the balance change table
        $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
        if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
            $balance_change_hash_by_process = createHashmapFromArrayOfArraysByFieldName($balance_change_records,'process');
        }
        $this->assertEquals('CANCELED',$balance_change_hash_by_process['AuthCancelled']['notes']);
        $void_record = $balance_change_hash_by_process['CCvoid'];
        $this->assertNotNull($void_record,"It should have found the void record");
        $this->assertEquals('Issuing a VioPaymentService VOID from the API: ',$void_record['notes']);
        $this->assertCount(3,$balance_change_hash_by_process);

        // now check to see that a row was added to the balance change table. sasquatch
        $adm_reversal_record = getStaticRecord(array("order_id"=>$order_resource->order_id),'AdmOrderReversalAdapter');
        $this->assertNotNull($adm_reversal_record);
        $this->assertEquals($order_resource->grand_total,$adm_reversal_record['amount']);
    }

    function testPartialOfAuthorize()
    {
        setContext("com.splickit.snarfs");
        $merchant_id = $this->ids['auth_merchant_id'];
        $billing_entity_external_id = $this->ids['auth_billing_entity_external'];
        $created_merchant_payment_type_map_id = $this->ids['auth_merchant_payment_type_map_id'];

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
        $order_data['tip'] = 0.00;
        $order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertNull($order_resource->error);
        $this->assertTrue($order_resource->order_id > 1000);
        $this->assertEquals('VioPaymentService', $order_resource->payment_service_used);


        // now do refund
        $request = new Request();
        $request->data['note'] = 'Sum dum note';
        $order_controller = new OrderController($mt, $user, $request, 5);
        $order_controller->updateOrderStatusById($order_id,'E');
        $refund_results = $order_controller->issueOrderRefund($order_id, "1.01");
        $this->assertEquals("success", $refund_results['result']," should have gotten a success but: ".$refund_results['message']);
        $this->assertEquals(100,$refund_results['response_code']);

        $balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),"BalanceChangeAdapter");
        $bc_hash_by_process = createHashmapFromArrayOfArraysByFieldName($balance_change_records,"process");

        $auth_record = $bc_hash_by_process['Authorize'];
        $this->assertEquals('captured',$auth_record['notes']);

        $cc_charge_record = $bc_hash_by_process['CCpayment'];
        $this->assertEquals($order_resource->grand_total,$cc_charge_record['charge_amt']);
        $this->assertEquals(-$order_resource->grand_total,$cc_charge_record['balance_before'],"balance before should be set appropriately");

        $this->assertCount(4,$balance_change_records,"There should have been 4 balance change records");
        $user = UserAdapter::getUserResourceFromId($user_id);
        $this->assertEquals(0.00,$user->balance,"Users balance should be zero");

        $adm_reversal_record = getStaticRecord(array("order_id"=>$order_id),'AdmOrderReversalAdapter');
        $this->assertNotNull($adm_reversal_record);
        $this->assertEquals(1.01,$adm_reversal_record['amount']);

    }

	function testRecordAuthorizeOnPlaceOrder()
	{
		setContext("com.splickit.moes");

		$merchant_id = $this->ids['auth_merchant_id'];
		$billing_entity_external_id = $this->ids['auth_billing_entity_external'];
		$created_merchant_payment_type_map_id = $this->ids['auth_merchant_payment_type_map_id'];

		$user = logTestUserIn($this->ids['user_id']);
		$user_id = $user['user_id'];
		$balance_before = $user['balance'];
		$order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
		$checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
		$this->assertNull($checkout_resource->error);
		$order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,getTomorrowTwelveNoonTimeStampDenver());
//		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
//		$order_data['tip'] = 0.00;
//		$order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
//		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$order_id = $order_resource->order_id;
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000);
		$this->assertEquals('VioPaymentService', $order_resource->payment_service_used);

		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
			$balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
		}
		$this->assertCount(2, $balance_change_rows_by_user_id);
		$this->assertTrue(isset($balance_change_rows_by_user_id["$user_id-Authorize"]),"Should have found the authorize row");

		$this->assertEquals($balance_before, $balance_change_rows_by_user_id["$user_id-Order"]['balance_before']);
		$this->assertEquals($order_resource->grand_total, -$balance_change_rows_by_user_id["$user_id-Order"]['charge_amt']);
		$this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-Order"]['balance_after']);

		$this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-Authorize"]['balance_before']);
		$this->assertEquals($balance_change_rows_by_user_id["$user_id-Authorize"]['balance_before'], -$balance_change_rows_by_user_id["$user_id-Authorize"]['charge_amt']);
		$this->assertEquals($billing_entity_external_id, $balance_change_rows_by_user_id["$user_id-Authorize"]['cc_processor']);
		$this->assertEquals($balance_change_rows_by_user_id["$user_id-Authorize"]['balance_before'],$balance_change_rows_by_user_id["$user_id-Authorize"]['balance_after'],"Balance should not have changed with authorize");
		$this->assertEquals("PENDING",$balance_change_rows_by_user_id["$user_id-Authorize"]['notes'],"notes should show a pending authrization");

		$new_user_resource = SplickitController::getResourceFromId($user_id, 'User');
		$this->assertEquals(0.00,$new_user_resource->balance,"Users balance should show 0 since the payment is pending");

		$order_conf_message = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'Econf');
		$message_text = $order_conf_message->message_text;
		$this->assertContains('billed to credit card ending in <b>1234</b>',$message_text);
		return $order_resource->ucid;
	}

	/**
	 * @depends testRecordAuthorizeOnPlaceOrder
	 */
	function testCaptureOfExistingAuthorize($ucid)
	{
		$order_record = OrderAdapter::staticGetRecord(array("ucid"=>$ucid),'OrderAdapter');
		$this->assertEquals(0.00,$order_record['tip_amt']);

//		$request = new Request();
//		$request->url = "/apiv2/pos/orders/$ucid";
//		$request->method = "PUT";
//		$request->data = array("tip_amt"=>1.88);
//		$order_controller = new OrderController($mt,$user,$request,5);
//		$resource = $order_controller->processOrderUpdate();

		$tip_amt = "1.88";
		$_SERVER['SOAPAction'] = "http://www.xoikos.com/webservices/ApplyTipToOrder";
		$xml_body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/"><OrderID>'.$ucid.'</OrderID><Amount>'.$tip_amt.'</Amount></ApplyTipToOrder></soap:Body></soap:Envelope>';
		$request = new Request();
		$request->url = "/pos/xoikos";
		$request->method = "POST";
		$request->body = $xml_body;
		$request->mimetype = 'Applicationxml';


		$pos_controller = new PosController($mt,$user,$request,5);
		$resource = $pos_controller->processV2request();

		$new_grand_total = $order_record['grand_total'] + $tip_amt;

		$new_order_record = OrderAdapter::staticGetRecord(array("ucid"=>$ucid),'OrderAdapter');
		$this->assertEquals($tip_amt,$new_order_record['tip_amt']);
		$this->assertEquals($new_grand_total,$new_order_record['grand_total']);

		$balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_record['order_id']),"BalanceChangeAdapter");
		$this->assertCount(3,$balance_change_records,"There should have been 3 balance change records");
		$bc_hash_by_process = createHashmapFromArrayOfArraysByFieldName($balance_change_records,"process");
		$auth_record = $bc_hash_by_process['Authorize'];
		$this->assertEquals('captured',$auth_record['notes']);

		$cc_charge_record = $bc_hash_by_process['CCpayment'];
		$this->assertEquals($new_grand_total,$cc_charge_record['charge_amt']);
		$this->assertEquals(-$new_grand_total,$cc_charge_record['balance_before'],"balance before should be set appropriately");

		$this->assertEquals(true,$resource->send_soap_response,"Should have found the send soap request parameter, and it should be true");
		$expected_soap_response = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrderResponse xmlns="http://www.xoikos.com/webservices/"><ApplyTipToOrderResult><Success>true</Success><ErrorMessage>A '.$tip_amt.' tip has been applied on Order with ID '.$ucid.'</ErrorMessage></ApplyTipToOrderResult></ApplyTipToOrderResponse></soap:Body></soap:Envelope>';
		$this->assertEquals($expected_soap_response,$resource->soap_body);
		return $ucid;
	}

    /**
     * @depends testCaptureOfExistingAuthorize
     */
	function testUserAuthIdAfterFailedRefundOfCapturedOrderDueToBadTransactionId($ucid)
    {

        $heartland_billing_entity_resource = $this->ids['heartland_billing_entity_resource'];
        $destination_id = $heartland_billing_entity_resource->external_id;
        $order_record = OrderAdapter::staticGetRecord(array("ucid"=>$ucid),'OrderAdapter');
        OrderAdapter::updateOrderStatus('E',$order_record['order_id']);
        $options[TONIC_FIND_BY_METADATA] = array("order_id"=>$order_record['order_id']);
        $balance_change_resources = Resource::findAll(new BalanceChangeAdapter(getM()),null,$options);
        foreach ($balance_change_resources as $balance_change_resource) {
            if ($balance_change_resource->process == 'CCpayment') {
                $balance_change_resource->cc_transaction_id = '1234567890';
                $balance_change_resource->cc_processor = $destination_id;
            } else if ($balance_change_resource->process == 'Authorize') {
                $balance_change_resource->cc_transaction_id = '1234-abcd-9876-efgh';
                $balance_change_resource->cc_processor = $destination_id;
            }
            $balance_change_resource->save();
        }

        $user = getUserFromId($order_record['user_id']);
        $order_controller = new OrderController(getM(), $user, $request, 5);
        $refund_results = $order_controller->issueOrderRefund($order_record['order_id'], "0.00");
        $this->assertEquals("success",$refund_results['result']);
    }



	function testAddTipToCapturedOrder()
	{
		setContext("com.splickit.snarfs");

		$merchant_id = $this->ids['auth_merchant_id'];
		$billing_entity_external_id = $this->ids['auth_billing_entity_external'];
		$created_merchant_payment_type_map_id = $this->ids['auth_merchant_payment_type_map_id'];

		$user = logTestUserIn($this->ids['user_id']);
		$user_id = $user['user_id'];
		$balance_before = $user['balance'];
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
		$order_data['tip'] = 0.00;
		$order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
		$order_result_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$this->assertNull($order_result_resource->error);
		$order_id = $order_result_resource->order_id;
		$ucid = $order_result_resource->ucid;

		$order_resource = Resource::find($order_adapter,"$order_id");
		$this->assertEquals(0.00,$order_resource->tip_amt);
		$vio_payment_service = new VioPaymentService($data);
		$results = $vio_payment_service->processOrderCaptureOfPreviousAuthorization($order_resource,$order_resource->grand_total);

		$balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),"BalanceChangeAdapter");
		$this->assertCount(3,$balance_change_records,"There should have been 3 balance change records");
		$bc_hash_by_process = createHashmapFromArrayOfArraysByFieldName($balance_change_records,"process");
		$auth_record = $bc_hash_by_process['Authorize'];
		$this->assertEquals('captured',$auth_record['notes']);

		$cc_charge_record = $bc_hash_by_process['CCpayment'];
		$this->assertEquals($order_resource->grand_total,$cc_charge_record['charge_amt']);

		// now add a tip after a capture

		$tip_amt = "2.22";
		$_SERVER['SOAPAction'] = "http://www.xoikos.com/webservices/ApplyTipToOrder";
		$xml_body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/"><OrderID>'.$ucid.'</OrderID><Amount>'.$tip_amt.'</Amount></ApplyTipToOrder></soap:Body></soap:Envelope>';
		$request = new Request();
		$request->url = "/pos/xoikos";
		$request->method = "POST";
		$request->body = $xml_body;
		$request->mimetype = 'Applicationxml';

		$pos_controller = new PosController($mt,$user,$request,5);
		$resource = $pos_controller->processV2request();
		$this->assertNull($resource->error);
		$balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),"BalanceChangeAdapter");
		$this->assertCount(4,$balance_change_records,"There should have been 4 balance change records");


		$new_grand_total = $order_resource->grand_total + $tip_amt;
		$new_order_record = OrderAdapter::staticGetRecord(array("ucid"=>$ucid),'OrderAdapter');
		$this->assertEquals($tip_amt,$new_order_record['tip_amt']);
		$this->assertEquals($new_grand_total,$new_order_record['grand_total']);

	}

	function testCaptureTimeOut()
	{
		setContext("com.splickit.snarfs");

		$merchant_id = $this->ids['auth_merchant_id'];
		$billing_entity_external_id = $this->ids['auth_billing_entity_external'];
		$created_merchant_payment_type_map_id = $this->ids['auth_merchant_payment_type_map_id'];

		$user = logTestUserIn($this->ids['user_id']);
		$user_id = $user['user_id'];
		$balance_before = $user['balance'];
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
		$order_data['tip'] = 0.00;
		$order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$order_id = $order_resource->order_id;
		$ucid = $order_resource->ucid;
		$this->assertNull($order_resource->error);

		$order_record = OrderAdapter::staticGetRecord(array("ucid"=>$ucid),'OrderAdapter');
		$this->assertEquals(0.00,$order_record['tip_amt']);

		$_SERVER['TEST_VIO_TIMEOUT'] = 'true';
		$tip_amt = "2.00";
		$_SERVER['SOAPAction'] = "http://www.xoikos.com/webservices/ApplyTipToOrder";
		$xml_body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/"><OrderID>'.$ucid.'</OrderID><Amount>'.$tip_amt.'</Amount></ApplyTipToOrder></soap:Body></soap:Envelope>';
		$request = new Request();
		$request->url = "/pos/xoikos";
		$request->method = "POST";
		$request->body = $xml_body;
		$request->mimetype = 'Applicationxml';


		$pos_controller = new PosController($mt,$user,$request,5);
		$resource = $pos_controller->processV2request();


		$new_order_record = OrderAdapter::staticGetRecord(array("ucid"=>$ucid),'OrderAdapter');
		$this->assertEquals(0.00,$new_order_record['tip_amt']);
		$this->assertEquals($order_resource->grand_total,$new_order_record['grand_total']);

		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
			$balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
		}
		// check to make sure it was set to 'TIMEOUT'
		$this->assertEquals("PENDING",$balance_change_rows_by_user_id["$user_id-Authorize"]['notes'],"notes should show a pending authrization");
        $this->assertEquals("TIMEOUT",$balance_change_rows_by_user_id["$user_id-ChargeModification"]['notes'],"notes should show a pending authrization");

	}

    function testCaptureFail()
    {
//		$order_id = $order_record['order_id'];
//		$sql = "DELETE FROM Balance_Change WHERE order_id = $order_id AND process='CCpayment'";
//		$bca = new BalanceChangeAdapter($m);
//		$bca->_query($sql);
//		$sql = "UPDATE Balance_Change set notes = 'PENDING' WHERE order_id = $order_id AND process='Authorize' AND note='captured'";
//		$bca->_query($sql);

        setContext("com.splickit.snarfs");

        $merchant_id = $this->ids['auth_merchant_id'];
        $billing_entity_external_id = $this->ids['auth_billing_entity_external'];
        $created_merchant_payment_type_map_id = $this->ids['auth_merchant_payment_type_map_id'];

        $user = logTestUserIn($this->ids['user_id']);
        $user_id = $user['user_id'];
        $balance_before = $user['balance'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
        $order_data['tip'] = 0.00;
        $order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertNull($order_resource->error);
        $ucid = $order_resource->ucid;


        $tip_amt = 10.00 - $order_resource->grand_total;  // this will make a total charge of 10.00 which will cause the capture fail in the mock object
        $_SERVER['SOAPAction'] = "ApplyTipToOrder";
        $xml_body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/"><OrderID>'.$ucid.'</OrderID><Amount>'.$tip_amt.'</Amount></ApplyTipToOrder></soap:Body></soap:Envelope>';
        $request = new Request();
        $request->url = "/pos/xoikos";
        $request->method = "POST";
        $request->body = $xml_body;
        $request->mimetype = 'Applicationxml';

        $pos_controller = new PosController($mt,$user,$request,5);
        $resource = $pos_controller->processV2request();

        $new_order_record = OrderAdapter::staticGetRecord(array("ucid"=>$ucid),'OrderAdapter');
        $this->assertEquals(0.00,$new_order_record['tip_amt']);
        $this->assertEquals($order_resource->grand_total,$new_order_record['grand_total']);

        $balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),"BalanceChangeAdapter");
        $this->assertCount(2,$balance_change_records,"There should have been only 2 balance change records");
        $bc_hash_by_process = createHashmapFromArrayOfArraysByFieldName($balance_change_records,"process");
        $auth_record = $bc_hash_by_process['Authorize'];
        $this->assertEquals('PENDING',$auth_record['notes']);

        $this->assertNull($bc_hash_by_process['CCpayment']);

        $this->assertEquals(true,$resource->send_soap_response,"Should have found the send soap request parameter, and it should be true");
        $expected_soap_response = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrderResponse xmlns="http://www.xoikos.com/webservices/"><ApplyTipToOrderResult><Success>false</Success><ErrorMessage>there was an unknown error trying to capture the authorized charge.. Please try again.</ErrorMessage></ApplyTipToOrderResult></ApplyTipToOrderResponse></soap:Body></soap:Envelope>';
        $this->assertEquals($expected_soap_response,$resource->soap_body);

        //check to make sure Emails were staged to support
        $sql = "SELECT * FROM Merchant_Message_History ORDER BY map_id DESC limit 1";
        $mmha = new MerchantMessageHistoryAdapter($m);
        $options[TONIC_FIND_BY_SQL] = $sql;
        $mmh_resource = Resource::find($mmha,'',$options);
        $this->assertContains("subject=We had a Capture failure on an executed Order",$mmh_resource->info);
        $this->assertContains("Order_id: ".$order_id,$mmh_resource->message_text);

    }

	function testBadSOAPReqest()
	{
		$user = logTestUserIn($this->ids['user_id']);
		$tip_amt = "1.88";
		$_SERVER['SOAPAction'] = "http://www.xoikos.com/webservices/ApplyTipToOrder";
		$xml_body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/webservices/"><OrderID>'.$ucid.'</OrderID><Amount>'.$tip_amt.'</Amount></ApplyTipToOrder></soap:Body></soap:Envelope>';
		$request = new Request();
		$request->url = "/pos/xoikos";
		$request->method = "POST";
		$request->body = $xml_body;
		$request->mimetype = 'Applicationxml';


		$pos_controller = new PosController($mt,$user,$request,5);
		$resource = $pos_controller->processV2request();
		$this->assertEquals(422,$resource->http_code,"Should have had the fail http code on the resource");
		$this->assertNotNull($resource->error);
		$this->assertEquals("No valid order id submitted.",$resource->error);

	}

	function testApplyTipToCCChargeOrderShouldFail()
	{
		$merchant_resource = createNewTestMerchant($this->ids['menu_id'],array("new_payment"=>true));
		$merchant_id = $merchant_resource->merchant_id;
		$user_resource = createNewUserWithCCNoCVV();
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user['user_id'];
		$order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'Pickup', 'skip hours');
		$order_data['tip'] = 0.00;
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$ucid = $order_resource->ucid;
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000);
		$this->assertEquals('VioPaymentService', $order_resource->payment_service_used);
		$bca = new BalanceChangeAdapter($mimetypes);
		if ($balance_change_records = $bca->getRecords(array("order_id"=>$order_resource->order_id), $options)) {
			$balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
		}
		$this->assertCount(2, $balance_change_rows_by_user_id);
		$this->assertTrue(isset($balance_change_rows_by_user_id["$user_id-CCpayment"]),"Should have found the payment row");

		// now try to add a tip after the fact
		$tip_amt = "1.88";
		$_SERVER['SOAPAction'] = "http://www.xoikos.com/webservices/ApplyTipToOrder";
		$xml_body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/webservices/"><OrderID>'.$ucid.'</OrderID><Amount>'.$tip_amt.'</Amount></ApplyTipToOrder></soap:Body></soap:Envelope>';
		$request = new Request();
		$request->url = "/pos/xoikos";
		$request->method = "POST";
		$request->body = $xml_body;
		$request->mimetype = 'Applicationxml';


		$pos_controller = new PosController($mt,$user,$request,5);
		$resource = $pos_controller->processV2request();
		$this->assertEquals(422,$resource->http_code,"Should have had the fail http code on the resource");
		$this->assertNotNull($resource->error);
		$this->assertEquals("This order cannot be updated.",$resource->error);

	}



	function testBackwardCompatibilityForCashUsingNewMerchantPaymentMap()
	{
		$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
		$merchant_id = $merchant_resource->merchant_id;
		$mpta = new MerchantPaymentTypeAdapter($m);
		$sql = "DELETE FROM Merchant_Payment_Type WHERE merchant_id = $merchant_id";
		$mpta->_query($sql);
		$mptm_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_id,1000,$billing_entity_id);
		$mptm_resource2 = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_id,2000,$billing_entity_id);

		$user = logTestUserIn($this->ids['user_id']);

		$merchant_controller = new MerchantController($m,$user,$r);
		$merchant_controller->setMerchantId($merchant_id);
		$merchant = $merchant_controller->getMerchant();
		$this->assertEquals('Y',$merchant->accepts_cash,"should have had the accepts cash flag");
		$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
		$r = createRequestObject("/apiv2/cart/checkout","POST",json_encode($order_data));
        $place_order_controller = new PlaceOrderController($mt, $user, $r,5);
        $place_order_controller->current_time = getTomorrowTwelveNoonTimeStampDenver();
        $checkout_data_resource = $place_order_controller->processV2Request();
		$this->assertEquals("Cash",$checkout_data_resource->accepted_payment_types[0]['name']);

        $order_resource = placeOrderFromCheckoutResource($checkout_data_resource,$user,$merchant_id,0.00);
    	$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000);
		$this->assertEquals('CashPaymentService', $order_resource->payment_service_used);
	}

    function testGetCheckoutDataWithAcceptedPaymentTypesArray()
    {
		$merchant_id = $this->ids['merchant_id_with_cc_and_cash'];
    	
		$user_resource = createNewUserWithCC();
		$user = logTestUserResourceIn($user_resource);
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',10);
        $r = createRequestObject("/apiv2/cart/checkout","POST",json_encode($order_data));
        $place_order_controller = new PlaceOrderController($mt, $user, $r,5);
        $place_order_controller->current_time = getTomorrowTwelveNoonTimeStampDenver();
        $checkout_data_resource = $place_order_controller->processV2Request();

        $this->assertNotNull($checkout_data_resource->accepted_payment_types,"Should have found the payment array");
    	$payments_array = $checkout_data_resource->accepted_payment_types;
    	$this->assertCount(2, $payments_array);
    }
    
    function testAccountCreditPaymentIdInCheckoutDataAndUsedInOrder()
    {
    	$balance_before = 100;
    	$merchant_id = $this->ids['merchant_id_with_cc_and_cash'];
    	$user_resource = createNewUserWithCC();
    	$user_resource->balance = $balance_before;
    	$user_resource->save();
    	$user = logTestUserResourceIn($user_resource);
    	$user_id = $user['user_id'];
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
        $r = createRequestObject("/apiv2/cart/checkout","POST",json_encode($order_data));
        $place_order_controller = new PlaceOrderController($mt, $user, $r,5);
        $place_order_controller->current_time = getTomorrowTwelveNoonTimeStampDenver();
        $checkout_data_resource = $place_order_controller->processV2Request();

        $this->assertNotNull($checkout_data_resource->accepted_payment_types,"Should have found the payment array");
    	$payments_array = $checkout_data_resource->accepted_payment_types;
    	$this->assertCount(1, $payments_array);
    	$payments_array = createHashmapFromArrayOfArraysByFieldName($payments_array, 'name');
    	$this->assertNotNull($payments_array['Account Credit']);
    	$account_credit_payment_type = $payments_array['Account Credit'];
    	$this->assertEquals('5000', $account_credit_payment_type['splickit_accepted_payment_type_id']);
    	$this->assertEquals('1000', $account_credit_payment_type['merchant_payment_type_map_id']);
    	$order_data['merchant_payment_type_map_id'] = 1000;
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNull($order_resource->error);
		$order_id = $order_resource->order_id;
    	
		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		$balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options);
		$this->assertCount(1, $balance_change_records);
		$balance_change_record = $balance_change_records[0];
		$this->assertEquals($balance_before, $balance_change_record['balance_before']);
		$this->assertEquals($order_resource->grand_total, -$balance_change_record['charge_amt']);
		$this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_record['balance_after']);
		$this->assertEquals("Order",$balance_change_record['process']);
		
		$new_user_resource = Resource::find(new UserAdapter($mimetypes), "$user_id", $options);
		$this->assertTrue($new_user_resource->_exists);
		$this->assertEquals($balance_before-$order_resource->grand_total, $new_user_resource->balance);
    	
    }

    /*
     * backwards compatability test with payment map existing
     */
    
    function testGetPaymentMapFromNonPaymentTypeOrderCash()
    {
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	$merchant_payment_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_id, 1000, $billing_entity_id);
    	$created_merchant_payment_type_map_id = $merchant_payment_map_resource->id;
		$this->assertTrue($created_merchant_payment_type_map_id > 1000);
		
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', $note);
    	$order_data['cash'] = 'Y';
    	$order_resource = Resource::dummyfactory($order_data);
    	$place_order_controller = new PlaceOrderController($mt, $u, $r, 5);
    	$place_order_controller->setMerchantById($merchant_id);
    	$merchant_payment_map_id = $place_order_controller->getMerchantPaymentTypeMapIdFromOrderData($order_resource->getDataFieldsReally());
    	$this->assertEquals($created_merchant_payment_type_map_id, $merchant_payment_map_id);
    }

    /*
     * backwards compatability test with payent map existing
     */
    function testGetPaymentMapFromNonPaymentTypeOrderCreditCard()
    {
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
        $mpta = new MerchantPaymentTypeAdapter($m);
        $sql = "DELETE FROM Merchant_Payment_Type_Maps WHERE merchant_id = $merchant_id";
        $mpta->_query($sql);
    	
    	$merchant_id_key = generateCode(10);
    	$merchant_id_number = generateCode(5);
    	$data['vio_selected_server'] = 'sage';
    	$data['vio_merchant_id'] = $merchant_id;
    	$data['name'] = "Test Billing Entity";
    	$data['description'] = 'An entity to test with';
    	$data['merchant_id_key'] = $merchant_id_key;
    	$data['merchant_id_number'] = $merchant_id_number;
    	$data['identifier'] = $merchant_resource->alphanumeric_id;
    	$data['brand_id'] = $merchant_resource->brand_id;
    	
    	$card_gateway_controller = new CardGatewayController($mt, $u, $r);
    	$resource = $card_gateway_controller->createPaymentGateway($data);
    	$billing_entity_external_id = $resource->external_id; 
    	$this->assertTrue(is_a($resource, 'Resource'),'Should have returned a resource');
    	$this->assertNull($resource->error);
    	$this->assertNotNull($resource->id);
    	$this->assertNotNull($resource->merchant_payment_type_map);
    	$expected_string = "merchant_id_key=$merchant_id_key|merchant_id_number=$merchant_id_number";
    	$this->assertEquals($expected_string,$resource->credentials);
    	$created_merchant_payment_type_map_id = $resource->merchant_payment_type_map->id;

    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', $note);
    	$order_data_resource = Resource::dummyfactory($order_data);
    	$place_order_controller = new PlaceOrderController($mt, $u, $r, 5);
        $place_order_controller->setMerchantById($merchant_id);
    	$merchant_payment_map_id = $place_order_controller->getMerchantPaymentTypeMapIdFromOrderData($order_data_resource->getDataFieldsReally());
    	$this->assertEquals($created_merchant_payment_type_map_id, $merchant_payment_map_id);
    	return $merchant_id;
    }
    
    /**
     * @depends testGetPaymentMapFromNonPaymentTypeOrderCreditCard
     */
    
    function testPlaceOrderWithOutPaymentTypeIdButUseNewService($merchant_id)
    {
    	$user_resource = createNewUserWithCCNoCVV();
    	$user = logTestUserResourceIn($user_resource);
    	$place_order_adapter = new PlaceOrderAdapter($mimetypes);
    	$order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'Pickup', 'skip hours');
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000);
		$this->assertEquals('VioPaymentService', $order_resource->payment_service_used);
		$bca = new BalanceChangeAdapter($mimetypes);
		$records = $bca->getRecords(array("order_id"=>$order_resource->order_id));
		myerror_log("we have the records");
    }    
    
    /**
     * @expectedException     CashSubittedForNonCashMerchantException
     */
    
    function testGetPaymentMapFromNonPaymentTypeOrderCashNotCashMerchant()
    {
		$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', $note);
    	$order_data['cash'] = 'Y';
    	$order_resource = Resource::dummyfactory($order_data);
    	$place_order_controller = new PlaceOrderController($mt, $u, $r, 5);
        $place_order_controller->setMerchantById($merchant_id);
    	$merchant_payment_map_id = $place_order_controller->getMerchantPaymentTypeMapIdFromOrderData($order_resource->getDataFieldsReally());
    }
    
    function testRunCCThroughSystemMercury()
    {
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	$mercury_merchant_id = generateCode(10);
    	$mercury_password = "xyz";
    	$data['vio_selected_server'] = 'mercury';
    	$data['vio_merchant_id'] = $merchant_resource->merchant_id;
    	$data['name'] = "Test Billing Entity Mercury";
    	$data['description'] = 'An entity to test with';
    	$data['merchant_id'] = $mercury_merchant_id;
    	$data['password'] = $mercury_password;
    	$data['identifier'] = $merchant_resource->alphanumeric_id;
    	$data['brand_id'] = $merchant_resource->brand_id;
    	
    	$card_gateway_controller = new CardGatewayController($mt, $u, $r);
    	$resource = $card_gateway_controller->createPaymentGateway($data);
    	
    	$mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
    	$merchant_payment_map_record = $mptma->getRecord(array("merchant_id"=>$merchant_id));
    	
    	$user_resource = createNewUserWithCCNoCVV();
    	$user = logTestUserResourceIn($user_resource);
    	$user_id = $user['user_id'];
    	$balance_before = $user['balance'];
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
    	$order_data['merchant_payment_type_map_id'] = $merchant_payment_map_record['id'];
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$order_id = $order_resource->order_id;
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000);
		$this->assertEquals('VioPaymentService', $order_resource->payment_service_used);
		
		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
			$balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
		}
		$this->assertCount(2, $balance_change_rows_by_user_id);
		$this->assertEquals($balance_before, $balance_change_rows_by_user_id["$user_id-Order"]['balance_before']);
		$this->assertEquals($order_resource->grand_total, -$balance_change_rows_by_user_id["$user_id-Order"]['charge_amt']);
		$this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-Order"]['balance_after']);
		
		$this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_before']);
		$this->assertEquals($balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_before'], -$balance_change_rows_by_user_id["$user_id-CCpayment"]['charge_amt']);
		
		//get the billing entity
		$bea = new BillingEntitiesAdapter($mimetypes);
		$bea_record = $bea->getRecordFromPrimaryKey($merchant_payment_map_record['billing_entity_id']);
		$this->assertEquals($bea_record['external_id'], $balance_change_rows_by_user_id["$user_id-CCpayment"]['cc_processor']);
		
		$new_user_resource = SplickitController::getResourceFromId($user_id, 'User');
		$this->assertEquals($new_user_resource->balance, $balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_after']);    	
		return $order_id;

    }

    function testRunCCThroughSystemSage()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id'],array("authorize"=>true));
        $merchant_id = $merchant_resource->merchant_id;
        $mpta = new MerchantPaymentTypeAdapter($m);
        $sql = "DELETE FROM Merchant_Payment_Type_Maps WHERE merchant_id = $merchant_id";
        $mpta->_query($sql);

        //$merchant_resource = SplickitController::getResourceFromId($merchant_id, 'Merchant');
        $merchant_id_key = generateCode(10);
        $merchant_id_number = generateCode(5);
        $data['vio_selected_server'] = 'sage';
        $data['vio_merchant_id'] = $merchant_id;
        $data['name'] = "Test Billing Entity";
        $data['description'] = 'An entity to test with';
        $data['merchant_id_key'] = $merchant_id_key;
        $data['merchant_id_number'] = $merchant_id_number;
        $data['identifier'] = $merchant_resource->alphanumeric_id;
        $data['brand_id'] = $merchant_resource->brand_id;
        $data['process_type'] = "Authorize";

        $card_gateway_controller = new CardGatewayController($mt, $u, $r);
        $resource = $card_gateway_controller->createPaymentGateway($data);
        $billing_entity_external_id = $resource->external_id;
        $this->assertTrue(is_a($resource, 'Resource'),'Should have returned a resource');
        $this->assertNull($resource->error);
        $this->assertNotNull($resource->id);
        $this->assertNotNull($resource->merchant_payment_type_map);
        $expected_string = "merchant_id_key=$merchant_id_key|merchant_id_number=$merchant_id_number";
        $this->assertEquals($expected_string,$resource->credentials);
        $created_merchant_payment_type_map_id = $resource->merchant_payment_type_map->id;

        $user = logTestUserIn($this->ids['user_id']);
        $user_id = $user['user_id'];
        $balance_before = $user['balance'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
        $order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertNull($order_resource->error);
        $this->assertTrue($order_resource->order_id > 1000);
        $this->assertEquals('VioPaymentService', $order_resource->payment_service_used);

        $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
        if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
            $balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
        }
        $this->assertCount(2, $balance_change_rows_by_user_id);
        $this->assertEquals($balance_before, $balance_change_rows_by_user_id["$user_id-Order"]['balance_before']);
        $this->assertEquals($order_resource->grand_total, -$balance_change_rows_by_user_id["$user_id-Order"]['charge_amt']);
        $this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-Order"]['balance_after']);

        $this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-Authorize"]['balance_before']);
        $this->assertEquals($balance_change_rows_by_user_id["$user_id-Authorize"]['balance_before'], -$balance_change_rows_by_user_id["$user_id-Authorize"]['charge_amt']);
        $this->assertEquals($billing_entity_external_id, $balance_change_rows_by_user_id["$user_id-Authorize"]['cc_processor']);

        $new_user_resource = SplickitController::getResourceFromId($user_id, 'User');
        // $this->assertEquals($new_user_resource->balance, $balance_change_rows_by_user_id["$user_id-Authorize"]['balance_after']);
        return $order_id;
    }

    /**
     * @depends testRunCCThroughSystemSage
     */
    function testVoidOfVioCCpayment($order_id)
    {
        $order_resource = SplickitController::getResourceFromId($order_id, "Order");
        $user = logTestUserIn($order_resource->user_id);
        $order_controller = new OrderController(getM(), $user, $r, 5);
        $refund_results = $order_controller->issueOrderRefund($order_id, "0.00");
        $this->assertEquals("success", $refund_results['result']," should have gotten a success but: ".$refund_results['message']);
        $this->assertEquals(100,$refund_results['response_code']);

        // check to see if order was changed to cancelled
        $order_adapter = new OrderAdapter(getM());
        $new_order_resource = Resource::find($order_adapter,''.$order_id);
        $this->assertEquals('C', $new_order_resource->status);

        // check to see if the messages were cancelled

        $message_data['order_id'] = $order_id;
        $message_options[TONIC_FIND_BY_METADATA] = $message_data;
        $message_resources = Resource::findAll(new MerchantMessageHistoryAdapter($mimetypes),'',$message_options);
        $this->assertTrue(sizeof($message_resources) > 0);
        foreach ($message_resources as $message_resource) {
            $this->assertEquals('C', $message_resource->locked);
        }

        // now check the balance change table and the order_reversal table

        $balance_change_resource = Resource::find(new BalanceChangeAdapter($mimetypes),''.$refund_results['balance_change_id']);
        $this->assertEquals($expected, $actual);
        $this->assertEquals($balance_change_resource->charge_amt, $order_resource->grand_total);
        $this->assertEquals($balance_change_resource->process, 'CCvoid');
        $this->assertEquals($balance_change_resource->notes, 'Issuing a VioPaymentService VOID from the API: ');

        $adm_reversal_resource = Resource::find(new AdmOrderReversalAdapter($mimetypes),''.$refund_results['order_reversal_id']);
        $this->assertNull($adm_reversal_resource);
    }

    function testTimeoutMessage()
    {
        $merchant_id = $this->ids['merchant_id'];
        $user = logTestUserIn($this->ids['user_id']);
        $user_id = $user['user_id'];
        $balance_before = $user['balance'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
        $order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;

        $_SERVER['TEST_TIMEOUT'] = 'true';
		$request = new Request();
		$request->data = array("note"=>"Here is the note","employee_name"=>"Roberts");
        $order_controller = new OrderController($mt, $user, $request, 5);
        $refund_results = $order_controller->issueOrderRefund($order_id, "0.00");
        $this->assertEquals("failure", $refund_results['result']);
        $this->assertEquals("The request timed out reaching the cc processing facility.",$refund_results['message']);

		$record = AdmOrderReversalAdapter::staticGetRecord(array("order_id"=>$order_id),'AdmOrderReversalAdapter');
		$this->assertEquals("Here is the note - Roberts",$record['note'],"should have saved the note in the pending reversal record");
		$this->assertEquals($order_resource->grand_total,$record['amount'],"should have saved the amount in the pending reversal record");

    }

    function testVioTimeoutMessage()
    {
        $merchant_id = $this->ids['merchant_id'];
        $user = logTestUserIn($this->ids['user_id']);
        $user_id = $user['user_id'];
        $balance_before = $user['balance'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
        $order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;

        $_SERVER['TEST_VIO_TIMEOUT'] = 'true';
        $order_controller = new OrderController($mt, $user, $r, 5);
        $refund_results = $order_controller->issueOrderRefund($order_id, "0.00");
        $this->assertEquals("failure", $refund_results['result']);
        $this->assertEquals("The request timed out reaching the cc processing facility.",$refund_results['message']);

    }

    function testVoidOfExecutedOrderWithRecordInAdminReversal()
    {
		//$merchant_id = $this->ids['merchant_id'];
        $merchant_resource = createNewTestMerchant($this->ids['menu_id'],array("authorize"=>true));
        $merchant_id = $merchant_resource->merchant_id;


        $merchant_payment_type_map_records = MerchantPaymentTypeMapsAdapter::getMerchantPaymentTypes($merchant_id);
		$hash = createHashmapFromArrayOfArraysByFieldName($merchant_payment_type_map_records, 'splickit_accepted_payment_type_id');
		$merchant_payment_type_map_id = $hash['2000']['id'];
    	
    	$user = logTestUserIn($this->ids['user_id']);
    	$user_id = $user['user_id'];
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
    	$order_data['merchant_payment_type_map_id'] = $merchant_payment_type_map_id;
    	$created_order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNull($created_order_resource->error);
    	$order_id = $created_order_resource->order_id;
    	
    	$order_resource = SplickitController::getResourceFromId($order_id, "Order");
    	// set status to executed
    	$order_resource->status = 'E';
    	$order_resource->save();
    	
    	$user = logTestUserIn($order_resource->user_id);
		$request = new Request();
		$request->data['note'] = "Here I am";
    	$order_controller = new OrderController($mt, $user, $request, 5);
    	$refund_results = $order_controller->issueOrderRefund($order_id, "0.00");
    	$this->assertEquals("success", $refund_results['result']," should have gotten a success but: ".$refund_results['message']);
    	$this->assertEquals(100,$refund_results['response_code']);

    	// check to make sure order was NOT changed to cancelled 'N'
    	$order_adapter = new OrderAdapter($mimetypes);
    	$new_order_resource = Resource::find($order_adapter,''.$order_id);
    	$this->assertEquals('E', $new_order_resource->status);
    	
    	// now check the balance change table
    	$balance_change_resource = Resource::find(new BalanceChangeAdapter($mimetypes),''.$refund_results['balance_change_id']);
    	$this->assertEquals($order_resource->grand_total, $balance_change_resource->charge_amt);
    	$this->assertEquals('CCvoid', $balance_change_resource->process);
    	$this->assertEquals('Issuing a VioPaymentService VOID from the API: Here I am',$balance_change_resource->notes );

		// this was a void of an executed order so there shiould be an adm reversal record.
    	$adm_reversal_resource = Resource::find(new AdmOrderReversalAdapter($mimetypes),''.$refund_results['order_reversal_id']);
    	$this->assertNotNull($adm_reversal_resource);
    }
    
    function testAdminUserResponse()
    {
		$merchant_id = $this->ids['merchant_id'];
    	$user = logTestUserIn(101);
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',2);
    	$mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
    	$mptm_record = $mptma->getRecord(array("merchant_id"=>$merchant_id));
    	$order_data['merchant_payment_type_map_id'] = $mptm_record['id'];
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$order_id = $order_resource->order_id;
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000);
		$this->assertEquals('VioPaymentService', $order_resource->payment_service_used);
		
		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		$balance_change_record = $balance_change_adapter->getRecord(array("order_id"=>$order_id,"process"=>'CCpayment'), $options);
		$this->assertEquals('fakeprocessor', $balance_change_record['cc_processor']);
		$this->assertEquals('fake', substr($balance_change_record['cc_transaction_id'], 0,4));
				    	
    }
    
    function testRollOverToRefundIfVoidFails()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$user = logTestUserIn($this->ids['user_id']);
    	$balance_before = $user['balance'];
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',2);
    	$mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
    	$mptm_record = $mptma->getRecord(array("merchant_id"=>$merchant_id));
    	$order_data['merchant_payment_type_map_id'] = $mptm_record['id'];
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$order_id = $order_resource->order_id;
    	$created_order_resource = SplickitController::getResourceFromId($order_id, 'Order');    	
    	$created_order_resource->status = 'E';    	
    	$created_order_resource->save();
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000);
		$this->assertEquals('VioPaymentService', $order_resource->payment_service_used);

		$request = new Request();
		$request->data['note'] = 'Sum dum note';
		$order_controller = new OrderController($mt, $user, $request, 5);
		setSessionProperty("force_void_fail", "true");
    	$refund_results = $order_controller->issueOrderRefund($order_id, "0.00");
    	$this->assertEquals("success", $refund_results['result']," should have gotten a success but: ".$refund_results['message']);
    	$this->assertEquals(100,$refund_results['response_code']);
		
    	$balance_change_resource = Resource::find(new BalanceChangeAdapter($mimetypes),''.$refund_results['balance_change_id']);
    	$this->assertEquals($expected, $actual);
    	$this->assertEquals($balance_change_resource->charge_amt, $order_resource->grand_total);
    	$this->assertEquals($balance_change_resource->process, 'CCrefund');
    	$this->assertEquals($balance_change_resource->notes, 'Issuing a VioPaymentService REFUND from the API: Sum dum note');
    	
    	$adm_reversal_resource = Resource::find(new AdmOrderReversalAdapter($mimetypes),''.$refund_results['order_reversal_id']);
    	$this->assertNotNull($adm_reversal_resource);
    	$this->assertEquals($adm_reversal_resource->order_id, $order_id);
    	$this->assertEquals($adm_reversal_resource->amount, $order_resource->grand_total);
    	$this->assertEquals($adm_reversal_resource->credit_type, 'G');
    	$this->assertEquals($adm_reversal_resource->note, 'Issuing a VioPaymentService refund from the API: Sum dum note');
    }
    
    function testCreateOrderForPartialForSameDayRefund()
    {
    	setContext("com.splickit.snarfs");
    	$merchant_id = $this->ids['merchant_id'];
    	$user_resource = createNewUserWithCCNoCVV();
    	$user_resource->uuid = "ppppp-ppppp-ppppp-ppppp";
    	$user_resource->save();
    	$user = logTestUserResourceIn($user_resource);
    	$balance_before = $user['balance'];
    	
    	// clear tha activity list
    	$order_adapter = new OrderAdapter($mimetypes);
    	$sql = "UPDATE Activity_History SET locked = 'E' WHERE 1=1";
    	$order_adapter->_query($sql);
    	
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',2);
    	$mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
    	$mptm_record = $mptma->getRecord(array("merchant_id"=>$merchant_id));
    	$order_data['merchant_payment_type_map_id'] = $mptm_record['id'];
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$order_id = $order_resource->order_id;
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000);
		$this->assertEquals('VioPaymentService', $order_resource->payment_service_used);

		$request = new Request();
		$request->data['note'] = 'Sum dum note';
		$order_controller = new OrderController($mt, $user, $request, 5);
    	$order_controller->updateOrderStatusById($order_id,'E');
		$refund_results = $order_controller->issueOrderRefund($order_id, "1.01");
		$data['order_id'] = $order_id;
		$data['refund_results'] = $refund_results;
		return $data;
    }
    
    /**
     * @depends testCreateOrderForPartialForSameDayRefund
     */
    function testProcessPartialOnUnsettledTransaction($data)
    {
		$refund_results = $data['refund_results'];
    	$this->assertEquals("success", $refund_results['result']," should have gotten a success but: ".$refund_results['message']);
    	$this->assertEquals(101,$refund_results['response_code'],"Reponse code should have been 101 to indicate a schedueled partial");	
    }
    
    /**
     * @depends testCreateOrderForPartialForSameDayRefund 
     */
    function testSendEmailToUserIndicatingRefundWillHappenWithinTheNextDay($data)
    {
    	$order_id = $data['order_id'];
    	$order_resource = SplickitController::getResourceFromId($order_id, 'Order');
    	$user_resource = SplickitController::getResourceFromId($order_resource->user_id, 'User');
    	$email = $user_resource->email;
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$record = $mmha->getRecord(array("message_delivery_addr"=>$email,"info"=>"subject=Account Status;from=Snarfs;","message_format"=>'E',"locked"=>'N'));
    	$this->assertNotNull($record);
    	$email_text = $record['message_text'];
    	$this->assertContains("Your Snarfs order #$order_id, has been scheduled for a refund of $1.01.", $email_text);
    	$this->assertContains("Please note, this can take up to 24hrs to process.",$email_text);
    	// make th messge seem sent.
    	$sql = "UPDATE Merchant_Message_History SET locked = 'S' where map_id = ".$record['map_id'];
    	$mmha->_query($sql);
    }

    /**
     * @depends testCreateOrderForPartialForSameDayRefund 
     */
    function testSchedulePartialForSameDayRefund($data)
    {
    	$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
    	$order_id = $data['order_id'];
    	$activity_data['info'] = "object=OrderController;method=executeScheduledPartialOrderRefund;thefunctiondatastring=$order_id,1.01,Sum dum note";
    	$activity_data['activity'] = "ExecuteObjectFunction";
    	$created_activity_history_resource = $activity_history_adapter->getExactResourceFromData($activity_data);
    	if (date('H') < 23) {
    		$expected_ts = mktime(23,0,0,date('m'),date('d'),date('year'));
    		$error_message = "should have been 11pm later today";
    	} else {
    		$expected_ts = mktime(23,0,0,date('m'),date('d')+1,date('year'));
    		$error_message = "should have been 11pm tomorrow";
    	}
    	$this->assertEquals($expected_ts, $created_activity_history_resource->doit_dt_tm,$error_message);
    	//$this->assertTrue($created_activity_history_resource->doit_dt_tm > (time()+(23*60*60)),"do it time should have been more than 23 hours in the future");
    	//$this->assertTrue($created_activity_history_resource->doit_dt_tm < (time()+(25*60*60)),"do it time should have been less than 25 hours in the future");
    	return $created_activity_history_resource;
    	
    }
    
    /**
     * @depends testSchedulePartialForSameDayRefund 
     */
    function testDoubleFailureOfRefund($created_activity_history_resource)
    {	
    	// now reset the doit date time so it can get picked up
    	$created_activity_history_resource->doit_dt_tm = time() -10;
    	$created_activity_history_resource->save();
    	
    	$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
    	$activity_history_resources = $activity_history_adapter->getAvailableActivityResourcesArray($aha_options);
    	$activity_history_resource = $activity_history_resources[0];
    	$this->assertNotNull($activity_history_resource,"should have found the activity to do the partial refund");
    	$activity = $activity_history_adapter->getActivityFromUnlockedActivityHistoryResource($activity_history_resource);
    	$this->assertEquals('ExecuteObjectFunctionActivity', get_class($activity));
    	//$execute_object_function_activity = new ExecuteObjectFunctionActivity($activity_history_resource);
    	$this->assertFalse($activity->doit(),"Should have returned a false from executing the activity since it was a double failure");
    	
    	$data_string = $activity->data['thefunctiondatastring'];
    	$s = explode(',', $data_string);
    	$order_id = $s[0];
    	$order_resource = SplickitController::getResourceFromId($order_id, 'Order');
    	$user_resource = SplickitController::getResourceFromId($order_resource->user_id, 'User');
    	$email = $user_resource->email;
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$record = $mmha->getRecord(array("message_delivery_addr"=>$email,"info"=>"subject=Account Status;from=Snarfs;","message_format"=>'E',"locked"=>'N'));
    	$this->assertNull($record,"Should not have found another email to the customer since the refund failed on the second try");

    	$support_email = getProperty('email_string_support');
    	$support_records = $mmha->getRecords(array("message_delivery_addr"=>$support_email,"message_format"=>'E'));
		$support_record = array_pop($support_records);
		$this->assertContains("MANUAL INTERVENTION REQUIRED", $support_record['info']);
		$this->assertContains("We had a delayed partial refund fail on the next day.  order_id: $order_id.", $support_record['message_text']);

		// now make sure no records in the db for a refund
    }
    	
    /**
     * @depends testSchedulePartialForSameDayRefund 
     */
    function testSuccessfullStagedPartialRefund($activity_history_resource)
    {	
    	$created_activity_history_resource = SplickitController::getResourceFromId($activity_history_resource->activity_id, 'ActivityHistory'); 
    	$created_activity_history_resource->locked = 'N';
    	$created_activity_history_resource->save();
    	$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
    	$activity = $activity_history_adapter->getActivityFromUnlockedActivityHistoryResource($created_activity_history_resource);
    	$data_string = $activity->data['thefunctiondatastring'];
    	$s = explode(',', $data_string);
    	$order_id = $s[0];

		//get rid of adm record
		$sql = "DELETE FROM adm_order_reversal WHERE order_id = $order_id";
		$activity_history_adapter->_query($sql);

		$order_resource = SplickitController::getResourceFromId($order_id, 'Order');
    	$user_resource = SplickitController::getResourceFromId($order_resource->user_id, 'User');
    	$user_resource->uuid = createUUID();
    	$user_resource->save();
    	$this->assertTrue($activity->doit(),"Should have returned a True from executing the activity since it passed");
    	
    	$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
    	$bcr = $balance_change_adapter->getRecord(array("user_id"=>$user_resource->user_id,"order_id"=>$order_id,"process"=>"CCrefund"));
    	$this->assertEquals(1.01, $bcr['charge_amt']);
    	$this->assertEquals('Issuing a credit card REFUND from the API: Delayed Partial Order Refund. Sum dum note', $bcr['notes']);
    	
    	$adm_reversal_adapter = new AdmOrderReversalAdapter($mimetypes);
    	$adm_reversal_record = $adm_reversal_adapter->getRecord(array("order_id"=>$order_id));
    	$this->assertNotNull($adm_reversal_record);
    	$this->assertEquals(1.01, $adm_reversal_record['amount']); 
    	$this->assertEquals('Issuing a credit card refund from the API: Delayed Partial Order Refund. Sum dum note',$adm_reversal_record['note']);
    }


    
    function testCardFailureSage()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
    	$merchant_payment_type_map = $mptma->getRecord(array("merchant_id"=>$merchant_id,"splickit_accepted_payment_type_id"=>2000));
    	
    	$user_resource = createNewUserWithCC();
    	$user_resource->uuid = '1234-5678-9012-3456';
    	$user_resource->save();
    	
    	$user = logTestUserResourceIn($user_resource);
    	$user_id = $user['user_id'];
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
    	$order_data['merchant_payment_type_map_id'] = $merchant_payment_type_map['id'];
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNotNull($order_resource->error);
    	$this->assertEquals("We're sorry but your credit card was declined.", $order_resource->error);
    }
    
    function testCardFailureSageCustomError()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
    	$merchant_payment_type_map = $mptma->getRecord(array("merchant_id"=>$merchant_id,"splickit_accepted_payment_type_id"=>2000));
    	$custom_error = "We are sorry, but you are a bad person and cannot be allowed to order lunch";
    	
    	$sqls = "INSERT INTO Lookup (`type_id_field`,`type_id_value`,`type_id_name`) VALUES ('vio_payment_error','BADPERSON','$custom_error')";
    	$mptma->_query($sqls);
    	
    	$user_resource = createNewUserWithCC();
    	$user_resource->uuid = '1234-5678-9012-8888';
    	$user_resource->save();
    	
    	$user = logTestUserResourceIn($user_resource);
    	$user_id = $user['user_id'];
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
    	$order_data['merchant_payment_type_map_id'] = $merchant_payment_type_map['id'];
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNotNull($order_resource->error);
    	$this->assertEquals($custom_error, $order_resource->error);
    }
    
    function testCardFailureSageUnknownError()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
    	$merchant_payment_type_map = $mptma->getRecord(array("merchant_id"=>$merchant_id,"splickit_accepted_payment_type_id"=>2000));
    	
    	$user_resource = createNewUserWithCC();
    	$user_resource->uuid = '1234-5678-9012-XXXX';
    	$user_resource->save();
    	
    	$user = logTestUserResourceIn($user_resource);
    	$user_id = $user['user_id'];
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
    	$order_data['merchant_payment_type_map_id'] = $merchant_payment_type_map['id'];
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNotNull($order_resource->error);
    	$this->assertEquals("We're sorry but there was an unrecognized error running your credit card and the charge did not go through.", $order_resource->error);
    }

    function testBlankCardError()
    {
        $sql = "INSERT INTO Lookup VALUES(NULL ,'vio_payment_error','cannot purchase with blank credit_card','We''re very sorry about this, but our credit card processor has indicated that your card data was not saved correctly, you will need to re-enter it.','Y',now(),now(),'N')";
        $merchant_id = $this->ids['merchant_id'];
        $mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
        $mptma->_query($sql);
        $merchant_payment_type_map = $mptma->getRecord(array("merchant_id"=>$merchant_id,"splickit_accepted_payment_type_id"=>2000));

        $user_resource = createNewUserWithCC();
        $user_resource->uuid = '12340-56789-BLANK-CARDX';
        $user_resource->save();

        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
        $order_data['merchant_payment_type_map_id'] = $merchant_payment_type_map['id'];
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $this->assertNotNull($order_resource->error);
        $this->assertEquals("We're very sorry about this, but our credit card processor has indicated that your card data was not saved correctly, you will need to re-enter it.", $order_resource->error);
    }

    function testVIOCurlError()
    {
        $merchant_id = $this->ids['merchant_id'];
        $mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
        $merchant_payment_type_map = $mptma->getRecord(array("merchant_id"=>$merchant_id,"splickit_accepted_payment_type_id"=>2000));

        $user_resource = createNewUserWithCC();
        $user_resource->uuid = '12345-56789-error-vcurl';
        $user_resource->save();

        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
        $order_data['merchant_payment_type_map_id'] = $merchant_payment_type_map['id'];
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $this->assertNotNull($order_resource->error);
        $this->assertEquals("We're sorry but there was an connection problem reaching the credit card processing facility and your order did not go through. Please try again.", $order_resource->error);
        $this->assertEquals(500,$order_resource->http_code);
    }

    function testVIOResetConnetionError()
    {
        $merchant_id = $this->ids['merchant_id'];
        $mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
        $merchant_payment_type_map = $mptma->getRecord(array("merchant_id"=>$merchant_id,"splickit_accepted_payment_type_id"=>2000));

        $user_resource = createNewUserWithCC();
        $user_resource->uuid = '12340-56789-SERVE-RESET';
        $user_resource->save();

        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
        $order_data['merchant_payment_type_map_id'] = $merchant_payment_type_map['id'];
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $this->assertNotNull($order_resource->error);
        $this->assertEquals("We're sorry but there was an connection problem reaching the credit card processing facility and your order did not go through. Please try again.", $order_resource->error);
        $this->assertEquals(422,$order_resource->http_code);
    }

    function testVIOunknownError()
    {
        $merchant_id = $this->ids['merchant_id'];
        $mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
        $merchant_payment_type_map = $mptma->getRecord(array("merchant_id" => $merchant_id, "splickit_accepted_payment_type_id" => 2000));

        $user_resource = createNewUserWithCC();
        $user_resource->uuid = '12340-56789-INVLD-NUMBR';
        $user_resource->save();

        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
        $order_data['merchant_payment_type_map_id'] = $merchant_payment_type_map['id'];
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $this->assertNotNull($order_resource->error);
        $this->assertEquals("We're sorry but there was an unrecognized error running your credit card and the charge did not go through.", $order_resource->error);
        $this->assertEquals(422, $order_resource->http_code);
    }
    
    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
    	SplickitCache::flushAll();
    	$db = DataBase::getInstance();
    	$mysqli = $db->getConnection();
    	$mysqli->begin_transaction(); ;
    	
    	setSessionProperty("email_string_support", "sumdumemail@email.com");
    	//$skin_resource = createWorldHqSkin();
    	$skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty('pfftskin','pfftbrand',null,null);
    	$ids['skin_id'] = $skin_resource->skin_id;
    	$ids['context'] = $skin_resource->external_identifier;
        setContext($skin_resource->external_identifier);

        $brand_id = $skin_resource->brand_id;
        $brand_resource = Resource::find(new BrandAdapter($mimetypes),"$brand_id");
        $brand_resource->loyalty = 'Y';
        $brand_resource->save();

        $blr_data['brand_id'] = $brand_id;
        $blr_data['loyalty_type'] = 'splickit_earn';
        $brand_loyalty_rules_resource = Resource::factory(new BrandLoyaltyRulesAdapter($m),$blr_data);
        $brand_loyalty_rules_resource->save();
        $ids['blr_resource'] = $brand_loyalty_rules_resource->getRefreshedResource();


        //map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;
    	
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);

    	$merchant_resource = createNewTestMerchant($menu_id);
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
    	$merchant_resource2 = createNewTestMerchant($menu_id);
    	$merchant_id2 = $merchant_resource2->merchant_id;
    	attachMerchantToSkin($merchant_id2, $ids['skin_id']);
    	$ids['merchant_id_with_cc_and_cash'] = $merchant_id2;

    	$billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);
    	
    	$merchant_payment_type_map_adapter = new MerchantPaymentTypeMapsAdapter($mimetypes);
    	$cc_merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_id2, 2000, $billing_entity_resource->id);
    	$ids['cc_billing_entity_id'] = $cc_merchant_payment_type_resource->billing_entity_id;
    	
    	// create cash merchang payment type record
    	$merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_id2, 1000, $billing_entity_id);

		// create merchant that does authorization
		$merchant_resource = createNewTestMerchant($menu_id,array("authorize"=>true));
		$merchant_resource->merchant_external_id = 88888;
		$merchant_resource->save();
		$merchant_id = $merchant_resource->merchant_id;

        $heartland_billing_entity_resource = createHeartlandBillingEntity($merchant_resource->brand_id);
        $ids['heartland_billing_entity_resource'] = $heartland_billing_entity_resource;

//		$merchant_id_key = generateCode(10);
//		$merchant_id_number = generateCode(5);
//		$data['vio_selected_server'] = 'sage';
//		$data['vio_merchant_id'] = $merchant_id;
//		$data['name'] = "Test Billing Entity";
//		$data['description'] = 'An entity to test with';
//		$data['merchant_id_key'] = $merchant_id_key;
//		$data['merchant_id_number'] = $merchant_id_number;
//		$data['identifier'] = $merchant_resource->alphanumeric_id;
//		$data['brand_id'] = $merchant_resource->brand_id;
//
//		$card_gateway_controller = new CardGatewayController($mt, $u, $r);
//		$resource = $card_gateway_controller->createPaymentGateway($data);
//		$resource->process_type = 'authorize';
//		$resource->save();
		$ids['auth_merchant_id'] = $merchant_id;
		$ids['auth_merchant_payment_type_map_id'] = $merchant_resource->merchant_payment_type_map_id;
		$ids['auth_billing_entity_external'] = $merchant_resource->billing_entity_external_id;

    	$user_resource = createNewUserWithCCNoCVV();
    	$ids['user_id'] = $user_resource->user_id;
    	
    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;


        $sqls = "INSERT INTO Lookup (`type_id_field`,`type_id_value`,`type_id_name`) VALUES ('vio_payment_error','The remote server reset the connection','We''re sorry but there was an connection problem reaching the credit card processing facility and your order did not go through. Please try again.')";
        $lookup_adapter = new LookupAdapter($m);
        $lookup_adapter->_query($sqls);
    }
    
	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();
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
    PaymentFrameworkFeaturesTest::main();
}

?>