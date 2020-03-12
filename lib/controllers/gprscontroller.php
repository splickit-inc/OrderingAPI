<?php

class GprsController extends MessageController
{
	protected $representation = '/order_templates/gprs/execute_order_gprs2.txt';
	protected $no_order_representation = '/order_templates/gprs/no_order_gprs.txt';
	protected $format = 'G';
	
	protected $format_array = array ();
	
	protected $format_name = 'gprs';
	protected $second_part = false;
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
		
	function GprsController($mt,$u,&$r,$l = 0)
	{
		parent::MessageController($mt,$u,$r,$l);
		if (function_exists('getallheaders'))
			$heads = getallheaders();
		if ($range = $heads['RANGE'])
			; // all is good
		else
		{
			myerror_logging(3,"no RANGE header sent, re-setting to default of bytes=0-1023");
			$range = 'bytes=0-1023';
		}
		$r = explode('=', $range);
		$range_value = $r[1];
		myerror_logging(3,"the range value from the printer is: ".$range_value);
		$rs = explode('-',$range_value);
		$this->request_lower_range_value = $rs[0];
		$this->request_upper_range_value = $rs[1];
		
		if ($this->log_level > 3)
		{
			myerror_log("*********GPRS HEADERS*********");
			foreach ($heads as $name => $value) 
	    		myerror_log("$name: $value");
			myerror_log("******************************");
		}
		
	}
		
	function markMessageDelivered($message_resource = null,$locked = 'S')
	{
		myerror_log("starting the GPRS mark message delivered code");
		if ($message_resource == null)
			$message_resource =& $this->message_resource;
		
		if 	(substr_count($this->request->url,'/gprs/f2.txt') > 0)
		{
			$message_resource->message_text = $this->complete_body;
			$message_resource->viewed = 'N';
			myerror_log("we have an f2.txt so we need to test to see if we mark delivered or not");
		}
		else
		{
			myerror_log("in gprs code about to call parent mark message delivered because we have an old firmware printer");
			return parent::markMessageDelivered($message_resource,$locked);
		}
			
		$body_length = intval($this->body_length);
		$upper_range_value = intval($this->request_upper_range_value);
		
		myerror_log("body length is: ".$body_length);
		myerror_log("upper range value is: ".$upper_range_value);
		
		if (($body_length - 1) <= $upper_range_value )
		{
			myerror_log("about to call the parent mark message delivered");
			return parent::markMessageDelivered($message_resource,$locked);
		} else {
			myerror_log("resetting locked to P");
		}
		
		$message_resource->locked = 'P';
		$message_resource->modified = time();
		if ($message_resource->save())
			return true;
		else
		{
			myerror_log("ERROR trying to mark message as NOT delivered in gprs controller for f2.txt printer call: ".$message_resource->getAdapterError());
			myerror_log("error code is: ".mysqli_errno());
			throw new Exception("ERROR!  message could not be set back to unsent with P as locked: ".$message_resource->getAdapterError(),20);
		}	
	}
	
	/**
	 * 
	 * @desc will find the GPRS message associated with this order_id and merchant_id.  Only works on > version 7
	 * @param unknown_type $order_id
	 * @param unknown_type $merchant_id
	 */
	private function getGPRSMessageForThisOrderAndMerchantID($order_id,$merchant_id)
	{
		$options[TONIC_FIND_BY_METADATA]['order_id'] = $order_id;
		$options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
		$options[TONIC_FIND_BY_METADATA]['message_format'] = array("LIKE"=>"G%");
		return Resource::find($this->adapter,NULL,$options); 
	}

