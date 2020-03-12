<?php

class MerchantController extends SplickitController
{
    protected $menu_types;
    protected $modifier_group_names = array();
    protected $hours_today;
    protected $hours_all;
    protected $merchant_resource;
    protected $merchant_id;
    protected $the_time;
    protected $email_service;
    protected $user_id;
    protected $active_item_list;
    protected $active_modifiers_list;

    function MerchantController($mt, $u, $r, $l = 0)
    {
        parent::SplickitController($mt, $u, $r, $l);
        $this->adapter = new MerchantAdapter($this->mimetypes);
        $this->log_level = $l;
        myerror_logging(1, "MerchantController: starting merchant controller");
        $this->the_time = time();
        $this->user_id = $u['user_id'];
    }

    function processPosRequest()
    {
        if (preg_match('%/merchants/([0-9a-zA-Z\-]+)%', $this->request->url, $matches)) {
            $merchant_options[TONIC_FIND_BY_METADATA] = array("merchant_external_id" => $matches[1], "brand_id" => getBrandIdFromCurrentContext());
            if ($merchant_resource = Resource::find($this->adapter, '', $merchant_options)) {
                if (isset($this->data['GetLeadTimes'])) {
                    $mdi_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_resource->merchant_id;
                    if ($mdi_resource = Resource::find(new MerchantDeliveryInfoAdapter(getM()), '', $mdi_options)) {
                        $merchant_resource->minimum_delivery_time = $mdi_resource->minimum_delivery_time;
                    } else {
                        $merchant_resource->minimum_delivery_time = "Mechant has no delivery information";
                    }
                } else if (isset($this->data['lead_time'])) {
                    $merchant_resource->lead_time = $this->data['lead_time'];
                    $merchant_resource->save();
                    if (isset($this->data['minimum_delivery_time'])) {
                        $mdi_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_resource->merchant_id;
                        if ($mdi_resource = Resource::find(new MerchantDeliveryInfoAdapter(getM()), '', $mdi_options)) {
                            $mdi_resource->minimum_delivery_time = $this->data['minimum_delivery_time'];
                            $mdi_resource->save();
                            $merchant_resource->minimum_delivery_time = $this->data['minimum_delivery_time'];
                        } else {
                            return createErrorResourceWithHttpCode("Merchant does not appear to be set up for delivery", 422, 999);
                        }
                    }
                }
                return $merchant_resource;
            } else {
                return createErrorResourceWithHttpCode("No matching merchant found.", 422, 999);
            }
        }
    }


  function isSubmittedUserDataAGuest()
  {
    return doesFlagPositionNEqualX($this->user['flags'],9,'2');
  }
  
    function processV2Request()
    {
        if (strtoupper($this->request->method) != 'GET') {
            return returnErrorResource("Method Not Available");
        }

        if (preg_match('%/merchants/([0-9]{4,15})%', $this->request->url, $matches)) {
            if ($this->hasRequestForDestination('getstatus')) {
                $merchant_numeric_id = $matches[1];
                $getstatusactivity = new SendMerchantStatusActivity(NULL);
                $getstatusactivity->set("destination", "browser");
                $resource = $getstatusactivity->getTempMerchantStatus($merchant_numeric_id);
                $_REQUEST['show'] = 'true';
            } else if ($this->hasRequestForDestination('info')) {
                return returnErrorResource("ERROR! endpoint not allowed!");
            } else if ($this->hasRequestForDestination('getinfo')) {
                $resource = $this->getMerchantGetInfo($matches[1]);
            } else if (substr_count($this->request->url, '/grouporderavailabletimes') > 0) {
                $hour_type = 'R';
                if (substr_count($this->request->url, '/delivery') > 0) {
                    $hour_type = 'D';
                }
                $submit_times_array = $this->getMerchantAvailableGroupOrderTimes($matches[1], $hour_type);
                $resource = new Resource();
                $resource->set('submit_times_array', $submit_times_array);
            } else if ($this->hasRequestForDestination('cateringorderavailabletimes')) {
                $hour_type = $this->hasRequestForDestination('delivery') ? 'D' : 'R';
                $resource = $this->getMerchantAvailableCateringOrderTimes($matches[1],$hour_type);
            } else {
                if ($this->hasRequestForDestination('isindeliveryarea')) {
                    if (preg_match('%/isindeliveryarea/([0-9]{2,10})%', $this->request->url, $matches2)) {
                        $this->request->data['merchant_id'] = $matches[1];
                        $delivery_controller = new DeliveryController(getM(), $this->user, $this->request);
                        $delivery_info = $delivery_controller->getRelevantDeliveryInfoFromIds($matches2[1], $matches[1]);
                        if ($delivery_info['name'] == 'Doordash') {
                            // check to make sure merchant is open at the present moment
                            $hour_adapter = new HourAdapter(getM());
                            if ($hour_adapter->isMerchantOpenAtThisTime($matches[1],$this->merchant_resource->time_zone,'D',$this->the_time)) {
                                ;//merchant is open so allow the doordash logic to work
                            } else {
                                return createErrorResourceWithHttpCode(DeliveryController::DOORDASH_STORE_CLOSED_MESSAGE, 422, 422);
                            }
                        }
                        $resource = Resource::dummyfactory($delivery_info);
                    } else {
                        return createErrorResourceWithHttpCode("No user delivery location passed in", 422, 422);
                    }
                } else {
                    $merchant_id = $matches[1];
                    $this->setMerchantId($merchant_id);
                    $time1 = microtime(true);
                    $resource = $this->getMerchant();
                    $time2 = microtime(true);
                    if ($resource->hasError()) {
                        return $resource;
                    }
                    $elapsed = $time2 - $time1;
                    myerror_logging(5, "Total time for get Merchant and Menu " . $resource->merchant_id . ": " . $elapsed);

                    if (getSkinIdForContext() == 4 && getProperty('moes_ordering_on') == 'false' && shouldSystemProcessAsRegularUser($this->user['user_id'])) {
                        $resource->user_message = isset($resource->user_message) ? getProperty('moes_ordering_off_message') . "\n\n" . $resource->user_message : getProperty('moes_ordering_off_message');
                    } else if (isOrderingShutdown()) {
                        $resource->user_message = isset($resource->user_message) ? "PLEASE NOTE: the ordering system is currently offline!\n\n" . $resource->user_message : "PLEASE NOTE: the ordering system is currently offline!";
                    }

                    $this->addTaxInfoToResource($resource);
                    $this->addHoursInfoToResource($resource);

                    // get delivery info if it exists
                    if ($merchant_delivery_info = $this->getMerchantDeliveryInfoNew($merchant_id)) {
                        $resource->set("delivery_info", $merchant_delivery_info);
                    }

                    $resource->set("menus", MerchantMenuMapAdapter::staticGetRecords(array("merchant_id" => merchant_id), 'MerchantMenuMapAdapter'));
                    $resource->set("do_not_clean", true);

                }
            }
        } else {
            myerror_log("MerchantController : getMerchantList " . $this->request->url,3);
            $resource = $this->getMerchantList(getSkinIdForContext(), true);
        }
        unset($resource->_adapter);
        unset($resource->_exists);
        unset($resource->logical_delete);
        return $resource;
    }

    function getMerchantGetInfo($merchant_id)
    {
        $complete_merchant = new CompleteMerchant($merchant_id);
        $menu_type = $this->hasRequestForDestination('delivery') ? 'delivery' : 'pickup';
        $resource = $complete_merchant->getTheMerchantData($menu_type);
        $this->addHoursInfoToResource($resource);
        $this->addTaxInfoToResource($resource);
        $merchant_brand = BrandAdapter::staticGetRecordByPrimaryKey($resource->brand_id,'BrandAdapter');
        $resource->set('brand_name',$merchant_brand['brand_name']);
        return $resource;
    }

    function addHoursInfoToResource(&$resource)
    {
        $hour_adapter = new HourAdapter($this->mimetypes);
        $resource->set("readable_hours", $hour_adapter->getAllMerchantHoursHumanReadableV2($this->merchant_id));

    }

    function addTaxInfoToResource(&$resource)
    {
        $resource->set('base_tax_rate', $this->getTotalTax($this->merchant_id));
        $resource->set('tax_rates_by_group', $this->getTotalTaxRates($this->merchant_id));
    }

    function addFavoriteAndLastOrderImagesToResource(&$resource)
    {
        if (isProd()) {
            $skin = getContext();
            $resource->set("favorite_img_2x", $this->setFavoriteAndLastOrderImagesOnResource($skin, "large/2x/Favorite.jpg"));
            $resource->set("favorite_img_small_2x", $this->setFavoriteAndLastOrderImagesOnResource($skin, "small/2x/Favorite.jpg"));
            $resource->set("last_order_img_2x", $this->setFavoriteAndLastOrderImagesOnResource($skin, "large/2x/Lastorder.jpg"));
            $resource->set("last_order_img_small_2x", $this->setFavoriteAndLastOrderImagesOnResource($skin, "small/2x/Lastorder.jpg"));
        }
    }

