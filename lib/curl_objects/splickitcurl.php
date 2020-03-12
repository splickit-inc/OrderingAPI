<?php
class SplickitCurl
{
	
	/**
	 * 
	 * @desc does a curl and returns an array with response information 
	 * @param resource a cURL handle $curl
	 * @return array ('raw_result','error','error_no','curl_info','http_code')
	 */
	static function curlIt(&$curl)
	{
		if (isLaptop() && getProperty('verbose_curl') == 'true') { 
			curl_setopt($curl, CURLOPT_VERBOSE,1);
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST,'TLSv1.2');
		if (isLaptop() || isStagingTypeServer() || getProperty("do_not_verifypeer") == true || getProperty("do_not_verifypeer") == 'true') {
			if (isNotProd()) {
				myerror_log("we are setting verify peer to false");
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			}
		}
		$time1 = microtime(true);
		if ($raw_result = curl_exec($curl)) {
            $curl_info = curl_getinfo($curl);
			$clean_raw_result = cleanUpXML($raw_result);
			myerror_log("Clean Raw Result from ".get_called_class()." to ".$curl_info['url'].": ".cleanUpDoubleSpacesCRLFTFromString($clean_raw_result));
			$response['raw_result'] = $raw_result;
		} else {
            $curl_info = curl_getinfo($curl);
        }
		$time2 = microtime(true);
		$diff = $time2-$time1;
		$response['error'] = curl_error($curl);
		$response['error_no'] = curl_errno($curl);
		if ($response['error_no'] == 28) {
			$response['error'] = 'Timeout';
		}
		if ($response['error']) {
			myerror_log("Curl Error: ".$response['error']);
		}
		$curl_info['time_of_request_in_seconds'] = number_format($diff,3);
		$response['curl_info'] = $curl_info;
		$response['http_code'] = $curl_info['http_code'];
        logData($curl_info,'CURL INFO',5);
        myerror_log("http_code: ".$curl_info['http_code'],3);
		return $response;
	}
}
?>