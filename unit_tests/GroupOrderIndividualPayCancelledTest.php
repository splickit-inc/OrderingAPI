<?php
ini_set('max_execution_time', 300);
$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class GroupOrderIndividualPayCancelledTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $user;
    var $merchant_id;
    var $merchant;
    var $users;
    var $ids;

    function setUp()
    {
        // we dont want to call to inspirepay
        setContext("com.splickit.gotwo");
        $_SERVER['SKIN']['show_notes_fields'] = true;
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'] = '100.0.0';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__ . '-' . $_SERVER['STAMP'];
        $this->users = $_SERVER['users'];
        $this->ids = $_SERVER['unit_test_ids'];
        $user_id = $_SERVER['unit_test_ids']['user_id'];
        $user_resource = SplickitController::getResourceFromId($user_id, 'User');
        $this->user = $user_resource->getDataFieldsReally();

        $this->merchant_id = $_SERVER['unit_test_ids']['merchant_id'];
        $this->merchant = SplickitController::getResourceFromId($this->merchant_id, 'Merchant');
        setProperty('do_not_call_out_to_aws', 'true');
    }

    function tearDown()
    {
        $_SERVER['STAMP'] = $this->stamp;
        setProperty('do_not_call_out_to_aws', 'true');
    }

    function testCreateGroupOrderPickup()
    {
        $note = "sum dum note";
        $emails = "sumdumemail1@dummy.com,sumdumemail2@dummy.com,sumdumemail3@dummy.com,sumdumemail4@dummy.com";
        $user = logTestUserIn($this->users[2]);

        $request = new Request();
        $request->data = array("merchant_id" => $this->merchant_id, "note" => $note, "participant_emails" => $emails, "group_order_type" => 2);
        $request->data['submit_at_ts'] = getTomorrowTwelveNoonTimeStampDenver() + 900;
        $request->url = "app2/apiv2/grouporders";
        $request->method = "POST";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $group_order_controller->current_time = getTomorrowTwelveNoonTimeStampDenver();
        $resource = $group_order_controller->processV2Request();
        $this->assertNull($resource->error);
        $this->assertNotNull($resource);

        $this->assertNotNull($resource->group_order_token);
        $this->assertTrue($resource->group_order_id > 1000, "shouljd have a group order id");
        $this->assertEquals($user['user_id'], $resource->admin_user_id);

        $group_order_adapter = new GroupOrderAdapter($mimetypes);
        $group_order_record = $group_order_adapter->getRecordFromPrimaryKey($resource->group_order_id);

        $activity_id = $resource->auto_send_activity_id;
        $this->assertTrue($activity_id>1000,"should have found a valid activity id");
        $group_order_record['activity_id'] = $activity_id;
        $group_order_record['order_id'] = $resource->order_id;

        return $group_order_record;
    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testAddToGroupOrderUsingCart($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $group_order_id = $group_order_record['group_order_id'];

        $user_resource = createNewUserWithCC();
        $user_resource->first_name = 'Rob';
        $user_resource->last_name = 'Tombie';
        $user_resource->flags = '1C20000001';
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id']);
        $cart_note = "sum dum cart note";
        $order_data['note'] = $cart_note;
        $order_data['group_order_token'] = $group_order_token;

        $cart_resource = $this->addtoCart($user,$order_data);

        $ucid = $cart_resource->ucid;
        $checkout_data_resource = $this->getCheckoutForGroupOrder($user,$ucid,$group_order_token,getTomorrowTwelveNoonTimeStampDenver()-1800);
        $order_resource = $this->placeOrderToBePartOfTypeTwoGroupOrder($user,$this->ids['merchant_id'],$ucid,$group_order_token,$checkout_data_resource,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);

    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testAdminAddOrderToGroupOrder($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $group_order_id = $group_order_record['group_order_id'];

        $user = logTestUserIn($this->users[2]);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'pickup','another note',2);
        $cart_note = "sum dum cart note";
        $order_data['note'] = $cart_note;
        $order_data['group_order_token'] = $group_order_token;

        $order_resource = $this->addToGroupOrderFullProcess($user,$this->ids['merchant_id'],$order_data,$group_order_token,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);

        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_id),'GroupOrderIndividualOrderMapsAdapter');
        $this->assertCount(2,$records,"there should have been two records in the group order map table");
    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testAThirdOrderToGroupOrder($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $group_order_id = $group_order_record['group_order_id'];

        $user_resource = createNewUserWithCC();
        $user_resource->first_name = 'third';
        $user_resource->last_name = 'group guy';
        $user_resource->flags = '1C20000001';
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id'],'pickup','the third note',2);
        $cart_note = "sum dum third note";
        $order_data['note'] = $cart_note;
        $order_data['group_order_token'] = $group_order_token;

        $order_resource = $this->addToGroupOrderFullProcess($user,$this->ids['merchant_id'],$order_data,$group_order_token,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);

        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_id),'GroupOrderIndividualOrderMapsAdapter');
        $this->assertCount(3,$records,"there should have been three records in the group order map table");
    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testAddToGroupOrderUsingCartButNotSubmitt($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $group_order_id = $group_order_record['group_order_id'];

        $user_resource = createNewUserWithCC();
        $user_resource->first_name = 'Rob';
        $user_resource->last_name = 'Tombie';
        $user_resource->flags = '1C20000001';
        $user_resource->save();
        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id']);
        $cart_note = "sum dum cart note";
        $order_data['note'] = $cart_note;
        $order_data['group_order_token'] = $group_order_token;

        $cart_resource = $this->addtoCart($user,$order_data);

        $ucid = $cart_resource->ucid;
        $checkout_data_resource = $this->getCheckoutForGroupOrder($user,$ucid,$group_order_token,getTomorrowTwelveNoonTimeStampDenver()-1800);
        $this->assertNull($checkout_data_resource->error);
    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testGetGroupOrderInfoForTypeTwo($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $user = logTestUserIn($this->users[2]);
        $group_order_controller = new GroupOrderController($m,$user,$r);
        $group_order_resource = $group_order_controller->getGroupOrderData($group_order_token);
        $group_order_info_resource = $group_order_controller->getTypeTwoGroupOrderInfoAsResource($group_order_resource->group_order_id);
        $summary = $group_order_info_resource->order_summary;
        $user_items = $summary['user_items'];
        $this->assertCount(4,$user_items);
        $this->assertEquals(6,$group_order_info_resource->total_items);
        $this->assertEquals(6,$group_order_info_resource->total_e_level_items);
        $this->assertEquals(3,$group_order_info_resource->total_submitted_orders);
        $this->assertEquals($this->merchant_id,$group_order_info_resource->merchant_id,"should have the merchant id on the group order info object");
        $user_items_as_hash_by_user_id = createHashmapFromArrayOfArraysByFieldName($user_items,"user_id");
        $user_item_record = $user_items_as_hash_by_user_id[$user['user_id']];
        $this->assertEquals('Rob Zmopolis',$user_item_record['full_name']);
        $this->assertEquals(2,$user_item_record['item_count']);

        //check for auto submit time on returned resource
        $expected_time_string = date('l',getTomorrowTwelveNoonTimeStampDenver()).' 12:15 pm';
        $this->assertEquals($expected_time_string,$group_order_info_resource->send_on_local_time_string);
        $this->assertTrue($group_order_resource->auto_send_activity_id > 0,"It should have a valid activity id on the group order resource");
    }

    /**
     * @depends testCreateGroupOrderPickup
     */
    function testCancelGroupOrder($group_order_record)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $user = logTestUserIn($this->users[2]);

        $url = "app2/apiv2/grouporders/$group_order_token";
        $request = createRequestObject($url,'DELETE');
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $go_resource = $group_order_controller->processV2request();
        $this->assertNull($go_resource->error,"should not have gotten an error");
        $group_order_record = GroupOrderAdapter::staticGetRecord(array("group_order_token"=>$group_order_token),'GroupOrderAdapter');
        $this->assertEquals('cancelled',$group_order_record['status'],"Group order should show as cancelled");
        $cart_record = CartsAdapter::staticGetRecordByPrimaryKey($group_order_token,'Carts');
        $this->assertEquals('C',$cart_record['status'],"Cart should show as cancelled");
        return $group_order_record;
    }

    /**
     * @depends testCancelGroupOrder
     */
    function testActivityShouldBeCancelledToo($group_order_record)
    {
        $activity_id = $group_order_record['auto_send_activity_id'];
        $activity = ActivityHistoryAdapter::staticGetRecordByPrimaryKey($activity_id,'ActivityHistoryAdapter');
        $this->assertNotNull($activity);
        $this->assertEquals('N',$activity['locked']);
    }

    /**
     * @depends testCancelGroupOrder
     */
    function testAllChildOrdersShouldBeRefunded($group_order_record)
    {
        $group_order_id = $group_order_record['group_order_id'];
        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_id,"status"=>'Submitted'),"GroupOrderIndividualOrderMapsAdapter");
        $this->assertCount(3,$records,"there shoudl be 3 records");
        $bca = new BalanceChangeAdapter($m);
        $oa = new OrderAdapter($m);
        $aora = new AdmOrderReversalAdapter(getM());
        foreach ($records as $go_map_record) {
            $order_id = $go_map_record['user_order_id'];
            $new_order_resource = Resource::find($oa,''.$order_id);

            // now check to see that the charge was cancelled
            myerror_log("check to see that the cc charge was cancelled");

            $records = $bca->getRecords(array("order_id"=>$order_id));
            $bc_hash = createHashmapFromArrayOfArraysByFieldName($records,'process');
            $this->assertNotNull($bc_hash['CCvoid'],"there should be a void record for each child record");
            $this->assertEquals($new_order_resource->grand_total,$bc_hash['CCvoid']['charge_amt']);
            $this->assertEquals('Issuing a VioPaymentService VOID from the API: Group Order Cancelled',$bc_hash['CCvoid']['notes']);

            //check for admin reversal record no because it was a void
//            $adm_reversal_record = $aora->getRecord(array("order_id"=>$order_id));
//            $this->assertEquals('Issuing a VioPaymentService void from the API: Group Order Cancelled',$adm_reversal_record['note']);


            // check to see that the order was cancelled.
            $this->assertEquals(OrderAdapter::ORDER_CANCELLED, $new_order_resource->status);
        }
    }



    /********************  helper functions **************************/

    function addtoCart($user,$order_data)
    {
        $json_encoded_data = json_encode($order_data);
        $request = createRequestObject('/app2/apiv2/cart','POST',$json_encoded_data,'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $cart_resource = $place_order_controller->processV2Request();
        return $cart_resource;
    }

    function getCheckoutForGroupOrder($user,$ucid,$group_order_token,$time)
    {
        $request = createRequestObject("http://localhost/app2/apiv2/cart/$ucid/checkout",'GET',$body,$mim);
        $place_order_controller = new PlaceOrderController($mt, $user, $request,5);
        $place_order_controller->setCurrentTime($time);
        $checkout_data_resource = $place_order_controller->processV2Request();
        return $checkout_data_resource;
    }

    function placeOrderToBePartOfTypeTwoGroupOrder($user,$merchant_id,$ucid,$group_order_token,$checkout_data_resource,$time)
    {
        $order_data = array();
        $order_data['merchant_id'] = $merchant_id;
        $order_data['user_id'] = $user['user_id'];
        $order_data['cart_ucid'] = $ucid;
        $order_data['tip'] = (rand(100, 1000)) / 100;
        $payment_array = $checkout_data_resource->accepted_payment_types;
        $order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
        $lead_times_array = $checkout_data_resource->lead_times_array;
        $order_data['actual_pickup_time'] = $lead_times_array[0];

        //$order_data['group_order_token'] = $group_order_token;
        $json_encoded_data = json_encode($order_data);
        $request = createRequestObject("/apiv2/orders/$ucid",'POST',$json_encoded_data,'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $place_order_controller->setCurrentTime($time);
        $order_resource = $place_order_controller->processV2Request();
        return $order_resource;
    }

    function addToGroupOrderFullProcess($user,$merchant_id,$order_data,$group_order_token,$time)
    {
        $cart_resource = $this->addtoCart($user,$order_data);
        if ($cart_resource->error) {
            return $cart_resource;
        }
        $ucid = $cart_resource->ucid;

        $checkout_data_resource = $this->getCheckoutForGroupOrder($user,$ucid,$group_order_token,$time);
        if ($checkout_data_resource->error) {
            return $checkout_data_resource;
        }
        $order_resource = $this->placeOrderToBePartOfTypeTwoGroupOrder($user,$merchant_id,$ucid,$group_order_token,$checkout_data_resource,$time);
        return $order_resource;
    }

    /********************  end helper functions **************************/

    static function setUpBeforeClass()
    {
        ini_set('max_execution_time', 300);

        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction();

        getOrCreateSkinAndBrandIfNecessaryWithLoyalty("gotwo","gotwo",$skin_id,$brand_id);
        $skin_resource = setContext("com.splickit.gotwo");

        $blr_data['brand_id'] = $skin_resource->brand_id;
        $blr_data['loyalty_type'] = 'splickit_earn';
        $blr_data['earn_value_amount_multiplier'] = 1;
        $blr_data['cliff_value'] = 10;
        $brand_loyalty_rules_resource = Resource::factory(new BrandLoyaltyRulesAdapter($m),$blr_data);
        $brand_loyalty_rules_resource->save();

        $_SERVER['request_time1'] = microtime(true);
        $menu_id = $menu_id = createTestMenuWithNnumberOfItems(3);
        $ids['menu_id'] = $menu_id;

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_resource->group_ordering_on = 1;
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        $ids['merchant_id'] = $merchant_id;

        $map_resource = Resource::createByData(new MerchantMessageMapAdapter($mimetypes),array("merchant_id"=>$merchant_resource->merchant_id,"message_format"=>'FUA',"delivery_addr"=>"1234567890","message_type"=>"X"));

        $billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);

        $merchant_payment_type_map_adapter = new MerchantPaymentTypeMapsAdapter($mimetypes);
        $cc_merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_id, 2000, $billing_entity_resource->id);
        $ids['cc_billing_entity_id'] = $cc_merchant_payment_type_resource->billing_entity_id;

        $user_resource = createNewUser(array('flags' => '1C20000001', 'first_name' => 'adam', 'last_name' => 'zmopolis'));
        $ids['user_id'] = $user_resource->user_id;
        $users[1] = $user_resource->user_id;
        $user_resource2 = createNewUser(array('flags' => '1C20000001', 'first_name' => 'rob', 'last_name' => 'zmopolis'));
        $user_resource3 = createNewUser(array('flags' => '1C20000001', 'first_name' => 'ty', 'last_name' => 'zmopolis'));
        $user_resource4 = createNewUser(array('flags' => '1C20000001', 'first_name' => 'jason', 'last_name' => 'zmopolis'));
        $users[2] = $user_resource2->user_id;
        $users[3] = $user_resource3->user_id;
        $users[4] = $user_resource4->user_id;

        $_SERVER['log_level'] = 5;
        $_SERVER['unit_test_ids'] = $ids;
        $_SERVER['users'] = $users;
    }

    static function tearDownAfterClass()
    {
        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->rollback();
    }

    static function main()
    {
        $suite = new PHPUnit_Framework_TestSuite(__CLASS__);
        PHPUnit_TextUI_TestRunner::run($suite);
    }
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    GroupOrderIndividualPayCancelledTest::main();
}

?>