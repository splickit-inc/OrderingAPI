<?php
class HeartlandLoyaltyService extends SplickitLoyaltyService
{	
	var $archer_url;
	var $archer_data; 
	var $domain;
	var $terminal;
	var $terminal_order;
	var $terminal_version;
	
	var $katana_url;
	var $api_key;
	var $user_password;

	function __construct($api_key,$domain,$terminal,$terminal_order,$terminal_version)
	{
		$code = generateCode(4);
		$stamp = getRawStamp().'-'.$code;
		$this->archer_url = getProperty('heartland_archer_url');
		if ($domain == null || $domain == '') {
			throw new HeartlandDomainNotSetException();
		}
		if ($api_key == null || $api_key == '') {
			throw new HeartlandAPIKeyNotSetException();
		}

		$this->domain = $domain;
		$this->terminal = $terminal;
		$this->terminal_order = $terminal_order;
		$this->terminal_version = $terminal_version;
		$this->api_key = $api_key;
		$this->archer_data = array('domain'=>$this->domain,'terminal'=>$this->terminal,'terminal.order'=>$this->terminal_order,'terminal.version'=>$this->terminal_version);
		$this->katana_url = str_replace('%%domain%%',$domain,getProperty('heartland_katana_url'));
		$this->archer_data['request'] = "$stamp";
		$this->archer_data['store'] = 'splickit';
	}
	
	function setUserPassword($user_password)
	{
		$this->user_password = $user_password;
	}
	
	function setStore($heartland_store_id)
	{
		$this->archer_data['store'] = $heartland_store_id;
	}
	
	function createPitCardUser($email,$password)
	{
		$response = $this->createUserKatana($email, $password);
		return $response;
	}
	
	function createUserKatana($email,$password)
	{
		$url = $this->katana_url.'admin/users';
		$data['email'] = $email;
		$data['password'] = $password;
		$this->setData($data);
		$this->setMethod('POST');
		$this->setUserPassword(getProperty("heartland_archer_create_account_user_password"));
		$response = $this->send($url);
		return $response;
	}
	
	function createAccountKatana($email,$password)
	{
		$url = $this->katana_url.'user/accounts';
		$data['chainId'] = 'global';
		$this->setData($data);
		$this->setUserPassword($email.':'.$password);
		$this->setMethod('post');
		$response = $this->send($url);
		return $response;
	}
	
	function getHistory($sva,$pin,$email)
	{
		$url = $this->katana_url.'account/history';
		$this->setUserPassword($sva.':'.$pin);
		$this->setMethod('GET');
		$response = $this->send($url);
		$better_history_array = array();
		// got to clean up the history here since it return everything including balance inquiries
		if (count($response['history'] > 0)) {
			$better_history_array = $this->cleanHistory($response['history']);
		}
		$response['history'] = $better_history_array;
		return $response;
	}

    function cleanHistory($history_array)
    {
        $better_history_array = array();
        foreach ($history_array as $history_record) {
            if (strtolower($history_record['type']) == 'balance inquiry') {
                // dont show our inquiries
                continue;
            }
            if ($history_record['transactionAmount'] == null) {
                // empty record from heartland
                continue;
            }
            $d = explode('T', $history_record['date']);
            $new_record['transaction_date'] = $d[0];
            $new_record['description'] = $history_record['type'];
            $new_record['amount'] = $history_record['transactionAmount']['formatted'].' '.$history_record['transactionAmount']['currency'];

            $amount = $history_record['transactionAmount']['formatted'];
            $amount_type = $history_record['transactionAmount']['currency'];

            if($amount >= 0) {
                $new_record['description'] = "Earned $amount $amount_type.";
            } else if($amount < 0) {
                $new_record['description'] = "Spent ".(-1 * $amount)." $amount $amount_type.";
            }

            $better_history_array[] = $new_record;
        }
        return $better_history_array;
    }

