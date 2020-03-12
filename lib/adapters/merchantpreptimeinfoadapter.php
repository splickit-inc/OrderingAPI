<?php

class MerchantPreptimeInfoAdapter extends MySQLAdapter
{

	function MerchantPreptimeInfoAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_Preptime_Info',
			'%([0-9]{4,10})%',
			'%d',
			array('merchant_preptime_info_id')
			);
		
		//$this->allow_full_table_scan = true;
						
	}
	
	function &select($url, $options = NULL)
    {
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
	}

}
?>