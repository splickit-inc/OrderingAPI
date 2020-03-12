<?php
use Aws\S3\S3Client;
class EmailService {
	
	protected $aws_service;
	protected $template_service;
	
	function __construct($aws = null, $ts = null) {
		if(isset($aws)) {
			$this->aws_service = $aws;
		} else {
			$this->aws_service = new AWSService();
		}
		
		if(isset($ts)) {
		  $this->template_service = $ts;
		} else {
		  $this->template_service = new TemplateManager();
		}
	}
	
	function getGroupOrderInviteEmail($data) {
	  if(isProd() || isLaptop()) {
	    $bucket = "/splickit-templates/group-order-email/production";
	  } else {
	    $bucket = "/splickit-templates/group-order-email/staging";
	  }
	  if($body = $this->aws_service->getKey($bucket, "group_order_invitation.html")) {
	    myerror_log("EmailService - successfully got group order invite email.");
	    $body = $this->rewindReadbody($body);
	    return $this->template_service->populateTemplate($body, $data);
	  } else {
	    myerror_log("EmailService - could not get group order invite email from $bucket.");
	    return null;
	  }
	}
	
	function getGroupOrderAdminEmail($data) {
	  if(isProd() || isLaptop()) {
	    $bucket = "/splickit-templates/group-order-email/production";
	  } else {
	    $bucket = "/splickit-templates/group-order-email/staging";
	  }
	  //if($body = $this->aws_service->getKey($bucket, "group_order_admin_email.html"))
	  if($body = $this->aws_service->getKey($bucket, "group_order_confirmation.html")) {
	    myerror_log("EmailService - successfully got group order admin email.");
	    $body = $this->rewindReadbody($body);
	    return $this->template_service->populateTemplate($body, $data);
	  } else {
	    myerror_log("EmailService - could not get group order admin email from $bucket.");
	    return null;
	  }
	}

	function getWelcomeEmail($bucket_name, $skin_name) {
	  if(isProd() || isLaptop()) {
	    $bucket = "$bucket_name/production";
	  } else {
	    $bucket = "$bucket_name/staging";
	  }
	   
	  myerror_log("EmailService - getting $bucket_name welcome template for skin: $skin_name");

//		THis is only used during offline testing.
//		if ($_SERVER['no-welcome']) {
//			return null;
//		}
	   
	  if($body = $this->aws_service->getKey($bucket, $skin_name."-welcome-email.html")) {
	    myerror_log("EmailService - successfully got welcome email for skin: $skin_name");
	    $body = $this->rewindReadbody($body);
	    return $this->template_service->populateTemplate($body, array());
	  } else {
	    myerror_log("EmailService - could not get welcome email for skin: $skin_name");
	    return null;
	  }
	}
	
	function getUserWelcomeTemplate($skin_name) {
	  return $this->getWelcomeEmail("splickit-templates/user-welcome-email", $skin_name);
	}
	
	function getMerchantWelcomeTemplate($skin_name) {		
		return $this->getWelcomeEmail("splickit-templates/merchant-welcome-email", $skin_name);
	}
	
	function rewindReadbody($body)
	{
		if ($body) {
			$body->rewind();			
			return $body->read($body->getContentLength());
		} else {
			return null;
		}
	}
	
	static function staticGetCrmTemplate()
	{
		$email_service = new EmailService();
		return $email_service->getCrmTemplate();
	}
	
	function getCrmTemplate() 
	{
		$bucket = "splickit-templates/administrative-emails";
		if($body = $this->aws_service->getKey($bucket, "stats-email.html")) {
			return $this->rewindReadbody($body);
		}
	}
}