<?php
class FacebookCurl extends SplickitCurl
{

    static function curlIt($url,$data)
    {
        if (substr_count($url,'access_token=XXXXXXXX')) {
            $response['raw_result'] = '{"error":{"message":"An active access token must be used to query information about the current user.","type":"OAuthException","code":2500,"fbtrace_id":"CTTVQd1Vcvh"}}';
            $response['http_code'] = 400;
            $response['curl_info']['http_code'] = 400;
        } else {
            $phone = rand(1111111111,9999999999);
            if (isset($_SERVER['facebook_test_data'])) {
                $response['raw_result'] = $_SERVER['facebook_test_data'];
            } else {
                //$response['raw_result'] = '{"first_name": "Bob","last_name": "Roberts","email": "sumdumguy\u0040splickit.com", "phone": "'.$phone.'"}';
                $response['raw_result'] = '{"id":"10155980520911972","name":"Bob Robers","email":"sumdumguy\u0040splickit.com"}';
            }
            $response['http_code'] = 200;
            $response['curl_info']['http_code'] = 200;
            $response['error'] = null;
            $response['error_no'] = null;
        }
        return $response;
    }
}
?>