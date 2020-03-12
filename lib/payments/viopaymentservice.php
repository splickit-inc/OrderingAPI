<?php
class VioPaymentService extends SplickitPaymentService
{
    var $url;
	var $username_password;
	var $balance_change_resource_for_cc_payment;
	var $process;
    var $response_in_data_format;
	var $batch_errors = array();
    var $vio_response_message;
	private $force_purchase = false;
    private $send_url;
    private $user_vault_id;
    private $vio_payment_identifier;


	/*
	 * the default message when we cant get it out of the retunr data in a way we understand
	 */
	var $error_processing_payment_message = "We're sorry but there was an unrecognized error running your credit card and the charge did not go through.";
	
	var $failed_cvv_message = "Sorry, your credit card's security code has either expired or is incorrect, please re-enter your card information.";

    var $curl_error_user_message = "We're sorry but there was an connection problem reaching the credit card processing facility and your order did not go through. Please try again.";

    static function getVioWriteCredentials()
    {
        return getProperty('vio_write_username_password');
    }
	/**
	 * 
	 * @desc need to pass in the following parameters in th data array "billing_entity_external_id" OR "billing_entitiy_id,"uuid"
	 * @param mixed $data
	 */
	function __construct($data)
	{
		parent::__construct($data);
		myerror_log("creating viopaymentservice");
    	$this->send_url = getProperty('vio_url');
		myerror_log("we have obtained the vio_url: ".$this->send_url);
    	if ($vio_payment_identifier = $data['billing_entity_external_id']) {
			if ($map = BillingEntitiesAdapter::getBillingEntityByExternalId($vio_payment_identifier)) {
				$this->vio_payment_identifier = $vio_payment_identifier;
				$this->billing_entity_record = $map;
			} else {
				throw new NoSuchBillingEntityException();
			}
		} else if ($billing_entity_id = $data['billing_entity_id']) {
			$this->setBillingEntityAndIdentifierFromBillingEntityId($billing_entity_id);
		} else if ($merchant_id = $data['merchant_id']) {
			$this->setBillingEntityAndIdentifierFromMerchantId($merchant_id);
		}
		if ($user_id = $data['user_id']) {
			$this->setBillingUserIdAndResourceFromUserId($user_id);
		}
		if ($order_id = $data['order_id']) {
			$this->order_id = $order_id;
		}
	}

	private function setBillingEntityAndIdentifierFromMerchantId($merchant_id)
	{
		if ($mptm = MerchantPaymentTypeMapsAdapter::staticGetRecord(array("merchant_id"=>$merchant_id,"splickit_accepted_payment_type_id"=>2000),'MerchantPaymentTypeMapsAdapter')) {
			if ($billing_entity_id = $mptm['billing_entity_id']) {
				return $this->setBillingEntityAndIdentifierFromBillingEntityId($billing_entity_id);
			}
		}
		throw new BillingException("Merchant is not a Credit Card merchant");
	}

	private function setBillingEntityAndIdentifierFromBillingEntityId($billing_entity_id)
	{
		if ($map = BillingEntitiesAdapter::staticGetRecordByPrimaryKey($billing_entity_id, 'BillingEntitiesAdapter')) {
			$this->vio_payment_identifier = $map['external_id'];
			$this->billing_entity_record = $map;
		} else {
			throw new NoSuchBillingEntityException();
		}
	}

	private function addDummyAddressFieldsToCardData(&$card_data)
	{
		$card_data['city'] = 'Boulder';
		$card_data['state'] = 'CO';
		$card_data['address1'] = '1305 Pearl Street';
		$card_data['country'] = 'USA';
	}
	
	/**
	 * 
	 * @desc will save a CC in the vault must be in the form:  {"brand": "visa", "number":"4242424242424242","cvv":"123","month":4,"year":2015,"first_name":"Ara","last_name":"Howard"}
	 * @param unknown_type $card_data
	 */
	function saveCreditCard($card_data)
	{
		$this->setWrite();
		$card_data['vaulted'] = "true";
		$this->addDummyAddressFieldsToCardData($card_data);
		$url = $this->send_url.'credit_cards';
		$data['credit_card'] = $card_data;
		$response = $this->curlIt($url, $data);
		return $response;
	}

    function updateExistingRecordWithNewUUID($identifier,$uuid)
    {
        $this->setAdmin();
        $card_data['credit_card']['identifier'] = $uuid;
        $card_data['action'] = 'put';
        $url = $this->send_url.'credit_cards/'.$identifier;
        $response = $this->curlIt($url,$card_data);
        return $response;
    }

	function getBalanceChangeAdapter()
	{

		if ($this->balance_change_adapter) {
			return $this->balance_change_adapter;
		} else {
			$this->balance_change_adapter = new BalanceChangeAdapter($m);
			return $this->balance_change_adapter;
		}
	}

	function getAllPendingBalanceChangeResourcesByBrand($brand_id)
	{

		$options[TONIC_FIND_BY_METADATA] = array("process"=>"Authorize","notes"=>"PENDING");
		$options[TONIC_JOIN_STATEMENT] = " JOIN Billing_Entities ON Balance_Change.cc_processor = Billing_Entities.external_id ";
		$options[TONIC_FIND_BY_STATIC_METADATA] = " Billing_Entities.brand_id = $brand_id ";
		$bc_resources = Resource::findAll($this->getBalanceChangeAdapter(),'',$options);
		return $bc_resources;
	}

	function executeCapturesFromActivityForBrandId($brand_id)
	{
		$brand = BrandAdapter::staticGetRecordByPrimaryKey($brand_id,'Brand');
		$results = $this->processOrderCaptureOfAllPendingAuthorizationForBrand($brand_id);
		$email_subject = "Process capture for brand: ".$brand['brand_name'];
		$email_body = "There were ".$results['number_of_successful_captures']." successful captures\r\n";
		$email_body = $email_body."There were ".$results['number_of_failed_captures']." failed captures\r\n";
		if (count($this->batch_errors) > 0 ) {
			$email_body = $email_body."\r\nThese errors were thrown:\r\n\r\n";
			foreach ($this->batch_errors as $error_text) {
				$email_body = $email_body."$error_text \r\n";
			}
			$email_body = $email_body."\r\n\r\n";
			$email_body = $email_body."Use this url from within the VPN to rerun a specific capture\r\n\r\n   https://pweb.splickit.com/app2/admin/captureauthorizedpayment?order_id=<order_id> \r\n\r\n";
		}
		MailIt::sendErrorEmailSupport($email_subject,$email_body);

	}

