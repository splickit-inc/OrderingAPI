<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class CCFraudTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }

	function testGetNumberOfUpdatesFromWebupdate()
	{
		$user_resource = createNewUser();
		$user_resource->device_id = '';
		$user_resource->save();
		$ccuta = new CreditCardUpdateTrackingAdapter($m);
		$this->assertEquals(0,count($ccuta->getCardUpdatesInLastTimePeriod($user_resource->user_id,$user_resource->device_id)),"should have 0 cc updates");

		CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($user_resource->user_id,$user_resource->device_id,'1234');
		$this->assertEquals(1,count($ccuta->getCardUpdatesInLastTimePeriodByUserResource($user_resource)),"should have 1 cc updates");
		$this->assertEquals(1,count($ccuta->getNumberOfDifferentCardUpdatesInLastTimePeriodByUserResource($user_resource)),"should have been 1 card");
	}


	function testGetNumberOfUpdatesInLastTimePeriod()
	{
		$user_resource = createNewUser();
		CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($user_resource->user_id,$user_resource->device_id,'1234');
		CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($user_resource->user_id,$user_resource->device_id,'1234');
		CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($user_resource->user_id,$user_resource->device_id,'1234');
		CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($user_resource->user_id,$user_resource->device_id,'1234');

		$ccuta = new CreditCardUpdateTrackingAdapter($m);
		$this->assertEquals(4,count($ccuta->getCardUpdatesInLastTimePeriodByUserResource($user_resource)),"should have 4 cc updates");
		$this->assertEquals(1,count($ccuta->getNumberOfDifferentCardUpdatesInLastTimePeriodByUserResource($user_resource)),"should have been only 1 card though");
	}

	function testGetNumberOfUpdatesInLastTimePeriodDifferentCards()
	{
		$user_resource = createNewUser();
		CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($user_resource->user_id,$user_resource->device_id,rand(1112,9999));
		CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($user_resource->user_id,$user_resource->device_id,rand(1112,9999));
		CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($user_resource->user_id,$user_resource->device_id,rand(1112,9999));
		CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($user_resource->user_id,$user_resource->device_id,rand(1112,9999));

		$ccuta = new CreditCardUpdateTrackingAdapter($m);
		$this->assertEquals(4,$ccuta->getNumberOfDifferentCardUpdatesInLastTimePeriodByUserResource($user_resource));
	}

	function testGetNumberOfUpdatesInLastTimePeriodDifferentCardsButMoreInPast()
	{
		$user_resource = createNewUser();
		$user_id = $user_resource->user_id;
		CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($user_resource->user_id,$user_resource->device_id,rand(1112,9999));
		CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($user_resource->user_id,$user_resource->device_id,rand(1112,9999));
		CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($user_resource->user_id,$user_resource->device_id,rand(1112,9999));

		//now add one that is 2 hours old
		$ccuta = new CreditCardUpdateTrackingAdapter($m);
		$two_hours_ago = date('Y-m-d H:i:s',time()-(2*3600));
		$sql = "INSERT INTO Credit_Card_Update_Tracking (`user_id`,`device_id`,`last_four`,`created`) VALUES ($user_id,'".$user_resource->device_id."','".rand(1112,9999)."','$two_hours_ago')";
		$r = $ccuta->_query($sql);

		//now add 2 more over 24hrs old
		$sql = "INSERT INTO Credit_Card_Update_Tracking (`user_id`,`device_id`,`last_four`,`created`) VALUES ($user_id,'".$user_resource->device_id."','".rand(1112,9999)."','2016-01-02 12:00:00')";
		$r = $ccuta->_query($sql);
		$sql = "INSERT INTO Credit_Card_Update_Tracking (`user_id`,`device_id`,`last_four`,`created`) VALUES ($user_id,'".$user_resource->device_id."','".rand(1112,9999)."','2016-01-02 12:20:00')";
		$r = $ccuta->_query($sql);
		$records = $ccuta->getRecords(array("user_id"=>$user_id));
		$this->assertCount(6,$records);


		$number_of_dif_in_last_24_hours = $ccuta->getNumberOfDifferentCardUpdatesInLastTimePeriodByUserResource($user_resource);
		$this->assertEquals(3,$number_of_dif_in_last_24_hours);
	}


	function testGetDeviceIdForBlackListNoDeviceId()
	{
		$user_resource = createNewUser();
		$user_resource->device_id = '';
		$user_resource->save();
		$dbla = new DeviceBlacklistAdapter($m);
		$this->assertEquals('userid-'.$user_resource->user_id,$dbla->getDeviceIdFromUserResourceForBlackList($user_resource));
	}

	function testGetDeviceIdForBlackList()
	{
		$user_resource = createNewUser();
		$user_resource->device_id = '1234-qwer-254-wret';
		$user_resource->save();
		$dbla = new DeviceBlacklistAdapter($m);
		$this->assertEquals('1234-qwer-254-wret',$dbla->getDeviceIdFromUserResourceForBlackList($user_resource));
	}

	function testAddUserToBlackList()
	{
		$user_resource = createNewUser();
		$this->assertFalse(DeviceBlacklistAdapter::isUserResourceOnBlackList($user_resource));
		DeviceBlacklistAdapter::addUserResourceToBlackList($user_resource);
		$dbla = new DeviceBlacklistAdapter($m);
		$record = $dbla->getBlackListRecordByUserResource($user_resource);
		$this->assertEquals($user_resource->device_id,$record['device_id']);

		$this->assertTrue(DeviceBlacklistAdapter::isUserResourceOnBlackList($user_resource));
	}


	function testAddUserToBlacklistFromRequest()
	{
		$created_user_resource = createNewUser();
		// the 'rand' token with set an random last 4 on the cc mock save. see lib\mocks\viopaymentcurl.php  at line 100 approx
		$created_user_resource->uuid = "12345-abcde-67890-rand";
		$created_user_resource->save();
		$user_id = $created_user_resource->user_id;

		//add 3 records with different cards in short time frame
		CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($created_user_resource->user_id,$created_user_resource->device_id,rand(1112,9999));
		CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($created_user_resource->user_id,$created_user_resource->device_id,rand(1112,9999));
		CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($created_user_resource->user_id,$created_user_resource->device_id,rand(1112,9999));

		//now add one that is 2 hours old
		$ccuta = new CreditCardUpdateTrackingAdapter($m);
		$two_hours_ago = date('Y-m-d H:i:s',time()-(2*3600));
		$sql = "INSERT INTO Credit_Card_Update_Tracking (`user_id`,`device_id`,`last_four`,`created`) VALUES ($user_id,'".$created_user_resource->device_id."','".rand(1112,9999)."','$two_hours_ago')";
		$r = $ccuta->_query($sql);


		//now add 2 more over 24hrs old
		$sql = "INSERT INTO Credit_Card_Update_Tracking (`user_id`,`device_id`,`last_four`,`created`) VALUES ($user_id,'".$created_user_resource->device_id."','".rand(1112,9999)."','2016-01-02 12:00:00')";
		$r = $ccuta->_query($sql);
		$sql = "INSERT INTO Credit_Card_Update_Tracking (`user_id`,`device_id`,`last_four`,`created`) VALUES ($user_id,'".$created_user_resource->device_id."','".rand(1112,9999)."','2016-01-02 12:20:00')";
		$r = $ccuta->_query($sql);

		$records = $ccuta->getRecords(array("user_id"=>$user_id));
		$this->assertCount(6,$records);

		$user = logTestUserResourceIn($created_user_resource);
		$starting_email = $user['email'];
		$data = array ('credit_card_saved_in_vault'=>true);
		$request = new Request();
		$request->data = $data;
		$user_controller = new UserController($mt, $user, $request,5);
		$user_resource = $user_controller->updateUser();
		$this->assertNull($user_resource->error);
		$this->assertEquals('1C21000001',$user_resource->flags);
		$this->assertFalse(DeviceBlacklistAdapter::isUserResourceOnBlackList($created_user_resource));


		$request = new Request();
		$request->data = $data;
		$user_controller = new UserController($mt, $user, $request,5);
		$user_resource = $user_controller->updateUser();
		$this->assertTrue(DeviceBlacklistAdapter::isUserResourceOnBlackList($created_user_resource),'User Should have been added to the black list');
		$user_record = UserAdapter::staticGetRecord(array("user_id"=>$user_resource->user_id),'UserAdapter');
		$this->assertNotNull($user_record);
		$this->assertEquals($starting_email,$user_record['email']);
		return $user['user_id'];
	}

	/**
	 * @depends testAddUserToBlacklistFromRequest
	 */
	function testDoNotAllowAuthenticationForBlacklistedUserAuthenticationToken($user_id)
	{
		$authentication_token_resource = createUserAuthenticationToken($user_id);

		$request_all['splickit_authentication_token'] = $authentication_token_resource->token;
		$loginAdapter = new LoginAdapter($mimetypes);
		$this->assertFalse($loginAdapter->doAuthorizeWithSpecialUserValidation($email, $password,$request_all),"Should not have allowed a black listed user to authenticate");
	}

	/**
	 * @depends testAddUserToBlacklistFromRequest
	 */
	function testDoNotAllowAuthenticationForBlacklistedUser($user_id)
	{
		$loginAdapter = new LoginAdapter($mimetypes);
		$this->assertFalse($loginAdapter->doAuthorizeWithSpecialUserValidation($user_id,'welcome',$request_all),"Should not have allowed a black listed user to authenticate");
	}


	/**
	 * @depends testAddUserToBlacklistFromRequest
	 */
	function testCheckBlackListedDeviceId($user_id)
	{
		$user_record = UserAdapter::staticGetRecordByPrimaryKey($user_id,'User');
		$device_id = $user_record['device_id'];

		$this->assertFalse(DeviceBlacklistAdapter::isDeviceIdOnBlackList('234567'),"Device shoul NOT be on teh black list");
		$this->assertTrue(DeviceBlacklistAdapter::isDeviceIdOnBlackList($device_id),"Device should be on the black list");
	}

	/**
	 * @depends testAddUserToBlacklistFromRequest
	 */
	function testDoNotAllowAuthenticationForBlacklistedDeviceId($user_id)
	{
		$user_record = UserAdapter::staticGetRecordByPrimaryKey($user_id,'User');
		$device_id = $user_record['device_id'];

		$user_resource = createNewUser();
		$user_resource->device_id = $device_id;
		$user_resource->save();
		$user_id = $user_resource->user_id;

		$loginAdapter = new LoginAdapter($mimetypes);
		$this->assertFalse($loginAdapter->doAuthorizeWithSpecialUserValidation($user_id,'welcome',$request_all),"Should not have allowed a black listed device id to authenticate");
	}

	/**
	 * @depends testAddUserToBlacklistFromRequest
	 */
	function testRemoveUserFromBlackListAndThenAllowLogin($user_id)
	{
		$ua = new UserAdapter($m);
		$user_resource = Resource::find($ua,"$user_id",$options);
		$user = logTestUserResourceIn($user_resource);
		$request = new Request();
		$request->data = array("email"=>$user_resource->email);
		$user_controller = new UserController($mt, $user, $request);
		$this->assertTrue($user_controller->undoBlacklisted());
		$new_user_resource = $ua->getUserResourceFromId($user['user_id']);
		$this->assertEquals('1000000001',$new_user_resource->flags);

		$loginAdapter = new LoginAdapter($mimetypes);
		$login_result = $loginAdapter->doAuthorizeWithSpecialUserValidation($user_id,'welcome',$request_all);
		$this->assertTrue($login_result->_exists,"should have gotten back a valid user resource");

		// allow new user with device id
		$device_id = $user['device_id'];

		$user_resource = createNewUser();
		$user_resource->device_id = $device_id;
		$user_resource->save();

		$loginAdapter = new LoginAdapter($mimetypes);
		$resource = $loginAdapter->doAuthorizeWithSpecialUserValidation($user_resource->user_id,'welcome',$request_all);
		$this->assertTrue($resource->_exists,"should have gotten back a valid user resource");

	}





		static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
		setProperty('cc_fraud_time_period_in_hours',1,true);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;

    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;
		for ($i=0;$i<5;$i++){
			$user_resource = createNewUser();
			CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($user_resource->user_id,$user_resource->device_id,rand(1211,9999));
		}
		for ($i=0;$i<5;$i++){
			$user_resource = createNewUser();
			$user_resource->device_id = '';
			$user_resource->save();
			CreditCardUpdateTrackingAdapter::recordCreditCardUpdate($user_resource->user_id,$user_resource->device_id,rand(1211,9999));
		}
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
	CCFraudTest::main();
}

?>