	public function callBack($id)
	{
		if ($merchant_id = $this->getMerchantIdFromNumeric($id))
		{	
			$order_id = $this->request->data['o'];
			if ($order_id < 1000)
			{
				myerror_log("we have an order id that doesnt conform. so just pass through.  order_id: ".$order_id);
				return true;
			}
			if ($order_message_resource = $this->getGPRSMessageForThisOrderAndMerchantID($order_id, $merchant_id)) {
				$viewed_result = MerchantMessageHistoryAdapter::markMessageResourceAsViewed($order_message_resource);
				$this->testForDelayedMessageWaitingForCallBackFinishAndScheduleTextIfNeeded($merchant_id,$order_message_resource->message_delivery_addr);
				return $viewed_result;
			}
		} else {
			return false;
		}
	}
	
	/**
	 * 
	 * @desc finds a GPRS message resource that is past due for this merchant. returns null on no messages
	 * @param $merchant_id
	 * @return Resource
	 */
	function getGPRSMessageThatIsReadyToBeSentForThisMerchant($merchant_id)
	{
		$options[TONIC_FIND_BY_METADATA]['sent_dt_tm'] = '0000-00-00 00:00:00';
		$now_string = date('Y-m-d H:i:s');
		$options[TONIC_FIND_BY_METADATA]['next_message_dt_tm'] = array('<'=>$now_string);
		$options[TONIC_FIND_BY_METADATA]['message_format'] = array("LIKE"=>"G%");
		$options[TONIC_FIND_BY_METADATA]['locked'] = 'P';
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
		$options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
		return Resource::find($this->adapter,'',$options);
	}
	function isThereAGPRSMessageReadyForThisMerchant($merchant_id)
	{
		if ($message_resource = $this->getGPRSMessageThatIsReadyToBeSentForThisMerchant($merchant_id)) {
			return true;
		}
		return false;
	}
	
	function getSMSNumberForMerchant($merchant_id)
	{
        return MerchantMessageMapAdapter::getSMSNumberForMerchant($merchant_id);
	}

	function testForDelayedMessageWaitingForCallBackFinishAndScheduleTextIfNeeded($merchant_id,$sms_no)
	{
		if ($gprs_message_resource = $this->getGPRSMessageThatIsReadyToBeSentForThisMerchant($merchant_id))
		{
	        // we have a pending gprs message so send a text
	        // had to add +5 since printer takes 5 seconds to be ready to recieve text after making callback request
	        if ($mapped_sms_no = $this->getSMSNumberForMerchant($merchant_id)) {
	        	$next_message_time = time()+5;
	        	$sms_resource = $this->createTEXTMessageFirmware8($merchant_id, $mapped_sms_no, null, $next_message_time);
	        }
		}		
	}
	
	private function getMerchantIdFromNumeric($merchant_id)
	{
		//error_reporting(E_ALL);
		if ($merchant_resource = MerchantAdapter::getMerchantFromIdOrNumericId($merchant_id))
			return $merchant_resource->merchant_id;
		return false;
	}
	
