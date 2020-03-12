<?php
class PitCardStoredValuePaymentService extends HeartlandPaymentService
{
	function processPayment($amount)
	{
		$this->heartland_loyalty_service = PitapitLoyaltyController::loyaltyServiceFactory();
        return parent::processPayment($amount);
	}
}
?>