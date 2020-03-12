<?php

require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'holidayhouradapter.php';

class HourAdapter extends MySQLAdapter
{
	private $this_weeks_hours;
	private $this_weeks_hours_type;
	private $closed_message;
	private $merchant_status_message;
	private $holiday;
	private $day_array = array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");
	
	private $current_time;
	
	private $local_order_and_pickup_time_array = array();
	function HourAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Hour',
			'%([0-9]{1,15})%',
			'%d',
			array('hour_id')
			);
		$this->log_level = $_SERVER['log_level'];
		$this->current_time = time();
	}
	
	function setCurrentTime($time_stamp)
	{
		$this->current_time = $time_stamp;
	}
	
	// just a test function
	function getNowAsInt()
	{
		$now_as_int = $this->current_time;
		return $now_as_int;
	}
	 
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
    
    function getMerchantHoursForWeek($starting_time_stamp,$merchant_id,$hour_type,$reload = false)
    {
    	if ($this->this_weeks_hours && $this->this_weeks_hours_type == $hour_type && !$reload)
    		return $this->this_weeks_hours;
    	return $this->loadMerchantHoursForWeek($starting_time_stamp, $merchant_id, $hour_type);
    }
    
    function getAllMerchantHourRecords($merchant_id)
    {
    	$hours = array();
		$hours['pickup'] = $this->getHours($merchant_id, 'R');
		$hours['delivery'] = $this->getHours($merchant_id, 'D');
		return $hours;
		
    }
    
    /**
     * 
     * @desc For pickup right now. gets an array of days of the week with human readable hours    "Monday"=>"08:00am-05:00pm"
     * 
     * @param $merchant_id
     */
    function getAllMerchantHoursHumanReadable($merchant_id)
    {
    	if ($hours = $this->getAllMerchantHourRecords($merchant_id)) {
	    	if ($pickuphours = $hours['pickup']) {
	    		$the_array = $this->getHumanReadableHoursForFullWeeksHourRecords($pickuphours);
				$sunday = array_shift($the_array);
				$the_array[] = $sunday;
				return $the_array;
	    	}
    	} else {
    		return false;
    	}
    }

    function newGetAllMerchantHoursHumanReadable($merchant_id)
    {
        if ($hours = $this->getAllMerchantHourRecords($merchant_id)) {

            if ($pickuphours = $hours['pickup']) {
                $the_array = $this->getHumanReadableHoursForFullWeeksHourRecords($pickuphours);
                $sunday = array_shift($the_array);
                $the_array[] = $sunday;
                $hours['pickup'] = $the_array;
            }

            if ($delivery_hours = $hours['delivery']) {
                $the_array = $this->getHumanReadableHoursForFullWeeksHourRecords($delivery_hours);
                $sunday = array_shift($the_array);
                $the_array[] = $sunday;
                $hours['delivery'] = $the_array;
            }
            return $hours;
        } else {
            return false;
        }
    }

    function getAllMerchantHoursHumanReadableV2($merchant_id)
	{
		if ($hours = $this->getAllMerchantHourRecords($merchant_id)) {
			$human_readables = array();
			foreach ($hours as $type=>$day_array){
				$base_array = $this->getHumanReadableHoursForFullWeeksHourRecords($day_array);
				foreach ($base_array as $index=>$row) {
					$day = (string)$index+1;
					$human_readables[$type]["$day"] = $row;
				}
			}
			return $human_readables;
		} else {
			return false;
		}
	}

	function getHumanReadableHoursForFullWeeksHourRecords($hour_records)
	{
		for($i=0;$i<7;$i++) {
			$open = $hour_records[$i]['open'];
			$close = $hour_records[$i]['close'];
			$second_close = $hour_records[$i]['second_close'];
			if ($second_close > "00:00:00"){
				$close = $second_close;
			} else if ($close < $open) {
				if ($i==6){
					$close = $hour_records[0]['close'];
				} else {
					$close = $hour_records[$i + 1]['close'];
				}
			}
			// now convert to ampm
			$open = $this->getAmPmStringFromTimeOfDayMilitary($open);
			$close = $this->getAmPmStringFromTimeOfDayMilitary($close);
			$day_index = $hour_records[$i]['day_of_week'];
			$day = $this->day_array[$day_index-1];
			if ($hour_records[$i]['day_open'] == 'Y') {
				$the_array[] = array($day=>$open."-".$close);
			} else {
				$the_array[] = array($day => "closed");
			}
		}
		return $the_array;
	}

    /**
     * 
     * @desc will take a military time and convert to an am/pm time   05:45  ->  5:45am    or     15:45  ->  3:45pm
     * 
     * @param $time  
     */
    function getAmPmStringFromTimeOfDayMilitary($time)
    {
    	$time_split = explode(":",$time);
    	if ($time_split[0] < 12)
    		$string = $time_split[0].":".$time_split[1]."am";
    	else if ($time_split[0] == 12)
    		$string = $time_split[0].":".$time_split[1]."pm";
    	else if ($time_split[0] > 12)
    	{
    		$new_hour = $time_split[0] - 12;
    		$string = $new_hour.":".$time_split[1]."pm";
    	}
    	if (substr($string, 0,1) == "0")
    		$string = substr($string, 1);
    	return $string;
    }

    function getHours($merchant_id,$hour_type)
    {
		$hours = $this->getRecords(array("merchant_id"=>$merchant_id,"hour_type"=>$hour_type));
		return $hours;
    }
    
    function getHolidaysThatFallOnTheseDays($merchant_id,$days)
    {
    	$holiday_array = array();
    	$holiday_hour_adapter = new HolidayHourAdapter($this->mimetypes);
		$holiday_hour_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
		$holiday_hour_options[TONIC_FIND_BY_METADATA]['the_date'] = array("IN"=>$days);
		if ($holiday_hours = $holiday_hour_adapter->select('',$holiday_hour_options))
		{
			myerror_logging(3, "we have a holiday during the next week");
			$holiday = true;
			foreach ($holiday_hours as $holiday_hour)
			{
				//resolve to date
				$the_date = date('Y-m-d',$holiday_hour['the_date']);
				myerror_logging(3, "THe holiday is on day: ".$the_date);
				$holiday_array[$the_date] = $holiday_hour;
			}
		} else {
			myerror_logging(3, "we DO NOT have a holiday during the next week");
		}    	
		return $holiday_array;
    }

    /**
     * 
     * @desc Will return an array for the next 7 days open,close,day_open,holiday
     * @param $days 
     * @param $hours (hour records from the db)
     * @param $holiday_array (array of which days are holidays in the next 7 with the hours for that day)
     */
    function getTheWeeksHoursFromDaysHoursAndHolidaysDuringThisWeek($days,$hours,$holiday_array)
    {
		foreach ($days as $day)
		{
			// get day of week
			$day_of_week = date('w',strtotime($day))+1;
			// loop through hours
			foreach ($hours as $hour)
			{
				if ($hour['day_of_week'] == $day_of_week)
				{
					$hour['date'] = $day;
					//check for holiday
					if (isset($holiday_array[$day]))
					{
						myerror_logging(3, "now setting the holiday in the date array on: ".$day);
						$holiday_day = $holiday_array[$day];
						$hour['open'] = $holiday_day['open'];
						$hour['close'] = $holiday_day['close'];
						$hour['day_open'] = $holiday_day['day_open'];
						$hour['holiday'] = 'Y';
					}	else {
						$hour['holiday'] = 'N';
					}
					$this_weeks_hours[] = $hour;
				}	
			}
		}
		return $this_weeks_hours;
    }
    
    function getDaysArrayFromStartingTimeStamp($starting_time_stamp)
    {
		//myerror_logging(3,"the starting time stamp is: ".$testing);
		$m = date("m",$starting_time_stamp);
		$d = date("d",$starting_time_stamp);
		$y = date("Y",$starting_time_stamp);
		
		// load days array for starting time stamp
		for ($inc = 0; $inc < 7; $inc++)
			$days[] = date('Y-m-d',mktime(0, 0, 0, $m , $d+$inc, $y));
    	return $days;
    }
    
    /**
     * 
     * @desc Will load up the merchant hours array for the next 7 days open,close,day_open,holiday
     * @param $days 
     * @param $hours (hour records from the db)
     * @param $holiday_array (array of which days are holidays in the next 7 with the hours for that day)
     * @return array or false
     */
    
	function loadMerchantHoursForWeek($starting_time_stamp,$merchant_id,$hour_type)
	{
		
		$starting_local_date_time = date("l F j, Y, g:i a e",$starting_time_stamp);
		myerror_logging(3, "starting loadMerchantHoursForWeek with a starting local time of: ".$starting_local_date_time);
		$hours = $this->getHours($merchant_id, $hour_type);
		if (sizeof($hours) < 7)
		{
			$message = "There are no $hour_type hours set up for this merchant.  Please check the setup.    merchant_id: ".$merchant_id. "    user: ".$_SERVER['PHP_AUTH_USER'];
			//MailIt::sendErrorEmailSupport('ERROR no hours set up for merchant', 'merchant_id: '.$merchant_id.' --  for hour_type: '.$hour_type.' --  user: '.$_SERVER['PHP_AUTH_USER'].'  --  from: '.$_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'].' -- v'.$_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'].' --  '.$_SERVER['STAMP']);
			MailIt::sendErrorEmailSupport("ERROR no hours set up for merchant", $message);
			return false;
		}
		
		$days = $this->getDaysArrayFromStartingTimeStamp($starting_time_stamp);
		
		$holiday_array = $this->getHolidaysThatFallOnTheseDays($merchant_id, $days);
		$this_weeks_hours = $this->getTheWeeksHoursFromDaysHoursAndHolidaysDuringThisWeek($days, $hours, $holiday_array);
		$this->this_weeks_hours = $this_weeks_hours;
		$this->this_weeks_hours_type = $hour_type;
		return $this_weeks_hours;	
	}

	/**
	 * @desc Will return the timestamp of the close for COB scheduling of the passed in timestamp as the day in question.  basically finds out which hour type closes last.  not passing in a timestamp results in find the COB for now (today)
	 * 
	 * @param $merchant_id
	 * @param $time_stamp
	 */
	function getOpenAndCloseForCOBInTimeStampFormForMerchantId($merchant_id,$time_stamp = 0)
	{
		// if a time is not passed in, then set to now (today)
		if ($time_stamp == 0)
			$time_stamp = $this->current_time;
		$merchant_resource = Resource::find(new MerchantAdapter($this->mimetypes),''.$merchant_id);
		$time_zone = $merchant_resource->time_zone;
		$tz = date_default_timezone_get();
		setTheDefaultTimeZone($time_zone,$merchant_resource->state);

		$pickup_hours = $this->getTSForOpenAndCloseForHourType($merchant_id,'R',$time_stamp);
		$delivery_hours = $this->getTSForOpenAndCloseForHourType($merchant_id,'D',$time_stamp);
		
		if ($pickup_hours && $delivery_hours)
		{
			$combined_hours['local_open_date'] = $pickup_hours['local_open_date'];
			if ($pickup_hours['open_ts'] <= $delivery_hours['open_ts'])
			{
				$combined_hours['open_ts'] = $pickup_hours['open_ts'];
				$combined_hours['local_open'] = $pickup_hours['local_open'];
				$combined_hours['local_open_dt_tm'] = $pickup_hours['local_open_dt_tm'];
			} else {
				$combined_hours['open_ts'] = $delivery_hours['open_ts'];
				$combined_hours['local_open'] = $delivery_hours['local_open'];
				$combined_hours['local_open_dt_tm'] = $delivery_hours['local_open_dt_tm'];
			}
			
			if ($pickup_hours['close_ts'] >= $delivery_hours['close_ts'])
			{
				$combined_hours['close_ts'] = $pickup_hours['close_ts'];
				$combined_hours['local_close'] = $pickup_hours['local_close'];
				$combined_hours['local_close_dt_tm'] = $pickup_hours['local_close_dt_tm'];
			} else {
				$combined_hours['close_ts'] = $delivery_hours['close_ts'];
				$combined_hours['local_close'] = $delivery_hours['local_close'];
				$combined_hours['local_close_dt_tm'] = $delivery_hours['local_close_dt_tm'];
			}
			return $combined_hours;
		} else if ($pickup_hours) {
			return $pickup_hours;
		} else if ($delivery_hours) {
			return $delivery_hours;
		} else {
			return false;
		}
		
	}
	
	/**
	 * 
	 * @desc will generate an epoch time stamp for the open and close for the given merchant and hour type and number of days. 1 = today, 2 = today and tomorrow, etc.  
	 * @desc PLEASE NOTE:  time zone MUST BE SET before calling this function
	 * 
	 * @param $merchant_id
	 * @param $hour_type
	 * @param $total_days
	 */
	function getNextOpenAndCloseTimeStamps($merchant_id,$hour_type,$total_days,$right_now = 0)
	{
		if ($right_now == 0) {
            $right_now = $this->current_time;
        }
		$open_close_ts = array();
		for ($i = 0 ; $i < $total_days ; $i++)
		{
			$time_stamp = $right_now + ($i * 24 * 60 * 60);
			if ($hours = $this->getTSForOpenAndCloseForHourType($merchant_id, $hour_type,$time_stamp))
			{
				$open_close_ts[$i] = array("open"=>$hours['open_ts'],"close"=>$hours['close_ts']);
			} else {
				$open_close_ts[$i] = array();
			}
			
		}
		if ($_SERVER['log_level'] > 3)
		{
			myerror_log("results for getNextOpenAndCloseTimeStamps in houradapter");
			$i= 1;
			foreach($open_close_ts as $day)
			{
				
				if ($open = $day['open'])
					$open_string = date("Y-m-d H:i:s",$open);
				if ($close = $day['close'])
					$close_string = date("Y-m-d H:i:s",$close);
				myerror_log("day $i:  o - $open_string     c - $close_string");
				$i++;
			}

		}
		return $open_close_ts;
	}
	
	/**
	 * 
	 * @desc will generate an epoch time stamp for the todays open and close for the given merchant and hour type.  PLEASE NOTE:  time zone MUST BE SET before calling this function
	 * 
	 * @param $merchant_id
	 * @param $hour_type
	 */
	function getTSForOpenAndCloseForHourType($merchant_id,$hour_type,$time_stamp=0)
	{
		// if a time is not passed in, then set to now (today)
		if ($time_stamp == 0)
			$time_stamp = $this->current_time;
		$date = date('Y-m-d',$time_stamp);
		$m = date("m",$time_stamp);
		$d = date("d",$time_stamp);
		$Y = date("Y",$time_stamp);

		$todays_hours = $this->getHourRecordForDate($date, $merchant_id, $hour_type);
		$open = $todays_hours['open'];
		$close = $todays_hours['close'];
		$second_close = isset($todays_hours['second_close']) ? $todays_hours['second_close'] : null;
		$day_open = $todays_hours['day_open'];

		//first do the test for before inverted hours close in which case we'll need to get previous days 
		if ($close < $open)
		{
			$c = explode(':',$close);	
			$close_ts = mktime($c[0], $c[1], '0', $m, $d, $Y);
			if ($time_stamp < $close_ts)
			{
				//reset the time 
				$time_stamp = $time_stamp-86400;
				myerror_log("WE HAVE AN AFTER MIDNIGHT BEFORE CLOSE SITUATION");
				// get yesterdays hours as today's
				$date = date('Y-m-d',$time_stamp);
				$m = date("m",$time_stamp);
				$d = date("d",$time_stamp);
				$Y = date("Y",$time_stamp);
		
				//$yesterday_hours = $this->getHourRecordForDate($yesterday,$merchant_id, $hour_type);
				//$is_merchant_open_yesterday = $yesterday_hours['day_open'];
				//$open_yesterday = $yesterday_hours['open'];
				//$o = explode(':',$open_yesterday);
				$todays_hours = $this->getHourRecordForDate($date,$merchant_id, $hour_type);
				$open = $todays_hours['open'];
				$close = $todays_hours['close'];
				$second_close = isset($todays_hours['second_close']) ? $todays_hours['second_close'] : null;
				$day_open = $todays_hours['day_open'];
			}
		}
		
		if ($day_open == 'Y')
		{	
			
			$o = explode(':',$open);
			$open_ts = mktime($o[0], $o[1], '0', $m , $d, $Y);
			
			//if we have a second close we can ignore the first close
			if ($second_close)
			{
				$c =  explode(':',$second_close);
				$close = $second_close;
			}
			else if ($close < $open)
			{
				//inverted hours without second close
				//NOTE:  if the timestamp
				$tomorrow = date('Y-m-d', $time_stamp+86400);
				$tomorrows_hours = $this->getHourRecordForDate($tomorrow,$merchant_id, $hour_type);
				$close_tomorrow = $tomorrows_hours['close'];
				$c = explode(':',$close_tomorrow);
				//now set the day to one day in the future;
				$d = $d+1;
			} else {
				//normal hours
				$c = explode(':',$close);	
			}
			$close_ts = mktime($c[0], $c[1], '0', $m, $d, $Y);
			$close_date = date('Y-m-d',$close_ts);
			$hours['local_open_date'] = $date;
			$hours['local_open'] = $open;
			$hours['local_open_dt_tm'] = $date.' '.$open;
			$hours['local_close'] = $close;
			$hours['local_close_dt_tm'] = $close_date.' '.$close;
			$hours['open_ts'] = $open_ts;
			$hours['close_ts'] = $close_ts;
			return $hours;
		}
		return false;		
	}

	function getTodaysHours($merchant_id,$hour_type,$time_zone = -100,$time = 0)
	{
		if ($time == 0)
			$time = $this->current_time;
		return $this->getHourRecordForTimeStamp($time, $merchant_id, $hour_type,$time_zone);
	}
	
	function getHourRecordForTimeStamp($timestamp,$merchant_id,$hour_type,$time_zone = -100)
	{
		myerror_logging(3, "starting getHourRecordForTimeStamp");
		$time_zone_string = date_default_timezone_get();
		if ($time_zone != -100)
			setTheDefaultTimeZone($time_zone);
		// this will resolve to the date of the merchants time zone since it should have been set already either in code somewhere else or passed in here
		//  a -100 means it was set somewhere else
		$date = date('Y-m-d',$timestamp);
		$record = $this->getHourRecordForDate($date, $merchant_id, $hour_type);
		date_default_timezone_set($time_zone_string);
		return $record;
	}
	
	function getHourRecordForDate($submitted_date,$merchant_id,$hour_type)
	{
		myerror_logging(3, "starting getHourRecordForDate");
		if ($this->this_weeks_hours)
		{
			myerror_logging(3, "in the this weeks hours");
			foreach ($this->this_weeks_hours as $day_hours)
			{
				if ($submitted_date == $day_hours['date'])
					return $day_hours;
			}
		} else {
			myerror_logging(3, "in the NOT this weeks hours (hours haven't been loaded yet)");
			$holiday_hour_adapter = new HolidayHourAdapter($mimetypes);
			$holiday_hour_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
			$holiday_hour_options[TONIC_FIND_BY_METADATA]['the_date'] = $submitted_date;
			if ($hours = $holiday_hour_adapter->select('',$holiday_hour_options))
			{
				//$this->holiday = true;// ok loaded up with the holiday hours
				myerror_logging(3, "we got holiday hours");
				$hour = array_pop($hours);
				$hour['holiday'] = 'Y';
			}
			else
			{
				//$this->holiday = false;
				myerror_logging(3, "did not find any holiday hours");
				$hour_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
				$hour_options[TONIC_FIND_BY_METADATA]['hour_type'] = $hour_type;
				$day_of_week = date('w',strtotime($submitted_date))+1;
				$hour_options[TONIC_FIND_BY_METADATA]['day_of_week'] = $day_of_week;
				$hours = $this->select('',$hour_options);
				$hour = array_pop($hours);
				$hour['holiday'] = 'N';
			}
			return $hour;
		}
		
	}
	
	function getNext90()
	{
		myerror_logging(2,"starting get next 90 lead times");
		$gmt_available_times = array();
		$minimum_lead = isset($this->merchant_resource) ? $this->merchant_resource->lead_time : 10 ;
		$now = $this->current_time+($minimum_lead * 60);
        $now_plus_90 = $now + 5400;
		return $this->getAvailableTimesFormattedToEvenMinutes($now, $now_plus_90);
/*		
		while ($now < $now_plus_90)
		{
			$gmt_available_times[] = $now;
			$now = $now + 300;
		}
		return $gmt_available_times;
*/		
	}
	
	function getLeadTimesArray($merchant_id,$time_zone,$hour_type)
	{
		$time = $this->current_time;
		
		$gmt_available_times = array();
		
		$todays_hours = $this->getTodaysHours($merchant_id, $hour_type, $time_zone);
		$open = $todays_hours['open'];
		$close = $todays_hours['close'];
		$day_open = $todays_hours['day_open'];
		$second_close = $todays_hours['second_close'];
				
		if ($day_open == 'N')
		{
			$gmt_available_times[] = "No available times today";
			return $gmt_available_times;
		} 
				
		$o = explode(':',$open);
		$c = explode(':',$close);
		
		//$the_day_as_time = strtotime($hours['date']);
		
		$gmt_open = mktime($o[0], $o[1], '0', date("m")  , date("d"), date("Y"));
		$gmt_close = mktime($c[0], $c[1], '0', date("m")  , date("d"), date("Y"));
		$gmt_second_close = NULL;

		// get min lead time for merchant
		$merchant_resource = Resource::find(new MerchantAdapter($this->mimetypes),''.$merchant_id);
		$min_lead_time = $merchant_resource->lead_time;

		myerror_logging(3,"about to do the today exception");
		
		$gmt_first_time = mktime(date("H",$time), date("i",$time)+$min_lead_time, '0', date("m",$time)  , date("d",$time), date("Y",$time));					
		$gmt_last_time = mktime(date("H",$time), date("i",$time)+90, '0', date("m",$time)  , date("d",$time), date("Y",$time));					
		
		myerror_logging(3,"first time is: ".date("l F j, Y, g:i a",$gmt_first_time));
		myerror_logging(3,"today open time is: ".date("l F j, Y, g:i a",$gmt_open));
		myerror_logging(3,"today close is: ".date("l F j, Y, g:i a",$gmt_close));
		if ($gmt_second_close != null)
			myerror_logging(3,"today second close is: ".date("l F j, Y, g:i a",$gmt_close));
		else
			myerror_logging(3,"there is no second close today");

		//check for inverted times
		if ($gmt_open < $gmt_close)
		{
			// times are normal
			if ($gmt_first_time < $gmt_open)
			{
				if ($gmt_open - $gmt_first_time > (75*60))
				{
					$gmt_available_times[] = "This merchant is not accepting orders for today yet.  Please try again closer to merchant opening time.";
					return $gmt_available_times;
				} else {
					; // dont think i need to do anything here
				}	
			}
			else if ($gmt_first_time > $gmt_open && $gmt_first_time < $gmt_close)
			{
				$gmt_open = $gmt_first_time;
				if ($gmt_last_time < $gmt_close)
					$gmt_close = $gmt_last_time;
			}
			else if ($gmt_first_time > $gmt_close)
			{
					$gmt_available_times[] = "This merchant has closed for the day and is no longer accepting orders";
					return $gmt_available_times;
			}
		} else {
			// we have an inverted time				
			if ($gmt_first_time < $gmt_close)
			{
				$gmt_open = $gmt_first_time;
				if ($gmt_close > $gmt_last_time)
					$gmt_close = $gmt_last_time;
			}	
			else if ($gmt_first_time > $gmt_open)
			{
				// after the store has re-opened
				$gmt_open = $gmt_first_time;
				
				if ($gmt_second_close != null)
				{
					if ($gmt_first_time < $gmt_second_close)
						$gmt_close = $gmt_second_close;
					else 
						; // after second close so not open today
				} else {
					;//do we need to figure out the close tomorrow?
					//fuck it just use gmt_last_time and hope its not after close tomorrow
					$gmt_close = $gmt_last_time;
				}
			} else {
				// in between the late night close and the open
				// determine if the open is more than 90 minutes in the future.
				if ($gmt_open > $gmt_last_time)
				{
					$gmt_available_times[] = "This merchant is currently closed and will open at $open";
					return $gmt_available_times;
				}
				else
					$gmt_close = $gmt_last_time;				
			}							
		}
		return $this->getAvailableTimesFormattedToEvenMinutes($gmt_open, $gmt_close);
	}
	
	function getAvailableTimesFormattedToEvenMinutes($start_ts,$stop_ts)
	{
		$available_times = array();
		$i = 0;
		$interval = 1;
		while ($start_ts < $stop_ts) {
			//myerror_logging(2,"adding: ".date("l F j, Y, g:i a",$start_ts));
			$available_times[] = $start_ts;
			$min = ''.date("i",$start_ts);
			$dig = substr($min, 1);
		//*	
		 	if ($i == 10)
			{ 
				if ($dig == 0 || $dig == 5)
					$interval = 5;
				else if ($dig < 5)
					$interval = 5 - $dig;
				else if ($dig > 5)
					$interval = 10 - $dig;
			} else 
		//*/	
			if ($i > 10)
				$interval = 5;
			$start_ts = $start_ts + ($interval*60);
			$i++;
		}
		return $available_times;
	}
	
	function buildAdvancedDeliveryTimesArray($merchant_id,$time_zone,$hour_type,$time)
	{
		// get merchant delivery info
		$mdi_adapter = new MerchantDeliveryInfoAdapter($mimetypes);
		$mdi_data['merchant_id'] = $merchant_id;
		$mdi_options[TONIC_FIND_BY_METADATA] = $mdi_data;
		if ($resource = Resource::find($mdi_adapter,null,$mdi_options))
		{
			$merchant_minimum_delivery_window = $resource->minimum_delivery_time;
			$interval = $resource->delivery_increment;
			$max_days_out = $resource->max_days_out;	
		} else {
			$merchant_minimum_delivery_window = 90;
			$interval = 15;	
			$max_days_out = 5;
		}
		return $this->buildAdvancedTimesArray($merchant_id, $time_zone, $hour_type, $time, $interval, $merchant_minimum_delivery_window, $max_days_out);
	}
	
	function buildCurrentPickupTimesArray()
	{
		;// first get next 90 min
		
		// then get remaining with an interval of 15, min delivery window of 90, max days out of 0?  could work

	}
	
	function buildAdvancedTimesArray($merchant_id,$time_zone,$hour_type,$time=0,$interval = 30,$merchant_minimum_delivery_window = 90,$max_days_out = 5)
	{
		if ($time == 0)
			$time = $this->current_time;
		
		// shortest delivery window is set on the merchant delivery info record and passed in here as $merchant_minimum_delivery_window
		// so we first set the starting point to build the array from.
		$gmt_first_time = mktime(date("H",$time), date("i",$time)+$merchant_minimum_delivery_window, '0', date("m",$time)  , date("d",$time), date("Y",$time));					
		
		myerror_logging(3, "now is: ".date("Y-m-d H:i:s",$time));
		myerror_logging(3, "start is: ".date("Y-m-d H:i:s",$gmt_first_time));
		myerror_logging(3, "max days out is: ".$max_days_out);
		
		if (date("Y-m-d",$time) != date("Y-m-d",$gmt_first_time))
			$reload = true;	
		
		$the_weeks_hours = $this->getMerchantHoursForWeek($gmt_first_time, $merchant_id, $hour_type,$reload);
		
		myerror_logging(3,"about to build advanced time array starting with: ".date("l F j, Y, g:i a e",$gmt_first_time));

		$gmt_available_times = array();
		for ($i = 0; $i<$max_days_out+1 ;$i++)
		{
			$gmt_open2 = null;
			$hours = $the_weeks_hours[$i];
			$hours_tomorrow = $the_weeks_hours[$i+1];
			//myerror_logging(2,"the looping date is: ".$hours['date']);			
			if ($hours)
			{
				$open = $hours['open'];
				$close = $hours['close'];
				$day_open = $hours['day_open'];
				$second_close = $hours['second_close'];
				if ($day_open == 'N')
					continue;
				$o = explode(':',$open);
				$c = explode(':',$close);
				
				$the_day_as_time = strtotime($hours['date']);
				
				$gmt_open = mktime($o[0], $o[1], '0', date("m",$the_day_as_time)  , date("d",$the_day_as_time), date("Y",$the_day_as_time));
				$gmt_close = mktime($c[0], $c[1], '0', date("m",$the_day_as_time)  , date("d",$the_day_as_time), date("Y",$the_day_as_time));
				$gmt_second_close = NULL;
				
				if ($gmt_open > $gmt_close && $second_close == NULL)
				{
						// first get tomorrow's close (like 3am or something)
						myerror_logging(3,"we have inverted hours without a second close so get the close time of tomorrow");
						$close2 = $hours_tomorrow['close'];
						$c2 = explode(':',$close2);
						$gmt_close2 = mktime($c2[0], $c2[1], '0', date("m",$the_day_as_time)  , date("d",$the_day_as_time)+1, date("Y",$the_day_as_time));
						myerror_logging(2,"close2 is: ".date("l F j, Y, g:i a",$gmt_close2));
				}
				else if ($second_close != NULL)
				{
					myerror_logging(3,"we have inverted hours WITH a second close.");
					$sc = explode(':',$second_close);
					$gmt_second_close = mktime($sc[0], $sc[1], '0', date("m",$the_day_as_time)  , date("d",$the_day_as_time), date("Y",$the_day_as_time));
					myerror_logging(2,"second close is: ".date("l F j, Y, g:i a",$gmt_second_close));
				}
				if ($i == 0)
				{
					myerror_logging(3,"about to do the first day exception");

					myerror_logging(2,"first time is: ".date("l F j, Y, g:i a",$gmt_first_time));
					myerror_logging(2,"today open time is: ".date("l F j, Y, g:i a",$gmt_open));
					myerror_logging(2,"today close is: ".date("l F j, Y, g:i a",$gmt_close));
					if ($gmt_second_close != null)
						myerror_logging(3,"today second close is: ".date("l F j, Y, g:i a",$gmt_close));
					else
						myerror_logging(3,"there is no second close today");

					//check for inverted times
					if ($gmt_open < $gmt_close)
					{
						// times are normal
						if ($gmt_first_time < $gmt_open)
							$gmt_open = $gmt_open + (30*60); // set to an 30 min after open	
						else if ($gmt_first_time > $gmt_open && $gmt_first_time < $gmt_close)
							$gmt_open = $gmt_first_time;
						else if ($gmt_first_time > $gmt_close)
							continue;
					} else {
						// we have an inverted time				
						if ($gmt_first_time < $gmt_close)
						{
							// first save the open time in a differnt variable
							$gmt_open2 = $gmt_open + (30*60);
							
							// now set start time
							$gmt_open = $gmt_first_time;
							
							// now check to see if there is a second close and save it if so
							if ($gmt_second_close != null)
								$gmt_close2 = $gmt_second_close;
						}	
						else if ($gmt_first_time > $gmt_open)
						{
							// after the store has re-opened
							$gmt_open = $gmt_first_time;
							
							// inverted time so set close to close tomorrow unless there is a second close 
							$gmt_close = $gmt_close2;
							if ($gmt_second_close != null)
							{
								if ($gmt_first_time < $gmt_second_close)
									$gmt_close = $gmt_second_close;
								else 
									continue; // after second close so not open today
							}
						} else {
							// in between the late night close and the open
							$gmt_open = $gmt_open + (30*60); // set to an 30 min after open
							
							// inverted time so set close to close tomorrow unless there is a second close 
							$gmt_close = $gmt_close2;
							if ($gmt_second_close != null)
								$gmt_close = $gmt_second_close;
						}							
					}
				} else {
					// ok so now we're into the subsequent days
					$gmt_open2 = null;

					// set the open to 30 min after open that was grabbed from the the hours query
					$gmt_open = $gmt_open + (30*60);
					
					// if there was an inverted time, it was already grabbed by the previous day so just check to see if there is a second close
					if ($gmt_second_close != NULL)
						$gmt_close = $gmt_second_close;
					else if ($gmt_open > $gmt_close) // no second close, so check for inverted time, if so grab tomorrows close
						$gmt_close = $gmt_close2;
					else
						; // normal hours today so just use the close grabbed from the query.
					
				}
				
				if ($interval < 1)
					$interval = 30;
				// now set the start to always be on the half hour
				$min = date('i',$gmt_open);
				if ($min < 16)
					$gmt_open = mktime(date("H",$gmt_open),'0','0', date("m",$gmt_open)  , date("d",$gmt_open), date("Y",$gmt_open));
				else if ($min > 15 && $min < 45)
					$gmt_open = mktime(date("H",$gmt_open),'30','0', date("m",$gmt_open)  , date("d",$gmt_open), date("Y",$gmt_open));
				else if ($min > 44)
					$gmt_open = mktime(date("H",$gmt_open)+1,'0','0', date("m",$gmt_open)  , date("d",$gmt_open), date("Y",$gmt_open));
				
				while ($gmt_open < $gmt_close) {
					//myerror_logging(2,"adding: ".date("l F j, Y, g:i a",$gmt_open));
					$gmt_available_times[] = $gmt_open;
					$gmt_open = $gmt_open + ($interval*60);
				}
				if ($gmt_open2)
				{
					myerror_logging(3,"we are in the gmt open 2");
					
					$gmt_open = $gmt_open2;
					$gmt_close = $gmt_close2;
					while ($gmt_open < $gmt_close) {
						//myerror_logging(2,"adding: ".date("l F j, Y, g:i a",$gmt_open));
						$gmt_available_times[] = $gmt_open;
						$gmt_open = $gmt_open + ($interval*60);
					}
				}
			}
		} // end for next loop
		if (getProperty("log_level") > 3)
		{		
			myerror_logging(3,"*******************");
			foreach ($gmt_available_times as $date_time)
				myerror_logging(3,''.$date_time.' = '.date("l F j, Y, g:i a e",$date_time));
			myerror_logging(3,"*******************");
		}	
		//date_default_timezone_set('America/Denver');
		return $gmt_available_times;

	}
	
	/**
	 * 
	 * @desc Takes a date string, eg: 2012-10-15, and returns the local open and close date time strings for a merchant in an array object 
	 * @return $local['local_open_dt_tm'] <br>$local['local_close_dt_tm']<br>$local['local_open_date'] <br>$local['merchant_id']
	 * @return $local['local_close_dt_tm']<br>
	 * @return $local['local_open_date'] <br>
	 * @return $local['merchant_id']
	 * 
	 * @param $merchant_id
	 * @param $date
	 * @param $hour_type
	 */
	function getLocalOpenAndCloseDtTmForDate($merchant_id,$date,$hour_type)
	{
    	$hour_record = $this->getHourRecordForDate($date, $merchant_id, $hour_type);
    	$open = $hour_record['open'];
		$close = $hour_record['close'];
		$local_open_dt_tm = $date.' '.$open;	
		if ($open > $close)
		{
			//inverted hours so get hour record of tomorrow
			$tomorrow = strtotime ( '+1 day' , strtotime($date) ) ;
			$tomorrow = date ( 'Y-m-d' , $tomorrow );
			$hour_record_tomorrow = $this->getHourRecordForDate($tomorrow, $merchant_id, $hour_type);
			$local_close_dt_tm = $tomorrow.' '.$hour_record_tomorrow['close'];		
		} else {
			$local_close_dt_tm = $date.' '.$close;
		}
		$local['local_open_dt_tm'] = $local_open_dt_tm;
		$local['local_close_dt_tm'] = $local_close_dt_tm;
		$local['local_open_date'] = $date;
		$local['merchant_id'] = $merchant_id;
		return $local;
		
	}
	
	function getLocalOpenAndCloseDtTmForTimestamp($merchant_id,$time_stamp,$hour_type)
	{
		;//$this->getHour
		
	}
		
	function isMerchantOpenAtThisTime($merchant_id,$time_zone,$hour_type,$the_time = 0)
	{
		
		$merchant_resource = Resource::find(new MerchantAdapter($mimetypes),''.$merchant_id);
		
		$tzone_string = date_default_timezone_get();
		// now set the TZ if it wasn't set before calling the function. a -100 indicates we set it already
		if ($time_zone != -100)
			setTheDefaultTimeZone($time_zone,$merchant_resource->state);
		
		if ($the_time == 0)
		{
			$now = true;
			$the_time = time();
		}
			
		$tz = date_default_timezone_get();
		myerror_logging(3,"the default timezone is: ".$tz);
		
		//set merchant local times
		$local_order_time = date("Y-m-d H:i:s",$this->current_time);
		$local_pickup_time = date("Y-m-d H:i",$the_time);
		$this->local_order_and_pickup_time_array['local_order_time'] = $local_order_time;
		$this->local_order_and_pickup_time_array['local_pickup_time'] = $local_pickup_time;
		myerror_logging(3,"local order time: ".$local_order_time);
		myerror_logging(3,"local pickup time: ".$local_pickup_time);
		
		if ($this->this_weeks_hours)
		{
			$date = date('Y-m-d',$the_time);
			foreach ($this->this_weeks_hours as $daily_hours)
			{
				if ($daily_hours['date'] == $date)
				{
					$hours = $daily_hours;
				}
			}
		} else {
			$hours = $this->getHourRecordForTimeStamp($the_time,$merchant_id,$hour_type);
		}

		if ($hour_type == 'D')
			$delivery_string = ' for delivery';
		else
			$delivery_string = '';
		
		if ($hours['day_open'] == 'Y')
		{

				$gmt_open = $this->getTimeStampOfDayHour($hours['open'],$the_time);
				$gmt_close = $this->getTimeStampOfDayHour($hours['close'],$the_time);
				myerror_logging(3,"local_open: ".date("g:i a",$gmt_open));
				myerror_logging(3,"local_close: ".date("g:i a",$gmt_close));
				myerror_logging(3,"local pickup time: ".date("g:i a",$the_time));

				$second_close = $hours['second_close'];
				if ($second_close != NULL) {
					myerror_logging(3,"we have inverted hours WITH a second close.");
					$gmt_second_close = $this->getTimeStampOfDayHour($second_close,$the_time);
					myerror_logging(3,"second close is: ".date("l F j, Y, g:i a",$gmt_second_close));
				}
				$message = '';
				if ($gmt_open < $gmt_close) {
					if ($gmt_open <= $the_time && $the_time <= $gmt_close) {
						myerror_logging(3,"merchant is open");
						$merchant_open = true;
						$minutes_till_close = ($gmt_close - $the_time)/60;
						myerror_logging(3,'minutes till close is: '.$minutes_till_close);
						if ($minutes_till_close < 60 && $now) {
							$message = "Please note that this merchant will close$delivery_string at ".date('g:i a',$gmt_close)." today";
						}
					} else if ($gmt_open > $the_time) {
						myerror_logging(3,"merchant is not open yet.  merchant will open$delivery_string at ".date('g:i a',$gmt_open));
						if ($now)
							$message = "Merchant is not open$delivery_string yet.  Merchant will open$delivery_string at ".date('g:i a',$gmt_open);
						else
							$message = "Sorry, this merchant is closed$delivery_string at your requested pickup time. Merchant will open$delivery_string at ".date('g:i a',$gmt_open);
						$merchant_open = false;
					} else if ($gmt_close < $the_time) {
						myerror_logging(3,"merchant has closed$delivery_string for the day.  Merchant closed$delivery_string at ".date('g:i a',$gmt_close));
						if ($now)
							$message = "Sorry, this merchant has closed$delivery_string for the day.  Merchant closed$delivery_string at ".date('g:i a',$gmt_close);
						else
							$message = "We're sorry, this merchant is closed$delivery_string at your requested time. Merchant closes$delivery_string at ".date('g:i a',$gmt_close);
						$merchant_open = false;
					}
				} else {
					if ($the_time < $gmt_close) {
						myerror_logging(3,"merchant is open inverted hours before close");
						$message = "merchant is open";
						$merchant_open = true;
					} else if ($the_time > $gmt_open) {
						if ($second_close == NULL) {
							myerror_logging(3,"merchant is open inverted hours after open");
							$message = "merchant is open";
							$merchant_open = true;
						} else if ($the_time < $gmt_second_close) {
							myerror_logging(3,"merchant is open inverted hours after open but before second close");
							$message = "merchant is open";
							$merchant_open = true;
						} else if ($the_time > $gmt_second_close) {
							myerror_logging(3,"inverted hours merchant has closed for the day.  merchant closed for the second time at ".date('g:i a',$gmt_second_close));
							if ($now)
								$message = "We're sorry, this merchant is currently closed$delivery_string.  Merchant closes$delivery_string at ".date('g:i a',$gmt_second_close)." today.";
							else 
								$message = "We're sorry, this merchant is closed$delivery_string at your requested time. Merchant closes$delivery_string at ".date('g:i a',$gmt_second_close);
							$merchant_open = false;
						}
					} else {
						myerror_logging(3,"inverted hours, in between close and open.  Merchant will open at ".date('g:i a',$gmt_open));
						if ($now)
							$message = "Sorry this merchant is not open$delivery_string yet. Merchant will open$delivery_string at ".date('g:i a',$gmt_open);
						else
							$message = "Sorry, this merchant is closed$delivery_string at your requested pickup time. Merchant will open$delivery_string at ".date('g:i a',$gmt_open);
						$merchant_open = false;
					}
				}

				if ($merchant_open) {
					//check for donut hole
					$hole_adapter = new HoleHoursAdapter($m);
					if ($row = $hole_adapter->getByMerchantIdAndDayOfWeekAndOrderType($merchant_id,date('w',$the_time)+1,$hour_type)) {
						myerror_log("THere are hole hours");
						$hole_start = $this->getTimeStampOfDayHour($row['start_time'],$the_time);
						$hole_stop = $this->getTimeStampOfDayHour($row['end_time'],$the_time);
						if ($hole_start < $the_time && $the_time < $hole_stop) {
							myerror_log("WE ARE IN THE HOLE!!!!");
							$merchant_open = false;
						}
					}
				}
		} else {
			$message = "Sorry this merchant is closed today$delivery_string .";
			if ($hours['holiday'] == 'Y')
			{
				$message = $_SERVER['GLOBAL_PROPERTIES']['holiday_closed_message'];
				$this->holiday = true;
			}
			$merchant_open = false;
		}
		
//CHANGE_THIS			
		date_default_timezone_set($tzone_string);  // reset the timezone back to what it was.  before calculating the open hours.  this is only relevant during order creation.
		
		$this->merchant_status_message = $message;
		return $merchant_open;
	}

	function getTimeStampOfDayHour($day_hour,$time_stamp_of_date)
	{
		$s = explode(":",$day_hour);
		$time_stamp_of_day_hour = mktime($s[0], $s[1], '0', date("m",$time_stamp_of_date)  , date("d",$time_stamp_of_date), date("Y",$time_stamp_of_date));
		return $time_stamp_of_day_hour;
	}
	
	function getRecentlyClosedMerchants($minutes,$day = 0)
	{
		
		if ($day == 0)
			$day = date('w') + 1;
		
		$default_server_timezone_offset = $_SERVER['GLOBAL_PROPERTIES']['default_server_timezone_offset'];
		$ts = time();
		$ts_sub_min = time()-($minutes*60);
		
		// this is how we get the day
		//DAYOFWEEK(DATE_SUB(NOW(),INTERVAL ($default_server_timezone_offset-a.time_zone) HOUR ))

//		if ($_SERVER['GLOBAL_PROPERTIES']['daylight_savings'])
//			$sql = "SELECT z.* FROM (SELECT a.merchant_id,DATE(DATE_SUB(NOW(),INTERVAL ($default_server_timezone_offset - a.time_zone) HOUR)) as local_close_date, c.close, c.open, DATE_ADD(DATE_ADD(DATE(DATE_SUB(NOW(),INTERVAL ($default_server_timezone_offset - a.time_zone) HOUR)), INTERVAL c.close HOUR_SECOND), INTERVAL ($default_server_timezone_offset-a.time_zone) HOUR ) as close_stamp FROM Merchant a, `Hour` c WHERE a.active = 'Y' AND a.merchant_id = c.merchant_id AND c.day_of_week = $day AND c.day_open = 'Y' AND c.merchant_id > 1000 AND c.hour_type = 'R') z WHERE z.close_stamp > DATE_SUB(NOW(), INTERVAL $minutes MINUTE) AND z.close_stamp < NOW()";
//		else
//			$sql = "SELECT z.* FROM (SELECT a.merchant_id, c.close, DATE_ADD(DATE_ADD(DATE(DATE_SUB(NOW(),INTERVAL 8 HOUR)), INTERVAL c.close HOUR_SECOND), INTERVAL (-a.time_zone) HOUR ) as close_stamp FROM Merchant a, `Hour` c WHERE a.active = 'Y' AND a.merchant_id = c.merchant_id AND c.day_of_week = $day AND c.day_open = 'Y' AND c.merchant_id > 1000 AND c.hour_type = 'R') z WHERE z.close_stamp > DATE_SUB(NOW(), INTERVAL $minutes MINUTE) AND z.close_stamp < NOW()";
		
		//if ($_SERVER['GLOBAL_PROPERTIES']['daylight_savings'])
		//	$default_server_timezone_offset = -1;
		$sql = "SELECT z.* FROM (SELECT a.merchant_id,DATE(DATE_SUB(NOW(),INTERVAL ($default_server_timezone_offset - a.time_zone) HOUR)) as local_close_date, c.close, c.open, DATE_ADD(DATE_ADD(DATE(DATE_SUB(NOW(),INTERVAL ($default_server_timezone_offset - a.time_zone) HOUR)), INTERVAL c.close HOUR_SECOND), INTERVAL ($default_server_timezone_offset-a.time_zone) HOUR ) as close_stamp, c.day_of_week  FROM Merchant a, `Hour` c WHERE c.close IS NOT NULL AND c.close > '00:00:00' AND a.active = 'Y' AND a.merchant_id = c.merchant_id AND c.day_of_week = DAYOFWEEK(DATE_SUB(NOW(),INTERVAL ($default_server_timezone_offset-a.time_zone) HOUR )) AND c.day_open = 'Y' AND c.merchant_id > 1000 AND c.hour_type = 'R') z WHERE z.close_stamp > DATE_SUB(NOW(), INTERVAL $minutes MINUTE) AND z.close_stamp < NOW()";
		$sql .= " UNION ";
		$sql .= "SELECT z.* FROM (SELECT a.merchant_id,DATE(DATE_SUB(NOW(),INTERVAL ($default_server_timezone_offset - a.time_zone) HOUR)) as local_close_date, c.second_close, c.open, DATE_ADD(DATE_ADD(DATE(DATE_SUB(NOW(),INTERVAL ($default_server_timezone_offset - a.time_zone) HOUR)), INTERVAL c.second_close HOUR_SECOND), INTERVAL ($default_server_timezone_offset-a.time_zone) HOUR ) as close_stamp, c.day_of_week  FROM Merchant a, `Hour` c WHERE c.second_close IS NOT NULL AND c.second_close > '00:00:00' AND a.active = 'Y' AND a.merchant_id = c.merchant_id AND c.day_of_week = DAYOFWEEK(DATE_SUB(NOW(),INTERVAL ($default_server_timezone_offset-a.time_zone) HOUR )) AND c.day_open = 'Y' AND c.merchant_id > 1000 AND c.hour_type = 'R') z WHERE z.close_stamp > DATE_SUB(NOW(), INTERVAL $minutes MINUTE) AND z.close_stamp < NOW()";

		myerror_logging(2,"GET CLOSED STORES: ".$sql);
		$options[TONIC_FIND_BY_SQL] = $sql;
		if ($merchants = $this->select('',$options))
		{
			myerror_logging(1,"Size of recently closed merchants is: ".sizeof($merchants));
			
			// now get the open time stamp of the recently closed merchnat
			foreach ($merchants as &$merchant)
			{
				$merchant['local_close_dt_tm'] = $merchant['local_close_date'].' '.$merchant['close'];
				
				// check to see if we have inverted hours
				if ($merchant['close'] > $merchant['open'] && $merchant['open'] != '00:00:00')
				{
					$merchant['local_open_date'] = $merchant['local_close_date'];
					$merchant['local_open_dt_tm'] = $merchant['local_open_date'].' '.$merchant['open'];
				}
				else 
				{
					// inverted hours so get open from yesterday
					$day = $merchant['day_of_week'];
					if ($day == 1)
						$day = 7;
					else 
						$day = $day - 1;
					
					$sql = "SELECT open FROM Hour WHERE merchant_id = ".$merchant['merchant_id']." AND day_of_week = $day AND hour_type = 'R'";
						
					$options[TONIC_FIND_BY_SQL] = $sql;
					$open_hour = $this->select('',$options);
					$open_hour = array_pop($open_hour);	
					$merchant['open'] = $open_hour['open'];
					
					$merchant_local_close_date = $merchant['local_close_date'];
					
					$newdate = strtotime ( '-1 day' , strtotime ( $merchant_local_close_date ) ); 
					$date = date('Y-m-d',$newdate);
					$merchant['local_open_date'] = $date;
					
					$merchant['local_open_dt_tm'] = $date.' '.$merchant['open'];
					
				}
			
				$sectoep = strtotime($merchant['close_stamp']);
				//myerror_logging(2,$merchant['merchant_id'].' '.$merchant['local_open_dt_tm'].'   '.$merchant['local_close_dt_tm'].'  '.$merchant['close_stamp']);
				myerror_logging(2,$merchant['merchant_id'].' '.$merchant['local_open_dt_tm'].'   '.$merchant['local_close_dt_tm'].'  '.$sectoep);
			}
			return $merchants;
		}
		else
			return false;
	}
   
	function getLocalOrderAndPickupTimes()
	{
		return $this->local_order_and_pickup_time_array;
	}
	
    function getMerchantStatusMessage()
    {
    	return $this->merchant_status_message;
    }
    
    function getHoliday()
    {
    	return $this->holiday;
    }
}
?>