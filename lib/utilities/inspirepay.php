<?php

class InspirePay
{
	//private $data = array('username'=>'splickitapiuser','password'=>'Spl2ck2ty');
	private $url = 'https://secure.inspiregateway.net/api/transact.php';
	private $ch;
	private $return_data = array();
	private $mimetypes = array(
		'html' => 'text/html',
		'xml' => 'text/xml'	
		);
		
	private $splickit_username = 'splickitapiuser';
	private $splickit_password = 'Spl2ck2ty';
	private $brand_id;
	
	function getDummyReturnData($order_id,$amount,$cc_number = "4111111111111111")
	{
		if ($cc_number == "void")
		{
			$data['response']=1;
			$data['responsetext']="Transaction Void Successful";
			$data['authcode']=123456;
			$data['transactionid']=2022472613;
			$data['orderid']=277488;
			$data['type']="void";
			$data['response_code']=100;
		}
		else if ($cc_number == "refund")
		{
			$data['response']=1;
			$data['responsetext']="Transaction Refund Successful";
			$data['authcode']=123456;
			$data['transactionid']=2022472613;
			$data['orderid']=277488;
			$data['type']="refund";
			$data['response_code']=100;
			
		}
		else if ($amount < 0)
		{
			$data['response']=2;
			$data['responsetext']='Sale transactions must use a positive amount';
			$data['authcode']=null;
			$data['transactionid']=time();
			$data['avsresponse']=N;
			$data['orderid']=$order_id;
			$data['response_code']=300;
		}
		else if ($cc_number == '4111111111111444')
		{
			$data['response']=2;
			$data['responsetext']='This card is bad bad bad';
			$data['authcode']=null;
			$data['transactionid']=time();
			$data['avsresponse']=N;
			$data['orderid']=$order_id;
			$data['response_code']=300;
		}
		else if ($_SERVER['HTTP_NO_CC_CALL_WITH_FAIL'])
		{
			$data['response']=2;
			$data['responsetext']='Issuer Declined';
			$data['authcode']=null;
			$data['transactionid']=time();
			$data['avsresponse']=N;
			$data['orderid']=$order_id;
			$data['response_code']=300;
		} else {
			$data['response']=1;
			$data['responsetext']='SUCCESS';
			$data['authcode']=123456;
			$data['transactionid']=1809583970;
			$data['avsresponse']=N;
			$data['orderid']=$order_id;
			$data['response_code']=100;
		}
		return $data;		
	}
	
	function getDummySaveResponse()
	{
		$cvv = $this->data['cvv'];
		if (strlen($cvv) != 3) {
			$return_fields['response_code'] = 300;
			$return_fields['result'] = 'failure';
			$return_fields['message'] = 'CVV must be 3 or 4 digits';
		} else {
			$return_fields['response_code'] = 100;
			$return_fields['result'] = 'success';
		}
		return $return_fields;
	}
	
	function getDummyVaultDataResponse($user_id)
	{
		$response = '<?xml version="1.0" encoding="UTF-8"?>
<nm_response><customer_vault><customer id="'.$user_id.'"><first_name>first</first_name><last_name>last</last_name><cc_number>4xxxxxxxxxxx1111</cc_number><cc_hash>f6c609e195d9d4c185dcc8ca662f0180</cc_hash><cc_exp>0615</cc_exp><cc_bin>411111</cc_bin><customer_vault_id>'.$user_id.'</customer_vault_id></customer></customer_vault></nm_response>';
		return $response;
	}
	
	function getDummyVaultDataResponseCC($user_id)
	{
		$response = '<?xml version="1.0" encoding="UTF-8"?>
<nm_response><customer_vault><customer id="'.$user_id.'"><cc_number>4111111111111111</cc_number><cc_hash>f6c609e195d9d4c185dcc8ca662f0180</cc_hash><cc_exp>0615</cc_exp><cc_bin>411111</cc_bin><customer_vault_id>'.$user_id.'</customer_vault_id></customer></customer_vault></nm_response>';
		return $response;
	}

