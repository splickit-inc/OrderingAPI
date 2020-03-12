<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class VivonetControllerTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__ . '-' . $_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        setContext("com.splickit.pitapit");
    }

    function tearDown()
    {
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
    }

    function testCombineTax()
    {
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        $store_id = 123456789;
        $mvima_data = ['merchant_id'=>$merchant_resource->merchant_id,'store_id'=>$store_id,'merchant_key'=>'736de47f4cdee9e8fe9ab8a0bc0eb37e'];
        $mvima = new MerchantVivonetInfoMapsAdapter(getM());
        $mvim_resource = Resource::createByData($mvima,$mvima_data);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $this->assertEquals(.33,$checkout_resource->total_tax_amt);

    }

    function testGetTipId()
    {
        $vivonet_service = new VivonetService(array('merchant_id'=>$this->ids['merchant_id']));
        $service_tip_id = $vivonet_service->getServiceTipIdForStore();
        $this->assertEquals(89998,$service_tip_id);
    }

    function testGetVivonetInfo()
    {
        $vivonet_service = new VivonetService(array('merchant_id'=>$this->ids['merchant_id']));
        $tender_id = $vivonet_service->getTenderIdForStore();
        $this->assertEquals(8767339,$tender_id);
    }

    function testGetAndSetVivonetInfo()
    {
        $merchant_id = $this->ids['merchant_id'];
        $mvima = new MerchantVivonetInfoMapsAdapter(getM());
        $record = $mvima->getRecord(array("merchant_id"=>$merchant_id));
        $this->assertNull($record['tender_id']);
        $vivonet_controller = new VivonetController(getM(),null,$r,5);
        $tender_id = $vivonet_controller->getTenderIdForMerchant($merchant_id);
        $this->assertEquals(8767339,$tender_id);
        $info_record = $mvima->getRecord(array("merchant_id"=>$merchant_id));
        $this->assertEquals(8767339,$info_record['tender_id'],'It should have created the tender id');
    }

    function testGetAndSetVivonetTipInfo()
    {
        $merchant_id = $this->ids['merchant_id'];
        $mvima = new MerchantVivonetInfoMapsAdapter($m);
        $mvim_record = $mvima->getRecord(array("merchant_id"=>$merchant_id));
        $this->assertNotNull($mvim_record,"there should be a record");
        $this->assertNull($mvim_record['service_tip_id'],"It should not have a service tip yet");

        $vivonet_controller = new VivonetController($m,$u,$r,5);
        $service_tip_id = $vivonet_controller->getTipIdForMerchant($merchant_id);
        $this->assertEquals(89998,$service_tip_id);
        $info_record = $mvima->getRecord(array("merchant_id"=>$merchant_id));
        $this->assertEquals(89998,$info_record['service_tip_id'],'It should have created the service tip id');
    }

    function testGetPromoChargeId()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'pickup','the note',5);
        $cart_data['promo_code'] = 'type1promo';
        $request = createRequestObject('app2/apiv2/cart/checkout','post',json_encode($cart_data),'application/json');
        $place_order_controller = new PlaceOrderController(getM(),$user,$request,5);
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error,"It should not throw an error");
        $this->assertEquals("type1promo",$checkout_resource->promo_code);
        $this->assertTrue($checkout_resource->promo_amt < 0.00,"It should have a negative promo amt");
        $post_grand_total = $checkout_resource->grand_total;
        $post_tax_total = $checkout_resource->total_tax_amt;
        $post_order_amt = $checkout_resource->order_amt;
        $post_promo_amt = $checkout_resource->promo_amt;
        $order_sum = $post_order_amt+$post_promo_amt+$post_tax_total;
        $this->assertEquals($post_grand_total,$order_sum,"the grand total should replect the amounts");

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$this->ids['merchant_id'],1.00,time());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000);
        $this->assertNull($order_resource->error);

        $vivonet_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id, 'V');
        $this->assertNotNull($vivonet_message_resource);

        $vivonet_controller = ControllerFactory::generateFromMessageResource($vivonet_message_resource);
        $ready_to_send_message_resource = $vivonet_controller->prepMessageForSending($vivonet_message_resource);
        $message_text = $ready_to_send_message_resource->message_text;
        myerror_log("vivonet message text: ".$message_text);

        $this->assertContains('"charges":[{"amount":1,"chargeId":89998,"name":"SERVICETIP"},{"amount":-5,"chargeId":88888,"name":"SERVICETIP"}]',$message_text);
        $this->assertNotContains('"discountName":"type1promo"',$message_text);

    }


    function testNestedModifierTemplate()
    {
        $menu_id = createTestMenuWithNnumberOfItems(1);

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $options[TONIC_FIND_BY_METADATA]['modifier_group_id'] = $modifier_group_id;
        $modifier_item_resource = Resource::find(new ModifierItemAdapter($m),null,$options);
        $modifier_item_resource->modifier_item_name = 'Flavor Shot=Irish Cream';
        $modifier_item_resource->modifier_item_print_name = 'Flavor Shot=Irish Cream';
        $modifier_item_resource->save();
        $options2[TONIC_FIND_BY_METADATA]['modifier_item_id'] = $modifier_item_resource->modifier_item_id;
        $modifier_size_resource = Resource::find(new ModifierSizeMapAdapter($m), null, $options2);
        $modifier_size_resource->external_id = "88888:77777";
        $modifier_size_resource->save();

        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 0);

        $merchant_resource = createNewTestMerchant($menu_id);
        $store_id = generateCode(10);
        $mvima_data = ['merchant_id'=>$merchant_resource->merchant_id,'store_id'=>$store_id];
        $mvima = new MerchantVivonetInfoMapsAdapter(getM());
        $mvim_resource = Resource::createByData($mvima,$mvima_data);

        $merchant_id = $merchant_resource->merchant_id;
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_id,'V','vivonet','X');

        $user = logTestUserIn($this->ids['user_id']);
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'sum dum note');
        $order_data['items'][0]['note'] = "item level note";
        $order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;

        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'V');
        $vivonet_controller = ControllerFactory::generateFromMessageResource($message_resource);
        $message_to_send_resource = $vivonet_controller->prepMessageForSending($message_resource);
        $this->assertNotNull($message_to_send_resource,"Should have generated the message to send resource");
        $json_body = $message_to_send_resource->message_text;
        $order_payload_as_array = json_decode($json_body,true);
        $this->assertEquals("Flavor Shot",$order_payload_as_array['orderLineItems'][0]['modifiers'][0]['productName']);
        $this->assertEquals(88888,$order_payload_as_array['orderLineItems'][0]['modifiers'][0]['productId']);
        $nested_mods = $order_payload_as_array['orderLineItems'][0]['modifiers'][0]['modifiers'];
        $this->assertCount(1,$nested_mods,'Should have had 1 nested mod');
        $nested_mod = $nested_mods[0];
        $this->assertEquals('Irish Cream',$nested_mod['productName']);
        $this->assertEquals(77777,$nested_mod['productId']);
    }

    function testCreateVivonetMessageControllerFromFactory()
    {
        $message_resource = Resource::dummyfactory(array("message_format" => 'V'));
        $controller_name = ControllerFactory::getControllerNameFromMessageResource($message_resource);
        $this->assertEquals('Vivonet', $controller_name, "It should return Vivonet as the controller name");
        $controller = ControllerFactory::generateFromMessageResource($message_resource, $m, $u, $r, 5);
        $this->assertEquals('VivonetController', get_class($controller), "It should return a Vivonet Controller");
    }

    //this test should be in the service test temporally
    function testCurlObjectWithAllRequest()
    {
        $message_resource = Resource::dummyfactory(array("message_format" => 'V'));
        $controller_name = ControllerFactory::getControllerNameFromMessageResource($message_resource);
        $this->assertEquals('Vivonet', $controller_name, "It should return a Vivonet Controller");
        $controller = ControllerFactory::generateFromMessageResource($message_resource, $m, $u, $r);
        $this->assertEquals('VivonetController', get_class($controller), "It should be a Vivonet Controller");

        //initial configuration
        $x_api_key = "736de47f4cdee9e8fe9ab8a0bc0eb37e";
        $headers[] = "x-api-key: $x_api_key";

        //request to the configuration of vivonet
        $url_get_configuration = "https://api.vivonet.com/v1/apiKeys/stores";
        $curl_response_configuration = VivonetCurl::curlIt($url_get_configuration, $json, $headers);
        $this->assertNotNull($curl_response_configuration);
        $this->assertEquals(200, $curl_response_configuration['http_code']);
        $this->assertEquals("https://api.vivonet.com/v1/apiKeys/stores", $curl_response_configuration['curl_info']['url']);
        $this->assertNotEmpty($curl_response_configuration['curl_info']);

        //recovery and set storeId for others request
        $configuration_to_json = json_decode($curl_response_configuration['raw_result']);
        $this->assertNotNull($configuration_to_json);
        $configuration = $configuration_to_json[0];
        $this->assertNotNull($configuration->storeId);
        $this->assertEquals(161668, $configuration->storeId);

        //request to get tenders
        $url_get_tenders = "https://api.vivonet.com/v1/stores/" . $configuration->storeId . "/tenders";
        $curl_response_tenders = VivonetCurl::curlIt($url_get_tenders, $json, $headers);
        $this->assertNotNull($curl_response_tenders);
        $this->assertEquals(200, $curl_response_tenders['http_code']);
        $tenders_to_json = json_decode($curl_response_tenders['raw_result']);
        $this->assertNotNull($tenders_to_json);
        $tenders = $tenders_to_json[0];
        $this->assertNotNull($tenders->tenderId);

        //post request for orders test the curl only with no valid data the purpose is test the curl object
        $json_encoded_order = "{\"orderId\": 0,\"externalSystemOrderId\":\"string\",\"orderPlacedBy\":\"string\",
        \"orderPlacerId\":\"string\",\"orderLineItems\": [{\"orderLineItemId\": 0,\"productId\": 0,
        \"productName\":\"string\",\"orderTypeId\": 0,\"price\": 0,\"quantity\": 0,\"quantityUnit\":\"string\",
        \"ignorePrice\": true,\"remark\":\"string\",\"modifiers\": [{}],\"discounts\": [ {\"discountId\": 0,
        \"discountName\":\"string\",\"discountType\":\"string\",\"value\": 0}]}],\"charges\": [{\"chargeId\": 0,
        \"name\":\"string\",\"amount\": 0}],\"payments\": [{\"paymentId\": 0,\"tenderId\": 0,\"amount\": 0,
        \"lineItemIds\": [0],\"paymentMethod\": {\"paymentMethodId\": 0,\"type\":\"string\",\"nameOnCard\":\"string\",
        \"cardNumber\":\"string\",\"expirationDate\":\"string\",\"securityCode\":\"string\",
        \"base64Data\":\"string\"}}],\"pickupTime\": 0}";

        $url_post_ordes = "https://api.vivonet.com/v1/stores/" . $configuration->storeId . "/orders";
        $curl_response_order = VivonetCurl::curlIt($url_post_ordes, $json_encoded_order, $headers);
        $this->assertNotNull($curl_response_order);
        $result = json_decode($curl_response_order['raw_result']);
        $this->assertNotNull($result);
    }

    //Get the message template from database and populate it
    function testGenerateTemplate()
    {
        $user = logTestUserIn($this->ids['user_id']);
        $merchant_id = $this->ids['merchant_id'];
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,1.11);
//        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'sum dum note');
//        $order_resource = placeOrderFromOrderData($order_data, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertNotNull($order_id);
        $messages = MerchantMessageHistoryAdapter::getAllOrderMessages($order_id);
        $messages_hash = createHashmapFromArrayOfResourcesByFieldName($messages, 'message_format');
        $this->assertNotNull($messages_hash['V'], "SHould have created a message with the Vivonet format");
        return $messages_hash['V'];
    }

    /**
     * @depends testGenerateTemplate
     */
    function testCreateTemplateFromMessageResource($message_resource)
    {
        $this->assertNotNull($message_resource);
        $this->assertTrue(true);
        $order_id = $message_resource->order_id;
        $this->assertNotNull($order_id);
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id, $m);
        $user = $complete_order['user'];
        $this->assertNotNull($user);
        $vivonet_controller = ControllerFactory::generateFromMessageResource($message_resource, $m, $u, $r);
        $response = $vivonet_controller->sendThisMessage($message_resource);
        $this->assertNotNull($response);
        $this->assertTrue($response);
        $message = $message_resource->message_text;
        $this->assertContains('"tenderId":8767339',$message);
        $this->assertContains('"charges":[{"amount":'.$complete_order['tip_amt'].',"chargeId":89998,"name":"SERVICETIP"}]',$message);
        $this->assertContains("orderId",$message);
        $this->assertContains("externalSystemOrderId",$message);
        $this->assertContains("orderPlacedBy",$message);
        $this->assertContains("orderPlacerId",$message);
        $this->assertContains("pickupTime",$message);
        $this->assertContains("orderLineItems",$message);
        $this->assertContains("orderLineItemId",$message);
        $this->assertContains("productId",$message);
        $this->assertContains("productName",$message);
        $this->assertContains("orderTypeId",$message);
        $this->assertContains("price",$message);
        $this->assertContains("quantity",$message);
        $this->assertContains("quantityUnit",$message);
        $this->assertContains("ignorePrice",$message);
        $this->assertContains("remark",$message);
        $this->assertContains("modifiers",$message);
        $this->assertContains("orderLineItemId",$message);
        $this->assertContains("discounts",$message);
