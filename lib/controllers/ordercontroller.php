<?php

class OrderController extends SplickitController
{
	var $maitre_d_refund_message_id;
	private $full_order;
	private $merchant;
	private $order_user;
	private $cc_functions;
	private $reassign_error;
	private $allowed_update_fields = array("tip_amt"=>1,"note"=>1,"status"=>1);

	private $submitted_ucid;

	var $ucid;

    /**
     * @var Order
     */
	var $order;

    var $user;


    const ORDER_REFUNDED_MESSAGE = "The order has been refunded.";
    const ORDER_ID_DOES_NOT_EXIST_ERROR_MESSAGE = "Sorry, the submitted order id does not exist in our system.";
    const NO_ORDER_ID_SUBMITTED_ERROR_MESSAGE = "No order id submitted.";
    const ORDER_SENT_TO_NEW_DESTINATION_MESSAGE = "The order has been sent to the new destination.";
    const ORDER_HAS_BEEN_RESENT_TO_THE_DESTINATION = "The order has been re-sent to the merchant.";
    const ORDER_AUTHORIZATION_HAS_BEEN_CAPTURED_MESSAGE = "The order authorization has been captured";
    const ORDER_HAS_BEEN_REASSIGNED_MESSAGE = "The order has been reassigned to the new merchant";
    const ORDER_HAS_ALREADY_BEEN_VOIDED_MESSAGE = "Sorry, this transaction has already been voided, please check your records.";

    function returnSuccessWithMessageResource($message,$data = array())
    {
        $result = array("result" => 'success',"message"=>$message);
        return Resource::dummyfactory(array_merge($result,$data));
    }

    function getNoLoadedOrderFailureResource()
    {
        $error_message = isset($this->submitted_ucid) ? OrderController::ORDER_ID_DOES_NOT_EXIST_ERROR_MESSAGE.' '.$this->submitted_ucid : OrderController::NO_ORDER_ID_SUBMITTED_ERROR_MESSAGE;
        return $this->returnFailureWithMessageResource($error_message,422,array("result"=>"failure"));
    }

    function returnFailureWithMessageResource($error_message,$http_code,$data = array())
    {
        $result = array("result" => 'failure',"error_message"=>$error_message,"http_code"=>$http_code);
        return Resource::dummyfactory(array_merge($result,$data));
    }

    function OrderController($mt,$u,&$r,$l = 0)
	{
		parent::SplickitController($mt,$u,$r,$l);
		$this->adapter = new OrderAdapter($this->mimetypes);
        if (preg_match('%/orders/([0-9]{4}-[0-9a-z]{5}-[0-9a-z]{5}-[0-9a-z]{5})%', $r->url, $matches)) {
            $ucid = $matches[1];
            $this->submitted_ucid = $ucid;
            try {
                $this->setOrderAndMerchantByUcid($ucid);
            } catch (NoMatchingOrderIdException $e) {
                myerror_log("could not get order from id: $ucid");
            }
        }
        $_SERVER['log_level'] = $l;
	}

    function setOrderAndMerchantByUcid($ucid)
    {
        $this->ucid = $ucid;
        $this->order = new Order($ucid);
        $this->user = getUserFromId($this->order->getUserId());
        if ($this->merchant == null) {
            //$this->merchant = $this->getMerchantResourceAsRecord($this->order->get('merchant_id'));
            $this->merchant = Resource::find(new MerchantAdapter(), '' . $this->order->get('merchant_id')) ->getDataFieldsReally();
        }

    }

    function processV2Request()
    {

        /* ALL METHODS BELOW HERE REQUIRE LOADED ORDER */
        if ($this->order) {
            if ($this->hasRequestForDestination('updateorderstatus')) {
                if ($status = isset($this->request->data['status']) ? $this->request->data['status'] : $this->request->data['order_status']) {
                    return $this->updateOrderStatusAdmin($status);
                } else {
                    return createErrorResourceWithHttpCode("No status submitted",422,422);
                }
            } else if ($this->hasRequestForDestination('resend') || $this->hasRequestForDestination('resendanorder')) {
                $new_destination_address = (isset($this->request->data['new_destination_address']) && trim($this->request->data['new_destination_address'] != '')) ? $this->request->data['new_destination_address'] : null ;
                return $this->resendAnOrder($new_destination_address);
            } else if ($this->hasRequestForDestination('refund') || $this->hasRequestForDestination('refundorder')) {
                return $this->refundOrder($this->request->data['refund_amount']);
            } else if ($this->hasRequestForDestination('captureauthorizedpayment')) {
                return $this->processOrderCaptureOfPreviousAuthorization();
            } else if ($this->hasRequestForDestination('reassignorder')) {
                $new_merchant_id = $this->request->data['new_merchant_id'];
                return $this->reassignOrderToNewMerchant($new_merchant_id);
            } else {
                return $this->getOrder();
            }
        } else {
            return $this->getNoLoadedOrderFailureResource();
        }

    }

    function getOrder()
    {
        $complete_order = $this->order->getCompleteOrder();
        return Resource::dummyfactory($complete_order);
    }

    function reassignOrderToNewMerchant($new_merchant_id)
    {
        if ($new_merchant_id < 1000) {
            return createErrorResourceWithHttpCode("No valid new merchant id passed in.",422,422);
        }
        if ($this->reassignAndSendOrder($this->order->getOrderId(), $new_merchant_id)) {
            return $this->returnSuccessWithMessageResource(OrderController::ORDER_HAS_BEEN_REASSIGNED_MESSAGE);
        } else {
            return createErrorResourceWithHttpCode($this->getReassignError(),500,500);
        }
    }

    function processOrderCaptureOfPreviousAuthorization()
    {
        $vio_payment_service = new VioPaymentService();
        try {
            $order_resource = $this->order->getOrderResource();
            $results = $vio_payment_service->processOrderCaptureOfPreviousAuthorization($order_resource,$order_resource->grand_total);
            if ($results['response_code'] == 100) {
                return $this->returnSuccessWithMessageResource(OrderController::ORDER_AUTHORIZATION_HAS_BEEN_CAPTURED_MESSAGE);
            } else {
                return createErrorResourceWithHttpCode("There was an error and the order could not be captured.",500,500);
            }
        } catch (Exception $e) {
            return createErrorResourceWithHttpCode($e->getMessage(),500,500);
        }
    }

    function refundOrder($refund_amt) {
        // validate user for order
        if ($this->request->data['user_id'] != $this->order->getUserId()) {
            return createErrorResourceWithHttpCode("Sorry, submitted order and user do not match.",422,422);
        }
        myerror_log("valid order and user for refund");
        $return = $this->issueOrderRefund($this->order->getOrderId(),$refund_amt);
        logData($return,"REFUND RETURN");
        if ($return['result'] == 'success') {
            return $this->returnSuccessWithMessageResource($return['message']);
        } else {
            return createErrorResourceWithHttpCode("There was an error and the order could not be refunded.",500,500);
        }
    }


