<?php
require_once 'tonic'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'request.php';
require_once 'tonic'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'resource.php';

class SplickitController 
{
	var $data = array();
	protected $mimetypes;
	protected $user;
	
	/**
	 * @var SQLAdapter 
	 */
	protected $adapter;
	
	/**
	 * @var Request
	 */
	protected $request;
	
	protected $log_level;
	protected $file_adapter;
	protected $global_properties;
	protected $url_hash = array();
	
	function SplickitController($mimetypes,$user,$request,$log_level = 0)
	{
		myerror_logging(3,"Constructing class: ".get_class($this));
		if ($mimetypes)
			$this->mimetypes = $mimetypes;
		else
			$this->mimetypes = array('html' => 'text/html','xml' => 'text/xml');
		$this->user = $user;
		if ($this->request = $request)
		{
			// test to make sure this is an actual Request so we dont blow up
			if (is_a($request, 'Request')) {
				$this->request->_parseRequestBody();
                $this->request->parseRequestUrl();
				$this->data = $this->request->data;
			}
		}
		$class_name = get_class($this);
		if ($custom_log_level = getProperty(strtolower($class_name)."_log_level")) {
			myerror_log("We have a custom controller log level of $custom_log_level");
			if ($custom_log_level > $log_level) {
				myerror_log("we are setting to a custom log level of $custom_log_level");
				$log_level = $custom_log_level;
				$_SERVER['log_level'] = $log_level;
			} else {
				myerror_log("NOT settting to a custom log level because   $log_level  !<  $custom_log_level");
			}
		}

		$this->log_level = $log_level;	
		$this->file_adapter = new FileAdapter($mimetypes, 'resources');	
		$this->global_properties = $_SERVER['GLOBAL_PROPERTIES'];
	}

	function hasRequestForDestination($endpoint)
	{
		return isset($this->request->url_hash[$endpoint]);
	}

    function isAnonymousRequest()
    {
        return ($this->user == null || $this->user['user_id'] == 9999);
    }

    function isThisUserAGuest()
    {
        return doesFlagPositionNEqualX($this->user['flags'],9,'2');
    }

    function isThisUserABlackListedUser()
    {
        return doesFlagPositionNEqualX($this->user['flags'],1,'X');
    }

	function setRequest($request)
	{
		$this->request = $request;
	}
	
	/**
	 * 
	 * @desc will return a resource matching the primary Id
	 * @param int $id
	 * @param string $object_name (adapter class)
	 * @return Resource
	 */
	static function getResourceFromId($id,$object_name)
	{
		$class_name = $object_name."Adapter";
		$adapter = new $class_name($mimetypes);
		if ($resource = Resource::find($adapter,''.$id))
			return $resource;
		else
			return false;
	}
	
	function setListAsDataInDispatchReturnFormat($data)
	{
		return Resource::dummyFactory(array("data"=>$data));
	}

	function setUserByUserId($user_id)
    {
        $user_resource = $this->getResourceFromId($user_id,'User');
        $this->user = $user_resource->getDataFieldsReally();
    }

    function isThisRequestMethodAGet()
    {
        return isRequestMethodAGet($this->request);
    }

    function isThisRequestMethodAPost()
    {
        return isRequestMethodAPost($this->request);
    }

    function isThisRequestMethodAPut()
    {
        return isRequestMethodAPut($this->request);
    }

    function isThisRequestMethodADelete()
    {
        return isRequestMethodADelete($this->request);
    }
}

class NullRequestException extends Exception
{
    public function __construct() { 
        parent::__construct("Null Request", 999);   
    }
}

?>