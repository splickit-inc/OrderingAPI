<?php

abstract class SplickitActivity
{
	public $data = array();
	protected $activity_history_resource;
	protected $error_text;
	protected $mimetypes = array(
		'html' => 'text/html',
		'xml' => 'text/xml'	);
	protected $file_adapter;   
	
	var $rescheduled_activity_id;
	
	function SplickitActivity($activity_history_resource)
	{
		if ($activity_history_resource != null)
		{
			$this->activity_history_resource = $activity_history_resource;
			$this->setDataFromSemicolonSeparatedNameValuePairs($activity_history_resource->info);
			//$this->rescheduleThisActivity($activity_history_resource);
		}
		$this->file_adapter = new FileAdapter($this->mimetypes, 'resources');
		
	}
	
	/**
	 * @desc Takes a string of secicolon separated name value pairs and loads of the data field of this activity. Example:   name=bob;age=46;address=11 main street   will result in    $data['name'] = 'bob', $data['age'] = 46,  etc....
	 * @param string $info_string
	 */
	function setDataFromSemicolonSeparatedNameValuePairs($info_string)
	{
		if ($info_string == null || trim($info_string) == '')
			return true;
		myerror_log("info_string in activity is: $info_string",3);
		$field_data = explode(';', $info_string);
		$this->setDataFromArrayOfStringsOfNameValuePairs($field_data);
	}
	
	/**
	 * @desc takes an array of strings of name value pairs ('name=bob','age=46','address=11 main street') and loads the data array with the name value pairs.  result will be $data['name'] = 'bob', $data['age'] = 46,  etc....
	 * @param array $field_data
	 */
	function setDataFromArrayOfStringsOfNameValuePairs($field_data)
	{
		foreach ($field_data as $data_row)
		{
			$s = explode('=', $data_row);
			$this->data[$s[0]] = $s[1];
		}
	}
	
	/**
	 * 
	 * @desc used to prevent duplication.  will return true if a duplicate activity in the locked='N' state exists'
	 * @param Resource $activity_history_resource
	 */
	function hasThisBeenRescheduledAlready($activity_history_resource)
	{
		$data['activity_id'] = array("!="=>$activity_history_resource->activity_id);
		$data['activity'] = $activity_history_resource->activity;
		$data['info'] = $activity_history_resource->info;
		$data['locked'] = 'N';
		$data['repeat_interval'] = $activity_history_resource->repeat_interval;
		myerror_logging(3, "aboiut to check if this recuring activity has already been rescheduled");
		$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
		if ($records = $activity_history_adapter->getRecords($data))
		{
			//we already have an activity sceduled so dont create a new one.
			myerror_log("we have a duplication.  there were ".count($records)." records found");
			return true;
		}
		myerror_logging(3,"no existing activity so let reschedule it");
		return false;
	}
	
	/**
	 * 
	 * @desc determines if the activity is recuring and if so then reschedules it
	 * @param Resource $activity_history_resource
	 */
	function rescheduleThisActivity($activity_history_resource)
	{
		myerror_logging(3,"check for recuring activity");
		if (isset($activity_history_resource->repeat_interval) && $activity_history_resource->repeat_interval > 0)
		{
			myerror_logging(3,"we have an activity that is recuring");
			// so lets make sure we're not duplicating
			if ($this->hasThisBeenRescheduledAlready($activity_history_resource)) {
				return true;
			}
			$next_iteration_of_activity_history_resource = clone $activity_history_resource;
			$next_iteration_of_activity_history_resource->_exists = false;
			unset($next_iteration_of_activity_history_resource->activity_id);
			unset($next_iteration_of_activity_history_resource->created);
			unset($next_iteration_of_activity_history_resource->modified);
			unset($next_iteration_of_activity_history_resource->stamp);
			$next_iteration_of_activity_history_resource->tries = 0;
			$next_iteration_of_activity_history_resource->locked = 'N';
			//$new_doit_dt_tm = $next_iteration_of_activity_history_resource->doit_dt_tm + $next_iteration_of_activity_history_resource->repeat_interval;
			$new_doit_dt_tm = $this->getNextDoItTimeForRepeatingActivity($next_iteration_of_activity_history_resource->doit_dt_tm, $next_iteration_of_activity_history_resource->repeat_interval);
			$next_iteration_of_activity_history_resource->doit_dt_tm = $new_doit_dt_tm;
			if ($next_iteration_of_activity_history_resource->save())
			{
				myerror_logging(3, "the activity has been rescheduled");
				$this->rescheduled_activity_id = $next_iteration_of_activity_history_resource->activity_id;
				return true;
			}
			
			// there was some error rescheduleing the activity
			$activity_id = $activity_history_resource->activity_id;
			error_log("There was an error thown trying to reschedule activity $activity_id: ".$next_iteration_of_activity_history_resource->_adapter->getLastErrorText());
			MailIt::sendErrorEmail("ERROR RESCHEDULING ACTIVITY!", "There was an error thown trying to reschedule activity $activity_id: ".$next_iteration_of_activity_history_resource->_adapter->getLastErrorText());
		}
		myerror_log("Not a recuring activity");
		return;
	}
	
	function getNextDoItTimeForRepeatingActivity($last_doit_dt_tm,$repeat_interval)
	{
		$new_doit_dt_tm = $last_doit_dt_tm + $repeat_interval;
		// do not let new doit be in the past
		while ($new_doit_dt_tm < time()) {
			$new_doit_dt_tm = $new_doit_dt_tm + $repeat_interval;
		}
		return $new_doit_dt_tm;
	}
	
	function getErrorText()
	{
		return $this->error_text;
	}
	
