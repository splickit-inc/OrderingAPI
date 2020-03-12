
<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class LoyaltyItemPointsTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $merchant_id;
	var $menu_id;
	var $user_id;
	var $brand_points_id;
	var $user_brand_loyalty_map_id;
	var $size_id;
	var $ids;
	
	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		setContext($this->ids['context']);
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->stamp);
		unset($this->ids);
    }
    
    function testRecordPointsFromOrder()
    {
		$user = logTestUserIn($this->ids['user_id']);		
		$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours');
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000,"Should have created a valid order by got an error: ".$order_resource->error);
		return $order_resource;
    }
    
    /**
     * @depends testRecordPointsFromOrder
     */
    function testCorrectPointsAdded($order_resource)
    {
    	$user_id = $order_resource->user_id;
    	$ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
    	$ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>$this->ids['brand_id']));
		$this->assertEquals(1, $ubpm_record['points'],"should have had a single point added");
    }
		
	/**
     * @depends testRecordPointsFromOrder
     */
    function testCorrectLoyaltyHistoryRecord($order_resource)
	{
		$order_id = $order_resource->order_id;
		$user_id = $order_resource->user_id;
		$ublh_adapter = new UserBrandLoyaltyHistoryAdapter(getM());
		$ublh_record = $ublh_adapter->getRecord(array("user_id"=>$user_id,"order_id"=>$order_id));
		$this->assertEquals(1, $ublh_record['points_added'],"should have a record of a single point being added");
    }
 
//*    
    function testPlaceOrderWithPoints()
    {
		// we dont want to call to inspirepay 
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		
		$user_id = $this->ids['user_id'];
		$user = logTestUserIn($user_id);		
		
		// now adjust point values
		//$user_brand_points_map_resource = Resource::find(new UserBrandPointsMapAdapter($mimetypes),"".$this->ids['user_brand_loyalty_map_id']);
		//$user_brand_points_map_resource->points = 5;
		//$user_brand_points_map_resource->save();
		    	
		$order_adapter = new OrderAdapter($mimetypes);
    	$order = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours');
    	
    	// now add the points stuff to the item
    	$item = $order['items'][0];
    	$item['points_used'] = 10;
    	$item['amt_off'] = 1.65;
    	//$item['mods'] = [];
    	$item['brand_points_id'] = $this->ids['brand_points_id'];
    	$order['items'][0] = $item;
    	$order['tip'] = '0.00';
    	$order['grand_total'] = '0.00';
    	$order['total_points_used'] = 10;
    	$order['lead_time'] = 20;
    	$pickup_time = time()+1200;
    	$order['actual_pickup_time'] = $pickup_time;
    	$pickup_string = date('g:ia',$pickup_time);
    	$json_encoded_data = json_encode($order);
    	$checkout_resource = getCheckoutResourceFromOrderData($order,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNotNull($checkout_resource->error, "should have gotten an error");
        $this->assertEquals("We're sorry, but it appears you do not have enough points in your account to place this order.  If you feel you have received this message in error, please contact customer support", $checkout_resource->error);


//        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$this->ids['merchant_id'],0.00,time());
//        //$order_resource = placeOrderFromOrderData($order, $time_stamp);
//    	// order should fail;
//    	$this->assertNotNull($order_resource->error, "should have gotten an error");
//    	$this->assertEquals("We're sorry, but it appears you do not have enough points in your account to place this order.  If you feel you have received this message in error, please contact customer support", $order_resource->error);

		$user_brand_points_map_resource = Resource::find(new UserBrandPointsMapAdapter($m),$this->ids['user_brand_loyalty_map_id']);
    	$user_brand_points_map_resource->points = 11;
    	$user_brand_points_map_resource->save();

        $checkout_resource = getCheckoutResourceFromOrderData($order,getTomorrowTwelveNoonTimeStampDenver());
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$this->ids['merchant_id'],0.00,time());

        //$order_resource = placeOrderFromOrderData($order,$the_time);
    	$order_id = $order_resource->order_id;
		$this->assertTrue($order_resource->order_id > 1000,"should have created a good order id but got: ".$order_resource->error);
    	$this->assertNull($order_resource->error);
    	
    	$this->assertEquals(0.00, $order_resource->order_amt);
    	$this->assertEquals(0.00, $order_resource->total_tax_amt);
    	
		// now check to see if the correct amount was recorded in the history
		$brand_points_id = $this->ids['brand_points_id'];
		$brand_points_resource = Resource::find(new BrandPointsAdapter($mimetypes), "$brand_points_id", $options);
		
		$ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
		$ublh_record = $ublh_adapter->getRecord(array("user_id"=>$user_id,"order_id"=>$order_id));
		$this->assertNotNull($ublh_record);
		$this->assertEquals($brand_points_resource->points, $ublh_record['points_redeemed']);		
    	
		$user_brand_points_map_resource = Resource::find(new UserBrandPointsMapAdapter($mimetypes),"".$this->ids['user_brand_loyalty_map_id']);
		$this->assertEquals(1, $user_brand_points_map_resource->points);

    }

    function testPlaceOrderWithPointsChargePremeiummods()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';

        $blrid = $this->ids['brand_loyalty_rules_id'];
        $brand_loyalty_rules_resource = Resource::find(new BrandLoyaltyRulesAdapter($m),"$blrid");
        $brand_loyalty_rules_resource->charge_modifiers_loyalty_purchase = 1;
        $brand_loyalty_rules_resource->save();
        $user_id = $this->ids['user_id'];
        $user = logTestUserIn($user_id);

        $user_brand_points_map_resource = Resource::find(new UserBrandPointsMapAdapter($m),$this->ids['user_brand_loyalty_map_id']);
        $user_brand_points_map_resource->points = 11;
        $user_brand_points_map_resource->save();

        $order_adapter = new OrderAdapter($mimetypes);
        $order = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours');

        // now add the points stuff to the item
        $item = $order['items'][0];
        $item['points_used'] = 10;
        $item['amt_off'] = 1.65;
        $item['brand_points_id'] = $this->ids['brand_points_id'];
        $order['items'][0] = $item;
        $order['tip'] = '0.00';
        $order['grand_total'] = '0.00';
        $order['total_points_used'] = 10;
        $order['lead_time'] = 20;
        $pickup_time = time()+1200;
        $order['actual_pickup_time'] = $pickup_time;
        $pickup_string = date('g:ia',$pickup_time);
        $json_encoded_data = json_encode($order);
        $order_resource = placeOrderFromOrderData($order, $time_stamp);

        $order_id = $order_resource->order_id;
        $this->assertTrue($order_resource->order_id > 1000,"should have created a good order id but got: ".$order_resource->error);
        $this->assertNull($order_resource->error);

        $this->assertEquals(4.00, $order_resource->order_amt,"We should have charged the modifiers");
        $this->assertEquals(0.40, $order_resource->total_tax_amt,"there should be tax because we are charging the modifiers");

        $brand_loyalty_rules_resource->charge_modifiers_loyalty_purchase = 0;
        $brand_loyalty_rules_resource->save();


    }


