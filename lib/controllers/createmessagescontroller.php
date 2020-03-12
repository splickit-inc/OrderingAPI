<?php

/**
 * Class CreateMessagesController: Used to create all the messages for an order
 */
class CreateMessagesController
{

    var $pulled_message_types = array("G","H","W","S","O","U","P","J","R");
    var $merchant;
    var $user;
    var $immediate_delivery = false;
    var $isCatering = false;
    var $isDelivery = false;
    private $current_time;

    function __construct($merchant)
    {
        if ($this->merchant = $merchant) {
            $this->immediate_delivery = (strtoupper($merchant['immediate_message_delivery']) == 'Y');
        }
        $this->current_time = time();
    }

    /**
     * @param $order Order
     */
    function setImmediateDeliveryIfOrderTypeIsDeliveryOrCatering($order)
    {
        if ($order->isDeliveryOrder()) {
            $this->isDelivery = true;
            $this->immediate_delivery = true;
        } else if ($order->isCateringOrder()) {
            $this->isCatering = true;
            $this->immediate_delivery = true;
        }
    }

    function createOrderMessagesFromOrderInfo($order_id,$merchant_id,$lead_time_for_order_in_minutes,$pickup_timestamp)
    {
        $merchant_message_map_adapter = new MerchantMessageMapAdapter(getM());
        $merchant_message_maps = $merchant_message_map_adapter->getRecords(["merchant_id"=>$merchant_id]);
        foreach ($merchant_message_maps as $merchant_message_map) {
            if (! $this->createTheOrderMessage($order_id,$merchant_message_map,$lead_time_for_order_in_minutes,$pickup_timestamp)) {
                MailIt::sendErrorEmailSupport("MESSAGE CREATION FAILURE FOR ORDER ID: $order_id","merchant_id: $merchant_id.  Message could not be created for format: ".$merchant_message_map['message_format']);
            }
        }
        $this->createShaddowMessages($order_id);
        return true;
    }

    function createShaddowMessages($order_id)
    {
        if (strtolower(getProperty('new_shadow_device_on')) == 'true') {
            if ($this->shouldCreateShaddowMessageForOrderId($order_id)) {
                $merchant_message_history_adapter = new MerchantMessageHistoryAdapter(getM());
                if (substr(getProperty('new_shadow_message_type'),0,1) == 'G') {
                    $number = getProperty('shadow_printer_number');
                    $merchant_message_history_adapter->createMessage(10, $order_id, 'T', "$number", time(), 'A', 'firmware=11.0', '***', 'N');
                    $id = $merchant_message_history_adapter->createMessage(10, $order_id, 'GUC', "$number", time(), 'O', 'firmware=11.0;merch=HQ', null, 'P');
                } else {
                    $id = $merchant_message_history_adapter->createMessage(10, $order_id,getProperty('new_shadow_message_type'),"delivery_addr", time(), 'O', null, null, 'N');
                }
            }
        }
        return $id;
    }

    function shouldCreateShaddowMessageForOrderId($order_id)
    {
        return ($order_id % getProperty('new_shadow_message_frequency')) == 0;
    }

    function createTheOrderMessage($order_id,$merchant_message_map,$lead_time_for_order_in_minutes,$pickup_timestamp)
    {
        $message_text = $merchant_message_map['message_text'];
        $merchant_message_history_adapter = new MerchantMessageHistoryAdapter(getM());
        $merchant_message_history_adapter->pickup_timestamp = $pickup_timestamp;
        $next_message_dt_tm = $this->calculateNextMessageDtTm($pickup_timestamp,$lead_time_for_order_in_minutes,$merchant_message_map['delay']);
        if (getProperty('bypass_portal_message')) {
            $portal_order_json = 'dummy';
        } else {
            $full_order = CompleteOrder::staticGetCompleteOrder($order_id, getM());
            $portal_order_array = $this->createOrderDataForPortalDisplayFromCompleteOrder($full_order);
            $portal_order_json = json_encode($portal_order_array);
        }


        if (substr($merchant_message_map['message_format'],0,1) == 'G') {
            //get sms number
            $merchant_message_map['delivery_addr'] = MerchantMessageMapAdapter::getSMSNumberForMerchant($merchant_message_map['merchant_id']);
        } else if (substr($merchant_message_map['message_format'],0,1) == 'P') {
            //create json for portal
            $message_text = $portal_order_json;
        }
        // do merchant id swap here for admin users where user_id < 100 for pulled message types
        if ($this->isCatering) {
            if ($catering_info = MerchantCateringInfosAdapter::getInfoAsResourceByMerchantId($this->merchant['merchant_id'])) {
                if ($new_destination = $catering_info->special_merchant_message_destination) {
                    $merchant_message_map['delivery_addr'] = $new_destination;
                    if ($new_format = $catering_info->special_merchant_message_format) {
                        $merchant_message_map['message_format'] = $new_format;
                    }
                }
            }
        }
        $locked = $this->getLockedForMessage($merchant_message_map['message_format']);
        return $merchant_message_history_adapter->createMessage($merchant_message_map['merchant_id'],$order_id,$merchant_message_map['message_format'],$merchant_message_map['delivery_addr'],$next_message_dt_tm,$merchant_message_map['message_type'],$merchant_message_map['info'],$message_text,$locked,0,0,$portal_order_json);
    }

