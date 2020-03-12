<?php
class LevelupPaymentService extends SplickitPaymentService
{
    var $user_token;
    var $authorization_header;
    var $url;
    var $levelup_info = array();
    var $sandbox_levelup_location_id = 83;
    CONST SERVICE_NAME = 'Levelup';

    function __construct($data)
    {
        parent::__construct($data);
        if ($this->merchant_id) {
            $this->levelup_info = $this->getMerchantLevelupInfo();
        }
        $this->merchant_token = getProperty("levelup_merchant_token");
        $this->url = getProperty("levelup_url");
    }

    function loadAdditionalDataFieldsIfNeeded($order_data)
    {
        $this->user_token = $order_data['levelup_user_token'];
    }

    function getMerchantLevelupInfo()
    {
        if ($levelup_merchant_info = MerchantLevelupInfoMapsAdapter::staticGetRecord(array("merchant_id"=>$this->merchant_id),'MerchantLevelupInfoMapsAdapter')) {
            if (isNotProd()) {
                $levelup_merchant_info['levelup_location_id'] = $this->sandbox_levelup_location_id;
            }
            return $levelup_merchant_info;
        }
    }

    function buildLevelupOrderPaymentPayloadFromOrderId($order_id)
    {
        $complete_order = $this->getCompleteOrderFromOrderId($order_id);
        $amount_in_pennies = $complete_order['grand_total'] * 100;
        $order['identifier_from_merchant'] = $order_id;
        $order['location_id'] = intval($this->levelup_info['levelup_location_id']);
        $order['spend_amount'] =  intval($amount_in_pennies);
        $order['cashier'] = 'Bob Roberts';
        $order['register'] = "03";
        $order['applied_discount_amount'] = null;
        $order['available_gift_card_amount'] = null;

        foreach ($complete_order['order_details'] as $order_detail) {
            $items[] = $this->buildLevelUpItemArray($order_detail);
        }
        $order['items'] = $items;
        $payload['order'] = $order;
        return $payload;
    }

