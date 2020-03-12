<?php

class UserDeliveryLocationAdapter extends MySQLAdapter
{

	function UserDeliveryLocationAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'User_Delivery_Location',
			'%/userdeliverylocation/([0-9]{3,7})%',
			'%d',
			array('user_addr_id'),
			null,
			array('created','modified')
			);
	}

	function &select($url, $options = NULL)
    {
    	if (is_numeric($url)) {
    		$url = "/userdeliverylocation/$url";
    	} else if (substr_count($url, 'deleteuserdelivery')) {
    		// ok this is messed up. in converting to V2 we do have to do this. hopefuly will completely go away when we sunset V1
    		$url = str_replace('deleteuserdelivery', 'userdeliverylocation', $url);
    	}
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
	
    function update($resource) {
    	UserDeliveryLocationMerchantPriceMapsAdapter::deleteRecordsForUserDeliveryLocationId($resource->user_addr_id);
    	return parent::update($resource);
    }
    
}
?>