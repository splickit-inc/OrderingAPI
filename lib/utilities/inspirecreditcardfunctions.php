<?php

class InspireCreditCardFunctions extends CreditCardFunctions
{

	function creditVoidTransaction($balance_change_row, $refund_amt, $merchant_resource)
	{
		$brand_id = $merchant_resource->brand_id;
		$inspire_pay = new InspirePay($brand_id);
		
		$transaction_id = $balance_change_row['cc_transaction_id'];
		
		//get it all in mountian
		$tz = date_default_timezone_get();
		date_default_timezone_set('AMERICA/DENVER');
		
		$created_date_string = date('Y-m-d',$balance_change_row['created']);
		$now_date_string = date('Y-m-d');
		myerror_log("created date string in (MOUNTAIN TIME ZONE): ".$created_date_string);
		myerror_log("now date string in (mountina time zone): ".$now_date_string);
		
		date_default_timezone_set($tz);

		if ($created_date_string == $now_date_string )
		{
			if ($refund_amt != $balance_change_row['charge_amt']) 
			{
				 myerror_log("we are doing a partial on the same day so we must do a refund, not a void");
				 $inspire_pay->refund($transaction_id,$refund_amt);
				//$this->refund_error = "Error! partial refunds cannot be processed until the original charge has 'settled'. Usually 24hrs from time of purchase.";
				//return false;
				$this->process = 'REFUND';
			} else {
				// do void instead
				$this->void = true;
				myerror_log('we are doing a void since its the entire amount and on teh same day');
				$inspire_pay->void($transaction_id);
				$this->process = 'VOID';
                $this->process_string = 'CCvoid';
			}
		} else {
			$inspire_pay->refund($transaction_id,$refund_amt);
			$this->process = 'REFUND';
            $this->process_string = 'CCrefund';
		}
		$return_fields = $inspire_pay->getReturnData();
		
		return $return_fields;
		 
	}
	
}
