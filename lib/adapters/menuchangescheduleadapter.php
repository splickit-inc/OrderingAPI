<?php

class MenuChangeScheduleAdapter extends MySQLAdapter
{

	function MenuChangeScheduleAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Menu_Change_Schedule',
			'%([0-9]{1,15})%',
			'%d',
			array('menu_change_id'),
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