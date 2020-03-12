<?php

require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'modifiersizemapadapter.php';

class EditModifierItemAdapter extends EditMenuObjectAdapter
{
	
	function EditModifierItemAdapter($mimetypes)
	{
		parent::__construct(
			$mimetypes,
			'Modifier_Item',
			'%([0-9]{1,15})%',
			'%d',
			array('modifier_item_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    	
    	$data = parent::select($url,$options);
		if (preg_match('%^/admin/modifieritems/([0-9]+)%', $url)) {
    	    // we know we are requesting from the edit menu pages so add the price and size info
    		$modifier_size_map_adapter = new ModifierSizeMapAdapter($this->mimetypes);
    		$options2[TONIC_FIND_BY_METADATA]['modifier_item_id'] = $data[0]['modifier_item_id'];
    		$mod_prices = $modifier_size_map_adapter->select('',$options2);
    		$data[0]['prices'] = $mod_prices;
			// now get all the sizes for this merchant so we can give them the option of setting prices for any size that this modifier item may be attached to
		}	
    	return $data;
    }

    function update(&$resource)
    {
        $this->setInstanceVaribles($resource);
        $resource->set('modified',time());
        if (parent::update($resource))
        {
            // now deal with the prices
            myerror_log("good modifier item update");
            $this->priceSave($resource);
            return true;
        }
        return false;
    }

    function insert(&$resource)
    {
        $this->setInstanceVaribles($resource);
        if (parent::insert($resource))
        {
            // now deal with the prices
            myerror_log("good modifier item save");
            $this->priceSave($resource);
            return true;
        }
        return false;
    }

    private function priceSave(&$resource)
	{
		$modifier_size_map_adapter = new ModifierSizeMapAdapter($this->mimetypes);
		$number_of_sizes = $resource->number_of_sizes;
		for ($i = 0; $i < $number_of_sizes+1; $i++)
		{
			$modifier_price = null;
			$mod_size_price_id = null;
			
			$price_string = $i."modifier_price";
			$active_string = $i."active";
			$size_id_string = $i."size_id";
			$mod_size_price_id_string = $i."mod_sizeprice_id";
			$external_id_string = $i."external_id";
			
			$mod_size_price_id = $resource->$mod_size_price_id_string;
			$modifier_price = $resource->$price_string;
			
			if ($modifier_price !== null) {
				$fields = array();
				$fields['modifier_price'] = $modifier_price;
                if ($resource->$external_id_string == null || trim($resource->$external_id_string) == '') {
                    $fields['external_id'] = 'nullit';
                } else {
                    $fields['external_id'] = $resource->$external_id_string;
                }
				$fields['active'] = $resource->$active_string;
				$fields['size_id'] = $resource->$size_id_string;
				$fields['merchant_id'] = $resource->merchant_id;
				$fields['modifier_item_id'] = $resource->modifier_item_id;
				$fields['priority'] = $resource->priority;
				if ($mod_size_price_id == null) {
                        myerror_log("in the mod size price id is NULL");
						$size_price_resource = Resource::factory($modifier_size_map_adapter,$fields);
						$modifier_size_map_adapter->insert($size_price_resource);
						myerror_log("we are about to create the MODIFIER child records");
						$this->createChildPriceRecords($fields, $modifier_size_map_adapter);
				} else {
                    $size_price_resource = Resource::find($modifier_size_map_adapter,"$mod_size_price_id");
                    $changed_fields = ["modifier_item_id"=>$fields['modifier_item_id'],"size_id"=>$fields['size_id']];
                    foreach ($fields as $name=>$value) {
                        if ($name == 'modifier_item_id' || $name == 'size_id') {
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
                        myerror_log("we are about to Update the MODIFIER child records");
                        if (sizeof($changed_fields) > 2) {
                            foreach ($merchant_list as $merchant_id) {
                                $this->updateChildPriceRecordForMerchantId($merchant_id, $changed_fields, $modifier_size_map_adapter);
                            }
                        }
                    } else if (isset($resource->update_child_records) && strtolower(substr($resource->update_child_records,0,3)) == 'yes') {
                        myerror_log("we are about to update the Modifier child records in base answer");
                        if ($resource->update_child_records == 'yes') {
                            unset($fields['active']);
                        } else if ($resource->update_child_records == 'yes_innactive') {
                            $fields['active'] = 'N';
                        } else if ($resource->update_child_records == 'yes_active') {
                            $fields['active'] = 'Y';
                        }
                        $this->updateChildPriceRecords($fields, $modifier_size_map_adapter);
                    }
				}
				if ($resource->apply_prices_to_all_modifier_items_in_group == 'yes') {
				    $modifier_item_adapter = new ModifierItemAdapter();
				    // get all modifier id's from this group
                    $modifier_group_id = $resource->modifier_group_id;
                    if ($modifier_items = $modifier_item_adapter->getRecords(array("modifier_group_id"=>$modifier_group_id))) {
                        myerror_log("we have the other modifier_items in the group");
                        logData($modifier_items,"group modifier items");
                        foreach ($modifier_items as $modifier_item) {
                            if ($modifier_item['modifier_item_id'] == $resource->modifier_item_id) {
                                myerror_log("skiping the current one");
                                continue;
                            }
                            $data = array("modifier_item_id"=>$modifier_item['modifier_item_id'],"size_id"=>$fields['size_id']);
                            $data['merchant_id'] = $resource->merchant_id;
                            $modifier_size_price_resource = Resource::findOrCreateIfNotExistsByData($modifier_size_map_adapter,$data);
                            $modifier_size_price_resource->active = $fields['active'];
                            $modifier_size_price_resource->priority = $modifier_item['priority'];
                            $modifier_size_price_resource->modifier_price = $modifier_price;
                            $modifier_size_price_resource->save();
                            myerror_log("we saved the child");
                            if ($resource->merchant_id == 0  && $base_answer == 'yes') {
                                // then update all the child records
                                myerror_log("about to update the children for this other modifier");
                                $this->updateChildPriceRecords($modifier_size_price_resource->getDatafieldsReally(),$modifier_size_map_adapter);
                            }

                        }
                    } else {
                        myerror_log("no other modifier items in teh group");
                    }

                }
			}			
	
		}
		myerror_log("done");
		return true;
	}		
}
?>