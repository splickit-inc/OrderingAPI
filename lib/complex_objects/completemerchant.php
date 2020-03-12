<?php

class CompleteMerchant
{
    var $merchant_adapter;
    var $current_merchant_resource;
    var $menu_type = 'pickup';
    var $error;
    protected $forced_time_stamp;
    var $time;
    var $open_close_ts_for_next_7_days_including_today;
    var $merchant_payment_types = array();
    private $merchant_id;
    var $api_version = 1;
    var $last_order_displayed = 0;
    var $catering_info;

    function CompleteMerchant($merchant_id)
    {
        $merchant_adapter = new MerchantAdapter(getM());
        $tz = date_default_timezone_get();

        date_default_timezone_set(getProperty("default_server_timezone"));
        if ($resource = $merchant_adapter->getExactResourceFromData(array("merchant_id" => $merchant_id))) {
            $this->time = time();
            //remove unwanted fields
            unset($resource->EIN_SS);
            unset($resource->order_del_type);
            unset($resource->order_del_addr);
            unset($resource->order_del_addr2);

            $this->current_merchant_resource = $resource;
            $this->merchant_id = $merchant_id;

            if ($resource->brand_id == getBrandIdFromCurrentContext()) {
                $brand = getBrandForCurrentProcess();
            } else {
                $brand = BrandAdapter::staticGetRecordByPrimaryKey($resource->brand_id, 'BrandAdapter');
            }

            if ($brand['last_orders_displayed']) {
                $this->last_order_displayed = intval($brand['last_orders_displayed']);
                myerror_logging(3, "Set last_orders_displayed for brand: " . $brand['brand_id'] . " --> " . $this->last_order_displayed);
            } else {
                myerror_logging(3, "Not set last_orders_displayed for brand: " . $brand['brand_id']);
            }

        } else {
            $this->error = "No matching merchant id located";
        }
        date_default_timezone_set($tz);
    }

    function setTimeForTesting($time_stamp)
    {
        $this->time = $time_stamp;
    }

    /**
     * @param $merchant_id
     * @param $menu_type
     * @param int $api_version
     * @return Resource
     */
    static function staticGetCompleteMerchant($merchant_id, $menu_type, $api_version = 1)
    {
        $cm = new CompleteMerchant($merchant_id);
        $cm->api_version = $api_version;
        return $cm->getCompleteMerchant($menu_type);
    }

