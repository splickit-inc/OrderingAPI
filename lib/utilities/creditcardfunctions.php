<?php

class CreditCardFunctions
{
	protected $status;
	protected $refund_error;
	var $void = false;
	var $splickit_vault_save_process;
	var $process;

	/**
	 * 
	 * @desc returns the correct CC functions object based on the CHAR that represents it.  I,M,F
	 * @param Char $processor
	 * 
	 * @return CreditCardFunctions
	 */
	static function creditCardFunctionsFactory($processor)
	{
		myerror_log("using processor: $processor");
		$backwards_compatable_hash_of_processors = array("m"=>'mercurypay',"i"=>"inspirepay","f"=>"fpnpay");
		$processor = strtolower($processor);
		$processor = isset($backwards_compatable_hash_of_processors[$processor]) ? $backwards_compatable_hash_of_processors[$processor] : $processor;
		if ($processor == 'mercurypay') {
			return new MercuryCreditCardFunctions();
		} else if ($processor == 'inspirepay') {
			return new InspireCreditCardFunctions();
		} else if ($processor == 'fpnpay') {
			return new FranchiseCreditCardFunctions();
        } else if ($processor == strtolower(LevelupPaymentService::SERVICE_NAME)) {
            return new LevelupPaymentService($data);
        } else if ($payment_service = new VioPaymentService(array("billing_entity_external_id"=>$processor))) {
            return $payment_service;
        } else {
			myerror_log("no matching CC provider in credit card functions factory: ".$processor);
			MailIt::sendErrorEmail("UNRECOGNIZED CC PROCESSOR ($processor)","UNRECOGNIZED CC PROCESSOR ($processor), in cc function factory ".getRawStamp());
		}
	}
	
	/**
	 * 
	 * @desc will bill a user or a submitted card data
	 * @param unknown_type $amount_to_run_card_for
	 * @param unknown_type $billing_user_resource
	 * @param unknown_type $neworder_id
	 * @param unknown_type $merchant
	 * @param unknown_type $card_data
	 */
	
