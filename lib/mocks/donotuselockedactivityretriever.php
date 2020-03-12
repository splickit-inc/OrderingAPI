<?php
Class donotuseLockedActivityRetriever
{
	static function findActivityResourceAndReturnLockedActivityObject($activity_options)
	{
		unset($activity_options[TONIC_FIND_TO]);
		$activity_adapter = new ActivityHistoryAdapter($mimetypes); 
		if ($resources = Resource::findAll($activity_adapter,'',$activity_options))
		{
			foreach ($resources as $resource)
			{
				$activity_id = $resource->activity_id;
				$new_stamp = getRawStamp();
				myerror_log("we have an activity to DOIT: ".$resource->activity."  ".$resource->activity_id." so try and get the lock");
				if ($resource->stamp == NULL)
					;//$old_stamp_sql = " AND stamp IS NULL ";
				else if (trim($resource->stamp) == '')
					;//$old_stamp_sql = " AND stamp = ".$resource->stamp." ";
				else 
				{	
					$new_stamp = $message_resource->stamp.';'.getRawStamp();
					$old_stamp_sql = "AND stamp='".$resource->stamp."'";
				}	
				
				$sql = "UPDATE Activity_History SET locked = 'Y',stamp = '$new_stamp',tries = tries+1,modified=NOW() WHERE activity_id = $activity_id AND locked = 'N' $old_stamp_sql";
				myerror_log("the optimistic locking SQL: ".$sql);
				$activity_adapter->_query($sql);
				if (mysqli_affected_rows($activity_adapter->_handle) == 1)
					return SplickitActivity::getActivity($resource);

				myerror_log("Couldn't get lock on activity since other process grabbed it. move on to the next in the list if there is one");
			}
		} else {
			myerror_log("no acitivity to execute");			
			return false;
		}
	}	
}
?>