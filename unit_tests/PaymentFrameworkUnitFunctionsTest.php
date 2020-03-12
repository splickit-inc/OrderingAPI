<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class PaymentFrameworkUnitFunctionsTest extends PHPUnit_Framework_TestCase
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

	function createBCRecords()
	{

		$brand_id = getBrandIdFromCurrentContext();
		$billing_entity_resource = createSageBillingEntity($brand_id);
		$billing_entity_resource2 = createSageBillingEntity($brand_id);

		$balance_change_adapter = new BalanceChangeAdapter($m);
		$bcar = array ();
		for ($i=0;$i<11;$i++) {
			$user_id = rand(111111,999999);
			$order_resource = $this->createBaseOrder($user_id);
			$order_id = $order_resource->order_id;
			$grand_total = rand(1001,2000)/100;
			if ($i==6) {
				$grand_total = 10.00; // this will fail the capture
			} else if ($i == 7) {
				// change order resource to in process status. that should bypass the capture and change the fail to cancelled
				$order_resource->status = OrderAdapter::ORDER_IS_IN_PROCESS_CART;
				$order_resource->save();
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
		}
		return $bcar;
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


	function testGetAllPendingBalanceChangeRecordsByBrand()
	{
		$sql = "SET FOREIGN_KEY_CHECKS=0;";
		mysqli_query(Database::getInstance()->getConnection(),$sql);

		$skin = getOrCreateSkinAndBrandIfNecessary('testoneskin','testonebrand',$skin_id,$brand_id);
		setContext($skin->external_identifier);

		$bcar = $this->createBCRecords();
		// add another one for a differnt brand
		$balance_change_adapter = new BalanceChangeAdapter($m);
		$billing_entity_resource = createSageBillingEntity(12345432);
		$bcar[] = $balance_change_adapter->addAuthorizeRow(rand(1111111,9999999),5.99,5.99,$billing_entity_resource->external_id,rand(111111,999999),generateUUID(),"PENDING");

		$this->assertTrue(count(BalanceChangeAdapter::staticGetRecords(array("notes"=>"PENDING","process"=>"Authorize"),'BalanceChangeAdapter')) > 10,"there should be more then 10 bcrs");

		$data['brand_id'] = getBrandIdFromCurrentContext();
		$vio_payment_service = new VioPaymentService($data);
		$balance_change_resources = $vio_payment_service->getAllPendingBalanceChangeResourcesByBrand(getBrandIdFromCurrentContext());
		$this->assertCount(11,$balance_change_resources,"It should have returned 11 balance change resources");

	}

	function testIsStatusReadyForBiling()
	{
		$this->assertTrue(OrderAdapter::isStatusReadyForBilling('E'),"E should be ready for billing");
		$this->assertTrue(OrderAdapter::isStatusReadyForBilling('O'),"O should be ready for billing");
		$statuses = array('F','Y','N','C','A','Z','X');
		foreach ($statuses as $status) {
			$this->assertFalse(OrderAdapter::isStatusReadyForBilling($status), "$status should NOT be ready for biling");
		}
	}


	function testCaptureAllPendingBalanceChangeRecords()
	{
		$sql = "SET FOREIGN_KEY_CHECKS=0;";
		mysqli_query(Database::getInstance(),$sql);

		setContext('com.splickit.testoneskin');

		//$data['billing_entity_external_id'] = $billing_entity_resource->external_id;
		$data['brand_id'] = getBrandIdFromCurrentContext();
		$vio_payment_service = new VioPaymentService($data);
		$capture_response = $vio_payment_service->processOrderCaptureOfAllPendingAuthorizationForBrand(getBrandIdFromCurrentContext());
		$this->assertEquals(8,$capture_response['number_of_successful_captures'],"Should have been 8 successful captures");
		$this->assertEquals(2,$capture_response['number_of_failed_captures'],"Should have been 2 failed capture");
		$errors = $vio_payment_service->batch_errors;
		$this->assertCount(2,$errors,"It should have returned one error message");
		$this->assertContains('there was an unknown error trying to capture the authorized charge. order_id: ',$errors[0]);

	}

	function testRecordCCUpdate()
	{
		$resource = CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($this->ids['user_id'],"33345TFT-4TGDRT-TYHBG","8888");
		$this->assertNotNull($resource->insert_id,"should have found the last insert id");

		$resource2 = CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($this->ids['user_id'],null,null);
		$this->assertNotNull($resource2->insert_id,"should have found the last insert id");
	}

	function testTest()
	{
		$this->assertTrue(true);
	}

    function testGetLastFourButUSerDoesNotExist()
    {
        $vio_payment_service = new VioPaymentService($data);
        $result = $vio_payment_service->getLast4FromVioForUUID('FFFFF-FFFFF-FFFFF-FFFFF');
        $this->assertFalse($result);
    }
    
    function testGetNext11pmMountianBefore()
    {
    	$order_controller = new OrderController($mt, $u, $r);
    	$before_11_pm_ts = getTimeStampForDateTimeAndTimeZone(4, 0, 0, 7, 5, 2014, 'UTC');
    	$next_eleven_pm_ts = $order_controller->getNextElevenPMMountiainTimeStampFromSubmittedTimeStamp($before_11_pm_ts);
    	$date_string = date("Y-m-d H:i:s",$next_eleven_pm_ts);
    	$this->assertEquals(getTimeStampForDateTimeAndTimeZone(23, 0, 0, 7, 4, 2014, 'America/Denver'), $next_eleven_pm_ts);
    }

    function testGetNext11pmMountianAfter()
    {
    	$order_controller = new OrderController($mt, $u, $r);
    	$before_11_pm_ts = getTimeStampForDateTimeAndTimeZone(8, 0, 0, 7, 5, 2014, 'UTC');
    	$next_eleven_pm_ts = $order_controller->getNextElevenPMMountiainTimeStampFromSubmittedTimeStamp($before_11_pm_ts);
    	$date_string = date("Y-m-d H:i:s",$next_eleven_pm_ts);
    	$this->assertEquals(getTimeStampForDateTimeAndTimeZone(23, 0, 0, 7, 5, 2014, 'America/Denver'), $next_eleven_pm_ts);
    }

    function testChargeInLast24Hours()
    {
    	$vio_payment_service = new VioPaymentService($data);
    	$created_time_stamp = time()-(24*60*60)+2;
    	$this->assertTrue($vio_payment_service->isChargeWithinLast24Hours($created_time_stamp));
    	$created_time_stamp = time()-(24*60*60)-2;
    	$this->assertFalse($vio_payment_service->isChargeWithinLast24Hours($created_time_stamp));
    }
    
    function testCreateDummyPaymentResponseForAdminUser()
    {
    	$vio_payment_service = new VioPaymentService($data);
    	$response = $vio_payment_service->createAdminBypassPaymentResponse(5.27, 'abcd1234', '123456', 'aaaaa-bbbbb-ccccc-ddddd');
    	$this->assertEquals(200, $response['http_code']);
    	$this->assertEquals('success', $response['status']);
    	$payment = $response['payment'];
    	$this->assertEquals("fake_destination_for_admin_user", $payment['destination_id']);
    	$this->assertEquals("fakeprocessor",$payment['destination_identifier']);
    	$this->assertEquals(527, $payment['cents']);
    	$this->assertEquals("aaaaa-bbbbb-ccccc-ddddd",$payment['credit_card']);
    	$this->assertEquals("Match",$payment['response']['cvv_result']['message']);
    	$this->assertEquals("M",$payment['response']['cvv_result']['code']);
    }
    
    function testBackwardsCompatabilityForCreditCardFunctionsFactory()
    {
    	$f = CreditCardFunctions::creditCardFunctionsFactory("F");
    	$this->assertTrue(is_a($f, 'FranchiseCreditCardFunctions'));
    	$f = CreditCardFunctions::creditCardFunctionsFactory("I");
    	$this->assertTrue(is_a($f, 'InspireCreditCardFunctions'));
    	$f = CreditCardFunctions::creditCardFunctionsFactory("M");
    	$this->assertTrue(is_a($f, 'MercuryCreditCardFunctions'));
    }
    
    function testBackwardsComatabilityForCash()
    {
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	// old adapter
    	$merchant_payment_type_adapter = new MerchantPaymentTypeAdapter($mimetypes);
    	$merchant_payment_type_adapter->setCashForMerchant($merchant_id);
    	
    	$user_resource = createNewUser();
    	$user = logTestUserResourceIn($user_resource);
    	$user_id = $user['user_id'];
    	$balance_before = $user['balance'];
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
    	$order_data['tip'] = '0.00';
    	$order_data['cash'] = 'Y';
    	$order_data_resource = Resource::dummyfactory($order_data);
    	
    	$place_order_controller = new PlaceOrderController($mt, $user, $r, 5);
    	$place_order_controller->setMerchantById($merchant_id);

    	$created_id = $place_order_controller->getMerchantPaymentTypeMapIdFromOrderData($order_data_resource->getDataFieldsReally());
    	$this->assertTrue($created_id > 1000);
    	
    	$payment_service = PaymentGod::paymentServiceFactoryByMerchantPaymentTypeMapId($created_id,$billing_user);
    	$this->assertTrue(is_a($payment_service, 'CashPaymentService'));
    }

    function testSetBillingUser()
    {
    	$user_resource = createNewUser();
    	$user = logTestUserResourceIn($user_resource);
    	// use any service since the methods are in the parent abstract class
    	$payment_service = new CashPaymentService($data);
    	$payment_service->setBillingUserIdAndResourceFromUserId($user_resource->user_id);
    	$billing_user_resource = $payment_service->billing_user_resource;
    	$this->assertEquals($user['user_id'], $billing_user_resource->user_id,"logged in user and billing user should be the same");
    	$this->assertEquals($user['flags'], $billing_user_resource->flags,"logged in user and billing user should be the same");
    }
    
    function testValidateCash()
    {
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	$merchant_payment_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_id, 1000, $billing_entity_id);
    	$created_merchant_payment_type_map_id = $merchant_payment_map_resource->id;
    	$this->assertTrue($created_merchant_payment_type_map_id >= 1000,"should have found a map id");
    	$this->assertTrue(MerchantPaymentTypeMapsAdapter::validateCashForMerchantId($merchant_id),"should have validated cash");    	
    }

	function testGetAuthResource()
	{
		$sql = "SET FOREIGN_KEY_CHECKS=0;";
		mysqli_query(Database::getInstance(),$sql);

		$order_id = rand(111111, 999999);
		$balance_change_adapter = new BalanceChangeAdapter($m);
		$simulated_transaction_idetifier = generateUUID();
		$bcar = $balance_change_adapter->addAuthorizeRow(20000,10.00,10.00,"567rty567rty",$order_id,$simulated_transaction_idetifier,"PENDING");

		$vio_payment_service = new VioPaymentService($data);
		$resource = $vio_payment_service->getAuthorizationResourceFromBalanceChange($order_id);

		$this->assertEquals($bcar->id,$resource->id,"should have returned the balance change resource");
	}

	function testCaptureExistingAuthRecordInBalanceChangeTable()
	{
		$sql = "SET FOREIGN_KEY_CHECKS=0;";
		mysqli_query(Database::getInstance(),$sql);

		$user_resource = createNewUser();
		$order_id = rand(111111, 999999);
		$order_resource = Resource::dummyfactory(array("grand_total"=>10.51,"order_id"=>$order_id,"user_id"=>$user_resource->user_id));

		$merchant_resource = createNewTestMerchant();
		$billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);


		$balance_change_adapter = new BalanceChangeAdapter($m);
		$simulated_transaction_idetifier = generateUUID();
		$bcar = $balance_change_adapter->addAuthorizeRow($user_resource->user_id,10.00,10.00,$billing_entity_resource->external_id,$order_id,$simulated_transaction_idetifier,"PENDING");



		$data['billing_entity_external_id'] = $billing_entity_resource->external_id;
		$vio_payment_service = new VioPaymentService($data);
		$capture_response = $vio_payment_service->processOrderCaptureOfPreviousAuthorization($order_resource,11.00);
		$this->assertEquals(100,$capture_response['response_code']);

		$updated_bcar = $balance_change_adapter->getRecordFromPrimaryKey($bcar->id);
		$this->assertEquals('captured',$updated_bcar['notes']);

		$balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id));
		$bcr_hash_by_process = createHashmapFromArrayOfArraysByFieldName($balance_change_records,'process');
		$this->assertNotNull($bcr_hash_by_process['CCpayment']);
		$this->assertEquals($billing_entity_resource->external_id,$bcr_hash_by_process['CCpayment']['cc_processor'],"Should have the processor listed");
		$this->assertNotNull($bcr_hash_by_process['CCpayment']['cc_transaction_id'],"Should have recroded the transaction id");
	}

