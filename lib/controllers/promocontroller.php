<?php
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'promotype3usermapadapter.php';

class PromoController extends SplickitController
{
	
	private $user_message_title;
	private $user_message;
	private $amt;
	private $error_code;
	private $error;
	//private $bypass_user_list = array(20588=>'1',20000=>'1',2=>'1',3=>'1'); // boy is this dumb
	//private $bypass_user_list = array(20588=>'1'); // boy is this dumb
	private $bypass_user_list = array();
	private $tax_rates_for_merchant = array();
	
	private $promo_merchant_map;
	protected $adapter;

	var $promo_messages;
	var $objects = [];
	var $menu_id;

	const REFER_A_FRIEND_TYPE_ID = 100;
	const FREE_DELIVERY_TYPE_ID = 300;

	const PROMO_MINIMUM_NOT_MET_MESSAGE = 'You have not met the minimum subtotal amount of $%%minimum_amount%% for this promo.';


	function PromoController($mt,$u,$r,$l = 0)
	{
		parent::SplickitController($mt,$u,$r,$l);
		$this->adapter = new PromoAdapter($mt);
		$this->log_level = 3;
	}

    function processV2Request()
    {
        if (preg_match("%/promos/([0-9]{3,15})%", $this->request->url, $matches)) {
            // we have an edit promo
            if ($this->isThisRequestMethodAPost() || $this->isThisRequestMethodADelete()) {
                if ($promo_resource = Resource::find($this->adapter,$matches[1])) {
                    if ($this->hasRequestForDestination('merchant_maps')) {
                        return $this->updatePromoMerchantMaps($promo_resource);
                    } else if ($this->hasRequestForDestination('key_words')) {
                        return $this->updatePromoKeyWordMaps($promo_resource);
                    } else {
                        return $this->updatePromo($promo_resource);
                    }

                } else {
                    return createErrorResourceWithHttpCode('Promo does not exist with that id: '.$matches[1],422,422);
                }
            } else if ($this->isThisRequestMethodAGet()){
                if ($promo_resource = Resource::find($this->adapter,$matches[1])) {
                    $promo_edit_resource = $this->getPromoForEdit(cleanData($promo_resource->getDataFieldsReally()));
                    return $promo_edit_resource;
                } else {
                    return createErrorResourceWithHttpCode('Promo does not exist with that id: '.$matches[1],422,422);
                }
            } else {
                return createErrorResourceWithHttpCode("Method not allowed",422,422);
            }

        } else {
            if ($this->isThisRequestMethodAPost()) {
                //create promo
                return $this->createPromo();
            } else  {
                // get promos for skin and merchant_id
                return createErrorResourceWithHttpCode("This endpoint does not exist",422,422);
            }
        }
    }

    function getPromosForSkinAndMerchant($skin_id,$merchant_id)
    {
        return null;
    }


    function getOfferList()
	{
		$now_string = date('Y-m-d');
		$data[TONIC_FIND_BY_METADATA]['end_date'] = array('>'=>$now_string);
		$data[TONIC_FIND_BY_METADATA]['offer'] = 'Y';
		if ($promos = $this->adapter->select('',$data))
			return $promos;
		return false;
	}
		
	function getPromoList()
	{
		$now_string = date('Y-m-d');
		$data[TONIC_FIND_BY_METADATA]['end_date'] = array('>'=>$now_string);
		if ($promos = $this->adapter->select('',$data))
			return $promos;
		return false;
	}

	function applyPromoToCart($promo_code,$cart_id)
    {

    }
	
	function checkAlreadyUsed($promo_id,$max_use,$user_id)
	{
			if ($this->bypass_user_list[$user_id])
				return false;
			if ($user_id < 1000)
				return false;
			$order_adapter = new OrderAdapter($this->mimetypes);
			$order_options[TONIC_FIND_BY_METADATA]['user_id'] = $user_id;
			$order_options[TONIC_FIND_BY_METADATA]['promo_id'] = $promo_id;
			$order_options[TONIC_FIND_BY_METADATA]['promo_amt'] = array('<'=>'0.00');
			
			// might want to change this to IN ('E','P') or it could be exploited (low risk)
			$order_options[TONIC_FIND_BY_METADATA]['status'] = array ('IN'=>"E,P,O");
			
			$orders = $order_adapter->select('',$order_options);
			if (sizeof($orders) > ($max_use-1))
				return true;
			else
			{
				// check device ID
				$account_hash = $this->user['account_hash'];
				$sql = "SELECT a.order_id FROM Orders a, User b WHERE a.user_id = b.user_id AND a.promo_id = $promo_id AND a.promo_amt < 0.00 AND (a.status = 'E' OR a.status = 'O') AND b.logical_delete = 'N' AND b.account_hash = '$account_hash'";
					myerror_logging(2,"about to check device id for promo:  ".$promo_id);
					myerror_logging(2,"$sql");
				$order_options2[TONIC_FIND_BY_SQL] = $sql;
				$orders2 = $order_adapter->select('',$order_options2);
				if ($this->log_level > 1)
				{
					myerror_logging(2,"size of similar device id orders is: ".sizeof($orders2));
					myerror_logging(2,"max use is:  $max_use");
				}
				if (sizeof($orders2) > ($max_use-1))
				{
					myerror_logging(2,"ERROR! *********WE HAVE AN ACCOUNT_HASH MATCH ON PROMO!**********");
					return true;
				}	
				return false;
			}
	}
	
	function validateOffer($order_data)
	{
		$resource = $this->validatePromo($order_data);
		if ($resource->user_message_title)
		{
			$title = $resource->user_message_title;
			$resource->user_message_title = str_ireplace('Promo', 'Offer', $title);
		}
		return $resource;
	}
	
	/**
	 * 
	 * @desc takes a promo merchant combination and returns true and set the instance variable $promo_merchant_map if it exists, returns false if it does not.
	 * @param int $promo_id
	 * @param int $merchant_id
	 * @return boolean
	 */
	function validatePromoMerchantCombination($promo_id,$merchant_id)
	{
		if ($promo_merchant_map = $this->getPromoMerchantMapRecord($promo_id, $merchant_id)) {
			$this->promo_merchant_map = $promo_merchant_map;
			return true;
		} else {
			return false;
		}	
	}

	function getMatchingPromoFromPromoListAndMerchantIdCombination($promos,$merchant_id)
	{
		foreach ($promos as $validate_promo) {
			if ($this->validatePromoMerchantCombination($validate_promo['promo_id'], $merchant_id)) {
				myerror_log("we have a promo merchant map record");
				return $validate_promo;
			}
		}
		throw new InvalidPromoException("So sorry, this promo is not valid at this location.", 800);
//		return false;
	}
	
	function getPromoMerchantMapRecord($promo_id,$merchant_id)
	{
		$promo_merchant_map_adapter = new PromoMerchantMapAdapter($this->mimetypes);
		$pmma_options[TONIC_FIND_BY_METADATA]['merchant_id'] = array("IN"=>array(0,$merchant_id));
		$pmma_options[TONIC_FIND_BY_METADATA]['promo_id'] = $promo_id;
		if ($promo_merchant_map = $promo_merchant_map_adapter->select('',$pmma_options)) {
			$promo_merchant_map = array_pop($promo_merchant_map);
			myerror_logging(2,"we got a good promo_merchant_map: ".$promo_merchant_map['map_id']);
			return $promo_merchant_map;
		}
	}

	function getNowTimeForMerchant($merchant_id)
	{
		$merchant_adapter = new MerchantAdapter($this->mimetypes);
		if ($merchant_resource = Resource::find($merchant_adapter,''.$merchant_id))
		{
			// get current time at local merchant
			$tz = date_default_timezone_get();
			$merchant_tz = getTheTimeZoneStringFromOffset($merchant_resource->time_zone,$merchant_resource->state);
			date_default_timezone_set($merchant_tz);
			$now = date('Y-m-d');
			date_default_timezone_set($tz);
		} else {
			// not sure if would ever get here
			$now = date('Y-m-d');
		}
		return $now;
	}

	function getPromoFromCode($the_code)
	{
		if ($this->user['user_id'] > 99) {
			$promo_options[TONIC_FIND_BY_METADATA]['active'] = 'Y';
		}
		$promo_options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';

		if (substr($the_code,0,2) == 'x_') {
			//its an auto promo
			$promo_options[TONIC_FIND_BY_METADATA]['promo_key_word'] = $the_code;
		} else {
			$promo_options[TONIC_JOIN_STATEMENT] = " JOIN Promo_Key_Word_Map ON Promo_Key_Word_Map.promo_id = Promo.promo_id ";
			$promo_options[TONIC_FIND_BY_STATIC_METADATA] = " Promo_Key_Word_Map.promo_key_word = '$the_code' AND Promo_Key_Word_Map.logical_delete = 'N' ";
		}
	 	return $this->adapter->select('',$promo_options);
	}

	function validateOrderType($promo,$order_data)
    {
        $promo_order_type = $promo['order_type'];
        if ($promo_order_type == 'all') {
            return true;
        } else if ($order_data['order_type'] == 'R' && $promo_order_type == 'pickup') {
            return true;
        } else if ($order_data['order_type'] == 'D' && $promo_order_type == 'delivery') {
            return true;
        } else {
            throw new InvalidPromoException("Sorry! This promo is only valid on $promo_order_type orders.", 801);
        }
    }

	function validateFirstOrderPromo($promo)
	{
		if ($promo['valid_on_first_order_only'] == 'Y') {
			if ($this->user['orders'] > 0) {
				throw new InvalidPromoException("Sorry!  This promo is only valid on your first order", 801);
			}
			// now check if they've order before from a different account.
			$account_hash = $this->user['account_hash'];
			$sql = "SELECT a.order_id FROM Orders a, User b WHERE a.user_id = b.user_id AND (a.status = 'E' OR a.status = 'O') AND b.logical_delete = 'N' AND b.account_hash = '$account_hash'";
			myerror_logging(2, "about to verify if first time use for promo:  " . $promo['promo_id']);
			myerror_logging(2, "$sql");
			$order_options2[TONIC_FIND_BY_SQL] = $sql;
			if ($orders2 = $this->adapter->select('', $order_options2)) {
				if (sizeof($orders2) > 0) {
					throw new InvalidPromoException("Sorry!  It appears you have ordered before from a different account, and this promo is only valid on your first order. We hope you understand. If you feel you have gotten this message in error, please contact customer service. Thanks!", 801);
				}
			}
		}
	}

	function validateCashWithSplickitPay($order_data,$promo)
	{
		//check for cash if merchant is NOT paying for it
		if ($promo['payor_merchant_user_id'] == 1 && isset($order_data['cash'])) {
			if (strtoupper(substr($order_data['cash'], 0, 1)) == 'Y') {
				myerror_log("ERROR!  promo payor of 1 and cash option");
				throw new InvalidPromoException("Sorry!  The promo code you entered, cannot be used with the cash payment option.", 815);
			}
		}
	}

