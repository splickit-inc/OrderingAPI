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
require_once 'lib/utilities/functions.inc';
require_once 'lib/utilities/unit_test_functions.inc';

class ApiDispatchTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;
	var $info;
	var $api_port = "80";

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		setContext("com.splickit.vtwoapi");
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

//    function testGetMerchantListtwoo()
//    {
//        $menu_id = createTestMenuWithNnumberOfItems(1);
//        createNewTestMerchant($menu_id);
//        //$data = array ('zip'=>'30302','range'=>100,'minimum_merchant_count'=>25);
//        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/merchants?range=100&minimum_merchant_count=5&location=Boulder%2CCO'&merchant_id=&log_level=5";
//        $result = $this->makeRequestWithDefaultHeaders($url, null);
//        $this->assertEquals('200', $this->info['http_code']);
//        $request_result_as_array = json_decode($result,true);
//        $merchants = $request_result_as_array['data']['merchants'];
//        $this->assertTrue(count($merchants) > 0);
//        $this->assertNull($request_result_as_array['error']);
//    }

    function testMaxEntrePerOrder()
    {
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $menu_type_id = createNewMenuTypeWithNNumberOfItems($menu_id,'Drinks','D',1);

        $complete_menu = CompleteMenu::getCompleteMenu($menu_id);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        $user_resource = createNewUserWithCCNoCVV();
        $user_resource->balance = 100.00;
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);

        $merchant_menu_map_resource = Resource::find(new MerchantMenuMapAdapter(getM()),'',[3=>['merchant_id'=>$merchant_id,'merchant_menu_type'=>'pickup']]);
        $merchant_menu_map_resource->max_entres_per_order = 2;
        $merchant_menu_map_resource->default_tip_percentage = 15;
        $merchant_menu_map_resource->allows_dine_in_orders = 1;
        $merchant_menu_map_resource->save();


        $order_adapter = new OrderAdapter(getM());
        $cart_data = getEmptyCart($user,$merchant_id);
        $items = $order_adapter->getItemsForCartWithOneModifierPerModifierGroup($complete_menu,6);
        $cart_data['items'] = $items;

        //$checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());


        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/cart/checkout";
        $response = $this->makeRequestWithDefaultHeaders($url, $user['email'].":welcome","POST",$cart_data);
        $checkout_resource = json_decode($response);
        $ucid = $checkout_resource->data->ucid;


        $this->assertNotNull($checkout_resource->error);
        $expected_message = str_replace('%%max%%',2,PlaceOrderController::MAX_NUMBER_OF_ENTRES_EXCEEDED_ERROR_MESSAGE);
        $expected_message = str_replace('%%diff%%',1,$expected_message);

        $this->assertEquals($expected_message,$checkout_resource->error->error);

        $merchant_menu_map_resource->max_entres_per_order = 3;
        $merchant_menu_map_resource->save();

        //$checkout_resource2 = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());

        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/cart/checkout";
        $response = $this->makeRequestWithDefaultHeaders($url, $user['email'].":welcome","POST",$cart_data);
        $checkout_result_as_array = json_decode($response,true);
        $this->assertEquals('200', $this->info['http_code'],"an error was thrown: ".$checkout_result_as_array['error']['error']);
        $checkout_data = $checkout_result_as_array['data'];
        $this->assertNull($checkout_result_as_array['error']);

        $this->assertEquals('15%',$checkout_data['pre_selected_tip_value']);

        $this->assertTrue($checkout_data['allows_dine_in_orders'],"It should have the flag on for dine in");

        $order_data = array();
        $order_data['tip'] = 0.00;
        $payment_array = $checkout_data['accepted_payment_types'];
        $order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];

        $lead_times_array = $checkout_data['lead_times_array'];
        $order_data['requested_time'] = $lead_times_array[0];

        $order_data['dine_in'] = true;

        $cart_ucid = $checkout_data['ucid'];
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/orders/$cart_ucid";
        $response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','POST',$order_data);
        $request_result_as_array = json_decode($response,true);
        $this->assertEquals('200', $this->info['http_code'],"an error was thrown: ".$request_result_as_array['error']['error']);
        $this->assertNull($request_result_as_array['error'],"an error was thrown: ".$request_result_as_array['error']['error']);

        $result_order_data = $request_result_as_array['data'];

        $this->assertEquals('dine in',substr($result_order_data['note'],0,7),"It should have appended the dine in to the note");

    }


    function testGetCorrectErrorFromDeliveryTurnedOffAtMerchant()
    {
        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);

        $mdi_resource = MerchantDeliveryInfoAdapter::getFullMerchantDeliveryInfoAsResource($merchant_id);

        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);
        $user_id = $user['user_id'];
        $email = $user_resource->email;

        $json = '{"user_addr_id":null,"user_id":"' . $user_id . '","name":"","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"9709262121","lat":40.059190,"lng":-105.282113}';
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


        $merchant_resource->delivery = 'N';
        $merchant_resource->save();

//        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id", 'GET', null, getM());
//        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
//        $resource = $merchant_controller->processV2Request();


        $url = "http://127.0.0.1:".$this->api_port."/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id";
        $response = $this->makeRequestWithDefaultHeaders($url, "$email:welcome");
        $response_as_array = json_decode($response,true);
        $this->assertEquals(500,$response_as_array['http_code']);
        $this->assertEquals(MerchantDeliveryInfoAdapter::MERCHANT_DELIVERY_NOT_ACTIVE_ERROR_MESSAGE,$response_as_array['error']['error']);


    }

    function testForgotPasswordGuest()
    {
        $user_resource = createGuestUser();
        $email = $user_resource->email;
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/forgotpassword";
        $response = $this->makeRequestWithDefaultHeaders($url, "admin:welcome",'Post',array("email"=>$email));
        $this->assertEquals(500, $this->info['http_code']);
        $request_result_as_array = json_decode($response,true);
        $this->assertEquals("We're sorry but we do not have a user registered with that email, please check your entry.", $request_result_as_array['error']['error']);

        // now check login
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/usersesssion";
        $response = $this->makeRequestWithDefaultHeaders($url, "$email:welcome");
        $this->assertEquals(401, $this->info['http_code']);
        $response_as_array = json_decode($response,true);
        $error = $response_as_array['error']['error'];
        $this->assertEquals('Your username does not exist in our system, please check your entry.',$error);
    }


//    function testMerchantClosed()
//    {
//        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
//        $merchant_id = $merchant_resource->merchant_id;
//
//
//
//        // create the user record
//        $user_resource = createNewUserWithCCNoCVV();
//        $user = logTestUserResourceIn($user_resource);
//
//        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
//
//        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/cart/checkout";
//        $response = $this->makeRequestWithDefaultHeaders($url, $user['email'].":welcome","POST",$cart_data);
//        $checkout_data = json_decode($response,true);
//
//            //        $request = createRequestObject($url,'post',$json_encoded_data,'application/json');
//            //        $place_order_controller = new PlaceOrderController($mt, $user, $request);
//            //        $checkout_resource = $place_order_controller->processV2Request();
//
//        $cart_info = $checkout_data['data'];
//        $this->assertTrue(isset($cart_info['ucid']),"it should return a cart object");
//        $this->assertNotNull($cart_info['order_summary'],"IT should have the order summary of the cart object");
//        $order_summary = $cart_info['order_summary'];
//        $this->assertCount(4,$order_summary);
//        $this->assertCount(1,$order_summary['cart_items']);
//        $this->assertCount(3,$order_summary['receipt_items']);
//    }


//    function testFacebookAuthenticateWithToken()
//    {
//        $facebook_authentication_token = createCode(20);
//        $facebook_authentication_token = 'EAAB7uXMcknUBAEeBaHWZBViGVg7uIPkuY1s4f1dQbZAa6WhmbFUWTHSqPJlcaCA3pCk1l3ZAlDXlm7AstYtw0Sd4KgKwPZC6SpsLAAew4U2JQZAFltPU4k9i6pQd223fnSpvbhYoXFrsUeQZCMKQZBZByYU1m6V5wPFJUZArjATZCFHrnJTQDQejwpvgqfvmZBN0m4ZD';
//        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/usersesssion";
//        $response = $this->makeRequestWithDefaultHeaders($url, "facebook_authentication_token:$facebook_authentication_token");
//        $this->assertEquals(200, $this->info['http_code']);
//        $response_as_array = json_decode($response,true);
//        $email = $response_as_array['data']['email'];
//        $this->assertEquals('sumdumguy@dummy.com',$email);
//    }

//    function testProductionMenu()
//    {
//        $url = "http://api.splickit.com/app2/apiv2/merchants/107118?log_level=5";
//        //com.splickit.ford
//
//        $headers = array("X_SPLICKIT_CLIENT_ID"=>"com.splickit.ford","X_SPLICKIT_CLIENT_DEVICE"=>"command_line","HTTP_X_SPLICKIT_CLIENT_VERSION"=>"10.5.0","X_SPLICKIT_CLIENT"=>"laptop");
//        $header_array = array();
//        foreach ($headers as $key=>$value) {
//            $header_array[] = $key.":".$value;
//        }
//        $result = $this->makeRequest($url,'store_tester@dummy.com:st','GET',$header_array,$data);
//        $request_result_as_array = json_decode($result,true);
//        $merchant_data = $request_result_as_array['data'];
//       $menu = $merchant_data['menu'];
//    }

    function testDoNotRememberMe()
    {
        $user_resource = createNewUser();
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/apiv2/users";
        $response = $this->makeRequestWithDefaultHeaders($url, $user_resource->email . ':welcome', 'GET', $data);
        $result_as_array = json_decode($response, true);
        $this->assertNull($result_as_array['error']);
        $this->assertEquals($user_resource->uuid, $result_as_array['data']['user_id']);
        $this->assertNotNull($result_as_array['data']['splickit_authentication_token']);
        $data = $result_as_array['data'];
        $authentication_token = $data['splickit_authentication_token'];
        $expires_at = $data['splickit_authentication_token_expires_at'];
        $hours = ($expires_at - time()) / 60 / 60;
        $rounded_hours = round($hours, 0);
        $this->assertEquals(12, $rounded_hours, "it shold expire in 12 hours");
    }

    function testRememberMe()
    {
        $user_resource = createNewUser();
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users?remember_me=1";
//        $request = createRequestObject($url,'GET',null);
//        $usersession_controller = new UsersessionController(getM(),$user_resource->getDataFieldsReally(),$request,5);
//        $resource = $usersession_controller->getUserSession();

        $response = $this->makeRequestWithDefaultHeaders($url, $user_resource->email.':welcome','GET',null);
        $result_as_array = json_decode($response,true);
        $this->assertNull($result_as_array['error']);
        $this->assertEquals($user_resource->uuid, $result_as_array['data']['user_id']);
        $this->assertNotNull($result_as_array['data']['splickit_authentication_token']);
        $data = $result_as_array['data'];
        $expires_at = $data['splickit_authentication_token_expires_at'];
        $days = ($expires_at - time())/60/60/24;
        $rounded_days = round($days,0);
        $this->assertEquals(180,$rounded_days,"it should expire in 6 months or 180 days");
    }


    function testSkinBustCacheBeforeLoginIssue()
    {

//        $skin_adapter = new SkinAdapter(getM());
//        $skin_adapter->cache_enabled = false;
//
//        $options[TONIC_FIND_BY_SQL] = "SELECT * FROM Skin WHERE (external_identifier = 'com.splickit.snarfs' OR public_client_id = 'com.splickit.snarfs') AND logical_delete = 'N'";
//        $skin_resource = Resource::find($skin_adapter,null,$options);


//        $skin = SkinAdapter::getSkin('com.splickit.snarfs',false);

        setContext('com.splickit.snarfs');
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/skins/com.splickit.snarfs";
        $response = $this->makeRequestWithDefaultHeaders($url, "store_tester@dummy.com:st");
    }


    function testContextAssociationOnRequest()
    {
        setContext('com.splickit.snarfs');
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        $url = "http://127.0.0.1:".$this->api_port."/app2/phone/merchants/$merchant_id?order_type=pickup";
//        $user_resource = createNewUserWithCCNoCVV();
//        $email = $user_resource->email;
//        $response = $this->makeRequestWithDefaultHeaders($url, "$email:welcome");

        $response = $this->makeRequestWithDefaultHeaders($url, "store_tester@dummy.com:st");

        myerror_log($response);

    }

    function testIsItWorking()
    {
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/merchants/1234567";
        $headers = array("X_SPLICKIT_CLIENT_ID"=>"com.splickit.rewardr","X_SPLICKIT_CLIENT_DEVICE"=>"unit_testing","HTTP_X_SPLICKIT_CLIENT_VERSION"=>"10.5.0","X_SPLICKIT_CLIENT"=>"APIDispatchTest");
        $header_array = array();
        foreach ($headers as $key=>$value) {
            $header_array[] = $key.":".$value;
        }
        $return = $this->makeRequest($url,null,'GET',$header_array);
        $this->assertEquals("bad request",$return);
    }

