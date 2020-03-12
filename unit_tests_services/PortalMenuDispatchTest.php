<?php

error_reporting(E_ERROR | E_COMPILE_ERROR | E_COMPILE_WARNING | E_PARSE);
$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');
$db_info->database = 'smaw_unittest';
$db_info->username = 'root';
$db_info->password = 'splickit';
if (isset($_SERVER['XDEBUG_CONFIG'])) {
    putenv("SMAW_ENV=unit_test_ide");
    $db_info->hostname = "127.0.0.1";
    $db_info->port = 13306;
} else {
    $db_info->hostname = "db_container";
    $db_info->port = 3306;
}
$_SERVER['DB_INFO'] = $db_info;
require_once 'lib/curl_objects/splickitcurl.php';
require_once 'lib/mocks/viopaymentcurl.php';
require_once 'lib/utilities/functions.inc';
require_once 'lib/utilities/unit_test_functions.inc';


class PortalMenuDispatchTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;
    var $info;
    var $api_port = "80";
    var $menu_id;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        //$_SERVER['DO_NOT_RUN_CC'] = true;
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        if (isset($_SERVER['XDEBUG_CONFIG'])) {
            $this->api_port = "10080";
        }
    }

    function tearDown()
    {
        //delete your instance
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
        unset($this->info);
    }

//    function testReturnPriceOverrideBySizeValues()
//    {
//        $menu_id = createTestMenuWithNnumberOfItemsAndMenuTypes(1,0,1,3);
//        $menu_resource = Resource::find(new MenuAdapter(getM()),"$menu_id");
//        $menu_resource->version = 3.00;
//        $menu_resource->save();
//        $ids['menu_id'] = $menu_id;
//
//        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1);
//        $modifier_group_id = $modifier_group_resource->modifier_group_id;
//        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
//        $item_size_resources = CompleteMenu::getAllItemSizesAsResources($menu_id, 0);
//
//        $imgm_resource = assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 0);
//        $imgm_resource->price_override = .08; // this should be ignored
//        $imgm_resource->save();
//        $size_resources = CompleteMenu::getAllSizesAsResources($menu_id);
//
//        $modifier_item_size_resources = CompleteMenu::getAllModifierItemSizesAsResoures($menu_id);
//        $modifier_item_size_resource = $modifier_item_size_resources[0];
//        $size_names = ['small','medium','large'];
//        $i = 0;
//        foreach ($size_resources as &$size_resource) {
//            $size_resource->size_name = $size_names[$i];
//            $size_resource->size_print_name = $size_names[$i];
//            $size_resource->save();
//
//            $is_data = ["item_id"=>$item_records[0]['item_id'],"size_id"=>$size_resource->size_id,"merchant_id"=>0];
//            $is_options[TONIC_FIND_BY_METADATA] = $is_data;
//            $item_size_resource = Resource::find(new ItemSizeAdapter(getM()),null,$is_options);
//            $item_size_resource->price = 2.00 * ($i+1);
//            $item_size_resource->save();
//
//            $modifier_item_size_resource->_exists = false;
//            unset($modifier_item_size_resource->modifier_size_id);
//            $modifier_item_size_resource->size_id = $size_resource->size_id;
//            $modifier_item_size_resource->modifier_price = .25 * ($i+1);
//            $modifier_item_size_resource->save();
//
//            if ( $size_resource->size_name != 'medium') {
//                $imipobs_adapter = new ItemModifierItemPriceOverrideBySizesAdapter(getM());
//                $data = ['item_id'=> $item_records[0]['item_id'],'size_id'=>$size_resource->size_id,'modifier_group_id'=>$modifier_group_id,'merchant_id'=>0,'price_override'=>$modifier_item_size_resource->modifier_price];
//                $override_by_size_resource = Resource::factory($imipobs_adapter,$data);
//                $override_by_size_resource->save();
//            }
//            $i++;
//        }
//        $sizes = CompleteMenu::getAllSizes($menu_id);
//        $sizes_hash = createHashmapFromArrayOfArraysByFieldName(array_pop($sizes),'size_name');
//
//        $merchant_resource = createNewTestMerchant($menu_id);
//        attachMerchantToSkin($merchant_resource->merchant_id, getSkinIdForContext());
//
//        //pull menu and see if price override by size comes in
//        //$this->assertTrue(false,"TODO: pull menu and see if price override by size comes in");
//
//    }


    function testUpdateItemDoNotCreateExtraItemSizeRecords()
    {
        setContext('com.splickit.vtwoapi');
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $menu_resource = Resource::find(new MenuAdapter(getM()),"$menu_id");
        $menu_resource->version = 2.00;
        $menu_resource->save();

        $complete_menu = CompleteMenu::getCompleteMenu($menu_id);
        $menu_type_id = $complete_menu['menu_types'][0]['menu_type_id'];
        $size_resource = createNewSize($menu_type_id, 'Additional Size 1');
        $size_resource = createNewSize($menu_type_id, 'Additional Size 2');
        $size_resource = createNewSize($menu_type_id, 'Additional Size 3');
        $size_resource = createNewSize($menu_type_id, 'Additional Size 4');

        $item_id = $complete_menu['menu_types'][0]['menu_items'][0]['item_id'];
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id/menu_items/$item_id?merchant_id=0";
//        $request = createRequestObject($url,'GET');
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
        $response = $this->makeRequest($url, null,'GET');
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $data = $response_array['data'];
        $this->assertCount(1,$data['size_prices'],'there should be 1 default price for this item');

        //NOW UPDATE THE ITEM
        $i = 0;
        $priority = 50;
        $size_price_priority = [];
        $size_price_price = [];
        $size_prices = $data['size_prices'];

        foreach ($data['sizes'] as $size) {
            if (isset($size_prices[$size['size_id']])) {
                $item_size_id = $size_prices[$size['size_id']]['item_size_id'];
                $price = $size_prices[$size['size_id']]['price'];
            } else {
                $item_size_id = null;
                $price = 0.00;
            }
            if ($i == 3) {
                $price = 1.00;
            }
            $data[$i."sizeprice_id"]=$item_size_id;
            $data[$i."size_id"]=$size['size_id'];
            $data[$i."active"]= $i == 0 ? "Y" : 'N';
            $data[$i."price"]=$price;
            $data[$i."external_id"]='';
            $data[$i."itemsize_priority"]=$priority;
//            $size_price_price[$size_price['size_id']] = $data[$i."price"];
//            $size_price_priority[$size_price['size_id']] = $priority;
            $i++;

            $priority = $priority - 10;
        }

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id/menu_items/$item_id";


//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();

        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $data = $response_array['data'];
        $this->assertCount(2,$data['size_prices'],'there should be 2  price records for this item');



    }

    function testUpdateItemSizeMapExternalIdFromValueToBlank()
    {
        setContext('com.splickit.vtwoapi');
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $menu_resource = Resource::find(new MenuAdapter(getM()),"$menu_id");
        $menu_resource->version = 3.00;
        $menu_resource->save();
        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_resource2 = createNewTestMerchant($menu_id);

        $merchant_ids = [0,$merchant_resource->merchant_id,$merchant_resource2->merchant_id];

        $complete_menu = CompleteMenu::getCompleteMenu($menu_id);
        $menu_type_id = $complete_menu['menu_types'][0]['menu_type_id'];
        $item_id = $complete_menu['menu_types'][0]['menu_items'][0]['item_id'];

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id/menu_items/$item_id?merchant_id=0";
//        $request = createRequestObject($url,'GET');
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
        $response = $this->makeRequest($url, null,'GET');
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $data = $response_array['data'];
        $this->assertCount(1,$data['size_prices'],'there should be 1 default price for this item');

        // now update
        $data['propogate_prices'] = true;
        $i = 0;
        $priority = 50;
        $size_price_priority = [];
        $size_price_price = [];
        foreach ($data['size_prices'] as $size_price) {
            $data[$i."sizeprice_id"]=$size_price['item_size_id'];
            $data[$i."size_id"]=$size_price['size_id'];
            $data[$i."active"]="Y";
            $data[$i."price"]=$size_price['price'];
            $data[$i."external_id"]='';
            $data[$i."itemsize_priority"]=$size_price['priority'];
            $size_price_price[$size_price['size_id']] = $data[$i."price"];
            $size_price_priority[$size_price['size_id']] = $priority;
            $i++;

            $priority = $priority - 10;
        }
        $data['propogate_to_merchant_ids'] = 'All';
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id/menu_items/$item_id";


//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();

        $response = $this->makeRequest($url, null,'POST',$data);

        //check to see if child prices went to a blank exteral id
        foreach ($merchant_ids as $merchant_id) {
            foreach ($data['sizes'] as $size_record) {
                $size_id = $size_record['size_id'];
                $price_record = ItemSizeAdapter::staticGetRecord(['merchant_id'=>$merchant_id,'size_id'=>$size_id,'item_id'=>$item_id],'ItemSizeAdapter');
                $this->assertEquals('',$price_record['external_id'],'It shoudl have set the external id to empty for merchant id: '.$merchant_id);
            }
        }
    }



    function testCopyExistingIMGMRecordsFromOtherItemInTheGroup()
    {
        $menu_id = createTestMenuWithNnumberOfItemsAndMenuTypes(2,0,2,2);
        $menu_resource = Resource::find(new MenuAdapter(getM()),$menu_id);
        $menu_resource->version = 3.00;
        $menu_resource->save();

        $modifier_group_resource1 = createModifierGroupWithNnumberOfItems($menu_id,5,'Veggies');
        $modifier_group_resource2 = createModifierGroupWithNnumberOfItems($menu_id,3,'Meats');
        $modifier_group_resource3 = createModifierGroupWithNnumberOfItems($menu_id,10,'Condiments');
        $modifier_group_resource4 = createModifierGroupWithNnumberOfItems($menu_id,8,'Others');

        $merchant_resource1 = createNewTestMerchant($menu_id);

        $complete_menu = CompleteMenu::getCompleteMenu($menu_id,"Y",0,2);

        $menu_types = $complete_menu['menu_types'];

        $first_menu_type = $menu_types[0];
        $first_menu_type_id = $first_menu_type['menu_type_id'];
        $first_item = $first_menu_type['menu_items'][0];
        $first_item_id = $first_item['item_id'];

        //only default merchant can add IMGM records
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$first_menu_type_id/menu_items/$first_item_id?merchant_id=0";
//        $request = createRequestObject($url,'GET');
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
        $response = $this->makeRequest($url, null,'GET');
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);

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

        $data['0item_modifier_group_map_id']=$response_array['data']['allowed_modifier_groups'][$modifier_group_hash['Meats']['modifier_group_id']]['map_id'];
        $data["0modifier_group_id"]=$modifier_group_hash['Meats']['modifier_group_id'];
        $data["0allowed"]="ON";
        $data["0display_name"]="Meats";
        $data["0min"]="1";
        $data["0max"]="5";
        $data["0price_override"]="1.00";
        $data["0priority"]="100";
        $data["0push_this_mapping_to_each_item_in_menu_type"]=0;



        $data["1modifier_group_id"]=$modifier_group_hash['Veggies']['modifier_group_id'];
        $data["1allowed"]="ON";
        $data["1display_name"]='Special Veggies';
        $data["1min"]="0";
        $data["1max"]="8";
        $data["1price_override"]="0.50";
        $data["1priority"]="90";
        $data["1push_this_mapping_to_each_item_in_menu_type"]=0;

        $data["2modifier_group_id"]=$modifier_group_hash['Condiments']['modifier_group_id'];
        $data["2allowed"]="ON";
        $data["2display_name"]=$modifier_group_hash['Condiments']['modifier_group_name'];
        $data["2min"]="0";
        $data["2max"]="3";
        $data["2price_override"]="0.25";
        $data["2priority"]="80";
        $data["2push_this_mapping_to_each_item_in_menu_type"]=0;

        $data["3modifier_group_id"]=$modifier_group_hash['OThers']['modifier_group_id'];
        $data["3allowed"]="OFF";
        $data["3display_name"]=$modifier_group_hash['Others']['modifier_group_name'];
        $data["3min"]="0";
        $data["3max"]="3";
        $data["3price_override"]="0.25";
        $data["3priority"]="80";
        $data["3push_this_mapping_to_each_item_in_menu_type"]=0;

        $data["apply_IMGM_to_all_items"]="no";


        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$first_menu_type_id/menu_items/$first_item_id";

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();


        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];

        $complete_menu = CompleteMenu::getCompleteMenu($menu_id,"Y",0,2);

        $menu_types_hash = createHashmapFromArrayOfArraysByFieldName($complete_menu['menu_types'],'menu_type_id');

        $first_menu_type = $menu_types_hash[$first_menu_type_id];


        /******************************************************************/

        // now lets create a new item and copy the mappings from item 1
        $first_menu_type_id = $first_menu_type['menu_type_id'];

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$first_menu_type_id/menu_items/new";