    function setSkuAndUpcIfAvailable($order_detail,&$item)
    {
        if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($order_detail,'external_id')) {
            $s = explode(':',$order_detail['external_id']);
            if ($s[0]) {
                $item['sku'] = $s[0];
            }
            if ($s[1]) {
                $item['upc'] = $s[1];
            }
        }
    }

    function buildLevelUpItemArray($order_detail)
    {
        $item['charged_price_amount'] = intval($order_detail['item_total_w_mods'] * 100);
        $item['description'] = $order_detail['item_name'];
        $item['name'] = $order_detail['item_name'];
        $item['quantity'] = intval($order_detail['quantity']);
        $item['category'] = $order_detail['menu_type_name'];
        $item['standard_price_amount'] = intval($order_detail['price']*100);
        $this->setSkuAndUpcIfAvailable($order_detail,$item);
        $children = array();
        if ($child = $this->getChildNoteIfItExists($order_detail['note'])) {
            $children[0] = $child;
        }
        if (count($order_detail['order_detail_modifiers']) > 0) {
            foreach ($order_detail['order_detail_modifiers'] as $order_detail_modifier) {
                $children[] = $this->buildLevelUpModifierArray($order_detail_modifier);
            }
        }
        if (count($children) > 0) {
            $item['children'] = $children;
        }
        $item_payload['item'] = $item;
        return $item_payload;
    }

    function getChildNoteIfItExists($note)
    {
        if ($note != null && trim($note) != '') {
            return $this->buildLevelUpItemNoteChildArray(trim($note));
        }
    }

    function buildLevelUpItemNoteChildArray($note)
    {
        $note_array = $this->buildLevelUpChild(0.00,'Special Instructions',$note,1);
        unset($note_array['item']['quantity']);
        return $note_array;
    }


    function buildLevelUpModifierArray($order_detail_modifier)
    {
        return $this->buildLevelUpChild($order_detail_modifier['mod_total_price'],$order_detail_modifier['mod_name'],$order_detail_modifier['mod_name'],$order_detail_modifier['mod_quantity']);
    }

    function buildLevelUpChild($charged_price,$name,$description,$quantity)
    {
        $child['charged_price_amount'] = intval($charged_price * 100);
        $child['name'] = $name;
        $child['quantity'] = intval($quantity);
        $child['description'] = $description;
        $child_item['item'] = $child;
        return $child_item;
    }

    function processPayment($amount)
    {
        $data = $this->buildLevelupOrderPaymentPayloadFromOrderId($this->order_id);
        $headers = $this->createAuthHeader($this->merchant_token,$this->user_token);
        $levelup_payment_results = $this->curlIt($this->url, $data,$headers);
        return $this->processLevelupResults($levelup_payment_results);
    }

    function creditVoidTransaction($balance_change_row, $refund_amt, $merchant_resource)
    {
        if ($refund_amt != ''.$balance_change_row['charge_amt']) {
            throw new Exception("Error. Level up cannot do partial refunds. Must see store for individual item refunds.");
        }
        $response = $this->processRefundForUUID($balance_change_row['cc_transaction_id']);
        if ($response['status'] == 'success') {
            // for backward compataboility
            $response['response_code'] = 100;
            $this->refund = true;
            $this->process = 'REFUND';
            $this->process_string = 'LevelupRefund';
        }
        return $response;

    }

    function processRefundForUUID($uuid)
    {
        $this->url = $this->url."/$uuid/refund";
        $headers = $this->createAuthHeaderForRefund($this->merchant_token);
        $levelup_payment_results = $this->curlIt($this->url,$data,$headers);
        return $this->processLevelupRefundResults($levelup_payment_results);
    }

    function createAuthHeader($merchant_token,$user_token)
    {
        return 'Authorization: token merchant="'.$merchant_token.'",user="'.$user_token.'"';
    }

    function createAuthHeaderForRefund($merchant_token)
    {
        return 'Authorization: token merchant="'.$merchant_token.'"';
    }

    function processLevelupRefundResults($levelup_payment_results)
    {
        if ($levelup_payment_results['status'] == 'success') {
            $levelup_payment_results = array_merge($levelup_payment_results,$levelup_payment_results['order']);
            unset($levelup_payment_results['order']);
            return $levelup_payment_results;
        } else {
            return $this->processLevelupError($levelup_payment_results);
        }
    }

    function processLevelupResults($levelup_payment_results)
    {
        // if successfull set the transaction_id,processor_used,authcode
        if ($levelup_payment_results['status'] == 'success') {
            return $this->formatSuccessForBackwardsCompatabilty($levelup_payment_results);

        } else {
            return $this->processLevelupError($levelup_payment_results);
        }
    }

    function formatSuccessForBackwardsCompatabilty($levelup_payment_results)
    {
        $results = $levelup_payment_results['order'];
        $results['response_code'] = 100;
        $results['transactionid'] = $results['uuid'];
        return $results;
    }

    function processLevelupError($levelup_payment_results)
    {
        $levelup_payment_results['status'] = 'failure';
        if (isset($levelup_payment_results[0]["error"])) {
            $levelup_payment_results['error'] = $levelup_payment_results[0]["error"];
        }
        if ('base' == $levelup_payment_results['error']['property']) {
            $levelup_payment_results['response_text'] = $levelup_payment_results['error']['message'];
        } else if ('user_token' == $levelup_payment_results['error']['property']) {
                $levelup_payment_results['response_text'] = "We're sorry, but there is a problem with your LevelUp authentication and payment cannot be processed at this time.";
        } else if (substr_count($this->url,'refund') && $levelup_payment_results['http_code'] == 404) {
            $levelup_payment_results['response_text'] = "Unable to refund, Levelup UUID not validated by Levelup server.";
        } else {
            $levelup_payment_results['response_text'] = "We're sorry, there is a configuration problem with this merchant. We have now been notified and will address it immediately.";
        }
        return $levelup_payment_results;
    }

    function processLevelupRefundError($levelup_payment_results)
    {
        $levelup_payment_results["error"] = $levelup_payment_results[0]["error"];

    }

    function curlIt($endpoint,$data,$headers)
    {
        myerror_logging(3,"about to curl Levelup to endpoint: ".$endpoint);
        logData($data, "levelup payload",5);
        $response = LevelupPaymentCurl::curlIt($endpoint,$data, $headers);
        $this->curl_response = $response;
        $result_array = $this->processCurlResponse($response);
        return $result_array;
    }

    function recordOrderTransactionsInBalanceChangeTable($order_resource,$payment_results)
    {
        logData($payment_results, "payment results",5);
        $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
        $balance_change_resource_for_order = parent::recordOrderTransactionsInBalanceChangeTable($order_resource,null);
        $balance_after_order = $balance_change_resource_for_order->balance_after;
        $ending_balance_after_levelup_charge = $balance_after_order + $this->amount;
        $billing_user_id = (isset($payment_results['user_id'])) ? $payment_results['user_id'] : $this->billing_user_id;
        if ($bc_resource = $balance_change_adapter->addRow($billing_user_id, $balance_after_order, $this->amount, $ending_balance_after_levelup_charge,LevelupPaymentService::SERVICE_NAME ,LevelupPaymentService::SERVICE_NAME, $order_resource->order_id, $payment_results['transactionid'], 'uuid='.$payment_results['transactionid'])) {
            $this->balance_change_resource_for_levelup_payment = $bc_resource;
            $this->setUsersBalance($ending_balance_after_levelup_charge);
            myerror_log("successful insert of primary order record balance change row");
        } else {
            myerror_log("ERROR!  FAILED TO ADD ROW TO BALANCE CHANGE TABLE");
            MailIt::sendErrorEMail('Error thrown in PlaceOrderController','ERROR*****************  FAILED TO ADD ROW TO BALANCE CHANGE TABLE, user_id = '.$user_id.', AFTER RUNNING THEIR Levelup Account AND UPDATING BALANCE: '.$balance_change_adapter->getLastErrorText());
        }
    }

}
?>