<?php

Class IvrController extends MessageController
{
	const IVRSUCCESS = '2';

	function IvrController($mt,$u,&$r,$l = 0)
	{
		parent::MessageController($mt,$u,$r,$l);
	}
	
	function send($message)
	{
		$message_response_url = getProperty('twimlets_server') . urlencode($message);
		$twilio_service = new TwilioService();
		if ($response = $twilio_service->doOutboundCall($this->deliver_to_addr, $message_response_url)) {
			$this->message_resource->message_text = $message;
			$this->message_resource->response = $twilio_service->curl_response['raw_result'];
			return $response;
		}
	}

	function buildMessage($message)
	{
		// we're setting delivery address here instead of in the template becuase it allows us more flexibility
		$phone_number = trim($this->deliver_to_addr);
		$message = str_replace('xxxxxxxxxx','1'.$phone_number,$message);
		myerror_logging(2,"about to send IVR order to: ".$phone_number);
		
		// what a hack
		$message = str_replace('replace_me_with_order_id',$this->full_order['order_id'],$message);
		return $message;
	}

	function processCallBack()
	{
		$data = $this->request->data;
		if ($order_id = $data['orderid']) {
			if (IvrController::IVRSUCCESS == $data['CallStatus']) {
				$sql = "UPDATE Merchant_Message_History SET locked = 'C' WHERE order_id = $order_id AND message_format = 'IC' AND locked = 'N'";
				$this->adapter->_query($sql);
			} else {
				myerror_log("call status is NOT 2 so do not update.  CallStatus: ".$data['CallStatus']);
			}	
		} else {
			myerror_log("Unable to get order id out of the submitted call back data. skipping update");
		}
	}
}