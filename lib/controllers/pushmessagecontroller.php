<?php
/**
 * 
 * @desc Class used for processing ANDROID (mostly) push messages in the message que.
 * 
 * @author radamnyc
 *
 */
Class PushMessageController extends MessageController
{
	var $message_data;
	protected $max_retries = 0;

	const APPLE = 'iphone';
	const ANDROID = 'gcm';

    var $push_message_services = array();
		
	function PushMessageController($mt,$u,&$r,$l = 0)
	{
		parent::MessageController($mt,$u,$r,$l);
        $this->push_message_services[self::ANDROID] = new PushAndroidService();
		$this->push_message_services[self::APPLE] = new ApplePushNotificationService();
	}
	
	function prepMessageForSending($message_resource)
	{
		$resource = $this->populateMessageData($message_resource);
		return $resource;
	}
	
	public function populateMessageData($message_resource)
	{
		if ($info_string = $message_resource->info)
		{
			$fielddata = explode(';', $info_string);
			foreach ($fielddata as $datarow)
			{
				$s = explode('=', $datarow);
				$additional_data[$s[0]] = $s[1];
			}
			$message_resource->set("info_data",$additional_data);
			$this->message_data = $additional_data;
		}
		// this is messed up, why am i using message_id and not map_id?
		$message_resource->set('message_id',$message_resource->map_id);		
		return $message_resource;
	}
	
	function send($message_text)
	{
		//first get skin name for message title
		$skin_id = $this->message_data['skin_id'];
		$skin_adapter = new SkinAdapter($mimetypes);
		if ($skin_resource = Resource::find($skin_adapter,''.$skin_id))
			$message_title = $skin_resource->skin_name;
		else
			$message_title = 'Mobile Ordering';
			
		// check to see if there is a title
		if (substr_count($message_text, '#') > 0)
		{
			$message_data = explode('#', $message_text);
			$message_text = $message_data[0];
			$message_title = $message_title.' '.$message_data[1];
		} 
		
		$device_type = $this->message_data['device_type'];
		
		$this->push_message_services[$device_type]->prepareService($this->message_data);

        $response = $this->push_message_services[$device_type]->send(array('title' => $message_title, 'message' => $message_text)); 

		$this->message_resource->response = $response['raw_result'];

		myerror_log("PushMessageController response for ".$this->message_data['device_type']." push message to user_id: ".$this->message_data['user_id'].", is: ".$response);
		if (substr_count(strtolower($response), 'error'))
			throw new Exception("bad android send: ".$response, 100);
		//CHANGE_THIS
		// need to figure out exactly what the correct test is here.  cant use error as error is returned from a bad connection too.
		if (false)
		{
			myerror_log("we have a android failure so lets set the record to innactive for user_id ".$this->message_data['user_id']."    map_id: ".$this->message_data['map_id']);
			// question do we remove all records with this Token?  no set to innactive.
			$user_messaging_setting_map_adapter = new UserMessagingSettingMapAdapter($mimetypes);
			if ($umsm_resource = Resource::find($user_messaging_setting_map_adapter,''.$this->message_data['map_id']))
			{
				$umsm_resource->active = 'N';
				$umsm_resource->modified = time();
				$umsm_resource->save();
			}
		}
		return true;
	}
	
	static function staticPushMessageToUser($user_id,$message,$skin_id)
    {
        $pmc = new PushMessageController($m,$u,$r);
        return $pmc->pushMessageToUser($user_id,$message,$skin);
    }

    function pushMessageToUserFromRequest()
    {
        $this->request->_parseRequestBody();
        myerror_log("*******  starting pushmessage with changes to gcm ********");
        foreach ($this->request->data as $name=>$value)
            myerror_log("$name=$value");

        $user_ids = $this->request->data['users'];
        $message = $this->request->data['message'];
        $skin_external_identifier = $this->request->data['skin'];

        $skin_options[TONIC_FIND_BY_METADATA]['external_identifier'] = $skin_external_identifier;
        if ($skin_resource =& Resource::findExact(new SkinAdapter($mimetypes),'',$skin_options)) {
            $skin_id = $skin_resource->skin_id;
            if (is_array($user_ids)) {
                ;// all is good
            } else if (is_numeric($user_ids)) {
                $user_ids = array($user_ids);
            } else {
                throw new Exception("invalid user ids for push");
            }
            foreach ($user_ids as $user_id) {
                $this->pushMessageToUser($user_id,$message,$skin_id);
            }
        }
    }

    function pushMessageToUser($user_id,$message,$skin_id = 0)
	{
		if ($user_id < 20000) {
			myerror_log("ERROR! couldn't push message to user. user_id is either an admin user or user_id is null");
			return false;	
		}
		if ($skin_id < 1) {
			throw new Exception("Skin not set for push");
		}

		$user_messaging_setting_map_adapter = new UserMessagingSettingMapAdapter($mimetypes);
		$umsma_options[TONIC_FIND_BY_METADATA]['skin_id'] = $skin_id;
		$umsma_options[TONIC_FIND_BY_METADATA]['messaging_type'] = 'push';
		$umsma_options[TONIC_FIND_BY_METADATA]['active'] = 'Y';
		$umsma_options[TONIC_FIND_BY_METADATA]['user_id'] = $user_id;

        $umsma_options[TONIC_SORT_BY_METADATA] = 'map_id desc';

		if ($user_messaging_records = $user_messaging_setting_map_adapter->select('',$umsma_options)) {

			$last_active_devices_by_device_type = getUniqueElementsFromArrayByFieldValue($user_messaging_records,'device_type');

			foreach ($last_active_devices_by_device_type as $user_messaging_record) {
				$record_as_array = array();
				$record_as_array[] = $user_messaging_record;

				$this->stagePush($record_as_array,$message);

				unset($record_as_array);
			}
		}				
		
	}

    function stagePush($user_messaging_records,$message,$send_time = 0)
    {
        if ($send_time == 0) {
            $send_time = time();
        }
        if (sizeof($user_messaging_records) < 1) {
            myerror_log("ERROR!  NO USERS SELECTED FOR PUSH!");
            return false;
        }
        myerror_log("the size of hte records in push_android is: ".sizeof($user_messaging_records));

        // now we're going to load it up in the message table instead of sending each one now
        $mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);

        $i = 0;
        $f = 0;
        foreach ($user_messaging_records as $record) {
            //$info = "user_id=".$record['user_id'].";device_type=android;auth_code=".$auth_code.";user_messaging_setting_map_id=".$record['map_id'].";skin_id=".$this->skin_id;
            $info = sprintf("user_id=%s;device_type=%s;user_messaging_setting_map_id=%s;skin_id=%s",$record['user_id'], $record['device_type'],$record['map_id'],$record['skin_id']);
            if ($this->is_a_test_push) {
                $info = $info.";test=true";
            }
            if ($id = $mmh_adapter->createMessage($merchant_id, $order_id, 'Y', $record['token'], $send_time, 'I', $info, $message)) {
                myerror_log("message was created with id: $id");
                $i++;//all is good
            } else {
                myerror_log("ERROR! couldn't save push message record to db.  ".$mmh_adapter->getLastErrorText());
                $f++;
            }
        }
        myerror_log("there were $i messages staged in the db");
        myerror_log("there were $f message that failed to stage");
        return true;
    }

    function getTestTokenForAndroidSend()
    {
        return 'sumdumtoken';
    }
}
?>