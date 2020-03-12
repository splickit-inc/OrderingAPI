 <?php

class PushSQLQueryAdapter extends MySQLAdapter
{

	function PushSQLQueryAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Push_SQL_Query',
			'%([0-9]{1,15})%',
			'%d',
			array('sql_id')
			);
	}
	
}
?>