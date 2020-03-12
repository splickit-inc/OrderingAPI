<?php

class CustomerServiceResultsAdapter extends MySQLAdapter
{

	function CustomerServiceResultsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'customer_service_results',
			'%([0-9]{1,10})%',
			'%d',
			array('id'),
			null,
			array('created')
			);
	}

}
?>