<?php

class AdmFlurryDataAdapter extends MySQLAdapter
{

	function AdmFlurryDataAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Adm_Flurry_Data',
			'%([0-9]{1,15})%',
			'%d',
			array('flurry_data_id'),
			null,
			array('created')
			);
	}
		
}
?>