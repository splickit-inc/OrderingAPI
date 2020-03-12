<?php

class UserController extends SplickitController
{
	var $refund_error;
	private $refund_results = array();
	private $user_resource;
	protected $email_service;
	const STARTING_FLAGS = '1000000001';
    const STARTING_FACEBOOK_FLAGS = '1000F00001';

	const ERROR_FOR_GUEST_USER_FORGOT_PASSWORD = "We're sorry but we do not have a user registered with that email, please check your entry.";

	function UserController($mt,$u,$r,$l = 0, $ws = null) {		
		parent::SplickitController($mt,$u,$r,$l);
		
		if(isset($ws)) {
			$this->email_service = $ws;
		} else {
			$this->email_service = new EmailService();
		}
		
		$this->adapter = new UserAdapter($this->mimetypes);

		if ($u['user_id'] > 1) {
			if ($this->user_resource =& Resource::find($this->adapter,$u['user_id'])) {
			  ;
			} else {
				myerror_log("Error instantiating UserController: could not find user with id ".$u['user_id']);
			}
		}
	}

	function isSubmittedUserDataNOTAGuest()
	{
		return !$this->isSubmittedUserDataAGuest();
	}

	function isSubmittedUserDataAGuest()
	{
		return $this->request->data['is_guest'] == true || $this->isUserResourceAGuest($this->user_resource);
	}

	function isUserResourceAGuest($user_resource)
    {
        return doesFlagPositionNEqualX($user_resource->flags,9,'2');
    }

	function processV2Request()
	{
	    if (preg_match('%/users/([0-9]{4}-[0-9a-z]{5}-[0-9a-z]{5}-[0-9a-z]{5})%', $this->request->url, $matches)) {
			$user_id = $matches[1];
			if ($user_id != getLoggedInUserUUId() && $this->isSubmittedUserDataNOTAGuest()) {
				recordError("User Id Mismatch error", "logged in user and update user and differnt");
				logData($_SERVER['LOGIN_ERROR_DATA'],"TOKEN LOGIN RECORD");
				return returnErrorResource("Sorry, there was an authentication error.",403,array("http_code"=>403));
			}

			if (substr_count($this->request->url,'/userdeliverylocation') > 0) {
				if (isRequestMethodAPost($this->request)) {			
					$resource = $this->setDeliveryAddr();
				} else if (isRequestMethodADelete($this->request)) {
					$resource = $this->deleteDeliveryAddr();
				}
				return $resource;
			} else if (substr_count($this->request->url,'/credit_card') > 0) {
				if (isRequestMethodADelete($this->request)) {
					$resource = $this->deleteCCInfo();
					if ($resource->error == null) {
						$resource = Resource::dummyfactory(array("user_message"=>"Your credit card has been deleted."));
					}
					return $resource;
				} else {
					return createErrorResourceWithHttpCode("Method Not Allowed", 405, $error_code, $error_data);
				}
			} else if (substr_count($this->request->url,'/orderhistory') > 0){
				if($this->isSubmittedUserDataAGuest()){
					return Resource::dummyfactory(array("data"=> array("orders"=> 0, "totalOrders" => 0)));
				}
				else if (isRequestMethodAGet($this->request)) {
					$resource = $this->getOrderHistory();
					return $resource;
				} else  {
					return createErrorResourceWithHttpCode("Method Not Allowed", 405, $error_code, $error_data);
				}
			}else if(substr_count($this->request->url,'/favorites') > 0){
				if( $this->isSubmittedUserDataAGuest()){
					return Resource::dummyfactory(array("data"=> 0));
				}
				else if (isRequestMethodAGet($this->request)) {
					$resource = $this->getUserFavorites();
					return $resource;
				} else  {
					return createErrorResourceWithHttpCode("Method Not Allowed", 405, $error_code, $error_data);
				}
			} else if ($this->hasRequestForDestination('stored_value')) {
                if ($this->isUserResourceAGuest($this->user_resource)) {
                    return createErrorResourceWithHttpCode("Guests do not have access to stored value functionality", 405, $error_code, $error_data);
                }
                if (isRequestMethodAPost($this->request)) {
                    if ($result = $this->addStoredValueCard()) {
                        if ($result->hasError()) {
                            return $result;
                        }
                        $balance = $result->balance;
                        // we eventually will get this from the DB
                        $currency_symbol = '$';
                        $result->currency_symbol = $currency_symbol;
                        $result->success = 'true';
                        $result->user_message = 'Your card info was saved successfully. You have a balance of '.$currency_symbol.$balance;
                        return $result;
                    }
                } else {
                    return $this->getStoredValueInfo();
                }
            }
		}
		if ($this->hasRequestForDestination('loyalty') && $this->isSubmittedUserDataNOTAGuest()) {
			if ($loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext($this->user)) {
				return $loyalty_controller->updateLoyaltyNumberOnBrandPointsMapResourceFromRequestData($this->request->data);
			} else {
				return createErrorResourceWithHttpCode('Loyalty is not enabled for this brand',422,422);
			}
		} else if (substr_count($this->request->url,'/forgotpassword') > 0) {
			$resource = $this->forgotPassword();
		} else if (substr_count($this->request->url,'/resetpassword') > 0) {
			$resource = $this->changePasswordWithToken();
		} else if (isRequestMethodAGet($this->request)) {
			if (substr_count($this->request->url,'/loyalty_history') > 0 && $this->isSubmittedUserDataNOTAGuest()) {
				$resource = $this->getLoyaltyHistory($this->request->data['format']);
			} else if (substr_count($this->request->url,'/credit_card/getviowritecredentials')) {
                $resource = $this->getVioWriteCredentials();
            } else {
                $_SERVER['USE_WRITE_DB'] = true;
				$usersession_controller = new UsersessionController($mimetypes,$this->user,$this->request,$this->log_level);
				$resource = $usersession_controller->getUserSession();
			}			
		} else if (isRequestMethodAPost($this->request)) {
			// may have to code the create user token thing like for JM
			if (isLoggedInUserTheAdmin()) {
			    myerror_log("we are about to create a user");
				$resource = $this->createUser();
			} else {
				myerror_log("We have an update user call");
				$resource = $this->updateUser();
			}
		}
	
		unset($resource->password);
		unset($resource->cc_number);
		unset($resource->last_order_merchant_id);
		unset($resource->skin_name);
		unset($resource->skin_id);
		unset($resource->device_type);
		unset($resource->app_version);
		unset($resource->bad_login_count);
		unset($resource->segment);
		unset($resource->modified);
		unset($resource->logical_delete);
		unset($resource->loyalty_number_x);
		unset($resource->skin_type);
		unset($resource->credit_limit);
		unset($resource->trans_fee_override);
		unset($resource->account_hash);
		unset($resource->cc_exp_date);
		unset($resource->cvv);
		unset($resource->zip);
		
		if ($resource->uuid) {
			$resource->user_id = $resource->uuid;
		}
		return $resource;	
	}

    /**
     * @return Resource
     *
     */
	function addStoredValueCard()
    {
        if (preg_match('%/stored_value/([0-9]{5})%', $this->request->url, $sv_matches)) {
            $splickit_accepted_payment_type_id = $sv_matches[1];
            if ($payment_service = PaymentGod::getPaymentServiceBySplickitAcceptedPaymentTypeId($splickit_accepted_payment_type_id,[])) {
                $skin_id = getSkinIdForContext();
                $user_id = $this->user['user_id'];
                $card_number = $this->data['card_number'];
                if ($card_resource = $payment_service->addCardToUserAccount($card_number,$user_id,$skin_id)) {
                    return $card_resource;
                } else {
                    myerror_log("ERROR!!!! unable to save stored value card");
                    MailIt::sendErrorEmail("ERROR!!!! unable to save stored value card","check logs");
                    MailIt::sendErrorEmailSupport("ERROR!!!! unable to save stored value card","check logs");
                    return createErrorResourceWithHttpCode("Sorry, there was an error and your data was not saved. Support has been alerted",422,422,[]);
                }
            } else {
                return createErrorResourceWithHttpCode("$splickit_accepted_payment_type_id is not a valid payment type",422,422,null);
            }
        } else {
            return createErrorResourceWithHttpCode("Error! No card type submitted.", 422, 422, null);
        }
    }

