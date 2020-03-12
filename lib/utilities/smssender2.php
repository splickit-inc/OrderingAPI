<?php

require_once 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'SmsInterface.inc';

class SmsSender2 {
	
	var $error_text;

	/**
	 * @desc sends an alert to the numbers listed in the engineering_alert_list_sms property.  eg: adam,tarek,dave
	 *
	 * @param varchar $message
	 */
	public static function sendEngineeringAlert($message)
	{
		$alert_list_sms = getProperty("engineering_alert_list_sms");
		$sms_nos = explode(',', $alert_list_sms);
		SmsSender2::sendSmsAlert($sms_nos, $message);
	}
	
	/**
	 * @desc sends an alert to the numbers listed in the vault_alert_list_sms property.  eg: adam,tarek,kendal,garett
	 *
	 * @param varchar $message
	 */
	public static function sendVaultAlert($message)
	{
		$alert_list_sms = getProperty("vault_alert_list_sms");
		if ($alert_list_sms != null && trim($alert_list_sms) != '') {
			$sms_nos = explode(',', $alert_list_sms);
			SmsSender2::sendSmsAlert($sms_nos, $message);
		}
		return true;
	}
	
	/**
	 * @desc sends an alert to the phone numbers in the alert_list_sms property.  this is really just for support.
	 * 
	 * @param $message
	 * @param $use_test not really used anymore. use sendEngineeringAlert method instead
	 * 
	 */
	public static function sendAlertListSMS($message,$use_test = false)
	{
		$alert_list_sms = getProperty("alert_list_sms");
		if ($use_test)
			$alert_list_sms = getProperty("alert_list_sms_test");
		$sms_nos = explode(',', $alert_list_sms);
		SmsSender2::sendSmsAlert($sms_nos, $message);
	}

	public static function send_with_twilio($sms_delivery_no,$message)
	{
		$twilio_service = new TwilioService();
		return $twilio_service->doSMSMessage($sms_delivery_no,$message);
	}

 	public static function send_with_cdyne($sms_delivery_no,$message)
 	{
 		if (!isProd() && getProperty('test_sms_messages_on') == 'false')
 		{
 			usleep(200000);
 			return true;
 		}
		try {
 			$cdyne_license_key = '2f7e45fb-f41a-4edf-a6f4-419d2316e769';
			$wsdl = 'http://sms2.cdyne.com/sms.svc?wsdl';
 			$client = new SoapClient($wsdl);
			
 			$param = array('PhoneNumber' => $sms_delivery_no,'LicenseKey' => $cdyne_license_key,'Message' => $message);
			// Send the text message
			$result = $client->SimpleSMSsend($param);
			$response = $result->SimpleSMSsendResult;
			$vars = get_object_vars($response);
			$sms_error = $vars['SMSError'];
			$message_id = $vars['MessageID'];
			$vars['response_id'] = $message_id;
			$vars['response_text'] = $sms_error;
			myerror_log("sms result from cdyne is: ".$sms_error." - ".$message_id);
			if ($sms_error == 'NoError')
				return $vars;
			else if ($sms_error == 'PhoneNumberInvalid')
			{
				MailIt::sendErrorEmail("Invalid Phone Number in SmsSender2", $sms_error.'   '.$sms_delivery_no);
				// will just mark as complete.
				return true;
			}
			myerror_log("ERROR! something other than NoError returned from cdyne");
		} catch (Exception $e) {
			myerror_log("ERROR! we had a serious error doing a SOAP call with cdyne in SmsSender2: ".$e->getMessage());
			$message_id = 88888;			
			$sms_error = $e->getMessage();
		}
		if (isLaptop() && $sms_delivery_no == '1234567890')
			return true;
		MailIt::sendErrorEmail("ERROR! something other than NoError returned from cdyne", 'result from cdyne is: '.$message_id.' - message: '.$sms_error);
		return false;
 	}

