<?php

class MailIt {
	

	/**
	 * @desc Will stage an email message in the merchant message history
	 * 
	 * 
	 */
	
	public static function stageEmail($to_email, $subject, $body, $from_name, $bcc,$mmh_data = array()) {		
		
		$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
		if ($mmh_data['message_format'] == null) {
			$mmh_data['message_format'] = "E";
		}
		$mmh_data['next_message_dt_tm'] = time();
		$mmh_data['message_text'] = $body;
		$mmh_data['message_delivery_addr'] = $to_email;
		$mmh_data['info'] = "subject=$subject;";
		if ($from_name) { 
			$mmh_data['info'] .= "from=$from_name;";
		}
			
		$mmh_resource = Resource::factory($mmh_adapter,$mmh_data);
		if ($mmh_resource->save()) {
			return $mmh_resource->map_id;
		} else {
			return false;
		}
	}
	
	public static function stageOrderConfirmationEmail($order_id,$to_email,$message_text,$subject,$from_name,$send_on_time_stamp)
	{
        $dftz = $_SERVER['GLOBAL_PROPERTIES']['default_server_timezone'];
        $tz = date_default_timezone_get();
        date_default_timezone_set($dftz);
		$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
		$mmh_data['order_id'] = $order_id;
		$mmh_data['message_format'] = "Econf";
		$mmh_data['next_message_dt_tm'] = MailIt::getCorrctSendOnTimeStamp($send_on_time_stamp);
		$mmh_data['message_text'] = $message_text;
		$mmh_data['message_delivery_addr'] = $to_email;
		$mmh_data['message_type'] = 'I';
		$mmh_data['info'] = "subject=$subject;";
		if ($from_name != null) {
			$mmh_data['info'] .= "from=$from_name;";
		}
		$mmh_resource = Resource::factory($mmh_adapter,$mmh_data);
		if ($mmh_resource->save()) {
            date_default_timezone_set($tz);
			return $mmh_resource->map_id;
		} else {
            date_default_timezone_set($tz);
			return false;
		}
	}
	
	public static function getCorrctSendOnTimeStamp($send_on_time_stamp)
	{
		$ts = (is_numeric($send_on_time_stamp) && $send_on_time_stamp > time())? $send_on_time_stamp : time();
		return $ts;
	}

	/**
	 * stages the email in the message history table and will return a true on success and queued, false otherwise
	 * 
	 * @return boolean
	 *
	 * @param  $to_email
	 * @param  $subject
	 * @param  $body
	 * @param  $from_name
	 * @param  $bcc
	 */
	public static function sendUserEmailMandrill($to_email,$subject,$body,$from_name,$bcc,$data = array())
	{
		return MailIt::stageEmail($to_email, $subject, $body, $from_name, $bcc,$data);
	}

    /**
     * send error email to specific person
     */
    public static function sendErrorEmailToIndividual($email,$subject,$body)
    {
        return MailIt::sendErrorEmailMandrillWrapper($email, $subject, $body, $from_name, $bcc, $attachments);
    }
	
	/**
	 * send email to adam only
	 */
	public static function sendErrorEmailAdam($subject,$body)
	{
		return MailIt::sendErrorEmailMandrillWrapper('arosenthal@dummy.com', $subject, $body, $from_name, $bcc, $attachments);
	}

	/**
	 * send email to tarek, adam, etc
	 */
	public static function sendErrorEmail($subject,$body)
	{
		return MailIt::sendErrorEmailMandrillWrapper(getProperty('email_string_error'), $subject, $body, $from_name, $bcc, $attachments);
	}
	
	/**
	 * send email to tarek and adam
	 */
	public static function sendErrorEmailTesting($subject,$body)
	{
		return MailIt::sendErrorEmailMandrillWrapper(getProperty('email_string_error_testing'), $subject, $body, $from_name, $bcc, $attachments);
	}
	
	/**
	 * send email to cary,nick,tarek,adam,mikiko
	 */
	public static function sendErrorEmailSupport($subject,$body)
	{
		return MailIt::sendErrorEmailMandrillWrapper(getProperty('email_string_support'), $subject, $body, $from_name, $bcc, $attachments);
	}
	
	/**
	 * send email to a semicolon separated list  eg: "tarek@dummy.com;kaubertot@dummy.com;arosenthal@dummy.com;justin@dummy.com;chris@dummy.com"
	 */
	public static function sendEmailToList($email_list,$subject,$body)
	{
		return MailIt::sendErrorEmailMandrillWrapper($email_list, $subject, $body, $from_name, $bcc, $attachments);
	}
	
