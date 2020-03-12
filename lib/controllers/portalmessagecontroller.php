<?php

class PortalMessageController extends MessageController
{

    // we'll go with a new message type.  a pulled type of 'P'  since ping is going away
    // or shoud we do the old 'O' for opie.

    private $client_response_format = false;
    private $merchant_id;
    private $read_only;
    private $merchant_message_history_adapter;
    private $merchant_message_map_adapter;
    private $primary_message_format;
    private $primary_locked_value;


    function __construct($mt,$user,&$request,$l = 0)
    {
        parent::MessageController($mt, $user, $request,$l);
        $this->format = 'P';
        $this->merchant_message_history_adapter = new MerchantMessageHistoryAdapter(getM());
        $this->merchant_message_map_adapter = new MerchantMessageMapAdapter(getM());
        if (function_exists('getallheaders')) {
            $heads = getallheaders();
            logData($heads, 'Portal Message Headers',3);
        }
        if ($merchant_id = $this->getMerchantIdFromRequest()) {
            $this->merchant_id = $merchant_id;
            $this->setViewType();
        }

    }

    function processV2Request()
    {
        $this->processRequest();
    }

    function processRequest()
    {
        if (preg_match('%/messages/([0-9]{4,15})%', $this->request->url, $matches)) {
            $message_id = $matches[1];
            if ($this->markMessageAsViewed($message_id)) {
                return Resource::dummyfactory(['success'=>true]);
            } else {
                myerror_log("THERE WAS AN ERROR!!!  and the message could not be marked as viewed!  message_id: $message_id");
                return createErrorResourceWithHttpCode("The message could not be marked as viewed",500,500);
            }
        } else {
            if ($this->merchant_id) {
                return $this->getMessagesFromRemoteRequest();
            }  else {
                throw new Exception("no merchant id submitted");
            }
        }
    }

    function markMessageAsViewed($message_id)
    {
        $message_resource = Resource::find($this->merchant_message_history_adapter,''.$message_id);
        if ($this->merchant_message_history_adapter->markMessageResourceAsViewed($message_resource)) {
            if ($this->updateOrderStatus('E',$message_resource->order_id)) {
                return true;
            } else {
                myerror_log("The order could not be marked as executed in portal message controller!!!!  order_id: ".$message_resource->order_id);
                return true;
            }
        }
    }

    function getMerchantIdFromRequest()
    {
        $request = $this->request;
        if ($merchant_id = $request->data['merchant_id']) {
            return $merchant_id;
        }
    }

    function setViewType()
    {
        // determine if this merchant uses 'P' message types
        $mm['X'] = [];
        $mm['O'] = [];
        $records = $this->merchant_message_map_adapter->getRecords(['merchant_id'=>$this->merchant_id]);
        foreach ($records as $mmm) {
            if ($mmm['message_format'] == 'P') {
                $this->primary_message_format = 'P';
                $this->read_only = false;
                return;
            } else if ($mmm['message_type'] == 'X') {
                $mm['X'][] = $mmm['message_format'];
            } else if ($mmm['message_type'] == 'O') {
                $mm['O'][] = $mmm['message_format'];
            }
        }
        if (isset($mm['X'][0])) {
            $this->primary_message_format = $mm['X'][0];
            $this->read_only = true;
        } else {
            $this->primary_message_format = $mm['O'][0];
            $this->read_only = true;
        }
        return;
    }

    function isReadOnly()
    {
        return $this->read_only;
    }

    function isNotReadOnly()
    {
        return ! $this->isReadOnly();
    }

    function isPortalMessageDeliveryPrimary()
    {
        return 'P' == $this->primary_message_format;
    }

    function getMessagesFromRemoteRequest()
    {
        $this->client_response_format = true;
        $message_results = $this->getOrderMessagesForLoadedMerchantId();
        $message_results['read_only'] = $this->isReadOnly();
        return Resource::dummyfactory($message_results);
    }


