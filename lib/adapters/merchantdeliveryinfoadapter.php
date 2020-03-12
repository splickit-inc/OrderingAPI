<?php

class MerchantDeliveryInfoAdapter extends MySQLAdapter
{
	
	var	$sql1 = "ALTER TABLE `Merchant_Delivery_Info` ADD `delivery_price_type` ENUM( 'driving', 'zip', 'polygon' ) NOT NULL DEFAULT 'driving' AFTER `zip_codes` ";
	var	$sql2 = "UPDATE `Merchant_Delivery_Info` set delivery_price_type = 'zip' WHERE zip_codes = 'true'";
	
	private $cached_price_boolean = false;
	private $catering = false;
	private $merchant_catering_delivery_active = false;

	private $user_delivery_location_resource;
	private $base_order_details;
	private $doordash_error_result;
    private $delivery_calculation_type;


	const MERCHANT_DELIVERY_NOT_ACTIVE_ERROR_MESSAGE = "We're sorry, it appears this merchant is not accepting delivery orders at the moment. This may have just changed, sorry for the inconvenience.";

	
	function MerchantDeliveryInfoAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_Delivery_Info',
			'%([0-9]{2,15})%',
			'%d',
			array('merchant_delivery_id'),
			null,
			array('created','modified')
			);
	}

	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	if ($options[TONIC_FIND_BY_METADATA]['delivery_type'] == null) {
            $options[TONIC_FIND_BY_METADATA]['delivery_type'] = 'Regular';
        }
    	return parent::select($url,$options);
    }
    
    /**
     * @desc static function to get a MerchantDeliveryInfo resource with lat and long of the merhant;
     * 
     * @param int $merchant_id
     * 
     * returns the resource or false if it cant get the info
     */
    
    static function getFullMerchantDeliveryInfoAsResource($merchant_id)
    {
    	$mdi_data['merchant_id'] = $merchant_id;
    	$options[TONIC_FIND_BY_METADATA] = $mdi_data;
    	if ($mdi_resource = Resource::find(new MerchantDeliveryInfoAdapter(getM()),null,$options))
    	{
	    	if ($merchant_resource = Resource::find(new MerchantAdapter(getM()),''.$merchant_id))
	    	{
		    	$mdi_resource->set('lat',$merchant_resource->lat);
		    	$mdi_resource->set('lng',$merchant_resource->lng);
		    	$mdi_resource->set('merchant_delivery_active',$merchant_resource->delivery);
		    	return $mdi_resource;
	    	}
    	}
    	return false;
    }
    
    /**
     * 
     * @desc Used to calculate the delivery price based on Id's.  
     * 
     * @param $user_delivery_location_id
     * @param $merchant_id
     * 
     * @throws Exception with error message to user
     */
    
    function getDeliveryPriceFromIds($user_delivery_location_id,$merchant_id)
    {    	
    	if ($resource = $this->getMerchantDeliveryPriceResourceForUserLocationAndMerchantId($user_delivery_location_id,$merchant_id)) {
    		return $resource->price;
    	}
    	return false;    	
    }
    
    /**
     * 
     * @desc will get the cheapest matching merchant delivery location that the users address falls into.  
     * @param int $user_delivery_location_id
     * @param int $merchant_id
     * @return Resource
     */
    function getMerchantDeliveryPriceResourceForUserLocationAndMerchantId($user_delivery_location_id,$merchant_id)
    {
        if ($user_delivery_location_id == null) {
            myerror_log("ERROR!!! no user delivery location id subitted to get merchant delivery price");
            return createErrorResourceWithHttpCode("Sorry there was an error and your request could not be processed",500,500);
        } else if ($merchant_id == null) {
            myerror_log("ERROR!!! no merchant id subitted to get merchant delivery price");
            return createErrorResourceWithHttpCode("Sorry there was an error and your request could not be processed",500,500);
        }
    	$this->cached_price_boolean = false;
    	if ($mdi_resource = $this->getFullMerchantDeliveryInfoAsResource($merchant_id)) {
            if (!$this->catering && $mdi_resource->merchant_delivery_active == 'N' ) {
                myerror_log("ERROR! Merchant showing delivery NOT active but order for delivery submitted");
                MailIt::sendErrorEmail('Impossible error', 'Merchant showing delivery NOT active but order for delivery submitted.  merchant_id: ' . $merchant_id);
                throw new MerchantDeliveryNotActiveException();
                //return returnErrorResource("We're sorry, this merchant has not set up their delivery information yet so a delivery order cannot be submitted at this time.", 520);
            } else if ($this->catering && !$this->merchant_catering_delivery_active) {
                myerror_log("ERROR!  Merchant with catering delivery NOT active but order for catering delivery submitted");
                MailIt::sendErrorEmail('Impossible error', 'Merchant showing catering delivery NOT active but order for catering delivery submitted.  merchant_id: ' . $merchant_id);
                throw new MerchantDeliveryNotActiveException();
            } else if ($mdpd_id = $this->getCachedDeliveryPriceResourceIdIfItExists($user_delivery_location_id, $merchant_id,$this->catering)) {
                myerror_logging(3,"we have found a cached merchant delivery price from user_delivery_location_id: $user_delivery_location_id,   and merchant_id:  $merchant_id");
                $this->cached_price_boolean = true;
                $merchant_delivery_price_resource = SplickitController::getResourceFromId($mdpd_id, 'MerchantDeliveryPriceDistance');
                return $merchant_delivery_price_resource;
            }
	    	if ($udl_resource = Resource::find(new UserDeliveryLocationAdapter(getM()),''.$user_delivery_location_id))
	    	{
//	    	    if ($order_amt = $this->base_order_details['order_amt']) {
//	    	        $udl_resource->set('order_amt',$order_amt);
//                }
	    	    foreach ($this->base_order_details as $name=>$value) {
                    $udl_resource->set($name,$value);
                }
	    	    $this->user_delivery_location_resource = $udl_resource;
	    		$mdpd_adapter = new MerchantDeliveryPriceDistanceAdapter(getM());
	    		if ($this->getCatering()) {
	    		    $mdpd_adapter->setCatering();
                }
	    		$result = $mdpd_adapter->getDeliveryPriceResourceFromUserDeliveryLocationAndMerchantDeliveryInfoResources($udl_resource, $mdi_resource);
	    		if ($mdpd_adapter->getDeliveryCalculationType() == 'doordash' && $mdpd_adapter->hasDoorDashError() ) {
	    		   myerror_log("we have a door dash fail");
	    		   $this->doordash_error_result = $mdpd_adapter->getDoordashErrorResult();
	    		   $this->delivery_calculation_type = 'doordash';
	    		   return null;
                }
	    		return $result;
	    	} else {
	    		throw new NoMatchingUserDeliveryLocationException();
	    	}
    	} else {
			myerror_log("ERROR!  Merchant delivery information does not exist but delivery price distance request has been submitted");
			MailIt::sendErrorEmail('Impossible error',  'Merchant delivery information does not exist but delivery price distance request has been submitted.  merchant_id: '.$merchant_id);
    		throw new NoMerchantDeliveryInformationException();
    	}    	
    }

    function getDeliveryTaxAmount($merchant, $delivery_price)
    {
        if ($delivery_tax_rate = $this->getDeliveryTaxRateOverride($merchant['merchant_id'])) {
            myerror_log("we have a delivery tax rate override of: " . $delivery_tax_rate);
        } else {
            $lookup_adapter = new LookupAdapter(getM());
            if (strtolower($lookup_adapter->getNameFromTypeAndValue("state_delivery_is_taxed", $merchant['state'])) == 'yes') {
                $delivery_tax_rate = TaxAdapter::staticGetTotalBaseTaxRate($merchant['merchant_id']);
            } else {
                $delivery_tax_rate = 0;
            }
        }
        $delivery_tax_amount = $delivery_price * ($delivery_tax_rate / 100);
        return number_format($delivery_tax_amount, 2);
    }

    function getDeliveryTaxRateOverride($merchant_id)
    {
        $tax_adapter = new TaxAdapter(getM());
        if ($record = $tax_adapter->getRecord(array("merchant_id" => $merchant_id, "locale" => 'Delivery'))) {
            return $record['rate'];
        }
    }
    
    function getCachedDeliveryPriceResourceIdIfItExists($user_delivery_location_id,$merchant_id,$catering = false)
    {
    	$udlmpma = new UserDeliveryLocationMerchantPriceMapsAdapter(getM());
    	if ($merchant_delivery_price_distance_map_id = $udlmpma->getStoredUserDeliveryLocationMerchantPriceDistanceMapIdIfItExists($user_delivery_location_id, $merchant_id,$catering)) {
    		return $merchant_delivery_price_distance_map_id;
    	}	
    	return false;
    }

    function getCachedPriceBooleanForLastCall()
    {
    	return $this->cached_price_boolean;
    }

    function setCateringDeliveryActive()
    {
        $this->merchant_catering_delivery_active = true;
    }

    function setCatering()
    {
        $this->catering = true;
    }

    function getCatering()
    {
        return $this->catering;
    }

    function getUserDeliveryLocationResource()
    {
        return $this->user_delivery_location_resource;
    }

    function setBaseOrderDetails($base_order_detials)
    {
        $this->base_order_details = $base_order_detials;
    }

    function getDeliveryCalculationType()
    {
        return $this->delivery_calculation_type;
    }


}

class MerchantDeliveryException extends Exception
{
    public function __construct($message)
    {
        parent::__construct($message);
    }
}

class NoMatchingUserDeliveryLocationException extends MerchantDeliveryException
{
    public function __construct() {
        parent::__construct("We're sorry, but we can't find this delivery address record, please create a new one", 530);
    }
}

class NoMerchantDeliveryInformationException extends MerchantDeliveryException
{
    public function __construct() {
        parent::__construct("We're sorry, it appears this merchant has not set up their delivery information yet so a delivery order cannot be submitted a this time", 580);
    }
}

class MerchantDeliveryNotActiveException extends MerchantDeliveryException
{
    public function __construct() {
        parent::__construct(MerchantDeliveryInfoAdapter::MERCHANT_DELIVERY_NOT_ACTIVE_ERROR_MESSAGE, 580);
    }
}

?>