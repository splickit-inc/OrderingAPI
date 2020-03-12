<?php

class FranchiseCreditCardFunctions extends CreditCardFunctions
{

	function creditVoidTransaction($balance_change_row,  $refund_amt, $merchant_resource)
	{
		$merchant_id = $merchant_resource->merchant_id;
		
		$ref_num_string = $balance_change_row['cc_transaction_id'];
    	$s = explode("=", $ref_num_string);
    	if ($ref_no = $s[1])
    	{
	    	$fz_payments = new FranchisePayments($merchant_id);
			$return_fields = $fz_payments->creditVoid($refund_amt,$ref_no);
			if ($return_fields['result'] == 'success')
			{			
				$return_fields['response_code'] = 100;
				$return_fields['authcode'] = $balance_change_row['note'];
				$return_fields['transactionid'] = $ref_num_string;
				$this->process = 'credit/void';
			}
			
    	} else {
    		$return_fields['result'] = "failure";
    		$return_fields['responsetext'] = "Unable to get the ref no from the balance change records: ".$ref_num_string;
    	}		
		return $return_fields;
 
	}
	
}