    function resendAnOrder($new_destination_address)
    {
        myerror_log("valid order id");
        if ($new_destination_address) {
            if ($this->sendOrderToNewDestination($this->order->getOrderId(),$new_destination_address)) {
                $message = OrderController::ORDER_SENT_TO_NEW_DESTINATION_MESSAGE;
                return $this->returnSuccessWithMessageResource($message);
            }
        } else if ($this->resendOrder($this->order->getOrderId())) {
            $message = $message = OrderController::ORDER_HAS_BEEN_RESENT_TO_THE_DESTINATION;
            return $this->returnSuccessWithMessageResource($message);
        }
        return createErrorResourceWithHttpCode("There was an error and the order could not be resent.",500,500);
    }

    function updateOrderStatusAdmin($status)
    {
        $order_resource = $this->order->getOrderResource();
        if ($this->order->getUserId() < 20000 && $status == 'E') {
            $status = "T";
            $test_user_message = ", since it was a test user.";
        } else {
            $test_user_message = ".";
        }
        $order_resource->status = $status;
        $order_resource->modified = time();
        if ($order_resource->save()) {
            $message = "The order status has been changed to '".$status."'".$test_user_message;

            // do we want to shut down any NON pulled messages here to avoid alarms?
            if ($status == 'E' || $status == 'N' || $status == 'T' || $status == 'C') {
                $mha = new MerchantMessageHistoryAdapter(getM());
                $mha_options[TONIC_FIND_BY_METADATA]['order_id'] = $order_resource->order_id;
                if ($message_resources = Resource::findAll($mha,null,$mha_options)) {
                    foreach ($message_resources as $message_resource) {
                        $recordHasModified = false;
                        if($status == 'E' && $message_resource->viewed != null){
                            $message_resource->viewed = 'V';
                            $recordHasModified = true;
                        }
                        if($message_resource->locked == 'P' || $message_resource->locked == 'N'){
                            $message_resource->locked = 'C';
                            $message_resource->modified = time();
                            $recordHasModified = true;
                        }

                        if($recordHasModified){
                            $message_resource->save();
                        }


                    }
                    $message .= ".  Any open messages have been cancelled";
                }
            }
            return $this->returnSuccessWithMessageResource($message);
        } else {
            $message = "ERROR! the order status could not be updated: ".$order_resource->_adapter->getAdapterError();
            return createErrorResourceWithHttpCode($message,500,500);
        }
    }

	function processRequest()
	{
		if (preg_match('%/orders/([0-9]{4}-[0-9a-z]{5}-[0-9a-z]{5}-[0-9a-z]{5})%', $this->request->url, $matches)) {
			$cart_ucid = $matches[1];
			$options[TONIC_FIND_BY_METADATA]['ucid'] = $cart_ucid;
			if ($order_resource = Resource::findExact(new OrderAdapter($m),"",$options)) {
				$this->user = array("user_id"=>$order_resource->user_id);
				return $this->updateOrderResourceFromRequestData($order_resource);
			}
		}
		return createErrorResourceWithHttpCode("No valid order id submitted.",422,999);
	}



	/**
	 * @param $order_resoure
	 * @return Resource
	 */
	function updateOrderResourceFromRequestData($order_resource)
	{
	    if ($this->isThisRequestMethodADelete()) {
	        // we have and order Cancel
            $this->request->data = array("status"=>'cancel');
        }
		foreach ($this->request->data as $update_param=>$value) {
			if ($this->isValidUpdateParam($update_param)) {
				if ($update_param == 'tip_amt') {
					// first determine if this is allowed for this order
					if ($order_resource->tip_amt > 0) {
						$error_message = "A ".$order_resource->tip_amt." tip has already been applied to order: ".$order_resource->ucid;
						return createErrorResourceWithHttpCode($error_message,422,999);
					}
					if ($bcr = BalanceChangeAdapter::staticGetRecord(array("order_id"=>$order_resource->order_id,"process"=>"authorize","notes"=>"PENDING"),"BalanceChangeAdapter")) {
					    if ($value >= $bcr['charge_amt']) {
					        // first capture auth then do a purchase on the tip
                            $delayed_tip_amount = $value;
                        } else {
                            if (! $this->updateOrderResourceWIthUpdateParam($order_resource,$value,$update_param)) {
                                throw new Exception("COULD NOT UPDATE TIP ON ORDER!!!!!!!!!!");
                            }
                        }
                        $vio_payment_service = new VioPaymentService($data);
                        try {
                            $capture_response = $vio_payment_service->processOrderCaptureOfPreviousAuthorization($order_resource, $order_resource->grand_total);
                            if ($delayed_tip_amount) {
                                $data = array("merchant_id"=>$order_resource->merchant_id);
                                $vio_payment_service = new VioPaymentService($data);
                                $result = $vio_payment_service->processAddTipPayment($order_resource,$value);
                                if ($result['response_code'] == 100) {
                                    $this->updateOrderResourceWIthUpdateParam($order_resource,$delayed_tip_amount,$update_param);
                                }
                            }
                        } catch (CaptureErrorException $e) {
                            // reset the tip
                            if ($delayed_tip_amount) {
                                // skip it
                            } else {
                                $order_resource->tip_amt = 0.00;
                                $order_resource->grand_total = $order_resource->grand_total - $value;
                                $order_resource->save();
                            }

                            MailIt::sendErrorEmailAdam("We had a Capture failure on an executed Order.", $e->getMessage() . ". Order_id: " . $order_resource->order_id);
                            MailIt::sendErrorEmailSupport("We had a Capture failure on an executed Order.", $e->getMessage() . ".  Order_id: " . $order_resource->order_id);
                            if ($vio_payment_service->exception && $vio_payment_service->calling_action != 'PlaceOrder') {
                                return createErrorResourceWithHttpCode($e->getMessage().'. Please try again.',422,999);
                            }
                        }
                        return $order_resource;
					} else if ($bcr = BalanceChangeAdapter::staticGetRecord(array("order_id"=>$order_resource->order_id,"process"=>"authorize","notes"=>"captured"),"BalanceChangeAdapter")) {
						$data = array("merchant_id"=>$order_resource->merchant_id);
						$vio_payment_service = new VioPaymentService($data);
						$result = $vio_payment_service->processAddTipPayment($order_resource,$value);
						if ($result['response_code'] == 100) {
                            $this->updateOrderResourceWIthUpdateParam($order_resource, $value, $update_param);
                            return $order_resource;
                        } else if ($vio_payment_service->exception) {
                            return createErrorResourceWithHttpCode($result['response_text'],500,999);
						} else {
                            return createErrorResourceWithHttpCode("We're sorry but the credit card was declined",422,999);
                        }
					} else {
						return createErrorResourceWithHttpCode("This order cannot be updated.",422,999);
					}
				} else if ($update_param == 'status') {
					$val = strtolower($value);
					if ($val == 'c' || $val == 'cancel') {
						if ($order_resource->status == OrderAdapter::ORDER_SUBMITTED) {
                            $order_resource->status = OrderAdapter::ORDER_CANCELLED;
                            $mmha = new MerchantMessageHistoryAdapter($m);
                            $mmha->cancelOrderMessages($order_resource->order_id);
                        } else if ($order_resource->status == 'G') {
						    $group_order_controller = new GroupOrderController(getM(),$this->user,$this->request);
						    $resource = $group_order_controller->cancelGroupOrder($order_resource->ucid);
						    return $resource;
						} else if ($order_resource->status == OrderAdapter::ORDER_PAYMENT_FAILED || $order_resource->status == OrderAdapter::ORDER_CANCELLED) {
							return createErrorResourceWithHttpCode("This order has already been canceled.",500,999);
						}
						$refund_result = $this->issueOrderRefund($order_resource->order_id, 0.00);
						if ($refund_result['status'] == 'failure') {
							MailIt::sendErrorEmailSupport("FAILURE TO REFUND","We failed refunding the order on a POS request to cancel an order");
						}
					} else {
						return createErrorResourceWithHttpCode("Invalid order status submitted",422,999);
					}

					if ($order_resource->save()) {
						myerror_log("order was updated");
					} else {
						MailIt::sendErrorEmailSupport("ERROR in order_cancel","we couldn't adjust an order status in the cancel order code after we refuned. please investigate order_id: $order_id");
						MailIt::sendErrorEmailAdam("ERROR in order_cancel","we couldn't adjust an order status in the cancel order code after we refuned. please investigate order_id: $order_id");
					}
					return $order_resource;

				}
			}
		}
	}

