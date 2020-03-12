<?php
class SFaxCurl extends SplickitCurl
{
	
	static function curlIt($url,$postData)
	{
		if ($ch = curl_init($url))
		{ 
			curl_setopt($ch, CURLOPT_URL, $url); 
			curl_setopt($ch, CURLOPT_HEADER, 1); 
			//curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
			curl_setopt($ch, CURLOPT_NOBODY, 0); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  
			if ($postData)
			{
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
			}
			curl_setopt($ch, CURLOPT_VERBOSE,0);
			$response = parent::curlIt($ch);
			curl_close ($ch);
		} else {
			$response['error'] = "FAILURE. Could not connect to SFax";
			myerror_log("ERROR!  could not connect to SFax!");
		}
		return $response;
	}
}
?>