    function getVioWriteCredentials()
    {
        $creds = getProperty('vio_write_username_password');
        return Resource::dummyfactory(array("vio_write_credentials"=>$creds));
    }
	
	function getLoyaltyHistory($version)
	{
		if ($loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext()) {
			$history = $loyalty_controller->getLoyaltyHistory();
			$data = array();
			/// TODO: only for retrocompatibility with older mobile apps
			myerror_log("LOYALTY HISTORY VERSION: $version");
			if($version == "v2"){
				$data['data'] = array(
					"headings" => $loyalty_controller->getLoyaltyHistoryHeadings(),
					"rows" => $history
				);
			}else{
				$data['data'] = $history;
				myerror_log("USING SUPPORT OLD VERSION: using $version loyalty history");
 			}

			return Resource::dummyfactory($data);
		}
		return false;
	}
	
	function getUserFavorites(){
		$favorite_controller = new FavoriteController($mt, $this->user);

		if(isset($this->request->data['merchant_id']) && $this->request->data['merchant_id'] > 0){
			$merchant_id = $this->request->data['merchant_id'];
			if(isset($this->request->data['merchant_menu_type']) && ($this->request->data['merchant_menu_type'] != '')){
				$menu_type = $this->request->data['merchant_menu_type'];
				$merchant_resource = CompleteMerchant::staticGetCompleteMerchant($merchant_id, $menu_type, getEndpointVersion($this->request));
                if ($merchant_resource->hasError()) {
                    return $merchant_resource;
                }
			}else{
				return createErrorResourceWithHttpCode("Missing merchant_menu_type parameter",422,999);
			}
		}else{
			if(isset($this->request->data['merchant_menu_type']) && ($this->request->data['merchant_menu_type'] != '')) {
				return createErrorResourceWithHttpCode("Missing merchant_id parameter",422,999);
			}
		}

		$favorites = $favorite_controller->getFavorites($merchant_resource, getEndpointVersion($this->request));
        if ($this->request->isRequestDeviceTypeNativeApp()) {
            // get last order
            $complete_merchant = new CompleteMerchant($this->request->data['merchant_id']);
            $last_orders = $complete_merchant->loadLastOrdersValidForUserAndMenu($this->user['user_id'], $merchant_resource->menu);
            foreach ($last_orders as $index=>$last_order) {
                $last_order_as_favorite['favorite_id'] = time()+$index;
                $last_order_as_favorite['favorite_name'] = $last_order['label'];
                $last_order_as_favorite['favorite_order'] = $last_order['order'];
                $favorites[] = $last_order_as_favorite;
            }
        } else {
            myerror_log("Device is not a native app. device: ".$this->request->getHeaderVariable('HTTP_X_SPLICKIT_CLIENT_DEVICE'),5);
        }

		return Resource::dummyfactory(array("data"=> $favorites));
	}

	function getOrderHistory()
	{

		$history = array();
		$page = isset($this->request->data["page"])?$this->request->data["page"]: 1;
		$page = $page < 1? 1: $page;

		$start = ($page-1)*10;
		$user_id = $this->user['user_id'];
        if ($user_id < 20000) {
            return Resource::dummyfactory(array("data"=> array("orders"=>$history, "totalOrders" => 0)));
        }
		$brand_id = $_SERVER["BRAND"]["brand_id"];
		$order_adapter = new OrderAdapter(getM());
		//$sqlcount = "select count(o.`order_id`) total from `Brand2` b join `Merchant` m on b.`brand_id` = m.`brand_id` join `Orders` o on m.`merchant_id` = o.`merchant_id` where b.`brand_id` = $brand_id and o.`user_id` = $user_id and o.`status` in ('E', 'O', 'N') and o.`logical_delete` = 'N' order by o.`order_dt_tm`";
		$sqlcount = "select count(o.`order_id`) total from `Orders` o join `Merchant` m on m.`merchant_id` = o.`merchant_id`where m.`brand_id` = $brand_id and o.`user_id` = $user_id and o.`status` in ('E', 'O', 'N') and o.`logical_delete` = 'N' order by o.`order_dt_tm`";
		$count_options[TONIC_FIND_BY_SQL] = $sqlcount;
		$countorders = Resource::findAll($order_adapter, $url, $count_options);
		$totalOrders = 0;
		foreach($countorders as $item) {
			$totalOrders = $item->total;
		}
		//$sql = "select o.`order_id` from `Brand2` b join `Merchant` m on b.`brand_id` = m.`brand_id` join `Orders` o on m.`merchant_id` = o.`merchant_id` where b.`brand_id` = $brand_id and o.`user_id` = $user_id and o.`status` in ('E', 'O', 'N') and o.`logical_delete` = 'N' order by o.`order_dt_tm` desc limit $start, 10";
        $sql = "select o.`order_id` from `Orders` o join `Merchant` m on m.`merchant_id` = o.`merchant_id`where m.`brand_id` = $brand_id and o.`user_id` = $user_id and o.`status` in ('E', 'O', 'N') and o.`logical_delete` = 'N' order by o.`order_dt_tm` desc limit $start, 10";
		$item_options[TONIC_FIND_BY_SQL] = $sql;
		$orders = Resource::findAll($order_adapter, $url, $item_options);

		foreach($orders as $order){
			$order_complete = CompleteOrder::staticGetCompleteOrder($order->order_id, $mimetypes);
			$order_to_result = array(
				"order_id" => $order_complete["order_id"],
				"merchant_id"=>$order_complete["merchant_id"],
				"order_dt_tm"=>$order_complete["order_dt_tm"],
				"tip_amt" => $order_complete["tip_amt"],
				"status"=>$order_complete["status"],
				"order_date"=>$order_complete["order_date"],
				"order_date2"=>$order_complete["order_date2"],
				"order_date3"=>$order_complete["order_date3"],
				"order_date_task_retail"=>$order_complete["order_date_task_retail"],
				"order_time"=>$order_complete["order_time"],
				"merchant"=>$order_complete["merchant"],
				"gift_used"=>$order_complete["gift_used"],
				"order_summary"=>$order_complete["order_summary"],
				"receipt_items_for_merchant_printout"=>$order_complete["receipt_items_for_merchant_printout"]
			);
			array_push($history, $order_to_result);
		}


		return Resource::dummyfactory(array("data"=> array("orders"=>$history, "totalOrders" => $totalOrders)));
	}

	/**
	 * 
	 * returns user resource
	 */
	function getUser()
	{
		return $this->user_resource;
	}
	
	function setUser(&$user_resource)
	{
		$this->user_resource = $user_resource;
	}
	
	function adminLandingLogicallyDeleteUser()
	{
		if ($email = $this->request->data['email'])
		{
			if ($user_resource = UserAdapter::doesUserExist($email))
			{
				if ($this->logicalyDeleteUserResource($user_resource))
				{
					$message = "The Users Record Has Been Deleted";
					$error = 'green';
				}	else  {
					$message = "There was an error";
					$error = 'red';
				}
			} else {
				$message = "NO MATCHING USER FOUND";
				$error = 'red';
			}
		} else {
			$message = "please enter an email address"; 
			$error = "green";
			if ($this->request->method == "post")
			{
				$message = "You did not enter an email address";
				$error = "red";
			}
		}
        $resource = Resource::dummyfactory(array("message"=>$message,"error"=>$error,"temp"=>rand(1,1000000)));
		$resource->_representation = '/admin/deleteuser.html';
		return $resource;
	}
	
	function logicalyDeleteUserResource($user_resource)
	{
		$user_resource->logical_delete = 'Y';
		$user_resource->email = 'deleted-'.getRawStamp().'-'.$user_resource->email;
		$user_resource->flags = '1000000001';
		if ($user_resource->save())
			return true;
		return false;
	}
	
	function logicalyDeleteUserByUserId($user_id)
	{
		$user_resource = Resource::find($this->adapter,"$user_id");
		return $this->logicalyDeleteUserResource($user_resource);
	}
	
