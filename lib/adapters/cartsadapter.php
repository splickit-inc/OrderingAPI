<?php

class CartsAdapter extends MySQLAdapter
{
    var $merchant_id;
    var $tax_rates;

    const CREATE_DELIVERY_LOCATION_ERROR_MESSAGE = "Please select or create a destination address for your delivery order.";

    function CartsAdapter($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'Orders',
            '%([0-9a-zA-Z\-]{22})%',
            '%d',
            array('ucid'),
            null,
            array('created', 'modified')
        );

        $this->allow_full_table_scan = false;

    }

    function &select($url, $options = NULL)
    {
        $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
        return parent::select($url, $options);
    }

    function addItemsToCart($items, $order_data)
    {
        if ($this->createTemporaryTablesForAddToCart()) {
            $this->merchant_id = $order_data['merchant_id'];
            if ($this->addItemsToTemporaryTables($items)) {
                //$sp_log_level = (isset($_SERVER['GLOBAL_PROPERTIES']["sp_log_level"])) ? getProperty("sp_log_level") : getBaseLogLevel();
                $sql = "call SMAWSP_ADD_ITEMS_TO_ORDER(" . $order_data['order_id'] . "," . getSkinIdForContext() . ",'" . getRawStamp() . "',@id,@message)";
                $this->_query($sql);
                if ($error = $this->getLastErrorText()) {
                    myerror_log("error text: $error");
                }
                $options[TONIC_FIND_BY_SQL] = "SELECT @id AS result_id,@message AS info";
                if ($sp_result_resource = Resource::find($this, null, $options)) {
                    if ($sp_result_resource->result_id < 1000) {
                        myerror_log("WE HAVE A STORED PROCEDURE ERROR: " . $sp_result_resource->info);
                        return false;
                    }
                    $stamp = getRawStamp();
                    $sql = "SELECT * FROM order_detail_inserted_ids WHERE `raw_stamp` = '$stamp'";
                    $options[TONIC_FIND_BY_SQL] = $sql;
                    if ($records = $this->select(null,$options)) {
                        $this->_query("DELETE FROM order_detail_inserted_ids WHERE `raw_stamp` = '$stamp'");
                        //check for combos
                        $this->doComboPriceAdjustments($records);
                        //do taxes
                        $new_item_taxes = $this->doTaxCalculations($records);
                        if ($new_item_taxes > 0) {
                            $order = new Order($sp_result_resource->result_id);
                            $order->recalculateItemAmountsFromOrderDetailsOnOrderResource();
                        }
                    }


                    return true;
                }
            }

        } else {
            return false;
        }


    }

    function doTaxCalculations($records)
    {
        $oda = new OrderDetailAdapter(getM());
        $total_tax_for_post = 0;
        foreach ($records as $temp_record) {
            if ($order_detail_resource = Resource::find($oda,''.$temp_record['order_detail_id'])) {
                $item_tax_group = $temp_record['tax_group'];
                $tax_rate = isset($this->tax_rates[$item_tax_group]) ? $this->tax_rates[$item_tax_group] : $this->tax_rates[1];
                $item_tax = $tax_rate * $order_detail_resource->item_total_w_mods;
                $sql = "UPDATE Order_Detail SET item_tax = $item_tax WHERE order_detail_id = ".$order_detail_resource->order_detail_id;
                if ($this->_query($sql)) {
                    $total_tax_for_post = $total_tax_for_post + $item_tax;
                }
            }
        }
        return $total_tax_for_post;
    }

    function doComboPriceAdjustments($records)
    {
        $oda = new OrderDetailAdapter(getM());
        foreach($records as $temp_record) {
            if ($order_detail_id = $temp_record['order_detail_id']) {
                if ($price_adjustments = $this->getComboBonusesForOrderDetailId($order_detail_id)) {
                    $total_adjustments = 0;
                    foreach ($price_adjustments as $price_adjustment) {
                        $combo_id = $price_adjustments['combo_id'];
                        $amount = $price_adjustment['price_adjustment'];
                        $total_adjustments = $total_adjustments + $amount;
                        $sql = "INSERT INTO Order_Detail_Modifier (order_detail_id,modifier_item_id,modifier_group_id,mod_name,mod_print_name,modifier_type,hold_it,mod_quantity,mod_price,mod_total_price,created)
										VAlUES ($order_detail_id,0, 0,'price adjustment','Combo $combo_id','A','N',1,-$amount,-$amount,NOW());";
                        $oda->_query($sql);
                    }

                    // now adjust item record to reflect the chantge
                    $sql = "UPDATE Order_Detail SET item_total_w_mods = item_total_w_mods - $total_adjustments WHERE order_detail_id = $order_detail_id";
                    $oda->_query($sql);
                }
            }
        }
    }

    function createModifierSpecialHashamapWithAllSingleQuantities($input_array)
    {
        $better_hash = array();
        $index = 0;
        foreach ($input_array as $order_detail_modifier_record) {
            if ($order_detail_modifier_record['mod_quantity'] == 1) {
                $better_hash[$index++] = $order_detail_modifier_record;
            } else if ($order_detail_modifier_record['mod_quantity'] > 1) {
                $quantity = $order_detail_modifier_record['mod_quantity'];
                $order_detail_modifier_record['mod_quantity'] = 1;
                for($i=0;$i<$quantity;$i++) {
                    $better_hash[$index++] = $order_detail_modifier_record;
                }
            }
        }
        return $better_hash;
    }

    function getComboBonusesForOrderDetailId($order_detail_id)
    {
        $price_adjustments = [];
        $odma = new OrderDetailModifierAdapter(getM());
        if ($mods = $odma->getRecords(["order_detail_id"=>$order_detail_id])) {
            $mods_hash = $this->createModifierSpecialHashamapWithAllSingleQuantities($mods);
            $menu_combo_price_adapter = new MenuComboPriceAdapter(getM());
            while ($match = $this->getNextComboMatch($mods_hash)) {
                $order_detail_modifier_one = $match[0];
                $order_detail_modifier_two = $match[1];
                $combo_id = $order_detail_modifier_one['combo_id'];
                if ($price_record = $menu_combo_price_adapter->getRecord(["merchant_id"=>$this->merchant_id,"combo_id"=>$combo_id,"active"=>'Y'])) {
                    $full_price = $order_detail_modifier_one['mod_price'] + $order_detail_modifier_two['mod_price'];
                    if ($full_price > $price_record['price']) {
                        $price_adjustments[] = ["combo_id"=>$combo_id,"price_adjustment"=>($full_price - $price_record['price'])];
                    }
                }
            }
        }
        if (sizeof($price_adjustments) > 0) {
            return $price_adjustments;
        }
    }

    function getNextComboMatch(&$mods_hash)
    {
        $combo_associations_adapter = new MenuComboAssociationAdapter(getM());
        while (sizeof($mods_hash) > 1) {
            $mod = array_pop($mods_hash);
            $modifier_item_id = $mod['modifier_item_id'];
            $sql = "SELECT a.* FROM Menu_Combo_Association a JOIN Menu_Combo_Association b ON a.combo_id = b.combo_id WHERE b.kind_of_object = 'modifier_item' AND b.object_id = $modifier_item_id AND a.kind_of_object = 'modifier_item' AND a.object_id != $modifier_item_id";
            $options[TONIC_FIND_BY_SQL] = $sql;
            if ($combo_associations_records = $combo_associations_adapter->select(null,$options)) {
                foreach ($combo_associations_records as $combo_associations_record) {
                    $modifier_item_id_two = $combo_associations_record['object_id'];
                    $combo_id = $combo_associations_record['combo_id'];
                    $mod['combo_id'] = $combo_id;
                    foreach ($mods_hash as $index=>$individual_order_detail_modifier_record) {
                        if ($modifier_item_id_two == $individual_order_detail_modifier_record['modifier_item_id']) {
                            $matches[0] = $mod;
                            $matches[1] = $individual_order_detail_modifier_record;
                            unset($mods_hash[$index]);
                            return $matches;
                        }
                    }

                }
            }
        }
        return null;
    }

    private function addItemsToTemporaryTables($items)
    {
        foreach ($items as $item) {
            // hack to remove the � in cafe or saute (remove it, doesn't work)
            $skin = getSkinForContext();
            if(!$skin['show_notes_fields']) {
                $item['note'] = '';
            } else {
                $item['note'] = str_replace("\xc3\xa9", "e", $item['note']);
            }

            $item['name'] = str_replace("\xc3\xa9", "e", $item['name']);
            $points_used = isset($item['points_used']) ? $item['points_used'] : 0;
            $amount_off_from_points = isset($item['amount_off_from_points']) ? $item['amount_off_from_points'] : 0.00;
            if (!isset($item['sizeprice_id'])) {
                if ($item_size_record = getStaticRecord(array("item_id"=>$item['item_id'],'size_id'=>$item['size_id']),'ItemSizeAdapter')) {
                    $item['sizeprice_id'] = $item_size_record['item_size_id'];
                } else {
                    myerror_log("Bad data submitted. Couldn't get Item Size Map record from submitted data");
                    return false;
                }
            }
            $sql = "INSERT INTO TempOrderItems (sizeprice_id,quantity,name,note,points_used,amount_off_from_points,external_detail_id) Values (" . $item['sizeprice_id'] . "," . $item['quantity'] . ",'" . mysqli_real_escape_string($this->_handle,$item['name']) . "','" . mysqli_real_escape_string($this->_handle,$item['note']) . "'," . $points_used . "," . $amount_off_from_points . ",'" . $item['external_detail_id'] . "')";
            myerror_logging(2, $sql);
            if ($this->_query($sql)) {
                $temp_order_item_id = mysqli_insert_id($this->_handle);
                $mods = $item['mods'];
                if (sizeof($mods) > 0) {
                    $modifier_size_map_adapter = new ModifierSizeMapAdapter();
                    $sql2 = "INSERT INTO TempOrderItemMods (temp_order_detail_id,mod_sizeprice_id,mod_quantity) Values ";
                    foreach ($mods as $mod) {
                        if (!isset($mod['mod_sizeprice_id'])) {
                            $sql = "SELECT * FROM Modifier_Size_Map WHERE modifier_item_id = ".$mod['modifier_item_id']." and (size_id = ".$item['size_id']." OR size_id = 0)";
                            $options[TONIC_FIND_BY_SQL] = $sql;
                            if ($modifier_size_records = $modifier_size_map_adapter->getRecords(null,$options)) {
                                $mod['mod_sizeprice_id'] = $modifier_size_records[0]['modifier_size_id'];
                            } else {
                                myerror_log("ERROR couldn't get modifier size map record from sbmitted cart data. Skip this modifier");
                                continue;
                            }

                        }
                        $sql2 .= "(" . $temp_order_item_id . "," . $mod['mod_sizeprice_id'] . "," . $mod['mod_quantity'] . "),";
                    }
                    $sql2 = substr($sql2, 0, -1);
                    myerror_logging(2, $sql2);
                    if (!$this->_query($sql2)) {
                        // error creating order item modifier temp table
                        myerror_log("*********  very serious DB error in placeorderadapter saving order item modifiers into temp table: " . $this->getLastErrorText());
                        return false;
                    }
                }
            } else {
                // error creating order item temp table
                myerror_log("*********  very serious DB error in orderadapter saving order items into temp table: " . $this->getLastErrorText());
                return false;
            }
        }
        return true;
    }

    private function createTemporaryTablesForAddToCart()
    {
        //create temp table and load with order items
        $sql = "DROP TEMPORARY TABLE IF EXISTS `TempOrderItems`";
        $this->_query($sql);
        $sql = "CREATE TEMPORARY TABLE IF NOT EXISTS TempOrderItems (`temp_order_detail_id` INT NOT NULL AUTO_INCREMENT, `sizeprice_id` INT,`quantity` INT, `name` VARCHAR(50) NULL, `note` VARCHAR (255) NULL, `points_used` INT, `amount_off_from_points` decimal(10,2) NOT NULL DEFAULT '0.000',`external_detail_id` VARCHAR (255) NULL,PRIMARY KEY (`temp_order_detail_id`)) AUTO_INCREMENT=1";
        myerror_logging(2, $sql);
        if (!$this->_query($sql)) {
            myerror_log("ERROR! serious error creating TempOrderItems table: " . $this->getLastErrorText());
            return false;
        }

        $sql = "DROP TEMPORARY TABLE IF EXISTS `TempOrderItemMods`";
        $this->_query($sql);
        $sql = "CREATE TEMPORARY TABLE IF NOT EXISTS TempOrderItemMods (`temp_order_detail_mod_id` INT NOT NULL AUTO_INCREMENT, `temp_order_detail_id` INT, `mod_sizeprice_id` INT,`mod_quantity` INT,PRIMARY KEY (`temp_order_detail_mod_id`)) AUTO_INCREMENT=50";
        myerror_logging(2, $sql);
        $this->_query($sql);
        if (!$this->_query($sql)) {
            myerror_log("ERROR!  serious error creating TempOrderItemMods table: " . $this->getLastErrorText());
            return false;
        }
        return true;
    }

    function setTaxRates($tax_rates)
    {
        $this->tax_rates = $tax_rates;
    }

    function update(&$resource)
    {
        $stamps = $resource->stamp;
        $raw_stamp = getRawStamp();
        if (substr_count($stamps, $raw_stamp) == 0) {
            $resource->stamp = getStamp() . ';' . $stamps;
        }
        return parent::update($resource);
    }

    static function createCart($data)
    {
        $ca = new CartsAdapter(getM());
        $data['stamp'] = getRawStamp();
        if ($data['ucid'] == null) {
            $data['ucid'] = generateUUID();
        }
        $skin = getSkinForContext();
        if(!$skin['show_notes_fields']) {
            unset($data['note']);
        }
        if ($cart_resource = $ca->createSkeletonOrderFromCartData($data)) {
            return $cart_resource;
        } else {
            throw new CouldNotCreateCartException($ca->getLastErrorText());
        }
    }

    static function staticGetCartAndBaseOrderDataFromCartUcid($ucid)
    {
        $ca = new CartsAdapter(getM());
        return $ca->getCartAndBaseOrderDataFromCartUcid($ucid);
    }

    function getCartAndBaseOrderDataFromCartUcid($ucid)
    {
        return $this->getRecord(array("ucid" => $ucid));
//		$sql = "SELECT a.ucid, b.* FROM Carts a JOIN Orders b ON a.order_id = b.order_id WHERE a.ucid = '$ucid'";
//		$options[TONIC_FIND_BY_SQL] = $sql;
//		if ($result = $this->select(null,$options)) {
//			return array_pop($result);
//		}
    }

    function isValidMerchantId($merchant_id)
    {
        return isset($merchant_id) && $merchant_id > 999;
    }

    function createSkeletonOrderFromCartData($data)
    {
        if ($this->isValidMerchantId($data['merchant_id'])) {
            $merchant_resource = Resource::find(new MerchantAdapter(), '' . $data['merchant_id']);
        } else {
            throw new NoDataPassedInForCartCreationException();
        }

        $data['status'] = $data['group_order_type'] == GroupOrderAdapter::INVITE_PAY ? OrderAdapter::GROUP_ORDER : OrderAdapter::ORDER_IS_IN_PROCESS_CART;
        $data['stamp'] = getStamp();
        if ($data['user_addr_id'] > 0) {
            $data['user_delivery_location_id'] = $data['user_addr_id'];
            $data['order_type'] = OrderAdapter::DELIVERY_ORDER;
//            $mdi_adapter = new MerchantDeliveryInfoAdapter($m);
//            if ($merchant_delivery_price_distance_resource = $mdi_adapter->getMerchantDeliveryPriceResourceForUserLocationAndMerchantId($data['user_addr_id'], $data['merchant_id'])) {
//                $data['delivery_amt'] = $merchant_delivery_price_distance_resource->price;
//            } else {
//                return createErrorResourceWithHttpCode("We're sorry, this delivery address appears to be outside of our delivery range.", 422, 999);
//            }
        } else if (strtolower($data['delivery']) == 'yes' || strtolower($data['submitted_order_type']) == 'delivery') {
            if (! isset($data['group_order_token'])) {
                MailIt::sendErrorEmail("No Delivery Address submitted with delivery order", "submitted data: " . json_encode($data) . "  /r/n/r/n Check logs for stamp: " . getRawStamp());
                return createErrorResourceWithHttpCode(self::CREATE_DELIVERY_LOCATION_ERROR_MESSAGE, 422, 0, ['error_type' => 'CreateOrderError']);
            }
        } else {
            $data['order_type'] = OrderAdapter::PICKUP_ORDER;
//            if (isset($data['group_order_token'])) {
//                if ($data['group_order_parent_order_record']['order_type'] == OrderAdapter::DELIVERY_ORDER) {
//                    $data['order_type'] = OrderAdapter::DELIVERY_ORDER;
//                    $data['user_delivery_location_id'] = $data['group_order_parent_order_record']['user_delivery_location_id'];
//                    if ($data['user_id'] == $data['group_order_parent_order_record']['user_id']) {
//                        // this is the admin adding their order so charge them the delivery fee
//                        $data['delivery_amt'] = $data['group_order_parent_order_record']['delivery_amt'];
//                    }
//                }
//                unset($data['group_order_parent_order_record']);
//            }
        }
        $data['skin_id'] = getSkinIdForContext();
        $data['app_version'] = $_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'];
        $data['device_type'] = $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'];
        if ($merchant_resource->trans_fee_type == 'F') {
            // fixed transaction fee
            $data['trans_fee_amt'] = $merchant_resource->trans_fee_rate;
        }
        if ($cart_resource = Resource::createByData($this, $data)) {
            $user_brand_maps_adapter = new UserBrandMapsAdapter(getM());
            $user_brand_map_data = ['user_id'=>$cart_resource->user_id,'brand_id'=>$merchant_resource->brand_id];
            $user_brand_resource = Resource::findOrCreateIfNotExistsByData($user_brand_maps_adapter,$user_brand_map_data);
        }
        return $cart_resource;


    }

    static function setStatusOfCart($cart_ucid, $status)
    {
        $cart_resource = SplickitController::getResourceFromId($cart_ucid, 'Carts');
        $cart_resource->status = $status;
        $cart_resource->save();
    }

    static function staticGetCartResourceWithOrderSummary($ucid)
    {
        $ca = new CartsAdapter();
        return $ca->getCartResourceWithOrderSummary($ucid);
    }

    function getCartResourceWithOrderSummary($ucid)
    {
        if ($cart_resource = Resource::find($this,"$ucid")) {
            if ($order_id = $cart_resource->order_id) {
                $complete_order = CompleteOrder::staticGetCompleteOrder($order_id, getM());
                $order_summary = $complete_order['order_summary'];
                $cart_resource->set('order_summary', $order_summary);
            }
            if (isNotProd()) {
                $cart_resource->set('oid_test_only', $cart_resource->order_id);
            }
            return $cart_resource;
        }
        return null;
    }
}

class CouldNotCreateCartException extends Exception
{
    public function __construct($error_message)
    {
        parent::__construct("Could not create the cart: $error_message", 999);
    }
}

?>