<?php

class ItemSizeAdapter extends MySQLAdapter
{

	function ItemSizeAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Item_Size_Map',
			'%([0-9]{1,15})%',
			'%d',
			array('item_size_id'),
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