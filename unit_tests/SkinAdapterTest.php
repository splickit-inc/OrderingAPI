<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class SkinAdapterTest extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__ . '-' . $_SERVER['STAMP'];
    }

    function tearDown()
    {
        //delete your instance
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
    }

    function testGetSkinFromPublicKey()
    {
        setProperty('DO_NOT_CHECK_CACHE','true');
        $skin_resource = getOrCreateSkinAndBrandIfNecessary('testit','testid',null,null);

        $skin_id = $skin_resource->skin_id;

        $the_skin = SkinAdapter::getSkin($skin_resource->public_client_id);
        $this->assertEquals($skin_id,$the_skin['skin_id']);
    }

    function testLoyaltyArrayOnSelect()
    {
        $adapter = new SkinAdapter($m);
        $loyalty_vals = array(
            "supports_history" => true,
            "supports_join" => false,
            "supports_link_card" => true,
            "supports_pin" => false,
            "loyalty_lite" => false,
            "loyalty_type" => "splickit_earn",
            "loyalty_labels" => array(
                array(
                    "label" => "Points balance",
                    "type" => "points"
                )
            )
        );
        $skin_resource = Resource::createByData($adapter, array_merge(array("external_identifier" => "com.splickit.cheeseshop", "brand_id" => 300), $loyalty_vals));

        $brand_resource = Resource::find(new BrandAdapter($mimetypes), "$skin_resource->brand_id");
        $brand_resource->loyalty = 'Y';
        $brand_resource->save();

        $blr_data['brand_id'] = $skin_resource->brand_id;
        $blr_data['loyalty_type'] = 'splickit_earn';
        $blr_data['earn_value_amount_multiplier'] = 1;
        $blr_data['cliff_value'] = 10;
        $brand_loyalty_rules_resource = Resource::factory(new BrandLoyaltyRulesAdapter($m), $blr_data);
        $brand_loyalty_rules_resource->save();

        setContext($skin_resource->external_identifier);

        $brand_array = $adapter->findForBrand("com.splickit.cheeseshop");
        $homegrown_skin = $brand_array[0];
        $this->assertEquals($loyalty_vals, $homegrown_skin['loyalty_features'], "The array of loyalty features should contain the supports_history, supports_join and supports_link_card fields from the skin.");
    }

    function testBrandSpecificSignupFields()
    {
        $brand = Resource::createByData(new BrandAdapter($m), array("brand_id" => "8008"));
        $adapter = new SkinAdapter($m);
        $skin = Resource::createByData($adapter, array("external_identifier" => "com.splickit.saltypretzels", "brand_id" => $brand->brand_id));

        $mustard_field = Resource::createByData(new BrandSignupFieldsAdapter($m), array('field_name' => 'dijon', 'field_type' => 'text', 'brand_id' => $brand->brand_id, 'group_name' => 'saucy_group'));
        $thousand_island_field = Resource::createByData(new BrandSignupFieldsAdapter($m), array('field_name' => 'newmans', 'field_type' => 'text', 'brand_id' => $brand->brand_id, 'group_name' => 'saucy_group'));

        $brand_array = $adapter->findForBrand("com.splickit.saltypretzels");
        $salty_skin = $brand_array[0];

        $this->assertEquals(
            array(
                array("field_name" => $mustard_field->field_name, "field_type" => $mustard_field->field_type, "field_label" => $mustard_field->field_label, "field_placeholder" => $mustard_field->field_placeholder),
                array("field_name" => $thousand_island_field->field_name, "field_type" => $thousand_island_field->field_type, "field_label" => $thousand_island_field->field_label, "field_placeholder" => $thousand_island_field->field_placeholder)),
            $salty_skin['brand_fields'],
            "This skin should have a populated array of brand fields.");
    }

    function testBrandWithoutSignupFields()
    {
        $adapter = new SkinAdapter($m);
        $brand = Resource::createByData(new BrandAdapter($m), array("brand_id" => "8009"));
        $skin = Resource::createByData($adapter, array("external_identifier" => "com.splickit.saucysides", "brand_id" => $brand->brand_id));

        $brand_array = $adapter->findForBrand("com.splickit.saucysides");
        $saucy_skin = $brand_array[0];
        $this->assertEmpty($saucy_skin->brand_fields, "This skin should have an empty array of brand fields because no BrandSignupFields exist.");
    }

    function testMerchantsWithDelivery()
    {

        $adapter = new SkinAdapter($m);
        $skin = Resource::createByData($adapter, array("external_identifier" => "com.splickit.beefshop"));

        $merchant = Resource::createByData(new MerchantAdapter($m), array('name' => "Beef I", 'lat' => 0.0, 'state' => "CA", 'delivery' => false));
        Resource::createByData(new SkinMerchantMapAdapter($m), array('skin_id' => $skin->skin_id, 'merchant_id' => $merchant->merchant_id));

        $merchant2 = Resource::createByData(new MerchantAdapter($m), array('name' => "Beef II", 'state' => "CA", 'lat' => 0.0, 'delivery' => 'Y'));
        Resource::createByData(new SkinMerchantMapAdapter($m), array('skin_id' => $skin->skin_id, 'merchant_id' => $merchant2->merchant_id));

        $brand_array = $adapter->findForBrand("com.splickit.beefshop");
        $homegrown_skin = $brand_array[0];
        $this->assertEquals(true, $homegrown_skin['merchants_with_delivery'], "A skin for a brand with a merchant who supports delivery should have merchants_with_delivery set to true.");
    }

    function testMerchantsWithoutDelivery()
    {
        $adapter = new SkinAdapter($m);
        $skin = Resource::createByData($adapter, array("external_identifier" => "com.splickit.porkshop"));

        $merchant = Resource::createByData(new MerchantAdapter($m), array('name' => "Pork 1", 'lat' => 0.0, 'state' => "CA", 'delivery' => false));
        Resource::createByData(new SkinMerchantMapAdapter($m), array('skin_id' => $skin->skin_id, 'merchant_id' => $merchant->merchant_id));

        $merchant2 = Resource::createByData(new MerchantAdapter($m), array('name' => "Pork 2", 'lat' => 0.0, 'state' => "CA", 'delivery' => false));
        Resource::createByData(new SkinMerchantMapAdapter($m), array('skin_id' => $skin->skin_id, 'merchant_id' => $merchant2->merchant_id));

        $brand_array = $adapter->findForBrand("com.splickit.porkshop");
        $homegrown_skin = $brand_array[0];
        $this->assertEquals(false, $homegrown_skin['merchants_with_delivery'], "A skin for a brand without a merchant who supports delivery should have merchants_with_delivery set to false.");
    }

    static function setUpBeforeClass()
    {
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));

        ini_set('max_execution_time',300);
        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;

        $skin_resource = createWorldHqSkin();
        $ids['skin_id'] = $skin_resource->skin_id;

        $simple_menu_id = createTestMenuWithOneItem("item_one");
        $ids['simple_menu_id'] = $simple_menu_id;

        $menu_id = createTestMenuWithNnumberOfItems(5);
        $ids['menu_id'] = $menu_id;

        /*        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
                $modifier_group_id = $modifier_group_resource->modifier_group_id;
                $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
                assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
                assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
                assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);
        */
        $merchant_resource = createNewTestMerchant($menu_id);
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $ids['merchant_id'] = $merchant_resource->merchant_id;

        $user_resource = createNewUser(array("flags" => "1C20000001"));
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
    static function main()
    {
        $suite = new PHPUnit_Framework_TestSuite(__CLASS__);
        PHPUnit_TextUI_TestRunner::run($suite);
    }
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    SkinAdapterTest::main();
}

?>
