<?php

class AirportAreasAdapter extends MySQLAdapter
{

	function AirportAreasAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Airport_Areas',
			'%([0-9]{4,7})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
			
		$this->allow_full_table_scan = false;
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
    
    function getAirportAreas($airport_id)
    {
    	return $this->getRecords(array('airport_id'=>$airport_id));
    }
    
    static function staticGetAirportAreas($airport_id)
    {
    	$aaa = new AirportAreasAdapter($mimetypes);
    	return $aaa->getAirportAreas($airport_id);
    }
	
}
?>
