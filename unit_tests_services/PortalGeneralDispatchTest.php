<?php

error_reporting(E_ERROR | E_COMPILE_ERROR | E_COMPILE_WARNING | E_PARSE);
$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');
$db_info->database = 'smaw_unittest';
$db_info->username = 'root';
$db_info->password = 'splickit';
if (isset($_SERVER['XDEBUG_CONFIG'])) {
    putenv("SMAW_ENV=unit_test_ide");
    $db_info->hostname = "127.0.0.1";
    $db_info->port = 13306;
} else {
    $db_info->hostname = "db_container";
    $db_info->port = 3306;
}
$_SERVER['DB_INFO'] = $db_info;
require_once 'lib/curl_objects/splickitcurl.php';
require_once 'lib/mocks/viopaymentcurl.php';
require_once 'lib/utilities/functions.inc';
require_once 'lib/utilities/unit_test_functions.inc';


class PortalGeneralDispatchTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;
    var $info;
    var $api_port = "80";
    var $menu_id;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        //$_SERVER['DO_NOT_RUN_CC'] = true;
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        if (isset($_SERVER['XDEBUG_CONFIG'])) {
            $this->api_port = "10080";
        }
    }

    function tearDown()
    {
        //delete your instance
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
        unset($this->info);
    }

    function testCreateMerchantAccount()
    {
        setContext('com.splickit.worldhq');
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $merchant_resource = createNewTestMerchant($menu_id,['no_payment'=>true]);





        $data["first_name"] = "Chris";
        $data["last_name"] = "Terry";
        $data["date_of_birth"] = "1970-1-23";
        $data["social_security_number"] = "555-55-5555";
        $data["phone"] = "555-555-5555";
        $data["business_legal_name"] = "Booming Biz";  // business name
        $data["doing_business_as"] = "sum dum name";
        $data["ein"] = "444-44-4444"; // federal tax id
        $data["bank_account_account_number"] = "55555555555";
        $data["bank_account_routing_number"] = "011401533";
        $data["merchant_id"] = $merchant_resource->merchant_id;

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/payments/merchant_account";
        $response = $this->makeRequest($url,null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];
        $this->assertTrue(isset($response_data['vio_credit_card_processor_id']),'It should have a credit card processor id');
        $this->assertTrue(isset($response_data['merchant_payment_type_map']),'It should have a merchant_payment_type_map' );


    }

    function testFlushAll()
    {
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/flushcache";
        $response = $this->makeRequest($url,null);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);

        $data = $response_array['data'];
        $this->assertEquals('Entire Cache',$data['resource']);

    }

    function testCacheBust()
    {
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/brands/101/flushcache";
        $response = $this->makeRequest($url,null);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);

        $data = $response_array['data'];
        $this->assertEquals('brand-101',$data['resource']);


        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/skins/252/flushcache";
        $response = $this->makeRequest($url,null);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);

        $data = $response_array['data'];
        $this->assertEquals('com.splickit.vtwoapi',$data['resource']);

    }

    function testCreatePublicKey()
    {
        //$skin_adapter = new SkinAdapter();

        $string = generateAlphaCode(3);
        $skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty('pubkeyskin' . $string, 'pubkeybrand' . $string, null, null);
        $skin_resource->public_client_id = '';
        $skin_resource->save();
        $skin_id = $skin_resource->skin_id;
        $skin_resource = Resource::find(new SkinAdapter(getM()),"$skin_id");
        $this->assertEquals('',$skin_resource->public_client_id);
        $brand_id = $skin_resource->brand_id;

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/brands/$brand_id/createpublickey";
        $response = $this->makeRequest($url, null, 'POST', ['skin_id'=>$skin_id]);
        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $this->assertEquals(200,$this->info['http_code']);

        $skin_resource_after = Resource::find(new SkinAdapter(getM()),"$skin_id");
        $this->assertNotEquals('',$skin_resource_after->public_client_id);
        $public_client_id = $skin_resource_after->public_client_id;

        $response = $this->makeRequest($url, null, 'POST', ['skin_id'=>$skin_id]);
        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $this->assertEquals(422,$this->info['http_code']);
        $this->assertEquals(BrandController::PUBLIC_KEY_ALREADY_EXISTS_ERROR,$response_array['error']['error_message']);



    }

    function testLoyaltyResetToPrimary()
    {
        $string = generateAlphaCode(3);
        $skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty('loydupskin' . $string, 'loydupbrand' . $string, null, null);
        $brand_id = $skin_resource->brand_id;
        setContext($skin_resource->external_identifier);
        $contact_no = '' . rand(1111111111, 9999999999);
        $user_resource = createNewUser(array("contact_no" => $contact_no));
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m, $user, $r, 5);
        $user_session = $user_session_controller->getUserSession($user_resource);

        $user_resource2 = createNewUser(array("contact_no" => $contact_no));
        $user2 = logTestUserResourceIn($user_resource2);
        $user_session_controller = new UsersessionController($m, $user2, $r, 5);
        $user_session = $user_session_controller->getUserSession($user_resource2);

        $user_resource3 = createNewUser(array("contact_no" => $contact_no));
        $user3 = logTestUserResourceIn($user_resource3);
        $user_session_controller = new UsersessionController($m, $user3, $r, 5);
        $user_session = $user_session_controller->getUserSession($user_resource3);

        $ubpma = new UserBrandPointsMapAdapter(getM());
        $records = $ubpma->getRecords(["brand_id" => $brand_id]);
        $this->assertCount(3, $records);
        foreach ($records as $record) {
            $loyalty_number = $record['loyalty_number'];
            if (strlen($loyalty_number) == 10) {
                $this->assertNull($current_primary_user_id, "there should only be one 10 digit number");
                $current_primary_user_id = $record['user_id'];
            } else {
                $non_primary_ids[] = $record['user_id'];
            }
            $this->assertEquals($contact_no, substr($loyalty_number, 0, 10), "they should all have the same base number");
        }

        // switch the primary account
        $new_primary_user_id = $non_primary_ids[0];
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/brands/$brand_id/loyalty/users/$new_primary_user_id/setasprimaryaccount";
        $data['user_id'] = $new_primary_user_id;