	function validatePromoLimits($promo,$now)
	{
		$promo_merchant_map = $this->promo_merchant_map;
		if ($promo_merchant_map['start_date'] != null && $promo_merchant_map['end_date'] != null && $promo_merchant_map['end_date'] > $promo_merchant_map['start_date']) {
			myerror_logging(2, "We have a promo_merchant_map date range override");
			if ($now > $promo_merchant_map['end_date']) {
				myerror_log("PROMO EXPIRED at this merchant!  expires: " . $promo_merchant_map['end_date']);
				throw new InvalidPromoException("Sorry this promotion has expired at this merchant :(", 820);
			} else if ($now < $promo_merchant_map['start_date']) {
				myerror_log("PROMO HAS NOT STARTED YET at this merchant!  starts: " . $promo_merchant_map['start_date']);
				$error_message = "Sorry this promotion has not started yet.  Promo begins on " . $promo_merchant_map['start_date'] . " :(";
				throw new InvalidPromoException($error_message, 820);
			}
		}

		if ($now > $promo['end_date']) {
			myerror_log("PROMO EXPIRED");
			throw new InvalidPromoException("Sorry this promotion has expired :(", 820);
		} else if ($now < $promo['start_date']) {
			myerror_log("PROMO HAS NOT STARTED YET");
			throw new InvalidPromoException("Sorry this promotion has not started yet :(", 820);
		} else if ($promo['max_redemptions'] != 0 && $promo['max_redemptions'] <= $promo['current_number_of_redemptions']) {
			myerror_log("PROMO USED UP");
			throw new InvalidPromoException("Sorry this promotion has been all used up :(", 820);
		} else if ($promo['max_dollars_to_spend'] != 0.00 && $promo['max_dollars_to_spend'] <= $promo['current_dollars_spent']) {
			myerror_log("PROMO USED UP");
			throw new InvalidPromoException("Sorry this promotion has been all used up :(", 820);
		}

	}

	function validateUserUsesOfPromo($promo,$now)
	{
		// now check to see if they have used it before from another account by checking the hash
		$account_hash = $this->user['account_hash'];
		$promo_id = $promo['promo_id'];

		$sql = "SELECT a.order_id FROM Orders a, User b WHERE a.user_id = b.user_id AND a.promo_id = $promo_id AND a.promo_amt < 0.00 AND (a.status = 'E' OR a.status = 'O') AND b.logical_delete = 'N' AND b.account_hash = '$account_hash'";
		myerror_logging(2, "about to check account hash for promo:  " . $promo_id);
		myerror_logging(2, "$sql");
		$order_options2[TONIC_FIND_BY_SQL] = $sql;
		if ($account_hash == NULL || trim($account_hash) == '') {
			$times_used_by_account_hash = 0;
		} else if ($orders2 = $this->adapter->select('', $order_options2)) {
			myerror_logging(1, "size of similar device id orders is: " . sizeof($orders2));
			myerror_logging(1, "max use is: ".$promo['max_use']);
			$similar_device_order = true;
			$times_used_by_account_hash = sizeof($orders2);
		} else {
			$times_used_by_account_hash = 0;
		}

		$gaming = false;
		if ($promo_user_map = PromoUserMapAdapter::staticGetRecord(array("user_id"=>$this->user['user_id'],"promo_id"=>$promo_id),'PromoUserMapAdapter')) {
			myerror_logging(2, "User IS associated with this promo, so get values from promo_user_map table");
			if ($now > $promo_user_map['end_date']) {
				throw new InvalidPromoException("Sorry this promotion has expired :(", 820);
			}
			$times_used = $promo_user_map['times_used'];
			$times_allowed = $promo_user_map['times_allowed'];
			if ($times_used != $times_used_by_account_hash) {
				myerror_log("HOLY COW! we have a mismatch between times used and times use by account hash");
			}
			if ($times_used_by_account_hash > $times_used) {
				$times_used = $times_used_by_account_hash;
			}
		} else {
			myerror_logging(2, "User is not associated with this promo yet, so first check if this is public promo or not");
			if ($promo['public'] == 'N') {
				throw new InvalidPromoException("Sorry!  The promo code you entered, is not valid for you.", 805);
			} else {
				$times_used = $times_used_by_account_hash;
				if ($similar_device_order) {
					$gaming = true;
				}

			}
		}
		myerror_logging(2, "total times used is: " . $times_used);
		if ($times_used >= $times_allowed && $times_allowed != 0) {
			if ($gaming) {
				myerror_log("ERROR! GAMING THE SYSTEM!   CHEATER!");
				$error_message = "It appears you have used this promo already from a different account.  We have a limit of $times_allowed per customer, thanks for understanding. If you feel this is an error please contact support.";
				throw new InvalidPromoException($error_message, 811);
			} else {
				myerror_log("User has already used this promo max number of times!");
				throw new InvalidPromoException("Sorry :( You do not currently have any more uses left of this promo.", 810);
			}
		}
	}

	function validatePointsNeededByUser($promo)
	{
		if ($promo['points'] > $this->user['points_current']) {
			myerror_log("User does not have enough points to use this promo! needed: ".$promo['points']." , available: " . $this->user['points_current']);
			throw new InvalidPromoException("Sorry but you do not have enough points to use this promotion :(   ", 825);
		}
	}
		
	/**
	 * 
	 * @desc used to validate a promo code on order data
	 * @param Hashmap $order_data
	 * @return Resource
	 */
	function validatePromo($order_data)
	{
		logData($order_data, "Submitted Promo Data",3);
		
		$promo_type = null;

		if (sizeof($order_data, $mode) < 1)
			$order_data = $this->data;		
			
		$the_code = trim(strtolower($order_data['promo_code']));
		$merchant_id = $order_data['merchant_id'];
		
		$now = $this->getNowTimeForMerchant($merchant_id);
		
		$this->setTaxRates($merchant_id);
		
		if ($the_code == 'usegift') {
			return $this->processGift($order_data);
		}
				
		myerror_logging(2,"starting validate Promo in promocontroller.php");
		myerror_logging(2,"the submitted promo code is: ".$order_data['promo_code']);
		
		//remove other characters
		$the_code = str_replace("'","",$the_code);
		$the_code = trim($the_code);
		
		if (substr_count($the_code, '@') == 1) {
			$referrer_email = $the_code;
			$the_code = 'referral';
		}

		$the_code = str_replace(".","",$the_code);
		if ($promos = $this->getPromoFromCode($the_code)) {
			try {
				$promo = $this->getMatchingPromoFromPromoListAndMerchantIdCombination($promos, $merchant_id); // throws exception if not valid at that merchant
				$promo_id = $promo['promo_id'];
				$this->validateOrderType($promo,$order_data);
				$this->validateFirstOrderPromo($promo);
				$this->validateCashWithSplickitPay($promo,$order_data);
				$this->logPromoInfo($promo,$now);
				if ($this->user['user_id'] > 99) {
					$this->validatePromoLimits($promo,$now); // start tiem, end time, max uses, max dollars spent, etc..
					$this->validateUserUsesOfPromo($promo,$now); // check for gaming or override in promo user map
					$this->validatePointsNeededByUser($promo);
				}
				// get the messages associated with this promo
				try {
				    if ($promo['promo_type'] != 6) {
                        $this->setPromoMessagesFromPromoId($promo_id);
                    }
				} catch (NoMessageSetUpForPromoException $e) {
					return $this->returnPromoError("We're sorry, this promo appears to not be active at the moment.  Please contact customer support if you feel you have recieved this message in error.", 999);
				}
			} catch (InvalidPromoException $ipe) {
				return $this->returnPromoError($ipe->getMessage(), $ipe->getCode());
			}
		}
		myerror_logging(2,"the returned promo_id is $promo_id");
		$promo_type = $promo['promo_type'];
		myerror_log("we have the promo type: ".$promo_type,3);

		switch($promo_type){
			case 1 : {
				$resource = $this->processPromoTypeOne($order_data,$promo);
			} break;
			case 2 : {
				$resource = $this->processPromoTypeTwo($order_data,$promo);
			} break;
            case 4 : {
                $resource = $this->processPromoTypeFour($order_data,$promo);
            } break;
            case 5 : {
                $resource = $this->processPromoTypeFive($order_data,$promo);
            } break;
            case 6 : {
                $resource = $this->processPromoTypeSix($order_data,$promo);
            } break;
            case PromoController::FREE_DELIVERY_TYPE_ID : {
                myerror_log('free delivery promo');
				$resource = $this->processPromoDelivery($order_data,$promo);
			} break;
			case PromoController::REFER_A_FRIEND_TYPE_ID : {
				$order_data['referrer_email'] = $referrer_email;
				$resource = $this->processPromoReferAFriend($order_data,$promo);
			} break;
			default:{
				return $this->returnPromoError("Sorry!  The promo code you entered, ".$order_data['promo_code'].", is not valid.", 800);
			}
		}

		return $resource;
	}

	function logPromoInfo($promo,$now)
	{
		myerror_logging(2, "promo now: $now");
		logData($promo,"PROMO INFO",2);
		$start_date = $promo['start_date'];
		$end_date = $promo['end_date'];
		if ($now > $end_date) {
			myerror_logging(2, 'promo is expired');
		} else if ($now <= $end_date && $now >= $start_date) {
			myerror_logging(2, 'promo is valid');
		} else if ($now < $start_date) {
			myerror_logging(2, 'promo has not started yet');
		} else {
			myerror_log('HOW DID WE GET HERE IN PROMO??  wtf??');
		}
	}

	function setPromoValuesOnResource($resource,$promo)
	{
		$resource->set("promo_points",$promo['points']);
		$resource->set("promo_id",$promo['promo_id']);
		$resource->set("payor_merchant_user_id",$promo['payor_merchant_user_id']);
		$resource->set("promo_type",$promo['promo_type']);
		return $resource;
	}

