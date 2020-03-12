<?php
error_reporting(E_ERROR);

class MessageController extends SplickitController
{
	protected $full_order;
	protected $merchant;
	protected $order_user;
	protected $message_info;
	protected $info_data;
	protected $error_message;
	protected $error_code;
	protected $email_headers = "MIME-Version: 1.0 \nContent-Type: text/html; charset=UTF-8 \nFrom: message_controller@dummy.com\n";
	public $message_resource;
	protected $message_data;
	
	protected $representation = '/json.xml';
	protected $format;
	protected $format_array = array();
	protected $format_name;
	protected $test_delivery_addr;
	protected $max_retries = 3;
	protected $deliver_to_addr;
	protected $static_message_template = '/utility_templates/static_message.txt';
	protected $static_message_template_no_new_line = '/utility_templates/static_message_no_new_line_at_end.txt';

	
	protected $use_test_addr = false;

	protected $has_messaging_error = false;

	/*
	 * to allow certain situation to retrieve the message without any updatin of the record.  mostly for printer swap.
	 */
	protected $bypass_update = false;

	function setTestDeliveryAddress($address)
	{
		$this->test_delivery_addr = $address;
	}

	function MessageController($mt,$u,&$request,$l = 0)
	{
				
		parent::SplickitController($mt,$u,$request,$l);
		
		$this->loadCustomLogLevelForFormat();
		$this->loadTestDeliveryAddressForFormat();
		$this->loadCustomTemplateArrayFromLookupTable();

		$this->adapter = new MerchantMessageHistoryAdapter($mt);
		myerror_logging(2,"******* created instance of ".get_class($this)." **********");
		if ($request) {
			myerror_logging(3,"url: " . $request->url);
			logData($_SERVER, "SERVER headers in message controller", 6);
			if (count($request->data) > 0) {
				logData($request->data, "request data in messagse controller", 6);
			}
		}
	}

	protected function loadTestDeliveryAddressForFormat()
	{
		if ($this->test_delivery_addr = getProperty('test_addr_'.$this->format_name)) {
			myerror_logging(2, "Test Delivery Address set to: " . $this->test_delivery_addr);
		} else {
			myerror_logging(2,"no test delivery address, setting to 'none'.");
			$this->test_delivery_addr = 'none';
		}
	}

	protected function loadCustomTemplateArrayFromLookupTable()
	{
		$lookup_adapter = new LookupAdapter($mt);
		$lookup_data['type_id_field'] = "message_template";
		$lookup_data['active'] = 'Y';
		$lookup_options[TONIC_FIND_BY_METADATA] = $lookup_data;
		if ($resources = Resource::findAll($lookup_adapter,null,$lookup_options))
		{
			myerror_logging(4, "we found custom templates, so create the array");
			foreach ($resources as $resource) {
				$this->format_array[$resource->type_id_value] = $resource->type_id_name;
			}
		}
	}

	function getLogLevel()
	{
		return $this->log_level;
	}

	protected function loadCustomLogLevelForFormat()
	{
		if ($new_log_level = $this->getCustomLogLevelForFormat()) {
			myerror_log("about to reset to custom log level of: ".$new_log_level);
			$this->log_level = $new_log_level;
			$_SERVER['log_level'] = $new_log_level;
		}
	}

	protected function getCustomLogLevelForFormat()
	{
		if (isset($this->global_properties[''.$this->format_name.'_log_level']) && $this->global_properties[''.$this->format_name.'_log_level'] > $this->log_level) {
			return $this->global_properties[''.$this->format_name.'_log_level'];
		}
		return null;
	}
	
	public static function getNextMessageResourceForSend($mmha_options = array(),$bypass_update = false)
	{
			$mmha = new MerchantMessageHistoryAdapter($mimetypes);			
			return $mmha->getNextMessageResourceForSend($mmha_options);
	}		
	
	public function cleanModRow(&$mod_row) {	  
	  foreach($mod_row as &$mod) {
	    $mod['mod_total_price'] = toCurrency($mod['mod_total_price']);
	  }
	}

	public function getCompleteOrderForMessageSend($order_id)
	{
		return CompleteOrder::staticGetCompleteOrder($order_id, $this->mimetypes, false);
	}
	
	public function getOrderInfoForMessage($message_resource)
	{
		$full_order = $this->getCompleteOrderForMessageSend($message_resource->order_id);
		$clean_details = array();
		foreach($full_order['order_details'] as &$order_item) {
			if ($order_item['item_name'] == LoyaltyBalancePaymentService::DISCOUNT_NAME) {
				continue;
			}
			$this->cleanModRow($order_item['order_detail_modifiers']);
			$this->cleanModRow($order_item['order_detail_mealdeal']);
			$this->cleanModRow($order_item['order_detail_sides']);
			$this->cleanModRow($order_item['order_detail_added_modifiers']);
			$this->cleanModRow($order_item['order_detail_comeswith_modifiers']);
			$this->cleanModRow($order_item['order_detail_holditmodifiers']);
			$clean_details[] = $order_item;
		}
		$full_order['order_details'] = $clean_details;
		return $full_order;
	}
	