//        $user_record = getUserFromId($new_primary_user_id);
//        $loyalty_factory = new LoyaltyControllerFactory();
//        $loyalty_factory->brand_id = $brand_id;
//        $loyalty_controller = $loyalty_factory->getLoyaltyControllerFromSkinName($skin_resource->skin_name,$user_record);
//        $loyalty_controller->setCurrentUserAsPrimaryAccount();


        $response = $this->makeRequest($url, null, 'POST', $data);

        $records = $ubpma->getRecords(["brand_id" => $brand_id]);
        $this->assertCount(3, $records);
        foreach ($records as $record) {
            $loyalty_number = $record['loyalty_number'];
            if (strlen($loyalty_number) == 10) {
                $this->assertNull($actual_new_primary_user_id, "there should only be one 10 digit number");
                $actual_new_primary_user_id = $record['user_id'];
            } else {
                $new_non_primary_ids[] = $record['user_id'];
            }
            $this->assertEquals($contact_no, substr($loyalty_number, 0, 10), "they should all have the same base number");
        }


        $this->assertEquals($new_primary_user_id, $actual_new_primary_user_id, 'It should have switched the primary account');

    }

    function testCreateFreeDeliveryPromo()
    {
        setContext('com.splickit.vtwoapi');
        $menu_id = createTestMenuWithNnumberOfItems(1);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_resource->delivery = 'Y';
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, null);
        $billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);
        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);


        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $menu_id, 'delivery');
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $menu_id, 'pickup');

        $data = array("merchant_id" => $merchant_resource->merchant_id);

        // set merchant delivery info
        $mdia = new MerchantDeliveryInfoAdapter(getM());
        $mdia_resource = $mdia->getExactResourceFromData($data);
        $mdia_resource->minimum_order = 1.00;
        $mdia_resource->save();

        $mdpd = new MerchantDeliveryPriceDistanceAdapter(getM());
        $mdpd_resource = $mdpd->getExactResourceFromData($data);
        $this->assertNotNull($mdpd_resource, "should have found a merchant delivery price distance resource");
        $mdpd_resource->distance_up_to = 100.0;
        $mdpd_resource->price = 5.55;
        $mdpd_resource->save();

        $promo_data = [];
        $keyword = "FDForever";
        $promo_data['key_word'] = $keyword;
        $promo_data['promo_type'] = 300;
        $promo_data['description'] = 'Get Free Delivery';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2040-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['allow_multiple_use_per_order'] = false;
        $promo_data['valid_on_first_order_only'] = 'N';
        $promo_data['order_type'] = 'delivery';
        $promo_data['merchant_id'] = $merchant_id;
        $promo_data['qualifying_amt'] = 10.00;
        $promo_data['brand_id'] = getBrandIdFromCurrentContext();
//        $promo_data['percent_off'] = 50;

//        $request = createRequestObject("/app2/admin/promo",'POST',json_encode($promo_data));
//        $promo_controller = new PromoController(getM(),null,$request,5);
//        $response = $promo_controller->createPromo();

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos";
        $response = $this->makeRequest($url,null,'POST',$promo_data);

        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $this->assertEquals(200,$this->info['http_code']);
        $promo_id = $response_array['data']['promo_id'];
        $this->assertTrue( $promo_id > 0,"It should have created a valid promo id");

        $promo_resource = Resource::find(new PromoAdapter(getM()),$promo_id);
        $promo_key_word = $promo_resource->promo_key_word;
        $this->assertEquals('master_'.$keyword,$promo_key_word,"It should have created the master promo key word");

        // make sure promo_key_word_map record was created
        $pkwm_data['promo_id'] = $promo_id;
        $pkwm_options[TONIC_FIND_BY_METADATA] = $pkwm_data;
        $pkwm_resources = Resource::findAll(new PromoKeyWordMapAdapter(getM()),null,$pkwm_options);
        $this->assertCount(1,$pkwm_resources,"there should have been one PromoKewWordMapRecords created");
        $this->assertEquals($keyword,$pkwm_resources[0]->promo_key_word);

        // check to see if promo amounts was created
        $promo_amounts_id = $response_array['data']['promo_amount']['id'];
        $promo_amts_resource = Resource::find(new PromoDeliveryAmountMapAdapter(getM()),"$promo_amounts_id");
        $this->assertEquals(100,$promo_amts_resource->percent_off,'It should have the default value of 100% off');

        // check to see if messages were created
        $promo_messasges_adapter = new PromoMessageMapAdapter(getM());
        $record = $promo_messasges_adapter->getRecord(['promo_id'=>$promo_id]);
        $this->assertNotNull($record,"It should have created the message record");
        $expected_message = "Congratulations! You're getting free delivery!";
        $this->assertEquals($expected_message,$record['message1'],"This shoudl be message 1");
        $this->assertEquals($expected_message,$record['message2'],"This should be message TWO");
        $expected_message2 = "Here's the deal, spend more than $10.00, and you'll get free delivery!";
        $this->assertEquals($expected_message2,$record['message5']);


        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos/$promo_id";

//        $request = createRequestObject($url,'GET');
//        $promo_controller = new PromoController(getM(),null,$request,5);
//        $response = $promo_controller->processV2Request();

        $response = $this->makeRequest($url);
        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $this->assertEquals(200,$this->info['http_code']);
        //$this->assertEquals($expected_message,$response_array['data']['promo_messages']['message1']);

