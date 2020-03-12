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

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class AuthenticationTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;
    var $api_port = "80";

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
        if (isset($_SERVER['XDEBUG_CONFIG'])) {
            $this->api_port = "10080";
        }
		
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }

    function testPublicSkinPasswordCreation()
    {
        $code = generateCode(4);
        $skin_resource = getOrCreateSkinAndBrandIfNecessary('testskin'.$code,'testbrand'.$code,null,null);
        $this->assertNull($skin_resource->password,"there should be no password");

        setContext($skin_resource->external_identifier);
        $url = "http://127.0.0.1:".$this->api_port."/app2/skins/".$skin_resource->public_client_id;
        $data = ['password'=>'test1234'];
        $request = createRequestObject($url,'POST',json_encode($data));
        $skin_controller = new SkinController(getM(), null, $request);
        $resource = $skin_controller->processRequest();
        $skin_adapter = new SkinAdapter(getM());
        $skin_adapter->bustCacheFromSkinId($skin_resource->skin_id);

        logTestUserIn(101);
        $after_skin_resource = Resource::find(new SkinAdapter(),$skin_resource->skin_id);
        $this->assertNotNull($after_skin_resource->password);
        return $after_skin_resource;
    }

    /**
     * @depends testPublicSkinPasswordCreation
     */
    function testAuthenticationWithAdminUserForPublicApiAccessBadPassword($after_skin_resource)
    {
        setContext($after_skin_resource->external_identifier);
        $login_adapter = LoginAdapterFactory::getLoginAdapterForContext();
        $this->assertFalse($login_adapter->authorize('admin','badpassword'));
    }

    /**
     * @depends testPublicSkinPasswordCreation
     */
    function testAuthenticationWithAdminUserForPublicApiAccessGoodPassword($after_skin_resource)
    {
        setContext($after_skin_resource->external_identifier);
        $login_adapter = LoginAdapterFactory::getLoginAdapterForContext();
        $user_resource = $login_adapter->authorize('admin','test1234');
        $this->assertNotNull($user_resource);
        $this->assertEquals(1,$user_resource->user_id);
    }

    function testCreateAndSaveAuthToken()
    {
    	$user_id = $this->ids['user_id'];
    	$authentication_token_resource = createUserAuthenticationToken($user_id);
    	$this->assertNotNull($authentication_token_resource);
    	$token_record = TokenAuthenticationsAdapter::staticGetRecord(array("token"=>$authentication_token_resource->token),'TokenAuthenticationsAdapter');
    	$this->assertNotNull($token_record);
    	$this->assertEquals($user_id, $token_record['user_id'],"shoudl have found the record with the correct user_id");
    	$this->assertTrue($token_record['expires_at'] > time()+43100,"should have been greater then almost 12 hours from now");
    	$this->assertTrue($token_record['expires_at'] < time()+43300,"should have been less then a bit more than 12 hours from now");
    }

	function testValidityOfHeaderTokenFieldName()
	{
		$login_adapter = new LoginAdapter($m);
		$headers = array();
		$headers['splickit_authentication_token'] = "sumdumtoken";
		$this->assertTrue($login_adapter->isValidHeaderTokenSet($headers),"It should accept the splickit_authentication_token as valid");
	}

	function testValidityOfHeaderTokenFieldNameBadTokenName()
	{
		$login_adapter = new LoginAdapter($m);
		$headers = array();
		$headers['stupid'] = "sumdumtoken";
		$this->assertFalse($login_adapter->isValidHeaderTokenSet($headers),"It should accept the splickit_authentication_token as valid");
	}

	function testAuthenticateWithToken()
    {
    	$user_id = $this->ids['user_id'];
    	$authentication_token_resource = createUserAuthenticationToken($user_id);
		
    	$request_all['splickit_authentication_token'] = $authentication_token_resource->token;
    	$loginAdapter = new LoginAdapter($mimetypes);
    	$user_resource = $loginAdapter->doAuthorizeWithSpecialUserValidation($email, $password,$request_all);
    	$this->assertNotFalse($user_resource,"should have found a user resource but authorize came back false");
    	$this->assertEquals($user_id, $user_resource->user_id,"should have grabbed the user resource associated wih the correct user but it did not");    
    	return $authentication_token_resource;	    	
    }
    
    /**
     * @depends testAuthenticateWithToken
     */
    function testExpiredToken($authentication_token_resource)
    {
    	$authentication_token_resource->expires_at = time() - 10;
    	$authentication_token_resource->save();
    	$login_adapter = new LoginAdapter($mimetypes);
    	$request_all['splickit_authentication_token'] = $authentication_token_resource->token;
    	$user_resource = $login_adapter->doAuthorizeWithSpecialUserValidation($email, $password,$request_all);
    	$this->assertFalse($user_resource);
    	$login_error_resource = $login_adapter->getErrorResource();
    	$this->assertNotNull($login_error_resource->error,"authentication should have produced an error do to an expired token");
    	$this->assertEquals("Sorry, your session has expired, please log in again.", $login_error_resource->error);
    }

	function testDeletedToken()
	{
		$login_adapter = new LoginAdapter($mimetypes);
		$request_all['splickit_authentication_token'] = generateCode(20);
		$user_resource = $login_adapter->doAuthorizeWithSpecialUserValidation($email, $password,$request_all);
		$this->assertFalse($user_resource);
		$login_error_resource = $login_adapter->getErrorResource();
		$this->assertNotNull($login_error_resource->error,"authentication should have produced an error do to a deleted");
		$this->assertEquals("Sorry, your session has expired, please log in again.", $login_error_resource->error);

	}

	function testSetCorrectHTTPcodeForBadAuth()
	{
		$login_adapter = new LoginAdapter($mimetypes);
		$this->assertFalse($login_adapter->doAuthorizeWithSpecialUserValidation("bademail@dummy.com", "password",$request_all),"auth should have come back false");
		$resource = $login_adapter->getErrorResource();
		$this->assertTrue(is_a($resource, 'Resource'),'Should have gotten back a resource');
		$this->assertEquals(401, $resource->http_code,"Http status code shoudl be 401");
	}

	function testSetCorrectHTTPcodeForBadAuthGoodEmail()
	{
		$user_resource = createNewUser();
		$user_resource->bad_login_count = 4;
		$user_resource->save();
		$login_adapter = new LoginAdapter($mimetypes);
		$this->assertFalse($login_adapter->doAuthorizeWithSpecialUserValidation($user_resource->email, "sumpassword",$request_all),"auth should have come back false");
		$resource = $login_adapter->getErrorResource();
		$this->assertTrue(is_a($resource, 'Resource'),'Should have gotten back a resource');
		$this->assertEquals(401, $resource->http_code,"Http status code shoudl be 401");
	}

	function testDeleteOldTokens()
	{
		$taa = new TokenAuthenticationsAdapter($m);
		$taa->_query("DELETE FROM Token_Authentications WHERE 1=1");
		for ($i=0;$i<10;$i++) {
			$auth_token = generateCode(20);
			if ($i < 6) {
				$expires_at = time() - 100;
			} else {
				$expires_at = time() + 100;
			}
			$token_authentication_resource = Resource::createByData($taa, array("user_id"=>1000,"token"=>$auth_token,"expires_at"=>$expires_at));
		}
		$ntaa = new TokenAuthenticationsAdapter($m);
		$records = $ntaa->clearExpiredTokens();
		$this->assertEquals(6,$records,"there should have been 6 records deleted");
		$records = $taa->getRecords(array("expires_at"=>array(">"=>1)));
		$this->assertCount(4,$records,"there should be 4 records left");
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

    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;
    	
    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;
    }
    
	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();
    	$db = DataBase::getInstance();
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
    AuthenticationTest::main();
}

?>