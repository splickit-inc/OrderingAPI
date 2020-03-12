<?php
class FacebookCurl extends SplickitCurl
{

    static function curlIt($url,$data)
    {
        $service_name = 'Facebook';
        $json = null;
        $headers = [];
        $user_password = null;
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST,'TLSv1');

        $response = curl_exec( $ch );
        curl_close( $ch );

        if ($ch = curl_init($url)) {
            $method = 'GET';
            curl_setopt($ch, CURLOPT_VERBOSE, 1);
            if ($data) {
                // replace nulls with empty
                $json = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                curl_setopt($ch, CURLOPT_POST, 1);
                $method = 'POST';
                $headers[] = 'Content-type: application/json;';
                $headers[] = 'Content-Length: ' . strlen($json);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            logCurl($url, $method, $user_password, $headers, $json);
            $response = parent::curlIt($ch);
            curl_close($ch);
        } else {
            $response['error'] = "FAILURE. Could not connect to " . $service_name;
            myerror_log("ERROR!  could not connect to " . $service_name);
        }
        return $response;



/*  example response
        $phone = rand(1111111111,9999999999);
        $response['raw_result'] = '{"first_name": "Bob","last_name": "Roberts","email": "sumdumguy@dummy.com", "phone": "'.$phone.'"}';
        $response['http_code'] = 200;
        $response['curl_info']['http_code'] = 200;
        return $response;
*/
    }
}
?>