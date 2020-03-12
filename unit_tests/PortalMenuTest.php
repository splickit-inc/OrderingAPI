<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class PortalMenuTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;
    var $merchant_id;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__ . '-' . $_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        setContext("com.splickit.worldhq");

    }

    function tearDown()
    {
        //delete your instance
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
    }


    function testPropogateSingleIMGMRecordToAllItemsInTheGroup()
    {
        $menu_id = createTestMenuWithNnumberOfItemsAndMenuTypes(5,0,2,2);
        $menu_resource = Resource::find(new MenuAdapter(getM()),$menu_id);
        $menu_resource->version = 3.00;
        $menu_resource->save();

        $complete_menu = CompleteMenu::getCompleteMenu($menu_id,"Y",0,2);
        $menu_types = $complete_menu['menu_types'];


        $modifier_group_resource1 = createModifierGroupWithNnumberOfItems($menu_id,5,'Veggies');
        $modifier_group_resource2 = createModifierGroupWithNnumberOfItems($menu_id,3,'Meats');
        $modifier_group_resource3 = createModifierGroupWithNnumberOfItems($menu_id,10,'Condiments');
        $modifier_group_resource4 = createModifierGroupWithNnumberOfItems($menu_id,3,'Other Things');

        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
        foreach ($item_records as $item_record) {
            if ($item_record['menu_type_id'] == $menu_types[0]['menu_type_id']) {
                assignModifierGroupToItemWithFirstNAsComesWith($item_record['item_id'], $modifier_group_resource1->modifier_group_id,0,$modifier_group_resource1->modifier_group_name);
                assignModifierGroupToItemWithFirstNAsComesWith($item_record['item_id'], $modifier_group_resource2->modifier_group_id,0,$modifier_group_resource2->modifier_group_name);
                //assignModifierGroupToItemWithFirstNAsComesWith($item_record['item_id'], $modifier_group_resource3->modifier_group_id,0,$modifier_group_resource3->modifier_group_name);
            } else {
                assignModifierGroupToItemWithFirstNAsComesWith($item_record['item_id'], $modifier_group_resource4->modifier_group_id,0,$modifier_group_resource4->modifier_group_name);
            }

        }

        $merchant_resource1 = createNewTestMerchant($menu_id);
        $merchant_resource2 = createNewTestMerchant($menu_id);
        $merchant_resource3 = createNewTestMerchant($menu_id);

        $merchant_ids = [$merchant_resource1->merchant_id,$merchant_resource2->merchant_id,$merchant_resource3->merchant_id];


        $first_menu_type = $menu_types[0];
        $first_menu_type_id = $first_menu_type['menu_type_id'];
        $first_item = $first_menu_type['menu_items'][0];
        $first_item_id = $first_item['item_id'];

        //only default merchant can add IMGM records
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$first_menu_type_id/menu_items/$first_item_id?merchant_id=0";
        $request = createRequestObject($url,'GET');
        $menu_controller = new MenuController(getM(),null,$request);
        $resource = $menu_controller->processV2Request();
        $response_array['data'] = $resource->getDataFieldsReally();

        $modifier_group_hash = createHashmapFromArrayOfArraysByFieldName($response_array['data']['modifier_groups'],'modifier_group_name');

        $data = [];
        $data["merchant_id"]="0";
        $data["item_id"]=$first_item_id;
        $data["item_name"]="sasquatch";
        $data["external_item_id"]="sas-1";
        $data["item_print_name"]="sasquatch";
        $data["description"]="this is the sasquatch sandwhich";
        $data["priority"]="100";
        $data['number_of_sizes'] = 2;
        $data["0sizeprice_id"]=null;
        $data["0size_id"]=$response_array['data']['sizes'][0]['size_id'];
        $data["0active"]="Y";
        $data["0price"]="4.44";
        $data["0external_id"]="";
        $data["0itemsize_priority"]=$response_array['data']['sizes'][0]['priority'];
        $data["1sizeprice_id"]=null;
        $data["1size_id"]=$response_array['data']['sizes'][1]['size_id'];
        $data["1active"]="N";
        $data["1price"]="8.88";
        $data["1external_id"]="";
        $data["1itemsize_priority"]=$response_array['data']['sizes'][1]['priority'];
        //$data["number_of_possible_modifier_items"]="0";
        $data['number_of_groups']=$response_array['data']['number_of_groups'];

        $data['0item_modifier_group_map_id']=$response_array['data']['allowed_modifier_groups'][$modifier_group_hash['Veggies']['modifier_group_id']]['map_id'];
        $data["0modifier_group_id"]=$modifier_group_hash['Veggies']['modifier_group_id'];
        $data["0allowed"]="ON";
        $data["0display_name"]="Special Veggies";
        $data["0min"]="0";
        $data["0max"]="10";
        $data["0price_override"]="0.88";
        $data["0priority"]="100";
        $data["0push_this_mapping_to_each_item_in_menu_type"]=1;

//        $data["1modifier_group_id"]=$modifier_group_hash['Meats']['modifier_group_id'];
//        $data["1allowed"]="ON";
//        $data["1display_name"]='Meats';
//        $data["1min"]="0";
//        $data["1max"]="1";
//        $data["1price_override"]="1.50";
//        $data["1priority"]="90";
//        $data["1push_this_mapping_to_each_item_in_menu_type"]=1;

        $data["apply_IMGM_to_all_items"]="no";
        $data['propogate_to_merchant_ids'] = 'All';


        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$first_menu_type_id/menu_items/$first_item_id";

        $request = createRequestObject($url,'POST',json_encode($data));
        $menu_controller = new MenuController(getM(),null,$request);
        $resource = $menu_controller->processV2Request();
        $response_data = $resource->getDataFieldsReally();

        $complete_menu = CompleteMenu::getCompleteMenu($menu_id,"Y",0,2);
        $menu_types_hash = createHashmapFromArrayOfArraysByFieldName($complete_menu['menu_types'],'menu_type_id');

        $first_menu_type = $menu_types_hash[$first_menu_type_id];


        foreach ($menu_types_hash as $menu_type) {
            if ($menu_type['menu_type_id'] == $first_menu_type_id) {
                continue;
            }
            foreach ($menu_type['menu_items'] as $menu_item) {
                $this->assertEquals(1,sizeof($menu_item['modifier_groups']),"There should only be 1 modifier group listed with the other items in the other menu types");
            }
        }

        foreach ($first_menu_type['menu_items'] as $menu_item) {

            $this->assertEquals(2,sizeof($menu_item['modifier_groups']),"There should be 2 modifier groups allowed with this item: ".$menu_item['item_name']);

            $modifier_group_for_item_hash = createHashmapFromArrayOfArraysByFieldName($menu_item['modifier_groups'],'modifier_group_id');

            $propogated_imgm = $modifier_group_for_item_hash[$modifier_group_hash['Veggies']['modifier_group_id']];
            $this->assertEquals(0,$propogated_imgm['modifier_group_min_modifier_count'],"It should have a min of 0");
            $this->assertEquals(10,$propogated_imgm['modifier_group_max_modifier_count'],"It should have a max of 8");
            $this->assertEquals(.88,$propogated_imgm['modifier_group_credit'],"It should have a price override of .50");
            $this->assertEquals('Special Veggies',$propogated_imgm['modifier_group_display_name'],"It should have a display name of Special Veggies");

            foreach ($merchant_ids as $child_merchant_id) {
                $sql = "SELECT * FROM Item_Modifier_Group_Map WHERE item_id = ".$menu_item['item_id']." AND modifier_group_id = ".$modifier_group_hash['Veggies']['modifier_group_id']." and merchant_id = $child_merchant_id";
                $imgm_adapter = new ItemModifierGroupMapAdapter(getM());
                $options[TONIC_FIND_BY_SQL] = $sql;
                $child_propogated_imgm = $imgm_adapter->getRecord([],$options);

                $this->assertEquals($child_propogated_imgm['min'],$propogated_imgm['modifier_group_min_modifier_count'],"It should have a min equal to its parent");
                $this->assertEquals($child_propogated_imgm['max'],$propogated_imgm['modifier_group_max_modifier_count'],"It should have a max equal to its parent");
                $this->assertEquals($child_propogated_imgm['price_override'],$propogated_imgm['modifier_group_credit'],"It should have a price override equal to its parent");
                $this->assertEquals($child_propogated_imgm['display_name'],$propogated_imgm['modifier_group_display_name'],"It should have a display name equal to its parent");
            }
        }
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
        //$mysqli->begin_transaction(); ;

        createWorldHqSkin();
        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $ids['menu_id'] = $menu_id;

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
    PortalMenuTest::main();
}

?>