    function getCompleteMerchant($menu_type_string)
    {
        $mmt = explode('-', $menu_type_string);
        $menu_type = $mmt[0];
        $catering = (isset($mmt[1]) && strtolower($mmt[1]) == 'catering') ? true : false;

        myerror_logging(3, "Starting the new getCompleteMerchant with caching!  menu_type_string: $menu_type_string");
        if ($this->error) {
            return returnErrorResource($this->error, 999);
        }

        // will either get it from the file cache or load it and store it.
        $merchant_resource = $this->getTheMerchantData($menu_type);
        myerror_log("we have the merchant_resource");
        $merchant_id = $merchant_resource->merchant_id;

        // now do all the tests.  ugh......



        //************* app bomb **************
        if (strtolower($_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE']) == 'android')
            $minimum_menu_version = $merchant_resource->minimum_android_version;
        else if (strtolower($_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE']) == 'iphone')
            $minimum_menu_version = $merchant_resource->minimum_iphone_version;
        else
            $minimum_menu_version = 2.0;
        // get ordering status of this merchant.
        myerror_log("we have the minimum version for the menu: $minimum_menu_version", 5);

        $brand = getBrandForCurrentProcess();
        $ordering_on = true;
        if ((isset($merchant_resource->ordering_on) && $merchant_resource->ordering_on == 'N' && $merchant_resource->active == 'Y') ||  $brand['active'] == 'N'){
            $ordering_on = false;
        }

        if ($ordering_on && version_compare($_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'], $minimum_menu_version) < 0) {
            myerror_log("ERROR!  version out of date for merchant! minimum: $minimum_menu_version   submitted version: " . $_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION']);
            return returnErrorResource("We're sorry but your app version is out of date for this merchant. To place an order at this location, please upgrade.", 90, array("text_title" => "Version Out Of Date!", "text_for_button" => "Upgrade Now", "url" => $_SERVER['SKIN_URL'], "fatal" => false));
        }

        $time = $this->time;

        if (substr_count($_SERVER['AUTHENTICATED_USER']['email'], $merchant_resource->numeric_id) > 0) {
            $merchant_resource->lead_time = 3;
            $merchant_resource->set("accepts_cash", "Y");
        }

        if (strtolower($menu_type) == 'delivery' && $merchant_resource->delivery == 'N') {
            return returnErrorResource("We're sorry, but this location does not appear to be accepting delivery orders right now.", 540, array("http_code" => 500));
        }

        if ($merchant_resource->active == 'N' && $_SERVER['AUTHENTICATED_USER_ID'] > 19999) {
            return returnErrorResource("We're sorry, this merchant appears to be offline at the moment.", 540, array("http_code" => 500));
        }

        // get override if it exists
        if ($_SERVER['AUTHENTICATED_USER']['trans_fee_override'] != null)
            $merchant_resource->trans_fee_rate = $_SERVER['AUTHENTICATED_USER']['trans_fee_override'];

        $weeks_open_close_ts = $merchant_resource->the_weeks_hours;

        if ($ordering_on && sizeof($weeks_open_close_ts) < 7) {
            return returnErrorResource("We're sorry, but this location does not appear to be accepting " . $menu_type . " orders right now.", 540, array("http_code" => 500));
        }

        if ($merchant_resource->today_open == 'closed') {
            $message = "We're sorry, this merchant is closed for " . $menu_type . " orders today.";
        } else {
            $message = $this->isMerchantOpenAtThisTime($weeks_open_close_ts, $time);
        }

        if ($message != 'merchant is open') {
            $holiday = false;
            $holiday_array = $merchant_resource->holidays;
            $today = date('Y-m-d', $time);
            if ($holiday_array["$today"] == 'closed') {
                $holiday = true;
            }
        }

        if ($_SERVER['AUTHENTICATED_USER']['user_id'] < 100) {
            $hours = array("open" => "00:01", "close" => "23:59");
            $merchant_resource->set("todays_hours", $hours);
            $message = "merchant is open";
        }
        if ($message != 'merchant is open') {
            $merchant_resource->set('user_message_title', 'Alert!');
            $merchant_resource->set('user_message', $message);
        }

        $merchant_resource->set('max_lead', 90);

        // ***************** THE MENU! *****************
        if ($menu_id = $merchant_resource->menu_id) {
            $show_catering_items = true;
            if (!$catering && $this->hasCateringInfoRecord()) {
                myerror_log("we are setting the show catering items to false",5);
                $show_catering_items = false;
            }
            $cat_value = $catering ? 'true' : 'false';
            $show_cat = $show_catering_items ? 'true': 'false';
            myerror_log("about to get the menu for the merchant. catering is set to: $cat_value  -  and  show_catering_items is set to: $show_cat",5);

            $menu = CompleteMenu::getCompleteMenu($menu_id,'Y',$merchant_id,$this->api_version,$menu_type_string,$catering,$show_catering_items);
            $menu['api_version'] = $this->api_version;
            $merchant_resource->set('menu_id', $menu['menu_id']);
            $merchant_resource->set('menu_key', $menu['menu_key']);
            $merchant_resource->set('menu', $menu);
        }
        if (!isAggregate()) {
            $merchant_resource->name = $merchant_resource->city . ', ' . $merchant_resource->address1;
        }

        // check for open store if Merchant is Xoikos

        if ($merchant_resource->brand_id == 430 && $ordering_on) {
            // we have a xoikos merchant
            if ($merchant_resource->ordering_on == 'Y') {
                $xoikos_service = new XoikosService(["merchant"=>$merchant_resource->getDataFieldsReally()]);
                if ($xoikos_service->testStoreIsInactive()) {
                    myerror_log("Xoikos Store is NOT showing active on their network: store: ".$merchant_resource->merchant_id);
                    $ordering_on = false;
                }
            }
        }


        if (!$ordering_on) {
            $merchant_resource->set('user_message_title', 'Alert!');
            //$ordering_off_message = getProperty('ordering_off_message');
            $ordering_off_message = PlaceOrderController::ORDERING_OFFLINE_MESSAGE;
            $merchant_resource->set('user_message', $ordering_off_message);
            //return $merchant_resource;
        }

        //custom_menu_message is actualy a field on teh merchant.  should be called custom_menu_load_message
        if ($merchant_resource->custom_menu_message != NULL && trim($merchant_resource->custom_menu_message) != '') {
            $s = explode('#', $merchant_resource->custom_menu_message);
            if ($s[1]) {
                $merchant_resource->set('user_message_title', $s[1]);
            }
            if (isset($merchant_resource->user_message)) {
                $merchant_resource->user_message = $merchant_resource->user_message . chr(10) . chr(10) . $s[0];
            } else {
                $merchant_resource->set('user_message', $s[0]);
            }
        }

        if (! $catering) {
            $this->setDeliveryMessage($merchant_resource);
        }

        // ***************** check for any gifts that the user has *****************
        $authenticated_user = $_SERVER['AUTHENTICATED_USER'];
        if ($gift_resource = $authenticated_user['gift_resource']) {
            $user_message = "You have gift of up to $" . $gift_resource->amt . " you can use on your next purchase.  Enter 'usegift' in the promocode at checkout";
            $this->setUserMessageAndTitleOnMerchantResource($merchant_resource, $user_message, 'Gift Info');
        } else if ($authenticated_user['balance'] > 0.00 && $authenticated_user['user_id'] > 1000) {
            $user_message = 'You have a credit of $' . $authenticated_user['balance'] . '. Credit will be applied on billing of your credit card after purchase. Please check email reciept for verification.';
            $this->setUserMessageAndTitleOnMerchantResource($merchant_resource, $user_message, 'Account Info');
        }

        //cant use the $ordering_on boolean since that is also dependent on the active='Y' flag
        if ($merchant_resource->ordering_on == 'Y') {
            //***  check for ability to order ***/
            if (!MerchantMessageMapAdapter::doesMerchantHaveMessagesSetUp($merchant_id)) {
                $merchant_resource->set('user_message_title', 'Merchant Error');
                if (isLoggedInUserStoreTesterLevelOrBetter()) {
                    $merchant_resource->set('user_message', 'Admin user message: please set up merchant message map, there are no messages for order delivery.');
                } else {
                    $merchant_resource->set('user_message', 'Sorry, there is a problem with this merchants set up and they cannot receive orders at this time. Support has been alerted, we apologize for the inconvenience.');
                    $map_id = MailIt::sendErrorEmailSupport("We have a merchant with no Message Map Set Up", "merchant_id: $merchant_id");
                    $merchant_resource->set('error_message_map_id', $map_id);
                }
            }
        }
        $merchant_brand = BrandAdapter::staticGetRecordByPrimaryKey($merchant_resource->brand_id,'BrandAdapter');
        $merchant_resource->set('brand_name',$merchant_brand['brand_name']);

        myerror_logging(3, "caching_log: we have completed the get merchant call");
        return $merchant_resource;

    }

    function setUserMessageAndTitleOnMerchantResource(&$merchant_resource, $user_message, $user_message_title)
    {
        if (isset($merchant_resource->user_message)) {
            $merchant_resource->user_message = $merchant_resource->user_message . chr(10) . chr(10) . $user_message;
        } else {
            $merchant_resource->set('user_message_title', $user_message_title);
            $merchant_resource->set('user_message', $user_message);
        }
    }

    function setDeliveryMessage(&$merchant_resource)
    {
        if (isset($merchant_resource->delivery_info) && $merchant_resource->delivery_info['minimum_order'] > 0.00) {
            if (MerchantDeliveryPriceDistanceAdapter::areThereMinimumsSetOnDeliveryDistancePriceRecords($merchant_resource->merchant_id)) {
                myerror_log("We have multiple delivery minimums so do not display minimum message to user");
            } else {
                if ($merchant_resource->delivery_info['minimum_order'] > 3.00) {
                    $delivery_message = "Please note: This merchant has a minimum delivery order of $" . $merchant_resource->delivery_info['minimum_order'] . ".";
                    $merchant_resource->user_message = ($merchant_resource->user_message != null) ? $merchant_resource->user_message . chr(10) . chr(10) . $delivery_message : $delivery_message;
                }
            }
        }
    }

    function isMerchantOpenAtThisTime($weeks_open_close_ts, $time)
    {
        myerror_logging(3, "passed in time for is merchant_open is: " . $time);
        myerror_logging(3, "current time zone checking is: " . date_default_timezone_get());
        $message = '';

        $tz = date_default_timezone_get();
        $merchant_time_zone = $this->current_merchant_resource->time_zone;
        setTheDefaultTimeZone($merchant_time_zone, $this->current_merchant_resource->state);

        $first_loop = true;
        foreach ($weeks_open_close_ts as $open_close) {
            if ($message != '')
                continue;
            myerror_logging(5, "checking hours open: " . $open_close['open']);
            myerror_logging(5, "checking hours closed: " . $open_close['close']);
            if ($time < $open_close['open']) {
                $message = "Merchant is currently closed and will open at " . date('g:i a', $open_close['open']);
                if ($this->menu_type == 'delivery') {
                    if ($first_loop) {
                        $message = "Please note: This merchant's delivery hours start at " . date('g:i a', $open_close['open']);
                    } else {
                        $message = "Please note: This merchant's delivery hours have ended for today. Next available delivery is " . date('D g:i a', $open_close['open']);
                    }
                }
            }
            if ($time > $open_close['open'] && $time < $open_close['close']) {
                myerror_logging(3, "merchant is open");
                $minutes_till_close = ($open_close['close'] - $time) / 60;
                myerror_logging(3, 'minutes till close is: ' . $minutes_till_close);
                if ($minutes_till_close < 60 && $first_loop) {
                    $message = "Please note that this merchant will close for pickup at " . date('g:i a', $open_close['close']) . " today.";
                    if ($this->menu_type == 'delivery') {
                        $message = "Please note: This merchant's delivery hours end today at " . date('g:i a', $open_close['close']);
                    }
                } else {
                    $message = "merchant is open";
                }
            }
            $first_loop = false;
        }
        date_default_timezone_set($tz);
        return $message;
    }

    function getTheMerchantData($menu_type)
    {
        $merchant_id = $this->current_merchant_resource->merchant_id;
        myerror_logging(3, "caching_log: starting getTheMerchantData for merchant_id: " . $merchant_id);
        $current_merchant_ts = $this->current_merchant_resource->modified;
        $merchant_caching_string = "merchant-" . $merchant_id . "-" . $menu_type;

        // try to get from Cache first.
        //PhpFastCache::$storage = "files";
        if (getProperty("use_merchant_caching") == "false") {
            myerror_logging(3, "caching_log: use_merchant_caching set to false. Do Not Check Cache");
        } else if (isLoggedInUserStoreTesterLevelOrBetter()) {
            myerror_logging(3, "caching_log: user is store tester or better. Do Not Check Cache");
        } else if (getProperty('DO_NOT_CHECK_CACHE') == 'true') {
            myerror_logging(3, "caching_log: DONOT Check Cache is set to false, so skip");
        } else if ($merchant_resource = SplickitCache::getCacheFromKey($merchant_caching_string)) {
            // merchant cache should expire at the end of each day.
            myerror_logging(3, "caching_log:  we have a cached merchant " . $merchant_caching_string);
            $cached_time_stamp = $merchant_resource->modified;

            myerror_logging(3, "cached merchant modified time stamp is " . date('Y-m-d H:i:s', $cached_time_stamp));
            myerror_logging(3, "current merchant modified time stamp is " . date('Y-m-d H:i:s', $current_merchant_ts));

            if ($current_merchant_ts == $cached_time_stamp) {
                myerror_logging(3, "caching_log: use the cached merchant becuase the modified value is the same");
                $merchant_resource->set('using_cached_merchant', true);
                if ($merchant_resource->has_catering) {
                    $this->catering_info = $merchant_resource->catering_info;
                }
                return $merchant_resource;// all is good so return the resource so we can get the menu.
            }
            myerror_logging(3, "caching_log: do NOT use the cached merchant, RELOAD");
//      PhpFastCache::$storage = "files";
//      PhpFastCache::delete($merchant_caching_string);
            SplickitCache::deleteCacheFromKey($merchant_caching_string);
        } else {
            myerror_logging(3, "caching_log: there is NO valid cached merchant so create it. " . $merchant_caching_string);
        }


        $resource = $this->current_merchant_resource;
        $resource->set('using_cached_merchant', false);
        $merchant_id = $resource->merchant_id;

        //********** get merchant menu id ***********
        $merchant_menu_map_adapter = new MerchantMenuMapAdapter($this->mimetypes);
        if ($menu_map = $merchant_menu_map_adapter->getRecord(array("merchant_id" => $merchant_id, "merchant_menu_type" => $menu_type))) {
            $resource->menu_id = $menu_map['menu_id'];
        } else {
            MailIt::sendErrorEmailSupport("SERIOUS MERCHANT ERROR!", 'SPLICKIT ERROR! No ' . $menu_type . ' menu mapping for merchant_id: ' . $merchant_id);
            myerror_log("Merchant $merchant_id does not have a $menu_type map record!");
            return createErrorResourceWithHttpCode("We're sorry, but this location does not appear to be accepting $menu_type orders right now.", 422, 422, null);
        }

        //******* get merchant payment types *******
        $mpt_adapter = new MerchantPaymentTypeAdapter($this->mimetypes);
        $mpt_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
        $payment_array = array();
        if ($mpt_resources = Resource::findALL($mpt_adapter, '', $mpt_options)) {
            foreach ($mpt_resources as $mpt_resource) {
                $payment_array[] = strtolower($mpt_resource->payment_type);
                // for legacy code
                if ($mpt_resource->payment_type == 'cash') {
                    myerror_logging(3, "We have a merchant that accepts cash");
                    $resource->set("accepts_cash", "Y");
                }
            }
        } else {
            // this is just for backward copatibilty with WEB1 which needs to know that there are ways to pay. any web1 merchant will have a CC entry so we can
            // just force it here.  this entire block will go away once we complete the move to WEB2
            if ($records = MerchantPaymentTypeMapsAdapter::getMerchantPaymentTypes($merchant_id)) {
                $payment_array[] = 'creditcard';
            } else {
                MailIt::sendErrorEmailSupport("No Payment Type Record For Merchant", "no payment type record for merchant_id: " . $merchant_id);
                throw new Exception("Merchant Setup Error. No accepted payment types listed");
            }
        }
        $resource->set("payment_types", $payment_array);

        //now add hack for backwards compatabity for merchants set up with new payment framework but that take cash
        if (MerchantPaymentTypeMapsAdapter::validateCashForMerchantId($merchant_id)) {
            $resource->set("accepts_cash", "Y");
        }


        // *************  get merchant Tax stuff *************
        $tax_adapter = new TaxAdapter($this->mimetypes);
        $tax_rate = $tax_adapter->getTotalTax($merchant_id);
        $resource->set('tax_rate', $tax_rate);

        // tax rates will start with index[1];
        $tax_rates = $tax_adapter->getTotalTaxRates($merchant_id);

        if (sizeof($tax_rates) < 1) {
            MailIt::sendErrorEmail("merchant tax setup error!", "merchant_id : " . $merchant_id);
            return returnErrorResource("We're sorry, this merchant has not completed their tax setup yet and cannot accept orders right now", 999);
        }
        // need to add the default no tax rate
        $tax_rates[0] = 0.00;
        $resource->set('tax_rates_by_group', $tax_rates);

        // **********  get hours stuff ***************

        $time_zone = $resource->time_zone;
        $time_zone_string = getTheTimeZoneStringFromOffset($time_zone, $resource->state);
        $resource->set("time_zone_string", $time_zone_string);
        $resource->set("time_zone_offset", getCurrentOffsetForTimeZone($time_zone_string));
        setDefaultTimeZoneFromString($time_zone_string);

        $hour_adapter = new HourAdapter(getM());

        $hour_type = 'R';
        if (strtolower($menu_type) == 'delivery') {
            $hour_type = 'D';
            $this->menu_type = 'delivery';

            if ($delivery_info = $this->getMerchantDeliveryInfo($merchant_id)) {
                $resource->set('delivery_info', $delivery_info);
            } else {
                recordError("Merchant Delivery NOt Set UP", "could not get merchant_delivery_info record for merchant_id: $merchant_id");
                MailIt::sendErrorEmailSupport("Merchant Delivery Set Up Not Complete!", "could not get merchant_delivery_info record for merchant_id: $merchant_id");
                return createErrorResourceWithHttpCode("We're sorry, but this location does not appear to be accepting delivery orders right now.", 500, 500, null);
            }
        }

        $time = $this->time;

        $open_close_for_next_7_days = $hour_adapter->getNextOpenAndCloseTimeStamps($merchant_id, $hour_type, 7, $time);
        myerror_log("we have the hours");

        $this->open_close_ts_for_next_7_days_including_today = $open_close_for_next_7_days;

        $resource->set('the_weeks_hours', $open_close_for_next_7_days);
        if ($todays_hours = $hour_adapter->getTodaysHours($merchant_id, $hour_type, -100, $time)) {
            if ($todays_hours['day_open'] == 'N') {
                $resource->set("today_open", "closed");
            } else {
                $resource->set("today_open", "open");
            }
            $hours = array("open" => $todays_hours['open'], "close" => $todays_hours['close']);
            if ($todays_hours['second_close'] != NULL)
                $hours['second_close'] = $todays_hours['second_close'];
            $resource->set("todays_hours", $hours);
        }

        // check catering
        if ($record = MerchantCateringInfosAdapter::staticGetRecord(array("merchant_id" => $merchant_id), 'MerchantCateringInfosAdapter')) {
            $resource->set("has_catering", true);
            $resource->set("catering_info", $record);
            $this->catering_info = $record;
        } else {
            $resource->set("has_catering", false);
            $resource->set("catering_info", null);
        }

        // get primary message delivery type
        $mmma = new MerchantMessageMapAdapter(getM());
        $mmm_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
        $mmm_options[TONIC_FIND_BY_METADATA]['message_type'] = 'X';  // x means its the execution message
        if ($mmmr = Resource::find($mmma,null,$mmm_options)) {
            $resource->set("primary_message_delivery_format",$mmmr->message_format);
        } else {
            $resource->set("primary_message_delivery_format",'');
        }

        // now get ts of the next midnight locally at merchant so we know how long to cache the data for
        $ts = mktime(23, 59, 58, date('m', $time), date('d', $time), date('Y', $time));
        myerror_log("cached until:  " . date('Y-m-d H:i:s e', $ts));
        $expires_in_seconds = $ts - $time;
        $resource->set("cached_till", $ts);
        $resource->set("cached_from", $time);
        $minutes = $expires_in_seconds / 60;
        myerror_logging(3, "caching_log: we will cache this merchants info till " . date('Y-m-d H:i:s e', $ts) . ",  which is $minutes minutes from now");
        //PhpFastCache::$storage = "files";
        //PhpFastCache::set("$merchant_caching_string", $resource, $expires_in_seconds);
        $splickit_cache = new SplickitCache();
        $splickit_cache->setCache($merchant_caching_string,$resource,$expires_in_seconds);
        return $resource;
  }

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
                return $merchant_delivery_info;
            } else {
                myerror_log("ERROR! Merchant_Delivery_Price_Distance not set up for this merchant! merchant_id: $merchant_id");
            }

        } else {
            myerror_log("ERROR! Merchant_Delivery_Info not set up for this merchant! merchant_id: $merchant_id");
        }
        return false;
    }

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

    function loadLastOrdersValidForUserAndMenu($user_id, $menu)
    {
        $last_orders = array();
        myerror_log("display last orders: " . $this->last_order_displayed);
        if ($this->last_order_displayed > 0) {
            $active_item_size_list = array();
            $active_modifiers_list = array();
            foreach ($menu['menu_types'] as $menu_type) {
                foreach ($menu_type['menu_items'] as $item) {
                    foreach ($item['size_prices'] as $item_size) {
                        $active_item_size_list[$item_size['item_id'] . '-' . $item_size['size_id']] = true;
                    }
                    if (isset($item['modifier_groups'])) {
                        foreach ($item['modifier_groups'] as $modifier_group) {
                            foreach ($modifier_group['modifier_items'] as $modifier_item) {
                                $active_modifiers_list[$item['item_id'] . '-' . $modifier_item['modifier_item_id']] = true;
                            }
                        }
                    }
                }
            }

            $order_adapter = new OrderAdapter(getM());
            $options[TONIC_FIND_BY_METADATA]['user_id'] = $user_id;
            $options[TONIC_FIND_BY_METADATA]['status'] = 'E';
            $options[TONIC_SORT_BY_METADATA] = 'order_dt_tm DESC';
            // we'll only look through the last 10 orders
            $options[TONIC_FIND_TO] = 10;

            $orders = Resource::findAll($order_adapter, '', $options);
            myerror_logging(3, "Start adding last order");

            foreach ($orders as $order) {
                if (sizeof($last_orders) >= $this->last_order_displayed) {
                    continue;
                }
                try {
                    $complete_order = CompleteOrder::staticGetCompleteOrder($order->order_id, getM());
                    myerror_log("we have a last order: " . $complete_order['order_id'], 3);
                } catch (Exception $e) {
                    myerror_log("could NOT build this order: " . $order->order_id . ", so we will skip it in the last orders section of get merchant for this user");
                    continue;
                }

                $latest_order = array(
                    'note' => $complete_order['note']
                );

                foreach ($complete_order['order_details'] as $item) {
                    $order_item = array(
                        'quantity' => $item['quantity'],
                        'note' => $item['note'],
                        'size_id' => $item['size_id'],
                        'item_id' => $item['item_id'],
                        'sizeprice_id' => isset($item['sizeprice_id']) ? $item['sizeprice_id'] : $item['item_size_id']
                    );

                    foreach ($item['order_detail_complete_modifier_list_no_holds'] as $modifier) {

                        $order_item['mods'][] = array(
                            'modifier_item_id' => $modifier['modifier_item_id'],
                            'mod_item_id' => $modifier['modifier_item_id'],
                            'mod_sizeprice_id' => $modifier['modifier_size_id'],
                            'quantity' => $modifier['mod_quantity'],
                            'mod_quantity' => $modifier['mod_quantity']
                        );
                    }

                    $latest_order['items'][] = $order_item;
                }

                if ($this->ensureOrderAgainstActiveItemsSizesAndModifiers($latest_order, $active_item_size_list, $active_modifiers_list)) {
                    myerror_log("order is still good with active items modifiers", 3);
                    $last_orders[] = array(
                        'order_id' => $complete_order['order_id'],
                        'label' => 'Last Order placed on ' . date('m-d-Y', $complete_order['order_dt_tm']),
                        'order' => $latest_order
                    );
                } else {
                    myerror_log("order is no longer valid against current menu", 3);
                }
            }
        }

        myerror_logging(3, "Last orders added" . json_encode($last_orders));
        logData($last_orders, "LAST ORDERS", 5);
        return $last_orders;
    }

    function hasCateringInfoRecord()
    {
        return $this->catering_info != null;
    }

    function ensureOrderAgainstActiveItemsSizesAndModifiers($order, $active_item_size_list, $active_modifiers_list)
    {
        if (count($order['items']) < 1) {
            return false;
        }
        foreach ($order['items'] as $item) {
            if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($item, 'item_id') && validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($item, 'size_id')) {
                $item_size_string = $item['item_id'] . '-' . $item['size_id'];
                myerror_logging(3, "checking order $item_size_string against merchant menu");
                if ($active_item_size_list[$item_size_string] && $this->ensureOrderAgainstActiveModifiers($item['item_id'], $item['mods'], $active_modifiers_list)) {
                    continue;
                } else {
                    return false;
                }
            } else {
                return false;
            }

        }
        return true;
    }

    function ensureOrderAgainstActiveModifiers($item_id, $item_mods, $active_modifiers_list)
    {
        foreach ($item_mods as $mod) {
            $item_mod_string = $item_id . '-' . $mod['mod_item_id'];
            myerror_logging(3, "checking order modifier $item_mod_string against merchant menu");
            if ($active_modifiers_list[$item_mod_string]) {
                continue;
            } else {
                return false;
            }
        }
        return true;
    }

    static function checkForGPRSshutdownMessageForThisMerchantById($merchant_id)
    {
        if ((getProperty('gprs_total_merchant_shutdown') == 'true' || getProperty('gprs_tunnel_merchant_shutdown') == 'true')) {
            //determine if this is a gprs merchant
            $merchant_map_adapter = new MerchantMessageMapAdapter(getM());
            $mm_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
            $mm_options[TONIC_FIND_BY_METADATA]['message_format'] = array("LIKE" => "G%");
            $mm_options[TONIC_FIND_BY_METADATA]['message_type'] = 'X';
            $mm_options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
            if ($merchant_map_resource = Resource::findExact($merchant_map_adapter, '', $mm_options)) {
                if (getProperty('gprs_total_merchant_shutdown') == 'false') {
                    // means we have a tunnel shut down only, so lets determine if this is an old style GPRS printer
                    $info = $merchant_map_resource->info;
                    if (substr_count(strtolower($info), 'firmware') > 0) {
                        return;
                    }
                }
                return "We're sorry but due to a T-Mobile outage, we cannot deliver orders to this merchant at this time, please try again soon.";
            }
        }
    }
}
