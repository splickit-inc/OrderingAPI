<?php
class StsCurl extends SplickitCurl
{
	static function curlIt($url,$xml)
	{

		$service_name = 'Smart Transaction Systems API';
        if ($curl = curl_init($url))
		{
            $method = 'POST';
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS,$xml);
            $headers[] = 'Content-Type: application/XML';
            $headers[] = 'Content-Length: ' . strlen($xml);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            logCurl($url,$method,null,$headers,$xml);
            $response = parent::curlIt($curl);
			curl_close($curl);
		} else {
			$response['error'] = "FAILURE. Could not connect to ".$service_name;
			myerror_log("ERROR!  could not connect to ".$service_name);
		}
		return $response;
	}
}
?>