	function processGift($order_data)
	{
		myerror_logging(2, "starting the usegift promo code");
		if ($gift_resource_on_user = $this->user['gift_resource']) {
			myerror_logging(3,"we have found the gift resource: ".$gift_resource_on_user->gift_id);
			$gift_resource = $this->getResourceFromId($gift_resource_on_user->gift_id, 'Gift');
			$tax_rate = $this->tax_rates_for_merchant[1];
			$sub_total = $order_data['sub_total'];
			$tax_amt = round($sub_total*$tax_rate,2);
			myerror_logging(2,"in gift check of promo controller  sub_total-tip-tax = ".$order_data['sub_total']." - ".$order_data['tip']." - ".$tax_amt);
			myerror_logging(2,"will use merchants default tax rate option to caculate total now.  default tax rate is: ".$tax_rate);
			$grand_total = $sub_total + $tax_amt + $order_data['tip'];
			myerror_logging(2,"calculated grand_total is: ".$grand_total);
			if ( $grand_total > $gift_resource->amt) {
				if (substr($this->user['flags'],1,1) != 'C') {
					return returnErrorResource("Sorry, this gift has a maximum value of $".$gift_resource->amt.".  Please add a Credit Card to your account or remove something from your cart",150);
				} else {
					$double_billing_amt = $grand_total - $gift_resource->amt;
					//ok trying to use more than the gift but the user has a credit card on file so we'll run that for the extra
					$gift_resource->set("double_billing_id",$this->user['user_id']);
					$gift_resource->set("double_billing_amt",$double_billing_amt);
				}
			} else if ($this->getNowTimeForMerchant($order_data['merchant_id']) > $gift_resource->expires_on) {
				return returnErrorResource("Sorry this gift expired on ".$gift_resource->expires_on,820);
			} else if ($grand_total < $gift_resource->amt) {
				$amt_left_to_use = $gift_resource->amt - $grand_total;
				if ($amt_left_to_use > 2.00) {
					$gift_resource->set("user_message","This is a one time use gift and you have $".$amt_left_to_use." left as part of the gift, so add somethign else to the cart!");
				} else {
					$gift_resource->set("user_message","Well done!  You've used nearly all your gift, so place your order and enjoy the free lunch!");
				}
			}
		} else {
			return returnErrorResource("Sorry, you do not appear to have any active gifts",805);
		}
		// we got here so the gift is good remove the promo code and add the gift_token
		return $gift_resource;
	}

	function processPromoDelivery($order_data,$promo)
	{
		$resource = new Resource();
		if (!(isset($order_data['user_addr_id']) || isset($order_data['user_delivery_location_id'])) ) {
		    myerror_log('ERROR!!!  This promo is only valid on delivery orders',5);
			$resource->set("error_code",805);
			$resource->set("http_code",422);
			$resource->set("error","Sorry! This promo is only valid on delivery orders.");
			$resource->set("amt",0.00);
			$resource->set("promo_id",0);
			return $resource;
		} else {
            $promo_id = $promo['promo_id'];
            $amt = 0.00;
            $resource = new Resource();
            $mdi_adapter = new MerchantDeliveryInfoAdapter($this->mimetypes);

            // for backwards compaitbility. carts use 'user_delivery_location_id' but old style APIv1 phones use user_addr_id
            $user_delivery_location_id = isset($order_data['user_delivery_location_id']) ? $order_data['user_delivery_location_id'] : $order_data['user_addr_id'];
            if($merchant_delivery_price_distance_resource = $mdi_adapter->getMerchantDeliveryPriceResourceForUserLocationAndMerchantId($user_delivery_location_id, $order_data['merchant_id'])) {
                if ($merchant_delivery_price_distance_resource->hasError()) {
                    return $this->returnPromoError($merchant_delivery_price_distance_resource->error);
                }
                $delivery_price = $merchant_delivery_price_distance_resource->price;
            } else {
                return $this->returnPromoError("So sorry, this promo is not valid at this location.", 800);
            }

            if ($promo_amts = PromoDeliveryAmountMapAdapter::staticGetRecord(array("promo_id"=>$promo_id),'PromoDeliveryAmountMapAdapter')) {

                $resource->set("user_message_title","Promo Code Validated");
                logData($promo_amts,"Promo Amounts",2);
                if ($order_data['sub_total'] == null) {
                    $order_data['sub_total'] = $order_data['order_amt'];
                }
                if ( $order_data['sub_total'] > $promo_amts['qualifying_amt']) {
                    $amt = $promo_amts['fixed_off'];
                    if ($amt == 0) {
                        // this is a percent off promo
                        $amt = $delivery_price * $promo_amts['percent_off'] / 100;
                    }

                    if ($amt > $delivery_price) {
                        $amt = $delivery_price;
                    }

                    $amt = number_format($amt, 2);
                    myerror_logging(2, "user is getting $" . $amt . " off of their order");
                    $message1 = $amt != $delivery_price
                        ? 'Congratulations! You\'re getting $'.$amt.' off of your delivery charge!'
                        : "Congratulations! You're getting free delivery!";
                    $resource->set("user_message", $message1);
                    $resource->set("complete_promo", 'true');
                    $resource->set("amt", $amt);
                } else {
                    myerror_logging("FAILURE.....subtotal is NOT greater than qualifing amount");
                    $resource->set("user_message",$this->promo_messages['message5']);
                    $resource->set("complete_promo",'false');
                }
            } else {
                //TODO: to support older free delivery
                $resource->set("user_message_title","Promo Code Validated");
                $resource->set("user_message",trim($this->promo_messages['message2']) != '' ? $this->promo_messages['message2'] : "Congratulations! You're getting free delivery!" );
                $resource->set("amt",$delivery_price);
                $resource->set("complete_promo",'true');
            }
			$resource = $this->setPromoValuesOnResource($resource,$promo);
		}
		return $resource;
	}

	function processPromoReferAFriend($order_data,$promo)
	{
		$referrer_email = $order_data['referrer_email'];
		$resource = new Resource();
		if ($this->user['orders'] > 0) {
			$resource->set("error_code",801);
			$resource->set("error","Sorry!  The referal promo bonus is only valid on your first order.");
		} else if ($this->user['email'] == $referrer_email) {
			$resource->set("error_code",802);
			$resource->set("error","Sorry!  You cant use yourself as a referral :p");
		}

		$user_adapter = new UserAdapter($this->mimetypes);
		$user_options[TONIC_FIND_BY_METADATA]['email'] = $referrer_email;
		if ($referrers = $user_adapter->select('',$user_options)) {
			$referrer = array_pop($referrers);
			myerror_logging(2,"we have a valid referrer in promo type 100. user_id: ".$referrer['user_id']);
			$resource->set("user_message",$this->promo_messages['message1']);
			$resource->set("amt",1.00);
		} else {
			$resource->set("error_code",803);
			$resource->set("error",$this->promo_messages['message5']);
		}

		if ($resource->error_code) {
			$resource->set("http_code",422);
			$resource->set("amt",0.00);
			$resource->set("promo_id",0);
		} else {
			$resource = $this->setPromoValuesOnResource($resource,$promo);
		}
		return $resource;
	}

	function isOrderBelowPromoMinimumQualifyingAmount($qualifying_amount,$subtotal)
    {
        return $qualifying_amount > 0.00 && $subtotal < $qualifying_amount;
    }

	function setAmountMinimumNotMetFieldsOnResource($resource,$amount_minimum)
    {
        $resource->set("user_message",$this->getPromoMinumimNotMetMessage($amount_minimum));
        $resource->set("complete_promo",false);
        $resource->set("amt",0.00);
        $resource->set("tax_amt",0.00);
        return $resource;

    }

	function processPromoTypeFive($order_data,$promo)
    {
        myerror_logging(2,"********* STARTING PROMO TYPE 5 CODE **********");
        $promo_id = $promo['promo_id'];
        $payor_merchant_user_id = $promo['payor_merchant_user_id'];
        $resource = new Resource();

        // get all promo data
        if ($promo_items = PromoType4ItemAmountMapsAdapter::staticGetRecord(array("promo_id"=>$promo_id),'PromoType4ItemAmountMapsAdapter')) {
            // all is good
            logData($promo_items,"Promo 4 Item Amount Map",2);
        } else {
            myerror_log("DATA INTEGRITY ERROR type 5 PROMO HAS NO ITEM DATA ASSOCIATED WITH IT!");
            $body = "There is a data integrity error for promo id: $promo_id.  There are no ITEMS associate with this promo. ".$_SERVER['HTTP_HOST'];
            MailIt::sendErrorEmail('PROMO TYPE 5 ITEMS ERROR', $body);
            return $this->returnPromoError("We're sorry, this promo appears to be inactive at the moment.  Please contact customer support if you feel you have recieved this message in error.", 999);
        }


        $resource->set("user_message_title","promo code validated");

        if ($order_data['sub_total'] == null) {
            $order_data['sub_total'] = $order_data['order_amt'];
        }
        if ($this->isOrderBelowPromoMinimumQualifyingAmount($promo_items['qualifying_amt'],$order_data['sub_total'])) {
            myerror_logging("FAILURE.....subtotal is NOT greater than qualifing amount");
            $resource = $this->setAmountMinimumNotMetFieldsOnResource($resource,$promo_items['qualifying_amt']);
            $resource = $this->setPromoValuesOnResource($resource,$promo);
            return $resource;
        }

        $items_info = $this->getItemsInfoForPromoProcessing($order_data);
        $this->sortItemsInfoByPrice($items_info);
        $tax_amt = 0.00;
        $promo_items['qualifying_object_array'] = explode(',',$promo_items['qualifying_object']);
        $number_of_complete_promos_in_cart = 0;
        $total_promo_amount_off = 0.00;
        $complete_promo = false;
        do {
            $group_price = 0.00;
            $qualifying_object_array = $promo_items['qualifying_object_array'];
            $remove_list = [];
            $complete_on_cycle = false;
            foreach ($items_info as $price_key=>$item_info) {
                myerror_logging(2, "about to check: " . $item_info['item_name']);
                foreach ($qualifying_object_array as $index=>$value) {
                    if ($this->doesItemSatisfyThisRequirement($item_info,$value)) {
                        unset($qualifying_object_array[$index]);
                        $remove_list[] = $price_key;
                        $group_price = $group_price + $item_info['price'];
                        break;
                    }
                }
                if (sizeof($qualifying_object_array) == 0) {
                    $complete_promo = true;
                    $complete_on_cycle = true;
                    $number_of_complete_promos_in_cart++;
                    $total_promo_amount_off = $total_promo_amount_off + $this->getAmountOffOfItemForType5Promo($promo_items,$group_price);
                    break;
                }
            }
            if ($complete_on_cycle) {
                foreach ($remove_list as $price_key) {
                    unset($items_info[$price_key]);
                }
            }

        } while ($promo['allow_multiple_use_per_order'] && $complete_on_cycle && sizeof($items_info) > 0);


        if ($complete_promo) {
            myerror_logging(2, "we have valid qualifying items for type 5 promo");
            myerror_logging(2, "amount off is now: ".$total_promo_amount_off);
            if ($payor_merchant_user_id == 2) {
                $tax_amt = $tax_amt + ($total_promo_amount_off * $this->tax_rates_for_merchant[$item_info['tax_group']]);
            }
            myerror_logging(2, "tax off is now: ".$tax_amt);
            $message = str_replace('%%amt%%',"$".number_format($total_promo_amount_off,2),$this->promo_messages['message1']);
            $message = str_replace('%%item_name%%',$item_info['item_name'],$message);
            $resource->set("user_message",$message);
        } else {
            myerror_logging(2,"all qualifying items were not satisfied");
            $total_promo_amount_off = 0.00;
            $resource->set("user_message",$this->promo_messages['message5']);
        }

        myerror_logging(2,"promo message is: ".$resource->user_message);
        myerror_logging(2,"total amount off is: ".$total_promo_amount_off);
        myerror_logging(2,"tax to be taken off is: ".$tax_amt);
        $resource->set("amt",$total_promo_amount_off);
        $resource->set("tax_amt",$tax_amt);
        $resource->set("complete_promo",$number_of_complete_promos_in_cart > 0 ? "true" : false);
        return $this->setPromoValuesOnResource($resource,$promo);

    }

