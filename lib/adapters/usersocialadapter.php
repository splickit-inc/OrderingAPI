<?php

class UserSocialAdapter extends MySQLAdapter
{

	function UserSocialAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'User_Social',
			'%([0-9]{4,10})%',
			'%d',
			array('social_map_id'),
			null,
			array('created','modified')
		);
						
	}
	
	static function createUserSocialRecord($user_id,$user_social_data)
	{
		$user_social_adapter = new UserSocialAdapter($mimetypes);
		$user_social_data['user_id'] = $user_id;
		if ($user_social_resource = Resource::createByData($user_social_adapter, $user_social_data)) {
			return $user_social_resource;
		} else {
			MailIt::sendErrorEmail("ERROR SAVING USER SOCIAL INFO", "sql error: ".$user_social_adapter->getLastErrorText());
			return false;
		}
	}
}

?>