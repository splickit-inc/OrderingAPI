<?php

class FavoriteOrderDetailModifierAdapter extends MySQLAdapter
{

	function FavoriteOrderDetailModifierAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Favorite_Order_Detail_Modifier',
			'%([0-9]{1,15})%',
			'%d',
			array('favorite_detail_mod_id'),
			NULL,
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
