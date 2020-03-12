<?php
class AccountCreditPaymentService extends SplickitPaymentService
{
	var $successfull_process_payment_message = "Payment Posted Against Account Balance";
	
	function processPayment($amount)
	{
		if ($this->billing_user_resource->balance > $amount) {
			return $this->createSuccessfullPaymentResponseHash();
		} else if ($this->billing_user_resource->balance > $amount - 1) {
			myerror_log("we have a small negative balance so process with account credit");
			return $this->createSuccessfullPaymentResponseHash();
		} else {
			myerror_log("Account credit payment service chosen but user does not have a big enough balance!");
			myerror_log("balance: ".$this->billing_user_resource->balance."   amount of order: ".$amount);
			MailIt::sendErrorEmail("Seroius Payment Error!", "Account credit payment service chosen but user does not have a big enough balance!");
			return $this->createPaymentResponseHash("There was a serious error and your order did not go through. Support has been alerted. Please try again", 250);
		}
	}

	function recordOrderTransactionsInBalanceChangeTable($order_resource, $payment_results)
	{
		parent::recordOrderTransactionsInBalanceChangeTable($order_resource, $payment_results);
		$ending_balance = $this->user_starting_balance - $this->amount;
		myerror_log("we have the ending balance in AccountCreditPaymentService->recordOrderTransactionsInBalanceChangeTable(): $ending_balance");
		$this->setUsersBalance($ending_balance);
	}
}
?>
