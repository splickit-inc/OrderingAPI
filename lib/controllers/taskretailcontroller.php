<?php

class TaskRetailController extends MessageController
{
    private $id = 1;
    private $current_order_id;
    private $current_order_item_id;
    private $merchant_task_retail_info_map;

    const TASK_RETAIL_DATE_TIME_FORMAT = "Y-m-d\TH:i:00.0000";

    const SINGLE_MODIFIER_PLU_FOR_ONE_OR_MANY_GROUP = 6057;
    const UNLIMITED_MODIFIER_PLU_FOR_ONE_OR_MANY_GROUP = 6058;

    // HARD CODED GROUP ID!!!!!!!!!!!!!
    const SPLICKIT_MODIFIER_GROUP_ID_OF_ONE_OR_UNLIMITED_TOPPINGS = 337832;

    public function TaskRetailController($mt,$u,&$r,$l = 0)
    {
        parent::MessageController($mt, $u, $r, $l);
    }

    protected function send($message_body)
    {
        myerror_log("Task retail sending with body: ".cleanUpCRLFTFromString($message_body));
        $task_retail_service = new TaskRetailService($this->message_resource);
        if ($response = $task_retail_service->send($message_body)) {
            $this->message_resource->message_text = $message_body;
            $this->message_resource->response = $response['raw_result'];
            return $response;
        }
    }

    public function populateMessageData($message_resource)
    {
        $resource = parent::populateMessageData($message_resource);
        $resource = $this->setTaskRetailPickupInfo($resource);
        $resource = $this->setTaskRetailPickupAndOrderTimeStringsOnResource($resource);
        $resource = $this->doPickOneOrMany($resource);
        $resource = $this->doComboModification($resource);
        $resource = $this->setPromoInformation($resource);
        if (is_null($resource->user['last_name']) || trim($resource->user['last_name']) == '') {
            $resource->user['last_name'] = 'GstChkOut';
        }
        return $resource;
    }

    public function doPickOneOrMany(&$message_resource)
    {
        foreach ($message_resource->order_details as &$order_item_record) {
            $list = [];
            $temp = [];
            foreach($order_item_record['order_detail_complete_modifier_list_no_holds'] as &$mod_record){
                if ($this->isModifierPartOfPickOneModifierGroup($mod_record) ){
                    //The Pick 1 for a dollar(PLU:6057) and pick unlimited for 2 dollars(PLU:6058)
                    $mod_record['mod_price'] = 0.00;
                    $mod_record['mod_total_price'] = 0.00;
                    $list[] = $mod_record;
                } else {
                    $temp[] = $mod_record;
                }
            }
            if (sizeof($list) == 0) {
                continue;
            }
            //The Pick 1 for a dollar(PLU:6057) and pick unlimited for 2 dollars(PLU:6058)
            if (sizeof($list) == 1) {
               // add single plu to front of list
                $pick_one_or_many_modifier['mod_price'] = 1.00;
                $pick_one_or_many_modifier['mod_total_price'] = 1.00;
                $pick_one_or_many_modifier['external_id'] = self::SINGLE_MODIFIER_PLU_FOR_ONE_OR_MANY_GROUP;
                $pick_one_or_many_modifier['mod_quantity'] = 1;
                $pick_one_or_many_modifier['comes_with'] = "N";
            } else if (sizeof($list) > 1) {
                // add unlimited plu to front of list
                $pick_one_or_many_modifier['mod_price'] = 2.00;
                $pick_one_or_many_modifier['mod_total_price'] = 2.00;
                $pick_one_or_many_modifier['external_id'] = self::UNLIMITED_MODIFIER_PLU_FOR_ONE_OR_MANY_GROUP;
                $pick_one_or_many_modifier['mod_quantity'] = 1;
                $pick_one_or_many_modifier['comes_with'] = "N";
            } else {
                myerror_log("Whisky Tango Foxtrot!");
                return $message_resource;
            }
            $one_or_many_modifiers = array_merge([$pick_one_or_many_modifier],$list);
            $total_modifiers_for_item = array_merge($temp,$one_or_many_modifiers);
            $order_item_record['order_detail_complete_modifier_list_no_holds'] = $total_modifiers_for_item;
        }
        return $message_resource;
    }

