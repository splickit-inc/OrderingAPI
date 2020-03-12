<?php
class PaymentGod
{
	var $merchant_payment_type_map_resource;
	var $merchant_payment_type_map_id;
	
	function __construct($merchant_payment_type_map_id)
	{
		$this->merchant_payment_type_map_id = $merchant_payment_type_map_id;
		$this->merchant_payment_type_map_resource = SplickitController::getResourceFromId($merchant_payment_type_map_id, 'MerchantPaymentTypeMaps');	
	}
	
	/**
	 * @desc generates the correct payment service from the $merchant_payment_type_map_id
	 * @param int $merchant_payment_type_map_id
	 * @param int $user_vault_id
	 * @return SplickitPaymentService
	 */
	static function paymentServiceFactoryByMerchantPaymentTypeMapId($merchant_payment_type_map_id,$billing_user)
	{
		if ($merchant_payment_type_map_resource = SplickitController::getResourceFromId($merchant_payment_type_map_id, 'MerchantPaymentTypeMaps'))
		{
			$data['merchant_id'] = $merchant_payment_type_map_resource->merchant_id;
			if ($billing_user)
			{
				$data['user'] = $billing_user;
				$data['user_id'] = $billing_user['user_id'];
				$data['uuid'] = $billing_user['uuid'];
			}
			$data['merchant_payment_type_map_id'] = $merchant_payment_type_map_id;	
			if ($billing_entity_id = $merchant_payment_type_map_resource->billing_entity_id) {
				$billing_entity_adapter = new BillingEntitiesAdapter($mimetypes);
				$billing_entity_record = $billing_entity_adapter->getRecordFromPrimaryKey($billing_entity_id);
				$data['billing_entity_record'] = $billing_entity_record;
				$data['billing_entity_external_id'] = $billing_entity_record['external_id'];
			}
			$data['splickit_accepted_payment_type_id'] = $merchant_payment_type_map_resource->splickit_accepted_payment_type_id;
			return PaymentGod::getPaymentServiceBySplickitAcceptedPaymentTypeId($merchant_payment_type_map_resource->splickit_accepted_payment_type_id,$data);
		}
	}

	static function getPaymentServiceBySplickitAcceptedPaymentTypeId($splickit_accepted_payment_type_id,$data)
    {
        $accepted_payment_type_resource = SplickitController::getResourceFromId($splickit_accepted_payment_type_id, 'SplickitAcceptedPaymentTypes');
        $payment_service_name = $accepted_payment_type_resource->payment_service;
        if ($payment_service_name == 'loyaltybalancepaymentservice') {
            $loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext();
            $payment_service_name = isset($loyalty_controller->payment_service_name) ? $loyalty_controller->payment_service_name : $payment_service_name;
        }
        if ($payment_service = new $payment_service_name($data)) {
            return $payment_service;
        }
    }
}
?>