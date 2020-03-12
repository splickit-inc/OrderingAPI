<?php

class HeartlandLoyaltyController extends LoyaltyController
{
    var $pin;
    var $qrcode_number;

    function __construct($mimetypes, $user, $request,$log_level = 0)
    {
        parent::__construct($mimetypes, $user, $request,$log_level);
    }

    function sendLoyaltyOrderEvent($complete_order,$points)
    {
        if ($heartland_store_id = MerchantHeartlandInfoMapsAdapter::getHeartlandStoreId($complete_order['merchant_id'])) {
            $this->service->setStore($heartland_store_id);
        } else {
            MailIt::sendErrorEmailSupport("Heartland Merchant NOT SET UP", "Attempting to send earn points for heartland merchant but no creds in DB for merchant_id: ".$complete_order['merchant_id']);
            if (isLaptop()) {
                throw new Exception("There no heartland data for this merchant: ".$complete_order['merchant_id'], $code, $previous);
            }
            return;
        }
        if ($points > 0) {
            return $this->doReward($complete_order,$points);
        } else if ($points < 0) {
            // redeem. points are negative so reset to positive
            return $this->redeemLoyaltyPointsFromAccount(-$points);
        }
    }

    function doReward($complete_order,$points)
    {
        $rewarded_purchase_amount = $this->getRewardedPurchaseAmountFromCompleteOrder($complete_order);
        return $this->addRewardToAccount($rewarded_purchase_amount);

    }

    function getRewardedPurchaseAmountFromCompleteOrder($complete_order)
    {
        $rewardable_amount = number_format($complete_order['order_amt']+$complete_order['promo_amt']+$complete_order['total_tax_amt'],2);
        return $rewardable_amount;
    }


    function addRewardToAccount($purchase_amount)
    {
        $account_credentials = $this->getSvaAndPinFromLoyaltyNumber();
        $heartland_loyalty_service = $this->service;
        $this->service_response = $heartland_loyalty_service->rewardPoints($account_credentials['sva'], $account_credentials['pin'], $purchase_amount);
        return $this->service_response;

    }

    /**
     * @deprecated
     * @desc old way of doing it. Use addRewardToAccount going foward.
     * @param $points
     */
    function addLoyaltyPointsToAccount($points)
    {
        $account_credentials = $this->getSvaAndPinFromLoyaltyNumber();
        $heartland_loyalty_service = $this->service;
        $this->service_response = $heartland_loyalty_service->addPoints($account_credentials['sva'], $account_credentials['pin'], $points);
        return $this->service_response;
    }

    function redeemLoyaltyPointsFromAccount($points)
    {
        $account_credentials = $this->getAccountCredentials();
        $heartland_loyalty_service = $this->service;
        $this->service_response = $heartland_loyalty_service->redeemPoints($account_credentials['sva'], $account_credentials['pin'], $points);
        return $this->service_response;
    }

    function getAccountCredentials()
    {
        if (isset($this->loyalty_number) && isset($this->pin)) {
            return array("sva"=>$this->loyalty_number,"pin"=>$this->pin,"qrcode_number"=>$this->qrcode_number);
        } else {
            return $this->getSvaAndPinFromLoyaltyNumber();
        }
    }

    function getSvaPinAndQRcodeFromSubmittedLoyaltyNumber($loyalty_number)
    {
        $s = explode(':', $loyalty_number);
        return array("sva"=>$s[0],"pin"=>$s[1],"qrcode_number"=>$s[2]);
    }

    function getSvaAndPinFromLoyaltyNumber()
    {
        // this is confusing and needs to be rethought through in the larger context.  why would we need a function like this? seems like good coding would eleminate the need.
        if (isset($this->loyalty_number) && substr_count($this->loyalty_number,":") > 0) {
            return $this->getSvaPinAndQRcodeFromSubmittedLoyaltyNumber($this->loyalty_number);
        } else if ($loyalty_record = $this->getLocalAccountInfo() ) {
            return $this->getSvaPinAndQRcodeFromSubmittedLoyaltyNumber($loyalty_record['loyalty_number']);
        } else if (isset($this->loyalty_number)) {
            return $this->getSvaPinAndQRcodeFromSubmittedLoyaltyNumber($this->loyalty_number);
        }
        return array();
    }


    function createAccount($user_id,$points)
    {
        $hls = $this->service;
        //first need to create user
        $user_resource = Resource::find(new UserAdapter($mimetypes),"$user_id", $options);
        $password = ($_SERVER['PHP_AUTH_PW'] == null || trim($_SERVER['PHP_AUTH_PW']) == '') ? generateCode(8) : $_SERVER['PHP_AUTH_PW'];
        $this->service_response = $hls->createPitCardUser($user_resource->email, $password);
        if ($this->service_response['http_code'] == 204) {
            $result = $hls->createAccountKatana($user_resource->email, $password);
            $this->service_response = $result;
            if ($result['status'] == 'success') {
                $loyalty_number = $result['id'].":".$result['pin'].":".$result['track2'];
                if ($points > 0) {
                    $hls->setStore('splickitstore');
                    $hls->addPoints($result['id'],$result['pin'],$points);
                }
                return $this->addUserBrandLoyaltyRecord($user_id, $loyalty_number, $points);
            } else {
                myerror_log("we had an error creating the loyalty account with the heartland service");
                //probably shuold throw an exception here
            }
        } else {
            myerror_log("we had an error creating pita card user with the heartland service: ".$this->service_response['description']);
            if ($this->service_response['description'] == 'That alias is in use by another profile') {
                $this->service_response['error'] = "We're sorry but that email is already registered with loyalty service, please link your card by entering your loyalty number (and pin if required).";
            } else {
                $this->service_response['error'] = "We're sorry but there was an unknown error creating the loyalty account. Please try again or contact customer service.";
            }
            $this->service_response['error_code'] = $response['http_code'];
            //probably shuold throw an exception here
        }
        return false;

    }

