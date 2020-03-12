<?php

/**
 * Created by PhpStorm.
 * User: Elvis
 * Date: 8/18/16
 * Time: 11:32 AM
 */
class EmagineController extends MessageController
{
    var $format_name = 'emagine';
    var $service;

    function EmagineController($mt, $u, &$r, $l = 0)
    {
        parent::MessageController($mt, $u, $r, $l);
        
        $this->service = new EmagineService();
    }
    
    public function populateMessageData($message_resource)
    {
        $resource = parent::populateMessageData($message_resource);
        $skin = SkinAdapter::staticGetRecordByPrimaryKey($resource->skin_id,'SkinAdapter');
        if ($skin['brand_id'] > 0) {
            if ($loyalty_resource = UserBrandPointsMapAdapter::getUserBrandPointsMapRecordForUserBrandCombo($resource->user_id,$skin['brand_id'])) {
                $resource->loyalty_number = $loyalty_resource->loyalty_number;
            }
        }


        $this->service->setStoreData(array("merchant_id" => $resource->merchant_id));
        $order_data =  $this->buildEmagineOrderObject($resource);
        $data_for_template = array_merge($this->service->getHeaders(), array('order' => $order_data));

        $resource->emagine_order = json_encode($data_for_template);
        return $resource;
    }

    protected function send($message_body)
    {
        $message_body = str_ireplace('"null"','"none"',$message_body);
        myerror_log("Emagine sending with body: " . $message_body);
        if ($response = $this->service->send($message_body)) {
            $this->message_resource->message_text = $message_body;
            $this->message_resource->response = $response['raw_result'];
            return $response;
        }
    }

    private function buildEmagineOrderObject($resource){
        $emagine_grand_total = $resource->grand_total - $resource->trans_fee_amt;
        $note = $resource->note == null || $resource->note == 'null' ? ' ' : $resource->note ;
        $payment_types = [];
        if (isset($resource->group_order_payments)) {
            $payment_types[0]['payment_type'] = "Credit Card";
            $resource->cash = 'N';
        } else {
            foreach ($resource->payments as $payment) {
                $process = $payment['process'];
                myerror_log("getting charge process. checking: $process",5);
                if ($process == 'CCpayment' || $process == 'Authorize') {
                    $process = 'Credit Card';
                    $payment_types[] = ["payment_type"=>$process,"amount"=>$payment['charge_amt']];
                } else if ($process == 'LevelUpPassThrough') {
                    $process = 'Level Up Pass Through';
                    $payment_types[] = ["payment_type"=>$process,"amount"=>$payment['charge_amt']];
                }
            }
        }

        $emagine_order = array(
            "order_id" => "".$resource->order_id,
            "order_dt_tm" => intval($resource->order_dt_tm),
            "user_id" => "".$resource->user_id,
            "pickup_dt_tm" => intval($resource->pickup_dt_tm),
            "order_amt" => "".$resource->order_amt,
            "total_tax_amt" => "".$resource->total_tax_amt,
            "promo_code" => "".$resource->promo_code,
            "promo_amt" => "".$this->getEmaginePromoAmount($resource),
            "delivery_amt" => ''.$resource->delivery_amt,
            "grand_total" => "$emagine_grand_total",
            "tip_amt" => "".$resource->tip_amt,
            "tender_type" => $payment_types[0]['payment_type'],
//            "payment_types" => $payment_types,
            "customer_donation_amt" => "".$resource->customer_donation_amt,
            "cash" => "".$resource->cash,
            "note" => ''.$note,
            "order_qty" => "".$resource->order_qty,
            "full_name" => "".$resource->customer_full_name,
            "user_email" => "".$resource->user_email,
            "user_phone_no" => "".$resource->user_phone_no,
            "pickup_time" => "".$resource->pickup_time,
            "pickup_date" => "".$resource->pickup_date,
            "order_date" => "".$resource->order_date,
            "order_time" => "".$resource->order_time,
            "loyalty_number" => "".isset($resource->loyalty_number) ? $resource->loyalty_number : ''
        );

        if($resource->user_delivery_location_id){

            if($delivery_data = UserDeliveryLocationAdapter::staticGetRecordByPrimaryKey($resource->user_delivery_location_id, "UserDeliveryLocationAdapter")){

                $address_data = array_map(
                    function($item) { return trim($item); },
                    array($delivery_data["business_name"], $delivery_data["address1"], $delivery_data["address2"], $delivery_data["city"], $delivery_data["state"], $delivery_data["zip"])
                );

                $address = implode(", ",array_filter($address_data, function($item) { return $item != null && $item !== ''; } ) );

                $emagine_order["requested_delivery_time"] = $resource->requested_delivery_time;
                $emagine_order["delivery_address"] = $address;
                $emagine_order["delivery_notes"] = $delivery_data["instructions"];
                $emagine_order["delivery_phone"] = $delivery_data["phone_no"];

            }
        }

        $emagine_order['order_details'] = $this->buildOrderDetail($resource->order_details);
        //$emagine_order['discounts'] = array();
        logData($emagine_order,"emagine order",3);
        return $emagine_order;
    }

