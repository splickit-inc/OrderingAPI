<?php

class CompleteOrder extends MySQLAdapter
{
	var $do_not_show_loyalty_in_payment_section = false;
	
	const CC_CHARGED_LABEL = 'Credit Card Charged';
	const AMICIS_FEE_LABEL = 'SF Surcharge';
	const FEE_LABEL = "Convenience Fee";

	static function getBaseOrderDataAsResource($order_id,$mimetypes)
	{
		$base_order_data = CompleteOrder::getBaseOrderData($order_id, $mimetypes);
		$resource = Resource::factory(new OrderAdapter($mimetypes),$base_order_data);
		return $resource;
	}
		
	static function getBaseOrderData($order_id,$mimetypes)
	{
		myerror_logging(2,"starting the getBaseOrderDetails order with order_id: ".$order_id);
		if ($order_id == null || trim($order_id) == '') {
			myerror_log("No order id submitted");
			return false;
		}
		$adapter = new OrderAdapter($mimetypes);
		if (is_numeric($order_id)) {
			$options[TONIC_FIND_BY_METADATA]['order_id'] = $order_id;
		} else {
			$options[TONIC_FIND_BY_METADATA]['ucid'] = $order_id;
		}

		if ($the_order = $adapter->select('',$options))
		{
			myerror_logging(3,"we have the order");
			$the_order = array_pop($the_order);
			$order_id = $the_order['order_id'];
			$options = array();

			//get merchant information
			$merchant_adapter = new MerchantAdapter($mimetypes);
			if (! $merchant = $merchant_adapter->select($the_order['merchant_id'],$options))
			{
				//throw new Exception('ERROR could not get Merchant as part of order build in CompleteOrder class');
				myerror_log("ERROR!  could not get Merchant as part of order build in CompleteOrder class for order_id: ".$order_id);
				return false;
			}
			
			$merchant = array_pop($merchant);
			
			// fixed taxes stuff
			$fixed_tax_list = FixedTaxAdapter::staticGetFixedTaxRecordsHashMappedByName($merchant['merchant_id']);
			if (count($fixed_tax_list) > 0) {
				$the_order['fixed_tax_list'] = $fixed_tax_list;
			}
			
			//get customer information
			$user_adapter = new UserAdapter($mimetypes);
			if (! $user = $user_adapter->select($the_order['user_id'],$options))
			{
				//throw new Exception('ERROR could not get user as part of order build in CompleteOrder class');\
				myerror_log("ERROR!  could not get user as part of order build in CompleteOrder class for order_id: ".$order_id);
				return false;
			}
			$user = array_pop($user);

			// dont do promo logic for G records (group orders)  this is for the parent of Type 2 and the Children of Type 1
			if ($the_order['status'] != 'G') {
				if ($the_order['promo_amt'] != 0.00) {
					if ($promo_id = $the_order['promo_id']) {
						if ($promo_resource = Resource::find(new PromoAdapter($mimetypes),$promo_id)) {
							//resolve to a positive number for calcs possibly
							$open_order_promo_amt_positive = -$the_order['promo_amt'];
							$open_order_promo_amt_positive = sprintf("%01.2f", $open_order_promo_amt_positive);

							$the_order['promo_payor'] = $promo_resource->payor_merchant_user_id;
							if ($promo_external_id = PromoBrandExternalIdMapAdapter::staticGetExternalIdForPromoBrandMaping($promo_id, $merchant['brand_id'])) {
								$the_order['promo_external_id_for_brand'] = $promo_external_id;
							}
						}
					}
				}
			}

			myerror_logging(2,"have merchant stuff in CompleteOrder");
				
///*			//set time zone for merchant so printed times are correct
// CHANGE_THIS

			$tzone_string = date_default_timezone_get();
			myerror_logging(3,'the tzone string is: '.$tzone_string);
				
//			if ($_SERVER['GLOBAL_PROPERTIES']['server'] == 'test')
			{
				myerror_logging(3,"about to set the default time zone to that of the merchant in complete order: ".$merchant['time_zone']);
				setTheDefaultTimeZone($merchant['time_zone'],$merchant['state']);
			}
			myerror_logging(3,"the default time zone is now: ".date_default_timezone_get());
			$now_string = date('Y-m-d H:i');
			myerror_logging(3,"current time as far as this server goes is: ".$now_string);
//*/
			//THIS IS IMPORTANT AND STUPID
			$the_order['pickup_dt_tm'] = strtotime($the_order['pickup_dt_tm']);
			$the_order['order_dt_tm'] = strtotime($the_order['order_dt_tm']);

			// the order is due more than 12 hours from the order time, we consider it a future order.
			$hours_in_the_future = ($the_order['pickup_dt_tm'] - $the_order['order_dt_tm'])/60/60;
			if ($hours_in_the_future > 12) {
				$the_order['future_order'] = 'true';
			} else {
                $the_order['future_order'] = 'false';
			}

			$pickup_time = date('g:i',$the_order['pickup_dt_tm']);
			$pickup_time_ampm = date('g:i A',$the_order['pickup_dt_tm']);
			$pickup_time_military = date('H:i',$the_order['pickup_dt_tm']);
			$pickup_time_military_with_seconds = date('H:i:s',$the_order['pickup_dt_tm']);
			$pickup_date = date('m/d/y',$the_order['pickup_dt_tm']);
			$pickup_date2 = date('Y-m-d',$the_order['pickup_dt_tm']);
			$pickup_date_time = date('M d, Y   G:i',$the_order['pickup_dt_tm']);
			$pickup_date_time2 = date('Y-m-d H:i:s',$the_order['pickup_dt_tm']);
			$pickup_date_time3 = date('n/j g:iA',$the_order['pickup_dt_tm']);
			$pickup_date_time4 = date('m/d/Y H:i:s',$the_order['pickup_dt_tm']);
			$pickup_date_time_foundry = date('Y/m/d H:i',$the_order['pickup_dt_tm']);

			$pickup_date_time_task_retail = date("Y-m-d\TH:i:00.0000",$the_order['pickup_dt_tm']);
			$pickup_day_string = date('l',$the_order['pickup_dt_tm']);
			$pickup_month_string = date('M',$the_order['pickup_dt_tm']);
			$pickup_day_of_month_string = date('j',$the_order['pickup_dt_tm']);
			$pickup_day = date('D',$the_order['pickup_dt_tm']);
			$order_date = date('m/d/y',$the_order['order_dt_tm']);
			$order_date2 = date('Y-m-d',$the_order['order_dt_tm']);
			$order_date_time3 = date('n/j g:iA',$the_order['order_dt_tm']);
            $order_date_time_task_retail = date("Y-m-d\TH:i:00.0000",$the_order['order_dt_tm']);
            $ready_time_at_merchant = date('H:i:s',$the_order['ready_timestamp']);
            $ready_time_at_merchant_for_doordash = date(DATE_ATOM,$the_order['ready_timestamp']);

            $order_day = date('D',$the_order['order_dt_tm']);
			$order_time = date('g:i',$the_order['order_dt_tm']);
			if ($order_day != $pickup_day)
				$the_order['advance_order'] = 'true';
			else
				$the_order['advance_order'] = 'false';
			
			$the_order['first_name']=$user['first_name'];
			$the_order['last_name']=$user['last_name'];
			$the_order['customer_first_name']=$user['first_name'];
			$the_order['full_name']=$user['first_name'].' '.$user['last_name'];
			$the_order['customer_full_name']=$user['first_name'].' '.$user['last_name'];
			$the_order['user_email']=$user['email'];
			$the_order['user_phone_no']=$user['contact_no'];
			$the_order['pickup_date_time']=$pickup_date_time;
			$the_order['pickup_date_time2']=$pickup_date_time2;
			$the_order['pickup_date_time4']=$pickup_date_time4;
			$the_order['pickup_date_time_foundry']=$pickup_date_time_foundry;
			$the_order['pickup_time']=$pickup_time;
			//Line Buster Stuff
			//if ($user['email'] == $merchant['numeric_id']."_manager@dummy.com")
			//	$the_order['pickup_time']="In Store Now!";
			$the_order['pickup_time_ampm']=$pickup_time_ampm;
			$the_order['pickup_time_military']=$pickup_time_military;
			$the_order['pickup_time_military_with_seconds']=$pickup_time_military_with_seconds;
			$the_order['pickup_date']=$pickup_date;
			$the_order['pickup_date2']=$pickup_date2;
			$the_order['pickup_date3']=$pickup_date_time3;
            $the_order['pickup_date_task_retail'] = $pickup_date_time_task_retail;
            $the_order['ready_time_at_merchant'] = $ready_time_at_merchant;
            $the_order['ready_time_at_merchant_for_doordash'] = $ready_time_at_merchant_for_doordash;
			$the_order['pickup_day']=$pickup_day_string;
            $the_order['pickup_day_of_month']=$pickup_day_of_month_string;
			$the_order['pickup_month'] = $pickup_month_string;
			$the_order['order_day']=$order_day;
			$the_order['order_date']= $order_date;
			$the_order['order_date2']= $order_date2;
			$the_order['order_date3']= $order_date_time3;
            $the_order['order_date_task_retail'] = $order_date_time_task_retail;
            $the_order['order_time']=$order_time;
			$the_order['merchant_name']=$merchant['name'];
			$the_order['merchant_addr']=$merchant['address1'];
			$the_order['merchant_phone_no']=$merchant['phone_no'];
			$the_order['merchant_external_id'] = $merchant['merchant_external_id'];
			$the_order['brand_id']=$merchant['brand_id'];
			$the_order['merchant_city_st_zip']=$merchant['city'].', '.$merchant['state'].' '.$merchant['zip'];
			$the_order['day_of_week'] = date('l',$the_order['pickup_dt_tm']);
			$the_order['order_identifier'] = substr($order_id, -3);

			$the_order['positive_promo_amount'] = number_format(-$the_order['promo_amt'],2);
			
			//check for loyalty
			$sa = new SkinAdapter(getM());
			$skin_record = $sa->getRecordFromPrimaryKey($the_order['skin_id']);
			if ($skin_brand_id = $skin_record['brand_id'])
			{
		    	$ubl_adapter = new UserBrandPointsMapAdapter($mimetypes);
		    	myerror_logging(3, "about to get the loyalty number for user_id: ".$the_order['user_id']);
		    	if ($user_brand_loyalty_resource = $ubl_adapter->getExactResourceFromData(array("user_id"=>$the_order['user_id'],"brand_id"=>$skin_brand_id)))
		    	{
			    	$loyalty_number = $user_brand_loyalty_resource->loyalty_number;
			    	myerror_logging(3, "loyalty number is: ".$loyalty_number);
			    	$the_order['loyalty_number'] = $loyalty_number;
			    	$the_order['brand_loyalty_number'] = $loyalty_number;
			    	
		    	}
			}
			
			//Matre'D fields
			//$the_order['matre_d_media_amount'] = $the_order['order_amt'] + $the_order['tip_amt'] + $the_order['delivery_amt'] + $the_order['total_tax_amt'];
			$the_order['matre_d_media_amount'] = $the_order['order_amt'] + $the_order['total_tax_amt'];
			
			//$order_as_string = print_r($the_order,true);
			//myerror_log("the order meta data in complete order: ".$order_as_string);
			
			$the_order['user']=$user;
			$the_order['merchant']=$merchant;			
			
			if ($user['user_id'] < 1000) {
				$the_order['test'] = 'test';
				$the_order['test_message'] = '- TEST ORDER DO NOT MAKE -';
			}
			
			// if delivery, get the delivery location
			if ($the_order['user_delivery_location_id'] != null && $the_order['user_delivery_location_id'] != 0) {
				$user_delivery_location_resource = Resource::find(new UserDeliveryLocationAdapter($mimetypes),$the_order['user_delivery_location_id']);
				if ($user_delivery_location_resource->user_addr_id == 100) {
					$user_delivery_location_resource->phone_no = $user['contact_no'];
					$user_delivery_location_resource->instructions = 'Call Customer: '.$user['contact_no'];
				}
				$user_delivery_location_resource->name = ($user_delivery_location_resource->business_name != null && trim($user_delivery_location_resource->business_name) != '') ? $user_delivery_location_resource->name.', '.$user_delivery_location_resource->business_name : $user_delivery_location_resource->name;
				$the_order['delivery_info'] = $user_delivery_location_resource;
			}
			date_default_timezone_set($tzone_string);
			
			// creat the order error message to be used in the case of message failure
			$the_order['late_order_message_sms'] = CompleteOrder::createFailedOrderMessageSMS($the_order, $merchant);

			$order = new Order($order_id);
			if ($order->isCateringOrder()) {
				// get catering info
                if ($catering_info = $order->getCateringInfo()) {
                    $the_order['catering'] = 'Y';
                    $the_order['catering_info'] = $catering_info;
				}
			}
			
			return $the_order;
		
		} else {
			myerror_log("ERROR!  could not locate order record for order_id: ".$order_id);
			return false;
		}			
	}
	