	function processOrderCaptureOfAllPendingAuthorizationForBrand($brand_id)
	{
		$batch_capture_results = array("number_of_successful_captures"=>0,"number_of_failed_captures"=>0);
		$order_adapter = new OrderAdapter($m);
		$merchant_adapter = new MerchantAdapter($m);
		$user_adatper = new UserAdapter($m);
		if ($pending_balance_change_resources = $this->getAllPendingBalanceChangeResourcesByBrand($brand_id)) {
            $processed_order_amounts = array();
			foreach ($pending_balance_change_resources as $authorization_resource) {
			    $order_amount_string = $authorization_resource->order_id.'-'.$authorization_resource->charge_amt;
			    if (isset($processed_order_amounts[$order_amount_string])) {
			        myerror_log("WE HAVE A DUPLICATE AUTHORIZATION!!!!!");
                    $authorization_resource->notes = 'duplicate_cancelled';
                    $authorization_resource->save();
                    MailIt::sendErrorEmailSupport("Duplicate Authorization Identified","We have an order with a double auth. second auth has been set to cancelled. order_id: ".$authorization_resource->order_id);
                    MailIt::sendErrorEmailAdam("Duplicate Authorization Identified","We have an order with a double auth. second auth has been set to cancelled. order_id: ".$authorization_resource->order_id);
                    continue;
                }
				$order = $order_adapter->getRecord(array("order_id"=>$authorization_resource->order_id));
				if (isARegularUser($authorization_resource->user_id)) {
					if ($order_adapter->isStatusReadyForBilling($order['status'])) {
					    $authorization_resource = $authorization_resource->getRefreshedResource();
					    if (strtolower($authorization_resource->notes) == 'pending') {
                            $results = $this->capture($authorization_resource->charge_amt, $authorization_resource->cc_transaction_id);
                            $results['process_as'] = "captured";
                        } else {
					        myerror_log("VIO Payment: CAPTURED AUTH after start of batch process. order_id: ".$authorization_resource->order_id);
					        myerror_log("Probably a tip capture that got executed after the start of the batch capture");
					        continue;
                        }
                    } else if ($order['status'] == 'G') {
					    myerror_log("we have a group order with a auth row. Skip it for now. order_id: ".$order['order_id']);
					    continue;
					} else {
					    myerror_log("Order is Not in a ready state for billing. order_id: ".$order['order_id']."   status: ".$order['status']);
						$this->setCaptureOfAuthorizationBalanceChangeResource($authorization_resource,"cancelled");
						$results = array("status"=>'failed',"error"=>"Order NOT submited");
					}
				} else {
					$this->setCaptureOfAuthorizationBalanceChangeResource($authorization_resource,"test");
					continue;
				}

				if ($results['status'] == 'success') {
					if (! $this->getBalanceChangeAdapter()->addCCRow($authorization_resource->user_id, 0.00, $authorization_resource->charge_amt, 0.00, $authorization_resource->cc_processor, $authorization_resource->order_id, $results['payment']['_id'], 'authcode='.$results['payment']['authcode'])) {
						$this->batch_errors[] = "COULD NOT CCpayment row on captured authorizion for order_id: ".$authorization_resource->order_id;
					}
					$this->setCaptureOfAuthorizationBalanceChangeResource($authorization_resource);
					$batch_capture_results["number_of_successful_captures"]++;
                    $processed_order_amounts[$order_amount_string] = 1;
				} else {
					$error_message = $this->getCaptureErrorFromResultsArray($results);
					$merchant = $merchant_adapter->getRecord(array("merchant_id"=>$order['merchant_id']));
					$user = $user_adatper->getRecord(array("user_id"=>$order['user_id']));
					$error_message = $error_message." order_id: ".$authorization_resource->order_id." -- ";
					$error_message = $error_message." merchant_external: ".$merchant['merchant_external_id']." -- ";
					$error_message = $error_message." order_date: ".$order['order_dt_tm']." -- ";
					$error_message = $error_message." order_amt: ".$order['grand_total']." -- ";
					$error_message = $error_message." user: ".$user['first_name']." ".$user['last_name'];


					$this->batch_errors[] = $error_message;
					$batch_capture_results["number_of_failed_captures"]++;
				}
			}
		}
		return $batch_capture_results;
	}

	function createTimeoutRecordInBalanceChange($existing_authorize_resource)
    {
        $bca = new BalanceChangeAdapter(getM());
        $timeout_bca_resource = $bca->addRow($existing_authorize_resource->user_id,0.00,0.00,0.00,'ChargeModification','',$existing_authorize_resource->order_id,getStamp(),'TIMEOUT');
    }


	function setCaptureOfAuthorizationBalanceChangeResource(&$authorization_resource,$value = 'captured')
	{
		$authorization_resource->notes = $value;
		$authorization_resource->save();
	}

