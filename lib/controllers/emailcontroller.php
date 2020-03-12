<?php

Class EmailController extends MessageController
{
	protected $representation = '/order_templates/email/execute_order_email.htm';
		
	protected $format = 'E';
	protected $format_name = 'email';
	
	protected $order_email_headers = "MIME-Version: 1.0 \nContent-Type: text/html; charset=UTF-8 \nFrom: order_manager@dummy.com\n";
	protected $email_headers = "MIME-Version: 1.0 \nContent-Type: text/html; charset=UTF-8 \n";
	protected $retry_delay = 5;
	protected $max_retries = 100;
	//protected $from_header = "From:order_manager@dummy.com\n";

    private $email_fields = [];
		
	function EmailController($mt,$u,&$r,$l = 0)
	{
		parent::MessageController($mt,$u,$r,$l);		
	}
	
	function prepMessageForSending($message_resource)
	{
		$resource = parent::prepMessageForSending($message_resource);
		
		if ($resource->message_text == null || $resource->message_text == '')
		{
			$representation = $resource->loadRepresentation(new FileAdapter($this->mimetypes, 'resources'));
			$content = $representation->_getContent();
			$resource->message_text = $content;
			$resource->_representation = $this->static_message_template;
		}
		return $resource;
		
	}
	
	function send($email_body)
	{
		// first create email subject		
		$message_info = $this->message_resource->info;
		$m = explode(';', $message_info);
		foreach ($m as $name_value_pair)
		{
			$nvp = explode('=', $name_value_pair);
			$data[strtolower($nvp[0])] = $nvp[1];
		}
		$email_subject = $data['subject'];
		if (isset($data['bcc']) && $data['bcc'] != '')
			$bcc = $data['bcc'];

		if (isset($data['from']) && $data['from'] != '') {
		  $from_name = $data['from'];
		}
		
		myerror_logging(2,"about to send email message to: ".$this->deliver_to_addr);
			
		if (isset($data['attachment']))
		{
			$file_string = $data['attachment'];
			//we have an email with an attachment
			
			// preparing attachments
			$files = explode('&', $file_string);
			foreach ($files as $file_name)
			{
				// this is still used for build letter process.
				myerror_log("in old attachment code block");
				$attachments = array();
		    	if(is_file($file_name)){
					//$file_name = 'dummy_merch.csv';
					$fp = @fopen($file_name,"rb");
		        	$file_data = @fread($fp,filesize($file_name));
		            @fclose($fp);
		            $file_data = chunk_split(base64_encode($file_data));
					$attachment['type'] = "text/plain";
					// determine if this is a path
					$f = explode('/', $file_string);
					if (sizeof($f, $mode)>1)
						$file_name_actual = array_pop($f);
					else
						$file_name_actual = $file_name;
					
					$attachment['name'] = $file_name_actual;
					$attachment['content'] = $file_data;
					$attachments[] = $attachment;
		    	} else {
		        	myerror_log("ERROR!  couldn't attach file in email controller: ".$file);
		        	myerror_log("couldn't find file so lets retry in 1 minute on the other server");
		        	throw new Exception("Error sending email in email controller.  Couldn't find file attachment. might be on other server so retry in 1 minute",100);
		        }
			}
		} else if (isset($data['document_ids'])) {
			myerror_logging(1,"we have documents to attache from the db");
			$document_adapter = new DocumentAdapter($mimetypes);
			$document_ids_string = $data['document_ids'];
			$document_ids = explode('&',$document_ids_string);
			$attachments = array();
			foreach ($document_ids as $document_id) {
				
		    	if($document_resource = Resource::find($document_adapter,''.$document_id)) {
					$file_data = $document_resource->file_content;
					$file_name = $document_resource->file_name;
					
		            $file_data = chunk_split(base64_encode($file_data));
					$attachment['type'] = "text/plain";
					$attachment['name'] = $file_name;
					$attachment['content'] = $file_data;
					$attachments[] = $attachment;
		    	} else {
		        	myerror_log("ERROR!  couldn't attach file in email controller: ".$file);
		        	myerror_log("couldn't find file in db");
		        	throw new Exception("Error sending email in email controller.  Couldn't find file in db",100);
		        }
			}
		}
		
		// now doing this in teh MailTo object
		//$email_string = $this->deliver_to_addr;
		$email_string = $this->message_resource->message_delivery_addr;
		myerror_log("about to go into the mandrill call with email_string: ".$email_string."   and subject: ".$email_subject);
		if ($email_string == null || trim($email_string) == '') {
			myerror_log("we have a blank email address!");
			//MailIt::sendErrorEmail("we have an email message with a blank address", "message_id:".$this->message_resource->map_id);
			//set max retries to 1 so it will set the message to 'F'
			$this->max_retries = 1;
		    throw new Exception("Error sending email in email controller.  BLANK EMAIL ADDRESS!",100);
		}

		if ($this->message_resource->message_format == 'Econf') {
            if ($skin_id = $this->full_order['skin_id']) {
                $skin_adapter = new SkinAdapter(getM());
                $skin = $skin_adapter->getRecordFromPrimaryKey($skin_id);
                if ($brand_id = $skin['brand_id']) {
                    $brand_adapter = new BrandAdapter(getM());
                    $brand_record = $brand_adapter->getRecordFromPrimaryKey($brand_id);
                    if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmptyOnArray($brand_record,'support_email')) {
                        // use brand support email
                        $reply_email = $brand_record['support_email'];
                    } else {
                        //get merchant email
                        $shop_email = $this->merchant['shop_email'];
                        $email_array = explode(',',$shop_email);
                        $reply_email = $email_array[0];
                    }
                    if (filter_var($reply_email, FILTER_VALIDATE_EMAIL)) {
                        ;//valid
                    } else {
                        myerror_log("BAD EMAIL ADDRESS FOR REPLY TO for Econf: $reply_email");
                        myerror_log("Resetting to: support@dummy.com");
                        $reply_email = 'support@dummy.com';
                    }
                } else {
                    $reply_email = 'support@dummy.com';
                }
            } else {
                $reply_email = 'support@dummy.com';
            }
        } else {
            $reply_email = 'support@dummy.com';
        }

        $this->email_fields['to'] = $email_string;
        $this->email_fields['subject'] = $email_subject;
        $this->email_fields['body'] = $email_body;
        $this->email_fields['from_name'] = $from_name;
        $this->email_fields['reply_to'] = $reply_email;
        $this->email_fields['bcc'] = $bcc;

        // mandril wont let us send from a different reply to domain it seems
        if ($reply_email != 'support@dummy.com') {
            myerror_log("reply_email is as: $reply_email   ----   we now going to set it back to support@dummy.com",5);
            $reply_email = 'support@dummy.com';
        }
        if ($result = MailIt::sendEmailMandrill($email_string, $email_subject, $email_body, $from_name, $bcc, $attachments,$reply_email))
		{
			myerror_log("result from mandrill in Email Controller: ".$result);
			$this->message_resource->response = $result;

			if (substr_count($result, 'sent') > 0 || substr_count($result, 'queued') > 0 || substr_count($result, 'rejected') > 0 || substr_count($result, 'invalid') > 0)
			{
				if (substr_count($result, 'rejected') > 0 || substr_count($result, 'invalid') > 0)
				{
					$email_results = json_decode($result);
					foreach($email_results as $email_result_record)
					{
						if ($email_result_record->status == 'rejected' || $email_result_record->status == 'invalid')
						{
							// need to log the bad address here somehow
							myerror_log("We have a bad address:  ".$email_result_record->email);
						}
					}
				}
				return true;
			}
		}
		// ok if we get here there was a problem sending the email.
		myerror_log("ERROR SENDING EMAIL EXECUTION! result: $result     --  message_id: ".$this->message_resource->map_id);
		if ($result == null || trim($result) == '')
			$result = "COULD NOT CONNECT!";
		throw new Exception("Error sending email in email controller. ".$result,200);
	}

	function getEmailFields()
    {
        return $this->email_fields;
    }
}
?>