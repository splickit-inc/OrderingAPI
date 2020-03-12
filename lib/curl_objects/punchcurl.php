<?php
class PunchCurl extends SplickitCurl
{
    static function curlIt($url,$json,$headers,$user_password,$method = 'GET')
    {
        $service_name = 'Punch Loyalty Curl';
        if ($ch = curl_init($url))
        {
            curl_setopt($ch, CURLOPT_VERBOSE,0);
            if ($json) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                $headers[] = 'Content-type: application/json';
                $headers[] = 'Accept: application/json';
                $headers[] = 'Cache-Control: no-cache';
                $headers[] = 'Content-Length: '.strlen($json);
                if (strtolower($method) == 'delete') {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                } else if (strtolower($method) == 'put') {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                } else {
                    $method = 'POST';
                    curl_setopt($ch, CURLOPT_POST, 1);
                }
            }
            if ($user_password) {
                curl_setopt($ch, CURLOPT_USERPWD, $user_password);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            logCurl($url,strtoupper($method),$user_password,$headers,$json);
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