	function processOrderCaptureOfPreviousAuthorization(&$order_resource,$billing_amount)
	{
		$this->setAmount($billing_amount);
		$this->setBillingUserIdAndResourceFromUserId($order_resource->user_id);
		$this->setOrderId($order_resource->order_id);
		$authorization_resource = $this->getAuthorizationResourceFromBalanceChange($order_resource->order_id);
		$this->payment_results = $this->capture($billing_amount,$authorization_resource->cc_transaction_id);
		if ($this->payment_results['status'] == 'success') {
			$bc_resource = $this->getBalanceChangeAdapter()->addCCRow($order_resource->user_id, -$order_resource->grand_total, $billing_amount, 0.00, $authorization_resource->cc_processor, $order_resource->order_id, $this->payment_results['payment']['_id'], 'authcode=' . $this->payment_results['payment']['authcode']);
			$this->setCaptureOfAuthorizationBalanceChangeResource($authorization_resource);
			return $this->createSuccessfullPaymentResponseHash();
		} else if ($this->curl_response['http_code'] == 504) {
			myerror_log("we had a TIME out reaching the cc processing falicity. set Capture to TIMEOUT");
			//$this->setCaptureOfAuthorizationBalanceChangeResource($authorization_resource,"TIMEOUT");
			$this->createTimeoutRecordInBalanceChange($authorization_resource);
			$this->payment_results['errors']['payment']['gateway_error']['message'] = "We had a Gateway Time-out trying to reach the CC processing facility for a capture.";
		}
		return $this->processCaptureError($this->payment_results);
	}


	private function processCaptureError($payment_results)
	{
		$error_message = $this->getCaptureErrorFromResultsArray($payment_results);
		$this->exception = true;
		throw new CaptureErrorException($error_message);
	}

	private function getCaptureErrorFromResultsArray($payment_results)
	{
		if (isset($payment_results['errors']['payment']['gateway_error']['message'])) {
			return $payment_results['errors']['payment']['gateway_error']['message'];
        } else if (isset($payment_results['error_message'])) {
            return $payment_results['error_message'];
        } else if (isset($payment_results['payment']['response']['exception'])) {
            $this->exception = true;
		    return $payment_results['payment']['response']['exception'];
        } else {
			return "there was an unknown error trying to capture the authorized charge.";
		}
	}


	function getAuthorizationResourceFromBalanceChange($order_id)
	{
		$options[TONIC_FIND_BY_METADATA] = array("order_id"=>$order_id,"process"=>"Authorize","notes"=>"PENDING");
		return Resource::findExact($this->getBalanceChangeAdapter(),null,$options);
	}
	
	/**
	 *
	 * @desc will add tip at a later date to a captured order
	 * @param Resource $order_resource
	 * @return Hashmap
	 */
	function processAddTipPayment(&$order_resource,$billing_amount)
	{
	    $this->setForcePurchase();
		$this->setAmount($billing_amount);
		$this->setBillingUserIdAndResourceFromUserId($order_resource->user_id);
		$this->setOrderId($order_resource->order_id);
		$this->payment_results = $this->processPayment($billing_amount);
        $this->unSetForcePurchase();
		if ($this->payment_results['response_code'] == 100) {
			$balance_change_adapter = new BalanceChangeAdapter($m);
			$bc_resource = $balance_change_adapter->addCCRow($this->billing_user_id, 0, $billing_amount, 0, $this->payment_results['processor_used'], $order_resource->order_id, $this->payment_results['transactionid'], 'authcode='.$this->payment_results['authcode']);
			return $this->createSuccessfullPaymentResponseHash();
		} else {
			return $this->processPaymentError($this->payment_results);
		}
	}
	
	function processPayment($amount)
	{
		if ($amount) {
			$this->amount = $amount;
		}
		$vio_payment_results = $this->processPayment2($this->vio_payment_identifier, $this->billing_user_resource->uuid);
		return $this->processVioResults($vio_payment_results);
	}
	
	function processVioResults($vio_payment_results)
	{
		// if successfull set the transaction_id,processor_used,authcode
		if ($vio_payment_results['status'] == 'success') {
			// right here is where we would check cvv validation if necessary
			if ($this->isItAValidCvvResponseForBillingUserState($vio_payment_results)) {
				return $this->formatSuccessForBackwardsCompatabilty($vio_payment_results);
			} else {
				$this->recordChargeAndThenVoidIt($vio_payment_results);
				$vio_payment_results['status'] = 'failure';
				$vio_payment_results['message'] = $this->failed_cvv_message;
				$vio_payment_results['response_text'] = $this->failed_cvv_message;
				$vio_payment_results['response_code'] = 999;
                //die("line 296 viopaymentservice");
				return $vio_payment_results;
			}
		} else {
			return $this->processVioError($vio_payment_results);
		}
	}
	
	function formatSuccessForBackwardsCompatabilty($vio_payment_results)
	{
		$vio_payment_results['response_code'] = 100;
		$vio_payment_results['transactionid'] = $vio_payment_results['payment']['_id'];
		$vio_payment_results['processor_used'] = $vio_payment_results['payment']['destination_identifier'];
		$vio_payment_results['authcode'] = $this->getAuthCodeFromVIOSuccessfulReturn($vio_payment_results);
		return $vio_payment_results;
	}
	
	function getAuthCodeFromVIOSuccessfulReturn($vio_payment_results)
	{
		if (isset($vio_payment_results['payment']['response']['params']['code'])) {
			$authcode = $vio_payment_results['payment']['response']['params']['code'];
		} else if ($vio_payment_results['payment']['response']['params']['auth_code']) {
			$authcode = $vio_payment_results['payment']['response']['params']['auth_code'];
		} else {
			$authcode = "noauthcode"; 
		}
		return $authcode;
	}
	
	function recordChargeAndThenVoidIt($vio_payment_results)
	{
		myerror_log("about to record the order and charge in teh balance change table");
		$this->recordOrderTransactionsInBalanceChangeTableFromOrderId($this->order_id,$this->formatSuccessForBackwardsCompatabilty($vio_payment_results));
		//$this->recordOrderTransactionsInBalanceChangeTableFromOrderId($this->order_id,$vio_payment_results);
		$user = $this->billing_user_resource->getDataFieldsReally();
		$order_controller = new OrderController($mt,$user,$r, 5);
		myerror_log("Now going to void the charge");
        $send_email_to_user = false;
    	$refund_results = $order_controller->issueOrderRefund($this->order_id, "0.00",$send_email_to_user);
    	if ($refund_results['result'] != 'success') {
    		myerror_log("ERROR TRYING TO RECORD AND VOID CVV FAIL");
    		logData($refund_results, "refund results");
			MailIt::sendErrorEmailSupport("ERROR TRYING TO RECORD AND VOID CVV FAIL", "we were unable to record and then void a failed order due to a CVV mismatch.  order_id: ".$this->order_id."  stamp: ".getRawStamp());
    	}		
	}
	
