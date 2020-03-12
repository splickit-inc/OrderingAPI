<?php
class LoyaltyBalancePaymentService extends SplickitPaymentService
{

    var $balance_change_resource_for_stored_value_payment;
    var $loyalty_payment_results = array();
    var $vio_payment_service;
    var $process = 'LoyaltyBalancePayment';
    protected $brand_loyalty_rules;
    protected $type_of_balance_payment = 'order_amt'; // this is subtotal  vs   grand_total
    var $reset_tax_amounts_due_to_loyalty_purchase = false;
    var $tax_adjustment;
    var $loyalty_tax_adjustment = 0.000;

    const BALANCE_ON_CARD_TEXT = 'Balance On Card';
    const BALANCE_WITH_CASH_TEXT = 'Balance With Cash';
    const NO_CC_ON_FILE_FOR_LOYALTY_PAYMENT_MESSAGE = "No CC on file. A valid credit card must be stored before redeeming loyalty balance.";
    const FAILED_LOYALTY_REDEMPTION_MESSAGE = "We're sorry but there was an unknown error trying to debit your rewards card. Please try again or use a different payment method.";


    // DO NOT CHANGE THIS VALUE IN ANY CHILD OBJECT
    const DISCOUNT_NAME = 'Loyalty Discount';
    const REDEEM_PROCESS_NAME = 'Redeem';


    // you can change this value
    protected static $discount_display = 'Rewards Used';

    function __construct($data)
    {
        parent::__construct($data);
        if ($this->brand_loyalty_rules = BrandLoyaltyRulesAdapter::getBrandLoyaltyRulesForContext()) {
            $this->type_of_balance_payment = $this->brand_loyalty_rules['loyalty_order_payment_type'];
        }
        if ($data['splickit_accepted_payment_type_id'] == 9000) {
            $this->cash_type_payment_service = true;
        }
    }

    function processPayment($amount)
    {
        $complete_order = CompleteOrder::staticGetCompleteOrder($this->order_id);
        if (!$this->isCashTypePaymentService() && substr($this->billing_user_resource->flags,1,2) != 'C2') {
            throw new NoCreditCardOnFileBillingException(self::NO_CC_ON_FILE_FOR_LOYALTY_PAYMENT_MESSAGE);
        }
        if ($user_brand_points_resource = Resource::find(new UserBrandPointsMapAdapter($m),null,array(TONIC_FIND_BY_METADATA=>array("user_id"=>$complete_order['user_id'],"brand_id"=>getBrandIdFromCurrentContext())))) {
            $loyalty_payment_amount = $this->getLoyaltyPaymentAmountForThisOrderAndLoyaltyPaymentType($amount,$complete_order,$user_brand_points_resource->dollar_balance);
            $loyalty_payment_results = $this->processLoyaltyPayment($loyalty_payment_amount, $user_brand_points_resource);
            if ($loyalty_payment_results['status'] == 'failure') {
                throw new FailedLoyaltyRedemptionException(self::FAILED_LOYALTY_REDEMPTION_MESSAGE);
            }
            if ($this->brand_loyalty_rules['charge_tax']) {
                myerror_log("Brand charges tax on loyalty so do not adjust",3); // tax was already charged so do nothing
            } else {
                myerror_log("Brand reduces tax burden on loyalty purchase. ADJUST THE TAX",3);
                // first get tax reduction
                $tax_rate = TaxAdapter::staticGetTotalBaseTaxRate($complete_order['merchant_id']);
                $loyalty_tax_adjustment = round(($tax_rate * $loyalty_payment_results['redemption_amount'])/100,3 );
                $this->loyalty_tax_adjustment = $loyalty_tax_adjustment;

                $old_total_tax = $complete_order['item_tax_amt'] + $complete_order['promo_tax_amt'] + $complete_order['delivery_tax_amt'];
                $new_total_tax = $complete_order['item_tax_amt'] - $loyalty_tax_adjustment + $complete_order['promo_tax_amt'] + $complete_order['delivery_tax_amt'];

                $this->tax_adjustment = round((float)($old_total_tax),2) - round((float)($new_total_tax),2);
                $amount = $amount - $this->tax_adjustment;

            }
            myerror_log("the amout to bill was: ".round($amount,2),3);
            myerror_log("the amount redeemed was: ".round($loyalty_payment_results['redemption_amount'],2),3);
            if (round($loyalty_payment_results['redemption_amount'],2) < round($amount,2)) {
                $remainder_to_bill = $amount - $loyalty_payment_results['redemption_amount'];
                myerror_log("the remainder to bill is: ".round($remainder_to_bill,2),3);
                if ($remainder_to_bill < .01) {
                    // trying to account for small balances due to tax rounding. just a hunch.
                    myerror_log("We have a small roudning error so ignore and assume zero");
                    return $this->processLoyaltyPaymentResults($user_brand_points_resource, $this->loyalty_payment_results);
                }
                if ($this->isCashTypePaymentService()) {
                    return $this->processLoyaltyPaymentResults($user_brand_points_resource, $this->loyalty_payment_results);
                }
                $results = $this->processVioPaymentForRemainder($remainder_to_bill , $complete_order['user_id'], $complete_order['merchant_id']);
                if ($results['status'] == 'success') {
                    $this->recordLoyaltyTransaction($user_brand_points_resource, $this->loyalty_payment_results);
                } else {
                    if (! $this->refundLoyaltyPayment($this->loyalty_payment_results)) {
                        myerror_log("ERROR REFUNDING LOYALTY PAYMENT!!!!!!!!");
                        MailIt::sendErrorEmailSupport("THere was an error refunding the loyalty payment","Order_id: ".$this->order_id." had a problem refunding the loyalty payment");
                    }
                }
                return $results;
            } else {
                return $this->processLoyaltyPaymentResults($user_brand_points_resource, $this->loyalty_payment_results);
            }
        } else {
            throw new BillingException("No Loyalty Record for user but loyalty payment chosen");
        }
    }

