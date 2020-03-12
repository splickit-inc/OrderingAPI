<?php
class LoyaltyController extends SplickitController
{

	var $merchant_resource;
	var $brand_id;
	var $brand_resource;

    /**
     * @var Resource
     */
	var $brand_loyalty_rules_resource;

	var $brand_loyalty_rules_record;
	var $remote_account_info;  // as array
	var $local_account_info;

    /**
     * @var Resource
     */
	var $local_brand_points_map_resource;

	var $brand_points_list;
	var $complete_order;
	var $loyalty_number;
	var $service;
	var $service_response;
	var $loyalty_history;
	var $message;
	var $auto_join;
    var $brand_points_adapter;
	
	var $starting_point_value = 0;
	
	var $use_cheapest = true;
	var $max_points_per_order = 88888;

	var $use_static_loyalty_earn_arrays = false;
	var $menu_type_array_for_loyalty_earn = array();
	var $size_array_for_loyalty_earn = array();
	var $item_array_for_loyalty_earn = array();

	var $dollar_mulitplier_for_points_earned = 10;

	protected $loyalty_payment_name = 'Loyalty Rewards';

	protected $loyalty_earned_label = 'Congratulations! You just earned';

	protected $loyalty_earned_message = '{points} Points';

	protected $loyalty_balance_label = '* New Rewards Balance';

	protected $loyalty_balance_message = '{points} Points';

	protected $default_points_to_dollars_factor = 100;
	
	const IN_STORE_PURCHASE_LABEL = "InStore Purchase";
	const IN_STORE_CANCELLED_LABEL = "InStore Cancelled";
	const IN_STORE_REDEMPTION_LABEL = "InStore Redemption";
	const ORDER_LABEL_FOR_HISTORY = "Order";
	const ADMIN_ADJUST = "Admin Adjustment";

	const LOYALTY_NUMBER_SAVE_SUCCESS_MESSAGE = "Your loyalty number has been saved.";


	const PARKING_LOT_MESSAGE_TEXT = 'Welcome to %%skin_name%%! Start earning rewards today by downloading our app %%link%% or signing up online.';
	const PARKING_LOT_LINK = 'http://%%skin_name_id%%.splickit.com/sms_app_link';

	const SPLICKIT_POINTS_LOYALTY_PROGRAM = 'splickit_points';
	const SPLICKIT_EARM_LOYALTY_PROGRAM ='splickit_earn';
	const SPLICKIT_CLIFF_LOYALTY_PROGRAM = 'splickit_cliff';
	const REMOTE_LOYALTY_PROGRAM = 'remote';

	var $loyalty_history_headings = array(
		"transaction_date" => "DATE",
		"activity_type" => "Activity",
		"description" => "Points Earned/Spent",
		"amount" => "Balance"
	);

	var $loyalty_type_labels = array(
		array('label' => 'Points balance', 'type' => 'points')
	);

	const LOYALTY_NUMBER_DUPLICATE_MESSAGE = "Please Note: This phone number is in use by another user. Your remote loyalty number is NOW: ";

	/***  errors  ***/
	const INNACTIVE_ACCOUNT_ERROR = 'This user has not yet activated their account.';
	const INNACTIVE_ACCOUNT_ERROR_CODE = 1011;
	const LOYALTY_ACCOUNT_DOES_NOT_EXIST_ERROR = "Loyalty number does not exist.";
	const LOYALTY_ACCOUNT_DOES_NOT_EXIST_ERROR_CODE = 1010;
	const LOYALTY_NUMBER_EXISTS_FOR_REMOTE_JOIN_ERROR = "This loyalty number already exists for user: ";
	const LOYALTY_NUMBER_EXISTS_FOR_REMOTE_JOIN_ERROR_CODE = 1012;
	const NOT_A_VALID_10_DIGIT_PHONE_NUMBER_ERROR = "Not a valid 10 digit phone number";
	const NOT_A_VALID_10_DIGIT_PHONE_NUMBER_ERROR_CODE = 1013;


	const ORDER_ALREADY_PROCESSED_ERROR = "This order has already been processed.";
	const ORDER_ALREADY_PROCESSED_ERROR_CODE = 1020;
	const DUPLICATE_INSTORE_ORDER_ID_ERROR = "This order has already posted to the account.";
	const DUPLICATE_INSTORE_ORDER_ID_ERROR_CODE = 1021;
	const NO_ORDER_ID_SUBMITTED_ERROR = "No order_id was submitted";
	const NO_ORDER_ID_SUBMITTED_ERROR_CODE = 1022;
	const NO_ORDER_AMOUNT_SUBMITTED_ERROR = "No order_amount was submitted";
	const NO_ORDER_AMOUNT_SUBMITTED_ERROR_CODE = 1023;
	const ORDER_AMOUNT_IS_INVALID_ERROR = "order_amount must be greater than 0.00";
	const ORDER_AMOUNT_IS_INVALID_ERROR_CODE = 1024;
	const ORDER_ID_DOES_NOT_EXIST_ERROR = "Order id does not exist";
	const ORDER_ID_DOES_NOT_EXIST_ERROR_CODE = 1025;
    const ORDER_HISTORY_DOES_NOT_EXIST_ERROR = "Order history record does not exist for order id and location";
	const ORDER_HISTORY_DOES_NOT_EXIST_ERROR_CODE = 1026;
    const ORDER_ALREADY_CANCELLED_ERROR = "This order has already been cancelled.";
    const ORDER_ALREADY_CANCELLED_ERROR_CODE = 1027;


    const REDEMPTION_AMOUNT_GREATER_THEN_BALANCE_ERROR = "The redemption amount is greater than the loyalty balance.";
    const REDEMPTION_AMOUNT_GREATER_THEN_BALANCE_ERROR_CODE = 1100;

    const USER_IS_RESTRICTED_ERROR = "User has a restricted status. Please call customer service";
    const USER_IS_RESTRICTED_ERROR_CODE = 999999;



        function __construct($mt,$user,$request,$l = 0)
	{
		if (is_a($request,'Resource') && isset($request->data['brand_id']) && $request->data['brand_id'] > 0) {
			$brand_id = $request->data['brand_id'];
		} else if (isset($_SERVER['SKIN']['brand_id']) && 0 < $_SERVER['SKIN']['brand_id']) {
			$brand_id = $_SERVER['SKIN']['brand_id'];
		} else {
			$brand_id = 0;
		}
		if ($brand_id > 0) {
			parent::SplickitController($mt,$user,$request,$l);
			$this->adapter = new BrandPointsAdapter($mt);
			$this->brand_id = $brand_id;
			$options[TONIC_FIND_BY_METADATA] = array("brand_id"=>$this->brand_id,"loyalty"=>'Y');
			if ($brand_resource = Resource::find(new BrandAdapter(getM()), ''.$this->brand_id, $options)) {
				$this->brand_resource = $brand_resource; //all is good
				if ($brand_loyalty_rules_record = BrandLoyaltyRulesAdapter::staticGetRecord(array("brand_id"=>$this->brand_id),'BrandLoyaltyRulesAdapter')) {
					logData($brand_loyalty_rules_record,"Brand Loyalty Rules Record",3);
					$this->brand_loyalty_rules_record = $brand_loyalty_rules_record;
					$this->dollar_mulitplier_for_points_earned = $brand_loyalty_rules_record['earn_value_amount_multiplier'];
					$this->starting_point_value = $brand_loyalty_rules_record['starting_point_value'];

					switch ($this->brand_loyalty_rules_record['loyalty_type']){
						case self::SPLICKIT_EARM_LOYALTY_PROGRAM :{
							$this->loyalty_balance_message = '${dollar_balance}';
						} break;
						case self::SPLICKIT_CLIFF_LOYALTY_PROGRAM :{
							$this->loyalty_balance_message = '{points} Points and ${dollar_balance}';
						} break;
						default :{
							myerror_log("we are setting the loyalty messages to blank",3);
							$this->loyalty_balance_message = '';
							$this->loyalty_balance_label = '';
							$this->loyalty_earned_message = '';
							$this->loyalty_earned_label = '';
						} break;
					}
					$this->brand_points_list = $this->getBrandPointsList($this->brand_id);

				} else {
					//throw new BrandLoyaltyRulesNotConfiguredException();
				}
			} else {
				throw new NoBrandLoyaltyEnabledException();				
			}	
		} else {
			throw new BrandNotSetException();
		}
		if ($user) {
			// call get local which will set the loyalty number if it exists
			$this->local_account_info = $this->getLocalAccountInfo();
		}
		$this->setLoyaltyData($this->data);
	}

