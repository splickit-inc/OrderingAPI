<?php

class MerchantListAdapter extends MySQLAdapter
{

	function MerchantListAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant',
			'%([0-9]{2,15})%',
			'%d',
			array('merchant_id'),
			array('merchant_id','merchant_external_id','numeric_id','brand_id','lat','lng','name','display_name','active','address1','description','city','state','zip','phone_no','delivery','group_ordering_on'),
			null
			);
	}
	
	function &select($url, $options = NULL)
    {
        if (isset($options[TONIC_FIND_BY_STATIC_METADATA])) {
            $options[TONIC_FIND_BY_STATIC_METADATA] = $options[TONIC_FIND_BY_STATIC_METADATA]." AND Merchant.logical_delete = 'N' ";
        } else {
            $options[TONIC_FIND_BY_STATIC_METADATA] = " Merchant.logical_delete = 'N' ";
        }

    	//setting this in the controller now.
    	//$options[TONIC_FIND_BY_METADATA]['active'] = 'Y';
    	return parent::select($url,$options);
    }
    
    function &selectAirportLocations($airport_id,$skin_id,$data)
    {
    	$options = $this->setFindByMetaData($data, $options);
    	$options[TONIC_JOIN_STATEMENT] = " JOIN Skin_Merchant_Map ON Merchant.merchant_id = Skin_Merchant_Map.merchant_id ";
		$options[TONIC_JOIN_STATEMENT] .= " JOIN Airport_Areas_Merchants_Map ON Airport_Areas_Merchants_Map.merchant_id = Merchant.merchant_id ";
    	$options[TONIC_JOIN_STATEMENT] .= " JOIN Airport_Areas ON Airport_Areas_Merchants_Map.airport_area_id = Airport_Areas.id ";
		$options[TONIC_FIND_BY_STATIC_METADATA] .= " Skin_Merchant_Map.skin_id = $skin_id AND Airport_Areas.airport_id = $airport_id";
		$options[TONIC_FIND_STATIC_FIELD] = " Airport_Areas_Merchants_Map.airport_area_id, Airport_Areas_Merchants_Map.location ";
		return $this->select($url,$options);	
		
    }
	
}
?>