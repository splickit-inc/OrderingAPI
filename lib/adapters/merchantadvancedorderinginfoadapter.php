<?php

class MerchantAdvancedOrderingInfoAdapter extends MySQLAdapter
{

	function MerchantAdvancedOrderingInfoAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_Advanced_Ordering_Info',
			'%([0-9]{2,15})%',
			'%d',
			array('merchant_advanced_ordering_id'),
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