<?php

class CateringOrdersAdapter extends MySQLAdapter
{

	function CateringOrdersAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Catering_Orders',
			'%([0-9]{4,10})%',
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

	function setCateringOrderToSubmitted($order_id)
    {
        $options[TONIC_FIND_BY_METADATA]['order_id'] = $order_id;
        if ($resource = Resource::find($this,null,$options)) {
            $resource->status = 'Submitted';
            $resource->save();
        }
    }

    static function getActiveFutureCateringOrdersByMerchantId($merchant_id,$current_time_stamp)
    {
        $sql = "SELECT a.* FROM Catering_Orders a JOIN Orders b ON a.order_id = b.order_id WHERE b.merchant_id = $merchant_id AND a.timestamp_of_event > $current_time_stamp AND a.status != 'Cancelled'";
        $options[TONIC_FIND_BY_SQL] = $sql;
        return Resource::findAll(new CateringOrdersAdapter(),null,$options);
    }

}
?>