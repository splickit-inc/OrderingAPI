<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class PortalPromoTest extends PHPUnit_Framework_TestCase
{
	var $menu;
	var $merchant;
	var $user;
	var $stamp;
	var $ids;

	function setUp()
	{		
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];

		setContext("com.splickit.order");		
		$this->user = logTestUserIn($_SERVER['unit_test_ids']['user_id']);
		$this->ids = $_SERVER['unit_test_ids'];
		setProperty('duplicate_order_test', 'true');
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		setContext($this->ids['context']);

	}
	
	function tearDown() 
	{
		//delete your instance
		unset($this->user);
    	unset($this->merchant);
    	unset($this->menu);
    	unset($this->ids);
    	$_SERVER['STAMP'] = $this->stamp;
    	unset($this->stamp);
    }

    function testGetPromoWithKeyWordlist()
    {
        $promo_id = $this->ids['promo_id_type_1'];
        $url = "/app2/portal/promos/$promo_id";
        $request = createRequestObject($url,"GET",null);
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->processV2Request();
        $response_array = $response->getDataFieldsReally();
        $this->assertCount(3,$response_array['promo_key_words'],'There should be 3 key words attached to this promo');
        return $promo_id;
    }

    /**
     * @depends testGetPromoWithKeyWordlist
     */
    function testAddKeyWord($promo_id)
    {
        $url = "/app2/portal/promos/$promo_id/key_words";
        $key_word = 'great key word';
        $data['promo_key_word'] = $key_word;
        $request = createRequestObject($url,'POST',json_encode($data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->processV2Request();

        $url = "/app2/portal/promos/$promo_id";
        $request = createRequestObject($url,"GET",null);
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->processV2Request();
        $response_array = $response->getDataFieldsReally();
        $key_word_hash = createHashmapFromArrayOfArraysByFieldName($response_array['promo_key_words'],'promo_key_word');
        $this->assertTrue(isset($key_word_hash["$key_word"]),'It should now have the key word in the list');

    }


    /**
     * @depends testGetPromoWithKeyWordlist
     */
    function testDeleteKeyWord($promo_id)
    {
        $url = "/app2/portal/promos/$promo_id";
        $request = createRequestObject($url,"GET",null);
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->processV2Request();
        $response_array = $response->getDataFieldsReally();
        $key_word_hash = createHashmapFromArrayOfArraysByFieldName($response_array['promo_key_words'],'promo_key_word');
        $this->assertTrue(isset($key_word_hash['sumdumkeyword']),'It should have the key word in the list');
        $key_word_record = $key_word_hash['sumdumkeyword'];
        $map_id = $key_word_record['map_id'];

        $url = "/app2/portal/promos/$promo_id/key_words/$map_id";
        $request = createRequestObject($url,'DELETE');
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->processV2Request();

        $url = "/app2/portal/promos/$promo_id";
        $request = createRequestObject($url,"GET",null);
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->processV2Request();
        $response_array = $response->getDataFieldsReally();
        $key_word_hash = createHashmapFromArrayOfArraysByFieldName($response_array['promo_key_words'],'promo_key_word');
        $this->assertFalse(isset($key_word_hash['sumdumkeyword']),'It should NOT have the key word in the list anymore');
    }

    function testGetPromoWithPromoMerchantMapList()
    {
        $promo_id = $this->ids['promo_type_2_id'];
        $url = "/app2/portal/promos/$promo_id";
        $request = createRequestObject($url,"GET",null);
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->processV2Request();
        $response_array = $response->getDataFieldsReally();
        $this->assertCount(3,$response_array['promo_merchant_maps'],'There should be 3 merchant ids attached to this promo');
        return $response_array['promo_merchant_maps'];
    }

    /**
     * @depends testGetPromoWithPromoMerchantMapList
     */
    function testUpdatePromoMerchantMapRecord($promo_merchant_maps)
    {
        $record_one = $promo_merchant_maps[0];
        $promo_id = $record_one['promo_id'];
        $merchant_id = $record_one['merchant_id'];
        $today = date("Y-m-d");
        $tomorrow = date("Y-m-d",getTomorrowTwelveNoonTimeStampDenver());
        $record_one['start_date'] = $today;
        $record_one['end_date'] = $tomorrow;
        $record_one['max_discount_per_order'] = 8.88;
        $url = "/app2/portal/promos/$promo_id/merchant_maps/".$record_one['map_id'];
        $request = createRequestObject($url,'POST',json_encode($record_one));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->processV2Request();

        $url = "/app2/portal/promos/$promo_id";
        $request = createRequestObject($url,"GET",null);
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->processV2Request();
        $response_array = $response->getDataFieldsReally();
        $merchant_list_hash = createHashmapFromArrayOfArraysByFieldName($response_array['promo_merchant_maps'],'merchant_id');
        $promo_merchant_map = $merchant_list_hash[$merchant_id];
        $this->assertEquals($today,$promo_merchant_map['start_date']);
        $this->assertEquals($tomorrow,$promo_merchant_map['end_date']);
        $this->assertEquals(8.88,$promo_merchant_map['max_discount_per_order']);
    }

    /**
     * @depends testGetPromoWithPromoMerchantMapList
     */
    function testDeletePromoMerchantMapRecord($promo_merchant_maps)
    {
        $record_one = $promo_merchant_maps[0];
        $promo_id = $record_one['promo_id'];
        $map_id = $record_one['map_id'];
        $merchant_id = $record_one['mercant_id'];

        $url = "/app2/portal/promos/$promo_id/merchant_maps/$map_id";
        $request = createRequestObject($url,'DELETE');
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->processV2Request();

        $url = "/app2/portal/promos/$promo_id";
        $request = createRequestObject($url,"GET",null);
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->processV2Request();
        $response_array = $response->getDataFieldsReally();
        $merchant_list_hash = createHashmapFromArrayOfArraysByFieldName($response_array['promo_merchant_maps'],'merchant_id');
        $this->assertCount(2,$merchant_list_hash);
        $this->assertFalse(isset($merchant_list_hash[$merchant_id]),"It should no longer have a record for this merchant: $merchant_id");

    }

    /**
     * @depends testGetPromoWithPromoMerchantMapList
     */
    function testAddPromoMerchantMapRecord($promo_merchant_maps)
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_id = $merchant_resource->merchant_id;
        $record_one = $promo_merchant_maps[0];
        $promo_id = $record_one['promo_id'];

        $url = "/app2/portal/promos/$promo_id";
        $request = createRequestObject($url,"GET",null);
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->processV2Request();
        $response_array = $response->getDataFieldsReally();
        $merchant_list_hash = createHashmapFromArrayOfArraysByFieldName($response_array['promo_merchant_maps'],'merchant_id');
        $this->assertFalse(isset($merchant_list_hash[$merchant_id]),"It should not YET have a record for this merchant: $merchant_id");

        $url = "/app2/portal/promos/$promo_id/merchant_maps";
        $data['merchant_id'] = $merchant_id;
        $data['max_discount_per_order'] = 10.88;
        $request = createRequestObject($url,'POST',json_encode($data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->processV2Request();

        $url = "/app2/portal/promos/$promo_id";
        $request = createRequestObject($url,"GET",null);
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->processV2Request();
        $response_array = $response->getDataFieldsReally();
        $merchant_list_hash = createHashmapFromArrayOfArraysByFieldName($response_array['promo_merchant_maps'],'merchant_id');
        $this->assertTrue(isset($merchant_list_hash[$merchant_id]),"It should now have a record for this merchant: $merchant_id");

    }

    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['default_tz'] = $tz;
    	date_default_timezone_set("America/Denver");

    	SplickitCache::flushAll();
    	$db = DataBase::getInstance();
    	$mysqli = $db->getConnection();
    	$mysqli->begin_transaction(); ;
    	
    	$skin_resource = createWorldHqSkin();
    	setContext($skin_resource->external_identifier);
    	$ids['skin_id'] = $skin_resource->skin_id;
    	$ids['context'] = $skin_resource->external_identifier;
		//map it to a menu
    	$menu_id = createTestMenuWithNnumberOfItems(5);
    	$ids['menu_id'] = $menu_id;

    	$item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());

    	$merchant_resource = createNewTestMerchant($menu_id);
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	$merchant_id = $merchant_resource->merchant_id;

        $merchant_resource2 = createNewTestMerchant($menu_id);
        attachMerchantToSkin($merchant_resource2->merchant_id, $ids['skin_id']);
        $merchant_id2 = $merchant_resource2->merchant_id;

        $merchant_resource3 = createNewTestMerchant($menu_id);
        attachMerchantToSkin($merchant_resource3->merchant_id, $ids['skin_id']);
        $merchant_id3 = $merchant_resource3->merchant_id;



        $billing_entity_resource = createSageBillingEntity($merchant_resource->brand_id);
        $merchant_payment_type_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource->merchant_id, 2000, $billing_entity_resource->id);


        $user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;

        //create the type 2 promo
        $promo_data = [];
        $promo_data['promo_id'] = 200;
        $promo_data['key_word'] = "Test Promo,AlternateKeyWord";
        $promo_data['promo_type'] = 2;
        $promo_data['description'] = 'Get a free this when you purchase a large that';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['allow_multiple_use_per_order'] = true;
        $promo_data['valid_on_first_order_only'] = 'N';
        $promo_data['order_type'] = 'pickup';
        $promo_data['merchant_id'] = "$merchant_id,$merchant_id2,$merchant_id3";
        $promo_data['message1'] = "Congratulations! You're getting a FREE that!";
        $promo_data['message4'] = "Almost there, now add a standard that to this order and its FREE!";
        $promo_data['message5'] = "Here's the deal, order a large this, then add a that to go with it, and its FREE! Limit 1";
        $promo_data['qualifying_amt'] = 0.00;
        $promo_data['qualifying_object_array'] = ['Item-'.$item_records[1]['item_id'],'Item-'.$item_records[3]['item_id']];
        //$promo_data['promo_item_1_array'] = 'pitem000000200,sumdumPromoItemId200';
        $promo_data['promo_item_1_array'] = ['Item-'.$item_records[2]['item_id'],'Item-'.$item_records[4]['item_id']];
        $promo_data['menu_id'] = $menu_id;
        $promo_data['brand_id'] = $skin_resource->brand_id;

        $request = createRequestObject("/app2/admin/promotype1",'POST',json_encode($promo_data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->createPromo();
        $ids['promo_type_2_id'] = $response->promo_id;
        $ids['promo_merchant_map_ids'] = $response->merchant_id_maps;


        //create the type 1 promo
        $promo_data = [];
        $promo_data['key_word'] = "The Type1 Promo,type1promo,sumdumkeyword";
        $promo_data['promo_type'] = 1;
        $promo_data['description'] = 'Get 25% off';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['allow_multiple_use_per_order'] = false;
        $promo_data['valid_on_first_order_only'] = 'N';
        $promo_data['order_type'] = 'pickup';
        $promo_data['merchant_id'] = $merchant_id;
        $promo_data['message1'] = "Congratulations! You're getting a 25% off your order!";
        $promo_data['qualifying_amt'] = 1.00;
        $promo_data['promo_amt'] = 0.00;
        $promo_data['percent_off'] = 25;
        $promo_data['max_amt_off'] = 50.00;
        $promo_data['menu_id'] = $menu_id;
        $promo_data['brand_id'] = $skin_resource->brand_id;;

        $request = createRequestObject("/app2/admin/promotype1",'POST',json_encode($promo_data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->createPromo();

        $ids['promo_id_type_1'] = $response->promo_id;

        $pkwm_adapter = new PromoKeyWordMapAdapter(getM());
        $promo_adapter = new PromoAdapter(getM());

		// create another promo on a differnt brand with same key word
		$menu_id2 = createTestMenuWithNnumberOfItems(1);
		$merchant_resource2 = createNewTestMerchant($menu_id2);
		$merchant_resource2->brand_id = 282;
		$merchant_resource2->save();

        $merchant_payment_type_map_resource = MerchantPaymentTypeMapsAdapter::createMerchantPaymentTypeMap($merchant_resource2->merchant_id, 2000, $billing_entity_resource->id);


        $merchant_id2 = $merchant_resource2->merchant_id;
		$ids['merchant_id2'] = $merchant_id2;
    	
    	//create the type 1 promo
    	$duplicate_promo_key_word = "AlternateKeyWord";

        $promo_data = [];
        $promo_data['promo_id'] = 202;
        $promo_data['key_word'] = "The Type1 Promo,type1promo,$duplicate_promo_key_word";
        $promo_data['promo_type'] = 1;
        $promo_data['description'] = 'Get $10 off';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['allow_multiple_use_per_order'] = false;
        $promo_data['valid_on_first_order_only'] = 'N';
        $promo_data['order_type'] = 'pickup';
        $promo_data['merchant_id'] = $merchant_id2;
        $promo_data['message1'] = "Congratulations! You're getting $%%amt%% off your order!";
        $promo_data['qualifying_amt'] = 1.00;
        $promo_data['promo_amt'] = 10.00;
        $promo_data['percent_off'] = 0;
        $promo_data['max_amt_off'] = 50.00;
        $promo_data['menu_id'] = $menu_id;
        $promo_data['brand_id'] = 282;

        $request = createRequestObject("/app2/admin/promotype1",'POST',json_encode($promo_data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->createPromo();

        $ids['duplicate_promo_key_word'] = $duplicate_promo_key_word;


        //create the type 4 promo
        $promo_data = [];
        $promo_data['key_word'] = 'Test Promo Four';
        $promo_data['promo_type'] = 4;
        $promo_data['description'] = 'Get $ off your item';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['merchant_id'] = $merchant_id;
        $promo_data['message5'] = "Here's the deal, order a large this, and you'll get a discount! Limit 1";
        $promo_data['qualifying_object_array'] = ['Item-'.$item_records[1]['item_id']];
        $promo_data['fixed_amount_off'] = 1.00;
        $promo_data['percent_off'] = 40;
        $promo_data['fixed_price'] = .75;
        $promo_data['menu_id'] = $menu_id;
        $promo_data['brand_id'] = 300;

        $request = createRequestObject("/app2/admin/promotype1",'POST',json_encode($promo_data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->createPromo();

        $promo_id = $response->promo_id;
        $ids['promo_id_type_4'] = $promo_id;

        // create the type5 promo
        $promo_data = [];
        $promo_data['key_word'] = 'Test Promo Five';
        $promo_data['promo_type'] = 5;
        $promo_data['description'] = 'Get $ off when ordered together';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2020-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['merchant_id'] = $merchant_id;
        $promo_data['message5'] = "Here's the deal, order a this and a that, and you'll get a discount!";
        //$promo_data['qualifying_object'] = 'qitem000000200,sumdumExternalId200';
        $promo_data['qualifying_object_array'] = ['Entre','Entre','Entre'];
        $promo_data['fixed_amount_off'] = 1.00;
        $promo_data['percent_off'] = 40;
        $promo_data['fixed_price'] = .75;
        $promo_data['menu_id'] = $menu_id;
        $promo_data['brand_id'] = 300;

        $request = createRequestObject("/app2/admin/promotype1",'POST',json_encode($promo_data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response_five = $promo_controller->createPromo();
        $ids['promo_id_type_5'] = $response_five->promo_id;

        // add a key word for a different brand
        $type5_promo_key_word = "type_five";
        $ids['duplicate_promo_key_word'] = $duplicate_promo_key_word;
        $pkwm_adapter = new PromoKeyWordMapAdapter($mimetypes);
        Resource::createByData($pkwm_adapter, array("promo_id"=>$response_five->promo_id,"promo_key_word"=>"$type5_promo_key_word","brand_id"=>282));
        $ids['promo_type_5_key_word'] = $type5_promo_key_word;


        //assign promo ids
//    	$item1_resource = SplickitController::getResourceFromId($item_records[1]['item_id'], "Item");
//    	$item1_resource->promo_tag = "qitem000000200";
//    	$item1_resource->save();
//		$item2_resource = SplickitController::getResourceFromId($item_records[2]['item_id'], "Item");
//		$item2_resource->promo_tag = "pitem000000200";
//		$item2_resource->save();
//		$item3_resource = SplickitController::getResourceFromId($item_records[3]['item_id'], "Item");
//		$item3_resource->promo_tag = "sumdumExternalId200";
//		$item3_resource->save();
//		$item4_resource = SplickitController::getResourceFromId($item_records[4]['item_id'], "Item");
//		$item4_resource->promo_tag = "sumdumPromoItemId200";
//		$item4_resource->save();

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
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    PortalPromoTest::main();
}

?>