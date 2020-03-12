<?php

class SplickitAcceptedPaymentTypesAdapter extends MySQLAdapter
{

	function __construct($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Splickit_Accepted_Payment_Types',
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
	
	static function getAllIndexedById()
	{
		$sapta = new SplickitAcceptedPaymentTypesAdapter($mimetypes);
		$records = $sapta->getRecords(array(), $options);
		foreach ($records as $record) {
			$better_array[$record['id']] = $record;
		}
		return $better_array;
	}
	
}
?>