	function updateOrderResourceWIthUpdateParam(&$order_resource,$value,$update_param)
    {
        $order_resource->$update_param = $value;
        $order_resource->grand_total = $order_resource->grand_total + $value;
        if ($order_resource->save()) {
            return true;
        } else {
            return false;
        }

    }

	function isValidUpdateParam($param_name)
	{
		return isset($this->allowed_update_fields["$param_name"]);
	}

		
	/**
	 * 
	 * @desc this will resend an order to a new destination.  currently only available for email and fax.  format is determined by the address. existence of '@' kind of thing
	 * 
	 * @param $new_destination_address 
	 */
	function sendOrderToNewDestination($order_id,$new_destination_address)
	{
	    // first need order info
		if ($order_resource = Resource::find($this->adapter,''.$order_id))
			;//all is good
		else
			return "ERROR!  Order Id does not exists";
		
		$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
		if (substr_count($new_destination_address, '@') > 0)
			$message_format = 'E';
		else
			$message_format = 'FUA';
			
		$message_type = 'O';
		$next_message_dt_tm = time()-1;
		$info = 'Resend_To_New_Destination;';
		$result = $mmh_adapter->createMessage($order_resource->merchant_id, $order_id, $message_format, $new_destination_address, $next_message_dt_tm, $message_type, $info, $message_text);
		
		return $result;
	}
	
	function resendOrder($order_id)
	{
		$options[TONIC_FIND_BY_METADATA]['order_id'] = $order_id;
		$mmha = new MerchantMessageHistoryAdapter($this->mimetypes);
		if ($message_resources = Resource::findAll($mmha,'',$options))
		{
			foreach ($message_resources as $message_resource)
			{
				$format = $message_resource->message_format;
                $base_format = substr($format,0,1);
				$message_type = $message_resource->message_type;
				if ($format == 'R' || $format == 'CC' || $format == 'IB' || $format == 'FC')
					continue;
				if ($message_type == 'I' && $base_format == 'E') {
                    if ($message_resource->locked == 'S') {
                        continue;
                    }
                } else if ($message_type == 'I' || $message_type == 'X2')
					continue;
				//$message_resource = Resource::find($mmha,''.$message_record['map_id']);
				$message_resource->sent_dt_tm = '0000-00-00 00:00:00';
				$message_resource->next_message_dt_tm = time();
				$message_resource->modified= time();
				$message_resource->locked = 'N';
				myerror_log("the format of this message is $format.  the trimmed version is: ".substr($format,0,1));
				$base_format = substr($format,0,1);
				if ($base_format == 'U' || $base_format == 'R' || $base_format == 'W' || $base_format == 'H' || $base_format == 'S' || $base_format == 'P') {
					$message_resource->locked = 'P';
					$message_resource->tries = $message_resource->tries + 100;
					if ($base_format == 'G' || $base_format == 'H') {
						$message_resource->message_text = 'nullit';
					} else if ($base_format == 'P') {
					    $message_resource->viewed = 'nullit';
                    }
                    $message_resource->viewed = "nullit";
				} else if ($base_format == 'X' || $base_format == 'B') {
					$message_resource->message_text = 'nullit';
				}
				$message_resource->save();
			}
			return true;
		} else {
			return false;
		}
	}

    /**
     * @param $order_id
     * @param $new_merchant_id
     * @return bool|int
     */
    function reassignAndSendOrder($order_id,$new_merchant_id)
	{
		$order_resource = SplickitController::getResourceFromId($order_id, 'Order');

		// get make sure original order is sage
		$sql = "SELECT a.name FROM Vio_Credit_Card_Processors a JOIN Billing_Entities b ON a.id = b.vio_credit_card_processor_id JOIN Merchant_Payment_Type_Maps c ON b.id = c.billing_entity_id WHERE c.splickit_accepted_payment_type_id = 2000 AND c.merchant_id = ".$order_resource->merchant_id;
		$vcp_adapter = new VioCreditCardProcessorsAdapter(getM());
		$options[TONIC_FIND_BY_SQL] = $sql;
		$results = $vcp_adapter->select('',$options);
		if (strtolower($results[0]['name']) != 'sage') {
			$this->reassign_error = "Original Merchant is not a Sage Merchant. Reassign can only be done for sage";
			myerror_log("NOT A SAGE MERCHANT CANNOT REASSIGN ORDER");
			return false;
		}


		$sql = "SELECT a.name FROM Vio_Credit_Card_Processors a JOIN Billing_Entities b ON a.id = b.vio_credit_card_processor_id JOIN Merchant_Payment_Type_Maps c ON b.id = c.billing_entity_id WHERE c.splickit_accepted_payment_type_id = 2000 AND c.merchant_id = $new_merchant_id ";
		$options[TONIC_FIND_BY_SQL] = $sql;
		$results = $vcp_adapter->select('',$options);
		if (strtolower($results[0]['name']) != 'sage') {
			$this->reassign_error = "New Merchant is not a Sage Merchant. Reassign can only be done for sage";
			myerror_log("NOT A SAGE MERCHANT CANNOT REASSIGN ORDER");
			return false;
		}

		$old_merchant_id = $order_resource->merchant_id;
        myerror_log("resetting order to new merchant. original merchand_id:$old_merchant_id   new merchant_id: $new_merchant_id");
		$order_resource->merchant_id = $new_merchant_id;
		if ($order_resource->save()) {
			$mmha = new MerchantMessageHistoryAdapter(getM());
			$mmha->cancelOrderMessages($order_id);

			$create_messages_controller = new CreateMessagesController(getM());
			$create_messages_controller->immediate_delivery = true;
			return $create_messages_controller->createOrderMessagesFromOrderInfo($order_id,$new_merchant_id,$l,$p);
		} else {
            $this->reassign_error = "Unable to save new order to new merchant. ".$order_resource->_adapter->getLastErrorText();
            myerror_log("Unable to save new merhcant to order. error: ".$order_resource->_adapter->getLastErrorText());
            return false;
        }
	}

