<?php
class SfaxFaxService extends SplickitFaxService
{
	var $sfax_queued_id;
	var $response_message;
		
	protected $serviceEndpointUrl;
    protected $securityContext;
    protected $securityToken;
    protected $apiKey;
    
    private $response;
    private $response_info;
    private $curl_error;
    private $helper;

    public function SfaxFaxService($message_resource)
    {
    	parent::SplickitFaxService($message_resource);
    	
        $this->serviceEndpointUrl = getProperty('sfax_url');
        $this->securityContext = ""; //(Required but leave blank exactly as it is here)
        
        $this->apiKey = getProperty('sfax_api_key'); 

        // Set Security Token
		$FTSAES = new FTSAESHelper($this->securityContext);
		$this->securityToken = $FTSAES->GenerateSecurityTokenUrl();
		$this->helper = new FTSHelper();
        
    }			
    
    function setServiceEndPointUrl($url)
    {
    	// do not allow in prod, this is only for testing.
    	if (isProd())
    		return false;
    	$this->serviceEndpointUrl = $url;	
    }
    
    function constructUrlEndpointForStatusCheck($queued_id)
    {
    	//api.sfaxme.com/api/SendFaxStatus?token=5c802wxZLX7LuFu2xbvRCcP1r87TX5P6IpErNPpr2VpGr8QpW5jkZWiE32of%2fKO5ExwJMOf2K6KP%2bGeGkn4DX8F0EgmsZE0CxSXpgxckPOX4dRDd1IPCecBLxVNkw9Yb&apikey=C911F4C4C2B94F00B04C8F3A2AE6E417&SendFaxQueueId=XXXXXXXXXXXXXXXXXXXXXXXXXXX&
		$url = $this->getBaseUrl("SendFaxStatus");

		// Add the method specific parameters
		$url .= "&SendFaxQueueId=" . urlencode($queued_id);
    	return $url;
    	
    }
    function constructUrlEndpointForSending($faxNumber,$faxRecipient)
    {
		$url = $this->getBaseUrl("sendFax");
		
		// Add the method specific parameters
		$url .= "&RecipientFax=" . urlencode($faxNumber);
		$url .= "&RecipientName=" . urlencode($faxRecipient);
		$url .= "&OptionalParams=&";
		return $url;    	
    }
    
    function getBaseUrl($endpoint)
    {
    	
    	$url = $this->serviceEndpointUrl; 
		$url .= "$endpoint?";
		$url .= "token=". urlencode($this->securityToken);
		$url .= "&ApiKey=" . urlencode($this->apiKey);
		return $url;
    	
    }

    private function sFaxCurlIt($url,$postData)
    {
		
    	$response = SFaxCurl::curlIt($url, $postData);
    	myerror_log("raw fax result: ".$response['raw_result']);
		$this->response = $response['raw_result'];
		$this->response_info = $response['curl_info'];
		$this->curl_error = $response['error'];
		return $response['raw_result'];
    	
    }
	
	/**
	 * @desc will send a fax using fax service sfax.  fax_data must pass in ('fax_no','fax_text','order_id')
	 * 
	 *  @return boolean
	 */
	
	function send($fax_data)
	{
		// Service Connection and Security Settings
		$isSuccess = false;

		// IMPORTANT: key parameters
		$faxNumber = $fax_data['fax_no'];	//<--- IMPORTANT: Enter a valid fax number
		if (strlen($faxNumber) == 10)
			$faxNumber = '1'.$faxNumber;
		if ($fax_data['fax_path_file'])
			$filePath = $fax_data['fax_path_file'];
		else
			$filePath = $this->createFile($fax_data['order_id'], $fax_data['fax_text']);
		$faxRecipient = "Splickit Order";							
		
		// Construct the base service URL endpoint
		$url = $this->constructUrlEndpointForSending($faxNumber, $faxRecipient);
		myerror_logging(3,"Sfax url endpoing: ".$url);
		
		//reference primary file to fax
		$postData = array('file'=>"@$filePath");
		
		$response_body = $this->sFaxCurlIt($url, $postData);
		$response_info = $this->response_info;

		//get headers and response data
		$headers = $this->helper->getHeaders($response_body, $response_info);
		
		if ($response_info["http_code"] == 200)
		{	
			$response_string = $this->helper->getResponseData($response_body, $response_info);
			$this->setMessageResourceResponse($response_string);
			$x_response_data = json_decode($response_string,true);
			if ($x_response_data['isSuccess'] == true)
			{
				$message = $x_response_data['message'];
				$sfax_queued_id = $x_response_data['SendFaxQueueId'];				
				
				$this->sfax_queued_id = $sfax_queued_id;
				
				//so now deal with the fax check for SFax. it must be the 'X' message
				$mmha = new MerchantMessageHistoryAdapter($mimetypes);
				if (! $mmha->createMessage($this->message_resource->merchant_id, $this->message_resource->order_id, 'FC', 'StatusCheck', time()+240, 'X', "fax_service=SFax;SendFaxQueueId=".$sfax_queued_id, $message_text)) {
					MailIt::sendErrorEmail("ERROR! unable to schedule FC message", "There was an error trying to schedule the Fax Check message from a Faxage send.  error: ".$mmha->getLastErrorText());
				}

				return true;
			} else {
				// fax failure
				myerror_log("We had an SFax failure: ".$x_response_data['message']);
				MailIt::sendErrorEmailAdam("SFax error", $x_response_data['message']);
			}			
		}
		else
		{
			//something went wrong so investigate result and error information
			//get error information from response headers
			$xws_error_code = $response_info["http_code"];
			myerror_log("http response code=" . $xwsErrorCode);
			if ($error = $this->curl_error)
				$this->setMessageResourceResponse($error);
			else
				$this->setMessageResourceResponse("HttpErrorCode=".$xws_error_code);
		}
		return false;
	}	
	
