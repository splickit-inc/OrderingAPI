<?php
class HeartlandCurl extends SplickitCurl
{
	static function curl($url,$method,$data,$user_password)
	{
		if ($_SERVER['NO_MOCKS']) {
			return HeartlandCurl::curlNoMock($url, $method, $data, $user_password);
		}
		$user_pass = 'pos650000005197689:wv1ixega';
		$create_account_user_pass = 'splickit:12345';
		$service_name = 'Heartland';
		if (substr_count($url, 'katana')) {
			if (strtolower($method) == 'post' && substr($url, -18) == 'global/admin/users') {
				// creating a user in the heartland system
				$response['http_code'] = 204;
			} else if (strtolower($method) == 'post' && substr($url, -20) == 'global/user/accounts') {
				// creating a user in the heartland system
				$ts = time();
				$response['raw_result'] = '{"id":"'.$ts.'","pin":"1234","number":"6277204114005862","chainName":"Pita Pit","status":"ACTIVE","balances":[{"currency":"USD","amount":0,"formatted":"0.00"},{"currency":"Points","amount":0,"formatted":"0"}],"track2":";6277204114005862=380100059687KATANA?"}';
				$response['http_code'] = 200;
			} else if (strtolower($method) == 'get' && substr($url, -14) == 'global/account') {
				//{"id":"140105400000016373","number":"6277204114009518","chainName":"Pita Pit","status":"ACTIVE","balances":[{"currency":"USD","amount":0,"formatted":"0.00"},{"currency":"Points","amount":0,"formatted":"0"}],"track2":";6277204114009518=380100071527KATANA?","pin":null}
				$up = explode(":", $user_password);
				if ($up[1] == '4444') {
					$ts = "1441441441441441";	
				} else if ($up[1] == '3333') {
					$ts = "1331331331331331";
				} else {
					$ts = "5022440100000000118";
				}
				$response['raw_result'] = '{"id":"'.$up[0].'","number":"'.$ts.'","chainName":"Pita Pit","status":"ACTIVE","balances":[{"currency":"USD","amount":0,"formatted":"0.00"},{"currency":"Points","amount":0,"formatted":"0"}],"track2":";'.$ts.'=380100071527KATANA?","pin":null}';
				$response['http_code'] = 200;
			} else if (strtolower($method) == 'get' && substr($url, -22) == 'global/account/history') {
			  if ($user_password == ':') {
					$response['raw_result'] = '{"code":403,"name":"Forbidden","description":"Forbidden"}';
			  		$response['http_code'] = 403;
			  } else {
			      //this is a balance inquirey only response.  probably need something with real vluea
			 	 //$response['raw_result'] = '{"history":[{"id":"1xxxxxxxxxxx047002","type":"Balance Inquiry","date":"2014-09-16T01:53:53Z","store":null,"transactionAmount":null,"status":{"code":0,"name":"okay","description":"okay"}},{"id":"1xxxxxxxxxxx047094","type":"Balance Inquiry","date":"2014-09-16T01:53:38Z","store":null,"transactionAmount":null,"status":{"code":0,"name":"okay","description":"okay"}},{"id":"1xxxxxxxxxxx047089","type":"Balance Inquiry","date":"2014-09-16T01:53:28Z","store":null,"transactionAmount":null,"status":{"code":0,"name":"okay","description":"okay"}},{"id":"1xxxxxxxxxxx047097","type":"Balance Inquiry","date":"2014-09-15T22:07:18Z","store":null,"transactionAmount":null,"status":{"code":0,"name":"okay","description":"okay"}}]}';
			  		$response['raw_result'] = '{"history":[{"id":"1xxxxxxxxxxx058396","type":"Redeem","date":"2014-09-16T12:42:35-07:00","store":"Barcode Scanner","transactionAmount":{"currency":"Points","amount":-90,"formatted":"-90"},"status":{"code":0,"name":"okay","description":"okay"}},{"id":"1xxxxxxxxxxx058391","type":"Load","date":"2014-09-16T12:42:33-07:00","store":"splickitstore","transactionAmount":{"currency":"Points","amount":100,"formatted":"100"},"status":{"code":0,"name":"okay","description":"okay"}},{"id":"1xxxxxxxxxxx058394","type":"Balance Inquiry","date":"2014-09-16T19:42:33Z","store":null,"transactionAmount":null,"status":{"code":0,"name":"okay","description":"okay"}},{"id":"1xxxxxxxxxxx058389","type":"Balance Inquiry","date":"2014-09-16T19:42:31Z","store":null,"transactionAmount":null,"status":{"code":0,"name":"okay","description":"okay"}},{"id":"1xxxxxxxxxxx058386","type":"Promotion","date":"2014-09-16T12:42:30-07:00","store":"Barcode Scanner","transactionAmount":{"currency":"Points","amount":7,"formatted":"7"},"status":{"code":0,"name":"okay","description":"okay"}},{"id":"1xxxxxxxxxxx058383","type":"Register","date":"2014-09-16T12:42:28-07:00","store":"Crossroads Town Center","transactionAmount":null,"status":{"code":0,"name":"okay","description":"okay"}}]}';			  	
					$response['http_code'] = 200;
			  }
			} else {
				die("BUILD MOCK ENDPOINT FOR heartland curl Katana");
			}
		} else if (substr_count($url, 'archer')) {		
			if (substr_count($url, 'create')) {
				// {"pin":"9294","status.code":"200","status.description":"The request has succeeded","status.name":"OK","sva":"5022440100002635014","track2":";5022440100002635014=380100011691ARCHER?"}
				$pin = rand(1000, 9999);
				$sva = time();
				$response['raw_result'] = '{"'.$pin.'":"9294","status.code":"200","status.description":"The request has succeeded","status.name":"OK","sva":"'.$sva.'","track2":";5022440100002635014=380100011691ARCHER?"}';
				$response['http_code'] = 200;
			} else if (substr_count($url, 'load')) {
				//{"notes":"","order":"1xxxxxxxxxxx020149","rewards":"","status.code":"200","status.description":"The request has succeeded","status.name":"OK","sva":"XXXXXXXXXXXXXX0137","sva.balances":"Points 1000","sva.registered":"true","sva.status":"ACTIVE"}
				//throw new Exception('please set up mock object for load');
				$heartland_order_number = rand(1111111111, 1999999999);
				$response['raw_result'] = '{"notes":"","order":"'.$heartland_order_number.'","rewards":"","status.code":"200","status.description":"The request has succeeded","status.name":"OK","sva":"'.$data['sva'].'","sva.balances":"Points '.$data['amount'].'","sva.registered":"true","sva.status":"ACTIVE"}';
				$response['http_code'] = 200;
			} else if (substr_count($url, 'reward')) {
				//{"notes":"","order":"1xxxxxxxxxxx020149","rewards":"","status.code":"200","status.description":"The request has succeeded","status.name":"OK","sva":"XXXXXXXXXXXXXX0137","sva.balances":"Points 1000","sva.registered":"true","sva.status":"ACTIVE"}
				//throw new Exception('please set up mock object for load');
				$heartland_order_number = rand(1111111111, 1999999999);
				$response['raw_result'] = '{"notes":"Points Earned: 33 Points","order":"'.$heartland_order_number.'","rewards":"Points 33","status.code":"200","status.description":"The request has succeeded","status.name":"OK","sva":"'.$data['sva'].'","sva.balances":"'.$data['amount'].'","sva.registered":"true","sva.status":"ACTIVE"}';
				$response['http_code'] = 200;
			} else if (substr_count($url, 'inquiry')) {
				// {"notes":"","rewards":"","status.code":"200","status.description":"The request has succeeded","status.name":"OK","sva":"XXXXXXXXXXXXXXX5014","sva.balances":"Points 1000,USD 0","sva.registered":"false","sva.status":"ACTIVE"}
				// {"status.code":"400","status.description":"Unknown account [XXXXXXXXXXXXXXX5213].","status.name":"AccountNotFound"}
				if (substr($data['sva'],-5) == '88888') {
					$response['raw_result'] = '{"notes":"","rewards":"","status.code":"200","status.description":"The request has succeeded","status.name":"OK","sva":"XXXXXXXXXXXXXXX8888","sva.balances":"Points 1500,USD 0","sva.registered":"false","sva.status":"ACTIVE"}';
					$response['http_code'] = 200;
				} else if (substr($data['sva'],-5) == '55555') {
					$response['raw_result'] = '{"notes":"","rewards":"","status.code":"200","status.description":"The request has succeeded","status.name":"OK","sva":"XXXXXXXXXXXXXXX5555","sva.balances":"Points 500,USD 5000","sva.registered":"false","sva.status":"ACTIVE"}';
					$response['http_code'] = 200;
				} else if (substr($data['sva'],-5) == '33333') {
					$response['raw_result'] = '{"notes":"","rewards":"","status.code":"200","status.description":"The request has succeeded","status.name":"OK","sva":"XXXXXXXXXXXXXXX3333","sva.balances":"Points 500,USD 150","sva.registered":"false","sva.status":"ACTIVE"}';
					$response['http_code'] = 200;
				} else if (substr($data['sva'],-5) == '22222') {
					$response['raw_result'] = '{"notes":"","rewards":"","status.code":"200","status.description":"The request has succeeded","status.name":"OK","sva":"XXXXXXXXXXXXXXX3333","sva.balances":"Points 25,USD 0","sva.registered":"false","sva.status":"ACTIVE"}';
					$response['http_code'] = 200;
				} else if ($data['sva'] == $_SERVER['heartland_test_loyalty_number']) {
					$response['raw_result'] = '{"notes":"","rewards":"","status.code":"200","status.description":"The request has succeeded","status.name":"OK","sva":"XXXXXXXXXX'.substr($data['sva'], -5).'","sva.balances":"Points 0,USD 0","sva.registered":"false","sva.status":"ACTIVE"}';
					$response['http_code'] = 200;
				} else { //if (substr($data['sva'],-5) == '77777') {
					$response['raw_result'] = '{"status.code":"400","status.description":"Unknown account [XXXXXXXXXXXXXXX'.substr($data['sva'], -4).'].","status.name":"AccountNotFound"}';
					$response['http_code'] = 400;
				}
			} else if (substr_count($url, 'redeem')) {
				// {"notes":"Please go to test.heartlandgiftcard.com and REGISTER your account. Your PIN is  9294","order":"140104370000044109","owed":"Points 0","rewards":"","status.code":"200","status.description":"The request has succeeded","status.name":"OK","sva":"XXXXXXXXXXXXXXX5014","sva.balances":"Points 500,USD 0","sva.registered":"false","sva.status":"ACTIVE"}
				if ($data['currency'] == 'USD') {
					myerror_log("doing mock redeem for stored value");
					if (substr($data['sva'],-5) == '55555') {
						$heartland_internal_order = rand(1111111111, 1999999999);
						$amount_in_dollars = $data['amount'] / 100;
						$remaining_balance = 50.00 - $amount_in_dollars;
						$response['raw_result'] = '{"notes":"","owed":"USD 0","rewards":"","status.code":"200","status.description":"The request has succeeded","status.name":"OK","sva":"XXXXXXXXXXXXXXX5555","order":"'.$heartland_internal_order.'","sva.balances":"Points 500,USD '.$remaining_balance.'","sva.registered":"true","sva.status":"ACTIVE"}';
						$response['http_code'] = 200;
					} else if (substr($data['sva'],-5) == '33333') {
						$amount_in_dollars = $data['amount'] / 100;
						$response['raw_result'] = '{"status.code":"400","status.description":"For the account [123456789033333] cannot pay the amount ['.$amount_in_dollars.' USD] against the current balance [1.50 USD].","status.name":"InsufficientFunds"}';
						$response['http_code'] = 400;
					} else {
						// {"status.code":"400","status.description":"For the account [XXXXXXXXXXXXXX7224] cannot pay the amount [10.59 USD] against the current balance [5.59 USD].","status.name":"InsufficientFunds"}
						throw new Exception('please set up mock object for this stored value redeem');
					}
				} else {
					myerror_log("doing mock redeem for loyalty points");
					if (substr($data['sva'],-5) == '88888') {
						$heartland_internal_order = rand(1111111111, 1999999999);
						$remaining_balance = 1500 - $points;
						$response['raw_result'] = '{"notes":"","order":"'.$heartland_internal_order.'","owed":"Points 0","rewards":"","status.code":"200","status.description":"The request has succeeded","status.name":"OK","sva":"'.$data['sva'].'","sva.balances":"Points 1000","sva.registered":"true","sva.status":"ACTIVE"}';
						$response['http_code'] = 200;
					} else {
						//{"status.code":"400","status.description":"Amount limit [500 Points] exceeded for payment type [STOREDVALUE].","status.name":"PaymentTypeLimitExceeded"}
						throw new Exception('please set up mock object for this loyalty points redeem');
					}	
				}
			}
		} else {
			$response['error'] = "FAILURE. Could not connect to ".$service_name;
			myerror_log("ERROR!  could not connect to ".$service_name);
		}
		return $response;
	}
	
	static function curlNoMock($url,$method,$data,$user_password)
	{		
		if ($api_key = getProperty('heartland_api_key')) {
			; // all is good
		} else {
			$api_key = 'hrhq3coqbkuv';	
		}		
		
		if ($ch = curl_init($url))
		{		
			$headers = array(                                                                          
				    'Content-type: application/json',
					'Accept: application/json');
			if (strtolower($method) == 'post') {
				curl_setopt($ch, CURLOPT_POST, 1);
			} else if (strtolower($method) == 'put') {
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
			}  
			
			/*else if (strtolower($method) == 'put') {
				// how do we set a PUT?
			} */
			if (substr_count($url, 'katana')) {
				$headers[] = 'Api-Key: '.$api_key; 
			}
			if ($user_password) {
				curl_setopt($ch, CURLOPT_USERPWD, $user_password);
			}
			if ($data) {
				$json_payload = json_encode($data);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);                                                                  
			}
			
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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