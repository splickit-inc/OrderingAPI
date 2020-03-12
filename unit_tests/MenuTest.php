<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class MenuTest extends PHPUnit_Framework_TestCase
{
	
	var $stamp;
	var $ids;

	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		logTestUserIn(2);
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->stamp);
		unset($this->ids);
    }
    
    function testLTOforMenuTypeNoReplacement()
    {
    	$menu_data = $this->createMenuWithMenuTypeLTO();
    	$menu_id = $menu_data['menu_id'];

        $complete_menu = new CompleteMenu($menu_id);
    	$ltos = $complete_menu->getLTOs($menu_id, 0, true);
		$this->assertNotNull($ltos,"should hvae found an LTO");
		
    	$complete_menu = new CompleteMenu($menu_id);
    	$menu_types = $complete_menu->getMenuTypesItemsPrices($menu_id, 'Y', $mimetypes,0,true, $ltos);
    	$this->assertCount(2, $menu_types);
    	return $menu_data;
    }
    
    /**
     * @depends testLTOforMenuTypeNoReplacement
     */
    function testLTOforMenuWithReplacement($menu_data)
    {
    	$menu_id = $menu_data['menu_id'];
    	$menu_change_id = $menu_data['menu_change_id'];
    	$menu_change_resource = SplickitController::getResourceFromId($menu_change_id, "MenuChangeSchedule");
    	$menu_change_resource->replace_id = $menu_data['regular_menu_type_id'];
    	$menu_change_resource->save();

        $complete_menu = new CompleteMenu($menu_id);
        $ltos = $complete_menu->getLTOs($menu_id, 0, true);

        $this->assertNotNull($ltos,"should hvae found an LTO");
		
    	$complete_menu = new CompleteMenu($menu_id);
    	$menu_types = $complete_menu->getMenuTypesItemsPrices($menu_id, 'Y', $mimetypes,0,true, $ltos);
    	$this->assertCount(1, $menu_types);
    	
    }
    
    /**
     * @expectedException Exception 
     */
    function testCompleteMenuBadMenuId()
    {
    	$complete_menu = new CompleteMenu();
    }
    
    function testGetMenuStatusNullMenuId()
    {
    	$request = new Request();
    	$request->url = "/phone/menustatus/null";
    	$resource = CompleteMenu::getMenuStatus($request, $mimetypes);
    	$this->assertEquals("Null Menu Id submitted for status call", $resource->error);
    	$this->assertEquals(422,$resource->http_code);
    }
    
    function testGetMenuStatusNoMatchingMenuId()
    {
    	$request = new Request();
    	$request->url = "/phone/menustatus/999999";
    	$resource = CompleteMenu::getMenuStatus($request, $mimetypes);
    	$this->assertEquals("NO EXISTING MENU FOR THIS ID", $resource->error);
    	$this->assertEquals(422,$resource->http_code);
    }
    
    function testGetMenuStatusGoodMenu()
    {
    	$request = new Request();
    	$request->url = "/phone/menustatus/".$this->ids['menu_id'];
    	$resource = CompleteMenu::getMenuStatus($request, $mimetypes);
    	$this->assertEquals($this->ids['menu_id'], $resource->menu_id);
    }
    
    function testGetSizes()
    {
    	$menu = CompleteMenu::getCompleteMenu($this->ids['menu_id']);
    	$complete_menu = new CompleteMenu($menu['menu_id']);
    	$sizes = $complete_menu->getSizes($menu['menu_types'][0]['menu_type_id'],'Y',null);
    	$this->assertCount(1, $sizes);
    	return $complete_menu;
    }
    
    /**
     * @depends testGetSizes
     */
    function testGetMenuModifiersSizePrices($complete_menu)
    {
    	$modifier_item_sizes = $complete_menu->getAllModifierItemSizeResources($this->ids['menu_id'],$active,0);
    	$this->assertCount(12, $modifier_item_sizes);
    }
    
    /**
     * @depends testGetSizes
     */
    function testGetMenuItems($complete_menu)
    {
    	$menu = CompleteMenu::getCompleteMenu($this->ids['menu_id']);
    	$items = $complete_menu->getMenuItems($menu['menu_types'][0]['menu_type_id']);
    	$this->assertCount(5, $items);
    }
    
    /**
     * @depends testGetSizes
     */
    function testGetMenuGetModifierItems($complete_menu)
    {
    	$menu = CompleteMenu::getCompleteMenu($this->ids['menu_id']);
    	$modifier_items = $complete_menu->getModifierItems($menu['modifier_groups'][0]['modifier_group_id']);
    	$this->assertCount(10, $modifier_items);
    }
    
    /**
     * @depends testGetSizes
     */
    function testGetMenuGetModifierGroups($complete_menu)
    {
    	$modifier_groups = $complete_menu->getModifierGroups($this->ids['menu_id'], 'Y',$mimetypes);
    	$this->assertCount(2, $modifier_groups);
    }

    function testCreateTestMenu(){
    	$menu_id = createTestMenuWithOneItem($name = 'Adam and Dave Rock');
    	$full_menu = CompleteMenu::getCompleteMenu($menu_id);
    	$this->assertNull($full_menu['error_text']);
    	$this->assertNotNull($full_menu,"Should have found a menu we just created.");
    	$this->assertEquals($name, $full_menu['menu_types'][0]['menu_items'][0]['item_name']);
    	$this->assertEquals("Test Size 1", $full_menu['menu_types'][0]['sizes'][0]['size_name']);
    	$item_size_price_id = $full_menu['menu_types'][0]['menu_items'][0]['size_prices'][0]['item_size_id'];
    	$this->assertNotNull($item_size_price_id,"Item size price id should not be null.");
    	
    	return $menu_id;
    	
    }
    
    /**
     * @depends testCreateTestMenu
     */
    function testErrorTextForInnactiveModifierGroupButAssociatedToItemWithAMinOfGreaterThanZero($menu_id) {
    	$merchant_resource = createNewTestMerchant($menu_id);
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	//$menu_caching_string = "menu-".$menu_id."-Y-".$merchant_id;
    	//$this->deleteMenuCachingString($menu_caching_string);
    	MenuAdapter::touchMenu($menu_id,time()+1);

    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	$item_modifier_group_map_resource = assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 1);
    	$full_menu = CompleteMenu::getCompleteMenu($menu_id,'Y',0);
    	$this->assertNull($full_menu['error_text']);
    	
    	//$menu_caching_string = "menu-".$menu_id."-Y-".$merchant_id;
    	//$this->deleteMenuCachingString($menu_caching_string);
    	MenuAdapter::touchMenu($menu_id,time()+2);
		
    	// now set modifier_group to innactive
    	$modifier_group_resource->active = 'N';
    	$modifier_group_resource->save();
    	
    	// still should not get an error
    	$full_menu = CompleteMenu::getCompleteMenu($menu_id,'Y',0);
    	$this->assertNull($full_menu['error_text']);
    	
    	//$menu_caching_string = "menu-".$menu_id."-Y-".$merchant_id;
    	//$this->deleteMenuCachingString($menu_caching_string);
    	MenuAdapter::touchMenu($menu_id,time()+3);
		
    	// now set min to 0 and should get the error
    	$item_modifier_group_map_resource->min = 1;
    	$item_modifier_group_map_resource->save();
    	$full_menu = CompleteMenu::getCompleteMenu($menu_id,'Y',0);
    	$this->assertNotNull($full_menu['error_text']);
    	myerror_log("error Text: ".$full_menu['error_text']);
    	$error_message = "Innactive modifier group but min greater than 0 on map record";
    	$this->assertTrue(substr_count($full_menu['error_text'],$error_message) > 0);
    	$map_string = "item_modifier_group_map_id = ".$item_modifier_group_map_resource->map_id;
    	$group_string = "modifier_group_id = ".$modifier_group_resource->modifier_group_id;
    	$this->assertTrue(substr_count($full_menu['error_text'],$map_string) > 0);
    	$this->assertTrue(substr_count($full_menu['error_text'],$group_string) > 0);
    	
    	// now set modifier group to active and the error should go away.
    	$modifier_group_resource->active = 'Y';
    	$modifier_group_resource->save();
    	MenuAdapter::touchMenu($menu_id,time()+4);
    	$full_menu = CompleteMenu::getCompleteMenu($menu_id,'Y',0);
    	$this->assertNull($full_menu['error_text']);
    	return $menu_id;
    }
    
    /**
     * @depends testErrorTextForInnactiveModifierGroupButAssociatedToItemWithAMinOfGreaterThanZero
     */
	function testErrorTextForMenuTypeWithNoActiveSizes($menu_id)
	{
		MenuAdapter::touchMenu($menu_id,time()+5);
    	$menu_type_resource = createNewMenuType($menu_id, 'Test Menu Type 2');
    	$size_resource = createNewSize($menu_type_resource->menu_type_id, 'Test Size 2');
    	$item_resource = createItem($item_name, $size_resource->size_id, $menu_type_resource->menu_type_id);
    	$full_menu = CompleteMenu::getCompleteMenu($menu_id,'Y',0);
    	$this->assertNull($full_menu['error_text']);
    	$this->assertCount(2, $full_menu['menu_types']);
    	
    	MenuAdapter::touchMenu($menu_id,time()+6);
    	//now set size to innactive
    	$size_resource->active = 'N';
    	$size_resource->save();
    	$full_menu2 = CompleteMenu::getCompleteMenu($menu_id,'Y',0);
    	$this->assertNotNull($full_menu2['error_text']);
    	$this->assertCount(1,$full_menu2['menu_types']);
    	$error_text = "No active sizes for active menutype! menu_type_id: ".$menu_type_resource->menu_type_id;
    	myerror_log($full_menu2['error_text']);
    	$this->assertTrue(substr_count($full_menu2['error_text'],$error_text) > 0);
    	
    	// now add another size
    	$size_resource2 = createNewSize($menu_type_resource->menu_type_id, 'Test Size 3');
    	//create the new item size map
    	Resource::createByData(new ItemSizeAdapter($mimetypes),array('size_id'=>$size_resource2->size_id, 'item_id'=>$item_resource->item_id,'price'=>2.50,'tax_group'=>1));
    	
    	MenuAdapter::touchMenu($menu_id,time()+7);
    	$full_menu3 = CompleteMenu::getCompleteMenu($menu_id,'Y',0);
    	$this->assertNull($full_menu3['error_text']);
    	$this->assertCount(2,$full_menu3['menu_types']);

	}