//    function testRangeAndMax()
//    {
//        setContext('com.splickit.pitapit');
//            $external_id = $this->getExternalId();
//            $headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_VERSION:4.5.5","X_SPLICKIT_CLIENT_DEVICE:web","X_SPLICKIT_CLIENT:APIDispatchTest","NO_CC_CALL:true");
//            $url = "https://device.splickit.com/app2/apiv2/merchants?zip=80302&range=2500&limit=500";
//            $result = $this->makeRequest($url,$username_password,'GET',$headers,$data);
//            $request_result_as_array = json_decode($result,true);
//            $merchants = $request_result_as_array['data']['merchants'];
//            $count = sizeof($merchants);
//            $this->assertTrue($count > 150);
//    }

    function testPublicSkinPasswordCreation()
    {
//        $sql = "DELETE FROM Skin WHERE skin_name = 'testskin' LIMIT 1";
//        $skin_adapter = new SkinAdapter();
//        $skin_adapter->_query($sql);
        $code = generateAlphaCode(4);
        $skin_name = 'testskin'.$code;
        $brand_name = 'testbrand'.$code;
        $skin_resource = getOrCreateSkinAndBrandIfNecessary($skin_name,$brand_name,null,null);
        setContext('com.splickit.'.$skin_name);
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/skins/delete_password";
        $response = $this->makeRequestWithDefaultHeaders($url, $up,"DELETE");

        $skin_resource = Resource::find(new SkinAdapter(),getSkinIdForContext());
        $this->assertNull($skin_resource->password,"there should be no password");
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/skins";
        $data = ['password'=>'test1234'];

        $response = $this->makeRequestWithDefaultHeaders($url, $up,"POST",$data);
//        $request = createRequestObject($url,'POST',json_encode($data));
//        $skin_controller = new SkinController($mt, $up, $request);
//        $resource = $skin_controller->processRequest();

        $after_skin_resource = Resource::find(new SkinAdapter(),$skin_resource->skin_id);
        $this->assertNotNull($after_skin_resource->password);
        return $after_skin_resource;
    }

    /**
     * @depends testPublicSkinPasswordCreation
     */
    function testAuthenticationWithAdminUserForPublicApiAccessBadPassword($after_skin_resource)
    {
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users";
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_USERPWD, "admin:welcome");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("X_SPLICKIT_CLIENT_ID:".$after_skin_resource->public_client_id,"X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:PublicCreateUserTest","NO_CC_CALL:true","Content-Type: application/json"));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POST, 1);
        $new_user_data = createNewUserDataFields();
        curl_setopt($curl, CURLOPT_POSTFIELDS,json_encode($new_user_data));
        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        $this->assertEquals(401,$info['http_code']);
        curl_close($curl);
        $this->assertEquals('application/json', $info['content_type']);
        $new_user_response = json_decode($result,true);
        $this->assertNotNull($new_user_response['error'],"should have gotten an error");
        $this->assertEquals("Admin Authentication Error",$new_user_response['error']['error']);

        $this->assertNull(getStaticRecord(array('email'=>$new_user_data['email']),'UserAdapter'),"Should not have created a user request");
    }

    /**
     * @depends testPublicSkinPasswordCreation
     */
    function testAuthenticationWithAdminUserForPublicApiAccessGoodPassword($after_skin_resource)
    {
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users";
        $curl = curl_init($url);
        $headers = array("X_SPLICKIT_CLIENT_ID:".$after_skin_resource->public_client_id,"X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:PublicCreateUserTest","NO_CC_CALL:true","Content-Type: application/json");
        curl_setopt($curl, CURLOPT_USERPWD, "admin:test1234");
        //curl_setopt($curl, CURLOPT_HTTPHEADER, array("X_SPLICKIT_CLIENT_ID:".$after_skin_resource->public_client_id,"X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:PublicCreateUserTest","NO_CC_CALL:true","Content-Type: application/json"));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        $new_user_data = createNewUserDataFields();
        $json = json_encode($new_user_data);
        curl_setopt($curl, CURLOPT_POSTFIELDS,$json);
        $headers[] = 'Content-Length: ' . strlen($json);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        logCurl($url,'POST',"admin:test1234",$headers,$json);
        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);
        $this->assertEquals('application/json', $info['content_type']);
        $new_user_response = json_decode($result,true);
        $this->assertNull($new_user_response['error'],"should NOT have gotten an error");

        $user_record = getStaticRecord(array('email'=>$new_user_data['email']),'UserAdapter');
        $this->assertNotNull($user_record);
        return $after_skin_resource;
    }

    /**
     * @depends testAuthenticationWithAdminUserForPublicApiAccessGoodPassword
     */
    function testGetVioWriteCredentialsWithPublicPasswordEnabledInternalGood($after_skin_resource)
    {
        setContext($after_skin_resource->external_identifier);
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/credit_card/getviowritecredentials";
        $response = $this->makeRequestWithDefaultHeaders($url,'admin:welcome','GET');
        $this->assertEquals(200,$this->info['http_code']);
        $result_as_array = json_decode($response,true);
        $data = $result_as_array['data'];
        $this->assertNotNull($data['vio_write_credentials'],"vio write credentials should not be null");
        $this->assertEquals(getProperty('vio_write_username_password'),$data['vio_write_credentials']);
    }

    /**
     * @depends testAuthenticationWithAdminUserForPublicApiAccessGoodPassword
     */
    function testGetVioWriteCredentialsWithPublicPasswordEnabledInternalBad($after_skin_resource)
    {
        setContext($after_skin_resource->external_identifier);
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/credit_card/getviowritecredentials";
        $response = $this->makeRequestWithDefaultHeaders($url,'admin:test1234','GET');
        $this->assertEquals(401,$this->info['http_code']);
//        $result_as_array = json_decode($response,true);
//        $data = $result_as_array['data'];
//        $this->assertNotNull($data['vio_write_credentials'],"vio write credentials should not be null");
//        $this->assertEquals(getProperty('vio_write_username_password'),$data['vio_write_credentials']);
    }


    /**
     * @depends testAuthenticationWithAdminUserForPublicApiAccessGoodPassword
     */
    function testGetVioWriteCredentialsWithPublicPasswordEnabledExternalGood($after_skin_resource)
    {
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/credit_card/getviowritecredentials";
        $curl = curl_init($url);
        $headers = array("X_SPLICKIT_CLIENT_ID:".$after_skin_resource->public_client_id,"X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:PublicCreateUserTest");
        curl_setopt($curl, CURLOPT_USERPWD, "admin:test1234");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        logCurl($url,'GET',"admin:test1234",$headers,null);
        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);
        $this->assertEquals(200,$info['http_code']);




//        $response = $this->makeRequestWithDefaultHeaders($url,'admin:welcome','GET');
//        $this->assertEquals(200,$this->info['http_code']);
//        $result_as_array = json_decode($response,true);
//        $data = $result_as_array['data'];
//        $this->assertNotNull($data['vio_write_credentials'],"vio write credentials should not be null");
//        $this->assertEquals(getProperty('vio_write_username_password'),$data['vio_write_credentials']);
    }

    /**
     * @depends testAuthenticationWithAdminUserForPublicApiAccessGoodPassword
     */
    function testGetVioWriteCredentialsWithPublicPasswordEnabledExternalBad($after_skin_resource)
    {
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/credit_card/getviowritecredentials";
        $curl = curl_init($url);
        $headers = array("X_SPLICKIT_CLIENT_ID:".$after_skin_resource->public_client_id,"X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:PublicCreateUserTest");
        curl_setopt($curl, CURLOPT_USERPWD, "admin:welcome");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        logCurl($url,'GET',"admin:welcome",$headers,null);
        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);
        $this->assertEquals(401,$info['http_code']);
    }

    /**
     * @depends testPublicSkinPasswordCreation
     */
    function testForgotPasswordBadEmailWithAdminAuth($after_skin_resource)
    {
        setContext($after_skin_resource->external_identifier);
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/forgotpassword";
//        $response = $this->makeRequest($url, "admin:test1234",'Post',array("email"=>'sumdumemail@email.com'));
//        $request_result_as_array = json_decode($response,true);
//        $this->assertEquals("Sorry, that email is not registered with us. Please check your entry.", $request_result_as_array['error']['error']);
//        $this->assertEquals(404, $this->info['http_code']);

        $skin = getSkinForContext();
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_USERPWD, "admin:test1234");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("X_SPLICKIT_CLIENT_ID:".$skin['public_client_id'],"X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:PublicCreateUserTest","NO_CC_CALL:true","Content-Type: application/json"));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POST, 1);
        $data = array("email"=>'sumdumemail@email.com');
        curl_setopt($curl, CURLOPT_POSTFIELDS,json_encode($data));
        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);
        $this->assertEquals('application/json', $info['content_type']);
        $new_user_response = json_decode($result,true);

    }

    function testGetMerchantMetaData()
    {
        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        $url = "http://127.0.0.1:".$this->api_port."/app2/phone/getmerchantmetadata/$merchant_id";
        $result = $this->makeRequestWithDefaultHeaders($url);
        $this->assertEquals(200, $this->info['http_code'],"Should have gotten an OK response");
        $request_result_as_array = json_decode($result,true);
        $this->assertNotNull($request_result_as_array['hours']);
        $this->assertNotNull($request_result_as_array['delivery_hours']);

    }

    function testAPIDocs()
    {
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2";
        $result = $this->makeRequestWithDefaultHeaders($url, null);
        $this->assertContains('<title>Splick.it: ApiDocumentation</title>',$result);

    }

    function testDoNotAllowCrossBrandMerchantLoadingInBrandedSkinsInProduction()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/merchants/$merchant_id?log_level=5";
        $result = $this->makeRequestWithDefaultHeaders($url, null);
        $this->assertEquals('200', $this->info['http_code']);
        $request_result_as_array = json_decode($result,true);
        $merchant_data = $request_result_as_array['data'];
        $this->assertEquals($merchant_resource->numeric_id,$merchant_data['numeric_id']);

        setContext('com.splickit.snarfs');
        $request = createRequestObject($url,'GET');
        $merchant_controller = new MerchantController(getM(),null,$request);
        $resource = $merchant_controller->processV2Request();

        $result = $this->makeRequestWithDefaultHeaders($url, null);
        $this->assertEquals('422', $this->info['http_code']);



    }

    function testRejectDeliveryOrderCreationWithoutUserAddressId()
    {
        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;

        // create the user record
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"1045 Pine Street","address2":"","city":"boulder","state":"co","zip":"80302","phone_no":"9709262121","lat":40.0197891,"lng":-105.284703}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "application/json";
        $request->_parseRequestBody();
        $request->method = 'POST';
        $request->url = "/users/" . $user['uuid'] . "/userdeliverylocation";
        $user_controller = new UserController($mt, $user, $request, 5);
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'delivery');
        $cart_data['submitted_order_type'] = 'delivery';
        //$cart_data['user_addr_id'] = $user_address_id;
        $cart_data['user_addr_id'] = null;

        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/cart/checkout";

