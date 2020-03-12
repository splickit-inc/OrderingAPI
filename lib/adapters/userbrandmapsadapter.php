<?php

class UserBrandMapsAdapter extends MySQLAdapter
{

	function UserBrandMapsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'User_Brand_Maps',
			'%([0-9]{4,15})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }

    static function incrementOrderCountForUserBrand($user_id,$brand_id)
    {
        $ubma = new UserBrandMapsAdapter(getM());
        if ($ubm_resource = Resource::findOrCreateIfNotExistsByData($ubma,['user_id'=>$user_id,'brand_id'=>$brand_id])) {
            $ubm_resource->number_of_orders = $ubm_resource->number_of_orders + 1;
            $ubm_resource->save();
        }
    }

}
?>