	function getReassignError()
	{
		return $this->reassign_error;
	}

	function updateOrderStatus($status = OrderAdapter::ORDER_EXECUTED)
	{
		if ($this->updateOrderStatusById($this->full_order['order_id'],$status)) {
			// now need to mark the message as delivered
			$mha = new MerchantMessageHistoryAdapter($this->mimetypes);
			if ($message_resource = Resource::find($mha, $this->full_order['message_map_id'])) {
                $message_resource->sent_dt_tm = time();
                $message_resource->save();
            }
			return true;
		} else {
			myerror_log("ERROR TRYING TO UPDATE THE ORDER STATUS IN ordercontroller");
			return false;
		}
	}
	
	function updateOrderStatusById($order_id,$status = OrderAdapter::ORDER_EXECUTED)
	{
		$resource = Resource::find($this->adapter,"$order_id");
		$resource->status = $status; 
		return $resource->save();
	}

	static function processExecutionOfOrderByOrderId($order_id)
	{
		$order_adapter = new OrderAdapter($m);
		$resource =& Resource::find($order_adapter, $order_id);
		if ($resource->status == OrderAdapter::GROUP_ORDER) {
			$order_adapter->markChildOrdersExecutedIfNecessary($resource);
			return true;
		} else {
			$resource->status = OrderAdapter::ORDER_EXECUTED;
			return $order_adapter->updateOrderResource($resource);
		}
	}
	
	function formatOrder($order_delivery_type = 'Z')
	{
		if ($this->log_level > 1)
			myerror_log("starting the formatOrder");
		
		$new_order = $this->full_order;
		$merchant = $this->merchant;

		// first get type of delivery if its not passed in
		if ($this->request->data['delivery_type'] && $this->request->data['delivery_type'] != NULL)
			$order_delivery_type = $this->request->data['delivery_type'];
		else if ($order_delivery_type == 'Z')
			$order_delivery_type = $merchant['order_del_type'];
		else
			; // do nothing, the delivery type was passed in
			
		//now create the resource
		$resource = new Resource($this->adapter, $new_order);
		
		if ($this->log_level > 1)
			myerror_log("order del type: ".$order_delivery_type);
		if ($order_delivery_type == 'P')
		{
			// deliver with a text to voice phone call
			$resource->_representation = '/order_templates/ivr/ivr_complete.xml';
// exception for test server
			if ($_SERVER['SERVER_NAME'] = 'test')
				$resource->_representation = '/order_templates/ivr/ivr_custom.xml';
			myerror_log("resource set in Order Controller");
			return $resource;
		} else if ($order_delivery_type == 'W') {
			$resource->_representation = '/order_templates/windows_service/windows_service_full.xml';
			myerror_log("resource set");
			return $resource;
		} else if ($order_delivery_type == 'E') {
			$resource->_representation = '/email_templates/customer_receipt.htm';
			myerror_log("resource set");
			return $resource;
		}
        return null;
	}

	function loadNextOrderByMerchant($merchant_id)
	{
		//if ($_SERVER['SERVER_NAME'] == 'test.splickit.com')
		$now_string = date('Y-m-d H:i:s');
		$messages_to_send_adapter = new MessagesToSendAdapter($this->mimetypes);
		$order_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
		$order_options[TONIC_FIND_BY_METADATA]['next_message_dt_tm'] = array('<'=>$now_string);
		$order_options[TONIC_SORT_BY_METADATA] = 'next_message_dt_tm DESC';
		if ($orders = $messages_to_send_adapter->select('',$order_options))
		{
			//  since last order is the earliest to be delivered. pop it off the end of the array
			$order = array_pop($orders);
			$this->buildOrder($order['order_id']);
			$this->full_order['message_map_id'] = $order['map_id'];
			return true;
		} else {
			return false;
		}
	}
	
	function getAmtBilledToCC($order_id)
	{
		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		$bc_options[TONIC_FIND_BY_METADATA]['order_id'] = $order_id;
		$bc_options[TONIC_FIND_BY_METADATA]['process'] = 'CCpayment';
		if ($balance_change = $balance_change_adapter->select('',$bc_options))
		{
			$balance_change = array_pop($balance_change);
			$amt_billed_CC = $balance_change['charge_amt'];
		}
		else
			$amt_billed_CC = 0.00;
		return $amt_billed_CC;
		
	}
	
	function buildOrder($order_id)
	{
		$full_order = CompleteOrder::staticGetCompleteOrder($order_id, $this->mimetypes);
		$this->full_order = $full_order;
		$this->merchant = $full_order['merchant'];
		$this->order_user = $full_order['user'];
	}

	function issueOrderRefundNew($user,$order_id,$refund_amt) {
		myerror_log("starting the new order refund code for order_id: $order_id");
		if ($user)
		{		
			if ($data = $this->issueOrderRefund($order_id, $refund_amt)) {
				return $data;
			} else {
				$data['error'] = "There was an unknown error!";
				$data['result'] = "falure";
			}
		} else if ($this->request->data['user_id']) {		
			$data['error'] = "NO MATCHING USER FOUND";
			$data['result'] = "failure";
			//die ("NO USER ID OR BAD USER ID SUBMITTED");
		} else {
			$data['error'] = "NO USER SUBMITTED";
			$data['result'] = "failure";
		}
		return $data;		
	}

