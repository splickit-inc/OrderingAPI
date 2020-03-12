<?php

class SplickitService
{
	var $good_codes = array("200"=>1,"201"=>1,"204"=>1);
	var $success_no_content = '204 No Content';
	var $curl_response;
	var $method;
	var $data;
	var $service_name = "splickit";
	var $results_array;

	const SUCCESS = 'success';
	const FAILURE = 'failure';

	function __construct($data)
	{
		setLogLevelForObjectNameIfExists(get_class($this));
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

		//determine if there is going to be a name collision with the response from the service in use, and reset the field appropriately
		if ($result['status']) {
			$name = $this->service_name;
			$result[$name."_status"] = $result['status'];
		}
		$result['http_code'] = $response['http_code'];
		$result['status'] = $this->getSuccessStatusFromFullResult($result);
		logData($result, "Curl Response",3);
        myerror_log("http response code: ".intval($response['http_code']));
		return $result;
	}

	function getSuccessStatusFromFullResult($result)
	{
		return $this->getSuccessFromHttpCode($result['http_code']);
	}
	
	function getSuccessFromHttpCode($returned_code)
	{
		if ($this->good_codes[$returned_code]) {
			return self::SUCCESS;
		} else {
			return self::FAILURE;
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
	    
		myerror_log("ERROR! processing curl: ".$response['error']);
	    $this->raw_response = $response['error'];
	    return false;		
	}

    function isSuccessfulResponse($response)
    {
        return isset($this->good_codes[$response['http_code']]);
    }
	
}
?>
