<?php

class LoyaltyParkingLotRecordsAdapter extends MySQLAdapter
{

	function LoyaltyParkingLotRecordsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Loyalty_Parking_Lot_Records',
			'%([0-9]{4,10})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
		
		$this->allow_full_table_scan = false;
						
	}

	function saveRemoteRecord($data)
	{
		$data['brand_id'] = getBrandIdFromCurrentContext();
		logData($data,"remote record save in parking lot",5);
		$resource = Resource::createByData($this,$data);
		return $resource;
	}
	
}
?>