/*    function testGetMenuStatusFromDispatch()
    {
		$url = "http://localhost:8888/app2/phone/menustatus/102433/?log_level=5";
    	$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		//curl_setopt($curl, CURLOPT_USERPWD, "admin:welcome"); 	
		$result = curl_exec($curl);
		$headers = get_headers($url);
		curl_close($curl);
		$result_data = json_decode($result,true);
		$this->assertNotNull($result_data['menu_key']);
    	
    }
*/    
    function testMenuCachingV1()
    {
    	$ids = $this->ids;
    	$merchant_id = $ids['merchant_id'];
    	$menu_id = $ids['menu_id'];
    	$show_active_only = 'Y';
    	    	
    	$time1 = microtime(true);
    	$menu = CompleteMenu::getCompleteMenu($menu_id,$show_active_only,$merchant_id);
    	$time2 = microtime(true);
    	$total_time_no_cache = ($time2-$time1);
    	myerror_log("time for getting the menu with no cach: $total_time_no_cache seconds");
    	
    	// now log a regular user in so that caching works
    	$user_resource = createNewUser();
    	logTestUserResourceIn($user_resource);
    	
    	$time3 = microtime(true);
    	$menu = CompleteMenu::getCompleteMenu($menu_id,$show_active_only,$merchant_id);
    	$time4 = microtime(true);
    	$total_time_with_cache = ($time4-$time3);
    	myerror_log("time for getting the menu with CACH: $total_time_with_cache seconds");

    	$this->assertTrue($total_time_no_cache > $total_time_with_cache);

    }
	
