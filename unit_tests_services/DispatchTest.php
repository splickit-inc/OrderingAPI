<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/functions.inc';
require_once 'lib/utilities/unit_test_functions.inc';

class DispatchTest extends PHPUnit_Framework_TestCase
{
	var $stamp;

	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
	}
	
	function tearDown() {
  	$_SERVER['STAMP'] = $this->stamp;
  	setProperty('system_shutdown', 'false');
  }
    
    function testSystemShutdownMessage()
    {
    	setProperty('system_shutdown', 'true');
		$url = "http://localhost/app2/apiv2/merchants?log_level=5";
    	$curl = curl_init($url);
    	$headers = array("X_SPLICKIT_CLIENT_ID:com.splickit.order","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:DispatchTest","NO_CC_CALL:true");
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($curl);
		curl_close($curl);
		$result_array = json_decode($result,true);
		$error_message = $result_array['erorr'];
		$expected_message = "We're currently upgrading our software and the servers are off line for a few minutes. We'll be back up shortly. Sorry for the inconvenience.";
		$this->assertEquals($expected, $error_message);

    }
    
//	function testCreateUserWithToken()
//    {
//    	$user_data = createNewUserDataFields();
//    	$json_user_data = json_encode($user_data);
//    	$encrypted_user_data = SplickitCrypter::doEncryption($json_user_data,'mikesmarketer');
//    	$create_user_data['create_user_token'] = $encrypted_user_data;
//		$json_body = json_encode($create_user_data);
//
//    	$admin_user_resource = Resource::find(new UserAdapter($mimetypes),"12");
//    	$user = $admin_user_resource->getDataFieldsReally();
//
//    	$url = "http://localhost/app2/phone/users?log_level=1";
//    	$curl = curl_init($url);
//        curl_setopt($curl, CURLOPT_USERPWD, ''.$user['email'].':xxxxxxxxx');
//        curl_setopt($curl, CURLOPT_HTTPHEADER, array("X_SPLICKIT_CLIENT_ID:com.splickit.jerseymikes","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:CreateUserTestWithToken","NO_CC_CALL:true","Content-Type: application/json"));
//		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//		//curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
//		curl_setopt($curl, CURLOPT_POST, 1);
//		curl_setopt($curl, CURLOPT_POSTFIELDS,$json_body);
//		$result = curl_exec($curl);
//		$info = curl_getinfo($curl);
//		curl_close($curl);
//
//		$this->assertEquals('Application/json', $info['content_type']);
//		$new_user_response = json_decode($result,true);
//		$this->assertNull($new_user_response['ERROR'],"should NOT have gotten an error");
//		$this->assertNotNull($new_user_response['uuid']);
//
//		$user_record = UserAdapter::staticGetRecord(array("uuid"=>$new_user_response['uuid']), 'UserAdapter');
//    	$this->assertNotNull($user_record);
//    	$this->assertTrue($user_record['user_id'] > 1000);
//    	$this->assertEquals(72, $user_record['skin_id']);
//
//    	$user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
//    	$record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_record->user_id,"brand_id"=>326));
//    	$this->assertNull($record);
//    	return $user_record;
//    }
//
//    /**
//     * @depends testCreateUserWithToken
//     */
//    function testCreateUserWithTokenFailure($user_record)
//    {
//    	$user_data = createNewUserDataFields();
//    	$user_data['email'] = $user_record['email'];
//    	$json_user_data = json_encode($user_data);
//    	$encrypted_user_data = SplickitCrypter::doEncryption($json_user_data,'mikesmarketer');
//    	$create_user_data['create_user_token'] = $encrypted_user_data;
//		$json_body = json_encode($create_user_data);
//
//    	$admin_user_resource = Resource::find(new UserAdapter($mimetypes),"12");
//    	$user = $admin_user_resource->getDataFieldsReally();
//
//    	$url = "http://localhost/app2/phone/users?log_level=5";
//    	$curl = curl_init($url);
//    	curl_setopt($curl, CURLOPT_USERPWD, ''.$user['email'].':xxxxxxxxx');
//    	curl_setopt($curl, CURLOPT_HTTPHEADER, array("X_SPLICKIT_CLIENT_ID:com.splickit.jerseymikes","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:CreateUserTestWithToken","NO_CC_CALL:true",'Content-Type: application/json'));
//		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
//		curl_setopt($curl, CURLOPT_POST, 1);
//		curl_setopt($curl, CURLOPT_POSTFIELDS,$json_body);
//		$result = curl_exec($curl);
//		$info = curl_getinfo($curl);
//		curl_close($curl);
//		$this->assertEquals('Application/json', $info['content_type']);
//		$this->assertEquals(500, $info['http_code']);
//		$new_user_response = json_decode($result,true);
//		$this->assertNotNull($new_user_response['ERROR'],"should should have gotten an error");
//		$this->assertEquals("Sorry, it appears this email address exists already.", $new_user_response['ERROR']);
//    }
    
    function testTempUserFixForNewPhone()
    {
    	$user_resource = createNewTempUser();
    	$old_device_id = $user_resource->device_id;
    	$user_id = $user_resource->user_id;
    	$email = $user_resource->email;
    	$password = 'TlhKDMd8ni6M';

    	$url = "http://localhost/app2/phone/usersession?log_level=6";
    	$curl = curl_init($url);
    	curl_setopt($curl, CURLOPT_USERPWD, "$email:$password");
    	curl_setopt($curl, CURLOPT_HTTPHEADER, array("X_SPLICKIT_CLIENT_VERSION:88.8.8","X_SPLICKIT_CLIENT_ID:com.splickit.order","X_SPLICKIT_CLIENT_DEVICE:iphone","X_SPLICKIT_CLIENT_DEVICE_ID:$old_device_id","X_SPLICKIT_CLIENT:CreateUserTest","NO_CC_CALL:true"));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($curl);
		curl_close($curl);
		$user_session_response = json_decode($result,true);
		$this->assertNull($user_session_response['ERROR'],"should NOT have gotten an error");
		
		$user_resource2 = SplickitController::getResourceFromId($user_id, 'User');
		$this->assertEquals($old_device_id, $user_resource2->device_id);
		$this->assertEquals($old_device_id.'@splickit.dum', $user_resource2->email);

		$new_device_id = generateCode(10);
    	
    	$url = "http://localhost/app2/phone/usersession?log_level=6";
    	$curl = curl_init($url);
    	curl_setopt($curl, CURLOPT_USERPWD, "$email:$password");
    	curl_setopt($curl, CURLOPT_HTTPHEADER, array("X_SPLICKIT_CLIENT_VERSION:88.8.8","X_SPLICKIT_CLIENT_ID:com.splickit.order","X_SPLICKIT_CLIENT_DEVICE:iphone","X_SPLICKIT_CLIENT_DEVICE_ID:$new_device_id","X_SPLICKIT_CLIENT:CreateUserTest","NO_CC_CALL:true"));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($curl);
		curl_close($curl);
		$user_session_response = json_decode($result,true);
		$this->assertNull($user_session_response['ERROR'],"should NOT have gotten an error");
		
		$user_resource3 = SplickitController::getResourceFromId($user_id, 'User');
		$this->assertEquals($new_device_id, $user_resource3->device_id);
		$this->assertEquals($new_device_id.'@splickit.dum', $user_resource3->email);
				
    }

	function testGetAuthToken()
	{
		$user_resource = createNewUser();
		$user_id = $user_resource->user_id;
		$email = $user_resource->email;
		$url = "http://localhost/app2/phone/getauthtoken?log_level=6";
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_USERPWD, "$email:welcome");
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("X_SPLICKIT_CLIENT_VERSION:88.8.8","X_SPLICKIT_CLIENT_ID:com.splickit.order","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:CreateUserTest","NO_CC_CALL:true"));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($curl);
		curl_close($curl);
		$user_session_response = json_decode($result,true);
		$this->assertNotNull($user_session_response['auth_token'],'should have returned the auth token');
		$this->assertCount(1,$user_session_response,"shoudl have returned a response with ONLY the auth token");
	}

    function testAuthentication()
    {
    	$skin_adapter = new SkinAdapter($mimetypes);
    	$options[TONIC_FIND_BY_METADATA] = array("skin_id"=>1);
    	$skin_resource = Resource::find($skin_adapter, $url, $options);
    	$skin_resource->custom_skin_message = 'the custom skin message';
		$skin_resource->save();
    	
    	$user_resource = createNewUser();
    	$user_resource->last_four = '4321';
    	$user_resource->save();
    	$user_id = $user_resource->user_id;
    	$email = $user_resource->email;
    	$url = "http://localhost/app2/phone/usersession?log_level=6";
    	$curl = curl_init($url);
    	curl_setopt($curl, CURLOPT_USERPWD, "$email:welcome");
    	curl_setopt($curl, CURLOPT_HTTPHEADER, array("X_SPLICKIT_CLIENT_VERSION:88.8.8","X_SPLICKIT_CLIENT_ID:com.splickit.order","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:CreateUserTest","NO_CC_CALL:true"));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($curl);
		curl_close($curl);
		$user_session_response = json_decode($result,true);
		$this->assertNull($user_session_response['ERROR'],"should NOT have gotten an error");
		$this->assertEquals('the custom skin message', $user_session_response['user_message']);
		$this->assertNotNull($user_session_response['skin_charity_info']);
		$skin_charity_info = $user_session_response['skin_charity_info'];
		$this->assertEquals('Community Food Share', $skin_charity_info['charity_alert_title']);
		$this->assertEquals("88.8.8", $user_session_response['app_version']);
		$this->assertEquals("unit_testing", $user_session_response['device_type']);
    }
    
    function testCreateNewUser()
    {
    	$url = "http://localhost/app2/phone/users?log_level=5";
    	$curl = curl_init($url);
    	curl_setopt($curl, CURLOPT_USERPWD, "admin:welcome");
    	curl_setopt($curl, CURLOPT_HTTPHEADER, array("X_SPLICKIT_CLIENT_ID:com.splickit.order","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:CreateUserTest","NO_CC_CALL:true","Content-Type: application/json"));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POST, 1);
		$new_user_data = createNewUserDataFields();
		curl_setopt($curl, CURLOPT_POSTFIELDS,json_encode($new_user_data));
		$result = curl_exec($curl);
		$info = curl_getinfo($curl);
		curl_close($curl);
		$this->assertEquals('Application/json', $info['content_type']);
		$new_user_response = json_decode($result,true);
		$this->assertNull($new_user_response['ERROR'],"should NOT have gotten an error");
		return $new_user_response['user_id'];
    }
    
    /**
     * @depends testCreateNewUser
     */
    function testNoOrderDataError($user_id)
    {
    	$url = "http://localhost/app2/phone/getcheckoutdata?log_level=5";
    	$curl = curl_init($url);
    	curl_setopt($curl, CURLOPT_USERPWD, "$user_id:welcome");
    	curl_setopt($curl, CURLOPT_HTTPHEADER, array("X_SPLICKIT_CLIENT_ID:com.splickit.order","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:CheckoutDataTest","NO_CC_CALL:true"));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$headers = array('Content-Type: application/json');
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS,null);
		$result = curl_exec($curl);
		curl_close($curl);
		$checkout_data = json_decode($result,true);
		$this->assertNotNull($checkout_data['ERROR'],"should have gotten an error due to a null body submitted");
		$this->assertEquals("Sorry, There was an error with your request, please try again", $checkout_data['ERROR']);
    	return $user_id;
    }

    /**
     * @depends testNoOrderDataError
     */
    function testOrderBadJsonError($user_id)
    {
    	$url = "http://localhost/app2/phone/getcheckoutdata?log_level=5";
    	$curl = curl_init($url);
    	curl_setopt($curl, CURLOPT_USERPWD, "$user_id:welcome");
    	curl_setopt($curl, CURLOPT_HTTPHEADER, array("X_SPLICKIT_CLIENT_ID:com.splickit.order","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:CheckoutDataTest","NO_CC_CALL:true"));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$headers = array('Content-Type: application/json');
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POST, 1);
		$bad_json = '{"bad_thing":"af"';
		curl_setopt($curl, CURLOPT_POSTFIELDS,$bad_json);
		$result = curl_exec($curl);
		curl_close($curl);
		$checkout_data = json_decode($result,true);
		$this->assertNotNull($checkout_data['ERROR'],"should have gotten an error due to bad json submitted");
		$this->assertEquals('There was a transmission error and we could not understand your request, please try again.', $checkout_data['ERROR']);
    	
    }
        
  static function main() {
    $suite = new PHPUnit_Framework_TestSuite( __CLASS__);
    PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    DispatchTest::main();
}

?>