	function InspirePay($brand_id = 100)
	{
	/*	myerror_log("******starting INSPIRE PAY request*********");
		foreach ($_REQUEST as $name=>$value)
			myerror_log(''.$name.':'.$value);
		myerror_log("******starting server*********");
		foreach ($_SERVER as $name=>$value)
			myerror_log(''.$name.':'.$value);
		myerror_log("******starting post*********");
		foreach ($_POST as $name=>$value)
			myerror_log(''.$name.':'.$value);
		myerror_log("******starting get*********");
		foreach ($_GET as $name=>$value)
			myerror_log(''.$name.':'.$value);
		myerror_log("***************ENDING INSPIRE PAY *****************");
		
	*/	
		myerror_logging(2,"*****************");
		myerror_log("submitted brand id to inspirepay object is: ".$brand_id);
		$this->brand_id = $brand_id;
		
		if (isProd())
		{
			// get the login 
			myerror_logging(1,"about to get the new inspire pay credentials for brand_id: ".$brand_id);
			$brand_adapter = new BrandAdapter($this->mimetypes);
			$brand_resource = Resource::find($brand_adapter,''.$brand_id);
			myerror_logging(1,"the brand resource retrieved is: ".$brand_resource->brand_id);
			$this->data = array('username'=>$brand_resource->cc_processor_username,'password'=>$brand_resource->cc_processor_password);
			myerror_logging(1,"using account inspirepay info for: ".$this->data['username']);	
			
			// get default username and password for pulling data out of vault for submission to other CC providers
			$splickit_brand_resource = Resource::find($brand_adapter,'100');
			$this->splickit_username = $splickit_brand_resource->cc_processor_username;
			$this->splickit_password = $splickit_brand_resource->cc_processor_password;			
		} else {
			$this->data = array('username'=>'username','password'=>'password');
		}
		myerror_logging(1,"the inspire pay username has been set as: ".$this->data['username']);
		myerror_logging(1,"*****************");

	}

	function void($transaction_id)
	{
		$this->data['transaction_id'] = $transaction_id;
		$this->data['type'] = 'void';
		if ((!isProd()) && $_SERVER['HTTP_NO_CC_CALL'] == 'true')
			$this->return_data = $this->getDummyReturnData("1234567890", $amount,'void');
		else 
			$this->process();
	}
	
	function refund($transaction_id,$amt = '0.00')
	{
		$this->data['transaction_id'] = $transaction_id;
		$this->data['type'] = 'refund';
		if ($amt != '0.00')
			$this->data['amount'] = $amt;
		if ((!isProd()) && $_SERVER['HTTP_NO_CC_CALL'] == 'true')
			$this->return_data = $this->getDummyReturnData("1234567890", $amount,'refund');
		else 
			$this->process();
	}
	
	/**
	 * 
	 * @desc To run card data not pulling from the Inspire Pay vault. To get results, call InspirePay->getReturnData (stupid)
	 *  
	 * @param array $card_data  ('cc_number','cc_exp','zip')
	 * @param float $amount
	 * @param int $order_id
	 */
	
	function runCardNoVault($card_data,$amount,$order_id)
	{
		$this->data['type'] = 'Sale';
		$this->data['amount'] = $amount;
		$this->data['ccnumber'] = $card_data['cc_number'];
		$this->data['ccexp'] = $card_data['cc_exp'];
		$this->data['zip'] = $card_data['zip'];
		$this->data['orderid'] = $order_id;
		if ((!isProd()) && $_SERVER['HTTP_NO_CC_CALL'] == 'true')
		{
			$this->return_data = $this->getDummyReturnData($order_id,$amount,$card_data['cc_number']);
		}	
		else
		{
			$this->process();
		}
	}

