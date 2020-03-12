<?php

class PunchLoyaltyController extends LoyaltyController
{
    public $service;
    protected $payload_array;
    public $redemption_event_processed = false;
    private $action;

    function __construct($mimetypes, $user, $request, $log_level = 0)
    {
        parent::__construct($mimetypes, $user, $request, $log_level);
        $this->service = new PunchLoyaltyService();
        $this->auto_join = false;
    }
    
    function sendLoyaltyOrderEvent($complete_order,$points)
    {
        myerror_log("starting send loyalty order event",3);
        $this->action = 'earn';
        if ($payload = $this->getPunchPayloadArrayForOrder($complete_order)) {
            $punch_authentication_token = $this->getPunchAuthenticationToken();
            $this->service_response = $this->service->rewardPoints($payload,$punch_authentication_token);
            logData($this->service_response,"Punch Reward Points Response",3);
        }
    }

    function refundLoyaltyRedemtion($loyalty_payment_results,$reason)
    {
        $data['redemption_id'] = $loyalty_payment_results['redemption_id'];
        $authentication_token = $this->getPunchAuthenticationToken();
        $data['authentication_token'] = $authentication_token;
        if ($reason) {
            $data['reason'] = $reason;
        }
        return $this->service->voidRedemption($data,$authentication_token);
    }
    
    function sendLoyaltyRemdemptionEvent($complete_order,$redemption_amount)
    {
        $this->action = 'redeem';
        if ($payload = $this->getPunchPayloadArrayForRedemption($complete_order,$redemption_amount)) {
            $this->payload_array = $payload;
            $punch_authentication_token = $this->getPunchAuthenticationToken();
            $response = $this->service->redemptionPurchase($payload,$punch_authentication_token);
            if ($this->service->isSuccessfulResponse($response)) {
                if ($array = json_decode($response['raw_result'],true)) {
                    $response = array_merge($response,$array);
                    logData($response,"Punch Reward Points Response",3);
                    $this->service_response = $response;
                    if ($response['http_code'] == 200) {
                        $response['status'] = 'success';
                        $response['response_code'] = 100;
                        if ($payload['receipt_amount'] > $response['redemption_amount']) {
                            $response['remainder_to_bill'] = $complete_order['grand_total'] - $response['redemption_amount'];
                        }
                        $this->redemption_event_processed = true;
                    } else {
                        $response['remainder_to_bill'] = $complete_order['grand_total'];
                    }

                }
            } else {
                $response['status'] = 'failure';
                $response['response_code'] = 400; // we dont get a code from punch so i need something other then 100;
            }
            return $response;
        }
    }

    function getPunchAuthenticationToken()
    {
        if ($info = $this->getLocalAccountInfo()) {
            $loyalty_number = $info['loyalty_number'];
            $l = explode(':',$loyalty_number);
            return $l[1];
        }
    }

    function setOrderDetailFromAlohaInfoRecord(&$order_detail,$record)
    {
        $order_detail['moes_item_name'] = $record['name'];
        $order_detail['moes_remote_id'] = $record['aloha_id'];
        $order_detail['moes_group_name'] = $record['major_group'];
    }

    function getPunchPayloadArrayForRedemption($complete_order,$redemption_amount)
    {
        $payload = $this->getPunchPayloadArrayForOrder($complete_order);
        $payload["discount_type"] = "discount_amount";
        $payload["redeemed_points"] = "".floatval($redemption_amount);
        $this->payload_array = $payload;
        return $payload;
    }

