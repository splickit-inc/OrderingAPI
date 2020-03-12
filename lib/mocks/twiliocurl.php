<?php
class TwilioCurl extends SplickitCurl
{

    static function curlIt($url,$data)
    {

        if ($data['To'] == '11234') {
            $response['raw_result'] = '{"code": 21211, "message": "The \'To\' number 11234 is not a valid phone number.", "more_info": "https://www.twilio.com/docs/errors/21211", "status": 400}';
            $response['http_code'] = 400;
            $response['curl_info']['http_code'] = 400;
        } else {
            $queued_id = generateCode(10);
            $response['raw_result'] = '{"sid": "SM3d6e3039b8404a6e9b99408d2292c1e2", "date_created": "Fri, 16 Oct 2015 18:22:17 +0000", "date_updated": "Fri, 16 Oct 2015 18:22:17 +0000", "date_sent": null, "account_sid": "AC2ccdc203cfaf99b028b797ad6d178af1", "to": "'.$data['To'].'", "from": "'.$data['From'].'", "body": "'.$data['Body'].'", "status": "queued", "direction": "outbound-api", "api_version": "2010-04-01", "price": null, "price_unit": "USD", "uri": "/2010-04-01/Accounts/AC2ccdc203cfaf99b028b797ad6d178af1/SMS/Messages/SM3d6e3039b8404a6e9b99408d2292c1e2.json", "num_segments": "1"}';
            $response['http_code'] = 200;
            $response['curl_info']['http_code'] = 200;
        }
        return $response;
    }
}
?>