// put auth captuer test here

    function testCreateOrderRecordInBalanceChangeTable()
    {
    	$sql = "SET FOREIGN_KEY_CHECKS=0;"; 
    	mysqli_query(Database::getInstance(),$sql);
    	
    	$user_resource = createNewUser();
    	$order_id = rand(111111, 999999);
    	$order_resource = Resource::dummyfactory(array("grand_total"=>10.51,"order_id"=>$order_id,"user_id"=>$user_resource->user_id));
    	$payment_service = new TestPaymentService($data);
    	$payment_service->setBillingUserIdAndResourceFromUserId($user_resource->user_id);
    	$balance_change_resource = $payment_service->recordOrderTransactionsInBalanceChangeTable($order_resource, $payment_results);
    	$this->assertTrue(is_a($balance_change_resource, 'Resource'));
    	$this->assertEquals(-10.51, $balance_change_resource->balance_after);
    	$this->assertEquals($order_id,$balance_change_resource->order_id);
    	$this->assertEquals(-10.51,$balance_change_resource->charge_amt);
    }
    
    function testGetIndexedPaymentTypesArray()
    {
    	$results = SplickitAcceptedPaymentTypesAdapter::getAllIndexedById();
    	$this->assertCount(11, $results);
    	$this->assertEquals('Cash',$results[1000]['name']);	
    }
    
    function testGetMerchantPaymentTypes()
    {    
    	$cc_billing_entity_id = $this->ids['cc_billing_entity_id'];	
    	$merchant_id = $this->ids['merchant_id'];
    	$place_order_controller = new PlaceOrderController($mt, $u, $r);
    	$payment_array = $place_order_controller->getPaymentMethodsForMerchantUserOrderCombination($merchant_id, $checkout_order_resource);
    	$this->assertCount(2, $payment_array);
    	$payment_array = createHashmapFromArrayOfArraysByFieldName($payment_array, 'name');
    	$cash_payment_type = $payment_array['Cash'];
    	$this->assertTrue(isset($cash_payment_type['merchant_payment_type_map_id']));
    	$this->assertEquals('Cash', $cash_payment_type['name']);
    	$this->assertEquals(1000, $cash_payment_type['splickit_accepted_payment_type_id']);
    	
    	// cc payment type should be a hash of 4 values
    	$cc_payment_type = $payment_array['Credit Card'];
    	$this->assertTrue(isset($cc_payment_type['merchant_payment_type_map_id']));
    	$this->assertEquals('Credit Card', $cc_payment_type['name']);
    	$this->assertEquals(2000, $cc_payment_type['splickit_accepted_payment_type_id']);
    	$this->assertTrue(isset($cc_payment_type['billing_entity_id']));
    	$this->assertEquals($cc_billing_entity_id, $cc_payment_type['billing_entity_id']);
    }
    
    function testAccountCreditPaymentServiceProcessPayment()
    {
    	$sql = "SET FOREIGN_KEY_CHECKS=0;"; 
    	mysqli_query(Database::getInstance(),$sql);
    	
    	$user_resource = createNewUserWithCC();
    	$user_resource->balance = 100;
    	$user_resource->save();
    	$user = logTestUserResourceIn($user_resource);
    	$user_id = $user['user_id'];
    	$order_amount = 25.00;
    	// create dummy resource
    	$order_id = rand(111111, 999999);
    	$order_resource = Resource::dummyfactory(array("grand_total"=>$order_amount,"order_id"=>$order_id,"user_id"=>$user_resource->user_id));

    	$payment_service = new AccountCreditPaymentService($data);
    	$payment_service->setBillingUserIdAndResourceFromUserId($user_resource);
    	$payment_results = $payment_service->processOrderPayment($order_resource, $order_amount, $gift_resource);
    	$this->assertEquals("Payment Posted Against Account Balance", $payment_results['response_text']);
    	$this->assertEquals(100,$payment_results['response_code']);
    	
    	$billing_user_resource = $payment_service->billing_user_resource;
    	$this->assertEquals(75.00, $billing_user_resource->balance);

    	$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
			$balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
		}
		$this->assertCount(1, $balance_change_rows_by_user_id);
		$this->assertEquals(100, $balance_change_rows_by_user_id["$user_id-Order"]['balance_before']);
		$this->assertEquals($order_resource->grand_total, -$balance_change_rows_by_user_id["$user_id-Order"]['charge_amt']);
		$this->assertEquals(100-$order_amount, $balance_change_rows_by_user_id["$user_id-Order"]['balance_after']);
		
		$this->assertNull($balance_change_rows_by_user_id["$user_id-CCpayment"],"should not have found a CC entry for this order");
    	
    }
        
    function testPaymentGodGetPaymentServiceFactory()
    {
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$cash_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_id, 1000, $billing_entity_id);
    	$cash_map_id = $cash_map_resource->id;
    	
    	$pitcard_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_id, 3000, $billing_entity_id);
    	$pitcard_map_id = $pitcard_map_resource->id;
    	
    	$boeing_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_id, 4000, $billing_entity_id);
    	$boeing_map_id = $boeing_map_resource->id;
    	
    	$payment_service = PaymentGod::paymentServiceFactoryByMerchantPaymentTypeMapId($cash_map_id,$billing_user);
    	$this->assertTrue(is_a($payment_service, 'CashPaymentService'));
    	
    	$payment_service = PaymentGod::paymentServiceFactoryByMerchantPaymentTypeMapId($pitcard_map_id,$billing_user);
    	$this->assertTrue(is_a($payment_service, 'PitCardStoredValuePaymentService'));
    	
    	$payment_service = PaymentGod::paymentServiceFactoryByMerchantPaymentTypeMapId($boeing_map_id,$billing_user);
    	$this->assertTrue(is_a($payment_service, 'BoeingEmployeeAccountPaymentService'));

		$payment_service = PaymentGod::paymentServiceFactoryByMerchantPaymentTypeMapId(1000,$billing_user);
    	$this->assertTrue(is_a($payment_service, 'AccountCreditPaymentService'));
    }
 
    /**
     * @expectedException NoSuchBillingEntityException
     */
    function testNoBillingEntity()
    {
    	$data['billing_entity_external_id'] = '345wert456';
    	$payment_service = new VioPaymentService($data);
    	$this->assertNull($payment_service);
    }
    
    /**
     * @expectedException     NoMatchingMerchantPaymentTypeMapForOrderDataException
     */
    function testGetPaymentMapFromNonPaymentTypeOrderNoPaymentMapRecord()
    {
		$merchant_resource = createNewTestMerchant($this->ids['menu_id'],array("no_payment"=>true));
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', $note);
    	$order_resource = Resource::dummyfactory($order_data);
    	$place_order_controller = new PlaceOrderController($mt, $u, $r, 5);
    	$place_order_controller->setMerchantById($merchant_id);
    	$merchant_payment_map_id = $place_order_controller->getMerchantPaymentTypeMapIdFromOrderData($order_resource->getDataFieldsReally());

    }
    
    /**
     * @expectedException     NoMatchingMerchantPaymentTypeMapForOrderDataException
     */
    function testLogicalDeleteOfPaymentTypeMaps()
    {
    	$merchant_id = $this->ids['merchant_id'];
				
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', $note);
    	$order_resource = Resource::dummyfactory($order_data);
    	$place_order_controller = new PlaceOrderController($mt, $u, $r, 5);
        $place_order_controller->setMerchantById($merchant_id);
    	$merchant_payment_map_id = $place_order_controller->getMerchantPaymentTypeMapIdFromOrderData($order_resource->getDataFieldsReally());
    	$this->assertTrue($merchant_payment_map_id > 999);
    	
    	$mptma = new MerchantPaymentTypeMapsAdapter($m);
    	$mptm_resource = $mptma->getExactResourceFromData(array("merchant_id"=>$merchant_id,"splickit_accepted_payment_type_id"=>2000));
    	$mptm_resource->logical_delete = 'Y';
    	$mptm_resource->save();

    	$merchant_payment_map_id2 = $place_order_controller->getMerchantPaymentTypeMapIdFromOrderData($order_resource->getDataFieldsReally());
    	$this->assertNull($merchant_payment_map_id2);
    }
    
    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
       	
		ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;

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
    	$merchant_id = $merchant_resource->merchant_id;
    	
//    	$billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);
//
//    	$merchant_payment_type_map_adapter = new MerchantPaymentTypeMapsAdapter($mimetypes);
//    	$cc_merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_id, 2000, $billing_entity_resource->id);
    	$ids['cc_billing_entity_id'] = $merchant_resource->cc_billing_entity_id;
    	
    	// create cash merchang payment type record
        $merchant_payment_type_map_adapter = new MerchantPaymentTypeMapsAdapter($mimetypes);
    	$merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_id, 1000, $billing_entity_id);
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
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
    PaymentFrameworkUnitFunctionsTest::main();
}

?>