    /**
     *
     * @desc used to link an existing external loyalty account to a splickit user account
     * @param unknown_type $user_id
     * @param unknown_type $points
     *
     * @return Resource
     */
    function linkAccount($user_id,$points)
    {
        myerror_logging(3, "about to get remote account info for ".__CLASS__);
        $result = $this->getRemoteAccountInfo($this->getLoyaltyNumber());
        $this->loyalty_number = $result['loyalty_number'].':'.$result['pin'].':'.$result['track2'];
        if ($result['status'] == 'success') {
            return $this->addLoyaltyRecord($user_id, $result['Points']);
        } else {
            return false;
        }
    }

    function getRemoteAccountInfo($loyalty_number)
    {
        if ($this->remote_account_info) {
            myerror_logging(3,"there is remote info saved");
            return $this->remote_account_info;
        }
        $l = explode(":", $loyalty_number);
        $sva = $l[0];
        $pin = isset($l[1]) ? $l[1] : $this->pin;
        $hls = $this->service;
        $result = $hls->getBalance($sva, $pin);
        $this->service_response = $result;
        if ($result['status'] == 'success')
        {
            $result['loyalty_number'] = $sva;
            $result['pin'] = $pin;
            $qrcode_call_result = $hls->getKatanaAccountInfoWithQRCodeNumber($sva, $pin);
            $result['track2'] = $qrcode_call_result['track2'];
            $this->loyalty_number = $loyalty_number;
            $this->remote_account_info = $result;
            return $result;
        } else {
            if ($result['status.name'] == 'AccountNotFound') {
                $this->service_response['error'] = $this->getBadLoyaltyNumberMessage();
            } else {
                $this->service_response['error'] = $result['status.description'];
            }
            $this->service_response['error_code'] = $result['http_code'];

        }
        return false;
    }

    function validateLoyaltyNumber($loyalty_number)
    {
        if ($info = $this->getRemoteAccountInfo($loyalty_number)) {
            return true;
        } else {
            return false;
        }
    }

    function setLoyaltyData($data)
    {
        $this->data = $data;
        if ($loyalty_number = $data['loyalty_number']) {
            $this->loyalty_number = $loyalty_number;
            $this->pin = $data['pin'];
        }
    }
    function getAccountInfoForUserSession()
    {
        //return false;
        if ($user_brand_points_map_resource = $this->getLocalAccountInfoAsResource()) {
            $loyalty_number = $user_brand_points_map_resource->loyalty_number;
        } else {
            return null;
        }
        $creds = $this->getSvaPinAndQRcodeFromSubmittedLoyaltyNumber($loyalty_number);
        try {
            if ($info = $this->service->getBalance($creds['sva'], $creds['pin'])) {
                $info['loyalty_number'] = $creds['sva'];
                $info['pin'] = $creds['pin'];
                $info['barcode_number'] = $creds['qrcode_number'];
                $info['qrcode_number'] = $creds['qrcode_number'];
                $info['loyalty_transactions'] = array();
                $info['points_current'] = $info['Points'];
                $info['points'] = $info['Points'];
                $info['loyalty_points'] = $info['Points'];
                $info['usd'] = $info['USD'];

                // record the value in  the user_brand_points table
                $user_brand_points_map_resource->points = $info['points'];
                $user_brand_points_map_resource->dollar_balance = $info['usd'];
                $user_brand_points_map_resource->save();
                return $info;
            }
        } catch (NoHeartandPinException $nhpe) {
            myerror_log("user with old ".__CLASS__." loyalty setup. Delete account and message user");
            $user_brand_points_map_resource->_adapter->delete("".$user_brand_points_map_resource->map_id);
            $info['user_message'] = 'Please Note: We have fully integrated our loyalty functionality now, BUT....you will need to link your account again with both loyalty number AND pin. Sorry for the inconvenience.';
            return $info;
        }
    }

    /**
     * @desc will get the local brand points resource
     * @return Resource
     */
    function getLocalAccountInfoAsResource()
    {
        if ($user_brand_points_map_resource = parent::getLocalAccountInfoAsResource()) {
            return $this->setLoyaltyInstanceVariables($user_brand_points_map_resource);
        } else {
            return null;
        }
    }

    function setLoyaltyInstanceVariables($user_brand_points_map_resource)
    {
        $loyalty_number = $user_brand_points_map_resource->loyalty_number;
        $ln = explode(":", $loyalty_number);
        $this->loyalty_number = $ln[0];
        $this->pin = $ln[1];
        $this->qrcode_number = $ln[2];
        return $user_brand_points_map_resource;
    }

    function getLoyaltyHistory()
    {
// CHANGE THIS   --   remove prod logic when launched
        if (!isProd()) {
            $hls = $this->service;
            $history_response = $hls->getHistory($this->loyalty_number, $this->pin, $email);
            if ($history = $history_response['history']) {
                return $history;
            }
        }
        return array();
    }

    function getPin()
    {
        return $this->pin;
    }

    function getBadLoyaltyNumberMessage()
    {
        return "We're sorry, the card number you entered, ".$this->getLoyaltyNumber().":".$this->getPin().", appears to be invalid, please check your entry.";
    }
}
?>