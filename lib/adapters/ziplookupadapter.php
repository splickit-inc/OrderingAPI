<?php

class ZipLookupAdapter extends MySQLAdapter
{

	function ZipLookupAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Zip_Lookup',
			'%([0-9]{1,15})%',
			'%d',
			array('zip_tz_id'),
			array('zip_tz_id','zip','city','state','lat','lng','time_zone_offset','dst'),
			null
			);

			$this->field_types['zip'] = 'varchar';
	}
	
	/**
	 * @desc takes a zip code that has no record in the Zip Code Table and finds the lat long of a merchant that matches the first 3 or 2 digits
	 * 
	 */
	static function getFakeZipCodeLatLong($the_zip)
	{
		// first try to get the lat long of a zip with 3 matches
		$zip = (string)$the_zip;
		if (strlen($zip) == 3)
			$zip = '00'.$zip;
		else if (strlen($zip) == 4)
			$zip = '0'.$zip;
		$merchant_adapter = new MerchantAdapter($mimetypes);
		$short_zip = substr($zip,0,3);
		$zip_options[TONIC_FIND_BY_METADATA]['zip'] = array('like'=>$short_zip.'%');
		$zip_options[TONIC_SORT_BY] = ' merchant_id ASC ';
		//if ($results = $zip_adapter->select('',$zip_options))
		if ($m_resources = Resource::findAll($merchant_adapter,'',$zip_options))
			$m_resource = $m_resources[0];
		else {
			$short_zip = substr($zip,0,2);
			$zip_options[TONIC_FIND_BY_METADATA]['zip'] = array('like'=>$short_zip.'%');
			if ($m_resources = Resource::findAll($merchant_adapter,'',$zip_options))
				$m_resource = $m_resources[0];
		}
		if ($m_resource)
		{
			$zip_record['lat'] = $m_resource->lat;
			$zip_record['lng'] = $m_resource->lng;
			$zip_record['time_zone_offset'] = $m_resource->time_zone;
			if ($m_resource->state != 'AZ')
				$zip_record['dst'] = 1;
			return $zip_record;
		}
		return false;
	}
	
	/**
	 * 
	 * @desc takes a zip code and returns  a hash of the lat,lng, and time_zone_offset, along with city, state, and whether it participates in DST
	 * 
	 * @param unknown_type $zip
	 */
	static function getZipInfo($zip)
	{
		$zip_adapter = new ZipLookupAdapter($mimetypes);
		$zip_data['zip'] = (string)$zip;
		$zip_options[TONIC_FIND_BY_METADATA] = $zip_data;
		//if ($results = $zip_adapter->select('',$zip_options))
		if ($zip_resource = Resource::find($zip_adapter,'',$zip_options))
		{
			if ($zip_resource->lat == 0)
			{
				$google_data = LatLong::generateLatLong($zip,false);
				if (($google_data['lng'] > -65) ||  ($google_data['lat'] < 15 ))
				{
					myerror_log("we have a zip that is outside the bounds of North america");
					MailIt::sendErrorEmailAdam("we have a zip that is outside the bounds of the US", "zip = $zip");
					return false;
				}
				$zip_resource->lat = $google_data['lat'];
				$zip_resource->lng = $google_data['lng'];
				$zip_resource->modified = time();
				$zip_resource->save();
			}
			$zip_record = $zip_resource->getDataFieldsReally();
			return $zip_record;
		} else {
			myerror_log("ERROR!  no zip entry for zip: ".$zip);
			myerror_log("lets see if we can get a result for it and add it to the table");
			return false;
		}
	}
	
/*	function update($resource)
	{
		die("NOT ALLOWED");
	}
	
	function insert($resource)
	{
		die("NOT ALLOWED");
	}
*/	
}
?>