<?php

class OrderDetailModifierAdapter extends MySQLAdapter
{

	function OrderDetailModifierAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'`Order_Detail_Modifier`',
			'%([0-9]{1,15})%',
			'%d',
			array('order_detail_mod_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
    
    function &update($resource)
    {
    	$resource->set('error','method not allowed');
    	return false;
    }

    function &insert($resource)
    {
		myerror_log("error! method not allowed!");
		$resource->set('error','method not allowed');
		return false;
    }
}
?>