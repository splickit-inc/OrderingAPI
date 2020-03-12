<?php

class SageBillingEntitiesAdapter extends MySQLAdapter
{

	function SageBillingEntitiesAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'smawv_sage_billing_entities',
			'%([0-9]{3,10})%',
			'%d',
			array('html_row')
			);
        $this->allow_full_table_scan = true;
	}
	
}
?>
