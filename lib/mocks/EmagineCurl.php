<?php

/**
 * Created by PhpStorm.
 * User: Elvis
 * Date: 9/29/16
 * Time: 5:17 PM
 */
class EmagineCurl extends SplickitCurl
{
    static function curlIt($url, $data)
    {
        $service_name = 'Emagine';
        if(strpos($data, 'qwertyu234567') > 0){
            $response = array("Ok" => false, "failureCode" => 45897458, "failureMessage" => "Error message from Emagine", "order_uid" => "");
            $response['http_code'] = 200;
            $response['raw_result'] = '{"Ok":false,"failureCode":45897458,"failureMessage":"Error message from Emagine","order_uid":""}';

            $response['curl_info']['url'] = $url;
        } else {
            $response = array("ok" => true, "failureCode" => 0, "failureMessage" => "", "order_uid" => "qs1tyfwnd3hmthvdzx4pfzrf0i");
            $response['http_code'] = 200;
            $response['raw_result'] = '{"ok":true,"failureCode":0,"failureMessage":"","order_uid":"qs1tyfwnd3hmthvdzx4pfzrf0i"}';

            $response['curl_info']['url'] = $url;
        }

        return $response;
    }
}