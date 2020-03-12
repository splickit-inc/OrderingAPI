<?php

class MerchantFoundryInfoMapsAdapter extends MySQLAdapter
{

	function MerchantFoundryInfoMapsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_Foundry_Info_Maps',
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

	static function getMerchantFoundryInfoMap($merchant_id)
    {
        $mfima = new MerchantFoundryInfoMapsAdapter(getM());
        if ($record = $mfima->getRecord(['merchant_id'=>$merchant_id])) {
            return $record;
        }
    }

    static function getMerchantFoundryInfoMapAsResource($merchant_id)
    {
        $mfima = new MerchantFoundryInfoMapsAdapter(getM());
        $options[TONIC_FIND_BY_METADATA] = ['merchant_id'=>$merchant_id];
        if ($mfim_resource = Resource::find($mfima,null,$options)) {
            return $mfim_resource;
        } else {
            return null;
        }
    }


}
?>