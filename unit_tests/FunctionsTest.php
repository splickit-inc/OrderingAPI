<?php
$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class FunctionsTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
        $_SERVER['ENVIRONMENT_NAME'] = $this->ids['ENVIRONMENT_NAME'];
        $_SERVER['ENVIRONMENT'] = $this->ids['ENVIRONMENT'];

	}

	function tearDown()
	{
		//delete your instance
		$_SERVER['ENVIRONMENT_NAME'] = 'laptop';
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }

	function testGetTimeStringFormatedForMerchant()
	{
		// default is denver
		$time_stamp = mktime(7,32,0,7,20,1969);
		$merchant_resource = createNewTestMerchant();
		$merchant = $merchant_resource->getDataFieldsReally();
		$expected = 'Sun 7:32 AM';
		$this->assertEquals($expected,getTimeStringForUnixTimeStampInMerchantLocal('D g:i A',$time_stamp,$merchant));

		$merchant['time_zone'] = -5;
		$expected = 'Sun 9:32 AM';
		$this->assertEquals($expected,getTimeStringForUnixTimeStampInMerchantLocal('D g:i A',$time_stamp,$merchant));

		$merchant['time_zone'] = -8;
		$expected = 'Sun 6:32 AM';
		$this->assertEquals($expected,getTimeStringForUnixTimeStampInMerchantLocal('D g:i A',$time_stamp,$merchant));

	}

	function testProductionCredentials()
	{
	    unset($_SERVER['ENVIRONMENT']);
		$data['name'] = "sumdumname";
		$data['value'] = "sumdumvalue";
		$r1 = Resource::createByData(new ThirdPartyProductionCredentialsAdapter(),$data);

		// and now one to override a value in the default.conf file
		$data['name'] = "default_test_value";
		$data['value'] = "production";
		$r2 = Resource::createByData(new ThirdPartyProductionCredentialsAdapter(),$data);

		$global_properties = loadProperties();
		$this->assertNull($global_properties['sumdumname']);
		$this->assertEquals('default',$global_properties['default_test_value']);

		// now fake production
		$_SERVER['ENVIRONMENT_NAME'] = 'prod';
        $_SERVER['ENVIRONMENT'] = 'prod';
		$global_properties = loadProperties();
		$this->assertEquals('sumdumvalue',$global_properties['sumdumname']);
		$this->assertEquals('production',$global_properties['default_test_value']);
	}


  function testHardWrap() {      
    $normal_string = "Line1::Line2::Line3ISWAYTOOLONG::Line4";
    $wrapped_string = hard_wrap($normal_string, 5, "::");
    $this->assertEquals("Line1::Line2::Line3::ISWAY::TOOLO::NG::Line4", $wrapped_string, "We should wrap the Line3ISWAYTOOLONG chunk into smaller chunks, separated by the separator, without touching any other chunks.");
  }
  
	function testEnvironmentStuffLaptop()
	{
	    myerror_log("STARTING TESTS");
		$ids = $this->ids;
		$db_info = DatabaseInfo::getDbInfo(null);
        unset($db_info->port);
        $this->assertEquals($ids['dbs']->unit_test->database,$db_info->database);
        $this->assertEquals($ids['dbs']->unit_test->username,$db_info->username);
        //$this->assertEquals('laptop',getEnvironment());
        //  $this->assertEquals('laptop',$_SERVER['ENVIRONMENT_NAME']);
		$this->assertTrue(validateSystemPropertiesConfiguration());
		$this->assertEquals("https://api-staging.value.io/v1/",getProperty("vio_url"),"should have gotten the test VIO url");
	}

	function testEnvironmentStuffProduction()
	{
		if (@fsockopen('127.0.0.1', 3307)) {
			;//all is good
		} else {
			throw new Exception("This test needs a tunnel opened to the production db. You can ignore this error.");
		}

		//Linux tweb03-i-027c8323.splickit.com 2.6.18-xenU-ec2-v1.2 #2 SMP Wed Aug 19 09:04:38 EDT 2009 i686 i686 i386 GNU/Linux
		unset($_SERVER['ENVIRONMENT']);
		$ids = $this->ids;
		$shell_output = "Linux pweb03-i-027c8323.splickit.com 2.6.18-xenU-ec2-v1.2 #2 SMP Wed Aug 19 09:04:38 EDT 2009 i686 i686 i386 GNU/Linux";
		$db_info = DatabaseInfo::getDbInfo(null);
		$this->assertEquals($ids['dbs']->production,$db_info);
		$this->assertEquals('prod',$_SERVER['ENVIRONMENT_NAME']);
		$this->assertEquals('prod',getEnvironment());
		try {
			validateSystemPropertiesConfiguration();
			$this->assertTrue(false);
		} catch (SystemPropertiesConfigurationException $e) {
			$this->assertEquals("SYSTEM CONFIGURATION EXCEPTION: laptop vs prod",$e->getMessage());
		}
		setGlobalProperties(null,getEnvironmentConfigurationProperties());
		$this->assertTrue(validateSystemPropertiesConfiguration());
		$this->assertEquals("https://api.value.io/v1/",getProperty("vio_url"),"should have gotten the production VIO url");
		$_SERVER['ENVIRONMENT'] = 'unit_test';
	}

	function testEnvironmentStuffStaging()
	{
		if (@fsockopen('127.0.0.1', 3308)) {
			;//all is good
		} else {
			throw new Exception("This test needs a tunnel opened to the staging db. You can ignore this error.");
		}

		//Linux tweb03-i-027c8323.splickit.com 2.6.18-xenU-ec2-v1.2 #2 SMP Wed Aug 19 09:04:38 EDT 2009 i686 i686 i386 GNU/Linux
		unset($_SERVER['ENVIRONMENT']);
		$ids = $this->ids;
		$shell_output = "Linux tweb03-i-027c8323.splickit.com 2.6.18-xenU-ec2-v1.2 #2 SMP Wed Aug 19 09:04:38 EDT 2009 i686 i686 i386 GNU/Linux";
		$db_info = DatabaseInfo::getDbInfo(null);
		$this->assertEquals($ids['dbs']->staging,$db_info);
		$this->assertEquals('staging',$_SERVER['ENVIRONMENT_NAME']);
		$this->assertEquals('staging',getEnvironment());
		try {
			validateSystemPropertiesConfiguration();
			$this->assertTrue(false);
		} catch (SystemPropertiesConfigurationException $e) {
			$this->assertEquals("SYSTEM CONFIGURATION EXCEPTION: laptop vs staging",$e->getMessage());
		}
		setGlobalProperties(null,getEnvironmentConfigurationProperties());
		$this->assertTrue(validateSystemPropertiesConfiguration());
		$this->assertEquals("https://api-staging.value.io/v1/",getProperty("vio_url"),"should have gotten the test VIO url");
		$_SERVER['ENVIRONMENT'] = 'unit_test';
	}

