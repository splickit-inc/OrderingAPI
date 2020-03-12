<?php

class GroupOrderAdapter extends MySQLAdapter
{

	/*
 * Group order type
 * */
	const ORGANIZER_PAY = 1;
	const INVITE_PAY = 2;

	function GroupOrderAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Group_Order',
			'%([0-9]{4,14})%',
			'%d',
			array('group_order_id'),
			null,
			array('created')
			);
	}
	
	function insert($resource)
	{
        $expire_time_stamp = getTimeStampDaysFromNow(2);
        $expire_time = date('Y-m-d H:i:s',$expire_time_stamp);
        myerror_log("setting expires at for group order to be: ".$expire_time);
        $resource->expires_at =  $expire_time_stamp;
        return parent::insert($resource);
	}
}
?>