<?php

class UserDeliveryLocationMerchantPriceMapsAdapter extends MySQLAdapter
{

	function __construct($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'User_Delivery_Location_Merchant_Price_Maps',
			'%([0-9]{4,10})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
		
		$this->allow_full_table_scan = false;
						
	}
	
	function &select($url, $options = NULL)
    {
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
	}

	function createRecord($user_delivery_location_id,$merchant_id,$map_id,$merchant_delivery_type = 'Regular')
	{
		$data['user_delivery_location_id'] = $user_delivery_location_id;
		$data['merchant_id'] = $merchant_id;
		$data['merchant_delivery_price_distance_map_id'] = $map_id;
		$data['delivery_type'] = $merchant_delivery_type;
		return $resource = Resource::createByData($this, $data);
	}
	
	function getStoredUserDeliveryLocationMerchantPriceDistanceMapIdIfItExists($user_delivery_location_id,$merchant_id,$catering)
	{
	    $data = array("user_delivery_location_id"=>$user_delivery_location_id,"merchant_id"=>$merchant_id);
	    $data['delivery_type'] = $catering ? 'Catering' : 'Regular';
		if ($record = $this->getRecord($data)) {
			return $record['merchant_delivery_price_distance_map_id'];
		}
	}
	
	static function staticGetStoredUserDeliveryLocationMerchantPriceDistanceMapIdIfItExists($user_delivery_location_id,$merchant_id) {
		$udlmpma = new UserDeliveryLocationMerchantPriceMapsAdapter($mimetypes);
		return $udlmpma->getStoredUserDeliveryLocationMerchantPriceDistanceMapIdIfItExists($user_delivery_location_id, $merchant_id);
	}
	
	static function deleteRecordsForUserDeliveryLocationId($udl_id)
	{
		$udlmpma = new UserDeliveryLocationMerchantPriceMapsAdapter($mimetypes);
		$sql = "DELETE FROM User_Delivery_Location_Merchant_Price_Maps WHERE user_delivery_location_id = $udl_id";
		$udlmpma->_query($sql);
	}
}
?>