	function faxCheck()
	{
		try
		{
			if ($info_string = $this->message_resource->info)
			{
				$additional_data = MessageController::extractInfoData($info_string);
				if ($queued_id = $additional_data['SendFaxQueueId'])
					return $this->checkFaxStatus($queued_id);
				myerror_log("ERROR!  serious error in Fax controller.  No SendFaxQueueId for Fax Check!");
			}
			throw new Exception("POSSIBLE FAX FAILURE, NO SendFaxQueueId LISTED. CANT CHECK THE FAX!  ", 99);
		} catch (Exception $e) {
			throw $e;
		}		
	}

	function checkFaxStatus($queued_id)
	{
		//api.sfaxme.com/api/SendFaxStatus?token=5c802wxZLX7LuFu2xbvRCcP1r87TX5P6IpErNPpr2VpGr8QpW5jkZWiE32of%2fKO5ExwJMOf2K6KP%2bGeGkn4DX8F0EgmsZE0CxSXpgxckPOX4dRDd1IPCecBLxVNkw9Yb&apikey=C911F4C4C2B94F00B04C8F3A2AE6E417&SendFaxQueueId=XXXXXXXXXXXXXXXXXXXXXXXXXXX&
		$url = $this->constructUrlEndpointForStatusCheck($queued_id);
		$response_body = $this->sFaxCurlIt($url, $postData);
		$response_info = $this->response_info;
		$headers = $this->helper->getHeaders($response_body, $response_info);
		if ($response_info["http_code"] == 200)
		{	
			$response_string = $this->helper->getResponseData($response_body, $response_info);
			$this->setMessageResourceResponse($response_string);
			$x_response_data = json_decode($response_string,true);
			if ($x_response_data['isSuccess'] == true)
			{
				//{"RecipientFaxStatusItems":[],"isSuccess":true,"message":"Processing fax request"}
				
				//{"RecipientFaxStatusItems":[{"SendFaxQueueId":"CCACD7521B59418DBDBBA2975D3B0988","IsSuccess":true,"ResultCode":0,"ErrorCode":0,"ResultMessage":"OK","RecipientName":"Splickit Order","RecipientFax":"1-7204384799","TrackingCode":"","FaxDateUtc":"2013-09-25T22:16:21Z","FaxId":2130925221621997110,"Pages":1,"Attempts":1}],"isSuccess":true,"message":"Fax request is complete"}

				//{"RecipientFaxStatusItems":[{"SendFaxQueueId":"96E92EC8BB3C473ABAB997CD6FAC6C80","IsSuccess":false,"ResultCode":6300,"ErrorCode":28025, ÒResultMessageÓ: "VoiceLine","RecipientName":"GeneFry","RecipientFax":"15125551212","TrackingCode":"GFry1234","FaxDateUtc":"2013-05-08T18:10:20Z","FaxId":2130508180940997005, "Pages":2, "Attempts":1}],"isSuccess":true,"message":"Fax request is complete"}
				
				$message = $x_response_data['message'];
				$this->response_message = $message;
				$recipient_fax_status_items_array = $x_response_data['RecipientFaxStatusItems'];
				if ($message == 'Processing fax request')
					throw new FaxStillPendingException("fax still showing pending, check again in 3 minutes", 105); // reschedule the fax check
				else if ($message == "Fax request is complete")
				{
					// so now check to see what the result was
					foreach ($recipient_fax_status_items_array as $fax_response)
					{
						if ($fax_response['SendFaxQueueId'] == $queued_id)
						{
							// ok we have the response for this fax now
							if ($fax_response['IsSuccess'] == true)
								return true;
							else if ($fax_response['IsSuccess'] == false)
								myerror_log("The fax failed!");
						} 
						
					}
					// IF we get here something is really wrong
					throw new Exception("FAX FAILURE!  CALL THE ORDER IN! ".$result, 99);
				} 
				else
				{
					MailIt::sendErrorEmailAdam("Un-recognized response from SFax", "response: ".$message);
					return false;
				}
			} else {
				// the status request failed
				myerror_log("SFax status check request failure: ".$x_response_data['message']);
				MailIt::sendErrorEmailSupport("SFax error", $x_response_data['message']);
				MailIt::sendErrorEmailAdam("SFax error", $x_response_data['message']);
				
				//check needs to be rescheduled
				return false;
			}			
		}
		else
		{
			//something went wrong so investigate result and error information
			//get error information from response headers
			$xws_error_code = $response_info["http_code"];
			myerror_log("http response code=" . $xwsErrorCode);
			if ($error = $this->curl_error)
				$this->setMessageResourceResponse($error);
			else
				$this->setMessageResourceResponse("HttpErrorCode=".$xws_error_code);
			MailIt::sendErrorEmailSupport("SFax curl error", "HttpErrorCode=".$xws_error_code."  curl_error=".$error);
			MailIt::sendErrorEmailAdam("SFax curl error", "HttpErrorCode=".$xws_error_code."  curl_error=".$error);
		}
		return false;

	}
}

class FaxStillPendingException extends Exception
{
    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 0) {
        // some code
    
        // make sure everything is assigned properly
        parent::__construct($message, $code);
    }

    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}

?>
