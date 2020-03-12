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
		
		if (true) 
		{
			$auth_code = "ABCD1234";
			$acq_ref_data = "acq_ref_data";
			$process_data = "process data";
			$ref_no = "1234567890";
			$response_array['response_code'] = 100;
			$response_array['result'] = 'success';
			$response_array['auth_code'] = $auth_code;
			$response_array['acq_ref_data'] = $acq_ref_data;
			$response_array['process_data'] = $process_data;
			$response_array['authcode'] = $auth_code;
			$response_array['transactionid'] = 'ref_no='.$ref_no;
			$response_array['responsetext'] = "Success WIth Mercury";
			$this->text_response = "Success WIth Mercury";
		}
		else
		{
			$cmd_error_text = "The Charge Was Declined By Mercury";
			$this->text_response = $cmd_error_text;
			$response_array['response_code'] = 500;
			$response_array['responsetext'] = "the charge was declined. message: ".$cmd_error_text;
			if ($response_array['DSIXReturnCode'] == '009999')
				MailIt::sendErrorEmail("MERCURY PAY ERROR!", "Serious Mercury pay error: ".$cmd_error_text);
		}
		return $response_array;
				
	}	
}
?>
