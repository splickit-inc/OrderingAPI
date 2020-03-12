<?php
class PhaxioCurl extends SplickitCurl
{
	
	static function curlIt($url,$data)
	{
		if ($_SERVER['NO_MOCKS']) {
			return PhaxioCurl::curlItNoMock($url, $data);
		}
		// this is part of unit testing
		if ($_SERVER['TEST_TIMEOUT'] == 'true')
		{
			$url = "http://localhost:8888/smaw/phone/testtimeout";
			$response['error'] = 'Timeout';
			$response['error_no'];
		}
		else if ($_SERVER['PHAXIO_FORCE_FAIL'] == 'true')	
		{
			$response['error'] = "FAILURE. Could not connect to faxio";
		} 
		else
		{
			$id = rand(111111, 999999);
			$response['raw_result'] = '{"success":true,"message":"Fax queued for sending","faxId":'.$id.',"data":{"faxId":'.$id.'}}';
		}
		return $response;
	}	
	
	static function curlItNoMock($url,$data)
	{
		myerror_log("FAX: starting phaxio curl");
		// this is part of unit testing
		if ($_SERVER['TEST_TIMEOUT'] == 'true')
			$url = "http://localhost:8888/smaw/phone/testtimeout";
		else if ($_SERVER['PHAXIO_FORCE_FAIL'] == 'true')	
			return false;
		
		// for services testing. phaxio blows up if you send localhost as a callback.
		if (substr_count($data['callback_url'], 'localhost:8888')) {
			$data['callback_url'] = str_replace('localhost:8888', 'test.splickit.com', $data['callback_url']);
		}

		if ($curl = curl_init($url))
		{		
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_VERBOSE, 0);
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			curl_setopt($curl, CURLOPT_HEADER, 0);
			curl_setopt($curl, CURLOPT_TIMEOUT, 5);
			$response = parent::curlIt($curl);
			// eg: {"success":true,"message":"Fax queued for sending","faxId":710673,"data":{"faxId":710673}}
			curl_close($curl);
		} else {
			$response['error'] = "FAILURE. Could not connect to faxio";
			myerror_log("ERROR!  could not connect to faxio!");
		}
		return $response;
	}	

}
?>