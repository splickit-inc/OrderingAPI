<?php
Class XoikosController extends MessageController
{
    protected $format_name = 'xoikos';

    function XoikosController($mt,$u,&$r,$l = 0)
    {
        parent::MessageController($mt,$u,$r,$l);
        $this->representation = '/order_templates/xoikos/place_order_xoikos.xml';
    }

    protected function send($message_body)
    {
        myerror_log("Xoikos sending with body: ".cleanUpDoubleSpacesCRLFTFromString($message_body));
        $xoikos_service = new XoikosService(array("merchant"=>$this->merchant));
        if ($response = $xoikos_service->send($message_body)) {
            $this->message_resource->message_text = $message_body;
            $this->message_resource->response = $response['raw_result'];
            return $response;
        }
    }

    public function populateMessageData($message_resource)
    {
        $resource = parent::populateMessageData($message_resource);
        if ($this->full_order['cash'] == 'Y') {
            $resource->set("loyalty_earned","False");
        } else {
            $resource->set("loyalty_earned","True");
        }
        $resource = $this->setXoikosPickupAndOrderTimeStringsOnResource($resource);
        $resource->user['contact_no'] = $this->formatUserPhoneNumber($resource->user['contact_no']);
        $resource->set("xoikos_item_nodes",array());
        if ($resource->delivery_info) {
            $resource->delivery_info->phone_no = $this->formatUserPhoneNumber($resource->delivery_info->phone_no);
        }
        foreach ($resource->order_details as &$item) {
            $additional_nodes = array();
            if ($item['item_id'] == XoikosImporter::COOKIE_ITEM_ID || $item['item_id'] == XoikosImporter::CHIP_ITEM_ID) {
                $multi_option_items = $this->processMultiOptionItem($item);
                foreach ($multi_option_items as $multi_option_item) {
                    $resource->xoikos_item_nodes[] = $multi_option_item;
                }
                continue;
            }
            if (substr_count($item['external_id'],':') == 1) {
                $i = explode(":",$item['external_id']);
                $item['external_id'] = $i[0];
                if ($i[1] != 'zero_price') {
                    $additional_nodes[] = $this->createNewModifierNode($i[1],$name,"0.00",$group_name);
                }
            }
            $item['xoikos_single_item_tax_value'] = number_format($item['item_tax']/$item['quantity'],2);
            foreach ($item['order_detail_complete_modifier_list_no_holds'] as &$modifier) {
                if (substr_count($modifier['mod_name'],"=") > 0) {
                    $mn = explode("=",$modifier['mod_name']);
                    $modifier['mod_name'] = $mn[1];
                    $modifier['mod_print_name'] = $mn[1];
                }

                if (substr_count($modifier['external_id'],':') == 0) {
                    $mod_quantity = $modifier['mod_quantity'];
                    $modifier['mod_quantity'] = 1;
                    for ($i=1;$i<$mod_quantity;$i++) {
                        //create duplicate node
                        $additional_nodes[] = $modifier;
                    }

                } else {
                    myerror_logging(5,"we have an concat external: ".$modifier['external_id']);
                    $m = explode(':',$modifier['external_id']);
                    myerror_logging(5,"the plu value is: ".$m[0]);
                    if ($m[1] == 'zero_price') {
                        $modifier['external_id'] = $m[0];
                    } else if ($m[0] == 423 || $m[0] == 424) {
                        // meal deal
                        myerror_logging(5,"its a meal deal node");
                        $additional_nodes[] = $this->createNewNodeFromMealDeal($modifier,$m,"Make It A Meal - Includes a drink and a side.",($m[0] == 423) ?  "Yes With Chips" : "Yes With a Cookie",($m[0] == 423) ?  "Select Chips" : "Select Cookie");
                    } else if ($m[0] == 383 || $m[0] == 384) {
                        //Drink Size
                        myerror_logging(5,"its a drink size node");
                        $additional_nodes[] = $this->createNewNodeFromMealDeal($modifier,$m,"Drink Size",($m[0] == 383) ?  "Medium" : "Large","Drink");
                    } else if ($m[0] == 393 || $m[0] == 394 || $m[0] == 395) {
                        //Drive Through top level drink
                        $new_item = array();
                        $new_item['item_name'] = $modifier['mod_name'];
                        $new_item['size_name'] = $mn[0];
                        $new_item['quantity'] = 1;
                        $new_item['price'] = number_format($modifier['mod_price'],2);
                        $new_item['item_total'] = number_format($modifier['mod_price'],2);
                        $new_item['item_total_w_mods'] = number_format($modifier['mod_price'],2);
                        $new_item['note'] = '';
                        $new_item['external_id'] = $m[0];
                        // need tax rate for merchant
                        $tax_rate = TaxAdapter::staticGetTotalTax($this->merchant['merchant_id']);
                        $new_item['item_tax'] = number_format($modifier['mod_price'] * $tax_rate,2);
                        $new_item['xoikos_single_item_tax_value'] = number_format($new_item['item_tax'],2);

                        //set tax on top item since drink was removed
                        $item['item_tax'] = number_format($item['price'] * $tax_rate,2);

                        $new_item['order_detail_complete_modifier_list_no_holds'][] = $modifier;

                        $new_item['order_detail_complete_modifier_list_no_holds'][0]['external_id'] = $m[1];
                        $new_item['order_detail_complete_modifier_list_no_holds'][0]['mod_price'] = "0.00";
                        $new_item['order_detail_complete_modifier_list_no_holds'][0]['mod_total_price'] = "0.00";
                        $modifier['mod_name'] = "SKIP THIS MODIFIER";
                        $resource->xoikos_item_nodes[] = $new_item;
                    } else {
                        $additional_nodes[] = $this->createNewNodeFromCombinationExternalId($modifier,$m);
                    }
                }
                $modifier['mod_price'] = number_format($modifier['mod_price'],2);
                $modifier['mod_total_price'] = number_format($modifier['mod_total_price'],2);
            }
            foreach ($additional_nodes as $node_to_add) {
                $item['order_detail_complete_modifier_list_no_holds'][] = $node_to_add;
            }
            $resource->xoikos_item_nodes[] = $item;
        }
        $xoikos_grand_total = number_format($resource->grand_total,2);
        $discounts = array();
        if ($resource->promo_amt < 0.00) {
            $tax_rate = TaxAdapter::staticGetTotalTax($this->merchant['merchant_id']);
            $promo_tax_amount = $tax_rate * $resource->positive_promo_amount;
            $xoikos_grand_total = number_format($promo_tax_amount + $resource->positive_promo_amount + $resource->grand_total, 2);
            //$xoikos_total_tax_amt = number_format($promo_tax_amount + $resource->total_tax_amt,2);
            $discounts[] = array("discount_code"=>$resource->promo_code,"amount"=>$resource->positive_promo_amount);
        }
        if ($balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$resource->order_id,"process"=>'LoyaltyBalancePayment'),'BalanceChangeAdapter')) {
            $loyalty_discount = $balance_change_records[0]['charge_amt'];
            $discounts[] = array("discount_code"=>"Loyalty Purchase","amount"=>number_format($loyalty_discount,2));
        }
        $resource->set("discounts",$discounts);
        //$resource->set("xoikos_total_tax_amt",$xoikos_total_tax_amt);
        $resource->set("xoikos_grand_total",$xoikos_grand_total);
        if (strtolower($resource->requested_delivery_time) == 'as soon as possible') {
            $resource->set("ASAP","true");
        }
        return $resource;
    }

    public function processMultiOptionItem($item)
    {
        // need tax rate for merchant
        $tax_rate = TaxAdapter::staticGetTotalTax($this->merchant['merchant_id']);

        $items = array();
        $mod_list = $item['order_detail_complete_modifier_list_no_holds'];
        foreach ($mod_list as $flavor) {
            $item['quantity'] = $flavor['mod_quantity'];
            $item['item_total_w_mods'] = floatval($item['item_total']) * $flavor['mod_quantity'];
            $item['item_tax'] = number_format($item['item_total_w_mods'] * $tax_rate,2);
            $item['xoikos_single_item_tax_value'] = $item['item_tax'];
            $flavor['mod_quantity'] = 1;
            $flavor['mod_price'] = 0.00;
            $flavor['mod_total_price'] = 0.00;
            $item['order_detail_complete_modifier_list_no_holds'] = array($flavor);
            $items[] = $item;
        }
        return $items;
    }


    public function createNewNodeFromCombinationExternalId(&$modifier,$m)
    {

        return $this->createNewNodeFromMealDeal($modifier,$m,$modifier['modifier_group_name'],$modifier['mod_name'],$modifier['modifier_group_name']);
    }

    public function createNewNodeFromMealDeal(&$modifier,$m,$mod_group_name,$mod_name,$addional_node_mod_group_name)
    {
        $additional_node = $this->createNewModifierNode($m[1],$modifier['mod_name'],"0.00",$addional_node_mod_group_name);

        $modifier['external_id'] = $m[0];
        $modifier['modifier_group_name'] = $mod_group_name;
        $modifier['mod_name'] = $mod_name;
        $modifier['mod_print_name'] = $mod_name;

        return $additional_node;
    }

    public function createNewModifierNode($external_id,$modifier_name,$price,$group_name)
    {
        $additional_node = array();
        $additional_node['external_id'] = $external_id;
        $additional_node['mod_name'] = $modifier_name;
        $additional_node['mod_print_name'] = $modifier_name;
        $additional_node['mod_price'] = "$price";
        $additional_node['mod_total_price'] = "$price";
        $additional_node['mod_quantity'] = 1;
        $additional_node['modifier_group_name'] = $group_name;
        return $additional_node;
    }


    public function setXoikosPickupAndOrderTimeStringsOnResource($resource)
    {
        //$tz = date_default_timezone_get();
        //date_default_timezone_set('GMT');
        //      mm/dd/yyyy hh:mm:ss
        $xoikos_pickup_time_string = date("m/d/Y H:i:s P",$resource->pickup_dt_tm);
        $xoikos_order_time_string = date("m/d/Y H:i:s P",$resource->order_dt_tm);
        $resource->set("xoikos_pickup_time_string",$xoikos_pickup_time_string);
        $resource->set("xoikos_order_time_string",$xoikos_order_time_string);
        //date_default_timezone_set($tz);
        return $resource;
    }

    public function formatUserPhoneNumber($phone_number)
    {
        return preg_replace("/[^0-9]/", "", $phone_number);
    }
}
?>