<?php

class PushActivity extends SplickitActivity
{
	protected $server;
	
	function PushActivity($activity_history_resource)
	{
		myerror_log("Creating PushActivity");
		parent::SplickitActivity($activity_history_resource);
	}

	function doit() {

		$is_a_test_push = false;
		if (isset($this->data['test']) && $this->data['test'] = 'true')
			$is_a_test_push = true;
		$sql_id = $this->data['sql_id'];
		$table_name =  $this->data['table_name'];
		$skin = $this->data['skin'];
		$time_back_in_seconds = $this->data['time_back_in_seconds'];
		$send_time = time()-$time_back_in_seconds;
		
		if ($table_name || $sql_id)
		{
			
			if ($table_name != null)
			{
				$skin_options[TONIC_FIND_BY_METADATA]['external_identifier'] = $skin;
				if ($skin_resource =& Resource::findExact(new SkinAdapter($mimetypes),'',$skin_options))
				{
					$skin_id = $skin_resource->skin_id;
				}
				else
				{
					myerror_log("there was no matching external id submitted");
					$error_message = "there was no matching external id submitted";
					$error_text = "The skin does not exist";
					MailIt::sendErrorEmail('ERROR! NO MATCHING SKIN ID FOR PUSH MESSAGE ACTIVITY', "the external identifier does not exist: ".$push_sql_query_resource->skin, $from_name, $bcc, $attachments);
					return false;
				}
					
				$message = $this->activity_history_resource->activity_text;
				$push_records_sql_iphone = "SELECT User_Messaging_Setting_Map.* FROM User_Messaging_Setting_Map JOIN $table_name ON $table_name.user_id = User_Messaging_Setting_Map.user_id WHERE skin_id = $skin_id AND messaging_type = 'push' AND active = 'Y' AND device_type = 'iphone' ORDER BY User_Messaging_Setting_Map.user_id";
				$push_records_sql_android = "SELECT User_Messaging_Setting_Map.* FROM User_Messaging_Setting_Map JOIN $table_name ON $table_name.user_id = User_Messaging_Setting_Map.user_id WHERE skin_id = $skin_id AND messaging_type = 'push' AND active = 'Y' AND device_type = 'gcm' ORDER BY User_Messaging_Setting_Map.user_id";
				
			} else {
			
				if (!$push_sql_query_resource =& Resource::find(new PushSQLQueryAdapter($this->mimetypes),''.$sql_id))
				{
					$this->error_text = "No PushSQLQuery matching this id: ".$sql_id;
					return false;
				}	
				$user_sql = $push_sql_query_resource->sql_text;
				$skin = $push_sql_query_resource->skin;
				if ($message = $push_sql_query_resource->message_text)
					myerror_log("about to push message: ".$message);
				else
				{
					$this->error_text = "no message indicated in push_sql_query_record";
					return false; 
				}
				$query_name = $push_sql_query_resource->query_name;
				myerror_log("the sql to select the users for push is: ".$query_name."  -  ".$user_sql);
				
				$skin_options[TONIC_FIND_BY_METADATA]['external_identifier'] = $push_sql_query_resource->skin;
				if ($skin_resource =& Resource::findExact(new SkinAdapter($mimetypes),'',$skin_options))
				{
					$skin_id = $skin_resource->skin_id;
				}
				else
				{
					$myerror_log("there was no matching external id submitted");
					$error_message = "there was no matching external id submitted";
					$error_text = "The skin does not exist";
					MailIt::sendErrorEmail('ERROR! NO MATCHING SKIN ID FOR PUSH MESSAGE ACTIVITY', "the external identifier does not exist: ".$push_sql_query_resource->skin, $from_name, $bcc, $attachments);
					return false;
				}
				
				$push_records_sql_iphone = "SELECT * FROM User_Messaging_Setting_Map a JOIN ($user_sql) b ON a.user_id = b.user_id WHERE a.skin_id = $skin_id AND a.messaging_type = 'push' AND a.active = 'Y' AND device_type = 'iphone'";
				$push_records_sql_android = "SELECT * FROM User_Messaging_Setting_Map a JOIN ($user_sql) b ON a.user_id = b.user_id WHERE a.skin_id = $skin_id AND a.messaging_type = 'push' AND a.active = 'Y' AND device_type = 'gcm'";
				
			}

			if (isset($this->data['user_id'])) {
				$user_id = $this->data['user_id'];
				myerror_log("resetting user query to static user id of: ".$user_id);
				$push_records_sql_iphone = "SELECT * FROM User_Messaging_Setting_Map WHERE skin_id = $skin_id AND messaging_type = 'push' AND active = 'Y' AND user_id = $user_id  AND device_type = 'iphone'";
				$push_records_sql_android = "SELECT * FROM User_Messaging_Setting_Map WHERE skin_id = $skin_id AND messaging_type = 'push' AND active = 'Y' AND user_id = $user_id AND device_type = 'gcm'";
			} 

			$user_messaging_setting_map_adapter = new UserMessagingSettingMapAdapter($mimetypes);
			
			if ($this->data['device'] == 'android')
				$push_records_sql_iphone = null;
			if ($this->data['device'] == 'iphone')
				$push_records_sql_android = null;

			if ($push_records_sql_iphone != null)
			{	
				$umsma_options[TONIC_FIND_BY_SQL] = $push_records_sql_iphone;
				if ($user_messaging_records = $user_messaging_setting_map_adapter->select('',$umsma_options))
				{
					$push_iphone = new PushIphone($skin, $this->mimetypes,$is_a_test_push);
					$push_iphone->push($user_messaging_records,$message);
					$this->error_text = "iPhone successful. ";
				} else if ($user_messaging_setting_map_adapter->getLastErrorNo() > 0) {
					$this->error_text = $user_messaging_setting_map_adapter->getLastErrorText();
					return false;
				}	
			}
	
			if ($push_records_sql_android != null)
			{
				$umsma_options[TONIC_FIND_BY_SQL] = $push_records_sql_android;
				if ($user_messaging_records = $user_messaging_setting_map_adapter->select('',$umsma_options))
				{
					$push_android = new PushAndroid($skin_id, $this->mimetypes,$is_a_test_push);
					$push_android->stagePush($user_messaging_records,$message,$send_time);	
				} else if ($user_messaging_setting_map_adapter->getLastErrorNo() > 0) {
					$this->error_text .= "Android Failure: ".$user_messaging_setting_map_adapter->getLastErrorText();
					return false;
				}
			}
			return true;
		}
		else
		{
			$error_text = "no push_sql_query id submitted";
			return false;
		}
	}
}
