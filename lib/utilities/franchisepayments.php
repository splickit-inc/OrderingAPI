<?php

class FranchisePayments
{
	
	var $key; // = "41Mk65OH04krz32O4ZG2vy3Oj884882A";
	var $pin; // = "1405";
	
	function FranchisePayments($merchant_id)
	{
		if (!isProd()) {
			$this->key = "_1Mk65OH04krz32O4ZG2vy3Oj884882A";
			$this->pin = 1405;			
		} else {
			$mfm_adapter = new MerchantFPNMapAdapter($mimetypes);
			$record = $mfm_adapter->getMerchantFPNMapRecord($merchant_id);
			$this->key = $record['fpn_merchant_key'];
			$this->pin = $record['fpn_merchant_pin'];
		}
	}
	
	function process($amount_to_run_card_for,$billing_user_resource,$neworder_id,$merchant,$card_data)
	{
		// Instantiate USAePay client object
		$tran=new umTransaction;
		
		$tran->key=$this->key;
		if ($this->pin) {
			$tran->pin=$this->pin;
		}
		
		if (!isProd()) {
			if ($card_data['cc_number'] == '4111111111111111') {
				$card_data['cc_number'] = "4005562233445564";
				$card_data['cc_exp'] = '0320';
				$card_data['zip'] = "90036";
				if (isset($card_data['cvv'])) {
					unset($card_data['cvv']);
				}
			}
			$tran->usesandbox=true;
			$tran->ignoresslcerterrors=true;    
		}
    
		$tran->card=$card_data['cc_number'];		
		$tran->exp=$card_data['cc_exp'];			
		$tran->amount=$amount_to_run_card_for;			
		$tran->invoice=$neworder_id;   		
		$tran->cardholder=$billing_user_resource->first_name.' '.$billing_user_resource->last_name; 	
		//$tran->street="1234 Main Street";	
		$tran->zip=$card_data['zip'];			
		$tran->description="Online Order";	
		$tran->cvv2=$card_data['cvv'];		
				
		if($tran->Process())
		{
			myerror_log("successfull processing of Credit Card with USAePay. auth_code: ".$tran->authcode);
			$result_data['response_code'] = 100;
			$result_data['auth_code'] = $tran->authcode;
			$result_data['authcode'] = $tran->authcode;
			$result_data['avs_result'] = $tran->avs_result;
			$result_data['cvv2_result'] = $tran->cvv2_result;
			$result_data['ref_no'] = $tran->refnum;
			$result_data['transactionid'] = 'ref_no='.$tran->refnum;
			$result_data['responsetext'] = $tran->result;
		} else {
			myerror_log("ERROR! we had a card failure");
			$result_data['result'] = $tran->result;
			$result_data['result_code'] = $tran->resultcode;
			$result_data['error'] = $tran->error;
			$result_data['error_code'] = $tran->errorcode;
			$result_data['responsetext'] = $tran->result;
			$result_data['ref_no'] = $tran->refnum;
			if($tran->curlerror) {
				$result_data['curl_error'] = $tran->curlerror;
			}				
		}	
		return $result_data;
	}
	
	function creditVoid($amount_to_run_card_for,$transaction_id)
	{
		$tran=new umTransaction;
		 
		$tran->key=$this->key;
		$tran->pin=$this->pin;    
		$tran->testmode=0;    // Change this to 0 for the transaction to process
		$tran->command="creditvoid";    
		$tran->refnum="$transaction_id";		// the original ref number received during the authorization
		if (!isProd())
		{
			$tran->usesandbox=true;
			$tran->ignoresslcerterrors=true;    
		}
		if($tran->Process())
		{
			myerror_log("successful credit void");
			$result_data['result'] = "success";
			$result_data['responsetext'] = $tran->result;
		} else {
			myerror_log("there was an error");
			$result_data['result'] = "failure";
			$result_data['responsetext'] = $tran->error;
		}
		return $result_data;
	}

}