    function getEmaginePromoAmount($resource)
    {
        return "".number_format(-$resource->promo_amt,2);
    }

    private function buildOrderDetail($items = array()){
        myerror_log("building order details with format: ".$this->message_resource->message_format);

        $order_items = array();
        foreach($items as $item) {
            $order_item = array(
                "order_detail_id" => "".$item["order_detail_id"],
                "external_id" => "".$item["external_id"],
                "item_name" => $this->message_resource->message_format == 'NE' ? "".$item["item_print_name"] : "".$item["item_name"],
                "note" => "".$item["note"],
                "quantity" => "".$item["quantity"],
                "price" => "".$item["price"],
                "item_total" => "".$item["item_total"],
                "item_total_w_mods" => "".$item["item_total_w_mods"],
                "item_tax" => "".$item["item_tax"]
            );
            if ($this->message_resource->message_format == 'N') {
                $order_item["order_detail_modifiers"] = $this->buildOrderDetailModifiers($item["order_detail_complete_modifier_list_no_holds"]);
            } else  if ($this->message_resource->message_format == 'NE') {
                $added_modifiers = $this->buildOrderDetailModifiers($item["order_detail_added_modifiers"]);
                $held_modifiers = $this->buildOrderDetailModifiers($item['order_detail_hold_it_modifiers']);
                $added_side_modifiers = $this->buildOrderDetailModifiers($item['order_detail_sides']);
                $complete_mods = array_merge($added_modifiers,$held_modifiers,$added_side_modifiers);
                logData($complete_mods,"the complete mods");
                $order_item["order_detail_modifiers"] = $complete_mods;
            }
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
        return $order_items;
    }

    private function buildOrderDetailModifiers($modifiers = array()){
        $item_modifiers = array();

        foreach ($modifiers as $modifier){
            // first test for blank external which will blow up Emagine
            if (isStringFieldNullOrEmptyOnArray($modifier, 'external_id')) {
                continue;
            }
            $quantity = $modifier['mod_quantity'];
            if ($modifier['comes_with'] == 'Y' && $this->message_resource->message_format == 'N') {
                $comes_with_mod = array(
                    "order_detail_mod_id" => $modifier["order_detail_mod_id"]."F",
                    "external_id" => $modifier["external_id"],
                    "mod_name" => $modifier["mod_name"],
                    "mod_quantity" => "1",
                    "mod_price" => "0.00",
                    "mod_total_price" => "0.00"
                );
                array_push($item_modifiers, $comes_with_mod);
                $quantity = $quantity-1;
            }
            if ($quantity > 0) {
                $mod = array(
                    "order_detail_mod_id" => "".$modifier["order_detail_mod_id"],
                    "external_id" => "".$modifier["external_id"],
                    "mod_name" => $this->message_resource->message_format == 'NE' ? "".$modifier["mod_print_name"] : "".$modifier["mod_name"],
                    "mod_quantity" => "$quantity",
                    "mod_price" => "".$modifier["mod_price"],
                    "mod_total_price" => "".$modifier["mod_total_price"]
                );
                array_push($item_modifiers, $mod);
            } else if ($quantity == 0 && $modifier['hold_it'] == 'Y' && $this->message_resource->message_format == 'NE') {
                //hold it modifier
                $mod = array(
                    "order_detail_mod_id" => "".$modifier["order_detail_mod_id"],
                    "external_id" => "".$modifier["external_id"],
                    "mod_name" => "NO ".$modifier["mod_print_name"],
                    "mod_quantity" => "0",
                    "mod_price" => "0.00",
                    "mod_total_price" => "0.00"
                );
                array_push($item_modifiers, $mod);
            }
        }

        return $item_modifiers;
    }
}