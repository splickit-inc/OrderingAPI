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

class JsonControllerTest extends PHPUnit_Framework_TestCase
{
    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__ . '-' . $_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        setContext("com.splickit.jsonskin");
    }

    function tearDown()
    {
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
    }


    function testGetNextMessage()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_numeric = $merchant_resource->numeric_id;
        $merchant_id = $merchant_resource->merchant_id;
        $map_resource = Resource::createByData(new MerchantMessageMapAdapter(getM()),array("merchant_id"=>$merchant_id,"message_format"=>'J',"delivery_addr"=>"Ghost Kitchen","message_type"=>"X"));

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data);
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,null);
        $this->assertNull($order_resource->error);


        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_resource->order_id,'J');
        $message_resource->next_message_dt_tm = time()-100;
        $message_resource->save();
        $expected_message_text = $message_resource->portal_order_json;

        $request = new Request();
        $request->url = "/messagemanager/getnextmessagebymerchantid/$merchant_numeric/json";
        $message_controller = ControllerFactory::generateFromUrl($request->url, getM(), $user, $request, 5);
        $this->assertEquals("JsonController",get_class($message_controller));

        $pulled_message_resource = $message_controller->pullNextMessageResourceByMerchant($merchant_numeric);
        myerror_log("pulled message is: ".$pulled_message_resource->message_text);

    }


    static function setUpBeforeClass()
    {
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        ini_set('max_execution_time', 300);
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction();

        $skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty('jsonskin','jsonbrand',null,null);
        setContext("com.splickit.jsonskin");

        $menu_id = createTestMenuWithNnumberOfItems(1);
        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 3);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id,1);

        $ids['menu_id'] = $menu_id;

        $merchant_resource =createNewTestMerchant($menu_id);
        $ids['merchant_id'] = $merchant_resource->merchant_id;

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
    JsonControllerTest::main();
}
