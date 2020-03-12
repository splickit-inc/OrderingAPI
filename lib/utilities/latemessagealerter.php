<?php

class LateMessageAlerter
{
	var $message_resource;
	var $base_order_resource;
	var $task;
	var $result;
	var $mmha;
	var $error;
	var $action;
	var $file_adapter;
	var $message_info_data;
	var $message_info_data_lowercase_keys;
	
	function LateMessageAlerter()
	{
		$this->mmha  = new MerchantMessageHistoryAdapter($mimetypes);
		$this->file_adapter = new FileAdapter($mimetypes, 'resources');
	}

	/**
	 * @desc Reterns all the GRPS messages that have been sent but not marked as viewed
	 */
	
	static function getUnviewedMessages()
	{
		$ten_minutes_ago = date("Y-m-d H:i:s",time()-600);
		$one_minute_ago = date("Y-m-d H:i:s",time()-90);
		$five_minutes_ago = date("Y-m-d H:i:s",time()-300);
		//$sql = "SELECT * FROM Merchant_Message_History WHERE sent_dt_tm > '$ten_minutes_ago' AND sent_dt_tm < '$one_minute_ago' AND locked = 'S' AND viewed = 'N' AND order_id > 999 AND message_format LIKE 'GU%' AND logical_delete = 'N'";
		$sql = "SELECT * FROM Merchant_Message_History WHERE sent_dt_tm > '$ten_minutes_ago' AND sent_dt_tm < '$one_minute_ago' AND locked = 'S' AND viewed = 'N' AND order_id > 999 AND logical_delete = 'N'";
		$options[TONIC_FIND_BY_SQL] = $sql;
		if ($unviewed_message_resources = Resource::findAll(new MerchantMessageHistoryAdapter($mimetypes),null,$options))
			return $unviewed_message_resources;
		return false;
	}
	
	/**
	 * @desc Alert Support for unvieweed message
	 */
	
	static function processUnviewedMessages()
	{
		$five_minutes_ago = date("Y-m-d H:i:s",time()-300);
		if ($unviewed_message_resources = LateMessageAlerter::getUnviewedMessages())
		{
			myerror_log("WE HAVE MASSAGES THAT HAVE NOT BEEN MARKED AS VIEWED!  number of messages is: ".sizeof($unviewed_message_resources, $mode));
			foreach ($unviewed_message_resources as $unviewed_message_resource)
			{
				myerror_log("processing message id: ".$unviewed_message_resource->map_id);
				$order_id = $unviewed_message_resource->order_id;
				$order_resource = Resource::find(new OrderAdapter($mimetypes),''.$order_id);
				
				$merchant_id = $unviewed_message_resource->merchant_id;
				$merchant_resource = Resource::find(new MerchantAdapter($mimetypes),''.$merchant_id);
				
				$test_message="";
				if ($merchant_resource->active == "N")
					$test_message = "TEST ";
				else if ($order_resource->user_id < 20000)
					$test_message = "TEST USER ";
				$message_format = substr($unviewed_message_resource->message_format,0,1);
				if ($message_format == 'T' || $message_format == 'E')
					continue;
				else if ($message_format == 'G')
				{
					if ($unviewed_message_resource->tries == 1)
					{
						//$this->action = "resend activation text";
						$sms_no = $unviewed_message_resource->message_delivery_addr;
						$unviewed_message_resource->sent_dt_tm = '0000-00-00 00:00:00';
						$unviewed_message_resource->locked = 'P';
						$unviewed_message_resource->tries = 101;
						$unviewed_message_resource->message_text = 'nullit';
						$unviewed_message_resource->save();
						SmsSender2::send_sms($sms_no, '***');
						//$sms_message = $test_message."About to resend a no call back message.".chr(10)."order_id: ".$order_id.chr(10).$merchant_resource->name.chr(10)."phone: ".$merchant_resource->phone_no;
						//MailIt::sendEmailSingleRecepientWithValidation("adam@dummy.com", "About to resend a no call back!", $sms_message, $from_name, $bcc, $attachments);
					} else {
						//$this->action = "alert support: GPRS message sent and resent with NO call back";
						$sms_message = $test_message."GPRS message sent and resent with NO call back.".chr(10)."order_id: ".$order_id.chr(10).$merchant_resource->name.chr(10)."phone: ".$merchant_resource->phone_no;
						//MailIt::sendEmailSingleRecepientWithValidation("adam@dummy.com", "no call back after resend!", $sms_message, $from_name, $bcc, $attachments);
						MailIt::sendErrorEmailSupport("GPRS message sent and resent with NO call back", $sms_message);
						SmsSender2::sendAlertListSMS($sms_message);
					}
				} else if ($message_format == 'F' && $unviewed_message_resource->sent_dt_tm > $five_minutes_ago) {
					myerror_logging(3, "we have an unviewed fax but its less than 5 minutes old so wait"); // do nothing and wait
					//$this->action = "we have an unviewed fax but its less than 5 minutes old so wait";
				} else {
					$sms_message = $test_message."We have an ".$message_format." message sent with NO call back.".chr(10)."order_id: ".$order_id.chr(10).$merchant_resource->name.chr(10)."phone: ".$merchant_resource->phone_no;
					//MailIt::sendEmailSingleRecepientWithValidation("adam@dummy.com", "no call back after resend!", $sms_message, $from_name, $bcc, $attachments);
					MailIt::sendErrorEmailSupport($message_format." message sent and resent with NO call back", $sms_message);
					SmsSender2::sendAlertListSMS($sms_message);
					//$this->action = "alert support: ".$message_format." message sent and resent with NO call back";					
				}
			}
		} else {
			myerror_log("we have no unviewed message older than 1 minute");
		}
		
		//static method used for an activity so must return true is all exectuted ok.
		return true;
		
	}

