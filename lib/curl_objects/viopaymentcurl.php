<?php
class VioPaymentCurl extends SplickitCurl
{
	var $user_password = "";
	static function curlIt($url,$data,$username_password)
	{
		$service_name = 'VioPayment';
		setSessionProperty('use_TLSv1', true);
		if ($ch = curl_init($url))
		{		
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_USERPWD, "$username_password");
            if ($data['action'] == 'delete') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            } else if ($data) {
				if ($data['payment']['kind'] == 'void' || $data['payment']['kind'] == 'refund' ) {
					curl_setopt($ch, CURLOPT_TIMEOUT, 90);
				} else if ($data['payment']['kind'] == 'capture') {
					curl_setopt($ch, CURLOPT_TIMEOUT, 150);
				}
				if ($data['payment']['kind'] == 'void') {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                    $method = 'PUT';
                } else if ($data['action'] == 'put') {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                    $method = 'PUT';
                    unset($data['action']);
				} else {
                    $method = 'POST';
					curl_setopt($ch, CURLOPT_POST, 1);
				}
                $json_data = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                $headers = array(
                    'Content-type: application/json',
                    'Content-Length: ' . strlen($json_data));
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			}
            //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            logCurl($url,$method,$username_password,$headers,$json_data);
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