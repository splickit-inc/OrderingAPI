<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class GuestUserFunctionsTest extends PHPUnit_Framework_TestCase
{

    var $stamp;
    var $ids;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $code = generateCode(7);
        $_SERVER['STAMP'] = __CLASS__ . '-' . $code;
        $_SERVER['RAW_STAMP'] = $code;
        $this->ids = $_SERVER['unit_test_ids'];
        setProperty('do_not_call_out_to_aws', 'true');
        setContext('com.splickit.guesttestskin');
    }

    function tearDown()
    {
        //delete your instance
        unset($this->ids);
        unset($_SERVER['max_lead']);
    }

    function testForgotPasswordLinkForGuestUserShouldNotWork()
    {
        $user_resource = createGuestUser();
        $user = logTestUserResourceIn($user_resource);
        $request = createRequestObject("apiv2/users/forgotpassword",'POST',json_encode(array("email"=>$user_resource->email)));
        $user_controller = new UserController($mt, $user, $request);
        $resource = $user_controller->processV2Request();
        $this->assertNotNull($resource->error);
        $this->assertEquals(UserController::ERROR_FOR_GUEST_USER_FORGOT_PASSWORD,$resource->error);
    }

    function testDelivertyOrderForGuest()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_resource->delivery = 'Y';
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);

        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'delivery');
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'pickup');

        $data = array("merchant_id" => $merchant_resource->merchant_id);

        // set merchant delivery info
        $mdia = new MerchantDeliveryInfoAdapter($mimetypes);
        $mdia_resource = $mdia->getExactResourceFromData($data);
        $mdia_resource->minimum_order = 1.00;
        $mdia_resource->delivery_cost = 1.00;
        $mdia_resource->delivery_increment = 15;
        $mdia_resource->max_days_out = 4;
        $mdia_resource->minimum_delivery_time = 45;
        $mdia_resource->save();

        $mdpd = new MerchantDeliveryPriceDistanceAdapter($mimetypes);
        $mdpd_resource = $mdpd->getExactResourceFromData($data);
        $this->assertNotNull($mdpd_resource, "should have found a merchant delivery price distance resource");
        $mdpd_resource->distance_up_to = 20.0;
        $delivery_charge = 8.88;
        $mdpd_resource->price = $delivery_charge;
        $mdpd_resource->save();


        $user_resource = createGuestUser();
        $user_resource->flags = "1C20000021";
        $user_resource->last_four = '1234';
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];

        $json = '{"user_addr_id":null,"user_id":"' . $user_id . '","name":"","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
        $request = createRequestObject("/users/" . $user['uuid'] . "/userdeliverylocation",'POST',$json);
        $user_controller = new UserController($mt, $user, $request, 5);
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id", 'GET', $body, $mimetype);
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range), "should have found the 'is in delivery range' field");
        $this->assertTrue($resource->is_in_delivery_range, " the is in delivery range should be true");
        $this->assertEquals($mdpd_resource->price, $resource->price);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note', 2);
        $order_data['user_addr_id'] = $user_address_id;
        $json_encoded_data = json_encode($order_data);

        $checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $cart_ucid = $checkout_resource->ucid;

        //validate delivery stuff
        $this->assertEquals($delivery_charge, $checkout_resource->delivery_amt, "It should have the delivery amount");
        $u = getUserFromId($user_id);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$t);
        $this->assertNull($order_resource->error);

        // now validate address on delivery order
        $this->assertTrue(isset($order_resource->user_delivery_address),"there should be a user delivery address field on the order resource");
        $this->assertEquals("4670 N Broadway St",$order_resource->user_delivery_address['address1']);

        $u = getUserFromId($order_resource->user_id);
        $this->assertEquals('Guest',$order_resource->user_type,"It should record the order as a guest");

    }

    function testResetCCForGuestAfterOneHour()
    {
        $user_resource = createGuestUser();
        $user = logTestUserResourceIn($user_resource);


        // now add credit card
        $data = array('cc_number' => '4111888899994321');
        $data['zip'] = '12345';
        $data['cc_exp_date'] = '0620';
        $data['cvv'] = '023';
        $request = createRequestObject("/app2/apiv2/users/".$user['uuid'],'POST',json_encode($data));
        $user_controller = new UserController($m,$user,$request,5);
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error);

        $user = getUserFromId($user['user_id']);
        $this->assertEquals("1C21000021",$user['flags']);

        $cctua = new CreditCardUpdateTrackingAdapter($m);
        $new_created = getMySqlFormattedDateTimeFromTimeStampAndTimeZone(time() - 3601,date_default_timezone_get());
        $resource = $cctua->getLastCardUpdateForUserId($user['user_id']);
        $this->assertFalse($resource->created < (time() - 3598));

        // now run service and the user shoudl still have a CC attached.
        $guccca = new GuestUserCreditCardCheckActivity();
        $guccca->doIt();

        $user_resource = $user_resource->refreshResource();
        $this->assertEquals('1C21000021',$user_resource->flags);

        // update cc update record to show it was updated over an hour ago
        $sql = "UPDATE Credit_Card_Update_Tracking SET created = '$new_created' WHERE id = ".$resource->id;
        $cctua->_query($sql);

        $resource = $resource->refreshResource();
        $this->assertTrue($resource->created < (time() - 3598));

        // now run service and the user should have the CC erased
        $guccca = new GuestUserCreditCardCheckActivity();
        $guccca->doIt();

        $user = getUserFromId($user_resource->user_id);
        $this->assertEquals('1000000021',$user['flags']);
        $this->assertNull($user['last_four'],'There should not be any cc info');


    }

    function testCreateGuestUser()
    {
        setProperty('do_not_call_out_to_aws',true);
        $user = logTestUserIn(1);
        $guest_user_data['first_name'] = "bob";
        $guest_user_data['email'] = 'testguestuser_' . generateAlphaCode(10) . '@dummy.com';
        $guest_user_data['contact_no'] = rand(1111111111, 9999999999);
        $guest_user_data['is_guest'] = true;
        $request = createRequestObject('/apiv2/users', 'POST', json_encode($guest_user_data));
        $user_controller = new UserController($mt, $user, $request);
        $resource = $user_controller->processV2Request();

        $this->assertEquals('1000000021', $resource->flags);
        $user = getUserFromId($resource->user_id);
        $this->assertEquals('Bob', $user['first_name'], "first name shoudl Bob");

        // make sure welcome letter was not created
        $mmha = new MerchantMessageHistoryAdapter($m);
        $this->assertNull($mmha->getRecord(array("message_format"=>'Ewel','message_delivery_addr'=>$guest_user_data['email'])),'It should not have created a welcome letter');

    }

    function testUpdateGuestToGuestUserWithSameEmail()
    {
        $user_resource = createGuestUser();
        $user = logTestUserResourceIn($user_resource);
        $user_data['first_name'] = 'magdalena';
        $user_data['email'] = $user['email'];
        $user_data['contact_no'] = rand(1111111111, 9999999999);
        $user_data['is_guest'] = true;
        $admin_user = logTestUserIn(2);
        $code = createUUID();
        $user_data['uuid'] = $code;
        $user_data['device_id'] = $code;
        $user_data['skin_id'] = 1;
        $user_data['skin_name'] = 'splickit';
        $user_data['device_type'] = 'unit_testing';
        $user_data['app_version'] = '100.0.1';

        $request = new Request();
        $request->data = $user_data;
        $request = createRequestObject('/apiv2/users', 'POST', json_encode($user_data));

        $user_controller = new UserController($mt, $admin_user, $request);
        $user_resource = $user_controller->createUser();

        $this->assertNotNull($user_resource);
        $this->assertNull($user_resource->error);
        $this->assertEquals('Magdalena', $user_resource->first_name);
        $this->assertNotEquals($user['contact_no'], $user_resource->contact_no);
        $this->assertEquals($user_resource->flags, '1000000021');
        $this->assertNotNull($user_resource->splickit_authentication_token);
        $this->assertNotNull($user_resource->splickit_authentication_token_expires_at);
    }

    function testUpdateGuestToNormalUserWithSameEmail()
    {
        $guest_user_resource = createGuestUser();

        // now join with same email
        $user_data = createNewUserDataFields();
        $user_data['first_name'] = 'Nicole';
        $user_data['last_name'] = 'Smith';
        $user_data['password'] = '123456';
        $user_data['email'] = $guest_user_resource->email;

        $admin_user = logTestUserIn(1);

        $request = createRequestObject('/apiv2/users', 'POST', json_encode($user_data));

        $user_controller = new UserController($mt, $admin_user, $request);
        $user_resource = $user_controller->createUser();

        $this->assertNotNull($user_resource);
        $this->assertNull($user_resource->error);
        $this->assertEquals('Nicole', $user_resource->first_name);
        $this->assertNotEquals($guest_user_resource->contact_no, $user_resource->contact_no);
        $this->assertEquals("Smith", $user_resource->last_name);
        $this->assertEquals($user_resource->flags, '1000000001');
        $this->assertNotNull($user_resource->splickit_authentication_token);
        $this->assertNotNull($user_resource->splickit_authentication_token_expires_at);
    }

    function testTryUpdateNormalToGuestUser()
    {
        $user_resource = createNewUser();
        $user = logTestUserResourceIn($user_resource);
        $user_data['first_name'] = 'Mili';
        $user_data['email'] = $user['email'];
        $user_data['contact_no'] = rand(1111111111, 9999999999);
        $user_data['is_guest'] = true;
        $admin_user = logTestUserIn(2);
        $code = createUUID();
        $user_data['uuid'] = $code;
        $user_data['device_id'] = $code;
        $user_data['skin_id'] = 1;
        $user_data['skin_name'] = 'splickit';
        $user_data['device_type'] = 'unit_testing';
        $user_data['app_version'] = '100.0.1';

        $request = new Request();
        $request->data = $user_data;
        $request = createRequestObject('/apiv2/users', 'POST', json_encode($user_data));

        $user_controller = new UserController($mt, $admin_user, $request);
        $user_resource = $user_controller->createUser();

        $this->assertNotNull($user_resource);
        $this->assertNotNull($user_resource->error);
        $this->assertEquals("Sorry, it appears this email address exists already.", $user_resource->error);
    }

    function testGetUserSessionWithGuestFlagSet()
    {
        $user_resource = createGuestUser();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($mt, $user, $r, 5);
        $user_session_resource = $user_session_controller->getUserSession($user_resource);
        $this->assertTrue($user_session_resource->guest_user, "It should have the guest_user flag set on the user session");

    }

    function testDoNotLoadUserOrderHistoryForGuestUser()
    {
        $merchant_id = $this->ids['merchant_id'];
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'the note');
        $checkout_resource = getCheckoutResourceFromOrderData($order_data, getTodayTwelveNoonTimeStampDenver() - (24 * 60 * 60));
        $order_resource = placeOrderFromCheckoutResource($checkout_resource, $user, $merchant_id, 0.00, getTodayTwelveNoonTimeStampDenver() - (24 * 60 * 60));
        $this->assertNull($order_resource->error);
        $order_resource->set('status', 'E');
        $this->assertTrue($order_resource->save());

        $order_adapter = new OrderAdapter($m);
        $record_order = $order_adapter->getRecordFromPrimaryKey($order_resource->order_id);
        $this->assertNotNull($record_order);

        $user = logTestUserIn($user['user_id']);
        $uuid = $user['uuid'];

        $user_controller = new UserController($mt, $user, createRequestObject("apiv2/users/$uuid/orderhistory", 'GET'), 5);
        $order_history = $user_controller->processV2Request();
        $this->assertNull($order_history->error);
        $this->assertCount(1, $order_history->data['orders']);

        // should also show up on merchant last orders discplayed
        $merchant_controller = new MerchantController($mt, $user, createRequestObject("apiv2/merchants/$merchant_id", 'GET'), 5);
        $merchant_response = $merchant_controller->processV2Request();
        $this->assertCount(1, $merchant_response->user_last_orders, "last_order_displayed is 1, should return 1 order in 'last_orders' fieled of merchant data");

        // now change the user to a guest user and then get the order history and merchant again.
        $user_resource = getUserResourceFromId($user['user_id']);
        $user_resource->flags = '1000000021';
        $user_resource->save();

        $user = logTestUserResourceIn($user_resource);

        $user_controller = new UserController($mt, $user, createRequestObject("apiv2/users/$uuid/orderhistory", 'GET'), 5);
        $order_history_guest = $user_controller->processV2Request();
        $this->assertNull($order_history_guest->error);
        $this->assertEquals(0, $order_history_guest->data['orders'], "There should be zero order in order history call");
        $merchant_controller = new MerchantController($mt, $user, createRequestObject("apiv2/merchants/$merchant_id", 'GET'), 5);
        $merchant_response_guest = $merchant_controller->processV2Request();
        $this->assertEquals(array(), $merchant_response_guest->user_last_orders, "should return 0 orders in 'last_orders' field of merchant data");
    }

    function testNoUserFavoritesForGuestUser()
    {
        //creates a normal user and an order
        $merchant_id = $this->ids['merchant_id'];
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'the note');
        $checkout_resource = getCheckoutResourceFromOrderData($order_data, getTodayTwelveNoonTimeStampDenver() - (24 * 60 * 60));
        $order_resource = placeOrderFromCheckoutResource($checkout_resource, $user, $merchant_id, 0.00, getTodayTwelveNoonTimeStampDenver() - (24 * 60 * 60));
        $this->assertNull($order_resource->error);
        $order_resource->set('status', 'E');
        $this->assertTrue($order_resource->save());

        $order_adapter = new OrderAdapter($m);
        $record_order = $order_adapter->getRecordFromPrimaryKey($order_resource->order_id);
        $this->assertNotNull($record_order);

        $user = logTestUserIn($user['user_id']);
        $uuid = $user['uuid'];

        // create a favorite for user normal
        $request = new Request();
        $request->url = "apiv2/favorites?log_level=5";
        $request->body = json_encode(array("order_id" => $record_order['order_id'], "favorite_name" => 'favoritetest'));
        $request->method = 'post';
        $request->mimetype = 'application/json';

        $favorite_controller = new FavoriteController($m, $user, $request, 5);
        $favorite = $favorite_controller->processV2Request();
        $this->assertNotNull($favorite->favorite_id, "should have gotten back a resource with the favorite id");

        // should also show up on merchant user_favorites
        $merchant_controller = new MerchantController($mt, $user, createRequestObject("apiv2/merchants/$merchant_id", 'GET'), 5);
        $merchant_response_guest = $merchant_controller->processV2Request();
        $this->assertCount(1, $merchant_response_guest->user_favorites, "Should have found one favorite");

        // now change the user to a guest user and then get the favorites
        $user_resource = getUserResourceFromId($user['user_id']);
        $user_resource->flags = '1000000021';
        $user_resource->save();

        $user = logTestUserResourceIn($user_resource);

        $request = createRequestObject("apiv2/users/" . $user['uuid'] . "/favorites?merchant_id=" . $merchant_id . "&merchant_menu_type=pickup", 'GET');
        $user_controller = new UserController($mt, $user, $request, 5);

        $favorite_guest = $user_controller->processV2Request();
        $this->assertNull($favorite_guest->error);
        $this->assertEquals(0, $favorite_guest->data, "There should be zero order in order history call");

        // should also dont show up on merchant user_favorites
        $merchant_controller = new MerchantController($mt, $user, createRequestObject("apiv2/merchants/$merchant_id", 'GET'), 5);
        $merchant_response_guest = $merchant_controller->processV2Request();
        $this->assertEquals(array(), $merchant_response_guest->user_favorites, "Should dont have found favorites section");
    }


    function testFullProcessOfOrderingForGuestUser()
    {
        $user = logTestUserIn(1);
        $guest_user_data['first_name'] = "bob";
        $guest_user_data['email'] = 'testguestuser_' . generateAlphaCode(10) . '@dummy.com';
        $guest_user_data['contact_no'] = rand(1111111111, 9999999999);
        $guest_user_data['is_guest'] = true;
        $request = createRequestObject('/apiv2/users', 'POST', json_encode($guest_user_data));
        $user_controller = new UserController($mt, $user, $request);
        $resource = $user_controller->processV2Request();

        $user = logTestUserIn($resource->user_id);

        $data['cc_number'] = '4111111111111111';
        $data['cc_exp_date'] = '0620';
        $data['cvv'] = '023';
        $data['zip'] = '12345';
        $request = createRequestObject("/apiv2/users/" . $user['uuid'], 'POST', json_encode($data));
        $user_controller = new UserController($m, $user, $request);
        $user_save_response = $user_controller->processV2Request();
        $this->assertNull($user_save_response->error);

        $user_after_cc_save = getUserFromId($user['user_id']);

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id']);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);

        //check user has flags 1C21000021
        $user_adapter = new UserAdapter($m);
        $user_record = $user_adapter->getRecordFromPrimaryKey($user['user_id']);
        $this->assertNotNull($user_record);
        $this->assertEquals('1C21000021', $user_record['flags']);

        $order_resource = placeOrderFromCheckoutResource($checkout_resource, $user, $merchant_id, 0.00, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);

        //now check to make sure the flags were reset
        $user_after = getUserFromId($user['user_id']);
        $this->assertEquals('1000000021', $user_after['flags'], 'It should have guest flags after the order without CC info');
    }

    function testForNotRetrieveSomeDataForGuestUser(){
        $user_resource = createGuestUser();
        $user = logTestUserResourceIn($user_resource);

        $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'] = 'hackedthissorry';
        $user_session_controller = new UsersessionController($mt, $user, $r, 5);
        $user_session_resource = $user_session_controller->getUserSession($user_resource);

        $this->assertNull($user_session_resource->error);
        //do not retrieve delivery location when guest initialize the session
        $this->assertNull($user_session_resource->delivery_locations);
        //do not retrieve loyalty when guest initialize the session
        $this->assertNull($user_session_resource->loyalty_number);
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


        $skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty('guesttestskin', 'guesttestbrand');
        setContext('com.splickit.guesttestskin');
        $ids['skin_id'] = $skin_resource->skin_id;
        $skin_resource->base_url = "https://sumdum.domain.com";
        $skin_resource->save();

        $brand = Resource::find(new BrandAdapter($m), $skin_resource->brand_id, $op);
        $brand->last_orders_displayed = 1;
        $brand->save();


        $menu_id = createTestMenuWithNnumberOfItems(1);
        $ids['menu_id'] = $menu_id;

        $merchant_resource = createNewTestMerchant($menu_id);
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $ids['merchant_id'] = $merchant_resource->merchant_id;

        //MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);


        $_SERVER['log_level'] = 5;
        $_SERVER['unit_test_ids'] = $ids;
    }

    static function tearDownAfterClass()
    {
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->rollback();
    }

    /* mail method for testing */
    static function main()
    {
        $suite = new PHPUnit_Framework_TestSuite(__CLASS__);
        PHPUnit_TextUI_TestRunner::run($suite);
    }

}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    GuestUserFunctionsTest::main();
}

?>