<?php
class LevelupPaymentCurl extends SplickitCurl
{
    static function curlIt($url, $data, $header_auth_tokens)
    {
        $service_name = 'LevelupPayment';
        if ($_SERVER['NO_MOCKS']) {
            return LevelupPaymentCurl::curlItNoMock($url, $data, $username_password);
        }

        $spend_amount = $data['order']['spend_amount'];
        if (strpos($url,'refund') > 0) {
            $start = strpos($url,'orders/') + 7;
            $end = strpos($url,'/refund');
            $length = $end - $start;
            $uuid = substr($url,$start,$length);
            if (false) {
                // already refunded error
                $json_result = '[{"error":{"message":"The refund was unsuccessful. Perhaps this order has already been refunded or was unsuccessful.","object":"order","property":"base"}}]';
                $http_code = '';
            } else if (false) {
                // uuid doesn't exist
                $http_code = 404;
            } else {
                $json_result = '{"order":{"created_at":"2015-04-14T14:23:14-04:00","location_id":83,"loyalty_id":21,"refunded_at":"2015-04-14T14:25:37-04:00","user_display_name":"Splick It One O.","uuid":"'.$uuid.'","earn_amount":0,"merchant_funded_credit_amount":0,"spend_amount":1538,"tip_amount":0,"total_amount":1538}}';
                $http_code = 200;
            }
        } else if (strpos($header_auth_tokens,'merchant=""') > 0) {
            $json_result = '{"error": {"message": "Not authorized to create orders for this merchant.","object": "order","property": "merchant_token","code": "not_authorized"}}';
            $http_code = 401;
        } else if (strpos($header_auth_tokens,'user=""') > 0) {
            $json_result = '{"error": {"message": "Not authorized to create orders for this user.","object": "order","property": "user_token","code": "not_authorized"}}';
            $http_code = 401;
        } else if ($data['order']['location_id'] < 1 || strpos($header_auth_tokens,'NOLOCATIONID') > 0) {
            $json_result = '{"error": {"object": "order","property": "location_id","code": "not_found","message": "Location cant be blank"}}';
            $http_code = 422;
        } else if ($spend_amount < 2000) {
            $json_result = '{"order": {"uuid": "1a2b3c4d5e6f7g8h9i9h8g7f6e5d4c3b2a1","spend_amount": '.$spend_amount.',"tip_amount": 0,"total_amount": '.$spend_amount.'}}';
            $http_code = 200;
        } else {
            $json_result = '{"error": {"message": "Sorry. We cannot charge the credit card at this time.","object": "order","property": "base"}}';
            $http_code = 422;
        }
        $response['raw_result'] = $json_result;
        $response['error'] = $error;
        $response['error_no'] = $error_no;
        $response['curl_info'] = $curl_info;
        $response['http_code'] = $http_code;
        return $response;

    }

    static function curlItNoMock($url,$data,$authorization_header)
    {
        $service_name = 'LevelupPayment';
        setSessionProperty('use_TLSv1', true);
        if ($ch = curl_init($url))
        {
            curl_setopt($ch, CURLOPT_URL, $url);
            if ($data)
            {
                $json_data = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                curl_setopt($ch, CURLOPT_POST, 1);

                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Accept: application/json',
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($json_data),
                        $authorization_header
                    )
                );
            }
            myerror_log("curl -X POST -v -H 'Accept: application/json' -H 'Content-Type: application/json' -H '".$authorization_header."' -d '$json_data' $url");
            //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            $response = parent::curlIt($ch);
            curl_close($curl);
        } else {
            $response['error'] = "FAILURE. Could not connect to ".$service_name;
            myerror_log("ERROR!  could not connect to ".$service_name);
        }
        return $response;
    }
}
?>
