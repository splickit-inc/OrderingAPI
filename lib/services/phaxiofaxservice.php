<?php

class PhaxioFaxService extends SplickitFaxService
{
  var $key;
  var $secret;
  var $url;
  
	function PhaxioFaxService(&$message_resource)
	{
		parent::SplickitFaxService($message_resource);
		$this->service_name = "Phaxio";
		$this->key = getProperty('phaxio_key');
		$this->secret = getProperty('phaxio_secret');
		$this->url = getProperty('phaxio_url');
	}

	/**
	 * @desc will send a fax using fax service phaxio.  fax_data must pass in ('fax_no','fax_text')
	 * 
	 *  @return boolean
	 */
	
	function send($fax_data)
	{
		$key = $this->key;
		$key_secret = $this->secret;
		
		if (substr_count($fax_data['fax_text'], 'html') > 0)
			$text_type = 'html';
		else
			$text_type = 'text';
		
		$data['string_data']=$fax_data['fax_text'];
		$data['string_data_type']=$text_type;
		$data['to']=$fax_data['fax_no'];
		$data['api_key']=$key;
		$data['api_secret']=$key_secret;
		
		// generate call back.  need message map_id,stamp,order_id
		if (isset($this->message_resource->order_id)){
			if ($callback_url = $this->createTheCallBackURL())
				$data['callback_url'] = $callback_url;
		}
		
		logData($data, 'Phaxio fields',3);
		
		return $this->curlToPhaxio($data);
	}
	
	private function curlToPhaxio($data)
	{
		$url = $this->url;
		
		if ($response = PhaxioCurl::curlIt($url, $data))
		{		
			$this->curl_response_array = $response;
			if ($result = $response['raw_result'])
			{
				// eg: {"success":true,"message":"Fax queued for sending","faxId":710673,"data":{"faxId":710673}}
				$this->setMessageResourceResponse($result);
				$r = json_decode($result,true);
				if ($success = $r['success']) {
					$fax_id = $r['faxId'];
				}
				
				$message = $r['message'];
				
				myerror_log("success=$success");
				myerror_log("message=$message");
				myerror_log("fax_id=$fax_id");
				if ($success)
				{
					// set viewed value for the call back
					$this->message_resource->viewed = 'N';
					return true;
				}
				$error = $message;
				$this->setMessageResourceResponse($error);
			} else {
				$error = $response['error'];
				$error_number = $response['error_no'];
				$this->setMessageResourceResponse($error_number.' - '.$error);
			}
			myerror_log("ERROR SENDING FAX WITH Phaxio! something other than success returned.  result: ".$error);
			throw new Exception($error, 100);		
		}
		return false;
	}
	
	function getCallBackResult($data)
	{
		myerror_logging(3, "we are about to  retreived the status from the call back");
		myerror_logging(3, "phaxio fax object: ".$data['fax']);
		$fax_object = json_decode($data['fax'],true);
		$status = $fax_object['status'];
		if (strtolower($status) == 'failure') {
			$this->callback_fail_reason = 'Some Phaxio Failure';
			return false;
		} else {
			return true;
		}	
	}	
}