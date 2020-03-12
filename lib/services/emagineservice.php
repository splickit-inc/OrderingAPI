<?php

/**
 * Created by PhpStorm.
 * User: Elvis
 * Date: 8/23/16
 * Time: 9:42 AM
 */
class EmagineService extends SplickitService
{
    private $base_service_url;
    private $authToken;
    private $locationId;

    private $headers = array();
    
    function __construct($data)
    {
        parent::__construct($data);
        $this->base_service_url = getProperty('emagine_service_url');
        $this->current_end_point = $this->base_service_url . "/splickit";

    }

    function setStoreData($data)
    {
        if ($data['merchant_id']) {
            $map_data = MerchantEmagineInfoMapsAdapter::staticGetRecord(array("merchant_id"=>$data['merchant_id']), 'MerchantEmagineInfoMapsAdapter');
            $this->locationId = $map_data['location'];
            $this->authToken = $map_data['token'];
            $this->headers = array(
                'location' => $this->locationId,
                'authToken' => $this->authToken
            );
        }

    }

    function send($data){

        $response = $this->processCurlResponse(
            EmagineCurl::curlIt($this->current_end_point, $data, $this->headers)
        );
        
        if ($response['status'] == 'success') {
            return $response;
        }
        throw new UnsuccessfulEmaginePushException($response['error'], $response['error_no']);
    }

    function getHeaders(){
        return $this->headers;
    }

    function processCurlResponse($response)
    {
        $result = array();
        if ($raw_return = $this->getRawResponse($response)) {
            $result = array_change_key_case($this->processRawReturn($raw_return));
            $result['raw_result'] = $raw_return;
        }
        if ( $result['ok'] === false ) {
            $result['error'] = $result['failuremessage'];
            $result['error_no'] = $result['failurecode'];
        }

        $result['status'] = $this->isSuccessfulResponse($result) ? 'success' : 'failure';
        $result['http_code'] = $response['http_code'];
        logData($result, "Curl Response",3);
        myerror_log("http response code: ".intval($response['http_code']));
        return $result;
    }

    function isSuccessfulResponse($response){
        return isset($response['ok']) && $response['ok'] && isset($response['order_uid']) ;
    }
}

class UnsuccessfulEmaginePushException extends Exception
{
    public function __construct($error_message, $emagien_error_code, $code = 100)
    {
        parent::__construct("Emagine message failure: '$error_message'. Emagine Error Code: $emagien_error_code", $code);
    }
}

class InvalidEmagineConfigurationException extends Exception
{
    public function __construct($error_message, $code = 100)
    {
        parent::__construct("Emagine message failure: '$error_message'. Emagine Error Code: $code", $code);
    }
}