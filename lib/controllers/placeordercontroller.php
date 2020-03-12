<?php

class PlaceOrderController extends SplickitController
{

    public $full_order;
    public $delivery_service;
    private $card_data;
    private $minimum_tip_requirement_exists_for_this_order = false;
    private $payment_service_used;
    private $isPlaceOrder = false;
    protected $submitted_order_data;
    protected $internal_order = false;
    protected $new_order = array();
    protected $group_order_record;

    protected $billing_user;
    protected $billing_user_resource;
    protected $order_user_resource;
    protected $get_checkout_data;

    protected $converted_order_new;
    protected $forced_ts = 0; // for testing only

    protected $delivery_order_minimum;
    protected $is_delivery = false;
    protected $error_resource;
    protected $existing_cart_and_order_data;
    protected $minimum_lead_time_for_this_order;

    protected $group_override_record;

    protected $carts_adapter;

    /**
     * @var Order
     */
    protected $order;
    protected $merchant;
    protected $ucid;
    protected $merchant_catering_info;

    /**
     * @var LeadTime
     */
    protected $lead_time_object;

    /**
     * @var AWSService
     *
     */
    protected $aws_service;

    public $current_time;
    public $merchant_resource;

    /*
     * @var Array with info eg: taxes
     */
    public $merchant_info;



    private $payment_service;
    private $loyalty_controller;
    private $current_order_item_type_counts;

    const ORDER_SUMMARY_SIZE = 4;
    const BASE_NUMBER_ENTRE_LEVEL_ITEMS_FOR_NO_LEADTIME_LIMIT = 4;
    const ORDERING_OFFLINE_MESSAGE = "Sorry, this merchant is not currently accepting mobile/online orders. Please try again soon.";
    const STORE_DROPPED_OFFLINE_MESSAGE = "We're sorry, but this store seems to have just gone offline. Please try again shortly.";
    const INTERNAL_ERROR_LOG_BACK_IN_MESSAGE = "We're sorry, there was an internal error with your session. Please log back in and start over, sorry for the inconvenience :(";
    const REMOTE_SYSTEM_CHECKOUT_INFO_ERROR_MESSAGE = "We're sorry, there was an error connecting to the remote system and the request could not be completed. Please try again.";
    const MAX_NUMBER_OF_ENTRES_EXCEEDED_ERROR_MESSAGE = 'Sorry, this merchant has a maximum number of entres per order of %%max%%, please remove %%diff%% entres from your cart to place this order.';

    const ADDRESS_IS_OUTSIDE_DELIVERY_ZONE_ERROR_MESSAGE = "We're sorry, this delivery address appears to be outside of our delivery range.";

    var $cant_tip_because_cash_message = "We're sorry, you can't add a tip here if paying with cash. You'll need to tip in person when you pick up your food. Please set the tip value to 0.00, thanks!";