    public function isModifierPartOfPickOneModifierGroup($order_modifier_item_record)
    {
        return $order_modifier_item_record['modifier_group_id'] == self::SPLICKIT_MODIFIER_GROUP_ID_OF_ONE_OR_UNLIMITED_TOPPINGS;
    }

    public function doComboModification(&$message_resource)
    {
        foreach ($message_resource->order_details as &$order_item_record) {
            $this->breakOutComboModifierFromItem($order_item_record);
        }
        return $message_resource;
    }

    public function breakOutComboModifierFromItem(&$order_item_record)
    {
        $mods_array = array();
        foreach($order_item_record['order_detail_complete_modifier_list_no_holds'] as &$mod_record)
        {
            $external_id = $mod_record['external_id'];
            $s = explode(":",$external_id);
            if (isset($s[1])) {
                $other_mod_price = $mod_record['mod_price'] - 1.75;
                $mod_record['mod_price'] = "1.75";
                $mod_record['mod_total_price'] = "1.75";
                $mod_record['external_id'] = $s[0];

                $additional_combo_item_record['mod_price'] = "$other_mod_price";
                $additional_combo_item_record['mod_total_price'] = "$other_mod_price";
                $additional_combo_item_record['external_id'] = "".$s[1];
                $additional_combo_item_record['mod_quantity'] = 1;
                $additional_combo_item_record['comes_with'] = "N";
            }
        }
        if ($additional_combo_item_record) {
            $order_item_record['order_detail_complete_modifier_list_no_holds'][] = $additional_combo_item_record;
        }
    }

    public function setTaskRetailPickupInfo($resource) {
      $m = new MerchantTaskRetailInfoMapAdapter($m);
      $merchant_id = $resource->merchant_id;
      $order_id = $resource->order_id;
      if($row = Resource::find($m, '', array(TONIC_FIND_BY_METADATA => array("merchant_id" => $merchant_id)))) {
          $this->merchant_task_retail_info_map = $row;
        $resource->set('location_id', $row->location);
        $resource->set('media_id', $row->media_id);
        $resource->set('payment_id', $row->payment_id);
          
      } else {
        MailIt::sendErrorEmailSupport("Couldn't find TaskRetail location mapping for $merchant_id", "Merchant $merchant_id did not receive message for order $order_id. Merchant does not have a TaskRetail location mapping. Orders will not go through until this is corrected.");
        throw new NoMerchantTaskRetailLocationException($merchant_id);
      }
      
      return $resource;
    }

    public function setPromoInformation($resource){
        $promo_amt = floatval($resource->promo_amt);
        $amount = abs($promo_amt);
        if($amount > 0 ){
            $resource->set('promo_media_id', 30039); //TODO: create additional field on Merchant_Task_Retail_Info_Map table
            $resource->set('promo_amount', number_format($amount, 2));
        }
        return $resource;

    }

    public function setTaskRetailPickupAndOrderTimeStringsOnResource($resource)
    {
        $dt = new DateTime();
        $dt->setTimezone(new DateTimeZone($this->getServerTimeZoneForCurrentMessage()));
        $dt->setTimestamp($resource->pickup_dt_tm);
        $task_retail_pickup_time_string = $dt->format(self::TASK_RETAIL_DATE_TIME_FORMAT);

        $dt->setTimestamp($resource->order_dt_tm);
        $task_retail_order_time_string = $dt->format(self::TASK_RETAIL_DATE_TIME_FORMAT);

        $resource->set("task_retail_pickup_time_string",$task_retail_pickup_time_string);
        $resource->set("task_retail_order_time_string",$task_retail_order_time_string);
        return $resource;
    }