	/**
	 * @desc This takes a message resource that has already been determined as late and processes it. currently only works on GRPS version 7.0 or greater
	 * @param Resource $message_resource
	 */
	
	function processLateMessage($message_resource)
	{
		myerror_log("************* STARTING NEW LateMessageAlerterCode! ******************");
		$result = array();
		$this->message_resource = $message_resource;
		if ($base_order_resource = CompleteOrder::getBaseOrderDataAsResource($message_resource->order_id, $mimetypes))
		{
			$this->base_order_resource = $base_order_resource;
		} else {
			$this->error = "ERROR!  COULD NOT LOCATE ORDER RESOURCE IN LateMessageAlerter";
			myerror_log("ERROR!  COULD NOT LOCATE ORDER RESOURCE IN LateMessageAlerter");
			return false;
		}
		$format = substr($message_resource->message_format, 0,1);
		if ($format == 'G')
			$this->processLateGPRSMessage($message_resource);
		else
		{
			$this->error = "ERROR!  LateMessageAlerter not set up for $format yet. BUILD IT!";
			myerror_log("ERROR!  LateMessageAlerter not set up for $format yet. BUILD IT!");
			return false;
		}	

	}
	
	private function processLateGPRSMessage($message_resource)
	{
			$mmh_adapter = $this->mmha;
			$info_data = $this->message_info_data_lowercase_keys;
			//get firware version from message
			$firmware = (isset($info_data['firmware'])) ? $info_data['firmware'] : ' is pre 5.0';
			if ($this->base_order_resource->user_id < 20000) {
				$test_user_string = ' TEST USER ';
			}
			$result = "Call Center Off! Support Alerted!";
			// so we need to alert us!
			$script = 'script1';
			$this->alertSupportOfMessageFailure("We have a firmware $firmware GPRS device off line!");
			$this->result[1] = "Call Center Off. Support Alerted";
			$this->addMerchantMessageHistoryRowForCallCenterMessage($xml_body, $result, $script);

			
	}
	
