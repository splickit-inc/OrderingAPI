<?php
class FishbowlCurl extends SplickitCurl
{

	static function curlIt($url,$data)
	{
		/*	$loyalty_return ='<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><AddPointsResponse xmlns="http://tempuri.org/"><AddPointsResult>{"xml":{"Reward":"REWARD:0.00","status":"success","Approved":"29916540","Clerk":"27","Check":"9780653","PointsAdded":"10.50","TotalPoints":["369.50","369.50"],"TotalSaved":"0.00","TotalVisits":"37","GiftCardBalance":"0.00","RewardCashBalance":"0.00","CustomReceiptMessages":"Register your Rewards Number and receive\na R5.00 instant reward\n"}}</AddPointsResult></AddPointsResponse></soap:Body></soap:Envelope>'; */
		
		$service_name = 'Fishbowl';
		if ($data['Birthdate'] == '01/01') {
			$response['http_code'] = 500;
		} else {
			$response['http_code'] = 302;
		}
		return $response;
	}
}
?>