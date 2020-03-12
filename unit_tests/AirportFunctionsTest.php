<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class AirportFunctions extends PHPUnit_Framework_TestCase
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

    /**
     * @expectedException NullRequestException
     */
    function testNullRequestOnAirportController()
    {
    	$airport_controller = new AirportController($mimetypes, $user, $request);
    	$airport_controller->processRequest();
    }
    
    function testAssignMerchantToAirportLocation()
    {
    	
    	$aamma = new AirportAreasMerchantsMapAdapter($mimetypes);
    	$result = $aamma->assignMerchantToAirportArea(1000, $this->ids['merchant_id'],"Gate 35");
    	$this->assertTrue($result->id > 0);
    }
    
    function testIsMerchantAnAirportLocation()
    {
    	$result = AirportAreasMerchantsMapAdapter::isMerchantAnAirportLocation($this->ids['merchant_id']);
    	$this->assertTrue($result);
    	
    	$result2 = AirportAreasMerchantsMapAdapter::isMerchantAnAirportLocation(12345);
    	$this->assertFalse($result2);
    }
    
    function testGetAirportList()
    {
    	$airports = AirportsAdapter::getAllAirports($url, $data);
    	$this->assertNotNull($airports);
    	$this->assertEquals(2, count($airports));
    }
    
    function testGetAirportListFromRequest()
    {
    	$request = new Request();
    	$request->url = 'apiv2/airports/';
    	$user = logTestUserIn($this->ids['user_id']);
    	$airport_controller = new AirportController($mimetypes, $user, $request);
    	$resource = $airport_controller->processRequest();
    	$airports = $resource->data;
    	$this->assertEquals(2, count($airports));	
    }
    
    function testGetBaseAirport()
    {
    	// get DIA airport 1000
    	$complete_airport = new CompleteAirport(1000);
    	$this->assertEquals('Denver International Airport', $complete_airport->name);
    	$this->assertEquals('DEN', $complete_airport->code);
    	$this->assertEquals('8500 Pena Blvd.', $complete_airport->address);
    }
    
    function testGetAirportFromRequest()
    {
    	$request = new Request();
    	$request->url = 'apiv2/airports/1000';
    	$user = logTestUserIn($this->ids['user_id']);
    	$airport_controller = new AirportController($mimetypes, $user, $request);
    	$resource = $airport_controller->processRequest();
    	$this->assertEquals('Denver International Airport', $resource->name);
    	$this->assertEquals('DEN', $resource->code);
    	$this->assertEquals('8500 Pena Blvd.', $resource->address);
    	$this->assertCount(5, $resource->airport_merchants);
    }
      
    function testGetMerchantListForAirport()
    {
    	$complete_airport = new CompleteAirport($this->ids['airport_id']);
    	$merchants = $complete_airport->getAllAirportMerchants();
    	$this->assertEquals(1, count($merchants));
    	
    	$complete_airport2 = new CompleteAirport(1000);
    	$merchants2 = $complete_airport2->getAllAirportMerchants();
    	$this->assertEquals(5, count($merchants2));
    }
    
    function testGetAreaListForAirport()
    {
    	$complete_airport = new CompleteAirport(1000);
    	$area_list = $complete_airport->getAirportAreas();
    	$this->assertEquals(4, count($area_list));
    }
    
    function testGetCompleteAirportStaticCall()
    {
    	
    	$complete_airport = CompleteAirport::getCompleteAirport(1000);
    	$this->assertNotNull($complete_airport->airport_areas);
    	$this->assertCount(4, $complete_airport->airport_areas,"shold have found 4 areas for DIA");
    	$this->assertTrue(is_array($complete_airport->airport_areas),"areas should be an array of hashes");
    	$this->assertNotNull($complete_airport->airport_merchants);
    	$this->assertCount(5, $complete_airport->airport_merchants,"should have found 5 airport merchants");
    	$this->assertTrue(is_array($complete_airport->airport_merchants),"merchants should be an array of hashes");
    	
    }
    
    function testGetAirportMerchantListFromMerchantListObject()
    {
    	$mla = new MerchantListAdapter($mimetypes);
    	$merchant_list = $mla->selectAirportLocations(1000, $this->ids['skin_id'],$data);
    	$this->assertCount(5, $merchant_list);
    	$merchant = $merchant_list[0];
    	$this->assertNotNull($merchant['airport_area_id']);
    	$this->assertNotNull($merchant['location']);
    	$this->assertEquals("Gate 35", $merchant['location']);
    }
    
    function testGetAirportMerchantListFromMerchantController()
    {
    	$user_resource = createNewUser();
    	$user = logTestUserIn($user_resource->user_id);
    	$request = new Request();
    	$request->data['airport_id'] = 1000;
    	$merchant_controller = new MerchantController($mt, $user, $request, 5);
    	$response = $merchant_controller->getMerchantList2($this->ids['skin_id']);
    	$this->assertNotNull($response->airport_areas);
    	$this->assertCount(4, $response->airport_areas);
    	$this->assertNotNull($response->merchants);
    	$this->assertCount(5, $response->merchants);
    	
    	// now set one merchant to innactive
    	$merchant_id = $response->merchants[0]['merchant_id'];
    	$merchant_resource = SplickitController::getResourceFromId($merchant_id, 'Merchant');
    	$merchant_resource->active = 'N';
    	$merchant_resource->save();
    	
    	$response = $merchant_controller->getMerchantList2($this->ids['skin_id']);
    	$this->assertNotNull($response->merchants);
    	$this->assertCount(4, $response->merchants);
    	
    	// now log the store tester in and see if they show up
    	$user = logTestUserIn(101);
    	$request = new Request();
    	$request->data['airport_id'] = 1000;
    	$merchant_controller = new MerchantController($mt, $user, $request, 5);
    	$response = $merchant_controller->getMerchantList2($this->ids['skin_id']);
    	$this->assertNotNull($response->merchants);
    	$this->assertCount(5, $response->merchants);
    	
    }
    
    function testSetAirportInnactive()
    {
    	// create new airport
    	$airport_resource = createNewTestAirport("My Other Airport");
    	$area_resource1 = createAirportArea($airport_resource->id,"Main Terminal");
    	$area_resource2 = createAirportArea($airport_resource->id,"Concourse 1");
    	
    	$user = logTestUserIn($this->ids['user_id']);
    	$request = new Request();
    	$request->url = "/app2/phone/airports";
    	$airport_controller = new AirportController($mimetypes, $user, $request,5);
    	$airports = $airport_controller->getAllAirportsWithUserVerification();
    	$this->assertNotNull($airports);
    	$this->assertEquals(3, count($airports));
    	
    	//now set airport to innactive
    	$airport_resource->active = 'N';
    	$airport_resource->save();
    	
    	$airports = $airport_controller->getAllAirportsWithUserVerification();
    	$this->assertNotNull($airports);
    	$this->assertEquals(2, count($airports));

    }
    
    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;
    	
    	$skin_resource = createYumTicketSkin();
    	setContext($skin_resource->external_identifier);
    	$ids['skin_id'] = $skin_resource->skin_id;
    	
		$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;
    	
