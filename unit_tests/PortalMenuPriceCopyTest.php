<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class PortalMenuPriceCopyTest extends PHPUnit_Framework_TestCase
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

    function testCopyPrices()
    {
        // add third merchant that shoudn't be affected
        $menu_id = $this->ids['menu_id'];
        $merchant_resource3 = createNewTestMerchant($menu_id);
        attachMerchantToSkin($merchant_resource3->merchant_id, $this->ids['skin_id']);
        $merchant_id3 = $merchant_resource3->merchant_id;

        $sql = "UPDATE Item_Size_Map SET price = 5.55 WHERE merchant_id = $merchant_id3";
        $spa = new ItemSizeAdapter(getM());
        $spa->_query($sql);


        $sql = "UPDATE Modifier_Size_Map SET modifier_price = .55 WHERE merchant_id = $merchant_id3";
        $misma = new ModifierSizeMapAdapter(getM());
        $misma->_query($sql);



        $merchant_id = $this->ids['merchant_id'];
        $merchant_id2 = $this->ids['merchant_id2'];


        $item_prices_merchant_1 = CompleteMenu::getAllItemSizesAsResources($menu_id,$merchant_id);
        $item_count = sizeof($item_prices_merchant_1);
        $m1p = [];
        foreach ($item_prices_merchant_1 as $price_resource) {
            $item_id = $price_resource->item_id;
            $size_id = $price_resource->size_id;
            $m1p[$item_id.'-'.$size_id] = $price_resource->price;
            $this->assertEquals(8.88,$price_resource->price);
        }
        $item_prices_merchant_2 = CompleteMenu::getAllItemSizesAsResources($menu_id,$merchant_id2);
        $m2p = [];
        foreach ($item_prices_merchant_2 as $price_resource) {
            $item_id = $price_resource->item_id;
            $size_id = $price_resource->size_id;
            $m2p[$item_id.'-'.$size_id] = $price_resource->price;
            $this->assertNotEquals(8.88,$price_resource->price);
        }


        $modifier_prices_m1 = CompleteMenu::getAllModifierItemSizesAsResoures($menu_id, $merchant_id);
        $modifier_count = sizeof($modifier_prices_m1);
        $m1mp = [];
        foreach ($modifier_prices_m1 as $mp_resource) {
            $modifier_item_id = $mp_resource->modifier_item_id;
            $size_id = $mp_resource->size_id;
            $m1mp[$modifier_item_id.'-'.$size_id] = $mp_resource->modifier_price;
            $this->assertEquals(.88,$mp_resource->modifier_price);
        }

        $modifier_prices_m2 = CompleteMenu::getAllModifierItemSizesAsResoures($menu_id, $merchant_id2);
        $m2mp = [];
        foreach ($modifier_prices_m2 as $mp_resource) {
            $modifier_item_id = $mp_resource->modifier_item_id;
            $size_id = $mp_resource->size_id;
            $m2mp[$modifier_item_id.'-'.$size_id] = $mp_resource->modifier_price;
            $this->assertNotEquals(.88,$mp_resource->modifier_price);
        }


        $imgm2 = CompleteMenu::staticGetAllItemModifierGroupMapsAsResources($menu_id,$merchant_id2);
        $imgm_count = sizeof($imgm2);
        foreach ($imgm2 as $imgm_resource) {
            $this->assertEquals(0.00,$imgm_resource->price_override);
        }


        // now call the function


        $request = createRequestObject("app2/portal/menus/$menu_id/copypricelist",'POST');
        $menu_controller = new MenuController(getM(),null,$request,5);
        $data['source_merchant_id'] = $merchant_id;
        $data['destination_merchant_id'] = $merchant_id2;
        $result = $menu_controller->processCopyPriceListRequest($data);

        $item_prices_merchant_2 = CompleteMenu::getAllItemSizesAsResources($menu_id,$merchant_id2);
        $this->assertCount($item_count,$item_prices_merchant_2,'It should an equal number of prices to merchant 1');
        foreach ($item_prices_merchant_2 as $price_resource) {
            $this->assertEquals(8.88,$price_resource->price);
        }

        $modifier_prices_m2 = CompleteMenu::getAllModifierItemSizesAsResoures($menu_id, $merchant_id2);
        $this->assertCount($modifier_count,$modifier_prices_m2);
        foreach ($modifier_prices_m2 as $mp_resource) {
            $this->assertEquals(.88,$mp_resource->modifier_price);
        }

        $imgm2 = CompleteMenu::staticGetAllItemModifierGroupMapsAsResources($menu_id,$merchant_id2);
        $this->assertCount($imgm_count,$imgm2);
        foreach ($imgm2 as $imgm_resource) {
            $this->assertEquals(0.22,$imgm_resource->price_override);
        }

        // now validate that m3 didn't change

        $item_prices_merchant_3 = CompleteMenu::getAllItemSizesAsResources($menu_id,$merchant_id3);
        foreach ($item_prices_merchant_3 as $price_resource) {
            $this->assertEquals(5.55,$price_resource->price);
        }

        $modifier_prices_m3 = CompleteMenu::getAllModifierItemSizesAsResoures($menu_id, $merchant_id3);
        foreach ($modifier_prices_m3 as $mp_resource) {
            $this->assertEquals(.55,$mp_resource->modifier_price);
        }

        $imgm3 = CompleteMenu::staticGetAllItemModifierGroupMapsAsResources($menu_id,$merchant_id3);
        foreach ($imgm3 as $imgm_resource) {
            $this->assertEquals(0.00,$imgm_resource->price_override);
        }


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
    	$menu_id = createTestMenuWithNnumberOfItemsAndMenuTypes(3,0,2,3);
    	$menu_resource = Resource::find(new MenuAdapter(getM()),$menu_id);
    	$menu_resource->version = 3.0;
    	$menu_resource->save();

    	$ids['menu_id'] = $menu_id;

        $item_records_unsorted = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
        $item_hash_by_ids = createHashmapFromArrayOfArraysByFieldName($item_records_unsorted,'item_id');
        ksort($item_hash_by_ids);
        $item_records = [];
        foreach ($item_hash_by_ids as $key=>$item) {
            $item_records[] = $item;
        }

        $ids['item_records'] = $item_records;


        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 5,'Group of 5');
        $imgm_resource = assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_resource->modifier_group_id);

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 4,'Group of 4');
        $imgm_resource = assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_resource->modifier_group_id);

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 3,'Group of 3');
        $imgm_resource = assignModifierGroupToItemWithFirstNAsComesWith($item_records[4]['item_id'], $modifier_group_resource->modifier_group_id);


        $merchant_resource = createNewTestMerchant($menu_id);
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $ids['merchant_id'] = $merchant_resource->merchant_id;
        $merchant_resource2 = createNewTestMerchant($menu_id);
        attachMerchantToSkin($merchant_resource2->merchant_id, $ids['skin_id']);
        $ids['merchant_id2'] = $merchant_resource2->merchant_id;

        $sql = "UPDATE Item_Size_Map SET price = 8.88 WHERE merchant_id = ".$merchant_resource->merchant_id;
        $spa = new ItemSizeAdapter(getM());
        $spa->_query($sql);


        $sql = "UPDATE Modifier_Size_Map SET modifier_price = .88 WHERE merchant_id = ".$merchant_resource->merchant_id;
        $misma = new ModifierSizeMapAdapter(getM());
        $misma->_query($sql);

        $sql = "UPDATE Item_Modifier_Group_Map SET price_override = .22 WHERE merchant_id = ".$merchant_resource->merchant_id;
        $imgma = new ItemModifierGroupMapAdapter(getM());
        $imgma->_query($sql);


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
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    PortalMenuPriceCopyTest::main();
}

?>