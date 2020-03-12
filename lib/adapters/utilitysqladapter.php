<?php

class UtilitySQLAdapter extends MySQLAdapter
{

	function UtilitySQLAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'utility_sql',
			'%([0-9]{1,15})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
	
}
?>