    function getMerchantAvailableCateringOrderTimes($merchant_id, $hour_type)
    {
        if ($merchant_catering_info = MerchantCateringInfosAdapter::getInfoAsResourceByMerchantId($merchant_id)) {
            if ($merchant_catering_info->active != 'Y') {
                return createErrorResourceWithHttpCode(CateringController::CATERING_NOT_ACTIVE_FOR_THIS_MERCHANT,422,422);
            }
            $lead_time = new LeadTime($merchant_id);
            $lead_time->setCateringInfo($merchant_catering_info);
            $increment_in_minutes = $merchant_catering_info->time_increment_in_minutes;
            $lead_time->setCateringIncrementInMinutes($increment_in_minutes);

            // have to add +1 becuase lead time treats current day as 1 instead of 0
            $number_days = $merchant_catering_info->max_days_out + 1;

            $open_close_ts_array = $lead_time->getNextOpenAndCloseTimeStamps($merchant_id, $hour_type, $number_days, $this->the_time);
            foreach ($open_close_ts_array as &$record) {
                // need to subtract an hour from the value becuase the syste automatically pushes the first time 1 increment from opening.
                // for catering the increment is an hour.
                $record['open'] = $record['open'] + (($merchant_catering_info->min_lead_time_in_hours_from_open_time) * 60 * 60);
            }
            $first_time = $this->the_time + (3600*$merchant_catering_info->lead_time_in_hours);
            //myerror_log("current first time is: ".date("Y-m-d H:i:s",$first_time));
            $minutes = date(i,$first_time);
            $remainder_minutes = $minutes % $increment_in_minutes;
            $change = $remainder_minutes/$increment_in_minutes < .5 ? - $remainder_minutes : $increment_in_minutes - $remainder_minutes;
            $first_time = $first_time + $change*60;
            //myerror_log("now first time is: ".date("Y-m-d H:i:s",$first_time));
            $available_times = $lead_time->getAvailableTimesArray($open_close_ts_array, $first_time, $this->the_time, $hour_type);

            $existing_orders_by_day_part_array = $this->getExistingCateringOrdersForMerchantByDayPartArray($merchant_id,$merchant_catering_info->maximum_number_of_catering_orders_per_day_part);

            $available_times_with_display = new stdClass();
            $available_daily_times = array();
            $available_times_array = array();
            $last_day = intval(date('z',$available_times[0]));
            $time_zone = date('e', $available_times[0]);
            foreach ($available_times as $ts) {
                if ($this->catering_time_is_valid_for_existing_orders($existing_orders_by_day_part_array,$ts)) {
                    $this_day = intval(date('z',$ts));
                    if ($last_day == $this_day){
                      $available_daily_times[] = array("time"=> date('g:i a',$ts),"ts"=> $ts);
                    }else{
                      $available_times_array[] = $available_daily_times;
                      $available_daily_times = array();
                      $available_daily_times[] = array("time"=> date('g:i a',$ts),"ts"=> $ts);
                      $last_day = $this_day;
                    }

                }
            }
            
            $available_times_with_display -> time_zone = $time_zone;
            $available_times_with_display -> max_days_out = $merchant_catering_info->max_days_out;
            $available_times_with_display -> daily_time = $available_times_array;

            // my god this is stupid. i really need to recode everything to use a date object to get times and such and stop doing this getting and setting so much. sheesh
            date_default_timezone_set($lead_time->getStartingTz());
            $data = array();
            $data['available_catering_times'] = $available_times_with_display;
            $data['catering_place_order_times'] = $available_times;
            $data['catering_message_to_user_on_create_order'] = $merchant_catering_info->catering_message_to_user_on_create_order;
            $data['minimum_pickup_amount'] = $merchant_catering_info->minimum_pickup_amount;
            $data['minimum_delivery_amount'] = $merchant_catering_info->minimum_delivery_amount;
            return Resource::dummyfactory($data);
        } else {
            return createErrorResourceWithHttpCode("This merchant does not have catering set up",500,500);
        }
    }

    function getExistingCateringOrdersForMerchantByDayPartArray($merchant_id,$max_orders_per_day_part)
    {
        $catering_orders = CateringOrdersAdapter::getActiveFutureCateringOrdersByMerchantId($merchant_id,$max_orders_per_day_part);
        $existing_orders_by_day_part_array = array();
        foreach ($catering_orders as $catering_order) {
            $date_tm_of_event = $catering_order->date_tm_of_event;
            $d = explode(' ',$date_tm_of_event);
            $date = $d[0];
            $time = $d[1];
            $hour = explode(':',$time);
            $day_part = $hour[0] <= 13 ? 'morning' : 'afternoon';

            if ($existing_orders_by_day_part_array[$date][$day_part] != 'closed') {
                $orders_by_this_day_part = $existing_orders_by_day_part_array[$date][$day_part] + 1;
                if ($orders_by_this_day_part >= $max_orders_per_day_part) {
                    $existing_orders_by_day_part_array[$date][$day_part] = 'closed';
                } else {
                    $existing_orders_by_day_part_array[$date][$day_part] = $existing_orders_by_day_part_array[$date][$day_part] + 1;
                }
            }
        }
        return $existing_orders_by_day_part_array;
    }

    function catering_time_is_valid_for_existing_orders($existing_orders_by_day_part_array,$ts)
    {
        $day = date('Y-m-d',$ts);
        $hour = date('H',$ts);
        if ($hour <= 13) {
            return $existing_orders_by_day_part_array[$day]['morning'] != 'closed';
        } else {
            return $existing_orders_by_day_part_array[$day]['afternoon'] != 'closed';
        }
    }

    function getMerchantAvailableGroupOrderTimes($merchant_id, $hour_type)
    {
        $merchant_resource = Resource::find($this->adapter, $merchant_id);
        setTheDefaultTimeZone($merchant_resource->time_zone, $merchant_resource->state);
        $max_day_out = 2;
        $lead_time = new LeadTime($merchant_id);
        $holehours_object = new HoleHoursAdapter(getM());
        $hole_hours = $holehours_object->getByMerchantIdAndOrderType($merchant_id, $hour_type);
        $lead_time->setGroupOrderMaxAndMinLeads();
        // default to 7 entres level items
        $lead_time->setMinMaxAndIncrement(7, $hour_type);
        $lead_time->setHoleHours($hole_hours, $this->the_time);
        $open_close_ts_array = $lead_time->getNextOpenAndCloseTimeStamps($merchant_id, $hour_type, $max_day_out, $this->the_time);
        foreach ($open_close_ts_array as &$record) {
            // set close 30 minutes before actual close
            $record['close'] = $record['close'] - (30 * 60);
        }
        $first_time = $this->the_time + 3600;
        $available_times = $lead_time->getAvailableTimesArray($open_close_ts_array, $first_time, $this->the_time, $hour_type);
        $submit_times = array();
        foreach ($available_times as $available_time) {
            $submit_times[] = $available_time - (45 * 60);
        }
        return $submit_times;
    }

    function setFavoriteAndLastOrderImagesOnResource($skin, $image_url){
        if (isLaptop()) {
            return "";
        }
      $image = "https://s3.amazonaws.com/com.splickit.products/".$skin."/menu-item-images/".$image_url;
      if(getimagesize($image)){
        return $image;
      }else{
        return "";
      }
    }


    function setTheTime($the_time)
    {
        $this->the_time = $the_time;
    }

    function touchMerchantRecord($merchant_id)
    {
        $merchant_resource = Resource::find($this->adapter, "" . $merchant_id);
        $merchant_resource->modified = time();
        $merchant_resource->save();
    }

    function setMerchantResource($merchant_resource)
    {
        $adapter = $merchant_resource->_adapter;
        if ($adapter->table == 'Merchant' && $merchant_resource->_exists = true)
            $this->merchant_resource = $merchant_resource;
        else
            throw new Exception("ERROR! that is not a valid merchant resource");
    }

    function setLatLong($limit = 0)
    {
        // get limit if it exists in the request
        if ($this->request->data['limit'])
            $limit = $this->request->data['limit'];
        $options[TONIC_FIND_BY_METADATA]['lat'] = '0.000000';
        $options[TONIC_FIND_BY_METADATA]['lng'] = '0.000000';
        $options[TONIC_FIND_BY_METADATA]['merchant_id'] = array('>' => 1000);
        if ($merchants = $this->adapter->select('', $options)) {
            $i = 0;
            foreach ($merchants as $merchant) {
                $address = "" . $merchant['address1'] . "," . $merchant['city'] . "," . $merchant['state'] . " " . $merchant['zip'];
                $address = str_ireplace(' ', '+', $address);
                myerror_logging(1, "MerchantController: the address in Lat Long is: " . $address);
                if ($data = LatLong::generateLatLong($address)) {
                    $latitude = $data['lat'];
                    $longitude = $data['lng'];

                    $merchantresource =& Resource::find($this->adapter, '/merchants/' . $merchant['merchant_id']);
                    $merchantresource->lat = $latitude;
                    $merchantresource->lng = $longitude;
                    myerror_logging(1, "MerchantController: about to update the lat long for merchant " . $merchant['name'] . "  id: " . $merchant['merchant_id']);
                    $this->adapter->update($merchantresource);
                }
                $i++;
                sleep(1);
                if ($limit != 0 && $i == $limit)
                    die ('over the limit of ' . $limit);
            }
        }

    }