/*    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);
*/
    	$merchant_resource = createNewTestMerchant($menu_id);
    	
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
    	$aamma = new AirportAreasMerchantsMapAdapter($mimetypes);
    	// lets add some merchants to the other areas
    	$merchant_resource = createNewTestMerchant($ids['menu_id']);
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$aamma->assignMerchantToAirportArea(1001, $merchant_resource->merchant_id,"Gate 35");
    	
    	$merchant_resource2 = createNewTestMerchant($ids['menu_id']);
    	attachMerchantToSkin($merchant_resource2->merchant_id, $ids['skin_id']);
    	$aamma->assignMerchantToAirportArea(1002, $merchant_resource2->merchant_id,"Gate 35");
    	
    	$merchant_resource3 = createNewTestMerchant($ids['menu_id']);
    	attachMerchantToSkin($merchant_resource3->merchant_id, $ids['skin_id']);
    	$aamma->assignMerchantToAirportArea(1003, $merchant_resource3->merchant_id,"Gate 35");
    	
    	$merchant_resource4 = createNewTestMerchant($ids['menu_id']);
    	attachMerchantToSkin($merchant_resource4->merchant_id, $ids['skin_id']);
    	$aamma->assignMerchantToAirportArea(1001, $merchant_resource4->merchant_id,"Gate 35");

    	// now lets create one attached to the skin but in a different airport
    	$airport_resource = createNewTestAirport("My Airport");
    	$ids['airport_id'] = $airport_resource->id;
    	$area_resource1 = createAirportArea($airport_resource->id,"Main Terminal");
    	$area_resource2 = createAirportArea($airport_resource->id,"Concourse 1");
    	
    	$merchant_resource5 = createNewTestMerchant($ids['menu_id']);
    	attachMerchantToSkin($merchant_resource5->merchant_id, $ids['skin_id']);
    	$aamma->assignMerchantToAirportArea($area_resource1->id, $merchant_resource5->merchant_id, "Food Court");

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

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    AirportFunctions::main();
}

?>