<?php
class UsersessionController extends SplickitController
{
	
	function UsersessionController($mt,$u,$r,$l = 0)
	{
		parent::SplickitController($mt,$u,$r,$l);
		$this->adapter = new UserAdapter($this->mimetypes);
		//myerror_log("log level in user session controllers constructor is: ".$this->log_level);
	}
	
	function isUserNotAGuest()
	{
		if (!doesFlagPositionNEqualX($this->user['flags'],9,'2')) {
			return true;
		}
		return false;
	}

	/**
	 *
	 * @desc returns a valid user session after authentication.  Must pass in a valid user resource;
	 *
	 * @param Resource $resource
	 */
	function getUserSession($resource = null)
	{
		myerror_logging(3,"*************** starting UsersessionController->getUserSession *************");
		$resource = isset($resource) ? $resource : Resource::find($this->adapter, ''.$this->user['user_id'], $options);
		if ($this->user['user_id'] == 1)
		{
			// admin user access KILL IT!
			myerror_log("ERROR! admin accessing usersessioncontroller!");
			$response = new Response(401);
			$response->body = 'Unauthorized access';
			$response->output();
			die;
		}
		if ($resource->_exists)
		{
			myerror_logging(3,"We have a valid user resource in usersessioncontroll->getUserSesssion()");
				
			// now  update the bio stuff if its a real user and its a newer version (new version check takes place in method
			if ($this->user['user_id'] > 19999  && strtolower($_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE']) != 'ruby' && strtolower($_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID']) != 'hackedthissorry') {
				$this->updateDeviceInfoForUserResource($resource);
			}
		} else {
			myerror_log("ERROR!  CANT CREATE USER RESOURCE IN USERSESSIONCONTROLLER! somethign other than a valid user resource was passed in.");
			$string = Resource::encodeResourceIntoTonicFormat($resource);
			MailIt::sendErrorEmail('COULD NOT CREATE User Resource in UserSessionController', 'COULD NOT CREATE User Resource in UserSessionController for user_id: '.$this->user['user_id'].'.   somethign other than a valid user resource was passed in. \r\n Resource: '.$string);
			return createErrorResourceWithHttpCode("There was an error and your account could not be accessed",500,500);
		}
		if($this->isUserNotAGuest()) {
			
			$resource->set('guest_user', false);
			
			// now get messaging fields
			if ($messaging_records = $this->getMessagingRecordsForContext($this->user['user_id'], $_SERVER['SKIN']['skin_id']))
				$resource->set("messaging_records", $messaging_records);

			// now get delivery locations for this user
			if ($delivery_locations = $this->getUserDeliveryRecords($this->user['user_id'])) {
				$resource->set("delivery_locations", $delivery_locations);
			}

			// now get donation fields
			if ($donation_record = $this->getUserSkinDonationResource($this->user['user_id'], $_SERVER['SKIN_ID'])) {
				$resource->set("donation_active", $donation_record->donation_active);
				$resource->set("donation_type", $donation_record->donation_type);
				$resource->set("donation_amt", $donation_record->donation_amt);
			}

			// now get UserGroups that this user is a memeber of
			$resource->set('user_groups', $this->getUserGroupsFormattedForUserSession());

			// now get social stuff
			$usa = new UserSocialAdapter($this->mimetypes);
			if ($social_record = $usa->getRecord(array("user_id" => $this->user['user_id'])))
				$resource->set("social_settings", $social_record);

			// now get loyalty stuff
			if ($loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext($this->user)) {
				if ($loyalty_account_info = $loyalty_controller->getAccountInfoForUserSession()) {
					$this->setUserBrandLoyaltySessionData($resource, $loyalty_account_info);
				} else if ($this->copyExistingLoyaltyFromTempUserWithSameDeviceId($resource, $_SERVER['BRAND']['brand_id'])) {
					if ($loyalty_account_info = $loyalty_controller->getAccountInfoForUserSession()) {
						$this->setUserBrandLoyaltySessionData($resource, $loyalty_account_info);
					}
				} else if (get_class($loyalty_controller) == "LoyaltyController" || $loyalty_controller->isAutoJoinOn() == true) {
					// homegrown loyalty so create the record
					$loyalty_controller->setLoyaltyData($resource->getDataFieldsReally());
					$user_brand_loyalty_resource = $loyalty_controller->createAccount($resource->user_id, $points);
					$loyalty_account_info = $loyalty_controller->getAccountInfoForUserSession();
					$this->setUserBrandLoyaltySessionData($resource, $loyalty_account_info);
				} else {
					// old loyalty field on user record. remove in th caser of no loyalty for this brand
					unset($resource->loyalty_number);
				}
			} else {
				// old loyalty field on user record. remove in th caser of no loyalty for this brand
// CHANGE THIS    -    get rid of loyalty field on user object
				unset($resource->loyalty_number);
			}

			if ($group_order_record = $this->getActiveGroupOrderRecordForUser($resource->user_id)) {
				$resource->group_order_token = $group_order_record['group_order_token'];
				$resource->group_order_type = $group_order_record['group_order_type'];
			}

			$resource->password = '';
			if ($this->log_level > 3) {
				Resource::encodeResourceIntoTonicFormat($resource);
			}
			//myerror_log("version: ".$_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION']);	
			myerror_logging(2, "************ ending UsersessionController->getUserSession ***************");
			$the_last_four = (string)$resource->last_four;
			while (strlen($the_last_four) < 4) {
				$the_last_four = "0" . $the_last_four;
			}
			$resource->last_four = "$the_last_four";

			$skin = getSkinForContext();
			$resource->set('skin_type', $skin['mobile_app_type']);
			if ($skin['custom_skin_message'] != NULL && trim($skin['custom_skin_message']) != '') {
				if ($skin['custom_skin_message'] != null && $skin['custom_skin_message'] != '') {
					$resource->user_message_title = $skin['skin_name'] . ' Info';
					$resource->user_message = $skin['custom_skin_message'];
				}

			}
			$resource->set("rewardr_active", $skin['rewardr_active']);

			// add philanthropy to the user session
			if ($skin['donation_active'] == 'Y') {
				$skin_charity_info['charity_active'] = 'Y';
				$skin_charity_info['charity_nav_text'] = 'Donations';
				$skin_charity_info['charity_alert_title'] = $skin['donation_organization'];
				$skin_charity_info['charity_alert_body'] = "Want to make a difference? You can donate a small amount with each order to " . $skin['donation_organization'] . " by rounding up your orders to the nearest dollar.\nTap below to be redirected to a Safari page where you can manage your donation preferences.";
				$skin_charity_info['charity_alert_cancel'] = "Cancel";
				$skin_charity_info['charity_alert_okay'] = "Okay";
				$skin_charity_info['charity_web_host_base'] = "order";
				$resource->set("charity_active", 'Y');
				$resource->set("skin_charity_info", $skin_charity_info);
			}
		}else{
			$resource->set('guest_user', true);
		}
		$app_version = getRequestingDevicesAppVersion();
		$device_type = getRequestingDevicesAppType();
		$resource->set("device_type",$device_type);
		$resource->set("app_version",$app_version);
		myerror_logging(3,"version and device type = $device_type  $app_version");
        myerror_logging(3,"current versions android: ".$skin['current_android_version']."  iphone: ".$skin['current_iphone_version']);
		if (($device_type == 'android' && version_compare($app_version, $skin['current_android_version']) < 0) || ($device_type == 'iphone' && version_compare($app_version, $skin['current_iphone_version']) < 0)) {
			$resource->user_message_title = $skin['skin_name'].' Info';
			$resource->user_message = "There is a new version of our app available please download to get access to new features";
		} 
		// if the authentication token has been set return existing otherwise create it
		if ($authentication_token = getProperty('splickit_authentication_token')) {
			$resource->set('splickit_authentication_token',$authentication_token);
		} else {
			//set duration of token to be 12 hours unless remember me and set to 2 weeks
			$duration_in_seconds = $this->request->data['remember_me'] == 1 ? (180*24*60*60)  :43200;
			$authentication_token_resource = createUserAuthenticationToken($resource->user_id,$duration_in_seconds);
			$resource->set('splickit_authentication_token',$authentication_token_resource->token);
			$resource->set('splickit_authentication_token_expires_at',$authentication_token_resource->expires_at);
		}