//        $request = createRequestObject($url,'GET');
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $new_item_data = $resource->getDataFieldsReally();

        $response = $this->makeRequest($url, null,'GET');
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $new_item_data = $response_array['data'];
        $this->assertCount(2,$new_item_data['sizes'],'there should be 2 sizes for this item');
        $this->assertCount(2,$new_item_data['size_prices'],'there should be 2 default price place holders for this item');
        $this->assertCount(4,$new_item_data['modifier_groups'],"there should be 3 modifier groups");
        $this->assertEquals(4,$new_item_data['number_of_groups'],"there should be a count of number of modifier_groups");
        $this->assertTrue(sizeof($new_item_data['allowed_modifier_groups']) == 0,"There should not be any allowed modifier groups yet");


        $data = [];
        $data["merchant_id"]="0";
        $data["item_id"]=null;
        $data["item_name"]="sasquatch";
        $data["external_item_id"]="sas-1";
        $data["item_print_name"]="sasquatch";
        $data["description"]="this is the sasquatch sandwhich";
        $data["priority"]="100";
        $data["0sizeprice_id"]=null;
        $data["0size_id"]=$new_item_data['sizes'][0]['size_id'];
        $data["0active"]="Y";
        $data["0price"]="4.44";
        $data["0external_id"]="";
        $data["0itemsize_priority"]=$new_item_data['sizes'][0]['priority'];
        $data["0include"] = true;
        $data["1sizeprice_id"]=null;
        $data["1size_id"]=$new_item_data['sizes'][1]['size_id'];
        $data["1active"]="N";
        $data["1price"]="8.88";
        $data["1external_id"]="";
        $data["1itemsize_priority"]=$new_item_data['sizes'][1]['priority'];
        $data["1include"] = true;
        $data["2sizeprice_id"]=null;
        $data["2size_id"]=$new_item_data['sizes'][2]['size_id'];
        $data["2active"]="N";
        $data["2price"]="0.00";
        $data["2external_id"]="";
        $data["2itemsize_priority"]=$new_item_data['sizes'][2]['priority'];
        $data["2include"] = false;
        $data["number_of_possible_modifier_items"]="0";

        $data['copy_imgm_from_item_id'] = $first_item_id;

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$first_menu_type_id/menu_items";

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();

        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];
        $item_id = $response_data['item_id'];

        $this->assertCount(2,$response_data['size_prices'],'there should be 2 default prices for this item');
        $this->assertCount(4,$response_data['modifier_groups'],"there should be 4 modifier group");
        $this->assertCount(3,$response_data['allowed_modifier_groups'],"There should be 3 allowed modifier group");

    }

    function testPropogateSingleIMGMRecordToAllItemsInTheGroup()
    {
        $menu_id = createTestMenuWithNnumberOfItemsAndMenuTypes(5,0,3,2);
        $menu_resource = Resource::find(new MenuAdapter(getM()),$menu_id);
        $menu_resource->version = 3.00;
        $menu_resource->save();

        $modifier_group_resource1 = createModifierGroupWithNnumberOfItems($menu_id,5,'Veggies');
        $modifier_group_resource2 = createModifierGroupWithNnumberOfItems($menu_id,3,'Meats');
        $modifier_group_resource3 = createModifierGroupWithNnumberOfItems($menu_id,10,'Condiments');

        $modifier_group_id = $modifier_group_resource2->modifier_group_id;
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
        foreach ($item_records as $item_record) {
            assignModifierGroupToItemWithFirstNAsComesWith($item_record['item_id'], $modifier_group_id,1,$modifier_group_resource2->modifier_group_name);
        }

        $merchant_resource1 = createNewTestMerchant($menu_id);
        $merchant_resource2 = createNewTestMerchant($menu_id);

        $merchant_ids = [$merchant_resource1->merchant_id,$merchant_resource2->merchant_id];

        $complete_menu = CompleteMenu::getCompleteMenu($menu_id,"Y",0,2);

        $menu_types = $complete_menu['menu_types'];

        $first_menu_type = $menu_types[0];
        $first_menu_type_id = $first_menu_type['menu_type_id'];
        $first_item = $first_menu_type['menu_items'][0];
        $first_item_id = $first_item['item_id'];

        //only default merchant can add IMGM records
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$first_menu_type_id/menu_items/$first_item_id?merchant_id=0";
//        $request = createRequestObject($url,'GET');
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
        $response = $this->makeRequest($url, null,'GET');
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);

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

        $data['0item_modifier_group_map_id']=$response_array['data']['allowed_modifier_groups'][$modifier_group_hash['Meats']['modifier_group_id']]['map_id'];
        $data["0modifier_group_id"]=$modifier_group_hash['Meats']['modifier_group_id'];
        $data["0allowed"]="ON";
        $data["0display_name"]="Meats";
        $data["0min"]="1";
        $data["0max"]="5";
        $data["0price_override"]="1.00";
        $data["0priority"]="100";
        $data["0push_this_mapping_to_each_item_in_menu_type"]=0;



        $data["1modifier_group_id"]=$modifier_group_hash['Veggies']['modifier_group_id'];
        $data["1allowed"]="ON";
        $data["1display_name"]='Special Veggies';
        $data["1min"]="0";
        $data["1max"]="8";
        $data["1price_override"]="0.50";
        $data["1priority"]="90";
        $data["1push_this_mapping_to_each_item_in_menu_type"]=1;

        $data["2modifier_group_id"]=$modifier_group_hash['Condiments']['modifier_group_id'];
        $data["2allowed"]="ON";
        $data["2display_name"]=$modifier_group_hash['Condiments']['modifier_group_name'];
        $data["2min"]="0";
        $data["2max"]="3";
        $data["2price_override"]="0.25";
        $data["2priority"]="80";
        $data["2push_this_mapping_to_each_item_in_menu_type"]=0;

        $data["apply_IMGM_to_all_items"]="no";


        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$first_menu_type_id/menu_items/$first_item_id";

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();


        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];

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
            $expected_count = $menu_item['item_id'] == $first_item_id ? 3 : 2;
            $this->assertEquals($expected_count,sizeof($menu_item['modifier_groups']),"There should be $expected_count modifier groups allowed with this item: ".$menu_item['item_name']);

            $modifier_group_for_item_hash = createHashmapFromArrayOfArraysByFieldName($menu_item['modifier_groups'],'modifier_group_id');

            $propogated_imgm = $modifier_group_for_item_hash[$modifier_group_hash['Veggies']['modifier_group_id']];
            $this->assertEquals(0,$propogated_imgm['modifier_group_min_modifier_count'],"It should have a min of 0");
            $this->assertEquals(8,$propogated_imgm['modifier_group_max_modifier_count'],"It should have a max of 8");
            $this->assertEquals(.50,$propogated_imgm['modifier_group_credit'],"It should have a price override of .50");
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
        $data["0size_id"]=$response_data['sizes'][0]['size_id'];
        $data["0active"]="Y";
        $data["0price"]="4.44";
        $data["0external_id"]="";
        $data["0itemsize_priority"]=$response_data['sizes'][0]['priority'];
        $data["1sizeprice_id"]=null;
        $data["1size_id"]=$response_data['sizes'][1]['size_id'];
        $data["1active"]="N";
        $data["1price"]="8.88";
        $data["1external_id"]="";
        $data["1itemsize_priority"]=$response_array['data']['sizes'][1]['priority'];
        //$data["number_of_possible_modifier_items"]="0";
        $data['number_of_groups']=$response_array['data']['number_of_groups'];

        $data['0item_modifier_group_map_id']=$response_data['allowed_modifier_groups'][$modifier_group_hash['Meats']['modifier_group_id']]['map_id'];
        $data["0modifier_group_id"]=$modifier_group_hash['Meats']['modifier_group_id'];
        $data["0allowed"]="OFF";
        $data["0display_name"]="Meats";
        $data["0min"]="1";
        $data["0max"]="5";
        $data["0price_override"]="1.00";
        $data["0priority"]="100";
        $data["0push_this_mapping_to_each_item_in_menu_type"]=1;

        $data['1item_modifier_group_map_id']=$response_data['allowed_modifier_groups'][$modifier_group_hash['Veggies']['modifier_group_id']]['map_id'];
        $data["1modifier_group_id"]=$modifier_group_hash['Veggies']['modifier_group_id'];
        $data["1allowed"]="ON";
        $data["1display_name"]='Special Veggies';
        $data["1min"]="0";
        $data["1max"]="8";
        $data["1price_override"]="0.50";
        $data["1priority"]="90";
        $data["1push_this_mapping_to_each_item_in_menu_type"]=0;

        $data['2item_modifier_group_map_id']=$response_data['allowed_modifier_groups'][$modifier_group_hash['Condiments']['modifier_group_id']]['map_id'];
        $data["2modifier_group_id"]=$modifier_group_hash['Condiments']['modifier_group_id'];
        $data["2allowed"]="ON";
        $data["2display_name"]=$modifier_group_hash['Condiments']['modifier_group_name'];
        $data["2min"]="0";
        $data["2max"]="3";
        $data["2price_override"]="0.25";
        $data["2priority"]="80";
        $data["2push_this_mapping_to_each_item_in_menu_type"]=0;

        $data["apply_IMGM_to_all_items"]="no";


        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$first_menu_type_id/menu_items/$first_item_id";

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();


        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);

        // setProperty('DO_NOT_CHECK_CACHE',"true",true);
        $menu_resource->last_menu_change = time()+10000;
        $menu_resource->save();
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
            $expected_count = $menu_item['item_id'] == $first_item_id ? 2 : 1;
            $this->assertEquals($expected_count,sizeof($menu_item['modifier_groups']),"Delete Should have propogated and there should only be $expected_count modifier groups allowed with this item: ".$menu_item['item_name']);
        }
    }

    function testMenuTypePriority()
    {
        $menu_id = createTestMenuWithNnumberOfItems(5);
        $menu_resource = Resource::find(new MenuAdapter(getM()),"$menu_id");
        $menu_resource->version = 3.0;
        $menu_resource->save();
        $menu_type_resource = createNewMenuTypeWithNNumberOfItems($menu_id, '5 Sandwiches');
        $menu_type_resource->priority = 200;
        $menu_type_resource->save();
        $menu_type_resource = createNewMenuTypeWithNNumberOfItems($menu_id, '3 tSandwiches');
        $menu_type_resource->priority = 150;
        $menu_type_resource->save();
        $menu_type_resource = createNewMenuTypeWithNNumberOfItems($menu_id, '4 Sandwiches');
        $menu_type_resource->priority = 180;
        $menu_type_resource->save();
        $menu_type_resource = createNewMenuTypeWithNNumberOfItems($menu_id, '6 Sandwiches');
        $menu_type_resource->priority = 220;
        $menu_type_resource->save();
        $menu_type_resource = createNewMenuTypeWithNNumberOfItems($menu_id, '1 Sandwiches');
        $menu_type_resource->priority = 100;
        $menu_type_resource->save();

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/getpricelist?merchant_id=$merchant_id";

//        $request = createRequestObject($url,'GET');
//        $menu_controller = new MenuController(getM(),null,$request,getBaseLogLevel());
//        $resource = $menu_controller->processV2Request();

        $response = $this->makeRequest($url, null,'GET');
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $price_list = $response_array['data'];

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id?merchant_id=$merchant_id";

//        $request = createRequestObject($url,'GET');
//        $menu_controller = new MenuController(getM(),null,$request,getBaseLogLevel());
//        $resource = $menu_controller->processV2Request();

        $response = $this->makeRequest($url, null,'GET');
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $fullmenu = $response_array['data'];
        $menu_types = $fullmenu['menu_types'];
    }

    function testDeleteMenuObject()
    {
        $menu_id = createTestMenuWithNnumberOfItems(1);

        $item_resources = CompleteMenu::getAllMenuItemsAsResources();

        $menu_resource = Resource::find(new MenuAdapter(getM()),"$menu_id");
        $menu_resource->version = 3.0;
        $menu_resource->save();

        $menu_type_resource = createNewMenuType($menu_id, 'Sandwiches');
        $large_size_resource = createNewSize($menu_type_resource->menu_type_id, 'Large');
        $large_size_resource->priority = 200;
        $large_size_resource->save();
        $small_size_resource = createNewSize($menu_type_resource->menu_type_id, 'Small');
        $small_size_resource->priority = 180;
        $small_size_resource->save();

        $size_array = [$large_size_resource->size_id,$small_size_resource->size_id];
        $priority = 200;
        for ($i = 1; $i < 2; $i++) {
            $item_resources[] = createItem("Sandwich Item " . $i, $size_array, $menu_type_resource->menu_type_id,null,null,$priority);
            $priority = $priority - 10;
        }

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1,"First Group");

        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_resource->modifier_group_id, 2);

        $complete_menu = CompleteMenu::getCompleteMenu($menu_id);
        $this->assertCount(2,$complete_menu['menu_types']);
        $this->assertCount(1,$complete_menu['modifier_groups']);

        $item_id = $item_resources[0]->item_id;


       $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_items/$item_id";
