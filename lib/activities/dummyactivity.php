<?php

class DummyActivity extends SplickitActivity
{
	
	function DummyActivity($activity_history_resource)
	{
		$this->activity_history_resource = $activity_history_resource;
	}

	function doit() {
		myerror_log("executing dummy activity so as to not blow up the loops");
		return false;
	}
	
}
?>