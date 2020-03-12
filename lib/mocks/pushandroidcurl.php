<?php

class PushAndroidCurl extends SplickitCurl
{

    static function curlIt($url, $data)
    {
        if ($data['data']['message'] == 'failthismessage') {
            $raw_response = '{"multicast_id":9075546181009905511,"success":0,"failure":1,"canonical_ids":0,"results":[{"error":"SumBadError"}]}';
            $response['raw_result'] = $raw_response;
            $response['http_code'] = 200;
        } else {
            $raw_response = '{"multicast_id":6895728910369106598,"success":1,"failure":0,"canonical_ids":0,"results":[{"message_id":"0:1475695042939132%d245f8c1f9fd7ecd"}]}';
            $response['raw_result'] = $raw_response;
            $response['http_code'] = 200;
        }
        return $response;
    }
}
?>