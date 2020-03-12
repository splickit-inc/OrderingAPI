<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class PosControllerTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		setContext('com.splickit.goodcentssubs');
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }

	function testGetMerchantIdFromUrl()
	{
		$url = '/app2/apiv2/pos/merchants/1234-0987';
		preg_match('%/merchants/([0-9a-zA-Z\-]+)%', $url, $matches);
		$this->assertEquals('1234-0987',$matches[1]);

		$url2 = '/app2/apiv2/pos/merchants/1234-0987/shutdown';
		preg_match('%/merchants/([0-9a-zA-Z\-]+)%', $url2, $matches2);
		$this->assertEquals('1234-0987',$matches2[1]);

	}

	function testReformatSoapRequestForUpdateLeadTimes()
	{
		$soap_action = "http://www.xoikos.com/webservices/UpdateLeadTime";
		$_SERVER['SOAPAction'] = $soap_action;
		$xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><UpdateLeadTime xmlns="http://www.xoikos.com/webservices/"><storeNumber>8888</storeNumber><pickupLeadTime>30</pickupLeadTime><deliveryLeadTime>90</deliveryLeadTime></UpdateLeadTime></soap:Body></soap:Envelope>';
		$url = "http://localhost/app2/pos/xoikos";

		$request = new Request();
		$request->url = $url;
		$request->body = $xml;
		$request->mimetype = 'Applicationxml';
		$request->_parseRequestBody();
		$data = $request->data;
		$pos_controller = new PosController($mt,$u,$request,5);
		$pos_controller->reformatSoapRequest($request);

		$this->assertEquals("/app2/apiv2/pos/merchants/8888",$request->url,"Url should have been reformatted");
		$this->assertEquals("30",$request->data['lead_time'],"A parameter of pickupLeadTime should have been set");
		$this->assertEquals("90",$request->data['minimum_delivery_time'],"A parameter of deliveryLeadTime should have been set");
	}

	function testReformatSoapRequestForAddTip()
	{
		$xml_body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/webservices/"><OrderID>1234-56789</OrderID><Amount>1.88</Amount></ApplyTipToOrder></soap:Body></soap:Envelope>';
		$soap_action = "ApplyTipToOrder";
		$_SERVER['SOAPAction'] = $soap_action;
		$request = new Request();

		$request->body = $xml_body;
		$request->mimetype = 'Applicationxml';

		$request->_parseRequestBody();
		$data = $request->data;
		$pos_controller = new PosController($mt,$u,$request,5);
		$pos_controller->reformatSoapRequest($request);

		$this->assertEquals("/app2/apiv2/pos/orders/1234-56789",$request->url,"Url should have been reformatted");
		$this->assertEquals("1.88",$request->data['tip_amt'],"A parameter of tip_amt should ahve been set");
	}

	function testReformatSoapRequestForCancelOrder()
	{
		$_SERVER['SOAPAction'] = "http://www.xoikos.com/webservices/CancelOrder";
		$xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CancelOrder xmlns="http://www.xoikos.com/webservices/"><OrderID>1234-0987</OrderID></CancelOrder></soap:Body></soap:Envelope>';
		$request = new Request();
		$request->url = "/pos/xoikos";
		$request->method = "POST";
		$request->body = $xml;
		$request->mimetype = 'Applicationxml';

		$request->_parseRequestBody();
		$data = $request->data;
		$pos_controller = new PosController($mt,$u,$request,5);
		$pos_controller->reformatSoapRequest($request);

		$this->assertEquals("/app2/apiv2/pos/orders/1234-0987",$request->url,"Url should have been reformatted");
		$this->assertEquals("C",$request->data['status'],"A parameter of status should ahve been set to 'C'");
	}

	function testReformatSoapRequestForGetLeadTimes()
	{
		$_SERVER['SOAPAction'] = "http://www.xoikos.com/webservices/GetLeadTimes";
		$xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><GetLeadTimes xmlns="http://www.xoikos.com/webservices/"><storeNumber>8500</storeNumber></GetLeadTimes></soap:Body></soap:Envelope>';
		$request = new Request();
		$request->url = "/pos/xoikos";
		$request->method = "POST";
		$request->body = $xml;
		$request->mimetype = 'Applicationxml';

		$request->_parseRequestBody();
		$data = $request->data;
		$pos_controller = new PosController($mt,$u,$request,5);
		$pos_controller->reformatSoapRequest($request);

		$this->assertEquals("/app2/apiv2/pos/merchants/8500",$request->url,"Url should have been reformatted");
		$expected_soap_action = 'GetLeadTimes';
		$this->assertEquals($expected_soap_action,$pos_controller->getSoapAction(),"The soap action of the POS controller should have been set to GetLeadTimes");
	}

	function testUpdateLeadTimes()
	{
		$merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
		$merchant_resource->merchant_external_id = "8888";
		$merchant_resource->save();

		$merchant = CompleteMerchant::staticGetCompleteMerchant($merchant_resource->merchant_id,'delivery');
		$this->assertEquals(20,$merchant->lead_time);
		$this->assertEquals(45,$merchant->delivery_info['minimum_delivery_time']);

		$soap_action = "http://www.xoikos.com/webservices/UpdateLeadTime";
		$_SERVER['SOAPAction'] = $soap_action;
		$xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><UpdateLeadTime xmlns="http://www.xoikos.com/webservices/"><storeNumber>8888</storeNumber><pickupLeadTime>30</pickupLeadTime><deliveryLeadTime>90</deliveryLeadTime></UpdateLeadTime></soap:Body></soap:Envelope>';
		$request = new Request();
		$request->url = "/pos/xoikos";
		$request->method = "POST";
		$request->body = $xml;
		$request->mimetype = 'Applicationxml';

		$pos_controller = new PosController($mt,$u,$request,5);
		$resource = $pos_controller->processV2request();

		$merchant_after = CompleteMerchant::staticGetCompleteMerchant($merchant_resource->merchant_id,'delivery');
		$this->assertEquals(30,$merchant_after->lead_time);
		$this->assertEquals(90,$merchant_after->delivery_info['minimum_delivery_time']);


		$this->assertTrue(isset($resource->send_soap_response),"should have set the soap flag on the resource");
		$expected_response = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><UpdateLeadTimeResponse xmlns="http://www.xoikos.com/webservices/"><UpdateLeadTimeResult><Success>true</Success><ErrorMessage>Store number 8888 had its lead times updated to Pickup: 30 minutes and Delivery: 90 minutes.</ErrorMessage></UpdateLeadTimeResult></UpdateLeadTimeResponse></soap:Body></soap:Envelope>';
		$this->assertEquals($expected_response,$resource->soap_body,"Should have the expected soap body");
	}

	function testGetLeadTimes()
	{
		$_SERVER['SOAPAction'] = "http://www.xoikos.com/webservices/GetLeadTimes";
		$xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><GetLeadTimes xmlns="http://www.xoikos.com/webservices/"><storeNumber>8888</storeNumber></GetLeadTimes></soap:Body></soap:Envelope>';
		$request = new Request();
		$request->url = "/pos/xoikos";
		$request->method = "POST";
		$request->body = $xml;
		$request->mimetype = 'Applicationxml';

		$pos_controller = new PosController($mt,$u,$request,5);
		$resource = $pos_controller->processV2request();
		$this->assertTrue(isset($resource->send_soap_response),"should have set the soap flag on the resource");
		$expected_response = '<GetLeadTimesResult><Success>true</Success><PickupLeadTime>30</PickupLeadTime><DeliveryLeadTime>90</DeliveryLeadTime><ErrorMessage/></GetLeadTimesResult></GetLeadTimesResponse></soap:Body></soap:Envelope>';
		$this->assertContains($expected_response,$resource->soap_body,"Should have the expected soap body");
	}



	function testCancelOrder()
	{
	    setContext('com.splickit.goodcentssubs');
		$user_resource = createNewUserWithCC();
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user['user_id'];
		$order_adapter = new OrderAdapter($m);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'],'pickup',$note);
		$order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
		$ucid = $order_resource->ucid;
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000);

		$_SERVER['SOAPAction'] = "http://www.xoikos.com/webservices/CancelOrder";
		$xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CancelOrder xmlns="http://www.xoikos.com/webservices/"><OrderID>'.$ucid.'</OrderID></CancelOrder></soap:Body></soap:Envelope>';
		$request = new Request();
		$request->url = "/pos/xoikos";
		$request->method = "POST";
		$request->body = $xml;
		$request->mimetype = 'Applicationxml';


		$pos_controller = new PosController($mt,$u,$request,5);
		$resource = $pos_controller->processV2request();
		$this->assertTrue(isset($resource->send_soap_response),"should have set the soap flag on the resource");
		$expected_response = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CancelOrderResponse xmlns="http://www.xoikos.com/webservices/"><CancelOrderResult><Success>true</Success><ErrorMessage>Order with ID '.$ucid.' has been canceled.</ErrorMessage></CancelOrderResult></CancelOrderResponse></soap:Body></soap:Envelope>';
		$this->assertEquals($expected_response,$resource->soap_body,"Should have the expected soap body");

		// check to see if order was refunded.
		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
			$balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
		}
		$this->assertNotNull($balance_change_rows_by_user_id["$user_id-CCvoid"],"Should have found the CCvoid row");
		$this->assertEquals($order_resource->grand_total,$balance_change_rows_by_user_id["$user_id-CCvoid"]['charge_amt']);
		$this->assertContains('VOID from the API: ',$balance_change_rows_by_user_id["$user_id-CCvoid"]['notes']);

		$adm_reversal_resource = Resource::find(new AdmOrderReversalAdapter($mimetypes),''.$refund_results['order_reversal_id']);
		$this->assertNull($adm_reversal_resource);
	}

	function testCancelAuthedOrder()
	{
        setContext('com.splickit.goodcentssubs');
		$user_resource = createNewUserWithCC();
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user['user_id'];
		$order_adapter = new OrderAdapter($m);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['auth_merchant_id'],'pickup',$note);
		$order_data['merchant_payment_type_map_id'] = $this->ids['auth_merchant_payment_type_map_id'];
		$order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
		$ucid = $order_resource->ucid;
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000);

		OrderAdapter::updateOrderStatus('E',$order_id);

		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		$this->assertCount(2,$balance_change_adapter->getRecords(array("order_id"=>$order_id), $options),"should only be 2 rows in balance change");

		$_SERVER['SOAPAction'] = "http://www.xoikos.com/webservices/CancelOrder";
		$xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CancelOrder xmlns="http://www.xoikos.com/webservices/"><OrderID>'.$ucid.'</OrderID></CancelOrder></soap:Body></soap:Envelope>';
		$request = new Request();
		$request->url = "/pos/xoikos";
		$request->method = "POST";
		$request->body = $xml;
		$request->mimetype = 'Applicationxml';


		$pos_controller = new PosController($mt,$u,$request,5);
		$resource = $pos_controller->processV2request();
		$this->assertTrue(isset($resource->send_soap_response),"should have set the soap flag on the resource");
		$expected_response = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CancelOrderResponse xmlns="http://www.xoikos.com/webservices/"><CancelOrderResult><Success>true</Success><ErrorMessage>Order with ID '.$ucid.' has been canceled.</ErrorMessage></CancelOrderResult></CancelOrderResponse></soap:Body></soap:Envelope>';
		$this->assertEquals($expected_response,$resource->soap_body,"Should have the expected soap body");

		$order = CompleteOrder::getBaseOrderData($order_id);
		$this->assertEquals('E',$order['status'],"Status should have stayed at 'E'");

		// there should NOT have been a refund
		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
			$balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
		}
		$this->assertCount(3,$balance_change_records,"there should still be only 2 rows");
		$this->assertNotNull($balance_change_rows_by_user_id["$user_id-CCvoid"],"Should NOT have found the CCvoid row");
		$this->assertEquals('CANCELED',$balance_change_rows_by_user_id["$user_id-Authorize"]['notes'],'Note shoudl have been changed from PENDING to CANCELED');

		$adm_reversal_resource = Resource::find(new AdmOrderReversalAdapter($mimetypes),''.$refund_results['order_reversal_id']);
		$this->assertNull($adm_reversal_resource);
	}

	function testAddTipWithTimeout()
    {
        setContext('com.splickit.goodcentssubs');
        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];
        $order_adapter = new OrderAdapter($m);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['auth_merchant_id'],'pickup','sumdumnote',5);
        $order_data['tip'] = 0.00;
        $order_data['merchant_payment_type_map_id'] = $this->ids['auth_merchant_payment_type_map_id'];
        $order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $ucid = $order_resource->ucid;
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000);