	public function populateMessageData($message_resource)
	{
		if (isset($message_resource->order_id))
		{
			try {
        		$full_order = $this->getOrderInfoForMessage($message_resource);
				$this->full_order = $full_order;
				$this->merchant = $full_order['merchant'];
				$this->order_user = $full_order['user'];
				$resource = Resource::dummyFactory($full_order);
			} catch (Exception $oe) {
				throw new Exception($oe->getMessage(), 50);
			}
		} else {
			// do something else to get the data into the resource since this is not a message that uses order data
			$some_data = $this->getTheData($message_resource);
			$this->message_data = $some_data;
			$resource = new Resource(new MySQLAdapter($this->mimetypes),$some_data);
		}

		if ($info_string = $message_resource->info)
		{
			$additional_data = $this->extractAndSetInfoData($info_string);
			$resource->set("info_data",$additional_data);
		}
		
		$resource->set('message_id',$message_resource->map_id);
		if (isset($message_resource->message_delivery_addr)) {
			$resource->set('message_delivery_addr',$message_resource->message_delivery_addr);
		}
		
		return $resource;
	}
	
	public function extractAndSetInfoData($info_string)
	{
		$additional_data = $this->extractInfoData($info_string);
		$this->info_data = $additional_data;
		return $additional_data;
	}
	
	static function extractInfoData($info_string)
	{
		$fielddata = explode(';', $info_string);
		foreach ($fielddata as $datarow)
		{
			$s = explode('=', $datarow);
			$additional_data[$s[0]] = $s[1];
		}
		return $additional_data;
	}
	
	public function sendTheMessage()
	{
		if ($this->message_resource) {
			return $this->sendThisMessage($this->message_resource);
		}
		else
		{
			myerror_log("ERROR! No message_resource to send in MessageController.sendTheMessage()");
			return false;
		}
	}
	
	public function sendThisMessage($message_resource)
	{
        try {
            $this->message_resource = $message_resource;
            if ($resource = $this->prepMessageForSending($message_resource)) {
                myerror_log("we've returned from prepping the message");
            } else {
                return false;
            }
            $body = $resource->message_text;
            $this->message_resource->message_text = $body;
            // determine if we need to use a test address
            if (isset($message_resource->order_id) && isset($resource->user_id) && $resource->user_id < 100) {
                $this->use_test_addr = true;
            }

            $this->deliver_to_addr = $this->message_resource->message_delivery_addr;
            if ($this->message_resource->message_format == 'C') {
                myerror_log("skip test address test since we are in the curl controller.  should have been set at message creation");
            } else if (($this->use_test_addr || isNotProd())) {
                myerror_log("About to set the delivery to address to the test delivery address: ".$this->test_delivery_addr);
                if (isset($this->test_delivery_addr)) {
                    $this->deliver_to_addr = $this->test_delivery_addr;
                }
            } else {
                myerror_log("using the normal delivery address");
            }

            myerror_logging(3,"the message address is: ".$message_resource->message_delivery_addr);
            myerror_logging(3,"the deliver to address is: ".$this->deliver_to_addr);
            // call the send.  will throw exception if it fails.

			if ($this->send($body)) 
			{	
				$this->markMessageDelivered(); // will also updat the order status to 'E' if this is the X message
				return true;
			} else {
				// we get here if we send a robo call, or if gprs got picked up during a failed ping attempt (rare)
				return false;
			}						
		} catch (Exception $e) {
			$this->processError($e);
			// must return true here since there was a message it just failed in the sending.  so in order not to screw the other messages
			// that are in the que, must return a true.
			return true;
		}
	}

	public function getAvailablePuledMessageResourcesArrayByMerchantId($merchant_id)
	{
		$message_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
		$message_options[TONIC_FIND_BY_METADATA]['locked'] = 'P';
		$message_options[TONIC_FIND_BY_METADATA]['message_format'] = array('like'=>''.$this->format.'%');

		return $this->adapter->getAvailableMessageResourcesArray($message_options);
	}

	public function getAuthenticationSkinIfNeeded($merchant_id)
    {
        $sma = new SkinMerchantMapAdapter(getM());
        return  $sma->getRestrictedSkinThatMerchantIsMemberOf($merchant_id);
    }

