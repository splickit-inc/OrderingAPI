<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class CVVValidationTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }
    
    function testFailedCVVFromVIOReturn()
    {
    	$json = '{"payment": {"captures": [],"refunds": [],"response": {"cvv_result": {"message":"Fail","code":"F"},"avs_result": {"postal_match": null,"code": null,"message": null,"street_match": null},"params": {"reference":"E9PEBFvQe0","recurring":"0","order_number":"1xxxxxxxxxxx9881","risk":"00","avs_result":"","cvv_result":"M","front_end":"10","message":"APPROVED 000001","success":"A","code":"000001"},"authorization":"E9PEBFvQe0;bankcard","success?": true,"test": true,"message":"APPROVED 000001"},"void_response": {},"_id":"542431acdd39162f950003f4","account_id":"535fe8fd9811b4770a00000c","address1":"1305 Pearl Street","amount":"1.00","authorization_id":"E9PEBFvQe0","cents": 100,"city":"Boulder","country":"USA","created_at":"2014-09-25T15:15:56Z","credit_card_brand":"visa","credit_card_id":"5424319ddd39162f950003f1","credit_card_identifier":"6409-j6p58-yx9t3-wgdez","currency":"USD","destination_id":"5424319ddd39162f950003f3","destination_identifier":"F7L9US616X36CQ8139I8","destination_kind":"sage-gateway-388","destination_name":"sage_gateway_388","destination_request_time": 1.595514078,"first_name":"sumdum","gateway_order_id":"1xxxxxxxxxxx9881","identifier":"c96e786c-c8cd-4207-8aca-9e7a8db02e96","kind":"purchase","last_name":"guy","require_cvv": false,"void_status":"unvoided","state":"CO","updated_at":"2014-09-25T15:15:57Z","transaction_id":"E9PEBFvQe0","transacted": true,"status":"success","zip":"12345","destination":"F7L9US616X36CQ8139I8","credit_card":"6409-j6p58-yx9t3-wgdez","links": [{"rel":"self","href":"https://api-staging.value.io/v1/payments/542431acdd39162f950003f4"},{"rel":"index","href":"https://api-staging.value.io/v1/payments"}]}}';
    	$vio_payment_results = json_decode($json,true);
    	$vio_payment_service = new VioPaymentService($data);
    	$this->assertFalse($vio_payment_service->isCvvValidatedFromVIOPaymentResultArray($vio_payment_results));
    }
    
    function testValidatedCVVFromVIOReturn()
    {
    	$json = '{"payment": {"captures": [],"refunds": [],"response": {"cvv_result": {"message":"Match","code":"M"},"avs_result": {"postal_match": null,"code": null,"message": null,"street_match": null},"params": {"reference":"E9PEBFvQe0","recurring":"0","order_number":"1xxxxxxxxxxx9881","risk":"00","avs_result":"","cvv_result":"M","front_end":"10","message":"APPROVED 000001","success":"A","code":"000001"},"authorization":"E9PEBFvQe0;bankcard","success?": true,"test": true,"message":"APPROVED 000001"},"void_response": {},"_id":"542431acdd39162f950003f4","account_id":"535fe8fd9811b4770a00000c","address1":"1305 Pearl Street","amount":"1.00","authorization_id":"E9PEBFvQe0","cents": 100,"city":"Boulder","country":"USA","created_at":"2014-09-25T15:15:56Z","credit_card_brand":"visa","credit_card_id":"5424319ddd39162f950003f1","credit_card_identifier":"6409-j6p58-yx9t3-wgdez","currency":"USD","destination_id":"5424319ddd39162f950003f3","destination_identifier":"F7L9US616X36CQ8139I8","destination_kind":"sage-gateway-388","destination_name":"sage_gateway_388","destination_request_time": 1.595514078,"first_name":"sumdum","gateway_order_id":"1xxxxxxxxxxx9881","identifier":"c96e786c-c8cd-4207-8aca-9e7a8db02e96","kind":"purchase","last_name":"guy","require_cvv": false,"void_status":"unvoided","state":"CO","updated_at":"2014-09-25T15:15:57Z","transaction_id":"E9PEBFvQe0","transacted": true,"status":"success","zip":"12345","destination":"F7L9US616X36CQ8139I8","credit_card":"6409-j6p58-yx9t3-wgdez","links": [{"rel":"self","href":"https://api-staging.value.io/v1/payments/542431acdd39162f950003f4"},{"rel":"index","href":"https://api-staging.value.io/v1/payments"}]}}';
    	$vio_payment_results = json_decode($json,true);
    	$vio_payment_service = new VioPaymentService($data);
    	$this->assertTrue($vio_payment_service->isCvvValidatedFromVIOPaymentResultArray($vio_payment_results));
    }
    
    function testCVVCheckIsOff()
    {
    	$flags = '1C20000001';
    	$vio_payment_service = new VioPaymentService($data);
    	$this->assertFalse($vio_payment_service->isCVVCheckFlagSetOnUserFlags($flags));
    }
    
    function testCVVCheckIsON()
    {
    	$flags = '1C21000001';
    	$vio_payment_service = new VioPaymentService($data);
    	$this->assertTrue($vio_payment_service->isCVVCheckFlagSetOnUserFlags($flags));
    }
    
    function testDoCVVCHecksWithFlagModsNoCheck()
    {
    	
    	$json = '{"payment": {"captures": [],"refunds": [],"response": {"cvv_result": {"message":"Fail","code":"F"},"avs_result": {"postal_match": null,"code": null,"message": null,"street_match": null},"params": {"reference":"E9PEBFvQe0","recurring":"0","order_number":"1xxxxxxxxxxx9881","risk":"00","avs_result":"","cvv_result":"M","front_end":"10","message":"APPROVED 000001","success":"A","code":"000001"},"authorization":"E9PEBFvQe0;bankcard","success?": true,"test": true,"message":"APPROVED 000001"},"void_response": {},"_id":"542431acdd39162f950003f4","account_id":"535fe8fd9811b4770a00000c","address1":"1305 Pearl Street","amount":"1.00","authorization_id":"E9PEBFvQe0","cents": 100,"city":"Boulder","country":"USA","created_at":"2014-09-25T15:15:56Z","credit_card_brand":"visa","credit_card_id":"5424319ddd39162f950003f1","credit_card_identifier":"6409-j6p58-yx9t3-wgdez","currency":"USD","destination_id":"5424319ddd39162f950003f3","destination_identifier":"F7L9US616X36CQ8139I8","destination_kind":"sage-gateway-388","destination_name":"sage_gateway_388","destination_request_time": 1.595514078,"first_name":"sumdum","gateway_order_id":"1xxxxxxxxxxx9881","identifier":"c96e786c-c8cd-4207-8aca-9e7a8db02e96","kind":"purchase","last_name":"guy","require_cvv": false,"void_status":"unvoided","state":"CO","updated_at":"2014-09-25T15:15:57Z","transaction_id":"E9PEBFvQe0","transacted": true,"status":"success","zip":"12345","destination":"F7L9US616X36CQ8139I8","credit_card":"6409-j6p58-yx9t3-wgdez","links": [{"rel":"self","href":"https://api-staging.value.io/v1/payments/542431acdd39162f950003f4"},{"rel":"index","href":"https://api-staging.value.io/v1/payments"}]}}';
    	$vio_payment_results = json_decode($json,true);
    	$vio_payment_service = new VioPaymentService($data);
    	$vio_payment_service->billing_user_resource = createNewUserWithCCNoCVV();
    	$this->assertTrue($vio_payment_service->isItAValidCvvResponseForBillingUserState($vio_payment_results));
    }
    
    function testDoCVVCHecksWithFlagModsYesCheckBadCVV()
    {
    	$user_resource = $this->ids['user_resource'];
    	$user_resource->flags = '1C21000001';
    	$user_resource->save();
    	$json = '{"payment": {"captures": [],"refunds": [],"response": {"cvv_result": {"message":"Fail","code":"F"},"avs_result": {"postal_match": null,"code": null,"message": null,"street_match": null},"params": {"reference":"E9PEBFvQe0","recurring":"0","order_number":"1xxxxxxxxxxx9881","risk":"00","avs_result":"","cvv_result":"M","front_end":"10","message":"APPROVED 000001","success":"A","code":"000001"},"authorization":"E9PEBFvQe0;bankcard","success?": true,"test": true,"message":"APPROVED 000001"},"void_response": {},"_id":"542431acdd39162f950003f4","account_id":"535fe8fd9811b4770a00000c","address1":"1305 Pearl Street","amount":"1.00","authorization_id":"E9PEBFvQe0","cents": 100,"city":"Boulder","country":"USA","created_at":"2014-09-25T15:15:56Z","credit_card_brand":"visa","credit_card_id":"5424319ddd39162f950003f1","credit_card_identifier":"6409-j6p58-yx9t3-wgdez","currency":"USD","destination_id":"5424319ddd39162f950003f3","destination_identifier":"F7L9US616X36CQ8139I8","destination_kind":"sage-gateway-388","destination_name":"sage_gateway_388","destination_request_time": 1.595514078,"first_name":"sumdum","gateway_order_id":"1xxxxxxxxxxx9881","identifier":"c96e786c-c8cd-4207-8aca-9e7a8db02e96","kind":"purchase","last_name":"guy","require_cvv": false,"void_status":"unvoided","state":"CO","updated_at":"2014-09-25T15:15:57Z","transaction_id":"E9PEBFvQe0","transacted": true,"status":"success","zip":"12345","destination":"F7L9US616X36CQ8139I8","credit_card":"6409-j6p58-yx9t3-wgdez","links": [{"rel":"self","href":"https://api-staging.value.io/v1/payments/542431acdd39162f950003f4"},{"rel":"index","href":"https://api-staging.value.io/v1/payments"}]}}';
    	$vio_payment_results = json_decode($json,true);
    	$vio_payment_service = new VioPaymentService($data);
    	$vio_payment_service->billing_user_resource = $user_resource;
    	$this->assertFalse($vio_payment_service->isItAValidCvvResponseForBillingUserState($vio_payment_results));
    }
    
    function testDoCVVCHecksWithFlagModsYesCheckGoodCVV()
    {
    	$user_resource = $this->ids['user_resource'];
    	$user_resource->flags = '1C21000001';
    	$user_resource->save();
    	$json = '{"payment": {"captures": [],"refunds": [],"response": {"cvv_result": {"message":"Match","code":"M"},"avs_result": {"postal_match": null,"code": null,"message": null,"street_match": null},"params": {"reference":"E9PEBFvQe0","recurring":"0","order_number":"1xxxxxxxxxxx9881","risk":"00","avs_result":"","cvv_result":"M","front_end":"10","message":"APPROVED 000001","success":"A","code":"000001"},"authorization":"E9PEBFvQe0;bankcard","success?": true,"test": true,"message":"APPROVED 000001"},"void_response": {},"_id":"542431acdd39162f950003f4","account_id":"535fe8fd9811b4770a00000c","address1":"1305 Pearl Street","amount":"1.00","authorization_id":"E9PEBFvQe0","cents": 100,"city":"Boulder","country":"USA","created_at":"2014-09-25T15:15:56Z","credit_card_brand":"visa","credit_card_id":"5424319ddd39162f950003f1","credit_card_identifier":"6409-j6p58-yx9t3-wgdez","currency":"USD","destination_id":"5424319ddd39162f950003f3","destination_identifier":"F7L9US616X36CQ8139I8","destination_kind":"sage-gateway-388","destination_name":"sage_gateway_388","destination_request_time": 1.595514078,"first_name":"sumdum","gateway_order_id":"1xxxxxxxxxxx9881","identifier":"c96e786c-c8cd-4207-8aca-9e7a8db02e96","kind":"purchase","last_name":"guy","require_cvv": false,"void_status":"unvoided","state":"CO","updated_at":"2014-09-25T15:15:57Z","transaction_id":"E9PEBFvQe0","transacted": true,"status":"success","zip":"12345","destination":"F7L9US616X36CQ8139I8","credit_card":"6409-j6p58-yx9t3-wgdez","links": [{"rel":"self","href":"https://api-staging.value.io/v1/payments/542431acdd39162f950003f4"},{"rel":"index","href":"https://api-staging.value.io/v1/payments"}]}}';
    	$vio_payment_results = json_decode($json,true);
    	$vio_payment_service = new VioPaymentService($data);
    	$vio_payment_service->billing_user_resource = $user_resource;
    	$this->assertTrue($vio_payment_service->isItAValidCvvResponseForBillingUserState($vio_payment_results));
    }
    
    function testDoCVVCHecksWithFlagModsYesButNoCvvFieldInResponse()
    {
    	$user_resource = $this->ids['user_resource'];
    	$user_resource->flags = '1C21000001';
    	$user_resource->save();
    	$json = '{"payment": {"captures": [],"refunds": [],"response": {"avs_result": {"postal_match": null,"code": null,"message": null,"street_match": null},"params": {"reference":"E9PEBFvQe0","recurring":"0","order_number":"1xxxxxxxxxxx9881","risk":"00","avs_result":"","cvv_result":"M","front_end":"10","message":"APPROVED 000001","success":"A","code":"000001"},"authorization":"E9PEBFvQe0;bankcard","success?": true,"test": true,"message":"APPROVED 000001"},"void_response": {},"_id":"542431acdd39162f950003f4","account_id":"535fe8fd9811b4770a00000c","address1":"1305 Pearl Street","amount":"1.00","authorization_id":"E9PEBFvQe0","cents": 100,"city":"Boulder","country":"USA","created_at":"2014-09-25T15:15:56Z","credit_card_brand":"visa","credit_card_id":"5424319ddd39162f950003f1","credit_card_identifier":"6409-j6p58-yx9t3-wgdez","currency":"USD","destination_id":"5424319ddd39162f950003f3","destination_identifier":"F7L9US616X36CQ8139I8","destination_kind":"sage-gateway-388","destination_name":"sage_gateway_388","destination_request_time": 1.595514078,"first_name":"sumdum","gateway_order_id":"1xxxxxxxxxxx9881","identifier":"c96e786c-c8cd-4207-8aca-9e7a8db02e96","kind":"purchase","last_name":"guy","require_cvv": false,"void_status":"unvoided","state":"CO","updated_at":"2014-09-25T15:15:57Z","transaction_id":"E9PEBFvQe0","transacted": true,"status":"success","zip":"12345","destination":"F7L9US616X36CQ8139I8","credit_card":"6409-j6p58-yx9t3-wgdez","links": [{"rel":"self","href":"https://api-staging.value.io/v1/payments/542431acdd39162f950003f4"},{"rel":"index","href":"https://api-staging.value.io/v1/payments"}]}}';
    	$vio_payment_results = json_decode($json,true);
    	$vio_payment_service = new VioPaymentService($data);
    	$vio_payment_service->billing_user_resource = $user_resource;
    	$this->assertTrue($vio_payment_service->isItAValidCvvResponseForBillingUserState($vio_payment_results),"SHoujld have validated since reponse did not contain the expected CVV field");
    }

    function testCorrectBalanceChangeAmountsWithTipForFail()
    {
        $merchant_id = $this->ids['merchant_id'];
        $user_resource = $this->ids['user_resource'];
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'Pickup', 'skip hours');
        $order_data['tip'] = 1.00;
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $this->assertNotNull($order_resource->error);
        $base_order_data = CompleteOrder::getBaseOrderData($order_resource->cancelled_order_id);
        $balance_change_records = getStaticRecords(array("order_id"=>$order_resource->cancelled_order_id),'BalanceChangeAdapter');
        $bcrhash = createHashmapFromArrayOfArraysByFieldName($balance_change_records,'process');
        $this->assertEquals($base_order_data['grand_total'],-$bcrhash['Order']['charge_amt']);
        $this->assertEquals($base_order_data['grand_total'],$bcrhash['CCpayment']['charge_amt']);

    }

    function testNoAdminReversalWithNewPlaceOrderCVVFail()
    {
        $merchant_id = $this->ids['merchant_id'];
        //$user_resource = createNewUser(array("flags"=>"1C21000001"));
        $user_resource = $this->ids['user_resource'];
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id']);
        $checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user);
        $this->assertNotNull($order_resource->error);
        //$order_resource->set("test_transaction_id",$_SERVER['transaction_id']);

        $order_id = $order_resource->cancelled_order_id;
        $record = AdmOrderReversalAdapter::staticGetRecord(array("order_id"=>$order_id), 'AdmOrderReversalAdapter');
        $this->assertNull($record,"There should not be a record for a void");
    }

    function testPlaceOrderUseNewServiceCheckCVVWithFail()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	//$user_resource = createNewUser(array("flags"=>"1C21000001"));
        $user_resource = $this->ids['user_resource'];
    	$user = logTestUserResourceIn($user_resource);
    	$order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'Pickup', 'skip hours');
    	$order_data['tip'] = 1.00;
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertTrue(is_a($order_resource, 'Resource'));
    	$order_resource->set("test_transaction_id",$_SERVER['transaction_id']);
    	return $order_resource;
	}

	/**
	 * @depends testPlaceOrderUseNewServiceCheckCVVWithFail
	 */
	function testvalidateOrderChangedBackFromPendingToFailed($order_resource)
	{
		$order_id = $order_resource->cancelled_order_id;
		$order_record = OrderAdapter::staticGetRecordByPrimaryKey($order_id,"OrderAdapter");
		$this->assertEquals('N',$order_record['status'],"should have listed the cart as a failed payment");
	}


	/**
	 * @depends testPlaceOrderUseNewServiceCheckCVVWithFail
	 */
	function testvalidateOrderFailure($order_resource)
	{
    	$this->assertNotNull($order_resource->error,"Order should have failed due to a mismatched CC");
    	$vio_payment_service = new VioPaymentService($data);
		$this->assertEquals($vio_payment_service->failed_cvv_message, $order_resource->error);		
	}
    
	/**
	 * @depends testPlaceOrderUseNewServiceCheckCVVWithFail
	 */
	function testvalidateFlagsAreUnchangedOnUser($order_resource)
	{
		$base_order = CompleteOrder::getBaseOrderData($order_resource->cancelled_order_id, $mimetypes);
		$user = UserAdapter::staticGetRecordByPrimaryKey($base_order['user_id'], 'UserAdapter');
		$this->assertEquals('1C21000001', $user['flags'],"Flags should have stayed with CVV check on");
	}
	
	/**
	 * @depends testPlaceOrderUseNewServiceCheckCVVWithFail
	 */
	function testvalidateChargeWasRecorded($order_resource)
	{
		$user_id = $order_resource->user_id;
		$order_id = $order_resource->cancelled_order_id;
		$process = 'CCpayment';
		$record = BalanceChangeAdapter::staticGetRecord(array("user_id"=>$user_id,"order_id"=>$order_id,"process"=>$process), 'BalanceChangeAdapter');
		$this->assertTrue(is_array($record),"should have found a charge record in teh balance change table");
		$record['test_transaction_id'] = $order_resource->test_transaction_id;
		return $record;
	}
	
	/**
	 * @depends testvalidateChargeWasRecorded
	 */
	function testCCProcessorWasRecorded($record)
	{
		$this->assertEquals($this->ids['billing_entity_external_id'], $record['cc_processor'],"cc processor should have recorded as the destination id");
	}
	
	/**
	 * @depends testvalidateChargeWasRecorded
	 */
	function testCCTransactionIdWasRecorded($record)
	{
		$this->assertEquals($record['test_transaction_id'], $record['cc_transaction_id']);
		$this->assertTrue($record['cc_transaction_id'] != null,"cc transaction id should not be null");
	}
	
	/**
	 * @depends testPlaceOrderUseNewServiceCheckCVVWithFail
	 */
	function testvalidateChargeWasVoided($order_resource)
	{
		$user_id = $order_resource->user_id;
		$order_id = $order_resource->cancelled_order_id;
		$process = 'CCvoid';
        $records = BalanceChangeAdapter::staticGetRecords(array("user_id"=>$user_id,"order_id"=>$order_id), 'BalanceChangeAdapter');
		$record = BalanceChangeAdapter::staticGetRecord(array("user_id"=>$user_id,"order_id"=>$order_id,"process"=>$process), 'BalanceChangeAdapter');
		$this->assertTrue(is_array($record),"should have found a void record in teh balance change table");
	}

    /**
     * @depends testPlaceOrderUseNewServiceCheckCVVWithFail
     */
    function testvalidateNoRecordInAdminReversal($order_resource)
    {
        $order_id = $order_resource->cancelled_order_id;
        $record = AdmOrderReversalAdapter::staticGetRecord(array("order_id"=>$order_id), 'AdmOrderReversalAdapter');
		$this->assertNull($record,"There should not be a record for a void");
        //$this->assertEquals("X",$record['credit_type'],"should have found the pending adm order reversal record");
    }

    /**
     * @depends testPlaceOrderUseNewServiceCheckCVVWithFail
     */
    function testvalidateNoEmailToUserOnRefund($order_resource)
    {
        $order_id = $order_resource->cancelled_order_id;
        $cancelled_order_record = OrderAdapter::staticGetRecordByPrimaryKey($order_id,'Order');
        $user_id = $cancelled_order_record['user_id'];
        $user = UserAdapter::getUserResourceFromId($user_id);
        $mmha = new MerchantMessageHistoryAdapter();
        $records = $mmha->getRecords(array("message_delivery_addr"=>$user->email));
        $this->assertCount(0,$records,"should not have found an email");
    }

	/**
	 * @depends testPlaceOrderUseNewServiceCheckCVVWithFail
	 */
	function testValidateSecondFail($order_resource)
	{
		$base_order = CompleteOrder::getBaseOrderData($order_resource->cancelled_order_id);
		$merchant_id = $base_order['merchant_id'];
		$user = logTestUserIn($base_order['user_id']);

		$ucid = $base_order['ucid'];

		$url = "/app2/apiv2/cart/$ucid/checkout";
		$request = createRequestObject($url,'GET');
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$checkout_data_resource = $place_order_controller->processV2Request();
        $order_resource = placeOrderFromCheckoutResource($checkout_data_resource,$user,$merchant_id,0.00,$t);
//
//		$place_order_data['merchant_id'] = $merchant_id;
//		$place_order_data['note'] = "skip hours";
//		$place_order_data['user_id'] = $user['user_id'];
//		$place_order_data['cart_ucid'] = $ucid;
//		$place_order_data['tip'] = 0.00;
//		$place_order_data['merchant_payment_type_map_id'] = $checkout_data_resource->accepted_payment_types[0]['merchant_payment_type_map_id'];
//		$place_order_data['requested_time'] = $checkout_data_resource->lead_times_array[0];
//		$request = createRequestObject("/apiv2/orders/$ucid",'post',json_encode($place_order_data),'application/json');
//		$place_order_controller = new PlaceOrderController($mt, $user, $request);
//		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
//		$order_resource = $place_order_controller->processV2Request();
		$this->assertNotNull($order_resource->error);
		$this->assertEquals("Sorry, your credit card's security code has either expired or is incorrect, please re-enter your card information.",$order_resource->error);
		$this->assertEquals(422,$order_resource->http_code);

        //check to make sure both charges were voided
        $bcrecords = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$base_order['order_id'],"process"=>'CCpayment'),'BalanceChangeAdapter');
        $this->assertCount(2,$bcrecords,"it shoudl have two CCpayment records");
        foreach($bcrecords as $record) {
            //notes should contain the 'voided'
            $notes = $record['notes'];
            $this->assertContains("voided-authcode",$notes,'Should have shown the authcode was voided');
        }

    }


    function testPlaceOrderUseNewServiceNOCheckCVVWithFail()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$user_resource = createNewUserWithCCNoCVV();
    	$user = logTestUserResourceIn($user_resource);
    	$place_order_adapter = new PlaceOrderAdapter($mimetypes);
    	$order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'Pickup', 'skip hours');
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNull($order_resource->error,"Should have gotten a good order since check flag was not set");
	} 
	
    function testPlaceOrderUseNewServiceCheckCVVWithPass()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$user_resource = createNewUser(array('flags'=>'1C21000001'));
    	$user_resource->uuid = "cvvpp-12345-12345-12345";
    	$user_resource->save();
    	$user = logTestUserResourceIn($user_resource);
    	$place_order_adapter = new PlaceOrderAdapter($mimetypes);
    	$order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'Pickup', 'skip hours');
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertTrue(is_a($order_resource, 'Resource'));
    	return $order_resource;
	} 

	/**
	 * @depends testPlaceOrderUseNewServiceCheckCVVWithPass
	 */
	function testPlaceOrderUseNewServiceCheckCVVWithPassCheckNoError($order_resource)
	{
		$this->assertNull($order_resource->error,"Should not have thrown an error since cvv passed");
	}
	
	/**
	 * @depends testPlaceOrderUseNewServiceCheckCVVWithPass
	 */
	function testPlaceOrderUseNewServiceCheckCVVWithPassCheckUserFlags($order_resource)
	{
		$user = UserAdapter::staticGetRecordByPrimaryKey($order_resource->user_id, 'UserAdapter');
		$this->assertEquals('1C20000001', $user['flags'],"Users flags should have been reset to no CVV check");
	}
	
   function testPlaceOrderUseNewServiceCheckCVVWithNoCVVField()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$user_resource = createNewUser(array('flags'=>'1C21000001'));
    	$user_resource->uuid = "cvvxx-12345-12345-12345";
    	$user_resource->save();
    	$user = logTestUserResourceIn($user_resource);
    	$place_order_adapter = new PlaceOrderAdapter($mimetypes);
    	$order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'Pickup', 'skip hours');
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertTrue(is_a($order_resource, 'Resource'));
    	return $order_resource;
	} 
	
	/**
	 * @depends testPlaceOrderUseNewServiceCheckCVVWithNoCVVField
	 */
	function testPlaceOrderUseNewServiceCheckCVVWithNOCvvFIeldCheckNoError($order_resource)
	{
		$this->assertNull($order_resource->error,"Should not have thrown an error since cvv data did not exist in return ");
	}
	
	/**
	 * @depends testPlaceOrderUseNewServiceCheckCVVWithNoCVVField
	 */
	function testPlaceOrderUseNewServiceCheckCVVWithNoCvvFieldCheckUserFlags($order_resource)
	{
		$user = UserAdapter::staticGetRecordByPrimaryKey($order_resource->user_id, 'UserAdapter');
		$this->assertEquals('1C21000001', $user['flags'],"Users flags should NOT have been reset since CVV was not in return");
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
    	setProperty('check_cvv', 'true');
    	$skin_resource = createWorldHqSkin();
    	$ids['skin_id'] = $skin_resource->skin_id;
    	
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

    	//$billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);

    	$merchant_payment_type_map_adapter = new MerchantPaymentTypeMapsAdapter($mimetypes);
    	//$cc_merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);
        //$options[TONIC_FIND_BY_METADATA] = array("merchant_id"=>$merchant_resource->merchant_id,""=>);
        //$cc_merchant_payment_type_resource = Resource::findAll($merchant_payment_type_map_adapter,'',$options);
        $ids['cc_billing_entity_id'] = $merchant_resource->cc_billing_entity_id;
    	$ids['billing_entity_external_id'] = $merchant_resource->billing_entity_external_id;
    	
    	// create cash merchang payment type record
    	//$merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);
    	
    	$user_resource = createNewUserWithCC();
    	$user_resource->uuid = "cvvff-12345-12345-12345";
    	$user_resource->save();

    	$ids['user_resource'] = $user_resource;
    	$ids['user_id'] = $user_resource->user_id;
    	
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
    CVVValidationTest::main();
}

?>