/*    function testPlaceOrderWithPointsDoNotChargePremeiummods()
    {
        $menu_id = createTestMenuWithNnumberOfItems(5);

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;

        setContext($this->ids['context']);
        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);


    }
*/

    function testAddToCartWithPoints()
    {
        $user_id = $this->ids['user_id'];
        $user = logTestUserIn($user_id);

        // now adjust point values
        $user_brand_points_map_resource = Resource::find(new UserBrandPointsMapAdapter($mimetypes), "" . $this->ids['user_brand_loyalty_map_id']);
        $user_brand_points_map_resource->points = 100;
        $user_brand_points_map_resource->save();

        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleCartArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours');

        // now add the points stuff to the item
        $item = $order_data['items'][0];
        $item['points_used'] = 10;
        $item['amt_off'] = 1.65;
        $item['brand_points_id'] = $this->ids['brand_points_id'];
        $order_data['items'][0] = $item;

        $json_encoded_data = json_encode($order_data);
        $request = new Request();
        $request->url = '/app2/apiv2/cart';
        $request->method = "post";
        $request->body = $json_encoded_data;
        $request->mimetype = 'application/json';
        $request->_parseRequestBody();
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        //$cart_resource = $place_order_controller->createNewCart();
        $cart_resource = $place_order_controller->processV2Request();
        $this->assertNotNull($cart_resource, "should have gotten a cart resource back");
        //$this->assertTrue($cart_resource->insert_id > 999,"should have a valid cart id");
        $this->assertNotNull($cart_resource->ucid, "cart should have a unique identifier");
        return $cart_resource;
    }

    /**
     * @depends testAddToCartWithPoints
     */
    function testSummaryWithPointsFromAddToCart($cart_resource)
    {
        $order_summary = $cart_resource->order_summary['cart_items'];
        $this->assertCount(1, $order_summary);
        $item = $order_summary[0];
        $this->assertNotNull($item['item_price'],"shoudl have found an item price");
        $this->assertEquals("10 pts",$item['item_price']);
        $receipt_items_array = $cart_resource->order_summary['receipt_items'];
        $receipt_items = createHashOfRecieptItemsByTitle($receipt_items_array);
        $this->assertEquals(10,$receipt_items['Points Used'],"Should have had a points used section on the receipt summary");
        $this->assertEquals('$0.00', $receipt_items['Subtotal']);
        $this->assertEquals('$0.00',$receipt_items['Tax']);
    }

    /**
     * @depends testAddToCartWithPoints
     */
    function testGetCheckoutDataWithPoints($cart_resource)
    {
        $cart_ucid = $cart_resource->ucid;
        $user_id = $this->ids['user_id'];
        $user = logTestUserIn($user_id);

        $request = new Request();
        $request->url = "/apiv2/cart/$cart_ucid/checkout";
        $request->method = "get";
        $request->mimetype = 'application/json';

        $placeorder_controller = new PlaceOrderController($mt, $user, $request);
        $placeorder_controller->setCurrentTime(getTodayTwelveNoonTimeStampDenver());
        $checkout_data_resource = $placeorder_controller->processV2Request();
        $order_summary = $checkout_data_resource->order_summary['cart_items'];
        $this->assertCount(1, $order_summary);
        $item = $order_summary[0];
        $this->assertNotNull($item['item_price'],"shoudl have found an item price");
        $this->assertEquals("10 pts",$item['item_price']);
        $receipt_items_array = $checkout_data_resource->order_summary['receipt_items'];
        $receipt_items = createHashOfRecieptItemsByTitle($receipt_items_array);
        $this->assertEquals(10,$receipt_items['Points Used'],"Should have had a points used section on the receipt summary");
        $this->assertEquals('$0.00', $receipt_items['Subtotal']);
        $this->assertEquals('$0.00',$receipt_items['Tax']);
    }

