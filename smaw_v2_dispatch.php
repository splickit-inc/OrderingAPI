<?php
if ($_SERVER['SCRIPT_URL'] == '/app2/apiv2/healthcheck') {
	header('Content-Type: Application/json');
    $result = json_encode(array("success"=>"true"));
    header('Content-Length: '.strlen($result));
    echo $result;
	die();
}
// last php5 branch commit here
$_SERVER['request_time1'] = microtime(true);
error_reporting(E_ERROR|E_COMPILE_ERROR|E_COMPILE_WARNING|E_PARSE);
ini_set('display_errors','0');
include 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'functions.inc';
include 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'dispatch_functions.inc';
$user_name_for_log = $_SERVER['PHP_AUTH_USER'];
if ($user_name_for_log == 'splickit_authentication_token') {
    $user_name_for_log = $_SERVER['PHP_AUTH_PW'];
} else if ($user_name_for_log == 'facebook_authentication_token') {
    $user_name_for_log = 'facebook-'.$_SERVER['PHP_AUTH_PW'];
}
logData($_SERVER,"server",6);
myerror_log("********* starting V2 dispatch.php *********  $user_name_for_log  *******");
$request = new Request();
if (extension_loaded ('newrelic')) {
	$raw_url = $request->url;
    $clean_url = preg_replace('%/([0-9]{4}-[0-9a-z]{5}-[0-9a-z]{5}-[0-9a-z]{5})%','/ID',$raw_url);
    $url_parts = explode('?',$clean_url);
    newrelic_name_transaction ($url_parts[0]);
}
try {

	if (substr($request->url,-6) == '/apiv2') {
		getAndRespondWithAPIDocs();
		die();
	} else if (substr_count($request->url,'/menustatus') > 0 ) {
		$resource = CompleteMenu::getMenuStatus($request,$mimetypes);
		$response = getV2ResponseWithJsonFromResource($resource, $headers);
		$response->output();
		die();
	}
	$body=$request->validateAndParseRequestBody();
    $_SERVER['request_body'] = $body;
} catch (Exception $e) {
	myerror_log("Error throw trying to parse request body: ".$e->getMessage());
	$response = getResponseWithJsonForError($e->getMessage(), $e->getCode(), $error_title, $text_for_button, $fatal, $url,422);
}
myerror_logging(5, "request url: ".$request->url);
if (getBaseLogLevel() > 4) {
	$json_string_of_request = json_encode($request);
	$pattern = '/"password":"[A-Za-z0-9!#$%&-_]{3,20}"/';
	$json_string_of_request = preg_replace($pattern, '"password":"xxxxxx"', $json_string_of_request);
	myerror_log( "request object: ".$json_string_of_request);
}
$_SERVER['request_url'] = $request->fullUrl;
// we should exit dispatch login with a good user resource.
include 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'dispatch_login.inc';
logData($login_headers, "All Headers",5);

if (isLoggedInUserStoreTesterLevelOrBetter()) {
    myerror_log("logged in user is store tester or better so let logging to 5");
    $log_level = 5;
    $_SERVER['log_level'] = 5;
}


try {
		if (substr_count($request->fullUrl,'/grouporders') > 0 ) {
        $group_order_controller = new GroupOrderController($mt, getLoggedInUser(), $request);
        $resource = $group_order_controller->processV2Request();
	} else if (substr_count($request->fullUrl, '/skins') > 0 ) {
		$skin_controller = new SkinController($mt, getLoggedInUser(), $request);
		$resource = $skin_controller->processRequest();
	} else if (substr_count($request->fullUrl, '/pos/') > 0 ) {
		$pos_controller = new PosController($mt, $user, $request, 5);
		$resource = $pos_controller->processV2Request();
	} else if (substr_count($request->url, '/items') > 0){
		$items_controller = new ItemsController($mt, getLoggedInUser(), $request);
		$resource = $items_controller->processV2Request();
	} else if ($authorized) {
		if (substr_count($request->fullUrl,'/users') > 0 ) {
			$user_controller = new UserController($mt, getLoggedInUser(), $request);	
			$resource = $user_controller->processV2Request();
		} else if (substr_count($request->fullUrl,'/merchants') > 0 ) {
			$merchant_controller = new MerchantController($mt, getLoggedInUser(), $request);
			$resource = $merchant_controller->processV2Request();
		} else if ($user['email'] == 'admin' || $user['user_id'] == 9999) {
			// admin user should NOT be able to access anything beyond this point
			myerror_log("ERROR!  admin endpoint not allowed!");
			respondWithPlainTextBody('Unauthorized access', 401);
			die;
        } else if (substr_count($request->url,'/catering') > 0 ) {
            $catering_controller = new CateringController($mt, getLoggedInUser(), $request);
            $resource = $catering_controller->processV2Request();
        } else if (substr_count($request->url,'/orders') > 0 ) {
            $place_order_controller = new PlaceOrderController($mt, getLoggedInUser(), $request);
            $resource = $place_order_controller->processV2Request();
            if (isset($resource->error)) {
                if (isset($resource->http_code)) {
                    $code = $resource->http_code;
                } else {
                    $code = 'NONE';
                }
                myerror_log("ORDER ERROR: ".$resource->error."     http_code: ".$code);
            }
        } else if (substr_count($request->url,'/favorites') > 0 ) {
				$favorite_controller = new FavoriteController($mt, getLoggedInUser(), $request);
				$resource = $favorite_controller->processV2Request();
		} else if (substr_count($request->fullUrl,'/validatepromo') > 0 ) {
			$promo_controller = new PromoController($mimetypes,getLoggedInUser(),$request,1);
			$resource = $promo_controller->validatePromo($order_data);
		} else if (substr_count($request->fullUrl,'/cart') > 0 ) {
			$place_order_controller = new PlaceOrderController($mt, getLoggedInUser(), $request);
			$resource = $place_order_controller->processV2Request();
		} else {
			$resource = createErrorResourceWithHttpCode("NEED ENDPOINT ON smaw_v2_dispatch.php", 488, $error_code);
		}
	} else if ($resource == null) {
        myerror_log("bad anonymous request: ".$request->fullUrl);
        $resource = createErrorResourceWithHttpCode("Authentication failure", 401, $error_code);
    }
} catch (MerchantDeliveryException $mde) {
	$resource = createErrorResourceWithHttpCode($mde->getMessage(),500,500);
} catch (Exception $e) {
	// some excpetion was thrown.  package it up in a response
	myerror_log("HOLY COW!  we had an exception thrown! message: ".$e->getMessage());
	recordError("Error thrown on smaw_dispatch", "error: ".$e->getMessage());
	$resource = createErrorResourceWithHttpCode("Sorry there was an internal error. Our engineering team has been notified",500,500);
}
myerror_logging(3,"about to build the response");
$final_response = isset($response) ? $response : getV2ResponseWithJsonFromResource($resource, $headers);
$final_response->output();
?>
