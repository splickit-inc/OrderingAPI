<?php
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'merchantadapter.php';
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'menuadapter.php';
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'menutypeadapter.php';
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'sizeadapter.php';
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'itemadapter.php';
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'itemsizeadapter.php';
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'modifiergroupadapter.php';
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'modifieritemadapter.php';
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'modifiersizemapadapter.php';
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'itemmodifiergroupmapadapter.php';
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'itemmodifieritemmapadapter.php';
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'merchantmenumapadapter.php';

require_once 'lib'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'splickitcontroller.php';
require_once 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'mailit.php';

class MenuController extends SplickitController
{
	protected $menu_types;
	protected $modifier_group_names = array();
	protected $menu_record;
	protected $merchant_id;
	protected $menu_type_record;
	protected $modifier_group_record;

	protected $submitted_menu_id;
	protected $submitted_menu_type_id;
	protected $submitted_modifier_group_id;

	protected $menu_type_adapter;
    protected $modifier_group_adapter;

    protected $price_copy_error_messages;
    protected $clean_price_copy = true;

	function MenuController($mt,$u,$r,$l = 0)
	{
		parent::SplickitController($mt,$u,$r,$l);
		$this->adapter = new MenuAdapter($this->mimetypes);
        if (preg_match('%/menus/([0-9]{4,15})%', $r->url, $matches)) {
            $menu_id = $matches[1];
            $this->submitted_menu_id = $menu_id;
            $this->setMenuFromId($menu_id);
        }
        if (preg_match('%/menu_types/([0-9]{4,15})%', $r->url, $mt_matches)) {
            $menu_type_id = $mt_matches[1];
            $this->submitted_menu_type_id = $menu_type_id;
            $this->setMenuTypeFromId($menu_type_id);
        }
        if (preg_match('%/modifier_groups/([0-9]{4,15})%', $r->url, $mg_matches)) {
            $modifier_group_id = $mg_matches[1];
            $this->submitted_modifier_group_id = $modifier_group_id;
            $this->setModifierGroupFromId($modifier_group_id);
        }
        if (isset($this->request->data['merchant_id'])) {
            $this->merchant_id = $this->request->data['merchant_id'];
        } else {
            $this->merchant_id = '0';
        }
		$this->log_level = $l;
	}



	function processV2Request()
    {
        if (isset($this->submitted_menu_id)) {
            if ($this->hasRequestForDestination('menu_items')) {
                $resource = $this->processEditItem();
                $this->updateMenuStatus();
                return $resource;
            } else if ($this->hasRequestForDestination('modifier_items')) {
                $resource =  $this->processEditModifierItem();
                $this->updateMenuStatus();
                return $resource;
            } else if ($this->hasRequestForDestination('menu_types')) {
                $adapter = $this->getMenuTypeAdapter();
                $object = 'menu_types';
            } else if ($this->hasRequestForDestination('modifier_groups')) {
                $adapter = $this->getModifierGroupAdapter();
                $object = 'modifier_groups';
            } else if ($this->hasRequestForDestination('getpricelist')) {
                $list = $this->getMerchantPriceListForEdit($this->submitted_menu_id,$this->merchant_id);
                $resource =  Resource::dummyfactory($list);
                $this->updateMenuStatus();
                return $resource;
            } else if ($this->hasRequestForDestination('item_price')) {
                $resource =  $this->updateItemPriceRecord($this->request->data);
                $this->updateMenuStatus();
                return $resource;
            } else if ($this->hasRequestForDestination('modifier_price')) {
                $resource =  $this->updateModifierItemPriceRecord($this->request->data);
                $this->updateMenuStatus();
                return $resource;
            } else if ($this->hasRequestForDestination('merchants')) {
                $resource =  $this->processMerchantRequest($this->request->data);
                $this->updateMenuStatus();
                return $resource;
            } else if ($this->hasRequestForDestination('nutrition')) {
                $resource =  $this->processNutritionRequest($this->request->data);
                return $resource;
            } else if ($this->hasRequestForDestination('copypricelist')) {
                $resource =  $this->processCopyPriceListRequest($this->request->data);
                $this->updateMenuStatus();
                return $resource;
            } else {
                // get menu
                if ($this->isThisRequestMethodAPost()) {
                    // its an update menu call
                    $menu_resource = Resource::find($this->adapter,$this->submitted_menu_id);
                    $menu_resource->_updateResource($this->request);
                    $this->updateMenuStatus();
                }
                return $this->getMenuForEdit();
            }
            myerror_log("we have the object: ".$object);
            $adapter->setMenuResourceByMenuId($this->getMenuID());
            $adapter->merchant_id = $this->getMerchantId();
            if (preg_match("%/$object/([0-9]{4,15})%", $this->request->url, $matches)) {
                //edit menu object
                myerror_log("we have the object id: ".$matches[1]);
                if ($this->isThisRequestMethodAGet()) {
                    $adapter->get_child_objects = true;
                    return Resource::find($adapter,$matches[1]);
                } else if ($this->isThisRequestMethodAPost()) {
                    $resource = Resource::find($adapter, "" . $matches[1]);
                    if ($resource->saveResourceFromData($this->data)) {
                        $this->updateMenuStatus();
                        $resource->_adapter->get_child_objects = true;
                        return $resource->getRefreshedResource();
                    } else {
                        return createErrorResourceWithHttpCode("Could not save resource", 422, 422);
                    }
                } else if ($this->isThisRequestMethodADelete()) {
                    $resource = Resource::find($adapter, "" . $matches[1]);
                    $return = $this->doLogicalDeletefAndFormatReturn($resource);
                    $this->updateMenuStatus();
                    return $return;
                } else {
                    return createErrorResourceWithHttpCode("unknown endpoint for object",422,422);
                }
            } else {
                //create new menu object;
                if ($this->isThisRequestMethodAPost()) {
                    $adapter->get_child_objects = true;
                    $object = $this->createMenuChildResource($adapter, $this->data);
                    return $object;
                } else {
                    return createErrorResourceWithHttpCode("unknown method for endpoint",422,0);
                }
            }
        } else {
            // create new menu
            $resource = new Resource($this->adapter,$this->request->data);
            if ($resource->save()) {
                return $resource->refreshResource();
            } else {
                return createErrorResourceWithHttpCode("There was an error and the menu could not be created. ".$this->adapter->getLastErrorText(),500,$this->adapter->getLastErrorNo());
            }

        }
    }

