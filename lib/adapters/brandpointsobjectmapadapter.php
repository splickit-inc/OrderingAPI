<?php

class BrandPointsObjectMapAdapter extends MySQLAdapter
{

	function BrandPointsObjectMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Brand_Points_Object_Map',
			'%([0-9]{3,10})%',
			'%d',
			array('map_id'),
			null,
			array('created','modified')
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