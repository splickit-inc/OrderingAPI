<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class DeliveryTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $mdi_resource;
	var $user;
	var $merchant_id;
	var $test_user_delivery_location_resource;
	var $ids;
	
	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];

		setContext("com.splickit.order");
		
		// we dont want to call to inspirepay 
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->ids = $_SERVER['unit_test_ids'];
		
		$user_id = $_SERVER['unit_test_ids']['user_id'];
		
		$user_resource = SplickitController::getResourceFromId($user_id, 'User');
		$this->user = $user_resource->getDataFieldsReally();
		$_SERVER['AUTHENTICATED_USER'] = $this->user;
    $_SERVER['AUTHENTICATED_USER_ID'] = $this->user['user_id'];
		
    $this->merchant_id = $_SERVER['unit_test_ids']['merchant_id'];
    	
		$mdi_adapter = new MerchantDeliveryInfoAdapter($mimetypes);
		$this->mdi_resource = $mdi_adapter->getExactResourceFromData(array("merchant_id"=>$_SERVER['unit_test_ids']['merchant_id']));
	}
	
	function tearDown() 
	{
		//delete your instance
		$this->mdi_resource->zip_codes = "false";
		$this->mdi_resource->save();
		unset($this->user);
		unset($this->mdi_resource);
		unset($this->ids);
		$_SERVER['STAMP'] = $this->stamp;
    }

    function testLogicallyDeletedDeliveryAddress()
    {
        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;

        $prep_resource = Resource::createByData(new MerchantPreptimeInfoAdapter(getM()), array("merchant_id"=>$merchant_resource->merchant_id,"entree_preptime_seconds"=>120));

        // create the user record
        $user_resource = createNewUserWithCCNoCVV();
        $contact_no = $user_resource->contact_no;
        $user = logTestUserResourceIn($user_resource);
        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"1045 Pine Street","address2":"","city":"boulder","state":"co","zip":"80302","phone_no":"9709262121","lat":40.0197891,"lng":-105.284703}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "Application/json";
        $request->_parseRequestBody();
        $request->method = 'POST';
        $request->url = "/users/" . $user['uuid'] . "/userdeliverylocation";
        $user_controller = new UserController(getM(), $user, $request, 5);
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;
        $user_delivery_location_resource = SplickitController::getResourceFromId($user_address_id, 'UserDeliveryLocation');

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'delivery');
        $cart_data['user_addr_id'] = $user_address_id;

        $json_encoded_data = json_encode($cart_data);
        $url = '/app2/apiv2/cart/checkout';
        $request = createRequestObject($url,'post',$json_encoded_data,'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);

        // now logically delete the delivery record and place the order
        $user_delivery_location_resource->logical_delete = 'Y';
        $user_delivery_location_resource->save();

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000);

        // now see what is on teh order ticket
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'E');
        $email_controller = ControllerFactory::generateFromMessageResource($message_resource);
        $ready_to_send_message_resource = $email_controller->prepMessageForSending($message_resource);
        $message_text = $ready_to_send_message_resource->message_text;
        myerror_log($message_text);
        $this->assertContains("Call Customer: $contact_no",$message_text);
    }

    function testDeliveryMinimumNotMetOnCartCheckoutAndAddMakeSureToReturnCartObjectWithError()
    {
        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;

        $data = array("merchant_id"=>$merchant_resource->merchant_id);

        $prep_resource = Resource::createByData(new MerchantPreptimeInfoAdapter(getM()), array("merchant_id"=>$merchant_resource->merchant_id,"entree_preptime_seconds"=>120,"delivery_throttling_on"=>'N'));

        // set merchant delivery info
        $mdia = new MerchantDeliveryInfoAdapter($mimetypes);
        $mdia_resource = $mdia->getExactResourceFromData($data);
        $mdia_resource->minimum_order = 10.00;
        $mdia_resource->save();



        // create the user record
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"1045 Pine Street","address2":"","city":"boulder","state":"co","zip":"80302","phone_no":"9709262121","lat":40.0197891,"lng":-105.284703}';
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
        $user_delivery_location_resource = SplickitController::getResourceFromId($user_address_id, 'UserDeliveryLocation');

        // url = isindeliveryarea
        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id?log_level=5",'GET',$b,$m);
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range),"should have found the 'is in delivery range' field");
        $this->assertTrue($resource->is_in_delivery_range," the is in delivery range should be true");

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'delivery');
        $cart_data['user_addr_id'] = $user_address_id;

        $json_encoded_data = json_encode($cart_data);
        $url = '/app2/apiv2/cart/checkout';
        $request = createRequestObject($url,'post',$json_encoded_data,'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $resource = $place_order_controller->processV2Request();
        $this->assertNotNull($resource->error,"should have found a delivery minimum but the get checkout went through");
        $this->assertEquals("Minimum order required! You have not met the minimum subtotal of $10.00 for your deliver area.", $resource->error);
        $this->assertEquals("CheckoutError",$resource->error_type);

        $checkout_resource = $resource->data;
        $this->assertTrue(isset($checkout_resource->ucid),"it should return a cart object");
        $this->assertNotNull($checkout_resource->order_summary,"IT should have the order summary of the cart object");
        $order_summary = $checkout_resource->order_summary;
        $this->assertCount(4,$order_summary);
        $this->assertCount(1,$order_summary['cart_items']);
        $this->assertCount(3,$order_summary['receipt_items']);


        $response = getV2ResponseWithJsonFromResource($resource, $headers);
        $body = $response->body;
        $response_array = json_decode($body,true);
        $this->assertNotNull($response_array['error'],"It should have an error parameter");
        $this->assertNotNull($response_array['data'],"It should contiain the cart info as data");
        $cart_data = $response_array['data'];

        $response_order_summary = $cart_data['order_summary'];
        $this->assertCount(4,$response_order_summary);
        $this->assertCount(1,$response_order_summary['cart_items']);
        $this->assertCount(3,$response_order_summary['receipt_items']);
    }

    function testStripDeliveryPhoneNumber()
    {
        $user_resource = createNewUser();
        $user = logTestUserResourceIn($user_resource);
        $json = '{"user_id":"'.$user['user_id'].'","name":"","address1":"11 Riverside Drive","address2":"","city":"new york","state":"ny","zip":"12345","phone_no":"(888)775-4254","lat":40.796202,"lng":-73.936635}';
        $request = createRequestObject("/users/".$user['uuid']."/userdeliverylocation","POST",$json,"application/json");
        $user_controller = new UserController($mt, $user, $request,5);
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error,"should not have gotten a delivery save error but did");
        $user_address_id = $response->user_addr_id;

        $udal = UserDeliveryLocationAdapter::staticGetRecordByPrimaryKey("$user_address_id","UserDeliveryLocationAdapter");
        $this->assertEquals("888-775-4254",$udal['phone_no'],"delivery phone number should not have any special characters and shoudl be formatted correctly");
    }

    function testXoikosFormattedDeliveryOrder()
    {
        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);

        $prep_resource = Resource::createByData(new MerchantPreptimeInfoAdapter(getM()), array("merchant_id"=>$merchant_resource->merchant_id,"entree_preptime_seconds"=>120,"delivery_throttling_on"=>'N'));

        $map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_resource->merchant_id,"message_format"=>'X',"delivery_addr"=>"Xoikos","message_type"=>"X"));
        $merchant_id = $merchant_resource->merchant_id;
        $mdpda = new MerchantDeliveryPriceDistanceAdapter($mimetypes);
        $mdpd_resource = $mdpda->getExactResourceFromData(array("merchant_id"=>$merchant_id));
        $mdpd_resource->distance_up_to = 100;
        $mdpd_resource->price = 1.00;
        $mdpd_resource->save();


        $mdi_adapter = new MerchantDeliveryInfoAdapter($this->mimetypes);
        $mdi_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
        $mdi_resource = Resource::findExact($mdi_adapter,'',$mdi_options);
        $mdi_resource->allow_asap_on_delivery = 'Y';
        $mdi_resource->save();


        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
        $request = createRequestObject("/users/" . $user['uuid'] . "/userdeliverylocation","POST",$json,'application/json');
        $user_controller = new UserController($mt, $user, $request, 5);

        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;
        $user_delivery_address_resource = Resource::find(new UserDeliveryLocationAdapter(),"$user_address_id");
        $user_delivery_address_resource->phone_no = "(303) 555-6666";
        $user_delivery_address_resource->save();

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id", 'GET');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range),"should have found the 'is in delivery range' field");
        $this->assertTrue($resource->is_in_delivery_range," the is in delivery range should be true");

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'delivery','the note',10);
        $order_data['user_addr_id'] = $user_address_id;
        $json_encoded_data = json_encode($order_data);

        $url = '/app2/apiv2/cart/checkout';
        $request = createRequestObject($url,'post',$json_encoded_data,'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);
        $cart_ucid = $checkout_resource->ucid;
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertTrue(isset($order_resource->user_delivery_address),"there should be a user delivery address field on the order resource");
        $this->assertEquals("4670 N Broadway St",$order_resource->user_delivery_address['address1']);

        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,"X");
        $xoikos_message_controller = ControllerFactory::generateFromMessageResource($message_resource);
        $message_data_as_resource = $xoikos_message_controller->populateMessageData($message_resource);
        $this->assertEquals("3035556666",$message_data_as_resource->delivery_info->phone_no,"phone number should have been formatted correctly");
        $this->assertEquals('true',$message_data_as_resource->ASAP,"It shoudl have the ASAP field");

        $xoikos_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id, 'X');
        $xoikos_controller = ControllerFactory::generateFromMessageResource($xoikos_message_resource);
        $ready_to_send_message_resource = $xoikos_controller->prepMessageForSending($xoikos_message_resource);
        $message_text = $ready_to_send_message_resource->message_text;
        myerror_log($message_text);
        $body = cleanUpDoubleSpacesCRLFTFromString($ready_to_send_message_resource->message_text);

        $expected_asap_node = "<FulfillmentASAP>true</FulfillmentASAP>";
        $this->assertContains($expected_asap_node,$body,"should have created ASAP node");

    }



    /**
     * @expectedException BadDeliveryDataPassedInException
     */
    function testGetDeliveryInfoBadData()
    {
    	$delivery_controller = new DeliveryController($mt, $u, $r);
    	$delivery_controller->getRelevantDeliveryInfoFromIds(13245,null);
    }
    
    function testNoUserDeliveryLocationIdOnGetCheckoutData()
    {
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	$user = logTestUserIn($user_id);
   		$merchant_id = $this->merchant_id;
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', 'skip hours');
    	$order_data['lead_time'] = '0';
    	$order_data['delivery'] = 'yes';

    	$order_data['delivery_time'] = "As soon as possible";

        $checkout_data_resource = getCheckoutResourceFromOrderData($order_data);
    	$this->assertNotNull($checkout_data_resource->error);
    	$this->assertEquals("Please select or create a destination address for your delivery order.", $checkout_data_resource->error);     	
    }
    
    function testGetDeliveryTaxAmount()
    {

    	$merchant_resource = createNewTestMerchant();
    	$merchant_resource->delivery = 'Y';
    	$merchant_resource->state = 'CA';
    	$merchant_resource->save();
    	$merchant = $merchant_resource->getDataFieldsReally();
    	$merchant_id = $merchant_resource->merchant_id;

        $prep_resource = Resource::createByData(new MerchantPreptimeInfoAdapter(getM()), array("merchant_id"=>$merchant_resource->merchant_id,"entree_preptime_seconds"=>120,"delivery_throttling_on"=>'N'));

    	MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $this->ids['menu_id'], 'pickup');
    	MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $this->ids['menu_id'], 'delivery');
    	
    	$mdi_adapter = new MerchantDeliveryInfoAdapter($mimetypes);
    	$options[TONIC_FIND_BY_METADATA] = array("merchant_id"=>$merchant_resource->merchant_id);
    	$mdi_resource = Resource::find($mdi_adapter, null, $options);
    	
    	$place_order_controller = new PlaceOrderController($mt, $u, $r, 5);
    	$delivery_tax_amount = $place_order_controller->getDeliveryTaxAmount($merchant, 5.00);
    	$this->assertEquals('0.00', $delivery_tax_amount);
    	
    	$lookup_adapter = new LookupAdapter($mimetypes);
    	$sql = "INSERT INTO `Lookup` (`lookup_id`, `type_id_field`, `type_id_value`, `type_id_name`, `active`, `created`, `modified`, `logical_delete`) VALUES (NULL, 'state_delivery_is_taxed', 'CA', 'Yes', 'Y', NOW(), '0000-00-00 00:00:00.000000', 'N');";
    	$lookup_adapter->_query($sql);
    	$delivery_tax_amount = $place_order_controller->getDeliveryTaxAmount($merchant, 5.00);
    	$this->assertEquals('0.50', $delivery_tax_amount);
    	
    	// now set an override value
    	$tax_adapter = new TaxAdapter($mimetypes);
    	$delivery_tax_resource = $tax_adapter->createTaxRecord($merchant_id, "Delivery", 20, 99);
    	
    	$delivery_tax_amount = $place_order_controller->getDeliveryTaxAmount($merchant, 5.00);
    	$this->assertEquals('1.00', $delivery_tax_amount);

    	// now try an override of 0%
    	$delivery_tax_resource->rate = 0;
    	$delivery_tax_resource->save();
    	
    	$delivery_tax_amount = $place_order_controller->getDeliveryTaxAmount($merchant, 5.00);
    	$this->assertEquals('0.00', $delivery_tax_amount);
    	return $merchant_id;    	
    }
    
    /**
     * @depends testGetDeliveryTaxAmount
     */
    function testAddDeliveryTaxToTotal($merchant_id)
    {
    	$mdi_adapter = new MerchantDeliveryInfoAdapter($mimetypes);
    	$mdi_resource = $mdi_adapter->getExactResourceFromData(array("merchant_id"=>$merchant_id));
    	$mdi_resource->delivery_price_type = 'zip';
    	$mdi_resource->save();
    	
    	$tax_adapter = new TaxAdapter($mimetypes);
    	$tax_resource = $tax_adapter->getExactResourceFromData(array("merchant_id"=>$merchant_id,"tax_group"=>99));
    	$sql = "DELETE FROM Tax WHERE tax_id = ".$tax_resource->tax_id;
    	$tax_adapter->_query($sql);

    	$mdpd_adapter = new MerchantDeliveryPriceDistanceAdapter($mimetypes);
    	$mdpd_resources = $mdpd_adapter->getMerchantDeliveryPriceDistanceResourcesOrderedByPriceAscending($merchant_id);
    	$mdpd_resource = $mdpd_resources[0];
    	$mdpd_resource->zip_codes = '12345';
    	$mdpd_resource->price = 1.00;
    	$mdpd_resource->save();
    	    	
   		$json = '{"user_id":"'.$this->user['user_id'].'","name":"","address1":"11 Riverside Drive","address2":"","city":"new york","state":"ny","zip":"12345","phone_no":"(888)775-4254","lat":40.796202,"lng":-73.936635}';
    	$request = new Request();
    	$request->body = $json;
    	$request->mimetype = "Application/json";
    	$request->_parseRequestBody(); 
    	$request->method = 'POST';
    	$request->url = "/users/".$this->user['uuid']."/userdeliverylocation";
    	$user_controller = new UserController($mt, $this->user, $request,5);

    	$response = $user_controller->processV2Request();
    	$this->assertNull($response->error,"should not have gotten a delivery save error but did");
    	$user_address_id = $response->user_addr_id;

    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', 'skip hours',10);
    	$order_data['lead_time'] = '45';
    	$order_data['delivery'] = 'yes';
    	$order_data['tip'] = 0.00;
    	$order_data['user_addr_id'] = $user_address_id;
    	$order_data['delivery_time'] = "As soon as possible";

        $order_resource = placeOrderFromOrderData($order_data, getTomorrowTwelveNoonTimeStampDenver());
    	$this->assertNull($order_resource->error);
    	$this->assertTrue($order_resource->order_id > 1000);  	
    	
    	$order_amount = $order_resource->order_amt;
    	$this->assertEquals(15.00, $order_amount);
    	$total_tax = $order_resource->total_tax_amt;
    	$this->assertEquals(1.60, $total_tax);
    	$delivery_price = $order_resource->delivery_amt;
    	$this->assertEquals(1.00, $delivery_price);
    	$grand_total = $order_resource->grand_total;
    	$this->assertEquals(17.60, $grand_total);
    	$data['user_addr_id'] = $user_address_id;
    	$data['merchant_id'] = $merchant_id;
    	return $data;
    }
    
    /**
     * @depends testAddDeliveryTaxToTotal
     */
    function testGetDeliveryInfoFromRequest($data)
    {
    	$request = new Request();
    	$data['merchant_id'] = $data['merchant_id'];
    	$data['user_addr_id'] = $data['user_addr_id'];
    	$request->data = $data;
    	$delivery_controller = new DeliveryController($mt, $u, $request);
    	$info = $delivery_controller->getRelevantDeliveryInfoFromRequest();
    	$this->assertEquals(1.00, $info['price']);
    }
    
    function testDuplicateAddressReturnUserAddressId()
    {
    	$user_resource = createNewUser();
    	$user = logTestUserIn($user_resource->user_id);
   		$json = '{"user_addr_id":null,"user_id":"'.$user['user_id'].'","name":"","address1":"11 Riverside Drive","address2":"","city":"new york","state":"ny","zip":"12345","phone_no":"(888)7754254","lat":40.796202,"lng":-73.936635}';
    	$request = new Request();
    	$request->body = $json;
    	$request->mimetype = "Application/json";
    	$request->_parseRequestBody(); 
    	$request->method = 'POST';
    	$request->url = "/users/".$user['uuid']."/userdeliverylocation";
    	$user_controller = new UserController($mt, $user, $request,5);
    	$response = $user_controller->processV2Request();
    	$this->assertNull($response->error,"should not have gotten a delivery save error but did");
    	$this->assertNotNull($response->user_addr_id);
    	$user_address_id = $response->user_addr_id;
    	
   		$json = '{"user_addr_id":null,"user_id":"'.$user['user_id'].'","name":"","address1":"11 Riverside Drive","address2":"","city":"new york","state":"ny","zip":"12345","phone_no":"(888)7754254","lat":40.796202,"lng":-73.936635}';
    	$request2 = new Request();
    	$request2->body = $json;
    	$request2->mimetype = "Application/json";
    	$request2->_parseRequestBody(); 
    	$request2->method = 'POST';
    	$request2->url = "/users/".$user['uuid']."/userdeliverylocation";
    	$user_controller2 = new UserController($mt, $user, $request2,5);
    	$response2 = $user_controller2->processV2Request();
    	$this->assertNull($response2->error,"should not have gotten a delivery save error but did");
    	$this->assertNotNull($response2->user_addr_id);
    	$user_address_id2 = $response2->user_addr_id;
    	
    	$this->assertEquals($user_address_id, $user_address_id2);
    	return $user_address_id;
    }
    
    /**
     * @depends testDuplicateAddressReturnUserAddressId
     */
    function testDeleteUserAddress($user_address_id)
    {
    	$udl_record = UserDeliveryLocationAdapter::staticGetRecordByPrimaryKey($user_address_id, 'UserDeliveryLocation');
    	$user = logTestUserIn($udl_record['user_id']);
    	$request = new Request();
    	$request->method = 'DELETE';
    	$request->url = "/users/".$user['uuid']."/userdeliverylocation/$user_address_id";
    	$user_controller = new UserController($mt, $user, $request,5);
    	$response = $user_controller->processV2Request();
    	$this->assertNull($response->error,"should not have gotten a delivery save error but did");
    	$this->assertEquals('success', $response->result);
    	
    	$this->assertNull(UserDeliveryLocationAdapter::staticGetRecordByPrimaryKey($user_address_id, 'UserDeliveryLocation'));
    }
    
    function testDeliveryMessageWithClosedToday()
    {
    	$merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;

        $prep_resource = Resource::createByData(new MerchantPreptimeInfoAdapter(getM()), array("merchant_id"=>$merchant_resource->merchant_id,"entree_preptime_seconds"=>120,"delivery_throttling_on"=>'N'));


    	// set day open to 'N'
    	$sql = "UPDATE Hour SET day_open = 'N' WHERE merchant_id = $merchant_id";
    	$hour_adapter = new HourAdapter($mimetypes);
    	$hour_adapter->_query($sql);
    	// now do tests as a regular user 
    	$user_resource = createNewUserWithCC();
      $user = logTestUserResourceIn($user_resource);
      $request = new Request();
      $request->url = "merchants/$merchant_id/delivery";
      $merchant_controller = new MerchantController($mt, $user, $request);
      $merchant_result = $merchant_controller->getMerchant();
      $user_message = $merchant_result->user_message;
      $expected = "We're sorry, this merchant is closed for delivery orders today.";
      $this->assertEquals($expected, $user_message);
      
      $user_resource = createNewUser(array("flags"=>"1C20000001"));
      $user = logTestUserIn($user_resource->user_id);
      $user_id = $user['user_id'];
    }

    function testDeliveryMessage()
    {    	
    	$merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	// Set minimum to something that will trigger the message
    	$merchant_delivery_info = MerchantDeliveryInfoAdapter::getFullMerchantDeliveryInfoAsResource($merchant_id);
    	$merchant_delivery_info->minimum_order = '5.00';
    	$merchant_delivery_info->save();
    	// now do tests as a regular user
  		$user = logTestUserIn($this->ids['user_id']);
  		$request = new Request();
  		$request->url = "merchants/$merchant_id/delivery";
  		$merchant_controller = new MerchantController($mt, $user, $request);
  		$merchant_result = $merchant_controller->getMerchant();
  		$user_message = $merchant_result->user_message;
  		$this->assertContains("Please note: This merchant has a minimum delivery order of $5.00", $user_message);
  		
  		// now try the same with pickup and we should not get the message
  		$request->url = "merchants/$merchant_id/";
  		$merchant_controller = new MerchantController($mt, $user, $request);
  		$merchant_result = $merchant_controller->getMerchant();
  		$user_message2 = $merchant_result->user_message;
  		
  		// JSB 12/14/2014 - This unusual construct seems to be because of limits on delivery times. If you run the tests when a merchant wouldn't accept delivery, you'd get a different error message. 
  		if ($user_message2) {
  			$this->assertNotContains("Please note: This merchant has a minimum delivery order of $5.00", $user_message2);
  		} else {
  			$this->assertNull($user_message2);
  		}		
    }
    
    function testDeliveryMessageWithMulitpleCharges()
    {
    	$merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
    	$merchant_id = $merchant_resource->merchant_id;
    	$merchant_delivery_info_id = $merchant_resource->merchant_delivery_info_id;
    	$mdi_resource = SplickitController::getResourceFromId($merchant_delivery_info_id, 'MerchantDeliveryInfo');
    	$mdi_resource->minimum_order = 5.00;
    	$mdi_resource->save();
      	// now do tests as a regular user
  		$user = logTestUserIn($this->ids['user_id']);
  		$request = new Request();
  		$request->url = "merchants/$merchant_id/delivery";
  		$merchant_controller = new MerchantController($mt, $user, $request);
  		$merchant_result = $merchant_controller->getMerchant();
  		$user_message = $merchant_result->user_message;
  		$this->assertContains("Please note: This merchant has a minimum delivery order of $5.00", $user_message);
  		  	
  		// no try it with different minimums on the price records.  should NOT get a delivery minimum message
  		// first add another record
  		$mdpda = new MerchantDeliveryPriceDistanceAdapter($mimetypes);
  		$mdpd_resource = Resource::createByData($mdpda, array("merchant_id"=>$merchant_id,"distance_up_to"=>20,"minimum_order_amount"=>10.00));
  		$this->assertNotNull($mdpd_resource);
  		
  		$boolean = MerchantDeliveryPriceDistanceAdapter::areThereMinimumsSetOnDeliveryDistancePriceRecords($merchant_id);
  		$this->assertTrue($boolean);
  
  		$request = new Request();
  		$request->url = "merchants/$merchant_id/delivery";
  		$merchant_controller = new MerchantController($mt, $user, $request);
  		$merchant_result = $merchant_controller->getMerchant();
  		$user_message2 = $merchant_result->user_message;
  		// JSB 12/14/2014 - This unusual construct seems to be because of limits on delivery times. If you run the tests when a merchant wouldn't accept delivery, you'd get a different error message.
  		if ($user_message2) {
  			$this->assertNotContains("Please note: This merchant has a minimum delivery order of $5.00", $user_message2);
  		} else {
  			$this->assertNull($user_message2); 
  		}
    }
        	
    function testPolygonCode()
    {
    	$pointLocation = new PointLocation();
		$points = array("50 70","70 40","-20 30","100 10","-10 -10","40 -20","110 -20");
		$polygon = array("-50 30","50 70","100 50","80 10","110 -10","110 -30","-20 -50","-30 -40","10 -10","-10 10","-30 -20","-50 30");
    	$result = $pointLocation->pointInPolygon($points[0], $polygon);
    	$this->assertEquals('vertex', $result);
    	$this->assertTrue(PointLocation::isPointWithinThePolygon($points[0], $polygon));
    	
    	$result = $pointLocation->pointInPolygon($points[1], $polygon);
    	$this->assertEquals('inside', $result);
    	$this->assertTrue(PointLocation::isPointWithinThePolygon($points[1], $polygon));
    	
    	$result = $pointLocation->pointInPolygon($points[2], $polygon);
    	$this->assertEquals('inside', $result);
      	$this->assertTrue(PointLocation::isPointWithinThePolygon($points[2], $polygon));
    	
    	$result = $pointLocation->pointInPolygon($points[3], $polygon);
    	$this->assertEquals('outside', $result);
    	$this->assertFalse(PointLocation::isPointWithinThePolygon($points[3], $polygon));
    	
    	$result = $pointLocation->pointInPolygon($points[4], $polygon);
    	$this->assertEquals('outside', $result);
    	$this->assertFalse(PointLocation::isPointWithinThePolygon($points[4], $polygon));
    	
    	$result = $pointLocation->pointInPolygon($points[5], $polygon);
    	$this->assertEquals('inside', $result);
    	$this->assertTrue(PointLocation::isPointWithinThePolygon($points[5], $polygon));
    	
    	$result = $pointLocation->pointInPolygon($points[6], $polygon);
    	$this->assertEquals('boundary', $result);
    	$this->assertTrue(PointLocation::isPointWithinThePolygon($points[6], $polygon));
    }
    
    function testJMPolygon()
    {
		$point_location = new PointLocation();
		$polygon = array("40.707372 -74.01209","40.706135 -74.009729","40.704867 -74.007916","40.703679 -74.006479","40.706591 -74.002037","40.708217 -74.003475","40.708844 -74.00399","40.708722 -74.004279","40.70851 -74.004762","40.709543 -74.005084","40.710879 -74.007723","40.711414 -74.008678","40.707372 -74.01209");
		
		// first test one inside
		$point = "40.708424 -74.007318";
		$result = $point_location->pointInPolygon($point, $polygon);
    	$this->assertEquals('inside', $result);
    	
    	// now outside
		$point = "40.705154 -74.010108";
		$result = $point_location->pointInPolygon($point, $polygon);
    	$this->assertEquals('outside', $result);
    }
    
    function testJMPolygon2()
    {
		$point_location = new PointLocation();
		$polygon_coordinates_as_string = "40.702570 -74.012833,40.705498 -74.015579,40.711581 -74.015279,40.715452 -74.012232,40.715908 -74.008842,40.715127 -74.005108,40.712720 -74.001589,40.710703 -74.000258,40.708068 -73.999701,40.705270 -73.999701,40.703969 -74.002705,40.703155 -74.007683,40.702765 -74.002876,40.701529 -74.006567,40.701333 -74.010687";
		$mdpda = new MerchantDeliveryPriceDistanceAdapter($mimetypes);
    	$polygon = $mdpda->createPolygonArrayFromString($polygon_coordinates_as_string);
    	
		// first test one inside
		$point = "40.713858 -74.012532";
		$result = $point_location->pointInPolygon($point, $polygon);
    	$this->assertEquals('inside', $result);

    	// now outside
		$point = "50.705154 -84.010108";
		$result = $point_location->pointInPolygon($point, $polygon);
    	$this->assertEquals('outside', $result);
    }
    
    function testCreatePolygonArrayFromString()
    {
    	$polygon_coordinates_as_string = "40.707372 -74.01209,40.706135 -74.009729,40.704867 -74.007916";
    	$mdpda = new MerchantDeliveryPriceDistanceAdapter($mimetypes);
    	$polygon = $mdpda->createPolygonArrayFromString($polygon_coordinates_as_string);
    	$this->assertCount(4, $polygon);
    }
    
    function testJMPolygonAsString()
    {
    	$polygon_coordinates_as_string = "40.707372 -74.01209,40.706135 -74.009729,40.704867 -74.007916,40.703679 -74.006479,40.706591 -74.002037,40.708217 -74.003475,40.708844 -74.00399,40.708722 -74.004279,40.70851 -74.004762,40.709543 -74.005084,40.710879 -74.007723,40.711414 -74.008678";
    	$point = "40.708424 -74.007318";
    	
    	$mdpda = new MerchantDeliveryPriceDistanceAdapter($mimetypes);
    	$polygon = $mdpda->createPolygonArrayFromString($polygon_coordinates_as_string);
    	$this->assertCount(13, $polygon);
    	
    	$result = PointLocation::isPointWithinThePolygon($point, $polygon);
    	$this->assertTrue($result);
    }

    function testCachedPrice()
    {
    	$user_id = $this->ids['user_id'];
    	$udla = new UserDeliveryLocationAdapter($mimetypes);
    	$data = array("user_id"=>$user_id,"address1"=>"888 main","city"=>"new York","state"=>"ny","zip"=>"10029","phone_no"=>"1234567890","lat"=>55.705154,"lng"=>-113.010108);
    	$udla_resource = Resource::createByData($udla, $data);
    	$udl_id = $udla_resource->user_addr_id;
    	$merchant_id = $this->ids['merchant_id'];
    	
    	$mdpd_adapter = new MerchantDeliveryPriceDistanceAdapter($mimetypes);
    	$mdpd_resources = $mdpd_adapter->getMerchantDeliveryPriceDistanceResourcesOrderedByPriceAscending($merchant_id);
    	$first = $mdpd_resources[0];
    	
    	$udlmpma = new UserDeliveryLocationMerchantPriceMapsAdapter($mimetypes);
    	$this->assertNull($udlmpma->getStoredUserDeliveryLocationMerchantPriceDistanceMapIdIfItExists($udl_id, $merchant_id));
    	
    	$resource = $udlmpma->createRecord($udl_id, $merchant_id, $first->map_id);
    	$this->assertNotNull($resource);    	
    	$this->assertEquals($first->map_id,$udlmpma->getStoredUserDeliveryLocationMerchantPriceDistanceMapIdIfItExists($udl_id, $merchant_id));
    }
    
    function testDeliveryPriceBasedOnPolygon()
    {
		$merchant_resource = createNewTestMerchant();
    	$merchant_resource->delivery = 'Y';
    	$merchant_resource->save();
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$data = array("merchant_id"=>$merchant_resource->merchant_id);

        $prep_resource = Resource::createByData(new MerchantPreptimeInfoAdapter(getM()), array("merchant_id"=>$merchant_resource->merchant_id,"entree_preptime_seconds"=>120,"delivery_throttling_on"=>'N'));


    	// set merchant delivery info
    	$mdia = new MerchantDeliveryInfoAdapter($mimetypes);
    	$mdia_resource = $mdia->getExactResourceFromData($data);	
    	$mdia_resource->minimum_order = 0.01;
    	$mdia_resource->delivery_price_type = 'polygon';
    	$mdia_resource->delivery_cost = 1.00;
    	$mdia_resource->delivery_increment = 15;
    	$mdia_resource->max_days_out = 3;
    	$mdia_resource->save();
    	
    	//map it to a menu
    	MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $this->ids['menu_id'], 'pickup');
    	MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $this->ids['menu_id'], 'delivery');
    	
    	// now create the distance price records.
    	$mdpd = new MerchantDeliveryPriceDistanceAdapter($mimetypes);
    	$mdpd_resource = $mdpd->getExactResourceFromData($data);
    	$mdpd_resource->polygon_coordinates = "40.707372 -74.01209,40.706135 -74.009729,40.704867 -74.007916,40.703679 -74.006479,40.706591 -74.002037,40.708217 -74.003475,40.708844 -74.00399,40.708722 -74.004279,40.70851 -74.004762,40.709543 -74.005084,40.710879 -74.007723,40.711414 -74.008678";
    	$mdpd_resource->price = 2.00;
    	$mdpd_resource->save();
    	
    	$mdpd_resource->_exists = false;
    	unset($mdpd_resource->map_id);
    	$mdpd_resource->polygon_coordinates = "40.702570 -74.012833,40.705498 -74.015579,40.711581 -74.015279,40.715452 -74.012232,40.715908 -74.008842,40.715127 -74.005108,40.712720 -74.001589,40.710703 -74.000258,40.708068 -73.999701,40.705270 -73.999701,40.703969 -74.002705,40.703155 -74.007683,40.702765 -74.002876,40.701529 -74.006567,40.701333 -74.010687";
    	$mdpd_resource->distance_up_to = 2;
    	$mdpd_resource->price = 4.00;
    	$mdpd_resource->save();
    	$user_for_test_mdpd_id = $mdpd_resource->map_id;
    	
    	$user_resource = createNewUser();
    	$user_id = $user_resource->user_id;
    	
    	//outside
    	$udla = new UserDeliveryLocationAdapter($mimetypes);
    	$data = array("user_id"=>$user_id,"address1"=>"100 main","city"=>"new York","state"=>"ny","zip"=>"10038","phone_no"=>"1234567890","lat"=>42.705154,"lng"=>-76.010108);
    	$udla_resource = Resource::createByData($udla, $data);
    	$udl_id = $udla_resource->user_addr_id;
    	$results = $mdia->getDeliveryPriceFromIds($udl_id, $merchant_id);
    	$this->assertFalse($results);
    	
    	//make it inside outer
    	$udla_resource->lat = 40.713858; 
    	$udla_resource->lng = -74.012532;
    	$udla_resource->save();
    	$udl_id = $udla_resource->user_addr_id;
    	
    	$results = $mdia->getDeliveryPriceFromIds($udl_id, $merchant_id);
    	$this->assertEquals(4.00, $results);
		$this->assertFalse($mdia->getCachedPriceBooleanForLastCall());
    	
		// make it inside inner
    	$data = array("user_id"=>$user_id,"address1"=>"102 south street","city"=>"new York","state"=>"ny","zip"=>"10038","phone_no"=>"1234567890","lat"=>40.708424,"lng"=>-74.007318);
    	$udla_resource = Resource::createByData($udla, $data);
    	$udl_id = $udla_resource->user_addr_id;
    	
    	$results = $mdia->getDeliveryPriceFromIds($udl_id, $merchant_id);
    	$this->assertEquals(2.00, $results);
    	$this->assertFalse($mdia->getCachedPriceBooleanForLastCall());
    	
    	$udlmpda = new UserDeliveryLocationMerchantPriceMapsAdapter($mimetypes);
    	$usdlmdpm_record_resource = $udlmpda->getExactResourceFromData(array("user_delivery_location_id"=>$udl_id,"merchant_id"=>$merchant_id));
    	$usdlmdpm_record_resource->merchant_delivery_price_distance_map_id = $user_for_test_mdpd_id;
    	$usdlmdpm_record_resource->save();
    	
    	$results = $mdia->getDeliveryPriceFromIds($udl_id, $merchant_id);
    	$this->assertEquals(4.00, $results);
    	$this->assertTrue($mdia->getCachedPriceBooleanForLastCall(),'Should have used the stored value in the db rather than call out to the third party API');
    	return $merchant_id;
    }
    
    function testSetAddressAlreadyExisting()
    {
    	$user_resource = createNewUser();
    	$user = logTestUserResourceIn($user_resource);
    	$user_id = $user['user_id'];
    	$json = '{"user_id":"'.$user_id.'","name":"","address1":"332 east 116th street","address2":"","city":"new york","state":"ny","zip":"10029","phone_no":"1234566890","lat":40.796202,"lng":-73.936635}';
    	$request = new Request();
    	$request->body = $json;
    	$request->mimetype = "Application/json";
    	$request->_parseRequestBody(); 
    	$request->method = 'POST';
    	$request->url = "/users/".$user['uuid']."/userdeliverylocation";
    	$user_controller = new UserController($mt, $user, $request,5);
    	//$response = $user_controller->setDeliveryAddr();
    	$response = $user_controller->processV2Request();
    	$this->assertNotNull($response->user_addr_id,'should have found a user_address id');
    	
    	$user_controller2 = new UserController($mt, $user, $request,5);
    	$response2 = $user_controller2->processV2Request();
    	$this->assertEquals($response->user_addr_id, $response2->user_addr_id);
    }
    
    function testBadPhoneNumberOnDeliverySave()
    {
    	$user_resource = createNewUser();
    	$user = logTestUserResourceIn($user_resource);
    	$user_id = $user_resource->user_id;
    	$user_uuid = $user_resource->uuid;
    	$json = '{"user_id":"'.$user_id.'","name":"","address1":"332 east 116th street","address2":"","city":"new york","state":"ny","zip":"10029","phone_no":"123456890","lat":40.796202,"lng":-73.936635}';
    	$request = new Request();
    	$request->body = $json;
    	$request->mimetype = "Application/json";
    	$request->_parseRequestBody(); 
    	$request->method = 'POST';
    	$request->url = "/users/$user_uuid/userdeliverylocation";
    	$user_controller = new UserController($mt, $user, $request,5);
    	$response = $user_controller->processV2Request();
    	$this->assertNotNull($response->error);
    	$this->assertEquals("The phone number you entered is not valid", $response->error);
    }


    function testFailoverToGeoFarm()
    {
    	$google_geocoder_service = new GoogleGeoCoderService();
  		$google_reply = $google_geocoder_service->geoCodeAddress("530+W+Main+St,Anoka+MN+55303");
    	$this->assertNull($google_reply);
    	$return_data = LatLong::generateLatLong("530+W+Main+St,Anoka+MN+55303");
    	$this->assertNotNull($return_data['lat']);
    	$this->assertEquals(45.204389, $return_data['lat']);
    	$this->assertNotNull($return_data['lng']);
    	$this->assertEquals(-93.400146, $return_data['lng']);
    }
        
    function createUserDeliveryLocationRecord($locs)
    {
    	$udla = new UserDeliveryLocationAdapter($mimetypes);
    	$data['user_id'] = $this->user['user_id'];
    	$code = generateAlphaCode(2);
    	$data['address1'] = "address1-$code";
    	$data['city'] = "city";
    	$data['state'] = "CO";
    	$data['zip'] = "12345";
    	$data['lat'] = $locs[0];
    	$data['lng'] = $locs[1];
    	$udl_resource = Resource::factory($udla,$data);
    	$udl_resource->save();
    	$udl_id = $udl_resource->user_addr_id;
    	return $udl_id;
    }
    
    function testPriceDistance()
    {
    	// create user delivery location record
    	$udla = new UserDeliveryLocationAdapter($mimetypes);
    	
    	$locs[0] = array(40.014594,-105.275990);
    	$locs[1] = array(39.867014,-104.934993);
    	$locs[2] = array(40.400445,-104.695179);
		$locs[3] = array(47.673989,-116.786191);
    	
    	$merchant_id = $this->merchant_id;
    	$mdi_adapter = new MerchantDeliveryInfoAdapter($mimetypes);
    	
    	$udl_id = $this->createUserDeliveryLocationRecord($locs[0]);
    	$results = $mdi_adapter->getDeliveryPriceFromIds($udl_id, $merchant_id);
    	$this->assertEquals(1.00, $results);
    	
    	$udl_id = $this->createUserDeliveryLocationRecord($locs[1]);
    	$results2 = $mdi_adapter->getDeliveryPriceFromIds($udl_id, $merchant_id);
    	$this->assertEquals(4.00, $results2);
    	$mdi_adapter->_query($del_sql);
    	
		$udl_id = $this->createUserDeliveryLocationRecord($locs[2]);
    	$results3 = $mdi_adapter->getDeliveryPriceFromIds($udl_id, $merchant_id);
    	$this->assertEquals(6.00, $results3); 
		$mdi_adapter->_query($del_sql);
    	
    	// out of delivery range
    	$udl_id = $this->createUserDeliveryLocationRecord($locs[3]);
    	$results4 = $mdi_adapter->getDeliveryPriceFromIds($udl_id, $merchant_id);
    	$this->assertFalse($results4);
    	
    	// error thrown, could not determine
    	try {
    		$results5 = $mdi_adapter->getDeliveryPriceFromIds(123456, $merchant_id);
    		$this->assertTrue(false,"We should have thrown an error");
    	} catch (Exception $e) {
    		$message = $e->getMessage();
    		$this->assertEquals("We're sorry, but we can't find this delivery address record, please create a new one", $message);	
    	}
    }

    function createUserDeliveryLocationRecordByZip($zip)
    {
    	$udla = new UserDeliveryLocationAdapter($mimetypes);
    	$data['user_id'] = $this->user['user_id'];
    	$code = generateAlphaCode(2);
    	$data['address1'] = "address1-$code";
    	$data['city'] = "city";
    	$data['state'] = "CO";
    	$data['zip'] = "$zip";
    	$data['lat'] = 0.00;
    	$data['lng'] = 0.00;
    	$udl_resource = Resource::factory($udla,$data);
    	$udl_resource->save();
    	$udl_id = $udl_resource->user_addr_id;
    	return $udl_id;
    }
    
    function testPriceZipCodes($udl_id)
    {
		$merchant_id = $this->merchant_id;
		
    	//  NOW DO THE ZIP CODE TEST
    	//$this->mdi_resource->zip_codes = 'true';
    	$this->mdi_resource->delivery_price_type = 'zip';
		$this->mdi_resource->save();

    	$mdi_adapter = new MerchantDeliveryInfoAdapter($mimetypes);
    	
    	$udl_id = $this->createUserDeliveryLocationRecordByZip('10029');
    	$results = $mdi_adapter->getDeliveryPriceFromIds($udl_id, $merchant_id);
    	$this->assertEquals(1.00, $results);
    	
    	$udl_id = $this->createUserDeliveryLocationRecordByZip('83814');
       	$results = $mdi_adapter->getDeliveryPriceFromIds($udl_id, $merchant_id);
       	$this->assertEquals(1.00, $results);
    	
    	$udl_id = $this->createUserDeliveryLocationRecordByZip('60647');
    	$results2 = $mdi_adapter->getDeliveryPriceFromIds($udl_id, $merchant_id);
    	$this->assertEquals(4.00, $results2);
    	
		$udl_id = $this->createUserDeliveryLocationRecordByZip('99201');
    	$results3 = $mdi_adapter->getDeliveryPriceFromIds($udl_id, $merchant_id);
    	$this->assertEquals(6.00, $results3); 
    	
    	// out of delivery range
		$udl_id = $this->createUserDeliveryLocationRecordByZip('80631');
    	$results4 = $mdi_adapter->getDeliveryPriceFromIds($udl_id, $merchant_id);
    	$this->assertFalse($results4);
    	
    	// error thrown, could not determine
    	try {
    		$results5 = $mdi_adapter->getDeliveryPriceFromIds(123456, $merchant_id);
    		$this->assertTrue(false,"We should have thrown an error");
    	} catch (Exception $e) {
    		$message = $e->getMessage();
    		$this->assertEquals("We're sorry, but we can't find this delivery address record, please create a new one", $message);	
    	}
    }   
    
    // now test ordering
    function testPlaceDeliveryOrderWithZip()
    {
    	//$udl_adapter = new UserDeliveryLocationAdapter($mimetypes);
    	//$sql = "DELETE FROM User_Delivery_Location Where user_id = 20000";
    	//$udl_adapter->_query($sql);
    	$user = logTestUserIn($this->user['user_id']);
    	$json = '{"user_id":"'.$this->user['user_id'].'","name":"","address1":"332 east 116th street","address2":"","city":"new york","state":"ny","zip":"10029","phone_no":"1234567890","lat":40.796202,"lng":-73.936635}';
    	$request = new Request();
    	$request->body = $json;
    	$request->mimetype = "Application/json";
    	$request->_parseRequestBody(); 
    	$request->method = 'POST';
    	$request->url = "/users/".$this->user['uuid']."/userdeliverylocation";
    	$user_controller = new UserController($mt, $this->user, $request,5);
    	//$response = $user_controller->setDeliveryAddr();
    	$response = $user_controller->processV2Request();
    	$this->assertNull($response->error,"should not have gotten a delivery save error but did");
    	$user_address_id = $response->user_addr_id;

    	$this->mdi_resource->minimum_delivery_time = 45;
    	$this->mdi_resource->delivery_price_type = 'driving';
    	$this->mdi_resource->save();
    	$merchant_id = $this->merchant_id;
        $merchant_resource = Resource::find(new MerchantAdapter($m),"$merchant_id");
        $merchant_resource->immediate_message_delivery = 'N';
        $merchant_resource->save();
    	$order_adapter = new OrderAdapter($mimetypes);

        $time = getTomorrowTwelveNoonTimeStampDenver();
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', 'skip hours');
    	$order_data['lead_time'] = '120';
    	$order_data['delivery'] = 'yes';
    	$order_data['user_addr_id'] = $user_address_id;
    	$order_data['delivery_time'] = "".date('Y-m-d H:i:s',$time + (120*60));
        $order_data['tip'] = 0.00;
		$order_resource = placeOrderFromOrderData($order_data, $time);
    	$this->assertNotNull($order_resource->error,"should have found a delivery distance error but it went through");
    	$this->assertEquals("We're sorry, this delivery address appears to be outside of our delivery range.", $order_resource->error);
    	
    	// now set 1080 to be zip code
		$this->mdi_resource->zip_codes = 'true';
		$this->mdi_resource->save();
    	
		$order_resource = placeOrderFromOrderData($order_data, $time);
    	$this->assertNull($order_resource->error);
    	$this->assertTrue($order_resource->order_id > 1000);

        // check to see if delivery message was scheduled for now
        $mmha = new MerchantMessageHistoryAdapter($m);
        $order_messages = $mmha->getAllOrderMessages($order_resource->order_id);
        $order_messages_hash = createHashmapFromArrayOfResourcesByFieldName($order_messages,'message_type');
        $x_message_resource = $order_messages_hash['X'];
        $now_plus_two_seconds = $time + 2;
        myerror_log("next message dt tm: ".date("Y-m-d H:i:s",$x_message_resource->next_message_dt_tm));
        myerror_log("now +2            : ".date("Y-m-d H:i:s",$now_plus_two_seconds));
        $this->assertTrue($x_message_resource->next_message_dt_tm < $now_plus_two_seconds,"order messages should have been scheduled for now");

    	return $order_resource->order_id;
    }
    
    /**
     * @depends testPlaceDeliveryOrderWithZip
     */
    
    function testCompleteOrderHasDeliveryInfo($order_id)
    {
    	$complete_order = CompleteOrder::getCompleteOrderAsResource($order_id, $mimetypes);
    	$this->assertNotNull($complete_order->delivery_info);
    	$delivery_info = $complete_order->delivery_info;
    	$this->assertNotNull($delivery_info->address1);
    	$this->assertNotNull($delivery_info->city);
    	$this->assertNotNull($delivery_info->phone_no);
    	
    	//check formats
    	$complete_order->_representation = "/order_templates/universal/universal_text_based_header.txt";
    	$text = getResourceBody($complete_order);
    	myerror_log($text);
    }
  
    function testDeliveryMinimumOnMerchantDeliveryPriceDistanceRecord()
    {
    	$merchant_resource = createNewTestMerchant();
    	$merchant_resource->delivery = 'Y';
    	$merchant_resource->save();
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$data = array("merchant_id"=>$merchant_resource->merchant_id);
    	
    	// set merchant delivery info
    	$mdia = new MerchantDeliveryInfoAdapter($mimetypes);
    	$mdia_resource = $mdia->getExactResourceFromData($data);	
    	$mdia_resource->minimum_order = 0.01;
    	$mdia_resource->delivery_price_type = 'Zip';
    	$mdia_resource->delivery_cost = 1.00;
    	$mdia_resource->delivery_increment = 15;
    	$mdia_resource->max_days_out = 3;
    	$mdia_resource->save();
    	
    	//map it to a menu
    	MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'pickup');
    	MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'delivery');
    	
    	// now create the distance price records.
    	$mdpd = new MerchantDeliveryPriceDistanceAdapter($mimetypes);
    	$mdpd_resource = $mdpd->getExactResourceFromData($data);
    	$mdpd_resource->zip_codes = "10029";
    	$mdpd_resource->price = 2.00;
    	$mdpd_resource->minimum_order_amount = 5.00;
    	$mdpd_resource->save();
    	
    	$mdpd_resource->_exists = false;
    	unset($mdpd_resource->map_id);
    	$mdpd_resource->zip_codes = "60647";
    	$mdpd_resource->distance_up_to = 2;
    	$mdpd_resource->price = 4.00;
    	$mdpd_resource->minimum_order_amount = 10.00;
    	$mdpd_resource->save();
    	
    	$mdpd_resource->_exists = false;
    	unset($mdpd_resource->map_id);
    	$mdpd_resource->zip_codes = "99201,45675,76546";
    	$mdpd_resource->distance_up_to = 4;
    	$mdpd_resource->price = 6.00;
    	$mdpd_resource->minimum_order_amount = 15.00;
    	$mdpd_resource->save();
	
    	// create the user record
    	$json = '{"user_id":"'.$this->user['user_id'].'","name":"","address1":"180 Meserole Ave","address2":"","city":"Brooklyn","state":"ny","zip":"76546","phone_no":"1234567890","lat":40.796202,"lng":-73.936635}';
    	$request = new Request();
    	$request->body = $json;
    	$request->mimetype = "Application/json";
    	$request->_parseRequestBody(); 
    	$request->method = 'POST';
    	$request->url = "/users/".$this->user['uuid']."/userdeliverylocation";
    	$user_controller = new UserController($mt, $this->user, $request,5);
    	//$response = $user_controller->setDeliveryAddr();
    	$response = $user_controller->processV2Request();
    	$this->assertNull($response->error,"should not have gotten a delivery save error but did");
    	$user_address_id = $response->user_addr_id;
    	$user_delivery_location_resource = SplickitController::getResourceFromId($user_address_id, 'UserDeliveryLocation');
    	
    	// url = isindeliveryarea	
		$request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id?log_level=5",'GET',$b,$m);
    	$merchant_controller = new MerchantController($mt, $user, $request, 5);
    	$resource = $merchant_controller->processV2Request();
    	
    	$this->assertTrue(isset($resource->is_in_delivery_range),"should have found the 'is in delivery range' field");
    	$this->assertTrue($resource->is_in_delivery_range," the is in delivery range should be trud");
    	$this->assertEquals("Please Note: This merchant has a minimum order amount of $15.00 for this delivery area.",$resource->user_message);
   	