	static function createFailedOrderMessageSMSFromOrderId($order_id)
	{
		$order_adapter = new OrderAdapter(getM());
		$order_data = $order_adapter->getRecord(array("order_id"=>$order_id));
		return CompleteOrder::createFaildOrderMessageSMSFromOrderData($order_data); 
	}
	
	static function createFaildOrderMessageSMSFromOrderData($order_data)
	{
		$merchant_id = $order_data['merchant_id'];
		$merchant_adapter = new MerchantAdapter($mimetypes);
		$merchant_data = $merchant_adapter->getRecord(array("merchant_id"=>$merchant_id));
		return CompleteOrder::createFailedOrderMessageSMS($order_data, $merchant_data);		
	}	
	
	static function createFailedOrderMessageSMS($order_data,$merchant_data)
	{
			$body = "merchant: ".$merchant_data['name'].chr(10);
			$body .= "merchant_id: ".$merchant_data['merchant_id'].chr(10);
			$body .= "phone: ".$merchant_data['phone_no'].chr(10);
			$body .= "order_id: ".$order_data['order_id'].chr(10);
			return $body;
	}

	static function getCompleteOrderAsResource($order_id,$mimetypes)
	{
		$order_data = CompleteOrder::staticGetCompleteOrder($order_id, $mimetypes);
		$resource = Resource::factory(new OrderAdapter($mimetypes),$order_data);
		return $resource;
	}
	
