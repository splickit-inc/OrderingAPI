<?php
Class JsonController extends MessageController
{
	protected $representation = '/json.xml';
	protected $format = 'J';
	
	protected $format_array = array ('J'=>'/json.xml');
	protected $format_name = 'json';
	
	function JsonController($mt,$u,&$r,$l = 0)
	{
		parent::MessageController($mt, $u, $r,$l);		
	}


	function pullNextMessageResourceByMerchant($numeric_id)
    {
        $merchant_resource = MerchantAdapter::getMerchantFromIdOrNumericId($numeric_id);
        $merchant_id = $merchant_resource->merchant_id;
        DeviceCallInHistoryAdapter::recordPullCallIn($merchant_id,$this->format);
        if ($message_resource = parent::pullNextMessageResourceByMerchant($merchant_id)) {
            $resource = $this->prepMessageForSending($message_resource);
            return $resource;
        } else {
            myerror_logging(3, "There are no pulled messages ready for this merchant");
            return false;
        }

    }

    function prepMessageForSending($message_resource)
    {
        $resource = clone $message_resource;
        $resource->message_text = $resource->portal_order_json;
        $resource->_representation = $this->static_message_template;
        $resource->mimetype = "application/json";
        $resource->set('loaded','true');
        $resource->set('message_id',$message_resource->map_id);
        $resource->set('message_delivery_addr',$message_resource->message_delivery_addr);
        myerror_logging(3,"finishing prep message for sending logic");
        return $resource;
    }

    private function getJsonFormattedOrder()
	{
		$order = $this->full_order;
		unset($order['user']);
		unset($order['merchant']);
		//$results = print_r($order, true);
		//myerror_log($results);
		return $order;
	}
	
	public function getOrderById($order_id)
	{
		
		myerror_logging(2,"starting JsonController->get order by id");
		$this->full_order = $this->getCompleteOrderForMessageSend($order_id);
		
		$json_order_data = $this->getJsonFormattedOrder();
		try {
			$json_string = json_encode($json_order_data);
		} catch (Exception $e) {
			myerror_log("error encoding ".$e->getMessage()); // do nothing
		}
		$json_resource =& Resource::Factory(new OrderAdapter($this->mimetypes),array('json'=>$json_string));
		$json_resource->message_text = $json_string;
		$json_resource->_representation = $this->static_message_template;
		return $json_resource;	
	}	
	
}
?>