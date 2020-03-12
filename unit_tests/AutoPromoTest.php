<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class AutoPromoTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		setContext('com.splickit.yumticket');
		
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }

    function testAutoPromoFreeDelivery()
    {
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $merchant_resource = createNewTestMerchantDelivery($menu_id);
        $merchant_id = $merchant_resource->merchant_id;

        // create type 1 promo for manual promo override test
        $promo_data = [];
        $promo_data['promo_id'] = 201;
        $promo_data['key_word'] = "The Type1 Promo,type1promo";
        $promo_data['promo_type'] = 1;
        $promo_data['description'] = 'Get 50% off';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['allow_multiple_use_per_order'] = false;
        $promo_data['valid_on_first_order_only'] = 'N';
        $promo_data['order_type'] = 'all';
        $promo_data['merchant_id'] = $merchant_id;
        $promo_data['message1'] = "Congratulations! You're getting a 25% off your order!";
        $promo_data['qualifying_amt'] = 1.00;
        $promo_data['promo_amt'] = 0.00;
        $promo_data['percent_off'] = 50;
        $promo_data['max_amt_off'] = 50.00;
        $promo_data['brand_id'] = 300;

        $request = createRequestObject("/app2/admin/promotype1",'POST',json_encode($promo_data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->createPromo();

        $pkwm_adapter = new PromoKeyWordMapAdapter(getM());
        $promo_adapter = new PromoAdapter(getM());

        $promo_id = 801;
        $sql = "INSERT INTO `Promo` VALUES($promo_id, 'X_goodcents_free_delivery', 'Free Delivery', 300, 'Y', 'N', 0, 2, 'N', 'N','delivery','2010-01-01', '2020-01-01', 2, 0, 0, 0.00, 0, 0.00, 'Y', 'N',0,300, NOW(), NOW(), 'N')";
        $promo_adapter->_query($sql);
        $pmm_resource = Resource::createByData(new PromoMerchantMapAdapter(getM()), array("merchant_id" => $merchant_id, "promo_id" => $promo_id));
        $ids['promo_merchant_map_id_type_1'] = $pmm_resource->map_id;
        $sql = "INSERT INTO `Promo_Message_Map` VALUES(null, $promo_id, 'Congratulations! You''re getting free delivery!', 'Congratulations! You''re getting free delivery!', NULL, NULL, NULL, now())";
        $promo_adapter->_query($sql);

        $promo_resource = Resource::find($promo_adapter,$promo_id);

        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, null);

//        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $menu_id, 'delivery');
//        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $menu_id, 'pickup');

       // $data = array("merchant_id" => $merchant_resource->merchant_id);

        // set merchant delivery info
//        $mdia = new MerchantDeliveryInfoAdapter(getM());
//        $mdia_resource = $mdia->getExactResourceFromData($data);
//        $mdia_resource->minimum_order = 10.00;
//        $mdia_resource->delivery_cost = 1.00;
//        $mdia_resource->delivery_increment = 15;
//        $mdia_resource->max_days_out = 4;
//        $mdia_resource->minimum_delivery_time = 45;
//        $mdia_resource->save();
//
        $mdpd = new MerchantDeliveryPriceDistanceAdapter(getM());
        $mdpd_resource = $mdpd->getExactResourceFromData(['merchant_id'=>$merchant_id]);
        $this->assertNotNull($mdpd_resource, "should have found a merchant delivery price distance resource");
        $mdpd_resource->distance_up_to = 10.0;
        $mdpd_resource->price = 8.88;
        $mdpd_resource->save();


        $user_resource = createNewUserWithCCNoCVV();
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
        //$response = $user_controller->setDeliveryAddr();
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;

        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id", 'GET', $body, $mimetype);
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range), "should have found the 'is in delivery range' field");
        $this->assertTrue($resource->is_in_delivery_range, " the is in delivery range should be True");
        $this->assertEquals($mdpd_resource->price, $resource->price);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note');
        $order_data['user_addr_id'] = $user_address_id;
        $json_encoded_data = json_encode($order_data);

        $url = '/app2/apiv2/cart';
        $request = createRequestObject($url, 'post', $json_encoded_data, 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        //$cart_resource = $place_order_controller->createNewCart();
        $cart_resource = $place_order_controller->processV2Request();
        $cart_ucid = $cart_resource->ucid;
        $this->assertNull($cart_resource->error);
        $this->assertNotNull($cart_resource, "should have gotten a cart resource back");
        //$this->assertTrue($cart_resource->insert_id > 999,"should have a valid cart id");

        $full_cart_resource = SplickitController::getResourceFromId($cart_ucid, 'Carts');

        $cart_order_id = $full_cart_resource->order_id;
        $base_order_data = CompleteOrder::getBaseOrderData($cart_order_id, getM());
        $this->assertEquals($user_address_id, $base_order_data['user_delivery_location_id']);
        $this->assertEquals(8.88, $base_order_data['delivery_amt']);

        $request = createRequestObject('/app2/apiv2/cart/' . $full_cart_resource->ucid . '/checkout','GET',null);

        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_data_resource = $place_order_controller->processV2Request();

        $this->assertEquals(-8.88,$checkout_data_resource->promo_amt,"It should deduct the amount of delivery");
        $base_order_data = CompleteOrder::getBaseOrderData($checkout_data_resource->ucid);
        $this->assertEquals($promo_id,$base_order_data['promo_id'],"it should have the auto promo applied");

        $order_resource = placeOrderFromCheckoutResource($checkout_data_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);

        $this->assertEquals(-8.88,$order_resource->promo_amt);
        return ['merchant_id'=>$merchant_id,'user_id'=>$user_id,"user_addr_id"=>$user_address_id];
    }

    /**
     * @depends testAutoPromoFreeDelivery
     */
    function testManualPromoOverride($data)
    {
        $merchant_id = $data['merchant_id'];
        $user_id = $data['user_id'];
        $user_addr_id = $data['user_addr_id'];
        $user = logTestUserIn($user_id);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note');
        $order_data['user_addr_id'] = $user_addr_id;

        $checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals(-8.88,$checkout_resource->promo_amt);
        $ucid = $checkout_resource->ucid;

        $request = createRequestObject("/app2/apiv2/cart/$ucid/checkout?promo_code=type1promo","GET");
        $place_order_controller = new PlaceOrderController(getM(), $user, $request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $new_checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($new_checkout_resource->error);
        $this->assertEquals(-0.75,$new_checkout_resource->promo_amt,'It should have the manual promo amount');
        $base_order = CompleteOrder::getBaseOrderData($ucid);
        $this->assertEquals(201,$base_order['promo_id']);

        $order_resource = placeOrderFromCheckoutResource($new_checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);

        $this->assertEquals(-0.75,$order_resource->promo_amt);
        $this->assertEquals(201,$order_resource->promo_id);

        return $data;
    }

    /**
     * @depends testManualPromoOverride
     */
    function testNonUseOfAutoPromo($data)
    {
        $merchant_id = $data['merchant_id'];
        $user_id = $data['user_id'];
        $user = logTestUserIn($user_id);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);

        $checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $this->assertEquals(0.00,$checkout_resource->promo_amt);
        $ucid = $checkout_resource->ucid;

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);
        $this->assertEquals(0.00,$order_resource->promo_amt);

        return $data;
    }

    /**
     * @depends testManualPromoOverride
     */

    function testSwitchToPickupRemoveFreeDeliveryPromo($data)
    {
        $merchant_id = $data['merchant_id'];
        $user_id = $data['user_id'];
        $user_addr_id = $data['user_addr_id'];
        $user = logTestUserIn($user_id);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'delivery', 'the note');
        $order_data['user_addr_id'] = $user_addr_id;

        $checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals(-8.88,$checkout_resource->promo_amt);
        $ucid = $checkout_resource->ucid;

        // now switch the order to pickup
        $new_order_data['submitted_order_type'] = 'pickup';

        $switch_request = createRequestObject("/app2/apiv2/cart/$ucid/checkout", 'POST', json_encode($new_order_data), 'application/json');
        $place_order_controller = new PlaceOrderController(getM(), $user, $switch_request);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $updated_cart_resource = $place_order_controller->processV2Request();
        $updated_order_record = OrderAdapter::staticGetRecord(array("ucid" => $ucid), 'OrderAdapter');

        $this->assertEquals($ucid, $updated_cart_resource->cart_ucid, "is same order");

        $this->assertEquals(0,$updated_cart_resource->delivery_amt, "not have delivery amt because is pickup order");
        $this->assertEquals(OrderAdapter::PICKUP_ORDER, $updated_order_record['order_type'], "saved pickup order");
        $this->assertEquals("0.00", $updated_order_record['delivery_amt'], "not have delivery amt because is pickup order");

        $this->assertEquals(0,$updated_cart_resource->promo_amt);
        $this->assertEquals(0,$updated_cart_resource->promo_id);
        $this->assertEquals(0.00,$updated_order_record['promo_amt'],'It should zero out the promo amt');
        $this->assertEquals(0,$updated_order_record['user_addr_id']);



    }
    
    function testStaticGetRecord()
    {
    	$record = MySQLAdapter::staticGetRecord(array("id"=>1000),'AirportsAdapter');
    	$this->assertNotNull($record);
    }
    
    function testJoinandUnjoin()
    {
    	$user_resource = createNewUser();
    	UserGroupMembersAdapter::joinGroup($this->ids['user_group_id'], $user_resource->user_id);
    	$result = UserGroupMembersAdapter::isUserAMemberOfTheGroupById($user_resource->user_id, $this->ids['user_group_id']);
    	$this->assertTrue($result);
    	
    	UserGroupMembersAdapter::unJoinGroup($this->ids['user_group_id'], $user_resource->user_id);
    	$result = UserGroupMembersAdapter::isUserAMemberOfTheGroupById($user_resource->user_id, $this->ids['user_group_id']);
    	$this->assertFalse($result);
    	
    }
    
    function testAddUserToAirlineWorkersGroup()
    {
    	$result = UserGroupMembersAdapter::isUserAMemberOfTheGroupById($this->ids['user_id'], $this->ids['user_group_id']);
    	$this->assertFalse($result);   	
    	$user = logTestUserIn($this->ids['user_id']);
    	$data = array ('group_airport_employee'=>'Y');
    	$request = new Request();
    	$request->method = "post";
    	$request->data = $data;
    	$user_controller = new UserController($mt, $user, $request,5);
    	$user_resource = $user_controller->updateUser();
    	
    	$result = UserGroupMembersAdapter::isUserAMemberOfTheGroupById($this->ids['user_id'], $this->ids['user_group_id']);
    	$this->assertTrue($result);
    }

    function testRemoveUserFromAirlineWorkersGroup()
    {
    	$result = UserGroupMembersAdapter::isUserAMemberOfTheGroupById($this->ids['user_id'], $this->ids['user_group_id']);
    	$this->assertTrue($result);
    	
    	$user = logTestUserIn($this->ids['user_id']);
    	$data = array ('group_airport_employee'=>'N');
    	$request = new Request();
    	$request->method = "post";
    	$request->data = $data;
    	$user_controller = new UserController($mt, $user, $request,5);
    	$user_resource = $user_controller->updateUser();
    	
    	$result = UserGroupMembersAdapter::isUserAMemberOfTheGroupById($this->ids['user_id'], $this->ids['user_group_id']);
    	$this->assertFalse($result);
    }
    
    function testIsUserAMemberOfAGroupById()
    {
    	UserGroupMembersAdapter::joinGroup($this->ids['user_group_id'], $this->ids['user_id']);
    	$result = UserGroupMembersAdapter::isUserAMemberOfTheGroupById($this->ids['user_id'], $this->ids['user_group_id']);
    	$this->assertTrue($result);
    	
    	$result2 = UserGroupMembersAdapter::isUserAMemberOfTheGroupById(20000, $this->ids['user_group_id']);
    	$this->assertFalse($result2);
    	
    }
    
    /**
     * @expectedException     NoMatchingGroupException
     */
 
    function testIsUserAMemberOfAGroupByGroupName()
    {
    	$user_group_name = $this->ids['user_group_name'];
    	$result = UserGroupMembersAdapter::isUserAMemberOfTheGroupByGroupName($this->ids['user_id'], $user_group_name);
    	$this->assertTrue($result);
    	
    	$result2 = UserGroupMembersAdapter::isUserAMemberOfTheGroupByGroupName(20000, $user_group_name);
    	$this->assertFalse($result2);

    	//this should throw the exception
    	$result3 = UserGroupMembersAdapter::isUserAMemberOfTheGroupByGroupName($this->ids['user_id'], "Some Dumb Group");
    }

    function testGetAutoPromoForThisUserMerchantCombination()
    {
    	$promo_controller = new PromoController($mt, $u, $r,5);
    	$user_id = $this->ids['user_id'];
    	$merchant_id = $this->ids['merchant_id'];
    	
    	$result = $promo_controller->getAutoPromoForThisUserMerchantCombination($user_id, $merchant_id);
    	$this->assertNotNull($result);
    	$this->assertTrue(is_a($result, 'Resource'));
    	$this->assertEquals($this->ids['promo_id'],$result->promo_id);
    }
    
    function testGetUserGroupOverrideValues()
    {
    	$placeorder_controller = new PlaceOrderController($mt, $u, $r,5);
    	$user_id = $this->ids['user_id'];
    	$merchant_id = $this->ids['merchant_id'];
    	
    	$result = $placeorder_controller->getUserGroupOverrideValuesIfItAppliesForThisUserMerchantCombination($user_id, $merchant_id);
    	$this->assertNotNull($result);
    	$this->assertEquals($this->ids['promo_id'],$result['promo_id']);
    	$this->assertEquals(.25,$result['convenience_fee_override']);
    	$this->assertEquals(5,$result['minimum_lead_time_override']);
    }
    
    function testGetConvenienceFeeFromValues()
    {
    	$poc = new PlaceOrderController($mt, $u, $r);
    	$merchant_cf = 1.00;
    	$user_cf = null;
    	$group_cf = null;
    	$this->assertEquals(1.00, $poc->getLowestValidConvenienceFeeFromTheseValues($merchant_cf, $user_cf, $group_cf));
    	$merchant_cf = 1.00;
    	$user_cf = .50;
    	$group_cf = null;
    	$this->assertEquals(.50, $poc->getLowestValidConvenienceFeeFromTheseValues($merchant_cf, $user_cf, $group_cf));
    	$merchant_cf = 1.00;
    	$user_cf = .50;
    	$group_cf = .25;
    	$this->assertEquals(.25, $poc->getLowestValidConvenienceFeeFromTheseValues($merchant_cf, $user_cf, $group_cf));
    	$merchant_cf = 1.00;
    	$user_cf = null;
    	$group_cf = .25;
    	$this->assertEquals(.25, $poc->getLowestValidConvenienceFeeFromTheseValues($merchant_cf, $user_cf, $group_cf));
    	$merchant_cf = 0.00;
    	$user_cf = null;
    	$group_cf = 0.25;
    	$this->assertEquals(0.00, $poc->getLowestValidConvenienceFeeFromTheseValues($merchant_cf, $user_cf, $group_cf));
    	$merchant_cf = 0.00;
    	$user_cf = 0.33;
    	$group_cf = 0.25;
    	$this->assertEquals(0.00, $poc->getLowestValidConvenienceFeeFromTheseValues($merchant_cf, $user_cf, $group_cf));
    	
    }
    
    function testGetCheckoutDataWithDiscountApplied()
    {
    	$ids = $this->ids;
    	$user_id = $ids['user_id'];
    	$merchant_id = $ids['merchant_id'];
    	$user = logTestUserIn($user_id);
    	
    	//get promo message for validation
    	$pma = new PromoMessageMapAdapter($mimetypes);
    	$promo_messages = $pma->getRecord(array("promo_id"=>$ids['promo_id']));
    	$this->assertNotNull($promo_messages,"Should have founds some promo messages");

 		// set merchatn convenience fee to 0.00 
    	$merchant_resource = SplickitController::getResourceFromId($merchant_id, 'Merchant');
    	$merchant_resource->trans_fee_rate = 0;
    	$merchant_resource->save();

 		$order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'dum note',2);

        $r = createRequestObject("/apiv2/cart/checkout","POST",json_encode($order_data),'application/json');
 		$placeorder_controller = new PlaceOrderController($mt, $user, $r, 5);
        $placeorder_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
 		$checkout_data_resource = $placeorder_controller->processV2Request();
        $ucid = $checkout_data_resource->ucid;
    	$this->assertEquals(0.00,$checkout_data_resource->convenience_fee);
    	
    	$merchant_resource->trans_fee_rate = 1.00;
    	$merchant_resource->save();

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'dum note',3);
        $r = createRequestObject("/apiv2/cart/$ucid/checkout","POST",json_encode($order_data),'application/json');
        $placeorder_controller = new PlaceOrderController($mt, $user, $r, 5);
        $placeorder_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_data_resource = $placeorder_controller->processV2Request();
    	$this->assertNotNull($checkout_data_resource);
    	$this->assertNotNull($checkout_data_resource->user_message,"there should have been a user messagse field");
    	$this->assertEquals($promo_messages['message1'],$checkout_data_resource->user_message);
    	$this->assertTrue($checkout_data_resource->total_tax_amt > 0.00,"Checkout data should have returned a positive tax amount");
    	$this->assertEquals(.67,$checkout_data_resource->total_tax_amt);
    	$this->assertNotNull($checkout_data_resource->discount_amt,"A discount amount should have been present in the returned checkout data");
    	$this->assertEquals(.75,$checkout_data_resource->discount_amt);
    	$this->assertEquals(.25,$checkout_data_resource->convenience_fee);

        $order_resource = placeOrderFromCheckoutResource($checkout_data_resource,$user,$merchant_id,0.00,$imte);
        $order_id = $order_resource->order_id;
    	$this->assertTrue($order_id > 1000);
    	$this->assertEquals(-0.75, $order_resource->promo_amt);
    	$this->assertEquals(.25, $order_resource->trans_fee_amt);
    	$grand_total = $order_resource->order_amt + $order_resource->promo_amt + $order_resource->total_tax_amt + $order_resource->tip_amt + $order_resource->trans_fee_amt;
    	$this->assertEquals($grand_total, $order_resource->grand_total);
    }
    
    function testUserGroupMemberRecords()
    {
    	$records = UserGroupMembersAdapter::getUserGroupRecordsThatThisUserIsAMemberOf(123456);
    	$this->assertCount(0,$records);
    	
    	$records = UserGroupMembersAdapter::getUserGroupRecordsThatThisUserIsAMemberOf($this->ids['user_id']);
    	$this->assertCount(1,$records);
    }
    
    function testUserGroupRecordsForUserId()
    {
    	$user_groups = UserGroupsAdapter::getAllGroupsInformationThatThisUserIsAMemeberOf(1234567);
    	$this->assertNull($user_groups);
    	
    	$user_groups = UserGroupsAdapter::getAllGroupsInformationThatThisUserIsAMemeberOf($this->ids['user_id']);
    	$this->assertNotNull($user_groups);
    	$this->assertCount(1, $user_groups);
    	$user_group_info_with_active = $user_groups[0];
    	$this->assertEquals($this->ids['user_group_id'],$user_group_info_with_active['id']);
    	$this->assertEquals($this->ids['user_group_name'],$user_group_info_with_active['name']);
    }
    
    function testUserGroupsOnUserSession()
    {
    	$user_resource = createNewUser();
    	$user_session_controller = new UsersessionController($mt, logTestUserIn($user_resource->user_id), $r,5);
    	$user_session = $user_session_controller->getUserSession($user_resource);
    	$this->assertTrue(isset($user_session->user_groups),"there should be a user groups section on the user session resource");
    	$this->assertCount(0, $user_session->user_groups);
    	
    	// now lets assign the user to the airport UserGroup
    	UserGroupMembersAdapter::joinGroup($this->ids['user_group_id'], $user_resource->user_id);
    	$user_session = $user_session_controller->getUserSession($user_resource);
    	$this->assertTrue(isset($user_session->user_groups));
    	$this->assertCount(1, $user_session->user_groups,'Should have found 1 user group');
    	$user_groups = $user_session->user_groups;
    	$group = $user_groups[0];
    	$this->assertEquals($this->ids['user_group_name'], $group['name']);
    	$this->assertEquals($this->ids['user_group_id'], $group['id']);
    	
    }
    
    static function setUpBeforeClass()
    {
    	// only for development
    	//$_SERVER['ENVIRONMENT'] = "local";
    	
    	$_SERVER['log_level'] = 5;
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;
    	
    	$skin_resource = createYumTicketSkin();
    	$ids['skin_id'] = $skin_resource->skin_id;

		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(1);
    	$ids['menu_id'] = $menu_id;
    	
/*    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);
*/
    	$merchant_resource = createNewTestMerchant($menu_id);
    	// created 1.00 service fee
    	$merchant_resource->trans_fee_rate = 1.00;
    	$merchant_resource->lead_time = 30;
    	$merchant_resource->save();
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;

    	$aamma = new AirportAreasMerchantsMapAdapter($mimetypes);
    	$aamma->assignMerchantToAirportArea(1001, $merchant_resource->merchant_id,"Next to Gate 35");
    	
    	//create airline workers promo
    	$ids['promo_id'] = 500;
	   	$promo_adapter = new PromoAdapter($mimetypes);
    	$sql = "INSERT INTO `Promo` VALUES(500, 'X_AirlineWorkers', 'Get 10% off', 1, 'Y', 'N', 0, 2, 'N', 'N','all', '2010-01-01', '2050-01-01', 888888888, 0, 888888888, 88888888, 0, 0.00, 'Y', 'N', 0,300,NOW(), NOW(), 'N')";
    	$promo_adapter->_query($sql);
    	$sql = "INSERT INTO `Promo_Merchant_Map` VALUES(null, 500, ".$ids['merchant_id'].", '2013-10-05', '2050-01-01', NULL, now())";
    	$pmm_resource = Resource::createByData(new PromoMerchantMapAdapter($mimetypes), array("merchant_id"=>$ids['merchant_id'],"promo_id"=>500));
    	$ids['promo_merchant_map_id'] = $pmm_resource->map_id;
    	$sql = "INSERT INTO `Promo_Message_Map` VALUES(null, 500, '10% Airline Workers Discount Applied. Please present your badge at pickup.', NULL, NULL, NULL, NULL, now())";
    	$promo_adapter->_query($sql);
    	$sql = "INSERT INTO Promo_Type1_Amt_Map VALUES(null,500,0.00,0.00,10,88888888,now())";
    	$promo_adapter->_query($sql);
    	    	
    	//create group
    	$user_group_name = "Airport Employees";
    	$user_group_data = array("name"=>$user_group_name,"description"=>"Airport Employees","promo_id"=>500,"convenience_fee_override"=>0.25,"minimum_lead_time_override"=>5);
    	$uga = new UserGroupsAdapter($mimetypes);
    	$options[TONIC_FIND_BY_METADATA] = $user_group_data;
    	$ug_resource = Resource::findOrCreateIfNotExists($uga, $url, $options);
    	//$ug_resource = Resource::createByData($uga, array("name"=>$user_group_name,"desciption"=>"Airport Employees","promo_id"=>500));
    	$ids['user_group_id'] = $ug_resource->id;
    	$ids['user_group_name'] = $user_group_name;
    	    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;
    	
    	//assign user to group
    	//$ugma = new UserGroupMembersAdapter($mimetypes);
    	//$ugm_resource = Resource::createByData($ugma, array('user_group_id'=>$ug_resource->id,'user_id'=>$user_resource->user_id));

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
    AutoPromoTest::main();
}

?>