	function processPromoTypeFour($order_data,$promo)
	{
		myerror_logging(2,"********* STARTING PROMO TYPE 4 CODE **********");
		$promo_id = $promo['promo_id'];
		$payor_merchant_user_id = $promo['payor_merchant_user_id'];
		$resource = new Resource();

		// get all promo data
		if ($promo_items = PromoType4ItemAmountMapsAdapter::staticGetRecord(array("promo_id"=>$promo_id),'PromoType4ItemAmountMapsAdapter')) {
			// all is good
			logData($promo_items,"Promo 4 Item Amount Map",2);
		} else {
			myerror_log("DATA INTEGRITY ERROR PROMO HAS NO ITEM DATA ASSOCIATED WITH IT!");
			$body = "There is a data integrity error for promo id: $promo_id.  There are no ITEMS associate with this promo. ".$_SERVER['HTTP_HOST'];
			MailIt::sendErrorEmail('PROMO TYPE4 ITEMS ERROR', $body);
			return $this->returnPromoError("We're sorry, this promo appears to be inactive at the moment.  Please contact customer support if you feel you have recieved this message in error.", 999);
		}

		$complete_promo = false;
		$resource->set("user_message_title","promo code validated");

        if ($order_data['sub_total'] == null) {
            $order_data['sub_total'] = $order_data['order_amt'];
        }
        if ($this->isOrderBelowPromoMinimumQualifyingAmount($promo_items['qualifying_amt'],$order_data['sub_total'])) {
            myerror_logging("FAILURE.....subtotal is NOT greater than qualifing amount");
            $resource = $this->setAmountMinimumNotMetFieldsOnResource($resource,$promo_items['qualifying_amt']);
            $resource = $this->setPromoValuesOnResource($resource,$promo);
            return $resource;
        }

		$items_info = $this->getItemsInfoForPromoProcessing($order_data);
        $this->sortItemsInfoByPrice($items_info);
        $promo_items['qualifying_object_array'] = explode(',',$promo_items['qualifying_object']);
        $valid_qualifing_item = false;
		$this->loadUpPromoObjectArrays($promo_items);
        $tax_amt = 0.00;
        $amt = 0.00;
		foreach ($items_info as $item_info) {
			myerror_logging(2, "about to check: " . $item_info['item_name']);
			if ($this->doesItemSatisfyRequirements($item_info,$promo_items['qualifying_object_array'])) {
				$valid_qualifing_item = true;
				myerror_logging(2, "we have a valid qualifying item for type 4 promo");
                //commenting this out to satisfy corner case of when fixed price is more than existing price
				//$amt = $amt + $this->getAmountOffOfItemForType4Promo($promo_items,$item_info['price']);
				if ($temp_amt = $this->getAmountOffOfItemForType4Promo($promo_items,$item_info['price'])) {
				    $amt = $amt + $temp_amt;
                } else {
                    $valid_qualifing_item = false;
                    continue;
                }
				myerror_logging(2, "amount off is now: ".$amt);
				if ($payor_merchant_user_id == 2) {
					$tax_amt = $tax_amt + ($temp_amt * $this->tax_rates_for_merchant[$item_info['tax_group']]);
				}
				myerror_logging(2, "tax off is now: ".$tax_amt);
				$message = str_replace('%%amt%%',"$".number_format($amt,2),$this->promo_messages['message1']);
				$message = str_replace('%%item_name%%',$item_info['item_name'],$message);
				$resource->set("user_message",$message);
				$complete_promo = 'true';
			}
			if ($promo['allow_multiple_use_per_order'] == false && $valid_qualifing_item) {
			    break;
            }
		}
		if (!$valid_qualifing_item) {
			myerror_logging("NO valid qualifying item",5);
			$amt = 0.00;
			$resource->set("user_message",$this->promo_messages['message5']);
		}

		myerror_logging(2,"promo message is: ".$resource->user_message);
		myerror_logging(2,"amt off is: ".$amt);
		myerror_logging(2,"tax to be taken off is: ".$tax_amt);
		$resource->set("amt",$amt);
		$resource->set("tax_amt",$tax_amt);
		$resource->set("complete_promo",$complete_promo);
		return $this->setPromoValuesOnResource($resource,$promo);
	}

	function getAmountOffOfItemForType5Promo($promo_item_amount_map,$group_price_of_items)
    {
        // logic is the same as type 4
        return $this->getAmountOffOfItemForType4Promo($promo_item_amount_map,$group_price_of_items);
    }

	function getAmountOffOfItemForType4Promo($promo_item_amount_map,$price_of_item)
	{
		if ($promo_item_amount_map['fixed_amount_off'] > 0) {
			return floatval($promo_item_amount_map['fixed_amount_off']);
		} else if ($promo_item_amount_map['percent_off'] > 0) {
			return (floatval($price_of_item)*(intval($promo_item_amount_map['percent_off'])/100));
		} else {
            //return floatval($price_of_item)-floatval($promo_item_amount_map['fixed_price']);
		    $amt_off = floatval($price_of_item)-floatval($promo_item_amount_map['fixed_price']);
		    if ($amt_off > 0) {
		        return $amt_off;
            } else {
		        return null;
            }
		}
	}

	function processPromoTypeTwo($order_data,$promo)
	{
		myerror_logging(2,"********* STARTING PROMO TYPE 2 CODE **********");
		$promo_id = $promo['promo_id'];
		$payor_merchant_user_id = $promo['payor_merchant_user_id'];
		$resource = new Resource();
		$promo_data_error = false;

		// get all promo data
		if ($promo_items = PromoType2ItemMapAdapter::staticGetRecord(array("promo_id"=>$promo_id),'PromoType2ItemMapAdapter')) {
			// all is good
			logData($promo_items,"Promo 2 Item Map",3);
		} else {
			myerror_log("DATA INTEGRITY ERROR PROMO HAS NO ITEM DATA ASSOCIATED WITH IT!");
			$body = "There is a data integrity error for promo id: $promo_id.  There are no ITEMS associate with this promo. ".$_SERVER['HTTP_HOST'];
			MailIt::sendErrorEmail('PROMO TYPE2 ITEMS ERROR', $body);
			return $this->returnPromoError("We're sorry, this promo appears to be inactive at the moment.  Please contact customer support if you feel you have recieved this message in error.", 999);
		}

		$resource->set("user_message_title","promo code validated");
        if ($order_data['sub_total'] == null) {
            $order_data['sub_total'] = $order_data['order_amt'];
        }
        if ( $promo_items['qualifying_amt'] > 0.00 && $order_data['sub_total'] < $promo_items['qualifying_amt']) {
            myerror_logging("FAILURE.....subtotal is NOT greater than qualifing amount");
            $resource->set("user_message",$this->getPromoMinumimNotMetMessage($promo_items['qualifying_amt']));
            $resource->set("complete_promo",'false');
            $resource->set("amt",0.00);
            $resource->set("tax_amt",0.00);
            $resource = $this->setPromoValuesOnResource($resource,$promo);
            return $resource;
        }



		$items = $order_data['items'];

		$amt = 0.00;
		logData($items,"items for promo check",3);
		$items_info = $this->getItemsInfoForPromoProcessing($order_data);
		$this->sortItemsInfoByPrice($items_info);
		$this->loadUpPromoObjectArrays($promo_items);
		logData($promo_items,"promo record",3);

        $tax_amt = 0.00;
        $remove_list = [];
        $complete_promo = false;
        $i = 1;
        do {
            myerror_log("starting iteration $i for promo type 2 test",3);
            $complete_on_cycle = false;
            $valid_qualifing_item = false;
            $valid_promo_item1 = false;
            $valid_promo_item2 = false;

            // now set the flags if there are no promo items ( cant remember what the use case here was for both being null, might not be any)
            if ($promo_items['qualifying_object'] == '0') {
                $valid_qualifing_item = true;
            }
            if ($promo_items['promo_item_1'] == null || trim($promo_items['promo_item_1']) == '') {
                $valid_promo_item1 = true;
            }
            if ($promo_items['promo_item_2'] == null || trim($promo_items['promo_item_2']) == '') {
                $valid_promo_item2 = true;
            }
            foreach ($items_info as $price_key=>$item_info) {
                myerror_logging(2,"about to check: ".$item_info['item_name']);
                logData($item_info,'item info',3);
                if (!$valid_promo_item1 && $this->doesItemSatisfyRequirements($item_info,$promo_items['promo_item_1_array'])) {
                    $valid_promo_item1 = true;
                    myerror_logging(1, "we have a valid promotional item 1");
                    $amt_promo_item_1 = $item_info['price'];
                    myerror_logging(3, "amount off for this item is: ".$amt_promo_item_1);
                    $remove_list[] = $price_key;
                } else if (!$valid_promo_item2 && $this->doesItemSatisfyRequirements($item_info,$promo_items['promo_item_2_array']) ) {
                    $valid_promo_item2 = true;
                    myerror_logging(1, "we have a valid promotional item 2");
                    //$amt = $amt + $item_info['price'];
                    $amt_promo_item_2 = $item_info['price'];
                    myerror_logging(3, "amount off for this item is: ".$amt_promo_item_2);
                    $remove_list[] = $price_key;
                } else if (!$valid_qualifing_item && $this->doesItemSatisfyRequirements($item_info,$promo_items['qualifying_object_array'])) {
                    $valid_qualifing_item = true;
                    myerror_logging(1, "we have a valid qualifying item");
                    $remove_list[] = $price_key;
                }

                if ($valid_qualifing_item && $valid_promo_item1 && $valid_promo_item2) {
                    break;
                }
            }

            if ($valid_qualifing_item) {
                myerror_logging(2,"we have a valid qualifing item on iteration: $i");
                if ($valid_promo_item1 && $valid_promo_item2 ) {
                    myerror_log("we have a complete promo on this iteration: $i",3);
                    $complete_promo = true;
                    $complete_on_cycle = true;
                    $promo_merchant_map = $this->promo_merchant_map;

                    $amt = $amt + $amt_promo_item_1 + $amt_promo_item_2;
                    myerror_log("the amount off is now: $amt",3);
                    foreach ($remove_list as $key) {
                        unset($items_info["$key"]);
                    }
                    $resource->set("user_message",$this->promo_messages['message1']);
                } else if (!$complete_promo && $valid_promo_item1) {
                    $resource->set("user_message", $this->promo_messages['message2']);
                } else if (!$complete_promo && $valid_promo_item2 && $promo_items['promo_item_2'] != null) {
                    $resource->set("user_message", $this->promo_messages['message3']);
                } else if (!$complete_promo) {
                    $resource->set("user_message", $this->promo_messages['message4']);
                }
            } else if (!$complete_promo) {
                myerror_logging("NO valid qualifying item",3);
                $amt = 0.00;
                $resource->set("user_message",$this->promo_messages['message5']);
            }
            $i++;
        } while ($complete_on_cycle && $promo['allow_multiple_use_per_order'] == 1);


		if ($complete_promo) {
            if ($promo_merchant_map['max_discount_per_order'] != NULL && $amt > $promo_merchant_map['max_discount_per_order']) {
                $amt = $promo_merchant_map['max_discount_per_order'];
            }
            if ($payor_merchant_user_id == 2) {
                $tax_amt = $amt * $this->tax_rates_for_merchant[$item_info['tax_group']];
                myerror_log("the promo tax amount is: $tax_amt",3);
            }
        }

		if ($resource->user_message == NULL) {
			$resource->user_message = "We're sorry but this promo message does not appear to be working properly.  Please continue with your order.";
		}
		myerror_logging(2,"promo message is: ".$resource->user_message);
		myerror_logging(2,"amt off is: ".$amt);
		myerror_logging(2,"tax to be taken off is: ".$tax_amt);
		$resource->set("amt",$amt);
		$resource->set("tax_amt",$tax_amt);
		$resource->set("complete_promo",$complete_promo ? 'true' : 'false');
		$resource = $this->setPromoValuesOnResource($resource,$promo);
		return $resource;
	}

