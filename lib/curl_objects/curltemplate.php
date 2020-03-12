<?php
class xxxxxxCurl extends SplickitCurl
{
	static function curlIt($url,$data)
	{
		$service_name = 'xxxxxx';
		if ($ch = curl_init($url))
		{		
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_VERBOSE,0);
			if ($data)
			{
				$json_data = json_encode($data);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);                                                                  
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
				    'Content-Type: application/json',                                                                                
				    'Content-Length: ' . strlen($json_data))                                                                       
				);
			}
			$response = parent::curlIt($ch);
			curl_close($ch);
		} else {
			$response['error'] = "FAILURE. Could not connect to ".$service_name;
			myerror_log("ERROR!  could not connect to ".$service_name);
		}
		return $response;
	}
}
?>