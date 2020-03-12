<?php

class UserPasswordResetAdapter extends MySQLAdapter
{
	function UserPasswordResetAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'user_password_reset',
			'%([0-9]{1,15})%',
			'%d',
			array('reset_id'),
			NULL,
			array('retrieved','modified','created')
			);
	}
}
?>