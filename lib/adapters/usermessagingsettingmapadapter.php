<?php

class UserMessagingSettingMapAdapter extends MySQLAdapter
{

	function UserMessagingSettingMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'User_Messaging_Setting_Map',
			'%([0-9]{1,15})%',
			'%d',
			array('map_id'),
			null,
			array('created','modified')
			);
	}
	
	function createRecord($user_id,$skin_id,$messaging_type,$device_type,$device_id,$token,$active)
	{
		$data['user_id'] = $user_id;
		$data['skin_id'] = $skin_id;
		$data['messaging_type'] = $messaging_type;
		$data['device_type'] = $device_type;
		$data['device_id'] = $device_id;
		$data['token'] = $token;
		$data['active'] = $active;
		$resource = Resource::factory($this,$data);
		if ($resource->save())
		{
			$id = $this->_insertId();
			return $id;
		}
		return false;
	}
	
}
?>