	static function getBetterOrderDetailModifiers($order_id,$mimetypes)
	{
		$order_detail_modifier_adapter = new OrderDetailModifierAdapter($mimetypes);
		$order_data['order_id'] = $order_id;
		$order_options[TONIC_FIND_BY_METADATA] = $order_data;
		$order_options[TONIC_JOIN_STATEMENT] = " JOIN Order_Detail ON Order_Detail.order_detail_id = Order_Detail_Modifier.order_detail_id ";
		$order_options[TONIC_FIND_BY_STATIC_METADATA] = " Order_Detail.order_id = $order_id ";
		$order_options[TONIC_SORT_BY_METADATA] = ' modifier_item_priority DESC ';
		if ($order_detail_modifiers = $order_detail_modifier_adapter->select(null,$order_options))
		{
			foreach ($order_detail_modifiers as $order_detail_modifier)
			{
				myerror_logging(5,'adding the modifier '.$order_detail_modifier['mod_print_name'].'  of record_id: '.$order_detail_modifier['order_detail_mod_id']);
				$hold_it = $order_detail_modifier['hold_id'];
				$comes_with = $order_detail_modifier['comes_with'];
				$modifier_type = $order_detail_modifier['modifier_type'];
				$better_order_detail_modifiers[$order_detail_modifier['order_detail_id']][] = $order_detail_modifier; 
			}
		}
		return $better_order_detail_modifiers;		
	}

	static function staticGetCompleteOrder($order_id,$mimetypes,$rewarder = false)
	{
		$complete_order = new CompleteOrder($mimetypes)	;
		return $complete_order->getCompleteOrder($order_id, $mimetypes,$rewarder);
	}
	
