<?php

class PropertyAdapter extends MySQLAdapter
{

	function PropertyAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Property',
			'%([0-9]{1,8})%',
			'%d',
			array('id'),
			array('id','name','value','created','modified'),
			array('created','modified')
		);
		
		$this->allow_full_table_scan = true;
	}
		
}
?>