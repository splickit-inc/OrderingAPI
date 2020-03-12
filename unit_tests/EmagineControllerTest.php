<?php

/**
 * Created by PhpStorm.
 * User: Elvis
 * Date: 8/18/16
 * Time: 5:41 PM
 */

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class EmagineControllerTest extends PHPUnit_Framework_TestCase
{
    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__ . '-' . $_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        setContext("com.splickit.snarfs");
    }

    function tearDown()
    {
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
    }

    function testDoubleComesWithModifierWithPrice()
    {
        $menu_id = createTestMenuWithNnumberOfItems(1);

        $menu_items = CompleteMenu::getAllMenuItemsAsArray($menu_id);
        $menu_item_resource = Resource::find(new ItemAdapter(getM()),$menu_items[0]['item_id']);
        $menu_item_resource->item_print_name = $menu_item_resource->item_print_name.' PRINT';
        $menu_item_resource->save();


        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $modifier_item_record = ModifierItemAdapter::staticGetRecord(array("modifier_group_id" => $modifier_group_id), 'ModifierItemAdapter');
        $modifier_size_record = ModifierSizeMapAdapter::staticGetRecord(array("modifier_item_id" => $modifier_item_record['modifier_item_id']), 'ModifierSizeMapAdapter');

        $modifier_size_resource = Resource::find(new ModifierSizeMapAdapter($m), "" . $modifier_size_record['modifier_size_id'], $options);
        $modifier_size_resource->external_id = "8765-4321";
        $modifier_size_resource->save();
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 1);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_resource->set('merchant_external_id', '88888888');
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_id, 'N', 'emagine', 'X');
        $emagine_map = Resource::createByData(new MerchantEmagineInfoMapsAdapter($mimetypes), array('merchant_id' => $merchant_id, 'location' => "Location888", 'token' => "9876543210"));

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        // add a additional modifier that comes with
        $cart_data['items'][0]['mods'][0]['mod_quantity'] = 3;
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_resource->order_id);
        $order_detail_id = $complete_order['order_details'][0]['order_detail_modifiers'][0]['order_detail_mod_id'];
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_resource->order_id,'N');
        $emagine_controller = ControllerFactory::generateFromMessageResource($message_resource);
        $ready_to_send_message_resource = $emagine_controller->prepMessageForSending($message_resource);
        $message_text = $ready_to_send_message_resource->message_text;
        $message_array = json_decode($message_text,true);

        $this->assertEquals('Test Item 1',$message_array['order']['order_details'][0]['item_name'],"it should have the item name");

        $order_detail_modifiers = $message_array['order']['order_details'][0]['order_detail_modifiers'];
        $this->assertCount(2,$order_detail_modifiers,"It should have broken the modifier into 2 records since 1 is comes with and free the other is charged");
        $mod_hash = createHashmapFromArrayOfArraysByFieldName($order_detail_modifiers,'order_detail_mod_id');
        $comes_with_record = $mod_hash["$order_detail_id"."F"];
        $this->assertEquals('8765-4321',$comes_with_record['external_id']);
        $this->assertEquals('1',$comes_with_record['mod_quantity']);
        $this->assertEquals('0.00',$comes_with_record['mod_price']);
        $this->assertEquals('0.00',$comes_with_record['mod_total_price']);

        $added_record = $mod_hash["$order_detail_id"];
        $this->assertEquals('8765-4321',$added_record['external_id']);
        $this->assertEquals('2',$added_record['mod_quantity']);
        $this->assertEquals('0.50',$added_record['mod_price']);
        $this->assertEquals('1.00',$added_record['mod_total_price']);

    }

    function testExceptionsTempate()
    {
        $lua = new LookupAdapter(getM());
        $sql = "INSERT INTO Lookup VALUES(NULL ,'message_template', 'NE','/order_templates/emagine/place_order.txt','Y',NOW(),NOW(),'N')";
        $lua->_query($sql);

        $menu_id = createTestMenuWithNnumberOfItems(1);
        $menu_items = CompleteMenu::getAllMenuItemsAsArray($menu_id);
        $menu_item_resource = Resource::find(new ItemAdapter(getM()),$menu_items[0]['item_id']);
        $menu_item_resource->item_print_name = $menu_item_resource->item_print_name.' PRINT';
        $menu_item_resource->save();

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 5);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $sql = "UPDATE Modifier_Item SET modifier_item_print_name = CONCAT(modifier_item_print_name,' PRINT') WHERE modifier_group_id = $modifier_group_id";
        $modifier_item_adapter = new ModifierItemAdapter(getM());
        $modifier_item_adapter->_query($sql);
        $modifier_item_records = ModifierItemAdapter::staticGetRecords(array("modifier_group_id" => $modifier_group_id), 'ModifierItemAdapter');