	function resetPinKatana($email,$password,$sva,$pin)
	{
		$url = $this->katana_url.'user/accounts/'.$sva.'/pin';
		$data['pin'] = $pin;
		$this->setData($data);
		$this->setUserPassword($email.':'.$password);
		$this->setMethod('PUT');
		$response = $this->send($url);
		return $response;
	}

    function addDollars($sva,$pin,$dollars)
    {
        $amount_in_pennies = $dollars*100;
        return $this->archerLoad($sva,$pin,$amount_in_pennies,'USD');
    }
			
	function addPoints($sva,$pin,$points)
	{
        return $this->archerLoad($sva,$pin,$points,'Points');
	}

    function archerLoad($sva,$pin,$amount,$currency)
    {
        $url = $this->archer_url."load";
        $this->archer_data['sva'] = $sva;
        if ($pin) {
            $this->archer_data['pin'] = $pin;
        }
        $this->archer_data['acquired'] = 'MANUAL';
        $this->archer_data['amount'] = $amount;
        $this->archer_data['currency'] = $currency;
        $this->setData($this->archer_data);
        $this->setUserPassword(getProperty("heartland_archer_user_password"));
        $this->setMethod('post');
        return $this->send($url, $this->data);
    }
	
	function rewardPoints($sva,$pin,$amount_with_decimal_point)
	{
		$amount = $this->convertAmountToAmountInPennies($amount_with_decimal_point);
		$url = $this->archer_url."reward";
		$this->archer_data['sva'] = $sva;
		if ($pin) {
			$this->archer_data['pin'] = $pin;
		}
		$this->archer_data['acquired'] = 'MANUAL';
		$this->archer_data['amount'] = $amount;
		$this->archer_data['currency'] = "USD";
		$this->setData($this->archer_data);
		$this->setUserPassword(getProperty("heartland_archer_user_password"));
		$this->setMethod('post');
		return $this->send($url, $this->data);
	}
	
	function getBalance($sva,$pin)
	{
		if ($pin == null && PitapitLoyaltyController::useNewPitaPitLoyalty()) {
			throw new NoHeartandPinException();
		}
		$url = $this->archer_url."inquiry";
		$this->archer_data['sva'] = $sva;
		$this->archer_data['pin'] = $pin;
		$this->archer_data['acquired'] = 'MANUAL';
		$this->setData($this->archer_data);
		$this->setUserPassword(getProperty("heartland_archer_user_password"));
		$return = $this->send($url);
		$this->setReadableBalances($return);
		return $return;
	}
	
	function getKatanaAccountInfoWithQRCodeNumber($sva,$pin)
	{
		$url = $this->katana_url.'account';
		$this->setUserPassword($sva.':'.$pin);
		$this->setMethod('GET');
		unset($this->data);
		$response = $this->send($url);
		return $response;		
	}
	
	function setReadableBalances(&$results) 
	{
		if ($results['status'] == 'success') {
			$results['Points'] = null;
			$results['USD'] = null;
			$balance_array = explode(",", $results['sva.balances']);
			foreach ($balance_array as $name_value)
			{
				$name_value_array = explode(" ", $name_value);
				if ($name_value_array[0] == 'Points') {
					$results['Points'] = $name_value_array[1];
				} else if ($name_value_array[0] == "USD") {
					$value = number_format(($name_value_array[1]/100),2);
					$results['USD'] = $value;
				}
			}
		}
		return $results;
	}
	
	function redeemPoints($sva,$pin,$points)
	{
		$url = $this->archer_url."redeem";
		$this->archer_data['sva'] = $sva;
		$this->archer_data['amount'] = "$points";
		$this->archer_data['currency'] = "Points";		
		if ($pin) {
			$this->archer_data['pin'] = $pin;
		}
		$this->archer_data['acquired'] = 'MANUAL';
		// uncomment this line if we want to fail redemptions that the balance is not enough. 
		// near impossible since we get balance at user session call. but we may want to invoke it in the future
		$this->archer_data['partial'] = false;
		$this->setData($this->archer_data);
		$this->setUserPassword(getProperty("heartland_archer_user_password"));
		return $this->send($url, $this->data);
	}

/********  stored value stuff **********/	
	
