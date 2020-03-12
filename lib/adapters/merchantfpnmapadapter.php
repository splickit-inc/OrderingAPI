<?php

class MerchantFPNMapAdapter extends MySQLAdapter
{

	function MerchantFPNMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_FPN_Map',
			'%([0-9]{4,11})%',
			'%d',
			array('map_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
    
    function getMerchantFPNMapRecord($merchant_id)
    {
    	
    	$data['merchant_id'] = $merchant_id;
    	if ($result = $this->getRecord($data))
    		return $result;
    	else
    	{
    		myerror_log("ERROR! unable to find Merchant_FPN_Map record for merchant_id: ".$merchant_id);
    		return false;
    	}
    }
	
}
?>