// */    

    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	$_SERVER['request_time1'] = microtime(true);
    	$skin_id = 500;
    	$brand_id = 505;
    	$name = "dummys";
    	$skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty($name, $name, $skin_id, $brand_id);

        $blr_data['brand_id'] = $brand_id;
        if ($brand_loyalty_rules_resource = Resource::find(new BrandLoyaltyRulesAdapter(getM()),null,[3=>$blr_data])) {
            $brand_loyalty_rules_resource->loyalty_type = 'splickit_earn';
        } else {
            $blr_data['loyalty_type'] = 'splickit_earn';
            $brand_loyalty_rules_resource = Resource::factory(new BrandLoyaltyRulesAdapter(getM()),$blr_data);
        }
        $brand_loyalty_rules_resource->charge_modifiers_loyalty_purchase = 0;
        $brand_loyalty_rules_resource->save();
        $ids['brand_loyalty_rules_id'] = $brand_loyalty_rules_resource->brand_loyalty_rules_id;



        $ids['context'] = "com.splickit.$name";
    	$ids['brand_id'] = $brand_id;
    	$ids['skin_id'] = $skin_id;

        //$resource = Resource::createByData(new BrandLoyaltyRulesAdapter($m),array("brand_id"=>$brand_id,"charge_modifiers_loyalty_purchase"=>false));

        //$ids['brand_loyalty_rules_id'] = $resource->brand_loyalty_rules_id;
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
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	    	
    	setContext($ids['context']);
    	$user_resource = createNewUser(array('flags'=>'1C20000001'));
    	$ids['user_id'] = $user_resource->user_id;
    	
    	//get a user session to create eh loyalty record
    	$usc = new UsersessionController($mt, $user_resource->getDataFieldsReally(), $r, 5);
    	$user_session_resource = $usc->getUserSession($user_resource);

    	$ubpma = new UserBrandPointsMapAdapter($mimetypes);
    	$record = $ubpma->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>$brand_id), $options);
    	$ids['user_brand_loyalty_map_id'] = $record['map_id'];
    	
    	//set up points stuff
    	$complete_menu = CompleteMenu::getCompleteMenu($menu_id);
    	//set menu type name to be espresso
    	$menu_type_id = $complete_menu['menu_types'][0]['menu_type_id'];
		$bepom_resource = Resource::createByData(new BrandEarnedPointsObjectMapsAdapter($m),array("brand_id"=>$brand_id,"points"=>1,"object_type"=>'menu_type',"object_id"=>$menu_type_id));

    	//Test item 5 no points
    	$fifth_item_id = $complete_menu['menu_types'][0]['menu_items'][4]['item_id'];
		$bepom_resource = Resource::createByData(new BrandEarnedPointsObjectMapsAdapter($m),array("brand_id"=>$brand_id,"points"=>0,"object_type"=>'item',"object_id"=>$fifth_item_id));

    	
    	$bp_resource = Resource::createByData(new BrandPointsAdapter($mimetypes), array("brand_id"=>$brand_id,"points"=>10,"description"=>"Test Size 1 Points"));
    	$brand_points_id = $bp_resource->brand_points_id;
    	$ids['brand_points_id'] = $brand_points_id;
		$size_id = $complete_menu['menu_types'][0]['sizes'][0]['size_id'];
    	$bpom_resource = Resource::createByData(new BrandPointsObjectMapAdapter($mimetypes), array("brand_points_id"=>$brand_points_id,"object_type"=>'size',"object_id"=>$size_id));
		//$blr_resource = Resource::createByData(new BrandLoyaltyRulesAdapter($mimetypes), array("brand_id"=>$brand_id,"charge_modifiers_loyalty_purchase"=>0));
    	//$ids['brand_loyalty_rules_id'] = $blr_resource->brand_loyalty_rules_id;
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
    LoyaltyItemPointsTest::main();
}

?>