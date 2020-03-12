<?php
class ShorepointsLoyaltyService extends SplickitLoyaltyService
{
	private $url;
	
	var $response_fields;
		
	function __construct()
	{
    $this->url = getProperty('shorepoints_url');		
	}
	
	function getShorePointsAccount($phonenumber_or_loyalty_number)
	{
		$url = $this->url.$phonenumber_or_loyalty_number;
		myerror_log("the url for the shorepoints call is: ".$url);
		$results = $this->send($url, 'GET', $data);
		if ($results['status'] == 'success' || $results['error'] == null) {
			// add dummy history
			$results['history'] = array();
			return $results;
		} else {
			$this->response_fields = $results;
		}
	}
	
	function createShorePointsAccountFromPhoneNumber($phone_number)
	{
		$phone_number = str_ireplace("-", "", $phone_number);
		$phone_number = str_ireplace("(", "", $phone_number);
		$phone_number = str_ireplace(")", "", $phone_number);
		$phone_number = str_ireplace(".", "", $phone_number);
		$data['phone_number'] = $phone_number;
		return $this->send($this->url, 'POST', $data);
	}
	
	function send($url,$method,$data)
	{
		$response = ShorepointsCurl::curl($url, $method, $data);		
		$this->curl_response = $response;
		$result_array = $this->processCurlResponse($response);
		if ($result_array['status'] == 'failure' && (!validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($response, 'error'))) {
			// due to the fact that error comes back as 'message' from JM sometimes so if error is NOT set do this......
			$error = $result_array['message'];
			$this->curl_response['error'] = $error;
			$result_array['error'] = $error;
		} 
		return $result_array;
	}
	
	function processRawReturn($raw_return)
	{	
		$return_array = json_decode($raw_return,true);
		if (is_array($return_array['response'])) {
			return $return_array['response'];
		} else {
			return $return_array;
		}
	}
}
?>
