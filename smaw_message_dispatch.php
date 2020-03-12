<?php
$_SERVER['request_time1'] = microtime(true);

error_reporting(E_ERROR|E_COMPILE_ERROR|E_COMPILE_WARNING|E_PARSE);
ini_set('display_errors','0');
ini_set('mysql.connect_timeout', 20);
//error_reporting(E_ALL);

$path = $_SERVER['DOCUMENT_ROOT'].'/app2/';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

include 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'functions.inc';
include 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'SmsInterface.inc';

$login_headers = apache_request_headers();
foreach ($login_headers as $key=>$value) {
    $_SERVER['HTTP_'.$key] = $value;
    $_SERVER['HTTP_'.strtoupper($key)] = $value;
    $_SERVER[$key] = $value;
    $_SERVER[strtoupper($key)] = $value;
}


$_SERVER['RAW_STAMP'] = $code;
$_SERVER['STAMP'] = 'message-'.$code;

//$fileAdapter = new FileAdapter($mimetypes, 'resources');
$request = new Request();
$request->_parseRequestBody();

if (extension_loaded ('newrelic')) {
    $raw_url = $request->url;
    $clean_url = preg_replace('%/getnextmessagebymerchantid/([0-9a-zA-Z]+)%','/getnextmessagebymerchantid/ID',$raw_url);
    $url_parts = explode('?',$clean_url);
    newrelic_name_transaction ($url_parts[0]);
}
$_SERVER['request_url'] = $request->url;
if ((substr_count($request->url, 'sendnextmessage') < 1) && (substr_count($request->url,'/getnextmessagebymerchantid') < 1 )) {
	myerror_log("hitting smaw_message_dispatch with url: ".$request->url);
	if (count($request->data) > 0) {
		logData($request->data, "request data on message dispatch",3);
	}
	
}

if (substr_count($request->url,'/createtestmessages') > 0 )
{
	$num_messages = $request->data['number_of_messages'];
	testConcurrantWorkers($num_messages);
	die("messages created");
}

if (substr_count($request->url,'/verifySMSspeed') > 0 )
{
	$now = time();
	myerror_log("starting the verify sms speed in smaw_dispatch with a time of: ".$now);
	$text_controller = new TextController($mimetypes, $user, $request);
	if ($text_controller->verifySMSSpeedNoTextBody($now))
	{
		// all is good
		die();
	} else {
		//SEND THE ALARMS
		die();
		//die("BAD RESPONSE TIME!")
	}
	$response = new Response(200);
	$response->output();
	die();	

}

if ($request->data['log_level'])
	$log_level = $request->data['log_level'];
else
	$log_level = $global_properties['log_level_message_manager'];

$_SERVER['log_level'] = $log_level;
myerror_logging(3,"************* starting message dispatch.php *************".$request->url);

// first get the id of the focus object if it was passed in the url.  this could be an order_id or, merchant_id, etc. 
$key_values_alpha = '%/([0-9]{2}[0-9a-zA-Z]{0,10})/%';
$key_values = '%/([0-9]{2,11})/%';

// NEED TO CHECK FOR ALPHA NUMERIC WITH NEW WINAPP
if (substr_count($request->url,'/foundry/') > 0 || substr_count($request->url,'/winapp/') > 0 || substr_count($request->url,'/windowsservice') > 0 || substr_count($request->url,'/fax/callback.txt') > 0) {
    $key_values = '%/([0-9]{2}[0-9a-zA-Z]{0,10})%';
}

preg_match($key_values, $request->url, $matches);
$id = 0;
if ($matches)
{
	$id = str_ireplace('/','', $matches[0]);
	myerror_logging(2,"WE HAVE AN ID IN MESSAGE DISPATCH: ".$id);
}	

