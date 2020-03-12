<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class PaymentFrameworkProcessorSetupTest extends PHPUnit_Framework_TestCase
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
    
    function testProcessorFactorySage()
    {
    	$type = 'Sage';
    	$processor = ProcessorFactory::getProcessor($type);
    	$this->assertTrue(is_a($processor, 'SageProcessor'));
    }

    function testProcessorFactorySecureNet()
    {
        $type = 'Secure-Net';
        $processor = ProcessorFactory::getProcessor($type);
        $this->assertTrue(is_a($processor, 'SecureNetProcessor'));
    }
    
    function testGetCCProcessorsList()
    {
    	$list = VioCreditCardProcessorsAdapter::staticGetCCPaymentProcessorsHashWithNameAsKey();
    	$this->assertNotNull($list);
    	$this->assertCount(7, $list);
    	$sage = $list['sage'];
    	$this->assertEquals(2000, $sage['splickit_accepted_payment_type_id']);
    	$this->assertEquals(2002, $sage['id']);
    }
    
    function testGetProcessorListWithFields()
    {
    	$card_gateway_controller = new CardGatewayController($mt, $u, $r);
    	$processor_schema = $card_gateway_controller->getAvailableProcessorsAndFields();
    	$this->assertCount(7, $processor_schema);
    	$sage = $processor_schema['sage'];
    	$this->assertCount(2, $sage['fields']);
    	$mercury = $processor_schema['mercury'];
    	$this->assertCount(2, $mercury['fields']);
    	$fpn = $processor_schema['fpn'];
    	$this->assertCount(1, $fpn['fields']);
    }
    
    function testPayloadFormatSage()
    {
    	//array("merchant_id_key","merchant_id_number");
    	$merchant_id_key = generateCode(10);
    	$merchant_id_number = generateCode(5);
    	
    	$type = 'sage';
    	
    	$data['vio_selected_server'] = 'sage';
    	$data['vio_merchant_id'] = $this->ids['merchant_id'];
    	$data['loga'] = 'adsf';
    	$data['otyher'] = "aoiufoiau";
    	$data['merchant_id_key'] = $merchant_id_key;
    	$data['merchant_id_number'] = $merchant_id_number;
    	$processor = ProcessorFactory::getProcessor($type);
    	$payload = $processor->getVIOPayload($data);
    	$this->assertNotNull($payload);
    	$this->assertCount(2, $payload);
    	$this->assertEquals($merchant_id_key, $payload['merchant_id_key']);
    	$this->assertEquals($merchant_id_number, $payload['merchant_id_number']);
    	return $payload;
    }
    
    /**
     * @depends testPayloadFormatSage
     */
    function testProcessCurlResponse($payload)
    {
    	$vio_payment_service = new VioPaymentService();
    	$identifier = "test_".generateCode(5);
    	$response = $vio_payment_service->createDestination('sage', $identifier, $payload);
    	$this->assertNotNull($response);
    	$this->assertEquals('success', $response['status']);
    	$this->assertNotNull($response['destination']);
    }
    
    function testBillingEntityFunctionsSageWithPassedInMerchantId()
    {
		$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        $mpta = new MerchantPaymentTypeAdapter($m);
        $sql = "DELETE FROM Merchant_Payment_Type_Maps WHERE merchant_id = $merchant_id";
        $mpta->_query($sql);

    	$merchant_id_key = generateCode(10);
    	$merchant_id_number = generateCode(5);
    	$data['vio_selected_server'] = 'sage';
    	$data['vio_merchant_id'] = $merchant_resource->merchant_id;
    	$data['name'] = "Test Billing Entity";
    	$data['description'] = 'An entity to test with';
    	$data['merchant_id_key'] = $merchant_id_key;
    	$data['merchant_id_number'] = $merchant_id_number;
    	$data['identifier'] = $merchant_resource->alphanumeric_id;
    	$data['brand_id'] = $merchant_resource->brand_id;
    	
    	$card_gateway_controller = new CardGatewayController($mt, $u, $r);
    	$resource = $card_gateway_controller->createPaymentGateway($data);
    	$billing_entity_external_id = $resource->external_id; 
    	$map = BillingEntitiesAdapter::getBillingEntityByExternalId($billing_entity_external_id);
    	$this->assertNotNull($map);
    	$this->assertEquals($data['identifier'], $map['external_id']);
    	$this->assertEquals(2002,$map['vio_credit_card_processor_id']);
    	
    	$merchant_payment_type_maps_adapter = new MerchantPaymentTypeMapsAdapter($mimetypes);
    	$record = $merchant_payment_type_maps_adapter->getRecord(array("merchant_id"=>$merchant_resource->merchant_id));
    	$this->assertNotNull($record);
    	$this->assertEquals($map['id'], $record['billing_entity_id'],"map id should match what was stored int eh mechant payment type maps table");
    }

    /*
     * not sure how this is differnt from the previous test
     */
    function testCreateSageDestination()
    {
    	$merchant_resource = SplickitController::getResourceFromId($this->ids['merchant_id'], 'Merchant');
        $merchant_id = $merchant_resource->merchant_id;
        $mpta = new MerchantPaymentTypeAdapter($m);
        $sql = "DELETE FROM Merchant_Payment_Type_Maps WHERE merchant_id = $merchant_id";
        $mpta->_query($sql);

        $merchant_id_key = generateCode(10);
    	$merchant_id_number = generateCode(5);
    	$data['vio_selected_server'] = 'sage';
    	$data['vio_merchant_id'] = $this->ids['merchant_id'];
    	$data['name'] = "Test Billing Entity";
    	$data['description'] = 'An entity to test with';
    	$data['merchant_id_key'] = $merchant_id_key;
    	$data['merchant_id_number'] = $merchant_id_number;
    	$data['identifier'] = $merchant_resource->alphanumeric_id;
    	$data['brand_id'] = $merchant_resource->brand_id;
    	
    	$card_gateway_controller = new CardGatewayController($mt, $u, $r);
    	$resource = $card_gateway_controller->createPaymentGateway($data);
    	$this->assertTrue(is_a($resource, 'Resource'),'Should have returned a resource');
    	$this->assertNull($resource->error);
    	$this->assertNotNull($resource->id);
    	$this->assertNotNull($resource->merchant_payment_type_map);
    	$expected_string = "merchant_id_key=$merchant_id_key|merchant_id_number=$merchant_id_number";
    	$this->assertEquals($expected_string,$resource->credentials);
    	$created_merchant_payment_type_map_id = $resource->merchant_payment_type_map->id;

    	$billing_entity_resource = SplickitController::getResourceFromId($resource->id, 'BillingEntities');
    	$this->assertNotNull($billing_entity_resource);
    	$this->assertEquals('2002',$billing_entity_resource->vio_credit_card_processor_id);
    	$this->assertEquals('sage='.$this->ids['merchant_id'], $billing_entity_resource->name);
    	$this->assertEquals($merchant_resource->brand_id, $billing_entity_resource->brand_id);
    	$this->assertEquals($merchant_resource->alphanumeric_id, $billing_entity_resource->external_id);

    	$merchant_payment_type_map_resource = SplickitController::getResourceFromId($created_merchant_payment_type_map_id, 'MerchantPaymentTypeMaps');
    	$this->assertNotNull($merchant_payment_type_map_resource);
    	//$this->assertEquals($resource->identifier, $merchant_payment_type_map_resource->vio_destination_id);
    	$this->assertEquals('2000', $merchant_payment_type_map_resource->splickit_accepted_payment_type_id);
    	$this->assertEquals($billing_entity_resource->id,$merchant_payment_type_map_resource->billing_entity_id);
    	return $merchant_payment_type_map_resource->id;
    }
    
    /**
     * @depends testCreateSageDestination
     */
    
    function testGetPaymentTypeMapRecordWithBillingEntityDetail($merchant_payment_type_map_id) {
    	
    	$mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
    	$merchant_payment_type_map_record = $mptma->getMerchantPaymentTypeMapFromIdWithBillingEntityIfItExists($merchant_payment_type_map_id);
    	$this->assertNotNull($merchant_payment_type_map_record,"should have found a merchant payment type map record");
    	$this->assertEquals('2000', $merchant_payment_type_map_record['splickit_accepted_payment_type_id']);
    	$this->assertNotNull($merchant_payment_type_map_record['billing_entity_record'],'should have found a billing entity record on mercahtn payment type record');
    	$billing_entity_record = $merchant_payment_type_map_record['billing_entity_record'];
    	$this->assertEquals('2002',$billing_entity_record['vio_credit_card_processor_id']);
    	$this->assertEquals('sage='.$this->ids['merchant_id'], $billing_entity_record['name']);
    	return $merchant_payment_type_map_id;
    }
    
    /**
     * @depends testGetPaymentTypeMapRecordWithBillingEntityDetail
     */
    
    function testGetPaymentServiceFactory($merchant_payment_type_map_id)
    {
    	$user = logTestUserResourceIn($this->ids['user_resource']);
    	$payment_service = PaymentGod::paymentServiceFactoryByMerchantPaymentTypeMapId($merchant_payment_type_map_id,$user);
    	$this->assertTrue(is_a($payment_service, 'VioPaymentService'));
    }
            
    function testRunCCThroughSystem()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
    	$record = $mptma->getRecord(array("merchant_id"=>$merchant_id));
    	$user = logTestUserResourceIn($this->ids['user_resource']);
    	$user_id = $user['user_id'];
    	$balance_before = $user['balance'];
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
    	$order_data['merchant_payment_type_map_id'] = $record['id'];
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$order_id = $order_resource->order_id;
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000);

		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
			$balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
		}
		$this->assertCount(2, $balance_change_rows_by_user_id);
		$this->assertEquals($balance_before, $balance_change_rows_by_user_id["$user_id-Order"]['balance_before']);
		$this->assertEquals($order_resource->grand_total, -$balance_change_rows_by_user_id["$user_id-Order"]['charge_amt']);
		$this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-Order"]['balance_after']);
		
		$billing_entity_record = $mptma->getBillingEntityFromBillingEntityId($record['billing_entity_id']);
		
		$new_user_resource = SplickitController::getResourceFromId($user_id, 'User');
		$this->assertEquals($balance_before-$order_resource->grand_total, $balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_before']);
		$this->assertEquals($balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_before'], -$balance_change_rows_by_user_id["$user_id-CCpayment"]['charge_amt']);
		$this->assertEquals($new_user_resource->balance, $balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_after']);
		$this->assertEquals($billing_entity_record['external_id'], $balance_change_rows_by_user_id["$user_id-CCpayment"]['cc_processor']);    	
    }

    function testThrowExceptionBillingCancellMesssagesAndSetOrderToCancelledWithGoodMessageToUser()
    {
		$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        $mpta = new MerchantPaymentTypeAdapter($m);
        $sql = "DELETE FROM Merchant_Payment_Type_Maps WHERE merchant_id = $merchant_id";
        $mpta->_query($sql);

        $merchant_id_key = generateCode(10);
		$merchant_id_number = generateCode(5);
		$data['vio_selected_server'] = 'sage';
		$data['vio_merchant_id'] = $merchant_resource->merchant_id;
		$data['name'] = "Test Billing Entity";
		$data['description'] = 'An entity to test with';
		$data['merchant_id_key'] = $merchant_id_key;
		$data['merchant_id_number'] = $merchant_id_number;
		$data['identifier'] = "sumdumdestination";
		$data['brand_id'] = $merchant_resource->brand_id;

		$card_gateway_controller = new CardGatewayController($mt, $u, $r);
		$resource = $card_gateway_controller->createPaymentGateway($data);
		$billing_entity_external_id = $resource->external_id;
		$map = BillingEntitiesAdapter::getBillingEntityByExternalId($billing_entity_external_id);
		$this->assertNotNull($map);
		$this->assertEquals($data['identifier'], $map['external_id']);
		$this->assertEquals(2002,$map['vio_credit_card_processor_id']);

    	$merchant_id = $merchant_resource->merchant_id;
    	$mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
    	$mptm_resource = $mptma->getExactResourceFromData(array("merchant_id"=>$merchant_id));
//    	$mptm_resource->billing_entity_id = 'sumdumentity';
//    	$mptm_resource->save();
    	$user = logTestUserResourceIn($this->ids['user_resource']);
    	$user_id = $user['user_id'];
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
    	$order_data['merchant_payment_type_map_id'] = $mptm_resource->id;
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNotNull($order_resource->cancelled_order_id);
    	$cancelled_order_id = $order_resource->cancelled_order_id;
    	$this->assertNotNull($order_resource->error);
		$this->assertEquals("We're sorry but there was an unrecognized error running your credit card and the charge did not go through.", $order_resource->error);
//    	$this->assertEquals("We're sorry but there is a problem with this merchant's billing setup and orders cannot be processed. We have been alerted and will take care of the error shortly. Sorry for the inconvenience.", $order_resource->error);
//    	$this->assertEquals(500,$order_resource->http_code);
    	$base_order_data = CompleteOrder::getBaseOrderData($cancelled_order_id, $mimetypes);
    	$this->assertEquals('N', $base_order_data['status'],"order status should have been set to cancelled");
    	$this->assertFalse(MerchantMessageHistoryAdapter::getAllOrderMessages($cancelled_order_id),"should not have found any messages since they are all logcally deleted");
    }
    
    /**
     * @expectedException BadCredentialDataPassedInException
     */
    function testBillingEntityFunctionsSageBadCredentials()
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
    	$data['merchant_id_number'] = $merchant_id_number;
    	$data['identifier'] = $merchant_resource->alphanumeric_id;
    	$data['brand_id'] = $merchant_resource->brand_id;
    	
    	$card_gateway_controller = new CardGatewayController($mt, $u, $r);
    	$resource = $card_gateway_controller->createPaymentGateway($data);
    }
    
    function testBillingEntityFunctionsMercury()
    {
		$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        $mpta = new MerchantPaymentTypeAdapter($m);
        $sql = "DELETE FROM Merchant_Payment_Type_Maps WHERE merchant_id = $merchant_id";
        $mpta->_query($sql);

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
    	$billing_entity_external_id = $resource->external_id; 
    	$map = BillingEntitiesAdapter::getBillingEntityByExternalId($billing_entity_external_id);
    	$this->assertNotNull($map);
    	$this->assertEquals($data['identifier'], $map['external_id']);
    	$this->assertEquals(2001,$map['vio_credit_card_processor_id']);
    	
    	$merchant_payment_type_maps_adapter = new MerchantPaymentTypeMapsAdapter($mimetypes);
    	$record = $merchant_payment_type_maps_adapter->getRecord(array("merchant_id"=>$merchant_resource->merchant_id));
    	$this->assertNotNull($record);
    	$this->assertEquals($map['id'], $record['billing_entity_id'],"map id should match what was stored int eh mechant payment type maps table");  	
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
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;
    	
    	$ids['user_resource'] = $user_resource; 
    	$uuid = $user_resource->uuid;
    	
    	//{"brand": "visa", "number":"4242424242424242","cvv":"123","month":4,"year":2015,"first_name":"Ara","last_name":"Howard"}
    	$data['identifier'] = $uuid;
    	$data['brand'] = "visa";
    	$data['number'] = "4111111111111111";
    	$data['cvv'] = "123";
    	$data['month'] = 10;
    	$data['year'] = 2020;
    	$data['first_name'] = "sumdum";
    	$data['last_name'] = "guy";
    	$vio_payment_service = new VioPaymentService();
    	$response = $vio_payment_service->saveCreditCard($data);
    	if ($response['status'] != 'success') {
    		die ("couldn't create user cc vault");
    	}
    	   	
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
    PaymentFrameworkProcessorSetupTest::main();
}

?>