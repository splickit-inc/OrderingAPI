<?php
class HeartlandCurl extends SplickitCurl
{
	static function curl($url,$method,$data,$user_password,$api_key)
	{		
		if ($ch = curl_init($url))
		{
			if (strtolower($method) == 'post') {
				curl_setopt($ch, CURLOPT_POST, 1);
			} else if (strtolower($method) == 'put') {
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
			}  
			
			/*else if (strtolower($method) == 'put') {
				// how do we set a PUT?
			} */
			if ($user_password) {
				curl_setopt($ch, CURLOPT_USERPWD, $user_password);
			}
			$headers = array(
				'Content-type: application/json',
				'Accept: application/json');

			if (substr_count($url, 'katana')) {
				$headers[] = 'Api-Key: '.$api_key;
			}
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			if ($data) {
				$json_payload = json_encode($data);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);                                                                  
			}
			
			logCurl($url,$method,$user_password,$headers,$json_payload);
			$response = parent::curlIt($ch);
			curl_close($ch);
		} else {
			$response['error'] = "FAILURE. Could not connect to ".$url;
			myerror_log("ERROR!  could not connect to ".$url);
		}
		return $response;
	}
}
?>