	function validatePassword($password)
	{
			myerror_logging(4, "we have a password to update");
			$password = trim($password);
			if (strlen($password) > 32) {
				myerror_log("Password too long ON USER UPDATE");
				return setErrorOnResourceReturnFalse($this->user_resource, 'Sorry but the password you entered is too long, maximum is 32 characters', 11);
			} else if (preg_match('/^[0-9a-zA-Z!_@\.\$]+$/', $password)) {
				//myerror_logging(2,"password is good: ".$password);
				return true;
			} else {
				myerror_log("BAD CHARACTERS ON USER CREATION");
				return setErrorOnResourceReturnFalse($this->user_resource, 'Sorry but the password you entered contains bad characters. Please use only numbers, letters, and ! . $ @ _ ', 12);
			}	
		
	}
	
	function updateUserFromData($data)
	{
	    myerror_log("starting update user in UserController");
		if ($password = $data['password']) {
			myerror_logging(4, "we have a password to update");
			if ($this->validatePassword($password)) {
				$data['password'] = Encrypter::Encrypt($password);
				myerror_logging(2, "password encrypted is: " . $data['password']);
				$data['balance'] = $this->user_resource->balance;
			} else {
				return $this->user_resource;
			}
		} else if (isset($data['loyalty_phone_number']) || isset($data['loyalty_number']) ) {
			if (isBrandLoyaltyOn()) {
				$loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext($this->user_resource->getDataFieldsReally());
				if ($loyalty_controller->isNonRemoteLoyalty()) {
					return $loyalty_controller->updateLoyaltyNumberOnBrandPointsMapResourceFromRequestData($data);
				} else {
					myerror_log("This is a remote loyalty program so let remote validation logic execute");
				}
			} else {
				myerror_log("brand loyalty is not on. skip loyalty update");
			}
		} else {
			myerror_logging(3, "no password to update");
		}
		// loyalty hack to update loyalty record when user record phone number is updated
		if (isBrandLoyaltyOn()) {
			if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($data,'contact_no')) {
				$submitted_phone_number = cleanAllNonNumericCharactersFromString($data['contact_no']);
				if ($submitted_phone_number != cleanAllNonNumericCharactersFromString($this->user_resource->contact_no)) {
					$data['loyalty_phone_number'] = $submitted_phone_number;
				}
			}
		}

		if (isset($data['contact_no'])) {
            $phone_number = $this->replaceEverythingButNotNumber($data['contact_no']);
            if (strlen($phone_number) == 10) {
                $data['contact_no'] = $this->adapter->formatGoodTenDigitPhoneNumber($phone_number);
            } else if (strlen($phone_number) == 11 && substr($phone_number,0,1) == 1) {
                $phone_number = substr($phone_number,1,10);
                $data['contact_no'] = $this->adapter->formatGoodTenDigitPhoneNumber($phone_number);
            } else {
                return createErrorResourceWithHttpCode('Phone number must be a 10 digit number.',422,422);
                //return setErrorOnResourceReturnFalse($resource, 'Phone number must be a 10 digit number.', 422);
            }
        }

		$result = $this->user_resource->saveResourceFromData($data); 
		if (!$result ) {
			// ok we had a failure
			return $this->getProperReturnResourceFromUserSaveFail($this->user_resource);
		}
		if ($data['email']) {
			$this->sendWelcomeLetterToUserIfNecessary($data['email']);
		}  
		//Resource::encodeResourceIntoTonicFormat($this->user_resource);
				
