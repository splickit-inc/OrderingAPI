<?php

/**
 * Created by PhpStorm.
 * User: Elvis
 * Date: 8/23/16
 * Time: 11:36 AM
 */
class EmagineCurl extends SplickitCurl
{
    static function curlIt($url, $json, $headers)
    {
        $service_name = 'Emagine Ordering Curl';
        if ($ch = curl_init($url)) {
            $method = 'GET';
            curl_setopt($ch, CURLOPT_VERBOSE, 0);
            if ($json) {
                // replace nulls with empty
                $json = str_replace("null", '""', $json);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                curl_setopt($ch, CURLOPT_POST, 1);
                $method = 'POST';
                $headers[] = 'Content-type: application/json;';
                $headers[] = 'Content-Length: ' . strlen($json);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            logCurl($url, $method, $user_passowrd, $headers, $json);
            $response = parent::curlIt($ch);
            curl_close($ch);
        } else {
            $response['error'] = "FAILURE. Could not connect to " . $service_name;
            myerror_log("ERROR!  could not connect to " . $service_name);
        }
        return $response;
    }
}