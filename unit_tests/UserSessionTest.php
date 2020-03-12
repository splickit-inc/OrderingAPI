<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class UserSessionTest extends PHPUnit_Framework_TestCase
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

    function testUpdateUserRecordWithDifferentDeviceId()
    {
    	setContext('com.splickit.moes');
    	$user_resource = createNewUser(array('app_version'=>1000));
    	$user = logTestUserResourceIn($user_resource);
    	$original_device_id = $user_resource->device_id;
    	
    	//now set the device id to something else
    	$_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'] = '8888888888';
		
    	$usersession_controller = new UsersessionController($mt, $user, $r, 6);
		$usersession_controller->updateDeviceInfoForUserResource($user_resource);

		$user2 = UserAdapter::staticGetRecord(array("user_id"=>$user['user_id']),'UserAdapter');
		$this->assertEquals('8888888888',$user2['device_id']);
    }
    
    function testDoNotUpdateIfFromWeb()
    {
    	setContext('com.splickit.moes');
    	$user_resource = createNewUser(array('app_version'=>10000));
    	$user = logTestUserResourceIn($user_resource);
    	$original_device_id = $user_resource->device_id;
    	
    	//now set the device id to something else
    	$_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'] = "somedumdeviceid";
    	$_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'] = 'web';
		
    	$usersession_controller = new UsersessionController($mt, $user, $r, 6);
		$usersession_controller->updateDeviceInfoForUserResource($user_resource);

		$user2 = UserAdapter::staticGetRecord(array("user_id"=>$user['user_id']),'UserAdapter');
		$this->assertEquals($original_device_id,$user2['device_id']);
    	
    }
    
    function testUpdateEmailOnTempUserRecordWithNewDeviceId()
    {
		$user_resource = createNewUser(array('app_version'=>1000));
		$user_resource->first_name = 'SpTemp';
    	$user_resource->last_name = 'User';
    	$user_resource->email = $user_resource->device_id.'@splickit.dum';
    	$user_resource->save();
    	$user = logTestUserResourceIn($user_resource);
    	$this->assertTrue(isLoggedInUserATempUser());
    	
    	$_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'] = '8888888888';
		
    	$usersession_controller = new UsersessionController($mt, $user, $r, 6);
		$usersession_controller->updateDeviceInfoForUserResource($user_resource);

		$user2 = UserAdapter::staticGetRecord(array("user_id"=>$user['user_id']),'UserAdapter');
		$this->assertEquals('8888888888',$user2['device_id']);
		$this->assertEquals('8888888888@splickit.dum', $user2['email']);
    	
    }
    
    function testUserSessionWithHackedThisSorryDoNotUpdateDeviceId()
    {
		setContext("com.splickit.order");
		$user_resource = createNewUser();
		$original_device_id = $user_resource->device_id;
		$user = logTestUserResourceIn($user_resource);
		
		$_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'] = 'hackedthissorry';
		$user_session_controller = new UsersessionController($mt, $user, $r, 5);
		$user_session_resource = $user_session_controller->getUserSession($user_resource);
		
		$update_user_resource = SplickitController::getResourceFromId($user_resource->user_id, 'User');
		$this->assertEquals($original_device_id, $update_user_resource->device_id);	    	
    }
    
    function testUserSessionUpdateDeviceId()
    {
		setContext("com.splickit.order");
		$user_resource = createNewUser();
		$original_device_id = $user_resource->device_id;
		$user = logTestUserResourceIn($user_resource);
		
		$code = generateCode(20);
		$_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'] = "$code";
		$user_session_controller = new UsersessionController($mt, $user, $r, 5);
		$user_session_resource = $user_session_controller->getUserSession($user_resource);
		
		$update_user_resource = SplickitController::getResourceFromId($user_resource->user_id, 'User');
		$this->assertEquals($code, $update_user_resource->device_id);	    	
    }
    
    function testUserSessionForAdminUser()
    {
    	setContext("com.splickit.order");
		$user_id = $user_resource->user_id;
		$user = logTestUserIn(2);
		$user_session_controller = new UsersessionController($mt, $user, $r, 5);
		$new_user_resource = UserAdapter::getUserResourceFromId(2);
		$user_session = $user_session_controller->getUserSession($new_user_resource);    
		$this->assertNull($user_session->error);	
    }
    	    
//    function testAssignLoyaltyToRealUserFromTemp()
//    {
//    	setContext("com.splickit.jerseymikes");
//    	$_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'] = "88888-99999";
//		$_SERVER['REQUEST_METHOD'] = 'POST';
//    	$loyalty_number = '1234567890';
//    	$user_resource = createNewUser(array("loyalty_number"=>$loyalty_number,"loyalty_phone_number"=>$loyalty_number));
//    	$user_resource->device_id = "88888-99999";
//    	$user_resource->first_name = 'SpTemp';
//    	$user_resource->last_name = 'User';
//    	$user_resource->email = str_ireplace('@dummy.com', '@splickit.dum', $user_resource->email);
//    	$user_resource->save();
//
//    	$ubl_adapter = new UserBrandPointsMapAdapter($mimetypes);
//    	$record = $ubl_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
//    	$this->assertNotNull($record);
//    	$created_loyalty_number = $record['loyalty_number'];
//
//    	// now create a real user with the same device id
//    	// now do a get usersession with the newly created user
//    	$user_resource2 = createNewUser(array("device_id"=>"88888-99999"));
//    	$ubl_adapter = new UserBrandPointsMapAdapter($mimetypes);
//    	$record = $ubl_adapter->getRecord(array("user_id"=>$user_resource2->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
//    	$this->assertNull($record);
//
//    	$user = logTestUserIn($user_resource2->user_id);
//    	$user_session_controller = new UserSessionController($mt, $user, $r,5);
//    	$user_session_resource = $user_session_controller->getUserSession($user_resource2);
//    	$this->assertNotNull($user_session_resource->brand_loyalty);
//    	$brand_loyalty = $user_session_resource->brand_loyalty;
//    	$this->assertEquals("$created_loyalty_number", $brand_loyalty['loyalty_number']);
//
//    	$record2 = $ubl_adapter->getRecord(array("user_id"=>$user_resource2->user_id,"brand_id"=>$brand_id));
//    	$this->assertNotNull($record2);
//    	$this->assertEquals("$created_loyalty_number", $record2['loyalty_number']);
//    }
    
    function testZeroFillOfLastFour()
    {
    	setContext("com.splickit.order");
    	$user_resource = createNewUser();
    	$user_id = $user_resource->user_id;
    	$user_resource->flags = "1C20000001";
    	$user_resource->last_four = "0007";
    	$user_resource->save();
    	//$user = $user_resource->getDataFieldsReally();
    	
    	$user = $user_resource->getDataFieldsReally();
    	$user_session_controller = new UsersessionController($mt, $user, $r,5);
    	$new_user_resource = UserAdapter::getUserResourceFromId($user_id);
    	$user_session = $user_session_controller->getUserSession($new_user_resource);
		$last_four_as_string = (string)$user_session->last_four;
		$this->assertEquals(4,strlen($last_four_as_string));
    }

    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	
    	$skin_resource = createWorldHqSkin();
    	$ids['skin_id'] = $skin_resource->skin_id;
    	
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;
    	
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);

    	$merchant_resource = createNewTestMerchant($menu_id);
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;
    	
    	$_SERVER['log_level'] = 5;
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
    UserSessionTest::main();
}

?>