	function alertSupportOfMessageFailure($message_text)
	{
		
		$merchant_name = $this->base_order_resource->merchant_name;
		$addr = $this->base_order_resource->merchant_addr;
		$city_st_zip = $this->base_order_resource->merchant_city_st_zip;
		$phone = $this->base_order_resource->merchant_phone_no;
		$merchant_id = $this->base_order_resource->merchant_id;
		$user_id = $this->base_order_resource->user_id;
		$order_id = $this->base_order_resource->order_id;

		// do not alert for admin users
		if ($user_id < 100)
			return true;
		else if ($user_id < 20000)
			$test_message = 'TEST USER ';

		$top = '';
		$top .= "order_id = ".$order_id."<br>";
		$top .= "merchant_id = ".$merchant_id."<br>";
		$top .= "merchant = ".$merchant_name."<br>";
		$top .= $addr."<br>"; 
		$top .= $city_st_zip."<br>";
		$top .= $phone."<br>";
		$top .= "<p>";
		$top .= "user_id = ".$this->base_order_resource->user_id."<br>";
		$top .= "email = ".$this->base_order_resource->user_email;

		$sms_message = $test_message.$message_text.chr(10).'phone= '.$phone.chr(10).'order_id='.$order_id.chr(10).''.$merchant_name.chr(10).$addr.chr(10).$city_st_zip;
				
		$message = '<html></body>'.$test_message.$message_text.'<p><p>'.$top.'</body></html>';

		MailIt::sendErrorEmailSupport($test_message.$message_text, $message);
		myerror_logging(1, "WE are in the alertSupportOfMessageFailure() of late message alerter, about to check the user_id: ".$user_id);
		SmsSender2::sendAlertListSMS($sms_message);
	}

	function addMerchantMessageHistoryRowForCallCenterMessage($xml_body,$result,$script)
	{
		$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
		
		//first check to see if a row exists already
		$mmh_data = array();
		$mmh_data['merchant_id'] = $this->message_resource->merchant_id;
		$mmh_data['order_id'] = $this->message_resource->order_id;
		$mmh_data['message_format'] = 'CC';
		$mmh_data['message_type'] = 'A';
		$mmh_data['message_delivery_addr'] = $script;
		$mmh_data['info'] = $result;
		if ($mmh_adapter->getRecord($mmh_data))
		{
			myerror_log("row already exists, do not add another");
			return;
		}	
		
		$mmh_data['next_message_dt_tm'] = time();
		$mmh_data['sent_dt_tm'] = date("Y-m-d H:i:s");
		$mmh_data['stamp'] = getStamp();
		$mmh_data['locked'] = 'S';
		$mmh_data['tries'] = '1';
		$mmh_data['message_text'] = $xml_body;
		$mmh_resource = Resource::factory($mmh_adapter,$mmh_data);
		$mmh_resource->save();

		// now bump up the tries on the original message
		$this->message_resource->tries = $this->message_resource->tries + 1;
		$this->message_resource->save();
	}

	function setBaseVars($message_resource)
	{
		$this->message_resource = $message_resource;
		$this->setBaseOrderResource($message_resource->order_id);
	}
	
	function setBaseOrderResource($order_id)
	{
		$base_order_resource = CompleteOrder::getBaseOrderDataAsResource($order_id, $mimetypes);
		$this->base_order_resource = $base_order_resource;
		
	}
	
	function getResult()
	{
		return $this->result;
	}

	function chooseActionForLateNewFirmwarePrinterMessage(&$message_resource)
	{
		myerror_log("We have a new firmware printer off line!");
		if (ifLogLevelIsGreaterThan(3))
			Resource::encodeResourceIntoTonicFormat($message_resource);
		
		// ok we have a firmware 7.0 off line. so send alerts and retext.
		if (strtolower(substr($this->message_info_data_lowercase_keys['firmware'],-2)) == 'ip') {
			$this->action = 'processLateMessage';
			$this->processLateMessage($message_resource);
		} else if ($message_resource->tries < 1) {
			$this->action = 'stageNewGPRSactiviationMessage';
			TextController::stageNewGPRSactiviationMessage($message_resource);
		} else {
			$this->action = 'processLateMessage';
			$this->processLateMessage($message_resource);
		}
		
	}
	
