<?php
class DeliveryController extends SplickitController
{	
	private $user_message_title;
	private $user_message;
	private $error_code;
	private $error;
    private $merchant_id;

	var $catering = false;
	var $merchant_catering_info;

	const DOORDASH_ESTIMATE_USER_MESSAGE = "Please note this is an estimate from DoorDash";
	const DOORDASH_CANNOT_DELIVER_MESSAGE = "We're sorry, but Doorddash is reporting they cannot deliver to this location at this time.";
	const DOORDASH_STORE_CLOSED_MESSAGE = "We're sorry, but this merchant uses DoorDash to deliver to your location and cannot accept orders when the store is closed.";
	
	function DeliveryController($mt,$u,$r,$l = 0)
	{
		parent::SplickitController($mt,$u,$r,$l);
		if ($merchant_id = $r->data['merchant_id']) {
			$this->merchant_id = $merchant_id;
		}
		if ($this->hasRequestForDestination('catering')) {
			$this->catering = true;
			if ($merchant_id) {
				$this->merchant_catering_info = MerchantCateringInfosAdapter::getInfoAsResourceByMerchantId($merchant_id);
			}

		}

	}
	
	function getRelevantDeliveryInfoFromRequest()
	{
		myerror_logging(2,"body in isindeliveryarea: ".$this->request->body);
		$merchant_id = $this->request->data['merchant_id'];
		$user_delivery_location_id = $this->request->data['user_addr_id'];
		$delivery_info = $this->getRelevantDeliveryInfoFromIds($user_delivery_location_id, $merchant_id);
		return $delivery_info;	
	}
	
	function getRelevantDeliveryInfoFromIds($user_delivery_location_id,$merchant_id)
	{
		myerror_logging(1, "starting isindeliveryarea with udl_id: ".$user_delivery_location_id."    merchant_id: ".$merchant_id);
		if ($merchant_id == null || $user_delivery_location_id == null || $merchant_id < 10) { 
			throw new BadDeliveryDataPassedInException();
		}
		$mdi_adapter = new MerchantDeliveryInfoAdapter(getM());
		if ($this->catering) {
			$mdi_adapter->setCatering();
		}
		if ($this->merchant_catering_info->delivery_active == 'Y') {
			$mdi_adapter->setCateringDeliveryActive();
		}
		if ($delivery_price_resource = $mdi_adapter->getMerchantDeliveryPriceResourceForUserLocationAndMerchantId($user_delivery_location_id, $merchant_id))
		{
			$delivery_info = $delivery_price_resource->getDataFieldsReally();
			$this->stripIrrelevantInfo($delivery_info); 
			$delivery_info['is_in_delivery_range'] = true;
			if ($delivery_info['minimum_order_amount'] > 0.01) {
				$delivery_info['user_message'] = "Please Note: This merchant has a minimum order amount of $".$delivery_info['minimum_order_amount']." for this delivery area.";
			}
			if (strtolower($delivery_info['name']) == 'doordash') {
				if (isset($delivery_info['user_message'])) {
                    $delivery_info['user_message'] .= "\r\n ".self::DOORDASH_ESTIMATE_USER_MESSAGE;
				} else {
                    $delivery_info['user_message'] = self::DOORDASH_ESTIMATE_USER_MESSAGE;
				}
			}
		} else {
			$delivery_info['is_in_delivery_range'] = false;
			if ($mdi_adapter->getDeliveryCalculationType() == 'doordash') {
				$delivery_info['user_message'] = self::DOORDASH_CANNOT_DELIVER_MESSAGE;
			}
		}
		return $delivery_info;
	}
	
	function stripIrrelevantInfo(&$data)
	{
		unset($data['merchant_id']);
		unset($data['distance_up_to']);
		unset($data['zip_codes']);
		unset($data['polygon_coordinates']);
		unset($data['class']);
		unset($data['logical_delete']);
		unset($data['mimetype']);
	}
}
class BadDeliveryDataPassedInException extends Exception
{
    public function __construct() {
    	MailIt::sendErrorEmail("ERROR THROWN for is in delivery areas!", "one of these things is null:   \r\n  merchant_id: $merchant_id    user_delivery_location: $user_delivery_location_id"); 
        parent::__construct("We're VERY sorry, but there appears to be a problem. Our techteam has been alerted, so please try again later or do a pickup order.", 580);   
    }
}
?>