/*	
  	function testImportFromProd()
	{
		$menu_adapter = new MenuAdapter($mimetypes);
		$menu_adapter->importMenuFromProd(102433);
	}
//*/	
/*	function testGetMenuWithMessageGroup()
	{
		$menu = CompleteMenu::getCompleteMenu(102433,'Y',1093);
		myerror_log("got the full menu");
	}
*/	
    
    function testGetLTOs()
    {
		// first set menu schedule to today
		$ids = $this->ids;
		$merchant_id = $ids['merchant_id'];
		$merchant_id = 0;
		$menu_id = $ids['menu_id'];
		$menu_change_schedule_resource = SplickitController::getResourceFromId($ids['menu_change_id'], "MenuChangeSchedule");
		$menu_change_schedule_resource->active = 'Y';
		$menu_change_schedule_resource->save();

        $complete_menu = new CompleteMenu($menu_id);
        $ltos = $complete_menu->getLTOs($menu_id, $merchant_id, true);
        
		$this->assertNotNull($ltos,"should hvae found and LTO");
		$this->assertEquals(1, count($ltos),"shoujdl have only found 1 lto");
		return ($menu_change_schedule_resource);
    }
	
    /**
     * 
     * @depends testGetLTOs
     * @param $menu_change_schedule_resource
     */
	function testLTOItem($menu_change_schedule_resource)
	{
		$ids = $this->ids;
		$merchant_id = $ids['merchant_id'];
    	$menu_id = $ids['menu_id'];
    	$show_active_only = 'Y';
    	
    	$menu_caching_string = "menu-".$menu_id."-".$show_active_only."-".$merchant_id."V1";
    	PhpFastCache::$storage = "files";
    	PhpFastCache::delete($menu_caching_string);
    	
		// so now make sure we have the LTO
		$menu = CompleteMenu::getCompleteMenu($menu_id,'Y',$merchant_id);
		$time = $menu['time_to_retrieve_menu'];
		myerror_log("time to retrieve menu is: ".$time);
		foreach ($menu['menu_types'][0]['menu_items'] as $item)
		{
			if ($item['item_name'] == "LTO Item")
				$lto = $item;
			else if ($item['item_name'] == "Test Item 1")
				$replaced_item = $item;
		}
		$this->assertNotNull($lto);
		$this->assertNull($replaced_item);
		
		$menu_change_schedule_resource->day_of_week = date('w')+2;
		$menu_change_schedule_resource->save();
		
		// now make sure we DO NOT have the LTO
		PhpFastCache::$storage = "files";
    	PhpFastCache::delete($menu_caching_string);
		$menu = CompleteMenu::getCompleteMenu($menu_id,'Y',$merchant_id);
		$time = $menu['time_to_retrieve_menu'];
		myerror_log("time to retrieve menu is: ".$time);
		foreach ($menu['menu_types'][0]['menu_items'] as $item)
		{
			if ($item['item_name'] == "LTO Item")
				$lto2 = $item;
			else if ($item['item_name'] == "Test Item 1")
				$replaced_item2 = $item;
		}
		$this->assertNotNull($replaced_item2);
		$this->assertNull($lto2);

	}

	function testGetAllSizesForMenu()
	{
		$all_sizes = CompleteMenu::getAllSizes($this->ids['menu_id'],'Y', $mimetypes);
		$this->assertEquals(1, sizeof($all_sizes, $mode),"should be only 1 size");
	}
	
	function testGetAllMenuItemsForMenu()
	{
		$complete_menu = new CompleteMenu($this->ids['menu_id']);
		$all_items = $complete_menu->getAllMenuItems($menu_id, 'Y', $mimetypes);
		$this->assertEquals(1, count($all_items));
		$menu_items = array_pop($all_items);
		$this->assertEquals(4, count($menu_items),"should only be 4 items since the LTO is not returend in this list");
	}
	
	function testGetAllMenuItemPricesForMenu()
	{
		$complete_menu = new CompleteMenu($this->ids['menu_id']);
		$all_size_prices = $complete_menu->getAllMenuItemSizePrices($menu_id, 'Y', 0, $mimetypes);
		$this->assertEquals(5, count($all_size_prices));
		foreach ($all_size_prices as $size_price_array)
		{
			$this->assertEquals(1, count($size_price_array));
		}
	}
	
	function testCompleteMenuPhotos() {
		$complete_menu = CompleteMenu::getCompleteMenu($this->ids['menu_id']);
		$items = $complete_menu['menu_types'][0]['menu_items'];
				
		foreach($items as $item) {
			$item_id = $item['item_id'];			
			$this->assertNotNull($item['photos'], "Item ".$item['item_id']." should have photos.");			
		}
	}
	
	function testGetMenuUpsellItems()
	{
		$complete_menu = new CompleteMenu($this->ids['menu_id']);
		$upsell_item_ids =  $complete_menu->getUpsellItemIdsAsArray($menu_id, 'Y');
		$this->assertNotNull($upsell_item_ids);
		$this->assertCount(2, $upsell_item_ids);
	}
	
	function testGetCompleteMenuWithUpsellData()
	{
		$ids = $this->ids;
		$complete_menu = CompleteMenu::getCompleteMenu($this->ids['menu_id']);
		$upsell_item_ids = $complete_menu['upsell_item_ids'];
		$this->assertNotNull($upsell_item_ids,"should have found an upsell section");
		$this->assertCount(2, $upsell_item_ids,"there should have been 2 upsell items");
		$this->assertTrue(($upsell_item_ids[0] == $ids['upsell_item_id1']) || ($upsell_item_ids[0] == $ids['upsell_item_id2'])," should have found 1 of the upsell items");
		$this->assertTrue(($upsell_item_ids[1] == $ids['upsell_item_id1']) || ($upsell_item_ids[1] == $ids['upsell_item_id2']), "should have round the other upsell item");
	}

	function testGetCompleteMenuWithUpsellSectionButNoData()
	{
		$menu_id = createTestMenuWithNnumberOfItems(5);
		$complete_menu = CompleteMenu::getCompleteMenu($menu_id);
		$this->assertTrue(isset($complete_menu['upsell_item_ids']));
		$this->assertCount(0, $complete_menu['upsell_item_ids']);
	}
	
	function testItemWithCalories(){
		$menu_id = createTestMenuWithCaloriesInfo(5);
		$complete_menu = CompleteMenu::getCompleteMenu($menu_id);
		$all_items = $complete_menu['menu_types'][0]['menu_items'];
		$this->assertEquals(5, count($all_items));
		$first_item = $all_items[0];
		$this->assertTrue(isset($first_item['calories']));
		$this->assertEquals('100 - 200 Cal', $first_item['calories']);
	}
		
	/*
	function testGetMenu()
	{
		
		$menu = CompleteMenu::getCompleteMenu(102336,'Y',1004);
		$time = $menu['time_to_retrieve_menu'];
		myerror_log("time to retrieve menu is: ".$time);
		//$this->assertTrue($time < 2,"$time is not less than 2");
		$this->assertEquals(10, sizeof($menu['menu_types']));
		if (date('w') == 1)
			$this->assertEquals("MOES MONDAYS",$menu['menu_types'][0]['menu_type_name']);
		else
			$this->assertEquals("BURRITOS",$menu['menu_types'][0]['menu_type_name']);
		$this->assertEquals(3,sizeof($menu['menu_types'][0]['menu_items'], $mode));
		$this->assertEquals("Joey Bag of Donuts",$menu['menu_types'][0]['menu_items'][1]['item_name']);
		$this->assertEquals("Original",$menu['menu_types'][0]['menu_items'][1]['size_prices'][0]['size_name']);			
		if (date('w') == 1)
			$this->assertEquals("5.00",$menu['menu_types'][0]['menu_items'][1]['size_prices'][0]['price']);
		else 
			$this->assertEquals("6.49",$menu['menu_types'][0]['menu_items'][1]['size_prices'][0]['price']);			

		$this->assertEquals("Junior",$menu['menu_types'][0]['menu_items'][1]['size_prices'][1]['size_name']);			
		
		if (date('w') == 1)
			$this->assertEquals("5.00",$menu['menu_types'][0]['menu_items'][1]['size_prices'][0]['price']);
		else 
			$this->assertEquals("5.59",$menu['menu_types'][0]['menu_items'][1]['size_prices'][1]['price']);
		
		if (date('w') == 1)
			$this->assertEquals(5,sizeof($menu['menu_types'][0]['menu_items'][1]['allowed_modifier_groups']));
		else	
			$this->assertEquals(6,sizeof($menu['menu_types'][0]['menu_items'][1]['allowed_modifier_groups']));
		$this->assertEquals(235545, $menu['menu_types'][0]['menu_items'][1]['allowed_modifier_groups'][1]['modifier_group_id']);	
		$this->assertEquals(5,sizeof($menu['menu_types'][0]['menu_items'][1]['comes_with_modifier_items']));
		$comes_with = $menu['menu_types'][0]['menu_items'][1]['comes_with_modifier_items'];
		foreach ($comes_with as $cw_record)
		{
			$id = $cw_record['modifier_item_id'];
			$ids[$id] = $id;
		}
		$this->assertNotNull($ids['1602019']);
		$this->assertNotNull($ids['1602149']);
		$this->assertNotNull($ids['1602053']);
		$this->assertNotNull($ids['1602055']);
		$this->assertNotNull($ids['1602037']);
		
//		$this->assertEquals(1602037, $menu['menu_types'][0]['menu_items'][1]['comes_with_modifier_items'][1]['modifier_item_id']);	
		
		// now check modifier groups
		if (date('w') == 1)
		{
			$this->assertEquals(12, sizeof($menu['modifier_groups'], $mode));
			$i=7;
		}	
		else	
		{
			$this->assertEquals(12, sizeof($menu['modifier_groups'], $mode));
			$i=7;
		}	
		
		$this->assertEquals('fresh free ingredients ', $menu['modifier_groups'][$i]['modifier_group_name']);
		$this->assertEquals(20, sizeof($menu['modifier_groups'][$i]['modifier_items']));
		$this->assertEquals('shredded cheese', $menu['modifier_groups'][$i]['modifier_items'][4]['modifier_item_name']);
		$this->assertEquals('0.00', $menu['modifier_groups'][$i]['modifier_items'][4]['modifier_size_maps'][0]['modifier_price']);
		$this->assertEquals('meat', $menu['modifier_groups'][4]['modifier_group_name']);
		$this->assertEquals(5, sizeof($menu['modifier_groups'][4]['modifier_items']));
		$this->assertEquals('pork', $menu['modifier_groups'][4]['modifier_items'][2]['modifier_item_name']);
		$this->assertEquals(11, sizeof($menu['modifier_groups'][4]['modifier_items'][2]['modifier_size_maps'], $mode));
		$taco_size_exists_for_pork = false;
		$fajita_size_exists_for_pork = false;
		foreach ($menu['modifier_groups'][4]['modifier_items'][2]['modifier_size_maps'] as $size_price_record)
		{
			if ($size_price_record['size_id'] == 70005)
			{
				$taco_size_exists_for_pork = true;
				$pork_price_for_taco = $size_price_record['modifier_price'];
			}
			if ($size_price_record['size_id'] == 70015)
			{
				$fajita_size_exists_for_pork = true;
				$pork_price_for_fajita = $size_price_record['modifier_price'];
			}
		}
		$this->assertEquals('0.30', $pork_price_for_taco);
		$this->assertEquals('1.00', $pork_price_for_fajita);
		
	}
*/
	
	function testModifierShutOff()
	{
		$ids = $this->ids;
		//lets shut off add ons and see if we get the correct menu
		MenuAdapter::touchMenu($ids['menu_id']);
		$menu_initial = CompleteMenu::getCompleteMenu($ids['menu_id'],'Y',0);
		//number of modifiers for the second item
		$number_allowed = $menu_initial['menu_types'][0]['menu_items'][1]['allowed_modifier_groups'];
		$this->assertEquals(2, count($number_allowed));
		$modifier_group_resource = Resource::find(new ModifierGroupAdapter($mimetypes),$ids['modifier_group_id2']);
		$modifier_group_resource->active = 'N';
		$modifier_group_resource->save();
		MenuAdapter::touchMenu($ids['menu_id'],time()+1);
		$menu = CompleteMenu::getCompleteMenu($ids['menu_id'],'Y',0);
		$time = $menu['time_to_retrieve_menu'];
		myerror_log("time to retrieve menu is: ".$time);

		$this->assertEquals(1,sizeof($menu['menu_types'][0]['menu_items'][1]['allowed_modifier_groups']));
	}
	
	private function deleteMenuCachingString($menu_caching_string)
	{
    	PhpFastCache::$storage = "files";
    	PhpFastCache::delete($menu_caching_string);
		
	}
	
	private function createMenuWithMenuTypeLTO()
	{
		$menu_id = createTestMenuWithNnumberOfItemsAndMenuTypes(1,$menu_id,2);
		$menu = CompleteMenu::getCompleteMenu($menu_id);
		$menu_type_2_id = $menu['menu_types'][1]['menu_type_id'];

		$menu_type_resource = SplickitController::getResourceFromId($menu_type_2_id, "MenuType");
    	$menu_type_resource->menu_type_name = "LTO Menu Type";
    	$menu_type_resource->active = "L";
    	$menu_type_resource->save();
    	
    	$mcs_adapter = new MenuChangeScheduleAdapter($mimetypes);
    	$mcs_data['menu_id'] = $menu_id;
    	$mcs_data['object_type'] = "menu_type";
    	$mcs_data['object_id'] = $menu_type_2_id;
    	$mcs_data['day_of_week'] = date('w')+1;
    	$mcs_data['active'] = 'Y';
    	$mcs_resource = Resource::createByData($mcs_adapter, $mcs_data);
    	$menu_change_id = $mcs_resource->menu_change_id;

    	//$new_resource = SplickitController::getResourceFromId($mcs_resource->menu_change_id, "MenuChangeSchedule");
    	$test_menu_data['menu_id'] = $menu_id;
    	$test_menu_data['menu_change_id'] = $menu_change_id;
    	$test_menu_data['lto_menu_type_id'] = $menu_type_2_id;
    	$test_menu_data['regular_menu_type_id'] = $menu['menu_types'][0]['menu_type_id'];
    	return $test_menu_data;
	}

    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	$_SERVER['request_time1'] = microtime(true);    	
	
		$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;
    	
    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_resource2 = createModifierGroupWithNnumberOfItems($menu_id, 2);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$modifier_group_id2 = $modifier_group_resource2->modifier_group_id;
    	$ids['modifier_group_id2'] = $modifier_group_id2;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id2, 0);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);

    	// create upsell
    	$muia = new MenuUpsellItemMapsAdapter($mimetypes);
    	Resource::createByData($muia, array("menu_id"=>$menu_id,"item_id"=>$item_records[0]['item_id']));
    	Resource::createByData($muia, array("menu_id"=>$menu_id,"item_id"=>$item_records[4]['item_id']));
    	$ids['upsell_item_id1'] = $item_records[0]['item_id'];
    	$ids['upsell_item_id2'] = $item_records[4]['item_id'];
    	
    	// create LTO
    	$lto_item_id = $item_records[2]['item_id'];
    	$item_resource = SplickitController::getResourceFromId($lto_item_id, "Item");
    	$item_resource->item_name = "LTO Item";
    	$item_resource->active = "L";
    	$item_resource->save();
    	
    	//create change schedule
    	$mcs_adapter = new MenuChangeScheduleAdapter($mimetypes);
    	$mcs_data['menu_id'] = $menu_id;
    	$mcs_data['object_type'] = "item";
    	$mcs_data['object_id'] = $lto_item_id;
    	$mcs_data['replace_id'] = $item_records[0]['item_id'];
    	$mcs_data['day_of_week'] = date('w')+1;
    	$mcs_data['active'] = 'N';
    	$mcs_resource = Resource::createByData($mcs_adapter, $mcs_data);
    	$ids['menu_change_id'] = $mcs_resource->menu_change_id;

    	$new_resource = SplickitController::getResourceFromId($mcs_resource->menu_change_id, "MenuChangeSchedule");
    	
    	$merchant_resource = createNewTestMerchant($menu_id);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	    	
    	$user_resource = createNewUser(array('flags'=>'1C20000001'));
    	$ids['user_id'] = $user_resource->user_id;
    	    	
    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;
    	
    }
    
	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
    }
	
	/* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    MenuTest::main();
}
	