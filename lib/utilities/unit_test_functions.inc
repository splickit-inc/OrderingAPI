<?php
ini_set("error_log", "./logs/php_errors.log");
error_reporting(E_ERROR | E_COMPILE_ERROR | E_COMPILE_WARNING | E_PARSE);
error_log("STARTING unit_test_functions.inc");
spl_autoload_register('mymockautoload');
$_SERVER['ENVIRONMENT'] = isset($_SERVER['XDEBUG_CONFIG']) ? "unit_test_ide" : "unit_test";
$_SERVER['DB_INFO_FILE_PATH'] = "config/smaw_database_unittest.conf";

function mymockautoload($class_name)
{
    $class_name_lower = strtolower($class_name);
    $path = "lib" . DIRECTORY_SEPARATOR . "mocks" . DIRECTORY_SEPARATOR . $class_name_lower . ".php";
    if (file_exists($path)) {
        require_once "lib" . DIRECTORY_SEPARATOR . "mocks" . DIRECTORY_SEPARATOR . $class_name_lower . ".php";
    } else {
        return false;
    }
}

function setContext($external_identifier)
{
    $skin_adapter = new SkinAdapter();
    if ($records = $skin_adapter->findForBrand($external_identifier)) {
        $record = $records[0];
        $_SERVER['DOCUMENT_ROOT'] = getcwd();
        $_SERVER['SKIN_ID'] = $record['skin_id'];
        $_SERVER['SKIN'] = $record;
        $_SERVER['SKIN_TYPE'] = $record['mobile_app_type'];
        if ($brand_id = $record['brand_id']) {
            $ba = new BrandAdapter();
            $_SERVER['BRAND'] = $ba->getCleanRecord(array("brand_id" => $brand_id));
        }
        $_SERVER['HTTP_X_SPLICKIT_CLIENT_ID'] = $external_identifier;
        $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'] = "unit testing";
        $_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'] = "1000";
        return true;
    } else {
        throw new Exception("NO MATCHING external_idetifier: " . $external_identifier, $code);
    }
}