	function cardProcessor($amount_to_run_card_for,$billing_user_resource,$neworder_id,$merchant,$card_data)
	{
		$merchant_processor = $merchant['cc_processor'];
		$billing_user_id = $billing_user_resource->user_id;
		if ($amount_to_run_card_for == 0)
		{
			myerror_log("ERROR!  cannot have a zero amount to run card for. creditcardfunction.php");
			return false;
		}	
		else if ($amount_to_run_card_for < 0)
		{
			myerror_log("ERROR!  cannot have a negative value for running CC card. creditcardfunction.php");
			return false;
		}	
		else if ($billing_user_id < 2)
		{
			myerror_log("ERROR!  NO billing user id!");
			return false;
		}
		else if ($neworder_id < 1000)
		{
			myerror_log("ERROR!  Bad order id: ".$neworder_id);
			return false;
		}
		else if ($billing_user_id < 200)
		{
			$return_fields['response_code'] = 100;
			return $return_fields;
		}	
			
		$inspireObj = new InspirePay($merchant['brand_id']);
		$splickit_vault = new SplickitVault();
		myerror_log("user flags are set as: ".$billing_user_resource->flags);
		if ($card_data)
		{ 
			myerror_log("card data has been submitted with the order");
		}				
		else if (substr($billing_user_resource->flags,1,2) == 'C2')
		{
			//this user is stored in the new vault
			myerror_logging(3, "user data is stored in the new vault");
			if ($card_data = $splickit_vault->getCardInfoOnly($billing_user_resource->uuid)) {
				$clean_card_no = 'xxxxxxxxxxxx'.substr($card_data['cc_number'], -4);
			} else {
				if (strtolower($splickit_vault->getError()) == 'timeout' || $splickit_vault->getErrorNo() == 28) {
					$return_fields['response_code'] = 999;
					$return_fields['responsetext'] = 'timeout';
				} else {
					myerror_log("ERROR! Unable to get card info out of splickit vault, appears to not exist");
					$return_fields['response_code'] = 120;
					$return_fields['responsetext'] = 'invalid customer vault id';
				}
				return $return_fields;
			}
		}/* else {
			myerror_log("NO CC information to run card against");
			return false;
		}*/
		$card_data = $this->cleanCardData($card_data);
		
		$time1 = time();
		if (isTest() && $billing_user_id == 121236)
		{
			$return_fields['response_code'] = 999;
			$return_fields['responsetext'] = 'Forced failure for testing';
		}
		else if ($merchant_processor == 'M') // && $user_id == 124890 && $merchant_id == 102237) //&& ($_SERVER['HTTP_HOST'] == 'test.splickit.com' || $_SERVER['HTTP_HOST'] == 'localhost'))
		{
			$processor = "mercurypay";
			myerror_log("IN THE M PROCESSOR");
			$mercury_payment = new MercuryPayments();
			$mercury_payment->setFileAdapter(getFileAdapter());
			//Resource::encodeResourceIntoTonicFormat($userresource);
			//if (substr($billing_user_resource->flags,1,2) == 'C0')
			if (! $card_data)
			{
				// no card_data so check IP vault for the data.
				$card_data = $inspireObj->getCustomerVaultRecordReally($billing_user_id);
				$clean_card_no = $card_data['cc_number'];
				if (isProd())
				{
					$return_data2 = $inspireObj->getCustomerVaultRecordCCReally($billing_user_id);
					$card_data['cc_number'] = $return_data2['cc_number'];
				} else {
					myerror_log("setting default CC number since we are NOT in prod");
					$card_data['cc_number'] = "4111111111111111";
				}
				if (substr($billing_user_resource->flags,1,2) == 'C0')
				{
					// card_data needs to be saved in the new vault
					if ($splickit_vault->saveCardFromInspirePayReturnAndUserResource($card_data, $billing_user_resource))
						myerror_log("succesfull insert of card data in new vault from Inspire Pay data");
					else 
					{
						$error = $splickit_vault->getError();
						myerror_log("ERROR!  unable to save card info in new vault: ".$error);
						if ($error == 'year=expired')
						{
							$return_fields['response_code'] == 223;
							$return_fields['responsetext'] == 'Expired card';
						}
						else
						{
							MailIt::sendErrorEmail("ERROR!  Unable to save card info in new vault!", "Unrecognized error thrown trying insert card data in vault from data pulled from Inspire Pay: ".$error);
							$return_fields['response_code'] == 190;
							$return_fields['responsetext'] == $error;
						}
						return $return_fields;
					}
				} else {
					myerror_log("Do NOT try and save after inspire pay pull, we were unable to get CC data out of splickit vault due to a connection problem so went to back up");
				}				 
			}
			$lastfour = substr($card_data['cc_number'], -4);
			$mercury_payment->setLastFour($lastfour);
			$mercury_payment->setCleanCardNo($clean_card_no);
			
			myerror_log("amount to run card for: ".$amount_to_run_card_for);
			$card_data['charge_amt'] = ''.$amount_to_run_card_for;
			$card_data['order_id'] = ''.$neworder_id;
			$card_data['merchant_id'] = ''.$merchant['merchant_id'];
			$card_data['tran_code'] = 'Sale';
			$card_data['ref_no'] = '1';
			$this->card_data = $card_data;
			$return_fields = $mercury_payment->runcard($card_data);
		} else if ($merchant_processor == 'I') {
			myerror_log("about to run card with inspire pay");
			$processor = "inspirepay";
			if ($card_data)
				$inspireObj->runCardNoVault($card_data, $amount_to_run_card_for, $neworder_id);
			else
				$inspireObj->runCard($billing_user_id,$amount_to_run_card_for,$neworder_id);
			$return_fields = $inspireObj->getReturnData();
		} else if ($merchant_processor == 'F') {
			myerror_log("about to run card with usaepay");
			$processor = "fpnpay";
			$fz_payments = new FranchisePayments($merchant['merchant_id']);
			$return_fields = $fz_payments->process($amount_to_run_card_for, $billing_user_resource, $neworder_id, $merchant, $card_data);
		} else if ($merchant['brand_id'] == 326) {
			myerror_log("in the JERSEY MIKES!  We should never be here");
			if (isLaptop())
				$return_fields = $inspireObj->getDummyReturnData($neworder_id);
		} else {
			myerror_log("in the ELSE!  THERE IS NO CC processor listed for this merchant!");
			MailIt::sendErrorEmailSupport("ERROR! No CC processor listed for merchant!", "There is no cc processor listed for this merchant_id: ".$merchant['merchant_id']);
			return false;
		}
		$time2 = time();
		$diff = $time2-$time1;
		if ($diff > 10) {
			myerror_log("LONG REquest time to process credit cards with $processor");
			recordError("CreditCard DELAY", "please be aware that card processing time is $diff seconds, with processor: $processor");
		}
		$return_fields['processor_used'] = $processor;
		return $return_fields;		
	}

