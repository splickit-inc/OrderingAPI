<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class APIOauthTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }
    
    function testGetLoginPage()
    {
    	$curl = curl_init('http://localhost:3000/oauth/signin?client_id=12345ABCDE&redirect_uri=http://www.sumdumurl.com&auth_type=sumdumauthtype');
    	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($curl);
		myerror_log($result);
		$this->info = curl_getinfo($curl);
		curl_close($curl);

    }
    
    function makeRequest($url,$userpassword,$method = 'GET',$data = null)
    {
    	unset($this->info);
    	$method = strtoupper($method);
    	$curl = curl_init($url);
    	if ($userpassword) {
    		curl_setopt($curl, CURLOPT_USERPWD, $userpassword);
    	}
    	if ($external_id = getContext()) {
    		// use it
    	} else {
    		$external_id = "com.splickit.vtwoapi";
    	}
    	$headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:APIDispatchTest","NO_CC_CALL:true");
    	if ($authentication_token = $data['splickit_authentication_token']) {
    		$headers[] = "splickit_authentication_token:$authentication_token";
    	}
    	if ($method == 'POST') {
    		$json = json_encode($data);
    		curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS,$json);
			$headers[] = 'Content-Type: application/json';
			$headers[] = 'Content-Length: ' . strlen($json);
    	} else if ($method == 'DELETE') {
   			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE"); 		
    	}
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($curl);
		$this->info = curl_getinfo($curl);
		curl_close($curl);
    	return $result;
    }

    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',0);
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;
    	
    	$skin_resource = createWorldHqSkin();
    	$ids['skin_id'] = $skin_resource->skin_id;
    	
		//map it to a menu
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;
    	
    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;
    }
    
	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
    	date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'use_main'  && !defined('PHPUnit_MAIN_METHOD')) {
    APIOauthTest::main();
}

?>