<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class RefundCreditTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $user;
	var $ids;

	function setUp()
	{		
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		setContext("com.splickit.order");
		$this->ids = $_SERVER['unit_test_ids'];
		$this->user = logTestUserIn($this->ids['user_id']);
	}
	
	function tearDown() 
	{
		//delete your instance
		unset($this->ids);
		unset($this->user);
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->stamp);
    }

	function createOrder()
	{
		$user_resource = createNewUserWithCC();
		$user = logTestUserResourceIn($user_resource);
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'],'pickup','skip hours');
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
		$order_info['user_id'] = $user['user_id'];
		$order_info['order_id'] = $order_resource->order_id;
		return $order_info;
	}

	function testRefundBlockOfOrders()
	{
		$order1_order_info = $this->createOrder();
		$order2_order_info = $this->createOrder();
		$order3_order_info = $this->createOrder();
		$create_table_sql = "CREATE TABLE `smaw_prod`.`xxrefund_data` (`order_id` INT( 11 ) NOT NULL ,`user_id` INT( 11 ) NOT NULL ,UNIQUE (`order_id`)) ENGINE = MYISAM";
		$delete_records = "DELETE FROM xxrefund_data WHERE 1=1";
		$insert_data_sql = "INSERT INTO `xxrefund_data` (`order_id`, `user_id`) VALUES (".$order1_order_info['order_id'].", ".$order1_order_info['user_id']."),(".$order2_order_info['order_id'].", ".$order2_order_info['user_id']."),(".$order3_order_info['order_id'].", ".$order3_order_info['user_id'].")";
		$delete_sql = "DELETE FROM adm_order_reversal  WHERE (adm_order_reversal.`order_id` IN (240805,240803)";
		$aor_adapter = new AdmOrderReversalAdapter($mimetypes);
		$aor_adapter->_query($create_table_sql);
		$aor_adapter->_query($delete_records);
		$aor_adapter->_query($insert_data_sql);
		$aor_adapter->_query($delete_sql);
		$user_controller = new UserController($mt, $u,new Request(), 5);
		$table_name = "xxrefund_data";
		$refund_data = $user_controller->refundBlockOfOrders($table_name);
		$this->assertEquals('We had 3 successful refunds and 0 failures',$refund_data['message']);
	}

    function testRefundOrderMercuryPay()
    {
    	// mercury merchant_id = 102289
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id'],array("no_payment"=>true));
        $merchant_id = $merchant_resource->merchant_id;
//        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
//        $merchant_id = $merchant_resource->merchant_id;
//        $mpta = new MerchantPaymentTypeAdapter($m);
//        $sql = "DELETE FROM Merchant_Payment_Type_Maps WHERE merchant_id = $merchant_id";
//        $mpta->_query($sql);

        $mercury_merchant_id = generateCode(10);
        $mercury_password = "xyz";
        $data['vio_selected_server'] = 'mercury';
        $data['vio_merchant_id'] = $merchant_resource->merchant_id;
        $data['name'] = "Test Billing Entity Mercury";
        $data['description'] = 'An entity to test with';
        $data['merchant_id'] = $mercury_merchant_id;
        $data['password'] = $mercury_password;
        $data['identifier'] = $merchant_resource->alphanumeric_id;
        $data['brand_id'] = $merchant_resource->brand_id;

        $card_gateway_controller = new CardGatewayController($mt, $u, $r);
        $billing_entity_resource = $card_gateway_controller->createPaymentGateway($data);
        $billing_entity_external_id = $billing_entity_resource->external_id;
        $map = BillingEntitiesAdapter::getBillingEntityByExternalId($billing_entity_external_id);
//        $merchant_payment_type_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);

        $mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
        $merchant_payment_map_record = $mptma->getRecord(array("merchant_id"=>$merchant_resource->merchant_id));

    	
		$order_adapter = new OrderAdapter($mimetypes);
		$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','skip hours');
 		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	    	
		$this->assertNotNull($order_resource);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000,"Bad order id of: ".$order_resource->order_id);  
    	
		$order_id = $order_resource->order_id;
		$grand_total = $order_resource->grand_total;
		$order_controller = new OrderController($mt, $this->user, $r,5);
		$order_controller->updateOrderStatusById($order_id,'E');

		// reset created of balance change row to similate refund from yesterday
		$bca = new BalanceChangeAdapter($this->mimetypes);
		$sql = "UPDATE Balance_Change SET created = '".date("Y-m-d H:i:s",time()-(36*3600))."' WHERE order_id = $order_id AND process='CCpayment'";
		$bca->_query($sql);

		$refund_results = $order_controller->issueOrderRefund($order_id, 100.00);
    	//should throw an error since the amount is too much
    	$this->assertEquals('failure',$refund_results['result']);
    	$this->assertEquals("Error! The refund amount, 100, cannot be more than the total amount the card was originally run for: ".$grand_total, $refund_results['message']);
    	
    	// value of zero means do the whole thing
    	$refund_results = $order_controller->issueOrderRefund($order_id, "0.00");
    	$this->assertEquals("success", $refund_results['result']," should have gotten a success but: ".$refund_results['message']);
    	$this->assertEquals(100,$refund_results['response_code']);
    	
    	// check to make sure the order stayed executed
    	$new_order_resource = Resource::find($order_adapter,''.$order_id);
    	$this->assertEquals('E', $new_order_resource->status);
    	
    	// now check the balance change table and the order_reversal table
    	$balance_change_resource = Resource::find(new BalanceChangeAdapter($mimetypes),''.$refund_results['balance_change_id']);
    	$this->assertEquals($expected, $actual);
    	$this->assertEquals($balance_change_resource->charge_amt, $grand_total);
    	$this->assertEquals($balance_change_resource->process, 'CCrefund');
    	$this->assertEquals($balance_change_resource->notes, 'Issuing a VioPaymentService REFUND from the API: ');

		// should have a record becasue this is a refund
    	$adm_reversal_resource = Resource::find(new AdmOrderReversalAdapter($mimetypes),''.$refund_results['order_reversal_id']);
    	$this->assertNotNull($adm_reversal_resource);
    	$this->assertEquals($adm_reversal_resource->order_id, $order_id);
    	$this->assertEquals($adm_reversal_resource->amount, $grand_total);
    	$this->assertEquals($adm_reversal_resource->credit_type, 'G');
    	$this->assertEquals($adm_reversal_resource->note, 'Issuing a VioPaymentService refund from the API: ');
    }

    function testRefundOrderInspirePay()
    {
    	
		$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'],'pickup','skip hours');
 		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	    	
		$this->assertNotNull($order_resource);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000,"Bad order id of: ".$order_resource->order_id);  
    	
		$order_id = $order_resource->order_id;
		$grand_total = $order_resource->grand_total;
		$order_controller = new OrderController($mt, $this->user, $r,5);

    	$refund_results = $order_controller->issueOrderRefund($order_id, 100.00);
    	//should throw an error since the amount is too much
    	$this->assertEquals('failure',$refund_results['result']);
    	$this->assertEquals("Error! The refund amount, 100, cannot be more than the total amount the card was originally run for: ".$grand_total, $refund_results['message']);

    	// shoudl throw an error since we reject partials for open orders
    	$refund_results = $order_controller->issueOrderRefund($order_id, 1.01);
    	$this->assertEquals('failure',$refund_results['result'],"Should have failed due to partial refund on open order");
    	$this->assertEquals("ERROR! This order has not yet executed. Either refund the entire order, or wait until it executes to process the partial refund.", $refund_results['message']);
    	
    	// value of zero means do the whole thing
    	$refund_results = $order_controller->issueOrderRefund($order_id, "0.00");
    	$this->assertEquals("success", $refund_results['result']);
    	$this->assertEquals(100,$refund_results['response_code']);
    	
    	// check to see if order was changed to cancelled
    	$new_order_resource = Resource::find($order_adapter,''.$order_id);
    	$this->assertEquals('C', $new_order_resource->status);
    	
    	// check to see if the messages were cancelled
    	$message_data['order_id'] = $order_id;
    	$message_options[TONIC_FIND_BY_METADATA] = $message_data;
    	$message_resources = Resource::findAll(new MerchantMessageHistoryAdapter($mimetypes),'',$message_options);
		$this->assertTrue(sizeof($message_resources) > 0);
		foreach ($message_resources as $message_resource)
		{
			$this->assertEquals('C', $message_resource->locked);
		}    	

    	// now check the balance change table and the order_reversal table
    	
    	$balance_change_resource = Resource::find(new BalanceChangeAdapter($mimetypes),''.$refund_results['balance_change_id']);
    	$this->assertEquals($expected, $actual);
    	$this->assertEquals($balance_change_resource->charge_amt, $grand_total);
    	$this->assertEquals($balance_change_resource->process, 'CCvoid');
    	$this->assertEquals($balance_change_resource->notes, 'Issuing a VioPaymentService VOID from the API: ');
    	
    	$adm_reversal_resource = Resource::find(new AdmOrderReversalAdapter($mimetypes),''.$refund_results['order_reversal_id']);
    	$this->assertNull($adm_reversal_resource);
    }
    
    function testRefundOrderMercuryPayForMaitreDMerchant()
    {
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
    	$merchant_resource->cc_processor = 'M';
    	$merchant_resource->save();
    	$merchant_id = $merchant_resource->merchant_id;
    	$map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_id,"message_format"=>'WM',"delivery_addr"=>"MatReD","message_type"=>"O"));
    	
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','skip hours');
		$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	    	
		$this->assertNotNull($order_resource);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000,"Bad order id of: ".$order_resource->order_id); 
		$order_id = $order_resource->order_id;
		$grand_total = $order_resource->grand_total;
		
		//now mark all messages as send before doing refund to see if the new MaitreD message get created correctly
		$sql = "UPDATE Merchant_Message_History SET locked = 'S' WHERE order_id = $order_id";
		$order_adapter->_query($sql);
		$sql = "UPDATE Merchant_Message_History SET locked = 'C' WHERE locked = 'N'";
		$order_adapter->_query($sql);	 
    	$new_order_resource = Resource::find($order_adapter,''.$order_id);
    	$new_order_resource->status = 'E';
    	$new_order_resource->save();
		
    	$grand_total_to_merchant = $new_order_resource->grand_total_to_merchant;
    	
		$order_controller = new OrderController($mt, $this->user, $r,5);
		// value of zero means do the whole thing
    	$refund_results = $order_controller->issueOrderRefund($order_id, "0.00");
    	$this->assertEquals("success", $refund_results['result']," should have gotten a success but: ".$refund_results['message']);
    	$this->assertEquals(100,$refund_results['response_code']);

    	// now check to see if the MaitreD refund message was created
    	$this->assertNotNull($order_controller->maitre_d_refund_message_id, "should have the message id fromt the created maitreD message");
    	$maitre_d_message_id = $order_controller->maitre_d_refund_message_id;
    	$maitre_d_refund_message_resource = Resource::find(new MerchantMessageHistoryAdapter($mimetypes),''.$maitre_d_message_id);
    	$this->assertNotNull($maitre_d_refund_message_resource,"Should have found the maitre D refund message");
    	$this->assertEquals("WMR", $maitre_d_refund_message_resource->message_format);
    	$windows_controller = new WindowsServiceController($mt, $u, $r,5);
    	$resource = $windows_controller->prepMessageForSending($maitre_d_refund_message_resource);
		myerror_log("message text: ".$resource->message_text);    
		//verify that the amoutn is negative	
		$this->assertTrue(substr_count($resource->message_text,"<Amount>-".$grand_total_to_merchant."</Amount>") == 1);
		
		$ws_controller = new WindowsServiceController($mt, $u, $r,5);
		$complete_message_resource = $ws_controller->pullNextMessageResourceByMerchant($merchant_resource->alphanumeric_id);
    	$message_id = $complete_message_resource->message_id;
    	$ws_controller->markMessageDeliveredById($message_id);
    	$message_text = $complete_message_resource->message_text;
		$this->assertTrue(substr_count($message_text,"<Amount>-".$grand_total_to_merchant."</Amount>") > 0);

		//sleep(1);