//        $request = createRequestObject($url,'GET',null);
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();

        $response = $this->makeRequest($url, null,'DELETE',null);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];
        $this->assertEquals("Success",$response_data['result']);

        $sql = "SELECT * FROM Item WHERE item_id = $item_id";
        $item_adapter = new ItemAdapter(getM());
        $options[TONIC_FIND_BY_SQL] = $sql;

        $item_resource = Resource::find($item_adapter,null,$options);
        $this->assertEquals('Y',$item_resource->logical_delete);

        $modifier_item_id = $modifier_group_resource->modifier_items[0]->modifier_item_id;
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/modifier_items/$modifier_item_id";
//        $request = createRequestObject($url,'GET',null);
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();

        $response = $this->makeRequest($url, null,'DELETE',null);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];
        $this->assertEquals("Success",$response_data['result']);

        $sql = "SELECT * FROM Modifier_Item WHERE modifier_item_id = $modifier_item_id";
        $modifier_item_adapter = new ModifierItemAdapter(getM());
        $options[TONIC_FIND_BY_SQL] = $sql;

        $mod_item_resource = Resource::find($modifier_item_adapter,null,$options);
        $this->assertEquals('Y',$mod_item_resource->logical_delete);


        $menu_type_id = $menu_type_resource->menu_type_id;

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id";
//        $request = createRequestObject($url,'GET',null);
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();

        $response = $this->makeRequest($url, null,'DELETE',null);

        $sql = "SELECT * FROM Menu_Type WHERE menu_type_id = $menu_type_id";
        $menu_type_adapter = new MenuTypeAdapter(getM());
        $options[TONIC_FIND_BY_SQL] = $sql;

        $mt_resource = Resource::find($menu_type_adapter,null,$options);
        $this->assertEquals('Y',$mt_resource->logical_delete);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];
        $this->assertEquals("Success",$response_data['result']);

        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/modifier_groups/$modifier_group_id";
//        $request = createRequestObject($url,'GET',null);
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();

        $response = $this->makeRequest($url, null,'DELETE',null);

        $sql = "SELECT * FROM Modifier_Group WHERE modifier_group_id = $modifier_group_id";
        $modifier_group_adapter = new ModifierGroupAdapter(getM());
        $options[TONIC_FIND_BY_SQL] = $sql;

        $mg_resource = Resource::find($modifier_group_adapter,null,$options);
        $this->assertEquals('Y',$mg_resource->logical_delete);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];
        $this->assertEquals("Success",$response_data['result']);


    }


    function testGetMerchantPriceList()
    {

        $menu_id = createTestMenuWithNnumberOfItems(5);
        $menu_resource = Resource::find(new MenuAdapter(getM()),"$menu_id");
        $menu_resource->version = 3.0;
        $menu_resource->save();
        $menu_type_resource = createNewMenuType($menu_id, 'Sandwiches');
        $large_size_resource = createNewSize($menu_type_resource->menu_type_id, 'Large');
        $large_size_resource->priority = 200;
        $large_size_resource->save();
        $small_size_resource = createNewSize($menu_type_resource->menu_type_id, 'Small');
        $small_size_resource->priority = 180;
        $small_size_resource->save();

        $size_array = [$large_size_resource->size_id,$small_size_resource->size_id];
        $priority = 200;
        for ($i = 1; $i < 5; $i++) {
            createItem("Sandwich Item " . $i, $size_array, $menu_type_resource->menu_type_id,null,null,$priority);
            $priority = $priority - 10;
        }

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 5,"Group 5");
        $modifier_group_resource->priority = 100;
        $modifier_group_resource->save();
        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10,"Group 10");
        $modifier_group_resource->priority = 50;
        $modifier_group_resource->save();
        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 7,"Group 7");
        $modifier_group_resource->priority = 150;
        $modifier_group_resource->save();

        $merchant_resource = createNewTestMerchant($menu_id);
        $store_id = rand(111111,999999);
        $mvima_data = ['merchant_id'=>$merchant_resource->merchant_id,'store_id'=>$store_id,'merchant_key'=>'736de47f4cdee9e8fe9ab8a0bc0eb37e'];
        $mvima = new MerchantVivonetInfoMapsAdapter(getM());
        $mvim_resource = Resource::createByData($mvima,$mvima_data);

        $merchant_id = $merchant_resource->merchant_id;
        $sql = "INSERT INTO Merchant_Pos_Maps VALUES (NULL,$merchant_id,2000,NOW(),NOW(),'N')";
        $adapter = new MySQLAdapter(getM());
        $adapter->_query($sql);
        $sql = "INSERT INTO import_audit VALUES (NULL,'123545','/app2/portal/menus/$menu_id/getpricelist?merchant_id=$merchant_id','nothing',$merchant_id,NOW(),NOW(),'N')";
        $adapter->_query($sql);

        $complete_menu = CompleteMenu::getCompleteMenu($menu_id,"Y",$merchant_id,2);

        $menu_controller = new MenuController(null,null,null);
        $price_list = $menu_controller->getMerchantPriceListForEdit($menu_id,$merchant_id);
        $this->assertCount(2,$price_list['menu_types'],"It Should have 2 menu_types");
        $sandwhich_menu_type = $price_list['menu_types']['Sandwiches'];
        $this->assertCount(8,$sandwhich_menu_type,"There should be 8 price items for this menu type");
        $sandwich_hash = createHashmapFromArrayOfArraysByFieldName($sandwhich_menu_type,'priority');
        $price_record = $sandwich_hash['200'];
        $this->assertEquals('Sandwich Item 1',$price_record['item_name']);
        $this->assertEquals('Large',$price_record['size_name']);
        $this->assertEquals('Y',$price_record['active']);
        $this->assertEquals('All',$price_record['included_merchant_menu_types']);
        $this->assertEquals('1.50',$price_record['price']);
        $this->assertTrue($price_record['item_size_id'] > 1000);

        $this->assertCount(3,$price_list['modifier_groups'],"It should 3 modifier groups");

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/getpricelist?merchant_id=$merchant_id";