function removeContext()
{
    unset($_SERVER['SKIN_ID']);
    unset($_SERVER['SKIN']);
    unset($_SERVER['SKIN_TYPE']);
    unset($_SERVER['BRAND']);
    unset($_SERVER['HTTP_X_SPLICKIT_CLIENT_ID']);
    unset($_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE']);
    unset($_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION']);
}

function createRLCSkin()
{
    $skin_adapter = new SkinAdapter($mt);
    $data['skin_name'] = 'RLC';
    $data['skin_description'] = 'Restaurant Leadership Conference';
    $data['external_identifier'] = 'com.splickit.rlc';
    $data['iphone_certificate_file_name'] = 'com.splickit.rlc';
    $resource = Resource::factory($skin_adapter, $data);
    $resource->save();
}

function placeTheOrderV2($json_encoded_data)
{
    return placeTheOrder($json_encoded_data, 'v2');
}

function placeTheOrder($json_encoded_data, $version = 'v1')
{
    $request = new Request();
    $request->url = '/app2/phone/placeorder';
    $request->method = "post";
    $request->body = $json_encoded_data;
    $request->mimetype = 'Applicationjson';
    //$request->_parseRequestBody();
    try {
        $place_order_controller = new PlaceOrderController($mt, $_SERVER['AUTHENTICATED_USER'], $request, 5);
        $order_resource = $place_order_controller->placeOrderFromRequest();
    } catch (BadJsonException $bje) {
        $order_resource = returnErrorResource($bje->getMessage(), 999);
    } catch (Exception $e) {
        $order_resource = returnErrorResource($e->getMessage(), 999);
    }

    return $order_resource;
}

function getCheckoutDataV2FromOrderData($order_data)
{
    return getCheckoutDataV2(json_encode($order_data));
}

function getCheckoutDataV2($json_encoded_data)
{
    return getCheckoutData($json_encoded_data, 'v2');
}

function getCheckoutData($json_encoded_data, $version = 'v1')
{
    $request = createRequestObject('/app2/phone/getcheckoutdata', 'POST', $json_encoded_data, 'application/json');
    $merchant_id = $request->data['merchant_id'];
    $merchant_resource = Resource::find(new MerchantAdapter(), '' . $merchant_id);
    //$leadtime_object = new LeadTime($merchant_resource);
    //$lead_times_array = $leadtime_object->getLeadTimesArrayFromOrder($request);

    $place_order_controller = new PlaceOrderController($mt, $_SERVER['AUTHENTICATED_USER'], $request, 5);
    $checkout_data_resource = $place_order_controller->getCheckoutDataFromOrderRquest();
    //$checkout_data_resource->set('lead_times_array', $lead_times_array);
    return $checkout_data_resource;
}

function unset_server_session_data()
{
    unset($_SERVER[getRawStamp()]);
}


function getCartResourceFromOrderData($order_data)
{
    $user = logTestUserIn($order_data['user_id']);
    $json_encoded_data = json_encode($order_data);
    $request = createRequestObject('/app2/apiv2/cart', 'POST', $json_encoded_data, 'application/json');
    $place_order_controller = new PlaceOrderController($mt, $user, $request);
    $cart_resource = $place_order_controller->processV2Request();
    return $cart_resource;
}

function getCheckoutResourceFromCartUcid($ucid, $time_stamp)
{
    $cart = CartsAdapter::staticGetCartAndBaseOrderDataFromCartUcid($ucid);
    $user = logTestUserIn($cart['user_id']);
    $request = createRequestObject("/app2/apiv2/cart/$ucid/checkout", 'GET');
    $place_order_controller = new PlaceOrderController($mt, $user, $request);
    $place_order_controller->setCurrentTime($time_stamp);
    $checkout_resource = $place_order_controller->processV2Request();
    return $checkout_resource;
}

function getCheckoutResourceFromOrderData($order_data, $time_stamp)
{
    if ($time_stamp == null) {
        $time_stamp = getTomorrowTwelveNoonTimeStampDenver();
    }
    $user = logTestUserIn($order_data['user_id']);
    $json_encoded_data = json_encode($order_data);
    $url = isset($order_data['ucid']) ? "/app2/apiv2/cart/" . $order_data['ucid'] . "/checkout" : '/app2/apiv2/cart/checkout';
    $request = createRequestObject($url, 'POST', $json_encoded_data, 'application/json');
    $place_order_controller = new PlaceOrderController($mt, $user, $request);
    $place_order_controller->setCurrentTime($time_stamp);
    $checkout_resource = $place_order_controller->processV2Request();
    return $checkout_resource;

}

function placeOrderFromCheckoutResourceWithoutCCCall($checkout_resource, $tip = 0.00, $time)
{
    $_SERVER['DO_NOT_RUN_CC'] = 'true';
    return placeOrderFromCheckoutResource($checkout_resource, null, null, $tip, $time);
}

function placeOrderFromCheckoutResource($checkout_resource, $user, $merchant_id, $tip, $time)
{
    $order_data = array();
    $order_data['tip'] = $tip === null ? (rand(100, 1000)) / 100 : $tip;
    $payment_array = $checkout_resource->accepted_payment_types;
    $order_data['merchant_payment_type_map_id'] = $payment_array[0]['merchant_payment_type_map_id'];
    $lead_times_array = $checkout_resource->lead_times_array;
    if ($checkout_resource->note) {
        $order_data['note'] = $checkout_resource->note;
    }
    if ($checkout_resource->dine_in) {
        $order_data['dine_in'] = true;
    }
    if (strtolower($lead_times_array[0]) == 'as soon as possible') {
        $order_data['delivery_time'] = $lead_times_array[0];
        $order_data['requested_time'] = $lead_times_array[0];
    } else {
        $order_data['actual_pickup_time'] = $lead_times_array[0];
        $order_data['requested_time'] = $lead_times_array[0];
    }

    if ($user == null) {
        $user = getLoggedInUser();
    }

    $json_encoded_data = json_encode($order_data);

    $user = logTestUserIn($user['user_id']);

    $request = createRequestObject("/apiv2/orders/" . $checkout_resource->cart_ucid, "post", $json_encoded_data, 'application/json');
    $place_order_controller = new PlaceOrderController($mt, $user, $request);
    $current_time = $time == null ? getTomorrowTwelveNoonTimeStampDenver() : $time;
    $place_order_controller->setCurrentTime($current_time);
    $order_resource = $place_order_controller->processV2Request();
    return $order_resource;
}

function authenticateUser($user_id)
{
    if ($user_resource = Resource::find(new UserAdapter(), $user_id)) {
        $user = $user_resource->getDataFieldsReally();
        $_SERVER['AUTHENTICATED_USER'] = $user;
        $_SERVER['AUTHENTICATED_USER_ID'] = $user['user_id'];
    } else {
        myerror_log("ERROR! NO USER FOUND matching id: " . $user_id);
    }
    return $user;
}

function createCode($number)
{
    $characters = 'abc1234def567890ghijk1234lm567nop890qrstuvwxyz';
    $code = '';
    for ($i = 0; $i < $number; $i++) {
        $new = mt_rand(0, 45);
        $code = $code . substr($characters, $new, 1);
    }
    return $code;
}

function createPLU($number)
{
    $code = '';
    for ($i = 0; $i < $number; $i++) {
        $new_value = mt_rand(0, 9);
        $code .= $new_value;
    }
    return $code;
}

function createUUID()
{
    return generateUUID();
//    	$code = createCode(15);
    //   	$new_code =  mt_rand(1111,9999).'-'.substr($code, 0,5).'-'.substr($code, 4,5).'-'.substr($code, 9,5);
    //  	return $new_code;

}

function createNewUserDataFieldsLite()
{
    $randomizer_string = createCode(3);
    $user_data['first_name'] = 'first name';
    $user_data['last_name'] = 'last name';
    $user_data['email'] = getCreateTestUserEmail($randomizer_string);
    $user_data['contact_no'] = rand(1111111111, 9999999999);
    return $user_data;
}

function createNewUserDataFields()
{
    $randomizer_string = createCode(3);
    $user_data['first_name'] = 'first';
    $user_data['last_name'] = 'last';
    $user_data['email'] = getCreateTestUserEmail($randomizer_string);
    $user_data['password'] = 'welcome';
    $user_data['contact_no'] = rand(1111111111, 9999999999);
    $user_data['loyalty_number'];
    $user_data['account_hash'] = time() . '_' . $randomizer_string;
    return $user_data;
}

function getCreateTestUserEmail($randomizer_string = 'abc')
{
    if ($randomizer_string == 'abc') {
        $randomizer_string = createCode(3);
    }
    return 'testuser_' . time() . '_' . $randomizer_string . '@dummy.com';
}

/**
 *
 * @desc will set the authenticated User and user_id to be that of the submitted user id.
 * @param $user_id
 * @return array of the user object
 */
function logTestUserIn($user_id)
{
    $loginAdapter = new UserAdapter(getM());
    if (is_numeric($user_id)) {
        $data = array("user_id" => $user_id);
    } else {
        $data = array("uuid" => $user_id);
    }
    if ($user = $loginAdapter->getRecord($data)) {
        return doTheAuthStuff($user);
    }
    throw new Exception("user_id: $user_id , does not exist", 101);
}

function setSessionAuthenticatedUserParameters($user)
{
    $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'] = $user['device_id'];
    $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'] = 'UnitTest';
    setAuthenticatedUserParametersForSession($user);
}

/**
 *
 * @desc will set the authenticated User and user_id to be that of the submitted user_resource.
 * @param Resource $user_resource
 * @return array of the user object
 */
function logTestUserResourceIn($user_resource)
{
    $user = $user_resource->getDataFieldsReally();
    $authed_user = doTheAuthStuff($user);
    return array_merge($authed_user, $user);
}

function doTheAuthStuff($user)
{
    checkUserIdAndSetAdminFields($user['user_id']);
    setSessionAuthenticatedUserParameters($user);
    $authentication_token_resource = createUserAuthenticationToken($user['user_id']);
    $user['splickit_authentication_token'] = $authentication_token_resource->token;
    return $user;
}

function logTestUserInWithGift($user_id, $gift_resource)
{
    $user = logTestUserIn($user_id);
    $user['gift_resource'] = $gift_resource;
    $_SERVER['AUTHENTICATED_USER'] = $user;
    return $user;
}

function createNewUserWithCC($data = null)
{
    $user_resource = createNewUser($data);
    $user_resource->flags = '1C21000001';
    $user_resource->last_four = '1234';
    $user_resource->save();
    return $user_resource;
}

function createNewUserWithCCNoCVV($data = null)
{
    $user_resource = createNewUser($data);
    $user_resource->flags = '1C20000001';
    $user_resource->last_four = '1234';
    $user_resource->save();
    return $user_resource;
}

/**
 * @desc creates a new user for testing.  any passed in data will overwrite the defaults.
 * @return Resource
 */
function createNewUser($data = null)
{
    $admin_user = logTestUserIn(1);
    $user_data = createNewUserDataFields();
    $code = createUUID();
    //i'm sure there's a better way to do this
    $user_data['uuid'] = $code;
    $user_data['device_id'] = $code;
    $user_data['skin_id'] = 1;
    $user_data['skin_name'] = 'splickit';
    $user_data['device_type'] = 'unit_testing';
    $user_data['app_version'] = '100.0.1';
    $user_data['balance'] = 0.00;
    if ($data) {
        $user_data = array_merge($user_data, $data);
    }
    $request = new Request();
    $request->data = $user_data;
    $user_controller = new UserController($mt, $admin_user, $request);
    $user_resource = $user_controller->createUser();
    if ($user_resource->user_id > 1000) {
        $user_resource = SplickitController::getResourceFromId($user_resource->user_id, 'User');
        if ($data['flags']) {
            $user_resource->flags = $data['flags'];
            $user_resource->save();
        }
    }
    return $user_resource;
}

/**
 * @desc creates a new guest user for testing.  any passed in data will overwrite the defaults.
 * @return Resource
 */
function createGuestUser($data = null)
{
    $randomizer_string = createCode(3);
    $user_data['first_name'] = 'first';
    $user_data['email'] = getCreateTestUserEmail($randomizer_string);
    $user_data['contact_no'] = rand(1111111111, 9999999999);
    $user_data['is_guest'] = true;

    $admin_user = logTestUserIn(1);
    $code = createUUID();

    $user_data['uuid'] = $code;
    $user_data['device_id'] = $code;
    $user_data['skin_id'] = 1;
    $user_data['skin_name'] = 'splickit';
    $user_data['device_type'] = 'unit_testing';
    $user_data['app_version'] = '100.0.1';
    $user_data['balance'] = 0.00;

    if ($data) {
        $user_data = array_merge($user_data, $data);
    }
    $request = new Request();
    $request->data = $user_data;
    $user_controller = new UserController($mt, $admin_user, $request);
    $user_resource = $user_controller->createUser();
    if ($user_resource->user_id > 1000) {
        $user_resource = SplickitController::getResourceFromId($user_resource->user_id, 'User');
    }
    return $user_resource;
}

function createNewBlacklistedUserNew($data = null)
{
    $user_resource = createNewUser($data);
    $user_resource->flags = 'X000000001';
    if ($user_resource->save()) {
        return $user_resource;
    } else {
        throw new Exception("couldn't create the new user: " . $user_resource->_adapter->getLastErrorText());
    }

}

function createNewBlacklistedUser($data = null)
{
    $admin_user = logTestUserIn(1);
    $user_data = createNewUserDataFields();
    $code = createUUID();
    //i'm sure there's a better way to do this
    $user_data['uuid'] = $code;
    $user_data['device_id'] = $code;
    $user_data['skin_id'] = 1;
    $user_data['skin_name'] = 'splickit';
    $user_data['device_type'] = 'unit_testing';
    $user_data['app_version'] = '100.0.1';
    $user_data['balance'] = 0.00;
    if ($data) {
        $user_data = array_merge($user_data, $data);
    }
    $request = new Request();
    $request->data = $user_data;
    $user_adapter = new UserAdapter();
    $user_controller = new UserController($mt, $admin_user, $request);
    $user_resource = $user_controller->createUser();

    if ($user_resource->save()) {
        $user_id = $user_resource->user_id;
        return Resource::find($user_adapter, "$user_id", array(TONIC_FIND_BY_METADATA => array('logical_delete' => $user_resource->logical_delete)));
    } else {
        throw new Exception("couldn't create the new user: " . $user_resource->_adapter->getLastErrorText());
    }
    return $user_resource;
}

function createNewTempUser($data = null)
{

    $randomizer_string = createCode(3);
    $device_id = generateCode(20);
    $user_data['first_name'] = 'SpTemp';
    $user_data['last_name'] = 'User';
    $user_data['email'] = $device_id . '@splickit.dum';
    $user_data['password'] = 'TlhKDMd8ni6M';
    $user_data['contact_no'] = '1234567890';
    $user_data['loyalty_number'];
    $user_data['account_hash'] = time() . '_' . $randomizer_string;

    //i'm sure there's a better way to do this
    $user_data['uuid'] = createUUID();
    $user_data['device_id'] = $device_id;
    $user_data['skin_id'] = 1;
    $user_data['skin_name'] = 'splickit';
    $user_data['device_type'] = 'unit_testing';
    $user_data['app_version'] = '100.0.1';
    $user_data['balance'] = 0.00;
    if ($data) {
        $user_data = array_merge($user_data, $data);
    }
    $user_adapter = new UserAdapter();
    $user_resource = Resource::factory($user_adapter, $user_data);
    if ($user_resource->save()) {
        $user_id = $user_resource->user_id;
        return Resource::find($user_adapter, "$user_id");
    } else {
        throw new Exception("couldn't create the new user: " . $user_resource->_adapter->getLastErrorText());
    }
}

/**
 *
 * @desc creates the wordhqskin branded skin if it doesn't exist and returns it as a resource
 * @return Resouce
 */
function createWorldHqSkin()
{
    $skin_resource = getOrCreateSkinAndBrandIfNecessary("World Hq", "World Hq", 250, 300);
    setContext("com.splickit.worldhq");
    return $skin_resource;
}

/**
 *
 * @desc creates the yumticket branded skin if it doesn't exist and returns it as a resource
 * @return Resouce
 */
function createYumTicketSkin()
{
    $skin_resource = getOrCreateSkinAndBrandIfNecessary("Yum Ticket", "Yum Ticket", 251, $brand_id);
    return $skin_resource;
}

function getOrCreateSkinAndBrandIfNecessaryWithLoyaltyAndRoundUP($skin_name, $brand_name, $skin_id, $brand_id)
{
    $skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty($skin_name, $brand_name, $skin_id, $brand_id);
    $skin_resource->donation_active = 'Y';
    $skin_resource->donation_organization = 'SomeDumOrg';
    $skin_resource->save();
    return $skin_resource;
}

function getOrCreateSkinAndBrandIfNecessaryWithLoyalty($skin_name, $brand_name, $skin_id, $brand_id)
{
    $skin_resource = getOrCreateSkinAndBrandIfNecessary($skin_name, $brand_name, $skin_id, $brand_id);
    $brand_id = $skin_resource->brand_id;
    $brand_resource = Resource::find(new BrandAdapter(), "$brand_id");
    $brand_resource->loyalty = 'Y';
    $brand_resource->save();
//    $blr_data['brand_id'] = $brand_id;
//    $blr_data['loyalty_type'] = 'splickit_earn';
//    $brand_loyalty_rules_resource = Resource::factory(new BrandLoyaltyRulesAdapter($m),$blr_data);
//    $brand_loyalty_rules_resource->save();
//  return Resource::find(new SkinAdapter(getM()),"".$skin_resource->skin_id);
    return $skin_resource;
}

/**
 * @param $skin_name
 * @param $brand_name
 * @param $skin_id
 * @param $brand_id
 * @return Resource
 */
function getOrCreateSkinAndBrandIfNecessary($skin_name, $brand_name, $skin_id, $brand_id)
{
    $skin_adapter = new SkinAdapter();
    $data['skin_id'] = $skin_id;
    $data['skin_name'] = $skin_name;
    $data['skin_description'] = $skin_name;
    $brand_resource = getBrandOrCreateIfNotExists($brand_name, $brand_id);
    if ($brand_resource->brand_name == 'World Hq') {
        $brand_resource->allows_tipping = 'Y';
        $brand_resource->save();
    }
    $data['brand_id'] = $brand_resource->brand_id;
    $external_identifier = createSkinExternalIdentifierFromSkinName($skin_name);
    $data['external_identifier'] = $external_identifier;
    $data['facebook_thumbnail_link'] = "http://iphone.sumdumurl.com";
    $data['android_marketplace_link'] = "http://android.sumdumurl.com";
    $options[TONIC_FIND_BY_METADATA] = $data;
    $resource = Resource::findOrCreateIfNotExists($skin_adapter, '', $options);
    return $resource;
}

function createSkinExternalIdentifierFromSkinName($skin_name)
{
    $lower_skin_name = strtolower($skin_name);
    $final = str_replace(' ', '', $lower_skin_name);
    return "com.splickit.$final";
}

/**
 *
 * @desc finds or creates a brand
 * @param string $brand_name
 * @return Resource
 */
function getBrandOrCreateIfNotExists($brand_name, $brand_id)
{
    $data['brand_id'] = $brand_id;
    $data['brand_name'] = $brand_name;
    $options[TONIC_FIND_BY_METADATA] = $data;
    $resource = Resource::findOrCreateIfNotExists(new BrandAdapter(), '', $options);
    return $resource;
}

function createWorldHqSkinAndAddMerchants()
{
    $skin_resource = createWorldHqSkin();
    $skin_id = $skin_resource->skin_id;
    myerror_log("about to add the merchants to splickit world hq skin.  skin_id: " . $skin_id);
    $sql = "INSERT INTO Skin_Merchant_Map (merchant_id,skin_id ) SELECT merchant_id,$skin_id FROM Merchant WHERE brand_id = 300";
    $smma = new SkinMerchantMapAdapter();
    $smma->_query($sql);
    return $skin_resource;
}

function attachMerchantToSkin($merchant_id, $skin_id)
{
    $smma = new SkinMerchantMapAdapter();
    $data['merchant_id'] = $merchant_id;
    $data['skin_id'] = $skin_id;
    Resource::createByData($smma, $data);
}

function createNewTestAirport($name)
{
    $airports_adapter = new AirportsAdapter();

    $adata['name'] = "name1";
    $adata['code'] = "ABC";
    $adata['address'] = "500 middle of nowhere drive";
    $adata['city'] = "Smallville";
    $adata['state'] = "NB";
    $adata['zip'] = "12345";
    $adata['lat'] = 50.00000;
    $adata['lng'] = -100.00000;

    $resource = Resource::factory($airports_adapter, $adata);
    $resource->save();
    return $resource;
}

function createAirportArea($airport_id, $area_name = 'This area')
{
    $airport_areas_adapter = new AirportAreasAdapter();

    $aa_data['name'] = $area_name;
    $aa_data['airport_id'] = $airport_id;
    $resource = Resource::factory($airport_areas_adapter, $aa_data);
    if ($resource->save())
        return $resource;
}

function addDeliveryToMerchant($merchant_resource, $menu_id)
{
    $merchant_resource->delivery = 'Y';
    $merchant_resource->save();

    $data = array("merchant_id" => $merchant_resource->merchant_id);
    // set merchant delivery info
    $mdia = new MerchantDeliveryInfoAdapter();
    $mdi_resource = $mdia->getExactResourceFromData($data);
    $mdi_resource->minimum_order = 0.01;
    $mdi_resource->delivery_cost = 1.00;
    $mdi_resource->delivery_increment = 15;
    $mdi_resource->max_days_out = 3;
    $mdi_resource->save();

    $mdpda = new MerchantDeliveryPriceDistanceAdapter();
    $mdpd_resource = $mdpda->getExactResourceFromData($data);

    //map it to a menu
    MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $menu_id, 'pickup');
    MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $menu_id, 'delivery');

    $merchant_resource->set("merchant_delivery_info_id", $mdi_resource->merchant_delivery_id);
    $merchant_resource->set("merchant_delivery_price_distance_id", $mdpd_resource->map_id);
    return $merchant_resource;
}

