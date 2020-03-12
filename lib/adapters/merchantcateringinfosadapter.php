<?php

class MerchantCateringInfosAdapter extends MySQLAdapter
{

	function MerchantCateringInfosAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_Catering_Infos',
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

    static function getInfoAsResourceByMerchantId($merchant_id)
    {
        $mcia = new MerchantCateringInfosAdapter();
        $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
        return Resource::find($mcia,null,$options);
    }

}
?>