//        // now try updateing the promo
//        $rdata = $response_array['data'];
//        $rdata['valid_on_first_order_only'] = 'Y';
//        $rdata['qualifying_amt'] = 2.00;
//        $rdata['percent_off'] = 50;
//        $rdata['promo_messages']['message1'] = 'You will get NOTHING and like it';
//        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos/$promo_id";
//
////        $request = createRequestObject($url,"POST",json_encode($rdata));
////        $promo_controller = new PromoController(getM(),null,$request,5);
////        $u_response = $promo_controller->processV2Request();
//
//
//        $u_response = $this->makeRequest($url,null,'POST',$rdata);
//
//        $response_array = json_decode($u_response,true);
//        $this->assertNotNull($response_array,"returned data was not json");
//        $this->assertEquals(200,$this->info['http_code']);
//
//        // now get the promo and see if the fields updated
//        $promo = PromoAdapter::staticGetRecordByPrimaryKey($promo_id,'PromoAdapter');
//        $this->assertEquals('Y',$promo['valid_on_first_order_only']);
//
//        $promo_amts_resource = Resource::find(new PromoDeliveryAmountMapAdapter(getM()),"$promo_amounts_id");
//        $this->assertEquals(50,$promo_amts_resource->percent_off,'It should have the new value of 50');



        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
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

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id", 'GET', null);
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertTrue($resource->is_in_delivery_range, " the is in delivery range should be false");
        $this->assertEquals($mdpd_resource->price, $resource->price);

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note');
        $cart_data['user_addr_id'] = $user_address_id;

        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $this->assertEquals(5.55,$checkout_resource->delivery_amt);
        $ucid = $checkout_resource->ucid;

        $url = "/app2/apiv2/cart/$ucid/checkout?promo_code=$keyword";
        $request = createRequestObject($url, 'get', null);
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);

        $this->assertEquals(5.55,$checkout_resource->delivery_amt,"It should charge have a charge for delivery");
        $this->assertEquals(0,$checkout_resource->promo_amt,"It shoudl not have a promo amount because minimum purchase was not reached");
        $this->assertEquals("You have not completed your promo! Here's the deal, spend more than $10.00, and you'll get free delivery!",$checkout_resource->user_message);

        //now set minimum to 1.00 and retry
        $promo_amts_resource->qualifying_amt = 1.00;
        $promo_amts_resource->save();

        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertEquals(5.55,$checkout_resource->delivery_amt,"It should charge have a charge for delivery");
        $this->assertEquals(-5.55,$checkout_resource->promo_amt,"It should show the promo of -5.55 which is the delivery amount");
    }

    function testTurnOnLoyaltyWithParemetersEarn()
    {
        $alphacode = generateAlphaCode(5);
        $skin_resource = getOrCreateSkinAndBrandIfNecessary($alphacode.'defloyskin',$alphacode.'defloybrand',null,null);
        setContext($skin_resource->external_identifyer);
        $brand_id = $skin_resource->brand_id;
        $blr = BrandLoyaltyRulesAdapter::staticGetRecord(['brand_id'=>$skin_resource->brand_id],'BrandLoyaltyRulesAdapter');
        $this->assertNull($blr);
        $this->assertEquals(0,$skin_resource->supports_history);

        $data['loyalty_type'] = 'splickit_earn';
        $data['starting_point_value'] = 1000;
        $data['earn_value_amount_multiplier'] = 20;  // points per dollar spent
        $data['charge_tax'] = false; // should tax be charged on items purchased with loyalty

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/brands/$brand_id/loyalty/enable_loyalty";

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $brand_controller = new BrandController(getM(),null,$request,5);
//        $resource = $brand_controller->processRequest();

        $response = $this->makeRequest($url,null,'POST',$data);


        $skin_adapter = new SkinAdapter(getM());
        $skin_resource = Resource::find($skin_adapter,$skin_resource->skin_id);
        $this->assertEquals(1,$skin_resource->supports_history);


        $brand_resource = Resource::find(new BrandAdapter(getM()),$brand_id);
        $this->assertEquals('Y',$brand_resource->loyalty);
        $blr = BrandLoyaltyRulesAdapter::staticGetRecord(['brand_id'=>$skin_resource->brand_id],'BrandLoyaltyRulesAdapter');
        $this->assertNotNull($blr);
        $this->assertEquals('splickit_earn',$blr['loyalty_type']);
        $this->assertEquals(1000,$blr['starting_point_value']);
        $this->assertEquals(20,$blr['earn_value_amount_multiplier']);
        return $brand_id;
    }

    /**
     * @depends testTurnOnLoyaltyWithParemetersEarn
     */
    function testGetLoyaltyForEdit($brand_id)
    {
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/brands/$brand_id/loyalty";

//        $request = createRequestObject($url,'GET');
//        $brand_controller = new BrandController(getM(),null,$request,5);
//        $resource = $brand_controller->processRequest();

        $response = $this->makeRequest($url,null);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);

        $data = $response_array['data'];

        $this->assertEquals('Y',$data['enabled']);

        $data['enabled'] = 'N';

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $brand_controller = new BrandController(getM(),null,$request,5);
//        $resource = $brand_controller->processRequest();



        $response = $this->makeRequest($url,null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);

        $data = $response_array['data'];

        $this->assertEquals('N',$data['enabled']);

        return $brand_id;
    }

    /**
     * @depends testGetLoyaltyForEdit
     */
    function testDoNotAllowGetOrEditOfNonHomegrownLoyalty($brand_id)
    {
        $blra = new BrandLoyaltyRulesAdapter(getM());
        $blr_resource = Resource::find($blra,null,[3=>['brand_id'=>$brand_id]]);
        $this->assertEquals('splickit_earn',$blr_resource->loyalty_type);
        $blr_resource->loyalty_type = 'remote';
        $blr_resource->save();

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/brands/$brand_id/loyalty";

//        $request = createRequestObject($url,'GET');
//        $brand_controller = new BrandController(getM(),null,$request,5);
//        $resource = $brand_controller->processRequest();

        $response = $this->makeRequest($url,null);
        $response_array = json_decode($response,true);
        $this->assertEquals(422,$response_array['http_code']);
        $this->assertEquals(BrandController::NO_HOMEGROWN_LOYALTY_ERROR,$response_array['error']['error_message']);




    }

    function testCreateAutoPromoType1()
    {
        //setContext('com.splickit.worldhq');

        $code = generateAlphaCode(3);
        $skin_resource = getOrCreateSkinAndBrandIfNecessary('promo_skin_'.$code,'promo_brand_'.$code,null,null);
        setContext($skin_resource->external_identifier);
        $menu_id = createTestMenuWithNnumberOfItems(1);

        $merchant_resource = createNewTestMerchant($menu_id);
        $mr = createNewTestMerchant($menu_id);
        $mr = createNewTestMerchant($menu_id);
        $mr = createNewTestMerchant($menu_id);
        $mr = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;

        $billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);
        $merchant_payment_type_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);


        //create the type 1 promo
        $promo_data = [];
        $key_word = time();
        $promo_data['key_word'] = "$key_word";
        $promo_data['promo_type'] = 1;
        $promo_data['auto_promo'] = 1;
        $promo_data['description'] = 'Get $10 off';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['allow_multiple_use_per_order'] = false;
        $promo_data['valid_on_first_order_only'] = 'N';
        $promo_data['order_type'] = 'pickup';
        $promo_data['merchant_id'] = 0;