    public function isRequestAuthorizedForSkin($restrictive_skin)
    {
        myerror_log("about to test authentication for ".$restrictive_skin['skin_name']);
        $client_id = $_SERVER['HTTP_X_SPLICKIT_CLIENT_ID'];
        $password = $_SERVER['PHP_AUTH_PW'];
        myerror_log("about to test authentication with $client_id : $password");
        if ($client_id == $restrictive_skin['public_client_id']) {
            if ($restrictive_skin['password'] != null) {
                return LoginAdapter::verifyPasswordWithDbHash($password, $restrictive_skin['password']);
            } else {
                myerror_log("AUTHENTICATION PROBLEM!!!! Restrictive skin with NO password set");
            }
        }
        return false;
    }
		
	/**
	 * 
	 * @desc get the next message that is ready to be sent by a merchant id.  will work with merchant_id or numeric_id
	 * @param int $merchant_id
	 * @return Resource
	 */
	public function pullNextMessageResourceByMerchant($merchant_id)
	{
	    // validate if authentication is needed
        if ($restricted_skin = $this->getAuthenticationSkinIfNeeded($merchant_id)) {
            if ($this->isRequestAuthorizedForSkin($restricted_skin)) {
                myerror_log("Request is authorized for ".$restricted_skin['skin_name'],1);
            } else {
                myerror_log("Reqeust is NOT authorized for ".$restricted_skin['public_client_id']."  ".$restricted_skin['skin_name'],1);
                $this->has_messaging_error = true;
                $this->error_code = 401;
                $this->error_message = "Unauthorized Request";
                return false;
            }
        }
		myerror_logging(2,'starting pullNextMessageResourceByMerchant in '.get_class($this));
		$message_resources = $this->getAvailablePuledMessageResourcesArrayByMerchantId($merchant_id);
		myerror_logging(3, "there were ".sizeof($message_resources)." message ready for sending");
		// 2.  loop until we have a successfully locked message and return it to the call controller (gprs,windows,opie)
		foreach ($message_resources as $unlocked_message_resource)
		{
			myerror_logging(3, "trying to get lock on message id: ".$unlocked_message_resource->map_id);
			if ($message_resource = $this->adapter->getLockedMessageResourceForSending($unlocked_message_resource, $this->bypass_update))
			{
				$this->message_resource = $message_resource;
				return $message_resource;
			}			
		}
		myerror_logging(3,"couldnt get lock on any messages for sending");
		return false;
	}
	
	public function getOrderRecordById($order_id)
	{
		$order_adapter = new OrderAdapter($this->mimetypes);
		$resource = Resource::find($order_adapter,''.$order_id);
		$resource->_representation = '/json.xml';
		return $resource;
	}
	
	public function formatProcessor($string)
	{
		myerror_log("base format processor: ".$string);
		return $string;
	}
	
	/**
	 * 
	 * @desc Returns a resource with the order info.  format will be set in request->data
	 * @param $order_id
	 * @return Resource
	 */
	public function getOrderById($order_id)
	{
		myerror_logging(2,"starting MessageController->get order by id");
		$this->full_order = CompleteOrder::staticGetCompleteOrder($order_id, $this->mimetypes);
		$resource = new Resource(new MySQLAdapter($this->mimetypes),$this->full_order);
		$resource->_representation = $this->representation;
		if ($this->request->data['format'])
		{
			// legacy
			$resource->_representation = $this->format_array[$this->request->data['format']];
			
			// new
			$resource->message_format = $this->request->data['format'];
		} else if (isset($this->default_message_format)) {
			$resource->message_format = $this->default_message_format;
		}
		if ($this->log_level > 4)
			Resource::encodeResourceIntoTonicFormat($resource);

		$resource = $this->prepMessageForSending($resource);	

		return $resource;
	}
	
	public function getFormattedMessageTextByOrderIdAndMessageFormat($order_id,$message_format)
	{
		$this->full_order = CompleteOrder::staticGetCompleteOrder($order_id, $this->mimetypes);
		$resource = new Resource(new MySQLAdapter($this->mimetypes),$this->full_order);
		$resource->message_format = $message_format;
		$resource = $this->prepMessageForSending($resource);
		$complete_text = $resource->message_text;
		return $complete_text;
	}
	
	public function setFormat($format)
	{
		$this->default_message_format = $format;
	}

