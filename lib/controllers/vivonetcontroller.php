<?php

Class VivonetController extends MessageController
{
    var $format_name = 'vivonet';
    var $info_adapter;

    function VivonetController($mt, $u, &$r, $l = 0)
    {
        parent::MessageController($mt, $u, $r, $l);
        $this->info_adapter = new MerchantVivonetInfoMapsAdapter($m);
    }

    public function populateMessageData($message_resource)
    {
        $resource = parent::populateMessageData($message_resource);
        if ($this->merchant == null) {
            $this->merchant = MerchantAdapter::getMerchantFromIdOrNumericId($message_resource->merchant_id);
        }
        $vivonet_order = $this->createVivonetOrder($resource);
        $resource->vivonet_json_order = json_encode($vivonet_order);
        return $resource;
    }

    protected function send($message_body)
    {
        myerror_log("Vivonet sending with body: " . $message_body);
        if ($this->merchant == null) {
            $merchant_resource = MerchantAdapter::getMerchantFromIdOrNumericId($this->message_resource->merchant_id);
            $this->merchant = $merchant_resource->getDataFieldsReally();
        }
        if ($store_id = $this->info_adapter->getStoreId($this->merchant['merchant_id'])) {
            $vivonet_service = new VivonetService($this->info_adapter->getInfoRecord());
            $vivonet_service->configure(
                VivonetService::ORDERING,
                VivonetService::PLACE_ORDER,
                array('storeId' => $store_id),
                array('storeId' => $store_id)
            );
        } else {
            throw new InvalidVivonetMerchantSetupException("ERROR!!!! Could not get store id for merchant_id: ".$this->merchant['merchant_id']);
        }

        try {
            if ($response = $vivonet_service->send($message_body)) {
                $this->message_resource->message_text = $message_body;
                $this->message_resource->response = $response['raw_result'];
                return $response;
            }
        } catch (UnsuccessfulVivonetPushException $e) {
            $this->error_message = $e->getMessage();
            $this->has_messaging_error = true;
            throw $e;
        }

    }

    protected function createVivonetOrder($resource){
        myerror_logging(3,"starting create createVivonetOrder");
        $vivonet_order = array(
            "orderId" => 0,
            "externalSystemOrderId" => $resource->order_id,
            "orderPlacedBy" => $resource->customer_full_name,
            "orderPlacerId" => $resource->user_id,
            "pickupTime" => $resource->pickup_dt_tm
        );

        $vivonet_order['orderLineItems'] = $this->createOrderLineItems($resource->order_details);
        if ($resource->tip_amt > 0) {
            $vivonet_order['charges'] = $this->createCharges($resource->tip_amt);
        } else {
            $vivonet_order['charges'] = [];
        }
        $vivonet_order['payments'] = $this->getPaymentInfo($resource, $vivonet_order['orderLineItems']);
        if ($resource->promo_amt < 0.00) {
            $vivonet_order['charges'][] = $this->getDiscountsForOrder($resource);
        }
        logData($vivonet_order,"vivonet order",3);
        return $vivonet_order;
    }

    protected function createOrderLineItems($order_details = array()){
        myerror_logging(3,"creating line items");
        $order_line_items = array();
        $count = 0;
        foreach($order_details as $ordered_item){
            $size_print_name = ($ordered_item['size_print_name'] == null || trim($ordered_item['size_print_name']) == '') ? 'Default' : $ordered_item['size_print_name'];
            $order = array(
                "orderLineItemId" => $count++,
                "productId" => intval($ordered_item['external_id']),
                "productName" => $ordered_item['item_print_name'],
                "orderTypeId" => 0,
                "price" => floatval($ordered_item['price']),
                "quantity" =>  intval($ordered_item['quantity']),
                //"quantityUnit" => $size_print_name,
                "quantityUnit" => 'item',
                "ignorePrice" => false,
                "remark" => $ordered_item['note'],
            );

            $order['modifiers'] = $this->createItemModifiersNode($ordered_item['order_detail_complete_modifier_list_no_holds'], $count);

            $order['discounts'] = array();
//                array(
//                    "discountId" => 0,
//                    "discountName" => "name",
//                    "discountType" => "promotion",
//                    "value" => 0
//                )
//            );
            array_push($order_line_items, $order);
        }
        logData($order_line_items,"order line items",3);
        return $order_line_items;
    }

    protected function createItemModifiersNode($item_modifiers = array(), $orderLineItemId = 0)
    {
        $modifiers = array();
        foreach($item_modifiers as $item_modifier){
            $name = explode("=",$item_modifier['mod_print_name']);
            $external_id = explode(":",$item_modifier['external_id']);
            $price = $item_modifier['mod_price'];
            $sub_modifiers = array();
            if ($name[1] && $external_id[1]) {
                $sub_modifier = $this->getPayloadModifier($item_modifier['mod_price'],1,$name[1],$external_id[1],$orderLineItemId);
                $sub_modifiers[] = $sub_modifier;
                $price = 0.00;
            }
            $modifier = $this->getPayloadModifier($price,$item_modifier['mod_quantity'],$name[0],$external_id[0],$orderLineItemId,$sub_modifiers);
            $modifiers[] = $modifier;
        }
        return $modifiers;
    }

    function getPayloadModifier($price,$quantity,$name,$external_id,$orderLineItemId,$sub_modifiers = array())
    {
        return array(
            "price" => floatval($price),
            "remark" => "",
            "orderTypeId" => 0,
            "quantityUnit" => "item",
            "ignorePrice" => false,
            "quantity" => intval($quantity),
            "productName" => $name,
            "orderLineItemId" => $orderLineItemId,
            "productId" => intval($external_id),
            "modifiers"=> $sub_modifiers,
            "discounts"=> array()
        );
    }

    protected function createCharges($tip_amt)
    {
        $tip = number_format((($tip_amt > 0) ? floatval($tip_amt) : 0.00),2);
        $tip_node = array();
        $data['amount'] = floatval($tip);
        $data['chargeId'] = intval($this->getTipIdForMerchant($this->merchant['merchant_id']));
        $data['name'] = "SERVICETIP";
        $tip_node[] = $data;
        return $tip_node;
    }

    protected function getPaymentInfo($resource, $order_line_items = array())
    {
        //myerror_log("here");
        return array(
            array(
                "amount" => floatval($resource->grand_total),
                "tenderId" => intval($this->getTenderIdForMerchant($resource->merchant_id)),
                "paymentId" => 1,
                "lineItemIds" => array(),
                "paymentMethod" => array(
                    "securityCode" => "",
                    "expirationDate" => "0000",
                    "nameOnCard" => $resource->customer_full_name,
                    "paymentMethodId" => 0,
                    "type" => "RawCard",
                    "cardNumber"=> "0000000000000000"
                )
            )
        );
    }

    protected function getDiscountsForOrder($resource){
        return array(
                "amount" => floatval($resource->promo_amt),
                "chargeId" => $this->getPromoChargeIdForMerchant($resource->merchant_id),
                "name" => "SERVICETIP"
            );
    }

    function getPromoChargeIdForMerchant($merchant_id)
    {
        //return 10698157;
        if ($promo_charge_id = $this->info_adapter->getPromoChargeIdForMerchant($merchant_id)) {
            return $promo_charge_id;
        } else {
            myerror_log("WE HAVE A VIVONET MERCHANT WITH NO PROMO ID REGISTERED");
            recordError("We have a vivonet merchant with NO promo_charge_id listed in Merchant_Vivonet_Info_Maps","GET ONE!!!");
            return "NeedPromooChargeId";
        }

    }

    function checkLocalProductIdsAgainstRemotePOSIds($menu_id,$merchant_id)
    {
        $item_sizes = CompleteMenu::getAllItemSizesAsResources($menu_id,$merchant_id);
        $remote_product_ids = $this->getPromoChargeIdForMerchant($merchant_id);
    }

    function getProductIdsForMerchant($merchant_id)
    {
        $vivonet_service = new VivonetService(array("merchant_id"=>$merchant_id));
        if ($product_ids = $vivonet_service->getProductIdsForStore()) {
            return $product_ids;
        }
    }

    /*
     * @desc Method for activity history use
     */
    static function staticValidateMenuExternalIdsBySkinId($skin_id)
    {
        if ($skin_id < 1) {
            myerror_log("BAD SKIN ID for activity");
            return false;
        }
        $merchant_list = SkinMerchantMapAdapter::staticGetRecords(['skin_id'=>$skin_id],'SkinMerchantMapAdapter');
        $vivonet_controller = new VivonetController(getM(),null,$request);
        foreach ($merchant_list as $merchant) {
            $merchant_id = $merchant['merchant_id'];
            $menu_id = MerchantMenuMapAdapter::getMenuIdFromMerchantIdAndType($merchant_id,'Pickup');
            if (MerchantVivonetInfoMapsAdapter::isMechantAVivonetMerchant($merchant_id)) {
                if ($menu_id < 1000 || $merchant_id < 1000) {
                    myerror_log("WE HAVE A BAD VALUE FOR merchant_id: $merchant_id   OR    menu_id: $menu_id");
                    myerror_log("We cannot execute method");
                } else {
                    $vivonet_controller->validateMenuExternalIds($menu_id,$merchant_id);
                }
            } else {
                myerror_log("Merchant does not have a record in the Merchant_Vivonet_Info_Maps table. Skipping merchant_id: $merchant_id");
            }
        }
        return true;
    }

    /*
     * @desc Method for activity history use
     */
    static function staticValidateMenuExternalIds($info)
    {
        $s = explode(',',$info);
        $menu_id = $s[0];
        $merchant_id = $s[1];
        if ($menu_id < 1000 || $merchant_id < 1000) {
            myerror_log("BAD INFO STRING:  $info");
            myerror_log("WE HAVE A BAD VALUE FOR merchant_id: $merchant_id   OR    menu_id: $menu_id");
            myerror_log("We cannot execute method");
            return false;
        }
        $vivonet_controller = new VivonetController(getM(),null,$request);
        $vivonet_controller->validateMenuExternalIds($menu_id,$merchant_id);
        return true;
    }

    function validateMenuExternalIds($menu_id,$merchant_id)
    {
        if ($ids = $this->getProductIdsForMerchant($merchant_id)) {
            $item_size_resources = CompleteMenu::getAllItemSizesAsResources($menu_id,$merchant_id);
            $failures = "Here are the vivonet id Failures for merchant_id: $merchant_id  \r\n";
            $failures .= "\r\n   ----  Items  ----\r\n";
            foreach ($item_size_resources as $item_size_resource) {
                $id = $item_size_resource->external_id;
                if ($ids[$id] == 1) {
                    myerror_log("we have a valid item id",5);
                } else if ($item_size_resource->active == 'Y') {
                    $object_failures = true;
                    $json = json_encode($item_size_resource->getDataFieldsReally());
                    myerror_log("VIVONET ID MISMATCH!!!  No matching ITEM PLU on POS for: $json");
                    $failures .= "$json \r\n";
                    $item_size_resource->active = 'N';
                    $item_size_resource->save();

                }
            }
            $failures .= "\r\n\r\n   ----  Modifiers  ----\r\n";
            $modifier_size_resources = CompleteMenu::getAllModifierItemSizesAsResoures($menu_id,$merchant_id);
            foreach ($modifier_size_resources as $modifier_size_resources) {
                $id = $modifier_size_resources->external_id;
                if ($ids[$id] == 1) {
                    myerror_log("we have a valid modifier id",5);
                } else if ($modifier_size_resources->active == 'Y') {
                    $object_failures = true;
                    $json = json_encode($modifier_size_resources->getDataFieldsReally());
                    myerror_log("VIVONET ID MISMATCH!!!  No matching MODIFIER PLU on POS for: $json");
                    $failures .= "$json \r\n";
                    $modifier_size_resources->active = 'N';
                    $modifier_size_resources->save();
                }
            }
            $failures .= "\r\n \r\n These objects have all be set to active = 'N' ";
            if ($object_failures) {
                MailIt::sendErrorEmailSupport("We have Vivonet ID Mismatch Errors",$failures);
                MailIt::sendErrorEmail("We have Vivonet ID Mismatch Errors",$failures);
            }
        }

    }

    function getTenderIdForMerchant($merchant_id)
    {
        if ($tender_id = $this->info_adapter->getTenderIdForMerchant($merchant_id)) {
            return $tender_id;
        } else {
            $vivonet_service = new VivonetService(array("merchant_id"=>$merchant_id));
            if ($tender_id = $vivonet_service->getTenderIdForStore()) {
                $resource = Resource::findOrCreateIfNotExistsByData($this->info_adapter,array("merchant_id"=>$merchant_id));
                $resource->tender_id = $tender_id;
                $resource->save();
                return $tender_id;
            }
        }
    }

    function getTipIdForMerchant($merchant_id)
    {
        if ($service_tip_id = $this->info_adapter->getServiceTipIdForMerchant($merchant_id)) {
            return $service_tip_id;
        } else {
            $vivonet_service = new VivonetService(array("merchant_id"=>$merchant_id));
            if ($service_tip_id = $vivonet_service->getServiceTipIdForStore()) {
                $resource = Resource::findOrCreateIfNotExistsByData($this->info_adapter,array("merchant_id"=>$merchant_id));
                $resource->service_tip_id = $service_tip_id;
                $resource->save();
                return $service_tip_id;
            }
        }
    }

    //************* checkout stuff for validating order ******************

    public function setVivonetCheckoutInfoOnOrderObject($order)
    {
        $order_resource = $this->setVivonetCheckoutInfoOnOrderResource($order->getOrderResource());
        $order_resource->save();
        return new Order($order_resource->order_id);
    }

    public function setVivonetCheckoutInfoOnOrderResource(&$order_resource)
    {
        if ($checkout_info_as_array = $this->getVivonetCheckoutInfo($order_resource->order_id)) {
            $vivonet_total_tax = 0.00;
            foreach ($checkout_info_as_array['charges'] as $charges) {
                $vivonet_total_tax = $vivonet_total_tax + $charges['amount'];
            }
            $order_resource->total_tax_amt = floatval($vivonet_total_tax);
            $tip_amt = $order_resource->tip_amt > 0.00 ? floatval($order_resource->tip_amt) : 0.00;
            $transaction_fee =  $order_resource->trans_fee_amt > 0.00 ? floatval($order_resource->trans_fee_amt) : 0.00;
            $promo_amt = $order_resource->promo_amt < 0.00 ? floatval($order_resource->promo_amt) : 0.00;
            $total_from_vivonet = floatval($checkout_info_as_array['total']);
            $order_resource->grand_total = floatval($total_from_vivonet+$tip_amt+$transaction_fee+$promo_amt);
            $order_resource->grand_total_to_merchant = floatval($total_from_vivonet+$promo_amt);
        }
        return $order_resource;
    }

    public function getVivonetCheckoutInfo($order_id)
    {
        $resource = $this->prepMessageForSending(Resource::dummyfactory(array("order_id"=>$order_id,"message_format"=>'V')));
        $vivonet_service = new VivonetService($this->info_adapter->getInfoRecord());
        $vivonet_service->configure(VivonetService::ORDERING,VivonetService::ORDER_DATA_CHARGES);
        $full_order_text = $resource->message_text;
        $full_order_text_as_array = json_decode($full_order_text,true);
        $items_as_array = $full_order_text_as_array["orderLineItems"];
        // right here is where we would add promo information if it is used in tax calculation.
        // currently there does not seem to be a place for order level discounts on the get tax info call
        // (there is a place for item level discounts)
        $body = json_encode($items_as_array);
        $vivonet_service->setMethod('put');
        $response = $vivonet_service->send($body);
        if ($response['http_code'] == 200) {
            return $response['data'];
        } else {
            return false;
        }
    }



}
?>
