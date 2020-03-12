<?php

class TaskRetailService
{
    /*

    #dev creds and url
      const WEB_SERVICE_URL_DEV = 'http://136.179.5.40/xchangenetfrpilot/services/xn_secureorders.asmx';
      const WEB_SERVICE_AUTH_URL_DEV = 'http://136.179.5.40/xchangenetfrpilot/services/xn_authenticate.asmx?op=authenticateClient';

      const WEB_SERVICE_PASSWORD_DEV = 'Splickit1';
      const WEB_SERVICE_USERNAME_DEV = '154';

    #prod creds and url

      const WEB_SERVICE_URL = 'http://136.179.5.40/xchangenetFR/services/xn_secureorders.asmx';
      const WEB_SERVICE_AUTH_URL = 'http://136.179.5.40/xchangenetFR/services/xn_authenticate.asmx?op=authenticateClient';

      const WEB_SERVICE_PASSWORD = 'task123';
      const WEB_SERVICE_USERNAME = 'task1';

    */

    public $message_resource;
    public $adapter;

    protected $url;
    protected $api_key;
    protected $auth_url;
    protected $web_service_username;
    protected $web_service_password;
    protected $service_name;
    protected $callback_fail_reason;
    protected $merchant_task_retail_info_map;

    private $auth_token = null;
    private $version = 'V1';

    private $success_codes = [200=>1,201=>1];

    function TaskRetailService(&$message_resource)
    {
        $mtrim = new MerchantTaskRetailInfoMapAdapter(getM());

        if ($message_resource->message_format == 'AJ') {
            $settings = $mtrim->getV2Info($message_resource->merchant_id);
            $this->merchant_task_retail_info_map = $settings;
            $this->version = 'V2';
            $this->url = $settings['task_retail_url'];
            $this->api_key = getProperty('task_retail_api_key');
        } else {
            $settings = $mtrim->getConfigSettingsForTaskRetailService($message_resource->merchant_id);
            $this->ensureTaskRetailServiceSettings($settings);
            $this->url = $settings['task_retail_url'];
            $this->auth_url = $settings['task_retail_auth_url'];
            $this->web_service_username = $settings['task_retail_username'];
            $this->web_service_password = $settings['task_retail_password'];
            myerror_log("TaskRetail Service - webservice settings for merchant $message_resource->merchant_id : {task_retail_url : $this->url, task_retail_auth_url: $this->auth_url, task_retail_username: $this->web_service_username, task_retail_password: $this->web_service_password}");
        }


        $this->message_resource = $message_resource;
        $this->adapter = new MerchantMessageHistoryAdapter($mimetypes);
        if (isset($message_resource->merchant_id) && $message_resource->merchant_id > 0) {
            $this->merchant_resource = SplickitController::getResourceFromId($message_resource->merchant_id, "Merchant");
        }
    }

    private function ensureTaskRetailServiceSettings($settings)
    {

        $missing_settings = array();
        foreach ($settings as $setting => $value) {
            if ($value === false) {
                array_push($missing_settings, $setting);
            }
        }

        if (count($missing_settings) > 0) {
            throw new MissingSettingsForTaskRetailException(join(', ', $missing_settings), 500);
        }
    }

    private function getHeadersStringFromResponse($response)
    {
        $header_size = $response['curl_info']['header_size'];
        return substr($response['raw_result'], 0, $header_size);
    }

