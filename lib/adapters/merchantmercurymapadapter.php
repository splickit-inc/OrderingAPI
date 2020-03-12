<?php
class MerchantMercuryMapAdapter extends MySQLAdapter
{

	function MerchantMercuryMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_Mercury_Map',
			'%([0-9]{1,15})%',
			'%d',
			array('merchant_id')
			);
	}
}
?>