//        $request = createRequestObject($url,'GET');
//        $menu_controller = new MenuController(getM(),null,$request,getBaseLogLevel());
//        $resource = $menu_controller->processV2Request();

        $response = $this->makeRequest($url, null,'GET');
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $price_list = $response_array['data'];

        $this->assertNotNull($price_list['import_url'],"It should have the import URL");
        $this->assertNotNull($price_list['last_import_timestamp'],"It shoudl have the last import time");
        $this->assertEquals('/app2/portal/import/vivonet/'.$merchant_resource->alphanumeric_id,$price_list['import_url']);

        $this->assertCount(2,$price_list['menu_types'],"It Should have 2 menu_types");
        $sandwhich_menu_type = $price_list['menu_types']['Sandwiches'];
        $this->assertCount(8,$sandwhich_menu_type,"There should be 8 price items for this menu type");
        $price_record = $sandwhich_menu_type[0];
        $this->assertEquals('Sandwich Item 1',$price_record['item_name']);
        $this->assertTrue($price_record['size_name'] == 'Large' || $price_record['size_name'] == 'Small');
        $this->assertEquals($price_record['item_name'],$sandwhich_menu_type[1]['item_name'],'Second price shoujld be same item');
        $next_size = $price_record['size_name'] == 'Large' ? 'Small' : 'Large';
        $this->assertEquals($next_size,$sandwhich_menu_type[1]['size_name'],'Second price shoujld be the other size');
        $this->assertEquals('Y',$price_record['active']);
        $this->assertEquals('All',$price_record['included_merchant_menu_types']);
        $this->assertEquals('1.50',$price_record['price']);
        $this->assertTrue($price_record['item_size_id'] > 1000);

        $this->assertCount(3,$price_list['modifier_groups'],"It should have 3 modifier groups");
        $price_list['merchant_id'] = $merchant_id;
        return $price_list;
    }

    /**
     * @depends testGetMerchantPriceList
     */
    function testStageImportFromPricelistUrl($price_list)
    {
        $merchant_id = $price_list['merchant_id'];
        $merchant_resource = Resource::find(new MerchantAdapter(getM()),$merchant_id);
        $alpha = $merchant_resource->alphanumeric_id;
        $url = "http://127.0.0.1:".$this->api_port.$price_list['import_url'];

        //$user = logTestUserIn(1);
        clearAuthenticatedUserParametersForSession();
//        removeContext();
//        $request = createRequestObject("$url",'POST');
//        $pos_controller = new PosController(getM(),null,$request,5);
//        $resource = $pos_controller->processV2request();

        $response = $this->makeRequest($url,null,'POST','');
        $this->assertEquals(200,$this->info['http_code']);
        $response_array = json_decode($response,true);
        $this->assertContains('The import has been staged',$response_array['data']['message']);

        //validate that import was staged correctly
        $activity_id = $response_array['data']['activity_id'];
        $activity_resource = Resource::find(new ActivityHistoryAdapter(getM()),"$activity_id");
        $info = $activity_resource->info;
        $expected_info = "object=VivonetImporter;method=import;thefunctiondatastring=$alpha";
        $this->assertEquals($expected_info,$info,"it shoujdl have the url with the alpha numeric");
    }


    /**
     * @depends testGetMerchantPriceList
     */
    function testUpdatePriceRecordFromListData($price_list)
    {
        $menu_id = $price_list['menu_id'];
        // try updating item
        $price_info = $price_list['menu_types']['Sandwiches'][0];
        $item_size_id = $price_info['item_size_id'];
        $price_info2['item_size_id'] = $item_size_id;
        $price_info2['price'] = 8.88;
        $price_info2['priority'] = 777;
        $price_info2['external_id'] = 'ABCDEFG';
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/item_price";

//        $request = createRequestObject($url,'POST',json_encode($price_info));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();


        $response = $this->makeRequest($url, null,'POST',$price_info2);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];
        $this->assertEquals("Success",$response_data['result']);

        $item_size_resource = Resource::find(new ItemSizeAdapter(getM()),"$item_size_id");
        $this->assertEquals(8.88,$item_size_resource->price,"it should have the updated price");
        $this->assertEquals(777,$item_size_resource->priority,"it should have the updated priority");

    }

    /**
     * @depends testGetMerchantPriceList
     */
    function testUpdateModifierPriceRecordFromListData($price_list)
    {
        $menu_id = $price_list['menu_id'];
        // try updating item
        $price_info = $price_list['modifier_groups']['Group 10'][0];
        $modifier_size_id = $price_info['modifier_size_id'];
        $price_info['modifier_price'] = 3.33;
        $price_info['active'] = 'N';
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/modifier_price";

//        $request = createRequestObject($url,'POST',json_encode($price_info));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();


        $response = $this->makeRequest($url, null,'POST',$price_info);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];
        $this->assertEquals("Success",$response_data['result']);

        $modifier_size_resource = Resource::find(new ModifierSizeMapAdapter(getM()),"$modifier_size_id");
        $this->assertEquals(3.33,$modifier_size_resource->modifier_price,"it should have the updated the modifier price");
        $this->assertEquals('N',$modifier_size_resource->active,"It should have set the price to innactive");
    }

    function testCreateNewMenuAttachMerchant()
    {
        setContext('com.splickit.vtwoapi');
        $merchant_resource = createNewTestMerchant();
        $merchant_id = $merchant_resource->merchant_id;

        $merchant_resource = createNewTestMerchant();
        $merchant_id2 = $merchant_resource->merchant_id;

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus";
        $data['name'] = 'Sumdum Name';
        $data['external_menu_id'] = generateCode(10);
        $data['description'] = 'Sumdum Description';
        $data['merchant_ids'] = "$merchant_id, $merchant_id2";
        $data['brand_id'] = getBrandIdFromCurrentContext();
        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $resp_data = $response_array['data'];
        $this->assertEquals("Sumdum Name",$resp_data['name']);
        $menu_id = $resp_data['menu_id'];
        $this->assertTrue($menu_id > 1000,"we should have generated a valid menu id");
        $this->assertEquals(getBrandIdFromCurrentContext(),$resp_data['brand_id'],'It should save the brand_id on the menu');
        $merchant_menu_map_records = MerchantMenuMapAdapter::staticGetRecords(array("menu_id"=>$menu_id,"merchant_menu_type"=>"pickup"),"MerchantMenuMapAdapter");
        $this->assertCount(2,$merchant_menu_map_records,"there should be two merchan menu map records");
        $map_hash = createHashmapFromArrayOfArraysByFieldName($merchant_menu_map_records,"merchant_id");
        $this->assertTrue(isset($map_hash[$merchant_id]),"merchant 1 shoudl have a menu mapping");
        $this->assertTrue(isset($map_hash[$merchant_id2]),"merchant 2 shoudl have a menu mapping");
        $this->menu_id = $menu_id;
        return $menu_id;
    }

    /**
     * @depends testCreateNewMenuAttachMerchant
     */

    function testUpdateMenuRecord($menu_id)
    {
        $description = 'Sumdum Description Part 2';
        $data['name'] = 'Sumdum Name';
        $data['description'] = $description;
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id";

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();


        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $resp_data = $response_array['data'];
        $this->assertEquals($description,$resp_data['description']);

    }

    /**
     * @depends  testCreateNewMenuAttachMerchant
     */
    function testAddMenuTypesWithItemsAndSizes($menu_id)
    {
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types";
        $data['menu_type_name'] = 'sumdum section';
        $data['menu_type_description'] = 'sumdum description of sumdum section';
        $data['priority'] = '200';
        $data['start_time'] = '08:00:00';
        $data['end_time'] = '10:30:00';
        $data['items'] = 'item 1=item 1 description=8.88,7.77; item 2=item 2 description=6.66,5.55; item 3=item 3 description=4.44,3.33';
        $data['sizes'] = 'large; small';
        $data['create_item_size_maps'] = true;

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();



        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $data = $response_array['data'];
        $menu_type_id = $data['menu_type_id'];
        $this->assertTrue($menu_type_id > 1000,"It should generate a good menu type id");

        $menu_type = MenuTypeAdapter::staticGetRecordByPrimaryKey($menu_type_id,'MenuTypeAdapter');
        $this->assertEquals('08:00:00',$menu_type['start_time']);
        $this->assertEquals('10:30:00',$menu_type['end_time']);

        $item_id = $data['created_items'][0]['item_id'];
        $records = ItemSizeAdapter::staticGetRecords(['item_id'=>$item_id],'ItemSizeAdapter');
        foreach ($records as $record) {
            $this->assertEquals('N',$record['active'],'All Price records shouldbe innactive');
        }



        return $menu_type_id;
    }

    /**
     * @depends testAddMenuTypesWithItemsAndSizes
     */
    function testGetMenuType($menu_type_id)
    {
        $menu_type_resource = Resource::find(new MenuTypeAdapter(),"$menu_type_id");
        $this->assertEquals('200',$menu_type_resource->priority);
        $menu_id = $menu_type_resource->menu_id;

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id";
//        $request = createRequestObject($url,'GET',null);
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();

        $response = $this->makeRequest($url, null,'GET',null);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $data = $response_array['data'];
        $this->assertNotNull($data['items'],'It should have an items section');
        $this->assertNotNull($data['sizes'],"it should have a sizes section");

    }


    /**
     * @depends testAddMenuTypesWithItemsAndSizes
     */
    function testUpdateMenuType($menu_type_id)
    {
        $menu_type_resource = Resource::find(new MenuTypeAdapter(),"$menu_type_id");
        $this->assertEquals('200',$menu_type_resource->priority);
        $menu_id = $menu_type_resource->menu_id;

        $records = SizeAdapter::staticGetRecords(['menu_type_id'=>$menu_type_id],'SizeAdapter');
        foreach($records as &$record) {
            $record['external_size_id'] = generateCode(10);
        }

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id";
        $data['priority'] = '201';
        $data['sizes'] = $records;
        $data['sizes'][] = ['size_name'=>"huge","active"=>"Y","new"=>true,"size_print_name"=>"huge","priority"=>300];
        $data['create_item_size_maps'] = false;

//        $_SERVER['PORTAL_REQUEST'] = true;
//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $this->assertEquals($menu_type_id,$resource->menu_type_id,"it should return the resouce with the menu type id");
//        $response_data = $resource->getDataFieldsReally();


        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];

        $menu_type_resource = Resource::find(new MenuTypeAdapter(),"$menu_type_id");
        $this->assertEquals(201,$menu_type_resource->priority);

        $this->assertCount(3,$response_data['items']);
        $this->assertCount(3,$response_data['sizes'],"It should now have 3 sizes");

        $size_hash_by_name = createHashmapFromArrayOfArraysByFieldName($response_data['sizes'],'size_name');
        $new_size_id = $size_hash_by_name['huge']['size_id'];
        $item_size_adapter = new ItemSizeAdapter(getM());
        $item_size_records = $item_size_adapter->getRecords(['size_id'=>$new_size_id]);
        $this->assertCount(0,$item_size_records,'It should NOT have created the item size records');

        return $menu_type_id;
    }

    /**
     * @depends testUpdateMenuType
     */
    function testUpdateMenuTypeWithDefaultSizeFlag($menu_type_id)
    {
        $menu_type_resource = Resource::find(new MenuTypeAdapter(),"$menu_type_id");
        $menu_id = $menu_type_resource->menu_id;

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id";
        $request = createRequestObject($url,'GET',null);

        $response = $this->makeRequest($url, null,'GET',null);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $data = $response_array['data'];
        $this->assertEquals(0,$data['sizes'][1]['default_selection'],"There should not be a default selection yet");
        $data['sizes'][1]['default_selection'] = 1;
        unset($data['items']);

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request,5);
//        $resource = $menu_controller->processV2Request();


        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];

        $size_adapter = new SizeAdapter(getM());
        $size_records = $size_adapter->getRecords(['menu_type_id'=>$menu_type_id]);
        $size_records_hash = createHashmapFromArrayOfArraysByFieldName($size_records,'size_name');
        $this->assertEquals(0,$size_records_hash['huge']['default_selection'],'Huge should NOT be a default selection');
        $this->assertEquals(0,$size_records_hash['small']['default_selection'],'Small should NOT be a default selection');
        $this->assertEquals(1,$size_records_hash['large']['default_selection'],'Large should be a default selection');
    }


    /**
     * @depends  testCreateNewMenuAttachMerchant
     */
    function testAddModifierGroupsWithModifierItems($menu_id)
    {
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/modifier_groups";
        $data['merchant_id'] = 0;
        $data['modifier_group_name'] = 'First modifier group name';
        $data['external_modifier_group_id'] = 'external-123';
        $data['modifier_description'] = 'Sumdum modififer First group description';
        $data['modifier_type'] = 'T';
        $data['active'] = 'Y';
        $data['priority'] = 100;
        $data['item_list'] = 'mod item 1; mod item 2; mod item 3; mod item 4';
        $data['default_item_price'] = 0.50;
        $data['default_item_max'] = 2;
        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];
        $modifier_group_id = $response_data['modifier_group_id'];
        $this->assertTrue($modifier_group_id > 1000,"It should generate a good menu type id");

        // lets add a second
        $data['merchant_id'] = 0;
        $data['modifier_group_name'] = 'Second modifier group name';
        $data['external_modifier_group_id'] = 'external-456';
        $data['modifier_description'] = 'Sumdum modififer group 2 description';
        $data['modifier_type'] = 'T';
        $data['active'] = 'Y';
        $data['priority'] = 100;
        $data['item_list'] = 'mod item 1;    mod item 2; mod item 3; mod item 4';
        $data['default_item_price'] = 0.50;
        $data['default_item_max'] = 2;
        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        return $modifier_group_id;
    }

    /**
     * @depends  testAddModifierGroupsWithModifierItems
     */
    function testGetModifierItemDataForEdit($modifier_group_id)
    {
        $modifier_group_resource = Resource::find(new ModifierGroupAdapter(),"$modifier_group_id");
        $menu_id = $modifier_group_resource->menu_id;

        $options[TONIC_FIND_BY_METADATA] = array("modifier_group_id"=>$modifier_group_id);
        $modifier_item_resources = Resource::findAll(new ModifierItemAdapter(),null,$options);
        $first_item = $modifier_item_resources[0];
        $first_item_id = $first_item->modifier_item_id;
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/modifier_groups/$modifier_group_id/modifier_items/$first_item_id?merchant_id=0";

//        $request = createRequestObject($url,'GET');
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();

        $response = $this->makeRequest($url, null,'GET');
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];

        $this->assertCount(3,$response_data['all_sizes'],'there should be 4 sizes, 3 regular and 1 default');
        $this->assertCount(1,$response_data['mod_size_prices'],"there should be 1 mod size price record");
        $this->assertEquals(3,$response_data['number_of_sizes'],"There should be a variable number_of_sizes, and it should be 4");
        $all_sizes = $response_data['all_sizes'];

        //now update a price and save
        $data["merchant_id"]=0;
        $data['number_of_sizes'] = sizeof($all_sizes);
        $data["modifier_item_name"]=$response_data["modifier_item_name"];
        $data["modifier_item_print_name"]=$response_data["modifier_item_name"];
        $data["modifier_item_max"]=1;
        $data["priority"]=$response_data["priority"];
        $data["update_child_records"]='yes';

        $data["2mod_sizeprice_id"]=$response_data['mod_size_prices'][0]['modifier_size_id'];
        $data["2size_id"]=0;
        $data["2active"]='Y';
        $data["2modifier_price"]=0.250;
        $data["2external_id"]="new-external123";

        $data["0mod_sizeprice_id"]=null;
        $data["0size_id"]=$all_sizes[0]['size_id'];
        $data["0active"]='N';
        $data["0modifier_price"]=null;
        $data["0external_id"]=null;

        $data["1mod_sizeprice_id"]=null;
        $data["1size_id"]=$all_sizes[1]['size_id'];;
        $data["1active"]='N';
        $data["1modifier_price"]=null;
        $data["1external_id"]=null;
        $data["apply_prices_to_all_modifier_items_in_group"]='no';

        $data['propogate_to_merchant_ids'] = 'All';

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/modifier_groups/$modifier_group_id/modifier_items/$first_item_id";

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();

        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];

        // now validate it was all updated.
        $modifier_resource = Resource::find(new ModifierItemAdapter(getM()),"$first_item_id");
        $this->assertEquals(1,$modifier_resource->modifier_item_max);

        $modifier_size_adapter = new ModifierSizeMapAdapter();
        $price_records = $modifier_size_adapter->getRecords(array("modifier_item_id"=>$first_item_id));
        $this->assertCount(3,$price_records,"there should be 3 price records. one for each merchant and the default");
        foreach ($price_records as $price_record) {
            $this->assertEquals(.25,$price_record['modifier_price']);
            $this->assertEquals('new-external123',$price_record['external_id'],"It should have saved and propogated the external id");
        }

    }

    /**
     * @depends  testAddModifierGroupsWithModifierItems
     */
    function testGetBasicModifierDataToCreateNewModifier($modifier_group_id)
    {
        $modifier_group_resource = Resource::find(new ModifierGroupAdapter(),"$modifier_group_id");
        $menu_id = $modifier_group_resource->menu_id;
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/modifier_groups/$modifier_group_id/modifier_items/new?merchant_id=0";

//        $request = createRequestObject($url,'GET');
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $data = $resource->getDataFieldsReally();

        $response = $this->makeRequest($url, null,'GET');
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $data = $response_array['data'];

        $this->assertCount(3,$data['all_sizes'],'there should be 3 sizes');
        $this->assertCount(0,$data['mod_size_prices'],"there should be 0 mod size price record");
        $this->assertEquals(3,$data['number_of_sizes'],"There should be a variable number_of_sizes, and it should be 3");
        $all_sizes = $data['all_sizes'];

        $data = array();
        $data["merchant_id"]=0;
        $data["modifier_item_name"]='Avocado';
        $data["modifier_item_print_name"]='Avocado';
        $data["modifier_item_max"]=8;
        $data["priority"]=150;
        $data["update_child_records"]='yes';

        $data["2mod_sizeprice_id"]=null;
        $data["2size_id"]=0;
        $data["2active"]='Y';
        $data["2modifier_price"]=0.250;
        $data["2external_id"]='external-12345';

        $data["0mod_sizeprice_id"]=null;
        $data["0size_id"]=$all_sizes[0]['size_id'];
        $data["0active"]='N';
        $data["0modifier_price"]=0.350;
        $data["0external_id"]='external-11111';

        $data["1mod_sizeprice_id"]=null;
        $data["1size_id"]=$all_sizes[1]['size_id'];;
        $data["1active"]='Y';
        $data["1modifier_price"]=0.45;
        $data["1external_id"]='external-22222';
        $data["apply_prices_to_all_modifier_items_in_group"]='no';

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/modifier_groups/$modifier_group_id/modifier_items";

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();

        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];

        // now validate it was all created.
        $modifier_item_id = $response_data['modifier_item_id'];
        $modifier_resource = Resource::find(new ModifierItemAdapter(getM()),"$modifier_item_id");
        $this->assertEquals('Avocado',$modifier_resource->modifier_item_name,"it should have been named avocado");
        $this->assertEquals($modifier_group_id,$modifier_resource->modifier_group_id);
        $this->assertEquals(150,$modifier_resource->priority);
        $this->assertEquals(8,$modifier_resource->modifier_item_max);

        //now check size prices.  first the default
        $options[TONIC_FIND_BY_METADATA] = ['modifier_item_id'=>$modifier_item_id,'merchant_id'=>'0'];
        $modifier_size_resources = Resource::findAll(new ModifierSizeMapAdapter(),null,$options);
        $this->assertCount(3,$modifier_size_resources,"there should have been 3 default created modifier size maps for the zero merchant");


        $options[TONIC_FIND_BY_METADATA] = ['modifier_item_id'=>$modifier_item_id];
        $modifier_size_resources = Resource::findAll(new ModifierSizeMapAdapter(),null,$options);
        $this->assertCount(9,$modifier_size_resources,"there should have been 9 total created modifier size maps");

    }

    /**
     * @depends testAddMenuTypesWithItemsAndSizes
     */
    function testValidMenuType($menu_type_id)
    {
        $menu_type_resource = Resource::find(new MenuTypeAdapter(),"$menu_type_id");
        $this->assertEquals("sumdum section",$menu_type_resource->menu_type_name);
        $this->assertTrue($menu_type_resource->menu_id > 0,"it should have a valid menu_id");

    }

    /**
     * @depends testAddMenuTypesWithItemsAndSizes
     */
    function testSizeCreation($menu_type_id)
    {
        $size_records = (new SizeAdapter())->getRecords(array("menu_type_id"=>$menu_type_id));
        $this->assertCount(3,$size_records,"there should be 3 size records");
    }

    /**
     * @depends testAddMenuTypesWithItemsAndSizes
     */
    function testItemCreation($menu_type_id)
    {
        $item_records = (new ItemAdapter())->getRecords(array("menu_type_id"=>$menu_type_id));
        $this->assertCount(3,$item_records,"It should have created 3 items");
        return $menu_type_id;
    }

    /**
     * @depends testItemCreation
     */
    function testItemSizeCreations($menu_type_id)
    {
        $item_records = (new ItemAdapter())->getRecords(array("menu_type_id"=>$menu_type_id));
        foreach ($item_records as $item_record) {
            $item_id = $item_record['item_id'];
            $price_records = (new ItemSizeAdapter())->getRecords(array("item_id"=>$item_id));
            // 2 sizes and 3 merchants (including 0 merchant)
            $this->assertCount(6,$price_records,"There should be 6 price records");
        }
    }

    /**
     * @depends testItemCreation
     */
    function testGetItemDataForEdit($menu_type_id)
    {
        $menu_type_resource = Resource::find(new MenuTypeAdapter(),"$menu_type_id");
        $menu_id = $menu_type_resource->menu_id;

        $records = MerchantMenuMapAdapter::staticGetRecords(['menu_id'=>$menu_id,'merchant_menu_type'=>'pickup'],'MerchantMenuMapAdapter');
        $propogate_ids = $records[0]['merchant_id'].',1231465,'.$records[1]['merchant_id'];
        $options[TONIC_FIND_BY_METADATA] = array("menu_type_id"=>$menu_type_id);
        $item_resources = Resource::findAll(new ItemAdapter(),null,$options);
        $first_item = $item_resources[0];
        $first_item_id = $first_item->item_id;
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id/menu_items/$first_item_id?merchant_id=0";
//        $request = createRequestObject($url,'GET');
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
        $response = $this->makeRequest($url, null,'GET');
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $data = $response_array['data'];
        $this->assertCount(2,$data['size_prices'],'there should be 2 default prices for this item');
        $this->assertCount(2,$data['modifier_groups'],"there should be 1 modifier group");
        $this->assertCount(0,$data['allowed_modifier_groups'],"There should not be any allowed modifier groups yet");

        // now update with and without propogation
        $data['propogate_prices'] = false;
        $i = 0;
        $priority = 50;
        $size_price_priority = [];
        $size_price_price = [];
        foreach ($data['size_prices'] as $size_price) {
            $data[$i."sizeprice_id"]=$size_price['item_size_id'];
            $data[$i."size_id"]=$size_price['size_id'];
            $data[$i."active"]="Y";
            $data[$i."price"]= ($i==2) ? 6.66 : $size_price['price'];
            $data[$i."external_id"]=$size_price['external_id'];
            $data[$i."itemsize_priority"]=$priority;
            $size_price_price[$size_price['size_id']] = $data[$i."price"];
            $size_price_priority[$size_price['size_id']] = $priority;
            $i++;

            $priority = $priority - 10;
        }
        // add this to test for bad data
        $data['number_of_sizes']++;
        //$data['propogate_to_merchant_ids'] = $propogate_ids;
        $data['propogate_to_merchant_ids'] = 'All';
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id/menu_items/$first_item_id";


//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();

        $response = $this->makeRequest($url, null,'POST',$data);

        //check to see if child prices went to 6.66
        foreach ($records as $record) {
            $merchant_id = $record['merchant_id'];
            foreach ($data['sizes'] as $size_record) {
                $size_id = $size_record['size_id'];
                $item_id = $first_item_id;
                $price_record = ItemSizeAdapter::staticGetRecord(['merchant_id'=>$merchant_id,'size_id'=>$size_id,'item_id'=>$item_id],'ItemSizeAdapter');
                $this->assertEquals($size_price_price[$size_id],$price_record['price'],'It shoudl have set the price to 6.66 for merchant id: '.$merchant_id);
                $this->assertEquals($size_price_priority[$size_id],$price_record['priority'],'It shoudl have propagated the priority');
            }
        }
    }

    /**
     * @depends testItemCreation
     */
    function testGetBasicDataToAddNewItemToMenuType($menu_type_id)
    {
        $menu_type_resource = Resource::find(new MenuTypeAdapter(),"$menu_type_id");
        $menu_id = $menu_type_resource->menu_id;
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id/menu_items/new";

//        $request = createRequestObject($url,'GET');
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $new_item_data = $resource->getDataFieldsReally();

        $response = $this->makeRequest($url, null,'GET');
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $new_item_data = $response_array['data'];
        $this->assertCount(3,$new_item_data['sizes'],'there should be 3 sizes for this item');
        $this->assertCount(3,$new_item_data['size_prices'],'there should be 3 default price place holders for this item');
        $this->assertCount(2,$new_item_data['modifier_groups'],"there should be 1 modifier group");
        $this->assertEquals(2,$new_item_data['number_of_groups'],"there should be a count of number of modifier_groups");
        $this->assertTrue(sizeof($new_item_data['allowed_modifier_groups']) == 0,"There should not be any allowed modifier groups yet");

        // we will only create 2 new price records, third will stay out
        $data = [];
        $data["merchant_id"]="0";
        $data["item_id"]=null;
        $data["item_name"]="sasquatch";
        $data["external_item_id"]="sas-1";
        $data["item_print_name"]="sasquatch";
        $data["description"]="this is the sasquatch sandwhich";
        $data["priority"]="100";
        $data["0sizeprice_id"]=null;
        $data["0size_id"]=$new_item_data['sizes'][0]['size_id'];
        $data["0active"]="Y";
        $data["0price"]="4.44";
        $data["0external_id"]="";
        $data["0itemsize_priority"]=$new_item_data['sizes'][0]['priority'];
        $data["0include"] = true;
        $data["1sizeprice_id"]=null;
        $data["1size_id"]=$new_item_data['sizes'][1]['size_id'];
        $data["1active"]="N";
        $data["1price"]="8.88";
        $data["1external_id"]="";
        $data["1itemsize_priority"]=$new_item_data['sizes'][1]['priority'];
        $data["1include"] = true;
        $data["2sizeprice_id"]=null;
        $data["2size_id"]=$new_item_data['sizes'][2]['size_id'];
        $data["2active"]="N";
        $data["2price"]="0.00";
        $data["2external_id"]="";
        $data["2itemsize_priority"]=$new_item_data['sizes'][2]['priority'];
        $data["2include"] = false;
        $data["number_of_possible_modifier_items"]="0";


        $data['number_of_groups']=$new_item_data['number_of_groups'];
        $data["0modifier_group_id"]=$new_item_data['modifier_groups'][0]['modifier_group_id'];
        $data["0allowed"]="ON";
        $data["0display_name"]="BIG GROUP";
        $data["0min"]="1";
        $data["0max"]="5";
        $data["0price_override"]="1.00";
        $data["0priority"]="100";
        $data["1modifier_group_id"]=$new_item_data['modifier_groups'][1]['modifier_group_id'];
        $data["1allowed"]="ON";
        $data["1display_name"]="OTHER GROUP";
        $data["1min"]="0";
        $data["1max"]="10";
        $data["1price_override"]="2.00";
        $data["1priority"]="90";
        $data["apply_IMGM_to_all_items"]="no";

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id/menu_items";

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();

        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];
        $item_id = $response_data['item_id'];

        $this->assertCount(2,$response_data['size_prices'],'there should be 2 default prices for this item');
        $this->assertCount(2,$response_data['modifier_groups'],"there should be 2 modifier group");
        $this->assertCount(2,$response_data['allowed_modifier_groups'],"There should be 2 allowed modifier group");

        // now make sure all daughter records were created.
        $price_records = (new ItemSizeAdapter())->getRecords(array("item_id"=>$item_id));
        $this->assertCount(6,$price_records,"There should be 6 price records");

        $active_price_records = (new ItemSizeAdapter())->getRecords(array("item_id"=>$response_data['item_id'],"size_id"=>$data["0size_id"]));
        $this->assertCount(3,$active_price_records,"there should be 3 active price records");
        foreach ($active_price_records as $record) {
            $this->assertEquals('Y',$record['active'],"It should be active. merchant_id: ".$record['merchant_id']);
        }

        $inactive_price_records = (new ItemSizeAdapter())->getRecords(array("item_id"=>$response_data['item_id'],"size_id"=>$data["1size_id"]));
        $this->assertCount(3,$inactive_price_records,"there should be 3 inactive price records");
        foreach ($inactive_price_records as $record) {
            $this->assertEquals('N',$record['active'],"It should be active. merchant_id: ".$record['merchant_id']);
        }

        // see if all item modifier group maps were created
        $imgma = new ItemModifierGroupMapAdapter(getM());
        $records = $imgma->getRecords(['item_id'=>$item_id]);
        $this->assertCount(6,$records,'It should have created the item modifier group map daughter records');

        // now turn off other group and make sure the IMGM propogates
        $data = [];
        $data["merchant_id"]="0";
        $data["item_id"]=$item_id;
        $data["item_name"]="sasquatch";
        $data["external_item_id"]="sas-1";
        $data["item_print_name"]="sasquatch";
        $data["description"]="this is the sasquatch sandwhich";
        $data["priority"]="100";
        $data['number_of_sizes'] = 2;
        $data["0sizeprice_id"]=$response_data['size_prices'][$new_item_data['sizes'][0]['size_id']]['item_size_id'];
        $data["0size_id"]=$new_item_data['sizes'][0]['size_id'];
        $data["0active"]="Y";
        $data["0price"]="4.43";
        $data["0external_id"]="";
        $data["0itemsize_priority"]=$new_item_data['sizes'][0]['priority'];
        $data["1sizeprice_id"]=$response_data['size_prices'][$new_item_data['sizes'][1]['size_id']]['item_size_id'];
        $data["1size_id"]=$new_item_data['sizes'][1]['size_id'];
        $data["1active"]="N";
        $data["1price"]="8.88";
        $data["1external_id"]="";
        $data["1itemsize_priority"]=$new_item_data['sizes'][1]['priority'];
        $data["number_of_possible_modifier_items"]="0";


        $data['number_of_groups']=$new_item_data['number_of_groups'];
        $data["0item_modifier_group_map_id"] = $response_data['allowed_modifier_groups'][$new_item_data['modifier_groups'][0]['modifier_group_id']]['map_id'];
        $data["0modifier_group_id"]=$new_item_data['modifier_groups'][0]['modifier_group_id'];
        $data["0allowed"]="ON";
        // change display name
        $data["0display_name"]="REALLY BIG GROUP";
        $data["0min"]="1";
        $data["0max"]="5";
        // change price override
        $data["0price_override"]=".75";
        $data["0price_max"] = "88888.00";
        $data["0priority"]="100";

        $data["1item_modifier_group_map_id"] = $response_data['allowed_modifier_groups'][$new_item_data['modifier_groups'][1]['modifier_group_id']]['map_id'];
        $data["1modifier_group_id"]=$new_item_data['modifier_groups'][1]['modifier_group_id'];
        // set to off
        $data["1allowed"]="OFF";
        $data["1display_name"]="OTHER GROUP";
        $data["1min"]="0";
        $data["1max"]="10";
        $data["1price_override"]="2.00";
        $data["1priority"]="90";
        $data["apply_IMGM_to_all_items"]="no";
        $data['propogate_to_merchant_ids'] = 'All';

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id/menu_items/$item_id";

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();

        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];


        // check that imgm delete propogated
        $imgma = new ItemModifierGroupMapAdapter(getM());
        $records = $imgma->getRecords(['item_id'=>$item_id]);
        $this->assertCount(3,$records,'It should have deleted half of the item modifier group map daughter records. There should only be 3 now');

        // check override price propogations
        foreach ($records as $record) {
            $this->assertEquals(.75,$record['price_override'],'the price should have propogated to all');
        }
        return $response_data['item_id'];
    }

    /**
     * @depends testGetBasicDataToAddNewItemToMenuType
     */
    function testAddComesWith($item_id)
    {
        $edit_item_adapter = new EditItemAdapter(getM());
        $menu_id = $edit_item_adapter->getMenuIdFromItemId($item_id);
        $menu_type_id = $edit_item_adapter->getMenuTypeIdFromItemId($item_id);

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id/menu_items/$item_id";
//        $request = createRequestObject($url,'GET');
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $full_response_array = $resource->getDataFieldsReally();

        $response = $this->makeRequest($url, null,'GET');
        $full_response_array = json_decode($response,true);
        $this->assertEquals(200,$full_response_array['http_code']);
        $this->assertCount(1,$full_response_array['data']['allowed_modifier_groups'],'There should be one allowed modifier group');
        $response_array = $full_response_array['data'];
        $data = [];
        $data["merchant_id"]="0";
        $data["item_id"]=$item_id;
        $data["item_name"]="sasquatch";
        $data["external_item_id"]="sas-1";
        $data["item_print_name"]="sasquatch";
        $data["description"]="this is the sasquatch sandwhich";
        $data["priority"]="100";
        $data["0sizeprice_id"]=null;
        $data["0size_id"]=$response_array['sizes'][0]['size_id'];
        $data["0active"]="Y";
        $data["0price"]="4.44";
        $data["0external_id"]="";
        $data["0itemsize_priority"]=$response_array['sizes'][0]['priority'];
        $data["1sizeprice_id"]=null;
        $data["1size_id"]=$response_array['sizes'][1]['size_id'];
        $data["1active"]="N";
        $data["1price"]="8.88";
        $data["1external_id"]="";
        $data["1itemsize_priority"]=$response_array['sizes'][1]['priority'];
        $data["number_of_possible_modifier_items"]="0";
        $data['number_of_groups']=$response_array['number_of_groups'];
        $data['0item_modifier_group_map_id']=$response_array['allowed_modifier_groups'][$response_array['modifier_groups'][0]['modifier_group_id']]['map_id'];
        $data["0modifier_group_id"]=$response_array['modifier_groups'][0]['modifier_group_id'];
        $data["0allowed"]="ON";
        $data["0display_name"]="BIG GROUP";
        $data["0min"]="1";
        $data["0max"]="6";
        $data["0price_override"]="1.00";
        $data["0priority"]="100";
        $data["1modifier_group_id"]=$response_array['modifier_groups'][1]['modifier_group_id'];
        $data["1allowed"]="OFF";
        $data["1display_name"]="OTHER GROUP";
        $data["1min"]="";
        $data["1max"]="";
        $data["1price_override"]="0.00";
        $data["1priority"]="90";
        $data["apply_IMGM_to_all_items"]="no";

        $comes_with_index = 0;
        foreach ($response_array['allowed_modifier_groups'] as $allowed_modifier_grouop) {
            foreach ($allowed_modifier_grouop['modifier_items'] as $modifier_item) {
                $data[$comes_with_index."item_modifier_item_map_id"] = null;
                $data[$comes_with_index."modifier_item_id"] = $modifier_item['modifier_item_id'];
                $data[$comes_with_index."comes_with"] = ($comes_with_index % 2 == 0) ? 'ON' : 'OFF';
                $comes_with_index++;
            }
        }
        $data['number_of_possible_modifier_items'] = $comes_with_index + 1; // we add the 1 because index starts with 0

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id/menu_items/$item_id";

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();


        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];


        // now validate that there were 2 Item_Modifier_Item_Map records created
        $imima = new ItemModifierItemMapAdapter(getM());
        $records = $imima->getRecords(['item_id'=>$item_id]);
        $this->assertCount(3,$records,"It should have 3 comes with since there are 5 modifier and 3 have even indexes (0,2,4)");

        // see if allowed max went to 6
        $this->assertEquals(6,array_pop($response_data['allowed_modifier_groups'])['max']);

        // get new data to try turning off comes with
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id/menu_items/$item_id?merchant_id=0";
        $response = $this->makeRequest($url, null,'GET');
        $response_array = json_decode($response,true);
        $response_data = $response_array['data'];


        $data = [];
        $data["merchant_id"]="0";
        $data["item_id"]=$item_id;
        $data["item_name"]="sasquatch";
        $data["external_item_id"]="sas-1";
        $data["item_print_name"]="sasquatch";
        $data["description"]="this is the sasquatch sandwhich";
        $data["priority"]="100";

        $data["apply_IMGM_to_all_items"]="no";

        $comes_with_index = 0;
        foreach ($response_data['comes_with_modifier_items'] as $modifier_item_id => $comes_with_modifier_item) {

                $data[$comes_with_index."item_modifier_item_map_id"] = $comes_with_modifier_item['map_id'];
                $data[$comes_with_index."modifier_item_id"] = $modifier_item['modifier_item_id'];
                $data[$comes_with_index."comes_with"] = ($comes_with_index % 2 == 0) ? 'ON' : 'OFF';
                $comes_with_index++;

        }
        $data['number_of_possible_modifier_items'] = 3;
        $data['propogate_to_merchant_ids'] = ['all'];

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/menu_types/$menu_type_id/menu_items/$item_id";

        $request = createRequestObject($url,'POST',json_encode($data));
        $menu_controller = new MenuController(getM(),null,$request);
        $resource = $menu_controller->processV2Request();
        $response_data = $resource->getDataFieldsReally();