	function processRemoteRequest()
	{
		$data = $this->request->data;
		//$user = $this->getUserFromPhoneNumberInUrl($this->request->url);
		if ($loyalty_number = $this->getLoyaltyNumberFromUrl($this->request->url)) {
			$data['phone_number'] = $loyalty_number;
		}

		if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($data,"phone_number")) {
			if ($user_id = $this->getUserIdFromSubmittedPhoneNumber($data['phone_number'])) {
				if ($data['new_user']) {
					$existing_user_record = getStaticRecord(array("user_id"=>$user_id),"UserAdapter");
					$first_name = $existing_user_record['first_name'];
					$last_name = $existing_user_record['last_name'];
					return createErrorResourceWithHttpCode(self::LOYALTY_NUMBER_EXISTS_FOR_REMOTE_JOIN_ERROR."$first_name $last_name", 422, self::LOYALTY_NUMBER_EXISTS_FOR_REMOTE_JOIN_ERROR_CODE);
				}
				if ($this->user == null) {
					$this->user = getStaticRecord(array("user_id"=>$user_id),'UserAdapter');
                    if ($this->user == null) {
                    	// we have a logically deleted user
                        return createErrorResourceWithHttpCode(self::USER_IS_RESTRICTED_ERROR, 422, self::USER_IS_RESTRICTED_ERROR_CODE);
                    }
				}

				if ($this->isThisRequestMethodADelete()) {
					return $this->cancelRemoteOrderEarningsFromRequest($loyalty_number);
				} else if (isRequestMethodAPost($this->request)) {
					if ($order_id = $data['order_number']) {
						if ($this->hasRequestForDestination('cancel')) {
                            return $this->cancelRemoteOrderEarningsFromRequest($loyalty_number);
						} else if ($order_amount = $data['order_amount']) {
                            $order_amount = floatval($order_amount);
                            if ($order_amount < .01) {
                                return createErrorResourceWithHttpCode(self::ORDER_AMOUNT_IS_INVALID_ERROR, 422, self::ORDER_AMOUNT_IS_INVALID_ERROR_CODE);
                            }
                            $points = $this->processPointsBasedOnDollarMultiple($order_amount);
                            $notes = isset($data['location_id']) ? 'location ' . $data['location_id'] : '';
                            if ($this->recordPointsAndHistory($user_id, $order_id, self::IN_STORE_PURCHASE_LABEL, $points, 0, $notes)) {
                                return Resource::dummyfactory(array("success" => 'true'));
                            } else {
                                if ($this->message == self::DUPLICATE_INSTORE_ORDER_ID_ERROR) {
                                    return createErrorResourceWithHttpCode(self::DUPLICATE_INSTORE_ORDER_ID_ERROR, 409, self::DUPLICATE_INSTORE_ORDER_ID_ERROR_CODE);
                                } else {
                                    return createErrorResourceWithHttpCode("There was an unknown error recording the loyalty information", 500, 500);
                                }

                            }
                        } else if ($redemption_amount = $data['redemption_amount']) {
                            if ($this->isThisUserABlackListedUser()) {
                                return createErrorResourceWithHttpCode(self::USER_IS_RESTRICTED_ERROR, 422, self::USER_IS_RESTRICTED_ERROR_CODE);
                            }
							$redemption_amount = floatval($redemption_amount);
                            if ($redemption_amount < .01) {
                                return createErrorResourceWithHttpCode(self::ORDER_AMOUNT_IS_INVALID_ERROR, 422, self::ORDER_AMOUNT_IS_INVALID_ERROR_CODE);
                            }
							if (floatval($redemption_amount) > floatval($this->local_brand_points_map_resource->dollar_balance)) {
                                return createErrorResourceWithHttpCode(self::REDEMPTION_AMOUNT_GREATER_THEN_BALANCE_ERROR, 422, self::REDEMPTION_AMOUNT_GREATER_THEN_BALANCE_ERROR_CODE);
							}
                            $points = $this->processRedemptionPointsBasedOnDollarMultiple($redemption_amount);
                            $notes = isset($data['location_id']) ? 'location ' . $data['location_id'] : '';
                            if ($this->recordPointsAndHistory($user_id, $order_id, self::IN_STORE_REDEMPTION_LABEL, $points, 0, $notes)) {
                                return Resource::dummyfactory(array("success" => 'true'));
                            } else {
                                if ($this->message == self::DUPLICATE_INSTORE_ORDER_ID_ERROR) {
                                    return createErrorResourceWithHttpCode(self::DUPLICATE_INSTORE_ORDER_ID_ERROR, 409, self::DUPLICATE_INSTORE_ORDER_ID_ERROR_CODE);
                                } else {
                                    return createErrorResourceWithHttpCode("There was an unknown error recording the loyalty information", 500, 500);
                                }

                            }
						} else {
							return createErrorResourceWithHttpCode(self::NO_ORDER_AMOUNT_SUBMITTED_ERROR, 422, self::NO_ORDER_AMOUNT_SUBMITTED_ERROR_CODE);
						}
					} else {
						return createErrorResourceWithHttpCode(self::NO_ORDER_ID_SUBMITTED_ERROR, 422, self::NO_ORDER_ID_SUBMITTED_ERROR_CODE);
					}
				} else {
					// get Loyalty info
					$clean_loyalty_info = $this->getCleanLoyaltyInfoForRemoteRequest($this->local_brand_points_map_resource->getDataFieldsReally());
					return Resource::dummyfactory($clean_loyalty_info);
				}
			} else {
				$lplra = new LoyaltyParkingLotRecordsAdapter($m);
				if ($data['new_user']) {
					// determin if its a valid phone number with 10 digits
					// if so put in parking lot and send text
					// if not valid return error
					$phone_number = cleanAllNonNumericCharactersFromString($data['phone_number']);
					if (strlen($phone_number) == 10) {
						myerror_log("Phone number is valid");
					} else if (strlen($phone_number) == 11 && substr($phone_number,0,1) == 1) {
						$phone_number = substr($phone_number,1,10);
					} else {
						return createErrorResourceWithHttpCode(self::NOT_A_VALID_10_DIGIT_PHONE_NUMBER_ERROR,422,self::NOT_A_VALID_10_DIGIT_PHONE_NUMBER_ERROR_CODE);
					}
					$data['phone_number'] = $phone_number;
					$data['process'] = self::IN_STORE_PURCHASE_LABEL;
					$data['amount'] = $data['order_amount'];
					$data['remote_order_number'] = $data['order_number'];
					$data['location'] = $data['location_id'];
					if ($resource = $lplra->saveRemoteRecord($data)) {
						$records = $lplra->getRecords(array("brand_id"=>getBrandIdFromCurrentContext(),"phone_number"=>$phone_number));
						if (sizeof($records) == 1) {
							// only send text message on first one
							$message = str_replace('%%link%%',self::PARKING_LOT_LINK,self::PARKING_LOT_MESSAGE_TEXT);
							$message = str_replace('%%skin_name%%',getSkinNameForContext(),$message);
							$message = str_replace('%%skin_name_id%%',getIdentifierNameFromContext(),$message);
							$mmha = new MerchantMessageHistoryAdapter($m);
							$send_on_time = time();
							$hour = intval(date("H",$send_on_time));
							if ($hour > 4 && $hour < 18) {
								$send_on_time = mktime(18,0,0,date('m'),date('d'),date('Y'));
								myerror_log("due to late night reconciliation of remote loyalty we are resetting the text send on time to: ".date('Y-m-d H:i:s',$send_on_time));
							}
							$mmha->createMessageReturnResource(0,0,'T',$phone_number,$send_on_time,'I',"remote_order_number=".$data['order_number'],$message);
						} else {
							myerror_log("we have multiple records in the parking lot so dont send the text again");
						}
						return Resource::dummyfactory(array("success"=>'true',"id"=>$resource->id));
					} else {
						if ($lplra->getLastErrorNo() == MySQLAdapter::DUPLICATE_ENTRY) {
							return createErrorResourceWithHttpCode(self::ORDER_ALREADY_PROCESSED_ERROR,422,self::ORDER_ALREADY_PROCESSED_ERROR_CODE);
						}
						return createErrorResourceWithHttpCode("Internal error. Could not save record",500,500);
					}
					return Resource::dummyfactory(array("success"=>'true'));
				} else if ($this->message) {
					return createErrorResourceWithHttpCode($this->message, 422, 422);
				} else if ($records = $lplra->getRecords(array("brand_id"=>getBrandIdFromCurrentContext(),"phone_number"=>$loyalty_number))) {
					return createErrorResourceWithHttpCode(self::INNACTIVE_ACCOUNT_ERROR,422,self::INNACTIVE_ACCOUNT_ERROR_CODE);
				} else {
					return createErrorResourceWithHttpCode(self::LOYALTY_ACCOUNT_DOES_NOT_EXIST_ERROR,422,self::LOYALTY_ACCOUNT_DOES_NOT_EXIST_ERROR_CODE);
				}
			}
		} else {
			return createErrorResourceWithHttpCode("No phone_number was submitted.",422,422);
		}
	}

	function processAdminRequest()
	{
        $resource = new Resource();
        myerror_log("the method is: ".$this->request->method);
		$brand_adapter = new BrandAdapter($mimetypes);
		$options[TONIC_FIND_BY_SQL] = "select a.brand_id,b.brand_name From Brand_Loyalty_Rules a JOIN Brand2 b ON a.brand_id = b.brand_id where a.loyalty_type != 'remote'";
		if ($brands = $brand_adapter->select('',$options)) {
			myerror_log("we have the brands");
			logData($brands,"brands");
			$resource->set("brands",$brands);
		} else {
			myerror_log("we do not have the brands");
		}
		if (strtoupper($this->request->method) == 'POST') {
			myerror_log("wer are NOT in the GET");
			$data = $this->request->data;
			if ($user_brand_points_map_resource = UserBrandPointsMapAdapter::getUserBrandPointsMapRecordForLoyaltyNumberBrandCombo($data['loyalty_number'],$data['brand_id'])) {
				$user_id = $user_brand_points_map_resource->user_id;
				$user = getUserFromId($user_id);
				$this->user = $user;
                $this->local_account_info = $this->getLocalAccountInfo();
                if ($this->recordPointsAndHistory($user_id, $order_id, self::ADMIN_ADJUST, $data['points'])) {
                    $resource->set('error','green');
                    $resource->set('message','Points have been adjusted');
                } else {
                    $resource->set('error','red');
                    $resource->set('message','Could not update loyalty record');
				}
			} else {
				myerror_log("NO such match for loyatly number and brand");
                $resource->set('error','red');
                $resource->set('message','No such match for loyalty number and brand');
			}
		}
		return $resource;
	}

	function processManualLoyaltyAdjustmentReturnUserBrandPointsMap($user_id,$points,$notes)
	{
		if ($this->processManualLoyaltyAdjustment($user_id,$points,$notes)) {
			return $this->getLocalAccountInfo();
		}
	}

	function processManualLoyaltyAdjustment($user_id,$points,$notes)
	{
		if ($this->local_account_info == null) {
            $this->local_account_info = $this->getLocalAccountInfo();
		}
		if ($this->local_account_info) {
            if ($this->recordPointsAndHistory($user_id, null, self::ADMIN_ADJUST, $points,time(),$notes)) {
				return true;
            }
		}
		return false;
	}

	function cancelRemoteOrderEarningsFromRequest($loyalty_number)
	{
		if ($remote_order_id = $this->getRemoteOrderNumberFromUrl($this->request->url)) {
            return $this->cancelRemoteOrderEarnings($loyalty_number, $remote_order_id);
        } else if ($remote_order_id = $this->request->data['order_number']) {
			return $this->cancelRemoteOrderEvent($loyalty_number,$remote_order_id,$this->request->data['location_id']);
		} else {
			return createErrorResourceWithHttpCode(self::NO_ORDER_ID_SUBMITTED_ERROR, 422, self::NO_ORDER_ID_SUBMITTED_ERROR_CODE);
		}
	}

	function cancelRemoteOrderEvent($loyalty_number,$remote_order_id,$location_id)
	{
        $user_brand_loyalty_history_adapter = new UserBrandLoyaltyHistoryAdapter();
        $options[TONIC_FIND_BY_METADATA] = array("brand_id"=>getBrandIdFromCurrentContext(),"order_id"=>$remote_order_id,"user_id"=>$this->user['user_id']);
        if ($location_id) {
            $options[TONIC_FIND_BY_METADATA]['notes'] = "location $location_id";
		}
		try {
            if ($history_resources = Resource::findAll($user_brand_loyalty_history_adapter,null,$options)) {
                $history_resources_hash = createHashmapFromArrayOfResourcesByFieldName($history_resources,'process');
                if (isset($history_resources_hash[self::IN_STORE_CANCELLED_LABEL])) {
                    return createErrorResourceWithHttpCode(self::ORDER_ALREADY_CANCELLED_ERROR, 422, self::ORDER_ALREADY_CANCELLED_ERROR_CODE);
				}
				foreach ($history_resources as $history_resource) {
                    if ($history_resource->points_redeemed > 0) {
                        $points = $history_resource->points_redeemed;
                    } else if ($history_resource->points_added > 0) {
                        $points = -$history_resource->points_added;
                    }
                    if ($this->recordPointsAndHistory($this->user['user_id'], $remote_order_id, self::IN_STORE_CANCELLED_LABEL, $points)) {
                        $options[TONIC_FIND_BY_METADATA]['process'] = self::IN_STORE_CANCELLED_LABEL;
                        unset($options[TONIC_FIND_BY_METADATA]['notes']);
                        if ($history_resource2 = Resource::find($user_brand_loyalty_history_adapter, null, $options)) {
                            $history_resource2->points_added = -$history_resource->points_added;
                            $history_resource2->points_redeemed = -$history_resource->points_redeemed;
                            $history_resource2->notes = "location $location_id";
                            $history_resource2->save();
                        }
                        return Resource::dummyfactory(array("success" => 'true'));
                    } else {
                        return createErrorResourceWithHttpCode("Internal Error", 500, 0);
                    }
				}
            } else {
                return createErrorResourceWithHttpCode(self::ORDER_HISTORY_DOES_NOT_EXIST_ERROR, 422, self::ORDER_HISTORY_DOES_NOT_EXIST_ERROR_CODE);
            }
		} catch (Exception $e) {
        	// do something
		}

    }

	function cancelRemoteOrderEarnings($loyalty_number,$remote_order_id)
	{
		$user_brand_loyalty_history_adapter = new UserBrandLoyaltyHistoryAdapter();
		$options[TONIC_FIND_BY_METADATA] = array("brand_id"=>getBrandIdFromCurrentContext(),"order_id"=>$remote_order_id,"process"=>LoyaltyController::IN_STORE_PURCHASE_LABEL,"user_id"=>$this->user['user_id']);
		if ($history_resource = Resource::find($user_brand_loyalty_history_adapter,null,$options)) {
			$points = -$history_resource->points_added;
			if ($this->recordPointsAndHistory($this->user['user_id'], $remote_order_id, self::IN_STORE_CANCELLED_LABEL, $points)) {
				$options[TONIC_FIND_BY_METADATA]['process'] = self::IN_STORE_CANCELLED_LABEL;
				if ($history_resource2 = Resource::find($user_brand_loyalty_history_adapter,null,$options)) {
					$history_resource2->points_added = -$history_resource->points_added;
					$history_resource2->points_redeemed = -$history_resource->points_redeemed;
					$history_resource2->save();
				}
				return Resource::dummyfactory(array("success" => 'true'));
			}
		} else {
			return createErrorResourceWithHttpCode(self::ORDER_ID_DOES_NOT_EXIST_ERROR, 422, self::ORDER_ID_DOES_NOT_EXIST_ERROR_CODE);
		}
	}

	function getCleanLoyaltyInfoForRemoteRequest($loyalty_record)
	{
		$clean_data['points'] = $loyalty_record['points'];
		$clean_data['dollar_balance'] = $loyalty_record['dollar_balance'];
		$clean_data['brand_id'] = $loyalty_record['brand_id'];
		return $clean_data;
	}

	function getUserBrandPointsMapResourceFromLoyaltyNumberAndCurrentContext($loyalty_number)
	{
		$user_brand_points_map_adapter = new UserBrandPointsMapAdapter($m);
		$options[TONIC_FIND_BY_METADATA] = array("loyalty_number"=>$loyalty_number,"brand_id"=>getBrandIdFromCurrentContext());
		$options[TONIC_SORT_BY_METADATA] = " map_id DESC ";
		if ($ubpmr = Resource::findAll($user_brand_points_map_adapter,null,$options)) {
			if (sizeof($ubpmr) == 1) {
				$this->local_brand_points_map_resource = $ubpmr[0];
				return $ubpmr[0];
			} else if (sizeof($ubpmr) > 1) {
				// error multiple rows for this loyalty number and brand combination
				myerror_log("ERROR!!! There are multiple accounts matching this loyalty number:$loyalty_number  for brand id: ".getBrandIdFromCurrentContext());
				$this->message = "There are multiple accounts matching this loyalty number. Remote functions not possible.";
                $this->local_brand_points_map_resource = array_shift($ubpmr);
                $i = 10;
                foreach($ubpmr as $duplicate_phone_loylaty_resource) {
                	$duplicate_phone_loylaty_resource->loyalty_number = $duplicate_phone_loylaty_resource->loyalty_number."$i";
                    $duplicate_phone_loylaty_resource->save();
                    $i++;
				}
                return $this->local_brand_points_map_resource;
			}
		}
	}

	function getUserIdFromSubmittedPhoneNumber($phone_number)
	{
		$clean_phone_number = cleanAllNonNumericCharactersFromString($phone_number);
        if (strlen($clean_phone_number) == 11 && substr($clean_phone_number,0,1) == 1) {
            $clean_phone_number = substr($clean_phone_number,1,10);
        }
		if ($ubpmr = $this->getUserBrandPointsMapResourceFromLoyaltyNumberAndCurrentContext($clean_phone_number)) {
			return $ubpmr->user_id;
		}
	}

	function getRemoteOrderNumberFromUrl($url)
	{
		if (preg_match('%/orders/([0-9a-zA-Z\-]+)%', $url, $matches)) {
			return $matches[1];
		} else if (preg_match('%/order/([0-9a-zA-Z\-]+)%', $url, $matches)) {
            return $matches[1];
        }
	}

	function getLoyaltyNumberFromUrl($url)
	{
		if (preg_match('%/loyalty/([0-9a-zA-Z\-]+)%', $url, $matches)) {
			return $matches[1];
		}
	}

	function getBadLoyaltyNumberMessage()
	{
		return 'Sorry but the loyalty number you entered, '.$this->getLoyaltyNumber().', is not valid';
	}

	function getPaymentAllowedForThisOrder($loyalty_balance,$order_info)
	{
		// reset order amt with promo to accurately calculate the max payable amount
		$order_info['order_amt'] = $order_info['order_amt'] + $order_info['promo_amt'];
		// this will give us grand_total or order_amt.  default is order amt
		$max_payable_order_amount = ($this->brand_loyalty_rules_record) ? $order_info[$this->brand_loyalty_rules_record['loyalty_order_payment_type']] : $order_info['order_amt'];
		myerror_log("Default max payable amoutn is: $max_payable_order_amount",3);
		if ($loyalty_balance < $max_payable_order_amount) {
			$max_payable_order_amount = $loyalty_balance;
			myerror_log("ACTUAL max payable amoutn is: $max_payable_order_amount",3);
		}
		return $max_payable_order_amount;
	}
	
	/**
	 * 
	 * @desc takes the resource that is submitted as part of the user update and either links or creates the loyalty association.  if it is a create, a field is set on the resource called 'created_loyalty_number' for use later.  Returns false if linking or creating is bad.
	 * @param Resource $user_resource_with_other_loyalty_data
	 */
	function createOrLinkAccountFromDataResource(&$user_resource_with_other_loyalty_data)
	{
		$this->setLoyaltyData($user_resource_with_other_loyalty_data->getDataFieldsReally());
		if ($this->createOrLinkAccount($user_resource_with_other_loyalty_data->user_id)) {
			if ($this->isCreateLoyaltyAccountTrue()) {
				$user_resource_with_other_loyalty_data->set('created_loyalty_number', $this->getLoyaltyNumber());
			} else {
				if (is_a($this,'LiteLoyaltyController')) {
					$user_resource_with_other_loyalty_data->user_message = self::LOYALTY_NUMBER_SAVE_SUCCESS_MESSAGE;
				}
			}
			return true;
		}
		return false;
	}
	
	function createOrLinkAccount($user_id,$points = 0)
	{
		if ($this->isCreateLoyaltyAccountTrue()) {
			$user_brand_loyalty_resource = $this->createAccount($user_id,$points);
		} else if ($this->getLoyaltyNumber()) {
			$user_brand_loyalty_resource = $this->linkAccount($user_id,$points);
		} else {
			// no loyalty number submitted and no create.  just return true.  boy is that poor coding.
			return true;
		}
		return $user_brand_loyalty_resource;
	}
		
	function addLoyaltyRecord($user_id,$points)
	{
   		$user_brand_loyalty_adapter = new UserBrandPointsMapAdapter($mimetypes);
   		return $user_brand_loyalty_adapter->addUserLoyaltyRecord($user_id, $this->brand_id, $this->getLoyaltyNumber(), $points);
	}
	
	function addUserBrandLoyaltyRecord($user_id,$loyalty_number,$points)
	{
   		$user_brand_loyalty_adapter = new UserBrandPointsMapAdapter($mimetypes);
   		return $user_brand_loyalty_adapter->addUserLoyaltyRecord($user_id, $this->brand_id, $loyalty_number, $points);
	}
	
	function processOrderFromCompleteOrder($complete_order)
	{
		$this->complete_order = $complete_order;
		if ($this->loyalty_number) {
			$points = $this->getPointsAndSendIfApplicable($complete_order);
			$this->recordPointsAndHistory($complete_order['user_id'], $complete_order['order_id'], 'Order', $points);

			if ($this->isHomeGrownLoyalty()) {
				// surprise and delight funcationality
				$active_loyalty_awards_for_brand = LoyaltyBrandBehaviorAwardAmountMapsAdapter::getBrandBehaviorAwardRecords(getBrandIdFromCurrentContext());
				foreach ($active_loyalty_awards_for_brand as $active_award) {
					if ($this->isAwardWithinActiveDates($active_award,$complete_order['pickup_date2'])) {
						if ($surprise_points = $this->processAwardOrderCombination($active_award,$complete_order,$points)) {
							$points = $points + $surprise_points;
							$this->points_earned = $points;
							$this->message = "You earned $points points on this order.";
						}
					} else {
						myerror_log("award is not within the dates");
					}
				}
			}
			return true;
		}
	}

	function validatePointsForOrderItem(&$item)
	{
		if ($item['points_used'] < 1) {
			myerror_log("ok item does not have points so skip");
			// hack alert
			unset($item['points_used']);
			unset($item['amount_off_from_points']);
			return;
		}
		if ($brand_points_item_data = $this->brand_points_adapter->validateCartItem($item)) {
			$item['points_used'] = $brand_points_item_data['points'];
			$item['amount_off_from_points'] = $brand_points_item_data['amount_off_from_points'];
			myerror_logging(3,"we have a validated pay with points item in the cart");
			return $brand_points_item_data['points'];
		} else {
			myerror_log("ERROR!  something is wrong with this item!  item_size_id: ".$item['item_size_id']);
			throw new PayWithPointsException("We're sorry, but there was a problem with your pay with points request. Please re-select your option and try again");
		}
	}

	function validateTotalPointsAgainstRulesAndUserAccount($total_validated_points)
	{
		if ($total_validated_points > $this->brand_loyalty_rules_record['max_points_per_order']) {
			myerror_log("ERROR! too many points used. max per order for this brand is: " . $this->brand_loyalty_rules_record['max_points_per_order']);
			return createErrorResourceWithHttpCode("We're sorry, but there is max points per order of " . $this->brand_loyalty_rules_record['max_points_per_order'] . ", please remove something from your cart.  If you feel you have received this message in error, please contact customer support", 422, 999);
		}
		if ($user_brand_points_map_resource = $this->local_brand_points_map_resource) {
			if ($total_validated_points > $user_brand_points_map_resource->points) {
				myerror_log("ERROR! User does NOT have enough points (".$user_brand_points_resource->points.") to place this order: ".$total_validated_points);
				return createErrorResourceWithHttpCode("We're sorry, but it appears you do not have enough points in your account to place this order.  If you feel you have received this message in error, please contact customer support",422,999);
			} else {
				myerror_logging(3,"The pay with points order has been validated. Place the order");
			}
		} else {
			MailIt::sendErrorEmail("SERIOUS LOYALTY ERROR","someone paying with points that does not have a loyalty account");
			return createErrorResourceWithHttpCode("There is a probem with your loyalty account and you cannot pay with points at this time. Please contact customer service.",500,999);
		}
	}

	function isAwardWithinActiveDates($award_record,$pickup_date)
	{
		return $pickup_date >= $award_record['first_date_available'] && $pickup_date <= $award_record['last_date_available'];
	}

	function processAwardOrderCombination($active_award,$complete_order,$base_points_awarded_for_order)
	{
		if ($this->isAwardMet($active_award,$complete_order)) {
			if ($active_award['process_type'] != 'free_item') {
				$points = $this->getAwardValue($active_award,$base_points_awarded_for_order);
				$this->recordPointsAndHistory($this->user['user_id'],$complete_order['order_id'],$this->getProcessLabelForAward($active_award),$points);
				return $points;
			} else {
				// need to figure out how to do this
				throw new Exception("write code to process free item award");
			}


		}
	}

	function getProcessLabelForAward($active_award)
	{
		if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($active_award,'history_label')) {
			return $active_award['history_label'];
		} else {
			$award_type = $active_award['trigger_name'];
			return str_replace('_',' ',$award_type)." Award";
		}

	}

	function getAwardValue($active_award,$base_points_awarded_for_order)
	{
		if ($active_award['process_type'] == 'fixed') {
			return $active_award['value'];
		} else if ($active_award['process_type'] == 'percent') {
			return ($active_award['value'] * $base_points_awarded_for_order)/100 ;
		} else if ($active_award['process_type'] == 'multiplier') {
			// we need to subtract 1 from the multiplier because the order has already processed one.
			return (($active_award['value']-1) * $base_points_awarded_for_order) ;
		}
		return 0;
	}

	function isAwardMet($active_award,$complete_order)
	{
		$behaviour = $active_award['trigger_name'];
		$method_name = 'is'.str_replace('_','',$behaviour).'AwardMet';
		return $this->$method_name($complete_order,$active_award['trigger_value']);

	}

	function isOrderMinimumAwardMet($complete_order,$minimum)
	{
		return $minimum < ($complete_order['order_amt'] + $complete_order['promo_amt']);
	}

	function isOrderTypeAwardMet($complete_order,$type)
	{
		if ($complete_order['order_type'] == $type) {
			return true;
		} else {
			// check for catering here
			return false;
		}
	}

	function isOrderDayPartAwardMet($complete_order,$day_part_string)
	{
		$dps = explode(' ',$day_part_string);
		$satisfied = false;
		foreach ($dps as $dp) {
			if (preg_match('%([a-zA-Z]{6,9})%', $dp, $matches)) {
				$satisfied = strtolower($complete_order['pickup_day']) == strtolower($matches[0]);
			} else if (preg_match('%([0-9:\-]+)%', $dp, $matches2)) {
				$time_string = $matches2[0];
				$tss = explode('-',$time_string);
				$start_time = $tss[0];
				$end_time = $tss[1];
				$pickup_time = $complete_order['pickup_time_military'];
				$satisfied = ($pickup_time >= $start_time && $pickup_time <= $end_time);
			}
		}
		return $satisfied;
	}

	/**
	 * 
	 * @desc 
	 * @param unknown_type $user_id
	 * @param unknown_type $brand_id
	 * @param unknown_type $order_id
	 * @param unknown_type $process
	 * @param unknown_type $points
	 */ 
	protected function recordPointsAndHistory($user_id,$order_id,$process,$points,$action_date = 0,$notes = '')
	{
		myerror_log("starting recordPoints and History with point value of: $points");
		$ublha = new UserBrandLoyaltyHistoryAdapter(getM());
		if ($points != 0) {
			if ($loyalty_history_resource = $ublha->recordLoyaltyTransaction($user_id, $this->brand_id, $order_id,$process, $points,0,0,$action_date)) {
				if ($user_brand_points_map_resource = $this->processPointsForLoyaltyType($user_id, $this->brand_id, $points)) {
					$loyalty_history_resource->current_points = $user_brand_points_map_resource->points;
					$loyalty_history_resource->current_dollar_balance = $user_brand_points_map_resource->dollar_balance;
					if ($notes != null && $notes != '') {
                        $loyalty_history_resource->notes = $notes;
					}
					return $loyalty_history_resource->save();
				} else {
					if ($id = $loyalty_history_resource->id) {
                        $sql = "DELETE FROM User_Brand_Loyalty_History WHERE id = $id LIMIT 1";
                        $ublha->_query($sql);
					}
					return false;
				}
			} else if ($ublha->getLastErrorNo() == 1062) {
				myerror_log("DUPLICATE instore loyalty post");
				$this->message = self::DUPLICATE_INSTORE_ORDER_ID_ERROR;
				return false;
			} else {
				myerror_log("Could not record loyalty history. will now try to updating loyalty record");
				if ($user_brand_points_map_resource = $this->processPointsForLoyaltyType($user_id, $this->brand_id, $points)) {
					return true;
				} else {
					return false;
				}
			}

		}
	}
	
	function processPointsForLoyaltyType($user_id, $brand_id, $points)
	{
		$ubpa = new UserBrandPointsMapAdapter($mimetypes);
		$ubpa->setWriteDb();
		$this->points_earned = $points;
		if ($type = $this->brand_loyalty_rules_record['loyalty_type']) {
			$user_brand_points_map_resource = Resource::find($ubpa,'',array(TONIC_FIND_BY_METADATA=>array("user_id"=>$user_id,"brand_id"=>$brand_id)));
			$method_name = 'processAs'.$type;
			if ($this->brand_loyalty_rules_record['loyalty_type'] == 'splickit_cliff') {
				$total_points = $user_brand_points_map_resource->points + $points;
				if ($total_points > $this->brand_loyalty_rules_record['cliff_value']) {
					$number_of_awards = (int)($total_points/$this->brand_loyalty_rules_record['cliff_value']);
					$dollar_value_of_awards = $number_of_awards*$this->brand_loyalty_rules_record['cliff_award_dollar_value'];
					$user_brand_points_map_resource->dollar_balance = $user_brand_points_map_resource->dollar_balance + $dollar_value_of_awards;
					$user_brand_points_map_resource->points = $total_points % $this->brand_loyalty_rules_record['cliff_value'];
					$this->points_earned = $points;
				} else {
					$user_brand_points_map_resource->points = $total_points;
				}
				$user_brand_points_map_resource->save();
				return $user_brand_points_map_resource;
			} else if ($this->brand_loyalty_rules_record['loyalty_type'] == 'splickit_earn') {
				$user_brand_points_map_resource->points = $user_brand_points_map_resource->points + $points;
				$user_brand_points_map_resource->dollar_balance = $this->processPointsToDollarsEarnAsYouGoType($user_brand_points_map_resource->points);
				$user_brand_points_map_resource->save();
				return $user_brand_points_map_resource;
			} else if ($this->brand_loyalty_rules_record['loyalty_type'] == 'splickit_punchcard') {
				throw new Exception("BUILD SPLICKIT PUNHCARD METHOD");
			}
		}
		if ($user_brand_points_map_resource = $ubpa->addPointsToUserBrandPointsRecord($user_id, $brand_id, $points) ) {
			return $user_brand_points_map_resource;
		}
	}

	function reverseLoyaltyTransactionsForOrderId($order_id)
	{
		myerror_log("Starting reverseLoyaltyTransactionsForOrderId");
		$ublha = new UserBrandLoyaltyHistoryAdapter($m);
		if ($history_transactions_as_resources = $ublha->getLoyaltyHistoryByOrderId($order_id)) {
			$points_added = 0;
			$points_redeemed = 0;
			foreach ($history_transactions_as_resources as $loyalty_history_transaction) {
				$points_added = $points_added + $loyalty_history_transaction->points_added;
				$points_redeemed = $points_redeemed + $loyalty_history_transaction->points_redeemed;
				$loyalty_history_transaction->process = $loyalty_history_transaction->process."-REVERSED";
				$loyalty_history_transaction->_exists = false;
				$loyalty_history_transaction->id = null;
				$loyalty_history_transaction->points_added = -$loyalty_history_transaction->points_added;
				$loyalty_history_transaction->points_redeemed = -$loyalty_history_transaction->points_redeemed;
				$loyalty_history_transaction->current_points = 0;
				$loyalty_history_transaction->current_dollar_balance = 0.00;
				$loyalty_history_transaction->save();
			}
			myerror_log("There were $points_added points added and $points_redeemed points redeemed");
			if (($points_added + $points_redeemed) > 0.00) {
				logData($this->brand_loyalty_rules_record,"Brand Loyalty rules");
				// we have some change so lets reverse it
				$user_brand_points_map_resource = $this->getLocalAccountInfoAsResource();
				logData($user_brand_points_map_resource->getDataFieldsReally(),"User Brand Points Map");
				if ($this->brand_loyalty_rules_record['loyalty_type'] == 'splickit_cliff') {
					myerror_log("Doing refund for splickit_cliff");
					$user_brand_points_map_resource->points =  $user_brand_points_map_resource->points - $points_added;
					$user_brand_points_map_resource->dollar_balance = $user_brand_points_map_resource->dollar_balance + $points_redeemed;
					if ($user_brand_points_map_resource->points < 0) {
						$remainder = ($user_brand_points_map_resource->points%$this->brand_loyalty_rules_record['cliff_value']);
						if ($remainder < 0) {
							$new_points = $remainder + $this->brand_loyalty_rules_record['cliff_value'];
						} else if ($remainder == 0) {
							$new_points = 0;
						}
						myerror_log("new points are $new_points");
						$user_brand_points_map_resource->points = $new_points;
						$number_of_awards = (int)($points_added/$this->brand_loyalty_rules_record['cliff_value']);
						$dollar_value_of_awards = $number_of_awards*$this->brand_loyalty_rules_record['cliff_award_dollar_value'];
						myerror_log("dollar value of awards to be refunded");
						$user_brand_points_map_resource->dollar_balance = $user_brand_points_map_resource->dollar_balance - $dollar_value_of_awards;
					}
				} else if ($this->brand_loyalty_rules_record['loyalty_type'] == 'splickit_earn') {
					myerror_log("Doing refund for splickit_earn");
					$user_brand_points_map_resource->points = $user_brand_points_map_resource->points - $points_added + $points_redeemed;
					$user_brand_points_map_resource->dollar_balance =  $this->processPointsToDollarsEarnAsYouGoType($user_brand_points_map_resource->points);
				} else if ($this->brand_loyalty_rules_record['loyalty_type'] == 'splickit_punchcard') {
					myerror_log("we need to build reufund for splickit_punchcard");
					MailIt::sendErrorEmail("ATTEPTED REFUND OF splickit_punchcard order.","Guess its time to build it. NOW!!!!");
					return false;
				} else {
					myerror_log("NOT a home grown loyalty program so we skin the refund");
					return false;
				}
				if ($user_brand_points_map_resource->dollar_balance < 0  || $user_brand_points_map_resource->points < 0) {
					$number = $user_brand_points_map_resource->loyalty_number;
					myerror_log("WE HAVE ENDED WITH A NEGATIVE VALUE FOR LOYALTY BALANCE!!!!  could be due to someone trying to refund a loyalty earn after they have used it");
					MailIt::sendErrorEmail("Negative value for loyalty on refund call","Loyalty balance has ended up negative after refund. Possibly due to refund of balance that was used already. Loyalty number: $number");
					MailIt::sendErrorEmailSupport("Negative value for loyalty on refund call","Loyalty balance has ended up negative after refund. Possibly due to refund of balance that was used already. Loyalty number: $number");
				}
				$user_brand_points_map_resource->save();
				// now update last history record
				$loyalty_history_transaction->current_points = $user_brand_points_map_resource->points;
				$loyalty_history_transaction->current_dollar_balance = $user_brand_points_map_resource->dollar_balance;
				$loyalty_history_transaction->save();
			}
		}
	}

	function processPointsToDollarsEarnAsYouGoType($points)
	{
		return $points/$this->default_points_to_dollars_factor;
	}
	
	/**
	 * @desc will determine the point value for the order is positive or negative.  awarded or redeemed.  and then call the send method. send method is overridden by custom loyalty objects.
	 * @desc (-negative points indicate redemption, +positive points indicate earned)
	 * @param complete order array
	 * @return int Points that were awarded (- or +)
	 */
	function getPointsAndSendIfApplicable($complete_order)
	{
		if (isset($complete_order['points_used'])) {
			$points = - $complete_order['points_used'];
		} else {
			if ($points = $this->getPointsEarnedFromCompleteOrder($complete_order)) {
				$this->message = "You earned $points points on this order.";
			}
		}
		myerror_log("loyalty: points on order is ".$points);
		$result = $this->sendLoyaltyOrderEvent($complete_order,$points);
		return $points;
	}
		
	/**
	 * 
	 * @desc this should return the data as an array?  maybe?  hmmm..
	 * @param string $loyalty_number
	 * @return array
	 */
	function getLocalAccountInfo()
	{
		if ($this->local_brand_points_map_resource = $this->getLocalAccountInfoAsResource()) {

			$data = $this->local_brand_points_map_resource->getDataFieldsReally();
			return cleanData($data); 
		} else {
			return null;
		}
	}
	
	function getLocalAccountInfoAsResource()
	{
		$ubpma = new UserBrandPointsMapAdapter($mimetypes);
		if ($user_brand_points_map_resource = $ubpma->getExactResourceFromData(array("user_id"=>$this->user['user_id'],"brand_id"=>$this->brand_id))) {
			$this->loyalty_number = $user_brand_points_map_resource->loyalty_number;
			return $user_brand_points_map_resource;
		} else {
			return null;
		}	
	}

	/**
	 * @param $data
	 * @return Resource
	 */
	function updateLoyaltyNumberOnBrandPointsMapResourceFromRequestData($data)
	{
		if ($this->isNonRemoteLoyalty()) {
			if ($loyalty_number = $data['loyalty_phone_number']) {
				$loyalty_number = cleanAllNonNumericCharactersFromString($loyalty_number);
				if (strlen($loyalty_number) < 10) {
					return createErrorResourceWithHttpCode("Not a valid phone number. Must be 10 digits.", 422, 422);
				}
				$data['loyalty_number'] = $loyalty_number;
			}
			if ($loyalty_number = $data['loyalty_number']) {
				if ($this->updateLoyaltyNumberOnBrandPointsMapResource($loyalty_number)) {
					$message = isset($this->message) ? $this->message : self::LOYALTY_NUMBER_SAVE_SUCCESS_MESSAGE;
					return Resource::dummyfactory(array("user_message"=>$message,"success"=>"true"));
				} else {
					return createErrorResourceWithHttpCode("There was an error and the loyalty number was not updated",500,500);
				}
			} else {
				return createErrorResourceWithHttpCode("No loyalty number submitted.",422,422);
			}
		} else {
			return createErrorResourceWithHttpCode("Unauthorized access. Cannot update third party loyalty programs with this endpoint",401,401);
		}
	}

	/**
	 * @description  This function can only be used by HomeGrown and LoyaltyLite programs
	 * @param $submitted_loyalty_number
	 * @return mixed
	 */
	function updateLoyaltyNumberOnBrandPointsMapResource($submitted_loyalty_number)
	{

		if (isset($this->local_brand_points_map_resource)) {
			$submitted_loyalty_number = $this->doCheckForDuplicateNumberReturnLoyaltyNumber(new UserBrandPointsMapAdapter($m),$submitted_loyalty_number);
			$this->local_brand_points_map_resource->loyalty_number = $submitted_loyalty_number;
			return $this->local_brand_points_map_resource->save();
		} else if ($this->isLoyaltyLite()) {
			if ($new_resource = Resource::createByData(new UserBrandPointsMapAdapter($m),array("user_id"=>$this->user['user_id'],"loyalty_number"=>$submitted_loyalty_number,"brand_id"=>getBrandIdFromCurrentContext()))) {
				return true;
			}
		} else {
			// odd case of no loyalty record on a homegrown loyalty
			myerror_log("no loyalty record to update. user needs a loyalty record so check for home grown and create if so");
			if ($this->isHomeGrownLoyalty()) {
				myerror_log("Probably a temp user conversion",3);
				$this->data['contact_no'] = $submitted_loyalty_number;
				if ($this->createAccount($this->user['user_id'])) {
					return true;
				}
			} else {
				MailIt::sendErrorEmail("Weird case of loyalty update with no loyalty record for remote loyalty plan.","INVESTIGATE NOW");
			}

		}

	}

	function getExistingLoyaltyRecordForCurrentBrandAndLoyaltyNumber($loyalty_number)
	{
		$user_brand_points_map_adapter = new UserBrandPointsMapAdapter($m);
		if ($existing_record_with_same_number_and_brand = $user_brand_points_map_adapter->getRecord(array("brand_id"=>$this->brand_id,"loyalty_number"=>$loyalty_number))) {
			return $existing_record_with_same_number_and_brand;
		}
	}

	function doCheckForDuplicateNumberReturnLoyaltyNumber($user_brand_points_map_adapter,$loyalty_number)
	{
		if ($existing_records_with_same_number_and_brand = $user_brand_points_map_adapter->getRecords(array("brand_id"=>$this->brand_id,"loyalty_number"=>$loyalty_number))) {
			$existing_hash_by_user_id = createHashmapFromArrayOfArraysByFieldName($existing_records_with_same_number_and_brand,'user_id');
			if ($existing_hash_by_user_id[$this->local_account_info['user_id']]) {
				return $loyalty_number;
			}
			$code = (string) rand(10,99);
			$loyalty_number = "$loyalty_number"."$code";
			$this->message = self::LOYALTY_NUMBER_DUPLICATE_MESSAGE."$loyalty_number";
		}
		return $loyalty_number;
	}
	
