<?php

class MerchantMessageMapAdapter extends MySQLAdapter
{

	function MerchantMessageMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_Message_Map',
			'%([0-9]{1,15})%',
			'%d',
			array('map_id'),
			null,
			array('created','modified')
			);
	}
	
	static function createMerchantMessageMap($merchant_id,$message_format,$delivery_address,$message_type)
	{
		//message map
		$mmm_adapter = new MerchantMessageMapAdapter($mimetypes);
		$mmm_data['merchant_id'] = $merchant_id;
		$mmm_data['message_format'] = $message_format;
		$mmm_data['delivery_addr'] = $delivery_address;
		$mmm_data['message_type'] = $message_type;
		$mmm_resource = Resource::factory($mmm_adapter,$mmm_data);
		if ($mmm_resource->save())
			return $mmm_resource;//all is good
		else
			return returnErrorResource("could not create merchant message map: ".$mmm_adapter->getLastErrorText());
	}
	
	static function isMerchantMatreDMerchant($merchant_id)
	{
		$mmm_data['merchant_id'] = $merchant_id;
		$mmm_data['message_format'] = 'WM';
		$mmm_adapter = new MerchantMessageMapAdapter($mimetypes);
		if ($record = $mmm_adapter->getRecord($mmm_data)) {
			return true;
		} else {
			return false;
		}		
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
    
    static function doesMerchantHaveMessagesSetUp($merchant_id)
    {
    	$mmm_adapter = new MerchantMessageMapAdapter($mimetypes);
    	if ($records = $mmm_adapter->getRecords(array("merchant_id"=>$merchant_id), $options)) {
    		return true;
    	} else {
    		return false;
    	}
    }

    static function getSMSNumberForMerchant($merchant_id)
    {
        $mmm_adapter = new MerchantMessageMapAdapter($mimetypes);
        if ($text_map_record = $mmm_adapter->getRecord(array("merchant_id"=>$merchant_id,"message_format"=>'T',"message_type"=>'A'))) {
            return $text_map_record['delivery_addr'];
        }
    }

}
?>