<?php

class OpenMenuStatusAdapter extends MySQLAdapter
{
	private $open_menu_data;
	private $resource;
	
	function OpenMenuStatusAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Open_Menu_Status',
			NULL,
			'%d',
			array('open_menu_id'),
			NULL,
			array('modified','created')
			);
			
		$this->allow_full_table_scan = true;
			
	}
	
	static function staticCheckAndUpdateOpenMenuMerchants()
	{
		$omsa = new OpenMenuStatusAdapter($mimetypes);
		$omsa->checkAndUpdateOpenMenuMerchants();
		// since this is called from an activity it needs to return true.
		return true;
	}
	
	function checkAndUpdateOpenMenuMerchants()
	{
		// cant do full table scans anymore so have to set some value
		$options[TONIC_FIND_BY_METADATA]['last_update'] = array('>'=>"0");
		if ($records = Resource::findALL($this,null))
		{
			myerror_log("we found ".sizeof($records)." open menu records so lets check the status");
			foreach ($records as $open_menu_merchant_status_record_resource)
			{
				$this->resource = $open_menu_merchant_status_record_resource;
				// check last updated against open menu
				if (! $this->isMerchantCurrent($open_menu_merchant_status_record_resource->open_menu_id, $open_menu_merchant_status_record_resource->last_updated))
				{
					// run the import
					myerror_log("merchant is UN-current, run the import");
					$this->openMenuImport($open_menu_merchant_status_record_resource->open_menu_id);
				} else {
					myerror_log("merchant is current");
				}
			}
			
			$this->open_menu_data = null;
			$this->resource = null;
		} else {
			myerror_log("There are no open menu merchants to check status of");
		}
	}
	
	function isMerchantCurrent($open_menu_id,$last_updated_in_splickit)
	{
		$this->open_menu_data = null;
		$a = null;
		$mimetypes = $_SERVER['MIMETYPES'];
		$_SERVER['log_level'] = 5;
		$_SERVER['GLOBAL_PROPERTIES']['log_level'] = 5;
		$url = 'http://openmenu.com/menu/'.$open_menu_id.'&json';
		$curl = curl_init($url);
		myerror_log("about to curl with: ".$url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_VERBOSE, 0);
		$headers = array('Content-Type: text/xml', 'Accept-Charset: UTF-8');
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		$result = curl_exec($curl);
		myerror_log("we have returned from the curl");
		$a = json_decode($result);
		curl_close($curl);
		$this->open_menu_data = $a;
		$last_updated_open_menu = strtotime($a->omf_updated_timestamp);
		
		myerror_log("last updated from open menu is: ".$last_updated_open_menu);
		myerror_log("last updated in splickit db is: ".$last_updated_in_splickit);
		if ($last_updated_open_menu > $last_updated_in_splickit)
			return false;
		else
			return true;
	}
	
	function openMenuImport($id)
	{
		//error_reporting(E_ALL);
		if ($this->open_menu_data)
			$a = $this->open_menu_data;
		else
		{
			$mimetypes = $_SERVER['MIMETYPES'];
			$_SERVER['log_level'] = 5;
			$_SERVER['GLOBAL_PROPERTIES']['log_level'] = 5;
			//$curl = curl_init('http://openmenu.com/menu/3b16beb0-15bb-11e0-b40e-0018512e6b26&json');
		
			$url = 'http://openmenu.com/menu/'.$id.'&json';
			$curl = curl_init($url);
			myerror_log("about to curl with: ".$url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_VERBOSE, 0);
			$headers = array('Content-Type: text/xml', 'Accept-Charset: UTF-8');
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			if ($result = curl_exec($curl))
			{
				myerror_logging(3,'result: '.$result);
				$a = json_decode($result);
				curl_close($curl);
			} else {
				curl_close($curl);
				myerror_log("NO RESPONSE DATA FROM OPEN MENU. ABORTING IMPORT");
				//die("NO RESPONSE DATA FROM OPEN MENU. ABORTING IMPORT");
				return false;
			}
		}
		
		$last_updated = strtotime($a->omf_updated_timestamp);
		
		if ($this->resource)
			;// all is good we already have the resource
		else 
		{
			$open_menu_options[TONIC_FIND_BY_METADATA]['open_menu_id'] = $id;
			$open_menu_status_adapter = new OpenMenuStatusAdapter($mimetypes);
			if ($open_menu_status_resource = Resource::findExact($open_menu_status_adapter,null,$open_menu_options))
			{
				// record exists so check the timestamp
				myerror_log("last updated from open menu is: ".$last_updated);
				myerror_log("last updated in splickit db is: ".$open_menu_status_resource->last_updated);
				if ($last_updated > $open_menu_status_resource->last_updated)
					myerror_log("ther is new data so proceede with import");
				else
					return false;
					
					//die ("no new data.  import has been killed");
			} else {	
				// create the record with dummy last update
				$open_menu_data['open_menu_id'] = $id;
				$open_menu_data['last_updated'] = 1;
				$open_menu_status_resource = Resource::factory($open_menu_status_adapter,$open_menu_data);
				$open_menu_status_resource->save();	
			}
			$this->resource = $open_menu_status_resource;
		}
		$merchant_external_id = $a->omf_uuid;
		
		$restaurant_info = $a->restaurant_info;
		$res_info_string = print_r($restaurant_info, true);
		error_log('result: '.$res_info_string);
		
		$the_name = $restaurant_info->restaurant_name;
		
		// create the brand and merchant
		$brand_adapter = new BrandAdapter($mimetypes);
		$brand_data['brand_name'] = $the_name;
		$brand_data['brand_external_identifier'] = $id;
		
		$options[TONIC_FIND_BY_METADATA] = $brand_data;
		if ($resource = Resource::findExact($brand_adapter,'',$options))
			$brand_id = $resource->brand_id;
		else
			$brand_id = $this->insertIt($brand_adapter, $brand_data);
			
		$merchant_adapter = new MerchantAdapter($mimetypes);
		$merchant_data['name'] = $the_name;
		$merchant_data['brand_id'] = $brand_id;
		$merchant_data['merchant_external_id'] = $merchant_external_id;
		$options_m[TONIC_FIND_BY_METADATA] = $merchant_data;
		if ($resource = Resource::findExact($merchant_adapter,'',$options_m))
		{
			$merchant_id = $resource->merchant_id;
			$shop_email = $resource->shop_email;
			$fax_no = $resource->fax_no;
			$new_merchant = false;
		}
		else
		{
			$new_merchant = true;
		/*	$contacts = $a->contacts;
			$contacts = array();
			if (isset($contacts_sub['@attributes']))
				$contacts[0] = $contacts_sub;
			else
				$contacts = $contacts_sub;
		*/	foreach ($a->contacts as $contact)
			{
				if ($contact->contact_type == 'primary')
					$shop_email = $contact->email;
				else
					$admin_emails[] = $contact->email;
				$admin_emails[] = $shop_email;
			}
			
			// WE'LL CREATE A OPEN MENU merchant_user_id

			$merchant_data['merchant_external_id'] = $merchant_external_id;
			$merchant_data['shop_email'] = $shop_email;
			$merchant_data['display_name'] = $name;
			$merchant_data['address1'] = $restaurant_info->address_1;
			$merchant_data['address2'] = $restaurant_info->address_2;
			$merchant_data['city'] = $restaurant_info->city_town;
			$merchant_data['state'] = $restaurant_info->state_province;
			$merchant_data['zip'] = ''.$restaurant_info->postal_code.'';
			$merchant_data['country'] = 'US'; 
			$merchant_data['lat'] = $restaurant_info->latitude;
			$merchant_data['lng'] = $restaurant_info->longitude;
			$merchant_data['phone_no'] = $restaurant_info->phone;
			$merchant_data['fax_no'] = $restaurant_info->fax;
			//if (( ! isset($restaurant_info->fax)) || $restaurant_info->fax == null || (isset($restaurant_info->fax) && trim($restaurant_info->fax) == '') )
			//	die("ERROR! Restaurant needs a valid fax number for import");
			$fax_no = $restaurant_info->fax;
			$merchant_data['time_zone'] = $restaurant_info->utc_offset;
			$merchant_data['trans_fee_type'] = 'F'; 
			$merchant_data['trans_fee_rate'] = 0.25;
			$merchant_data['show_tip'] = 'Y'; 
			$merchant_data['active'] = 'N'; 
			$merchant_data['lead_time'] = 20; 
			$resource = Resource::factory($merchant_adapter,$merchant_data);
			if ($resource->save())
				$merchant_id = $resource->merchant_id;
			else
			{
				die("ERROR! couldn't create merchant");
			} 
		}
		
		// hours
		$operating_days = $a->operating_days;
		
		$day_array = array('sun','mon','tue','wed','thu','fri','sat');
		
		foreach ($day_array as $index=>$value)
		{
			$day_string_open = $value.'_open_time';
			$day_string_close = $value.'_close_time';
			$hour_adapter = new HourAdapter($mimetypes);
			$hour_data['merchant_id'] = $merchant_id;
			$hour_data['hour_type'] = 'R';
			$hour_data['day_of_week'] = $index+1;
			$options_h[TONIC_FIND_BY_METADATA] = $hour_data;
			if ($hour_resource = Resource::find($hour_adapter,'',$options_h))
			{
				$hour_resource->open = $operating_days->$day_string_open;
				$hour_resource->close = $operating_days->$day_string_close;
				$hour_resource->save();
			} else {
				$hour_data['day_of_week'] = $index+1;
				$hour_data['open'] = $operating_days->$day_string_open;
				$hour_data['close'] = $operating_days->$day_string_close;
				$hour_resource = Resource::factory($hour_adapter,$hour_data);
				$hour_resource->save();
			}
			
			$hour_adapter = new HourAdapter($mimetypes);
			$dhour_data['merchant_id'] = $merchant_id;
			$dhour_data['hour_type'] = 'D';
			$dhour_data['day_of_week'] = $index+1;
			$options_h[TONIC_FIND_BY_METADATA] = $dhour_data;
			if ($hour_resource = Resource::find($hour_adapter,'',$options_h))
			{
				$hour_resource->open = $operating_days->$day_string_open;
				$hour_resource->close = $operating_days->$day_string_close;
				$hour_resource->save();
			} else {
				$dhour_data['day_of_week'] = $index+1;
				$dhour_data['open'] = $operating_days->$day_string_open;
				$dhour_data['close'] = $operating_days->$day_string_close;
				$hour_resource = Resource::factory($hour_adapter,$dhour_data);
				$hour_resource->save();
			}
		}

		$merchant_controller = new MerchantController($mimetypes, null, null);
		$merchant_controller->setMerchantResource($resource);
	
		if ($new_merchant)
		{
			
			$merchant_controller->stubOutMerchantTax($merchant_id);	
			$merchant_controller->stubOutMerchantSkinMap($merchant_id);
			$merchant_controller->stubOutMerchantMessageMap($merchant_id, $shop_email, $fax_no);
			$merchant_controller->stubOutMerchantDelivery($merchant_id);
			$merchant_controller->stubOutMerchantPaymentType($merchant_id,'cash');
		
			//Holiday
			$merchant_controller->stubOutMerchantHoliday($merchant_id);
			
			//ACH
			$merchant_controller->stubOutMerchantACH($merchant_id);
			
			//ZZUser
			$merchant_controller->stubOutMerchantZZUser($merchant_id, $merchant_external_id,$the_name);
				
			$ts = time();
			foreach ($admin_emails as $email_address)
			{
				$sql = "INSERT INTO adm_merchant_email (merchant_id,email,daily,weekly,admin,created) VALUES ($merchant_id,'$email_address', 'Y','Y','Y',$ts)";	
				$merchant_adapter->_query($sql);
			}
			
			$merchant_controller->sendWelcomeLetter($merchant_id);
		
		}	
		$menus_all = array();
		
		$mysql_adapater = new MySQLAdapter($mimetypes);
		$mysql_adapater->_query('START TRANSACTION');
		$mysql_adapater->_query('BEGIN');
		
		$code = date('his');
		
		$menu_reset_list = array();
		
		try {
			$menu_priority = 20000;
		 	$menus = $a->menus;
		 	$size_of_menus = sizeof($menus);
		 	if ($size_of_menus < 1)
		 		die("NO MENUS ARE ATTACHED TO THIS open menu ID: ".$id);
			foreach ($menus as $menu)
			{
	
					$menu_name = $menu->menu_name;
					$merchant_menu_type = 'pickup';
					if (strtolower($menu_name) == 'delivery')
						$merchant_menu_type = 'delivery';
						
					// so basically we need to create the pickup menu if it hasn't been created yet or create the delivery menu if it hasn't been created yet.	
					$menu_adapter = new MenuAdapter($mimetypes);
					$menu_data = array();
					$menu_data['name'] = $the_name;
					$menu_data['external_menu_id'] = 'open_menu-'.$id.'-'.$merchant_menu_type;
					$options_menu = array();
					$options_menu[TONIC_FIND_BY_METADATA] = $menu_data;

					if ($menu_resource = Resource::findExact($menu_adapter,'',$options_menu))
					{
						$menu_id = $menu_resource->menu_id;
						$menu_resource->last_menu_change = time();
						$menu_resource->save();
					}
					else
					{
						// create the menu id
						$menu_data['description'] = $merchant_menu_type;
						$menu_data['last_menu_change'] = time();
						$menu_data['version'] = 2.0;
						$menu_data['created'] = time();
						$menu_id = $this->insertIt($menu_adapter, $menu_data);
						$menus_all[$merchant_menu_type] = $menu_id;
					}
						
					if ($merchant_menu_type == 'pickup')
						$pickup_id = $menu_id;
					else if ($merchant_menu_type == 'delivery')
						$delivery_id = $menu_id;
					
					if ($menu_reset_list[$menu_id])
						myerror_log("menu prices have already been reset"); // do nothing we already set all prices to innactive
					else 
					{
						$item_prices_deactivate_sql = "UPDATE Modifier_Size_Map JOIN Modifier_Item ON Modifier_Item.modifier_item_id = Modifier_Size_Map.modifier_item_id JOIN Modifier_Group ON Modifier_Group.modifier_group_id = Modifier_Item.modifier_group_id SET Modifier_Size_Map.active = 'N' WHERE Modifier_Group.menu_id = $menu_id";
						$modifier_prices_deactivate_sql = "UPDATE Item_Size_Map JOIN Item ON Item.item_id = Item_Size_Map.item_id JOIN Menu_Type ON Menu_Type.menu_type_id = Item.menu_type_id SET Item_Size_Map.active = 'N' WHERE Menu_Type.menu_id = $menu_id";
						
						myerror_log("about to run item prices deactivate: ".$item_prices_deactivate_sql);
						$menu_adapter->_query($item_prices_deactivate_sql);
						myerror_log("about to run modifier prices deactivate: ".$modifier_prices_deactivate_sql);
						$menu_adapter->_query($modifier_prices_deactivate_sql);
						
						$menu_reset_list[$menu_id] = true;
						
					}	

					//menu durations are hours for the menu type.  MUST BE SET!  this is NOT the R and D values.
					if (isset($menu->menu_duration_time_start) && isset($menu->menu_duration_time_end))
					{
						$menu_type_start_time = $menu->menu_duration_time_start;
						$menu_type_end_time = $menu->menu_duration_time_end;
					} else {
						$menu_type_start_time = '00:00:01';
						$menu_type_end_time = '23:59:59';
					}
					
					//eg: salads, appitizers, sandwiches, etc....
					$menu_type_priority = 200;
					$size_priority = 100;
					$menu_type_sizes_all[] = array();
					$menu_types = array();
					foreach ($menu->menu_groups as $menu_type)
					{
							$menu_type_adapter = new MenuTypeAdapter($mimetypes);
							$menu_type_data = array();
							$menu_type_name = $menu_type->group_name;
							if ($size_of_menus > 1)
								$menu_type_name = $menu_name.'-'.$menu_type->group_name;
							$menu_type_external_id = $menu_type->group_uid;
							$menu_type_data['external_menu_type_id'] = $menu_type_external_id;
							$menu_type_data['menu_id'] = $menu_id;
							$options_menu_type = array();
							$options_menu_type[TONIC_FIND_BY_METADATA] = $menu_type_data;

							if (isset($menu_type->group_description))
								$menu_type_description = $menu_type->group_description;
							else
								$menu_type_description = $menu_type_name;
								
							//detemine if it exists already
							
							if ($menu_type_resource = Resource::findExact($menu_type_adapter,'',$options_menu_type))
							{
								$menu_type_id = $menu_type_resource->menu_type_id;
								myerror_log("have retrieved existing menu type id of: ".$menu_type_id);
								$menu_type_resource->menu_type_name = $menu_type_name;
								$menu_type_resource->menu_type_description = $menu_type_description;
								$menu_type_resource->start_time = $menu_type_start_time;
								$menu_type_resource->end_time = $menu_type_end_time;
								$menu_type_resource->save();
								myerror_log("have updated the data");
							}
							else
							{
								$menu_type_data['menu_type_name']=$menu_type_name;
								$menu_type_data['menu_type_description']=$menu_type_description;
								$menu_type_data['priority']=$menu_priority + $menu_type_priority;
								$menu_type_data['start_time'] = $menu_type_start_time;
								$menu_type_data['end_time'] = $menu_type_end_time;
								$menu_type_data['cat_id'] = 'E';
								
								// create menu type
								$menu_type_id = $this->insertIt($menu_type_adapter,$menu_type_data);
								myerror_log("created new menu type id: ".$menu_type_id);
							}
							
							//create default size for this menu type (will be done for all menu types from open menu)
							$size_adapter = new SizeAdapter($mimetypes);
							$size_data = array();	
							$size_data['menu_type_id'] = $menu_type_id;
							$size_data['external_size_id'] = $menu_type_external_id.'-onesize';
							$options_base_size = array();
							$options_base_size[TONIC_FIND_BY_METADATA] = $size_data;
							if ($base_size_resource = Resource::findExact($size_adapter,'',$options_base_size))
							{
								$menu_type_base_size_id = $base_size_resource->size_id;
								myerror_log("have retrieved existing base size if of: ".$menu_type_base_size_id);
							}
							else
							{
								$size_data['size_name'] = $menu_type_name;
								$size_data['size_print_name'] = 'one size';
								$size_data['size_description'] = $menu_type_name;
								$size_data['priority'] = $size_priority;
								$size_priority = $size_priority - 10;
								$menu_type_base_size_id = $this->insertIt($size_adapter, $size_data);
								myerror_log("created new base size id: ".$menu_type_base_size_id);
							}
							$menu_type_sizes_all[$size_name] = $menu_type_base_size_id;

							$allowed_modifier_groups = array();
							$modifier_group_priority = 200;
							foreach ($menu_type->menu_group_options as $modifier_group)
							{
								$modifier_group_name = $modifier_group->group_options_name;
								$modifier_group_adapter = new ModifierGroupAdapter($mimetypes);
								$modifier_group_data = array();
								$modifier_group_data['menu_id'] = $menu_id;
								$modifier_group_data['modifier_group_name'] = $modifier_group_name;
								$modifier_group_data['external_modifier_group_id'] = $menu_type_external_id.'-'.$modifier_group_name;
								$options_modifier_group = array();
								$options_modifier_group[TONIC_FIND_BY_METADATA] = $modifier_group_data;
								if ($modifier_group_resource = Resource::findExact($modifier_group_adapter,null,$options_modifier_group) )
								{
									myerror_log("have retrieved existing modifier group id: ".$modifier_group_id);
									$modifier_group_resource->active = 'Y';
									$modifier_group_resource->priority = $modifier_group_priority;
									$modifier_group_resource->save();
									$modifier_group_id = $modifier_group_resource->modifier_group_id;
									$item_modifier_group_priority = $item_modifier_group_priority - 10;
									
									//now add the existing fields to the $modifier_group_data so they can added later in the item mod group map
									$modifier_group_data = $modifier_group_resource->getDataFieldsReally();

								} else {	
									$modifier_group_data['modifier_group_name'] = $modifier_group_name;
									$modifier_group_data['modifier_group_description'] = $modifier_group_name;
									$modifier_group_data['modifier_type'] = 'T';
									$modifier_group_data['active'] = 'Y';
									$modifier_group_data['priority'] = $modifier_group_priority;
									$modifier_group_id = $this->insertIt($modifier_group_adapter, $modifier_group_data);
									myerror_log("created new modifier group id: ".$modifier_group_id);
								}
								
								if (isset($modifier_group->menu_group_option_min_selected))
									$group_min = $modifier_group->menu_group_option_min_selected;
								else
									$group_min = 0;
								
								if (isset($modifier_group->menu_group_option_max_selected))
									$group_max = $modifier_group->menu_group_option_max_selected;
								else
									$group_max = 1;
									
								$modifier_group_data['min'] = $group_min;
								$modifier_group_data['max'] = $group_max;
								
								// now add the id into the data set so we can add it to the Item_Modifier_Group_Map
								$modifier_group_data['modifier_group_id'] = $modifier_group_id;
								$allowed_modifier_groups[] = $modifier_group_data;
							
								$modifier_group_priority = $modifier_group_priority - 10;
								
								$modifier_item_priority = 200;
								foreach ($modifier_group->option_items as $modifier_item_record)
								{
									$modifier_item_name = $modifier_item_record->menu_group_option_name;
									$modifier_item_adapter = new ModifierItemAdapter($mimetypes);
									$modifier_item_data = array();
									$modifier_item_data['external_modifier_item_id'] = $menu_type_external_id.'-'.$modifier_group_name.'-'.$modifier_item_name;
									$modifier_item_data['modifier_group_id'] = $modifier_group_id;
									//nmeed to get existing here
									$options_modifier_item = array();
									$options_modifier_item[TONIC_FIND_BY_METADATA] = $modifier_item_data;
									if ($modifier_item_resource = Resource::findExact($modifier_item_adapter,null,$options_modifier_item))
									{
										$modifier_item_id = $modifier_item_resource->modifier_item_id;
										myerror_log("we have retrieved the existing modifier item: ".$modifier_item_id);

										// make any necessary updates
										$modifier_item_resource->modifier_item_name = $modifier_item_name;
										$modifier_item_resource->modifier_item_print_name = $modifier_item_name;
										$modifier_item_resource->save();										
									} else {
										$modifier_item_data['modifier_item_name'] = $modifier_item_name;
										$modifier_item_data['modifier_item_print_name'] = $modifier_item_name;
										$modifier_item_data['modifier_item_max'] = 1;
										$modifier_item_data['priority'] = $modifier_item_priority;
										$modifier_item_priority = $modifier_item_priority - 10;
										$modifier_item_id = $this->insertIt($modifier_item_adapter, $modifier_item_data);
									}
									
									$modifier_size_map_adapter = new ModifierSizeMapAdapter($mimetypes);
									//$mod_price_data['merchant_id'] = 0; // all open menu is version 2.0 
									$mod_price_data = array();
									$mod_price_data['external_id'] = $modifier_item_data['external_modifier_item_id'].'-0';
									$mod_price_data['modifier_item_id'] = $modifier_item_id;
									//get existing
									$options_modifier_item_size = array();
									$options_modifier_item_size[TONIC_FIND_BY_METADATA] = $mod_price_data;
									if ($modifier_item_size_resource = Resource::findExact($modifier_size_map_adapter,null,$options_modifier_item_size))
									{
										$modifier_item_size_resource->active = 'Y';
										$modifier_item_size_resource->modifier_price = $modifier_item_record->menu_group_option_additional_cost;									
										if ($modifier_item_size_resource->save())
											$modifier_item_size_id = $modifier_item_size_resource->modifier_size_id;
										else
										{
											if (mysql_errno() == 0)
												$modifier_item_size_id = $modifier_item_size_resource->modifier_size_id;
											else 
											{
												myerror_log("ERROR! updateing modifiier_item_size_price: ".mysql_error());
												throw new Exception("ERROR! modifier_size_price: ".mysql_error(), mysql_errno());
											}
										}
									} else {
										//$mod_price_data['modifier_item_id'] = $modifier_item_id;
										// open menu does not have a concept of modifier sizes for price
										// so we'll skip it and let the db default to 0
										//$mod_price_data['size_id'] = "0"; 
										$mod_price_data['modifier_price'] = $modifier_item_record->menu_group_option_additional_cost;
										$mod_price_data['active'] = "Y";
										$mod_price_data['priority'] = $modifier_item_priority;
										$modifier_item_size_id = $this->insertIt($modifier_size_map_adapter, $mod_price_data);								
									}								
								}
							}
							$item_priority = 200;
							$items_all[] = array();
							foreach ($menu_type->menu_items as $item)
							{
								$item_name = $item->menu_item_name;
								$item_allowed_modifier_groups = array();
								if ($allowed_modifier_groups)
									$item_allowed_modifier_groups =  $allowed_modifier_groups;
	
								$calories = $item->menu_item_calories;
								
								$external_uid = $item->item_uid;
								myerror_log("about to load the menu item: ".$item->menu_item_name."  with external id of: ".$external_uid);
								$item_adapter = new ItemAdapter($mimetypes);
								$item_data = array();
								$item_data['menu_type_id'] = $menu_type_id;
								$item_data['external_item_id'] = $external_uid;
								
								$options_item = array();
								$options_item[TONIC_FIND_BY_METADATA] = $item_data;
								if ($item_resource = Resource::findExact($item_adapter,null,$options_item)   )
								{
									$item_resource->active = 'Y';
									$item_resource->item_name = $item->menu_item_name;
									$item_resource->item_print_name = $item->menu_item_name;
									$item_resource->description = $item->menu_item_description;
									$item_resource->save();
									$item_id = $item_resource->item_id;
									$item_priority = $item_priority - 10;
								} else {
									$item_data['tax_group'] = 1;
									$item_data['item_name'] = $item->menu_item_name;
									$item_data['item_print_name'] = $item->menu_item_name;
									$item_data['description'] = $item->menu_item_description;
									$item_data['active'] = "Y";
									$item_data['priority'] = $item_priority;
									$item_priority = $item_priority - 10;
									$item_id = $this->insertIt($item_adapter, $item_data);
								}
	
								// determine if there are modifier groups that are specific to this item.
								$menu_item_options = $item->menu_item_options;
								$item_modifier_group_priority = 400;
								foreach ($menu_item_options as $item_modifier_group)
								{
	//**********************************************************************************************************									
									
									$modifier_group_name = $item_modifier_group->item_options_name;
									$modifier_group_adapter = new ModifierGroupAdapter($mimetypes);
									$modifier_group_data = array();
									$modifier_group_data['menu_id'] = $menu_id;
									$modifier_group_data['external_modifier_group_id'] = $menu_type_external_id.'-'.$item_name.'-'.$modifier_group_name;
									$options_modifier_group = array();
									$options_modifier_group[TONIC_FIND_BY_METADATA] = $modifier_group_data;
									if ($modifier_group_resource = Resource::findExact($modifier_group_adapter,null,$options_modifier_group) )
									{
										$modifier_group_resource->active = 'Y';
										$modifier_group_resource->modifier_group_name = $modifier_group_name;
										$modifier_group_resource->modifier_group_description = $modifier_group_name;
										$modifier_group_resource->save();
										$modifier_group_id = $modifier_group_resource->modifier_group_id;
										$item_modifier_group_priority = $item_modifier_group_priority - 10;
										$modifier_group_data = $modifier_group_resource->getDataFieldsReally();
										myerror_log("have retrieved existing modifier group id: ".$modifier_group_id);
									} else {	
										$modifier_group_data['modifier_group_name'] = $modifier_group_name;
										$modifier_group_data['modifier_group_description'] = $modifier_group_name;
										$modifier_group_data['modifier_type'] = 'T';
										$modifier_group_data['active'] = 'Y';
										$modifier_group_data['priority'] = $item_modifier_group_priority;
										$modifier_group_id = $this->insertIt($modifier_group_adapter, $modifier_group_data);
										// now add the id into the data set so we can add it to the Item_Modifier_Group_Map
										$modifier_group_data['modifier_group_id'] = $modifier_group_id;
									
										$item_modifier_group_priority = $item_modifier_group_priority - 10;
										myerror_log("created new modifier group id: ".$modifier_group_id);
									}
									
									if (isset($item_modifier_group->menu_item_option_min_selected))
										$group_min = $item_modifier_group->menu_item_option_min_selected;
									else
										$group_min = 0;
									
									if (isset($item_modifier_group->menu_item_option_max_selected))
										$group_max = $item_modifier_group->menu_item_option_max_selected;
									else
										$group_max = 1;
										
									$modifier_group_data['min'] = $group_min;	
									$modifier_group_data['max'] = $group_max;	
										
									$item_allowed_modifier_groups[] = $modifier_group_data;
								
									$item_modifier_group_priority = $item_modifier_group_priority - 10;
									
									$item_modifier_item_priority = 200;
									foreach ($item_modifier_group->option_items as $modifier_item_record)
									{
										
										$modifier_item_name = $modifier_item_record->menu_item_option_name;
										$modifier_item_adapter = new ModifierItemAdapter($mimetypes);
										$modifier_item_data = array();
										$modifier_item_data['external_modifier_item_id'] = $external_uid.'-'.$modifier_group_name.'-'.$modifier_item_name;
										$modifier_item_data['modifier_group_id'] = $modifier_group_id;
											
										//need to get existing here
										$options_modifier_item = array();
										$options_modifier_item[TONIC_FIND_BY_METADATA] = $modifier_item_data;
										if ($modifier_item_resource = Resource::findExact($modifier_item_adapter,null,$options_modifier_item))
										{
											$modifier_item_id = $modifier_item_resource->modifier_item_id;
											myerror_log("we have retrieved the existing modifier item: ".$modifier_item_id);

											// make any necessary updates
											$modifier_item_resource->modifier_item_name = $modifier_item_name;
											$modifier_item_resource->modifier_item_print_name = $modifier_item_name;
											$modifier_item_resource->save();										
											
											$item_modifier_item_priority = $item_modifier_item_priority - 10;	
										} else {
											$modifier_item_data['modifier_item_name'] = $modifier_item_name;
											$modifier_item_data['modifier_item_print_name'] = $modifier_item_name;
											$modifier_item_data['modifier_item_max'] = 1;
											$modifier_item_data['priority'] = $item_modifier_item_priority;
											$modifier_item_id = $this->insertIt($modifier_item_adapter, $modifier_item_data);
											$item_modifier_item_priority = $item_modifier_item_priority - 10;
										}
											
										if (isset($modifier_item_record->menu_item_option_additional_cost) && $modifier_item_record->menu_item_option_additional_cost > 0)
											$price = $modifier_item_record->menu_item_option_additional_cost;
										else
											$price = 0.00;
											
										$modifier_size_map_adapter = new ModifierSizeMapAdapter($mimetypes);
										//$mod_price_data['merchant_id'] = 0; // all open menu is version 2.0 
										$mod_price_data = array();
										$mod_price_data['external_id'] = $modifier_item_data['external_modifier_item_id'].'-0';
										$mod_price_data['modifier_item_id'] = $modifier_item_id;
										
										//get existing
										$options_modifier_item_size = array();
										$options_modifier_item_size[TONIC_FIND_BY_METADATA] = $mod_price_data;
										if ($modifier_item_size_resource = Resource::findExact($modifier_size_map_adapter,null,$options_modifier_item_size))
										{
											$modifier_item_size_resource->active = 'Y';
											$modifier_item_size_resource->modifier_price = $price;
											if ($modifier_item_size_resource->save())
												$modifier_item_size_id = $modifier_item_size_resource->modifier_size_id;
											else
											{
												if (mysql_errno() == 0)
													$modifier_item_size_id = $modifier_item_size_resource->modifier_size_id;
												else 
												{
													myerror_log("ERROR! updateing modifiier_item_size_price: ".mysql_error());
													throw new Exception("ERROR! modifier_size_price: ".mysql_error(), mysql_errno());
												}
											}
										} else {
											
											// open menu does not have a concept of modifier sizes for price
											// so we'll skip it and let the db default to 0
											//$mod_price_data['size_id'] = "0"; 
											$mod_price_data['modifier_price'] = $price;
											$mod_price_data['active'] = "Y";
											$mod_price_data['priority'] = $modifier_item_priority;
											$modifier_item_size_id = $this->insertIt($modifier_size_map_adapter, $mod_price_data);								
										}								

									}

	//**********************************************************************************************************									
								}
								
								// now attach the modifier groups if any exist for this item
								
								// so for menu update vs delete and rebuild, i actually think we want to delete all these records and then re-insert them.
								// since this is a complete child reference removing and re-adding will not affect anything really.  
								if (sizeof($item_allowed_modifier_groups) > 0)
								{
									$imgm_adapter = new ItemModifierGroupMapAdapter($mimetypes);
									if ($item_id > 1000)
									{
										$sql = "DELETE FROM Item_Modifier_Group_Map WHERE item_id = $item_id";
										if ($imgm_adapter->_query($sql))
											myerror_log("we deleted all the item mod group maps");
										else
											myerror_log("ERROR! couldn't delete item mod group map records: ".mysql_error());	
									}
								
									foreach ($item_allowed_modifier_groups as $allowed_group)
									{
										foreach ($allowed_group as $name=>$value)
											myerror_log("$name = $value");
										//$imgm_data['merchant_id'] = "0"; // version 2.0
										$imgm_data['item_id'] = $item_id;
										$imgm_data['modifier_group_id'] = $allowed_group['modifier_group_id'];
										$imgm_data['display_name'] = $allowed_group['modifier_group_name'];
										$imgm_data['priority'] = $allowed_group['priority'];
										$imgm_data['min'] = $allowed_group['min'];
										$imgm_data['max'] = $allowed_group['max'];
										$this->insertIt($imgm_adapter, $imgm_data);							
									}
									
								}

								if ($menu_item_sizes = $item->menu_item_sizes)
								{
									$size_priority = 100;
									$item_size_priority = 100;
									//$menu_item_sizes = array_pop($menu_item_sizes);
									$size_adapter = new SizeAdapter($mimetypes);	
									
									foreach ($menu_item_sizes as $menu_item_size)
									{
										$size_name = $menu_item_size->menu_item_size_name;
										
										$size_data = array();
										$size_data['menu_type_id'] = $menu_type_id;
										$size_data['external_size_id'] = $menu_type_external_id.'-size-'.$size_name;
										
										$options_size = array();
										$options_size[TONIC_FIND_BY_METADATA] = $size_data;
										if ($size_resource = Resource::findExact($size_adapter,null,$options_size) )
										{
											$size_id = $size_resource->size_id;
											myerror_log("we have retrieved an existing size: ".$size_id);
										}
										else
										{									
											// new size for this menu type so enter it
											$size_data['menu_type_id'] = $menu_type_id;
											$size_data['external_size_id'] = $menu_type_external_id.'-size-'.$size_name;
											$size_data['size_name'] = $size_name;
											$size_data['size_print_name'] = $size_name;
											if (isset($menu_item_size->menu_item_size_description))
												$size_data['size_description'] = $menu_item_size->menu_item_size_description;
											else
												$size_data['size_description'] = $size_name;
											$size_data['priority'] = $size_priority;
											$size_id = $this->insertIt($size_adapter, $size_data);
											$menu_type_sizes_all[$size_name] = $size_id;
										}
										$size_priority = $size_priority - 10;
										
										$item_size_price_adapter = new ItemSizeAdapter($mimetypes);
										$isp_data = array();
										// cant use external id since a name change will crate a new row in the ISP table
										//$isp_data['external_id'] = $external_uid.'-'.$item_name.'-'.$size_name;
										$isp_data['item_id'] = $item_id;
										$isp_data['size_id'] = $size_id;
										$options_itemsize = array();
										$options_itemsize[TONIC_FIND_BY_METADATA] = $isp_data;
										if ($itemsize_resource = Resource::findExact($item_size_price_adapter,null,$options_itemsize))
										{
											$size_price_id = $itemsize_resource->item_size_id;
											//$itemsize_resource->item_id = $item_id;
											//$itemsize_resource->size_id = $size_id;
											$itemsize_resource->external_id = $external_uid.'-'.$item_name.'-'.$size_name;
											$itemsize_resource->price = $menu_item_size->menu_item_size_price;
											$itemsize_resource->active = 'Y';
											if ($itemsize_resource->save())
												$size_price_id = $itemsize_resource->item_size_id;
											else
											{
												if (mysql_errno() == 0)
													$size_price_id = $itemsize_resource->item_size_id;
												else
												{
													myerror_log("ERROR!  couldn't save item size map in open menu import".mysql_error());
													throw new Exception("couldn't save item size map in open menu import: ".mysql_error(), mysql_errno());
												}
											} 
										} else {
											$isp_data['merchant_id'] = "0";// could also skip because default this to zero;  we are 2.0
											$isp_data['item_id'] = $item_id;
											$isp_data['size_id'] = $size_id;
											$isp_data['price'] = $menu_item_size->menu_item_size_price;
											$isp_data['active'] = 'Y';
											$isp_data['priority'] = $item_size_priority;
											$size_price_id = $this->insertIt($item_size_price_adapter, $isp_data);
										}
										$item_size_priority = $item_size_priority - 10;
									}
								} else {
									$price = $item->menu_item_price;
									$item_size_price_adapter = new ItemSizeAdapter($mimetypes);
									
									$isp_data = array();
									$isp_data['external_id'] = $external_uid.'-'.$item_name.'-onesize';
									
									$options_itemsize = array();
									$options_itemsize[TONIC_FIND_BY_METADATA] = $isp_data;
									if ($itemsize_resource = Resource::findExact($item_size_price_adapter,null,$options_itemsize))
									{
										$itemsize_resource->item_id = $item_id;
										$itemsize_resource->size_id = $menu_type_base_size_id;
										$itemsize_resource->price = $price;
										$itemsize_resource->active = 'Y';
										if ($itemsize_resource->save())
											$size_price_id = $itemsize_resource->item_size_id;
										else
											if (mysql_errno() == 0)
												$size_price_id = $itemsize_resource->item_size_id;
											else
											{
												myerror_log("ERROR!  couldn't save item size map in open menu import".mysql_error());
												throw new Exception("couldn't save item size map in open menu import: ".mysql_error(), mysql_errno());
											} 
									} else {
									
										$isp_data['merchant_id'] = "0";// could also skip because default this to zero;  we are 2.0
										$isp_data['item_id'] = $item_id;
										$isp_data['size_id'] = $menu_type_base_size_id;
										$isp_data['price'] = $price;
										$isp_data['active'] = 'Y';
										$isp_data['priority'] = 100;
										$size_price_id = $this->insertIt($item_size_price_adapter, $isp_data);
									}
								} // end sizes and prices for items
							} // foreach looping through the list of items
							$menu_type_priority = $menu_type_priority - 10;
					} // foreach looping through all menu types
					$menu_priority = $menu_priority - 1000;
			}
			
			//$open_menu_status_resource->last_updated = $last_updated;
			//$open_menu_status_resource->save();

			$this->resource->last_updated = $last_updated;
			$this->resource->save();
			$mysql_adapater->_query('COMMIT');

			if ($pickup_id > 0)
			{
				$this->setMenuMap($merchant_id,'pickup',$pickup_id);
			}
			if ($delivery_id > 0)
			{
				$this->setMenuMap($merchant_id, 'delivery', $delivery_id);
			}
		} catch (Exception $e) {
			myerror_log("there was an error:  ".$e->getMessage());
			$mysql_adapater->_query("ROLLBACK");
		}
	}

	function setMenuMap($merchant_id,$menu_type,$menu_id)
	{
		$merchant_menu_map_adapter = new MerchantMenuMapAdapter($mimetypes);
		$mmm_data = array();
		$mmm_data['merchant_id'] = $merchant_id;
		$mmm_data['merchant_menu_type'] = $menu_type;
		$options_mmm[TONIC_FIND_BY_METADATA] = $mmm_data;
		if ($mmm_resource = Resource::findExact($merchant_menu_map_adapter,'',$options_mmm))
		{
			$mmm_resource->menu_id = $menu_id;
			$mmm_resource->modified = time();
			$mmm_resource->save();
		} else {
			$mmm_data['menu_id'] = $menu_id;
			$mmm_resource = Resource::factory($merchant_menu_map_adapter,$mmm_data);
			$mmm_resource->save();
		}			
	}

	function insertIt($adapter,$data)
	{
		$resource = new Resource($adapter,$data);
		$adapter->insert($resource);
		if ($insert_id = $adapter->_insertId())
			return $insert_id;
		else {
			$no_errors = false;
			$adapter->_query("ROLLBACK");
			myerror_log("WE HAVE ERRORS!  cannot import open menu merchant: ".mysql_error());
			//die("WE HAVE ERRORS!");
		}
	}

}
?>