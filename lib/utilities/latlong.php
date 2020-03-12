<?php
class LatLong {
	
	static function generateLatLong($address,$with_sleep = true) 
	{
		//if (isTest() || isLaptop())
		//	$key = "AIzaSyA_sYlLdSr3dJAsyms6GtNRnzepdWVmtVY";
		
		if (substr_count($address, "#") > 0) {
			$address = str_replace("#", "", $address);
		}

		if (isTest()) {
			sleep(2);		
		}
		
		if ($with_sleep) {
			if ($_SERVER['NOSLEEP']) {
				;
			} else {
				sleep(1);
			}
		}

		if($latlng = LatLong::getGoogleLatLng($address)) {
			return $latlng;				
		} else if($latlng = LatLong::getGeoFarmLatLng($address)) {
			myerror_log("Falling back to GeoFarm for $address");
			MailIt::sendErrorEmailAdam("We had a fail with Google that passed with Geo coding",$address);
			return $latlng;
		} else {
			myerror_log("Google and GeoFarm both failed to geocode $address");
			//LatLong::sendGeocodeErrorNotifications($address);
			return false;
		}
	}
	
	/**
	 * @codeCoverageIgnore
 	 */
	function sendGeocodeErrorNotifications($address) {
		$message_text = "Hello ".$_SERVER['AUTHENTICATED_USER']['first_name'].",
		
I'm sorry for the interruption, my name is Adam Rosenthal and I'm the head of engineering at splick-it, the company that handles the mobile/online ordering application for ".$_SERVER['SKIN']['skin_name'].".  I saw you were having trouble saving an address in the app, so I'm wondering if you could help me debug the problem by letting me know if there is anything special about this address?  Like is in a Mall or something else that might affect the google location services ability to locate you?
		
Thanks!";
		MailIt::sendErrorEmail("Error in geo coding!", "could not genereate lat long for address! addr: ".$address."    user ".$_SERVER['AUTHENTICATED_USER']['email']."  first name: ".$_SERVER['AUTHENTICATED_USER']['first_name']);
		MailIt::sendErrorEmailAdam("geoletter text", $message_text);
		return false;
	}
	
	function getGeoFarmLatLng($address) {
		return GeoFarmGeoCoderService::staticGeoCodeAddress($address);
	}
	
	function getGoogleLatLng($address) {
		$google_geocoder_service = new GoogleGeoCoderService();
		return $google_geocoder_service->geoCodeAddress($address);
	}
}
?>