<?php

class SplickitVault
{
	protected $error;
	protected $error_no;
	protected $error_data;
	protected $return_status;
	protected $return_http_code;
	var $curl_response_array;
	var $vault_save_process;
	
	function SplickitVault()
	{
		// the vault
		;
	}
	
	function getCardInfoOnly($uuid)
	{
		if ($all_card_data = $this->getVaultRecord($uuid))
		{
			
			if ($card_info = $all_card_data['data']['credit_card']) {
				myerror_log("we have got the CC info");
				
				// add fields for backward compatibility
				$card_info['cc_number'] = $card_info['number'];
				$card_info['postal_code'] = $card_info['zip'];
				$exp_two_digit_year = substr($card_info['year'], 2,2);
				$month = $card_info['month'];
				$month = "0".$month;
				$month = substr($month, -2);
				$exp = $month.''.$exp_two_digit_year;
				$card_info['cc_exp'] = $exp;		
				return $card_info;
			} else if ($all_card_data['errors']) {
				$error = $all_card_data['errors'][0];
				foreach ($error as $name=>$value) {
					myerror_log("$name = $value");
				}
			} else {
				myerror_log("unkown error recieved from vault");
				MailIt::sendErrorEmail("unknown error trying to pull vault data", "check logs");
			}
		
		} else {
			myerror_log("there was no info retrieved from the new vault");
		} 
		return false;
	}
	
	function getVaultRecord($uuid)
	{
		//if (isLaptop() && $_SERVER['HTTP_NO_CC_CALL'] == 'true')
		$return = $this->curlIt("credit_cards/$uuid", null);
		return $return;
	}
	
	/**
	 * 
	 * @desc currently does NOTHING since this is not built on their side yet.  always returns true right now.
	 * @param $uuid
	 * @return boolean
	 */
	function deleteVaultRecord($uuid)
	{
//CHANGE THIS   we are waiting till they build this on their side.
		return true;
	}
	
	/**
	 * 
	 * @desc will save the CC info contained in the submitted user resource and inspire pay pull data. returns true on success
	 * @param array $inspire_pay_card_data
	 * @param Resource $user_resource
	 */
	
	function saveCardFromInspirePayReturnAndUserResource($inspire_pay_card_data,$user_resource)
	{
		$user_resource->set('zip',$inspire_pay_card_data['postal_code']);
		$user_resource->set('number',$inspire_pay_card_data['cc_number']);
		$user_resource->set('month',substr($inspire_pay_card_data['cc_exp'], 0,2));
		$user_resource->set('year','20'.substr($inspire_pay_card_data['cc_exp'], 2,2));
		return $this->save($user_resource);				
	}
	
	/**
	 * @desc wrapper method to take card data and either save it or update it
	 * 
	 * @param Resource $resource
	 * @param boolean $force_update  (defaults to false)
	 * @return boolean
	 * @throws Exception on a failure to reach the vault
	 */
	function save($resource,$force_update=false)
	{
		
		// ok this WHOLE THING IS SHIT! but rather than rewrite it, i'll just wait till its obsolete with the new vault framwork.  couple of weeks probably. its currently 5/30/14
		if ($force_update)
		{
			myerror_log("We are starting the SplickitVault->save() with a FORCED UPDATE!");
		}	
		
		if (!$force_update && $this->saveCardFromResource($resource))
		{
			return true;
		}	
		else
		{
			if ($force_update || $error_data = $this->getReturnData())
			{
				if ($force_update || strtolower($error_data['identifier'][0]) == 'identifier already used' || strtolower($error_data['identifier'][0]) == 'is already taken')
				{
					// we need to do an update
					if (!$force_update)
					{
						myerror_log("USER ALREADY EXISTS IN VAULT! so lets do an update instead.  about to unset the error values in splickit vault");
						unset($error_data);
						unset($error);
					}	
					if ($this->updateCardFromResource($resource)) {
						return true;						
					} else {
						// check to see if it was a failure to update so try an insert
						if ($this->getReturnHttpCode() == "404") {
							if ($this->saveCardFromResource($resource)) {
								return true;
							}
						}
						if ($error = $this->getError())
						{
							myerror_log("There was an error with saving the card data: ".$error);
							return false;
							//throw new Exception($error, 180);
						}
					}											
				} else {
					$error = $this->getError();
					myerror_log("There was an error with the card data: ".$error);
					return false;
					//throw new Exception($error, 180);
				}
			}
		}
		$time_of_request_in_seconds = $this->curl_response_array['curl_info']['time_of_request_in_seconds'];
		myerror_log("Vault request time in seconds: ".$time_of_request_in_seconds);
		if ($error = $this->curl_response_array['error']) {
			myerror_log("Vault error: $error");
		} else if ($error = $this->curl_response_array['raw_result']) {
			myerror_log("Vault error: $error");
		}
		myerror_log("ERROR SAVING TO NEW VAULT! throw exception!");
		throw new Exception("ERROR TRYING TO REACH NEW VAULT FOR SAVE! time_of_request: $time_of_request_in_seconds , error: $error", 999);
	}
	
