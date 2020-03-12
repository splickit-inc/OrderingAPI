<?php

error_reporting(E_ERROR | E_COMPILE_ERROR | E_COMPILE_WARNING | E_PARSE);
$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');
$db_info->database = 'smaw_unittest';
$db_info->username = 'root';
$db_info->password = 'splickit';
if (isset($_SERVER['XDEBUG_CONFIG'])) {
    putenv("SMAW_ENV=unit_test_ide");
    $db_info->hostname = "127.0.0.1";
    $db_info->port = 13306;
} else {
    $db_info->hostname = "db_container";
    $db_info->port = 3306;
}
$_SERVER['DB_INFO'] = $db_info;
require_once 'lib/curl_objects/splickitcurl.php';
require_once 'lib/mocks/viopaymentcurl.php';
require_once 'lib/utilities/functions.inc';
require_once 'lib/utilities/unit_test_functions.inc';


class PortalMenuNutritionDispatchTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;
    var $info;
    var $api_port = "80";
    var $menu_id;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        //$_SERVER['DO_NOT_RUN_CC'] = true;
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        if (isset($_SERVER['XDEBUG_CONFIG'])) {
            $this->api_port = "10080";
        }
    }

    function tearDown()
    {
        //delete your instance
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
        unset($this->info);
    }


    function testGetNutritionGrid()
    {
        $menu_id = $this->ids['menu_id'];
        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/nutrition";
//        $request = createRequestObject($url,'GET');
//        $menu_controller = new MenuController(getM(),null,$request,getBaseLogLevel());
//        $resource = $menu_controller->processV2Request();

        $response = $this->makeRequest($url, null,'GET');
        $response_array = json_decode($response,true);
        $this->assertEquals(200,$response_array['http_code']);

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/portal/menus/$menu_id/nutrition";
//        $request = createRequestObject($url,'GET');
//        $menu_controller = new MenuController(getM(),null,$request,getBaseLogLevel());
//        $resource = $menu_controller->processV2Request();

        $data = $response_array['data']['menu_types']['Hot Drinks']['FRESH BREWED COFFEE-16 oz'];
        $starting_calories = intval($data['calories']);
        $data['calories'] =  $starting_calories + 50;

//        $request = createRequestObject($url,'POST',json_encode($data));
//        $menu_controller = new MenuController(getM(),null,$request,getBaseLogLevel());
//        $resource = $menu_controller->processV2Request();


        $response2 = $this->makeRequest($url, null,'POST',$data);
        $response_array2 = json_decode($response2,true);

        $ending_calories = intval($response_array2['data']['menu_types']['Hot Drinks']['FRESH BREWED COFFEE-16 oz']['calories']);

        $this->assertEquals($starting_calories+50,$ending_calories);


        $data = $response_array['data']['menu_types']['Espresso']['CAPPUCCINO-Double'];
        $starting_calories_for_no_record = "88";

        $data['calories'] =  $starting_calories_for_no_record;

        $response3 = $this->makeRequest($url, null,'POST',$data);
        $response_array3 = json_decode($response3,true);

        $ending_calories_again = intval($response_array3['data']['menu_types']['Espresso']['CAPPUCCINO-Double']['calories']);

        $this->assertEquals($starting_calories_for_no_record,$ending_calories_again);



    }


    function getExternalId()
    {
        if ($external_id = getContext()) {
            // use it
        } else {
            $external_id = "com.splickit.vtwoapi";
        }
        return $external_id;
    }

    function makeRequest($url,$userpassword,$method = 'GET',$data = null)
    {
        logData($data," curl data");
        unset($this->info);
        $method = strtoupper($method);
        $curl = curl_init($url);
        if ($userpassword) {
            curl_setopt($curl, CURLOPT_USERPWD, $userpassword);
        }
        $external_id = getContext();
        $headers = array("X_SPLICKIT_CLIENT_ID:$external_id","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:AdminDispatchTest","NO_CC_CALL:true");
        if ($authentication_token = $data['splickit_authentication_token']) {
            $headers[] = "splickit_authentication_token:$authentication_token";
        }
        if ($data['headers']) {
            $headers = $data['headers'];
            unset($data['headers']);
        }
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            if ($data) {
                $json = json_encode($data);
                curl_setopt($curl, CURLOPT_POSTFIELDS,$json);
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Content-Length: ' . strlen($json);
            }
        } else if ($method == 'DELETE') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        logCurl($url,$method,$userpassword,$headers,$json);
        $result = curl_exec($curl);

        $this->info = curl_getinfo($curl);
        curl_close($curl);
        return $result;
    }

    static function setUpBeforeClass()
    {
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        ini_set('max_execution_time',300);

        $menu_id = 102775;
        $ids['menu_id'] = $menu_id;
        $skin_id = 157;
        $brand_id = 444;
        $menu_adapter = new MenuAdapter(getM());
        if ($menu_resource = Resource::find($menu_adapter,"$menu_id")){
            myerror_log("we have the menu");
        } else {
            //create menu
            $menu_adapter->importMenu($menu_id,'prod','local',$brand_id);
        }

        $skin_resource = getOrCreateSkinAndBrandIfNecessary("aspertto","aspertto",$skin_id,$brand_id);
        setContext('com.splickit.aspertto');

        $_SERVER['unit_test_ids'] = $ids;
        $_SERVER['log_level'] = 5;
    }

    static function tearDownAfterClass()
    {
        //mysqli_query("ROLLBACK");
        date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
        $suite = new PHPUnit_Framework_TestSuite( __CLASS__);
        PHPUnit_TextUI_TestRunner::run( $suite);
    }



}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    PortalMenuNutritionDispatchTest::main();
}

?>