	function getCompleteOrder($order_id,$mimetypes,$rewarder = false)
	{
		myerror_logging(3, "Starting getCompleteOrder");
		if ($the_order = $this->getBaseOrderData($order_id, $mimetypes))
		{
            $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
			myerror_logging(2,"we have the base order now get the details");
			$order_id = $the_order['order_id'];
			// reset log level for order query so we dont get an overload of SELECT statements
			$server_log_level = $_SERVER['log_level'];
			if ($server_log_level < 5)
				$_SERVER['log_level'] = 1;	
				
			// now get the order details (items)
			$order_detail_adapter = new OrderDetailAdapter($mimetypes);
			$options[TONIC_FIND_BY_METADATA]['order_id'] = $the_order['order_id'];
			if (! $order_details = $order_detail_adapter->select('',$options)) {
				if ($the_order['status'] == 'G') {
					//we should only get here for TYPE 2 group orders
					$group_order_info = array();
					$order_details = array();
					$goma = new GroupOrderIndividualOrderMapsAdapter($m);
					// need to get all the order details for the individual group orders
					$maps = $goma->getSubmittedChildRecordsBasedOnGroupOrderToken($the_order['ucid']);
					foreach ($maps as $map) {
						$child_order_id = $map['user_order_id'];
						$child_order = CompleteOrder::staticGetCompleteOrder($child_order_id);
						foreach ($child_order['order_details'] as &$child_order_detail) {
							$child_order_detail['note'] = 'For '.ucwords($child_order['user']['first_name']).' '.ucwords(substr($child_order['user']['last_name'],0,1)).'. '.$child_order_detail['note'];
							$child_order_detail['name'] = $child_order['user']['first_name'].' '.substr($child_order['user']['last_name'],0,1);
						}
						$order_details = array_merge($order_details,$child_order['order_details']);
						$group_order_info[] = array($child_order['first_name'].' '.substr($child_order['last_name'],0,1).'  '.$child_order_id,"Promo Amt: $".-$child_order['promo_amt'],"Grand Total: $".$child_order['grand_total']);

						$sql = "SELECT * FROM Balance_Change WHERE order_id = $child_order_id AND process = 'CCpayment' ORDER BY id DESC limit 1";
						$options[TONIC_FIND_BY_SQL] = $sql;
						if ($results = $balance_change_adapter->select(null,$options)) {
							$row = $results[0];
							//add ucid
							$row['ucid'] = $child_order['ucid'];
							// now get last 4 from user and add to the row
							$last_four = $the_order['user']['last_four'] == null ? '0000' : $the_order['user']['last_four'];
							$row['last_four'] = $last_four;
							$balance_change_records[$child_order_id] = $row;
						} else {
                            $sql = "SELECT * FROM Balance_Change WHERE order_id = $child_order_id AND process = 'Authorize' ORDER BY id DESC limit 1";
                            $options[TONIC_FIND_BY_SQL] = $sql;
                            if ($results = $balance_change_adapter->select(null,$options)) {
                                $row = $results[0];
                                //add ucid
                                $row['ucid'] = $child_order['ucid'];
                                // now get last 4 from user and add to the row
                                $last_four = $the_order['user']['last_four'] == null ? '0000' : $the_order['user']['last_four'];
                                $row['last_four'] = $last_four;
                                $balance_change_records[$child_order_id] = $row;
                            }
						}
					}
					$the_order['group_order_payments'] = $balance_change_records;
				} else if ($order_id != 0 && $the_order['order_qty'] > 0) {
					if ($the_order['order_dt_tm'] > time()-(30*24*60*60)) {
						throw new MissingOrderDetailsException($order_id);
					}
                } else if ($order_id != 0 && $the_order['order_qty'] == 0) {
                    return $the_order;
                }
            } else {
				$all_order_detail_modifiers = CompleteOrder::getBetterOrderDetailModifiers($order_id, $mimetypes);

				// now get the order detail modifiers()
				$item_adapter = new ItemAdapter($mimetypes);
				foreach ($order_details as &$order_detail)
				{
					myerror_logging(4,"getting the order details");
					// get additional info
					$sql = "SELECT b.*,a.size_id FROM Item_Size_Map a JOIN Item b ON a.item_id = b.item_id WHERE a.item_size_id = ".$order_detail['item_size_id'];
					$item_options[TONIC_FIND_BY_SQL] = $sql;
					$item_select_resource = Resource::find($item_adapter, $url, $item_options);
					$order_detail['menu_type_id'] = $item_select_resource->menu_type_id;
					$order_detail['size_id'] = $item_select_resource->size_id;
					$order_detail['item_id'] = $item_select_resource->item_id;

					{
						// i know i could do this with a single foreach but keeping it compartmentalized like this is more readable and perhaps more flexible
						// the loop is so small that its not really a performance hit.

						// first get any price adjustments and load them up in an array with teh modifier group as the index
						$order_detail_price_adjustments = array();
						foreach ($all_order_detail_modifiers[$order_detail['order_detail_id']] as $mod_record)
						{
							if ($mod_record['modifier_type'] == 'A')
							{
								myerror_logging(3,"we have an override! group_id: ".$mod_record['modifier_group_id']."    price=".$mod_record['mod_total_price']);
								$order_detail_price_adjustments[$mod_record['modifier_group_id']] = -$mod_record['mod_total_price'];
							}

						}
						// get ALL the mods that were ordered (including comes with)
						// using the '&' on this one since we may need to adjust price
						$order_detail_modifiers = array();
						foreach ($all_order_detail_modifiers[$order_detail['order_detail_id']] as &$mod_record_all)
						{
							if ($mod_record_all['modifier_type'] == 'Q'){
								$order_detail['quantity'] = $mod_record_all['mod_quantity'];
								$order_detail['item_print_name'] = $order_detail['item_print_name'] . " (X".$order_detail['quantity'].")";
								myerror_logging(3,"we have an quantity modifier group group_id: ".$mod_record_all['modifier_group_id']."    price=".$mod_record_all['mod_quantity']. " name=".$order_detail['item_print_name']);
							}

							if ($mod_record_all['hold_it'] == 'N' && $mod_record_all['modifier_type'] == 'T')
							{
								// adjust price if necessary
								$group_id = $mod_record_all['modifier_group_id'];
								if ($override_price = $order_detail_price_adjustments[$group_id])
								{
									if ($override_price < .01)
										;  // do nothing, all used up
									else if ($mod_record_all['mod_total_price'] < $override_price) {
										// price of modifier is less than the override
										$order_detail_price_adjustments[$group_id] = $override_price - $mod_record_all['mod_total_price'];
										$mod_record_all['mod_total_price'] = 0.00;
									} else {
										// override is less than total price of the modifier
										$mod_record_all['mod_total_price'] = $mod_record_all['mod_total_price'] - $override_price;
										$order_detail_price_adjustments[$group_id] = 0.00;
									}

								}
								$order_detail_modifiers[] = $mod_record_all;
							}
						}
						$order_detail['order_detail_modifiers'] = $order_detail_modifiers;

						// now get any sides that are part of this item like make it a meal stuff
						$order_detail_sides = array();
						foreach ($all_order_detail_modifiers[$order_detail['order_detail_id']] as $mod_record)
						{
							if ($mod_record['hold_it'] == 'N' && $mod_record['modifier_type'] == 'S')
							{
								$order_detail_sides[] = $mod_record;
							}
						}
						$order_detail['order_detail_sides'] = $order_detail_sides;

						// now get any meal deal mods
						$order_detail_mealdeal = array();
						$mod_record = null;
						foreach ($all_order_detail_modifiers[$order_detail['order_detail_id']] as $mod_record)
						{
							$modifier_type_root = substr($mod_record['modifier_type'], 0,1);
							if ($mod_record['hold_it'] == 'N' && strtoupper($modifier_type_root) == 'I')
								$order_detail_mealdeal[] = $mod_record;
						}
						$order_detail['order_detail_mealdeal'] = $order_detail_mealdeal;

						// here is where we will create the group that has all the things on the item
						$order_detail['order_detail_complete_modifier_list_no_holds'] = array_merge($order_detail_modifiers,$order_detail_sides,$order_detail_mealdeal);

						// now get the mods that 'comes with' this item
						$order_detail_comeswith_modifiers = array();
						$mod_record = null;
						foreach ($all_order_detail_modifiers[$order_detail['order_detail_id']] as $mod_record)
							if ($mod_record['hold_it'] == 'N' && $mod_record['modifier_type'] == 'T' && $mod_record['comes_with'] == 'Y')
							{
								// set mod quantity = 1 since a comes with is just one thing ( mostly? )
								//$mod_record['mod_quantity'] = 1;
								$order_detail_comeswith_modifiers[] = $mod_record;
							}
						$order_detail['order_detail_comeswith_modifiers'] = $order_detail_comeswith_modifiers;

						// now get the mods that were added to this item
						$order_detail_added_modifiers = array();
						$mod_record = null;
						foreach ($all_order_detail_modifiers[$order_detail['order_detail_id']] as $mod_record)
							if ($mod_record['hold_it'] == 'N' && $mod_record['modifier_type'] == 'T' && ($mod_record['comes_with'] == 'N' || ($mod_record['comes_with'] == 'Y' && $mod_record['mod_quantity'] > 1)))
							{
								if ($mod_record['comes_with'] == 'Y')
									$mod_record['mod_quantity'] = $mod_record['mod_quantity'] - 1;

								// now we need to check if there is a price override for this group
								$order_detail_added_modifiers[] = $mod_record;
							}
						$order_detail['order_detail_added_modifiers'] = $order_detail_added_modifiers;

						// get the mods that were held
						$order_detail_hold_it_modifiers = array();
						$mod_record = null;
						foreach ($all_order_detail_modifiers[$order_detail['order_detail_id']] as $mod_record)
						{
							if ($mod_record['hold_it'] == 'Y' && $mod_record['comes_with'] == 'H')
							{
								$order_detail_hold_it_modifiers[] = $mod_record;
							}
						}
						$order_detail['order_detail_hold_it_modifiers'] = $order_detail_hold_it_modifiers;

						// now see if this is a pay with points item
						foreach ($all_order_detail_modifiers[$order_detail['order_detail_id']] as $mod_record)
						{
							if ($mod_record['modifier_type'] == 'P' && $mod_record['mod_quantity'] > 0 && $mod_record['mod_price'] < 0.00)
							{
								//we have a pay with points item!
								$current_points_used = (isset($order_detail['points_used'])) ? $order_detail['points_used'] : 0 ;
								$current_amount_off = (isset($order_detail['amount_off_from_points'])) ? $order_detail['amount_off_from_points'] : 0;
								$order_detail['points_used'] = $mod_record['mod_quantity'] + $current_points_used;
								$order_detail['amount_off_from_points'] = -$mod_record['mod_price'] + $current_amount_off;
							}
						}

                        // now see if this there are any price adjustments
                        $order_detail_price_adjustments = array();
                        $mod_record = null;
                        foreach ($all_order_detail_modifiers[$order_detail['order_detail_id']] as $mod_record)
                        {
                            if ($mod_record['modifier_type'] == 'A' && $mod_record['mod_quantity'] == 1 && $mod_record['mod_price'] < 0.00)
                            {
                                //we have a price adjust record
                                $order_detail_price_adjustments[] = $mod_record;
                            }
                        }
                        $order_detail['order_detail_price_adjustments'] = $order_detail_price_adjustments;
					} // end if else rewarder
					$total_points_used = $total_points_used + $order_detail['points_used'];
					$total_amount_off_from_points = $total_amount_off_from_points + $order_detail['amount_off_from_points'];
				}	// end order details loop
				$_SERVER['log_level'] = $server_log_level;
				if ($total_amount_off_from_points > 0)
				{
					$the_order['points_used'] = $total_points_used;
					$the_order['amount_off_from_points'] = $total_amount_off_from_points;
				}

			}

			$the_order['order_details'] = $order_details;
		} else {
			date_default_timezone_set($tzone_string);
			throw new Exception("ERROR! Could not build order in complete order, no matching order_id: ".$order_id);
		}
		if ($group_order_info) {
			$the_order['show_group_order_info'] = true;
			$the_order['group_order_info'] = $group_order_info;
		} else {
			$the_order['show_group_order_info'] = false;
		}

		$bc_options[TONIC_FIND_BY_METADATA]['order_id'] = $order_id;
		$amt_billed_CC = 0.00;
		$payments = [];
		if ($balance_change = $balance_change_adapter->select('',$bc_options)) {
			foreach ($balance_change as $balance_change_record) {
				//$balance_change = array_pop($balance_change);
				if ($balance_change_record['process'] == 'CCpayment' || $balance_change_record['process'] == 'Authorize') {
					if (substr($balance_change_record['notes'],0,4) == 'GIFT')
						$gift_used = 'true';
					else if (substr($balance_change_record['notes'],0,16) == 'AdditionalCharge')
						$amt_billed_CC = $balance_change_record['charge_amt'];
					else
						$amt_billed_CC = $balance_change_record['charge_amt'];
				} else if ($balance_change_record['process'] == 'Order') {
					$balance_before = $balance_change_record['balance_before'];
					$balance_after = $balance_change_record['balance_after'];
				}
				if ($balance_change_record['charge_amt'] > -0.01) {
					$payments[] = ["process"=>$balance_change_record['process'],"charge_amt"=>$balance_change_record['charge_amt']];
				}
			}
		}
		$the_order['payments'] = $payments;
		$the_order['amt_billed_to_cc'] = $amt_billed_CC;
		$the_order['balance_before'] = $balance_before;
		$the_order['balance_after'] = $balance_after;
		$the_order['gift_used'] = $gift_used;
				
		// set the time zone back to whatever it was
		date_default_timezone_set($tzone_string);
		
		myerror_logging(3,"finishing the order build code");
			
		$the_order['order_summary'] = $this->createOrderSummary($the_order);

        //for backward compatibility
        $the_order['receipt_items'] = $the_order['order_summary']['receipt_items'];

        $the_order['receipt_items_for_merchant_printout'] = $this->createReceiptItemsForPrintoutAtMerchant($the_order);

		return $the_order;
	}	
	