function createNewTestMerchantDelivery($menu_id)
{
    if ($menu_id < 1) {
        throw new Exception("Must Supply a valid menu id for creating a delivery merchant");
    }
    $merchant_resource = createNewTestMerchant();
    return addDeliveryToMerchant($merchant_resource, $menu_id);
}

function getOrCreateNewTestMerchantBasedOnExternalId($merchant_external_id, $menu_id = 0,$data = [])
{
    if ($merchant_record = MerchantAdapter::staticGetRecord(array("merchant_external_id" => $merchant_external_id, "brand_id" => getBrandIdFromCurrentContext()), 'MerchantAdapter')) {
        return Resource::find(new MerchantAdapter(), $merchant_record['merchant_id']);
    } else {
        $merchant_resource = createNewTestMerchant($menu_id,$data);
        $merchant_resource->merchant_external_id = $merchant_external_id;
        $merchant_resource->save();
        return $merchant_resource;
    }
}

function createNewTestMerchantWithCatering($menu_id, $data)
{
    $merchant_resource = createNewTestMerchant($menu_id, $data);
    $c_data = array();
    $c_data['merchant_id'] = $merchant_resource->merchant_id;
    $c_data['lead_time_in_hours'] = 5;
    $c_data['minimum_pickup_amount'] = 1.00;
    $c_data['minimum_delivery_amount'] = 20.00;
    $c_data['minimum_tip_percent'] = 10;
    $c_data['maximum_catering_orders_per_day_part'] = 2;
    $c_data['accepted_payment_types'] = 'Credit Card Only';
    $c_data['active'] = "Y";

    $merchant_catering_info_adpater = new MerchantCateringInfosAdapter();
    $merchant_catering_info_resource = Resource::createByData($merchant_catering_info_adpater, $c_data);
    $merchant_resource->set("merchant_catering_info_resource", $merchant_catering_info_resource);

    // now delivery data
    $merchant_controller = new MerchantController($m, $u, $r);
    $merchant_controller->stubOutMerchantCateringDelivery($merchant_resource->merchant_id);
    return $merchant_resource;
}

