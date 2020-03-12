<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class FishbowlUserCreateTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		setContext("com.splickit.moes");
		
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }
    
    function testValidateDate()
    {
    	$fish_bowl_service = new FishBowlService();
    	$date_of_birth = "06/08";
    	$this->assertTrue($fish_bowl_service->validateBirthday($date_of_birth));
    	$date_of_birth = "0608";
    	$this->assertFalse($fish_bowl_service->validateBirthday($date_of_birth));
    	$date_of_birth = "june 8";
    	$this->assertFalse($fish_bowl_service->validateBirthday($date_of_birth));
    	$date_of_birth = "06/31";
    	$this->assertFalse($fish_bowl_service->validateBirthday($date_of_birth));
    	$date_of_birth = "06/2008";
    	$this->assertFalse($fish_bowl_service->validateBirthday($date_of_birth));
    	$date_of_birth = "happpy";
    	$this->assertFalse($fish_bowl_service->validateBirthday($date_of_birth));
    	$date_of_birth = "14/06";
    	$this->assertFalse($fish_bowl_service->validateBirthday($date_of_birth));
    	$date_of_birth = "10/O6";
    	$this->assertFalse($fish_bowl_service->validateBirthday($date_of_birth));
    }
    
    function testValidateZip()
    {
    	$fish_bowl_service = new FishBowlService();
    	$zip = "12345";
    	$this->assertTrue($fish_bowl_service->validateZipcode($zip));
    	$zip = "00345";
    	$this->assertTrue($fish_bowl_service->validateZipcode($zip));
    	$zip = "1234";
    	$this->assertFalse($fish_bowl_service->validateZipcode($zip));
    	$zip = "123456";
    	$this->assertFalse($fish_bowl_service->validateZipcode($zip));
    	$zip = "123A5";
    	$this->assertFalse($fish_bowl_service->validateZipcode($zip));
    }


    /*function testUserControllerFishBowlValidator()
    {
    	$user_controller = new UserAdapter($mimetypes);
    	$this->assertTrue($user_controller->validateFishBowlDataIfPresent(array("birthday"=>"0608","zipcode"=>"145")));
    	$this->assertTrue($user_controller->validateFishBowlDataIfPresent(array("marketing_email_opt_in"=>"Y","birthday"=>"06/08","zipcode"=>"12345")));
    	$this->assertFalse($user_controller->validateFishBowlDataIfPresent(array("marketing_email_opt_in"=>"Y","birthday"=>"0608","zipcode"=>"12345")));
    	$this->assertEquals("Sorry, birthdate must be in the form of mm/dd. please try again", $user_controller->fish_bowl_error);
    	$this->assertFalse($user_controller->validateFishBowlDataIfPresent(array("marketing_email_opt_in"=>"Y","birthday"=>"06/08","zipcode"=>"1245")));
    	$this->assertEquals("Sorry, your zip appears invalid, please try again", $user_controller->fish_bowl_error);
    }*/
    
    function testCreateUserWithFishBowl()
    {
    	//{"jsonVal":{"birthday":"06/69","zipcode":"12345","first_name":"Kdj","password":"welcome","last_name":"Djsj","email":"dh@jdj.con","contact_no":"1234567890","loyalty_number":"","group_airport_employee":"N","marketing_email_opt_in":"Y"}}
    	$user_resource = createNewUser(array("birthday"=>"06/08/1979","zipcode"=>"10029","marketing_email_opt_in"=>"Y"));
    	$this->assertNull($user_resource->error);
    	$user_id = $user_resource->user_id;
    	$this->assertTrue($user_id > 20000);
    	
    	// now check to see if the records were updated in the user extr data table
    	$ueda = new UserExtraDataAdapter($mimetypes);
    	$record = $ueda->getRecord(array('user_id'=>$user_id));
    	$this->assertEquals("fishbowl", $record['process']);
    }
    
    function testCreateUserWithFishBowlBadData()
    {
    	//{"jsonVal":{"birthday":"06/69","zipcode":"12345","first_name":"Kdj","password":"welcome","last_name":"Djsj","email":"dh@jdj.con","contact_no":"1234567890","loyalty_number":"","group_airport_employee":"N","marketing_email_opt_in":"Y"}}
    	$user_resource = createNewUser(array("birthday"=>"0698","zipcode"=>"10029","marketing_email_opt_in"=>"Y"));
    	$this->assertNotNull($user_resource->error,"Should have gotten an error on save from bad birthday");
    	$this->assertNull($user_resource->user_id);
    	$this->assertEquals(FishBowlService::BIRTHDAY_ERROR_MESSAGE, $user_resource->error);
    	
    	$user_resource = createNewUser(array("birthday"=>"06/08/1979","zipcode"=>"1029","marketing_email_opt_in"=>"Y"));
    	$this->assertNotNull($user_resource->error);
    	$this->assertNull($user_resource->user_id);
    	$this->assertEquals(FishBowlService::ZIPCODE_ERROR_MESSAGE, $user_resource->error);
    }
    
    function testUserUpdateWithGoodFishBowlData()
    {
		$ur = createNewUser($new_user_data);
    	$user = logTestUserIn($ur->user_id);
    	$data = array("birthday"=>"06/08","zipcode"=>"10029","marketing_email_opt_in"=>"Y");
    	$request = new Request();
    	$request->method = "post";
    	$request->data = $data;
    	$user_controller = new UserController($mt, $user, $request,5);
    	$user_resource = $user_controller->updateUser();
    	
    	// now check to see if the records were updated in the user extr data table
    	$ueda = new UserExtraDataAdapter($mimetypes);
    	$record = $ueda->getRecord(array('user_id'=>$user['user_id']));
    	$this->assertEquals("fishbowl", $record['process']);
    	    	
    }
    
    function testUserUpdateWithBadFishBowlData()
    {
		$ur = createNewUser($new_user_data);
    	$user = logTestUserIn($ur->user_id);
    	$data = array("birthday"=>"0608","zipcode"=>"10029","marketing_email_opt_in"=>"Y");
    	$request = new Request();
    	$request->method = "post";
    	$request->data = $data;
    	$user_controller = new UserController($mt, $user, $request,5);
    	$user_resource = $user_controller->updateUser();
    	$this->assertNotNull($user_resource->error);
    	$this->assertEquals(FishBowlService::BIRTHDAY_ERROR_MESSAGE, $user_resource->error);
    	    	
    }
    
    function testCreateUserWIthNOdata()
    {
		$user_resource = createNewUser($data);
    	$this->assertNull($user_resource->error);
    	$user_id = $user_resource->user_id;
    	$this->assertTrue($user_id > 20000);
    	
    	$user = logTestUserIn($user_id);
    	$data = array("first_name"=>"roberts");
    	$request = new Request();
    	$request->method = "post";
    	$request->data = $data;
    	$user_controller = new UserController($mt, $user, $request,5);
    	$user_resource = $user_controller->updateUser();
    	
    	// now check to see if the records were updated in the user extr data table
    	$ueda = new UserExtraDataAdapter($mimetypes);
    	$record = $ueda->getRecord(array('user_id'=>$user_id));
    	$this->assertNull($record);    	
    }
    
    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
     	$_SERVER['log_level'] = 5; 
     	$ids = array();
		$_SERVER['unit_test_ids'] = $ids;
    }
    
	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
    	date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    FishbowlUserCreateTest::main();
}

?>