	public function pullNextMessageResourceByMerchant($merchant_id)
	{
		if ($merchant_resource = MerchantAdapter::getMerchantFromIdOrNumericId($merchant_id))
		{
			$merchant_id = $merchant_resource->merchant_id;
		} else {
			myerror_log("SERIOUS ERROR IN GPRS CONTROLLER!  no matching merchant for submitted id: ".$merchant_id);
			recordError('no matching id in GPRS controller', 'no matching id: '.$merchant_id);
			return false;
		}
		
		// used during printer swapping
		$production_operation_firmware8 = false;
				
		// check if we're on the swap table
		if (substr_count($this->request->url,'/f2.txt') > 0 ) 
		{
			$printer_swap_adapter = new MerchantPrinterSwapMapAdapter($this->mimetypes);
			if ($this->swap_record_resource = Resource::find($printer_swap_adapter,''.$merchant_id))
			{
			    if ($this->swap_record_resource->new_sms_no == 'ip printer') {
			        // ok we have a firmware 8.0 or greater printer that is being swapped out for an epson so we need to break out of here
                    myerror_log("current gprs printer calling in while new epson is live on swap table. break from swap code");
                } else {
                    if ($this->swap_record_resource->live == 'N')
                    {
                        $this->bypass_update = true;
                        $this->preswap = true;
                    } else {
                        myerror_log("******** STARTING SWAP CODE ******");
                        /*  START THE SWAP PROCEDURE.

                            1.  remove ping from merchant message map
                            2.  change Text address to value in the swap table
                            3.  	- set text delay to 0;
                                    - set text info to firmware=8.0
                            4.  add a '2' to the message format of the like 'G%' message
                            5.  set 'G' delay to 0 also
                                    - set 'G' info to firmware=8.0
                            6.  remove the record from the swap table
                            7.  create a test order
                            8.  turn merchant to active if they are innactive
                            8.  return the bio so the merchant knows they've got the correct printer.
                        */

                        // update the existing T,P,and G message.
                        $message_data['merchant_id'] = $merchant_id;
                        $m_options[TONIC_FIND_BY_METADATA] = $message_data;
                        $merchant_message_map_adapter = new MerchantMessageMapAdapter($this->mimetypes);
                        $messages = Resource::findAll($merchant_message_map_adapter,null,$m_options);
                        foreach ($messages as $message_resource)
                        {
                            $message_format = $message_resource->message_format;
                            $message_format_root = substr($message_format, 0,1);
                            if ($message_format == 'T')
                            {
                                myerror_log("about to swap out the sms number.  old number: ".$message_resource->delivery_addr."    new number: ".$this->swap_record_resource->new_sms_no);
                                $message_resource->delivery_addr = $this->swap_record_resource->new_sms_no;
                                $message_resource->message_text = "***";
                            }
                            else if ($message_format_root == 'G')
                            {
                                $message_format = str_replace('2', '', $message_format);
                                if ($message_format == 'G')
                                    $message_resource->message_format = 'GUA';
                                else if ($message_format == 'GE')
                                    $message_resource->message_format = 'GUE';
                                else if ($message_format == 'GM')
                                    $message_resource->message_format = 'GUW';
                                $message_resource->message_text = "nullit";
                            }
                            else if ($message_format == 'P')
                            {
                                $ping_id = $message_resource->map_id;
                                $message_resource->logical_delete = 'Y';

                            } else {
                                continue;
                            }

                            $message_resource->delay = 0;
                            $message_resource->info = 'firmware=8.0';
                            $message_resource->save();

                        }

                        //remove message from swap table
                        $printer_swap_adapter->delete(''.$merchant_id);

                        // remove ping from map table
                        if ($ping_id)
                            $merchant_message_map_adapter->delete(''.$ping_id);

                        if ($merchant_resource->active == 'N' || $merchant_resource->ordering_on == 'N')
                        {
                            $merchant_resource->active = 'Y';
                            $merchant_resource->ordering_on = 'Y';
                            $merchant_resource->save();
                            MailIt::sendErrorEmailSupport("MERCHANT SET TO ACTIVE FROM SWAP TABLE", 'merchant_id: '.$merchant_id);
                        }

                        //now create the test order
                        if (getBrandIdFromCurrentContext() == null) {
                            // this is so the stored procedure doesnt't blow up
                            setSkinFromBrandId($merchant_resource->brand_id);
                        }
                        $place_order_controller = new PlaceOrderController($this->mimetypes, $this->user, $this->request);
                        $order_resource = $place_order_controller->placeSimpleTestOrder($merchant_id);


                        // now create the returned message
                        $bio_message_resource = $this->createFirmware8BioMessage($merchant_id);

                        // for unit testing add this flag
                        $bio_message_resource->set('test_order_id',$order_resource->order_id);

                        return $bio_message_resource;
                    }
                }
			} else {
				// we have a supposedly fully live firmware 8 printer calling so we'll need to make sure the message_format is of '2'
				$production_operation_firmware8 = true;
			}
		}
		
		if (! $this->bypass_update)
		{
			$gpcih_adapter = New GprsPrinterCallInHistoryAdapter(getM());
			myerror_logging(3,"about to create gprs printer call in history record");
			$gpcih_adapter->createRecord($merchant_id);
			if (getProperty("gprs_tunnel_merchant_shutdown") == 'true' && substr_count($this->request->url,'/gprs/f.txt') > 0 )
				MailIt::sendErrorEmail("GPRS TUNNEL SHUT DOWN SET TO TRUE BUT PRINTERS CALLING IN", "gprs_tunnel_merchant_shutdown=true in properties file but this just called in: ".$this->request->url.".   It appears the tunnel is back up, please adjust value in properites file");
		}

		if ($message_resource = parent::pullNextMessageResourceByMerchant($merchant_id))
		{
			// used during message formatProcessor to know what header and footer to use on the GPRS message
			$this->gprs_order_id = $message_resource->order_id;

			if ($this->preswap)
			{
				if ($message_resource->order_id > 5000000)
				{
					// probably the test message that we built so lets make sure to set the message to locked of Y
					$message_resource->locked = 'Y';
					$message_resource->save();
				}
			} 
			
 			$resource = $this->prepMessageForSending($message_resource);
            if ($resource->static == 'true')
			{
				myerror_logging(3, "we have a static message");
				$this->body_length = strlen($resource->message_text);
				return $resource;
			}

			// CHECK FOR NEW FIRMWARE and use new code
			if (substr_count($this->request->url,'/gprs/f2.txt') > 0 )
			{
				$this->body_length = strlen($resource->message_text);
				$this->complete_body = $resource->message_text;
				myerror_log("complete body: ".$resource->message_text);
				// now cancel any activation message that are associated with this order
				if (! $this->preswap)
					$this->cancelActivationMessages($message_resource->order_id);
				return $resource;
			} 

			// OLD FIRMWARE PRINTER........ aka: REALLY UGLY CODE (please ignore anything below this line, i was young, i needed th money)
			
			$complete_body = $resource->message_text;
			$this->complete_body = $complete_body;			
			$this->body_length = strlen($complete_body);

			$request_string = $this->request->url;
			$body_length = strlen($complete_body);
			$trimed_body = $complete_body;
			
			myerror_log("body length in gprscontroller->getNextMessageByMerchant: ".$body_length);
			myerror_log("in gprs controller the url string is: ".$request_string);
			myerror_log("about to check the length of the returned stuff");
		
			//  OK! BODY LENGTH HAS TO ADD 45 CHARACTERS OF HEADER AND FOOTER..  MAX IS 810 NOW it seems
			$max_chunk_length = 764;
			
			if ($body_length > $max_chunk_length)
			{	
				$trimed_body = substr($trimed_body, 0,-1);
				myerror_log("large gprs message. will need to break it up.");
				if (isProd() && $body_length > 3000 && $this->full_order['user_id'] > 1000)
				{
					MailIt::sendErrorEmailSupport("PLEASE BE AWARE! REALLY LARGE GPRS MESSAGE to old Firmware", "PLEASE BE AWARE OF LARGE ORDER TO OLD FIRMWARE PRINTER. order_id: ".$this->full_order['order_id']);
					SmsSender2::sendAlertListSMS(" REALLY LARGE GRPS MESSAGE $body_length.  order_id: ".$this->full_order['order_id']);
				}
				
				//$messages = $this->createBrokenUpMessageForOldFirmware($trimmed_body);
				
				$segments = explode('    ------', $trimed_body);

				$index = strpos($trimed_body, "::");
				$prepend = substr($trimed_body, 0,$index+2);
				
				$chunk_piece = '';
				$chunks = array();
				//myerror_log("--------------------");
				$segment_index = 0;
				foreach ($segments as $segment)
				{
					$segment_index++;
					myerror_log("here is this segment: ".$segment);
					myerror_log("length of this segment is: ".strlen($segment));
					if ($segment_index == 1)
					{
						myerror_log("this is the first segment so just go onto the next.");
						$chunk_piece .= $segment.'    ------';
						continue;
					}	
					
					myerror_log("length of chunk_piece at this point is: ".strlen($chunk_piece));
					myerror_log("length of chunk_piece + this segment is: ".strlen($chunk_piece.$segment));
					if (strlen($chunk_piece.$segment) < $max_chunk_length)
					{
						myerror_log("adding them together because they are less than ".$max_chunk_length);
						$chunk_piece .= $segment.'    ------';
					}
					else		
					{
						myerror_log("over the limit, so save the chunk and start a new one with this segment");
						$chunks[] = $chunk_piece;
						$chunk_piece = $segment.'    ------';
					}
					//myerror_log("--------------------");
				}
				$chunks[] = substr($chunk_piece,0,-9);

				//$chunks = explode("||||",wordwrap($trimed_body,823,"||||",false));
				$total = count($chunks);
				
				foreach($chunks as $page_index => $chunk)
				{
					$page = $page_index+1;
					if ($page == 1)
					{
						$message = sprintf("%s::::(page %d of %d)::xx#",$chunk,$page,$total);

					}
				    else if ($page == $total)
				    {
				    	//$chunk = substr($chunk,0,-1);
				    	
				    	$message = sprintf("$prepend%s::::(page %d of %d)::xx#",$chunk,$page,$total);
				    }
				    else
				    	$message = sprintf("$prepend%s::::(page %d of %d)::xx#",$chunk,$page,$total);
				    	
				    $messages[$page] = $message;
				}
				
				// message is now broken up so save the additional parts and just send the first part
				$timestamp_of_original_send_time = $message_resource->next_message_dt_tm;
				
				for ($i=2;$i<$total+1;$i++)
				{
					$new_resource = clone $message_resource;
					unset($new_resource->map_id);
					$new_resource->_exists = false;
					$new_resource->message_type = 'X2';
					$new_resource->next_message_dt_tm = $timestamp_of_original_send_time+$i;
					$new_resource->locked = 'P';
					$new_resource->tries = '0';
					$new_resource->message_text = $messages[$i];
					$new_resource->save();
				}
				// now set this message to a static representation
				$message_resource->message_text = $messages[1];
				
				// now set it on the message resource object so it saves correctly in the db.
				$this->message_resource->message_text = $messages[1];
				$this->complete_body = $messages[1];			
				$this->body_length = strlen($messages[1]);
				
				$message_resource->_representation = $this->static_message_template;
				return $message_resource;

			} else if ($this->full_order['order_id'] > 1000) {

				// not a multipart order message
				
				$today = date('Y-m-d');
				$sql = "UPDATE Merchant_Message_History SET sent_dt_tm = '".$today."', locked = 'C' WHERE sent_dt_tm = '0000-00-00 00:00:00' AND order_id = ".$this->full_order['order_id']." AND merchant_id = ".$this->full_order['merchant_id']." AND locked = 'N' AND message_type = 'A'";			
				myerror_log("the sql to update the gprs activation messages is: ".$sql);
				if ($this->adapter->_query($sql))
					; // all is good
				else
					myerror_log("ERROR Updating activation messages: ".$this->adapter->getLastErrorText());
			}					
			return $resource;	
		} else if (false && $merchant_id == 10) {
			$resource = Resource::dummyFactory(array());
			$resource->_representation = $this->no_order_representation;
			$resource->set('loaded','true');
			$resource->set('order_id','101');
			$resource->set('user_id','101');
			return $resource;
		} else if (substr_count($this->request->url,'/f2.txt') > 0 ) {
			// no messages ready for pickup
			
			if ($this->preswap)
		 	{
				// create the bio message
				
				$gprs_bio_message_resource = $this->createFirmware8BioMessage($merchant_id);
				$this->body_length = strlen($gprs_bio_message_resource->message_text);

				// now create a text and test gprs message
				
				// +30 so the bio has time to print
				$next_message_time = time()+20;  
				
				// during testing on local, nothing is being printed so had to do the minus 5 becuase it was too fast and looked like there was no message.
				if (isLaptop())
					$next_message_time = time()-5; //  
				
				//create a fake order id
				//$test_order_id = 'TEST-'.generateCode(5);
				$test_order_id = time();
					
				// now create the new text and the test GPRS message
				$new_sms_number = $this->swap_record_resource->new_sms_no;
				$new_text_message_resource = $this->createTEXTMessageFirmware8($merchant_id, $new_sms_number, $test_order_id, $next_message_time);
				
				$new_gprs_message_resource = $this->createTestGPRSMessageFirmware8($merchant_id, "This is a test message::to make sure your printer::is functioning correctly::Please ignore::sms_no=".$new_sms_number,$test_order_id,$next_message_time);
				
				//and now return the previously created bio message.
				return $gprs_bio_message_resource;
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
        myerror_logging(5,"we have the string to process in grps controller: $string");
        $string = preg_replace( "/\r|\n/", "", $string );
		$string=str_replace("&quot;",'', $string);
		$string=hard_wrap($string, 30, "::");
		if (isset($this->full_order['merchant_id'])) {
			$merchant_id = $this->full_order['merchant_id'];
		} else { 
			$merchant_id = $this->merchant['merchant_id'];
		}
		if (substr_count($this->request->url,'/f2.txt') > 0 )  {
			if ($this->message_resource->tries > 99) {
				$order_id = $this->message_resource->order_id;
				$alphabet_string = "abcdefghijklmnopqrstuvwxyz";
				$ind = rand (0,25);
				$letter = substr($alphabet_string, $ind,1);
				$ind = rand (0,25);
				$letter .= substr($alphabet_string, $ind,1);
				// we have a resend so modify order id
				$this->gprs_order_id = $order_id.'-'.$letter;
			}			
			$string = "#".$merchant_id."*1*".$this->gprs_order_id."**".$string.";*#";
		} else {
			$string = "#".$merchant_id."*".$this->gprs_order_id."*".$string."#";
		}
		
		return $string;
	}

	private function createFirmware8BioMessage($merchant_id)
	{
		$merchant_adapter = new MerchantAdapter($this->mimetypes);
		$merchant_resource = Resource::find($merchant_adapter,''.$merchant_id);
		$this->merchant = $merchant_resource->getDataFieldsReally();
		$merchant_resource->_representation = '/utility_templates/universal_bio.txt';

		// used as the order id in teh bio message
		$ts = time();
		$merchant_resource->modified = $ts;
		$this->gprs_order_id = 'bio'.$ts;

		// for backward compatability
		$merchant_resource->set('merchant',$merchant_resource->getDataFieldsReally());
		$representation_temp = $merchant_resource->loadRepresentation(new FileAdapter($this->mimetypes, 'resources'));
		$complete_body = $representation_temp->_getContent();
		$complete_body = $this->formatProcessor($complete_body);
		// now create bio message and set it to sent since we're sending it here.
		$message_data['next_message_dt_tm'] = time();
		$message_data['sent_dt_tm'] = date("Y-m-d h:i:s",time());
		$message_data['message_format'] = 'GUB';
		$message_data['merchant_id'] = $merchant_id;
		$message_data['locked'] = 'X'; // to indicate message that shouljd be bypassed by processing system because we are not live
		$message_data['info'] = 'firmware=8.0';
		$message_data['message_text'] = $complete_body;
		$message_data['created'] = time();
		$message_data['modified'] = time();
		$message_resource = Resource::factory($this->adapter,$message_data);
		$message_resource->save();
		$message_resource->_representation = $this->static_message_template;
		$message_resource->set('bio_message_order_id',$ts);
		return $message_resource;
	}

	/**
	 * 
	 * @desc will create a text message for GPRS activation. returns the created message as a resource
	 * @param $merchant_id
	 * @param $sms_no
	 * @param $test_order_id
	 * @param $next_message_time
	 * @return Resource 
	 */
	function createTEXTMessageFirmware8($merchant_id,$sms_no,$test_order_id,$next_message_time)
	{
				if ($next_message_time == null)
					$next_message_time = time();
				$sms_data['merchant_id'] = $merchant_id;
				//$sms_data['order_id'] = $test_order_id;
				$sms_data['message_format'] = 'T';
				$sms_data['message_type'] = 'A';
				$sms_data['message_delivery_addr'] = $sms_no;
				$sms_data['next_message_dt_tm'] = $next_message_time;
				$sms_data['locked'] = 'N';
				$sms_data['info'] = 'firmware=8.0';
				$sms_data['message_text'] = '***';
				$new_text_message_resource = Resource::factory($this->adapter,$sms_data);
				$new_text_message_resource->save();
				return $new_text_message_resource;
		
	}
	
	private function createTestGPRSMessageFirmware8($merchant_id,$message,$test_order_id,$next_message_time)
	{	
		$gprs_data['merchant_id'] = $merchant_id;
		$gprs_data['message_format'] = 'GUT';
		$gprs_data['locked'] = 'P';
		$gprs_data['viewed'] = 'N';
		$gprs_data['order_id'] = $test_order_id;
		$gprs_data['next_message_dt_tm'] = $next_message_time;
		$gprs_data['info'] = 'firmware=8.0';
		
		$gprs_data['message_text'] = "#".$merchant_id."*1*".$test_order_id."**::".$message.";*#";
		$new_gprs_message_resource = Resource::factory($this->adapter,$gprs_data);
		$new_gprs_message_resource->save();
		return $new_gprs_message_resource;
		
	}

	private function cancelActivationMessages($order_id)
	{
				$today = date('Y-m-d');
				$sql = "UPDATE Merchant_Message_History SET sent_dt_tm = '".$today."', locked = 'C' WHERE sent_dt_tm = '0000-00-00 00:00:00' AND merchant_id = ".$this->message_resource->merchant_id." AND order_id = $order_id AND locked = 'N' AND message_type = 'A'";			
				myerror_log("the sql to update the gprs activation messages is: ".$sql);
				if ($this->adapter->_query($sql))
					return true; // all is good
				else
					myerror_log("ERROR Updating activation messages: ".$this->adapter->getLastErrorText());
				return false;
	}
	
	function send($body = null) 
	{
		//throw new Exception("NO SEND METHOD FOR GPRS CONTROLLER.  MUST BE CALLED BY SERVICE");
		MailIt::sendErrorEmailSupport("ERROR! GPRS message being called with SEND method!", "message_id: ".$this->message_resource->message_id);
		return true;

/*		
		// this is now for sending a *** to a printer
		$returns = SmsSender2::send_sms($this->message_resource->message_delivery_addr, "***");
		if ($returns)
		{
			$this->message_resource->locked = 'P';
			$this->message_resource->save();
		}
		
		// need to return false here so that the message does not get marked as sent.
		return false;
*/
	}

	public function sendThisMessageX($message_resource)
	{
		// new gprs functionality  GPRS message with starting with a locked of 'N'.  need to send a text to the address and then set the locked to 'P'
		$sms_delivery_no = $message_resource->message_delivery_addr;
		$message = '***';
		try { 
			SmsSender2::send_sms($sms_delivery_no, $message);
			// do we record the sending of this text in the merchant message history for historical purposes?
			$mmh_adapter = new MerchantMessageHistoryAdapter(getM());
			$mmh_adapter->createMessage($message_resource->merchant_id, $message_resource->order_id, 'T', $sms_delivery_no, time(), 'A', $message_resource->info, $message,'S',time());
		} catch (Exception $e) {
			// ok we couldtn' send the text so NOW WHAT?
			MailIt::sendErrorEmailSupport("Couldn't Send text! possible serious outage", "So the text could not be sent, will set the GPRS message to 'P' so we can manually activate");
		}
		// so set GPRS message to locked now.
		$message_resource->locked = 'P';
		$message_resource->save();
		return true;
	}
}

?>