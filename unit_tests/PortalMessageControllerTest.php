<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class PortalMessageControllerTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;
    var $merchant_id;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__ . '-' . $_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        setContext("com.splickit.worldhq");

    }

    function tearDown()
    {
        //delete your instance
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
    }

//    function testResend()
//    {
//        $merchant_resource = $this->createMerchantWithPortalMessageType();
//        $merchant_id = $merchant_resource->merchant_id;
//        $user_resource = createNewUserWithCCNoCVV();
//        $user = logTestUserResourceIn($user_resource);
//
//        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
//        $order_resource = placeOrderFromOrderData($cart_data);
//        $order_id = $order_resource->order_id;
//        $ucid = $order_resource->ucid;
//        $this->assertNull($order_resource->error);
//
//        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'P');
//        $message_resource->locked = 'S';
//        $message_resource->viewed = 'V';
//        $message_resource->sent_dt_tm = time()-1;
//        $message_resource->save();
//
//        $url = "/app2/portal/orders/$ucid/resendanorder";
//
//        $request = createRequestObject($url,"POST");
//        $order_controller = new OrderController(getM(),null,$request,5);
//        $resource = $order_controller->processV2Request();
//
//    }

    function testSides()
    {
        $menu_id = createTestMenuWithNnumberOfItems(3);
        $menu_resource = Resource::find(new MenuAdapter(),"$menu_id");
        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1);
        $modifier_item_id = $modifier_group_resource->modifier_items[0]->modifier_item_id;
        $modifier_group_resource_sides = createModifierGroupWithNnumberOfItems($menu_id, 1);
        $modifier_group_resource_sides->modifier_type = 'S';
        $modifier_group_resource_sides->save();
        $modifier_side_item_id = $modifier_group_resource_sides->modifier_items[0]->modifier_item_id;
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_resource->modifier_group_id);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_resource_sides->modifier_group_id);

        $data['message_data'] = ["message_format"=>"P",'delivery_addr'=>"portal","message_type"=>'O'];
        $merchant_resource = createNewTestMerchant($menu_id,$data);
        $merchant_id = $merchant_resource->merchant_id;
        attachMerchantToSkin($merchant_id, getSkinIdForContext());

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, 'pickup', 'the note');
        // create mods node
        $order_data['items'][0]['mods'][] = $order_data['items'][0]['mods'][0];
        $order_data['items'][0]['mods'][0]['modifier_item_id'] = $modifier_item_id;
        $order_data['items'][0]['mods'][1]['modifier_item_id'] = $modifier_side_item_id;

        $checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);
        $messages = MerchantMessageHistoryAdapter::staticGetRecords(['merchant_id'=>$merchant_id,'message_format'=>'P'],'MerchantMessageHistoryAdapter');
        $message = $messages[0];
        $portal_order_json = $message['portal_order_json'];
        $portal_order_json_as_array = json_decode($portal_order_json,true);
        $this->assertCount(1,$portal_order_json_as_array['order_items'][0]['order_detail_modifiers'],"It should have 1 regular modifier");
        $this->assertCount(1,$portal_order_json_as_array['order_items'][0]['order_detail_sides'],'It should have 1 side');



    }

    function testGetMinutesBackToLast6am()
    {
        $merchant_resource = $this->createMerchantWithPortalMessageType();
        $merchant = $merchant_resource->getDataFieldsReally();
        $pmc = new PortalMessageController(getM(),null,$r);

        $current_time = getTimeStampForDateTimeAndTimeZone(16, 23, 15, date('m'), date('d'), date('Y'), date_default_timezone_get());
        $this->assertEquals(623,$pmc->getMinutesBackToLast6amAtMerchantsTimeZone($merchant,$current_time));

        $current_time = getTimeStampForDateTimeAndTimeZone(6, 10, 0, date('m'), date('d'), date('Y'), date_default_timezone_get());
        $this->assertEquals(10,$pmc->getMinutesBackToLast6amAtMerchantsTimeZone($merchant,$current_time));

        $current_time = getTimeStampForDateTimeAndTimeZone(16, 0, 0, date('m'), date('d'), date('Y'), date_default_timezone_get());
        $this->assertEquals(600,$pmc->getMinutesBackToLast6amAtMerchantsTimeZone($merchant,$current_time));

        $current_time = getTimeStampForDateTimeAndTimeZone(2, 0, 0, date('m'), date('d'), date('Y'), date_default_timezone_get());
        $this->assertEquals(1200,$pmc->getMinutesBackToLast6amAtMerchantsTimeZone($merchant,$current_time));

        $current_time = getTimeStampForDateTimeAndTimeZone(6, 6, 45, date('m'), date('d'), date('Y'), date_default_timezone_get());
        $this->assertEquals(6,$pmc->getMinutesBackToLast6amAtMerchantsTimeZone($merchant,$current_time));



    }

    function testMarkFutureOrderComplete()
    {
        $current_time = time();
        $merchant_resource = $this->createMerchantWithPortalMessageType();
        $merchant_id = $merchant_resource->merchant_id;
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,$current_time);
        $checkout_resource->lead_times_array = [array_pop($checkout_resource->lead_times_array)];
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$current_time);
        $this->assertNull($order_resource->error);
        $messages = MerchantMessageHistoryAdapter::staticGetRecords(['merchant_id'=>$merchant_id,'message_format'=>'P'],'MerchantMessageHistoryAdapter');
        $message = $messages[0];
        $this->assertEquals('P',$message['locked']);
        $order_id = $message['order_id'];
        $message_id = $message['map_id'];
        $order = new Order($order_id);
        $this->assertEquals('O',$order->get('status'),"Or should be in the open state since message hasn't been viewed yet");

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/messages/$message_id/markcomplete";
        $request = createRequestObject($url,'POST');
        $portal_message_controller = new PortalMessageController(getM(),null,$request,5);
        $response = $portal_message_controller->processRequest();

        $message_record = MerchantMessageHistoryAdapter::staticGetRecordByPrimaryKey($message_id,'MerchantMessageHistoryAdapter');
        $this->assertEquals('V',$message_record['viewed'],"message should now be showing viewed.");
        $order = new Order($order_id);
        $this->assertEquals('E',$order->get('status'),"Or should be in the executed state since message hasn't been viewed yet");
        $this->assertEquals('S',$message_record['locked'],"message should now be showing sent.");
    }


    function testNonExectionMessageMapFax()
    {
        $data['message_data'] = ["message_format"=>"F",'delivery_addr'=>"1234567890","message_type"=>'O'];
        $merchant_resource = createNewTestMerchant($this->ids['menu_id'],$data);
        $merchant_id = $merchant_resource->merchant_id;
        $request = createRequestObject("/app2/portal/messages?merchant_id=$merchant_id",'GET');
        $portal_message_controller = new PortalMessageController(getM(),null,$request,5);
        $this->assertEquals('F',$portal_message_controller->getPrimaryMessageFormat(),"Primary message Format should be 'F'");
        $this->assertTrue($portal_message_controller->isReadOnly(),"Mercahtn has an fax delivery system so should be read only for portal view");

    }

    function testNonExectionMessageMapPortal()
    {
        $data['message_data'] = ["message_format"=>"P",'delivery_addr'=>"portal","message_type"=>'O'];
        $merchant_resource = createNewTestMerchant($this->ids['menu_id'],$data);
        $merchant_id = $merchant_resource->merchant_id;
        $request = createRequestObject("/app2/portal/messages?merchant_id=$merchant_id",'GET');
        $portal_message_controller = new PortalMessageController(getM(),null,$request,5);
        $this->assertEquals('P',$portal_message_controller->getPrimaryMessageFormat(),"Primary message Format should be 'F'");
        $this->assertFalse($portal_message_controller->isReadOnly(),"Mercahtn has an fax delivery system so should be read only for portal view");

    }

    function testSetReadOnlyViewType()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_resource->lead_time = 15;
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        $request = createRequestObject("/app2/portal/messages?merchant_id=$merchant_id",'GET');
        $portal_message_controller = new PortalMessageController(getM(),null,$request,5);
        $this->assertTrue($portal_message_controller->isReadOnly(),"Mercahtn has an email delivery system so should be read only for portal view");
        return $merchant_id;
    }

    /**
     * @depends testSetReadOnlyViewType
     */
    function testGetOrdersForReadOnlyMerchant($merchant_id)
    {
        $this->createOrderMessages($merchant_id,5,27, 10);
        $orders = OrderAdapter::staticGetRecords(['merchant_id'=>$merchant_id],'OrderAdapter');
        $this->assertCount(5,$orders);
        $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
        $options[TONIC_FIND_BY_METADATA]['locked'] = "N";
        $options[TONIC_SORT_BY_METADATA] = ' order_id ASC ';
        $messages = Resource::findAll(new MerchantMessageHistoryAdapter(getM()),null, $options);
        $this->assertCount(5,$messages);
        $messages[0]->locked = 'S';
        $messages[0]->viewed = 'V';
        $messages[0]->save();
        $messages[1]->locked = 'S';
        $messages[1]->viewed = 'V';
        $messages[1]->save();
        $messages[3]->next_message_dt_tm = time() - 5;
        $messages[3]->locked = 'S';
        $messages[3]->viewed = 'N';
        $messages[3]->save();
        $messages[4]->next_message_dt_tm = time() + 100;
        $messages[4]->save();

        $request = createRequestObject("/app2/portal/messages?merchant_id=$merchant_id",'GET');
        $portal_message_controller = new PortalMessageController(getM(),null,$request,5);
        $read_only_messages = $portal_message_controller->getMessagesLaterThanNMinutesAgo(30);
        $this->assertCount(5,$read_only_messages);

        $get_messages = $portal_message_controller->processRequest();
        $this->assertCount(2,$get_messages->past_messages, 'There should be 2 past messages');
        $this->assertCount(1,$get_messages->late_messages, 'There should be 1 late messages');
        $this->assertCount(1,$get_messages->current_messages, 'There should be 1 current message');
        $this->assertCount(1,$get_messages->future_messages, 'There should be 1 future message');

        $first_message_info = $get_messages->current_messages[0];
        $portal_order_json_as_array = json_decode($first_message_info['portal_order_json'],true);
        $cmc = new CreateMessagesController($merchant_id);
        $expected_portal_order_json = json_encode($cmc->createOrderDataForPortalDisplayFromCompleteOrder(CompleteOrder::staticGetCompleteOrder($portal_order_json_as_array['order_id'],getM())));
        $this->assertEquals($expected_portal_order_json,$first_message_info['portal_order_json'],"message should have had the order json");

    }

    function testNewPulledMessagseType()
    {
        $current_time = time();
        $merchant_resource = $this->createMerchantWithPortalMessageType();
        $merchant_id = $merchant_resource->merchant_id;
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,$current_time);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$current_time);
        $messages = MerchantMessageHistoryAdapter::staticGetRecords(['merchant_id'=>$merchant_id,'message_format'=>'P'],'MerchantMessageHistoryAdapter');
        $this->assertEquals('P',$messages[0]['locked']);
        $this->assertTrue($current_time<=$messages[0]['next_message_dt_tm'] && $messages[0]['next_message_dt_tm'] < $current_time + 3);
        $this->assertEquals($current_time+(20*60),$messages[0]['pickup_timestamp'],"pickup timestamp should be now plus 20 minutes but was: ".date("Y-m-d H:i:s",$messages[0]['pickup_timestamp']));
        return $merchant_id;
    }

    /**
     * @depends testNewPulledMessagseType
     */
    function testGetMessages($merchant_id)
    {
        $request = createRequestObject("/app2/portal/messages?merchant_id=$merchant_id",'GET');
        $portal_message_controller = new PortalMessageController(getM(),null,$request,5);
        $this->assertFalse($portal_message_controller->isReadOnly(),"Mercahtn has an portal delivery system so should NOT be read only for portal view");
        $messages = $portal_message_controller->getMessagesLaterThanNMinutesAgo(5);
        $this->assertCount(1,$messages);
        $this->assertEquals('P',$messages[0]->locked);

        $r = $portal_message_controller->getOrderMessagesForLoadedMerchantId();
        $messages = $portal_message_controller->getMessagesLaterThanNMinutesAgo(5);
        $this->assertCount(1,$messages);
        $this->assertEquals('S',$messages[0]->locked);
        $this->assertEquals('N',$messages[0]->viewed);
        $this->assertNotEquals('0000-00-00 00:00:00',$messages[0]->sent_dt_tm);
        $portal_order_json = $messages[0]->portal_order_json;
        $cmc = new CreateMessagesController($merchant_id);
        $expected_portal_order_json = json_encode($cmc->createOrderDataForPortalDisplayFromCompleteOrder(CompleteOrder::staticGetCompleteOrder($messages[0]->order_id,getM())));
        $this->assertEquals($expected_portal_order_json,$portal_order_json,"message should have had the order json");
        return $messages[0];
    }

    /**
     * @depends testGetMessages
     */

    function testAcceptMessageMarkAsViewedOrderToExecuted($message)
    {
        $this->assertEquals('N',$message->viewed);
        $order_id = $message->order_id;
        $message_id = $message->map_id;
        $order = new Order($order_id);
        $this->assertEquals('O',$order->get('status'),"Or should be in the open state since message hasn't been viewed yet");

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/messages/$message_id/markcomplete";
        $request = createRequestObject($url,'POST');
        $portal_message_controller = new PortalMessageController(getM(),null,$request,5);
        $portal_message_controller->processRequest();

        $message_record = MerchantMessageHistoryAdapter::staticGetRecordByPrimaryKey($message_id,'MerchantMessageHistoryAdapter');
        $this->assertEquals('V',$message_record['viewed'],"message should now be showing viewed.");

        $order = new Order($order_id);
        $this->assertEquals('E',$order->get('status'),"Or should be in the executed state since message hasn't been viewed yet");
    }


    function testGetOrderMessagesForLoadedMerchantId()
    {
        $merchant_resource = $this->createMerchantWithPortalMessageType();
        $merchant_id = $merchant_resource->merchant_id;
        $this->createOrderMessages($merchant_id,10,30);
        $orders = OrderAdapter::staticGetRecords(['merchant_id'=>$merchant_id],'OrderAdapter');
        $this->assertCount(10,$orders);
        $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
        $options[TONIC_FIND_BY_METADATA]['locked'] = "P";
        $options[TONIC_SORT_BY_METADATA] = ' order_id ASC ';
        $messages = Resource::findAll(new MerchantMessageHistoryAdapter(getM()),null, $options);
        $this->assertCount(10,$messages);
        for ($i=0;$i<3;$i++) {
            $messages[$i]->locked = 'S';
            $messages[$i]->viewed = 'V';
            $messages[$i]->save();
        }

        $request = createRequestObject("/app2/portal/messages?merchant_id=$merchant_id",'GET');
        $portal_message_controller = new PortalMessageController(getM(),null,$request,5);
        $messages1 = $portal_message_controller->getMessagesLaterThanNMinutesAgo(35);
        $this->assertCount(10,$messages1);

        $messages2 = $portal_message_controller->getMessagesLaterThanNMinutesAgo(28);
        $this->assertCount(9,$messages2);

        $mmha = new MerchantMessageHistoryAdapter(getM());
        $options2[TONIC_FIND_BY_SQL] = "SELECT * from Merchant_Message_History WHERE merchant_id = $merchant_id AND locked = 'S' AND next_message_dt_tm < NOW() ORDER BY next_message_dt_tm desc";
        $records = $mmha->getRecords(null,$options2);
        $this->assertCount(3,$records,"there should now be 3 records that are showing as sent");

        $messages3 = $portal_message_controller->getOrderMessagesForLoadedMerchantId();

        // that call should have caused all due messages to convert to 'S' and non-viewed.
        $options2[TONIC_FIND_BY_SQL] = "SELECT * from Merchant_Message_History WHERE merchant_id = $merchant_id AND locked = 'S' AND next_message_dt_tm < NOW() ORDER BY next_message_dt_tm desc";
        $records = $mmha->getRecords(null,$options2);
        $this->assertCount(7,$records,"there should now be 7 records that are showing as sent");
        return $messages3;
    }

    /**
     * @depends testGetOrderMessagesForLoadedMerchantId
     */
    function testHaveAllSectionsOfTheReturn($messages)
    {
        $this->assertCount(4,$messages,"there shuold be 4 sections");
    }

    /**
     * @depends testGetOrderMessagesForLoadedMerchantId
     */
    function testShowPastMessages($messages)
    {
        $past_messages = $messages['past_messages'];
        $this->assertCount(3,$past_messages, 'There should be 3 past messages');
    }