	function getModifierStringFromOrderDetail($order_detail)
	{
		$modifier_string = $this->getModifierString($order_detail['order_detail_complete_modifier_list_no_holds'], $modifier_string);
		if ($modifier_string) {
		  $modifier_string = preg_replace('/^\s*/', '', $modifier_string);
		  $modifier_string = preg_replace('/,*$/', '', $modifier_string);
		}
		return $modifier_string;
	}
	
	static function getModifierString($order_detail_mods,$modifier_string)
	{
		foreach ($order_detail_mods as $modifier) {		  
		  $modifier_string = $modifier_string.' ';
			if ($modifier['mod_quantity'] > 1) {
				$mq = $modifier['mod_quantity'];
				$modifier_string = $modifier_string.$modifier['mod_name']."(x$mq),";
			} else {
				$modifier_string = $modifier_string.$modifier['mod_name'].',';
			}
		}
		return $modifier_string;		
	}
	
	function getDisplayItemFromOrderDetail($order_detail)
	{
		if ($order_detail['item_name'] == LoyaltyBalancePaymentService::DISCOUNT_NAME) {
			$this->do_not_show_loyalty_in_payment_section = true;
		}
        $display_item['size_name'] = $order_detail['size_print_name'];

		$display_item['item_name'] = $order_detail['item_name'];
        $display_item['item_price'] = '$'.$order_detail['item_total_w_mods'];
        if ($order_detail['points_used'] >0) {
            $display_item['item_price'] = $order_detail['points_used'].' pts';
        }
		$display_item['item_quantity'] = $order_detail['quantity'];
		return $display_item;		
	}
	