// changed how we're doing discounts
//        $this->assertContains("discountName",$message);
//        $this->assertContains("discountType",$message);
//        $this->assertContains("promotion",$message);
        $this->assertContains("charges",$message);
        $this->assertContains("payments",$message);
        $this->assertContains("paymentId",$message);
        $this->assertContains("lineItemIds",$message);
        $this->assertContains("cardNumber",$message);
    }

    /**
     * @depends testGenerateTemplate
     */
    function testSendThisMessage($message_resource)
    {
        $this->assertNotNull($message_resource);
        $this->assertTrue(true);
        $order_id = $message_resource->order_id;
        $this->assertNotNull($order_id);
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id, getM());
        $user = $complete_order['user'];
        $this->assertNotNull($user);
        $vivonet_controller = ControllerFactory::generateFromMessageResource($message_resource, $m, $u, $r);
        $response = $vivonet_controller->sendThisMessage($message_resource);
        $this->assertTrue($response);
        return $order_id;
    }

    /**
     * @depends testSendThisMessage
     */
    function testSendThisMessageWithTextAlreadyRecorded($order_id)
    {
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'V');
        $message_resource->locked = 'N';
        $message_resource->save();
        $this->assertNotNull($message_resource);
        $vivonet_controller = ControllerFactory::generateFromMessageResource($message_resource, getM(),null, null);
        $response = $vivonet_controller->sendThisMessage($message_resource);
        $this->assertTrue($response);
        $this->assertNull($vivonet_controller->getErrorMessage());
    }


    static function setUpBeforeClass()
    {
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        ini_set('max_execution_time', 300);
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;

        setContext('com.splickit.pitapit');
        $ids['skin_id'] = getSkinIdForContext();

        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $ids['menu_id'] = $menu_id;

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $modifier_item_record = ModifierItemAdapter::staticGetRecord(array("modifier_group_id" =>
            $modifier_group_id), 'ModifierItemAdapter');
        $modifier_size_record = ModifierSizeMapAdapter::staticGetRecord(array("modifier_item_id" =>
            $modifier_item_record['modifier_item_id']), 'ModifierSizeMapAdapter');

        $modifier_size_resource = Resource::find(new ModifierSizeMapAdapter($m), "" .
            $modifier_size_record['modifier_size_id'], $options);
        $modifier_size_resource->external_id = "1234-5678";
        $modifier_size_resource->save();
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 0);

        $merchant_resource = createNewTestMerchant($menu_id);
        $mvima_data = ['merchant_id'=>$merchant_resource->merchant_id,'store_id'=>1616688,'promo_charge_id'=>88888];
        $mvima = new MerchantVivonetInfoMapsAdapter(getM());
        $mvim_resource = Resource::createByData($mvima,$mvima_data);
