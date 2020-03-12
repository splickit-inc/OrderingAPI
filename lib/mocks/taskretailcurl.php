<?php
class TaskRetailCurl extends SplickitCurl
{
  static function curlIt($url,$form_data, $cookie=null)
  {
  	if ($_SERVER['NO_MOCKS']) {
  		return TaskRetailCurl::curlItNoMock($url, $form_data, $cookie);
  	}
    //http://136.179.5.40/xchangenetFR/services/xn_authenticate.asmx?op=authenticateClient

    if (substr_count($url,'op=authenticateClient') > 0) {
      // ===========================
      // = Authentication Requests =
      // ===========================
      
      if ($_SERVER['AUTOFAIL_AUTHENTICATION']) {
        $response['raw_result'] = "<authenticateClientResult>false</authenticateClientResult>";
        $response['http_code'] = 200;
      } else {
        // Successful login
        $response['raw_result'] = "<authenticateClientResult>true</authenticateClientResult>";
        $response['http_code'] = 200;
      }
    } else {
      
      // =======================
      // = Send Order Requests =
      // =======================
      
      if (strpos($form_data, "<PLUU>") !== false) {
        // Bad request. Had PLUU field instead of PLU
        $response['raw_result'] = TaskRetailCurl::missingPLUResponseBody();
        $response['http_code'] = 200;
      }
      else if (strpos($form_data, "Envelopee")) {
        // Bad SOAP format
        $response['raw_result'] = TaskRetailCurl::invalidSoapFormatResponseBody();
        $response['http_code'] = 500;
      } else {
        // Good request
        $response['raw_result'] = "<Successful>true</Successful>";
        $response['http_code'] = 200;
      }
    }

    return $response;
  }
  
  static private function invalidSoapFormatResponseBody()
  {
    return "<soap:Body><soap:Fault><soap:Code><soap:Value>soap:Receiver</soap:Value></soap:Code><soap:Reason><soap:Text xml:lang=\"en\">Server was unable to process request. ---&gt; Request format is invalid: Missing required soap:Envelope element.</soap:Text></soap:Reason><soap:Detail /></soap:Fault></soap:Body>";
  }
  
  static private function missingPLUResponseBody()
  {
    return "<Successful>false</Successful><ErrorNumber>101</ErrorNumber><ErrorString>Order Incomplete: Missing PLU Value @ 10
</ErrorString>";
  }
  
	static function curlItNoMock($url,$data, $cookie=null)
	{
		$service_name = 'Task Retail';
		if ($ch = curl_init($url))
		{		
    	curl_setopt($ch, CURLOPT_URL, $url);
    	curl_setopt($ch, CURLOPT_POSTFIELDS, $soap_xml);
    	curl_setopt($ch, CURLOPT_HEADER, 1);
			if ($data)
			{
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);                                                                  
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
				    'Content-Type: application/soap+xml',                                                                                
				    'Content-Length: ' . strlen($data))                                                                       
				);
			}
    	if ($cookie) {
    		curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    	}
      
		$response = parent::curlIt($ch);
			
		curl_close($curl);
		} else {
			$response['error'] = "FAILURE. Could not connect to ".$service_name;
			myerror_log("ERROR!  could not connect to ".$service_name);
		}
		return $response;
	}  
}
?>
