<?php

class AirportsAdapter extends MySQLAdapter
{

	function AirportsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Airports',
			'%([0-9]{4,7})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
			
		$this->allow_full_table_scan = true;
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
	
    static function getAllAirports($url,$data)
    {
    	$aa = new AirportsAdapter($mimetypes);
    	$options = $aa->setFindByMetaData($data, $options);
    	return $aa->select($url,$options);
    }
}
?>