//    /**
//     * @depends testGetOrderMessagesForLoadedMerchantId
//     */
//    function testShowLateMessages($messages)
//    {
//        $late_messages = $messages['late_messages'];
//        $this->assertCount(2,$late_messages,"there should be 2 messages that have not been marked as viewed that are within 10 minutes of their pickup time");
//
//    }

    /**
     * @depends testGetOrderMessagesForLoadedMerchantId
     */
    function testShowCurrentMessages($messages)
    {
        $current_messages = $messages['current_messages'];
        $this->assertCount(4,$current_messages,"there should be 2 messages that have not been marked as viewed that are within the lead time of their pickup time but more than 10 minutes");
    }

    /**
     * @depends testGetOrderMessagesForLoadedMerchantId
     */
    function testShowFutureMessages($messages)
    {
        $future_messages = $messages['future_messages'];
        $this->assertCount(3,$future_messages,"there should be 3 messages in the future messages section");
    }

    /************************************************/

    function createMerchantWithPortalMessageType()
    {
        $data['message_data'] = ["message_format"=>"P",'delivery_addr'=>"portal","message_type"=>'O'];
        $merchant_resource = createNewTestMerchant($this->ids['menu_id'],$data);
        return $merchant_resource;
    }

    function createOrderMessages($merchant_id,$number_of_orders,$starting_minutes_back,$increase = 5)
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $order_resources = [];
        for ($i=0;$i<$number_of_orders;$i++) {
            $current_time = time() - ($starting_minutes_back*60) + ($increase*60*$i);
            $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
            $checkout_resource = getCheckoutResourceFromOrderData($cart_data,$current_time);
            $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$current_time);
            $order_resources[] = $order_resource;
            // now fix message becuase sytem will not aloow message to be created in the past
            $options[TONIC_FIND_BY_METADATA] = ['merchant_id'=>$merchant_id,'order_id'=>$order_resource->order_id,'message_type'=>'X'];
            if ($message_resource = Resource::find(new MerchantMessageHistoryAdapter(getM()),null,$options)) {
                $message_resource->next_message_dt_tm = $current_time;
                $message_resource->save();
            } else {
                $options[TONIC_FIND_BY_METADATA]['message_type'] = 'O';
                $message_resource = Resource::find(new MerchantMessageHistoryAdapter(getM()),null,$options);
                $date_string = date('Y-m-d H:i;j',$current_time);
                $message_resource->next_message_dt_tm = $current_time;
                $message_resource->save();
            }
        }
        return $order_resources;
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

        createWorldHqSkin();
        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $ids['menu_id'] = $menu_id;

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
    PortalMessageControllerTest::main();
}

?>