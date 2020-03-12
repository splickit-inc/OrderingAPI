<?php

class GroupOrderIndividualOrderMapsAdapter extends MySQLAdapter
{

	function GroupOrderIndividualOrderMapsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Group_Order_Individual_Order_Maps',
			'%([0-9]{4,15})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
		
		$this->allow_full_table_scan = false;
						
	}
	
	function &select($url, $options = NULL)
    {
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
	}

	function addCompletedOrderToGroup($order_id,$group_order_id)
	{
		return $this->addSubmittedOrderToGroup($order_id,$group_order_id);
	}

	function addSubmittedOrderToGroup($order_id,$group_order_id)
	{
		$group_order_individual_map_resource = $this->getOrCreateGroupOrderIndividualMapResource($order_id,$group_order_id);
		$group_order_individual_map_resource->status = 'Submitted';
		$group_order_individual_map_resource->save();
		return $group_order_individual_map_resource;
	}

	function getOrCreateGroupOrderIndividualMapResource($order_id,$group_order_id)
	{
		if ($order_id == null || $group_order_id == null) {
			myerror_log("ERROR !!!!  tring to add group order map record but one of these is null.   order_id: $order_id,   group_order_id: $group_order_id");
			throw new Exception("Problem creating grouop order map record. One of these is null.   order_id: $order_id,   group_order_id: $group_order_id");
		}
		$data = array();
		$data['user_order_id'] = $order_id;
		$data['group_order_id'] = $group_order_id;
		return Resource::findOrCreateIfNotExistsByData($this,$data);
	}

	static function addOrderToGroup($order_id,$group_order_id)
	{
		if ($order_id == null && $group_order_id == null) {
			myerror_log("ERROR !!!!  tring to add group order map record but one of these is null.   order_id: $order_id,   group_order_id: $group_order_id");
			throw new Exception("Problem creating grouop order map record. One of these is null.   order_id: $order_id,   group_order_id: $group_order_id");
		}

		$gom = new GroupOrderIndividualOrderMapsAdapter($m);
		$data = array();
		$data['user_order_id'] = $order_id;
		$data['group_order_id'] = $group_order_id;
		$data['status'] = 'In Process';
		return Resource::createByData($gom,$data);
	}

	function getSubmittedChildRecordsBasedOnGroupOrderToken($group_order_token)
	{
		return $this->getChildRecordsBasedOnGroupOrderToken($group_order_token,true);
	}
	
	function getChildRecordsBasedOnGroupOrderToken($group_order_token,$submitted = false)
	{
		if ($group_order_record = GroupOrderAdapter::staticGetRecord(array("group_order_token"=>$group_order_token),'GroupOrderAdapter')) {
			$data = array("group_order_id"=>$group_order_record['group_order_id']);
			if ($submitted) {
				$data['status'] = 'Submitted';
			}
			if ($group_order_record['group_order_type'] == 2) {
				return $this->getRecords($data);
			} else {
				throw new Exception("Cant get child orders for Group oder as its not type = 2");
			}
		} else {
			throw new Exception("Cant find group order with token:  $group_order_token");
		}
	}
}
?>