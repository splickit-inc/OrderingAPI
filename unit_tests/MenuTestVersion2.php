<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class MenuTestVersionTwo extends PHPUnit_Framework_TestCase
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

    function testGetAllUpsellMenuTypeMapsForMenu()
    {
        $mtu = new MenuTypeItemUpsellMapsAdapter(getM());
        $upsell_list = $mtu->getUpsellItemsForMenuByMenuType($this->ids['menu_id']);
        $this->assertCount(2,$upsell_list);
    }

    function testGetAllUpsellMenuTypeMapsForMenuByType()
    {
        $mtu = new MenuTypeItemUpsellMapsAdapter(getM());
        $upsell_list = $mtu->getUpsellItemsForMenuByMenuType($this->ids['menu_id']);
        $this->assertCount(3,$upsell_list[$this->ids['menu_type_id_1']],"this menu type should have 3 upsell items");
        $this->assertCount(2,$upsell_list[$this->ids['menu_type_id_2']],"this menu type should have 2 upsell items");
    }

    function testGetUpsellItemsAsPartOfFullMenu()
    {
        $full_menu = CompleteMenu::getCompleteMenu($this->ids['menu_id'],'Y',0,2);
        $menu_type_by_id = createHashmapFromArrayOfArraysByFieldName($full_menu['menu_types'],'menu_type_id');
        $upsell_item_ids = $menu_type_by_id[$this->ids['menu_type_id_1']]['upsell_item_ids'];
        $item_records = $this->ids['item_records'];
        $this->assertEquals($item_records[15]['item_id'],$upsell_item_ids[0]);
        $this->assertEquals($item_records[16]['item_id'],$upsell_item_ids[1]);
        $this->assertEquals($item_records[17]['item_id'],$upsell_item_ids[2]);

        $this->assertCount(3,$upsell_item_ids);
    }

    function testDoNotIncludeUpsellIdsThatAreNotCurrentlyActiveOnTheMenu()
    {
        SplickitCache::flushAll();
        $item_records = $this->ids['item_records'];
        $item_id = $item_records[15]['item_id'];
        $resource = Resource::find(new ItemAdapter(getM()),"$item_id");
        $resource->active = 'N';
        $resource->save();
        $full_menu = CompleteMenu::getCompleteMenu($this->ids['menu_id'],'Y',0,2);
        $menu_type_by_id = createHashmapFromArrayOfArraysByFieldName($full_menu['menu_types'],'menu_type_id');
        $upsell_item_ids = $menu_type_by_id[$this->ids['menu_type_id_1']]['upsell_item_ids'];
        $this->assertCount(2,$upsell_item_ids);
        $this->assertEquals($item_records[16]['item_id'],$upsell_item_ids[0]);
        $this->assertEquals($item_records[17]['item_id'],$upsell_item_ids[1]);
    }

    
    function testGetMenuVersion2() {
    	$full_menu = CompleteMenu::getCompleteMenu($this->ids['menu_id'],'Y',0,2);
    	$this->assertNull($full_menu['error_text']);
    	$this->assertNotNull($full_menu,"Should have found a menu we just created.");
    	$this->assertNull($full_menu['menu_types'][0]['sizes'],'SHould no longer have a sizes section since its now redundant');
    	$this->assertNull($full_menu['modifier_groups'],'Should no longer have modifier groups, they are in the item');
    	$item_size_price_id = $full_menu['menu_types'][0]['menu_items'][0]['size_prices'][0]['item_size_id'];
    	$size_id = $full_menu['menu_types'][0]['menu_items'][0]['size_prices'][0]['size_id'];
    	$this->assertNotNull($item_size_price_id,"Item size price id should not be null.");
    	$menu_items_by_item_id  = createHashmapFromArrayOfArraysByFieldName($full_menu['menu_types'][0]['menu_items'],'item_id');
    	ksort($menu_items_by_item_id);
    	$item = array_shift($menu_items_by_item_id);
    	$modifier_group = $item['modifier_groups'][0];
    	$this->assertNotNull($modifier_group,"should have found the new modifier group section");
    	$this->assertTrue(isset($modifier_group['modifier_group_display_name']));
    	$this->assertTrue(isset($modifier_group['modifier_group_credit']));
    	$this->assertTrue(isset($modifier_group['modifier_group_max_price']));
    	$this->assertTrue(isset($modifier_group['modifier_group_max_modifier_count']));
    	$this->assertTrue(isset($modifier_group['modifier_group_min_modifier_count']));
    	$this->assertTrue(isset($modifier_group['modifier_group_display_priority']));
		$modifier_item = $modifier_group['modifier_items'][0];
		$this->assertTrue(isset($modifier_item['modifier_item_id']));
		$this->assertTrue(isset($modifier_item['modifier_item_name']));
		$this->assertTrue(isset($modifier_item['modifier_item_max']));
		$this->assertTrue(isset($modifier_item['modifier_item_min']));
		$this->assertTrue(isset($modifier_item['modifier_item_pre_selected']));
		$this->assertTrue(isset($modifier_item['modifier_prices_by_item_size_id'][0]));
		$modifier_price = $modifier_item['modifier_prices_by_item_size_id'][0];
		$this->assertTrue(isset($modifier_price['size_id']));
		$this->assertEquals($size_id, $modifier_price['size_id']);
		$this->assertTrue(isset($modifier_price['modifier_price']));
		$this->assertEquals(.50, $modifier_price['modifier_price']);

		$upsell_item_ids = $full_menu['upsell_item_ids'];
		$ids = $this->ids;
		$this->assertNotNull($upsell_item_ids,"should have found an upsell section");
		$this->assertCount(2, $upsell_item_ids,"there should have been 2 upsell items");
		$this->assertTrue(($upsell_item_ids[0] == $ids['upsell_item_id1']) || ($upsell_item_ids[0] == $ids['upsell_item_id2'])," should have found 1 of the upsell items");
		$this->assertTrue(($upsell_item_ids[1] == $ids['upsell_item_id1']) || ($upsell_item_ids[1] == $ids['upsell_item_id2']), "should have round the other upsell item");

	}

  function testSetDefaultComesWithIfNonelisted()
  {
    $menu_id = createTestMenuWithNnumberOfItems(1);
    $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    $modifier_group_id = $modifier_group_resource->modifier_group_id;
    $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    $item_modifier_group_map_resource = assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 0);
    $item_modifier_group_map_resource->min = 1;
    $item_modifier_group_map_resource->save();
    $merchant_resource = createNewTestMerchant($menu_id);
    attachMerchantToSkin($merchant_resource->merchant_id, $this->ids['skin_id']);
    $full_menu = CompleteMenu::getCompleteMenu($menu_id,'Y',0,2);

    //should have defaulted a comes with for the item
    $modifier_items = $full_menu['menu_types'][0]['menu_items'][0]['modifier_groups'][0]['modifier_items'];
    $first_modifier_item = $modifier_items[0];
    $second_modifier_item = $modifier_items[1];

	  $this->assertEquals('no',$second_modifier_item['modifier_item_pre_selected']);
	  $this->assertEquals('no',$first_modifier_item['modifier_item_pre_selected'],"should have defaulted the first value to no since we are no longer auto selecting");
    //$this->assertEquals('yes',$first_modifier_item['modifier_item_pre_selected'],"should have defaulted the first value to yes since there is a required of greater then zero");


  }

    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',0);
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        //$mysqli->begin_transaction(); ;
    	
    	$skin_resource = createWorldHqSkin();
    	$ids['skin_id'] = $skin_resource->skin_id;
    	
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItemsAndMenuTypes(5,0,4);
    	$ids['menu_id'] = $menu_id;

    	$menu_types = MenuTypeAdapter::staticGetRecords(array("menu_id"=>$menu_id),'MenuTypeAdapter');
    	
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records_unsorted = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
    	$item_hash_by_ids = createHashmapFromArrayOfArraysByFieldName($item_records_unsorted,'item_id');
    	ksort($item_hash_by_ids);
        $item_records = [];
    	foreach ($item_hash_by_ids as $key=>$item) {
    	    $item_records[] = $item;
        }
        $ids['item_records'] = $item_records;
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);

		// create upsell
		$muia = new MenuUpsellItemMapsAdapter(getM());
		Resource::createByData($muia, array("menu_id"=>$menu_id,"item_id"=>$item_records[0]['item_id']));
		Resource::createByData($muia, array("menu_id"=>$menu_id,"item_id"=>$item_records[4]['item_id']));
		$ids['upsell_item_id1'] = $item_records[0]['item_id'];
		$ids['upsell_item_id2'] = $item_records[4]['item_id'];

		//create menutype upsells
        $mtiuma = new MenuTypeItemUpsellMapsAdapter(getM());
        Resource::createByData($mtiuma, array("menu_type_id"=>$menu_types[1]['menu_type_id'],"item_id"=>$item_records[15]['item_id']));
        Resource::createByData($mtiuma, array("menu_type_id"=>$menu_types[1]['menu_type_id'],"item_id"=>$item_records[16]['item_id']));
        Resource::createByData($mtiuma, array("menu_type_id"=>$menu_types[1]['menu_type_id'],"item_id"=>$item_records[17]['item_id']));

        Resource::createByData($mtiuma, array("menu_type_id"=>$menu_types[2]['menu_type_id'],"item_id"=>$item_records[18]['item_id']));
        Resource::createByData($mtiuma, array("menu_type_id"=>$menu_types[2]['menu_type_id'],"item_id"=>$item_records[19]['item_id']));

        $ids['menu_type_id_0'] = $menu_types[0]['menu_type_id'];
        $ids['menu_type_id_1'] = $menu_types[1]['menu_type_id'];
        $ids['menu_type_id_2'] = $menu_types[2]['menu_type_id'];
        $ids['menu_type_id_3'] = $menu_types[3]['menu_type_id'];



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
    MenuTestVersionTwo::main();
}

?>