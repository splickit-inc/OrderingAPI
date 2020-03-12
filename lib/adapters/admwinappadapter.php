<?php

class AdmWinappAdapter extends MySQLAdapter
{

	function AdmWinappAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'adm_winapp',
			'%([0-9]{1,15})%',
			'%d',
			array('merchant_id'),
			null,
			array('created','modified')
			);
	}

}
?>