    function createTaskV2OrderModelAsArray($resource)
    {
        $member_id = $this->getMemberIdFromTask($resource->user['email']);

        $order_model = [];
        $order_model['ID'] = $this->id++;
        $this->current_order_id = $order_model['ID'];
        $order_model['SaleName'] = 'Web Order';
        $order_model['Status'] = 1;
        $order_model['PickupLocation'] = $resource->location_id;
        $order_model['PickUpDate'] = $resource->task_retail_pickup_time_string;
        $order_model['OrderedDate'] = $resource->task_retail_order_time_string;
        $order_model['IsDelivery'] = $resource->field;
        if ($member_id) {
            $order_model['MemberId'] = $member_id; /***** customer number, will need to be created in TASK system *************/
        } else {
            $order_model['PartialMember'] = $this->getPartialMemberNode($resource);
        }
        $order_model['Covers'] = $resource->order_qty;
        $order_model['AddedDate'] = $resource->task_retail_order_time_string;
        $order_model['ExtraInstructions'] = $resource->note;
        $order_model['ExtraInstructions'] = $resource->field;
        $order_model['SendToKMS'] = true;
        $order_model['TotalTaxes'] = [];
        if ($resource->cash == 'Y') {
            $order_model['TotalLeftToPay'] = $resource->grand_total;
            $order_model['TotalPaid'] = 0;
        } else {
            $order_model['TotalLeftToPay'] = 0;
            $order_model['TotalPaid'] = $resource->grand_total;
        }
        $order_model['OrderTypeId'] = 0;
        if (substr_count(strtolower($resource->note),'dine in') > 0 ) {
            $order_model['OrderTypeId'] = 1;
        } else if ($resource->order_type == 'R') {
            $order_model['OrderTypeId'] = 2;
        } else if (strpos(strtolower($resource->note),'doordash') == true )  {
            // this is currently only door dash
            $order_model['OrderTypeId'] = 16;
        }

        $order_model['Items'] = $this->getItemsNode($resource);
        $order_model['Medias'] = $this->getMediasNode($resource);
        if (floatval($resource->promo_amount) > 0) {
            $order_model['OnlineDiscounts'] = $this->getOnlineDiscountsNode($resource);
        }
        return $order_model;
    }

    function getOnlineDiscountsNode($resource)
    {
        $discount = [];
        $discount['ID'] = $this->id++;
        $discount['OnlineOrderId'] = $this->current_order_id;
        $discount['Value'] = floatval($resource->promo_amount);
        return [$discount];
    }

    function getMediasNode($resource)
    {
        $medias_node = [];

        /** some for each loop in here but currently we only have a single media **/

        $media = [];
        $media['ID'] = $this->id++;
        $media['MediaDescription'] = 'Payment';
        $media['MediaId'] = $resource->payment_id;
        $media['OrderId'] = $this->current_order_id;
        $media['Value'] = $resource->grand_total;
        $media['PaymentToken'] = 'Payment Token';
        $media['PaymentType'] = 0;
        $media['PaymentTransactionId'] = 'Payment Transaction Id';
        $media['IsTax'] = false;

        /** end for each loop when it exists **/

        $medias_node[] = $media;
        $medias_node[] = $this->getMediaTaxsNode($resource);
        return $medias_node;
    }