    function PlaceOrderController($mt, $u, $r, $l = 0)
    {
        parent::SplickitController($mt, $u, $r, $l);
        $this->adapter = new PlaceOrderAdapter($mt);
        $this->current_time = time();
        $this->aws_service = new AWSService();
        $this->order_user_resource = $this->getResourceFromId($u['user_id'], 'User');
        // set loyalty controller if it is exists for this skin
        $this->loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext($u);
        if (preg_match('%/([0-9]{4}-[0-9a-z]{5}-[0-9a-z]{5}-[0-9a-z]{5})%', $r->url, $matches)) {
            $ucid = $matches[1];
        } else if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($r->data, 'cart_ucid')) {
            $ucid = $r->data['cart_ucid'];
        }
        if ($ucid) {
            $this->setOrderAndMerchantByUcid($ucid);
            //test for user_id mismatch and just recreate a new order
            if ($this->testForUserIdMisMatch()) {
                myerror_log("WE HAVE A USER ID MISMATCH. Probably a back button violation so just recreate the order if its a data post");
                if ($this->isThisRequestMethodAPost() && $this->request->data['items']) {
                    $cart_resource = $this->createNewCart($this->request->data);
                    if ($cart_resource->hasError()) {
                        return $cart_resource;
                    }
                    $this->setOrderAndMerchantByUcid($cart_resource->ucid);
                }
            }
            if ($this->order->get('status') == OrderAdapter::GROUP_ORDER) {
                // this is a parent
                $this->setGroupOrderRecordByGroupOrderToken($ucid);
            } else {
                // test if its a child to a group order and if so, get and set group order record.
                $this->setGroupOrderRecordByUserOrderId($this->order->getOrderId());
            }
        } else {
            if ($group_order_token = $r->data['group_order_token']) {
                $this->setGroupOrderRecordByGroupOrderToken($group_order_token);
            } else if ($r->data['merchant_id'] > 0) {
                $this->setMerchantResourceAndInfoByMerchantId($r->data['merchant_id']);
            }
        }
    }

    function getCartsAdapter()
    {
        if ($this->carts_adapter) {
            return $this->carts_adapter;
        } else {
            $this->carts_adapter = new CartsAdapter(getM());
            return $this->carts_adapter;
        }
    }

    /*
     *  @desc returns true if we have a user id mismatch
     */
    function testForUserIdMisMatch()
    {
        return $this->order->getUserId() != $this->user['user_id'];
    }

    function isUserAGuest()
    {
        if (doesFlagPositionNEqualX($this->order_user_resource->flags, 9, '2')) {
            return true;
        }
        return false;
    }

    function setOrderAndMerchantByUcid($ucid)
    {
        $this->ucid = $ucid;
        $this->order = new Order($ucid);
        if ($this->merchant == null) {
            $this->merchant = $this->getMerchantResourceAsRecord($this->order->get('merchant_id'));
        }
        if ($this->order->isCateringOrder()) {
            $this->merchant_catering_info = getStaticRecord(array("merchant_id"=>$this->order->get('merchant_id')),'MerchantCateringInfosAdapter');
        }

    }

    function processV2Request()
    {
        if ($this->request == null) {
            throw new Exception("No valid request sent",422,422);
        }
        if (!$this->order) {
            if ($this->isThisRequestMethodAPost()) {
                $cart_resource = $this->createNewCart($this->request->data);
                if ($cart_resource->hasError()) {
                    return $cart_resource;
                }
                $this->setOrderAndMerchantByUcid($cart_resource->ucid);
            } else {
                return $this->returnUnableToFindCartErrorResource();
            }
        }
        if ($this->order) {
            $this->group_override_record = $this->getUserGroupOverrideValuesIfItAppliesForThisUserMerchantCombination($this->order->getUserId(), $this->order->get('merchant_id'));
            if ($error_resource = $this->doHighLevelValidations()) {
                return $error_resource;
            }
            if ($this->isThisRequestMethodAPost()) {
                if ($error_resource = $this->merchantOrderingActiveValidations()) {
                    return $error_resource;
                }
            }
            if ($this->hasRequestForDestination('cart') || $this->hasRequestForDestination('carts')) {
                if ($this->isThisRequestMethodAGet()) {
                    $resource =  $this->processCartGet();
                } else if ($this->isThisRequestMethodAPost()) {
                    $resource =  $this->processCartPost();
                } else if (preg_match('%/cartitem/([0-9]{4,10})%', $this->request->url, $matches)) {
                    $order_detail_id = $matches[1];
                    if (isRequestMethodADelete($this->request)) {
                        $resource = $this->deleteItemFromCart($this->ucid, $order_detail_id);
                    } else {
                        $resource = createErrorResourceWithHttpCode($this->request->method." method for cartitem does not exist", 404);
                    }
                }
            } else if ($this->hasRequestForDestination('orders')) {
                if (isRequestMethodAPost($this->request)) {
                    if (isUserATempUserByUserId($this->user['user_id'])) {
                        return createErrorResourceWithHttpCode("We're sorry there was an error. You do not appear to be logged in. Please try again.",404,404);
                    }
                    if ($this->isAddToTypeOneGroupOrder()) {
                        $resource = $this->addCartToTypeOneGroupOrder($this->ucid, $this->group_order_record);
                    } else {
                        if ($error_resource = $this->doOrdersEndpointValidations()) {
                            return $error_resource;
                        }
                         $resource = $this->newPlaceOrder($this->order, $this->request->data);
                        //change flags to guest
                        if($this->isUserAGuest()){
                            $user_adapter = new UserAdapter($m);
                            $this->order_user_resource->flags = $user_adapter->deleteUserCCVaultInfoAndGetNewFlagsFromUserResource($this->order_user_resource);
                            if(!$this->order_user_resource->save()){
                                myerror_log("we had an error remomving guest user CC info: ".$this->order_user_resource->_adapter->getLastErrorText());
                                recordError("User guest", "User guest CC flag cannot be removed");
                            }
                        }
                    }
                } else {
                    return createErrorResourceWithHttpCode("GET Method for Orders not built yet", 404);
                }
            } else {
                return createErrorResourceWithHttpCode("Unknown Endpoint In placeordercontroller", 404);
            }
            // backwards compatibility
            if (isset($resource->ucid)) {
                $resource->set('cart_ucid',$resource->ucid);
            }


            return $resource;
        } else {
            myerror_log("ERROR!!!! entering place order process v2 request main block with NO Order object");
            return createErrorResourceWithHttpCode("There was an internal error",500,500);
        }
        $response_resource = ($resource == null) ? createErrorResourceWithHttpCode("Endpoint does not exist", 404, $error_code) : $resource;
        return $response_resource;
    }

    function processCartPost()
    {
        if ($error_resource = $this->validateRequest()) {
            return $error_resource;
        }
        $this->processNonItemNonCheckoutMethodsRequestData($this->request->data);
        if ($items = $this->filterSubmittedCartItemsByStatusIfItExists($this->request->data['items'], $this->order->get('ucid'))) {
            if ($error_resource = $this->doSubmittedItemValidations($items)) {
                return $error_resource;
            }
            return $this->processCartPostItems($items);
        } else {
            if ($promo_code = $this->getPromoCodeFromExistingOrderOrSubmittedData()) {
                if ($promo_resource = $this->applyPromoToOrder($this->order, $promo_code)) {
                    if ($promo_resource->hasError()) {
                        return $promo_resource;
                    }
                }
            }
            if (isset($this->request->data['user_addr_id']) && $this->request->data['user_addr_id'] != $this->order->get('user_delivery_location_id')) {
                $cart_resource = $this->processDeliveryLocation($this->request->data['user_addr_id'],$this->order->getOrderResource());
                if ($cart_resource->hasError()) {
                    return $cart_resource;
                }
                $this->order = new OrderMaster($cart_resource->order_id);
            }
            if ($this->hasRequestForDestination('checkout')) {
                return $this->getCheckoutDataFromOrder($this->order);
            }
            return $this->getCart($this->ucid);
        }
    }

    function processCartGet()
    {
        if ($promo_code = $this->getPromoCodeFromExistingOrderOrSubmittedData()) {
            if ($promo_resource = $this->applyPromoToOrder($this->order, $promo_code)) {
                if ($promo_resource->hasError()) {
                    return $promo_resource;
                }
            }
        }
        if ($this->hasRequestForDestination('checkout')) {
            $resource = $this->getCheckoutDataFromOrder($this->order);
        } else {
            $resource = $this->getCart($this->ucid);
        }
        unset($resource->order_id);
        $this->applyPromoUserMessageToResource($resource, $promo_resource);
        return $resource;
    }

    function addItemsToCart($items,$order_data)
    {
        // use NEW carts adapter to prevent any chance of TempTable curruption since PHP7 the connection is kept alive.
        // this might be overkill but didn't want to risk it
        $carts_adapter = $this->getCartsAdapter();
        $carts_adapter->setTaxRates($this->merchant_info['taxes']);
        return $carts_adapter->addItemsToCart($items, $order_data);
    }

    function processCartPostItems($items)
    {
        if ($this->addItemsToCart($items, $this->order->getBaseOrderData())) {
            $this->updateOrderObject();
            //$resource = $this->getCart($this->order->get('ucid'));
            if ($promo_code = $this->getPromoCodeFromExistingOrderOrSubmittedData()) {
                // apply promo
                $promo_resource = $this->applyPromoToOrder($this->order, $promo_code);
                if ($promo_resource != null && $promo_resource->hasError()) {
                    return $promo_resource;
                }
            }
            if (isset($this->request->data['user_addr_id']) && $this->request->data['user_addr_id'] != $this->order->get('user_delivery_location_id')) {
                $cart_resource = $this->processDeliveryLocation($this->request->data['user_addr_id'],$this->order->getOrderResource());
                if ($cart_resource->hasError()) {
                    return $cart_resource;
                }
                $this->order = new OrderMaster($cart_resource->order_id);
            }
            $resource = $this->getCart($this->order->get('ucid'));
            if ($this->hasRequestForDestination('checkout')) {
                $this->order = new OrderMaster($resource->order_id);
                $resource = $this->getCheckoutDataFromOrder($this->order);
                if ($resource->hasError()) {
                    $resource->set('error_type',"CheckoutError");
                    $cart_resource = $this->getCart($this->order->get('ucid'));
                    $cart_resource->cleanResource();
                    $resource->set('data',$cart_resource);
                    return $resource;
                }
            }
            if ($promo_resource) {
                $this->applyPromoUserMessageToResource($resource, $promo_resource);
            }

            unset($resource->order_id);
            return $resource;
        } else {
            // need to get the error from the carts adapter
            return createErrorResourceWithHttpCode("Internal Error",500,500);
        }
    }

    function applyPromoUserMessageToResource(&$resource, $promo_resource)
    {
        if ($promo_resource) {
            $resource->user_message = $promo_resource->user_message;
            $resource->user_message_title = $promo_resource->user_message_title;
        }
    }

    function getPromoCodeFromExistingOrderOrSubmittedData()
    {
        if ($promo_code = $this->request->data['promo_code']) {
            return $promo_code;
        } else if ($promo_code = $this->order->get('promo_code')) {
            if ($promo_code != null && trim($promo_code) != '') {
                return $promo_code;
            }
        } else if ($this->group_override_record['promo_id'] > 0) {
            if ($promo_record = PromoAdapter::staticGetRecordByPrimaryKey($this->group_override_record['promo_id'],'PromoAdapter')){
                return $promo_record['promo_key_word'];
            }
        }
    }

    function updateOrderObject()
    {
        $this->order = new OrderMaster($this->order->getOrderId());
        $this->setConvenienceFeeForOrderUserMerchantCombination();

    }

    function setConvenienceFeeForOrderUserMerchantCombination()
    {
        $order_resource = $this->order->getOrderResource();
        $convenience_fee = $this->getLowestValidConvenienceFeeFromMerchantUserCombination($order_resource->order_amt, $this->merchant, $this->user, $this->group_override_record);
        $order_resource->trans_fee_amt = $convenience_fee;
        $order_resource->save();
    }

    function doHighLevelValidations()
    {
        if ($this->isThisRequestMethodAPost()) {
            // Do validations

                if ($this->isGroupOrderClosed()) {
                    return $this->error_resource;
                }

            if (isOrderingShutdown() && $this->user['user_id'] > 1000) {
                return createErrorResourceWithHttpCode("Sorry, the mobile ordering system is currently offline.  Please try again shortly.", 590, 590);
            }
            if ($this->order->getUserId() != $this->user['user_id']) {
                $order_resource = $this->order->getOrderResource();
                logData($order_resource->getDataFieldsReally(),"ORDER");
                MailIt::sendErrorEmail('USER ID MISMATCH ERROR!', 'authenticated user: ' . $this->user['user_id'] . ':' . $this->user['email'] . ' -- user on order: ' . $this->order->getUserId());
                return returnErrorResource("Sorry, your session has gotten corrupted. Please log out and start again. We apologize for the inconvenience.", 80);
            }
        }
        if ($this->isAddToTypeOneGroupOrder()) {
            if ($this->request->data['promo_code']) {
                return createErrorResourceWithHttpCode("Sorry, promos are not enabled on this type of group order.", 422, 422, array("error_type" => 'promo'));
            }
        }

        if (!MerchantMessageMapAdapter::doesMerchantHaveMessagesSetUp($this->merchant['merchant_id'])) {
            if (isLoggedInUserStoreTesterLevelOrBetter()) {
                return returnErrorResource("Admin User Alert: There are no message set up for this merchant, please address before placing orders");
            } else {
                $map_id = MailIt::sendErrorEmailSupport("Merchant Messages Not Set UP!", "We have a merchant with no messages set up and a regular user is trying to place an order.  merchant_id: ".$this->merchant['merchant_id']);
                return createErrorResourceWithHttpCode("We're sorry but there is a problem with this merchants set up. Support has now been alerted. Please try again soon.", 500, 800, array("error_message_map_id" => $map_id));
            }
        }
    }

    function doOrdersEndpointValidations()
    {
        if (isLoggedInUserATempUser()) {
            return returnErrorResource("We're sorry but you do not appear to be logged in. Please log in and try again. We apologize for the inconvenience.", 999, array("http_code" => 500));
        }
        return $this->validateRequest();

    }

    function validateRequest()
    {
        if ($this->isOrderInActiveState()) {
            return $this->merchantOrderingActiveValidations();
        } else {
            return $this->getErrorForStatus($this->order->get('status'));
        }
    }

    function getErrorForStatus($status)
    {
        if (OrderAdapter::isStatusASubmittedStatus($status)) {
            return createErrorResourceWithHttpCode("Sorry, this order has already been submitted. Check your email for order confirmation.", 422, 80);
        } else {
            return createErrorResourceWithHttpCode("Sorry, this cart is no longer active and cannot be submitted.", 422, 80);
        }
    }

    function isOrderInActiveState()
    {
        if ($this->order->isActiveStatus()) {
            return true;
        }
    }

    function merchantOrderingActiveValidations()
    {
        if ($this->isThisARegularUser($this->order->getUserId())) {
            if ($error_message = $this->checkForNonActiveOrOrderingOffMessageByMerchantObject($this->merchant)) {
                return createErrorResourceWithHttpCode($error_message, 500, 500);
            }

            // CHANGE THIS - interesting problem here. sometimes need to shut down a skin but what about merchants that exist in two skins, their branded skin and YumTicket or another agregator
            /* if ($this->merchant['brand_id'] == 112 && getProperty('moes_ordering_on') == 'false') {
                return returnErrorResource(getProperty('moes_ordering_off_message'), 999);
            } else */ if ($this->testForCCProcessorShutDown($this->merchant['cc_processor'])) {
                return returnErrorResource(getProperty('cc_processor_ordering_off_message'), 999);
            }
        }
    }

    function doSubmittedItemValidations(&$items)
    {
        // add to cart.
        $price_record_merchant_id = $this->getPriceRecordMerchantIdFromOrderObject($this->order);
        try {
            $items = $this->convertCartItemsFromItemIdAndSizeIdToItemSizeIdAKASizePriceId($items, $price_record_merchant_id);
        } catch (MenuNotCurrentException $mnce) {
            return returnErrorResource('Sorry, something has changed with this merchant. Please reload the merchant from the merchant list. Sorry for the confusion.');
        }
        $total_validated_points = 0;
        foreach ($items as &$item) {
            if ($this->loyalty_controller) {
                try {
                    $total_validated_points = $total_validated_points + $this->loyalty_controller->validatePointsForOrderItem($item);
                } catch (PayWithPointsException $pe) {
                    return createErrorResourceWithHttpCode($pe->getMessage(), 500, 999);
                }
            }
            if ($error_resource = $this->doComboValidationForItem($item)) {
                return $error_resource;
            }
        }

        if ($total_validated_points > 0) {
            if ($error_resource = $this->loyalty_controller->validateTotalPointsAgainstRulesAndUserAccount($total_validated_points)) {
                return $error_resource;
            }
        }
    }

    function doComboValidationForItem($item)
    {
        myerror_log("testing combo item", 3);
        $combo_items = 0;
        $mods = $item['mods'];
        foreach ($mods as $mod) {
            $sql_combo = "SELECT c.modifier_type FROM Modifier_Size_Map a, Modifier_Item b, Modifier_Group c WHERE a.modifier_size_id = " . $mod['mod_sizeprice_id'] . " AND a.modifier_item_id = b.modifier_item_id AND b.modifier_group_id = c.modifier_group_id";
            $combo_options[TONIC_FIND_BY_SQL] = $sql_combo;
            $rs = $this->adapter->select('', $combo_options);
            $type = $rs[0]['modifier_type'];
            myerror_logging(3, "type is: $type");
            if ($type == 'I2') {
                $combo_items++;
            }
        }
        myerror_log("number of combo items is: " . $combo_items, 3);
        if ($combo_items == 1) {
            $sql_item = "SELECT b.item_name FROM Item_Size_Map a, Item b WHERE a.item_size_id = " . $item['sizeprice_id'] . " AND a.item_id = b.item_id";
            $item_options[TONIC_FIND_BY_SQL] = $sql_item;
            $rs2 = $this->adapter->select('', $item_options);
            $item_name = $rs2[0]['item_name'];
            if (getBrandIdFromCurrentContext() == 292)
                return createErrorResourceWithHttpCode("Sorry, you must choose both combo size and combo drink to get the combo price on your " . $item_name . ".  A-la-carte items are available from the main menu. Thanks!", 422, 999);
            else {
                return createErrorResourceWithHttpCode("Sorry, you must choose both combo items to get the combo price on your " . $item_name . ".  A-la-carte items are available from the main menu. Thanks!", 422, 999);
            }
        }
    }

    /**
     * @param $order Order
     * @param $promo_code string
     * @return Resource
     */
    function applyPromoToOrder($order, $promo_code)
    {
        $order_resource = $order->getOrderResource();
        if ($promo_code == 'variable') {
            $order_resource->promo_amt = 0.00;
            $order_resource->promo_tax_amt = 0.00;
            $order_resource->promo_id = 0;
            $order_resource->promo_code = 'nullit';
            $order_resource->save();
            $this->order = new OrderMaster($order_resource->order_id);
            return null;
        }
        $promo_controller = new PromoController(getM(), $this->user, $this->request);
        $items = $order->getOrderItemInfo();
        if ($this->isAddToTypeOneGroupOrder()) {
            if (substr($promo_code,0,2) == 'X_') {
                // auto promo
                $order_resource->promo_amt = 0.00;
                $order_resource->promo_tax_amt = 0.00;
                $order_resource->promo_id = 0;
                $order_resource->promo_code = 'nullit';
                $order_resource->save();
                $this->order = new OrderMaster($order_resource->order_id);
                return null;
            } else {
                return createErrorResourceWithHttpCode("Sorry, promos are not enabled on this type of group order.", 422, 422, array("error_type" => 'promo'));
            }

        }
        // create promo data
        $promo_data = $order->getOrderResource()->getDataFieldsReally();
        $promo_data['items'] = $items;
        $promo_data['promo_code'] = $promo_code;
        $promo_resource = $promo_controller->validatePromo($promo_data);

        if (isset($promo_resource->error_code)) {
            myerror_log("we have a promo_error: ".$promo_resource->error);
            if (substr($promo_code,0,2) == 'X_') {
                // auto promo
                if ($order_resource->promo_amt < 0.00 || $order_resource->promo_id > 0) {
                    $order_resource->promo_amt = 0.00;
                    $order_resource->promo_tax_amt = 0.00;
                    $order_resource->promo_code = 'nullit';
                    $order_resource->promo_id = 'nullit';
                    $order_resource->save();
                }
                return null;
            }
            $promo_resource->set("http_code", 422);
            return $promo_resource;
        } else if ($promo_resource->complete_promo == false || $promo_resource->complete_promo == "false") {
            $promo_resource->user_message_title = "Promo NOT complete!";
            $promo_resource->user_message = "You have not completed your promo! " . $promo_resource->user_message;
            $order_resource->promo_amt = 0.00;
            $order_resource->promo_tax_amt = 0.00;
        } else {
            $order_resource->promo_amt = -$promo_resource->amt;
            $order_resource->promo_tax_amt = -$promo_resource->tax_amt;
        }
        //$order_resource->grand_total = $order_resource->grand_total - $promo_resource->amt;
        // update order values
        if ($promo_resource->promo_type == 6) {
            $promo_code = 'variable';
        }
        $order_resource->promo_code = $promo_code;
        $order_resource->promo_id = $promo_resource->promo_id;
        $order_resource->save();
        $this->order = new OrderMaster($order_resource->order_id);
        return $promo_resource;
    }

    /**
     * @desc does things like switch between pickup and delivery
     * @param $data
     */
    function processNonItemNonCheckoutMethodsRequestData($data)
    {
        if (OrderAdapter::updateOrderType($data, $this->order->getOrderResource())) {
            $this->order = new OrderMaster($this->order->getOrderId());
        }
    }

    function setGroupOrderRecordByGroupOrderToken($group_order_token)
    {
        if ($group_order_record = getStaticRecord(array("group_order_token" => $group_order_token), "GroupOrderAdapter")) {
            $this->setGroupOrderRecord($group_order_record);
            $this->setMerchantResourceAndInfoByMerchantId($group_order_record['merchant_id']);
            return true;
        }
    }

    function setGroupOrderRecordByUserOrderId($order_id)
    {
        $sql = "SELECT a.* FROM Group_Order a JOIN Group_Order_Individual_Order_Maps b ON a.group_order_id = b.group_order_id WHERE b.user_order_id = $order_id";
        $options[TONIC_FIND_BY_SQL] = $sql;
        if ($record = $this->adapter->select(null, $options)) {
            $this->group_order_record = array_pop($record);
        }
    }

    function setGroupOrderRecord($group_order_record)
    {
        $this->group_order_record = $group_order_record;
    }

    function isGroupOrder()
    {
        return $this->group_order_record != null;
    }

    function isAddToTypeOneGroupOrder()
    {
        if ($this->isGroupOrder()) {
            if ($this->group_order_record['group_order_type'] == GroupOrderAdapter::ORGANIZER_PAY) {
                if ($this->group_order_record['group_order_token'] != $this->order->get('ucid')) {
                    return true;
                }
            }
        }
        return false;
    }

    function isTypeTwoGroupOrderDeliveryParticipant()
    {
        if ($this->isTypeTwoGroupOrderDelivery()) {
            return $this->user['user_id'] != $this->group_order_record['admin_user_id'];
        }
    }

    function isTypeOneGroupOrderDelivery()
    {
        if ($this->isGroupOrder()) {
            if ($this->group_order_record['group_order_type'] == GroupOrderAdapter::ORGANIZER_PAY) {
                return strtolower($this->group_order_record['merchant_menu_type']) == 'delivery';
            }
        }
        return false;
    }

    function isTypeTwoGroupOrderDelivery()
    {
        if ($this->isTypeTwoGroupOrder()) {
            return strtolower($this->group_order_record['merchant_menu_type']) == 'delivery';
        }
    }

    function isTypeTwoGroupOrder()
    {
        if ($this->isGroupOrder()) {
            return $this->group_order_record['group_order_type'] == GroupOrderAdapter::INVITE_PAY;
        } else {
            return false;
        }
    }

    function getMerchantResourceAsRecord($merchant_id)
    {
        if ($merchant_resource = $this->getMerchantResource($merchant_id)) {
            return $merchant_resource->getDataFieldsReally();
        }
    }

    function getMerchantResource($merchant_id)
    {
        if ($this->merchant_resource && $this->merchant_resource->merchant_id == $merchant_id) {
            return $this->merchant_resource;
        } else {
            return $this->setMerchantResourceAndInfoByMerchantId($merchant_id);
        }
    }

    function  setMerchantResourceAndInfoByMerchantId($merchant_id)
    {
        if ($this->merchant_resource = Resource::find(new MerchantAdapter(), '' . $merchant_id)) {
            $merchant_controller = new MerchantController(getM(),$this->user,$this->request);
            $merchant_info = [];
            $merchant_info['taxes'] = $merchant_controller->getTotalTaxRates($merchant_id);
            $merchant_info['taxes'][0] = 0.00;
//            $hour_adapter = new HourAdapter($this->mimetypes);
//            $merchant_info["readable_hours"] = $hour_adapter->getAllMerchantHoursHumanReadableV2($merchant_id);
//            // get delivery info if it exists
//            if ($merchant_delivery_info = $merchant_controller->getMerchantDeliveryInfoNew($merchant_id)) {
//                $merchant_info["delivery_info"] = $merchant_delivery_info;
//            }

            $merchant_info['merchant_menu_maps'] = MerchantMenuMapAdapter::getMerchantMenuMapsByOrderType($merchant_id);
            $this->merchant_info = $merchant_info;
            return $this->merchant_resource;
        }
    }

    function getMerchantTaxRates()
    {
        return $this->merchant_info['taxes'];
    }

    function addCartToTypeOneGroupOrder($ucid, $group_order_record)
    {
        $user = $this->user;
        $cart = CompleteOrder::getItemsForGroupOrderFromCartId($ucid);
        $user_name = $user['first_name'] . ' ' . substr($user['last_name'], 0, 1);
        myerror_logging(3, "name acquired: " . $user_name);
        foreach ($cart['items'] as &$item) {
            if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($item, 'note')) {
                $item['note'] = $user_name . ". - " . $item['note'];
            } else {
                $item['note'] = $user_name . ".";
            }

        }
        if ($this->addItemsToCart($cart['items'], $group_order_record)) {
            $cart_detail_resource = $this->getCart($ucid);
        } else {
            return createErrorResourceWithHttpCode("ther was an error", 422, 999);
        }
        // now get participants cart

        $group_order_detail_resource = Resource::createByData(new GroupOrderDetailAdapter($this->mimetypes), array("group_order_id" => $group_order_record['group_order_id'], "user_id" => $user['user_id'], "order_json" => json_encode($cart['items'])));
        $cart_detail_resource->set("group_order_detail_id", $group_order_detail_resource->group_order_detail_id);
        $cart_detail_resource->set("user_message", "Your order has been added to the group order.");

        // set map to submitted
        $goima = new GroupOrderIndividualOrderMapsAdapter();
        $goima->addSubmittedOrderToGroup($cart['cart_order_id'], $group_order_record['group_order_id']);

        // change cart to 'G'
        OrderAdapter::updateOrderStatus(OrderAdapter::GROUP_ORDER, $cart['cart_order_id']);
        return $cart_detail_resource;
    }

    function setTestUser()
    {
        $loginAdapter = new UserAdapter();
        $user_id = 101;
        $user_resource = Resource::find($loginAdapter, $user_id);
        $user = $user_resource->getDataFieldsReally();
        $_SERVER['AUTHENTICATED_USER'] = $user;
        $_SERVER['AUTHENTICATED_USER_ID'] = $user['user_id'];
        $this->user = $user;
    }

    /**
     *
     * @desc Logs the store_tester in and places a simple test order at the passed in merchant
     * @param $merchant_id
     * @param $merchant_menu_type
     * @param $note
     */
    function placeSimpleTestOrder($merchant_id, $merchant_menu_type = 'pickup', $note = 'skip hours')
    {
        $this->setTestUser();
        $order_adapter = new OrderAdapter();
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, $merchant_menu_type, $note);
        $order_data['items'][0]['note'] = $note;
        $order_resource = placeOrderFromOrderData($order_data,time());
        return $order_resource;
    }

    /**
     *
     * @desc will create a new Cart.
     * @return Resource
     */
    function createNewCart($data)
    {
        if (isLoggedInUserATempUser()) {
            return returnErrorResource("We're sorry but you do not appear to be logged in. Please log in and try again. We apologize for the inconvenience.", 999, array("http_code" => 500));
        }
        $data['user_id'] = getLoggedInUserId();
        if ($this->isTypeTwoGroupOrderDelivery()) {
            if ($parent_order_data = $this->adapter->getRecordFromPrimaryKey($this->group_order_record['order_id'])) {
                $data['group_order_parent_order_record'] = $parent_order_data;
            }
        }
        $cart_resource = CartsAdapter::createCart($data);
        if ($cart_resource->hasError()) {
            return $cart_resource;
        }
        $this->merchant = $this->getMerchantResourceAsRecord($cart_resource->merchant_id);

        if ($data['group_order_token']) {
            if ($this->isGroupOrderClosed()) {
                return $this->error_resource;
            }
            GroupOrderIndividualOrderMapsAdapter::addOrderToGroup($cart_resource->order_id, $this->group_order_record['group_order_id']);
        }
		// apply delivery stuff if exists
		if (isset($data['user_addr_id']) && ! $this->hasRequestForDestination('checkout')) {
			$cart_resource = $this->processDeliveryLocation($data['user_addr_id'],$cart_resource);
		} else {
            if (isset($data['group_order_token'])) {
                if ($data['group_order_parent_order_record']['order_type'] == OrderAdapter::DELIVERY_ORDER) {
                    $cart_resource->order_type = OrderAdapter::DELIVERY_ORDER;
                    $cart_resource->user_delivery_location_id = $data['group_order_parent_order_record']['user_delivery_location_id'];
                    if ($data['user_id'] == $data['group_order_parent_order_record']['user_id']) {
                        // this is the admin adding their order so charge them the delivery fee
                        $cart_resource->delivery_amt = $data['group_order_parent_order_record']['delivery_amt'];
                    }
                    $cart_resource->save();
                }
                unset($data['group_order_parent_order_record']);
            }
        }
        //$convenience_fee = $this->getLowestValidConvenienceFeeFromMerchantUserCombination(0.00, $this->merchant, $this->user, $this->group_override_record);

        return $cart_resource;
    }

    /**
     * @param $udl_id int
     * @param $cart_resource Resource
     * @return Resource
     */
    function processDeliveryLocation($udl_id, $cart_resource)
    {
        myerror_logging(1, "we are starting the new delivery calculation");
        myerror_logging(2, "the user delivery location id is: " . $udl_id);

        if ($udl_id == null || $udl_id < 100) {
            MailIt::sendErrorEmail("ERROR WITH PLACE ORDER!", "delivery order submitted with no user_delivery_location_id");
            return returnErrorResource("We're VERY sorry, but there appears to be a problem with our delivery system. Our techteam has been alerted, so please try again later or do a pickup order.  Again, we're very sorry about this.");
        }

        $merchant_id = $cart_resource->merchant_id;
        $mdi_adapter = new MerchantDeliveryInfoAdapter($this->mimetypes);
        $mdi_resource = $mdi_adapter->getFullMerchantDeliveryInfoAsResource($merchant_id);

        if ($mdi_resource->merchant_delivery_active == 'N') {
            myerror_log("ERROR!  Merchant showing delivery NOT active but order for delivery submitted");
            MailIt::sendErrorEmail('Impossible error', 'Merchant showing delivery NOT active but order for delivery submitted.  merchant_id: ' . $merchant_id);
            return returnErrorResource("We're sorry, this merchant has not set up their delivery information yet so a delivery order cannot be submitted at this time.", 520);
        }

        if ($merchant_delivery_price_distance_resource = $mdi_adapter->getMerchantDeliveryPriceResourceForUserLocationAndMerchantId($udl_id, $merchant_id)) {
            $delivery_price = $merchant_delivery_price_distance_resource->price;
            myerror_logging(3, "we have aquired the delivery price of: " . $delivery_price);
            $delivery_tax_amount = $mdi_adapter->getDeliveryTaxAmount($this->merchant,$delivery_price);
            //$delivery_tax_amount = $this->getDeliveryTaxAmount($this->merchant, $delivery_price);
            $cart_resource->delivery_amt = $delivery_price;// + $delivery_tax_amount;
            $cart_resource->delivery_tax_amt = $delivery_tax_amount;
            $cart_resource->user_delivery_location_id = $udl_id;
            //$cart_resource->grand_total = $cart_resource->grand_total + $cart_resource->delivery_amt;
            $cart_resource->save();
            return $cart_resource->getRefreshedResource();
        } else {
            //  outside delivery range
            return createErrorResourceWithHttpCode("We're sorry, this delivery address appears to be outside of our delivery range.", 422, 422);
        }
    }

    function filterSubmittedCartItemsByStatusIfItExists($items, $ucid)
    {
        //loop through order_data['items'] to determin if there is status
        // if there is status
        // status = 'Deleted' delete the item on the API cart and remove from order data
        // status = 'Updated' delete the item on the API cart leave item in order data. possibly null out order_detail_id
        // status = 'New' do nothing
        // status = 'null' remove from order data (no changes to this item that is already in the API cart
        $new_items_only = array();
        $new = false;
        foreach ($items as $item) {
            if ($status = $item['status']) {
                myerror_log("we have the status of the item: $status", 3);
                logData($item, "THE ITEM", 5);
                if (areStringsEqualCaseInsentive($status, 'deleted')) {
                    // do delete
                    if ($order_detail_id = $item['order_detail_id']) {
                        $delete_result = $this->deleteItemFromCart($ucid, $order_detail_id);
                        $new = true;
                    } else {
                        //abort. correct data not submitted
                        return $items;
                    }
                } else if (areStringsEqualCaseInsentive($status, 'updated')) {
                    // do update
                    if ($order_detail_id = $item['order_detail_id']) {
                        // delete old item
                        $delete_result = $this->deleteItemFromCart($ucid, $order_detail_id);
                        // now add it as a new item
                        $new_items_only[] = $item;
                        $new = true;
                    } else {
                        // abort. correct data not subitted
                        return $items;
                    }
                } else if (areStringsEqualCaseInsentive($status, 'new')) {
                    $new_items_only[] = $item;
                } else {
                    $new = true;
                }
            } else {
                // we have no status on at least 1 of the itmes so we have to abort and just use the submitted list.
                myerror_log("no status on this item so revert to processing all items as new", 3);
                return $items;
            }
        }
        if ($new) {
            return $new_items_only;
        } else {
            return $items;
        }
    }

    function getCompleteUserMessageFromExistingResourceAndNewMessage($resource, $new_user_message)
    {
        if (isset($resource->user_message) && $resource->user_message != null && trim($resource->user_message) != '') {
            return $resource->user_message . '. ' . $new_user_message;
        } else {
            return $new_user_message;
        }
    }

    function isCartActive($cart_resource)
    {
        return OrderAdapter::isStatusAnActiveStatus($cart_resource->status);
    }

    static function staticGetCartWithOrderSummary($cart_ucid)
    {
        if ($cart_resource = SplickitController::getResourceFromId($cart_ucid, 'Carts')) {
            if ($order_id = $cart_resource->order_id) {
                $complete_order = CompleteOrder::staticGetCompleteOrder($order_id, getM());
                $order_summary = $complete_order['order_summary'];
                $cart_resource->set('order_summary', $order_summary);
            }
            if (isNotProd()) {
                $cart_resource->set('oid_test_only', $cart_resource->order_id);
            }
            return $cart_resource;
        }
        return null;
    }

    function getCart($cart_ucid)
    {
        if ($this->order) {
            $this->recalculateOrder($this->order);
        }
        return CartsAdapter::staticGetCartResourceWithOrderSummary($cart_ucid);
    }

    function recalculateOrderTotalsAndItemAmounts($order_id)
    {
        $order_detail_adapter = new OrderDetailAdapter();
        $sql = "SELECT SUM(item_total_w_mods) AS order_amt,SUM(item_tax) AS item_tax_amt,SUM(quantity) as quantity FROM Order_Detail WHERE `order_id` = $order_id and `quantity``logical_delete` = 'N'";
        $options[TONIC_FIND_BY_SQL] = $sql;
        $results = $order_detail_adapter->select(null, $options);

        $order_adapter = new OrderAdapter();
        $order_resource = Resource::find($order_adapter, "$order_id");
        $order_resource->order_amt = $results[0]['order_amt'];
        $order_resource->item_tax_amt = $results[0]['item_tax_amt'];
        $order_resource->quantity = $results[0]['quantity'];
        $order_resource->save();
        $this->recalculateOrder($this->order);
    }

    function updateOrderTotalsFromDeletedCartItem($order_id, $deleted_item_record)
    {
        if ($order_resource = Resource::find(new OrderAdapter(), "$order_id")) {
            $order_resource->order_amt = $order_resource->order_amt - $deleted_item_record['item_total_w_mods'];
            $order_resource->total_tax_amt = $order_resource->total_tax_amt - $deleted_item_record['item_tax'];
            $order_resource->grand_total = $order_resource->grand_total - $deleted_item_record['item_total_w_mods'] - $deleted_item_record['item_tax'];
            $order_resource->order_qty = $order_resource->order_qty - 1;
            $order_resource->save();
        } else {
            myerror_log("GROUP ORDER DELETE. ERROR!  could not get order resource to update totals");
        }
    }

    function deleteItemFromCart($cart_ucid, $order_detail_id)
    {
        if ($cart_resource = $this->getCart($cart_ucid)) {
            if ($order_id = $cart_resource->order_id) {
                $order_detail_adapter = new OrderDetailAdapter();
                if ($deleted_item_record = $order_detail_adapter->logicallyDeleteOrderDetailItem($order_detail_id)) {
//				  	//now update item totals on order
//					$this->order->recalculateTotals();
                    // getting cart we will update the order record with new grand total
                    return $this->getCart($cart_ucid);
                } else {
                    return $this->unableToDeleteItemFromOrderErrorResource(mysql_error());
                }
            } else {
                return $this->returnUnableToFindCartErrorResource();
            }
        } else {
            return $this->returnUnableToFindCartErrorResource();
        }
        return $cart_resource;
    }

    function getCheckoutDataFromCartId($cart_ucid)
    {
        try {
            if ($order = new OrderMaster($cart_ucid)) {
                return $this->getCheckoutDataFromOrder($order);
            } else {
                return $this->returnUnableToFindCartErrorResource();
            }
        } catch (NoMatchingOrderIdException $e) {
            return $this->returnUnableToFindCartErrorResource();
        }

    }

    function returnUnableToFindCartErrorResource()
    {
        return returnErrorResource("Sorry, we cannot find your cart, it may have expired. Sorry for the inconvenience.", 400, array("http_code" => 400));
    }

    function unableToDeleteItemFromOrderErrorResource($error)
    {
        return createErrorResourceWithHttpCode("Error: unable to delete item from order: $error", 500, 999);
    }

    /**
     * @desc used to get the checkout data
     *
     * @return Resource
     */
    function getCheckoutDataFromOrderRquest()
    {
        $order_data = $this->request->data;
        return $this->getCheckoutDataFromOrderData($order_data);
    }

    function getCheckoutDataAdditionalMessage($lead_times_array, $order_type, $high_volume)
    {
        $first_time = $lead_times_array[0];
        $current_time = $this->current_time;
        $diff = $first_time - $current_time;
        if ($diff > 2400) {
            // please note, your first available pickup time for this order is over 1 hour from now.
            myerror_logging(3, "first available time is $diff seconds from now");
            $first_time_string = date("Y-m-d H:i", $first_time);
            $current_time_string = date("Y-m-d H:i", $current_time);
            myerror_logging(3, "about to find diff in hours of $first_time_string  and  $current_time_string");

            // diff is total number of seconds the first available time is in the future
            if ($diff < 7200) {
                // if less than 2 hours
                $minutes = floor($diff / 60);
                $time_string = "$minutes minutes";
            } else {
                // more than 2 hours
                $hours = floor($diff / 3600);
                $time_string = "over $hours hours";
            }

            if ($this->delivery_service != null) {
                $additional_message = "Please note, your first available $order_type time for this order is $time_string from now. This is an estimated delivery time from 3rd party delivery service ".$this->delivery_service.".";
                return $additional_message;
            }

            $high_volume_string = "";
            if ($high_volume) {
                $high_volume_string = ", due to high volume";
            }

            $additional_message = "Please note$high_volume_string, your first available $order_type time for this order is $time_string from now.";
            myerror_logging(3, "Checkout Data message set as:  $additional_message");
            return $additional_message;
        } else if ($this->delivery_service != null) {
            $additional_message = "Please note, this is an estimated delivery time from 3rd party delivery service ".$this->delivery_service.".";
            return $additional_message;
        }
    }

    function getOrderTypeFromOrderData($order_data)
    {
        if (isset($order_data['order_type'])) {
            return strtoupper($order_data['order_type']);
        } else if (isset($order_data['base_order_data']['order_type'])) {
            return strtoupper($order_data['base_order_data']['order_type']);
        } else if (isset($order_data['user_addr_id'])) {
            return 'D';
        } else {
            return 'R';
        }
    }

    function isGroupOrderClosed()
    {
        if ($this->isGroupOrder()) {
            if (strtolower($this->group_order_record['status']) == 'submitted') {
                $this->error_resource = createErrorResourceWithHttpCode("Sorry, this group order has already been submitted.", 422, 999);
                return true;
            } else if (strtolower($this->group_order_record['status']) != 'active') {
                $this->error_resource = createErrorResourceWithHttpCode("Sorry, this group order is no longer active", 422, 999);
                return true;
            }
        }
        return false;
    }

    /**
     * @param $order Order
     */
    function recalculateOrder($order)
    {
        $this->order = $order->recalculateTotals();
        return $this->order;
    }

    function doesUserNeedToEnterCreditCardForOrder()
    {
        if ($this->isAddToTypeOneGroupOrder()) {
            return false;
        } else if ($this->order->get('grand_total') == 0.00) {
            return false;
        } else if (substr($this->user['flags'],1,1) == 'C') {
            return false;
        } else {
            return true;
        }
    }

    function isRoundupActiveForSkinAndUser($skin,$user_id)
    {
        if ($skin['donation_active'] == 'Y') {
            if ($resource = UserSkinDonationAdapter::getDonationResourceForUserAndSkin($user_id,$skin['skin_id'])) {
                return $resource->donation_active == 'Y';
            }
        }
        return false;
    }

    function calculateRoundUpAmountForOrder($order_resource)
    {
        $round_up_amount = ceil($order_resource->grand_total) - $order_resource->grand_total;
        return $round_up_amount;
    }

    /**
     * @param $order Order
     * @return mixed
     */
    function getCheckoutDataFromOrder($order)
    {
        $checkout_data = [];
        if (isLoggedInUserATempUser()) {
            return returnErrorResource("We're sorry but you do not appear to be logged in. Please log in and try again. We apologize for the inconvenience.", 999, array("http_code" => 500));
        }
        if ($error_resource = $this->merchantOrderingActiveValidations()) {
            return $error_resource;
        }
        // figure out if we need to set trans fee based on percentage
        $order = $this->recalculateOrder($order);

        if ($this->merchant['trans_fee_type'] == 'P') {
            $order_resource = $order->getOrderResource();
            $rate = $this->merchant['trans_fee_rate'];
            $order_amt = $order->get("order_amt");
            $order_resource->trans_fee_amt = ($order_amt * ($rate/100));
            $order_resource->save();
            $order = $this->recalculateOrder($order);
        }

        // round up if doing it pre checkout
//        if ($this->isRoundupActiveForSkinAndUser(getSkinForContext(),$order->getUserId())) {
//            $order_resource = $order->getOrderResource();
//            $round_up_amount = $this->calculateRoundUpAmountForOrder($order_resource);
//            $order_resource->customer_donation_amt = $round_up_amount;
//            $order_resource->save();
//            $order = $this->recalculateOrder($order);
//        }

        $order_type = $order->get("order_type");
        $merchant_menu_map = $this->merchant_info['merchant_menu_maps'][$order_type];
        if (! ($order->isCateringOrder() ||  $this->isTypeTwoGroupOrder()) ) {
            // check for entre max
            $max_entres_per_order = $merchant_menu_map['max_entres_per_order'];
            $number_of_entres_in_order = $order->getNumberOfEntreLevelItems();
            myerror_log("max numer of entres per order: $max_entres_per_order,  entres in this order: $number_of_entres_in_order");
            if ($max_entres_per_order > 0 && $number_of_entres_in_order > $max_entres_per_order) {
                $error_message = str_replace('%%max%%',$max_entres_per_order,PlaceOrderController::MAX_NUMBER_OF_ENTRES_EXCEEDED_ERROR_MESSAGE);
                $diff = $order->getNumberOfEntreLevelItems() - $max_entres_per_order;
                $error_message = str_replace('%%diff%%',$diff,$error_message);
                return createErrorResourceWithHttpCode($error_message, 422, 422);
            }
        }


        $merchant_id = $order->get("merchant_id");
        $merchant_resource = $this->getMerchantResource($merchant_id);
        $time_zone_string = getTheTimeZoneStringFromOffset($merchant_resource->time_zone, $merchant_resource->state);


        $leadtime_object = new LeadTime($merchant_resource);
        $holehours_object = new HoleHoursAdapter();
        $holehours = $holehours_object->getByMerchantIdAndOrderType($merchant_resource->merchant_id, $order_type);
        $leadtime_object->setHoleHours($holehours, $this->current_time);



        // check for delivery minimums
        if ($order_type == OrderAdapter::DELIVERY_ORDER) {
            $udl_id = $order->get('user_delivery_location_id');
            $mdi_adapter = new MerchantDeliveryInfoAdapter();
            $mdi_resource = $mdi_adapter->getFullMerchantDeliveryInfoAsResource($merchant_id);
            if ($order->isCateringOrder()) {
                if ($merchant_catering_info_resource = MerchantCateringInfosAdapter::getInfoAsResourceByMerchantId($merchant_id)) {
                    $mdi_resource->minimum_order = $merchant_catering_info_resource->minimum_delivery_amount;
                    $mdi_adapter->setCatering();
                    if ($merchant_catering_info_resource->delivery_active) {
                        $mdi_adapter->setCateringDeliveryActive();
                    }
                }
            }
            //needed for doordash (and probably not a bad idea)
            $base_order_details = ["order_amt"=>$order->get("order_amt")];
            $minimum_pickup_lead_time = $leadtime_object->getPickupLeadtime($this->current_time);
            $number_of_entres_in_order = $order->getNumberOfEntreLevelItems();
            if ($number_of_entres_in_order > 4) {
                $additional_item_count = $number_of_entres_in_order-4;
                $minimum_pickup_lead_time = $leadtime_object->getMinLeadTimeForLargePickupOrder($additional_item_count,$minimum_pickup_lead_time);
            }
            $minimum_pickup_timestamp = $this->current_time + ($minimum_pickup_lead_time * 60);
            $base_order_details['pickup_timestamp_at_merchant'] = $minimum_pickup_timestamp;

            $mdi_adapter->setBaseOrderDetails($base_order_details);
            if ($merchant_delivery_price_distance_resource = $mdi_adapter->getMerchantDeliveryPriceResourceForUserLocationAndMerchantId($udl_id, $merchant_id)) {
                try {
                    $this->validateDeliveryMinimum($mdi_resource, $merchant_delivery_price_distance_resource, $order->get('order_amt'));
                    $order_resource = $order->getOrderResource();
                    if (strtolower($merchant_delivery_price_distance_resource->name) == 'doordash') {
                        $order_resource->ready_timestamp = $minimum_pickup_timestamp;
                        $this->delivery_service = 'Doordash';
                    } else {

                        $order_resource->ready_timestamp = $minimum_pickup_timestamp;
                    }
                    if ($this->isTypeTwoGroupOrder()) {
                        if ($this->group_order_record['admin_user_id'] != $order->getUserId()) {
                            // this is participant so no charge
                            $merchant_delivery_price_distance_resource->price = 0.00;
                        }
                    }
                    $delivery_price = $merchant_delivery_price_distance_resource->price;
                    $delivery_tax_amount = $mdi_adapter->getDeliveryTaxAmount($merchant_resource->getDataFieldsReally(),$delivery_price);
                    $order_resource->merchant_delivery_price_distance_id = $merchant_delivery_price_distance_resource->map_id;
                    $order_resource->delivery_amt = $delivery_price;
                    $order_resource->delivery_tax_amt = $delivery_tax_amount;
                    $order_resource->save();
                    $order = $this->recalculateOrder($order);

                } catch (DeliveryMinimumNotMetException $e) {
                    return createErrorResourceWithHttpCode($e->getMessage(), 422, 999);
                }
            } else {
                if ($mdi_adapter->getDeliveryCalculationType() == 'doordash') {
                    return createErrorResourceWithHttpCode(DeliveryController::DOORDASH_CANNOT_DELIVER_MESSAGE, 422, 999);
                } else {
                    return createErrorResourceWithHttpCode(self::ADDRESS_IS_OUTSIDE_DELIVERY_ZONE_ERROR_MESSAGE, 422, 999);
                }

            }
        } else {
            if ($order->isCateringOrder()) {
                // check for minimum
                if (isset($this->merchant_catering_info) && $this->merchant_catering_info['minimum_pickup_amount'] > $order->get('order_amt')) {
                    return createErrorResourceWithHttpCode("Sorry, this merchant has a minimum pickup catering order amount of $".$this->merchant_catering_info['minimum_pickup_amount'], 422, 999);
                }
            } else {
                $checkout_data['allows_dine_in_orders'] = $merchant_menu_map['allows_dine_in_orders'] == 1 ? true : false;
                $checkout_data['allows_curbside_pickup'] = $merchant_menu_map['allows_curbside_pickup'] == 1 ? true : false;
            }

        }


        $time1 = microtime(true);
        $show_lead_times = true;
        if ($this->isTypeTwoGroupOrder()) {
            if ($this->isGroupOrderClosed()) {
                return $this->error_resource;
            }
            $base_group_order = CompleteOrder::getBaseOrderData($this->group_order_record['group_order_token']);
            $lead_times_array = array($base_group_order['pickup_dt_tm']);
        } else if ($this->isAddToTypeOneGroupOrder()) {
            $checkout_data = array_merge($checkout_data,$order->getCheckoutBaseOrderData());
            $checkout_data['time_zone_string'] = $time_zone_string;
            $checkout_data['time_zone_offset'] = getCurrentOffsetForTimeZone($time_zone_string);
            $checkout_data['lead_times_array'] = array();
            $checkout_data['show_submit_to_parent_group_order_content'] = true;
            $checkout_data['accepted_payment_types'] = array();
            $checkout_data['tip_array'] = array();
            $cart_order = CompleteOrder::staticGetCompleteOrder($order->get('order_id'), getM());
            $checkout_data['order_summary'] = $cart_order['order_summary'];
            $checkout_data['receipt_items'] = $cart_order['receipt_items'];
            return Resource::dummyFactory($checkout_data);
        } else if ($catering_record = $order->getCateringInfo()) {
            $merchant_controller = new MerchantController(getM(),$this->user,$this->request);
            $available_catering_leadtimes_resource = $merchant_controller->getMerchantAvailableCateringOrderTimes($merchant_id,$order_type);
            $lead_times_array = array($catering_record['timestamp_of_event']);
            foreach ($available_catering_leadtimes_resource->catering_place_order_times as $the_time_record) {
                $lead_times_array[] = $the_time_record;
            }
        } else if ($order->useDefaultLeadTimes()) {
            $lead_times_array = $leadtime_object->getNext90();
        } else if ($order_type == OrderAdapter::DELIVERY_ORDER && strtolower($merchant_delivery_price_distance_resource->name) == 'doordash') {
            $lead_times_array = [$merchant_delivery_price_distance_resource->delivery_timestamp];
            $leadtime_object->open_close_ts_array = $leadtime_object->getNextOpenAndCloseTimeStamps($merchant_id,'D','7');
        } else {
            //$lead_times_array = $leadtime_object->getLeadTimesArrayFromOrderDataWithThrottling($order_data,$this->current_time);
            $lead_times_array = $leadtime_object->getLeadTimesArrayFromOrderObjectWithThrottling($order, $this->current_time);
        }
        $lead_times_by_day_array = [];
        foreach ($lead_times_array as $available_time) {
            if (is_numeric($available_time)) {
                foreach ($leadtime_object->open_close_ts_array as $index=>$values) {
                    if ($values['open'] <= $available_time && $values['close'] >= $available_time) {
                        if ($index == 0) {
                            $day = 'Today';
                        } else if ($index == 1) {
                            $day = 'Tomorrow';
                        } else {
                            $day = date('D j',$values['open']);
                        }
                        $lead_times_by_day_array["$day"][] = $available_time;
                        break;
                    }
                }
            } else if ($available_time == 'As soon as possible') {
                $lead_times_by_day_array["Today"][] = $available_time;
            }
        }
        myerror_log("time for get lead time with throttling is " . getElapsedTimeFormatted($time1) . " seconds");
        $checkout_data['minimum_leadtime_for_this_order'] = $leadtime_object->getMinimumLeadTimeForThisOrder();
        $this->minimum_lead_time_for_this_order = $leadtime_object->getMinimumLeadTimeForThisOrder();
        $checkout_data['show_lead_times'] = $show_lead_times;

        if ($leadtime_error_message = $leadtime_object->leadtime_error) {
            // we have an error message that needs to be disolayed to the user, abort process
            return returnErrorResource($leadtime_error_message, 999);
        }


        // check for delivery minimums
        if ($order_type == OrderAdapter::DELIVERY_ORDER) {
            try {
                $this->doDeliveryCalculation($order,$lead_times_array,$leadtime_object);
            } catch (DeliveryMinimumNotMetException $e) {
                return createErrorResourceWithHttpCode($e->getMessage(), 422, 999);
            } catch (UserAddressNotWithinDeliveryZoneException $e) {
                return createErrorResourceWithHttpCode($e->getMessage(), 422, 999);
            } catch (ParticipantNoDeliveryChargeException $e) {
                // do nothing, just using this as a logic path break
            }
        } else {
            if ($order->isCateringOrder()) {
                // check for minimum
                if (isset($this->merchant_catering_info) && $this->merchant_catering_info['minimum_pickup_amount'] > $order->get('order_amt')) {
                    return createErrorResourceWithHttpCode("Sorry, this merchant has a minimum pickup catering order amount of $".$this->merchant_catering_info['minimum_pickup_amount'], 422, 999);
                }
            }
        }


        $checkout_data = array_merge($checkout_data,$order->getCheckoutBaseOrderData());
        $checkout_data['time_zone_string'] = $time_zone_string;
        $checkout_data['time_zone_offset'] = getCurrentOffsetForTimeZone($time_zone_string);
        myerror_log("time for get lead time with throttling is " . getElapsedTimeFormatted($time1) . " seconds");
        $checkout_data['minimum_leadtime_for_this_order'] = $leadtime_object->getMinimumLeadTimeForThisOrder();
        $this->minimum_lead_time_for_this_order = $leadtime_object->getMinimumLeadTimeForThisOrder();
        $checkout_data['show_lead_times'] = $show_lead_times;

        //get alert message if there is one

        $checkout_data['user_message'] = $leadtime_object->user_message != null ? $leadtime_object->user_message : $this->getCheckoutDataAdditionalMessage($lead_times_array, $leadtime_object->order_type, $leadtime_object->high_volume);

        if ($this->order->isCateringOrder() && isset($this->merchant_catering_info) && $this->merchant_catering_info['minimum_tip_percent'] > 0) {
            if ($checkout_data['user_message'] != null || $checkout_data['user_message'] != '') {
                $checkout_data['user_message'] = "\n".$checkout_data['user_message'];
            }
            $checkout_data['user_message'] = "Please note, this merchant has a required minimum tip of ".$this->merchant_catering_info['minimum_tip_percent']."% of your order.".$checkout_data['user_message'];
        }
        myerror_logging(3, "we have retrieved the lead times array and it is of size: " . sizeof($lead_times_array));

//        //now validate door dash stuff
//        if ($order_type == OrderAdapter::DELIVERY_ORDER) {
//            if (strtolower($merchant_delivery_price_distance_resource->name) == 'doordash') {
//                // need to recalculate now
//                $doordash_service = new DoordashService();
//                $estimate = $doordash_service->getEstimate($mdi_adapter->getUserDeliveryLocationResource()->getDataFieldsReally(),$merchant_resource->getDataFieldsReally(),$order_amt*100,$lead_times_array[0]);
//            }
//        }

        $checkout_data['lead_times_array'] = $lead_times_array;
        $checkout_data['lead_times_by_day_array'] = $lead_times_by_day_array;

        //$json = json_encode($checkout_data);
        if ($order->get('promo_amt') < 0.00) {
            $checkout_data['promo_amt'] = $order->get('promo_amt');
            // had to add this as string becuase it was blowing up IOS. this is due to the Tonic Adapter returning EVERYTHIGN as a string i think. shit.
            $checkout_data['discount_amt'] = "".-$checkout_data['promo_amt'];
//			if ($resource->user_message) {
//				$checkout_data['user_message'] = $resource->user_message;
//			}
        }
        $checkout_data['order_amt'] = $order->get('order_amt');
        // now get tip array
        $brand = getBrandForCurrentProcess();
        if ($order->isCateringOrder() && isset($this->merchant_catering_info) && $this->merchant_catering_info['minimum_tip_percent'] > 0) {
            $tip_array = $this->getCateringTipArrayForMinimum($order->get('order_amt'),$this->merchant_catering_info['minimum_tip_percent']);
            $checkout_data['tip_array'] = $tip_array;
        } else {
            logData($brand,' *******  BRAND ********',5);
            if (isRequestBrowserBased() && $brand['allows_tipping'] == 'N') {
                ; // skip tip
            } else if (isRequestBrowserBased() && strtoupper($merchant_resource->show_tip) == 'N') {
                ; // skip tip
            } else {
                $default_tip_value = null;
                $tip_array = $this->createTipArray($merchant_resource->tip_minimum_trigger_amount, $merchant_resource->tip_minimum_percentage, $order->get('order_amt'));
                $checkout_data['tip_array'] = $tip_array;
                if ($this->minimum_tip_requirement_exists_for_this_order == true) {
                    $message = 'Please note, this merchant has a minimum tip of ' . $merchant_resource->tip_minimum_percentage . '% on orders over $' . number_format($merchant_resource->tip_minimum_trigger_amount, 2);
                    if ($merchant_resource->tip_minimum_trigger_amount < 1) {
                        $message = 'Please note, this merchant has a minimum tip of ' . $merchant_resource->tip_minimum_percentage . '% on all orders.';
                    }
                    $checkout_data['user_message'] = isset($checkout_data['user_message']) ? $checkout_data['user_message'] . chr(10) . chr(10) . $message : $message;
                } else {
                    if ($merchant_menu_map['default_tip_percentage'] > 0) {
                        $default_tip_value = $merchant_menu_map['default_tip_percentage'];
                    }
                }
                $checkout_data['pre_selected_tip_value'] = $default_tip_value.'%';
            }
        }
        try {
            if (($this->merchant['brand_id'] == 150 || $this->merchant['brand_id'] == 152 || $this->merchant['brand_id'] == 437 )&& MerchantBrinkInfoMapsAdapter::isMechantBrinkMerchant($merchant_id)) {
                // get correct tax amount from brink
                $brink_controller = new BrinkController($m, $u, $r);
                $order = $brink_controller->setBrinkCheckoutInfoOnOrderObject($order);
                $checkout_data['grand_total'] = $order->get('grand_total');
            } else if (MerchantVivonetInfoMapsAdapter::isMechantAVivonetMerchant($merchant_id)) {
                $vivonet_contoller = new VivonetController(getM(),$this->user,$this->request);
                $order = $vivonet_contoller->setVivonetCheckoutInfoOnOrderObject($order);
                $checkout_data['promo_amt'] = $order->get('promo_amt');
                $checkout_data['discount_amt'] = "".-$checkout_data['promo_amt'];
                $checkout_data['grand_total'] = $order->get('grand_total');
            }
        } catch (Exception $e) {
            myerror_log("We had an exception thrown getting the checkout info from an external location: ".$e->getMessage());
            return createErrorResourceWithHttpCode(self::REMOTE_SYSTEM_CHECKOUT_INFO_ERROR_MESSAGE,500);
        }

        $checkout_data['item_tax_amt'] = $order->get('item_tax_amt');
        $checkout_data['total_tax_amt'] = $order->get('total_tax_amt');
        $checkout_data['convenience_fee'] = $order->get('trans_fee_amt');


        if ($order->get('order_type') == OrderAdapter::DELIVERY_ORDER) {
            $checkout_data['delivery_amt'] = $order->get('delivery_amt');
        }


        // add in the order summary
        $complete_order = CompleteOrder::staticGetCompleteOrder($order->get('order_id'));
        $checkout_data['order_summary'] = $complete_order['order_summary'];

        // get payment methods
        $payment_array = $this->getPaymentMethodsForMerchantUserOrderCombination($merchant_id, $order->getOrderResource());
        $cash_type_payment_service_exists = false;
        $skin_id = getSkinIdForContext();
        $payment_exception_records = $this->getPaymentArrayExceptions($skin_id);

        foreach ($payment_array as $row) {
            if ($payment_exception_records) {
                if (! isset($payment_exception_records[$row['splickit_accepted_payment_type_id']])) {
                    continue;
                }
            }
            if ($row['splickit_accepted_payment_type_id'] == StsPaymentService::SPLICKIT_ACCEPTED_PAYMENT_TYPE_ID_FOR_STS) {
                $skin_sts_info_maps_adapter = new SkinStsInfoMapsAdapter(getM());
                if (! $skin_sts_info_maps_adapter->isSkinStsSkin(getSkinIdForContext())) {
                    continue;
                }
                // now get card number if it exists
                $user_skin_stored_value_maps_adapter = new UserSkinStoredValueMapsAdapter(getM());
                if ($card_number = $user_skin_stored_value_maps_adapter->getCardNumberForUserSkinPaymentTypeCombination($order->getUserId(),getSkinIdForContext(),StsPaymentService::SPLICKIT_ACCEPTED_PAYMENT_TYPE_ID_FOR_STS)) {
                    $checkout_data['user_info']['stored_value_card_number'] = $card_number;
                    $payment_service = PaymentGod::getPaymentServiceBySplickitAcceptedPaymentTypeId(StsPaymentService::SPLICKIT_ACCEPTED_PAYMENT_TYPE_ID_FOR_STS);
                    if ($card_info = $payment_service->getCardBalance($card_number)) {
                        $checkout_data['user_info']['stored_value_card_balance'] = $card_info['Amount_Balance'];
                    } else {
                        $checkout_data['user_info']['stored_value_card_balance'] = '0.00';
                    }

                } else {
                    $checkout_data['user_info']['stored_value_card_number'] = '';
                }

            }
            // ok we're going to need to change this. we'll need to see how other Stored Value cards work first though
            // perhaps all stored value payment types are in the 12000 range?   12000,12010,12020,12030, etc......
            if ($row['splickit_accepted_payment_type_id'] == SplickitPaymentService::CASHSPLICKITPAYMENTID || $row['splickit_accepted_payment_type_id'] == SplickitPaymentService::LOYALTY_PLUS_CASH_PAYMENT_ID) {
                if ($this->isTypeTwoGroupOrder()) {
                    // no cash option for type 2 group order
                    continue;
                } else if ($this->order_user_resource->balance > 0.00) {
                    // no cash option if user has balance
                    continue;
                }
                $cash_type_payment_service_exists = true;
            }
            $better_payment_array[] = $row;
        }


        $checkout_data['accepted_payment_types'] = $better_payment_array;
        if (isNotProd()) {
            $checkout_data['oid_test_only'] = $order->get('order_id');
        }

        if (doesFlagPositionNEqualX($this->user['flags'],2,'C')) {
            $checkout_data['user_info']['user_has_cc'] = true;
            $checkout_data['user_info']['last_four'] = $this->user['last_four'];
        } else {
            $checkout_data['user_info']['user_has_cc'] = false;
        }

        // stupid backwards compatibility
        $checkout_data['cart_ucid'] = $order->get('ucid');
        $checkout_data['ucid'] = $order->get('ucid');
        $checkout_data["delivery_tax_amount"] = $order->get('delivery_tax_amt');

        //update stamp on order
        $order->updateStamp();

        $checkout_resource = Resource::dummyFactory($checkout_data);
        return $checkout_resource;
    }

    function getPaymentArrayExceptions($skin_id)
    {
        $adapter = new SkinSplickitAcceptedPaymentTypeMapsAdapter(getM());
        if ($records = $adapter->getRecords(['skin_id'=>$skin_id])) {
            return createHashmapFromArrayOfArraysByFieldName($records,'splickit_accepted_payment_type_id');
        }
    }

    /**
     * @param $order Order
     * @param $lead_times_array
     * @param $leadtime_object LeadTime
     * @return Mixed
     * @throws NoMatchingUserDeliveryLocationException
     * @throws NoMerchantDeliveryInformationException
     */
    function doDeliveryCalculation(&$order,&$lead_times_array,$leadtime_object)
    {
        $udl_id = $order->get('user_delivery_location_id');
        $merchant_resource = $this->merchant_resource;
        $merchant_id = $order->get('merchant_id');
        $mdi_adapter = new MerchantDeliveryInfoAdapter();
        $mdi_resource = $mdi_adapter->getFullMerchantDeliveryInfoAsResource($merchant_id);
        if ($order->isCateringOrder()) {
            if ($merchant_catering_info_resource = MerchantCateringInfosAdapter::getInfoAsResourceByMerchantId($merchant_id)) {
                $mdi_resource->minimum_order = $merchant_catering_info_resource->minimum_delivery_amount;
                $mdi_adapter->setCatering();
                if ($merchant_catering_info_resource->delivery_active) {
                    $mdi_adapter->setCateringDeliveryActive();
                }
            }
        }
        if ($this->isTypeTwoGroupOrder()) {
            if ($this->group_order_record['admin_user_id'] != $order->getUserId()) {
                // this is participant so break out of here
                throw new ParticipantNoDeliveryChargeException();
            }
        }

        // ok this sucks. this is a total hack due to needing to do delivery stuff in another location because of door dash.  ugh
        if ($merchant_delivery_price_distance_id = $order->get('merchant_delivery_price_distance_id')) {
            return;
//            $mdpd_resource = Resource::find(new MerchantDeliveryPriceDistanceAdapter(getM()),"$merchant_delivery_price_distance_id");
//            if (strtolower($mdpd_resource->name) == 'doordash') {
//                $this->delivery_service = 'Doordash';
//            }
//            $delivery_price = $order->get('delivery_amt');
//            $delivery_tax_amount = $mdi_adapter->getDeliveryTaxAmount($merchant_resource->getDataFieldsReally(),$delivery_price);
//            $order_resource = $order->getOrderResource();
//            $order_resource->delivery_amt = $delivery_price;
//            $order_resource->delivery_tax_amt = $delivery_tax_amount;
//            $order_resource->save();
//            $order = $this->recalculateOrder($order);
//            return;
        }

        $first_delivery_time = $lead_times_array[0] == 'As soon as possible' ? $lead_times_array[1] : $lead_times_array[0];
        $ready_timestamp = $this->calculateReadyTimeAtMerchantForDelivery($first_delivery_time,$leadtime_object->getCurrentDeliveryLeadTime()*60,$merchant_resource->lead_time * 60);
//        $base_order_details_for_delivery = ['order_amt'=>$order->get('order_amt'),"pickup_timestamp_at_merchant"=>$ready_timestamp];
//        if ($this->isPlaceOrder) {
//            $base_order_details_for_delivery['is_place_order'] = true;
//        }
//        $mdi_adapter->setBaseOrderDetails($base_order_details_for_delivery);

        if ($merchant_delivery_price_distance_resource = $mdi_adapter->getMerchantDeliveryPriceResourceForUserLocationAndMerchantId($udl_id, $merchant_id)) {

            $this->validateDeliveryMinimum($mdi_resource, $merchant_delivery_price_distance_resource, $order->get('order_amt'));
            // we need to recalculate the devliery fee and entire order
            $delivery_price = $merchant_delivery_price_distance_resource->price;

            $delivery_tax_amount = $mdi_adapter->getDeliveryTaxAmount($merchant_resource->getDataFieldsReally(),$delivery_price);
            $order_resource = $order->getOrderResource();
            $order_resource->delivery_amt = $delivery_price;
            $order_resource->delivery_tax_amt = $delivery_tax_amount;
            $order_resource->merchant_delivery_price_distance_id = $merchant_delivery_price_distance_resource->map_id;
            $order_resource->ready_timestamp = $ready_timestamp;
            $order_resource->save();
            $order = $this->recalculateOrder($order);
        } else {
            throw new UserAddressNotWithinDeliveryZoneException($message);
        }
    }

    function calculateReadyTimeAtMerchantForDelivery($deliver_at_ts,$delivery_lead_time_in_minutes,$merchant_lead_time_in_minutes)
    {
        $base_time = $deliver_at_ts - $delivery_lead_time_in_minutes;
        $ready_timestamp = $base_time + $merchant_lead_time_in_minutes;
        myerror_log("Base time: ".date(DATE_ATOM,$base_time));
        myerror_log("Ready time: ".date(DATE_ATOM,$ready_timestamp));
        return $ready_timestamp;
    }

    /**
     *
     * @desc will return the checkout data from the order_data
     * @param $order_data
     * @return Resource
     */
    function getCheckoutDataFromOrderData($order_data)
    {
        $cart_resource = $this->createNewCart($order_data);
        if ($cart_resource->hasError()) {
            return $cart_resource;
        }
        $this->order = new OrderMaster($cart_resource->order_id);
        $this->setOrderAndMerchantByUcid($cart_resource->ucid);
        if ($error_resource = $this->doSubmittedItemValidations($order_data['items'])) {
            return $error_resource;
        }
        if ($this->addItemsToCart($order_data['items'], $this->order->getBaseOrderData())) {
            $resource = $this->getCart($cart_resource->ucid);
        } else {
            myerror_log("ERROR adding items to cart: ".$this->carts_adapter->getLastErrorText());
            return createErrorResourceWithHttpCode("There was an internal error adding your items to the cart",500,500);
        }
        if ($resource->error) {
            return returnCleanErrorResource($resource);
        }
        $order = new OrderMaster($resource->order_id);
        $checkout_resource = $this->getCheckoutDataFromOrder($order);
        if (isset($resource->user_message) && $resource->user_message != null && trim($resource->user_message) != '') {
            $checkout_resource->user_message = $resource->user_message;
        }
        return $checkout_resource;
    }

    function shouldWeGenerateDefaultLeadTime($order_data)
    {
        $order_data_as_json_string = json_encode($order_data);
        return (stripos($order_data_as_json_string, 'skip hours') > 0 || isLoggedInUserProdTester());
    }

    function removeLevelupPaymentOption($splickit_accepted_payment_type_id, $order_data)
    {
        return (($splickit_accepted_payment_type_id == SplickitPaymentService::LEVELUPSPLICKITPAYMENTID) && !isset($order_data['levelup_user_token']) && !isset($order_data['supports_levelup']));
    }

    /**
     * @desc takes a merchant_id and the checkout order resource and returns the various ways this merchant accepts payment. returns an array of
     * @desc hashmaps witht the following field 'merchant_payment_type_map_id' 'name' 'splickit_accepted_payment_type_id' 'billing_entity_id' (if relevant)
     * @param int $merchant_id
     * @param Resource $checkout_order_resource
     * @return hashmap
     */
    function getPaymentMethodsForMerchantUserOrderCombination($merchant_id, $checkout_order_resource)
    {
        // first check for account credit
        if (isset($this->user) && isset($checkout_order_resource->grand_total) && $checkout_order_resource->grand_total != 0.00 && $this->user['balance'] > ($checkout_order_resource->grand_total)) {
            $merchant_payment_type_maps[] = MySQLAdapter::staticGetRecordByPrimaryKey(1000, 'MerchantPaymentTypeMaps');
        } else {
            $merchant_payment_type_maps = MerchantPaymentTypeMapsAdapter::getMerchantPaymentTypes($merchant_id);
        }
        if (isLevelUpBroadcastContext()) {
            $merchant_payment_type_maps_adapter = new MerchantPaymentTypeMapsAdapter(getM());
            $merchant_payment_type_maps[] = $merchant_payment_type_maps_adapter->getRecord(["merchant_id"=>0,"splickit_accepted_payment_type_id"=>SplickitPaymentService::LEVELUPBROADCASTPAYMENTID]);
        }
        if ($this->loyalty_controller = $this->getLoyaltyController($this->user)) {
            myerror_log("we have the loyalty controler: " . get_class($this->loyalty_controller));
            $local_info = $this->loyalty_controller->getLocalAccountInfo();
        }

        $splickit_accepted_payment_types = SplickitAcceptedPaymentTypesAdapter::getAllIndexedById();
        $better_merchant_accepted_payment_types = array();

        $catering = ($this->order && $this->order->isCateringOrder()) ? true : false;
        foreach ($merchant_payment_type_maps as $merchant_payment_type_map) {
            unset($row);
            if ($catering && $this->isPaymentTypeRestricted($merchant_payment_type_map)) {
                continue;
            }
            $accepted_payment_type_id = $merchant_payment_type_map['splickit_accepted_payment_type_id'];
            myerror_log("we have the accepted payment type id: $accepted_payment_type_id", 3);
            $display_name = $splickit_accepted_payment_types[$accepted_payment_type_id]['name'];
            if ($splickit_accepted_payment_types[$accepted_payment_type_id]['description'] == 'Loyalty Balance Payment') {
                //validate that there is balance
                myerror_log("we have a loyalty balance payment for this merchant", 3);
                myerror_log("the dollar balance for the user is: " . $local_info['dollar_balance'], 3);
                if ($local_info['dollar_balance'] > 0.00) {
                    $loyalty_payment_name = $this->loyalty_controller->getLoyaltyPaymentName();
                    $loyalty_payment_for_this_order = $this->loyalty_controller->getPaymentAllowedForThisOrder($local_info['dollar_balance'], $checkout_order_resource->getDataFieldsReally());
                    if ($loyalty_payment_for_this_order < .01) {
                        // loyalty payment amout is 0.00 for this order, probably due to a promo
                        continue;
                    }
                    $cc_string = $accepted_payment_type_id == 9000 ? '(' . LoyaltyBalancePaymentService::BALANCE_WITH_CASH_TEXT . ')' : '(' . LoyaltyBalancePaymentService::BALANCE_ON_CARD_TEXT . ')';
                    $display_name = "Use $" . number_format($loyalty_payment_for_this_order, 2) . " $loyalty_payment_name $cc_string";
                } else {
                    // no balance do not add row
                    myerror_log("there is no dollar balance so do NOT show loyalty payment info");
                    continue;
                }
            }

            $row['merchant_payment_type_map_id'] = $merchant_payment_type_map['id'];
            $row['name'] = $display_name;
            $row['splickit_accepted_payment_type_id'] = $accepted_payment_type_id;
            $row['billing_entity_id'] = $merchant_payment_type_map['billing_entity_id'];
            logData($row, "PAYMENT ROW", 3);
            $better_merchant_accepted_payment_types[] = $row;
        }
        return $better_merchant_accepted_payment_types;
    }

    function isPaymentTypeRestricted($merchant_payment_type_map)
    {
        if ($this->merchant_catering_info['accepted_payment_types'] == 'Credit Card Only') {
            if ($merchant_payment_type_map['splickit_accepted_payment_type_id'] != 2000) {
                return true;
            }
        } else if ($this->merchant_catering_info['accepted_payment_types'] == 'Cash Or Credit Card') {
            if ($merchant_payment_type_map['splickit_accepted_payment_type_id'] != 2000 && $merchant_payment_type_map['splickit_accepted_payment_type_id'] != 1000) {
                return true;
            }
        }
    }

    function getCateringTipArrayForMinimum($order_amount,$minimum_tip_percent)
    {
        $tip_array[] = array('No Tip' => 0.00);
        $i = $minimum_tip_percent;
        while ($i < 41) {
            $tip_array[] = array($i . "%" => number_format($order_amount * $i / 100, 2));
            $i = $i + 5;
        }
        return $tip_array;
    }

    function createTipArray($tip_minimum_trigger_amount, $tip_minimum_percentage, $subtotal)
    {
        $tip_array = array();
        if ($tip_minimum_trigger_amount <= $subtotal && $tip_minimum_percentage != 0) {
            $this->minimum_tip_requirement_exists_for_this_order = true;
            $i = $tip_minimum_percentage;
        } else {
            $tip_array[] = array('No Tip' => 0.00);
            $i = 10;
        }
        while ($i < 25) {
            $tip_array[] = array($i . "%" => number_format($subtotal * $i / 100, 2));
            $i = $i + 5;
        }
        for ($j = 1; $j < 26; $j++) {
            $dollar_amount = '$' . number_format($j, 2);
            $tip_array[] = array($dollar_amount => $j);
        }
       if ($this->merchant_id['merchant_id'] == 1103 && sizeof($tip_array) > 2) {
            $tip_array[] = ['$2.50'=>2.50];
            $tip_array[] = ['$5.50'=>5.50];
        }
        return $tip_array;
    }

    /**
     *
     * @desc used to place an order from the APIv1 dispatch page with a request
     *
     * @return Resource
     */
    function placeOrderFromRequest()
    {
        $this->request->url_hash["checkout"] = true;
        if (!$this->order) {
            $cart_resource = $this->createNewCart($this->request->data);
            if ($cart_resource->hasError()) {
                return $cart_resource;
            }
            $this->setOrderAndMerchantByUcid($cart_resource->ucid);
            if ($error_resource = $this->doSubmittedItemValidations($this->request->data['items'])) {
                return $error_resource;
            }
            $checkout_resource = $this->processCartPostItems($this->request->data['items']);
        } else {
            $checkout_resource = $this->processCartGet();
        }
        if ($checkout_resource->hasError()) {
            return $checkout_resource;
        }
        $this->order = new OrderMaster($checkout_resource->ucid);
        unset($this->request->data['items']);
        if ($error_resource = $this->doOrdersEndpointValidations()) {
            return $error_resource;
        }
        return  $this->newPlaceOrder($this->order, $this->request->data);
    }

    function getCartIdForTypeOneGroupOrderIfAdmin($existing_cart_order_id)
    {
        if ($this->isGroupOrder()) {
            if ($this->group_order_record['group_order_type'] == 1) {
                if ($this->user['user_id'] == $this->group_order_record['admin_user_id']) {
                    if ($existing_cart_order_id == $this->group_order_record['order_id']) {
                        return $this->group_order_record['group_order_token'];
                    }
                }
            }
        }
    }

    function getPickupTimeStampFromSubmittedData($data)
    {
        // check to see if there is a actual pickupt time submitted
        if ($pickup_time_stamp = isset($data['actual_pickup_time']) ? $data['actual_pickup_time'] : $data['requested_time']) {
            if (is_numeric($pickup_time_stamp)) {
                return $pickup_time_stamp;
            } else {
                // let lead time logic work?  this is probably an as soon as possible submission
                myerror_log("Non numeric actual_pickup_time submitted: " . $pickup_time_stamp);
                if ($this->order->isDeliveryOrder() && substr(strtolower($pickup_time_stamp), 0, 19) == 'as soon as possible') {
                    $lead_time = $this->getDeliveryMinimumLeadTimeForMerchant($this->merchant['merchant_id']);
                    return $this->current_time + ($lead_time * 60);
                }
            }
        }
        // for backwards compatability
        $lead_time = isset($data['lead_time']) ? $data['lead_time'] : $this->merchant['lead_time'];
        $pickup_time_stamp = $this->current_time + ($lead_time * 60);
        return $pickup_time_stamp;
    }

    function getDeliveryMinimumLeadTimeForMerchant($merchant_id)
    {
        $mdi_adapter = new MerchantDeliveryInfoAdapter();
        $mdi_resource = $mdi_adapter->getFullMerchantDeliveryInfoAsResource($merchant_id);
        return $mdi_resource->minimum_delivery_time;
    }

    /**
     * @desc Used to auto submit an exising order. Generally a Type 1 group order.
     * @return Resource
     */
    function newPlaceOrderFromLoadedOrder($additional_data)
    {
        $merchant_payment_type_maps_adapter = new MerchantPaymentTypeMapsAdapter();
        if (! isset($additional_data['merchant_payment_type_map_id'])) {
            $merchant_payment_type_map = $merchant_payment_type_maps_adapter->getRecord(array("merchant_id" => $this->merchant['merchant_id'], "splickit_accepted_payment_type_id" => SplickitPaymentService::CREDITCARDSPLICKITPAYMENTID));
            $additional_data['merchant_payment_type_map_id'] = $merchant_payment_type_map['id'];
        }
        if (! isset($additional_data['requested_time'])) {
            $additional_data['requested_time'] = $this->current_time + (45 * 60);
        }

        return $this->newPlaceOrder($this->order,$additional_data);
    }

    /**
     * @param $order Order
     * @param $place_order_request_data array
     *
     * @return Resource
     */

    private function newPlaceOrder($order, $place_order_request_data)
    {
        $this->isPlaceOrder = true;
        $time1 = microtime(true);
        $skin = getSkinForContext();
        if (stripos($place_order_request_data['note'], 'skip hours') !== false) {
            $order->setSkipHours(true);
        }
        if(!$skin['show_notes_fields']) {
            unset($place_order_request_data['note']);
        }
        if ($place_order_request_data['dine_in'] == true) {
            $place_order_request_data['note'] = 'XXX DINE IN XXX - '.$place_order_request_data['note'];
        } else if ($place_order_request_data['curbside_pickup'] == true) {
            $car = $place_order_request_data['car_make'];
            $color = $place_order_request_data['car_color'];
            $place_order_request_data['note'] = "Curbside Pickup! $color $car - ".$place_order_request_data['note'];
        }
        $merchant = $this->merchant;
        $merchant_id = $merchant['merchant_id'];
        try {
            $merchant_payment_type_map_id = (isset($place_order_request_data['merchant_payment_type_map_id'])) ? $place_order_request_data['merchant_payment_type_map_id'] : $this->getMerchantPaymentTypeMapIdFromOrderData($place_order_request_data);
            $payment_service = PaymentGod::paymentServiceFactoryByMerchantPaymentTypeMapId($merchant_payment_type_map_id, null);
        } catch (NoMatchingMerchantPaymentTypeMapForOrderDataException $e) {
            myerror_log("we have an old style order and no record for this merchant in the merchant_payment_type_maps table so create the dummy service that will process the order");
            $payment_service = new DummyPaymentService(array("merchant" => $this->merchant));
        } catch (CashSubittedForNonCashMerchantException $e) {
            MailIt::sendErrorEmail("ERROR! Cash order submitted to non cash merchant", "merchant_id: $merchant_id");
            return createErrorResourceWithHttpCode("We're sorry, there appears to be an error in the system. This order was submitted as pay in store but this merchant does not accept mobile orders with pay in store. Please try reloading and submitting your order again!", 422, 545, null);
        } catch (BillingException $e) {
            myerror_log("Billing exception thrown: " . $e->getMessage());
            return createErrorResourceWithHttpCode("We're sorry but there is a problem with this merchant's billing setup and orders cannot be processed. We have been alerted and will take care of the error shortly. Sorry for the inconvenience.", 500, 500);
        }

        if ($payment_service->isCashTypePaymentService() || $this->user['line_buster_merchant_id'] > 1000) {
            $cash_bool = true;
            if ($place_order_request_data['tip'] > 0.00) {
                if ($this->user['line_buster_merchant_id'] > 1000) {
                    myerror_log("accidental tip with line buster, just set to zero and pass it through");
                } else {
                    return createErrorResourceWithHttpCode($this->cant_tip_because_cash_message, 422, 541);
                }
            } else {
                $place_order_request_data['tip'] = 0.00;
            }
        } else {
            if ($error_resource = $this->validateTip($order->get('order_amt'), $place_order_request_data['tip'], $this->merchant['tip_minimum_trigger_amount'], $this->merchant['tip_minimum_percentage'])) {
                return $error_resource;
            }
        }

        if (is_a($payment_service, 'VioPaymentService')) {
            if (($order->get('order_amt') + $place_order_request_data['tip']) > 0.00 && substr($this->user['flags'], 1, 1) != 'C') {
                myerror_log("NO CREDIT CARD ON FILE REJECT THE ORDER");
                UserNoCCFailureAdapter::createFailRecord($this->user['user_id'],0);
                return createErrorResourceWithHttpCode('Please enter your credit card info', 400, 150);
            } /*  else if ($use_gift == true && $order->get('grand_total') > $this->user['gift_resource']->amt && $this->user['gift_flags_set'] == 'true') {
                myerror_log("AMOUNT GREATER THAN GIFT WITH NO CREDIT CARD ON FILE. REJECT THE ORDER");
                return createErrorResourceWithHttpCode('Your purchase amount is greater than your gift. Please enter your credit card info', 400, 150);
            }*/
        }
        $payment_service->loadAdditionalDataFieldsIfNeeded($place_order_request_data);

        // check lead time stuff
        $hour_adapter = new HourAdapter($this->mimetypes);
        $hour_adapter->setCurrentTime($this->current_time);
        $hour_type = $order->get('order_type');
        $pickup_ts = $this->getPickupTimeStampFromSubmittedData($place_order_request_data);
        $local_pickup_time = getMySqlFormattedDateTimeFromTimeStampAndTimeZone($pickup_ts, getTheTimeZoneStringFromOffset($this->merchant['time_zone'], $this->merchant['state']));
        $local_current_time = getMySqlFormattedDateTimeFromTimeStampAndTimeZone($this->current_time, getTheTimeZoneStringFromOffset($this->merchant['time_zone'], $this->merchant['state']));

        myerror_logging(3, 'local_pickup_time: ' . $local_pickup_time);
        myerror_logging(3, 'local_current_time: ' . $local_current_time);

        // calculate lead time and check its relavancy.  have to do this since SP takes a lead time in minutes.
        $lead_time = round(($pickup_ts - $this->current_time) / 60);
        myerror_log("the submitted lead time is $lead_time minutes based on the submitted pickup timestamp: $pickup_ts", 3);
        $two_weeks_in_minutes = 14 * 24 * 60;
        if ($lead_time > $two_weeks_in_minutes) {
            recordError("LEAD TIME ERROR", "a lead time of $lead_time minutes was submitted");
            return createErrorResourceWithHttpCode("Sorry, there was an error with your submitted data, please try again", 422, 422);
        }

        $local_order_time = getMySqlFormattedDateTimeFromTimeStampAndTimeZone($this->current_time, getTheTimeZoneStringFromOffset($merchant['time_zone'], $merchant['state']));
        $this->lead_time_object = new LeadTime($this->merchant_resource);
        if ($hour_type == 'R') {
            $elevel_num = $order->getNumberOfEntreLevelItems();

            // base min is min
            $min_lead_time_for_this_order = $this->lead_time_object->getPickupLeadtime($this->current_time);
            myerror_log("minimum lead time for this order without order data is: $min_lead_time_for_this_order", 3);
            if ($elevel_num > 4) {
                myerror_logging(3, "ok.  we have more than 4 E level items.");
                $additional_item_count = $elevel_num - 4;
                $min_lead_time_for_this_order = LeadTime::getMinLeadTimeForLargePickupOrder($additional_item_count, $min_lead_time_for_this_order);
                myerror_logging(3, "min lead time for this order: " . $min_lead_time_for_this_order);
                myerror_logging(3, "submitted lead time with order: " . $lead_time);
            }

        }

        if (!$order->skipHours() && $lead_time < $min_lead_time_for_this_order) {
            $diff = $min_lead_time_for_this_order - $lead_time;
            $this->order_lead_differential_between_submitted_and_minimum = $diff;
            // first check to see if they sat on checkout screen too long. if so, just pass the order in to the next available time if its not too far in the future.
            if ($diff <= 4) {
                //now determin if its less than 5 minutes, if so change the $lead_time, $pickup_ts, and $local_pickup_time
                $lead_time = $min_lead_time_for_this_order;
                $pickup_ts = $this->current_time + ($lead_time * 60);
                $local_pickup_time = getMySqlFormattedDateTimeFromTimeStampAndTimeZone($pickup_ts, getTheTimeZoneStringFromOffset($merchant['time_zone'], $merchant['state']));
            } else {
                myerror_log("ERROR! we have a short lead time! reject the order");
                return $this->getShortLeadTimeErrorResponse($min_lead_time_for_this_order, $elevel_num);
            }
        }

        if ($hour_adapter->isMerchantOpenAtThisTime($this->merchant['merchant_id'], $this->merchant['time_zone'], $hour_type, $pickup_ts)) {
            myerror_log("MERCHANT IS OPEN!");
        } else if (!$order->skipHours()) {
            myerror_log("MERCHANT IS CLOSED!");
            $error_message = $hour_adapter->getMerchantStatusMessage();
            if ($order->isDeliveryOrder()) {
                $error_message = "We're sorry, this merchant is closed at your requested delivery time.";
                if (substr_count(strtolower(trim($place_order_request_data['delivery_time'])), 'as soon as possible') > 0) {
                    $error_message = "We're sorry, this merchant is currently out of delivery hours, and cannot deliver 'As soon as possible'. Please choose a delivery time from the drop down list.";
                }
            }
            return createErrorResourceWithHttpCode($error_message, 422, 540);
        }

        myerror_log("order times set update the order object with teh new values");

        if ($order->isDeliveryOrder()) {
            $merchant_delivery_price_distance_id = $order->get('merchant_delivery_price_distance_id');
            if ($this->isDoordashDeliveryService($merchant_delivery_price_distance_id)) {
                $complete_order = CompleteOrder::staticGetCompleteOrder($order->getOrderId());
                $doordash_service = new DoordashService();
                if ($doordash_service->requestDelivery($complete_order)) {
                    $doordash_response = $doordash_service->getDoordashResponse();
                    $doordash_delivery_id = $doordash_response['id'];
                } else {
                    $door_dash_error_string = $doordash_service->getErrorFromCurlResponse();
                    myerror_log("DOOR DASH REQUEST DELIVERY ERROR: $door_dash_error_string");
                    return createErrorResourceWithHttpCode(DeliveryController::DOORDASH_CANNOT_DELIVER_MESSAGE, 422, 999);
                }
            }
        }



        $order_resource = $order->getOrderResource();
        $order_resource->note = $place_order_request_data['note'];

//CHANGE THIS
        // BRINK SHIT!!!!!  need to start creating child object with custom code
        if (($this->merchant['brand_id'] == 282 || $this->merchant['brand_id'] == 152  || $this->merchant['brand_id'] == 150 )&& MerchantBrinkInfoMapsAdapter::isMechantBrinkMerchant($merchant_id)) {
            // get correct tax amount from brink
            $brink_controller = new BrinkController(getM(),null,new Request());
            try {
                $brink_controller->setBrinkCheckoutInfoOnOrderResource($order_resource);
            } catch (UnsuccessfulBrinkPushException $e) {
                return createErrorResourceWithHttpCode(PlaceOrderController::REMOTE_SYSTEM_CHECKOUT_INFO_ERROR_MESSAGE, 500, 500);
            }
        }
        $order_resource->order_dt_tm = $local_order_time;
        $order_resource->pickup_dt_tm = $local_pickup_time;
        if ($doordash_delivery_id) {
            $order_resource->pickup_dt_tm = date('Y-m-d H:i:s',$complete_order['ready_timestamp']);
        }

        $tip_sent = false;
        foreach ($place_order_request_data as $name=>$value) {
            if ($name == 'tip') {
                $tip_sent = true;
                break;
            }
        }
        if ($tip_sent && $order_resource->tip_amt != $place_order_request_data['tip']) {
            $diff = $place_order_request_data['tip'] - $order_resource->tip_amt;
            $order_resource->grand_total = $order_resource->grand_total + $diff;
            $order_resource->tip_amt = $place_order_request_data['tip'];
        }

        if (!$payment_service->isCashTypePaymentService() && !$this->isAddToTypeOneGroupOrder()) {
            if ($this->isRoundupActiveForSkinAndUser(getSkinForContext(),$order->getUserId())) {
                $round_up_amount = $this->calculateRoundUpAmountForOrder($order_resource);
                $order_resource->customer_donation_amt = $round_up_amount;
                $order_resource->grand_total = $order_resource->grand_total + $round_up_amount;
            }
        }



        // do payment
        /*
 * we need to update the grand total to merchant based on several things.  trans fee, promo, etc...
 */
        $order_resource->grand_total_to_merchant = $order_resource->grand_total;
        if ($order_resource->promo_amt < 0.00) {
            // if splickit it paying for hte promo, then add the value back on to the grand total to help calculate grand total to merchant
            $promo = Resource::find(new PromoAdapter(),"".$order_resource->promo_id);
            $payor_merchant_user_id = $promo->payor_merchant_user_id;
            myerror_logging(3, "promo amt: " . $order_resource->promo_amt . "      payor: " . $payor_merchant_user_id);
            if ($payor_merchant_user_id == 1)
                $order_resource->grand_total_to_merchant = $order_resource->grand_total_to_merchant + (-$order_resource->promo_amt);
        }

        $order_resource->grand_total_to_merchant = $order_resource->grand_total_to_merchant - $order_resource->trans_fee_amt - $order_resource->tip_amt - $order_resource->customer_donation_amt;


        $amount_to_bill = $order_resource->grand_total;

        // save current amounts
        $order_resource->save();

        if ($this->user['balance'] > $order_resource->grand_total || is_a($payment_service,'AccountCreditPaymentService')) {
            // always use credit if balance is greater thatn total
            if (!is_a($payment_service,'AccountCreditPaymentService')) {
                $payment_service = new AccountCreditPaymentService(array());
            }
        } else if ($this->user['balance'] > 0 && $this->user['balance'] < $order_resource->grand_total) {
            // if there is a credit on the account that is less than the order, set amount to bill to be the differenc
            $amount_to_bill = $order_resource->grand_total - $this->user['balance'];
        } else if (is_a($payment_service, "VioPaymentService")) {
            // if we are paying by credit card and there is an existing negative balance, then add that to the amount to run the card for to clear the account.
            $amount_to_bill = -($this->user['balance'] - $order_resource->grand_total);
        }
        if (!is_a($payment_service, "VioPaymentService")) {
            // gifts can only be used for cc payments
            unset($gift_resource);
        }
        // *********  end confusing bit ***********

        $amount_to_bill = number_format($amount_to_bill, 2, '.', '');
        if ($amount_to_bill == 0.00) {
            // easiest way to make sure we dont blow up on zero amounts
            $payment_service = new AccountCreditPaymentService(array());
        }
        try {
            $payment_service->calling_action = 'PlaceOrder';
            $payment_results = $payment_service->processOrderPayment($order_resource, $amount_to_bill, $gift_resource);
        } catch (NoCreditCardOnFileBillingException $nccofbe) {
            $payment_results['response_text'] = $nccofbe->getMessage();
            $payment_results['response_code'] = 422;
        } catch (BillingEntityException $bee) {
            MailIt::sendErrorEmailSupport("Serious Merchant Billing SETUP Error!", $bee->getMessage());
            $payment_results['response_text'] = "We're sorry but there is a problem with this merchant's billing setup and orders cannot be processed. We have been alerted and will take care of the error shortly. Sorry for the inconvenience.";
            $payment_results['response_code'] = 500;
        } catch (FailedLoyaltyRedemptionException $be) {
            $payment_results['response_text'] = $be->getMessage();
            $payment_results['response_code'] = 500;
        } catch (BillingException $be) {
            MailIt::sendErrorEmail("Serious Billing Error!", $be->getMessage());
            $payment_results['response_text'] = "We're sorry but there is an unknown error in billing and we cannot process your payment. We have been alerted and will take care of the error shortly. Sorry for the inconvenience.";
            $payment_results['response_code'] = 500;
        } catch (Exception $e) {
            MailIt::sendErrorEmail("Serious Billing Error!", $e->getMessage());
            $payment_results['response_text'] = "We're sorry but there is a problem with this merchant's billing setup and orders cannot be processed. We have been alerted and will take care of the error shortly. Sorry for the inconvenience.";
            $payment_results['response_code'] = 500;
        }
        $this->payment_service = $payment_service;
        if ($payment_results['response_code'] != 100) {
            $data = array("cancelled_order_id"=>$order_resource->order_id);
            $code = $payment_results['response_code'] == 500 ? 500 : 422;
            // we may need to cancel the doordash order here
            if ($doordash_delivery_id) {
                // cancel door dash delivery
                $doordash_cancel_result = $doordash_service->cancelDeliveryRequest($doordash_delivery_id);
                if ($doordash_cancel_result === true) {
                    $order_resource->note .= '---Doordash Delivery Cancelled:'.$doordash_delivery_id;
                } else {
                    $doordash_response = $doordash_service->getDoordashResponse();
                    $json_string = json_encode($doordash_response);
                    myerror_log("ERROR!!!!! CANNOT CANCEL DOORDASH Delivery after failed CC charge. order_id: ".$order->getOrderId().".  DD response: $json_string");
                    MailIt::sendErrorEmail("CANNOT CANCEL DOORDASH DELIVERY REQUEST","Cannot cancel Doordash Delivery Request after failed CC charge. order_id: ".$order->getOrderId()."\r\n".$json_string);
                    MailIt::sendErrorEmailSupport("CANNOT CANCEL DOORDASH DELIVERY REQUEST","Cannot cancel Doordash Delivery Request after failed CC charge. order_id: ".$order->getOrderId());
                }
            }
            return createErrorResourceWithHttpCode($payment_results['response_text'], $code, $code, $data);
        } else {
            // billing was successfull so set the order to 'O'
            myerror_log("billing was successfull so set the order to 'O'");
            $order_resource->status = OrderAdapter::ORDER_SUBMITTED;
            $order_resource->payment_file = 'nullit';
            if ($doordash_delivery_id) {
                $order_resource->note .= '---Doordash Delivery Id:'.$doordash_delivery_id;
            }

        }
        $order_resource->set('payment_service_used', $payment_service->name);
        if ($gift_resource) {
            $this->stageGiftEmail($gift_resource);
        }

        if ($payment_service->isCashTypePaymentService()) {
            $order_resource->cash = 'Y';
        } else {
            $order_resource->cash = 'N';
        }


        // get the user_resource from the payment service since any changes to it were done there

        $user_resource = $payment_service->billing_user_resource;
        $user_resource->orders = $user_resource->orders + 1;
        $user_id = $user_resource->user_id;
        $user_resource->last_order_merchant_id = $merchant_id;
        if ($user_resource->save()) {
            myerror_log("successful update of user values in place order controller");
        } else {
            myerror_log("ERROR*****************  UNABLE TO UPDATE USER VALUES, user_id = $user_id, AFTER RUNNING THEIR CREDIT CARD: " . $user_resource->_adapter->getLastErrorText());
            MailIt::sendErrorEMail('Error thrown in PlaceOrderController', 'ERROR*****************  UNABLE TO UPDATE USER VALUES, user_id = ' . $user_id . ', AFTER RUNNING THEIR CREDIT CARD: ' . $user_resource->_adapter->getLastErrorText());
        }

        $pickup_time = date('g:ia', strtotime($order_resource->pickup_dt_tm));
        $pickup_date_time = date('l g:ia', strtotime($order_resource->pickup_dt_tm));
        myerror_log("pickup_date_time: " . $pickup_date_time);

        $submit_time_string = date('g:ia', strtotime($order_resource->pickup_dt_tm) - (45 * 60));

        $order_day = date('z', strtotime($order_resource->order_dt_tm));
        $pickup_day = date('z', strtotime($order_resource->pickup_dt_tm));
        $pickup_string = $order_day != $pickup_day ? $pickup_date_time : $pickup_time;
        if ($order->isDeliveryOrder()) {
            $user_message = "Your order to " . $this->merchant['name'] . " has been scheduled for delivery at ";

            if (isset($place_order_request_data['delivery_time']) && substr_count(strtolower($place_order_request_data['delivery_time']), 'possible') > 0) {
                $order_resource->requested_delivery_time = $place_order_request_data['delivery_time'];
            } else if (is_numeric($place_order_request_data['requested_time'])) {
                $order_resource->requested_delivery_time = getTimeStringForUnixTimeStampInMerchantLocal('D m/d g:i A', $place_order_request_data['requested_time'], $this->merchant);
            } else {
                $order_resource->requested_delivery_time = $place_order_request_data['requested_time'];
            }
            if (substr_count(strtolower($order_resource->requested_delivery_time), 'possible') > 0) {
                $user_message = str_replace("delivery at ", "delivery " . strtolower($order_resource->requested_delivery_time), $user_message . ".");

            }
            $order_resource->set('user_message', "$user_message $pickup_string");
            $order_resource->set('order_ready_time_string',$order_resource->requested_delivery_time); // new single field value
            if ($user_delivery_resource = Resource::find(new UserDeliveryLocationAdapter(),$order_resource->user_delivery_location_id)) {
                $user_delivery_resource->cleanResource();
                $ud_record = $user_delivery_resource->getDataFieldsReally();
            } else {
                $error_message_subject = "We have a logically deleted delivery address on a placed order";
                $error_message_body = "order_id: ".$order_resource->order_id."  user_address_id: ".$order_resource->user_delivery_location_id."  user_id: ".$user_id;
                recordError($error_message_subject,$error_message_body);
                MailIt::sendErrorEmailSupport($error_message_subject,$error_message_body);
                $order_resource->user_delivery_location_id = 100;
                $ud_record = UserDeliveryLocationAdapter::staticGetRecord(['user_addr_id'=>100],'UserDeliveryLocationAdapter');
            }
            unset($ud_record['created']);
            unset($ud_record['modified']);
            $order_resource->set("user_delivery_address",$ud_record);
        } else {
            $user_message = "Your order to " . $merchant['name'] . " will be ready for pickup at ";
            $order_resource->set('pickup_time_string', $pickup_string);
            $order_resource->set('order_ready_time_string',$pickup_string); // new single field value
            $order_resource->set('user_message', "$user_message $pickup_string");
        }
        $order_resource->set('user_message_title','Order Info');

        $order_resource->save();
        $this->order = new OrderMaster($order_resource->order_id);

        myerror_log("number of entres: " . $this->order->getNumberOfEntreLevelItems() . ".   number of catering items: " . $this->order->getNumberOfCateringItems());

        if ($this->order->isOrderInSubmittedState()) {
            if ($this->order->isOverSizedEntreOrder() && $order_resource->user_id > 1000) {
                $message_leadtime_for_order = $this->getMessageLeadTimeForLargeOrderAndSetMessageOnReturnedResourceIfNeeded($order_resource,$lead_time,$min_lead_time_for_this_order);
            } else {
                $message_leadtime_for_order = $min_lead_time_for_this_order;
            }

            if ($this->isUserAGuest()) {
                $order_resource->user_type = "Guest";
                $order_resource->save();
            }

            $this->processPromoSystemAmountsForOrder($this->order);

            $this->createMessagesForOrder($this->order,$message_leadtime_for_order,$pickup_ts);

            if ($this->isTypeTwoGroupOrder()) {
                $participant_submit_to_group_order = true;
                $this->processSuccessfullAddToTypeTwoGroupOrder($order_resource);
            } else {
                $this->closeOutGroupOrderIfItExists($order_resource->ucid);
                $this->setAdditionalMessagesOnReturnedResourceIfNeeded($order_resource);
            }

            $complete_order = CompleteOrder::staticGetCompleteOrder($order_resource->order_id);

            $this->processLoyaltyForOrder($order_resource, $complete_order);

            //add items to the response
            $order_summary = $complete_order['order_summary'];
            $order_resource->set('order_summary', $order_summary);
            $complete_order['participant_submit_to_group_order'] = $participant_submit_to_group_order ? true : false;

            $this->buildAndStageConfirmationEmailFromCompleteOrderData($complete_order);
            $this->setTwitterAndFacebookStuffOnOrderResource($order_resource, $this->merchant, getSkinForContext());

            if ($this->order->isCateringOrder()) {
                $catering_orders_adapter = new CateringOrdersAdapter();
                $catering_orders_adapter->setCateringOrderToSubmitted($order_resource->order_id);
            }

            UserBrandMapsAdapter::incrementOrderCountForUserBrand($order_resource->user_id,$merchant['brand_id']);
        } else {
            myerror_logging(3, "order status is NOT 'O' so we are skipping processing");
        }

        myerror_log("Time for full processing of order is: " . getElapsedTimeFormatted($time1));

        // testing hack for delivery check
        if ($this->order->isDeliveryOrder()) {
            $order_resource->set("delivery_tax_amount", $this->order->get('delivery_tax_amt'));
        }
        if (isset($place_order_request_data['requested_time_string'])){
            $order_resource->set("requested_time_string",$place_order_request_data['requested_time_string']);
        } else {
            $order_resource->set("requested_time_string",$local_pickup_time);
        }

        return $order_resource;
    }

    /**
     * @param $merchant_delivery_price_distance_id
     * @return bool
     */
    function isDoordashDeliveryService($merchant_delivery_price_distance_id)
    {
        if ($merchant_delivery_price_distance_id > 0) {
            if ($merchant_delivery_price_distance_record = MerchantDeliveryPriceDistanceAdapter::staticGetRecordByPrimaryKey($merchant_delivery_price_distance_id,'MerchantDeliveryPriceDistanceAdapter')) {
                return strtolower($merchant_delivery_price_distance_record['name']) == 'doordash';
            }
        }
        return false;
    }

    function getMessageLeadTimeForLargeOrderAndSetMessageOnReturnedResourceIfNeeded(&$order_resource,$order_lead_time,$order_min) {
        myerror_log( "lead time is: " . $order_lead_time,3);
        myerror_log( "minimum for this order is: " . $order_min,3);

        if ($order_lead_time < $order_min) {
            $user_message = "Your order to " . $this->merchant['name'] . " is confirmed.  Please note, due to the number of items ordered, allow extra time for preparation";
            $order_resource->set('user_message', $user_message);
            $order_resource->set('additional_message', $user_message);
            $message_leadtime_for_order = $order_min;
        } else {
            $message_leadtime_for_order = $order_min;
        }
        return $message_leadtime_for_order;
    }

    function setAdditionalMessagesOnReturnedResourceIfNeeded(&$order_resource) {
        if ($order_resource->order_type == 'D' && $order_resource->tip_amt == '0.00' && $order_resource->cash == 'N' && $this->merchant['brand_id'] != 430) {
            $user_message = $order_resource->user_message . '. Your credit card has already been run, so if you\'d like to leave a tip, please use cash!';
            $order_resource->set('user_message', $user_message);
            $order_resource->set('additional_message', 'Your credit card has already been run, so if you\'d like to leave a tip, please have cash!');
            //$message = 'Your credit card has already been run, so if you\'d like to leave a tip, please have cash!';
            //PushMessageController::pushMessageToUser($resource->user_id, $message);
        }

        $skin = getSkinForContext();
        if ($order_resource->customer_donation_amt == '0.00' && $skin['donation_active'] == 'Y' && $order_resource->cash == 'N') {
            if (!isset($order_resource->additional_message)) {
                $order_resource->set('additional_message', 'Hey! Did you know that you can choose to donate a small amount with each order to help make a big difference? Tap here for details.');
                $order_resource->set('additional_message_type', 'charity');
            }
        }
    }

    function processSuccessfullAddToTypeTwoGroupOrder($order_resource)
    {
        $order_resource->status = OrderAdapter::GROUP_ORDER;
        $order_resource->save();
        if ($gom_resource = $this->addCompletedOrderToGroup($order_resource->order_id, $this->group_order_record['group_order_id'])) {
            myerror_log("Successful adding of order to group order map");
            $this->updateTotalsOfGroupOrder($order_resource, $this->group_order_record);
        } else {
            throw new Exception("Problem creating grouop order map record. Could not add record to map table");
        }
    }

    function processPromoSystemAmountsForOrder($order)
    {
        if ($this->doesOrderConatainExecutedPromo($order)) {
            // set amounts on user and promo
            $this->doExecutedPromoStuff($order->getOrderResource());
        }
    }

    function processLoyaltyForOrder(&$order_resource, $complete_order)
    {
        try {
            if ($this->loyalty_controller = $this->getLoyaltyController($this->user)) {
                // so we could do something to fail the order her if the remote system denies the charge? but since we get the info on the session, maybe dont worry about it
                // and assume out system has the true value?  super corner case.
                myerror_log("we have the loyalty controler: " . get_class($this->loyalty_controller));
                if ($this->loyalty_controller->processOrderFromCompleteOrder($complete_order)) {
                    if ($loyalty_result_message = $this->loyalty_controller->message) {
                        $order_resource->set("loyalty_message", $loyalty_result_message);
                    }

                    $order_resource->set("loyalty_earned_label", $this->loyalty_controller->getLoyaltyEarnedLabel());
                    $order_resource->set("loyalty_earned_message", $this->loyalty_controller->getLoyaltyEarnedMessage());

                    $order_resource->set("loyalty_balance_label", $this->loyalty_controller->getLoyaltyBalanceLabel());
                    $order_resource->set("loyalty_balance_message", $this->loyalty_controller->getLoyaltyBalanceMessage());
                }

            }
        } catch (MoreThanOneMatchingRecordException $e) {
            myerror_log("MORE THEN ONE MATCHNIG ROW EXCEPTION. loyalty returned more than one matching row exception. user_id: " . $order_resource->user_id);
            recordError("MORE THEN ONE MATCHNIG ROW EXCEPTION", "loyalty returned more than one matching row exception. user_id: " . $order_resource->user_id);
        }
    }

    /**
     * @param $order Order
     */
    function doesOrderConatainExecutedPromo($order)
    {
        return $order->get('promo_id') > 0 && $order->get('status') == OrderAdapter::ORDER_SUBMITTED && $order->get('promo_amt') < 0.00;
    }

    function doExecutedPromoStuff($resource)
    {
        if ($resource->user_id > 19999) {
            // first get promo and update the record with the new values
            myerror_logging(3, "starting the promo code in place order controller");
            if ($promo_record_resource =& Resource::find(new PromoAdapter($this->mimetypes), '' . $resource->promo_id)) {
                $promo_record_resource->current_number_of_redemptions = $promo_record_resource->current_number_of_redemptions + 1;
                $promo_record_resource->current_dollars_spent = $promo_record_resource->current_dollars_spent - $resource->promo_amt;
                $promo_record_resource->modified = time();
                // now check to see if we need to deactivate the promo
                if ($promo_record_resource->max_dollars_to_spend != 0.00 && $promo_record_resource->current_dollars_spent > $promo_record_resource->max_dollars_to_spend)
                    $promo_record_resource->active = 'N';
                if ($promo_record_resource->max_redemption != 0 && $promo_record_resource->current_number_of_redemptions > $promo_record_resource->max_redemption)
                    $promo_record_resource->active = 'N';

                if (!$promo_record_resource->save()) {
                    myerror_log("THERE WAS AN ERROR UPDATING THE PROMO TABLE WITH THE CURRENT REDEMPTION INFORMATAION!");
                    MailIt::sendErrorEMail('THERE WAS AN ERROR UPDATING THE PROMO TABLE WITH THE CURRENT REDEMPTION INFORMATAION!', 'you heard me: ' . $promo_record_resource->getAdapterError());
                }

                // update or add a row to the Promo_User_Table.  except in teh case of unlimited promo.  max_use == 0
                if ($promo_record_resource->max_use > 0) {
                    $puma = new PromoUserMapAdapter($this->mimetypes);
                    $data = array('user_id' => $resource->user_id, 'promo_id' => $resource->promo_id);

                    $puma_options[TONIC_FIND_BY_METADATA] = $data;
                    if ($puma_resource =& Resource::findExact($puma, '', $puma_options)) {
                        $puma_resource->times_used = $puma_resource->times_used + 1;// we got it
                    } else {
                        $puma_resource = Resource::factory($puma, $data);
                        $puma_resource->set('times_used', 1);
                        $puma_resource->set('end_date', $promo_record_resource->end_date);
                        $puma_resource->set('times_allowed', $promo_record_resource->max_use);
                    }
                    $puma_resource->modified = time();
                    if ($puma_resource->save())
                        ; // all is good
                    else {
                        myerror_log("THERE WAS AN ERROR UPDATING THE PROMO_USER_MAP TABLE!");
                        MailIt::sendErrorEMail('error updating promo user map record in place order controller', 'you heard me: ' . $puma->getLastErrorText());
                    }
                } else {
                    myerror_logging(1, "DO NOT ADD ROW TO promo_user_map, promo has no max_use");
                }

            } else {
                myerror_log("THERE WAS A SERIOUS ERROR GETTING THE PROMO RECORD AFTER THE ORDER CREATION IN PLACEORDERCONTROLLER!");
                MailIt::sendErrorEMail('THERE WAS A SERIOUS ERROR GETTING THE PROMO RECORD AFTER THE ORDER CREATION IN PLACEORDERCONTROLLER!', 'you heard me');
            }
        }
    }


    function getShortLeadTimeErrorResponse($min_lead_time_for_this_order, $elevel_num)
    {
        // create some buffer
        $min_lead_time_for_this_order = $min_lead_time_for_this_order + 2;
        //now get local minimum pickup time
        $tz = date_default_timezone_get();
        $time_zone = $this->merchant['time_zone'];
        $merchant_tz = getTheTimeZoneStringFromOffset($time_zone);
        date_default_timezone_set($merchant_tz);
        $min_pickup_time_stamp = $this->current_time + ($min_lead_time_for_this_order * 60);
        $local_pickup_time_string = date("g:i a", $min_pickup_time_stamp);
        date_default_timezone_set($tz);
        if ($elevel_num > 4) {
            $error_message = "ORDER ERROR! We're sorry, but the size of your order requires a minimum preptime of $min_lead_time_for_this_order minutes. Please choose a pickup time of " . $local_pickup_time_string . " or later.";
            myerror_log($error_message);
        } else {
            myerror_log("Expired minimum lead time by: " . $this->order_lead_differential_between_submitted_and_minimum . " minutes");
            $error_message = "ORDER ERROR! Your pickup time has expired. Please select a new pickup time and proceed to check out.";
        }
        return createErrorResourceWithHttpCode($error_message, 422, 422);
    }


    function addCompletedOrderToGroup($order_id, $group_order_id)
    {
        $goioma = new GroupOrderIndividualOrderMapsAdapter();
        return $goioma->addCompletedOrderToGroup($order_id, $group_order_id);
    }

    function updateTotalsOfGroupOrder($resource, $group_order_record)
    {
        $parent_order_resource = Resource::find(new CartsAdapter(), $group_order_record['group_order_token']);
        $parent_order_resource->order_amt = $parent_order_resource->order_amt + $resource->order_amt;
        $parent_order_resource->total_tax_amt = $parent_order_resource->total_tax_amt + $resource->total_tax_amt;
        $parent_order_resource->tip_amt = $parent_order_resource->tip_amt + $resource->tip_amt;
        $parent_order_resource->grand_total = $parent_order_resource->grand_total + $resource->grand_total;
        $parent_order_resource->grand_total_to_merchant = $parent_order_resource->grand_total_to_merchant + $resource->grand_total_to_merchant;
        $parent_order_resource->order_qty = $parent_order_resource->order_qty + $resource->order_qty;
        $parent_order_resource->promo_amt = $parent_order_resource->promo_amt + $resource->promo_amt;
        if ($group_order_record['admin_user_id'] == $resource->user_id) {
            // this is the admin
            if ($resource->order_type == 'D') {
                // propogate the requested delivery time to the parent order
                $parent_order_resource->requested_delivery_time = $resource->requested_delivery_time;
            }
        }
        $parent_order_resource->save();

    }

    /**
     * @param $order Order
     */
    function createMessagesForOrder($order,$message_leadtime_for_order,$pickup_ts)
    {
        if ($this->shouldMessagesBeCreated()) {
            $create_messages_controller = new CreateMessagesController($this->merchant);
            $create_messages_controller->setImmediateDeliveryIfOrderTypeIsDeliveryOrCatering($order);
            $create_messages_controller->setCurrentTime($this->current_time);
            $tz_string = getTheTimeZoneStringFromOffset($this->merchant['time_zone'],$this->merchant['state']);
            myerror_log("about to create messages with a pickuptime of: " . getMySqlFormattedDateTimeFromTimeStampAndTimeZone($pickup_ts, "$tz_string"));
            $create_messages_controller->createOrderMessagesFromOrderInfo($order->getOrderId(), $this->merchant['merchant_id'], $message_leadtime_for_order, $pickup_ts);
        }
    }

    function shouldMessagesBeCreated()
    {
        $create = true;
        if ($this->user['user_id'] == 2) {
            $create = false;
        }
        if ($this->isTypeTwoGroupOrder()) {
            $create = false;
        }
        return $create;
    }

    function closeOutGroupOrderIfItExists($group_order_token)
    {
        if ($group_order_token != null && $group_order_token != '') {
            // close out the group order
            myerror_logging(3, "close out the group order!");
            $group_order_adapter = new GroupOrderAdapter($this->mimetypes);
            $group_order_options[TONIC_FIND_BY_METADATA]['group_order_token'] = $group_order_token;
            if ($group_order_resource = Resource::findExact($group_order_adapter, '', $group_order_options)) {
                $group_order_resource->status = 'Submitted';
                $group_order_resource->sent_ts = date('Y-m-d H:i:s');
                $group_order_resource->save();
            }
        }
    }

    /**
     * @desc will create the order summary.  must pass in a CompleteOrder object  NOT an order_resource
     * @param CompleteOrder $new_order
     */
    function createOrderSummary($new_order)
    {
        $complete_order = CompleteOrder::staticGetCompleteOrder($new_order['order_id'], getM());
        return $complete_order['order_summary'];
    }

    /**
     * @desc will create the order data with id's and status that can be passed back in if changes are made.  must pass in a CompleteOrder object  NOT an order_resource
     * @param CompleteOrder $new_order
     */
    function resubmit_order_items($new_order)
    {
        $complete_order = new CompleteOrder();
        $items = $complete_order->createOrderDataAsIdsWithStatusField($new_order);
        return array("merchant_id" => $new_order['merchant_id'], "user_id" => $new_order['user_id'], "items" => $items);
    }

    function checkForNonActiveOrOrderingOffMessageByMerchantObject($merchant)
    {
        $brand = getBrandForCurrentProcess();
        if ($merchant['active'] == 'Y' && $merchant['ordering_on'] == 'N') {
            myerror_log("active merchant but ordering_on = 'N' so reject the order or checkout data call");
            return self::ORDERING_OFFLINE_MESSAGE;
        } else if ($brand['active'] == 'N') {
            myerror_log("ERROR! Brand active flag is set to ordering off");
            return self::ORDERING_OFFLINE_MESSAGE;
        } else if ($merchant['active'] == 'N') {
            myerror_log("we have an innactive merchant!  return an error!");
            return "Sorry, something has changed with this merchant. Please reload the merchant from the merchant list. Sorry for the confusion.";
        } else if ($this->checkBrandSpecificMethodForOrderingOff($merchant)) {
            myerror_log("We have a brand specific custom call ordering off result.");
            return self::STORE_DROPPED_OFFLINE_MESSAGE;
        }
    }

    function checkBrandSpecificMethodForOrderingOff($merchant,$brand) {
        $brand_id = $merchant['brand_id'];
        $method_name = 'checksBrand'.$brand_id.'OrderingOffCustomCall';
        if (method_exists($this,$method_name)) {
            return $this->$method_name($merchant);
        }
    }

    function checksBrand430OrderingOffCustomCall($merchant)
    {
        // we only want to check on the CHECKOUT call
        if ($this->hasRequestForDestination('checkout')) {
            $xoikos_service = new XoikosService(['merchant'=>$merchant]);
            if ($xoikos_service->testStoreIsInactive()) {
                myerror_log("Xoikos Store is NOT showing active on their network CHECKOUT: store: ".$merchant['merchant_id']);
                $merchant_email = $merchant['shop_email'];
                $brand = getBrandForCurrentProcess();
                $brand_email = $brand['support_email'];
                $body = "Hello,\nThis is an automatic message from the splickit system to let you know that store ".$merchant['merchant_external_id']." is not showing as active on the Xoikos system and we are unable to send orders in.  Please check the stores connectivity.";
                $subject = "Store ".$merchant['merchant_external_id']." is showing OFFLINE";
                MailIt::sendEmailToList("$merchant_email;$brand_email",$body,$subject);
                MailIt::sendErrorEmailSupport($subject,$body);
                MailIt::sendErrorEmail($subject,$body);
                return true;
            } else {
                return false;
            }
        }
    }

    function buildAndStageConfirmationEmailFromCompleteOrderData($complete_order)
    {
        $order_id = $complete_order['order_id'];
        //get skin info
        $external_identifier = getContext();
        myerror_logging(2, "the skin external identifier for the customer reciept is: " . $external_identifier);
        $complete_order['skin_external_identifier'] = $external_identifier;

        $order_user = $complete_order['user'];
        $merchant = $complete_order['merchant'];

        $resource = Resource::dummyfactory($complete_order);

        $resource->set('server', $_SERVER['HTTP_HOST']);
        $resource->set('android_link', $_SERVER['ANDROID_URL']);
        $resource->set('iphone_link', $_SERVER['IPHONE_URL']);
        $resource->set('additional_message', $merchant['custom_order_message']);

        if ($this->loyalty_controller) {

            $loyalty_messages = array(
                array("label" => $this->loyalty_controller->getLoyaltyEarnedLabel(), "message" => $this->loyalty_controller->getLoyaltyEarnedMessage()),
                array("label" => $this->loyalty_controller->getLoyaltyBalanceLabel(), "message" => $this->loyalty_controller->getLoyaltyBalanceMessage())
            );

            $resource->set("show_loyalty_information", "Y");

            $resource->set("loyalty_message", $loyalty_messages);

            foreach ($complete_order["order_summary"]["payment_items"] as $payment_item) {
                $payment_type = strtoupper($payment_item["title"]);
                if (strpos($payment_type, "REWARDS") !== false) {
                    $resource->set("rewards_used", array("label" => LoyaltyBalancePaymentService::getDiscountDisplay(), "amount" => $payment_item["amount"]));
                } elseif (strpos($payment_type, "CARD") !== false) {
                    $bill_message = $payment_item["amount"] . " billed to credit card ending in " . $order_user["last_four"];
                    $resource->set("bill", $bill_message);
                } elseif (strpos($payment_type, "AUTHORIZE") !== false) {
                    $bill_message = $payment_item["amount"] . " billed to credit card ending in " . $order_user["last_four"];
                    $resource->set("bill", $bill_message);
                } else {
                    $resource->set("bill", $payment_item["amount"] . " " . strtolower($payment_item["title"]));
                }
            }
        }

        if (isset($complete_order['participant_submit_to_group_order']) && $complete_order['participant_submit_to_group_order'] === true) {
            $resource->set('participant_submit_to_group_order', 'Y');
            $admin_user_group_order_resource = Resource::find(new UserAdapter(), $this->group_order_record['admin_user_id']);
            $resource->set('group_order_admin_first_name', $admin_user_group_order_resource->first_name);
        }

        if (isset($complete_order['is_submit_group_order'])) {
            $resource->set('is_submit_group_order', 'Y');
        }

        $resource->_representation = $this->getOrderConfirmationTemplateFilePath();

        $email_body = getResourceBody($resource);
        myerror_logging(6, "email body: " . $email_body);

        $email_reciept_subject = "Order Confirmation For " . $merchant['name'] . " " . $merchant['city'];
        $email_address = $order_user['email'];
        myerror_logging(2, "about to send receipt to user.  user email is: " . $email_address);

        if (preg_match("/test_api/i", $email_address) || preg_match("/testable.user/i", $email_address) || $complete_order['brand_id'] == 326 || $order_user['user_id'] < 20000) {
            myerror_log("skip sending order confirmation email. either test user or Jersey Mikes order");
        } else {
            MailIt::stageOrderConfirmationEmail($order_id, $email_address, $email_body, $email_reciept_subject, $_SERVER['SKIN']['skin_name'], time());
        }
    }

    function getOrderConfirmationTemplateFilePath()
    {
        $context_name = getIdentifierNameFromContext();
        myerror_log("about to get template for skin: $context_name");
        $doc_root = $_SERVER['DOCUMENT_ROOT'];
        if (!isLaptop()) {
            $doc_root .= '/app2';
        }
        $path = $doc_root . '/resources/email_templates/order_confirmation_templates/' . $context_name . '_order_confirmation.html';
        myerror_log("looking for template path: " . $path);
        if (file_exists($path)) {
            return '/email_templates/order_confirmation_templates/' . $context_name . '_order_confirmation.html';
        } else {
            return '/email_templates/order_confirmation_templates/order_confirmation.html';
        }

    }

    function testForCCProcessorShutDown($cc_processor)
    {
        if ($cc_processor == 'I' && getProperty('inspire_pay_ordering_on') == 'false') {
            return true;
        } else if ($cc_processor == 'M' && getProperty('mercury_pay_ordering_on') == 'false') {
            return true;
        } else if ($cc_processor == 'F' && getProperty('fpn_ordering_on') == 'false') {
            return true;
        }
        return false;
    }

    /* keeping this commented out until we decide to do gifting again */
