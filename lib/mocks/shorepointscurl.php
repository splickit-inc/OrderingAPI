<?php
class ShorepointsCurl extends SplickitCurl
{
	static function curl($url,$method,$data)
	{
		if ($_SERVER['NO_MOCKS']) {
			return ShorepointsCurl::curlNoMock($url, $method, $data);
		}		
		if (substr($url, -9) == '/loyalty/') {		
			if ($method == 'POST') {
				if ($data['phone_number'] == '14567') {
					$response['raw_result'] = '{"status": "failed","code": 400,"message": "The number provided was not a valid phone number."}';
					$response['error'] = null;
					$response['error_no'] = 0;
					$response['http_code'] = 400;
					return $response;
				} else if (strlen($data['phone_number']) == 10) {	
					$response['raw_result'] = '{"status": "success","code": 200,"response": "3900000000004247"}';
					$response['error'] = null;
					$response['error_no'] = 0;
					$response['http_code'] = 200;
					return $response;
				}
				
			} else {
				if ( substr_count($url, 'history') >0 ) {
					$response['raw_result'] = null;
					$response['error'] = 'Timeout';
					$response['error_no'] = 28;
					return $response;
				}
			}
		} else if (substr_count($url, '1234123412') || substr_count($url, '1239087654')) {
					$response['raw_result'] = '{"status": "failed","code": 400,"message": "Account was Not Found"}';
					$response['error'] = null;
					$response['error_no'] = 0;
					$response['http_code'] = 404;
					return $response;

		} else if ($url == getProperty('shorepoints_url')."999999999?history") {
					$response['raw_result'] = '{"status": "failed","code": 400,"message": "Phone or Card\/Tag Number is Incorrect Format"}';
					$response['error'] = null;
					$response['error_no'] = 0;
					$response['http_code'] = 404;
					return $response;
		} else 	if ( substr_count($url, 'history') >0 ) {
			$response['raw_result'] = null;
			$response['error'] = 'Timeout';
			$response['error_no'] = 28;
			return $response;
		} else if ($url == getProperty('shorepoints_url').'1234567890') {
					$response['raw_result'] = '{
    "status": "success",
    "code": 200,
    "response": {
        "lpc_id": "348070",
        "phone_number": "123-456-7890",
        "card_number": "3C154325E7544F",
        "points": "3907",
        "registration_code": "1335",
        "store_number": "HD",
        "creation_date": "2013-03-22 10:09:08.493",
        "marketing_option": "1"
    }
}';
					$response['error'] = "there is no error";
					$response['error_no'] = 0;
					$response['http_code'] = 200;
					return $response;
			
		} else if ($url == getProperty('shorepoints_url').'1234567890?history') {
					$response['raw_result'] = '{
    "status": "success",
    "code": 200,
    "response": {
        "lpc_id": "348070",
        "phone_number": "123-456-7890",
        "card_number": "3C154325E7544F",
        "points": "3907",
        "registration_code": "1335",
        "store_number": "HD",
        "creation_date": "2013-03-22 10:09:08.493",
        "marketing_option": "1",
        "history": [
            {
                "transaction_date": "2013-03-25 09:37:00",
                "balance": "44",
                "store_number": "HD",
                "points_added": "44",
                "transaction_type": "Purchase"
            } ]
    }
}';
					$response['error'] = "there is no error";
					$response['error_no'] = 0;
					$response['http_code'] = 200;
					return $response;
			
		}
					
	}
	
	static function curlNoMock($url,$method,$data)
	{		
		if ($ch = curl_init($url))
		{

			$username = getProperty('jm_username');	
			$password = getProperty('jm_password');
			curl_setopt($ch, CURLOPT_USERPWD, $username.":".$password);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,5);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);				
			
			if (!isProd()) {
				myerror_log("setting verify peer to false becuase we 're not on productino ");
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			}
			if (strtolower($method) == 'post') {
				curl_setopt($ch, CURLOPT_POST, 1);
			}
			if ($data) {
				$json_payload = json_encode($data);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
				/*$headers = array(                                                                          
				    'Content-type: application/json',
					'Content-Length: '.strlen($json_payload));
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				 */                                                                 
			}
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