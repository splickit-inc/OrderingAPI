<?php

class GiftAdapter extends MySQLAdapter
{

	function GiftAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Gift',
			'%([0-9]{4,10})%',
			'%d',
			array('gift_id'),
			null,
			array('created','modified')
		);
						
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
		return parent::select($url,$options);
	}
	
	function getGiftHistory($user_id)
	{
		$gift_data['gifter_user_id'] = $user_id;
		$options[TONIC_FIND_BY_METADATA] = $gift_data;
		$options[TONIC_SORT_BY_METADATA] = 'gift_id DESC';
		$gifts = $this->select('',$options);
		$return_data['user_id'] = $user_id;
		$return_data['gifts'] = $gifts;
		$resource = Resource::dummyFactory($return_data);
		return $resource;
	}

	function claimGift($token)
	{
		if ($token == null || trim($token) == '')
			return returnErrorResource("token cannot be null");
		$gift_data['gift_token'] = $token;
		$options[TONIC_FIND_BY_METADATA] = $gift_data;
		if ($gift_resource = Resource::find($this,'',$options))
		{
			if ($gift_resource->receiver_user_id > 0)
			{
				if ($gift_resource->receiver_user_id == $_SERVER['AUTHENTICATED_USER_ID'])
					return returnErrorResource("You have already claimed your gift, or it was auto claimed for you.  Enter 'usegift' in the promo code field at checkout.");
				else
					return returnErrorResource("This gift has already been claimed by another user");
			}
			else
			{
				$gift_resource->receiver_user_id = $_SERVER['AUTHENTICATED_USER_ID'];
				if ($gift_resource->save())
					return $gift_resource;
				else
					return returnErrorResource("There was an error and the gift could not be claimed");
			}
		} else {
			return returnErrorResource("No matching token");
		}

	}
	
	function createGiftFromRequest($request)
	{
		$gift_data = $request->data;
		myerror_log("*******************");
		foreach ($gift_data as $name=>$value)
			myerror_log("$name=$value");
		myerror_log("*******************");
		return $this->createGift($gift_data);
	}
	
	function createGift($gift_data)
	{
		// verify the gifter has a CC on file and has placed an order with this CC
		$user = $_SERVER['AUTHENTICATED_USER'];
		if ($user['user_id'] < 20000)
			return returnErrorResource("Sorry, this user cannot send gifts. You are very bad!");
		else if (substr($user['flags'], 1,1) != 'C')
			return returnErrorResource("Sorry, You must have a working credit card on file to send a gift.");		
		else if ($gift_data['amt'] < 5)
			return returnErrorResource("Gift amt cannot be less than 5.00");
		else if ($gift_data['receiver_email'] == null || trim($gift_data['receiver_email']) == '')
			return returnErrorResource("receiver_email cannot be null", $error_data);
		else if ($gift_data['expires_on'] == null)
			return returnErrorResource("expires_on date cannont be null");

		$gift_token = generateCode(25);
		$gift_data['gift_token'] = $gift_token;
		
		// check to see if the email exists in our system
		if ($resource = UserAdapter::doesUserExist($gift_data['receiver_email']))
			$gift_data['receiver_user_id'] = $resource->user_id;
		else
			$gift_data['receiver_user_id'] = 0;
		
		$gift_data['gifter_user_id'] = $user['user_id'];
		
		$gift_resource = Resource::factory($this,$gift_data);
		if ($gift_resource->save())
		{
			if ($gift_resource->gift_id > 1000)
			{
				$skin_external_id = $_SERVER['HTTP_X_SPLICKIT_CLIENT_ID'];
				$s = explode('.', $skin_external_id);
				$domain = $s[2];
				
				if (isTest() || isLaptop())
					$domain = $domain.'-test';
				
				 $gift_message = "Great news! ".$user['first_name']." ".$user['last_name']." has given you the opportunity to aquire calories without hunting or gathering, up to $".$gift_data['amt']."! To claim your lunch (or dinner too), just click the link below to create your account. <p>  ".getBaseUrlForContext()."/gifting/claim/".$gift_resource->gift_token." <p>  After you've logged in and built your order, just enter 'usegift' in the promo code at checkout. Enjoy!<p>  Must be used by ".$gift_data['expires_on'];
				 if ($gift_data['receiver_user_id'] > 19999)				  
				 	$gift_message = "Great news!  ".$user['first_name']." ".$user['last_name']." has given you the opportunity to aquire calories without hunting or gathering, up to $".$gift_data['amt']."! Just login, build your order, and enter 'usegift' in the promo code at checkout!  Enjoy!<p>  Must be used by ".$gift_data['expires_on'];

				$gift_resource->set("gifter_name",$user['first_name']." ".$user['last_name']);
					
 			 	$gift_message = $gift_message."<p> Here's a personal note from ".$user['first_name'].":<p>".$gift_data['personal_note'];
				
 			 	$gift_resource->set('gift_message',$gift_message);
				$gift_resource->set('skin_external_identifier',$_SERVER['HTTP_X_SPLICKIT_CLIENT_ID']);
				
				$skin_name = $_SERVER['SKIN']['skin_name'];
				$gift_resource->set('skin',$_SERVER['SKIN']);
				$gift_resource->set('skin_name',$skin_name);
				// now get images
				$skin_images_map_adapter = new SkinImagesMapAdapter($this->mimetypes);
				$skin_option[TONIC_FIND_BY_METADATA]['skin_id'] = $_SERVER['SKIN_ID'];
				$skins_images = $skin_images_map_adapter->select('',$skin_option);
				$skin_images = array_pop($skins_images);
				$gift_resource->set('skin_images',$skin_images);

				$gift_resource->_representation = '/email_templates/email_confirm_sean/gift.html';
				$representation =& $gift_resource->loadRepresentation(getFileAdapter());
				$body = $representation->_getContent();
				//myerror_log("gift body: ".$body);
				
 			 	if (MailIt::sendEmailSingleRecepientWithValidation($gift_data['receiver_email'], "You've got a gift!",$body, $user['first_name'].' '.$user['last_name'], $bcc, $attachments))
				{
					if ($gift_data['receiver_user_id'] > 20000) {
                        PushMessageController::pushMessageToUser($gift_data['receiver_user_id'], $gift_message,getSkinIdForContext());
                    }
					return $gift_resource; // all is good
				}
				else
				{
					// there is a problem with the email
					$gift_resource->logical_delete = 'Y';
					$gift_resource->save();
					return returnErrorResource("There was an error trying to send the gift to the listed email, please check your entry.");
				}	
				
			}
			else
				return returnErrorResource("There was an error and there record was not created. ".$this->getLastErrorText());
		}
		else
			return false;
			
	}
	
	/**
	 * 
	 * @desc gets the soonest expiring gift as a resource
	 * @param int $user_id
	 * @return Resource
	 */
	function getSoonestExpiringGiftResource($user_id)
	{
			$gift_data['receiver_user_id'] = $user_id;
			$gift_data['used_on'] = '0000-00-00 00:00:00';
    		$today = date("Y-m-d");
    		$gift_data['expires_on'] = array(">"=>$today);
			$gift_options[TONIC_FIND_BY_METADATA] = $gift_data;
			$gift_options[TONIC_SORT_BY_METADATA] = " expires_on DESC ";
			if ($resources = Resource::findAll($this,'',$gift_options))
			{
				$soonest_expiring_gift_resource = array_pop($resources);
				return $soonest_expiring_gift_resource;
			} else {
				return false;
			}
	}
}
?>