    function processCopyPriceListRequest($data)
    {
        if ($source_merchant_id = $data['source_merchant_id']) {
            if ($this->isMerchantAttachedToLoadedMenu($source_merchant_id)) {
                if ($destination_merchant_id = $data['destination_merchant_id']) {
                    if ($this->isMerchantAttachedToLoadedMenu($destination_merchant_id)) {
                        if ($this->copyPricesFromMerchantToMerchant($source_merchant_id,$destination_merchant_id)) {
                            return Resource::dummyfactory(['result' => 'Success']);
                        } else {
                            return Resource::dummyfactory(['result' => 'Failure',"http_code" => 500]);
                        }
                    } else {
                        return createErrorResourceWithHttpCode("Destination merchant is not attached to loaded menu",422,422);
                    }
                } else {
                    return createErrorResourceWithHttpCode("No destination merchant_id submitted",422,422);
                }
            } else {
                return createErrorResourceWithHttpCode("Source merchant is not attached to loaded menu",422,422);
            }
        } else {
            return createErrorResourceWithHttpCode("No source merchant_id submitted",422,422);
        }
    }

    function isMerchantAttachedToLoadedMenu($merchant_id)
    {
        if (strtolower($merchant_id) == 'master') {
            return true;
        }
        $mmma = new MerchantMenuMapAdapter(getM());
        if ($record = $mmma->getRecords(['menu_id'=>$this->submitted_menu_id,'merchant_id'=>$merchant_id])) {
            return true;
        } else {
            return false;
        }
    }

    function copyPricesFromMerchantToMerchant($source_merchant_id,$destination_merchant_id)
    {
        // first delete all existing price records
        if ($destination_merchant_id < 1000) {
            return createErrorResourceWithHttpCode("Not a valid destination merchant_id submitted: $destination_merchant_id",422,422);
        }

        if (strtolower($source_merchant_id) == 'master') {
            $source_merchant_id = 0;
        } else if ($source_merchant_id < 1000) {
            return createErrorResourceWithHttpCode("Not a valid source merchant_id submitted: $source_merchant_id",422,422);
        }

        $item_size_sql = "DELETE FROM Item_Size_Map WHERE merchant_id = $destination_merchant_id";
        $item_size_map_adapter = new ItemSizeAdapter(getM());
        $r1 = $item_size_map_adapter->_query($item_size_sql);

        $modifier_size_sql = "DELETE FROM Modifier_Size_Map WHERE merchant_id = $destination_merchant_id";
        $modifier_size_map_adapter = new ModifierSizeMapAdapter(getM());
        $r2 = $modifier_size_map_adapter->_query($modifier_size_sql);

        $imgm_sql = "DELETE FROM Item_Modifier_Group_Map WHERE merchant_id = $destination_merchant_id";
        $imgm_adapter = new ItemModifierGroupMapAdapter(getM());
        $r3 =  $imgm_adapter->_query($imgm_sql);

        $price_override_sql = "DELETE FROM Item_Modifier_Item_Price_Override_By_Sizes WHERE merchant_id = $destination_merchant_id";
        $imipobs_adapter = new ItemModifierItemPriceOverrideBySizesAdapter(getM());
        $r4 = $imipobs_adapter->_query($price_override_sql);

        foreach (CompleteMenu::getAllItemSizesAsResources($this->submitted_menu_id,$source_merchant_id) as $item_price_resource) {
            $this->copyPriceResourceToDestinationMerchantId($item_price_resource,$destination_merchant_id);
        }

        foreach (CompleteMenu::getAllModifierItemSizesAsResoures($this->submitted_menu_id,$source_merchant_id) as $modifier_price_resource) {
            $this->copyPriceResourceToDestinationMerchantId($modifier_price_resource,$destination_merchant_id);
        }

        foreach (CompleteMenu::staticGetAllItemModifierGroupMapsAsResources($this->submitted_menu_id,$source_merchant_id) as $imgm_resource) {
            $this->copyPriceResourceToDestinationMerchantId($imgm_resource,$destination_merchant_id);
        }

        $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $source_merchant_id;
        $po_resources = Resource::findAll($imipobs_adapter,null,$options);
        foreach ($po_resources as $po_resource) {
            $this->copyPriceResourceToDestinationMerchantId($po_resource,$destination_merchant_id);
        }

        return $this->clean_price_copy;
    }

    /**
     * @param $splickit_price_resource Resource
     * @param $destination_merchant_id integer
     * @return boolean
     */
    function copyPriceResourceToDestinationMerchantId($splickit_price_resource,$destination_merchant_id)
    {
        $primary_key = $splickit_price_resource->_adapter->primaryKeys[0];
        unset($splickit_price_resource->$primary_key);
        $splickit_price_resource->merchant_id = $destination_merchant_id;
        $splickit_price_resource->_exists = false;
        $splickit_price_resource->created = time();
        $splickit_price_resource->modified = time();
        if ($splickit_price_resource->save()) {
            return;
        } else {
            myerror_log("PRICE COPY ERROR sql: ".$splickit_price_resource->_adapter->getLastRunSQL());
            myerror_log("PRICE COPY ERROR message: ".$splickit_price_resource->_adapter->getLastErrorText());
            $this->price_copy_error_messages .= $splickit_price_resource->_adapter->getLastErrorText()."\r\n";
            $this->clean_price_copy = false;
            return;
        }
    }

    function doLogicalDeletefAndFormatReturn($resource)
    {
        $resource->logical_delete = 'Y';
        if ($resource->save()) {
            return Resource::dummyfactory(array("result"=>"Success"));
        } else {
            return Resource::dummyfactory(array("result"=>"Failure"));
        }
    }