/**
 *
 * @desc creates a test merchant
 * @param $menu_id
 * @return Resource
 */
function createNewTestMerchant($menu_id = 0, $data = array())
{
    $merchant_adapter = new MerchantAdapter();
    $sql = "Select * from Merchant_User where first_name = 'System Default'";
    $mu_options[TONIC_FIND_BY_SQL] = $sql;
    $results = $merchant_adapter->select($url, $mu_options);
    $record = array_pop($results);
    $merchant_user_id = $record['merchant_user_id'];
    $merchant_data['merchant_user_id'] = $merchant_user_id;
    $merchant_data['merchant_external_id'] = null;
    $merchant_data['shop_email'] = 'dummy@dummy.com';
    $brand_id = getBrandIdFromCurrentContext() != null ? getBrandIdFromCurrentContext() : 300;
    $merchant_data['brand_id'] = $brand_id;
    $merchant_data['name'] = "Unit Test Merchant";
    $merchant_data['display_name'] = "Display Name";
    if ($data['time_zone'] == 'pacific') {
        $merchant_data['address1'] = '7135 Sunset Blvd';
        $merchant_data['address2'] = null;
        $merchant_data['city'] = 'Los Angeles';
        $merchant_data['state'] = 'CA';
        $merchant_data['zip'] = '90046';
        $merchant_data['country'] = 'US';
        $merchant_data['lat'] = 34.097990;
        $merchant_data['lng'] = -118.345214;
        $merchant_data['time_zone'] = -8;
    } else {
        $merchant_data['address1'] = '1505 Arapaho Ave';
        $merchant_data['address2'] = null;
        $merchant_data['city'] = 'boulder';
        $merchant_data['state'] = 'CO';
        $merchant_data['zip'] = '80302';
        $merchant_data['country'] = 'US';
        $merchant_data['lat'] = 40.014726;
        $merchant_data['lng'] = -105.274479;
        $merchant_data['time_zone'] = -7;

    }
    //$merchant_data['trans_fee_type'] = 'F';
    //$merchant_data['trans_fee_rate'] = 0.25;
    $merchant_data['cc_processor'] = 'I';
    $merchant_data['phone_no'] = '1234567890';
    $merchant_data['fax_no'] = '1234567890';
    $merchant_data['show_tip'] = 'Y';
    $merchant_data['active'] = 'Y';
    $merchant_data['ordering_on'] = 'Y';
    $merchant_data['lead_time'] = 20;
    $merchant_data['delivery'] = 'Y';
    $merchant_data['nutrition_flag'] = "1";
    $merchant_controller = new MerchantController($mt, $u, $r);
    $merchant_data['nutrition_message'] = $merchant_controller->getNutritionMessage();
    $merchant_resource = $merchant_adapter->createMerchant($merchant_data);
    $merchant_id = $merchant_resource->merchant_id;
    if ($data['long_hours']) {
        $merchant_resource->set('long_hours',true);
    }
    $merchant_controller->stubOutMerchant($merchant_resource, false);
    logData($merchant_data, "dummy merchant data");

    if ($message_data = $data['message_data']) {
        $mmm_adapter = new MerchantMessageMapAdapter();
        $message_data['message_type'] = isset($message_data['message_type']) ? $message_data['message_type'] : 'X';
        $message_data['merchant_id'] = $merchant_id;
        $mmm_resource = Resource::factory($mmm_adapter, $message_data);
        $mmm_resource->save();
    } else {
        $mmm_resource = $merchant_controller->stubOutMerchantMessageMap($merchant_resource->merchant_id, '' . $merchant_resource->merchant_id . 'dummy@dummy.com');
    }
    $merchant_resource->set("merchant_message_map_id", $mmm_resource->map_id);

    // give it a tax rate
    $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_resource->merchant_id;
    $tax_resource = Resource::find(new TaxAdapter(), null, $options);
    $tax_resource->rate = 10;
    $tax_resource->save();

    // payment types
    //MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);
    if ($data['no_payment'] || $_SERVER['no_new_merchant_payment']) {
        myerror_log("skip creating payment stuff");
    } else {
        $billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);
        if ($data['authorize']) {
            $billing_entity_resource->process_type = 'authorize';
            $billing_entity_resource->save();
        }
        $merchant_payment_type_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);
        $merchant_resource->set('merchant_payment_type_map_id', $merchant_payment_type_map_resource->id);
        $merchant_resource->set('cc_billing_entity_id', $billing_entity_resource->id);
        $merchant_resource->set('billing_entity_external_id', $billing_entity_resource->external_id);
    }

    $skin_adapter = new SkinAdapter();
    $skin_options[TONIC_FIND_BY_METADATA] = array("external_identifier" => 'com.splickit.worldhq');
    if ($records = $skin_adapter->selectLite(null, $options)) {
        $skin_merchant_map_adapter = new SkinMerchantMapAdapter();
        Resource::createByData($skin_merchant_map_adapter, array("merchant_id" => $merchant_resource->merchant_id, "skin_id" => $records[0]['skin_id']));
    }
    if ($skin_id = getSkinIdForContext()) {
        if ($skin_id > 1) {
            attachMerchantToSkin($merchant_resource->merchant_id, $skin_id);
        }
    }

    if ($menu_id > 0) {
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $menu_id, 'pickup');
        MerchantMenuMapAdapter::createMerchantMenuMap($merchant_resource->merchant_id, $menu_id, 'delivery');
    }

    // now check for menu type 3.0  if so then create all the child records
    $menu_record = MenuAdapter::staticGetRecordByPrimaryKey($menu_id, 'MenuAdapter');
    if ($menu_record['version'] > 2.0) {
        $complete_menu = new CompleteMenu($menu_id);
        $item_size_resources = $complete_menu->getAllMenuItemSizeMapResources($menu_id, 'Y', 0);
        myerror_log("we found this many item size maps: " . count($item_size_resources));
        foreach ($item_size_resources as $item_size_resource) {
            $item_size_resource->_exists = false;
            unset($item_size_resource->item_size_id);
            $item_size_resource->merchant_id = $merchant_id;
            $item_size_resource->save();
        }
        $modifier_item_size_resources = $complete_menu->getAllModifierItemSizeResources($menu_id, 'Y', 0);
        myerror_log("we found this many modifier item size maps: " . count($modifier_item_size_resources));
        foreach ($modifier_item_size_resources as $modifieritem_size_resource) {
            $modifieritem_size_resource->_exists = false;
            unset($modifieritem_size_resource->modifier_size_id);
            $modifieritem_size_resource->merchant_id = $merchant_id;
            $modifieritem_size_resource->save();
        }

        $item_modifier_group_resources = $complete_menu->getAllItemModifierGroupMapsAsResources($menu_id, 'Y', 0, true);
        myerror_log("we found this many item modifier group maps: " . count($item_modifier_group_resources));
        foreach ($item_modifier_group_resources as $item_modifier_group_resource) {
            $item_modifier_group_resource->_exists = false;
            unset($item_modifier_group_resource->map_id);
            $item_modifier_group_resource->merchant_id = $merchant_id;
            $item_modifier_group_resource->save();
        }


        $item_modifier_item_price_overrides_by_size_resources = $complete_menu->getAllItemModifierItemPriceOverridesBySizesAsResources($menu_id, 0);
        myerror_log("we found this many item modifier item price overrides by sizes: " . count($item_modifier_item_price_overrides_by_size_resources));
        foreach ($item_modifier_item_price_overrides_by_size_resources as $item_modifier_item_price_overrides_by_size_resource) {
            $item_modifier_item_price_overrides_by_size_resource->_exists = false;
            unset($item_modifier_item_price_overrides_by_size_resource->id);
            $item_modifier_item_price_overrides_by_size_resource->merchant_id = $merchant_id;
            $item_modifier_item_price_overrides_by_size_resource->save();
        }
    }
    return $merchant_resource;
}