//        $modifier_size_record = ModifierSizeMapAdapter::staticGetRecord(array("modifier_item_id" => $modifier_item_record['modifier_item_id']), 'ModifierSizeMapAdapter');
//
//        $modifier_size_resource = Resource::find(new ModifierSizeMapAdapter($m), "" . $modifier_size_record['modifier_size_id'], $options);
//        $modifier_size_resource->external_id = "8765-4321";
//        $modifier_size_resource->save();

        // add a side modifier to the world
        $side_modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 2, 'Test Side Modifier Group', 'S');
        $side_modifier_group_id = $side_modifier_group_resource->modifier_group_id;
        $side_modifier_item_records = ModifierItemAdapter::staticGetRecords(array("modifier_group_id" => $side_modifier_group_id), 'ModifierItemAdapter');

        $added_side_modifier = $side_modifier_item_records[1];

        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 3);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $side_modifier_group_id, 1);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_resource->set('merchant_external_id', '9999999');
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_id, 'NE', 'emagine', 'X');
        $emagine_map = Resource::createByData(new MerchantEmagineInfoMapsAdapter(getM()), array('merchant_id' => $merchant_id, 'location' => "Location999", 'token' => "98765432109"));

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        // add a additional modifier that comes with
        $cart_data['items'][0]['mods'][3]['mod_quantity'] = 3;
        $hold_it_modfier_item_id = $cart_data['items'][0]['mods'][4]['modifier_item_id'];
        unset($cart_data['items'][0]['mods'][4]);
        $cart_data['items'][0]['mods'][] = ["modifier_item_id"=>$side_modifier_item_records[1]['modifier_item_id'],"mod_quantity"=>1];
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_resource->order_id);
        $order_detail_id = $complete_order['order_details'][0]['order_detail_modifiers'][0]['order_detail_mod_id'];
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_resource->order_id,'NE');
        $emagine_controller = ControllerFactory::generateFromMessageResource($message_resource);
        $ready_to_send_message_resource = $emagine_controller->prepMessageForSending($message_resource);
        $message_text = $ready_to_send_message_resource->message_text;
        $message_array = json_decode($message_text,true);

        $this->assertEquals('Test Item 1 PRINT',$message_array['order']['order_details'][0]['item_name'],"it should have the item print name");

        $order_detail_modifiers = $message_array['order']['order_details'][0]['order_detail_modifiers'];
        $this->assertCount(6,$order_detail_modifiers,"It should have broken the modifiers into 6 records");

        $modifier_item_size_record = ModifierSizeMapAdapter::staticGetRecord(array("modifier_item_id"=>$hold_it_modfier_item_id),'ModifierSizeMapAdapter');
        $external_id = $modifier_item_size_record['external_id'];
        $modifier_hash_map = createHashmapFromArrayOfArraysByFieldName($order_detail_modifiers,'external_id');
        $expected_hold_it_order_detail_modifier = $modifier_hash_map["$external_id"];
        $this->assertEquals(0,$expected_hold_it_order_detail_modifier['mod_quantity']);
        $this->assertEquals('NO Test Modifier Group Item 1 PRINT',$expected_hold_it_order_detail_modifier['mod_name']);
        $last_modifier_item_in_list = array_pop($order_detail_modifiers);
        $this->assertEquals($added_side_modifier['modifier_item_print_name'],$last_modifier_item_in_list['mod_name']);
    }

    function testPromo()
    {
        $user = logTestUserIn($this->ids['user_id']);
        $merchant_id = $this->ids['merchant_id'];
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'sum dum note');
        $order_resource = placeOrderFromOrderData($order_data, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertNotNull($order_id);

        $order_resource = Resource::find(new OrderAdapter(),"$order_id");
        $order_resource->promo_code = 'snarfs50';
        $order_resource->promo_amt = -1.00;
        $order_resource->promo_tax_amt = -.10;
        $order_resource->save();
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'N');
        $ec = ControllerFactory::generateFromMessageResource($message_resource);
        $ready_to_send_message_resource = $ec->prepMessageForSending($message_resource);

        $message = $ready_to_send_message_resource->message_text;
        $this->assertContains('"promo_amt":"1.00"', $message,"It should have the promo amount as part of the payload");
        $this->assertContains('"promo_code":"snarfs50"', $message,"It should have the promo code as part of the payload");
    }

    function testCreateEmagineMessageControllerFromFactory()
    {
        $user = logTestUserIn($this->ids['user_id']);
        $merchant_id = $this->ids['merchant_id'];
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'sum dum note');
        $order_resource = placeOrderFromOrderData($order_data, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertNotNull($order_id);
        $messages = MerchantMessageHistoryAdapter::getAllOrderMessages($order_id);
        $messages_hash = createHashmapFromArrayOfResourcesByFieldName($messages, 'message_format');
        $this->assertNotNull($messages_hash['N'], "Should have created a message with the Emagine  format");
        $message_resource = $messages_hash['N'];

        $controller_name = ControllerFactory::getControllerNameFromMessageResource($message_resource);
        $this->assertEquals('Emagine',$controller_name,"It should return Emagine as the controller name");

        $controller = ControllerFactory::generateFromMessageResource($message_resource,$m,$u,$r,5);
        $this->assertEquals('EmagineController', get_class($controller), "It should return a Emagine Controller");
        return $message_resource;
    }

    /**
     * @depends testCreateEmagineMessageControllerFromFactory
     */
    function testGenerateTemplate($message_resource)
    {
        //Get the message template from database and populate it
        $order_resource = Resource::find(new OrderAdapter(getM()),"".$message_resource->order_id);
        $order_resource->note = 'nullit';
        $order_resource->save();

        $base_order = CompleteOrder::getBaseOrderData($message_resource->order_id);

        $controller = ControllerFactory::generateFromMessageResource($message_resource, $m, $u, $r);

        $message_resource = $controller->prepMessageForSending($message_resource);

        $message = $message_resource->message_text;
        $this->assertContains('"location":"Location168"', $message);
        $this->assertContains('"authToken":"1234567890"', $message);
        $this->assertContains("order", $message);
        $this->assertContains('"note":" "',$message);
    }

    /**
     * @depends testCreateEmagineMessageControllerFromFactory
     */
    function testSendOrder($message_resource)
    {
        //Get the message template from database and populate it
        $controller = ControllerFactory::generateFromMessageResource($message_resource, $m, $u, $r);

        $response = $controller->sendThisMessage($message_resource);
        $this->assertTrue($response);

        // now check to see if the message was actually sent
        $new_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($message_resource->order_id,'N');
        $this->assertEquals("S",$new_message_resource->locked,"The locked should have been set to 'S'");
        $this->assertNotNull($new_message_resource->response,"the response should have been saved");
        $this->assertNotNull($new_message_resource->message_text,"the message text should have been saved");

        $message = $message_resource->message_text;
        $this->assertContains('"location":"Location168"', $message);
        $this->assertContains('"authToken":"1234567890"', $message);
        $this->assertContains("order", $message);


    }

    /**
     * @depends testCreateEmagineMessageControllerFromFactory
     */
    function testSendOrderFailure($message_resource)
    {
        //Get the message template from database and populate it
        $controller = ControllerFactory::generateFromMessageResource($message_resource, $m, $u, $r);

        $fake_id = "qwertyu234567";
        $body = $message_resource->message_text;
        $body = str_replace("1234-5678",$fake_id,$body);

        $message_resource->set('message_text', $body);

        $response = $controller->sendThisMessage($message_resource);
        $this->assertTrue($response);

        // now check to see if the message was actually sent
        $new_message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($message_resource->order_id,'N');
        $this->assertEquals("N",$new_message_resource->locked,"The locked should have been set to 'N'");
    }


    static function setUpBeforeClass()
    {
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        ini_set('max_execution_time', 300);
        SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction();

        setContext('com.splickit.snarfs');
        $ids['skin_id'] = getSkinIdForContext();

        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $ids['menu_id'] = $menu_id;

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $modifier_item_record = ModifierItemAdapter::staticGetRecord(array("modifier_group_id" => $modifier_group_id), 'ModifierItemAdapter');
        $modifier_size_record = ModifierSizeMapAdapter::staticGetRecord(array("modifier_item_id" => $modifier_item_record['modifier_item_id']), 'ModifierSizeMapAdapter');

        $modifier_size_resource = Resource::find(new ModifierSizeMapAdapter($m), "" . $modifier_size_record['modifier_size_id'], $options);
        $modifier_size_resource->external_id = "1234-5678";
        $modifier_size_resource->save();
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 0);

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_resource->set('merchant_external_id', '161668');
        $merchant_resource->save();
        $complete_menu = CompleteMenu::getCompleteMenu($menu_id, 'Y', $merchant_resource->merchant_Id);
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $ids['merchant_id'] = $merchant_resource->merchant_id;
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_resource->merchant_id, 'N', 'emagine', 'X');

        $emagine_map = Resource::createByData(new MerchantEmagineInfoMapsAdapter($mimetypes), array('merchant_id' => $merchant_resource->merchant_id, 'location' => "Location168", 'token' => "1234567890"));

        $user_resource = createNewUser(array("flags" => "1C20000001"));
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
    static function main()
    {
        $suite = new PHPUnit_Framework_TestSuite(__CLASS__);
        PHPUnit_TextUI_TestRunner::run($suite);
    }
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    EmagineControllerTest::main();
}