    function getReadOnlyOrders()
    {
        // if merchant is not a PORTAL Message user, then the screen is read only. no interraction

        $messages_results = [];
        $messages_results['past_messages'] = [];
        $messages_results['late_messages'] = [];
        $messages_results['current_messages'] = [];
        $messages_results['future_messages'] = [];

        // first get all messages with a send time > 6am local
        $minutes_back = $this->getMinutesBackToLast6amAtMerchantsTimeZone($this->merchant,time());

        $message_resources = $this->getMessagesLaterThanNMinutesAgo($minutes_back);
        foreach ($message_resources as &$message_resource) {
            //$pickup_date_string = getMySqlFormattedDateTimeFromTimeStampAndTimeZone($message_resource->pickup_timestamp,date_default_timezone_get());
            if ($message_resource->locked == 'S') {
                if ($message_resource->pickup_timestamp <= time()) {
                    if ($message_resource->viewed == 'V') {
                        $messages_results['past_messages'][] = $this->getMessageInCorrectFormat($message_resource);
                    } else {
                        $messages_results['late_messages'][] = $this->getMessageInCorrectFormat($message_resource);
                    }
                } else if ($message_resource->pickup_timestamp > time()) {
                    if ($message_resource->viewed == 'V') {
                        $messages_results['past_messages'][] = $this->getMessageInCorrectFormat($message_resource);
                    } else {
                        $messages_results['current_messages'][] = $this->getMessageInCorrectFormat($message_resource);
                    }
                }
            } else if ($message_resource->next_message_dt_tm <= time()) {
                $messages_results['late_messages'][] = $this->getMessageInCorrectFormat($message_resource);
            } else {
                $messages_results['future_messages'][] = $this->getMessageInCorrectFormat($message_resource);
            }
        }
        return $messages_results;
    }

    function getOrderMessagesForLoadedMerchantId()
    {
        if ($this->isPortalMessageDeliveryPrimary()) {
            return $this->getPortalDeliveryMessages();
        } else {
            return $this->getReadOnlyOrders();
        }
    }

    function getMinutesBackToLast6amAtMerchantsTimeZone($merchant,$current_time)
    {
        $time_zone = getTheTimeZoneStringFromOffset($merchant['time_zone'],$merchant['state']);
        $today_6_am = getTimeStampForDateTimeAndTimeZone(6, 0, 0, date('m'), date('d'), date('Y'), $time_zone);
        if ($today_6_am > $current_time) {
            $today_6_am = $today_6_am - (24*60*60);
        }
        $minutes_back = floor(($current_time - $today_6_am) / 60);
        return $minutes_back;
    }

