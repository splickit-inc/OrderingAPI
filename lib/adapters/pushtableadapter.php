<?php

class PushTableAdapter extends MySQLAdapter
{

	function PushTableAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'smawv_push_tables',
			'%([0-9]{3,10})%',
			'%d',
			array('table_name')
			);
        $this->allow_full_table_scan = true;
	}
	
}
?>
