<?php
class XoikosCurl extends SplickitCurl
{
    static function curlIt($url,$xml,$headers)
    {
        $service_name = 'Xoikos Ordering Curl';
        if ($ch = curl_init($url)) {
            if ($xml) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
                $method = 'POST';
                curl_setopt($ch, CURLOPT_POST, 1);
                $headers[] = 'Content-Type: text/xml; charset=utf-8';
                $headers[] = 'Content-Length: '.strlen($xml);
                if (substr_count($xml,'isStoreActive') > 0) {
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                } else {
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                }
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