<?php
class FpnLoyaltyCurl extends SplickitCurl
{
	static function curlIt($url,$soap_xml)
	{
		if ($_SERVER['NO_MOCKS']) {
			return FpnLoyaltyCurl::curlItNoMock($url, $soap_xml);
		}
		$loyalty_return ='<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><AddPointsResponse xmlns="http://tempuri.org/"><AddPointsResult>{"xml":{"Reward":"REWARD:0.00","status":"success","Approved":"29916540","Clerk":"27","Check":"9780653","PointsAdded":"10.50","TotalPoints":["369.50","369.50"],"TotalSaved":"0.00","TotalVisits":"37","GiftCardBalance":"0.00","RewardCashBalance":"0.00","CustomReceiptMessages":"Register your Rewards Number and receive\na R5.00 instant reward\n"}}</AddPointsResult></AddPointsResponse></soap:Body></soap:Envelope>';
		$response['raw_result'] = $loyalty_return;
		return $response;
	}
	
	static function curlItNoMock($url,$soap_xml)
	{
		/*	$loyalty_return ='<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><AddPointsResponse xmlns="http://tempuri.org/"><AddPointsResult>{"xml":{"Reward":"REWARD:0.00","status":"success","Approved":"29916540","Clerk":"27","Check":"9780653","PointsAdded":"10.50","TotalPoints":["369.50","369.50"],"TotalSaved":"0.00","TotalVisits":"37","GiftCardBalance":"0.00","RewardCashBalance":"0.00","CustomReceiptMessages":"Register your Rewards Number and receive\na R5.00 instant reward\n"}}</AddPointsResult></AddPointsResponse></soap:Body></soap:Envelope>'; */
		
		$service_name = 'Fpn Loyalty';
		
		if ($ch = curl_init($url))
		{		
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_VERBOSE,0);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $soap_xml);                                                                  
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
			    'Content-Type: application/soap+xml',                                                                                
			    'Content-Length: ' . strlen($soap_xml))                                                                       
			);
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