    function buildNutritionGrid($menu_id)
    {
        $complete_menu = new CompleteMenu($menu_id);
        $nutrition_records_by_item_id_size_id = $complete_menu->getAllNuritionRecordsByItemIdAndSizeId($menu_id);
        $complete_menu = $complete_menu->getCompleteMenu($menu_id);
        $nutrition_grid = [];
        foreach ($complete_menu['menu_types'] as $menu_type) {
            foreach ($menu_type['menu_items'] as $menu_item) {
                foreach ($menu_type['sizes'] as $size) {
                    $nutrition_key = $menu_item['item_id'].'-'.$size['size_id'];
                    if ($nutrition_record = $nutrition_records_by_item_id_size_id[$nutrition_key]) {
                        $nutrition_grid['menu_types'][$menu_type['menu_type_name']][$menu_item['item_name'].'-'.$size['size_name']] = $nutrition_record;
                    } else {
                        $nutrition_grid['menu_types'][$menu_type['menu_type_name']][$menu_item['item_name'].'-'.$size['size_name']] = ['item_id'=>$menu_item['item_id'],'size_id'=>$size['size_id']];
                    }
                }
            }
        }
        return $nutrition_grid;
    }

    function processNutritionRequest($data)
    {
        if ($this->isThisRequestMethodAGet()) {
            $nutrition_grid = $this->buildNutritionGrid($this->submitted_menu_id);
            return Resource::dummyfactory($nutrition_grid);
        } else if ($this->isThisRequestMethodAPost()) {
            $nutrition_adapter = new NutritionItemSizeInfosAdapter(getM());
            if ($id = $data['id']) {
                // existing record
                $nutrition_resource = Resource::find($nutrition_adapter,"$id");
                $nutrition_resource->saveResourceFromData($data);
            } else {
                $nutrition_resource = Resource::createByData($nutrition_adapter,$data);
            }
            $nutrition_grid = $this->buildNutritionGrid($this->submitted_menu_id);
            return Resource::dummyfactory($nutrition_grid);
        } else {
            throw new Exception("No endpoints for this request method");
        }
    }

    function processMerchantRequest($data)
    {
        if (preg_match("%/merchants/([0-9]{4,15})%", $this->request->url, $matches)) {
            $menu_id = $this->submitted_menu_id;
            $merchant_id = $matches[1];
            $add_both = false;
            $mechant_menu_map_data = ['merchant_id'=>$merchant_id,'menu_id'=>$menu_id];
            if (isset($data['merchant_menu_type'])) {
                if (strtolower($data['merchant_menu_type']) == 'pickup') {
                    $mechant_menu_map_data['merchant_menu_type'] = 'pickup';
                } else if (strtolower($data['merchant_menu_type']) == 'delivery') {
                    $mechant_menu_map_data['merchant_menu_type'] = 'delivery';
                } else if (strtolower($data['merchant_menu_type']) == 'both') {
                    $mechant_menu_map_data['merchant_menu_type'] = 'pickup';
                    $add_both = true;
                }
            }
            // if type doesn't get assigned the db will default to pickup

            $mmma = new MerchantMenuMapAdapter(getM());
            // delete existing
            $sql = "DELETE FROM Merchant_Menu_Map WHERE merchant_id = $merchant_id";
            $mmma->_query($sql);

            Resource::createByData($mmma,$mechant_menu_map_data);
            if ($add_both) {
                $mechant_menu_map_data['merchant_menu_type'] = 'delivery';
                Resource::createByData($mmma,$mechant_menu_map_data);
            }
            if ($mmma->_insertId() > 1000) {
                // now create the daughter records
                $menu_item_sizes_zero = CompleteMenu::getAllItemSizesAsResources($menu_id,0);
                $item_size_count = sizeof($menu_item_sizes_zero);
                $is = 0;
                foreach ($menu_item_sizes_zero as $size_resource) {
                    $size_resource->_exists = false;
                    $size_resource->item_size_id = null;
                    $size_resource->merchant_id = $merchant_id;
                    if ($size_resource->save()) {
                        $is++;
                    }
                }

                $menu_modifier_sizes_zero = CompleteMenu::getAllModifierItemSizesAsResoures($menu_id,0);
                $modiifer_size_count = sizeof($menu_modifier_sizes_zero);
                $ms = 0;
                foreach ($menu_modifier_sizes_zero as $size_resource) {
                    $size_resource->_exists = false;
                    $size_resource->modifier_size_id = null;
                    $size_resource->merchant_id = $merchant_id;
                    if ($size_resource->save()) {
                        $ms++;
                    }
                }

                $item_modifier_group_maps_zero = CompleteMenu::staticGetAllItemModifierGroupMapsAsResources($menu_id,0);
                $imgm_count = sizeof($item_modifier_group_maps_zero);
                $imgms = 0;
                foreach ($item_modifier_group_maps_zero as $map_resource) {
                    $map_resource->_exists = false;
                    $map_resource->map_id = null;
                    $map_resource->merchant_id = $merchant_id;
                    if ($map_resource->save()) {
                        $imgms++;
                    }
                }

                if ($item_size_count == $is && $modiifer_size_count == $ms) {
                    return Resource::dummyfactory(array("result"=>"Success"));
                } else {
                    return Resource::dummyfactory(array("result"=>"Failure"));
                }
            }
        }
    }


