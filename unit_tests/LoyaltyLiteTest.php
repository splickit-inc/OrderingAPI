<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class LoyaltyLiteTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
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

    function testSeeIfCorrectFieldsAreOnLoyaltyFeatures() {
        $request = new Request();
        $request->url = '/app2/apiv2/skins/'.$this->ids['context'];
        $request->method = "get";
        $controller = new SkinController(null, null, $request);
        $response = $controller->processRequest();
        $this->assertEquals("http://sumdumlink.com",$response->loyalty_card_management_link,"loyalty_card_management_link should have been part of the skin info");
        $this->assertNotNull($response->loyalty_features, "The returned skin should have an array of loyalty features.");
        $loyalty_features = $response->loyalty_features;
        $this->assertTrue($loyalty_features['supports_link_card'],"flag should be true for supports link card");
        $this->assertTrue(isset($loyalty_features['supports_pin']),"Should be a field for supports pin");
        $this->assertFalse($loyalty_features['supports_pin'],"supports pin should be false");
        $this->assertTrue($loyalty_features['loyalty_lite'],"loyalty lite should be true");

    }

    function testGetLiteLoyaltyController()
    {
    	$loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext();
    	$this->assertEquals("LiteLoyaltyController", get_class($loyalty_controller));
    }

    function testNoAutoCreateAccount()
	{
		$user_resource = createNewUser();
		logTestUserResourceIn($user_resource);
		
		$user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
		$user_brand_points_record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
		$this->assertNull($user_brand_points_record,"Should NOT have found a user brand loyalty record");
		return true;
	}    
	
    function testLinkAccount()
	{
		$submitted_loyalty_number = "ABCD-88888";
		$user_resource = createNewUser();
		logTestUserResourceIn($user_resource);
    	$loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext();
    	$this->assertEquals("LiteLoyaltyController", get_class($loyalty_controller));
		$data['loyalty_number'] = "$submitted_loyalty_number";
		$loyalty_controller->setLoyaltyData($data);		
		$user_brand_loyalty_resource = $loyalty_controller->createOrLinkAccount($user_resource->user_id);
		$loyalty_number = $user_brand_loyalty_resource->loyalty_number;
		$this->assertEquals($submitted_loyalty_number,$loyalty_number);
        return $user_resource;
	}

    function testLinkAccountWithReturn()
    {
        $submitted_loyalty_number = "ABCD-9999";
        $user_resource = createNewUser();
        $user = logTestUserResourceIn($user_resource);
        $request = new Request();
        $request->body = '{"loyalty_number":"'.$submitted_loyalty_number.'"}';
        $request->method = 'POST';
        $request->mimetype = 'application/json';
        $request->url = "apiv2/users/".$user['uuid'];
        $request->_parseRequestBody();
        $user_controller = new UserController($mt, $user, $request);
        $response = $user_controller->processV2Request();
        $this->assertEquals(LoyaltyController::LOYALTY_NUMBER_SAVE_SUCCESS_MESSAGE,$response->user_message);
    }

    /**
     * @depends testLinkAccount
     */
	function testGetLoyaltySessionData($user_resource)
	{
    	$user = logTestUserIn($user_resource->user_id);
    	$usc = new UsersessionController($mt, $user, $r, 5);
    	$user_session_resource = $usc->getUserSession($user_resource);
    	$this->assertNotNull($user_session_resource->brand_loyalty,"Brand loyalty should exist on teh user session object");
    	
    	$loyalty_user_session_data = $user_session_resource->brand_loyalty;
    	$loyalty_number = $loyalty_user_session_data['loyalty_number'];
    	$this->assertEquals("ABCD-88888", $loyalty_number," should be a the loyalty number saved above of ABCD-88888");
    	$this->assertEquals(getBrandIdFromCurrentContext(), $loyalty_user_session_data['brand_id']);
    	$this->assertTrue($loyalty_user_session_data['points'] == '0');
    	$this->assertTrue(is_array($loyalty_user_session_data['loyalty_transactions']));
        $this->assertCount(0,$loyalty_user_session_data['loyalty_transactions'],"should be an empty array");
	}

    function testDoNotRecordPointsFromOrder()
    {
		$ids = $this->ids;
        $loyalty_number = $ids['loyalty_number'];
		$user_resource = $ids['user_with_loyalty_resource'];
		$user = logTestUserResourceIn($user_resource);
		$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($ids['merchant_id'], 'pickup', 'skip hours');
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $this->assertNull($order_resource->error);
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000,"should have created a valid order");
		$ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
		$ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user['user_id'],"brand_id"=>getBrandIdFromCurrentContext()));
		$this->assertEquals(0,$ubpm_record['points'],"no points should have been recorded");

		$ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
		$ublh_record = $ublh_adapter->getRecord(array("user_id"=>$user_id,"order_id"=>$order_id));
		$this->assertNull($ublh_record,"should not have had a history record created");

        $mmha = new MerchantMessageHistoryAdapter($m);
        $messages = $mmha->getAllOrderMessages($order_id);
        $messages_array = createHashmapFromArrayOfResourcesByFieldName($messages,'message_format');
        $gprs_message_resource = $messages_array['GUA'];
        $gprs_controller = new GprsController($m,$u,$r,5);
        $message_resource = $gprs_controller->prepMessageForSending($gprs_message_resource);
        $this->assertContains("Loyalty No: $loyalty_number",$message_resource->message_text);

        $fax_message_resource = $messages_array['FUA'];
        $fax_controller = new FaxController($m,$u,$r,5);
        $fax_message = $fax_controller->prepMessageForSending($fax_message_resource);
        $this->assertContains("Loyalty No: $loyalty_number",$fax_message->message_text);

        $email_message_resource = $messages_array['E'];
        $email_controller = new EmailController($m,$u,$r,5);
        $email_message = $email_controller->prepMessageForSending($email_message_resource);
        $this->assertContains("Loyalty No: $loyalty_number",$email_message->message_text);
    }

    function testNoIncludeLoyaltyNumberIfItDoesntExistOnMessageTemplates()
    {
        $ids = $this->ids;
        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($ids['merchant_id'], 'pickup', 'skip hours');
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000,"should have created a valid order");

        $mmha = new MerchantMessageHistoryAdapter($m);
        $messages = $mmha->getAllOrderMessages($order_id);
        $messages_array = createHashmapFromArrayOfResourcesByFieldName($messages,'message_format');
        $gprs_message_resource = $messages_array['GUA'];
        $gprs_controller = new GprsController($m,$u,$r,5);
        $message_resource = $gprs_controller->prepMessageForSending($gprs_message_resource);
        $this->assertNotContains("loyalty",strtolower($message_resource->message_text));

        $fax_message_resource = $messages_array['FUA'];
        $fax_controller = new FaxController($m,$u,$r,5);
        $fax_message = $fax_controller->prepMessageForSending($fax_message_resource);
        $this->assertNotContains("loyalty",strtolower($fax_message->message_text));
    }

    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	$_SERVER['request_time1'] = microtime(true);    	

		$skin_resource = getOrCreateSkinAndBrandIfNecessary("xlite", "litebrand", $skin_id, $brand_id);
        $skin_resource->supports_link_card = 1;
        $skin_resource->loyalty_card_management_link = 'http://sumdumlink.com';
        $skin_resource->save();
    	$brand_id = $skin_resource->brand_id;
    	$brand_resource = Resource::find(new BrandAdapter($mimetypes),"$brand_id");
    	$brand_resource->loyalty = 'Y';
        $brand_resource->use_loyalty_lite = 1;
    	$brand_resource->save();

        
        setContext($skin_resource->external_identifier);
        $ids['context'] = $skin_resource->external_identifier;
        $menu_id = createTestMenuWithNnumberOfItems(5);
        $ids['menu_id'] = $menu_id;

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        $ids['merchant_id'] = $merchant_id;

        $map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_id,"message_format"=>'GUA',"delivery_addr"=>"gprs","message_type"=>"X","info"=>"firmware=7.0"));
        $map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_id,"message_format"=>'FUA',"delivery_addr"=>"1234567890","message_type"=>"O"));

        // create user with loyalty
        $submitted_loyalty_number = "AA1234567890";
        $user_resource = createNewUserWithCC();
        logTestUserResourceIn($user_resource);
        $loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext();
        $data['loyalty_number'] = "$submitted_loyalty_number";
        $loyalty_controller->setLoyaltyData($data);
        $user_brand_loyalty_resource = $loyalty_controller->createOrLinkAccount($user_resource->user_id);
        $ids['loyalty_number'] = "$submitted_loyalty_number";
        $ids['user_brand_loyalty_resource'] = $user_brand_loyalty_resource;
        $ids['user_with_loyalty_resource'] = $user_resource;


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
    LoyaltyLiteTest::main();
}

?>