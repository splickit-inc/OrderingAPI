<?php
$authorized = false;
$logit = true;
$login_headers = apache_request_headers();
logData($login_headers,"HEADERS",10  );
foreach ($login_headers as $key=>$value) {
	$_SERVER['HTTP_'.$key] = $value;
    $_SERVER['HTTP_'.strtoupper($key)] = $value;
    $request->setHeaderVariable('HTTP_'.$key,$value);
    $request->setHeaderVariable('HTTP_'.strtoupper($key),$value);
    $_SERVER[$key] = $value;
    $_SERVER[strtoupper($key)] = $value;
}

if ($_SERVER['HTTP_X_SPLICKIT_CLIENT_ID'] == 'com.splickit.jerseymikes' || $_SERVER['HTTP_X_SPLICKIT_CLIENT_ID'] == 'com.splickit.jerseymikes-test' || $_SERVER['HTTP_X_SPLICKIT_CLIENT_ID'] == 'com.splickit.rewardr') {
    myerror_log("Killing Request for: ".$_SERVER['HTTP_X_SPLICKIT_CLIENT_ID']);
    die("bad request");
}
if (isset($_SERVER['HTTP_PHP_AUTH_USER'])) {
	$_SERVER['PHP_AUTH_USER'] = $_SERVER['HTTP_PHP_AUTH_USER'];
	$_SERVER['PHP_AUTH_PW'] = $_SERVER['HTTP_PHP_AUTH_PW'];
}

if ($_SERVER['PHP_AUTH_USER'] == 'pt' || $_SERVER['PHP_AUTH_USER'] == 'sys_admin')
{
	$_SERVER['PHP_AUTH_USER'] = 'prod_tester@dummy.com';
	$_SERVER['PHP_AUTH_PW'] = 'spl1ck1t';
} 
else if ($_SERVER['PHP_AUTH_USER'] == 'st' || strtolower(substr($_SERVER['PHP_AUTH_USER'],0,12)) == 'store_tester')
{
	$_SERVER['PHP_AUTH_USER'] = 'store_tester@dummy.com';
	$_SERVER['PHP_AUTH_PW'] = 'spl1ck1t';
}

if ($_SERVER['PHP_AUTH_USER'] == 'admin') {
    $_SERVER['PHP_AUTH_USER'] = 1;
}

if (isTest() && substr_count($_SERVER['PHP_AUTH_USER'],'testable.user') > 0 ) {
	$log_level = 0;
	$_SERVER['log_level'] = 0;
} else if (isLaptop() && $_SERVER['log_level'] < 5) {
	$log_level = 5;
	$_SERVER['log_level'] = 5;
}

//* need to get skin version and device type info
$skin_name = $_SERVER['HTTP_X_SPLICKIT_CLIENT'];
$app_version = $_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'];
if (substr_count($app_version, '.') == 1)
{
	myerror_logging(3,"2 place app version! ".$app_version);
	$app_version = $app_version.'.0';
	myerror_logging(3,"Version corrected to: ".$app_version);
	
}
$_SERVER['APP_VERSION'] = $app_version;
$device_type = $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'];
$external_id_string = $_SERVER['HTTP_X_SPLICKIT_CLIENT_ID'];
$email = $_SERVER['PHP_AUTH_USER'];
if ($external_id_string == "com.splickit.masterpiecedeli") {
	$external_id_string = "com.splickit.masterpiece";
}
if ($request->url_hash['healthcheck']) {
    $external_id_string = 'com.splickit.order';
}
$device_udid = $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'];

myerror_logging(5,"device udid: ".$device_udid);

if ($external_id_string == null || $external_id_string == '') { 
	if (substr_count($request->url,'/importbrinkmerchant/') > 0 ) {
        $brink_import_controller = new BrinkImporter($m);
        $resource = $brink_import_controller->processV2Request($request);
        $response = getV2ResponseWithJsonFromResource($resource, $headers);
        $response->output();
        die();
    } else if (substr_count($request->url, 'deploy.txt') > 0) {
		$output = file_get_contents('deploy.txt');
		$response = createResponseFromText($output, '200', $headers);
		$response->output();
		die();
	} else {
		myerror_log("We have a request with no skin identifier:  ".$_SERVER['HTTP_X_FORWARDED_FOR'].'    '.$_SERVER['REMOTE_ADDR']);
		$response = getResponseWithJsonForError("Unknown or missing client_id", 403, $error_title, $text_for_button, $fatal, $url, $headers, 403);
		$response->output();
		die();	
	}
}

if ($device_type == null || $device_type == '') {
    $device_type = 'unknown';
}
myerror_log("we are about to get and set the context for external id of: $external_id_string",5);
if ($email == 'store_tester@dummy.com') {
    $cache_enabled = false;
    myerror_log("Store tester logging in so we are busting cache for skin: ".$external_id_string);
} else {
    $cache_enabled = true;
}
if ($skin = SkinAdapter::getSkin($external_id_string,$cache_enabled)) {
    if (isNotProd() || $skin['in_production'] == 'Y') {
        $skin_id = $skin['skin_id'];
        setGlobalSkinValuesForContextAndDevice($skin, $device_type);
    } else {
        myerror_log("Production request for APP with in production set to 'N' !!!!  ".$skin['skin_name']);
        $resource = returnErrorResource("Sorry, we are currently offline and not accepting requests.", 90, array("text_title" => "App Offline!", "text_for_button" => "Offline", "fatal" => "true"));
        doAppBombResponse($resource, $skin, $device_type,$request);
    }

}  else {
    myerror_log("ERROR!  we have a bad skin id! $external_id_string");
    saveErrorForEndOfDayReport('BadSkinIdError', 'Error Getting Skin Id in smaw_dispatch. No Matching Id for: ' . $external_id_string);
    respondFromResourceForApiV2(createErrorResourceWithHttpCode("Sorry, something went wrong with the request. This app may have been discontinued.", 401, 999, $error_data));
}

