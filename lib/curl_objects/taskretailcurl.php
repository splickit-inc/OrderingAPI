<?php
class TaskRetailCurl extends SplickitCurl
{
	static function curlIt($url,$data, $cookie=null)
	{
		$service_name = 'Task Retail';
		if ($ch = curl_init($url)) {
    		//curl_setopt($ch, CURLOPT_URL, $url);
    		//curl_setopt($ch, CURLOPT_POSTFIELDS, $soap_xml);
    		curl_setopt($ch, CURLOPT_HEADER, 1);
			if ($data) {
				$content_length = strlen($data);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);                                                                  
				curl_setopt($ch, CURLOPT_POST, 1);
				if ($cookie == 'V2') {
                    $headers = array(
                        'Authorization: Key '.getProperty('task_retail_api_key'),
                        'Content-Type: application/json',
                        'Content-Length: ' . $content_length);
                    $cookie = null;
                    unset($cookie);
                } else {
                    $headers = array(
                        'Content-Type: application/soap+xml; charset=utf-8',
                        'Content-Length: ' . $content_length);
                }

				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			}
			if ($cookie) {
				curl_setopt($ch, CURLOPT_COOKIE, $cookie);
			}
			//myerror_log("curl -X POST -v -H 'Content-Length: $content_length' -H 'application/soap+xml; charset=utf-8'  -d '$data' $url");
			logCurl($url,'POST',$up,$headers,$data);
			$response = parent::curlIt($ch);
            if ($cookie == 'V2') {
                $body = substr($response['raw_result'],-($response['curl_info']['download_content_length']));
                $response['raw_result'] = $body;
            }
			curl_close($ch);
		} else {
			$response['error'] = "FAILURE. Could not connect to ".$service_name;
			myerror_log("ERROR!  could not connect to ".$service_name);
		}
		return $response;
	}
}
?>