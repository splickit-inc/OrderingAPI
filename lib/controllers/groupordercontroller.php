<?php

class GroupOrderController extends SplickitController
{

    var $submitted_merchant_id;
    var $error_resource;
    var $email_service;
    var $group_order_resource;
    var $current_time;
    var $submit_from_activity = false;

    var $group_order_cancelled_message = "Sorry! This group order has been cancelled by the admin.";
    var $group_order_expired_message = "Sorry! This group order has expired.";
    var $group_order_submitted_message = "Sorry! This group order has already been submitted.";
    var $group_order_not_active_message = "Sorry! This group order is no longer active.";
    
    const NO_ADDRESS_SUBMITTED_FOR_DELIVERY_GROUP_ORDER_MESSAGE = 'Sorry. It seems no address was submitted with your request for a delivery group order. Please try again.';
    const GUEST_CANNOT_BE_ADMIN_FOR_GROUP_ORDER_ERROR = "Sorry, guest users cannot create group orders. Please create an account if you wish to be the admin for a group order.";

    // we will currently default pickup time to be 45 minutes after submission of group order
    const DEFAULT_FULLFILLMENT_TIME_IN_MINUTES_FOR_AUTO_SEND = 45;

    const GROUP_ORDER_ADMINISTRATOR_PAY_TYPE = 1;
    const GROUP_ORDER_PARTICIPANT_PAY_TYPE = 2;


    function GroupOrderController($mt, $u, $r, $l = 3)
    {
        parent::SplickitController($mt, $u, $r, $l);
        $this->adapter = new GroupOrderAdapter($mt);
        $this->email_service = new EmailService();
    }

