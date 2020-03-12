<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class FaxForCateringTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        setContext('com.splickit.pitapit');
    }

    function tearDown()
    {
        //delete your instance
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
    }

    function testFormatFaxForSendOrderWithCatering(){
        $side_group = $this->ids['SideGroupItems'];
        $top_group = $this->ids['TopGroupItems'];

        $user_resource = createNewUserWithCCNoCVV(array("contact_no"=>'123 456 7890'));
        $user_id = $user_resource->user_id;
        logTestUserIn($user_id);
        $merchant_id = $this->ids['merchant_id'];
        $map_resource = Resource::createByData(new MerchantMessageMapAdapter(getM()),array("merchant_id"=>$merchant_id,"message_format"=>'FPC',"delivery_addr"=>"1234567890","message_type"=>"O"));

        $_SERVER['SKIN']['show_notes_fields'] = false;
        $order_adapter = new OrderAdapter(getM());
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', '', 1);
        $order_data['items'][0]['mods'] = [["mod_quantity"=>3,"modifier_item_id"=>$side_group[0]->modifier_item_id],["mod_quantity"=>3,"modifier_item_id"=>$top_group[0]->modifier_item_id],["mod_quantity"=>2,"modifier_item_id"=>$top_group[1]->modifier_item_id]];

        $order_data['tip'] = 0.00;
        $order_data['note'] = null;

        $response = placeOrderFromOrderData($order_data, time());
        $this->assertNull($response->error);
        $complete_order = CompleteOrder::staticGetCompleteOrder($response->order_id);
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($response->order_id,'FPC');
        $fax_controller = ControllerFactory::generateFromMessageResource($message_resource,$m,$u,$r,5);
        $template = $fax_controller->getRepresentationFromMessageFormatOfMessageResource($message_resource);
        $this->assertEquals("/order_templates/fax/execute_order_fax_PP4.htm", $template);

        $message_to_send_resource = $fax_controller->prepMessageForSending($message_resource);
        $this->assertNotNull($message_to_send_resource,"Should have generated the message to send resource");
        $body = $message_to_send_resource->message_text;
        //$expected_payload = file_get_contents("./unit_tests/resources/expected_fax_message_body.htm");
        $expected_payload = cleanUpDoubleSpacesCRLFTFromString(file_get_contents("./unit_tests/resources/expected_fax_message_body.htm"));

        $expected_payload = str_replace("%%order_id%%",$response->order_id,$expected_payload);
        $expected_payload = str_replace("%%place_at%%",$message_to_send_resource->order_date3,$expected_payload);
        $expected_payload = str_replace("%%requested_at%%",$message_to_send_resource->pickup_date3,$expected_payload);
        $expected_payload = str_replace("%%tip%%",$message_to_send_resource->tip_amt,$expected_payload);

        if ($message_to_send_resource->promo_payor == 2){
            $promo_amt = $message_to_send_resource->promo_amt;
        }else{
            $promo_amt = 0.0;
        }


        $grand_total = $message_to_send_resource->order_amt + $message_to_send_resource->total_tax_amt + $message_to_send_resource->tip_amt +$message_to_send_resource->delivery_amt + $promo_amt;

        $expected_payload = str_replace("%%grand_total%%",$grand_total,$expected_payload);
        $clean_body = cleanUpDoubleSpacesCRLFTFromString($body);
        $clean_body = str_replace("</tr> <tr>", "</tr><tr>", $clean_body);

        $this->assertContains('Top Group Item 1 (X3)',$clean_body,"Should have 3 top group item 3");
        $this->assertContains('Top Group Item 2 (X2)',$clean_body, "Should have 2 top group item 2");
        $this->assertContains("<b>5</b>",$clean_body,"The item count should be 5");
       // $this->assertEquals($expected_payload,$clean_body,"should have created the expected catering fax payload");
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

        $mysql_adapter = new MySQLAdapter($m);
        $sql = "INSERT INTO Lookup (type_id_field, type_id_value, type_id_name) values ('message_template', 'FPC', '/order_templates/fax/execute_order_fax_PP4.htm')";
        $mysql_adapter->_query($sql);
        setContext('com.splickit.pitapit');
        $ids['skin_id'] = getSkinIdForContext();

        // create catering menu with single regular menu type
        $menu_resource = createNewMenu();
        $menu_id = $menu_resource->menu_id;
        $menu_type_resource = createNewMenuType($menu_id, 'Catering - Test Menu Type 1','C');
        $size_resource = createNewSize($menu_type_resource->menu_type_id, 'Test Size 1');
        createItem("Test Menu Type 1", $size_resource->size_id, $menu_type_resource->menu_type_id);

        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 2, "Side Group",'S');
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 0);
        $ids['sideGroupItems'] = $modifier_group_resource->modifier_items;

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 2,"Top Group",'T');
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 0);
        $ids['TopGroupItems'] = $modifier_group_resource->modifier_items;

        $ids['menu_id'] = $menu_id;


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
    FaxForCateringTest::main();
}

?>