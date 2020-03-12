<?php

class PromoType1AmtMapAdapter extends MySQLAdapter
{

	function PromoType1AmtMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Promo_Type1_Amt_Map',
			'%([0-9]{1,15})%',
			'%d',
			array('map_id')
		);
			
	}
	
}
?>