    function getMerchantList2($skin_id = 0)
    {
        return $this->getMerchantList($skin_id, true);
    }

    function getMerchantList($skin_id = 0, $promo_info = false)
    {
        $options[TONIC_FIND_BY_METADATA] = $this->request->data;
        myerror_logging("MerchantController request url : " . $this->request->url);
        $user = $this->user;
        $used_merchant_id_based_search = false;
        if ($skin_id != 0) {
            if (isset($this->request->data['merchantlist'])) {
                myerror_logging(3, "MerchantController search : by merchant _id  " . $this->request->data['merchantlist']);
                $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $this->request->data['merchantlist'];
                $options[TONIC_JOIN_STATEMENT] = " LEFT JOIN Merchant_Catering_Infos ON Merchant.merchant_id = Merchant_Catering_Infos.merchant_id ";

                $used_merchant_id_based_search = true;
            } else {
                $skin_merchant_map_adapter = new SkinMerchantMapAdapter($this->mimetypes);
                if (substr_count($this->request->url, '/skinlist') > 0) {
                    $records = $skin_merchant_map_adapter->getMerchantIdListForSkin($skin_id);
                    return Resource::dummyFactory(array('data' => $records));
                } else if (substr_count($this->request->url, '/fullskinlist') > 0) {
                    $options[TONIC_JOIN_STATEMENT] = " JOIN Skin_Merchant_Map ON Merchant.merchant_id = Skin_Merchant_Map.merchant_id LEFT JOIN Merchant_Catering_Infos ON Merchant.merchant_id = Merchant_Catering_Infos.merchant_id ";
                    $options[TONIC_FIND_BY_STATIC_METADATA] .= " Skin_Merchant_Map.skin_id = $skin_id ";
                    $options[TONIC_FIND_BY_METADATA]['merchant_id'] = array('>' => 1000);
                    $options[TONIC_FIND_BY_METADATA]['active'] = 'Y';
                } else {


                    $use_zip = false;
                    // short circuit for line buster
                    if (substr_count($this->user['email'], "_manager@dummy.com") > 0) {
                        $linebuster_adapter = new LineBusterAdapter(getM());
                        if ($m_resource = Resource::findExact($linebuster_adapter, $this->user['email']))
                            $line_buster_merchant_id = $m_resource->merchant_id;
                    }

                    if ($line_buster_merchant_id > 0) {
                        $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $line_buster_merchant_id;
                    } else if (isset($this->request->data['airport_id'])) {
                        $airport_id = $this->request->data['airport_id'];
                    } else if (($this->request->data['lat'] && $this->request->data['lat'] != NULL && trim($this->request->data['lat']) != '')) {
                        $lat = $this->request->data['lat'];
                        $long = (isset($this->request->data['long'])) ? $this->request->data['long'] : $this->request->data['lng'];
                        $options[TONIC_FIND_STATIC_FIELD] = "( 3959 * acos( cos( radians(" . $lat . ") ) * cos( radians( lat ) ) * cos( radians( lng ) - radians(" . $long . ") ) + sin( radians(" . $lat . ") ) * sin( radians( lat ) ) ) ) AS distance ";
                        $options[TONIC_SORT_BY_METADATA] = "distance";
                        unset($options[TONIC_FIND_BY_METADATA]['zip']);
                    } else if (($this->request->data['zip'] && $this->request->data['zip'] != NULL && trim($this->request->data['zip']) != '') || (isset($this->request->data['location']) && preg_match("([0-9]{5})", $this->request->data['location']))) {
                        $data = $this->request->data;
                        if (isset($data['zip'])) {
                            $zip = $data['zip'];
                        } else if (isset($data['location']) && preg_match('([0-9]{5})', $data['location'])) {
                            $zip = $data['location'];
                        }

                        $key_values = $key_values = '([0-9]{5})';
                        preg_match($key_values, $zip, $matches);
                        if ($matches) {
                            if ($data = ZipLookupAdapter::getZipInfo($zip)) {
                                myerror_logging(3, "MerchantController: we were able to obtain lat long of the zip code");
                            } else {
                                myerror_log("MerchantController: ERROR!  could not get lat long of zip. resorting to fake zip search");
                                if ($data = ZipLookupAdapter::getFakeZipCodeLatLong($zip))
                                    myerror_log("MerchantController: we have faked a zip code from an existing merchant that matches 2 or 3 digits");
                                else
                                    return returnErrorResource("We're sorry, but $zip does not seem to be a valid zip code. Please check your entry");
                            }
                            $lat = $data['lat'];
                            $long = $data['lng'];
                            myerror_logging(3, "MerchantController: lat long retrieved   $lat    $long");
                            $options[TONIC_FIND_STATIC_FIELD] = "( 3959 * acos( cos( radians(" . $lat . ") ) * cos( radians( lat ) ) * cos( radians( lng ) - radians(" . $long . ") ) + sin( radians(" . $lat . ") ) * sin( radians( lat ) ) ) ) AS distance ";
                            $options[TONIC_SORT_BY_METADATA] = "distance";
                            unset($options[TONIC_FIND_BY_METADATA]['zip']);
                        } else {
                            return returnErrorResource("Sorry, that is not a valid zip code, please check your entry");
                        }
                        unset($this->request->data['limit']);
                    } else {
                        myerror_logging(3, "MerchantController: SKIPPING ANY LOCATION SEARCHING.  version is < 2.081");
                    }

                    if ($range = $this->request->data['range']) {
                        $minimum_merchant_count = $this->request->data['minimum_merchant_count'];
                        $merchant_id_exclude_list = $this->request->data['exclude_ids'];
                        myerror_logging(3, "MerchantController: we have a range of " . $range . " miles");
                        myerror_logging(3, "MerchantController: we have a minimum merchant count of " . $minimum_merchant_count);
                    } else {
                        $options[TONIC_FIND_TO] = 50;
                    }
                    if (isset($this->request->data['limit']))
                        $options[TONIC_FIND_TO] = $this->request->data['limit'];
                    unset($options[TONIC_FIND_BY_METADATA]['long']);
                    unset($options[TONIC_FIND_BY_METADATA]['lng']);
                    unset($options[TONIC_FIND_BY_METADATA]['lat']);
                    unset($options[TONIC_FIND_BY_METADATA]['zip']);

                    $options[TONIC_JOIN_STATEMENT] = " JOIN Skin_Merchant_Map ON Merchant.merchant_id = Skin_Merchant_Map.merchant_id ";
                    $options[TONIC_JOIN_STATEMENT] .= " LEFT JOIN Merchant_Catering_Infos ON Merchant.merchant_id = Merchant_Catering_Infos.merchant_id ";
                    if ($options[TONIC_FIND_BY_STATIC_METADATA]) {
                        $options[TONIC_FIND_BY_STATIC_METADATA] .= ' AND ';
                    }
                    $options[TONIC_FIND_BY_STATIC_METADATA] .= " Skin_Merchant_Map.skin_id = $skin_id ";

                    if ($used_search_criteria = validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($this->request->data, 'query')) {
                        $field = $this->request->data['query'];
                        $field = mysqli_real_escape_string($this->adapter->_handle, $field);
                        $options[TONIC_FIND_BY_STATIC_METADATA] .= " AND ( strip_punc(Merchant.display_name) LIKE strip_punc('%$field%') OR strip_punc(Merchant.name) LIKE strip_punc('%$field%') OR strip_punc(Merchant.city) LIKE strip_punc('%$field%') OR strip_punc(Merchant.address1) LIKE strip_punc('%$field%')  OR strip_punc(Brand2.brand_name) LIKE strip_punc('%$field%') ) ";
                        $options[TONIC_JOIN_STATEMENT] .= " JOIN Brand2 ON Merchant.brand_id = Brand2.brand_id ";
                        unset($options[TONIC_FIND_BY_METADATA]['query']);
                    } else if ($used_search_criteria = validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($this->request->data, 'location') && !preg_match('([0-9]{5})', $this->request->data['location'])) {
                        $location_search = explode(',', $this->request->data['location']);
                        $first = trim($location_search[0]);
                        if (!ctype_alnum(str_replace('.', '', str_replace(' ', '', str_replace(',', '', str_replace("'", '', $first)))))) {
                            return returnErrorResource("Sorry, please enter only letters and numbers for city, state or zip.");
                        }
                        if (strlen($first) < 2) {
                            return returnErrorResource("Sorry, you must enter at least two letters of a state abreviation or zip");
                        }
                        if (count($location_search) == 1) {
                            //check for full state name
                            if ($state_abreviation = $this->getStateAbreviationFromFullStateName($first)) {
                                $first = $state_abreviation;
                            }
                        }
                        if (strlen($first) == 2) {
                            $options[TONIC_FIND_BY_STATIC_METADATA] .= " AND Merchant.state = '$first' ";
                        } else {
                            $first = str_replace("'", "''", $first);
                            $dma_codes_adapter = new DmaCodesAdapter(getM());

                            if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmptyByIndex($location_search, 1)) {
                                //search by region name and state
                                $state = trim($location_search[1]);
                                $data['dma_region'] = $first;
                                $data['state'] = $state;
                                $dma_codes_record = $dma_codes_adapter->getRegionCodeByData($data, "Sorry. We could not find any existing merchants matching that City, St. If you feel you have reached this in error, please try again or contact support. ", 404, 999);

                                if (!empty($dma_codes_record->error)) {
                                    return $dma_codes_record;
                                }
                                if ($dma_codes_record != null && !empty($dma_codes_record)) {
                                    $sql = "SELECT c.*, (Merchant_Catering_Infos.active = 'Y') as has_catering, IFNULL(smawv_mmm_status.pickup_rec, 0) AS show_pickup_button, IFNULL(smawv_mmm_status.delivery_rec, 0) AS show_delivery_button   FROM adm_dma a JOIN Merchant c ON c.state = a.st AND c.city = a.city JOIN Skin_Merchant_Map ON c.merchant_id = Skin_Merchant_Map.merchant_id  AND Skin_Merchant_Map.skin_id = " . getSkinIdForContext() . " LEFT JOIN Merchant_Catering_Infos ON c.merchant_id = Merchant_Catering_Infos.merchant_id LEFT JOIN smawv_mmm_status ON c.merchant_id = smawv_mmm_status.merchant_id WHERE a.dma_region_code = " . $dma_codes_record['dma_region_code'];
                                    $options[TONIC_FIND_BY_SQL] = $sql;
                                } else {
                                    $options[TONIC_FIND_BY_STATIC_METADATA] .= " AND (Merchant.city LIKE '%$first%' OR Merchant.zip LIKE '%$first%') ";
                                }

                            } else {
                                //search by region name
                                $data['dma_region'] = $first;
                                $dma_codes_record = $dma_codes_adapter->getRegionCodeByData($data, "Many registries with this city. Please enter city, st.", 409);

                                if (!empty($dma_codes_record->error)) {
                                    return $dma_codes_record;
                                }
                                if ($dma_codes_record != null && !empty($dma_codes_record)) {
                                    $sql = "SELECT c.*, (Merchant_Catering_Infos.active = 'Y') as has_catering, IFNULL(smawv_mmm_status.pickup_rec, 0) AS show_pickup_button, IFNULL(smawv_mmm_status.delivery_rec, 0) AS show_delivery_button   FROM adm_dma a JOIN Merchant c ON c.state = a.st AND c.city = a.city JOIN Skin_Merchant_Map ON c.merchant_id = Skin_Merchant_Map.merchant_id  AND Skin_Merchant_Map.skin_id = " . getSkinIdForContext() . " LEFT JOIN Merchant_Catering_Infos ON c.merchant_id = Merchant_Catering_Infos.merchant_id LEFT JOIN smawv_mmm_status ON c.merchant_id = smawv_mmm_status.merchant_id WHERE a.dma_region_code = " . $dma_codes_record['dma_region_code'];
                                    $options[TONIC_FIND_BY_SQL] = $sql;
                                } else {
                                    $options[TONIC_FIND_BY_STATIC_METADATA] .= " AND (Merchant.city LIKE '%$first%' OR Merchant.zip LIKE '%$first%') ";
                                }
                            }
                        }
                        if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmptyByIndex($location_search, 1)) {
                            $state = str_replace("'", "''", trim($location_search[1]));
                            if (strlen($state) > 2) {
                                myerror_log("bad state format! reject location based search");
                                return createErrorResourceWithHttpCode("Sorry. We could not find any merchants matching those search criteria. Please make sure you use format 'city, state abreviation' or just 'city' or just 'state abbreviation' or just 'zip'.", 404, 999);
                            }
                            $options[TONIC_FIND_BY_STATIC_METADATA] .= " AND Merchant.state = '$state' ";
                        }
                        $used_location_based_search = true;
                        unset($options[TONIC_FIND_TO]);
                    }

                    // do the hack to prevent store_tester from seeing the demo stores.
                    if (!$this->shouldUserSeeDemoMerchants($user)) {
                        if (!isset($options[TONIC_FIND_BY_METADATA]['merchant_id'])) {
                            $options[TONIC_FIND_BY_METADATA]['merchant_id'] = array('>' => 1000);
                        }
                    }
                    if ($this->shouldUserSeeActiveMerchantsOnly($user)) {
                        $options[TONIC_FIND_BY_METADATA]['active'] = 'Y';
                        if ($options[TONIC_FIND_BY_SQL]) {
                            $options[TONIC_FIND_BY_SQL] .= " AND c.active = 'Y'";
                        }
                    }

                    // allow line buster to see innactive merchants so they can test.
                    if ($line_buster_merchant_id > 1000)
                        unset($options[TONIC_FIND_BY_METADATA]['active']);
                }
            }
            //record location of request for merchant list.  this is for marketing and research purposes
            if ($lat != null && $long != null && $this->user['user_id'] > 9998) {
                $mlrl_adapter = new MerchantListRequestLocationAdapter($this->mimetypes);
                $mlrl_adapter->log_level = 0;
                $mlrla_data = array('lat' => $lat, 'long' => $long, 'skin_id' => $skin_id, 'user_id' => $this->user['user_id']);
                $mlrla_resource =& Resource::factory($mlrl_adapter, $mlrla_data);
                $mlrla_resource->save();
            }
        }

        //cateing flag
        $options[TONIC_FIND_STATIC_FIELD] = isset($options[TONIC_FIND_STATIC_FIELD]) ? $options[TONIC_FIND_STATIC_FIELD].", (Merchant_Catering_Infos.active = 'Y') as has_catering " : " (Merchant_Catering_Infos.active = 'Y') as has_catering ";

        // add menu buttons code
        $options[TONIC_JOIN_STATEMENT] .= " LEFT JOIN smawv_mmm_status ON Merchant.merchant_id = smawv_mmm_status.merchant_id ";
        $options[TONIC_FIND_STATIC_FIELD] .= ", IFNULL(smawv_mmm_status.pickup_rec, 0) AS show_pickup_button, IFNULL(smawv_mmm_status.delivery_rec, 0) AS show_delivery_button  ";

        $merchant_list_adapter = new MerchantListAdapter(getM());
        if ($airport_id) {
            if (shouldSystemProcessAsRegularUser($this->user['user_id'])) {
                $airport_data['active'] = 'Y';
            }
            $initial_merchants = $merchant_list_adapter->selectAirportLocations($airport_id, $skin_id, $airport_data);
            $airport_areas = AirportAreasAdapter::staticGetAirportAreas($airport_id);
            $range = 0;
        } else {
            logData($options, "OPTIONS");
            $initial_merchants = $merchant_list_adapter->select('', $options);
        }


        $merchants = array();
        myerror_logging(3, "MerchantController: number of initial merchants found is: " . count($initial_merchants));
        if (sizeof($initial_merchants) < 1) {
            myerror_log("MerchantController: ERROR!  No merchants found in get merchant list");
            if ($used_search_criteria) {
                if ($used_location_based_search) {
                    return createErrorResourceWithHttpCode("Sorry. We could not find any merchants matching those search criteria. Please make sure you use format 'city, state abreviation' or just 'city' or just 'state abbreviation' or just 'zip'.", 404, 999);
                }
                return createErrorResourceWithHttpCode("Sorry. We could not find any existing merchants matching that name or having that brand.", 404, 999);
            }
            if ($used_merchant_id_based_search) {
                return createErrorResourceWithHttpCode("Sorry. We could not find any existing merchants matching that Merchant Id. If you feel you have reached this in error, please try again or contact support.", 404, 999);
            }
            return createErrorResourceWithHttpCode("Sorry. We could not find any merchants. If you feel you have reached this in error, please try again or contact support.", 404, 999);

        } else if ($range > 0) {
            $continue = false;
            foreach ($initial_merchants as $merchant) {
                $the_merchant_id_as_string = (string)$merchant['merchant_id'];
                if ($continue)
                    continue;
                else if (substr_count($merchant_id_exclude_list, $the_merchant_id_as_string) > 0)
                    continue;
                else if ($merchant['distance'] < $range)
                    $merchants[] = $merchant;
                else if (sizeof($merchants) < $minimum_merchant_count)
                    $merchants[] = $merchant;
                else
                    $continue = true;
            }
        } else {
            $merchants = $initial_merchants;
        }
        // get brand info
        if ($initial_merchants) {
            $brand_adapter = new BrandAdapter($this->mimetypes);
            $brands = $brand_adapter->select('', null);
            $brand_list = array();
            foreach ($brands as $brand) {
                $brand_list[$brand['brand_id']] = $brand['brand_name'];
            }

        }
        foreach ($merchants as &$merchant) {
            $merchant_id = $merchant['merchant_id'];
            $merchant_promos = array();

            // to prevent IOS from crashing
            if ($merchant['display_name'] == null || trim($merchant['display_name']) == '') {
                if (!isAggregate()) {
                    $merchant['display_name'] = $merchant['city'];
                } else {
                    $merchant['display_name'] = $merchant['name'];
                }
            }

            if (!isAggregate()) {
                $merchant['name'] = $merchant['display_name'];
            }
            // add brand info
            if ($merchant['brand'] = $brand_list[$merchant['brand_id']]) {
                ;//myerror_log("MerchantController: brand info added to merchant");
            } else {
                $merchant['brand'] = $merchant['name'];
            }
            $merchant['brand_name'] = $merchant['brand'];

            // left over field from rewardr. keeping it in so not to potentially blow up apps.
            $merchant['promo_count'] = 'NA';

            // force catering for the Luby's locations
            if ($merchant['has_catering'] && $merchant['brand_id'] == 434) {
                $merchant['force_catering'] = "1";
            }

            if ($merchant['delivery'] == 'Y' && $this->hasRequestForDestination('fullskinlist')) {
                // get delivery info
                if ($merchant_delivery_info = $this->getMerchantDeliveryInfoNew($merchant_id)) {
                   $merchant['delivery_area_info'] = array("delivery_zone_type"=>$merchant_delivery_info['delivery_price_type'],"delivery_prices"=>$merchant_delivery_info['delivery_prices']);
                }
            }

        }
        myerror_logging(3, "the number of merchants retrieved is: " . count($merchants));

        if ($promo_info && $initial_merchants) {
            $m_data['merchants'] = $merchants;

            // keep field to prevent potential app crashes
            $m_data['promos'] = array();

            if ($airport_areas)
                $m_data['airport_areas'] = $airport_areas;
            $resource =& Resource::dummyFactory($m_data);
        } else {
            $resource =& Resource::factory($this->adapter, array('data' => $merchants));
        }
        return $resource;
    }

    function shouldUserSeeActiveMerchantsOnly($user)
    {
        return !$this->shouldUserSeeInActiveMerchants($user);
    }

    function shouldUserSeeInActiveMerchants($user)
    {
        return ($user['see_inactive_merchants'] == 1) || ($user['see_inactive_merchants'] == true);
    }

    function shouldUserSeeDemoMerchants($user)
    {
        return ($user['see_demo_merchants'] == true);
    }

    function getStateAbreviationFromFullStateName($name)
    {
        return LookupAdapter::staticGetValueFromTypeAndName('state', ucwords(strtolower($name)));
    }

    function updateMerchant()
    {
        // cant do this here
        unset($this->request->data['merchant_user_id']);
        unset($this->request->data['numeric_id']);
        unset($this->request->data['alphanumeric_id']);

        $this->merchant_resource->_updateResource($this->request);
    }

    function updateHours()
    {
        $hour_adapter = new HourAdapter($this->mimetypes);
        foreach ($this->request->data as $hour_record) {
            $hour_resource = Resource::factory($hour_adapter, $hour_record);
            if (isset($hour_record['hour_id']) && $hour_record['hour_id'] > 0)
                $hour_resource->_exists = true;
            else
                $hour_resource->merchant_id = $this->merchant_resource->merchant_id;
            $hour_resource->modified = time();
            $hour_resource->save();
        }
        return $this->getMerchantInfoAsResource();
    }

    function updateTaxes()
    {
        $tax_adapter = new TaxAdapter($this->mimetypes);
        foreach ($this->request->data as $tax_record) {
            $tax_resource = Resource::factory($tax_adapter, $tax_record);
            if (isset($tax_record['tax_id']) && $tax_record['tax_id'] > 0)
                $tax_resource->_exists = true;// all is good
            else
                $tax_resource->merchant_id = $this->merchant_resource->merchant_id;
            $tax_resource->modified = time();
            $tax_resource->save();
        }
        return $this->getMerchantInfoAsResource();
    }

    function updateDelivery()
    {
        $mdi_adapter = new MerchantDeliveryInfoAdapter($this->mimetypes);
        if ($delivery_prices = $this->request->data['delivery_prices']) ;
        {
            unset($this->request->data['delivery_prices']);
            $mdpd_adapter = new MerchantDeliveryPriceDistanceAdapter($this->mimetypes);
        }
        $delivery_info = $this->request->data;
        $delivery_resource = Resource::factory($mdi_adapter, $delivery_info);
        if (isset($delivery_info['merchant_delivery_id']) && $delivery_info['merchant_delivery_id'] > 0)
            $delivery_resource->_exists = true;
        else
            $delivery_resource->merchant_id = $this->merchant_resource->merchant_id;
        $delivery_resource->modified = time();
        $delivery_resource->save();
        foreach ($delivery_prices as $delivery_price_record) {
            $mdpd_resource = Resource::factory($mdpd_adapter, $delivery_price_record);
            if (isset($delivery_price_record['map_id']) && $delivery_price_record['map_id'] > 0)
                $mdpd_resource->_exists = true;// all is good
            else
                $mdpd_resource->merchant_id = $this->merchant_resource->merchant_id;
            $mdpd_resource->modified = time();
            $mdpd_resource->save();
        }

        return $this->getMerchantInfoAsResource();
    }

    function getMerchantInfoAsResourceV2($merchant_id)
    {
        $resource = Resource::find($this->adapter, "$merchant_id");
        $resource->set('base_tax_rate', $this->getTotalTax($merchant_id));
        $resource->set('tax_rates_by_group', $this->getTotalTaxRates($merchant_id));
        $hour_adapter = new HourAdapter($this->mimetypes);
        $resource->set("readable_hours", $hour_adapter->getAllMerchantHoursHumanReadableV2($merchant_id));
        // get delivery info if it exists
        if ($merchant_delivery_info = $this->getMerchantDeliveryInfoNew($merchant_id)) {
            $resource->set("delivery_info", $merchant_delivery_info);
        }
        $resource->set("menus", MerchantMenuMapAdapter::staticGetRecords(array("merchant_id" => $merchant_id), 'MerchantMenuMapAdapter'));
        $this->merchant_resource = $resource;
        return $this->cleanResourceForV2Response($resource);
    }

    function cleanResourceForV2Response($resource)
    {
        $removed_fields = array("EIN_SS", "cc_processor", "merchant_type", "show_search", "order_del_type", "order_del_addr", "order_del_addr2",
            "payent_cycle", "live_dt", "created", "modified", "logical_delete", "mimetype", "class");
        foreach ($removed_fields as $removed_field) {
            unset($resource->$removed_field);
        }
        return $resource;
    }


    function getMerchantInfoAsResource($merchant_id = 0)
    {
        if ($merchant_id == 0) {
            $resource = $this->request->load($this->adapter);
            $merchant_id = $resource->merchant_id;
        } else {
            $resource = Resource::find($this->adapter, '' . $merchant_id);
        }

        // add taxes
        $resource->set('tax_rate', $this->getTotalTax($merchant_id));
        $resource->set('tax_rates_by_group', $this->getTotalTaxRates($merchant_id));

        // get hours
        $hour_adapter = new HourAdapter($this->mimetypes);
        $resource->set("hours", $hour_adapter->getAllMerchantHourRecords($merchant_id));
        $readable_hours = $hour_adapter->getAllMerchantHoursHumanReadable($merchant_id);
        $sunday = array_shift($readable_hours);
        $readable_hours[] = $sunday;
        $resource->set("readable_hours", $readable_hours);

        // get delivery info if it exists
        if ($merchant_delivery_info = $this->getMerchantDeliveryInfoNew($merchant_id)) {
            $resource->set("delivery_info", $merchant_delivery_info);
        }

        // get menus
        $mmm_adapter = new MerchantMenuMapAdapter($this->mimetypes);
        $resource->set("menus", $mmm_adapter->getRecords(array("merchant_id" => $merchant_id)));

        $this->addFavoriteAndLastOrderImagesToResource($resource);

        $this->merchant_resource = $resource;

        return $resource;
    }

    function getMerchantDeliveryInfoNew($merchant_id)
    {
        if ($record = MerchantDeliveryInfoAdapter::staticGetRecord(array("merchant_id" => $merchant_id), 'MerchantDeliveryInfoAdapter')) {
            $record['delivery_prices'] = MerchantDeliveryPriceDistanceAdapter::staticGetRecords(array("merchant_id" => $merchant_id), 'MerchantDeliveryPriceDistanceAdapter');
            return $record;
        }
    }

    function getMerchantInfo($merchant_id)
    {
        $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
        if ($merchant = $this->adapter->select('', $options)) {
            $merchant = array_pop($merchant);
            $merchant['tax_rate'] = $this->getTotalTax($merchant_id);
            $merchant['tax_rates_by_group'] = $this->getTotalTaxRates($merchant_id);
        }
        return $merchant;
    }

    /**
     *
     * @desc will create and send a windows test message
     * @return Response
     */

    function createWindowsTestMessage()
    {
        $key_values = '%/([0-9]{2,11})/%';
        preg_match($key_values, $this->request->url, $matches);
        $id = 0;
        if ($matches) {
            $id = str_ireplace('/', '', $matches[0]);
            myerror_logging(1, "MerchantController: WE HAVE AN ID IN createWindowsTestMessage: " . $id);
        } else {
            die("NO ID SUBMITTED");
        }
        $merchant_options[TONIC_FIND_BY_METADATA]['numeric_id'] = $id;
        if ($merchants = $this->adapter->select('', $merchant_options)) {
            $merchant = array_pop($merchants);
            $merchant_id = $merchant['merchant_id'];

            $mmha = new MerchantMessageHistoryAdapter($this->mimetypes);
            $mmha_data['merchant_id'] = $merchant_id;
            $mmha_data['order_id'] = '0';
            $mmha_data['message_format'] = 'WT';
            $mmha_data['message_delivery_addr'] = '1';
            $mmha_data['next_message_dt_tm'] = date('Y-m-d H:i:s');
            $mmha_data['message_type'] = 'I';
            $mmha_data['info'] = '' . $id;
            $resource = new Resource($mmha, $mmha_data);
            if ($resource->save()) {
                respondWithPlainTextBody("Test Message Created", 200);
            }
        } else {
            myerror_log("MerchantController: SERIOUS ERROR IN MERCHANT CONTROLLER!  no matching merchant for submitted numeric_id: " . $id);
            MailIt::sendErrorMail('arosenthal@dummy.com,tarek@dummy.com,dave@dummy.com', 'no matching merchant for submitted numeric_id: ' . $id);
        }
        respondWithPlainTextBody("SERIOUS ERROR", 400);
    }

    function getMenuStatus()
    {
        if ($menu_resource =& $this->request->load(new MenuAdapter($this->mimetypes))) {
            $resource = new Resource();
            $resource->set('menu_key', $menu_resource->last_menu_change);
            $resource->set('menu_id', $menu_resource->menu_id);
            unset($resource->modified);
            unset($resource->created);
            return $resource;
        } else {
            myerror_log("MerchantController: ERROR!  NO EXISTING MENU FOR THIS ID");
            $resource = new Resource();
            $resource->set("error_code", "1000");
            $resource->set('error', "NO EXISTING MENU FOR THIS ID");
            return $resource;
        }

    }

    function getMerchant()
    {
        if ($merchant_id = $this->merchant_id)
            ; // all is good
        else if (preg_match('%([0-9]{4,10})%', $this->request->url, $matches))
            $merchant_id = $matches[0];
        else {
            myerror_log("MerchantController: Error! NO merchant id passed in for get merchant");
            return false;
        }

        if (! SkinMerchantMapAdapter::isMerchantInSkin(getSkinIdForContext(),$merchant_id)) {
            return createErrorResourceWithHttpCode("Merchant $merchant_id does not exist for context: ".getSkinNameForContext(), 422, 422);
        }

        $menu_type = 'pickup';
        if (preg_match('%/delivery%', $this->request->url))
            $menu_type = 'delivery';
        $complete_merchant = new CompleteMerchant($merchant_id);

        // the time should always be current time except in testing
        $complete_merchant->setTimeForTesting($this->the_time);

        $complete_merchant->api_version = getEndpointVersion($this->request);

        if ($this->hasRequestForDestination('catering')) {
            $menu_type = $menu_type.'-catering';
        }
        myerror_log("the merchant menu type is: ".$menu_type,5);
        $this->merchant_resource = $complete_merchant->getCompleteMerchant($menu_type);

        $favorite_controller = new FavoriteController(getM(), $this->user);
        $favorites = $favorite_controller->getFavorites($this->merchant_resource, $complete_merchant->api_version);

        if (count($favorites) > 0) {
            if ($this->isSubmittedUserDataAGuest()) {
                $this->merchant_resource->set('user_favorites', array());
            } else {
                $this->merchant_resource->set('user_favorites', $favorites);
            }
        }

        $last_orders = $complete_merchant->loadLastOrdersValidForUserAndMenu($this->user['user_id'], $this->merchant_resource->menu);

        if (count($last_orders) > 0) {
            if ($this->isSubmittedUserDataAGuest()) {
                $this->merchant_resource->set('user_last_orders', array());
            } else {
                $this->merchant_resource->set('user_last_orders', $last_orders);
            }
        }
        //additional_menu_sections for v1 merchant menu call
        if ($complete_merchant->api_version == 1 && isset($this->merchant_resource->menu)) {
            myerror_log("starting the new favorite code for apiv1 menu call");
            $menu = CompleteMenu::getCompleteMenu($this->merchant_resource->menu_id, 'Y', $merchant_id, 2, $menu_type); //we need reload menu in APIv2 format to correct process favorites and last orders
            $merchant = new Resource();
            $merchant->set('merchant_id', $this->merchant_resource->merchant_id);
            $merchant->set('menu_id', $this->merchant_resource->menu_id);
            $merchant->set('menu', $menu);

            $this->merchant_resource->menu['additional_menu_sections'] = array(
                'user_favorites' => $favorite_controller->getFavorites($merchant, 2), //force reload favorites with APIv2 format
                'user_last_orders' => $complete_merchant->loadLastOrdersValidForUserAndMenu($this->user['user_id'], $menu)
            );
        }
        $this->addFavoriteAndLastOrderImagesToResource($this->merchant_resource);
        $nutrition_flag = $this->getNutritionFlag(getBrandForCurrentProcess());
        $this->merchant_resource->set("nutrition_flag", $nutrition_flag);
        if($nutrition_flag == "1"){
          $this->merchant_resource-> set ("nutrition_message", $this->getNutritionMessage());
        }
        return $this->merchant_resource;
    }

    function isLastOrderedMerchantIdDifferentThenCurrentMerchantRequest($user, $merchant_id)
    {
        return isset($user['last_order_merchant_id']) && $user['last_order_merchant_id'] != $merchant_id;
    }

    function getMerchantForEdit()
    {
        if ($resource =& $this->request->load($this->adapter)) {
            $merchant_id = $resource->merchant_id;

            // now get menu id for this merchant
            $merchant_menu_map_adapter = new MerchantMenuMapAdapter($this->mimetypes);
            $menu_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
            $merchant_menu_type = 'pickup';
            if (preg_match('%/delivery%', $this->request->url))
                $merchant_menu_type = 'delivery';
            $menu_options[TONIC_FIND_BY_METADATA]['merchant_menu_type'] = $merchant_menu_type;

            if ($menu_maps = $merchant_menu_map_adapter->select('', $menu_options)) {
                $menu_map = array_pop($menu_maps);
                $menu_id = $menu_map['menu_id'];
            } else {
                MailIt::sendErrorEmail('SPLICKIT ERROR! No menu mapping for merchant', 'merchant_id: ' . $merchant_id);
                $resource->set("error_code", "540");
                $resource->set('error', "We're sorry, but this location does not appear to be accepting orders right now.");
                return $resource;
            }

            $resource->set('menu_id', $menu_id);

            $menu = CompleteMenu::getCompleteMenu($menu_id, 'N', $merchant_id);
            $resource->set('menu', $menu);
            return $resource;
        } else {
            myerror_log("MerchantController: no merchant id found");
            $resource = new Resource();
            $resource->set('error', 'no merchant id found');
        }
    }

    /**
     *
     * @desc Deprecated use CompleteMerchant Object
     * @param int $merchant_id
     */
    function getMerchantDeliveryInfo($merchant_id)
    {
        // now check for delivery
        $mdia = new MerchantDeliveryInfoAdapter($this->mimetypes);
        $mdia_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
        if ($infos = $mdia->select('', $mdia_options)) {
            $merchant_delivery_info = array_pop($infos);
            $sql = "SELECT max(price) as price FROM Merchant_Delivery_Price_Distance WHERE merchant_id = $merchant_id AND logical_delete = 'N'";
            $mdia_static_options[TONIC_FIND_BY_SQL] = $sql;
            if ($datas = $mdia->select('', $mdia_static_options)) {
                $data = array_pop($datas);
                $merchant_delivery_info['delivery_cost'] = $data['price'];
            }
            return $merchant_delivery_info;
        } else
            return false;
    }

    /**
     *
     * @desc Deprecated use CompleteMerchant Object
     * @param int $merchant_id
     */
    function getMerchantAdvancedOrderingInfo($merchant_id)
    {
        // now check for delivery
        $maoia = new MerchantAdvancedOrderingInfoAdapter($this->mimetypes);
        $maoia_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
        if ($infos = $maoia->select('', $maoia_options)) {
            $merchant_advanced_ordering_info = array_pop($infos);
            return $merchant_advanced_ordering_info;
        } else
            return false;
    }

    function doesMerchantParticipateInCatering($merchant_id)
    {
        if ($record = getStaticRecord(array("merchant_id" => $merchant_id), 'MerchantCateringInfosAdapter')) {
            return true;
        }
    }

    /**
     *
     * @desc will return an array of the tax rates as a decimal value (already divided by 100)
     *
     * @param int $merchant_id
     */
    static function getTotalTaxRatesStatic($merchant_id)
    {
        $tax_adapter = new TaxAdapter(getM());
        $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
        $taxs = $tax_adapter->select('', $options);
        $tax_rates = array();
        foreach ($taxs as $tax)
            $tax_rates[$tax['tax_group']] = $tax_rates[$tax['tax_group']] + $tax['rate'];
        foreach ($tax_rates AS $group => &$tax_rate)
            $tax_rate = $tax_rate / 100;
        return $tax_rates;
    }

    function getTotalTaxRates($merchant_id)
    {
        $tax_adapter = new TaxAdapter($this->mimetypes);
        return $tax_adapter->getTotalTaxRates($merchant_id);
    }

    function getTotalTax($merchant_id)
    {
        $tax_adapter = new TaxAdapter($this->mimetypes);
        return $tax_adapter->getTotalTax($merchant_id);
    }

    function getNutritionFlag($brand)
    {
        return $brand['nutrition_flag'];
    }

    function getNutritionMessage(){
        $nutrition_message['all'] = getProperty('nutrition_language_all');
        $nutrition_message['kids'] = getProperty('nutrition_language_kids');
        return $nutrition_message;
    }

    function stubOutMerchant(&$merchant_resource, $new_payment = false)
    {
        $merchant_id = $merchant_resource->merchant_id;
        $shop_email = $merchant_resource->shop_email;

        // first create HOUR RECORDS
        $hour_adapter = new HourAdapter(getM());
        $hour_data['merchant_id'] = $merchant_id;
        $hour_data['hour_type'] = 'R';
        if ($merchant_resource->long_hours) {
            $hour_data['open'] = '01:00:00';
            $hour_data['close'] = '23:00:00';
        } else {
            $hour_data['open'] = '07:00:00';
            $hour_data['close'] = '20:00:00';
        }


        for ($i = 1; $i < 8; $i++) {
            $hour_data['day_of_week'] = $i;
            $hour_resource = Resource::factory($hour_adapter, $hour_data);
            $hour_resource->save();
        }
        $hour_data['hour_type'] = 'D';
        for ($i = 1; $i < 8; $i++) {
            $hour_data['day_of_week'] = $i;
            $hour_resource = Resource::factory($hour_adapter, $hour_data);
            $hour_resource->save();
        }

        $this->stubOutMerchantTax($merchant_id);
        $this->stubOutMerchantSkinMap($merchant_id);
        if ($new_payment) {
            $merchant_id_key = generateCode(10);
            $merchant_id_number = generateCode(5);
            $data['vio_selected_server'] = 'sage';
            $data['vio_merchant_id'] = $merchant_id;
            $data['name'] = "Test Billing Entity";
            $data['description'] = 'An entity to test with';
            $data['merchant_id_key'] = $merchant_id_key;
            $data['merchant_id_number'] = $merchant_id_number;
            $data['identifier'] = $merchant_resource->alphanumeric_id;
            $data['brand_id'] = $merchant_resource->brand_id;

            $card_gateway_controller = new CardGatewayController(getM(), null, null);
            $payment_gateway_resource = $card_gateway_controller->createPaymentGateway($data);
            $created_merchant_payment_type_map_id = $payment_gateway_resource->merchant_payment_type_map->id;
            $merchant_resource->set("merchant_payment_type_map_id", $created_merchant_payment_type_map_id);

        } else {
            $this->stubOutMerchantPaymentType($merchant_id);
        }
        $this->stubOutMerchantDelivery($merchant_id);
        $this->stubOutMerchantHoliday($merchant_id);
        $this->stubOutMerchantZZUser($merchant_id, $merchant_resource->merchant_external_id, $merchant_resource->name);
        $this->stubOutMerchantAdmEmail($merchant_id, $shop_email);
        $this->stubOutMerchantACH($merchant_id);
    }

    function sendLetterFromTemplate($merchant_id, $template_file)
    {
        if ($template_file == 'welcome_letter')
            $this->sendWelcomeLetter($merchant_id);
        else {
            $merchant_resource = Resource::find($this->adapter, '' . $merchant_id);

            //create the custom fields
            $merchant_city_st_zip = $merchant_resource->city . ', ' . $merchant_resource->state . ' ' . $merchant_resource->zip;
            $merchant_addr = $merchant_resource->address1 . ' ' . $merchant_resource->address2;
            $merchant_resource->set('merchant_city_st_zip', $merchant_city_st_zip);
            $merchant_resource->set('merchant_addr', $merchant_addr);

// CHANGE THIS
            //hard coded get password bullshit
            $sql = "SELECT sus_login_name, sus_login_password FROM zzsys_user WHERE merchant_id = $merchant_id AND sus_zzsys_user_group_id = '14e2240245a46f'";
            $options[TONIC_FIND_BY_SQL] = $sql;
            $results = $this->adapter->select(null, $options);
            $record = array_pop($results);
            $merchant_resource->set('sus_login_name', $record['sus_login_name']);
            $merchant_resource->set('sus_login_password', $record['sus_login_password']);
            $data = $merchant_resource->getDataFieldsReally();
            $merchant_resource->set('data', $data);

            if (isTest() || isLaptop())
                Resource::encodeResourceIntoTonicFormat($merchant_resource);

            $merchant_resource->_representation = $template_file;
            $representation =& $merchant_resource->loadRepresentation($this->file_adapter);
            $letter_html = $representation->_getContent();

            $emails['shop_email'] = $merchant_resource->shop_email;

            // now get admin emails from adm_merchant_email table
            $adm_email_data['merchant_id'] = $merchant_id;
            $adm_email_data['admin'] = 'Y';
            $adm_options[TONIC_FIND_BY_METADATA] = $adm_email_data;
            $adm_email_resources = Resource::findAll(new AdmMerchantEmailAdapter(getM()), null, $adm_options);

            foreach ($adm_email_resources as $adm_email_resource)
                $emails[$adm_email_resource->email] = $adm_email_resource->email;

            $mmh_adapter = new MerchantMessageHistoryAdapter(getM());
            foreach ($emails as $email) {
                $message_data = array();
                $message_data['merchant_id'] = $merchant_id;
                $message_data['message_format'] = 'EL';
                $message_data['message_delivery_addr'] = $email;
                $message_data['next_message_dt_tm'] = time();
                $message_data['message_type'] = 'I';
                $message_data['info'] = "subject=A Message From Splickit";
                $message_data['message_text'] = $letter_html;
                $message_resource = Resource::factory($mmh_adapter, $message_data);
                if ($message_resource->save())
                    myerror_logging(3, "MerchantController: the message has been staged");
                else
                    myerror_log("MerchantController: ERROR! couldn't save letter to merchant message history table: " . $message_resource->getAdapterError());
            }
        }
    }

    function sendWelcomeLetter($merchant_id)
    {
        $sql = "SELECT a.merchant_id, c.sus_login_name, c.sus_login_password, a.address1, a.city, a.state, a.zip, a.merchant_external_id, d.icon_image as image, 'dummy' as merchant_status, a.brand_id FROM Merchant a  LEFT OUTER JOIN Skin b ON a.brand_id = b.brand_id JOIN zzsys_user c ON a.merchant_id = c.merchant_id LEFT OUTER JOIN Skin_Images_Map d ON b.skin_id = d.skin_id  WHERE a.merchant_id = $merchant_id AND c.sus_zzsys_user_group_id = '14e2240245a46f'";
        myerror_logging(3, "MerchantController: merchant welcome sql: " . $sql);
        $options[TONIC_FIND_BY_SQL] = $sql;
        if ($merchant_letter_data_resource = Resource::find($this->adapter, '', $options))
            ; // all is good
        else
            die ("couldn't get merchant info. " . $merchant_letter_data_resource->getAdapterError());

        $sql = "select merchant_id, email from adm_merchant_email where merchant_id = $merchant_id AND admin = 'Y' UNION select merchant_id, shop_email as email from Merchant where merchant_id = $merchant_id";
        $options[TONIC_FIND_BY_SQL] = $sql;
        if ($merchant_email_records = $this->adapter->select('', $options))
            $mmh_adapter = new MerchantMessageHistoryAdapter($this->mimetypes); // all is good
        else
            die("couldn't get merchant email records");

        if ($merchant_letter_data_resource->brand_id == 112) {
            $skin = "moes";
        } else if ($merchant_letter_data_resource->brand_id == 282) {
            $skin = "pitapit";
        } else {
            $skin = "default";
        }
        $this->email_service = new EmailService();
        $letter_html = $this->email_service->getMerchantWelcomeTemplate($skin);

        foreach ($merchant_email_records as $merchant_email_record) {
            if ($mmh_adapter->createMessage($merchant_id, 0, 'EW', $merchant_email_record['email'], time(), 'I', "subject=Welcome to splick-it mobile ordering", $letter_html))
                continue;
            else
                myerror_log("MerchantController: ERROR! couldn't save welcome letter to merchant message history table");
        }
    }

    function stubOutMerchantCateringDelivery($merchant_id)
    {
        return $this->stubOutMerchantDelivery($merchant_id,true);
    }

    function stubOutMerchantDelivery($merchant_id,$catering = false)
    {
        // dummy delivery records
        $mdi_adapter = new MerchantDeliveryInfoAdapter(getM());

        $mdi_data['merchant_id'] = $merchant_id;
        if ($catering) {
            $mdi_data['delivery_type'] = 'Catering';
        }
        $mdi_data['active'] = 'Y';
        $mdi_data['created'] = time();
        $mdi_resource = Resource::factory($mdi_adapter, $mdi_data);
        if ($mdi_resource->save())
            ;//all is good
        else
            die("could not create merchant delivery info record");

        // dummy delivery records
        $mdpd_adapter = new MerchantDeliveryPriceDistanceAdapter(getM());
        $mdpd_data['merchant_id'] = $merchant_id;
        $mdpd_data['distance_up_to'] = 1.0;
        if ($catering) {
            $mdpd_data['delivery_type'] = 'Catering';
        }
        $mdpd_data['price'] = 0.00;
        $mdpd_resource = Resource::factory($mdpd_adapter, $mdpd_data);
        if ($mdpd_resource->save())
            ;//all is good
        else
            die("could not create merchant delivery price distance record");
    }

    function stubOutMerchantHoliday($merchant_id)
    {
        $sql = "INSERT INTO Holiday (merchant_id) VALUES ($merchant_id)";
        $this->adapter->_query($sql);
    }

    function stubOutMerchantACH($merchant_id)
    {
        $ts = time();
        $sql = "INSERT INTO adm_ach (merchant_id,created) VALUES ($merchant_id,$ts)";
        $this->adapter->_query($sql);
    }

    function stubOutMerchantZZUser($merchant_id, $merchant_external_id, $merchant_name)
    {
        if ($merchant_external_id == NULL || trim($merchant_external_id) == '')
            $merchant_external_id = $merchant_id;
        $ts = time();

        $sql = "INSERT INTO zzsys_user SELECT CONCAT(DATE_FORMAT(NOW(),'%i%s'),$merchant_id), '14e2240245a46f', CONCAT('OP-',$merchant_id), CONCAT('OP-',$merchant_id), 'hello',$ts, $ts, NULL, $merchant_id FROM DUAL";
        $this->adapter->_query($sql);
    }

    function stubOutMerchantAdmEmail($merchant_id, $email_address)
    {
        $ts = time();
        $sql = "INSERT INTO adm_merchant_email (merchant_id,email,daily,weekly,admin,created) VALUES ($merchant_id,'$email_address', 'Y','Y','Y',$ts)";
        $this->adapter->_query($sql);
    }

    function stubOutMerchantTax($merchant_id)
    {
        $tax_adapter = new TaxAdapter($this->mimetypes);
        $tax_data['merchant_id'] = $merchant_id;
        $tax_data['tax_group'] = 1;
        $tax_data['locale'] = 'Dummy';
        $tax_data['locale_description'] = 'Dummy';
        $tax_data['rate'] = '0.00';
        $tax_resource = Resource::factory($tax_adapter, $tax_data);
        if ($tax_resource->save())
            return $tax_resource;
        else
            die("couldn't create dummy tax records. " . $tax_adapter->getLastErrorText());
    }

    function stubOutMerchantSkinMap($merchant_id)
    {
        $smm_adapter = new SkinMerchantMapAdapter(getM());
        $smm_data['skin_id'] = 1;
        $smm_data['merchant_id'] = $merchant_id;
        $smm_resource = Resource::factory($smm_adapter, $smm_data);
        if ($smm_resource->save())
            return $smm_resource;
        else
            die("couldn't create merchant skin map. " . $smm_adapter->getLastErrorText());
    }

    function stubOutMerchantMessageMap($merchant_id, $email, $fax_no = '88888')
    {
        $mmm_adapter = new MerchantMessageMapAdapter($mimetypes);
        $mmm_data['merchant_id'] = $merchant_id;
        if (strlen($fax_no) < 10) {
            myerror_log("MerchantController: we do not have a valid fax number so setting primary delivery to email");
            $mmm_data['message_format'] = 'E';
            $mmm_data['delivery_addr'] = $email;
        } else {
            $mmm_data['message_format'] = 'FUA';
            $mmm_data['delivery_addr'] = $fax_no;
        }
        $mmm_data['message_type'] = 'X';
        $mmm_resource = Resource::factory($mmm_adapter, $mmm_data);
        if ($mmm_resource->save())
            return $mmm_resource;
        else
            die("could not create merchant message map");
    }

    function stubOutMerchantPaymentType($merchant_id, $payment_type = 'SageCreditCard')
    {
        $payment_resource = MerchantPaymentTypeAdapter::createMerchantPaymentTypeRecord($merchant_id, $payment_type);
        if (isset($payment_resource->error)) {
            die("could not create merchant payment type map");
        }
    }

    function setMerchantMenuMap($merchant_id, $menu_id, $menu_type = 'pickup')
    {
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_id, $menu_id, $menu_type);
    }

    function setMerchantId($merchant_id)
    {
        $this->merchant_id = $merchant_id;
    }

    function createNewTestMerchant($menu_id = 0)
    {
        if (isProd()) {
            die("cant do this in prod");
        }
        $merchant_adapter = new MerchantAdapter(getM());

        $merchant_user_id = 0;
        $merchant_data['merchant_user_id'] = $merchant_user_id;
        $merchant_data['merchant_external_id'] = null;
        $merchant_data['shop_email'] = 'dummy@dummy.com';
        $merchant_data['brand_id'] = 300;
        $merchant_data['name'] = "Unit Test Merchant";
        $merchant_data['display_name'] = "Display Name";
        $merchant_data['address1'] = '1505 Arapaho Ave';
        $merchant_data['address2'] = null;
        $merchant_data['city'] = 'boulder';
        $merchant_data['state'] = 'CO';
        $merchant_data['zip'] = '80302';
        $merchant_data['country'] = 'US';
        $merchant_data['cc_processor'] = 'I';
        $merchant_data['lat'] = 40.014726;
        $merchant_data['lng'] = -105.274479;
        $merchant_data['phone_no'] = '1234567890';
        $merchant_data['fax_no'] = '1234567890';
        $merchant_data['time_zone'] = -7;
        //$merchant_data['trans_fee_type'] = 'F';
        //$merchant_data['trans_fee_rate'] = 0.25;
        $merchant_data['show_tip'] = 'Y';
        $merchant_data['active'] = 'Y';
        $merchant_data['ordering_on'] = 'Y';
        $merchant_data['lead_time'] = 20;
        $merchant_data['delivery'] = 'Y';
        logData($merchant_data, "dummy merchant data");
        $merchant_resource = $merchant_adapter->createMerchant($merchant_data);

        $merchant_controller = new MerchantController($mt, $u, $r);
        $merchant_controller->stubOutMerchant($merchant_resource);
        $merchant_controller->stubOutMerchantMessageMap($merchant_resource->merchant_id, ''.$merchant_resource->merchant_id.'dummy@dummy.com');

        // give it a tax rate
        $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_resource->merchant_id;
        $tax_resource = Resource::find(new TaxAdapter(getM()), null, $options);
        $tax_resource->rate = 10;
        $tax_resource->save();


        if ($menu_id > 0)
        {
            MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $menu_id, 'pickup');
            MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $menu_id, 'delivery');
        }
        return $merchant_resource;
    }

}

class MerchantException extends Exception
{
    public function __construct($message)
    {
        parent::__construct($message);
    }
}