function createNewMenu()
{
    $menu_adapter = new MenuAdapter();
    $menu_resource = Resource::createByData($menu_adapter, array('name' => 'Unit Test Menu', 'version' => 2.0));
    return $menu_resource;

}

function createNewMenuType($menu_id, $menu_type_name, $menu_category = 'E')
{
    $mt_resource = Resource::createByData(new MenuTypeAdapter(), array('menu_type_name' => $menu_type_name, 'menu_id' => $menu_id, 'cat_id' => $menu_category));
    return $mt_resource;
}

function createNewMenuTypeWithNNumberOfItems($menu_id, $menu_type_name, $menu_category = 'E', $number_of_items = 1)
{
    $mt_resource = Resource::createByData(new MenuTypeAdapter(), array('menu_type_name' => $menu_type_name, 'menu_id' => $menu_id, 'cat_id' => $menu_category));
    $size_resource = Resource::createByData(new SizeAdapter(), array('size_name' => $menu_type_name . '_size', 'menu_type_id' => $mt_resource->menu_type_id));
    for ($i = 0; $i < $number_of_items; $i++) {
        $item_resource = createItem($menu_type_name . "_Item_" . $i, $size_resource->size_id, $mt_resource->menu_type_id, null, $menu_type_name . "_Item_" . $i . "_print", 100 - $i);
    }
    return $mt_resource;
}

function createNewSize($menu_type_id, $size_name)
{
    $size_resource = Resource::createByData(new SizeAdapter(), array('size_name' => $size_name, 'size_print_name' => $size_name, 'menu_type_id' => $menu_type_id));
    return $size_resource;
}

function createItem($item_name, $size_id, $menu_type_id, $calories = null, $item_print_name = null, $priority)
{
    $item_resource = Resource::createByData(new ItemAdapter(),
        array('item_name' => $item_name,
            'description' => $item_name.' desription',
            'item_print_name' => isset($item_print_name) ? $item_print_name : $item_name,
            'menu_type_id' => $menu_type_id,
            'priority' => $priority,
            'calories' => $calories
        )
    );
    $i = 1;
    $plu = createPLU(5);
    $sizes = is_array($size_id) ? $size_id : [$size_id];
    foreach ($sizes as $size_id) {
        $p = $priority - $i + 1;
        $item_size_map_resource = Resource::createByData(new ItemSizeAdapter(), array('size_id' => $size_id, 'external_id' => $plu . $i++, 'item_id' => $item_resource->item_id, 'price' => 1.50, 'tax_group' => 1, 'priority' => $p));
    }


    createPhoto($item_resource->item_id, "http://example.com/images/" . createUUID(), 479, 479);
    createPhoto($item_resource->item_id, "http://example.com/images/" . createUUID(), 481, 481);

    return $item_resource;
}

function createPhoto($item_id, $url, $height, $width)
{
    $photo_resource = Resource::createByData(new PhotoAdapter(), array('item_id' => $item_id, 'url' => $url, 'height' => $height, 'width' => $width));
    return $photo_resource;
}


function createModifierItem($modifier_item_name, $size_id, $modifier_group_id, $priority = 1)
{
    $modifier_item_resource = Resource::createByData(new ModifierItemAdapter(), array('modifier_item_name' => $modifier_item_name, 'modifier_item_print_name' => $modifier_item_name, 'modifier_group_id' => $modifier_group_id, 'priority' => $priority));
    $plu = createPLU(5);
    $price = .50;
    if ($modifier_item_name == 'Quantity Item') {
        $price = 0.00;
        $plu = '';
    }
    $modifier_size_map_resource = Resource::createByData(new ModifierSizeMapAdapter(), array('size_id' => $size_id, 'external_id' => $plu, 'modifier_item_id' => $modifier_item_resource->modifier_item_id, 'modifier_price' => $price));
    $modifier_item_resource->set('modifier_size_map',$modifier_size_map_resource);
    return $modifier_item_resource;
}

