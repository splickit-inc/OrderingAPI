<?php

class MerchantAdapter extends MySQLAdapter
{

	function MerchantAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant',
			'%([0-9]{2,15})%',
			'%d',
			array('merchant_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	//setting this in the controller now.
    	//$options[TONIC_FIND_BY_METADATA]['active'] = 'Y';
    	return parent::select($url,$options);
    }
    
    function insert(&$resource)
    {
    	$code = $this->getAlphaNumericId();
		$resource->numeric_id = mt_rand(10000000,99999999);
		$resource->alphanumeric_id = $code;
		if (isLaptop() && (! isset($resource->lat)))
		{
				$address = "".$resource->address1.",".$resource->city.",".$resource->state." ".$resource->zip;
				$address = str_ireplace(' ', '+', $address);
				myerror_logging(2,"the address in Lat Long is: ".$address);
				if ($data = LatLong::generateLatLong($address))
				{
					$latitude = $data['lat'];
					$longitude = $data['lng'];
					$resource->lat = $latitude;
					$resource->lng = $longitude;
				}
		}
    	return parent::insert($resource);
    }
    
    function getSkinForMerchant($merchant_resource) {
      $smma = Resource::find(new SkinMerchantMapAdapter(getM()), '', array(TONIC_FIND_BY_METADATA => array('merchant_id' => $merchant_resource->merchant_id)));
      return Resource::find(new SkinLightAdapter(getM()), '', array(TONIC_FIND_BY_METADATA => array('skin_id' => $smma['skin_id'])));
    }
    
    function getMerchantMenuStatus($merchant_id,$menu_id,$menu_type)
    {
    	$merchant_resource = $this->getExactResourceFromData(array("merchant_id"=>$merchant_id));
    	$merchant_ts = $merchant_resource->modified;

    	if ($menu_type) {
	    	$mmm_adapter = new MerchantMenuMapAdapter(getM());
	    	$options = array("merchant_id"=>$merchant_id,"merchant_menu_type"=>$menu_type);
	    	$mmm_resource = $mmm_adapter->getExactResourceFromData($options);
	    	$menu_id = $mmm_resource->menu_id;
    	}
    	$menu_ts = MenuAdapter::getMenuStatus($menu_id);
    	
    	if ($menu_ts > $merchant_ts)
    		return $menu_ts;
    	else
    		return $merchant_ts;
    	
    }
    
	function setLatLong($limit = 0)
	{
		$options[TONIC_FIND_BY_METADATA]['lat'] = '0.000000';
		$options[TONIC_FIND_BY_METADATA]['lng'] = '0.000000';
		$options[TONIC_FIND_BY_METADATA]['merchant_id'] = array('>'=>1000);
		
		if ($merchants = Resource::findAll($this,null,$options))
		{
			$i=0;
			foreach ($merchants as $merchant_resource)
			{
				if ($merchant_resource->zip == '99999') {
					continue;
				}
				
				$address = "".$merchant_resource->address1.",".$merchant_resource->city.",".$merchant_resource->state." ".$merchant_resource->zip;
				$address = str_ireplace(' ', '+', $address);
				myerror_logging(2,"the address in Lat Long is: ".$address);
				if ($data = LatLong::generateLatLong($address))
				{
					$merchant_resource->lat = $data['lat'];
					$merchant_resource->lng = $data['lng'];
					myerror_log("about to update the lat long for mercahnt ".$merchant_resource->name."  id: ".$merchant_resource->merchant_id);
					if ($merchant_resource->save()) {
						myerror_log("merchant updated with lat long");
					} else {
						myerror_log("ERROR! lat long not updated: ".$merchant_resource->getAdapterError());
					}								
				} else {
					myerror_log("ERROR! could not generate lat long for address: ".$address);
					MailIt::sendErrorEmailSupport("Could not generate Lat Long for new merchant_id: ".$merchant_resource->merchant_id, "please set zip to 99999 to skip auto populate of lat long till address is fixed");
				}
				$i++;
				
				if (!$_SERVER['NOSLEEP']) {
					sleep(1);
				}
				
				if ($limit != 0 && $i == $limit)
					return true;
			}
		} else {
			myerror_log("no merchants to update with lat long");
		}
		return true;
	}

	function setAlphaNumericIds()
	{
		$options[TONIC_FIND_BY_SQL] = "SELECT * FROM Merchant WHERE numeric_id IS NULL and logical_delete = 'N'";
		if ($merchants = Resource::findAll($this,null,$options))
		{
			foreach ($merchants as $merchant_resource)
			{
				$code = $this->getAlphaNumericId();
				$merchant_resource->numeric_id = mt_rand(10000000,99999999);
				$merchant_resource->alphanumeric_id = $code;
				if ($merchant_resource->save())	
					myerror_log("merchant ".$merchant_resource->merchant_id." updated with unique id's");
				else
					myerror_log("random id generation failed. retry on next cycle: ".$this->getLastErrorText());
			}	
		} else {
			myerror_log("no merchants to update with alpha and numeric ID's");
		}
	}
	