//        $response = $this->makeRequest($url, null,'POST',$data);
//        $response_array = json_decode($response,true);
//        $this->assertEquals(200,$response_array['http_code']);
//        $response_data = $response_array['data'];

        // now validate that are now only 2 Item_Modifier_Item_Map records
        $imima = new ItemModifierItemMapAdapter(getM());
        $records = $imima->getRecords(['item_id'=>$item_id]);
        $this->assertCount(2,$records,"It should have 2 comes with since we turned one off");





    }





    /**
     * @depends testAddModifierGroupsWithModifierItems
     */
    function testModifierGroupCreated($modifier_group_id)
    {
        $modfifer_group_resource = Resource::find(new ModifierGroupAdapter(),"$modifier_group_id");
        $this->assertEquals("First modifier group name",$modfifer_group_resource->modifier_group_name);
        $this->assertTrue($modfifer_group_resource->menu_id > 0," it should have the menu id");
    }

    /**
     * @depends testAddModifierGroupsWithModifierItems
     */
    function testModifierItemCreation($modifier_group_id)
    {
        $modifier_item_records = (new ModifierItemAdapter())->getRecords(array("modifier_group_id"=>$modifier_group_id));
        $this->assertCount(5,$modifier_item_records,"It should have created 5 items");
        return $modifier_group_id;
    }

    /**
     * @depends testModifierItemCreation
     */
    function testModifierSizeCreations($menu_type_id)
    {
        $modifier_item_records = (new ModifierItemAdapter())->getRecords(array("menu_type_id"=>$menu_type_id));
        foreach ($modifier_item_records as $modifier_item_record) {
            $modifier_item_id = $modifier_item_record['modifier_item_id'];
            $price_records = (new ModifierSizeMapAdapter())->getRecords(array("modifier_item_id"=>$modifier_item_id));
            // 1 size (defualt 0) and 3 merchants (including 0 merchant)
            $this->assertCount(12,$price_records,"There should be 12 price records");
            foreach($price_records as $price_record) {
                $this->assertEquals(.50,$price_record['price']);
            }
        }
    }

    /**
     * @depends  testCreateNewMenuAttachMerchant
     */
    function testGetMenu($menu_id)
    {
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id";
        $response = $this->makeRequest($url, null,'GET');
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $menu = $response_array['data']['menu'];
        return $menu;
    }

    /**
     * @depends  testGetMenu
     */
    function testMenuTypesReturned($menu)
    {
        $this->assertCount(1,$menu['menu_types'],"It should have one menu type");
    }

    /**
     * @depends  testGetMenu
     */
    function testModifierGroupsReturned($menu)
    {
        $this->assertCount(2,$menu['modifier_groups'],"It should have two modifier groups ");
    }

    /**
     * @depends  testGetMenu
     */
    function testItemsReturned($menu)
    {
        $this->assertCount(4,$menu['menu_types'][0]['menu_items'],"It should have 4 items ");

    }

    /**
     * @depends  testGetMenu
     */
    function testModifierItemsReturned($menu)
    {
        $this->assertCount(5,$menu['modifier_groups'][0]['modifier_items'],"It should have 5 modifier items");
    }