	function splitOutFixedTaxesForDisplayPurposes(&$receipt_items,$complete_order)
	{
		if ($fixed_tax_list = $complete_order['fixed_tax_list']) {
			$fixed_tax_total = 0.00;
			foreach ($fixed_tax_list as $name=>$value) {
				$fixed_tax_total = $fixed_tax_total + $value;
				$receipt_items[] = array("title"=>$name,"amount"=>'$'.number_format($value,2));
			}
			$new_tax_total = $complete_order['total_tax_amt'] - $fixed_tax_total;
			$receipt_items[] = array("title"=>'Tax',"amount"=>'$'.$new_tax_total);
		} else {
			$receipt_items[] = array("title"=>'Tax',"amount"=>'$'.$complete_order['total_tax_amt']);
		}
		return $receipt_items;
	}

	function createPaymentItemsForOrderSummaryFromCompleteOrder($complete_order)
	{
		$payment_items = array();
		$status = $complete_order['status'];
		if ($status == 'O' || $status == 'E' || $status == 'T') {
			$payment_items = $this->createPaymentItemsForOrderSummaryFromOrderId($complete_order['order_id']);
		}
		return $payment_items;
	}
	
	function createPaymentItemsForOrderSummaryFromOrderId($order_id)
	{
		$payment_items = array();
		if ($records = BalanceChangeAdapter::staticGetRecords(array("order_id" => $order_id),'BalanceChangeAdapter')) {
			foreach ($records as $record) {
				if ($record['charge_amt'] < 0.00) {
					continue;
				} else {
					$name = $record['process'];
					if ($name == 'CCpayment') {
						$name = self::CC_CHARGED_LABEL;
					} else if ($name == 'LoyaltyBalancePayment') {
						if ($this->do_not_show_loyalty_in_payment_section) {
							// by pass becuase loyalty is pretax and is shown above the subtotal
							continue;
						}
						$name = $record['cc_processor']::getDiscountDisplay();
					}
					$payment_items[] = array("title"=>"$name","amount"=>'$'.number_format($record['charge_amt'],2));
				}
			}
		}
		return $payment_items;
	}
	
