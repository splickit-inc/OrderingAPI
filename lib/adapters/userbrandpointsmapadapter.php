<?php

class UserBrandPointsMapAdapter extends MySQLAdapter
{

	function UserBrandPointsMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'User_Brand_Points_Map',
			'%([0-9]{3,10})%',
			'%d',
			array('map_id'),
			null,
			array('created','modified')
			);
		
		//$this->allow_full_table_scan = true;
						
	}
/*	
	function &select($url, $options = NULL)
    {
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
	}
*/	
	/**
	 * 
	 * @desc used to create a new user loyalty account that is NOT attached to an outside service.  
	 * @param int $user_id
	 * @param int $brand_id
	 * @param alphanumeric $loyalty_number
	 * @param int $points
	 * @return Resource
	 */
	function createUserLoyaltyAccount($user_id,$brand_id,$loyalty_number,$points = 0, $phone_number = null)
	{
		return $this->addUserLoyaltyRecord($user_id, $brand_id, $loyalty_number, $points, $phone_number);
	}
	
	/**
	 * 
	 * @desc will first try to update an existing row, if it doesn't exist then it will add a row to the User_Brand_Loyalty_Table
	 * @param unknown_type $user_id
	 * @param unknown_type $brand_id
	 * @param unknown_type $loyalty_number
	 * @param unknown_type $points
	 * @return Resource
	 */
	function addUserLoyaltyRecord($user_id,$brand_id,$loyalty_number,$points)
	{
		if ($user_brand_points_map_resource = $this->getExactResourceFromData(array("user_id"=>$user_id,"brand_id"=>$brand_id))) {
			if ($loyalty_number) {
				$user_brand_points_map_resource->loyalty_number = $loyalty_number;
			}
			if ($points) {
				$user_brand_points_map_resource->points = $points;
			}
			$user_brand_points_map_resource->save();

			// since phone number has to be unique with brand on the loyalty records, we need to update it separately to not blow up a loyalty insert or update.
			if ($phone_number != null && trim($phone_number) != '') {
				$user_brand_points_map_resource->phone_number = $phone_number;
				$user_brand_points_map_resource->save();
			}
		} else if (! $user_brand_points_map_resource = Resource::createByData($this, array("brand_id"=>$brand_id,"user_id"=>$user_id,"loyalty_number"=>$loyalty_number,"points"=>$points,"phone_number"=>$phone_number))) {
   			MailIt::sendErrorEmail("there was a problem creating the User Brand Loyalty Record on User create", "sql error: ".$this->getLastErrorText());
   			return false;
   		}		
   		return $user_brand_points_map_resource;
	}
	
	function getOrCreateUserBrandPointsResource($user_id,$brand_id)
	{
		$data['user_id'] = $user_id;
		$data['brand_id'] = $brand_id;
		$options[TONIC_FIND_BY_METADATA] = $data;
		if ($resource = Resource::find($this,'',$options)) {
			return $resource;
		} else {
			$resource = Resource::factory($this,$data);
			if ($resource->save()) {
				return $resource;
			} else {
				myerror_log("we had an error creating the brand points data: ".$this->getLastErrorText());
			}
		}
	}
	
	function recordBrandPointsSaveError()
	{
		$error = $this->getLastErrorText();
		myerror_log("we had an error saving the brand points data: ".$error);
		recordError($error, "we had an error saving the brand points data");
	}
	
	function addPointsToUserBrandPointsRecord($user_id,$brand_id,$points)
	{
		//$user_brand_points_map_resource = $this->getOrCreateUserBrandPointsResource($user_id, $brand_id);
		myerror_log("about to get the user brand points map record");
		if ($user_brand_points_map_resource = $this->getExactResourceFromData(array('user_id'=>$user_id,'brand_id'=>$brand_id)))
		{
			myerror_log("we have the record. currnet point value is: ".$user_brand_points_map_resource->points);
			myerror_log("about to add $points to that value");
			$new_points = $user_brand_points_map_resource->points + $points;
			$user_brand_points_map_resource->points = $new_points;
			if ($user_brand_points_map_resource->save()) {
				return $user_brand_points_map_resource;
			} else {
				myerror_log("bad save!");
				$this->recordBrandPointsSaveError();
			}
		} else {
			myerror_log("COULDN'T FIND THE USER BRAND POINTS MAP RECORD");
		}
	}
	
	function setPointsOnUserBrandPointsRecord($user_id,$brand_id,$points)
	{
		$user_brand_points_map_resource = $this->getOrCreateUserBrandPointsResource($user_id, $brand_id);
		$user_brand_points_map_resource->points = $points;
		if ($user_brand_points_map_resource->save()) {
			return $user_brand_points_map_resource;
		} else {
			$this->recordBrandPointsSaveError();
		}
	}

    static function getUserBrandPointsMapRecordForUserBrandCombo($user_id,$brand_id)
    {
        $uboma = new UserBrandPointsMapAdapter($m);
        $data['user_id'] = $user_id;
        $data['brand_id'] = $brand_id;
        $options[TONIC_FIND_BY_METADATA] = $data;
        if ($resource = Resource::find($uboma,'',$options)) {
            return $resource;
        }

    }

    static function getUserBrandPointsMapRecordForLoyaltyNumberBrandCombo($loyalty_number,$brand_id)
    {
        $uboma = new UserBrandPointsMapAdapter($m);
        $data['loyalty_number'] = $loyalty_number;
        $data['brand_id'] = $brand_id;
        $options[TONIC_FIND_BY_METADATA] = $data;
        if ($resource = Resource::find($uboma,'',$options)) {
            return $resource;
        }

    }

}
?>