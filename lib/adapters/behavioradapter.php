<?php

class BehaviorAdapter extends MySQLAdapter
{

	function BehaviorAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Behavior',
			'%^/behaviors/([0-9]+)%',
			'%d',
			array('behavior_id'),
			array('behavior_id','behavior_name','behavior_description')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
	
}
?>