myerror_logging(3,"we have the submitted id: $id");
try
{
    if (extension_loaded ('newrelic')) {
        $raw_url = $request->url;
        $clean_url = str_replace($id,'ID',$raw_url);
        $url_parts = explode('?',$clean_url);
        newrelic_name_transaction ($url_parts[0]);
    }

    $mmha = new MerchantMessageHistoryAdapter($mimetypes);
		
	// ****** first get the correct controller if its listed in the request, like for pings ********
	//if ($message_controller = getMessageController($request->url, $mimetypes, $user, $request, $log_level))
	if ($message_controller = ControllerFactory::generateFromUrl($request->url, $mimetypes, $user, $request, $log_level))
	{
		myerror_logging(2,"************* got ".get_class($message_controller)." controller *************");

	}
	if (substr_count($request->url,'/sendnextmessage') > 0) 
	{
		// first get array of all the available messages, excluding Pings
		myerror_logging(4, "****** IN THE NEW MESSAGE SENDING LOGIC *******");
		$fixed_controller = false;
		if ($message_controller)
		{
			$fixed_controller = true;
			$format = $message_controller->getFormat();
			$message_data['message_format'] = array("LIKE"=>''.$format.'%');			
		}
		
		$worker_load = getProperty('worker_message_load');
		logData($request->data, "sendnextmessage paramters",3);
					
		$priority = isset($data['priority']) ? $data['priority'] : getProperty('message_priority');
			
		if ($priority == '1') {
			// order id's of less than 1000 are error codes.
			$message_data['order_id'] = array(">"=>1000);
		} else if ($priority == '2') {
			$message_data['order_id'] = array("IS"=>"NULL");
		}
		$mmha_options[TONIC_FIND_BY_METADATA] = $message_data;
		while ($message_resources = $mmha->getAvailableMessageResourcesArray($mmha_options))
		{
			myerror_logging(3,"Worker has grabbed ".sizeof($message_resources)." messages ready to be sent.  now try to get a lock on them one at a time to send");
			// we have available messages so we cycle through and try to grab it with a lock, if its unable to grab the mesage goto the next one.
			$sent_messages = 0;
			foreach ($message_resources as $unlocked_message_resource)
			{
                myerror_log("we have the unlocked message resource: map_id ".$unlocked_message_resource->map_id,5);
                logData($unlocked_message_resource->getDataFieldsReally(),"MESSAGE",5);
				if ($message_resource = $mmha->getLockedMessageResourceForSending($unlocked_message_resource))
				{
					if (! $fixed_controller)
						$message_controller = ControllerFactory::generateFromMessageResource($message_resource, $mimetypes, $user, $request, $log_level);
					
					if ($message_controller)	
						$message_controller->sendThisMessage($message_resource);
					else
					{
						myerror_log("ERROR! Serious problem with message sending message_id: ".$message_resource->map_id.".  No controller matching message type of: ".$message_resource->message_format);
						MailIt::sendErrorEmailSupport("MESSAGE SENDING ERROR!", "Serious problem with message sending message_id: ".$message_resource->map_id.".  No controller matching message type of: ".$message_resource->message_format);						
					}
				}
				$message_resource = null;
			}
			myerror_logging(3,"Worker has finished this set of messages, worker will now try to grab up to $worker_load more");
			
			//reset the counter
			set_time_limit(30);
		}
		myerror_logging(3, "No more messages ready to be sent.  Worker will now die");
		$response = new Response(200);
		$response->output();
		die("message have been sent: ".getRawStamp()."    \r\n");

	}
			
	// ****** now determine what action to take *********
	if (substr_count($request->url,'/getnextmessagebymerchantid') > 0 ) {
		if ($resource = $message_controller->pullNextMessageResourceByMerchant($id)) {
			myerror_log("successful retrieval of message by merchant id, about to send message as response to Pull Request");
			if ($message_controller->message_resource->locked == 'Y') {
			    $message_controller->message_resource->message_text = $resource->message_text;
				$message_controller->markMessageDelivered();
			}
			/*
			 * need to create the response here and output for both message_text and others......
			 */
		} else if ($message_controller->hasMessagingError()) {
			$response = new Response($message_controller->getErrorCode());
			$response->body = $message_controller->getErrorMessage();
			$response->output();
			die;
		} else {
			myerror_logging(3,"there are no orders for the PULL from merchant");
			$response = $message_controller->getNoPulledMessageAvailableResponse();
			myerror_logging(3,'sending back a status of: '.$response->statusCode);
			logData($response->headers, "response headers",3);
			$response->output();
			die;
		}
	} else if (substr_count($request->url,'/fax/callback.txt') > 0 ) {
		$resource = $message_controller->callBack($id);
		die("end of call back end");
		
	} else if (substr_count($request->url,'/gprs/callback.txt') > 0 ) {
		$resource = $message_controller->callBack($id);
		die("end of call back end");
		
	} else if (substr_count($request->url,'/chinaipprinter/callback.txt') > 0 ) {
		$resource = $message_controller->callBack($id);
		Response::respond200OkAndDie();
	} else if (substr_count($request->url,'/windowsservice/callback') > 0 ) {
		myerror_log("call back request body: ".$request->body);
		$resource = $message_controller->callBack($id);
		$response = new Response(200,"Success");
		$response->output();
		die;
		//die("end of call back");
	} else if (substr_count($request->url,'/getorderbyid') > 0 ) {
		$resource = $message_controller->getOrderById($id);
	} else if (substr_count($request->url,'/getorderrecordbyid') > 0 ) {
		$resource = $message_controller->getOrderRecordById($id);
		
	} else if (substr_count($request->url,'/sendorderbyid') > 0 ) {
		if ($message_controller->sendOrderById($id))
			$resource =& Resource::factory(new MySQLAdapter($mimetypes),array("message"=>"your request was processed"));			
		else
			$resource =& Resource::factory(new MySQLAdapter($mimetypes),array("message"=>$message_controller->getErrorMessage()));			
		$resource->_representation = '/utility_templates/generic_message.htm';
	} else if (substr_count($request->url,'/markmessageviewed') > 0 ) {
		if ($id == 0)
			die ('horrible death. no message id submitted');
		try { 
			$message_controller->markMessageAsViewed($id);
			$resource = new Resource(new MySQLAdapter($mimetypes),array("result"=>"success"));
		} catch (Exception $e) {
			$resource = new Resource(new MySQLAdapter($mimetypes),array("error"=>$e->getMessage(),"error_code"=>$e->getCode()));
		}
		$resource->_representation = '/json.xml';
	} else if (substr_count($request->url,'/markordercomplete') > 0 ) {
		if ($id == 0)
			die ('horrible death. no order id submitted');
		try { 
			$message_controller->updateOrderStatus('E',$id);
			$resource = new Resource(new MySQLAdapter($mimetypes),array("result"=>"success"));
		} catch (Exception $e) {
			$resource = new Resource(new MySQLAdapter($mimetypes),array("error"=>$e->getMessage(),"error_code"=>$e->getCode()));
		}
		$resource->_representation = '/json.xml';
	} else if (substr_count($request->url,'/getwindowsserviceversion') > 0) {
		$resource = new Resource();
		$resource->_representation = '/utility_templates/windows_service_version.xml';
	} else if (substr_count($request->url,'/updateivrorderstatus') > 0) {
		$ivr_controller = new IvrController($mt, $u, $request);
		$ivr_controller->processCallBack();
		$resource = new Resource();
		$resource->_representation = '/utility_templates/update_ivr_order_status.xml';
	} else if (substr_count($request->url,'/updatemessagestatus') > 0) {
		$resource = new Resource();
		$resource->_representation = '/utility_templates/update_ivr_order_status.xml';
	} else if (substr_count($request->url,'/configuremerchant') > 0) {
		$data['merchant_id'] = $user['merchant_id'];
		$data['result'] = 'success';
		$resource = new Resource(new AdmWinappAdapter($mimetypes),$data);
		$resource->_representation = '/utility_templates/windows_service_configure.xml';
	} else if (substr_count($request->url,'/thisisatest') > 0) {
		
		$resource =& Resource::factory(new MySQLAdapter($mimetypes),array("message"=>"this is the test message"));
		$resource->_representation = '/utility_templates/generic_message.htm';
	} else {
		die ('horrible death. no endpoint');
	}

	//Resource::encodeResourceIntoTonicFormat($resource);
	if ($resource->_representation == '/json.xml')
	{
		if (isset($resource->error))
			$datafields = array('ERROR' => $resource->error,
								'ERROR_CODE' => $resource->error_code,
								'TEXT_TITLE' => $resource->text_title,
								'TEXT_FOR_BUTTON' => $resource->text_for_button,
								'FATAL' => $resource->fatal,
								'URL' => $resource->url
							);
		else
			$datafields = Resource::encodeResourceIntoJsonPrepArray($resource);
		try {
			$jsonString = json_encode($datafields);
		} catch (Exception $e) {
			myerror_log("error encoding ".$e->getMessage()); // do nothing
		}
		$resource->set('json',$jsonString);
	}
	
	$representation =& $resource->loadRepresentation($file_adapter);
		
	if ($resource && $representation)
		$response =& $representation->get($request);
		
	if (substr_count($request->url,'/gprs/') > 0 || substr_count($request->url,'/epsonprinter/') > 0)
	{	
		myerror_log("********** headers ***********");
		foreach ($response->headers as $header_name=>$header_value) {
			myerror_log("$header_name : $header_value");
		}
		myerror_log("********** body *************");
            $text = $response->getOutputAsText();
		myerror_log($text);
		myerror_log("**************************");
	} else {
		myerror_logging(3,"*************************");
		$text = $response->getOutputAsText();
		myerror_logging(3,$text);
		myerror_logging(3,"**************************");
	}
	$response->output();	
} catch (Exception $e) {
	MailIt::sendErrorEmail("EXCEPTION THROWN ON smaw_message_dispatch", $e->getMessage());
	$response = new Response(500,$e->getMessage());
	$response->output();
}
myerror_logging(2,"***********finishing message_dispatch************");

    function testConcurrantWorkers($num)
    {
    	if (isProd())
    		die("not to be used in prod");
    	$sql = 'TRUNCATE TABLE `db_message_log`';
    	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
    	$mmha->_query($sql);
    	createMessages($num,1083,'D','N',true);
    	
    }