    function getPunchPayloadArrayForOrder($complete_order)
    {
        $discount_amount = 0.00;
        myerror_log("starting getPunchPayloadArrayForOrder()",3);
        $amaia = new AdmMoesAlohaInfoAdapter($m);
        $payload_array = array();
        $payload_array['cc_last4'] = $complete_order['user']['last_four'];
        $payload_array['store_number'] = $complete_order['merchant']['merchant_external_id'];
        $payload_array['employee_id'] = 1;
        $payload_array['employee_name'] = "Splickit Server";
        $payload_array['menu_items'] = array();
        foreach ($complete_order['order_details'] as $order_detail) {
            $satisfied = false;
            if ($item_id = $order_detail['item_id']) {
                if ($records = $amaia->getRecords(array("item_id"=>$item_id))) {
                    myerror_log("we have records for this item",3);
                    logData($records,"alho data for moes item",3);
                    if (count($records) == 1 && strtolower($records[0]['rule']) == 'item') {
                        $this->setOrderDetailFromAlohaInfoRecord($order_detail,$records[0]);
                        $satisfied = true;
                    } else {
                        foreach ($order_detail['order_detail_complete_modifier_list_no_holds'] as $modifier) {
                            foreach ($records as $record) {
                                if ($record['modifier_id'] == $modifier['modifier_item_id']) {
                                    myerror_log("we have a modifier id match",3);
                                    $this->setOrderDetailFromAlohaInfoRecord($order_detail,$record);
                                    $satisfied = true;
                                    break;
                                }
                            }
                            if ($satisfied) {
                                break;
                            }
                        }
                        if (! $satisfied) {
                            foreach ($records as $record) {
                                if ($record['size_id'] == $order_detail['size_id']) {
                                    myerror_log("we have a size id match",3);
                                    $this->setOrderDetailFromAlohaInfoRecord($order_detail,$record);
                                    $satisfied = true;
                                    break;
                                }
                            }
                        }
                    }
                    // now check for premium modifier for this item
                    $modifier_nodes = array();
                    foreach ($order_detail['order_detail_complete_modifier_list_no_holds'] as $modifier) {
                        if ($record = $amaia->getRecord(array("modifier_id"=>$modifier['modifier_item_id'],"rule"=>'mod'))) {
                            if ($record['modifier_id'] == $modifier['modifier_item_id']) {
                                myerror_log("we have a modifier id match for premium", 3);
                                $modifier_price = floatval($modifier['mod_total_price']);
                                $modifier_nodes[] = $this->getPunchItemArrayForPayload($record['name'],"".$modifier['mod_quantity'],"".number_format($modifier_price,2), "M","".$record['aloha_id'],"800",$record['major_group']);
                                $order_detail['item_total_w_mods'] = $order_detail['item_total_w_mods'] - $modifier_price;
                            }
                        }
                    }

                    if ($satisfied) {
                        $item = $this->getPunchItemArrayForPayload($order_detail['moes_item_name'],"".$order_detail['quantity'],"".number_format(floatval($order_detail['item_total_w_mods']),2), "M","".$order_detail['moes_remote_id'],"800",$order_detail['moes_group_name']);
                        $payload_array['menu_items'][] = $item;
                    }
                    foreach ($modifier_nodes as $modifier_node) {
                        $payload_array['menu_items'][] = $modifier_node;
                    }
                }
            } else if ($order_detail['item_name'] == LoyaltyBalancePaymentService::DISCOUNT_NAME) {
                $discount_amount = - $order_detail['item_total_w_mods'];
                $payload_array['menu_items'][] = $this->getPunchItemArrayForPayload('Redemption Discount','1',"".number_format(floatval($discount_amount),2),"D",'00','00','00');
            }
        }
        $balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$complete_order['order_id']),'BalanceChangeAdapter');
        if ($discount_amount == 0) {
            if ($balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$complete_order['order_id'],"process"=>'PunchLoyaltyBalancePayment'),'BalanceChangeAdapter')) {
                $bcr = array_pop($balance_change_records);
                $discount_amount = round((float)$bcr['charge_amt'],2);
                $payload_array['menu_items'][] = $this->getPunchItemArrayForPayload('Redemption Discount','1',"".number_format(floatval($discount_amount),2),"D",'00','00','00');
            }
        }
        if (count($payload_array['menu_items']) > 0) {
            $payload_array['receipt_amount'] = floatval($complete_order['grand_total']-$discount_amount);
            $payload_array['subtotal_amount'] = floatval(number_format($complete_order['order_amt']-$discount_amount,2));
            //$payload_array['payable'] = floatval(number_format($complete_order['grand_total'],2));
            $payload_array['receipt_datetime'] = $this->getPunchDateFormat($complete_order['order_dt_tm']);
            $payload_array['transaction_no'] = intval($complete_order['order_id']);
            $payload_array['external_uid'] = $complete_order['ucid'];
            $payload_array['client'] = $this->service->getPunchClientId();
            logData($payload_array,"Punch Payload",3);
            $this->payload_array = $payload_array;
            return $payload_array;
        } else {
            return null;
        }
    }

    function getPunchItemArrayForPayload($item_name,$item_quantity,$item_total_w_mods,$punch_item_type,$punch_item_id,$punch_family,$punch_major_group)
    {
        $item = array();
        $item['item_name'] = "$item_name";
        $item['item_qty'] = "$item_quantity";
        $item['item_amount'] = "$item_total_w_mods";
        $item['menu_item_type'] = "$punch_item_type";
        $item['menu_item_id'] = "$punch_item_id";
        $item['menu_family'] = "$punch_family";
        $item['menu_major_group'] = "$punch_major_group";
        return $item;
    }


    function getPunchDateFormat($time_stamp)
    {
        return date('Y-m-d',$time_stamp).'T'.date('H:i:sP',$time_stamp);
    }

    function getAccountInfoForUserSession()
    {
        //return false;
        if ($user_brand_points_map_resource = $this->getLocalAccountInfoAsResource()) {
            $loyalty_number = $user_brand_points_map_resource->loyalty_number;
            $l = explode(":",$loyalty_number);
            if ($remote_balance_info = $this->service->getBalance($l[1])) {
                $user_brand_points_map_resource->points = $remote_balance_info['points_balance'];
                $user_brand_points_map_resource->dollar_balance = $remote_balance_info['banked_rewards'];
                $user_brand_points_map_resource->save();
                $remote_balance_info['loyalty_number'] = $loyalty_number;
                $remote_balance_info['points'] = '$'.$remote_balance_info['banked_rewards'];
                $remote_balance_info['usd'] = $remote_balance_info['banked_rewards'];
                $remote_balance_info['dollar_balance'] = $remote_balance_info['banked_rewards'];
                //place holder for now
                $remote_balance_info['loyalty_transactions'] = array();
                return $remote_balance_info;
            }
        }
        return null;
    }

    public function getPayloadArray()
    {
        return $this->payload_array;
    }
}

?>