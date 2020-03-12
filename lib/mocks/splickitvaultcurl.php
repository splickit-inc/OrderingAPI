<?php
class SplickitVaultCurl extends SplickitCurl
{
	static function curlIt($endpoint,$data)
	{
		myerror_log("MOCKS:  we are starting the mock object in splickit vault curl");
		if ($_SERVER['NO_MOCKS']) {
			myerror_log("MOCKS:  redirecting to real splickit vault curl");
			return SplickitVaultCurl::curlItNoMock($endpoint, $data);
		}
		if (substr($endpoint, 0,21) == 'credit_cards/existing')
		{
			$credit_card = SplickitVaultCurl::getCardData($data);
			$return_data['data']['credit_card'] = $credit_card;
			$http_code = 200;		
			$return_data['status'] = '200 OK';
		}
		else if ($data['card_data']['cc_exp_date'] == '1222')
		{
			$return_data['error'] = "couldn't connect to host";
			return $return_data;
		}
		else if ($data['card_data']['cc_number'] == '4111114567111111')
		{
			$return_data['status'] = '422 Unprocessable Entity';
			$return_data['errors']['credit_card']['number'][] = 'is not a valid credit card number';
			$https_code = 422;
		}
		else if ($data['card_data']['cc_exp_date'] == '0312')
		{
			$return_data['status'] = '422 Unprocessable Entity';
			$return_data['errors']['credit_card']['year'][] = 'expired';
			$https_code = 422;
		}
		else if ($data['card_data']['uuid'] == 'existing-3906-4l68-00o8-zd0c')
		{
			$return_data['status'] = '422 Unprocessable Entity';
			$return_data['errors']['credit_card']['identifier'][] = 'is already taken';
			$https_code = 422;
		}
		else if (substr($endpoint, 0, 18) == 'credit_cards/test-')
		{
			$e = explode('/', $endpoint);
			$d = explode('-', $e[1]);
			$credit_card['number'] = '4111111111111111';
			$credit_card['zip'] = $d[1];
			$cc_exp_date = $d[2];
			$month = substr($cc_exp_date, 0,2);
			$year = '20'.substr($cc_exp_date,2,2);
			$credit_card['year'] = $year;
			$credit_card['month'] = $month;
			$return_data['data']['credit_card'] = $credit_card;
			$http_code = 200;		
			$return_data['status'] = '200 OK';
		}
		else if (true)
		{
			if ( substr($endpoint,-11) == 'ttttt-ttttt') {
				$error_no = 28;
				$error = 'Timeout';
			} else {
				$credit_card = SplickitVaultCurl::getCardData($data);
				$return_data['data']['credit_card'] = $credit_card;
				$http_code = 200;		
				$return_data['status'] = '200 OK';
			}			
		} else {
			$return_data['errors']['credit_card'] = $errors;
			$error = 'some error';
			$https_code = 422;
		}
			
		if ($return_data) {
			$json_result = json_encode($return_data);
		}

		$response['raw_result'] = $json_result;
		$response['error'] = $error;
		$response['error_no'] = $error_no;
		$response['curl_info'] = $curl_info;
		$response['http_code'] = $http_code;
		return $response;

	}
	
	static function getCardData($data)
	{
		$credit_card['number'] = '4111111111111111';
		$credit_card['zip'] = '12345';
		$credit_card['year'] = '2020';
		$credit_card['month'] = '10';
		
		if ($zip = $data['card_data']['zip']) {
			$credit_card['zip'] = $zip;
		} else if (preg_match('%credit_cards/([0-9]{4}-[0-9a-z]{5}-[0-9a-z]{5}-[0-9a-z]{5})%', $data['end_point'], $matches)) {
			$d = explode('-', $matches[1]);
			$credit_card['zip'] = $d[1];
		}
		if ($cc_exp_date = $data['card_data']['cc_exp_date'])
		{
			$month = substr($cc_exp_date, 0,2);
			$year = '20'.substr($cc_exp_date,2,2);
			$credit_card['year'] = $year;
			$credit_card['month'] = $month;
		}
		return $credit_card;
		
	}
	
	static function curlItNoMock($endpoint,$data)
	{
		$url = getProperty('vio_url').$endpoint;
		$user_pwd = "splikit:50F3304A-B665-11E2-805C-14109FE16435";
		if (isProd()) {		
			$user_pwd = "splikit:3d456672-d55d-11e3-ae80-12314000803f";
		}
		myerror_log("the url: ".$url);
		if ($curl = curl_init($url))
		{
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			
			curl_setopt($curl, CURLOPT_VERBOSE, 0);
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT,5);
			curl_setopt($curl, CURLOPT_TIMEOUT, 20);				
			if ($card_data = $data['card_data'])
			{
				// reformat data for new vault
				$clean_data = SplickitVault::cleanCCdata($card_data);
				$payload['credit_card'] = $clean_data;
				$json = json_encode($payload);
				// 32 bit server can handle cc number as int so have to change it here
				$json = SplickitVault::convertCCNumberInJsonToNumber($json);
				if ($data['update'])
				{
					myerror_log("We are setting the curl request to PUT");
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
				}
				else {
					curl_setopt($curl, CURLOPT_POST, 1);
				}
				curl_setopt($curl, CURLOPT_POSTFIELDS,$json);
				$headers = array('Content-Type: application/json','Content-Length: ' . strlen($json));
				curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			}
			curl_setopt($curl, CURLOPT_USERPWD, $user_pwd); 

//CHANGE THIS -- verify peer needs to be false or it fails.  need to explore this
			setSessionProperty("do_not_verifypeer", true);

			if ($_SERVER['HTTP_NO_CC_CALL'] == 'true') {
				$credit_card['number'] = '4111111111111111';
				$credit_card['zip'] = '12345';
				$credit_card['year'] = '2020';
				$credit_card['month'] = '10';
				$return_data['data']['credit_card'] = $credit_card;
				$return_data['status'] = '200 OK';
				
				$response['raw_result'] = json_encode($return_data);
				$response['http_code'] = 200;
				return $response;
			} else {
				$response = parent::curlIt($curl);
			}
			curl_close($curl);
		} else {
			myerror_log("COULD NOT CONNECT TO VAULT!");
			SmsSender2::sendEngineeringAlert("COULD NOT CONNECT TO NEW VAULT!");
			$response['error'] = "Could Not Connect To SPlickit Vault";
		}
		return $response;
	}	
}
?>