//        $request = createRequestObject($url,'post',json_encode($cart_data),'application/json');
//        $place_order_controller = new PlaceOrderController($mt, $user, $request);
//        $checkout_resource = $place_order_controller->processV2Request();

        $response = $this->makeRequestWithDefaultHeaders($url, $user['email'].":welcome","POST",$cart_data);
        $this->assertEquals('422', $this->info['http_code']);
        $checkout_data = json_decode($response,true);

        $this->assertNotNull($checkout_data['error'],"should have found an internal system error since no user_addr_id was submitted with a delivery order");
        $this->assertEquals(CartsAdapter::CREATE_DELIVERY_LOCATION_ERROR_MESSAGE, $checkout_data['error']['error']);
        $this->assertEquals("CreateOrderError",$checkout_data['error']['error_type']);
    }

    function testDeliveryMinimumNotMetOnCartCheckoutAndAddMakeSureToReturnCartObjectWithError()
    {
        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;

        $data = array("merchant_id"=>$merchant_resource->merchant_id);

        // set merchant delivery info
        $mdia = new MerchantDeliveryInfoAdapter($mimetypes);
        $mdia_resource = $mdia->getExactResourceFromData($data);
        $mdia_resource->minimum_order = 10.00;
        $mdia_resource->save();



        // create the user record
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $json = '{"user_addr_id":null,"user_id":"' . $user['user_id'] . '","name":"","address1":"1045 Pine Street","address2":"","city":"boulder","state":"co","zip":"80302","phone_no":"9709262121","lat":40.0197891,"lng":-105.284703}';
        $request = new Request();
        $request->body = $json;
        $request->mimetype = "application/json";
        $request->_parseRequestBody();
        $request->method = 'POST';
        $request->url = "/users/" . $user['uuid'] . "/userdeliverylocation";
        $user_controller = new UserController($mt, $user, $request, 5);
        $response = $user_controller->processV2Request();
        $this->assertNull($response->error, "should not have gotten a delivery save error but did");
        $this->assertNotNull($response->user_addr_id);
        $user_address_id = $response->user_addr_id;
        $user_delivery_location_resource = SplickitController::getResourceFromId($user_address_id, 'UserDeliveryLocation');

        // url = isindeliveryarea
        $request = createRequestObject("/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id?log_level=5",'GET',$b,$m);
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();

        $this->assertTrue(isset($resource->is_in_delivery_range),"should have found the 'is in delivery range' field");
        $this->assertTrue($resource->is_in_delivery_range," the is in delivery range should be true");

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'delivery');
        $cart_data['user_addr_id'] = $user_address_id;

        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/cart/checkout";
        $response = $this->makeRequestWithDefaultHeaders($url, $user['email'].":welcome","POST",$cart_data);
        $this->assertEquals('422', $this->info['http_code']);
        $checkout_data = json_decode($response,true);