//        $promo_data['message1'] = "Congratulations! You're getting $%%amt%% off your order!";
        $promo_data['qualifying_amt'] = 8.88;
        $promo_data['promo_amt'] = 0.00;
        $promo_data['percent_off'] = 10;
        $promo_data['max_amt_off'] = 50.00;
        $promo_data['brand_id'] = getBrandIdFromCurrentContext();

//        $request = createRequestObject("/app2/admin/promo",'POST',json_encode($promo_data));
//        $promo_controller = new PromoController(getM(),null,$request,5);
//        $response = $promo_controller->createPromo();


        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos";
        $response = $this->makeRequest($url,null,'POST',$promo_data);

        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $this->assertEquals(200,$this->info['http_code']);
        $promo_id = $response_array['data']['promo_id'];
        $this->assertTrue( $promo_id > 0,"It should have created a valid promo id");

        $promo_resource = Resource::find(new PromoAdapter(getM()),$promo_id);
        $promo_key_word = $promo_resource->promo_key_word;
        $this->assertEquals("X_$key_word",$promo_key_word,"It should have created the auto promo key word");

        // make sure no promo_key_word_map record was created
        $pkwm_data['promo_id'] = $promo_id;
        $pkwm_options[TONIC_FIND_BY_METADATA] = $pkwm_data;
        $pkwm_resources = Resource::findAll(new PromoKeyWordMapAdapter(getM()),null,$pkwm_options);
        $this->assertCount(0,$pkwm_resources,"there should have been no PromoKewWordMapRecords created");



        // check to see if messages were created
        $promo_messasges_adapter = new PromoMessageMapAdapter(getM());
        $record = $promo_messasges_adapter->getRecord(['promo_id'=>$promo_id]);
        $this->assertNotNull($record,"It should have created the message record");
        $expected_message = "Congratulations! You're getting $%%amt%% off your order!";
        $this->assertEquals($expected_message,$record['message1']);
        $expected_message2 = "Here's the deal, spend more than $8.88, and you'll get a discount on your order";
        $this->assertEquals($expected_message2,$record['message5']);

        // check to see if all 5 merchants were addedd to the promo_merchant_map
        $pmma = new PromoMerchantMapAdapter(getM());
        $pmm_records = $pmma->getRecords(["promo_id"=>$promo_id]);
        $this->assertCount(5,$pmm_records);


    }


    function testAdjustLoyaltyPoints()
    {
        $skin_resource = getOrCreateSkinAndBrandIfNecessary("xearn", "earnbrand", null, null);
        $brand_id = $skin_resource->brand_id;
        $brand_resource = Resource::find(new BrandAdapter($mimetypes),"$brand_id");
        $brand_resource->loyalty = 'Y';
        $brand_resource->save();

        $blr_data['brand_id'] = $brand_id;
        $blr_data['loyalty_type'] = 'splickit_earn';
        $brand_loyalty_rules_resource = Resource::factory(new BrandLoyaltyRulesAdapter($m),$blr_data);
        $brand_loyalty_rules_resource->save();
        //$ids['blr_resource'] = $brand_loyalty_rules_resource->getRefreshedResource();
        setContext($skin_resource->external_identifier);
        //$ids['context'] = $skin_resource->external_identifier;
        $menu_id = createTestMenuWithNnumberOfItems(1);
        //$ids['menu_id'] = $menu_id;

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;

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

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/brands/$brand_id/adjustloyaltypoints";
        $data['user_id'] = $user['user_id'];
        $data['points'] = 100;
        $data['note'] = "sasquatch";
        $response = $this->makeRequest($url,null,'POST',$data);

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $brand_controller = new BrandController(getM(),null,$request,5);
//        $resource = $brand_controller->processRequest();



        $user_brand_points_record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals(100,$user_brand_points_record['points'],'There should be 100 points');

        $user_brand_loyalty_history_adapter = new UserBrandLoyaltyHistoryAdapter(getM());
        $history = $user_brand_loyalty_history_adapter->getLoyaltyHistoryForUserBrand($user['user_id'],$brand_id);
        $this->assertCount(1,$history);
        $this->assertEquals('sasquatch',$history[0]['notes']);


    }

    function testTurnOnDefaulLoyalty()
    {
        $alphacode = generateAlphaCode(5);
        $skin_resource = getOrCreateSkinAndBrandIfNecessary($alphacode.'defloyskin',$alphacode.'defloybrand',null,null);
        setContext($skin_resource->external_identifyer);
        $brand_id = $skin_resource->brand_id;
        $blr = BrandLoyaltyRulesAdapter::staticGetRecord(['brand_id'=>$skin_resource->brand_id],'BrandLoyaltyRulesAdapter');
        $this->assertNull($blr);

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/brands/$brand_id/loyalty/enable_loyalty";
        $response = $this->makeRequest($url,null,'POST',null);

//        $request = createRequestObject($url,'POST');
//        $brand_controller = new BrandController(getM(),null,$request,5);
//        $resource = $brand_controller->processRequest();

        $brand_resource = Resource::find(new BrandAdapter(getM()),$brand_id);
        $this->assertEquals('Y',$brand_resource->loyalty);
        $blr = BrandLoyaltyRulesAdapter::staticGetRecord(['brand_id'=>$skin_resource->brand_id],'BrandLoyaltyRulesAdapter');
        $this->assertNotNull($blr);
        $this->assertEquals('splickit_cliff',$blr['loyalty_type']);
        $this->assertEquals(500,$blr['cliff_value']);
        return $brand_id;
    }

    /**
     * @depends testTurnOnDefaulLoyalty
     */
    function testTurnOffLoyalty($brand_id)
    {
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/brands/$brand_id/loyalty/disable_loyalty";
        $response = $this->makeRequest($url,null,'POST',null);
        $brand_resource = Resource::find(new BrandAdapter(getM()),$brand_id);
        $this->assertEquals('N',$brand_resource->loyalty);
        return $brand_id;
    }

    /**
     * @depends testTurnOffLoyalty
     */
    function testTurnBackOnLoylaty($brand_id)
    {
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/brands/$brand_id/loyalty/enable_loyalty";
        $response = $this->makeRequest($url,null,'POST',null);
        $brand_resource = Resource::find(new BrandAdapter(getM()),$brand_id);
        $this->assertEquals('Y',$brand_resource->loyalty);

        $skin_record = SkinAdapter::staticGetRecord(['brand_id'=>$brand_id],'SkinAdapter');
        setContext($skin_record['external_identifier']);

        $menu_id = createTestMenuWithNnumberOfItems(1);
        $merchant_resource = createNewTestMerchant($menu_id);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);
        $user_brand_points_map_adapter = new UserBrandPointsMapAdapter(getM());
        $user_brand_points_record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertNotNull($user_brand_points_record,"Should have found a user brand loyalty record");
        $loyalty_number = $user_brand_points_record['loyalty_number'];
        $this->assertNotNull($loyalty_number,"Should have generated a loyalty number");
        $this->assertEquals(cleanAllNonNumericCharactersFromString($user_resource->contact_no), $loyalty_number,"It should be the users contact nubmer");
        $this->assertEquals(0,$user_brand_points_record['points'],'should have zero points');
        $this->assertEquals(0,$user_brand_points_record['dollar_balance'],'should have zero dollar value');

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_resource->merchant_id,'pickup','note',5);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_resource->merchant_id,0.00);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000);
        $ubpm_adapter = new UserBrandPointsMapAdapter(getM());
        $this->assertTrue($order_resource->order_amt > 1.00);
        $expected_points = round($order_resource->order_amt*10);
        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals($expected_points,$ubpm_record['points'],"Shouljd have the expected points");
        $this->assertEquals(0,$ubpm_record['dollar_balance'],'should have zero dollar value');

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter(getM());
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$order_id));
        $this->assertCount(1,$ublh_records,"There should be 1 record for this order");
        $hash = createHashmapFromArrayOfArraysByFieldName($ublh_records,'process');
        $this->assertEquals($ubpm_record['points'],$hash['Order']['points_added'],'It should have the points earned');
        $this->assertEquals($ubpm_record['points'],$hash['Order']['current_points']);
        $this->assertEquals(0.00,$hash['Order']['current_dollar_balance']);
    }


    function testCreateType6Promo()
    {
        $promo_data = [];
        $promo_data['key_word'] = 'variable';
        $promo_data['promo_type'] = 6;
        $promo_data['description'] = 'LU discount';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 1000;
        $promo_data['brand_id'] = getBrandIdFromCurrentContext();

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos";
        $response = $this->makeRequest($url,null,'POST',$promo_data);
        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");
        $this->assertEquals(200,$this->info['http_code']);
    }


    function testCreatePromoType1()
    {
        setContext('com.splickit.worldhq');
        $menu_id = createTestMenuWithNnumberOfItems(1);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;

        $billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);
        $merchant_payment_type_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);


        //create the type 1 promo
        $promo_data = [];
        $promo_data['key_word'] = "The Type1 Promo,type1promo";
        $promo_data['promo_type'] = 1;
        $promo_data['description'] = 'Get $10 off';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['allow_multiple_use_per_order'] = false;
        $promo_data['valid_on_first_order_only'] = 'N';
        $promo_data['order_type'] = 'pickup';
        $promo_data['merchant_id'] = $merchant_id;
