<?php

class TwilioCurl extends SplickitCurl
{

    static function curlIt($url,$data)
    {
        $account_sid = getProperty('twilio_account_sid');
        $account_auth_token = getProperty('twilio_account_auth_token');

        $service_name = 'Twilio';
        if ($ch = curl_init($url)) {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_VERBOSE,0);
            curl_setopt($ch, CURLOPT_USERPWD, "$account_sid:$account_auth_token");
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            if ($data) {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            logCurl($url,'POST',"$account_sid:$account_auth_token",$headers,$data);
            $response = parent::curlIt($ch);
            curl_close($ch);
        } else {
            $response['error'] = "FAILURE. Could not connect to ".$service_name;
            myerror_log("ERROR!  could not connect to ".$service_name);
        }
        return $response;
    }

}
?>