//        $request = createRequestObject($url,'post',$json_encoded_data,'application/json');
//        $place_order_controller = new PlaceOrderController($mt, $user, $request);
//        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNotNull($checkout_data['error'],"should have found a delivery minimum but the get checkout went through");
        $this->assertEquals("Minimum order required! You have not met the minimum subtotal of $10.00 for your deliver area.", $checkout_data['error']['error']);
        $this->assertEquals("CheckoutError",$checkout_data['error']['error_type']);

        $cart_info = $checkout_data['data'];
        $this->assertTrue(isset($cart_info['ucid']),"it should return a cart object");
        $this->assertNotNull($cart_info['order_summary'],"IT should have the order summary of the cart object");
        $order_summary = $cart_info['order_summary'];
        $this->assertCount(4,$order_summary);
        $this->assertCount(1,$order_summary['cart_items']);
        $this->assertCount(3,$order_summary['receipt_items']);
    }


    function testHealthCheckUrl()
    {
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/apiv2/healthcheck";
        $response = $this->makeRequest($url,$userpassword,'GET',$header_array,$data);
        $this->assertEquals('200', $this->info['http_code']);
        $healthcheck_response_array = json_decode($response,true);
        $this->assertEquals("true",$healthcheck_response_array['success']);
    }

    function testGetMerchantOne()
    {
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/apiv2/merchants/" . $this->ids['merchant_id'] . "?log_level=5";
        $result = $this->makeRequestWithDefaultHeaders($url, $userpassword);
        $this->assertEquals('200', $this->info['http_code']);
        $request_result_as_array = json_decode($result, true);
        $this->assertCount(4, $request_result_as_array);
        $this->assertNull($request_result_as_array['error']);
        $merchant = $request_result_as_array['data'];
        $this->assertNotNull($merchant['merchant_id']);
        $this->assertNotNull($merchant['menu']);
        $this->assertNotNull($merchant['todays_hours']);
        $this->assertNotNull($merchant['payment_types']);
    }

    function testCreateNewUser()
    {
        setContext('com.splickit.vtwoapi');

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/apiv2/users?log_level=5";
        $new_user_data = createNewUserDataFields();
        $new_user_data['birthday'] = "12/21";
        $new_user_data['donation_active'] = 'Y';

//        $external_id = $this->getExternalId();
//        $headers = array("X_SPLICKIT_CLIENT_ID"=>"$external_id","X_SPLICKIT_CLIENT_DEVICE"=>"unit_testing","HTTP_X_SPLICKIT_CLIENT_VERSION"=>"5.3","X_SPLICKIT_CLIENT"=>"APIDispatchTest","NO_CC_CALL"=>"true");
//        $header_array = array();
//        foreach ($headers as $key=>$value) {
//            $header_array[] = $key.":".$value;
//        }
//        $response = $this->makeRequest($url,'','POST',$header_array,$new_user_data);

        $response = $this->makeRequestWithDefaultHeaders($url, "admin:welcome","POST",$new_user_data);
        $this->assertEquals('200', $this->info['http_code']);
        $new_user_response = json_decode($response,true);
        $this->assertNull($new_user_response['error'],"should NOT have gotten an error");
        $user = $new_user_response['data'];
        $this->assertNotNull($user['user_id']);

        // now make sure password is not in request times table
        $stamp = $new_user_response['stamp'];
        $record = getStaticRecord(array("stamp"=>$stamp),'RequestTimesAdapter');
        $request_body = $record['request_body'];
        $this->assertContains('first_name',$request_body);
        $this->assertNotContains('welcome',$request_body);
        $this->assertContains('password',$request_body);
        $this->assertContains('XXXXXXXX',$request_body);

        //validate round up was turned on for this skin
        $user_record = getUserFromId($user['user_id']);
        $user_skin_donation_adapter = new UserSkinDonationAdapter(getM());
        $record = $user_skin_donation_adapter->getRecord(["user_id"=>$user_record['user_id']]);
        $this->assertEquals('Y',$record['donation_active'],"The user should have a record in the donations table and it should be active");

        return $user;
    }

    /**
     * @depends testCreateNewUser
     */
    function testCreateNewUserSamePhoneNumberCheckForLoyaltyMessage($user)
    {
        setContext('com.splickit.vtwoapi');
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/apiv2/users?log_level=5";
        $new_user_data = createNewUserDataFields();
        $new_user_data['contact_no'] = $user['contact_no'];
        $response = $this->makeRequestWithDefaultHeaders($url, "admin:welcome","POST",$new_user_data);
        $this->assertEquals('200', $this->info['http_code']);
        $new_user_response = json_decode($response,true);
        $this->assertNull($new_user_response['error'],"should NOT have gotten an error");
        $this->assertContains(LoyaltyController::LOYALTY_NUMBER_DUPLICATE_MESSAGE,$new_user_response['message']);
    }

    function testBadErrorMessageOfMultipleRows()
    {
        $brand_resource = Resource::find(new BrandAdapter($mimetypes),"150");
        $brand_resource->loyalty = 'Y';
        $brand_resource->save();

        $blr_data['brand_id'] = 150;
        $blr_data['loyalty_type'] = 'splickit_earn';
        $brand_loyalty_rules_resource = Resource::factory(new BrandLoyaltyRulesAdapter($m),$blr_data);
        $brand_loyalty_rules_resource->save();

        setContext('com.splickit.snarfs');
        $user_resource = createNewUserWithCCNoCVV();

        //validate that a row was create in the Loyalty table
        $ln = str_replace(' ','',$user_resource->contact_no);
        $ln = str_replace('-','',$ln);
        $record = getStaticRecord(array("loyalty_number"=>$ln),'UserBrandPointsMapAdapter');
        $this->assertNotNull($record,"There should have been a record created");

        //create another user assigne loyalty number to same number
        $user_resource = createNewUserWithCCNoCVV();
        $resource = Resource::find(new UserBrandPointsMapAdapter($m),null,array(3=>array("user_id"=>$user_resource->user_id)));
        $resource->loyalty_number = $ln;
        $resource->save();

        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users?log_level=5";
        $new_user_data = createNewUserDataFields();
        $new_user_data['contact_no'] = $ln;
        $response = $this->makeRequestWithDefaultHeaders($url, "admin:welcome","POST",$new_user_data);
        $this->assertEquals('200', $this->info['http_code']);
        $new_user_response = json_decode($response,true);
        $this->assertNull($new_user_response['error'],"should NOT have gotten an error");
        $user = $new_user_response['data'];
        $this->assertNotNull($user['user_id']);
        $this->assertContains("Please Note: This phone number is in use by another user. Your remote loyalty number is NOW:",$new_user_response['message']);

    }


    function testBreakCClink()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $this->assertEquals("1C20000001",$user_resource->flags);
        $uuid = $user_resource->uuid;
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/$uuid/credit_card";
        $user_name = $user_resource->email;
        $response = $this->makeRequestWithDefaultHeaders($url,"$user_name:welcome",'DELETE',$data);

        $user = UserAdapter::getUserResourceFromId($user_resource->user_id);
        $this->assertEquals("1000000001",$user->flags);

        $result_as_array = json_decode($response,true);
        $data = $result_as_array['data'];
        $this->assertEquals("Your credit card has been deleted.",$data['user_message']);

    }

    function testGetVioWriteCredentialsone()
    {
        setContext('com.splickit.moes');
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/credit_card/getviowritecredentials";
        $response = $this->makeRequestWithDefaultHeaders($url,'admin:welcome','GET',$data);
        $result_as_array = json_decode($response,true);
        $data = $result_as_array['data'];
        $this->assertNotNull($data['vio_write_credentials'],"vio write credentials should not be null");
        $this->assertEquals(getProperty('vio_write_username_password'),$data['vio_write_credentials']);
    }

    function testPunchAuthenticationScheme()
    {
        setContext('com.splickit.moes');
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/usersession";
        $data['headers'][] = "Punch_authentication_token: eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL3B1bmNoaC5jb20iLCJlbWFpbF92ZXJpZmllZCI6ZmFsc2UsInN1YiI6MTA5NjE4NywiZW1haWwiOiJyYWRhbW55Y0BnbWFpbC5jb20iLCJpYXQiOjE0NTkzNTg4OTksImV4cCI6MTQ1OTM2MjQ5OX0.flfD1XQRXqU32f5H7ZyjZ70n7e7IfzwJ2NNmIjAuLZ4";
        $response = $this->makeRequestWithDefaultHeaders($url,$up,'GET',$data);
        $result_as_array = json_decode($response,true);

    }


    function testGetGroupOrderLeadTimes()
    {
        setContext('com.splickit.vtwoapi');
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/merchants/".$this->ids['merchant_id']."/grouporderavailabletimes/pickup";
        $response = $this->makeRequestWithDefaultHeaders($url,$up);
        $response_as_array = json_decode($response,true);
        $this->assertEquals(200,$response_as_array['http_code']);
        $lead_times = $response_as_array['data']['submit_times_array'];

    }

    function testGetCorrectHttpCodeFromBadPassword()
    {
        $user_resource = createNewUser();
        $user_resource->bad_login_count = 4;
        $user_resource->save();
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users";
        $response = $this->makeRequestWithDefaultHeaders($url, $user_resource->email.":sumpassword");
        $response_as_array = json_decode($response,true);
        $this->assertEquals(401,$response_as_array['http_code']);
        $this->assertEquals(401, $this->info['http_code'],"Should have gotten a failed authentication status code");

    }



    /*	function testGetJMMenuWIthNoPOints()
        {
            $url = "https://puat.splickit.com/app2/phone/merchants/105188?order_type=pickup";
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_USERPWD, 'store_tester@tullys.com:test');

            $external_id = "com.splickit.jerseymikes";

            $headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_VERSION:100","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:APIDispatchTest","NO_CC_CALL:true");
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            //curl_setopt($curl, CURLOPT_VERBOSE, 1);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($curl);
            $result_array = json_decode($result,true);
            $this->assertEquals(200,$result_array['http_code']);
            $this->info = curl_getinfo($curl);

        }
    */
    function testGetUserLast4ZeroFill()
    {
        $user_resource = createNewUserWithCC();
        $user_resource->last_four = "0004";
        $user_resource->save();
        $user = UserAdapter::staticGetRecordByPrimaryKey($user_resource->user_id, "User");
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users?log_level=5";
        $response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome');
        $this->assertEquals(200, $this->info['http_code']);
        $request_result_as_array = json_decode($response,true);
        $user_session = $request_result_as_array['data'];
        $this->assertEquals("0004",$user_session['last_four'],"last 4 should have be zero filled");
    }

    function testMoesAppBombAndriod()
    {
        setContext('com.splickit.moes');
        createNewTestMerchant($this->ids['menu_id']);
        createNewTestMerchant($this->ids['menu_id']);
        createNewTestMerchant($this->ids['menu_id']);
        $sql = "insert into Skin_Merchant_Map (SELECT null,4,merchant_id,now(),now(),'N' FROM Merchant where brand_id = 112)";
        $mmma = new MerchantMenuMapAdapter($m);
        $mmma->_query($sql);
        setProperty('minimum_android_version',"1.0.0");
        $external_id = $this->getExternalId();
        $headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_VERSION:4.5.5","X_SPLICKIT_CLIENT_DEVICE:android","X_SPLICKIT_CLIENT:APIDispatchTest","NO_CC_CALL:true");
        $url = "http://127.0.0.1:".$this->api_port."/app2/phone/merchants";
        $result = $this->makeRequest($url,$username_password,'GET',$headers,$data);
        $request_result_as_array = json_decode($result,true);
        $this->assertEquals(400, $this->info['http_code'],"Should have gotten a bad request http code but got: ".$this->info['http_code']);
        $this->assertEquals("Sorry, this app is no longer supported. Please download our new `Moe's Rockin' Rewards` app.",$request_result_as_array['ERROR']);
        $this->assertEquals("http://market.android.com/details?id=com.splickit.moes",$request_result_as_array['URL']);
        $this->assertEquals("App Discontinued!",$request_result_as_array['TEXT_TITLE']);


    }

    function testMoesAppBombIPhone()
    {
        setContext('com.splickit.moes');
        createNewTestMerchant($this->ids['menu_id']);
        setProperty('minimum_iphone_version',"1.0.0");
        $external_id = $this->getExternalId();
        $headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_VERSION:4.5.5","X_SPLICKIT_CLIENT_DEVICE:iphone","X_SPLICKIT_CLIENT:APIDispatchTest","NO_CC_CALL:true");
        $url = "http://127.0.0.1:".$this->api_port."/app2/phone/merchants";
        $result = $this->makeRequest($url,$username_password,'GET',$headers,$data);
        $request_result_as_array = json_decode($result,true);
        $this->assertEquals(400, $this->info['http_code'],"Should have gotten a bad request http code but got: ".$this->info['http_code']);
        $this->assertEquals("Sorry, this app is no longer supported. Please download our new `Moe's Rockin' Rewards` app.",$request_result_as_array['ERROR']);
        $this->assertEquals("http://itunes.apple.com/us/app/moes-southwest-grill/id389854659?mt=8&ls=1",$request_result_as_array['URL']);
        $this->assertEquals("App Discontinued!",$request_result_as_array['TEXT_TITLE']);
    }
    
    function testWebNoAppBombMoes()
    {
        setContext('com.splickit.moes');
        createNewTestMerchant($this->ids['menu_id']);
        $external_id = $this->getExternalId();
        $headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_VERSION:3","X_SPLICKIT_CLIENT_DEVICE:web","X_SPLICKIT_CLIENT:APIDispatchTest","NO_CC_CALL:true");
        $url = "http://127.0.0.1:".$this->api_port."/app2/phone/merchants";
        $result = $this->makeRequest($url,$username_password,'GET',$headers,$data);
        $request_result_as_array = json_decode($result,true);
        $this->assertEquals(200, $this->info['http_code'],"Should have gotten a good request http code but got: ".$this->info['http_code']);
        $this->assertTrue(isset($request_result_as_array[0]['merchant_id']));
    }

    function testAppBombAndriod()
    {
        setProperty('minimum_android_version',"4.5.6");
        $external_id = $this->getExternalId();
        $headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_VERSION:4.5.5","X_SPLICKIT_CLIENT_DEVICE:android","X_SPLICKIT_CLIENT:APIDispatchTest","NO_CC_CALL:true");
        $url = "http://127.0.0.1:".$this->api_port."/app2/phone/merchants";
        $result = $this->makeRequest($url,$username_password,'GET',$headers,$data);
        $request_result_as_array = json_decode($result,true);
        $this->assertEquals(400, $this->info['http_code'],"Should have gotten a bad request http code but got: ".$this->info['http_code']);
        $this->assertEquals("Sorry, your app version is no longer supported. Please Upgrade.",$request_result_as_array['ERROR']);
        $this->assertEquals("http://android.sumdumurl.com",$request_result_as_array['URL']);
        $this->assertEquals("Version Out Of Date!",$request_result_as_array['TEXT_TITLE']);
        setProperty('minimum_android_version',"4.5.4");
        $url = "http://127.0.0.1:".$this->api_port."/app2/phone/merchants";
        $result = $this->makeRequest($url,$username_password,'GET',$headers,$data);
        $request_result_as_array = json_decode($result,true);
        $this->assertTrue(isset($request_result_as_array[0]['merchant_id']));
    }

    function testAppBombIphoneApiV2()
    {
        setProperty('minimum_iphone_version',"4.5.6");
        $external_id = $this->getExternalId();
        $headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_VERSION:4.5.5","X_SPLICKIT_CLIENT_DEVICE:iphone","X_SPLICKIT_CLIENT:APIDispatchTest","NO_CC_CALL:true");
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/merchants";
        $result = $this->makeRequest($url,$username_password,'GET',$headers,$data);
        $request_result_as_array = json_decode($result,true);
        $this->assertEquals(400, $this->info['http_code'],"Should have gotten a bad request http code but got: ".$this->info['http_code']);
        $this->assertEquals("Sorry, your app version is no longer supported. Please Upgrade.",$request_result_as_array['error']['error']);
        $this->assertEquals("http://iphone.sumdumurl.com",$request_result_as_array['error']['error_data']['link']);
        setProperty('minimum_iphone_version',"4.5.4");
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/merchants";
        $result = $this->makeRequest($url,$username_password,'GET',$headers,$data);
        $this->assertEquals(200, $this->info['http_code'],"Should have gotten a good request http code but got: ".$this->info['http_code']);
    }

    function testNewMessageForVersionApiV1()
    {
        setProperty('DO_NOT_CHECK_CACHE',"true",true);
        $external_id = $this->getExternalId();
        $options[TONIC_FIND_BY_METADATA]["external_identifier"] = $external_id;
        $skin_resource = Resource::find(new SkinAdapter($m),"",$options);
        $skin_resource->current_iphone_version = "4.5.8";
        $skin_resource->current_android_version = "4.5.8";
        $skin_resource->save();
        $headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_VERSION:4.5.5","X_SPLICKIT_CLIENT_DEVICE:android","X_SPLICKIT_CLIENT:APIDispatchTest","NO_CC_CALL:true");
        $url = "http://127.0.0.1:".$this->api_port."/app2/phone/usersession?log_level=5";
        $result = $this->makeRequest($url,"store_tester@dummy.com:Spl1ck1t",'GET',$headers,$data);
        $request_result_as_array = json_decode($result,true);
        $this->assertContains("There is a new version of our app available please download to get access to new features",$request_result_as_array['user_message']);
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users?log_level=5";
        $result = $this->makeRequest($url,"store_tester@dummy.com:Spl1ck1t",'GET',$headers,$data);
        $request_result_as_array = json_decode($result,true);
        $this->assertContains("There is a new version of our app available please download to get access to new features",$request_result_as_array['message']);
    }

    function testSaveFavoriteWithAPIV2()
    {
        $user_resource = createNewUserWithCC();
        $user_resource->balance = 100.00;
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);
        $ids = $this->ids;
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($ids['merchant_id'],'pickup','sumdumnote skip hours');
        $order_resource = placeOrderFromOrderData($order_data,$time_stamp);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;

        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/favorites?log_level=5";
        $data['order_id'] = $order_id;
        $data['favorite_name'] = "sumdumname";
        $response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','POST',$data);
        $this->assertEquals(200, $this->info['http_code']);
        $request_result_as_array = json_decode($response,true);
        $data = $request_result_as_array['data'];
        $this->assertNotNull($data['favorite_id']);
        $this->assertEquals("Your favorite was successfully stored.",$data['user_message']);
        return $data['favorite_id'];
    }

    /**
     * @depends testSaveFavoriteWithAPIV2
     */
    function testGetFavorites($favorite_id)
    {
        $favorite_record = FavoriteAdapter::staticGetRecordByPrimaryKey($favorite_id,'FavoriteAdapter');
        $user = UserAdapter::staticGetRecordByPrimaryKey($favorite_record['user_id'],'UserAdapter');
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/".$user['uuid']."/favorites?merchant_menu_type=Pickup&merchant_id=".$this->ids['merchant_id'];
        $data['headers']['X_SPLICKIT_CLIENT_DEVICE'] = 'iphone';
        $response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','GET',$data);
         $this->assertEquals(200, $this->info['http_code']);
        $request_result_as_array = json_decode($response,true);
        $result_data = $request_result_as_array['data'];
        $this->assertCount(1,$data,"It should have found 1 favorite");
        $this->assertEquals('sumdumname',$result_data[0]['favorite_name']);

        // now place a few orders at this merchant and another.

        logTestUserIn($user['user_id']);
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($this->ids['merchant_id']);
        $order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        OrderAdapter::updateOrderStatus('E',$order_resource->order_id);

        //now order from a different merchant
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_resource->merchant_id);
        $order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        OrderAdapter::updateOrderStatus('E',$order_resource->order_id);

        // now get favorites.  there should be 3 records
        $response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','GET',$data);
        $this->assertEquals(200, $this->info['http_code']);
        $request_result_as_array = json_decode($response,true);
        $result_data = $request_result_as_array['data'];
        $this->assertCount(3,$result_data,"It should have found 3 favorites");
    }

    /**
     * @depends testSaveFavoriteWithAPIV2
     */
    function testDeleteFavorite($favorite_id)
    {
        $favorite_record = FavoriteAdapter::staticGetRecordByPrimaryKey($favorite_id,'FavoriteAdapter');
        $user = UserAdapter::staticGetRecordByPrimaryKey($favorite_record['user_id'],'UserAdapter');
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/favorites/$favorite_id?log_level=5";
        $response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','DELETE',$data);
        $this->assertEquals(200, $this->info['http_code']);
        $request_result_as_array = json_decode($response,true);
        $this->assertEquals("Your favorite was successfully deleted",$request_result_as_array['message']);
        $this->assertNull(FavoriteAdapter::staticGetRecordByPrimaryKey($favorite_id,'FavoriteAdapter'),"Should not have found a favorite with that id");

    }

    function testSystemShutdownApiV2()
    {
        setProperty("system_shutdown",'true');
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/merchants";
        $result = $this->makeRequestWithDefaultHeaders($url,$username_password);
        setProperty("system_shutdown",'false');
        $request_result_as_array = json_decode($result,true);
        //$this->assertEquals(590, $this->info['http_code'],"Should have gotten an unprocessable entity https code but got: ".$this->info['http_code']);
        $this->assertEquals(200, $this->info['http_code'],"Should have gotten a 200 OK https code but got: ".$this->info['http_code']);
        $this->assertEquals(getProperty("system_shutdown_message"),$request_result_as_array['error']['error']);
    }

    function testOrderingShutdownApiV2()
    {
        setProperty("ordering_shutdown",'true');
        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', "some note");
        $order_data['merchant_payment_type_map_id'] = $this->ids['merchant_payment_type_map_id_for_cash'];
        $order_data['tip'] = '0.00';
        $order_data['requested_time'] = getTomorrowTwelveNoonTimeStampDenver();

        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/orders";
        $response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','POST',$order_data);
        setProperty("ordering_shutdown",'false');
        $request_result_as_array = json_decode($response,true);
        $this->assertEquals('590', $request_result_as_array['http_code'],"Should have gotten back a 590 error but got: ".$request_result_as_array['http_code']);
        $this->assertEquals("Sorry, the mobile ordering system is currently offline.  Please try again shortly.",$request_result_as_array['error']['error']);

        $merchant_id = $this->ids['merchant_id'];
        $merchant_resource = Resource::find(new MerchantAdapter($m),$merchant_id,$o);
        $merchant_resource->custom_menu_message = 'sum dum menu message';
        $merchant_resource->save();

        setProperty("ordering_shutdown",'true');
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/merchants/$merchant_id";
        $response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','GET',$data);
        setProperty("ordering_shutdown",'false');
        $request_result_as_array = json_decode($response,true);
        $message = $request_result_as_array['message'];
        $this->assertContains('the ordering system is currently offline',$message);
        $this->assertContains('sum dum menu message',$message);

    }

    function testGetGroupOrderStatusAnonymous()
    {
        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);
        $request = new Request();
        $request->data = array("merchant_id"=>$this->ids['merchant_id'],"note"=>$note,"merchant_menu_type"=>'Pickup',"participant_emails"=>$emails);
        $request->url = "app2/apiv2/grouporders";
        $request->method = "POST";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $resource = $group_order_controller->processV2Request();
        $this->assertNull($resource->error);
        $group_order_token = $resource->group_order_token;
        $this->assertNotNull($group_order_token);

        // now check status anonymously
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/grouporders/$group_order_token?log_level=5";
        $result = $this->makeRequestWithDefaultHeaders($url, $userpassword);
        $this->assertEquals('200', $this->info['http_code']);
        $request_result_as_array = json_decode($result,true);
        $data = $request_result_as_array['data'];
        $this->assertEquals('active',$data['status'],"should have found an active group order");

    }

    function testGetSkinAnonymouslyWithLoyaltyPhone()
    {
        $loyalty_card_management_link = "https://sumdumlink.com/card_management/12345678";
        $phone_number = "(123) 456-7890";
        $skin_resource = Resource::find(new SkinAdapter($m),"13",$options);
        $skin_resource->loyalty_support_phone_number = $phone_number;
        $skin_resource->loyalty_card_management_link = $loyalty_card_management_link;
        $skin_resource->save();
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/skins/com.splickit.pitapit?log_level=5";
        $result = $this->makeRequestWithDefaultHeaders($url, $userpassword);
        $this->assertEquals('200', $this->info['http_code']);
        $request_result_as_array = json_decode($result,true);
        $data = $request_result_as_array['data'];
        $this->assertEquals($phone_number,$data['loyalty_support_phone_number'],"should have found the loyalty phone number in the data");
        $this->assertEquals($loyalty_card_management_link,$data['loyalty_card_management_link'],"should have found the loyalty_card_management_link in the data");
    }

    function testGoodResponseFromBadAuthRequestAPIv2()
    {
        $user_resource = createNewUser();
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/".$user_resource->uuid;
        $response = $this->makeRequestWithDefaultHeaders($url, $data);
        $this->assertEquals(401, $this->info['http_code'],"Should have gotten an unauthorized response");
    }