	function doWeNeedToCheckCVVReturn()
	{
		if (strtolower(getProperty('check_cvv')) == 'true') {
			return $this->isCVVCheckFlagSetOnUserFlags($this->billing_user_resource->flags);
		}
		return false;
	}
	
	function isCVVCheckFlagSetOnUserFlags($flags)
	{
        myerror_log("we are about to check the flags for a cvv check: ".$flags);
		return doesFlagPositionNEqualX($flags, 4, '1');
	}
	
	function isCvvValidatedFromVIOPaymentResultArray($vio_payment_results)
	{
		return strtoupper($vio_payment_results['payment']['response']['cvv_result']['code']) == 'M';
	}
	
	function isItAValidCvvResponseForBillingUserState($vio_payment_results)
	{
		if ($this->doWeNeedToCheckCVVReturn()) {
            myerror_log("YES! we need to check for a CVV match");
			if ($this->testForExistanceOfCvvInVioReturn($vio_payment_results)) {
				if ($this->isCvvValidatedFromVIOPaymentResultArray($vio_payment_results)) {
					$this->billing_user_resource->flags = substr($this->billing_user_resource->flags, 0,3).'0'.substr($this->billing_user_resource->flags,4,6);
					return true;
				} else {
                    myerror_log("WE HAVE A CVV FAILURE!  message:  ".$vio_payment_results['payment']['response']['cvv_result']['message']);
					return false;
				}
			}
		}
		return true;
	}
	
	function testForExistanceOfCvvInVioReturn($vio_payment_results)
	{
		return (isset($vio_payment_results['payment']['response']['cvv_result']['code']));
	}
	
	function formatVioResponseMessage($vio_response_message)
	{
		if (is_array($vio_response_message)) {
            // new TSYS format
            $vio_response_message = isset($vio_response_message['responseMessage']) ? $vio_response_message['responseMessage'] : 'generic-error';  
        } else if (substr_count($vio_response_message, 'AVS FAILURE')) {
			// there are inconsistant characters on the AVS failure so trim it down
			$vio_response_message = 'AVS FAILURE';
		}
		$this->vio_response_message = $vio_response_message;
		return $vio_response_message;
	}
	
	function processVioError($vio_payment_results) 
	{
		$vio_payment_results['status'] = 'failure';
        if ($response_message = $this->formatVioResponseMessage($vio_payment_results['payment']['response']['message'])) {
            return $this->formatReturnFromErrorMessage($vio_payment_results, $response_message);
        } else if (isset($vio_payment_results['payment']['response']['exception']) && $this->calling_action != 'PlaceOrder') {
            $this->exception = true;
            $exception_response_message = $this->formatVioResponseMessage($vio_payment_results['payment']['response']['exception']);
            myerror_log("THere was an exception thrown on the call to VIO: $exception_response_message");
            $this->error_processing_payment_message = $exception_response_message.". Please try again.";
            return $this->formatReturnFromErrorMessage($vio_payment_results, $exception_response_message);
        } else {
            // ok so there may be strange formatted error in here so lets check it out
            $raw_results_array = json_decode($this->curl_response['raw_result'],true);
            if ($errors = $raw_results_array['errors']) {
                if ($errors['payment']['credit_card']) {
                    $the_error = $errors['payment']['credit_card'][0];
                } else if ($errors['payment']['exception']) {
                    $the_error = $errors['payment']['exception'][0]['message'];
                } else {
                    $the_error = 'generic-error';
                }

                return $this->formatReturnFromErrorMessage($vio_payment_results,$the_error);
            }
            myerror_log("CONNECTION ERROR TO VIO: ".$vio_payment_results['error']);
            recordError("CONNECTION ERROR TO VIO: ".$vio_payment_results['error'],"error code: ".$vio_payment_results['error_code']);
            $vio_payment_results['response_text'] = $this->curl_error_user_message;
            $vio_payment_results['response_code'] = 500;
        }
		return $vio_payment_results;
	}

    function formatReturnFromErrorMessage($vio_payment_results,$error_text)
    {
        if ($message = $this->getVioErrorMessageFromLookupTable($error_text)) {
            $vio_payment_results['response_text'] = $message;
        }
        return $vio_payment_results;
    }

    function getVioErrorMessageFromLookupTable($error_text)
    {
        if ($message = LookupAdapter::staticGetNameFromTypeAndValue('vio_payment_error', $error_text)) {
           return $message;
        } else {
            myerror_log("some unknown error processing card");
            MailIt::sendErrorEmailAdam("uncaught cc processing error message", "check logs to find out what to create in viopaymentservice->processVioError. $error_text");
        }
    }
	
	function recordOrderTransactionsInBalanceChangeTableFromOrderId($order_id,$payment_results)
	{
		$order_resource = CompleteOrder::getBaseOrderDataAsResource($order_id, $mimetypes);
		$this->recordOrderTransactionsInBalanceChangeTable($order_resource, $payment_results);
	}
	
	function recordOrderTransactionsInBalanceChangeTable($order_resource,$payment_results)
	{
		logData($payment_results, "payment results",5);
		$balance_change_resource_for_order = parent::recordOrderTransactionsInBalanceChangeTable($order_resource,null);
		$this->recordCCTransactionInBalaneChangeTable($order_resource,$payment_results,$balance_change_resource_for_order->balance_after);
	}