//        OrderAdapter::updateOrderStatus('E',$order_id);
//        $options[TONIC_FIND_BY_METADATA]= array("order_id"=>$order_id,"process"=>'authorize');
//        $balanace_change_resource = Resource::find(new BalanceChangeAdapter($m),null,$options);
//        $balanace_change_resource->cc_transaction_id = 'fail-12345-12345-12345';
//        $balanace_change_resource->save();

        $tip_amt = "2.00";
        $_SERVER['SOAPAction'] = "http://www.xoikos.com/webservices/ApplyTipToOrder";
        $xml_body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/"><OrderID>'.$ucid.'</OrderID><Amount>'.$tip_amt.'</Amount></ApplyTipToOrder></soap:Body></soap:Envelope>';
        $request = new Request();
        $request->url = "/pos/xoikos";
        $request->method = "POST";
        $request->body = $xml_body;
        $request->mimetype = 'Applicationxml';
        $pos_controller = new PosController($mt,$user,$request,5);
        $_SERVER['TEST_VIO_TIMEOUT'] = 'true';
        $resource = $pos_controller->processV2request();
        $_SERVER['TEST_VIO_TIMEOUT'] = 'false';
        $this->assertEquals(200,$resource->http_code);
        $this->assertContains('<Success>false</Success>',$resource->soap_body);
        $this->assertContains("We had a Gateway Time-out trying to reach the CC processing facility for a capture",$resource->error);

        $base_order_data = CompleteOrder::getBaseOrderData($order_id,getM());
        $this->assertEquals(0.00,$base_order_data['tip_amt'],"this tip should stay at 0");

        $balance_change_adapter = new BalanceChangeAdapter(getM());
        $record = $balance_change_adapter->getRecord(['order_id'=>$order_id,'notes'=>'TIMEOUT']);
        $this->assertEquals(getStamp(),$record['cc_transaction_id']);
    }

	function testAddTipCaptureFailure()
    {
        setContext('com.splickit.goodcentssubs');
        $user_resource = createNewUserWithCC();
        $user_resource->uuid = 'fail-12345-12345-12345';
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];
        $order_adapter = new OrderAdapter($m);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['auth_merchant_id'],'pickup',$note);
        $order_data['tip'] = 0.00;
        $order_data['merchant_payment_type_map_id'] = $this->ids['auth_merchant_payment_type_map_id'];
        $order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $ucid = $order_resource->ucid;
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000);

        OrderAdapter::updateOrderStatus('E',$order_id);
        $options[TONIC_FIND_BY_METADATA]= array("order_id"=>$order_id,"process"=>'authorize');
        $balanace_change_resource = Resource::find(new BalanceChangeAdapter($m),null,$options);
        $balanace_change_resource->cc_transaction_id = 'fail-12345-12345-12345';
        $balanace_change_resource->save();

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
        $this->assertEquals(200,$resource->http_code);
        $this->assertContains('<Success>false</Success>',$resource->soap_body);
        $this->assertEquals("The remote server reset the connection. Please try again.",$resource->error);

        $base_order_data = CompleteOrder::getBaseOrderData($order_id,getM());
        $this->assertEquals(0.00,$base_order_data['tip_amt'],"this tip should stay at 0");
    }

	function testAddTipToCapturedOrder()
	{
        setContext('com.splickit.goodcentssubs');
		$user_resource = createNewUserWithCC();
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user['user_id'];
		$order_adapter = new OrderAdapter($m);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['auth_merchant_id'],'pickup',$note);
		$order_data['tip'] = 0.00;
		$order_data['merchant_payment_type_map_id'] = $this->ids['auth_merchant_payment_type_map_id'];
		$order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
		$ucid = $order_resource->ucid;
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000);

		OrderAdapter::updateOrderStatus('E',$order_id);
		$options[TONIC_FIND_BY_METADATA]= array("order_id"=>$order_id,"process"=>'authorize');
		$balanace_change_resource = Resource::find(new BalanceChangeAdapter($m),null,$options);
		$balanace_change_resource->notes = 'Captured';
		$balanace_change_resource->save();

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
		$this->assertEquals(200,$resource->http_code);
		$this->assertContains('<Success>true</Success>',$resource->soap_body);

		// now try with a tip already applied
		$pos_controller = new PosController($mt,$user,$request,5);
		$resource = $pos_controller->processV2request();
		$this->assertContains('<ErrorMessage>A 2.00 tip has already been applied to order: '.$ucid.'</ErrorMessage>',$resource->soap_body);
		$this->assertEquals(200,$resource->http_code);
	}

	function testCancelCapturedOrder()
	{
        setContext('com.splickit.goodcentssubs');
		$user_resource = createNewUserWithCC();
		$user = logTestUserResourceIn($user_resource);
		$user_id = $user['user_id'];
		$order_adapter = new OrderAdapter($m);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['auth_merchant_id'],'pickup',$note);
		$order_data['tip'] = 0.00;
		$order_data['merchant_payment_type_map_id'] = $this->ids['auth_merchant_payment_type_map_id'];
		$order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
		$ucid = $order_resource->ucid;
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000);

		OrderAdapter::updateOrderStatus('E',$order_id);

		// capture it
		$tip_amt = ".50";
		$_SERVER['SOAPAction'] = "http://www.xoikos.com/webservices/ApplyTipToOrder";
		$xml_body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/"><OrderID>'.$ucid.'</OrderID><Amount>'.$tip_amt.'</Amount></ApplyTipToOrder></soap:Body></soap:Envelope>';
		$request = new Request();
		$request->url = "/pos/xoikos";
		$request->method = "POST";
		$request->body = $xml_body;
		$request->mimetype = 'Applicationxml';
		$pos_controller = new PosController($mt,$user,$request,5);
		$resource = $pos_controller->processV2request();


		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
        $bcrs = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options);
		$this->assertCount(3,$bcrs,"should be 3 rows in balance change");


		// now cancel it
		$_SERVER['SOAPAction'] = "http://www.xoikos.com/webservices/CancelOrder";
		$xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CancelOrder xmlns="http://www.xoikos.com/webservices/"><OrderID>'.$ucid.'</OrderID></CancelOrder></soap:Body></soap:Envelope>';
		$request = new Request();
		$request->url = "/pos/xoikos";
		$request->method = "POST";
		$request->body = $xml;
		$request->mimetype = 'Applicationxml';


		$pos_controller = new PosController($mt,$u,$request,5);
		$resource = $pos_controller->processV2request();
		$this->assertTrue(isset($resource->send_soap_response),"should have set the soap flag on the resource");
		$expected_response = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CancelOrderResponse xmlns="http://www.xoikos.com/webservices/"><CancelOrderResult><Success>true</Success><ErrorMessage>Order with ID '.$ucid.' has been canceled.</ErrorMessage></CancelOrderResult></CancelOrderResponse></soap:Body></soap:Envelope>';
		$this->assertEquals($expected_response,$resource->soap_body,"Should have the expected soap body");

		$order = CompleteOrder::getBaseOrderData($order_id);
		$this->assertEquals('E',$order['status'],"Status should have stayed at 'E'");

		// check to see if order was refunded.
		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
			$balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
		}
		$this->assertCount(4,$balance_change_records,"there should now be 4 balance change records");
		$this->assertNotNull($balance_change_rows_by_user_id["$user_id-CCvoid"],"Should have found the CCvoid row");
		$this->assertEquals($order_resource->grand_total+.5,$balance_change_rows_by_user_id["$user_id-CCvoid"]['charge_amt']);
		$this->assertContains('VOID from the API: ',$balance_change_rows_by_user_id["$user_id-CCvoid"]['notes']);
		$this->assertEquals('captured',$balance_change_rows_by_user_id["$user_id-Authorize"]['notes'],'Note should have stayed at captured');


		$adm_reversal_resource = Resource::find(new AdmOrderReversalAdapter($mimetypes),''.$refund_results['order_reversal_id']);
		$this->assertNull($adm_reversal_resource);
	}

    
    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	
    	$skin_resource = getOrCreateSkinAndBrandIfNecessary('goodcentssubs','Goodcents Subs',140,430);
    	$ids['skin_id'] = $skin_resource->skin_id;
    	
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(1);
    	$ids['menu_id'] = $menu_id;
    	
