<?php

class AdminMenuAdapter extends MySQLAdapter
{

	function AdminMenuAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Menu',
			'%([0-9]{1,8})%',
			'%d',
			array('menu_id'),
			NULL
			);
		$this->allow_full_table_scan = true;
	}
	
}
?>