	function recordCCTransactionInBalaneChangeTable($order_resource,$payment_results,$balance_after_order)
	{
		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		$ending_balance_after_cc_charge = $balance_after_order + $this->amount;
		$billing_user_id = (isset($payment_results['user_id'])) ? $payment_results['user_id'] : $this->billing_user_id;
		$transaction_type = $payment_results['payment']['kind'];
		$transaction_identifier = $payment_results['payment']['identifier'];
		$card_type = $payment_results['payment']['credit_card_brand'];
		$card_last_four = $payment_results['payment']['credit_card_last_four'];
		$card_info = $card_type.'-'.$card_last_four;
		if (strtolower($transaction_type) == 'authorize') {
			$bc_resource = $balance_change_adapter->addAuthorizeRow($billing_user_id,$balance_after_order,$this->amount,$payment_results['processor_used'],$order_resource->order_id,$transaction_identifier,"PENDING");
			$this->balance_change_resource_for_cc_payment = $bc_resource;
			myerror_log("Successfull insert of authorization row");
		} else if ($bc_resource = $balance_change_adapter->addCCRow($billing_user_id, $balance_after_order, $this->amount, $ending_balance_after_cc_charge, $payment_results['processor_used'], $order_resource->order_id, $payment_results['transactionid'], 'authcode='.$payment_results['authcode'])) {
			$this->balance_change_resource_for_cc_payment = $bc_resource;
			$this->setUsersBalance($ending_balance_after_cc_charge);
			myerror_log("successful insert of primary order record balance change row");
		} else {
			myerror_log("ERROR!  FAILED TO ADD ROW TO BALANCE CHANGE TABLE");
			MailIt::sendErrorEMail('Error thrown in PlaceOrderController','ERROR*****************  FAILED TO ADD ROW TO BALANCE CHANGE TABLE, user_id = '.$user_id.', AFTER RUNNING THEIR CREDIT CARD AND UPDATING BALANCE: '.$balance_change_adapter->getLastErrorText());
			return;
		}
		$bc_resource->card_info = $card_info;
		$bc_resource->save();
	}
	
	function updateBalanceChangeInfo($results)
	{
		// if we get here all went well so the users balance shouldn't change.  we just need to update the balance change table to show charges an such??
		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		$starting_balance = $this->billing_user_resource->balance;
		if ($this->is_gift_charge) {
			myerror_log("we have a gift charge so first add gift row to balance change");
			$bc_resource = $balance_change_adapter->addGiftRow($_SERVER['AUTHENTICATED_USER'], 0.00, $this->amount, 0.00, $this->order_id, $notes, $double_billing_amt);
			$ending_balance = $starting_balance;
		} else {
			myerror_logging(1,"card has been run so set the users balance to zero");
			$this->billing_user_resource->balance = 0.00;
			$ending_balance = 0.00;
		}
		if ($bc_resource = $balance_change_adapter->addCCRow($this->billing_user_id, $starting_balance, $this->amount, $ending_balance, $results['processor_used'], $this->order_id, $results['transactionid'], 'authcode='.$results['authcode'])) {
			myerror_log("successful insert of primary order record balance change row");
		} else {
			myerror_log("ERROR!  FAILED TO ADD ROW TO BALANCE CHANGE TABLE");
			MailIt::sendErrorEMail('Error thrown in PlaceOrderController','ERROR*****************  FAILED TO ADD ROW TO BALANCE CHANGE TABLE, user_id = '.$user_id.', AFTER RUNNING THEIR CREDIT CARD AND UPDATING BALANCE: '.$balance_change_adapter->getLastErrorText());		
		}		
	}

	function capture($amount,$identifier)
	{
		if ($identifier == null) {
			throw new BillingException("No Transaction Identifier submitted for capture", 999, $previous);
		}
		$this->setAdmin();
		$data = array();
		$creds = array();
		$url = $this->send_url.'payments';
		$creds['kind'] = 'capture';
		$creds['amount'] = $amount;
		$creds['authorized_payment'] = $identifier;
		$creds['destination'] = $this->vio_payment_identifier;
		if (!isProd()) {
			$creds['test'] = "true";
		}
		$data['payment'] = $creds;
		$response = $this->curlIt($url, $data);
		return $response;


	}

	function bypassCreditCardCall()
	{
		return $this->billing_user_resource->user_id < 1000 || $_SERVER['DO_NOT_RUN_CC'] == 'true' || isUat() || isTest() || isStaging();
        //return $this->billing_user_resource->user_id < 1000 || $_SERVER['DO_NOT_RUN_CC'] == 'true';
	}
	
	function processPayment2($vio_destination_id,$user_guid)
	{
		// data must be in this form {"payment":{"kind":"purchase","amount":"10.50","destination":"5369011c9811b43dd5000001","credit_card":"6605-5vy07-w61m7-154z2"}}
		if ($user_guid == null) {
			throw new BillingException("user_guid ID IS NULL FOR PROCESSING PAYMENT", 999, $previous);
		} elseif ($vio_destination_id == null) {
			throw new BillingEntityException("vio_destination_id IS NULL FOR PROCESSING PAYMENT", 999, $previous);
		}
		
		// check for admin user or uat
        if ($this->order_as_it_is_submited['note'] == 'Fail Credit Card') {
		    myerror_log("About to force a CC fail because order note said so :p ");
            return $this->createForceCCFail();
        } else if ($this->bypassCreditCardCall()) {
			myerror_log("about to bypass CC payment due to Admin user or UAT");
			return $this->createAdminBypassPaymentResponse($this->amount,$vio_destination_id,$this->order_id,$user_guid);
		} else {
			myerror_log("process CC payment normally");
		}
		
		$this->setAdmin();
		$url = $this->send_url.'payments';
		//$creds['kind'] = "purchase";
        if ($this->forcePurchase()) {
            myerror_log("we are forcing a purchase");
            $creds['kind'] = "purchase";
        } else {
            $creds['kind'] = isset($this->billing_entity_record['process_type']) ? $this->billing_entity_record['process_type'] : "purchase";
        }
		$creds['amount'] = $this->amount;
		$creds['destination'] = $vio_destination_id;
		$creds['credit_card'] = $user_guid;
		if (isset($this->order_id)) {
			$creds['gateway_order_id'] = $this->order_id;
		}
		if (!isProd()) {
			$creds['test'] = "true";
		}
		$data['payment'] = $creds;
		if ($this->isBillingEntitySecureNet($this->additional_parameters['billing_entity_record'])) {
		    $data['gateway_options'] = ['customer'=>$this->billing_user_resource->first_name,'invoice_number'=>''.$this->order_id];
        }
		$time1 = microtime(true);
		$response = $this->curlIt($url, $data);
        $elapsed_time_string = getElapsedTimeFormatted($time1);
		if ($elapsed_time_string > 10) {
            recordError("LONG ORDER PROCESS","Time for CC processing is: $elapsed_time_string");
        }
		return $response;
	}

