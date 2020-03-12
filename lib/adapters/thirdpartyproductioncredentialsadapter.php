<?php

class ThirdPartyProductionCredentialsAdapter extends MySQLAdapter
{

	function ThirdPartyProductionCredentialsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Third_Party_Production_Credentials',
			'%([0-9]{4,10})%',
			'%d',
			array('id'),
			array('id','name','value'),
			array('created','modified')
			);
		
		$this->allow_full_table_scan = true;
						
	}
}
?>