<?php

class UserCreationDataAdapter extends MySQLAdapter
{

	function UserCreationDataAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'user_creation_data',
			'%([0-9]{5,12})%',
			'%d',
			array('user_id'),
			null,
			array('created','modified')
			);
	}
	
}
?>