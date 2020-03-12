<?php

class GarbageSfaxFaxService extends SplickitFaxService
{
	protected $serviceEndpointUrl;
    protected $securityContext;
    protected $securityToken;
    protected $apiKey;
    
    private $response;
    private $response_info;
    private $curl_error;

    public function SfaxFaxService($message_resource)
    {
    	parent::SplickitFaxService($message_resource);    	
      $this->serviceEndpointUrl = getProperty('sfax_url');
      $this->securityContext = ""; //(Required but leave blank exactly as it is here)      
      $this->apiKey = getProperty('sfax_api_key');    
    }			
    
    function setServiceEndPointUrl($url)
    {
    	// do not allow in prod, this is only for testing.
    	if (isProd())
    		return false;
    	$this->serviceEndpointUrl = $url;	
    }
    
    function constructUrlEndpointForSending($faxNumber,$faxRecipient)
    {
		$url = $this->serviceEndpointUrl; 
		$url .= "sendfax?";
		$url .= "token=". urlencode($this->securityToken);
		$url .= "&ApiKey=" . urlencode($this->apiKey);
		
		// Add the method specific parameters
		$url .= "&RecipientFax=" . urlencode($faxNumber);
		$url .= "&RecipientName=" . urlencode($faxRecipient);
		$url .= "&OptionalParams=&";
		return $url;    	
    }

    private function sFaxCurlIt($url,$postData)
    {
		
    	$response = SFaxCurl::curlIt($url, $postData);
		$this->response = $response['response_body'];
		$this->response_info = $response['response_info'];
		$this->curl_error = $response['curl_error'];
		return $response['response_body'];
    	
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
		if ($this->serviceEndpointUrl == "badurl")
		{
			$this->setMessageResourceResponse("HttpErrorCode=404");
			return false;
		}
		else if ($this->serviceEndpointUrl == 'httxps://api.sfaxme.com/api/')
		{
			$this->setMessageResourceResponse("Protocol httxps not supported or disabled in libcurl");
			return false;
		}
		else if ($fax_data['fax_no'] == '7204x84799')
		{
			$this->setMessageResourceResponse('{"SendFaxQueueId":"-1","isSuccess":false,"message":"Invalid fax number(s): 17204x84799"}');
			return false;
		}
		else
		{
			$response_string = '{"SendFaxQueueId":"123456","isSuccess":true,"message":"Fax is queued for sending"}';
			$this->setMessageResourceResponse($response_string);
			$x_response_data = json_decode($response_string,true);
			$message = $x_response_data['message'];
			$sfax_queued_id = $x_response_data['SendFaxQueueId'];				
				
			//so now deal with the fax check for SFax.

			return true;
		}
	}	
}
?>
