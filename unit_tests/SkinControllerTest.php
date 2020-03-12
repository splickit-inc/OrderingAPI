<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class SkinControllerTest extends PHPUnit_Framework_TestCase {
	
	function setUp() {
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
	}
	
	function tearDown() {
		$sa = new SkinAdapter($m);
		$sa->_query("DELETE FROM Skin WHERE external_identifier = 'com.splickit.firebuggrill'");	
	}

	function test400IfNoParameterSupplied() {
		$request = new Request();
		$request->url = '/app2/apiv2/skins';
		$request->method = "get";
		$controller = new SkinController(null, null, $request);
		$response = $controller->processRequest();
		$this->assertEquals(400,$response->http_code, "The controller should return a 400 if no brand parameter was provided.");
	}
	
	function test404IfNoMatchesFound() {
		$request = new Request();
		$request->url = '/app2/apiv2/skins/com.splickit.firebuggrill';
		$request->method = "get";
		$controller = new SkinController(null, null, $request);
		$response = $controller->processRequest();
		$this->assertEquals( 404,$response->http_code, "The controller should return a 404 if no matching skins were found.");
	}

    function testV2Response() {

        $data['brand_id'] = 2002;
        $data['brand_name'] = "Firebug Grill";
        $brand = Resource::createByData(new BrandAdapter($m), $data);

        $data = array();
        $data['facebook_thumbnail_link'] = 'http://itunes.apple.com/us/app/firebug-grill/id8?mt=8';
        $data['external_identifier'] = 'com.splickit.firebuggrill';
        $data['skin_name'] = 'Firebug Grill';
        $data['skin_description'] = 'com.splickit.firebuggrill';
        $data['skin_id'] = 10002;
        $data['brand_id'] = $brand->brand_id;

        $skin = Resource::createByData(new SkinAdapter($m), $data);

        $request = new Request();
        $request->url = '/app2/apiv2/skins/com.splickit.firebuggrill';
        $request->method = "get";
        $controller = new SkinController(null, null, $request);
        $response = $controller->processRequest();
        $this->assertEquals($response->iphone_app_link, 'http://itunes.apple.com/us/app/firebug-grill/id8?mt=8', "The controller should return the iphone_app_link field on the skin and not the facebook_thumbnail_link ");
        $this->assertNull($response->facebook_thumbnail_link, "The controller should return the iphone_app_link field on the skin and not the facebook_thumbnail_link ");
    }

    function test200IfMatchFound() {
		$skin = getOrCreateSkinAndBrandIfNecessary("Firebug Grill","Firebug Grill",$skin_id,$brand_id);
		$request = new Request();
		$request->url = '/app2/apiv2/skins/com.splickit.firebuggrill';
		$request->method = "get";
		$controller = new SkinController(null, null, $request);
		$response = $controller->processRequest();
		$this->assertEquals( 200,$response->http_code, "The controller should return a 200 if some matching skins were found. ");
	}
	
	function testProperResponseIfMatchFound() {
		$skin = getOrCreateSkinAndBrandIfNecessary("Firebug Grill","Firebug Grill",10002,2002);
		$request = new Request();
		$request->url = '/app2/apiv2/skins/com.splickit.firebuggrill';
		$request->method = "get";
		$controller = new SkinController(null, null, $request);
		$response = $controller->processRequest();
		$this->assertEquals($response->skin_id, "10002", "The returned skin should have the id of the skin.");
		$this->assertEquals("Firebug Grill", $response->skin_name, "The returned skin should have the name of the skin.");
		$this->assertEquals("com.splickit.firebuggrill", $response->external_identifier, "The returned skin should have the correct external identifier.");
		$this->assertNotNull($response->loyalty_features, "The returned skin should have an array of loyalty features.");
		$this->assertFalse($response->loyalty_features['loyalty_lite'],"Loyalty lite should be false");
	}
	


	function testReturnLoyaltyTypeField()
    {

        logTestUserIn(101);

		$skin = getOrCreateSkinAndBrandIfNecessaryWithLoyalty("Firebug Grill","Firebug Grill",10002,2002);

		$blr_data['brand_id'] = $skin->brand_id;
		$blr_data['loyalty_type'] = 'splickit_cliff';
		$blr_data['earn_value_amount_multiplier'] = 1;
		$blr_data['cliff_value'] = 10;
		$brand_loyalty_rules_resource = Resource::factory(new BrandLoyaltyRulesAdapter($m),$blr_data);
		$result = $brand_loyalty_rules_resource->save();
		$ids['blr_resource'] = $brand_loyalty_rules_resource->getRefreshedResource();

        SplickitCache::flushAll();

		setContext($skin->external_identifier);

		$request = new Request();
		$request->url = '/app2/apiv2/skins/com.splickit.firebuggrill';
		$request->method = "get";
		$controller = new SkinController(null, null, $request);
		$response = $controller->processRequest();
		$this->assertNotNull($response->loyalty_features['loyalty_type'], "The controller should return the loyalty type  field on the skin");
		$this->assertEquals("splickit_cliff", $response->loyalty_features['loyalty_type'], "The controller should return loyalty program value");

	}

	function testLoyaltyFeaturesForGoodCentsSubs(){
        logTestUserIn(101);
		$skin_resource = getOrCreateSkinAndBrandIfNecessary("Goodcents Subs", "Goodcents Subs", 140, 430);
		setContext($skin_resource->external_identifier);

        $brand_loyalty_rules_resource = Resource::findOrCreateIfNotExistsByData(new BrandLoyaltyRulesAdapter(),array("brand_id"=>430));
        $brand_loyalty_rules_resource->loyalty_type = 'splickit_earn';
		$brand_loyalty_rules_resource->save();

		$request = createRequestObject('/app2/apiv2/skins/com.splickit.goodcentssubs', 'GET', $body, "'application/json'");
		$controller = new SkinController(null, null, $request);
		$response = $controller->processRequest();
		$this->assertNotNull($response->loyalty_features['loyalty_type'], "The controller should return the loyalty type  field on the skin");
		$this->assertEquals("splickit_earn", $response->loyalty_features['loyalty_type'], "The controller should return loyalty program value");
		$this->assertCount(2, $response->loyalty_features['loyalty_labels'], "The controller should return loyalty program 2 labels");

	}

	function testEventMerchantHollywoodBowl(){
        logTestUserIn(101);

        $skin = getOrCreateSkinAndBrandIfNecessary("Hollywood Bowl","Hollywood Bowl",149,$brand_id);

    $request = new Request();
    $request->url = '/app2/apiv2/skins/com.splickit.hollywoodbowl';
    $request->method = "get";
    $controller = new SkinController(null, null, $request);
    $response = $controller->processRequest();
    $this->assertEquals($response->event_merchant, 0);
    $this->assertEquals('https://d38o1hjtj2mzwt.cloudfront.net/com.splickit.hollywoodbowl/merchant-location-images/large/HollywoodBowlMap.png',$response->map_url);
  }

    static function setUpBeforeClass()
    {
        ini_set('max_execution_time',300);

        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;
        $_SERVER['request_time1'] = microtime(true);
        $_SERVER['log_level'] = 5;
        //$_SERVER['unit_test_ids'] = $ids;

    }

    static function tearDownAfterClass()
    {
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->rollback();
    }


    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
		PHPUnit_TextUI_TestRunner::run( $suite);
	}
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    SkinControllerTest::main();
}