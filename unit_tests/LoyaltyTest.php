<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class LoyaltyTest extends PHPUnit_Framework_TestCase
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
		$this->merchant_id = $_SERVER['unit_test_ids']['merchant_id'];
    	$this->menu_id = $_SERVER['unit_test_ids']['menu_id'];
		$this->brand_points_id = $_SERVER['unit_test_ids']['brand_points_id'];
		$this->user_brand_loyalty_map_id = $_SERVER['unit_test_ids']['user_brand_loyalty_map_id'];
		$this->user_id = $_SERVER['unit_test_ids']['user_id'];
		$this->size_id = $_SERVER['unit_test_ids']['size_id'];
					
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->stamp);
		unset($this->merchant_id);
		unset($this->menu_id);
		unset($this->brand_points_id);
		unset($this->size_id);
		unset($this->user_id);
		unset($this->ids);
    }
    
    function testBrandPointsObjectMap()
    {
    	$bpom = new BrandPointsObjectMapAdapter($mimetypes);
    	$result = $bpom->getRecord(array("id"=>12345));
    }
    
    /**
     * @expectedException BrandNotSetException
     */
    function testNoBrandException()
    {
    	removeContext();
    	$loyalty_controller = new LoyaltyController($mt, $user, $request);
    	$this->assertNull($loyalty_controller);
    }
    
    /**
     * @expectedException NoBrandLoyaltyEnabledException
     */
    function testBrandLoyaltyException()
    {
    	$skin_resource = getOrCreateSkinAndBrandIfNecessary("noloyaltyskin", "noloyaltybrand", $skin_id, $brand_id);
    	setContext("com.splickit.noloyaltyskin");
    	$loyalty_controller = new LoyaltyController($mt, $user, $request);	
    }
    
    function testValidateLoyaltyNumberForHomeGrown()
    {
    	setContext("com.splickit.myskin");
    	$loyalty_controller = new LoyaltyController($mt, $user, $request);
    	$this->assertTrue($loyalty_controller->validateLoyaltyNumber('1234567'));
    }
    
    function testReturnExistingLoyaltyHIstory()
    {
    	setContext("com.splickit.myskin");
    	$history = array("asfadsf","ksjafhkjf","oirtuafklj","fjsdkafasld");
    	$loyalty_controller = new LoyaltyController($mt, $user, $request);
    	$loyalty_controller->loyalty_history = $history;
    	$this->assertEquals($history, $loyalty_controller->getLoyaltyHistory());
    }
    
    function testSetLoyaltyData()
    {
    	setContext("com.splickit.myskin");
    	$data['brand_loyalty'] = array("name1"=>"value1","name2"=>"value2","create_loyalty_account"=>true);
    	$loyalty_controller = new LoyaltyController($mt, $user, $request);
    	$loyalty_controller->setLoyaltyData($data);
    	$this->assertEquals($data['brand_loyalty'], $loyalty_controller->data);
    }
    
    function testAutoJoin()
    {
    	setContext("com.splickit.myskin");
    	$loyalty_controller = new LoyaltyController($mt, $user, $request);
    	$loyalty_controller->auto_join = false;
    	$this->assertFalse($loyalty_controller->isAutoJoinOn());
    	$loyalty_controller->auto_join = true;
    	$this->assertTrue($loyalty_controller->isAutoJoinOn());
    }

    function testGetIdentifierNameFromContext()
    {
    	setContext("com.splickit.pitapit");
    	$name = getIdentifierNameFromContext();
    	$this->assertEquals("pitapit", $name);
    }
    
    function testGetBaseLoyaltyControllerWhenCustomControllerDoesntExist()
    {
    	setContext("com.splickit.myskin");
    	$loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext();
    	$this->assertEquals("HomeGrownLoyaltyController", get_class($loyalty_controller));
		return true;
    }

    /**
     * @depends testGetBaseLoyaltyControllerWhenCustomControllerDoesntExist
     */
    function testAutoCreateAccountWithUserBrandLoyaltyCreated($return)
	{
		setContext("com.splickit.myskin");
		$user_resource = createNewUser();
		logTestUserResourceIn($user_resource);
		
		$user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
		$user_brand_points_record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
		$this->assertNotNull($user_brand_points_record,"Should have found a user brand loyalty record");
		$loyalty_number = $user_brand_points_record['loyalty_number'];
		$this->assertEquals(cleanAllNonNumericCharactersFromString($user_resource->contact_no),$loyalty_number);
//		$this->assertNotNull($loyalty_number,"Should have generated a loyalty number");
//		$this->assertEquals("splick", substr($loyalty_number, 0,6)," should be a splickit loyalty nubmer");
		return true;
	}    
	
    /**
     * @depends testAutoCreateAccountWithUserBrandLoyaltyCreated
     */
    function testLinkAccount($return)
	{
		$submitted_loyalty_number = "888888888";
		setContext("com.splickit.myskin");
		$user_resource = createNewUser();
		logTestUserResourceIn($user_resource);
    	$loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext();
    	$this->assertEquals("HomeGrownLoyaltyController", get_class($loyalty_controller));
        $this->assertTrue(is_a($loyalty_controller,'LoyaltyController'));
		$data['loyalty_number'] = "$submitted_loyalty_number";
		$loyalty_controller->setLoyaltyData($data);		
		$user_brand_loyalty_resource = $loyalty_controller->createOrLinkAccount($user_resource->user_id);
		$loyalty_number = $user_brand_loyalty_resource->loyalty_number;
		$this->assertEquals($submitted_loyalty_number,$loyalty_number);
	}    
	
	function testGetLoyaltySessionData()
	{
    	$skin_resource = getOrCreateSkinAndBrandIfNecessary("myotherskin", "myotherbrand", $skin_id, $brand_id);
    	setContext('com.splickit.myotherskin');
    	$user_resource = createNewUser();
    	$user_id = $user_resource->user_id;
    	$user = logTestUserIn($user_id);
    	$usc = new UsersessionController($mt, $user, $r, 5);
    	$user_session_resource = $usc->getUserSession($user_resource);
    	$this->assertNull($user_session_resource->brand_loyalty,"should not have created a brand loyalty record since this skin does not have it turned on");
    	
    	// now log them into the skin with loyalty and it should create one for them
    	setContext('com.splickit.myskin');
    	$user = logTestUserIn($user_id);
    	$usc = new UsersessionController($mt, $user, $r, 5);
    	$user_resource2 = SplickitController::getResourceFromId($user_id, 'User');
    	$user_session_resource = $usc->getUserSession($user_resource2);
    	$this->assertNotNull($user_session_resource->brand_loyalty,"Brand loyalty record should have gotten created on login");
    	
    	$loyalty_user_session_data = $user_session_resource->brand_loyalty;
    	$loyalty_number = $loyalty_user_session_data['loyalty_number'];
		$this->assertEquals(cleanAllNonNumericCharactersFromString($user_resource->contact_no),$loyalty_number);
    	//$this->assertEquals("splick", substr($loyalty_number, 0,6)," should be a splickit loyalty nubmer");
    	$this->assertEquals(getBrandIdFromCurrentContext(), $loyalty_user_session_data['brand_id']);
    	$this->assertTrue($loyalty_user_session_data['points'] == '0');
    	$this->assertTrue(is_array($loyalty_user_session_data['loyalty_transactions']));
    	
    	// now log them back into the other skin and should not have a loyalyt record
    	setContext('com.splickit.myotherskin');
    	$user = logTestUserIn($user_id);
    	$user_resource3 = SplickitController::getResourceFromId($user_id, 'User');
    	$usc = new UsersessionController($mt, $user, $r, 5);
    	$user_session_resource = $usc->getUserSession($user_resource3);
    	$this->assertNull($user_session_resource->brand_loyalty,"should not have a brand loyalty record on the user session since this skin does not have loyalty turned on ");

	}
    
