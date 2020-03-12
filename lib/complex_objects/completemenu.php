<?php

Class CompleteMenu
{
	protected $available_modifier_groups = array();
	protected $full_item_list = array();
	protected $full_item_sizeprice_list = array();
	protected $full_modifier_group_list = array();
	protected $full_price_list_by_item_size = array();
	protected $full_price_list_by_external_id = array();
	protected $use_default_price_list;
	protected $menu_id;
	protected $menu;
	protected $merchant_resource;
	protected $error_text;
	protected $all_size_prices;
    protected $brand_loyalty_rules;

    private $all_price_overrides_by_size_by_item_modifier_group;
    private $all_sizes_by_menu_type;

    var $active_items = [];


    var $show_catering_items = true;
	
	var $api_version;
	var $merchant_menu_type = 'Pickup'; //default will always be pickup

    var $catering = false;

	const CAL = "Cal"; //used to add 'cal' label to calories data sample to retrieve: '287 - 233 cal.'


	// ****************  static methods  ********************
	static function getCompletePickupMenu($menu_id,$show_active_only = 'Y',$merchant_id = 0, $api_version = 1)
	{
		return CompleteMenu::getCompleteMenu($menu_id,$show_active_only,$merchant_id,$api_version,'Pickup');
	}
	
	static function getCompleteDeliveryMenu($menu_id,$show_active_only = 'Y',$merchant_id = 0, $api_version = 1)
	{
		return CompleteMenu::getCompleteMenu($menu_id,$show_active_only,$merchant_id,$api_version,'Delivery');
	}
	
	static function getCompleteMenu($menu_id,$show_active_only = 'Y',$merchant_id = 0, $api_version = 1,$merchant_menu_type = 'pickup',$catering = false,$show_catering_items = true)
	{
		$merchant_menu_type = ucwords(strtolower($merchant_menu_type));
		$menu_caching_string = "menu-".$menu_id."-".$show_active_only."-".$merchant_id."-V".$api_version."-".$merchant_menu_type."-".str_replace(' ','',getSkinNameForContext());
		myerror_log("Menu Caching String: ".$menu_caching_string);
		// try to get from Cache first.
        $splickit_cache = new SplickitCache();
        if ($menu = $splickit_cache->getCache($menu_caching_string)) {
    		$menu_key = $menu['menu_key'];
    		$current_menu_key = MenuAdapter::getMenuStatus($menu_id);
    		if (isLoggedInUserStoreTesterLevelOrBetter()) {
				myerror_logging(3,"caching_log: user is store tester or better. Do Not Check Cache, create new menu");
    		} else if ($menu_key == $current_menu_key) {
    			myerror_log("caching_log: we are using a cached version of menu: $menu_caching_string");
    			$menu['using_cached_menu'] = 'true';
    			return $menu;
    		} else {
    			myerror_log("caching_log: menu status key is no longer valid so delete the cache and create a new one");
    		}
    	} else {
    		myerror_log("caching_log: cache does not exist for this key so create it: $menu_caching_string");
    	}
    	 
		$complete_menu = new CompleteMenu($menu_id);
		$complete_menu->api_version = $api_version;
		$complete_menu->merchant_menu_type = $merchant_menu_type;
        if ($catering) {
            myerror_log("we are setting the complete menu to CATERING");
            $complete_menu->catering = true;
        } else {
            $complete_menu->show_catering_items = $show_catering_items;
        }
		$menu = $complete_menu->getTheCompleteMenu($show_active_only,$merchant_id);
        $menu['api_version'] = $api_version;
        $menu['using_cached_menu'] = 'false';
        // get number of seconds till 3am mountain time
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone('America/Denver'));
        $current_hour_mountain_time = $date->format("G");
        $date->setTime(3,0,0);
        $time_stamp_of_next_3am = $current_hour_mountain_time < 3 ? $date->getTimestamp() : $date->getTimestamp() + 24*3600;
        $date->setTimestamp($time_stamp_of_next_3am);
        $format = $date->format("Y-m-d H:i:s");
        myerror_log("caching_log: We are setting the merchant menu cache ($menu_caching_string) to expire at $format mountain time");
        $expires_in_seconds = $time_stamp_of_next_3am - time();
		if (isLaptop()) {
			$expires_in_seconds = 60;
		}
		myerror_log("caching_log: we are about to set the menu cache for:  $menu_caching_string, which will expire in: $expires_in_seconds seconds");
        $splickit_cache->setCache($menu_caching_string,$menu,$expires_in_seconds);
		if ($complete_menu->error_text != null)
    	{
			$error_text = $complete_menu->error_text;
			if ($complete_menu->merchant_resource && $complete_menu->merchant_resource->active == 'N') {
				$active_status = 'INNACTIVE MERCHANT ';
			}
			myerror_log("ERROR!  MENU DATA INTEGRITY ERROR  menu_id=$menu_id  merchant_id=$merchant_id! menu data integrity error report.  \r\n".$error_text);
			//MailIt::sendErrorEmailSupport("Menu Integrity Report $active_status menu_id: ".$menu_id, "merchant_id: $merchant_id     ".$error_text);
			$me_resource = MenuIntegrityErrorsAdapter::recordMenuError($menu_id,$merchant_id,$error_text);
			$menu['error_text'] = $error_text;
		}
		$menu['api_version'] = $api_version;
    	return $menu;
	}
	
	static function getMenuStatus($request,$mimetypes)
	{
		$_SERVER['STAMP'] = 'menustatus-'.$_SERVER['STAMP'];
		if ($request->url == "/phone/menustatus/null") {
			$app_version = $_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'];
			$device_type = $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'];
			$external_id_string = $_SERVER['HTTP_X_SPLICKIT_CLIENT_ID'];
			$access_string = "skin: ".$external_id_string."     version: ".$app_version."    device: ".$device_type;
			myerror_log("NULL MENUSTATUS KEY SUBMITTED KILL IT.  $access_string ");
			saveErrorForEndOfDayReport('Null Status Key Submitted', 'this should never happen: '.$access_string);
			return createErrorResourceWithHttpCode("Null Menu Id submitted for status call", 422, 999, $error_data);
		}
			
		if($menu_resource =& $request->load(new MenuAdapter($mimetypes)))
		{
			$menu_status_array = array('menu_key'=>$menu_resource->last_menu_change,'menu_id'=>$menu_resource->menu_id);
			$resource = Resource::dummyFactory($menu_status_array);
			unset($resource->modified);
			unset($resource->created);
			myerror_log("for menu ".$resource->menu_id.", the menustatuskey value is: ".$resource->menu_key);
			return $resource;
		}
		else
		{
				myerror_log("SERIOUS ERROR!  No existing menu for this ID!");
				MailIt::sendErrorEmail('ERROR! No existing menu for this ID! '.$_SERVER['HTTP_HOST'], 'submitted id: '.$request->url);
        return createErrorResourceWithHttpCode("NO EXISTING MENU FOR THIS ID",422,999,$a);
		}
	}
	
	function getLTOs($menu_id,$merchant_id,$use_default_price_list)
	{
		// get all LTO records for this menu and merchant
		$log_level = $_SERVER['log_level'];
		$_SERVER['log_level'] = 10;
		$mcs_adapter = new MenuChangeScheduleAdapter(null);
		$mcs_options[TONIC_FIND_BY_METADATA]['menu_id'] = $menu_id;
		if ($use_default_price_list || $merchant_id == 0)
			$mcs_options[TONIC_FIND_BY_METADATA]['merchant_id'] = 0;
		else
			$mcs_options[TONIC_FIND_BY_METADATA]['merchant_id'] = array("IN"=>array($merchant_id,0));
		$mcs_options[TONIC_FIND_BY_METADATA]['active'] = 'Y';
		$mcs_options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
		
		// time zone should have already been set in teh merchant controller
		$tz = date_default_timezone_get();
		$tz_string_for_merchant = getTheTimeZoneStringFromOffset($this->merchant_resource->time_zone,$this->merchant_resource->state);
		myerror_log("We have the new timezone string for this merchant in get LTO: $tz_string_for_merchant",3);
		date_default_timezone_set($tz_string_for_merchant);
		$local_time = date('H:i');
		myerror_log("the local time for the get LTOs is: ".$local_time);
		$mcs_options[TONIC_FIND_BY_METADATA]['day_of_week'] = date('w')+1;
		$mcs_options[TONIC_FIND_BY_METADATA]['start'] = array('<'=>$local_time);
		$mcs_options[TONIC_FIND_BY_METADATA]['stop'] = array('>'=>$local_time);
		
		if ($ltos = $mcs_adapter->select('',$mcs_options))
		{
			myerror_log("we have some LTOs!",3);
		} else {
		    myerror_log("we DONT have some LTOS",3);
        }
		$_SERVER['log_level'] = $log_level;
		date_default_timezone_set($tz);
		return $ltos;
	}

        function CompleteMenu($menu_id)
	{
		$this->menu_id = $this->getMenuIdForMethodCalls($menu_id);

		$menu_resource = Resource::find(new MenuAdapter($mimetypes),$this->menu_id);
		$menu = $menu_resource->getDataFieldsReally();
		$menu['menu_key'] = $menu_resource->last_menu_change;
		$this->use_default_price_list = false;
		
		if ($menu_resource->version < 3)
		{
			myerror_log("the menu version is less than 3.0");
			$this->use_default_price_list = true;
		}
		$this->menu = $menu;
	}
	
	function getComboPrices($menu_id,$merchant_id,$use_default_price_list,$active_only)
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		if ($use_default_price_list)
			$merchant_id = '0';
		$menu_combo_price_adapter = new MenuComboPriceAdapter($mimetypes);
		$menu_combo_price_data = array();
		if ($active_only == 'Y')
			$menu_combo_price_data['active'] = 'Y';
		$menu_combo_price_options[TONIC_FIND_BY_METADATA] = $menu_combo_price_data;
		$menu_combo_price_options[TONIC_JOIN_STATEMENT] = " JOIN Menu_Combo ON Menu_Combo_Price.combo_id = Menu_Combo.combo_id ";
		$menu_combo_price_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Combo.menu_id = $menu_id AND Menu_Combo_Price.merchant_id = $merchant_id AND Menu_Combo.logical_delete = 'N' ";
		if ($menu_combo_prices = $menu_combo_price_adapter->select(null,$menu_combo_price_options))
		{
			$better_menu_combo_prices = array();
			foreach ($menu_combo_prices as $menu_combo_price)
			{
				$combo_id = $menu_combo_price['combo_id'];
				$better_menu_combo_prices[$combo_id] = $menu_combo_price;
			}
			return $better_menu_combo_prices;
		}
		else
			return false;
	}
	
	function getComboAssociations($menu_id)
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		$menu_combo_association_adapter = new MenuComboAssociationAdapter($mimetypes);
		$menu_combo_association_data = array();
		$menu_combo_associotion_options[TONIC_FIND_BY_METADATA] = $menu_combo_association_data;
		$menu_combo_associotion_options[TONIC_JOIN_STATEMENT] = " JOIN Menu_Combo ON Menu_Combo.combo_id = Menu_Combo_Association.combo_id ";
		$menu_combo_associotion_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Combo.menu_id = $menu_id AND Menu_Combo.logical_delete = 'N' ";
		if ($menu_combo_associations = $menu_combo_association_adapter->select(null,$menu_combo_associotion_options))
		{
			$better_menu_combo_associations = array();
			foreach ($menu_combo_associations as $menu_combo_association)
			{
				$combo_id = $menu_combo_association['combo_id'];
				$better_menu_combo_associations[$combo_id][] = $menu_combo_association;
			}
			return $better_menu_combo_associations;
		}
		else
			return false;		
	}
	
	function getCombos($menu_id,$show_active_only,$merchant_id,$use_default_price_list)
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		myerror_logging(2, "Starting the get combos code");
		$menu_combo_adapter = new MenuComboAdapter($mimetypes);
		$combo_data['menu_id'] = $menu_id;
		$combo_data['active'] = 'Y';
		$combo_options[TONIC_FIND_BY_METADATA] = $combo_data;
		if ($combos = $menu_combo_adapter->select(null,$combo_options))
		{
			if ($menu_combo_prices = $this->getComboPrices($menu_id, $merchant_id, $use_default_price_list, $active_only))
			{
				if ($menu_combo_associations = $this->getComboAssociations($menu_id))
				{
					myerror_log("WE HAVE COMBOS!");
					foreach ($combos as &$combo)
					{
						$combo_id = $combo['combo_id'];
						$combo['price'] = $menu_combo_prices[$combo_id]['price'];
						$list = array();
						foreach ($menu_combo_associations[$combo_id] as $menu_combo_association)
						{
							if (strtolower($menu_combo_association['kind_of_object']) == 'modifier_item')
							{
								$modifier_item_id = $menu_combo_association['object_id'];
								$list = array($modifier_item_id);
							} else if (strtolower($menu_combo_association['kind_of_object']) == 'modifier_group') {
								// need to get list of modifier items that belong to this group.
								$modifier_item_adapter = new ModifierItemAdapter($mimetypes);
								$modifier_group_id = $menu_combo_association['object_id'];
								$modifier_item_data['modifier_group_id'] = $modifier_group_id;
								$modifier_item_options[TONIC_FIND_BY_METADATA] = $modifier_item_data;
								if ($modifier_items = $modifier_item_adapter->select(null,$modifier_item_options))
								{
									foreach ($modifier_items as $modifier_item)
										$list[] = $modifier_item['modifier_item_id'];
								}

							} else {
								myerror_log("ERROR! UNKNOWN combo.kind_of_object: ".$menu_combo_association['kind_of_object']);
								MailIt::sendErrorEmail("ERROR!  UNKNOWN combo.kind_of_object", "listed kind: ".$menu_combo_association['kind_of_object']);
								return false;	
							}
							$combo['external_ids'][] = $menu_combo_association['external_object_id'];
							$modifier_item_lists[] = $list;
						}
						$combo['modifier_item_lists'] = $modifier_item_lists;
						unset($modifier_item_lists);
					}
					recursiveRemoval($combos, 'created');
					recursiveRemoval($combos, 'modified');
					recursiveRemoval($combos, 'logical_delete');
					
					return $combos;
					
				} else {
					myerror_log("***** active combos and prices but no menu combo associations *****");
				}
			} else {
				myerror_log("***** active combos but NO ACTIVE COMBO PRICES ******");
			}
		} else {
			myerror_logging(2, "ther are no active combos for this merchant");
		}
		
	}
		
	function getTheCompleteMenu($show_active_only = 'Y',$merchant_id = 0)
	{
		$use_default_price_list = $this->use_default_price_list;
		$menu_id = $this->menu_id;
		$menu =& $this->menu;
		
		// menu has its own log level because of the high number of selects and data.
		$menu_log_level = getProperty('menu_log_level');
		$temp = $_SERVER['log_level'];
		$_SERVER['log_level'] = $menu_log_level;
		
		if ($merchant_id != 0) {
			$this->merchant_resource = Resource::find(new MerchantAdapter(getM()),''.$merchant_id);
		} else {
			$this->merchant_resource = Resource::dummyfactory(array("name"=>"default","zip"=>"88888","active"=>"Y"));
		}
		$time1 = microtime(true);
			
		// get the LTO's for this menu at this time.
        if (!$this->catering) {
            $ltos = $this->getLTOs($menu_id, $merchant_id, $use_default_price_list);
            myerror_log("we have returned from checkint the ltos and there are: ".sizeof($ltos));
        }
        myerror_log("about to go get the modifiers",2);

		$modifier_groups = $this->getModifierItemsPrices($menu_id,$show_active_only,getM(),$merchant_id,$use_default_price_list,$ltos);
		if ($this->api_version == 1) {
			$menu['modifier_groups'] = $modifier_groups;
		}
		if (isLocalDevelopment()) {
			$menu['test_modifier_groups'] = $modifier_groups;
		}
		myerror_log("about to get items",2);
		$menu['menu_types'] = $this->getMenuTypesItemsPrices($menu_id,$show_active_only,getM(),$merchant_id,$use_default_price_list,$ltos);
		if (!$this->catering) {
            myerror_logging(2, "about to get combos");
            $menu['combos'] = $this->getCombos($menu_id,$show_active_only,$merchant_id,$use_default_price_list);
            myerror_logging(2, "about to get upsell");
            $menu['upsell_item_ids'] = $this->getUpsellItemIdsAsArray($menu_id, $show_active_only);
            myerror_logging(2, "done getting all groups");
        }


		$time2 = microtime(true);
		$time_of_query = $time2 - $time1;
		
		myerror_log("Time for full menu retieval of menu $menu_id,  and merchant_id: $merchant_id  is: ".$time_of_query);
		$menu['time_to_retrieve_menu'] = $time_of_query;
		
		$time3 = microtime(true);
		recursiveRemoval($menu, 'class');

		$time4 = microtime(true);
		$time_of_query2 = $time4 - $time3;
		myerror_log("time for recursive removal of unused fields is: ".$time_of_query2);
		
		$_SERVER['log_level'] = $temp;

        if ($this->brand_loyalty_rules) {
            $menu['charge_modifiers_loyalty_purchase'] = $this->brand_loyalty_rules['charge_modifiers_loyalty_purchase'];
        }

        return $menu;
	}

	function getSizes($menu_type_id, $active,$mimetypes)
	{
		
			$size_adapter = new SizeAdapter($mimetypes);
			myerror_logging(4,"about to get sizes with menu_type id of: ".$menu_type_id);
			$optionsx[TONIC_FIND_BY_METADATA]['menu_type_id'] = $menu_type_id;
			if ($active == 'Y')		
				$optionsx[TONIC_FIND_BY_METADATA]['active'] = 'Y';		
			$optionsx[TONIC_SORT_BY_METADATA] = 'priority DESC';		
			$sizes = $size_adapter->select('',$optionsx);
		return $sizes;
	}
	
	/**
	 * Returns a hash of arrays with menu_type_id as the index.  Each menu_type_id has an array of all the child Size records
	 * 
	 * @param unknown_type $menu_id
	 * @param unknown_type $active
	 * @param unknown_type $mimetypes
	 */
	static function getAllSizes($menu_id,$active,$mimetypes)
	{
		$size_adapter = new SizeAdapter($mimetypes);
		$size_data = array();
		$size_options[TONIC_FIND_BY_METADATA] = $size_data;
		$size_options[TONIC_JOIN_STATEMENT] = " JOIN Menu_Type ON Sizes.menu_type_id = Menu_Type.menu_type_id ";
		$size_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
		$sizes = $size_adapter->select(null,$size_options);
		foreach ($sizes as $size_record)
		{
			if ($active == 'Y' && $size_record['active'] == 'N')
				continue;
			$menu_type_id = $size_record['menu_type_id'];
			$size_id = $size_record['size_id'];
			
			unset($size_record['created']);
			unset($size_record['modified']);
			unset($size_record['logical_delete']);
			
			$all_sizes[$menu_type_id][] = $size_record;
		}	 

		return $all_sizes;
	}

	static function getAllSizesAsResources($menu_id)
    {
        $size_adapter = new SizeAdapter(getM());
        $size_data = array();
        $size_options[TONIC_FIND_BY_METADATA] = $size_data;
        $size_options[TONIC_JOIN_STATEMENT] = " JOIN Menu_Type ON Sizes.menu_type_id = Menu_Type.menu_type_id ";
        $size_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
        $sizes = Resource::findAll($size_adapter,null,$size_options);
        return $sizes;

    }
	
	/**
	 * Returns a hash of arrays with menu_type_id as the index.  Each menu_type_id has an array of all the child Item records
	 * 
	 * @param unknown_type $menu_id
	 * @param unknown_type $active
	 * @param unknown_type $mimetypes
	 */
	function getAllMenuItems($menu_id,$active,$mimetypes)
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		$items = CompleteMenu::getAllMenuItemsAsArray($menu_id, $active, $mimetypes);
		foreach ($items as $item_record)
		{
			if ($active == 'Y' && ($item_record['active'] == 'N' || $item_record['active'] == 'L') )
				continue;
			$menu_type_id = $item_record['menu_type_id'];

      if($item_record['calories'] != NULL ){
				$item_record['calories'] = $item_record['calories'].' '. self::CAL; //add 'Cal' label to the end of calories	
			}
			unset($item_record['created']);
			unset($item_record['modified']);
			unset($item_record['logical_delete']);
			//unset($item_record['external_item_id']);
			$all_items[$menu_type_id][] = $item_record;
			$this->full_item_list[$item_record['item_id']] = $item_record;
		}
		return $all_items;
	}

	static function getAllSizesInMenuType($menu_type_id)
    {
        $menu_type_record = MenuTypeAdapter::staticGetRecord(['menu_type_id'=>$menu_type_id],'MenuTypeAdapter');
        $sizes_in_menu_type = [];
        foreach (CompleteMenu::getAllSizes($menu_type_record['menu_id'],'Y',getM()) as $size) {
            if ($size['menu_type_id'] == $menu_type_id) {
                $sizes_in_menu_type[] = $size;
            }
        }
        return $sizes_in_menu_type;
    }

	static function getAllMenuItemsInMenuType($menu_type_id)
    {
        $menu_type_record = MenuTypeAdapter::staticGetRecord(['menu_type_id'=>$menu_type_id],'MenuTypeAdapter');
        $menu_items_in_menu_type = [];
        foreach(CompleteMenu::getAllMenuItemsAsArray($menu_type_record['menu_id'],'Y',getM()) as $menu_item) {
            if ($menu_item['menu_type_id'] == $menu_type_id) {
                $menu_items_in_menu_type[] = $menu_item;
            }
        }
        return $menu_items_in_menu_type;
    }

    static function getAllMenuItemsAsArray($menu_id,$active='Y',$mimetypes = [])
    {
        $item_adapter = new ItemAdapter(getM());
        $item_data = array();
        $item_options[TONIC_FIND_BY_METADATA] = $item_data;
        $item_options[TONIC_JOIN_STATEMENT] = " JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
        $item_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
        $item_options[TONIC_SORT_BY_METADATA] = " Item.priority DESC ";
        $items = $item_adapter->select(null,$item_options);
        return $items;
    }

    static function getAllMenuItemsAsResources($menu_id,$active='Y',$mimetypes = [])
    {
        $item_adapter = new ItemAdapter(getM());
        $item_data = array();
        $item_options[TONIC_FIND_BY_METADATA] = $item_data;
        $item_options[TONIC_JOIN_STATEMENT] = " JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
        $item_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
        $item_options[TONIC_SORT_BY_METADATA] = " Item.priority DESC ";
        return Resource::findAll($item_adapter,null,$item_options);
    }

	static function getAllMenuItemsAsArrayOrganizedByMenuType($menu_id)
    {
        $menu_items_by_menu_type = [];
        $menu_items = CompleteMenu::getAllMenuItemsAsArray($menu_id);
        foreach ($menu_items as $menu_item) {
            $menu_type_id = $menu_item['menu_type_id'];
            $menu_items_by_menu_type[$menu_type_id][] = $menu_item;
        }
        return $menu_items_by_menu_type;
    }

    static function staticGetAllItemModifierGroupMapsAsResources($menu_id,$merchant_id)
    {
        $item_modifier_group_map_adapter = new ItemModifierGroupMapAdapter(getM());
        $imgma_data['merchant_id'] = $merchant_id;
        $imgma_options[TONIC_FIND_BY_METADATA] = $imgma_data;
        $imgma_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Item.item_id = Item_Modifier_Group_Map.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
        $imgma_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
        $imgma_options[TONIC_SORT_BY_METADATA] = ' Item_Modifier_Group_Map.priority DESC ';
        return Resource::findAll($item_modifier_group_map_adapter,null,$imgma_options);
    }

	static function getAllItemSizesAsResources($menu_id,$merchant_id)
    {
        $item_size_adapter = new ItemSizeAdapter();
        $item_size_data['merchant_id'] = $merchant_id;
        $item_size_options[TONIC_FIND_BY_METADATA] = $item_size_data;
        $item_size_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Item.item_id = Item_Size_Map.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
        $item_size_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
        $item_size_options[TONIC_SORT_BY_METADATA] = ' Item_Size_Map.priority DESC ';
        return Resource::findAll($item_size_adapter,null,$item_size_options);
    }

    static function getAllModifierItemsAsArray($menu_id,$active,$merchant_id)
    {
        $modifier_adapter = new ModifierItemAdapter($m);
        $mod_data = array('merchant_id'=>$merchant_id);
        $item_options[TONIC_FIND_BY_METADATA] = $mod_data;
        $item_options[TONIC_JOIN_STATEMENT] = " JOIN Modifier_Group ON Modifier_Item.modifier_group_id = Modifier_Group.modifier_group_id ";
        $item_options[TONIC_FIND_BY_STATIC_METADATA] = " Modifier_Group.menu_id = $menu_id AND Modifier_Group.logical_delete = 'N' ";
        //$item_options[TONIC_SORT_BY_METADATA] = " Item.priority DESC ";
        $modifiers = $modifier_adapter->select(null,$item_options);
        return $modifiers;
    }

    static function getAllModifierItemSizesAsResoures($menu_id,$merchant_id)
    {
        $modifier_size_adapter = new ModifierSizeMapAdapter(getM());
        $mod_data = array('merchant_id'=>$merchant_id);
        $modifier_options[TONIC_FIND_BY_METADATA] = $mod_data;
        $modifier_options[TONIC_JOIN_STATEMENT] = " JOIN Modifier_Item ON Modifier_Size_Map.modifier_item_id = Modifier_Item.modifier_item_id JOIN Modifier_Group ON Modifier_Item.modifier_group_id = Modifier_Group.modifier_group_id ";
        $modifier_options[TONIC_FIND_BY_STATIC_METADATA] = " Modifier_Group.menu_id = $menu_id AND Modifier_Group.logical_delete = 'N' ";
        return Resource::findAll($modifier_size_adapter,null,$modifier_options);
    }

    static function getAllModifierItemSizesAsArray($menu_id,$active,$merchant_id)
    {
        $modifier_size_adapter = new ModifierSizeMapAdapter(getM());
        $mod_data = array('merchant_id'=>$merchant_id);
        $modifier_options[TONIC_FIND_BY_METADATA] = $mod_data;
        $modifier_options[TONIC_JOIN_STATEMENT] = " JOIN Modifier_Item ON Modifier_Size_Map.modifier_item_id = Modifier_Item.modifier_item_id JOIN Modifier_Group ON Modifier_Item.modifier_group_id = Modifier_Group.modifier_group_id ";
        $modifier_options[TONIC_FIND_BY_STATIC_METADATA] = " Modifier_Group.menu_id = $menu_id AND Modifier_Group.logical_delete = 'N' ";

        $modifiers = $modifier_size_adapter->select(null,$modifier_options);
        return $modifiers;
    }


    function getAllNuritionRecordsByItemIdAndSizeId($menu_id)
    {
        $menu_id = $this->getMenuIdForMethodCalls($menu_id);
        $nutrition_item_size_infos_adapter = new NutritionItemSizeInfosAdapter(getM());

        $nutrition_options[TONIC_FIND_BY_METADATA] = [];
        $nutrition_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Nutrition_Item_Size_Infos.item_id = Item.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id JOIN Menu ON Menu_Type.menu_id = Menu.menu_id ";
        $nutrition_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu.menu_id = $menu_id ";
        $nutrition_resources = Resource::findAll($nutrition_item_size_infos_adapter, '', $nutrition_options);

        $nutrition_array = [];
        foreach ($nutrition_resources as $nr) {
            $item_id = $nr->item_id;
            $size_id = $nr->size_id;
            $nutrition_array["$item_id-$size_id"] = cleanDataForResponse($nr->getDataFieldsReally());
        }
        return $nutrition_array;

    }

    /**
	 * @desc returns a hash of arrays with item_id as the index.  each item_id has array of all teh item_size records associated with it
	 *
	 * @param $menu_id
	 * @param $active
	 * @param $merchant_id
	 * @param $mimetypes
	 */
	function getAllMenuItemSizePrices($menu_id,$active,$merchant_id,$mimetypes,$use_default_price_list = false,$ltos = array())
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		$item_size_adapter = new ItemSizeAdapter($mimetypes);
		
		// menu version 3.0 will use merchant id on the price records
		$merchant_id = $use_default_price_list ? 0 : $merchant_id;
		$item_size_options = $this->getItemSizeOptions($menu_id, $merchant_id);
		$sizeprices = $item_size_adapter->select('',$item_size_options);
		foreach ($sizeprices as $size_price_record)
		{
			$size_id = $size_price_record['size_id'];
			if ($active == 'Y' && $size_price_record['active'] == 'N')
				continue;
			$item_id = $size_price_record['item_id'];
			unset($size_price_record['created']);
			unset($size_price_record['modified']);
			unset($size_price_record['logical_delete']);
			if ($size_price_record['external_id']) {
				$this->full_price_list_by_external_id[$size_price_record['external_id']] = $size_price_record;
				//unset($size_price_record['external_id']);
			} 
			unset($size_price_record['merchant_id']);
			$all_size_prices[$item_id][] = $size_price_record;
			$this->full_price_list_by_item_size["$item_id-$size_id"] = $size_price_record['price'];
			$this->full_item_list[$item_id]['sizeprices'][] = $size_price_record;
		}
		$this->all_size_prices = $all_size_prices;
		return $all_size_prices;
	}
	
	function getFullPriceListByItemSize()
	{
		return $this->full_price_list_by_item_size;
	}
	
	function getFullPriceListByExternalId()
	{
		return $this->full_price_list_by_external_id;
	}
	
	function getItemSizeOptions($menu_id,$merchant_id)
	{
		$item_size_data['merchant_id'] = $merchant_id;
		$item_size_options[TONIC_FIND_BY_METADATA] = $item_size_data;
		$item_size_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Item.item_id = Item_Size_Map.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
		$item_size_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' AND (Item_Size_Map.included_merchant_menu_types = '".$this->merchant_menu_type."' OR Item_Size_Map.included_merchant_menu_types = 'ALL') ";
		$item_size_options[TONIC_SORT_BY_METADATA] = ' Item_Size_Map.priority DESC ';
		return $item_size_options;
	}
	
	function getAllMenuItemSizeMapResources($menu_id,$active,$merchant_id)
	{
		$item_size_adapter = new ItemSizeAdapter($mimetypes);
		
		// menu version 3.0 will use merchant id on the price records
		$item_size_options = $this->getItemSizeOptions($menu_id, $merchant_id);
		$resources = Resource::findAll($item_size_adapter, $url, $item_size_options);
		return $resources;
	}
	
	function getModifierItemSizeOptions($menu_id,$merchant_id)
	{
		$modifier_item_size_data['merchant_id'] = $merchant_id;
		$modifier_item_size_options[TONIC_FIND_BY_METADATA] = $modifier_item_size_data;
		$modifier_item_size_options[TONIC_JOIN_STATEMENT] = " JOIN Modifier_Item ON Modifier_Item.modifier_item_id = Modifier_Size_Map.modifier_item_id JOIN Modifier_Group ON Modifier_Item.modifier_group_id = Modifier_Group.modifier_group_id ";
		$modifier_item_size_options[TONIC_FIND_BY_STATIC_METADATA] = " Modifier_Group.menu_id = $menu_id AND Modifier_Group.logical_delete = 'N'  AND (Modifier_Size_Map.included_merchant_menu_types = '".$this->merchant_menu_type."' OR Modifier_Size_Map.included_merchant_menu_types = 'ALL') ";
		$modifier_item_size_options[TONIC_SORT_BY_METADATA] = ' Modifier_Size_Map.priority DESC ';
		return $modifier_item_size_options;
	}
	
	function getAllModifierItemSizeResources($menu_id,$active,$merchant_id)
	{	
		$modifier_size_map_adapter = new ModifierSizeMapAdapter($mimetypes);
		// menu version 3.0 will use merchant id on the price records
		$modifier_item_size_options = $this->getModifierItemSizeOptions($menu_id, $merchant_id);
		$resources = Resource::findAll($modifier_size_map_adapter, $url, $modifier_item_size_options);
		return $resources;		
	}

	/**
	 *  @desc returns a hash of resources that are the price records for a particular menu for a particular merchant.  If merchant_id = 0  then it just returns all the pirce recrods
	 *  @desc if merchant_id does not equal zero, the resouces are set to _exists = false, and id = null where there is no price record for that merchant id.
	 *  
	 *  @param $menu_id
	 *  @param $merchant_id
	 * 
	 */
	function getHashOfResourcesAllPricesForMenuWithExternalIdAsIndex($menu_id,$merchant_id)
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		// we could add another parameter called $index which is the string name of the field that will be used for the index. "external_id"  or   "item_size_id"  etc....
		$item_size_adapter = new ItemSizeAdapter(getM());
		$item_size_adapter->log_level = 5;
		// menu version 3.0 will use merchant id on the price records
		$item_size_data['merchant_id'] = "0";
		$item_size_options[TONIC_FIND_BY_METADATA] = $item_size_data;
		$item_size_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Item.item_id = Item_Size_Map.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
		$item_size_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
		//$item_size_options[TONIC_SORT_BY_METADATA] = ' Item_Size_Map.priority DESC ';
		$sizeprice_resources = Resource::findAll($item_size_adapter,'',$item_size_options);
		foreach ($sizeprice_resources as $sp_resource)
		{
			if ($merchant_id > 0)
			{
				$sp_resource->item_size_id = null;
				$sp_resource->_exists = false;
			}
			$base_menu_external_ids['Item-'.$sp_resource->item_id.'-'.$sp_resource->size_id] = $sp_resource;
			
		}
		if ($merchant_id > 0)
		{
			//$item_size_data['merchant_id'] = $merchant_id;
			//$item_size_options[TONIC_FIND_BY_METADATA] = $item_size_data;
			unset($item_size_options[TONIC_FIND_BY_METADATA]);
			$item_size_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
			$sizeprice_merchant_id_resources = Resource::findAll($item_size_adapter,'',$item_size_options);
			foreach ($sizeprice_merchant_id_resources as $spmid_resource)
				$base_menu_external_ids['Item-'.$spmid_resource->item_id.'-'.$spmid_resource->size_id] = $spmid_resource;
		}
		return $base_menu_external_ids;		
	}

	/**
	 *  @desc returns a hash of resources that are the modifier price records for a particular menu for a particular merchant.  If merchant_id = 0  then it just returns all the pirce recrods
	 *  @desc if merchant_id does not equal zero, the resouces are set to _exists = false, and id = null where there is no price record for that merchant id.
	 *  
	 *  @param $menu_id
	 *  @param $merchant_id
	 * 
	 */	
	function getHashOfResourcesAllModifierPricesForMenuWithExternalIdAsIndex($menu_id,$merchant_id)
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		$base_menu_external_ids = array();
		// we could add another parameter called $index which is the string name of the field that will be used for the index. "external_id"  or   "item_size_id"  etc....
		$modifier_size_adapter = new ModifierSizeMapAdapter($mimetypes);
		
		// menu version 3.0 will use merchant id on the price records
		$modifier_size_data['merchant_id'] = "0";
		$modifier_size_options[TONIC_FIND_STATIC_FIELD] = " Modifier_Item.modifier_group_id, Modifier_Group.modifier_type ";
		$modifier_size_options[TONIC_FIND_BY_METADATA] = $modifier_size_data;
		$modifier_size_options[TONIC_JOIN_STATEMENT] = " JOIN Modifier_Item ON Modifier_Item.modifier_item_id = Modifier_Size_Map.modifier_item_id JOIN Modifier_Group ON Modifier_Item.modifier_group_id = Modifier_Group.modifier_group_id ";
		$modifier_size_options[TONIC_FIND_BY_STATIC_METADATA] = " Modifier_Group.menu_id = $menu_id AND Modifier_Group.logical_delete = 'N' ";
		$modifiersizeprice_resources = Resource::findAll($modifier_size_adapter,'',$modifier_size_options);
		foreach ($modifiersizeprice_resources as $msp_resource)
		{
			if ($merchant_id > 0)
			{
				$msp_resource->modifier_size_id = null;
				$msp_resource->_exists = false;
			}
			
			$base_menu_external_ids["modifier-".$msp_resource->modifier_item_id."-".$msp_resource->size_id] = $msp_resource;
		}
		if ($merchant_id > 0)
		{
			$modifier_size_data['merchant_id'] = $merchant_id;
			$modifier_size_options[TONIC_FIND_BY_METADATA] = $modifier_size_data;
			$modifiersizeprice_merchant_id_resources = Resource::findAll($modifier_size_adapter,'',$modifier_size_options);
			foreach ($modifiersizeprice_merchant_id_resources as $spmid_resource)
				$base_menu_external_ids["modifier-".$spmid_resource->modifier_item_id."-".$spmid_resource->size_id] = $spmid_resource;
		}
		return $base_menu_external_ids;		

	}

	/**
	 * 
	 * @desc returns a hash of resources that are the combo price records for a particualr menu for a particular merchant.  If merchant_id = 0  then it just returns all the pirce recrods
	 * @desc if merchant_id does not equal zero, the resouces are set to _exists = false, and id = null where there is no price record for that merchant id. 
	 * 
	 * @param $menu_id
	 * @param $merchant_id
	 */
	
	function getHashOfResourcesAllComboPricesForMenuWithExternalIdAsIndex($menu_id,$merchant_id)
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		$base_menu_external_ids = array();
		// we could add another parameter called $index which is the string name of the field that will be used for the index. "external_id"  or   "item_size_id"  etc....
		$combo_price_adapter = new MenuComboPriceAdapter($mimetypes);
		
		// menu version 3.0 will use merchant id on the price records
		$combo_price_data['merchant_id'] = "0";
		$combo_price_options[TONIC_FIND_BY_METADATA] = $combo_price_data;
		$combo_price_options[TONIC_JOIN_STATEMENT] = " JOIN Menu_Combo ON Menu_Combo.combo_id = Menu_Combo_Price.combo_id ";
		$combo_price_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Combo.menu_id = $menu_id AND Menu_Combo.logical_delete = 'N' ";
		$comboprice_resources = Resource::findAll($combo_price_adapter,'',$combo_price_options);
		foreach ($comboprice_resources as $cp_resource)
		{
			if ($merchant_id > 0)
			{
				$cp_resource->combo_price_id = null;
				$cp_resource->_exists = false;
			}
			$base_menu_external_ids['combo-'.$cp_resource->combo_id] = $cp_resource;
		}
		if ($merchant_id > 0)
		{
			$combo_price_data['merchant_id'] = $merchant_id;
			$combo_price_options[TONIC_FIND_BY_METADATA] = $combo_price_data;
			$comboprice_merchant_id_resources = Resource::findAll($combo_price_adapter,'',$combo_price_options);
			foreach ($comboprice_merchant_id_resources as $cpmid_resource)
			{
				$base_menu_external_ids['combo-'.$cpmid_resource->combo_id] = $cpmid_resource;
			}
		}
		return $base_menu_external_ids;		
	}

	function getMenuTypes($menu_id,$active,$mimetypes)
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		$menu_type_adapter = new MenuTypeAdapter($mimetypes);
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';		
		if ($active == 'Y') {
            $options[TONIC_FIND_BY_METADATA]['active'] = 'Y';
        }

        if ($this->catering) {
            $options[TONIC_FIND_BY_METADATA]['cat_id'] = 'C';
        } else if ($this->show_catering_items == false) {
            $options[TONIC_FIND_BY_METADATA]['cat_id'] = array("!=" => "C");
        }

		$options[TONIC_FIND_BY_METADATA]['menu_id'] = $menu_id;		
		$options[TONIC_SORT_BY_METADATA] = array('priority DESC','menu_type_name');		
		$menu_types = $menu_type_adapter->select('',$options);
		return $menu_types;
	}
		
	function getMenuItems($menu_type, $mimetypes)
	{
		$item_adapter = new ItemAdapter($mimetypes);
		$options[TONIC_FIND_BY_METADATA]['menu_type_id'] = $menu_type;		
		$options[TONIC_SORT_BY_METADATA] = array('priority DESC','item_name');		
		$items = $item_adapter->select('',$options);
		return $items;
	}
	
	function getMenuTypesItemsPrices($menu_id,$active,$mimetypes,$merchant_id = 0,$use_default_price_list = false,$ltos = array())
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		// load points data
		$using_loyalty = false;
		if ($merchant_id > 1000)
		{
			if ($loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext()) {
				if ($brand_points_list = $loyalty_controller->getBrandPointsList()) {
					myerror_log("we have a brand with loyalty turned on");
					$using_loyalty = true;
                    $this->brand_loyalty_rules = BrandLoyaltyRulesAdapter::staticGetRecord(array("brand_id"=>getBrandIdFromCurrentContext()),'BrandLoyaltyRulesAdapter');
				} else {
					myerror_log("BRAND WITH LOYALTY ON BUT NO BRAND POINTS!  probably pita pit before go live.");
				}
			} else {
                myerror_log(getSkinForContext()." with brand_id: ".getBrandIdFromCurrentContext().", does not have loyalty turned on, bypass points on menu.");
            }
		}
		myerror_log("about to execute getMenuTypesItemsPrices with a use_default_price_list of: ".$use_default_price_list);
		$better_menu_types_array = array();
		$menu_types = $this->getMenuTypes($menu_id,$active,$mimetypes);

		$upsells_adapter = new MenuTypeItemUpsellMapsAdapter(getM());
		$upsells_by_menu_type = $upsells_adapter->getUpsellItemsForMenuByMenuType($menu_id);

		// first add in any additional LTO menu types that are not a swap
		foreach ($ltos as $lto)
		{
			myerror_log('we have an lto!');
			foreach ($lto as $name=>$value)
				myerror_log("$name=$value");
			if (strtolower($lto['object_type']) == 'modifier_group')
				continue;
			else if (strtolower($lto['object_type']) == 'menu_type' && $lto['replace_id'] == NULL)
			{
				// get menu_type record
				$lto_menu_type_id = $lto['object_id'];
				$menu_type_adapter = new MenuTypeAdapter($mimetypes);
				$lto_menu_type_record = $menu_type_adapter->select($lto_menu_type_id);
				array_unshift($menu_types, array_pop($lto_menu_type_record));
			} else if (strtolower($lto['object_type']) == 'menu_type') {
				myerror_log('we have a menu type with a replace id of: '.$lto['replace_id']);
				$replaced_menu_types[$lto['replace_id']] = $lto;
			} else {
				myerror_log("we have an item lto");
				// its an item so get the item record
				$item_adapter = new ItemAdapter($mimetypes);
				$return = $item_adapter->select($lto['object_id']);
				$item = array_pop($return);
				if ( $lto['replace_id'] != null)
					$replaced_items[$lto['replace_id']] = $item;
				else
					$lto_items_by_menu_type[$item['menu_type_id']][] = $item;
			}
		}
		// first get complete sizes array but set the index as the menu_type
		myerror_logging(2,"about to get all items in the menu");
		$all_sizes = $this->getAllSizes($menu_id, $active, $mimetypes);
		$this->all_sizes_by_menu_type = $all_sizes;
		$all_items = $this->getAllMenuItems($menu_id, $active, $mimetypes);
		$all_item_sizes = $this->getAllMenuItemSizePrices($menu_id, $active, $merchant_id, $mimetypes, $use_default_price_list);
		$all_item_modifier_group_maps = $this->getAllItemModifierGroupMaps($menu_id, $active, $merchant_id, $use_default_price_list, $mimetypes);
		$all_item_modifier_item_maps = $this->getAllItemModifierItemMaps($menu_id, $active, $mimetypes);
		$all_price_overrides_by_size_by_item_modifier_group = $this->getAllItemModifierItemPriceOverridesBySizesOrganizedByItemModifierGroup($menu_id,$merchant_id);
		$this->all_price_overrides_by_size_by_item_modifier_group = $all_price_overrides_by_size_by_item_modifier_group;
		myerror_logging(2,"all items in the menu retrieved");
		foreach ($menu_types as &$menu_type)
		{
			unset($menu_type['logical_delete']);
			unset($menu_type['created']);
			unset($menu_type['modified']);
			unset($menu_type['external_menu_type_id']);

			$menu_type['visible'] = $menu_type['visible'] == "1" ? true : false;
			//could do a menu type LTO swap here by setting $menu_type to the LTO menu type and then letting the rest of the code run
			myerror_logging(4,"about to do menu type id: ".$menu_type['menu_type_id']);
			if ($lto_record = $replaced_menu_types[$menu_type['menu_type_id']])
			{
				myerror_log("LTO! about to do the sub of ".$lto_record['object_id']." for ".$menu_type['menu_type_id']);
				//get new menu type record
				$menu_type_adapter = new MenuTypeAdapter($mimetypes);
				$menu_type_id = $lto_record['object_id'];
				myerror_log("about to get the menu type record for menu type id: ".$menu_type_id);
				if ($menu_type_id > 0)
				{
					$lto_menu_type_record = $menu_type_adapter->select(''.$menu_type_id);
					myerror_log("size of lto returned is: ".sizeof($lto_menu_type_record));
					if (sizeof($lto_menu_type_record) < 1)
						MailIt::sendErrorEmail("ERROR!  couldn't locate LTO menu type record", "menu_type_id of: $menu_type_id");
					else
					{
						$menu_type = array_pop($lto_menu_type_record);
						myerror_log("****** getting lto menu type values *******");
						foreach ($menu_type as $name=>$value)
						{
							myerror_log("$name = $value");
						}
						myerror_log("we got the menu type record for the lto");
					}
				}
				else
				{
					myerror_log("ERROR!   serious error, no menu type id to serach for lto");
					MailIt::sendErrorEmail("ERROR! NO object_id set in LTO record" , "LTO record id: ".$lto_record['menu_change_id']);
				}
				
				// and we're on our merry way
			} 
	
			$menu_type_id = $menu_type['menu_type_id'];
			//first get sizes
			if ($sizes = $all_sizes[$menu_type_id]) {
				if ($this->api_version != 2) {
					$menu_type['sizes'] = $sizes; // all is good
				}
			} else {
				myerror_log('************* SERIOUS ERROR IN completemenu.  no active sizes for active menutype!  We will skip.  menu_type_id: '.$menu_type_id);
				$error_text = "No active sizes for active menutype! menu_type_id: ".$menu_type_id."  \r\n";
				$this->error_text = $this->error_text.$error_text;
				continue;
			}

			//now get items
			$items = $all_items[$menu_type_id];
			$sizeprices = array();
			$items_to_return = array();

			//check for non substituted lto items that just get added to this menu_type
			
			foreach ($lto_items_by_menu_type[$menu_type['menu_type_id']] as $lto_item)
			{
				array_unshift($items, $lto_item);
			}
			
			foreach ($items as &$item)
			{
				// swap out existing ITEM with the LTO item record if it exists
				if ($lto_item_record = $replaced_items[$item['item_id']])
				{
					$item = $lto_item_record;
					// and we're on our merry way?
				}
				
				myerror_logging(4, "looping with item ".$item['item_name']."   id:".$item['item_id']);
				// what about burritos that are %50 off during the afternoon, kind of an LTO?  
				// more of a time based price.  we have no way of doing that without completely replacing the item

				if ($item['item_id'] && $item['item_id'] != '') {
					$item_id = $item['item_id'];
					if ($sizeprices = $all_item_sizes[$item_id]) {
						foreach ($sizeprices as &$sizeprice) {
							// get the name
							foreach ($sizes as $size) {

								if ($sizeprice['size_id'] == $size['size_id']) {
									$sizeprice['size_name'] = $size['size_name'];
									$sizeprice['default_selection'] = $size['default_selection'] == 1 ? "Yes" : null;
								}
							}
							// get the points
							$this_item_id = $sizeprice['item_id'];
							$this_size_id = $sizeprice['size_id'];
							if ($using_loyalty) {
								if ($brand_points_record = $brand_points_list['size_'.$this_size_id])
									;
								else if (($brand_points_record = $brand_points_list['item_'.$this_item_id]))
									;
                                else if (($brand_points_record = $brand_points_list['menu_type_'.$item['menu_type_id']]))
                                    ;
								
								if ($brand_points_record) {
									$sizeprice['points'] = $brand_points_record['points'];
									$sizeprice['brand_points_id'] = $brand_points_record['brand_points_id'];
								}														
							
							}
// CHANGE THIS!
		// need to allow each size to have its own tax group.  forcing right now because needs to be backwards compatable.
							$tax_group = $sizeprice['tax_group'];							
						}
						if (sizeof($sizeprices) == 1) {
                            $sizeprices[0]['default_selection'] = 'Yes';
                        }
						$item['size_prices'] = $sizeprices;
						$item['tax_group'] = $tax_group;  // CHANGE THIS!
					} else {
						myerror_logging(4,"NO ACTIVE PRICES FOR THIS ITEM!  so we'll skip. merchant_id: ".$merchant_id."  item_id: ".$item['item_id']);
						continue; // no need to get any further info, there are no active prices for this item.  goto next item
					}
					if ($this->api_version == 2) {
						if ($item_modifier_group_maps = $all_item_modifier_group_maps[$item_id]) {
							$comes_with_items = $all_item_modifier_item_maps[$item_id];
							$valid_group_maps = $this->getValidItemModifierGroupMapsFromAllMapsForThisValidActiveItem($item_id, $item_modifier_group_maps);
							$item['modifier_groups'] = $this->configureVersionTwoModifierGroups($valid_group_maps,$comes_with_items,$this->available_modifier_groups,$menu_type_id);
						}
					} else {
						if ($item_modifier_group_maps = $all_item_modifier_group_maps[$item_id]) {
							//check the groups assigned to this item.
							$valid_group_maps = $this->getValidItemModifierGroupMapsFromAllMapsForThisValidActiveItem($item_id, $item_modifier_group_maps);
							$item['allowed_modifier_groups'] = $valid_group_maps;
						} 
						if ($comes_with_items = $all_item_modifier_item_maps[$item_id]) {
							$item['comes_with_modifier_items'] = $comes_with_items;
						}
					}
					
					$photos = PhotoAdapter::findForItem($item_id);
					$item['photos'] = $photos;
					
					$items_to_return[] = $item;
					$this->active_items[$item['item_id']] = $item;
				} else {
					myerror_log('************* SERIOUS ERROR IN completemenu line 395.  no item id!');
				}
			}

			// check to see if we actually have prices and add it to the returned array if we do.		
			if (sizeof($items_to_return) > 0)
			{
				$menu_type['menu_items'] = $items_to_return;
                $better_menu_types_array[] = $menu_type;
			}
		}
        //now add upsells if they exist
        foreach ($better_menu_types_array as &$menu_type) {
		    $menu_type_id = $menu_type['menu_type_id'];
            $menu_type['upsell_item_ids'] = [];
		    foreach ($upsells_by_menu_type[$menu_type_id] as $item_id) {
		        if (isset($this->active_items["$item_id"])) {
                    $menu_type['upsell_item_ids'][] = $item_id;
                }
            }
        }
		return $better_menu_types_array;
	}

	function getPriceOverrideBySizeArray($price_overrides_by_size_for_item_group_combo,$sizes_for_menu_type,$default_price_override)
    {
        //$price_overrides_by_size_for_item_group_combo = $this->all_price_overrides_by_size_by_item_modifier_group[$item_id.'-'.$modifier_group_id];
        $override_by_size = [];
        foreach ($sizes_for_menu_type as $size) {
            $size_id = $size['size_id'];
            $override_by_size[$size_id] = isset($price_overrides_by_size_for_item_group_combo[$size_id]) ? $price_overrides_by_size_for_item_group_combo[$size_id]['price_override'] : $default_price_override;
        }
        return $override_by_size;
    }
	
	function configureVersionTwoModifierGroups($valid_group_maps,$comes_with_items,$available_modifier_groups,$menu_type_id)
	{
		$modifier_groups = array();
		$comes_with_hashmap_by_modifier_id = createHashmapFromArrayOfArraysByFieldName($comes_with_items, 'modifier_item_id');
		foreach ($valid_group_maps as $group_map) {
			$item_id = $group_map['item_id'];
			$modifier_group_id = $group_map['modifier_group_id'];
			if (isLaptop()) {
				//need this for running tests
				$group['modifier_group_id'] = $modifier_group_id;
			}
			$group['modifier_group_display_name'] = $group_map['display_name'];
			$group['modifier_group_type'] = $available_modifier_groups[$modifier_group_id]['modifier_type'];
			$group['modifier_group_credit'] = $group_map['price_override'];

			// get the build array of price overrides
			$group['modifier_group_credit_by_size'] = $this->getPriceOverrideBySizeArray($this->all_price_overrides_by_size_by_item_modifier_group[$item_id.'-'.$modifier_group_id],$this->all_sizes_by_menu_type[$menu_type_id],$group_map['price_override']);

			$group['modifier_group_max_price'] = $group_map['price_max'];
			$group['modifier_group_max_modifier_count'] = $group_map['max'];
			$group['modifier_group_min_modifier_count'] = $group_map['min'];
			$group['modifier_group_display_priority'] = $group_map['priority'];
			$modifier_items = $available_modifier_groups[$modifier_group_id]['modifier_items'];
			$better_modifier_items = array();
            $this->number_of_preselected_modifiers = 0;
            $item_sizes = $this->all_size_prices[$item_id];
            foreach ($modifier_items as $modifier_item) {
                if ($modifier_item['nested_items']) {
                    $better_nested_modifier_items = array();
                    foreach ($modifier_item['nested_items'] as $nested_modifier_item) {
                        $better_nested_modifier_items[] = $this->createBetterFinalModifierItemObject($nested_modifier_item,$group_map,$comes_with_hashmap_by_modifier_id,$item_sizes);
                    }
                    $modifier_item['nested_items'] = $better_nested_modifier_items;
                    $better_modifier_item = $modifier_item;
                } else {
                    $better_modifier_item = $this->createBetterFinalModifierItemObject($modifier_item,$group_map,$comes_with_hashmap_by_modifier_id,$item_sizes);
                }
                if (sizeof($better_modifier_item["modifier_prices_by_item_size_id"]) > 0) {
                    $better_modifier_items[] = $better_modifier_item;
                } else if (isset($better_modifier_item['nested_items']) && sizeof($better_modifier_item['nested_items']) > 0) {
                    if (sizeof($better_modifier_item['nested_items'][0]['modifier_prices_by_item_size_id']) > 0) {
                        $better_modifier_items[] = $better_modifier_item;
                    }
                }
			}
            if ($this->number_of_preselected_modifiers == 0 && $group_map['min'] > 0){
				myerror_log("NOW by-passing AUTO PRESELECT",3);
                //need to preselect the first one of the group
//                if ($better_modifier_items[0]['nested_items']) {
//                    $better_modifier_items[0]['nested_items'][0]['modifier_item_pre_selected'] = 'yes';
//                }
//                $better_modifier_items[0]['modifier_item_pre_selected'] = 'yes';
            }
            if (sizeof($better_modifier_items) > 0) {
                $group['modifier_items'] = $better_modifier_items;
                $modifier_groups[] = $group;
            }
		}
		return $modifier_groups;
	}

    function createBetterFinalModifierItemObject($modifier_item,$group_map,$comes_with_hashmap_by_modifier_id,$item_sizes)
    {
        $better_modifier_item = array();
        $better_modifier_item['modifier_item_name'] = $modifier_item['modifier_item_name'];
        $better_modifier_item['modifier_item_priority'] = $modifier_item['priority'];
        $better_modifier_item['modifier_item_id'] = $modifier_item['modifier_item_id'];
        $better_modifier_item['modifier_item_calories'] =  $modifier_item['calories'];
        $better_modifier_item['modifier_item_max'] = ($group_map['max'] < $modifier_item['modifier_item_max']) ? $group_map['max'] : $modifier_item['modifier_item_max'];
        if ($comes_with_array = $comes_with_hashmap_by_modifier_id[$modifier_item['modifier_item_id']]) {
            $this->number_of_preselected_modifiers++;
            $better_modifier_item['modifier_item_min'] = $comes_with_array['mod_item_min'];
            $better_modifier_item['modifier_item_pre_selected'] = 'yes';
        } else {
            $better_modifier_item['modifier_item_min'] = 0;
            $better_modifier_item['modifier_item_pre_selected'] = 'no';
        }
        $better_modifier_prices_by_item_size_id = array();
        foreach ($item_sizes as $item_size) {
            $size_id = $item_size['size_id'];
			$delay_default_prices_by_item_size_id_record = null;
            foreach ($modifier_item['modifier_size_maps'] as $modifier_size_map) {
                // use delay so exact modifier_item_size price will override the default
                if ($modifier_size_map['size_id'] == $size_id) {
                    if ($modifier_size_map['active'] == 'Y') {
                        $delay_default_prices_by_item_size_id_record = array("size_id"=>$size_id,"modifier_price"=>$modifier_size_map['modifier_price']);
                    } else {
                        unset($delay_default_prices_by_item_size_id_record);
                        $delay_default_prices_by_item_size_id_record = null;
                    }
                    break;
                } else if (isset($modifier_size_map['size_id']) && $modifier_size_map['size_id'] == 0) {
                    if ($modifier_size_map['active'] == 'Y') {
                        $delay_default_prices_by_item_size_id_record = array("size_id"=>$size_id,"modifier_price"=>$modifier_size_map['modifier_price']);
                    }
                }
            }
			if ($delay_default_prices_by_item_size_id_record) {
                $better_modifier_prices_by_item_size_id[] = $delay_default_prices_by_item_size_id_record;
			}
        }
        $better_modifier_item['modifier_prices_by_item_size_id'] = $better_modifier_prices_by_item_size_id;
        return $better_modifier_item;
    }
	
	function getValidItemModifierGroupMapsFromAllMapsForThisValidActiveItem($item_id,$item_modifier_group_maps)
	{
		$valid_group_maps = array();
		foreach ($item_modifier_group_maps as $item_modifier_group_map)
		{
			if ($this->validateItemModifierGroupMapForThisValidActiveItem($item_id, $item_modifier_group_map)) {
				//unset($item_modifier_group_map['item_id']); 
				$valid_group_maps[] = $item_modifier_group_map;
			}
		}
		return $valid_group_maps;
	}
	
	function validateItemModifierGroupMapForThisValidActiveItem($item_id,$item_modifier_group_map)
	{
		if ($item_id != $item_modifier_group_map['item_id'])
		{
			myerror_log("CORRUPTION ERROR! item_id does not match in item_modifier_group_map_record");
			MailIt::sendErrorEmail("MENU DATA CORRUPTION ERROR", "item_id does not match value in item_modifier_group_map_record.  item_id: $item_id  map_id: ".$item_modifier_group_map['map_id']);
			return false;
		}
		$modifier_group_id = $item_modifier_group_map['modifier_group_id'];
		if ($this->available_modifier_groups[$modifier_group_id]) {
			// modifier group exists so check if its active
			$active_flag = $this->available_modifier_groups[$modifier_group_id]['active'];
			if ( $active_flag == 'Y' || $active_flag == 'M') {
				return true;
			} else {
				// modifier group is innactive so check to make sure the min is 0 for this mapping
				if ($item_modifier_group_map['min'] > 0) {
					myerror_log("innactive modifier group but min greater than 0. THIS IS AN ERROR!");
					$error_text .= "Innactive modifier group but min greater than 0 on map record! item_id = $item_id (".$this->full_item_list[$item_id]['item_name'].") -  modifier_group_id = $modifier_group_id  (".$this->full_modifier_group_list[$modifier_group_id]['modifier_group_name'].")-  item_modifier_group_map_id = ".$item_modifier_group_map['map_id']." \r\n";
				} else {
					myerror_log("we have an group with active='N' so DO NOT throw error, just do not include the mapping");
				}
			}
		} else {
			// make sure that the min on the mapping is greater than zero before throwing an error
			if ($item_modifier_group_map['min'] > 0) {
				myerror_log("Active item mapped to unavailable modifier group with a min of greater than 0. THIS IS AN ERROR!");
				$error_text .= "Active item mapped to unavailable modifier group with a min greater than 0! item_id = $item_id (".$this->full_item_list[$item_id]['item_name'].") -  modifier_group_id = $modifier_group_id  (".$this->full_modifier_group_list[$modifier_group_id]['modifier_group_name'].")-  item_modifier_group_map_id = ".$item_modifier_group_map['map_id']." \r\n";
			} else {
				myerror_log("Active item mapped to unavailable modifier group but no minimum required so do not throw an error",3);
			}								
		}
		if ($error_text) {
			$this->error_text = $this->error_text.$error_text;
		}
		return false;
	}
	
	/**
	 * returns a hash of arrays with item_id as the index.  each item_id has array of all teh item_modifier_group_map records associated with it
	 * 
	 * @param unknown_type $menu_id
	 * @param unknown_type $active
	 * @param unknown_type $merchant_id
	 * @param unknown_type $use_default_price_list
	 * @param unknown_type $mimetypes
	 */
	function getAllItemModifierGroupMaps($menu_id,$active,$merchant_id,$use_default_price_list,$mimetypes)
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		$item_modifier_group_map_adapter = new ItemModifierGroupMapAdapter($mimetypes);

		// menu version 3.0 will use merchant id on the price records
		$item_modifier_group_data['merchant_id'] = $merchant_id;
		if ($use_default_price_list)
			$item_modifier_group_data['merchant_id'] = 0;

		$item_modifier_group_map_options[TONIC_FIND_BY_METADATA] = $item_modifier_group_data;
		$item_modifier_group_map_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Item.item_id = Item_Modifier_Group_Map.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
		$item_modifier_group_map_options[TONIC_FIND_BY_STATIC_METADATA] = " Item.logical_delete = 'N' AND Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
		//$item_modifier_group_map_options[TONIC_SORT_BY_METADATA] = array('Item_Modifier_Group_Map.priority DESC','display_name');
		
		if ($item_group_maps = $item_modifier_group_map_adapter->select('',$item_modifier_group_map_options))
		{
			usort($item_group_maps, "CompleteMenu::sortByItemModifierGroupMapPriorityDescending");
			foreach ($item_group_maps as $item_group_map)
			{
				$item_id = $item_group_map['item_id'];
				// this could throw errors if available groups has not been loaded yet
					$all_item_group_maps[$item_id][] = $item_group_map;
			}
		}
		return $all_item_group_maps;		
	}

	function getAllItemModifierGroupMapsAsResources($menu_id,$active,$merchant_id,$use_default_price_list,$mimetypes)
    {
        $item_modifier_group_map_adapter = new ItemModifierGroupMapAdapter($mimetypes);
        $item_modifier_group_data['merchant_id'] = $merchant_id;
        if ($use_default_price_list)
            $item_modifier_group_data['merchant_id'] = 0;

        $item_modifier_group_map_options[TONIC_FIND_BY_METADATA] = $item_modifier_group_data;
        $item_modifier_group_map_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Item.item_id = Item_Modifier_Group_Map.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
        $item_modifier_group_map_options[TONIC_FIND_BY_STATIC_METADATA] = " Item.logical_delete = 'N' AND Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
        return Resource::findAll($item_modifier_group_map_adapter,null,$item_modifier_group_map_options);

    }

    function getAllItemModifierItemPriceOverridesBySizesOrganizedByItemModifierGroup($menu_id,$merchant_id)
    {
        $overrides_by_sizes = $this->getAllItemModifierItemPriceOverridesBySizesAsResources($menu_id,$merchant_id);
        $better_organized_price_override_by_size = [];
        foreach ($overrides_by_sizes as $price_override_by_size) {
            $group_id = $price_override_by_size->modifier_group_id;
            $item_id = $price_override_by_size->item_id;
            $size_id = $price_override_by_size->size_id;
            $better_organized_price_override_by_size[$item_id.'-'.$group_id][$size_id] = $price_override_by_size->getDataFieldsReally();
        }
        return $better_organized_price_override_by_size;
    }

    function getAllItemModifierItemPriceOverridesBySizesAsResources($menu_id,$merchant_id)
    {
        $item_modifier_item_price_override_by_size_adapter = new ItemModifierItemPriceOverrideBySizesAdapter(getM());
        $item_modifier_item_price_override_by_size_data['merchant_id'] = $merchant_id;
        $options[TONIC_FIND_BY_METADATA] = $item_modifier_item_price_override_by_size_data;
        $options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Item.item_id = Item_Modifier_Item_Price_Override_By_Sizes.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
        $options[TONIC_FIND_BY_STATIC_METADATA] = " Item.logical_delete = 'N' AND Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
        return Resource::findAll($item_modifier_item_price_override_by_size_adapter,null,$options);
    }

	static function sortByItemModifierGroupMapPriorityDescending($a,$b) {
		return $a['priority']<$b['priority'];
	}
	
	/**
	 * 
	 * returns a hash of arrays with item_id as the index.  each item_id has array of all teh item_modifier_item_map records associated with it (comes with list)
	 * 
	 * @param int $menu_id
	 * @param char $active
	 * @param unknown_type $mimetypes
	 */
	function getAllItemModifierItemMaps($menu_id,$active,$mimetypes)
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		$item_modifier_item_map_adapter = new ItemModifierItemMapAdapter($mimetypes);
		$item_modifier_item_data = array();
		$item_modifier_item_map_options[TONIC_FIND_BY_METADATA] = $item_modifier_item_data;
		$item_modifier_item_map_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Item.item_id = Item_Modifier_Item_Map.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
		$item_modifier_item_map_options[TONIC_FIND_BY_STATIC_METADATA] = " Item.logical_delete = 'N' AND Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
		
		$all_comes_with_items = array();
		if ($comes_with_items = $item_modifier_item_map_adapter->select('',$item_modifier_item_map_options))
		{
			foreach ($comes_with_items as $comes_with_item)
			{
				$item_id = $comes_with_item['item_id'];
				unset($comes_with_item['map_id']);
				unset($comes_with_item['item_id']);
				$all_comes_with_items[$item_id][] = $comes_with_item;
			}
		}
		return $all_comes_with_items;
	}

	function getModifierItems($modifier_group_id,$active,$mimetypes)
	{
		$modifier_item_adapter = new ModifierItemAdapter($mimetypes);
		$options[TONIC_FIND_BY_METADATA]['modifier_group_id'] = $modifier_group_id;		
		$options[TONIC_SORT_BY_METADATA] = 'priority DESC, modifier_item_name';		
		$modifier_items = $modifier_item_adapter->select('',$options);
		return $modifier_items;
	}
	
	function getModifierGroups($menu_id, $active,$mimetypes)
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		$modifier_group_adapter = new ModifierGroupAdapter($mimetypes);
		$options[TONIC_FIND_BY_METADATA]['menu_id'] = $menu_id;		
		$options[TONIC_SORT_BY_METADATA] = 'priority DESC';		
		$modifier_groups = $modifier_group_adapter->select('',$options);
		foreach ( $modifier_groups as $modifier_group)
		{
			$this->full_modifier_group_list[$modifier_group['modifier_group_id']] = $modifier_group;
			if ($active == 'Y')
			{
				if ($modifier_group['active'] == 'Y')
					$result_set[] = $modifier_group;
			} else {
				$result_set[] = $modifier_group;
			}
		}
		return $result_set;
	}
	
	/**
	 * Returns a hash of arrays with modifier_group_id as the index.  Each modifier_group_id has an array of all the child Modifier_Item records
	 * 
	 * @param int $menu_id
	 */
	function getAllModifierItemsForMenu($menu_id,$active)
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		$modifier_item_adapter = new ModifierItemAdapter($mimetypes);
		$modifier_item_data = array();
		$modifier_item_options[TONIC_FIND_BY_METADATA] = $modifier_item_data;
		$modifier_item_options[TONIC_JOIN_STATEMENT] = " JOIN Modifier_Group ON Modifier_Item.modifier_group_id = Modifier_Group.modifier_group_id ";
		$modifier_item_options[TONIC_FIND_BY_STATIC_METADATA] = " Modifier_Group.menu_id = $menu_id AND Modifier_Group.logical_delete = 'N' ";
		$modifier_item_options[TONIC_SORT_BY_METADATA] = ' Modifier_Item.priority DESC, Modifier_Item.modifier_item_name ';
		$modifier_items = $modifier_item_adapter->select(null,$modifier_item_options);
		foreach ($modifier_items as $modifier_item_record)
		{
			unset($modifier_item_record['logical_delete']);
			unset($modifier_item_record['created']);
			unset($modifier_item_record['modified']);
			unset($modifier_item_record['external_modifier_item_id']);
			unset($modifier_item_record['modifier_item_print_name']);
			unset($modifier_item_record['merchant_id']);
			$modifier_group_id = $modifier_item_record['modifier_group_id'];
			$all_modifier_items[$modifier_group_id][] = $modifier_item_record;
		}
		return $all_modifier_items;
	}
	
	/**
	 * 
	 * Returns a hash of arrays with modifier_item_id as the index.  Each modifier_item_id has an array of the all the child price records.
	 * 
	 * @param int $menu_id
	 * @param char $active
	 * @param unknown_type $mimetypes
	 * @param int $merchant_id
	 * @param boolean $use_default_price_list
	 */
	function getAllModifierItemPricesForMenu($menu_id,$active,$mimetypes,$merchant_id = 0,$use_default_price_list = false)
	{	
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);	
		$modifier_size_map_adapter = new ModifierSizeMapAdapter($mimetypes);
		$all_modifier_size_prices = array();
		// menu version 3.0 will use merchant id on the price records
		if ($use_default_price_list) {
			$merchant_id = 0;
		}
		$modifier_item_size_options = $this->getModifierItemSizeOptions($menu_id, $merchant_id);
		$modifier_sizeprices = $modifier_size_map_adapter->select('',$modifier_item_size_options);
		foreach ($modifier_sizeprices as $modifier_size_price_record)
		{
			if ($active == 'Y' && $modifier_size_price_record['active'] == 'N')
				continue;
			unset($modifier_size_price_record['logical_delete']);
			unset($modifier_size_price_record['created']);
			unset($modifier_size_price_record['modified']);
			//unset($modifier_size_price_record['external_id']);
			$modifier_item_id = $modifier_size_price_record['modifier_item_id'];
			$all_modifier_size_prices[$modifier_item_id][] = $modifier_size_price_record;
		}		
		return $all_modifier_size_prices;
		
	}

	function getModifierItemsPrices($menu_id,$active,$mimetypes,$merchant_id = 0,$use_default_price_list = false,$ltos = array())
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		$themods = array();
		// now setting the only get active to N ( not to be  confused with active='N'
		//$modifier_groups = $this->getModifierGroups($menu_id,'Y',$mimetypes);
		$modifier_groups = $this->getModifierGroups($menu_id,'X',$mimetypes);
		
		$all_modifier_items = $this->getAllModifierItemsForMenu($menu_id,$active);
		$all_modifier_item_prices = $this->getAllModifierItemPricesForMenu($menu_id, $active, $mimetypes,$merchant_id,$use_default_price_list);
		
		$better_modifier_group_array = array();
		
		foreach ($modifier_groups as &$modifier_group)
		{
			unset($modifier_group['logical_delete']);
			unset($modifier_group['modifier_description']);
			unset($modifier_group['created']);
			unset($modifier_group['modified']);
			unset($modifier_group['external_modifier_group_id']);

			$modifier_group_names[$modifier_group['modifier_group_id']] = $modifier_group['modifier_group_name'];
			myerror_logging(4,"looping with modifier: ".$modifier_group['modifier_group_name']);
			$modifier_items = $all_modifier_items[$modifier_group['modifier_group_id']];

			$mod_items = $this->formatBetterModifierItemArray($modifier_items,$all_modifier_item_prices);
			// check to see if we actually have prices and add it to the returned array if we do.
			if (sizeof($mod_items)>0)
			{
				$modifier_group['modifier_items'] = $mod_items;
				if ($modifier_group['active'] == 'Y')
					$better_modifier_group_array[] =$modifier_group;
				$this->available_modifier_groups[$modifier_group['modifier_group_id']]=$modifier_group;
			} else if ($modifier_group['modifier_type'] == 'M') {
				$better_modifier_group_array[] =$modifier_group;
				$this->available_modifier_groups[$modifier_group['modifier_group_id']]=$modifier_group;
			}
		}
		return $better_modifier_group_array;
	}

    /**
     * @desc will create the complete modifier groups with their respected items. if we are calling APIv2 it will correctly
     * @desc organize the nested modifiers if they are present
     * @param $modifier_items
     * @param $all_modifier_item_prices
     * @return array
     */
    function formatBetterModifierItemArray($modifier_items,$all_modifier_item_prices)
    {
        $better_modifier_items_hash = array();
        // do somethign here to get array (with nested if necessary) cause this is where we do it.
        // then when the $this->available_modifier_groups is refereneced it will already be in teh correct format
        foreach ($modifier_items as $modifier_item) {
            $modifier_name = $modifier_item['modifier_item_name'];
            myerror_logging(4,"modifier item: ".$modifier_item['modifier_item_name']);

            $modifier_item_id = $modifier_item['modifier_item_id'];
            if ($modifier_prices = $all_modifier_item_prices[$modifier_item_id]) {
                $modifier_item['modifier_size_maps'] = $this->formatBetterModifierItemSizePriceArray($modifier_prices);
                $m = explode("=",$modifier_item['modifier_item_name']);
                if ($this->api_version > 1 && isset($m[1])) {
                    $modifier_item['modifier_item_name'] = $m[1];
                    if ($better_modifier_items_hash[$m[0]]) {
                        // now just add the nested item
                        $better_modifier_items_hash[$m[0]]['nested_items'][] = $modifier_item;
                    } else {
                        //create the parent item
                        $parent_item = array();
                        $parent_item['modifier_item_name'] = $m[0];
                        $parent_item['priority'] = $modifier_item['priority'];
                        $parent_item['nested_items'][] = $modifier_item;
                        $better_modifier_items_hash[$m[0]] = $parent_item;
                    }
                } else {
                    $better_modifier_items_hash[$modifier_item['modifier_item_name']] = $modifier_item;
                }
            }
        }
        foreach ($better_modifier_items_hash as $the_modifier_item) {
            $mod_items[] = $the_modifier_item;//$mod_items[$modifier_item['modifier_item_id']] = $modifier_item;
        }
        return $mod_items;
    }

    function formatBetterModifierItemSizePriceArray($modifier_prices)
    {
        $mod_sizeprices = array();
        foreach ($modifier_prices as &$modifier_price) {
            myerror_logging(4,"modifier item price: ".$modifier_price['modifier_price']."  active: ".$modifier_price['active']);
            // had to reduce to precision of 2 becuase 3 was screwing up phone.  3 was needed for stupid lennys combo price splits.
            if ($modifier_price['modifier_price'] == null)
                $modifier_price['modifier_price'] = 0.00;
            else
                $modifier_price['modifier_price'] = sprintf("%01.2f", $modifier_price['modifier_price']);
            //myerror_log("mod_price: ".$modifier_price['modifier_price']);
            $mod_sizeprices[]=$modifier_price;  //$mod_sizeprices[$modifier_price['modifier_size_id']]=$modifier_price;
        }
        return $mod_sizeprices;
    }

    function getUpsellItemIdsAsArray($menu_id,$active)
	{
		$return_array = array();
		$upsell_items_records = $this->getMenuUpsellItemMaps($menu_id, $active);
		foreach ($upsell_items_records as $upsell_item_record) {
			$return_array[] = $upsell_item_record['item_id'];
		}
		return $return_array;
	}
	
	function getMenuUpsellItemMaps($menu_id,$active)
	{
		$menu_id = $this->getMenuIdForMethodCalls($menu_id);
		$menu_upsell_items_maps_adapter = new MenuUpsellItemMapsAdapter($mimetypes);
		return $menu_upsell_items_maps_adapter->getUpsellItemsForMenu($menu_id, $active);
	}

    function getAvailableModifierGroups()
    {
        return $this->available_modifier_groups;
    }
	
	private function getMenuIdForMethodCalls($id)
	{
		$menu_id = ($id) ? $id : $this->menu_id;
		if ($menu_id) {
			return $menu_id;
		} 
		throw new Exception("No menu_id submitted or set", 999);
	}

	function getFullItemList()
    {
        return $this->full_item_list;
    }
}

?>