    /**
     * @param $order_id
     * @param $refund_amt
     * @param bool $send_email
     *
     * @return array()
     */
    function issueOrderRefund($order_id,$refund_amt,$send_email = true) {
        myerror_logging(1, "Starting the orderRefundCode in OrderController for order_id: $order_id");
		// refund_amt of 0.00 means refund the entire order
		if ($order_id == null)
			return $this->createRefundReturn("failure", "NO ORDER ID");
		
		$order_resource = SplickitController::getResourceFromId($order_id, "Order");
		
		if ($order_resource == null)
		{
			myerror_log("ERROR! no matching order id was found in issueOrderRefund");
			return $this->createRefundReturn("failure", "Sorry, no matching order id was found.");
		} else if ($order_resource->user_id != $this->user['user_id']) {
			myerror_log("ERROR! User id does not match that on the order");
			return $this->createRefundReturn("failure", "Sorry, that email address does not correspond to that order id. ".$this->user['email']." -  order_id: ".$order_resource->order_id);
		}
				
		myerror_log("ok! good order_id and user_id for refund.  now process the refund");
		$user = $this->user;
		// get skin info 
		$skin_resource = SplickitController::getResourceFromId($order_resource->skin_id, "SkinLight");
        setGlobalSkinValuesForContextAndDevice($skin_resource->getDataFieldsReally(),'AdminDispatch');
        setBrandFromCurrentSkin();

		
		// get merchant resource
		$merchant_resource = SplickitController::getResourceFromId($order_resource->merchant_id, 'Merchant');
		
		$balance_change_adapter = new BalanceChangeAdapter($this->mimetypes);
        $is_authorize = false;
        $is_cc_type = false;
		if ($order_resource->cash == 'Y') {
		    // so just cancel the order and reset the loyalty if applicable
            if ($this->isOrderResourceInSubmittedState($order_resource)) {
                $order_resource->status = OrderAdapter::ORDER_CANCELLED;
                $order_resource->stamp = getStamp().';'.$order_resource->stamp;
                $order_resource->save();
                $mmh_adapter = new MerchantMessageHistoryAdapter(getM());
                $mmh_adapter->cancelOrderMessages($order_resource->order_id);
            }
            if ($loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext($user)) {
                $loyalty_controller->reverseLoyaltyTransactionsForOrderId($order_id);
            }
            $bc_r = BalanceChangeAdapter::staticAddRow($order_resource->user_id, 0.00, 0.00, 0.00, 'Cash Order Cancelled', null, $order_resource->order_id, null, 'Order Cancelled '.$this->request->data['note']);
            $return_fields = [];
            $return_fields['balance_change_id'] = $bc_r->id;
            $return_fields['response_code'] = 100;
            $return_fields['message'] = "The order has been cancelled";
            $return_fields['result'] = "success";
            return $return_fields;
        } else if ($balance_change_row = $balance_change_adapter->getCurrentRecordForVoidOrRefund(array("order_id"=>$order_id,"process"=>"CCpayment"))) {
            myerror_log("we have the balance change row and it is a CC payment");
            $is_cc_type = true;
        } else if ($balance_change_row = $balance_change_adapter->getCurrentRecordForVoidOrRefund(array("order_id"=>$order_id,"process"=>"Levelup"))) {
            myerror_log("we have the balance change row and it is a Levelup payment");
        } else if ($balance_change_row = $balance_change_adapter->getCurrentRecordForVoidOrRefund(array("order_id"=>$order_id,"process"=>"StoredValuePayment"))) {
		    myerror_log("we have the balance change row and it is a Stored Value payment");
		    if ($refund_amt == 0.00) {
		        myerror_log("refunding full amount");
            } else if ($balance_change_row['charge_amt'] != $refund_amt) {
                return $this->createRefundReturn("failure", "Error! The refund amount, $refund_amt, cannot be different than the total amount the card was originally run for: ".$balance_change_row['charge_amt']);
            }
		    /*********** this should get moved to the appropriate payment object (curently sts) ********/
            $is_stored_value = true;
            $auth_trans = $balance_change_row['cc_transaction_id'];
            $s = explode(':',$auth_trans);
            $auth_reference = $s[0];
            $transaction_id = $s[1];
            if ($balance_change_row['notes'] == 'VOIDED' || $auth_reference == 'void') {
                return $this->createRefundReturn("failure", self::ORDER_HAS_ALREADY_BEEN_VOIDED_MESSAGE);
            }
            $sts_service = new StsPaymentService();
            $results = $sts_service->voidTransaction($auth_reference,$transaction_id);
            /********************/

            logData($results, 'Refund Return');
            if ($results['response_code'] == 100) {
                $sql = "UPDATE Balance_Change SET notes = 'VOIDED', cc_transaction_id = 'void:$auth_trans' WHERE id = ".$balance_change_row['id']." LIMIT 1";
                if ($balance_change_adapter->_query($sql)) {
                    myerror_log("Successfull change of Balance_Change row to voided");
                }
                $return_fields['message'] = "The order has been refunded";
                $return_fields['result'] = "success";
                return $return_fields;
            } else {
                return $this->createRefundReturn("failure", "Error! cannot void/refund order, please contact customer service");
            }
        } else if ($balance_change_row = $balance_change_adapter->getCurrentRecordForVoidOrRefund(array("order_id"=>$order_id,"process"=>"Authorize"))) {
		    myerror_log("we have the balance change row and it is an Authorize row");
		    if (strtolower($balance_change_row['notes']) != 'pending' && strtolower($balance_change_row['notes']) != 'captured') {
                return $this->createRefundReturn("failure", "This request cannot be processed, the authorize row is not in a refundable state");
            }
            $is_cc_type = true;
            if ($refund_amt != 0.00 && $balance_change_row['charge_amt'] > $refund_amt) {
                myerror_log("partial refund of authorization so first capture");
                try {
                    $vio_payment_service = new VioPaymentService($data);
                    $capture_response = $vio_payment_service->processOrderCaptureOfPreviousAuthorization($order_resource, $order_resource->grand_total);
                    if ($capture_response['response_code'] != 100) {
                        throw new CaptureErrorException("There was an error capturing the initial order, so could not issue refund");
                    }
                    if ($balance_change_row = $balance_change_adapter->getCurrentRecordForVoidOrRefund(array("order_id"=>$order_id,"process"=>"CCpayment"))) {
                        myerror_log("we have the capture, now issue the partial refund");
                    } else {
                        return $this->createRefundReturn("failure", "cannot retreive new CCpayment row from balance change table after capture");
                    }
                } catch (CaptureErrorException $e) {
                    MailIt::sendErrorEmailAdam("We had a Capture failure on a partial refund.", $e->getMessage() . ". Order_id: " . $order_resource->order_id);
                    MailIt::sendErrorEmailSupport("We had a Capture failure on a partial refund.", $e->getMessage() . ".  Order_id: " . $order_resource->order_id);
                    return $this->createRefundReturn("failure", "This request cannot be processed, Capture Failure. ".$e->getMessage);
                }

            } else if ($balance_change_row['charge_amt'] < $refund_amt) {
                return $this->createRefundReturn("failure", "Error! The refund amount, $refund_amt, cannot be more than the total amount the card was originally run for: ".$balance_change_row['charge_amt']);
            } else {
                $is_authorize = true;
//                myerror_log("do auth reversal by sending void");
//                $sql = "UPDATE Balance_Change SET notes = 'CANCELED' WHERE id = ".$balance_change_row['id']." LIMIT 1";
//                if ($balance_change_adapter->_query($sql)) {
//                    myerror_log("Successfull change of Balance_Change Auth row to Canceled");
//                } else {
//                    MailIt::sendErrorEmailSupport("Error Cancelling Authorize","WE had an error updaing the balance change row from PENDING to CANCELED for an Authorize row. order_id: $order_id");
//                }
            }

		} else if ($balance_change_row = $balance_change_adapter->getCurrentRecordForVoidOrRefund(array("order_id"=>$order_id,"process"=>"Order"))) {
			myerror_log("we have the balance change row and it is an order on Splickit Credit");
		} else {
			myerror_log("ERROR! This request cannot be processed, no matching row in the balance change table for refund request");
			return $this->createRefundReturn("failure", "This request cannot be processed, no matching row in the balance change table.");
		}
		
		if ($is_cc_type && $balance_change_row['charge_amt'] < $refund_amt) {
			return $this->createRefundReturn("failure", "Error! The refund amount, $refund_amt, cannot be more than the total amount the card was originally run for: ".$balance_change_row['charge_amt']);
		} else if ($is_cc_type && $balance_change_row['cc_processor'] ==  null) {
			return $this->createRefundReturn("failure", "Error! cannot void/refund order, there is no listed CC processor");
		} else if ($is_cc_type && $balance_change_row['cc_transaction_id'] ==  null) {
			return $this->createRefundReturn("failure", "Error! cannot void/refund order, there is no listed cc_transaction_id");
		}	
		$order_grand_total = $order_resource->grand_total;
		// a refund_amt submitted of 0.00 means refund the entire purchase.
		if ($refund_amt == '0.00') {
			$refund_amt = ''.$balance_change_row['charge_amt'];
		}

		// see if there is a row in the adm_order_reversal table
		$aor_adapter = new AdmOrderReversalAdapter(getM());
		if ($this->request && $this->request->data['employee_name'] != null) {
			$note = $this->request->data['note'].' - '.$this->request->data['employee_name'];
		} else {
			$note = $this->request->data['note'];
		}

		if ($aor_adapter->checkForAlreadyInProcessRefundAndInitiateIfNot($order_id,$refund_amt,$note)) {
			myerror_log("Sorry, this order cannot be refunded in this manner, it has already been refunded or partially refunded");
			return $this->createRefundReturn("failure", "Sorry, this order cannot be refunded in this manner, it has already been refunded or partially refunded");
		}

		// hack for the moes debocle
		if ($this->request->data['force_charge_amount'] == 'true')
			$order_grand_total = $balance_change_row['charge_amt'];
		
		// situation 1:  the CC charge is the same as the order amt.  straight up refund
		if ($is_cc_type && $refund_amt <= $balance_change_row['charge_amt'])
		{
			if ($order_resource->status == OrderAdapter::ORDER_SUBMITTED) {
				// check to make sure this is a complete void or refund
				if ($balance_change_row['charge_amt'] != $refund_amt) {
					myerror_log("ERROR!  attempted partial on an open order!");
					return $this->resetAdmReversalTableAndCreateRefundReturn($order_id,"failure", "ERROR! This order has not yet executed. Either refund the entire order, or wait until it executes to process the partial refund.");
				}
			}

			// //get appropriate CC functions object
			if ($cc_functions = CreditCardFunctions::creditCardFunctionsFactory($balance_change_row['cc_processor'])) {
				$return_fields = $cc_functions->creditVoidTransaction($balance_change_row, $refund_amt, $merchant_resource);
				logData($return_fields, 'Refund Return');
			} else {
				return $this->createRefundReturn("failure", "Error! There is a data problem and we cannot find the correct payment processor to refund with: ".$balance_change_row['cc_processor']);
			}
			
			if ($return_fields['response_code'] == 100)
			{
			    // now we need to change the auth row if it existed
                if ($is_authorize) {
                    myerror_log("cancel pending auth row since void went through");
                    //$sql = "UPDATE Balance_Change SET notes = 'CANCELED' WHERE id = ".$balance_change_row['id']." LIMIT 1";
                    $sql = "UPDATE Balance_Change SET notes = 'CANCELED', process = 'AuthCancelled' WHERE id = ".$balance_change_row['id']." LIMIT 1";
                    if ($balance_change_adapter->_query($sql)) {
                        myerror_log("Successfull change of Balance_Change Auth row to Canceled");
                    } else {
                        MailIt::sendErrorEmailSupport("Error Cancelling Authorize","WE had an error updaing the balance change row from PENDING to CANCELED for an Authorize row. order_id: $order_id");
                    }

                }

				$bc_r = BalanceChangeAdapter::staticAddRow($order_resource->user_id, $balance_before, $refund_amt, $balance_after, $cc_functions->process_string, $cc_processor, $order_resource->order_id, $return_fields['transactionid'], 'Issuing a '.get_class($cc_functions).' '.$cc_functions->process.' from the API: '.$this->request->data['note']);
				$return_fields['balance_change_id'] = $bc_r->id;

				$reset_admin_reversal = false;
				if ($this->isOrderInRefundablestate($order_resource)) {
					$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
					$mmh_adapter->cancelOrderMessages($order_resource->order_id);
                    if ($order_resource->user_id < 20000) {
                        $order_resource->status = OrderAdapter::TEST_ORDER;
                    } else if ($this->isOrderResourceInSubmittedState($order_resource)) {
                        $order_resource->status = OrderAdapter::ORDER_CANCELLED;
                    } else {
                        $order_resource->status = OrderAdapter::ORDER_PAYMENT_FAILED;
                    }
					$order_resource->save();
					if (isset($cc_functions->process_string) && strtolower($cc_functions->process_string) == 'ccvoid') {
						$reset_admin_reversal = true;
					}
				} else if ($cc_functions->process == "VOID") {
				    if ($this->isOrderInPreExecutedState($order_resource)) {
                        $reset_admin_reversal = true;
                    }

                }
				if ($reset_admin_reversal) {
					$this->resetAdmReversalTable($order_id);
				} else {
					$aor_r = $aor_adapter->completeRefund($order_resource->order_id, $refund_amt, 'G', "Issuing a ".get_class($cc_functions)." ".strtolower($cc_functions->process)." from the API: ".$this->request->data['note'], $invoice);
					$return_fields['order_reversal_id'] = $aor_r->id;
				}

				$order_resource->set("refunded_amount",$refund_amt);

				// so now we need to check for MaitreD and if this is a matreD merchant, we need to create the Return Message to the POS
				if ($refund_amt == $balance_change_row['charge_amt'])
				{
					if (MerchantMessageMapAdapter::isMerchantMatreDMerchant($merchant_resource->merchant_id))
					{
						// ok so we need to schedule the MaitreD refund message
						$mmha = new MerchantMessageHistoryAdapter($mimetypes);
						$id = $mmha->createMessage($merchant_resource->merchant_id, $balance_change_row['order_id'], 'WMR', 'MatreD', time(), 'O', 'refund', $message_text,'P');
						$this->maitre_d_refund_message_id = $id;
					}
				}

				// now check for loyalty payment on home grown only for now
                // check history for this order and reverse all transactioans
                if ($loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext($user)) {
                    $loyalty_controller->reverseLoyaltyTransactionsForOrderId($order_id);
                }

			
				$order_resource->set("message_one","Your ".ucwords($skin_resource->skin_name)." order #".$order_resource->order_id." refund request has been processed.");
				$order_resource->set("message_two","$".$refund_amt." has been refunded to your card.");	
			} else if ($return_fields['response_code'] == 101) {
				// erase the inprocess record
				$this->resetAdmReversalTable($order_id);
				$info = "object=OrderController;method=executeScheduledPartialOrderRefund;thefunctiondatastring=$order_id,$refund_amt,".$this->request->data['note'];
				$doit_ts = $this->getNextElevenPmMountainTimeStamp();
				ActivityHistoryAdapter::createActivity("ExecuteObjectFunction", $doit_ts, $info, $activity_text);
				$order_resource->set("message_one","Your ".ucwords($skin_resource->skin_name)." order #".$order_resource->order_id.", has been scheduled for a refund of $".$refund_amt.".");
				$order_resource->set("message_two","Please note, this can take up to 24hrs to process.");
				
			} else {
				return $this->createRefundReturn("failure", $return_fields['responsetext']);
			}
		} else if ($order_resource->grand_total == -$balance_change_row['charge_amt']) {
			// situation 2: just recredit the user, CC was not run on this order.
			$user_controller = new UserController($mt, $user, $r,5);
			//if ($this->issueSplickitCredit($order_resource->grand_total,'Refund', $notes))
			$return_array = $user_controller->issueSplickitCredit($refund_amt, 'Refund', $notes,$order_id);
			if ($return_array['error'] == 'green')
			{
			    $aor_r = $aor_adapter->completeRefund($order_resource->order_id, $refund_amt, 'G', "Issuing a splickit credit refund from the API: ".$this->request->data['note'], $invoice);
				$return_fields['order_reversal_id'] = $aor_r->id;

				//$order_adapter->update($order_resource);
				myerror_log("Credit has been applied");
				$order_resource->set("credited_amount",$order_resource->grand_total);
				$order_resource->set("message_one","Your ".ucwords($skin_resource->skin_name)." order #".$order_resource->order_id." has been refunded.");
				$order_resource->set("message_two","$".$refund_amt." has been credited to your account.");
			} else {
				myerror_log("there was a problem crediting the user: ".$return_array['message']);
				return $this->createRefundReturn("failure", "SQL error crediting user: ".$return_array['message']);
			}
		} else {
			return $this->createRefundReturn("failure", "Sorry, this transaction requires a manual refund");
		}
		
		// all is good so email the user and return true
        if ($send_email) {
            $this->emailUserResultsOfCreditVoid($order_resource, $skin_resource, $user);
        }

		$return_fields['message'] = "The order has been refunded";
		$return_fields['result'] = "success";
		return $return_fields;
	}