	public function sendOrderById($order_id)
	{
		try {
			$resource = $this->getOrderById($order_id);
			// need to get delivery address since an order does not contain it.
			$merchant_message_map_adapter = new MerchantMessageMapAdapter($this->mimetypes);
			$mmma_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $this->full_order['merchant_id'];
			$mmma_options[TONIC_FIND_BY_METADATA]['message_format'] = $this->format;
			if ($this->request['message_delivery_addr'])
				$this->message_info['message_delivery_addr'] = $this->request['message_delivery_addr'];
			else if ($message_map = $merchant_message_map_adapter->select('',$mmma_options))
			{
				$message_map = array_pop($message_map);
				$this->message_info['message_delivery_addr'] = $message_map['delivery_addr'];
			} else {
				throw new Exception("NO MESSAGE DELIVERY ADDRESS SUBMITTED OR STORED IN MERCHANT MESSAGE MAP TABLE");
			}
			
			$body = $this->getMessageBody($resource);
			$result = $this->send($body);
			return result;
		} catch (Exception $e) {
			$this->error_message = $e->getMessage();
			return false;
		}		
	}
	
	static function createOrderMessages($merchant_id,$order_id,$send_time_stamp)
	{
		// first get MessageMaps
		$mmm_adapter = new MerchantMessageMapAdapter($mimetypes);
		$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
		$mmm_records = $mmm_adapter->getRecords(array("merchant_id"=>$merchant_id)); 
		$number_of_created_messages = 0;
		foreach ($mmm_records as $mmh_data)
		{
			$send_time = $send_time_stamp + ($mmh_data['delay']*60);
			if ($map_id = $mmh_adapter->createMessage($merchant_id, $order_id, $mmh_data['message_format'], $mmh_data['delivery_addr'], $send_time, $mmh_data['message_type'], $mmh_data['info'], $mmh_data['message_text']))
				$number_of_created_messages++; // all is good
			else
				recordError("UNABLE TO CREATE AN ORDER MESSAGE! sql_error: ".$mmh_adapter->getLastErrorText(), "order_id: $order_id,    merchant_id: $merchant_id,   message_format: ".$mmh_data['message_format']);
		}
		return $number_of_created_messages;
		
	}
	
	/**
	 * 
	 * @desc will find the Current Format message associated with this order_id and merchant_id. 
	 * @param unknown_type $order_id
	 * @param unknown_type $merchant_id
	 */
	protected function getCurrentFormatMessageForThisOrderAndMerchantID($order_id,$merchant_id)
	{
		$options[TONIC_FIND_BY_METADATA]['order_id'] = $order_id;
		$options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
		$options[TONIC_FIND_BY_METADATA]['message_format'] = array("LIKE"=>$this->format."%");
		return Resource::find($this->adapter,NULL,$options); 
	}

	//******  private functions *******//
	private function getTheData($message_resource)
	{
		$data = array();
		if (isset($message_resource->merchant_id) && $message_resource->merchant_id > 0)
		{	
			$data['merchant_id'] = $message_resource->merchant_id;
			$merchant_adapter = new MerchantAdapter($this->mimetypes);
			if (! $merchant = $merchant_adapter->select($data['merchant_id'],$options)) {
				throw new Exception('ERROR could not get Merchant as part of message build in MessageController->getTheData()');
			}
			$merchant = array_pop($merchant);
			$data['merchant'] = $merchant;
			$this->merchant = $merchant;
		}
		if (isset($message_resource->message_text))
			$data['message_text'] = $message_resource->message_text; // load a field with message text
			
		return $data;
	}
	
