<?php
class FpnLoyaltyCurl extends SplickitCurl
{
	static function curlIt($url,$soap_xml)
	{
		$service_name = 'Fpn Loyalty';
		
		if ($ch = curl_init($url))
		{		
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_VERBOSE,0);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $soap_xml);                                                                  
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
			    'Content-Type: application/soap+xml',                                                                                
			    'Content-Length: ' . strlen($soap_xml))                                                                       
			);
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