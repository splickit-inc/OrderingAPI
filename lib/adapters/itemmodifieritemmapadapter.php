<?php

class ItemModifierItemMapAdapter extends MySQLAdapter
{

	function ItemModifierItemMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Item_Modifier_Item_Map',
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
	
}
?>