	protected function processError($e)
	{
		//error codes
		#10 no representation
		#20 message could not be marked as delivered
		#30 order status could not be updated
		#100 sending error, check for retry
		
		$this->error_message = $e->getMessage();
		$this->error_code = $e->getCode();
		myerror_log("ERROR!  IN THE MESSAGE CONTROLLER: ".$this->error_message);
		if ($e->getCode() == 105)  //  105 is the fax pending error 
		{
			if ($this->message_resource->tries == 2)
			{
				MailIt::sendErrorEmailSupport("Warning! We have a fax still pending after 7 minutes", "order_id: ".$this->message_resource->order_id);
			}	
		}		
		else if ($e->getCode() == 200)  // cant connect to mandrill error  
		{
			// nothing we can really do here but wait untill mandrill is back online
			$message_type = $this->message_resource->message_type;
			if ($message_type == 'X')
			{
				// order delivery message so send alerts via text.
				SmsSender2::sendAlertListSMS("WE have a Manrill Connection Error for an ORDER!\r\n order_id: ".$this->message_resource->order_id."\r\n error: ".$this->error_message);
			} else if ($message_type == 'Z') {
				$this->max_retries = 0;
				SmsSender2::sendEngineeringAlert("Problem Connecting to Mandrill!\r\n error: ".$this->error_message);
			}
		}	
		else if ($e->getCode() == 100 && $this->message_resource->order_id < 1000)  {
			myerror_log("We have an error in a non order related message, so not send alerts");
		} else if ($this->message_resource->message_type == 'X' || ($this->message_resource->message_type == 'O' && substr($this->message_resource->message_format,0,1) == 'F')) {
			$this->emailorderexecutionerror($this->error_message);
		}

		if ($e->getCode() > 99)
		{ 
			if ($this->message_resource->tries > $this->max_retries - 1) 
			{
				myerror_log("FAIL THE MESSAGE.  REPEATED TRIES HAVE FAILED TO SEND THE MESSAGE");
				$this->message_resource->locked = 'F';
				$this->message_resource->info .= ";error_message=".$this->error_message;
				$this->message_resource->stamp = getStamp();
				// since we skipped a long processing error before now need to send the error message to us
				if ($e->getCode() == 105) {  //  105 is the fax processing error
					$this->emailorderexecutionerror('Repeated tries still showing the fax as Status=Pending In QUE. User may need to have transaction voided');
				}				
				if ($this->message_resource->message_type == 'X') {
					$this->sendFailMessageToUser();
				}	
			} else {
				//now unlock the message and set the new delivery time to 4 minutes in the future.
				$retry_delay = 3;
				if ($this->retry_delay) {
                    $retry_delay = $this->retry_delay;
                }
				$next_message_new_time  = mktime(date("H"), date("i")+$retry_delay, date("s"), date("m")  , date("d"), date("Y"));									
				myerror_log("do not fail the message. retry in $retry_delay minutes setting new delivery time to be: ".date("Y-m-d H:i:s",$next_message_new_time));
				$this->message_resource->next_message_dt_tm = $next_message_new_time;
				$this->message_resource->modified = date("Y-m-d H:i:s");
				$this->message_resource->locked = 'N';	
			}
		} else if ($e->getCode() == 10) {
			// fail the message, there is no representation
			$this->message_resource->locked = 'F';
			$this->sendFailMessageToUser();
		} else if ($e->getCode() == 11) {
			// fail the message, there is no representation but its not the X message
			$this->message_resource->locked = 'F';
			//$this->sendFailMessageToUser();
		} else if ($e->getCode() == 20) {
			//couldn't mark message as delivered
			MailIt::sendErrorEmailTesting('Message Controller Error', 'Couldnt mark message as delivered.  message_id: '.$this->message_resource->map_id);
			return false; 
		} else if ($e->getCode() == 21) {
			//couldn't mark message as delivered
			MailIt::sendErrorEmailAdam('Message Controller PSUDO Error', 'Couldnt mark message as delivered. already delivered.  message_id: '.$this->message_resource->map_id);
			return false; 
		} else if ($e->getCode() == 30) {
			// couldn't update order status but message was marked as delivered
			MailIt::sendErrorEmailTesting('Message Controller Error', 'Couldnt update order status but message was marked as delivered.  message_id: '.$this->message_resource->map_id);
			return false;
		} else if ($e->getCode() == 50) {
			// send fail message to user, there is a serious problem building the order
			MailIt::sendErrorEmail( 'Message Controller Error', 'There is a serious problem building the order data.  message_id: '.$this->message_resource->map_id);
			$this->sendFailMessageToUser();
		} else if ($e->getCode() == 60) {
			// send fail message to user, there is a serious problem with the message history record
			MailIt::sendErrorEmail('Message Controller Error', 'There is a serious problem with the message history record.  message_id: '.$this->message_resource->map_id);
			$this->sendFailMessageToUser();
		} else if ($e->getCode() == 98) {
			// PING failed
			$this->message_resource->locked = 'F';
			$this->message_resource->info = $this->error_message;
		} else if ($e->getCode() == 99) {
			// fax check came back with a failure
			$this->message_resource->locked = 'F';
			$this->sendFailMessageToUser();	
		}
		if (isset($this->message_resource) && is_a($this->message_resource,'Resource') && $this->message_resource->exists())
		{
			$this->message_resource->modified = time();
			$this->message_resource->save();
		}	
		return false;		
	}
	
	private function sendFailMessageToUser()
	{
		// send fail message to user
		$message = "We're sorry. but due to network issues, we cannot confirm that the store received your order, you may want to call ".$this->full_order['merchant']['phone_no']." to verify they did before heading over to pick up your items.";
		$to_addr = $this->full_order['user_email'];
		$subject = 'Update on your mobile order to '.$this->full_order['merchant_name'];
		myerror_log('ERROR EMAIL SENT TO USER!: '.$to_addr.' : '.$subject.' : '.$message);
		if (substr_count($to_addr, 'test_api') > 0 || $this->full_order['user_id'] < 1000)
			myerror_log("skip sending email, this is a test user");
		else
		{
			MailIt::sendUserEmailMandrill($to_addr, $subject, $message, $this->full_order['merchant_name'], $bcc, $data);
		}	
	}

	function cleanMessageForSending(&$message_resource) {
	  return $message_resource;
	}

