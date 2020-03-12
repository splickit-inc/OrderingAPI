<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class CompleteMerchantTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		setContext("com.splickit.worldhq");
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
    
	function testGetMerchantDeliveryNoDelivery()
	{
		$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
		$merchant_id = $merchant_resource->merchant_id;
		MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_id,2000,$b);

		$sql = "DELETE FROM Merchant_Payment_Type WHERE merchant_id = $merchant_id";
		$merchant_adapter = new MerchantAdapter($mimetypes);
		$merchant_adapter->_query($sql);
		$complete_merchant = new CompleteMerchant($merchant_resource->merchant_id);
		$merchant_delivery_info_resource = MerchantDeliveryInfoAdapter::getFullMerchantDeliveryInfoAsResource($merchant_resource->merchant_id);
		$merchant_delivery_info_resource->logical_delete = 'Y';
		$merchant_delivery_info_resource->save();

		$resource = $complete_merchant->getTheMerchantData('delivery');
		$this->assertEquals("We're sorry, but this location does not appear to be accepting delivery orders right now.", $resource->error);
		$this->assertEquals(500,$resource->http_code);
		return $merchant_resource->merchant_id;
	}
	
	/**
	 * @depends testGetMerchantDeliveryNoDelivery
	 */
    function testCustomMessageWithTitle($merchant_id)
    {
		$merchant_resource = SplickitController::getResourceFromId($merchant_id, 'Merchant');
		$merchant_resource->custom_menu_message = "Here is the custom merchant message.#sumdumtitle";
		$merchant_resource->save();
		
		$complete_merchant = new CompleteMerchant($merchant_id);
		$merchant = $complete_merchant->getCompleteMerchant('pickup');
		$this->assertEquals("Here is the custom merchant message.", $merchant->user_message);
		$this->assertEquals("sumdumtitle",$merchant->user_message_title);
    }
	
	/**
	 * @depends testGetMerchantDeliveryNoDelivery
	 */
	function testNoTaxRate($merchant_id)
	{
		setProperty("use_merchant_caching","false");
		MerchantPaymentTypeAdapter::createMerchantPaymentTypeRecord($merchant_id, 'cash');
		$tax_adapter = new TaxAdapter($mimetypes);
		$sql = "DELETE FROM Tax WHERE merchant_id = $merchant_id";
		$tax_adapter->_query($sql);
		$complete_merchant = new CompleteMerchant($merchant_id);
		$resource = $complete_merchant->getTheMerchantData('pickup');
		$this->assertEquals("We're sorry, this merchant has not completed their tax setup yet and cannot accept orders right now", $resource->error);
		return $merchant_id;
	}
	
	/**
	 * @depends testGetMerchantDeliveryNoDelivery
	 */
	function testAdvancedOrdering($merchant_id)
	{
		$maoa = new MerchantAdvancedOrderingInfoAdapter($m);
		$resource = Resource::createByData($maoa, array("merchant_id"=>$merchant_id));
		$complete_merchant = new CompleteMerchant($merchant_id);
		$this->assertFalse($complete_merchant->getMerchantAdvancedOrderingInfo(9999999));
		$info = $complete_merchant->getMerchantAdvancedOrderingInfo($merchant_id);
		$this->assertEquals($merchant_id, $info['merchant_id']);
		return $merchant_id;
	}
	
    function testIsMerchantOpenAtThisTime()
	{
		$merchant_id = $this->ids['merchant_id'];
		$complete_merchant = new CompleteMerchant($merchant_id);
		$time_stamp = getTimeStampForDateTimeAndTimeZone(5, 0, 0, 2, 13, 2013, 'America/Denver');
		$complete_merchant->setTimeForTesting($time_stamp);
		$merchant_data = $complete_merchant->getTheMerchantData('pickup');
		$weeks_open_close_ts = $complete_merchant->open_close_ts_for_next_7_days_including_today;
		
		$user_message = $complete_merchant->isMerchantOpenAtThisTime($weeks_open_close_ts, $time_stamp);
		$this->assertEquals('Merchant is currently closed and will open at 7:00 am', $user_message);
		
		$time_stamp = getTimeStampForDateTimeAndTimeZone(10, 0, 0, 2, 13, 2013, 'America/Denver');
		$user_message = $complete_merchant->isMerchantOpenAtThisTime($weeks_open_close_ts, $time_stamp);
		$this->assertEquals('merchant is open', $user_message);
		
		$time_stamp = getTimeStampForDateTimeAndTimeZone( 19, 30, 0, 2, 13, 2013, 'America/Denver');
		$user_message = $complete_merchant->isMerchantOpenAtThisTime($weeks_open_close_ts, $time_stamp);
		$this->assertEquals('Please note that this merchant will close for pickup at 8:00 pm today.', $user_message);
		
	}

	function testCheckClosedMessage()
	{	
		setProperty("use_merchant_caching","true");
		
		$user_resource = createNewUser();
		$this->user = logTestUserIn($user_resource->user_id);
		$merchant_id = $this->ids['merchant_id'];
//		PhpFastCache::$storage = "files";
//    	PhpFastCache::delete('merchant-'.$merchant_id.'-pickup');
    	SplickitCache::deleteCacheFromKey('merchant-'.$merchant_id.'-pickup');
    	
		$time_stamp = getTimeStampForDateTimeAndTimeZone(3, 0, 0, 2, 10, 2013, 'America/Denver');
		$complete_merchant = new CompleteMerchant($merchant_id);
		
		$complete_merchant->setTimeForTesting($time_stamp);
		$merchant_resource = $complete_merchant->getCompleteMerchant("pickup");
		$cache = $merchant_resource->using_cached_merchant;
		$user_message = $merchant_resource->user_message;
		$this->assertEquals("Merchant is currently closed and will open at 7:00 am", $user_message);
	}
	
	function testCheckClosedMessageWithCache()
	{
		$user_resource = createNewUser();
		$this->user = logTestUserIn($user_resource->user_id);
		$merchant_id = $this->ids['merchant_id'];
		
		$time_stamp = getTimeStampForDateTimeAndTimeZone(19, 30, 0, 2, 10, 2013, 'America/Denver');		
    	
		$complete_merchant = new CompleteMerchant($merchant_id);
		$complete_merchant->setTimeForTesting($time_stamp);

		$merchant_resource = $complete_merchant->getCompleteMerchant("pickup");
		$cache = $merchant_resource->using_cached_merchant;
		$this->assertTrue($cache,"should have been using the cached merchant");
		$this->assertEquals('true',$merchant_resource->menu['using_cached_menu'],'It should be using a cached menu');
		$user_message = $merchant_resource->user_message;
		$this->assertEquals("Please note that this merchant will close for pickup at 8:00 pm today.", $user_message);

		// see if caching of menu changes with menuKey
		$menu_id = $this->ids['menu_id'];
		$menu_resource = Resource::find(new MenuAdapter(getM()),"$menu_id");
		$menu_resource->last_menu_change = $menu_resource->last_menu_update + 5;
		$menu_resource->save();

        $complete_merchant = new CompleteMerchant($merchant_id);
        $complete_merchant->setTimeForTesting($time_stamp);

        $merchant_resource = $complete_merchant->getCompleteMerchant("pickup");
        $cache = $merchant_resource->using_cached_merchant;
        $this->assertTrue($cache,"should have been using the cached merchant");
        $this->assertEquals('false',$merchant_resource->menu['using_cached_menu'],"It should NOT be using a cache menu");


    }

	function testCompleteMerchantObject()
	{	
		logTestUserIn($this->ids['user_id']);
		setProperty('use_merchant_caching', "true");
		$merchant_id = $this->ids['merchant_id'];
		$merchant_controller = new MerchantController($mt, $u, $r,5);
		$complete_merchant_load = new CompleteMerchant($merchant_id);
		sleep(1);
		$merchant_controller->touchMerchantRecord($merchant_id);
		$complete_merchant = new CompleteMerchant($merchant_id);
		$merchant_resource = $complete_merchant->getCompleteMerchant("pickup");
		$this->assertFalse($merchant_resource->using_cached_merchant,"should NOT have been using a cached version of the merchant");
		$complete_merchant2 = new CompleteMerchant($merchant_id);
		$merchant_resource2 = $complete_merchant2->getCompleteMerchant("pickup");
		$this->assertTrue($merchant_resource2->using_cached_merchant,"should have been using a cached version of the merchant");
		$this->assertTrue(is_array($merchant_resource2->payment_types));
		$this->assertTrue(is_array($merchant_resource2->the_weeks_hours));
		$this->assertEquals(7,count($merchant_resource2->the_weeks_hours));
		$this->assertTrue(is_array($merchant_resource2->todays_hours));
		$this->assertEquals(2,count($merchant_resource2->todays_hours));
		$this->assertTrue(is_array($merchant_resource2->menu));
	}
	
	function testGetMerchantMenuBadID()
	{
		myerror_log("starting: ".__FUNCTION__);
		$request = new Request();
		$request->url = "/merchants/8768768";
		$merchant_controller = new MerchantController($mt, $this->user, $request,5);
		$resource = $merchant_controller->getMerchant();
		$this->assertEquals('Merchant 8768768 does not exist for context: World Hq', $resource->error);
	}
	
	function testSetLastOrderMerchantIdOnUser()
	{
		
		$user = logTestUserIn($this->ids['user_id']);
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'no note');
		$order_resource = placeOrderFromOrderData($order_data, getTodayTwelveNoonTimeStampDenver());
		$this->assertNull($order_resource->error);
		
		//validate user's last ordered value was set.
		$user_resource = SplickitController::getResourceFromId($user['user_id'], 'User');
		$this->assertEquals($this->ids['merchant_id'], $user_resource->last_order_merchant_id,"last order merchant id should have been set on the user");
		
		//now lets get the menu for a different merchant and make sure the user gets a message alerting them
		$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
		$request = new Request();
		$request->url = "/merchants/".$merchant_resource->merchant_id;
		
		// create the cache
		
		$user = logTestUserIn($this->ids['user_id']);
		$merchant_controller2 = new MerchantController($mt, $user, $request);
		$merchant2 = $merchant_controller2->getMerchant();
		$user_message = $merchant2->user_message.' ';
		$this->assertNotContains("This is a different merchant than your last order", $user_message);
	}

	function testStatus()
	{
		$merchant_adapter = new MerchantAdapter($mimetypes);
		$ts = $merchant_adapter->getMerchantMenuStatus($this->ids['merchant_id'],$this->ids['menu_id']);
		myerror_log("ts: ".$ts);
		$this->assertTrue($ts > 0);
		
	}

	function testMerchantWithLastOrdersByMerchantAndUser()
    {
        setContext('com.splickit.worldhq');
        setProperty('DO_NOT_CHECK_CACHE', "true", true);
        $brand = Resource::find(new BrandAdapter($m), 300, $op);
        $ids['skin_id'] = $skin_resource->skin_id;
        $user = logTestUserIn($this->ids['user_id']);
        $menu_id = createTestMenuWithNnumberOfItems(5);
        $merchant_resource = createNewTestMerchant($menu_id, array('brand_id' => $brand->brand_id));



        $menu = CompleteMenu::getCompleteMenu($menu_id, 'Y', 0, 2);
        $item_size = $menu['menu_types'][0]['menu_items'][0]['size_prices'][0];
        $modifiers = CompleteMenu::getAllModifierItemSizesAsArray($menu_id, 'Y', 0);
        $modifier_size = $modifiers[0];
        $merchant_id = $merchant_resource->merchant_id;

        $order_adapter = new OrderAdapter($mimetypes);

        $favorite_data = '{"note":"order note","lead_time":"15","merchant_id":"' . $merchant_id . '","tax_amt":"0.00","grand_total":"13.16","tip":"0.00","favorite_name":"burrito","user_id":"' . $user['user_id'] . '","sub_total":"13.16","delivery":"no","items":[{"quantity":1,"note":"item note","size_id":"' . $item_size['size_id'] . '","mods":[{"mod_quantity":1,"mod_item_id":"' . $modifier_size['modifier_item_id'] . '","mod_sizeprice_id":"' . $modifier_size['modifier_size_id'] . '"}],"sizeprice_id":"' . $item_size['item_size_id'] . '","item_id":"' . $item_size['item_id'] . '"}],"total_points_used":"0","promo_code":""}';

        $favorite_json = str_replace($this->ids['user_id'], $user['user_id'], str_replace("burrito", 'favorite 1', $favorite_data));
        $fave_data['merchant_id'] = $merchant_id;
        $fave_data['user_id'] = $user['user_id'];
        $fave_data['favorite_name'] = 'favorite 1';
        $fave_data['favorite_json'] = $favorite_json;
        Resource::createByData(new FavoriteAdapter($m), $fave_data);


        $request = new Request();
        $request->url = "/apiv2/merchants/$merchant_id?log_level=5";
        $request->method = 'GET';
        $merchant_controller = new MerchantController($mt, $user, $request, 5);

        $time = strtotime('10:32 AM');

        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', '');
        $order_resource1 = placeOrderFromOrderData($order_data, $time);
        $order_resource1->set('status', 'E');
        $order_resource1->save();

        $order_data1 = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', '');
        $order_resource2 = placeOrderFromOrderData($order_data1, $time + (60 * 60));
        $order_resource2->set('status', 'E');
        $order_resource2->save();

        $order_data2 = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'notes 1');
        $order_resource3 = placeOrderFromOrderData($order_data2, $time + (60 * 60 * 2));
        $order_resource3->set('status', 'E');
        $order_resource3->save();

        $order_data3 = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'notes 2');
        $order_resource4 = placeOrderFromOrderData($order_data3, $time + (60 * 60 * 3));
        $order_resource4->set('status', 'O');
        $order_resource4->save();

        $order_data4 = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', 'notes 3');
        $order_resource5 = placeOrderFromOrderData($order_data4, $time + (60 * 60 * 4));
        $order_resource5->set('status', 'E');
        $order_resource5->save();

        $resource = $merchant_controller->processV2Request();

        $this->assertNull($resource->user_last_orders, "last_order_displayed is 0, should be not return any orders in 'last_orders' filed of merchant data");

        $brand->set('last_orders_displayed', 2);
        $brand->save();

        setContext('com.splickit.worldhq');
        $resource = $merchant_controller->processV2Request();

        $this->assertCount(2, $resource->user_last_orders, "last_order_displayed is 2, should be not return 2 orders in 'last_orders' filed of merchant data");

        //verify the order desc

        $last_order_data = $resource->user_last_orders;

        $this->assertEquals("notes 3", $last_order_data[0]['order']['note']);
        $this->assertEquals("notes 1", $last_order_data[1]['order']['note']);


        $stuff['menu'] = $menu;
        $stuff['merchant_id'] = $merchant_id;
        $stuff['user_id'] = $user['user_id'];

        return $stuff;
    }

    /**
     * @depends testMerchantWithLastOrdersByMerchantAndUser
     */
    function testNoOrdersIfTheyAreInvalid($stuff)
	{
		$menu = $stuff['menu'];
		$menu_id = $menu['menu_id'];
		$merchant_id = $stuff['merchant_id'];
		$user_id = $stuff['user_id'];

		$user = logTestUserIn($user_id);

        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu['menu_id'], 'Y', getM());
        $item_id = $item_records[0]['item_id'];
        $item_resource = Resource::find(new ItemAdapter(getM()),"$item_id");
        $item_resource->logical_delete = 'Y';
        $item_resource->save();
        $sp_options[TONIC_FIND_BY_METADATA]['item_id'] = $item_id;
        $o_item_size_resources = Resource::findAll(new ItemSizeAdapter(getM()),null,$sp_options);
        $this->assertCount(1,$o_item_size_resources);
        $o_item_size_resource = $o_item_size_resources[0];
        $o_item_size_resource->active = 'N';
        $o_item_size_resource->logical_delete = 'Y';
        $o_item_size_resource->save();

        $key = "menu-$menu_id-Y-$merchant_id-V2-Pickup-WorldHq";
		SplickitCache::deleteCacheFromKey($key);