    function createOrderDataForPortalDisplayFromCompleteOrder($complete_order)
    {
        $portal_order_data = array(
            "order_id" => $complete_order['order_id'],
            "order_dt_tm" => intval($complete_order['order_dt_tm']),
            "user_id" => $complete_order['user_id'],
            "order_type" => $complete_order['order_type'],
            "pickup_dt_tm" => intval($complete_order['pickup_dt_tm']),
            "order_amt" => $complete_order['order_amt'],
            "total_tax_amt" => $complete_order['total_tax_amt'],
            "promo_code" => $complete_order['promo_code'],
            "promo_amt" => $complete_order['promo_amt'],
            "delivery_amt" => $complete_order['delivery_amt'],
            "grand_total" => $complete_order['grand_total'],
            "tip_amt" => $complete_order['tip_amt'],
            "cash" => "".$complete_order['cash'],
            "note" => $complete_order['note'],
            "order_qty" => $complete_order['order_qty'],
            "full_name" => $complete_order['customer_full_name'],
            "user_email" => $complete_order['user_email'],
            "user_phone_no" => "".$complete_order['user_phone_no'],
            "pickup_time" => "".$complete_order['pickup_time'],
            "pickup_day"=>$complete_order['pickup_day'],
            "pickup_day_of_month"=>$complete_order['pickup_day_of_month'],
            'pickup_month'=>$complete_order['pickup_month']
        );

        if($complete_order['user_delivery_location_id']){
            if($delivery_data = UserDeliveryLocationAdapter::staticGetRecordByPrimaryKey($complete_order['user_delivery_location_id'], "UserDeliveryLocationAdapter")){
                $address_data = array_map(
                    function($item) { return trim($item); },
                    array($delivery_data["business_name"], $delivery_data["address1"], $delivery_data["address2"], $delivery_data["city"], $delivery_data["state"], $delivery_data["zip"])
                );
                $address = implode(", ",array_filter($address_data, function($item) { return $item != null && $item !== ''; } ) );
                $portal_order_data["order_type"] = 'Delivery';
                $portal_order_data["requested_delivery_time"] = $complete_order['requested_delivery_time'];
                $portal_order_data["delivery_address"] = $address;
                $portal_order_data["delivery_notes"] = $delivery_data["instructions"];
                $portal_order_data["delivery_phone"] = $delivery_data["phone_no"];
            }
        } else {
            $portal_order_data["order_type"] = 'Pickup';
        }

        $order_items = array();
        foreach($complete_order['order_details'] as $item) {
            $order_item = array(
                "order_detail_id" => "".$item["order_detail_id"],
                "external_id" => "".$item["external_id"],
                "size_name" => $item['size_name'],
                "item_name" => $item["item_name"],
                "note" => "".$item["note"],
                "quantity" => "".$item["quantity"],
                "price" => "".$item["price"],
                "item_total" => "".$item["item_total"],
                "item_total_w_mods" => "".$item["item_total_w_mods"],
                "item_tax" => "".$item["item_tax"]
            );

            $order_item["order_detail_modifiers"] = $this->buildOrderDetailModifiers($item["order_detail_complete_modifier_list_no_holds"],true);
            $order_item['order_detail_held_modifiers'] = $this->buildOrderDetailModifiers($item['order_detail_hold_it_modifiers']);
            $order_item['order_detail_sides'] = $this->buildOrderDetailModifiers($item['order_detail_sides']);

            if (isset($item['amount_off_from_points']) && $item['amount_off_from_points'] > 0) {
                $order_item['price'] = "".$order_item['price'] - $item['amount_off_from_points'];
                $order_item['item_total'] = "".$order_item['item_total'] - $item['amount_off_from_points'];
                if ($order_item['price'] == 0) {
                    $order_item['price'] = "0.00";
                    $order_item['item_total'] = "0.00";
                }
            }
            array_push($order_items, $order_item);
        }
        $portal_order_data['order_items'] = $order_items;


        return $portal_order_data;
    }

