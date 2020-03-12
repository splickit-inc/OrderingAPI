<?php
require_once 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'dompdf'.DIRECTORY_SEPARATOR.'dompdf_config.inc.php';
class FaxController extends MessageController
{

	protected $format = 'F';
	protected $format_name = 'fax';
	protected $retry_delay = 2;
	private $SAR;
	
	// this is just for testing;
	var $callback_url; 
	var $service_used;
	var $callback_failure_reason;
	
	function FaxController($mt,$u,&$r,$l = 0)
	{
		parent::MessageController($mt,$u,$r,$l);
	}
	
	protected function send($message_body)
	{
		if (isTest() && getProperty("test_email_messages_on") == 'false') {
			throw new Exception("Foreced Exception In Test. No fax sending", 100, $previous);
		}	
		// ok so we first need to check to see if this is a Fax Status Check message
		myerror_logging(3,"about to send fax with message body of: ".$message_body);
		myerror_logging(3,"the fax message format is: ".$this->message_resource->message_format);
		
		if (substr($this->message_resource->message_format, 0,2) == 'FU') {
			$message_body = $this->formatProcessor($message_body);
		}
		$user_id = $this->order_user['user_id'];
		
		//so build the array of fax data for the differnt services
		// faxio:   ('fax_no','fax_text','data_type'). data type is 'text' vs 'html'
		$data['fax_no'] = $this->deliver_to_addr;
		$data['fax_text'] = $message_body;
		$data['user_id'] = $user_id;
		if (isset($this->message_resource->order_id) && $this->message_resource->order_id > 1000)
			$data['order_id'] = $this->message_resource->order_id;
		
		$fax_list = getProperty("fax_service_list");
		$fax_services_names_array = explode(',', $fax_list);
		// check if there is a forced service on the merchant message map
		if ($this->info_data['service']) {
			myerror_log("we have a forced fax service of: ".$this->info_data['service']);
			$fax_services_names_array = array($this->info_data['service']);
		} else {
			myerror_log("we have an array of fax services of size: ".count($this->info_data['service']));
		}
		$fax_services_string = '';
		foreach ($fax_services_names_array as $fax_service_name)
		{
			$fax_services_string = $fax_services_string.$fax_service_name.',';
			myerror_logging(3,"attempting to send fax with $fax_service_name");
			$fax_service_class_name = $fax_service_name.'FaxService';
			$fax_service = new $fax_service_class_name($this->message_resource);
			try {
				if ($success = $fax_service->send($data))
				{
					if (isset($fax_service->callback_url))
						$this->callback_url = $fax_service->callback_url;
					$this->service_used = $fax_service_name;
					return $success;
				}
				throw new Exception("Unknown fax issue", 100);
			} catch (Exception $e) {
				myerror_log("we have thrown an error sending with a fax service, so roll over to the next one.  error: ".$e->getMessage());
			}
		}
		$fax_services_string = substr($fax_services_string, 0, -1);
		if (count($fax_services_names_array) == 1) {
			myerror_log("we have failed to send a fax in with a forced single provider. check if we can reschedule without alerts");
			if ($this->message_resource->tries < $this->max_retries) {
				//set code to 105 so we dont alert support on first failure
				$original_message = $e->getMessage();
				$e = new Exception($original_message, 105, $previous);
			}
		} else {
			myerror_log("ERROR!  we have failed with all selected fax service providers: ".$fax_services_string);
			$subject = "MAJOR FAX FAILURE!";
			$body = "We have failed sending a fax with ALL third party fax providers ($fax_services_string)! ORDER_ID: ".$this->message_resource->order_id."     Do we need to shut down all merchants with Fax????";
			MailIt::sendErrorEmailSupport($subject, $body);
			MailIt::sendErrorEmailAdam($subject, $body);
		}
		// if we get here we were unable to send the fax at all so throw the last error
		throw $e;
	}

	/**
	 * 
	 * @desc takes an alpha numeric merchant id as the parameter along with a message id and markes it as viewed. they must match on a message resource to be a valid call back
	 * @param $id
	 */
	public function callback($alpha_numeric_id)
	{
		if ($merchant_resource = MerchantAdapter::getMerchantResourceFromAlphaNumeric($alpha_numeric_id)) {
			$data = $this->request->data;
			logData($data,"call back",2);
			if (! isset($data['map_id'])) {
				$callback_url = $this->request->url;
				if ($url_data_array = SplickitFaxService::extractMapIdAndServiceFromCallBackUrl($callback_url)) {
					$data['map_id'] = $url_data_array['map_id'];
					$data['service'] = $url_data_array['service'];
				}
			}
			logData($data, "call back",3);
			if (isset($data['map_id']) && $data['map_id'] > 1000) {
				if ($message_resource = $this->adapter->getExactResourceFromData(array("merchant_id"=>$merchant_resource->merchant_id,"map_id"=>$data['map_id']))) {
					if ($this->processCallBackData($data)) {
						myerror_logging(3, "about to mark the fax as viewed due to call back");
						$this->adapter->markMessageResourceAsViewed($message_resource);
						if ($message_resource->order_id > 1000) {
							myerror_logging(3, "now mark the order as executed");
							OrderController::processExecutionOfOrderByOrderId($message_resource->order_id);
						}
						return true;
					} else {
						MailIt::sendErrorEmailSupport("FAILURE! we had a fax report back as failed from call back", "message_id: ".$id."   order_id: ".$message_resource->order_id);
						SmsSender2::sendAlertListSMS("FAILURE! we had a fax report back as failed from call back.  order_id: ".$message_resource->order_id);
						//MailIt::sendErrorEmailAdam("FAILURE! call back reported failure" , "message_id: ".$id."   order_id: ".$message_resource->order_id);
					}
				} else {
					myerror_log("ERROR! fax call back id did not match any messages  map_id: ".$data['map_id']."    stamp: ".$data['order_id']);
				}
			} else {
				myerror_log("ERROR!  No valid map id submitted with fax call back");
			}
		} else {
			myerror_log("ERROR! call back with bad alpha numeric id: ".$alpha_numeric_id);
			recordError("ERROR! Fax call back with bad alpha numeric id", "request: ".$this->request->url);
		}
		return false;
	}
		
	function processCallBackData($data)
	{
		if ($fax_service_name = $data['service'])
		{
			$fax_service = SplickitFaxService::faxServiceFactory($fax_service_name);
			if ($result = $fax_service->getCallBackResult($data)) {
				return true;
			} else {
				$this->callback_failure_reason = $fax_service->getCallbackFailReason();
				return false;
			}
		}
		//no service just return true for now till we have other situations.
		return true;
	}

	function formatProcessor($string)
	{
		$string = str_replace('::','', $string);
		return $string;
	}
	
}

?>