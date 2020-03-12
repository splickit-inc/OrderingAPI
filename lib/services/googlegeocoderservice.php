<?php
class GoogleGeoCoderService
{
	var $key;
	
	function __construct()
	{
		$this->key = getProperty('google_geocode_key');
	}
	
	static function staticGeoCodeAddress($address)
	{
		$ggcs = new GoogleGeoCoderService();
		return $ggcs->geoCodeAddress($address);
	}
	
	function geoCodeAddress($address) {
		$key = $this->key;
		$url = getProperty('google_geocode_url')."&key=$key&address=".urlencode($address);
		
		$geo_return = GoogleGeoCoderCurl::curlIt($url, $data);
		if ($raw_return = $geo_return['raw_result']) {
			$response = json_decode($raw_return, true);
			if(json_last_error() == JSON_ERROR_NONE) {
				if($response) {
					myerror_log("Google v3 geocoding - got response $response\n back for $address");
					switch($response['status']) {
						case 'OK':
							$results = $response['results'];
							$return_data['lat'] = $results[0]['geometry']['location']['lat'];
							$return_data['lng'] = $results[0]['geometry']['location']['lng'];
							return $return_data;
						case 'ZERO_RESULTS':
							myerror_log("Google v3 geocoding - got zero results for $address .  Got error_message of ".$response['error_message']);
							break;
						case 'OVER_QUERY_LIMIT':
							myerror_log("Google v3 geocoding - over rate limit!  Got error_message of ".$response['error_message']);
							break;
						case 'REQUEST_DENIED':
							myerror_log("Google v3 geocoding - request denied!  Got error_message of ".$response['error_message']);
							break;
						case 'INVALID_REQUEST':
							myerror_log("Google v3 geocoding - invalid request! Got error_message of ".$response['error_message']);
							break;
						case 'UNKNOWN_ERROR';
						default;
							myerror_log("Google v3 geocoding - unknown error! Got error_message of ".$response['error_message']);
							break;
					}
					//MailIt::sendErrorEmail("Google Geo Coding error","error: ".$response['error_message']);
				}	
			} else {
				MailIt::sendErrorEmail("Google Geo Coding error","Return was not good JSON: ".$raw_return);
			}			
		}
		
		return null;
	}
		
	static function getShortestDrivingDistance($lat1,$lng1,$lat2,$lng2)
	{
		if ($lat1 == $lat2 && $lng1 == $lng2) {
			myerror_log("starting and ending location are the SAME!  distance is 0");
			return 0.00;
		}
		$url = "https://maps.googleapis.com/maps/api/directions/json?origin=$lat1,$lng1&destination=$lat2,$lng2&units=imperial&alternatives=true&sensor=false&key=AIzaSyDUNIKNIRricPVXVPSpbdQXmgk2l4fF9XM";
		$geo_return = GoogleGeoCoderCurl::curlIt($url, $data);
		if ($raw_return = $geo_return['raw_result']) {
			$result = preg_replace('%Map data.*Google%', 'Map data copyright Google 2013', $raw_return);
			$a = json_decode($result, true);
			$shortest_driving_distance = 888888888;
			foreach ($a['routes'] as $route)
			{
				if (intval($route['legs'][0]['distance']['value'])) {
					$route_distance = intval($route['legs'][0]['distance']['value']);
					myerror_logging(3,"distance for this route is: ".$route_distance." meters");
					if ($route_distance < $shortest_driving_distance) {
						$shortest_driving_distance = $route_distance;	
					}
				} else { 
					myerror_log("ERROR! no return distance value for this route");
				}					
			}
			myerror_logging(3,"for origin=$lat1,$lng1&destination=$lat2,$lng2, the shortest driving distance from google in meters is: ".$shortest_driving_distance);
			$miles_google_api = $shortest_driving_distance/1609.344;
			$miles_google_api = number_format($miles_google_api, 2,'.','');
			myerror_logging(3,"for $lat1,$lng1,$lat2,$lng2 we got the resopnse from google.  driving distance in miles is: ".$miles_google_api);
			return $miles_google_api;							
		}
	}	
}
?>