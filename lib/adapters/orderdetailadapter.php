<?php

class OrderDetailAdapter extends MySQLAdapter
{

	function OrderDetailAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'`Order_Detail`',
			'%([0-9]{1,15})%',
			'%d',
			array('order_detail_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	if (!isset($options[TONIC_FIND_BY_METADATA]['logical_delete'])) {
            $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
        }
    	return parent::select($url,$options);
    }

    function &update(&$resource)
    {
        if ($resource->logical_delete == 'Y') {
            return parent::update($resource);
        }
    	$resource->set('error','method not allowed');
    	return false;
    }

     function logicallyDeleteOrderDetailItem($order_detail_id)
    {
        if ($order_detail_resource = Resource::find($this,"$order_detail_id")) {
            $order_detail_resource->logical_delete = 'Y';
            if ($order_detail_resource->save()) {
                return $order_detail_resource->getDataFieldsReally();
            } else {
                myerror_log("ERROR!  could not set logical delete on item! ".$order_detail_resource->adapter->getLastErrorText());
                return false;
            }
        } else {
            $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'Y';
            if ($order_detail_resource = Resource::find($this,"$order_detail_id",$options)) {
                // ok it was deleted already so just return the record
                return $order_detail_resource->getDataFieldsReally();
            }
        }
        return false;

    }

    function &insert($resource)
    {
    	$resource->set('error','method not allowed');
    	return false;
    }
	
}
?>