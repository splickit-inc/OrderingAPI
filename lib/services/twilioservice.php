<?php

class TwilioService extends SplickitService
{
    var $service_name = "twilio";
    var $account_sid;

    function __construct()
    {
        parent::__construct($data);
        $this->account_sid = getProperty('twilio_account_sid');
    }

    /**
     * @param $sms_delivery_nos delivery numbers will be send the message
     * @param $message the message will be send
     * @return bool returns true if message has been sent
     */
    static function sendSmsMessage($sms_delivery_nos, $message)
    {
        $twilio_service = new TwilioService();
        return $twilio_service->doSMSMessage($sms_delivery_nos, $message);
    }

    static function InitializeOutboundCall($phone_number, $message_response_url)
    {
        $twilio_service = new TwilioService();
        return $twilio_service->doOutboundCall($phone_number,$message_response_url);
    }

    function send($sms_delivery_no, $url, $data)
    {
        if (empty($sms_delivery_no)) {
            return false;
        }
        if (empty($data)) {
            return false;
        }
        logData($data,"TWilio post",5);
        $data['From'] = getProperty("twilio_from_number");
        $data['To'] = '+1' . $sms_delivery_no;
        $response = TwilioCurl::curlIt($url,$data);
        $this->data = $data;
        $this->curl_response = $response;
        $results_array = $this->processCurlResponse($response);
        if ($this->isSuccessfulResponse($results_array)) {
            return $results_array;
        }
        throw new UnsuccessfulIvrPushException($results_array['message'], $results_array['code']);
    }

    function doSMSMessage($phone_number,$message_text)
    {
        $data = array();
        $data['Body'] = substr($message_text,0,159);
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $this->account_sid . '/SMS/Messages.json';
        $results_as_array = $this->send($phone_number,$url,$data);
        return array("response_id"=>$results_as_array['sid'],"response_text"=>"queued");
    }

    function doOutboundCall($phone_number, $message_response_url)
    {
        $twilio_domain ="https://api.twilio.com/2010-04-01/Accounts/";
        $url = $twilio_domain . $this->account_sid .'/Calls.json' ;
        $data['Url'] = $message_response_url;

        return $this->send($phone_number,$url,$data);

    }

    function isSuccessfulResponse($response)
    {
        return $response['twilio_status'] == 'queued';
    }

}
class UnsuccessfulIvrPushException extends Exception
{
    public function __construct($error_message, $error_code, $code = 100) {
        parent::__construct("IVR message Falure: $error_message", $code);
    }
}
?>