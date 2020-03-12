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

class FacebookAuthenticationTest extends PHPUnit_Framework_TestCase
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
        unset($_SERVER['facebook_test_data']);
        unset($this->ids);
    }

    function testUpdateGuestToNormalUserWithSameEmail()
    {
        $user_resource = createGuestUser();

        // now join with same email
        $facebook_id = rand(11111111111111,99999999999999);
        $email_escaped = str_replace('@','\u0040',$user_resource->email);
        //$_SERVER['facebook_test_data'] = ['id'=>'123456789','name'=>$new_user_data['first_name'].' '.$new_user_data['last_name'],'email'=>"$email_escaped"];
        $_SERVER['facebook_test_data'] = '{"id":"'.$facebook_id.'","name":"Bob Newhart","email":"'.$email_escaped.'"}';

        $this->assertNotFalse(UserAdapter::doesUserExist($user_resource->email));

        $facebook_token = createCode(20);
        $request_all['facebook_authentication_token'] = $facebook_token;
        $loginAdapter = new LoginAdapter(getM());
        $logged_in_user_resource = $loginAdapter->doAuthorizeWithSpecialUserValidation('facebook_authentication_token', $facebook_token,$request_all);
        $this->assertNotFalse($logged_in_user_resource,"should have found a user resource but authorize came back false");
        $this->assertEquals($user_resource->email, $logged_in_user_resource->email,"should have grabbed the user resource associated wih the correct user but it did not");

        // check to make sure maps was created
        $ufima = new UserFacebookIdMapsAdapter(getM());
        $this->assertEquals($user_resource->user_id,$ufima->findUserIdFromFacebookId($facebook_id));

        // see if flag is set on user for facebook auth
        $this->assertFalse(doesFlagPositionNEqualX($logged_in_user_resource->flags, 5, 'F'),"Facebook created user flag should not be set");
        $this->assertEquals('Bob', $logged_in_user_resource->first_name);
        $this->assertEquals("Newhart", $logged_in_user_resource->last_name);
        $this->assertNotNull($logged_in_user_resource->splickit_authentication_token);
        $this->assertNotNull($logged_in_user_resource->splickit_authentication_token_expires_at);
        $this->assertEquals($logged_in_user_resource->flags, '1000000001');

    }

    function testNoEmailFromFacebook()
    {
        $facebook_id = rand(1111111,9999999).''.rand(1111111,9999999);
        $_SERVER['facebook_test_data'] = '{"id":"'.$facebook_id.'","name":"Bad Roberts"}';
        $facebook_token = createCode(20);
        $request_all['facebook_authentication_token'] = $facebook_token;
        $loginAdapter = new LoginAdapter(getM());
        $user_resource = $loginAdapter->doAuthorizeWithSpecialUserValidation('facebook_authentication_token', $facebook_token,$request_all);
        $this->assertNotFalse($user_resource,"should have found a user resource but authorize came back false");
        $expected_email = $facebook_id.'@facebook.com';
        $this->assertEquals($expected_email, $user_resource->email,"should have grabbed the user resource associated wih the correct user but it did not");
        $this->assertNotFalse(UserAdapter::doesUserExist($expected_email));

        // check to make sure maps was created
        $ufima = new UserFacebookIdMapsAdapter(getM());
        $this->assertEquals($user_resource->user_id,$ufima->findUserIdFromFacebookId($facebook_id));

        // see if flag is set on user for facebook auth
        $this->assertTrue(doesFlagPositionNEqualX($user_resource->flags, 5, 'F'),"Facebook created user flag should be set");

        $facebook_token = createCode(20);
        $request_all['facebook_authentication_token'] = $facebook_token;
        $loginAdapter = new LoginAdapter(getM());
        $user_resource_existing = $loginAdapter->doAuthorizeWithSpecialUserValidation('facebook_authentication_token', $facebook_token,$request_all);
        $this->assertNotFalse($user_resource_existing,"should have found a user resource but authorize came back false");
        $this->assertEquals($expected_email, $user_resource_existing->email,"should have grabbed the user resource associated wih the correct user but it did not");

    }

    function testValidityOfHeaderTokenFieldName()
    {
        $login_adapter = new LoginAdapter(getM());
        $headers = array();
        $headers['facebook_authentication_token'] = "sumdumtoken";
        $this->assertTrue($login_adapter->isValidHeaderTokenSet($headers),"It should accept the facebook_authentication_token as valid");
    }

    function testValidityOfHeaderTokenFieldNameBadTokenName()
    {
        $login_adapter = new LoginAdapter(getM());
        $headers = array();
        $headers['stupid'] = "sumdumtoken";
        $this->assertFalse($login_adapter->isValidHeaderTokenSet($headers),"It should NOT accept the facebook_authentication_token as valid");
    }

    function testGetFacebookAuthenticationTokenFromSubmittedLoginData()
    {
        $login_adapter = new LoginAdapter(getM());
        $headers = array();
        $headers['facebook_authentication_token'] = "sumdumtoken";
        $this->assertEquals('sumdumtoken',$login_adapter->getFacebookAuthenticationTokenFromSubmittedLoginData(null, null,$headers));
    }

    function testBadToken()
    {
        $facebook_token = 'XXXXXXXX';
        $request_all['facebook_authentication_token'] = $facebook_token;
        $loginAdapter = new LoginAdapter(getM());
        $response = $loginAdapter->doAuthorizeWithSpecialUserValidation('facebook_authentication_token', $facebook_token,$request_all);
        $this->assertFalse($response);
    }

    function testLinkToExistingSplickitUserWithFirstTimeFacebookLogin()
    {
        $facebook_id = rand(11111111111111,99999999999999);
        $user_resource = createNewUserWithCCNoCVV();
        $email_escaped = str_replace('@','\u0040',$user_resource->email);
        //$_SERVER['facebook_test_data'] = ['id'=>'123456789','name'=>$new_user_data['first_name'].' '.$new_user_data['last_name'],'email'=>"$email_escaped"];
        $_SERVER['facebook_test_data'] = '{"id":"'.$facebook_id.'","name":"firstname lastname","email":"'.$email_escaped.'"}';

        $this->assertNotFalse(UserAdapter::doesUserExist($user_resource->email));

        $facebook_token = createCode(20);
        $request_all['facebook_authentication_token'] = $facebook_token;
        $loginAdapter = new LoginAdapter(getM());
        $logged_in_user_resource = $loginAdapter->doAuthorizeWithSpecialUserValidation('facebook_authentication_token', $facebook_token,$request_all);
        $this->assertNotFalse($logged_in_user_resource,"should have found a user resource but authorize came back false");
        $this->assertEquals($user_resource->email, $logged_in_user_resource->email,"should have grabbed the user resource associated wih the correct user but it did not");

        // check to make sure maps was created
        $ufima = new UserFacebookIdMapsAdapter(getM());
        $this->assertEquals($user_resource->user_id,$ufima->findUserIdFromFacebookId($facebook_id));

        // see if flag is set on user for facebook auth
        $this->assertFalse(doesFlagPositionNEqualX($logged_in_user_resource->flags, 5, 'F'),"Facebook created user flag should NOT be set");

    }

    function testCreateUserFromValidFacebookToken()
    {
        $facebook_id = '88888888';
        $new_user_data = createNewUserDataFieldsLite();
        $email_escaped = str_replace('@','\u0040',$new_user_data['email']);
        //$_SERVER['facebook_test_data'] = ['id'=>'123456789','name'=>$new_user_data['first_name'].' '.$new_user_data['last_name'],'email'=>"$email_escaped"];
        $_SERVER['facebook_test_data'] = '{"id":"'.$facebook_id.'","name":"firstname lastname","email":"'.$email_escaped.'"}';

        $this->assertFalse(UserAdapter::doesUserExist($new_user_data['email']));

        $facebook_token = createCode(20);
        $request_all['facebook_authentication_token'] = $facebook_token;
        $loginAdapter = new LoginAdapter(getM());
        $user_resource = $loginAdapter->doAuthorizeWithSpecialUserValidation('facebook_authentication_token', $facebook_token,$request_all);
        $this->assertNotFalse($user_resource,"should have found a user resource but authorize came back false");
        $this->assertEquals($new_user_data['email'], $user_resource->email,"should have grabbed the user resource associated wih the correct user but it did not");
        $this->assertNotFalse(UserAdapter::doesUserExist($new_user_data['email']));

        // check to make sure maps was created
        $ufima = new UserFacebookIdMapsAdapter(getM());
        $this->assertEquals($user_resource->user_id,$ufima->findUserIdFromFacebookId($facebook_id));

        // see if flag is set on user for facebook auth
        $this->assertTrue(doesFlagPositionNEqualX($user_resource->flags, 5, 'F'),"Facebook created user flag should be set");

        return $user_resource;
    }

    /**
     * @depends testCreateUserFromValidFacebookToken
     */
    function testAuthenticateExistingUser($existing_user_resource)
    {
        $facebook_id = '88888888';
        $email_escaped = str_replace('@','\u0040',$existing_user_resource->email);
        $_SERVER['facebook_test_data'] = $_SERVER['facebook_test_data'] = '{"id":"'.$facebook_id.'","name":"firstname lastname","email":"'.$email_escaped.'"}';
        $facebook_token = createCode(20);
        $request_all['facebook_authentication_token'] = $facebook_token;
        $loginAdapter = new LoginAdapter(getM());
        $user_resource = $loginAdapter->doAuthorizeWithSpecialUserValidation('facebook_authentication_token', $facebook_token,$request_all);
        $this->assertNotFalse($user_resource,"should have found a user resource but authorize came back false");
        $this->assertEquals($existing_user_resource->email, $user_resource->email,"should have grabbed the user resource associated wih the correct user but it did not");
    }

    /**
     * @depends testCreateUserFromValidFacebookToken
     */
    function testAuthenticateExistingUserButEmailChanged($existing_user_resource)
    {
        $email_escaped = str_replace('@','\u0040',$existing_user_resource->email);

        //now change our record
        $existing_user_resource->email = 'sumnewdumemail@dummy.com';
        $existing_user_resource->save();

        $_SERVER['facebook_test_data'] = $_SERVER['facebook_test_data'] = '{"id":"88888888","name":"first name last name","email":"'.$email_escaped.'"}';
        $facebook_token = createCode(20);
        $request_all['facebook_authentication_token'] = $facebook_token;
        $loginAdapter = new LoginAdapter(getM());
        $user_resource = $loginAdapter->doAuthorizeWithSpecialUserValidation('facebook_authentication_token', $facebook_token,$request_all);
        $this->assertNotFalse($user_resource,"should have found a user resource but authorize came back false");
        $this->assertEquals($existing_user_resource->email, $user_resource->email,"should have grabbed the user resource associated wih the correct user but it did not");
    }

    /**
     * @depends testCreateUserFromValidFacebookToken
     */
    function testFaceBookUserLoggingIsFromLoginScreen($existing_user_resource)
    {
        $loginAdapter = new LoginAdapter(getM());
        $result = $loginAdapter->doAuthorizeWithSpecialUserValidation($existing_user_resource->email, 'abcd1234',null);
        $this->assertFalse($result,"Login result should have been false");
        $error_message = LoginAdapter::BAD_PASSWORD_FOR_FACEBOOK_CREATED_ACCOUNT_ERROR;
        $this->assertEquals($error_message,$loginAdapter->getErrorResource()->error);
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
    FacebookAuthenticationTest::main();
}

?>