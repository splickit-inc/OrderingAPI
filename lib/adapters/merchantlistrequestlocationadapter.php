<?php

class MerchantListRequestLocationAdapter extends MySQLAdapter
{

	function MerchantListRequestLocationAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'merchant_list_request_location',
			'%([0-9]{1,15})%',
			'%d',
			array('id'),
			null,
			array('created')
			);
	}
	
}
?>