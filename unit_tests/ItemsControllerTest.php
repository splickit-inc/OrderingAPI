<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class ItemsControllerTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $merchant_id;
    var $ids;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__ . '-' . $_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
    }

    function tearDown()
    {
        //delete your instance
        unset($this->ids);
        unset($this->merchant_id);
        $_SERVER['STAMP'] = $this->stamp;
    }

    function testGetNutritionUnitsFromLookup()
    {
        $nutrition_units = LookupAdapter::staticGetRecords(array("type_id_field" => 'nutritional_label'), 'LookupAdapter');
        $this->assertNotNull($nutrition_units);
        $this->assertCount(11, $nutrition_units);
        $this->assertContains('cal', $nutrition_units[0]['type_id_name']);
    }

    function testCreatesItemSizeAndNutritionInfo()
    {
        $skin_resource = createWorldHqSkin();
        $skin_id = $skin_resource->skin_id;

        //creates the menu
        $menu = Resource::createByData(new MenuAdapter($m), array());
        $menu_id = $menu->menu_id;

        //creates the merchants
        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        attachMerchantToSkin($merchant_id, $skin_id);

        //creates menu, item, and size
        $mt = Resource::createByData(new MenuTypeAdapter($m), array('menu_id' => $menu_id));
        $item = Resource::createByData(new ItemAdapter($m), array('menu_type_id' => $mt->menu_type_id,
            "item_name" => "Super Greek",
            "item_print_name" => "Super Greek Print",
        ));
        $this->assertNotNull($item);
        $this->assertNotNull($item->item_id);

        $specific_size = Resource::createByData(new SizeAdapter($m),
            array('size_name' => 'Regular Pita',
                'active' => true,
                'menu_type_id' => $mt->menu_type_id
            ));
        $specific_item_size = Resource::createByData(new ItemSizeAdapter($m),
            array('merchant_id' => $merchant_id,
                'item_id' => $item->item_id,
                'size_id' => $specific_size->size_id,
                'price' => 5.49
            ));
        $this->assertNotNull($specific_item_size);
        $this->assertNotNull($specific_item_size->item_size_id);

        //creates data for nutrition info table
        $data_nutrition_info = Resource::createByData(new NutritionItemSizeInfosAdapter($m),
            array('item_id' => $item->item_id,
                'size_id' => $specific_size->size_id,
                'serving_size' => '1/2 Sandwich',
                'calories' => 10,
                'calories_from_fat' => 13,
                'total_fat' => 28.5,
                'saturated_fat' => 32.12,
                'trans_fat' => 15.16,
                'cholesterol' => 16.78,
                'sodium' => 12.36,
                'total_carbohydrates' => 25.89,
                'dietary_fiber' => 16.45,
                'sugars' => 46.98,
                'protein' => 200
            ));

        $this->assertNotNull($data_nutrition_info);
        $this->assertNotNull($data_nutrition_info->id);

        return array('item_id' => $item->item_id, 'size_id' => $specific_item_size->size_id);
    }

    /**
     * @depends testCreatesItemSizeAndNutritionInfo
     */
    function testGetNutritionDataFromNutritionInfos($ids)
    {
        $nutrition_item_size_infos_adapter = new NutritionItemSizeInfosAdapter($m);
        $nutrition_info = $nutrition_item_size_infos_adapter->retrieveNutritionSizesInfo($ids['item_id'], $ids['size_id']);
        $this->assertNotNull($nutrition_info);
        $label_hash = createHashmapFromArrayOfArraysByFieldName($nutrition_info,'label');
        $this->assertNotNull($label_hash['Serving Size']);
        $this->assertCount(12, $label_hash);
        //$this->assertEquals('Cholesterol', $label_hash['Cholesterol']['label']);
        $this->assertEquals('1/2 Sandwich', $label_hash['Serving Size']['value']);
        $this->assertEquals('16.8mg', $label_hash['Cholesterol']['value']);
    }
    
    function testGetNutritionDataFromNutritionInfosFailureCase()
    {
        $nutrition_item_size_infos_adapter = new NutritionItemSizeInfosAdapter($m);
        $nutrition_info = $nutrition_item_size_infos_adapter->retrieveNutritionSizesInfo(3656, 1493);
        $this->assertNotNull($nutrition_info);
        $this->assertCount(0, $nutrition_info);
    }

    /**
     * @depends testCreatesItemSizeAndNutritionInfo
     */
    function testEndPointToGetNutritionInfo($ids)
    {
        //creates user
        $user_resource = createNewUser();
        $user = logTestUserResourceIn($user_resource);

        //call end point
        $request = createRequestObject("/apiv2/items/" . $ids['item_id'] . "/nutrition?size_id=" . $ids['size_id'],"GET");

        //creates items controller sending data
        $items_controller = new ItemsController($m, $user, $request);
        $response = $items_controller->processV2Request();

        //creates asserts for recovery nutrition data to retrieve to clients
        $this->assertNotNull($response->nutrition_info);
        $this->assertCount(12, $response->nutrition_info);
        $this->assertEquals('Super Greek', $response->item_name);
        $this->assertEquals('Regular Pita', $response->item_size);
        $this->assertEquals('Serving Size', $response->nutrition_info[0]['label']);
        $this->assertEquals('1/2 Sandwich', $response->nutrition_info[0]['value']);
    }

    /**
     * @depends testCreatesItemSizeAndNutritionInfo
     */
    function testEndPointToGetNutritionInfoWithNulls($ids)
    {
        $user_resource = createNewUser();
        $user = logTestUserResourceIn($user_resource);

        // update nutrition record to have nulls
        $nsia = new NutritionItemSizeInfosAdapter();
        $options[TONIC_FIND_BY_METADATA] = $ids;
        $nsi_resource = Resource::find($nsia,null,$options);
        $nsi_resource->trans_fat = 'nullit';
        $nsi_resource->sugars = 'nullit';
        $nsi_resource->save();

        $nsi_refreshed = $nsi_resource->refreshResource();
        $this->assertNull($nsi_refreshed->trans_fat);
        $this->assertNull($nsi_refreshed->sugars);

        //call end point
//        $request = new Request();
//        $request->url = "/apiv2/items/" . $ids['item_id'] . "?size_id=" . $ids['size_id'];
//        $request->method = "GET";

        $request = createRequestObject("/apiv2/items/" . $ids['item_id'] . "?size_id=" . $ids['size_id'],"GET");

        //creates items controller sending data
        $items_controller = new ItemsController($m, $user, $request);
        $response = $items_controller->processV2Request();

        $nutrition_info = $response->nutrition_info;
        $nutrition_hash_map_by_label = createHashmapFromArrayOfArraysByFieldName($nutrition_info,'label');
        $this->assertEquals("Not Available",$nutrition_hash_map_by_label['Trans Fat']['value'],"It should return the Not Available for the null entry");
        $this->assertEquals("Not Available",$nutrition_hash_map_by_label['Sugars']['value'],"It should return the Not Available for the null entry");
    }

    function testEndPointToGetNutritionInfoFailure()
    {
        $skin_resource = createWorldHqSkin();
        //creates user
        $user_resource = createNewUser();
        $user = logTestUserResourceIn($user_resource);
        //call end point
        $request = new Request();
        $request->url = "/apiv2/items/3656?size_id=1493";

        //creates items controller sending data
        $items_controller = new ItemsController($m, $user, $request);
        $response = $items_controller->processV2Request();

        $this->assertNotNull($response->message);
        $this->assertEquals("There is no nutrition information for this item", $response->message);
    }

    function testRegExp(){
        $regular_expression = "%/items/([0-9]{4,10})\?size_id=([0-9]{4,10})%";
        $result = preg_match($regular_expression, '/apiv2/items/3656?size_id=1493');
        $this->assertEquals(1, $result);
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
        $mysqli->begin_transaction(); ;
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
    ItemsControllerTest::main();
}
?>
