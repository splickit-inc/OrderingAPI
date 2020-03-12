<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class FavoritesTest extends PHPUnit_Framework_TestCase
{
  var $stamp;
  var $ids;

  function setUp()
  {
    $_SERVER['HTTP_NO_CC_CALL'] = 'true';
    $this->stamp = $_SERVER['STAMP'];
    $_SERVER['STAMP'] = __CLASS__ . '-' . $_SERVER['STAMP'];
    $this->ids = $_SERVER['unit_test_ids'];
    setContext('com.splickit.worldhq');

  }

  function tearDown()
  {
    //delete your instance
    $_SERVER['STAMP'] = $this->stamp;
    unset($this->ids);
  }

  function testConvertV1JsonToV2Json()
  {
    $v1_favorite_json = $this->ids['favorite_json'];
    $expected_v2_json_array = json_decode($this->ids['expected_new_favorite_json'], true);
    $favorite_controller = new FavoriteController($m, $u, $r, 5);
    FavoriteController::clearPriceIdsFromItemsOfFavorite($expected_v2_json_array["items"]);
    $processed_json_array = json_decode($favorite_controller->convertV1FavoriteJsonToV2Json($v1_favorite_json), true);
    $this->assertEquals($expected_v2_json_array, $processed_json_array, "should have converted the json");
  }

  function testConvertV1JsonToV2JsonWithNoModItem()
  {
    $ids = $this->ids;
    $modifier_size = $ids['modifier_size'];
    $item_size = $ids['item_size'];
    $v1_favorite_json = '{"note":"order note","lead_time":"15","merchant_id":"' . $ids['merchant_id'] . '","tax_amt":"0.00","grand_total":"13.16","tip":"0.00","favorite_name":"burrito","user_id":"' . $ids['user_id'] . '","sub_total":"13.16","delivery":"no","items":[{"quantity":1,"note":"item note","size_id":"' . $item_size['size_id'] . '","mods":[{"mod_quantity":1,"mod_sizeprice_id":"' . $modifier_size['modifier_size_id'] . '"}],"sizeprice_id":"' . $item_size['item_size_id'] . '","item_id":"' . $item_size['item_id'] . '"}],"total_points_used":"0","promo_code":""}';
    $expected_v2_json_array = json_decode($this->ids['expected_new_favorite_json'], true);
    FavoriteController::clearPriceIdsFromItemsOfFavorite($expected_v2_json_array["items"]);
    $favorite_controller = new FavoriteController($m, $u, $r, 5);
    $processed_json_array = json_decode($favorite_controller->convertV1FavoriteJsonToV2Json($v1_favorite_json), true);
    $this->assertEquals($expected_v2_json_array, $processed_json_array, "should have converted the json");
  }

  function testConvertV1ItemFavoriteToV2()
  {
    $item_size = $this->ids['item_size'];
    $v1_favorite_json = $this->ids['favorite_json'];
    $v1_favorite_array = json_decode($v1_favorite_json, true);
    $item = $v1_favorite_array['items'][0];
    unset($item['mods']);
    $favorite_controller = new FavoriteController($m, $u, $r, 5);
    $new_item = $favorite_controller->convertV1FavoriteItemArrayToV2($item);
    $expected_new_item_array = array('quantity' => 1, "note" => "item note", "item_id" => $this->ids['item_size']['item_id'], "size_id" => $this->ids['item_size']['size_id'], "sizeprice_id" => $this->ids['item_size']['item_size_id']);
    $this->assertEquals($expected_new_item_array, $new_item, "SHould have created the super set item (without mods)");
  }

  function testConvertV1ModifierFavoriteToV2()
  {
    $modifier_size = $this->ids['modifier_size'];
    $v1_favorite_json = $this->ids['favorite_json'];
    $v1_favorite_array = json_decode($v1_favorite_json, true);
    $modifier = $v1_favorite_array['items'][0]['mods'][0];
    $favorite_controller = new FavoriteController($m, $u, $r, 5);
    $new_modifier = $favorite_controller->convertV1FavoriteModifierArrayToV2($modifier);
    $expected_new_modifier = array("modifier_item_id" => $modifier_size['modifier_item_id'], "mod_item_id" => $modifier_size['modifier_item_id'], "mod_sizeprice_id" => $modifier_size['modifier_size_id'], "quantity" => 1, "mod_quantity" => 1);
    $this->assertEquals($expected_new_modifier, $new_modifier, "modifier array should be in the super set format");
  }


  function testCreateFavoriteV1()
  {
    $ids = $this->ids;
    $favorite_json = $ids['favorite_json'];
    $favorite_json = str_replace($this->ids['user_id'], $this->ids['user_uuid'], $favorite_json);
    $favorite_json = str_replace('burrito', 'burrito2', $favorite_json);

    // /app2/phone/favorites/
    $request = createRequestObject('/phone/favorites/', 'post', $favorite_json, 'application/json');
    $user = logTestUserIn($ids['user_id']);
    $favorite_controller = new FavoriteController($m, $user, $request);
    $favorite = $favorite_controller->process();
    $this->assertNotNull($favorite->favorite_id);
    $this->assertEquals("Your favorite was successfully stored.", $favorite->user_message);
    return FavoriteAdapter::staticGetRecordByPrimaryKey($favorite->favorite_id, 'FavoriteAdapter');
  }

  /**
   * @depends testCreateFavoriteV1
   */
  function testMechantIdSaved($favorite)
  {
    $this->assertEquals($this->ids['merchant_id'], $favorite['merchant_id']);
  }

  /**
   * @depends testCreateFavoriteV1
   */
  function testMenuIdSaved($favorite)
  {
    $this->assertEquals($this->ids['menu_id'], $favorite['menu_id'], "Menu Id should have been saved");
  }

  /**
   * @depends testCreateFavoriteV1
   */
  function testV1FavoriteToSuperSetOnSave($favorite)
  {
    $saved_favorite_as_array = json_decode($favorite['favorite_json'], true);
    $expected_array = json_decode($this->ids['expected_new_favorite_json'], true);
    //FavoriteController::clearPriceIdsFromItemsOfFavorite($expected_array["items"]);
    // replace the burrito with burrito2 since we did somethign difernt here
    $expected_array['favorite_name'] = 'burrito2';
    $this->assertEquals($expected_array, $saved_favorite_as_array, "saved favorite should be equal to the super set");
  }

  private function createOrder($user_id, $merchant_id)
  {
    $user = logTestUserIn($user_id);
    $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'sumdumnote skip hours');
    $order_data['items'][0]['note'] = 'item note';
    $mod = $order_data['items'][0]['mods'][0];
    unset($order_data['items'][0]['mods']);
    $order_data['items'][0]['mods'][] = $mod;
    return placeOrderFromOrderData($order_data, $time_stamp);
  }

  function testCreateOrderForTesting()
  {
    $ids = $this->ids;
    $order_resource = $this->createOrder($ids['user_id'], $ids['merchant_id']);
    $this->assertNull($order_resource->error);
    $order_id = $order_resource->order_id;
    return $order_id;
  }

  /**
   * @depends testCreateOrderForTesting
   */
  function testCreateSuperSetItemArrayFromOrderDetail($order_id)
  {
    $complete_order = CompleteOrder::staticGetCompleteOrder($order_id, $m);
    $order_item = $complete_order['order_details'][0];
    $favorite_controller = new FavoriteController($m, $u, $r, 5);
    $new_item_array = $favorite_controller->convertOrderDetailToFavoriteItem($order_item);
    $this->assertTrue(isset($new_item_array['mods']), "should have the modifier section");
    unset($new_item_array['mods']);
    $expected_new_item_array = array('quantity' => '1', "note" => "item note", "item_id" => $order_item['item_id'], "size_id" => $order_item['size_id'], "sizeprice_id" => $order_item['item_size_id']);
    $this->assertEquals($expected_new_item_array, $new_item_array, "SHould have created the super set item (without mods)");
  }

  /**
   * @depends testCreateOrderForTesting
   */
  function testCreateSuperSetModifierArrayFromOrderDetailModifier($order_id)
  {
    $complete_order = CompleteOrder::staticGetCompleteOrder($order_id, $m);
    $order_item = $complete_order['order_details'][0];
    $ordered_modifier = $order_item['order_detail_complete_modifier_list_no_holds'][0];
    $favorite_controller = new FavoriteController($m, $u, $r, 5);
    $new_modifier_array = $favorite_controller->convertOrderedModifierToSupeSetModifier($ordered_modifier);
    $expected_new_modifier = array("modifier_item_id" => $ordered_modifier['modifier_item_id'], "mod_item_id" => $ordered_modifier['modifier_item_id'], "mod_sizeprice_id" => $ordered_modifier['modifier_size_id'], "quantity" => 1, "mod_quantity" => 1);
    $this->assertEquals($expected_new_modifier, $new_modifier_array, "modifier array should be in the super set format");
  }

  /**
   * @depends testCreateOrderForTesting
   */
  function testCreateFavoriteFromOrderId($order_id)
  {
    $complete_order = CompleteOrder::staticGetCompleteOrder($order_id, $m);

    // add dummy row
    $oda = new OrderDetailAdapter($m);
    $sql = "Insert INTO Order_Detail (`order_id`,`item_name`,`name`,`item_total_w_mods`) VALUES ($order_id,'test name','test name',-10.00)";
    $oda->_query($sql);

    $user = logTestUserIn($complete_order['user_id']);

    $request = new Request();
    $request->url = "http://localhost/app2/apiv2/favorites?log_level=5";
    $request->body = json_encode(array("order_id" => $order_id, "favorite_name" => 'sumdumname'));
    $request->method = 'post';
    $request->mimetype = 'application/json';

    $favorite_controller = new FavoriteController($m, $user, $request, 5);
    $favorite = $favorite_controller->processV2Request();
    //$favorite = $favorite_controller->saveFavoriteFromOrderId($order_id,'sumdumname');
    $this->assertNotNull($favorite->favorite_id, "should have gotten back a resource with the favorite id");
    $this->assertEquals("Your favorite was successfully stored.", $favorite->user_message);

    $favorite_record = FavoriteAdapter::staticGetRecordByPrimaryKey($favorite->favorite_id, 'FavoriteAdapter');
    $this->assertEquals('sumdumname', $favorite_record['favorite_name']);
    $this->assertEquals($this->ids['menu_id'], $favorite_record['menu_id']);
    $favorite_array = json_decode($favorite_record['favorite_json'], true);
    $this->assertEquals($this->ids['merchant_id'], $favorite_array['merchant_id']);
    $this->assertContains('sumdumnote', $favorite_array['note']);
    $this->assertCount(1, $favorite_array['items'], "It should only have 1 item");
    $item = $favorite_array['items'][0];
    $this->assertCount(6, $item, "shoudl be an array of 6");

  }

  function testCreateFavoriteWithBadOrderData()
  {
    $user_resource = createNewUserWithCC();
    $user = logTestUserResourceIn($user_resource);
    $merchant_id = $this->ids['merchant_id'];
    $order_resource = $this->createOrder($user['user_id'], $merchant_id);
    $order_id = $order_resource->order_id;

    // now arbitrarilly set the item_size_id to something random
    $sql = "UPDATE Order_Detail SET item_size_id = 88888888 WHERE order_id = $order_id";
    $oda = new OrderDetailAdapter($m);
    $r = $oda->_query($sql);

    $user = logTestUserIn($user['user_id']);

    $request = new Request();
    $request->url = "http://localhost/app2/apiv2/favorites?log_level=5";
    $request->body = json_encode(array("order_id" => $order_resource->order_id, "favorite_name" => 'sumdumname'));
    $request->method = 'post';
    $request->mimetype = 'application/json';

    $favorite_controller = new FavoriteController($m, $user, $request, 5);
    $favorite_resource = $favorite_controller->processV2Request();
    $this->assertNotNull($favorite_resource->error);
    $this->assertEquals(422, $favorite_resource->http_code);
  }

  function testGetFavoritesOnMerchantCall()
  {
    $ids = $this->ids;
    $favorite_records = FavoriteAdapter::staticGetRecords(array("user_id" => $ids['user_id'], "menu_id" => $ids['menu_id']), "FavoriteAdapter");
    $this->assertCount(2, $favorite_records, "Should have found two records for this user menu combination");

    $user = logTestUserIn($this->ids['user_id']);

    $request = new Request();
    $request->url = "/apiv2/merchants/" . $this->ids['merchant_id'] . "?log_level=5";
    $request->method = 'GET';
    $merchant_controller = new MerchantController($mt, $user, $request, 5);
    $resource = $merchant_controller->processV2Request();
    $this->assertNotNull($resource->user_favorites, "Should have found favorites section");
    $this->assertCount(2, $resource->user_favorites, "Should have found two records for this user menu combination");
    $favorite_hash = createHashmapFromArrayOfArraysByFieldName($resource->user_favorites, 'favorite_name');
    $favorite_one = $favorite_hash['sumdumname'];
    //$this->assertCount(3,$favorite_one,"should only have 2 fields as part of the favorite");
    $this->assertEquals('sumdumname', $favorite_one['favorite_name']);
    $this->assertTrue(isset($favorite_one['favorite_id']), "should have passed back the favorite id as part of the array");
    $this->assertTrue(isset($favorite_one['favorite_order']), "the favorite shoud be in a field called order");
    $order = $favorite_one['favorite_order'];
    $this->assertCount(2, $order);
    $this->assertContains('sumdumnote', $order['note']);
    $this->assertTrue(isset($order['items']), "there should be an item field");

    $item = $order['items'][0];
    $this->assertCount(5, $item, "should be 5 fields on the favorite item"); //remove price ids
    $mod = $item['mods'][0];
    $this->assertCount(4, $mod, "shoudl be 4 fields on the modifier"); // remove price ids
  }

  function testGetFavoriteOnMerchantCallForDifferentMerchantWithSameMenu()
  {
    $ids = $this->ids;
    $favorite_records = FavoriteAdapter::staticGetRecords(array("user_id" => $ids['user_id'], "menu_id" => $ids['menu_id']), "FavoriteAdapter");
    $this->assertCount(2, $favorite_records, "Should have found two records for this user menu combination");

    // create second merchant with same menu
    $merchant_resource = createNewTestMerchant($ids['menu_id']);
    attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    $user = logTestUserIn($this->ids['user_id']);

    $request = new Request();
    $request->url = "/apiv2/merchants/" . $this->ids['merchant_id'] . "?log_level=5";
    $request->method = 'GET';
    $merchant_controller = new MerchantController($mt, $user, $request, 5);
    $resource = $merchant_controller->processV2Request();
    $this->assertNotNull($resource->user_favorites, "Should have found favorites section");
    $this->assertCount(2, $resource->user_favorites, "Should have found two records for this user menu combination");
    $favorite_hash = createHashmapFromArrayOfArraysByFieldName($resource->user_favorites, 'favorite_name');
    $favorite_one = $favorite_hash['sumdumname'];
    $this->assertEquals('sumdumname', $favorite_one['favorite_name']);
    $this->assertTrue(isset($favorite_one['favorite_id']), "should have passed back the favorite id as part of the array");
    $this->assertTrue(isset($favorite_one['favorite_order']), "the favorite shoud be in a field called order");
    $order = $favorite_one['favorite_order'];
    $this->assertCount(2, $order);
    $this->assertContains('sumdumnote', $order['note']);
    $this->assertTrue(isset($order['items']), "there should be an item field");

    $item = $order['items'][0];
    $this->assertCount(5, $item, "should be 5 fields on the favorite item"); //remove price ids
    $mod = $item['mods'][0];
    $this->assertCount(4, $mod, "shoudl be 4 fields on the modifier"); // remove price ids

  }

  function testGetFavoritesOnlyThatMatchMenu()
  {
    $menu_id = createTestMenuWithNnumberOfItems(1);
    $complete_menu = CompleteMenu::getCompleteMenu($menu_id, 'Y');
    $item_size = $complete_menu['menu_types'][0]['menu_items'][0]['size_prices'][0];

    $ids = $this->ids;

    $fave_data['user_id'] = $ids['user_id'];
    $fave_data['menu_id'] = $menu_id;
    $fave_data['merchant_id'] = $ids['merchant_id'];
    $fave_data['favorite_name'] = "different menu_id";
    $fave_data['favorite_json'] = '{"merchant_id":"' . $ids['merchant_id'] . '","note":"order note","items":[{"quantity":1,"note":"item note","item_id":"' . $item_size['item_id'] . '","size_id":"' . $item_size['size_id'] . '","sizeprice_id":"' . $item_size['item_size_id'] . '","mods":[]}],"user_id":"' . $ids['user_id'] . '","favorite_name":"different menu_id"}';
    $fave_resource = new Resource(new FavoriteAdapter($m), $fave_data);
    $this->assertTrue($fave_resource->save());


    $favorite_records = FavoriteAdapter::staticGetRecords(array("user_id" => $ids['user_id']), "FavoriteAdapter");
    $this->assertCount(3, $favorite_records, "Should have found three records for this user combination");

    $user = logTestUserIn($this->ids['user_id']);

    $request = new Request();
    $request->url = "/apiv2/merchants/" . $ids['merchant_id'] . "?log_level=5";
    $request->method = 'GET';
    $merchant_controller = new MerchantController($mt, $user, $request, 5);
    $resource = $merchant_controller->processV2Request();
    $this->assertNotNull($resource->user_favorites, "Should have found favorites section");
    $this->assertCount(2, $resource->user_favorites, "Should have found two records for this user menu combination");

  }

  function testReturnOnlyValidFavorites()
  {
    $ids = $this->ids;

    $fave_data['user_id'] = $ids['user_id'];
    $fave_data['menu_id'] = $ids['menu_id'];
    $fave_data['merchant_id'] = $ids['merchant_id'];
    $fave_data['favorite_name'] = "invalid items for menu";
    $fave_data['favorite_json'] = '{"merchant_id":"' . $ids['merchant_id'] . '","note":"order note","items":[{"quantity":1,"note":"item note","item_id":"4875","size_id":"9857","sizeprice_id":"9857","mods":[]}],"user_id":"' . $ids['user_id'] . '","favorite_name":"different menu_id"}';
    $fave_resource = new Resource(new FavoriteAdapter($m), $fave_data);
    $this->assertTrue($fave_resource->save());


    $favorite_records = FavoriteAdapter::staticGetRecords(array("user_id" => $ids['user_id']), "FavoriteAdapter");
    $this->assertCount(4, $favorite_records, "user have four records for this user combination");

    $user = logTestUserIn($this->ids['user_id']);

    $request = new Request();
    $request->url = "/apiv2/merchants/" . $ids['merchant_id'] . "?log_level=5";
    $request->method = 'GET';
    $merchant_controller = new MerchantController($mt, $user, $request, 5);
    $resource = $merchant_controller->processV2Request();
    $this->assertNotNull($resource->user_favorites, "Should have found favorites section");
    $this->assertCount(2, $resource->user_favorites, "Should have found two records for this user menu combination");
  }

  function testGetFavoritesOnUserCall()
  {
    $ids = $this->ids;
    $merchant_id = $ids['merchant_id'];

    $user = logTestUserIn($ids['user_id']);

    $request = createRequestObject("apiv2/users/".$user['uuid']."/favorites?merchant_id=".$merchant_id."&merchant_menu_type=pickup",'GET');

    $user_controller = new UserController($mt, $user, $request,5);
    $resource = $user_controller->processV2Request();

    $this->assertNotNull($resource->data, "Should have found favorites section");
    $this->assertCount(2, $resource->data, "only include valid items");
  }

  function testGetFavoritesWithLastOrderForMobile()
  {
    $brand_id = getBrandIdFromCurrentContext();
    $brand_resource = Resource::find(new BrandAdapter($m),"$brand_id",$o);
    $brand_resource->last_orders_displayed = 1;
    $brand_resource->save();
    setContext('com.splickit.worldhq');

    $brand = getBrandForCurrentProcess();

    $ids = $this->ids;
    $merchant_id = $ids['merchant_id'];
    $user = logTestUserIn($ids['user_id']);

    $order_adapter = new OrderAdapter($m);
    $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup');
    $order_resource = placeOrderFromOrderData($order_data);
    $this->assertNull($order_resource->error);
    $this->assertTrue($order_adapter->updateOrderStatus('E',$order_resource->order_id),"Update of order status failed");

    $request = createRequestObject("apiv2/users/".$user['uuid']."/favorites?merchant_id=".$merchant_id."&merchant_menu_type=pickup",'GET');
    $request->setHeaderVariable('HTTP_X_SPLICKIT_CLIENT_DEVICE','iphone');

    $this->assertFalse($request->isRequestDeviceType('android'));
    $this->assertFalse($request->isRequestDeviceType('web'));
    $this->assertTrue($request->isRequestDeviceTypeNativeApp());
    $this->assertTrue($request->isRequestDeviceType('iphone'));


    $user_controller = new UserController($mt, $user, $request,5);
    $resource = $user_controller->processV2Request();

    $this->assertNotNull($resource->data, "Should have found favorites section");
    $this->assertCount(3, $resource->data, "should now include the last order");

    $request->setHeaderVariable('HTTP_X_SPLICKIT_CLIENT_DEVICE','web');
    $user_controller = new UserController($mt, $user, $request,5);
    $resource = $user_controller->processV2Request();

    $this->assertNotNull($resource->data, "Should have found favorites section");
    $this->assertCount(2, $resource->data, "should NOT include the last order");

    $brand_resource->last_orders_displayed = 0;
    $brand_resource->save();
  }

  function testGetAllFavoritesOnUserCallWithoutValidation()
  {
    $ids = $this->ids;
    $merchant_id = $ids['merchant_id'];

    $user = logTestUserIn($ids['user_id']);

    $request = createRequestObject("apiv2/users/".$user['uuid']."/favorites",'GET');

    $user_controller = new UserController($mt, $user, $request,5);
    $resource = $user_controller->processV2Request();
    $this->assertNull($resource->error);
    $this->assertNotNull($resource->data, "should have found favorites section");
    $this->assertCount(4, $resource->data, "should have all items");
  }

  function testGetFavoritesOnUserCallMissingParameter()
  {
    $ids = $this->ids;
    $merchant_id = $ids['merchant_id'];

    $user = logTestUserIn($ids['user_id']);

    $request = createRequestObject("apiv2/users/".$user['uuid']."/favorites?merchant_id=".$merchant_id,'GET');

    $user_controller = new UserController($mt, $user, $request,5);
    $resource = $user_controller->processV2Request();
    $this->assertEquals($resource->http_code, 422);
    $this->assertEquals($resource->error, "Missing merchant_menu_type parameter");
  }

  function testToEliminateFavoritesWithNonOfferedItem()
  {
    $ids = $this->ids;
    $fave_data['user_id'] = $ids['user_id'];
    $fave_data['menu_id'] = $ids['menu_id'];
    $fave_data['merchant_id'] = $ids['merchant_id'];
    $fave_data['favorite_name'] = "bad favorite";
    $fave_data['favorite_json'] = '{"merchant_id":"' . $ids['merchant_id'] . '","note":"order note","items":[{"quantity":1,"note":"item note","item_id":"123456789","size_id":"' . $item_size['size_id'] . '","sizeprice_id":"' . $item_size['item_size_id'] . '","mods":[{"modifier_item_id":"' . $modifier_size['modifier_item_id'] . '","mod_item_id":"' . $modifier_size['modifier_item_id'] . '","mod_sizeprice_id":"' . $modifier_size['modifier_size_id'] . '","mod_quantity":1,"quantity":1}]}],"user_id":"' . $ids['user_id'] . '","favorite_name":"bad favorite"}';
    $fave_resource = new Resource(new FavoriteAdapter($m), $fave_data);
    $fave_resource->save();
    $favorite_records = FavoriteAdapter::staticGetRecords(array("user_id" => $ids['user_id'], "menu_id" => $ids['menu_id']), "FavoriteAdapter");
    $this->assertCount(4, $favorite_records, "Should have found three records for this user menu combination");

    $user = logTestUserIn($this->ids['user_id']);

    $request = new Request();
    $request->url = "/apiv2/merchants/" . $this->ids['merchant_id'] . "?log_level=5";
    $request->method = 'GET';
    $merchant_controller = new MerchantController($mt, $user, $request, 5);
    $resource = $merchant_controller->processV2Request();
    $this->assertNotNull($resource->user_favorites, "Should have found favorites section");
    $this->assertCount(2, $resource->user_favorites, "Should have found two records for this user menu combination");
    return $fave_resource;
  }

  /**
   * @depends testToEliminateFavoritesWithNonOfferedItem
   */
  function testDeleteFavorite($favorite_resource)
  {
    $user = logTestUserIn($favorite_resource->user_id);

    $request = new Request();
    $request->url = "http://localhost/app2/apiv2/favorites/" . $favorite_resource->favorite_id . "?log_level=5";
    $request->method = 'delete';

    $favorite_controller = new FavoriteController($m, $user, $request, 5);
    $favorite = $favorite_controller->processV2Request();
    //$favorite = $favorite_controller->saveFavoriteFromOrderId($order_id,'sumdumname');
    $this->assertEquals("Your favorite was successfully deleted", $favorite->user_message);

  }

  function testNewMerchantWithSameMenuIdShouldReturnFavorites()
  {
    $user = logTestUserIn($this->ids['user_id']);
    $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    $merchant_id = $merchant_resource->merchant_id;
    attachMerchantToSkin($merchant_id, $this->ids['skin_id']);
    $request = new Request();
    $request->url = "/apiv2/merchants/$merchant_id?log_level=5";
    $request->method = 'GET';
    $merchant_controller = new MerchantController($mt, $user, $request, 5);
    $resource = $merchant_controller->processV2Request();
    $this->assertNotNull($resource->user_favorites, "Should have found favorites section");
    $this->assertCount(2, $resource->user_favorites, "Should have found two records for this user menu combination");
  }

  function testConvertV1SavedFavoriteWithoutMenuIdToSuperSetOnTheFlyWhenUserRequestsMerchantMenu()
  {
    $merchant_id = $this->ids['merchant_id'];
    $user_resource = createNewUser();
    $favorite_name = "convert fave";
    $user = logTestUserResourceIn($user_resource);
    $favorite_json = str_replace($this->ids['user_id'], $user['user_id'], str_replace("burrito", $favorite_name, $this->ids['favorite_json']));
    $fave_data['merchant_id'] = $this->ids['merchant_id'];
    $fave_data['user_id'] = $user['user_id'];
    $fave_data['favorite_name'] = $favorite_name;
    $fave_data['favorite_json'] = $favorite_json;
    $favorite_resource = Resource::createByData(new FavoriteAdapter($m), $fave_data);
    $this->assertNotFalse($favorite_resource);


    $request = new Request();
    $request->url = "/apiv2/merchants/$merchant_id?log_level=5";
    $request->method = 'GET';
    $merchant_controller = new MerchantController($mt, $user, $request, 5);
    $resource = $merchant_controller->processV2Request();
    $this->assertNotNull($resource->user_favorites, "Should have found favorites section");
    $this->assertCount(1, $resource->user_favorites, "Should have found two records for this user menu combination");

    $favorite_one = $resource->user_favorites[0];
    $this->assertEquals($favorite_name, $favorite_one['favorite_name']);
    $this->assertTrue(isset($favorite_one['favorite_order']), "the favorite shoud be in a field called order");
    $order = $favorite_one['favorite_order'];
    $this->assertCount(2, $order);
    $this->assertContains('order note', $order['note']);
    $this->assertTrue(isset($order['items']), "there should be an item field");

    $item = $order['items'][0];
    $this->assertCount(5, $item, "should be 5 fields on the favorite item"); // remove price ids
    $mod = $item['mods'][0];
    $this->assertCount(4, $mod, "shoudl be 4 fields on the modifier"); // removed price ids
  }

  function testChangeMenuAttachedToMerchantShouldGetZeroFavorites()
  {
    $menu_id = createTestMenuWithNnumberOfItems(1);
    $mechant_menu_map_resource = Resource::find(new MerchantMenuMapAdapter($m), '', array(TONIC_FIND_BY_METADATA => array("merchant_id" => $this->ids['merchant_id'])));
    $mechant_menu_map_resource->menu_id = $menu_id;
    $mechant_menu_map_resource->save();
    setProperty('use_merchant_caching', 'false');
    $user = logTestUserIn($this->ids['user_id']);

    $request = new Request();
    $request->url = "/apiv2/merchants/" . $this->ids['merchant_id'] . "?log_level=5";
    $request->method = 'GET';
    $merchant_controller = new MerchantController($mt, $user, $request, 5);
    $resource = $merchant_controller->processV2Request();
    $this->assertNull($resource->user_favorites, "Should NOT have found favorites section");
  }

  static function setUpBeforeClass()
  {
    $_SERVER['request_time1'] = microtime(true);
    $tz = date_default_timezone_get();
    $_SERVER['starting_tz'] = $tz;
    date_default_timezone_set(getProperty("default_server_timezone"));
    ini_set('max_execution_time', 300);
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
    $menu = CompleteMenu::getCompleteMenu($menu_id, 'Y', 0, 2);
    $item_size = $menu['menu_types'][0]['menu_items'][0]['size_prices'][0];
    $ids['item_size'] = $item_size;
    $modifiers = CompleteMenu::getAllModifierItemSizesAsArray($menu_id, 'Y', 0);
    $modifier_size = $modifiers[0];
    $ids['modifier_size'] = $modifier_size;

    $user_resource = createNewUser(array("flags" => "1C20000001"));
    $ids['user_id'] = $user_resource->user_id;

    $ids['user_uuid'] = $user_resource->uuid;

    $favorite_json = '{"note":"order note","lead_time":"15","merchant_id":"' . $ids['merchant_id'] . '","tax_amt":"0.00","grand_total":"13.16","tip":"0.00","favorite_name":"burrito","user_id":"' . $ids['user_id'] . '","sub_total":"13.16","delivery":"no","items":[{"quantity":1,"note":"item note","size_id":"' . $item_size['size_id'] . '","mods":[{"mod_quantity":1,"mod_item_id":"' . $modifier_size['modifier_item_id'] . '","mod_sizeprice_id":"' . $modifier_size['modifier_size_id'] . '"}],"sizeprice_id":"' . $item_size['item_size_id'] . '","item_id":"' . $item_size['item_id'] . '"}],"total_points_used":"0","promo_code":""}';

    $ids['favorite_json'] = $favorite_json;

    $expected_new_item_array = array('quantity' => 1, "note" => "item note", "item_id" => $ids['item_size']['item_id'], "size_id" => $ids['item_size']['size_id'], "sizeprice_id" => $ids['item_size']['item_size_id']);
    $ids['expected_new_item_array'] = $expected_new_item_array;

    $expected_new_favorite_json = '{"merchant_id":"' . $ids['merchant_id'] . '","note":"order note","items":[{"quantity":1,"note":"item note","item_id":"' . $item_size['item_id'] . '","size_id":"' . $item_size['size_id'] . '","sizeprice_id":"' . $item_size['item_size_id'] . '","mods":[{"modifier_item_id":"' . $modifier_size['modifier_item_id'] . '","mod_item_id":"' . $modifier_size['modifier_item_id'] . '","mod_sizeprice_id":"' . $modifier_size['modifier_size_id'] . '","mod_quantity":1,"quantity":1}]}],"user_id":"' . $ids['user_id'] . '","favorite_name":"burrito"}';
    $ids['expected_new_favorite_json'] = $expected_new_favorite_json;


    $_SERVER['log_level'] = 5;
    $_SERVER['unit_test_ids'] = $ids;
  }

  static function tearDownAfterClass()
  {
    SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
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
  FavoritesTest::main();
}
?>
