<?php

class PromoType3UserMapAdapter extends MySQLAdapter
{

	function PromoType3UserMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Promo_Type3_User_Map',
			'%([0-9]{1,15})%',
			'%d',
			array('map_id'),
			null,
			array('created','modified')
		);
			
	}
	
}
?>