    function getMerchantPriceListForEdit($menu_id,$merchant_id)
    {
        $complete_menu = new CompleteMenu($menu_id);
        //$price_list = $complete_menu->getAllMenuItemSizeMapResources($menu_id,'N',$merchant_id);

        $item_size_data['merchant_id'] = $merchant_id;
        $item_size_options[TONIC_FIND_STATIC_FIELD] = ' Menu_Type.menu_type_name, Item.item_name, Item.description, Sizes.size_name, Menu_Type.priority as mt_priority ';
        $item_size_options[TONIC_FIND_BY_METADATA] = $item_size_data;
        $item_size_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Item.item_id = Item_Size_Map.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id JOIN Sizes ON Item_Size_Map.size_id = Sizes.size_id ";
        $item_size_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
        $item_size_options[TONIC_SORT_BY_METADATA] = ' mt_priority DESC, Item.item_name ';

        $item_size_adapter = new ItemSizeAdapter(getM());
        $item_resources = Resource::findAll($item_size_adapter, null, $item_size_options);
        $result_set = [];
        foreach ($item_resources as $price_resource) {
            $result_set['menu_types'][$price_resource->menu_type_name][] = $price_resource->getDataFieldsReally();
        }

        $modifier_item_size_data['merchant_id'] = $merchant_id;
        $modifier_item_size_options[TONIC_FIND_STATIC_FIELD] = ' Modifier_Group.modifier_group_name, Modifier_Item.modifier_item_name, Sizes.size_name, Modifier_Group.priority as mg_priority ';
        $modifier_item_size_options[TONIC_FIND_BY_METADATA] = $modifier_item_size_data;
        $modifier_item_size_options[TONIC_JOIN_STATEMENT] = " JOIN Modifier_Item ON Modifier_Item.modifier_item_id = Modifier_Size_Map.modifier_item_id JOIN Modifier_Group ON Modifier_Item.modifier_group_id = Modifier_Group.modifier_group_id JOIN Sizes ON Modifier_Size_Map.size_id = Sizes.size_id ";
        $modifier_item_size_options[TONIC_FIND_BY_STATIC_METADATA] = " Modifier_Group.menu_id = $menu_id AND Modifier_Group.logical_delete = 'N'  ";
        $modifier_item_size_options[TONIC_SORT_BY_METADATA] = ' mg_priority DESC, Modifier_Item.modifier_item_name ASC ';

        $modifier_size_map_adapter = new ModifierSizeMapAdapter(getM());
        $modifier_resources = Resource::findAll($modifier_size_map_adapter, null, $modifier_item_size_options);

        foreach ($modifier_resources as $modifier_price_resource) {
            $result_set['modifier_groups'][$modifier_price_resource->modifier_group_name][] = $modifier_price_resource->getDataFieldsReally();
        }
        $result_set['menu_id'] = $menu_id;
        $sql = "Select a.*,b.name from Merchant_Pos_Maps a JOIN Splickit_Accepted_Pos_Types b ON a.pos_id = b.id WHERE a.merchant_id = $merchant_id";
        $options[TONIC_FIND_BY_SQL] = $sql;
        if ($resource = Resource::find(new MySQLAdapter(getM()),null,$options)) {
            $merchant_resource = Resource::find(new MerchantAdapter(getM()),"$merchant_id");
            $pos_name = strtolower(str_ireplace(' ','',$resource->name));
            if ($pos_name == 'brink') {
                if ($merchant_resource->brand_id == 152) {
                    $pos_name = 'brinkmoose';
                } else if ($merchant_resource->brand_id == 282) {
                    $pos_name = 'brinkpitapit';
                }
                $import_url = "/app2/portal/import/$pos_name/" . $merchant_resource->alphanumeric_id;
            } else if ($pos_name == 'emagine') {
                $import_url = null;
            } else {
                $import_url = "/app2/portal/import/$pos_name/".$merchant_resource->alphanumeric_id;
            }
            $result_set['import_url'] = $import_url;
            //get last import time if it exists
            $sql = "SELECT * FROM import_audit WHERE merchant_id = $merchant_id order by 1 desc limit 1";
            $iaa = new ImportAuditAdapter(getM());
            $ia_options[TONIC_FIND_BY_SQL] = $sql;
            if ($resource = Resource::find($iaa,null,$options)) {
                $result_set['last_import_timestamp'] = $resource->created;
            } else {
                $result_set['last_import_timestamp'] = '';
            }

        }
        return $result_set;
    }

    function getAllowedModifierGroupsForItemIdAndMerchantId($item_id,$merchant_id)
    {
        $item_modifier_group_map_adapter = new ItemModifierGroupMapAdapter(getM());
        $options[TONIC_FIND_BY_METADATA]['item_id'] = $item_id;
        $options[TONIC_FIND_BY_METADATA]['merchant_id'] = intval($merchant_id);
        $allowed_modifier_groups = $item_modifier_group_map_adapter->select('',$options);

        $allowed_modifier_groups_array = array();
        foreach($allowed_modifier_groups as &$allowed_modifier_group) {
            // get the modifier items that are part of this modifier group so they can be added to the menu item
            $modifier_item_adapter = new ModifierItemAdapter(getM());
            $options_mod_item[TONIC_FIND_BY_METADATA]['modifier_group_id'] = $allowed_modifier_group['modifier_group_id'];
            if ($modifier_items = $modifier_item_adapter->select('', $options_mod_item)) {
                $allowed_modifier_group['modifier_items'] = $modifier_items;
            }
            $allowed_modifier_groups_array[$allowed_modifier_group['modifier_group_id']]=$allowed_modifier_group;
        }
        return $allowed_modifier_groups_array;
    }

    function getSizePricesForItemAndMerchantId($item_id,$merchant_id)
    {
        $item_size_adapter = new ItemSizeAdapter(getM());
        $options[TONIC_FIND_BY_METADATA]['item_id'] = $item_id;
        $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
        $size_prices = $item_size_adapter->select('',$options);
        // will need to do a for each here to set the records equal to the size id?  in order to make them accessable on the the html page. but we dont have an html page anymore so do we need this?
        $size_price_array = array();
        foreach($size_prices as $size_price) {
            $size_price_array[$size_price['size_id']]=$size_price;
        }
        return $size_price_array;
    }

    function getAllSizesForMenu($menu_id)
    {
        $size_adapter = new SizeAdapter(getM());
        $sql = "SELECT b.menu_type_name, a.* FROM Sizes a JOIN Menu_Type b ON a.menu_type_id = b.menu_type_id WHERE b.menu_id = $menu_id";
        $options[TONIC_FIND_BY_SQL] = $sql;
        if ($all_sizes = $size_adapter->select('',$options)) {
            return $all_sizes;
        } else {
            return array();
        }

    }

