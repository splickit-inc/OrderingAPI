<?php

/**
 * Created by PhpStorm.
 * User: Elvis
 * Date: 8/12/16
 * Time: 8:29 AM
 */
class MailChimpCurl extends SplickitCurl
{
    static function curlIt($url, $data, $headers)
    {
        $service_name = 'Mail Chimp';
        if ($ch = curl_init($url))
        {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_VERBOSE,0);
            if ($data)
            {
                $json_data = json_encode($data);
                $headers[] = 'Content-type: application/json';
                $headers[] = 'Content-Length: ' . strlen($json_data);

                curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            $response = parent::curlIt($ch);
            curl_close($ch);
        } else {
            $response['error'] = "FAILURE. Could not connect to ".$service_name;
            myerror_log("ERROR!  could not connect to ".$service_name);
        }
        return $response;
    }

}