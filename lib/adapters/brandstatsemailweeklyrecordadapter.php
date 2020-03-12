<?php

class BrandStatsEmailWeeklyRecordAdapter extends MySQLAdapter
{

	function BrandStatsEmailWeeklyRecordAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'brand_stats_email_weekly_record',
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

}
?>