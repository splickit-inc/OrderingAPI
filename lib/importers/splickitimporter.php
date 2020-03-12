<?php
abstract Class SplickitImporter
{
	protected  $val = 'value';
	protected $loaded_modifier_groups = array();
    protected $loaded_modifier_items = array();
    protected $loaded_modifier_size_maps = array();
    var $create_zero_records = false;
    protected $brand_id;
    protected $external_merchant_id;
    protected $brand_regex_for_merchant_external_ids;
    protected $message = "need response message for operation here";
    protected $menu_id;
    protected $stage_import_for_all_merchants_attached_to_brand = false;
    var $number_of_staged_merchants = 0;

    var $imported_prices = array();
    var $merchant_resource;
    var $item_price_records_imported;
    var $number_of_item_price_records_imported;
    var $modifier_price_records_imported;
    var $number_of_modifier_price_records_imported;
    var $running_import_results;
    var $merchant_mixed;


    function __construct($merchant_mixed)
	{
        myerror_log("starting constructor of splcikitimporter");
        myerror_log("the merchant mixed is a: ".get_class($merchant_mixed));
        $this->merchant_mixed = $merchant_mixed;
        if ($this->brand_id == null) {
            $this->setBrandId(getBrandIdFromCurrentContext());
        }
        $this->setBrandRegExForMerchantExternalIds();
        $this->loadMerchantFromMixedParameter($merchant_mixed);
        $this->setMenuId();

    }

    function loadMerchantFromMixedParameter($merchant_mixed)
    {
        if ($merchant_mixed != null) {
            $regex_for_merchant = '%/import/'.strtolower($this->getImporterType()).'/('.$this->brand_regex_for_merchant_external_ids.')%';
            if (is_a($merchant_mixed,'Resource')) {
                myerror_log("about to assign the resource");
                $this->assignMerchantResource($merchant_mixed);
            } else if (preg_match($regex_for_merchant, $merchant_mixed, $matches)) {
                myerror_log("got the match: ".$matches[1]);
                if ($matches[1] == 'all') {
                    myerror_log("bypass load merchant, we are staging them all");
                    $this->stage_import_for_all_merchants_attached_to_brand = true;
                } else {
                    $this->loadMerchantFromExternalIdBrandIdCombination($matches[1]);
                }
            } else {
                myerror_log("looks like we have an extern id already: ".$merchant_mixed);
                $this->loadMerchantFromExternalIdBrandIdCombination($merchant_mixed);
            }
        }
    }

    function importRemoteData($merchant_resource)
    {
        try {
            $this->getRemoteData($merchant_resource);
        } catch (UnsuccessfulImportException $e) {
            $message = $e->getMessage();
            MailIt::sendErrorEmailSupport("ERROR IMPORTING PRICES",$message);
            $this->setResultsMessage();
            return false;
        }

        $this->callCustomMethodsOnData();
        $merchant_id = $merchant_resource->merchant_id;
        $complete_menu = new CompleteMenu($this->menu_id);
        $splickit_price_resources = $complete_menu->getHashOfResourcesAllPricesForMenuWithExternalIdAsIndex($this->menu_id, $merchant_id);
        $this->addToRunningImport("STARTING ITEM PRICE IMPORT \r\n ");
        $this->number_of_item_price_records_imported = $this->savePricesForPriceResources($splickit_price_resources);

        $splickit_modifier_price_resources = $complete_menu->getHashOfResourcesAllModifierPricesForMenuWithExternalIdAsIndex($this->menu_id,$merchant_id);
        $this->addToRunningImport("\r\n STARTING MODIFIER PRICE IMPORT \r\n ");
        $this->number_of_modifier_price_records_imported = $this->savePricesForPriceResources($splickit_modifier_price_resources);

        $this->addToRunningImport("\r\n END OF IMPORT");
        //rebuild IMGM records

        $imgma = new ItemModifierGroupMapAdapter($m);
        $sql = "DELETE FROM Item_Modifier_Group_Map WHERE merchant_id = $merchant_id";
        $imgma->_query($sql);

        $item_modifier_group_data['merchant_id'] = 0;
        $item_modifier_group_map_options[TONIC_FIND_BY_METADATA] = $item_modifier_group_data;
        $item_modifier_group_map_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Item.item_id = Item_Modifier_Group_Map.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
        $item_modifier_group_map_options[TONIC_FIND_BY_STATIC_METADATA] = " Item.logical_delete = 'N' AND Menu_Type.menu_id = ".$this->menu_id." AND Menu_Type.logical_delete = 'N' ";
        $imgm_resources = Resource::findAll($imgma,'',$item_modifier_group_map_options);
        foreach ($imgm_resources as $imgm_resource) {
            $imgm_resource->_exists = false;
            $imgm_resource->map_id = null;
            $imgm_resource->merchant_id = $merchant_id;
            $imgm_resource->modified = time();
            $this->setIMGMPriceOverride($imgm_resource);
            $imgm_resource->save();
            $this->doCustomImgmLogic($imgm_resource);
        }

        $this->setResultsMessage();
        $this->clearCacheForMerchantAndMenu();
        $import_audit_adapter = new ImportAuditAdapter(getM());
        $results_data = [];
        $results_data['stamp'] = getRawStamp();
        $results_data['url'] = $this->merchant_mixed;
        $results_data['merchant_id'] = $this->merchant_resource->merchant_id;
        $results_data['results_report'] = $this->running_import_results;
        $resource = Resource::factory($import_audit_adapter,$results_data);
        $resource->save();
        return true;
    }

    function clearCacheForMerchantAndMenu()
    {
        myerror_log("about to clear the cache for merchant_id: ".$this->merchant_resource->merchant_id);
        $skin_merchant_map_adapter = new SkinMerchantMapAdapter(getM());
        $skin_merchant_map_records = $skin_merchant_map_adapter->getRecords(["merchant_id"=>$this->merchant_resource->merchant_id]);
        $skin_adapter = new SkinAdapter(getM());
        foreach (['Pickup','Delivery'] as $merchant_menu_type) {
            foreach (['1','2'] as $api_version) {
                foreach($skin_merchant_map_records as $skin_merchant_map_record) {
                    $skin = ($skin_adapter->selectLite($skin_merchant_map_record['skin_id']))[0];
                    $menu_caching_string = "menu-" . $this->menu_id . "-Y-" . $this->merchant_resource->merchant_id . "-V" . $api_version . "-" . $merchant_menu_type . "-" . str_replace(' ', '', $skin['skin_name']);
                    myerror_log("about to delete cache for $menu_caching_string");
                    SplickitCache::deleteCacheFromKey($menu_caching_string);
                }
            }
        }
    }

    function setResultsMessage()
    {
        $this->message = "There were ".$this->getNumberOfItemPricesImported()." item prices imported, and ".$this->getNumberOfModifierPricesImported()." modifier prices imported.";
    }

    function getNumberOfItemPricesImported()
    {
        return $this->number_of_item_price_records_imported;
    }

    function getNumberOfModifierPricesImported()
    {
        return $this->number_of_modifier_price_records_imported;
    }

    function setIMGMPriceOverride(&$imgm_resource)
    {
        return true;
    }

    function doCustomImgmLogic($imgm_resource)
    {
        return true;
    }

    /* 
     * @description method used for making custom changes or price calculations from the imported data. will need to be overridden if used
     */
    function callCustomMethodsOnData()
    {
        return true;
    }

    /**
     * @param $merchant_external_id can also be merchant alphanumeric_id
     * @throws Exception
     * @throws NoBrandSetForImportException
     * @throws NoMatchingMerchantException
     * @throws NoMerchantIdSetForImportException
     */
    function loadMerchantFromExternalIdBrandIdCombination($merchant_external_id)
    {
        // need something here that set it to load all stores if $merchant_external_id  =  'allstores'
        if ($this->brand_id < 101) {
            throw new NoBrandSetForImportException();
        }
        if ($merchant_external_id == null || $merchant_external_id == '') {
            throw new NoMerchantIdSetForImportException();
        }
        $brand_id = $this->brand_id;
        $sql = "SELECT * FROM Merchant WHERE `brand_id` = $brand_id AND (`merchant_external_id` = '$merchant_external_id' OR `alphanumeric_id` = '$merchant_external_id')";
        $merchant_adapter = new MerchantAdapter(getM());
        $options[TONIC_FIND_BY_SQL] = $sql;
        //$options[TONIC_FIND_BY_METADATA]['merchant_external_id'] = $merchant_external_id;
        //$options[TONIC_FIND_BY_METADATA]['brand_id'] = $this->brand_id;
        if ($merchant_resource = Resource::find($merchant_adapter,null,$options)) {
            $this->assignMerchantResource($merchant_resource);
        } else {
            throw new NoMatchingMerchantException($merchant_external_id);
        }

    }

    function assignMerchantResource($merchant_resource)
    {
        if (is_a($merchant_resource,'Resource')) {
            $this->merchant_resource = $merchant_resource;
            $this->external_merchant_id = $this->merchant_resource->merchant_external_id;
        } else {
            throw new Exception("Attempt to set merchant resource with NON resource object");
        }
    }

    function shouldCreateZeroRecords()
    {
        return $this->create_zero_records;
    }

    /**
     * @desc gets or creates a menu object resource
     * @param mixed $data
     * @return Resource
     */
    function getOrCreateMenuObjectResource($data,$adapter)
    {
        $options[TONIC_FIND_BY_METADATA] = $data;
        if ($resource = Resource::findOrCreateIfNotExists($adapter, $url, $options)) {
            error_log("we have the parent menu object resource");
            return $resource;
        } else {
            throw new Exception("Serious error building or creating menu object resource: ".$adapter->getLastErrorText());
        }
    }

    function savePricesForPriceResources(&$splickit_price_resources)
    {
        $total_price_records_imported = 0;
        foreach ($splickit_price_resources as $splickit_price_resource) {
            if ($this->processPriceResource($splickit_price_resource)) {
                $total_price_records_imported++;
            }
        }
        return $total_price_records_imported;
    }

    /**
     * @desc gets or creates a menu price object resource
     * @param mixed $data
     * @return Resource
     */
    function getOrCreateMenuPriceObjectResource($data,$adapter)
    {
        if ($this->shouldCreateZeroRecords()) {
            $options[TONIC_FIND_BY_METADATA] = $data;
            if ($resource = Resource::find($adapter, $url, $options)) {
                error_log("we have the parent menu object resource");
                return $resource;
            } else {
                $merchant_id = $data['merchant_id'];
                unset($data['merchant_id']);
                $default_price_record_resource = $this->getOrCreateMenuObjectResource($data,$adapter);
                $default_price_record_resource->_exists = false;
                $primary_field_name = $adapter->primaryKeys[0];
                unset($default_price_record_resource->$primary_field_name);
                $default_price_record_resource->merchant_id = $merchant_id;
                $default_price_record_resource->save();
                return $default_price_record_resource;
            }
        } else {
            return $this->getOrCreateMenuObjectResource($data,$adapter);
        }
    }

    function processPriceResource(&$splickit_price_resource)
    {
        $merchant_id = $this->merchant_resource->merchant_id;
        $imported_price_hash_by_plu = $this->imported_prices;
        $raw_plu_on_splickt_price_record = $splickit_price_resource->external_id;
        $p = explode(":",$raw_plu_on_splickt_price_record);
        $plu = $p[0];
        if ($plu == null || trim($plu) == '') {
            $json_of_thing = json_encode($splickit_price_resource->getDataFieldsReally());
            myerror_log("BLANK PLU SKIP UPDATE FOR THIS ITEM!  price record: $json_of_thing",5);
            $this->addToRunningImport("BLANK PLU SKIP UPDATE FOR THIS ITEM!  price record: $json_of_thing");
            return false;
        }
        myerror_log("about to get price for: $plu",5);
        if (isset($imported_price_hash_by_plu[$plu])) {
            myerror_log("WE DO HAVE A PRICE: ".json_encode($imported_price_hash_by_plu[$plu]),5);
            $this->addToRunningImport("WE DO HAVE A PRICE: ".json_encode($imported_price_hash_by_plu[$plu]));
            $starting_active = $splickit_price_resource->active;
            $starting_price = isset($splickit_price_resource->price) ? $splickit_price_resource->price : $splickit_price_resource->modifier_price;
            $splickit_price_resource->active = 'Y';
            $splickit_price_resource->price = $this->getObjectPriceFromItemData($imported_price_hash_by_plu[$plu]);
            $splickit_price_resource->modifier_price = $this->getObjectPriceFromItemData($imported_price_hash_by_plu[$plu]);
            $this->doCustomPriceManipulationForPriceResource($splickit_price_resource);
            $splickit_price_resource->tax_group = $this->isImportedItemTaxable($imported_price_hash_by_plu[$plu]) ? 1 : 0;
            $price_recorded = true;
        } else {
            // there was no price returned so create an innactive price record
            myerror_log("WE DID NOT IMPORT A PRICE RECORD FOR PLU: $plu.  SET record to INACTIVE!",5);
            $id = isset($splickit_price_resource->item_id) ? $splickit_price_resource->item_id : $splickit_price_resource->modifier_item_id;
            $this->addToRunningImport("WE DID NOT IMPORT A PRICE RECORD FOR ITEM_ID: $id   SIZE_ID: ".$splickit_price_resource->size_id."   PLU: $plu SET record to INACTIVE!");
            $splickit_price_resource->active = 'N';
            $price_recorded = false;
        }
        $splickit_price_resource->merchant_id = $merchant_id; // in case this is a default record
        if ($p[1] == 'zero_price') {
            $splickit_price_resource->modifier_price = 0.00;
            $splickit_price_resource->price = 0.00;
        }
        if  ($this->saveThePriceResource($splickit_price_resource)) {
            return $price_recorded;
        }
    }

    protected function saveThePriceResource($splickit_price_resource) {
        return $splickit_price_resource->save();
    }
    
    function doCustomPriceManipulationForPriceResource(&$splickit_price_resource)
    {
        return true;
    }

    function isImportedItemTaxable($imported_price_record)
    {
        return true;
    }
	
	/**
	 * 
	 * @desc will get menu object 
	 * @param array $data
	 * @param MySQLAdapter $adapter
	 * @return Resource
	 */
	function getMenuObjectResource($data,$adapter)
	{
		$options[TONIC_FIND_BY_METADATA] = $data;
		return Resource::find($adapter, $url, $options);
	}

	/**
	 * 
	 * @desc used to insert records for the various menu objects.  miGHT NOT BE USED???
	 * @param MySQLAdapter $adapter
	 * @param mixed $data
	 * @return Resource
	 */
	function insertIt($adapter,$data)
	{
		$resource = new Resource($adapter,$data);
		$adapter->insert($resource);
		if ($insert_id = $adapter->_insertId()) {
			return $insert_id;
		} else {
			$no_errors = false;
			$adapter->_query("ROLLBACK");
			myerror_log("WE HAVE ERRORS!  cannot import menu: ".$adapter->getLastErrorText());
			//die("WE HAVE ERRORS!");
		}
	}

    function getItemModifierGroupMap($item_id,$modifier_group_id,$merchant_id = 0)
    {
        $data['item_id'] = $item_id;
        $data['modifier_group_id'] = $modifier_group_id;
        $data['merchant_id'] = $merchant_id;
        return $this->getOrCreateMenuPriceObjectResource($data, new ItemModifierGroupMapAdapter($mimetypes));
    }

    function updateItemModifierGroupMapResource(&$item_modifier_group_map_resource,$modifier_group_data,$priority)
    {
        $item_modifier_group_map_resource->display_name = $modifier_group_data['name'];
        $min = $this->getModifierGroupMinForThisIntegration($modifier_group_data);
        $max = $this->getModifierGroupMaxForThisIntegration($modifier_group_data);
        $item_modifier_group_map_resource->min = $min;
        $item_modifier_group_map_resource->max = $max;
        $item_modifier_group_map_resource->priority = $priority;
        $item_modifier_group_map_resource->save();
    }

    function updateModifierSizeResource(&$modifier_size_resource,$modifier_item_data)
    {
        $modifier_size_resource->price = $modifier_item_data['price'];
        $modifier_size_resource->active = $this->getActiveFlagSetting($modifier_item_data);
        $modifier_size_resource->save();
    }

    function getModifierSizeResource($modifier_item_id,$size_id,$external_id,$merchant_id = 0)
    {
        if ($this->loaded_modifier_size_maps["$size_id-$external_id"]) {
            unset($this->loaded_modifier_size_maps["$size_id-$external_id"]->insert_id);
            $modifier_size_map_resource = $this->loaded_modifier_size_maps["$size_id-$external_id"];
        } else {
            $data['modifier_item_id'] = $modifier_item_id;
            $data['size_id'] = $size_id;
            $data['external_id'] = $external_id;
            $data['merchant_id'] = $merchant_id;
            $modifier_size_map_resource = $this->getOrCreateMenuPriceObjectResource($data, new ModifierSizeMapAdapter($mimetypes));
            $this->loaded_modifier_size_maps["$size_id-$external_id"] = $modifier_size_map_resource;
        }
        return $modifier_size_map_resource;
    }

    function updateModifierItemResource(&$modifier_item_resource,$modifier_item_data)
    {
        $modifier_item_resource->modifier_item_name = $modifier_item_data['name'];
        $modifier_item_resource->modifier_print_name = $modifier_item_data['name'];
        $modifier_item_resource->modifier_item_max = 1;
        $modifier_item_resource->save();
    }

    function getModifierItemResource($modifier_group_id,$external_modifier_item_id)
    {
        if ($this->loaded_modifier_items["$modifier_group_id-$external_modifier_item_id"]) {
            unset($this->loaded_modifier_items["$modifier_group_id-$external_modifier_item_id"]->insert_id);
            $modifier_item_resource = $this->loaded_modifier_items["$modifier_group_id-$external_modifier_item_id"];
        } else {
            $data['modifier_group_id'] = $modifier_group_id;
            $data['external_modifier_item_id'] = $external_modifier_item_id;
            $modifier_item_resource = $this->getOrCreateMenuObjectResource($data, new ModifierItemAdapter($mimetypes));
            $this->loaded_modifier_items["$modifier_group_id-$external_modifier_item_id"] = $modifier_item_resource;
        }
        return $modifier_item_resource;
    }

    function updateModifierGroupResource(&$modifier_group_resource,$modifier_group_data)
    {
        $modifier_group_resource->modifier_group_name = $modifier_group_data['name'];
        $modifier_group_resource->modifier_description = $this->getItemDescriptionFromItemData($modifier_group_data);
        $modifier_group_resource->active = $this->getActiveFlagSetting($modifier_group_data);
        $modifier_group_resource->save();
    }

    function getModifierGroupResource($menu_id,$external_modifier_group_id)
    {
        if ($this->loaded_modifier_groups["$menu_id-$external_modifier_group_id"]) {
            unset($this->loaded_modifier_groups["$menu_id-$external_modifier_group_id"]->insert_id);
            $modifier_group_resource = $this->loaded_modifier_groups["$menu_id-$external_modifier_group_id"];
        } else {
            $data['menu_id'] = $menu_id;
            $data['external_modifier_group_id'] = $external_modifier_group_id;
            $modifier_group_resource = $this->getOrCreateMenuObjectResource($data, new ModifierGroupAdapter(getM()));
            $this->loaded_modifier_groups["$menu_id-$external_modifier_group_id"] = $modifier_group_resource;
        }
        return $modifier_group_resource;
    }

    function getItemSizePriceResource($item_id,$size_id,$external_id,$merchant_id = 0)
    {
        $data['item_id'] = $item_id;
        $data['size_id'] = $size_id;
        $data['external_id'] = $external_id;
        $data['merchant_id'] = $merchant_id;
        return $this->getOrCreateMenuPriceObjectResource($data, new ItemSizeAdapter($mimetypes));
    }

    function updateItemSizePriceResource(&$item_size_resource,$item_data)
    {
        $item_size_resource->price = $item_data['price'];
        $item_size_resource->active = $this->getActiveFlagSetting($item_data);
        $item_size_resource->save();
    }

    function getSizeForMenuTypeId($menu_type_id,$size_name,$external_size_id = null)
    {
        $data['menu_type_id'] = $menu_type_id;
        $data['size_name'] = $size_name;
        if ($external_size_id != null) {
            $data['external_size_id'] = $external_size_id;
        }

        $size_resource = $this->getOrCreateMenuObjectResource($data, new SizeAdapter(getM()));
        if (isset($size_resource->insert_id)) {
            $size_resource->size_print_name = $size_name;
            $size_resource->save();
        }
        return $size_resource;
    }

    function getItemResource($menu_type_id,$external_item_id)
    {
        $data['external_item_id'] = $external_item_id;
        $data['menu_type_id'] = $menu_type_id;
        return $this->getOrCreateMenuObjectResource($data, new ItemAdapter($mimetypes));
    }

    function updateItemResource($item_data,&$item_resource)
    {
        $item_resource->item_name = $item_data['name'];
        $item_resource->active = $this->getActiveFlagSetting($item_data);
        $item_resource->item_print_name = $item_data['name'];
        $item_resource->description = $this->getItemDescriptionFromItemData($item_data);
        $item_resource->save();
    }

    function getMenuTypeResource($menu_id,$external_menu_type_id)
    {
        $data['external_menu_type_id'] = $external_menu_type_id;
        $data['menu_id'] = $menu_id;
        $menu_type_resource = $this->getOrCreateMenuObjectResource($data, new MenuTypeAdapter(getM()));
        return $menu_type_resource;
    }

    function updateMenuTypeResource($menu_type_data,&$menu_type_resource)
    {
        $menu_type_resource->menu_type_name = $menu_type_data['name'];
        $menu_type_resource->cat_id = $this->getMenuTypeCatId($menu_type_data);
        $menu_type_resource->active = $this->getActiveFlagSetting($menu_type_data);
        $menu_type_resource->save();

    }

    function getLoadedMerchantResource()
    {
        return $this->merchant_resource;
    }

    function getImporterType()
    {
        $class_name = get_class($this);
        return str_ireplace('importer','',strtolower($class_name));
    }

    static function staticStageImportForEntireBrandList($url)
    {
        if ($importer = ImporterFactory::getImporterFromUrl($url)) {
            try {
                $importer->stageImportsForEntireBrandList();
                myerror_log("Import has been run. there were ".$importer->number_of_staged_merchants." merchants staged for price import");
                return true;
            } catch (NoBrandSetForImportException $e1) {
                return false;
            } catch (NoMatchingMerchantException $e2) {
                return false;
            }
        } else {
            myerror_log("ERROR!!!   couldnt create importer from URL: ".$url);
            return false;
        }
    }

    function stageImportsForEntireBrandList()
    {
        if ($this->brand_id > 101) {
            $merchant_options[TONIC_FIND_BY_METADATA]['brand_id'] = $this->brand_id;
            $merchant_options[TONIC_FIND_BY_METADATA]['active'] = 'Y';
            if ($merchant_resources = Resource::findAll(new MerchantAdapter(getM()),'',$merchant_options)) {
                $delay = 0;
                foreach ($merchant_resources as $merchant_resource) {
                    $merchant_id = $merchant_resource->merchant_id;
                    try {
                        if ($activity_id = $this->stageImportFromMerchantResource($merchant_resource,$delay)) {
                            myerror_log("we have successfully staged the import for merchant_id: $merchant_id.  Activity_id: $activity_id");
                            $this->number_of_staged_merchants++;
                            $delay = $delay + 10;
                        }
                    } catch (Exception $e) {
                        myerror_log("Couldnt stage merchant_id: ".$merchant_resource->merchant_id.".  ".$e->getMessage());
                    }
                }
            } else {
                myerror_log("there were no merchants matching brand_id: ".$this->brand_id);
                throw new NoMatchingMerchantException();
            }
        } else {
            throw new NoBrandSetForImportException();
        }
    }

    function stageImport()
    {
        return $this->stageImportFromMerchantExternal($this->external_merchant_id);
    }

    function stageImportFromMerchantResource($merchant_resource,$delay)
    {
        return $this->stageImportFromMerchantExternal($merchant_resource->merchant_external_id,$delay);
    }

    function stageImportFromMerchantExternal($merchant_external_id,$time_offset_in_seconds = 0)
    {
        if ($merchant_external_id == null) {
            throw new Exception("NO MERCHANT LOADED!!! cannot stage import");
        }
        return ActivityHistoryAdapter::createActivity('ExecuteObjectFunction', time()+60+$time_offset_in_seconds, "object=".get_class($this).";method=import;thefunctiondatastring=".$merchant_external_id, $activity_text);
    }

    function import($mixed)
    {
        $this->merchant_mixed = "/activity/import/".$this->getImporterName()."/$mixed";
        $this->loadMerchantFromMixedParameter($mixed);
        return $this->importRemoteMerchantMetaDataForLoadedMerchant();
    }

    function importRemoteMerchantMetaDataForLoadedMerchant()
    {
        return $this->importRemoteData($this->merchant_resource);
    }

    function getMessage()
    {
        return $this->message;
    }

    function setBrandId($brand_id)
    {
        $this->brand_id = $brand_id;
    }

    function getStageImportForAllMerchantsAttachedToBrand()
    {
        return $this->stage_import_for_all_merchants_attached_to_brand;
    }

    function getMenuId()
    {
        return $this->menu_id;
    }

    function addToRunningImport($text)
    {
        $this->running_import_results = $this->running_import_results."$text \r\n";
    }
    /*
     * default is to use our own alphanumeric ids
     */
    function setBrandRegExForMerchantExternalIds()
    {
        $this->brand_regex_for_merchant_external_ids = '[0-9a-zA-Z]+';
    }

    function getImporterName()
    {
        $class_name = get_class($this);
        $importer_name = strtolower(str_ireplace('importer','',$class_name));
        return $importer_name;
    }

    /***** abstract functions that need to be overwritten for the specific integration *******/

    abstract function getItemDescriptionFromItemData($item_data);

    abstract function getModifierGroupMinForThisIntegration($modifier_group_data);

    abstract function getModifierGroupMaxForThisIntegration($modifier_group_data);

    abstract function getActiveFlagSetting($data);

    abstract function getMenuTypeCatId($menu_type_data);

    abstract function getObjectPriceFromItemData($item_data);
    

    /* GENERAL ABSTACT METHODS FOR FRAMEWORK */

    abstract function getRemoteData($merchant_resource);

    function setMenuId($menu_id)
    {
        $this->menu_id = $menu_id;
    }


}

class NoMatchingMerchantException extends Exception
{
    public function __construct($merchant_id)
    {
        $brand_id = getBrandIdFromCurrentContext();
        parent::__construct("No matching merchant id for: $merchant_id  with brand: $brand_id", 422);
    }

}

class NoMatchingMerchantMenuException extends Exception
{
    public function __construct($merchant_id)
    {
        $brand_id = getBrandIdFromCurrentContext();
        parent::__construct("No assigned Menu for merchant_id: $merchant_id", 422);
    }

}

class NoBrandSetForImportException extends Exception
{
    public function __construct()
    {
        parent::__construct("No brand set for import. Cannot retrieve merchant", 422);
    }

}

class UnsuccessfulImportException extends Exception
{
    public function __construct($merchant_id) {
        parent::__construct("We could not import the prices for this merchant: $merchant_id", 100);
    }
}

class NoMerchantIdSetForImportException extends Exception
{
    public function __construct()
    {
        parent::__construct("There was no submitted parameter to identify the merchant to import prices for.", 422);
    }

}

?>