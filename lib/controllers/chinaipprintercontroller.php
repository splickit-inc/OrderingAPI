<?php

class ChinaIPPrinterController extends MessageController
{
	protected $representation = '/order_templates/gprs/execute_order_gprs2.txt';
	//protected $representation = '/order_templates/gprs/dummy_test.txt';
	protected $no_order_representation = '/order_templates/gprs/no_order_gprs.txt';
	protected $format = 'H';
	
	protected $format_array = array ();
	
	protected $format_name = 'chinaipprinter';
	protected $body_length;
	protected $complete_body;	
	protected $request_lower_range_value;
	protected $request_upper_range_value;
	
	private $swap_record_resource;
	private $gprs_order_id;

    private $chars_to_strip = array(';', '*', '#', '@');

	/*
	 * to indicate when this is a new printer calling in for a message but is not yet at the store.  Pre-swap, during configuration
	 */
	private $preswap = false;
		
	function __construct($mt,$u,&$r,$l = 0)
	{
		parent::MessageController($mt, $u, $r,$l);
		if (function_exists('getallheaders')) {
			$heads = getallheaders();			
			logData($heads, 'Ip Printer Headers',3);
		}
		$range = (isset($heads['RANGE'])) ? $heads['RANGE'] : $range = 'bytes=0-1023';
		$r = explode('=', $range);
		$range_value = $r[1];
		myerror_logging(3,"the range value from the printer is: ".$range_value);
		$rs = explode('-',$range_value);
		$this->request_lower_range_value = $rs[0];
		$this->request_upper_range_value = $rs[1];
	}
	
	function markMessageDelivered($message_resource = null,$locked = 'S')
	{
		myerror_log("starting the IP mark message delivered code");
		if ($message_resource == null) {
			$message_resource =& $this->message_resource;
		}
		
		if 	(substr_count($this->request->url,'/f2.txt') > 0)
		{
			$message_resource->message_text = $this->complete_body;
			$message_resource->viewed = 'N';
			myerror_log("we have an f2.txt so we need to test to see if we mark delivered or not");
			
			$body_length = intval($this->body_length);
			$upper_range_value = intval($this->request_upper_range_value);
			
			myerror_log("body length is: ".$body_length);
			myerror_log("upper range value is: ".$upper_range_value);
			
			if (($body_length - 1) <= $upper_range_value )
			{
				myerror_log("about to call the parent mark message delivered");
				return parent::markMessageDelivered($message_resource,$locked);
			} else {
				myerror_log("China IP resetting locked to P");
			}
			
			$message_resource->locked = 'P';
			$message_resource->modified = time();
			if ($message_resource->save()) {
				return true;
			} else {
				myerror_log("ERROR trying to mark message as NOT delivered in gprs controller for f2.txt printer call: ".$message_resource->getAdapterError());
				myerror_log("error code is: ".mysqli_errno());
				throw new Exception("ERROR!  message could not be set back to unsent with P as locked: ".$message_resource->getAdapterError(),20);
			}	
		}
			
	}
	
	public function callBack($id)
	{
		if ($merchant_resource = MerchantAdapter::getMerchantFromIdOrNumericId($id))
		{	
			$merchant_id = $merchant_resource->merchant_id;
			$order_id = $this->request->data['o'];
			if ($order_id < 1000) {
				myerror_log("we have an order id that doesnt conform. so just pass through.  order_id: ".$order_id);
				return true;
			}
			if ($order_message_resource = $this->getCurrentFormatMessageForThisOrderAndMerchantID($order_id, $merchant_id)) {
				$viewed_result = MerchantMessageHistoryAdapter::markMessageResourceAsViewed($order_message_resource);
				return $viewed_result;
			}
		} else {
			return false;
		}
	}