	/**
	 * 
	 * @desc checks for reschduling, runs the doit() method, marks activity failed or executed
	 */
	function executeThisActivity()
	{
		myerror_log("*************** we found an activity, so lets DOIT! ***********************");
		$this->rescheduleThisActivity($this->activity_history_resource);
		$time1 = microtime(true);
		if ($this->doit() === false) {
            $this->markActivityFailed();
        } else {
            $this->markActivityExecuted();
        }
		$time2 = microtime(true);
		$elapsed = $time2-$time1;
		myerror_log("elapsed time for activity->doit call is: ".$elapsed);
		myerror_log("************************END Of ACTIVITY********************************");
		return;
	}
	
	abstract function doit();
		
	/**
	 * @desc finds the activity history record matching the submitted acitivity history id. then tries to get a lock, and if so, returns the associated activity
	 * @return SplickitActivity
	 */
	static function findActivityResourceAndReturnActivityObjectByActivityId($activity_id)
	{
		$activity_history_resource_adapter = new ActivityHistoryAdapter($mimetypes);
		if ($activity_history_resource = Resource::find($activity_history_resource_adapter,"$activity_id"))
		{
			return $activity_history_resource_adapter->getActivityFromUnlockedActivityHistoryResource($activity_history_resource);	
		}
		myerror_log("ERROR!  could not retrieve ActivityHistoryResource by id as no matching record for id: ".$activity_id);
		return;
		
	}
		
	static function getActivity($activity_history_resource)
	{
			$activity_name = $activity_history_resource->activity;
			if ($activity_name == 'CreateCOB')
				$activity = new COBActivity($activity_history_resource);
			else if ($activity_name == 'PushMessage')
				$activity = new PushActivity($activity_history_resource);
			else if ($activity_name == 'RunQuery')
				$activity = new RunQueryActivity($activity_history_resource);		
			else if ($activity_name == 'SendMerchantStatement')
				$activity = new SendMerchantStatementActivity($activity_history_resource);		
			else if ($activity_name == 'SendMerchantStatus')
				$activity = new SendMerchantStatusActivity($activity_history_resource);		
			else if ($activity_name == 'BuildLetter')
				$activity = new BuildLetterActivity($activity_history_resource);		
			else if ($activity_name == 'DailyReport')
				$activity = new DailyReportActivity($activity_history_resource);		
			else if ($activity_name == 'SendDepositStatement')
				$activity = new SendDepositStatementActivity($activity_history_resource);		
			else if ($activity_name == 'ExecuteObjectFunction')
				$activity = new ExecuteObjectFunctionActivity($activity_history_resource);		
			else if ($activity_name == 'SendGroupOrder')
				$activity = new SendGroupOrderActivity($activity_history_resource);		
			else if ($activity_name == 'SendResellerStatement')
				$activity = new SendResellerStatementActivity($activity_history_resource);
            else if ($activity_name == 'StatsEmail')
                $activity = new StatsEmailActivity($activity_history_resource);
            else if ($activity_name == 'GuestUserCreditCardCheck')
                $activity = new GuestUserCreditCardCheckActivity($activity_history_resource);
            else {
                $activity_class_name = $activity_name.'Activity';
                $activity_class_name_lower = strtolower($activity_class_name);
                $path = "lib" . DIRECTORY_SEPARATOR . "activities" . DIRECTORY_SEPARATOR . $activity_class_name_lower . ".php";
                if (file_exists($path)) {
                    $activity = new $activity_class_name($activity_history_resource);
                } else {
                    myerror_log("ERROR!  NO matching activity found!");
                    MailIt::sendErrorEmail('Error in activity controller', 'no matching activity found for: '.$activity_name);
                    $activity = new DummyActivity($activity_history_resource);
                }
			}
			return $activity;
	}
	
	function markActivityFailed()
	{
		MailIt::sendErrorEmail(get_class()." Failure!", "reason: ".$this->getErrorText()."     activity_id: ".$this->activity_history_resource->activity_id);
		return $this->markActivityFailedWithoutEmail();
	}
	
	function markActivityFailedWithoutEmail()
	{
		$this->activity_history_resource->modified = time();
		$this->activity_history_resource->locked = 'F';
		if ($this->activity_history_resource->save())
			;// all is good
		else
			MailIt::sendErrorEmail('ERROR! could not mark activity as FAILED', 'activity_id: '.$this->activity_history_resource->activity_id.'   error: '.$this->activity_history_resource->_adapter->getLastErrorText());
		return true;
		
	}

	function cancelActivity()
    {
        $this->activity_history_resource->modified = time();
        $this->activity_history_resource->locked = 'C';
        if ($this->activity_history_resource->save()) {
            return true;
        } else {
            MailIt::sendErrorEmail('ERROR! could not mark activity cancelled', 'activity_id: '.$this->activity_history_resource->activity_id);
        }
    }
	
	function markActivityExecuted()
	{
		myerror_log("about to mark the activity as executed");
		$this->activity_history_resource->executed_dt_tm = date('Y-m-d H:i:s');
		$this->activity_history_resource->modified = time();
		$this->activity_history_resource->locked = 'E';
		if ($this->activity_history_resource->save())
			return true;
		else
		{
			MailIt::sendErrorEmail('ERROR! could not mark activity complete', 'activity_id: '.$this->activity_history_resource->activity_id);
		}	
	}
	
	function set($name,$value)
	{
		$this->data[$name] = $value;
	}

	function setData($data)
	{
		if (is_array($data))
			$this->data = $data;
		else
			myerror_log("DATA IS NOT AN ARRATY IN SplickitActivity->setData");
	}
	
	function getActivityHistoryId()
	{
		return $this->activity_history_resource->activity_id;
	}
	
}

?>