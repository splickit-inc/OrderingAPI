<?php
class MockCalls
{
	static function getGooglePushAuthToken()
	{
		return "RT376GD94UH84H874R928FHYUWG";
	}
	
	static function createShorePointsAccount($phone_number)
	{
		if ($phone_number == '14567')
			return '{"status": "failed","code": 400,"message": "The number provided was not a valid phone number."}';
		else
		{
			$time_stamp = time();
			return '{"status": "success","code": 200,"response": "'.$time_stamp.'"}';
		}
	}
	
	static function getShorePointsAccount($number)
	{
		if ($number == '123412341234') 
			return '{"status": "failed","code": 404,"message": "Account was Not Found"}';
		else if ($number == '999999999') {
			return '{"status": "failed","code": 404,"message": "Account was Not Found"}';
		} else
			return '{"status": "success","code": 200,"response": {"lpc_id": "348070","phone_number": "123-456-7890","card_number": "002000002150","points": "1192","registration_code": "1335","store_number": "HD","creation_date":"2013-03-2210:13:05.067","history": [{"transaction_date": "2013-03-25 09:37:00","balance": "44","store_number": "HD","points_added": "44","transaction_type": "Purchase"}]}}';
	}
	
}
?>