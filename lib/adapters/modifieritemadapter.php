<?php

require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'modifiersizemapadapter.php';

class ModifierItemAdapter extends MySQLAdapter
{

	function ModifierItemAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Modifier_Item',
			'%([0-9]{1,15})%',
			'%d',
			array('modifier_item_id'),
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