    function getItemsNode($resource)
    {
        $items_node = [];
        $ordered_items = $resource->order_details;
        foreach ($ordered_items as $ordered_item) {
            $substitute_price = 0;
            if ($ordered_item['external_id'] != null && trim($ordered_item['external_id']) != '') {
                $top_level_item = [];
                $top_level_item['ID'] = $this->id++;
                $this->current_order_item_id = $top_level_item['ID'];
                $top_level_item['DisplayName'] = $ordered_item['item_print_name'];
                $top_level_item['OrderId'] = $this->current_order_id;
                $top_level_item['PLU'] = $ordered_item['external_id'];
                $top_level_item['Quantity'] = $ordered_item['quantity'];
                $top_level_item['Value'] = $ordered_item['price'];
                $items_node[] = $top_level_item;
            } else {
                $substitute_price = $ordered_item['price'];
            }
            foreach ($ordered_item['order_detail_complete_modifier_list_no_holds'] as $modifier_item) {
                $next_item_for_node = [];
                $quantity = $modifier_item['mod_quantity'];
                for ($i = 0; $i < $quantity; $i++) {
                    if ($i == 1 && $modifier_item['comes_with'] == 'Y') {
                        continue;
                    }
                    if ($modifier_item['external_id'] != null && trim($modifier_item['external_id']) != '' && $modifier_item['comes_with'] == 'N') {
                        $price = $modifier_item['mod_price'];
                        $next_item_for_node['ID'] = $this->id++;
                        if ($substitute_price > 0) {
                            // ok so the modifier is now the parent item so set the fields accordingly

                            $price = $substitute_price;
                            $substitute_price = 0;
                            $this->current_order_item_id = $modifier_item['ID'];
                            $next_item_for_node['OrderId'] = $this->current_order_id;
                            $next_item_for_node['DisplayName'] = $modifier_item['mod_print_name'].' '.$ordered_item['item_print_name'];

                        } else {
                            $next_item_for_node['OrderItemId'] = $this->current_order_item_id;
                            $next_item_for_node['DisplayName'] = $modifier_item['mod_print_name'];
                        }


                        $next_item_for_node['PLU'] = $modifier_item['external_id'];
                        $next_item_for_node['Quantity'] = 1;
                        $next_item_for_node['Value'] = $price;
                        $items_node[] = $next_item_for_node;
                    }
                }
            }
        }
        // special PLU for delivery fee
        if ($resource->delivery_amt > 0.00) {
            $top_level_item = [];
            $top_level_item['ID'] = $this->id++;
            $this->current_order_item_id = $top_level_item['ID'];
            $top_level_item['DisplayName'] = 'Delivery Fee 3PD';
            $top_level_item['OrderId'] = $this->current_order_id;
            $top_level_item['PLU'] = 60625;
            $top_level_item['Quantity'] = 1;
            $top_level_item['Value'] = $resource->delivery_amt;
            $items_node[] = $top_level_item;
        }
        return $items_node;
    }


    function getItemsNodeNotUsed($resource)
    {
        $items_node = [];
        $ordered_items = $resource->order_details;
        foreach ($ordered_items as $ordered_item) {
            if ($ordered_item['external_id'] == null || trim($ordered_item['external_id']) == '') {
                /***  do the modifier for item thing  ******/
            } else {
                $item = [];
                $item['ID'] = $this->id++;
                $this->current_order_item_id = $item['ID'];
                $item['DisplayName'] = $ordered_item['item_print_name'];
                $item['OrderId'] = $this->current_order_id;
                $item['PLU'] = $ordered_item['external_id'];
                $item['Quantity'] = $ordered_item['quantity'];
                $item['Value'] = $ordered_item['price'];
                $item['UnitPrice'] = $ordered_item['fielpriced'];
                $item['RedeemedProductId'] = 0;
                $item['IsRedeemedByPoints'] = false;
                $item['PointsValue'] = 0;
                $item['IngredientsChanges'] = $this->getIngredientsChangesNode($ordered_item);
            }
            $items_node[] = $item;
        }
        return $items_node;
    }

    function getIngredientsChangesNode($ordered_item)
    {
        $ingredients_changes_node = [];
        $ingredients_added = [];
        $ingredients_removed = [];
        foreach ($ordered_item['order_detail_complete_modifier_list_no_holds'] as $modifier_item) {
            $quantity = $modifier_item['mod_quantity'];
            for ($i = 0; $i < $quantity; $i++) {
                if ($i == 1 && $modifier_item['comes_with'] == 'Y') {
                    continue;
                }
                $added = [];
                $added['ID'] = $this->id++;;
                $added['OrderItemId'] = $this->current_order_item_id;
                $added['IngredientPLU'] = $modifier_item['external_id'];
                $added['ModifierId'] = 0;
                $added['ModifierName'] = $modifier_item['mod_print_name'];
                $added['ExtraPrice'] = $modifier_item['mod_price'];
                $ingredients_added[] = $added;
            }
        }
        foreach ($ordered_item['order_detail_hold_it_modifiers'] as $removed_modifier_item) {
            $removed = [];
            $removed['ID'] = $this->id++;;
            $removed['OrderItemID'] = $this->current_order_item_id;
            $removed['IngredientPLU'] = $removed_modifier_item['external_id'];
            $ingredients_removed[] = $removed;
        }
        $ingredients_changes_node['IngredientsAdded'] = $ingredients_added;
        $ingredients_changes_node['IngredientsRemoved'] = $ingredients_removed;
        return $ingredients_changes_node;
    }

