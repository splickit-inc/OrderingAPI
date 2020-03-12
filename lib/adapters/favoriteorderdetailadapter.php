<?php

class FavoriteOrderDetailAdapter extends MySQLAdapter
{

	function FavoriteOrderDetailAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Favorite_Order_Detail',
			'%([0-9]{1,15})%',
			'%d',
			array('favorite_detail_id'),
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
