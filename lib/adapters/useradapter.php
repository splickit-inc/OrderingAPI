<?php

class UserAdapter extends MySQLAdapter
{

	private $ENCRYPT_KEY="z1Mc6KRxA7Nw90dGjY5qLXhtrPgJOfeCaUmHvQT3yW8nDsI2VkEpiS4blFoBuZ";
	private $user_id;
	private $too_many_failed_loyalty_attempts_message = 'Sorry, but your account is now locked out of loyalty for 1 hour due to too many failed attempts.';
	
	var $fish_bowl_error;
	const BIRTHDAY_ERROR_MESSAGE =  "Sorry, birthday must be in the form of mm/dd/YYYY. please try again";
	
	function UserAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'User',
			'%([0-9]{1,15})%',
			'%d',
			array('user_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	if ($options[TONIC_FIND_BY_METADATA]['logical_delete'] == null && $options[TONIC_FIND_BY_METADATA]['user_id'] != 9999 && $url != "9999") {
    		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	}
		return parent::select($url,$options);
	}

	function shouldSendLiveOrder($user_resource) {
	  $user_resource = (object)$user_resource;
	  return ($user_resource->ordering_action == 'send');
	}
	
	function shouldSendTestOrder($user_resource) {
	  $user_resource = (object)$user_resource;
	  return ($user_resource->ordering_action == 'test');
	}
	
	function shouldSendEmail($user_resource) {
	  $user_resource = (object)$user_resource;
	  return ($user_resource->send_emails == true);
	}
	
	function shouldSeeInactiveMerchants($user_resource) {
	  $user_resource = (object)$user_resource;
	  return ($user_resource->see_inactive_merchants == 1) || ($user_resource->see_inactive_merchants == true);
	}
	
	function shouldSeeDemoMerchants($user_resource) {
	  $user_resource = (object)$user_resource;
	  return ($user_resource->see_demo_merchants == true);
	}
	
	function shouldBypassCache($user_resource) {
	  $user_resource = (object)$user_resource;
	  return ($user_resource->caching_action == 'bypass' || $this->shouldRefreshCache($user_resource));
	}
	
	function shouldRefreshCache($user_resource) {
	  $user_resource = (object)$user_resource;
    return ($user_resource->caching_action == 'refresh');
	}
	
	function shouldRespectCache($user_resource) {
	  $user_resource = (object)$user_resource;
	  return ($user_resource->caching_action == 'respect');
	}
	
	function getTooManyFailsLoyaltyMessage($brand_id)
	{
		return $this->too_many_failed_loyalty_attempts_message;
	}
		
	function deleteUserCCVaultInfoAndGetNewFlagsFromUserResource($user_resource)
	{
		return $this->deleteUserCCVaultInfoAndGetNewFlags($user_resource->uuid, $user_resource->flags);
	}
	
	function deleteUserCCVaultInfoAndGetNewFlagsFromUserId($user_id)
	{
		$record = $this->getRecord(array("user_id"=>$user_id));
		return $this->deleteUserCCVaultInfoAndGetNewFlags($record['uuid'], $record['flags']);
	}
	
	function deleteUserCCVaultInfoAndGetNewFlags($uuid,$flags)
	{
		$splickit_vault = new SplickitVault();
		if ($splickit_vault->deleteVaultRecord($uuid)) {
			return $this->resetFlagsToBreakCCconnection($flags);
		}
		return false;	
		
	}
		
	function resetFlagsToBreakCCconnection($flags)
	{
		$new_flags = substr($flags,0,1)."000".substr($flags,4);
		return $new_flags;
	}

    function setFlagPosition($flags,$position,$value)
    {
        $run = $position-1;
        $string_length = strlen($value);
        $final_position = $position+$string_length-1;
        return substr($flags,0,$run)."$value".substr($flags,$final_position);
    }

	function setFlagsForSavedCreditCard($flags)
    {
        return $this->setFlagPosition($flags,2,'C21');
    }
	
	function update(&$resource)
	{
   		$this->user_id = $resource->user_id;
   		myerror_logging(4, "starting the update in useradapter");

		// need to do this since if nothing changes the update will comeback false and then try to insert the record.
		$resource->modified = time();
  		
		if ($user_social_data = $resource->user_social)
		{
			$user_social_options[TONIC_FIND_BY_METADATA]['user_id'] = $resource->user_id;
			$user_social_adapter = new UserSocialAdapter($mimetypes);
			if ($user_social_resource = Resource::findExact($user_social_adapter,'',$user_social_options))
			{
				$user_social_map_id = $user_social_resource->social_map_id;
				foreach ($user_social_data as $field => $value) {
					$user_social_resource->set($field, $value);
				}
			} else {
				$user_social_data['user_id'] = $resource->user_id;			
				$user_social_resource = Resource::factory($user_social_adapter,$user_social_data);
			}
			if ($user_social_resource->save())
			{
				if (!$user_social_map_id)
					$user_social_map_id = $user_social_adapter->_insertId();//all is good
				$resource->set("user_social_id",$user_social_map_id);
			} else {
				MailIt::sendErrorEmail("ERROR SAVING USER SOCIAL INFO", "sql error: ".$user_social_adapter->getLastErrorText());
				return returnErrorResource("Sorry there was a problem and the social data was not updated");
			}

		}
		// next to CC info if it exists
		if (substr(strtolower($resource->delete_cc_info),0,1) == 'y') {
			$resource->flags = $this->deleteUserCCVaultInfoAndGetNewFlagsFromUserId($resource->user_id);
		} else if (isset($resource->cc_number)) {
			if (isLoggedInUserATempUser()) {
				myerror_log("ERROR! TEMP USER SAVING CC!");
				MailIt::sendErrorEmail("TEMP USER ATTEPTING TO ADD A CC", "Temp user adding a cc.  check it out");
				return setErrorOnResourceReturnResource($resource,"We're sorry, but your session has gotten corrupted. Please log out and start over. We apologize for the inconvenience.", 999);
			}
			$credit_card_functions = new CreditCardFunctions();
			if (! $credit_card_functions->cc_save($resource))
			{
				unset($resource->cc_number);
				// ok this is kinda messed up but we have to return the resource here so that the user save doesn't bomb on a bad CC.  the error will be attached to the resource so it will be presented to the user.
				return $resource;
			}
			$resource->vault_process = $credit_card_functions->splickit_vault_save_process;
			unset($resource->cc_number);
		} else if (isset($resource->credit_card_saved_in_vault)) {
            // new remote setting of CC number
            $vio_payment_service = new VioPaymentService($data);
            if (isset($resource->credit_card_token_single_use )  && $resource->credit_card_saved_in_vault == false) {
                if ($last_four = $vio_payment_service->getLast4FromVioForUUID($resource->credit_card_token_single_use)) {
                    $identifier = $vio_payment_service->response_in_data_format['credit_card']['identifier'];
                    $vio_payment_service->deleteVaultRecord($resource->uuid);
                    sleep(1); // need to sleep because the update is coming too quickly before the delete has time to propogate to all the servers. hopefully this fixes the problem.
                    $response = $vio_payment_service->updateExistingRecordWithNewUUID($identifier,$resource->uuid);
                    if ($response['status'] == 'success') {
                        $resource->last_four = substr($response['credit_card']['number'],-4);
                        $resource->flags = $this->setFlagsForSavedCreditCard($resource->flags);
                    } else {
                        $resource->flags = $this->resetFlagsToBreakCCconnection($resource->flags);
                        return setErrorOnResourceReturnFalse($resource,"The credit card information did not get saved",500);
                    }
                } else {
                    $resource->flags = $this->resetFlagsToBreakCCconnection($resource->flags);
                    return setErrorOnResourceReturnFalse($resource,"The credit card information did not get saved",500);
                }
            } else if ($resource->credit_card_saved_in_vault == true){
                if ($resource->last_four = $vio_payment_service->getLast4FromVioForUUID($resource->uuid)) {
                    $resource->flags = $this->setFlagsForSavedCreditCard($resource->flags);
                } else {
                    $resource->flags = $this->resetFlagsToBreakCCconnection($resource->flags);
                    return setErrorOnResourceReturnFalse($resource,"The credit card information did not get saved",999);
                }
            }
			if (!CreditCardUpdateTrackingAdapter::recordCreditCardUpdateAndCheckForBlacklisting($resource->user_id,$resource->device_id,$resource->last_four)) {
				return setErrorOnResourceReturnFalse($resource,"The credit card information did not get saved",999);
			}
        }
			
	   	if ($resource->user_id > 19999) 
	   	{
	    	if ($resource->email)
	    	{
	    		if (! filter_var($resource->email, FILTER_VALIDATE_EMAIL)) {
		    		myerror_log("BAD EMAIL ADDRESS ON USER UPDATE: ".$resource->email);
					$resource->password='';
					return setErrorOnResourceReturnFalse($resource, 'Sorry but the email address you entered is not valid', 10);
				}
	    		$resource->email = strtolower($resource->email);
	    	}
	   	}

	   	//now verify loyalty but only when doing a user update which is set in teh UserController update user method.
	   	if (getProperty('is_a_user_update') == 'true') {
	   		myerror_logging(3, "we have a user update so check for loyalty on");
	   		if ($loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext($resource->getDataFieldsReally())) {
				if ($loyalty_controller->isNonRemoteLoyalty()) {
				    //we will now bypass update till after user save since we're still technically a temp user if we are in an temp user conversion situation
				} else if (!$loyalty_controller->createOrLinkAccountFromDataResource($resource)) {
		   			return setErrorOnResourceReturnFalse($resource, $loyalty_controller->getServiceResponseError(), $loyalty_controller->getServiceResponseErrorCode());
		   		}
	   		}
	   	} else {
	   		myerror_logging(3, "Not a post request from remote client. skip loyalty update");
	   	}
	   	
		myerror_logging(4, "about to call parent update in user adapter");
		
		//FISHBOWL HACK
		if ($current_skin = getSkinForContext()) {
            $marketing_service = new MarketingService($current_skin["brand_id"]);
            if ($marketing_service->service ) {
                if (!$marketing_service->validateJoinFields($resource->getDataFieldsReally())) {
                    return setErrorOnResourceReturnFalse($resource, $marketing_service->getValidationError(), 999);
                } else {
                    if ($marketing_service->service && $marketing_service->enable_join_service) {
                        $marketing_service->join($resource);
                    }
                }
            }
        } else {
            myerror_log("no skin for update, probably something running from an activity");
        }
		
		if (isset($resource->password) && ($resource->password == null || trim($resource->password) == '')) {
			unset($resource->password);
		}
		if (parent::update($resource)) {
		    if ($loyalty_controller) {
		        if ($loyalty_controller->isHomeGrownLoyalty()) {
                    $update_loyalty_record_response_resource = $loyalty_controller->updateLoyaltyNumberOnBrandPointsMapResourceFromRequestData($resource->getDataFieldsReally());
                    if ($update_loyalty_record_response_resource->hasError()) {
                        $loyalty_controller->message = $update_loyalty_record_response_resource->error;
                    }
                }
                if ($loyalty_controller->message) {
                    $resource->set("user_message",$loyalty_controller->message);
                }
            }
		    return true;
        } else {
            return false;
        }
	}
	
	function stripLoyaltyNumber($loyalty_number) {
   		$loyalty_number = str_ireplace("-", "", $loyalty_number);
   		$loyalty_number = str_ireplace("(", "", $loyalty_number);
   		$loyalty_number = str_ireplace(")", "", $loyalty_number);
   		$loyalty_number = str_ireplace(".", "", $loyalty_number);
  		return $loyalty_number;		
	}

	function validateFishBowlDataIfPresent($request_data)
	{
		if ($request_data['marketing_email_opt_in'] == 'Y' || $request_data['marketing_email_opt_in'] == '1') {
			$fishbowl_service = new FishBowlService();
			if ($fishbowl_service->validateBirthdate($request_data['birthday'])) {
				if ($fishbowl_service->validateZip($request_data['zipcode'])) {
					myerror_log("fishbowl data validated");
					$this->fish_bowl_data['birthdate'] = $request_data['birthday'];
					$this->fish_bowl_data['zipcode'] = $request_data['zipcode'];
				} else {
					$this->fish_bowl_error = $fishbowl_service->getError();
					return false;
				}
			} else {
				$this->fish_bowl_error = $fishbowl_service->getError();
				return false;
			}
		}
		return true;
	}
	
	function joinFishBowl($resource)
	{
		$fishbowl_service = new FishBowlService();

		if ($fishbowl_service->joinFishBowl($resource->first_name, $resource->last_name, $resource->email, $resource->phone, $this->fish_bowl_data['birthdate'], $this->fish_bowl_data['zipcode'])) {
			$user_extra_data_adapter = new UserExtraDataAdapter($mimetypes);
			Resource::createByData($user_extra_data_adapter, array("user_id"=>$resource->user_id,"birthdate"=>$this->fish_bowl_data['birthdate'],"zip"=>$this->fish_bowl_data['zipcode'],"process"=>"FishBowl","results"=>$fishbowl_service->response_array['http_code']));
		} else {
			myerror_log("ERROR TRYING TO JOIN FISHBOWL");
			logError("error", "Fishbowl Error. they dont really tell us","");
		}		
	}

    function formatGoodTenDigitPhoneNumber($phone_number)
    {
        // user glob
        return formatGoodTenDigitPhoneNumber($phone_number);
    }

	function isResourceNotGuestUser($resource){
		if(empty($resource->is_guest) || $resource->is_guest == false){
			return true;
		}
		return false;
	}

	function insert(&$resource)
	{
		myerror_log("starting insert of user adapater");
		// first need to encode the password
		if ($this->isResourceNotGuestUser($resource)) {
			if ($password = $resource->password) {
				$password = trim($password);
				if (strlen($password) > 16) {
					return setErrorOnResourceReturnFalse($resource, 'Sorry but the password you entered is too long, maximum is 16 characters', 11);
				} else if (preg_match('/^[0-9a-zA-Z!_$\.\@]+$/', $password)) {
					;
				} else {
					return setErrorOnResourceReturnFalse($resource, 'Sorry but the password you entered contains bad characters. Please use only numbers, letters, !, ., $, @, and _', 12);
				}
				$resource->password = Encrypter::Encrypt($password);
			} else {
				return setErrorOnResourceReturnFalse($resource, 'No Password Submitted on User Creation!', 13);
			}
		}
		if (filter_var($resource->email, FILTER_VALIDATE_EMAIL)) {
            //valid
            $resource->email = strtolower($resource->email);
		} else {
			myerror_log("BAD EMAIL ADDRESS ON USER CREATION: ".$resource->email);
			return setErrorOnResourceReturnFalse($resource, 'Sorry but the email address you entered is not valid', 10);
		}

        // by pass for temp user
        if (strtolower($resource->first_name) != 'sptemp') {
            if ($resource->first_name == null || trim($resource->first_name) == '') {
                return setErrorOnResourceReturnFalse($resource, 'First name cannot be blank.', 422);
            } else if (preg_match('/^[a-zA-Z1-9 .\-]+$/i', $resource->first_name)) {
                $resource->first_name = ucfirst($resource->first_name);
            } else {
                return setErrorOnResourceReturnFalse($resource, 'First name can only contain letters, spaces, and dashes.', 422);
            }
            // replace apostrophy in last names
			if ($this->isResourceNotGuestUser($resource)) {
				$resource->last_name = str_replace("'", "", $resource->last_name);
				if ($resource->last_name == null || trim($resource->last_name) == '') {
					return setErrorOnResourceReturnFalse($resource, 'Last name cannot be blank.', 422);
				} else if (preg_match('/^[a-zA-Z .\-]+$/i', $resource->last_name)) {
					$resource->last_name = ucfirst($resource->last_name);
				} else {
					return setErrorOnResourceReturnFalse($resource, 'Last name can only contain letters, spaces, and dashes.', 422);
				}
			}

            if ($resource->contact_no == null || trim($resource->contact_no) == '') {
                return setErrorOnResourceReturnFalse($resource, 'Phone number cannot be blank.', 422);
            }

            $phone_number = preg_replace("/[^0-9]/","",$resource->contact_no);
            if (strlen($phone_number) == 10) {
                $resource->contact_no = $this->formatGoodTenDigitPhoneNumber($phone_number);
            } else if (strlen($phone_number) == 11 && substr($phone_number,0,1) == 1) {
                $phone_number = substr($phone_number,1,10);
                $resource->contact_no = $this->formatGoodTenDigitPhoneNumber($phone_number);
            } else {
                return setErrorOnResourceReturnFalse($resource, 'Phone number must be a 10 digit number.', 422);
            }
        }

		//verify if the birthdate is in valid format
		if ($resource->birthday != null) {
			$birthday = $resource->birthday;
			if ($current_skin = getSkinForContext()) {
				if ($resource->marketing_email_opt_in == '1' && $current_skin['skin_id'] == 4) {
				    if (strlen($birthday) == 5) {
                        $birthday = $birthday . "/2000"; //adding a year thats a leap year for MOES because mouse requires only MM/DD
                    }
				}
				if (!$this->isValidBirthday($birthday)) {
					return setErrorOnResourceReturnFalse($resource, self::BIRTHDAY_ERROR_MESSAGE, 999);
				} else {
					$resource->birthday = $birthday;
				}
			}
		}
		
		$resource->created = time();
		if ($this->isResourceNotGuestUser($resource)) {
			// check loyalty
			if ($loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext()) {
				$loyalty_controller->setLoyaltyData($resource->getDataFieldsReally());
				if ($loyalty_number = $loyalty_controller->getLoyaltyNumber()) {
					if (!$loyalty_controller->validateLoadedLoyaltyNumber()) {
						return setErrorOnResourceReturnFalse($resource, $loyalty_controller->getBadLoyaltyNumberMessage(), 10);
					}
				} else if ($loyalty_controller->isAutoJoinOn()) {
					// we have a home grown loyalty controller so we want to create an account
					$loyalty_controller->setCreateLoyaltyAcccount(true);
				}
			}
		}
		//FISHBOWL HACK
		$current_skin = getSkinForContext();
		if ($marketing_service = new MarketingService($current_skin["brand_id"])) {
			if (! $marketing_service->validateJoinFields($resource->getDataFieldsReally())) {
				return setErrorOnResourceReturnFalse($resource, $marketing_service->getValidationError(), 999);
			}	
		}
		

		if (parent::insert($resource))
		{
            if ($marketing_service->service && $marketing_service->enable_join_service) {
				$marketing_service->join($resource);
			}
			
			$user_id = $this->_insertId();
            $user_brand_maps_adapter = new UserBrandMapsAdapter(getM());
            $user_brand_map_data = ['user_id'=>$user_id,'brand_id'=>getBrandIdFromCurrentContext()];
            $user_brand_resource = Resource::findOrCreateIfNotExistsByData($user_brand_maps_adapter,$user_brand_map_data);


            if ($loyalty_controller && $this->isResourceNotGuestUser($resource)) {
				$loyalty_controller->createOrLinkAccount($user_id,$points);
                if ($loyalty_controller->message) {
                    $resource->user_message = $loyalty_controller->message;
                }
			}
			
			if ($user_social_data = $resource->user_social && $this->isResourceNotGuestUser($resource)) {
				UserSocialAdapter::createUserSocialRecord($user_id, $user_social_data);
			}			
			
			if (isset($resource->cc_number))
			{
	   			if ($this->cc_save($resource))
	   				parent::update($resource);
			}

			return true;
		} else if (substr_count($this->getLastErrorText(), "key 'account_hash'")) {
			
			/*
			 * ************* until we make account hash unique in teh db, we will never get here **************
			 */ 
			
			myerror_log("ERROR creating the user record: ".$this->getLastErrorText());
			$options[TONIC_FIND_BY_METADATA]['account_hash'] = $resource->account_hash;
			$existing_account = parent::select('',$options);
			$existing_account = array_pop($existing_account);
			$resource->user_id = $existing_account['user_id'];
			$resource->flags = str_replace('C', '0', $resource->flags);
			if (parent::update($resource)) {
				myerror_log("the account has been updated");
			} else { 
				myerror_log("ERROR! updating the user record: ".$this->getLastErrorText());
			}		
			$resource->set('error_code',20);
			$resource->set('error','This phone has an account already associated with it.  We have updated the existing account with your new credentials, please use these to log in. For security purposes your credit card data has been reset');
			$resource->password='';
			return false;
		} else {
			myerror_log("ERROR creating the user record: ".$this->getLastErrorText());
			$resource->set('error_code',10);
			$resource->set('error',$this->getLastErrorText());
			$resource->password='';
			return false;
		}
	}

	function validateFirstName($resource, $first_name)
	{
		if ($first_name == null || trim($first_name) == '') {
			return setErrorOnResourceReturnFalse($resource, 'First name cannot be blank.', 422);
		} else if (preg_match('/^[a-zA-Z1-9 .\-]+$/i', $first_name)) {
			$result = ucfirst($first_name);
			return $result;
		} else {
			return setErrorOnResourceReturnFalse($resource, 'First name can only contain letters, spaces, and dashes.', 422);
		}
	}

	function validateContactNo($resource, $contact_no)
	{
		if ($contact_no == null || trim($contact_no) == '') {
			return setErrorOnResourceReturnFalse($resource, 'Phone number cannot be blank.', 422);
		}
		$phone_number = preg_replace("/[^0-9]/", "", $contact_no);
		if (strlen($phone_number) == 10) {
			$result = $this->formatGoodTenDigitPhoneNumber($phone_number);
		} else if (strlen($phone_number) == 11 && substr($phone_number, 0, 1) == 1) {
			$phone_number = substr($phone_number, 1, 10);
			$result = $this->formatGoodTenDigitPhoneNumber($phone_number);
		} else {
			return setErrorOnResourceReturnFalse($resource, 'Phone number must be a 10 digit number.', 422);
		}
		return $result;
	}
	
	static function checkAndUnlockLockedAccounts()
	{
		myerror_log("*************** check for locked accounts that need to be unlocked *****************");
		$five_minutes_ago = mktime(date("H"), date("i")-2, date("s"), date("m")  , date("d"), date("Y"));
		$user_adapter2 = new UserAdapter($mimetypes);
		$options_u2[TONIC_FIND_BY_METADATA]['flags'] = array('LIKE'=>'2%');
		$options_u2[TONIC_FIND_BY_METADATA]['modified'] = array('<'=>date('Y-m-d H:i:s',$five_minutes_ago));
		
		$options_us2[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';	
		if ($users2 = $user_adapter2->select('',$options_u2))
		{
			foreach ($users2 as $user2)
			{
				$flags = $user2['flags'];
				$flags = '10'.substr($flags,2);
				$user_resource = Resource::find($user_adapter2,$user2['user_id']);
				$user_resource->flags = $flags;
				$user_resource->bad_login_count = 3;
				$user_resource->save();
				MailIt::sendErrorMail($user2['email'],'Your splick-it account is active again',"Hi ".$user2['first_name']."!\r\n\r\nYour account is now unlocked and you can log in again.  If you are having trouble with your password contact customer support.\r\nFor security purposes, you will need to re-enter your CC number the next time you order.\r\n\r\nThanks,\r\nthe splickit team",'support');
			}	
		}
	}

	static function getUserResourceFromId($user_id)
	{
	    if (is_numeric($user_id)) {
	        $options[TONIC_FIND_BY_METADATA]['user_id'] = $user_id;
        } else {
            $options[TONIC_FIND_BY_METADATA]['uuid'] = $user_id;
        }
		if ($resource = Resource::find(new UserAdapter($mimetypes),null,$options)) {
			return $resource;
		} else {
			return false;
		}
	}

	static function getUserResourceFromEmail($email)
    {
        $user_adapter = new UserAdapter($mimetypes);
        $options[TONIC_FIND_BY_METADATA] = array('email'=>$email);
        return Resource::find($user_adapter,'',$options);
    }

    /**
     * @param $email
     * @return Resource
     */
	static function doesUserExist($email)
	{
		$user_adapter = new UserAdapter($mimetypes);
		if (is_numeric($email)) {
			$options[TONIC_FIND_BY_METADATA] = array('user_id'=>$email,'logical_delete'=>'N');
		} else {
			$options[TONIC_FIND_BY_METADATA] = array('email'=>$email,'logical_delete'=>'N');
		}
		if ($resource = Resource::find($user_adapter,'',$options)) {
			return $resource;
		} else {
			return false;
		}
	}

	function isValidBirthday($date_of_birth)
	{
		$valid = validateDate($date_of_birth);
		return $valid;
	}
}
?>