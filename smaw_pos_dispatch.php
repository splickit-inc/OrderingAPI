<?php
$_SERVER['request_time1'] = microtime(true);
error_reporting(E_ERROR|E_COMPILE_ERROR|E_COMPILE_WARNING|E_PARSE);
ini_set('display_errors','0');
//error_reporting(E_ALL);
$login_headers = apache_request_headers();
include 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'functions.inc';
$log_level = getProperty('log_level_pos');
$_SERVER['log_level'] = $log_level;
include 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'dispatch_functions.inc';
myerror_log("********* starting POS dispatch.php ********");
foreach ($login_headers as $key=>$value) {
    $_SERVER['HTTP_'.$key] = $value;
    $_SERVER[$key] = $value;
}

$request = new Request();
if (extension_loaded ('newrelic')) {
    newrelic_name_transaction ($request->url);
}
if (substr_count($request->url, 'pos/loyalty/00000') > 0 ) {
    die();
}
logData($login_headers, "All Headers",5);
myerror_log("POS LOGGING: the request url is: ".$request->url);
$_SERVER['request_url'] = $request->url;
validatePOSRequest();

try {
    if (isSoapRequest()) {
        myerror_log("POS LOGGING: we have a soap request: ".$request->body);
        $request->parseSoapRequest();
    } else {
        myerror_log("POS LOGGING: request body: ".$request->body);
        $request->validateAndParseRequestBody();
    }
    $_SERVER['request_body'] = $request->body;
} catch (Exception $e) {
    myerror_log("Error throw trying to parse request body: ".$e->getMessage());
    $response = getResponseWithJsonForError($e->getMessage(), $e->getCode(), $error_title, $text_for_button, $fatal, $url,422);
    $response->output();
    die();
}

myerror_log("the current context is: ".getIdentifierNameFromContext(),3);

if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
    $loginAdapter = LoginAdapterFactory::getLoginAdapterForContext();
    myerror_logging(3, "user logging in is:   ".$_SERVER['PHP_AUTH_USER']);
    myerror_logging(6, "password logging in is:     -".$_SERVER['PHP_AUTH_PW']."-");
    if ($user_resource = $loginAdapter->doAuthorizeWithSpecialUserValidation($_SERVER['PHP_AUTH_USER'], trim($_SERVER['PHP_AUTH_PW']), $request_all)) {
        if ($user_resource->user_id > 100) {
            myerror_log("user " . $user_resource->email . " is illegally accesing pos dispatch. Deny auth");
        } else {
            myerror_log("user " . $user_resource->email . " is AUTHORIZED********  for context: ".getIdentifierNameFromContext());
            $user = $user_resource->getDataFieldsReally();
        }
    }
}

try {
    if (substr_count($request->url, '/pos/') > 0 ) {
        $pos_controller = new PosController($mt, $user, $request, 5);
        $resource = $pos_controller->processV2Request();
    } else {
        myerror_log("POS LOGGING: unknown destination: ".$request->url);
        $resource = createErrorResourceWithHttpCode("NOT FOUND", 404, $error_code);
    }
} catch (Exception $e) {
    // some excpetion was thrown.  package it up in a response
    myerror_log("POS LOGGING:  HOLY COW!  we had an exception thrown! message: ".$e->getMessage()."   ".$e->getCode());
    $resource = returnErrorResource($e->getMessage(),$e->getCode(),array("http_code"=>$e->getCode()));
    recordError("Error thrown on smaw_pos_dispatch", "error: ".$e->getMessage());
}
logData($resource->getDataFieldsReally(),"RESOURCE ON POS DISPATCH",5);
myerror_logging(3,"about to build the response");
if ($resource->send_soap_response) {
    $http_code = isset($resource->http_code) ? $resource->http_code : 200;
    $response = new Response($http_code,$resource->soap_body);
    $response->headers['Content-Type'] = 'text/xml; charset=utf-8';
    $response->output();
} else {
    $final_response = getV2ResponseWithJsonFromResource($resource, $headers);
    $final_response->output();
}

?>
