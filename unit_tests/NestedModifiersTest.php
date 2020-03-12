<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class NestedModifiersTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }

    function testNestedBreakup()
    {
        $cm = new CompleteMenu($this->ids['menu_id']);
        $cm->api_version = 2;
        $all_modifier_items = $cm->getAllModifierItemsForMenu($this->ids['menu_id'],$active);
        $modifier_groups = $cm->getModifierGroups($this->ids['menu_id'],'X',$mimetypes);
        $modifier_items = $all_modifier_items[$modifier_groups[0]['modifier_group_id']];
        $modifier_items_prices = $cm->getAllModifierItemPricesForMenu($this->ids['menu_id'], 'Y', $mimetypes);
        $this->assertCount(9,$modifier_items);
        $modifier_items_with_nested_for_menu_payload = $cm->formatBetterModifierItemArray($modifier_items,$modifier_items_prices);
        $this->assertCount(3,$modifier_items_with_nested_for_menu_payload,"should have been reduced to 3");
        return $modifier_items_with_nested_for_menu_payload;
    }

    /**
     * @depends testNestedBreakup
     */
    function testNestedItemsEachHaveThreeModifiers($modifier_items)
    {
        $this->assertCount(3,$modifier_items[0]['nested_items']);
        $this->assertCount(3,$modifier_items[1]['nested_items']);
        $this->assertCount(3,$modifier_items[2]['nested_items']);

    }

    function testMenuWithNestedModifiersMakeSureBasicStuffIsWorking()
    {
        $full_menu = $this->ids['full_menu'];
        $item_size_price_id = $full_menu['menu_types'][0]['menu_items'][0]['size_prices'][0]['item_size_id'];
        $size_id = $full_menu['menu_types'][0]['menu_items'][0]['size_prices'][0]['size_id'];
        $this->assertNotNull($item_size_price_id, "Item size price id should not be null.");
        $modifier_group = $full_menu['menu_types'][0]['menu_items'][0]['modifier_groups'][0];
        $this->assertNotNull($modifier_group, "should have found the new modifier group section");
        $this->assertTrue(isset($modifier_group['modifier_group_display_name']));
        $this->assertTrue(isset($modifier_group['modifier_group_credit']));
        $this->assertTrue(isset($modifier_group['modifier_group_max_price']));
        $this->assertTrue(isset($modifier_group['modifier_group_max_modifier_count']));
        $this->assertTrue(isset($modifier_group['modifier_group_min_modifier_count']));
        $this->assertTrue(isset($modifier_group['modifier_group_display_priority']));
    }

    function testModifierGroupShouldOnlyHaveThreeTopLevelItems()
    {
        $full_menu = $this->ids['full_menu'];
        $modifier_group = $full_menu['menu_types'][0]['menu_items'][0]['modifier_groups'][0];
        $modifier_group_json = json_encode($modifier_group);
        $this->assertCount(3,$modifier_group['modifier_items'],"Modifier group should only have 3 items because of nested");
    }

    function testModifierItemShouldEachHaveThreeNestedItems()
    {
        $full_menu = $this->ids['full_menu'];
        $modifier_items = $full_menu['menu_types'][0]['menu_items'][0]['modifier_groups'][0]['modifier_items'];
        $nested_one = $modifier_items[0]['nested_items'];
        $nested_two = $modifier_items[1]['nested_items'];
        $nested_three = $modifier_items[2]['nested_items'];
        $this->assertCount(3,$nested_one,"should have 3 nested items");
        $this->assertCount(3,$nested_two,"should have 3 nested items");
        $this->assertCount(3,$nested_three,"should have 3 nested items");
    }



    function testModifierItemNoModifierItemId()
    {
        $full_menu = $this->ids['full_menu'];
        $modifier_group = $full_menu['menu_types'][0]['menu_items'][0]['modifier_groups'][0];
        $modifier_item = $modifier_group['modifier_items'][0];
        $this->assertTrue(isset($modifier_item['modifier_item_name']));
        $this->assertfalse(isset($modifier_item['modifier_item_id']), "should not have found a modifier item id now");
    }

    function testModifierItemNoMaxAndMin()
    {
        $full_menu = $this->ids['full_menu'];
        $modifier_group = $full_menu['menu_types'][0]['menu_items'][0]['modifier_groups'][0];
        $modifier_item = $modifier_group['modifier_items'][0];
        $this->assertFalse(isset($modifier_item['modifier_item_max']));
        $this->assertFalse(isset($modifier_item['modifier_item_min']));
    }

    function testModifierItemShouldNotBePreselected()
    {
        $full_menu = $this->ids['full_menu'];
        $modifier_group = $full_menu['menu_types'][0]['menu_items'][0]['modifier_groups'][0];
        $modifier_item = $modifier_group['modifier_items'][0];
        $this->assertFalse(isset($modifier_item['modifier_item_pre_selected']));
    }

    function testModifierItemShouldNotHavePrices()
    {
        $full_menu = $this->ids['full_menu'];
        $modifier_group = $full_menu['menu_types'][0]['menu_items'][0]['modifier_groups'][0];
        $modifier_item = $modifier_group['modifier_items'][0];
        $this->assertFalse(isset($modifier_item['modifier_prices_by_item_size_id'][0]));
    }

    function testModifierItemShouldHaveNestedField()
    {
        $full_menu = $this->ids['full_menu'];
        $modifier_group = $full_menu['menu_types'][0]['menu_items'][0]['modifier_groups'][0];
        $modifier_item = $modifier_group['modifier_items'][0];
        $this->assertTrue(isset($modifier_item['nested_items']),"shoudl have a nested section");
        $this->assertCount(3,$modifier_item['nested_items'],"should have found 3 nested modifier items");
    }

    function testNestedItemShouldHaveModifierItemId()
    {
        $full_menu = $this->ids['full_menu'];
        $nested_modifier_item = $full_menu['menu_types'][0]['menu_items'][0]['modifier_groups'][0]['modifier_items'][0]['nested_items'][0];
        $this->assertTrue(isset($nested_modifier_item['modifier_item_id']), "should have found a modifier item id here in the nested item");
    }

    function testNestedItemShouldHaveMaxandMin()
    {
        $full_menu = $this->ids['full_menu'];
        $nested_modifier_item = $full_menu['menu_types'][0]['menu_items'][0]['modifier_groups'][0]['modifier_items'][0]['nested_items'][0];
        $this->assertTrue(isset($nested_modifier_item['modifier_item_max']), "should have found a modifier item MAX here in the nested item");
        $this->assertTrue(isset($nested_modifier_item['modifier_item_min']), "should have found a modifier item MIN here in the nested item");
    }

    function testNestedModifierItemShouldHavePrices()
    {
        $full_menu = $this->ids['full_menu'];
        $nested_modifier_item = $full_menu['menu_types'][0]['menu_items'][0]['modifier_groups'][0]['modifier_items'][0]['nested_items'][0];
        $this->assertTrue(isset($nested_modifier_item['modifier_prices_by_item_size_id']),"should have found a prices section in the nested modifier");
        $nested_modifier_price_record = $nested_modifier_item['modifier_prices_by_item_size_id'][0];
        $this->assertTrue(isset($nested_modifier_price_record['size_id']),"shoudl have found the size id in the mod price record in the nested item");
        $this->assertEquals(0.50,$nested_modifier_price_record['modifier_price'],"shoudl have found the price id in the mod price record in the nested item");
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
    	
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 9);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
        $modifier_options[TONIC_FIND_BY_METADATA] = array("modifier_group_id"=>$modifier_group_id);
        $modifier_item_resources = Resource::findAll(new ModifierItemAdapter($m),'',$modifier_options);
        $i = 0;
        $j = 1;
        foreach ($modifier_item_resources as $modifier_item_resource){
            if ($j==4) {
                $j = 1;
            }
            if ($i<3) {
                $modifier_item_resource->modifier_item_name = "modifier1=nested$j";
            } else if ($i<6) {
                $modifier_item_resource->modifier_item_name = "modifier2=nested$j";
            } else {
                $modifier_item_resource->modifier_item_name = "modifier3=nested$j";
            }
            $modifier_item_resource->save();
            $j++;
            $i++;
        }

        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 0);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 0);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 0);

    	$merchant_resource = createNewTestMerchant($menu_id);
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;

        $full_menu = CompleteMenu::getCompleteMenu($menu_id, 'Y', 0, 2);
        $ids['full_menu'] = $full_menu;
    	
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
    NestedModifiersTest::main();
}

?>