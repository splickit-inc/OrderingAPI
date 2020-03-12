<?php
class DoordashService extends SplickitService
{

    var $url;
    var $merchant;
    var $credentials;
    private $door_dash_response;

    function __construct($data)
    {
        parent::__construct($data);
        if ($credentials = $data['credentials']) {
            $this->credentials = $credentials;
        }
        if ($merchant = $data['merchant']) {
            $this->merchant = $merchant;
        }
        $this->url = getProperty('doordash_service_url');
        $this->api_key = getProperty('doordash_api_key');
    }

    //curl -H "Authorization: Bearer 2242feb2370658f1415ae86960fc21a0d4a4af51" https://api.doordash.com/drive/v1/estimates

    function setMethod($method)
    {
        $this->method = $method;
    }

    function getEstimate($user_delivery_info,$merchant,$order_value = 1500,$delivery_time_stamp = 1)
    {
        $this->merchant = $merchant;
        $estimate_fields = [];
        $estimate_fields['pickup_address'] = $this->createAddressObject($merchant);
        $estimate_fields['dropoff_address'] = $this->createAddressObject($user_delivery_info);
        $estimate_fields['external_business_name'] = $merchant['name'];
        // we dont know this when getting estimates so we guesss and message the user appropriately
        $estimate_fields['order_value'] = isset($user_delivery_info['order_amt']) ? $user_delivery_info['order_amt']*100 : $order_value;
        if (isset($user_delivery_info['requested_delivery_time_stamp'])) {
            $delivery_time_stamp;
        }
        if ($delivery_time_stamp == 1) {
            $pickup_time_stamp = isset($user_delivery_info['pickup_timestamp_at_merchant']) ? $user_delivery_info['pickup_timestamp_at_merchant'] : time() + ($merchant['lead_time'] * 60);
            $dummy_pickup_time = date(DATE_ATOM,$pickup_time_stamp);
            $estimate_fields['pickup_time'] = $dummy_pickup_time;
        } else {
            $delivery_time = date(DATE_ATOM,$delivery_time_stamp);
            $estimate_fields['delivery_time'] = $delivery_time;
        }
        $estimate_url = $this->url.'estimates';
        $response = DoordashCurl::curlIt($estimate_url,$estimate_fields);
        $response['method'] = 'doordash_estimate';
        return $this->processCurlResponse($response);
    }

    function validateDelivery($complete_order)
    {
        $fields = $this->buildFieldsForPost($complete_order);
        $dummy_pickup_time = date(DATE_ATOM,time()+1800);
        $fields['pickup_time'] = "$dummy_pickup_time";
        $validations_url = $this->url.'validations';
        $response = DoordashCurl::curlIt($validations_url,$fields);
        return $this->processCurlResponse($response);
    }

    function requestDelivery($complete_order)
    {
        $this->merchant = $complete_order['merchant'];
        $fields = $this->buildFieldsForPost($complete_order);
        $fields['items'] = $this->getItemsNode($complete_order->order_details);
        $fields['pickup_time'] = date(DATE_ATOM,$complete_order['ready_timestamp']);
        $request_delivery_url = $this->url.'deliveries';
        $response = DoordashCurl::curlIt($request_delivery_url,$fields);
        $response = $this->processCurlResponse($response);
        return $this->isSuccessfulResponse($response);
    }

    function cancelDeliveryRequest($doordash_delivery_id)
    {
        $request_delivery_url = $this->url."deliveries/$doordash_delivery_id/cancel";
        $response = DoordashCurl::curlIt($request_delivery_url);
        $response['doordash_delivery_id'] = $doordash_delivery_id;
        $response = $this->processCurlResponse($response);
        if ($response['http_code'] == 200) {
            return true;
        } else {
            MailIt::sendErrorEmailSupport("Doordash cancel Order failure",json_encode($response));
            MailIt::sendErrorEmailAdam("Doordash cancel Order failure",json_encode($response));
            return false;
        }
        //return $response['http_code'] == 200;
    }