//    function testGetLoyaltyOnUserSessionWithRemoteData()
//    {
//    	setContext('com.splickit.jerseymikes');
//    	$_SERVER['REQUEST_METHOD'] = 'POST';
//    	$loyalty_number = '1234567890';
//    	$user_resource = createNewUser(array("loyalty_number"=>$loyalty_number,"loyalty_phone_number"=>$loyalty_number));
//    	
//    	// check to see what points were set to
//    	$user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
//    	$ubpm_resource = $user_brand_points_map_adapter->getExactResourceFromData(array("user_id"=>$user_resource->user_id,"brand_id"=>326));
//    	$this->assertEquals('3907', $ubpm_resource->points);
//    	// now reset it
//    	$ubpm_resource->points = 1000; 
//    	$this->assertTrue($ubpm_resource->save());
//    	
//    	$_SERVER['REQUEST_METHOD'] = 'GET';
//    	$user = logTestUserIn($user_resource->user_id);
//    	$usc = new UsersessionController($mt, $user, $r, 5);
//    	$user_session_resource = $usc->getUserSession($user_resource);
//    	$this->assertNotNull($user_session_resource->brand_loyalty);
//    	$brand_loyalty = $user_session_resource->brand_loyalty;
//    	$this->assertNotNull($brand_loyalty['loyalty_number']);
//    	$this->assertEquals('1234567890', $brand_loyalty['loyalty_number']);
//    	$this->assertEquals('3C154325E7544F', $brand_loyalty['barcode_number']);
//    	$this->assertEquals('3907', $brand_loyalty['points']);
//    	$remote_history = $brand_loyalty['loyalty_transactions'];
//    	$this->assertTrue(is_array($remote_history));
// /*   	$history_record = $remote_history[0];
//    	$this->assertEquals('2013-03-25 09:37:00', $history_record['transaction_date']);
//    	$this->assertEquals('HD', $history_record['store_number']);
//    	$this->assertEquals('44', $history_record['points_added']);
//    	$this->assertEquals('Purchase', $history_record['transaction_type']);
//  */  	
//    	//no check to make sure the poinst were reset to the remote value
//    	$ubpm_resource = $user_brand_points_map_adapter->getExactResourceFromData(array("user_id"=>$user_resource->user_id,"brand_id"=>326));
//    	$this->assertEquals('3907', $ubpm_resource->points);
//    	
//    }
//    
//    function testPlaceOrderWithNoUserLoyaltyRecordDoNOtCreateLoyaltyRecord()
//    {
//    	setContext('com.splickit.jerseymikes');
//    	$ids = $this->ids['menu_id'];
//    	$merchant_resource = createNewTestMerchant();
//    	$merchant_resource->brand_id = 326;
//    	$merchant_resource->save();
//    	$user_resource = createNewUserWithCC();
//		$user_id = $user_resource->user_id;
//		$user = logTestUserIn($user_id);		
//		$order_adapter = new OrderAdapter($mimetypes);
//    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->merchant_id, 'pickup', 'skip hours');
//		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
//		$order_id = $order_resource->order_id;
//		$this->assertTrue($order_id > 1000);
//		$ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
//		$ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>326));
//		$this->assertNull($ubpm_record);
//    }
	
    function testGetCustomLoyaltyController()
    {
    	setContext("com.splickit.pitapit");
    	$loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext();
    	$this->assertEquals("PitapitLoyaltyController", get_class($loyalty_controller));
    }
    
    function testRecordPoints()
    {
    	$skin_resource = getOrCreateSkinAndBrandIfNecessary("myskin", "mybrand", $skin_id, $brand_id);
    	setContext('com.splickit.myskin');
    	$brand_id = $skin_resource->brand_id;
    	$user_resource = createNewUser();
    	$user_id = $user_resource->user_id;
    	
    	$user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
    	$points = 200;

    	$ubpm_resource = $user_brand_points_map_adapter->addPointsToUserBrandPointsRecord($user_id, $brand_id, $points);
    	$this->assertNotNull($ubpm_resource);
		$this->assertEquals($points, $ubpm_resource->points);
		$this->assertEquals($brand_id, $ubpm_resource->brand_id);
		
		$new_points = 2 * $points;
		$ubpm_resource2 = $user_brand_points_map_adapter->addPointsToUserBrandPointsRecord($user_id, $brand_id, $points);
		$this->assertNotNull($ubpm_resource2);
		$this->assertEquals($new_points, $ubpm_resource2->points);
		$this->assertEquals($brand_id, $ubpm_resource2->brand_id);
    }
    
    function testRecordLoyaltyHistory()
    {
    	$skin_resource = getOrCreateSkinAndBrandIfNecessary("myskin", "mybrand", $skin_id, $brand_id);
    	$brand_id = $skin_resource->brand_id;
        setContext($skin_resource->external_identifier);
    	
    	$user_resource = createNewUser();
    	$user_id = $user_resource->user_id;
    	
    	$user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
    	$points = 200;
    	//$brand_id = 300;
		
		$ublha = new UserBrandLoyaltyHistoryAdapter($mimetypes);
		$ublh_resource = $ublha->recordLoyaltyTransaction($user_id, $brand_id, 12345, 'Order', $points,1050,10.50);
		$this->assertNotNull($ublh_resource);
		$this->assertEquals($user_id, $ublh_resource->user_id);
		$this->assertEquals($brand_id, $ublh_resource->brand_id);
		$this->assertEquals(12345, $ublh_resource->order_id);
		$this->assertEquals($points,$ublh_resource->points_added);
    	
		// now try with a negative value
		$points2 = -50;
		$ublh_resource = $ublha->recordLoyaltyTransaction($user_id, $brand_id, 12346, 'Order',-50,550, 5.50);
		$this->assertNotNull($ublh_resource);
		$this->assertEquals($user_id, $ublh_resource->user_id);
		$this->assertEquals($brand_id, $ublh_resource->brand_id);
		$this->assertEquals(12346, $ublh_resource->order_id);
		$this->assertEquals(-$points2,$ublh_resource->points_redeemed);
		
		$points3 = 0;
		$ublh_resource = $ublha->recordLoyaltyTransaction($user_id, $brand_id, 12347, 'Order',0,550, 5.50);
		$this->assertNotNull($ublh_resource);
		$this->assertEquals($user_id, $ublh_resource->user_id);
		$this->assertEquals($brand_id, $ublh_resource->brand_id);
		$this->assertEquals(12347, $ublh_resource->order_id);
		$this->assertEquals(-$points3,$ublh_resource->points_redeemed);
		
		return $user_id;
    }
    
    /**
     * @depends testRecordLoyaltyHistory
     */
	function testGetLoyaltyHistory($user_id)
	{
		setContext('com.splickit.myskin');
		$user = logTestUserIn($user_id);
		$loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext();
		$loyalty_history = $loyalty_controller->getLoyaltyHistory();
		$this->assertCount(3, $loyalty_history);
		
		$loyalty_transaction = $loyalty_history[1];
		$this->assertEquals('Spent 50 points.', $loyalty_transaction['description']);
		$this->assertEquals('$5.50',$loyalty_transaction['amount']);
		$this->assertEquals(date('Y-m-d'),$loyalty_transaction['transaction_date']);
		
		$loyalty_transaction = $loyalty_history[2]; 
		$this->assertEquals('Earned 200 points.', $loyalty_transaction['description']);
		$this->assertEquals('$10.50',$loyalty_transaction['amount']);
		$this->assertEquals(date('Y-m-d'),$loyalty_transaction['transaction_date']);
		
		$loyalty_transaction = $loyalty_history[0];
		$this->assertEquals('Earned 0 points.', $loyalty_transaction['description']);
		$this->assertEquals('$5.50',$loyalty_transaction['amount']);
		$this->assertEquals(date('Y-m-d'),$loyalty_transaction['transaction_date']);
	}
	
	function testGetLoyaltyHistoryNoHistory()
	{
		setContext('com.splickit.myskin');
		$user_resource = createNewUser();
		$user = logTestUserResourceIn($user_resource);
		$loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext();
		$loyalty_history = $loyalty_controller->getLoyaltyHistory();
		$this->assertNotNull($loyalty_history,"loyalty history should not be null, shoudl be an empty array");
		$this->assertTrue(is_array($loyalty_history)," loyalty history should have been an array");
		$this->assertCount(0, $loyalty_history,"loyalty history should have no records. count 0");
	}
    
    function testRecordPointsFromOrder()
    {
		setContext("com.splickit.worldhq");
		$brand_resource = SplickitController::getResourceFromId(getBrandIdFromCurrentContext(), "Brand");
		$brand_resource->loyalty = 'N';
		$brand_resource->save();

		$blr_data['brand_id'] = $brand_resource->brand_id;
        if ($brand_loyalty_rules_resource = Resource::find(new BrandLoyaltyRulesAdapter(getM()),null,[3=>$blr_data])) {
            $brand_loyalty_rules_resource->loyalty_type = 'splickit_earn';
        } else {
            $blr_data['loyalty_type'] = 'splickit_earn';
            $brand_loyalty_rules_resource = Resource::factory(new BrandLoyaltyRulesAdapter(getM()),$blr_data);
        }
        $brand_loyalty_rules_resource->save();


        // we dont want to call to inspirepay
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';

		$user_resource = createNewUserWithCC();
		$user_id = $user_resource->user_id;
		$user = logTestUserIn($user_id);		
		$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->merchant_id, 'pickup', 'skip hours');
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000);
		$ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
		$ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>300));
		$this->assertNull($ubpm_record);
		
		// now turn loyalty on for brand
		$brand_resource = Resource::find(new BrandAdapter($mimetypes),"300");
		$brand_resource->loyalty = 'Y';
		$brand_resource->save();
		setContext("com.splickit.worldhq");
		
		// now create a loyalty account for this user by getting a user Session
		$user_session_contoller = new UsersessionController($mt, $user, $r);
		$user_session = $user_session_contoller->getUserSession($user_resource); 
		$ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>300));
		$this->assertNotNull($ubpm_record);
		$this->assertEquals(0, $ubpm_record['points']);
		
		unset($order_resource);
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000);
		unset($ubpm_record);
		$ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>300));
		
		$this->assertNotNull($ubpm_record);
		$this->assertEquals(round(10*$order_resource->order_amt), $ubpm_record['points']);
		
		$ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
		$ublh_record = $ublh_adapter->getRecord(array("user_id"=>$user_id,"order_id"=>$order_id));
		$this->assertNotNull($ublh_record);
		$this->assertEquals(round(10*$order_resource->order_amt), $ublh_record['points_added']);	
		$this->assertEquals(date('Y-m-d'), $ublh_record['action_date']);	
    }
 
    function testGetMenuWithPoints()
    {
    	setContext('com.splickit.worldhq');
    	$menu = CompleteMenu::getCompleteMenu($this->menu_id,'Y',$this->merchant_id);
		$item_1_data = $menu['menu_types'][0]['menu_items'][1]['size_prices'][0];
		$this->assertEquals(50, $item_1_data['points']);
		$this->assertEquals($this->brand_points_id, $item_1_data['brand_points_id']);
        $this->assertEquals(1,$menu['charge_modifiers_loyalty_purchase'],"shoud have a field that indicates whether this branch charges for mods on loyalty purchase");

				
    }
