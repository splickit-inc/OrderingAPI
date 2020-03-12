<?php

class EditMenuTypeAdapter extends EditMenuObjectAdapter
{

    var $get_item_ids = false;
    private $update = false;
    private $do_not_create_item_size_maps = false;

	function EditMenuTypeAdapter($mimetypes)
	{
        MySQLAdapter::MysqlAdapter(
			$mimetypes,
			'Menu_Type',
			'%([0-9]{4,15})%',
			'%d',
			array('menu_type_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	if ($results = parent::select($url,$options)) {
            if ($this->get_child_objects) {
                $this->attach_child_objects($results[0]);
            }
        }

    	return $results;
    }

    function attach_child_objects(&$record)
    {
        myerror_log("about to attache child objects to the menu_type_record: ".json_encode($record));
        $item_adapter = new ItemAdapter(getM());
        $mt_data = ['menu_type_id'=>$record['menu_type_id']];
        if ($item_results = $item_adapter->getRecords($mt_data)) {
            $record['items'] = $item_results;
        }
        $size_adapter = new SizeAdapter(getM());
        if ($size_results = $size_adapter->getRecords($mt_data)) {
            $record['sizes'] = $size_results;
        }
        return $record;
    }

    function getBulkFieldsArray($resource)
    {
    	if (isset($resource->items)) {
    		$bulk_fields['items_string'] = $resource->items;
    		myerror_log("we have bulk items: ".$bulk_fields['items_string']);
    	}
    	if (isset($resource->sizes)) {
    		$bulk_fields['sizes_string'] = $resource->sizes;
    		myerror_log("we have bulk sizes: ".$bulk_fields['sizes_string']);
    	}
    	return $bulk_fields;
    }
    
    function createBulkFieldsRecords($menu_type_id,$bulk_fields)
    {
    	if ($sizes_string = $bulk_fields['sizes_string'])
    	{
    		myerror_log("about to insert the sizes");
			$size_adapter = new SizeAdapter($mimetypes);
            $size_resource = Resource::dummyFactory(array("size_name"=>$sizes_string,"menu_type_id"=>$menu_type_id));
			$size_id_array = $size_adapter->bulkInsert($size_resource);
			logData($size_id_array,"size id array");
    	}
    	if ($items_string = $bulk_fields['items_string'])
    	{
    		myerror_log("about to insert the itesm");
    		$item_adapter = new ItemAdapter($mimetypes);
    		$item_resource = Resource::dummyFactory(array("item_name"=>$items_string,"menu_type_id"=>$menu_type_id));
    		$item_id_array = $item_adapter->bulkInsert($item_resource);
            logData($item_id_array,"item id array");
    	}
    	if ($items_string && $sizes_string) {
    	    $this->createSizePriceRecords($item_id_array,$size_id_array);
        } else if ($sizes_string) {
    	    // first get items in menu_type
            $item_id_array = [];
            foreach (CompleteMenu::getAllMenuItemsInMenuType($menu_type_id) as $menu_item) {
                $item_id_array[] = $menu_item['item_id'];
            }
            // not doing this here. will put it in the size adapter
            if ($this->do_not_create_item_size_maps) {
                myerror_log("Skiping the item_size record creations becuase flag has been unchecked");
            } else {
                $this->createSizePriceRecords($item_id_array,$size_id_array);
            }

        } else if ($items_string) {
            // first get sizes in menu_type
            $size_id_array = [];
            foreach (CompleteMenu::getAllSizesInMenuType($menu_type_id) as $size) {
                $size_id_array[] = $size['size_id'];
            }
            $this->createSizePriceRecords($item_id_array,$size_id_array);
        }

    }

    function createSizePriceRecords($item_id_array,$size_id_array)
    {
        myerror_log("about to create size price records");
        $item_size_adapter = new ItemSizeAdapter($this->mimetypes);
        foreach ($item_id_array as $item_id) {
            $priority = 100;
            $i = explode('#',$item_id);
            if ($i[1]) {
                $prices = explode(',',$i[1]);
            }
            foreach ($size_id_array as $index=>$size_id) {
                $fields = array("size_id"=>$size_id,"item_id"=>$i[0],"active"=>'N',"merchant_id"=>0,"priority"=>$priority);
                if ($prices) {
                    $fields['price'] = $prices[$index];
                }
                $size_price_resource = new Resource($item_size_adapter,$fields);
                $size_price_resource->save();
                $fields['active'] = 'N';
                $this->createChildPriceRecords($fields, $item_size_adapter);
                $priority = $priority - 10;
            }
        }
    }

    function insert($resource)
    {
        if (isset($resource->create_item_size_maps) && $resource->create_item_size_maps === false) {
            $this->do_not_create_item_size_maps = true;
        }
        $bulk_fields_array = $this->getBulkFieldsArray($resource);
        $return = parent::insert($resource);
        $this->createBulkFieldsRecords($resource->menu_type_id, $bulk_fields_array);
        return $return;
    }

    function update($resource)
    {
        if (isset($resource->create_item_size_maps) && $resource->create_item_size_maps === false) {
            $this->do_not_create_item_size_maps = true;
        }
        $this->update = true;
        $bulk_fields_array = $this->getBulkFieldsArray($resource);
        $return = parent::update($resource);
        $this->createBulkFieldsRecords($resource->menu_type_id, $bulk_fields_array);
        return $return;
    }

}
?>