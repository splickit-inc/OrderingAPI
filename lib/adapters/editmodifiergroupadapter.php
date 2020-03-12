<?php

class EditModifierGroupAdapter extends EditMenuObjectAdapter
{

    var $get_modifier_item_ids = false;

	function EditModifierGroupAdapter($mimetypes)
	{
		parent::__construct(
			$mimetypes,
			'Modifier_Group',
			'%([0-9]{1,15})%',
			'%d',
			array('modifier_group_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
        $results = parent::select($url,$options);
        if ($this->get_child_objects && sizeof($results) == 1) {
            $modifier_group_id = $results[0]['modifier_group_id'];
            $modifier_item_adapter = new ModifierItemAdapter(getM());
            if ($modifier_item_results = $modifier_item_adapter->getRecords(['modifier_group_id'=>$modifier_group_id])) {
                $results[0]['created_modifier_items'] = $modifier_item_results;
            }
        }
        return $results;
    }

    function update(&$resource)
    {
    	$this->setInstanceVaribles($resource);
		//first grab the modifier item string and default price if they exist
		$modifier_items_string = $resource->item_list;
		$default_item_price = $resource->default_item_price;
		$default_item_max = $resource->default_item_max;
		
    	//set created to now() otherise update will fail if only adding new items
    	$resource->set('modified',time());
		if (parent::update($resource))
    	{
    		$modifier_group_id = $resource->modifier_group_id;
			$this->createModifierItemRecords($modifier_group_id,$modifier_items_string,$default_item_price,$default_item_max);
			return true;
		} else {
			myerror_log("ERROR! ".$this->_error());
			return false;
		}
	}
	
    function insert(&$resource)
	{
		$this->setInstanceVaribles($resource);
		//first grab the modifier item string and default price if they exist
		$modifier_items_string = $resource->item_list;
		$default_item_price = $resource->default_item_price;
		$default_item_max = $resource->default_item_max;
		//next call the parent
		if (parent::insert($resource))
		{
			$modifier_group_id = $this->_insertId();
			// now put in the modifier items if they exist
			$this->createModifierItemRecords($modifier_group_id,$modifier_items_string,$default_item_price,$default_item_max);
			return true;
		} else {
			myerror_log("ERROR! ".$this->_error());
			return false;
		}
	}

	private function getSubmittedModifierItemsAsArrayFromStringList($modifier_items_string)
    {
        if ($modifier_items = isStringList($modifier_items_string,';')) {
            return $modifier_items;
        } else {
            return array($modifier_items_string);
        }
    }

    private function createModifierItemRecords($modifier_group_id,$modifier_items_string,$default_item_price,$default_item_max)
    {
    		$modifier_items_string = trim($modifier_items_string);
			if ($modifier_items_string != null && $modifier_items_string != '')
			{
			    $modifier_items = $this->getSubmittedModifierItemsAsArrayFromStringList($modifier_items_string);
				$modifier_item_adapter = new ModifierItemAdapter($this->mimetypes);
				$priority = 200;
				foreach ($modifier_items AS $modifier_item)
				{
				    $modifier_item = trim($modifier_item);
					$fields = array();
					$fields['modifier_group_id'] = $modifier_group_id;
					$fields['modifier_item_name'] = $modifier_item;
					$fields['modifier_item_print_name'] = $modifier_item;
					$fields['modifier_item_max'] = $default_item_max;
					$fields['priority'] = $priority;
					
					$modifier_item_resource = new Resource($modifier_item_adapter,$fields);
					if ($modifier_item_adapter->insert($modifier_item_resource))
					{
						myerror_log($fields['modifier_item_name']." added in modgroupadapter");
						$modifier_item_id = $modifier_item_adapter->_insertId();
						$modifier_size_map_adapter = new ModifierSizeMapAdapter($this->mimetypes);
						$price_fields = array();
						$price_fields['modifier_item_id'] = $modifier_item_id;
						$price_fields['size_id'] = 0;
						$price_fields['modifier_price'] = $default_item_price;
						$price_fields['priority'] = $priority;
						$modifier_size_map_resource = new Resource($modifier_size_map_adapter,$price_fields);
						if ($modifier_size_map_adapter->insert($modifier_size_map_resource)) {
							myerror_log("successful price row added for mod item"); // do nothing we're good
                            $this->set_child_records_active = true;
							$this->createChildPriceRecords($price_fields, $modifier_size_map_adapter);
						} else {
							myerror_log("ERROR!  ther was an error thrown inserting a modifer price in ModifierGroupAdapter: ".$modifier_size_map_adapter->_error());
						}
					} else {
						myerror_log("ERROR!  ther was an error thrown inserting a modifer item in ModifierGroupAdapter: ".$modifier_item_adapter->_error());
					}	
					$priority = $priority - 5;				
				}
			}    	
    }
}
?>