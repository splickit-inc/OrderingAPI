<?php
class DummyPaymentService extends VioPaymentService
{
	/* 
	 * class used purely for backward compatability untill we get everyone moved over to new payment servie.  This gets created if we there is no record 
	 * in the new MerchantPaymentMap table for a merchant.
	 * 
	 */
	
	function processPayment($amount)
	{
		$this->amount = $amount;
		$cc_functions = new CreditCardFunctions();
		$return_fields = $cc_functions->cardProcessor($amount, $this->billing_user_resource, $this->order_id, $this->additional_parameters['merchant'], $card_data);
		if (substr_count(strtolower($return_fields['responsetext']),'duplicate transaction') > 0)
		{
				// duplicate transaction outside of the 15 minute windown so its probably on purpose
				myerror_logging(2,"about to run duplicate transaction minus a penny");
				$return_fields = $cc_functions->cardProcessor($amount-.01, $this->billing_user_resource, $this->order_id, $this->additional_parameters['merchant'], $card_data);
				if ($return_fields['response_code'] != 100 && substr_count(strtolower($return_fields['responsetext']),'duplicate transaction') > 0)
				{
					myerror_logging(2,"NO GOOD :(  lets try with 1 cent more less since 3 orders a day is not completely unheard of, especially for a coffee shop");
					$return_fields = $cc_functions->cardProcessor($amount+.01, $this->billing_user_resource, $this->order_id, $this->additional_parameters['merchant'], $card_data);
					if (substr_count(strtolower($return_fields['responsetext']),'duplicate transaction') > 0)
						$return_fields['response_code'] == 160;			
				}
		}
		return $return_fields;
	}
	