	/**
	 * 
	 * @desc will check for late by more than 20 min, then check if merchant is innactive, then check if printer is backed up
	 * @param $message_resource
	 */
	function checkForAlertBypassIsTrue($message_resource)
	{
	    if ($message_resource->message_format == 'P') {
	        myerror_log("skip failing the message after 20 minutes check for portal delivery method");
        } else if ($this->mmha->checkIfMessageIsLateByThisManyMinutesAndFailIfSo(20, $message_resource)) {
			return true;;  
		}
		
		// get merchant info
		$merchant_id = $message_resource->merchant_id;
		$merchant_resource =& Resource::find(new MerchantAdapter($mimetypes),"$merchant_id");
		if ($merchant_resource->active == 'N' || $merchant_id < 1000) {
			myerror_log("merchant is not an active merchant so we will skip");
			return true;
		}
		return false;
	}
	
	function extractAndSetMessageInfoData($message_resource)
	{
		$info_data = $this->mmha->getMesageInfoData($message_resource);
		$this->message_info_data = $info_data;
		$this->message_info_data_lowercase_keys = createLowercaseHashmapFromMixedHashmap($info_data);
		return $info_data;
	}
	
	/**
	 * 
	 * @desc to be called by the activity 
	 */
	static function staticCheckAndProcessLatePulledMessages()
	{
		$lma = new LateMessageAlerter();
		$lma->checkAndProcessLatePulledMessages();
		return true;
	}
	
	function checkAndProcessLatePulledMessages()
	{
		myerror_log("****************** check for unpicked up pulled message types **********************");
		date_default_timezone_set(getProperty("default_server_timezone"));
		$failover_time = intval(getProperty('gprs_late_threshold_in_seconds'));
		myerror_log("about to check for late GPRS with a failover time of $failover_time seconds");
		$failover_date_string = date('Y-m-d H:i:s',(time()-$failover_time));	
		$texted_merchants = array();
		//lets get all the pulled 'X' message that are late with alert set to true
		if ($messages = $this->mmha->getLatePulledMessages($failover_date_string,true)) {
			$text_controller = new TextController(getM(), null, $r);
			foreach ($messages as $message_resource) {
				myerror_log("********* starting process of undelivered message ".$message_resource->map_id." ***********   order_id: ".$message_resource->order_id);
				
				// check if we should skip becuase of backed up or innactive merchant or
				if ($this->checkForAlertBypassIsTrue($message_resource)) {
					continue;
				}

				$merchant_id = $message_resource->merchant_id;
				$message_type = substr($message_resource->message_type,0,1);
				$base_format = substr($message_resource->message_format,0,1);
				$device_name = ControllerFactory::getControllerNameFromMessageResource($message_resource);
				myerror_log("WE HAVE A $device_name MESSAGE THATS LATE!  START ESCALATION LOGIC! merchant_id: $merchant_id message_history_map_id: ".$message_resource->map_id);
				$base_order_resource = CompleteOrder::getBaseOrderDataAsResource($message_resource->order_id, getM());
				
				$test_user = '';
				$use_test = false;
				if ($base_order_resource->user_id < 20000)
				{
					$test_user = 'XXXXXX  TEST USER  XXXXXX  ';
					$use_test = true; // used for submittion to the sms sender object so it knows that this is a test user and use the test group
				}

                $dcha = new DeviceCallInHistoryAdapter(getM());
                $time_stamp_of_last_call_in = $dcha->getLastCallInAsIntegerByMerchantId($merchant_id);
                $minutes_since_last_call_in = (time() - $time_stamp_of_last_call_in)/60;
                $last_call_in_string = "   The device has not called in for $minutes_since_last_call_in minutes";


                $body = $test_user.$base_order_resource->late_order_message_sms.$last_call_in_string;
				MailIt::sendErrorEmailSupport("$test_user  We HAVE AN $device_name OFF LINE!", $body);
				SmsSender2::sendAlertListSMS($body);
			}
			
		} else {
			myerror_log("no un-retrieved PULLED order messages");
		}
		
	}
	
}