/*		$curl = curl_init("http://localhost:8888/smaw/messagemanager/getnextmessagebymerchantid/319e8v6335l4/windowsservice?log_level=5");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_VERBOSE, 0);	
		$message_text = curl_exec($curl);
		$this->assertTrue(substr_count($message_text,"<Amount>-".$grand_total_to_merchant."</Amount>") > 0);
		curl_close($curl);
*/    	
		$message_resource = Resource::find(new MerchantMessageHistoryAdapter($mimetypes),''.$maitre_d_refund_message_resource->map_id);
    	$this->assertEquals('S', $message_resource->locked);
    }
    


	

    function testAddSplickitCreditNoValue()
    {
    	$user_controller = new UserController($mt, $this->user, $r,5);
    	$return = $user_controller->issueSplickitCredit(null, 'testing', 'here is a test');
		$this->assertEquals('red', $return['error']);
    	$this->assertEquals('Sorry, Refund amount cannot be 0 or null',$return['message']);
    }

    function testAddSplickitCreditBillingSPlickit()
    {
    	$user_id = $this->ids['user_id'];
    	$user_resource = Resource::find(new UserAdapter($mimetypes),"$user_id");
    	$balance_before = $user_resource->balance;
    	$user_controller = new UserController($mt, $this->user, $r,5);
    	$return = $user_controller->issueSplickitCredit('5.00', 'testing', 'here is a test');
    	$this->assertNotNull($return['balance_change_id']);
    	$this->assertEquals($return['error'], 'green');
    	$user_resource = Resource::find(new UserAdapter($mimetypes),"$user_id");
    	$balance_after = $user_resource->balance;
    	$this->assertEquals($balance_before+5.00, $balance_after);
    	$balance_change_id = $return['balance_change_id'];
    	
    	$balance_change_resource = Resource::find(new BalanceChangeAdapter($mimetypes),''.$balance_change_id);
    	$this->assertEquals($balance_change_resource->balance_before, $balance_before);
    	$this->assertEquals($balance_change_resource->charge_amt, 5);
    	$this->assertEquals($balance_change_resource->balance_after, $balance_after);
    	$this->assertEquals($balance_change_resource->process, 'testing');
    	$this->assertEquals($balance_change_resource->notes, 'here is a test');
    	
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

    /* main method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    RefundCreditTest::main();
}

?>