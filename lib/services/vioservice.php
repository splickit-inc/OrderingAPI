<?php

class VioService extends SplickitService
{
    private $username_password;
    private $send_url;

    function __construct($data)
    {
        parent::__construct($data);
        $this->username_password  = getProperty("vio_admin_username_password");
        $this->send_url = getProperty('vio_url');
    }

    function createMerchantAccount($data)
    {
        $url = $this->send_url.'vioinstant';
        $response = $this->curlIt($url, $data);
        return $response;
    }

    function curlIt($endpoint,$data)
    {
        myerror_logging(3,"about to curl VIO to endpoint: ".$endpoint);
        logData($data, "vio info",5);
        $response = VioPaymentCurl::curlIt($endpoint,$data, $this->username_password);
        $this->curl_response = $response;
        return $this->processCurlResponse($response);
    }

    function processCurlResponse($response)
    {
        //<html><body><h1>504 Gateway Time-out</h1>The server didn't respond in time.</body></html>
        $result_array = parent::processCurlResponse($response);
        $this->response_in_data_format = $result_array;
        return $result_array;
    }

    function processRawReturn($raw_return)
    {
        $return_array = json_decode($raw_return,true);
        $relavant_payload = $return_array['data'];
        if (isset($return_array['errors']) && sizeof($return_array['errors']) > 0) {
            $relavant_payload['error'] = $return_array['errors'];
        }
        return $relavant_payload;
    }




}