function getMessageController($string, $mimetypes, $user, $request, $log_level)
{
	if (substr_count($string,'/fax/') > 0)
		return  new FaxController($mimetypes, $user, $request, $log_level);
	else if (substr_count($string,'/ivr/') > 0)
		return  new IvrController($mimetypes, $user, $request, $log_level);
	else if (substr_count($string,'/email/') > 0)
		return  new EmailController($mimetypes, $user, $request, $log_level);
	else if (substr_count($string,'/windowsservice/') > 0)
		return  new WindowsServiceController($mimetypes, $user, $request, $log_level);
	else if (substr_count($string,'/winapp/') > 0)
		return  new WindowsServiceController($mimetypes, $user, $request, $log_level);
	else if (substr_count($string,'/gprs/') > 0)
		return  new GprsController($mimetypes, $user, $request, $log_level);
	else if (substr_count($string,'/rewardr/') > 0)
		return  new RewarderEventController($mimetypes, $user, $request, $log_level);
	else if (substr_count($string,'/ping/') > 0)
		return  new PingController($mimetypes, $user, $request, $log_level);
	else if (substr_count($string,'/text/') > 0)
		return  new TextController($mimetypes, $user, $request, $log_level);
	else if (substr_count($string,'/opie/') > 0)
		return  new OpieController($mimetypes, $user, $request, $log_level);
	else if (substr_count($string,'/qube/') > 0)
		return  new QubeController($mimetypes, $user, $request, $log_level);
	else if (substr_count($string,'/json/') > 0)
		return  new MessageController($mimetypes, $user, $request, $log_level);
	else
		return null;
	
}
?>