//        $promo_data['message1'] = "Congratulations! You're getting $%%amt%% off your order!";
        $promo_data['qualifying_amt'] = 5.00;
        $promo_data['promo_amt'] = 10.00;
        $promo_data['percent_off'] = 0;
        $promo_data['max_amt_off'] = 50.00;
        $promo_data['brand_id'] = getBrandIdFromCurrentContext();

//        $request = createRequestObject("/app2/admin/promo",'POST',json_encode($promo_data));
//        $promo_controller = new PromoController(getM(),null,$request,5);
//        $response = $promo_controller->createPromo();


        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos";
        $response = $this->makeRequest($url,null,'POST',$promo_data);

        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $this->assertEquals(200,$this->info['http_code']);
        $promo_id = $response_array['data']['promo_id'];
        $this->assertTrue( $promo_id > 0,"It should have created a valid promo id");

        // check to see if messages were created
        $promo_messasges_adapter = new PromoMessageMapAdapter(getM());
        $record = $promo_messasges_adapter->getRecord(['promo_id'=>$promo_id]);
        $this->assertNotNull($record,"It should have created the message record");
        $expected_message = "Congratulations! You're getting $%%amt%% off your order!";
        $this->assertEquals($expected_message,$record['message1']);
        $expected_message2 = "Here's the deal, spend more than $5.00, and you'll get a discount on your order";
        $this->assertEquals($expected_message2,$record['message5']);


        //test messages
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $cart_data['promo_code'] = 'type1promo';
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $promo_user_message = $checkout_resource->user_message;
        $this->assertContains($expected_message2,$promo_user_message);
        return $promo_id;
    }

    /**
     * @depends testCreatePromoType1
     */
    function testGetPromoType1($promo_id)
    {
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos/$promo_id";

//        $request = createRequestObject($url,"GET",null);
//        $promo_controller = new PromoController(getM(),null,$request,5);
//        $response = $promo_controller->processV2Request();


        $response = $this->makeRequest($url);
        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $this->assertEquals(200,$this->info['http_code']);
        return $response_array['data'];
    }

    /**
     * @depends testGetPromoType1
     */
    function testUpdatePromoType1($data)
    {
        $promo_id = $data['promo_id'];
        $data['valid_on_first_order_only'] = 'Y';
        $data['qualifying_amt'] = 2.00;
        $data['promo_amt'] = 0.00;
        $data['percent_off'] = 50;
        $data['promo_messages']['message1'] = 'You will get NOTHING and like it';
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos/$promo_id";

//        $request = createRequestObject($url,"POST",json_encode($data));
//        $promo_controller = new PromoController(getM(),null,$request,5);
//        $response = $promo_controller->processV2Request();


        $response = $this->makeRequest($url,null,'POST',$data);

        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");
        $this->assertEquals(200,$this->info['http_code']);

        // now get the promo and see if the fields updated
        $promo = PromoAdapter::staticGetRecordByPrimaryKey($promo_id,'PromoAdapter');
        $this->assertEquals('Y',$promo['valid_on_first_order_only']);

        $promo_amt_map = PromoType1AmtMapAdapter::staticGetRecord(['promo_id'=>$promo_id],'PromoType1AmtMapAdapter');
        $this->assertEquals(2.00,$promo_amt_map['qualifying_amt']);
        $this->assertEquals(0.00,$promo_amt_map['promo_amt']);
        $this->assertEquals(50,$promo_amt_map['percent_off']);

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos/$promo_id";

        $response = $this->makeRequest($url);
        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $this->assertEquals(200,$this->info['http_code']);
        $this->assertEquals("You will get NOTHING and like it",$response_array['data']['promo_messages']['message1']);
    }

    function testCreatePromoType2Items()
    {
        setContext('com.splickit.worldhq');
        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(5);
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());


        $merchant_resource = createNewTestMerchant($menu_id);
        attachMerchantToSkin($merchant_resource->merchant_id, getSkinIdForContext());
        $ids['merchant_id'] = $merchant_resource->merchant_id;
        $merchant_id = $merchant_resource->merchant_id;
        $item_size_records = CompleteMenu::getAllItemSizesAsResources($menu_id,0);
        $billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);
        $merchant_payment_type_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);


        //create the type 2 promo
        $promo_data = [];
        $promo_data['key_word'] = "sumdumkeyword";
        $promo_data['menu_id'] = $menu_id;
        $promo_data['promo_type'] = 2;
        $promo_data['description'] = 'Get a free this when you purchase a large that. boom';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['allow_multiple_use_per_order'] = true;
        $promo_data['valid_on_first_order_only'] = 'N';
        $promo_data['order_type'] = 'pickup';
        $promo_data['merchant_id'] = $merchant_id;
        $promo_data['qualifying_amt'] = 0.00;
        $promo_data['qualifying_object_array'] = ['Item-'.$item_records[1]['item_id'],'Item-'.$item_records[3]['item_id']];
        $promo_data['promo_item_1_array'] = ['Item-'.$item_records[2]['item_id'],'Item-'.$item_records[4]['item_id']];
        //  $promo_data['promo_item_1_array'] = ['ItemSize-'.$item_size_records[0]->item_size_id];
        $promo_data['brand_id'] = 300;