	/**
	 * 
	 * @desc will save the CC info contained in the submitted resource. returns true on success
	 * @param Resource $resource
	 * @return boolean
	 */
	
	function saveCardFromResource($resource)
	{
		$data = $resource->getDataFieldsReally();
		$data['first_name'] = str_replace(array('\'', '"'), '', $data['first_name']);
		$data['last_name'] = str_replace(array('\'', '"'), '', $data['last_name']);
		
		if ($this->saveCard($data))
		{
			$this->vault_save_process = 'insert';
			return true;
		}	
		return false;
	}

	/**
	 * @desc accepts card data returns true on success.  card data MUST contain the users UUID
	 * 
	 * @param array $card_data
	 * @return boolean
	 */
	
	function saveCard($card_data)
	{
		if ($card_data['uuid'])
			$card_data['identifier'] = $card_data['uuid'];
		else
		{
			myerror_log("NO UUID SUBMITTED WITH CARD DATA SAVE!");
			$this->setReturnHttpCode(422);
			return false;
		}
		$card_data['vaulted'] = 'true';
		myerror_logging(3,"Starting card INSERT in SplickitVault");
		if ($return = $this->curlIt("credit_cards", $card_data))
			return $this->processReturn($return);
		else
			return false;
	}
	
	/**
	 * 
	 * @desc will update the CC info contained in the submitted resource
	 * @param Resource $resource
	 * @return boolean
	 */
	
	function updateCardFromResource($resource)
	{
		$data = $resource->getDataFieldsReally();
		$data['first_name'] = str_replace(array('\'', '"'), '', $data['first_name']);
		$data['last_name'] = str_replace(array('\'', '"'), '', $data['last_name']);
		
		if ($this->updateCard($data))
		{
			$this->vault_save_process = 'update';
			return true;
		}	
		return false;
	}
	
	function updateCard($card_data)
	{
		myerror_logging(3,"Starting card UPDATE in SplickitVault");
		if ($card_data['uuid']) {
			$card_data['identifier'] = $card_data['uuid'];
		} else {
			myerror_log("NO UUID SUBMITTED WITH CARD DATA SAVE!");
			$this->setReturnHttpCode(422);
			return false;
		}
		$card_data['vaulted'] = 'true';
		if ($return = $this->curlIt("credit_cards/".$card_data['uuid'], $card_data, true)) {
			return $this->processReturn($return);
		} else {
			return false;
		}
	}
	
