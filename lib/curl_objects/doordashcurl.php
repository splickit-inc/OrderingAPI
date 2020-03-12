<?php
class DoordashCurl extends SplickitCurl
{
    static function curlIt($url,$data)
    {
        $service_name = 'Doordash';
        if ($ch = curl_init($url))
        {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_VERBOSE,0);
            $headers = [];
            if ($data) {
                $method = 'POST';
                $json_data = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                curl_setopt($ch, CURLOPT_POST, 1);
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Content-Length: ' . strlen($json_data);
            } else if (substr_count($url,'cancel') > 0) {
                $method = 'PUT';
                curl_setopt($ch, CURLOPT_PUT, 1);
            } else {
                $method = 'GET';
            }
            $api_key = getProperty('doordash_api_key');
            $headers[] = "Authorization: Bearer $api_key";
            curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
            logCurl($url,$method,null,$headers,$json_data);
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