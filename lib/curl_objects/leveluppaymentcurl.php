<?php
class LevelupPaymentCurl extends SplickitCurl
{
    static function curlIt($url,$data,$authorization_header)
    {
        $service_name = 'LevelupPayment';
        setSessionProperty('use_TLSv1', true);
        if ($ch = curl_init($url)) {

            $headers =  array('Accept: application/json', 'Content-Type: application/json',$authorization_header);
            curl_setopt($ch, CURLOPT_POST, 1);
            $method = 'GET';
            if ($data) {
                $json_data = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                $headers[] = 'Content-Length: ' . strlen($json_data);
                $method = 'POST';
            }
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