	function curlIt($endpoint,$card_data,$update = false)	
	{
		$the_data['end_point'] = $endpoint;
		$the_data['update'] = $update;
		if ($card_data) {
			$the_data['card_data'] = $card_data;
		}
		$response = SplickitVaultCurl::curlIt($endpoint, $the_data);
		$this->curl_response_array = $response;
		$this->setResponseStuff($response);
		
		if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($response, 'raw_result')) {
			$result = $response['raw_result'];
			if (isTest() || isLaptop()) {
				myerror_log("vault return: ".$result);
			}
			$return = json_decode($result,true);
			return $return;				
		} else {
			myerror_log("ERROR!  trying to reach Splickit Vault!  error: ".$response['error']);
			return false;
		}	
		
	}
	
	function setResponseStuff($response)
	{
		$this->setReturnHttpCode($response['http_code']);
		$this->error = $response['error'];
		$this->error_no = $response['error_no'];
	}
	
	function setReturnData($data)
	{
		$this->return_data = $data;
	}
	
	function getReturnData()
	{
		return $this->return_data;
	}
	
	function processReturn($return)
	{
		if ($return['status'] == '200 OK')
		{
			myerror_logging(3,"success with Vault process");
			myerror_log("********** processing splickit vault return **********");
			foreach ($return['data']['credit_card'] as $name=>$value)
			{
				if ($name == 'number') {
					myerror_log($name."=".substr($value, 0,1)."xxxxxxxxxxx".substr($value, -4));
				} else if ($name == 'cvv') {
					myerror_log("cvv=xxx");
				} else {
					myerror_log("$name=$value");
				}
			}	
			myerror_log("********** end splickit vault return **********");
			$this->return_status = $return['status'];
			$this->return_data = $return['data']['credit_card'];
			return true;
		}	
		else
		{
			myerror_log("WE HAVE AN ERROR processing the request!");
			$errors = $return['errors']['credit_card'];
			$error_string = '';
			foreach ($errors as $item=>$error)
			{
				myerror_log("the $item ".$error[0]);
				$error_string = $error_string.$item."=".$error[0]."  "; 
			}
			$this->error = $error_string;
			$this->return_status = $return['status'];
			$this->return_data = $return['errors']['credit_card'];
			return false;
		}
	}
	
	static function cleanCCdata($card_data)
	{
		if ($card_data['cc_number'])
			$card_data['number'] = $card_data['cc_number'];
		if ($card_data['cc_exp_date'])
		{
			$exp_date_string = $card_data['cc_exp_date'];
			$month = substr($exp_date_string, 0,2);
			$year = "20".substr($exp_date_string,-2);
			$card_data['month'] = $month;
			$card_data['year'] = $year;
		}
		// now use only the fields that we need to avoid storing other shit in the vault
		$clean_data = array();
		if ($card_data['identifier']) {
			$clean_data['identifier'] = $card_data['identifier'];
		} if ($card_data['first_name']) {
			$clean_data['first_name'] = $card_data['first_name'];
		} if ($card_data['last_name']) {
			$clean_data['last_name'] = $card_data['last_name'];
		} if ($card_data['number']) {
			$clean_data['number'] = $card_data['number'];
		} if ($card_data['cvv']) {
			$clean_data['cvv'] = (string) $card_data['cvv'];
		} if ($card_data['month']) {
			$clean_data['month'] = $card_data['month'];
		} if ($card_data['year']) {
			$clean_data['year'] = $card_data['year'];
		} if ($card_data['zip']) {
			$clean_data['zip'] = $card_data['zip'];
		} if ($card_data['vaulted']) {
			$clean_data['vaulted'] = $card_data['vaulted'];
		}
		return $clean_data;
	}
	
	// needed on 32 bit servers becuase they cant handle cc number as a string
	static function convertCCNumberInJsonToNumber($json)
	{
		$data = json_decode($json,true);
		$json = str_replace('"'.$data['number'].'"', $data['number'], $json);
		return $json;
	}

	function setReturnStatus($status)
	{
		$this->return_status = $status;
	}
	
	function getReturnStatus()
	{
		return $this->return_status;
	}
	
	function setReturnHttpCode($code)
	{
		$this->return_http_code = $code;
	}
	
	function getReturnHttpCode()
	{
		return $this->return_http_code;
	}
	
	function getError()
	{
		return $this->error;
	}
	
	function getErrorNo()
	{
		return $this->error_no;
	}
	
}