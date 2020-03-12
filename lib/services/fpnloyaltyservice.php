<?php
class FpnLoyaltyService
{
	var $url;
	var $raw_response;
	
	function __construct() {
	  $this->url = getProperty('fpn_loyalty_url'); 
	}
	
	function send($body)
	{
		myerror_logging(3,"FPN loyalty input: ".$body);
		$loyalty_return = FpnLoyaltyCurl::curlIt($this->url, $body);
		/*	$loyalty_return ='<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><AddPointsResponse xmlns="http://tempuri.org/"><AddPointsResult>{"xml":{"Reward":"REWARD:0.00","status":"success","Approved":"29916540","Clerk":"27","Check":"9780653","PointsAdded":"10.50","TotalPoints":["369.50","369.50"],"TotalSaved":"0.00","TotalVisits":"37","GiftCardBalance":"0.00","RewardCashBalance":"0.00","CustomReceiptMessages":"Register your Rewards Number and receive\na R5.00 instant reward\n"}}</AddPointsResult></AddPointsResponse></soap:Body></soap:Envelope>'; */
		if ($raw_return = $loyalty_return['raw_result']) {
			return $this->processGenericRawReturn($raw_return);
		}
			    
		myerror_log("ERROR! processing fpn loyalty message: ".$loyalty_return['error']);
	    $this->raw_response = $loyalty_return['error'];
	    return false;		
	}
	
	function processGenericRawReturn($raw_return)
	{
		if (substr_count($raw_return, 'AddPoints')) {
			return $this->processRawReturnForAddPoints($raw_return);
		} else if (substr_count($raw_return, 'BalanceInquiry')) {
			return $this->processRawReturnForBalanceInquiry($raw_return);
		} else {
			return $this->processRawReturnForVoidTransaction($raw_return);
		}
	}
	
	function processRawReturnForAddPoints($raw_return)
	{
		return $this->processRawReturn($raw_return, 'AddPointsResult');
	}
	
	function processRawReturnForBalanceInquiry($raw_return)
	{
		return $this->processRawReturn($raw_return, 'BalanceInquiryResult');
	}
	
	function processRawReturnForVoidTransaction($raw_return)
	{
		return true;
	}
	
	function processRawReturn($raw_return,$tag_name)
	{
		$xml = simplexml_load_string($raw_return);
		$xml->registerXPathNamespace('resp', 'http://tempuri.org/');
		$new_simple_xml_elements = $xml->xpath('//resp:'.$tag_name);
	    $json_payload = $this->getPayload($new_simple_xml_elements,$tag_name);

	    return $this->determineSuccessFromJsonPayload($json_payload);
	}
	
	function getPayload($new_simple_xml_elements,$tag_name)
	{
	    $string = $new_simple_xml_elements[0]->asXML();
	    $string = str_ireplace("<$tag_name>", "", $string);
	    $string = str_ireplace("</$tag_name>", "", $string);
	    $json_payload = trim($string);
		$this->raw_response = $json_payload;
	    myerror_log("the payload: ".$json_payload,3);
	    return $json_payload;
	}
	
	function determineSuccessFromJsonPayload($json_payload)
	{
	    $array = json_decode($json_payload,true);
	    $response_array = $array['xml'];
	    if ($response_array['status'] == 'success')
	    	return true;
	    return false;		
	}
	
	/**
	 * @desc takes order info and stages the FPN loyalty message in the Merchant Message History
	 * @param array $new_order
	 * @return int the map id of the newly created message
	 */
	function createLoyaltyMessageFromOrder($new_order)
	{
		$merchant_fpn_map = new MerchantFPNMapAdapter($mimetypes);
		$merchant_id = isProd() ? $new_order['merchant_id'] : 999;
		if ($mfm_record = $merchant_fpn_map->getRecord(array("merchant_id" => $merchant_id))) {
			if ($merchant_rock_comm_id = $mfm_record['merchant_rock_comm_id']) {
				$new_order['merchant_rock_comm_id'] = $merchant_rock_comm_id;
				$body = $this->createLoyaltySoapXmlDocument($new_order);
				return $this->stageMessage($new_order,$body);
			}
		}
		MailIt::sendErrorEmailSupport("ERROR with FPN LOYALTY", "Merchant_id: $merchant_id, does not have a merchant_rock_comm_id entry in the the Merchant_FPN table");
	}
	
	/**
	 * @desc takes the array and creates the Soap Xml document  array must have ('order_amt','loyalty_number','merchant_rock_comm_id','order_id'). returns the Soap xml document as a string
	 * @param $fpn_loyalty_data
	 * @return string
	 */
	function createLoyaltySoapXmlDocument($new_order)
	{
		$fpn_loyalty_data['order_amt'] = $new_order['order_amt'];
		$fpn_loyalty_data['loyalty_number'] = $new_order['brand_loyalty_number'];
		$fpn_loyalty_data['merchant_rock_comm_id'] = $new_order['merchant_rock_comm_id'];
		$fpn_loyalty_data['order_id'] = $new_order['order_id'];
		$fpn_loyalty_resource = Resource::dummyFactory($fpn_loyalty_data);
		$fpn_loyalty_resource->_representation = '/payment_templates/fpn/loyalty.xml';
		$body = getResourceBody($fpn_loyalty_resource);
		return $body;
	}

	function createBalanceInquiryXMLDocument($data)
	{
		$fpn_loyalty_data['loyalty_number'] = $data['brand_loyalty_number'];
		$fpn_loyalty_data['merchant_rock_comm_id'] = $data['merchant_rock_comm_id'];
		$fpn_loyalty_data['order_id'] = $data['order_id'];
		$fpn_loyalty_resource = Resource::dummyFactory($fpn_loyalty_data);
		$fpn_loyalty_resource->_representation = '/payment_templates/fpn/balance_inquiry.xml';
		$body = getResourceBody($fpn_loyalty_resource);
		$body = cleanUpXML($body);
		return $body;
	}
	
	function createVoidXMLDocument($data)
	{
		$fpn_loyalty_data['loyalty_number'] = $data['brand_loyalty_number'];
		$fpn_loyalty_data['merchant_rock_comm_id'] = $data['merchant_rock_comm_id'];
		$fpn_loyalty_data['order_id'] = $data['order_id'];
		$fpn_loyalty_data['authorization_number'] = $data['approved_code'];
		$fpn_loyalty_resource = Resource::dummyFactory($fpn_loyalty_data);
		$fpn_loyalty_resource->_representation = '/payment_templates/fpn/void_transaction.xml';
		$body = getResourceBody($fpn_loyalty_resource);
		$body = cleanUpXML($body);
		return $body;
		
	}
	
	function stageMessage($new_order,$body)
	{
		$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
		$send_ts = $new_order['pickup_dt_tm'];
		$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);
		// adding address is redundant since the service already knows it.  allows overriding i guess.
		if ($map_id = $mmh_adapter->createMessage($new_order['merchant_id'], $new_order['order_id'], 'C', $this->url,$send_ts, 'I', "service=FpnLoyalty", $body))
			return $map_id;
		else
			MailIt::sendErrorEmail("Messaging Error", "Unable to create Loyalty Curl Message in place order controller");
		return false;
	}	
}
?>