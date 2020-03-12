<?php

class GroupOrderDetailAdapter extends MySQLAdapter
{

	function GroupOrderDetailAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Group_Order_Detail',
			'%([0-9]{4,10})%',
			'%d',
			array('group_order_detail_id'),
			null,
			array('created')
			);
	}

}
?>