	function isOrderInPreExecutedState($order_resource)
    {
        return $order_resource->status != OrderAdapter::ORDER_EXECUTED;
    }

	function isOrderInRefundablestate($order_resource)
    {
        return ($order_resource->status == OrderAdapter::ORDER_SUBMITTED || $order_resource->status == OrderAdapter::ORDER_PENDING || $order_resource->status == OrderAdapter::GROUP_ORDER);
    }

    function isOrderResourceInSubmittedState($order_resource)
    {
        return ($order_resource->status == OrderAdapter::ORDER_SUBMITTED || $order_resource->status == OrderAdapter::GROUP_ORDER);
    }

	function getNextElevenPmMountainTimeStamp()
	{
		$time_stamp = time();
		return $this->getNextElevenPMMountiainTimeStampFromSubmittedTimeStamp($time_stamp);
	}
	
	function getNextElevenPMMountiainTimeStampFromSubmittedTimeStamp($time_stamp)
	{
		$ts = getTimeStampForDateTimeAndTimeZone(23, 0, 0, date('m',$time_stamp), date('d',$time_stamp), date('Y',$time_stamp), "America/Denver");
		$next_eleven_pm_mountain_ts = ($time_stamp > $ts) ? $ts + (24*60*60) : $ts;
		return $next_eleven_pm_mountain_ts; 
	}
		
