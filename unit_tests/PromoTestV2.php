<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class PromoTestV2 extends PHPUnit_Framework_TestCase
{
	var $menu;
	var $merchant;
	var $user;
	var $stamp;
	var $ids;

	function setUp()
	{		
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];

		setContext("com.splickit.order");		
		$this->user = logTestUserIn($_SERVER['unit_test_ids']['user_id']);
		$this->ids = $_SERVER['unit_test_ids'];
		setProperty('duplicate_order_test', 'true');
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
	}
	
	function tearDown() 
	{
		//delete your instance
		unset($this->user);
    	unset($this->merchant);
    	unset($this->menu);
    	unset($this->ids);
    	$_SERVER['STAMP'] = $this->stamp;
    	unset($this->stamp);
    }

    function testProcessPromoTypeFiveWithLooping()
    {
        setContext("com.splickit.pitapit");

        $menu_id = createTestMenuWithNnumberOfItems(1);
        createNewMenuTypeWithNNumberOfItems($menu_id,'menu_type_too');
        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;

        $is = CompleteMenu::getAllItemSizesAsResources($menu_id);

        $promo_data = [];
        $promo_data['key_word'] = 'Test Promo Five Full Process';
        $promo_data['promo_type'] = 5;
        $promo_data['description'] = 'Get $ off when ordered together';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['merchant_id'] = $merchant_id;
        $promo_data['message5'] = "Here's the deal, order a this and a that, and you'll get a discount!";
        $promo_data['qualifying_object_array'] = ["ItemSize-".$is[0]->item_size_id,"ItemSize-".$is[1]->item_size_id];
        $promo_data['fixed_amount_off'] = 1.00;
        $promo_data['percent_off'] = 50;
        $promo_data['fixed_price'] = .75;
        $promo_data['brand_id'] = 282;

        $request = createRequestObject("/app2/admin/promotype1",'POST',json_encode($promo_data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response_five = $promo_controller->createPromo();
        $response_five->allow_multiple_use_per_order = 1;
        $response_five->save();
        $promo_amount_map_resource = $response_five->promo_amount_map;
        $promo_id = $response_five->promo_id;

        $type5_promo_key_word = "type_five_loop";

        Resource::createByData(new PromoKeyWordMapAdapter(getM()), array("promo_id"=>$promo_id,"promo_key_word"=>"$type5_promo_key_word","brand_id"=>282));



        $promo = PromoAdapter::staticGetRecordByPrimaryKey($promo_id,'PromoAdapter');

        $user = logTestUserIn($this->ids['user_id']);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $items = [];
        for ($i=0;$i<3;$i++) {
            $items[] = ["item_id"=>$is[0]->item_id,"size_id"=>$is[0]->size_id,"quantity"=>1,"mods"=>[]];

        }
        for ($i=0;$i<3;$i++) {
            $items[] = ["item_id" => $is[1]->item_id, "size_id" => $is[1]->size_id, "quantity" => 1, "mods" => []];
        }
        $order_data['items'] = $items;
        $promo_controller = new PromoController(getM(),$user,null,5);
        $promo_controller->setPromoMessagesFromPromoId($promo_id);
        $promo_controller->setTaxRates($merchant_id);
        $resource = $promo_controller->processPromoTypeFive($order_data,$promo);

        $this->assertEquals("true",$resource->complete_promo,"It should have had complete promo");
        $this->assertEquals(5,$resource->promo_type,"It should be of type 5");
        $this->assertEquals("3.00",$resource->amt,"It should have 1.00 for the amt");
        $this->assertEquals(".30",$resource->tax_amt,"It should have .10 for the amt");
        $this->assertEquals("Congratulations! You're getting $3.00 off of your order!",$resource->user_message);
        return ['merchant_id'=>$merchant_id,"promo_id"=>$promo_id,"menu_id"=>$menu_id];
    }

    /**
     * @depends testProcessPromoTypeFiveWithLooping
     */
    function testPromoType5LoopingWithQuantity($data)
    {
        $promo_id = $data['promo_id'];
        $merchant_id = $data['merchant_id'];
        $menu_id = $data['menu_id'];
        $is = CompleteMenu::getAllItemSizesAsResources($menu_id);

        $promo = PromoAdapter::staticGetRecordByPrimaryKey($promo_id,'PromoAdapter');

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $items = [];
        for ($i=0;$i<3;$i++) {
            $items[] = ["item_id"=>$is[0]->item_id,"size_id"=>$is[0]->size_id,"quantity"=>1,"mods"=>[]];

        }
        $items[] = ["item_id" => $is[1]->item_id, "size_id" => $is[1]->size_id, "quantity" => 3, "mods" => []];

        $order_data['items'] = $items;
        $promo_controller = new PromoController(getM(),$user,null,5);
        $promo_controller->setPromoMessagesFromPromoId($promo_id);
        $promo_controller->setTaxRates($merchant_id);
        $resource = $promo_controller->processPromoTypeFive($order_data,$promo);

        $this->assertEquals("true",$resource->complete_promo,"It should have had complete promo");
        $this->assertEquals(5,$resource->promo_type,"It should be of type 5");
        $this->assertEquals("3.00",$resource->amt,"It should have 1.00 for the amt");
        $this->assertEquals(".30",$resource->tax_amt,"It should have .10 for the amt");
        $this->assertEquals("Congratulations! You're getting $3.00 off of your order!",$resource->user_message);
    }


    function testTypeTwoSameQualifyingAndPromoItem()
    {
        setContext("com.splickit.worldhq");
        $brand_id = getBrandIdFromCurrentContext();
        $menu_id = createTestMenuWithNnumberOfItems(5);

        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;

        $menu_type_promo_tag = '12345qwert';
        $menu_type_resource = Resource::find(new MenuTypeAdapter(getM()),null,[3=>['menu_id'=>$menu_id]]);
        $menu_type_resource->promo_tag = $menu_type_promo_tag;
        $menu_type_resource->save();


        //create the type 2 promo
        $promo_type = 'pickup';
        $promo_id = 210;
        $promo_adapter = new PromoAdapter(getM());
        $sql = "INSERT INTO `Promo` VALUES($promo_id, 'same item test', 'Buy one get one free', 2, 'Y', 'N', 0, 2, 'N', 'N','$promo_type','2010-01-01', '2020-01-01', 1,false, 0, 0.00, 0, 0.00, 'Y', 'N',0,$brand_id, NOW(), NOW(), 'N')";
        $promo_adapter->_query($sql);
        $pmm_resource = Resource::createByData(new PromoMerchantMapAdapter(getM()), array("merchant_id"=>$merchant_id,"promo_id"=>$promo_id));
        $sql = "INSERT INTO `Promo_Message_Map` VALUES(null, $promo_id, 'Congratulations! You''re getting a FREE that!', NULL, NULL, 'Almost there, now add a standard that to this order and its FREE!', 'Here''s the deal, order a large this, then add a that to go with it, and its FREE! Limit 1', now())";
        $promo_adapter->_query($sql);
        $sql = "INSERT INTO `Promo_Type2_Item_Map` VALUES(null, $promo_id, 10.00, '$menu_type_promo_tag', '$menu_type_promo_tag', NULL,'Menu_Type-".$menu_type_resource->menu_type_id."','Menu_Type-".$menu_type_resource->menu_type_id."', '2012-01-13 01:25:16')";
        $promo_adapter->_query($sql);

        $pkwm_adapter = new PromoKeyWordMapAdapter(getM());
        Resource::createByData($pkwm_adapter, array("promo_id"=>$promo_id,"promo_key_word"=>"SameItem","brand_id"=>getBrandIdFromCurrentContext()));

        $promo_resource = Resource::find(new PromoAdapter(getM()),"$promo_id");
        $this->assertEquals(0,$promo_resource->allow_multiple_use_per_order);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $cart_data['promo_code'] = 'SameItem';
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $ucid = $checkout_resource->ucid;

        //check for minimum
        $options[TONIC_FIND_BY_METADATA]['promo_id'] = 210;
        $promo_type2_item_map_resource = Resource::find(new PromoType2ItemMapAdapter(getM()),null,$options);

        $promo_controller = new PromoController(getM(),null,null);
        $expected = $promo_controller->getPromoMinumimNotMetMessage(10.00);
        $this->assertContains($expected, $checkout_resource->user_message,"It should have the promom minimum not met message");

        //now reset minimum
        $promo_type2_item_map_resource->qualifying_amt = 0.01;
        $promo_type2_item_map_resource->save();

        $expected = "You have not completed your promo! Almost there, now add a standard that to this order and its FREE!";
        //$this->assertEquals($expected, $checkout_resource->user_message);

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'pickup','the note',3);
        // remove the first item since its already in hte cart from the first push
        array_shift($cart_data['items']);
        $cart_data['ucid'] = $ucid;
        $checkout_resource2 = getCheckoutResourceFromOrderData($cart_data);
        $this->assertNull($checkout_resource2->error);

        $success_expected = "Congratulations! You're getting a FREE that!";
        $this->assertContains($success_expected, $checkout_resource2->user_message);

        $this->assertEquals(-1.50,$checkout_resource2->promo_amt);


        // now add another item and nothing should change untill looping is set to true for this promo

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'pickup','the note',1);
        $cart_data['ucid'] = $checkout_resource->ucid;
        $checkout_resource3 = getCheckoutResourceFromOrderData($cart_data);
        $this->assertNull($checkout_resource3->error);
        $this->assertEquals(-1.50,$checkout_resource3->promo_amt);


        // now set looping to true
        $promo_resource->allow_multiple_use_per_order = 1;
        $promo_resource->save();

        $url = "/app2/apiv2/cart/$ucid/checkout";
        $request = createRequestObject($url, 'GET');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource4 = $place_order_controller->processV2Request();

        $this->assertNull($checkout_resource4->error);
        $this->assertEquals(-3.00,$checkout_resource4->promo_amt);



    }

    function testLoopingOnPromoType2()
    {
        $promo_resource = Resource::find(new PromoAdapter(getM()),"200");
        $this->assertEquals(1,$promo_resource->allow_multiple_use_per_order);
        $merchant_id = $this->ids['merchant_id'];
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',3);
        $items = $order_data['items'];
        $double_items = array_merge($order_data['items'],$items);
        $order_data['items'] = $double_items;
        $order_data['promo_code'] = 'Alternatekeyword';
        $json_encoded_data = json_encode($order_data);
        $request = new Request();
        $request->url = '/app2/apiv2/cart/checkout';
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);
//        $expected = "Congratulations! You're getting 2 FREE that!";
//        $this->assertEquals($expected, $checkout_resource->user_message);
        $this->assertEquals(-3.00,$checkout_resource->promo_amt,'It should have doubled the promo amount');
    }

    function testPickupRestrictonOnPromo()
    {
        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;

        $pmm_resource = Resource::createByData(new PromoMerchantMapAdapter(getM()), array("merchant_id"=>$merchant_id,"promo_id"=>201));

        $user_resource = createNewUserWithCCNoCVV();

        $mdpd = new MerchantDeliveryPriceDistanceAdapter($mt);
        $mdpd_resource = $mdpd->getExactResourceFromData(array('merchant_id' => $merchant_id));
        $mdpd_resource->distance_up_to = 10.0;
        $mdpd_resource->price = 8.88;
        $mdpd_resource->save();

        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];
        $json = '{"user_addr_id":null,"user_id":"' . $user_id . '","name":"","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
        $request = createRequestObject("/users/" . $user['uuid'] . "/userdeliverylocation", 'POST', $json, "application/json");

        $user_controller = new UserController(getM(), $user, $request, 5);
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id", 'GET');
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNull($resource->error);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note', 4);
        $order_data['user_addr_id'] = $user_address_id;
        $order_data['submitted_order_type'] = 'delivery';

        $json_encoded_data = json_encode($order_data);
        $request = createRequestObject('/app2/apiv2/cart/checkout', 'POST', $json_encoded_data, 'application/json');

        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);
        $ucid = $checkout_resource->ucid;

        // now try to apply promo
        $duplicate_promo_key_word = $this->ids['duplicate_promo_key_word'];
        $request = createRequestObject("/app2/apiv2/cart/$ucid/checkout?promo_code=type1promo","GET");
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $promo_resource_result = $place_order_controller->processV2Request();
        $this->assertNotNull($promo_resource_result->error);
        $this->assertEquals("Sorry! This promo is only valid on pickup orders.",$promo_resource_result->error);


    }

    function testProcessPromoTypeFive()
    {
        setContext("com.splickit.pitapit");
        $merchant_id = $this->ids['merchant_id'];

        $promo_data = [];
        $promo_data['key_word'] = 'Test Promo Five Full Process';
        $promo_data['promo_type'] = 5;
        $promo_data['description'] = 'Get $ off when ordered together';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['merchant_id'] = $merchant_id;
        $promo_data['message5'] = "Here's the deal, order a this and a that, and you'll get a discount!";
        $promo_data['qualifying_object_array'] = ["Entre","Entre","Entre"];
        $promo_data['fixed_amount_off'] = 1.00;
        $promo_data['percent_off'] = 50;
        $promo_data['fixed_price'] = .75;
        $promo_data['brand_id'] = 282;

        $request = createRequestObject("/app2/admin/promotype1",'POST',json_encode($promo_data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response_five = $promo_controller->createPromo();
        $promo_amount_map_resource = Resource::find(new PromoType4ItemAmountMapsAdapter(getM()),$response_five->promo_amount_map['id']);
        $promo_id = $response_five->promo_id;

        $type5_promo_key_word = "type_five_full";

        Resource::createByData(new PromoKeyWordMapAdapter(getM()), array("promo_id"=>$promo_id,"promo_key_word"=>"$type5_promo_key_word","brand_id"=>282));

        $options[TONIC_FIND_BY_METADATA]['promo_id'] = $promo_id;
        $promo_type4_item_map_resource = Resource::find(new PromoType4ItemAmountMapsAdapter(getM()),null,$options);
        $promo_type4_item_map_resource->qualifying_amt = 8.88;
        $promo_type4_item_map_resource->save();

        $promo = PromoAdapter::staticGetRecordByPrimaryKey($promo_id,'PromoAdapter');
        $user = logTestUserIn($this->ids['user_id']);
        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours',2);
        $promo_controller = new PromoController(getM(),$user,null,5);
        $promo_controller->setPromoMessagesFromPromoId($promo_id);
        $promo_controller->setTaxRates($merchant_id);
        $resource = $promo_controller->processPromoTypeFive($order_data,$promo);
        $this->assertEquals(false,$resource->complete_promo,"It should NOT have had complete promo");

        //check for minimum
        $expected = $promo_controller->getPromoMinumimNotMetMessage(8.88);
        $this->assertEquals($expected, $resource->user_message,"It should have the promom minimum not met message");

        //now reset minimum
        $promo_type4_item_map_resource->qualifying_amt = 0.01;
        $promo_type4_item_map_resource->save();

        $resource = $promo_controller->processPromoTypeFive($order_data,$promo);
        $this->assertEquals(false,$resource->complete_promo,"It should NOT have had complete promo");
        $this->assertEquals("Here's the deal, order a Entre and Entre and Entre, and you'll get a discount!",$resource->user_message);

        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours',3);
        $promo_controller = new PromoController($m,$user,$r,5);
        $promo_controller->setPromoMessagesFromPromoId($promo_id);
        $promo_controller->setTaxRates($merchant_id);
        $resource = $promo_controller->processPromoTypeFive($order_data,$promo);

        $this->assertEquals("true",$resource->complete_promo,"It should have had complete promo");
        $this->assertEquals(5,$resource->promo_type,"It should be of type 5");
        $this->assertEquals("1.00",$resource->amt,"It should have 1.00 for the amt");
        $this->assertEquals(".10",$resource->tax_amt,"It should have .10 for the amt");
        $this->assertEquals("Congratulations! You're getting $1.00 off of your order!",$resource->user_message);

        $promo_amount_map_resource->fixed_amount_off = 'nullit';
        $promo_amount_map_resource->save();
        $pam_record = $promo_amount_map_resource->_adapter->getRecord(['id'=>$promo_amount_map_resource->id]);
       // $this->assertNull($pam_record['fixed_amount_off'],"shoudl now be null");

        // add an extra item to make sure bundle price is accurate for percent and fixed price
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours',4);

        $promo_controller = new PromoController($m,$user,$r,5);
        $promo_controller->setPromoMessagesFromPromoId($promo_id);
        $promo_controller->setTaxRates($merchant_id);
        $resource = $promo_controller->processPromoTypeFive($order_data,$promo);

        $this->assertEquals("true",$resource->complete_promo,"It should have had complete promo");
        $this->assertEquals(5,$resource->promo_type,"It should be of type 5");
        $this->assertEquals("2.25",$resource->amt,"It should have 2.25 for the amt");
        $this->assertEquals(".225",$resource->tax_amt,"It should have .225 for the amt");
        $this->assertEquals("Congratulations! You're getting $2.25 off of your order!",$resource->user_message);


        $promo_amount_map_resource->percent_off = 'nullit';
        $promo_amount_map_resource->save();
        $pam_record = $promo_amount_map_resource->_adapter->getRecord(['id'=>$promo_amount_map_resource->id]);
        // $this->assertNull($pam_record['fixed_amount_off'],"shoudl now be null");

        $promo_controller = new PromoController($m,$user,$r,5);
        $promo_controller->setPromoMessagesFromPromoId($promo_id);
        $promo_controller->setTaxRates($merchant_id);
        $resource = $promo_controller->processPromoTypeFive($order_data,$promo);

        $this->assertEquals("true",$resource->complete_promo,"It should have had complete promo");
        $this->assertEquals(5,$resource->promo_type,"It should be of type 5");
        $this->assertEquals("3.75",$resource->amt,"It should have 2.25 for the amt");
        $this->assertEquals(".375",$resource->tax_amt,"It should have .225 for the amt");
        $this->assertEquals("Congratulations! You're getting $3.75 off of your order!",$resource->user_message);


        $promo_amount_map_resource->fixed_amount_off = 1.00;
        $promo_amount_map_resource->save();




        $data['merchant_id'] = $merchant_id;
        $data['promo_id'] = $promo_id;
        $data['key_word'] = $type5_promo_key_word;
        return $data;


        // now try double
//        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours',10);
//        $promo_controller = new PromoController($m,$user,$r,5);
//        $promo_controller->setPromoMessagesFromPromoId($promo_id);
//        $promo_controller->setTaxRates($merchant_id);
//        $resource = $promo_controller->processPromoTypeFive($order_data,$promo);
//
//        $this->assertEquals("true",$resource->complete_promo,"It should have had complete promo");
//        $this->assertEquals(5,$resource->promo_type,"It should be of type 5");
//        $this->assertEquals("2.00",$resource->amt,"It should have 2.00 for the amt");
//        $this->assertEquals(".20",$resource->tax_amt,"It should have .20 for the tax amt");
//        $this->assertEquals("Congratulations! You're getting $2.00 off of your order!",$resource->user_message);
    }

    /**
     * @depends testProcessPromoTypeFive
     */
    function testFullProcessPromoTypeFive($data)
    {
        $promo_id = $data['promo_id'];
        $merchant_id = $data['merchant_id'];
        $type5_promo_key_word = $data['key_word'];
        $promo = PromoAdapter::staticGetRecordByPrimaryKey($promo_id,'PromoAdapter');

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'pickup','the note',2);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $ucid = $checkout_resource->ucid;

        $url = "/app2/apiv2/carts/$ucid?promo_code=$type5_promo_key_word";
        $request = createRequestObject($url,'GET');
        $place_order_controller = new PlaceOrderController(getM(),$user,$request);
        $promo_response_resource = $place_order_controller->processV2Request();
        $this->assertNull($promo_response_resource->error);
        $this->assertEquals("You have not completed your promo! Here's the deal, order a Entre and Entre and Entre, and you'll get a discount!",$promo_response_resource->user_message);
        $this->assertEquals(0.00,$promo_response_resource->promo_amt);

    }


    /**
     * @depends testProcessPromoTypeFive
     */
    function testLooping($data)
    {
        $merchant_id = $data['merchant_id'];
        $promo_id = $data['promo_id'];
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $promo_resource = SplickitController::getResourceFromId($promo_id,'Promo');
        $promo = $promo_resource->getDataFieldsReally();
        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours',6);
        $promo_controller = new PromoController(getM(),$user,null,5);
        $promo_controller->setPromoMessagesFromPromoId($promo_id);
        $promo_controller->setTaxRates($merchant_id);
        $resource = $promo_controller->processPromoTypeFive($order_data,$promo);

        $this->assertEquals("true",$resource->complete_promo,"It should have had complete promo");
        $this->assertEquals(5,$resource->promo_type,"It should be of type 5");
        $this->assertEquals("1.00",$resource->amt,"It should have 1.00 for the amt");
        $this->assertEquals(".10",$resource->tax_amt,"It should have .10 for the tax amt");
        $this->assertEquals("Congratulations! You're getting $1.00 off of your order!",$resource->user_message);


        $promo_resource->allow_multiple_use_per_order = 1;
        $promo_resource->save();

        $promo2 = PromoAdapter::staticGetRecordByPrimaryKey($promo_id,'PromoAdapter');

        $resource2 = $promo_controller->processPromoTypeFive($order_data,$promo2);
        $this->assertEquals("2.00",$resource2->amt,"It should have 2.00 for the amt");
        $this->assertEquals(".20",$resource2->tax_amt,"It should have .20 for the tax amt");
        $this->assertEquals("Congratulations! You're getting $2.00 off of your order!",$resource2->user_message);
    }


    function testGetAmountOffItemOfType4promo()
	{
		$promo_controller = new PromoController($m,$user,$r,5);
		$pa = new PromoType4ItemAmountMapsAdapter($m);
		$promo_record = $pa->getRecord(array("promo_id"=>$this->ids['promo_id_type_4']));
		$amt = $promo_controller->getAmountOffOfItemForType4Promo($promo_record,2.50);
		$this->assertEquals(1.00,$amt,"It should have resulted in 1.00 off");


		$promo_record['fixed_amount_off'] = 0.00;
		$amt = $promo_controller->getAmountOffOfItemForType4Promo($promo_record,5.00);
		$this->assertEquals(2.00,$amt,"It should have resulted in 2.00 off");

		$promo_record['percent_off'] = 0;
		$amt = $promo_controller->getAmountOffOfItemForType4Promo($promo_record,2.50);
		$this->assertEquals(1.75,$amt,"It should have resulted in 1.75 off");
	}

    function testProcessPromoTypeFour()
    {
        setContext("com.splickit.pitapit");
        $merchant_id = $this->ids['merchant_id'];
        $promo_id = $this->ids['promo_id_type_4'];
        $promo = PromoAdapter::staticGetRecordByPrimaryKey($promo_id,'PromoAdapter');

        $options[TONIC_FIND_BY_METADATA]['promo_id'] = $promo_id;
        $promo_type4_item_map_resource = Resource::find(new PromoType4ItemAmountMapsAdapter(getM()),null,$options);
        $promo_type4_item_map_resource->qualifying_amt = 7.77;
        $promo_type4_item_map_resource->save();


        $user = logTestUserIn($this->ids['user_id']);
        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours',2);
        $promo_controller = new PromoController(getM(),$user,null,5);
        $promo_controller->setPromoMessagesFromPromoId($promo_id);
        $resource = $promo_controller->processPromoTypeFour($order_data,$promo);

        //check for minimum
        $expected = $promo_controller->getPromoMinumimNotMetMessage(7.77);
        $this->assertEquals($expected, $resource->user_message,"It should have the promo minimum not met message");

        //now reset minimum
        $promo_type4_item_map_resource->qualifying_amt = 0.01;
        $promo_type4_item_map_resource->save();

        $resource = $promo_controller->processPromoTypeFour($order_data,$promo);
        $this->assertEquals("true",$resource->complete_promo,"It should have had complete promo");
        $this->assertEquals(4,$resource->promo_type,"It should be of type 4");
        $this->assertEquals("1.00",$resource->amt,"It should have 1.00 for the amt");
        $this->assertEquals("Congratulations! You're getting $1.00 off of your Test Item 2!",$resource->user_message);
    }

    function testProcessPromoTypeFourMultiple()
    {
        setContext("com.splickit.pitapit");
        $merchant_id = $this->ids['merchant_id'];
        $promo_id = $this->ids['promo_id_type_4'];
        $promo_resource = SplickitController::getResourceFromId($promo_id,'Promo');
        $promo_resource->allow_multiple_use_per_order = 1;
        $promo_resource->save();

        $promo = PromoAdapter::staticGetRecordByPrimaryKey($promo_id,'PromoAdapter');
        $user = logTestUserIn($this->ids['user_id']);
        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours',2);
        $order_data['items'][] = $order_data['items'][1];
        $promo_controller = new PromoController(getM(),$user,null,5);
        $promo_controller->setTaxRates($merchant_id);
        $promo_controller->setPromoMessagesFromPromoId($promo_id);
        $resource = $promo_controller->processPromoTypeFour($order_data,$promo);

        $this->assertEquals("true",$resource->complete_promo,"It should have had complete promo");
        $this->assertEquals(4,$resource->promo_type,"It should be of type 4");
        $this->assertEquals("2.00",$resource->amt,"It should have 2.00 for the amt");
        $this->assertEquals("Congratulations! You're getting $2.00 off of your Test Item 2!",$resource->user_message);
    }

	function testMessageOfItemNotAddedYet()
	{
		setContext("com.splickit.pitapit");
		$merchant_id = $this->ids['merchant_id'];
		$promo_id = $this->ids['promo_id_type_4'];
		$promo_merchant_map_id = $this->ids['promo_merchant_map_id_type_4'];
		$user = logTestUserIn($this->ids['user_id']);
		$order_adapter = new OrderAdapter(getM());
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours',1);
		$order_data['promo_code'] = 'Test Promo Four';

		$json_encoded_data = json_encode($order_data);
		$request = new Request();
		$request->url = '/app2/apiv2/cart/checkout';
		$request->method = "post";
		$request->body = $json_encoded_data;
		$request->mimetype = 'application/json';
		$request->_parseRequestBody();
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$promo_resource_result = $place_order_controller->processV2Request();
		$this->assertNull($promo_resource_result->error);
		$this->assertEquals("You have not completed your promo! Here's the deal, order a Test Item 2, and you'll get a discount!", $promo_resource_result->user_message);
	}

	function testType4PromoFixedAmountOff()
	{
		setContext("com.splickit.pitapit");
		$merchant_id = $this->ids['merchant_id'];
		$promo_id = $this->ids['promo_id_type_4'];
		$promo_merchant_map_id = $this->ids['promo_merchant_map_id_type_4'];
		$user = logTestUserIn($this->ids['user_id']);
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours',1);
		$order_data['promo_code'] = 'Test Promo Four';
        $json_encoded_data = json_encode($order_data);
        $request = createRequestObject('/app2/apiv2/cart','POST',$json_encoded_data,'application/json');

        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$promo_resource_result = $place_order_controller->processV2Request();
        $cart_ucid = $promo_resource_result->ucid;

        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours',2);
        //$order_data['promo_code'] = 'Test Promo Four';
        $json_encoded_data = json_encode($order_data);
        $request = createRequestObject("/app2/apiv2/cart/$cart_ucid/checkout",'POST',$json_encoded_data,'application/json');


        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $promo_resource_result = $place_order_controller->processV2Request();


        $this->assertNull($promo_resource_result->error);
		$this->assertEquals("Congratulations! You're getting $1.00 off of your Test Item 2!", $promo_resource_result->user_message);
        $this->assertEquals("-1.00",$promo_resource_result->promo_amt,"it should have the promo amount");
		return $promo_resource_result;
	}

	/**
	 * @depends testType4PromoFixedAmountOff
	 */
	function testPlacePromoType4Order($new_checkout_data_resource)
	{
		// now place the order
		$user = logTestUserIn($this->ids['user_id']);
		$order_data = array();
		$order_data['note'] = "the new cart note";
		$order_data['tip'] = 0.00;
		$payment_array = $new_checkout_data_resource->accepted_payment_types;
		$order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
		$lead_times_array = $new_checkout_data_resource->lead_times_array;
		$order_data['actual_pickup_time'] = $lead_times_array[0];

		$json_encoded_data = json_encode($order_data);
        $request = createRequestObject('/apiv2/orders/'.$new_checkout_data_resource->ucid,'POST',$json_encoded_data,"application/json");
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$order_resource = $place_order_controller->processV2Request();
		$this->assertNull($order_resource->error);
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000,"should have created a valid order id");
		$this->assertEquals(-1.00,$order_resource->promo_amt);

        //validate promo was added to the user map record
        $record = getStaticRecord(array("user_id" => $user['user_id']), 'PromoUserMapAdapter');
        $this->assertNotNull($record);
        $this->assertEquals($this->ids['promo_id_type_4'],$record['promo_id'],"It should have the correct promo id on the record");
        $this->assertEquals(1, $record['times_used'], 'should show times used as 1');
        $this->assertEquals(100, $record['times_allowed'], 'should show times used as 100 since thats what is in the promo record');


	}

	function testType4PromoPercentAmountOff()
	{
		setContext("com.splickit.pitapit");
		$merchant_id = $this->ids['merchant_id'];
		$promo_id = $this->ids['promo_id_type_4'];
		$options[TONIC_FIND_BY_METADATA]['promo_id'] = $promo_id;
		$promo_type4_item_amount_resouce = Resource::find(new PromoType4ItemAmountMapsAdapter($m),null,$options);
		$promo_type4_item_amount_resouce->fixed_amount_off = 0.00;
		$promo_type4_item_amount_resouce->save();

		$user = logTestUserIn($this->ids['user_id']);
		$order_adapter = new OrderAdapter(getM());
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours',2);
		$order_data['promo_code'] = 'Test Promo Four';

		$json_encoded_data = json_encode($order_data);
		$request = new Request();
		$request->url = '/app2/apiv2/cart/checkout';
		$request->method = "post";
		$request->body = $json_encoded_data;
		$request->mimetype = 'application/json';
		$request->_parseRequestBody();
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$promo_resource_result = $place_order_controller->processV2Request();
		$this->assertNull($promo_resource_result->error);
		$this->assertEquals("Congratulations! You're getting $0.60 off of your Test Item 2!", $promo_resource_result->user_message);
	}

	function testType4PromoFixedPrice()
	{
		setContext("com.splickit.pitapit");
		$merchant_id = $this->ids['merchant_id'];
		$promo_id = $this->ids['promo_id_type_4'];
		$options[TONIC_FIND_BY_METADATA]['promo_id'] = $promo_id;
		$promo_type4_item_amount_resouce = Resource::find(new PromoType4ItemAmountMapsAdapter($m),null,$options);
		$promo_type4_item_amount_resouce->percent_off = 0;
        //$promo_type4_item_amount_resouce->fixed_price = .10;
        $promo_type4_item_amount_resouce->fixed_price = 10.00;
        $promo_type4_item_amount_resouce->save();

		$user = logTestUserIn($this->ids['user_id']);
		$order_adapter = new OrderAdapter(getM());
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours',2);
		$order_data['promo_code'] = 'Test Promo Four';

		$checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
		$this->assertNull($checkout_resource->error);
        $this->assertEquals(0.00,$checkout_resource->promo_amt);
        $this->assertContains('You have not completed your promo',$checkout_resource->user_message);

        $ucid = $checkout_resource->ucid;
        $promo_type4_item_amount_resouce->fixed_price = .10;
        $promo_type4_item_amount_resouce->save();

        $checkout_resource2 = getCheckoutResourceFromCartUcid($ucid,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource2->error);

//		$json_encoded_data = json_encode($order_data);
//		$request = new Request();
//		$request->url = '/app2/apiv2/cart/checkout';
//		$request->method = "post";
//		$request->body = $json_encoded_data;
//		$request->mimetype = 'application/json';
//		$request->_parseRequestBody();
//		$place_order_controller = new PlaceOrderController($mt, $user, $request);
//		$promo_resource_result = $place_order_controller->processV2Request();
//		$this->assertNull($promo_resource_result->error);
		$this->assertEquals("Congratulations! You're getting $1.40 off of your Test Item 2!", $checkout_resource2->user_message);
	}

	function testPromoType1WithCart()
	{
		setContext("com.splickit.pitapit");
		$user_resource = createNewUserWithCCNoCVV();
		$user = logTestUserResourceIn($user_resource);

		$order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id2']);

		$json_encoded_data = json_encode($order_data);
		$request = new Request();
		$request->url = '/app2/apiv2/cart';
		$request->method = "post";
		$request->body = $json_encoded_data;
		$request->mimetype = 'application/json';
		$request->_parseRequestBody();
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$cart_resource = $place_order_controller->processV2Request();
		$this->assertNull($cart_resource->error);
		$ucid = $cart_resource->ucid;

		// now enter promo
		$duplicate_promo_key_word = $this->ids['duplicate_promo_key_word'];
        $request = createRequestObject("/app2/apiv2/cart/$ucid/checkout?promo_code=$duplicate_promo_key_word","GET");
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$promo_resource_result = $place_order_controller->processV2Request();
		$this->assertNull($promo_resource_result->error);
		$this->assertEquals("-1.50", $promo_resource_result->promo_amt);
		$this->assertEquals("Congratulations! You're getting $1.50 off your order!", $promo_resource_result->user_message);
		$this->assertEquals($cart_resource->oid_test_only,$promo_resource_result->oid_test_only,"order id's should be the same for before and after promo applied.");
		$order_data_after_promo = CompleteOrder::getBaseOrderData($cart_resource->oid_test_only);
		$this->assertEquals(strtolower($duplicate_promo_key_word),strtolower($order_data_after_promo['promo_code']));
		$this->assertEquals(202,$order_data_after_promo['promo_id']);


		//now add more to cart
		$json_encoded_data = json_encode($order_data);
		$request = new Request();
		$request->url = "/app2/apiv2/cart/$ucid";
		$request->method = "post";
		$request->body = $json_encoded_data;
		$request->mimetype = 'application/json';
		$request->_parseRequestBody();
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$new_cart_resource = $place_order_controller->processV2Request();
		$this->assertNull($new_cart_resource->error);
        $this->assertEquals("Congratulations! You're getting $3.00 off your order!", $new_cart_resource->user_message);

		// should promo stuff get updated on add to cart or only on checkout?????????
		$new_cart_order_resource = SplickitController::getResourceFromId($new_cart_resource->oid_test_only, 'Order');
		$complete_order = CompleteOrder::staticGetCompleteOrder($new_cart_resource->oid_test_only);
		$this->assertEquals($order_data_after_promo['order_id'],$new_cart_order_resource->order_id,"order id's should have stayed the same");
		$this->assertEquals(strtolower($duplicate_promo_key_word),strtolower($new_cart_order_resource->promo_code));
		$this->assertEquals(-3.00,$new_cart_order_resource->promo_amt);


		$request = new Request();
		$request->url = "/apiv2/cart/$ucid/checkout";
		$request->method = "get";
		$request->mimetype = 'application/json';

		$placeorder_controller = new PlaceOrderController($mt, $user, $request);
		$placeorder_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$new_checkout_data_resource = $placeorder_controller->processV2Request();
		$this->assertNull($new_checkout_data_resource->error);
		$this->assertEquals("-3.00", $new_checkout_data_resource->promo_amt);
        $this->assertEquals("3.00",$new_checkout_data_resource->discount_amt);
		//$this->assertEquals("Congratulations! You're getting $3.00 off your order!", $new_checkout_data_resource->user_message);

		// now place the order
		$order_data = array();
		$order_data['merchant_id'] = $this->ids['merchant_id2'];
		$order_data['note'] = "the new cart note";
		$order_data['user_id'] = $user['user_id'];
		$order_data['cart_ucid'] = $new_cart_resource->ucid;
		$order_data['tip'] = (rand(100, 1000))/100;
		$payment_array = $new_checkout_data_resource->accepted_payment_types;
		$order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
		$lead_times_array = $new_checkout_data_resource->lead_times_array;
		$order_data['actual_pickup_time'] = $lead_times_array[0];
		// this should be ignored;
		$order_data['lead_time'] = 1000000;

		$json_encoded_data = json_encode($order_data);
		$request = new Request();
		$request->url = '/apiv2/orders';
		$request->method = "post";
		$request->body = $json_encoded_data;
		$request->mimetype = 'application/json';
		$request->_parseRequestBody();
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
		$order_resource = $place_order_controller->processV2Request();
		$this->assertNull($order_resource->error);
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000,"should have created a valid order id");
		$this->assertEquals(-3.00,$order_resource->promo_amt);



	}
    
    function testPromoType1()
    {
    	setContext("com.splickit.pitapit");
    	$merchant_id = $this->ids['merchant_id2'];
    	$user = logTestUserIn($this->ids['user_id']);
    	$promo_id = 202;
    	$promo_merchant_map_id = $this->ids['promo_merchant_map_id_type_1_alternate'];
    	
    	$order_adapter = new OrderAdapter($mimetypes);
 		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours');
		$order_data['tip'] = 0.00;
 		$duplicate_promo_key_word = $this->ids['duplicate_promo_key_word'];
 		$order_data['promo_code'] = "$duplicate_promo_key_word";
 		
 		$json_encoded_data = json_encode($order_data);
    	$request = new Request();
    	$request->url = '/app2/apiv2/cart/checkout';
    	$request->method = "post";
    	$request->body = $json_encoded_data;
    	$request->mimetype = 'application/json';
    	$request->_parseRequestBody();    	
    	$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$promo_resource_result = $place_order_controller->processV2Request();
    	$this->assertNull($promo_resource_result->error);
    	$this->assertEquals("-1.50", $promo_resource_result->promo_amt);
    	$this->assertEquals("Congratulations! You're getting $1.50 off your order!", $promo_resource_result->user_message);
        return $promo_resource_result->cart_ucid;
    }

    /**
     * @depends testPromoType1
     */
    function testPromoValueInCheckoutdData($cart_ucid)
    {
        setContext("com.splickit.pitapit");
        $user = logTestUserIn($this->ids['user_id']);
        $request = createRequestObject("/app2/apiv2/cart/$cart_ucid/checkout","GET");
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $checkout_resource_result = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource_result->error);
        $order_summary = $checkout_resource_result->order_summary;
        $receipt_items = $order_summary['receipt_items'];
        $better_map = createHashmapFromArrayOfArraysByFieldName($receipt_items,'title');
        $total_row = $better_map['Total'];
        $this->assertEquals('$0.00',$better_map['Total']['amount']);

    }


	
    function testCheckPromoType2()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$promo_id = $this->ids['promo_id'];
    	$promo_merchant_map_id = $this->ids['promo_merchant_map_id'];
    	
    	$user = logTestUserIn($this->ids['user_id']);
    	
    	$yesterday = time()-86400;
    	$tomorrow = time()+86400;
    	$yesterday_date = date('Y-m-d',$yesterday);
    	$tomorrow_date = date('Y-m-d',$tomorrow);
    	$wayout_end_date = "2020-01-01";
    	$longago_start_date = "2010-01-01";
    	
    	// first zero out promo dates so promo is active
    	$promo_adapter = new PromoAdapter($mimetypes);
    	$promo_resource = Resource::find($promo_adapter,$promo_id);
    	$promo_resource->start_date = $longago_start_date;
    	$promo_resource->end_date = $wayout_end_date;
    	$promo_resource->save();
    	
    	$promo_merchant_map_adapter = new PromoMerchantMapAdapter($mimetypes);
    	$pmm_resource = Resource::find($promo_merchant_map_adapter,"$promo_merchant_map_id");
 		$pmm_resource->start_date = "nullit";
 		$pmm_resource->end_date = "nullit";
 		$pmm_resource->save();
    	    	
    	// first we test using the promo dates

 		$order_adapter = new OrderAdapter($mimetypes);
 		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours');
 		$order_data['promo_code'] = 'Test Promo';
 		
    	$json_encoded_data = json_encode($order_data);
    	
    	$bad_merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$bad_merchant_order_data = $order_adapter->getSimpleOrderArrayByMerchantId($bad_merchant_resource->merchant_id, 'pickup', 'skip_hours');
    	$bad_merchant_order_data['promo_code'] = 'Test Promo';
    	$json_bad_merchant_id_encoded_data = json_encode($bad_merchant_order_data);
    	
    	$order_data['merchant_id'] = $merchant_id;
    	$order_data['promo_code'] = 'stupid';
    	$json_bad_promo_code_encoded_data = json_encode($order_data);
    	
    	// test bad promo code
 		$request = new Request();
    	$request->url = '/app2/apiv2/cart/checkout';
    	$request->method = "post";
    	$request->body = $json_bad_promo_code_encoded_data;
    	$request->mimetype = 'application/json';
    	$request->_parseRequestBody();    	
    	$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$promo_resource_result = $place_order_controller->processV2Request();
		$this->assertEquals("Sorry!  The promo code you entered, stupid, is not valid.",$promo_resource_result->error);
		$this->assertEquals("promo",$promo_resource_result->error_type);

		$response = getV2ResponseWithJsonFromResource($promo_resource_result, $headers);

		//test bad merchant id
 		$request = new Request();
    	$request->url = '/app2/apiv2/cart/checkout';
    	$request->method = "post";
    	$request->body = $json_bad_merchant_id_encoded_data;
    	$request->mimetype = 'application/json';
    	$request->_parseRequestBody();    	
    	$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$promo_resource_result = $place_order_controller->processV2Request();
    	$this->assertEquals("So sorry, this promo is not valid at this location.",$promo_resource_result->error);
		$this->assertEquals("promo",$promo_resource_result->error_type);

    	// good promo lower case shoudl work
    	$order_data['merchant_id'] = $merchant_id;
    	$order_data['promo_code'] = 'test promo';

    	$json_encoded_data = json_encode($order_data);
 		$request = new Request();
    	$request->url = '/app2/apiv2/cart/checkout';
    	$request->method = "post";
    	$request->body = $json_encoded_data;
    	$request->mimetype = 'application/json';
    	$request->_parseRequestBody();    	
    	$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$promo_resource_result = $place_order_controller->processV2Request();
		$this->assertNull($promo_resource_result->error);
		$this->assertEquals("You have not completed your promo! Here's the deal, order a Test Item 2, then add a Test Item 3 to go with it, and its FREE!", $promo_resource_result->user_message);

    	// now set start equal to tomorrow
    	$promo_resource->start_date = $tomorrow_date;
    	$promo_resource->save();
		unset($request->data);
		$request->_parseRequestBody();
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$promo_resource_result = $place_order_controller->processV2Request();
		$this->assertEquals("Sorry this promotion has not started yet :(",$promo_resource_result->error);
    	    	
    	// now set end date to yesterday
    	$promo_resource->start_date = $longago_start_date;
    	$promo_resource->end_date = $yesterday_date;
    	$promo_resource->save();
		unset($request->data);
		$request->_parseRequestBody();
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$promo_resource_result = $place_order_controller->processV2Request();
    	$this->assertEquals("Sorry this promotion has expired :(",$promo_resource_result->error);
    	
    	// zero out the promo and test the merchant values
    	$promo_resource->start_date = $longago_start_date;
    	$promo_resource->end_date = $wayout_end_date;
    	$promo_resource->save();
     	    	
    	// promo expired at merchant
    	$pmm_resource->start_date = $longago_start_date;
    	$pmm_resource->end_date = $yesterday_date;
    	$pmm_resource->save();
		unset($request->data);
		$request->_parseRequestBody();
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$promo_resource_result = $place_order_controller->processV2Request();
    	$this->assertEquals("Sorry this promotion has expired at this merchant :(",$promo_resource_result->error);
    	
     	// promo has not yet started at merchant
    	$pmm_resource->start_date = $tomorrow_date;
    	$pmm_resource->end_date = $wayout_end_date;
    	$pmm_resource->save();
		unset($request->data);
		$request->_parseRequestBody();
		$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$promo_resource_result = $place_order_controller->processV2Request();
    	$this->assertEquals("Sorry this promotion has not started yet.  Promo begins on ".$tomorrow_date." :(",$promo_resource_result->error);

    }