 	public static function send_sms($sms_delivery_no,$message)
	{	
		$providers = array("primary"=>"cdyne","secondary"=>"twilio");
		
		if (is_array($sms_delivery_no))
			$sms_delivery_nos = $sms_delivery_no;
		else
			$sms_delivery_nos[] = $sms_delivery_no;
			
		// first check to make sure we submitted at least 1 number
		if ($sms_delivery_nos == NULL || sizeof($sms_delivery_nos) < 1 || (sizeof($sms_delivery_nos) == 1 && ($sms_delivery_nos[0] == NULL || trim($sms_delivery_nos[0]) == '')))
		{
			myerror_log("ERROR! No delivery numbers submitted to SMSSEnder so Skipping SMS sending");
			throw new NoSmsNumberException();
		}

		$use_primary = getProperty('use_primary_sms');
		if ($use_primary == 'true')
		{
			myerror_logging(3,"about to use primary as first try");
			$first_try_provider = strtolower($providers['primary']);
			$second_try_provider = strtolower($providers['secondary']);
		}
		else
		{
			myerror_logging(3,"about to use secondary as first try");
			$first_try_provider = strtolower($providers['secondary']);
			$second_try_provider = strtolower($providers['primary']);
		}
		
		$first_try_provider_method = 'send_with_'.$first_try_provider;
		$second_try_provider_method = 'send_with_'.$second_try_provider;
		
		$sms_sender = new SmsSender2();
	
		foreach ($sms_delivery_nos AS $sms_no)
		{
			myerror_log("about to send message: '".$message."'   to: ".$sms_no."   with provider: ".$first_try_provider);
			if (isNotProd()) {
				$message = $message." - TEST SYSTEM";
				if (getProperty("test_sms_messages_on") != 'true') {
					return array("response_id"=>time(),"response_text"=>"dummy send");
				}
			}
			if ($vars = $sms_sender->$first_try_provider_method($sms_no,$message))
				continue;
			else
			{
				myerror_log("we have had a primary provider failure.  trying secondary: ".$second_try_provider);
				if ($vars = $sms_sender->$second_try_provider_method($sms_no,$message))
				{
					myerror_log("success with secondary provider!");
					continue;
				}
				else
				{
					SmsSender2::emailsmserror('ERROR! cant send SMS in sms_sender.php in '.getProperty('server'). '  '.$vars['response_text'].' START CALLING ORDERS IN NOW!');
					throw new Exception('ERROR! cant send SMS in sms_sender.php in '.$si->getResponseMessage());			
				} 
			}				
		}			 
		return $vars;
	}
	
	/**
	 * 
	 * @desc this will send alerts using message media.  set the $use_long to boolean true to use the long code for sending alerts to printers
	 * 
	 * @param array $sms_delivery_nos
	 * @param string $message
	 * @param boolean $use_long
	 */
	
	static function sendSmsAlert($sms_delivery_nos,$message,$use_long = false)
	{
		$twilio_service = new TwilioService();
		if (isNotProd()) {
			$message = $message." - TEST SYSTEM";
			if (getProperty("test_sms_messages_on") != 'true') {
				myerror_log("by passing message send");
				return true;
			}
		}
		$results = array();
		foreach ($sms_delivery_nos as $sms_delivery_no) {
			$result = $twilio_service->doSMSMessage($sms_delivery_no, $message);
			if ($result['response_text']) {
				$result['response_message'] = $result['response_text'];
			}
			$result['number'] = $sms_delivery_nos;
			$results[] = $result;
		}
		return array_pop($results);
	}
		
	static private function emailsmserror($message)
	{
		myerror_log("in the emailsmserror");
		myerror_log($message);
		if (!isProd())
			return true;
		MailIt::sendErrorEmail('SMS sending error!', $message);	
	}
}
class NoSmsNumberException extends Exception
{
    public function __construct() { 
        parent::__construct("ERROR! cant send SMS in sms_sender.php.  no sms number submitted!", 500);   
    }
}

?>
