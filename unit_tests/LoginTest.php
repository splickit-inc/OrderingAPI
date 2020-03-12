<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class LoginTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $twitter_user_id;
	var $user;

	function setUp()
	{
    	$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		
		/*
		$user_name = "radamnyc@gmail.com";
    	$user_adapter = new UserAdapter($mimetypes);
    	$user_resource = $user_adapter->doesUserExist($user_name);
    	$user_adapter->setPassword($user_resource, 'welcome');
    	$user_resource->bad_login_count = 1;
		$user_resource->save();
		*/
	}
/*	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->stamp);
		unset($this->twitter_user_id);

    }	

*/    
	function testBadLoginCount()
	{
		$user_resource = createNewUserWithCC();
		$this->assertEquals('1C21000001', $user_resource->flags);
		$user_resource->bad_login_count = 4;
		$login_adapter = new LoginAdapter($mimetypes);
		$login_adapter->formatLoginError($user_resource);
		$error_resource = $login_adapter->getErrorResource();
		$this->assertEquals(50,$error_resource->error_code);
		$this->assertEquals("Password reset instructions have been emailed to you. ", $error_resource->error);
		$user = $login_adapter->getRecordFromPrimaryKey($user_resource->user_id);
		$this->assertEquals('1021000001', $user['flags']);
		
		$user_resource = Resource::find($login_adapter, ''.$user['user_id'], $options);
		$user_resource->bad_login_count = 6;
		$login_adapter->formatLoginError($user_resource);
		$error_resource = $login_adapter->getErrorResource();
		$this->assertEquals("Your account is now locked for 2 min.  Please try again after that.", $error_resource->error);
		$user = $login_adapter->getRecordFromPrimaryKey($user_resource->user_id);
		$this->assertEquals('2021000001', $user['flags']);
		
	}
	
	function testLoginWithLockedAccount()
	{
		$user_resource = createNewUser();
		$user_resource->flags = "2000000001";
		$user_resource->save();
		$login_adapter = new LoginAdapter($mimetypes);
		$this->assertFalse($login_adapter->authorize($user_resource->email, 'welcome', $request_data));
	}
	
    function testAdminUsers()
    {
        setContext('com.splickit.worldhq');
       	$la = new LoginAdapter(getM());
    	$this->assertTrue(is_a($la->authorize('admin', 'welcome'), 'Resource'));
    	$this->assertTrue(is_a($la->authorize('2', 'spl1ck1t'), 'Resource'));
    	$this->assertFalse($la->authorize('2', 'nobadness'));
    	$this->assertTrue(is_a($la->authorize('vip@pitapit.com', 'test'), 'Resource'));
    	$this->assertTrue(is_a($la->authorize('qa_tester', 'test'), 'Resource'));
    	$this->assertTrue(is_a($la->authorize('mikesmarketer', 'Z2xTtj5w1qz'), 'Resource'));
    	$this->assertTrue(is_a($la->authorize('store_tester@dummy.com', 'spl1ck1t'), 'Resource'));
    	$this->assertTrue(is_a($la->authorize('nouser', 'welcome'), 'Resource'));
    	
    	$user_resource = createNewUser();
    	$this->assertFalse(is_a($la->authorize($user_resource->email, 'adminpassword'), 'Resource'));
    	$user['user_id'] = 1000;
    	$user_controller = new UserController($mt, $user, $r);
    	$admin_user_resource = $user_controller->updateUserFromData(array("password"=>'adminpassword'));
    	$this->assertNull($admin_user_resource->error);
    	$this->assertTrue(is_a($la->authorize($user_resource->email, 'adminpassword'), 'Resource'));
    	
    }

/*    function testTimeOfCrypt()
    {
    	error_log("***********  salting test *********");
    	$login_adapter = new LoginAdapter($mimetypes);
    	$string = "abcdefghijklmn";
    	$time1 = microtime(true);
    	for ($i=1;$i<11;$i++)
    	{
    		$hash = Encrypter::Encrypt($string);
    	}
    	$time2 = microtime(true);
    	$total_time = $time2-$time1;
    	error_log("time for hashing 1000 strings is: ".$total_time);
    	$this->assertTrue(true);
    }
*/	
	
    function testBcryptofpassword()
    {
    	$login_adapter = new LoginAdapter($mimetypes);
    	$password = "rbetsjdigl";
    	$hash = Encrypter::Encrypt($password);
    	$thingsy = crypt($password, $hash);
    	
    	$this->assertTrue($hash == crypt($password,$hash),"$thingsy should have equaled $hash");
    	$this->assertFalse($hash == crypt('sdfgasfg',$hash));
    }

    // ********* WILL NEED TO GET THIS WORKING WHEN WE HAVE ANOTHER ENCRYPTION/NEED WITH A CLIENT  ************//


