<?php
class FishbowlCurl extends SplickitCurl
{

	static function curlIt($url,$data)
	{
		$service_name = 'Fishbowl';
		$json = json_encode($data); 
		$str_length_json = strlen($json);
		$string = http_build_query($data);
		$length = strlen($string);
		if ($ch = curl_init($url))
		{		
			//curl_setopt($ch, CURLOPT_POSTFIELDS, $json);                                                                  
			curl_setopt($ch, CURLOPT_POSTFIELDS, $string);                                                                  
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,5);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);				
			
			//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			$headers[] = 'Content-Type: application/x-www-form-urlencoded';
			$headers[] = 'Content-Length: ' . $length;
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			logCurl($url,'POST',null,$headers,$string);
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