<?php

class PushAndroidService extends SplickitService
{
    var $service_name = "Android Push Message";
    var $current_registration_id = '';
    const AndroidPushNotificationURL = "https://fcm.googleapis.com/fcm/send";
    

    function __construct($data)
    {
        parent::__construct($data);
    }

    /**
     * @param $title of message will be send
     * @param $message the message will be send
     * @return bool returns true if message has been sent
     * @throws UnsuccessfulAndroidPushException if can not sent the notification
     */
    function send($data)
    {
        myerror_log("INFO! Android push notifications sending messages to users -> ".$this->current_registration_id);
        myerror_log("INFO! Android push notifications message to send -> ".$message);

        if (empty($this->current_registration_id)) {
            return false;
        }

        $fields = array(
            'registration_ids'  => array($this->current_registration_id),
            'data'              => $data
        );

        logData($message," Android pushing Message",5);

        $response = PushAndroidCurl::curlIt(self::AndroidPushNotificationURL, $fields);
        $this->data = $fields;
        $this->curl_response = $response;
        $results_array = $this->processCurlResponse($response);
        $this->results_array = $results_array;
        if ($this->results_array['status'] == self::SUCCESS) {
            return $response;
        }
        throw new UnsuccessfulAndroidPushException($results_array['results'][0]['error']);
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

        $umsm =  new UserMessagingSettingMapAdapter($mt);
        if($message_setting = $umsm->getRecord(array("map_id"=>$message_data['user_messaging_setting_map_id']))){
            $this->current_registration_id = $message_setting['token'];
        }else{
            myerror_log("Missing User Message Settings Map for user: " . $message_data['user_id']);
            throw new BadConfigurationAndroidPushException("Missing User Message Settings Map for user: " . $message_data['user_id']);
        }

        if (isset($message_data['test']) && $message_data['test'] == "true") {
            $this->current_registration_id = $this->getTestTokenForAndroidSend();
        }

    }
}
class UnsuccessfulAndroidPushException extends Exception
{
    public function __construct($error_message, $error_code, $code = 100) {
        parent::__construct("Android Push Message Failure: $error_message", $code);
    }
}

class BadConfigurationAndroidPushException extends Exception
{
    public function __construct($error_message, $error_code, $code = 100) {
        parent::__construct("Android Push Message Failure: $error_message", $code);
    }
}
?>