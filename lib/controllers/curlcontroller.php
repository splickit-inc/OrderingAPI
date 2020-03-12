<?php
class CurlController extends MessageController
{
	protected $format = 'C';
	protected $format_name = 'curl';
	
	function CurlController($mt,$u,&$r,$l = 0)
	{
		parent::MessageController($mt,$u,$r,$l);
	}
		
	function send($message)
	{
		
		$info = MessageController::extractInfoData($this->message_resource->info);
		if ($curl_service = $info['service'])
		{
			$service_class_name = $curl_service.'Service';
			if ($service = new $service_class_name())
			{
				$result = $service->send($message);
				$this->message_resource->response = $service->raw_response;
				if ($result)
					return true;
				else
					throw new Exception("Error Sending Curl",100);
			}
			
		}
		$url = $this->deliver_to_addr;
		if ($info_string = $this->message_resource->info)
		{
			$additional_data = $this->extractAndSetInfoData($info_string);
			if ($header_json = $additional_data['headers'])
			{
				$header_list_array = json_decode($header_json,true);
				foreach ($header_list_array as $name=>$value)
					$headers[] = "$name: $value";
			}
			if ($additional_data['post'])
				$post = true;
			if ($additional_data['verbose'])
				$verbose = true;

		} else {
			$headers[] = 'Content-Length: ' . strlen($message);
			if (json_decode($message))
				$headers[] = 'Content-Type: Application/json';
			else if (substr_count($message, "soap-envelope") > 0)
				$headers[] = 'Content-Type: application/soap+xml';
			else if (substr_count($message, "<?xml version"))
				$headers[] = 'Content-Type: application/xml';
		}
		$response = $this->curlIt($url, $headers, $post, $message, $verbose);
		if ($raw_return = $response['raw_return'])
			$this->setMessageResourceResponse($raw_return);
		else if ($error = $response['error'])
			$this->setMessageResourceResponse($error);
		if ($response['http_code'] == 200)
			return true;
		else
			return false;
		
	}
	
	function curlIt($url,$headers,$post,$body,$verbose)
	{
		myerror_logging(3,"CurlController: about to curl with a body of: ".$body);
		if ($ch = curl_init($url))
		{
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			if ($verbose)
				curl_setopt($ch, CURLOPT_VERBOSE,$verbose);
			if ($post)
				curl_setopt($ch, CURLOPT_POST,$post);
			if ($body)
				curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			$response = SplickitCurl::curlIt($ch);
			curl_close($ch);
			myerror_logging(3,"the response in curl controller is: ".$response['raw_return']);
		} else {
			$response['error'] = "FAILURE. Could not connect to $url";
			myerror_log("ERROR! CurlController: could not connect to: $url");
		}
		return $response;
	}
}

