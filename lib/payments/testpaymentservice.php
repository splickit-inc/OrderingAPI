<?php
class TestPaymentService extends SplickitPaymentService
{

	function processPayment($amount)
	{
		throw new Exception("not a real class exception. should only be used for testing parent methods");
	}
}
?>