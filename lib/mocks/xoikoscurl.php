<?php
class XoikosCurl extends SplickitCurl
{
    static function curlIt($url,$xml,$headers)
    {
        $service_name = 'Xoikos Ordering Curl';

        if (substr_count($xml,'isStoreActive') > 0) {
            myerror_log("setting time out to 5 seconds"); // - set timeout to 5 seconds
        }

        if (substr_count($xml,'<store>22222</store>') > 0) {
            $response['raw_result'] = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><isStoreActiveResponse xmlns="http://www.subshop.com/"><isStoreActiveResult>true</isStoreActiveResult></isStoreActiveResponse></soap:Body></soap:Envelope>';
            $response['http_code'] = 200;
        } else {
            $response['raw_result'] = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><isStoreActiveResponse xmlns="http://www.subshop.com/"><isStoreActiveResult>false</isStoreActiveResult></isStoreActiveResponse></soap:Body></soap:Envelope>';
            $response['http_code'] = 200;
        }

        return $response;
    }
}
?>