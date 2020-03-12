<?php

Class LeadTime extends HourAdapter
{
	protected $number_of_elevel_items_in_order;
	var $high_volume = false;
	var $leadtime_error;
	var $user_message;

	var $pitapit_catering_lead = 60;
    var $toyo_joes_catering_lead = 120;

	// all defaults are in minutes
	
	//catering lead time 12 hours
	protected $catering_leadtime = 720;
	protected $catering_increment = 30;
	protected $catering_max_lead = 4320;  // 3 days in minutes



	//entree lead time
	protected $pickup_leadtime = 15; // get from merchant object
	protected $pickup_increment = 5;
	public $pickup_max_lead = 240; // 4 hours
	
	//delivery lead time
	protected $delivery_leadtime = 45;
	protected $delivery_increment = 5;
	protected $delivery_max_lead = 480; // 8 hours
	
	protected $min_lead_for_order;
	protected $increment_for_order;
	protected $max_lead_for_order;
	
	protected $max_days_out = 7;
    protected $pickup_max_days_out = 0; // will be overridden with value in Merchant_Advanced_Ordering_Info
    protected $advanced_ordering_increment = 15;
	
	protected $merchant_resource;
	protected $starting_tz;
	protected $elevel_time_for_merchant;
	protected $concurrently_able_to_make = 1;
    protected $delivery_order_buffer_in_minutes = 5;
    protected $concurrently_able_to_make_delivery = 1;
	protected $no_throttling_for_this_merchant;
	protected $delivery_throttling_on;
    protected $pickup_throttling_on;

	protected $lead_times_by_day_part;

    protected $open_close_ts = array();
    var $open_close_ts_array = array();

    protected $order_object;

	//hole hours
	protected $hole_hours = array();

	var $order_type;
	var $catering = false;

	protected  $allowASAPOnDelivery = 'N';

	protected $merchant_catering_info;

	function LeadTime($merchant_resource)
	{
		parent::HourAdapter(getM());
		if (is_numeric($merchant_resource)) {
			$m_resource = Resource::find(new MerchantAdapter(getM()),''.$merchant_resource);
			unset($merchant_resource);
			$merchant_resource = $m_resource;
		} else if (is_a($merchant_resource,'Resource')) {
			; // all good
		} else {
			throw new Exception("ERROR!!! no merchant submitted to Leadtime object");
		}
		$this->merchant_resource = $merchant_resource;
		$this->pickup_leadtime = $merchant_resource->lead_time;
		$time_zone = $merchant_resource->time_zone;
		$state = $merchant_resource->state;
		$this->starting_tz = date_default_timezone_get();
		$merchant_tz = getTheTimeZoneStringFromOffset($time_zone,$state);
		date_default_timezone_set($merchant_tz);
		
		$merchant_id = $this->merchant_resource->merchant_id;
		$merchant_preptime_info_data['merchant_id'] = $merchant_id;
		$mptid_options[TONIC_FIND_BY_METADATA] = $merchant_preptime_info_data;
		if ($mpti_resource = Resource::find(new MerchantPreptimeInfoAdapter(getM()),'',$mptid_options)) {
            $this->elevel_time_for_merchant = $mpti_resource->entree_preptime_seconds;
            $this->concurrently_able_to_make = $mpti_resource->concurrently_able_to_make;
            $this->pickup_throttling_on = $mpti_resource->pickup_throttling_on == 'Y' ? true : false;
            $this->delivery_order_buffer_in_minutes = $mpti_resource->delivery_order_buffer_in_minutes;
            $this->concurrently_able_to_make_delivery = $mpti_resource->concurrently_able_to_make_delivery;
            $this->delivery_throttling_on = $mpti_resource->delivery_throttling_on == 'Y' ? true : false;
            $this->delivery_increment = $this->delivery_order_buffer_in_minutes;
		} else {
			$this->no_throttling_for_this_merchant = true;
		}			
		
		$mdi_adapter = new MerchantDeliveryInfoAdapter(getM());
		if ($merchant_delivery_info_record = $mdi_adapter->getRecord(array("merchant_id"=>$merchant_id))) {
			$this->delivery_leadtime = $merchant_delivery_info_record['minimum_delivery_time'];	
			// commenting this out since we this is now set in prep time 
			//$this->delivery_increment = $merchant_delivery_info_record['delivery_increment'];
			$this->allowASAPOnDelivery = $merchant_delivery_info_record['allow_asap_on_delivery'];
			myerror_logging(3,"allow asap on delivery value on merchant $merchant_id: $this->allowASAPOnDelivery");
			if ($merchant_delivery_info_record['max_days_out'] > 1) {
				//$this->delivery_max_lead = $merchant_delivery_info_record['max_days_out'] * 24 * 60;
				// just set the max lead to something large and let the max days out limit the available times
				$this->delivery_max_lead = 100000000;
				// we'll limit it by the max days out so we get the full day of the last day
				$this->max_days_out = $merchant_delivery_info_record['max_days_out'];
                // currently there is no disctinction for delivery so we'll leave it as is
                $this->advanced_ordering_increment = $this->delivery_increment;
			}

		}
		if ($this->merchant_resource->advanced_ordering == 'Y') {
            $maoi_adapter = new MerchantAdvancedOrderingInfoAdapter(getM());
            if ($maoi_record = $maoi_adapter->getRecord(array("merchant_id"=>$merchant_id))) {
                $this->pickup_max_days_out = $maoi_record['max_days_out'];
                $this->advanced_ordering_increment = $maoi_record['increment'];
                $this->advanced_ordering_increment = 15;

                // if we have a record then set the max lead to the max days out (in minutes)
                $this->catering_max_lead = ($maoi_record['max_days_out']) * 24 * 60;

                // we need to add one because this->max days out uses current day as 1
                $this->max_days_out = $maoi_record['max_days_out']+1;

            }
        }

		if (isLaptop()) {
			$this->pickup_max_lead = isset($_SERVER['max_lead']) ? $_SERVER['max_lead'] : 90;
		}

		$this->setLeadTimesByDayPart($merchant_id);

		// pita pit exception for now
		if ($merchant_resource->brand_id == 282) {
			$this->catering_leadtime = $this->pitapit_catering_lead;
        } else if ($merchant_resource->brand_id == 200) {
            $this->catering_leadtime = $this->toyo_joes_catering_lead;
		}
	}

	function setLeadTimesByDayPart($merchant_id)
    {
        $ltbdpa = new LeadTimeByDayPartMapsAdapter();
        if ($lead_times_by_day_part_records = $ltbdpa->getLeadTimesByDayPartForMerchantId($merchant_id)) {
            $this->lead_times_by_day_part = $lead_times_by_day_part_records;
        }
    }

	function setCateringLeadTime($lead_time)
    {
        $this->catering_leadtime = $lead_time;
    }

	function setOrderObjectByOrderId($order_id)
    {
        $this->setOrderObject(new Order($order_id));
    }

    function setOrderObject($order_object)
    {
        $this->order_object = $order_object;
    }
	
	function getLeadTimesArrayFromOrderWithThrottling($request,$the_time = 0)
	{
		if ($the_time == 0)
			$the_time = time();

		$order_data = $request->data;
		return $this->getLeadTimesArrayFromOrderDataWithThrottling($order_data,$the_time);
	}

    /**
     * @param $order Order
     * @return bool
     */
	function testForSkipThrottling($order)
    {
        if (getProperty("global_throttling") == 'false') {
            myerror_log("Global throttling is set to false so skip");
            return true;
        } else if ($this->no_throttling_for_this_merchant) {
            myerror_log("there is no throttling for this merchant so return base array",3);
            return true;
        } else if ($order->isDeliveryOrder() && $this->delivery_throttling_on == false) {
            myerror_log("delivery throttling off for this merchant");
            return true;
        } else if ($order->isPickupOrder() && $this->pickup_throttling_on == false) {
            myerror_log("pickup throttling off for this merchant");
            return true;
        }
        return false;
    }

    /**
     * @param Order $order
     * @param int $the_time
     * @return array
     */
    function getLeadTimesArrayFromOrderObjectWithThrottling($order,$the_time = 0)
    {
        myerror_logging(3,"getLeadTimesArrayFromOrderObjectWithThrottling");
        if ($the_time == 0) {
            $the_time = time();
        }
        $lead_times_array = $this->getLeadTimesArrayFromOrder($order,$the_time);
        if ($this->leadtime_error) {
			return $lead_times_array;
		}
		if ($this->testForSkipThrottling($order)) {
            $this->includeAsapOption($lead_times_array, $the_time);
            return $lead_times_array;
        }

        $merchant_id = $this->merchant_resource->merchant_id;
        $now = date("Y-m-d H:i:s", $the_time);
        $order_adapter = new OrderAdapter(getM());
        if ($this->order_type == 'delivery') {
            $sql = "select *, 1 AS ecount from Orders WHERE merchant_id = $merchant_id AND status IN ('O','E') AND pickup_dt_tm > '$now' AND order_type = 'D'";
        } else {
            $sql = "SELECT e.*, count(a.order_detail_id) as ecount FROM Order_Detail a JOIN Item_Size_Map b ON a.item_size_id = b.item_size_id JOIN Item c ON b.item_id = c.item_id JOIN Menu_Type d ON c.menu_type_id = d.menu_type_id JOIN Orders e ON e.order_id = a.order_id WHERE d.cat_id = 'E' AND e.merchant_id = $merchant_id AND e.status IN ('O','E') AND e.pickup_dt_tm > '$now' AND e.order_type = 'R' group by order_id";
        }

        $orders_options[TONIC_FIND_BY_SQL] = $sql;
        $merchant_order_resources = Resource::findAll($order_adapter,null,$orders_options);
        $existing_order_data = array();
        foreach ($merchant_order_resources as $order_resource) {
            $order_id = $order_resource->order_id;
            $pickup_dt_tm = $order_resource->pickup_dt_tm;
            $pickup_ts = strtotime($pickup_dt_tm);
            $ecount = $order_resource->ecount;
            // so if i have an order with 5 items and $elevel_time_for_merchant = 120 (2 minutes), i want to loop through the five items and load up an item every 2 minutes for the 10 minutes leading up to the pickup time.
            // thats assuming a 2minute time for an item ($elevel_time_for_merchant).  this lets me know when the order is starting to be worked on

            for ($i = 0; $i < $ecount; $i++) {
				$temp_ts = $pickup_ts - ($i * $this->elevel_time_for_merchant);
				$existing_order_data[$temp_ts] = $existing_order_data[$temp_ts] + 1;
				$temp_ts_string = date("Y-m-d H:i:s",$temp_ts);
				myerror_logging(3,"we are loading an existing pickup at $temp_ts_string. so no order should be available for the ".$this->elevel_time_for_merchant." seconds before this time");	
			}
			
		}
		myerror_logging(1,"size of existing orders data is: ".sizeof($existing_order_data));
		$number_of_lead_times = sizeof($lead_times_array);
		$last_time = $lead_times_array[$number_of_lead_times-1];
		
        if (sizeof($existing_order_data) > 0) {
            if ($this->order_type == 'delivery') {
                $better_lead_times_array = $this->doLeadTimeThrottlingCalculationDelivery($lead_times_array, $existing_order_data, $the_time);
            } else {
                $better_lead_times_array = $this->doLeadTimeThrottlingCalculation($lead_times_array, $existing_order_data, $the_time);
            }

		} else {
			$better_lead_times_array = $lead_times_array;
		}

		$this->includeAsapOption($better_lead_times_array, $the_time);

		return $better_lead_times_array;
	}

    function includeAsapOption(&$lead_times, $the_time)
    {
		if($this->order_type == 'delivery' && $this->allowASAPOnDelivery == 'Y'){
			if ($the_time == 0)
				$the_time = time();
			$first_time = $lead_times[0];
			$minutes = abs($first_time - $the_time)/60;
			myerror_logging(3, "ASAP info -> time:".date("Y-m-d H:i:s", $the_time) . ",  first time:".date("Y-m-d H:i:s", $first_time). " diff: ".$minutes . ", mobile request: ".(!isMobileAppRequest()));

//			if($minutes < 60 && !isMobileAppRequest()) {
            if($minutes < 60) {
				array_unshift($lead_times, "As soon as possible");
				myerror_logging(3, "Added ASAP option on lead time array");
			}
		}else{
			myerror_logging(3,"No added ASAP option on lead time array");
		}

	}

	function doLeadTimeThrottlingCalculationDelivery($lead_times_array,$existing_order_data, $the_time)
    {
        $removed_times = 0;
        $need_first_time_added = true;
        $last_time = $lead_times_array[sizeof($lead_times_array)-1];
        $first_time = $lead_times_array[0];
        $total_minutes_in_range = ($last_time - $first_time)/60;
        $better_lead_times_array = [];
        $high_volume_message = '';
        foreach ($lead_times_array as $ts) {
            $ts_time_string = date('Y-m-d H:i:s',$ts);
            myerror_logging(3,"about to test ".$ts_time_string);
            if (isset($existing_order_data[$ts]) && $existing_order_data[$ts] >= $this->concurrently_able_to_make_delivery) {
                myerror_log("already delivery order for this timestamp. skip adding for available time: ".date('Y-m-d H:i',$ts),5);
                $removed_times++;
                continue;
            }
            $better_lead_times_array[] = $ts;
            $need_first_time_added = false;
        }
        if ($removed_times > 20) {
            $this->high_volume = true;
            $high_volume_message = ", due to high volume";
        }
        if ($need_first_time_added) {
            if ($total_minutes_in_range < 120) {
                //$better_lead_times_array[] = "So sorry$high_volume_message, there are no available pickup times in the next ".$total_minutes_in_range." minutes :(";
                $this->leadtime_error = "So sorry$high_volume_message, there are no available delivery times in the next ".$total_minutes_in_range." minutes :(";
            } else {
                $hours = floor($total_minutes_in_range/60);
                //$better_lead_times_array[] = "So sorry$high_volume_message, there are no available pickup times in the next $hours hours :( Please check back later.";
                $this->leadtime_error = "So sorry$high_volume_message, there are no available delivery times in the next $hours hours :( Please check back later.";
            }
        }

        return $better_lead_times_array;

    }

	function doLeadTimeThrottlingCalculation($lead_times_array,$existing_order_data, $the_time)
	{
		// this assumes elevel time for merchant is in minutes.
		$removed_times = 0;
		$need_first_time_added = true;
		$time_range_backwards = $this->elevel_time_for_merchant*$this->number_of_elevel_items_in_order; 
		$time_range_forwards = $this->elevel_time_for_merchant;
		// this is a bug since there could be orders after the last time that is shown to the consumer.
		// lets test against the last time plus 15 minutes or so.
		$last_time = $lead_times_array[sizeof($lead_times_array)-1];
		$last_time_plus_15 = $last_time + (15*60);
		$first_time = $lead_times_array[0];
		$total_minutes_in_range = ($last_time - $first_time)/60;
		// so now i have the elevel items per ts.  loop through leadtimes array and find out how many are within 2? minutes of that time.
		// if its more than X, remove that time from the lead times array.
        foreach ($lead_times_array as $ts) {
				$ts_time_string = date('Y-m-d H:i:s',$ts);
				myerror_logging(3,"about to test ".$ts_time_string);
				$total_existing_elevel_items_in_time_range = 0;
				
				$top_of_range = $ts+$time_range_forwards;
				$top_of_range_string = date('Y-m-d H:i:s',$top_of_range);
				$bottom_of_range = $ts-$time_range_backwards;
				$bottom_of_range_string = date('Y-m-d H:i:s',$bottom_of_range);

				myerror_logging(3,"now test to see if there are any existing pickup times between $bottom_of_range_string  and $top_of_range_string");
				// now check all existing orders and see if any conflict with the current array of available times based on teh time range
                foreach ($existing_order_data as $ots => $elevel_number) {
					$ots_string = date('Y-m-d H:i:s',$ots);
					myerror_logging(3,"is $ots_string in between the bad range?");
					
					// if we're past the last available time no need to do any caculations.
					$last_time_string = date('Y-m-d H:i:s',$last_time);
                    if ($ots > $last_time) {
						myerror_logging(5," $ots_string  is after the last time $last_time_string so we will skip the rest and go on to the next record in the lead times array");
						break;
					}
                    if ($ots < $top_of_range && $ots > $bottom_of_range) {
						// add in the nubmer of elevel items
						$total_existing_elevel_items_in_time_range = $total_existing_elevel_items_in_time_range + $elevel_number;	
						myerror_logging(3,"yes it is skip the rest and go onto the next pickup time");
						break;
					} else {
                        myerror_logging(5,"This order pickup is not in the range, so test the next order.");
                    }

				}
				
				$max_number_elevel_items_allowed_per_merchant_threshold_time = $this->concurrently_able_to_make;
                if ($total_existing_elevel_items_in_time_range < $max_number_elevel_items_allowed_per_merchant_threshold_time) {
					myerror_logging(3,"this time, $ts_time_string, is good as there are less then $max_number_elevel_items_allowed_per_merchant_threshold_time elevel items due in the past ");
					$better_lead_times_array[] = $ts;
					$need_first_time_added = false;
                } else {
					myerror_logging(3,"we have to remove a lead time option due to $total_existing_elevel_items_in_time_range elevel item(s) already due at that time.");
					$removed_times++;
				}
			}
            if ($removed_times > 20) {
				$this->high_volume = true;
				$high_volume_message = ", due to high volume";
			}
            if ($need_first_time_added) {
				if ($total_minutes_in_range < 120) {
					//$better_lead_times_array[] = "So sorry$high_volume_message, there are no available pickup times in the next ".$total_minutes_in_range." minutes :(";
					$this->leadtime_error = "So sorry$high_volume_message, there are no available pickup times in the next ".$total_minutes_in_range." minutes :(";
				} else {
					$hours = floor($total_minutes_in_range/60);
					//$better_lead_times_array[] = "So sorry$high_volume_message, there are no available pickup times in the next $hours hours :( Please check back later.";
					$this->leadtime_error = "So sorry$high_volume_message, there are no available pickup times in the next $hours hours :( Please check back later.";
				}
			}
			
			return $better_lead_times_array;
	}

	function getNumberOfEntreLevelItemsInOrderItemsData(&$items)
	{
		$catering = false;
		$elevel_num = 0;
        foreach ($items as &$item) {
			if ($this->catering) {
				continue;
			}

			// ok so we need the menu type of this item to determine its type
			$row = $this->getCatergoryIdOfOrderDataItem($item);
            if (strtoupper($row['cat_id']) == 'E') {
				myerror_logging(3,"we have an elevel item!");
				$elevel_num = $elevel_num + 1;
            } else if (strtoupper($row['cat_id']) == 'C') {
				myerror_logging(3,"we have a catering item!");
				$this->catering = true;
			} else {
				;
			}
			$item['start_time'] = $row['start_time'];
			$item['end_time'] = $row['end_time'];
			$item['name'] = $row['name'];
		}
		return $elevel_num;	
	}
	
	function getCatergoryIdOfOrderDataItem($item)
	{
		$merchant_id = $this->merchant_resource->merchant_id;
		if (isset($item['sizeprice_id'])) {
			$sql = "SELECT a.cat_id,b.item_name,a.menu_type_name,a.start_time,a.end_time,b.item_name AS name FROM Menu_Type a, Item b, Item_Size_Map c WHERE c.item_size_id = " . $item['sizeprice_id'] . " AND c.item_id = b.item_id AND b.menu_type_id = a.menu_type_id AND (c.merchant_id = $merchant_id  OR c.merchant_id = 0)";
		} else if (isset($item['item_id']) && isset($item['size_id'])) {
			$sql = "SELECT a.cat_id,b.item_name,a.menu_type_name,a.start_time,a.end_time,b.item_name AS name FROM Menu_Type a, Item b, Item_Size_Map c WHERE c.item_id = " . $item['item_id'] . " AND c.size_id = " . $item['size_id'] . " AND c.item_id = b.item_id AND b.menu_type_id = a.menu_type_id  AND (c.merchant_id = $merchant_id  OR c.merchant_id = 0)";
		} else {
			throw new Exception("ERROR! Bad data submitted merchant_id: $merchant_id", 999);
		}
		$options[TONIC_FIND_BY_SQL] = $sql;
		$result = $this->select(null,$options);
		$row = array_pop($result);
		myerror_logging(1,"we have found the category of this item (".$row['item_name'].") and its: ".$row['cat_id']);
		return $row;
	}

	function setCateringInfo($merchant_catering_info)
    {
	    $this->merchant_catering_info = $merchant_catering_info;
	    $this->catering = true;
	    $this->catering_max_lead = $merchant_catering_info->max_days_out * 24 * 3600;
        $this->setCateringLeadTime($merchant_catering_info->lead_time_in_hours);
        $this->setCateringOrderlimits();
    }

    function getMerchantCateringInfo($merchant_id)
    {
        if ($this->merchant_catering_info) {
            return $this->merchant_catering_info;
        } else if ($this->merchant_catering_info = MerchantCateringInfosAdapter::getInfoAsResourceByMerchantId($this->merchant_resource->merchant_id)) {
            return $this->merchant_catering_info;
        }
    }

	function getCateringMinLeadForMerchant($merchant_id)
	{
	    if ($merchant_catering_info = $this->getMerchantCateringInfo($merchant_id)) {
	        return $merchant_catering_info->lead_time_in_hours * 60;
        } else if ($advanced_ordering_record = MerchantAdvancedOrderingInfoAdapter::staticGetRecord(array("merchant_id"=>$merchant_id),'MerchantAdvancedOrderingInfoAdapter')) {
			if ($minutes = $advanced_ordering_record['catering_minimum_lead_time']) {
				return $minutes;
			}
		}
		return $this->catering_leadtime;
	}

	function setGroupOrderMaxAndMinLeads()
	{
		$this->pickup_max_lead = 48*60;
		$this->pickup_leadtime = 60;
		$this->delivery_max_lead = 48*60;
		$this->delivery_leadtime = 90;
	}

	static function getMinLeadTimeForLargePickupOrder($additional_item_count,$merchant_default_min_lead_time)
	{
		return $merchant_default_min_lead_time + 3 + ($additional_item_count*2);
	}

	function setCateringIncrementInMinutes($minutes)
    {
        $this->catering_increment = $minutes;
        $this->increment_for_order = $minutes;
    }

	function setCateringOrderlimits()
    {
        $this->min_lead_for_order = $this->getCateringMinLeadForMerchant($this->merchant_resource->merchant_id);
        $this->increment_for_order = $this->catering_increment;
        $this->max_lead_for_order = $this->catering_max_lead;
    }

	function setMinMaxAndIncrement($elevel_num,$hour_type,$the_time)
	{
		if ($this->catering) {
			$this->setCateringOrderlimits();
		} else if ($hour_type == 'D') {
		    $this->delivery_leadtime = $this->getDeliveryLeadtime($the_time);
			if ($elevel_num > 10) {
				$this->min_lead_for_order = $this->delivery_leadtime + ($elevel_num * 2);
			} else {
				$this->min_lead_for_order = $this->delivery_leadtime;
			}
			$this->increment_for_order = $this->delivery_increment;
			$this->max_lead_for_order = $this->delivery_max_lead;
		} else if ($elevel_num > 4) {
			$merchant_min_lead_time = $this->getPickupLeadtime($the_time);
			$additional_item_count = $elevel_num-4;
			$this->min_lead_for_order = $this->getMinLeadTimeForLargePickupOrder($additional_item_count,$merchant_min_lead_time);
			$this->increment_for_order = $this->pickup_increment;
			$this->max_lead_for_order = $this->pickup_max_lead;
            if ($this->pickup_max_days_out > 0) {
                $this->max_lead_for_order = ($this->pickup_max_days_out) * 24 * 60;
            }
		} else if ($hour_type == 'R') {
			$this->min_lead_for_order = $this->getPickupLeadtime($the_time);
			$this->increment_for_order = $this->pickup_increment;
			$this->max_lead_for_order = $this->pickup_max_lead;
            if ($this->pickup_max_days_out > 0) {
                $this->max_lead_for_order = ($this->pickup_max_days_out) * 24 * 60;
            }
		}	
	}

	function getPickupLeadtime($the_time)
    {
        $day = date('w',$the_time) + 1;
        if ($lead_time_changes_for_day = $this->lead_times_by_day_part['R'][$day][0]) {
            return $this->getCurrentLeadTimeForOrderType($the_time,$lead_time_changes_for_day,'R');
        }
        return $this->pickup_leadtime;
    }

    function getDeliveryLeadtime($the_time)
    {
        $day = date('w',$the_time) + 1;
        if ($lead_time_changes_for_day = $this->lead_times_by_day_part['D'][$day][0]) {
            return $this->getCurrentLeadTimeForOrderType($the_time,$lead_time_changes_for_day,'D');
        }
        return $this->delivery_leadtime;

    }

    function getCurrentLeadTimeForOrderType($the_time,$lead_time_changes_for_day,$hour_type)
    {
        myerror_log("we have a custom lead time for today: ".json_encode($lead_time_changes_for_day));
        $day_part_lead_time = $lead_time_changes_for_day['lead_time'];
        if ($hour_type == 'R') {
            $default_lead_time = $this->pickup_leadtime;
        } else if ($hour_type == 'D') {
            $default_lead_time = $this->delivery_leadtime;
        }


        $start_time = $lead_time_changes_for_day['start_time'];
        $sdate = new DateTime($start_time);
        $ts = $sdate->getTimestamp();
        $new_start_time = date('H:i:s',$ts /*- ($day_part_lead_time*60)*/);

        $end_time = $lead_time_changes_for_day['end_time'];
        $edate = new DateTime($end_time);
        $ets = $edate->getTimestamp();
        $new_end_time = date('H:i:s',$ets /*- ($default_lead_time*60)*/);

        $time = date('H:i:s',$the_time);
        if ($new_start_time < $time && $time < $new_end_time) {
            myerror_log("we have a custom lead time by day part of: $day_part_lead_time   which will replace the default lead time of $default_lead_time");
            return $day_part_lead_time;
        } else {
            myerror_log("current time is not within the custom range so keeping default lead time of $default_lead_time");
        }
        return $default_lead_time;
    }


	/**
     * @desc Gets the availavle times for the given order
     * @param $order Order
	 * @param $the_time int
     * @return array
	 */
	
	function getLeadTimesArrayFromOrder($order,$the_time = 0)
	{
		if ($the_time == 0) {
			$the_time = time();
		}

		$hour_type = 'R';
		$this->order_type = 'pickup';
		if ($order->isDeliveryOrder()) {
			$hour_type = "D";
			$this->order_type = 'delivery';
		}
		$elevel_num = $order->getNumberOfEntreLevelItems();
		$this->number_of_elevel_items_in_order = $elevel_num;
        $this->catering = $order->isCateringOrder();
		if ($order->skipHours()) {
            return $this->getNext90();
        }


		//set the min the max and the increments
		$this->setMinMaxAndIncrement($elevel_num, $hour_type,$the_time);

		// get the open and close for the days
		$open_close_ts_array = $this->getNextOpenAndCloseTimeStamps($this->merchant_resource->merchant_id, $hour_type, $this->max_days_out, $the_time);
        $this->open_close_ts_array = $open_close_ts_array;
		// now construct lead times array
		$first_time = $the_time + ($this->min_lead_for_order * 60);
		$first_time_string = date("Y-m-d H:i:s",$first_time);
		myerror_log("first time available is: $first_time_string",5);

		if (!$this->catering) {
			//check for time limited menu item
			$start_time_limit = 0;
			$end_time_limit = time() + (30 * 24 * 60 * 60);
			myerror_logging(5, "check for time limited menu item");
			myerror_log("start time limit: " . $start_time_limit);
			myerror_log("end time limit: " . $end_time_limit);
			foreach ($order->getOrderItemInfo() as $item) {
				logData($item, "cart item", 5);

                $t = explode(":", $item['start_time']);
                $start_time_item = mktime($t[0], $t[1], 0, date('m', $the_time), date('d', $the_time), date('Y', $the_time));

                $e = explode(":", $item['end_time']);
                $end_time_item = mktime($e[0], $e[1], 0, date('m', $the_time), date('d', $the_time), date('Y', $the_time));

                if ($item['end_time'] < $item['start_time'] ) {
                    // we have an ater close midnigth
                    if ($the_time < $end_time_item) {
                        // its after midnight AND before the end time so set start time to 24 hours earlier
                        myerror_log('start time for item was in previous day so subtract 24 hours from start time',5);
                        $start_time_item = $start_time_item - (24*3600);
                    } else if ($the_time >= $end_time_item) {
                        myerror_log("end time for item is in the next day so add 24 hours to the value",5);
                        $end_time_item = $end_time_item + (24*3600);
                    }
                }
                $start_time_item_string = date('Y-m-d H:i:s',$start_time_item);
                $end_time_item_string = date('Y-m-d H:i:s',$end_time_item);

                myerror_log("the start time and end time for this item are $start_time_item_string  - $end_time_item_string",5);

                if ($item['start_time'] != '00:00:00') {


					if ($start_time_item > $start_time_limit) {
						myerror_logging(5, "setting new start limit as: " . $start_time_item);
						$start_time_limit = $start_time_item;
						$start_time_limit_item = validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($item,'item_print_name') ? $item['item_print_name'] : $item['item_name'];
						$start_time_limit_string = date('g:i a', $start_time_item);
					}
				}

				if ($item['end_time'] != '23:59:59') {
					if ($end_time_item < $end_time_limit ) {
						myerror_logging(5, "setting new end limit as: " . $end_time_item);
						$end_time_limit = $end_time_item;
						$end_time_limit_item = validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($item,'item_print_name') ? $item['item_print_name'] : $item['item_name'];
						$end_time_limit_string = date('g:i a', $end_time_item);
					}
				}
			}
			myerror_log("end time limit is: $end_time_limit_string",5);
			myerror_log("first time is: $start_time_limit_string",5);
			// only check for today, that way we dont impact catering for the following day.
			if (date('Y-m-d', $first_time) == date('Y-m-d', $the_time) && $end_time_limit <= $first_time) {
				$this->leadtime_error = "Sorry the $end_time_limit_item is not available after $end_time_limit_string. Please remove it from your cart before placing your order.";
				return false;
			} else if ($start_time_item > $first_time) {
				$first_time = $start_time_item;
				$this->user_message = "Please Note: Your available times are limited by the $start_time_limit_item in your cart. Its not available until $start_time_limit_string.";
			}
		}
		// Jersery Mikes Exception
//        if ($this->catering && $this->merchant_resource->brand_id == 326) {
//			//need to determin current local time
//			$local_hour = date("G",$the_time);
//
//			//if its before 21:00 then set first time to 10:30 tomorrow, if its after 21:00 then set first time to 14:30 tomorrow
//            if ($local_hour < 21) {
//				$first_time = mktime(10, 30, '0', date("m",$the_time)  , date("d",$the_time)+1, date("Y",$the_time));
//			} else {
//				$first_time = mktime(14, 30, '0', date("m",$the_time)  , date("d",$the_time)+1, date("Y",$the_time));
//			}
//			$first_time_fixed = true;
//		}

		myerror_logging(1,"first time for this order will try to be: ".date("l F j, Y, g:i a",$first_time));
		
		$available_times = $this->getAvailableTimesArray($open_close_ts_array, $first_time, $the_time,$hour_type);
		
        if (sizeof($available_times) == 0) {
			myerror_log("THERE ARE NO AVAILABLE TIMES RIGHT NOW.  MERCHANT IS PROBABLY CLOSED");
			$this->leadtime_error = "We're sorry, this merchant is closed for mobile ordering right now.";
			//$available_times[] = "We're sorry, this merchant is closed for mobile ordering right now.";
		} else {
            if ($_SERVER['log_level'] > 4) {
				myerror_log("base list of lead times before throttling");
                foreach ($available_times as $ts) {
					myerror_log("$ts  -  ".date("l F j, Y, g:i a",$ts));
				}
			}
		}
		// now check for limit
		if (!$this->catering && $end_time_limit < $open_close_ts_array[0]['close']) {
			$limited_array_of_times = array();
			foreach ($available_times as $ts) {
				if ($ts <= $end_time_limit) {
					$limited_array_of_times[] = $ts;
				} else {
					$this->user_message = "Please Note: Your available times are limited by the $end_time_limit_item in your cart. Its not available after $end_time_limit_string.";
					break;
				}
			}
			$available_times = $limited_array_of_times;
		}

		return $available_times;
		
	}
	
    function merchantDoesNotHaveCateringInfoRecord()
    {
        return isset($this->merchant_catering_info->id) ? false : true;
    }

    function merchantDOESHaveCateringInfoRecord()
    {
        return !$this->merchantDoesNotHaveCateringInfoRecord();
    }

	function getAvailableTimesArray($open_close_ts_array,$first_time,$the_time,$hour_type)
	{
	    // FYI  for CATERING  opening time INCLUDES the minimum time from open for catering order.
		myerror_logging(3, "current time is: ".date("l F j, Y, g:i a",$the_time));
		myerror_logging(3, "closing time today is: ".date("l F j, Y, g:i a",$open_close_ts_array[0]['close']));
		myerror_logging(3, "opening time today is: ".date("l F j, Y, g:i a",$open_close_ts_array[0]['open']));
		myerror_logging(3, "first available time is: ".date("l F j, Y, g:i a",$first_time));
		$order_day = date('w', $the_time)+1;
		$hole_hour_for_order_day = isset($this->hole_hours[$order_day])? $this->hole_hours[$order_day] : array();
		if(!empty($hole_hour_for_order_day)){
		    myerror_log("we have hole hours for order day",3);
			if($first_time > $hole_hour_for_order_day['start'] && $first_time < $hole_hour_for_order_day['end']){
				myerror_logging(3,"first time into hole hour");
				$diference = $hole_hour_for_order_day['end'] - $first_time;
				$first_time = $first_time + $diference;
				myerror_logging(3, "recalculate first available time is: ".date("l F j, Y, g:i a",$first_time));
			}
		}

		$initial_first_time = $first_time;
		$increment_in_seconds = $this->increment_for_order * 60;
		$over_max = false;
		// does need to be max + 60? i think it just needs to be max + 1 second so the last value doesn't get dropped.
		$max_ts = $the_time + ($this->min_lead_for_order * 60) + ($this->max_lead_for_order * 60) + 60; // so the last value doesn't get dropped
		if ($max_ts < $first_time + 60) {
			$max_ts = $first_time + ($this->min_lead_for_order * 60) + ($this->max_lead_for_order * 60) + 60;
		}

		myerror_logging(3,"the max time for this order is: ".date("l F j, Y, g:i a",$max_ts));
		$available_times = array();
		$index_of_first_day = 0;
        $advanced_increment = $this->advanced_ordering_increment*60;
        $test_min_lead_value = $this->min_lead_for_order;
        if ($this->catering) {
            // need to determine which type of catering it is.
            if ($this->merchantDoesNotHaveCateringInfoRecord()) {
                //set test value to 2 hours
                $test_min_lead_value = 120;
            }
        }
        foreach ($open_close_ts_array as $day_num => $day_record) {
			myerror_log("Starting day number $day_num",3);
			if ($over_max) {
			    myerror_log("over max",5);
				continue;
			}
			if (sizeof($day_record) == 0) {
				continue;
			}
			$open_ts = $day_record['open'];
			$close_ts = $day_record['close'];
            myerror_logging(3, "closing time today is: ".date("l F j, Y, g:i a",$close_ts));
            myerror_logging(3, "opening time today is: ".date("l F j, Y, g:i a",$open_ts));

            if ($initial_first_time > $close_ts-(1.5*$increment_in_seconds)) {
                $index_of_first_day++;
                continue;
            } else if ($day_num == $index_of_first_day && $this->catering && $this->merchantDOESHaveCateringInfoRecord()) {
                if( $first_time <= $open_ts) {
                    // the bump should have already been taken care of so just set first time to open time
                    $first_time = $open_ts;
                    myerror_log("first time is now set to: ".date("l F j, Y, g:i a", $first_time),5);
                }
            } else if ($this->catering && $day_num == $index_of_first_day && $first_time <= $open_ts + ($test_min_lead_value*60)) {
                $first_time = $open_ts + ($test_min_lead_value*60);
                myerror_log("We have catering so first time is now set to: ".date("l F j, Y, g:i a", $first_time),5);
                $initial_first_time = $first_time;
                // now set last time to be first time + max_lead + 60 seconds
                if ($max_ts > $open_ts) {
                    $max_ts = $open_ts + ($this->max_lead_for_order * 60) + 60;
                }
            } else if ($day_num == $index_of_first_day && $first_time <= $open_ts) {
                // set first time to be opening time plus 1 minute
                $first_time = $open_ts + 60;
                myerror_log("first time is now set to: ".date("l F j, Y, g:i a", $first_time),5);
                $initial_first_time = $first_time;
                // now set last time to be first time + max_lead + 60 seconds
				if ($max_ts > $open_ts) {
					$max_ts = $open_ts + ($this->max_lead_for_order * 60) + 60;
				}
			} else if ($day_num != $index_of_first_day) {
                /* if ($this->min_lead_for_order < 16) {
                    $first_time = $open_ts + 900;
                } else if (15 < $this->min_lead_for_order && $this->min_lead_for_order < 31) {
                    $first_time = $open_ts + 1800;
                } else */ if ($this->catering && $this->merchantDOESHaveCateringInfoRecord()) {
                    // for full catering the bump is already built into the open_ts at this point
                    $first_time = $open_ts;
                } else {
                    // no longer buffering
                    //$first_time = $open_ts + 3600;
                    $first_time = $open_ts+1;
			    }
            }

            myerror_logging(3, "opening time today is: " . date("l F j, Y, g:i a", $open_ts));
			myerror_logging(3, "closing time today is: ".date("l F j, Y, g:i a",$close_ts));
			myerror_logging(3, "max lead for order is: ".date("l F j, Y, g:i a",$max_ts));
			if (date("Y-m-d",$max_ts) == date("Y-m-d",$close_ts) && $max_ts > $close_ts) {
				$max_ts = $close_ts;
			}


			$current_day_for_hole_hour = date("w",$first_time)+1;
			if ($day_num > 6) {
                $current_day_for_hole_hour = $current_day_for_hole_hour + $day_num;
            }
			$hole_hour = isset($this->hole_hours[$current_day_for_hole_hour])? $this->hole_hours[$current_day_for_hole_hour] : array();

			if(!empty($hole_hour)){
				myerror_logging(3, "hole hour day  $current_day_for_hole_hour " . date("l F j, Y, g:i a",$hole_hour['start']));
				myerror_logging(3, "Start hole hours for day $current_day_for_hole_hour " . date("l F j, Y, g:i a",$hole_hour['start']));
				myerror_logging(3, "End hole hours for day $current_day_for_hole_hour " . date("l F j, Y, g:i a",$hole_hour['end']));
			}else{
				myerror_logging(3, "no set hole hour for day $current_day_for_hole_hour ");
			}

            //push delivery first time to 5 minute increments
            if ($hour_type == 'D') {
			    $buffer_seconds = $this->delivery_order_buffer_in_minutes * 60;
			    myerror_log("we have the buffer in seconds: ".$buffer_seconds,3);
			    if ($buffer_seconds < 300) {
			        myerror_log("resetting buffer to 300 seconds");
			        $buffer_seconds = 300;
                }
                $first_time = ceil($first_time/$buffer_seconds)*$buffer_seconds;
            }

			if ($day_num == $index_of_first_day) {
				$starting_time = $first_time;
			} else {
			    // maybe set increment here
                //$increment_in_seconds = $this->advanced_increment_for_order * 60;
            }

            myerror_log("first_time: $first_time,  close_ts: $close_ts,   over_max: $over_max",5);
            while ($first_time < $close_ts && !$over_max) {
                myerror_log("looping time: ".date("Y-m-d H:i",$first_time),5);
                if ($first_time > $max_ts) {
					// exceeded max lead time
					$over_max = true;
					continue;
				}
                if (!$this->catering && $hour_type == 'R') {
					// do 1 minute intervals for first 15 minutes (NOT EVEN NUBMERS)
                    if ($first_time < $starting_time + 900) {
						$increment_in_seconds = 60;
                    } else {
						$increment_in_seconds = $this->increment_for_order * 60;
				    }
                }

                if (!$this->catering) {
                    myerror_log("first time: $first_time, hole hour start: ".$hole_hour['start'].",  hole hour end: ".$hole_hour['end'],3);
					if((!empty($hole_hour)) && ($first_time > $hole_hour['start'] && $first_time < $hole_hour['end'])){
						myerror_logging(3,"exclude: ".date("l F j, Y, g:i a",$first_time));
					}else{
						myerror_logging(3,"adding: ".date("l F j, Y, g:i a",$first_time));
						$available_times[] = $first_time;
					}
				}else{
					myerror_logging(3,"skipping hole logic because catering and adding: ".date("l F j, Y, g:i a",$first_time));
					$available_times[] = $first_time;
				}

				if (!$this->catering && $hour_type != 'D') {
                    if ($first_time > $initial_first_time + 900 && $first_time < $initial_first_time + 5400) {
                        $increment_in_seconds = 300;
                    } else if ($first_time > $initial_first_time + 5400 && $day_num < 2) {
                        $increment_in_seconds = 900;
                    }
                }
                myerror_log("the increment in seconds is: $increment_in_seconds",5);
                $first_time = $first_time + $increment_in_seconds;
				
				// ok we want to include the closing time in the list
				if ($first_time >= $close_ts) {
                    myerror_logging(3,"adding: ".date("l F j, Y, g:i a",$first_time));
					$available_times[] = $close_ts;
				}				
			}
			if (!$this->catering) {
                if ($day_num == 0) {
                    // just finished today so set increment to 15 minutes to start tomorrow
                    $increment_in_seconds = 900;
                } else if ($day_num == 1) {
                    // just finished first day in the future so set increment to 30 to start the next day
                    $this->increment_for_order = 30;
                    $increment_in_seconds = 1800;
                }
            } else {
			    if ($day_num > 6) {
                    $increment_in_seconds = 7200;
                }
            }
		}
		return $available_times;
		
	}

	function getStartingTz()
    {
        return $this->starting_tz;
    }
	
	function setElevelTimeForMerchant($elevel_time)
	{
		$this->elevel_time_for_merchant = $elevel_time;
	}
	
	function setNoThrottlingForThisMerchant($boolean)
	{
		$this->no_throttling_for_this_merchant = $boolean;
	}
	
	function setConcurrentlyAbleToMake($number)
	{
		$this->concurrently_able_to_make = $number;
	}
	
	function setPickupMaxLead($max_lead)
	{
		$this->pickup_max_lead = $max_lead;		
	}
	
	function setCateringMaxLead($max_lead)
	{
		$this->catering_max_lead = $max_lead;		
	}
	
	function setMaxDaysOut($max_days_out)
	{
		$this->max_days_out = $max_days_out;
	}

	function setHoleHours($hole_hours, $time = 0)
	{
		if ($time == 0) {
			$time = time();
		}


		$values = !isset($hole_hours->error_code)?  createHashmapFromArrayOfArraysByFieldName($hole_hours,'day_of_week') : array();


		if (sizeof($values) > 0 ) {
			for ($i=0;$i<$this->max_days_out;$i++) {
				$day_time = strtotime(' + '.$i.'day', $time);
				$day = date("w",$day_time)+1; // add one to match up with MySQL day index
				if (isset($values[$day])) {
					// there are hole hours on this day
					$ts = explode(":", $values[$day]['start_time']);
					$start_time = mktime($ts[0], $ts[1], 0, date('m', $day_time), date('d', $day_time), date('Y', $day_time));
					$te = explode(":", $values[$day]['end_time']);
					$end_time = mktime($te[0], $te[1], 0, date('m', $day_time), date('d', $day_time), date('Y', $day_time));
                    $this->setHoleHoursForDay($day,$start_time,$end_time,$day_time);
				}
			}
		}
		myerror_log("finished setting hole hours",3);
	}

	public function setHoleHoursForDay($day,$start_time,$end_time,$day_time)
    {
        if (isset($this->hole_hours[$day])) {
            $this->setHoleHoursForDay($day+7,$start_time,$end_time,$day_time);
        } else {
            $this->hole_hours[$day] = array(
                'start' => $start_time,
                'end' => $end_time
            );
            myerror_logging(3,"hole hour  for ".$day." : " .date("l",$day_time)." => " .date("l F j, Y, g:i a",$start_time)." -- ".date("l F j, Y, g:i a",$end_time));
        }
    }


    public function getCurrentHoleHours()
    {
		return $this->hole_hours;
	}

	public function getMinimumLeadTimeForThisOrder()
    {
        return $this->min_lead_for_order;
    }

    function getCurrentDeliveryLeadTime()
    {
        return $this->delivery_leadtime;
    }
}

?>