	function getMerchantMetadata($url_string)
	{
			if ($merchant_resource = Resource::find($this,$url_string)) {
				$hour_adapter = new HourAdapter(getM());
				$tax_adapter = new TaxAdapter(getM());
				$resource = new Resource();
				$resource->set("merchant_id",$merchant_resource->merchant_id);
				if ($hours = $hour_adapter->newGetAllMerchantHoursHumanReadable($merchant_resource->merchant_id)) {
				    if ($pickup_hours = $hours['pickup']) {
                        $resource->set("hours",$pickup_hours);
                    }
                    if ($delivery_hours = $hours['delivery']) {
                        $resource->set("delivery_hours",$delivery_hours);
                    }
				} else {
					return returnErrorResource("no hours found for merchant id: ".$merchant_resource->merchant_id,999);
				}

					
				if ($tax = $tax_adapter->getTotalTax($merchant_resource->merchant_id)) {
					$resource->set("total_tax",$tax);
				}
				if ($tax_rates = $tax_adapter->getTotalTaxRates($merchant_resource->merchant_id)) {
					$resource->set("tax_rates",$tax_rates);
				} else {
					return returnErrorResource("No Tax information for this merchant id: ".$merchant_resource->merchant_id,999);
				}
			} else {	
				$resource = returnErrorResource("No matching merchant found",999);
			}
			return $resource;
	}
	
	function createMerchant($base_data)
	{
		$resource = Resource::factory($this,$base_data);
		$resource->numeric_id = mt_rand(10000000,99999999);
		$resource->alphanumeric_id = $this->getAlphaNumericId();
		
		$resource->save();
		return $resource;
	}
	
	/**
	 * @desc Named incorrectly, this is really createAlphaNumericId()
	 * 
	 */
	function getAlphaNumericId()
	{
		$characters = 'abc1234def567890ghijk1234lm567nop890qrstuvwxyz';
		$code = '';
		for ($i = 0;$i < 10;$i++) {
			$new = mt_rand(0,45);
			$code = $code.substr($characters, $new,1);				
		}
		$pref = mt_rand(10,99);
		$code = $pref.$code;
		return $code;		
	}
	
	static function getMerchantResourceFromAlphaNumeric($alpha_numeric_id)
	{
		$stringed_merchant_id = (string) $alpha_numeric_id;
		$merchant_options[TONIC_FIND_BY_METADATA]['alphanumeric_id'] = $stringed_merchant_id;
		$merchant_adapter = new MerchantAdapter($mimetypes);
		if ($merchant_resource = Resource::findExact($merchant_adapter,'',$merchant_options)) {
			return $merchant_resource;
		} else {
			return false;
		}
	}
	
	static function getMerchantFromExternalIdBrandId($merchant_external_id,$brand_id)
	{
		$merchant_options[TONIC_FIND_BY_METADATA]['merchant_external_id'] = $merchant_external_id;
		$merchant_options[TONIC_FIND_BY_METADATA]['brand_id'] = $brand_id;
		return Resource::findExact(new MerchantAdapter($mimetypes),'',$merchant_options);
	}
	
	static function getMerchantFromNumericId($numeric_id)
	{
		$clean_numeric_id = intval($numeric_id);
		$merchant_options[TONIC_FIND_BY_METADATA]['numeric_id'] = $clean_numeric_id;
		return Resource::findExact(new MerchantAdapter($mimetypes),'',$merchant_options);
	}
	
	static function getMerchantFromIdOrNumericId($merchant_id)
	{
		$stringed_merchant_id = (string) $merchant_id;
		$merchant_id = intval($merchant_id);
		
		$merchant_options[TONIC_FIND_BY_METADATA]['OR'] = array('merchant_id'=>$merchant_id,'numeric_id'=>$merchant_id);
		$merchant_adapter = new MerchantAdapter(getM());
		return Resource::findExact($merchant_adapter,'',$merchant_options);
		
	}
	
	static function setAlphaNumericAndLatLongsOfNewMerchants()
	{
		$merchant_adapter = new MerchantAdapter($mimetypes);
		myerror_log("************* about update new merchants with numeric and alphanumeric id's and lat longs *************");
		$merchant_adapter->setAlphaNumericIds();
		$merchant_adapter->setLatLong();
		return true;
	}
}
?>