//*    
    function testPlaceOrderWithPointsSplickitWorldHqBrand()
    {
		setContext("com.splickit.worldhq");
		// we dont want to call to inspirepay 
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		
		$user_id = $this->user_id;
		$user = logTestUserIn($user_id);		
		
		// now adjust point values
		$user_brand_points_map_resource = Resource::find(new UserBrandPointsMapAdapter($mimetypes),"".$this->user_brand_loyalty_map_id);
		$user_brand_points_map_resource->points = 25;
		$user_brand_points_map_resource->save();
		    	
		$order_adapter = new OrderAdapter($mimetypes);
    	$order = $order_adapter->getSimpleOrderArrayByMerchantId($this->merchant_id, 'pickup', 'skip hours');
    	
    	// now add the points stuff to the item
    	$item = $order['items'][0];
    	$item['uuid'] = 10;
    	$item['points_used'] = 50;
    	$item['amt_off'] = 1.65;
    	$item['brand_points_id'] = $this->brand_points_id;
    	$order['items'][0] = $item;
    	$order['tip'] = '0.00';
    	$order['grand_total'] = '0.00';
    	$order['total_points_used'] = 50;
    	$order['lead_time'] = 20;
    	//$order['user_id'] = 20000;
    	$pickup_time = time()+1200;
    	$order['actual_pickup_time'] = $pickup_time;
    	$pickup_string = date('g:ia',$pickup_time);
    	$json_encoded_data = json_encode($order);
    	$order_resource = placeOrderFromOrderData($order,$the_time);
    	// order should fail;
    	$this->assertNotNull($order_resource->error, "should have gotten an error");
    	$this->assertEquals("We're sorry, but it appears you do not have enough points in your account to place this order.  If you feel you have received this message in error, please contact customer support", $order_resource->error);
    	
    	$user_brand_points_map_resource->points = 95;
    	$user_brand_points_map_resource->save();
    	
    	//set max order points to 40.  orders should fail
    	$blr_resource = SplickitController::getResourceFromId($this->ids['brand_loyalty_rules_id'], 'BrandLoyaltyRules');
    	$blr_resource->max_points_per_order = 40;
    	$blr_resource->save();
        $order_resource = placeOrderFromOrderData($order,$the_time);
    	// order should fail;
    	$this->assertNotNull($order_resource->error);
    	$this->assertEquals("We're sorry, but there is max points per order of 40, please remove something from your cart.  If you feel you have received this message in error, please contact customer support", $order_resource->error);
    	
    	$blr_resource->max_points_per_order = 88888;
    	$blr_resource->save();

        //$checkout_resource = getCheckoutDataV2($json_encoded_data);
        $checkout_resource = getCheckoutResourceFromOrderData($order);
        $order_summary = $checkout_resource->order_summary;
        $cart_item = $order_summary['cart_items'][0];
        $this->assertEquals('50 pts',$cart_item['item_price'],"should have shown the price in points for the item");
        $receipt_items = $order_summary['receipt_items'];
        $receipt_items_hash = createHashmapFromArrayOfArraysByFieldName($receipt_items,'title');
        $points_row = $receipt_items_hash['Points Used'];
        $this->assertNotNull($points_row,"should have a points row indicating the 50 points used");
        $this->assertEquals("50",$points_row['amount']);

        $order_resource = placeOrderFromOrderData($order,$the_time);
    	$order_id = $order_resource->order_id;
    	$this->assertTrue($order_resource->order_id > 1000,"should have created a good order id but got: ".$order_resource->error);
    	$this->assertNull($order_resource->error);
    	
    	$this->assertEquals(0.00, $order_resource->order_amt);
    	$this->assertEquals(0.00, $order_resource->total_tax_amt);
    	
    	// now check to see if the points were deducted from the account correctly
    	$user_brand_points_map_resource_after = Resource::find(new UserBrandPointsMapAdapter($mimetypes),"".$this->user_brand_loyalty_map_id);
    	$this->assertEquals(45, $user_brand_points_map_resource_after->points);
    	
		// now check to see if the correct amount was recorded in the history
		$brand_points_id = $this->ids['brand_points_id'];
		$brand_points_resource = Resource::find(new BrandPointsAdapter($mimetypes), "$brand_points_id", $options);
		
		$ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
		$ublh_record = $ublh_adapter->getRecord(array("user_id"=>$user_id,"order_id"=>$order_id));
		$this->assertNotNull($ublh_record);
		$this->assertEquals($brand_points_resource->points, $ublh_record['points_redeemed']);		
		
    }