    function processEditModifierItem()
    {
        $adapter = new EditModifierItemAdapter(getM());
        $adapter->setMenuResourceByMenuId($this->getMenuID());
        $adapter->merchant_id = $this->request->data['merchant_id'];
        if (preg_match("%/modifier_items/([0-9]{4,15})%", $this->request->url, $matches)) {
            $resource = Resource::find($adapter, $matches[1]);
            if ($this->isThisRequestMethodADelete()) {
                return $this->doLogicalDeletefAndFormatReturn($resource);
            }
        }
        $modifier_group_id = $this->getModifierGroupId();
        if ($modifier_group_id < 1) {
            return createErrorResourceWithHttpCode("ERROR. no modifier_group_id submitted.",422,0);
        }

        $all_sizes = $this->getAllSizesForMenu($this->getMenuID());
        if ($resource) {
            $resource->set('merchant_id',$this->request->data['merchant_id']);
            myerror_log("DONE WITH THE LOAD");
            if ($this->isThisRequestMethodAPost()) {
                if ($resource->saveResourceFromData($this->request->data)) {
                    $resource = $resource->getRefreshedResource();
                    $resource->set('merchant_id', $this->getMerchantId());
                    $this->updateMenuStatus();
                } else {
                    myerror_log("could not update the resource: " . $resource->_adapter->getLastErrorText());
                    return createErrorResourceWithHttpCode("COULD NOT update the item: " . $resource->_adapter->getLastError(), 422, 0);
                }
            }
        } else if ($this->hasRequestForDestination('new')) {
            $resource = new Resource($adapter, null);
            $resource->menu_id = $this->getMenuID();
            $resource->modifier_group_id = $modifier_group_id;
            $resource->merchant_id = 0;
        } else {
            if ($this->isThisRequestMethodAPost()) {
                // CREATE NEW MODIFIER ITEM!
                $resource = new Resource($adapter,$this->request->data);
                $resource->set('number_of_sizes',sizeof($all_sizes));
                $resource->set('modifier_group_id',$modifier_group_id);
                if ($resource->save()) {
                    $this->updateMenuStatus();
                    $resource = $resource->refreshResource();
                    $resource->menu_id = $this->getMenuID();
                    $resource->menu_type_id = $this->request->data['menu_type_id'];
                    $resource->merchant_id = 0;
                } else {
                    myerror_log("could not create the resource: ".$resource->_adapter->getLastErrorText());
                    return createErrorResourceWithHttpCode("COULD NOT create the modifier item: ".$resource->_adapter->getLastError(),422,0);
                }
            }
        }
        if ($resource->_exists) {
            $modifier_item_id = $resource->modifier_item_id;
            $modifier_size_map_adapter = new ModifierSizeMapAdapter(getM());
            $options3[TONIC_FIND_BY_METADATA]['modifier_item_id'] = $modifier_item_id;
            $options3[TONIC_FIND_BY_METADATA]['merchant_id'] = $this->getMerchantId();
            $mod_prices = $modifier_size_map_adapter->select('',$options3);
            $mod_size_price_array = array();
            foreach ($mod_prices as $mod_price) {
                $mod_size_price_array[$mod_price['size_id']] = $mod_price;
            }
            $resource->set('mod_size_prices',$mod_size_price_array);
        } else {
            $resource->set('mod_size_prices',[]);
        }
        $resource->set('all_sizes',$all_sizes);
        $resource->set('number_of_sizes',sizeof($all_sizes));
        return $resource;
    }

    function updateItemPriceRecord($data)
    {
        if ($item_size_resource = Resource::find(new ItemSizeAdapter(getM()),$data['item_size_id'])) {
            if (isset($data['price'])) {
                $item_size_resource->price = $data['price'];
            }
            if (isset($data['active'])) {
                $item_size_resource->active = $data['active'];
            }
            if (isset($data['priority'])) {
                $item_size_resource->priority = $data['priority'];
            }
            if (isset($data['included_merchant_menu_types'])) {
                $item_size_resource->included_merchant_menu_types = $data['included_merchant_menu_types'];
            }
            if (isset($data['external_id'])) {
                $item_size_resource->external_id = $data['external_id'];
            }
            if ($item_size_resource->save()) {
                return Resource::dummyfactory(array("result"=>"Success"));
            } else {
                return Resource::dummyfactory(array("result"=>"Failure"));
            }
        } else {
            return createErrorResourceWithHttpCode("Unable to locate price record for updating",422,0,null);
        }
    }

    function updateModifierItemPriceRecord($data)
    {
        if ($modifier_size_resource = Resource::find(new ModifierSizeMapAdapter(getM()),$data['modifier_size_id'])) {
            if (isset($data['modifier_price'])) {
                $modifier_size_resource->modifier_price = $data['modifier_price'];
            }
            if (isset($data['active'])) {
                $modifier_size_resource->active = $data['active'];
            }
            if (isset($data['priority'])) {
                $modifier_size_resource->priority = $data['priority'];
            }
            if (isset($data['included_merchant_menu_types'])) {
                $modifier_size_resource->included_merchant_menu_types = $data['included_merchant_menu_types'];
            }
            if ($modifier_size_resource->save()) {
                return Resource::dummyfactory(array("result"=>"Success"));
            } else {
                return Resource::dummyfactory(array("result"=>"Failure"));
            }
        } else {
            return createErrorResourceWithHttpCode("Unable to locate price record for updating",422,0,null);
        }
    }