	function sortItemsInfoByPrice(&$items_info)
    {
        if ($this->log_level > 4) {
            myerror_log("*********** promo list items************");
            foreach ($items_info as $key=>$the_lower_array) {
                myerror_log("$key = ".$the_lower_array['item_name']);
            }
            myerror_log("***********end list************");
        }
        // now sort $items_info by price
        asort($items_info);
        if ($this->log_level > 4) {
            myerror_log("***********sorted promo list items ************");
            foreach ($items_info as $key=>$the_lower_array) {
                myerror_log("$key = ".$the_lower_array['item_name']);
            }
            myerror_log("***********end sorted list************");
        }

    }

	function loadUpPromoObjectArrays(&$promo_items)
	{
		if ($qo = $promo_items['qualifying_object']) {
			$promo_items['qualifying_object_array'] = explode(',',$qo);
		}
		if ($p1 = $promo_items['promo_item_1']) {
			$promo_items['promo_item_1_array'] = explode(',',$p1);
		}
		if ($p2 = $promo_items['promo_item_2']) {
			$promo_items['promo_item_2_array'] = explode(',',$p2);
		}
	}

	function doesItemSatisfyThisRequirement($item_info,$qualifying_object)
    {
        myerror_log("about to test item for match with $qualifying_object",5);
        $types = ['promo_tag','size_promo_tag','item_promo_tag','menu_type_promo_tag','cat_id'];
        foreach ($types as $type) {
            myerror_log("testing $type: ".$item_info["$type"],5);
            if ($item_info["$type"] == $qualifying_object) {
                myerror_log("We have a match for this requirement",5);
                return true;
            }
        }
        return false;
    }

	function doesItemSatisfyRequirements($item_info,$qualifying_object_array)
	{
		if ($this->testStringAgainstArrayOfStringsForMatch($item_info['cat_id'],$qualifying_object_array)) {
			return true;
		} else if ($this->testStringAgainstArrayOfStringsForMatch($item_info['size_promo_tag'],$qualifying_object_array)) {
			return true;
		} else if ($this->testStringAgainstArrayOfStringsForMatch($item_info['promo_tag'],$qualifying_object_array)) {
			return true;
		} else if ($this->testStringAgainstArrayOfStringsForMatch($item_info['item_promo_tag'],$qualifying_object_array)) {
			return true;
		} else if ($this->testStringAgainstArrayOfStringsForMatch($item_info['menu_type_promo_tag'],$qualifying_object_array)) {
			return true;
		} else {
			return false;
		}
	}

    function testStringAgainstArrayOfStringsForMatchV2($string,$array_of_strings)
    {
        foreach($array_of_strings as $list_item) {
            if ($string == $list_item) {
                return $list_item;
            }
        }
        return false;
    }

	function testStringAgainstArrayOfStringsForMatch($string,$array_of_strings)
	{
	    return ! ($this->testStringAgainstArrayOfStringsForMatchV2($string,$array_of_strings) === false);
	}

	function getItemsInfoForPromoProcessing($order_data)
	{
		$items = $order_data['items'];
		$items_info = array();
        myerror_log("we have the full items to test for promo: ".json_encode($items));
        myerror_log("----------");
		foreach ($items as $item) {
		    myerror_log("we have the item to test for promo: ".json_encode($item));
			if ($size_price_id = $item['sizeprice_id']) {
				$items_info_sql = "SELECT a.price,b.item_id,b.item_name,b.menu_type_id,c.cat_id,b.promo_tag as item_promo_tag,e.promo_tag as size_promo_tag,a.external_id,a.promo_tag,c.promo_tag as menu_type_promo_tag,a.tax_group ".
						"FROM Item_Size_Map a, Item b, Menu_Type c, Sizes e ".
						"WHERE a.size_id = e.size_id AND a.item_size_id = ".$size_price_id." AND a.item_id = b.item_id AND b.menu_type_id = c.menu_type_id";
			} else {
				$size_id = $item['size_id'];
				$item_id = $item['item_id'];
				$merchant_id = $price_record_merchant_id = getPriceRecordMerchantIdFromOrderData($order_data);
				$items_info_sql = "SELECT a.price,b.item_id,b.item_name,b.menu_type_id,c.cat_id,b.promo_tag as item_promo_tag,e.promo_tag as size_promo_tag,a.external_id,a.promo_tag,c.promo_tag as menu_type_promo_tag,a.tax_group ".
						"FROM Item_Size_Map a, Item b, Menu_Type c, Sizes e ".
						"WHERE a.size_id = e.size_id AND a.item_id = ".$item_id."  AND a.size_id = ".$size_id."  AND a.merchant_id = ".$merchant_id." AND a.item_id = b.item_id AND b.menu_type_id = c.menu_type_id";
			}

			myerror_logging(2,"PROMO SQL: ".$items_info_sql);
			$options[TONIC_FIND_BY_SQL] = $items_info_sql;
			$adapter = new ItemAdapter($this->mimetypes);
			$item_info = $adapter->select('',$options);
			$item_info = array_pop($item_info);
			$key_thing = ''.$item_info['price'];
			for ($i=0;$i<$item['quantity'];$i++) {
                $code = mt_rand(1111,9999);
                $key_thing = $key_thing."$i".$code;
                $items_info[$key_thing] = $item_info;
            }
		}
		return $items_info;
	}

	function processPromoTypeSix($order_data,$promo)
    {
        $resource = new Resource();
        $amt = $this->request->data['variable_promo_amt'];
        if ($order_data['order_amt'] < $amt) {
            $amt = $order_data['order_amt'];
        }
        $amt = number_format($amt,2);
        myerror_logging(2,"user is getting $".$amt." off of their order");

        if ($amt != 0.00) {
            if ($promo['payor_merchant_user_id'] == 2) {
                $the_tax_rate = $this->tax_rates_for_merchant[1];
                $tax_amt = $amt * $the_tax_rate;
                $tax_amt = round($tax_amt,2);
            }
            $resource->set("tax_amt",$tax_amt);
        }
        myerror_logging(3,"finishing promo calulcation.  amt: ".$amt."     tax_amt: ".$tax_amt);


        $resource->set("user_message_title","Promo Code Validated");
        $resource->set("user_message","Congratulations! You're getting $".$amt." off your order.");
        $resource->set("complete_promo",'true');
        $resource->set("amt",$amt);
        $resource = $this->setPromoValuesOnResource($resource,$promo);
        return $resource;
    }

	function processPromoTypeOne($order_data,$promo)
	{
		$promo_id = $promo['promo_id'];
		myerror_logging(2,"********* STARTING PROMO TYPE 1 **********");
		$amt = 0.00;
		$resource = new Resource();
		if ($promo_amts = PromoType1AmtMapAdapter::staticGetRecord(array("promo_id"=>$promo_id),'PromoType1AmtMapAdapter')) {
			$resource->set("user_message_title","Promo Code Validated");
			logData($promo_amts,"Promo Amounts",2);
            if ($order_data['sub_total'] == null) {
                $order_data['sub_total'] = $order_data['order_amt'];
            }
			if ( $order_data['sub_total'] > $promo_amts['qualifying_amt']) {
				myerror_logging(2,"validated that subtotal is greater than qualifying amount");
				$amt = $promo_amts['promo_amt'];
				if ($amt == 0) {
					// this is a percent off promo
					$amt = $order_data['sub_total']*$promo_amts['percent_off']/100;
					// now see if this is over the max

					if ($amt > $promo_amts['max_amt_off'] && $promo_amts['max_amt_off'] != 0.00) {
						$amt = $promo_amts['max_amt_off'];
					}
				}
				if ($order_data['sub_total'] < $amt) {
					$amt = $order_data['sub_total'];
				}
				$amt = number_format($amt,2);
				myerror_logging(2,"user is getting $".$amt." off of their order");
				$message1 = str_replace('%%amt%%', ''.$amt, $this->promo_messages['message1']);
				$resource->set("user_message",$message1);
				$resource->set("complete_promo",'true');
			} else {
				myerror_logging("FAILURE.....subtotal is NOT greater than qualifing amount");
				$resource->set("user_message",$this->promo_messages['message5']);
				$resource->set("complete_promo",'false');
			}

		} else {
			myerror_log("DATA INTEGRITY ERROR PROMO HAS NO AMT DATA ASSOCIATED WITH IT!");
			$body = "There is a data integrity error for promo id: $promo_id.  There are no amts associate with this promo. ".$_SERVER['HTTP_HOST'];
			MailIt::sendErrorEmail('PROMO TYPE1 AMT ERROR', $body);
			return $this->returnPromoError("We're sorry, this promo appears to be inactive at the moment.  Please contact customer support if you feel you have recieved this message in error.", 999);
		}
		if ($resource->user_message == NULL) {
			$resource->user_message = "We're sorry but there appears to be a message error, and I'm not sure what i'm supposed to tell you, please continue with your order.";
		}
		if ($amt != 0.00) {
			if ($promo['payor_merchant_user_id'] == 2) {
				$the_tax_rate = $this->tax_rates_for_merchant[1];
				$tax_amt = $amt * $the_tax_rate;
				$tax_amt = round($tax_amt,2);
			}
		}
		myerror_logging(3,"finishing promo calulcation.  amt: ".$amt."     tax_amt: ".$tax_amt);
		$resource->set("amt",$amt);
		$resource->set("tax_amt",$tax_amt);
		$resource = $this->setPromoValuesOnResource($resource,$promo);
		return $resource;
	}
	