//    /**
//     * @depends  testCreateNewMenuAttachMerchant
//     */
//    function testCorrectErrorFormat($menu_id)
//    {
//        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/sum_bad_endpoint";
//        $data['bad'] = 'Really Bad';
//        $response = $this->makeRequest($url, null,'POST',$data);
//        $response_array = json_decode($response,true);
//        $this->assertEquals(404,$response_array['http_code']);
//        $this->assertEquals("unknown endpoint",$response_array['error']['error_message']);
//    }



    /**
     * @depends  testCreateNewMenuAttachMerchant
     */
    function testAttacheNewMerchantToMenuCreateDaughterRecords($menu_id)
    {
        $menu_item_sizes_zero = CompleteMenu::getAllItemSizesAsResources($menu_id,0);
        $count = sizeof($menu_item_sizes_zero);
        $this->assertTrue($count > 1);

        $modifier_item_sizes_zero = CompleteMenu::getAllModifierItemSizesAsArray($menu_id,'Y',0);
        $mod_count = sizeof($modifier_item_sizes_zero);
        $this->assertTrue($mod_count > 1);

        $item_modifier_group_maps_zero = CompleteMenu::staticGetAllItemModifierGroupMapsAsResources($menu_id,0);
        $imgm_count = sizeof($item_modifier_group_maps_zero);
        $this->assertTrue($imgm_count > 0);


        $merchant_resource = createNewTestMerchant();
        $merchant_id = $merchant_resource->merchant_id;

        // create pretend mapping that shoudl get deleted
        $new_menu_id = createTestMenuWithNnumberOfItems(1);
        $sql = "INSERT INTO Merchant_Menu_Map (`merchant_id`,`menu_id`) VALUES ($merchant_id,$new_menu_id)";
        $merchant_menu_map_adapter = new MerchantMenuMapAdapter(getM());
        $merchant_menu_map_adapter->_query($sql);
        $records = $merchant_menu_map_adapter->getRecords(['merchant_id'=>$merchant_id]);
        $this->assertCount(1,$records,"it should have one MMM record");
        $this->assertEquals($new_menu_id,$records[0]['menu_id']);

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/merchants/$merchant_id";
        $data = ["merchant_menu_type"=>'both'];


//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();



        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];
        $this->assertEquals("Success",$response_data['result']);

        $records = MerchantMenuMapAdapter::staticGetRecords(['merchant_id'=>$merchant_id],'MerchantMenuMapAdapter');
        $this->assertCount(2,$records);
        $hash_map = createHashmapFromArrayOfArraysByFieldName($records,'merchant_menu_type');
        $this->assertNotNull($hash_map['delivery']);
        $this->assertNotNull($hash_map['pickup']);
        //$this->assertEquals('delivery',$records[0]['merchant_menu_type']);

        $menu_item_sizes = CompleteMenu::getAllItemSizesAsResources($menu_id,$merchant_id);
        $this->assertCount($count,$menu_item_sizes,'It should have created all the daughter records');

        $modifier_item_sizes = CompleteMenu::getAllModifierItemSizesAsArray($menu_id,'Y',$merchant_id);
        $this->assertCount($mod_count,$modifier_item_sizes,'It should have created all the modifier daughter records');

        $item_modifier_group_maps = CompleteMenu::staticGetAllItemModifierGroupMapsAsResources($menu_id,$merchant_id);
        $this->assertCount($imgm_count,$item_modifier_group_maps,'It should have created all the item modifier group map daughter records');

        return ['merchant_id'=>$merchant_id,'menu_id'=>$menu_id];
    }

    /**
     * @depends testAttacheNewMerchantToMenuCreateDaughterRecords
     */
    function testCopyPrices($existing)
    {
        $source_merchant_id = $existing['merchant_id'];
        $menu_id = $existing['menu_id'];

        $merchant_resource = createNewTestMerchant();
        $merchant_id = $merchant_resource->merchant_id;

        $source_item_records = CompleteMenu::getAllItemSizesAsResources($menu_id,0);
        $expected_count = sizeof($source_item_records);

        $destination_item_records = CompleteMenu::getAllItemSizesAsResources($menu_id,$merchant_id);
        $this->assertCount(0,$destination_item_records);

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/copypricelist";
        $data = ["source_merchant_id"=>'master',"destination_merchant_id"=>$merchant_id];

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request);
//        $resource = $menu_controller->processV2Request();
//        $response_data = $resource->getDataFieldsReally();

        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(422,$response_array['http_code']);
        $error_message = $response_array['error']['error_message'];
        $this->assertEquals("Destination merchant is not attached to loaded menu",$error_message);

        // map it to a menu
        $mmm_adapter = new MerchantMenuMapAdapter(getM());
        Resource::createByData($mmm_adapter,['menu_id'=>$menu_id,'merchant_id'=>$merchant_id]);

        $request = createRequestObject($url,'POST',json_encode($data));
        $menu_controller = new MenuController(getM(),null,$request);
        $resource = $menu_controller->processV2Request();
        $response_data = $resource->getDataFieldsReally();


        $response = $this->makeRequest($url, null,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);
        $response_data = $response_array['data'];
        $this->assertEquals("Success",$response_data['result']);

        $new_item_records = CompleteMenu::getAllItemSizesAsResources($menu_id,$source_merchant_id);
        $this->assertCount($expected_count,$new_item_records,'It should now have the same number of item_size records as the source merchant');

    }


    function getExternalId()
    {
        if ($external_id = getContext()) {
            // use it
        } else {
            $external_id = "com.splickit.vtwoapi";
        }
        return $external_id;
    }

    function makeRequest($url,$userpassword,$method = 'GET',$data = null)
    {
        logData($data," curl data");
        unset($this->info);
        $method = strtoupper($method);
        $curl = curl_init($url);
        if ($userpassword) {
            curl_setopt($curl, CURLOPT_USERPWD, $userpassword);
        }
        $external_id = getContext();
        $headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:AdminDispatchTest","NO_CC_CALL:true");
        if ($authentication_token = $data['splickit_authentication_token']) {
            $headers[] = "splickit_authentication_token:$authentication_token";
        }
        if ($data['headers']) {
            $headers = $data['headers'];
            unset($data['headers']);
        }
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            if ($data) {
                $json = json_encode($data);
                curl_setopt($curl, CURLOPT_POSTFIELDS,$json);
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Content-Length: ' . strlen($json);
            }
        } else if ($method == 'DELETE') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        logCurl($url,$method,$userpassword,$headers,$json);
        $result = curl_exec($curl);

        $this->info = curl_getinfo($curl);
        curl_close($curl);
        return $result;
    }

    static function setUpBeforeClass()
    {
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        ini_set('max_execution_time',300);

        $skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty("vtwoapi","vtwoapi",252, 101);
        setContext('com.splickit.vtwoapi');

        $_SERVER['log_level'] = 5;
    }

    static function tearDownAfterClass()
    {
        //mysqli_query("ROLLBACK");
        date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
        $suite = new PHPUnit_Framework_TestSuite( __CLASS__);
        PHPUnit_TextUI_TestRunner::run( $suite);
    }



}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    PortalMenuDispatchTest::main();
}

?>