<?php
class BrinkService extends SplickitService
{

    var $method;
    var $location_token;
    var $merchant_id = 0;
    var $brand_id = 0;
    var $order_id = 0;
    private $url;

    function __construct($location_token,$method='SubmitOrder')
    {
        parent::__construct($data);
        if ($location_token == null || trim($location_token) == '') {
            myerror_log("ERROR!!!! trying to create a BrinkService with no location token!");
            throw new NoMerchantBrinkLocationException();
        }
        $this->location_token = $location_token;
        $this->setMethod($method);
    }

    function setMethod($method)
    {
        $this->method = $method;
    }

    function setOrderId($order_id)
    {
        $this->order_id = $order_id;
    }

    function setMerchantId($merchant_id)
    {
        $this->merchant_id = $merchant_id;
    }

    function getUrl()
    {
        return $this->url;
    }

    function setBrandId($brand_id)
    {
        $this->brand_id = $brand_id;
    }

    function sendSoapRequest($soap_action,$soapxml)
    {
        $url = $this->getBrandUrl(getProperty("brink_api_url"));
        $access_token = getProperty("brink_access_token");
        $headers[] = "AccessToken: $access_token";
        $headers[] = "LocationToken: ".$this->location_token;
        $headers[] = "SOAPAction: $soap_action";
        if ($this->method == 'CalculateOrder') {
            $soapxml = str_replace('SubmitOrder',$this->method,$soapxml);
        }
        $response = BrinkCurl::curlIt($url, cleanUpDoubleSpacesCRLFTFromString($soapxml),$headers);
        $this->curl_response = $response;
        return $response;
    }

    function getBrandUrl($url)
    {
        myerror_log("Getting url based on brand_id: ".$this->brand_id,3);
        if ($this->brand_id == 282) {
            // pita pit
            $url = str_replace('api.brinkpos.net','api4.brinkpos.net',$url);
        } else if ($this->brand_id == 150) {
            // snarfs
            $url = str_replace('api.brinkpos.net','api8.brinkpos.net',$url);
        }
        myerror_log("Brink url now set as: ".$url);
        $this->url = $url;
        return $url;
    }

    function send($xml)
    {
        $soap_action = "http://www.brinksoftware.com/webservices/ordering/20140219/IOrderingWebService/".$this->method;
        $response = $this->processCurlResponse($this->sendSoapRequest($soap_action, $xml));
        if ($this->isSuccessfulResponse($response)) {
            return $response;
        }
        $error_message = isset($response['message']) ? $response['message'] : $response['error'];
        throw new UnsuccessfulBrinkPushException($error_message.".   order_id: ".$this->order_id."   merchant_id: ".$this->merchant_id, $response['ResultCode']);
    }

    function processCurlResponse($response)
    {
        $raw_return_as_array = array();
        if ($raw_return = $this->getRawResponse($response)) {
            $raw_return_as_array = $this->processRawReturn($raw_return);
        }
        return array_merge($response,$raw_return_as_array);
    }

    function processRawReturn($raw_return)
    {
        return getSOAPCleanSectionFromEnvelopeBodyAsHashMap($raw_return,$this->method.'Result');
    }

    function isSuccessfulResponse($response)
    {
        return $response['ResultCode'] == "0";
    }

}

class UnsuccessfulBrinkPushException extends Exception
{
    public function __construct($error_message, $brink_error_code, $code = 100) {
        parent::__construct("Brink message failure: '$error_message'. Brink Error Code: $brink_error_code", $code);
    }
}
?>