	/**
	 * 
	 * @desc To run a card from the Inspire Pay Vault.  To get results, call InspirePay->getReturnData (stupid)
	 * 
	 * @param $user_id
	 * @param $balance
	 * @param $order_id
	 */
	function runCard($user_id,$balance,$order_id)
	{
		$this->data['amount'] = $balance;
		$this->data['customer_vault_id'] = $user_id;
		$this->data['orderid'] = $order_id;
		if (isLaptop() && $_SERVER['HTTP_NO_CC_CALL'] == 'true')
			$this->return_data = $this->getDummyReturnData($order_id,$balance);
		else
			$this->process();
		
	}
	
	function getCustomerVaultRecordReally($user_id)
	{
		$this->getCustomerVaultRecord($user_id);
		$data = $this->getReturnData();
		if (sizeof($data, $mode) > 0)
			return $data;
		else 
			return false;
	}
	
	function getCustomerVaultRecord($user_id)
	{
		if ((!isProd()) && $_SERVER['HTTP_NO_CC_CALL'] == 'true')
		{
			$response = $this->getDummyVaultDataResponse($user_id);
			$this->setReturnDataXML($response);
			return true;
		}
		$this->url = "https://secure.inspiregateway.net/api/query.php";
		$this->data['report_type']='customer_vault';
		$this->data['customer_vault_id']=$user_id;	
		$ch = curl_init($this->url);
		if ($this->data['username'] == 'splickittest') 		
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		else 
		{
			// set username and password to defualt
			myerror_logging(1,"switching to default username for light vault retrieval: ".$this->splickit_username);
			myerror_logging(1,"switching to default password for light vault retrieval: ".substr($this->splickit_password,0,4)."xxxx");
			$this->data['username'] = $this->splickit_username;
			$this->data['password'] = $this->splickit_password;
		}
		/*
		myerror_log("*************************");
		foreach ($this->data as $name=>$value)
			myerror_log("$name=$value");
		myerror_log("*************************");
		*/
		curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST,'TLSv1');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); //dont display to screen
		$response = curl_exec($ch);
		myerror_log("the light inspire response: $response");
		$this->setReturnDataXML($response);
	}
	
	function getCustomerVaultRecordCCReally($user_id)
	{
		if ((!isProd()) && $_SERVER['HTTP_NO_CC_CALL'] == 'true')
		{
			$response = $this->getDummyVaultDataResponseCC($user_id);
			$this->setReturnDataXML($response);
			return true;
		}
		$this->getCustomerVaultRecordCC($user_id);
		return $this->getReturnData();
	}
	
	function getCustomerVaultRecordCC($user_id)
	{
		$this->url = "https://secure.inspiregateway.net/api/fetch_full.php";
		$this->data['key']='4ahuHeswuphedexeDRanejutAd9ye4uv';
		$ch = curl_init($this->url);
		if ($this->data['username'] == 'splickittest') 		
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		else if (isProd())
		{
			// set username and password to defualt
			myerror_logging(1,"switching to default username for FULL vault retrieval: ".$this->splickit_username);
			myerror_logging(1,"switching to default password for FULL vault retrieval: ".substr($this->splickit_password,0,4)."xxxx");
			$this->data['username'] = $this->splickit_username;
			$this->data['password'] = $this->splickit_password;
		}
		curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST,'TLSv1');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); //dont display to screen
		$response = curl_exec($ch);
		//myerror_log("response: $response");
		$this->setReturnData($response);
	}
	
	function setReturnDataXML($response)
	{
		$xml = new SimpleXMLElement($response);
		foreach ($xml->customer_vault[0]->customer[0] as $name=>$value)
		{
			if ($value != NULL && trim($value) != '')
				$this->return_data[$name]=(string)$value;
		}		
	}

	function updateCustomerVaultRecord($user_id,$cc_no,$cvv,$exp_date,$zip)
	{
		$this->data['customer_vault']='update_customer';
		$this->save($user_id,$cc_no,$cvv,$exp_date,$zip);

		// this is stupid haveing to parse the string. but all the error codes are 300 for a variety of errors so not sure what else to do.
		if (substr_count($this->return_data['responsetext'],'Invalid Customer Vault Id') > 0)
		{
			myerror_log("ERROR!  VAULT ID DOES NOT EXIST AT INSPIRE PAY!");
			$this->data['customer_vault']='add_customer';
			$this->process();
		}
	}
	
	function createCustomerVaultRecord($user_id,$cc_no,$cvv,$exp_date,$zip,$first_name,$last_name)
	{
		$this->data['customer_vault']='add_customer';
		$this->data['firstname'] = $first_name;
		$this->data['lastname'] = $last_name;
		$this->save($user_id,$cc_no,$cvv,$exp_date,$zip);
		
		// this is stupid haveing to parse the string. but all the error codes are 300 for a variety of errors so not sure what else to do.
		if (substr_count($this->return_data['responsetext'],'Duplicate Customer Vault Id') > 0)
		{
			$this->data['customer_vault']='update_customer';
			$this->process();
		}
	}
	
	private function save($user_id,$cc_no,$cvv,$exp_date,$zip)
	{
		$this->data['ccnumber']=$cc_no;
		$this->data['ccexp']="$exp_date";
		$this->data['cvv']="$cvv";
		$this->data['customer_vault_id']=$user_id;
		$this->data['zip']=$zip;
		$this->saveWrapperProcess();
	}
	
	private function saveWrapperProcess()
	{
		if ((!isProd()) && $_SERVER['HTTP_NO_CC_CALL'] == 'true')
			$this->return_data = $this->getDummySaveResponse();
		else 
			$this->process();
	}
	
	private function process()
	{
		
		$ch = curl_init($this->url);
		myerror_logging(1,"about to curl with: ".$this->url);
		foreach ($this->data as $key=>$value)
		{
			if ($key == 'ccnumber')
				myerror_logging(2,"$key : ".substr($value, 0,1)."xxxxxxxxxxx".substr($value,-4));
			else if ($key == 'cvv')
				myerror_logging(2,"$key : xxx   length: ".strlen($value));
			else if ($key != 'username' &&  $key != 'password')
				myerror_logging(2,$key." : ".$value);
		}

		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->data);
		if (isLaptop())
		{
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			//if (getProperty("log_level") > 3)
			//	curl_setopt($ch, CURLOPT_VERBOSE, true);
		}		
		else
		{
			curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST,'TLSv1');
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
			//if (getProperty("log_level") > 3)
			//	curl_setopt($ch, CURLOPT_VERBOSE, true);
		
		}	
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); //dont display to screen
		myerror_logging(3,"About to curl to inspire pay to run cc");
		$response = curl_exec($ch);
		myerror_logging(3,"Returned from IP call on CC");
		curl_close($ch);
		$this->setReturnData($response);
		if ($this->return_data['messsagetext'] == 'Authentication Failed')
			MailIt::sendErrorEmail("INSPIRE PAY ERROR!", "Brand inspire pay credentials have not been set up correctly for brand_id: ".$this->brand_id);
	}
	
	private function setReturnData($response)
	{
		myerror_log("*****  getting inspire pay return values *****");
		$return_fields = Array();
		$return = explode('&',$response);
		
		foreach ($return as $field)
		{
			//myerror_log($field);
			$row = explode('=',$field);
			if (sizeof($row) > 1)
				$return_fields[$row[0]] = $row[1];
			else
				$return_fields[$row[0]] = '';
			if ($row[0] == 'cc_number')
				myerror_logging(1,'cc_number='.substr($row[1],0,1).'xxxxxxxxxxx'.substr($row[1], 12));
			else if ($row[1])
				myerror_logging(1,$field);				
		}
		if (getProperty('log_level') == 0)
			myerror_log("response text from inspire pay is: ".$return_fields['responsetext']);
			
		myerror_log("***********************************************");
		if ($return_fields['response_code'] == 100)
			$return_fields['result'] = 'success';
		$this->return_data = $return_fields;			
	}
	
	function getReturnData() {
		return $this->return_data;
	}
	
}