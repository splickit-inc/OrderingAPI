<?php

class GprsPrinterCallInHistoryAdapter extends MySQLAdapter
{
	function GprsPrinterCallInHistoryAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'gprs_printer_call_in_history',
			'%([0-9]{4,10})%',
			'%d',
			array('merchant_id'),
			NULL,
			array('last_call_in')
			);
        $this->log_level = 0;
	}

	/**
	 * 
	 * @desc used for the morning text to version 7 printers.  will check to see if the printer has called in within the last 5 minutes, if not alert support 
	 * @param int $merchant_id
	 */
	function checkForRecentCallInFromSobTextAndAlertSupportIfNeeded($merchant_id)
	{
		if ($this->hasPrinterCalledInRecently($merchant_id,300))
			return true;
		// if we get here we need to send robo call 
		myerror_log("ERROR! we have a version GPRS device not responding to morning Tsob and then Robo Call.  ALERT SUPPORT!");
		$merchant_resource = Resource::find(new MerchantAdapter($mimetypes),''.$merchant_id);	
		// alert support
		$body = "We have a new version printer that has not returned a morning call in request after Text AND Robo Call.".chr(10)."merchant_id: ".$merchant_resource->merchant_id.chr(10).$merchant_resource->name.chr(10)."phone: ".$merchant_resource->phone_no;				
		//MailIt::sendErrorEmailSupport("Version 7 or greater printer has NOT returned morning call in request after RoboCall", $body);
		if (isLaptop())
			MailIt::sendErrorEmailSupport("Version 7 or greater printer has NOT returned morning call in request after RoboCall", $body);
		return true;
	}

	/**
	 * 
	 * @desc used for the morning text to version 7 printers.  will check to see if the printer has called in within the last 5 minutes, if not schedule a robo call 
	 * @param int $merchant_id
	 * @param unused $tsob_message_id
	 */
	function checkForRecentCallInFromSobTextAndSendRoboCallIfNeeded($merchant_id)
	{
		if ($this->hasPrinterCalledInRecently($merchant_id,300))
			return true;
		// if we get here we need to send robo call 
		myerror_log("ERROR! we have a version GPRS device not responding to morning Tsob.  SEND A ROBO CALL!");
		$merchant_resource = Resource::find(new MerchantAdapter($mimetypes),''.$merchant_id);	
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		// first clean the phone number
		$phone_number = cleanString(array('-','(',')',' '), $merchant_resource->phone_no);
		$message_delivery_addr = $phone_number;
		if (isTest() || isLaptop())
			$message_delivery_addr = getProperty('test_addr_ivr');
		
		$mmha->createMessage($merchant_id, null, 'IA', $message_delivery_addr, time(), 'A', $info, $message_text,'N');			

		// now create the new activity to check the status again but alert support if there is a failure
		$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
		$doit_ts = time()+240;
		$info = 'object=GprsPrinterCallInHistoryAdapter;method=checkForRecentCallInFromSobTextAndAlertSupportIfNeeded;thefunctiondatastring='.$merchant_id.'';
		$id = $activity_history_adapter->createActivity('ExecuteObjectFunction', $doit_ts, $info, $activity_text);
		return true;		
	}
	
	static function staticHasPrinterCalledInRecently($merchant_id,$time_in_seconds_back)
	{
		$gpciha = new GprsPrinterCallInHistoryAdapter($mimetypes);
		$gpciha->hasPrinterCalledInRecently($merchant_id, $time_in_seconds_back);
	}
	
	function hasPrinterCalledInRecently($merchant_id,$time_in_seconds_back)
	{
		$last_call_in = $this->getLastCallInByMerchantId($merchant_id);
		$recently_threshold = time()-$time_in_seconds_back;
		
		if ($last_call_in > $recently_threshold) {
			myerror_log("printer has called in less than $time_in_seconds_back seconds ago");
			return true;
		} 
		myerror_log("printer has NOT NOT NOT called in in the last $time_in_seconds_back seconds");
		return false;
	}
	
	function getLastCallInByMerchantId($merchant_id)
	{
		$record = $this->getRecord(array('merchant_id'=>$merchant_id));
		$last_call_in = $record['last_call_in'];
		return $last_call_in;
	}
	
	function setAllRecordsInnactive()
	{
		$sql = "UPDATE gprs_printer_fails SET active = 'N'";
		try {
			$this->_query($sql);
		} catch (Exception $e) {
			myerror_log("error updating gprs_printer_fails to active=N: ".$this->getLastErrorText()); // do nothing, probably nothing to update
		}
	}
	
	function deleteMerchantFromGprsPrinterFailsTable($merchant_id)
	{
			$sql = "DELETE FROM gprs_printer_fails WHERE merchant_id = $merchant_id";
			try {
				$this->_query($sql);
			} catch (Exception $e) {
				myerror_log("error deleting gprs_printer_fails record for merchant id=$merchant_id: ".$this->getLastErrorText());  // do nothing, probably nothing to update
			}
		
	}
	
	static function updateGprsCallInHistoryTableForMerchantId($merchant_id)
	{
		$gpciha = new GprsPrinterCallInHistoryAdapter($mimetypes);
		$gpciha->createRecord($merchant_id);
	}
	
	function createRecord($merchant_id)
	{
		$merchant_id = intval($merchant_id);
		myerror_logging(3,"about to retrieve or create record for merchant_id: ".$merchant_id);
		// had to do this because of 2 digit demo merchants
		$merchant_data['merchant_id'] = $merchant_id;
		$m_options[TONIC_FIND_BY_METADATA] = $merchant_data;
		$cih_resource = Resource::findOrCreateIfNotExists($this, $url, $m_options);
		
		// now set fails to innactive
		//$this->setAllRecordsInnactive();
				
		//determine if this merchant is on the list
//		$sql = "SELECT 1 FROM gprs_printer_fails WHERE merchant_id = $merchant_id";
//		$options[TONIC_FIND_BY_SQL] = $sql;
//		if ($results = $this->select('',$options))
//		{
//			// first call in since fail so get the number and schedule a text
//			// also do not update last call in value, so text will execute correctly.
//			$mmm_adapter = new MerchantMessageMapAdapter($this->mimetypes);
//			if ($record = $mmm_adapter->getRecord(array("merchant_id"=>$merchant_id,"message_format"=>'T')))
//			{
//				myerror_log("FIRST CALL IN AFTER RE-BOOT!  schedule ***10   for merchant_id: ".$merchant_id);
//				$sms_number = $record['delivery_addr'];
//				$mmha = new MerchantMessageHistoryAdapter($mimetypes);
//				$map_id = $mmha->createMessage($merchant_id, $order_id, 'T', $sms_number, time()+30, 'A', 'reboot activation', '***10');
//			}
//			// now remove the record from the table
//			$this->deleteMerchantFromGprsPrinterFailsTable($merchant_id);
//		} else {
//			// not the first call in so update the last call in with this time
			$this->saveLastCallIn($cih_resource);
//		}
	}
	
	private function saveLastCallIn($cih_resource)
	{
		$cih_resource->last_call_in = time();
		$this->setLogLevel(0);
		return $cih_resource->save();		
		
	}
	
	/**
	 * @desc will record the last call in for things other than GRPS printers like Winapp or IP printers 
	 */
	static function recordPullCallIn($merchant_id)
	{
		$gpciha = new GprsPrinterCallInHistoryAdapter($mimetypes);
		$merchant_data['merchant_id'] = $merchant_id;
		$m_options[TONIC_FIND_BY_METADATA] = $merchant_data;
		$cih_resource = Resource::findOrCreateIfNotExists($gpciha, $url, $m_options);
		$gpciha->saveLastCallIn($cih_resource);
	}
}
?>