	/*
	 * currently unused;
	 */
	function getPromoType1ReturnValueWithValidationOfOrderAmt($promo_id, $order_data)
	{
		myerror_logging(2,"********* NEW STARTING getPromoType1ReturnValues **********");
		$amt = 0.00;
		if ($promo_amts = MySQLAdapter::staticGetRecord(array("promo_id"=>$promo_id), 'PromoType1AmtMapAdapter'))
		{
			logData($promo_amts, 'Promo Type 1 Db Record');
			if ( $order_data['sub_total'] > $promo_amts['qualifying_amt'])
			{
				myerror_logging(3,"validated that subtotal is greater than qualifying amount");
				$amt = $this->getType1AmountOff($promo_amts, $order_data['sub_total']);
				myerror_logging(2,"user is getting $".$amt." off of their order");
				return $amt;
			} else {
				return false;
			}
				
		} else {
			myerror_log("DATA INTEGRITY ERROR PROMO HAS NO AMT DATA ASSOCIATED WITH IT!");
			$body = "There is a data integrity error for promo id: $promo_id.  There are no amts associate with this promo. ".$_SERVER['HTTP_HOST'];
			MailIt::sendErrorEmail('PROMO TYPE1 AMT ERROR', $body);
			throw new NoAmountsSetUpForType1PromoException();
			//return $this->returnPromoError("We're sorry, this promo appears to be inactive at the moment.  Please contact customer support if you feel you have recieved this message in error.", 999);
		}
	}
	
	/*
	 * currently unused;
	 */
	function getType1AmountOff($promo_record,$sub_total)
	{
		$amt = $promo_record['promo_amt'];
		if ($amt == 0)
		{
			// this is a percent off promo
			$amt = $sub_total*$promo_record['percent_off']/100;
			// now see if this is over the max
			if ($amt > $promo_record['max_amt_off'] && $promo_record['max_amt_off'] != 0.00)
				$amt = $promo_record['max_amt_off'];
		}
		//for static amount off, make sure its not more than the order
		if ($sub_total < $amt)
			$amt = $sub_total;
		$amt = number_format($amt,2);
		myerror_logging(3,"type 1 promo amount off has been calculated at $amt");
		return $amt;
	}
	
	function setTaxRates($merchant_id)
	{
		$tax_rates = MerchantController::getTotalTaxRatesStatic($merchant_id);
		$tax_rates[0] = 0.00;
		$this->tax_rates_for_merchant = $tax_rates;
	}

	function setPromoMessagesFromPromoId($promo_id)
	{
		$this->promo_messages = $this->getPromoMessages($promo_id);
	}
	
	function getPromoMessages($promo_id)
	{
		// get the messages associated with this promo
		$promo_message_map_adpater = new PromoMessageMapAdapter(getM());
		if ($promo_messages_record = $promo_message_map_adpater->getRecord(array('promo_id'=>$promo_id))) {
			return $promo_messages_record;
		} else {
			$body = "There is a data integrity error in for promo id: $promo_id.  There are no messages associate with this promo. ".$_SERVER['HTTP_HOST'];
			MailIt::sendErrorEmail('PROMO MESSAGE ERROR', $body);
			myerror_log("DATA INTEGRITY ERROR PROMO HAS NO MESSAGE DATA ASSOCIATED WITH IT!");
			throw new NoMessageSetUpForPromoException();
		}
	}

	/**
	 * 
	 * @desc Currently ONLY works to detemine if the merchant user combination is part of the Airline Workers Discount
	 * @param int $user_id
	 * @param int $merchant_id
	 * @return Resource
	 */
	function getAutoPromoForThisUserMerchantCombination($user_id,$merchant_id)
	{
//CHANGE_THIS  ----   currently coded only for airports 
		return $this->getAirportAutoPromoIfItAppliesToThisUserMerchantCombination($user_id, $merchant_id);
	}

	function getAirportAutoPromoIfItAppliesToThisUserMerchantCombination($user_id,$merchant_id)
	{
		if (AirportAreasMerchantsMapAdapter::isMerchantAnAirportLocation($merchant_id)) {
			if ($group = UserGroupMembersAdapter::getGroupIfUserIsAMemberOfItByName($user_id, 'Airport Employees')) {
				if ($promo_id = $group['promo_id']) {
					return SplickitController::getResourceFromId($promo_id, 'Promo');
				}
			}
		}
	}
	
	function returnPromoError($error_message,$error_code)
	{
		myerror_log("ABOUT TO RETURN THE PROMO ERROR");
		$error_data['amt'] = '0.00';
		$error_data['text_title'] = "Promo Validation Error";
		return createErrorResourceWithHttpCode($error_message, 422, $error_code, array("error_type"=>'promo'));
	}

	function getPromoForEditByPromoId($promo_id)
    {
        $promo_record = $this->adapter->getRecordFromPrimaryKey($promo_id);
        return $this->getPromoForEdit($promo_record);
    }

	function getPromoForEdit($promo_record)
    {
        $this->menu_id = $promo_record['menu_id'];
        if ($promo_type = $promo_record['promo_type']) {
            $promo_id = $promo_record['promo_id'];
            switch ($promo_type) {
                case 1 : {
                    $data = array_merge($promo_record,$this->getTypeOnePromoValuesAndMessages($promo_id));
                }
                    break;
                case 2 : {
                    $data = array_merge($promo_record,$this->getTypeTwoPromoValuesAndMessages($promo_id));
                }
                    break;
                case 4 : {}
                case 5 : {
                    $data = array_merge($promo_record,$this->getTypeFourFivePromoValuesAndMessages($promo_id));
                }
                    break;
                case 300 : {
                    $data = array_merge($promo_record,$this->getTypeThreeHundredPromoValuesAndMessages($promo_id));
                }
                    break;
                default: {
                    return createErrorResourceWithHttpCode("No a valid promo type: $promo_type", 422, 422);
                }
            }
            // get promo key words
            $data['promo_key_words'] = $this->getAllKeyWordRecordsForPromoId($promo_id);
            $data['promo_merchant_maps'] = $this->getAllPromoMerchantMapRecordsForPromoId($promo_id);
            unset($data['points']);
            unset($data['id']);
            unset($data['map_id']);
            unset($data['class']);
            unset($data['mimetype']);
            unset($data['show_in_app']);
            unset($data['logical_delete']);
            unset($data['public']);
            unset($data['offer']);
            unset($data['payor_merchant_user_id']);
            unset($data['reallocate']);
            unset($data['promo_key_word']);
            return Resource::dummyfactory($data);
        }
    }

    function getAllPromoMerchantMapRecordsForPromoId($promo_id)
    {
        $pmma = new PromoMerchantMapAdapter(getM());
        return cleanData($pmma->getRecords(['promo_id'=>$promo_id]));
    }

    function getAllKeyWordRecordsForPromoId($promo_id)
    {
        $pkwma = new PromoKeyWordMapAdapter(getM());
        return cleanData($pkwma->getRecords(['promo_id'=>$promo_id]));
    }

    function getTypeThreeHundredPromoValuesAndMessages($promo_id)
    {
        $ptoama = new PromoDeliveryAmountMapAdapter(getM());
        $data = cleanData($ptoama->getRecord(['promo_id'=>$promo_id]));
        // delivery promo messages are fixed at line 583
//        $data['promo_messages'] = $this->getCleanMessagesFromPromoId($promo_id);
//        unset($data['promo_messages']['message2']);
        return $data;
    }

    function getTypeOnePromoValuesAndMessages($promo_id)
    {
        $ptoama = new PromoType1AmtMapAdapter(getM());
        $data = cleanData($ptoama->getRecord(['promo_id'=>$promo_id]));
        $data['promo_messages'] = $this->getCleanMessagesFromPromoId($promo_id);
        return $data;
    }

    function getAdapterFromName($name)
    {
        if ($name == 'Item') {
            return new ItemAdapter(getM());
        } else if ($name == 'Menu_Type') {
            return new MenuTypeAdapter(getM());
        } else if ($name == 'Size') {
            return new SizeAdapter(getM());
        } else if ($name == 'Item_Size') {
            return new ItemSizeAdapter(getM());
        } else if ($name == 'Entre') {
            return 'Entre';
        }
    }

    function getObjectNameListForPromoEditDisplay($object_id_list)
    {
        $name_list = [];
        foreach ($object_id_list as $object_name_id) {
            $s = explode('-',$object_name_id);
            $name = $s[0];
            if ($name == 'Entre') {
                $object_name = 'Entre';
            } else {
                $adapter = $this->getAdapterFromName($name);
                $record = $adapter->getRecordFromPrimaryKey($s[1]);
                if ($name == 'Item') {
                    $object_name = $record['item_name'];
                } else if ($name == 'Size') {
                    $menu_type_id = $record['menu_type_id'];
                    $mta = $this->getAdapterFromName('Menu_Type');
                    $menu_type_record = $mta->getRecordFromPrimaryKey($menu_type_id);
                    $object_name = $record['size_name'].'-'.$menu_type_record['menu_type_name'];
                } else if ($name == 'Menu_Type') {
                    $object_name = $record['menu_type_name'];
                } else if ($name == 'Item_Size') {
                    $item_id = $record['item_id'];
                    $ia = $this->getAdapterFromName('Item');
                    $item_record = $ia->getRecordFromPrimaryKey($item_id);
                    $size_id = $record['size_id'];
                    $sa = $this->getAdapterFromName('Size');
                    $size_record = $sa->getRecordFromPrimaryKey($size_id);
                    $object_name = $size_record['size_name'].'-'. $item_record['item_name'];
                }
            }
            $name_list[] = $object_name;
        }
        return $name_list;

    }

