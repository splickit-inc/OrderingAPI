<?php

class PlaceOrderAdapter extends MySQLAdapter
{
	public $log_level = 1;
	
	var $the_time;
	var $order_lead_differential_between_submitted_and_minimum;
	
	function PlaceOrderAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'`Orders`',
			'%([0-9]{1,15})%',
			'%d',
			array('order_id')
		);
		$this->log_level = $_SERVER['log_level'];
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
    
	/**
	 * @desc will insert the order record based on the order data passed in as a resource
	 * @param Resource $resource
	 */
    function insert(&$resource)
	{
		myerror_log("starting place order adapter");

		// set the current time if its submitted in (testing) or just current for normal operation
		$this->the_time = isset($resource->current_time) ? $resource->current_time : time();
		
		$user_id = $resource->user_id;
		
		$bypass_time_test = false;
		if ($user_id < 100 || substr_count(strtolower($resource->note),'skip hours') > 0 ) {
			myerror_log("******** By-pass merchant hours restriction *********");
			$bypass_time_test = true;
            str_replace('skip hours','',$resource->note);
		}

		$merchant = $resource->merchant_record;
		unset($resource->merchant_record);
		
		if ($resource->tip == 'select_tip') {
			$resource->tip = 0.00;
		}

        // strip out notes field if skin doesn't support it
        $skin = getSkinForContext();
        if(!$skin['show_notes_fields']) {
            unset($resource->note);
            foreach($resource->items as &$no_note_item) {
                unset($no_note_item['note']);
            }
        }


        myerror_log("brand_id = ".$merchant['brand_id']);
		myerror_log("we starting the combo check",3);
		if ($items=$resource->items) 
		{
			foreach ($items as $item)
			{
				myerror_log("testing combo item",3);
				$combo_items = 0;
				$upsize = 0;
				$mods = $item['mods'];
				foreach ($mods as $mod)
				{
					$mod['mod_sizeprice_id'];
					$sql_combo = "SELECT c.modifier_type FROM Modifier_Size_Map a, Modifier_Item b, Modifier_Group c WHERE a.modifier_size_id = ".$mod['mod_sizeprice_id']." AND a.modifier_item_id = b.modifier_item_id AND b.modifier_group_id = c.modifier_group_id";
					$combo_options[TONIC_FIND_BY_SQL] = $sql_combo;
					$rs = $this->select('',$combo_options);
					$rs = array_pop($rs);
					
					$type = $rs['modifier_type'];
					myerror_logging(3,"type is: $type");
					if ($type == 'I2')
						$combo_items++;
				}
				myerror_log("number of combo items is: ".$combo_items,3);
				if ($combo_items == 1)
				{
					$item['sizeprice_id'];
					$sql_item = "SELECT b.item_name FROM Item_Size_Map a, Item b WHERE a.item_size_id = ".$item['sizeprice_id']." AND a.item_id = b.item_id";
					$item_options[TONIC_FIND_BY_SQL] = $sql_item;
					$rs2 = $this->select('',$item_options);
					$rs2 = array_pop($rs2);
					$item_name = $rs2['item_name'];
					
					$resource->set("error_code","790");
					if ($merchant['brand_id'] == 326)
						return $this->setErrorOnResourceAndReturnFalse($resource,"Sorry, you must choose both a combo drink size and combo chip type to get the combo price on your ".$item_name.".  A-la-carte items are available from the main menu. Thanks!");
					else if ($merchant['brand_id'] == 292)
						return $this->setErrorOnResourceAndReturnFalse($resource,"Sorry, you must choose both combo size and combo drink to get the combo price on your ".$item_name.".  A-la-carte items are available from the main menu. Thanks!");
					else {
						return $this->setErrorOnResourceAndReturnFalse($resource,"Sorry, you must choose both combo items to get the combo price on your ".$item_name.".  A-la-carte items are available from the main menu. Thanks!");
					}
				}
			}
		}

		$hour_adapter = new HourAdapter($this->mimetypes);	
		$hour_adapter->setCurrentTime($this->the_time);
		// do check on order pickup time if in hours
		// first get time stamp of pickup time
		if (isOrderDataForDelivery($resource->getDataFieldsReally())) {
			$hour_type = "D";
		} else {
			$hour_type = "R";
		}	

		$pickup_ts = $this->getPickupTimeStampFromSubmittedData($resource);
		$local_pickup_time = getMySqlFormattedDateTimeFromTimeStampAndTimeZone($pickup_ts, getTheTimeZoneStringFromOffset($merchant['time_zone'],$merchant['state']));
		$local_current_time = getMySqlFormattedDateTimeFromTimeStampAndTimeZone($this->the_time, getTheTimeZoneStringFromOffset($merchant['time_zone'],$merchant['state']));

		myerror_logging(3,'local_pickup_time: '.$local_pickup_time);
		myerror_logging(3,'local_current_time: '.$local_current_time);

		// calculate lead time and check its relavancy.  have to do this since SP takes a lead time in minutes.
		$lead_time = round(($pickup_ts - $this->the_time)/60);
		myerror_log("the submitted lead time is $lead_time minutes based on the submitted pickup timestamp: $ts",3);
		$two_months_in_minutes = 60*24*60;
		if ($lead_time > $two_months_in_minutes)
		{
				recordError("LEAD TIME ERROR", "a lead time of $lead_time minutes was submitted");
				$resource->set('error',"Sorry, there was an error with your submitted data, please try again");
				$resource->set('error_code',999);
				return false;
		}

		// check for too many elevel items
if ($resource->get_checkout_data == false)
{
		$local_order_time = getMySqlFormattedDateTimeFromTimeStampAndTimeZone($this->the_time, getTheTimeZoneStringFromOffset($merchant['time_zone'],$merchant['state']));
		if ($hour_type == 'R' && ($resource->items || $resource->base_order_data['order_qty'] > 0) )
		{
		    if ($resource->items) {
                $order_items = $resource->items;
                $number_of_items = sizeof($order_items);
            } else {
                $number_of_items = $resource->base_order_data['order_qty'];
                if ($number_of_items > 4) {
                    // set the $order_items array to loop
                    $order_items = OrderDetailAdapter::staticGetRecords(array("order_id"=>$resource->base_order_data['order_id']),'OrderDetailAdapter');
                }
            }

			// base min is min
			$min_lead_time_for_this_order = $resource->base_minimum_lead_time_without_order_data;
			myerror_log("minimum lead time for this order without order data is: $min_lead_time_for_this_order",3);
			//skip all checks if we have less than 5 items
			if ($number_of_items > 4)
			{
				myerror_logging(3, "we have an order with more than 4 items.  Check the number of E level items");
				$elevel_num = $this->getNumberOfELevelItemsInItemArray($order_items);
				if ($elevel_num > 4)
				{
					myerror_logging(3, "ok.  we have more than 4 E level items.");
					$additional_item_count = $elevel_num-4;
					$min_lead_time_for_this_order = LeadTime::getMinLeadTimeForLargePickupOrder($additional_item_count,$resource->base_minimum_lead_time_without_order_data);
					myerror_logging(3,"min lead time for this order: ".$min_lead_time_for_this_order);
					myerror_logging(3, "submitted lead time with order: ".$lead_time);
				}
			}
		}
		
		if (($lead_time < $min_lead_time_for_this_order) && !$bypass_time_test)
		{
			$diff = $min_lead_time_for_this_order - $lead_time;
			$this->order_lead_differential_between_submitted_and_minimum = $diff;
			// first check to see if they sat on checkout screen too long. if so, just pass the order in to the next available time if its not too far in the future.
			if ($min_lead_time_for_this_order == $resource->base_minimum_lead_time_without_order_data && $diff <= 4) {
				//now determin if its less than 5 minutes, if so change the $lead_time, $pickup_ts, and $local_pickup_time
				$lead_time = $min_lead_time_for_this_order;
				$pickup_ts = $this->the_time + ($lead_time*60);
				$local_pickup_time = getMySqlFormattedDateTimeFromTimeStampAndTimeZone($pickup_ts, getTheTimeZoneStringFromOffset($merchant['time_zone'],$merchant['state']));
			} else {
				myerror_log("ERROR! we have a short lead time! reject the order");
				$this->setShortLeadTimeErrorMessageOnResource($resource, $merchant, $min_lead_time_for_this_order, $elevel_num);
				return false;
			}
		}
}		
		$cash_bool = false;
		if (isset($resource->cash) && $resource->cash == 'Y' )
			$cash_bool = true;
		else
			$resource->cash = 'N';

		// create time stamp from leadtime and current time
		//$pickup_ts = mktime(date("H"), date("i")+$lead_time,'0', date("m")  , date("d"), date("Y"));

if ($resource->get_checkout_data == false ) {
		if ($hour_adapter->isMerchantOpenAtThisTime($merchant['merchant_id'], $merchant['time_zone'], $hour_type, $pickup_ts)) {
			myerror_log("MERCHANT IS OPEN!");
		} else if (!$bypass_time_test) {
			myerror_log("MERCHANT IS CLOSED!");
			$resource->set('error',$hour_adapter->getMerchantStatusMessage());
			if ($resource->delivery == 'yes') {
				$resource->set('error',"We're sorry, this merchant is closed at your requested delivery time.");
				if ( substr_count( strtolower(trim($resource->delivery_time)),'as soon as possible' ) > 0) {
					$resource->set('error',"We're sorry, this merchant is currently out of delivery hours, and cannot deliver 'As soon as possible'. Please choose a delivery time from the drop down list.");
				}
			}
			
			$resource->set('error_code',540);
			$resource->set('created_id',540);
            $resource->set('http_code',422);
			return $resource;
		}
}

$items = $resource->items;

    // NOW DO PAY WITH POINTS VERIFICATION AND ORDER MODIFICATIONS
	if (isset($resource->total_points_used) && $resource->total_points_used > 0)
	{
		// we have a points order
		myerror_log("WE HAVE A POINTS ORDER! for: ".getIdentifierNameFromContext());
		// 1. verify the brand_points_id combinations are possible.
		// 2. verify the user has enough points in their account
		// 3. add fields to temp tables and let the SP do its work.
		
		//check against rules
		$brand_id = getBrandIdFromCurrentContext();
		$brand_loyalty_rules_adapter = new BrandLoyaltyRulesAdapter($mimetypes);
		$brand_loyalty_rules_record = $brand_loyalty_rules_adapter->getRecord(array("brand_id"=>$brand_id));
		if ($resource->total_points_used > $brand_loyalty_rules_record['max_points_per_order']) {
			myerror_log("ERROR! too many points used. max per order for this brand is: ".$brand_loyalty_rules_record['max_points_per_order']);
			return $this->setErrorOnResourceAndReturnFalse($resource, "We're sorry, but there is max points per order of ".$brand_loyalty_rules_record['max_points_per_order'].", please remove something from your cart.  If you feel you have received this message in error, please contact customer support");
		}
		
		$brand_points_adapter = new BrandPointsAdapter($mimetypes);
		$brand_points_list = $brand_points_adapter->getBrandPointsList($brand_id);
		
		$total_validated_points = 0;
		foreach ($items as &$test_item)
		{
				if ($test_item['points_used'] < 1)
				{
					myerror_log("ok item does not have points so skip");
					// hack alert
					unset($test_item['points_used']);
					unset($test_item['amount_off_from_points']);
					continue;
				}
				if ($brand_points_item_data = $brand_points_adapter->validateCartItem($test_item))
				{
					$test_item['points_used'] = $brand_points_item_data['points'];
					$test_item['amount_off_from_points'] = $brand_points_item_data['amount_off_from_points'];
					myerror_logging(3,"we have a validated pay with points item in the cart");
					$total_validated_points = $total_validated_points+$brand_points_item_data['points'];
				}
				else 
				{
					myerror_log("ERROR!  something is wrong with this item!  item_size_id: ".$test_item['item_size_id']);
					return $this->setErrorOnResourceAndReturnFalse($resource, "We're sorry, but there was a problem with your pay with points request. Please re-select your option and try again");
				}
			
		}
		//$user_resource = Resource::find(new UserAdapter($mimetypes),''.$user_id);
		
		$ubp_data['user_id'] = $user_id;
		$ubp_data['brand_id'] = $brand_id;
		$ubp_options[TONIC_FIND_BY_METADATA] = $ubp_data;
		$user_brand_points_resource = Resource::find(new UserBrandPointsMapAdapter($mimetypes),'',$ubp_options);
		if ($total_validated_points != $resource->total_points_used)
		{
			myerror_log("ERROR!  Validated Points ($total_validated_points) to not match up with submitted total_points_used: ".$resource->total_points_used);
			return $this->setErrorOnResourceAndReturnFalse($resource, "We're sorry, but there was a problem with your pay with points request. Please re-select your option and try again");
		} else if ($resource->total_points_used > $user_brand_points_resource->points) {	
			myerror_log("ERROR! User does NOT have enough points (".$user_brand_points_resource->points.") to place this order: ".$user_brand_points_resource->points);
			return $this->setErrorOnResourceAndReturnFalse($resource, "We're sorry, but it appears you do not have enough points in your account to place this order.  If you feel you have received this message in error, please contact customer support");
		} else {
			myerror_logging(3,"The pay with points order has been validated. Place the order");
		}
	}
		
		$time1 = microtime(true);	
		if ($items || $resource->existing_cart_order_id) {
			// 1.  get all order data (items, and modifiers) into the db in temp tables
			// 2.  then call a SP that looks at those tobles  to create the order.
			// create temp table for other order data
			$sql = "DROP TEMPORARY TABLE IF EXISTS `TempOrders`";
			$this->_query($sql);
			
			$sql = "CREATE TEMPORARY TABLE IF NOT EXISTS `TempOrders` ( `temp_order_id` int(11) NOT NULL AUTO_INCREMENT,
															`merchant_id` int(11) NOT NULL DEFAULT '0',
															`user_id` int(11) NOT NULL DEFAULT '0',
  															`promo_code` varchar(50) DEFAULT NULL,
															`promo_id` int(11) DEFAULT NULL,
															`promo_amt` decimal(10,2) NOT NULL DEFAULT '0.000',
															`promo_tax_amt` decimal(10,3) NOT NULL DEFAULT '0.000',
															`trans_fee_amt` decimal(10,2) NOT NULL DEFAULT '0.00',
															`delivery_amt` decimal(10,2) NOT NULL DEFAULT '0.00',
															`delivery_tax_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
															`tip_amt` decimal(10,2) NOT NULL DEFAULT '0.00',
															`customer_donation_amt` decimal(10,2) NOT NULL DEFAULT '0.00',
															`cash` char(1) DEFAULT NULL,
															`ucid` char(255),
															`user_delivery_location_id` int(11) DEFAULT NULL,
															`requested_delivery_time` varchar(50) DEFAULT NULL,
															`stamp` varchar(255) DEFAULT NULL,
															`local_order_time` DATETIME DEFAULT NULL,
															`local_pickup_time` DATETIME DEFAULT NULL,
															`brand_id` int(11) NOT NULL,
															`existing_cart_order_id` INT(11),
															PRIMARY KEY (`temp_order_id`)
															)";
			
			myerror_logging(2,$sql);
			if ($this->_query($sql))
				; // all is good
			else
				myerror_log("ERROR! serious error creating TempOrders table: ".$this->getLastErrorText());

			//create temp table and load with order items
			$sql = "DROP TEMPORARY TABLE IF EXISTS `TempOrderItems`";
			$this->_query($sql);
			$sql = "CREATE TEMPORARY TABLE IF NOT EXISTS TempOrderItems (`temp_order_detail_id` INT NOT NULL AUTO_INCREMENT, `sizeprice_id` INT,`quantity` INT, `name` VARCHAR(50) NULL, `note` VARCHAR (255) NULL, `points_used` INT, `amount_off_from_points` decimal(10,2) NOT NULL DEFAULT '0.000',`external_detail_id` VARCHAR (255) NULL,PRIMARY KEY (`temp_order_detail_id`)) AUTO_INCREMENT=1";
			myerror_logging(2,$sql);
			if ($this->_query($sql))
				; // all is good
			else
				myerror_log("ERROR! serious error creating TempOrderItems table: ".$this->getLastErrorText());
						
			// really not sure why i needed to do this with the not exists.  always threw an error without it though. tried DROPPING first too. no help?
			$sql = "DROP TEMPORARY TABLE IF EXISTS `TempOrderItemMods`";
			$this->_query($sql);
			$sql = "CREATE TEMPORARY TABLE IF NOT EXISTS TempOrderItemMods (`temp_order_detail_mod_id` INT NOT NULL AUTO_INCREMENT, `temp_order_detail_id` INT, `mod_sizeprice_id` INT,`mod_quantity` INT,PRIMARY KEY (`temp_order_detail_mod_id`)) AUTO_INCREMENT=50";
			myerror_logging(2,$sql);
			$this->_query($sql);
			if ($this->_query($sql))
				; // all is good
			else
				myerror_log("ERROR!  serious error creating TempOrderItemMods table: ".$this->getLastErrorText());
			$process = true;
			$number_of_items = sizeof($items);
			foreach ($items as $item)
			{
				// hack to remove the � in cafe or saute (remove it, doesn't work)
				$item['note'] = str_replace("\xc3\xa9","e",$item['note']);
				$item['name'] = str_replace("\xc3\xa9","e",$item['name']);
				$points_used = 0;
				$amount_off_from_points = 0.00;
				if ($item['points_used']) {
					$points_used = $item['points_used'];
				}
				if ($item['amount_off_from_points']) {
					$amount_off_from_points = $item['amount_off_from_points'];
				}

				$sql = "INSERT INTO TempOrderItems (sizeprice_id,quantity,name,note,points_used,amount_off_from_points,external_detail_id) Values (".$item['sizeprice_id'].",".$item['quantity'].",'".mysqli_real_escape_string($this->_handle,$item['name'])."','".mysqli_real_escape_string($this->_handle,$item['note'])."',".$points_used.",".$amount_off_from_points.",'".$item['external_detail_id']."')";
				myerror_logging(2,$sql);
				if ($this->_query($sql))
				{
					$temp_order_item_id = mysqli_insert_id($this->_handle);
					$mods = $item['mods'];
					if (sizeof($mods) > 0)
					{
						$sql2 = "INSERT INTO TempOrderItemMods (temp_order_detail_id,mod_sizeprice_id,mod_quantity) Values ";
						foreach ($mods as $mod)
							$sql2 .= "(".$temp_order_item_id.",".$mod['mod_sizeprice_id'].",".$mod['mod_quantity']."),";
						$sql2 = substr($sql2, 0, -1);
						myerror_logging(2,$sql2);
						if ($this->_query($sql2))
							; // do nothing
						else
						{
							// error creating order item modifier temp table
							myerror_log("*********  very serious DB error in placeorderadapter saving order item modifiers into temp table: ".$this->getLastErrorText());
							$process = false;
						}
					}		
				} else {
					// error creating order item temp table
					$process = false;
					myerror_log("*********  very serious DB error in orderadapter saving order items into temp table: ".$this->getLastErrorText());
				}
			}
			if ($process)
			{
				myerror_logging(2,"Successful creation of temp tables for Order in placeorderadapter.php");
				$merchant_id = $resource->merchant_id;
				$note = $resource->note;
				// lead time set above now so dont need to do it here.
				//$lead_time = $resource->lead_time;
				$sub_total = $resource->sub_total;
				if ($promo_id = $resource->promo_id)
				{
					$promo_amt = $resource->promo_amt;
					if (isset($resource->promo_tax_amt))
						$promo_tax_amt = $resource->promo_tax_amt;
					else
						$promo_tax_amt = '0.00';
					$promo_code = $resource->promo_code;
				} else {
					$promo_amt = '0.00';
					$promo_id = '0';
					$promo_tax_amt = '0.00';
				}
				myerror_log("the hour type is $hour_type");
				if ($hour_type == 'D')
				{
					$delivery_amt = $resource->delivery_amt;
					$delivery_tax_amount = $resource->delivery_tax_amount;
					$user_delivery_location_id = $resource->user_addr_id;
				} else {
					$delivery_amt = '0.00';
					$delivery_tax_amount = '0.00';
					$user_delivery_location_id = '0';
				}

				//if ($promo_amt == null)
				//	$promo_amt = 0.00;
				$tip = $resource->tip;
				if ($tip == null)
					$tip = 0.00;
				
				//hack to fix the saute thing
				$note = str_replace("\xc3\xa9","e",$note);	
								
				//adding skin_id to the stored procedure call.  need it for donation stuff and its probably usefull
				$skin_id = $_SERVER['SKIN_ID'];
				
				$promo_code_raw = $resource->promo_code;
				$promo_code_raw = strtolower($promo_code_raw);
				$promo_code_clean = str_replace("'", "", $promo_code_raw);
				$promo_code_clean = str_replace("delete","",$promo_code_clean);
				$promo_code_clean = str_replace("update","",$promo_code_clean);
				$promo_code_clean = str_replace("drop","",$promo_code_clean);
				$promo_code_clean = str_replace("insert","",$promo_code_clean);
				$promo_code_clean = str_replace("select","",$promo_code_clean);

				$new_ucid = generateUUID();

				$sql =  "INSERT INTO TempOrders (ucid,promo_code,promo_id,promo_amt,promo_tax_amt,delivery_amt,delivery_tax_amount,cash,user_delivery_location_id,stamp,local_order_time,local_pickup_time,trans_fee_amt,brand_id,existing_cart_order_id) ".
						"VALUES ('".$new_ucid."','".$promo_code_clean."',".$promo_id.",".$promo_amt.",".$promo_tax_amt.",".$delivery_amt.",".$delivery_tax_amount.",'".$resource->cash."',".$user_delivery_location_id.",'".$_SERVER['RAW_STAMP']."','".$local_order_time."','".$local_pickup_time."',".$resource->convenience_fee.",".getBrandIdFromCurrentContext().",".$this->getExistingCartOrderIdFromResource($resource).")";
				
				myerror_logging(2,$sql);

				if (! $this->_query($sql))
				{
					myerror_log("dberror in order creation: ".$this->_error());
					recordError('Error thrown in PlaceOrderAdapter loading up TempOrders '.$_SERVER['SERVER_NAME'],'dberror in order creation: '.$this->_error());
					//MailIt::sendErrorEmail('Error thrown in PlaceOrderAdapter loading up TempOrders '.$_SERVER['SERVER_NAME'],'dberror in order creation: '.$this->_error());
					//$resource->set('error','ORDER ADAPTER SERVER ERROR 1');
					$resource->set("error","Sorry, we are experiencing some technical difficulties.  Try us again soon.");
					$resource->set('error_code','902');
					$resource->set('http_code',500);
					return false;
					
				}		


				if ($sub_total < .01) {
					$sub_total = 0.00;
				} if ($skin_id < 1) {
					myerror_log("we have a null skin id so substituting splickit skin id of 1");
					$skin_id = 1;
				}

				$sp_log_level = (isset($_SERVER['GLOBAL_PROPERTIES']["sp_log_level"])) ? getProperty("sp_log_level") : getBaseLogLevel();
				$sql = "call SMAWSP_CREATE_ORDER('".$hour_type."','".$user_id."',".$lead_time.",'".mysqli_escape_string($this->_handle,$note)."','".$merchant_id."',".$sub_total.",".$tip.",'".$promo_amt."','".$promo_tax_amt."',".$skin_id.",".$sp_log_level.",@createID,@merchantName)";
								
				myerror_logging(1,$sql);
				if ($result = $this->_query($sql))
				{
					$time2 = microtime(true);
					$time_of_query = $time2 - $time1;
					myerror_log("Time for stored procedures execution is: ".$time_of_query);
					
					$options[TONIC_FIND_BY_SQL]="SELECT @createID AS created_id,@merchantName AS info";
					if ($order_result = $this->select('orderinfo',$options))
					{
						$order = array_pop($order_result);
						$resource->set('neworder',$order);
						foreach ($order as $name=>$value)
						{
							myerror_logging(2,"$name = $value");
							$resource->set($name,$value);
						}
						if ($order['created_id'] < 1000)
						{
							if ($order['info'] == 'NO_CREDIT_CARD_ON_FILE')
							{
								$order['info'] = 'Please enter your credit card info';
							}
							else if ($order['info'] == 'DUPLICATE_ORDER_ERROR') 
							{
								//$order['info'] = 'This appears to be an accidental duplicate order and it has been rejected by our server.  Please try again in 5 min or change the order slightly.';
                                $order['info'] = 'It appears an order exactly matching this one has already been submitted, please check for a confirmation email. If you are trying to submit a second order, either wait 5 minutes or change your order slightly and resubmit.';
							}
							else if ($order['created_id'] == 520) //merchant is not active.
							{
								$resource->set('text_title','Sorry');
								$order['info'] = 'Sorry :( but this merchant is currently not accepting mobile orders.';
							}
							else if ($order['created_id'] == 700 || $order['created_id'] == 705 || $order['created_id'] == 710) //menu is out of date.
							{
								$resource->set('text_title','Sorry');
								$order['info'] = 'Sorry :(  your menu is out of date, please reload it by selecting this store again from the store list.  Thanks!';
							}
							else if ($order['created_id'] == 715) //LTO not active
							{
								$resource->set('text_title','Item Not Active');
								$order['info'] = 'Sorry :(  it appears you are trying to use a time/day sensitive priced item on the wrong day.';
							}
							else if ($order['created_id'] == 780) //menu type out of hours.
								$resource->set('text_title','Item Out Of Hours Error');
							else
							{
/*								70 DUPLICATE_ORDER_ERROR
								100	SERIOUS_DATA_ERROR_USER_ID_DOES_NOT_EXIST
								150	NO_CREDIT_CARD_ON_FILE
								500	SERIOUS_DATA_ERROR_MERCHANT_ID_DOES_NOT_EXIST
								510	MERCHANT_DELETED
								520	MERCHANT_NOT_ACTIVE
								540	MERCHANT_IS_CLOSED (cant happen anymore from the stored procedure)
								700	DATA_INTEGRITY_ERROR_APP_ITEM_NOT_ACTIVE
								705	DATA_INTEGRITY_ERROR_APP_ITEM_NO_LONGER_EXISTS
								710	DATA_INTEGRITY_ERROR_ITEM_NOT_OWNED_BY_SUBMITTED_MERCHANT
								780 Menu type is not available at this time
								800	MERCHANT_DELIVERY_MESSAGE_NOT_SET_UP
*/								// we've have some kind of serious error on the order creation so create a presentable message to eh user and send error to me								
								
								$mail_body = 'ERROR in SMAWSP_CREATE_ORDER() '.$order['created_id'].': '.$order['info'].'   merchant_id: '.$merchant_id;
								$order['info'] = "Sorry, we are experiencing some technical difficulties.  Try us again soon.";								
								MailIt::sendErrorEmail('ERROR! in splickit stored procedure '.$_SERVER['SERVER_NAME'],$mail_body);
							}

							$resource->set('error',$order['info']);
							
							$resource->set('error_code',$order['created_id']);
							return false;
						} else {
							// get new order resource
							$order_resource = Resource::find(new OrderAdapter($this->mimetypes),$order['created_id']);
							$order_resource->phone_no = $resource->phone_no;
							if ($resource->delivery == 'yes' || isset($resource->user_addr_id)) {
								$order_resource->user_delivery_location_id = $resource->user_addr_id;
								$order_resource->save();
							}
//CHANGE THIS
							// BRINK SHIT!!!!!  need to start creating child object with custom code
							if ($merchant['brand_id'] == 282 && MerchantBrinkInfoMapsAdapter::isMechantBrinkMerchant($merchant_id)) {
								// get correct tax amount from brink
								$brink_controller = new BrinkController($m,$u,$r);
								$brink_controller->setBrinkCheckoutInfoOnOrderResource($order_resource);
							}


							if ($resource->is_place_order_request) {
								$order_resource->status = 'P';
							} else {
								$order_resource->status = 'Y';
								$order_resource->save();
								return true;
							}

                            // save new trans fee for discount in place order controller
							$resource->set("saved_trans_fee_from_place_order_adapter",$order_resource->trans_fee_amt);

							//now set correct order and pickup time

							$local_order_and_pickup_time_array = $hour_adapter->getLocalOrderAndPickupTimes();
							// this is set at the top of the function	
							$order_resource->order_dt_tm = $local_order_time;
							//$order_resource->order_dt_tm = $local_order_and_pickup_time_array['local_order_time'];
							$order_resource->pickup_dt_tm = $local_order_and_pickup_time_array['local_pickup_time'];	


							$pickup_time = date('g:ia',strtotime($order_resource->pickup_dt_tm));
							$pickup_date_time = date('l g:ia',strtotime($order_resource->pickup_dt_tm));
							myerror_log("pickup_date_time: ".$pickup_date_time);

							$submit_time_string = date('g:ia',strtotime($order_resource->pickup_dt_tm)-(45*60));

							$order_day = date('z',strtotime($order_resource->order_dt_tm));
							$pickup_day = date('z',strtotime($order_resource->pickup_dt_tm));
							$pickup_string = $order_day != $pickup_day ? $pickup_date_time : $pickup_time;
							if ($resource->delivery == 'yes' || isset($resource->user_addr_id))
							{
								$user_message = "Your order to ".$merchant['name']." has been scheduled for delivery at ";
								if (is_numeric($resource->requested_time)) {
									$order_resource->requested_delivery_time = getTimeStringForUnixTimeStampInMerchantLocal('D m/d g:i A',$resource->requested_time,$merchant);
								} else {
									$order_resource->requested_delivery_time = $resource->requested_time;
								}
								$resource->set('delivery_time_string',$pickup_string);
								if (substr_count(strtolower($order_resource->requested_delivery_time),'possible') > 0) {
									$user_message = str_replace("delivery at ","delivery ".strtolower($order_resource->requested_delivery_time),$user_message.".");
									$resource->set('user_message',$user_message);
								} else {
									$resource->set('user_message',"$user_message ".$order_resource->requested_delivery_time);
								}

							} else {	
								$user_message = "Your order to ".$merchant['name']." will be ready for pickup at ";
								$resource->set('pickup_time_string',$pickup_string);
								$resource->set('user_message',"$user_message $pickup_string");
							}
							$order_resource->save();
							$resource->set('order_ready_time_string',$pickup_string); // new single field value
							myerror_logging(2,"order[info]: ".$order['info']."     requested time: ".$pickup_string);
							
							$resource->set('requested_time_string', $submit_time_string);
							$resource->set('user_message_title','Order Result');

                            $resource->set('pickup_timestamp_used_to_create_order',$pickup_ts);
                            $resource->set('leadtime_used_to_create_order',$lead_time);
                            $message_leadtime_for_order = ($min_lead_time_for_this_order > $resource->base_minimum_lead_time_without_order_data) ? $min_lead_time_for_this_order : $resource->base_minimum_lead_time_without_order_data;
                            $resource->set('message_leadtime_for_order',$message_leadtime_for_order);
						}
					}			
				} else {
					// error stored procedure throwing an error
					myerror_log("dberror in order creation: ".$this->_error());
					recordError('Error thrown in PlaceOrderAdapter '.$_SERVER['SERVER_NAME'],''.$_SERVER['STAMP'].' dberror in order creation: '.$this->_error());
					//MailIt::sendErrorEmail('Error thrown in PlaceOrderAdapter '.$_SERVER['SERVER_NAME'],''.$_SERVER['STAMP'].' dberror in order creation: '.$this->_error());
					if ($user_id < 200) {
						$resource->set("error", 'dberror in order creation: ' . $this->_error());
					} else {
						$resource->set("error", "Sorry, we are experiencing some technical difficulties.  PLease try again.");
					}
					$resource->set('error_code','901');
					$resource->set('http_code',500);
					return false;
				}
			} else {
				// error creating temp tables from order data
				myerror_log('ERROR! error creating temp tables from order data');
				recordError('Error thrown in PlaceOrderAdapter'.$_SERVER['SERVER_NAME'],''.$_SERVER['STAMP'].' ERROR! error creating temp tables from order data');
				//MailIt::sendErrorEmail('Error thrown in PlaceOrderAdapter'.$_SERVER['SERVER_NAME'],''.$_SERVER['STAMP'].' ERROR! error creating temp tables from order data');
				if ($user_id < 200) {
					$resource->set("error",'dberror in order creation: '.$this->_error());
				} else {
					$resource->set("error","Sorry, we are experiencing some technical difficulties.  PLease try again.");
				}
				$resource->set('error_code','902');
				$resource->set('http_code',500);
				return false;
			}
		} else {
			myerror_log("ERROR! NO data passed for order");
			$resource->set('error','Sorry, there were no items passed for your request. Please try again :)');
			$resource->set('error_code','999');
			return false;
		}
		return true;
	}

	function getNumberOfELevelItemsInItemArray($items)
	{
		$elevel_num = 0;
		foreach ($items as $item) {
			// ok so we need the menu type of this item to determine if its an elevel item
			$item_size_id = isset($item['sizeprice_id']) ? $item['sizeprice_id'] : $item['item_size_id'];
			$sql = "SELECT a.cat_id,b.item_name,a.menu_type_name FROM Menu_Type a, Item b, Item_Size_Map c WHERE c.item_size_id = $item_size_id AND c.item_id = b.item_id AND b.menu_type_id = a.menu_type_id";
			$options[TONIC_FIND_BY_SQL] = $sql;
			$result = $this->select(null,$options);
			$row = array_pop($result);
			myerror_logging(3,"we have found the category of this item (".$row['item_name'].") and its: ".$row['cat_id']);
			if ($row['cat_id'] == 'E')
			{
				myerror_logging(3,"we have an elevel item!");
				$elevel_num++;
			} else {
				myerror_logging(3,"NOT an e level");
			}
		}
		return $elevel_num;
	}

	function getExistingCartOrderIdFromResource($resource)
	{
		if (isset($resource->existing_cart_order_id)) {
			return $resource->existing_cart_order_id;
		} else {
			return 0;
		}
	}

	function getPickupTimeStampFromSubmittedData($resource)
	{
		// check to see if there is a actual pickupt time submitted
		if ($pickup_time_stamp = isset($resource->actual_pickup_time) ? $resource->actual_pickup_time : $resource->requested_time) {
			if (is_numeric($pickup_time_stamp)) {
				return $pickup_time_stamp;
			} else {
				// let lead time logic work?  this is probably an as soon as possible submission
				myerror_log("Non numeric actual_pickup_time submitted: ".$pickup_time_stamp);
			}
		} 
		// for backwards compatability
		$lead_time = isset($resource->lead_time) ? $resource->lead_time : $resource->base_minimum_lead_time_without_order_data; //if there is no lead time, set to base minimum so stored procedure doesn't blow up. 
		$pickup_time_stamp = $this->the_time+($lead_time * 60);
		return $pickup_time_stamp;
	}
	
	function setShortLeadTimeErrorMessageOnResource(&$resource,$merchant,$min_lead_time_for_this_order,$elevel_num)
	{
			// create some buffer
			$min_lead_time_for_this_order = $min_lead_time_for_this_order + 2;
			//now get local minimum pickup time
			$tz = date_default_timezone_get();
			$time_zone = $merchant['time_zone'];
			$merchant_tz = getTheTimeZoneStringFromOffset($time_zone);
			date_default_timezone_set($merchant_tz);
			$min_pickup_time_stamp = $this->the_time + ($min_lead_time_for_this_order * 60);
			$local_pickup_time_string = date("g:i a",$min_pickup_time_stamp);
			date_default_timezone_set($tz);
			$resource->set("error_code","550");
			if ($elevel_num > 4) {
				$resource->set('error',"ORDER ERROR! We're sorry, but the size of your order requires a minimum preptime of $min_lead_time_for_this_order minutes. Please choose a pickup time of ".$local_pickup_time_string." or later.");
			} else {
				myerror_log("Expired minimum lead time by: ".$this->order_lead_differential_between_submitted_and_minimum." minutes");
				$resource->set('error',"ORDER ERROR! Your pickup time has expired. Please select a new pickup time and proceed to check out.");
			}
		
	}
	
	function setErrorOnResourceAndReturnFalse(&$resource,$error_message,$error_code = 999)
	{
		myerror_log("ERROR: $error_message");
		$resource->set("error_code",$error_code);
		$resource->set("error",$error_message);
		$resource->set("http_code",422);
		return false;
	}

}
?>
