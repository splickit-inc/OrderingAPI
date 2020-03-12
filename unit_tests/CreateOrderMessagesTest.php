<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class CreateOrderMessagesTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
        setProperty('new_shadow_device_on','false');
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
        setProperty('new_shadow_device_on','false');
        setProperty('bypass_portal_message', false);
    }

    function testGetBaseFormat()
    {
        $create_messages_controller = new CreateMessagesController($merchant);
        $this->assertEquals('G',$create_messages_controller->getBaseFormat('GUA'));
    }

    function testIsMessagePulledTypeTrue()
    {
        $create_messages_controller = new CreateMessagesController($merchant);
        $this->assertTrue($create_messages_controller->isPulledType('G'));
        $this->assertTrue($create_messages_controller->isPulledType('H'));
    }

    function testIsMessagePulledTypeFalse()
    {
        $create_messages_controller = new CreateMessagesController($merchant);
        $this->assertFalse($create_messages_controller->isPulledType('E'));
        $this->assertFalse($create_messages_controller->isPulledType('F'));
    }

    function testGetLockedForPulled()
    {
        $create_messages_controller = new CreateMessagesController($merchant);
        $this->assertEquals('P',$create_messages_controller->getLockedForMessage('GUW'));
        $this->assertEquals('P',$create_messages_controller->getLockedForMessage('OIA'));
        $this->assertEquals('P',$create_messages_controller->getLockedForMessage('HUG'));
        $this->assertEquals('P',$create_messages_controller->getLockedForMessage('WAI'));
    }

    function testGetLockedForPushed()
    {
        $create_messages_controller = new CreateMessagesController($merchant);
        $this->assertEquals('N',$create_messages_controller->getLockedForMessage('Econf'));
        $this->assertEquals('N',$create_messages_controller->getLockedForMessage('FUA'));
        $this->assertEquals('N',$create_messages_controller->getLockedForMessage('T'));
    }

    function testShouldCreateShaddowMessagesForOrderId()
    {
        setProperty('new_shadow_message_frequency',5);
        $cmc = new CreateMessagesController($merchant);
        $this->assertTrue($cmc->shouldCreateShaddowMessageForOrderId(100),"should return true since 100 is devisable by 5");
        $this->assertTrue($cmc->shouldCreateShaddowMessageForOrderId(125),"should return true since 125 is devisable by 5");
        $this->assertfalse($cmc->shouldCreateShaddowMessageForOrderId(101),"should return false since 101 is NOT devisable by 5");

    }

    function testGetNextMessageDtTm()
    {
        $cmc = new CreateMessagesController($merchant);
        $this->assertEquals(time()+600,$cmc->calculateNextMessageDtTm(time()+1200,10,0));
    }

    function testGetNextMessageDtTmWithDelay()
    {
        $cmc = new CreateMessagesController($merchant);
        $this->assertEquals(time()+720,$cmc->calculateNextMessageDtTm(time()+1200,10,2));
    }

    function testGetNextMessageDtTmWithImmediateDelivery()
    {
        $merchant['immediate_message_delivery'] = 'Y';
        $cmc = new CreateMessagesController($merchant);
        $this->assertEquals(time(),$cmc->calculateNextMessageDtTm(time()+1200,10,0));
    }

    function testCreateShaddowMessagesForOrder()
    {
        $cmc = new CreateMessagesController($merchant);
        setProperty('new_shadow_device_on','true');
        $id = $cmc->createShaddowMessages(1234500);
        $this->assertTrue($id > 0);
        $message = MerchantMessageHistoryAdapter::staticGetRecordByPrimaryKey($id,"MerchantMessageHistoryAdapter");
        $this->assertEquals(getProperty('new_shadow_message_type'),$message['message_format']);

    }

    function testCreateOrderMessage()
    {
        setProperty('bypass_portal_message', true);
        $create_messages_controller = new CreateMessagesController(null);
        $mmm_adapter = new MerchantMessageMapAdapter(getM());
        $mmms = $mmm_adapter->getRecords(array("merchant_id"=>$this->ids['merchant_id']));
        $mmm = $mmms[0];
        $pickup_ts = time()+3600;
        $id = $create_messages_controller->createTheOrderMessage(123456,$mmm,20,$pickup_ts);
        $this->assertTrue($id > 1000,"SHould have created a good message id but didn't");
        $expected_next_message_dt_tm = $pickup_ts-1200;
        $message = MerchantMessageHistoryAdapter::staticGetRecordByPrimaryKey($id,'MerchantMessageHistory');
        $this->assertEquals($expected_next_message_dt_tm,$message['next_message_dt_tm']);
    }

    function testCreateOrderMessagesShorterLeadTime()
    {
        setProperty('bypass_portal_message', true);
        $order_id = rand(1111111,9999999);
        $create_messages_controller = new CreateMessagesController(null);
        $pickup_ts = time()+3600;
        $create_messages_controller->createOrderMessagesFromOrderInfo($order_id,$this->ids['merchant_id'],10,$pickup_ts);
        $mmha = new MerchantMessageHistoryAdapter(getM());
        $message = $mmha->getRecord(array("order_id"=>$order_id));
        $this->assertNotNull($message,"Should have found a message");
        $expected_next_message_dt_tm = $pickup_ts-600;
        $this->assertEquals($expected_next_message_dt_tm,$message['next_message_dt_tm']);
    }

    function testCreateOrderMessagesFromPlaceOrder()
    {
        $the_time = getTomorrowTwelveNoonTimeStampDenver();
        //$merchant_resource = createNewTestMerchant($this->ids['simple_menu_id']);
        //$merchant_id = $merchant_resource->merchant_id;
        $merchant_id = $this->ids['merchant_id'];
        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'the note');
        $checkout_resource = getCheckoutResourceFromOrderData($order_data,$the_time);



        $actual_pickup_ts = $the_time+(35*60);
        $pickup_time_string = date('Y-m-d H:i:00',$actual_pickup_ts);
        $checkout_resource->lead_times_array = array($actual_pickup_ts);
        $response = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$the_time);

        //$response = placeOrderFromOrderData($order_data,$the_time);
        $order_id = $response->order_id;
        $order_resource = SplickitController::getResourceFromId($order_id, "Order");
        $this->assertEquals($pickup_time_string, $order_resource->pickup_dt_tm);

        // now check to see the X message was scheduled correctly
        $mmha = new MerchantMessageHistoryAdapter($mimetypes);
        $order_messages = $mmha->getAllOrderMessages($order_id);
        foreach ($order_messages as $message_resource) {
            if ($message_resource->message_type == 'X')	{
                $order_message_resource = $message_resource;
            }
        }
        $this->assertNotNull($order_message_resource);
        $this->assertEquals($merchant_id,$order_message_resource->merchant_id);

        $scheduled_string = date('Y-m-d H:i:s',$order_message_resource->next_message_dt_tm);
        $expected_scheduled_string = date('Y-m-d H:i:s',$actual_pickup_ts-1200);
        $actual_scheduled_string = date('Y-m-d H:i:s',$order_message_resource->next_message_dt_tm);
        $this->assertEquals($expected_scheduled_string,$actual_scheduled_string);
        $this->assertTrue(($actual_pickup_ts-1198 > $order_message_resource->next_message_dt_tm) && ($order_message_resource->next_message_dt_tm > $actual_pickup_ts-1202),"Should have been scheduled at $expected_scheduled_string but was scheduled at $actual_scheduled_string");

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
    CreateOrderMessagesTest::main();
}

?>