function createTestMenu($category_of_items, $number_of_items)
{
    $menu_resouce = createNewMenu();
    $menu_id = $menu_resouce->menu_id;

    $menu_type_resource = createNewMenuType($menu_id, 'Test Menu Type 1', $category_of_items);
    $size_resource = createNewSize($menu_type_resource->menu_type_id, 'Test Size 1');

    for ($i = 1; $i < $number_of_items + 1; $i++)
        createItem("Test Item " . $i, $size_resource->size_id, $menu_type_resource->menu_type_id);

    return $menu_id;
}

function createTestCateringMenuWithOneItem($item_name)
{
    $menu_resource = createNewMenu();
    $menu_id = $menu_resource->menu_id;
    $menu_type_resource = createNewMenuType($menu_id, 'Test Menu Type 1', 'C');
    $size_resource = createNewSize($menu_type_resource->menu_type_id, 'Test Size 1');
    createItem($item_name, $size_resource->size_id, $menu_type_resource->menu_type_id);
    return $menu_id;
}


function createTestMenuWithOneItem($item_name)
{
    $menu_resource = createNewMenu();
    $menu_id = $menu_resource->menu_id;
    $menu_type_resource = createNewMenuType($menu_id, 'Test Menu Type 1');
    $size_resource = createNewSize($menu_type_resource->menu_type_id, 'Test Size 1');
    createItem($item_name, $size_resource->size_id, $menu_type_resource->menu_type_id);
    return $menu_id;
}

function createTestMenuWithNnumberOfItems($number_of_items, $menu_id = 0)
{
    $menu_resouce = createNewMenu();
    if ($menu_id > 1000) {
        $current_menu_id = $menu_resouce->menu_id;
        $sql = "Update Menu SET menu_id = $menu_id WHERE menu_id = $current_menu_id";
        $menu_adapter = new MenuAdapter();
        $menu_adapter->_query($sql);
    } else {
        $menu_id = $menu_resouce->menu_id;
    }
    $menu_type_resource = createNewMenuType($menu_id, 'Test Menu Type 1');
    $size_resource = createNewSize($menu_type_resource->menu_type_id, 'Test Size 1');
    $priority = 200;
    for ($i = 1; $i < $number_of_items + 1; $i++) {
        createItem("Test Item " . $i, $size_resource->size_id, $menu_type_resource->menu_type_id, null, null, $priority--);
    }
    return $menu_id;
}

function createTestMenuWithNnumberOfItemsAndMenuTypes($number_of_items, $menu_id = 0, $number_of_menu_types = 1, $number_of_sizes = 1)
{
    $menu_resouce = createNewMenu();
    if ($menu_id > 1000) {
        $current_menu_id = $menu_resouce->menu_id;
        $sql = "Update Menu SET menu_id = $menu_id WHERE menu_id = $current_menu_id";
        $menu_adapter = new MenuAdapter();
        $menu_adapter->_query($sql);
    } else {
        $menu_id = $menu_resouce->menu_id;
    }
    for ($j = 0; $j < $number_of_menu_types; $j++) {
        $k = $j + 1;
        $size_resources = [];
        $menu_type_resource = createNewMenuType($menu_id, "Test Menu Type $k");
        for ($s = 0; $s < $number_of_sizes; $s++) {
            $ss = $s + 1;
            $size_resource = createNewSize($menu_type_resource->menu_type_id, "Size $k-$ss");
            $size_resources[] = $size_resource->size_id;
        }


        for ($i = 1; $i < $number_of_items + 1; $i++) {
            createItem("Test Item " . $i, $size_resources, $menu_type_resource->menu_type_id);
        }
    }

    return $menu_id;

}

function createTestMenuWithCaloriesInfo($number_of_items)
{
    $menu_resource = createNewMenu();
    $menu_id = $menu_resource->menu_id;
    $menu_type_resource = createNewMenuType($menu_id, 'Test Menu Calories');
    $size_resource = createNewSize($menu_type_resource->menu_type_id, 'Test Size To Calories');
    $priority = 100;
    for ($i = 1; $i < $number_of_items + 1; $i++) {
        $calories = $i * 100;
        createItem("Item " . $i, $size_resource->size_id, $menu_type_resource->menu_type_id, $calories . ' - ' . $calories * 2, null, $priority--);
    }
    return $menu_id;
}

function createNewModifierGroup($menu_id, $modifier_group_name = 'Test Group', $modifier_group_type = 'T')
{
    $modifier_group_name = ($modifier_group_name != 'Test Group') ? $modifier_group_name : $modifier_group_name . ' ' . generateCode(4);
    $mg_resource = Resource::createByData(new ModifierGroupAdapter(), array('modifier_group_name' => $modifier_group_name, 'menu_id' => $menu_id, 'modifier_type' => $modifier_group_type));
    return $mg_resource;
}

/**
 *
 * @desc creates a modifier group with N number of items, sets price at .50 for all modifiers
 * @param $menu_id
 * @param $number_of_modifier_items
 * @param $modifier_group_type (defaults to 'T')
 * @return Resource
 *
 */

function createModifierGroupWithNnumberOfItems($menu_id, $number_of_modifier_items, $modifier_group_name = 'Test Modifier Group', $modifier_group_type = 'T')
{
    $modifier_group_resource = createNewModifierGroup($menu_id, $modifier_group_name, $modifier_group_type);

    for ($i = 1; $i < $number_of_modifier_items + 1; $i++) {
        $modifier_items[] = createModifierItem("$modifier_group_name Item " . $i, "0", $modifier_group_resource->modifier_group_id, $i * 10);
    }
    $modifier_group_resource->set("modifier_items", $modifier_items);
    return $modifier_group_resource;
}

function createQuantityModifierGroup($menu_id)
{
    $modifier_group_resource = createNewModifierGroup($menu_id, 'Quantity', 'Q');
    $modifier_items[] = createModifierItem("Quantity Item", "0", $modifier_group_resource->modifier_group_id,10);
    $modifier_group_resource->set("modifier_items", $modifier_items);
    return $modifier_group_resource;
}

function assignModifierGroupToItemWithFirstNAsComesWith($item_id, $modifier_group_id, $first_N_as_comes_with,$display_name = 'Please Name Me')
{
  $modifier_item_adapter = new ModifierItemAdapter();
  $records = $modifier_item_adapter->getRecords(array("modifier_group_id" => $modifier_group_id));
  $item_modifier_group_map_resource = Resource::createByData(new ItemModifierGroupMapAdapter(), array("item_id" => $item_id, "modifier_group_id" => $modifier_group_id, "max" => sizeof($records),"display_name"=>$display_name));
    for ($i = 0; $i < $first_N_as_comes_with; $i++) {
        $modifier_item_id = $records[$i]['modifier_item_id'];
        $item_modifier_item_map_resource = Resource::createByData(new ItemModifierItemMapAdapter(), array("item_id" => $item_id, "modifier_item_id" => $modifier_item_id));
    }
    return $item_modifier_group_map_resource;
}

