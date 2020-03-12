<?php
class MessageMediaService
{
	
	static function sendSmsMessage($sms_delivery_nos,$message,$use_long = false)
	{	
		if ($sms_delivery_nos[0] == '123456789')
		{
			myerror_log("DUMMY sending in TEST with MM looks good");
			$results['response_code'] = "1234567890";
			$results['response_message'] = "TestSuccess";
			$results['response_id'] = 'None';
			return $results;
		}
	}
}
?>