//    function testTokenAuthentication()
//    {
//		$user_resource = createNewUser();
//
//		$mm_data['username'] = $user_resource->user_id;
//    	$mm_data['valid_until_time_stamp'] = time()+60;
//    	$auth_data['mikes_marketer_id'] = "12345";
//    	$auth_data['some_key'] = "some_value";
//        $mm_data['auth_data'] = $auth_data;
//        $json = json_encode($mm_data);
//    	$json_encoded = SplickitCrypter::doEncryption($json,'mikesmarketer');
//        myerror_log("JSON: ".$json_encoded);
//    	$login_adapter = new LoginAdapter($mimetypes);
//		$request_data['Auth_token'] = "$json_encoded";
//        myerror_log("ABOUT TO TO THE LOGING TEST");
//		$result = $login_adapter->doAuthorizeWithSpecialUserValidation("mikesmarketer", "Z2xTtj5w1qz",$request_data);
//    	$this->assertNotNull($result);
//    	$error_resource = $login_adapter->getErrorResource();
//    	$this->assertNull($error_resource->error);
//    	$this->assertTrue($result->_exists);
//    	$this->assertTrue(is_a($result->_adapter, "UserAdapter"));
//    	$this->assertTrue(is_a($result,"Resource"));
//    	$this->assertEquals($user_resource->user_id, $result->user_id);
//    	$auth_data = $result->auth_data;
//    	$this->assertNotNull($auth_data);
//    	$this->assertEquals("12345", $auth_data['mikes_marketer_id']);
//    	$this->assertEquals("some_value",$auth_data['some_key']);
//    }
	
    function testLoginObjectCheckPassword()
    {
    	//$user_resource = UserAdapter::doesUserExist("radamnyc@gmail.com"); 
    	$user_resource = createNewUser();
    	$login_adapter = new LoginAdapter($mimetypes);    	
    	$result = $login_adapter->checkPassword($user_resource, "badpass");
    	$this->assertFalse($result);
		$result = $login_adapter->checkPassword($user_resource, "welcome");
    	$this->assertTrue($result);
    }
    
    function testLoginObjectAuthorize()
    {
    	$user_resource = createNewUser();
    	$login_adapter = new LoginAdapter($mimetypes);
    	$result = $login_adapter->authorize("radam666666nyc@gmail.com", "badpass");
    	$this->assertFalse($result);
    	$error_resource = $login_adapter->getErrorResource();
    	$this->assertEquals("Your username does not exist in our system, please check your entry.", $error_resource->error);
    	
    	$result = $login_adapter->authorize($user_resource->email, "badpass");
    	$this->assertFalse($result);
    	$error_resource = $login_adapter->getErrorResource();
    	$this->assertEquals("Your password is incorrect.", $error_resource->error);
    	
    	$result = $login_adapter->authorize($user_resource->email, "badpass");
    	$this->assertFalse($result);
    	$error_resource = $login_adapter->getErrorResource();
    	$this->assertEquals("We will email you instructions on how to reset your password on one more failed attempt.", $error_resource->error);
    	
    	$result = $login_adapter->authorize($user_resource->email, "welcome");
    	$this->assertNotNull($result);
    	$this->assertTrue($result->_exists);
    	$this->assertTrue(is_a($result->_adapter, "UserAdapter"));
    	
    	$this->assertEquals(1, $result->bad_login_count);
    }
    
    function testDoAuthorizeWithSpecialUserValidation()
    {
    	$user_resource = createNewUser();
    	$login_adapter = new LoginAdapter($mimetypes);
    	$result = $login_adapter->doAuthorizeWithSpecialUserValidation("radam666666nyc@gmail.com", "badpass",$request_data);
    	$this->assertFalse($result);
    	$error_resource = $login_adapter->getErrorResource();
    	$this->assertEquals("Your username does not exist in our system, please check your entry.", $error_resource->error);
    	
    	$result = $login_adapter->doAuthorizeWithSpecialUserValidation($user_resource->email, "badpass",$request_data);
    	$this->assertFalse($result);
    	$error_resource = $login_adapter->getErrorResource();
    	$this->assertEquals("Your password is incorrect.", $error_resource->error);
    	
    	$result = $login_adapter->doAuthorizeWithSpecialUserValidation($user_resource->email, "badpass",$request_data);
    	$this->assertFalse($result);
    	$error_resource = $login_adapter->getErrorResource();
    	$this->assertEquals("We will email you instructions on how to reset your password on one more failed attempt.", $error_resource->error);
    	
    	$result = $login_adapter->doAuthorizeWithSpecialUserValidation($user_resource->email, "welcome",$request_data);
    	$this->assertNotNull($result);
    	$this->assertTrue($result->_exists);
    	$this->assertTrue(is_a($result->_adapter, "UserAdapter"));
    	
    	$this->assertEquals(1, $result->bad_login_count);

   }	

   /****  these next duplicates will be for testing the new auth stuff with bcrypt ****/
