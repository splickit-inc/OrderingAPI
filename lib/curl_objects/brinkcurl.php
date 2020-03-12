<?php
class BrinkCurl extends SplickitCurl
{
    static function curlIt($url,$xml,$headers)
    {
        $service_name = 'Brink Ordering Curl';
        if ($ch = curl_init($url)) {
            curl_setopt($ch, CURLOPT_VERBOSE,0);
            if ($xml) {
                $method = 'POST';
                curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
                curl_setopt($ch, CURLOPT_POST, 1);
                $headers[] = 'Content-type: text/xml; charset=utf-8';
                $headers[] = 'Content-Length: '.strlen($xml);
                if (substr_count($xml,'CalculateOrder') > 0) {
                    myerror_log("Setting brink curl timout to 5 seconds");
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                }

            } else {
                $method = 'GET';
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            logCurl($url,$method,null,$headers,$xml);
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