	function processPaymentError($payment_results)
	{
		// set error text and code
		// set for consistancy
		$payment_results['response_text'] = $payment_results['responsetext'];
		
		if (substr_count(strtolower($payment_results['responsetext']),'issuer declined') > 0 ||  $payment_results['responsetext'] == 'Transaction not permitted by issuer') {
			$payment_results['response_text'] = "We're sorry, but your bank does not appear to allow this type of transaction on this card, please try another.";
			$payment_results['response_code'] = 145;
		}
		else if (substr_count(strtolower($payment_results['responsetext']),'duplicate transaction') > 0)
		{
			$payment_results['response_text'] = "We're sorry, but this is a duplicate transaction amount and has been rejected by your credit card company.";
		}	
		else if (substr_count(strtolower($payment_results['responsetext']), "invalid credit card number") || $payment_results['responsetext'] == 'Invalid card number' || substr_count(strtoupper($payment_results['responsetext']),'INVLD EXP DATE') > 0)
		{
			$payment_results['response_text'] = "We're sorry, but your credit card number or expiration date appears invalid, please update it.";
			$payment_results['response_code'] = 110;
		}
		else if ($payment_results['responsetext'] == 'AVS REJECTED')
		{
			$payment_results['response_text'] = "We're sorry, but your credit card is being rejected due to an incorrect zip code, please update it.";
			$payment_results['response_code'] = 110;
		}	
		else if ($payment_results['responsetext'] == 'CVV2 Mismatch')
		{
			$payment_results['response_text'] = "We're sorry, but your CVV number does not match up correctly, please re-save your information";
			$payment_results['response_code'] = 110;
		}	
		else if (substr_count($payment_results['responsetext'],"DECLINE") > 0)
		{
			$payment_results['response_text'] = "We're sorry, but this credit card has been declined, please upload a differnt one.  It is possible your bank does not allow this type of transaction on this card.";
			$payment_results['response_code'] = 110;
		}	
		else if ($payment_results['responsetext'] == 'Insufficient funds')
		{
			$payment_results['response_text'] = "We're sorry, but your bank is denying this transaction due to insufficient funds. Please try a differnt card.";
			$payment_results['response_code'] = 146;
		}	
		else if ($payment_results['responsetext'] == 'No checking account' || $payment_results['responsetext'] == 'No account')
		{
			$payment_results['response_text'] = "We're sorry, but your bank is denying this transaction due to no account attached. Please try a differnt card or contact your bank to clear up the matter.";
			$payment_results['response_code'] = 146;
		}	
		else if ($payment_results['response_code'] == 223 || $payment_results['responsetext'] == 'Expired card')
		{
			$payment_results['response_text'] = "We're sorry, but your credit card has expired, please update it.";
			$payment_results['response_code'] = 120;
		}	
		else if ($payment_results['response_code'] == 240) // call voice center
		{
			$payment_results['response_text'] = "We're sorry, but there is a problem billing your credit card, please call your bank or try a different card.";
			$payment_results['response_code'] = 146;
		}	
		else if (substr_count(strtolower($payment_results['responsetext']),'invalid customer vault id') > 0)
		{
			$payment_results['response_text'] = "We're sorry, your credit card info has expired, please re-enter your information.  Thanks!";
			$payment_results['response_code'] = 120;
		}	
		else if (substr_count(strtolower($payment_results['responsetext']),'timeout') > 0)
		{
			$payment_results['response_text'] = "Sorry, there was a transmission error. Please try again.";
			$payment_results['response_code'] = 599;
		}	
		else if (substr_count(strtoupper($payment_results['responsetext']),'AMEX NOT ACCEPTED') > 0)
		{
			$payment_results['response_text'] = "We're sorry, but this merchant does not accept American Express. Please upload a different card.";
			$payment_results['response_code'] = 180;
		} 
		else if (substr_count(strtolower($payment_results['responsetext']),"and/or currency [usd] is not accepted") > 0)
		{
			$payment_results['response_text'] = "We're sorry, but this merchant does not accept this type of card. Please upload a different one.";
			$payment_results['response_code'] = 180;
		} 
		else if ($payment_results['response_code'] == 421 || substr_count(strtolower($payment_results['responsetext']),"no connection to any server") > 0 || substr_count(strtolower($payment_results['responsetext']),"file is temporarily unavailable") > 0 || substr_count(strtolower($payment_results['responsetext']),"bad bin") > 0 || substr_count(strtolower($payment_results['responsetext']),"host disconnect") > 0)
		{
			$payment_results['response_text'] = "We're sorry, but there was a connection problem reaching your bank to process your card, please try again, or use a different card.";
			$payment_results['response_code'] = 599;
		} 
		else if (substr_count(strtolower($payment_results['responsetext']),"invalid credentials") > 0 )
		{
			$payment_results['response_text'] = "We're sorry, but this merchants credit card processing has not been set up correctly, we have been notified and will correct the problem shortly. Sorry for the inconvenience.";
			$payment_results['response_code'] = 599;
		}
		else if (substr_count(strtolower($payment_results['responsetext']),"authentication failed") > 0 )
		{
			$payment_results['response_text'] = "We're sorry, but this merchants credit card processing has not been set up correctly, we have been notified and will correct the problem shortly. Sorry for the inconvenience.";
			$payment_results['response_code'] = 599;
		}
		else if (substr_count($payment_results['responsetext'],"Pick up card") > 0 )
		{
			$payment_results['response_text'] = "We're sorry, there was an unrecognized error running your credit card and your order did not go though. You may want to contact you bank to clear up the matter.  Please enter a different card.";
			$payment_results['response_code'] = 190;
		}
		else if ($payment_results['response_code'] == 500)
		{
			// bad mercury setup
			$payment_results['response_text'] = "We're sorry, there is an error in the setup of this merchant's credit card processing and cannot accept orders at this time. Support has been alerted, please try again shortly";
			$payment_results['response_code'] = 599;
		}
		else 
		{
			$payment_results['response_text'] = "We're sorry, there was an unrecognized error running your credit card and your order did not go though. Please try again or enter a different card.";
			$payment_results['response_code'] = 190;
			MailIt::sendErrorEmailTesting('strange error from billing', 'response:  '.$payment_results['responsetext']); 
		}
		return parent::processPaymentError($payment_results);		
	}
}
?>
