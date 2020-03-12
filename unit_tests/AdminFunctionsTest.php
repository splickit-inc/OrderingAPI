<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class AdminFunctionsTest extends PHPUnit_Framework_TestCase
{
	var $stamp;

	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		if (sizeof($_SERVER['unit_test_ids']) < 1)
			$_SERVER['unit_test_ids'] = $this->setUpBeforeClass();

		setContext("com.splickit.order");
		
		// we dont want to call to inspirepay 
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		
		$user_id = $_SERVER['unit_test_ids']['user_id'];
		$user = SplickitController::getResourceFromId($user_id, "User");
		$_SERVER['AUTHENTICATED_USER'] = $user->getDataFieldsReally();
    	$_SERVER['AUTHENTICATED_USER_ID'] = $user->user_id;
		$this->user = $user->getDataFieldsReally();
    	$this->merchant_id = $_SERVER['unit_test_ids']['merchant_id'];
		$this->menu_id = createTestMenuWithNnumberOfItems(1);
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->user_id);
		unset($this->merchant_id);
    }
    
    function testDeleteUserByUserId()
    {
    	$new_user_resource = createNewUser(array("flags"=>"1C20000001"));
    	
    	$user_resource = UserAdapter::doesUserExist($new_user_resource->email);
    	$this->assertNotNull($user_resource);
    	$this->assertEquals("1C20000001", $user_resource->flags);
    	
    	$user_controller = new UserController($mt, $u, $r,5);
    	$result = $user_controller->logicalyDeleteUserResource($user_resource);
    	$this->assertTrue($result);

    	$user_resource = UserAdapter::doesUserExist($new_user_resource->email);
    	$this->assertFalse($user_resource);
    	
    	$user_adapter = new UserAdapter(getM());
    	$record = $user_adapter->getRecord(array("user_id"=>$new_user_resource->user_id,"logical_delete"=>'Y'));
    	$this->assertNotNull($record,"should have found the user record");
    	$this->assertEquals("1000000001", $record['flags']);
    	$this->assertEquals(strtolower("deleted-".getRawStamp().'-'.$new_user_resource->email), $record['email']);
    	
    }

    function testDeleteUserFromRequest()
    {
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	//$user = $user_resource->getDataFieldsReally();
    	
    	$request = new Request();
    	//$request->data['email'] = "somebademail@dummy.com";
    	
    	$user_controller = new UserController($mt, $user, $request, 5);
    	$resource = $user_controller->adminLandingLogicallyDeleteUser();
    	$this->assertEquals("please enter an email address", $resource->message);
    	$this->assertEquals("green",$resource->error);
    	
    	$request->data['email'] = "somebademail@dummy.com";
    	
    	$user_controller = new UserController($mt, $user, $request, 5);
    	$resource = $user_controller->adminLandingLogicallyDeleteUser();
    	$this->assertEquals("NO MATCHING USER FOUND", $resource->message);
    	$this->assertEquals("red",$resource->error);
    	
    	$request->data['email'] = $user_resource->email;
    	$user_controller2 = new UserController($mt, $user, $request, 5);
    	$resource2 = $user_controller2->adminLandingLogicallyDeleteUser();
    	$this->assertEquals("The Users Record Has Been Deleted", $resource2->message);
    	$this->assertEquals("green",$resource2->error);
    }

	function testReassignOrderFailure()
	{
		$user_resource = createNewUserWithCC();
		$user = logTestUserResourceIn($user_resource);
		$menu_id = $this->menu_id;
		$merchant_resource = createNewTestMerchant($menu_id);
		$merchant_id = $merchant_resource->merchant_id;

		// create a new merchant to reassign to
		$new_merchant_resource = createNewTestMerchant($menu_id);
		$new_merchant_id = $new_merchant_resource->merchant_id;

		$order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id,'pickup','skip hours');
		$order_resource = placeOrderFromOrderData($order_data,$time);
		$this->assertNull($order_resource->error);
		$order_id = $order_resource->order_id;
		$this->assertTrue($order_id > 1000);

        $sql = "DELETE FROM Merchant_Payment_Type_Maps WHERE merchant_id = $merchant_id";
        $ma = new MerchantAdapter($m);
        $ma->_query($sql);

        $order_controller = new OrderController($mt, $user, $r, 5);
		$this->assertFalse($order_controller->reassignAndSendOrder($order_id, $new_merchant_id),"Order re-assign shoujdl have come back false");
		$this->assertEquals('Original Merchant is not a Sage Merchant. Reassign can only be done for sage',$order_controller->getReassignError());

	}

    function testReassignAndSendOrder()
    {
		$user_resource = createNewUserWithCC();
		$user = logTestUserResourceIn($user_resource);
		$menu_id = $this->menu_id;
		$merchant_resource = createNewTestMerchant($menu_id,array("new_payment"=>true));
		$merchant_id = $merchant_resource->merchant_id;

    	// create a new merchant to reassign to
    	$new_merchant_resource = createNewTestMerchant($menu_id,array("new_payment"=>true));
    	$new_merchant_id = $new_merchant_resource->merchant_id;

		$map_resource = Resource::createByData(new MerchantMessageMapAdapter(getM()),array("merchant_id"=>$new_merchant_id,"message_format"=>'HUA',"delivery_addr"=>"8888888888","message_type"=>"O","info"=>"firmware=7.0"));
    	
    	$order_adapter = new OrderAdapter(getM());

        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'Pickup', 'skip hours');
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);

        $this->assertTrue($order_resource->order_id > 1000,"should have created an order by got an error: ".$order_resource->error);
    	$order_id = $order_resource->order_id;
    	
    	// now get created messages
    	$mmha = new MerchantMessageHistoryAdapter(getM());
    	$records = $mmha->getRecords(array("order_id"=>$order_id));
    	$this->assertTrue(sizeof($records, $mode) > 0);
    	
    	$order_controller = new OrderController($mt, $user, $r, 5);
    	$order_controller->reassignAndSendOrder($order_id, $new_merchant_id);
    	
    	/******* now verify all the changes *******/

    	// check to see that message are cancelled
    	foreach ($records as $old_message_record)
    	{
    		$old_message_resource = SplickitController::getResourceFromId($old_message_record['map_id'], "MerchantMessageHistory");
    		$this->assertEquals("C", $old_message_resource->locked,"message should have been set to cancelled since we ressigned the order");
    	}
    	
    	// check to see that order record was re-assigned
    	$order_resource_after_reassignement = SplickitController::getResourceFromId($order_id, "Order");
    	$this->assertEquals($new_merchant_id, $order_resource_after_reassignement->merchant_id);
    	
    	// check to see that new messages where created
    	$new_message_records = $mmha->getRecords(array("order_id"=>$order_id,"merchant_id"=>$new_merchant_id));
    	$this->assertTrue(sizeof($new_message_records, $mode) > 0);
    	foreach($new_message_records as $new_message_record)
    	{
    		$this->assertEquals($new_merchant_id,$new_message_record['merchant_id']);
    		if ($new_message_record['message_format'] == 'E'){
				$email_record = $new_message_record;
			} else if ($new_message_record['message_format'] == 'HUA'){
				$gprs_record = $new_message_record;
			}

    	}
    	$this->assertNotNull($email_record);
    	$expected = ''.$new_merchant_id.'dummy@dummy.com';
    	$this->assertEquals($expected, $email_record['message_delivery_addr']);
		$this->assertEquals('N',$email_record['locked']);

		$this->assertNotNull($gprs_record);
		$this->assertEquals('8888888888',$gprs_record['message_delivery_addr']);
		$this->assertEquals('P',$gprs_record['locked']);

    	
    }
    
    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);

    	//SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;

    	$_SERVER['request_time1'] = microtime(true);    	
		$merchant_resource = createNewTestMerchant();
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
    	//map it to a menu
    	$menu_id = createTestMenuWithOneItem("Test Item 1");
    	MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $menu_id, 'pickup');
    	
    	$user_resource = createNewUser();
    	$user_resource->flags = '1C00000001';
    	$user_resource->save();
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
    AdminFunctionsTest::main();
}

?>