//	function testEnvironmentStuffDevelopment()
//	{
//		//Linux tweb03-i-027c8323.splickit.com 2.6.18-xenU-ec2-v1.2 #2 SMP Wed Aug 19 09:04:38 EDT 2009 i686 i686 i386 GNU/Linux
//		unset($_SERVER['ENVIRONMENT']);
//		$ids = $this->ids;
//		$shell_output = "Linux tweb05-i-027c8323.splickit.com 2.6.18-xenU-ec2-v1.2 #2 SMP Wed Aug 19 09:04:38 EDT 2009 i686 i686 i386 GNU/Linux";
//		$db_info = DatabaseInfo::getDbInfo($shell_output);
//		$this->assertEquals('staging',$db_info->hostname);
//		$this->assertEquals('development',$_SERVER['ENVIRONMENT_NAME']);
//		$this->assertEquals('development',getEnvironment());
//		try {
//			validateSystemPropertiesConfiguration();
//			$this->assertTrue(false);
//		} catch (SystemPropertiesConfigurationException $e) {
//			$this->assertEquals("SYSTEM CONFIGURATION EXCEPTION: laptop vs development",$e->getMessage());
//		}
//		setGlobalProperties(null,getEnvironmentConfigurationProperties());
//		$this->assertTrue(validateSystemPropertiesConfiguration());
//		$this->assertEquals("https://api-staging.value.io/v1/",getProperty("vio_url"),"should have gotten the test VIO url");
//		$_SERVER['ENVIRONMENT'] = 'unit_test';
//	}
//
//	function testEnvironmentStuffUAT()
//	{
//		//Linux tweb03-i-027c8323.splickit.com 2.6.18-xenU-ec2-v1.2 #2 SMP Wed Aug 19 09:04:38 EDT 2009 i686 i686 i386 GNU/Linux
//		unset($_SERVER['ENVIRONMENT']);
//		$ids = $this->ids;
//		$shell_output = "Linux puat99-i-027c8323.splickit.com 2.6.18-xenU-ec2-v1.2 #2 SMP Wed Aug 19 09:04:38 EDT 2009 i686 i686 i386 GNU/Linux";
//		$db_info = DatabaseInfo::getDbInfo($shell_output);
//		$this->assertEquals('uat',$db_info->hostname);
//		$this->assertEquals('uat',$_SERVER['ENVIRONMENT_NAME']);
//		$this->assertEquals('uat',getEnvironment());
//		try {
//			validateSystemPropertiesConfiguration();
//			$this->assertTrue(false);
//		} catch (SystemPropertiesConfigurationException $e) {
//			$this->assertEquals("SYSTEM CONFIGURATION EXCEPTION: laptop vs uat",$e->getMessage());
//		}
//		setGlobalProperties(null,getEnvironmentConfigurationProperties());
//		$this->assertTrue(validateSystemPropertiesConfiguration());
//		$this->assertEquals("https://api-staging.value.io/v1/",getProperty("vio_url"),"should have gotten the test VIO url");
//		$_SERVER['ENVIRONMENT'] = 'unit_test';
//	}

	function testGetTimeStamp24HoursFromNow()
    {
    	$this->assertEquals(time()+(24*60*60), getTimeStamp24HoursFromNow(),"time stamp is not 24 hours from now");
    }

    function testgetCurrentTimeZoneOffsetFromTimeZoneStringEastern()
    {
    	$offset = -5 + date("I");
    	$this->assertEquals($offset,getCurrentOffsetForTimeZone("America/New_York"));
    }

    function testgetCurrentTimeZoneOffsetFromTimeZoneStringCentral()
    {
    	$offset = -6 + date("I");
    	$this->assertEquals($offset,getCurrentOffsetForTimeZone("America/Chicago"));
    }

    function testgetCurrentTimeZoneOffsetFromTimeZoneStringMountain()
    {
    	$offset = -7 + date("I");
    	$this->assertEquals($offset,getCurrentOffsetForTimeZone("America/Denver"));
    }

    function testgetCurrentTimeZoneOffsetFromTimeZoneStringAZ()
    {
    	$offset = -7;
    	$this->assertEquals($offset,getCurrentOffsetForTimeZone("America/Phoenix"));
    }

    function testgetCurrentTimeZoneOffsetFromTimeZoneStringWestCoast()
    {
    	$offset = -8 + date("I");
    	$this->assertEquals($offset,getCurrentOffsetForTimeZone("America/Los_Angeles"));
    }

    function testGetAPIDocs()
    {
    	$output = getAPIDocs();
    	$this->assertNotNull($output);
    }

    function testGetRecordFromPrimaryKey()
    {
    	$brand_adapter = new BrandAdapter($mimetypes);
    	$record = $brand_adapter->getRecordFromPrimaryKey(282);
    	$this->assertEquals('Pita Pit', $record['brand_name']);
    }

    function testXMLstringFromHashMap()
    {
    	$sub_hash = array("item1"=>"value1","item2"=>"value2 & this");
    	$top['this']='again "classic"';
    	$top['that']='som more';
    	$top['sub'] = $sub_hash;
    	$top['sumdum'] = 'guy';
    	$xml_string = createXmlFromHashMap($top, $val);
    	$this->assertEquals('<this>again "classic"</this><that>som more</that><sub><item1>value1</item1><item2>value2 & this</item2></sub><sumdum>guy</sumdum>', htmlspecialchars_decode($xml_string));
    }

    function testCleanPasswordFromLoging()
    {
    	$body = '{"jsonVal":{"first_name":"Brian","last_name":"Linn","email":"jgjgjgj@gmail.com","password":"mypa8457kjasd","contact_no":"2408188925","loyalty_number":""}}';
    	$clean_body = cleanPasswordFromBody($body);
    	$this->assertEquals('{"jsonVal":{"first_name":"Brian","last_name":"Linn","email":"jgjgjgj@gmail.com","password":"xxxxxxxxxx","contact_no":"2408188925","loyalty_number":""}}', $clean_body);

    	$body = 'jsonVal=%7B%22last_name%22%3A%22adfasdf%22%2C%22first_name%22%3A%22Shannon%22%2C%22email%22%3A%22jdfh7d6fh6n1976%40gmail.com%22%2C%22password%22%3A%22campdesk1%22%2C%22contact_no%22%3A%22765465746%22%7D';
    	$clean_body = cleanPasswordFromBody($body);
    	$this->assertEquals('jsonVal=%7B%22last_name%22%3A%22adfasdf%22%2C%22first_name%22%3A%22Shannon%22%2C%22email%22%3A%22jdfh7d6fh6n1976%40gmail.com%22%2C%22password%22%3A%22xxxxxxxxxx%22%2C%22contact_no%22%3A%22765465746%22%7D', $clean_body);

    	$body = '{"jsonVal":{"merchant_id":"101691","items":[{"quantity":1,"note":"Please put chipotle ranch on the side. Thanks :)","item_id":"276461","size_id":"89497","sizeprice_id":"1571360","mods":[{"mod_sizeprice_id":"6079032","mod_quantity":1},{"mod_sizeprice_id":"6079036","mod_quantity":1},{"mod_sizeprice_id":"6079044","mod_quantity":1},{"mod_sizeprice_id":"6079072","mod_quantity":1},{"mod_sizeprice_id":"6079071","mod_quantity":1},{"mod_sizeprice_id":"6079070","mod_quantity":1},{"mod_sizeprice_id":"6079045","mod_quantity":1},{"mod_sizeprice_id":"6079064","mod_quantity":1},{"mod_sizeprice_id":"6079069","mod_quantity":1},{"mod_sizeprice_id":"6079066","mod_quantity":1},{"mod_sizeprice_id":"6079037","mod_quantity":1},{"mod_sizeprice_id":"6079043","mod_quantity":1},{"mod_sizeprice_id":"6079051","mod_quantity":1},{"mod_sizeprice_id":"6079065","mod_quantity":1}]}],"total_points_used":0,"note":"","lead_time":"","tip":"","user_id":"1750789","favorite_name":"Moes"}}';
    	$clean_body = cleanPasswordFromBody($body);
    	$this->assertEquals($body, $clean_body);
    }

    function testTempUserTests()
    {
    	$user_resource = createNewUser();
    	logTestUserIn($user_resource->user_id);
    	$this->assertFalse(isLoggedInUserATempUser());

    	$code = generateCode(20);
    	$user_resource->email = "$code@splickit.dum";
    	$user_resource->save();
    	logTestUserIn($user_resource->user_id);
    	$this->assertTrue(isLoggedInUserATempUser());

    }

    function testGenerateAlaphaCode()
    {
    	$code = generateAlphaCode(10);
    	$pattern = '/([a-z]{10})/';
	    $matches = array();
	    $this->assertEquals(1,preg_match($pattern, $code, $matches));
	    $this->assertEquals($code, $matches[0]);

    }

    function testGenerateAlphaNumericCode()
    {
    	$code = generateCode(10);
    	$pattern = '/([A-Z,0-9]{10})/';
	    $matches = array();
	    $this->assertEquals(1,preg_match($pattern, $code, $matches));
	    $this->assertEquals($code, $matches[0]);
    }

    function testCCCleanFunction()
    {
    	$full_message = "cc_number = 1234567876533456";
    	$clean = cleanCCNumberIfExists($full_message);
    	$this->assertEquals('cc_number = 1xxxxxxxxxxx3456', $clean);
    }

    function testCleanCCWithCVV()
    {
    	$body = '{"cc_exp_date":"11/2015","cc_number":"6574657463829123","cvv":"234","zip":"12345"}';
    	$clean = cleanCCNumberIfExists($body);
    	$this->assertEquals('{"cc_exp_date":"11/2015","cc_number":"6xxxxxxxxxxx9123","cvv":"xxx","zip":"12345"}', $clean);
    }