	function voidRefundTransactionFromBalanceChangeRow($balance_change_row,$refund_amounmt)
	{
		$cc_processor = $balance_change_row['hjk'];
		
	}

    /**
     * @param $balance_change_row
     * @param $refund_amt
     * @param $merchant_resource
     *
     * @return array()
     */
    function creditVoidTransaction($balance_change_row, $refund_amt, $merchant_resource) { }
	
	/**
	 * 
	 * @deprecated
	 * 
	 * @param $cc_processor
	 * @param $amount_to_run_card_for
	 * @param $transaction_id
	 * @param $authcode
	 * @param $card_data
	 */
	function voidTransaction($cc_processor,$amount_to_run_card_for,$transaction_id,$authcode,$card_data)
	{

		if ($cc_processor == 'M')
		{
			$mercury_payment = new MercuryPayments();
			$card_data = $this->card_data;
			$card_data['tran_code'] = 'VoidSale';
			$card_data['charge_amt'] = ''.$amount_to_run_card_for;
			$card_data['ref_no'] = $transaction_id;
			$card_data['auth_code'] = $authcode;
			$return_fields = $mercury_payment->runcard($card_data);
		} else if ($cc_processor == 'I') {
			$inspire_pay = new InspirePay();
			$inspire_pay->void($transaction_id);
		} else if ($cc_processor == 'F') {
			$fz_payments = new FranchisePayments();
			$return_fields = $fz_payments->creditVoid($amount_to_run_card_for,$transaction_id);
			
		}
		else
		{
			MailIt::sendErrorEmail("UNRECOGNIZED CC PROCESSOR DURING REFUND", "order_id : ".$neworder_id);
		}

	}
	
	function cleanCardData($card_data)
	{
		if (isset($card_data['cc_exp']))
			return $card_data;
			
		foreach ($card_data as $name=>$value)
		{	
			if (substr_count($name, "exp") == 1)
			{			
				$exp_string = $card_data[$name];
				if (strlen($exp_string) == 3)
					$card_data['cc_exp'] = '0'.$exp_string;
				else
					$card_data['cc_exp'] = substr($exp_string, 0,2).substr($exp_string,-2);
				unset($card_data[$name]);
				return $card_data;
			}
		}
		
	}
	
	function formatExpDateTo4NumbersBetter($expdate)
	{
		$exp_date = (string) $expdate;
		$exp_date = str_replace('/', '', $exp_date);
		if (strlen($exp_date) == 3) {
			$exp_date = '0'.$exp_date;
		} else if (strlen($exp_date) == 6) {
			$exp_date = substr($exp_date, 0, 2).substr($exp_date,-2);
		}
		//not completed yet
	}

	function formatExpDateTo4Numbers($expdate)
	{
		$exp_date = (string) $expdate;
		$date_array = explode("/", $exp_date);	
		if (sizeof($date_array, $mode) > 1)
		{
			$month = $date_array[0];
			$year = $date_array[1];
			if (strlen($month) < 2) {
				$month = "0".$month;
			}
			$formatted_exp_date = $month."".substr($year, -2);
			return $formatted_exp_date;
		} else {
			if (strlen($exp_date) == 3) {
				return "0".$exp_date;
			} else if (strlen($exp_date) == 4) {
				return $exp_date;
			} else if (strlen($exp_date) == 6) {
				return substr($exp_date, 0, 2).substr($exp_date,-2);
			} else {
				myerror_log("ERROR! recieved an unrecognizable date format in CC function");
				//MailIt::sendErrorEmail("ERROR! recieved an unrecognizable date format in CC function", "recieved format: ".$exp_date);
				return false;
			}
		}
	}

	/**
	 * 
	 * @desc takes in a resouce of a user with CC data attached.
	 * @param Resource $resource
	 * @return boolean
	 */
	