	function prepMessageForSending($message_resource)
	{
			if (isset($message_resource->message_text) && trim($message_resource->message_text) != '')
			{
				// we have a static message already formatted so add the static message template and return
				$message_resource->_representation = $this->static_message_template;
				$resource = clone $message_resource;
				$resource->set('static','true');
				// user_id if there is an order HACK!
				if (isset($message_resource->order_id) && $message_resource->order_id > 1000)
				{
                    $this->buildOrder($message_resource->order_id);
					if ($this->full_order['user_id'] > 0) {
                        $resource->set('user_id',$this->full_order['user_id']);
                    }
				}

				if (isset($this->full_order['merchant'])) {
                    $this->merchant = $this->full_order['merchant'];
                } else if ($merchant_id = $message_resource->merchant_id) {
				    $merchant = MerchantAdapter::staticGetRecordByPrimaryKey($merchant_id,'MerchantAdapter');
				    $this->merchant = $merchant;
                }
			} else {
				$resource = $this->populateMessageData($message_resource);
// CHANGE THIS  --  need to remove the 'U' logic once we completely migrate
				if (substr($message_resource->message_format, 1,1) == 'U')
				{				  
					// ok we have a new universal template so get the three parts
                    $resource = $this->cleanMessageForSending($resource);

					myerror_logging(3, "starting the new universal format logic with format of: ".$message_resource->message_format);
					$resource->_representation = '/order_templates/universal/universal_text_based_header.txt';
                    if ($merchant_delivery_price_distance_id = $resource->merchant_delivery_price_distance_id) {
                        if ($mdpd_record = MerchantDeliveryPriceDistanceAdapter::staticGetRecordByPrimaryKey($merchant_delivery_price_distance_id,'MerchantDeliveryPriceDistanceAdapter')) {
                            if (strtolower($mdpd_record['name']) == 'doordash') {
                                $resource->_representation = '/order_templates/universal/universal_text_based_header_doordash.txt';
                            }
                        }
                    }
					$representation_header = $resource->loadRepresentation(new FileAdapter($this->mimetypes, 'resources'));
					$header = $representation_header->_getContent();
					
					if (substr_count($this->request->url,'/f.txt') > 0 )
					{
						// do thing since this is a < firmware 7.0 printer and we need the breaks
					} else {
						// remove breaks from header
						$header = str_replace("::    ------", "", $header);						
					}
					
					$resource->_representation = '/order_templates/universal/universal_text_based_footer.txt';
					$representation_footer = $resource->loadRepresentation(new FileAdapter($this->mimetypes, 'resources'));
					$footer = $representation_footer->_getContent();

					//$resource = $this->cleanMessageForSending($resource);
					
					if (substr($message_resource->message_format, 2,1) == 'E')
						$resource->_representation = '/order_templates/universal/universal_exceptions.txt';
					else if (substr($message_resource->message_format, 2,1) == 'A')
						$resource->_representation = '/order_templates/universal/universal_all.txt';
					else if (substr($message_resource->message_format, 2,1) == 'W')
						$resource->_representation = '/order_templates/universal/universal_with.txt';
					else if (substr($message_resource->message_format, 2,1) == 'C')
						$resource->_representation = '/order_templates/universal/universal_complete.txt';
					
					$representation_details = $resource->loadRepresentation(new FileAdapter($this->mimetypes, 'resources'));
					$details = $representation_details->_getContent();
					
					$complete_body = $header.$details.$footer;
					
					// now call the formatter if there is one for the respective controller.
					$complete_body = $this->formatProcessor($complete_body);
					$resource->message_text = $complete_body;
					$resource->_representation = $this->static_message_template;
				} else {
					// this is either an old format message or is a custom message so get the template from the array.
					myerror_log("starting custom template for order: ".$message_resource->message_format);
                    $resource = $this->cleanMessageForSending($resource);
                    $resource = $this->loadMessageBody($resource,$message_resource);
                    if ($resource == false) {
                        return false;
                    }
				}
			}
			$resource->set('loaded','true');
				
			$resource->set('message_id',$message_resource->map_id);
			$resource->set('message_delivery_addr',$message_resource->message_delivery_addr);
			myerror_logging(3,"finishing prep message for sending logic");
			return $resource;		
	}

	function loadMessageBody($resource,$message_resource)
    {
        if ($resource->_representation = $this->getRepresentationFromMessageFormatOfMessageResource($message_resource)) {
            myerror_log("in messagecontroller custom teplate the representation is: ".$resource->_representation,3);
        } else {
            $error_code = 11;
            if ($message_resource->message_type == 'X')
                $error_code = 10;
            $e = new Exception("no representation association in array for: ".$message_resource->message_format, $error_code);
            return $this->processError($e);// need to throw error here.
        }
        if ($representation = $resource->loadRepresentation(new FileAdapter($this->mimetypes, 'resources'))) {
            $complete_body = $representation->_getContent();
            $resource->message_text = $complete_body;
            $resource->_representation = $this->static_message_template;
        }
        return $resource;
    }

