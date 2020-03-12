<?php

class VioCreditCardProcessorsAdapter extends MySQLAdapter
{

	function VioCreditCardProcessorsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Vio_Credit_Card_Processors',
			'%([0-9]{4,10})%',
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
	
	static function staticGetCCPaymentProcessorsHashWithNameAsKey()
	{
		$psa = new VioCreditCardProcessorsAdapter($mimetypes);
		return $psa->getCCPaymentProcessorsHashWithNameAsKey();	
	}
	
	function getCCPaymentProcessorsHashWithNameAsKey()
	{
		$records = $this->getRecords(array(), $options);
		foreach ($records as $record) {
			$better_hash[$record['name']] = $record;
		}
		return createLowercaseHashmapFromMixedHashmap($better_hash);
	}
}
?>