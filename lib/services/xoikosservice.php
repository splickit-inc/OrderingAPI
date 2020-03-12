<?php
class XoikosService extends SplickitService
{

    var $method = 'Post';
    //var $url = 'http://www.subshop.com/onlineordering/order.asmx';
    //var $url = 'http://orders.subshop.com/order.asmx';
    var $url;
    var $merchant;

    function __construct($data)
    {
        parent::__construct($data);
        $this->setMethod('post');
        if ($merchant = $data['merchant']) {
            $this->merchant = $merchant;
        }
        $this->url = getProperty('xoikos_service_url');
    }

    function testStoreIsInactive()
    {
        return ! $this->testStoreIsActive();
    }

    function testStoreIsActive()
    {
        myerror_log("About to test for Xoikos store active state",3);
        if (isTest() || isDevelopment()) {
            return true;
        }
        if ($merchant_external_id = $this->merchant['merchant_external_id']) {
            // first prevent calling twice on the same request
            if (isset($_SERVER[getRawStamp()]['xoikos_store_active_state'][$this->merchant['merchant_external_id']])) {
                return $_SERVER[getRawStamp()]['xoikos_store_active_state'][$this->merchant['merchant_external_id']];
            }
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
                <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                   <soap:Body>
                      <isStoreActive xmlns="http://www.subshop.com/">
                         <store>'.$this->merchant['merchant_external_id'].'</store>
                      </isStoreActive>
                   </soap:Body>
                </soap:Envelope>';
            $xml = cleanUpDoubleSpacesCRLFTFromString($xml);
            $soap_action = "http://www.subshop.com/isStoreActive";
            $headers[] = 'SOAPAction: "' . $soap_action . '"';
            $response = XoikosCurl::curlIt($this->url,$xml,$headers);
            $is_active = $this->isSuccessfulActiveResponse($response);
            $_SERVER[getRawStamp()]['xoikos_store_active_state'][$this->merchant['merchant_external_id']] = $is_active;
            return $is_active;
        } else {
            myerror_log("ERROR!!!!!  cant check xoikos store because merchant external id is null");
            throw new NoMerchantExternalIdLoadedException();
        }
    }

    function isSuccessfulActiveResponse($response)
    {
        return (substr_count($response['raw_result'],'<isStoreActiveResult>true</isStoreActiveResult>') > 0);
    }

    function setMethod($method)
    {
        $this->method = $method;
    }

    function send($xml)
    {
        $xml = htmlspecialchars(cleanUpDoubleSpacesCRLFTFromString($xml));
        $xml = '<?xml version="1.0" encoding="UTF-8"?><soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><placeOrderEx xmlns="http://www.subshop.com/"><store>'.$this->merchant['merchant_external_id'].'</store><order>'.$xml.'</order></placeOrderEx></soap:Body></soap:Envelope>';
        $soap_action = "http://www.subshop.com/placeOrderEx";
        $headers[] = 'SOAPAction: "'.$soap_action.'"';
        $response = $this->processCurlResponse(XoikosCurl::curlIt($this->url,$xml,$headers));
        if ($this->isSuccessfulResponse($response)) {
            return $response;
        }
        throw new UnsuccessfulXoikosPushException("We had a failure pushing the order into Xoikos. ".$response['placeOrderExResult']['errorMessage'], 500);
    }

    function processCurlResponse($response)
    {
        $this->curl_response = $response;
        $raw_return_as_array = array();
        if ($raw_return = $this->getRawResponse($response)) {
            $raw_return_as_array = $this->processRawReturn($raw_return);
        }
        return array_merge($response,$raw_return_as_array);
    }

    function processRawReturn($raw_return)
    {
        return getSOAPCleanSectionFromEnvelopeBodyAsHashMap($raw_return,'placeOrderExResponse');
    }

    function isSuccessfulResponse($response)
    {
        return $response['placeOrderExResult']['success'] == "true";
    }
}
class UnsuccessfulXoikosPushException extends Exception
{
    public function __construct($error_message, $xoikos_error_code, $code = 100) {
        parent::__construct("Xoikos message failure: '$error_message'. Error Code: $xoikos_error_code", $code);
    }
}

class NoMerchantExternalIdLoadedException extends Exception
{
    public function __construct() {
        parent::__construct("Xoikos merchant failure, no external id loaded for active check", 500);
    }
}
?>
