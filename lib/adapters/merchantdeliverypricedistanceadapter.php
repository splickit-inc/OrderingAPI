<?php

class MerchantDeliveryPriceDistanceAdapter extends MySQLAdapter
{

	var $sql1 = "ALTER TABLE `Merchant_Delivery_Price_Distance` ADD `polygon_coordinates` MEDIUMTEXT NULL DEFAULT NULL AFTER `zip_codes`; ";
	var $sql2 = "ALTER TABLE `Merchant_Delivery_Price_Distance` ADD `minimum_order_amount` DECIMAL(10,2) NULL DEFAULT NULL AFTER `price`, ADD `name` VARCHAR(255) NULL DEFAULT NULL AFTER `merchant_id`;";

	private $catering;
	private $delivery_calculation_type;
	private $doordash_error_result;

	function MerchantDeliveryPriceDistanceAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_Delivery_Price_Distance',
			'%([0-9]{2,15})%',
			'%d',
			array('map_id'),
			null,
			array('created','modified')
			);
	}

	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }

    static function areThereMinimumsSetOnDeliveryDistancePriceRecords($merchant_id)
    {
    	$mdpd_adapter = new MerchantDeliveryPriceDistanceAdapter($mimetypes);
    	$sql = "SELECT * FROM Merchant_Delivery_Price_Distance WHERE merchant_id = $merchant_id and minimum_order_amount IS NOT NULL";
    	$options[TONIC_FIND_BY_SQL] = $sql;
    	$rows = $mdpd_adapter->select('',$options);
    	if (count($rows) > 0) {
    		return true;
    	}
    	return false;
    }
    
    /**
     * 
     * @desc gets the price for a given user delivery location resouce and a merchant delivery info resource
     * 
     * @param $udl_resource
     * @param $mdi_resource
     * 
     * @return price if valid and false if not valid.
     * 
     * @throws Exception if there is a problem with the set up
     */
	function getDeliveryPriceFromUserDeliveryLocationAndMerchantDeliveryInfoResources($udl_resource,$mdi_resource)
    {
    	$resource = $this->getDeliveryPriceResourceFromUserDeliveryLocationAndMerchantDeliveryInfoResources($udl_resource,$mdi_resource);
    	return $this->returnPriceOnResourceIfItExistsReturnFalseOtherwise($resource);
    }
    
    function getDeliveryPriceResourceFromUserDeliveryLocationAndMerchantDeliveryInfoResources($udl_resource,$mdi_resource)
    {
    	$delivery_type_with_upper_case_first_letter = ucfirst($mdi_resource->delivery_price_type);
    	//for backwards compatability in case of data errors
    	if ($mdi_resource->delivery_price_type == 'driving' && $mdi_resource->zip_codes == 'true') {
    		$delivery_type_with_upper_case_first_letter = 'Zip';	
    	}
    	$method_name = "getPriceResourceBy".$delivery_type_with_upper_case_first_letter;
    	if ($merchant_delivery_price_resource = $this->$method_name($udl_resource,$mdi_resource)) {
    	    if (strtolower($merchant_delivery_price_resource->name) == 'doordash') {
                return $merchant_delivery_price_resource;
            }
    		$this->createUserDeliveryLocationMerchantPriceRecord($udl_resource->user_addr_id, $mdi_resource->merchant_id, $merchant_delivery_price_resource->map_id,$merchant_delivery_price_resource->delivery_type);
    		return $merchant_delivery_price_resource;
    	} else {
    	    return false;
        }
    }
    
    function createUserDeliveryLocationMerchantPriceRecord($user_delivery_location_id,$merchant_id,$map_id,$merchant_delivery_type = 'Regular')
    {
    	$udlmpma = new UserDeliveryLocationMerchantPriceMapsAdapter($mimetypes);
    	$udlmpma->createRecord($user_delivery_location_id, $merchant_id, $map_id,$merchant_delivery_type);
    }
    
    function getPriceByZip($udl_resource, $mdi_resource)
    {
    	$resource = $this->getPriceResourceByZip($udl_resource, $mdi_resource);
    	return $this->returnPriceOnResourceIfItExistsReturnFalseOtherwise($resource);
    }
    
    function getPriceByPolygon($udl_resource, $mdi_resource)
    {
    	$resource = $this->getPriceResourceByPolygon($udl_resource, $mdi_resource);
    	return $this->returnPriceOnResourceIfItExistsReturnFalseOtherwise($resource);
    }
    
    function getPriceByDriving($udl_resource, $mdi_resource)
    {
    	$resource = $this->getPriceResourceByDriving($udl_resource, $mdi_resource);
    	return $this->returnPriceOnResourceIfItExistsReturnFalseOtherwise($resource);
    }

    /****  getting record ****/
    
    function getPriceResourceByZip($udl_resource, $mdi_resource)
    {
        $this->delivery_calculation_type = 'zip';
    	return $this->getDeliveryPriceResourceByZipCodeAndMerchantId($udl_resource->zip, $mdi_resource->merchant_id);
    }
    
    function getPriceResourceByPolygon($udl_resource, $mdi_resource)
    {
        $this->delivery_calculation_type = 'polygon';
    	return $this->getPolygonDeliveryPriceResourceByUserLocationAndMerchant($udl_resource, $mdi_resource->merchant_id);
    }
    
    function getPriceResourceByDriving($udl_resource, $mdi_resource)
    {
        $this->delivery_calculation_type = 'driving';
    	return $this->getDeliveryPriceResourceByDrivingDistanceBetweenMerchantAndUserDeliveryLocation($udl_resource, $mdi_resource);
    }

    function getPriceResourceByDoordash($udl_resource, $mdi_resource)
    {
        $this->delivery_calculation_type = 'doordash';
        $merchant = MerchantAdapter::staticGetRecordByPrimaryKey($mdi_resource->merchant_id,'MerchantAdapter');

        $result = $this->getDeliveryPriceByDoordash($udl_resource, $merchant);
        return $this->processGetDeliveryPriceByDoordashResult($result,$merchant['merchant_id']);
    }



    function getPriceResourceByMixed($udl_resource, $mdi_resource)
    {
        $merchant_delivery_price_resources = $this->getMerchantDeliveryPriceDistanceResourcesOrderedByPriceAscending($mdi_resource->merchant_id);
        foreach ($merchant_delivery_price_resources as $merchant_delivery_price_resource)
        {
            if ($merchant_delivery_price_resource->distance_up_to > 0) {
                if ($result = $this->getDeliveryPriceResourceByDrivingDistanceBetweenMerchantAndUserDeliveryLocation($udl_resource, $mdi_resource)) {
                    return $result;
                }
            } else if ($merchant_delivery_price_resource->polygon_coordinates != null) {
                if ($result = $this->getPolygonDeliveryPriceResourceByUserLocationAndMerchant($udl_resource, $mdi_resource->merchant_id)) {
                    return $result;
                }
            } else if ($merchant_delivery_price_resource->zip_codes != null) {
                if ($result = $this->getDeliveryPriceResourceByZipCodeAndMerchantId($udl_resource->zip, $mdi_resource->merchant_id)) {
                    return $result;
                }
            } else if (strtolower($merchant_delivery_price_resource->name) == 'doordash' || strtolower($merchant_delivery_price_resource->name) == 'door dash') {
                $merchant = MerchantAdapter::staticGetRecordByPrimaryKey($mdi_resource->merchant_id,'MerchantAdapter');
                $result = $this->getDeliveryPriceByDoordash($udl_resource,$merchant);
                return $this->processGetDeliveryPriceByDoordashResult($result,$merchant['merchant_id']);
            }
        }
        return false;
    }

    function processGetDeliveryPriceByDoordashResult($result,$merchant_id)
    {
        if ($result['http_code'] == 200 || $result['http_code'] == 201) {
            $mdpd_data['merchant_id'] = $merchant_id;
            $mdpd_data['active'] = 'Y';
            $mdpd_data['name'] = 'Doordash';
            $mdpd_options[TONIC_FIND_BY_METADATA] = $mdpd_data;
            if ($merchant_delivery_price_resource = Resource::find($this,'',$mdpd_options)) {
                $merchant_delivery_price_resource->set('price', $result['fee']/100);
                $merchant_delivery_price_resource->set('delivery_time', $result['delivery_time']);
                $delivery_time_stamp = strtotime($result['delivery_time']);
                $local_delivery_time_from_door_dash = date('Y-m-d H:i:s',$delivery_time_stamp);
                $merchant_delivery_price_resource->set('delivery_timestamp',$delivery_time_stamp);
                $merchant_delivery_price_resource->set('local_delivery_time',$local_delivery_time_from_door_dash);
                return $merchant_delivery_price_resource;
            } else {
                myerror_log('unable to find MerchantDeliveryPriceDistanceRecord for Doordash merchant_id: '.$merchant['merchant_id']);
                throw new Exception('unable to find MerchantDeliveryPriceDistanceRecord for Doordash merchant_id: '.$merchant['merchant_id']);
            }
        } else {
            $result['name'] = 'Doordash';
            $this->doordash_error_result = $result;
        }
        return Resource::dummyfactory($result);
    }

    function hasDoorDashError()
    {
        return $this->getDoordashErrorResult()['failure'] == true;
    }

    function getDoordashErrorResult()
    {
        return $this->doordash_error_result;
    }

    function getDeliveryPriceByDoordash($user_delivery_location_resource,$merchant)
    {
        $this->delivery_calculation_type = 'doordash';
        // first call door dash to determine if delivery can happen, when it can happen, and how much it will cost
        $doordash_service = new DoordashService();
        $result = $doordash_service->getEstimate($user_delivery_location_resource->getDataFieldsReally(),$merchant);
        return $result;
    }


    /**
     * 
     * @desc To be used if merchant caculates price based on distance  NOT ON ZIP CODE
     * 
     * @param $user_delivery_location_resource
     * @param $mdi_resource (Merchant_delivery_info_resource)
     * 
     * @return price or false if out of Range
     * 
     * @throws Exception if there is a problem with the merchants delivery info.
     */
    
    function getDeliveryPriceResourceByDrivingDistanceBetweenMerchantAndUserDeliveryLocation($user_delivery_location_resource,$mdi_resource)
    {
    	$miles = $this->getMilesFromUserDeliveryLocationAndMerchantResources($user_delivery_location_resource, $mdi_resource);
		return $this->getDeliveryPriceResourceByDrivingDistanceAndMerchant($miles, $mdi_resource->merchant_id);
    }
    
    /**
     * @param calculate the driving distance between the two entities from their ID's
     * 
     * @param $udl_resource
     * @param $mdi_resource
     *
     * @return $miles
     * 
     * @throws Exception if setup is faulty
     */
    
    function getMilesFromUserDeliveryLocationAndMerchantIds($user_delivery_location_id,$merchant_id)
    {
    	$mdi_resource = MerchantDeliveryInfoAdapter::getFullMerchantDeliveryInfoAsResource($merchant_id);
    	$udl_resource = Resource::find(new UserDeliveryLocationAdapter($mimetypes),''.$user_delivery_location_id);
    	return $this->getMilesFromUserDeliveryLocationAndMerchantResources($udl_resource, $mdi_resource);	
    }
    
    /**
     * @param calculate the driving distance between the two entities from existing resources
     * 
     * @param $udl_resource
     * @param $mdi_resource
     *
     * @return $miles
     * 
     * @throws Exception if setup is faulty
     */
    
    function getMilesFromUserDeliveryLocationAndMerchantResources($udl_resource,$mdi_resource)
    {
    	$lat1 = $mdi_resource->lat;
		$lng1 = $mdi_resource->lng;
    	$lat2 = $udl_resource->lat;
		$lng2 = $udl_resource->lng;
    	
    	// get merchant info for lat long if needed
    	if ($lng1 == null && $lng1 == null)
    	{
	    	if ($merchant_resource = Resource::find(new MerchantAdapter($mimetypes),''.$merchant_id))
	    	{
	    		$lat1 = $merchant_resource->lat;
				$lng1 = $merchant_resource->lng;
	    	} else {
	    		myerror_log("ERROR!  HOLY COW! couldn't get merchant resource for submitted mercahnt id with delivery order");
	    		MailIt::sendErrorEmail("serious Error trying to get Merchant Resouce in MerchantDeliveryPriceDistanceAdapter", "merchant_id: ".$merchant_id);
	    		throw new Exception("We're sorry there appears to be a problem with this merchant's delivery information, so a delivery order cannot be submitted this time.", 520);
	    	}
    	}
    	$miles = $this->calculateDistance($lat1, $lng1, $lat2, $lng2);
    	return $miles;
    }

    /**
     * 
     * @desc given a distance in miles, will return a price for the delivery if in range and a boolean false if not.
     * 
     * @param decimal $miles
     * @param int $merchant_id
     * 
     * @return price or false if out of Range
     * 
     * @throws Exception if merchant has not set up their information yet
     */
    function getDeliveryPriceByDrivingDistanceAndMerchant($miles,$merchant_id)
    {
    	$resource = $this->getDeliveryPriceResourceByDrivingDistanceAndMerchant($miles,$merchant_id);
    	return $this->returnPriceOnResourceIfItExistsReturnFalseOtherwise($resource);
    }
    
    function getDeliveryPriceResourceByDrivingDistanceAndMerchant($miles,$merchant_id)
    {
    	if (is_string($miles)) {
    		$miles_as_float = floatval(str_replace(',', '', $miles));
    	} else {
    		$miles_as_float = floatval($miles);
    	}
    	$merchant_delivery_price_resources = $this->getMerchantDeliveryPriceDistanceResourcesOrderedByPriceAscending($merchant_id);
		foreach ($merchant_delivery_price_resources as $distance_resource)
		{
			$distance_up_to = floatval($distance_resource->distance_up_to);
			if ($miles_as_float < $distance_up_to) {
				return $distance_resource;
			}
		}
		return false;
    }
    
    /**
     * 
     * @desc given zip code, will return a price for the delivery if in range and a boolean false if not.
     * 
     * @param string $zip_code
     * @param int $merchant_id
     * 
     * @return price, false, or throws error if merchant price records are not set up yet.
     */
    function getDeliveryPriceByZipCodeAndMerchantId($zip_code,$merchant_id) {
    	$resource = $this->getDeliveryPriceResourceByZipCodeAndMerchantId($zip_code,$merchant_id);
    	return $this->returnPriceOnResourceIfItExistsReturnFalseOtherwise($resource);
    }
    
    function getDeliveryPriceResourceByZipCodeAndMerchantId($zip_code,$merchant_id)
    {
    	$merchant_delivery_price_resources = $this->getMerchantDeliveryPriceDistanceResourcesOrderedByPriceAscending($merchant_id);
		foreach ($merchant_delivery_price_resources as $price_resource)
		{
			$zip_code_string = $price_resource->zip_codes; 
			if (strpos($zip_code_string, $zip_code) !== false) {
    			return $price_resource;			
			}
		}
		return false;
	}
		
	function getPolygonDeliveryPriceByUserLocationAndMerchant($udl_resource,$merchant_id)
	{
    	$resource = $this->getPolygonDeliveryPriceResourceByUserLocationAndMerchant($udl_resource,$merchant_id);
		return $this->returnPriceOnResourceIfItExistsReturnFalseOtherwise($resource);
	}
	
	function getPolygonDeliveryPriceResourceByUserLocationAndMerchant($udl_resource,$merchant_id)
	{
		$merchant_delivery_price_resources = $this->getMerchantDeliveryPriceDistanceResourcesOrderedByPriceAscending($merchant_id);
		foreach ($merchant_delivery_price_resources as $merchant_delivery_price_distance_resource)
		{
			if ($this->isUserLocationWithinPolygon($udl_resource, $merchant_delivery_price_distance_resource->polygon_coordinates)) {
				return $merchant_delivery_price_distance_resource;
			}
		}
		return false;
	}
	
	function getMerchantDeliveryPriceDistanceResourcesOrderedByPriceAscending($merchant_id)
	{
    	$mdpd_data['merchant_id'] = $merchant_id;
    	if ($this->getCatering()) {
    	    $mdpd_data['delivery_type'] = 'Catering';
        } else {
            $mdpd_data['delivery_type'] = 'Regular';
        }
    	$mdpd_data['active'] = 'Y';
		$mdpd_options[TONIC_FIND_BY_METADATA] = $mdpd_data;
		$mdpd_options[TONIC_SORT_BY_METADATA] = "price ASC";
		if ($merchant_delivery_price_resources = Resource::findAll($this,'',$mdpd_options)) {
			return $merchant_delivery_price_resources;
		} else {
		    if ($this->getCatering()) {
		        // there are no catering records so use the regular
                // probably should figure this out earlier in the process, like we we get the delivery info, set a flag that there are catering delivery price records.
                $mdpd_options[TONIC_FIND_BY_METADATA]['delivery_type'] = 'Regular';
                if ($merchant_delivery_price_resources = Resource::findAll($this,'',$mdpd_options)) {
                    return $merchant_delivery_price_resources;
                }
            }
			throw new NoMerchantDeliveryPriceRecordsExistException();
		}
		
	}
		
	function isUserLocationWithinPolygon($udl_resource,$polygon_coordinates_as_string)
	{
		$user_lat = $udl_resource->lat;
		$user_lng = $udl_resource->lng;
		$user_lat_lng_as_string = "$user_lat $user_lng";
		$polygon = $this->createPolygonArrayFromString($polygon_coordinates_as_string);
		return PointLocation::isPointWithinThePolygon($user_lat_lng_as_string, $polygon);
	}
	
	function createPolygonArrayFromString($polygon_coordinates_as_string)
	{
		//create array out of polygon string
		$polygon = explode(",", $polygon_coordinates_as_string);
		return $this->checkAndModifyPolygonIfLastCoordinatesDoNotMatchFirst($polygon);
	}
	
	function checkAndModifyPolygonIfLastCoordinatesDoNotMatchFirst($polygon)
	{
		// if last does not equal first set it.
		$last = end(array_values($polygon));
		$first = $polygon[0];
		if ($first != $last) {
			$polygon[] = $first;
		}
		return $polygon;
	}

	static function staticGetMerchantPriceRecordsAsResourcesByMerchantId($merchant_id)
    {
        $mdpda = new MerchantDeliveryPriceDistanceAdapter();
        return $mdpda->getMerchantPriceRecordsAsResourcesByMerchantId($merchant_id);
    }

	function getMerchantPriceRecordsAsResourcesByMerchantId($merchant_id)
    {
        $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
        $options[TONIC_SORT_BY_METADATA] = "price ASC";
        return Resource::findAll($this,null,$options);
    }
	
	/**
	 * 
	 * @desc Static function to return the driving distance bewteen two lats and longs.
	 * @param decimal $lat1
	 * @param decimal $lng1
	 * @param decimal $lat2
	 * @param decimal $lng2
	 */
	
	static function calculateDistance($lat1,$lng1,$lat2,$lng2,$use_google = true)
	{
				//  1.calculate distace based on lat lng only.  as the crow flies
				myerror_log($lat1.' '.$lng1.' : '.$lat2.' '.$lng2);
				
				$miles = 0.00;					
				if ($lat2 == null || $lat2 == '' || $lng2 == null || $lng2 == '')
					return $miles;
				
				try {
					$theta = $lng1 - $lng2; 
					$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta)); 
					$dist = acos($dist); 
					$dist = rad2deg($dist); 
					$miles = $dist * 60 * 1.1515;
					$miles = number_format($miles, 2);
					myerror_logging(3,"we calculated the distance as the crow flys to be: ".$miles);
				} catch (Exception $e) {
					myerror_log("ERROR:  could not calculate distance from store");
				}
				
				//$url = "http://maps.google.com/maps/nav?q=from:28.054352,-82.432316%20to:28.014483,-82.475637";
				
				// now calculate distance with google maps.  driving distances
			if ($use_google)
			{	
				try {
					$miles_google_api = GoogleGeoCoderService::getShortestDrivingDistance($lat1, $lng1, $lat2, $lng2);
				} catch (Exception $e2) {
					myerror_log("ERROR!  SOME error trying to get the google maps driving distance: ".$e2->getMessage());
				}				
				
				$diff = $miles_google_api-$miles;
				myerror_log("calulated distance: ".$miles."   google driving distance: ".$miles_google_api."     diff: ".$diff);	
				//myerror_log("the distance is: ".$miles);
				if ($miles_google_api > $miles)
					return $miles_google_api;
			}
			return $miles;
	}

    function setCatering()
    {
        $this->catering = true;
    }

    function getCatering()
    {
        return $this->catering;
    }

	private function returnPriceOnResourceIfItExistsReturnFalseOtherwise($resource)
	{
		if ($resource) {
			return $resource->price;
		} else {
			return false;
		}
	}

	function getDeliveryCalculationType()
    {
        return $this->delivery_calculation_type;
    }

}

class NoMerchantDeliveryPriceRecordsExistException extends Exception
{
    public function __construct() {
        parent::__construct("We're sorry, this merchant has not set up their delivery information yet so a delivery order cannot be submitted at this time.", 520);
    }
}
?>