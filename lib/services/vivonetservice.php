<?php

class VivonetService extends SplickitService
{
	var $service_name = "vivonet";

	private $version = "v1";
	private $base_service_url;
	private $alternate_url;
	private $X_API_KEY;

	const CONFIGURATION  = "configuration";
	const STORES = "stores";
	const LOAD_PRICES = "import_prices";
	const PRICES = "products";

	const ORDERING  = "ordering";
	const TENDERS = "tenders";
	const PRODUCTS = "products";
	const DISCOUNTS = "discounts";
	const ORDER_RECEIPT = "order_receipt";
	const GET_ORDER_FROM_POS = "get_order_from_pos";
	const CLOSES_ORDER_ON_POS = "closes_order_on_pos";
	const PLACE_ORDER = "place_order";
	const SEND_ORDER_RECEIPT = "send_order_receipt_via_email";
	const ORDER_DATA_CHARGES = "order_data_charges";
	const ADD_ITEMS_TO_ORDER = "add_items_to_order_on_pos";

	const MENU  = "menu";

	private $current_end_point;
	private $headers;
	private $tender_ids;
	public $store_id;
	public $merchant_of_order;

	// 436 hollywood bowls
	// 88888 default for testing
	public $tender_name_by_brand_id = [88888 => 'APIAccount', 436 => 'API Tender'];


	private $endpoints  =  array(
		self::CONFIGURATION => array(
			self::STORES =>  "/{version}/apiKeys/stores"
		),
		self::ORDERING => array(
			self::TENDERS => "/{version}/stores/{storeId}/tenders",
            self::PRODUCTS => "/{version}/stores/{storeId}/products",
			self::ORDER_RECEIPT => "/{version}/stores/{storeId}/orders/receipt/{correlationId}",
			self::PLACE_ORDER => "/{version}/stores/{storeId}/orders",
			self::ORDER_DATA_CHARGES=> "/{version}/stores/{storeId}/orders/data",
			self::DISCOUNTS=>"/{version}/stores/{storeId}/discounts"
		),
		self::LOAD_PRICES => array(
			self::PRICES => "/{version}/stores/{storeId}/products"
		)
	);

	private $vivonet_available_stores;

	function __construct($data)
	{
		parent::__construct($data);
		$this->version = getProperty('vivonet_api_version');
		$this->base_service_url = getProperty('vivonet_service_url');
		$this->X_API_KEY = getProperty('vivonet_x_api_key');
		logData($data,"vivonet service constructor data");
		if ($data['store_id']) {
			$this->setStoreId($data['store_id']);
			$this->X_API_KEY = $data['merchant_key'];
            if ($alternate_url = $data['alternate_url']) {
                $this->base_service_url = $alternate_url;
            }
		} else if ($data['merchant_id']) {
			$merchant_record = MerchantAdapter::staticGetRecordByPrimaryKey($data['merchant_id'],'MerchantAdapter');
			$this->setMerchantOfOrder($merchant_record);
			$mvima = new MerchantVivonetInfoMapsAdapter(getM());
			if ($store_id = $mvima->getStoreId($merchant_record['merchant_id'])) {
                $this->setStoreId($store_id);
                $this->X_API_KEY = $mvima->getMerchantKey($merchant_record['merchant_id']);
                if ($alternate_url = $mvima->getAlternateUrl($merchant_record['merchant_id'])) {
                    $this->base_service_url = $alternate_url;
				}

			} else {
				throw new InvalidVivonetMerchantSetupException("No store id associated with this merchant_id: ".$merchant_record['merchant_id'].", in the Merchant_Vivonet_Info_Maps table.");
			}

		}
	}
	
	function setStoreId($store_id)
	{
		$this->store_id = $store_id;
	}

	function send($data){

		$response = $this->processCurlResponse(
			VivonetCurl::curlIt($this->current_end_point, $data, $this->headers)
		);
		if ($this->isSuccessfulResponse($response)) {
			return $response;
		}
		throw new UnsuccessfulVivonetPushException($response['data']['message'], $response['error_no']);
	}

	function pullPricesForLoadedMerchant($data)
	{
		$this->configureForPriceImport($data);
		$response = $this->send($data);
		return $response['data'];
	}

