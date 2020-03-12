<?php

class ModifierSizeMapAdapter extends MySQLAdapter
{

	function ModifierSizeMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Modifier_Size_Map',
			'%([0-9]{1,15})%',
			'%d',
			array('modifier_size_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
	
    static function getModifierSizeRecord($modifier_item_id, $modifier_size_id,$merchant_id)
    {
    	$mism_data['modifier_item_id'] = $modifier_item_id;
		$mism_data['size_id'] = $modifier_size_id;
		$mism_data['merchant_id'] = $modifier_merchant_id;
		$modifier_size_map_adapter = new ModifierSizeMapAdapter($mimetypes);
		return $modifier_size_map_adapter->getRecord($mism_data);
    }
    
}
?>