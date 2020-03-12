<?php

if (isset($login_headers['NO_CC_CALL']) && isNotProd()) {
	myerror_log("WE ARE IN THE NO CC CALL!!!!!!!!!");
	require_once "lib".DIRECTORY_SEPARATOR."mocks".DIRECTORY_SEPARATOR."viopaymentcurl.php";
}

function isSoapRequest()
{
	return isset($_SERVER['SOAPAction']);
}

function validatePOSRequest()
{
    myerror_log("Starting validate POS request",3);
	$external_id_string = $_SERVER['HTTP_SPLICKIT_CLIENT_ID'];
	if ($external_id_string != null && trim($external_id_string) != '') {
		$skin_adapter = new SkinAdapter(getM());
		$skin_options[TONIC_FIND_BY_METADATA] = ['public_client_id'=>$external_id_string];
		if ($skin_resource = Resource::find($skin_adapter,null,$skin_options)) {
			return setGlobalSkinValuesForContextAndDevice($skin_resource->getDataFieldsReally(),"POS");
		}
	}
	myerror_log("ERROR!  we have a bad skin id!");
	saveErrorForEndOfDayReport('BadSkinIdError', 'Error Getting Skin Id in smaw_dispatch. No Matching Id for: ' . $external_id_string);
	respondFromResourceForApiV2(createErrorResourceWithHttpCode("Unauthorized Request", 401, 999, null));
}

/****************** methods for request responses **********************/


function isRequestMethodAGet($request)
{
	return (getCapitalizedRequestMethod($request) == 'GET');
}

function isRequestMethodAPost($request)
{
	return (getCapitalizedRequestMethod($request) == 'POST');
}

function isRequestMethodAPut($request)
{
	return (getCapitalizedRequestMethod($request) == 'PUT');
}

function isRequestMethodADelete($request)
{
	return (getCapitalizedRequestMethod($request) == 'DELETE');
}

function getCapitalizedRequestMethod($request)
{
	return strtoupper($request->method);
}

function respondOkWithTextPlainbody($body)
{
	respondWithPlainTextBody($body, 200);
}

function respondWithPlainTextBody($body,$http_code)
{
	$response = new Response($http_code);
	$response->body = $body;
	$response->headers['Content-Type'] = 'text plain';
	$response->output();
	die;		
}

function respondFromResourceForApiV2($resource)
{
    $response = getV2ResponseWithJsonFromResource($resource,$headers);
    $response->output();
    die;
}

function getResponseWithJsonForErrorResource($resource,$headers,$http_code = 400)
{
	return getResponseWithJsonFromHash(convertErrorResourceToErrorHashWithOnlyErrorFieldsContained($resource), $headers,$http_code);
}

function getResponseWithJsonForError($error_text,$error_code,$error_title,$text_for_button,$fatal,$url,$headers,$http_code = 400)
{
	$datafields = array('ERROR' => $error_text,
							'ERROR_CODE' => $error_code,
							'TEXT_TITLE' => $error_title,
							'TEXT_FOR_BUTTON' => $text_for_button,
							'FATAL' => $fatal,
							'URL' => $url,
							'stamp' => getRawStamp()
						);
	return getResponseWithJsonFromHash($datafields, $headers,$http_code);					
}						

/**
 * 
 * @desc will produce the stand V2 json response of data,http_code,stamp
 * @param Resource $resource
 * @param hashmap $headers
 */
function getV2ResponseWithJsonFromResource($resource, $headers, $code = 200)
{
    myerror_logging(3,"starting getV2ResponseWithJsonFromResource");
	$http_code = isset($resource->http_code) ? $resource->http_code : $code;
	$resource_as_hashmap = $resource->getDataFieldsReally();
    $clean_hashmap = ($resource->do_not_clean) ? $resource_as_hashmap : cleanDataForResponse($resource_as_hashmap);
    $response_fields['http_code'] = $http_code;
	$response_fields['stamp'] = getRawStamp();	
	if ($clean_hashmap['error'] == NULL) {
		if (isset($clean_hashmap['data'])) {
			$response_fields['data'] = $clean_hashmap['data'];
		} else {
			$response_fields['data'] = $clean_hashmap;
		}
		$response_fields['message'] = $clean_hashmap['user_message'];
	} else {
        $error = array("error"=>$clean_hashmap['error'],"error_code"=>$clean_hashmap['error_code'],"error_type"=>$clean_hashmap['error_type']);
        if ($clean_hashmap['error_data']) {
            $error["error_data"] = $clean_hashmap['error_data'];
        }
		$response_fields['error'] = $error;
        if ($clean_hashmap['data']) {
            $response_fields['data'] = $clean_hashmap['data'];
        }
	}
	return getResponseWithJsonFromHashV2($response_fields, $headers,$http_code);	
}

function getPortalResponseWithJsonFromResource($resource, $headers, $code = 200)
{
    myerror_logging(3,"starting getPortalResponseWithJsonFromResource");
    $http_code = isset($resource->http_code) ? $resource->http_code : $code;
    $resource_as_hashmap = $resource->getDataFieldsReally();
    $clean_hashmap = ($resource->do_not_clean) ? $resource_as_hashmap : cleanDataForResponse($resource_as_hashmap);
    $response_fields['http_code'] = $http_code;
    $response_fields['stamp'] = getRawStamp();
    if ($clean_hashmap['error_message'] != NULL || $clean_hashmap['error'] != NULL) {
        $error_message = isset($clean_hashmap['error_message']) ? $clean_hashmap['error_message'] : $clean_hashmap['error'];
        $error = array("error_message"=>$error_message,"error_code"=>$clean_hashmap['error_code'],"error_type"=>$clean_hashmap['error_type']);
        if ($clean_hashmap['error_data']) {
            $error["error_data"] = $clean_hashmap['error_data'];
        }
        $response_fields['error'] = $error;
        unset($clean_hashmap['error_message']);
        unset($clean_hashmap['error_type']);
        unset($clean_hashmap['error_code']);
        unset($clean_hashmap['http_code']);
        $response_fields['data'] = $clean_hashmap;
    } else {
        if (isset($clean_hashmap['data'])) {
            $response_fields['data'] = $clean_hashmap['data'];
        } else {
            $response_fields['data'] = $clean_hashmap;
        }
        $response_fields['message'] = $clean_hashmap['user_message'];
    }
    return getResponseWithJsonFromHashV2($response_fields, $headers,$http_code);
}



