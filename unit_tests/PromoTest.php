<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class PromoTest extends PHPUnit_Framework_TestCase
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
    
    function testPromoType1()
    {
    	setContext("com.splickit.pitapit");
    	$merchant_id = $this->ids['merchant_id2'];
    	$promo_id = 202;
    	$promo_merchant_map_id = $this->ids['promo_merchant_map_id_type_1_alternate'];
    	
    	$order_adapter = new OrderAdapter(getM());
 		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours');
 		$duplicate_promo_key_word = $this->ids['duplicate_promo_key_word'];
 		$order_data['promo_code'] = "$duplicate_promo_key_word";
 		
 		$json_encoded_data = json_encode($order_data);
    	$request = new Request();
    	$request->method = "post";
    	$request->body = $json_encoded_data;
    	$request->mimetype = 'Applicationjson';
    	//$request->_parseRequestBody();
    	$promo_controller = new PromoController($mt, $this->user, $request, 5);
    	$promo_resource_result = $promo_controller->validatePromo($od);
    	$this->assertNull($promo_resource_result->error);
    	$this->assertEquals(202,$promo_resource_result->promo_id);
    }

    function testCheckPromoType2()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$promo_id = $this->ids['promo_id'];
    	$promo_merchant_map_id = $this->ids['promo_merchant_map_id'];
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
    	
    	$bad_merchant_resource = createNewTestMerchant();
    	
    	$order_data['merchant_id'] = $bad_merchant_resource->merchant_id;
    	$json_bad_merchant_id_encoded_data = json_encode($order_data);
    	
    	$order_data['merchant_id'] = $merchant_id;
    	$order_data['promo_code'] = 'stupid';
    	$json_bad_promo_code_encoded_data = json_encode($order_data);
    	
    	// test bad promo code
    	$request = new Request();
    	$request->method = "post";
    	$request->body = $json_bad_promo_code_encoded_data;
    	$request->mimetype = 'Applicationjson';
    	$request->_parseRequestBody();
    	$promo_controller = new PromoController($mt, $this->user, $request, 5);
    	$promo_resource_result = $promo_controller->validatePromo($od);
    	$this->assertEquals("Sorry!  The promo code you entered, stupid, is not valid.",$promo_resource_result->error);
    	
    	//test bad merchant id
    	$request = new Request();
    	$request->method = "post";
    	$request->body = $json_bad_merchant_id_encoded_data;
    	$request->mimetype = 'Applicationjson';
    	$request->_parseRequestBody();
    	$promo_controller = new PromoController($mt, $this->user, $request, 5);
    	$promo_resource_result = $promo_controller->validatePromo($od);
    	$this->assertEquals("So sorry, this promo is not valid at this location.",$promo_resource_result->error);

    	// good promo lower case shoudl work
    	$order_data['merchant_id'] = $merchant_id;
    	$order_data['promo_code'] = 'test promo';

    	$json_encoded_data = json_encode($order_data);
    	$request = new Request();
    	$request->method = "post";
    	$request->body = $json_encoded_data;
    	$request->mimetype = 'Applicationjson';
    	$request->_parseRequestBody();
    	$promo_controller = new PromoController($mt, $this->user, $request, 5);
    	$promo_resource_result = $promo_controller->validatePromo($od);
    	$this->assertNull($promo_resource_result->error);
