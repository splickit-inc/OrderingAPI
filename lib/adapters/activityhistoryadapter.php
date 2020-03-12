<?php

class ActivityHistoryAdapter extends MySQLAdapter
{
	function ActivityHistoryAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Activity_History',
			'%([0-9]{1,15})%',
			'%d',
			array('activity_id'),
			NULL,
			array('doit_dt_tm','modified','created')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
		return parent::select($url,$options);
	}

	/**
	 * 
	 * @desc  will create an activity, doit_ts must be a timestamp. return the created activity_id on sucess and null on fail.
	 * 
	 * @param string $activity
	 * @param timestamp $doit_ts
	 * @param string $info
	 * @param string $activity_text
	 * 
	 * @return activity_id
	 */
	
	static function createActivity($activity,$doit_ts,$info,$activity_text,$repeat_interval = 0)
	{
		if ($ah_resource = ActivityHistoryAdapter::createActivityReturnActivityResource($activity, $doit_ts, $info, $activity_text,$repeat_interval))
			return $ah_resource->activity_id;
		return false;
	}
	
	static function createActivityReturnActivityResource($activity,$doit_ts,$info,$activity_text,$repeat_interval = 0)
	{
		$tz = date_default_timezone_get();
		date_default_timezone_set(getProperty("default_server_timezone"));
		$ah_data['activity'] = $activity;
		$ah_data['doit_dt_tm'] = $doit_ts;
		$ah_data['info'] = $info;
		$ah_data['activity_text'] = $activity_text;
		$ah_data['repeat_interval'] = $repeat_interval;
		$ah_resource = Resource::factory(new ActivityHistoryAdapter($mimetypes),$ah_data);
		if ($ah_resource->save())
		{
			date_default_timezone_set($tz);
			$refreshed_resource = $ah_resource->refreshResource($ah_resource->activity_id);
			return $refreshed_resource;
		} 
		date_default_timezone_set($tz);
		return false;
		
	}
	
	public function getAvailableActivityResourcesArray($aha_options = array())
	{					
		if (!$aha_options[TONIC_FIND_BY_METADATA]['locked'])
			$aha_options[TONIC_FIND_BY_METADATA]['locked'] = 'N';
		//$mmha_options[TONIC_FIND_BY_METADATA]['sent_dt_tm'] = '0000-00-00 00:00:00';
		$now_string = date('Y-m-d H:i:s');

		$aha_options[TONIC_FIND_BY_METADATA]['doit_dt_tm'] = array('<='=>$now_string);
		$aha_options[TONIC_SORT_BY_METADATA] = 'doit_dt_tm';
		$message_load = getProperty("worker_message_load");
		$aha_options[TONIC_FIND_TO] = $message_load;
		if ($activity_resources = Resource::findAll($this,'',$aha_options)) {
		    myerror_log("we found activities",5);
            return $activity_resources;
        } else {
            myerror_log("we did not find activities",5);
            return null;
        }


		
	}
	
	/**
	 * 
	 * @desc This will be the method called from the Message workers when we move copletely away from Cron2
	 * @param hashmap $aha_options
	 * @return SplickitActivity
	 */
	public function getNextActivityToDo($aha_options)
	{
		if ($activity_history_resources = $this->getAvailableActivityResourcesArray($aha_options))
		{
			foreach ($activity_history_resources as $activity_history_resource) {
			    myerror_log("we have an activity to check for locked: ".$activity_history_resource->activity_id);
				return $this->getActivityFromUnlockedActivityHistoryResource($activity_history_resource);
			}
		}
		return false;
	}
	
	/**
	 * 
	 * @desc takes an activity_history_resource and first tries to get a lock on the record. then if locked, returns the Activity object
	 * @param Resource $activity_history_resource
	 * @return SplickitActivity
	 */
	public function getActivityFromUnlockedActivityHistoryResource(&$activity_history_resource)
	{
		if ($locked_activity_resource = $this->getLockedActivityResourceFromUnlockedActivityResource($activity_history_resource)) {
			myerror_log("We have the lock so create the activity",5);
			return SplickitActivity::getActivity($locked_activity_resource);
		}
		myerror_log("Could not get lock on activity: ".$activity_history_resource->activity_id);
		return false;
	}

	/**
	 * @desc method to loop through the list of activities ready to be executed. This should only be called fromt the croncron.  and will go away soon
	 */
	public function doAllActivitiesReadyToBeExecuted()
	{
		if ($activity_history_resources = $this->getAvailableActivityResourcesArray($aha_options)) {
			$count = count($activity_history_resources);
			myerror_log("we have found $count activities that are ready to execute. let try to get a lock on one",5);
			foreach ($activity_history_resources as $activity_history_resource) {
				//if ($locked_activity_resource = LockedActivityRetriever::returnLockedActivityObject($activity_resource))
				if ($activity = $this->getActivityFromUnlockedActivityHistoryResource($activity_history_resource)) {
					$activity->executeThisActivity();
				}
				
				//reset the counter
				set_time_limit(30);
			}	
		} else {
		    myerror_log("***********  No activities need to be executed  *************",5);
        }
	}
	
	/**
	 * 
	 * @desc kind of unnessesary.  just calls the Locked activity retriever.
	 * @param Resource $activity_history_resource
	 * @return Resource
	 */
	public function getLockedActivityResourceFromUnlockedActivityResource(&$activity_history_resource)
	{
		return LockedActivityRetriever::returnLockedActivityResource($activity_history_resource);
	}
		
}
?>