/**
 * 
 * Enter description here ...
 * @param Resource $resource
 * @param hashmap $headers
 */
function getResponseWithJsonFromResource($resource, $headers, $code = 200)
{
	$resource->cleanResource();
	$http_code = isset($resource->http_code) ? $resource->http_code : $code;
	if ($resource->error) {
		return getResponseWithJsonForErrorResource($resource,$headers,$http_code);
	} else {
		return getResponseWithJsonFromHash($resource->getDataFieldsReally(), $headers,$http_code);
	}
}

function getResponseWithJsonFromResourceData($resource,$headers,$code = 200)
{
	$data = $resource->getDataFieldsReally();
	return getResponseWithJsonFromHash($data, $headers,$code);
}

function createJsonForResponseFromData($data)
{
	if ($data['data']) {
		$data = $data['data'];
	} else {
		$data['stamp'] = getRawStamp();
	}
	$json = json_encode($data);
/*	$json = str_replace('\n', '\\n', $json);
	$json = str_replace('\r', '\\r', $json);
	$json = str_replace('\t', '\\t', $json);
*/	return $json;
}

function getResponseWithJsonFromHash($data,$headers,$code = 200)
{
	try {
		$json = createJsonForResponseFromData($data);
		return createResponseFromJson($json, $code, $headers);
	} catch (Exception $e) {
		myerror_log("error encoding ".$e->getMessage());
		recordError("Problem json encoding on smaw_dispatch", "exact error: ".$e->getMessage());
		$json = json_encode(array("error"=>"There was an internal error","error_code"=>null));
		return createResponseFromJson($json, 500, $headers);
	}
}

function getResponseWithJsonFromHashv2($data,$headers,$code = 200)
{
    myerror_logging(3,"starting getResponseWithJsonFromHashv2");
    try {
		$json = json_encode($data);
		return createResponseFromJson($json, $code, $headers);
	} catch (Exception $e) {
		myerror_log("error encoding ".$e->getMessage());
		recordError("Problem json encoding on smaw_dispatch", "exact error: ".$e->getMessage());
		$json = json_encode(array("error"=>"There was an internal error","error_code"=>null));
		return createResponseFromJson($json, 500, $headers);
	}
}

function createResponseFromJson($json,$code,$headers)
{
    myerror_logging(3,"starting createResponseFromJson");
		$response = new Response($code,$json,$headers);
		$response->headers['Content-Type'] = 'application/json';
		$response->headers['Content-Length'] = strlen($json);
        myerror_log("server log level is: ".$_SERVER['log_level']);
		if ($_SERVER['log_level'] < 6) {
            myerror_log("sending trimmed response x: ".substr($json, 0, 5000),3);
		} else {
            myerror_log("sending response x: ".$json);
        }

		return $response;
}

function createResponseFromText($text, $code, $headers) {
	$response = new Response($code, $text, $headers);
	$response->headers['Content-Type'] = 'text/plain';
	$response->headers['Content-Length'] = strlen($text);
	$output = $response->getOutputAsText();
	if (! ifLogLevelIsGreaterThan(5)) {
		$output = substr($output, 0, 5000);
	}
	myerror_logging(3,"sending response x: ".$output);
	return $response;	
}

function checkForOrderingOffForContext()
{
	;//need to build this out after RLC changes are in and merged with new loyalty
}

function getStaticAssetRequest($request) 
{
	$ac = new AssetsController();
	$response = $ac->getAsset($request);
	$response -> output();
}

function setGlobalSkinValuesForContextAndDevice($skin,$device_type)
{
	myerror_log("we are setting the skin now with skin: ".$skin['external_identifier'],5);
    $skin_id = $skin['skin_id'];
    $_SERVER['SKIN_ID'] = $skin_id;
    $_SERVER['SKIN'] = $skin;
    $_SERVER['SKIN_TYPE'] = $skin['mobile_app_type'];
    $_SERVER['SKIN_REWARDR_ACTIVE'] = $skin['rewardr_active'];
    if (strtolower(trim($device_type)) == 'android') {
        $_SERVER['SKIN_URL'] = $skin['android_marketplace_link'];
    } else {
        $_SERVER['SKIN_URL'] = $skin['facebook_thumbnail_link'];
    }
    $_SERVER['IPHONE_URL'] = $skin['facebook_thumbnail_link'];
    $_SERVER['ANDROID_URL'] = $skin['android_marketplace_link'];
}

function doAppBombResponse($error_resource,$skin,$device_type,$request)
{
	$link_name = ($device_type == 'android') ? 'android_marketplace_link' : 'facebook_thumbnail_link';
	$error_resource->set("url", $skin[$link_name]);
	$error_resource->set("error_data",array("link"=>$skin[$link_name]));
	$error_resource->set("http_code", 400);
	if (substr_count($request->url, '/apiv2/') > 0) {
		respondFromResourceForApiV2($error_resource);
	} else{
		$response = getResponseWithJsonForErrorResource($error_resource,$headers,400);
		$response->output();
	}
	die();
}

?>