	function cc_save(&$resource)
	{
			$splickit_vault = new SplickitVault();
   			myerror_log("In the cc_save function of CreditCardFunctions");
			$flags_in = $resource->flags;
			$account_hash_in = $resource->account_hash;
			if ($resource->cvv) {
				$resource->cvv = trim($resource->cvv);
				$cvv = (string) $resource->cvv;
			}
			
			if (!($resource->zip)) {
				return setErrorOnResourceReturnFalse(setHttpCodeOnResource($resource,422), 'Credit Card save error, zip cannot be blank.', 110);
			} else if (!($resource->cc_exp_date)) {
				return setErrorOnResourceReturnFalse(setHttpCodeOnResource($resource,422), 'Credit Card save error, expiration date cannot be blank.', 120);
			} else if (!($resource->cvv)) {
				return setErrorOnResourceReturnFalse(setHttpCodeOnResource($resource,422), 'Credit Card save error, CVV cannot be blank.', 130);
			} else if (! preg_match('/^([0-9]{3,4})$/', $cvv)) {
				return setErrorOnResourceReturnFalse(setHttpCodeOnResource($resource,422), 'Credit Card save error, CVV must be 3 or 4 digits only.', 130);
			} else {
				//expiration date hack
				if ($this->processAndSetExpirationDateOnResourceIfItsValidSetErrorOnResourceIfItsNot($resource) === false) {
					return false;
				}
				
				myerror_logging(4,"all is good lets update the CC");
				$last_four = substr($resource->cc_number, -4);
				
				// check if user exists in the new vault already
				$force_update = false;
				if (substr($resource->flags, 2,1) == 2)
					$force_update = true;

				try {
					if ($splickit_vault->save($resource,$force_update))
					{
						myerror_logging(3,"All is good with the save to the new vault");
						$resource->flags = '1C21'.substr($flags_in,4,7);
					} else {
						$error = $splickit_vault->getError();
						if (substr_count($error, 'is not a valid credit card number'))
							$error = "CC number is not valid";
						$resource->set('error','Error saving credit card info: '.$error);
						$resource->set('error_code',140);
						return false;
					}
				} catch (Exception $e) {
					$message = "VAULT EXCEPTION!  ".$e->getMessage();
					myerror_log($message);
					// trim message down
					if (strlen($message) > 280) {
						$message = substr($message, 0,279);
					}
					SmsSender2::sendEngineeringAlert($message);
					SmsSender2::sendVaultAlert($message);
					recordError("Vault Save Error", $e->getMessage());
					$resource->set('error',"We're sorry, but we did not reach the credit card storage facility, please try again.");
					$resource->set('error_code',140);
					return false;
					 					
				}
				$this->splickit_vault_save_process = $splickit_vault->vault_save_process;
				$this->status = $splickit_vault->getReturnStatus();
				
				$resource->account_hash = md5(''.$resource->zip.''.substr($resource->cc_number,-4).''.$resource->cc_exp_date);
				$resource->last_four = $last_four;
				$resource->set('user_message_title','Credit Card Response');
				$resource->set('user_message','Your credit card info has been securely stored.');
				if (!CreditCardUpdateTrackingAdapter::recordCreditCardUpdateAndCheckForBlacklisting($resource->user_id,$resource->device_id,substr($resource->cc_number,-4))) {
					$resource->set('error','Error saving credit card info.');
					$resource->set('error_code',999);
					return false;
				}
				return true;
			}
	}
	
	function processAndSetExpirationDateOnResourceIfItsValidSetErrorOnResourceIfItsNot(&$resource)
	{
		if ($formatted_exp_date = $this->formatExpDateTo4Numbers($resource->cc_exp_date)) {
			if (checkdate(substr($formatted_exp_date, 0, 2), 1, substr($formatted_exp_date,-2))) {
				$date_string = "".substr($formatted_exp_date, 0, 2)."/1/20".substr($formatted_exp_date,-2)." 13:00:00";
				if (time() > strtotime($date_string)) {
					return setErrorOnResourceReturnFalse($resource, 'Credit Card save error, expired expiration date: '.$formatted_exp_date, 120);
				}
			} else {
				return setErrorOnResourceReturnFalse($resource, 'Credit Card save error, invalid expiration date: '.$formatted_exp_date, 120);
			}
		} else {
			return setErrorOnResourceReturnFalse($resource, 'Credit Card save error, invalid expiration date: '.$resource->cc_exp_date, 120);
		}
		//all tests pass so set the formatted expiration date
		$resource->cc_exp_date = $formatted_exp_date;
	}
			