	function isBillingEntitySecureNet($billing_entity_record)
    {
        return $billing_entity_record['vio_credit_card_processor_id'] == 2008;
    }

    function processPassthrough($vio_destination,$passthrough_payload,$user_guid)
    {
        myerror_log("the jm passthrough payload: $passthrough_payload");
        $this->setAdmin();
        $url = $this->send_url.'payments';
        $payment['amount'] = $this->amount;
        $payment['destination'] = $vio_destination;
        $payment['pass_through_payload'] = $passthrough_payload;
        $payment['credit_card'] = $user_guid;
       /* if (isset($this->order_id)) {
            $payment['gateway_order_id'] = $this->order_id;
        }
        if (!isProd()) {
            $payment['test'] = "true";
        } */
        $data['payment'] = $payment;
        $response = $this->curlIt($url, $data);
        return $response;
    }
	
	function createAdminBypassPaymentResponse($amount,$vio_destination_id,$order_id,$uuid)
	{
		$refnum = generateCode(8);
		$authcode = generateCode(6);
		$amount_in_pennies = $amount*100;
		$transaction_id = "fake".generateCode(20);
		
		$json = '{"captures":[],"refunds":[],"response":{"cvv_result":{"code":"M","message":"Match"},"avs_result":{"street_match":null,"code":"YYY","message":null,"postal_match":null},"params":{"cvv2_result_code":"M","error":"Approved","error_code":"00000","result":"A","vpas_result_code":"","cvv2_result":"Match","avs_result_code":"YYY","avs_result":"Address: Match & 5 Digit Zip: Match","batch":"1","auth_code":"'.$authcode.'","status":"Approved","ref_num":"'.$refnum.'"},"authorization":"'.$refnum.'","success?":true,"test":true,"message":"Success"},"void_response":[],"_id":"'.$transaction_id.'","account_id":"535fe8fd9811b4770a00000c","address1":"1305 Pearl Street","amount":"'.$amount.'","authorization_id":"'.$refnum.'","cents":'.$amount_in_pennies.',"city":"Boulder","country":"USA","created_at":"2014-11-01T21:10:59Z","credit_card_brand":"visa","credit_card_id":"54554c5bdd391676eb00065c","credit_card_identifier":"'.$uuid.'","currency":"USD","destination_id":"fake_destination_for_admin_user","destination_identifier":"fakeprocessor","destination_kind":"sumdumgateway","destination_name":"sumdumgatewayname","destination_request_time":1.764781593,"first_name":"sumdum","gateway_order_id":"'.$order_id.'","identifier":"cb70aa77-5962-4647-affe-38ed3e9752a5","kind":"purchase","last_name":"guy","require_cvv":false,"void_status":"unvoided","updated_at":"2014-11-01T21:11:01Z","state":"CO","transaction_id":"'.$refnum.'","transacted":true,"status":"success","zip":"12345","destination":"fakeprocessor","credit_card":"'.$uuid.'","links":[{"rel":"self","href":"https:\/\/api-staging.value.io\/v1\/payments\/'.$transaction_id.'"},{"rel":"index","href":"https:\/\/api-staging.value.io\/v1\/payments"}]}';
		$payment = json_decode($json,true);
		
		$response['status'] = 'success';
		$response['http_code'] = 200;
		$response['payment'] = $payment;
		return $response;
	}

	function createForceCCFail()
    {
        $json = '{ "path":"/payments", "route":"/payments", "mode":"post", "status":"422 Unprocessable Entity", "errors":{"payment":{ "exception":[{ "message":"Really Bad Failure. 88888."} ]} }, "data":{"payment":{ "response":{ "exception": "Really Bad Failure. 88888" }, "void_response":{ }, "_id":"53a8b3b5dd3916cbf800011b", "account_id":"535fe8fd9811b4770a00000c", "amount":"16.00", "authorization_id":"A6NEJ9hKy0", "cents":1600, "created_at":"2014-06-23T23:09:42Z", "credit_card_id":"53a8b3a9dd3916cbf8000116", "currency":"USD", "destination_id":"53a8b3a0dd3916cbf8000112", "identifier":"4764fffd-ef95-4bd6-a43c-bda483b3071a", "kind":"purchase", "updated_at":"2014-06-23T23:09:42Z", "transaction_id":"A6NEJ9hKy0", "transacted":false, "status":"failure", "void_status":"unvoided", "destination":"8PX6X70I6KBQDA1GHH75", "credit_card":"3852-fhtt-oy5w-b440", "links":[{ "rel":"self", "href":"https://api-staging.value.io/v1/payments/53a8b3b5dd3916cbf800011b"},{ "rel":"index", "href":"https://api-staging.value.io/v1/payments"} ]} }, "account":{"slug":"splikit" }, "token":{"uuid":"05bfd758-5e1a-4ceb-bb39-f64b60d99d30","roles":[ "admin"] }}';

        $payment = json_decode($json,true);
        $response['raw_result'] = $json;
        $response['status'] = 'failure';
        $response['http_code'] = 422;
        $response['payment'] = $payment;
        $this->curl_response = $response;
        return $response;
    }

