<?php

class AdmFlurryKeyAdapter extends MySQLAdapter
{

	function AdmFlurryKeyAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Adm_Flurry_Key',
			'%([0-9]{1,15})%',
			'%d',
			array('flurry_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
	
}
?>