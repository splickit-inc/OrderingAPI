<?php

class PromoUserMapAdapter extends MySQLAdapter
{

	function PromoUserMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Promo_User_Map',
			'%([0-9]{1,15})%',
			'%d',
			array('map_id'),
			null,
			array('created','modified')
		);
			
	}
	
}
?>