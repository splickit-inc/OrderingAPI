<?php

class AndroidTestPushDataAdapter extends MySQLAdapter
{

	function AndroidTestPushDataAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'android_test_push_data',
			'%([0-9]{3,10})%',
			'%d',
			array('id'),
			null,
			array('created')
			);
						
	}
	
	static function insertRecord($message_title,$message_text)
	{
		$atpd_adapter = new AndroidTestPushDataAdapter($mimetypes);
		$message_data['message_title'] = $message_title;
		$message_data['message_text'] = $message_text;
		$atpd_resource = Resource::factory($atpd_adapter,$message_data);
		if ($atpd_resource->save())
		{
			return $atpd_adapter->_insertId();
		}
		else
			return false;
	}

}
?>