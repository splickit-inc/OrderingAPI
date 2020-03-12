<?php
class LockedMessageRetriever
{
	
	static function getLockedMessageResourceForSending($message_resource,$bypass_update = false)
	{
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		if ($message_resource->locked != 'N'  && $message_resource->locked != 'P')
			return false;
		
		//get message id
		$message_map_id = $message_resource->map_id;
		$locked = $message_resource->locked;
		
		$new_stamp = getRawStamp();
		if ($message_resource->stamp === null){
			$old_stamp_sql = " stamp IS NULL ";
		} else {	
			$old_stamp_sql = " stamp='".$message_resource->stamp."'";
		}	
		//$sql = "UPDATE Merchant_Message_History SET locked = 'Y',stamp = CONCAT(IFNULL(stamp,''),'$new_stamp'),tries = tries+1,modified=NOW() WHERE map_id = $message_map_id AND locked = '$locked' AND $old_stamp_sql";
		$sql = sprintf("UPDATE Merchant_Message_History SET locked = 'Y',stamp = CONCAT('%s',';',IFNULL(stamp,'')),tries = tries+1,modified=NOW() WHERE map_id = %s AND locked = '%s' AND %s",$new_stamp,$message_map_id,$locked,$old_stamp_sql);
		myerror_logging(3,"the optimistic locking SQL for message id $message_map_id: ".$sql);
		$mmha->_query($sql);
		if (mysqli_affected_rows($mmha->_handle) == 1)
		{
			//check to see if this is TYPE2 gprs.  if so then override the bypass update
			$format = $message_resource->message_format;
			if (substr($format, 0,1) == 'G' && substr($format,-1) == '2' && $bypass_update == true)
				$bypass_update = false;
			
			if ($bypass_update)
			{
				$sql = "UPDATE Merchant_Message_History SET locked = 'P' WHERE map_id = $message_map_id";
				$mmha->_query($sql);
				myerror_log("WE ARE BYPASSING THE MESSAGE UPDATE.  Probabaly configuring a swap");
			}
			$mmha->setWriteDb();
			$message_resource = Resource::find($mmha,''.$message_map_id);
			if ($bypass_update || ( substr($message_resource->stamp,0,strlen($new_stamp)) == $new_stamp)) {
				return $message_resource;	
			} else {
				myerror_log("Stamp check failed, some other worker grabbed it, probably during a slow down");
				myerror_log("new stamp: $new_stamp    -     message_stamp: ".$message_resource->stamp."    -    message_stamp_trimmed: ".substr($message_resource->stamp,0,strlen($new_stamp)));
			}
					} 
		return false;			
	}
	
}