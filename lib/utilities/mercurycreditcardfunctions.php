<?php

class MercuryCreditCardFunctions extends CreditCardFunctions
{

	function creditVoidTransaction($balance_change_row, $refund_amt, $merchant_resource)
	{
		$mercury_payment = new MercuryPayments();
		$mercury_payment->setFileAdapter($this->file_adapter);
		$card_data = $this->pullCreditCardDataFromUserId($balance_change_row['user_id']);

		$last_four = $this->getLastFour($card_data);
		$clean_card_no = $this->getCleanCardNumber($card_data);
		$mercury_payment->setLastFour($last_four);
		$card_data['order_id'] = ''.$balance_change_row['order_id'];
		$card_data['merchant_id'] = ''.$merchant_resource->merchant_id;
		
		//get it all in mountian
		$tz = date_default_timezone_get();
		date_default_timezone_set('AMERICA/DENVER');
		
		$created_date_string = date('Y-m-d',$balance_change_row['created']);
		$now_date_string = date('Y-m-d');
		myerror_log("created date string in (MOUNTAIN TIME ZONE): ".$created_date_string);
		myerror_log("now date string in (mountina time zone): ".$now_date_string);
		
		date_default_timezone_set($tz);
		
		if ($created_date_string == $now_date_string && $refund_amt == $balance_change_row['charge_amt'] )
		{
			$card_data['tran_code'] = 'VoidSale';
			$this->void = true;
			myerror_log('we are doing a void');
			// get auth code
			$auth_code_string = $balance_change_row['notes'];
			$auth_data = explode('=', $auth_code_string);
			$card_data['auth_code'] = $auth_data[1];
			$this->process = 'VOID';
            $this->process_string = 'CCvoid';
		} else {
			$card_data['tran_code'] = 'Return';
			$this->process = 'REFUND';
			$this->process_string = 'REFUND';
		}
		$card_data['charge_amt'] = ''.$refund_amt;
		
		// get ref number
		$ref_string = $balance_change_row['cc_transaction_id'];
		$ref_data = explode('=', $ref_string);
		$card_data['ref_no'] = $ref_data[1];

		$return_fields = $mercury_payment->runcard($card_data);
		if ($card_data['tran_code'] == 'VoidSale' && $mercury_payment->getTextResponse() == 'INV ITEM NUM')
		{
			// try doing a straight up return.
			myerror_log("The VOID failed so lets try a refund");
			$card_data['tran_code'] = 'Return';
			unset($card_data['auth_code']);
			$this->void = false;
			$return_fields = $mercury_payment->runcard($card_data);
			$this->process = 'REFUND';
            $this->process_string = 'CCrefund';
		}
		return $return_fields;
		
	}
	
}