		if ($this->request->data['donation_active']) {
			$this->setDonation();
		}
		if ($this->request->data['group_airport_employee'] == 'Y') {
			$this->joinAirportEmployeesGroup();
		} else if ($this->request->data['group_airport_employee'] == 'N') {
			$this->unjoinAirportEmployeesGroup();
		}
		return $this->user_resource;
		
	}
	
	function sendWelcomeLetterToUserIfNecessary($new_email)
	{
		if (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
			if ($this->isThisAnUpdateToARealUserFromATempUser($new_email)) {
				$this->sendWelcomeLetterToUserForContext($this->user_resource);
			}	
		}				
	}
	
	function updateUser()
	{
		setSessionProperty('is_a_user_update', 'true');
		// prevent hacking.  cant do this here
		unset($this->request->data['balance']);
		unset($this->request->data['credit_limit']);
		
		myerror_logging(3,"size of request data is: ".sizeof($this->request->data));
		
		return $this->updateUserFromData($this->request->data);
	}

	function deleteCCInfo()
	{
		$this->user_resource->flags = $this->adapter->deleteUserCCVaultInfoAndGetNewFlagsFromUserResource($this->user_resource);
		return $this->updateUser();
	}

	function unjoinAirportEmployeesGroup()
	{
		$uga = new UserGroupsAdapter($mimetypes);
		if ($record = $uga->getRecord(array("name"=>"Airport Employees"))) {
			UserGroupMembersAdapter::unJoinGroup($record['id'], $this->user['user_id']);
		}
	}
	
	function joinAirportEmployeesGroup()
	{
		try {
			$this->joinGroup('Airport Employees');
		} catch (Exception $e) {
			MailIt::sendErrorEmail("ERROR! Airport Employees Group Does Not Exists", $e->getMessage());
		}
	}
	
	function joinGroup($group_name)
	{
		$uga = new UserGroupsAdapter($mimetypes);
		if ($record = $uga->getRecord(array("name"=>$group_name))) {
			return UserGroupMembersAdapter::joinGroup($record['id'], $this->user['user_id']);
		}
		throw new NoMatchingGroupException("There is no group by that name: ".$group_name);		
	}

	function setDonation()
	{
		$type = 'R';
		$amt = 0.00;
		if ($this->request->data['donation_type'] == 'F')
		{
			$type = 'F';
			$amt = $this->request->data['donation_amt'];
		}
		$data['user_id'] = $this->user_resource->user_id;
		$data['skin_id'] = $_SERVER['SKIN_ID'];
		$options[TONIC_FIND_BY_METADATA] = $data;
		$user_skin_donation_adapter = new UserSkinDonationAdapter($this->mimetypes);
		if ($resource =& Resource::findExact($user_skin_donation_adapter,'', $options)) 
			;// we got it.  user has a record for this skin already
		else 
			$resource = Resource::factory($user_skin_donation_adapter,$data);

		$resource->donation_active = $this->request->data['donation_active'];
		$resource->donation_type = $type;
		$resource->donation_amt = $amt;
		$resource->modified = time();
		if ($resource->save())
			return $resource;
		else
			return false;
	}
	
	function deleteDeliveryAddr()
	{
		$udla = new UserDeliveryLocationAdapter($this->mimetypes);
		if ($user_delivery_resource = $this->request->load($udla))
		{
			if ($user_delivery_resource->user_id == $this->user['user_id'])
			{
				$user_delivery_resource->logical_delete = 'Y';
				$user_delivery_resource->modified = time();
				if ($user_delivery_resource->save())
					$data = array('result'=>'success');
				else
					$data = array('error_code'=>191,'error'=>'could not update the user delivery record to a logical delete');
			} else {
				$data = array('error_code'=>192,'error'=>'user id mismatch between logged in user and delivery address owner');
			}			
			
		} else 
			$data = array('error_code'=>190,'error'=>'could not locate the user delivery record');
		
		$resource = Resource::factory($udla,$data);
		return $resource;
	}
	
	function validateDeliveryAddr($addr_data) {
		$address1 = $addr_data['address1'];
		$city = $addr_data['city'];
		$state = strtolower($addr_data['state']);
		$zip = $addr_data['zip'];
		$phone = $addr_data['phone'];
				
		$la = new LookupAdapter($m);
		$options[TONIC_FIND_BY_METADATA]['type_id_field'] = "state";		
		$states = array(); 
		$rs = Resource::findAll($la, $options);
		foreach( $rs as $r) {
		  $states[] = strtolower($r->type_id_value);
		}
		
		if ($address1 == null || $address1 == '' )
			return array("error"=>"Address cannot be null","error_code"=>"11");
		else if ($city == null || $city == '')
			return array("error"=>"City cannot be null","error_code"=>"11");
		else if ($state == null || $state == '')
			return array("error"=>"State cannot be null","error_code"=>"11");
		else if (!in_array($state, $states))  
		  return array("error"=>"'$state' is not a recognized state abbreviation.","error_code"=>"11");
		else if ($zip == null || $zip == '')
			return array("error"=>"Zip cannot be null","error_code"=>"11");
		else if (!preg_match('/^[[:digit:]]{5,}/', $zip))
			return array("error"=>"Zip must start with 5 consecutive digits","error_code"=>"11");
		else if ($phone == null || $phone == '')
			return array("error"=>"You must enter a phone number for delivery","error_code"=>"11");
		else if ($this->checkForInvalidPhoneNumber($phone))
			return array("error"=>"The phone number you entered is not valid","error_code"=>"11");
	}
	
	function setDeliveryAddr()
	{
        $phone_number = $this->replaceEverythingButNotNumber($this->request->data['phone_no']);
        if (strlen($phone_number) == 10) {
            $phone_number = formatGoodTenDigitPhoneNumber($phone_number);
        } else if (strlen($phone_number) == 11 && substr($phone_number,0,1) == 1) {
            $phone_number = substr($phone_number,1,10);
            $phone_number = formatGoodTenDigitPhoneNumber($phone_number);
        } else {
            return createErrorResourceWithHttpCode('The phone number you entered is not valid',422,422);
        }
        unset($this->request->data['phone_no']);

		$udla = new UserDeliveryLocationAdapter($this->mimetypes);
		$this->request->data['user_id'] = $this->user['user_id'];
		$options[TONIC_FIND_BY_METADATA] = $this->request->data;
		if ($user_delivery_resource = Resource::find($udla, null, $options)) 
		{
            $this->request->data['phone_no'] = $phone_number;
			if ($user_delivery_resource->_updateResource($this->request)){
				return $user_delivery_resource;
			}
		} else {
            $this->request->data['phone_no'] = $phone_number;
			$user_delivery_resource = Resource::factory($udla, $this->request->data);
			// now set lat long
			$address1 = trim($this->request->data['address1']);
			$city = trim($this->request->data['city']);
			$state = strtoupper(trim($this->request->data['state']));
			$zip = trim($this->request->data['zip']);
			$phone = trim($this->request->data['phone_no']);			
			$errors = $this->validateDeliveryAddr(array("address1" => $address1, "city" => $city, "state" => $state, "zip" => $zip, "phone" => $phone_number));
			
			if ($errors) {
				return Resource::dummyfactory($errors);
			} 
						
			$address = "".$address1.",".$city.",".$state." ".$zip;
			$address = str_ireplace(' ', '+', $address);
			myerror_logging(1,"the address in setDeliveryAddr is: ".$address);
			
			$use_lat_lng = false;
			if (isset($this->request->data['lat']) && isset($this->request->data['lng'])) {
			  $latitude = $this->request->data['lat'];
			  $longitude = $this->request->data['lng'];
			  $use_lat_lng = true;
			  myerror_logging(2,"using lat long from request");
			} else if ($location = LatLong::generateLatLong($address,false)) {
				$latitude = $location['lat'];
				$longitude = $location['lng'];
			} else {
				myerror_log("COULD NOT DETERMINE LAT AND LONG IN UserController->setDeliveryAddr");
				$user_message = "We could not determine the exact location of this address";
				return returnErrorResource("We're sorry, but the google geo coder could not find the exact location of this addresss. Please check your entry.",121);
			}
			$user_delivery_resource->set('lat',$latitude);
			$user_delivery_resource->set('lng',$longitude);
			if ($user_delivery_resource->save())
				return $user_delivery_resource;
			else
			{
				$etext = $user_delivery_resource->_adapter->getLastErrorText();
				myerror_log("user delivery address NOT SAVED!: ".$etext);
				MailIt::sendErrorEmail("Error! user delivery address NOT SAVED!", "mysql error: ".$etext);
				return returnErrorResource("We're sorry, there was a problem saving your delivery address, please try again.",121);
			}
		}
	}
	
	/**
     * @codeCoverageIgnore
     * @deprecated
     */
	function processRequest()
	{
		$request = $this->request;
		$url = $this->request->url;
		if ($this->user['email'] == 'admin' || isset($request->data['create_user_token']))
		{
			// must be a new user creation;
			$resource = $this->createUser();
			if (isset($request->data['create_user_token'])) {
				// create special response for token creation
				if ($user_id = $resource->user_id) {
					$resource = Resource::dummyfactory(array("status"=>'success',"code"=>'200',"uuid"=>$resource->uuid,"http_code"=>200));
				} else {
					$resource = Resource::dummyfactory(array("status"=>'failed',"error_code"=>'500',"error"=>$resource->error,"http_code"=>500));
				}
			}
		} else if (substr_count($request->url,'/loadpitapitstoredvaluecard') > 0 ) {
			return returnErrorResource("endpoint has not been created yet");							
		} else {
			if (strtolower($request->method) == 'post') {
				$resource = $this->updateUser();
			} else {
				$resource = $this->getUser();
			}
		}
		$resource->password = '';
		$resource->cc_number = '';
		return $resource;
	}
	
	function createUser()
	{
		//search the user by mail in data base if exist and is guest and variable is_guest is true then update and return the resource
		if ($resource = $this->searchUserByEmail())
		{
		    myerror_log("looks like email already exists, return user record");
			return $resource;
		}

		//in other cases create new row
		if ($encrypted_createuser_data = $this->request->data['create_user_token']) {
			myerror_log("we have a create user token");
			$clear_data_json = SplickitCrypter::doDecryption($encrypted_createuser_data,$this->user['email']);
			myerror_log("decypted json: ".$clear_data_json);
			$createuser_data = json_decode($clear_data_json,true);
			
			//now add the fields to the request data
			foreach ($createuser_data as $cud_name=>$cud_value) {
				$this->request->data[$cud_name] = $cud_value;
			}
			
			if ($this->user['email'] == 'mikesmarketer') {
				$_SERVER['SKIN_ID'] = 72;
				$skin_resource = SplickitController::getResourceFromId(72, "Skin");
				$_SERVER['SKIN'] = $skin_resource->getDataFieldsReally();
				$_SERVER['HTTP_X_SPLICKIT_CLIENT_ID'] = 'com.splickit.jerseymikes';
				$_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'] = "MikesMarketerCreateUser";
				$_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'] = 10.0;
				
			} else {
				return returnErrorResource("ERROR! User without create privs trying to create a user");
			}	 
				
			if (!isProd())
			{
				myerror_log("************ creasteuser fields ************");
				foreach ($this->request->data as $name=>$value)
					myerror_log("$name=$value");
				myerror_log("********************************************");
				
			}		
		}		
		$code = generateUUID();

		//i'm sure there's a better way to do this
		$this->request->data['uuid'] = $code;
		$this->request->data['skin_id'] = $_SERVER['SKIN_ID'];
		$this->request->data['skin_name'] = $_SERVER['HTTP_X_SPLICKIT_CLIENT_ID'];
		$this->request->data['device_type'] = $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'];
		$this->request->data['app_version'] = $_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'];
		$this->request->data['balance'] = 0.00;

		if ($this->isSubmittedUserDataAGuest()) {
            //for guest users should set number 2 in the position 9 of flags
            $this->request->data['flags'] = $this->adapter->setFlagPosition(self::STARTING_FLAGS, 9, 2);
        } else if ($this->request->data['facebook_authentication']) {
            $this->request->data['flags'] = self::STARTING_FACEBOOK_FLAGS;
		} else {
			$this->request->data['flags'] = self::STARTING_FLAGS;
		}

		$this->user_resource = Resource::factory($this->adapter,$this->request->data);
		if ($this->user_resource->save())	{
		    if ($this->request->data['facebook_user_id']) {
                $user_facebook_id_maps_adapter = new UserFacebookIdMapsAdapter(getM());
                $resource = Resource::createByData($user_facebook_id_maps_adapter,['facebook_user_id'=>$this->request->data['facebook_user_id'],'user_id'=>$this->user_resource->user_id,'created_stamp'=>getRawStamp()]);
            }


			// get any message that was set in the adapter
            $message = $this->user_resource->user_message;

            // wecome letter if it exists
			myerror_logging(2,"we have successfully saved the user now check for welcome letter");
			$this->sendWelcomeLetterToUserForContext($this->user_resource);

			// now test for donation
			if ($this->request->data['donation_active'] && strtolower(substr($this->request->data['donation_active'],0,1)) == 'y')
				$this->setDonation();
			if ($_SERVER['HTTP_HOST'] == 'test.splickit.com')
			{
				$this->user_resource->set('user_message_title','Hello!');
				$this->user_resource->set('user_message','Welcome to mobile ordering! The future is friendly!');
			}
			
			//create record in the user_creation_data table
			if (isset($_SERVER['HTTP_X_SPLICKIT_CLIENT_LATITUDE']) && isset($_SERVER['HTTP_X_SPLICKIT_CLIENT_LONGITUDE']))
			{
				$ucd_adapter = new UserCreationDataAdapter($this->mimetypes);
				$nu_fields['user_id'] = $this->user_resource->user_id;
				$nu_fields['skin_id'] = $_SERVER['SKIN_ID'];
				$nu_fields['lat'] = $_SERVER['HTTP_X_SPLICKIT_CLIENT_LATITUDE'];
				$nu_fields['lng'] = $_SERVER['HTTP_X_SPLICKIT_CLIENT_LONGITUDE'];
				$nu_fields['device_type'] = $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'];
				$nu_fields['created'] = time();
				$ucd_resource = Resource::factory($ucd_adapter,$nu_fields);
				try {
					$ucd_resource->save();
					// now get closest merchant in skin
					$sql = "SELECT ( 3959 * acos( cos( radians(".$ucd_resource->lat.") ) * cos( radians( lat ) ) * cos( radians( lng ) - radians(".$ucd_resource->lng.") ) + sin( radians(".$ucd_resource->lat.") ) * sin( radians( lat ) ) ) ) AS distance FROM Merchant a, Skin_Merchant_Map b WHERE a.merchant_id = b.merchant_id and b.skin_id = ".$ucd_resource->skin_id." and a.active = 'Y' and a.logical_delete = 'N' ORDER BY distance limit 1";
					$dist_options[TONIC_FIND_BY_SQL] = $sql;
					if ($dists = $ucd_adapter->select('',$dist_options))
					{
						$dist_record = array_pop($dists);
						$dist = $dist_record['distance'];
						$ucd_resource->dist_to_closest_skin_store = $dist;
						$ucd_resource->save();
					}
				} catch (Exception $e) {
					myerror_log("ERROR! there was an error trying to create the User_Create_Data record: ".$e->getMessage());
				}
			}
            $authentication_token_resource = createUserAuthenticationToken($this->user_resource->user_id);
			$this->user_resource->set("splickit_authentication_token",$authentication_token_resource->token);
			$this->user_resource->set('splickit_authentication_token_expires_at',$authentication_token_resource->expires_at);
			return $this->user_resource;
		} else {
			return $this->getProperReturnResourceFromUserSaveFail($this->user_resource);
		}
	}

	function searchUserByEmail()
	{
		if ($this->request->data['email']) {
			$options[TONIC_FIND_BY_METADATA]['email'] = $this->request->data['email'];
			$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
			//search user by mail
			if ($resource = Resource::findExact($this->adapter, null, $options)) {
				$this->user_resource = $resource;
				$this->user_resource->first_name = $this->adapter->validateFirstName($this->user_resource, $this->request->data['first_name']);
				$this->user_resource->contact_no = $this->adapter->validateContactNo($this->user_resource, $this->request->data['contact_no']);
				$this->user_resource->created = time();
				$this->user_resource->modified = time();
				
				//if user exist and is guest and other guest with the same email is trying to access permit update
				if (doesFlagPositionNEqualX($this->user_resource->flags,9,'2') && $this->request->data['is_guest'] == true) {
					if ($this->user_resource->save()) {
						$this->createAuthenticationToken($this->user_resource);
						return $this->user_resource;
					}
				} elseif (doesFlagPositionNEqualX($this->user_resource->flags,9,'2') && !$this->request->data['is_guest'] == true) {
					//if user exist in data base and is guest and is trying to create a new normal user permit update
					$this->user_resource->flags = self::STARTING_FLAGS; //change flag

					if ($resource = $this->updateUser()) {
						$this->createAuthenticationToken($resource);
						return $resource;
					}
				} else {
					//in other cases try to save and returns email duplicated message
					$this->user_resource = Resource::factory($this->adapter, $this->request->data);

					if ($this->user_resource->save()) {
						return $this->user_resource;
					} else {
						return $this->getProperReturnResourceFromUserSaveFail($this->user_resource);
					}
				}
			}
		}
		return false;
	}

	function createAuthenticationToken(&$resource)
	{
		$authentication_token_resource = createUserAuthenticationToken($resource->user_id);
		$resource->set("splickit_authentication_token", $authentication_token_resource->token);
		$resource->set('splickit_authentication_token_expires_at', $authentication_token_resource->expires_at);
	}
	
	function isThisAnUpdateToARealUserFromATempUser($new_email)
	{
		if (isThisForRealAnUpdateQuestionMark())
		{
			if ($this->isCurrentUserTempUser()) {
				// current is temp lets see if they are trying to become real
				if ($this->user['email'] != $new_email) {
					// we have a temp update to real
					return true;
				}
			}
		}
		return false;
	}
	
	function isCurrentUserTempUser()
	{
		return $this->isThisEmailATempUserEmail($this->user['email']);
	}
	
	function isThisEmailATempUserEmail($email)
	{
		$user_email_array = explode('@', $email);
		return ('splickit.dum' == $user_email_array[1]);
	}
	
	function getProperReturnResourceFromUserSaveFail($failed_save_user_resource)
	{
			$error = $failed_save_user_resource->error;
			$lower_error = strtolower($error);
			$lower_email = strtolower($failed_save_user_resource->email);
			if ($lower_error == "duplicate entry '$lower_email' for key 'email'")
			{
				$error = "Sorry, it appears this email address exists already with a different password. Please try logging in on the main screen.";
				$this->user_resource->error = $error;
				if (! $this->isCurrentUserTempUser()) {
					//non temp user trying to save with an existing email
					// check to see if its the admin user and bypass if so
					if ($this->user['user_id'] != 1) {
						$error = "Sorry, it appears this email address exists already.";
						$this->user_resource->error = $error;
						return $this->user_resource;
					}
				}

				// first get original record
				$options[TONIC_FIND_BY_METADATA]['email'] = $this->request->data['email'];
				if ($original_user_resource = Resource::findExact($this->adapter,'',$options)) {
                    // now test to see if the paswords are the same if so, just log the user in
                    $submitted_password = $this->request->data['password'];
                    if (LoginAdapter::verifyPasswordWithDbHash(trim($this->request->data['password']), $original_user_resource->password) && substr($original_user_resource->flags, 0,1) != '2') {
                        $original_user_resource->bad_login_count = 1;
                        $original_user_resource->save();

                        if ($this->isThisEmailATempUserEmail($lower_email)) {
                            $original_user_resource->set('user_message_do_not_show',"This account already exists, however, your password matched, so we logged in you anyway :)");
                        } else {
                            $original_user_resource->user_message_title = "Account Already Exists!";
                            $original_user_resource->user_message = "This account already exists, however, your password matched, so we logged in you anyway :)";
                        }
                        $authentication_token_resource = createUserAuthenticationToken($original_user_resource->user_id);
                        $original_user_resource->set("splickit_authentication_token",$authentication_token_resource->token);
                        $original_user_resource->set('splickit_authentication_token_expires_at',$authentication_token_resource->expires_at);
                        return $original_user_resource;
                    } else if (substr($original_user_resource->flags, 0,1) == '2') {
                        $this->user_resource->error = 'Sorry the account with this email is now locked, please try again in 2 minutes';
                    } else {
                        // bump up the persons failed login attempts.
                        $original_user_resource->bad_login_count = $original_user_resource->bad_login_count + 1;
                        $original_user_resource->save();

                        // duplicate email with bad password so set code to 409
                        myerror_log("about to set the http code to 409");
                        $failed_save_user_resource->set('http_code', 409);
                    }
                } else {
                    myerror_log("Duplicate entry with NO existing record. Probably Logically deleted for fraud");
                    return createErrorResourceWithHttpCode('Sorry, there was an error. Please contact support.',500,500);
//                    $error = "Sorry, it appears this email address exists already.";
//                    $this->user_resource->error = $error;
//                    return $this->user_resource;
                }


			} else {
				//WHAT A HACK!
				if ($failed_save_user_resource->error_code > 399 && $failed_save_user_resource->error_code < 600) {
					// we have a http code probably 
					$failed_save_user_resource->set('http_code', $failed_save_user_resource->error_code);
				}
			}
			return $failed_save_user_resource;
	}
	
	function shouldSendWelcomeLetter($user_resource) {
	    if (isUserResourceATempUser($user_resource)) {
	        return false;
        } else if ($this->hasUserBeenWelcomed($user_resource)) {
	        return false;
        } else if ($this->isUserResourceAGuest($user_resource)) {
	        return false;
        } else {
	        return true;
        }
		$isTemp = isUserResourceATempUser($user_resource);
		$alreadySent = $this->hasUserBeenWelcomed($user_resource); 
		return !$isTemp && !$alreadySent;
	}
	
	function hasUserBeenWelcomed($user) {
		$mmh_adapter = new MerchantMessageHistoryAdapter($mimetypes);		
		$email = $user->email;
		$welcome_records = $mmh_adapter->getRecords(array("message_delivery_addr"=>$email, "message_format"=>"Ewel", "message_type"=>"I"));
		return (count($welcome_records) > 0);	
	}
	
	function sendWelcomeLetterToUserForContext($user_resource) {
		$brand_name = getSkinNameForContext();
		
		if ($this->shouldSendWelcomeLetter($user_resource) && $email_body = $this->getWelcomeLetterBodyForUserResourceForCurrentContext(getIdentifierNameFromContext())) {

			$subject = "Welcome to $brand_name mobile/online ordering!";			
			$mmh_data['message_format'] = 'Ewel';
			$mmh_data['message_type'] = 'I';
			
			$map_id = MailIt::stageEmail($user_resource->email, $subject, $email_body, $brand_name, $bcc, $mmh_data);
			return $map_id;
		} else {
			return false;
		}
	}
	
	function getWelcomeLetterBodyForUserResourceForCurrentContext($skin_name) {
		$template_resp = $this->email_service->getUserWelcomeTemplate($skin_name);
		return $template_resp;	
	}
	
	function setSkinStuffOnLetterResource(&$letter_resource)
	{
		$skin = getSkinForContext();
		$letter_resource->set('skin',$skin);
		$letter_resource->set('skin_name',$skin['skin_name']);
		// now get images
		$skin_images_map_adapter = new SkinImagesMapAdapter($this->mimetypes);
		if ($skins_images = $skin_images_map_adapter->getRecord(array("skin_id"=>$skin['skin_id']))) {
			$letter_resource->set('skin_images',$skin_images);
		}
	}
	
	function sendWelcomeLetterToUser($welcome_letter_file_name)
	{
		$doc_root = $_SERVER['DOCUMENT_ROOT'].'/app2';
		if (isLaptop()) {
			$doc_root = $_SERVER['DOCUMENT_ROOT'];
		}
		myerror_logging(2,"document_root is: ".$doc_root);
		if ($welcome_letter_file_name== null || $welcome_letter_file_name == '' || trim($welcome_letter_file_name) == '')
		{
			myerror_log("ERROR! cant sent new user email. File name is blank!");//bad file name skip the email
			return false;
		} else if (file_exists($doc_root.'/resources/email_templates/new_user_welcome/'.$welcome_letter_file_name)) {
			// a welcome letter file exists so lets send welcome letter
			$skin_name = $_SERVER['SKIN']['skin_name'];
			$user_welcome_resource = clone $this->user_resource;
			$user_welcome_resource->set('skin',$_SERVER['SKIN']);
			$user_welcome_resource->set('skin_name',$skin_name);
			// now get images
			$skin_images_map_adapter = new SkinImagesMapAdapter($this->mimetypes);
			$skin_option[TONIC_FIND_BY_METADATA]['skin_id'] = $_SERVER['SKIN_ID'];
			$skins_images = $skin_images_map_adapter->select('',$skin_option);
			$skin_images = array_pop($skins_images);
			$user_welcome_resource->set('skin_images',$skin_images);
			
			$user_welcome_resource->_representation = '/email_templates/new_user_welcome/'.$welcome_letter_file_name;
			$representation =& $user_welcome_resource->loadRepresentation($this->file_adapter);
			$body = $representation->_getContent();
			myerror_logging(6, "welcome letter: ".$body);
			$user_email = $this->user_resource->email;
			if (!isProd())
			{
				$user_email = getProperty('test_addr_email');
				$test_string = 'TEST ';
			}
			myerror_logging(2,"about to send user welcome letter to: ".$user_email);
			$result = MailIt::sendUserEmailMandrill($user_email, $test_string."Welcome to $skin_name mobile/online ordering! Here's a treat to get started!", $body,$skin_name,$bcc);
			return $result;
			
		} else {
			myerror_log("ERROR! cant sent new user email. File does not exists: ".$welcome_letter_file_name);// bad file name skip the email
			return false;
		}
		
	}
	
	function getRefundError()
	{
		return $this->refund_error;
	}

	function setCommunication()
	{
		myerror_log("starting communication update");
		if ($this->user['user_id'] < 10000)
		{
			myerror_log("about to skip communication update as user is an admin user");
			$resource = Resource::dummyfactory(array("result"=>"true"));
			return $resource;
		}
		// clean the token
		$device_token = $this->request->data['token'];
		$device_token = str_replace('<', '', $device_token);
		$device_token = str_replace('>', '', $device_token);
		$device_token = str_replace(' ', '', $device_token);
		
		$this->adapter = new UserMessagingSettingMapAdapter($this->mimetypes);
		
		$map_data['skin_id'] = $_SERVER['SKIN_ID'];
		/* right here, an earthquake happened in NYC */
		$map_data['messaging_type'] = $this->request->data['messaging_type'];
		$device_id = $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'];
		if ($device_id == 'hackedthissorry') {
			$device_id = substr($device_token, -20);
		}
		$map_data['device_id'] = $device_id;
		$map_data['device_type'] = $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'];
		if ($this->request->data['gcm']) {
			$map_data['device_type'] = 'gcm';
		}
		$map_data['user_id'] = $this->user['user_id'];
		$options[TONIC_FIND_BY_METADATA] = $map_data;
		if ($resource =& Resource::findExact($this->adapter,'', $options)) {
			;// we got it
		} else {
			// this device and user does not exist in the table so create it
			$resource = Resource::Factory($this->adapter,$map_data);
			$resource->created = time();
		}
		
		//set token
		$resource->token = $device_token;
		$resource->active = $this->request->data['active'];
		$resource->save();
		return $resource;
	}
		
	function forgotPassword()
	{
		if ($this->request->data['email'])
		{
			$options[TONIC_FIND_BY_METADATA]['email'] = $this->request->data['email'];
			$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
			if ($user_resource = Resource::find($this->adapter,null,$options)){
			    if ($this->isUserResourceAGuest($user_resource)) {
                    myerror_log("ERROR! GUEST USER TRYING TO USE FORGOT PASSWORD!");
                    return createErrorResourceWithHttpCode(self::ERROR_FOR_GUEST_USER_FORGOT_PASSWORD, 500, 999, null);
                }
				if ($token = $this->getPasswordResetLink($user_resource->getDataFieldsReally())) {
					// all is good, the email has been sent and the CC info reset
					myerror_logging(2,"FORGOT PASSWORD EXECUTED NORMALLY with token: ".$token);
					$message = "We have processed your request. Please check your email for reset instructions.";
					return Resource::dummyfactory(array("user_message"=>$message,"token"=>$token));
				} else {
					myerror_log("ERROR!  FORGOT PASSWORD DID NOT EXECUTE NORMALLY!");
					$message = "We're sorry but we were unable to process your request. Please contact support";
					return createErrorResourceWithHttpCode($message, 500, $error_code, $error_data);
				}	
			} else {
				// email address does not exist in our system
				myerror_log("FORGOT PASSWORD email does not exist in our system: ".$this->request->data['email']);
				$message = "Sorry, that email is not registered with us. Please check your entry.";	
				return createErrorResourceWithHttpCode($message, 404, $error_code, $error_data);			
			}
		} else {
			myerror_log("ERROR!  NO EMAIL SENT in forgot password!");
			return createErrorResourceWithHttpCode("Error! No email was passed", 422, $error_code, $error_data);
		}
	}

	/**
     * @codeCoverageIgnore
     * @deprecated
     */
	function unlockOffer()
	{
		if (!$this->request->data['promo_id'])
		{
			$this->refund_error = 'No promo_id submitted';
			return false;
		} else {
			// ok so check the promo_id to make sure its a vald promo
			$promo_adapter = new PromoAdapter($this->mimetypes);
			if ($promo_resource = Resource::find($promo_adapter,''.$this->request->data['promo_id']))
			{
				myerror_logging(2,"we found the promo");
				/*
				 * we are now going to rely on PAT to determin if it can call this 
				if ($promo_resource->active == 'N')
					$error_message = "Promo is not active";
				else if ($promo_resource->logical_delete == 'Y')
					$error_message = "Promo is deleted";
				else if ($promo_resource->start_date > time())
					$error_message = "Promo has not started yet";
				else if ($promo_resource->end_date < time())
					$error_message = "Promo has expired";
				*/
			} else {
				$error_message = "This promo does not exist";
			}
			if ($error_message)
			{
				myerror_log("promo is bad: ".$error_message);
				$this->refund_error = $error_message;
				return false;
			}
		}
		$promo_start_date = $promo_resource->start_date;
		$promo_end_date = $promo_resource->end_date;
		
		if ($days_valid = $this->request->data['days_valid'])
		{
			//$date = date("Y-m-d");// current date
			//$date = strtotime(date("Y-m-d", strtotime($date)) . " +1 day");
			
			$now = time();
			$now = date("Y-m-d");
			if ($now < $promo_start_date)
			{	
				//$end_date = mktime(0,0,0, date("m",$promo_start_date)  , date("d",$promo_start_date)+$days_valid, date("Y",$promo_start_date));
				$end_date = date('Y-m-d',strtotime($promo_start_date . " +$days_valid day"));
			} else {
				//$end_date = mktime(0,0,0, date("m")  , date("d")+$days_valid, date("Y"));
				$end_date = date('Y-m-d',strtotime(date("Y-m-d") . " +$days_valid day"));
			}
			if ($end_date > $promo_end_date)
				$end_date = $promo_end_date; 
		} else {
			$end_date = $promo_end_date;
		}

		$puma = new PromoUserMapAdapter($this->mimetypes);
		$resource = $this->request->loadIfExistsCreateIfNot($puma);
		if ($resource->_exists)
		{
			$resource->times_allowed = $resource->times_allowed + 1;
		} else {
			$resource->times_allowed = $promo_resource->max_use;
		}
		$resource->end_date = $end_date;
		$resource->modified = time();
		if ($resource->save())
			return true;
		else
			$this->refund_error = $puma->getLastErrorText();
		return false;		
	}
	
	/**
     * @codeCoverageIgnore
     * @deprecated
     */
	function addPoints()
	{
		$user_resource =& Resource::find($this->adapter,$this->user['user_id']);
		$points = $this->request->data['points'];
		if ($points == null || $points == 0)
		{
			$this->refund_error = '  No points value submitted with request';
			return false;
		}
		$user_resource->points_lifetime = $user_resource->points_lifetime + $points;
		$user_resource->points_current = $user_resource->points_current + $points;
		if ($user_resource->save())
			return true;
		else 
			$this->refund_error = $this->adapter->getLastErrorText();
		return false;
	}
	
	function unlockAccount()
	{
		myerror_logging(2,"user flags are: ".$this->user['flags']);
		myerror_logging(2,"1st postion flag is: ".substr($this->user['flags'], 0,1));
		if (substr($this->user['flags'], 0,1) != '2')
		{
			$this->refund_error = "ERROR! Users account is currently not locked";
			return false;
		}
		$flags = '1'.substr($this->user['flags'],1);
		$user_resource =& Resource::find($this->adapter,$this->user['user_id']);
		$user_resource->flags = $flags;
		return $user_resource->save();
	}

	function undoBlacklisted()
	{
		$user_resource =& Resource::find($this->adapter,$this->user['user_id']);
		$saved = false;
		if($user_resource){
			if (substr($user_resource->flags,0,1) != "X") {
				return false;
			}
			$user_resource->flags = "1000000001";
			$saved = $user_resource->save();
			if($saved){
				$sql = "DELETE FROM Credit_Card_Update_Tracking WHERE user_id = $user_resource->user_id";
				$this->adapter->_query($sql);

				$dbla = new DeviceBlacklistAdapter($m);
				$sql = "DELETE FROM Device_Blacklist WHERE device_id = '".$dbla->getDeviceIdFromUserResourceForBlackList($user_resource)."'";
				$dbla->_query($sql);

			}else{
				$this->refund_error = $this->adapter->getLastErrorNo() == 1062? "Failed. User has already created account again using same email": $this->adapter->getLastErrorText();
			}
		}

		return $saved;
	}
	
	function sendPasswordResetLink($user)
	{
		$this->getPasswordResetLink($user);
	}
	
	function getPasswordResetLinkFromUserId($user_id)
	{
		$user_adapter = new UserAdapter($mimetypes);
		return $this->getPasswordResetLink($user_adapter->getRecord(array("user_id"=>$user_id)));
	}
	
	function getPasswordResetLink($user)
	{
		$password_adapter = new UserPasswordResetAdapter($mimetypes);
		$data['user_id'] = $user['user_id'];
		$data['retrieved'] = '0000-00-00';
		$options[TONIC_FIND_BY_METADATA] = $data;
		if ($resource = Resource::findExact($password_adapter,null,$options))
		{
			//user has an unused token, so reset it
			myerror_log("user has an unused token so reset it but re-use existing token: ".$resource->token);
			//$resource->token = $token;
			$token = $resource->token;
			$resource->retrieved = 0;
			$resource->created = time();
		} else {
			$token = $this->generatePasswordResetToken();
			$data['token'] = $token;
			unset($data['retrieved']);
			$resource = Resource::factory($password_adapter,$data);
		}
		$resource->modified = time();
		$resource->save();
		
		$skin_display_name = $_SERVER['SKIN']['skin_name'];
		$base_url = getBaseUrlForContext();
		
		$link = "$base_url/reset_password/$token";
		$subject = "Your $skin_display_name Information";
		$body = "Hi ".$user['first_name']."!\r\n\r\nWe've noticed you're having trouble logging in to the system. Here is a link for you to reset your password:\t".$link."\r\n\r\n\r\nThanks,\r\nThe SplickIt Team";
		MailIt::sendUserEmailMandrill($user['email'], $subject, $body, $skin_display_name.' Account Manager', $bcc);
		$user_resource =& Resource::find($this->adapter,$user['user_id']);
		$flags = str_replace('C', '0', $user_resource->flags);
		$user_resource->flags = $flags;
		$user_resource->last_four = "0000";
		$user_resource->save();
		$this->user_resource = $user_resource;
		return $token;
	}

	function generatePasswordResetToken()
	{
		$characters = 'abc1234def567890ghijk1234lm567nop890qrstuvwxyz';
		$code = '';
		for ($i = 0;$i < 30 ;$i++)
		{
			$new = mt_rand(0,45);
			$code = $code.substr($characters, $new,1);
			if ( $i==4 || $i==9 || $i==14 || $i==19 || $i==24)
				$code = $code.'-';
		}
		$pref = mt_rand(1111,9999);
		$token = $pref.'-'.$code;
		myerror_log("newly generated password reset token: ".$token);
		return $token;
	}
	
	function changePasswordWithToken()
	{
		$user_password_reset_adapter = new UserPasswordResetAdapter($mimetypes);
		$token = $this->request->data['token'];
		$p_data['token'] = $token;
		$p_data['retrieved'] = '0000-00-00 00:00:00';
		$p_options[TONIC_FIND_BY_METADATA] = $p_data;
		if ($p_resource = Resource::findExact($user_password_reset_adapter,null,$p_options))
		{
			$user_id = $p_resource->user_id;
			$user_resource = Resource::find(new UserAdapter($mimetypes),''.$user_id);
			$password = $this->request->data['password'];
			$epassword = Encrypter::Encrypt($password);
			myerror_logging(2,"encrypted password is: ".$epassword);
			$user_resource->password = $epassword;
			if ($user_resource->save())
			{
				$r_data['result'] = 'success';
				$r_data['user_message'] = 'Your password has been reset';
				$p_resource->retrieved = time();
				$p_resource->save();
			} else {
				$r_data = array("result"=>'failure',"user_message"=>"There was an error and your password has NOT been reset. Support has been notified","http_code"=>500);
				MailIt::sendErrorEmail("ERROR RESETTING PASSWORD", "there was an error resetting this users password: $user_id  ");
			}
			$return_resource = Resource::dummyFactory($r_data);
			return $return_resource;
		} else {
			return createErrorResourceWithHttpCode('Serious Error. The Users token could not be found', 404, 998, $error_data);
		}
	}
	
	function retrievePasswordToken()
	{
		$user_password_reset_adapter = new UserPasswordResetAdapter($mimetypes);
		$email = $this->request->data['email'];
		$user_data['email'] = $email;
		myerror_logging(1,"trying retrieve user record for password token with email: ".$email);
		$user_options[TONIC_FIND_BY_METADATA] = $user_data;
		if ($user_resource = Resource::findExact(new UserAdapter($mimetypes),null,$user_options))
		{
			$user_id = $user_resource->user_id;
			$p_data['user_id'] = $user_id;
			$p_data['retrieved'] = '0000-00-00 00:00:00';
			$p_options[TONIC_FIND_BY_METADATA] = $p_data;
			if ($resource = Resource::findExact($user_password_reset_adapter,null,$p_options))
			{
				unset($resource->created);
				unset($resource->modified);
			} else {
				$resource = returnErrorResource("User does not have an active token right now");
			}
		} else {
			$resource = returnErrorResource("No user matching submitted email");
		}
		return $resource;
	}

	/**
	 * @desc used to apply splicket credit to a users account.  if an order id is submitted, then credit is billed to the merchant rather than splickit.
	 * 
	 * @return returns an array with the message and the color of the message (green good, red bad)
	 */
	function issueSplickitCredit($amt,$process,$notes,$order_id = null) 
	{
		if ($amt == null || $amt == 0.00)
		{
				$return['message'] = "Sorry, Refund amount cannot be 0 or null";
				$return['error'] = 'red';
				return $return;
		}				

		$user_resource = Resource::find($this->adapter,''.$this->user['user_id']);
		$starting_balance = $user_resource->balance;
		$finishing_balance = $starting_balance+$amt;
		$user_resource->balance = $finishing_balance;
		if ($order_id != null && $order_id != 0) {
			myerror_log("in issue splickit credit we have an order id of: ".$order_id);
			if ($order_id < 1000) {
					$return['message'] = "Sorry, bad order id submitted: order_id=".$order_id;
					$return['error'] = 'red';
					return $return;
			}
		}
		if ($user_resource->save()) {
			$balance_change_data['user_id'] =  $this->user['user_id'];
			$balance_change_data['balance_before'] = $starting_balance;
			$balance_change_data['charge_amt'] = $amt;
			$balance_change_data['balance_after'] = $finishing_balance;
			$balance_change_data['process'] = $process;
			$balance_change_data['notes'] = $notes;
			$balance_change_data['order_id'] = $order_id;
			$bca = new BalanceChangeAdapter($this->mimetypes);
			$balance_change_resource = Resource::factory($bca,$balance_change_data);
			if ( $balance_change_resource->save())
			{
				$return['balance_change_id'] = $balance_change_resource->id;
				$return['message'] = "Credit been appplied, billing Splickit";
				$return['error'] = 'green';
			} else {
				$return['message'] = "ERROR! Credit has not appplied: ".$bca->getLastErrorText();
				$return['error'] = 'red';
				return $return;
			}
			return $return;
		} else {
			$return['message'] = "ERROR! Credit has not appplied: ".$this->adapter->getLastErrorText();
			$return['error'] = 'red';
			return $return;
		}
	}
	
	/**
     * @codeCoverageIgnore
     */
	function refundBlockOfOrders($table_name)
	{
		$order_adapter = new OrderAdapter($mimetypes);
		$sql = "SELECT * FROM ".$table_name." WHERE processed='0000-00-00 00:00:00'";//processed='N'";
		$options[TONIC_FIND_BY_SQL] = $sql;
		$refund_email_text_failures = '';
		$refund_email_text_successes = '';
		$successes = 0;
		$fails = 0;

		if ($records = $order_adapter->select('',$options))
		{
			foreach ($records as $record)
			{
				$order_id = $record['order_id'];
				$user_id = $record['user_id'];
				$note = $record['message'];
				// fake out user
				$this->user['user_id'] = $user_id;
				$this->request->data['note'] = $note;
				$order_controller = new OrderController($mt,$this->user,$this->request,5);
				$results = $order_controller->issueOrderRefund($order_id, 0.00);
				if ($results['result'] == 'success') {
					$successes = $successes + 1;
					$refund_email_text_successes .= "order_id: ".$order_id."  for user_id: ".$user_id;
					myerror_log("successfull refund of order_id: ".$order_id."  for user_id: ".$user_id);
					$s = 'Y';
					$error = '';
				} else {
					$fails = $fails + 1;
					$error = $this->getRefundError();
					myerror_log("FAILURE TO REFUND order id=".$order_id."! ".$error);
					$refund_email_text_failures .= "order_id: ".$order_id." FAILURE ".$error." \r\n";
					$s = 'N';
				}
				$dt_tm = date('Y-m-d H:i:s');
				$update_sql = "UPDATE ".$table_name." SET success='".$s."',fail_reason='".$error."',processed='".$dt_tm."' WHERE order_id=".$order_id." AND user_id=".$user_id." LIMIT 1";
				if ($order_adapter->_query($update_sql)) {
					myerror_log("we successuflly updated the row in teh refund table $table_name");
				} else {
					myerror_log("ERROR UPDATEING ROW IN $table_name");
				}
			}
		}
		$email_message = "successes: ".$refund_email_text_successes."  FAILURES \r\n ".$refund_email_text_failures;
		MailIt::sendErrorEmailTesting("results of block refund", $email_message);
		myerror_log("We had ".$successes." successful refunds and ".$fails." failures");
		$data['message'] = "We had ".$successes." successful refunds and ".$fails." failures";
		$data['result'] = "success";
		return $data;
	}
	
	static function getUserResourceFromUserId($user_id)
	{
		$user_adapter = new UserAdapter($mimetypes);
		if ($user_resource = Resource::find($user_adapter,''.$user_id))
			return $user_resource;
		else
			return false;
	}

	function checkForInvalidPhoneNumber($phone_number)
	{
		$justNums = $this->replaceEverythingButNotNumber($phone_number);
		if (strlen($justNums) == 11) 
			$justNums = preg_replace("/^1/", '',$justNums);

		return (strlen($justNums) != 10);
	}

	function replaceEverythingButNotNumber($number){
		return preg_replace("/[^0-9]/", '', $number);
	}
	
	function getTooManyFailedLoyaltyAttemptsMessage($brand_id)
	{
		return $this->adapter->getTooManyFailsLoyaltyMessage($brand_id);
	}
}	
?>