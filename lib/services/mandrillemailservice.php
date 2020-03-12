<?php
class MandrillEmailService
{
	static function sendEmail($data)
	{
		$data_string = json_encode($data);
		
		$url = getProperty('mandrill_url');
		
		$ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST,'TLSv1');
		if (isLaptop())
        	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
        );
		
		myerror_logging(1,"about to curl to mail chimp");        
		$result = curl_exec($ch);
		curl_close($ch);
		myerror_logging(1,"mail chimp result: ".$result);
		return $result;	
	}
}
?>