<?php
class FaxageFaxService extends SplickitFaxService
{
	
	function FaxageFaxService(&$message_resource)
	{
		parent::SplickitFaxService($message_resource);
		$this->service_name = "Faxage";
	}

	/**
	 * @desc will send a fax using fax service faxage.  fax_data must pass in ('fax_no','fax_text','order_id'). if no order_id is passed, the time stamp will be used
	 * 
	 *  @return boolean
	 */
	
	function send($fax_data)
	{
		$order_id = isset($fax_data['order_id']) ? $fax_data['order_id'] : time();
		$fax_file = $this->createFile($fax_data['order_id'], $fax_data['fax_text']);
		myerror_logging(3,"about to try to send fax");
		try {
			$this->sendWithFaxage($fax_file,$fax_data['fax_no']);
		} catch (Exception $e) {
			myerror_log("EXCEPTION thrown sending fax with faxage: ".$e->getMessage());
			throw $e;
		}
		return true;
	}
	
	private function sendWithFaxage($file,$fax_number)
	{
		$fh = fopen($file, "r");
		$fdata = fread($fh, filesize($file));
		fclose($fh);
	
		$b64data = base64_encode($fdata);	
		
		$data1[] = $file;
		$data2[] = $b64data;
		
		$form_data_alt['username'] = 'splickit';
		$form_data_alt['company'] = '17121';
		$form_data_alt['password'] = 'Spl1ck1t';
		$form_data_alt['recipname'] = 'adam';
		$form_data_alt['faxno'] = ''.$fax_number;
		$form_data_alt['operation'] = 'sendfax';	
		$form_data_alt['faxfilenames[0]'] = $file;
		$form_data_alt['faxfiledata[0]'] = $b64data;
		$form_data_alt['url_notify'] = $this->createTheCallBackURL();
		
		logData($form_data_alt, "faxage data",3);
	
		$result = $this->curlToFaxage($form_data_alt);
		$this->setMessageResourceResponse($result);
		
		if (substr_count($result, 'JOBID') < 1)
		{
			myerror_log("ERROR SENDING FAX!");
			throw new Exception("Error sending fax: result=".$result, 100);
		} else {
			$result = str_replace(': ', '=', $result);
			$result = str_replace(':', '=', $result);
		}
		
		$update_sql = "UPDATE Merchant_Message_History SET info = '".$result."' WHERE map_id = ".$this->message_resource->map_id." LIMIT 1";

		myerror_logging(3,"fax update sql: ".$update_sql);
		
		if (! $this->adapter->_query($update_sql)) {
			myerror_log("ERROR UPDATING FAX ROWS WITH RESULTS!");
		}

	}
	
	function curlToFaxage($form_data)
	{
		$url = getProperty('faxage_url');
		$response = FaxageCurl::curlIt($url,$form_data);
		$this->curl_response_array = $response;
		if ($response['raw_result']) {
			$result = $response['raw_result'];
		} else {
			$result = $response['error'];
		}
		if ($result == null) {
			$result = "Null Response";
		}
		return $result;
	}
		
	function getCallBackResult($data)
	{
		myerror_logging(3, "we are about to  retreived the status from the call back");
		
		$status = $data['shortstatus'];
		if (strtolower($status) == 'failure') {
			$this->callback_fail_reason = $data['longstatus'];
			return false;
		} else {
			return true;
		}
	}
	
}
?>