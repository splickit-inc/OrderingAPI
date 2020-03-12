<?php

class MenuTypeAdapter extends MySQLAdapter
{

	function MenuTypeAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Menu_Type',
			'%([0-9]{1,15})%',
			'%d',
			array('menu_type_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }

//    function getBulkFieldsArray($resource)
//    {
//    	if (isset($resource->items)) {
//    		$bulk_fields['items_string'] = $resource->items;
//    		myerror_log("we have bulk items: ".$bulk_fields['items_string']);
//    	}
//    	if (isset($resource->sizes)) {
//    		$bulk_fields['sizes_string'] = $resource->sizes;
//    		myerror_log("we have bulk sizes: ".$bulk_fields['sizes_string']);
//    	}
//    	return $bulk_fields;
//    }
//
//    function createBulkFieldsRecords($menu_type_id,$bulk_fields)
//    {
//    	if ($sizes_string = $bulk_fields['sizes_string'])
//    	{
//    		myerror_log("about to insert the sizes");
//			$size_adapter = new SizeAdapter($mimetypes);
//			$size_resource = Resource::dummyFactory(array("size_name"=>$sizes_string,"menu_type_id"=>$menu_type_id));
//			$size_id_array = $size_adapter->bulkInsert($size_resource);
//    	}
//    	if ($items_string = $bulk_fields['items_string'])
//    	{
//    		myerror_log("about to insert the itesm");
//    		$item_adapter = new ItemAdapter($mimetypes);
//    		$item_resource = Resource::dummyFactory(array("item_name"=>$items_string,"menu_type_id"=>$menu_type_id));
//    		$item_id_array = $item_adapter->bulkInsert($item_resource);
//    	}
//    	if ($items_string && $sizes_string) {
//    	    myeror_log("about to create size price records");
//            $item_size_adapter = new ItemSizeAdapter($this->mimetypes);
//            foreach ($item_id_array as $item_id) {
//                foreach ($size_id_array as $size_id) {
//                    $fields = array("size_id"=>$size_id,"item_id"=>$item_id,"active"=>'Y',"merchant_id"=>0);
//                    $size_price_resource =& new Resource($item_size_adapter,$fields);
//                    $item_size_adapter->insert($size_price_resource);
//                    $this->createChildPriceRecords($fields, $item_size_adapter);
//                }
//            }
//        }
//
//    }
//
//    function insert($resource)
//    {
//    	$bulk_fields_array = $this->getBulkFieldsArray($resource);
//		$return = parent::insert($resource);
//		$this->createBulkFieldsRecords($resource->menu_type_id, $bulk_fields_array);
//		return $return;
//    }
//
//    function update($resource)
//    {
//    	$bulk_fields_array = $this->getBulkFieldsArray($resource);
//		$return = parent::update($resource);
//		$this->createBulkFieldsRecords($resource->menu_type_id, $bulk_fields_array);
//		return $return;
//    }
}
?>