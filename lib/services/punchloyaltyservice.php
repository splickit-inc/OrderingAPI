<?php
class PunchLoyaltyService extends SplickitLoyaltyService
{
    protected $punch_client_id;
    protected $punch_client_secret;
    protected $punch_service_url;
    protected $auth_user_info;

    function __construct()
    {
        $this->punch_client_id = getProperty('punch_client_id');
        $this->punch_client_secret = getProperty('punch_client_secret');
        $this->punch_service_url = getProperty('punch_service_url');
    }

    /*
     * THIS IS NEVER USED. ITS ONLY FOR CREATING USERS FOR TESTING PURPOSES
     */
    function createUserOnPunchSystemNeverUsed($user_data)
    {
        if (isProd()) {
            die("cant do this on production");
        }
        $uri = "/api/auth/customers.json?client=".$this->punch_client_id;
        $user['user'] = $user_data;
        return $this->curlIt($uri,$user);
    }

    function setAuthUserInfo($raw_return)
    {
        myerror_log("THE RAW RETURN IS: $raw_return",5);
        $this->auth_user_info = json_decode($raw_return,true);
        logData($this->auth_user_info,"PUnch User Info",5);
    }

    function getAuthUserInfo()
    {
        return $this->auth_user_info;
    }


    function isValidAuthResponse($results)
    {
        if ($results['http_code'] == 200) {
            $this->setAuthUserInfo($results['raw_result']);
            return true;
        }
        return false;
    }

    function isValidAuthenticationToken($punch_authentication_token)
    {
        return $this->isValidAuthResponse($this->authenticate($punch_authentication_token));
    }

    function authenticate($punch_authentication_token)
    {
        $uri = "/api/auth/sso";
        $data['client'] = $this->punch_client_id;
        $data['security_token'] = $punch_authentication_token;
        return $this->curlIt($uri,$data,$headers,$user_password);
    }

    function rewardPoints($punch_order_array,$punch_user_authentication_token)
    {
        $uri = "/api/auth/checkins/online_order";
        $user_password = "$punch_user_authentication_token:x";
        return $this->curlIt($uri,$punch_order_array,$headers,$user_password);
    }

    function redemptionPurchase($punch_order_array,$punch_user_authentication_token)
    {
        $uri = "/api/auth/redemptions/online_order";
        $user_password = "$punch_user_authentication_token:x";
        return $this->curlIt($uri,$punch_order_array,$headers,$user_password);
    }

    function voidRedemption($data,$punch_user_authentication_token)
    {
        $uri = "/api/auth/redemptions";
        $user_password = "$punch_user_authentication_token:x";
        $data['client'] = $this->punch_client_id;
        return $this->curlIt($uri,$data,$headers,$user_password,'delete');
    }

    function getBalance($punch_user_authentication_token)
    {
        $uri = "/api/auth/checkins/balance?client=".$this->punch_client_id;
        $user_password = "$punch_user_authentication_token:x";
        //$data['client'] = $this->punch_client_id;
        //$data['authentication_token'] = $punch_user_authentication_token;
        $response = $this->curlIt($uri,$data,$headers,$user_password);
        if ($raw_result = $response['raw_result']) {
            $response_array = json_decode($raw_result,true);
            logData($response_array,"Punch Balance Response",3);
            return $response_array;
        }
        myerror_log("SOME ERROR THROWN TRYING TO GET PUNCH POINTS: ".$response['error']);


    }

    function generateSignature($uri,$json_payload)
    {
        return hash_hmac("sha1", $uri.$json_payload, $this->punch_client_secret);
    }

    function curlIt($uri,$data,$headers,$user_password,$method = 'GET')
    {
        $headers = $headers == null ? array() : $headers;
        if ($data) {
            $json = json_encode($data);
        }
        $x_pch_digest = $this->generateSignature($uri,$json);
        $url = $this->punch_service_url.$uri;
        $headers[] = 'x-pch-digest: '.$x_pch_digest;
        $this->curl_response = PunchCurl::curlIt($url,$json,$headers,$user_password,$method);
        return $this->curl_response;
    }

    function getPunchClientId()
    {
        return $this->punch_client_id;
    }

}
?>