	function processCurlResponse($response)
	{
		$raw_return_as_array = array();
		if ($raw_return = $this->getRawResponse($response)) {
			$raw_return_as_array = $this->processRawReturn($raw_return);
		}
		$response['data'] = $raw_return_as_array;
		return $response;
	}
	
	function configureForPriceImport($data)
	{
		$this->configure(self::LOAD_PRICES,self::PRICES);
	}

	function configure($section = self::CONFIGURATION, $operation = self::STORES, $headers = array(), $params = array()){

		$this->headers = array();

		$this->headers[] = "x-api-key:".$this->X_API_KEY;

		foreach($headers as $header_key=>$header_value){
			$this->headers[] = $header_key . ":" . $header_value;
		}

		$endpoint = $this->endpoints[$section][$operation];
		if(!is_null($endpoint)){
			$url = $this->base_service_url . str_replace("{version}", $this->version, $endpoint);

			$url = isset($this->store_id)
				? str_replace("{storeId}", $this->store_id, $url)
				: $url;

			foreach($params as $key => $value){
				$url = str_replace($key, $value, $url);
			}

			$this->current_end_point = $url;
			myerror_log("Config $this->service_name service: url => " . $this->current_end_point . " with headers => ". json_encode($this->headers));

		}else{
			myerror_log("Error on config vivonet service: Not found Operation");
			throw new InvalidVivonetConfigurationException("Not found Operation", 400);
		}
	}

	public function getCurrentConfiguration(){
		return array(
			'headers' => $this->headers,
			'url' => $this->current_end_point
		);
	}

    function getProductIdsForStore()
    {
        $this->configure(self::ORDERING,self::PRODUCTS);
        if ($result = $this->send()) {
        	$ids = [];
            foreach ($result['data'] as $product) {
            	$ids[$product['productId']] = 1;
			}
			return $ids;
        }
    }

	function getTenderIdForStore()
	{
		if ($this->tender_ids = $this->getTenderIdsForStore()) {
			if ($brand_id = $this->merchant_of_order['brand_id']) {
                $tender_name = $this->tender_name_by_brand_id[$brand_id];
			} else {
                $tender_name = $this->tender_name_by_brand_id[getBrandIdFromCurrentContext()];
			}
			if ($tender_name == null) {
				myerror_log("Using default tender name on this vivonet order. Hopeuflly this is only happening in the test system");
				$tender_name = $this->tender_name_by_brand_id[88888];
            }
			foreach ($this->tender_ids as $tender_record) {
				if ($tender_record['tenderName'] == $tender_name) {
					return $tender_record['tenderId'];
				}
			}
			myerror_log("ERROR!!!!  COULDN'T GET TENDER ID FROM VIVONET for tender name: $tender_name");
			return 0;
		}

	}

	function getServiceTipIdForStore()
	{
		if ($this->discount_ids = $this->getDiscountIdsForStore()) {
			foreach ($this->discount_ids as $discount_record) {
				if ($discount_record['discountName'] == 'Gratuity') {
					return $discount_record['discountId'];
				} else if ($discount_record['discountName'] == 'Open Gratuity') {
                    return $discount_record['discountId'];
                }
			}

		}
	}

	function getTenderIdsForStore()
	{
		$this->configure(self::ORDERING,self::TENDERS);
		if ($result = $this->send()) {
			return $result['data'];
		}
	}

	function getDiscountIdsForStore()
	{
		$this->configure(self::ORDERING,self::DISCOUNTS);
		if ($result = $this->send()) {
			return $result['data'];
		}
	}

	function getErrorFromCurlResponse()
	{
		return $this->curl_response['error'];
	}

	function setMerchantOfOrder($merchant)
	{
		$this->merchant_of_order = $merchant;
	}
}

class UnsuccessfulVivonetPushException extends Exception
{
	public function __construct($error_message, $vivonet_error_code, $code = 100) {
		parent::__construct("Vivonet message failure: '$error_message'. Vivonet Error Code: $vivonet_error_code", $code);
	}
}

class InvalidVivonetConfigurationException extends Exception
{
    public function __construct($error_message, $code = 100) {
        parent::__construct("Vivonet message failure: '$error_message'. Vivonet Error Code: $code", $code);
    }
}

class InvalidVivonetMerchantSetupException extends Exception
{
    public function __construct($error_message) {
        parent::__construct("Vivonet Set Up Error: $error_message");
    }
}
?>
