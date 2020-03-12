<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class CompleteMenuTest extends PHPUnit_Framework_TestCase {

    var $skin;
    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
    }

    function tearDown() {
        //delete your instance
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
    }

    function testMenuErrors()
    {
        $merchant_id = 12345;
        $menu_id = 67890;
        $error = "sum dumb message error text";
        $me_resource = MenuIntegrityErrorsAdapter::recordMenuError($menu_id,$merchant_id,$error);
        $this->assertNotNull($me_resource);
        $this->assertEquals(1,$me_resource->running_count,"It shoudl have recorded a count of 1");

        $me_resource2 = MenuIntegrityErrorsAdapter::recordMenuError($menu_id,$merchant_id,$error);
        $this->assertNotNull($me_resource2);
        $this->assertEquals(2,$me_resource2->running_count,"It shoudl have recorded a count of 2 since its the same error");
    }

    function testSpecificSizePricesOverrideDefaults() {
        $skin_resource = createWorldHqSkin();
        $skin_id = $skin_resource->skin_id;

        $menu = Resource::createByData(new MenuAdapter($m), array());
        $menu_id = $menu->menu_id;

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        attachMerchantToSkin($merchant_id, $skin_id);

        $mt = Resource::createByData(new MenuTypeAdapter($m), array('menu_id' => $menu_id));
        $item = Resource::createByData(new ItemAdapter($m), array('menu_type_id' => $mt->menu_type_id));
        $specific_size = Resource::createByData(new SizeAdapter($m), array('size_name' => 'Super', 'active' => true, 'menu_type_id' => $mt->menu_type_id));

        $specific_item_size = Resource::createByData(new ItemSizeAdapter($m), array('merchant_id' => $merchant_id, 'item_id' => $item->item_id, 'size_id' => $specific_size->size_id, 'price' => 5.49));


        $mg = Resource::createByData(new ModifierGroupAdapter($m), array('menu_id' => $menu_id));
        $mod = Resource::createByData(new ModifierItemAdapter($m), array('modifier_group_id' => $mg->modifier_group_id));
        $mod_id = $mod->modifier_item_id;
        $size_mod_price = Resource::createByData(new ModifierSizeMapAdapter($m), array('merchant_id' => $merchant_id, 'included_merchant_menu_types' => 'ALL', 'modifier_item_id' => $mod_id, 'size_id' => $specific_size->size_id, 'modifier_price' => 1.99, 'menu_id' => $menu_id));
        $default_mod_price = Resource::createByData(new ModifierSizeMapAdapter($m), array('merchant_id' => $merchant_id, 'included_merchant_menu_types' => 'ALL', 'modifier_item_id' => $mod_id, 'size_id' => 0, 'modifier_price' => 0.99, 'menu_id' => $menu_id));

        $modifier_price_records = ModifierSizeMapAdapter::staticGetRecords(array("modifier_item_id"=>$mod_id),'ModifierSizeMapAdapter');
        $this->assertCount(2,$modifier_price_records,"should have found two");
        $hash = createHashmapFromArrayOfArraysByFieldName($modifier_price_records,'size_id');
        $this->assertEquals('0.990',$hash[0]['modifier_price']);
        $this->assertEquals('1.990',$hash[$specific_size->size_id]['modifier_price']);

        Resource::createByData(new ItemModifierGroupMapAdapter($m), array('merchant_id' => $merchant_id, 'item_id' => $item->item_id, 'modifier_group_id' => $mg->modifier_group_id));

        $user = logTestUserIn($this->ids['user_id']);
        $request = new Request();
        $request->url = "/apiv2/merchants/$merchant_id?log_level=5";
        $request->method = 'GET';
        $merchant_controller = new MerchantController($mt, $user, $request, 5);
        $resource = $merchant_controller->processV2Request();



        $menu = $resource->menu;
        $response_modifier_prices = $menu['menu_types'][0]['menu_items'][0]['modifier_groups'][0]['modifier_items'][0]['modifier_prices_by_item_size_id'];
        $this->assertCount(1,$response_modifier_prices);
        $this->assertEquals(1.99,$response_modifier_prices[0]['modifier_price'],"price should have been the exact size prize, not the default price of .99");
        $this->assertEquals($specific_size->size_id,$response_modifier_prices[0]['size_id']);
    }

    function testNutritionFlag()
    {
      $menu = Resource::createByData(new MenuAdapter($m), array());
      $menu_id = $menu->menu_id;

      $merchant_resource = createNewTestMerchant($menu_id);

      $this->assertNotNull($merchant_resource->nutrition_flag);
      $this->assertEquals("2,000 calories a day is used for general nutrition advice, but calorie needs vary.",$merchant_resource->nutrition_message['all']);
      $this->assertEquals("1,200 - 1,400 calories a day is used for general nutrition advice for children ages 4-8 years, and 1,400 - 2,000 calories a day for children ages 9-13, but calorie needs vary.",$merchant_resource->nutrition_message['kids']);
    }

    static function setUpBeforeClass()
    {
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        ini_set('max_execution_time',300);
              SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;

/*        $skin_resource = createWorldHqSkin();
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
*/
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
    CompleteMenuTest::main();
}
?>