//        $merchant_resource->set('merchant_external_id', '161668');
//        $merchant_resource->save();
        $complete_menu = CompleteMenu::getCompleteMenu($menu_id, 'Y', $merchant_resource->merchant_Id);
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $ids['merchant_id'] = $merchant_resource->merchant_id;
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_resource->merchant_id, 'V', 'vivonet', 'X');

        $promo_adapter = new PromoAdapter(getM());
        $promo_id = 111;
        if ($promo_record = $promo_adapter->getRecordFromPrimaryKey($promo_id)) {
            ;
        } else {
            $brand_id = getBrandIdFromCurrentContext();
            $ids['promo_id_type_1'] = $promo_id;
            $sql = "INSERT INTO `Promo` VALUES($promo_id, 'The Type1 Promo', 'Get Up to $5 off', 1, 'Y', 'N', 0, 2, 'N', 'N','all', '2010-01-01', '2020-01-01', 100000,FALSE, 0, 0.00, 0, 0.00, 'Y', 'N', 0,$brand_id, NOW(), NOW(), 'N')";
            $promo_adapter->_query($sql);
            $pmm_resource = Resource::createByData(new PromoMerchantMapAdapter(getM()), array("merchant_id"=>$merchant_resource->merchant_id,"promo_id"=>$promo_id));
            $ids['promo_merchant_map_id_type_1'] = $pmm_resource->map_id;
            $sql = "INSERT INTO `Promo_Message_Map` VALUES(null, $promo_id, 'Congratulations! You''re getting %%amt%% off your order!', NULL, NULL, NULL, NULL, now())";
            $promo_adapter->_query($sql);
            $sql = "INSERT INTO `Promo_Type1_Amt_Map` VALUES(null, $promo_id, 1.00, 0, 50,50.00, NOW())";
            $promo_adapter->_query($sql);
            $pkwm_adapter = new PromoKeyWordMapAdapter($m);
            Resource::createByData($pkwm_adapter, array("promo_id"=>$promo_id,"promo_key_word"=>"type1promo","brand_id"=>getBrandIdFromCurrentContext()));

        }

        $user_resource = createNewUser(array("flags" => "1C20000001"));
        $ids['user_id'] = $user_resource->user_id;

        $_SERVER['log_level'] = 5;
        $_SERVER['unit_test_ids'] = $ids;

    }

    static function tearDownAfterClass()
    {
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->rollback();
        date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main()
    {
        $suite = new PHPUnit_Framework_TestSuite(__CLASS__);
        PHPUnit_TextUI_TestRunner::run($suite);
    }
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    VivonetControllerTest::main();
}

?>