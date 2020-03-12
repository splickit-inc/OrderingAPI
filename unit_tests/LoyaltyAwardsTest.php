<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class LoyaltyAwardsTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        setContext($this->ids['context']);

    }

    function tearDown()
    {
        //delete your instance
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->stamp);
        unset($this->ids);
    }

    function testMinimumAwardFixed()
    {

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);

        $user_id = $user_resource->user_id;
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours',10);
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000);


        $ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $this->assertTrue($order_resource->order_amt > 1.00);
        $first_order_amt = $order_resource->order_amt;
        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals(round(10*$first_order_amt)+10,$ubpm_record['points'],"Shouljd have points");
        $this->assertEquals(.10+($order_resource->order_amt)/10,$ubpm_record['dollar_balance'],'should have a dollar value equal to 10% of what was spent + 10 points for the award.');

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$order_id));
        $this->assertCount(2,$ublh_records,"There should be 2 records for this order");

        $hash = createHashmapFromArrayOfArraysByFieldName($ublh_records,'process');
        $this->assertEquals($ubpm_record['points']-10,$hash['Order']['points_added'],'It should have the points earned');
        $this->assertEquals($ubpm_record['points']-10,$hash['Order']['current_points']);
        $this->assertEquals($ubpm_record['dollar_balance']-.10,$hash['Order']['current_dollar_balance']);

        $this->assertEquals(10,$hash['Order Minimum Award']['points_added'],'It should have the points awarded');
        $this->assertEquals($ubpm_record['points'],$hash['Order Minimum Award']['current_points']);
        $this->assertEquals($ubpm_record['dollar_balance'],$hash['Order Minimum Award']['current_dollar_balance']);
    }

    function testMinimumAwardPercent()
    {
        $adapter = new LoyaltyBrandBehaviorAwardAmountMapsAdapter($m);
        $ids = $this->ids;
        $res = Resource::find($adapter,''.$this->ids['brand_loyalty_behavior_award_map_resource']->id,$o);
        $res->process_type = 'percent';
        $res->value = "20";
        $res->save();


        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);

        $user_id = $user_resource->user_id;
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours',10);
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000);

        $expected_increase = 30;

        $ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $this->assertTrue($order_resource->order_amt > 1.00);
        $first_order_amt = $order_resource->order_amt;
        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals(round(10*$first_order_amt)+$expected_increase,$ubpm_record['points'],"Shouljd have points");
        $this->assertEquals($expected_increase/100+($order_resource->order_amt)/10,$ubpm_record['dollar_balance'],'should have a dollar value equal to 10% of what was spent + 10 points for the award.');

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$order_id));
        $this->assertCount(2,$ublh_records,"There should be 2 records for this order");

        $hash = createHashmapFromArrayOfArraysByFieldName($ublh_records,'process');
        $this->assertEquals($ubpm_record['points']-$expected_increase,$hash['Order']['points_added'],'It should have the points earned');
        $this->assertEquals($ubpm_record['points']-$expected_increase,$hash['Order']['current_points']);
        $this->assertEquals($ubpm_record['dollar_balance']-$expected_increase/100,$hash['Order']['current_dollar_balance']);

        $this->assertEquals($expected_increase,$hash['Order Minimum Award']['points_added'],'It should have the points awarded');
        $this->assertEquals($ubpm_record['points'],$hash['Order Minimum Award']['current_points']);
        $this->assertEquals($ubpm_record['dollar_balance'],$hash['Order Minimum Award']['current_dollar_balance']);
    }

    function testMultipleAwardsForOneOrder()
    {
        $adapter = new LoyaltyBrandBehaviorAwardAmountMapsAdapter($m);
        $res = Resource::find($adapter,''.$this->ids['brand_loyalty_behavior_award_map_resource']->id,$o);
        $res->process_type = 'fixed';
        $res->value = "15";
        $res->save();

        $award_type_data = array();
        $award_type_data['brand_id'] = getBrandIdFromCurrentContext();
        $award_type_data['loyalty_award_trigger_type_id'] = 1001;
        $award_type_data['trigger_value'] = 'R';
        $resource = Resource::createByData(new LoyaltyAwardBrandTriggerAmountsAdapter($m),$award_type_data);

        $brand_award_map_data = array();
        $brand_award_map_data['brand_id'] = getBrandIdFromCurrentContext();
        $brand_award_map_data['loyalty_award_brand_trigger_amounts_id'] = $resource->id;
        $brand_award_map_data['process_type'] = 'fixed';
        $brand_award_map_data['value'] = 30;
        $resource2 = Resource::createByData(new LoyaltyBrandBehaviorAwardAmountMapsAdapter($m),$brand_award_map_data);


        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);

        $user_id = $user_resource->user_id;
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours',10);
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000);

        $expected_increase = 45;

        $ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $this->assertTrue($order_resource->order_amt > 1.00);
        $first_order_amt = $order_resource->order_amt;
        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals(round(10*$first_order_amt)+$expected_increase,$ubpm_record['points'],"Shouljd have points");
        $this->assertEquals($expected_increase/100+($order_resource->order_amt)/10,$ubpm_record['dollar_balance'],'should have a dollar value equal to 10% of what was spent + 45 points for the awards.');

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$order_id));
        $this->assertCount(3,$ublh_records,"There should be 3 records for this order");

        $hash = createHashmapFromArrayOfArraysByFieldName($ublh_records,'process');
        $this->assertEquals($ubpm_record['points']-$expected_increase,$hash['Order']['points_added'],'It should have the points earned');
        $this->assertEquals(15,$hash['Order Minimum Award']['points_added'],'It should have the points awarded');
        $this->assertEquals(30,$hash['Order Type Award']['points_added'],'It should have the points awarded');
    }

    function testDayPartAwardForDay()
    {
        $bata = new LoyaltyAwardBrandTriggerAmountsAdapter($m);
        $sql = "DELETE FROM Loyalty_Brand_Behavior_Award_Amount_Maps";
        $bata->_query($sql);
        $sql = "DELETE FROM Loyalty_Award_Brand_Trigger_Amounts";
        $bata->_query($sql);

        $award_type_data = array();
        $award_type_data['brand_id'] = getBrandIdFromCurrentContext();
        $award_type_data['loyalty_award_trigger_type_id'] = 1002;
        foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day) {
            $award_type_data['trigger_value'] = $day;
            $resource = Resource::createByData($bata,$award_type_data);

            $label = "Double Point $day";
            $brand_award_map_data = array();
            $brand_award_map_data['brand_id'] = getBrandIdFromCurrentContext();
            $brand_award_map_data['loyalty_award_brand_trigger_amounts_id'] = $resource->id;
            $brand_award_map_data['process_type'] = 'multiplier';
            $brand_award_map_data['value'] = 2;
            $brand_award_map_data['history_label'] = $label;
            $resource2 = Resource::createByData(new LoyaltyBrandBehaviorAwardAmountMapsAdapter($m),$brand_award_map_data);
        }

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);

        $user_id = $user_resource->user_id;