    function getTypeTwoPromoValuesAndMessages($promo_id)
    {
        $pttama = new PromoType2ItemMapAdapter(getM());
        $data = cleanData($pttama->getRecord(['promo_id'=>$promo_id]));

        // now get menu objects that match
        //$complete_menu = CompleteMenu::ge
        $data['qualifying_object_name_list'] = $this->getObjectNameListForPromoEditDisplay(explode(',',$data['qualifying_object_id_list']));
        $data['promotional_object_name_list'] = $this->getObjectNameListForPromoEditDisplay(explode(',',$data['promotional_object_id_list']));

        unset($data['qualifying_object']);
        unset($data['promo_item_1']);
        unset($data['qualifying_object_id_list']);
        unset($data['promotional_object_id_list']);
        $data['promo_messages'] = $this->getCleanMessagesFromPromoId($promo_id);
        return $data;
    }

    function getTypeFourFivePromoValuesAndMessages($promo_id)
    {
        $ptffama = new PromoType4ItemAmountMapsAdapter(getM());
        $data = cleanData($ptffama->getRecord(['promo_id'=>$promo_id]));
        if ($data['qualifying_object_id_list'] == null) {
            $data['qualifying_object_name_list'] = [];
        } else {
            $data['qualifying_object_name_list'] = $this->getObjectNameListForPromoEditDisplay(explode(',',$data['qualifying_object_id_list']));
        }
        unset($data['qualifying_object_id_list']);
            unset($data['qualifying_object']);
        $data['promo_messages'] = $this->getCleanMessagesFromPromoId($promo_id);
        return $data;
    }

    function updatePromoKeyWordMaps($promo_resource)
    {
        $data = $this->data;
        $promo_key_word_map_adapter = new PromoKeyWordMapAdapter(getM());
        if ($this->isThisRequestMethodAPost()) {
            if ($key_word = $data['promo_key_word']) {
                if (isset($promo_resource->brand_id)) {
                    $data['brand_id'] = $promo_resource->brand_id;
                    $data['promo_id'] = $promo_resource->promo_id;
                    if ($resource = Resource::createByData($promo_key_word_map_adapter,$data)) {
                        return Resource::dummyfactory(array("result"=>'success'));
                    } else {
                        return createErrorResourceWithHttpCode("Error. Could not create record: ".$promo_key_word_map_adapter->getLastErrorText(), 500, 500);
                    }
                } else {
                    return createErrorResourceWithHttpCode("Error. Promo has no brand_id associated with it, cannot create keyword record.", 500, 500);
                }
            } else {
                return createErrorResourceWithHttpCode("No valid key word submitted", 422, 422);
            }
        } else if ($this->isThisRequestMethodADelete()) {
            if (preg_match("%/key_words/([0-9]{3,15})%", $this->request->url, $matches)) {
                if ($promo_key_word_map_adapter->delete($matches[1])) {
                    return Resource::dummyfactory(array("result"=>'success'));
                } else {
                    return createErrorResourceWithHttpCode("Error. Could not delete record: ".$promo_key_word_map_adapter->getLastErrorText(), 500, 500);
                }
            } else {
                return createErrorResourceWithHttpCode("Invalid DELETE request. No map id submitted", 422, 422);
            }
        } else {
            return createErrorResourceWithHttpCode("Invalid request method submitted to key word endpoint", 422, 422);
        }
    }

    function updatePromoMerchantMaps($promo_resource)
    {
        $data = $this->data;
        $promo_merchant_map_adapter = new PromoMerchantMapAdapter(getM());
        if (preg_match("%/merchant_maps/([0-9]{3,15})%", $this->request->url, $matches)) {
            if ($promo_merchant_map_resource = Resource::find($promo_merchant_map_adapter,$matches[1])) {
                if ($this->isThisRequestMethodADelete()) {
                    if ($promo_merchant_map_adapter->delete($matches[1])) {
                        unset($promo_merchant_map_resource);
                        return Resource::dummyfactory(array("result"=>'success'));
                    } else {
                        return createErrorResourceWithHttpCode("Error. Could not delete record: ".$promo_merchant_map_adapter->getLastErrorText(), 500, 500);
                    }
                } else if ($promo_merchant_map_resource->saveResourceFromData($data)) {
                    return $promo_merchant_map_resource->refreshResource();
                } else {
                    return createErrorResourceWithHttpCode("Error. Could not update record: ".$promo_merchant_map_resource->_adapter->getLastErrorText(), 500, 500);
                }
            } else {
                return createErrorResourceWithHttpCode("Promo merchant map id does not exist: ".$matches[1], 422, 422);
            }
        } else if ($merchant_id = $data['merchant_id']) {
            if ($this->isThisRequestMethodAPost()) {
                $data['promo_id'] = $promo_resource->promo_id;
                if ($resource = Resource::createByData($promo_merchant_map_adapter,$data)) {
                    return Resource::dummyfactory(array("result"=>'success'));
                } else {
                    return createErrorResourceWithHttpCode("Error. Could not create record: ".$promo_merchant_map_adapter->getLastErrorText(), 500, 500);
                }
            } else {
                return createErrorResourceWithHttpCode("Method not allowed for this endpoint: ".strtoupper($this->request->method), 422, 422);
            }

        } else {
            return createErrorResourceWithHttpCode("No valid merchant map submitted", 422, 422);
        }
    }

    function updatePromo($promo_resource)
    {
        if ($promo_resource->saveResourceFromData($this->data)) {
            switch ($promo_resource->promo_type) {
                case 1 : {
                    if ($promo_type_1_amt_map_resource = Resource::find(new PromoType1AmtMapAdapter(getM()),null,[TONIC_FIND_BY_METADATA=>['promo_id'=>$promo_resource->promo_id]])) {
                        $promo_type_1_amt_map_resource->saveResourceFromData($this->data);
                    }
                    return $this->getPromoForEditByPromoId($promo_resource->promo_id);
                }
                    break;
                case 2 : {
                    if ($promo_type_2_item_map_resource = Resource::find(new PromoType2ItemMapAdapter(getM()),null,[TONIC_FIND_BY_METADATA=>['promo_id'=>$promo_resource->promo_id]])) {
                        $promo_type_2_item_map_resource->saveResourceFromData($this->data);
                    }
                    return $this->getPromoForEditByPromoId($promo_resource->promo_id);
                }
                    break;
                case 4 : {}
                case 5 : {
                    if ($promo_type_4_item_amount_map_resource = Resource::find(new PromoType4ItemAmountMapsAdapter(getM()),null,[TONIC_FIND_BY_METADATA=>['promo_id'=>$promo_resource->promo_id]])) {
                        $promo_type_4_item_amount_map_resource->saveResourceFromData($this->data);
                    }
                    return $this->getPromoForEditByPromoId($promo_resource->promo_id);
                }
                    break;
                case PromoController::FREE_DELIVERY_TYPE_ID : {
                    if ($pdam_resource = Resource::find(new PromoDeliveryAmountMapAdapter(getM()),null,[TONIC_FIND_BY_METADATA=>['promo_id'=>$promo_resource->promo_id]])) {
                        $pdam_resource->saveResourceFromData($this->data);
                    }
                    return $this->getPromoForEditByPromoId($promo_resource->promo_id);

                }
                    break;
                default: {
                    return createErrorResourceWithHttpCode("No a valid promo type: ".$promo_resource->promo_type, 422, 422);
                }
            }
        }
    }

    function createPromo()
    {
        myerror_log("starting create promo");
        $request = $this->request;
        $data = $request->data;
        if ($promo_type = $data['promo_type']) {
            if (! isset($data['brand_id'])) {
                return createErrorResourceWithHttpCode("No promo brand_id submitted", 422, 422);
            }

            $auto_promo = false;
            $key_words = [];
            if ($data['auto_promo'] == 1) {
                $auto_promo = true;
                $data['promo_key_word'] = "X_".str_replace(' ','',$data['key_word']);
            } else {
                $key_words = explode(',',$data['key_word']);
                $data['promo_key_word'] = "master_".str_replace(' ','',$key_words[0]);
            }
            $data['payor_merchant_user_id'] = 2;
            $promo_adapter = new PromoAdapter(getM());
            if ($promo_resource = Resource::createByData($promo_adapter,$data)) {
                if ($promo_resource->promo_type == 6) {
                    $promo_key_word = generateCode(10);
                    $pkwm_adapter = new PromoKeyWordMapAdapter(getM());
                    $promo_resource->set("promo_key_words",[]);
                    $promo_resource->promo_key_words[] = Resource::createByData($pkwm_adapter, array("promo_id"=>$promo_resource->promo_id,"promo_key_word"=>"$promo_key_word","brand_id"=>$data['brand_id']));
                    Resource::createByData(new PromoMerchantMapAdapter(getM()), array("merchant_id" => 0, "promo_id" => $promo_resource->promo_id));
                    return $promo_resource;
                }
                $promo_id = $promo_resource->promo_id;
                $data['promo_id'] = $promo_id;
                if (isset($data['merchant_id'])) {
                    if ($data['merchant_id'] == 0) {
                        $brand_id = $data['brand_id'];
                        $m_options[TONIC_FIND_BY_METADATA]['brand_id'] = $brand_id;
                        $merchant_adapter = new MerchantAdapter(getM());
                        $merchant_id_array = [];
                        foreach (Resource::findAll($merchant_adapter,null,$m_options) as $merchant_resource) {
                            $merchant_id_array[] = $merchant_resource->merchant_id;
                        }
                    } else {
                        $merchant_list = $data['merchant_id'];
                        $merchant_id_array = explode(',', $merchant_list);
                    }

                    $promo_resource->set("merchant_id_maps", []);
                    foreach ($merchant_id_array as $merchant_id) {
//                        $pmm_resource = Resource::createByData(new PromoMerchantMapAdapter(getM()), array("merchant_id" => $merchant_id, "promo_id" => $promo_id));
//                        $promo_resource->merchant_id_maps[] = cleanDataForResponse($pmm_resource->getDataFieldsReally());
                        $promo_resource->merchant_id_maps[] = createResourceReturnCleanArray(new PromoMerchantMapAdapter(getM()), array("merchant_id" => $merchant_id, "promo_id" => $promo_id));
                    }
                }
                switch ($promo_type) {
                    case 1 : {
                        $resource = $this->createTypeOnePromoValuesAndMessages($data,$promo_resource);
                    }
                        break;
                    case 2 : {
                        $resource = $this->createTypeTwoPromoValuesAndMessages($data,$promo_resource);
                    }
                        break;
                    case 4 : {}
                    case 5 : {
                        $resource = $this->createTypeFourFivePromoValuesAndMessages($data,$promo_resource);
                    }
                        break;
                    case PromoController::FREE_DELIVERY_TYPE_ID : {
                        $resource = $this->createFreeDeliveryPromoValuesAndMessages($data,$promo_resource);
                    }
                        break;
                    default: {
                        $resource = createErrorResourceWithHttpCode("No a valid promo type: $promo_type", 422, 422);
                        return $resource;
                    }
                }
                $resource->set("promo_key_words",[]);
                if (! $auto_promo) {
                    $pkwm_adapter = new PromoKeyWordMapAdapter(getM());
                    foreach ($key_words as $key_word) {
                        //$resource->promo_key_words[] = Resource::createByData($pkwm_adapter, array("promo_id"=>$promo_id,"promo_key_word"=>"$key_word","brand_id"=>$data['brand_id']));
                        $resource->promo_key_words[] = createResourceReturnCleanArray($pkwm_adapter, array("promo_id"=>$promo_id,"promo_key_word"=>"$key_word","brand_id"=>$data['brand_id']));
                    }
                }
                return $resource;
            } else {
                return createErrorResourceWithHttpCode("There was an error creating the promo",500,500);
            }
        } else {
            return createErrorResourceWithHttpCode("No promo type submitted", 422, 422);
        }
    }