// now save brand stuff
setBrandFromCurrentSkin();
myerror_logging(1,"requst url is: ".$request->url);
$access_string = "skin_name: ".$skin_name."    skin_id: ".$skin_id."    version: ".$app_version."    device: ".$device_type."  requst url is: ".$request->url;
myerror_log("REQUEST DATA: ".$access_string);
$_SERVER['request_data_info_string'] = $access_string;
$_SERVER['device_type'] = $device_type;

$today = date('m-d');
//$app_version = substr($app_version, 0, 3);
myerror_logging(3,"version: $app_version    android minimum for system: ".$global_properties['minimum_android_version']);
myerror_logging(3,"version: $app_version    iphpone minimum for system: ".$global_properties['minimum_iphone_version']);
if (($device_type == 'android' || $device_type == 'iphone') && $skin_id == 4) {
    myerror_log("ERROR! MOES APP BOMB!  $device_type  $app_version");
    $resource = returnErrorResource("Sorry, this app is no longer supported. Please download our new `Moe's Rockin' Rewards` app.", 90, array("text_title" => "App Discontinued!", "text_for_button" => "Upgrade Now", "fatal" => "true"));
    doAppBombResponse($resource, $skin, $device_type,$request);
} else if (($device_type == 'android' && version_compare($app_version, $global_properties['minimum_android_version']) < 0) || ($device_type == 'iphone' && version_compare($app_version, $global_properties['minimum_iphone_version']) < 0)) {
    myerror_log("ERROR!   APP BOMB!  $device_type  $app_version ".getSkinNameForContext());
    $resource = returnErrorResource("Sorry, your app version is no longer supported. Please Upgrade.", 90, array("text_title" => "Version Out Of Date!", "text_for_button" => "Upgrade Now", "fatal" => "true"));
    doAppBombResponse($resource, $skin, $device_type,$request);
} else if (isSystemShutdown() && isUsernameNotABypassUsername($_SERVER['PHP_AUTH_USER'])) {
    $error_resource = createErrorResourceWithHttpCode(getProperty('system_shutdown_message'),200, 200);
    respondFromResourceForApiV2($error_resource, $headers);
} else if ($_SERVER['PHP_AUTH_USER'] == null && $login_headers['splickit_authentication_token'] == null && (substr_count($request->fullUrl,'/merchants') > 0 || substr_count($request->fullUrl,'/airports') > 0 ) || $_SERVER['PHP_AUTH_USER'] == 'nouser') {
	myerror_log("in the anonymous user bypass");
    $_SERVER['PHP_AUTH_USER'] = '9999';
	$app_version = 100.0;
	if ( $_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'] == NULL) {
        $_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'] = 100.0;
    }
    $user_resource = Resource::find(new UserAdapter($m),9999);
    setAuthenticatedUserParametersForSession($user_resource->getDataFieldsReally());
    $authorized = true;
} else {
    $loginAdapter = LoginAdapterFactory::getLoginAdapterForContext();
    if ($app_version == '5.3.0' && !isset($_SERVER['PHP_AUTH_USER']) && $request->url == '/app2/apiv2/users' && $request->method == 'post' && isset($request->data['password']) && isset($request->data['email'])) {
        $_SERVER['PHP_AUTH_USER'] = 'admin';
        $_SERVER['PHP_AUTH_PW'] = 'welcome';
    }
    if (isset($_SERVER['PHP_AUTH_USER']) || $loginAdapter->isValidHeaderTokenSet($login_headers)) {

        // now lets do the actual login
         if ($log_level < 1) {
             myerror_log("starting it all with log level = " . $log_level);
         }

        $bad_login_count = 0;
        $email = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];
        $password = trim($password);
        myerror_logging(3, "user logging in is:     $email");
        myerror_logging(6, "password logging in is:     -$password-");

        // do the hack for dave
        if (substr_count($request->url, '/users/') > 0 && $request->method == 'post') {
            $key_values = '%/([0-9]{5,11})%';
            preg_match($key_values, $request->url, $matches);
            $id = 0;
            if ($matches) {
                $id = str_ireplace('/', '', $matches[0]);
                myerror_logging(3, "WE HAVE AN ID IN the check for daves screw up: " . $id);
                if ($bad_user_resource = Resource::find(new UserAdapter($mimetypes), "" . $id)) {
                    if (substr_count($bad_user_resource->email, '@splickit.dum') > 0) {
                        myerror_log("FIXING DAVES SCREWUP! setting username and password to values in db for user_id: " . $bad_user_resource->user_id);
                        $email = $bad_user_resource->email;
                        $password = 'TlhKDMd8ni6M';
                    }
                }
            }
        }

        $request_all = array_merge($request->data, $login_headers);

        // special user validation will take care of order140,linebuster, gifts, etc......
        if ($user_resource = $loginAdapter->doAuthorizeWithSpecialUserValidation($email, $password, $request_all)) {
            $authorized = true;
            myerror_logging(1, "user " . $user_resource->email . " is AUTHORIZED********");
            $user = $user_resource->getDataFieldsReally();
            logData($user, "user object", 5);
            checkUserIdAndSetAdminFields($user['user_id']);
            setAuthenticatedUserParametersForSession($user);
            if ($user['user_id'] == $global_properties['high_logging_user']) {
                myerror_log("WE HAVE A HIGH LOGGING USER");
                $log_level = 5;
                $_SERVER['log_level'] = 5;
                setSessionProperty("log_level",5);
            }
        } else {
            $resource = $loginAdapter->getErrorResource();
        }
    } else {
        myerror_log("Anonymous request");
    }
}
?>