//        $order_adapter = new OrderAdapter($mimetypes);
//        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'skip hours',1);
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id']);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,time());
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$usesr,$this->ids['merchant_id'],0.00,time());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000);

        $expected_increase = 15;

        $ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $this->assertTrue($order_resource->order_amt > 1.00);
        $first_order_amt = $order_resource->order_amt;
        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals(round(10*$first_order_amt)+$expected_increase,$ubpm_record['points'],"Shouljd have points");
        $this->assertEquals($expected_increase/100+($order_resource->order_amt)/10,$ubpm_record['dollar_balance'],'should have a dollar value equal to 10% of what was spent + 10 points for the award.');

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$order_id));
        $this->assertCount(2,$ublh_records,"There should be 2 records for this order");

        $hash = createHashmapFromArrayOfArraysByFieldName($ublh_records,'process');
        $this->assertEquals($ubpm_record['points']-$expected_increase,$hash['Order']['points_added'],'It should have the points earned');
        $this->assertEquals($ubpm_record['points']-$expected_increase,$hash['Order']['current_points']);
        $this->assertEquals($ubpm_record['dollar_balance']-$expected_increase/100,$hash['Order']['current_dollar_balance']);

        $label = "Double Point ".date("l");
        $this->assertEquals($expected_increase,$hash[$label]['points_added'],'It should have the points awarded');
        $this->assertEquals($ubpm_record['points'],$hash[$label]['current_points']);
        $this->assertEquals($ubpm_record['dollar_balance'],$hash[$label]['current_dollar_balance']);

    }

    function testDayPartAwardForTimeIn()
    {
        $bata = new LoyaltyAwardBrandTriggerAmountsAdapter($m);
        $sql = "DELETE FROM Loyalty_Brand_Behavior_Award_Amount_Maps";
        $bata->_query($sql);
        $sql = "DELETE FROM Loyalty_Award_Brand_Trigger_Amounts";
        $bata->_query($sql);

        $award_type_data = array();
        $award_type_data['brand_id'] = getBrandIdFromCurrentContext();
        $award_type_data['loyalty_award_trigger_type_id'] = 1002;
        $award_type_data['trigger_value'] = "13:00-15:00";
        $resource = Resource::createByData($bata,$award_type_data);

        $brand_award_map_data = array();
        $brand_award_map_data['brand_id'] = getBrandIdFromCurrentContext();
        $brand_award_map_data['loyalty_award_brand_trigger_amounts_id'] = $resource->id;
        $brand_award_map_data['process_type'] = 'multiplier';
        $brand_award_map_data['value'] = 2;
        $resource2 = Resource::createByData(new LoyaltyBrandBehaviorAwardAmountMapsAdapter($m),$brand_award_map_data);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);

        $user_id = $user_resource->user_id;

        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'the note');

        $request = createRequestObject('/app2/apiv2/cart/checkout','post',json_encode($order_data),'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request,5);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver()+3600);
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$mercant_id,0.00,$time);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000);

        $expected_increase = 15;

        $ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $this->assertTrue($order_resource->order_amt > 1.00);
        $first_order_amt = $order_resource->order_amt;
        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals(round(10*$first_order_amt)+$expected_increase,$ubpm_record['points'],"Shouljd have points");
        $this->assertEquals($expected_increase/100+($order_resource->order_amt)/10,$ubpm_record['dollar_balance'],'should have a dollar value equal to 10% of what was spent + 10 points for the award.');

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$order_id));
        $this->assertCount(2,$ublh_records,"There should be 2 records for this order");

        $hash = createHashmapFromArrayOfArraysByFieldName($ublh_records,'process');
        $this->assertEquals($ubpm_record['points']-$expected_increase,$hash['Order']['points_added'],'It should have the points earned');
        $this->assertEquals($ubpm_record['points']-$expected_increase,$hash['Order']['current_points']);
        $this->assertEquals($ubpm_record['dollar_balance']-$expected_increase/100,$hash['Order']['current_dollar_balance']);

        $this->assertEquals($expected_increase,$hash['Order Day Part Award']['points_added'],'It should have the points awarded');
        $this->assertEquals($ubpm_record['points'],$hash['Order Day Part Award']['current_points']);
        $this->assertEquals($ubpm_record['dollar_balance'],$hash['Order Day Part Award']['current_dollar_balance']);

    }

    function testDayPartAwardForTimeOutSideOfRange()
    {
        $bata = new LoyaltyAwardBrandTriggerAmountsAdapter($m);
        $sql = "DELETE FROM Loyalty_Brand_Behavior_Award_Amount_Maps";
        $bata->_query($sql);
        $sql = "DELETE FROM Loyalty_Award_Brand_Trigger_Amounts";
        $bata->_query($sql);

        $award_type_data = array();
        $award_type_data['brand_id'] = getBrandIdFromCurrentContext();
        $award_type_data['loyalty_award_trigger_type_id'] = 1002;
        $award_type_data['trigger_value'] = "13:00-15:00";
        $resource = Resource::createByData($bata,$award_type_data);

        $brand_award_map_data = array();
        $brand_award_map_data['brand_id'] = getBrandIdFromCurrentContext();
        $brand_award_map_data['loyalty_award_brand_trigger_amounts_id'] = $resource->id;
        $brand_award_map_data['process_type'] = 'multiplier';
        $brand_award_map_data['value'] = 2;
        $resource2 = Resource::createByData(new LoyaltyBrandBehaviorAwardAmountMapsAdapter($m),$brand_award_map_data);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);

        $user_id = $user_resource->user_id;

        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->ids['merchant_id'], 'pickup', 'the note');

        $request = createRequestObject('/app2/apiv2/cart/checkout','post',json_encode($order_data),'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request,5);
        $place_order_controller->setCurrentTime(getTomorrowTwelveNoonTimeStampDenver());
        $checkout_resource = $place_order_controller->processV2Request();
        $this->assertNull($checkout_resource->error);

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$mercant_id,0.00,$time);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $this->assertTrue($order_id > 1000);

        $expected_increase = 0;

        $ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $this->assertTrue($order_resource->order_amt > 1.00);
        $first_order_amt = $order_resource->order_amt;
        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals(round(10*$first_order_amt)+$expected_increase,$ubpm_record['points'],"Shouljd have points");
        $this->assertEquals($expected_increase/100+($order_resource->order_amt)/10,$ubpm_record['dollar_balance'],'should have a dollar value equal to 10% of what was spent + 10 points for the award.');

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$order_id));
        $this->assertCount(1,$ublh_records,"There should be only 1 record for this order");

        $hash = createHashmapFromArrayOfArraysByFieldName($ublh_records,'process');
        $this->assertEquals($ubpm_record['points']-$expected_increase,$hash['Order']['points_added'],'It should have the points earned');
        $this->assertEquals($ubpm_record['points']-$expected_increase,$hash['Order']['current_points']);
        $this->assertEquals($ubpm_record['dollar_balance']-$expected_increase/100,$hash['Order']['current_dollar_balance']);

        $this->assertNull($hash['Order Day Part Award'],"It should not have an order day part record");
    }
    
    static function setUpBeforeClass()
    {
        ini_set('max_execution_time',300);

        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction();

        $_SERVER['request_time1'] = microtime(true);

        $skin_resource = getOrCreateSkinAndBrandIfNecessary("sandl", "sandlbrand", $skin_id, $brand_id);
        $brand_id = $skin_resource->brand_id;
        $brand_resource = Resource::find(new BrandAdapter($mimetypes),"$brand_id");
        $brand_resource->loyalty = 'Y';
        $brand_resource->save();

        $blr_data['brand_id'] = $brand_id;
        $blr_data['loyalty_type'] = 'splickit_earn';
        $brand_loyalty_rules_resource = Resource::factory(new BrandLoyaltyRulesAdapter($m),$blr_data);
        $brand_loyalty_rules_resource->save();
        $ids['blr_resource'] = $brand_loyalty_rules_resource->getRefreshedResource();
        setContext($skin_resource->external_identifier);
        $ids['context'] = $skin_resource->external_identifier;
        $menu_id = createTestMenuWithNnumberOfItems(5);
        $ids['menu_id'] = $menu_id;

        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_payment_type_map_adapter = new MerchantPaymentTypeMapsAdapter($m);
        $cash_merchant_payment_type_resource = $merchant_payment_type_map_adapter->createMerchantPaymentTypeMap($merchant_resource->merchant_id, 1000, $billing_entity_id);
        $merchant_id = $merchant_resource->merchant_id;
        $ids['merchant_id'] = $merchant_id;

        $award_type_data = array();
        $award_type_data['brand_id'] = getBrandIdFromCurrentContext();
        $award_type_data['loyalty_award_trigger_type_id'] = 1000;
        $award_type_data['trigger_value'] = '10.00';
        $resource = Resource::createByData(new LoyaltyAwardBrandTriggerAmountsAdapter($m),$award_type_data);
        $ids['behavior_award_type_resource'] = $resource;

        $brand_award_map_data = array();
        $brand_award_map_data['brand_id'] = getBrandIdFromCurrentContext();
        $brand_award_map_data['loyalty_award_brand_trigger_amounts_id'] = $resource->id;
        $brand_award_map_data['process_type'] = 'fixed';
        $brand_award_map_data['value'] = 10;
        $resource2 = Resource::createByData(new LoyaltyBrandBehaviorAwardAmountMapsAdapter($m),$brand_award_map_data);
        $ids['brand_loyalty_behavior_award_map_resource'] = $resource2;


        $_SERVER['log_level'] = 5;
        $_SERVER['unit_test_ids'] = $ids;

    }

    static function tearDownAfterClass()
    {
        SplickitCache::flushAll();         $db = DataBase::getInstance();
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
    LoyaltyAwardsTest::main();
}

?>