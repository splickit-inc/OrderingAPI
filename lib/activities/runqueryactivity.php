<?php

class RunQueryActivity extends SplickitActivity
{
	
	function RunQueryActivity($activity_history_resource)
	{
		$this->activity_history_resource = $activity_history_resource;
	}

	function doit() {
		$sql = $this->activity_history_resource->activity_text;
		$adapter = new MySQLAdapter($this->mimetypes);
		if ($adapter->_query($sql)) {
			return true;
		} else {
			$this->error_text = $adapter->getLastErrorText();
			return false;
		}
	}
	
}
?>