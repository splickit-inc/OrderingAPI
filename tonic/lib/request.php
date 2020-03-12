<?php
/*
Tonic: A simple RESTful Web publishing and development system
Copyright (C) 2007 Paul James <paul@peej.co.uk>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// $Id: request.php 26 2007-10-28 18:25:01Z peejeh $

/**
 * Name of the cookie auth cookie
 * @var str
 */
if (!defined('COOKIENAME')) define('COOKIENAME', 'tonic');

/**
 * Models the incoming HTTP request from the client.
 * @package Tonic/Lib
 * @version $Revision: 26 $
 */
class Request
{
	/**
	 * The request method
	 * @var str
	 */
	var $method;
	
	/**
	 * The full requested URL from the root of the domain
	 * @var str
	 */
	var $fullUrl;
	
	/**
	 * The requested URL relative to app directory
	 * @var str
	 */
	var $url;
	
	/**
	 * The requested URL with an extension removed
	 * @var str
	 */
	var $baseUrl;
	
	/**
	 * The request representation format accept array in order of preference
	 * @var str[]
	 */
	var $accept;
	
	/**
	 * The request language accept array in order of preference
	 * @var str[]
	 */
	var $language;
	
	/**
	 * The request body mimetype
	 * @var str
	 */
	var $mimetype;
	
	/**
	 * The request body content
	 * @var str
	 */
	var $body;
	
	/**
	 * The resource class to use for creating new resources
	 * @var str
	 */
	var $class;
	
	/**
	 * The match entity tags given in the request
	 * @var str[]
	 */
	var $ifMatch;
	
	/**
	 * The none match entity tags given in the request
	 * @var str[]
	 */
	var $ifNoneMatch;
	
	/**
	 * The modified since date given in the request
	 * @var int
	 */
	var $ifModifiedSince;
	
	/**
	 * The unmodified since date given in the request
	 * @var int
	 */
	var $ifUnmodifiedSince;
	
	/**
	 * Basic auth data
	 * @var str[]
	 */
	var $basicAuth;
	
	/**
	 * Digest auth data
	 * @var str[]
	 */
	var $digestAuth;
	
	/**
	 * Parsed request data
	 * @var str[]
	 */
	var $data = array();
	
	/**
	 * Accept URL extensions in order of preference
	 & @var str[]
	 */
	var $extensions = array();

	/**
	 * Parsed request url
	 * @var str[]
	 */
	var $url_hash = array();
	
	var $log_level;
	
	var $parse_error;

	private $headers = array();
	
	function request()
	{
		$this->method = $this->_getHTTPMethod();
		if (strtoupper($this->method) == 'POST' || strtoupper($this->method) == "DELETE") {
            $_SERVER['USE_WRITE_DB'] = true;
		} else {
            $_SERVER['USE_WRITE_DB'] = false;
		}
		list($this->fullUrl, $this->url) = $this->_getURL();
		$this->parseRequestUrl($this->url);
		$this->accept = $this->_getAcceptHeader();
		$this->language = $this->_getLanguageHeader();
		$this->encoding = $this->_getRequestAcceptEncoding();
		$this->mimetype = $this->_getRequestBodyMimetype();
		$this->body = $this->_getRequestBody();
		$this->ifNoneMatch = $this->_getIfNoneMatch();
		$this->ifMatch = $this->_getIfMatch();
		$this->ifModifiedSince = $this->_getIfModifiedSince();
		$this->ifUnmodifiedSince = $this->_getIfUnmodifiedSince();
		$this->basicAuth = $this->_getBasicAuth();
		$this->digestAuth = $this->_getDigestAuth();
		$this->cookieAuth = $this->_getCookieAuth();
		if (substr_count($this->fullUrl, "sendnextmessage") > 0 || substr_count($this->fullUrl, "messagemanager") > 0 || substr_count($this->fullUrl, "app3") > 0)
			myerror_logging(5,"full url in request.php: ".$this->fullUrl);
		else
			myerror_logging(3,"full url in request.php: ".$this->fullUrl);
		$this->log_level = $_SERVER['log_level'];

	}
	