    private function buildOrderDetailModifiers($modifiers = array(),$no_sides = false){
        $item_modifiers = array();

        foreach ($modifiers as $modifier){
            if ($no_sides && $modifier['modifier_type'] == 'S') {
                continue;
            }
            $mod = null;
            $quantity = $modifier['mod_quantity'];
            if ($quantity > 0) {
                if ($modifier['comes_with']=='Y' && $modifier['hold_it'] == 'N') {
                    $quantity_included = 1;
                } else {
                    $quantity_included = 0;
                }
                $mod = array(
                    "order_detail_mod_id" => $modifier["order_detail_mod_id"],
                    "external_id" => "".$modifier["external_id"],
                    "mod_name" => $modifier["mod_name"],
                    "mod_quantity" => $quantity,
                    "mod_price" => "".$modifier["mod_price"],
                    "mod_total_price" => "".$modifier["mod_total_price"],
                    "quantity_included" => "".$quantity_included
                );
            } else if ($quantity == 0 && $modifier['hold_it'] == 'Y') {
                //hold it modifier
                $mod = array(
                    "order_detail_mod_id" => $modifier["order_detail_mod_id"],
                    "external_id" => "".$modifier["external_id"],
                    "mod_name" => $modifier["mod_print_name"]
                );
            }
            if ($mod) {
                $item_modifiers[] = $mod;
            }
        }
        return $item_modifiers;
    }


    function calculateNextMessageDtTm($pickup_timestamp,$lead_time_for_order_in_minutes,$delay_in_minutes)
    {
        $date_string = date("Y-m-d H:i:s",$pickup_timestamp);

        myerror_log("Calculating next time for message with  pickup: $date_string, lead_time_for_Order_in_Minutes: $lead_time_for_order_in_minutes, delay_in_minutes: $delay_in_minutes");
        if ($this->immediate_delivery) {
            return $this->current_time;
        } else {
            $next_message_time_stamp = $pickup_timestamp - ($lead_time_for_order_in_minutes*60) + ($delay_in_minutes*60);
            $next_message_date_string = date("Y-m-d H:i:s",$next_message_time_stamp);
            if ($next_message_time_stamp < (time()-200)) {
                $diff_in_minutes = (time() - $next_message_time_stamp)/60;
                myerror_log("NEXT MESSAGE TIME IN THE PAST:  $next_message_date_string    RESET TO CURRENT TIME:   ".date("Y-m-d H:i:s")."     REQUEST DATA: ".$_SERVER['request_data_info_string']);
                MailIt::sendErrorEmail("NEXT MESSAGE TIME IN THE PAST","we have just produced a next message time that is $diff_in_minutes minutes in the past: $next_message_date_string .     REQUEST DATA: ".$_SERVER['request_data_info_string']);
                $next_message_time_stamp = time();
            } else if ($next_message_time_stamp < time()) {
                myerror_log("NEXT MESSAGE TIME IN THE PAST slightly:  $next_message_date_string    RESET TO CURRENT TIME:   ".date("Y-m-d H:i:s")."     REQUEST DATA: ".$_SERVER['request_data_info_string'],3);
                $next_message_time_stamp = time();
            }
            return $next_message_time_stamp;
        }
    }

    function getLockedForMessage($full_message_format)
    {
        if($this->isPulledType($this->getBaseFormat($full_message_format))) {
            return 'P';
        } else {
            return 'N';
        }
    }

    function isPulledType($base_format)
    {
        return in_array($base_format,$this->pulled_message_types);
    }

    function getBaseFormat($full_message_format)
    {
        return substr($full_message_format,0,1);
    }

    function setCurrentTime($timestamp)
    {
        $this->current_time = $timestamp;
    }
}
?>