<?php
class FaxageCurl extends SplickitCurl
{
	
	static function curlIt($url,$form_data)
	{
		if ($curl = curl_init($url))
		{
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		
			curl_setopt($curl, CURLOPT_VERBOSE, 0);
			if ($form_data)
			{
				curl_setopt($curl, CURLOPT_POST, 1);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $form_data);
			}
			curl_setopt($curl, CURLOPT_HEADER, 0);
		
			//$headers = array('Content-Type: multipart/form-data', 'Accept-Charset: UTF-8');
			//curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			$response = parent::curlIt($curl);
			curl_close($curl);
		} else {
			$response['error'] = "FAILURE. Could not connect to Faxage";
			myerror_log("ERROR!  could not connect to Faxage!");
		}
		return $response;
	}
	
}
?>