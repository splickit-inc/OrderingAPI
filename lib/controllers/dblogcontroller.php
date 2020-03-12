<?php
Class DbLogController extends MessageController
{
		
	function DbLogController($mt,$u,&$r,$l = 0)
	{
		parent::MessageController($mt,$u,$r,$l);		
	}
	
	function send($message)
	{
		if (isLaptop() || isTest())
		{
			// simlulte some delay
			$int = rand(1000, 750000);
			usleep($int);
		}
		return DbMessageLogAdapter::insertRecordFromMessageResource($this->message_resource);
	}
	
}
?>