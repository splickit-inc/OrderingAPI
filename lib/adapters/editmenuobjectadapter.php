<?php

class EditMenuObjectAdapter extends MySQLAdapter
{

	// perhaps this should be used by an abstraction wbetweebn an edit adapter nad the actual adapter. or maybe an impliments.
	var $menu_resource;
	var $menu_version;
	var $merchant_id_list = array();
	var $menu_id;
	var $merchant_id;
	var $set_child_records_active = false;
	var $get_child_objects = false;

	function setMenuResourceByMenuId($menu_id)
    {
        $this->menu_resource = Resource::find(new MenuAdapter($m),"$menu_id");
        myerror_log("we are setting the menu resource");
        logData($this->menu_resource->getDataFieldsReally(),"Menu");
        $this->menu_version = $this->menu_resource->version;
        $this->menu_id = $this->menu_resource->menu_id;
        $this->setMerchantListIf30Menu();
    }

	protected function setMerchantListIf30Menu()
	{
		if ($this->menu_resource->version < 3.0) {
			myerror_log("menu version is ".$this->menu_resource->version." so do not set merchant list");
			return;
		}
		$merchant_menu_map_adapter = new MerchantMenuMapAdapter($mimetypes);
		if ($map_list = $merchant_menu_map_adapter->getRecords(array("menu_id"=>$this->menu_id)))
		{
			foreach ($map_list as $mmm) {
				$this->merchant_id_list[$mmm['merchant_id']] = 1;
			}
			myerror_log("we have ".count($this->merchant_id_list)." merchants attached to this menu");
		} else {
			myerror_log("no merchants were found attached to this menu in the Merchant_Menu_Map_Table");
		}
	}
	
	protected function setInstanceVaribles($resource)
	{
	    $menu_id = isset($resource->link_menu_id) ? $resource->link_menu_id : $this->menu_id;
		myerror_log("setting menu_id = ".$menu_id);
		myerror_log("setting merchant_id = ".$resource->merchant_id);
		$this->menu_id = $this->menu_id;
		$this->merchant_id = $resource->merchant_id;
		$this->menu_resource = Resource::find(new MenuAdapter($mimetypes),$this->menu_id);
		$this->setMerchantListIf30Menu();	
	}
	
	protected function createChildPriceRecords($fields,$adapter)
	{
        myerror_log("we are starting the createChildPriceRecords");
        myerror_log("this merchant_id = ".$this->merchant_id);
        myerror_log("this menu_version = ".$this->menu_resource->version);
		if ($this->merchant_id == 0 && $this->menu_resource->version > 2.0) {
			if (count($this->merchant_id_list) > 0) {
				myerror_log("about to create child price records.");
				foreach ($this->merchant_id_list as $id=>$dummy_value) {
					$fields['merchant_id'] = $id;
                    //$fields['active'] = $this->set_child_records_active ? 'Y' : 'N';
                    logData($fields,"child price insert fields");
					$child_size_price_resource = new Resource($adapter, $fields);
					$adapter->insert($child_size_price_resource);
				}
			}
		}
	}

	protected function updateChildPriceRecordForMerchantId($merchant_id,$fields,$adapter)
    {
        if (is_a($adapter,'ItemSizeAdapter')) {
            $options[TONIC_FIND_BY_METADATA] = array("merchant_id"=>$merchant_id,"item_id"=>$fields['item_id'],"size_id"=>$fields['size_id']);
        } else if (is_a($adapter,'ModifierSizeMapAdapter')) {
            $options[TONIC_FIND_BY_METADATA] = array("merchant_id"=>$merchant_id,"modifier_item_id"=>$fields['modifier_item_id'],"size_id"=>$fields['size_id']);
        } else if (is_a($adapter,'ItemModifierGroupMapAdapter')) {
            $options[TONIC_FIND_BY_METADATA] = array("merchant_id"=>$merchant_id,"item_id"=>$fields['item_id'],"modifier_group_id"=>$fields['modifier_group_id']);
        }

        myerror_log("about to determine if the resource exists");
        if ($size_price_resource = Resource::find($adapter,null,$options)) {
            myerror_log("resource exists!");
            if (isset($size_price_resource->price)) {
                $size_price_resource->price = $fields['price'];
            } else if (isset($size_price_resource->modifier_price)) {
                $size_price_resource->modifier_price = $fields['modifier_price'];
            } else if (isset($size_price_resource->price_override)) {
                // want to update the existing record with the new fields but NOT merhant_id and map_id
                unset($fields['merchant_id']);
                unset($fields['map_id']);
                return $size_price_resource->saveResourceFromData($fields);
            } else {
                myerror_log("we are in teh continue");
                return;
            }
            if ($fields['active']) {
                //$size_price_resource->active = $this->set_child_records_active ? 'Y' : 'N';
                $size_price_resource->active = $fields['active'];
            }
            if ($fields['priority']) {
                $size_price_resource->priority = $fields['priority'];
            }
            if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($fields,'external_id')) {
                $size_price_resource->external_id = $fields['external_id'];
            }
            $size_price_resource->save();
        } else {
            myerror_log("resource DOES NOT exist");
            // check to see if merchant is mapped to this menu
            $mmm = new MerchantMenuMapAdapter(getM());
            if ($record = $mmm->getRecord(['merchant_id'=>$merchant_id,'menu_id'=>$this->menu_id])) {
                if (!isset($fields['active'])) {
                    $fields['active'] = $this->set_child_records_active ? 'Y' : 'N';
                }
                unset($fields['item_size_id']);
                unset($fields['modifier_size_id']);
                $child_size_price_resource = new Resource($adapter, $fields);
                $adapter->insert($child_size_price_resource);
            } else {
                myerror_log("ERROR!!!  Skipping child record update for merchant_id: $merchant_id. merchant is NOT mapped to this menu: ".$this->menu_id);
            }
        }
    }

	protected function updateChildPriceRecords($fields,$adapter)
    {
        logData($fields,"update fields");
        myerror_log("we are starting the updateChildPriceRecords");
        myerror_log("this merchant_id = ".$this->merchant_id);
        myerror_log("this menu_version = ".$this->menu_resource->version);
        if ($this->merchant_id == 0 && $this->menu_resource->version > 2.0) {
            if (count($this->merchant_id_list) > 0)
            {
                myerror_log("about to update child price records.");
                foreach ($this->merchant_id_list as $merchant_id=>$dummy_value)
                {
                    myerror_log("starting update for merchant_id: $merchant_id");
                    $fields['merchant_id'] = $merchant_id;
                    $this->updateChildPriceRecordForMerchantId($merchant_id,$fields,$adapter);
                }
            }
        }
    }

    function getPropogateMerchantIdsIfSent($merchant_ids)
    {
        if ($merchant_ids == null) {
            return null;
        } else if ($merchant_list = isStringList($merchant_ids)) {
            return $merchant_list;
        } else if (is_numeric($merchant_ids)) {
            return [$merchant_ids];
        } else if (strtolower($merchant_ids) == 'all') {
            return ['all'];
        } else {
            return null;
        }
    }

    function getObjectForEditById($id)
    {
        myerror_log("getting the object for edit by id: ".$id);
        if ($record = $this->getRecordFromPrimaryKey($id)) {
            return $this->attach_child_objects($record);
        } else {
            myerror_log('ERROR!!!!  No matching object with that ID: $id');
            throw new Exception("No matching object with that ID: $id");
        }
    }


}
?>