//		$complete_menu = CompleteMenu::getCompleteMenu($menu_id,'Y',$merchant_id,2,'pickup');
//		myerror_log("MENU: ".json_encode($complete_menu));

        $request = new Request();
        $request->url = "/apiv2/merchants/$merchant_id?log_level=5";
        $request->method = 'GET';
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);

        $resource_with_invalid = $merchant_controller->processV2Request();


		$this->assertNull($resource_with_invalid->user_last_orders,"all orders are invalid and not have last orders");

		//add new orders
        $time = strtotime('10:32 AM');

        $order_adapter = new OrderAdapter(getM());
        $menu = CompleteMenu::getCompleteMenu($menu_id,'Y',$merchant_id,2);

        $order_data_with_new_menu = $order_adapter->getSimpleOrderArrayFromFullMenu($menu, $merchant_id, "new menu order",1);
		$order_resource_with_new_menu = placeOrderFromOrderData($order_data_with_new_menu, $time + (60*60*5));
		$this->assertNull($order_resource_with_new_menu->error);
		$order_resource_with_new_menu->set('status', 'E');
		$order_resource_with_new_menu->save();

		$order_data_with_new_menu2 = $order_adapter->getSimpleOrderArrayFromFullMenu($menu, $merchant_id, "new menu order 2",1);
		$order_resource_with_new_menu2 = placeOrderFromOrderData($order_data_with_new_menu2, $time + (60*60*6));
        $this->assertNull($order_resource_with_new_menu2->error);
		$order_resource_with_new_menu2->set('status', 'E');
		$order_resource_with_new_menu2->save();

		$_SERVER['USER_IS_STORE_TESTER_OR_BETTER'] = 'true';

		$merchant_controller_reload = new MerchantController($mt, $user, $request, 5);

		$resource_with_new_menu = $merchant_controller_reload->processV2Request();

		$this->assertNotNull($resource_with_new_menu->user_last_orders,"should be return a user_last_orders with the last valid order");

		$this->assertCount(2,$resource_with_new_menu->user_last_orders,"last_order_displayed is 2, should be only  return 2 order valid  in 'user_last_orders' filed of merchant data");

	}

	function testFavoritesAndLastOrdersByMerchantAndUserOnV1MerchantMenuCall()
	{

		setProperty('DO_NOT_CHECK_CACHE',"true",true);
		$brand = Resource::find(new BrandAdapter($m),"300",$op);
		$brand->last_orders_displayed = 0;
		$brand->save();
		setContext("com.splickit.worldhq");


		$ids['skin_id'] = $this->ids['skin_id'];
		$user = logTestUserIn($this->ids['user_id']);
		$menu_id = createTestMenuWithNnumberOfItems(5);
		$merchant_resource = createNewTestMerchant($menu_id, array('brand_id'=>$brand->brand_id));
		$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);

		$modifier_group_id = $modifier_group_resource->modifier_group_id;
		$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
		assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
		assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
		assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);

		attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
		$menu = CompleteMenu::getCompleteMenu($menu_id,'Y',0,2);
		$item_size = $menu['menu_types'][0]['menu_items'][0]['size_prices'][0];
		$modifiers = CompleteMenu::getAllModifierItemSizesAsArray($menu_id,'Y',0);
		$modifier_size = $modifiers[0];
		$merchant_id = $merchant_resource->merchant_id;

		$order_adapter = new OrderAdapter($mimetypes);

		$favorite_data ='{"note":"order note","lead_time":"15","merchant_id":"'.$merchant_id.'","tax_amt":"0.00","grand_total":"13.16","tip":"0.00","favorite_name":"burrito","user_id":"'.$user['user_id'].'","sub_total":"13.16","delivery":"no","items":[{"quantity":1,"note":"item note","size_id":"'.$item_size['size_id'].'","mods":[{"mod_quantity":1,"mod_item_id":"'.$modifier_size['modifier_item_id'].'","mod_sizeprice_id":"'.$modifier_size['modifier_size_id'].'"}],"sizeprice_id":"'.$item_size['item_size_id'].'","item_id":"'.$item_size['item_id'].'"}],"total_points_used":"0","promo_code":""}';

		$favorite_json = str_replace($this->ids['user_id'],$user['user_id'],str_replace("burrito",'favorite 1',$favorite_data));
		$fave_data['merchant_id'] = $merchant_id;
		$fave_data['user_id'] = $user['user_id'];
		$fave_data['favorite_name'] = 'favorite 1';
		$fave_data['favorite_json'] = $favorite_json;
		Resource::createByData(new FavoriteAdapter($m),$fave_data);

		$time = strtotime('10:32 AM');

		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', '');
		$order_resource1 = placeOrderFromOrderData($order_data, $time);
		$order_resource1->set('status', 'E');
		$order_resource1->save();

		$order_data1 = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'delivery', '');
		$order_resource2 = placeOrderFromOrderData($order_data1, $time + (60*60));
		$order_resource2->set('status', 'E');
		$order_resource2->save();

		$order_data2 = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'notes 1');
		$order_resource3 = placeOrderFromOrderData($order_data2, $time + (60*60*2));
		$order_resource3->set('status', 'E');
		$order_resource3->save();

		$request = createRequestObject("/app2/phone/merchants/$merchant_id",'GET', $body, "application/json");
		$merchant_controller = new MerchantController($mt, $user, $request, 5);

		$resource = $merchant_controller->processV2Request();

		$this->assertEquals(1,$resource->menu['api_version'], "should be merchant menu call V1 response");
		$this->assertNotNull($resource->menu['additional_menu_sections'], "In merchant menu call v1 should present additional menu sections");

		$this->assertCount(1, $resource->menu['additional_menu_sections']['user_favorites'], "Should have 1 valid favorite");

		$this->assertNotNull($resource->menu['additional_menu_sections']['user_last_orders'], "should be have user last orders field");

		$this->assertCount(0, $resource->menu['additional_menu_sections']['user_last_orders'], "empty array");

		$brand->set('last_orders_displayed', 2);
		$brand->save();

		setContext('com.splickit.worldhq');

		$resource = $merchant_controller->processV2Request();

		$this->assertEquals(1,$resource->menu['api_version'], "should be merchant menu call V1 response");
		$this->assertNotNull($resource->menu['additional_menu_sections'], "In merchant menu call v1 should present additional menu sections");

		$this->assertCount(2, $resource->menu['additional_menu_sections']['user_last_orders'], "two valid last orders");
		
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
    	$mysqli->begin_transaction(); ;
    	
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
    	
    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;
    }
    
	static function tearDownAfterClass()
	{
        SplickitCache::flushAll();
        $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
        date_default_timezone_set($_SERVER['starting_tz']);
	}

	static function main()
	{
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    CompleteMerchantTest::main();
}

?>
