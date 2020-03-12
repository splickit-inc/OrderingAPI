<?php
class GoogleGeoCoderCurl extends SplickitCurl
{
	static function curlIt($url,$data)
	{
		$service_name = 'GoogleGeoCoder';
		if ($ch = curl_init($url))
		{		
			curl_setopt($ch, CURLOPT_URL, $url);
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
			curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"]);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			
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