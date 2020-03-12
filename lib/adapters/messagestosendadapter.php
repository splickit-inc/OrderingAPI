<?php
class MessagesToSendAdapter extends MySQLAdapter
{

	function MessagesToSendAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'smawv_messages_to_send',
			null,
			null,
			null,
			null,
			array('next_message_dt_tm')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	return parent::select($url,$options);
    }

    function &update($resource)
    {
    	die ('method not allowed');
    }
    
    function &insert($resource)
    {
    	die ('method not allowed');
    }
}
?>