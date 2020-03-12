<?php
class GoogleGeoCoderCurl extends SplickitCurl
{
	static function curlIt($url,$data)
	{
		if ($_SERVER['NO_MOCKS']) {
			return GoogleGeoCoderCurl::curlItNoMock($url, $data);
		}
		$service_name = 'GoogleGeoCoder';
		if (substr_count($url,"directions") > 0) {
			// getting driving directions
			if (substr_count($url,"destination=40.014594,-105.275990") > 0) {
				$return['raw_result'] = '{ "routes":[{ "bounds":{"northeast":{ "lat":40.0145922, "lng":-105.274479},"southwest":{ "lat":40.01458969999999, "lng":-105.27599} }, "copyrights":"Map data ©2014 Google", "legs":[{ "distance":{"text":"423 ft","value":129 }, "duration":{"text":"1 min","value":12 }, "end_address":"1400 Arapahoe Avenue, Boulder, CO 80302, USA", "end_location":{"lat":40.01458969999999,"lng":-105.27599 }, "start_address":"1520 Arapahoe Avenue, Boulder, CO 80302, USA", "start_location":{"lat":40.0145922,"lng":-105.274479 }, "steps":[{ "distance":{"text":"423 ft","value":129 }, "duration":{"text":"1 min","value":12 }, "end_location":{"lat":40.01458969999999,"lng":-105.27599 }, "html_instructions":"Head \u003cb\u003ewest\u003c/b\u003e on \u003cb\u003eArapahoe Ave\u003c/b\u003e toward \u003cb\u003e15th St\u003c/b\u003e", "polyline":{"points":"ejfsFnlpaS?`@?hB?dB?z@" }, "start_location":{"lat":40.0145922,"lng":-105.274479 }, "travel_mode":"DRIVING"} ], "via_waypoint":[]} ], "overview_polyline":{"points":"ejfsFnlpaS?lH" }, "summary":"Arapahoe Ave", "warnings":[], "waypoint_order":[]} ], "status":"OK"}';		
				$return['http_code'] = 200;
			} else if (substr_count($url,"destination=39.867014,-104.934993") > 0) {
				$return['raw_result'] =  '{ "routes":[{ "bounds":{"northeast":{ "lat":40.0145922, "lng":-105.274479},"southwest":{ "lat":40.01458969999999, "lng":-105.27599} }, "copyrights":"Map data ©2014 Google", "legs":[{ "distance":{"text":"4230 ft","value":37633 }, "duration":{"text":"1 min","value":12 }, "end_address":"1400 Arapahoe Avenue, Boulder, CO 80302, USA", "end_location":{"lat":40.01458969999999,"lng":-105.27599 }, "start_address":"1520 Arapahoe Avenue, Boulder, CO 80302, USA", "start_location":{"lat":40.0145922,"lng":-105.274479 }, "steps":[{ "distance":{"text":"423 ft","value":129 }, "duration":{"text":"1 min","value":12 }, "end_location":{"lat":40.01458969999999,"lng":-105.27599 }, "html_instructions":"Head \u003cb\u003ewest\u003c/b\u003e on \u003cb\u003eArapahoe Ave\u003c/b\u003e toward \u003cb\u003e15th St\u003c/b\u003e", "polyline":{"points":"ejfsFnlpaS?`@?hB?dB?z@" }, "start_location":{"lat":40.0145922,"lng":-105.274479 }, "travel_mode":"DRIVING"} ], "via_waypoint":[]} ], "overview_polyline":{"points":"ejfsFnlpaS?lH" }, "summary":"Arapahoe Ave", "warnings":[], "waypoint_order":[]} ], "status":"OK"}';
				$return['http_code'] = 200;
			} else if (substr_count($url,"destination=40.400445,-104.695179") > 0) {
				$return['raw_result'] =  '{ "routes":[{ "bounds":{"northeast":{ "lat":40.0145922, "lng":-105.274479},"southwest":{ "lat":40.01458969999999, "lng":-105.27599} }, "copyrights":"Map data ©2014 Google", "legs":[{ "distance":{"text":"42300 ft","value":79281 }, "duration":{"text":"1 min","value":12 }, "end_address":"1400 Arapahoe Avenue, Boulder, CO 80302, USA", "end_location":{"lat":40.01458969999999,"lng":-105.27599 }, "start_address":"1520 Arapahoe Avenue, Boulder, CO 80302, USA", "start_location":{"lat":40.0145922,"lng":-105.274479 }, "steps":[{ "distance":{"text":"423 ft","value":129 }, "duration":{"text":"1 min","value":12 }, "end_location":{"lat":40.01458969999999,"lng":-105.27599 }, "html_instructions":"Head \u003cb\u003ewest\u003c/b\u003e on \u003cb\u003eArapahoe Ave\u003c/b\u003e toward \u003cb\u003e15th St\u003c/b\u003e", "polyline":{"points":"ejfsFnlpaS?`@?hB?dB?z@" }, "start_location":{"lat":40.0145922,"lng":-105.274479 }, "travel_mode":"DRIVING"} ], "via_waypoint":[]} ], "overview_polyline":{"points":"ejfsFnlpaS?lH" }, "summary":"Arapahoe Ave", "warnings":[], "waypoint_order":[]} ], "status":"OK"}';
				$return['http_code'] = 200;
			} else if (substr_count($url,"destination=47.673989,-116.786191") > 0) {
				$return['raw_result'] =  '{ "routes":[{ "bounds":{"northeast":{ "lat":40.0145922, "lng":-105.274479},"southwest":{ "lat":40.01458969999999, "lng":-105.27599} }, "copyrights":"Map data ©2014 Google", "legs":[{ "distance":{"text":"423000 ft","value":1686719 }, "duration":{"text":"1 min","value":12 }, "end_address":"1400 Arapahoe Avenue, Boulder, CO 80302, USA", "end_location":{"lat":40.01458969999999,"lng":-105.27599 }, "start_address":"1520 Arapahoe Avenue, Boulder, CO 80302, USA", "start_location":{"lat":40.0145922,"lng":-105.274479 }, "steps":[{ "distance":{"text":"423 ft","value":129 }, "duration":{"text":"1 min","value":12 }, "end_location":{"lat":40.01458969999999,"lng":-105.27599 }, "html_instructions":"Head \u003cb\u003ewest\u003c/b\u003e on \u003cb\u003eArapahoe Ave\u003c/b\u003e toward \u003cb\u003e15th St\u003c/b\u003e", "polyline":{"points":"ejfsFnlpaS?`@?hB?dB?z@" }, "start_location":{"lat":40.0145922,"lng":-105.274479 }, "travel_mode":"DRIVING"} ], "via_waypoint":[]} ], "overview_polyline":{"points":"ejfsFnlpaS?lH" }, "summary":"Arapahoe Ave", "warnings":[], "waypoint_order":[]} ], "status":"OK"}';
				$return['http_code'] = 200;
			} else if (substr_count($url,"destination=40.796202,-73.936635") > 0) {
				$return['raw_result'] =  '{ "routes":[{ "bounds":{"northeast":{ "lat":40.0145922, "lng":-105.274479},"southwest":{ "lat":40.01458969999999, "lng":-105.27599} }, "copyrights":"Map data ©2014 Google", "legs":[{ "distance":{"text":"4230000 ft","value":2894590 }, "duration":{"text":"1 min","value":12 }, "end_address":"1400 Arapahoe Avenue, Boulder, CO 80302, USA", "end_location":{"lat":40.01458969999999,"lng":-105.27599 }, "start_address":"1520 Arapahoe Avenue, Boulder, CO 80302, USA", "start_location":{"lat":40.0145922,"lng":-105.274479 }, "steps":[{ "distance":{"text":"423 ft","value":129 }, "duration":{"text":"1 min","value":12 }, "end_location":{"lat":40.01458969999999,"lng":-105.27599 }, "html_instructions":"Head \u003cb\u003ewest\u003c/b\u003e on \u003cb\u003eArapahoe Ave\u003c/b\u003e toward \u003cb\u003e15th St\u003c/b\u003e", "polyline":{"points":"ejfsFnlpaS?`@?hB?dB?z@" }, "start_location":{"lat":40.0145922,"lng":-105.274479 }, "travel_mode":"DRIVING"} ], "via_waypoint":[]} ], "overview_polyline":{"points":"ejfsFnlpaS?lH" }, "summary":"Arapahoe Ave", "warnings":[], "waypoint_order":[]} ], "status":"OK"}';
				$return['http_code'] = 200;
			}
			return $return;
			
		} else {
			if (substr_count($url,"Janik%2BDrive%2CKent%2COH%2B44243") > 0) {
				$return['raw_result'] = '{ "results":[{ "address_components":[{ "long_name":"375", "short_name":"375", "types":[ "street_number" ]},{ "long_name":"Kent State University", "short_name":"Kent State University", "types":[ "establishment" ]},{ "long_name":"Janik Drive", "short_name":"Janik Dr", "types":[ "route" ]},{ "long_name":"Kent", "short_name":"Kent", "types":[ "locality", "political" ]},{ "long_name":"Portage County", "short_name":"Portage County", "types":[ "administrative_area_level_2", "political" ]},{ "long_name":"Ohio", "short_name":"OH", "types":[ "administrative_area_level_1", "political" ]},{ "long_name":"United States", "short_name":"US", "types":[ "country", "political" ]},{ "long_name":"44243", "short_name":"44243", "types":[ "postal_code" ]} ], "formatted_address":"375 Janik Drive, Kent State University, Kent, OH 44243, USA", "geometry":{"bounds":{ "northeast":{"lat":41.1482889,"lng":-81.3479526 }, "southwest":{"lat":41.1482801,"lng":-81.34796679999999 }},"location":{ "lat":41.1482801, "lng":-81.3479526},"location_type":"RANGE_INTERPOLATED","viewport":{ "northeast":{"lat":41.14963348029149,"lng":-81.34661071970849 }, "southwest":{"lat":41.1469355197085,"lng":-81.3493086802915 }} }, "partial_match":true, "types":[ "street_address" ]} ], "status":"OK"}';
				$return['http_code'] = 200;
			} else if (substr_count($url,"Amphitheatre%2CParkway") > 0) {
				$return['raw_result'] =  '{"results":[{"address_components":[{"long_name":"1600","short_name":"1600","types":[ "street_number" ]},{"long_name":"Amphitheatre Parkway","short_name":"Amphitheatre Pkwy","types":[ "route" ]},{"long_name":"Mountain View","short_name":"Mountain View","types":[ "locality", "political" ]},{"long_name":"Santa Clara County","short_name":"Santa Clara County","types":[ "administrative_area_level_2", "political" ]},{"long_name":"California","short_name":"CA","types":[ "administrative_area_level_1", "political" ]},{"long_name":"United States","short_name":"US","types":[ "country", "political" ]},{"long_name":"94043","short_name":"94043","types":[ "postal_code" ]}],"formatted_address":"1600 Amphitheatre Parkway, Mountain View, CA 94043, USA","geometry":{"location":{"lat":37.4219951,"lng":-122.0840046},"location_type":"ROOFTOP","viewport":{"northeast":{"lat":37.4233440802915,"lng":-122.0826556197085},"southwest":{"lat":37.4206461197085,"lng":-122.0853535802915}}},"types":[ "street_address" ]}],"status":"OK"}';
				$return['http_code'] = 200;
			} else {
				$return['raw_result'] = '{"results" : [],"status" :"ZERO_RESULTS"}';
				$return['http_code'] = 200;
			}
			return $return;
		}
	}
	
	static function curlItNoMock($url,$data)
	{
		$service_name = 'GoogleGeoCoder';
		if ($ch = curl_init($url))
		{		
			curl_setopt($ch, CURLOPT_URL, $url);
			if ($data)
			{
				$json_data = json_encode($data);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);                                                                  
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
				    'Content-Type: application/json',                                                                                
				    'Content-Length: ' . strlen($json_data))                                                                       
				);
			}
			curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"]);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			
			$response = parent::curlIt($ch);
			curl_close($curl);
		} else {
			$response['error'] = "FAILURE. Could not connect to ".$service_name;
			myerror_log("ERROR!  could not connect to ".$service_name);
		}
		return $response;
	}	
}
?>