    function processV2request()
    {
        if (preg_match('%/grouporders/([0-9]{4}-[0-9a-z]{5}-[0-9a-z]{5}-[0-9a-z]{5})%', $this->request->url, $matches)) {
            $group_order_token = $matches[1];
        }

        if (isRequestMethodADelete($this->request)) {
            if ($group_order_token) {
                return $this->cancelGroupOrder($group_order_token);
            }
        } else if (isRequestMethodAPost($this->request)) {
            if ($this->hasRequestForDestination('submit')) {
                return $this->manualSendOfType2GroupOrder($group_order_token);
            }
            // do cart stuff
            if ($this->isAnonymousRequest() || $this->user['is_guest']) {
                return createErrorResourceWithHttpCode("Unauthorized. You are not authorized to perform this action.", 403, $error_code);
            }
            if ($this->hasRequestForDestination("increment")) {
                if (preg_match('%/increment/([0-9]{2,3})%', $this->request->url, $matches2)) {
                    $increment_in_minutes = intval($matches2[1]);
                    if ($group_order_resource = Resource::find($this->adapter,'',array("3"=>array("group_order_token"=>$group_order_token)))) {
                        if ($group_order_activity_resource = SplickitController::getResourceFromId($group_order_resource->auto_send_activity_id,"ActivityHistory")) {
                            $parent_order_resource = SplickitController::getResourceFromId($group_order_resource->order_id,"Order");
                            $merchant = MerchantAdapter::staticGetRecordByPrimaryKey($parent_order_resource->merchant_id,'MerchantAdapter');
                            $new_auto_send_time = $group_order_activity_resource->doit_dt_tm + ($increment_in_minutes * 60);
                            $new_pickup_ts = $this->getDefaultFullfillmentTime($new_auto_send_time);
                            if (! $this->validateOrderFullfillmentTimeForGroupOrder($new_pickup_ts,$parent_order_resource->order_type,$merchant)) {
                                return $this->error_resource;
                            }
                            $group_order_activity_resource->doit_dt_tm = $new_auto_send_time;
                            if ($group_order_activity_resource->save()) {
                                $new_time_data = $this->getMerchantLocalTimesForGroupOrder($new_auto_send_time,$new_pickup_ts,$merchant);
                                
                                $group_order_resource->send_on_local_time_string = $new_time_data['send_on_local_time_string'];
                                $group_order_resource->save();

                                $parent_order_resource->pickup_dt_tm = $new_time_data['pickup_dt_tm'];
                                $parent_order_resource->save();

                                $this->request->method = 'GET';
                                $this->request->url = "/app2/apiv2/grouporders/$group_order_token";
                                unset($this->request->data);
                                return $this->processV2request();
                            } else {
                                return createErrorResourceWithHttpCode("There was an error trying to update the auto send time and the change was not made.", 500, $error_code);
                            }
                        } else {
                            return createErrorResourceWithHttpCode("Unable to locate group order auto send activity.", 500, $error_code);
                        }
                    } else {
                        return createErrorResourceWithHttpCode("Unable to locate group order: $group_order_token", 500, $error_code);
                    }
                } else {
                    return createErrorResourceWithHttpCode("Not a valid increment amount.", 422, $error_code);
                }

            }
            if ($cart_ucid = $this->request->data['cart_ucid']) {
                if ($group_order_data = CompleteOrder::getItemsForGroupOrderFromCartId($cart_ucid)) {
                    $this->request->data = array_merge($this->request->data, $group_order_data);
                }
            }

            if ($this->request->data['merchant_id']) {
                $this->submitted_merchant_id = $this->request->data['merchant_id'];
            }
            if ($group_order_token) {
                $resource = $this->addDataToGroupOrder($this->user, $this->request->data['items'], $group_order_token);
                if ($resource->hasError()) {
                    return $resource;
                }
                // sanitize the response with only relevant informaiton
                $group_order_resource = Resource::dummyfactory(array("group_order_detail_id" => $resource->group_order_detail_id));
            } else {
                $group_order_resource = $this->createGroupOrder();
                if ($group_order_resource->error) {
                    return $group_order_resource;
                }
                if ($this->request->data['items']) {
                    $group_order_detail_resource = $this->addDataToGroupOrder($this->user, $this->request->data['items'], $group_order_resource->group_order_token);
                }
            }
            // now set cart that was used to submit to 'G'
            if ($cart_ucid) {
                CartsAdapter::setStatusOfCart($cart_ucid, OrderAdapter::GROUP_ORDER);
            }
            return $group_order_resource;
        } else if (isRequestMethodAGet($this->request)) {
            if ($group_order_token) {
                $group_order_resource = $this->getGroupOrderData($group_order_token);
                unset($group_order_resource->auto_send_activity_id);
                if ($this->isRequestingUserTheAdminForThisGroupOrder($group_order_resource)) {
                    if (! $this->isGroupOrderResourceValidForAdminGet($group_order_resource)) {
                        return $this->error_resource;
                    }
                } else {
                    if ($this->isGroupOrderActive($group_order_resource)) {
                        return $group_order_resource;
                    } else {
                        return $this->error_resource;
                    }
                }
                $group_order_resource->set('group_order_admin', $this->getUserFields($group_order_resource->admin_user_id));
                $place_order_controller = new PlaceOrderController(getM(), $this->user, $this->request);
                if ($group_order_resource->group_order_type == 1) {
                    $group_order_info_resource = $place_order_controller->getCart($group_order_token);
                    $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_resource->group_order_id),'GroupOrderIndividualOrderMapsAdapter');
                    $total_orders = 0;
                    $total_complete_orders = 0;
                    foreach ($records as $record) {
                        $total_orders++;
                        if ($record['status'] == 'Submitted') {
                            $total_complete_orders++;
                        }
                    }
                    $group_order_resource->set("total_orders",$total_orders);
                    $group_order_resource->set("total_submitted_orders",$total_complete_orders);
                } else {
                    $submitted = strtolower($group_order_resource->status) == 'submitted' ? true : false;
                    $group_order_info_resource = $this->getTypeTwoGroupOrderInfoAsResource($group_order_resource->group_order_id,$submitted);
                }
                $group_order_resource->set('order_summary', $group_order_info_resource->order_summary);
                if ($group_order_resource->merchant_menu_type == 'delivery') {
                    if ($order_resource = Resource::find(new OrderAdapter($m), $group_order_info_resource->order_id)) {
                        $addr_opts[TONIC_FIND_BY_METADATA]['user_addr_id'] = $order_resource->user_delivery_location_id;
                        if ($addr_resource = Resource::find(new UserDeliveryLocationAdapter($m), $order_resource->user_delivery_location_id)) {
                            $addr_data['business_name'] = $addr_resource->business_name;
                            $addr_data['address1'] = $addr_resource->address1;
                            $addr_data['address2'] = $addr_resource->address2;
                            $addr_data['city'] = $addr_resource->city;
                            $addr_data['state'] = $addr_resource->state;
                            $addr_data['zip'] = $addr_resource->zip;
                            $group_order_resource->set('delivery_address', $addr_data);
                        }
                    }
                }
                unset($group_order_resource->admin_user_id);
                return $group_order_resource;
            }
        }
        return createErrorResourceWithHttpCode("GroupOrderController endpoint not built yet", 404, 999, $data);
    }

    function getSubmittedTypeTwoGroupOrderInfoAsResource($group_order_id)
    {
        $this->getTypeTwoGroupOrderInfoAsResource($group_order_id,true);
    }

    function getTypeTwoGroupOrderInfoAsResource($group_order_id,$submitted = false)
    {
        $data = array("group_order_id"=>$group_order_id);
        if ($submitted) {
            $data['status'] = 'Submitted';
        }
        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords($data,'GroupOrderIndividualOrderMapsAdapter');
        $group_order_info_resource = $this->group_order_resource;
        $total_orders = 0;
        $total_complete_orders = 0;
        $total_items = 0;
        $user_items = array();
        $place_order_adapter = new PlaceOrderAdapter($m);
        foreach ($records as $record) {
            $order_id = $record['user_order_id'];
            $base_order = CompleteOrder::staticGetCompleteOrder($order_id);
            $data['user_id'] = $base_order['user_id'];
            $data['full_name'] = $base_order['full_name'];
            $data['item_count'] = $base_order['order_qty'];
            $data['status'] = $record['status'];
            $user_items[] = $data;
            $total_orders++;
            if (strtolower($record['status']) == 'submitted') {
                $total_complete_orders++;
            }
            $total_items = $total_items + $base_order['order_qty'];
            $total_elevel_items = $total_elevel_items + $place_order_adapter->getNumberOfELevelItemsInItemArray($base_order['order_details']);
        }
        $group_order_info_resource->set("total_orders",$total_orders);
        $group_order_info_resource->set("total_submitted_orders",$total_complete_orders);
        $group_order_info_resource->set("total_items",$total_items);
        $group_order_info_resource->set("total_e_level_items",$total_elevel_items);
        //$group_order_info_resource->set("merchant_id",$this->group_order_resource->merchant_id);
        $group_order_info_resource->set("order_summary",array("user_items"=>$user_items));
        return $group_order_info_resource;
    }

    function getUserFields($user_id)
    {
        if ($user_resource = UserAdapter::getUserResourceFromId($user_id)) {
            return array(
                'first_name' => $user_resource->first_name,
                'last_name' => $user_resource->last_name,
                'email' => $user_resource->email,
                'admin_uuid' => $user_resource->uuid
            );
        } else {
            return null;
        }
    }

    function createGroupOrder()
    {
        if ($this->isThisUserAGuest()) {
            return createErrorResourceWithHttpCode(self::GUEST_CANNOT_BE_ADMIN_FOR_GROUP_ORDER_ERROR, 403, $error_code);
        }

      $this->request->_parseRequestBody();
      $data = $this->request->data;
      $go_options[TONIC_FIND_BY_METADATA] = array("admin_user_id"=>$this->user['user_id'],"status"=>'Active');

      if ($this->user['is_guest']) {
        return createErrorResourceWithHttpCode("Unauthorized. You are not authorized to perform this action.", 403, $error_code);
      }

      if ($go_resources = Resource::findAll($this->adapter,null,$go_options)) {
            foreach ($go_resources as $go_resource) {
                if ($go_resource->expires_at < $this->getCurrentTime()) {
                    $go_resource->status = 'Expired';
                    $go_resource->save();
                } else {
                    return createErrorResourceWithHttpCode("Sorry, you can only have one active group order at a time. Please cancel the first one if you would like to create a new one.", 422, 999, $data);
                }
            }

        }
        if (isset($data['submit_at_ts']) && $data['group_order_type'] != 2) {
            unset($data['submit_at_ts']);
        }
        if (isset($data['submit_at_ts'])) {
            if ($data['submit_at_ts'] < (time() + 600)) {
                return createErrorResourceWithHttpCode("Sorry. You must choose a time that is more than 10 minutes from now for auto submit.", 422, 999, $data);
            }
            $pickup_ts = $this->getDefaultFullfillmentTime($data['submit_at_ts']);
            // check to see if merchant is open at this time
            $merchant = MerchantAdapter::staticGetRecordByPrimaryKey($data['merchant_id'],'MerchantAdapter');
            if ($this->validateOrderFullfillmentTimeForGroupOrder($pickup_ts,isset($data['user_addr_id']) ? 'D' : 'R',$merchant)) {
                $time_data = $this->getMerchantLocalTimesForGroupOrder($data['submit_at_ts'],$pickup_ts,$merchant);
                $data = array_merge($data,$time_data);
            } else {
                return $this->error_resource;
            }
        }
        $admin_user_id = $this->user['user_id'];
        $merchant_id = $data['merchant_id'];

        if (!$this->doesMerchantParticipateInGroupOrderingById($merchant_id)) {
            return createErrorResourceWithHttpCode("This merchant does not participate in group ordering.", 422, 999, $error_data);
        }
        if (strtolower($data['merchant_menu_type']) == 'delivery' && $data['user_addr_id'] < 1000) {
            return createErrorResourceWithHttpCode(self::NO_ADDRESS_SUBMITTED_FOR_DELIVERY_GROUP_ORDER_MESSAGE, 500, 999, $error_data);
        }

        $data['merchant_menu_type'] = isset($data['user_addr_id']) ? 'Delivery' : 'Pickup';

        $new_group_order_token = generateUUID();
        $data['group_order_token'] = $new_group_order_token;
        $data['ucid'] = $new_group_order_token;
        $data['user_id'] = getLoggedInUserId();
        $cart_resource = CartsAdapter::createCart($data);
        if ($cart_resource->error) {
            return $cart_resource;
        }
        if ($data['user_addr_id']) {
            $place_order_controller = new PlaceOrderController($m,$this->user,$r);
            $place_order_controller->setOrderAndMerchantByUcid($cart_resource->ucid);
            $cart_resource = $place_order_controller->processDeliveryLocation($data['user_addr_id'],$cart_resource);
            if ($cart_resource->hasError()) {
                return $cart_resource;
            }
        }
        $data['order_id'] = $cart_resource->order_id;
        $data['admin_user_id'] = $admin_user_id;
        $resource = Resource::factory($this->adapter, $data);
        if ($resource->save()) {
            $group_order_id = $this->adapter->_insertId();
            // check to see if there is a send on timestamp
            if ($data['submit_at_ts']) {
                //create the activity to submit the group order
                $activity_id = ActivityHistoryAdapter::createActivity("SendGroupOrder", $data['submit_at_ts'], "group_order_id=$group_order_id", $activity_text);
                $resource->set("auto_send_activity_id", $activity_id);
                $resource->save();
            }
            $this->sendGroupOrderInfoAsEmails($data);
        }
        return $resource;
    }

    function getDefaultFullfillmentTime($submit_at_ts)
    {
        return $submit_at_ts + (self::DEFAULT_FULLFILLMENT_TIME_IN_MINUTES_FOR_AUTO_SEND*60);
    }

    function validateOrderFullfillmentTimeForGroupOrder($pickup_ts,$order_type,$merchant)
    {
        $hour_adapter = new HourAdapter($m);
        $hour_adapter->setCurrentTime($this->getCurrentTime());
        if (! $hour_adapter->isMerchantOpenAtThisTime($merchant['merchant_id'],$merchant['time_zone'],$order_type,$pickup_ts)) {
            $this->error_resource = createErrorResourceWithHttpCode("Sorry. This merchant is closed at your requested order time.", 422, 999, $data);
            return false;
        } else {
            return true;
        }
    }

    function getMerchantLocalTimesForGroupOrder($submit_at_ts,$pickup_ts,$merchant)
    {
        $time_data = array();
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone(getTheTimeZoneStringFromOffset($merchant['time_zone'],$merchant['state'])));
        $date->setTimestamp($this->getCurrentTime());
        $time_data['order_dt_tm'] = $date->format("Y-m-d H:i:s");
        $date->setTimestamp($pickup_ts);
        $time_data['pickup_dt_tm'] = $date->format("Y-m-d H:i:s");
        $date->setTimestamp($submit_at_ts);
        $time_data['send_on_local_time_string'] = $date->format("l g:i a");
        return $time_data;
    }

    function cancelGroupOrder($group_order_token)
    {
        $go_resource = $this->getGroupOrder($group_order_token);
        if (! $this->isRequestingUserTheAdminForThisGroupOrder($go_resource)) {
            return createErrorResourceWithHttpCode("Unauthorized. You are not authorized to perform this action.", 403, $error_code);
        }
        if ($go_resource->error == null) {
            $go_resource->status = 'cancelled';
            if ($go_resource->save()) {
                CartsAdapter::setStatusOfCart($group_order_token, OrderAdapter::ORDER_CANCELLED);
                if ($go_resource->group_order_type == self::GROUP_ORDER_PARTICIPANT_PAY_TYPE) {
                   $this->doRefundForChildOrdersOfCancelledParentGroupOrder($go_resource);
                }
            } else {
                return createErrorResourceWithHttpCode("There was an error and the group order could not be cancelled.", 500, $error_code);
            }
        }
        return $go_resource;
    }

    function doRefundForChildOrdersOfCancelledParentGroupOrder($group_order_resource)
    {
        $go_map_records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_resource->group_order_id,"status"=>'Submitted'),"GroupOrderIndividualOrderMapsAdapter");
        $request = $this->request;
        $request->data['note'] = "Group Order Cancelled";
        foreach ($go_map_records as $map_record) {
            if ($group_order_resource->group_order_id != $map_record['group_order_id']) {
                myerror_log("ERROR!!!! we have grabbed a group order to refund that is not part of the group order");
                MailIt::sendErrorEmail("GROUP ORDER REFUND ERROR!!!!","we are trying to refund an error that is not part of the group order");
                continue;
            }
            $order_record = OrderAdapter::staticGetRecordByPrimaryKey($map_record['user_order_id'],'OrderAdapter');
            $user = UserAdapter::staticGetRecordByPrimaryKey($order_record['user_id'],'UserAdapter');
            $request->url = '/orders/'.$order_record['ucid'];
            $order_controller = new OrderController(getM(), $user, $request, 5);
            $refund_results = $order_controller->issueOrderRefund($map_record['user_order_id'], "0.00");
            if ($refund_results['status'] == 'success') {
                $order_controller->updateOrderStatusById($order_record['order_id'],OrderAdapter::ORDER_CANCELLED);
            } else {
                myerror_log("ERROR!!! could not refund child order as part of group order cancel");
                MailIt::sendErrorEmailSupport("ERROR REFUNDING CHILD ORDER","There was an error trying to refund order_id: ".$map_record['user_order_id']."   which was part of a participant pay group order that got cancelled. Please investigate");
            }
        }
    }

    function generateGroupOrderLink($merchant_id, $group_order_token, $merchant_menu_type)
    {
        $merchant_menu_type = strtolower($merchant_menu_type);
        $skin_name = strtolower(getSkinNameForContext());
        $skin_name = preg_replace('/\s*/', '', $skin_name);
        
        $base_url = getBaseUrlForContext();

        $link = "$base_url/merchants/$merchant_id?order_type=$merchant_menu_type&group_order_token=$group_order_token";
        return $link;
    }

    function sendGroupOrderInfoAsEmails($data)
    {
        $link = $this->generateGroupOrderLink($data['merchant_id'], $data['group_order_token'], $data['merchant_menu_type']);
        $skin_external_identifier= strtolower($_SERVER['SKIN']['external_identifier']);
        $skin_name = strtolower($_SERVER['SKIN']['skin_name']);
        $merchant = MerchantAdapter::staticGetRecordByPrimaryKey($data['merchant_id'], 'MerchantAdapter');
        $merchant_name = $merchant['display_name'];
        $merchant_lat=$merchant['lat'];
        $merchant_lng=$merchant['lng'];
        $css_url_file = "https://s3.amazonaws.com/com.splickit.products/".str_replace(' ','',$skin_external_identifier)."/web/css/production.".str_replace(' ','',$skin_name).".css";
        $color= $this->parse_css($css_url_file);
        $skin_button_background = $color[',body button,body .button'][0]['background-color'];
        $skin_button_foreground = $color[',body button,body .button'][1]['color'];
        $skin_link_color = $color['body a'][0]['color'];
        $email_data = array(
            "skin_link_color" => $skin_link_color,
            "button_background_color" => $skin_button_background,
            "button_foreground_color" =>$skin_button_foreground,
            "merchant_lat" =>$merchant_lat,
            "merchant_lng" =>$merchant_lng,
            "merchant_name"=>$merchant_name,
            "skin_external_identifier" =>$skin_external_identifier,
            "skin_name"=> $skin_name,
            "admin_full_name" => $this->user['first_name'] . ' ' . $this->user['last_name'],
            "admin_first_name" => $this->user['first_name'],
            "notes" => $data['notes'],
            "link" => $link
        );

        if ($data['send_on_local_time_string']){
            $email_data["submit_at_ts"] = $data['send_on_local_time_string'];
        }
        
        $subject = "Invitation To A " . ucwords(getSkinNameForContext()) . " Group Order";
        $body = $this->getGroupOrderEmailBody($email_data);
        if ($body == null) {
            $body = "Click here to start your participation in the group order: ".$link;
        }

        if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($data,'participant_emails')) {
            $invitees = explode(",", $data['participant_emails']);
            foreach ($invitees as $invitee) {
                MailIt::sendUserEmailMandrill($invitee, $subject, $body, ucwords(getSkinNameForContext()) . " Group Ordering",$bcc,array("order_id"=>$data['order_id']));
            }
        }

        $admin_email_data = array(
            "skin_link_color" => $skin_link_color,
            'merchant_lat' =>$merchant_lat,
            'merchant_lng' =>$merchant_lng,
            'merchant_name'=>$merchant_name,
            'skin_external_identifier' =>$skin_external_identifier,
            'skin_name' => ucwords($skin_name),
            'link' => $link,
            'order_id' => $data['order_id']
        );

        $this->sendGroupOrderAdminEmail($admin_email_data, $this->user['email']);
    }

    function parse_css($css_file)
    {
        $css =  file_get_contents($css_file);
        preg_match_all( '/(?ims)([a-z0-9\s\,\.\:#_\-@]+)\{([^\}]*)\}/', $css, $arr);

        $result = array();
        foreach ($arr[0] as $i => $x)
        {
            $selector = trim($arr[1][$i]);
            $rules = explode(';', trim($arr[2][$i]));
            $result[$selector] = array();
            foreach ($rules as $strRule)
            {
                if (!empty($strRule))
                {
                    $rule = explode(":", $strRule);
                    $result[$selector][][trim($rule[0])] = trim($rule[1]);
                }
            }
        }
        return $result;
    }

    function sendGroupOrderAdminEmail($data, $email_addr)
    {
        $body = $this->email_service->getGroupOrderAdminEmail($data);

        $skin_name = ucwords(getSkinNameForContext());
        $subject = "Your $skin_name Online Group Order Has Been Started!";
        MailIt::sendUserEmailMandrill($email_addr, $subject, $body, "$skin_name Group Ordering",$bcc,array('order_id'=>$data['order_id']));
    }

    function getGroupOrderEmailBody($email_data)
    {
        return $this->email_service->getGroupOrderInviteEmail($email_data);
    }

    function getGroupOrder($token)
    {
        $options[TONIC_FIND_BY_METADATA]['group_order_token'] = $token;
        if ($resource = Resource::findExact($this->adapter, '', $options)) {
            myerror_log("we have the group order record");
            $this->group_order_resource = $resource;
            return $resource;
        } else {
            myerror_log("ERROR! COULD NOT FIND GROUP ORDER FROM TOKEN SUBMITTED. token: " . $token);
            return createErrorResourceWithHttpCode("Error. No group order matching token: $token", 422, 999, $error_data);
        }
    }

    function autoSendGroupOrder($group_order_token)
    {
        $complete_group_order_resource = $this->getGroupOrderData($group_order_token);
        $order_data = $complete_group_order_resource->getDataFieldsReally();
        $order_data['user_id'] = $complete_group_order_resource->admin_user_id;
        $order_data['auto_set_lead_time'] = 'true';

    }

    function manualSendOfType2GroupOrder($group_order_token)
    {
        if ($this->sendGroupOrder($group_order_token)) {
            return $this->getGroupOrderData($group_order_token);
        } else {
            return $this->error_resource;
        }
    }

    function sendGroupOrder($group_order_token)
    {
        // create messages
        $group_order_resource = $this->getGroupOrderData($group_order_token);
        return $this->sendGroupOrderByGroupOrderResource($group_order_resource);
    }

    function sendGroupOrderByGroupOrderResource($group_order_resource)
    {
        if ($group_order_resource->group_order_type != 2) {
            $this->error_resource = createErrorResourceWithHttpCode("Sorry, only Type 2 group orders can be submitted with this endpoint",422,422);
            return false;
        }
        $this->group_order_resource = $group_order_resource;
        $group_order_token = $group_order_resource->group_order_token;
        if ($this->isGroupOrderActive($group_order_resource)) {
            $base_order_parent = CompleteOrder::staticGetCompleteOrder($group_order_token,$m);
            if ($base_order_parent['order_qty'] == 0) {
                myerror_log("THERE ARE NO ITEMS added to this group order, CANNOT Submit");
                $this->error_resource = createErrorResourceWithHttpCode("Sorry, there were no orders added to the group order so we cannot submit it. The group order has been cancelled.",422,422);
                $this->group_order_resource->status = 'cancelled';
                $this->group_order_resource->save();
                OrderAdapter::updateOrderStatus(OrderAdapter::ORDER_CANCELLED, $this->group_order_resource->order_id);
                if ($this->submit_from_activity) {
                    return false;
                } else {
                    MailIt::sendErrorEmailSupport("Attempted Submit of EMPTY type 2 group order","group order token: $group_order_token");
                    return false;
                }

            }
        } else {
            return false;
        }

        $create_messages_controller = new CreateMessagesController();
        $create_messages_controller->immediate_delivery = true;
        $create_messages_controller->createOrderMessagesFromOrderInfo($base_order_parent['order_id'],$base_order_parent['merchant_id'],45,$base_order_parent['pickup_dt_tm']);

        $this->sendConfirmationEmailToGroupOrderAdminForTypeTwo($base_order_parent);

        // set sub orders to OrderAdapter::ORDER_SUBMITTED
        $group_order_id =  $this->group_order_resource->group_order_id;
        $records = GroupOrderIndividualOrderMapsAdapter::staticGetRecords(array("group_order_id"=>$group_order_id),'GroupOrderIndividualOrderMapsAdapter');
        $order_adapter = new OrderAdapter($m);
        foreach($records as $record) {
            $order_resource = Resource::find($order_adapter,$record['user_order_id'],$options);
            if ($order_resource->status == OrderAdapter::GROUP_ORDER) {
                $order_resource->status = OrderAdapter::ORDER_SUBMITTED;
                $order_resource->save();
            } else {
                $order_resource->status = OrderAdapter::ORDER_CANCELLED;
                $order_resource->save();
            }
        }

        // set group order record to submitted
        $this->group_order_resource->status = 'Submitted';
        $this->group_order_resource->sent_ts = date("Y-m-d H:i:s");
        return $this->group_order_resource->save();
    }

    function sendConfirmationEmailToGroupOrderAdminForTypeTwo($complete_order)
    {
        // first set amounts to zero for group order
        $complete_order['order_amt'] = 0.00;
        $complete_order['total_tax_amt'] = 0.00;
        foreach($complete_order['order_details'] as &$order_detail) {
            $order_detail['price'] = 0.00;
            $order_detail['item_total'] = 0.00;
            $order_detail['item_total_w_mods'] = 0.00;
            $order_detail['item_tax'] = 0.00;
        }
        foreach($complete_order['receipt_items'] as &$receipt_item) {
            if ($receipt_item['title'] == 'Subtotal' || $receipt_item['title'] == 'Tax') {
                $receipt_item['amount'] = "$0.00";
            }
        }
        $complete_order['is_submit_group_order'] = true;
        
        $place_order_controller = new PlaceOrderController($m,$this->user,$this->request,$this->log_level);
        $place_order_controller->buildAndStageConfirmationEmailFromCompleteOrderData($complete_order);
    }

    function getGroupOrderData($group_order_token = 'XX')
    {
        if ($group_order_token == 'XX') {
            $this->request->_parseRequestBody();
            if (isset($this->request->data['group_order_token'])) {
                $group_order_token = $this->request->data['group_order_token'];
            } else {
                myerror_log("ERROR! NO GROUP ORDER TOKEN SUBMITTED");
                return createErrorResourceWithHttpCode("Error. No group order token submitted", 422, 999, $error_data);
            }
        }
        myerror_log("WE HAVE A GROUP ORDER TOKEN: " . $group_order_token);

        $_SERVER['log_level'] = 5;

        $group_order = $this->getGroupOrder($group_order_token);
        $complete_group_order = $group_order;

        return $complete_group_order;
    }

    function addToGroupOrderFromRequest()
    {
        myerror_log("starting add to group order from request");
        return $this->addDataToGroupOrder($this->user, $this->request->data['items'], $this->request->data['group_order_token']);
    }

    function isGroupOrderResourceValidForAdminGet($group_order_resource)
    {
        if ($this->isGroupOrderActive($group_order_resource)) {
            return true;
        } else {
            if ($this->error_resource->error == $this->group_order_submitted_message) {
                $this->error_resource = null;
                return true;
            }
        }
        return false;
    }

    function isGroupOrderActive($group_order_resource)
    {
        if ($group_order_resource->sent_ts != '0000-00-00 00:00:00') {
            myerror_log("ERROR!.  group order has already been submitted: " . $group_order_resource->sent_ts);
            $this->error_resource = createErrorResourceWithHttpCode($this->group_order_submitted_message, 422, 422);
        } else if (strtolower($group_order_resource->status) == 'submitted') {
            myerror_log('ERROR! group order has been submitted');
            $this->error_resource = createErrorResourceWithHttpCode($this->group_order_submitted_message, 422, 422);
        } else if ($group_order_resource->expires_at < time()) {
            myerror_log("ERROR!.  group order token has expired");
            $this->error_resource = createErrorResourceWithHttpCode($this->group_order_expired_message, 422, 422);
        } else if (strtolower($group_order_resource->status) == 'cancelled') {
            myerror_log("ERROR!.  group order has been cancelled");
            $this->error_resource = createErrorResourceWithHttpCode($this->group_order_cancelled_message, 422, 422);
        } else if (strtolower($group_order_resource->status) != 'active') {
            myerror_log("ERROR!.  group order is no longer active: " . $group_order_resource->status);
            $this->error_resource = createErrorResourceWithHttpCode($this->group_order_not_active_message, 422, 422);
        } else {
            return true;
        }
        return false;
    }

    /**
     *
     * @desc used to add items for a user to a group order.
     * @param User $user
     * @param Array $items
     * @param String $group_order_token
     *
     * @return Resource (GroupOrderDetailResource)
     */
    function addDataToGroupOrder($user, $items, $group_order_token)
    {
        if ($group_order_resource = $this->getGroupOrder($group_order_token)) {
            if ($this->isGroupOrderActive($group_order_resource)) {
                // all is good
                $group_order_id = $group_order_resource->group_order_id;
            } else {
                return $this->error_resource;
            }
        } else {
            myerror_log("ERROR! no group id found with token: " . $group_order_token);
            return createErrorResourceWithHttpCode("ERROR! no group id found with token: " . $group_order_token, 422, 422);
        }
        if ($items == null || count($items) < 1) {
            return createErrorResourceWithHttpCode("ERROR! No items were passed to add to the group order", 422, 422);
        }
        myerror_logging(3, "group order id acquired: " . $group_order_id);
        if ($this->submitted_merchant_id) {
            if ($this->submitted_merchant_id != $group_order_resource->merchant_id) {
                return createErrorResourceWithHttpCode("Sorry, something has gotten corrupted with this group order. You are submitting an order for the wrong merchant.", 422, $error_code);
            }
        }

        $user_name = $user['first_name'] . ' ' . substr($user['last_name'], 0, 1);
        myerror_logging(3, "name acquired: " . $user_name);
        foreach ($items as &$item) {
            if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($item, 'note')) {
                $item['note'] = $user_name . ". - " . $item['note'];
            } else {
                $item['note'] = $user_name . ".";
            }

        }
