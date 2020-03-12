<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';


class ApiMerchantsTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		setContext("com.splickit.vtwoapi");
    	$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		
	}

    function testGetMerchantMetaDataInfo()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        $request = createRequestObject("/app2/apiv2/merchants/$merchant_id/getinfo",'get');
        $merchant_controller = new MerchantController(getM(),null, $request);
        $merchant_info_resource = $merchant_controller->processV2Request();
        $response = getV2ResponseWithJsonFromResource($merchant_info_resource, []);
        $this->assertEquals(200,$response->statusCode);
        $response_array = json_decode($response->body,true);
        $this->assertNotNull($response_array['data']['readable_hours'],"It should have an hours section");
        $this->assertCount(2,$response_array['data']['readable_hours'],"It should have delivery and pickup hours");
        $this->assertNotNull($response_array['data']['base_tax_rate'],"It should have a base tax rate section");
        $merchant_brand_id = $response_array['data']['brand_id'];
        $brand = BrandAdapter::staticGetRecordByPrimaryKey($merchant_brand_id,'BrandAdapter');
        $this->assertEquals($brand['brand_name'],$response_array['data']['brand_name'],"It should have the brand name on the merchant call");
        $this->assertNull($response_array['data']['menu'],"there should NOT be a menu");
    }

	function testSizesOnMenu()
    {
        $menu_id = createTestMenuWithNnumberOfItemsAndMenuTypes(3,0,2,3);
        $menu_resource = Resource::find(new MenuAdapter(getM()),"$menu_id");
        $menu_resource->version = 3.0;
        $menu_resource->save();

        $complete_menu_sizes = CompleteMenu::getAllSizesAsResources($menu_id);

        $sr = [];
        $p = 200;
        foreach($complete_menu_sizes as $size_resource) {
            $size_resource->priority = $p;
            $size_resource->save();
            $p = $p - 10;
            $sr[$size_resource->size_name] = $size_resource;
        }

        $size_resource = $sr['Size 1-2'];
        $size_resource->default_selection = 1;
        $size_resource->save();

        $size_resource = $sr['Size 2-3'];
        $size_resource->default_selection = 1;
        $size_resource->save();

        $merchant_resource = createNewTestMerchant($menu_id);
        $request = new Request();
        $request->url = "/apiv2/merchants/".$merchant_resource->merchant_id."";
        $request->method = 'GET';
        $merchant_controller = new MerchantController(getM(), null, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $menu = $resource->menu;

        $menu_types = $menu['menu_types'];
        $first_size_prices = $menu_types[0]['menu_items'][1]['size_prices'];
        $fsp_hash = createHashmapFromArrayOfArraysByFieldName($first_size_prices,'size_name');
        $this->assertEquals('Yes',$fsp_hash['Size 1-2']['default_selection']);
        $this->assertEquals(null ,$fsp_hash['Size 1-1']['default_selection']);
        $this->assertEquals(null ,$fsp_hash['Size 1-3']['default_selection']);

        $second_size_prices = $menu_types[1]['menu_items'][2]['size_prices'];
        $fsp_hash = createHashmapFromArrayOfArraysByFieldName($second_size_prices,'size_name');
        $this->assertEquals(null ,$fsp_hash['Size 2-2']['default_selection']);
        $this->assertEquals(null ,$fsp_hash['Size 2-1']['default_selection']);
        $this->assertEquals('Yes',$fsp_hash['Size 2-3']['default_selection']);


    }

	function testCheckCacheOnMenu()
    {
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $menu_resource = Resource::find(new MenuAdapter(getM()),"$menu_id");
        $menu_resource->version = 3.0;
        $menu_resource->save();
        $merchant_resource1 = createNewTestMerchant($menu_id);
        $merchant_resource2 = createNewTestMerchant($menu_id);
        $merchant_resource3 = createNewTestMerchant($menu_id);

        //create the cache
        $request = new Request();
        $request->url = "/apiv2/merchants/".$merchant_resource1->merchant_id."";
        $request->method = 'GET';
        $merchant_controller = new MerchantController(getM(), null, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $request = new Request();
        $request->url = "/apiv2/merchants/".$merchant_resource1->merchant_id."";
        $request->method = 'GET';
        $merchant_controller = new MerchantController(getM(), null, $request, 5);
        $resource_cached = $merchant_controller->processV2Request();


        $request = new Request();
        $request->url = "/apiv2/merchants/".$merchant_resource2->merchant_id."";
        $request->method = 'GET';
        $merchant_controller = new MerchantController(getM(), null, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $request = new Request();
        $request->url = "/apiv2/merchants/".$merchant_resource3->merchant_id."";
        $request->method = 'GET';
        $merchant_controller = new MerchantController(getM(), null, $request, 5);
        $resource = $merchant_controller->processV2Request();

        //




    }

    function testGetMerchantOrderingOffWithMenuMessage()
	{
        setProperty('DO_NOT_CHECK_CACHE','true');
		$user = logTestUserIn($this->ids['user_id']);
		$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
		$merchant_resource->ordering_on = 'N';
		$merchant_resource->custom_menu_message = "sum dum menu message";
		$merchant_resource->save();

		$request = new Request();
		$request->url = "/apiv2/merchants/".$merchant_resource->merchant_id."";
		$request->method = 'GET';
		$merchant_controller = new MerchantController($mt, $user, $request, 5);
		$resource = $merchant_controller->processV2Request();
		$message = $resource->user_message;
		$this->assertNull($resource->error);
		$this->assertContains(PlaceOrderController::ORDERING_OFFLINE_MESSAGE,$message);
		$this->assertContains('sum dum menu message',$message);

        $merchant_resource->ordering_on = 'Y';
        $merchant_resource->save();

        setProperty('use_merchant_caching', 'false');
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $message = $resource->user_message;
        $this->assertNull($resource->error);
        $this->assertNotContains(PlaceOrderController::ORDERING_OFFLINE_MESSAGE,$message);
        $this->assertContains('sum dum menu message',$message);

		return $merchant_resource;
	}

	/**
	 * @depends testGetMerchantOrderingOffWithMenuMessage
	 */
	function testCheckOrderingOffForBrand($merchant_resource)
    {
        $brand_id = getBrandIdFromCurrentContext();
        $brand_resource = Resource::find(new BrandAdapter(getM()),"$brand_id");
        $brand_resource->active = 'N';
        $brand_resource->save();
        setContext('com.splickit.vtwoapi');

        $request = new Request();
        $request->url = "/apiv2/merchants/".$merchant_resource->merchant_id."";
        $request->method = 'GET';
        $merchant_controller = new MerchantController(getM(), null, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $message = $resource->user_message;
        $this->assertNull($resource->error);
        $this->assertContains(PlaceOrderController::ORDERING_OFFLINE_MESSAGE,$message);

        $brand_resource->active = 'Y';
        $brand_resource->save();
        setContext('com.splickit.vtwoapi');

        $resource = $merchant_controller->processV2Request();
        $message = $resource->user_message;
        $this->assertNull($resource->error);
        $this->assertNotContains(PlaceOrderController::ORDERING_OFFLINE_MESSAGE,$message);

    }
	
	function testDeleteCardAssociationWithRequestData()
    {
    	$user_resource = createNewUser(array("flags"=>'1C20000001'));
    	$user_id = $user_resource->user_id;
    	$user = logTestUserIn($user_resource->user_id);
    	$this->assertEquals("1C20000001", $user['flags']);
    	
    	$request = new Request();
    	$request->url = '/app2/apiv2/users/'.$user['uuid'].'/credit_card';
    	$request->method = "delete";
    	
    	$user_controller = new UserController($mt, $user, $request,5);
    	$resource = $user_controller->processV2Request();
    	$this->assertNull($resource->error);
		$this->assertEquals("Your credit card has been deleted.", $resource->user_message);
    	
		$user_after = UserAdapter::staticGetRecordByPrimaryKey($user_id, "UserAdapter");
    	$this->assertEquals('1000000001', $user_after['flags']);   	
    	
    }

	function testForgotPasswordBadEmail()
    {
    	setContext('com.splickit.order');
    	$request = new Request();
    	$request->data = array("email"=>"sumdumemail@email.com");
    	$request->url = "apiv2/users/forgotpassword";
    	$request->method = 'GET';
    	$user_controller = new UserController($mt, $u, $request);
    	$resource = $user_controller->processV2Request();
    	$this->assertEquals('Sorry, that email is not registered with us. Please check your entry.', $resource->error);
    	$this->assertNull($resource->token);
    }
    
    function testForgotPassword()
    {
    	setContext('com.splickit.vtwoapi');
    	$user_resource = createNewUser();
    	$request = new Request();
    	$request->data = array("email"=>$user_resource->email);
    	$request->url = "apiv2/users/forgotpassword";
    	$request->method = 'GET';
    	$user_controller = new UserController($mt, $u, $request);
    	$resource = $user_controller->processV2Request();
    	$this->assertEquals('We have processed your request. Please check your email for reset instructions.', $resource->user_message);
    	$this->assertNotNull($resource->token);
    	
    	$message_record = MerchantMessageHistoryAdapter::staticGetRecord(array("message_format"=>'E',"message_delivery_addr"=>$user_resource->email), "MerchantMessageHistoryAdapter");
    	$this->assertContains("Here is a link for you to reset your password:", $message_record['message_text']);
    	$this->assertContains("https://sum.dum.domain.com/reset_password/".$resource->token,$message_record['message_text']);
    	return $resource->token;
    }
    
    /**
     * @depends testForgotPassword
     */
    function testResetPassword($token)
    {
    	// create new password to reset it to
    	$ts = time();
    	$tstring = (String) time();
		$new_password = 'adam'.substr($tstring,-4);    	
    	
    	$request = new Request();
    	$request->url = "/app2/user/resetpassword";
    	$request->data['token'] = $token;
    	$request->data['password'] = $new_password;
    	$user_controller = new UserController($mt, $u, $request);
    	$p_resource = $user_controller->processV2Request();
    	$this->assertNotNull($p_resource);
    	$this->assertNull($p_resource->error,'ERROR should have been NULL but it was: '.$p_resource->error);
    	$this->assertEquals('success', $p_resource->result);
    	
    	// now we need to test to see if the token can be used again (shoujdln't be able to)
    	$request4 = new Request();
    	$request4->url = "/app2/user/resetpassword";
    	$request4->data['token'] = $token;
    	$request4->data['password'] = $new_password;
    	$user_controller4 = new UserController($mt, $u, $request4);
    	$p_resource2 = $user_controller4->changePasswordWithToken();
    	
    	$this->assertNotNull($p_resource2->error,"ERROR was null for password retrieval and should have been present");
    	myerror_log("the error on bad token retrieval is: ".$p_resource2->error);
    	$this->assertEquals(998,$p_resource2->error_code);
    	
    	$upra = new UserPasswordResetAdapter($mimetypes);
    	$token_record = $upra->getRecord(array("token"=>$token), $options);
    	$user_id = $token_record['user_id'];
    	$user_resource = Resource::find(new UserAdapter($mimetypes),"$user_id", $options);
    	$this->assertNotNull($user_resource);
    	
    	$login_adapter = new LoginAdapter($mimetypes);
    	$this->assertFalse($login_adapter->checkPassword($user_resource, "sumdumpaxword"));
    	$this->assertTrue($login_adapter->checkPassword($user_resource, $new_password));
    	
    }

	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }

	function testGetAllMerchantsInSkin()
	{
		$request = new Request();
		$request->url = "apiv2/merchants";
		$request->method = 'GET';
		$merchant_controller = new MerchantController($mt, $user, $request, 5);
		$resource = $merchant_controller->processV2Request();
		$this->assertNotNull($resource->merchants);
		$merchants = $resource->merchants;
		$this->assertTrue(count($merchants) > 0);
		$this->assertTrue(is_array($merchants));
		$merchant_id = intval($merchants[0]);
		$this->assertTrue(is_int($merchant_id));
		$this->assertTrue($merchant_id > 0);
	}
    
    function testGetMerchants()
    {
    	$user = logTestUserIn($this->ids['user_id']);
    	
    	$request = new Request();
    	$request->url = "apiv2/merchants";
    	$request->method = 'GET';
    	$merchant_controller = new MerchantController($mt, $user, $request, 5);
    	$resource = $merchant_controller->processV2Request();
    	$this->assertNotNull($resource->merchants);
    	$this->assertNotNull($resource->promos);
    	$merchants = $resource->merchants;
    	$this->assertTrue(count($merchants) > 0);
    }

    function testGetMerchantsWithDeliveryInfo()
    {
        // add a delivery merchant
        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $user = logTestUserIn($this->ids['user_id']);

        $request = new Request();
        $request->url = "apiv2/merchants/fullskinlist";
        $request->method = 'GET';
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $this->assertNotNull($resource->merchants);
        foreach ($resource->merchants as $merchant) {
            // validate merchant has delivery info on
            $this->assertNotNull($merchant['delivery_area_info'],"there should be a delivery info field");
            $delivery_json = json_encode($merchant['delivery_area_info']);
        }
    }



	function testGetMerchantStatus()
	{
		$request = new Request();
		$request->url = "/apiv2/menu/".$this->ids['menu_id']."/menustatus";
		$request->method = 'GET';
		$resource = CompleteMenu::getMenuStatus($request,$mimetypes);
		$response = getV2ResponseWithJsonFromResource($resource, $headers);
		$this->assertEquals(200,$response->statusCode);
		$response_array = json_decode($response->body,true);
		$this->assertEquals($this->ids['menu_status_key'],$response_array['data']['menu_key']);
	}

	function testGetMerchantMetaData()
	{
		$request = createRequestObject('/app2/apiv2/merchants/'.$this->ids['merchant_id'],'get');
		$merchant_controller = new MerchantController($mt, $user, $request);
		$merchant_info_resource = $merchant_controller->processV2Request();
		$response = getV2ResponseWithJsonFromResource($merchant_info_resource, $headers);
		$this->assertEquals(200,$response->statusCode);
		$response_array = json_decode($response->body,true);
		$this->assertNotNull($response_array['data']['readable_hours'],"It should have an hours section");
		$this->assertCount(2,$response_array['data']['readable_hours'],"It should have delivery and pickup hours");
		$this->assertNotNull($response_array['data']['base_tax_rate'],"It should have a base tax rate section");
		$merchant_brand_id = $response_array['data']['brand_id'];
		$brand = BrandAdapter::staticGetRecordByPrimaryKey($merchant_brand_id,'BrandAdapter');
		$this->assertEquals($brand['brand_name'],$response_array['data']['brand_name'],"It should have the brand name on the merchant call");
	}

	function testGetMerchantDataInfoNoAllowedEndpoint()
	{
		$request = createRequestObject('/app2/apiv2/merchants/'.$this->ids['merchant_id'].'/info','get',$body,'application/json');
		$merchant_controller = new MerchantController($mt, $user, $request);
		$merchant_info_resource = $merchant_controller->processV2Request();
		$response = getV2ResponseWithJsonFromResource($merchant_info_resource, $headers);
		$this->assertEquals(200,$response->statusCode);
		$response_array = json_decode($response->body,true);
		$this->assertNotNull($response_array['error']['error']);
		$this->assertEquals("ERROR! endpoint not allowed!", $response_array['error']['error']);
	}

	function testGetMerchantOffLine()
	{
		$user = logTestUserIn($this->ids['user_id']);
		$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
		$merchant_resource->active = 'N';
		$merchant_resource->save();

		$request = new Request();
		$request->url = "/apiv2/merchants/".$merchant_resource->merchant_id."";
		$request->method = 'GET';
		$merchant_controller = new MerchantController($mt, $user, $request, 5);
		$resource = $merchant_controller->processV2Request();
		$this->assertNotNull($resource->error);
		$this->assertEquals(500,$resource->http_code);

	}

    function testGetMerchant()
    {
    	$user = logTestUserIn($this->ids['user_id']);

    	$request = new Request();
    	$request->url = "/apiv2/merchants/".$this->ids['merchant_id']."";
    	$request->method = 'GET';
    	$merchant_controller = new MerchantController($mt, $user, $request, 5);
    	$resource = $merchant_controller->processV2Request();
    	$this->assertEquals("America/Denver", $resource->time_zone_string);
    	$this->assertEquals(-7+date("I"),$resource->time_zone_offset);
    	$this->assertNotNull($resource->merchant_id);
    	$this->assertNotNull($resource->menu);
    	$this->assertNotNull($resource->todays_hours);
    	$this->assertNotNull($resource->payment_types);   
    	$full_menu = $resource->menu;
		$this->assertNull($full_menu['menu_types'][0]['sizes'],'SHould no longer have a sizes section since its now redundant');
    	$this->assertNull($full_menu['modifier_groups'],'Should no longer have modifier groups, they are in the item');
    	$item_size_price_id = $full_menu['menu_types'][0]['menu_items'][0]['size_prices'][0]['item_size_id'];
    	$size_id = $full_menu['menu_types'][0]['menu_items'][0]['size_prices'][0]['size_id'];
    	$this->assertNotNull($item_size_price_id,"Item size price id should not be null.");
    	$modifier_group = $full_menu['menu_types'][0]['menu_items'][0]['modifier_groups'][0];
    	$this->assertNotNull($modifier_group,"should have found the new modifier group section");
    	$this->assertTrue(isset($modifier_group['modifier_group_display_name']));
    	$this->assertTrue(isset($modifier_group['modifier_group_credit']));
    	$this->assertTrue(isset($modifier_group['modifier_group_max_price']));
    	$this->assertTrue(isset($modifier_group['modifier_group_max_modifier_count']));
    	$this->assertTrue(isset($modifier_group['modifier_group_min_modifier_count']));
    	$this->assertTrue(isset($modifier_group['modifier_group_display_priority']));
		$modifier_item = $modifier_group['modifier_items'][0];
		$this->assertTrue(isset($modifier_item['modifier_item_id']));
		$this->assertTrue(isset($modifier_item['modifier_item_name']));
		$this->assertTrue(isset($modifier_item['modifier_item_max']));
		$this->assertTrue(isset($modifier_item['modifier_item_min']));
		$this->assertTrue(isset($modifier_item['modifier_item_pre_selected']));
		$this->assertTrue(isset($modifier_item['modifier_prices_by_item_size_id'][0]));
		$modifier_price = $modifier_item['modifier_prices_by_item_size_id'][0];
		$this->assertTrue(isset($modifier_price['size_id']));
		$this->assertEquals($size_id, $modifier_price['size_id']);
		$this->assertTrue(isset($modifier_price['modifier_price']));
		$this->assertEquals(.50, $modifier_price['modifier_price']);    	
    }
    
    function testGetUser()
    {
    	$user = logTestUserIn($this->ids['user_id']);
    	
    	$request = new Request();
    	$request->url = "apiv2/users";
    	$request->method = 'GET';
    	$user_controller = new UserController($mt, $user, $request);
    	$resource = $user_controller->processV2Request();
		$this->assertNotNull($resource->user_id);
		$this->assertNotNull($resource->splickit_authentication_token,'get user call should have an authentication_token on the returned resource');
		$this->assertNotNull($resource->splickit_authentication_token_expires_at,"get user call should have an expires at timestamp");
		$this->assertEquals(time() + 43200, $resource->splickit_authentication_token_expires_at);
    }
    
    function testCreateNewUser()
    {
    	$user = logTestUserIn(1);
    	$request = new Request();
    	$request->url = "apiv2/users";
    	$request->method = 'POST';
    	$request->data = createNewUserDataFields();
    	$user_controller = new UserController($mt, $user, $request);
    	$resource = $user_controller->processV2Request();
		$this->assertNotNull($resource->user_id);
		$this->assertNotNull($resource->splickit_authentication_token,'create user call should have an authentication_token on the returned resource');
		$this->assertNotNull($resource->splickit_authentication_token_expires_at,"create user call should have an expires at timestamp");
		//$this->assertEquals(time() + 43200, $resource->splickit_authentication_token_expires_at);
        $this->assertTrue((time() + 43190) < $resource->splickit_authentication_token_expires_at);
        $this->assertTrue((time() + 43210) > $resource->splickit_authentication_token_expires_at);
    }

    function testCreateNewUserBadFieldsFirstName()
    {
        $user = logTestUserIn(1);
        $request = new Request();
        $request->url = "apiv2/users";
        $request->method = 'POST';
        $request->data = createNewUserDataFields();
        unset($request->data['first_name']);
        $user_controller = new UserController($mt, $user, $request);
        $resource = $user_controller->processV2Request();
        $this->assertNotNull($resource->error,"should have gotten an error becauser first name is blank");
        $this->assertEquals("First name cannot be blank.",$resource->error);

        $request->data['first_name'] = "rtyu567!tyu";
        $user_controller = new UserController($mt, $user, $request);
        $resource = $user_controller->processV2Request();
        $this->assertNotNull($resource->error,"should have gotten an error because first name is malformed");
        $this->assertEquals("First name can only contain letters, spaces, and dashes.",$resource->error);

        $request->data['first_name'] = "rtyu'tyu";
        $user_controller = new UserController($mt, $user, $request);
        $resource = $user_controller->processV2Request();
        $this->assertNotNull($resource->error,"should have gotten an error because first name is malformed");
        $this->assertEquals("First name can only contain letters, spaces, and dashes.",$resource->error);
    }

    function testCreateNewUserTestForGoodContactNumber()
    {
        $user = logTestUserIn(1);
        $request = new Request();
        $request->url = "apiv2/users";
        $request->method = 'POST';
        $request->data = createNewUserDataFields();
        unset($request->data['contact_no']);
        $user_controller = new UserController($mt, $user, $request);
        $resource = $user_controller->processV2Request();
        $this->assertNotNull($resource->error,"should have gotten an error becauser phone_number is blank");
        $this->assertEquals("Phone number cannot be blank.",$resource->error);

        $request->data['contact_no'] = "rtyu567tyu";
        $user_controller = new UserController($mt, $user, $request);
        $resource = $user_controller->processV2Request();
        $this->assertNotNull($resource->error,"should have gotten an error because phone number is malformed");
        $this->assertEquals("Phone number must be a 10 digit number.",$resource->error);

        $contact_no = "(303)-888.7777";
        $request->data['contact_no'] = "$contact_no";
        $user_controller = new UserController($mt, $user, $request);
        $resource = $user_controller->processV2Request();
        $this->assertNull($resource->error,"Should not have gotten an error but did: ".$resource->error);
        $this->assertNotNull($resource->user_id);
        $new_user = UserAdapter::staticGetRecord(array("uuid"=>$resource->uuid),'UserAdapter');
        $this->assertEquals("303-888-7777",$new_user['contact_no']);
    }


    function testUpdateUser()
    {
    	$user = logTestUserIn($this->ids['user_id']);
    	$data['first_name'] = "bill";
    	$request = new Request();
    	$request->url = "apiv2/users";
    	$request->method = 'POST';
    	$request->data = $data;
    	$user_controller = new UserController($mt, $user, $request);
    	$resource = $user_controller->processV2Request();
		$this->assertNotNull($resource->user_id);
		$this->assertEquals($user['uuid'], $resource->user_id);
		$this->assertEquals('bill', $resource->first_name);
    	
    }
    
    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;
    	
    	$skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty("vtwoapi","vtwoapi",252, 101);
        $skin_resource->base_url = "https://sum.dum.domain.com";
        $skin_resource->save();
    	$ids['skin_id'] = $skin_resource->skin_id;
    	
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;
		$menu_status_key = rand(11111111,99999999);
		$menu_resource = SplickitController::getResourceFromId($menu_id,'Menu');
		$menu_resource->last_menu_change = $menu_status_key;
		$menu_resource->save();
		$ids['menu_status_key'] = $menu_status_key;
    	
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);

    	$merchant_resource = createNewTestMerchant($menu_id);
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
		$ids['merchant_numeric_id'] = $merchant_resource->numeric_id;

    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;
    	
    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;
    }
    
	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();         $db = DataBase::getInstance();
    	$mysqli = $db->getConnection();
    	$mysqli->rollback();
    	date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    ApiMerchantsTest::main();
}

?>