/*    function testPerformanceCCCleanFunction()
    {
	    for ($j=0;$j<10;$j++)
	    {
	    	$number = 'the lazy fox jumped over the brown cc_umber=1234567890123465 bird is it is not used';
	    	$i = 1;
	    	$time1 = microtime(true);
	    	while ($i<1001) {
	    		//$number = cleanCCNumberIfExists($number);
	    		myerror_log($number);
	    		$i++;
	    	}
	    	$time2 = microtime(true);
	    	$diff = $time2-$time1;
	    	myerror_log("the time to execute is: ".$diff);
	    }
	    die;
    }
*/
    function testLowecaseHashmapFromMixed()
    {
    	$data['HereIam'] = "AdamIsCool";
    	$data['seCond'] = array("myPlace"=>"isInBrooklyn");
    	$data['LAST'] = "SHOULDBEALLCAPS";
    	$result = createLowercaseHashmapFromMixedHashmap($data);
    	$this->assertEquals("AdamIsCool", $result['hereiam']);
    	$this->assertEquals("isInBrooklyn",$result['second']['myplace']);
    	$this->assertEquals("SHOULDBEALLCAPS",$result['last']);
    }

    function testValidStringFieldOnArry()
    {
    	$data['bob'] = 'hellp';
    	$data['sally'] = null;
    	$data['empty'] = '  ';
    	$this->assertTrue(validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($data, 'bob'));
    	$this->assertFalse(validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($data, 'sally'));
    	$this->assertTrue(isset($data['empty']));
    	$this->assertFalse(validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($data, 'empty'));
    	$this->assertFalse(validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($data, 'unknown'));
    }

    function testValidStringFieldOnResource()
    {
    	$data = new Resource($adapter, $data);
    	$data->set('bob','help');
    	$data->set('sally',null);
    	$data->set('empty','  ');
    	$this->assertTrue(validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($data, 'bob'));
    	$this->assertFalse(validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($data, 'sally'));
    	$this->assertTrue(isset($data->empty));
    	$this->assertFalse(validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($data, 'empty'));
    	$this->assertFalse(validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($data, 'unknown'));
    }

    function testConvertResourceToJsonResponseBody()
    {
    	$some_resource = createNewUserWithCC();
    	$json_body = convertResourceToJsonResponseBody($some_resource);
    	$this->assertNotContains('_adapter', $json_body);
    	$this->assertNotContains('_exits', $json_body);
    	$this->assertContains('user_id', $json_body);
    	$this->assertContains('first_name', $json_body);

    	// now add an error field
    	$some_resource->set('error',"some dum error");
    	$some_resource->set('error_code',123);
    	$json_body = convertResourceToJsonResponseBody($some_resource);
    	$this->assertEquals('{"ERROR":"some dum error","ERROR_CODE":123,"TEXT_TITLE":null,"TEXT_FOR_BUTTON":null,"FATAL":null,"URL":null,"stamp":null}', $json_body);

    }
	
	function testCapitalizeWordAndAddSpaceBetweenWords(){
		$result = capitalizeWordAndAddSpaceBetweenWords("serving_size");
		$this->assertEquals("Serving Size", $result);
		$result = capitalizeWordAndAddSpaceBetweenWords("sugars");
		$this->assertEquals("Sugars", $result);
		$result = capitalizeWordAndAddSpaceBetweenWords("dietary_fiber");
		$this->assertEquals("Dietary Fiber", $result);
		$result = capitalizeWordAndAddSpaceBetweenWords("");
		$this->assertNull($result);
		$result = capitalizeWordAndAddSpaceBetweenWords(12);
		$this->assertNull($result);
		$result = capitalizeWordAndAddSpaceBetweenWords(" calories_from_fat ");
		$this->assertEquals("Calories From Fat", $result);
		$result = capitalizeWordAndAddSpaceBetweenWords("  calories   from    fat   ");
		$this->assertEquals("Calories From Fat", $result);
		//$result = capitalizeWordAndAddSpaceBetweenWords("  calories * from  *-  fat   ");//TODO test is failing here its needs add code to support this
		//$this->assertNull($result);
	}

	function testConvertDecimalToInteger(){
		$result = convertDecimalToIntegerOrRoundUp(11.56);
		$this->assertEquals(11.6, $result);
		$result = convertDecimalToIntegerOrRoundUp(10.05);
		$this->assertEquals(10.1, $result);
		$result = convertDecimalToIntegerOrRoundUp(10.08);
		$this->assertEquals(10.1, $result);
		$result = convertDecimalToIntegerOrRoundUp(10.1);
		$this->assertEquals(10.1, $result);
		$result = convertDecimalToIntegerOrRoundUp(10.11);
		$this->assertEquals(10.1, $result);
		$result = convertDecimalToIntegerOrRoundUp(10.54);
		$this->assertEquals(10.5, $result);
		$result = convertDecimalToIntegerOrRoundUp(10.55);
		$this->assertEquals(10.6, $result);//fails
		$result = convertDecimalToIntegerOrRoundUp(10.84);
		$this->assertEquals(10.8, $result);
		$result = convertDecimalToIntegerOrRoundUp(10.85);
		$this->assertEquals(10.9, $result);
		$result = convertDecimalToIntegerOrRoundUp(10.95);
		$this->assertEquals(11, $result);
		$result = convertDecimalToIntegerOrRoundUp(0.00);
		$this->assertEquals(0, $result);
	}

    static function setUpBeforeClass()
    {
 //*
		$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
    	      SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;

    	$skin_resource = createWorldHqSkin();
    	$ids['skin_id'] = $skin_resource->skin_id;

		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;

    	$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
    	$modifier_group_id = $modifier_group_resource->modifier_group_id;
    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
    	assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);

    	$merchant_resource = createNewTestMerchant($menu_id);
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;

    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;

		//$filename = "/usr/local/splickit/etc/smaw_database.conf";
        $filename = "./config/smaw_database_unittest.conf";
		$txt = file_get_contents($filename);
		$dbs = json_decode($txt);
		$ids['dbs'] = $dbs;
        $ids['ENVIRONMENT_NAME'] = 'unit_test';
        $ids['ENVIRONMENT'] = 'unit_test';

		$_SERVER['log_level'] = 5;
    	$_SERVER['unit_test_ids'] = $ids;
        unset($_SERVER['ENVIRONMENT']);
  //  */
    }

	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();
        $mysqli->rollback();
    	date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}

}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    FunctionsTest::main();
}
?>