    function getLoyaltyPaymentAmountForThisOrderAndLoyaltyPaymentType($amount,$complete_order,$loyalty_balance)
    {
        if ($this->type_of_balance_payment == 'order_amt') {
            $amount = $complete_order['order_amt']+$complete_order['promo_amt'];
        }
        return ($loyalty_balance < $amount) ? $loyalty_balance : $amount;
    }

    function getLoyaltyTypeOfBalancePayment()
    {
        return $this->type_of_balance_payment;
    }
    
    function recordLoyaltyTransaction($user_brand_points_resource,$loyalty_payment_results)
    {
        $user_brand_points_resource->dollar_balance = $user_brand_points_resource->dollar_balance - $loyalty_payment_results['redemption_amount'];
        if ($this->brand_loyalty_rules['loyalty_type'] == 'splickit_earn') {
            // ok since loyalty_earn basically has 2 sources of truth (points and dollar balance) and they both must always agree.....
            // if we adjust the dollar balance then we have to make sure the points reflect that value.
            $user_brand_points_resource->points = $user_brand_points_resource->dollar_balance*100;
            // ok since we are NOT showing redeemed in $ for earn we need to convert back
            $loyalty_payment_results['redemption_amount'] = 100*$loyalty_payment_results['redemption_amount'];
        }
        $user_brand_points_resource->save();
        $ublha = new UserBrandLoyaltyHistoryAdapter($m);
        $ublha->recordLoyaltyTransaction($user_brand_points_resource->user_id, getBrandIdFromCurrentContext(), $this->order_id,self::REDEEM_PROCESS_NAME, -$loyalty_payment_results['redemption_amount'],$user_brand_points_resource->points,$user_brand_points_resource->dollar_balance);
    }
    
    function processLoyaltyPaymentResults($user_brand_points_resource,$loyalty_payment_results)
    {
        $this->recordLoyaltyTransaction($user_brand_points_resource, $loyalty_payment_results);
        return $loyalty_payment_results;
    }
    
    function processLoyaltyPayment($amount,$user_brand_points_resource)
    {
        if ($this->chargeLoyaltyAccount($amount)) {
            return $this->loyalty_payment_results;
        } else {
            return $this->processLoyaltyPaymentError($this->loyalty_payment_results);
        }
    }

    function refundLoyaltyPayment()
    {
        return true;
    }

    function processLoyaltyPaymentError()
    {
        false;
    }

    function chargeLoyaltyAccount($redemption_amount)
    {
        $this->loyalty_payment_results['status'] = 'success';
        $this->loyalty_payment_results['response_code'] = 100;
        $this->loyalty_payment_results['transactionid'] = getRawStamp();
        $this->loyalty_payment_results['authcode'] = "noauthcode";
        $this->loyalty_payment_results['redemption_amount'] = $redemption_amount;
        return true;
    }

    function processVioPaymentForRemainder($amount,$user_id,$merchant_id)
    {
        $data = array();
        $data['user_id'] = $user_id;
        $merchant_payment_record = MerchantPaymentTypeMapsAdapter::staticGetRecord(array("splickit_accepted_payment_type_id"=>2000,"merchant_id"=>$merchant_id),'MerchantPaymentTypeMapsAdapter');
        $data['billing_entity_id'] = $merchant_payment_record['billing_entity_id'];
        $data['order_id'] = $this->order_id;
        $this->vio_payment_service = new VioPaymentService($data);
        $this->vio_payment_service->payment_results = $this->vio_payment_service->processPayment($amount);
        return $this->vio_payment_service->processVioResults($this->vio_payment_service->payment_results);
    }

    function recordOrderTransactionsInBalanceChangeTableFromOrderId($order_id,$payment_results)
    {
        $order_resource = CompleteOrder::getBaseOrderDataAsResource($order_id, getM());
        $this->recordOrderTransactionsInBalanceChangeTable($order_resource, $payment_results);
    }