//    function cancelOrderAndReturnErrorMessageResource($order_resource, $response_text, $internal_code)
//    {
//        $order_resource->status = OrderAdapter::ORDER_PAYMENT_FAILED;
//        $order_resource->payment_file = 'Cancelled';
//        $order_resource->save();
//        $sql = "call SMAWSP_CANCEL_ORDER_MESSAGES(" . $order_resource->order_id . ")";
//        myerror_logging(3, $sql);
//        $this->adapter->_query($sql);
//        if ($internal_code == 500) {
//            return createErrorResourceWithHttpCode($response_text, 500, $internal_code, array("cancelled_order_id" => $order_resource->order_id));
//        }
//        return createErrorResourceWithHttpCode($response_text, 422, $internal_code, array("cancelled_order_id" => $order_resource->order_id));
//    }

    function setForcedTs($time_stamp)
    {
        $this->setCurrentTime($time_stamp);
    }

    function setCurrentTime($time_stamp)
    {
        $forced_time_string = date("Y-m-d H:i", $time_stamp);
        myerror_log("setting current time to be $forced_time_string in time zone: " . date_default_timezone_get());
        $this->current_time = $time_stamp;
    }

    /**
     *
     * @desc will determine if there is an auto promo, convenience fee, and minumum lead time for this user merchant combination.  returns a hashmap 'promo_id','convenience_fee_override','minimum_lead_time_override'
     * @desc CURRENTLY ONLY WORKS FOR Airline Employees user group.
     * @param int $user_id
     * @param int $merchant_id
     * @return Hashmap
     */
    function getUserGroupOverrideValuesIfItAppliesForThisUserMerchantCombination($user_id, $merchant_id)
    {
        //CHANGE THIS  - currently hard coded to airport workers group
        $return_map = $this->getAirportEmployeesOverrideValuesIfItAppliesToThisUserMerchantCombination($user_id, $merchant_id);
        if (! isset($return_map['promo_id'])) {
            if ($promo_id = $this->getAutoPromoForMerchantId($merchant_id)) {
                $return_map['promo_id'] = $promo_id;
            }
        }
        return $return_map;
    }

    function getAutoPromoForMerchantId($merchant_id)
    {
        $sql = "SELECT a.* FROM Promo a JOIN Promo_Merchant_Map b ON a.promo_id = b.promo_id WHERE b.merchant_id = $merchant_id AND promo_key_word LIKE 'X_%' and a.logical_delete = 'N' and a.active = 'Y'";
        $options[TONIC_FIND_BY_SQL] = $sql;
        if ($promo_resources = Resource::findAll(new PromoAdapter(getM()),null,$options)) {
            return $promo_resources[0]->promo_id;
        }
    }

    function getAirportEmployeesOverrideValuesIfItAppliesToThisUserMerchantCombination($user_id, $merchant_id)
    {
        $return_map = array();
        if (AirportAreasMerchantsMapAdapter::isMerchantAnAirportLocation($merchant_id)) {
            if ($group = UserGroupMembersAdapter::getGroupIfUserIsAMemberOfItByName($user_id, 'Airport Employees')) {
                if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($group, 'promo_id')) {
                    $return_map['promo_id'] = $group['promo_id'];
                }
                if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($group, 'convenience_fee_override')) {
                    $return_map['convenience_fee_override'] = $group['convenience_fee_override'];
                }
                if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($group, 'minimum_lead_time_override')) {
                    $return_map['minimum_lead_time_override'] = $group['minimum_lead_time_override'];
                }

            }
        }
        logData($return_map, "User Group Override", 3);
        return $return_map;
    }

    function getLowestValidConvenienceFeeFromMerchantUserCombination($order_amt, $merchant, $user, $override_values)
    {
        if (strtolower($merchant['trans_fee_type']) == 'p') {
            $trans_fee_by_percentage = number_format($order_amt * ($merchant['trans_fee_rate'] / 100), 2);
            return $this->getLowestValidConvenienceFeeFromTheseValues($trans_fee_by_percentage, $user['trans_fee_override'], $override_values['convenience_fee_override']);
        } else if (strtolower($merchant['trans_fee_type']) == 'f') {
            return $this->getLowestValidConvenienceFeeFromTheseValues($merchant['trans_fee_rate'], $user['trans_fee_override'], $override_values['convenience_fee_override']);
        } else {
            return $merchant['trans_fee_rate'];
        }
    }

    function getLowestValidConvenienceFeeFromTheseValues($merchant_cf, $user_cf, $group_cf)
    {
        myerror_logging(3, "values passed into getting lowest convenience fee:  $merchant_cf, $user_cf, $group_cf");
        $cf = $merchant_cf;
        if (($user_cf !== null) && ($user_cf < $cf)) {
            $cf = $user_cf;
        }
        if (($group_cf !== null) && ($group_cf < $cf)) {
            $cf = $group_cf;
        }
        myerror_logging(3, "returning: $cf");
        return $cf;
    }

    function validateTip($sub_total, $tip, $tip_minimum_trigger_amount, $tip_minimum_percentage)
    {
        if ($this->order->isCateringOrder()) {
            $minimum_tip_percentage = $this->merchant_catering_info['minimum_tip_percent'];
            $minimum_tip = $sub_total * $minimum_tip_percentage/100;
            if ($tip < $minimum_tip) {
                $error_message = str_replace('%%minimum_tip%%','$'.number_format($minimum_tip,2),CateringController::MINIMUM_TIP_NOT_MET_ERROR);
                return createErrorResourceWithHttpCode($error_message,422,540);
            }
        } else {
            if (! $this->isTipValidAgainstSubtotalAndMinimums($sub_total, $tip, $tip_minimum_trigger_amount, $tip_minimum_percentage)) {
                return createErrorResourceWithHttpCode("We're sorry, but this merchant requires a gratuity of " . $this->merchant['tip_minimum_percentage'] . "% on orders over $" . $this->merchant['tip_minimum_trigger_amount'] . ". Please set tip to at least " . $this->merchant['tip_minimum_percentage'] . "%.", 422, 540, null);
            }
        }
        return null;
    }

    function isTipValidAgainstSubtotalAndMinimums($sub_total, $tip, $tip_minimum_trigger_amount, $tip_minimum_percentage)
    {
        if ($sub_total >= $tip_minimum_trigger_amount) {
            $sub_total = (float)$sub_total;
            $minimum_tip = (float)$sub_total * ($tip_minimum_percentage / 100);
            $tip = (float)$tip;
            myerror_log("min tip = " . $minimum_tip);
            myerror_log("tip = " . $tip);
            // seems wrong but i don't want to spend much time on this so am making sure its within a penny     blame = *** arosenthal ***
            if ($minimum_tip - $tip > .01) {
                return false;
            }
        }
        return true;
    }

    /**
     *
     * @desc will determin if the delivery minumum has been met.  throws exception if failure.
     * @param $mdi_resource
     * @param $merchant_delivery_price_distance_resource
     * @param $subtotal
     */
    function validateDeliveryMinimum($mdi_resource, $merchant_delivery_price_distance_resource, $sub_total)
    {
        if ($this->isGroupOrder()) {
            $order_minimum = 0.00;
        } else {
            $order_minimum = ($merchant_delivery_price_distance_resource->minimum_order_amount != null) ? $merchant_delivery_price_distance_resource->minimum_order_amount : $mdi_resource->minimum_order;
        }
        if ($order_minimum > $sub_total) {
            throw new DeliveryMinimumNotMetException($order_minimum);
        }
        $this->delivery_order_minimum = $order_minimum;
        return;
    }

    function getDeliveryTaxRateOverride($merchant_id)
    {
        $merchant_delivery_info_adapter = new MerchantDeliveryInfoAdapter(getM());
        return $merchant_delivery_info_adapter->getDeliveryTaxRateOverride($merchant_id);

    }

    function getDeliveryTaxAmount($merchant, $delivery_price)
    {
        $merchant_delivery_info_adapter = new MerchantDeliveryInfoAdapter(getM());
        return $merchant_delivery_info_adapter->getDeliveryTaxAmount($merchant,$delivery_price);

    }

    function stageGiftEmail(&$gift_resource)
    {
        $gifter_user_resource = $this->getResourceFromId($gift_resource->gifter_user_id, 'User');
        $gift_resource->set('gifter_first_name', $gifter_user_resource->first_name);
        $gift_date = date('F jS', $gift_resource->created);
        $gift_resource->set('gift_date', $gift_date);
        $gift_resource->set('giftee_first_name', $this->user['first_name']);
        $gift_resource->_representation = '/email_templates/email_confirm_sean/use_of_gift_notice.html';
        $representation = $gift_resource->loadRepresentation($this->file_adapter);
        $email_body = $representation->_getContent();
        myerror_logging(3, $email_body);
        MailIt::stageEmail($gifter_user_resource->email, 'Your Gift Was Used!', $email_body, $_SERVER['SKIN']['skin_name'], null, array());
    }

    function convertCartItemsFromItemIdAndSizeIdToItemSizeIdAKASizePriceId($items, $price_record_merchant_id)
    {
        if (!isset($items[0]['sizeprice_id'])) {
            foreach ($items as &$item) {
                $ism_data['item_id'] = $item['item_id'];
                $ism_data['size_id'] = $item['size_id'];
                $ism_data['active'] = 'Y';
                $ism_data['merchant_id'] = $price_record_merchant_id;
                $item_options[TONIC_FIND_BY_METADATA] = $ism_data;
                if ($item_size_resource = Resource::find(new ItemSizeAdapter(), '', $item_options)) {
                    $item['sizeprice_id'] = $item_size_resource->item_size_id;
                } else {
                    myerror_log("DATA ERROR this item no longer exists! item_id: " . $item['item_id'] . "   at  size_id: " . $item['size_id']);
                    recordError("Menu out of date Excpetion", "DATA ERROR this item no longer exists! item_id: " . $item['item_id'] . "   at  size_id: " . $item['size_id']);
                    throw new MenuNotCurrentException("item_id: " . $item['item_id']);
                }
                foreach ($item['mods'] as &$mod) {
                    if (!isset($mod['mod_sizeprice_id'])) {
                        $mism_data['modifier_item_id'] = $mod['modifier_item_id'];
                        $mism_data['merchant_id'] = $price_record_merchant_id;
                        $mism_data['active'] = 'Y';
                        $modifier_options[TONIC_FIND_BY_METADATA] = $mism_data;
                        $modifier_options[TONIC_FIND_BY_STATIC_METADATA] = ' (size_id = ' . $item['size_id'] . ' OR size_id = 0) ';
                        $modifier_options[TONIC_SORT_BY_METADATA] = ' size_id DESC ';
                        if ($modifier_size_map_resources = Resource::findAll(new ModifierSizeMapAdapter(getM()), '', $modifier_options)) {
                            // always take the first one since that will be for a specific size if it exists
                            $modifier_size_map_resource = $modifier_size_map_resources[0];
                            $mod['mod_sizeprice_id'] = $modifier_size_map_resource->modifier_size_id;
                        } else {
                            myerror_log("DATA ERROR this modifier item no longer exists! modifier_item_id: " . $mod['modifier_item_id'] . "   at  size_id: " . $item['size_id'] . " or size_id: 0");
                            recordError("Menu out of date Excpetion", "DATA ERROR this modifier item size record no longer exists! modifier_item_id: " . $mod['modifier_item_id'] . "   at  size_id: " . $item['size_id'] . " or size_id: 0");
                            throw new MenuNotCurrentException("modifier_id: " . $mod['modifier_item_id']);
                        }
                    }
                }
            }
        }
        return $items;
    }

    /**
     *
     * @desc purely used for backward compatability for orders that are submitted without a merchant_payment_map_id.
     * @param $resource
     */
    function getMerchantPaymentTypeMapIdFromOrderData($order_data)
    {
        $merchant_payment_type_maps_adapter = new MerchantPaymentTypeMapsAdapter();
        $merchant_payment_type_adapter = new MerchantPaymentTypeAdapter();
        if (isset($order_data['cash']) && (strtoupper($order_data['cash']) == 'YES' || strtoupper($order_data['cash']) == 'Y')) {
            if ($record = $merchant_payment_type_maps_adapter->getRecord(array("merchant_id" => $this->merchant['merchant_id'], "splickit_accepted_payment_type_id" => SplickitPaymentService::CASHSPLICKITPAYMENTID))) {
                return $record['id'];
            } else if ($record = $merchant_payment_type_adapter->getRecord(array("merchant_id" => $this->merchant['merchant_id'], "payment_type" => 'cash'))) {
                $created_payment_map_resource = $merchant_payment_type_maps_adapter->createMerchantPaymentTypeMap($this->merchant['merchant_id'], 1000, null);
                return $created_payment_map_resource->id;
            } else {
                throw new CashSubittedForNonCashMerchantException();
            }
        } else {
            if ($record = $merchant_payment_type_maps_adapter->getRecord(array("merchant_id" => $this->merchant['merchant_id'], "splickit_accepted_payment_type_id" => SplickitPaymentService::CREDITCARDSPLICKITPAYMENTID))) {
                return $record['id'];
            } else {
                throw new NoMatchingMerchantPaymentTypeMapForOrderDataException();
            }
        }
    }

    function setTwitterAndFacebookStuffOnOrderResource(&$resource, $merchant, $skin)
    {
        $store_website = $merchant['facebook_caption_link'];
        $twitter_link = $skin['twitter_handle'];
        if ($twitter_link == null || trim($twitter_link) == '')
            $twitter_link = $merchant['name'];
        else
            $twitter_link = '@' . $twitter_link;
        $facebook['facebook_title'] = "Ordered from " . $merchant['name'];
        $device = 'mobile';
        if (strtolower($_SERVER['device_type']) == 'web')
            $device = 'web';


        $powered_by = '@splickit';

        $facebook['facebook_caption'] = "I just ordered and paid at " . $twitter_link . ", powered by " . $powered_by . " " . $device . " ordering. Cut the line, just grab and go!";
        $twitter['twitter_caption'] = "I just ordered and paid at " . $twitter_link . ", powered by " . $powered_by . " " . $device . " ordering. Cut the line, just grab and go!";

        // custom moes message
        $merchant_id = $this->merchant['merchant_id'];
        $facebook['facebook_slogan'] = "";
        $facebook['facebook_thumbnail_url'] = $skin['facebook_thumbnail_url'];
        $facebook['facebook_thumbnail_link'] = $skin['facebook_thumbnail_link'];
        if ($store_website == null || trim($store_website) == '') {
            $store_website = 'http://www.splickit.com';
        }

        $facebook['caption_link'] = $store_website;
        $facebook['action_link'] = "http://itunes.apple.com/us/app/splick-it/id375047368?mt=8";
        $facebook['action_text'] = "Get splick-it";

        logData($facebook, 'facebook', 3);
        logData($twitter, 'twitter', 3);

        if ($merchant['custom_order_message'] != NULL) {
            $resource->user_message .= "\n\n" . $merchant['custom_order_message'];
            $resource->set('additional_message', $merchant['custom_order_message']);
        }
        $resource->set('facebook', $facebook);
        $resource->set('twitter', $twitter);
    }

    /**
     * @param $order Order
     * @return int
     */
    function getPriceRecordMerchantIdFromOrderObject($order)
    {
        $menu_type = ($order->isDeliveryOrder()) ? 'delivery' : 'pickup';
        $menu_id = MerchantMenuMapAdapter::getMenuIdFromMerchantIdAndType($order->get('merchant_id'), $menu_type);
        $menu_version = MenuAdapter::getMenuVersion($menu_id);
        if ($menu_version == 2.0) {
            $price_record_merchant_id = "0";
        } else {
            $price_record_merchant_id = $order->get('merchant_id');
        }
        return $price_record_merchant_id;
    }

    function getLoyaltyController($user)
    {
        if ($this->loyalty_controller) {
            return $this->loyalty_controller;
        } else if ($this->loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForContext($user)) {
            return $this->loyalty_controller;
        }

    }

    function getPaymentService()
    {
        return $this->payment_service;
    }

    function isThisARegularUser($user_id)
    {
        return ($user_id > 19999);
    }

    function setMerchantById($merchant_id)
    {
        if ($this->merchant_resource = Resource::find(new MerchantAdapter(),"$merchant_id")) {
            $this->merchant = $this->merchant_resource->getDataFieldsReally();
        }

    }
}