//    function testGoodResponseFromBadAuthRequestV1()
//    {
//
//        $url = "http://127.0.0.1:".$this->api_port."/app2/phone/getmerchantmetadata/104961";
//        $response = $this->makeRequestWithDefaultHeaders($url, $data);
//        $this->assertEquals(401, $this->info['http_code'],"Should have gotten an unauthorized response");
//    }

	function testGetMenuStatus()
	{
		$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/menu/".$this->ids['menu_id']."/menustatus?log_level=5";
		$result = $this->makeRequestWithDefaultHeaders($url, $userpassword);
		$this->assertEquals('200', $this->info['http_code']);
		$request_result_as_array = json_decode($result,true);
		$this->assertCount(4,$request_result_as_array);
		$this->assertNull($request_result_as_array['error']);
		$menu_status = $request_result_as_array['data'];
		$this->assertEquals($this->ids['menu_status_key'],$menu_status['menu_key']);
	}

	function testMasterpieceDeliAliasForMasterpiece()
	{
		getOrCreateSkinAndBrandIfNecessary('masterpiece','masterpiece',800,801);
		$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users";
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_USERPWD, 'store_tester@tullys.com:test');

		$external_id = "com.splickit.masterpiecedeli";

		$headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:APIDispatchTest","NO_CC_CALL:true");
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		$result = curl_exec($curl);
		$result_array = json_decode($result,true);
		$this->assertEquals(200,$result_array['http_code']);
		$this->info = curl_getinfo($curl);
		$this->assertEquals(200,$this->info['http_code']);
		$this->assertNotNull($result_array['data']['splickit_authentication_token']);
		curl_close($curl);
	}

	function testLoginStoreTester()
    {
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users";
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_USERPWD, 'store_tester@tullys.com:test');

        $external_id = "com.splickit.moes";

        $headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:APIDispatchTest","NO_CC_CALL:true");