	function createReceiptItemsForOrderSummary($complete_order)
	{
		$receipt_items[] = array("title"=>'Subtotal',"amount"=>'$'.$complete_order['order_amt']);

		if ($complete_order['promo_amt'] < 0) {
			$receipt_items[] = array("title"=>'Promo Discount',"amount"=>'$'.$complete_order['promo_amt']);
		}
        if ($complete_order['points_used'] > 0) {
            $receipt_items[] = array("title"=>'Points Used',"amount"=>''.$complete_order['points_used']);
        }
        $this->splitOutFixedTaxesForDisplayPurposes($receipt_items, $complete_order);

		$brand = getBrandForCurrentProcess();
        if ($brand['allows_tipping'] == 'Y') {
			$receipt_items[] = array("title"=>'Tip',"amount"=>'$'.$complete_order['tip_amt']);
		}
		if ($complete_order['trans_fee_amt'] > 0.00) {
			$fee_label = self::FEE_LABEL;
			if (getBrandIdFromCurrentContext() == 395) {//395 Amicis brand_id
				$fee_label = self::AMICIS_FEE_LABEL;
			}
			$receipt_items[] = array("title" => $fee_label, "amount" => '$' . $complete_order['trans_fee_amt']);
		}
		if ($complete_order['delivery_amt'] > 0.00) {
			$receipt_items[] = array("title"=>'Delivery Fee',"amount"=>'$'.$complete_order['delivery_amt']);
		}
		if ($complete_order['customer_donation_amt'] > 0.00) {
			$receipt_items[] = array("title"=>'Donation',"amount"=>'$'.$complete_order['customer_donation_amt']);
		}
		$receipt_items[] = array("title"=>'Total',"amount"=>'$'.$complete_order['grand_total']);
		
		return $receipt_items;
	}
	
	function createReceiptItemsForPrintoutAtMerchant($complete_order)
	{
		if ($complete_order['promo_amt'] < 0) {
			$receipt_items[] = array("title"=>'Promo Discount',"amount"=>'$'.toCurrency($complete_order['promo_amt']));
		}
		$receipt_items[] = array("title"=>'Subtotal',"amount"=>'$'.toCurrency($complete_order['order_amt']));
		$this->splitOutFixedTaxesForDisplayPurposes($receipt_items, $complete_order);
		if ($complete_order['delivery_amt'] > 0.00) {
			$receipt_items[] = array("title"=>'Delivery Fee',"amount"=>'$'.toCurrency($complete_order['delivery_amt']));
		}
		$receipt_items[] = array("title"=>'Total',"amount"=>'$'.toCurrency($complete_order['grand_total_to_merchant']));
		$receipt_items[] = array("title"=>'Tip',"amount"=>'$'.toCurrency($complete_order['tip_amt']));
		$tip = toCurrency($complete_order['tip_amt']);
		$printer_grand_total = $complete_order['grand_total_to_merchant'] + $tip;
		$receipt_items[] = array("title"=>'Grand Total',"amount"=>'$'.toCurrency($printer_grand_total));
		
		return $receipt_items;
	}
	
