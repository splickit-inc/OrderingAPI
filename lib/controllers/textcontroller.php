<?php
Class TextController extends MessageController
{
	protected $representation = '/utility_templates/blank.txt';
		
	protected $format_array = array('T'=>'/utility_templates/blank.txt','TS'=>'/utility_templates/blank.txt');
	protected $format = 'T';
	protected $format_name = 'text';
	protected $max_retries = 3;
	protected $retry_delay = 1;
	protected $use_primary_sms;
	var $sob_activity_id;
		
	function TextController($mt,$u,&$r,$l = 0)
	{
		parent::MessageController($mt,$u,$r,$l);
		$this->use_primary_sms = $this->global_properties['use_primary_sms'];		
	}
	
	public function sendThisMessage($message_resource)
	{
		$merchant_id = $message_resource->merchant_id;
		//check to see if this is a Tsob
		if ($message_resource->message_format == 'Tsob')
		{
			myerror_logging(3,"starting the new Tsob code");
			$tsob_map_id = $message_resource->map_id;
			$gpcih_adapter = new GprsPrinterCallInHistoryAdapter($mimetypes);
			if ($gpcih_adapter->hasPrinterCalledInRecently($merchant_id, 600))
			{
				$message_resource->locked = 'C';
				$message_resource->save();
				return true;
			}	
			else
			{
				// ok send the message and then schedule the activity to check to see if the printer called in 
				if (parent::sendThisMessage($message_resource))
				{
					// successful send of SOB text so now scedule an activity
					$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
					$doit_ts = time()+120;
					$info = 'object=GprsPrinterCallInHistoryAdapter;method=checkForRecentCallInFromSobTextAndSendRoboCallIfNeeded;thefunctiondatastring='.$merchant_id.'';
					$id = $activity_history_adapter->createActivity('ExecuteObjectFunction', $doit_ts, $info, $activity_text);
					$this->sob_activity_id = $id;
					return true;
				}
				// should never get here since an exception shoudl be thrown if the text could not be sent
				return false;

			}
		}

		//first check to see if this is an activation messages
		if ($info_string = $message_resource->info)
		{
			$fielddata = explode(';', $info_string);
			foreach ($fielddata as $datarow)
			{
				myerror_log("got info data row of: ".$datarow);
				$s = explode('=', $datarow);
				$additional_data[$s[0]] = $s[1];
			}
			//$message_resource->set("info_data",$additional_data);
		}
		$firmware = (float) $additional_data['firmware'];
		if ($firmware >= 7.0) 
		{
			// determin if a GPRS was sent int eh last 90 seconds that has not called in yet.
			$message_data['merchant_id'] = $message_resource->merchant_id;
			
			// since we are searching for the GPRS message we need to set the merchant id to TEST since thats the only message that 
			// has the merchant id change for the test server or a prod_tester user.
			if ($this->global_properties['server'] == 'test')
				$message_data['merchant_id'] = 10;	
			$message_data['message_format'] = array("LIKE"=>"G%");
			$ts = time() - 120;
			$message_data['sent_dt_tm'] = array(">" => date('Y-m-d H:i:s',$ts));
			$message_data['viewed'] = "N";
			$options[TONIC_FIND_BY_METADATA] = $message_data;
			if ($results = Resource::find($this->adapter,'',$options))
			{
				myerror_log("GPRS has recently been picked up without a call back so delay the text by 30 seconds");
				$message_resource->next_message_dt_tm = time()+30;
				$message_resource->locked = 'N';
				$message_resource->modified = time();
				$message_resource->save();
				return true;
			} else {
				// next see if a text was sent in the last 30 seconds
				$message_data['merchant_id'] = $message_resource->merchant_id;
				$message_data['message_format'] = 'T';
				$ts = time() - 30;
				$message_data['sent_dt_tm'] = array(">" => date('Y-m-d H:i:s',$ts));
				$message_data['locked'] ='S';
				unset($message_data['viewed']);
				$options[TONIC_FIND_BY_METADATA] = $message_data;
				$results = Resource::findAll($this->adapter,'',$options);
				myerror_logging(3, "size of results is: ".sizeof($results, $mode));
				if (sizeof($results, $mode) > 0)
				{				
					
					$mmh_record_resource = $results[0];
					myerror_logging(3,"TEXT has recently gone out in last 30 seconds so delay the text by 30 seconds.  previously sent map_id: ".$mmh_record_resource->map_id);
					myerror_logging(3,"sent time is: ".$mmh_record_resource->sent_dt_tm);
					myerror_logging(3,"stamp is: ".$mmh_record_resource->stamp);
					$message_resource->next_message_dt_tm = time()+30;
					$message_resource->locked = 'N';
					$message_resource->modified = time();
					$message_resource->save();
					return true;
				} else {
					myerror_log("not waiting for a call back so send it.");
				}
			}
			
			// lastly set test addres to the 7.0 address
			$this->test_delivery_addr = $this->global_properties['test_addr_text_firm7'];
		}
		else if ($message_resource->message_type == 'A')
		{
			
			//determine if printer is currently calling in.  if it is then reset the text to 1 minute in the future.
			$gpcih_adapter = new GprsPrinterCallInHistoryAdapter($mimetypes);
			
/*			$now_sub_two = time()-90;
			$last_call = 0;
			// stupid call in history table needs 4 digit id but the demo merchants are all less than 100.  really stupid
			if ($merchant_id > 999)
				if ($gpcih_resource = Resource::find($gpcih_adapter,''.$merchant_id))
					$last_call = $gpcih_resource->last_call_in;
							
			myerror_logging(3,"the now sub 90 value is: ".$now_sub_two);
			myerror_logging(3,"the last call in value is: ".$last_call);	
				
			if ($last_call > $now_sub_two)
*/
			if ($gpcih_adapter->hasPrinterCalledInRecently($merchant_id, 90))
			{
				myerror_log("printer has called in within the last 90 seconds so reset the text to 1 minute in the future");
				// printer has called in with in the last 90 seconds. so reset the text to 1 minute
				$message_resource->next_message_dt_tm = time()+60;
				$message_resource->locked = 'N';
				$message_resource->modified = time();
				$message_resource->save();
				return true;
			}
			myerror_log("printer has not called in recently so send the text");		
		}
		return parent::sendThisMessage($message_resource);
	}
	
	function send($body)
	{
		$number = $this->deliver_to_addr;
		if ($number == NULL || trim($number) == '')
		{
			myerror_log("we have no delivery to address in text controller!");
			if ($this->message_resource->order_id < 1000)
			{
				myerror_log("this is a non order related message so just bypass");
			} else {
				throw new Exception("Error sending Text message in TextController: message has no sms number listed! ",100);
			}
			
		}
		
		$message = $body;
		
		if ($results = SmsSender2::send_sms($number, $message, $this->use_primary_sms))
		{
			return true;	
		} else
			throw new Exception("Error sending Text message in TextController: ",100);
	}

	/**
	 * 
	 * @desc will switch SMS providers.  returns a string stating what it was switched to, primary or secondary.
	 * @return String
	 */
	static function switchProviders()
	{
		$use_primary_sms = getProperty('use_primary_sms');
		myerror_log("currently the use_primary_sms is: ".$use_primary_sms);
		if ($use_primary_sms == 'true')
			$value = 'false';
		else
			$value = 'true';
		
		myerror_log("resetting use_primary_sms to ".$value);
		setProperty('use_primary_sms', $value);
		$string = "setting use_primary_sms=$value";
		return $string;
	} 
	
	function switchProvidersWithTextResendForAllOpenNonLateGPRSMessages()
	{
		$string = $this->switchProviders();

		myerror_log("WE HAVE A provider_reset,  so resend ALL texts for GPRS messages that have not been picked up and are NOT late yet but are past their send time.");
		
		$failover_time = getProperty('gprs_late_threshold_in_seconds');
		$tz = date_default_timezone_get();
		$time_zone_string = getProperty('default_server_timezone');
		date_default_timezone_set($time_zone_string);
		$failover_date_string = date('Y-m-d H:i:s',(time()-$failover_time));

		$mmha = new MerchantMessageHistoryAdapter($mimetypes);			
		$results = $mmha->resendActivationTextsToNonLateGPRSMessages($failover_date_string);
		myerror_log("Number of messages that have been resent is: ".count($results));
		logData($results, $title);

		date_default_timezone_set($tz);
		return $string;
		
	}
	
	static function heartbeatCodeProcedure()
	{
		$tz = date_default_timezone_get();
		date_default_timezone_set('America/Denver');
		$the_time_mountain = date('H:i');
		$the_hour_mountain = date('H');
		date_default_timezone_set($tz);
		if ($the_hour_mountain < 7)
			return true;
		
		$text_controller = new TextController($mt, $u, $r);
		// first check to see if there is an unreturned heartbeat out there
		if ($message_resource = $text_controller->getLastUnreturnedHeartbeat())
		{
			// ok we have an unreturned message.  check for how late it is
			if ($text_controller->shouldWeWaitABitLongerForThisHeartbeatMessageToReturn($message_resource))
				return true;
	
			// 	we have an very late TEXT! switch providers;
			$string = $text_controller->switchProvidersWithTextResendForAllOpenNonLateGPRSMessages();	
			
			MailIt::sendErrorEmailTesting("3 MINUTE LATE HEARTBEAT! SWITCHING PROVIDERS", "3 MINUTE LATE HEARTBEAT! SWITCHING PROVIDERS and all texts have been resent".$string);
			SmsSender2::sendAlertListSMS("3 MINUTE LATE HEARTBEAT! SWITCHING PROVIDERS, resending texts ".$string);
		}
		// either there is no unreturned message or we have a failed message with a provider switch, so schedule the next heart beat message
		if ($id = $text_controller->sendMonitorTextToHQPrinter())
			return true;
		else
			return false;
	}
	
	function shouldWeWaitABitLongerForThisHeartbeatMessageToReturn($message_resource)
	{
			$now = time();
			$message_time_stamp = $message_resource->message_text;
			$diff = $now-$message_time_stamp;
			if ($diff < 180)
			{
				//heartbeat isn't late enough so return true
				return true;
			}	
			// fail the message
			$message_resource->viewed = 'F';
			$message_resource->save();
			return false;		
	}

	function checkForLateHeartbeatSMS($seconds_late = 180)
	{
		if ($message_resource = TextController::getLastUnreturnedHeartbeat())
		{
			// we have an unreturned TEXT check to see if its more than 3 minutes late
			$now = time();
			$message_time_stamp = $message_resource->message_text;
			$diff = $now-$message_time_stamp;
			if ($diff < $seconds_late)
			{
				// unreturned heartbeat but not late enough yet.
				return false;
			}
			// 	we have an very late TEXT! switch providers;
			$string = TextController::switchProviders();
			
			// 
			MailIt::sendErrorEmailSupport("3 MINUTE LATE HEARTBEAT! SWITCHING PROVIDERS", "3 MINUTE LATE HEARTBEAT! SWITCHING PROVIDERS. Texts have been resent to all open messages that are not late yet".$string);
			SmsSender2::sendAlertListSMS("3 MINUTE LATE HEARTBEAT! SWITCHING PROVIDERS ".$string);
			$message_resource->viewed = 'F';
			$message_resource->save();
			return true;	
		}
		// there is no unreturned heartbeat
		return false;
	}
	
	function sendMonitorTextToHQPrinter($sent_time_stamp = 0)
	{
		if ($sent_time_stamp == 0)
			$sent_time_stamp = time();
			
		// send text for self healing of switching text providers
		myerror_log("about to send monitoring text");
		//$message_text = "SPLICKIT $time_stamp";
		$sms_no = getProperty("monitor_sms_number");
		
		SmsSender2::send_sms($sms_no,'***');		
		// now record it in teh message history table
		$message_text = ''.$sent_time_stamp;
		$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
		if ($mmh_resource = $mmh_adapter->createMessageReturnResource(0, $order_id, 'TM', $sms_no, time(), 'A', "monitoring message sent with SMSSender object.  This is just a record", $message_text,'S',date('Y-m-d H:i:s',$sent_time_stamp),1))
		{
			$mmh_resource->viewed = 'N';
			if ($mmh_resource->save()) {
				return $mmh_resource->map_id;
			}
			$error_message = "ERROR! could not set viewed to 'N' for heartbeat message.";
		} else {
			$error_message = "ERROR! could not create heartbeat message.";
		}
		$error = $mmh_adapter->getLastErrorText();
		myerror_log("$error_message  error: ".$error);
		recordError($error_message, "error: $error");
	
		return false;
	}

	static function getLastUnreturnedHeartbeat()
	{
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);			
		$mmha_options[TONIC_FIND_BY_METADATA]['locked'] = 'S';
		$mmha_options[TONIC_FIND_BY_METADATA]['message_format'] = 'TM';
		$mmha_options[TONIC_FIND_BY_METADATA]['viewed'] = 'N';
		$mmha_options[TONIC_SORT_BY_METADATA] = 'map_id ASC';
		$mmha_optinos[TONIC_FIND_TO] = 1;
		//$now_string = date('Y-m-d H:i:s');
		//$mmha_options[TONIC_FIND_BY_METADATA]['next_message_dt_tm'] = array('<'=>$four_minute_late_date_string);
		if ($tm_message_resource = Resource::find($mmha,'',$mmha_options))
			return $tm_message_resource;
		else
			return false;
	}
	
	function verifySMSSpeedNoTextBody($now)
	{
		// get oldest TM message that is out there.  should only be one
		if ($message_resource = TextController::getLastUnreturnedHeartbeat())
		{
			$message_time_stamp = $message_resource->message_text;
			$diff = $now-$message_time_stamp;
			if ($diff > 180)
			{
				myerror_log("****** heartbeat was OVER 3 MINUTES LATE SWITCHING PROVIDERS. $diff seconds *****");
				$string = $this->switchProviders();
				MailIt::sendErrorEmailTesting("$diff SECONDS LATE HEARTBEAT! SWITCHING PROVIDERS", "$diff SECONDS LATE HEARTBEAT! SWITCHING PROVIDERS ".$string);
				SmsSender2::sendAlertListSMS("$diff SECONDS LATE HEARTBEAT! SWITCHING PROVIDERS ".$string);
				//serious problem
				$result = "category4"; // for testing/debugging
			}	
			else if ($diff > 120)
			{
				myerror_log("****** heartbeat was between 2 and 3 minutes late. $diff seconds *****");
				MailIt::sendErrorEmailTesting("$diff seconds for heartbeat", "$diff seconds for heatbeat. Please be aware.");
				SmsSender2::sendAlertListSMS(" Please be aware, $diff seconds for heartbeat");
				$result = "category3"; // for testing/debugging
			}	
			else if ($diff > 60)
			{
				// minor issue
				myerror_log("****** heartbeat was between 1 and 2 minutes late. $diff seconds *****");
				$result = "category2"; // for testing/debugging
			}	
			else
			{
				myerror_log("****** heartbeat returned in $diff seconds *****");;// no problem
				$result = "category1"; // for testing/debugging
			}	
			$message_resource->tries = $diff;
			$message_resource->viewed = 'Y';
			$message_resource->modified = time();
			$message_resource->save();
		} else {
			myerror_log("ERROR!  no message for heartbeat to confirm");
		}	
		
		return $result;
	}
	
	function stageNewGPRSactivationMessageAndAlertSupport(&$gprs_message_resource)
	{
		if ($this->stageNewGPRSactiviationMessage($gprs_message_resource)) 
		{
			if (substr_count(strtolower($gprs_message_resource->info, "firmware")))
				$message = "New Firmware Error! We have a ".$gprs_message_resource->info." printer off line.  Text has been resent to: ".$gprs_message_resource->message_delivery_addr.chr(10)."order_id: ".$message_resource->order_id.chr(10);
			else
				$message = "Error! We have an old firmware printer off line.  Text has been resent to: ".$gprs_message_resource->message_delivery_addr.chr(10)."order_id: ".$message_resource->order_id.chr(10);
			SmsSender2::sendAlertListSMS($message);
		} 
	}

	/**
	 * @deprecated
	 * @desc will restage a text message and alert support.  do not use any more.
	 * @param $text_message_resource
	 */
	function restageGPRSactiviationAndAlertSupport($text_message_resource)
	{
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		if ($mmha->resendMessageResource($text_message_resource))
		{
			$message = "FIRMWARE 7.0 ERROR! We have a firmware 7.0 printer off line.  Text has been resent to: $sms_no".chr(10)."order_id: ".$text_message_resource->order_id.chr(10);
			SmsSender2::sendAlertListSMS($message);
		} else {
			$message = "RESTAGING ERROR! WE were unable to restage an activation text for a late GPRS message".chr(10)."sms: $sms_no".chr(10)."order_id: ".$text_message_resource->order_id.chr(10);
			SmsSender2::sendAlertListSMS($message);
		}		
	}
	
	/**
	 * @desc will stage a new activation message for a passed in GPRS message resource.  Phone number must be in teh addr field.  will bump up the tries to1 for the submitted message
	 * @param $gprs_message_resource
	 */
	
	static function stageNewGPRSactiviationMessage(&$gprs_message_resource)
	{
		myerror_logging(3,"about to stage a new activation text");
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		// verify we have a phone number
		$text_activation_number = $gprs_message_resource->message_delivery_addr;
		// validate number with regex
		$key_values = '([0-9]{10})';
		
		if (strlen($text_activation_number) == 10 && preg_match($key_values, $text_activation_number, $matches))
		{
			if ($text_map_id = $mmha->createMessage($gprs_message_resource->merchant_id, $gprs_message_resource->order_id, 'T', $gprs_message_resource->message_delivery_addr, time(), 'A', $gprs_message_resource->info, '***'))
			{
				// now set the tries to 1 of the GPRS message so it knows that a text has been resent.
				myerror_logging(3, "about to set the tries of the GPRS message to +1");
				$gprs_message_resource->tries = $gprs_message_resource->tries + 1;
				$gprs_message_resource->save();
				return $text_map_id;
			} else {
				myerror_log("ERROR!  unable to save new GPRS activation text for message map_id: ".$gprs_message_resource->map_id."   error: ".$mmha->getLastErrorText());
				recordError("ERROR!  unable to save new GPRS activation text for message map_id: ".$gprs_message_resource->map_id,"error: ".$mmha->getLastErrorText());
			}
		} else {
			myerror_log("ERROR!  unable to stage new GPRS activation text. phone number is NOT VALID: ".$text_activation_number);
			MailIt::sendErrorEmailSupport("ERROR!  unable to stage new GPRS activation text. phone number is NOT VALID", "phone number in address field of GPRS message is not valid ($text_activation_number). Please check Merchant_Message_Map entry.   order_id: ".$gprs_message_resource->order_id);
		}	
		return false;
	}
	
	static function checkRecentProviderResetWithinThisManySeconds($seconds)
    {
    	$property_adapter = new PropertyAdapter($mimetypes);
    	$resource = $property_adapter->getExactResourceFromData(array("name"=>"use_primary_sms"));
		if ($resource->modified < time()-$seconds)
			return false;
		else
			return true;			    	
    }

}