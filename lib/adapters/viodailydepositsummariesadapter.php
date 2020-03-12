<?php

class VioDailyDepositSummariesAdapter extends MySQLAdapter
{

	function VioDailyDepositSummariesAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'vio_daily_deposit_summaries',
			'%([0-9]{1,15})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
        );
	}
		
}
?>