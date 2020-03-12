<?php

require_once 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'SmsInterface.inc';

class SmsSender {

	public static function send_sms($sms_delivery_nos,$messages,$use_primary = 'true')
	{	

		try {
			if (strtolower($use_primary) == 'false')
				throw new Exception('forced switch to MM');

	  		//qwerty sms stuff
			$wsdl = "https://api.strategicmobilesolutions.net/SMSService.asmx?WSDL";
			$ns = "https://api.strategicmobilesolutions.net/SMSService.asmx/";
					
				$header['Username'] = "5083332484";
				$header['Password'] = "strategic1";
				$header['AuthenticatedToken'] = "";
				
				myerror_log("about to make the soap call");
				$client = new SoapClient($wsdl);
				$soapie = new SoapHeader($ns, "SecuredWebServiceHeader", $header, false);
				$client->__setSoapHeaders($soapie);
				myerror_log("about to authenticate to the sms provider");
				$token = $client->AuthenticateUser();
				myerror_log("we have the authentication token: ".$token->AuthenticateUserResult);
				$header['AuthenticatedToken'] = $token->AuthenticateUserResult;
				$soapie = new SoapHeader($ns, "SecuredWebServiceHeader", $header, false);
				$client->__setSoapHeaders($soapie);
				//$client->ScheduleMessage(array("user" => $cellnumber, "message" => $message, "scheduledTime" => $timeToSend ));
				//myerror_log("about to send ".date(""));
				foreach ($sms_delivery_nos AS $sms_no)
				{
					$numbers = array();
					for($i=0;$i<sizeof($messages);$i++)
						$numbers[] = $sms_no; 			
					myerror_log('about to send messages to: '.$sms_no.'     and   messages array at: '.sizeof($messages));
					if ($_SERVER['log_level'] > 1)
					{
						foreach ($messages as $the_message)
							myerror_log("sending: ".$the_message);
					}
					$client->ScheduleMessages(array("user" => $numbers, "message" => $messages));
					myerror_log("messages sent");
				}			 
				myerror_log("soap call complete");
				return true;
	} catch (Exception $e) {
			myerror_log("ERROR SENDING MESSAGES in sms_sender: ".$e->getMessage());
			if ($e->getMessage() != 'forced switch to MM')
				MailIt::sendErrorEmail('ERROR!  CANT SEND SMS THROUGH PRIMARY!  SWITCHING TO MessageMedia','ERROR! cant send SMS in cron_smaw2.php in '.$_SERVER['SERVER_NAME'].'. '.$e->getMessage().'  SWITCHING TO Message Media');
			myerror_log("resending with Message media");
			$si = new SmsInterface (false, false); 
			if (!$si->connect ('SplickitInc002','0ctccr2', true, false))
			{
				MailIt::sendErrorEmailSupport('ERROR! cant connect to Message Messedia sms server in sms_sender.php.  WE ARE DOWN!  CALL THE ORDERS IN.','ERROR! cant connect to Message Messedia sms server in sms_sender.php.  WE ARE DOWN!  CALL THE ORDERS IN.');
				return false;
			}
			else
			{
				foreach ($sms_delivery_nos as $sms_no2)	
				{
					$si->addMessage ('+1'.$sms_no2,$order_details_string);
					foreach ($messages AS $message)
						$si->addMessage ('+1'.$sms_no2,$message);						
				}	
				if (!$si->sendMessages ())  
				{	
					MailIt::sendErrorEmailSupport('ERROR!  CANT SEND SMS! START CALLING ORDERS IN!','ERROR! cant send SMS in sms_sender.php in '.$_SERVER['SERVER_NAME'].'. '.$si->getResponseMessage().'  START CALLING ORDERS IN NOW!');
					return false;			
				} else {
					myerror_log("sms sending with MM looks good");
					return true;
				}
			}			
		}
	}
}
?>
