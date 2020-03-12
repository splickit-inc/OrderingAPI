<?php

class ItemModifierGroupMapAdapter extends MySQLAdapter
{

	function ItemModifierGroupMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Item_Modifier_Group_Map',
			'%([0-9]{1,15})%',
			'%d',
			array('map_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
    
    static function createChildRecordsForMerchant($menu_id,$merchant_id,$with_delete = false)
    {
    	$imgm_adapter = new ItemModifierGroupMapAdapter($mimetypes);
		
    	if ($with_delete)
    	{
    		myerror_log("about to delete the existing Item_Modifier_Group_Map records");
    		$sql = "DELETE a FROM Item_Modifier_Group_Map a JOIN Modifier_Group b ON b.modifier_group_id = a.modifier_group_id WHERE a.merchant_id = $merchant_id AND b.menu_id = $menu_id";
    		$imgm_adapter->_query($sql);	
    	}
    	
		// menu version 3.0 will use merchant id on the price records
		$imgm_data['merchant_id'] = "0";
		$imgm_options[TONIC_FIND_BY_METADATA] = $imgm_data;
		$imgm_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Item.item_id = Item_Modifier_Group_Map.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
		$imgm_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
		//$imgm_options[TONIC_SORT_BY_METADATA] = ' Item_Size_Map.priority DESC ';
		$imgm_resources = Resource::findAll($imgm_adapter,'',$imgm_options);
		myerror_log("about to add the Item_Modifier_Group_Map records");
		foreach ($imgm_resources as $imgm_resource)
		{
			$imgm_resource->map_id = null;
			$imgm_resource->_exists = false;
			$imgm_resource->merchant_id = $merchant_id;
			$imgm_resource->save();			
		}
		myerror_log("done with Item_Modifier_Group_Map records");    	
    }
    	
}
?>