	/**
	 * Get the HTTP method of this request
	 * @return str
	 */
	function _getHTTPMethod()
	{
		if (isset($_SERVER['REQUEST_METHOD'])) {
			return strtolower($_SERVER['REQUEST_METHOD']);
		}
		return NULL;
	}
	
	/**
	 * Get the URL of this request. Returns both the full URL and the URL relative
	 * to this app.
	 * @return str[]
	 */
	function _getURL()
	{
		$fullUrl = NULL;
		if (isset($_SERVER['REDIRECT_URL'])) {
            $fullUrl = $_SERVER['REDIRECT_URL'];
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $fullUrl = $_SERVER['REQUEST_URI'];
        }
        $url = $_SERVER['SCRIPT_URL'];
//		if (isset($_SERVER['PHP_SELF'])) {
//			$baseLength = strlen(dirname($_SERVER['PHP_SELF']));
//			if ($baseLength > 1) {
//				$url = substr($fullUrl, $baseLength);
//			}
//		}
		return array($fullUrl, $url);
	}

	function parseRequestUrl($url)
	{
		$this->url_hash = array();
		if ($url == null) {
			$url = $this->url;
		}
		$url_array = explode('/',$url);
		$index = 1;
		foreach ($url_array as $string) {
			$this->url_hash[$string] = $index++;
		}
	}
	
	/**
     * Explode the request accept string into an ordered array of content types
     * @return str[] An ordered array of acceptable content types
     */
    function _getAcceptHeader()
    {
        if (isset($_SERVER['HTTP_ACCEPT'])) {
            $accepts = explode(',', $_SERVER['HTTP_ACCEPT']);
            $orderedAccepts = array();
            foreach ($accepts as $key => $accept) {
                $exploded = explode(';', $accept);
                if (isset($exploded[1]) && substr($exploded[1], 0, 2) == 'q=') {
                    $orderedAccepts[substr($exploded[1], 2)][] = trim($exploded[0]);
                } else {
                    $orderedAccepts['1'][] = trim($exploded[0]);
                }
            }
            krsort($orderedAccepts);
            $accepts = array();
            foreach ($orderedAccepts as $q => $acceptArray) {
                foreach ($acceptArray as $mimetype) {
                    $accepts[] = trim($mimetype);
                }
            }
            // FIX for IE. if */*, replace with text/html
			$key = array_search('*/*', $accepts);
            if ($key !== FALSE) {
                $accepts[$key] = 'text/html';
            }
            return $accepts;
        }
		return array('text/html');
    }
	
