<?php
Class QubeController extends MessageController
{
	protected $representation = '/utility_templates/blank.txt';
		
	protected $format = 'Q';
	protected $format_name = 'qube';
	protected $max_retries = 2;
	protected $retry_delay = 1;
		
	function QubeController($mt,$u,&$r,$l = 0)
	{
		parent::MessageController($mt,$u,$r,$l);		
	}
	
	function send($body)
	{
		$result = MessageQubeSender::sendMessageToQube($body,$this->deliver_to_addr);
		myerror_log("result from message qube is: ".$result['message']);
		return true;	
	}
	
	function formatProcessor($string)
	{
		$string = str_replace('::','', $string);
		return $string;
	}
	
}