    function getPortalDeliveryMessages()
    {
        // should get ALL messages for the merchant from last hour and through to all future?
        // should catagorize messages as follows
        /*
         *   - future orders
         *   - current orders (orders that are within the lead time but have not been accepted) (when an order gets returned in this group we mark the message as 'S'
         *   - late orders ( orders that are within 10 minutes of due but have not been accepted by the merchant )
         *   - past orders ( orders that have been accepted by the merchant ) (message viewed gets set to 'V' which should trigger the order to be marked as 'E'
         *
         */

        myerror_log("We have portal as a primary delivery method calling in. merchant_id: ".$this->merchant_id,3);
        DeviceCallInHistoryAdapter::recordPullCallIn($this->merchant_id,$this->format);

        if ($this->isReadOnly()) {
            // if merchant is not a PORTAL Message user, then the screen is read only.  no interaction. throw error
            throw new Exception("cant get use portal get messages for read only merchant");
        }
        $messages_results = [];
        $messages_results['past_messages'] = [];
        $messages_results['late_messages'] = [];
        $messages_results['current_messages'] = [];
        $messages_results['future_messages'] = [];


        // first get all messages with a send time > 6am local
        $minutes_back = $this->getMinutesBackToLast6amAtMerchantsTimeZone($this->merchant,time());

        $message_resources = $this->getMessagesLaterThanNMinutesAgo($minutes_back);
        foreach ($message_resources as &$message_resource) {
            if ($message_resource->locked == 'P' && $message_resource->next_message_dt_tm <= time()) {
                $message_resource->locked = 'S';
                $message_resource->viewed = 'N';
                $message_resource->sent_dt_tm = getMySqlFormattedDateTimeFromTimeStampAndTimeZone(time());
                $message_resource->stamp = getStamp();
                $message_resource->save();
            }
            $message_resource->order_type = $message_resource->order_type == 'D' ? 'Delivery' :  'Pickup';
            if ($message_resource->locked == 'S') {
//                $pickup_time_stamp_string = date('Y-m-d H:i:s',$message_resource->pickup_timestamp);
//                $current_time_string = date('Y-m-d H:i:s');
//                myerror_log("pickup time string: $pickup_time_stamp_string       current time string: $current_time_string");
                if ($message_resource->pickup_timestamp <= time()) {
                    if ($message_resource->viewed == 'V') {
                        $messages_results['past_messages'][] = $this->getMessageInCorrectFormat($message_resource);
                    } else {
                        $messages_results['late_messages'][] = $this->getMessageInCorrectFormat($message_resource);
                    }
                } else if ($message_resource->pickup_timestamp > time()) {
                    if ($message_resource->viewed == 'V') {
                        $messages_results['past_messages'][] = $this->getMessageInCorrectFormat($message_resource);
                    } else {
                        // set this to the number of seconds before food is do to have the message put to the last column if it hasn't been viewed.  so if you want unviewed messages to move to 'late' when they hit 2 minutes till customer arrival, set this number to 120
                        $buffer_for_moving_message_to_late_group = 0;
                        if ($message_resource->pickup_timestamp > (time()+$buffer_for_moving_message_to_late_group)) {
                            $messages_results['current_messages'][] = $this->getMessageInCorrectFormat($message_resource);
                        } else {
                            $messages_results['late_messages'][] = $this->getMessageInCorrectFormat($message_resource);
                        }

                    }
                }
            } else if ($message_resource->next_message_dt_tm <= time()) {
                $messages_results['late_messages'][] = $this->getMessageInCorrectFormat($message_resource);
            } else {
                $messages_results['future_messages'][] = $this->getMessageInCorrectFormat($message_resource);
            }
        }

        return $messages_results;


    }

    function getMessageInCorrectFormat($message_resource)
    {
        $message_info = [];
        $message_info['order_id'] = $message_resource->order_id;
        $message_info['message_id'] = $message_resource->map_id;
        $message_info['order_status'] = $message_resource->status;
        $message_info['order_type'] = $message_resource->order_type;
        $message_info['viewed'] = $message_resource->viewed;
        $message_info['portal_order_json'] = $message_resource->portal_order_json;
        //might need to distinguish at some point
        if ($this->client_response_format) {
            return $message_info;
        } else {
            return $message_info;
        }
    }

    function getMessagesLaterThanNMinutesAgo($minutes_back)
    {
        $merchant_id = $this->merchant_id;
        $lower_time_limit = getMySqlFormattedDateTimeFromTimeStampAndTimeZone(time()-($minutes_back*60),date_default_timezone_get());
        if ($this->isPortalMessageDeliveryPrimary()) {
            $sql = "SELECT b.status, b.order_type, a.* from Merchant_Message_History a JOIN Orders b ON a.order_id = b.order_id WHERE a.merchant_id = $merchant_id and message_format = 'P' AND next_message_dt_tm > '$lower_time_limit' AND (locked = 'P' OR locked = 'S') AND a.logical_delete = 'N' order by pickup_timestamp DESC";
        } else {
            $sql = "SELECT b.status, b.order_type, a.* from Merchant_Message_History a JOIN Orders b ON a.order_id = b.order_id WHERE a.merchant_id = $merchant_id and message_format = '".$this->primary_message_format."' AND next_message_dt_tm > '$lower_time_limit' AND (locked = 'P' OR locked = 'N' OR locked = 'S') AND a.logical_delete = 'N' order by pickup_timestamp DESC";
        }

        myerror_log("the get portal messages sql: ".$sql);
        $mmha = new MerchantMessageHistoryAdapter(getM());
        $options[TONIC_FIND_BY_SQL] = $sql;
        if ($messages =  Resource::findAll($mmha,null,$options)) {
            return $messages;
        } else {
            return null;
        }
    }

    function getPrimaryMessageFormat()
    {
        return $this->primary_message_format;
    }


}
?>