    private function getHeadersFromResponse($response)
    {
        $headerString = $this->getHeadersStringFromResponse($response);
        $headers = array();

        foreach (explode("\r\n", $headerString) as $i => $line) {
            if ($i === 0) {
                $headers['http_code'] = $line;
            } else {
                list ($key, $value) = explode(': ', $line);

                if ('Set-Cookie' == $key) {
                    $cookie_array = explode(';', $value);
                    $value = $headers['Set-Cookie'] . '' . $cookie_array[0] . ';';
                }
                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    private function isSuccessfulResponse($response)
    {
        $curl_response = $response['raw_result'];
        $header_size = $response['curl_info']['header_size'];
        $header_string = substr($curl_response, 0, $header_size);
        $body = substr($curl_response, $header_size);

        $successful_body = (substr_count($body, '<Successful>true</Successful>') > 0);
        $successful_http_code = ($response['http_code'] == 200);
        return $successful_body && $successful_http_code;
    }

    private function isSuccessfulAuthenticationResponse($response)
    {
        $curl_response = $response['raw_result'];
        $header_size = $response['curl_info']['header_size'];
        $header_string = substr($curl_response, 0, $header_size);
        $body = substr($curl_response, $header_size);
        myerror_log("the body in task retail auth response is: ".$body,3);
        $successful_body = (substr_count($body, '<authenticateClientResult>true</authenticateClientResult>') > 0);
        $successful_http_code = ($response['http_code'] == 200);
        return $successful_body && $successful_http_code;
    }

    private function isUnSuccessfullJsonResponse($response)
    {
        return ! $this->isSuccessfullJsonResponse($response);
    }

    private function isSuccessfullJsonResponse($response)
    {
        return $this->success_codes[$response['http_code']] == 1;
    }

    function sendSoapRequest($function, $soapxml, $auth_cookie)
    {
        $url = $this->url . "?op=$function";
        $response = TaskRetailCurl::curlIt($url, cleanUpXML($soapxml), $auth_cookie);
        return $response;
    }

    function sendJSONRequest($json)
    {
        $url = $this->url;
        $response = TaskRetailCurl::curlIt($url, $json, 'V2');
        return $response;
    }

    /*
      TODO  Make this more generic to accept any method
    */
    function send($data)
    {
        if ($this->version == 'V1') {
            if ($this->auth_token == null) {
                $this->authenticate();
            }
            myerror_log("Authentication token is: $this->auth_token", 3);
            $response = $this->sendSoapRequest('UploadOrder', $data, $this->auth_token);
            if (!$this->isSuccessfulResponse($response)) {
                throw new UnsuccessfulTaskRetailPushException($response['raw_result'], $response['http_code']);
            }
        } else if ($this->version == 'V2') {
            $response = $this->sendJSONRequest($data);
            if ($this->isUnSuccessfullJsonResponse($response))  {
                throw new UnsuccessfulTaskRetailPushException($response['raw_result'], $response['http_code']);
            }
        } else {
            $version = $this->version;
            $error = "Task version '$version', not supported error";
            myerror_log($error);
            throw new UnsuccessfulTaskRetailPushException($error, 500);
        }

        $this->message_resource->message_text = $data;
        return $response;
    }

    private function authenticate()
    {
        $soap_xml = '<?xml version="1.0" encoding="utf-8"?><soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope"><soap12:Body><authenticateClient xmlns="http://tempuri.org/SwiftServices/xn_authenticate"><Username>' . $this->web_service_username . '</Username><Password>' . $this->web_service_password . '</Password></authenticateClient></soap12:Body></soap12:Envelope>';
        $authentication_response = TaskRetailCurl::curlIt($this->auth_url, $soap_xml);
        if ($this->isSuccessfulAuthenticationResponse($authentication_response)) {
            $headers = $this->getHeadersFromResponse($authentication_response);
            $this->auth_token = substr($headers['Set-Cookie'], 0, -1);
        } else {
            MailIt::sendErrorEmailSupport($subject, $body);
            SmsSender2::sendAlertListSMS($message);
            throw new UnsuccessfulTaskRetailAuthenticationException($response['raw_result'], $response['http_code']);
        }
    }

    function currentTaskRetailSettings()
    {
        return array(
            'task_retail_url' => $this->url,
            'task_retail_auth_url' => $this->auth_url,
            'task_retail_username' => $this->web_service_username,
            'task_retail_password' => $this->web_service_password,
        );

    }
}

class UnsuccessfulTaskRetailPushException extends Exception
{
    public function __construct($error, $http_code, $code = 100)
    {
        parent::__construct("Unable to send message to task retail. HTTP code: $http_code. Raw result: $error", $code);
    }
}

class UnsuccessfulTaskRetailAuthenticationException extends Exception
{
    public function __construct($error, $http_code, $code = 100)
    {
        parent::__construct("Unable to authenticate with task retail. HTTP code: $http_code. Raw result: $error", $code);
    }
}

class MissingSettingsForTaskRetailException extends Exception
{
    public function __construct($error, $http_code, $code = 100)
    {
        parent::__construct("Missing settings for task retail. HTTP code: $http_code. Missing settings: $error", $code);
    }
}

?>