/*   
   function testLoginObjectCheckPassword2()
   {
	   	//$user_resource = UserAdapter::doesUserExist("radamnyc@gmail.com"); 
	   	
	   	$user_resource = createNewUser();
	   	$login_adapter = new LoginAdapter($mimetypes);    	
	   	$result = $login_adapter->checkPassword($user_resource, "badpass");
	   	$this->assertFalse($result);
			$result = $login_adapter->checkPassword($user_resource, "welcome");
	   	$this->assertTrue($result);
   }

   function testLoginObjectAuthorize2()
   {
	   	$login_adapter = new LoginAdapter($mimetypes);
	   	$result = $login_adapter->authorize("radam666666nyc@gmail.com", "badpass");
	   	$this->assertFalse($result);
	   	$error_resource = $login_adapter->getErrorResource();
	   	$this->assertEquals("Your username does not exist in our system, please check your entry.", $error_resource->error);
	   	
	   	$result = $login_adapter->authorize("radamnyc@gmail.com", "badpass");
	   	$this->assertFalse($result);
	   	$error_resource = $login_adapter->getErrorResource();
	   	$this->assertEquals("Your password is incorrect.", $error_resource->error);
	   	
	   	$result = $login_adapter->authorize("radamnyc@gmail.com", "badpass");
	   	$this->assertFalse($result);
	   	$error_resource = $login_adapter->getErrorResource();
	   	$this->assertEquals("We will email you instructions on how to reset your password on one more failed attempt.", $error_resource->error);
	   	
	   	$result = $login_adapter->authorize("radamnyc@gmail.com", "welcome");
	   	$this->assertNotNull($result);
	   	$this->assertTrue($result->_exists);
	   	$this->assertTrue(is_a($result->_adapter, "UserAdapter"));
	   	
	   	$this->assertEquals(1, $result->bad_login_count);
   }

   function testDoAuthorizeWithSpecialUserValidation2()
   {
   	$login_adapter = new LoginAdapter($mimetypes);
   	$result = $login_adapter->doAuthorizeWithSpecialUserValidation("radam666666nyc@gmail.com", "badpass",$request_data);
   	$this->assertFalse($result);
   	$error_resource = $login_adapter->getErrorResource();
   	$this->assertEquals("Your username does not exist in our system, please check your entry.", $error_resource->error);
   	
   	$result = $login_adapter->doAuthorizeWithSpecialUserValidation("radamnyc@gmail.com", "badpass",$request_data);
   	$this->assertFalse($result);
   	$error_resource = $login_adapter->getErrorResource();
   	$this->assertEquals("Your password is incorrect.", $error_resource->error);
   	
   	$result = $login_adapter->doAuthorizeWithSpecialUserValidation("radamnyc@gmail.com", "badpass",$request_data);
   	$this->assertFalse($result);
   	$error_resource = $login_adapter->getErrorResource();
   	$this->assertEquals("We will email you instructions on how to reset your password on one more failed attempt.", $error_resource->error);
   	
   	$result = $login_adapter->doAuthorizeWithSpecialUserValidation("radamnyc@gmail.com", "welcome",$request_data);
   	$this->assertNotNull($result);
   	$this->assertTrue($result->_exists);
   	$this->assertTrue(is_a($result->_adapter, "UserAdapter"));
   	
   	$this->assertEquals(1, $result->bad_login_count);

	}
	
*/	
	function testSpecialUserValidationTwitter()
	{
		$user_resource = createNewUser();
		$user_social_data['user_id'] = $user_resource->user_id;
		$user_social_data['twitter_user_id'] = "8888866666";
		$user_social_adapter = new UserSocialAdapter($mimetypes);
		$us_resource = Resource::createByData($user_social_adapter, $user_social_data);
		
    	$login_adapter = new LoginAdapter($mimetypes);    	
		$request_data['twitter_user_id'] = 'jghdudhgy';
		//$_SERVER['twitter_user_id'] = 'jghdudhgy';
    	$result = $login_adapter->doAuthorizeWithSpecialUserValidation("order140", "welcome",$request_data);
    	$this->assertFalse($result);
    	$error_resource = $login_adapter->getErrorResource();
    	$this->assertEquals("No twitter ID was submitted.", $error_resource->error);

    	$_SERVER['HTTP_X_SPLICKIT_TWITTER_USER_ID'] = '8888866666';
    	//$_SERVER['twitter_user_id'] = '1234567890';
    	$result = $login_adapter->doAuthorizeWithSpecialUserValidation("order140", "welcome",$request_data);
    	$this->assertNotNull($result);
    	$this->assertTrue($result->_exists);
    	$this->assertTrue(is_a($result->_adapter, "UserAdapter"));
    	$this->assertEquals($user_resource->user_id, $result->user_id);
	}
	
	function testSpecialUserValidationLineBuster()
	{
    	$merchant_resource = createNewTestMerchant();
    	$numeric_id = $merchant_resource->numeric_id;
    	$user_email = $numeric_id.'_manager@dummy.com';
    	$user_resource = createNewUser(array("email"=>$user_email));
		
		$login_adapter = new LoginAdapter($mimetypes);    	
    	
    	$result = $login_adapter->doAuthorizeWithSpecialUserValidation($user_email, "welcome",$request_data);
    	$this->assertNotNull($result);
    	$this->assertTrue($result->_exists);
    	$this->assertTrue(is_a($result->_adapter, "UserAdapter"));
    	$this->assertEquals($user_resource->user_id, $result->user_id);
    	$this->assertEquals($merchant_resource->merchant_id, $result->line_buster_merchant_id);
	}

