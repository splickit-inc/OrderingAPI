<?php
$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class MerchantListTest extends PHPUnit_Framework_TestCase
{
	var $user;
	var $stamp;
	var $ids;
	
	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		
		setContext("com.splickit.worldhq");
		
		$this->ids = $_SERVER['unit_test_ids'];		
		$this->user = logTestUserIn($this->ids['user_id']);
		setProperty("use_merchant_caching", "true");
	}
	
	function tearDown()
	{
		unset($this->user);
		unset($this->stamp);
		unset($this->ids);
	}

	function testGetStateAbreviationFromFullStateName()
	{
		$merchant_controller = new MerchantController($m,$u,$r,5);
		$state = $merchant_controller->getStateAbreviationFromFullStateName("ColOraDo");
		$this->assertEquals('CO',$state,"It should have returned CO");

		$this->assertNull($merchant_controller->getStateAbreviationFromFullStateName("tyujhasdfj"),"It should return null if the state name doesn't match");

	}
	
	function testSQLInjection()
	{
		//setContext("com.splickit.moes");
		$request = new Request();
		$data = array ('location'=>"30A02");
		$request->data = $data;
		$merchant_controller = new MerchantController($mt, $this->user, $request,5);
		
		$resource = $merchant_controller->getMerchantList2($_SERVER['SKIN_ID']);
		$this->assertNotNull($resource->error);
		$expected = "Sorry. We could not find any merchants matching those search criteria. Please make sure you use format 'city, state abreviation' or just 'city' or just 'state abbreviation' or just 'zip'.";
		$this->assertEquals($expected, $resource->error);
		
		$request = new Request();
		$data = array ('location'=>"this i-m");
		$request->data = $data;
		$merchant_controller = new MerchantController($mt, $this->user, $request,5);
		
		$resource = $merchant_controller->getMerchantList2($_SERVER['SKIN_ID']);
		$this->assertNotNull($resource->error);
		$expected = "Sorry, please enter only letters and numbers for city, state or zip.";
		$this->assertEquals($expected, $resource->error);
		
		$request = new Request();
		$data = array ('location'=>"Atlanta, GA");
		$request->data = $data;
		$merchant_controller = new MerchantController($mt, $this->user, $request,5);
		
		$resource = $merchant_controller->getMerchantList2($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$merchants =$resource->merchants;
		$this->assertCount(15, $merchants);
		$resource2 = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource2->error);
		$json = createJsonForResponseFromData($data);
		$response = getResponseWithJsonFromResource($resource2, $headers);
		$this->assertEquals(200, $response->statusCode);
	}
	
	function testZip()
	{
		//$results = LatLong::generateLatLong("10250",false);
				
		$request = new Request();
		$data = array ('zip'=>'30A02');
		$request->data = $data;
		$merchant_controller = new MerchantController($mt, $this->user, $request,5);
		
		$resource = $merchant_controller->getMerchantList2($_SERVER['SKIN_ID']);
		$this->assertNotNull($resource->error);
		
	}
  
  function testZipWithLocation() {
    $zip_request = new Request();
    $zip_data = array('zip'=>'80301');
    $zip_request->data = $zip_data;
    $merchant_controller_for_zip = new MerchantController($mt, $this->user, $zip_request, 5);
    
    $location_request = new Request();
    $location_data = array('location'=>'80301');
    $location_request->data = $location_data;
    $merchant_controller_for_location = new MerchantController($mt, $this->user, $location_request, 5);
    
    $zip_resource = $merchant_controller_for_zip->getMerchantList2($_SERVER['SKIN_ID']);
    $location_resource = $merchant_controller_for_location->getMerchantList2($_SERVER['SKIN_ID']);
    
    $this->assertEquals(50, sizeof($location_resource->merchants));
    $this->assertEquals($zip_resource, $location_resource);
  }
	
	function testFakeZipCodeSearchWith3()
	{
		$sql = "SELECT * FROM Merchant WHERE zip LIKE '803%' ORDER BY merchant_id asc LIMIT 1";
		$zip_lookup_adapter = new ZipLookupAdapter($mimetypes);
		$options[TONIC_FIND_BY_SQL] = $sql;

		$records = $zip_lookup_adapter->select('',$options);
		$record = $records[0];
		
		$zip_record = ZipLookupAdapter::getFakeZipCodeLatLong(80399);
		$this->assertNotNull($zip_record);
		$this->assertEquals($record['lat'], $zip_record['lat']);
		$this->assertEquals($record['lng'], $zip_record['lng']);
		$this->assertEquals($record['time_zone'], $zip_record['time_zone_offset']);

	}

	function testFakeZipCodeSearchWith2()
	{
		$sql = "SELECT * FROM Merchant WHERE zip LIKE '07%' ORDER BY merchant_id asc LIMIT 1";
		$zip_lookup_adapter = new ZipLookupAdapter($mimetypes);
		$options[TONIC_FIND_BY_SQL] = $sql;

		$records = $zip_lookup_adapter->select('',$options);
		$record = $records[0];
		
		$zip_record = ZipLookupAdapter::getFakeZipCodeLatLong('07299');
		$this->assertNotNull($zip_record);
		$this->assertEquals($record['lat'], $zip_record['lat']);
		$this->assertEquals($record['lng'], $zip_record['lng']);
		$this->assertEquals($record['time_zone'], $zip_record['time_zone_offset']);

	}
	
	function testMinimumMerchantsWithLongRange()
	{
		myerror_log("starting: ".__FUNCTION__);
		$request = new Request();
		$data = array ('zip'=>'30302','range'=>100,'minimum_merchant_count'=>25);
		$request->data = $data;
		$merchant_controller = new MerchantController($mt, $this->user, $request,5);
		
		$resource = $merchant_controller->getMerchantList2($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals(67, sizeof($resource->merchants));
		$merchant_resource = array_pop($resource->merchants);
		$this->assertTrue($merchant_resource->distance < 100);

	}
		
	function testMinimumMerchantsWithShortRange()
	{
		myerror_log("starting: ".__FUNCTION__);
		$request = new Request();
		$data = array ('zip'=>'30302','range'=>2,'minimum_merchant_count'=>25);
		$request->data = $data;
		$merchant_controller = new MerchantController($mt, $this->user, $request,5);
		
		$resource = $merchant_controller->getMerchantList2($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals(25, sizeof($resource->merchants));
	}

	function testMinimumMerchantsWithShortRangeAndExcludeList()
	{
		myerror_log("starting: ".__FUNCTION__);
		$request = new Request();
		$data = array ('zip'=>'30302','range'=>2,'minimum_merchant_count'=>25,'exclude_ids'=>'103933,103953');
		$request->data = $data;
		$merchant_controller = new MerchantController($mt, $this->user, $request,5);
		
		$resource = $merchant_controller->getMerchantList2($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals(25, sizeof($resource->merchants));
		$merchants = $resource->merchants;
		$bad_id_exists = false;
		foreach ($merchants as $merchant)
		{
			if ($merchant['merchant_id'] == 103933 || $merchant['merchant_id'] == 103953)
				$bad_id_exists = true;
		}
		$this->assertFalse($bad_id_exists);
	}
	
	function testAggregateToShowName()
	{
		//$skin_resource = createWorldHqSkin();

		myerror_log("starting: ".__FUNCTION__);
		$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_resource->brand_id = 150;
        $merchant_resource->save();
		
		attachMerchantToSkin($merchant_resource->merchant_id, 5);
		
		// first check branded skin		
		$request = new Request();
		$request->data['zip'] = "80302";
		$merchant_controller = new MerchantController($mt, $u, $request,5);		
		$resource = $merchant_controller->getMerchantList(5);
		$this->assertNull($resource->error);
		//$this->assertEquals(1, sizeof($resource->data));
		foreach ($resource->data as $loop_merchant)
		{
			myerror_log("checking loop id: ".$loop_merchant['merchant_id']."   looking for: ".$merchant_resource->merchant_id);
			if ($loop_merchant['merchant_id'] == $merchant_resource->merchant_id)
				$merchant = $loop_merchant;
		}
		$this->assertEquals("Display Name", trim($merchant['name']));
		
		// now set to agregate and see if name chagnes
        $skin_resource = getOrCreateSkinAndBrandIfNecessary("sumdumskinagain", "sumdumskinagain", 444, 445);
		$skin_resource->mobile_app_type = 'A';
		$skin_resource->save();
        attachMerchantToSkin($merchant_resource->merchant_id, 444);
		setContext("com.splickit.sumdumskinagain");
		$resource2 = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource2->error);
		foreach ($resource2->data as $loop_merchant2)
		{
			if ($loop_merchant2['merchant_id'] == $merchant_resource->merchant_id)
				$merchant2 = $loop_merchant2;
		}
		$this->assertEquals("Unit Test Merchant", trim($merchant2['name']));
	}
		
	function testGetMerchantListNoPromo()
	{
		myerror_log("starting: ".__FUNCTION__);
		$skin_resource = SplickitController::getResourceFromId($this->ids['skin_id'], "Skin");
		$skin_resource->mobile_app_type = 'B';
		$skin_resource->save();
		setContext("com.splickit.worldhq");
		myerror_log("starting get merchant list no promo");
		$request = new Request();
		$request->data['lat'] = 33.757800;
		$request->data['long'] = -84.393700;
		$merchant_controller = new MerchantController($mt, $u, $request,5);		
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		//myerror_log("resource: ".$resource->__toString());
		$this->assertNull($resource->error);
		
		$this->assertEquals(50, sizeof($resource->data));
		
		$test_merchant = null;		
		//first merchatn shoudl be CNN center
		$merchant = $resource->data['0'];
		$this->assertLessThan(1, $merchant['distance']);
		$this->assertEquals('CNN Center', $merchant['name']);
	}
	
	function testGetMerchantListWithLNG()
	{
		myerror_log("starting: ".__FUNCTION__);
		$skin_resource = SplickitController::getResourceFromId($this->ids['skin_id'], "Skin");
		$skin_resource->mobile_app_type = 'B';
		$skin_resource->save();
		setContext("com.splickit.worldhq");
		myerror_log("starting get merchant list no promo");
		$request = new Request();
		$request->data['lat'] = 33.757800;
		$request->data['lng'] = -84.393700;
		$merchant_controller = new MerchantController($mt, $u, $request,5);		
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		//myerror_log("resource: ".$resource->__toString());
		$this->assertNull($resource->error);
		
		$this->assertEquals(50, sizeof($resource->data));
		
		$test_merchant = null;		
		//first merchatn shoudl be CNN center
		$merchant = $resource->data['0'];
		$this->assertLessThan(1, $merchant['distance']);
		$this->assertEquals('CNN Center', $merchant['name']);
	}
	
	function testGetMerchantList()
	{
		myerror_log("starting: ".__FUNCTION__);
		myerror_log("starting get merchant list WITH promo");
		
		$request = new Request();
		$request->data['lat'] = 33.757800;
		$request->data['long'] = -84.393700;
		$merchant_controller = new MerchantController($mt, $u, $request,5);		
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		
		$this->assertNull($resource->error);
		$this->assertEquals(50, sizeof($resource->data));
		
		//first merchatn shoudl be CNN center
		$merchant = $resource->data['0'];
		$this->assertLessThan(1, $merchant['distance'],"first merchant should be less than a mile away!");
		$this->assertEquals('CNN Center', $merchant['name'],"first merchant is NOT CNN Center. WTF?");
		
		$response = getResponseWithJsonFromResource($resource, $headers);
		$this->assertEquals(200, $response->statusCode);
		$this->assertEquals('application/json', $response->headers['Content-Type']);
		
		return $merchant['merchant_id'];
	}
	
	/**
	 * @depends testGetMerchantList
	 */
	function testSearchBoxQuery($merchant_id)
	{
	  $brand_resource = getBrandOrCreateIfNotExists("My New Brand");
		$options[TONIC_FIND_BY_METADATA]['brand_id'] = 300;
		$resources = Resource::findAll(new MerchantAdapter($mimetypes), $url, $options);
		$i = 0;
		$total = 0;
		foreach ($resources as $merchant_resource)
		{
			if ($merchant_resource->active == 'N' || $merchant_resource->merchant_id == $merchant_id)
				continue;
			if ($i == 20)
			{
				$merchant_resource->brand_id = $brand_resource->brand_id;
				if ($total == 10 || $total == 15) {
					$merchant_resource->active = 'N';
				}
                $merchant_resource->display_name = "New Brand $i";
                $merchant_resource->save();
                $total++;
				$i = 0;
			} else {
				$i++;
			}
		}
		myerror_log("we assigned $total merchants to the new brand");
		
		// ok we should have some merchants now in the world hq skin that are part of a different brand
		
		$user = Resource::findExact(new UserAdapter(), '', array(TONIC_FIND_BY_METADATA => array('user_id' => 101)));
		$user->see_inactive_merchants = 1;
		$user->see_demo_merchants = 1;
		$user->save();
		
		$u = logTestUserIn(101);
		
		$request = new Request();
		$request->data['lat'] = 33.757800;
		$request->data['long'] = -84.393700;
		$request->data['query'] = "My New Brand";
		$merchant_controller = new MerchantController(getM(), $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals($total, sizeof($resource->data));

		// test incomplete name
		$request->data['query'] = "w Brand";
		$merchant_controller = new MerchantController($mt, $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals($total, sizeof($resource->data));

		// now do it as a regular user
		
		$u = logTestUserIn($this->ids['user_id']);
		$merchant_controller = new MerchantController($mt, $u, $request,5);		
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals($total-2, sizeof($resource->data));
		
		// now add a merchant with a name matching but with punctuation
		$new_m = SplickitController::getResourceFromId($merchant_id, 'Merchant');
		$new_m->active = 'Y';
		$new_m->name = "The My New Bra'nd Merchant1";
		$new_m->save();
		
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals($total-1, sizeof($resource->data));
		
		// now try no mathcing
		$request->data['query'] = "Other Brand";
		$merchant_controller = new MerchantController($mt, $u, $request,5);		
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNotNull($resource->error);
		$this->assertEquals("Sorry. We could not find any existing merchants matching that name or having that brand.", $resource->error);
		
		$brand_resource->set("total",$total);
		return $brand_resource;
	}

	function testSearchBoxLocation()
	{

		$resources = Resource::findAll(new MerchantAdapter($mimetypes), $url, $options);
		$ma = new MerchantAdapter($mimetypes);
		$sql = "Select * from Merchant a JOIN Skin_Merchant_Map b ON a.merchant_id = b.merchant_id WHERE b.skin_id = ".$this->ids['skin_id']." AND a.state = 'SC' ";
		$options[TONIC_FIND_BY_SQL] = 	$sql;

		$merchant_resources = Resource::findAll($ma, $url, $options);
		$total = 0;
		$total_fc = 0;
		$total_sl = 0;
		foreach ($merchant_resources as $merchant_resource) {
			if ($total == 8 || $total == 16) {
				$merchant_resource->city = 'Yakutat';
				$merchant_resource->zip = "12399";
			} else if ($total == 5) {
				$merchant_resource->city = 'Anchorage';
				$merchant_resource->state = 'AK';
				$merchant_resource->zip = '12345';
			} else if (($total % 7) == 0){
				$merchant_resource->city = 'Fort Collins';
				$merchant_resource->state = 'CO';
				$total_fc++;
			} else if (($total % 11) == 0){
				$merchant_resource->city = 'St. Louis';
				$merchant_resource->state = 'MO';
				$total_sl++;
			} else {
				$merchant_resource->city = 'Anchorage';
			}
			$merchant_resource->active = 'Y';
			$merchant_resource->save();
			$total++;
		}
		myerror_log("we assigned $total merchants to the new state");

		$records = MerchantAdapter::staticGetRecords(array("active"=>'Y'),'MerchantAdapter');
		foreach ($records as $mr) {
			$state_hash[strtoupper($mr['state'])][] = $mr;
			$city_hash[strtolower($mr['city'])][] = $mr;
		}

		$u = logTestUserIn($this->ids['user_id']);
		$request = new Request();
		$request->data['lat'] = 33.757800;
		$request->data['long'] = -84.393700;
		$request->data['location'] = "Anchorage";
		// location searches should ignore the limit.
		$request->data['limit'] = "5";
		$merchant_controller = new MerchantController($mt, $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals($total-29, sizeof($resource->data));

		$request->data['location'] = "Anchorage, SC";
		$merchant_controller = new MerchantController($mt, $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals($total-10, sizeof($resource->data));

		$request->data['location'] = "SC";
		$merchant_controller = new MerchantController($mt, $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals($total-8, sizeof($resource->data));

		$request->data['location'] = "Anchorage, AK";
		$merchant_controller = new MerchantController($mt, $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals(1, sizeof($resource->data));

		$request->data['location'] = "Anchorage, CA";
		$merchant_controller = new MerchantController($mt, $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNotNull($resource->error);
		$this->assertEquals("Sorry. We could not find any merchants matching those search criteria. Please make sure you use format 'city, state abreviation' or just 'city' or just 'state abbreviation' or just 'zip'.", $resource->error);

		//test city name with spaces
		$request->data['location'] = "Fort Collins";
		$merchant_controller = new MerchantController($mt, $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals($total_fc, sizeof($resource->data));

		$request->data['location'] = "St. Louis";
		$merchant_controller = new MerchantController($mt, $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals($total_sl + 1, sizeof($resource->data));


		//zip test
		unset($request->data['limit']);
		$request->data['location'] = "12345";
		$merchant_controller = new MerchantController($mt, $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals(50, sizeof($resource->data));

		$request->data['location'] = "123";
		$merchant_controller = new MerchantController($mt, $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		//thought it was going to be three but avon indiana has a zip code of 46123
		$this->assertEquals(4, sizeof($resource->data));

		//full name state test
		$request->data['location'] = "Georgia";
		$request->data['limit'] = "5";
		$merchant_controller = new MerchantController($mt, $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertTrue(count($state_hash['GA']) != 5,"Cant have 5 becuase we're trying to show the limit override");
		$this->assertTrue(count($state_hash['GA']) > 0,'Make sure there some in Georgia');
		$this->assertEquals(count($state_hash['GA']), sizeof($resource->data));

		//DMA tests
		$dma_adapter = new DmaAdapter($mimetypes);
		$this->assertNotNull($dma_adapter);
		$dma_options[TONIC_FIND_BY_METADATA]['state'] = "Alaska";
		$result = $dma_adapter->select('id', $dma_options);
		$this->assertNotEmpty($result);

		$dma_codes_adapter = new DmaCodesAdapter($mimetypes);
		$sql = "SELECT * FROM adm_dma_codes";
		$options_dm[TONIC_FIND_BY_SQL] = $sql;
		$result = $dma_codes_adapter->select('',$options_dm);
		$this->assertNotEquals(0, sizeof($result));

		$this->assertNotNull($dma_codes_adapter);
		$dma_codes_options[TONIC_FIND_BY_METADATA]['dma_region'] = "Columbus";
		$result = $dma_codes_adapter->select('dma_region_code', $dma_codes_options);
		$this->assertEquals(2, sizeof($result));

		$result = $dma_codes_adapter->getRecords(array("dma_region" => "Kansas City"));
		$this->assertNotNull($result);
		$this->assertEquals(1, sizeof($result));

		$request->data['location'] = "Kansas City";
		$request->data['limit'] = "5";
		$merchant_controller = new MerchantController($mt, $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals(1, sizeof($resource->data));

		$request->data['location'] = "Columbus";
		$request->data['limit'] = "5";
		$merchant_controller = new MerchantController($mt, $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNotNull($resource->error);
		$this->assertEquals("Many registries with this city. Please enter city, st.", $resource->error);

		$request->data['location'] = "Columbus, OH";
		$request->data['limit'] = "5";
		$merchant_controller = new MerchantController($mt, $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNotNull($resource->error);//In this case we don't have merchant registries for Columbus, OH
		$this->assertEquals("Sorry. We could not find any merchants matching those search criteria. Please make sure you use format 'city, state abreviation' or just 'city' or just 'state abbreviation' or just 'zip'.", $resource->error);

		$request->data['location'] = "Columbus, GE";
		$request->data['limit'] = "5";
		$merchant_controller = new MerchantController($mt, $u, $request,5);
		$resource = $merchant_controller->getMerchantList($_SERVER['SKIN_ID']);
		$this->assertNull($resource->error);
		$this->assertEquals(3, sizeof($resource->data));
	}

	function testSingleMerchantWithValidMerchantId()
	{
		$request = new Request();
		$request->url = "/apiv2/merchants?merchantlist=".$this->ids['merchant_id']."&log_level=5";
		$request->data = array("merchantlist"=> $this->ids['merchant_id']);
		$request->method = 'GET';
		$merchant_controller = new MerchantController($mt, $user, $request, 5);
		$resource = $merchant_controller->processV2Request();
		$this->assertNull($resource->error);
		$this->assertCount(1, $resource->merchants, "Return only one merchant");
		$this->assertEquals($this->ids['merchant_id'], $resource->merchants[0]["merchant_id"], "Merchant id is ".$this->ids['merchant_id']);
	}

	function testSingleMerchantWithInvalidMerchantId()
	{
		$request = new Request();
		$request->url = "/apiv2/merchants?merchantlist=84635&log_level=5";
		$request->data = array("merchantlist"=> 84635);
		$request->method = 'GET';
		$merchant_controller = new MerchantController($mt, $user, $request, 5);
		$resource = $merchant_controller->processV2Request();
		$this->assertNotNull($resource->error);
		$this->assertEquals($resource->error, "Sorry. We could not find any existing merchants matching that Merchant Id. If you feel you have reached this in error, please try again or contact support.");
	}
		
  	static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',0);
    	myerror_log("Starting MerchantListAndMenuTest.  test for existance of skin");
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
    	if ($skin = SplickitController::getResourceFromId(250, "Skin"))
    	{
    		myerror_log("we have skin existance");
	    	myerror_log("about to delete the worldhq skin");
	    	$sa = new SkinAdapter(getM());
	    	$sql = "DELETE FROM Skin_Merchant_Map WHERE skin_id = ".$skin->skin_id;
	    	$sa->_query($sql);
	    	$sql = "DELETE FROM Skin WHERE external_identifier = 'com.splickit.worldhq' LIMIT 1";
	    	$sa->_query($sql);
            SplickitCache::flushAll();
    	}

    	//make sure all merchants are set at brand_id 300
    	$sql = "UPDATE Merchant SET brand_id = 300";
    	mysqli_query($mysqli,$sql);
        $skin_resource = createWorldHqSkinAndAddMerchants();
        $mysqli->begin_transaction();
    	$_SERVER['request_time1'] = microtime(true);    	
		
    	// create world hq skin
    	//$skin_resource = createWorldHqSkin();

		// add the apostrophy merchant


    	$ids['skin_id'] = $skin_resource->skin_id;
		
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;
    	  	    	
		$merchant_resource = createNewTestMerchant($menu_id);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array('flags'=>'1C20000001'));
    	$ids['user_id'] = $user_resource->user_id;
    	    	
    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;
    	
    }
    
	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();
    	$db = DataBase::getInstance();
    	$mysqli = $db->getConnection();
    	$mysqli->rollback();
    }
	
		/* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
		$_SERVER['request_time1'] = microtime(true);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    MerchantListTest::main();
}
	