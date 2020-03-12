<?php

class MerchantMessageHistoryAdapter extends MySQLAdapter
{
    var $created_message_resource;

	function MerchantMessageHistoryAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_Message_History',
			'%([0-9]{1,8})%',
			'%d',
			array('map_id'),
			array('map_id','merchant_id','order_id','message_format','message_delivery_addr','next_message_dt_tm','pickup_timestamp','from_email','sent_dt_tm','stamp','locked','viewed','message_type','tries','info','message_text','response','portal_order_json','created','modified','logical_delete'),
			array('next_message_dt_tm','created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
    
    function loadMessageInfo(&$message_resource)
    {
    	$data = $this->getMesageInfoData($message_resource);
    	$message_resource->set("info_data",$data);  
    }
    
	/**
	 * 
	 * @desc creates a record in the Merchant Message History Table.  can be used to schedule a message or to record the sending of one.
	 * @return returns the map id of the newly created row on success and a boolean false on failure
	 * 
	 * 
	 * @param int $merchant_id
	 * @param int $order_id
	 * @param varchar $message_format
	 * @param varchar $message_delivery_addr
	 * @param int $next_message_dt_tm
	 * @param char $message_type
	 * @param varchar $info
	 * @param varchar $message_text
	 * @param char $locked
	 * @param int $sent_dt_tm
	 */
    
    function createMessage($merchant_id,$order_id,$message_format,$message_delivery_addr,$next_message_dt_tm,$message_type,$info,$message_text,$locked = 'N',$sent_dt_tm = 0,$tries = 0,$portal_order_json = null)
    {
        $creation_attempts = 1;
        do {
            if ($message_resource = $this->createMessageReturnResource($merchant_id, $order_id, $message_format, $message_delivery_addr, $next_message_dt_tm, $message_type, $info, $message_text,$locked,$sent_dt_tm,$tries,$portal_order_json)) {
                $this->created_message_resource = $message_resource;
                return $message_resource->map_id;
            }
            // probable deadlock problem
            $error = $this->getLastErrorText();
            myerror_log("We had an error creating the message. the error is: ".$error);
            myerror_log("PAUSE AND Retry after probabal deadlock");
            usleep(50000);
        } while ($message_resource == false && $creation_attempts++ < 3);
        return false;
    }

    /**
     * 
     * @desc creates a message and returns that message as a resource
     * @param $merchant_id
     * @param $order_id
     * @param $message_format
     * @param $message_delivery_addr
     * @param $next_message_dt_tm
     * @param $message_type
     * @param $info
     * @param $message_text
     * @param $locked
     * @param $sent_dt_tm
     * @param $tries
     * 
     * @return Resource
     */
    function createMessageReturnResource($merchant_id,$order_id,$message_format,$message_delivery_addr,$next_message_dt_tm,$message_type,$info,$message_text,$locked = 'N',$sent_dt_tm = 0,$tries = 0,$portal_order_json = null)
    {
    	$mmh_data['merchant_id'] = $merchant_id;
    	$mmh_data['order_id'] = $order_id;
    	$mmh_data['message_format'] = $message_format; 
    	$mmh_data['message_delivery_addr'] = $message_delivery_addr;
    	$mmh_data['next_message_dt_tm'] = $next_message_dt_tm;
    	$mmh_data['portal_order_json'] = $portal_order_json;
    	if ($this->pickup_timestamp) {
            $mmh_data['pickup_timestamp'] = $this->pickup_timestamp;
        }
    	$mmh_data['message_type'] = $message_type;
    	$mmh_data['info'] = $info;
    	if ($locked != 'N') {
            $mmh_data['locked'] = $locked;
        } else {
			$base_format = substr($message_format, 0,1);
			if ($base_format == 'G' || $base_format == 'W' || $base_format == 'O' || $base_format == 'P') {
                $mmh_data['locked'] = 'P';
            } else {
                $mmh_data['locked'] = $locked;
			}
    	}
    	$mmh_data['tries'] = $tries;
    	if ($sent_dt_tm != 0) {
            $mmh_data['sent_dt_tm'] = date('Y-m-d H:i:s',$sent_dt_tm);
        }
    	if (isset($message_text) && trim($message_text != '')) {
            $mmh_data['message_text'] = $message_text;
        }
    	$message_resource = Resource::factory($this,$mmh_data);
        $tz = date_default_timezone_get();
        setDefaultTimeZoneFromString(getProperty('default_server_timezone'));
    	if ($message_resource->save()) {
    		myerror_log("MerchantMessageHistoryAdapter: newly created message id: ".$message_resource->map_id);
            setDefaultTimeZoneFromString($tz);
    		return $message_resource;
    	} else {
            setDefaultTimeZoneFromString($tz);
            myerror_log("MerchantMessageHistoryAdapter: ERROR! trying to save new message in MerchantMessageHistoryAdapter: ".$this->getLastErrorText());
        }
    	return false;
    }

    /**
     * 
     * @desc takes a merchant message history resource and returns an array of the info data
     * 
     * @param Merchant_Message_History_Resource $message_resource
     */
    function getMesageInfoData($message_resource)
    {
    	$additional_data = array();
    	if ($info_string = $message_resource->info)
		{
			$fielddata = explode(';', $info_string);
			foreach ($fielddata as $datarow)
			{
				$s = explode('=', $datarow);
				$additional_data[$s[0]] = $s[1];
			}
		}
		return $additional_data;
    }
    
    /**
     * @desc marks a message as viewed. throws an error if we cannot mark the message as viewed
     * 
     * @param int $message_id
     */
    
	function markMessageAsViewedById($message_id)
	{
		$message_resource = Resource::find($this,''.$message_id);
		return $this->markMessageResourceAsViewed($message_resource);
	}    
	
    /**
     * @desc marks a message as viewed. throws an error if we cannot mark the message as viewed
     * 
     * @param Resource $message_resource
     */
	
	static function markMessageResourceAsViewed($message_resource)
	{
		$message_resource->viewed = 'V';
		$message_resource->stamp = getRawStamp().'-callback;'.$message_resource->stamp;
		if ($message_resource->locked == 'P') {
			$message_resource->locked = 'S';
		}
		$message_resource->modified = time();
		if ($message_resource->save()) {
			return true;
		} else {
			myerror_log("MerchantMessageHistoryAdapter: ERROR TRYING TO MARK MESSAGE AS VIEWED in messagecontroller: ".$message_resource->getAdapterError());
			myerror_log("MerchantMessageHistoryAdapter: error code is: ".mysqli_errno($message_resource->_adapter->_handle));
			throw new Exception("ERROR!  message could not be marked as VIEWED: ".$message_resource->getAdapterError(),20);
		}
	}
	
	public function resendMessage($message_id)
	{
		$message_resource = Resource::find($this,''.$message_id);
		return $this->resendMessageResource($message_resource);
	}
	
	public function resendMessageResource($message_resource)
	{
		$message_resource->next_message_dt_tm = time();
		$message_resource->sent_dt_tm = '0000-00-00';
		$format = substr($message_resource->message_format, 0,1);
		if ($format == 'W' || $format == 'G' || $format == 'O' )
			$message_resource->locked = 'P';
		else 
			$message_resource->locked = 'N';
		if ($message_resource->save())
			return true;
		else
			return false;
	}
	
	public function getDailyReportListForMerchantId($merchant_id, $days_back = 30)
	{
		$mmh_data['merchant_id'] = $merchant_id;
		$mmh_data['message_format'] = 'ED';
		return $this->getReportListForMerchantId($mmh_data, $days_back);
	}
	
	public function getCOBListForMerchantId($merchant_id, $days_back = 30)
	{
		$mmh_data['merchant_id'] = $merchant_id;
		$mmh_data['message_format'] = array("LIKE"=>'%Cob');
		return $this->getReportListForMerchantId($mmh_data, $days_back);
	}
	
	private function getReportListForMerchantId($mmh_data, $days_back)
	{
		$newdate = strtotime ( '-'.$days_back.' day' , time() ) ;
		$newdate = date ( 'Y-m-d H:i:s' , $newdate );
		myerror_log("the formatted date is: ".$newdate);
		
		$mmh_data['created'] = array('>'=>$newdate);
		$mmh_options[TONIC_FIND_BY_METADATA] = $mmh_data;
		$mmh_options[TONIC_SORT_BY_METADATA] = " map_id DESC ";
		$report_resources = Resource::findAll($this,null,$mmh_options);
		
		// get merchant timezone so sent date times make sense
		$tz = date_default_timezone_get();
		$merchant_adapter = new MerchantAdapter();
		$merchant_resource = Resource::find($merchant_adapter,''.$mmh_data['merchant_id']);
		$merchant_local_time_zone_string = getTheTimeZoneStringFromOffset($merchant_resource->time_zone);

		date_default_timezone_set($merchant_local_time_zone_string);
		foreach ($report_resources as &$report_resource)
		{
			if ($info = $report_resource->info)
			{
				$data = array();
				$s = explode(';', $info);
				foreach ($s as $datapair)
				{
					$dp = explode('=', $datapair);
					$data[$dp[0]] = $dp[1];
				}
				$report_resource->set('data',$data);
			}
			$sent_string = date('l M j, Y  --  g:i A  ',$report_resource->next_message_dt_tm);
			$report_resource->set('sent_string',$sent_string);
			
		}
		date_default_timezone_set($tz);
		$merchant_resource->set("reports",$report_resources);
		$merchant_resource->set("merchant_id",$mmh_data['merchant_id']);
		return $merchant_resource;
		
	}
	
	/************  resend text to gprs messages ***************/
	
	function resend($mmha_options,$use_message_media = false)
	{
		$return_data = array();
		if ($messages = Resource::findAll($this,'',$mmha_options))
		{
			foreach ($messages as $message)
			{
				$sms_no = $message->message_delivery_addr;
				myerror_log("MerchantMessageHistoryAdapter: we have aquired the sms number of gprs message: '".$sms_no."'   -   so send a text");
				$info_data = array();
				$info_data = $this->getMesageInfoData($message);
				if ($message->message_type != 'X2')
				{
					if ($info_data['firmware'])
						$text_message = "***";
					else
						$text_message = "***5";
					myerror_log("MerchantMessageHistoryAdapter: about to send ".$text_message." to the printer");
					$results = SmsSender2::sendSmsAlert(array($sms_no), $text_message,true);
					$return_data[$message->map_id] = $text_message.'   '.$results['response_message'];			
				}
				else
					myerror_log("MerchantMessageHistoryAdapter: message is of type X2 so skip the text");
			}
		}
		return $return_data;
	}

	/**
	 * 
	 * @desc will send all non picked up GPRS messages a text.  the passed in parameter states what is considered late.
	 * @desc  format of paramter is:   'Y-m-d H:i:s'
	 *  
	 * @param $due_date_time_older_than_string
	 */
	function resendEmergencyActivationTextsToAllOpenGPRSMessages($due_date_time_older_than_string)
	{
		$mmha_options[TONIC_FIND_BY_METADATA]['next_message_dt_tm'] = array('<'=>$due_date_time_older_than_string);		
		$return_data = $this->resendGPRS($mmha_options,true);
		return $return_data;		
	}

	/**
	 * 
	 * @desc will send all non picked up GPRS messages a text.  the passed in parameter states what is considered late.
	 * @desc  format of paramter is:   'Y-m-d H:i:s'
	 *  
	 * @param $failover_date_string
	 */
	function resendActivationTextsToNonLateGPRSMessages($failover_date_string)
	{
		$now_string = date('Y-m-d H:i:s');
		$mmha_options[TONIC_FIND_BY_METADATA]['next_message_dt_tm'] = array('>'=>$failover_date_string);
		$mmha_options[TONIC_FIND_BY_STATIC_METADATA] = ' next_message_dt_tm < NOW() ';
		$return_data = $this->resendGPRS($mmha_options,true);
		//$return_data is mostly just for testing purposes
		return $return_data;
	}

	private function resendGPRS($mmha_options,$use_message_media)
	{
		$mmha_options[TONIC_FIND_BY_METADATA]['locked'] = 'P';
		$mmha_options[TONIC_FIND_BY_METADATA]['message_format'] = array('LIKE'=>'G%');
		$mmha_options[TONIC_FIND_BY_METADATA]['order_id'] = array('>'=>1000);
		$mmha_options[TONIC_FIND_BY_METADATA]['sent_dt_tm'] = '0000-00-00 00:00:00';
		$mmha_options[TONIC_SORT_BY_METADATA] = 'next_message_dt_tm';
		return $this->resend($mmha_options,$use_message_media);
	}

    /*************   message retrieval functions  ***************/

	function getLatePulledMessagesFromTimeStamp($ts = 0,$with_alert = false)
	{
		if ($ts == 0)
			$ts = time();
		$date_string = date('Y-m-d H:i:s',$ts);
		return $this->getLatePulledMessages($date_string,$with_alert);	
	}

	function getLatePulledMessagesFromTimeStampWithAlert($ts = 0)
	{
		return $this->getLatePulledMessagesFromTimeStamp($ts,true);
	}
	
	/**
	 * 
	 * @desc will get all the late 'X' pulled message.  'X' is the critical message from the order
	 * 
	 * @param String $time_that_is_late_string
	 */
	function getLatePulledMessages($time_that_is_late_string,$with_alert = false)
	{
		$mmha_options[TONIC_FIND_BY_METADATA]['locked'] = 'P';
		$mmha_options[TONIC_FIND_BY_METADATA]['merchant_id'] = array('>'=>1000);
		$mmha_options[TONIC_FIND_BY_METADATA]['order_id'] = array('>'=>1000);
		$mmha_options[TONIC_FIND_BY_METADATA]['sent_dt_tm'] = '0000-00-00 00:00:00';
		//$mmha_options[TONIC_FIND_BY_METADATA]['message_type'] = 'X';
		$mmha_options[TONIC_FIND_BY_METADATA]['message_type'] = array('LIKE'=>'X%');
		$mmha_options[TONIC_FIND_BY_METADATA]['next_message_dt_tm'] = array('<'=>$time_that_is_late_string);
		$mmha_options[TONIC_SORT_BY_METADATA] = 'next_message_dt_tm';
		//$texted_merchants = array();
		$resources = Resource::findAll($this,'',$mmha_options);
		if (count($resources) < 1)
			return false;
		if ($with_alert)
		{
            $num_gprs = 0;
			$number_of_late_messages = sizeof($resources);
			myerror_log("number of undelivered messages is: ".$number_of_late_messages);
			if ($number_of_late_messages > 10)
			{
				foreach ($resources as $message_resource) {
					if (substr($message_resource->message_format,0,1) == 'G') {
						$num_gprs++;
					}
				}
				if ($num_gprs > 10) {
					$gprs_string = 'GPRS';
				}
				SmsSender2::sendAlertListSMS("POSSIBLE $gprs_string BACK UP STARTING. We have $number_of_late_messages pulled type messages over 4 minutes late");
				SmsSender2::sendEngineeringAlert("POSSIBLE $gprs_string BACK UP STARTING. We have $number_of_late_messages pulled type messages over 4 minutes late");
				MailIt::sendErrorEmailSupport("POSSIBLE $gprs_string BACK UP STARTING", "we have $number_of_late_messages pulled type messages over 4 minutes late");
				MailIt::sendErrorEmail("POSSIBLE $gprs_string BACK UP STARTING", "we have $number_of_late_messages pulled type messages over 4 minutes late");
				
			}
		}	
		return $resources;
	}
	
	public function getAvailableMessageResourcesArrayUsingDBPrioritySetting($mmha_options = array())
	{
		if ($priority = getProperty('message_priority'))
		{
			// ok this is just for testing.  in teh test properties file there will be this property that will allow me to test the priority functionality of the messaging.
			if ($priority == '1')
				$mmha_options[TONIC_FIND_BY_METADATA]['order_id'] = array(">"=>1000);
			else if ($priority == '2')
				$mmha_options[TONIC_FIND_BY_METADATA]['order_id'] = array("IS"=>"NULL");
		}

		return $this->getAvailableMessageResourcesArray($mmha_options);
	}

	/**
	 * 
	 * @desc retrieves the available messages as an array of message resources.  Workers can then act on this array.  messages attached to order_id's appear first in the arry followed by non order_id messages
	 * 
	 * @param $mmha_options
	 * 
	 * @return array of message history resources, false if there are no available messages
	 */
	
	public function getAvailableMessageResourcesArray($mmha_options = array())
	{			
		if (ifLogLevelIsGreaterThan(4))
		{
			$sql = "SELECT NOW() as thetime";
			$o[TONIC_FIND_BY_SQL] = $sql;
			$resource = Resource::find($this,'',$o);
			$time = $resource->thetime;
			myerror_log("DB TIME IS: ".$time);
		}
		
		if (!$mmha_options[TONIC_FIND_BY_METADATA]['locked'])
			$mmha_options[TONIC_FIND_BY_METADATA]['locked'] = 'N';
		//$mmha_options[TONIC_FIND_BY_METADATA]['sent_dt_tm'] = '0000-00-00 00:00:00';
		$now_string = date('Y-m-d H:i:s');

		$mmha_options[TONIC_FIND_BY_METADATA]['next_message_dt_tm'] = array('<='=>$now_string);
		$mmha_options[TONIC_SORT_BY_METADATA] = 'if(order_id is null,1,0) ,next_message_dt_tm';
		$message_load = getProperty("worker_message_load");
		$mmha_options[TONIC_FIND_TO] = $message_load;
		if ($message_resources = Resource::findAll($this,'',$mmha_options))
			return $message_resources;
		return false;
		
	}

	/**
	 * @desc will lock a message resource for sending by trying to update it.  returns the message resource in a locked state or false if the mesage resrouce is already locked by another worker.
	 * 
	 * @param resource $unlocked_message_resource
	 * @param boolean $bypass_update
	 * 
	 * @return resource
	 */
	public function getLockedMessageResourceForSending($message_resource,$bypass_update = false)
	{
		return LockedMessageRetriever::getLockedMessageResourceForSending($message_resource,$bypass_update);	
	}
	
	function getNextMessageResourceForSend($mmha_options)
	{
		$resources = $this->getAvailableMessageResourcesArray($mmha_options);
		foreach ($resources as $unlocked_message_resource)
		{
			if ($locked_message_resource = $this->getLockedMessageResourceForSending($unlocked_message_resource))
				return $locked_message_resource;
		}
		return false;
			
	}

	function checkIfMessageIsLateByThisManyMinutesAndFailIfSo($minutes,&$message_resource)
	{
		$seconds = $minutes * 60;
		$fail_time = time() - $seconds;
		$diff = $fail_time - $message_resource->next_message_dt_tm;
		myerror_log("MerchantMessageHistoryAdapter: message is late by $diff seconds.  check if this is more than $seconds seconds");
		myerror_log("MerchantMessageHistoryAdapter: fail time: ".date('Y-m-d H::i:s',$fail_time));
		myerror_log("MerchantMessageHistoryAdapter: message time: ".date('Y-m-d H::i:s',$message_resource->next_message_dt_tm));
		if ($message_resource->next_message_dt_tm < $fail_time)
		{
			myerror_log("MerchantMessageHistoryAdapter: Message is more than $minutes late so we are failing the message");
			return $this->failThisMessage($message_resource);
		}
		return false;		
	}

	function failThisMessage(&$message_resource)
	{
		myerror_log("MerchantMessageHistoryAdapter: we are setting this message to Failed");
		$message_resource->locked = 'F';
		$message_resource->modified = time();
		if ($message_resource->save())
			return true;
		return false;
	}

	function getCreatedMessageResource()
    {
        return $this->created_message_resource;
    }

	static function getAllOrderMessages($order_id,$options_data = array())
	{
		if (count($options_data) > 0) {
			$option[TONIC_FIND_BY_META_DATA] = $options_data;
		}
		$data['order_id'] = $order_id;
		$options[TONIC_FIND_BY_METADATA] = $data;
		if ($message_resources = Resource::findAll(new MerchantMessageHistoryAdapter(),'',$options))
			return $message_resources;
		return false;
	}

    /**
     * @param $order_id
     * @param $message_format
     * @return Resource
     */
	static function getMessageByOrderIdAndFormat($order_id,$message_format)
	{
		$data['order_id'] = $order_id;
		$data['message_format'] = $message_format;
		$options[TONIC_FIND_BY_METADATA] = $data;
		if ($message_resource = Resource::find(new MerchantMessageHistoryAdapter(),'',$options)) {
            return $message_resource;
        }
		return false;
	}

	/**
	 * 
	 * @desc will cancel any messages attached to the order id that are still in a locked state of 'N' or 'P'
	 * 
	 * @param int $order_id
	 */
	function cancelOrderMessages($order_id)
	{
		$data['order_id'] = $order_id;
		$options[TONIC_FIND_BY_METADATA] = $data;
		if ($message_resources = Resource::findAll($this,'',$options))
		{
			foreach ($message_resources as $message_resource)
			{
				if ($message_resource->locked == 'P' || $message_resource->locked == 'N')
				{
					$message_resource->locked = 'C';
					$message_resource->modified = time();
					$message_resource->save();	
				}
			}
		}
	}
	
	/**
 	 * @codeCoverageIgnore
 	 */
	static function createMandrillCheckMessage()
	{
		$mmha = new MerchantMessageHistoryAdapter();
		if ($mmha->createMessage(null, null, 'E', 'dummy@dummy.com', time(), 'Z', "subject=mandril monitor", "dummy send"))
			return true;
		else
			return false;
	}
    
	static function createMorningSobMessages($merchant_id = 0)
	{
		$mmha = new MerchantMessageHistoryAdapter();
		$dlst_int = date('I');
		//$sql = "CALL SMAWSP_CREATE_MORNING_MESSAGES($dlst_int)";
		$sql = "INSERT INTO Merchant_Message_History (merchant_id,order_id,message_format,message_delivery_addr,next_message_dt_tm,message_type,created,modified )    SELECT a.merchant_id,NULL,'P',b.delivery_addr,DATE_SUB(DATE_SUB(DATE_ADD(DATE(NOW()), INTERVAL c.open HOUR_SECOND), INTERVAL ($dlst_int+a.time_zone) HOUR), INTERVAL 10 MINUTE),'A', NOW(),'0000-00-00 00:00:00' FROM Merchant a, Merchant_Message_Map b, `Hour` c WHERE a.active = 'Y' AND a.ordering_on = 'Y' AND a.merchant_id = b.merchant_id AND a.merchant_id = c.merchant_id AND b.message_format = 'P' AND c.day_of_week = DAYOFWEEK(NOW()) AND c.day_open = 'Y' AND c.merchant_id > 1000 AND b.logical_delete = 'N' AND c.hour_type = 'R'";
		if ($merchant_id > 0)
			$sql = $sql.' AND a.merchant_id = '.$merchant_id;
		if ($mmha->_query($sql))
		{
			myerror_log("MerchantMessageHistoryAdapter: the morning old firmware messages have been created");
			$success_with_old_firmware = true;
		}
		else
		{
			myerror_log("MerchantMessageHistoryAdapter: ERROR! there was an error and the old firmware Sob messages were not created: ".$mmha->getLastErrorText());
			MailIt::sendErrorEmail("Old Firmware Sob messages were not created", "error: ".$mmha->getLastErrorText());
		}
		$sql = "INSERT INTO Merchant_Message_History (merchant_id,order_id,message_format,message_delivery_addr,next_message_dt_tm,message_type,message_text,created,modified ) SELECT a.merchant_id,NULL,'Tsob',d.delivery_addr,DATE_SUB(DATE_SUB(DATE_ADD(DATE(NOW()), INTERVAL c.open HOUR_SECOND), INTERVAL ($dlst_int+a.time_zone) HOUR), INTERVAL 10 MINUTE),'A','***' AS message_text, NOW(),'0000-00-00 00:00:00'FROM Merchant a, Merchant_Message_Map b, `Hour` c, Merchant_Message_Map d WHERE a.active = 'Y' AND a.ordering_on = 'Y' AND a.merchant_id = b.merchant_id AND a.merchant_id = c.merchant_id AND b.message_format LIKE 'GU%' AND LOWER(b.info) LIKE 'firmware%' AND c.day_of_week = DAYOFWEEK(NOW()) AND c.day_open = 'Y' AND c.merchant_id > 1000 AND b.logical_delete = 'N' AND c.hour_type = 'R' and b.merchant_id = d.merchant_id and d.message_format = 'T'";
		if (isLaptop())
			$sql = $sql.' AND a.merchant_id = 1054';
		if ($mmha->_query($sql))
		{
			$success_with_new_firmware = true;
		}	else {
			myerror_log("MerchantMessageHistoryAdapter: ERROR! we did NOT create the MORNING messages for version 7.0 printers: ".$mmha->getLastErrorText());
			MailIt::sendErrorEmail("ERROR! new firmware Sob messages were not created" , "error: ".$mmha->getLastErrorText());
		}
		return ($success_with_new_firmware && $success_with_old_firmware);
		
	}
	
	static function failOldCobMessages()
	{
		// fail un-picked up COB message for GPRS
		$mmha = new MerchantMessageHistoryAdapter();
		$sql_cancel_cob = "UPDATE Merchant_Message_History SET locked = 'F' WHERE locked = 'P' and message_format = 'GCob'";
		if ($mmha->_query($sql_cancel_cob))
			myerror_log("MerchantMessageHistoryAdapter: COBs have been cancelled");
		else
		{
			if (mysqli_errno($mmha->_handle) == 0)
				myerror_log("MerchantMessageHistoryAdapter: there were no COB's that needed to be cancelled");
			else
			{
				myerror_log("ERROR!  there was an error cancelling unpicked up COB messages.  error: ".$mmha->getLastErrorText());
				MailIt::sendErrorEmail("There was an error canceling unpicked up COB messages from last night", "error: ".$mmha->getLastErrorText());
				return false;	
			}
		}
		return true;
		
	}
	
	static function failOldMessages()
	{
		$mmha = new MerchantMessageHistoryAdapter();
		$one_hour_ago = mktime(date("H")-1, date("i"), date("s"), date("m")  , date("d"), date("Y"));
		$one_hour_ago_string =date('Y-m-d H:i:s',$one_hour_ago);
		$sql = "UPDATE Merchant_Message_History SET locked = 'F' WHERE sent_dt_tm = '0000-00-00 00:00:00' AND next_message_dt_tm < '".$one_hour_ago_string."' AND (locked = 'N' OR locked = 'P') AND message_format != 'P' AND logical_delete = 'N' AND merchant_id != 50";
		myerror_log("MerchantMessageHistoryAdapter: the update messages to F cron sql: ".$sql);
		if ($mmha->_query($sql))
		{
			$num_affected_rows = $mmha->_affectedRows();
			if ($num_affected_rows > 0)
				myerror_log("MerchantMessageHistoryAdapter: there were $num_affected_rows messages that were set to failed");	
		}
		
		//return true since this is an activity 
		return true;		
	}
    
	/**
 	 * @codeCoverageIgnore
 	 */
	static function generateMessageFailureRatesForToday()
	{
	    return true;
//		$mmha = new MerchantMessageHistoryAdapter();
//		$dates['yesterday'] = date('Y-m-d',mktime(0, 0, 0, date("m")  , date("d")-2, date("Y")));
//		$dates['five_days_ago'] = date('Y-m-d',mktime(0, 0, 0, date("m")  , date("d")-6, date("Y")));
//		$dates['ten_days_ago'] = date('Y-m-d',mktime(0, 0, 0, date("m")  , date("d")-11, date("Y")));
//		//$dates['start_of_robo'] = '2012-01-14';
//
//		$body = "<html><body>";
//
//		$body .= "<p><p>Total Numbers ALL DELIVERY TYPES<p>";
//		foreach ($dates as $name=>$date)
//		{
//			$sql =  "SELECT count(*) as number FROM Merchant_Message_History a JOIN Orders b ON a.order_id = b.order_id WHERE a.message_format = 'CC' AND DATE(b.order_dt_tm) = '$date' AND b.status = 'E' AND b.merchant_id > 1000 AND LOWER(a.message_delivery_addr) = 'script1' ".
//					" UNION " .
//					" SELECT count(*) as number FROM Orders b WHERE DATE(b.order_dt_tm) = '$date' AND b.status = 'E' AND b.merchant_id > 1000";
//			myerror_log("MerchantMessageHistoryAdapter: failed total MESSAGE calculations sql: ".$sql);
//			$failed_messages_options[TONIC_FIND_BY_SQL] = $sql;
//			if ($results = $mmha->select('',$failed_messages_options))
//			{
//				$failed = $results[0]['number'];
//				$total = $results[1]['number'];
//				$rate = 100 * $failed/$total ;
//			}
//			$body .= "Failure rate since $name = $rate<p>";
//		}
//		$body .= "<p><p>GPRS Numbers<p>";
//		foreach ($dates as $name=>$date)
//		{
//			$sql =  "SELECT count(*) as number  FROM `Merchant_Message_History` WHERE `message_format` LIKE 'G%' AND DATE_FORMAT(created , '%Y-%m-%d' ) > '$date' and (TIMEDIFF( `sent_dt_tm`,`next_message_dt_tm`) > '00:07:00' || locked = 'F')  AND order_id IS NOT NULL and merchant_id > 1000 ".
//					" UNION " .
//					" SELECT count(*) as number FROM Merchant_Message_History  WHERE `message_format` LIKE 'G%' AND DATE_FORMAT(created , '%Y-%m-%d' ) > '$date' AND order_id IS NOT NULL and merchant_id > 1000";
//			myerror_log("failed gprs calculations sql: ".$sql);
//			$failed_messages_options[TONIC_FIND_BY_SQL] = $sql;
//			if ($results = $mmha->select('',$failed_messages_options))
//			{
//				$failed = $results[0]['number'];
//				$total = $results[1]['number'];
//				$rate = 100 * $failed/$total ;
//			}
//			$body .= "Failure rate since $name = $rate<p>";
//		}
//
//		$body .= "</body></html>";
//		myerror_log("$body");
//		MailIt::sendErrorEmail("Message Delivery Failure Rates", $body);
		
//		return true;
		
	}
	
	/**
 	 * @codeCoverageIgnore
 	 */
	static function checkCurrentPingFailures()
	{
		$mmha = new MerchantMessageHistoryAdapter();
		$sql = "SELECT count(merchant_id) as cnt FROM gprs_printer_fails WHERE active = 'Y'";
		$the_fail_options[TONIC_FIND_BY_SQL] = $sql;
		if ($results = $mmha->select('',$the_fail_options))
		{
			$result = array_pop($results);
			$number_of_fails = $result['cnt'];
			if ($number_of_fails > 5)
			{
				$alert_list_sms = getProperty('alert_list_sms');
				$alert_list_sms_array = explode(',', $alert_list_sms);
				//$alert_list_sms_array[] = '3038844083';
				
				if ($number_of_fails > 8)
				{
					SmsSender2::sendAlertListSMS("SHUTTING DOWN gprs ORDERING for tunnel! TMOBILE OUTAGE!");
					setProperty("gprs_tunnel_merchant_shutdown", "true");
				} else {
					SmsSender2::sendAlertListSMS("POSSIBLE TMOBILE OUTAGE!");
				}
			}
		}
		return true;
	}
}
?>