	function doUpdateProcess($resource)
	{
		if ($result = $splickit_vault->updateCardFromResource($resource))
		{
			// and now do the backup
			myerror_log("about to do backup UPDATE to IP vault untill we have confidence with splickit vault");
			$inspireObj->updateCustomerVaultRecord($resource->user_id,$resource->cc_number,$resource->cvv,$resource->cc_exp_date,$resource->zip);
		} else {
			// do NOT update inspire pay since then we will have currupted data if IP passes but SV did not.
			myerror_log("ERROR! We had a failure updating the card in the splickit vault.");
			// the error will be trapped below.
		}		
	}
	
	function pullCreditCardDataFromUserId($user_id)
	{
		$user_adapter = new UserAdapter($mimetypes);
		if ($user_resource = Resource::find($user_adapter,''.$user_id)) {
			return $this->pullCreditCardData($user_resource);
		} else {
			return false;
		}
		
	}
	
	function pullCreditCardData(&$user_resource)
	{
		$flags_in = $user_resource->flags;
		$splickit_vault = new SplickitVault();
		if (substr($user_resource->flags,1,1) == '0')
		{
			myerror_log("ERROR! attempt to pull CC data with no C value in user flags");
			return false;
		}
		else if (substr($user_resource->flags,1,2) == 'C2')
		{
			//this user is stored in the new vault
			if ($card_data = $splickit_vault->getCardInfoOnly($user_resource->uuid))
			{
				$clean_card_no = substr($card_data['cc_number'],0,1).'xxxxxxxxxxx'.substr($card_data['cc_number'], -4);
				myerror_logging(3,"we have retrieved the card number from the splickit vault: ".$clean_card_no);
				return $card_data;
			}
		}
		
		// if we get here we need to get the nubmer from the IP vault
		$inspireObj = new InspirePay();
		if ($card_data = $inspireObj->getCustomerVaultRecordReally($user_resource->user_id))
		{		
			if (isProd())
			{
				$return_data2 = $inspireObj->getCustomerVaultRecordCCReally($user_resource->user_id);
				$card_data['cc_number'] = $return_data2['cc_number'];
				$clean_card_no = substr($card_data['cc_number'],0,1).'xxxxxxxxxxx'.substr($card_data['cc_number'], -4);
				$first_four = substr($return_data2['cc_number'], 0,4);
				myerror_log("we retrieved the card number from inspire pay: ".$clean_card_no);
			} else {
				//$cc_number_For_test = "4242424242424242";
				$cc_number_for_test = "4111111111111111";
				myerror_log("WE ARE NOT IN PROD so do not get full CC number.  setting to test value of $cc_number_For_test");
				$card_data['cc_number'] = "$cc_number_For_test";
			}
			// make sure card doesn't exist in splickit vault before trying to save it.
			if (substr($user_resource->flags,1,2) != 'C2')
			{	
				if ($splickit_vault->saveCardFromInspirePayReturnAndUserResource($card_data, $user_resource))
				{
					myerror_log("succesfull insert of card data in new vault from Inspire Pay pull");
					$user_resource->flags = '1C2'.substr($flags_in,3,8);
					$user_resource->save();
							
				}	
				else 
				{
					$error = $splickit_vault->getError();
					if (trim($error) == "year=expired")
					{
						myerror_log("Card expired, cannot save to new vault. Reset flags so user will need to enter value next time");
						$user_resource->flags = '100'.substr($flags_in,3,8);
						$user_resource->save();
					}
					else
					{
						myerror_log("ERROR!  unable to save card info in new vault after pulling from inspire pay: ".$error);
						if (isProd())
							MailIt::sendErrorEmail("ERROR!  Unable to save card info in new vault!", "error thrown trying insert card data in vault from data pulled from Inspire Pay: ".$error);
						else
							MailIt::sendErrorEmailAdam("ERROR!  Unable to save card info in new vault TEST!", "error thrown trying insert card data in vault from data pulled from Inspire Pay: ".$error);
					}
				}				 
			}
			return $card_data;
		}
		return false;

	}
	
	function getStatus()
	{
		return $this->status;
	}
	
	function getCleanCardNumber($card_data)
	{
		$clean_card_no = substr($card_data['cc_number'],0,1).'xxxxxxxxxxx'.substr($card_data['cc_number'], -4);
		return $clean_card_no;
	}
	
	function getLastFour($card_data)
	{
		$last_four = substr($card_data['cc_number'], -4);
		return $last_four;
	}
	
	function getSplickitVaultSaveProcess()
	{
		
	}
}