    function recordOrderTransactionsInBalanceChangeTable(&$order_resource,$payment_results)
    {
        // need to record all types of payment nere
        if ($this->loyalty_tax_adjustment > 0.00) {
            // we need to correct the order amounts
            $order_resource->item_tax_amt = $order_resource->item_tax_amt - $this->loyalty_tax_adjustment;
            $order_resource->total_tax_amt = round((float)($order_resource->item_tax_amt + $order_resource->promo_tax_amt + $order_resource->delivery_tax_amt),2);
            //$order_resource->total_tax_amt = $order_resource->total_tax_amt - $this->tax_adjustment;
            $order_resource->grand_total = $order_resource->grand_total - $this->tax_adjustment;
            $order_resource->grand_total_to_merchant = $order_resource->grand_total_to_merchant - $this->tax_adjustment;
        }
        $balance_change_adapter = new BalanceChangeAdapter(getM());
        $balance_change_resource_for_order = parent::recordOrderTransactionsInBalanceChangeTable($order_resource);
        $balance_after_order = $balance_change_resource_for_order->balance_after;
        $loyalty_charge = $this->loyalty_payment_results['redemption_amount'];
        if ($loyalty_charge > 0.00) {
            $ending_balance_after_loyalty_charge = $balance_after_order + $loyalty_charge;
            $transaction_id = $this->getTransactionId();
            if ($bc_resource = $balance_change_adapter->addRow($this->billing_user_id, $balance_after_order, $loyalty_charge, $ending_balance_after_loyalty_charge,$this->process,$this->name, $order_resource->order_id, $transaction_id,'payment with loyalty balance')) {
                $this->balance_change_resource_for_stored_value_payment = $bc_resource;
                $this->setUsersBalance($ending_balance_after_loyalty_charge);
                myerror_log("successful insert of primary order record balance change row");
                $balance_after_order = $ending_balance_after_loyalty_charge;

                // now adjust order amounts
                if ($this->loyalty_tax_adjustment > 0.00) {
                    // if order totals have been adjusted due to payment with loyalty, then we need to record the change on the order. we do this by adding a row to the order_details
                    $order_adapter = new OrderAdapter(getM());
                    $order_id = $order_resource->order_id;
                    $loyalty_tax_adjustment = $this->loyalty_tax_adjustment;
                    $sql = "INSERT INTO Order_Detail (`order_id`,`item_name`,`item_print_name`,`item_total_w_mods`,`item_tax`) VALUES ($order_id,'".self::DISCOUNT_NAME."','".$this->getDiscountDisplay()."',-$loyalty_charge,-$loyalty_tax_adjustment)";
                    $order_adapter->_query($sql);
                    $order_resource->grand_total_to_merchant = $order_resource->grand_total_to_merchant - $loyalty_charge;
                    $order_resource->grand_total = $order_resource->grand_total - $loyalty_charge;
                    $order_resource->order_amt = $order_resource->order_amt - $loyalty_charge;
                } else {
                    $order_resource->grand_total_to_merchant = $order_resource->grand_total_to_merchant - $loyalty_charge;
                }


                if ($this->isCashTypePaymentService()) {
                    $bc_resource = $balance_change_adapter->addRow($this->billing_user_id, $balance_after_order, $order_resource->grand_total_to_merchant, 0,"Cash",$this->name, $order_resource->order_id,'','Remainder Payed With Cash');
                    $this->setUsersBalance(0.00);
                }

                /** if we decid to do this with a promo code that would go here **/

            } else {
                myerror_log("ERROR!  FAILED TO ADD ROW TO BALANCE CHANGE TABLE");
                MailIt::sendErrorEMail('Error thrown in PlaceOrderController','ERROR*****************  FAILED TO ADD ROW TO BALANCE CHANGE TABLE, user_id = '.$user_id.', AFTER RUNNING HEARTLAND STORED VALUE AND UPDATING BALANCE: '.$balance_change_adapter->getLastErrorText());
            }
        } else {
            //so here we need to do something with the balance
            $ending_balance_after_loyalty_charge = $balance_after_order;
            $this->setUsersBalance($ending_balance_after_loyalty_charge);
        }
        if ($this->vio_payment_service->payment_results['status'] == 'success') {
            $this->vio_payment_service->recordCCTransactionInBalaneChangeTable($order_resource,$payment_results,$balance_after_order);
            $cc_charge_amount = $this->vio_payment_service->amount;
            $this->billing_user_resource->balance = $this->billing_user_resource->balance + $cc_charge_amount;
        }
    }
    
    function getTransactionId()
    {
        return getRawStamp();
    }

    static function getDiscountDisplay()
    {
        return LoyaltyBalancePaymentService::$discount_display;
    }
}

class FailedLoyaltyRedemptionException extends BillingException
{
    public function __construct($string) {
        parent::__construct($string);
    }
}

?>