<?php

class FixedTaxAdapter extends MySQLAdapter
{

	function FixedTaxAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Fixed_Tax',
			'%([0-9]{1,15})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
	}
	
    static function createTaxRecord($merchant_id,$name,$amount)
    {
		$tax_adapter = new FixedTaxAdapter($mimetypes);
		$tax_data['merchant_id'] = $merchant_id;
		$tax_data['name'] = $name;
		$tax_data['amount'] = $amount;
		$tax_data['description'] = $name;
		return Resource::createByData($tax_adapter, $tax_data);
    }
	
    function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
    
    static function staticGetFixedTaxRecordsHashMappedByName($merchant_id)
    {
    	$fta = new FixedTaxAdapter($mimetypes);
    	return $fta->getFixedTaxRecordsHashMappedByName($merchant_id);
    }
    
    function getFixedTaxRecordsHashMappedByName($merchant_id)
    {
    	$hash_map = array();
    	$records = $this->getRecords(array('merchant_id'=>$merchant_id), $options);
    	foreach ($records as $record) {
    		$hash_map[$record['name']] = $record['amount'];
    	}
    	return $hash_map;
    }
}
?>