function createMessages($number_of_messages_to_create, $merchant_id = 1080, $message_format = 'E', $locked = 'N', $erase = true)
{
    if (isProd())
        die("not to be used in prod");
    $mmh_adapter = new MerchantMessageHistoryAdapter();
    if ($erase) {
        $sql = "UPDATE Merchant_Message_History SET locked = 'F' WHERE (locked = 'N' OR locked = 'P' OR locked = 'Y')";
        $mmh_adapter->_query($sql);
    }
    $mmh_data['merchant_id'] = $merchant_id;
    $mmh_data['order_id'] = 12345;
    $mmh_data['message_format'] = $message_format;
    $mmh_data['locked'] = $locked;
    $mmh_data['message_delivery_addr'] = 'arosenthal@dummy.com';
    if ($locked == 'P')
        $mmh_data['message_delivery_addr'] = 'pulled';
    if ($message_format == 'P')
        $mmh_data['message_delivery_addr'] = '127.0.0.1';
    for ($i = 0; $i < $number_of_messages_to_create; $i++) {
        $num = mt_rand(10, 90);
        $mmh_data['message_text'] = 'Hello World ' . $num;
        if ($message_format == 'T')
            $mmh_data['message_text'] = '*** ' . $num;
        $mmh_data['next_message_dt_tm'] = time() - $num;
        $mmh_data['order_id']++;
        $message_resource = Resource::factory($mmh_adapter, $mmh_data);
        $message_resource->save();
    }
}

function createGift($giftee_user_resource, $gift_amt = 0.00)
{
    $gift_data['receiver_email'] = $giftee_user_resource->email;
    if ($gift_amt == 0.00)
        $gift_amt = rand(500, 1500) / 100;
    $gift_data['amt'] = $gift_amt;

    $gift_data['expires_on'] = date("Y-m-d", time() + (14 * 24 * 60 * 60));
    $gift_data['personal_note'] = "here is the note";
    $json_encoded_data = json_encode($gift_data);

    $request = new Request();
    $request->method = "post";
    $request->body = $json_encoded_data;
    $request->mimetype = 'Applicationjson';
    $request->_parseRequestBody();
    $gift_adapter = new GiftAdapter();
    $gift_resource = $gift_adapter->createGiftFromRequest($request);
    return $gift_resource;
}

function getSimpletCartArrayByMerchantId($merchant_id, $menu_type = 'pickup', $note = 'some note', $item_count = 1)
{
    return OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id, $menu_type, $note, $item_count);
}


function placeOrderFromOrderDataAPIV1($order_data, $time_stamp)
{
    if ($time_stamp < 1000) {
        $time_stamp = time();
    }
    $user = getUserFromId($order_data['user_id']);
    logTestUserIn($user['user_id']);

    //set_actual_pickup_time
    $lead_time = isset($order_data['lead_time']) ? $order_data['lead_time'] : 20;
    $order_data['actual_pickup_time'] = isset($order_data['actual_pickup_time']) ? $order_data['actual_pickup_time'] : $time_stamp + ($lead_time * 60);

    $mptma = new MerchantPaymentTypeMapsAdapter();
    $records = $mptma->getRecords(array("merchant_id" => $order_data['merchant_id']));
    $payment_record = array_pop($records);
    $order_data['merchant_payment_type_map_id'] = $payment_record['id'];
    $request = createRequestObject("/phone/placeorder", "POST", json_encode($order_data));
    $place_order_controller = new PlaceOrderController($mt, $user, $request);
    $place_order_controller->setCurrentTime($time_stamp);
    return $place_order_controller->placeOrderFromRequest();

}

/**
 * @param $order_data mixed
 * @param $time_stamp int
 * @return Resource
 */
function placeOrderFromOrderData($order_data, $time_stamp)
{
    // this is the old way i used to send place orders. i refactored this so i wouldn't have to change ALL the tests.
    // ANY new test should NOT use this function
    if ($time_stamp == null) {
        $time_stamp = time();
    }
    $user = logTestUserIn($order_data['user_id']);
    $json_encoded_data = json_encode($order_data);
    $pickup_request = createRequestObject('/app2/apiv2/cart/checkout', 'POST', $json_encoded_data, 'application/json');
    $place_order_controller = new PlaceOrderController($mt, $user, $pickup_request);
    $place_order_controller->setCurrentTime($time_stamp);
    $checkout_resource = $place_order_controller->processV2Request();
    if ($checkout_resource->hasError()) {
        return $checkout_resource;
    }
    $accepted_payment_types_hash = createHashmapFromArrayOfArraysByFieldName($checkout_resource->accepted_payment_types, 'name');
    if ($order_data['cash'] == 'Y') {
        $checkout_resource->accepted_payment_types = array($accepted_payment_types_hash['Cash']);
    } else if (isset($accepted_payment_types_hash['Credit Card'])) {
        $checkout_resource->accepted_payment_types = array($accepted_payment_types_hash['Credit Card']);
    }

    // $order_data['merchant_payment_type_map_id'] = 1000;

    if ($order_data['actual_pickup_time']) {
        $checkout_resource->lead_times_array = array($order_data['actual_pickup_time']);
    } else if ($order_data['delivery_time']) {
        $checkout_resource->lead_times_array = array($order_data['delivery_time']);
    }
    return placeOrderFromCheckoutResource($checkout_resource, $user, $checkout_resource->merchant_id, $order_data['tip'], $time_stamp);

//  $request = new Request();
//  $place_order_controller = new PlaceOrderController($mt, $_SERVER['AUTHENTICATED_USER'], $request, 5);
//  if ($time_stamp)
//    $place_order_controller->setCurrentTime($time_stamp);
//  $order_resource = $place_order_controller->placeOrderForUnitTestWithOrderData($order_data);
//  return $order_resource;
}

function getCheckoutDataWithThrottling($order_data, $merchant_id, $time_stamp)
{
    $user = logTestUserIn($order_data['user_id']);
    $json_encoded_data = json_encode($order_data);
    $pickup_request = createRequestObject('/app2/apiv2/cart/checkout', 'POST', $json_encoded_data, 'application/json');
    $place_order_controller = new PlaceOrderController($mt, $user, $pickup_request);
    $place_order_controller->setCurrentTime($time_stamp);
    return $place_order_controller->processV2Request();

//  $place_order_controller = new PlaceOrderController($mt, $_SERVER['AUTHENTICATED_USER'], $request, 5);
//  if ($time_stamp)
//    $place_order_controller->setCurrentTime($time_stamp);
//  $time1 = microtime(true);
//  $checkout_data_resource = $place_order_controller->getCheckoutDataFromOrderData($order_data);
//  $time2 = microtime(true);
//  $total_time = ($time2 - $time1) / 1000;
//  myerror_log("the time for get checkout data is: " . $total_time . " seconds");
//  return $checkout_data_resource;
}

function getAuthenticatedUser()
{
    return $_SERVER['AUTHENTICATED_USER'];
}

/**
 *
 * @desc returns a timestamp for twelve noon devner if no time zone string is passed in
 * @param $time_zone
 * @return int
 */
function getTodayTwelveNoonTimeStampDenver($time_zone = "America/Denver")
{
    return getTimeStampForDateTimeAndTimeZone(12, 0, 0, date('m'), date('d'), date('Y'), $time_zone);
}

function getTomorrowTwelveNoonTimeStampDenver($time_zone = "America/Denver")
{
    return (getTodayTwelveNoonTimeStampDenver() + 24 * 60 * 60);
}