	function emailUserResultsOfCreditVoid($order_resource,$skin_resource,$user)
	{
		$order_resource->set("user",$user);
        $order_resource->_representation = '/email_templates/email_confirm_sean/refund_receipt_sean.htm';
		$representation =& $order_resource->loadRepresentation($this->file_adapter);
		$email_body = $representation->_getContent();
		myerror_logging(5,$email_body);
		if ($map_id = MailIt::sendUserEmailMandrill($user['email'], 'Account Status', $email_body, $skin_resource->skin_name, $bcc,$attachments)) {
			myerror_log("Success email sent to user (".$user['email'].") for refund.  message_map_id=".$map_id);
		} else {
			myerror_log("ERROR! failure to send email to user for refund. ".$user['email']);
		}		
	}
		
	static function executeScheduledPartialOrderRefund($function_data_string)
	{
		//throw new Exception("METHOD NOT BUILT", $code, $previous);
		$s = explode(',', $function_data_string);
		$order_id = $s[0];
		$refund_amt = $s[1];
		$note = $s[2];
		$aor_adapter = new AdmOrderReversalAdapter($m);
		if ($aor_adapter->checkForAlreadyInProcessRefundAndInitiateIfNot($order_id,$refund_amt,$note))  {
			myerror_log("ERROR! staged partial refund appears to have been manually refunded already");
			MailIt::sendErrorEmail("ERROR! staged partial refund appears to have been manually refunded already", "order_id: $order_id");
			return true;
		}
		$order_controller = new OrderController($mt, $u, $r);
		$order_resource = SplickitController::getResourceFromId($order_id, 'Order');
		$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
		if ($balance_change_row = $balance_change_adapter->getRecord(array("order_id"=>$order_id,"process"=>"CCpayment"))) {
			if ($cc_functions = CreditCardFunctions::creditCardFunctionsFactory($balance_change_row['cc_processor'])) {
				$return_fields = $cc_functions->creditVoidTransaction($balance_change_row, $refund_amt, $merchant_resource);
			} else {
				myerror_log("Error! There is a data problem and we cannot find the correct payment processor to refund with: ".$balance_change_row['cc_processor']);
				MailIt::sendErrorEmail("ERROR! Staged partial refund failure", "Error! There is a data problem for order_id: $order_id, and we cannot find the correct payment processor to refund with: ".$balance_change_row['cc_processor']);
				return false;
			}			
		} else {
			myerror_log("Error! There is a data problem and we cannot find the balance change row for order_id: $order_id");
			MailIt::sendErrorEmail("ERROR! Staged partial refund failure", "Error! There is a data problem and we cannot find the balance change row for order_id: $order_id");
			return false;			
		}
		if ($return_fields['response_code'] == 100) {
			$bc_r = BalanceChangeAdapter::staticAddRow($order_resource->user_id, $balance_before, $refund_amt, $balance_after, 'CCrefund', $cc_processor, $order_resource->order_id, $return_fields['transactionid'], 'Issuing a credit card REFUND from the API: Delayed Partial Order Refund. '.$note);
			$return_fields['balance_change_id'] = $bc_r->id;
			
			$aor_r = $aor_adapter->completeRefund($order_resource->order_id, $refund_amt, 'G', "Issuing a credit card refund from the API: Delayed Partial Order Refund. $note", $invoice);
			$return_fields['order_reversal_id'] = $aor_r->id;
			return true;
		} else {
			$body = "We had a delayed partial refund fail on the next day.  order_id: $order_id.";
			MailIt::sendErrorEmailSupport("MANUAL INTERVENTION REQUIRED! Double fail on partial refund next day!", $body);
			return false;	
		}		
	}

