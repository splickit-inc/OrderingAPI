<?php

class MessageAdapter extends MySQLAdapter
{

	function MessageAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Message',
			'%([0-9]{1,15})%',
			'%d',
			array('message_id')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
	
}
?>