//*
    	$delivery_controller = new DeliveryController($mt, $u, $r,5);
    	$delivery_info = $delivery_controller->getRelevantDeliveryInfoFromIds($user_delivery_location_resource->user_addr_id, $merchant_id);
    	$this->assertTrue($delivery_info['is_in_delivery_range']);
    	$this->assertEquals("Please Note: This merchant has a minimum order amount of $15.00 for this delivery area.",$delivery_info['user_message']);
//*/
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', 'skip hours',5);
    	$order_data['lead_time'] = '0';
    	$order_data['delivery'] = 'yes';
    	$order_data['user_addr_id'] = $user_address_id;
    	$order_data['delivery_time'] = "As soon as possible";

        $json_encoded_data = json_encode($order_data);
        $url = '/app2/apiv2/cart';
        $request = createRequestObject($url,'post',$json_encoded_data,'application/json');
        $place_order_controller = new PlaceOrderController($mt, $this->user, $request);
        $cart_resource = $place_order_controller->processV2Request();
        $ucid = $cart_resource->ucid;


        $url = "/app2/apiv2/cart/$ucid/checkout";
        $request = createRequestObject($url,'get');
        $place_order_controller = new PlaceOrderController($mt, $this->user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();


        $this->assertNotNull($checkout_resource->error,"should have found a delivery minimum but the order went through");
    	$this->assertEquals("Minimum order required! You have not met the minimum subtotal of $15.00 for your deliver area.", $checkout_resource->error);
    	
    	$user_delivery_location_resource->zip = "10029";
    	$user_delivery_location_resource->save();
    	
    	$order_resource = placeOrderFromOrderData($order_data, $time);
    	$this->assertNull($order_resource->error,"Order should now have gone through because order amount is 7.50");

    }

    function testPlaceDeliveryOrderWithAsSoonAsPossible()
    {
    	$mdi_adapter = new MerchantDeliveryInfoAdapter($this->mimetypes);
		$mdi_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $this->merchant_id;
		$mdi_resource = Resource::findExact($mdi_adapter,'',$mdi_options);
		$mdi_resource->minimum_delivery_time = 45;
		$mdi_resource->zip_codes = 'true';
		$mdi_resource->save();
    	
		$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->merchant_id, 'delivery', 'get it here fast please');
    	$order_data['lead_time'] = '0';
    	$order_data['delivery'] = 'yes';
    	
    	// get user delivery address
    	$udl_adapter = new UserDeliveryLocationAdapter($mimetypes);
    	$udl_options[TONIC_FIND_BY_METADATA]["user_id"] = $this->user['user_id'];
    	$udl_resource = Resource::find($udl_adapter,'',$udl_options);
    	$udl_resource->zip = 10029;
    	$udl_resource->save();
    	
    	$order_data['user_addr_id'] = $udl_resource->user_addr_id;
    	$order_data['delivery_time'] = "As soon as possible";
 		$order_data['actual_pickup_time'] = "As soon as possible";
    	
 		$time_stamp = getTodayTwelveNoonTimeStampDenver();
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNull($order_resource->error);
    	$order_id = $order_resource->order_id;
    	
    	$expected_pickup_dt_tm_string = date('Y-m-d H:i:s',$time_stamp+(45*60));
    	$actual_pickup_dt_tm_string = $order_resource->pickup_dt_tm;
    	$this->assertEquals($expected_pickup_dt_tm_string, $actual_pickup_dt_tm_string);
    }
   
    function testPlaceDeliveryOrderWithUseOfActualPickupTimeWhichIsPoorlyNamedInThisCaseDontYouThinkSoDave()
    {
    	$mdi_adapter = new MerchantDeliveryInfoAdapter($this->mimetypes);
		$mdi_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $this->merchant_id;
		$mdi_resource = Resource::findExact($mdi_adapter,'',$mdi_options);
		$mdi_resource->minimum_delivery_time = 45;
		$mdi_resource->zip_codes = 'true';
		$mdi_resource->save();
    	
    	$time_stamp = getTomorrowTwelveNoonTimeStampDenver();
    	$delivery_time_stamp = $time_stamp+(90*60);
    	$delivery_time_string = date('Y-m-d H:i:s',$delivery_time_stamp);
    	
    	// get user delivery address
    	$udl_adapter = new UserDeliveryLocationAdapter($mimetypes);
    	$udl_options[TONIC_FIND_BY_METADATA]["user_id"] = $this->user['user_id'];
    	$udl_resource = Resource::find($udl_adapter,'',$udl_options);
    	
		$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->merchant_id, 'delivery', 'get it here fast please');
    	
    	// seeing if it will ignore the lead time now that we use actual time stamp
    	$order_data['lead_time'] = '120';
    	
    	$order_data['delivery'] = 'yes';
    	$order_data['user_addr_id'] = $udl_resource->user_addr_id;
    	$order_data['delivery_time'] = "$delivery_time_string";
 		$order_data['actual_pickup_time'] = $delivery_time_stamp;
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNull($order_resource->error);
    	$order_id = $order_resource->order_id;
    	
    	//$actual_pickup_dt_tm_string = date('Y-m-d H:i:s',$order_resource->pickup_dt_tm);
    	$this->assertEquals($delivery_time_string, $order_resource->pickup_dt_tm);
    	
    }
    
    function testMakeSureAsSoonAsPossibleDoentShowIfMerchantIsAboutToClose()
    {
    	$tz = date_default_timezone_get();
    	date_default_timezone_set("America/Denver");
    	//$_SERVER['STAMP'] = 'PLACEORDERTEST-'.$_SERVER['STAMP'];
    	// first set hours to closing in 20 min for 1080
        	
    	$merchant_id = $this->merchant_id;
    	$hour_adapter = new HourAdapter($mimetypes);
    	$hour_data['merchant_id'] = $merchant_id;
    	$hour_data['hour_type'] = 'D';
    	$hour_data['day_of_week'] = date('w')+1;
    	$hour_options[TONIC_FIND_BY_METADATA] = $hour_data;
    	$hour_resource = Resource::findOrCreateIfNotExists($hour_adapter,'',$hour_options);
    	$hour_resource->open = '00:01:00';
    	
    	// get local time + 30 minutes
    	$time_plus_30 = time()+1800;
    	$time_string = date("H:i:s",$time_plus_30);
    	$hour_resource->close = "$time_string";
    	$hour_resource->save();
    	
    	// set minimum delivery lead to be 90 min
    	$mdi_adapter = new MerchantDeliveryInfoAdapter($this->mimetypes);
		$mdi_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
		$mdi_resource = Resource::findExact($mdi_adapter,'',$mdi_options);
		$mdi_resource->minimum_delivery_time = 45;
        $mdi_resource->allow_asap_on_delivery = 'Y';
		$mdi_resource->zip_codes = 'true';
		$mdi_resource->save();

        $user = logTestUserIn($this->ids['user_id']);
    	$order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', 'some note');

    	// get user delivery address
    	$udl_adapter = new UserDeliveryLocationAdapter($mimetypes);
    	$udl_options[TONIC_FIND_BY_METADATA]["user_id"] = $this->user['user_id'];
    	$udl_resource = Resource::find($udl_adapter,'',$udl_options);
    	$udl_resource->zip = 10029;
    	$udl_resource->save();

    	$order_data['user_addr_id'] = $udl_resource->user_addr_id;
    	//$order_data['delivery_time'] = "As soon as possible";

        $request = createRequestObject("/app2/apiv2/cart/checkout",'POST',json_encode($order_data),'application/json');
        $place_order_controller = new PlaceOrderController($m,$user,$request);
        $checkout_resource = $place_order_controller->processV2Request();
        $lead_times = $checkout_resource->lead_times_array;

        $this->assertNotNull($lead_times,"lead times array should not be null.");
        $this->assertNotEquals($lead_times[0], "As soon as possible", "the asap value is not first time of lead times");
    	
    	// reset close to 2 hours from now and try again
    	$time_string2 = date("H:i:s",time()+7200);
    	$hour_resource->close = "$time_string2";
    	$hour_resource->save();

        $place_order_controller = new PlaceOrderController($m,$user,$request);
        $checkout_resource2 = $place_order_controller->processV2Request();
   		$this->assertNull($checkout_resource2->error);
        $this->assertEquals($checkout_resource2->lead_times_array[0], "As soon as possible", "the asap value is first time of lead times");

   		$hour_resource->close = "23:00:00";
    	$hour_resource->save();
    	date_default_timezone_set($tz);
    	return $order_data['user_addr_id'];
    }
    
    /** 
     * @depends testMakeSureAsSoonAsPossibleDoentShowIfMerchantIsAboutToClose
     */
    function testPlaceDeliveryOrderWithAsSoonAsPossibleButMerchantLeadIs24Hours($user_addr_id)
    {
    	//set merchant delivery info to be 1440 minutes
    	$merchant_id = $this->merchant_id;
    	$mdi_adapter = new MerchantDeliveryInfoAdapter($this->mimetypes);
		$mdi_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
		$mdi_resource = Resource::findExact($mdi_adapter,'',$mdi_options);
		$mdi_resource->minimum_delivery_time = 1440;
		$mdi_resource->save();


        $user = logTestUserIn($this->ids['user_id']);
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', 'get it here fast please');
        $order_data['delivery'] = 'yes';
        $order_data['user_addr_id'] = $user_addr_id;

        $request = createRequestObject("/app2/apiv2/cart/checkout",'POST',json_encode($order_data),'application/json');
        $place_order_controller = new PlaceOrderController($m,$user,$request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);

        $this->assertNotEquals($checkout_resource->lead_times_array[0], "As soon as possible", "the asap value is first time of lead times");

 		$mdi_resource->minimum_delivery_time = 45;
		$mdi_resource->save();
    }

    /*
     * create any data you need and return the id's as a hash
     */
    static function setUpBeforeClass()
    {
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	$_SERVER['request_time1'] = microtime(true);    	
		$merchant_resource = createNewTestMerchant();
    	$merchant_resource->delivery = 'Y';
    	$merchant_resource->save();
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
    	$data = array("merchant_id"=>$merchant_resource->merchant_id);
    	
    	// set merchant delivery info
    	$mdia = new MerchantDeliveryInfoAdapter($mimetypes);
    	$mdia_resource = $mdia->getExactResourceFromData($data);	
    	$mdia_resource->minimum_order = 0.01;
    	$mdia_resource->delivery_cost = 1.00;
    	$mdia_resource->delivery_increment = 15;
    	$mdia_resource->max_days_out = 3;
    	$mdia_resource->save();
    	
    	//map it to a menu
    	$menu_id = createTestMenuWithOneItem("Delivery Test Item 1");
    	$ids['menu_id'] = $menu_id;
    	MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $menu_id, 'pickup');
    	MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $menu_id, 'delivery');
    	
    	// now create the distance price records.
    	$mdpd = new MerchantDeliveryPriceDistanceAdapter($mimetypes);
    	$mdpd_resource = $mdpd->getExactResourceFromData($data);
    	$mdpd_resource->zip_codes = "10029,83814,09877,23444";
    	$mdpd_resource->price = 1.00;
    	$mdpd_resource->save();
    	
    	$mdpd_resource->_exists = false;
    	unset($mdpd_resource->map_id);
    	$mdpd_resource->distance_up_to = 30.00;
    	$mdpd_resource->zip_codes = "60647";
    	$mdpd_resource->price = 4.00;
    	$mdpd_resource->save();
    	
    	$mdpd_resource->_exists = false;
    	unset($mdpd_resource->map_id);
    	$mdpd_resource->distance_up_to = 55.00;
    	$mdpd_resource->zip_codes = "99201,45675,76546";
    	$mdpd_resource->price = 6.00;
    	$mdpd_resource->save();

    	$user_resource = createNewUser(array('flags'=>'1C20000001'));
    	$ids['user_id'] = $user_resource->user_id;


        $prep_resource = Resource::createByData(new MerchantPreptimeInfoAdapter(getM()), array("merchant_id"=>$merchant_resource->merchant_id,"entree_preptime_seconds"=>120,"delivery_throttling_on"=>'N'));
    	
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
    DeliveryTest::main();
}
?>