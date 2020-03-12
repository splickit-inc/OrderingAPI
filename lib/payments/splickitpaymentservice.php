<?php
abstract class SplickitPaymentService extends SplickitService
{
	/**
	 *
	 * @desc the amount being charged to this payment type
	 * @var long
	 */
	var $amount;

	/**
	 *
	 * @desc any additional parameters that are needed for the payment type
	 * @var array()
	 */
	var $additional_parameters;

	/**
	 *
	 * @desc the lable that is shown to the consumer  (eg:  Mercury  would have a display_value of 'Credit Card'
	 * @var string
	 */
	var $display_value;

	/**
	 *
	 * @desc the class name
	 * @var string
	 */
	var $name;

	/**
	 *
	 * @desc the splickit user_id that is being billed
	 * @var int
	 */
	var $billing_user_id;

	/**
	 *
	 * @desc the splickit user resource that is being billed
	 * @var Resource
	 */
	var $billing_user_resource;

	/**
	 * @desc merchant_id associated with this charge
	 */
	var $merchant_id;

	/**
	 * @desc order id associated with this charge
	 * @var int
	 */
	var $order_id;

	/**
	 *
	 * @desc whether the current charge is a gift charge
	 * @param booan
	 */
	var $is_gift_charge = false;

	/**
	 *
	 * @desc array to hold the values retuned from the process payment call;
	 * @var Hashmap
	 */
	var $payment_results = array();

	/**
	 *
	 * @desc holds the starting balance of the user
	 * @var long
	 */
	var $user_starting_balance = 0.00;

	/**
	 *
	 * @desc holds the string that will be shown on successfull paymentProcess
	 * @var string
	 */
	var $successfull_process_payment_message = "Payment Processed";

	/**
	 *
	 * @desc holds the resource of the balance change row for the order
	 * @param Resource $balance_change_resource_for_order
	 */
	var $balance_change_resource_for_order;

	/**
	 *
	 * @desc holds the default response when a payment fails
	 * @var string
	 */
	var $error_processing_payment_message = 'Unknown Error Processing Payment';

	/**
	 * @desc holds the complete order object associated with this payment
	 * @var array()
	 */
	var $complete_order;


	/**
	 * @desc determines if this payment service has cash properties
	 * @var bool
	 */
	var $cash_type_payment_service = false;

    /**
	 * @desc holds the calling action of the service.  eg: 'PlaceOrder'
     * @var string
     */
	var $calling_action;

    /**
	 * @desc holds the order that was submitted to be placed in order to get access to things like submktted tip and note
     * @var Array
     */
	var $order_as_it_is_submited;
	var $exception = false;


	const CASHSPLICKITPAYMENTID = 1000;
	const CREDITCARDSPLICKITPAYMENTID = 2000;
	const LEVELUPSPLICKITPAYMENTID = 6000;
	const LOYALTY_PLUS_CC_PAYMENT_ID = 8000;
	const LOYALTY_PLUS_CASH_PAYMENT_ID = 9000;
    const LEVELUPPASSTHROUGHPAYMENTID = 10000;
    const LEVELUPBROADCASTPAYMENTID = 11000;

    function __construct($data)
	{
		parent::__construct($data);
		$this->name = get_class($this);
		$this->additional_parameters = $data;
		$this->billing_user_id = $data['user_id'];
		$this->merchant_id = $data['merchant_id'];
	}

	abstract function processPayment($amount);

	/**
	 * @desc this method should be overridden by custom payment services in order to get additional data out of the submitted order data.
	 * @param $order_data
	 * @return bool
	 */
	function loadAdditionalDataFieldsIfNeeded($order_data)
	{
		return true;
	}

	/**
	 *
	 * @desc will do the billing for the order and return the payments results with reponse_text and response_code.
	 * @param Resource $order_resource
	 * @param Resource $gift_resource
	 * @return Hashmap
	 */
	function processOrderPayment(&$order_resource,$billing_amount,$gift_resource)
	{
		if ($gift_resource) {
			return $this->processGiftOrderPayment($order_resource,$gift_resource);
		}
		$this->order_as_it_is_submited = $order_resource->getDataFieldsReally();
		$this->setAmount($billing_amount);
		$this->setBillingUserIdAndResourceFromUserId($order_resource->user_id);
		$this->setOrderId($order_resource->order_id);
		$this->payment_results = $this->processPayment($billing_amount);
		if ($this->payment_results['response_code'] == 100) {
			$this->recordOrderTransactionsInBalanceChangeTable($order_resource,$this->payment_results);
			return $this->createSuccessfullPaymentResponseHash();
		} else {
			if (! $this->isDuplicateTransaction()) {
                $order_adapter = new OrderAdapter();
                $order_resource->status = 'N';
                $order_adapter->updateOrderResource($order_resource);
            }
			return $this->processPaymentError($this->payment_results);
		}
	}

