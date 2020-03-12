<?php
class ErrorsAdapter extends MySQLAdapter
{
	function ErrorsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Errors',
			'%([0-9]{1,10})%',
			'%d',
			array('error_id'),
			array('error_id','message','info','custom01','custom02','created'),
			array('created')
			);
        $this->log_level = 0;
	}
	
	static function clearOldErrorLog($days_back)
	{
		$error_adapter = new ErrorsAdapter($mimetypes);
		$sql = "DELETE FROM `Errors` WHERE created < DATE_SUB(now(), INTERVAL $days_back DAY)";
		$error_adapter->_query($sql);
		if ($error_adapter->error_no < 1) {
			myerror_log("error log has been cleared out for entries older than $days_back days");
			return true;
		} else {
			MailIt::sendErrorEmail("THere was a problem clearing out the old error logs", "myerror: ".$error_adapter->getLastErrorText());
			return false;
		}
		
	}
	
	static function staticCheckForNewErrors($minutes_back)
	{
		$ea = new ErrorsAdapter($mimetypes);
		$ea->checkForNewErrors($minutes_back);
		// since this is used by the activity it needs to return a true or false.
		return true;
	}
	
	function checkForNewLoggedErrors($string_to_search_for,$number_of_occurances_to_trigger_alert,$number_of_minutes_back,$group_by_string_to_search_for = false)
	{
		$tz = date_default_timezone_get();
		myerror_log("the default timezone in checkForNewErrors is: ".$tz);
			
		$one_minute_ago = time()-($number_of_minutes_back*60);
		$time_string = date('Y-m-d H:i:s',$one_minute_ago);
		$errors_options[TONIC_FIND_BY_METADATA]['info'] = "LOG ERROR";
		$errors_options[TONIC_FIND_BY_METADATA]['custom01'] = array("LIKE"=>'%'.$string_to_search_for.'%');
		$errors_options[TONIC_FIND_BY_METADATA]['created'] = array(">"=>$time_string);	
		if ($group_by_string_to_search_for) {
			$errors_options[TONIC_GROUP_BY] = 'custom01';
		}	
		myerror_log("time now is: ".date('Y-m-d H:i:s'));
		$error_types = array();
		
		if ($error_results = Resource::findAll($this,null,$errors_options)) {
			return count($error_results);
		} else {
			return 0;
		}
		
	}
	
	/**
	 * @desc will check for new errors to get emailed out if `info` = 'EMAIL ERROR' 
	 * @param $minutes_back
	 */
	function checkForNewErrors($minutes_back)
	{
		$tz = date_default_timezone_get();
		myerror_log("the default timezone in checkForNewErrors is: ".$tz);
			
		$one_minute_ago = time()-($minutes_back*60);
		$time_string = date('Y-m-d H:i:s',$one_minute_ago);
		$number_of_errors = 0;
		$errors_options[TONIC_FIND_BY_METADATA]['info'] = "EMAIL ERROR";
		$errors_options[TONIC_FIND_BY_METADATA]['created'] = array(">"=>$time_string);
		$environment = getProperty('server');
		
		myerror_log("time now is: ".date('Y-m-d H:i:s'));
		$error_types = array();
		$vio_connection_problem = false;
		if ($error_results = Resource::findAll($this,'',$errors_options))
		{
			// create error report
			
			$error_types = array();
			$number_of_errors = sizeof($error_results);
			myerror_log("there were $number_of_errors thrown");
			$subject = "$number_of_errors ERRORS IN $environment!";
			$body = "<html><body><table><tr><td>ID</td><td>M1</td><td>M2</td><td>M3</td><td>M4</td><td>created</td><td>readable</td></tr>";
			foreach ($error_results as $error_row_resource)
			{
				if (substr_count($error_row_resource->custom01,"CONNECTION ERROR TO VIO") > 0) {
					$vio_connection_problem = true;
				}
				$id = $error_row_resource->error_id;
				$m1 = htmlspecialchars($error_row_resource->message);
				$m2 = htmlspecialchars($error_row_resource->info);
				$m3 = htmlspecialchars($error_row_resource->custom01);
				$m4 = htmlspecialchars($error_row_resource->custom02);
				$created = $error_row_resource->created;
				$readable = date('Y-m-d H:i:s',$created);
				$body .= "<tr><td>$id</td><td>$m1</td><td>$m2</td><td>$m3</td><td>$m4</td><td>$created</td><td>$readable</td></tr>";
				$error_name = trim($m3);
				$error_types[$error_name] = 1;
			}
			$body .= "</table></body></html>";
			
			myerror_log($body);
		} else {
			$subject = "there were no operation errors in $environment this cycle";
			myerror_log($subject);
		}
		if ($vio_connection_problem) {
			SmsSender2::send_sms('3037284847',"CONNECTION ERROR TO VIO!!!!!");
		}
		
		if ($number_of_errors > 0)
		{
            if (sizeof($error_types) == 1 && isset($error_types['LONG QUERY ERROR']) && $number_of_errors < 100) {
                myerror_log("skip sending of alerts, its a small number of long query errors"); // do nothing
            } else if (sizeof($error_types) == 1 && isset($error_types['BadSkinIdError']) && $number_of_errors < 10) {
                myerror_log("skip sending of alerts, its a small number of bad skin id errors"); // do nothing
            } else {
				if (MailIt::sendErrorEmail($subject, $body))
				{
					if ($_SERVER['MOUNTAIN_TIME_HOUR'] > 5 && $_SERVER['MOUNTAIN_TIME_HOUR'] < 23 && isProd())  {
						if ($number_of_errors > 5) {
							SmsSender2::sendEngineeringAlert("THERE ARE $number_of_errors SERVER ERRORS in $environment. Check email for info");
						}
					}
				} else {
					if ($_SERVER['MOUNTAIN_TIME_HOUR'] > 5 && $_SERVER['MOUNTAIN_TIME_HOUR'] < 23 && isProd()) {
						SmsSender2::sendEngineeringAlert("THERE ARE SERVER ERRORS BUT CANT SEND EMAIL.  CHECK DataBase NOW!");
					}
				}
			}
		}
		return $number_of_errors;
	}
	
	static function createNewErrorRecord($info,$message2,$message3,$message4)
	{

		$error_adapter = new ErrorsAdapter($mimetypes);
		$error_data['info'] = $info;
		$error_data['message'] = getRawStamp()." $message2";
		$error_data['custom01'] = $message3;
		$error_data['custom02'] = $message4;
		$error_resource = Resource::factory($error_adapter,$error_data);
		$dftz = $_SERVER['GLOBAL_PROPERTIES']['default_server_timezone'];
		$tz = date_default_timezone_get();
		date_default_timezone_set($dftz);
		$error_resource->save();
		date_default_timezone_set($tz);
		return $error_resource;
	}

    function auditTrail($sql)
    {
        return true;
    }


}
?>