class PlaceOrderException extends Exception
{
    var $error_type;
    var $error_http_code;
    var $error_resource;

    public function __construct($resource_with_error_info)
    {
        parent::__construct($resource_with_error_info->error, $resource_with_error_info->error_code);
        $this->error_resource = $resource_with_error_info;
        if (isset($resource_with_error_info->error_type)) {
            $this->error_type = $resource_with_error_info->error_type;
        }
        if (isset($resource_with_error_info->http_code)) {
            $this->error_http_code = $resource_with_error_info->http_code;
        }

    }
}

class MenuNotCurrentException extends Exception
{
    public function __construct($string)
    {
        parent::__construct("menu error, $string is no longer available");
    }
}

class DeliveryMinimumNotMetException extends Exception
{
    public function __construct($order_minimum)
    {
        parent::__construct("Minimum order required! You have not met the minimum subtotal of $" . $order_minimum . " for your deliver area.", 590);
    }
}

class UserAddressNotWithinDeliveryZoneException extends Exception
{
    public function __construct($message)
    {
        if ($message == null) {
            $message  = PlaceOrderController::ADDRESS_IS_OUTSIDE_DELIVERY_ZONE_ERROR_MESSAGE;
        }
        parent::__construct($message, 535);
    }
}

class NoMatchingMerchantPaymentTypeMapForOrderDataException extends Exception
{
    public function __construct($dummy = 'dummy')
    {
        parent::__construct("We're sorry, there appears to be a problem with payment set up of this mercahnt and we cannot accept orders at this time.");
    }
}

class CashSubittedForNonCashMerchantException extends Exception
{
    public function __construct($dummy = 'dum')
    {
        parent::__construct("Merchant does not accept cash", 999);
    }

}

class NoDataPassedInForCartCreationException extends Exception
{
    public function __construct($dummy = 'dum')
    {
        parent::__construct("No data passed in for cart creation.", 999);
    }
}

class OrderPlacedByTempUserException extends Exception
{
    public function __construct($dummy = 'dum')
    {
        MailIt::sendErrorEmail("ERROR! Temp User trying to place order", "Attempted order placed by temp user");
        parent::__construct("We're sorry there was an error. You do not appear to be logged in. Please try again.", 999);
    }

}


class ParticipantNoDeliveryChargeException extends Exception
{
    public function __construct()
    {
        parent::__construct();
    }
}


?>
