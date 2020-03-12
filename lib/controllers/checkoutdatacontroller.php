<?php

class CheckoutDataController extends SplickitController
{
	
	private $user_message_title;
	private $user_message;
	private $error_code;
	private $error;
	
	function CheckoutDataController($mt,$u,$r,$l = 0)
	{
		parent::SplickitController($mt,$u,$r,$l);
	}
	
}
?>	