	function isDuplicateTransaction()
	{
		if ($message = $this->vio_response_message) {
			return substr_count(strtolower($message),'duplicate') > 0;
		} else {
			return false;
		}
	}

	/**
	 *
	 * @desc will strip out all extranous info and just resturn the text and code of the error
	 * @param HashMap $payment_results
	 */
	function  processPaymentError($payment_results)
	{
		if ($payment_results['response_text']) {
			return $this->createPaymentResponseHash($payment_results['response_text'], $payment_results['response_code']);
		} else {
			return $this->createPaymentResponseHash($this->error_processing_payment_message, 999);
		}
	}

	/**
	 *
	 * @desc This method will record the order in teh balance change table. only payment services and run a charge should call this first from their
	 * @desc method of the same name. parent::recordOrderTransactionsInBalanceChangeTable
	 * @param Resource $order_resource
	 * @param Hashmap $payment_results
	 */
	function recordOrderTransactionsInBalanceChangeTable($order_resource,$payment_results = null)
	{
		$balance_change_adapter = new BalanceChangeAdapter($m);
		$order_ending_balance = $this->user_starting_balance - $order_resource->grand_total;
		$this->balance_change_resource_for_order = $balance_change_adapter->addOrderRow($this->billing_user_id, $this->user_starting_balance, -$order_resource->grand_total, $order_ending_balance, $order_resource->order_id, $notes);
		return $this->balance_change_resource_for_order;
	}

	function createSuccessfullPaymentResponseHash()
	{
		return $this->createPaymentResponseHash($this->successfull_process_payment_message, 100);
	}

	function createPaymentResponseHash($response_text,$response_code)
	{
		return array("response_text"=>$response_text,"response_code"=>$response_code);
	}

