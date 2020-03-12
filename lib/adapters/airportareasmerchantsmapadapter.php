<?php

class AirportAreasMerchantsMapAdapter extends MySQLAdapter
{

	function AirportAreasMerchantsMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Airport_Areas_Merchants_Map',
			'%([0-9]{1,15})%',
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
    
    function assignMerchantToAirportArea($airport_area_id,$merchant_id,$location)
	{
		$data['airport_area_id'] = $airport_area_id;
		$data['merchant_id'] = $merchant_id;
		$data['location'] = $location;
		$options[TONIC_FIND_BY_METADATA] = $data;
		$resource = Resource::findOrCreateIfNotExists($this, $url, $options);
		return $resource; 
	}

    /**
	 * 
	 * @desc takes an airport id and returns an array of all the merchants associated with that airport 
	 * @param int $airport_id
	 * @return array
	 */
    function getAllAirportMerchants($airport_id)
    {
		$sql = "SELECT a.* FROM Airport_Areas_Merchants_Map a JOIN Airport_Areas b ON a.airport_area_id = b.id WHERE b.airport_id = $airport_id";
		$options[TONIC_FIND_BY_SQL] = $sql;
		$merchants = $this->select(null,$options);
		return $merchants;
    }
    
    /**
	 * 
	 * @desc takes an airport area id and returns an array of all the merchants associated with that airport area
	 * @param int $airport_area_id
	 * @return array
	 */

    function getAirportAreaMerchants($airport_area_id)
    {
    	return $this->getRecords(array('id'=>$airport_area_id));
    }

    static function isMerchantAnAirportLocation($merchant_id)
    {
    	$aamma = new AirportAreasMerchantsMapAdapter($mimetypes);
    	if ($aamma->getRecord(array('merchant_id'=>$merchant_id))) {
    		return true;
    	}
    	return false;
    }
}
?>