//        $request = createRequestObject("/app2/admin/promo",'POST',json_encode($promo_data));
//        $promo_controller = new PromoController(getM(),null,$request,5);
//        $response = $promo_controller->createPromo();


        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos";
        $response = $this->makeRequest($url,null,'POST',$promo_data);

        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $this->assertEquals(200,$this->info['http_code']);
        $promo_id = $response_array['data']['promo_id'];
        $this->assertTrue($promo_id > 0,"It should have created a valid promo id");

        // check to see if messages where created
        $promo_messasges_adapter = new PromoMessageMapAdapter(getM());
        $record = $promo_messasges_adapter->getRecord(['promo_id'=>$promo_id]);
        $this->assertNotNull($record,"It should have created the messagse record");
        $expected_message1 = "Congratulations! You're getting a FREE ".$item_records[2]['item_name']."!";
        $expected_message4 = "Almost there, now add a ".$item_records[2]['item_name']." to this order and its FREE!";
        $expected_message5 = "Here's the deal, order a ".$item_records[1]['item_name'].", then add a ".$item_records[2]['item_name']." to go with it, and its FREE!";

        $this->assertEquals($expected_message1,$record['message1']);
        $this->assertEquals($expected_message4,$record['message4']);
        $this->assertEquals($expected_message5,$record['message5']);
        return $promo_id;
    }


    /**
     * @depends testCreatePromoType2Items
     */
    function testGetPromoType2($promo_id)
    {
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos/$promo_id";

//        $request = createRequestObject($url, "GET", null);
//        $promo_controller = new PromoController(getM(), null, $request, 5);
//        $response = $promo_controller->processV2Request();


        $response = $this->makeRequest($url);
        $response_array = json_decode($response, true);
        $this->assertNotNull($response_array, "returned data was not json");
        $this->assertEquals(['Test Item 2','Test Item 4'],$response_array['data']['qualifying_object_name_list']);
        $this->assertEquals(['Test Item 3','Test Item 5'],$response_array['data']['promotional_object_name_list']);

        return $response_array['data'];
    }

    /**
     * @depends testGetPromoType2
     */
    function testGetPromoType2MenuTypes($data)
    {
        $promo_id = $data['promo_id'];
        $promo_adapter = new PromoAdapter(getM());
        $promo_resource = Resource::find($promo_adapter,$promo_id);
        $menu_id = $promo_resource->menu_id;
        $complete_menu = CompleteMenu::getCompleteMenu($menu_id);
        $adapter = new PromoType2ItemMapAdapter(getM());
        $promo_type_2_resource = Resource::findOrCreateIfNotExistsByData($adapter,['promo_id'=>$promo_id]);
        $menu_type = $complete_menu['menu_types'][0];
        $menu_type_id = $menu_type['menu_type_id'];
        $promo_type_2_resource->qualifying_object_id_list = "Menu_Type-$menu_type_id";
        $promo_type_2_resource->promotional_object_id_list = "Menu_Type-$menu_type_id";
        $promo_type_2_resource->save();

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos/$promo_id";
//        $request = createRequestObject($url, "GET", null);
//        $promo_controller = new PromoController(getM(), null, $request, 5);
//        $response = $promo_controller->processV2Request();
        $response = $this->makeRequest($url);
        $response_array = json_decode($response, true);
        $this->assertNotNull($response_array, "returned data was not json");
        $this->assertEquals([$menu_type['menu_type_name']],$response_array['data']['qualifying_object_name_list']);
        $this->assertEquals([$menu_type['menu_type_name']],$response_array['data']['promotional_object_name_list']);

        $promo_type_2_resource->qualifying_object_id_list = "Entre";
        $promo_type_2_resource->promotional_object_id_list = "Entre";
        $promo_type_2_resource->save();

        $response = $this->makeRequest($url);
        $response_array = json_decode($response, true);
        $this->assertNotNull($response_array, "returned data was not json");
        $this->assertEquals(['Entre'],$response_array['data']['qualifying_object_name_list']);
        $this->assertEquals(['Entre'],$response_array['data']['promotional_object_name_list']);

        $size = $complete_menu['menu_types'][0]['sizes'][0];

        $promo_type_2_resource->qualifying_object_id_list = "Entre";
        $promo_type_2_resource->promotional_object_id_list = "Size-".$size['size_id'];
        $promo_type_2_resource->save();

        $response = $this->makeRequest($url);
        $response_array = json_decode($response, true);
        $this->assertNotNull($response_array, "returned data was not json");
        $expected_name = $size['size_name'].'-'.$menu_type['menu_type_name'];
        $this->assertEquals([$expected_name],$response_array['data']['promotional_object_name_list']);

        $item = $complete_menu['menu_types'][0]['menu_items'][0];
        $item_size = $item['size_prices'][0];
        $promo_type_2_resource->promotional_object_id_list = "Item_Size-".$item_size['item_size_id'];
        $promo_type_2_resource->save();

        $response = $this->makeRequest($url);
        $response_array = json_decode($response, true);
        $this->assertNotNull($response_array, "returned data was not json");
        $expected_name = $size['size_name'].'-'.$item['item_name'];
        $this->assertEquals([$expected_name],$response_array['data']['promotional_object_name_list']);


    }


    /**
     * @depends testGetPromoType2
     */
    function testUpdatePromoType2($data)
    {
        $promo_id = $data['promo_id'];
        $data['max_redemptions'] = '10';
        $data['active'] = 'N';
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos/$promo_id";
        $response = $this->makeRequest($url,null,'POST',$data);

        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");
        $this->assertEquals(200,$this->info['http_code']);

        // now get the promo and see if the fields updated
        $promo = PromoAdapter::staticGetRecordByPrimaryKey($promo_id,'PromoAdapter');
        $this->assertEquals(10 ,$promo['max_redemptions']);
        $this->assertEquals('N' ,$promo['active']);
    }


    function testCreatePromoType45()
    {
        $skin_resource = createWorldHqSkin();
        setContext($skin_resource->external_identifier);

        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(5);
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());

        $complete_menu = CompleteMenu::getCompleteMenu($menu_id);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        $item_size_records = CompleteMenu::getAllItemSizesAsResources($menu_id,0);


        //create the type 4 promo
        $promo_data = [];
        $promo_data['key_word'] = 'Test Promo Four';
        $promo_data['promo_type'] = 4;
        $promo_data['description'] = 'Get $ off when you order one of these items';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['merchant_id'] = $merchant_id;
        $promo_data['qualifying_object_array'] = ['Item-'.$item_records[0]['item_id'],'Item-'.$item_records[1]['item_id'],'Item-'.$item_records[2]['item_id']];
        //$promo_data['qualifying_object_array'] = ['Menu_Type-'.$complete_menu['menu_types'][0]['menu_type_id']];
        $promo_data['fixed_amount_off'] = 1.00;
        $promo_data['percent_off'] = 40;
        $promo_data['fixed_price'] = .75;
        $promo_data['brand_id'] = getBrandIdFromCurrentContext();