    function processEditItem()
    {
        $adapter = new EditItemAdapter();
        $adapter->setMenuResourceByMenuId($this->getMenuID());
        $adapter->merchant_id = $this->request->data['merchant_id'];
        if (preg_match("%/menu_items/([0-9]{4,15})%", $this->request->url, $matches)) {
            $resource = Resource::find($adapter, $matches[1]);
            if ($this->isThisRequestMethodADelete()) {
                return $this->doLogicalDeletefAndFormatReturn($resource);
            }
        }
        //get sizes for menu type
        $menu_type_id = $this->getMenuTypeId();
        if ($menu_type_id < 1) {
            return createErrorResourceWithHttpCode("ERROR. no menu type id submitted.",422,0);
        }
        $size_adapter = new SizeAdapter(getM());
        $options2[TONIC_FIND_BY_METADATA]['menu_type_id'] = $menu_type_id;
        $sizes = $size_adapter->select('',$options2);

        //get all the modifiers for this merchant
        $modifier_group_adapter = new ModifierGroupAdapter(getM());
        $options3[TONIC_FIND_BY_METADATA]['menu_id'] = $this->getMenuID();
        $options3[TONIC_SORT_BY_METADATA] = array('priority DESC','modifier_group_name');
        $modifier_groups = $modifier_group_adapter->select('',$options3);
        $modifier_group_name_array = array();
        foreach ($modifier_groups as &$modifier_group) {
            $modifier_group_name_array[$modifier_group['modifier_group_id']] = $modifier_group['modifier_group_name'];
        }

        if ($resource) {
            $resource->set('merchant_id',$this->request->data['merchant_id']);
            myerror_log("DONE WITH THE LOAD");
            if ($this->isThisRequestMethodAPost()) {
                if ($resource->saveResourceFromData($this->request->data)) {
                    $resource = $resource->getRefreshedResource();
                    $resource->set('merchant_id',$this->request->data['merchant_id']);
                    $this->updateMenuStatus();
                } else {
                    myerror_log("could not update the resource: ".$resource->_adapter->getLastErrorText());
                    return createErrorResourceWithHttpCode("COULD NOT update the item: ".$resource->_adapter->getLastError(),422,0);
                }
            }
            //now get any existing price records
            $item_id = $resource->item_id;
            $size_price_array = $this->getSizePricesForItemAndMerchantId($item_id,intval($this->request->data['merchant_id']));

            //get the groups allowed (if any) for this menu item
            $allowed_modifier_groups_array = $this->getAllowedModifierGroupsForItemIdAndMerchantId($item_id,intval($this->request->data['merchant_id']));

            $resource->set('allowed_modifier_groups',$allowed_modifier_groups_array);
            //$resource->set('number_of_allowed_modifier_groups',sizeof($allowed_modifier_groups));

            //get the comes with modifier_items for this menu item
            $item_modifier_item_map_adapter = new ItemModifierItemMapAdapter(getM());
            $options6[TONIC_FIND_BY_METADATA]['item_id'] = $item_id;
            $comes_with_modifier_items = $item_modifier_item_map_adapter->select('',$options6);
            $temp_array = array();
            foreach ($comes_with_modifier_items as $comes_with_modifier_item) {
                $temp_array[$comes_with_modifier_item['modifier_item_id']] = $comes_with_modifier_item;
            }
            $resource->set('comes_with_modifier_items',$temp_array);

        } else if ($this->hasRequestForDestination('new')) {
            $resource = new Resource($adapter,null);
            $resource->menu_id = $this->getMenuID();
            $resource->menu_type_id = $menu_type_id;
            $resource->merchant_id = 0;
            $size_price_array = array();
            foreach ($sizes as $size) {
                $size_price_array[$size['size_id']]=array("item_size_id"=>null,"merchant_id"=>0,"external_id"=>null,"item_id"=>null,"size_id"=>$size['size_id'],"price"=>'0.00',"active"=>'N',"priority"=>$size['priority']);
            }
        } else {
            if ($this->isThisRequestMethodAPost()) {
                // CREATE NEW ITEM!
                $resource = new Resource($adapter,$this->request->data);
                $resource->menu_id = $this->getMenuID();
                $resource->menu_type_id = $this->getMenuTypeId();
                $resource->set('number_of_sizes',sizeof($sizes));
                $resource->set('number_of_groups',sizeof($modifier_groups));
                if ($resource->save()) {
                    $this->updateMenuStatus();
                    $resource = $resource->refreshResource();
                    $resource->menu_id = $this->getMenuID();
                    $resource->menu_type_id = $menu_type_id;
                    $resource->merchant_id = 0;
                    $size_price_array = $this->getSizePricesForItemAndMerchantId($resource->item_id,0);
                    // now get allowed list if there were any
                    $allowed_modifier_groups_array = $this->getAllowedModifierGroupsForItemIdAndMerchantId($resource->item_id,0);
                    $resource->set('allowed_modifier_groups',$allowed_modifier_groups_array);
                } else {
                    myerror_log("could not update the resource: ".$resource->_adapter->getLastErrorText());
                    return createErrorResourceWithHttpCode("COULD NOT update the item: ".$resource->_adapter->getLastError(),422,0);
                }
            }
        }
        $resource->set('sizes',$sizes);
        $resource->set('number_of_sizes',sizeof($sizes));

        $resource->set('size_prices',$size_price_array);
        if (sizeof($size_price_array) == 0) {
            $resource->set("do_not_show_update_child_records","true");
        }

        $resource->set('modifier_group_name_array',$modifier_group_name_array);
        $resource->set('modifier_groups',$modifier_groups);
        $resource->set('number_of_groups',sizeof($modifier_groups));


        return $resource;
    }

    function updateMenuStatus()
    {
        $menu_adapter = new MenuAdapter(getM());
        $menu_resource = Resource::find($menu_adapter,''.$this->getMenuID());
        $menu_resource->last_menu_change = time();
        $menu_resource->save();
    }

    /**
     * @param $adapter EditMenuObjectAdapter
     * @param $data Array()
     * @return Resource
     */
    function createMenuChildResource($adapter,$data)
    {
        $data['menu_id'] = $this->getMenuId();
        $resource = Resource::factory($adapter,$data);
        if ($resource->save()) {
            return $resource->refreshResource();
        } else {
            return createErrorResourceWithHttpCode("Unable to create ".get_class($adapter)." object. ".$adapter->getLastErrorText(),500,$adapter->getLastErrorNo());
        }
    }


    function getMenu($menu_id)
	{
		$active = 'Y'; //menu item active
		$menu['modifier_groups'] = $this->getModifierItemsPrices($menu_id,$active);
		$menu['menu_types'] = $this->getMenuTypesItemsPrices($menu_id,$active);
/*		
		$v = var_export($menu, true);
		if ($this->log_level)
			myerror_log($v);
//*/
		return $menu;
	}
	
