<?php

Class OpieController extends MessageController
{
	protected $representation = '/order_templates/opie/execute_order_opie.xml';
	//protected $representation = '/order_templates/gprs/dummy_test.txt';
	protected $no_order_representation = '/order_templates/opie/no_order_opie.xml';
	protected $format = 'O';
	protected $format_name = 'opie';
		
	function OpieController($mt,$u,&$r,$l = 0)
	{
		parent::MessageController($mt,$u,$r,$l);
	}
	
	/*(function markMessageDelivered($message_resource = null)
	{
		parent::markMessageDelivered($message_resource,'P2');
	}*/
	
	public function pullNextMessageResourceByMerchant($merchant_id)
	{
		$merchant_options[TONIC_FIND_BY_METADATA]['OR'] = array('merchant_id'=>$merchant_id,'numeric_id'=>$merchant_id);
		$merchant_adapter = new MerchantAdapter($this->mimetypes);
		if ($merchant_resource = Resource::findExact($merchant_adapter,'',$merchant_options))
		{
			$merchant_id = $merchant_resource->merchant_id;
		} else {
			myerror_log("SERIOUS ERROR IN OPIE CONTROLLER!  no matching merchant for submitted id: ".$merchant_id);
			MailIt::sendErrorMail('arosenthal@dummy.com', 'no matching id in OPIE controller', 'no matching id: '.$merchant_id);
			return false;
		}
		
		if ($message_resource = parent::pullNextMessageResourceByMerchant($merchant_id))
		{
			$resource = $this->prepMessageForSending($message_resource);
			return $resource;
		}
		return false;
/*			
			if (isset($message_resource->message_text) && trim($message_resource->message_text) != '')
			{
				// we have a static message already formatted so add the static message template and return
				$message_resource->_representation = $this->static_message_template;
				return $message_resource;
			}
			// call populate to load up full order and such
			$dummy_resource = $this->populateMessageData($message_resource);
			
			$opie_order_data = $this->getOpieFormattedOrder();
			$opie_order_data['message_id'] = $message_resource->map_id;

			try {
				$jsonString = json_encode($opie_order_data);
			} catch (Exception $e) {
				myerror_log("error encoding ".$e->getMessage()); // do nothing
			}

			//myerror_log("json: ".$jsonString);
			$opie_data['json'] = $jsonString;
			$opie_data['user_id'] = $opie_order_data['user_id'];
			$opie_data['order_id'] = $opie_order_data['order_id'];
			$opie_data['message_id'] = $message_resource->map_id;
			$opie_resource =& Resource::Factory(new OrderAdapter($this->mimetypes),$opie_data);
			$resource = $this->prepMessageForSending($message_resource);
			return $resource;
			
			return $opie_resource;	
		} else if ($merchant_id == 10) {
			$resource = Resource::dummyFactory(array());
			$resource->_representation = $this->no_order_representation;
			$resource->set('loaded','true');
			$resource->set('order_id','101');
			$resource->set('user_id','101');
			return $resource;
		}
		return false;	*/		
	}
	
	public function populateMessageData($message_resource)
	{
			$dummy_resource = parent::populateMessageData($message_resource);
			
			$opie_order_data = $this->getOpieFormattedOrder();
			$opie_order_data['message_id'] = $message_resource->map_id;

			try {
				$jsonString = json_encode($opie_order_data);
			} catch (Exception $e) {
				myerror_log("error encoding ".$e->getMessage()); // do nothing
			}

			//myerror_log("json: ".$jsonString);
			$opie_data['json'] = $jsonString;
			$opie_data['user_id'] = $opie_order_data['user_id'];
			$opie_data['order_id'] = $opie_order_data['order_id'];
			$opie_data['message_id'] = $message_resource->map_id;
			$opie_message_resource =& Resource::Factory(new OrderAdapter($this->mimetypes),$opie_data);
			return $opie_message_resource;
	}

	private function getOpieFormattedOrder()
	{
		$order = $this->full_order;
		foreach ($order['order_details'] as &$order_detail)
		{
// CHANGE_THIS
			// hard coded format for opie Illegal Petes trial
			$mods = array();
			$mods[] = $order_detail['order_detail_hold_it_modifiers'];
			$mods[] = $order_detail['order_detail_modifiers'];
			$mods[] = $order_detail['order_detail_sides'];
			unset($order_detail['order_detail_added_modifiers']);
			unset($order_detail['order_detail_comeswith_modifiers']);
			unset($order_detail['order_detail_mealdeal']);
			unset($order_detail['order_detail_hold_it_modifiers']);
			unset($order_detail['order_detail_modifiers']);
			unset($order_detail['order_detail_sides']);
			$order_detail['mods'] = $mods;
			$order_detail['mod_titles'][] = 'Holds';
			$order_detail['mod_titles'][] = 'Mods';
			$order_detail['mod_titles'][] = 'Sides';

		} 			
		unset($order['user']);
		unset($order['merchant']);
		//$results = print_r($order, true);
		//myerror_log($results);
		return $order;
	}
		
	public function getOrderById($order_id)
	{
		
		myerror_logging(2,"starting OPieController->get order by id");
		$this->full_order = CompleteOrder::staticGetCompleteOrder($order_id, $this->mimetypes);
		
		$opie_order_data = $this->getOpieFormattedOrder();
		try {
			$jsonString = json_encode($opie_order_data);
		} catch (Exception $e) {
			myerror_log("error encoding ".$e->getMessage()); // do nothing
		}
		$opie_resource =& Resource::Factory(new OrderAdapter($this->mimetypes),array('json'=>$jsonString));
		$opie_resource->_representation = $this->representation;
		return $opie_resource;	
	}

    function markMessageDelivered($message_resource = null,$locked = 'S')
    {
        if ($message_resource) {
            $message_resource->viewed = 'N';
        }
        return parent::markMessageDelivered($message_resource,$locked);
    }

    function send($body = null)
	{
		throw new Exception("NO SEND METHOD FOR OPIE CONTROLLER.  MUST BE CALLED BY SERVICE");	
	}
	
}
?>