    function getErrorFromCurlResponse()
    {
        $total_error = '';
        foreach ($this->door_dash_response['field_errors'] as $field_error) {
            $total_error .= $field_error['error'].' - ';
        }
        return $total_error;
    }

    function getItemsNode($order_details)
    {
        $items = [];
        foreach ($order_details as $order_detail) {
            $item = [];
            $item['name'] = $order_detail['item_name'];
            $items[] = $item;
        }
        return $items;
    }

    function buildFieldsForPost($complete_order)
    {
        $merchant = $complete_order['merchant'];
        $full_name = $complete_order['full_name'];
        $user_delivery_info = $complete_order['delivery_info']->getDataFieldsReally();
        $user = $complete_order['user'];
        $user['delivery_phone_no'] = $user_delivery_info['phone_no'];
        $user['business_name'] = $user_delivery_info['business_name'];

        $fields = [];
        $fields['pickup_address'] = $this->createAddressObject($merchant);
        $fields['pickup_business_name'] = $merchant['name'];
        $fields['pickup_instructions'] = "ask for the splickit order for $full_name";
        $fields['pickup_phone_number'] = "+1".$merchant['phone_no'];

        $fields['dropoff_address'] = $this->createAddressObject($user_delivery_info);
        $fields['dropoff_instructions'] = $user_delivery_info->instructions == null ? '' : $user_delivery_info->instructions;
        $fields['customer'] = $this->getCustomerNode($user);
        $fields['order_value'] = $complete_order['order_amt']*100;
        $fields['tip'] = 0;
        $fields['num_items'] = $complete_order['order_qty'];
        $fields['external_business_name'] = $merchant['name'];
        return $fields;
    }

    function getCustomerNode($user)
    {
        $customer = [];
        $customer['first_name'] = $user['first_name'];
        $customer['last_name'] = $user['last_name'];
        $customer['business_name'] = $user['business_name'];
        $customer['email'] = $user['email'];
        $customer['phone_number'] = $user['delivery_phone_no'];
        return $customer;
    }

    function processCurlResponse($response)
    {
        $this->curl_response = $response;
        $raw_return_as_array = array();
        if ($raw_return = $this->getRawResponse($response)) {
            $raw_return_as_array = $this->processRawReturn($raw_return);
            if (isset($raw_return_as_array['field_errors'][0])) {
                $raw_return_as_array['failure'] = true;
            }
        } else {
            $raw_return_as_array['failure'] = true;
        }
        if ($this->merchant['brand_id'] == 372 && isset($raw_return_as_array['fee']) && $raw_return_as_array['fee'] > 399) {
            $raw_return_as_array['fee'] = 399;
        }
        $this->door_dash_response = array_merge($response,$raw_return_as_array);
        return $this->door_dash_response;
    }

    function createAddressObject($object)
    {
        if (is_a($object,'Resource')) {
            $object = $object->getDataFieldsReally();
        }
        $doordash_address_fields = [];
        $doordash_address_fields['street'] = $object['address1'];
        if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmptyOnArray($object,'address2')) {
            $doordash_address_fields['unit'] = $object['address2'];
        }
        $doordash_address_fields['city'] = $object['city'];
        $doordash_address_fields['state'] = $object['state'];
        $doordash_address_fields['zip_code'] = $object['zip'];
        return $doordash_address_fields;
    }

    function getDoordashResponse()
    {
        return $this->door_dash_response;
    }

}
//class UnsuccessfulXoikosPushException extends Exception
//{
//    public function __construct($error_message, $xoikos_error_code, $code = 100) {
//        parent::__construct("Xoikos message failure: '$error_message'. Error Code: $xoikos_error_code", $code);
//    }
//}
//
//class NoMerchantExternalIdLoadedException extends Exception
//{
//    public function __construct() {
//        parent::__construct("Xoikos merchant failure, no external id loaded for active check", 500);
//    }
//}
?>
