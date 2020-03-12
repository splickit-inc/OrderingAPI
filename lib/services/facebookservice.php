<?php
class FacebookService extends SplickitService
{
    var $good_codes = array("302"=>1);
    var $url;
    var $facebook_data = array();
    var $response_array = array();
    var $error = array();

    function __construct()
    {
        $this->service_name = 'facebook';
        $this->url = getProperty('facebook_url');
    }

    function getError()
    {
        return $this->error;
    }

    function authenticateToken($token)
    {
        $this->setUrlWithToken($token);
        $data = null;
        if ($response = $this->send(null)) {
            logData($response,"facebook auth response",5);
            if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($response,'error')) {
                myerror_log("we have a facebook error: " . $response['error']);
                return false;
            } else if ($response['http_code'] != 200) {
                $data = $this->processFacebookErrorResponse($response);
                myerror_log("we have a facebook error: " . $data['error']['message']);
                return false;
            } else {
                $data = $this->processCurlResponse($response);
                $name = $data['name'];
                $n = explode(' ',$name);
                $data['last_name'] = array_pop($n);
                $data['first_name'] = implode(' ',$n);
                $data['facebook_user_id'] = $data['id'];
                logData($data,"facebook data",5);
                return $data;
            }
        } else {
            return false;
        }
    }

    function processFacebookErrorResponse($response)
    {
        $result = array();
        if ($raw_return = $this->getRawResponse($response)) {
            $result = $this->processRawReturn($raw_return);
        }
        $result['http_code'] = $response['http_code'];
        $result['status'] = $this->getSuccessStatusFromFullResult($result);
        logData($result, "Curl Response");
        myerror_log("http response code: ".intval($response['http_code']));
        return $result;
    }

    function setUrlWithToken($token)
    {
       $this->url = $this->url.'?fields=id%2Cname%2Cemail&access_token='.$token;
    }

    function send($data, $resource = null)
    {
        $response = FacebookCurl::curlIt($this->url, $data);
        $this->response_array = $response;
        return $response;
    }


    function getFacebookData()
    {
        return $this->facebook_data;
    }

}

?>