//        $request = createRequestObject("/app2/admin/promo",'POST',json_encode($promo_data));
//        $promo_controller = new PromoController(getM(),null,$request,5);
//        $response = $promo_controller->createPromo();


        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos";
        $response = $this->makeRequest($url,null,'POST',$promo_data);

        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $this->assertEquals(200,$this->info['http_code']);
        $promo_id = $response_array['data']['promo_id'];
        $this->assertTrue($promo_id > 0,"It should have created a valid promo id");

        // check to see if messages where created
        $promo_messasges_adapter = new PromoMessageMapAdapter(getM());
        $record = $promo_messasges_adapter->getRecord(['promo_id'=>$promo_id]);
        $this->assertNotNull($record,"It should have created the message record");
        $expected_message1 = "Congratulations! You're getting %%amt%% off of your %%item_name%%!";
        $expected_message5 = "Here's the deal, order a ".$item_records[0]['item_name']." or ".$item_records[1]['item_name']." or ".$item_records[2]['item_name'].", and you'll get a discount!";

        $this->assertEquals($expected_message1,$record['message1']);
        $this->assertEquals($expected_message5,$record['message5']);

        return $promo_id;
    }

    /**
     * @depends testCreatePromoType45
     */
    function testGetPromoType45($promo_id)
    {
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos/$promo_id";
//        $request = createRequestObject($url,'GET',null);
//        $promo_controller = new PromoController(getM(),null,$request,5);
//        $resource = $promo_controller->processV2Request();
//        $data = $resource->getDataFieldsReally();
        $response = $this->makeRequest($url);
        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");

        $this->assertEquals(200,$this->info['http_code']);
        $data = $response_array['data'];

        $this->assertEquals($promo_id,$data['promo_id']);
        $this->assertEquals(4,$data['promo_type']);
        $this->assertNull($data['qualifying_object']);
        $this->assertEquals(['Test Item 1','Test Item 2','Test Item 3'],$response_array['data']['qualifying_object_name_list']);
        $this->assertNull($response_array['data']['promotional_object_name_list']);

        return $data;
    }


    /**
     * @depends testGetPromoType45
     */
    function testUpdatePromoType45($data)
    {
        $promo_id = $data['promo_id'];
        $data['fixed_amount_off'] = 0.00;
        $data['percent_off'] = 25;
        $data['fixed_price'] = 0.88;
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/promos/$promo_id";

//        $request = createRequestObject($url,"POST",json_encode($data));
//        $promo_controller = new PromoController(getM(),null,$request,5);
//        $response = $promo_controller->processV2Request();




        $response = $this->makeRequest($url,null,'POST',$data);

        $response_array = json_decode($response,true);
        $this->assertNotNull($response_array,"returned data was not json");
        $this->assertEquals(200,$this->info['http_code']);


        $promo_item_map = PromoType4ItemAmountMapsAdapter::staticGetRecord(['promo_id'=>$promo_id],'PromoType4ItemAmountMapsAdapter');
        $this->assertEquals(0.00,$promo_item_map['fixed_amount_off']);
        $this->assertEquals(25,$promo_item_map['percent_off']);
        $this->assertEquals(0.88,$promo_item_map['fixed_price']);
    }


    function getExternalId()
    {
        if ($external_id = getContext()) {
            // use it
        } else {
            $external_id = "com.splickit.vtwoapi";
        }
        return $external_id;
    }

    function makeRequest($url,$userpassword,$method = 'GET',$data = null)
    {
        logData($data," curl data");
        unset($this->info);
        $method = strtoupper($method);
        $curl = curl_init($url);
        if ($userpassword) {
            curl_setopt($curl, CURLOPT_USERPWD, $userpassword);
        }
        $external_id = getContext();
        $headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:AdminDispatchTest","NO_CC_CALL:true");
        if ($authentication_token = $data['splickit_authentication_token']) {
            $headers[] = "splickit_authentication_token:$authentication_token";
        }
        if ($data['headers']) {
            $headers = $data['headers'];
            unset($data['headers']);
        }
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            if ($data) {
                $json = json_encode($data);
                curl_setopt($curl, CURLOPT_POSTFIELDS,$json);
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Content-Length: ' . strlen($json);
            }
        } else if ($method == 'DELETE') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        logCurl($url,$method,$userpassword,$headers,$json);
        $result = curl_exec($curl);

        $this->info = curl_getinfo($curl);
        curl_close($curl);
        return $result;
    }

    static function setUpBeforeClass()
    {
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        ini_set('max_execution_time',300);

        $skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty("vtwoapi","vtwoapi",252, 101);
        setContext('com.splickit.vtwoapi');

        $_SERVER['log_level'] = 5;
    }

    static function tearDownAfterClass()
    {
        //mysqli_query("ROLLBACK");
        date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
        $suite = new PHPUnit_Framework_TestSuite( __CLASS__);
        PHPUnit_TextUI_TestRunner::run( $suite);
    }



}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    PortalGeneralDispatchTest::main();
}

?>