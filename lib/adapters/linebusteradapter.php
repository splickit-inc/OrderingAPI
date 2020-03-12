<?php

class LineBusterAdapter extends MySQLAdapter
{

	function LineBusterAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant',
			'%([0-9]{8})%',
			'%d',
			array('numeric_id'),
			null,
			array('created','modified')
			);
	}
}

?>