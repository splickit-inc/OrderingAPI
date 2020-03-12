<?php
class MapquestGeoCoderService
{
	static function getGeoCodeResultsMapquest($address)
	{
		if ($address == "375+Janik+Drive,Kent,OH+44243")
		{
			$lat_lng['lat'] = 55.00;
			$lat_lng['lng'] = -100.00;
			$geo_array['results'][0]['locations'][0]['latLng'] = $lat_lng;
			return $geo_array;
		}
		else
		{
			myerror_log("need response for: ".$address);
			return false;
		}
	}
}
?>