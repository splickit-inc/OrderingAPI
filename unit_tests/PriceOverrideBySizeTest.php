<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class PriceOverrideBySizeTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $code = generateCode(7);
        $_SERVER['STAMP'] = __CLASS__ . '-' . $code;
        $_SERVER['RAW_STAMP'] = $code;
        $this->ids = $_SERVER['unit_test_ids'];

    }

    function tearDown()
    {
        //delete your instance
        unset($this->ids);
        unset($_SERVER['max_lead']);
    }

    function testReturnPriceOverrideBySizeOnMenuCall()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $merchant_id = $this->ids['merchant_id'];

        $request = new Request();
        $request->url = "/apiv2/merchants/$merchant_id";
        $request->method = 'GET';
        $merchant_controller = new MerchantController(getM(), $user, $request, 5);
        $resource = $merchant_controller->processV2Request();
        $menu = $resource->menu;
        $item_modifier_group_map_info = $menu['menu_types'][0]['menu_items'][0]['modifier_groups'][0];
        $this->assertTrue(isset($item_modifier_group_map_info['modifier_group_credit']),'should still have the old parameter for backwards compatibility');
        $this->assertTrue(isset($item_modifier_group_map_info['modifier_group_credit_by_size']),"should have the new parameter for price override by size 'modifier_group_credit_by_size' field name");
        $modifier_group_credit_by_size = $item_modifier_group_map_info['modifier_group_credit_by_size'];
        $all_sizes = CompleteMenu::getAllSizes($menu['menu_id']);
        $all_sizes_hash_by_name = createHashmapFromArrayOfArraysByFieldName(array_pop($all_sizes),'size_name');
        $this->assertEquals(.25,$modifier_group_credit_by_size[$all_sizes_hash_by_name['small']['size_id']],"The small should have a .25 override");
        $this->assertEquals(.08,$modifier_group_credit_by_size[$all_sizes_hash_by_name['medium']['size_id']],"The medium should use the default of .08 since there is no specific override by size");
        $this->assertEquals(.75,$modifier_group_credit_by_size[$all_sizes_hash_by_name['large']['size_id']],"The large should have a .75 override");

    }

    function testUserPriceOverrideBySize()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $merchant_id = $this->ids['merchant_id'];
        $size_by_name = $this->ids['sizes'];
        $order_adapter = new OrderAdapter(getM());
        $cart_data = $order_adapter->getCartArrayWithOneModierPerModifierGroup($merchant_id);

        //do small first
        $cart_data['items'][0]['size_id'] = $size_by_name['small']['size_id'];
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $complete_order = CompleteOrder::staticGetCompleteOrder($checkout_resource->oid_test_only);
        $price_adjustment = $complete_order['order_details'][0]['order_detail_price_adjustments'][0]['mod_price'];

        $expected_price_adjustment = -.25;


        $this->assertEquals($expected_price_adjustment,$price_adjustment,"Price adjustment shoudl be the value in the override by size table");

        /*

SELECT modifier_group_id,price_override,price_max FROM Item_Modifier_Group_Map
WHERE item_id = 281752 AND logical_delete = 'N' AND merchant_id = 104307 AND
	modifier_group_id IN (SELECT DISTINCT modifier_group_id FROM Order_Detail_Modifier WHERE order_detail_id = 583048 AND ( modifier_type LIKE 'I%' OR modifier_type = 'T' OR modifier_type = 'S' ))

-- VS

SELECT modifier_group_id,price_override,88888 AS price_max FROM Item_Modifier_Item_Price_Override_By_Sizes
WHERE item_id = 281752 AND size_id = 91365 AND logical_delete = 'N' AND merchant_id = 104307 AND
	modifier_group_id IN (SELECT DISTINCT modifier_group_id FROM Order_Detail_Modifier WHERE order_detail_id = 583048 AND ( modifier_type LIKE 'I%' OR modifier_type = 'T' OR modifier_type = 'S' ))


        DECLARE order_item_mod_groups CURSOR FOR SELECT modifier_group_id,price_override,88888 AS price_max FROM Item_Modifier_Item_Price_Override_By_Sizes
									WHERE item_id = xitem_id AND size_id = xsize_id AND logical_delete = 'N' AND merchant_id = xmenu_merchant_id AND
									modifier_group_id IN (SELECT DISTINCT modifier_group_id FROM Order_Detail_Modifier WHERE order_detail_id = xorder_detail_id AND ( modifier_type LIKE 'I%' OR modifier_type = 'T' OR modifier_type = 'S' ));

         */


    }

    function testUsePriceOverrideByGroupWhenSizeRecordDoesntExist()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $merchant_id = $this->ids['merchant_id'];
        $size_by_name = $this->ids['sizes'];
        $order_adapter = new OrderAdapter(getM());
        $cart_data = $order_adapter->getCartArrayWithOneModierPerModifierGroup($merchant_id);

        //do small first
        $cart_data['items'][0]['size_id'] = $size_by_name['medium']['size_id'];
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $complete_order = CompleteOrder::staticGetCompleteOrder($checkout_resource->oid_test_only);
        $price_adjustment = $complete_order['order_details'][0]['order_detail_price_adjustments'][0]['mod_price'];

        $expected_price_adjustment = -.08;


        $this->assertEquals($expected_price_adjustment,$price_adjustment,"Price adjustment shoudl be the value in the imgm table");
    }

    static function setUpBeforeClass()
    {
        ini_set('max_execution_time', 0);
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        //$mysqli->begin_transaction(); ;


        $skin_resource = createWorldHqSkin();
        $ids['skin_id'] = $skin_resource->skin_id;

        $menu_id = createTestMenuWithNnumberOfItemsAndMenuTypes(1,0,1,3);
        $menu_resource = Resource::find(new MenuAdapter(getM()),"$menu_id");
        $menu_resource->version = 3.00;
        $menu_resource->save();
        $ids['menu_id'] = $menu_id;

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1);
        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
        $item_size_resources = CompleteMenu::getAllItemSizesAsResources($menu_id, 0);

        $imgm_resource = assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 0);
        $imgm_resource->price_override = .08; // this should be ignored
        $imgm_resource->save();
        $size_resources = CompleteMenu::getAllSizesAsResources($menu_id);

        $modifier_item_size_resources = CompleteMenu::getAllModifierItemSizesAsResoures($menu_id);
        $modifier_item_size_resource = $modifier_item_size_resources[0];
        $size_names = ['small','medium','large'];
        $i = 0;
        foreach ($size_resources as &$size_resource) {
            $size_resource->size_name = $size_names[$i];
            $size_resource->size_print_name = $size_names[$i];
            $size_resource->save();

            $is_data = ["item_id"=>$item_records[0]['item_id'],"size_id"=>$size_resource->size_id,"merchant_id"=>0];
            $is_options[TONIC_FIND_BY_METADATA] = $is_data;
            $item_size_resource = Resource::find(new ItemSizeAdapter(getM()),null,$is_options);
            $item_size_resource->price = 2.00 * ($i+1);
            $item_size_resource->save();

            $modifier_item_size_resource->_exists = false;
            unset($modifier_item_size_resource->modifier_size_id);
            $modifier_item_size_resource->size_id = $size_resource->size_id;
            $modifier_item_size_resource->modifier_price = .25 * ($i+1);
            $modifier_item_size_resource->save();

            if ( $size_resource->size_name != 'medium') {
                $imipobs_adapter = new ItemModifierItemPriceOverrideBySizesAdapter(getM());
                $data = ['item_id'=> $item_records[0]['item_id'],'size_id'=>$size_resource->size_id,'modifier_group_id'=>$modifier_group_id,'merchant_id'=>0,'price_override'=>$modifier_item_size_resource->modifier_price];
                $override_by_size_resource = Resource::factory($imipobs_adapter,$data);
                $override_by_size_resource->save();
            }
            $i++;
        }
        $sizes = CompleteMenu::getAllSizes($menu_id);
        $sizes_hash = createHashmapFromArrayOfArraysByFieldName(array_pop($sizes),'size_name');
        $ids['sizes'] = $sizes_hash;

        $merchant_resource = createNewTestMerchant($menu_id);
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $ids['merchant_id'] = $merchant_resource->merchant_id;

        MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, null);

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
    PriceOverrideBySizeTest::main();
}

?>


