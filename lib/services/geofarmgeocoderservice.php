<?php
class GeoFarmGeoCoderService
{
	var $key;
	
	function __construct()
	{
		$this->key = getProperty('geofarm_geocode_key');
	}
	
	static function staticGeoCodeAddress($address)
	{
		$ggcs = new GeoFarmGeoCoderService();
		return $ggcs->geoCodeAddress($address);
	}
	
	function geoCodeAddress($address) {
		$key = $this->key;
		// key not needed anymore for V3 it seems as long as we keep it under 250 requests a day. since this is the backup that shouldn't be a problem (till it is i guess)
		//$url = getProperty('geocode_farm_url')."$key/$address/";
        $url = getProperty('geocode_farm_url').'?addr='.$address;

		$geo_return = GeoFarmGeoCoderCurl::curlIt($url, $data);
		if ($raw_return = $geo_return['raw_result']) {
			$response = json_decode($raw_return, true);
			if(json_last_error() == JSON_ERROR_NONE) {
				if($response) {
					myerror_log("Geofarm geocoding - got response $response\n back for $address");
					switch($response['geocoding_results']['STATUS']['status']) {
						case 'SUCCESS':
							$results = $response['geocoding_results']['RESULTS'];
							$return_data['lat'] = number_format($results[0]['COORDINATES']['latitude'],6);
							$return_data['lng'] = number_format($results[0]['COORDINATES']['longitude'],6);
							return $return_data;
						case 'FAILED, ACCESS_DENIED':
							myerror_log("GeoFarm Error. Access has been denied");
							$response['error_message'] = "Access Denied";
							break;
						case 'FAILED, NO_RESULTS':
							myerror_log("Geo Farm Coding Error. There were no results for this address");
							$response['error_message'] = "There were no results";
							break;
						default;
							myerror_log("Geo farm unknown error: ".$response['geocoding_results']['STATUS']['status']);
							$response['error_message'] = $response['geocoding_results']['STATUS']['status'];
					}
					MailIt::sendErrorEmail("GeoFarm Coding error","error: ".$response['error_message']);
				}	
			} 			
		}
		
		return null;
	}

}
?>