    function getRepresentationFromMessageFormatOfMessageResource($message_resource)
    {
        $representation = $this->representation;
        if (isset($this->format_array[$message_resource->message_format])) {
            $representation = $this->format_array[$message_resource->message_format];
        }
        return $representation;

    }
	
	/**
	 * 
	 * @desc old method for getting message body.  use prepMessageForSending
	 * 
	 * @deprecated
	 * @param message_resource $resource
	 * @throws Exception
	 */
	private function getMessageBody($resource)
	{
			$file_adapter = new FileAdapter($this->mimetypes, 'resources');
			$representation =& $resource->loadRepresentation($file_adapter);
			if ($resource && $representation)
				$response =& $representation->get($request);
			else
			{
				myerror_log("ERROR BUILDING MESSAGE IN MESSAGE CONTROLLER.  no resource or no representation!");
				throw new Exception("Error! No resource or no representation",10);
			}
			$body = $response->getOutputAsText();
			
			if ($this->log_level > 2)
				myerror_log("message body for order delivery: ".$body);
				
			return $body;
		
	}

	// abstract function.  to be defined in the child objects   Fax Controller, IvrController, etc...
	protected function send($message){}

	function markMessageAsViewed($message_id)
	{
		return $this->adapter->markMessageAsViewedById($message_id);
	}
	
	function markMessageDeliveredById($message_id)
	{
		$message_resource = Resource::find($this->adapter,''.$message_id);
		return $this->markMessageDelivered($message_resource);
	}

    /**
     * @desc this function should be overridden in the controllers that need to set viewed to 'N'
     */
	function setViewed(&$message_resource)
    {
        return true;
    }
	
	/**
	 * 
	 * Enter description here ...
	 * @param Resource $message_resource
	 * @param unknown_type $locked
	 * @throws Exception
	 */
	function markMessageDelivered($message_resource = null,$locked = 'S')
	{
		// if map_id != 0 we could do some code here to get the message and then update it..
		$now_is = date("Y-m-d H:i:s");
		if ($message_resource == null) {
			if ($this->message_resource == null) {
                throw new Exception("ERROR! cant mark message as delivered because message doens't exists");
            }
            $message_resource =& $this->message_resource;
        }
		myerror_log("now time is: $now_is");
		$message_resource->sent_dt_tm = $now_is;
		$message_resource->locked = $locked;
		$message_resource->modified = time();
		$this->setViewed($message_resource);
        if ($message_resource->save()) {
			if (isset($message_resource->order_id) && $message_resource->order_id > 1000 && $message_resource->message_type == 'X') {
				$this->updateOrderStatus($this->getMainMessageSuccessOrderStatus(),$message_resource->order_id);
			}
			return true;
		} else {
			$error_code = mysqli_errno();
			myerror_log("ERROR TRYING TO MARK MESSAGE AS DELIVERYED in messagecontroller: ".$message_resource->getAdapterError());
			myerror_log("error code is: ".mysqli_errno());
			if ($error_code == 0) {
				throw new Exception("ERROR! message already marked as delivered. probably due to removal of table locks",21);
            } else {
                throw new Exception("ERROR!  message could not be marked as delivered: " . $message_resource->getAdapterError(), 20);
            }
		}	
	}

	function getMainMessageSuccessOrderStatus()
	{
		return 'E';
	}
	
	function updateOrderStatus($status,$order_id = 0)
	{
		if ($order_id == 0)
		{
			if (isset($this->full_order['order_id']) && $this->full_order['order_id'] > 1000)
				$order_id = $this->full_order['order_id'];
			else
				throw new Exception("ERROR!  order status could not be marked as ".$status." as no order_id was submitted",30);
		}
		if ($status == 'E') {
			return OrderController::processExecutionOfOrderByOrderId($order_id);
		} else {
			return OrderAdapter::updateOrderStatus($status, $order_id);	
		}
		
	}

	function buildOrder($order_id)
	{
		$this->full_order = CompleteOrder::staticGetCompleteOrder($order_id, $this->mimetypes);
	}
	
	function getErrorMessage()
	{
		return $this->error_message;
	}
	
	function getErrorCode()
	{
		return $this->error_code;
	}
	