	static function getItemsForGroupOrderFromCartId($ucid)
	{
		if ($cart_resource = SplickitController::getResourceFromId($ucid, 'Carts')) {
			$co = new CompleteOrder($mimetypes);
			$complete_order = $co->getCompleteOrder($cart_resource->order_id, $mimetypes);
			if ($cart_data = $co->createCartDataFromOrderDetails($complete_order['order_details'])) {
        foreach ($cart_data as &$item) {
          unset($item['order_detail_id']);
          $spacer = '';
          if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($complete_order, 'note') && validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($item, 'note')) {
            $spacer = ' - ';
          }
          $item['note'] = $item['note'] . "$spacer" . $complete_order['note'];
        }
        $group_order_data['items'] = $cart_data;
      }
			$group_order_data['merchant_id'] = $complete_order['merchant_id'];
			$group_order_data['cart_order_id'] = $complete_order['order_id'];
			return $group_order_data;
		}
		
	}
	
	static function getCartItemsFromOrderId($order_id)
	{
		$co = new CompleteOrder($mimetypes);
		$complete_order = $co->getCompleteOrder($order_id, $mimetypes);
		if ($complete_order['order_qty'] > 0) {
		  return $co->createCartDataFromOrderDetails($complete_order['order_details']);
		}
	}
	
	function createCartDataFromOrderDetails($order_details)
	{
		$cart_data = array();
		foreach ($order_details as $order_item)
		{
			$cart_item = array();
			$itemsize_record = ItemSizeAdapter::staticGetRecordByPrimaryKey($order_item['item_size_id'],'ItemSize');
			$cart_item['sizeprice_id'] = $order_item['item_size_id'];
			$cart_item['item_id'] = $itemsize_record['item_id'];
			$cart_item['size_id'] = $itemsize_record['size_id'];
			$cart_item['quantity'] = $order_item['quantity'];
			$cart_item['note'] = $order_item['note'];
			$cart_item['order_detail_id'] = $order_item['order_detail_id'];
			$cart_item['external_detail_id'] = $order_item['external_detail_id'];
			$cart_item_modifiers = array();

			foreach ($order_item['order_detail_complete_modifier_list_no_holds'] as $modifier) {
				if ($modifier['hold_it'] == 'N') {
					$cart_item_modifier['mod_quantity'] = $modifier['mod_quantity'];
					$cart_item_modifier['modifier_item_id'] = $modifier['modifier_item_id'];
					$cart_item_modifier['mod_sizeprice_id'] = $modifier['modifier_size_id'];
					$cart_item_modifiers[] = $cart_item_modifier;
				}
			}
			$cart_item['mods'] = $cart_item_modifiers;
			if ($order_item['points_used'] > 0) {
				$cart_item['points_used'] = $order_item['points_used'];
				$cart_item['amount_off_from_points'] = $order_item['amount_off_from_points'];
			}
			$cart_data[] = $cart_item;
		}
		return $cart_data;
		
	}
	
	function createCartItemsForOrderSummaryFromOrderDetails($order_details)
	{
		$order_items = array();
		foreach ($order_details as $order_detail)
		{
			$display_item = $this->getDisplayItemFromOrderDetail($order_detail);
			$display_item['item_description'] = $this->getModifierStringFromOrderDetail($order_detail);
			$display_item['order_detail_id'] = $order_detail['order_detail_id'];
			$display_item['item_note'] = $order_detail['note'];
			$order_items[] = $display_item;
		}
		return $order_items;
	}
	
	function createOrderDataAsIdsWithStatusField($complete_order)
	{
		$items = array();
		foreach ($complete_order['order_details'] as $order_detail) {
			$item = array();
			$item['item_id'] = $order_detail['item_id'];
			$item['size_id'] = $order_detail['size_id'];
			$item['quantity'] = $order_detail['quantity'];
			$item['order_detail_id'] = $order_detail['order_detail_id'];
			$item['note'] = $order_detail['note'];
			$item['status'] = 'saved';
			$item['external_detail_id'] = $order_detail['external_detail_id'];

			$mods = array();
			foreach ($order_detail['order_detail_modifiers'] as $modifier) {
				$mod = array();
				$mod['modifier_item_id'] = $modifier['modifier_item_id'];
				$mod['mod_quantity'] = $modifier['mod_quantity'];
				$mods[] = $mod;
			}
			$item['mods'] = $mods;
			$items[] = $item;
		}
		return array("merchant_id"=>$complete_order['merchant_id'],"user_id"=>$complete_order['user_id'],"items"=>$items);
	}
	
	
	function createOrderSummary($new_order) 
	{
	    if ($new_order['order_qty'] > 0) {
            $summary['cart_items'] = $this->createCartItemsForOrderSummaryFromOrderDetails($new_order['order_details']);
            $summary['receipt_items'] = $this->createReceiptItemsForOrderSummary($new_order);
			$summary['order_data_as_ids_for_cart_update'] = $this->createOrderDataAsIdsWithStatusField($new_order);
			$summary['payment_items'] = $this->createPaymentItemsForOrderSummaryFromCompleteOrder($new_order);
            return $summary;
        }
	}
	
	

	static function staticCreateOrderRecieptItemsFromOrderId($order_id)
	{
		$complete_order = new CompleteOrder($mimetypes);
		return $complete_order->createOrderReceiptItemsFromOrderId($order_id);
	}
	
	function createOrderReceiptItemsFromOrderId($order_id)
	{
		$order_adapter = new OrderAdapter($mimetypes);
		if ($order_record = $order_adapter->getRecord(array('order_id'=>$order_id))) {
			return $this->createOrderSumaryFromBaseOrderRecord($order_record);
		}
	}
}
class MissingOrderDetailsException extends Exception
{
    public function __construct($order_id)
    {
        parent::__construct("Unable to retrieve order details in complete order. order_id: $order_id", 999);
    }

}
?>