	private static function sendErrorEmailMandrillWrapper($to_email,$subject,$body,$from_name,$bcc,$attachments)
	{
		if (isTest())
			$to_email = getProperty('test_addr_email_error');
		myerror_log("We are about to send an error email to: ".$to_email);	
		if  ($to_email == NULL || trim($to_email) == '')
		{
			myerror_log("ERROR!  trying to stage email with blank email address");
			return false;
		}	
		return MailIt::stageEmail($to_email, $subject." ".getProperty('server'), $body."   Session: ".$_SERVER['STAMP'], $from_name, $bcc, $attachments);
	}
	
	/**
	 * sends and email to a single recepient ONLY using mandril and returns a true on success and false if there was a problem
	 * 
	 * eg:   [{"email":"adam@dummy.com","status":"sent"}]
	 * 
	 * @param unknown_type $to_email
	 * @param unknown_type $subject
	 * @param unknown_type $body
	 * @param unknown_type $from_name
	 * @param unknown_type $bcc
	 * @param unknown_type $attachments
	 */

	public static function sendEmailSingleRecepientWithValidation($to_email,$subject,$body,$from_name,$bcc,$attachments = array())
	{
		$e = explode(',', $to_email);
		if (sizeof($e, $mode) > 1)
			throwException(new Exception("Error! method only valid for 1 email address", $code));
		$result = MailIt::sendEmailMandrill($to_email, $subject, $body, $from_name, $bcc, $attachments);
		if (substr_count($result, 'sent') > 0 || substr_count($result, 'queued') > 0 )
			return true;
		else
			return false;
	}
	
	/**
	 * sends and email using mandril and returns the exact string from mandrill
	 * 
	 * eg:   [{"email":"adam@dummy.com","status":"sent"}]
	 * 
	 * @param unknown_type $to_email
	 * @param unknown_type $subject
	 * @param unknown_type $body
	 * @param unknown_type $from_name
	 * @param unknown_type $bcc
	 * @param unknown_type $attachments
	 */
	
	public static function sendEmailMandrill ($to_email,$subject,$body,$from_name,$bcc,$attachments = array(),$reply_email = 'support@dummy.com')
	{
		if (isTest() && getProperty("test_email_messages_on") == 'false')
		{
			myerror_log("in test so fake the sending since send messages is false");
			$result = "[{\"email\":\"dummy.$to_email\",\"status\":\"sent\"}]";
			return $result;
		}	
		
		if ($subject == null || trim($subject) == '')
		{
			myerror_log("No Subject for mail, resetting to 'Subject'");
			$subject = "Subject";			
		}	
		
		$mandrill_key = getProperty('mandrill_key');
		
		$data['key'] = $mandrill_key;
		if (substr_count(strtolower($body), "html") > 0)
			$message['html'] = $body;
		else
			$message['text'] = $body;
		$message['subject'] = $subject;
		$message['from_email'] = $reply_email;
		
		if ($from_name == null || trim($from_name) == '')
			$message['from_name'] = "Splickit Support";
		else
			$message['from_name'] = $from_name;
			
		$to = array();	
		if (!is_array($to_email))
		{
			// first check for csv or scsv
			$e = explode(";", $to_email);
			if (sizeof($e, $mode) == 1)
				$e = explode(",",$to_email);
			foreach ($e as $email)
				$to[] = array("email"=>$email);
		} else
			$to = $to_email;
		//if ($bcc)
		//	$message['bcc_address'] = $bcc;
		
		// so maybe here we check on test system and addressses and such.
		$override_list_string = getProperty("email_override_list");
		foreach ($to as &$email_record)
		{
			$email = $email_record['email'];
			myerror_log("checking email address: ".$email);
			
			if (isProd())
				continue; // do nothing
			else if (preg_match("/".$email."/i", $override_list_string))
				continue;// do nothing
			else if (preg_match("/@dummy.com/i", $email))
				continue;// do nothing
			else if (preg_match("/@gidigo.com/i", $email))
				continue;// do nothing
			else
			{	
				$email = getProperty("test_addr_email");
				myerror_log("emailAddress is now: ".$email);
				$email_record['email'] = $email;
			}
		}
		
		$message['to'] = $to;
		
		if (sizeof($attachments) > 0)
			$message['attachments'] = $attachments;	

		myerror_log(" **************  email data  **************",5);
		foreach ($message as $name=>$value) {
		    if ($name == 'text' || $name == 'html') {
		        myerror_log("Body = (trimmed)",5);
            } else {
		        myerror_log("$name = $value",5);
            }
        }
        myerror_log(" **************  end email data  **************", 5);
		$data['message'] = $message;
		return MandrillEmailService::sendEmail($data);;		
	}
}