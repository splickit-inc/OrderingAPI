<?php

class SplickitLoyaltyService extends SplickitService
{
	var $good_codes = array("200"=>1,"201"=>1,"202"=>1,"204"=>1);
	var $success_no_content = '204 No Content';
	var $curl_response;
	var $method;
	var $data;
	
	function __construct($data)
	{
		parent::__construct($data);
	}

	function setMethod($method)
	{
		$this->method = $method;
	}
	
	function setData($data)
	{
		$this->data = $data;
	}
	
	function getErrorFromCurlResponse()
	{
		return $this->curl_response['error'];
	}
	
	function processCurlResponse($response)
	{
		$result = array();
		if ($raw_return = $this->getRawResponse($response)) {
			$result = $this->processRawReturn($raw_return);
		}
		if ($response['error']) {
			$result['error'] = $response['error'];
			$result['error_no'] = $response['error_no'];
		}
		$result['status'] = $this->getSuccessFromHttpCode($response['http_code']);
		$result['http_code'] = $response['http_code'];
		logData($result, "Curl Response",3);
		$this->curl_response = $result;
		return $result;
	}
	
	function getSuccessFromHttpCode($returned_code)
	{
		if ($this->good_codes[$returned_code]) {
			return 'success';
		} else {
			return 'failure';
		}		
	}
	
	function processRawReturn($raw_return)
	{	
		$return_array = json_decode($raw_return,true);
		return $return_array;
	}
	
	function getRawResponse($response)
	{
		if ($raw_return = $response['raw_result']) {
			return $raw_return;
		}
		// case of no raw result but good response
		if ($response['http_code'] == 204) {
			return $this->success_no_content;
		}
	    
		myerror_log("ERROR! processing loyalty request: ".$response['error']);
	    $this->raw_response = $response['error'];
	    return false;		
	}
	
}
?>
