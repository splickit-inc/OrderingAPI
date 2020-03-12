<?php

class CateringController extends SplickitController
{

    private $catering_order_ucid;
    var $merchant_catering_info;

    const MINIMUM_TIP_NOT_MET_ERROR = "We're sorry, this merchant requires a mimimum tip of %%minimum_tip%% for this order.";
    const CATERING_NOT_ACTIVE_FOR_THIS_MERCHANT = "We're sorry, this merchant does not appear to have catering active at the moment.";

    function __construct($mt, $u, $r, $l = 3)
    {
        parent::SplickitController($mt, $u, $r, $l);
        $this->adapter = new CateringOrdersAdapter();
        if (preg_match('%/catering/([0-9]{4}-[0-9a-z]{5}-[0-9a-z]{5}-[0-9a-z]{5})%', $r->url, $matches)) {
          $this->catering_order_ucid = $matches[1];
        }
     
    }

    function processV2request()
    {
        if (isRequestMethodADelete($this->request)) {
            if ($this->catering_order_ucid) {
                return $this->cancelCateringOrder($this->catering_order_ucid);
            }
        } else if (isRequestMethodAPost($this->request)) {
            if ($this->catering_order_ucid) {
                // not sure what goes in here. maybe update? like time or the like?
            } else {
              if ($this->isGuest()) {
                  myerror_log("we have a guest user creating a catering order");
                  return $this->createCateringOrder($this->request->data);
                  //return createErrorResourceWithHttpCode("We're sorry, guest user is not allowed for this action.", 403, $error_code);
              }else {
                // new catering order
                return $this->createCateringOrder($this->request->data);
              }
            }
        }
    }

    function isGuest()
    {
      return doesFlagPositionNEqualX($this->user['flags'],9,'2');
    }

    function createCateringOrder($data)
    {
          if (isset($data['user_addr_id']) && $data['user_addr_id'] > 1000) {
            $mdi_adapter = new MerchantDeliveryInfoAdapter(getM());
            $mdi_adapter->setCatering();
            $this->merchant_catering_info = MerchantCateringInfosAdapter::getInfoAsResourceByMerchantId($data['merchant_id']);
           // $mdi_resource = $mdi_adapter->getFullMerchantDeliveryInfoAsResource($data['merchant_id']);
            if ($this->merchant_catering_info->delivery_active == 'N') {
              myerror_log("ERROR!  Merchant showing catering delivery NOT active but catering order for delivery submitted");
              MailIt::sendErrorEmail('Impossible error', 'Merchant showing delivery NOT active but catering order for delivery submitted.  merchant_id: ' . $data['merchant_id']);
              return createErrorResourceWithHttpCode("We're sorry, this merchant has not set up their catering delivery information yet so a delivery order cannot be submitted at this time.", 500, 500);
            } else if ($this->merchant_catering_info->delivery_active == 'Y') {
                $mdi_adapter->setCateringDeliveryActive();
            }
            if ($delivery_price_resource = $mdi_adapter->getMerchantDeliveryPriceResourceForUserLocationAndMerchantId($data['user_addr_id'], $data['merchant_id'])) {
              myerror_log("delivery address is valid");
              $delivery_price = $delivery_price_resource->price;
              myerror_logging(3, "we have aquired the delivery price of: " . $delivery_price);
              $delivery_tax_amount = $mdi_adapter->getDeliveryTaxAmount($data['merchant_id'], $delivery_price);
            } else {
              return createErrorResourceWithHttpCode("Sorry, that delivery address is not in range.", 422, 422);
            }
          }
          $data['user_id'] = getLoggedInUserId();
          $cart_resource = CartsAdapter::createCart($data);
          if ($cart_resource->hasError()) {
            return $cart_resource;
          }
          $data['order_id'] = $cart_resource->order_id;
          if ($cart_resource->order_type == OrderAdapter::DELIVERY_ORDER) {
            $data['order_type'] = 'delivery';
            $cart_resource->delivery_amt = $delivery_price;
            $cart_resource->delivery_tax_amt = $delivery_tax_amount;
            $cart_resource->save();
          } else {
            $data['order_type'] = 'pickup';
          }
          $merchant_resource = Resource::find(new MerchantAdapter(), $data['merchant_id']);
          $data['contact_info'] = $data['contact_name'] . ' ' . $data['contact_phone'];
          $data['date_tm_of_event'] = getMySqlFormattedDateTimeFromTimeStampAndTimeZone($data['timestamp_of_event'], getTheTimeZoneStringFromOffset($merchant_resource->time_zone, $merchant_resource->state));
          $catering_order_resource = Resource::createByData($this->adapter, $data);
          if ($catering_order_resource->hasError()) {
            return $catering_order_resource;
          }
          $catering_order_resource->set('ucid', $cart_resource->ucid);
          return $catering_order_resource;
        }

    function cancelCateringOrder($ucid)
    {
        $carts_adapter = new CartsAdapter();
        $cart_resource = Resource::find($carts_adapter,"$ucid");
        $cart_resource->status = OrderAdapter::ORDER_CANCELLED;
        if ($cart_resource->save()) {
            $catering_order_resource = Resource::find($this->adapter,null,array(3=>array("order_id"=>$cart_resource->order_id)));
            $catering_order_resource->status = 'cancelled';
            if ($catering_order_resource->save()) {
                return Resource::dummyfactory(array("success"=>true));
            }
        }
        return createErrorResourceWithHttpCode("The catering order could not be cancelled",500,500);
    }


}