// *********  METHODS TO BE OVERRIDEN BY EXTERNAL LOYALTY PROGRAMS ******/	
	function createAccount($user_id,$points) 
	{
		if (isUserATempUserByUserId($user_id)) {
			// do not create loyalty accounts for temp users
			return;
		}
		if ($phone_number = $this->data['contact_no']) {
			$loyalty_number = preg_replace("/[^0-9]/", '', $phone_number);
		} else {
			$loyalty_number = "splick-".generateCode(10);
		}
   		$user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
		$loyalty_number = $this->doCheckForDuplicateNumberReturnLoyaltyNumber($user_brand_points_map_adapter,$loyalty_number);
   		if ($user_brand_points_map_resource = $user_brand_points_map_adapter->createUserLoyaltyAccount($user_id, $this->brand_id, $loyalty_number, $points)) {
   			if ($this->starting_point_value > 0) {
   				$this->recordPointsAndHistory($user_id,$order_id,"Join Bonus",$this->starting_point_value);
				$user_brand_points_map_resource = $user_brand_points_map_resource->refreshResource();
			}
   			$this->local_brand_points_map_resource = $user_brand_points_map_resource;
			$this->loyalty_number = $loyalty_number;
			// now check for parking lot records
			$this->saveParkingLotPurchasesIfTheyExist($loyalty_number);
   			return $user_brand_points_map_resource;
   		}
	}

	function saveParkingLotPurchasesIfTheyExist($loyalty_number)
	{
		myerror_log("checking for parking lot records attached to loyalty number: $loyalty_number and brand_id: ".getBrandIdFromCurrentContext());
		$loyalty_parking_lot_records_adapter = new LoyaltyParkingLotRecordsAdapter($m);
		if ($records = $loyalty_parking_lot_records_adapter->getRecords(array("brand_id"=>getBrandIdFromCurrentContext(),"phone_number"=>$loyalty_number))) {
			myerror_log("we have orphaned parking lot records: ".sizeof($records));
			foreach ($records as $record) {
				$points = round($record['amount'] * $this->dollar_mulitplier_for_points_earned);
				$notes = $record['location'] != '' &&  $record['location'] != null ? 'location '.$record['location'] : '';
				if ($this->recordPointsAndHistory($this->local_brand_points_map_resource->user_id, $record['remote_order_number'], self::IN_STORE_PURCHASE_LABEL, $points,$record['created'],$notes)){
					$id = $record['id'];
                    myerror_log("we have saved the parking lot record to the user account. now delete the parking lot record with id: $id");
                    $sql = "DELETE FROM Loyalty_Parking_Lot_Records WHERE id = $id";
					myerror_log("$sql");
					$loyalty_parking_lot_records_adapter->_query($sql);
				} else {
					myerror_log("ERROR!!! couldn't save parking lot record to users account!");
				}
			}
			return true;
		} else {
			myerror_log("No parking lot Records exist");
			return false;
		}
	}


	function linkAccount($user_id,$points)
	{
		return $this->addLoyaltyRecord($user_id, $points);
	}
	
	function getAccountInfoForUserSession() {

        if ($data = $this->getLocalAccountInfo()) {
			// now check for parking lot records that may be orphaned
            $contact_no = str_replace(' ','',$this->user['contact_no']);
            $contact_no = str_replace('-','',$contact_no);

            // strip out 1 if it exists
            if (strlen($contact_no) == 11 && substr($contact_no,0,1) == 1) {
                $contact_no = substr($contact_no,1,10);
            }

            if ($this->saveParkingLotPurchasesIfTheyExist($contact_no)) {
                $data = $this->getLocalAccountInfo();
            }

            // now get history from history table
            $data['loyalty_transactions'] = $this->getLoyaltyHistory();
            return $data;
        } else {
            return null;
		}
	}
	
	function cleanRemoteLoyaltyResponseForUserSession($raw_info_from_curl)
	{
		unset($raw_info_from_curl['error']);
		unset($raw_info_from_curl['error_no']);
		unset($raw_info_from_curl['http_code']);
		unset($raw_info_from_curl['status']);
		return $raw_info_from_curl;
	}
	
	/**
	 * 
	 * @desc by default returns an array of the all the LOCAL loyalty history records in the db.  can be overridden by custom loyalty controller if there is an API call on the service to get external loyalty history
	 */
	function getLoyaltyHistory() 
	{
		if ($this->loyalty_history) {
			return $this->loyalty_history;
		}
		$loyalty_history = array();
		$ublha = new UserBrandLoyaltyHistoryAdapter($mimetypes);
		if ($local_history = $ublha->getLoyaltyHistoryForUserBrand($this->user['user_id'], $this->brand_id)) {
			$loyalty_history = $this->formatLoyaltyHistory($local_history);
		}
		$this->loyalty_history = $loyalty_history;
		return $loyalty_history;		
	}

	function getLoyaltyHistoryHeadings(){
		return $this->loyalty_history_headings;
	}

	function getLoyaltyLabels(){
		return $this->loyalty_type_labels;
	}
	
	function formatLoyaltyHistory($loyalty_history)
	{
		$history = array();
		foreach ($loyalty_history as $record) {
			unset($new_record);
			$amount = $this->getLoyaltyAmountFromLoyaltyRecord($record);
			//$new_record['amount'] = $amount;
			if($amount >= 0) {
				$new_record['description'] = "Earned ".round($amount)." points.";
			} else if($amount < 0) {
				if ($this->brand_loyalty_rules_record['loyalty_type'] == 'splickit_cliff') {
					$new_record['description'] = "Spent $".(-1 * $amount);
				} else {
					$new_record['description'] = "Spent ".(-1 * round($amount))." points.";
				}
			}
			$new_record['amount'] = '$'.$record['current_dollar_balance'];
			$d = explode(' ', $record['action_date']);
			$new_record['transaction_date'] = $d[0];
			$new_record['activity_type'] = $record['process'];
			$history[] = $new_record;
		}
		return $history;
	}
	
	function getLoyaltyAmountFromLoyaltyRecord($record)
	{
		return $record['points_added'] - $record['points_redeemed'];
	}
		
	/**
	 * @desc for base class will return true since there is nothing to validate against 
	 */
	function validateLoyaltyNumber($loyalty_number)
	{
		return true;
	}
	
	function validateLoadedLoyaltyNumber()
	{
		return $this->validateLoyaltyNumber($this->getLoyaltyNumber());
	}
	
	/**
	 * 
	 * @desc This method gets overridden in the custom loyalty progams.
	 * @param array $complete_order
	 * @param int $points
	 * @return mixed
	 */ 
	function sendLoyaltyOrderEvent($complete_order,$points)
	{
		return array();
	}
	
	/**
	 * @desc holds the function that creates points from an order
	 *
	 */ 
	function getPointsEarnedFromCompleteOrder($complete_order)
	{
		//base splickit functionality is 10x per order unless there are records in the Brand_Earned_Points_Obeject_Maps table which will override that for ALL items.
		// It wont mix, so if there are ANY records in that table then that is the ONLY points that can be earned for this skin/brand
		$use_maps = false;
		if ($this->use_static_loyalty_earn_arrays) {
			$use_maps = true;
		} else if ($earned_points_records = BrandEarnedPointsObjectMapsAdapter::staticGetRecords(array("brand_id"=>getBrandIdFromCurrentContext()),'BrandEarnedPointsObjectMapsAdapter')) {
			$this->loadEarnedPointsArrays($earned_points_records);
			$use_maps = true;
		}
		foreach ($complete_order['order_details'] as $order_detail) {
			if ($order_detail['points_used'] > 0) {
				myerror_logging(3,"this item was payed for with points so do not earn any");
				continue;
			}
			if ($use_maps) {
				$points = $points + $this->getPointsFromOrderDetailRecord($order_detail);
			} else {
				myerror_log("getting points with multiplier of: ".$this->dollar_mulitplier_for_points_earned,3);
				$points = $points + $this->dollar_mulitplier_for_points_earned*($order_detail['item_total_w_mods']);
			}

		}
		if ($complete_order['promo_amt'] < 0.00  && !$use_maps) {
			// subtract promo amount
			$promo_points_adjustment = floatval($complete_order['positive_promo_amount']) * $this->dollar_mulitplier_for_points_earned;
			$points = $points - $promo_points_adjustment;
		}
		if ($points <= 0.00) {
			$points = 0;
		} else {
			$points = round($points);
		}

		myerror_log("Points earned from this order: ".$points,3);
		return $points;
	}

	function getPointsFromOrderDetailRecord($order_detail)
	{
		if (isset($this->item_array_for_loyalty_earn[$order_detail['item_id']])) {
			$points = $this->item_array_for_loyalty_earn[$order_detail['item_id']];
		} else if (isset($this->size_array_for_loyalty_earn[$order_detail['size_id']])) {
			$points = $this->size_array_for_loyalty_earn[$order_detail['size_id']];
		} else if (isset($this->menu_type_array_for_loyalty_earn[$order_detail['menu_type_id']])) {
			$points = $this->menu_type_array_for_loyalty_earn[$order_detail['menu_type_id']];
		}
		return $points;
	}

	function loadEarnedPointsArrays($earned_points_records)
	{
		foreach ($earned_points_records as $record)
		{
			if (strtolower($record['object_type']) == 'menu_type') {
				$this->menu_type_array_for_loyalty_earn[$record['object_id']] = $record['points'];
			} else if (strtolower($record['object_type']) == 'size') {
				$this->size_array_for_loyalty_earn[$record['object_id']] = $record['points'];
			} else if (strtolower($record['object_type']) == 'item') {
				$this->item_array_for_loyalty_earn[$record['object_id']] = $record['points'];
			}
		}
	}

	function isHomeGrownLoyalty()
	{
		return $this->isNonRemoteLoyalty() && strtolower($this->brand_loyalty_rules_record['loyalty_type']) != 'loyaltylite';
	}

	function isNonRemoteLoyalty()
	{
		return strtolower($this->brand_loyalty_rules_record['loyalty_type']) != 'remote';
	}

	function isLoyaltyLite()
	{
		return $_SERVER['BRAND']['use_loyalty_lite'];
	}
	
	// function getHistory() {}
	
