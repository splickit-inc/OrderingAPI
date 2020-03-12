<?php
class SplickitFaxService
{
	public $message_resource;
	public $callback_url;
	public $adapter;
	public $curl_response_array;
	public $merchant_resource;
	
	protected $service_name;
	protected $callback_fail_reason;
	
	function SplickitFaxService(&$message_resource)
	{
		$this->message_resource = $message_resource;
		$this->adapter = new MerchantMessageHistoryAdapter($mimetypes);
		if (isset($message_resource->merchant_id) && $message_resource->merchant_id > 0)
			$this->merchant_resource = SplickitController::getResourceFromId($message_resource->merchant_id, "Merchant");
		
	}

	function send($data) {}
	
	/**
	 * @desc Takes a fax service name and returns the fax service matching it
	 * @param string $fax_service_class_name
	 * @return SplickitFaxService
	 */
	static function faxServiceFactory($fax_service_name)
	{
		$fax_list = getProperty("fax_service_list");
		if (substr_count(strtolower($fax_list), strtolower($fax_service_name)) < 1) {
			throw new NoMatchingFaxServiceRegisteredException();
		}
		$fax_service_class_name = $fax_service_name.'FaxService';
		$fax_service = new $fax_service_class_name($message_resource);	
		return $fax_service;	
	}
	
	protected function setMessageResourceResponse($response)
	{
		$response = get_class($this).": ".$response;
		if ($this->message_resource->response == null && $this->message_resource->response == '')
			$this->message_resource->response = $response;
		else
			$this->message_resource->response = $this->message_resource->response.";".$response;
	}
	
	protected function getFileHandle($fax_file)
	{
		myerror_logging(3,"opening file to create fax text. ".$fax_file);
		if ($file_handle = fopen($fax_file, 'w+'))
			return $file_handle; // all is good
		else
			throw new Exception("can't open file for faxing in SplickitFaxService getFileHandle method");
		
	}
	
	private function writeFaxToFile($file_handle,$message_body)
	{
		if (fwrite($file_handle, $message_body))
		{
			myerror_logging(3,"closing file");
			fclose($file_handle);
		} else {
			throw new Exception("can't open write to file for faxing in splickitfaxservice createFile method");
		}
		return true;
	}

	/**
	 * 
	 * @desc will create the fax file and return the path/file_name
	 * 
	 * @param int $order_id
	 * @param text $message_body
	 * 
	 * @return String of the path/file_name
	 */
	protected function createFile($order_id,$message_body)
	{
		myerror_logging(3,"starting send method in FaxageFaxService");
		
		// get full file name
		$fax_path = getProperty('splickit_fax_dir');
		if (isLaptop()) {
      $current_path = getcwd();
		  $fax_path = $current_path . "/orderfiles/faxfiles/";
		}
		$fax_file = $this->getCompleteFilePathAndName($fax_path, $order_id);	
		
		myerror_logging(3,"opening file to create fax text. ".$fax_file);
		$file_handle = $this->getFileHandle($fax_file);
		
		myerror_logging(3,"writing fax text");
		$this->writeFaxToFile($file_handle, $message_body);
		
		return $fax_file;	
	}
	
	private function getCompleteFilePathAndName($fax_path,$order_id)
	{
		if ($order_id)
		{
			$fax_file = $fax_path.$order_id."";
			if($_SERVER['HTTP_HOST'] == 'localhost:8888')
				$fax_file = "/tmp/".$order_id."";
		} else {
			$stamp = time();
			$file_name = 'static_'.$stamp;
			$fax_file = $fax_path.$file_name."";
		}
		if (substr($this->message_resource->message_format, 0,2) == 'FU') {
			$fax_file .= ".txt";
		} else {
			$fax_file .= ".html";
		}
		return $fax_file;
		
	}
	
	function createTheCallBackURL()
	{
		myerror_logging(3, "starting build call back url");
		if (isset($this->merchant_resource)) {
			return $this->createCallBackUrlFromMerchantResource($this->merchant_resource);
		} else {
			myerror_log("ERROR! no merchant associated with fax message to create call back!");
			MailIt::sendErrorEmail("ERROR! no merchant associated with fax message to create call back!", "order_id: ".$this->message_resource->order_id."    merchant_id: ".$this->message_resource->merchant_id);
			return false;
		}
	}
	
	function createCallBackUrlFromMerchantResource($merchant_resource)
	{
		$protocol_domain = getProperty('protocol_domain');
		$map_id = $this->message_resource->map_id;
		$service = $this->service_name;
		$callback_url = $protocol_domain."/app2/messagemanager/".$merchant_resource->alphanumeric_id."/map_id.$map_id/service.$service/fax/callback.txt?map_id=$map_id&service=$service&log_level=5";
		myerror_logging(3, "we have built the call back url: ".$callback_url);
		$this->callback_url = $callback_url;
		return $callback_url;	
		
	}
	
	static function extractMapIdAndServiceFromCallBackUrl($callback_url)
	{
		$key_values_map_id = '%/(map_id\.[0-9]{4,10})/%';
		$key_values_service = '%/(service\.[a-zA-Z]*)/%';
		
		if (preg_match($key_values_map_id, $callback_url,$matches))
		{
			$map_pair = $matches[0];
			$map_pair = str_ireplace('/', '', $map_pair);
		}	
		if (preg_match($key_values_service, $callback_url,$matches2))
		{
			$service_pair = $matches2[0];
			$service_pair = str_ireplace('/', '', $service_pair);
		}	
		
		$m = explode(".", $map_pair);
		$s = explode(".", $service_pair);
		
		$data[$m[0]] = $m[1];
		$data[$s[0]] = $s[1];
		return $data;		
	}	
	
	function getCallbackFailReason()
	{
		return $this->callback_fail_reason;
	}

}

class NoMatchingFaxServiceRegisteredException extends Exception
{
    public function __construct($code = 0) {
        // make sure everything is assigned properly
        parent::__construct("No Matching Fax Service Registered", $code);
    }
}