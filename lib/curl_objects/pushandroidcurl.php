<?php

class PushAndroidCurl extends SplickitCurl
{

    static function curlIt($url, $data)
    {
        myerror_log("INFO! Android push notifications connect to -> ".$url);
        myerror_log("INFO! Android push notifications data -> ".$data);

        $gsm_api_key = getProperty('gsm_api_key');
        $service_name = 'Push Message Android';
        if ($ch = curl_init($url)) {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_VERBOSE,0);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_POST, 1);
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Authorization: key=' . $gsm_api_key;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            logCurl($url, 'POST', null, $headers, json_encode($data));
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