	function creditVoidTransaction($balance_change_row, $refund_amt, $merchant_resource)
	{
		//get it all in mountian
		$tz = date_default_timezone_get();
		date_default_timezone_set('AMERICA/DENVER');
		
		$created_date_string = date('Y-m-d',$balance_change_row['created']);
		$now_date_string = date('Y-m-d');
		myerror_log("created date string in (MOUNTAIN TIME ZONE): ".$created_date_string);
		myerror_log("now date string in (mountina time zone): ".$now_date_string);
		
		date_default_timezone_set($tz);
		
		$this->billing_user_resource = SplickitController::getResourceFromId($balance_change_row['user_id'], 'User');


		if ($balance_change_row['notes'] == 'PENDING') {
            $response = $this->voidTransaction($balance_change_row);
            if ($response['status'] != 'success' && $response['http_code'] != 408) {
                $void_response_json = json_encode($response['payment']['void_response']);
                MailIt::sendErrorEmailSupport("WE had a authorization reversal failure","order_id: ".$balance_change_row['order_id']."   response: from vio: $void_response_json");
                MailIt::sendErrorEmailAdam("WE had a authorization reversal failure", "order_id: ".$balance_change_row['order_id']."   response: from vio: $void_response_json");

                $bc_resource = Resource::find($this->getBalanceChangeAdapter(),$balance_change_row['id']);
                $bc_resource->notes = "authreversal-fail";
                $bc_resource->save();

                return $response;
            }
        } else if ($this->isChargeWithinLast24Hours($balance_change_row['created']) && $refund_amt < $balance_change_row['charge_amt']) {
            $response = $this->refundPotentiallyUnsettledTransaction($balance_change_row,$refund_amt);
        } else if ($this->isChargeWithinLast24Hours($balance_change_row['created']) && $refund_amt = $balance_change_row['charge_amt']) {
            $response = $this->voidTransaction($balance_change_row);
            if ($response['status'] != 'success') {
                $response = $this->refundTransaction($balance_change_row,$refund_amt);
            }
        } else {
            $response = $this->refundTransaction($balance_change_row,$refund_amt);
        }
        return $response;

//		if ($created_date_string == $now_date_string && $refund_amt == $balance_change_row['charge_amt'] ) {
//			$response = $this->voidTransaction($balance_change_row);
//			if ($response['status'] != 'success' && $response['http_code'] != 408) {
//                if ($balance_change_row['notes'] == 'PENDING') {
//                    $void_response_json = json_encode($response['payment']['void_response']);
//                    MailIt::sendErrorEmailSupport("WE had a authorization reversal failure","order_id: ".$balance_change_row['order_id']."   response: from vio: $void_response_json");
//                    MailIt::sendErrorEmailAdam("WE had a authorization reversal failure", "order_id: ".$balance_change_row['order_id']."   response: from vio: $void_response_json");
//
//                    $bc_resource = Resource::find($this->getBalanceChangeAdapter(),$balance_change_row['id']);
//                    $bc_resource->notes = "authreversal-fail";
//                    $bc_resource->save();
//
//                    return $response;
//                } else {
//                    $response = $this->refundTransaction($balance_change_row,$refund_amt);
//                }
//			}
//		} else if ($this->isChargeWithinLast24Hours($balance_change_row['created']) && $refund_amt < $balance_change_row['charge_amt']) {
//			$response = $this->refundPotentiallyUnsettledTransaction($balance_change_row,$refund_amt);
//		} else {
//			$response = $this->refundTransaction($balance_change_row,$refund_amt);
//		}
//		return $response;
	}
	
	function isChargeWithinLast24Hours($created_time_stamp) {
		$twenty_four_hous_ago_time_stamp = time()-(24*60*60);
		return $created_time_stamp > $twenty_four_hous_ago_time_stamp;
	}
	
	function voidTransaction($balance_change_row)
	{	
		$this->setAdmin();
		$url = $this->send_url."payments/".$balance_change_row['cc_transaction_id'];
		myerror_log("about to send to VIO for void (auth reversal possibly): $url");
		$creds['kind'] = "void";
		if (!isProd()) {
			$creds['test'] = "true";
		}
		$data['payment'] = $creds;
		$response = $this->curlIt($url, $data);
		if ($response['status'] == 'success') {
			// for backward compataboility
			$response['response_code'] = 100;
			$this->void = true;
			$this->process = 'VOID';
            $this->process_string = 'CCvoid';
            if ($balance_change_row['process'] == 'CCpayment') {
                $this->updateBalanceChangeRowWithVoid($balance_change_row);
            }
		}
		return $response;
	}

	function updateBalanceChangeRowWithVoid($balance_change_row)
    {
        $bc_resource = Resource::find($this->getBalanceChangeAdapter(),$balance_change_row['id']);
        $bc_resource->notes = "voided-".$bc_resource->notes;
        $bc_resource->save();
    }

	function refundPotentiallyUnsettledTransaction($balance_change_row,$refund_amount)
	{
		$response = $this->refundTransaction($balance_change_row, $refund_amount);
		if ($response['payment']['response']['message'] == 'CREDIT VOL EXCEEDED 0.0000') {
			$response['status'] = 'unsettled';
			$response['response_code'] = 101;
			$this->refund = true;
		}
		return $response;
	}

	function isBillingEntityHeartland($billing_entities_record)
    {
        return $billing_entities_record['vio_credit_card_processor_id'] == 2005;
    }
	
	function refundTransaction($balance_change_row,$refund_amount)
	{
        if ($refund_amount <=  0) {
            throw new BillingException("amount cannot be less then .01 cents");
        }
		$this->setAdmin();
		//$url = $this->send_url."payments/".$balance_change_row['cc_transaction_id'];
		$url = $this->send_url."payments";
		$creds['kind'] = "refund";
		if (!isProd()) {
			$creds['test'] = "true";
		}
		$payment_to_refund = $balance_change_row['cc_transaction_id'];
		// now check for heartland destination:
        $billing_entities_adapter = new BillingEntitiesAdapter(getM());
		if ($billing_entities_record = $billing_entities_adapter->getRecord(['external_id'=>$balance_change_row['cc_processor']])) {
            if ($this->isBillingEntityHeartland($billing_entities_record)) {
                // so we need to use the auth id instead of the capture id. ugh
                myerror_log("we have a heartland refund so we need to get the auth id to use instead of the cc transaxction id");
                if ($auth_balance_change_row = $this->getBalanceChangeAdapter()->getCurrentRecordForVoidOrRefund(array("order_id"=>$balance_change_row['order_id'],"process"=>"Authorize"))) {
                    $payment_to_refund = $auth_balance_change_row['cc_transaction_id'];
                    myerror_log("we have the auth row and are setting the payment to refund to: $payment_to_refund");
                } else {
                    myerror_log("could not get auth record from balance change so will use cc transaction id which will probably fail");
                }

            } else {
                myerror_log("not heartland so use normal cc transaction id");
            }
        } else {
		    myerror_log("could not get billing entities record defaulting to current cc record");
        }

		$creds['refunded_payment'] = $payment_to_refund;
		$creds['amount'] = $refund_amount;
		$creds['destination'] = $balance_change_row['cc_processor'];
		$creds['credit_card'] = $this->billing_user_resource->uuid;
		$data['payment'] = $creds;
		$response = $this->curlIt($url, $data);
		if ($response['status'] == 'success') {
			// for backward compataboility
			$response['response_code'] = 100;
			$this->refund = true;
			$this->process = 'REFUND';
            $this->process_string = 'CCrefund';
		}
		return $response;
	}
	
