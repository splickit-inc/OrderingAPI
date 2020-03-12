<?php

class PromoType2ItemMapAdapter extends MySQLAdapter
{

	function PromoType2ItemMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Promo_Type2_Item_Map',
			'%([0-9]{1,15})%',
			'%d',
			array('map_id')
		);
			
	}
	
}
?>