    function createFreeDeliveryPromoValuesAndMessages($data,$promo_resource)
    {
        if (! isset($data["percent_off"])) {
            $data["percent_off"] = 100;
        }
        $data['promo_id'] = $promo_resource->promo_id;
        $promo_resource->set("promo_amount",$this->cleanPromoMessagesForFrontEnd(createResourceReturnCleanArray(new PromoDeliveryAmountMapAdapter(getM()),$data)));

        $promo_message_data = [];
        $promo_message_data['promo_id'] = $promo_resource->promo_id;
        $promo_amount = $promo_resource->promo_amount;
        $percent_off = $promo_amount['percent_off'];
        $message_controll = $percent_off == 100 ? "free" : "$percent_off% off";

        $promo_message_data['message1'] = "Congratulations! You're getting $message_controll delivery!";
        $promo_message_data['message2'] = "Congratulations! You're getting $message_controll delivery!";
        $qualifying_amount = number_format($data['qualifying_amt'],2);
        $promo_message_data['message5'] = "Here's the deal, spend more than $".$qualifying_amount.", and you'll get $message_controll delivery!";
        $promo_resource->set("promo_messages",$this->cleanPromoMessagesForFrontEnd(createResourceReturnCleanArray(new PromoMessageMapAdapter(getM()),$promo_message_data)));

        return $promo_resource;

    }

    function getCleanMessagesFromPromoId($promo_id)
    {
        $pomo_message_adapter = new PromoMessageMapAdapter(getM());
        $promo_messages = $pomo_message_adapter->getRecord(['promo_id'=>$promo_id]);
        return $this->cleanPromoMessagesForFrontEnd($promo_messages);
    }

    function cleanPromoMessagesForFrontEnd(&$promo_messages)
    {
        for ($i=1;$i<6;$i++) {
            if ($promo_messages["message$i"] == null) {
                unset($promo_messages["message$i"]);
            }
        }
        unset($promo_messages['insert_id']);
        unset($promo_messages['created']);
        return $promo_messages;
    }

    function createTypeOnePromoValuesAndMessages($data,$promo_resource)
    {
        $promo_message_data = [];
        $promo_message_data['promo_id'] = $promo_resource->promo_id;
        $promo_message_data['message1'] = "Congratulations! You're getting $%%amt%% off your order!";
        $qualifying_amount = number_format($data['qualifying_amt'],2);
        $promo_message_data['message5'] = "Here's the deal, spend more than $".$qualifying_amount.", and you'll get a discount on your order";
        //$promo_resource->set("promo_messages",Resource::createByData(new PromoMessageMapAdapter(getM()),$promo_message_data));
        $promo_resource->set("promo_messages",$this->cleanPromoMessagesForFrontEnd(createResourceReturnCleanArray(new PromoMessageMapAdapter(getM()),$promo_message_data)));

        //$promo_resource->set('promo_amount_map',Resource::createByData(new PromoType1AmtMapAdapter(getM()),$data));
        $promo_resource->set("promo_amount_map",createResourceReturnCleanArray(new PromoType1AmtMapAdapter(getM()),$data));
        return $promo_resource;
    }

    function createAndAssignPromoTags($object_array,$promo_type,$promo_obect_type)
    {
        //$qualifying_objects_array = $data['qualifying_object_array'];
        $promo_tag = generateAlphaCode(10);
        $qualifying_promo_tags = [];
        foreach ($object_array as $menu_object) {
            $moa = explode("-",$menu_object);
            $object_type = $moa[0];
            if ($object_type == 'Entre') {
                $qualifying_promo_tags[] = 'E';
                $this->objects[$promo_obect_type][] = 'Entre';
            } else if ($object_type == 'Side') {
                $qualifying_promo_tags[] = 'S';
                $this->objects[$promo_obect_type][] = 'Side';
            } else if ($object_type == 'Drink') {
                $qualifying_promo_tags[] = 'B';
                $this->objects[$promo_obect_type][] = 'Beverage';
            } else {
                //strip out underscore from Menu_Type and Item_Size
                $adapter_name = str_replace('_','',$object_type).'Adapter';
                $adapter = new $adapter_name(getM());
                if ($id = $moa[1]) {
                    if (is_numeric($id) && $id > 0) {
                        if ($menu_object_resource = Resource::find($adapter,"$id")) {
                            if ($menu_object_resource->promo_tag == null || trim($menu_object_resource->promo_tag) == '') {
                                if ($promo_type == 5) {
                                    $promo_tag = generateAlphaCode(10);
                                    $qualifying_promo_tags[] = $promo_tag;
                                } else if ($promo_type == 4 || $promo_type == 2) {
                                    if (!in_array($promo_tag,$qualifying_promo_tags)) {
                                        $qualifying_promo_tags[] = $promo_tag;
                                    }
                                } else {
                                    throw new Exception("Unknown promo type in createAndAssignPromoTags: ".$promo_type);
                                }
                                $menu_object_resource->promo_tag = $promo_tag;
                                $menu_object_resource->save();

                            } else {
                                $existing_promo_tag = $menu_object_resource->promo_tag;
                                $qualifying_promo_tags[] = $existing_promo_tag;
                            }
                            if ($object_type == "MenuType") {
                                $this->objects[$promo_obect_type][] = $menu_object_resource->menu_type_name;
                            } else if ($object_type == "Item") {
                                $this->objects[$promo_obect_type][] = $menu_object_resource->item_name;
                            } else if ($object_type == "ItemSize") {
                                $item_adapter = new ItemAdapter(getM());
                                $item_record = $item_adapter->getRecordFromPrimaryKey($menu_object_resource->item_id);
                                $size_adapter = new SizeAdapter(getM());
                                $size_record = $size_adapter->getRecordFromPrimaryKey($menu_object_resource->size_id);
                                $this->objects[$promo_obect_type][] = $size_record['size_name'].' '.$item_record['item_name'];
                            }
                        }
                    } else {
                        throw new Exception("Not a valid id submitted to create promo tag for $object_type.  submitted id: $id");
                    }
                } else {
                    throw new Exception("No id submitted for menu object: $object_type");
                }
            }
        }
        return implode(",",$qualifying_promo_tags);

    }

	function createTypeTwoPromoValuesAndMessages($data,$promo_resource)
    {
        $data['qualifying_object'] = $this->createAndAssignPromoTags($data['qualifying_object_array'],$promo_resource->promo_type,'qualifying');
        $data['promo_item_1'] = $this->createAndAssignPromoTags($data['promo_item_1_array'],$promo_resource->promo_type,'promotional');
        $promo_message_data = [];
        $promo_message_data['promo_id'] = $promo_resource->promo_id;

        $data['qualifying_object_id_list'] = implode(',',$data['qualifying_object_array']);
        $data['promotional_object_id_list'] = implode(',',$data['promo_item_1_array']);

        $promo_message_data['message1'] = "Congratulations! You're getting a FREE ".$this->objects['promotional'][0]."!";
        $promo_message_data['message4'] = "Almost there, now add a ".$this->objects['promotional'][0]." to this order and its FREE!";
        $promo_message_data['message5'] = "Here's the deal, order a ".$this->objects['qualifying'][0].", then add a ".$this->objects['promotional'][0]." to go with it, and its FREE!";
        $promo_resource->set("promo_messages",$this->cleanPromoMessagesForFrontEnd(createResourceReturnCleanArray(new PromoMessageMapAdapter(getM()),$promo_message_data)));

        $promo_resource->set('promo_amount_map',createResourceReturnCleanArray(new PromoType2ItemMapAdapter(),$data));
        return $promo_resource;
    }

	function createTypeFourFivePromoValuesAndMessages($data,$promo_resource)
    {
        $data['qualifying_object_id_list'] = implode(',',$data['qualifying_object_array']);
        $data['qualifying_object'] = $this->createAndAssignPromoTags($data['qualifying_object_array'],$promo_resource->promo_type,'qualifying');
        $promo_message_data = [];
        $promo_message_data['promo_id'] = $promo_resource->promo_id;
        $promo_message_data['message1'] = $data['promo_type'] == 4 ? "Congratulations! You're getting %%amt%% off of your %%item_name%%!" : "Congratulations! You're getting %%amt%% off of your order!";
        $qualifying_item_list = '';
        if (sizeof($this->objects['qualifying']) == 1) {
            $qualifying_item_list = $this->objects['qualifying'][0];
        } else {
            if ($data['promo_type'] == 4) {
                $separator = 'or';
                $offset = -4;
            } else {
                $separator = 'and';
                $offset = -5;
            }
            foreach ($this->objects['qualifying'] as $item) {
                $qualifying_item_list = $qualifying_item_list."$item $separator ";
            }
            $qualifying_item_list = substr($qualifying_item_list,0,$offset);
        }


        $message5 = "Here's the deal, order a $qualifying_item_list, and you'll get a discount!";
        $promo_message_data['message5'] = $message5;
        $promo_resource->set("promo_messages",$this->cleanPromoMessagesForFrontEnd(createResourceReturnCleanArray(new PromoMessageMapAdapter(getM()),$promo_message_data)));

        $promo_resource->set('promo_amount_map',createResourceReturnCleanArray(new PromoType4ItemAmountMapsAdapter(getM()),$data));
        return $promo_resource;
    }

    function getPromoMinumimNotMetMessage($minimum_amount)
    {
        return str_replace('%%minimum_amount%%',number_format($minimum_amount,2),self::PROMO_MINIMUM_NOT_MET_MESSAGE);
    }
}

class NoMessageSetUpForPromoException extends Exception
{
    public function __construct($code = 0) {
        parent::__construct("Data Integrity Error. Promo has no messages associated with it", $code);
    }	
}

class NoAmountsSetUpForType1PromoException extends Exception
{
    // Redefine the exception so message isn't optional
    public function __construct($code = 0) {
        parent::__construct("No Amounts been set up for this promo.", $code);
    }
	
}

class InvalidPromoException extends Exception
{
	public function __construct($message,$code) {
		parent::__construct($message, $code);
	}
}
