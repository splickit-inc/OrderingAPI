<?php

class PromoKeyWordMapAdapter extends MySQLAdapter
{

	function PromoKeyWordMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Promo_Key_Word_Map',
			'%([0-9]{4,10})%',
			'%d',
			array('map_id')
			);
		
		$this->allow_full_table_scan = true;
						
	}
	
	function &select($url, $options = NULL)
    {
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
	}

}
?>