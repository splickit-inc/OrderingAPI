<?php
class EditItemAdapter extends EditMenuObjectAdapter
{	
	function EditItemAdapter($mimetypes)
	{
		parent::__construct(
			$mimetypes,
			'Item',
			'%([0-9]{1,15})%',
			'%d',
			array('item_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
     	return parent::select($url,$options);
	}

	function insert(&$resource)
	{
		$this->setInstanceVaribles($resource);
		if (parent::insert($resource))
		{
			$id = $this->_insertId();
			$resource->set('new_item_id',$id);
			// now deal with the prices
			myerror_log("good insert");
			$this->priceSave($resource);
			$this->modifierGroupMapSave($resource);
			return true;
		}
		return false;
	}
	
	function update(&$resource)
	{
	    myerror_log("starting edit_item_adapter");
		$resource->set('modified',time());
		if (parent::update($resource))
		{
			// now deal with the prices
			myerror_log("good update");
			$this->priceSave($resource);
			myerror_log("about to do the IMGM save");
			$this->modifierGroupMapSave($resource);
			myerror_log("about to do the Item_Modifier_Item_Map save");
			$this->modifierItemMapSave($resource);
			
			return true;
		}
		return false;
	}
	
	private function modifierItemMapSave(&$resource)
	{
		$item_modifier_item_map_adapter = new ItemModifierItemMapAdapter($this->mimetypes);
		myerror_log("we are about to loop through the number of possible modifier items: ".$resource->number_of_possible_modifier_items);
		for ($i = 0; $i < $resource->number_of_possible_modifier_items; $i++)
		{
			$comes_with_string = $i."comes_with";
			$modifier_item_id_string = $i."modifier_item_id";
			$map_id_string = $i."item_modifier_item_map_id";
			$mod_item_min_string = $i."mod_item_min";
			
//			myerror_log("************");
//			myerror_log("locked string: ".$resource->$mod_item_min_string);
//			myerror_log("************");
			
			$fields = array();
			$fields['item_id'] = $resource->item_id;
			$fields['modifier_item_id'] = $resource->$modifier_item_id_string;
			$fields['mod_item_min'] = $resource->$mod_item_min_string;
			if ($map_id = $resource->$map_id_string) {
                $fields['map_id'] = $map_id;
            }
			if (strtolower($resource->$comes_with_string) == 'on') {
			    myerror_log("doing either the insert or update of IMIM");
				$item_modifier_item_map_resource = new Resource($item_modifier_item_map_adapter,$fields);
				if ($map_id == null) {
                    $item_modifier_item_map_adapter->insert($item_modifier_item_map_resource);
                } else {
                    $item_modifier_item_map_adapter->update($item_modifier_item_map_resource);
                }
			} else if (strtolower($resource->$comes_with_string) == 'off' && $map_id != null) {
				myerror_log("delete the mapping");
				$item_modifier_item_map_adapter->delete($map_id);    
			} else {
				myerror_log("do nothing",5);
			}
		}
		myerror_log("done saving comes with mods");
	}
	
	private function modifierGroupMapSave(&$resource)
	{
	    $submitted_merchant_id = $resource->merchant_id;
		$item_modifier_group_map_adapter = new ItemModifierGroupMapAdapter($this->mimetypes);
		if ($resource->copy_imgm_from_item_id > 0 ) {
		    $source_item_id = $resource->copy_imgm_from_item_id;
		    $new_item_id = $resource->new_item_id;
		    $options[TONIC_FIND_BY_METADATA]['item_id'] = $source_item_id;
		    if ($imgm_resources = Resource::findAll($item_modifier_group_map_adapter,null,$options)) {
		        foreach ($imgm_resources as $imgm_resource) {
                    unset($imgm_resource->map_id);
                    unset($imgm_resource->created);
                    unset($imgm_resource->modified);
                    $imgm_resource->_exists = false;
		            $imgm_resource->item_id = $new_item_id;
		            $imgm_resource->save();
                }
            }

        } else {
            for ($i = 0; $i < $resource->number_of_groups; $i++) {
                $item_modifier_group_map_resource = null;
                $allowed_string = $i."allowed";
                $modifier_group_id_string = $i."modifier_group_id";
                $map_id_string = $i."item_modifier_group_map_id";
                $display_name_string = $i."display_name";
                $min_string = $i."min";
                $max_string = $i."max";
                $price_override_string = $i."price_override";
                $price_max_string = $i."price_max";
                $priority_string = $i."priority";
                $combo_tag_string = $i."combo_tag";

                $push_this_imgm_to_each_item_in_menu_type_string = $i."push_this_mapping_to_each_item_in_menu_type";
                $push_this_imgm_to_each_item_in_menu_type = $resource->$push_this_imgm_to_each_item_in_menu_type_string;

                $fields = array();
                //create data array
                $item_id = $resource->item_id;
                $fields['item_id'] = $item_id;
                $modifier_group_id = $resource->$modifier_group_id_string;
                $fields['modifier_group_id'] = $modifier_group_id;
                $fields['display_name'] = $resource->$display_name_string;
                $fields['min'] = $resource->$min_string;
                $fields['max'] = $resource->$max_string;
                $fields['price_override'] = $resource->$price_override_string;
                $fields['price_max'] = $resource->$price_max_string;
                $fields['priority'] = $resource->$priority_string;
                $fields['combo_tag'] = $resource->$combo_tag_string;
                $fields['merchant_id'] = $resource->merchant_id;



                if ($map_id = $resource->$map_id_string) {
                    $fields['map_id'] = $map_id;
                }


                if (strtolower($resource->$allowed_string) == 'on') {
                    $merchant_list = null;
                    if ($map_id == null) {
                        $item_modifier_group_map_resource = new Resource($item_modifier_group_map_adapter,$fields);
                        $item_modifier_group_map_adapter->insert($item_modifier_group_map_resource);
                        $this->createChildPriceRecords($fields, $item_modifier_group_map_adapter);
                    } else {
                        $item_modifier_group_map_resource = Resource::find($item_modifier_group_map_adapter,"$map_id");
                        $changed_fields = ['item_id'=>$item_id,'modifier_group_id'=>$modifier_group_id];
                        foreach ($fields as $name=>$value) {
                            if ($name == 'item_id' || $name == 'modifier_group_id') {
                                continue;
                            }
                            if ($item_modifier_group_map_resource->$name != $value) {
                                $changed_fields[$name] = $value;
                            }
                        }
                        $item_modifier_group_map_resource->saveResourceFromData($fields);
                        if ($merchant_list = $this->getPropogateMerchantIdsIfSent($resource->propogate_to_merchant_ids)) {
                            if (sizeof($merchant_list) == 1 && strtolower($merchant_list[0]) == 'all') {
                                // get all merchants associated with this menu
                                $merchant_list = [];
                                foreach($this->merchant_id_list as $merchant_id=>$value) {
                                    $merchant_list[] = $merchant_id;
                                }
                            }
                            if (sizeof($changed_fields) > 2) {
                                foreach ($merchant_list as $merchant_id) {
                                    $this->updateChildPriceRecordForMerchantId($merchant_id, $changed_fields, $item_modifier_group_map_adapter);
                                }
                            }
                        } else if (strtolower(substr($resource->update_child_records,0,3)) == 'yes') {
                            $sql = "DELETE FROM Item_Modifier_Group_Map WHERE item_id = ".$resource->item_id." AND modifier_group_id = ".$item_modifier_group_map_resource->modifier_group_id." AND merchant_id > 0";
                            myerror_log("SQL to erase IMGM records for item_id: ".$resource->item_id."  is: ".$sql);
                            $item_modifier_group_map_adapter->_query($sql);
                            myerror_log("we are about to recreate item_modifier_group_map child records");
                            unset($fields['map_id']);
                            $this->createChildPriceRecords($fields, $item_modifier_group_map_adapter);
                        }
                    }
                } else if (strtolower($resource->$allowed_string) == 'off' && $map_id != null) {
                    myerror_log("delete the mapping");
                    if ($resource->merchant_id == 0) {
                        $sql = "DELETE FROM Item_Modifier_Group_Map WHERE item_id = $item_id AND modifier_group_id = $modifier_group_id";
                        myerror_log("about to delete the mapping for this item: $sql");
                        $item_modifier_group_map_adapter->_query($sql);
                    } else {
                        $item_modifier_group_map_adapter->delete($map_id);
                    }
                } else {
                    myerror_log("do nothing");
                    continue;
                }
                if ($push_this_imgm_to_each_item_in_menu_type == 1) {
                    myerror_log("about to propogate a single IMGM record to the entire menu type");
                    /*
                     * 1. Get list of items in group
                     * 2. for each item delete any existing IMGM records for this item-modifiergroup combination
                     * 3. IF this was an update or insert then for each itme recreate this IMGM record with the same values at the primary.
                     */

                    $menu_type_id = $resource->menu_type_id;

                    $item_adapter = new ItemAdapter(getM());
                    $item_records = $item_adapter->getRecords(array("menu_type_id"=>$menu_type_id));

                    foreach ($item_records as $item_record) {
                        // skip this item since it was already done above
                        if ($item_record['item_id'] == $item_id) {
                            continue;
                        }

                        // Delete any existing (MAKE SURE ONLY FOR MERCHANT OF RECORD)
                        $sql = "DELETE FROM Item_Modifier_Group_Map WHERE item_id = ".$item_record['item_id']." AND modifier_group_id = $modifier_group_id AND merchant_id = $submitted_merchant_id";
                        $item_modifier_group_map_adapter->_query($sql);

                        if ($item_modifier_group_map_resource) {
                            myerror_log("We have a imgm resource so this is an update or insert so now lets propogate the record");
                            $item_modifier_group_map_resource->_exists = false;
                            unset($item_modifier_group_map_resource->map_id);
                            $item_modifier_group_map_resource->item_id = $item_record['item_id'];
                            unset($item_modifier_group_map_resource->modified);
                            $item_modifier_group_map_resource->created = time();
                            if ($item_modifier_group_map_resource->save()) {
                                myerror_log("we have cloned the IMGM record for item: ".$item_record['item_id']);
                            } else {
                                myerror_log("unable to save this cloned ItemModifierGroupMap record.  error: ".$item_modifier_group_map_resource->getAdapterError());
                            }
                            if ($map_id == null){
                                $fields['item_id'] = $item_record['item_id'];
                                $this->createChildPriceRecords($fields, $item_modifier_group_map_adapter);
                            } else {
                                if ($merchant_list) {
                                    foreach ($merchant_list as $merchant_id) {
                                        $sql = "DELETE FROM Item_Modifier_Group_Map WHERE item_id = ".$item_record['item_id']." AND modifier_group_id = $modifier_group_id AND merchant_id = $merchant_id";
                                        $item_modifier_group_map_adapter->_query($sql);
                                        $fields['merchant_id'] = $merchant_id;
                                        $fields['item_id'] = $item_record['item_id'];
                                        unset($fields['map_id']);
                                        if ($new_resource = Resource::createByData($item_modifier_group_map_adapter,$fields)) {
                                            myerror_log("successfull creation of propogation IMGM record: ".$new_resource->insert_id,3);
                                        } else {
                                            myerror_log("ERROR: Could not create propogation IMGM record: ".$item_modifier_group_map_adapter->getLastErrorText());
                                        }
                                    }
                                }
                            }
                        } else {
                            myerror_log("There is no IMGM so this must be a delete");
                        }
                    }
                }
            }
            myerror_log("done saving allowed mods");

            //now check for apply to all
            if (strtolower($resource->apply_IMGM_to_all_items) == 'yes')
            {
                myerror_log("WE HAVE AN APPLY TO ALL ITEMS for IMGM");
                $menu_type_id = $resource->menu_type_id;
                $item_id = $resource->item_id;
                // first delete all existing item modifier group mad records for all other items
                $sql = "DELETE FROM Item_Modifier_Group_Map WHERE item_id IN (SELECT item_id FROM Item WHERE menu_type_id = $menu_type_id AND item_id != $item_id)";
                myerror_log("SQL to erase IMGM records for menu_type_id: $menu_type_id   is: ".$sql);
                $item_modifier_group_map_adapter->_query($sql);

                // then get all the resources for the item modifier group map for this item
                $data['item_id'] = $item_id;
                $imgm_options[TONIC_FIND_BY_METADATA] = $data;
                $item_modifier_group_map_resources = Resource::findAll($item_modifier_group_map_adapter,'',$imgm_options);

                //now get all the items from the group
                $item_adapter = new ItemAdapter(getM());
                $item_records = $item_adapter->getRecords(array("menu_type_id"=>$menu_type_id));

                foreach ($item_records as $item_record)
                {
                    myerror_log("Cloning for item: ".$item_record['item_id']."   ".$item_record['item_name']);
                    // skip this item
                    if ($item_record['item_id'] == $item_id)
                        continue;

                    foreach ($item_modifier_group_map_resources as $item_modifier_group_map_resource)
                    {
                        // first see if this matchup exists already
                        //$options[TONIC_FIND_BY_METADATA] = array("item_id"=>$item_record['item_id'],"modifier_group_id"=>$item_modifier_group_map_resource->modifier_group_id);

                        // ok for now we are just deleting them all.

                        $item_modifier_group_map_resource->_exists = false;
                        unset($item_modifier_group_map_resource->map_id);
                        $item_modifier_group_map_resource->item_id = $item_record['item_id'];
                        unset($item_modifier_group_map_resource->modified);
                        $item_modifier_group_map_resource->created = time();
                        if ($item_modifier_group_map_resource->save())
                            ;
                        else
                            myerror_log("unable to save this cloned ItemModifierGroupMap record.  error: ".$item_modifier_group_map_resource->getAdapterError());
                    }
                }
            }

        }
	}
		
	private function priceSave(&$resource)
	{
		$item_size_adapter = new ItemSizeAdapter($this->mimetypes);
		for ($i = 0; $i < $resource->number_of_sizes; $i++)
		{
			$fields = null;
			$fields = array();
			$price_string = $i."price";
			$active_string = $i."active";
			$size_id_string = $i."size_id";
			$size_price_id_string = $i."sizeprice_id";
			$external_id_string = $i."external_id";
			$priority_string = $i."itemsize_priority";
			$include_string = $i."include";
			if (isset($resource->$include_string) && ! $resource->$include_string) {
			    continue;
            }
			
			$size_price_id = $resource->$size_price_id_string;
			
			$fields['price'] = $resource->$price_string;
			$fields['active'] = $resource->$active_string;
			if ($current_size_id = $resource->$size_id_string) {
                $fields['size_id'] = $current_size_id;
            } else {
			    continue;
            }

			$fields['item_id'] = $resource->item_id;
			$fields['merchant_id'] = $resource->merchant_id;
			if ($resource->$external_id_string == null || trim($resource->$external_id_string) == '') {
                $fields['external_id'] = 'nullit';
            } else {
                $fields['external_id'] = $resource->$external_id_string;
            }
			$fields['priority'] = $resource->$priority_string;
			if ($size_price_id == null) {
			    myerror_log("in the sizepriceid is null");
			    if ($fields['active'] == 'N' && $fields['price'] == 0) {
			        continue;
                }
                $size_price_resource = new Resource($item_size_adapter,$fields);
                $item_size_adapter->insert($size_price_resource);
               // $this->set_child_records_active = false;
                $this->createChildPriceRecords($fields, $item_size_adapter);
			} else {
                myerror_log("we have a size price id of: $size_price_id");
				$size_price_resource = Resource::find($item_size_adapter,"$size_price_id");
				//$size_price_resource =& new Resource($item_size_adapter,$fields);
				//$size_price_resource->save();
                $changed_fields = ["item_id"=>$fields['item_id'],"size_id"=>$fields['size_id']];
                foreach ($fields as $name=>$value) {
                    if ($name == 'item_id' || $name == 'size_id') {
                        continue;
                    }
                    if ($size_price_resource->$name != $value) {
                        $changed_fields[$name] = $value;
                    }
                }
                $size_price_resource->saveResourceFromData($fields);
                if ($merchant_list = $this->getPropogateMerchantIdsIfSent($resource->propogate_to_merchant_ids)) {
                    if (sizeof($merchant_list) == 1 && strtolower($merchant_list[0]) == 'all') {
                        // get all merchants associated with this menu
                        $merchant_list = [];
                        foreach($this->merchant_id_list as $merchant_id=>$value) {
                            $merchant_list[] = $merchant_id;
                        }
                    }
                    if (sizeof($changed_fields) > 2) {
                        foreach ($merchant_list as $merchant_id) {
                            $this->updateChildPriceRecordForMerchantId($merchant_id, $changed_fields, $item_size_adapter);
                        }
                    }
                } else if (isset($resource->update_child_records) && strtolower(substr($resource->update_child_records,0,3)) == 'yes') {
				    myerror_log("we are about to update ITEM the child records");
				    if ($resource->update_child_records == 'yes') {
				        unset($fields['active']);
                    } else if ($resource->update_child_records == 'yes_innactive') {
                        $fields['active'] = 'N';
                    } else if ($resource->update_child_records == 'yes_active') {
                        $fields['active'] = 'Y';
                    }
                    $this->updateChildPriceRecords($fields, $item_size_adapter);
                }
			}

		}
		if ($resource->number_of_sizes == 0 && $this->rows_updated == 1) {
		    // we have a new item
            $size_adapter = new SizeAdapter(getM());
            foreach ($size_adapter->getRecords(array("menu_type_id"=>$resource->menu_type_id)) as $size_record) {
                $fields['price'] = 0.00;
                $fields['active'] = 'N';
                $fields['size_id'] = $size_record['size_id'];
                $fields['item_id'] = $resource->new_item_id;
                $fields['merchant_id'] = 0;
                $fields['priority'] = $size_record['priority'];
                $size_price_resource = new Resource($item_size_adapter,$fields);
                $item_size_adapter->insert($size_price_resource);
                $this->set_child_records_active = false;
                $this->createChildPriceRecords($fields, $item_size_adapter);
            }

        }



		return true;
	}

	function getDataFromItemId($item_id)
    {
        $item_resource = Resource::find(new ItemAdapter(),$item_id);
        $menu_type_resource = Resource::find(new MenuTypeAdapter(),$item_resource->menu_type_id);
        return [ 'menu_type_id'=> $menu_type_resource->menu_type_id, "menu_id" => $menu_type_resource->menu_id];
    }

    function getMenuIdFromItemId($item_id)
    {
        return $this->getDataFromItemId($item_id)['menu_id'];
    }

    function getMenuTypeIdFromItemId($item_id)
    {
        return $this->getDataFromItemId($item_id)['menu_type_id'];
    }

}
?>