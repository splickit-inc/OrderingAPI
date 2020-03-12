<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class BrinkControllerTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        setContext("com.splickit.snarfs");

    }

    function tearDown()
    {
        //delete your instance
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
        $_SERVER['BRINK_TIMEOUT'] = false;
    }

    function testBrinkCurlTimeoutOnCheckout()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        Resource::createByData(new MerchantBrinkInfoMapsAdapter(getM()),array("merchant_id"=>$merchant_id,"brink_location_token"=>getProperty('brink_test_location_token')));
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_id,'B','brink','X');
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $_SERVER['BRINK_TIMEOUT'] = true;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertEquals(PlaceOrderController::REMOTE_SYSTEM_CHECKOUT_INFO_ERROR_MESSAGE,$checkout_resource->error);
        $this->assertEquals(500,$checkout_resource->http_code);
    }

    function testBrinkCurlTimeoutOnPlaceOrder()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        $mbimr = Resource::createByData(new MerchantBrinkInfoMapsAdapter(getM()),array("merchant_id"=>$merchant_id,"brink_location_token"=>getProperty('brink_test_location_token')));
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_id,'B','brink','X');
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertNull($checkout_resource->error);

        $_SERVER['BRINK_TIMEOUT'] = true;
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,time());
        $this->assertEquals(PlaceOrderController::REMOTE_SYSTEM_CHECKOUT_INFO_ERROR_MESSAGE,$order_resource->error);
        $this->assertEquals(500,$order_resource->http_code);
        $_SERVER['BRINK_TIMEOUT'] = false;

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,time());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
    }

    function testBrinkCurlTimeoutOnMessageSend()
    {
        setContext("com.splickit.smilingmoose");
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        Resource::createByData(new MerchantBrinkInfoMapsAdapter(getM()),array("merchant_id"=>$merchant_id,"brink_location_token"=>getProperty('brink_test_location_token')));
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_id,'B','brink','X');
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertNull($checkout_resource->error);


        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,time());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $complete_order = CompleteOrder::getBaseOrderData($order_id);

        $_SERVER['BRINK_TIMEOUT'] = true;
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'B');
        $locked_message_resource = LockedMessageRetriever::getLockedMessageResourceForSending($message_resource);
        $brink_controller = ControllerFactory::generateFromMessageResource($locked_message_resource,$m,$u,$r,5);
        $send_time = time();
        $this->assertTrue($brink_controller->sendThisMessage($locked_message_resource));

        $original_send_on_time = date("Y-m-d H:i:s",$message_resource->next_message_dt_tm);

        // now get new message resource
        $message_resource2 = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'B');
        $new_send_on_time = date("Y-m-d H:i:s",$message_resource2->next_message_dt_tm);
        $this->assertEquals("1",$message_resource2->tries);
        $this->assertEquals('N',$message_resource2->locked);
        $diff = $message_resource2->next_message_dt_tm - $send_time;
        $this->assertTrue(125 > $diff && $diff > 119,"It should be 2 minutes in the future");

    }

    function testGetBrandUrlMoose()
    {
        $brink_service = new BrinkService('23456789');
        $brink_service->setBrandId(152);
        $url = $brink_service->getBrandUrl(getProperty("brink_api_url"));
        $this->assertContains('api.brinkpos.net',$url,"should have the pitapit url");
    }

    function testGetBrandUrlPitaPit()
    {
        $brink_service = new BrinkService('23456789');
        $brink_service->setBrandId(282);
        $url = $brink_service->getBrandUrl(getProperty("brink_api_url"));
        $this->assertContains('api4.brinkpos.net',$url,"should have the pitapit url");
    }

    function testSnarfsNewUrl()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_resource->trans_fee_rate = .25;
        $merchant_resource->trans_fee_type = 'F';
        $merchant_resource->save();
        $tax_resource = Resource::find(new TaxAdapter(getM()),null,[3=>['merchant_id'=>$merchant_resource->merchant_id]]);
        $tax_resource->rate = 1;
        $tax_resource->save();
        Resource::createByData(new MerchantBrinkInfoMapsAdapter($m),array("merchant_id"=>$merchant_resource->merchant_id,"brink_location_token"=>getProperty('brink_test_location_token')));
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_resource->merchant_id,'B','brink','X');
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_resource->merchant_id,'HUC','ChinaIP','O');
        $merchant_id = $merchant_resource->merchant_id;
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertEquals(2.45,$checkout_resource->grand_total);


        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,1.50,time());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $complete_order = CompleteOrder::getBaseOrderData($order_id);
        $expected_grand_total = $complete_order['order_amt']+$complete_order['total_tax_amt']+$complete_order['trans_fee_amt']+$complete_order['tip_amt'];
        $this->assertEquals($expected_grand_total,$complete_order['grand_total'],'It should have the expected grand total');
        $expected_grand_total_to_merchant = $complete_order['order_amt']+$complete_order['total_tax_amt'];
        $this->assertEquals($expected_grand_total_to_merchant,$complete_order['grand_total_to_merchant'],'It should have the expected grand total to merchant');
        $this->assertEquals(3.95,$order_resource->grand_total);

        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'B');

        $brink_controller = ControllerFactory::generateFromMessageResource($message_resource,$m,$u,$r,5);
        $brink_controller->sendThisMessage($message_resource);
        $brink_service = $brink_controller->brink_service;
        $this->assertContains('api8.brinkpos.net',$brink_service->getUrl());
        $message_record = MerchantMessageHistoryAdapter::staticGetRecordByPrimaryKey($message_resource->map_id,'MerchantMessageHistoryAdapter');
        $message_text = $message_record['message_text'];
        $this->assertContains('SubmitOrder',$message_text);
        $this->assertEquals('SubmitOrder',$brink_service->method);

        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'HUC');
        $controller = ControllerFactory::generateFromMessageResource($message_resource);
        $message = $controller->prepMessageForSending($message_resource);
        $message_text = $message->message_text;
        $printed_grand_total = $expected_grand_total - $complete_order['trans_fee_amt'];
        $this->assertContains('Grand Total: $'.$printed_grand_total,$message_text);


    }

    function testMooseNotes()
    {
        $skin = getOrCreateSkinAndBrandIfNecessary("Smiling Moose","Smiling Moose",6,152);
        setContext("com.splickit.smilingmoose");
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        Resource::createByData(new MerchantMessageMapAdapter(getM()),array("merchant_id"=>$merchant_id,"message_format"=>'B',"delivery_addr"=>"Brink","message_type"=>"X"));
        Resource::createByData(new MerchantBrinkInfoMapsAdapter(getM()),array("merchant_id"=>$merchant_id,"brink_location_token"=>'sumdumtoken'));


        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $note = "sasquatch lives";
        $cart_data['note'] = $note;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,000,$time);
        $this->assertNull($order_resource->error);

        $brink_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_resource->order_id,'B');
        $this->assertNotNull($brink_message_resource,"we shojdl have a message resource");
        $brink_controller = new BrinkController($m, $u, $r, 5);
        $brink_controller->sendThisMessage($brink_message_resource);
        $ready_to_send_message_resource = $brink_controller->prepMessageForSending($brink_message_resource);
        myerror_log($ready_to_send_message_resource->message_text);
        $body = cleanUpDoubleSpacesCRLFTFromString($ready_to_send_message_resource->message_text);
        //check to see that promo is in the XML
        $expected_note_node = cleanUpDoubleSpacesCRLFTFromString('<Items>
                                    <NewOrderItem>
                                        <Description>Order Instructions</Description>
                                        <Id>1</Id>
                                        <ItemId>640212020</ItemId>
                                        <Modifiers i:nil="true" />
                                        <Price>0.00</Price>
                                        <DestinationId>640212044</DestinationId>
                                        <Note>'.$note.'</Note>
                                    </NewOrderItem>');
        $this->assertContains($expected_note_node,$body,"It should have the  note node");

        $brink_controller = new BrinkController(getM(),null, $r,5);
        $brink_controller->sendThisMessage($brink_message_resource);
        $brink_service = $brink_controller->brink_service;
        $this->assertContains('api.brinkpos.net',$brink_service->getUrl());

    }

    function testPromo()
    {
//        $skin = getOrCreateSkinAndBrandIfNecessary("Pitapit","Pitapit",13,282);
        setContext("com.splickit.smilingmoose");

        $merchant_id = $this->ids['merchant_id'];

        $user_resource = createNewUserWithCCNoCVV(array("email"=>"bobnewhart@dummy.com","first_name"=>"bob","last_name"=>"newhart","contact_no"=>'8888888888'));
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $request = createRequestObject('app2/apiv2/cart','post',json_encode($cart_data),'application/json');
        $place_order_controller = new PlaceOrderController(getM(),$user,$request,5);
        $create_cart_response = $place_order_controller->processV2Request();
        $this->assertNull($create_cart_response->error);
        $complete_order = CompleteOrder::staticGetCompleteOrder($create_cart_response->oid_test_only,getM());
        $splickit_sub_total = $complete_order['order_details'][0]['item_total_w_mods'];
        $ucid = $create_cart_response->ucid;
        $request = createRequestObject("app2/apiv2/cart/$ucid/checkout?promo_code=type1promo","GET");
        $place_order_controller = new PlaceOrderController(getM(),$user,$request,5);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error,"It should not throw an error");
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$time);
        $this->assertNull($order_resource->error,"It should not throw an error on place order with promo");
        $this->assertEquals(-$splickit_sub_total/2,$order_resource->promo_amt,"It should have the promo amount");

        $brink_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_resource->order_id,'B');
        $this->assertNotNull($brink_message_resource,"we shojdl have a message resource");
        $brink_controller = new BrinkController($m, $u, $r, 5);
        $ready_to_send_message_resource = $brink_controller->prepMessageForSending($brink_message_resource);
        myerror_log($ready_to_send_message_resource->message_text);
        $body = cleanUpDoubleSpacesCRLFTFromString($ready_to_send_message_resource->message_text);
        //check to see that promo is in the XML
        $expected_promo_node = cleanUpDoubleSpacesCRLFTFromString('<Discounts>
						<NewOrderDiscount>
							<Amount>'.number_format($splickit_sub_total/2,2).'</Amount>
							<DiscountId>640212223</DiscountId>
							<Id>1</Id>
							<LoyaltyRewardId>0</LoyaltyRewardId>
							<Name i:nil="true"/>
							<OrderItemIds i:nil="true" xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays"/>
							<Percent>0</Percent>
						</NewOrderDiscount>
					</Discounts>');
        $this->assertContains($expected_promo_node,$body,"It should have the promo node");
        $this->assertEquals($splickit_sub_total,$order_resource->order_amt,"When promo is used, we need OUR subtotal");

        //make sure totals are correct.
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_resource->order_id,$m);
        $grand_total_from_math = $complete_order['order_amt']+$complete_order['total_tax_amt']+$complete_order['delivery_amt']+$complete_order['tip_amt']+$complete_order['promo_amt'];
        $this->assertEquals($grand_total_from_math,$complete_order['grand_total'],"the set grand total should equal the sum of the parts");

    }

    function testGoodResendAfterFail()
    {
        $user = logTestUserIn($this->ids['user_id']);
        $merchant_id = $this->ids['merchant_id'];
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'sum dum note');
        $order_data['tip'] = 0.00;
        $order_resource = placeOrderFromOrderData($order_data, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'B');

        $brink_controller = ControllerFactory::generateFromMessageResource($message_resource,$m,$u,$r,5);
        $message_to_send_resource = $brink_controller->prepMessageForSending($message_resource);
        $this->assertNotNull($brink_controller->location_token,"it should have a location token");
        $message_resource->message_text = $message_to_send_resource->message_text;
        $message_resource->save();

        $brink_controller2 = ControllerFactory::generateFromMessageResource($message_resource,$m,$u,$r,5);
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'B');
        $response = $brink_controller2->sendThisMessage($message_resource);
        $this->assertTrue($response);
        $message_to_send_resource2 = $brink_controller2->prepMessageForSending($message_resource);
        $this->assertNotNull($brink_controller2->location_token,"it should have a location token");
        $this->assertEquals($merchant_id,$brink_controller2->getMerchantIdForCurrent(),"It should have set the merchant id even though there was a static method");
    }

    function testCreateBrinkMessageControllerFromFactory()
    {
        $message_resource = Resource::dummyfactory(array("message_format" => 'B'));
        $controller_name = ControllerFactory::getControllerNameFromMessageResource($message_resource);
        $this->assertEquals('Brink',$controller_name,"It should return Brink as the controller name");

        $controller = ControllerFactory::generateFromMessageResource($message_resource,$m,$u,$r,5);
        $this->assertEquals('BrinkController', get_class($controller), "It should return a Brink Controller");
    }

    function testCreateBrinkMessageControllerFromUrl()
    {
        $url = "/getnextmessagebymerchantid/brink/3456wert3456ert";
        $controller = ControllerFactory::generateFromUrl($url,$m,$u,$r,5);
        $this->assertEquals('BrinkController', get_class($controller), "It should return a Brink Controller");
    }

    function testDeliveryBrink()
    {
        $user = logTestUserIn($this->ids['user_id']);
        $json = '{"user_addr_id":null,"user_id":"'.$user['user_id'].'","name":"","address1":"1045 Pine Street","address2":"3B & 3C","city":"boulder","state":"co","zip":"80302","instructions":"come around back please","phone_no":"9709262121","lat":40.0197891,"lng":-105.284703}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "Application/json";
        $request->_parseRequestBody();
        $request->method = 'POST';
        $request->url = "/users/".$user['uuid']."/userdeliverylocation";
        $user_controller = new UserController($mt, $user, $request,5);
        //$response = $user_controller->setDeliveryAddr();
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error,"should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;


        $merchant_id = $this->ids['merchant_id'];
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'delivery', 'sum dum note');
        $order_data['items'][0]['note'] = 'item note';
        $order_data['user_addr_id'] = $user_address_id;
        $order_data['tip'] = 0.00;
        $order_resource = placeOrderFromOrderData($order_data, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;

        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id, 'B');

        $brink_controller = ControllerFactory::generateFromMessageResource($message_resource,$m,$u,$r,5);
        $message_to_send_resource = $brink_controller->prepMessageForSending($message_resource);
        $this->assertNotNull($message_to_send_resource,"Should have generated the message to send resource");
        myerror_log($message_to_send_resource->message_text);
        $body = cleanUpDoubleSpacesCRLFTFromString($message_to_send_resource->message_text);
        $expected_delivery_node = cleanUpDoubleSpacesCRLFTFromString("<Delivery>
                            <Address1>1045 Pine Street</Address1>
                            <Address2>3B and 3C, come around back please</Address2>
                            <City>boulder</City>
                            <Country>USA</Country>
                            <PostalCode>80302</PostalCode>
                            <StateCode>CO</StateCode>
                        </Delivery>");
        $this->assertContains($expected_delivery_node,$body,"should have the delivery info");

        $destination_string = "<DestinationId>640225739</DestinationId>";
        $this->assertContains($destination_string,$body,"it should have the zone 1 destination id");

/*        $expected_delivery_surcharge_node = cleanUpDoubleSpacesCRLFTFromString('<Surcharges>
                  <OrderSurcharge>
                     <Amount>8.18</Amount>
                     <Id>1</Id>
                     <IsSystemApplied>true</IsSystemApplied>
                     <Name>Delivery Fee 2</Name>
                     <SurchargeId>88888888</SurchargeId>
                     <Taxes />
                  </OrderSurcharge>
               </Surcharges>');
        $expected_delivery_surcharge_node = str_ireplace('> <','><',$expected_delivery_surcharge_node);
        $this->assertContains($expected_delivery_surcharge_node,$body,"It should contain the delivery surcharge node");
*/
    }

    function testGenerateTemplate()
    {
        $user = logTestUserIn($this->ids['user_id']);
        $merchant_id = $this->ids['merchant_id'];
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'pickup', '');
        $order_data['tip'] = 3.33;
        $order_data['note'] = '';
        $order_resource = placeOrderFromOrderData($order_data, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $messages = MerchantMessageHistoryAdapter::getAllOrderMessages($order_id);
        $messages_hash = createHashmapFromArrayOfResourcesByFieldName($messages, 'message_format');
        $this->assertNotNull($messages_hash['B'], "SHould have created a message with the Brink format");
        return $messages_hash['B'];
    }

    /**
     * @depends testGenerateTemplate
     */
    function testCreateTemplateFromMessageResourece($message_resource)
    {
        $this->assertTrue(true);
        $expected_payload = cleanUpDoubleSpacesCRLFTFromString(file_get_contents("./unit_tests/resources/expected_brink_message_body.txt"));
        $order_id = $message_resource->order_id;
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
        $this->assertEquals(3.33,$complete_order['tip_amt']);
        $user = $complete_order['user'];
        $expected_payload = str_replace("%%email%%",$user['email'],$expected_payload);
        $expected_payload = str_replace("%%item_external_id%%",$complete_order['order_details'][0]['external_id'],$expected_payload);
        $tz = date_default_timezone_get();
        date_default_timezone_set('GMT');
        $brink_pickup_time_string = date("Y-m-d\TH:i:00.0000\Z",$complete_order['pickup_dt_tm']);
        date_default_timezone_set($tz);
        $expected_payload = str_replace("%%brink_pickup_time%%",$brink_pickup_time_string,$expected_payload);




        $brink_controller = ControllerFactory::generateFromMessageResource($message_resource,$m,$u,$r,5);
        $message_to_send_resource = $brink_controller->prepMessageForSending($message_resource);
        $this->assertNotNull($message_to_send_resource,"Should have generated the message to send resource");
        $body = cleanUpDoubleSpacesCRLFTFromString($message_to_send_resource->message_text);

        $this->assertContains('<Amount>2.20</Amount>',$body);
        $this->assertContains('<TipAmount>3.33</TipAmount>',$body);


    }

    function testMulitpleItemsOnTemplate()
    {
        $user = logTestUserIn($this->ids['user_id']);
        $merchant_id = $this->ids['merchant_id'];
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'sum dum note');
        $order_data['items'][0]['quantity'] = 2;
        $order_data['tip'] = 0.00;
        $order_data['note'] = '';
        $order_resource = placeOrderFromOrderData($order_data, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id);
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'B');
        $brink_controller = ControllerFactory::generateFromMessageResource($message_resource,$m,$u,$r,5);
        $message_to_send_resource = $brink_controller->prepMessageForSending($message_resource);
        $this->assertNotNull($message_to_send_resource,"Should have generated the message to send resource");
        myerror_log($message_to_send_resource->message_text);
        $body = cleanUpDoubleSpacesCRLFTFromString($message_to_send_resource->message_text);

        $cm = new CompleteMenu($this->ids['menu_id']);
        $isrs = $cm->getAllMenuItemSizeMapResources($this->ids['menu_id'],'Y',0);
        $item_external_id = $isrs[0]->external_id;

        $this->assertContains('<Price>1.50</Price>',$body,"It should contain the single item price on each of the nodes");
        $this->assertNotContains('<Price>3.00</Price>',$body,"It should NOT contain the full price of the ordered item");


        $expected_item_payload = cleanUpDoubleSpacesCRLFTFromString('<Items>
                  <NewOrderItem>
                     <Description i:nil="true" />
                     <Id>1</Id>
                     <ItemId>'.$item_external_id.'</ItemId>
                     <Modifiers>
                        <NewOrderItemModifier>
                           <Description i:nil="true" />
                           <Id>2</Id>
                           <ItemId>5678</ItemId>
                           <Modifiers i:nil="true" />
                           <Price>0.500</Price>
                           <ModifierCodeId>0</ModifierCodeId>
                           <ModifierGroupId>1234</ModifierGroupId>
                        </NewOrderItemModifier>
                     </Modifiers>
                     <Price>1.50</Price>
                     <DestinationId>640225738</DestinationId>
                     <Note i:nil="true" />
                  </NewOrderItem>
                  <NewOrderItem>
                     <Description i:nil="true" />
                     <Id>3</Id>
                     <ItemId>'.$item_external_id.'</ItemId>
                     <Modifiers>
                        <NewOrderItemModifier>
                           <Description i:nil="true" />
                           <Id>4</Id>
                           <ItemId>5678</ItemId>
                           <Modifiers i:nil="true" />
                           <Price>0.500</Price>
                           <ModifierCodeId>0</ModifierCodeId>
                           <ModifierGroupId>1234</ModifierGroupId>
                        </NewOrderItemModifier>
                     </Modifiers>
                     <Price>1.50</Price>
                     <DestinationId>640225738</DestinationId>
                     <Note i:nil="true" />
                  </NewOrderItem>
               </Items>');
        $expected_item_payload = str_ireplace('> <','><',$expected_item_payload);
        $this->assertContains($expected_item_payload,$body,"It should have 2 item nodes since the item had a quantity of 2");

    }

    function testMulitpleModifiersOnTemplate()
    {
        $user = logTestUserIn($this->ids['user_id']);
        $merchant_id = $this->ids['merchant_id'];
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'sum dum note');
        $order_data['items'][0]['mods'][0]['mod_quantity'] = 3;
        $order_data['tip'] = 0.00;
        $order_data['note'] = '';
        $order_resource = placeOrderFromOrderData($order_data, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id);
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'B');
        $brink_controller = ControllerFactory::generateFromMessageResource($message_resource,$m,$u,$r,5);
        $message_to_send_resource = $brink_controller->prepMessageForSending($message_resource);
        $this->assertNotNull($message_to_send_resource,"Should have generated the message to send resource");
        myerror_log($message_to_send_resource->message_text);
        $body = cleanUpDoubleSpacesCRLFTFromString($message_to_send_resource->message_text);
        $expected_modifier_payload = cleanUpDoubleSpacesCRLFTFromString('
                                <NewOrderItemModifier>
                                    <Description i:nil="true" />
                                    <Id>2</Id>
                                    <ItemId>5678</ItemId>
                                    <Modifiers i:nil="true" />
                                    <Price>0.500</Price>
                                    <ModifierCodeId>0</ModifierCodeId>
                                    <ModifierGroupId>1234</ModifierGroupId>
                                </NewOrderItemModifier>
                                <NewOrderItemModifier>
                                    <Description i:nil="true" />
                                    <Id>3</Id>
                                    <ItemId>5678</ItemId>
                                    <Modifiers i:nil="true" />
                                    <Price>0.500</Price>
                                    <ModifierCodeId>0</ModifierCodeId>
                                    <ModifierGroupId>1234</ModifierGroupId>
                                </NewOrderItemModifier>
                                <NewOrderItemModifier>
                                    <Description i:nil="true" />
                                    <Id>4</Id>
                                    <ItemId>5678</ItemId>
                                    <Modifiers i:nil="true" />
                                    <Price>0.500</Price>
                                    <ModifierCodeId>0</ModifierCodeId>
                                    <ModifierGroupId>1234</ModifierGroupId>
                                </NewOrderItemModifier>
                                ');

        $this->assertContains($expected_modifier_payload,$body,"it should have all three modifier nodes since there is no such thing as quantity for the template");

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

        getOrCreateSkinAndBrandIfNecessary("Smiling Moose","Smiling Moose",6,152);

        $mysqli->begin_transaction();
        setContext("com.splickit.smilingmoose");
        $ids['skin_id'] = getSkinIdForContext();

        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $ids['menu_id'] = $menu_id;

        $cm = new CompleteMenu($menu_id);
        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $modifier_item_record = ModifierItemAdapter::staticGetRecord(array("modifier_group_id"=>$modifier_group_id),'ModifierItemAdapter');
        $modifier_size_record = ModifierSizeMapAdapter::staticGetRecord(array("modifier_item_id"=>$modifier_item_record['modifier_item_id']),'ModifierSizeMapAdapter');

        $modifier_size_resource = Resource::find(new ModifierSizeMapAdapter($m),"".$modifier_size_record['modifier_size_id'],$options);
        $modifier_size_resource->external_id = "1234-5678";
        $modifier_size_resource->save();
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 0);

        //$merchant_resource = createNewTestMerchant($menu_id);
        $merchant_resource = createNewTestMerchantDelivery($menu_id);
        $merchant_resource->merchant_external_id = 'pitapit-123456';
        $merchant_resource->save();

        $merchant_delivery_price_resource = Resource::find(new MerchantDeliveryPriceDistanceAdapter($m),'',array(3=>array("merchant_id"=>$merchant_resource->merchant_id)));
        $merchant_delivery_price_resource->name = "zone_1";
        $merchant_delivery_price_resource->save();

        Resource::createByData(new MerchantDeliveryPriceDistanceAdapter($m),array("merchant_id"=>$merchant_resource->merchant_id,"distance_up_to"=>"1000","name"=>"zone_2"));

        $complete_menu = CompleteMenu::getCompleteMenu($menu_id,'Y',$merchant_resource->merchant_Id);
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $ids['merchant_id'] = $merchant_resource->merchant_id;
        Resource::createByData(new MerchantBrinkInfoMapsAdapter($m),array("merchant_id"=>$merchant_resource->merchant_id,"brink_location_token"=>getProperty('brink_test_location_token')));
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_resource->merchant_id,'B','brink','X');

        $user_resource = createNewUserWithCCNoCVV(array("contact_no"=>'123 456 7890'));
        $ids['user_id'] = $user_resource->user_id;

        $_SERVER['log_level'] = 5;
        $_SERVER['unit_test_ids'] = $ids;


        $promo_adapter = new PromoAdapter($m);
        $promo_id = 111;
        $ids['promo_amt'] = $promo_amt;
        if ($promo_record = $promo_adapter->getRecordFromPrimaryKey($promo_id)) {
            ;
        } else {
            $ids['promo_id_type_1'] = $promo_id;
            $sql = "INSERT INTO `Promo` VALUES($promo_id, 'The Type1 Promo', 'Get Up to $5 off', 1, 'Y', 'N', 0, 2, 'N', 'N','all', '2010-01-01', '2020-01-01', 100000,FALSE, 0, 0.00, 0, 0.00, 'Y', 'N',0, 282, NOW(), NOW(), 'N')";
            $promo_adapter->_query($sql);
//            $sql = "INSERT INTO `Promo_Merchant_Map` VALUES(null, 201, $merchant_resource->merchant_id, '2013-10-05', '2020-01-01', NULL, now())";
//            $promo_adapter->_query($sql);
            $pmm_resource = Resource::createByData(new PromoMerchantMapAdapter($mimetypes), array("merchant_id"=>$merchant_resource->merchant_id,"promo_id"=>$promo_id));
            $ids['promo_merchant_map_id_type_1'] = $pmm_resource->map_id;
            $sql = "INSERT INTO `Promo_Message_Map` VALUES(null, $promo_id, 'Congratulations! You''re getting %%amt%% off your order!', NULL, NULL, NULL, NULL, now())";
            $promo_adapter->_query($sql);
            $sql = "INSERT INTO `Promo_Type1_Amt_Map` VALUES(null, $promo_id, 1.00, 0, 50,50.00, NOW())";
            $promo_adapter->_query($sql);
            $pkwm_adapter = new PromoKeyWordMapAdapter($m);
            Resource::createByData($pkwm_adapter, array("promo_id"=>$promo_id,"promo_key_word"=>"type1promo","brand_id"=>282));

        }
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
    BrinkControllerTest::main();
}

?>