	/**
	 * 
	 * @desc to add stored value from a CC.  $exp_date must be in the form of mm/yy
	 * @param unknown_type $sva
	 * @param unknown_type $pin
	 * @param unknown_type $amount
	 * @param unknown_type $cc_number
	 * @param unknown_type $exp_date
	 * @param unknown_type $cvv
	 * @param unknown_type $full_name
	 */
	function addStoredValueFromCC($sva,$pin,$amount,$cc_number,$exp_date,$cvv,$full_name,$email,$password)
	{
		$amount_in_pennies = $this->convertAmountToAmountInPennies($amount);
		$good_email = urlencode($email);
		$url = $this->katana_url."users/".urlencode($email)."/accounts/$sva/load";
		myerror_logging(3, "load card url: ".$url);
		$data['loadAmount'] = array("amount"=>$amount_in_pennies,"currency"=>'USD');
		$data['number'] = $cc_number;
		$data['expiration'] = $exp_date;
		$data['cvv2'] = $cvv;
		$data['billing'] = array("cardholder"=>$full_name);
		$json_string = json_encode($data);
		myerror_logging(5, $json_string);
		$this->setData($data);
		$this->setMethod('post');
		$this->setUserPassword("$email:$password");
		$response = $this->send($url);
		return $response;
//{"loadAmount":{"amount":4001,"currency":"USD"},"number":"4111111111111111","expiration":"0418","cvv2":"123","billing":{"cardholder":"First Last"}}		
	}
	
	function storeCC($cc_info)
	{
		return false;
	}
	
	function setAutoLoad($info)
	{
		return false;
	}
	
	function convertAmountToAmountInPennies($amount)
	{
		$amount_in_money_form = number_format($amount,2);
		$amount_in_pennies = number_format((100*$amount_in_money_form),0,".","");
		return $amount_in_pennies;
	}
	
	function chargeAgainstStoredValue($sva,$pin,$amount)
	{
		$amount_in_pennies = $this->convertAmountToAmountInPennies($amount);
		$url = $this->archer_url."redeem";
		$this->archer_data['sva'] = $sva;
		$this->archer_data['amount'] = "$amount_in_pennies";
		$this->archer_data['currency'] = "USD";		
		if ($pin) {
			$this->archer_data['pin'] = $pin;
		}
		$this->archer_data['acquired'] = 'MANUAL';
		$this->archer_data['partial'] = false;
		$this->setData($this->archer_data);
		$this->setUserPassword(getProperty("heartland_archer_user_password"));
		return $this->send($url, $this->data);
	}

/********  end stored value stuff **********/
	
	function send($url)
	{
		myerror_log("about to curl to heartland with $url");
		logData($this->data, "heartland curl",5);
		$response = HeartlandCurl::curl($url, $this->method, $this->data, $this->user_password, $this->api_key);
		$this->curl_response = $response;
		$result_array = $this->processCurlResponse($response);
		return $result_array;
	}
	
	function processCurlResponse($response)
	{
		$result_array = parent::processCurlResponse($response);
		if ($result_array['status'] == 'failure') {
			$result_array['error'] = isset($result_array['status.name']) ? $result_array['status.name'] : $result_array['description'];
		}
		return $result_array;
	}
}

class NoHeartandPinException extends Exception
{
  public function __construct() {
      parent::__construct("No pin for getting remote loyalty information");
  }
}

class HeartlandDomainNotSetException extends Exception
{
	public function __construct() {
		parent::__construct("Domain has not been set for Heartland Loyalty service in context: ".getSkinNameForContext());
	}
}

class HeartlandAPIKeyNotSetException extends Exception
{
	public function __construct() {
		parent::__construct("APIKey has not been set for Heartland Loyalty service in context: ".getSkinNameForContext());
	}
}

?>
