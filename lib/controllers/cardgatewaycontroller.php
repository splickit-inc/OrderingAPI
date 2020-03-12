<?php

class CardGatewayController extends SplickitController
{
	var $cc_processor_list;

	CONST MERCHANT_ACCOUNT_SUCCESSFULLY_CREATED_MESSAGE = "Your account was successfully created and can now be used immediately.";
	CONST MERCHANT_ACCOUNT_NOT_CREATED_ERROR_MESSAGE = "We're sorry, there was a problem creating your account and it was not successful. Please try again.";
	CONST MERCHANT_ACCOUNT_CREATED_BUT_ERROR_MESSAGE = "Your account was created, but there was a problem and it is not ready to take orders yet. Customer service will contact you shortly.";
		
	function __construct($mt,$u,$r,$l = 3)
	{
		parent::SplickitController($mt,$u,$r,$l);
		//$this->payment_services_list = PaymentServicesAdapter::staticGetPaymentSevicesHashWithNameAsKey();
		$this->cc_processor_list = VioCreditCardProcessorsAdapter::staticGetCCPaymentProcessorsHashWithNameAsKey();		
	}

	function processV2Request()
	{
		return $this->processRequest();
	}

	function processRequest()
	{
		if ($this->hasRequestForDestination('merchant_account')) {
			if ($this->isThisRequestMethodAPost()) {
				// create Merchant_account
				try {
					if ($resource = $this->createNewMerchantAccountAndBillingEntity($this->data)) {
						return $resource;
					} else {
						return Resource::dummyfactory(['success'=>'false']);
					}
				} catch (Exception $e) {
					$message = $e->getMessage();
					return createErrorResourceWithHttpCode($message,422);
				}
			}
		}
	}


	function createPaymentGatewayFromRequest($request)
	{
		return $this->createPaymentGateway($request->data);	
	}

