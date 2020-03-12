<?php

class PromoMerchantMapAdapter extends MySQLAdapter
{

	function PromoMerchantMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Promo_Merchant_Map',
			'%([0-9]{1,15})%',
			'%d',
			array('map_id'),
			null,
			array('created','modified')
		);
			
	}
	
}
?>