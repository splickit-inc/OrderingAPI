<?php
Class LockedActivityRetriever
{
	static function returnLockedActivityResource(&$activity_resource)
	{
	    myerror_log('starting trying to get lock on activity id: '.$activity_resource->activity_id);
	    logData($activity_resource->getDataFieldsReally(),'Activity History Resource',5);
		if ($activity_resource->locked != 'N') {
            return false;
        }

		
		//get activity resoiurce id
		$activity_resource_id = $activity_resource->activity_id;
		
		$new_stamp = getRawStamp();
		$existing_stamp = $activity_resource->stamp;
		if ( $existing_stamp === null) {
			$old_stamp_sql = " stamp IS NULL ";
		} else {	
			$old_stamp_sql = " stamp='".$activity_resource->stamp."'";
		}	
		$sql = sprintf("UPDATE Activity_History SET locked = 'Y',stamp = CONCAT('%s',';',IFNULL(stamp,'')),tries = tries+1,modified=NOW() WHERE activity_id = %s AND locked = 'N' AND %s",$new_stamp,$activity_resource_id,$old_stamp_sql);
		myerror_log("the optimistic locking SQL for activity is: ".$sql,5);
		$aha = new ActivityHistoryAdapter(getM());
		$aha->_query($sql);
		if (mysqli_affected_rows($aha->_handle) == 1)
		{
		    myerror_log("we ahve the locked activity now",3);
			$locked_activity_resource = $activity_resource->refreshResource($activity_resource_id);
			if ( substr($locked_activity_resource->stamp,0,strlen($new_stamp)) == $new_stamp) {
				return $locked_activity_resource;
			} else {
				myerror_log("Stamp check failed, some other worker grabbed it, probably during a slow down"); 
			}
		} else {
            myerror_log("OPTOMISTIC LOCKING FAILED for sql: ".$sql);
		    myerror_log("ERROR: ".$aha->getLastErrorText());
        }
		return false;			
	}
}
?>