	public function pullNextMessageResourceByMerchant($merchant_id)
	{
		if ($merchant_resource = MerchantAdapter::getMerchantFromIdOrNumericId($merchant_id))
		{
			$merchant_id = $merchant_resource->merchant_id;
		} else {
			myerror_log("SERIOUS ERROR IN CHinaPrinter CONTROLLER!  no matching merchant for submitted id: ".$merchant_id);
			recordError('no matching id in CHinaPrinter controller', 'no matching id: '.$merchant_id);
			return false;
		}
		
		// used during printer swapping
		$production_operation_firmware8 = false;
				
		// check if we're on the swap table
		if (substr_count($this->request->url,'/f2.txt') > 0 ) {
			$production_operation_firmware8 = true;
		}	
		
		DeviceCallInHistoryAdapter::recordPullCallIn($merchant_id,$this->format);
			
		if ($message_resource = parent::pullNextMessageResourceByMerchant($merchant_id))
		{
			// used during message formatProcessor to know what header and footer to use on the GPRS message
			$this->gprs_order_id = $message_resource->order_id;

			$resource = $this->prepMessageForSending($message_resource);
			if ($resource->static == 'true')
			{
				myerror_logging(3, "we have a static message");
				$this->body_length = strlen($resource->message_text);
				return $resource;
			}
			
			if (substr_count($this->request->url,'/f2.txt') > 0 ) 
			{
				$this->body_length = strlen($resource->message_text);
				$this->complete_body = $resource->message_text;
				myerror_log("complete body: ".$resource->message_text);
				return $resource;
			} 
		}
		
		return false;			
	}

    function cleanModRow(&$modifier_array)
    {
        foreach($modifier_array as &$mod) {
            $mod['mod_total_price'] = toCurrency($mod['mod_total_price']);
            $mod['mod_print_name'] = str_replace($this->chars_to_strip, '', $mod['mod_print_name']);
        }
    }

    function cleanMessageForSending(&$message_resource) {
        foreach($message_resource->order_details as &$order_item) {
            $order_item['item_print_name'] = str_replace($this->chars_to_strip, '', $order_item['item_print_name']);
            $order_item['size_print_name'] = str_replace($this->chars_to_strip, '', $order_item['size_print_name']);
            $order_item['note'] = str_replace("\n",'::', $order_item['note']);
            $order_item['note'] = preg_replace("/[^A-Za-z0-9 .\-:!]/", '', $order_item['note']);
            $order_item['item_total_w_mods'] = toCurrency($order_item['item_total_w_mods']);
            $this->cleanModRow($order_item['order_detail_modifiers']);
            $this->cleanModRow($order_item['order_detail_hold_it_modifiers']);
            $this->cleanModRow($order_item['order_detail_sides']);
            $this->cleanModRow($order_item['order_detail_mealdeal']);
            $this->cleanModRow($order_item['order_detail_comeswith_modifiers']);
            $this->cleanModRow($order_item['order_detail_added_modifiers']);
        }
        $message_resource->note = str_replace("\n","::", $message_resource->note);
        $message_resource->note = preg_replace("/[^A-Za-z0-9 .\-:!]/", '', $message_resource->note);
        if (isset($message_resource->delivery_info)) {
            $message_resource->delivery_info->address1 = preg_replace("/[^A-Za-z0-9 .\-:!]/", '', $message_resource->delivery_info->address1);
            $message_resource->delivery_info->address2 = preg_replace("/[^A-Za-z0-9 .\-:!]/", '', $message_resource->delivery_info->address2);
            $message_resource->delivery_info->phone_no = preg_replace("/[^0-9]/", '', $message_resource->delivery_info->phone_no);
            $message_resource->delivery_info->instructions = preg_replace("/[^A-Za-z0-9 .\-:!]/", '', $message_resource->delivery_info->instructions);
        }

        return $message_resource;
    }


    function formatProcessor($string)
	{
		$string=str_replace("\n",'', $string);
		$string=str_replace("\r",'', $string);
		$string=str_replace("&quot;",'', $string);
		if (substr_count($this->request->url,'/f2.txt') > 0 ) 
		{
			if ($this->message_resource->tries > 99)
			{
				// we have a resend so modify order id
				$this->gprs_order_id = $this->message_resource->order_id.'-'.generateAlphaCode(2);
			}			
			$string = "#".$this->getMerchantIdForCurrent()."*1*".$this->gprs_order_id."***;;;".$string.";*#";
		}
		return $string;
		
	}

	function send($body = null) 
	{
		MailIt::sendErrorEmailSupport("ERROR! IP message being called with SEND method!", "message_id: ".$this->message_resource->message_id);
		return true;
	}
}
?>