//*    
    function testGetBrandPointsResourceList()
    {
    	$bp_adapter = new BrandPointsAdapter($mimetypes);
    	$resources = $bp_adapter->getBrandPointsResourceList(300);
    	$this->assertEquals(1, sizeof($resources, $mode));
    }
    
    function testGetBrandPointList()
    {
    	$bp_adapter = new BrandPointsAdapter($mimetypes);
    	$brand_points_hash = $bp_adapter->getBrandPointsList(300);
    	$this->assertEquals(1, sizeof($brand_points_hash, $mode));
    	
    	$size_hash_string = 'size_'.$this->size_id;
    	$this->assertNotNull($brand_points_hash[$size_hash_string]);
    	$thing = $brand_points_hash[$size_hash_string];
    	$this->assertEquals(50, $thing['points']);
    	$this->assertEquals('test size 1 points', strtolower($thing['description']));
    }

    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);
    	SplickitCache::flushAll();
    	$db = DataBase::getInstance();
    	$mysqli = $db->getConnection();
    	$mysqli->begin_transaction(); ;
    	$_SERVER['request_time1'] = microtime(true);    	
		$skin_resource = createWorldHqSkin();
		
		$merchant_resource = createNewTestMerchant();
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;
    	
/*    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);
*/    	
    	MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $menu_id, 'pickup');
    	    	
    	$user_resource = createNewUser(array('flags'=>'1C20000001'));
    	$ids['user_id'] = $user_resource->user_id;
    	
    	$ubp_resource = Resource::createByData(new UserBrandPointsMapAdapter($mimetypes), array("user_id"=>$user_resource->user_id,"loyalty_number"=>"300-67890","brand_id"=>300,"points"=>100));
    	$ids['user_brand_loyalty_map_id'] = $ubp_resource->map_id;
    	
    	//set up points stuff
    	$complete_menu = CompleteMenu::getCompleteMenu($menu_id);
    	//Test Size 1
    	$size_id = $complete_menu['menu_types'][0]['sizes'][0]['size_id'];
    	$ids['size_id'] = $size_id;
    	
    	$bp_resource = Resource::createByData(new BrandPointsAdapter($mimetypes), array("brand_id"=>300,"points"=>50,"description"=>"Test Size 1 Points"));
    	$brand_points_id = $bp_resource->brand_points_id;
    	$ids['brand_points_id'] = $brand_points_id;
    	$bpom_resource = Resource::createByData(new BrandPointsObjectMapAdapter($mimetypes), array("brand_points_id"=>$brand_points_id,"object_type"=>'size',"object_id"=>$size_id));
		$blr_resource = Resource::createByData(new BrandLoyaltyRulesAdapter($mimetypes), array("brand_id"=>300));
    	$ids['brand_loyalty_rules_id'] = $blr_resource->brand_loyalty_rules_id;
    	
    	$skin_resource = getOrCreateSkinAndBrandIfNecessary("myskin", "mybrand", $skin_id, $brand_id);
    	$brand_id = $skin_resource->brand_id;
    	$brand_resource = Resource::find(new BrandAdapter($mimetypes),"$brand_id");
    	$brand_resource->loyalty = 'Y';
    	$brand_resource->save();
    	
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
    LoyaltyTest::main();
}

?>