//
//	function testDebugPromoType2FromAndroidStyleRequestAPIV1()
//	{
//
//
//		$merchant_id = $this->ids['merchant_id'];
//		$promo_id = $this->ids['promo_id'];
//		$promo_merchant_map_id = $this->ids['promo_merchant_map_id'];
//
//		$user = logTestUserIn($this->ids['user_id']);
//
//		$yesterday = time()-86400;
//		$tomorrow = time()+86400;
//		$yesterday_date = date('Y-m-d',$yesterday);
//		$tomorrow_date = date('Y-m-d',$tomorrow);
//		$wayout_end_date = "2020-01-01";
//		$longago_start_date = "2010-01-01";
//
//		// first zero out promo dates so promo is active
//		$promo_adapter = new PromoAdapter($mimetypes);
//		$promo_resource = Resource::find($promo_adapter,'200');
//		$promo_resource->start_date = $longago_start_date;
//		$promo_resource->end_date = $wayout_end_date;
//		$promo_resource->save();
//
//		$promo_merchant_map_adapter = new PromoMerchantMapAdapter($mimetypes);
//		$pmm_resource = Resource::find($promo_merchant_map_adapter,"$promo_merchant_map_id");
//		$pmm_resource->start_date = "nullit";
//		$pmm_resource->end_date = "nullit";
//		$pmm_resource->save();
//
//		$item_records = CompleteMenu::getAllMenuItemsAsArray($this->ids['menu_id'], 'Y', $mimetypes);
//		$item1_size_record = ItemSizeAdapter::staticGetRecord(array("item_id"=>$item_records[1]['item_id']),'ItemSizeAdapter');
//		$item2_size_record = ItemSizeAdapter::staticGetRecord(array("item_id"=>$item_records[2]['item_id']),'ItemSizeAdapter');
//
//
//		$json_encoded_data = '{"jsonVal":{"note":"","tax_amt":"0.69","user_id":"'.$user['user_id'].'","tip":"0.00","promo_code":"Test Promo","delivery":"no","lead_time":"15","items":[{"mods":[],"quantity":1,"size_id":"'.$item1_size_record['size_id'].'","item_id":"'.$item1_size_record['item_id'].'","sizeprice_id":"'.$item1_size_record['item_size_id'].'"},{"mods":[],"quantity":1,"size_id":"'.$item2_size_record['size_id'].'","item_id":"'.$item2_size_record['item_id'].'","sizeprice_id":"'.$item2_size_record['item_size_id'].'"}],"merchant_id":"'.$merchant_id.'","grand_total":"3.28","sub_total":"10.58","total_points_used":"0"}}';
//		$request = new Request();
//		$request->url = '/app2/phone/getcheckoutdata/';
//		$request->method = "post";
//		$request->body = $json_encoded_data;
//		$request->mimetype = 'application/json';
//		$request->_parseRequestBody();
//		$place_order_controller = new PlaceOrderController($mt, $user, $request);
//		$promo_resource_result = $place_order_controller->getCheckoutDataFromOrderRquest();
//
//	}


	function testCheckPromoWithAlternateKeyWord()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$promo_id = $this->ids['promo_id'];
    	$promo_merchant_map_id = $this->ids['promo_merchant_map_id'];
    	
    	$user = logTestUserIn($this->ids['user_id']);
    	
    	$yesterday = time()-86400;
    	$tomorrow = time()+86400;
    	$yesterday_date = date('Y-m-d',$yesterday);
    	$tomorrow_date = date('Y-m-d',$tomorrow);
    	$wayout_end_date = "2020-01-01";
    	$longago_start_date = "2010-01-01";
    	
    	// first zero out promo dates so promo is active
    	$promo_adapter = new PromoAdapter($mimetypes);
    	$promo_resource = Resource::find($promo_adapter,'200');
    	$promo_resource->start_date = $longago_start_date;
    	$promo_resource->end_date = $wayout_end_date;
    	$promo_resource->save();
    	
    	$promo_merchant_map_adapter = new PromoMerchantMapAdapter($mimetypes);
    	$pmm_resource = Resource::find($promo_merchant_map_adapter,"$promo_merchant_map_id");
 		$pmm_resource->start_date = "nullit";
 		$pmm_resource->end_date = "nullit";
 		$pmm_resource->save();
    	    	
 		$order_adapter = new OrderAdapter($mimetypes);
 		
    	// good promo
 		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
 		$order_data['promo_code'] = 'Alternatekeyword';
    	$json_encoded_data = json_encode($order_data);
    	$request = new Request();
    	$request->url = '/app2/apiv2/cart/checkout';
    	$request->method = "post";
    	$request->body = $json_encoded_data;
    	$request->mimetype = 'application/json';
    	$request->_parseRequestBody();
    	$place_order_controller = new PlaceOrderController($mt, $user, $request);
		$promo_resource_result = $place_order_controller->processV2Request();

    	//$promo_resource_result = $this->validatePromo($json_encoded_data);
    	$this->assertNull($promo_resource_result->error);
    	$expected = "You have not completed your promo! Here's the deal, order a Test Item 2, then add a Test Item 3 to go with it, and its FREE!";
    	$this->assertEquals($expected, $promo_resource_result->user_message);
 
    	// good promo qitem
 		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',2);
 		$order_data['promo_code'] = 'Alternatekeyword';
    	$json_encoded_data = json_encode($order_data);
    	$request = new Request();
    	$request->url = '/app2/apiv2/cart/checkout';
    	$request->method = "post";
    	$request->body = $json_encoded_data;
    	$request->mimetype = 'application/json';
    	$request->_parseRequestBody();    	
    	$place_order_controller = new PlaceOrderController($mt, $user, $request);
 		$promo_resource_result = $place_order_controller->processV2Request();
    	$this->assertNull($promo_resource_result->error);
    	$expected = "You have not completed your promo! Almost there, now add a Test Item 3 to this order and its FREE!";
    	$this->assertEquals($expected, $promo_resource_result->user_message);
    		
    	// good promo qitem and pitem
 		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',3);
 		$order_data['promo_code'] = 'Alternatekeyword';
    	$json_encoded_data = json_encode($order_data);
    	$request = new Request();
    	$request->url = '/app2/apiv2/cart/checkout';
    	$request->method = "post";
    	$request->body = $json_encoded_data;
    	$request->mimetype = 'application/json';
    	$request->_parseRequestBody();    	
    	$place_order_controller = new PlaceOrderController($mt, $user, $request);
 		$checkout_resource = $place_order_controller->processV2Request();
    	$this->assertNull($checkout_resource->error);
    	$expected = "Congratulations! You're getting a FREE Test Item 3!";
    	$this->assertEquals($expected, $checkout_resource->user_message);
    }

    function testReturnValuesOfTaxAndSubtotalForMerchantPays()
    {
        $user = logTestUserIn($this->ids['user_id']);
        $merchant_id = $this->ids['merchant_id'];
        $order_adapter = new OrderAdapter();
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',3);
        $order_data['promo_code'] = 'Alternatekeyword';
        $json_encoded_data = json_encode($order_data);
        $request = createRequestObject("/app2/apiv2/cart/checkout","POST",$json_encoded_data,'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);
        $expected = "Congratulations! You're getting a FREE Test Item 3!";
        $this->assertEquals($expected, $checkout_resource->user_message);
        $this->assertEquals(.30, $checkout_resource->item_tax_amt+$checkout_resource->promo_tax_amt);
        $this->assertEquals(-1.50, $checkout_resource->promo_amt);
        $this->assertEquals(4.50, $checkout_resource->order_amt);


        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$t);
    	$this->assertEquals(.30, $order_resource->item_tax_amt+$order_resource->promo_tax_amt);
    	$this->assertEquals(-1.50, $order_resource->promo_amt);
    	$this->assertEquals(4.50, $order_resource->order_amt);
    	$this->assertEquals(3.30,$order_resource->grand_total);
    	
		$balance_before = 0.00;
		$uuid = $order_data['user_id'];
    	$order_id = $order_resource->order_id;
    	$new_user_resource = getUserResourceFromId($uuid);
		$this->assertTrue($new_user_resource->_exists);
		$this->assertEquals(0.00, $new_user_resource->balance);
		$user_id = $new_user_resource->user_id;
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
		$this->assertEquals($new_user_resource->balance, $balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_after']);    	

    	return $order_data;
    }
    
    /**
     * @depends testReturnValuesOfTaxAndSubtotalForMerchantPays
     */
    
    function testReturnValuesOfTaxAndSubtotalForSplickitPays($order_data)
    {
    	$starting_user_id = $order_data['user_id'];
   		//first set promo to type 1 where splickit pays so we tax the promo stuff
   		$promo_adapter = new PromoAdapter($mimetypes);
    	$promo_resource = Resource::find($promo_adapter,'200');
    	$promo_resource->payor_merchant_user_id = 1;
    	$promo_resource->save();

    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$user_id = $user_resource->user_id;
    	$user = logTestUserIn($user_resource->user_id);
    	$order_data['user_id'] = $user['user_id'];
    	
    	$order_data['tip'] = 0.00;
     	$json_encoded_data = json_encode($order_data);
    	$request = new Request();
    	$request->url = '/app2/apiv2/cart/checkout';
    	$request->method = "post";
    	$request->body = $json_encoded_data;
    	$request->mimetype = 'application/json';
    	$request->_parseRequestBody();    	
    	$place_order_controller = new PlaceOrderController($mt, $user, $request);
 		$checkout_resource = $place_order_controller->processV2Request();
    	$this->assertEquals(.45, $checkout_resource->total_tax_amt);
    	$this->assertEquals(-1.50, $checkout_resource->promo_amt);
    	$this->assertEquals(4.50, $checkout_resource->order_amt);
    	
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertEquals(.45, $order_resource->total_tax_amt);
    	$this->assertEquals(-1.50, $order_resource->promo_amt);
    	$this->assertEquals(4.50, $order_resource->order_amt);
    	$this->assertEquals(3.45,$order_resource->grand_total);
    	
    	// validate promo_user map entry
    	$puma = new PromoUserMapAdapter($mimetypes);
    	$record = $puma->getRecord(array("user_id"=>$user_id,"promo_id"=>200), $options);
    	$this->assertNotNull($record,"should have found a promo user map record");
    	$this->assertEquals(1, $record['times_used'],"should be showing used 1 time");
		
    	$balance_before = 0.00;
    	$user_id = $order_data['user_id'];
    	$order_id = $order_resource->order_id;
    	$new_user_resource = Resource::find(new UserAdapter($mimetypes), "$user_id", $options);
		$this->assertTrue($new_user_resource->_exists);
		$this->assertEquals(0.00, $new_user_resource->balance);
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
		$this->assertEquals($new_user_resource->balance, $balance_change_rows_by_user_id["$user_id-CCpayment"]['balance_after']);    	

    	return $order_data;
    }

	function testPromoType2WithIdList()
	{
		$merchant_id = $this->ids['merchant_id'];
//		$promo_id = $this->ids['promo_id'];
//		$promo_merchant_map_id = $this->ids['promo_merchant_map_id'];

		$user_resource = createNewUserWithCCNoCVV();
		$user = logTestUserResourceIn($user_resource);


		$order_adapter = new OrderAdapter(getM());

		// good promo
		$menu = CompleteMenu::getCompleteMenu($this->ids['menu_id'],'Y',$merchant_id,2,'pickup');
		$qitem = $menu['menu_types'][0]['menu_items'][3]['size_prices'][0];
		$pitem = $menu['menu_types'][0]['menu_items'][4]['size_prices'][0];
		$order_data = $order_adapter->getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
		$order_data['items'][0]['item_id'] = $qitem['item_id'];
		$order_data['items'][0]['size_id'] = $qitem['size_id'];
		$order_data['items'][1]['item_id'] = $pitem['item_id'];
		$order_data['items'][1]['size_id'] = $pitem['size_id'];
		$order_data['items'][1]['mods'] = array();
		$order_data['items'][1]['quantity'] = 1;
		$order_data['promo_code'] = 'Alternatekeyword';
		$json_encoded_data = json_encode($order_data);
        $request = createRequestObject('/app2/apiv2/cart/checkout','post',$json_encoded_data,'application/json');
		$place_order_controller = new PlaceOrderController(getM(), $user, $request);
		$promo_resource_result = $place_order_controller->processV2Request();
		$this->assertNull($promo_resource_result->error);
		$expected = "Congratulations! You're getting a FREE Test Item 3!";
		$this->assertEquals($expected, $promo_resource_result->user_message);
	}
    
    function validatePromo($json)
    {
    	$request = new Request();
    	$request->method = "post";
    	$request->body = $json;
    	$request->mimetype = 'Applicationjson';
    	$request->_parseRequestBody();
    	$promo_controller = new PromoController($mt, $this->user, $request, 5);
    	$promo_resource_result = $promo_controller->validatePromo($od);
    	return $promo_resource_result;    	
    }
	    
    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['default_tz'] = $tz;
    	date_default_timezone_set("America/Denver");

    	SplickitCache::flushAll();
    	$db = DataBase::getInstance();
    	$mysqli = $db->getConnection();
    	$mysqli->begin_transaction(); ;
    	
    	$skin_resource = createWorldHqSkin();
    	$ids['skin_id'] = $skin_resource->skin_id;
    	
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;

    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);

    	$merchant_resource = createNewTestMerchant($menu_id);
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	$merchant_id = $merchant_resource->merchant_id;

        $billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);
        $merchant_payment_type_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);


        $user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;

        //create the type 2 promo
        $promo_data = [];
        $promo_data['promo_id'] = 200;
        $promo_data['key_word'] = "Test Promo,AlternateKeyWord";
        $promo_data['promo_type'] = 2;
        $promo_data['description'] = 'Get a free this when you purchase a large that';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['allow_multiple_use_per_order'] = true;
        $promo_data['valid_on_first_order_only'] = 'N';
        $promo_data['order_type'] = 'pickup';
        $promo_data['merchant_id'] = $merchant_id;
        $promo_data['message1'] = "Congratulations! You're getting a FREE that!";
        $promo_data['message4'] = "Almost there, now add a standard that to this order and its FREE!";
        $promo_data['message5'] = "Here's the deal, order a large this, then add a that to go with it, and its FREE! Limit 1";
        $promo_data['qualifying_amt'] = 0.00;
        $promo_data['qualifying_object_array'] = ['Item-'.$item_records[1]['item_id'],'Item-'.$item_records[3]['item_id']];
        //$promo_data['promo_item_1_array'] = 'pitem000000200,sumdumPromoItemId200';
        $promo_data['promo_item_1_array'] = ['Item-'.$item_records[2]['item_id'],'Item-'.$item_records[4]['item_id']];
        $promo_data['brand_id'] = 300;

        $request = createRequestObject("/app2/admin/promotype1",'POST',json_encode($promo_data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->createPromo();
        $ids['promo_id'] = $response->promo_id;
        $ids['promo_merchant_map_id'] = $response->merchant_id_maps[0]['map_id'];

        //create the type 1 promo
        $promo_data = [];
        $promo_data['promo_id'] = 201;
        $promo_data['key_word'] = "The Type1 Promo,type1promo";
        $promo_data['promo_type'] = 1;
        $promo_data['description'] = 'Get 25% off';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['allow_multiple_use_per_order'] = false;
        $promo_data['valid_on_first_order_only'] = 'N';
        $promo_data['order_type'] = 'pickup';
        $promo_data['merchant_id'] = $merchant_id;
        $promo_data['message1'] = "Congratulations! You're getting a 25% off your order!";
        $promo_data['qualifying_amt'] = 1.00;
        $promo_data['promo_amt'] = 0.00;
        $promo_data['percent_off'] = 25;
        $promo_data['max_amt_off'] = 50.00;
        $promo_data['brand_id'] = 300;

        $request = createRequestObject("/app2/admin/promotype1",'POST',json_encode($promo_data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->createPromo();

        $pkwm_adapter = new PromoKeyWordMapAdapter(getM());
        $promo_adapter = new PromoAdapter(getM());

		// create another promo on a differnt brand with same key word
		$menu_id2 = createTestMenuWithNnumberOfItems(1);
		$merchant_resource2 = createNewTestMerchant($menu_id2);
		$merchant_resource2->brand_id = 282;
		$merchant_resource2->save();

        $merchant_payment_type_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource2->merchant_id, 2000, $billing_entity_resource->id);


        $merchant_id2 = $merchant_resource2->merchant_id;
		$ids['merchant_id2'] = $merchant_id2;
    	
    	//create the type 1 promo
    	$duplicate_promo_key_word = "AlternateKeyWord";

        $promo_data = [];
        $promo_data['promo_id'] = 202;
        $promo_data['key_word'] = "The Type1 Promo,type1promo,$duplicate_promo_key_word";
        $promo_data['promo_type'] = 1;
        $promo_data['description'] = 'Get $10 off';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['allow_multiple_use_per_order'] = false;
        $promo_data['valid_on_first_order_only'] = 'N';
        $promo_data['order_type'] = 'pickup';
        $promo_data['merchant_id'] = $merchant_id2;
        $promo_data['message1'] = "Congratulations! You're getting $%%amt%% off your order!";
        $promo_data['qualifying_amt'] = 1.00;
        $promo_data['promo_amt'] = 10.00;
        $promo_data['percent_off'] = 0;
        $promo_data['max_amt_off'] = 50.00;
        $promo_data['brand_id'] = 282;

        $request = createRequestObject("/app2/admin/promotype1",'POST',json_encode($promo_data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->createPromo();

        $ids['duplicate_promo_key_word'] = $duplicate_promo_key_word;


/*      //maybe choose object
        - Any Entre
        - Menu Type
        - Size
        - Item
        - Item at a size


*/
        //create the type 4 promo
        $promo_data = [];
        $promo_data['key_word'] = 'Test Promo Four';
        $promo_data['promo_type'] = 4;
        $promo_data['description'] = 'Get $ off your item';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['merchant_id'] = $merchant_id;
        $promo_data['message5'] = "Here's the deal, order a large this, and you'll get a discount! Limit 1";
        $promo_data['qualifying_object_array'] = ['Item-'.$item_records[1]['item_id']];
        $promo_data['fixed_amount_off'] = 1.00;
        $promo_data['percent_off'] = 40;
        $promo_data['fixed_price'] = .75;
        $promo_data['brand_id'] = 300;

        $request = createRequestObject("/app2/admin/promotype1",'POST',json_encode($promo_data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->createPromo();

        $promo_id = $response->promo_id;
        $ids['promo_id_type_4'] = $promo_id;

        // create the type5 promo
        $promo_data = [];
        $promo_data['key_word'] = 'Test Promo Five';
        $promo_data['promo_type'] = 5;
        $promo_data['description'] = 'Get $ off when ordered together';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['merchant_id'] = $merchant_id;
        $promo_data['message5'] = "Here's the deal, order a this and a that, and you'll get a discount!";
        //$promo_data['qualifying_object'] = 'qitem000000200,sumdumExternalId200';
        $promo_data['qualifying_object_array'] = ['Entre','Entre','Entre'];
        $promo_data['fixed_amount_off'] = 1.00;
        $promo_data['percent_off'] = 40;
        $promo_data['fixed_price'] = .75;
        $promo_data['brand_id'] = 300;

        $request = createRequestObject("/app2/admin/promotype1",'POST',json_encode($promo_data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response_five = $promo_controller->createPromo();
        $ids['promo_id_type_5'] = $response_five->promo_id;

        // add a key word for a different brand
        $type5_promo_key_word = "type_five";
        $ids['duplicate_promo_key_word'] = $duplicate_promo_key_word;
        $pkwm_adapter = new PromoKeyWordMapAdapter($mimetypes);
        Resource::createByData($pkwm_adapter, array("promo_id"=>$response_five->promo_id,"promo_key_word"=>"$type5_promo_key_word","brand_id"=>282));
        $ids['promo_type_5_key_word'] = $type5_promo_key_word;


        //assign promo ids
//    	$item1_resource = SplickitController::getResourceFromId($item_records[1]['item_id'], "Item");
//    	$item1_resource->promo_tag = "qitem000000200";
//    	$item1_resource->save();
//		$item2_resource = SplickitController::getResourceFromId($item_records[2]['item_id'], "Item");
//		$item2_resource->promo_tag = "pitem000000200";
//		$item2_resource->save();
//		$item3_resource = SplickitController::getResourceFromId($item_records[3]['item_id'], "Item");
//		$item3_resource->promo_tag = "sumdumExternalId200";
//		$item3_resource->save();
//		$item4_resource = SplickitController::getResourceFromId($item_records[4]['item_id'], "Item");
//		$item4_resource->promo_tag = "sumdumPromoItemId200";
//		$item4_resource->save();

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
    PromoTestV2::main();
}

?>