<?php

class UserNoCCFailureAdapter extends MySQLAdapter
{

	function UserNoCCFailureAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'user_no_cc_failure',
			'%([0-9]{5,10})%',
			'%d',
			array('user_id'),
			null,
			array('created')
			);
	}
	
	static function createFailRecord($user_id,$order_distance)
	{
		$unccf_adapter = new UserNoCCFailureAdapter($mimetypes);
		$fail_data = array("user_id"=>$user_id,"skin_id"=>getSkinIdForContext(),"distance_to_store"=>$order_distance);
		$fail_resource = Resource::factory($unccf_adapter,$fail_data);
		try {
			$fail_resource->save();
		} catch (Exception $e) {
			myerror_log("ERROR! could not add row to CC fail table: ".$unccf_adapter->getLastErrorText());
		}
	}
}
?>