<?php
require_once 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'Php5Redis.php';

Class WindowsServiceController extends MessageController
{
	protected $representation = '/order_templates/windows_service/execute_order_windows.xml';
	protected $format = 'W';
	
	protected $format_array = array ('WT'=>'/utility_templates/windows_service_configure.xml');
	
	protected $format_name = 'windowsservice';
	protected $retry_delay = 3;
		
	function WindowsServiceController($mt,$u,&$r,$l = 0)
	{
		parent::MessageController($mt,$u,$r,$l);
	}
	
	public function getMerchantResourceFromAlphaNumeric($alpha_numeric_id)
	{
		$stringed_merchant_id = (string) $alpha_numeric_id;
		//$merchant_id = intval($merchant_id);
		//$merchant_options[TONIC_FIND_BY_METADATA]['OR'] = array('merchant_id'=>$merchant_id,'numeric_id'=>$merchant_id,'alphanumeric_id'=>$stringed_merchant_id);
		$merchant_options[TONIC_FIND_BY_METADATA]['alphanumeric_id'] = $stringed_merchant_id;
		$merchant_adapter = new MerchantAdapter($this->mimetypes);
		if ($merchant_resource = Resource::findExact($merchant_adapter,'',$merchant_options))
		{
			myerror_logging(3, "apha_numeric has been resolved to merchant_id: ".$merchant_resource->merchant_id);
			return $merchant_resource;
		}
		else
		{
			myerror_log("ERROR! could not resolve alpha_numeric '$stringed_merchant_id', to any existing merchant!");
			return false;
		}
	}
	
	public function pullNextMessageResourceByMerchant($merchant_id)
	{
		$stringed_merchant_id = (string) $merchant_id;
		if ($merchant_resource = $this->getMerchantResourceFromAlphaNumeric($stringed_merchant_id))
		{
			$merchant_id = $merchant_resource->merchant_id;
		} else {
			if ($stringed_merchant_id == '999999999999')
				return false;
			else if ($stringed_merchant_id == '0')
				return false;
			myerror_log("SERIOUS ERROR IN WINAPP CONTROLLER!  no matching merchant for submitted alphanumeric id: ".$stringed_merchant_id);
			//MailIt::sendErrorMail('arosenthal@dummy.com', 'no matching id in WINAPP controller', 'no matching id: '.$merchant_id);
			//MailIt::sendErrorMail('tarek@dummy.com', 'no matching id in WINAPP controller', 'no matching id: '.$merchant_id);
			return false;
		}
		DeviceCallInHistoryAdapter::recordPullCallIn($merchant_id,$this->format);
		if ($message_resource = parent::pullNextMessageResourceByMerchant($merchant_id))
		{
			$resource = $this->prepMessageForSending($message_resource);
			return $resource;
		}
		return false;
	}

    function markMessageDelivered($message_resource = null,$locked = 'S')
    {
        if ($message_resource) {
            if ($message_resource->message_format == 'WM') {
                $message_resource->viewed = 'N';
            }
        }
        return parent::markMessageDelivered($message_resource,$locked);
    }

	function formatProcessor($string)
	{
		$string=str_replace("::",'', $string);
		$header = "<order><order_id>".$this->full_order['order_id']."</order_id>".
					"<message_id>".$this->full_order['message_id']."</message_id>".
					"<name>".$this->full_order['full_name']."</name>".
					"<pickup_time>".$this->full_order['pickup_date_time']."</pickup_time>".
					"<merchant_id>".$this->full_order['merchant_id']."</merchant_id>".
					"<action>1</action>".
					"<order_amt>".$this->full_order['grand_total']."</order_amt>".
					"<order_quantity>".$this->full_order['order_qty']."</order_quantity>".
					"<order_details>".chr(10)."---- SPLICKIT MOBILE ORDER ---".chr(10).chr(10);
		$footer = "</order_details></order>";

		$escaped_string = htmlspecialchars($string);
		$full_xml_order = $header.$escaped_string.$footer;
		myerror_logging(3,"win app string: ".$string);
		return $full_xml_order;
		
	}
	
	function getOrderIdFromCallBackData($data)
	{
		myerror_log("******call back data******");	
		foreach ($data as $name=>$value)
			myerror_log("$name=$value");
		myerror_log("******call back data******");	
			
		if ($ref_number_value = $data['RefNumber']) {
			return $this->getOrderIdFromRefNumberValueWithBackwardCompatability($ref_number_value);
		} else if ($ref_number_value = $data['refNumber']) {
			return $this->getOrderIdFromRefNumberValueWithBackwardCompatability($ref_number_value);
		}
		myerror_log("ERROR!  Could not get order id out of call back data");
		return false;
	}
	
	function getOrderIdFromRefNumberValueWithBackwardCompatability($ref_number_value)
	{
			$ref_number_value = str_ireplace('ORDER', '', $ref_number_value);
			$order_id_as_string = trim($ref_number_value);
			$order_id = intval($order_id_as_string);
			return $order_id;
	}
	
	function callback($alpha_numeric_id)
	{
		myerror_logging(3,"we have a call back with from alpha numeric: ".$alpha_numeric_id);
		if ($merchant_resource = $this->getMerchantResourceFromAlphaNumeric($alpha_numeric_id))
			$merchant_id = $merchant_resource->merchant_id; // all is good
		else
			return false;
			
		$call_back_data_hash_map = $this->parseCallBackXML($this->request->body);
		$order_id = $this->getOrderIdFromCallBackData($call_back_data_hash_map);
		
		if ($order_id < 1000)
		{
			myerror_log("ERROR! no order id on windows call back. Probably some error has been thrown");
			if ($call_back_data_hash_map['faultstring']) {
				$error = $call_back_data_hash_map['faultstring'];
			} else {
				$error = "An unknown error was thrown and the order id could not be determined.\r\n";
				$error .= "Call Back: ".$this->request->body;
			}
			$email_body = "The call back xml indicated an error on the initial order send. Please check all recent unviewed messages for merchant_id: $merchant_id \r\n";
			$email_body .= "Error Text: $error";
			MailIt::sendErrorEmailSupport("Winapp Call Back Failure For merchant_id: $merchant_id", $email_body);
			MailIt::sendErrorEmailAdam("Winapp Call Back Failure For merchant_id: $merchant_id", $email_body);
			SmsSender2::sendAlertListSMS("Winapp Call Back Failure For merchant_id: $merchant_id. Check unviewed messages for this merchant");
			return false;
		}
		myerror_logging(3,"we've pulled the order_id out.  order_id: ".$order_id);
		// now update the order id
		$options = array();
		$success = false;
		$options[TONIC_FIND_BY_METADATA]['order_id'] = $order_id;
		$options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
		//$options[TONIC_FIND_BY_METADATA]['message_format'] = array("LIKE"=>"G%");
		if ($order_message_resources = Resource::findAll($this->adapter,NULL,$options))
		{
			if (is_array($order_message_resources))
			{
				foreach ($order_message_resources as $order_message_resource)
				{
					$format = substr($order_message_resource->message_format, 0,1);
					if ($format == 'W')
					{
						$order_message_resource->viewed = 'Y';
						$order_message_resource->modified = time();
						if ($order_message_resource->save())
							$success = true;
						else
							MailIt::sendErrorEmail("WINDOWS SERVICE CALL BACK ERROR.  COULD NOT MARK MESSAGE AS VIEWED", "message_id: ".$order_message_resource->map_id."   order_id: $order_id   merchant_id: $merchant_id");
					}
				}
			}
		}
		return $success;
	}
	
	/**
	 * @desc takes and XML string and returns a hashmap
	 * @param string $body
	 * @return hashmap
	 */
	function parseCallBackXML($body)
	{
		 if (substr_count($body, '<soap:Body>')) {
		 	if (substr_count($body, '<orderStatusHeader>')) {
				$body = $this->getSOAPOrderStatusBody($body);
		 	} else if (substr_count($body, '<soap:Fault>')) {
		 		$body = $this->getSOAPOrderErrorBody($body);
		 	}
		} 
		return parseXMLintoHashmap($body);
	}
	
	function getSOAPOrderErrorBody($body) 
	{
		$start = strpos($body,  '<soap:Fault>');
		$end = strpos($body,  '</soap:Fault>') + 13;
		return $this->getCleanPayloadFromXML($body, $start, $end);
	}
	
	function getSOAPOrderStatusBody($body)
	{
		$start = strpos($body,  '<orderStatusHeader>');
		$end = strpos($body,  '</orderStatusHeader>') + 20;
		return $this->getCleanPayloadFromXML($body, $start, $end);
	}
	
	function getCleanPayloadFromXML($xml_string,$start,$end)
	{
		$length = $end-$start;
		$data_xml = substr($xml_string, $start, $length);
		$clean_xml = cleanUpXML($data_xml);
		return $clean_xml;
		
	}

	function send($body = null) 
	{
		//throw new Exception("NO SEND METHOD FOR GPRS CONTROLLER.  MUST BE CALLED BY SERVICE");
		MailIt::sendErrorEmailSupport("ERROR! WindowsService message being called with SEND method!", "message_id: ".$this->message_resource->message_id);
		return true;	
	}

}
?>