	function getMenuForEdit()
	{
	    $menu_id = isset($this->menu_record) ? $this->menu_record['menu_id'] : 0;
		if($resource = Resource::find($this->adapter,"$menu_id"))
		{
			$menu_id = $resource->menu_id;
			if ($merchant_id = $this->request->data['merchant_id'])
				;// for this merchant
			else
				$merchant_id = '0';	
				
			$create = false;
			if ($this->request->data['create_daughter_records'] == 'true')
				if ($resource->version > 2.99)
					$create = true;
				else
					die ("cannot create daughter records.  Menu is less than version 3.0");
				
			//now check to make sure this is menu 3.0, if less, auto set merchant_id = 0
			if ($resource->version < 3.0)
				$merchant_id = '0';	
			
			$resource->set('merchant_id',$merchant_id);

			$menu_types = $this->getMenuTypes($menu_id);
			foreach ($menu_types as &$menu_type)
			{
				//myerror_log("got menu type: ".$menu_type['menu_type_name']);
				$sizes = $this->getSizes($menu_type['menu_type_id']);
				$menu_type['sizes'] = $sizes;
				//myerror_log("got menu type sizes");
				
				$items = $this->getMenuItems($menu_type['menu_type_id']);
				$menu_type['menu_items'] = $items;
				//myerror_log("got menu type items");
				
				// now test for the create field.  if so, then create all the daughter records
				if (false && $create)
				{
					$item_size_adapter = new ItemSizeAdapter($this->mimetypes);
					foreach ($menu_type['menu_items'] as $menu_item)
						foreach ($menu_type['sizes'] as $size)
						{
							$item_size_data['item_id'] = $menu_item['item_id'];
							$item_size_data['size_id'] = $size['size_id'];
							$item_size_data['merchant_id'] = $merchant_id;
							$item_size_data['priority'] = $size['priority'];
							$item_size_resource = Resource::factory($item_size_adapter,$item_size_data);
							if ($item_size_resource->save())
								;//all is good. child record is created
							else
								myerror_log("couldn't insert daughter record. ".$item_size_adapter->getLastErrorText());
														
						}
				}
				
			}
			$menu['menu_types'] = $menu_types;
			
			$modifier_groups = $this->getModifierGroups($menu_id);
			foreach ($modifier_groups as &$modifier_group)
			{
				//myerror_log("got modifier group: ".$modifier_group['modifier_group_name']);
				$modifier_items = $this->getModifierItems($modifier_group['modifier_group_id']);
				$modifier_group['modifier_items'] = $modifier_items;
			}
			$menu['modifier_groups'] = $modifier_groups;
			$resource->set('menu',$menu);
			return $resource;
		} else {
			myerror_log("no merchant id found");
			$resource = new Resource();
			$resource->set('error','no merchant id found');
		}
			
	}

	function getModifierGroups($menu_id, $active = 'N')
	{
		$modifier_group_adapter = new ModifierGroupAdapter($this->mimetypes);
		$options[TONIC_FIND_BY_METADATA]['menu_id'] = $menu_id;		
		if ($active == 'Y')
			$options[TONIC_FIND_BY_METADATA]['active'] = 'Y';		
		$options[TONIC_SORT_BY_METADATA] = 'priority DESC';		
		$modifier_groups = $modifier_group_adapter->select('',$options);
		return $modifier_groups;
	}

	function getSizes($menu_type_id, $active = 'N')
	{
			$size_adapter = new SizeAdapter($this->mimetypes);
			$optionsx[TONIC_FIND_BY_METADATA]['menu_type_id'] = $menu_type_id;
			if ($active == 'Y')		
				$optionsx[TONIC_FIND_BY_METADATA]['active'] = 'Y';		
			$optionsx[TONIC_SORT_BY_METADATA] = 'priority DESC';		
			$sizes = $size_adapter->select('',$optionsx);
			/*myerror_log("********************size thing************************");
			foreach ($sizes as $size)
				myerror_log("id: ".$size['size_id']."  name: ".$size['size_name']);
			myerror_log("********************size thing************************");
			//*/
		return $sizes;
	}
		
	function getMenuTypes($menu_id,$active = 'N')
	{
		$menu_type_adapter = new MenuTypeAdapter($this->mimetypes);
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';		
		if ($active == 'Y')
			$options[TONIC_FIND_BY_METADATA]['active'] = 'Y';		

		$options[TONIC_FIND_BY_METADATA]['menu_id'] = $menu_id;		

		if ($active == 'Y')
			$options[TONIC_FIND_BY_METADATA]['active'] = 'Y';		
		$options[TONIC_SORT_BY_METADATA] = array('priority DESC','menu_type_name');		
		$this->menu_types = $menu_type_adapter->select('',$options);
		return $this->menu_types;
	}
		
	function getMenuItems($menu_type, $active = 'N')
	{
		$item_adapter = new ItemAdapter($this->mimetypes);
		$options[TONIC_FIND_BY_METADATA]['menu_type_id'] = $menu_type;		
		if ($active == 'Y')
			$options[TONIC_FIND_BY_METADATA]['active'] = 'Y';		
		$options[TONIC_SORT_BY_METADATA] = array('priority DESC','item_name');		
		$items = $item_adapter->select('',$options);
		return $items;
	}
	
	function getMenuTypesItemsPrices($menu_id,$active = 'N')
	{
		$menu_types = $this->getMenuTypes($menu_id,$active);
		//$behaviors = $this->getBehaviors();
		foreach ($menu_types as &$menu_type)
		{
			//first get sizes
			$sizes = $this->getSizes($menu_type['menu_type_id'],'Y');
			$menu_type['sizes'] = $sizes;
				
			//now get items
			$items = $this->getMenuItems($menu_type['menu_type_id'],'Y');
			$sizeprices = array();
			foreach ($items as &$item)
			{
				if ($item['item_id'] && $item['item_id'] != '')
				{   
					$options2 = null;
					// get modifiers groups associated with this item
					$item_modifier_group_map_adapter = new ItemModifierGroupMapAdapter($this->mimetypes);
					$options2[TONIC_FIND_BY_METADATA]['item_id'] = $item['item_id'];
					$options2[TONIC_SORT_BY_METADATA] = array('priority DESC','display_name');		
					$groups = $item_modifier_group_map_adapter->select('',$options2);
					if ($groups)
					{
						foreach ($groups as &$group)
						{
							//unset($group['map_id']);
							unset($group['item_id']);
							// get behavior key word for app?  seems odd to me.
						//	$group['behavior_name'] = $behaviors[$group['behavior_id']];
						//	if ($group['display_name'] == null)
						//		$group['display_name'] = $this->modifier_group_names[$group['modifier_group_id']];
						}
						$item['allowed_modifier_groups'] = $groups;
					}
					
					// get list of modifier items that come on the menu_item
					$item_modifier_item_map_adapter = new ItemModifierItemMapAdapter($this->mimetypes);
					$options3[TONIC_FIND_BY_METADATA]['item_id'] = $item['item_id'];
					$comes_with_items = $item_modifier_item_map_adapter->select('',$options3);
					if ($comes_with_items)
					{
						foreach ($comes_with_items as &$comes_with_item)
						{
							unset($comes_with_item['map_id']);
							unset($comes_with_item['item_id']);
						}
						$item['comes_with_modifier_items'] = $comes_with_items;
					}
					
					$item_size_adapter = new ItemSizeAdapter($this->mimetypes);
					$options2[TONIC_FIND_BY_METADATA]['active'] = 'Y';		
					$options2[TONIC_SORT_BY_METADATA] = 'priority DESC';		
					$sizeprices = $item_size_adapter->select('',$options2);
					if (sizeof($sizeprices) > 0)
					{
						foreach ($sizeprices as &$sizeprice)
						{
							unset($sizeprice['url']);
							foreach ($sizes as $size)
							{
								if ($sizeprice['size_id'] == $size['size_id'])
									$sizeprice['size_name'] = $size['size_name'];
							}
						}	
						$item['size_prices'] = $sizeprices;
					} else
						unset($items[$item['item_id']]);
				} else {
					myerror_log('serious error.  no item id!');
				}
			}		
			$menu_type['menu_items'] = $items;
		}
/*		
		$v = var_export($menu_types, true);
//		if (($this->log_level > 2))
			myerror_log($v);
//*/
		return $menu_types;
	}
	