function run_sql_file($location)
{
    //load file
    $commands = file_get_contents($location);

    //delete comments
    $lines = explode("\n", $commands);
    $commands = '';
    foreach ($lines as $line) {
        $line = trim($line);
        $start = substr(trim($line), 0, 2);
        if ($line && $start != '--') {
            $commands .= $line . "\n";
        }
    }

    //convert to array
    $commands = explode(";", $commands);

    $merchant_adapter = new MerchantAdapter();
    //run commands
    $total = $success = 0;
    foreach ($commands as $command) {
        if (trim($command)) {
            if ($merchant_adapter->_query($command))
                $success++;
            else
                myerror_log("WE HAD AN sql failure importing merchants: " . $merchant_adapter->getLastErrorText());
            $total++;
        }
    }

    //return number of successful queries and total number of queries found
    return array(
        "success" => $success,
        "total" => $total
    );
}

function setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records)
{
    $better_hash = array();
    foreach ($balance_change_records as $record) {
        $key = $record['user_id'] . '-' . $record['process'];
        $better_hash[$key] = $record;
    }
    return $better_hash;
}

function createSageBillingEntity($brand_id)
{
    $merchant_id_key = generateCode(10);
    $merchant_id_number = generateCode(5);
    $data['vio_selected_server'] = 'sage';
    $data['name'] = "Test Billing Entity";
    $data['description'] = 'An entity to test with';
    $data['merchant_id_key'] = $merchant_id_key;
    $data['merchant_id_number'] = $merchant_id_number;
    $data['brand_id'] = $brand_id;
    $card_gateway_controller = new CardGatewayController($mt, $u, $r);
    $resource = $card_gateway_controller->createPaymentGateway($data);
    return $resource;
}

function createHeartlandBillingEntity($brand_id)
{
    $merchant_id_key = generateCode(10);
    $merchant_id_number = generateCode(5);
    $secretApiKey = generateAlphaCode(15);
    $data['vio_selected_server'] = 'heartland';
    $data['name'] = "Test Billing Entity";
    $data['description'] = 'An entity to test with';
    $data['merchant_id_key'] = $merchant_id_key;
    $data['merchant_id_number'] = $merchant_id_number;
    $data['secretApiKey'] = $secretApiKey;
    $data['brand_id'] = $brand_id;
    $card_gateway_controller = new CardGatewayController($mt, $u, $r);
    $resource = $card_gateway_controller->createPaymentGateway($data);
    return $resource;
}


function createHashOfRecieptItemsByTitle($recipt_items)
{
    $hash_by_title = array();
    foreach ($recipt_items as $row) {
        $hash_by_title[$row['title']] = $row['amount'];
    }
    return $hash_by_title;
}

function addDummyCCDataToResource(&$resource)
{
    addCCtoResource($resource, "05/2029", "4111111111111111", "123", "45678");
}

function addCCtoResource(&$resource, $cc_exp, $cc_number, $cvv, $zip)
{
    $resource->set("cc_exp_date", "$cc_exp");
    $resource->set("cc_number", "$cc_number");
    $resource->set("cvv", "$cvv");
    $resource->set("zip", "$zip");
}

function setUserBalance($user_id, $balance)
{
    $sql = "UPDATE User SET balance = $balance WHERE user_id = $user_id LIMIT 1 ";
    $adapter = new UserAdapter();
    return $adapter->_query($sql);
}

function makeLocalhostRequest($url, $userpassword, $method = 'GET', $data = null, $type = 'application/json', $headers = array())
{
    //$headers = array("SPLICKIT_CLIENT_ID:$public_client_id","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:".get_class($this),"NO_CC_CALL:true");
    $curl = curl_init($url);
    if (strtoupper($method) == 'POST' || strtoupper($method) == 'PUT') {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        if ($method == 'PUT') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        } else {
            curl_setopt($curl, CURLOPT_POST, 1);
        }
        $headers[] = "Content-type: $type; charset=utf-8";
        $headers[] = 'Content-Length: ' . strlen($data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    logCurl($url, $method, $userpassword, $headers, $data);
    $result = curl_exec($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);
    return array("result" => $result, "curl_info" => $info);
}

function createRequestObject($url, $method, $body, $mimetype = 'application/json')
{
    $u = parse_url($url);
    if (strtolower($method) == 'get') {
        $_GET = true;
    }
    $_SERVER['QUERY_STRING'] = $u['query'];
    $request = new Request();
    $request->method = strtoupper($method);
    $request->url = $u['path'];
    $request->parseRequestUrl($u['path']);
    $request->body = $body;
    $request->mimetype = $mimetype;
    $request->_parseRequestBody();
    return $request;
}

function getPlaceOrderDataFromCheckoutResource($checkout_resource, $tip = 0.00)
{
    $base_order_data = CompleteOrder::getBaseOrderDataAsResource($checkout_resource->cart_ucid, $m);
    $order_data = array();
    $order_data['merchant_id'] = $base_order_data->merchant_id;
    $order_data['note'] = "the new cart note";
    $order_data['user_id'] = $base_order_data->user_id;
    $order_data['cart_ucid'] = $checkout_resource->cart_ucid;
    $order_data['tip'] = $tip;
    $payment_array = $checkout_resource->accepted_payment_types;
    $order_data['merchant_payment_type_map_id'] = $payment_array[0]['id'];
    $order_data['requested_time'] = $checkout_resource->lead_times_array[0];
    return $order_data;
}

function makeUnitTestRequest($url, $userpassword, $method = 'GET', $data = null)
{
    unset($_SERVER['TEST']['info']);
    $method = strtoupper($method);
    $curl = curl_init($url);
    if ($userpassword) {
        curl_setopt($curl, CURLOPT_USERPWD, $userpassword);
    }
    $client_id = getPublicClientIdForContext();
    $headers = array("SPLICKIT_CLIENT_ID:$client_id", "X_SPLICKIT_CLIENT_DEVICE:unit_testing", "X_SPLICKIT_CLIENT:APIDispatchTest", "NO_CC_CALL:true");
    if ($authentication_token = $data['splickit_authentication_token']) {
        $headers[] = "splickit_authentication_token:$authentication_token";
    }
    if ($data['headers']) {
        $headers = $data['headers'];
    }
    if ($method == 'POST') {
        curl_setopt($curl, CURLOPT_POST, 1);
        if ($data != null) {
            $json = json_encode($data);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($json);
        }
    } else if ($method == 'DELETE') {
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
    }
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    logCurl($url, $method, $userpassword, $headers, $json);
    $result = curl_exec($curl);
    $_SERVER['TEST']['info'] = curl_getinfo($curl);
    curl_close($curl);
    return $result;
}

function getEmptyCart($user,$merchant_id)
{
    $cart_data = [];
    $cart_data['user_id'] = $user['uuid'];
    $cart_data['merchant_id'] = $merchant_id;
    $cart_data['items'] = [];
    return $cart_data;
}

function getCompleteMenuItemFromCompleteMenu($complete_menu,$item_id)
{
    foreach ($complete_menu['menu_types'] as $menu_type) {
        foreach ($menu_type['menu_items'] as $item) {
            if ($item['item_id'] == $item_id) {
                return $item;
            }
        }
    }
}

function makeMockRequest($controller,$method = 'processV2Request')
{
    try {
        $resource = $controller->$method();
    } catch (Exception $e) {
        $resource = createErrorResourceWithHttpCode("Sorry there was an internal error. Our engineering team has been notified",500,500);
    }
    $response = getV2ResponseWithJsonFromResource($resource, null);
    return $response->body;

}

/**
 * @param $controller SplickitController
 * @return str|null
 */
function makeMockPortalRequest($controller)
{
    $resource = $controller->processV2Request();
    $response = getPortalResponseWithJsonFromResource($resource, null);
    return $response->body;
}

include_once 'tonic' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'request.php';
include 'lib' . DIRECTORY_SEPARATOR . 'utilities' . DIRECTORY_SEPARATOR . 'dispatch_functions.inc';

?>
