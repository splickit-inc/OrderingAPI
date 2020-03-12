<?php

class MenuComboAssociationAdapter extends MySQLAdapter
{

	function MenuComboAssociationAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Menu_Combo_Association',
			'%([0-9]{4,11})%',
			'%d',
			array('combo_association_id'),
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