	function getCreditCardInfoNonSensitive($uuid)
	{
		$this->setAdmin();
		$url = $this->send_url.'credit_cards/'.$uuid;
		$response = $this->curlIt($url, $data);
        return $response;
	}

    function deleteVaultRecord($uuid)
    {
        $this->setAdmin();
        $url = $this->send_url.'credit_cards/'.$uuid;
        $data['action'] = 'delete';
        $response = $this->curlIt($url, $data);
        return $response;

    }

    function getLast4FromVioForUUID($uuid)
    {
        return $this->getLast4FromNonSensitiveResponse($this->getCreditCardInfoNonSensitive($uuid));
    }

    function getLast4FromNonSensitiveResponse($response)
    {
        if ($number = $response['credit_card']['number']) {
            return substr($number,-4);
        } else {
            return false;
        }

    }
	
	function isMonthYearInThePast($month,$year)
	{
		$year_as_int = (int) "20".substr($year,-2);
		$cc_year = (int) date('Y');
		if ($year_as_int < $cc_year) {
			return true;
		} else if ($year_as_int > $cc_year) {
			return false;
		}
		$month_as_int = (int) $month;
		$cc_month = (int) date('m');
		return ($month_as_int < $cc_month);
	}

	static function isVaultedCardExpired($uuid)
	{
		if ($_SERVER['HTTP_NO_CC_CALL'] == 'true') {
			return false;
		}
		$viops = new VioPaymentService($data);
		$response = $viops->getCreditCardInfoNonSensitive($uuid);
		return $viops->isMonthYearInThePast($response['credit_card']['month'], $response['credit_card']['year']);
	}
	
	function curlIt($endpoint,$data)
	{
		myerror_logging(3,"about to curl VIO to endpoint: ".$endpoint);
		logData($data, "vio info",5);
		$response = VioPaymentCurl::curlIt($endpoint,$data, $this->username_password);
		$this->curl_response = $response;
		return $this->processCurlResponse($response);
	}

    function processCurlResponse($response)
    {
        //<html><body><h1>504 Gateway Time-out</h1>The server didn't respond in time.</body></html>
        $result_array = parent::processCurlResponse($response);
        if ($result_array['error_no'] == 28 || strpos($response['raw_result'],'Gateway Time-out')) {
            $result_array['responsetext'] = "The request timed out reaching the cc processing facility.";
            $result_array['http_code'] = 408;
        }
        $this->response_in_data_format = $result_array;
        return $result_array;
    }
	
	function processRawReturn($raw_return)
	{	
		$return_array = json_decode($raw_return,true);
		$relavant_payload = $return_array['data'];
		return $relavant_payload;
	}
	
	function setAdmin()
	{
		$this->username_password  = getProperty("vio_admin_username_password");
	}
	
	function setWrite()
	{
        $this->username_password  = getProperty("vio_write_username_password");
	}
	
	function setRead()
	{
		$this->username_password  = getProperty("vio_read_username_password");
	}
	
	function setVioPaymentIdentifier($vio_payment_identifier)
	{
		$this->vio_payment_identifier = $vio_payment_identifier;
	}
	
	function getVioPaymentIdentifier()
	{
		return $this->vio_payment_identifier;
	}
	
	/**** ADMIN FUNCTIONS ****/

	function updateDestination($identifier,$payload)
	{
		return false;
	}

    function createPassthroughDestination($identifier,$payload)
    {
        $this->setAdmin();
        if (isNotProd()) {
            $payload['options']['ssl_verify'] = "none";
        }
        $url = $this->send_url.'destinations';
        $data['kind'] = 'pass-through';
        $data['identifier'] = $identifier;
        $data['config'] = $payload;
        $destination['destination'] = $data;
        $response = $this->curlIt($url, $destination);
        return $response;
    }

	function createDestination($type,$identifier,$payload)
	{
		$this->setAdmin();
		if (strtolower($type) == 'fpn') {
			$type = 'usa-epay';
		}		
		
		$url = $this->send_url.'destinations';
		$data['type'] = $type;
		$data['identifier'] = $identifier;
		$data['config'] = $payload;
		$data['payment_address_defaults_to_account'] = "true";
		$data['payments_include_address'] = "true";
		$destination['destination'] = $data;
		$response = $this->curlIt($url, $destination);
		return $response;
	}

    function setForcePurchase()
    {
        $this->force_purchase = true;
    }

    function unSetForcePurchase()
    {
        $this->force_purchase = false;
    }

    function forcePurchase()
    {
        return $this->force_purchase;
    }

}
class BillingEntityException extends BillingException
{
	function __construct($message)
	{
		parent::__construct($message, 999);
	}
}

class CaptureErrorException extends BillingException
{
	function __construct($message)
	{
		parent::__construct($message, 999);
	}
}

class NoSuchBillingEntityException extends BillingEntityException
{
    function __construct($message = "dummy")
    {
        parent::__construct("There is no such billing entity", 999);
    }
}
class CreditCardDeclinedException extends BillingException
{
    function __construct($message = "dummy")
    {
        parent::__construct("We're sorry but your credit card was declined.", 999);
    }
}
?>