//        $order_data['merchant_id'] = $group_order_resource->merchant_id;
//        $order_data['user_id'] = $group_order_resource->admin_user_id;
//        $order_data['items'] = $items;
//        $group_admin_user = UserAdapter::staticGetRecordByPrimaryKey($group_order_resource->admin_user_id, 'User');
        $carts_adapter = new CartsAdapter();
        if ($carts_adapter->addItemsToCart($items, $group_order_resource->getDataFieldsReally())) {
            $cart_ucid = isset($this->request->data['cart_ucid']) ? $this->request->data['cart_ucid'] : $group_order_token;
            $cart_detail_resource = $carts_adapter->getCartResourceWithOrderSummary($cart_ucid);
        } else {
            return createErrorResourceWithHttpCode("ther was an error", 422, 999);
        }
        $group_order_detail_resource = Resource::createByData(new GroupOrderDetailAdapter($this->mimetypes), array("group_order_id" => $group_order_id, "user_id" => $user_id, "order_json" => json_encode($items)));
        $cart_detail_resource->set("group_order_detail_id", $group_order_detail_resource->group_order_detail_id);
        return $cart_detail_resource;
    }

    function doesMerchantParticipateInGroupOrderingById($merchant_id)
    {
        $merchant = MerchantAdapter::staticGetRecordByPrimaryKey($merchant_id, 'MerchantAdapter');
        return 1 == $merchant['group_ordering_on'];
    }

    function isRequestingUserTheAdminForThisGroupOrder($group_order)
    {
        $go_admin_user_id = is_a($group_order,'Resource') ? $group_order->admin_user_id : $group_order['admin_user_id'];
        return $this->isRequestingUserSameAsSubmittedUserId($go_admin_user_id);
    }

    function isRequestingUserSameAsSubmittedUserId($user_id)
    {
        return $user_id == $this->user['user_id'];
    }

    function getCurrentTime()
    {
        if ($this->current_time == null) {
            return time();
        } else {
            return $this->current_time;
        }
    }

}