	function getModifierItems($modifier_group_id,$active = 'N')
	{
		$modifier_item_adapter = new ModifierItemAdapter($this->mimetypes);
		$options[TONIC_FIND_BY_METADATA]['modifier_group_id'] = $modifier_group_id;		
		$options[TONIC_SORT_BY_METADATA] = 'priority DESC, modifier_item_name';		
		$modifier_items = $modifier_item_adapter->select('',$options);
		return $modifier_items;
	}
	
	function getModifierItemsPrices($menu_id,$active='N')
	{

		$themods = array();
		$modifier_groups = $this->getModifierGroups($menu_id,'Y');				
		foreach ($modifier_groups as &$modifier_group)
		{
			$this->modifier_group_names[$modifier_group['modifier_group_id']] = $modifier_group['modifier_group_name'];
			if ($this->log_level > 2)
				myerror_log("looping with modifier: ".$modifier_group['modifier_name']);
			$modifier_items = $this->getModifierItems($modifier_group['modifier_group_id'],'Y');				
			$mod_items = array();
			foreach ($modifier_items as &$modifier_item)
			{
				if ($this->log_level > 2)
					myerror_log("modifier item: ".$modifier_item['modifier_item_name']);
				$options = null;
				$mod_sizeprices = array();

				$modifier_size_map_adapter = new ModifierSizeMapAdapter($this->mimetypes);
				$options[TONIC_FIND_BY_METADATA]['modifier_item_id'] = $modifier_item['modifier_item_id'];		
				$options[TONIC_FIND_BY_METADATA]['active'] = 'Y';		
				$options[TONIC_SORT_BY_METADATA] = 'priority DESC';		
				$modifier_prices = $modifier_size_map_adapter->select('',$options);

				foreach ($modifier_prices as &$modifier_price)
				{
					if ($this->log_level > 2)
						myerror_log("modifier item price: ".$modifier_price['modifier_price']."  active: ".$modifier_price['active']);	
					if ($modifier_price['active']=='Y')
					{
						// had to reduce to precision of 2 becuase 3 was screwing up phone.  3 was needed for stupid lennys combo price splits.
						if ($modifier_price['modifier_price'] == null)
							$modifier_price['modifier_price'] = 0.00;
						else	
							$modifier_price['modifier_price'] = sprintf("%01.2f", $modifier_price['modifier_price']);
						//myerror_log("mod_price: ".$modifier_price['modifier_price']);
						$mod_sizeprices[]=$modifier_price;  //$mod_sizeprices[$modifier_price['modifier_size_id']]=$modifier_price;
					}	
				}
				if (sizeof($mod_sizeprices)>0)
				{
					$modifier_item['modifier_size_maps'] = $mod_sizeprices;
					$mod_items[] = $modifier_item;//$mod_items[$modifier_item['modifier_item_id']] = $modifier_item;
				}					
			}
			if (sizeof($mod_items)>0)
			{
				$modifier_group['modifier_items'] = $mod_items;
				$themods[] =$modifier_group;//$themods[$modifier_group['modifier_group_id']] =$modifier_group;
			}
		}
/*		
		$v = var_export($themods, true);
		//if ($this->log_level > 2)
			myerror_log($v);
//*/		
		return $themods;

	}

	/*******************   GETTER AND SETTERS  ********************/

    function setMenuFromId($menu_id)
    {
        if ($menu_record = $this->adapter->getRecordFromPrimaryKey($menu_id)) {
            $this->menu_record = $menu_record;
            return $menu_record;
        }
    }

    function setMenuTypeFromId($menu_type_id)
    {
        if ($menu_type_record = $this->getMenuTypeAdapter()->getRecordFromPrimaryKey($menu_type_id)) {
            $this->menu_type_record = $menu_type_record;
        }
    }

    function setModifierGroupFromId($modifier_group_id)
    {
        if ($modifier_group_record = $this->getModifierGroupAdapter()->getRecordFromPrimaryKey($modifier_group_id)) {
            $this->modifier_group_record = $modifier_group_record;
        }
    }

    /**
     * @return EditModifierGroupAdapter
     */
    function getModifierGroupAdapter()
    {
        if ($this->modifier_group_adapter == null) {
            $this->modifier_group_adapter = new EditModifierGroupAdapter(getM());
        }
        return $this->modifier_group_adapter;
    }

    /**
     * @return EditMenuTypeAdapter
     */
    function getMenuTypeAdapter()
    {
        if ($this->menu_type_adapter == null) {
            $this->menu_type_adapter = new EditMenuTypeAdapter(getM());
        }
        return $this->menu_type_adapter;
    }

    function getMenuID()
    {
        return $this->menu_record['menu_id'];
    }

    function getMerchantId()
    {
        return $this->merchant_id;
    }

    function getMenuTypeId()
    {
        return $this->menu_type_record['menu_type_id'];
    }

    function getModifierGroupId()
    {
        return $this->modifier_group_record['modifier_group_id'];
    }

}