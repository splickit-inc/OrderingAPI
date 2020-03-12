<?php
class MandrillEmailService
{
	static function sendEmail($data)
	{
		$to_email = $data['message']['to'][0]['email'];
		myerror_log("Starting MOCK OBJECT MandrillEmailService->sendEmail");
		if (getProperty("test_mandril_fail") == 'true')
			return false;
		$result = "[{\"email\":\"mock.$to_email\",\"status\":\"sent\"}]";
		return $result;
	}
}
?>