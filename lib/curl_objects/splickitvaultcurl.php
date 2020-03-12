<?php
class SplickitVaultCurl extends SplickitCurl
{
	static function curlIt($endpoint,$data)
	{
		$url = getProperty('vio_url').$endpoint;
        $user_pwd = getProperty('vio_vault_username_password');
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

			setSessionProperty("use_TLSv1",true);

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