<?php
class DbMessageLogAdapter extends MySQLAdapter
{

	function DbMessageLogAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'db_message_log',
			'%([0-9]{1,10})%',
			'%d',
			array('log_id'),
			null,
			array('created')
			);
		
		$this->allow_full_table_scan = true;
						
	}	
	
	static function insertRecordFromMessageResource($message_resource)
	{
		$dbml_adapter = new DbMessageLogAdapter($mimetypes);
		$message_data['message_id'] = $message_resource->map_id;
		$message_data['message_text'] = $message_resource->message_text;
		$message_data['stamp'] = getStamp();
		$dbml_resource = Resource::factory($dbml_adapter,$message_data);
		if ($dbml_resource->save())
			return true;
		else
			return false;
	}
	
}
?>