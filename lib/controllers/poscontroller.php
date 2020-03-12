<?php

Class PosController extends SplickitController
{
    var $id;
    var $admin = false;
    var $soap_action;
    var $allow_get = false;

    function PosController($mt, $u, &$r, $l = 0)
    {
        parent::SplickitController($mt, $u, $r, $l);
        if (isset($u['user_id']) && $u['user_id'] < 100) {
            myerror_log("we have an admin user");
            $this->setAdmin();
        }
    }

    function isAMethodViolation()
    {
        if (!$this->admin) {
            if (strtolower($this->request->method) == 'get' && $this->doNotAllowGet()) {
                if ($this->hasRequestForDestination('loyalty')) {
                    return false;
                } else {
                    return true;
                }
            }
        }
    }

    function processV2request()
    {
        if ($this->isAMethodViolation()) {
            return createErrorResourceWithHttpCode("Internal Error. Method Not Allowed", 500, 999);
        }
        if (isSoapRequest()) {
            myerror_log("POS LOGGING: we have a soap request");
            $this->reformatSoapRequest($this->request);
        }
        if (substr_count($this->request->url, '/import/') > 0 ) {
            if ($importer = ImporterFactory::getImporterFromUrl($this->request->url)) {
                if(is_a($importer,'ElPolloLocoImporter')) {
                    $this->setAdmin();
                }
                if ($importer->getStageImportForAllMerchantsAttachedToBrand()) {
                    $importer->stageImportsForEntireBrandList();
                    return Resource::dummyfactory(array("message"=>"The imports have been staged. there were ".$importer->number_of_staged_merchants." merchants."));
                } else if ($this->admin) {
                    $importer->importRemoteMerchantMetaDataForLoadedMerchant();
                    $message = $importer->getMessage();
                    return Resource::dummyfactory(array("message"=>"The import has been completed. $message"));
                } else if ($activity_id = $importer->stageImport()) {
                    $this->id = $activity_id;
                    return Resource::dummyfactory(array("message"=>"The import has been staged","activity_id"=>$activity_id));
                }
            }
            return createErrorResourceWithHttpCode("Error staging import",500,999);
        } else if (substr_count($this->request->url, '/merchants/') > 0 ) {
            $merchant_controller = new MerchantController($m,$this->user,$this->request,$this->log_level);
            $resource = $merchant_controller->processPosRequest();
        } else if ($this->hasRequestForDestination('loyalty')) {
            if ($loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext()) {
                $loyalty_controller->setRequest($this->request);
                $resource = $loyalty_controller->processRemoteRequest();
                return $resource;
            } else {
                return createErrorResourceWithHttpCode("There is no loyalty enabled for this brand",500,500);
            }

        } else if (substr_count($this->request->url, '/orders/') > 0 ) {
            myerror_log("starting orders section of PosController");
            $order_controller = new OrderController($mt, $this->user, $this->request, $this->log_level);
            $resource = $order_controller->processRequest();
            if (isset($resource->group_order_id)) {
                $resource->set('ucid',$resource->group_order_token);
                $resource->set("group","Group ");
            } else {
                $resource->set("group","");
            }
            logData($resource->getDataFieldsReally(),'order resource');
        } else {
            return createErrorResourceWithHttpCode('Endpoint not recognized',500,999);
        }
        if (isSoapRequest()) {
            $resource->set("send_soap_response",true);
            $soap_response_for_context = $this->getSoapResponseForContext($resource);
            $resource->set("soap_body",$soap_response_for_context);
            $resource->http_code = $this->getHttpStatusForContext($resource->http_code);
        }
        return $resource;

    }

    function getHttpStatusForContext($http_code)
    {
        myerror_log("We are in the change http_code for the context. current http_code is: $http_code");
        myerror_log("Setting http status code for a context of: ".getIdentifierNameFromContext());
        if (getIdentifierNameFromContext() == 'goodcentssubs') {
            // always resopnd with a 200 for xoikos soap
            $http_code = 200;
        }
        myerror_log("http_code is now: $http_code");
        return $http_code;
    }

    function getId()
    {
        return $this->id;
    }

    function setAdmin()
    {
        $this->admin = true;
    }

    function getSoapResponseForContext($resource)
    {
        // will need to put context logic in here but since we only have xoikos right now it will default to their format
        $response_body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body>';
        if ($this->soap_action == 'ApplyTipToOrder') {
            if (isset($resource->error)) {
                $response_body = $response_body.'<ApplyTipToOrderResponse xmlns="http://www.xoikos.com/webservices/"><ApplyTipToOrderResult><Success>false</Success><ErrorMessage>'.$resource->error.'</ErrorMessage></ApplyTipToOrderResult></ApplyTipToOrderResponse></soap:Body></soap:Envelope>';
            } else {
                $response_body = $response_body.'<ApplyTipToOrderResponse xmlns="http://www.xoikos.com/webservices/"><ApplyTipToOrderResult><Success>true</Success><ErrorMessage>A '.$resource->tip_amt.' tip has been applied on Order with ID '.$resource->ucid.'</ErrorMessage></ApplyTipToOrderResult></ApplyTipToOrderResponse></soap:Body></soap:Envelope>';
            }
        } else if ($this->soap_action == 'CancelOrder') {
            if (isset($resource->error)) {
                $response_body = $response_body.'<CancelOrderResponse xmlns="http://www.xoikos.com/webservices/"><CancelOrderResult><Success>false</Success><ErrorMessage>'.$resource->error.'</ErrorMessage></CancelOrderResult></CancelOrderResponse></soap:Body></soap:Envelope>';
            } else {
                $response_body = $response_body.'<CancelOrderResponse xmlns="http://www.xoikos.com/webservices/"><CancelOrderResult><Success>true</Success><ErrorMessage>'.$resource->group.'Order with ID '.$resource->ucid.' has been canceled.</ErrorMessage></CancelOrderResult></CancelOrderResponse></soap:Body></soap:Envelope>';
            }
        } else if ($this->soap_action == 'UpdateLeadTime') {
            if (isset($resource->error)) {
                $response_body = $response_body.'<UpdateLeadTimeResponse xmlns="http://www.xoikos.com/webservices/"><UpdateLeadTimeResult><Success>false</Success><ErrorMessage>'.$resource->error.'</ErrorMessage></UpdateLeadTimeResult></UpdateLeadTimeResponse></soap:Body></soap:Envelope>';
            } else {
                $response_body = $response_body.'<UpdateLeadTimeResponse xmlns="http://www.xoikos.com/webservices/"><UpdateLeadTimeResult><Success>true</Success><ErrorMessage>Store number '.$resource->merchant_external_id.' had its lead times updated to Pickup: '.$resource->lead_time.' minutes and Delivery: '.$resource->minimum_delivery_time.' minutes.</ErrorMessage></UpdateLeadTimeResult></UpdateLeadTimeResponse></soap:Body></soap:Envelope>';
            }
        } else if ($this->soap_action == 'GetLeadTimes') {
            if (isset($resource->error)) {
                $response_body = $response_body.'<GetLeadTimesResponse xmlns="http://www.xoikos.com/webservices/"><GetLeadTimesResult><Success>false</Success><ErrorMessage>'.$resource->error.'</ErrorMessage></GetLeadTimesResult></GetLeadTimesResponse></soap:Body></soap:Envelope>';
            } else {
                $response_body = $response_body.'<GetLeadTimesResponse xmlns="http://www.xoikos.com/webservices/"><GetLeadTimesResult><Success>true</Success><PickupLeadTime>'.$resource->lead_time.'</PickupLeadTime><DeliveryLeadTime>'.$resource->minimum_delivery_time.'</DeliveryLeadTime><ErrorMessage/></GetLeadTimesResult></GetLeadTimesResponse></soap:Body></soap:Envelope>';
            }
        }
        return $response_body;
    }

    function reformatSoapRequest(&$request)
    {
        $request->parseSoapRequest();
        $soap_action_long = $_SERVER['SOAPAction'];
        myerror_logging(3,"POS LOGGING: the soap $soap_action_long");
        if (substr_count($soap_action_long,'ApplyTipToOrder') > 0) {
            myerror_logging(3, "POS LOGGING: IN THE ApplyTipToOrder SOAP ACTION BLOCK");
            $this->soap_action = 'ApplyTipToOrder';
            $order_id = $request->data['ApplyTipToOrder']['OrderID'];
            $request->url = "/app2/apiv2/pos/orders/$order_id";
            $request->data['tip_amt'] = $request->data['ApplyTipToOrder']['Amount'];
        } else if (substr_count($soap_action_long,'CancelOrder') > 0) {
            myerror_log("POS LOGGING: IN THE CancelOrder SOAP ACTION BLOCK");
            $this->soap_action = 'CancelOrder';
            $order_id = $request->data['CancelOrder']['OrderID'];
            $request->url = "/app2/apiv2/pos/orders/$order_id";
            $request->data['status'] = "C";
        } else if (substr_count($soap_action_long,'UpdateLeadTime') > 0) {
            myerror_logging(3, "POS LOGGING: IN THE UpdateLeadTime SOAP ACTION BLOCK");
            $this->soap_action = 'UpdateLeadTime';
            $external_store_id = $request->data['UpdateLeadTime']['storeNumber'];
            $request->url = "/app2/apiv2/pos/merchants/$external_store_id";
            $request->data['lead_time'] = $request->data['UpdateLeadTime']['pickupLeadTime'];
            $request->data['minimum_delivery_time'] = $request->data['UpdateLeadTime']['deliveryLeadTime'];
        } else if (substr_count($soap_action_long,'GetLeadTimes') > 0) {
            myerror_logging(3, "POS LOGGING: IN THE GetLeadTimes SOAP ACTION BLOCK");
            $this->soap_action = 'GetLeadTimes';
            $this->allow_get == true;
            $external_store_id = $request->data['GetLeadTimes']['storeNumber'];
            $request->url = "/app2/apiv2/pos/merchants/$external_store_id";
        } else {
            myerror_log("POS LOGGING: NO SOAP, RADIO!! soap action NOT recoginized");
        }
    }

    function getSoapAction()
    {
        return $this->soap_action;
    }

    function allowGet()
    {
        return $this->allow_get == true;
    }

    function doNotAllowGet()
    {
        return $this->allow_get == false;
    }

}

