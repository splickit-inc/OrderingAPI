<?php

Class PushIphone
{
	
	private $apnsHost = 'gateway.push.apple.com';
	private $apnsPort = 2195;
	
	private $apnsHost_test = 'gateway.sandbox.push.apple.com';
	private $is_a_test_push;

	var $response = array();
	
	function PushIphone($skin_external_identifier,$mimetypes,$is_a_test_push = false)
	{
		$this->mimetypes = $mimetypes;
		$this->is_a_test_push = $is_a_test_push;
		$cert_file_name = $skin_external_identifier.'.pem';
		if (isTest() || isLaptop())
		{
			$cert_file_name = 'com.splickit.ordersandbox.pem'; // load testing
			//$cert_file_name = 'com.splickit.splickitbeta.pem'; // push testing
		}
		myerror_log("in push_iphone certificate file name is: ".$cert_file_name);
		$this->cert_file = $cert_file_name;
	}

	function push($user_messaging_records,$message)
	{

		if (sizeof($user_messaging_records) < 1)
		{
			myerror_log("ERROR!  NO USERS SELECTED FOR PUSH!");
			$this->response = array('error_no' => 500, 'error' => "ERROR!  NO USERS SELECTED FOR PUSH!", 'http_code' => 500);

			return false;
		}
		myerror_log("the size of hte records in push_iphone is: ".sizeof($user_messaging_records));
		
		$payload['aps'] = array('alert' => $message,'sound'=>'default');
		$payload = json_encode($payload);
		$streamContext = stream_context_create();

		$filename = 'lib/utilities/push_certificates/'.$this->cert_file;
		if (file_exists($filename)) {
		    myerror_log("The file $filename exists");
		} else {
		    myerror_log("ERROR!  The PEM file $filename does not exist!  CANT SEND PUSH MESSAGE");
			$this->response = array('error_no' => 500, 'error' => "ERROR!  The PEM file $filename does not exist!  CANT SEND PUSH MESSAGE", 'http_code' => 500);
		    MailIt::sendErrorEmail("ERROR!  The PEM file $filename does not exist!  CANT SEND PUSH MESSAGE", "ERROR!  The PEM file $filename does not exist!  CANT SEND PUSH MESSAGE");
		    
		    return false;		    
		}
		
		stream_context_set_option($streamContext, 'ssl', 'local_cert', 'lib/utilities/push_certificates/'.$this->cert_file);
				
		for( $z = 0; $z < 10; ++ $z ) {
				// prod test exception is done here.		
		    if( ($apns = $this->getApns($streamContext)) ) {
		        break;
		    }
		    myerror_log("unable to connect to apple. sleep for 2 seconds and try again");
		    sleep(2);
		}
		
		if ($apns)
			myerror_log("we have a valid connection to apple!");
		else
		{
			myerror_log("UNABLE TO CONNECT TO APPLE FOR PUSH MESSAGE!");
			$this->response = array('error_no' => 500, 'error' => "UNABLE TO CONNECT TO APPLE FOR PUSH MESSAGE!", 'http_code' => 500);
			MailIt::sendErrorEmail("UNABLE TO CONNECT TO APPLE TO SEND PUSH MESSAGES!", "NO iphone messages have been sent");
			return false;
		}

		// here is wehre we loop through all the user.  DO NOT CLOSE SOCKET utill you have looped throgh everyone with the fwrite
		$num_users_sent_to = 0;
		$fails = 0;
		$errors = array();
		$successes = array();
		foreach ($user_messaging_records as $record)
		{
			if ($this->is_a_test_push)
			{
				myerror_log("FAKING IT! iphone message pushed to user_id: ".$record['user_id']);
				$num_users_sent_to++;
				continue;
			}
			$device_token = $record['token'];
			myerror_log("token is: ".$device_token);
			$apnsMessage = chr(0) . chr(0) . chr(32) . pack('H*', str_replace(' ', '', $device_token)) . chr(0) . chr(strlen($payload)) . $payload;
			$i = 0;
			while ( ! fwrite($apns, $apnsMessage) && $i < 100)
			{
				myerror_log("trying it again");
				$error_array = error_get_last();
				if ($error_array['message'] == 'fwrite(): SSL: Broken pipe')
					$apns = $this->getApns($streamContext);
				//$string = print_r($error_array,true);
				$string = $error_array['type'].'  '.$error_array['message'];
				myerror_log($string);
				$i++;
			}
			if ($i > 99)
			{
				myerror_log("ERROR! Message not pushed to user is: ".$record['user_id']."  Response: ".$string);
				$errors[] = "ERROR! Message not pushed to user is: ".$record['user_id']."  Response: ".$string;
				$fails++;
			}
			else
			{
				myerror_log("iphone message pushed to user_id: ".$record['user_id']);
				$successes[] = "iphone message pushed to user_id: ".$record['user_id'];
				$num_users_sent_to++;
			} 							
		}
		myerror_log("RESULTS OF PUSH MESSAGE IS: successes=$num_users_sent_to     fails=$fails");
		$this->response = array(
			'http_code' => 200,
			'raw_result' => json_encode(
				array(
					'success' => true,
					'message' => "RESULTS OF PUSH MESSAGE IS: successes=$num_users_sent_to     fails=$fails",
					'fails' => implode('; ', $errors),
					'successes' => implode(';', $successes)
				)
			)
		);

		fclose($apns);
		return true;	
	}
		
	private function getApns($streamContext)
	{
		// always use apple production for testing real pushes
		if (isProd())
			$apns = stream_socket_client('ssl://' . $this->apnsHost . ':' . $this->apnsPort, $error, $errorString, 2, STREAM_CLIENT_CONNECT, $streamContext);
		else
			$apns = stream_socket_client('ssl://'.$this->apnsHost_test.':' . $this->apnsPort, $error, $errorString, 2, STREAM_CLIENT_CONNECT, $streamContext);
			
		return $apns;
	}

	//only for test service - remove
	function forceProdCertForTest(){
		$this->apnsHost_test = $this->apnsHost;
		$this->cert_file = 'com.splickit.pitapit.pem';
	}

} // class PushIphone
?>