<?php 
Class StarmicrosPrinterController extends MessageController
{

    // documentation:   http://www.starmicronics.com/support/sdkdocumentation.aspx

	protected $format_name = 'starmicros';
	protected $format = 'R';
	protected $default_message_format = 'RUC';
	private $bio_order_id;
	private $test_messages_text = "This is a test message\nto make sure your printer\nis functioning correctly.\nIf you see this, all is 200 OK.";
    private $chars_to_strip = array(';', '*', '#', '@','&','<','>');


	function StarmicrosPrinterController($mt,$u,&$r,$l = 0)
	{
		parent::MessageController($mt, $u, $r,$l);
	}

	public function pullNextMessageResourceByMerchant($numeric_merchant_id)
	{
	    // get body if it exists
        $body = $this->request->body;
        // get method
        $method = $this->request->method;
        myerror_log("We have the method: $method");
        if ($merchant_resource = MerchantAdapter::getMerchantFromNumericId($numeric_merchant_id)) {
            $merchant_id = $merchant_resource->merchant_id;
            if (strtolower($method) == 'post') {
                DeviceCallInHistoryAdapter::recordPullCallIn($merchant_id,$this->format);
                if ($message_resources = $this->getAvailablePuledMessageResourcesArrayByMerchantId($merchant_id)) {
                    myerror_log("there were messages");
                    $message_text = '{"jobReady": true,"mediaTypes": ["text/plain"]}';
                } else {
                    myerror_log("there were NO NO NO messages");
                    $message_text = '{"jobReady": false,"mediaTypes": ["text/plain"]}';
                }
                $resource = new Resource();
                $resource->_representation = $this->static_message_template_no_new_line;
                $resource->message_text = $message_text;
                $resource->static = true;
                $resource->mimetype = "text/plain";
                return $resource;
            } else if (strtolower($method) == 'get') {
                if ($message_resource = parent::pullNextMessageResourceByMerchant($merchant_id)) {
                    $resource = $this->prepMessageForSending($message_resource);
                    $resource->_representation = $this->static_message_template_no_new_line;
                    return $resource;
                } else {
                    return false;
                }
            }
        } else {
            myerror_log("SERIOUS ERROR IN StarmicrosPrinter CONTROLLER!  no matching merchant for submitted numeric id: ".$numeric_merchant_id);
            return false;
        }
		return false;
	}

    private function createTestEpsonMessage($merchant_id,$message,$test_order_id,$next_message_time)
    {
        $message_data['merchant_id'] = $merchant_id;
        $message_data['message_format'] = 'SUT';
        $message_data['locked'] = 'P';
        $message_data['viewed'] = 'N';
        $message_data['order_id'] = $test_order_id;
        $message_data['next_message_dt_tm'] = $next_message_time;

        $message_data['message_text'] = $message;
        $new_message_resource = Resource::factory($this->adapter,$message_data);
        $new_message_resource->save();
        return $new_message_resource;

    }
	
	function prepMessageForSending($message_resource)
	{
		$resource = parent::prepMessageForSending($message_resource);
		$resource->mimetype = "text/plain";
		// remove leading carriage return
        if (substr($resource->message_text,0,1) == "\n") {
            $resource->message_text = substr($resource->message_text,1);
        }
		return $resource;
	}

    public function formatProcessor($string)
    {
        return str_replace("::","",$string);
    }

    function markMessageDelivered($message_resource = null,$locked = 'S')
	{
		if ($message_resource == null) {
            $message_resource =& $this->message_resource;
        }
		//$message_resource->viewed = 'N';
		return parent::markMessageDelivered($message_resource,$locked);

	}
}
?>