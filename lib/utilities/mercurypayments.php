<?php

class MercuryPayments 
{
	
	private $wsdl = "https://w1.mercurypay.com/ws/ws.asmx?WSDL";
    private $ns = "w1.mercurypay.com";
	//private $wsdl = "https://w1.mercurydev.net/ws/ws.asmx?WSDL";
	//private $ns = "w1.mercurydev.net";
	private $return_data = array();
	private $file_adapter;
	private $password = "xyz";
	public $server;
	public $lastfour;
	public $clean_card_no;
	public $text_response;

	function MercuryPayments()
	{
		if (isProd())
		{
			$this->server = 'prod';//all is good, we are in prod
		} else {
			$this->wsdl = "https://w1.mercurydev.net/ws/ws.asmx?WSDL";
			$this->ns = "w1.mercurydev.net";
			$this->password = "xyz";
			$this->server = 'test';
		}
		
		myerror_log("MERCURY SERVER: ".$this->server);
	}
	
	function setLastFour($num)
	{
		$this->lastfour = $num;
	}
	
	function setCleanCardNo($cc_no)
	{
		$this->clean_card_no = $cc_no;
	}
	
	function setFileAdapter($file_adapter)
	{
		$this->file_adapter = $file_adapter;
	}
	
	function getTextResponse()
	{
		return $this->textResponses;
	}
	
