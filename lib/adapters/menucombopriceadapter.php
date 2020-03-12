<?php

class MenuComboPriceAdapter extends MySQLAdapter
{

	function MenuComboPriceAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Menu_Combo_Price',
			'%([0-9]{4,11})%',
			'%d',
			array('combo_price_id'),
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