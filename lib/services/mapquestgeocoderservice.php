<?php
class MapquestGeoCoderService
{
	static function getGeoCodeResultsMapquest($address)
	{
		// need to get good key for mapquest. right now this is failing
		//return false;
		$ch = curl_init();
		$url = "http://www.mapquestapi.com/geocoding/v1/address?key=Fmjtd%7Cluu22hutn5%2C70%3Do5-h0asq&location=".$address; 
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER,0); //Change this to a 1 to return headers
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		 
		$geodata = curl_exec($ch);
		curl_close($ch);
		if ($geo_array = json_decode($geodata,true))	
			return $geo_array;
		myerror_log("GEOCODE ERROR! response from mapquest: ".$geodata);
		return false;
	}
}
?>