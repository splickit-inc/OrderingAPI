<?php
class LeveluppassthroughPaymentService extends SplickitPaymentService
{
    var $successfull_process_payment_message = "Payment Passed Through";

    function __construct($data)
    {
        parent::__construct($data);
        //$this->cash_type_payment_service = true;
    }

    function processPayment($amount)
    {
        return $this->createSuccessfullPaymentResponseHash();
    }

    function recordOrderTransactionsInBalanceChangeTable($order_resource, $payment_results)
    {
        // do nothing since its cash type
        //return true;

        // ****** OR ********


        $balance_change_adapter = new BalanceChangeAdapter(getM());
//        $balance_change_resource_for_order = parent::recordOrderTransactionsInBalanceChangeTable($order_resource);
//        $balance_after_order = $balance_change_resource_for_order->balance_after;
//        $ending_balance_after = $balance_after_order + $this->amount;
        if ($bc_resource = $balance_change_adapter->addRow ($this->billing_user_id, 0.00, 0.00, 0.00, 'LevelUpPassThrough',$this->name, $order_resource->order_id, 'none','Level Up Pass Through Payment')) {
            //$this->setUsersBalance($ending_balance_after);
            myerror_log("successful insert of order records in balance change");
        } else {
            myerror_log("ERROR!  FAILED TO ADD ROW TO BALANCE CHANGE TABLE");
            MailIt::sendErrorEMail('Error thrown in PlaceOrderController','ERROR*****************  FAILED TO ADD ROW TO BALANCE CHANGE TABLE, user_id = '.$order_resource->user_id.', in LeveupPASSTHROUGH payment service: '.$balance_change_adapter->getLastErrorText());
        }

    }

}
?>