	function runcard($data)
	{
		
		// now get merchant mercury payment info
		$data['charge_amt'] = number_format($data['charge_amt'],2, '.', '');
		$merchant_id = $data['merchant_id'];
		if (!isProd()) {
			if ($data['cc_number'])
				$data['cc_number'] = '4003000123456781';
			if ($data['postal_code'])	
				$data['postal_code'] = '30329';
			//if ($data['cc_exp'])
				$data['cc_exp'] = '1215';
			//$data['terminal_id'] = '595901';
			$data['terminal_id'] = '003503902913105';
			$data['operator_id'] = 'Test';
			$data['web_services_password'] = 'xyz';
			if ($data['charge_amt'] > 10.59)
				$data['charge_amt'] = "10.50";
			$this->clean_card_no = '4003xxxxxxxx6781';
			$this->lastfour = '6781';	
		} else if ($merchant_mercury_data_resource = Resource::find(new MerchantMercuryMapAdapter($this->mimetypes),"$merchant_id")) {
			$data['terminal_id'] = $merchant_mercury_data_resource->TerminalID.'='.$merchant_mercury_data_resource->NickName;
			$data['web_services_password'] = $merchant_mercury_data_resource->WebServicePassword;
			$data['operator_id'] = 'splickit';
		} else {
			myerror_log("ERROR! Merchant Mercury Map NOT SET UP FOR THIS MERCHANT!  merchant_id: ".$data['merchant_id']);
			MailIt::sendErrorEMail("MERCURY PAY ERROR!", "Merchant Mercury Map NOT SET UP FOR THIS MERCHANT!  merchant_id: ".$data['merchant_id']);
			$response_array['response_code'] = 500;
			$response_array['responsetext'] = "Bad Mercury Setup";
			return $response_array;
		}
		
		$payment_resource = Resource::dummyfactory($data);
		$payment_resource->_representation = '/payment_templates/mercury/mercury_payment.xml';
		$mercury_payment_representation =& $payment_resource->loadRepresentation(getFileAdapter());
		$payload = $mercury_payment_representation->_getContent();
		
		$clean_payload = preg_replace('/(<AcctNo>.+?)+(<\/AcctNo>)/i', '<AcctNo>'.$this->clean_card_no.'</AcctNo>', $payload); 
		$clean_payload = preg_replace('/(<ExpDate>.+?)+(<\/ExpDate>)/i', '<ExpDate>xxxx</ExpDate>', $clean_payload); 
		myerror_log("Mercury Payments Payload: ".$clean_payload);
		$clean_payload = htmlspecialchars($clean_payload);	
		$full_clean_payload = "<CreditTransaction xmlns=\"http://www.mercurypay.com\"><tran>".$clean_payload."</tran><pw>".$data['web_services_password']."</pw></CreditTransaction>";
		myerror_log("******* mercury payments xml ********");
		myerror_log($full_clean_payload);
		myerror_log("*************************************");
		
		$payload = htmlspecialchars($payload);
		$full_payload = "<CreditTransaction xmlns=\"http://www.mercurypay.com\"><tran>".$payload."</tran><pw>".$data['web_services_password']."</pw></CreditTransaction>";
		$client = null;
		
		$headers[] = new SoapHeader($this->ns,'User-Agent','MPS Transact 1.2.0.4');
		$headers[] = new SoapHeader($this->ns,'Content-Type','text/xml; charset=utf-8');
		$headers[] = new SoapHeader($this->ns,'SOAPAction','http://'.$this->ns.'/CreditTransaction');
		
		try {		
				$client = new SoapClient($this->wsdl, array('trace' => true, 'exceptions'=>true));
				$client->__setSoapHeaders($headers);
				$soapvar = new SoapVar($full_payload, XSD_ANYXML);
				$result = $client->CreditTransaction($soapvar);
				myerror_log("******request********");
				$last = $client->__getLastRequest();
				// clean it
				$clean_last = preg_replace('/(&lt;AcctNo&gt;.+?)+(&lt;\/AcctNo&gt;)/i', '&lt;AcctNo&gt;xxxxxxxxxxxx'.$this->lastfour.'&lt;/AcctNo&gt;', $last);
				$clean_last = preg_replace('/(&lt;ExpDate&gt;.+?)+(&lt;\/ExpDate&gt;)/i', '&lt;ExpDate&gt;xxxx&lt;/ExpDate&gt;', $clean_last);

				myerror_log($clean_last);
				myerror_log("**************");
				$the_string = $result->CreditTransactionResult;
				$xml = new SimpleXMLElement($the_string);
				$json = json_encode($xml);
				$response_array = json_decode($json,TRUE);

				$clean_response_array = json_decode($json,TRUE);
				$clean_response_array['TranResponse']['AcctNo'] = 'xxxxxxxxxxxx'.$this->lastfour;
				$clean_response_array['TranResponse']['ExpDate'] = 'xxxx';
				$string = var_export($clean_response_array, true);
				myerror_log("mercury pay response: ".$string);
				
				$cmd_response = $response_array['CmdResponse'];
				$cmd_status = $cmd_response['CmdStatus'];
			
				if (strtolower($cmd_status) == 'approved')
				{
					$auth_code = $response_array['TranResponse']['AuthCode'];
					$acq_ref_data = $response_array['TranResponse']['AcqRefData'];
					$process_data = $response_array['TranResponse']['ProcessData'];
					$ref_no = $response_array['TranResponse']['RefNo'];
					$response_array['response_code'] = 100;
					$response_array['result'] = 'success';
					$response_array['auth_code'] = $auth_code;
					$response_array['acq_ref_data'] = $acq_ref_data;
					$response_array['process_data'] = $process_data;
					//$response_array['authcode'] = "$auth_code;AcqRefData=$acq_ref_data;ProcessData=$process_data";
					$response_array['authcode'] = $auth_code;
					$response_array['transactionid'] = 'ref_no='.$ref_no;
					$response_array['responsetext'] = $cmd_response['TextResponse'];
					$this->text_response = $cmd_response['TextResponse'];
				}
				else
				{
					$cmd_error_text = $cmd_response['TextResponse'];
					$this->text_response = $cmd_error_text;
					$response_array['response_code'] = 500;
					$response_array['responsetext'] = "the charge was declined. message: ".$cmd_status."  ".$cmd_error_text;
					if ($response_array['DSIXReturnCode'] == '009999')
						MailIt::sendErrorEmail("MERCURY PAY ERROR!", "Serious Mercury pay error: ".$cmd_error_text);
				}
				
				myerror_log("The Status: $cmd_status");
				
				return $response_array;
		}
		catch (Exception $e) {
			myerror_log("******ERROR********");
			$last = $client->__getLastRequest();
			myerror_log("error: ".$e->getMessage());
			myerror_log($last);
			myerror_log("******ERROR2********");
		}
	}	
}
?>