//*/   	
    	// now set start equal to tomorrow
    	$promo_resource->start_date = $tomorrow_date;
    	$promo_resource->save();
    	//$promo_controller = new PromoController($mt, $this->user, $request, 5);
    	$promo_resource_result = $promo_controller->validatePromo($od);
    	$this->assertEquals("Sorry this promotion has not started yet :(",$promo_resource_result->error);
    	    	
    	// now set end date to yesterday
    	$promo_resource->start_date = $longago_start_date;
    	$promo_resource->end_date = $yesterday_date;
    	$promo_resource->save();
    	//$promo_controller = new PromoController($mt, $this->user, $request, 5);
    	$promo_resource_result = $promo_controller->validatePromo($od);
    	$this->assertEquals("Sorry this promotion has expired :(",$promo_resource_result->error);
    	
    	// zero out the promo and test the merchant values
    	$promo_resource->start_date = $longago_start_date;
    	$promo_resource->end_date = $wayout_end_date;
    	$promo_resource->save();
     	
    	// valid date range
    	$pmm_resource->start_date = $yesterday_date;
    	$pmm_resource->end_date = $tomorrow_date;
    	$pmm_resource->save();
    	//$promo_controller = new PromoController($mt, $this->user, $request, 5);
    	$promo_resource_result = $promo_controller->validatePromo($od);
    	$this->assertNull($promo_resource_result->error);
    	
    	// promo expired at merchant
    	$pmm_resource->start_date = $longago_start_date;
    	$pmm_resource->end_date = $yesterday_date;
    	$pmm_resource->save();
    	//$promo_controller = new PromoController($mt, $this->user, $request, 5);
    	$promo_resource_result = $promo_controller->validatePromo($od);
    	$this->assertEquals("Sorry this promotion has expired at this merchant :(",$promo_resource_result->error);
    	
     	// promo has not yet started at merchant
    	$pmm_resource->start_date = $tomorrow_date;
    	$pmm_resource->end_date = $wayout_end_date;
    	$pmm_resource->save();
    	//$promo_controller = new PromoController($mt, $this->user, $request, 5);
    	$promo_resource_result = $promo_controller->validatePromo($od);
    	$this->assertEquals("Sorry this promotion has not started yet.  Promo begins on ".$tomorrow_date." :(",$promo_resource_result->error);

    }
	
    function testCheckPromoWithAlternateKeyWord()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	$promo_id = $this->ids['promo_id'];
    	$promo_merchant_map_id = $this->ids['promo_merchant_map_id'];
    	
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
 		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours');
 		$order_data['promo_code'] = 'Alternatekeyword';
    	$json_encoded_data = json_encode($order_data);
    	$promo_resource_result = $this->validatePromo($json_encoded_data);
    	$this->assertNull($promo_resource_result->error);
    	$expected = "Here's the deal, order a large this, then add a that to go with it, and its FREE! Limit 1";
    	$this->assertEquals($expected, $promo_resource_result->user_message);
 
    	// good promo qitem
 		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip_hours',2);
 		$order_data['promo_code'] = 'Alternatekeyword';
    	$json_encoded_data = json_encode($order_data);
    	$promo_resource_result = $this->validatePromo($json_encoded_data);
    	$this->assertNull($promo_resource_result->error);
    	$expected = "Almost there, now add a standard that to this order and its FREE!";
    	$this->assertEquals($expected, $promo_resource_result->user_message);
    		
    	// good promo qitem and pitem
 		$order_data = getSimpletCartArrayByMerchantId($merchant_id,'pickup','skip hours',3);
 		$order_data['promo_code'] = 'Alternatekeyword';
    	$json_encoded_data = json_encode($order_data);
    	$promo_resource_result = $this->validatePromo($json_encoded_data);
    	$this->assertNull($promo_resource_result->error);
    	$expected = "Congratulations! You're getting a FREE that!";
    	$this->assertEquals($expected, $promo_resource_result->user_message);
    	return $order_data;
    }
    
    /**
     * @depends testCheckPromoWithAlternateKeyWord
     */
    
    function testReturnValuesOfTaxAndSubtotalForMerchantPays($order_data)
    {
    	$order_data['tip'] = 0.00;
     	$json_encoded_data = json_encode($order_data);
    	$promo_resource_result = $this->validatePromo($json_encoded_data);
    	$this->assertEquals(1.50, $promo_resource_result->amt);
        $checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
    	$this->assertEquals(.30, $checkout_resource->total_tax_amt);
    	$this->assertEquals(-1.50, $checkout_resource->promo_amt);
    	$this->assertEquals(4.50, $checkout_resource->order_amt);

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$this->user,$order_data['merchant_id'],0.00,getTomorrowTwelveNoonTimeStampDenver());
    	$this->assertEquals(.30, $order_resource->total_tax_amt);
    	$this->assertEquals(-1.50, $order_resource->promo_amt);
    	$this->assertEquals(4.50, $order_resource->order_amt);
    	$this->assertEquals(3.30,$order_resource->grand_total);
    	
		$balance_before = 0.00;
    	$order_id = $order_resource->order_id;
    	$new_user_resource = getUserResourceFromId($order_data['user_id']);
    	$user_id = $new_user_resource->user_id;
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
    	
    	$promo_controller = new PromoController($mt, $user, $request, 5);
    	$promo_resource_result = $promo_controller->validatePromo($order_data);
    	$this->assertEquals(1.50, $promo_resource_result->amt);
        $checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
    	$this->assertEquals(.45, $checkout_resource->total_tax_amt);
    	$this->assertEquals(-1.50, $checkout_resource->promo_amt);
    	$this->assertEquals(4.50, $checkout_resource->order_amt);

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$order_data['merchant_id'],0.00,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
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
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	
    	$skin_resource = createWorldHqSkin();
    	$brand_id = $skin_resource->brand_id;
    	$ids['skin_id'] = $skin_resource->skin_id;
    	
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;

    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
