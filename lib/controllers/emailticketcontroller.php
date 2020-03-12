<?php

Class EmailTicketController extends EmailController
{
	protected $representation = '/order_templates/email/execute_order_email-ticket.htm';
	protected $format = 'ET';
	protected $format_name = 'emailticket';
		
	function EmailTicketController($mt,$u,&$r,$l = 0)
	{
		parent::EmailController($mt,$u,$r,$l);		
	}

}
?>