	function createPaymentGateway($data)
	{
		$type = strtolower($data['vio_selected_server']);
		if ($this->cc_processor_list["$type"]) {
			$cc_processor_record = $this->cc_processor_list["$type"];
		} else {
			throw new NoSuchProcessorException($type);
		}
		$brand_id = $data['brand_id'];
		$name = $data['vio_selected_server'].'='.$data['vio_merchant_id'];
		$description = $data['description'];
		$merchant_id = $data['vio_merchant_id'];
		$identifier = (isset($data['identifier'])) ? $data['identifier'] : generateCode(10);
		$process_type = isset($data['process_type']) ? $data['process_type'] : 'purchase';
		
		if(!isset($data['vio_selected_server']) || strlen($data['vio_selected_server']) == 0) {
			return returnErrorResource("A payment processor must be selected.",999);
		}

		if(!isset($data['brand_id']) || $data['brand_id'] <  100) {
			return returnErrorResource("A valid brand id must be provided.");
		}
				
		if ($processor = ProcessorFactory::getProcessor($data['vio_selected_server'])) {
			$payload = $processor->getVIOPayload($data);
			$vio_payment_service = new VioPaymentService();
			$raw_result_info = $vio_payment_service->createDestination($type,$identifier,$payload);
			if ($raw_result_info['status'] == 'success') {
				$billing_entity_resource = BillingEntitiesAdapter::createBillingEntity($cc_processor_record['id'], $name, $description, $brand_id, $identifier, $payload,$process_type);
				if ($data['do_not_create_mapping']) {
					myerror_log("Skipping creationg of Merchant_Payment_Type_Maps due to error on account creation");
				} else {
					$merchant_payment_type_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_id, $cc_processor_record['splickit_accepted_payment_type_id'], $billing_entity_resource->id);
					$billing_entity_resource->set("merchant_payment_type_map",$merchant_payment_type_map_resource);
				}
				return $billing_entity_resource;
			} else {
				return returnErrorResource($raw_result_info['error']);
			}
		} else {
			return returnErrorResource("Sorry, $type processing is not setup yet");
		}
	}


	/**
	 * @desc will create a new mechant_account through VIO
	 */

	function createNewMerchantAccountAndBillingEntityFromRequest($request)
	{
		return $this->createNewMerchantAccountAndBillingEntity($request->data);
	}

	/**
	 * @param $account_data
	 * @return Resource
	 * @throws NoSuchProcessorException
	 */
	function createNewMerchantAccountAndBillingEntity($account_data)
	{
		$vio_service = new VioService();
		if (isset($account_data['merchant_id']) && $account_data['merchant_id'] > 1000) {
			$merchant_id = $account_data['merchant_id'];
		} else {
			return createErrorResourceWithHttpCode("not a valid merchant_id: ".$account_data['merchant_id'],422,422);
		}
		//$sql = "SELECT * FROM payment_merchant_applications WHERE merchant_id = $merchant_id";
		$pmaa = new PaymentMerchantApplicationsAdapter(getM());
		$pmaa_options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
		if ($pma_resource = Resource::find($pmaa,null,$pmaa_options)) {
			if ($pma_resource->vio_account_number > 0) {
				myerror_log("ERROR!!!! Attempted account set up for existing account: ".$pma_resource->vio_account_number);
				throw new Exception("Merchant account has already been set up. Account number: ".$pma_resource->vio_account_number);
			} else {
				myerror_log("All is good with application record, proceed with account set up for merchant_id: $merchant_id");
			}
		} else {
			myerror_log("There was no existing row in the payment_merchant_application table, throw error");
			throw new Exception("No existing payment merchant application row.");
		}
		if ($merchant_resource = Resource::find(new MerchantAdapter(getM()),$merchant_id)) {
			$account_data["apartment_number"] = "";
			$account_data["address1"] = $merchant_resource->address1;
			$account_data["address2"] = $merchant_resource->address2;;
			$account_data["city"] = $merchant_resource->city;
			$account_data["state"] = $merchant_resource->state;
			$account_data["country"] = "USA";
			$account_data["zip"] = $merchant_resource->zip;

			$account_data["bank_account_bank_name"] = $account_data["business_legal_name"];
			$account_data["currency"] = "USD";  // this should be fixed
			$account_data["monthly_bank_card_volume"] = 0;
			$account_data["average_ticket"] = 0;
			$account_data["highest_ticket"] = 0;
			$account_data["bank_account_country_code"] = "USA";

			if (isNotProd()) {
				$account_data["test"] = "true";
			}
			$account_data["tier"] = "Splickit_2";
		} else {
			return createErrorResourceWithHttpCode("No merchant matching that id: ".$account_data['merchant_id'],422,422);
		}

		$raw_result_info = $vio_service->createMerchantAccount($account_data);
		$status = $raw_result_info['processor_status'];
		if ( $status == '00' || $raw_result_info['account_number'] > 0) {
			$account_number = $raw_result_info['account_number'];
			if ($status == '00') {
				myerror_log("We have a successfull merchant account creation for merchant_id: $merchant_id");
			}
			$data = [];
			$data['vio_selected_server'] = 'Vio-Instant';
			$data['vio_merchant_id'] = $merchant_id;
			$data['description'] = $merchant_resource->name;
			$data['account_num'] = $raw_result_info['account_number'];
			$data['identifier'] = $merchant_resource->alphanumeric_id.'-instant';
			$data['brand_id'] = $merchant_resource->brand_id;
			$data['process_type'] = 'authorize';

			// delete existing 2000 record
			if ($status == '00') {
				$sql = "DELETE FROM Merchant_Payment_Type_Maps WHERE merchant_id = $merchant_id AND splickit_accepted_payment_type_id = 2000";
				$merchant_payment_type_maps_adapter = new MerchantPaymentTypeMapsAdapter(getM());
				$merchant_payment_type_maps_adapter->_query($sql);
				$user_message = self::MERCHANT_ACCOUNT_SUCCESSFULLY_CREATED_MESSAGE;
			} else {
				// do not create merchant payment type maps
				myerror_log("ERROR !!!! We got a good account number but a bad status: $status");
				MailIt::sendErrorEmail("ERROR CREATING MERCHANT ACCOUNT","We got a good acount number: $account_number    but a bad status: $status. Billing entity was created, Vio-Instant=$merchant_id, but NOT assigned to merchant: $merchant_id");
				MailIt::sendErrorEmailToIndividual("mark@inspirecommerce.com","ERROR CREATING MERCHANT ACCOUNT FROM SPLICKIT","We got a good acount number: $account_number   but a bad status: $status");
				$user_message = self::MERCHANT_ACCOUNT_CREATED_BUT_ERROR_MESSAGE;
				$data['do_not_create_mapping'] = true;
			}
			$pma_resource->vio_result_status = "$status";
			$pma_resource->vio_account_number = "$account_number";
			$pma_resource->save();
			$resource = $this->createPaymentGateway($data);
			$resource->set('user_message',$user_message);
			return $resource;
		} else {
			$pma_resource->status = $status;
			$pma_resource->save();
			$error = $raw_result_info['error'];
			$vio_error_message = json_encode($error);
			$error_message = self::MERCHANT_ACCOUNT_NOT_CREATED_ERROR_MESSAGE;
			MailIt::sendErrorEmail("ERROR CREATING MERCHANT ACCOUNT",$vio_error_message);
			MailIt::sendErrorEmailToIndividual("mark@inspirecommerce.com","ERROR CREATING MERCHANT ACCOUNT FROM SPLICKIT","Status: $status  and NO account number created.  Data: ".json_encode($account_data));
			return createErrorResourceWithHttpCode($error_message,$raw_result_info['http_code']);
		}
	}

	/************   ADMIN FUNCTIONS   ************/

	/**
	 * @desc will return the admin landing page where support can create new payment entities
	 */
	function getVioPaymentPage($notes) {
		$processor_schema = $this->getAvailableProcessorsAndFields();
		$resource = Resource::dummyfactory($processor_schema);
		
		$resource->set("processor_schema", $processor_schema);
		$resource->set("notices", $notes);		
		$resource->_representation = '/admin/viopaymentsetup.html';
		return $resource;	
	}	
	
	/**
	 * @desc used to get data to populat the admin landing page to create payment gatways (billing entities)
	 */
	function getAvailableProcessorsAndFields()
	{
		$vio_credit_card_processor_adapter = new VioCreditCardProcessorsAdapter(getM());
		$payment_processor_records = $vio_credit_card_processor_adapter->getRecords(array("splickit_accepted_payment_type_id"=>2000));
		foreach($payment_processor_records as $record) {
			$lower_name = strtolower($record['name']);
			if ($payment_processor = ProcessorFactory::getProcessor($lower_name)) {
				$record['fields'] = $payment_processor->getFields();
				$record['display_name'] = ucfirst($lower_name);
				if ($lower_name == 'secure-net') {
					$record['display_name'] = 'Secure-Net';
				}
				$better_hash[$lower_name] = $record;
			}
		}
		return $better_hash;
	}
	
}

class NoSuchProcessorException extends Exception
{
	function __construct($type)
	{
		parent::__construct("There is no such CC processor ($type), registered in the db", 999);
	}
}

?>