/*    	
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);
*/
    	$merchant_resource = createNewTestMerchant($menu_id);
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;

    	$promo_type = "all";
    	//create the type 2 promo 
    	$ids['promo_id'] = 200;
	   	$promo_adapter = new PromoAdapter($mimetypes);
    	$sql = "INSERT INTO `Promo` VALUES(200, 'The Promo', 'Get a free this when you purchase a large that', 2, 'Y', 'N', 0, 2, 'N', 'N','$promo_type', '2010-01-01', '2020-01-01', 1, 0, 0, 0.00, 0, 0.00, 'Y', 'N',0,$brand_id, NOW(), NOW(), 'N')";
    	$promo_adapter->_query($sql);
    	$sql = "INSERT INTO `Promo_Merchant_Map` VALUES(null, 200, $merchant_id, '2013-10-05', '2020-01-01', NULL, now())";
    	$pmm_resource = Resource::createByData(new PromoMerchantMapAdapter($mimetypes), array("merchant_id"=>$merchant_id,"promo_id"=>200));
    	$ids['promo_merchant_map_id'] = $pmm_resource->map_id;
    	$sql = "INSERT INTO `Promo_Message_Map` VALUES(null, 200, 'Congratulations! You''re getting a FREE that!', NULL, NULL, 'Almost there, now add a standard that to this order and its FREE!', 'Here''s the deal, order a large this, then add a that to go with it, and its FREE! Limit 1', now())";
    	$promo_adapter->_query($sql);
    	$sql = "INSERT INTO `Promo_Type2_Item_Map` VALUES(null, 200, 0.00, 'qitem000000200', 'pitem000000200', NULL, 'item-".$item_records[1]['item_id']."', 'item-".$item_records[2]['item_id']."','2012-01-13 01:25:16')";
    	$promo_adapter->_query($sql);
    	
    	$pkwm_adapter = new PromoKeyWordMapAdapter($mimetypes);
    	Resource::createByData($pkwm_adapter, array("promo_id"=>200,"promo_key_word"=>"Test Promo","brand_id"=>300));
    	Resource::createByData($pkwm_adapter, array("promo_id"=>200,"promo_key_word"=>"AlternateKeyWord","brand_id"=>300));
    	
    	//create the type 1 promo 
    	$ids['promo_id_type_1'] = 201;
	   	$sql = "INSERT INTO `Promo` VALUES(201, 'The Type1 Promo', 'Get 25% off', 1, 'Y', 'N', 0, 2, 'N', 'N','$promo_type', '2010-01-01', '2020-01-01', 1, 0, 0, 0.00, 0, 0.00, 'Y', 'N', 0, $brand_id,NOW(), NOW(), 'N')";
    	$promo_adapter->_query($sql);
    	$sql = "INSERT INTO `Promo_Merchant_Map` VALUES(null, 201, $merchant_id, '2013-10-05', '2020-01-01', NULL, now())";
    	$pmm_resource = Resource::createByData(new PromoMerchantMapAdapter($mimetypes), array("merchant_id"=>$merchant_id,"promo_id"=>201));
    	$ids['promo_merchant_map_id_type_1'] = $pmm_resource->map_id;
    	$sql = "INSERT INTO `Promo_Message_Map` VALUES(null, 201, 'Congratulations! You''re getting a 25% off your order!', NULL, NULL, NULL, NULL, now())";
    	$promo_adapter->_query($sql);
    	$sql = "INSERT INTO `Promo_Type1_Amt_Map` VALUES(null, 201, 1.00, 0.00, 25,50.00, NOW())";
    	$promo_adapter->_query($sql);
    	
    	Resource::createByData($pkwm_adapter, array("promo_id"=>201,"promo_key_word"=>"type1promo","brand_id"=>300));

		// create another promo on a differnt brand with same key word
		$menu_id2 = createTestMenuWithNnumberOfItems(1);
		$merchant_resource2 = createNewTestMerchant($menu_id2);
		$merchant_resource2->brand_id = 282;
		$merchant_resource2->save();
		$merchant_id2 = $merchant_resource2->merchant_id;
		$ids['merchant_id2'] = $merchant_id2;
    	
    	//create the type 1 promo 
    	$ids['promo_id_type_1'] = 202;
	   	$sql = "INSERT INTO `Promo` VALUES(202, 'The Type1 Promo', 'Get $10 off', 1, 'Y', 'N', 0, 2, 'N', 'N','$promo_type', '2010-01-01', '2020-01-01', 1, 0, 0, 0.00, 0, 0.00, 'Y', 'N',0, $brand_id,NOW(), NOW(), 'N')";
    	$promo_adapter->_query($sql);
    	$sql = "INSERT INTO `Promo_Merchant_Map` VALUES(null, 202, $merchant_id2, '2013-10-05', '2020-01-01', NULL, now())";
    	$pmm_resource2 = Resource::createByData(new PromoMerchantMapAdapter($mimetypes), array("merchant_id"=>$merchant_id2,"promo_id"=>202));
    	$ids['promo_merchant_map_id_type_1_alternate'] = $pmm_resource2->map_id;
    	$sql = "INSERT INTO `Promo_Message_Map` VALUES(null, 202, 'Congratulations! You''re getting a $10 off your order!', NULL, NULL, NULL, NULL, now())";
    	$promo_adapter->_query($sql);
    	$sql = "INSERT INTO `Promo_Type1_Amt_Map` VALUES(null, 202, 1.00, 10.00, 0.00,50.00, NOW())";
    	$promo_adapter->_query($sql);
    	
    	//$duplicate_promo_key_word = "somedumkeyword";
    	$duplicate_promo_key_word = "AlternateKeyWord";
    	$ids['duplicate_promo_key_word'] = $duplicate_promo_key_word;
    	Resource::createByData($pkwm_adapter, array("promo_id"=>202,"promo_key_word"=>"type1promo","brand_id"=>282));
    	Resource::createByData($pkwm_adapter, array("promo_id"=>202,"promo_key_word"=>"$duplicate_promo_key_word","brand_id"=>282));

    	//assigne promo ids
    	$item1_resource = SplickitController::getResourceFromId($item_records[1]['item_id'], "Item");
    	$item1_resource->promo_tag = "qitem000000200";
    	$item1_resource->save();
    	$item2_resource = SplickitController::getResourceFromId($item_records[2]['item_id'], "Item");
    	$item2_resource->promo_tag = "pitem000000200";
    	$item2_resource->save();
    	
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
    PromoTest::main();
}

?>