// ****** END BASE FUNCTIONS TO BE OVER WRITTEN **********/	
	
	function setLoyaltyData($data)
	{
		if ($data['brand_loyalty']) {
			$this->data = $data['brand_loyalty'];
			if ($data['create_loyalty_account']) {
				$this->data['create_loyalty_account'] = true;
			}
		} else {
			$this->data = $data;
		}
		
		if ($loyalty_number = $this->data['loyalty_number']) {
			$this->loyalty_number = $loyalty_number;
		}
	}
	
	function isCreateLoyaltyAccountTrue()
	{
		if (isset($this->data['create_loyalty_account'])) {
			return $this->data['create_loyalty_account'];
		} else {
			return false;
		}
	}
	
	function setCreateLoyaltyAcccount($boolean)
	{
		$this->data['create_loyalty_account'] = $boolean;
	}
	
	function getLoyaltyNumber()
	{
		return $this->loyalty_number;
	}
	
	function getServiceResponse()
	{
		return $this->service_response;
	}
	
	function getServiceResponseError()
	{
		if ($service_response = $this->getServiceResponse()) {
			return $service_response['error'];
		}
	}
	
	function getServiceResponseErrorCode()
	{
		if ($service_response = $this->getServiceResponse()) {
			return $service_response['error_code'];
		}
	}
	
	function getBrandPointsList($brand_id)
	{
		$this->brand_points_adapter = new BrandPointsAdapter($mimetypes);
		if ($brand_points_list = $this->brand_points_adapter->getBrandPointsList($brand_id)) {
			return $brand_points_list;
		}
	}
	
	function isAutoJoinOn()
	{
		return $this->auto_join;
	}

    function isASplickitLoyaltyNumber($loyalty_number)
    {
        return ('splick-' == substr(strtolower($loyalty_number),0,7));
    }

	function getLoyaltyPaymentName()
	{
		return $this->loyalty_payment_name;
	}

	function getLoyaltyEarnedLabel(){
		return $this->loyalty_earned_label;
	}

	function getLoyaltyEarnedMessage(){
		$points_earned = isset($this->points_earned) ? $this->points_earned : 0;
		return str_replace('{points}', $points_earned, $this->loyalty_earned_message );
	}

	function getLoyaltyBalanceLabel(){
		return $this->loyalty_balance_label;
	}

	function getLoyaltyBalanceMessage(){
		$data = $this->getLocalAccountInfoAsResource();
		$items = array('points' => $data->points, 'dollar_balance' => $data->dollar_balance);
		$message = $this->loyalty_balance_message;
		foreach($items as $key => $value ){
			$message = str_replace('{'.$key.'}', $value, $message );
		}
		return $message;
	}

	function processPointsBasedOnDollarMultiple($order_amount)
	{
		myerror_log("order_amount: $order_amount ,      Dollar Multiple: ".$this->dollar_mulitplier_for_points_earned,3);
		$points = round($order_amount * $this->dollar_mulitplier_for_points_earned);
		myerror_log("Points earned for this remote order is: $points",3);
		return $points;
	}

    function processRedemptionPointsBasedOnDollarMultiple($redemption_amount)
    {
        myerror_log("redemption_amount: $redemption_amount ,      Dollar Multiple: ".$this->dollar_mulitplier_for_points_earned,3);
        $points = round($redemption_amount * $this->dollar_mulitplier_for_points_earned * 10);
        myerror_log("Points redemmed for this remote order is: $points",3);
        return -$points;
    }

}

class PayWithPointsException extends Exception
{
	public function __construct($message) {
		parent::__construct("$message", 500);
	}
}

class BrandLoyaltyRulesNotConfiguredException extends Exception
{
	public function __construct() {
		parent::__construct("Brand does not have any brand loyalty rules configured!", 500);
	}
}

class NoBrandLoyaltyEnabledException extends Exception
{
    public function __construct() { 
        parent::__construct("Brand does not have loyalty enabled!", 422);
    }
}

class BrandNotSetException extends Exception
{
    public function __construct() { 
        parent::__construct("Context has no brand associated with it, cant instantiate loyalty controller!", 500);
    }
}
?>