//    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
//    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
//    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
//    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
//    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
//    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);

    	$merchant_resource = createNewTestMerchant($menu_id);
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;

		$merchant_resource_a = createNewTestMerchant($menu_id,array("no_payment"=>true));
		$merchant_id = $merchant_resource_a->merchant_id;


		$merchant_id_key = generateCode(10);
		$merchant_id_number = generateCode(5);
		$data['merchant_id_key'] = $merchant_id_key;
		$data['merchant_id_number'] = $merchant_id_number;
		$data['vio_selected_server'] = 'sage';
		$data['vio_merchant_id'] = $merchant_id;
		$data['name'] = "Test Billing Entity";
		$data['description'] = 'An entity to test with';
		$data['identifier'] = $merchant_resource_a->alphanumeric_id;
		$data['brand_id'] = $merchant_resource_a->brand_id;
		$data['type'] = $type;

		$card_gateway_controller = new CardGatewayController($mt, $u, $r);
		$resource = $card_gateway_controller->createPaymentGateway($data);
		$resource->process_type = 'authorize';
		$resource->save();
		$created_merchant_payment_type_map_id = $resource->merchant_payment_type_map->id;
		$ids['auth_merchant_id'] = $merchant_id;
		$ids['auth_merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
		$ids['auth_billing_entity_external'] = $resource->external_id;
    	
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
	PosControllerTest::main();
}

?>