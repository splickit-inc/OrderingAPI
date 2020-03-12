<?php

class MerchantHeartlandInfoMapsAdapter extends MySQLAdapter
{

	function MerchantHeartlandInfoMapsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_Heartland_Info_Maps',
			'%([0-9]{4,10})%',
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
	
	static function getHeartlandStoreId($merchant_id)
	{
		$mhima = new MerchantHeartlandInfoMapsAdapter($mimetypes);
		if ($record = $mhima->getRecord(array("merchant_id"=>$merchant_id), $options)) {
			return $record['heartland_store_id'];
		} else if (isNotProd()) {
            return 'us-1000';
        }
		$message = ("***** ERROR!  NO PITA PIT CREDENTIALS FOR merchant_id: $merchant_id ********");
		myerror_log($message);
		MailIt::sendErrorEmailSupport("ERROR! No Heartland Merchant Credential Set up", $message); 
	}

}
?>