//        if ($authentication_token = $data['splickit_authentication_token']) {
//            $headers[] = "splickit_authentication_token:$authentication_token";
//        }
        /*$data['email'] = 'store_tester@dummy.com';
        $json = json_encode($data);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS,$json);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($json);
        */
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($curl);
        $result_array = json_decode($result,true);
        $this->assertEquals(200,$result_array['http_code']);
        $this->info = curl_getinfo($curl);
        $this->assertEquals(200,$this->info['http_code']);
        $this->assertNotNull($result_array['data']['splickit_authentication_token']);
        curl_close($curl);
    }

    function testGetVioWriteCredentials()
    {
        $user_resource = createNewUser();
        $authentication_token_resource = createUserAuthenticationToken($user_resource->user_id);
        $splickit_authentication_token = $authentication_token_resource->token;
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/credit_card/getviowritecredentials";
        $response = $this->makeRequestWithDefaultHeaders($url, "splickit_authentication_token:$splickit_authentication_token");
        $this->assertEquals(200, $this->info['http_code']);
        $response_array = json_decode($response,true);
        $this->assertEquals(VioPaymentService::getVioWriteCredentials(),$response_array['data']['vio_write_credentials']);
    }

    function testGetCorrectHttpCodeFromBadLogin()
    {
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users";
        $response = $this->makeRequestWithDefaultHeaders($url, "someemail:sumpassword");
        $this->assertEquals(401, $this->info['http_code'],"Should have gotten a failed authentication status code");

    }

	function testAuthenticateWithToken()
    {
    	$user_resource = createNewUser();
    	$authentication_token_resource = createUserAuthenticationToken($user_resource->user_id);
		$splickit_authentication_token = $authentication_token_resource->token;
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users";
		$response = $this->makeRequestWithDefaultHeaders($url, "splickit_authentication_token:$splickit_authentication_token");
		$this->assertEquals(200, $this->info['http_code']);
    }
    
    function testGetLoyaltyHistoryNoHistory()
    {
    	setContext("com.splickit.pitapit");
    	$user_resource = createNewUser();
    	$uuid = $user_resource->uuid;
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/$uuid/loyalty_history?log_level=5";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user_resource->email.":welcome",'GET',$data);
    	$this->assertEquals(200, $this->info['http_code']);
		$request_result_as_array = json_decode($response,true);
		$this->assertTrue(isset($request_result_as_array['data']));
		$this->assertTrue(is_array($request_result_as_array['data']));
		$this->assertTrue(count($request_result_as_array['data']) == 0,"should have an empty array for history but found ".count($request_result_as_array['data'])." records");
    }

    function testLinkPitaPitLoyaltyWithNoPin()
    {
        setContext("com.splickit.pitapit");
        Resource::findOrCreateIfNotExistsByData(new BrandLoyaltyRulesAdapter($m),array("brand_id"=>getBrandIdFromCurrentContext(),"loyalty_type"=>'remote'));
        $user_resource = createNewUser();
        $uuid = $user_resource->uuid;
        $data['loyalty_number'] = '1234567890';
        $data['pin'] = '';
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/$uuid?log_level=5";
        $response = $this->makeRequestWithDefaultHeaders($url, $user_resource->email.":welcome",'POST',$data);
        $this->assertEquals(401,$this->info['http_code']);
        $response_array = json_decode($response,true);
        $this->assertEquals("Sorry you must enter both loyalty number and pin in order to link to an existing loyalty account.",$response_array['error']['error']);
    }
        
    function testResponseFormatForError()
    {
    	removeContext();
    	$user = logTestUserIn($this->ids['user_id']);
    	// without a context we should get no merchants
    	$request = new Request();
    	$request->url = "apiv2/merchants?log_level=5";
    	$request->method = 'GET';
    	$merchant_controller = new MerchantController($mt, $user, $request, 5);
    	$resource = $merchant_controller->processV2Request();
    	
    	$response = getV2ResponseWithJsonFromResource($resource, $headers);
    	$body = $response->body;
    	$body_as_array = json_decode($body,true);
    	$this->assertCount(3, $body_as_array);
    	$this->assertNotNull($body_as_array['stamp']);
    	$this->assertNotNull($body_as_array['http_code']);
    	$this->assertNotNull($body_as_array['error']);
    	$error = $body_as_array['error'];
    	$this->assertCount(3, $error);
    	$this->assertEquals("Sorry. We could not find any merchants. If you feel you have reached this in error, please try again or contact support.", $error['error']);
    }
    
    function testResponseFormat()
    {
    	$user_resource = createNewUser();
    	$user = logTestUserResourceIn($user_resource);
    	
    	//now set context correctly and try again
    	setContext("com.splickit.vtwoapi");
    	$request = new Request();
    	$request->url = "apiv2/merchants?log_level=5";
    	$request->method = 'GET';
    	$merchant_controller = new MerchantController($mt, $user, $request, 5);
    	$resource = $merchant_controller->processV2Request();
    	
    	$response = getV2ResponseWithJsonFromResource($resource, $headers);
    	$body = $response->body;
    	$body_as_array = json_decode($body,true);
    	$this->assertCount(4, $body_as_array);
    	$this->assertNotNull($body_as_array['stamp']);
    	$this->assertNotNull($body_as_array['data']);
    	$data = $body_as_array['data'];
    	$this->assertCount(2, $data);
    	$this->assertNotNull($data['merchants']);
    	$this->assertTrue(count($data['merchants']) > 0);
    	$this->assertNotNull($data['promos']);
    }
    
    function testForgotPasswordBadEmail()
    {
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/forgotpassword";
    	$response = $this->makeRequestWithDefaultHeaders($url, "admin:welcome",'Post',array("email"=>'sumdumemail@email.com'));
    	$request_result_as_array = json_decode($response,true);
		$this->assertEquals("Sorry, that email is not registered with us. Please check your entry.", $request_result_as_array['error']['error']);
		$this->assertEquals(404, $this->info['http_code']);
    }
    
    function testForgotPassword()
    {
    	$user_resource = createNewUser();
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/forgotpassword";
    	$response = $this->makeRequestWithDefaultHeaders($url, "admin:welcome",'Post',array("email"=>$user_resource->email));
    	$this->assertEquals(200, $this->info['http_code']);
		$request_result_as_array = json_decode($response,true);
		$this->assertEquals("We have processed your request. Please check your email for reset instructions.", $request_result_as_array['message']);
		return $user_resource->user_id;
    }
    
    /**
     * @depends testForgotPassword
     */
    function testResetPassword($user_id)
    {
    	$upr_record = UserPasswordResetAdapter::staticGetRecord(array("user_id"=>$user_id), 'UserPasswordResetAdapter');
    	$token = $upr_record['token'];
    	$this->assertNotNull($token,"SHould have gotten a token from the table");

    	$ts = time();
    	$tstring = (String) time();
		$new_password = 'xxxx'.substr($tstring,-4);    	
    	
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/resetpassword";
    	$response = $this->makeRequestWithDefaultHeaders($url, "admin:welcome",'Post',array("token"=>$token,"password"=>"$new_password"));
    	$this->assertEquals(200, $this->info['http_code']);
    	$response_array = json_decode($response,true);
    	$this->assertEquals("Your password has been reset",$response_array['message']);

    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/resetpassword";
    	$response = $this->makeRequestWithDefaultHeaders($url, "admin:welcome",'Post',array("token"=>$token,"password"=>"$new_password"));
    	$this->assertEquals(404, $this->info['http_code']);
    	
    }
    
    function testGetUser()
    {
    	$user_id = $this->ids['user_id'];
    	$user = UserAdapter::staticGetRecordByPrimaryKey($user_id, "User");	
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users?log_level=5";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome');
    	$this->assertEquals(200, $this->info['http_code']);
		$request_result_as_array = json_decode($response,true);
		$user_session = $request_result_as_array['data'];
		$this->assertNotNull($user_session['user_id']);
		$this->assertNotNull($user_session['splickit_authentication_token'],'should have found an authentication token');
    }

    function testCreateNewUserBadName()
    {
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users?log_level=5";
        $new_user_data = createNewUserDataFields();
        $new_user_data['last_name'] = "ghjk678bn";
        $response = $this->makeRequestWithDefaultHeaders($url, "admin:welcome","POST",$new_user_data);
        $this->assertEquals('422', $this->info['http_code']);
        $new_user_response = json_decode($response,true);
        $this->assertNotNull($new_user_response['error'],"should have gotten an error");
    }

    function testCreateUserApostrophy()
    {
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users?log_level=5";
        $new_user_data = createNewUserDataFields();
        $new_user_data['last_name'] = "O'Connell";
        $response = $this->makeRequestWithDefaultHeaders($url, "admin:welcome","POST",$new_user_data);
        $this->assertEquals('200', $this->info['http_code']);
        $new_user_response = json_decode($response,true);
        $this->assertNull($new_user_response['error'],"should NOT have gotten an error");
        $user = $new_user_response['data'];
        $this->assertNotNull($user['user_id']);
    }

    function testCreateNewUserAlreadyExistsSoSendBackToken()
    {
        $user_resource = createNewUser();
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users?log_level=5";
        $new_user_data = createNewUserDataFields();
        $new_user_data['email'] = $user_resource->email;
        $response = $this->makeRequestWithDefaultHeaders($url, "admin:welcome","POST",$new_user_data);
        $this->assertEquals('200', $this->info['http_code']);
        $new_user_response = json_decode($response,true);
        $this->assertNull($new_user_response['error'],"should NOT have gotten an error");
        $this->assertEquals("This account already exists, however, your password matched, so we logged in you anyway :)",$new_user_response['message']);
        $user = $new_user_response['data'];
        $this->assertNotNull($user['user_id']);
        $this->assertNotNull($user['splickit_authentication_token'],'should have found an authentication token');
        $this->assertNotNull($user['splickit_authentication_token_expires_at'],'should have found an authentication token expires at');
    }
    
    function testUpdateUser()
    {
    	$user = $this->ids['user'];
    	$data['first_name'] = "john";
        $request_json = json_encode($data);
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users?log_level=5";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome',"POST",$data);
		$this->assertEquals('200', $this->info['http_code']);
		$user_response = json_decode($response,true);
		$this->assertNull($user_response['error'],"should NOT have gotten an error");
		$this->assertEquals($user['uuid'], $user_response['data']['user_id']);  	
    	$this->assertEquals('john',$user_response['data']['first_name']);
        $request_data['request_body'] = $request_json;
        $request_data['response_payload'] = $response;
        $request_data['stamp'] = $user_response['stamp'];
        return $request_data;
    }

    /**
     * @depends testUpdateUser
     */
    function testSaveRequestAndResponseInDB($request_data)
    {
        $rta = new RequestTimesAdapter($m);
        $rt_record = $rta->getRecord(array("stamp"=>$request_data['stamp']));
        $this->assertEquals($request_data['request_body'],$rt_record['request_body'],"should have saved the request body in the db");
        $this->assertEquals($request_data['response_payload'],$rt_record['response_payload'],"Should have had the response payload in the db");
    }
    
    function testSetUserDeliveryAddress()
    {
    	$user = $this->ids['user'];
    	$user_id = $user['user_id'];
    	$user_uuid = $user['uuid'];
    	$json = '{"name":"home","address1":"11 Riverside Drive","address2":"","city":"new york","state":"ny","zip":"12345","phone_no":"1234567890","lat":40.796202,"lng":-73.936635}';
    	$data = json_decode($json,true);
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/$user_uuid/userdeliverylocation?log_level=5";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome',"POST",$data);
		$this->assertEquals('200', $this->info['http_code']);
		$user_response = json_decode($response,true);
		$this->assertNull($user_response['error'],"should NOT have gotten an error");
		$user_delivery_info = $user_response['data'];
		$this->assertNotNull($user_delivery_info['user_addr_id']);
		$this->assertEquals('new york', $user_delivery_info['city']);
    	return $user_delivery_info['user_addr_id'];
    }
    
    /**
     * @depends testSetUserDeliveryAddress
     */
    function testDeleteUserAddress($user_address_id)
    {
    	$udl_record = UserDeliveryLocationAdapter::staticGetRecordByPrimaryKey($user_address_id, 'UserDeliveryLocation');
    	$user = UserAdapter::staticGetRecordByPrimaryKey($udl_record['user_id'], 'User');
    	$user_id = $user['user_id'];
    	$user_uuid = $user['uuid'];
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/$user_uuid/userdeliverylocation/$user_address_id?log_level=5";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome',"DELETE",$data);
		$this->assertEquals('200', $this->info['http_code']);
		$user_response = json_decode($response,true);
		$this->assertNull($user_response['error'],"should NOT have gotten an error");
		$user_delivery_info = $user_response['data'];
		$this->assertEquals('success', $user_delivery_info['result']);
		$this->assertNull(UserDeliveryLocationAdapter::staticGetRecordByPrimaryKey($user_address_id, 'UserDeliveryLocation'));
		
    }
    
    /***************************/
    
    function testPlaceDeliveryOrder()
    {
    	$user = $this->ids['user'];
    	$user_id = $user['user_id'];
    	$user_uuid = $user['uuid'];
    	$json = '{"name":"work","business_name":"bobs piano factory","address1":"4670 N Broadway St","address2":"","city":"boulder","state":"co","zip":"80304","phone_no":"1234567890","lat":40.059190,"lng":-105.282113}';
    	$data = json_decode($json,true);
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/$user_uuid/userdeliverylocation?log_level=5";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome',"POST",$data);
		$this->assertEquals('200', $this->info['http_code']);
		$user_response = json_decode($response,true);
		$this->assertNull($user_response['error'],"should NOT have gotten an error");
		$user_address_id = $user_response['data']['user_addr_id'];
		
		$merchant_resource = createNewTestMerchant();
    	$merchant_resource->delivery = 'Y';
    	$merchant_resource->save();
        $map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_resource->merchant_id,"message_format"=>'FUA',"delivery_addr"=>"1234567890","message_type"=>"X"));
    	$merchant_id = $merchant_resource->merchant_id;
    	MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);
    	
    	MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'delivery');
    	MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $this->ids['menu_id'], 'pickup');
    	
    	$data = array("merchant_id"=>$merchant_resource->merchant_id);
    	
    	// set merchant delivery info
    	$mdia = new MerchantDeliveryInfoAdapter($mimetypes);
    	$mdia_resource = $mdia->getExactResourceFromData($data);	
    	$mdia_resource->minimum_order = 1.00;
    	$mdia_resource->delivery_cost = 1.00;
    	$mdia_resource->delivery_increment = 15;
    	$mdia_resource->max_days_out = 3;
    	$mdia_resource->minimum_delivery_time = 45;
    	$mdia_resource->save();
    	
    	$mdpd = new MerchantDeliveryPriceDistanceAdapter($mimetypes);
    	$mdpd_resource = $mdpd->getExactResourceFromData($data);
    	$this->assertNotNull($mdpd_resource,"should have found a merchant delivery price distance resource");
    	$mdpd_resource->distance_up_to = 10.0;
    	$mdpd_resource->price = 8.88;
    	$mdpd_resource->save();
		
    	// check if user location is in range and how much 
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/merchants/$merchant_id/isindeliveryarea/$user_address_id?log_level=5";
    	$delivery_range_response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome',"GET",null);
    	$delivery_range_response_hash = json_decode($delivery_range_response,true);
		$this->assertTrue(isset($delivery_range_response_hash['data']['is_in_delivery_range']),"should have found the 'is in delivery range' field");
    	$this->assertTrue($delivery_range_response_hash['data']['is_in_delivery_range']," the is in delivery range should be true");
    	$this->assertEquals($mdpd_resource->price, $delivery_range_response_hash['data']['price']);
    	
    	$order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
    	$order_data['user_id'] = $user['uuid'];
    	$order_data['user_addr_id'] = $user_address_id;
 
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/cart";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','POST',$order_data);
		$this->assertEquals('200', $this->info['http_code']);
		$request_result_as_array = json_decode($response,true);
		$cart_ucid = $request_result_as_array['data']['ucid'];
		
		$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/cart/$cart_ucid/checkout";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','GET');
		$checkout_result_as_array = json_decode($response,true);
		$this->assertEquals('200', $this->info['http_code'],"an error was thrown: ".$checkout_result_as_array['error']['error']);
		$checkout_data = $checkout_result_as_array['data'];
		$this->assertNull($checkout_data['error']);
		$this->assertEquals(8.88, $checkout_data['delivery_amt']);

		$order_data = array();
    	$order_data['note'] = "this is the note on the order";

    	$order_data['tip'] = 0.00;
    	$payment_array = $checkout_data['accepted_payment_types'];
    	//set to cash
    	//$order_data['merchant_payment_type_map_id'] = $this->ids['merchant_payment_type_map_id_for_cash'];
    	$order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
    	
    	$lead_times_array = $checkout_data['lead_times_array'];
    	$order_data['requested_time'] = $lead_times_array[0];

    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/orders/$cart_ucid";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','POST',$order_data);
    	$request_result_as_array = json_decode($response,true);
		$this->assertEquals('200', $this->info['http_code'],"an error was thrown: ".$request_result_as_array['error']['error']);
		$this->assertNull($request_result_as_array['error'],"an error was thrown: ".$request_result_as_array['error']['error']);
    	
		$result_order_data = $request_result_as_array['data'];
		$order_id = $result_order_data['order_id'];
    	$this->assertTrue($order_id > 1000,"should have created a valid order id");

        $this->assertTrue(isset($result_order_data['user_delivery_address']),"there should be a user delivery address field on the order resource");
        $this->assertEquals("4670 N Broadway St",$result_order_data['user_delivery_address']['address1']);

        $order_resource = CompleteOrder::getBaseOrderDataAsResource($order_id, $mimetypes);
    	$this->assertEquals('D', $order_resource->order_type,"order typwe should be delivery");
    	$this->assertEquals(8.88,$order_resource->delivery_amt,"order shoudl have a delivery amoutn");

        $fax_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'FUA');
        $fax_controller = new FaxController($m, $u, $r, 5);
        $ready_to_send_message_resource = $fax_controller->prepMessageForSending($fax_message_resource);
        $this->assertContains('bobs piano factory',$ready_to_send_message_resource->message_text);


    }
    
     /***************************/

    function testUploadCreditCard()
    {
    	// get new user
    	$zip = rand(11111,19999);
    	$data['uuid'] = "test-$zip-0316-123-zd0c";
    	$user_resource = createNewUser($data);
    	$this->assertEquals('1000000001', $user_resource->flags);
    	$user_id = $user_resource->user_id;
    	$user = logTestUserIn($user_resource->user_id);

    	$json_encoded_data = "{\"cc_exp_date\":\"03/2021\",\"cc_number\":\"4111111111111111\",\"cvv\":\"123\",\"zip\":\"$zip\"}";
    	$data = json_decode($json_encoded_data,true);
    	
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users?log_level=5";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome',"POST",$data);
		$this->assertEquals('200', $this->info['http_code']);
		$user_response = json_decode($response,true);
		$this->assertNull($user_response['error'],"should NOT have gotten an error");
		$this->assertEquals($user['uuid'], $user_response['data']['user_id']);  	
    	
		$user_record = UserAdapter::staticGetRecordByPrimaryKey($user_id, 'User');
		$this->assertEquals('1C21000001', $user_record['flags']);
    }
    
    function testGetMerchantList()
    {
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/merchants?log_level=5";
		$result = $this->makeRequestWithDefaultHeaders($url, $userpassword);
		$this->assertEquals('200', $this->info['http_code']);
		$request_result_as_array = json_decode($result,true);
		$merchants = $request_result_as_array['data']['merchants'];
		$this->assertTrue(count($merchants) > 0);
		$this->assertNull($request_result_as_array['error']);
    }
    
    function testGetMerchant()
    {
		$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/merchants/".$this->ids['merchant_id']."?log_level=5";
		$result = $this->makeRequestWithDefaultHeaders($url, $userpassword);
		$this->assertEquals('200', $this->info['http_code']);
		$request_result_as_array = json_decode($result,true);
		$this->assertCount(4,$request_result_as_array);
		$this->assertNull($request_result_as_array['error']);
		$merchant = $request_result_as_array['data'];
    	$this->assertNotNull($merchant['merchant_id']);
    	$this->assertNotNull($merchant['menu']);
    	$this->assertNotNull($merchant['todays_hours']);
    	$this->assertNotNull($merchant['payment_types']);
    }
    
    function testLoginWithToken()
    {
    	$user_resource = createNewUser();
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user_resource->email.':welcome','GET',$data);
    	$result_as_array = json_decode($response,true);
    	$this->assertNull($result_as_array['error']);
    	$this->assertEquals($user_resource->uuid, $result_as_array['data']['user_id']);
    	$this->assertNotNull($result_as_array['data']['splickit_authentication_token']);
    	$data = $result_as_array['data'];
    	$authentication_token = $data['splickit_authentication_token'];
    	
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/users/".$user_resource->uuid;
    	$data['splickit_authentication_token'] = $authentication_token;
    	$response2 = $this->makeRequestWithDefaultHeaders($url, '','GET',$data);
    	$result_as_array2 = json_decode($response2,true);
    	$this->assertNull($result_as_array2['error']);
    	$data2 = $result_as_array2['data'];
    	
    	//need to remove the expires at from the regular auth since it wont be in the token @author radamnyc
		unset($data['splickit_authentication_token_expires_at']);
    	$this->assertEquals($data, $data2,"the user sessions should have been the same. with normal auth and with token auth");
    }

	function testCreateGroupOrder()
	{
        $merchant_resource = SplickitController::getResourceFromId($this->ids['merchant_id'], 'Merchant');
        $merchant_resource->group_ordering_on = 0;
        $merchant_resource->save();

        $user_id = $this->ids['user_id'];
    	$user = UserAdapter::staticGetRecordByPrimaryKey($user_id, "User");	
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/grouporders?log_level=5";
    	$data['merchant_id'] = $this->ids['merchant_id'];
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','POST',$data);
    	$this->assertEquals(422, $this->info['http_code']);
    	$response_array = json_decode($response,true);
    	$this->assertEquals("This merchant does not participate in group ordering.",$response_array['error']['error']);
    	$merchant_resource->group_ordering_on = 1;
    	$merchant_resource->save();
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','POST',$data);
    	$this->assertEquals(200, $this->info['http_code']);    	
		$request_result_as_array = json_decode($response,true);
		$data = $request_result_as_array['data'];
		$this->assertNotNull($data['group_order_token']);
		return $data['group_order_token'];		
	}
    
	/**
	 * @depends testCreateGroupOrder
	 */
	function testAddToGroupOrder($group_order_token)
	{
		$user_resource = createNewUserWithCC();
    	$user = logTestUserResourceIn($user_resource);
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', $note);
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/grouporders/$group_order_token?log_level=5";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','POST',$order_data);
    	$this->assertEquals(200, $this->info['http_code']);
		$request_result_as_array = json_decode($response,true);
		$data = $request_result_as_array['data'];
		$this->assertTrue($data['group_order_detail_id'] > 1000,"Should have found a valid group order detail id");
		$this->assertCount(1, $data,"response should only contain the group order detail id");
		return $group_order_token;
	}
	
	/**
	 * @depends testAddToGroupOrder
	 */
	function testAddToGroupOrderFromCart($group_order_token)
	{
    	$user_resource = createNewUserWithCC();
    	$user = logTestUserResourceIn($user_resource);
    	$order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id']);
 
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/cart";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user_resource->email.':welcome','POST',$order_data);
		$this->assertEquals('200', $this->info['http_code']);
		$request_result_as_array = json_decode($response,true);
    	
		$cart_data = $request_result_as_array['data'];
		$this->assertNotNull($cart_data['ucid'],"should have gotten cart ucid back");
		$cart_ucid = $cart_data['ucid'];
		
		$data['cart_ucid'] = $cart_ucid;
		$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/grouporders/$group_order_token?log_level=5";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','POST',$data);
    	$this->assertEquals(200, $this->info['http_code']);
		$request_result_as_array = json_decode($response,true);
		$response_data = $request_result_as_array['data'];
		
		$this->assertTrue($response_data['group_order_detail_id'] > 1000,"Should have found a valid group order detail id");
		$this->assertCount(1, $response_data,"response should only contain the group order detail id");
		return $group_order_token;
	}
	
	/**
	 * @depends testAddToGroupOrderFromCart
	 */
	function testGetGroupOrderForSubmit($group_order_token)
	{
		$group_order_record = GroupOrderAdapter::staticGetRecord(array("group_order_token"=>$group_order_token), 'GroupOrderAdapter');
        $user_id = $group_order_record['admin_user_id'];
		$user = UserAdapter::staticGetRecordByPrimaryKey($user_id, 'UserAdapter');
		$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/grouporders/$group_order_token?log_level=5";
    	$data['merchant_id'] = $this->ids['merchant_id'];
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome');
    	$this->assertEquals(200, $this->info['http_code']);
		$request_result_as_array = json_decode($response,true);
		$data = $request_result_as_array['data'];
		$this->assertNotNull($data['group_order_token'],"should have been a group order toekn on the response");
		$this->assertNotNull($data['order_summary']);
        $this->assertCount(2, $data['order_summary']['cart_items'],"there should be two items in the group order");
		// add admin user_id back in since its not returned in the api call
		$data['admin_user_id'] = $user_id;
		return $data;		
	}
	
	/**
	 * @depends testGetGroupOrderForSubmit
	 */
	function testSubmitGroupOrder($data)
	{
        $user_id = $data['admin_user_id'];
        $user = UserAdapter::staticGetRecordByPrimaryKey($user_id, 'UserAdapter');
        $group_order_token = $data['group_order_token'];
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/cart/$group_order_token/checkout";
        $checkout_response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','GET');
        $checkout_response_as_array = json_decode($checkout_response,true);
        $checkout_data = $checkout_response_as_array['data'];

        $order_data['merchant_id'] = $data['merchant_id'];
        $order_data['note'] = "some dumb note";
        $order_data['user_id'] = $user['user_id'];
        $order_data['cart_ucid'] = $group_order_token;
        $order_data['group_order_token'] = $data['group_order_token'];
        $order_data['tip'] = 0.00;
        $payment_array = $checkout_data['accepted_payment_types'];
        $order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
        $lead_times_array = $checkout_data['lead_times_array'];
        $order_data['actual_pickup_time'] = $lead_times_array[0];

        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/orders/$group_order_token";
        $response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','POST',$order_data);
        $order_ts = time();
        $request_result_as_array = json_decode($response,true);
        $this->assertEquals('200', $this->info['http_code'],"an error was thrown: ".$request_result_as_array['error']['error']);
        $this->assertNull($request_result_as_array['error'],"an error was thrown: ".$request_result_as_array['error']['error']);

		$order_data = $request_result_as_array['data'];
		$order_id = $order_data['order_id'];
    	$this->assertTrue($order_id > 1000,"should have created a valid order id");
    	
    	$group_order_id = $data['group_order_id'];
    	$group_order_record = GroupOrderAdapter::staticGetRecordByPrimaryKey($group_order_id, 'GroupOrderAdapter');
    	$sent_ts = strtotime($group_order_record['sent_ts']);
    	$this->assertTrue($sent_ts <= $order_ts,"sent ts should be now or close to it.");

	}
	
	function testAddToCart()
    {
    	$user_resource = createNewUserWithCC();
    	$user = logTestUserResourceIn($user_resource);
    	$brand_resource = Resource::find(new BrandAdapter(),getBrandIdFromCurrentContext());
    	$brand_resource->allows_tipping = 'N';
    	$brand_resource->save();
    	$order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id']);
 
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/cart";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user_resource->email.':welcome','POST',$order_data);
		$this->assertEquals('200', $this->info['http_code']);
		$request_result_as_array = json_decode($response,true);
    	
		$cart_data = $request_result_as_array['data'];
		$cart_resource = Resource::dummyfactory($cart_data);
    	$this->assertNotNull($cart_resource,"should have gotten a cart resource back");
    	$this->assertNotNull($cart_resource->ucid,"cart should have a unique identifier");	
		$order_summary = $cart_resource->order_summary['cart_items'];
		$this->assertCount(1, $order_summary);
		$item = $order_summary[0];
		$this->assertNotNull($item['item_name'],"should have found an item name");
		$this->assertNotNull($item['item_price'],"shoudl have found an item price");
		$this->assertNotNull($item['item_quantity'],"should have found an item quantity");
		$this->assertNotNull($item['item_description'],"should have found the list of mods");
		$this->assertNotNull($item['order_detail_id'],'should have found an order detail id on the cart summary hash');
		$receipt_items_array = $cart_resource->order_summary['receipt_items'];
		$receipt_items = createHashOfRecieptItemsByTitle($receipt_items_array);
		$sub_total = $receipt_items['Subtotal'];
		$this->assertEquals('$2.00', $sub_total);
		$tax = $receipt_items['Tax'];
		$this->assertEquals('$0.20',$tax);	
		
		$full_cart_resource = SplickitController::getResourceFromId($cart_resource->ucid, 'Carts');
		$order_record = OrderAdapter::staticGetRecordByPrimaryKey($full_cart_resource->order_id, 'Order');
		$status = $order_record['status'];
		$this->assertEquals('Y', $status,'Order status should be set to Y so cart does not expire');
		return $cart_resource->ucid;
    }
    
    /**
     * @depends testAddToCart
     */
    function testAddToExistingCart($cart_ucid)
    {
    	$cart_resource = SplickitController::getResourceFromId($cart_ucid, 'Carts');
    	$user = logTestUserIn($cart_resource->user_id);
    	$order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id']);
    	$order_data['items'][0]['note'] = 'skip hours';
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/cart/$cart_ucid";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','POST',$order_data);
		$this->assertEquals('200', $this->info['http_code']);
		$request_result_as_array = json_decode($response,true);
		$cart = $request_result_as_array['data'];
		$new_cart_resource = Resource::dummyfactory($cart);
		$order_summary = $new_cart_resource->order_summary['cart_items'];
		$this->assertCount(2, $order_summary,"order summary should now have two items");
		$item = $order_summary[1];
		$this->assertNotNull($item['item_name'],"should have found an item name");
		$this->assertNotNull($item['item_price'],"shoudl have found an item price");
		$this->assertNotNull($item['item_quantity'],"should have found an item quantity");
		$this->assertNotNull($item['item_description'],"should have found the list of mods");
		$receipt_items_array = $new_cart_resource->order_summary['receipt_items'];
		$receipt_items = createHashOfRecieptItemsByTitle($receipt_items_array);
		$sub_total = $receipt_items['Subtotal'];
		$this->assertEquals('$4.00', $sub_total);
		$tax = $receipt_items['Tax'];
		$this->assertEquals('$0.40',$tax);	
		
		$full_cart_resource = SplickitController::getResourceFromId($new_cart_resource->ucid, 'Carts');
		$order_record = OrderAdapter::staticGetRecordByPrimaryKey($full_cart_resource->order_id, 'Order');
		$status = $order_record['status'];
		$this->assertEquals('Y', $status,'Order status should be set to Y so cart does not expire');
		
		return $full_cart_resource;
    }

    /**
     * @depends testAddToExistingCart
     */
    function testAddBadPromoCode($cart_resource)
    {
        $user = UserAdapter::staticGetRecordByPrimaryKey($cart_resource->user_id,'User');
        $cart_ucid = $cart_resource->ucid;
        $url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/cart/$cart_ucid/checkout?promo_code=badpromocode";
        $response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','GET');
        $request_result_as_array = json_decode($response,true);
        $this->assertEquals('422', $this->info['http_code'],"Should have had a bad http code");
        $this->assertNotNull($request_result_as_array['error']['error_type'],"should have found the error type field");
        $this->assertEquals('promo',$request_result_as_array['error']['error_type']);
        $this->assertEquals('Sorry!  The promo code you entered, badpromocode, is not valid.',$request_result_as_array['error']['error']);
    }
    
    /**
     * @depends testAddToExistingCart
     */
    function testGetCartCheckoutData($cart_resource)
    {

    	$user = UserAdapter::staticGetRecordByPrimaryKey($cart_resource->user_id,'User');
    	$cart_ucid = $cart_resource->ucid;
    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/cart/$cart_ucid/checkout";

        $external_id = $this->getExternalId();
        $headers = array("X_SPLICKIT_CLIENT_ID"=>"$external_id","X_SPLICKIT_CLIENT_DEVICE"=>"web-unit_testing","HTTP_X_SPLICKIT_CLIENT_VERSION"=>"10.5.0","X_SPLICKIT_CLIENT"=>"APIDispatchTest","NO_CC_CALL"=>"true");
        $header_array = array();
        foreach ($headers as $key=>$value) {
            $header_array[] = $key.":".$value;
        }
        $response = $this->makeRequest($url,$user['email'].':welcome','GET',$header_array);
        $response = $this->makeRequest($url,$user['email'].':welcome','GET',$header_array);
		$request_result_as_array = json_decode($response,true);
		$this->assertEquals('200', $this->info['http_code'],"an error was thrown: ".$request_result_as_array['error']['error']);
		$checkout_data = $request_result_as_array['data'];
		$this->assertNull($checkout_data['error']);
		$this->assertNotNull($checkout_data['lead_times_array']."Should have found a lead times array");
        $this->assertNull($checkout_data['tip_array'],"Should NOT have found the tip array");
		$this->assertEquals(4.00, $checkout_data['order_amt']);
		$this->assertEquals(0.40, $checkout_data['total_tax_amt']);
		$this->assertEquals($cart_ucid,$checkout_data['cart_ucid']);

		$brand_resource = Resource::find(new BrandAdapter(),$this->ids['brand_resource']->brand_id);
		$brand_resource->allows_tipping = 'Y';
		$brand_resource->save();

        $response = $this->makeRequest($url,$user['email'].':welcome','GET',$header_array);
        $request_result_as_array = json_decode($response,true);
        $checkout_data = $request_result_as_array['data'];
        $this->assertNotNull($checkout_data['tip_array'],"Should have found the tip array");
        $brand_resource->allows_tipping = 'N';
        $brand_resource->save();
		return $checkout_data;
    }
    
    /**
     * @depends testGetCartCheckoutData
     */
    function testSubmitCart($checkout_data)
    {
    	$cart_ucid = $checkout_data['cart_ucid'];
    	$cart_resource = SplickitController::getResourceFromId($cart_ucid, 'Carts');
    	$user = UserAdapter::staticGetRecordByPrimaryKey($cart_resource->user_id,'User');
    	
    	$order_data['merchant_id'] = $this->ids['merchant_id'];
    	$order_data['note'] = "skip hours";
    	$order_data['user_id'] = $user['user_id'];
    	$order_data['cart_ucid'] = $cart_ucid;
    	$order_data['tip'] = 0.00;
    	$payment_array = $checkout_data['accepted_payment_types'];
    	//set to cash
    	//$order_data['merchant_payment_type_map_id'] = $this->ids['merchant_payment_type_map_id_for_cash'];
    	$order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
    	
    	$lead_times_array = $checkout_data['lead_times_array'];
    	$order_data['actual_pickup_time'] = $lead_times_array[0];

    	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/orders";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','POST',$order_data);
    	$request_result_as_array = json_decode($response,true);
		$this->assertEquals('200', $this->info['http_code'],"an error was thrown: ".$request_result_as_array['error']['error']);
		$this->assertNull($request_result_as_array['error'],"an error was thrown: ".$request_result_as_array['error']['error']);
    	
		$order_data = $request_result_as_array['data'];
		$order_id = $order_data['order_id'];
    	$this->assertTrue($order_id > 1000,"should have created a valid order id");
    	
    	$cart_record = CartsAdapter::staticGetRecordByPrimaryKey($cart_resource->ucid, 'Carts');
    	$this->assertEquals('O', $cart_record['status']);
    	$this->assertEquals($checkout_data['total_tax_amt'], $order_data['total_tax_amt']);
    	$this->assertEquals($checkout_data['order_amt'], $order_data['order_amt']);
    }
    
    function testPlaceOrderWithoutCart()
    {
		$user_resource = createNewUserWithCC();
    	$user = logTestUserResourceIn($user_resource);
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', "some note");
    	$order_data['merchant_payment_type_map_id'] = $this->ids['merchant_payment_type_map_id_for_cash'];
    	$order_data['tip'] = '0.00';
    	$order_data['requested_time'] = getTomorrowTwelveNoonTimeStampDenver();
    	
     	$url = "http://127.0.0.1:".$this->api_port."/app2/apiv2/orders";
    	$response = $this->makeRequestWithDefaultHeaders($url, $user['email'].':welcome','POST',$order_data);
    	$request_result_as_array = json_decode($response,true);
		$this->assertEquals('200', $this->info['http_code'],"an error was thrown: ".$request_result_as_array['error']['error']);
		$this->assertNull($request_result_as_array['error'],"an error was thrown: ".$request_result_as_array['error']['error']);
    	
		$order_data = $request_result_as_array['data'];
		$order_id = $order_data['order_id'];
    	$this->assertTrue($order_id > 1000,"should have created a valid order id");
    	
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

    function makeRequestWithDefaultHeaders($url,$userpassword,$method = 'GET',$data = null)
    {
        $external_id = $this->getExternalId();
        $headers = array("X_SPLICKIT_CLIENT_ID"=>"$external_id","X_SPLICKIT_CLIENT_DEVICE"=>"unit_testing","HTTP_X_SPLICKIT_CLIENT_VERSION"=>"10.5.0","X_SPLICKIT_CLIENT"=>"APIDispatchTest","NO_CC_CALL"=>"true");
        if ($authentication_token = $data['splickit_authentication_token']) {
            $headers['splickit_authentication_token'] = $authentication_token;
            unset($data['splickit_authentication_token']);
        }
        if ($data['headers']) {
            $headers = array_merge($headers,$data['headers']);
            unset($data['headers']);
        }
        $header_array = array();
        foreach ($headers as $key=>$value) {
            $header_array[] = $key.":".$value;
        }
        return $this->makeRequest($url,$userpassword,$method,$header_array,$data);
    }
    
    function makeRequest($url,$userpassword,$method = 'GET', $headers = array(),$data = null)
    {
    	unset($this->info);
    	$method = strtoupper($method);
    	$curl = curl_init($url);
    	if ($userpassword) {
    		curl_setopt($curl, CURLOPT_USERPWD, $userpassword);
    	}
    	if ($method == 'POST') {
    		$json = json_encode($data);
    		curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS,$json);
			$headers[] = 'Content-Type: application/json';
			$headers[] = 'Content-Length: ' . strlen($json);
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
        setProperty('DO_NOT_CHECK_CACHE',"false",true);
        error_log("starting setup");
    	ini_set('max_execution_time',0);
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
    	//mysqli_query("BEGIN");
    	
    	$skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyaltyAndRoundUP("vtwoapi","vtwoapi",252, 101);
    	setContext('com.splickit.vtwoapi');
        $ids['skin_id'] = $skin_resource->skin_id;
        $brand_resource = Resource::find(new BrandAdapter($m),"101");
        $brand_resource->last_orders_displayed = 5;
        $brand_resource->save();
    	$ids['brand_resource'] = $brand_resource;
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;
		$menu_status_key = rand(11111111,99999999);
		$menu_resource = SplickitController::getResourceFromId($menu_id,'Menu');
		$menu_resource->last_menu_change = $menu_status_key;
		$menu_resource->save();
		$ids['menu_status_key'] = $menu_status_key;
    	
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 3);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getMimetypes());
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	
    	$merchant_resource = createNewTestMerchant($menu_id);
        $merchant_resource->group_ordering_on = 1;
        $merchant_resource->save();
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
    	$merchant_id_key = generateCode(10);
    	$merchant_id_number = generateCode(5);
    	$data['vio_selected_server'] = 'sage';
    	$data['vio_merchant_id'] = $merchant_resource->merchant_id;
    	$data['name'] = "Test Billing Entity";
    	$data['description'] = 'An entity to test with';
    	$data['merchant_id_key'] = $merchant_id_key;
    	$data['merchant_id_number'] = $merchant_id_number;
    	$data['identifier'] = $merchant_resource->alphanumeric_id;
    	$data['brand_id'] = $merchant_resource->brand_id;
    	
    	$card_gateway_controller = new CardGatewayController($mt, $u, $r);
    	$resource = $card_gateway_controller->createPaymentGateway($data);
    	$payment_type_map_resouce = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);
    	$ids['merchant_payment_type_map_id_for_cash'] = $payment_type_map_resouce->id;
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;
    	$ids['user'] = $user_resource->getDataFieldsReally();
    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;
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
    ApiDispatchTest::main();
}


?>