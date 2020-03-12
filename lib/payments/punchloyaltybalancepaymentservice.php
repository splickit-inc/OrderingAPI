<?php
class PunchLoyaltyBalancePaymentService extends LoyaltyBalancePaymentService
{
    var $punch_loyalty_controller;
    var $process = 'PunchLoyaltyBalancePayment';

    protected static $discount_display = "Rockn' Rewards Used";

    function __construct($data)
    {
        parent::__construct($data);
    }
    
    function processLoyaltyPaymentResults($user_brand_points_resource,$payment_results)
    {
        // if successfull set the transaction_id,processor_used,authcode
        if ($payment_results['status'] == 'success') {
            $payment_results['response_code'] = 100;
            $payment_results['transactionid'] = $payment_results['order'];
            $payment_results['authcode'] = "noauthcode";
            return $payment_results;
        } else {
            return $this->processLoyaltyPaymentError($payment_results);
        }
    }

    function refundLoyaltyPayment($loyalty_payment_results)
    {
        $results = $this->punch_loyalty_controller->refundLoyaltyRedemtion($loyalty_payment_results);
        return $results['http_code'] == 202;
    }

    function chargeLoyaltyAccount($redemption_amount)
    {
        $complete_order = CompleteOrder::staticGetCompleteOrder($this->order_id,$m);
        $this->complete_order = $complete_order;
        $this->punch_loyalty_controller = new PunchLoyaltyController($m,$this->billing_user_resource->getDataFieldsReally(),$r);
        $punch_payment_results = $this->punch_loyalty_controller->sendLoyaltyRemdemptionEvent($complete_order,$redemption_amount);
        $this->loyalty_payment_results = $punch_payment_results;
        return $punch_payment_results['response_code'] == 100;
    }

    function processLoyaltyPaymentError($payment_results)
    {
        $payment_results['status'] = 'failure';
        if ($message = $payment_results['status.description']) {
            $payment_results['response_text'] = $message;
        } else if ($error_array = json_decode($payment_results['raw_result'])) {
            if (count($error_array) == 1) {
                $message = $error_array[0];
                $payment_results['response_text'] = $message;
                $payment_results['punch_error_message'] = $message;
            } else {
                myerror_log("something new returned from punch: ".$payment_results['raw_result']);
                MailIt::sendErrorEmail("Strange Punch Message", "something new returned from punch. check logs to find out what to create in PunchLoyaltyBalancePaymentService->processLoyaltyPaymentError line 48: ".logData($payment_results, 'punch return'));
            }
        } else {
            myerror_log("some unknown error processing stored value card");
            MailIt::sendErrorEmail("uncaught stored value processing error message", "check logs to find out what to create in PunchLoyaltyBalancePaymentService->processLoyaltyPaymentError: ".logData($payment_results, 'punch return'));
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
    
    function getTransactionId()
    {
        $transaction_id = "redemption_id: ".$this->loyalty_payment_results['redemption_id'].", redemption_code: ".$this->loyalty_payment_results['redemption_code'];
        return $transaction_id;
    }


}
?>