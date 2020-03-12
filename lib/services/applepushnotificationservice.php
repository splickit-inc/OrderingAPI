<?php

class ApplePushNotificationService extends SplickitService
{
    var $service_name = "Apple Push Message";
    var $skin_external_id;
    var $settings = array();
    

    function __construct($data)
    {
        parent::__construct($data);
    }

    /**
     * @param $data the $message message will be send
     * @return bool returns true if message has been sent

     */

    function send($data)
    {
        myerror_log("INFO! Apple push notifications sending messages to users -> ".$this->setings['user_id']);
        myerror_log("INFO! Apple push notifications message to send -> ".$data['message']);

        $push_iphone = new PushIphone($this->skin_external_id, $mt);
        
        //only for service test
        if(isset($this->settings['use_prod_cert']) && $this->settings['use_prod_cert'] === true){
            $push_iphone->forceProdCertForTest();
        }
        $push_iphone->push(array($this->settings),$data['message']);
        $response = $push_iphone->response;
        $this->curl_response = $response;

        $results_array = $this->processCurlResponse($response);
        $this->results_array = $results_array;
        if ($this->results_array['status'] == self::SUCCESS) {
            return $response;
        }
        $this->curl_response['raw_result'] = json_encode($response);

        throw new ApplePushNotificationServiceException($results_array['error'], $results_array['error_code']);
    }

    function getSuccessStatusFromFullResult($result)
    {
        $result_based_on_http_code = $this->getSuccessFromHttpCode($result['http_code']);
        if (self::SUCCESS == $result_based_on_http_code) {
            // since we can get a good http code from a failure we need to look deeper
            if ($result['success'] == 0) {
                $error = $result['results'][0]['error'];
                myerror_log("WE had an error pushing to android: $error");
                return self::FAILURE;
            } else if ($result['failure'] > 0) {
                // we have the potential of having a situation where we send multiple ids and some fail and some pass
                // we should send an email here to support with the results but return a success;
                myerror_log("we have multiple results from an android push");
            }
            return self::SUCCESS;
        } else {
            return $result_based_on_http_code;
        }
    }

    function prepareService($message_data){
        $skin_id = $message_data['skin_id'];
        $skin_adapter = new SkinAdapter($mt);

        if ($skin_record = $skin_adapter->getRecord(array('skin_id' => $skin_id))){
            $this->skin_external_id = $skin_record['external_identifier'];
        }else{
            throw new ApplePushNotificationServiceException();
        }
        
        $umsm_adapter =  new UserMessagingSettingMapAdapter($mt);
        if ($this->settings = $umsm_adapter->getRecord(array("map_id"=>$message_data['user_messaging_setting_map_id']))){
             ;
        }else{
            throw new ApplePushNotificationServiceException();
        }

        //only for service test
        if(isset($message_data['force_prod_cert']) && $message_data['force_prod_cert'] === true){
            $this->settings['use_prod_cert'] = true;
        }
        
    }
}
class ApplePushNotificationServiceException extends Exception
{
    public function __construct($error_message, $error_code, $code = 100) {
        parent::__construct("Apple Push Message Failure: $error_message", $code);
    }
}
?>