    function getMediaTaxsNode($resource)
    {
        $media = [];
        $media['ID'] = $this->id++;
        $media['MediaDescription'] = 'Tax';
        $media['MediaId'] = 5;
        $media['OrderId'] = $this->current_order_id;
        $media['Value'] = $resource->total_tax_amt;
        $media['PaymentToken'] = 'Payment Token';
        $media['PaymentType'] = 0;
        $media['PaymentTransactionId'] = null;
        $media['IsTax'] = true;
        return $media;

    }

    function getTotalTaxesNode($resource)
    {
        $taxes_node = [];
        $taxes_node['TaxId'] = $resource->media_id;
        if ($resource->total_tax_amt > 0) {
            $taxes_node['Value'] = $resource->total_tax_amt;
        } else {
            $taxes_node['Value'] = "0.00";
        }
        $taxes_node['IsInclusive'] = false;
        return $taxes_node;
    }

    function getPartialMemberNode($resource)
    {
        $partial_member_node = [];
        $partial_member_node['FirstName'] = $resource->user['first_name'];
        $partial_member_node['LastName'] = $resource->user['last_name'];
        $partial_member_node['Email'] = $resource->user['email'];
        $partial_member_node['MobileNumber'] = $resource->user['contact_no'];
        return $partial_member_node;
    }


    function loadMessageBody($resource, $message_resource)
    {
        if ($message_resource->message_format == 'AJ') {
            $order_model_as_array = $this->createTaskV2OrderModelAsArray($resource);
            $resource->message_text = json_encode($order_model_as_array);
            $resource->_representation = $this->static_message_template;
            return $resource;
        } else {
            return parent::loadMessageBody($resource, $message_resource);
        }

    }

    function getServerTimeZoneForCurrentMessage()
    {
        if (isset($this->merchant_task_retail_info_map->server_time_zone)) {
            return $this->merchant_task_retail_info_map->server_time_zone;
        } else {
            myerror_log("ERROR TRYING TO GET INFO from Task_Retail_Info_Maps.  couldnt get Time zone");
            return 'America/Los_Angeles';
        }
    }


    function getMemberIdFromTask($email)
    {
        $escaped_email = urlencode($email);
        $api_key = getProperty('task_retail_api_key');
        if (isProd()) {
            $url = "https://frapi.xchangefusion.com/api/v1/members?memberEmail=$escaped_email&api_key=$api_key";
        } else {
            $url = "https://frpilotapi.xchangefusion.com/api/v1/members?memberEmail=$escaped_email&api_key=$api_key";
        }

        if ($ch = curl_init($url)) {
            curl_setopt($ch, CURLOPT_HEADER, 1);
            $response = SplickitCurl::curlIt($ch);
            curl_close($ch);
            if ($response['http_code'] == 200) {
                $body = substr($response['raw_result'], -($response['curl_info']['download_content_length']));
                $response_array = json_decode($body,true);
                if ($member_id = $response_array['Items'][0]['MemberId']) {
                    return $member_id;
                }
            }
            return false;
        } else {
            throw new Exception("Cannot connect to task retail to get check memeber id");
        }
    }
}

class NoMerchantTaskRetailLocationException extends Exception
{
    public function __construct($merchant_id) {
        parent::__construct("No Task Retail location data for merchant_id: $merchant_id", 100);
    }
}
?>
