<?php
$_SERVER['request_time1'] = microtime(true);

error_reporting(E_ERROR|E_COMPILE_ERROR|E_COMPILE_WARNING|E_PARSE);
set_time_limit(1000);
//error_reporting(E_ALL);
//ini_set('display_errors','1');

$path = $_SERVER['DOCUMENT_ROOT'].'/app2/';

set_include_path(get_include_path() . PATH_SEPARATOR . $path);

include 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'functions.inc';
include 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'dispatch_functions.inc';
include 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'SmsInterface.inc';
include 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'smlrpc.inc';

$_SERVER['STAMP'] = 'portal-'.$code;

myerror_log("******starting smaw_portal_dispatch*********");
logData(getallheaders(),"HEADERS",3);
$request = new Request();
if (extension_loaded ('newrelic')) {
    newrelic_name_transaction ($request->url);
}
$request->_parseRequestBody();
myerror_log('request url: '.$request->url);
$_SERVER['request_url'] = $request->url;
logData($request->data,"REQUEST DATA",3);
if ($brand_name = $request->data['brand_name']) {
	myerror_log("WE HAVE A SUBMITTED BRAND NAME: $brand_name");
	if ($skin = SkinAdapter::getSkin('com.splickit.'.$brand_name)) {
		$skin_id = $skin['skin_id'];
		setGlobalSkinValuesForContextAndDevice($skin,'PortalDispatch');
	}
}

$resource = new Resource();
$log_level = isset($request->data['log_level']) ? $request->data['log_level'] : $global_properties['log_level_portal'];
if (getCapitalizedRequestMethod($request) != 'GET') {
    myerror_log("Setting custom log level to 5 on portal becuase request is NOT a GET");
    $log_level = 5;
}
myerror_log("we have set the log level to $log_level on the portal dispatch");
$_SERVER['log_level'] = $log_level;
$_SERVER['PORTAL_REQUEST'] = true;

if ($user_id = $request->data['user_id']) {
	$user_adapter = new UserAdapter($mimetypes);
	if (is_numeric($user_id)) {
        $user = $user_adapter->getRecord(array("user_id" => $user_id));
    } else {
        $user = $user_adapter->getRecord(array('email'=>$user_id));
	}
}
try {
    if (substr_count($request->url, '/pos/') > 0 ) {
        $pos_controller = new PosController($mt, $user, $request, 5);
        $pos_controller->setAdmin();
        $resource = $pos_controller->processV2Request();
    } else if (substr_count($request->url, '/import/') > 0 ) {
        $pos_controller = new PosController($mt, $user, $request, 5);
        $resource = $pos_controller->processV2Request();
    } else if (substr_count($request->url,'/flushcache')) {
        $splickit_cache = new SplickitCache();
        $resource = $splickit_cache->processCachBustRequest($request->url);
    } else if (substr_count($request->url,'/pushmessage/')) {
        $pmc = new PushMessageController($m, $u, $request);
        $pmc->pushMessageToUserFromRequest();
        $result = array('result' => 'success', 'stamp' => getRawStamp());
        $response = new Response(200, "<html><body>" . json_encode($result) . "</body></html>");
    } else if (substr_count($request->fullUrl,'/messages') > 0 ) {
        $portal_message_controller = new PortalMessageController(getM(),null,$request,getBaseLogLevel());
        $resource = $portal_message_controller->processRequest();
    } else if (substr_count($request->fullUrl,'/orders/') > 0 ) {
        $order_controller = new OrderController(getM(),null,$request);
        $resource = $order_controller->processV2Request();
    } else if (substr_count($request->fullUrl,'/menus') > 0 ) {
        $menu_controller = new MenuController(getM(),null,$request,getBaseLogLevel());
        $resource = $menu_controller->processV2Request();
    } else if (substr_count($request->fullUrl,'/promos') > 0 ) {
        $promo_controller = new PromoController(getM(),null,$request,getBaseLogLevel());
        $resource = $promo_controller->processV2Request();
    } else if (substr_count($request->fullUrl,'/brands') > 0 ) {
        $brand_controller = new BrandController(getM(),null,$request,getBaseLogLevel());
        $resource = $brand_controller->processRequest();
    } else if (substr_count($request->fullUrl,'/payments') > 0 ) {
        $card_gateway_controller = new CardGatewayController(getM(),null,$request,getBaseLogLevel());
        $resource = $card_gateway_controller->processRequest();
    } else {
		$resource = createErrorResourceWithHttpCode("No known endpoint",422,0);
	}// end if mercant_menu or menuitem else

} catch (Exception $e) {
    myerror_log("there was an error: ".$e->getMessage());
    //$resource = createErrorResourceWithHttpCode($e->getMessage(),422,422);
    $error = $e->getMessage();
    $resource = createErrorResourceWithHttpCode($error,500,500);
}
myerror_log("THS IS THE RESOURCE");
Resource::encodeResourceIntoTonicFormat($resource);

$final_response = getPortalResponseWithJsonFromResource($resource, $headers);
myerror_log("responding with: ".$final_response->body);
$final_response->output();
?>