	/**
	 *
	 * @desc will do the billing for the Gift order and return the resource.
	 * @param Resource $order_resource
	 * @param Resource $gift_resource
	 */
	function processGiftOrderPayment($order_resource,$gift_resource)
	{
		// if not a CC payment service like VIO then throw exception.
		if ($this->name != 'VioPaymentService' && $this->name != 'DummyPaymentService') {
			throw new Exception ("Something other than CC payment service chosen for gift order");
		}

// perhaps this needs to go in the VioPaymentProcessorOnly?
				$this->setIsGiftCharge();
				$billing_user_id = $gift_resource->gifter_user_id;
				$this->setBillingUserIdAndResourceFromUserId($billing_user_id);
				$double_billing_amt = 0.00;
				$gift_billing_amount = $order_resource->grand_total;
				if (isset($gift_resource->double_billing_amt) && $gift_resource->double_billing_amt > 0.00)
				{
					myerror_logging(3,"we have a use gift over the amt with a CC on file.  will run users CC for ".$gift_resource->double_billing_amt);
					$double_billing_amt = $gift_resource->double_billing_amt;
					if ($order_resource->customer_donation_amt > 0.00)
						$double_billing_amt = $double_billing_amt + $order_resource->customer_donation_amt;
					$gift_billing_amount = $gift_resource->amt;
					$double_billing_user_id = $order_resource->user_id;

				}
				$payment_results = $this->processPayment($gift_billing_amount);
				$payment_results['user_id'] = $gift_resource->gifter_user_id;
				if ($payment_results['response_code'] == 100 && $double_billing_amt > 0.00) {
					myerror_logging(3,"about to do the double billing for large order and small gift");
					$this->setIsNotGiftCharge();
					$this->setBillingUserIdAndResourceFromUserId($double_billing_user_id);
					$payment_results2 = $this->processPayment($double_billing_amt);
					if ($payment_results2['response_code'] != 100) {
						myerror_log("ERROR! we have a failure on a second billing on a gift order");
						$this->voidTransaction($payment_results['transactionid']);
						$payment_results2['response_text'] = "We're sorry but there was an error charging YOUR credit card for the extra amount: ".$payment_results2['response_text'];
						return $this->cancelOrderAndReturnErrorMessageResource($resource,$payment_results2);
					}
				} else if ($payment_results['response_code'] != 100) {
					myerror_log("ERROR!  there was an error billing the gifters cc");
					$error_message_to_gifter = $payment_results['response_text'];
					// get gifter info
					if ($gifter_user_resource = Resource::find($user_adapter,''.$gift_resource->gifter_user_id))
					{
						$subject = "There was an error running your CC for the gift you sent";
						$body = "Hello ".$gifter_user_resource->first_name.", We received the following error when we tried to process your credit card for the gift you sent to ".$this->user['first_name']."    Error Text: ".$error_message_to_gifter.".   Please contact support if you need help clearing up the matter.";
						MailIt::stageEmail($gifter_user_resource->email, $subject, $body, $from_name, $bcc, $mmh_data);

					} else {
						myerror_log("ERROR!  couldn't get gifter user info for CC error!");
						MailIt::sendErrorEmail("ERROR GETTING GIFTER INFO on CC FAIL", "ERROR GETTING GIFTER INFO on CC FAIL.  error: ".$user_adapter->getLastErrorText());
					}
					$payment_results['response_text'] = "We're sorry but there was an error charging the credit card from your gifter.  We have notified the individual who sent you the gift and it will hopefully be taken care of shortly. Sorry for the inconvenience.";
					$payment_results['response_code'] = 999;

					return $this->cancelOrderAndReturnErrorMessageResource($resource,$payment_results);
				} else {
					$this->setBillingUserIdAndResourceFromUserId($order_resource->user_id);
				}
				// now need to do all the balance change stuff
				$balance_change_adapter = new BalanceChangeAdapter;

				$order_ending_balance = $this->user_starting_balance - $order_resource->grand_total;
				$this->balance_change_resource_for_order = $balance_change_adapter->addOrderRow($order_resource->user_id, $this->user_starting_balance, -$order_resource->grand_total, $order_ending_balance, $order_resource->order_id, $notes);
				$this->balance_change_resource_for_cc_payment = $balance_change_adapter->addCCRow($gift_resource->gifter_user_id, 0.00, $gift_billing_amount, 0.00, $payment_results['processor_used'], $order_resource->order_id, $payment_results['transactionid'], 'authcode='.$payment_results['authcode']);
				if ($double_billing_amt > 0.00) {
					$this->balance_change_resource_for_double_billing_cc_payment = $balance_change_adapter->addCCRow($order_resource->user_id, 0.00, $double_billing_amt, 0.00, $payment_results2['processor_used'], $order_resource->order_id, $payment_results2['transactionid'], 'authcode='.$payment_results2['authcode']);
				}
				$balance_change_row_for_gift = $balance_change_adapter->addGiftRow($order_resource->user_id, $this->balance_change_resource_for_order->balance_after, $gift_billing_amount, $this->balance_change_resource_for_order->balance_after + $gift_billing_amount, $order_resource->order_id, $notes, $double_billing_amt);
				$gift_resource->saveResourceFromData(array("order_id"=>$order_resource->order_id,"used_on"=>$order_resource->order_dt_tm,"used_amt"=>$gift_billing_amount));
				return $this->createSuccessfullPaymentResponseHash();
	}

	function voidTransation($id){}

	function setAmount($amount)
	{
		$this->amount = $amount;
	}

	function setData($data)
	{
		$this->additional_parameters = $data;
	}

	function getName()
	{
		return $this->name;
	}

	function setOrderId($order_id)
	{
		$this->order_id = $order_id;
	}

	function setBillingUserIdAndResourceFromUserId($user_id)
	{
		if (is_a($user_id, 'Resource') && isset($user_id->user_id)) {
			$this->billing_user_id = $user_id->user_id;
			$this->billing_user_resource = $user_id;
		} else {
			$this->billing_user_id = $user_id;
			$this->billing_user_resource = SplickitController::getResourceFromId($user_id, 'User');
		}
		$this->user_starting_balance = $this->billing_user_resource->balance;
	}

	function setIsGiftCharge()
	{
		$this->is_gift_charge = true;
	}

	function setIsNotGiftCharge()
	{
		$this->is_gift_charge = false;
	}

	function setUsersBalance($amount)
	{
		$this->billing_user_resource->balance = $amount;
	}

	function getCompleteOrderFromOrderId($order_id)
	{
		if (!$this->complete_order) {
			$this->complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
		}
		return $this->complete_order;
	}

	function isCashTypePaymentService()
	{
		return $this->cash_type_payment_service;
	}
}

class BillingException extends Exception
{
	function __construct($message, $code = 999)
	{
		parent::__construct($message, $code);
	}
}

class NoCreditCardOnFileBillingException extends BillingException
{
	function __construct($message)
	{
		parent::__construct($message, 100);
	}
}

?>