	/**
     * Explode the request language accept string into an ordered array of languages
     * @return str[] An ordered array of acceptable languages
     */
    function _getLanguageHeader()
    {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $accepts = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            $orderedAccepts = array();
            foreach ($accepts as $key => $accept) {
                $exploded = explode(';', $accept);
                if (isset($exploded[1]) && substr($exploded[1], 0, 2) == 'q=') {
                    $q = substr($exploded[1], 2);
                    $orderedAccepts[$q][] = $exploded[0];
                    if ($pos = strpos($exploded[0], '-')) {
                        $orderedAccepts[strval($q - 10)][] = substr($exploded[0], 0, $pos);
                    }
                } else {
                    $orderedAccepts['1'][] = $exploded[0];
                    if ($pos = strpos($exploded[0], '-')) {
                        $orderedAccepts['-9'][] = substr($exploded[0], 0, $pos);
                    }
                }
            }
            krsort($orderedAccepts);
            $accepts = array();
            foreach ($orderedAccepts as $q => $acceptArray) {
                foreach ($acceptArray as $language) {
                    $accepts[] = $language;
                }
            }
            return array_unique($accepts);
        }
        return array();
    }
    
	function _getRequestAcceptEncoding()
	{
		if (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            $accepts = explode(',', $_SERVER['HTTP_ACCEPT_ENCODING']);
            foreach ($accepts as $key => $accept) {
				$accepts[$key] = trim($accept);
            }
			return $accepts;
		}
	}
	
	/**
	 * Get the request body mimetype from the incoming request
	 * @return str
	 */
	function _getRequestBodyMimetype()
	{
		if (isset($_SERVER['CONTENT_TYPE'])) {
			return $_SERVER['CONTENT_TYPE']; 
		} elseif (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
			return 'text/plain';
		}
		return NULL;
	}
	
	/**
	 * Get the request body from the incoming request
	 * @return str
	 */
	function _getRequestBody()
	{
		if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
			$requestData = '';
			global $HTTP_RAW_POST_DATA;
			if (isset($HTTP_RAW_POST_DATA)) { // use the magic POST data global if it exists
				return $HTTP_RAW_POST_DATA;
			} else { // other methods
				$requestPointer = fopen('php://input', 'r');
				while ($data = fread($requestPointer, 1024)) {
					$requestData .= $data;
				}
				return $requestData;
			}
		} else if (isLaptop() && $this->body != null) {
			return $this->body;
		} 
		return NULL;
	}
	
	/**
	 * Parse entity tags out of a string
	 * @param str string
	 * @return str[]
	 */
	function _getETags($string)
	{
		$eTags = array();
		if (isset($string) && $string != '') {
            $eTags = explode(',', $string);
			foreach ($eTags as $key => $eTag) {
				$eTags[$key] = trim($eTag, '" ');
			}
        }
		return $eTags;
	}
	
	/**
	 * Get the none match entity tags from the incoming request
	 * @return str[]
	 */
	function _getIfNoneMatch()
	{
		if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            return $this->_getETags($_SERVER['HTTP_IF_NONE_MATCH']);
        }
		return array();
	}
	
	/**
	 * Get the match entity tags from the incoming request
	 * @return str[]
	 */
	function _getIfMatch()
	{
		if (isset($_SERVER['HTTP_IF_MATCH'])) {
            return $this->_getETags($_SERVER['HTTP_IF_MATCH']);
        }
		return array();
	}
	
	/**
	 * Get the if-modified-since header from the incoming request
	 * @return int
	 */
	function _getIfModifiedSince()
	{
		if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] != '') {
            return strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
		}
		return 0;
	}
	
	/**
	 * Get the if-modified-since header from the incoming request
	 * @return int
	 */
	function _getIfUnmodifiedSince()
	{
		if (isset($_SERVER['HTTP_IF_UNMODIFIED_SINCE']) && $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] != '') {
            return strtotime($_SERVER['HTTP_IF_UNMODIFIED_SINCE']);
		}
		return 0;
	}
	
	/**
     * Get the username and password for the HTTP Basic auth given for this request
     * @return str[]
     */
    function _getBasicAuth()
    {
		if (isset($_SERVER['PHP_AUTH_USER'])) {
			return array(
				'username' => $_SERVER['PHP_AUTH_USER'],
				'password' => $_SERVER['PHP_AUTH_PW']
			);
		}
        return NULL;
    }
	
	/**
     * Get the username and auth details for the HTTP Digest auth given for this request
     * @return str[]
     */
    function _getDigestAuth()
    {
		if (isset($_SERVER['Authorization'])) {
            $authorization = $_SERVER['Authorization'];
        } elseif (isset($_ENV['HTTP_AUTHORIZATION'])) { // for FastCGI suggested by Daniel Patrick <daniel@geekmobile.biz>
            $authorization = stripslashes($_ENV['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $authorization = $headers['Authorization'];
            }
        }
        if (
			isset($authorization) &&
			preg_match('/username="([^"]+)"/', $authorization, $username) &&
			preg_match('/nonce="([^"]+)"/', $authorization, $nonce) &&
			preg_match('/response="([^"]+)"/', $authorization, $response) &&
			preg_match('/opaque="([^"]+)"/', $authorization, $opaque) &&
			preg_match('/uri="([^"]+)"/', $authorization, $uri)
		) {
			preg_match('/qop="?([^,\s"]+)/', $authorization, $qop);
			preg_match('/nc=([^,\s"]+)/', $authorization, $nc);
			preg_match('/cnonce="([^"]+)"/', $authorization, $cnonce);
			return array(
				'username' => $username[1],
				'nonce' => $nonce[1],
				'response' => $response[1],
				'opaque' => $opaque[1],
				'uri' => $uri[1],
				'qop' => $qop[1],
				'nc' => $nc[1],
				'cnonce' => $cnonce[1]
			);
		}
		return NULL;
    }
	
	/**
     * Get the username and hash for the HTTP Cookie auth given for this request
     * @return str[]
     */
    function _getCookieAuth()
    {
		if (isset($_COOKIE['tonic'])) {
			$parts = explode(':', $_COOKIE['tonic']);
			if (count($parts) == 2 && strlen($parts[1]) == 32) {
				return array(
					'username' => $parts[0],
					'hash' => $parts[1]
				);
			}
		}
        return NULL;
    }
	
	/**
	 * Is the given method name a valid HTTP method and a method on the resource
	 * @param Resource resource
	 * @param str method
	 * @return bool
	 */
	function _isHttpMethod(&$resource, $method)
	{
		if ($method == 'head' || $method == 'get' || $method == 'put' || $method == 'post' || $method == 'delete') {
			return method_exists($resource, $method);
		}
		return FALSE;
	}
	
	function &loadIfExistsCreateIfNot(&$adapter)
	{
		myerror_logging(3, "starting Resource->loadIfExistsCreateIfNot");
		// turn request body into data array
		if (!$this->data)
			$this->_parseRequestBody();
		// convert accept into format
		$format = array();
		foreach ($this->accept as $mimetype) {
			if ($extension = $adapter->mimetypeToExtension($mimetype)) {
				$format[] = $extension;
			}
		}	
		$options[TONIC_FIND_BY_METADATA] = $this->data;
		if ($resource =& Resource::findExactFromUrl($adapter,$this->url, $options)) {
			myerror_logging(3,"we found an exact matching resource");// we got it
			if ($_SERVER['log_level'] > 2)
				Resource::encodeResourceIntoTonicFormat($resource);
		} else {
			$resource = Resource::factory($adapter,$this->data);
		}
		
		return $resource;
		
	}

	/**
	 * Execute the fetching of a resource and execution of the requested method
	 * @param Adapter adapter
	 * @param str[] options An array of options to be passed to the adapter for
	 *                      loading the request resource
	 * @return Resource
	 */
	function &load(&$adapter, $options = array())
	{
		// turn request body into data array
		$this->_parseRequestBody();
		// convert accept into format
		$format = array();
		foreach ($this->accept as $mimetype) {
			if ($extension = $adapter->mimetypeToExtension($mimetype)) {
				$format[] = $extension;
			}
		}
		// get accept data from URL extensions
		list($this->baseUrl, $extensions) = $adapter->explodeUrlToGetExtensions($this->url);
		// generate accept URL extensions in order of preference
		$this->extensions =& $this->_generateAcceptArray($adapter->mimetypes, $extensions, $format, $this->language);
		// get resource
		$resource = NULL;
		// this is a hack to prevent the extra fields on an updqte from preventing the object from loading.  stupid.  there is probably a much better way of doing this.
		if (!(preg_match('%/([a-z]+)/([0-9]+)%', $this->url)) && $this->data)
			$options[TONIC_FIND_BY_METADATA] = $this->data;
		else
			;//
		if ($resource =& Resource::find($adapter, $this->baseUrl, $options)) {
			// found it
		} else { // nothing found
			if ($this->baseUrl != $this->url && $resource =& Resource::find($adapter, $this->url, $options)) { // we stripped the extensions, so lets look for the originally requested resource
				// found it
			}
		}
		return $resource;
	}

	function &loadAll(&$adapter, $options = array())
	{
		// turn request body into data array
		$this->_parseRequestBody();
		// convert accept into format
		$format = array();
		foreach ($this->accept as $mimetype) {
			if ($extension = $adapter->mimetypeToExtension($mimetype)) {
				$format[] = $extension;
			}
		}
		// get accept data from URL extensions
		list($this->baseUrl, $extensions) = $adapter->explodeUrlToGetExtensions($this->url);
		// generate accept URL extensions in order of preference
		$this->extensions =& $this->_generateAcceptArray($adapter->mimetypes, $extensions, $format, $this->language);
		// get resource
		$resource = NULL;
		$resources = NULL;
		if ($this->data)
			$options[TONIC_FIND_BY_METADATA] = $this->data;
		if ($resource =& Resource::findAll($adapter, $this->baseUrl, $options)) {
			// added by ****arosenthal**** 
				$resources =& Resource::factory($adapter, array('data'=>$resource));
				$resources->_exists = TRUE; // access private var!
			// ****end arosenthal***
			// found it
		} else { // nothing found
			if ($this->baseUrl != $this->url && $resource =& Resource::find($adapter, $this->url, $options)) { // we stripped the extensions, so lets look for the originally requested resource
				// found it
			}
		}
		return $resources;
	}
	
	/**
	 * Execute the fetching of a resource and execution of the requested method
	 * @param Adapter adapter
	 * @param Resource resource
	 * @param str responseClassName Optional name of the response class to return
	 * @return Response HTTP response
	 */
	function &exec(&$adapter, &$resource)
	{
		if (!$resource) {
			if ($this->method == 'put') { // no resource and method is PUT
				if (
					!$this->ifMatch ||
					!in_array('*', $this->ifMatch)
				) { // if-match is good
					if ($this->data) {
						if (!isset($this->data['url'])) {
							$this->data['url'] = $this->url;
						}
						if (!isset($this->data['class'])) {
							$this->data['class'] = $this->{'class'};
						}
						$resource =& Resource::factory($adapter, $this->data);
					} else {
						$response = new Response(411); // error
					}
				} else {
					$response = new Response(412); // bad pre-condition
				}
			} else { // no resource, and not PUT, so create empty resource with representations from extension list
				$data = array(
					'url' => $this->baseUrl,
					'class' => $this->{'class'}
				);
				$resource =& Resource::factory($adapter, $data);
			}
		}
		if ($resource) {
			if ($this->_isHttpMethod($resource, $this->method)) { // valid HTTP method
				if ($resource->etagOrUnmodified($this)) { // if-match is good
					// execute HTTP method on resource
					$response =& $resource->{$this->method}($this, $adapter, 'Response');
					if (!is_a($response, 'Response')) {
						trigger_error('Object returned from method "'.$this->method.'" is not a Response object', E_USER_ERROR);
					}
				} else {
					$response = new Response(412); // bad pre-condition
				}
			} else {
				$response = new Response(405); // method not allowed
			}
		}
		$response->resource =& $resource; // add reference to resource to response object
		return $response;
	}
	
	/**
	 * Take the three accept arrays and create a combined accept array
	 * @param str[] extensions
	 * @param str[] format
	 * @param str[] language
	 * @return str[]
	 */
	function &_generateAcceptArray(&$mimetypes, &$extensions, &$format, &$language)
	{
		// add extensions to appropriate array
		foreach ($extensions as $extension) {
			if (isset($mimetypes[$extension])) {
				array_unshift($format, $extension);
			} else {
				array_unshift($language, $extension);
			}
		}
		
		$accept = array();
		foreach ($format as $f) {
			foreach ($language as $lang) {
				$accept[] = $lang.'.'.$f;
				$accept[] = $f.'.'.$lang;
			}
		}
		foreach ($format as $f) {
			$accept[] = $f;
		}
		foreach ($language as $lang) {
			$accept[] = $lang;
		}
		$accept = array_unique($accept);
		return $accept;
	}
	
	function validateAndParseRequestBody()
	{
		if ($this->body == null) {
			myerror_logging(3,"null body submitted!");
			if (substr_count($this->url,'/getcheckoutdata') > 0 || substr_count($this->url,'/placeorder') > 0 ) {
				MailIt::sendErrorEmail("Error! Null body submitted for order or get checkout data", "Null body submitted for order or get checkout data");
				throw new NoDataForOrderException();
			}
		}
		$this->_parseRequestBody();
		if ($this->body) {
			return $this->body;
		} else {
			return null;
		}		
	}
	
	/**
	 * Parse the resource data from the request body
	 */
	function _parseRequestBody()
	{
		myerror_logging(3,"we are starting the Request->_parseRequestBody");
		if ($this->data) {
			myerror_logging(3,"data is already set on request object so do not parse again");
			return true;
		}
		myerror_logging(3,"about to determin parse method from mimetype: ".$this->mimetype);
		myerror_logging(3,"strip out charset info if it exists");
		$s = explode(";", $this->mimetype);
		$this->mimetype = $s[0];
		if ($this->body) {
			$the_body = $this->body;
			myerror_log("this url is: ".$this->url);
			if (substr_count($this->url,'/users') > 0 ) {
				$the_body = cleanPasswordFromBody($the_body);
			}
			myerror_log("the body in _parsRequestBody() is: ".$the_body);
		} else {
			myerror_logging(3,"There was no body submitted");
		}
		if ($this->mimetype && $this->body) {
			$parseFormatMethod = '_parseFormat'.preg_replace_callback(
				'/(^|[\/\-\+])(.)/',
				create_function(
					'$matches',
					'return strtoupper($matches[2]);'
				),
				$this->mimetype
			);
			myerror_logging(3, "we have the parseFormatMethod: ".$parseFormatMethod);
			if (method_exists($this, $parseFormatMethod)) { // found a custom handler
				$this->data =& $this->$parseFormatMethod();
			} else { // default format handler
				$this->data = array(
					'mimetype' => $this->mimetype,
					'content' => $this->body
				);
			}
			if (isset($this->{'class'})) {
				$this->data['class'] = $this->{'class'};
			}
		}
		else if ($this->mimetype == "multipart/form-data")
		{
			myerror_log("probably a mixture of post and get data in a call back");				
			$this->data =& $this->_parseGETString();
			foreach ($_POST as $name=>$value)
				$this->data["$name"] = $value;	
		}
		else if ($_GET) {
			myerror_logging(5,"parsing as a GET string");
			$this->data =& $this->_parseGETString();
		}
	}
	
	function &_parseGETString()
	{

          	myerror_logging(4,"starting the get string parser in request.php with query string: ".$_SERVER['QUERY_STRING']);
			$data = array();
          
          $fields = explode('&',$_SERVER['QUERY_STRING']);
          foreach ($fields as $row)
          {
          		$pair = explode('=',$row);
          		//myerror_log($pair[0].' = '.$pair[1]);
          		if ($pair[1] != null && trim($pair[1]) != '' && $pair[0] != 'XDEBUG_SESSION_START' && $pair[0] != 'KEY' && $pair[0] != 'show')
          			$data[$pair[0]] = urldecode($pair[1]);
          }
          return $data;
	}
	
	/**
	 * Turn the default Tonic data format into PHP array
	 * @return str[]
	 */
	function &_parseFormatApplicationTonicResource()
	{
		return Resource::decodeResourceFromTonicFormat($this->body);
	}
	
	function &_parseFormatApplicationxml()
	{
		myerror_logging(3, "starting the _parseFormatApplicationxml method");
		if ($body = @file_get_contents('php://input')) {
			; // all is good
		} else {
			$body = $this->body;
		}
		return $this->parseXML($body);
	}

	function parseXML($body)
	{
		myerror_logging(3,$body);
		myerror_logging(3,"is this good XML?");
		if (simplexml_load_string($body))
		{
			myerror_logging(3, "it is good XML so lets parse it");
			$xml = new SimpleXMLElement($body);
		} else {
			myerror_log("WE HAVE BAD XML! throw an exception");
			$this->parse_error = "String could not be parsed as XML";
			return false;
			//throw new Exception("String could not be parsed as XML", $code);
		}
		$order_array = $this->xmlToArray($xml);
		return $order_array;
	}
	
	function xmlToArray($xml) {
        $array = (array)$xml;

        foreach ( array_slice($array, 0) as $key => $value ) 
        {
            if ( $value instanceof SimpleXMLElement ) 
            {
                $array[$key] = empty($value) ? NULL : myToArray($value);
            }
		}
        return $array;
    }

	function parseSoapRequest()
	{
		if ($this->data) {
			return $this->data;
		}
		$this->extractSoapBody();
		myerror_logging(3, "POS LOGGING:  after extract the request body is: " . $this->body);
		$this->data = $this->parseXML($this->body);
		return $this->data;
	}

	function extractSoapBody()
	{
		$body = getSOAPCleanSectionFromEnvelopeBody($this->body,"soap:Body");
		myerror_logging(3,"POS LOGGING: setting the body to:   $body");
		$this->body = $body;
		return $body;
	}

	function &_parseFormatApplicationjson() {
		myerror_logging(3, "starting the _parseFormatApplicationjson method");
		
		if ($body = @file_get_contents('php://input'))
			; // all is good
		else
			$body = $this->body;
		if ($body_data_array = json_decode($body,true))
		{
			if ($body_data_array['jsonVal'])
				$data = $body_data_array['jsonVal'];
			else
				$data = $body_data_array;
			return $data;
		} else {
    		myerror_log("WE HAVE BAD JSON! throw an exception");
    		$this->parse_error = "String could not be parsed as JSON";
    		//return false;
    		throw new BadJsonException("String could not be parsed as JSON", $code);
			
		}
	}
	
	function &_parseFormatApplicationxwwwformurlencoded() {  
          $data = array();
          $fields = explode('&',$this->body);
          foreach ($fields as $row)
          {
          		$pair = explode('=',$row);
          		if (substr_count($this->fullUrl,'admin') < 1)
          		{
          			// log only if no CC number contained in the data
          			//if (substr_count($pair[1], 'cc_number') < 1 )
          			//	if ($_SERVER['log_level'] > 1)
          			;//		myerror_log($pair[0].' = '.$pair[1]); 
          		} 
          		if ($pair[1] != null && trim($pair[1]) != '' && $pair[0] != 'show')
          			$data[$pair[0]] = urldecode($pair[1]);
          }
          if ($data['jsonVal'])
          {
          		$dataRaw = json_decode($data['jsonVal'],true);
          		if ($dataRaw['jsonVal'])
          			$data = $dataRaw['jsonVal'];
          		else
          			$data = $dataRaw;
          }
          return $data;
	}

	function isRequestDeviceTypeNativeApp()
	{
		return $this->isRequestDeviceType('iphone') || $this->isRequestDeviceType('android');
	}

	function isRequestDeviceType($device_type)
	{
		return strtolower($this->getRequestDeviceType()) == strtolower($device_type);
	}

	function getRequestDeviceType()
	{
		return $this->headers['HTTP_X_SPLICKIT_CLIENT_DEVICE'];
	}

	function setHeaderVariable($name,$value)
	{
		$this->headers[strtoupper($name)] = $value;
	}

	function getHeaderVariable($name)
	{
		return $this->headers[strtoupper($name)];
	}

	function getHeaders()
	{
		return $this->headers;
	}
}

class BadJsonException extends Exception
{
    public function __construct($dummy = 'dum') {
        parent::__construct("There was a transmission error and we could not understand your request, please try again.", 999);
    }
}

class NoDataForOrderException extends Exception
{
    public function __construct($dummy = 'dum') {
        parent::__construct("Sorry, There was an error with your request, please try again", 999);
    }
}

?>
