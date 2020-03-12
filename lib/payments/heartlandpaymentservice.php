<?php
class HeartlandPaymentService extends SplickitPaymentService
{
    var $balance_change_resource_for_stored_value_payment;
    var $heartland_loyalty_service;

    function processPayment($amount)
    {
        $heartland_loyalty_service = $this->heartland_loyalty_service;
        if ($account_number = $this->getBillingUsersAccountNumber()) {
            $an = explode(":", $account_number);
            $sva = $an[0];
            if ($an[1]) {
                $pin = $an[1];
            }
            $heartland_loyalty_service->setStore(MerchantHeartlandInfoMapsAdapter::getHeartlandStoreId($this->merchant_id));
            $payment_results = $heartland_loyalty_service->chargeAgainstStoredValue($sva, $pin, $amount);
        } else {
            myerror_log("Could not get local loyalty record to process stored value for user_id: ".$this->billing_user_id);
            $payment_results['status.description'] = "Could not get local loyalty record to process stored value";
        }
        return $this->processHeartlandResults($payment_results);
    }

    function processHeartlandResults($payment_results)
    {
        // if successfull set the transaction_id,processor_used,authcode
        if ($payment_results['status'] == 'success') {
            $payment_results['response_code'] = 100;
            $payment_results['transactionid'] = $payment_results['order'];
            $payment_results['authcode'] = "noauthcode";
            return $payment_results;
        } else {
            return $this->processHeartlandError($payment_results);
        }
    }

    function processHeartlandError($payment_results)
    {
        $payment_results['status'] = 'failure';
        //if ($message = LookupAdapter::staticGetNameFromTypeAndValue('heartland_payment_error', $payment_results['status.name'])) {
        if ($message = $payment_results['status.description']) {
            $payment_results['response_text'] = $message;
        } else {
            myerror_log("some unknown error processing stored value card");
            MailIt::sendErrorEmailAdam("uncaught stored value processing error message", "check logs to find out what to create in HeartlandPaymentService->processHeartlandError: ".logData($payment_results, 'heartland return'));
        }
        return $payment_results;
    }

    function getBillingUsersAccountNumber()
    {
        $billing_user_id = $this->billing_user_id;
        $user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
        if ($user_brand_points_map_record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$billing_user_id,"brand_id"=>getBrandIdFromCurrentContext()))) {
            return $user_brand_points_map_record['loyalty_number'];
        }

    }

    function recordOrderTransactionsInBalanceChangeTable($order_resource,$payment_results)
    {
        $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
        $balance_change_resource_for_order = parent::recordOrderTransactionsInBalanceChangeTable($order_resource);
        $balance_after_order = $balance_change_resource_for_order->balance_after;
        $ending_balance_after_stored_value_charge = $balance_after_order + $this->amount;
        if ($bc_resource = $balance_change_adapter->addStoredValueRow($this->billing_user_id, $balance_after_order, $this->amount, $ending_balance_after_stored_value_charge, $this->name, $order_resource->order_id, $payment_results['transactionid'],'payment with stored value Card')) {
            $this->balance_change_resource_for_stored_value_payment = $bc_resource;
            $this->setUsersBalance($ending_balance_after_stored_value_charge);
            myerror_log("successful insert of primary order record balance change row");
        } else {
            myerror_log("ERROR!  FAILED TO ADD ROW TO BALANCE CHANGE TABLE");
            MailIt::sendErrorEMail('Error thrown in PlaceOrderController','ERROR*****************  FAILED TO ADD ROW TO BALANCE CHANGE TABLE, user_id = '.$user_id.', AFTER RUNNING HEARTLAND STORED VALUE AND UPDATING BALANCE: '.$balance_change_adapter->getLastErrorText());
        }
    }

}
?>