	function emailorderexecutionerror($message)
	{
		if ($order_id = $this->message_resource->order_id)
			$order_text = 'ORDER ';
		else
			$order_id = 0;
		
		myerror_log("format name: ".$this->format_name);
		$merchant = $this->message_data['merchant'];
    
    if (strtolower($this->format_name) == 'ping' && $order_id < 100) {
			$merchant_id = $this->message_resource->merchant_id;
			$merchant_resource = Resource::find(new MerchantAdapter($this->mimetypes),''.$merchant_id);
			$merchant_id = $merchant_resource->merchant_id;
			$merchant_name =$merchant_resource->name;
			$phone = $merchant_resource->phone_no;
			$addr = $merchant_resource->address1;
			$city_st_zip = $merchant_resource->city.', '.$merchant_resource->state.' '.$merchant_resource->zip; 
		} else {
			$merchant_name = $this->full_order['merchant']['name'];
			$addr = $this->full_order['merchant']['address1'];
			$city_st_zip = $this->full_order['merchant_city_st_zip'];
			$phone = $this->full_order['merchant']['phone_no'];
			$merchant_id = $this->full_order['merchant_id'];
		}
		$top = '';
		if ($order_id > 1000)
			$top .= "order_id = ".$order_id."<br>";
		$top .= "merchant_id = ".$merchant_id."<br>";
		$top .= "merchant = ".$merchant_name."<br>";
		$top .= $addr."<br>"; 
		$top .= $city_st_zip."<br>";
		$top .= $phone."<br>";
		$top .= "<p>";
		if ($order_id > 1000)
		{
			$top .= "user_id = ".$this->full_order['user_id']."<br>";
			$top .= "email = ".$this->full_order['user_email'];
			$sms_message = $this->format_name.' ORDER execution sending Error!'.chr(10).'phone= '.$phone.chr(10).'order_id='.$order_id.chr(10).''.$merchant_name.chr(10).$addr.chr(10).$city_st_zip;
		} else {
			$sms_message = $this->format_name.' sending Error!'.chr(10).''.$merchant_name.chr(10).$addr.chr(10).$city_st_zip;
		}
				
		$the_message = "<html><body>".$message."<p><p>".$top."</body></html>";
		$subject = $this->format_name.' '.$order_text.'execution sending error!   ';
		if ($this->format_name == 'rewardr') {
			recordError("Rewardr Execution Error!", $message);
		} else {
			myerror_log("WE are in the send error of message controller, about to check the user_id: ".$this->full_order['user_id']);
			// ok make sure we're not getting a positive feedback cycle here
			if ($this->format_name != 'email') {
				MailIt::sendErrorEmailSupport($subject, $the_message);
			}

			if ($this->format_name == 'brink') {
			    myerror_log("about to send direct brink failure message to cary  subject : $subject");
			    MailIt::sendErrorEmailToIndividual('krussell@dummy.com',$subject,$the_message);
            }
			
			if ($order_id > 1000 && $this->full_order['user_id'] > 100) {	
				SmsSender2::sendAlertListSMS($sms_message);
			}
		}
	}

	function hasMessagingError()
	{
		return $this->has_messaging_error;
	}
	
	function getNoPulledMessageAvailableResponse()
	{
		$response = new Response(200);
		return $response;
	}

	function setMessageResourceResponse($response)
	{
		if ($this->message_resource->response == null || trim($this->message_resource->response) == '' )
			$this->message_resource->response = $response;
		else
			$this->message_resource->response = $this->message_resource->response.';'.$response;
	}
	
	function getFormatArray()
	{
		return $this->format_array;
	}
	
	function getFormat()
	{
		return $this->format;
	}
	
	function setMessageResource($message_resource)
	{
		$this->message_resource = $message_resource;
	}
	
	function getAllMessagesForOrderId($order_id)
	{
		return MerchantMessageHistoryAdapter::getAllOrderMessages($order_id);	
	}
	
	function getMerchantIdForCurrent()
	{
		if (isset($this->full_order['merchant_id'])) {
			$merchant_id = $this->full_order['merchant_id'];
		} else if (isset($this->merchant['merchant_id'])){
			$merchant_id = $this->merchant['merchant_id'];
		}
		return $merchant_id;
	}

    function getSOAPCleanSectionFromEnvelopeBodyAsHashMap($body,$tag)
    {
        return getSOAPCleanSectionFromEnvelopeBodyAsHashMap($body,$tag);
    }

    function getCleanPayloadFromXML($xml_string,$start,$end)
    {
        return getCleanPayloadFromXML($xml,$start,$end);
    }

}

class NullMerchantException extends Exception
{
    public function __construct($class_name) {
        parent::__construct("Null Merchant In Message Controller: $class_name", 999);
    }
}

class NullMerchantExternalIdException extends Exception
{
    public function __construct($class_name) {
        parent::__construct("Null merchant_external_id in Message Controller: $class_name", 999);
    }
}

?>