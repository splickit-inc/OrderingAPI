<?php 
Class EpsonPrinterController extends MessageController
{
	protected $format_name = 'epsonprinter';
	protected $format = 'S';
	protected $default_message_format = 'SUC';
	private $bio_order_id;
	private $test_messages_text = "This is a test message\nto make sure your printer\nis functioning correctly.\nIf you see this, all is 200 OK.";
    private $chars_to_strip = array(';', '*', '#', '@','&','<','>');


	function EpsonPrinterController($mt,$u,&$r,$l = 0)
	{
		parent::MessageController($mt, $u, $r,$l);
	}
	
	public function pullNextMessageResourceByMerchant($numeric_merchant_id)
	{
		$body = $this->request->body;
// WHAT A HACK!  there must be a better way here. printers suck :|		
		if ($body != null && substr_count($body, 'ResponseFile') > 0) {
		    try {
                myerror_log("We have a call back!");
                $body = removeCarriageReturnsTabsLineFeedsFromString($body);
                $start_index = strpos($body,"<PrintResponseInfo");
                if (!$start_index) {
                    // we need to decode the escape url
                    $body = urldecode($body);
                    $start_index = strpos($body,"<PrintResponseInfo");
                }
                $stop_index = strpos($body,"</PrintResponseInfo>") + 20;
                $length = $stop_index-$start_index;
                $xml = substr($body,$start_index,$length);
                $data_hash = parseXMLintoLowercaseHashmap($xml);
                if ($data_hash['eposprint']['printresponse']['response']['@attributes']['success'][0] == 'true') {
                    $order_id = $data_hash['eposprint']['parameter']['printjobid'];
                    $sql = "SELECT * FROM Merchant_Message_History WHERE order_id = $order_id AND message_format LIKE 'S%' AND locked = 'S'";
                    $options[TONIC_FIND_BY_SQL] = $sql;
                    if ($message_resource = Resource::find($this->adapter,null,$options)) {
                        $message_resource->viewed = 'V';
                        $message_resource->stamp = getStamp().';'.$message_resource->stamp;
                        $message_resource->save();
                    }
                }
            } catch (Exception $e) {
		        myerror_log("ERROR THROWN ON EPSON CALL BACK: ".$e->getMessage());
		        MailIt::sendErrorEmail("Error Thrown Parsing Callback from epson printer",$e->getMessage());
            }
            return false;
		}

		if ($merchant_resource = MerchantAdapter::getMerchantFromNumericId($numeric_merchant_id)) {
			$merchant_id = $merchant_resource->merchant_id;
            DeviceCallInHistoryAdapter::recordPullCallIn($merchant_id,$this->format);
			if ($message_resource = parent::pullNextMessageResourceByMerchant($merchant_id)) {
                $resource = $this->prepMessageForSending($message_resource);
				return $resource;
			} else if (false) {
                // create the bio message
                $bio_message_resource = $this->createBioMessage($merchant_id);
                $this->body_length = strlen($bio_message_resource->message_text);
                return $bio_message_resource;
            }
			myerror_logging(3, "There are no pulled messages ready for this merchant");
		} else {
			if ($numeric_merchant_id != '999999999999' && $numeric_merchant_id != '0') {
				myerror_log("SERIOUS ERROR IN EpsonPrinter CONTROLLER!  no matching merchant for submitted numeric id: ".$numeric_merchant_id);
			}
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
		$resource->mimetype = "application/xml";
		return $resource;
	}

    function loadMessageBody($resource,$message_resource)
    {
        // custom epson stuff
        foreach ($resource->order_details as &$order_detail) {
            $string_length = strlen($order_detail['item_total_w_mods']);
            $diff = $string_length-4;
            $epson_absolute_position = 450-($diff*12);
            $order_detail['epson_absolute_position'] = $epson_absolute_position;

            if (sizeof($order_detail['order_detail_hold_it_modifiers']) > 0) {
                $order_detail['show_holds'] = 'yes';
            } else {
                $order_detail['show_holds'] = 'no';
            }
        }
        foreach ($resource->receipt_items_for_merchant_printout as &$rifmp) {
            $string_length = strlen($rifmp['amount']);
            $diff = $string_length-5;
            $epson_absolute_position = 450-($diff*12);
            $rifmp['epson_absolute_position'] = $epson_absolute_position;
        }


        myerror_logging(3, "starting the new epson format logic with format of: ".$message_resource->message_format);
        $resource->_representation = '/order_templates/epson/epson_header.txt';
        $representation_header = $resource->loadRepresentation(new FileAdapter($this->mimetypes, 'resources'));
        $header = $representation_header->_getContent();

        $resource->_representation = '/order_templates/epson/epson_footer.txt';
        $representation_footer = $resource->loadRepresentation(new FileAdapter($this->mimetypes, 'resources'));
        $footer = $representation_footer->_getContent();

        if ($message_resource->message_format == 'SE') {
            $resource->_representation = '/order_templates/epson/epson_item_exceptions.txt';
        } else if ($message_resource->message_format == 'SW') {
            $resource->_representation = '/order_templates/epson/epson_item_withs.txt';
        } else if ($message_resource->message_format == 'SA') {
            $resource->_representation = '/order_templates/epson/epson_item_all.txt';
        }

        if ($representation_details = $resource->loadRepresentation(new FileAdapter($this->mimetypes, 'resources'))) {
            $details = $representation_details->_getContent();

            $complete_body = $header.$details.$footer;
            $resource->message_text = $complete_body;
            $resource->_representation = $this->static_message_template;
            return $resource;
        } else {
            $error_code = 11;
            if ($message_resource->message_type == 'X')
                $error_code = 10;
            $e = new Exception("no representation association in array for: ".$message_resource->message_format, $error_code);
            return $this->processError($e);
        }


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
            $order_item['item_name'] = str_replace($this->chars_to_strip, '', $order_item['item_name']);
            $order_item['item_print_name'] = str_replace($this->chars_to_strip, '', $order_item['item_print_name']);
            $order_item['size_print_name'] = str_replace($this->chars_to_strip, '', $order_item['size_print_name']);
            $order_item['note'] = preg_replace("/[^A-Za-z0-9 .\-:]/", '', $order_item['note']);
            $order_item['item_total_w_mods'] = toCurrency($order_item['item_total_w_mods']);
            $order_item['epson_item_string'] = '';
            if ($order_item['quantity'] > 1) {
                $order_item['epson_item_string'] = $order_item['quantity'].' ';
            }
            if (strtolower($order_item['size_print_name'] != 'one size') ) {
                $order_item['epson_item_string'] = $order_item['epson_item_string'].$order_item['size_print_name'].' ';
            }
            $order_item['epson_item_string'] = substr($order_item['epson_item_string'].$order_item['item_print_name'],0,26);
        }
        $message_resource->note = preg_replace("/[^A-Za-z0-9 .\-:]/", '', $message_resource->note);
        if (isset($message_resource->delivery_info)) {
            $message_resource->delivery_info->address1 = preg_replace("/[^A-Za-z0-9 .\-:]/", '', $message_resource->delivery_info->address1);
            $message_resource->delivery_info->address2 = preg_replace("/[^A-Za-z0-9 .\-:]/", '', $message_resource->delivery_info->address2);
            $message_resource->delivery_info->phone_no = preg_replace("/[^0-9]/", '', $message_resource->delivery_info->phone_no);
            $message_resource->delivery_info->instructions = preg_replace("/[^A-Za-z0-9 .\-:]/", '', $message_resource->delivery_info->instructions);
        }
        foreach($message_resource->order_summary['cart_items'] as &$cart_item) {
            $cart_item['item_name'] = str_replace($this->chars_to_strip, '', $cart_item['item_name']);
            $cart_item['item_note'] = str_replace($this->chars_to_strip, '', $cart_item['item_note']);
        }
        if (isset($message_resource->delivery_info)) {
            $message_resource->delivery_info->name = str_replace($this->chars_to_strip, 'and', $message_resource->delivery_info->name);
        }

        return $message_resource;
    }


    function markMessageDelivered($message_resource = null,$locked = 'S')
	{
		// if map_id != 0 we could do some code here to get the message and then update it..
		if ($message_resource == null) {
            $message_resource =& $this->message_resource;
        }
		myerror_log("in the mark message delivered we are aboiut to test the Body: ".$this->request->body."   for order_id: ".$this->message_resource->order_id);
		if ($this->request->body == null || trim($this->request->body) == '')
		{
			//reset the message. DO NOT MARK AS DELIVERED
			if ($message_resource) {
                $message_resource->locked = 'P';
                $message_resource->modified = time();
                $message_resource->save();
            } else {
                myerror_log("NO MESSAGE RESOURCE TO MARK AS DELIVERED");
                return false;
            }
			return true;
		} else {
		    $message_resource->viewed = 'N';
			return parent::markMessageDelivered($message_resource,$locked);
		}
	}
	
	function formatProcessor($string)
	{
		$string = str_replace("::",'', $string);
		$message_parts = explode("\n", $string);
        $full_message_body = '';
		foreach ($message_parts as $line) {
			$full_message_body = $full_message_body."<text>$line&#10;</text>\n";
		}
		
		$epson_header = '<?xml version="1.0" encoding="utf-8"?>
<PrintRequestInfo Version="2.00">
	<ePOSPrint>
		<Parameter>
		    <devid>local_printer</devid>
		    <timeout>10000</timeout>
		    <printjobid>'.$this->message_resource->order_id.'</printjobid>
		</Parameter>
		<PrintData>
			<epos-print xmlns="http://www.epson-pos.com/schemas/2011/03/epos-print">
				<text lang="en"/>
				<text smooth="true"/>
				<text align="left"/>
				<text font="font_c"/>
				<text width="1" height="1"/>
				<text reverse="false" ul="false" em="true" color="color_1"/>';
		
		$epson_footer = '<feed line="3"/>
				<cut type="feed"/>
				<sound pattern="pattern_a" repeat="3" />
			</epos-print>
		</PrintData>
	</ePOSPrint>
</PrintRequestInfo>';		
		
		$complete_message_body = $epson_header.$full_message_body.$epson_footer;
		$complete_message_body = str_replace("\t", '', $complete_message_body); // remove tabs
		$complete_message_body = str_replace("\n", '', $complete_message_body); // remove new lines
		$complete_message_body = str_replace("\r", '', $complete_message_body); // remove carriage returns	
		myerror_log("$complete_message_body",3);
		
		return $complete_message_body;
	}

	function getNoPulledMessageAvailableResponse()
	{
		$response = new Response(200);
		$response->headers['Content-Type'] = 'application/xml; charset=utf-8';
		$response->headers['Content-Length'] = '0';
		return $response;
	}


    private function createBioMessage($merchant_id)
    {
        $merchant_adapter = new MerchantAdapter($this->mimetypes);
        $merchant_resource = Resource::find($merchant_adapter,''.$merchant_id);
        $this->merchant = $merchant_resource->getDataFieldsReally();
        $merchant_resource->_representation = '/utility_templates/universal_bio.txt';

        // used as the order id in teh bio message
        $ts = time();
        $merchant_resource->modified = $ts;
        $this->bio_order_id = 'bio'.$ts;

        // for backward compatability
        $merchant_resource->set('merchant',$merchant_resource->getDataFieldsReally());
        $representation_temp = $merchant_resource->loadRepresentation(new FileAdapter($this->mimetypes, 'resources'));
        $complete_body = $representation_temp->_getContent();
        $complete_body = $this->formatProcessor($complete_body);
        // now create bio message and set it to sent since we're sending it here.
        $message_data['next_message_dt_tm'] = time();
        $message_data['order_id'] = $ts;
        $message_data['sent_dt_tm'] = date("Y-m-d h:i:s",time());
        $message_data['message_format'] = 'SUB';
        $message_data['merchant_id'] = $merchant_id;
        $message_data['locked'] = 'X'; // to indicate message that shouljd be bypassed by processing system because we are not live
        $message_data['message_text'] = $complete_body;
        $message_data['created'] = time();
        $message_data['modified'] = time();
        $message_resource = Resource::factory($this->adapter,$message_data);
        $message_resource->save();
        $message_resource->_representation = $this->static_message_template;
        $message_resource->set('bio_message_order_id',$ts);
        return $message_resource;
    }
}
?>