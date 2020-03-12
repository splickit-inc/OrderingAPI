<?php

class OrderAdapter extends MySQLAdapter
{
	/* order statuses */
	const ORDER_EXECUTED = 'E';
	const ORDER_SUBMITTED = 'O';
	const ORDER_IS_IN_PROCESS_CART = 'Y';
	const GROUP_ORDER = 'G';
	const ORDER_PAYMENT_FAILED = 'N';
	const ORDER_CANCELLED = 'C';
	const TEST_ORDER = 'T';
	const ORDER_PENDING = 'P';

	const ORDER_IS_A = 'A'; //Not sure if this is ever possible anymore but have to account for it since it was in the logic.

	/* order types */
	const PICKUP_ORDER = 'R';
	const DELIVERY_ORDER = 'D';

	private $menu_type_index_for_test_orders = 0;

	var $old_style = false;
	var $super_simple = false;

	function setMenuTypeIndexForTestOrders($menu_type_index)
	{
		$this->menu_type_index_for_test_orders = $menu_type_index;
	}


	function __construct($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'`Orders`',
			'%([0-9]{1,15})%',
			'%d',
			array('order_id'),
			NULL,
			array('created','modified')
		);
	}

	function &select($url, $options = NULL)
  {
    $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    return parent::select($url,$options);
  }

  function update(&$resource)
  {
    if ($resource->status == self::ORDER_EXECUTED && $resource->user_id < 20000)
    {
      myerror_log("resetting the status to T becuase test user");
      $resource->status = 'T';
    }

    if ($resource->status == 'T') {
    $resource->payment_file = 'SplickitTest';
  }

  $stamps = $resource->stamp;
  $raw_stamp = getRawStamp();
  if (substr_count($stamps,$raw_stamp) == 0) {
    $resource->stamp = getStamp().';'.$stamps;
  }
    return parent::update($resource);
  }

  /**
   * @desc updates the order status, throws exception if the order cannot be updated
   *
   * @param char $status
   * @param int $order_id
   */
  static function updateOrderStatus($status,$order_id)
  {

    if ($order_id == null || $order_id == 0)
      throw new Exception("ERROR!  order status could not be marked as ".$status." as no order_id was submitted",30);
    else if ($order_id < 1000)
      throw new Exception("ERROR!  order status could not be marked as ".$status." as an INVALID order id was submitted",30);

    $order_adapter = new OrderAdapter($m);
    $resource =& Resource::find($order_adapter, $order_id);
    if ($resource->status == self::GROUP_ORDER) {
      $is_group_order = true;
    }
    if ($resource->status == $status) {
      return true;
    }

    $resource->status = $status;
    return $order_adapter->updateOrderResource($resource);
    $resource->modified = time();
    if ($resource->save()) {
      if ($is_group_order && $status == self::ORDER_EXECUTED) {
        $order_adapter->markChildOrdersExecutedIfNecessary($resource);
      }
      return true;
    } else {
      $error_no = mysqli_errno($order_adapter->_handle);
      if ($error_no == 0)
        return true;
      myerror_log("ERROR TRYING TO UPDATE THE ORDER STATUS IN OrderAdapter.updateOrderStatus: ".$resource->getAdapterError());
      myerror_log("error code is: ".mysqli_errno($order_adapter->_handle));
      MailIt::sendErrorEmailAdam("ERROR! Order status could not be marked as ".$status, "order_id: ".$order_id.".    Will now throw an exception");
      throw new Exception("ERROR!  order status could not be marked as ".$status.": ".$resource->getAdapterError(),30);
    }
  }

  static function updateOrderType($order_data, &$cart_resource){
    if ($cart_resource->order_type == OrderAdapter::PICKUP_ORDER && strtolower($order_data['submitted_order_type']) == 'delivery' && isset($order_data['user_addr_id']) && $order_data['user_addr_id'] > 0) {
      $cart_resource->user_delivery_location_id = $order_data['user_addr_id'];
      $cart_resource->order_type = OrderAdapter::DELIVERY_ORDER;
      $mdi_adapter = new MerchantDeliveryInfoAdapter($m);
      if ($merchant_delivery_price_distance_resource = $mdi_adapter->getMerchantDeliveryPriceResourceForUserLocationAndMerchantId($order_data['user_addr_id'],$cart_resource->merchant_id)) {
        $cart_resource->delivery_amt = $merchant_delivery_price_distance_resource->price;
      } else {
        return createErrorResourceWithHttpCode("We're sorry, this delivery address appears to be outside of our delivery range.",422,999);
      }
    } else if ($cart_resource->order_type == OrderAdapter::DELIVERY_ORDER && strtolower($order_data['submitted_order_type']) == 'pickup'){
      unset($order_data['user_addr_id']);
      $cart_resource->order_type = OrderAdapter::PICKUP_ORDER;
      $cart_resource->user_delivery_location_id = 0;
      $cart_resource->delivery_amt =  0.0;
      $cart_resource->delivery_tax_amt = 0.0;
    }

    return $cart_resource->save();
  }

  function updateOrderResource($order_resource)
	{
		$order_resource->modified = time();
		if ($order_resource->save()) {
			return true;
		} else {
			$error_no = mysqli_errno($this->_handle);
			if ($error_no == 0)
				return true;
			myerror_log("ERROR TRYING TO UPDATE THE ORDER STATUS IN OrderAdapter.updateOrderStatus: ".$order_resource->getAdapterError());
			myerror_log("error code is: ".mysqli_errno($this->_handle));
			MailIt::sendErrorEmailAdam("ERROR! Order status could not be marked as ".$order_resource->status, "order_id: ".$order_resource->order_id.".    Will now throw an exception");
			throw new Exception("ERROR!  order status could not be marked as ".$order_resource->status.": ".$order_resource->getAdapterError(),30);
		}
	}

	function markChildOrdersExecutedIfNecessary($order_resource)
	{
		$order_id = $order_resource->order_id;
		$sql = "SELECT a.* FROM Group_Order_Individual_Order_Maps a JOIN Group_Order b ON a.group_order_id = b.group_order_id WHERE b.order_id = $order_id AND b.group_order_type = 2";
		$options[TONIC_FIND_BY_SQL] = $sql;
		if ($records = $this->select(null,$options)) {
			foreach ($records as $record) {
                $resource = Resource::find($this, $record['user_order_id']);
				if ($resource->status == OrderAdapter::ORDER_SUBMITTED) {
					$resource->status = OrderAdapter::ORDER_EXECUTED;
                    $resource->save();
				}
			}
		}
	}

	static function isStatusASubmittedStatus($status)
	{
		$status = strtoupper($status);
		return ($status == self::ORDER_SUBMITTED || $status == self::ORDER_EXECUTED);
	}

	static function isStatusAnActiveStatus($status)
	{
		$status = strtoupper($status);
		return ($status == self::ORDER_IS_A || $status == self::ORDER_IS_IN_PROCESS_CART || $status == self::ORDER_PAYMENT_FAILED );
	}

	static function isStatusReadyForBilling($status)
	{
		$status = strtoupper($status);
		return ($status == self::ORDER_SUBMITTED || $status == self::ORDER_EXECUTED);

	}

	/**
	 *
	 * will get all open orders that are more than $minutes past their local pickup time.
	 *
	 * returns an array of late order resources.
	 *
	 * @param int $minutes
	 */

	function getOldOpenOrders($minutes)
	{
		$threshold_timestamp = time()-($minutes*60);
		$threshold_dt_tm = date("Y-m-d H:i:s",$threshold_timestamp);
		$old_order_data['status'] = 'O';
		$old_order_data['created'] = array("<"=>$threshold_dt_tm);
		$options[TONIC_FIND_BY_METADATA] = 	$old_order_data;
		$options[TONIC_JOIN_STATEMENT] = " JOIN Merchant ON Orders.merchant_id = Merchant.merchant_id ";
		$options[TONIC_FIND_STATIC_FIELD] = " Merchant.time_zone, Merchant.state ";

		$default_timezone_string = date_default_timezone_get();

		$old_order_resources = Resource::findAll($this,'',$options);
		$old_open_orders = array();
		foreach ($old_order_resources as $order_resource)
		{
			setTheDefaultTimeZone($order_resource->time_zone,$order_resource->state);
			$pickup_timestamp = strtotime($order_resource->pickup_dt_tm);
			if ($pickup_timestamp < $threshold_timestamp)
			{
				myerror_log("we have an open order that was scheduled to be picke up over $minutes minutes ago");
				$old_open_orders[] = $order_resource;
			}
		}
		date_default_timezone_set($default_timezone_string);
		return $old_open_orders;

	}

	static function alertForOrdersStuckInPendingStatus($minutes)
	{
		$order_adapter = new OrderAdapter();
		myerror_log("setting log level to 6 for checking for pending orders");
		$order_adapter->log_level = 6;

		$threshold_timestamp = time() - 23*60*60;

		$threshold_dt_tm = date("Y-m-d H:i:s",$threshold_timestamp);
		myerror_log("We are checking for pending orders in the last 24hrs");
		$pending_order_data['modified'] = array(">"=>$threshold_dt_tm);
		$pending_order_data['status'] = 'P';
		$options[TONIC_FIND_BY_METADATA] = 	$pending_order_data;
		if ($pending_order_resources = Resource::findAll($order_adapter,'',$options))
		{
			$message = "There were ".count($pending_order_resources)." orders stuck in the pending state";

			$order_id_string = "";
			foreach ($pending_order_resources as $pending_order_resource) {
				$order_id_string = $order_id_string . $pending_order_resource->order_id . ",";
			}

			MailIt::sendErrorEmailSupport($message." order_id's: $order_id_string");
			MailIt::sendErrorEmail($message." order_id's: $order_id_string");
		}
	}

	static function setStatusOfStaleOrders($minutes)
	{
		$order_adapter = new OrderAdapter();
		$old_order_resources = $order_adapter->getOldOpenOrders($minutes);
		//the above code used a join so now reset the fields?
		$new_order_adapter = new OrderAdapter();
		foreach ($old_order_resources as $old_order_resource)
		{
			$status = self::ORDER_EXECUTED;
			$old_order_resource->status = $status;
			// must reset the adapter becauset eh join caused bad field names.
			$old_order_resource->_adapter = $new_order_adapter;
			$old_order_resource->save();
			myerror_log("Stale order_id ".$old_order_resource->order_id." has had its status reset to ".$status);

		}

	}

	static function deleteCartOrderRecord($order_id)
	{
		$order_adapter = new OrderAdapter();

		$sql = "DELETE Order_Detail_Modifier FROM Order_Detail_Modifier INNER JOIN Order_Detail ON Order_Detail.order_detail_id = Order_Detail_Modifier.order_detail_id INNER JOIN Orders ON Orders.order_id = Order_Detail.order_id WHERE Orders.status = '".self::ORDER_IS_IN_PROCESS_CART."' AND Orders.order_id = $order_id";
		$order_adapter->_query($sql);

		$sql = "DELETE Order_Detail FROM Order_Detail INNER JOIN Orders ON Orders.order_id = Order_Detail.order_id WHERE Orders.status = '".self::ORDER_IS_IN_PROCESS_CART."' AND Orders.order_id = $order_id";
		$order_adapter->_query($sql);

		$sql = "DELETE FROM Orders WHERE status = '".self::ORDER_IS_IN_PROCESS_CART."' AND Orders.order_id = $order_id";
		$order_adapter->_query($sql);

		return true;
	}

	/*****  these methods are for testing only ******/
	static function getSimpleCartArrayByMerchantId($merchant_id,$merchant_menu_type = 'pickup',$note = 'the note' ,$number_of_items = 1)
	{
		$oa = new OrderAdapter();
		$pre_cart = $oa->getSimpleOrderArrayByMerchantId($merchant_id, $merchant_menu_type, $note, $number_of_items);
		$cart['items'] = $pre_cart['items'];
		$cart['merchant_id'] = $pre_cart['merchant_id'];
		$cart['user_id'] = $pre_cart['user_id'];
		return $cart;
	}

	static function getSuperSimpleCartArrayByMerchantId($merchant_id,$merchant_menu_type = 'pickup',$note = 'the note' ,$number_of_items = 1)
	{
        $oa = new OrderAdapter($mimetypes);
        $oa->super_simple = true;
        $pre_cart = $oa->getSimpleOrderArrayByMerchantId($merchant_id, $merchant_menu_type, $note, $number_of_items);
        $cart['items'] = $pre_cart['items'];
        $cart['merchant_id'] = $pre_cart['merchant_id'];
        $cart['user_id'] = $pre_cart['user_id'];
        return $cart;
	}

	static function staticGetSimpleOrderArrayByMerchantId($merchant_id,$merchant_menu_type = 'pickup',$note = 'the note' ,$number_of_items = 1)
	{
		$oa = new OrderAdapter();
		return $oa->getSimpleOrderArrayByMerchantId($merchant_id, $merchant_menu_type, $note, $number_of_items);
	}

	function getSimpleOrderArrayByMerchantId($merchant_id,$merchant_menu_type,$note,$number_of_items = 1)
  {
    $merchant_menu_map_adapter = new MerchantMenuMapAdapter();
    $data['merchant_id'] = $merchant_id;
    $merchant_menu_type = strtolower($merchant_menu_type);
    if ($merchant_menu_type != 'delivery' && $merchant_menu_type != 'pickup')
      $merchant_menu_type;
    $data['merchant_menu_type'] = $merchant_menu_type;
    $options[TONIC_FIND_BY_METADATA] = $data;
    if ($resource = Resource::findExact($merchant_menu_map_adapter,'',$options))
    {
      $menu_id = $resource->menu_id;
      $order_data = $this->getSimpleOrderArray($menu_id, $merchant_id, $note,$number_of_items);
      return $order_data;
    } else {
      myerror_log("ERROR! No merchant menu mapping found for merchant_id: $merchant_id,   and merchant_menu_type of $merchant_menu_type");
      return null;
    }
  }

	function getSimpleOrderArray($menu_id,$merchant_id,$note,$number_of_items = 1)
	{
		if ($note == null || trim($note) == '')
			$note = 'skip hours';

		$menu = CompleteMenu::getCompleteMenu($menu_id,'Y',$merchant_id);

		$order_data = $this->getSimpleOrderArrayFromFullMenu($menu, $merchant_id, $note,$number_of_items);

    return $order_data;
	}

	function getSimpleOrderArrayFromFullMenu($menu,$merchant_id,$note,$number_of_items = 1,$number_of_modifier_holds = 0,$item_size_ids = 0)
	{
		// now need to get the first item on teh menu
		if (is_int($menu))
		{
			$complete_menu = CompleteMenu::getCompleteMenu($menu,'Y',$merchant_id);
			unset($menu);
			$menu = $complete_menu;
		}

		$total_price = 0.00;
		$menu_max = 0;
		if ($item_size_ids != 0 && is_array($item_size_ids))
		{
			$item_size_adapter = new ItemSizeAdapter();
			foreach ($item_size_ids as $item_size_id)
			{
				$mods = array();
				$record = $item_size_adapter->getRecord(array("item_size_id"=>$item_size_id));
				$size_id_of_item = $record['size_id'];
				$price_of_item = $record['price'];
				$tax_group_id = $record['tax_group'];
				$total_price = $total_price + $price_of_item;
				$item = array('mods'=> $mods,'quantity'=>1,'sizeprice_id'=>$item_size_id);
	    		$items[] = $item;
			}

		} else {
			for ($i=0;$i<$number_of_items;$i++)
			{
				if ($menu['menu_types'][0]['menu_items'][$i-$menu_max])
					;// all is good
				else
					$menu_max = $i;

				$index = $i - $menu_max;
				//$item_size_price_id = $menu['menu_types'][0]['menu_items'][$index]['size_prices'][0]['item_size_id'];
				$item_id = $menu['menu_types'][$this->menu_type_index_for_test_orders]['menu_items'][$index]['size_prices'][0]['item_id'];
				$size_id_of_item = $menu['menu_types'][$this->menu_type_index_for_test_orders]['menu_items'][$index]['size_prices'][0]['size_id'];
				$item_price = $menu['menu_types'][$this->menu_type_index_for_test_orders]['menu_items'][$index]['size_prices'][0]['price'];
				$mods = array();
				$total_price = $total_price + $item_price;
				if ($first_mod_group_id = $menu['menu_types'][0]['menu_items'][$index]['allowed_modifier_groups'][0]['modifier_group_id']) {
					foreach ($menu['modifier_groups'] as $modifier_group)
					{
						if ($modifier_group['modifier_group_id'] == $first_mod_group_id) {
							foreach($modifier_group['modifier_items'] as $modifier_item) {
                                $added = false;
								$modifier_size_maps = $modifier_item['modifier_size_maps'];
								foreach ($modifier_size_maps as $modifier_size_map)
								{
									if ($added) {
                                        continue;
									}
									if ($modifier_size_map['size_id'] == 0 || $modifier_size_map['size_id'] == $size_id_of_item) {
										$modifier_size_price_id = $modifier_size_map['modifier_size_id'];
										//$mods[] = array("mod_quantity"=>1,"mod_sizeprice_id"=>$modifier_size_price_id);
										if ($this->old_style) {
											$mods[] = array("mod_quantity"=>1,"modifier_item_id"=>$modifier_size_map['modifier_item_id'],"mod_sizeprice_id"=>$modifier_size_price_id);
										} else {
											$mods[] = array("mod_quantity"=>1,"modifier_item_id"=>$modifier_size_map['modifier_item_id']);
										}
										$added = true;
										$modifier_price = $modifier_size_map->modifier_price;
										$total_price = $total_price + $modifier_price;
										if ($this->super_simple) {
                                            break 3;
										}
									}
								}
							}
						}
					}
				}

				//if ($modifier_size_price_id)
				//	$mods[] = array("mod_quantity"=>1,"mod_sizeprice_id"=>$modifier_size_price_id);
		    	//$item = array('mods'=> $mods,'quantity'=>1,'sizeprice_id'=>$item_size_price_id);
		    	if ($this->old_style) {
					$item_size_id = $menu['menu_types'][$this->menu_type_index_for_test_orders]['menu_items'][$index]['size_prices'][0]['item_size_id'];
					$item = array('mods'=> $mods,'quantity'=>1,'item_id'=>$item_id,'size_id'=>$size_id_of_item,'sizeprice_id'=>$item_size_id);
				} else {
					$item = array('mods'=> $mods,'quantity'=>1,'item_id'=>$item_id,'size_id'=>$size_id_of_item);
				}


		    	$items[] = $item;
			}
		}

    if ($_SERVER['AUTHENTICATED_USER'])
      $user_id = $_SERVER['AUTHENTICATED_USER']['uuid'];
    else
      $user_id = 101;

    $order_data['user_id'] = $user_id;
    $order_data['items'] = $items;
    $order_data['note'] = $note;
    $order_data['sub_total'] = $total_price;
    $tax_amt = $total_price*.1;
    $order_data['tax_amt'] = $tax_amt;
    //$order_data['merchant_id'] = $this->merchant['merchant_id'];
    $order_data['merchant_id'] = $merchant_id;

    //generate random tip to prevent duplicate order error
//    $tip = rand(100,1000)/100;
//    $tip = number_format($tip,2);
    $order_data['tip'] = 0.00;
    $order_data['grand_total'] = $total_price + $tax_amt + $tip;
    return $order_data;
	}

	function getCartArrayWithOneModierPerModifierGroup($merchant_id,$merchant_menu_type = 'pickup',$number_of_items = 1,$item_size_ids = 0,$number_of_modifiers = 10)
	{
        $merchant_menu_map_adapter = new MerchantMenuMapAdapter($mimetypes);
        $data['merchant_id'] = $merchant_id;
        $merchant_menu_type = strtolower($merchant_menu_type);
        $data['merchant_menu_type'] = $merchant_menu_type;
        $options[TONIC_FIND_BY_METADATA] = $data;
        if ($resource = Resource::findExact($merchant_menu_map_adapter,'',$options)) {
            $cart_data = $this->getCartArrayFromFullMenuWithOneModiferPerModifierGroup($resource->menu_id,$merchant_id,'note',$number_of_items,$item_size_ids,$number_of_modifiers);
            //$cart_data['merchant_id'] = $merchant_id;
            return $cart_data;
        } else {
            myerror_log("ERROR! No merchant menu mapping found for merchant_id: $merchant_id,   and merchant_menu_type of $merchant_menu_type");
            return null;
        }
	}

	function getItemsForCartWithOneModifierPerModifierGroup($complete_menu,$number_of_items = 1)
	{
		$cart_items = [];
		$count = 0;
		while ($count < $number_of_items) {
			foreach ($complete_menu['menu_types'] as $menu_type) {
				if ($count == $number_of_items) {
					continue;
				}
				foreach ($menu_type['menu_items'] as $complete_menu_item) {
					if ($count == $number_of_items) {
						continue;
					}
					$size_id = $complete_menu_item['size_prices'][0]['size_id'];
					$cart_items[] = $this->getItemForCartWithOneModifierPerModifierGroup($complete_menu_item,$size_id);
					$count++;
				}
			}
		}

		return $cart_items;
	}

	function getItemForCartWithOneModifierPerModifierGroup($complete_menu_item,$size_id)
    {
    	$item_id = $complete_menu_item['item_id'];
    	$mods = [];
    	foreach ($complete_menu_item['modifier_groups'] as $modifier_group) {
    		$added = false;
    		foreach ($modifier_group['modifier_items'] as $modifier_item) {
    			foreach ($modifier_item['modifier_prices_by_item_size_id'] as $mpbisi) {
    				if ($size_id == $mpbisi['size_id']) {
    					$mods[] = ['mod_quantity'=>1,"modifier_item_id"=>$modifier_item['modifier_item_id']];
    					$added = true;
    					break;
					}
				}
    			if ($added) {
    				break;
				}
			}
		}
		$cart_item = array('mods'=> $mods,'quantity'=>1,'item_id'=>$item_id,'size_id'=>$size_id);
    	return $cart_item;
    }

	function getCartArrayFromFullMenuWithOneModiferPerModifierGroup($menu,$merchant_id,$note,$number_of_items = 1,$item_size_ids = 0,$number_of_modifiers = 1)
	{
		// now need to get the first item on teh menu
		if (is_numeric($menu)) {
			$complete_menu = CompleteMenu::getCompleteMenu($menu,'Y',$merchant_id);
			unset($menu);
			$menu = $complete_menu;
		}
		$total_price = 0.00;
		$menu_max = 0;
		if ($item_size_ids != 0 && is_array($item_size_ids))
		{
			$item_size_adapter = new ItemSizeAdapter();
			foreach ($item_size_ids as $item_size_id)
			{
				$mods = array();
				$record = $item_size_adapter->getRecord(array("item_size_id"=>$item_size_id));
				$size_id_of_item = $record['size_id'];
				$price_of_item = $record['price'];
				$tax_group_id = $record['tax_group'];
				$total_price = $total_price + $price_of_item;
				$item = array('mods'=> $mods,'quantity'=>1,'sizeprice_id'=>$item_size_id);
				$items[] = $item;
			}

		} else {
			for ($i=0;$i<$number_of_items;$i++)
			{
				if ($menu['menu_types'][0]['menu_items'][$i-$menu_max])
					;// all is good
				else
					$menu_max = $i;

				$index = $i - $menu_max;
				//$item_size_price_id = $menu['menu_types'][0]['menu_items'][$index]['size_prices'][0]['item_size_id'];
				if (getProperty("skip_first_menu_type")) {
					$mti = 1;
				} else {
					$mti = 0;
				}
				$item_id = $menu['menu_types'][$mti]['menu_items'][$index]['size_prices'][0]['item_id'];
				$size_id_of_item = $menu['menu_types'][$mti]['menu_items'][$index]['size_prices'][0]['size_id'];
				$item_price = $menu['menu_types'][$mti]['menu_items'][$index]['size_prices'][0]['price'];
				$mods = array();
				$total_price = $total_price + $item_price;
				//if ($first_mod_group_id = $menu['menu_types'][0]['menu_items'][$index]['allowed_modifier_groups'][0]['modifier_group_id'])
				$mods_string = $menu['api_version'] == 2 ? "modifier_groups" : "allowed_modifier_groups";
				if (count($menu['menu_types'][$mti]['menu_items'][$index]["$mods_string"]) > 0) {
					$modifier_groups_hash_by_modifier_group_id = createHashmapFromArrayOfArraysByFieldName($menu['test_modifier_groups'],'modifier_group_id');
					//$allowed_modifier_group_hash_by_id = createHashmapFromArrayOfArraysByFieldName($menu['menu_types'][0]['menu_items'][$index]['allowed_modifier_groups'],'modifier_group_id');
					$allowed_groups = $menu['menu_types'][$mti]['menu_items'][$index]["$mods_string"];
					foreach ($allowed_groups as $item_modifier_group_map_record) {
						if (count($mods) >= $number_of_modifiers) {
							continue;
						}
						if ($modifier_group = $modifier_groups_hash_by_modifier_group_id[$item_modifier_group_map_record['modifier_group_id']]) {
							$added = false;
							foreach($modifier_group['modifier_items'] as $modifier_item)
							{
								if ($added) {
									continue;
								}
								$modifier_size_maps = $this->getModifierSizeMaps($modifier_item);
								foreach ($modifier_size_maps as $modifier_size_map)
								{
									if ($added) {
										continue;
									} else if ($modifier_size_map['size_id'] == 0 || $modifier_size_map['size_id'] == $size_id_of_item) {
										$modifier_size_price_id = $modifier_size_map['modifier_size_id'];
										//$mods[] = array("mod_quantity"=>1,"mod_sizeprice_id"=>$modifier_size_price_id);
										$mods[] = array("mod_quantity"=>1,"modifier_item_id"=>$modifier_size_map['modifier_item_id']);
										$added = true;
										$modifier_price = $modifier_size_map->modifier_price;
										$total_price = $total_price + $modifier_price;
									}
								}
							}
						}
					}
				}

				//if ($modifier_size_price_id)
				//	$mods[] = array("mod_quantity"=>1,"mod_sizeprice_id"=>$modifier_size_price_id);
				//$item = array('mods'=> $mods,'quantity'=>1,'sizeprice_id'=>$item_size_price_id);
				$item = array('mods'=> $mods,'quantity'=>1,'item_id'=>$item_id,'size_id'=>$size_id_of_item,'item_total_price'=>$total_price);
				$items[] = $item;
			}
		}

		if ($_SERVER['AUTHENTICATED_USER'])
			$user_id = $_SERVER['AUTHENTICATED_USER']['user_id'];
		else
			$user_id = 101;
		$order_data['user_id'] = $user_id;
		$order_data['items'] = $items;
		$order_data['note'] = $note;
		$order_data['merchant_id'] = $merchant_id;
		$order_data['total_price'] = $total_price;
		return $order_data;
	}

	function getModifierSizeMaps($modifier_item)
	{
		if (isset($modifier_item['nested_items'])) {
			return $modifier_item['nested_items'][0]['modifier_size_maps'];
		} else {
			return $modifier_item['modifier_size_maps'];
		}
	}

  function getOrderArrayFromFullMenuWithOneModiferPerModifierGroup($menu,$merchant_id,$note,$number_of_items = 1,$item_size_ids = 0,$number_of_modifiers = 1)
  {
  $order_data = $this->getCartArrayFromFullMenuWithOneModiferPerModifierGroup($menu,$merchant_id,$note,$number_of_items,$item_size_ids,$number_of_modifiers);

  $order_data['sub_total'] = $order_data['total_price'];
    $tax_amt = $order_data['total_price']*.1;
    $order_data['tax_amt'] = $tax_amt;
    //$order_data['merchant_id'] = $this->merchant['merchant_id'];


    //generate random tip to prevent duplicate order error
    $tip = rand(100,1000)/100;
    $tip = number_format($tip,2);
    $order_data['tip'] = $tip;
    $order_data['grand_total'] = round(floatval($order_data['total_price']) + floatval($tax_amt) + floatval($tip),2);
    return $order_data;
  }

	function getSimpleOrderJSON($menu_id,$merchant_id)
	{
		$order_data = $this->getSimpleOrderArray($menu_id, $merchant_id,$note);
		$json['jsonVal'] = $order_data;
    $json_encoded_data = json_encode($json);
    return $json_encoded_data;
	}

	function getSimpleOrderJSONByMerchantId($merchant_id,$merchant_menu_type,$note)
	{
		$order_data = $this->getSimpleOrderArrayByMerchantId($merchant_id, $merchant_menu_type, $note);
		$json['jsonVal'] = $order_data;
    $json_encoded_data = json_encode($json);
    return $json_encoded_data;
	}

	function getSimpleOrderJSONFromFullMenu($menu,$merchant_id,$note,$number_of_items = 1)
	{
		$order_data = $this->getSimpleOrderArrayFromFullMenu($menu, $merchant_id, $note,$number_of_items);
		$json['jsonVal'] = $order_data;
    $json_encoded_data = json_encode($json);
    return $json_encoded_data;
  }
}
?>
