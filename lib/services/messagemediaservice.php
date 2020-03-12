<?php
class MessageMediaService
{
	
	static function sendSmsMessage($sms_delivery_nos,$message,$use_long = false)
	{	
		$si = new SmsInterface (true, true); 
		$code = 'SplickitInc001';
		if ($use_long == true)
			$code = 'SplickitInc002';
		if (!$si->connect ($code,'0ctccr2', true, false))
		{
			MailIt::sendErrorEmail('SMS alert ERROR!', 'ERROR! cant connect to Message Messedia sms server for HIGH ALERT LEVEL MESSAGE : '.$message);
			return false;
		}
		else
		{
			foreach ($sms_delivery_nos as $sms_no2)	
			{
				$si->addMessage ('+1'.$sms_no2,$message);
				myerror_log("loading call with message: '".$message."'   to: ".$sms_no2);						
			}
		
			if ($si->sendMessages ())  
			{	
				myerror_log("sms alert sending with MM looks good: ".$si->responseMessage."  ".$si->responseCode);
				$results['response_code'] = $si->responseCode;
				$results['response_message'] = $si->responseMessage;
				$results['response_id'] = 'None';
				return $results;
			} else {
				myerror_log("ERROR! WE CANT SEND ALERT MESSAGE! error: ".$si->getResponseMessage());
				MailIt::sendErrorEmail('SMS alert ERROR!', 'ERROR! '.$si->getResponseMessage().'      Cant SEND ALERT: '.$message);
				return false;
			}
		}			
	}
}
?>