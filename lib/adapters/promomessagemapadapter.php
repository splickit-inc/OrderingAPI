<?php

class PromoMessageMapAdapter extends MySQLAdapter
{

	function PromoMessageMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Promo_Message_Map',
			'%([0-9]{1,15})%',
			'%d',
			array('map_id')
		);
			
	}
	
}
?>