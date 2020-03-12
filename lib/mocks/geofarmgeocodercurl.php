<?php
class GeoFarmGeoCoderCurl extends SplickitCurl
{
	static function curlIt($url,$data)
	{
		if ($_SERVER['NO_MOCKS']) {
			return GeoFarmGeoCoderCurl::curlItNoMock($url, $data);
		}
		$service_name = 'GeoFarmGeoCoder';
		if (substr_count($url,"530+W+Main+St") > 0) {
			$return['raw_result'] = '{"geocoding_results": {"LEGAL_COPYRIGHT": {"copyright_notice": "Copyright (c) 2016 Geocode.Farm - All Rights Reserved.","copyright_logo": "https:\/\/www.geocode.farm\/images\/logo.png","terms_of_service": "https:\/\/www.geocode.farm\/policies\/terms-of-service\/","privacy_policy": "https:\/\/www.geocode.farm\/policies\/privacy-policy\/"},"STATUS": {"access": "FREE_USER, ACCESS_GRANTED","status": "SUCCESS","address_provided": "530 W Main St Anoka MN 55303","result_count": 1},"ACCOUNT": {"ip_address": "74.66.67.32","distribution_license": "NONE, UNLICENSED","usage_limit": "250","used_today": "1","used_total": "1","first_used": "19 Dec 2016"},"RESULTS": [{"result_number": 1,"formatted_address": "530 W Main St, Anoka, MN 55303, USA","accuracy": "EXACT_MATCH","ADDRESS": {"street_number": "530","street_name": "West Main Street","locality": "Anoka","admin_2": "Anoka County","admin_1": "Minnesota","postal_code": "55303","country": "United States"},"LOCATION_DETAILS": {"elevation": "UNAVAILABLE","timezone_long": "UNAVAILABLE","timezone_short": "America\/Menominee"},"COORDINATES": {"latitude": "45.204389","longitude": "-93.400146"},"BOUNDARIES": {"northeast_latitude": "45.2039774062194","northeast_longitude": "-93.4003215302709","southwest_latitude": "45.2026272197740","southwest_longitude": "-93.4016762302153"}}],"STATISTICS": {"https_ssl": "ENABLED, SECURE"}}}';
			$return['http_code'] = 200; 
		} else if (substr_count($url,"1505+Arapaho+Ave") > 0) {
			$return['raw_result'] = '{"geocoding_results": {"LEGAL_COPYRIGHT": {"copyright_notice": "Copyright (c) 2016 Geocode.Farm - All Rights Reserved.","copyright_logo": "https:\/\/www.geocode.farm\/images\/logo.png","terms_of_service": "https:\/\/www.geocode.farm\/policies\/terms-of-service\/","privacy_policy": "https:\/\/www.geocode.farm\/policies\/privacy-policy\/"},"STATUS": {"access": "FREE_USER, ACCESS_GRANTED","status": "SUCCESS","address_provided": "530 W Main St Anoka MN 55303","result_count": 1},"ACCOUNT": {"ip_address": "74.66.67.32","distribution_license": "NONE, UNLICENSED","usage_limit": "250","used_today": "1","used_total": "1","first_used": "19 Dec 2016"},"RESULTS": [{"result_number": 1,"formatted_address": "530 W Main St, Anoka, MN 55303, USA","accuracy": "EXACT_MATCH","ADDRESS": {"street_number": "530","street_name": "West Main Street","locality": "Anoka","admin_2": "Anoka County","admin_1": "Minnesota","postal_code": "55303","country": "United States"},"LOCATION_DETAILS": {"elevation": "UNAVAILABLE","timezone_long": "UNAVAILABLE","timezone_short": "America\/Menominee"},"COORDINATES": {"latitude": "45.204389","longitude": "-93.400146"},"BOUNDARIES": {"northeast_latitude": "45.2039774062194","northeast_longitude": "-93.4003215302709","southwest_latitude": "45.2026272197740","southwest_longitude": "-93.4016762302153"}}],"STATISTICS": {"https_ssl": "ENABLED, SECURE"}}}';
			$return['http_code'] = 200; 
		} else {
			return die('endpoint not built for mock object: '.$url);
		}
		return $return;
	}
	
	static function curlItNoMock($url,$data)
	{
		$service_name = 'GeoFarmGeoCoder';
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