/*	function testLoginFromDispatch()
	{
		$url = "http://localhost:8888/app2/phone/usersession";
    	$curl = curl_init($url);
    	curl_setopt($curl, CURLOPT_USERPWD, "radamnyc@gmail.com:welxxcome"); 
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);	
		$result = curl_exec($curl);
		curl_close($curl);
		$r = json_decode($result);
		$this->assertEquals('Your password is incorrect.', $r->ERROR);
		$this->assertEquals(50,$r->ERROR_CODE);
		$this->assertEquals('Authentication Error',$r->TEXT_TITLE);
		
		$url = "http://localhost:8888/app2/phone/usersession?log_level=5";
    	$curl = curl_init($url);
    	curl_setopt($curl, CURLOPT_USERPWD, "radamnyc@gmail.com:welcome"); 
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);	
		$result = curl_exec($curl);
		curl_close($curl);
		$r = json_decode($result);
		$this->assertEquals(36985, $r->user_id);
		$this->assertEquals('Adam',$r->first_name);
	}
/*	
	function testLoginOrder140WithTwitterUserFromDispatch()
	{
		$url = "http://localhost:8888/app2/phone/usersession?log_level=5";
    	$curl = curl_init($url);
    	curl_setopt($curl, CURLOPT_USERPWD, "order140:welcome"); 
    	curl_setopt($curl, CURLOPT_HTTPHEADER, array("X_SPLICKIT_TWITTER_USER_ID:1234567890"));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);	
		$result = curl_exec($curl);
		curl_close($curl);
		$r = json_decode($result);
		$this->assertEquals(36985, $r->user_id);
		//$this->assertEquals('Adam',$r->first_name);	
	}

    function testPlaceOrderWithOrder140ThroughDispatch()
	{
		$order_adapter = new OrderAdapter($mimetypes);
		$url = "http://localhost:8888/app2/phone/placeorder?log_level=5";
		$order = $order_adapter->getSimpleOrderArrayByMerchantId(1083, 'Pickup', 'skip hours');
		$order['user_id'] = 36985;
		$json = json_encode(array('jsonVal'=>$order));
    	$curl = curl_init($url);
    	curl_setopt($curl, CURLOPT_USERPWD, "order140:welcome"); 
    	curl_setopt($curl, CURLOPT_HTTPHEADER, array("X-SPLICKIT-TWITTER-USER-ID:1234567890","Content-Type: application/json","Content-length: ".strlen($json),"X_SPLICKIT_CLIENT_DEVICE:twitter","X_SPLICKIT_CLIENT:LoginTest","NO_CC_CALL:true"));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);	
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
		$result = curl_exec($curl);
		curl_close($curl);
		$r = json_decode($result);
		$this->assertTrue($r->order_id > 1000);
		$this->assertEquals(36985, $r->user_id);
		$this->assertEquals("twitter", $r->device_type);
	}
	
	function testLoginLineBusterFromDispatch()
	{
		$url = "http://localhost:8888/app2/phone/usersession?log_level=5";
    	$curl = curl_init($url);
    	curl_setopt($curl, CURLOPT_USERPWD, "82171713_manager@dummy.com:welcome"); 
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);	
		$result = curl_exec($curl);
		curl_close($curl);
		$r = json_decode($result);
    	$this->assertEquals(274710, $r->user_id);
    	$this->assertEquals(1004, $r->line_buster_merchant_id);
	}
	
	//*/

    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	$_SERVER['request_time1'] = microtime(true);    	
		
/*		$merchant_resource = createNewTestMerchant();
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;
    	    	    	
    	$user_resource = createNewUser(array('flags'=>'1C20000001'));
    	$ids['user_id'] = $user_resource->user_id;
    	    	
    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;
*/    	
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

if (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'use_main'  && !defined('PHPUnit_MAIN_METHOD')) {
    LoginTest::main();
}

?>