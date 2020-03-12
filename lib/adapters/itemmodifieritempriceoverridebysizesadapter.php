<?php

class ItemModifierItemPriceOverrideBySizesAdapter extends MySQLAdapter
{

	function ItemModifierItemPriceOverrideBySizesAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Item_Modifier_Item_Price_Override_By_Sizes',
			'%([0-9]{4,15})%',
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