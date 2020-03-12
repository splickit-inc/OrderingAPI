<?php

class LoyaltyAwardBrandTriggerAmountsAdapter extends MySQLAdapter
{

	function LoyaltyAwardBrandTriggerAmountsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Loyalty_Award_Brand_Trigger_Amounts',
			'%([0-9]{4,15})%',
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

}
?>