	function resetAdmReversalTableAndCreateRefundReturn($order_id,$status,$message)
	{
		$this->resetAdmReversalTable($order_id);
		return $this->createRefundReturn($status,$message);
	}

	function resetAdmReversalTable($order_id)
	{
		$aor_adapter = new AdmOrderReversalAdapter($m);
		$sql = "DELETE FROM adm_order_reversal WHERE order_id = $order_id";
		$aor_adapter->_query($sql);
	}
	
	function createRefundReturn($status,$message)
	{
		$data['message'] = $message;
		$data['result'] = $status;
		return $data;	
	}

	static function retroBillOrdersFromADAMSscrewup()
	{
		$sql = "select * from Orders where user_id > 19999 AND date(order_dt_tm) = date(now()) and status = 'E' and skin_id != 72 and order_id < 2617592 and order_id > 2614655 and cash = 'N' and payment_file IS NULL order by order_id asc";
		$return_values = OrderController::retroBillOrdersBySQLQuery($sql);
		logData($return_values, "RetroBilling");
		$body = print_r($return_values,true);
		MailIt::sendErrorEmail("RetroBillingResult", $body);
		return true;
	}

	static function retroBillOrdersBySQLQuery($sql) 
	{
    	$order_adapter = new OrderAdapter($mimetypes);
    	$options[TONIC_FIND_BY_SQL] = $sql;
    	if ($order_resources = Resource::findAll($order_adapter, $url, $options))
    	{
    		foreach ($order_resources as $order_resource) 
    		{
    			if (OrderController::retroBillOrderByOrderResource($order_resource)) {
    				$successes++;
    			} else {
    				$failed_order_ids[] = $order_resource->order_id;
    				$failures++;
    			}   
    			$total++; 			
    		}
    	} else {
    		myerror_log("NO ORDERS FOUND!");
    	}
    	$return_values['total_orders_run'] = $total;
		$return_values['successes'] = $successes;
		$return_values['failures'] = $failures;
		$return_values['failed_order_ids'] = $failed_order_ids;
		return $return_values;
	}

	static function retroBillOrderByOrderResource($order_resource) 
	{
    	$order_id = $order_resource->order_id;
    	$user_id = $order_resource->user_id;
    	$merchant_id = $order_resource->merchant_id;
    	$amount_to_run_card_for = $order_resource->grand_total;
    	myerror_log("about to run credit card for re-biling order_id: $order_id");
    	$merchant_adapter = new MerchantAdapter($mimetypes);
    	if ($merchant = $merchant_adapter->getRecord(array("merchant_id"=>$merchant_id)))
    	{
    		//$complete_order = CompleteOrder::staticGetCompleteOrder($order_id, $mimetypes);
    		$user_adapter = new UserAdapter($mimetypes);	
    		if ($billing_user_resource = $user_adapter->getExactResourceFromData(array("user_id"=>$user_id)))
    		{
    			if (substr($billing_user_resource->flags,1,2) == 'C2') {
    				$cc_functions = new CreditCardFunctions();
					$return_fields = $cc_functions->cardProcessor($amount_to_run_card_for, $billing_user_resource, $order_id, $merchant, $card_data);
					if ($return_fields['response_code'] == 100) {
						myerror_log("REBILLING SUCCESS about to update balance change records for order_id: $order_id");
						$balance_change_adapter = new BalanceChangeAdapter($mimetypes);
						$balance_change_adapter->addOrderRow($user_id, 0.00, -$amount_to_run_card_for, -$amount_to_run_card_for, $order_id, "re-billing");
						if ($bc_resource = $balance_change_adapter->addCCRow($user_id, -$amount_to_run_card_for, $amount_to_run_card_for, 0.00, $return_fields['processor_used'], $order_id, $return_fields['transactionid'], 'authcode='.$return_fields['authcode'])) {
							;//myerror_log("success re-billing the user for order_id: $order_id");
						} else {
							myerror_log("REBILLING soft ERROR entering CC row in balance change table for order_id: $order_id");
						}
						$order_resource->payment_file = "re-billed";
						if ($order_resource->save()) {
							;//myerror_log("REBILLING sucesss updating the order resouce payment_file");
						} else {
							myerror_log("REBILLING soft ERROR updating the order record payment file field for order_id: $order_id");
						}
						return true;
					} else {
						myerror_log("REBILLING ERROR Failure billing user. skipping order_id: $order_id");
					}
    			} else {
    				myerror_log("REBILLING ERROR User does not have a CC saved. skipping order_id: $order_id");
    			}
    		} else {
    			myerror_log("REBILLING ERROR Could not get user resource for order_id.  skipping order_id: $order_id");
    		}
    	} else {
    		myerror_log("REBILLING ERROR couldn't get merchant object for rebilling skipping order_id: $order_id");
    	}
		return false;
	}

}