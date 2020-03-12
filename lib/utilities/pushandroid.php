<?php

/*
$authCode = googleAuthenticate();

$deviceRegistrationId='APA91bGkhZvJJJeO9GNsoy_3VuGlrp3iabLDvs9uUaHwOTVxWlN8_m0ywhGrKuwNsLGYk9LGS3ffywXrsUxXxhpfLiNZFpv1OQbyejvJ-uQ_RCKPpv5Y5kM';
$msgType="message";
$messageText = 'HI dave!';
sendMessageToPhone($authCode, $deviceRegistrationId, $msgType, $messageText);
die;
*/

Class PushAndroid
{
	private $username = 'splickit.push@gmail.com';
	private $password = 'goSplick!';
	private $source = 'DROID';
//	private $service = 'ac2dm';
	private $push_service;
	private $account_type = 'HOSTED_OR_GOOGLE';
	private $google_push_url = "https://www.google.com/accounts/ClientLogin";
	private $google_auth_code;
	private $is_a_test_push;
	
	private $mimetypes;

	function PushAndroid($skin_id,$mimetypes,$is_a_test_push = false)
	{
//	    die("DONT USE THIS OBJECT");
//		$this->is_a_test_push = $is_a_test_push;
//		$this->mimetypes = $mimetypes;
//		$this->skin_id = $skin_id;
//		$this->push_service = new PushAndroidService();
	}
	
//	function push_message_sql($message)
//	{
//		/*code here to get users*/;
//		$this->push_message($users, $message);
//	}
	


	static function xsendMessageToPhoneTest($auth_code, $deviceRegistrationId, $msgType, $messageText, $message_title='Mobile Ordering')
	{
		if (isLaptop())
		{
			$insert_id = AndroidTestPushDataAdapter::insertRecord($message_title, $messageText);
			$response = "successfull dummy push to android user:insert_id=$insert_id";
			myerror_logging(3,"response from test android is: ".$response);
			usleep(250000);
			return $response;
		}
        myerror_log("we are in the test android send method");
        //$url = "https://lweb-LoadBalancer-1356650055.us-east-1.elb.amazonaws.com/app2/phone/dummyandroidpushcall";
        $url = "https://lweb-loadbalancer-1356650055.us-east-1.elb.amazonaws.com/";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $response = "successfull dummy push to android user";
        curl_close($ch);
        myerror_logging(3,"response from test android is: ".$response);
        return $response;
	}
	
	static function xsendMessageToPhone($auth_code, $deviceRegistrationId, $msgType, $message_text, $message_title='Mobile Ordering')
	{
        if (isProd()) {
			$push_android = new PushAndroid(1, $mimetypes);
			$result = $push_android->push_service->send($deviceRegistrationId, $message_title, $message_text);
        	myerror_logging(4,"we have sending the push message ". $result['status']);

        } else if (isLaptop()) {
        	$url = "http://localhost:8888/app2/phone/dummyandroidpushcall";
        	myerror_log("we are in the dummy android send for laptop"); 			
        	// need to totally fake the sending
        	$atpd_adapter = new AndroidTestPushDataAdapter($mimetypes);
        	$atpd_resource = Resource::createByData($atpd_adapter, array("message_title"=>$message_title,"message_text"=>$message_text));
        	$insert_id = $atpd_resource->id;
        	$body = "successfull dummy push to android user:insert_id=$insert_id";
        	return $body;
        
        } else {
        	myerror_log("we are in the dummy android send");
        	$url = "https://tweb03.splickit.com/app2/phone/dummyandroidpushcall";
        	
        }
	}
	
	static function xdummyAndroidPush()
	{
		if (isLaptop()) {
			$insert_id = AndroidTestPushDataAdapter::insertRecord($_POST['data_title'], $_POST['data_message']);
			$body = "successfull dummy push to android user:insert_id=$insert_id";
		} else {
			sleep(1);
			$body = "successfull dummy push to android user";
		}
		respondOkWithTextPlainbody($body);		
	}
}