        //set the new privs
        $this->setUserPrivilegesOnUserSession($resource);
		if (!isLoggedInUserATempUser()) {
			if ($resource->contact_no == null || trim($resource->contact_no) == '') {
				$resource->user_message = "Alert! You do not have a valid phone number on record. Please update it. ".$resource->user_message;
			}
		}
		return $resource;
	}

    function setUserPrivilegesOnUserSession(&$user_resource)
    {
        $privileges['caching_action'] = $user_resource->caching_action;
        $privileges['ordering_action'] = $user_resource->ordering_action;
        $privileges['send_emails'] = ($user_resource->send_emails == '1') ? true : false;
        $privileges['see_inactive_merchants'] = ($user_resource->see_inactive_merchants == '1') ? true : false;
        $privileges['see_demo_merchants'] = ($user_resource->see_demo_merchants == '1') ? true : false;
        $user_resource->set("privileges", $privileges);
        unset($user_resource->caching_action);
        unset($user_resource->ordering_action);
        unset($user_resource->send_emails);
        unset($user_resource->see_inactive_merchants);
        unset($user_resource->see_demo_merchants);
    }
	
	private function setUserBrandLoyaltySessionData(&$user_session_resource,$info)
	{
		if ($user_message = $info['user_message']) {
			if ($user_session_resource->user_message) {
				$user_session_resource->user_message = $user_message." ".$user_session_resource->user_message;
			} else {
				$user_session_resource->set("user_message",$user_message);
			}
		}
		if ($loyalty_number = $info['loyalty_number']) {
			unset($info['history']);
			$info['loyalty_points'] = $info['points'];
			if (! isset($info['usd'])) {
				$info['usd'] = $info['dollar_balance'];
			}
			$info['loyalty_transactions'] = array_slice($info['loyalty_transactions'], 0, 5);
	    	$user_session_resource->set("loyalty_number",$loyalty_number);
			$user_session_resource->set("points_current",$info['points']);
		    $user_session_resource->set("brand_points",$info['points']);
	   		$user_session_resource->set("brand_loyalty_history",$info['loyalty_transactions']);
	   		$user_session_resource->set("brand_loyalty",$info);
		}
   		return true;
	}
	
	private function getUserDeliveryRecords($user_id)
	{
		$udla = new UserDeliveryLocationAdapter($this->mimetypes);
		if ($delivery_locations = $udla->getRecords(array("user_id"=>$user_id)))
			return $delivery_locations;
		
		return false;
	}
	
	private function getMessagingRecordsForContext($user_id,$skin_id)
	{
		// now get messaging fields
		$umsma = new UserMessagingSettingMapAdapter($this->mimetypes);
		$extra_options[TONIC_FIND_BY_METADATA]['user_id'] = $user_id;
		$extra_options[TONIC_FIND_BY_METADATA]['skin_id'] = $skin_id;
		if ($messaging_records = $umsma->select('',$extra_options))
		{
			return $messaging_records;
		} else if ($_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'] != 'web') {
			
// CHANGE THIS   only needed as long as there are significant numbers of user on 3.4
			// fixing the 3.4 bug!
			unset($extra_options[TONIC_FIND_BY_METADATA]['user_id']);
			$extra_options[TONIC_FIND_BY_METADATA]['device_id'] = $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'];
			$extra_options[TONIC_SORT_BY] = ' map_id desc';
			if ($_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'] == null || $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'] == '')
				;// skip it
			else if ($messaging_records = $umsma->select('',$extra_options))
			{
				$most_recent_record = $messaging_records[0];
				unset($most_recent_record['map_id']);
				$most_recent_record['user_id'] = $user_id;
				$most_recent_record['created'] = time();
				$new_messageing_resource = Resource::factory($umsma,$most_recent_record);
				if ($new_messageing_resource->save())
				{
					myerror_log("we have successfully ported over the push token to account for the 3.4 bug");
					$id = $umsma->_insertId();
					$the_new_records = $umsma->getRecords(array('map_id'=>$id));
					return $the_new_records;
				}
				else
					myerror_log("ERROR!  Could not port over push token!");
			}
		}
		myerror_logging(3, "There were no messaging records found");
		return false;
	}
	
	private function copyExistingLoyaltyFromTempUserWithSameDeviceId($user_resource,$brand_id)
	{
		if ($brand_id < 1) {
			return false;
		}
		if (isEmailASplickitTempUserEmail($user_resource->email)) {
			return false;
		}
    	$device_id = $user_resource->device_id;
    	if ($device_id != null && trim($device_id) != '')
    	{	    		
    		myerror_log("starting the temp user fix for loyalty in copy existing");
    		$data['device_id'] = $device_id;
    		$data['first_name'] = 'SpTemp';
    		$data['last_name'] = 'User';
    		$user_adapter = new UserAdapter($mimetypes);
    		// this could throw an exception if there is more than one temp user with the same device id.  
    		// shoudl never happen since email is the device_id@splickit.dum
			try {    		
	    		if ($record = $user_adapter->getRecord($data))
	    		{
	    			myerror_log("we found a temp user, now check to see if has a loyalty record");
	    			$ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
	    			if ($user_brand_points_map_resource = $ubpm_adapter->getExactResourceFromData(array("user_id"=>$record['user_id'],"brand_id"=>$brand_id))) {
	    				$loyalty_number = $user_brand_points_map_resource->loyalty_number;
	    				if ($loyalty_number == null || trim($loyalty_number) == '') {
	    					myerror_log("ERROR! saved loyalty number on temp user is NULL");
	    				} else {
	    					myerror_log("YES! WE HAVE AN EXISTING LOYALTY NUMBER!  so now port the number over to he new user");
	    					// NOTE:  should we just be changing the user_id on the existing record rather than creating a new record?
	    					$user_brand_points_map_resource->_exists = false;
	    					unset($user_brand_points_map_resource->map_id);
	    					$user_brand_points_map_resource->user_id = $user_resource->user_id; // the logged in user_id;
	    					// will insert the record as NEW
	    					return $user_brand_points_map_resource->save();
	    				}
	    			} else {
	    				myerror_logging(3, 'No user brand points map record for the temp user so we have nothing to port over');
	    			}
	    		} else {
	    			myerror_logging(3, 'No temp user so we do not need to check if there is loyalty to port over');
	    		}
    		} catch (MoreThanOneMatchingRecordException $e) {
    			myerror_log("we had more than one row returned for trying to find temp user loyalty copy existing");
    			myerror_log("skipping the copy exising");
    			MailIt::sendErrorEmailAdam("duplicate temp user problem", "for copy existing more than one record returned for device_id: ".$device_id);
    		}
    	} else {
    		myerror_logging(3,"no device id so skip the temp user checks");
    	}
    	return false;
	}
	
	private function getUserBrandLoyaltyResource($user_resource,$brand_id)
	{
		$user_id = $user_resource->user_id;
    	$ubl_adapter = new UserBrandPointsMapAdapter($mimetypes);
    	if ($user_brand_loyalty_resource = $ubl_adapter->getExactResourceFromData(array("user_id"=>$user_id,"brand_id"=>$brand_id)))
    	{
	    	$loyalty_number = $user_brand_loyalty_resource->loyalty_number;
	    	myerror_logging(3,"the loyalty number is: ".$loyalty_number);
	    	if ($loyalty_number == NULL || trim($loyalty_number) == '')
	    	{
	    		myerror_log("ERROR! saved loyalty number is NULL!  SHOULD WE DELETE IT?");
	    		unset($user_brand_loyalty_resource);
	    	}
    	} else {
    		// now do the temp user check
    		// must have a device id
    		$device_id = $user_resource->device_id;
    		if ($device_id != null && trim($device_id) != '')
    		{
	    		myerror_log("starting the temp user fix for loyalty in getUserBrandLoyaltyResource()");
	    		$data['device_id'] = $device_id;
    			$data['first_name'] = 'SpTemp';
    			$data['last_name'] = 'User';
    			$user_adapter = new UserAdapter($mimetypes);
    			// this could throw an exception if there is more than one temp user with the same device id.
    			// shoudl never happen since email is the device_id@splickit.dum
    			try {
	    			if ($record = $user_adapter->getRecord($data))
	    			{
	    				myerror_log("we found a temp user, now check to see if has a loyalty record");
	    				if ($user_brand_loyalty_resource = $ubl_adapter->getExactResourceFromData(array("user_id"=>$record['user_id'],"brand_id"=>$brand_id)))
	    				{
	    					$loyalty_number = $user_brand_loyalty_resource->loyalty_number;
	    					if ($loyalty_number == null || trim($loyalty_number) == '')
	    					{
	    						myerror_log("ERROR! saved loyalty number on temp user is NULL");
	    						unset($user_brand_loyalty_resource);
	    					}
		    				else
		    				{
		    					myerror_log("YES! WE HAVE AN EXISTING LOYALTY NUMBER!  so now port the number over to he new user");
		    					$user_brand_loyalty_resource->_exists = false;
		    					unset($user_brand_loyalty_resource->map_id);
		    					$user_brand_loyalty_resource->user_id = $user_id; // the logged in user_id;
		    					
		    					// will insert the record as NEW
		    					$user_brand_loyalty_resource->save();
		    				}
	    				}
	    			}
    			} catch (MoreThanOneMatchingRecordException $e) {
    				myerror_log("we had more than one row returned for trying to find temp user loyalty stuff so we will skip.");
    				MailIt::sendErrorEmailAdam("duplicate temp user problem", "more than one record returned for device_id: ".$device_id);
    			}
    		} else {
    			myerror_logging(3,"no device id so skip the temp user checks");
    		}
    	}
    	return $user_brand_loyalty_resource;
	}
	
	private function getUserSkinDonationRecord($user_id, $skin_id)
	{
		$usda = new UserSkinDonationAdapter($this->mimetypes);
		$data['user_id'] = $user_id;
		$data['skin_id'] = $skin_id;
		$data['donation_active'] = 'Y';
		if ($donation_record = $usda->getRecord($data))
			return $donation_record;
		else
			return false;		
	}
	
	private function getUserSkinDonationResource($user_id,$skin_id)
	{
		$user_skin_donation_resource = UserSkinDonationAdapter::getDonationResourceForUserAndSkin($user_id, $skin_id);
		
		if ($user_skin_donation_resource == null && getContext() == 'com.splickit.rlc') {
			UserSkinDonationAdapter::setDonationResourceForUserAndSkin($user_id, $skin_id, 'Y');
			$user_skin_donation_resource = UserSkinDonationAdapter::getDonationResourceForUserAndSkin($user_id, $skin_id);
		}
		
		if ($user_skin_donation_resource != null) {
			return $user_skin_donation_resource;
		} else {
			return false;
		}
	}
	
	function updateDeviceInfoForUserResource(&$resource)
	{
		if (strtolower($_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE']) != 'web' && ($resource->app_version < $_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'] || $resource->device_id != $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID']))
		{
			$resource->device_id = $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'];
			$resource->device_type = $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'];
			$resource->app_version = $_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'];
			$resource->skin_name = $_SERVER['HTTP_X_SPLICKIT_CLIENT_ID'];
			$resource->modified = time();
			if (isLoggedInUserATempUser()) {
				$resource->email = $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'].'@splickit.dum';
			}
			$resource->save();
		}
		
	}
	
	private function getUserGroupsFormattedForUserSession()
	{
		
		if ($user_groups = UserGroupsAdapter::getAllGroupsInformationThatThisUserIsAMemeberOf($this->user['user_id'])) {
			cleanData($user_groups);
		} else {
			$user_groups = array();
		}
		return $user_groups;
	}
	
	function getActiveGroupOrderTokenForUserSessionFromUserId($user_id)
	{
		if ($group_order_record = $this->getActiveGroupOrderRecordForUser($user_id)) {
			return $group_order_record['group_order_token'];
		}
	}

	function getActiveGroupOrderRecordForUser($user_id)
	{
		$group_order_adapter = new GroupOrderAdapter($mimetypes);
		$current_time = time();
		$options[TONIC_FIND_BY_METADATA]['admin_user_id'] = $user_id;
		$options[TONIC_FIND_BY_METADATA]['status'] = 'active';
		$options[TONIC_FIND_BY_STATIC_METADATA] = " expires_at > $current_time ";
		$options[TONIC_SORT_BY_METADATA] = " expires_at DESC ";
		if ($group_order_records = $group_order_adapter->select(null,$options)) {
			return $group_order_records[0];
		}
	}
}

?>
