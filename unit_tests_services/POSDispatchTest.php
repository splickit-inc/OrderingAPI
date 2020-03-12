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

require_once 'lib/utilities/functions.inc';
require_once 'lib/utilities/unit_test_functions.inc';

class POSDispatchTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;
    var $info;
    var $api_port = "80";

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        setContext("com.splickit.vtwoapi");
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

    function testUpdateTipOnAuthorizedOrderFailOnCapture()
    {
        setContext("com.splickit.goodcentssubs");
        $merchant_id = $this->ids['auth_merchant_id'];
        $created_merchant_payment_type_map_id = $this->ids['auth_merchant_payment_type_map_id'];

        $user = logTestUserIn($this->ids['user_id']);
        $user_id = $user['user_id'];
        $balance_before = $user['balance'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
        $order_data['tip'] = 0.00;
        $order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertNull($order_resource->error);
        $this->assertTrue($order_resource->order_id > 1000);
        $original_order_grand_total = $order_resource->grand_total;
        $this->assertEquals('VioPaymentService', $order_resource->payment_service_used);
        $ucid = $order_resource->ucid;

        $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
        if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
            $balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
        }
        $this->assertCount(2, $balance_change_rows_by_user_id);
        $this->assertTrue(isset($balance_change_rows_by_user_id["$user_id-Authorize"]),"Should have found the authorize row");

        $_SERVER['SOAPAction'] = "ApplyTipToOrder";
        $soap_action = "http://www.xoikos.com/webservices/ApplyTipToOrder";
        $tip_amt = 1.00;

        $xml_body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/webservices/"><OrderID>'.$ucid.'</OrderID><Amount>'.$tip_amt.'</Amount></ApplyTipToOrder></soap:Body></soap:Envelope>';
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/xoikos";
        $headers = array("SOAPAction: $soap_action");

        $request = createRequestObject($url,'POST',$xml_body,'application/xml');
        $request->setHeaderVariable("SOAPAction",$soap_action);
        $request->parseSoapRequest();

        $pos_controller = new PosController(getM(),$user,$request);
        $_SERVER['TEST_GATEWAYRESET'] = 'true';
        $response = $pos_controller->processV2request();
        unset($_SERVER['TEST_GATEWAYRESET']);

        $this->assertEquals("The remote server reset the connection. Please try again.",$response->error);

        $order = new Order($order_id);
        $this->assertEquals($order_resource->grand_total, $order->get('grand_total'));
        $this->assertEquals(0.00,$order->get('tip_amount'));

        $response2 = $pos_controller->processV2request();
        $this->assertNull($response2->error);

        $order = new Order($order_id);
        $this->assertEquals($order->get('grand_total'), $order_resource->grand_total+$tip_amt);
        $this->assertEquals($tip_amt,$order->get('tip_amt'));




        //$response = $this->makePOSXMLRequest($url,"POST",$xml_body,$headers);

        $expected_response = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrderResponse xmlns="http://www.xoikos.com/webservices/"><ApplyTipToOrderResult><Success>true</Success><ErrorMessage>A 1 tip has been applied on Order with ID '.$ucid.'</ErrorMessage></ApplyTipToOrderResult></ApplyTipToOrderResponse></soap:Body></soap:Envelope>';

        $this->assertEquals($expected_response,$response2->soap_body,"should have responded with the soap body");
        $this->assertEquals(200,$response2->http_code);

        // check balance change records
        $balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),"BalanceChangeAdapter");
        $this->assertCount(3,$balance_change_records,"There should have been 4 balance change records");
    }


    function testVivonetStageImportForSodexo()
    {
        setContext('com.splickit.snarfs');
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $alpha = $merchant_resource->alphanumeric_id;
        $store_id = rand(111111,999999);
        $mvima_data = ['merchant_id'=>$merchant_resource->merchant_id,'store_id'=>$store_id,'merchant_key'=>'736de47f4cdee9e8fe9ab8a0bc0eb37e'];
        $mvima = new MerchantVivonetInfoMapsAdapter(getM());
        $mvim_resource = Resource::createByData($mvima,$mvima_data);
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/import/vivonet/$alpha/100mainstreet";

        //$user = logTestUserIn(1);
        clearAuthenticatedUserParametersForSession();
//        removeContext();
//        $request = createRequestObject("$url",'POST');
//        $pos_controller = new PosController(getM(),null,$request,5);
//        $resource = $pos_controller->processV2request();

        $response = $this->makeRequest($url,null,'POST','');
        $this->assertEquals(200,$this->info['http_code']);
        $response_array = json_decode($response,true);
        $this->assertContains('The import has been staged',$response_array['data']['message']);

        //validate that import was staged correctly
        $activity_id = $response_array['data']['activity_id'];
        $activity_resource = Resource::find(new ActivityHistoryAdapter(getM()),"$activity_id");
        $info = $activity_resource->info;
        $expected_info = "object=VivonetImporter;method=import;thefunctiondatastring=$alpha";
        $this->assertEquals($expected_info,$info,"it shoujdl have the url with the alpha numeric");
    }


//    function testPitaPitImportThoughPOSEndpoint()
//    {
//        setContext('com.splickit.pitapit');
//
//        $menu_id = 102774;
//        $brand_id = 282;
//        $skin_id = 13;
//        $menu_adapter = new MenuAdapter(getM());
//        if ($menu_resource = Resource::find($menu_adapter,"$menu_id")){
//            ; // all is good
//        } else {
//            //create menu
//            $menu_adapter->importMenu($menu_id,'prod','local',$brand_id);
////            $menu_resource = Resource::find($menu_adapter,"$menu_id");
////            $menu_resource->version = 2.0;
////            $menu_resource->save();
//        }
//        $merchant_resource = getOrCreateNewTestMerchantBasedOnExternalId('Lab',$menu_id);
//        Resource::createByData(new MerchantBrinkInfoMapsAdapter(getM()),array("merchant_id"=>$merchant_resource->merchant_id,"brink_location_token"=>"4Uo9rh1ZOUKambW059BLoA=="));
//        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/import/brinkpitapit/Lab";
//        $importer = ImporterFactory::getImporterFromUrl($url);
//        $this->assertEquals('BrinkpitapitImporter',get_class($importer));
//
//        $importer->importRemoteMerchantMetaDataForLoadedMerchant();
//        $message = $importer->getMessage();
//
//        $response = $this->makeRequest($url,'admin:welcome','POST','');
//        $this->assertEquals(200,$this->info['http_code']);
//        $response_array = json_decode($response,true);
//        $this->assertContains('The import has been completed',$response_array['data']['message']);
//
//    }


    function addToGroupOrder($group_order_record,$user_resource)
    {
        $group_order_token = $group_order_record['group_order_token'];
        $group_order_id = $group_order_record['group_order_id'];
        $user = logTestUserResourceIn($user_resource);

        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($group_order_record['merchant_id']);
        $order_data['group_order_token'] = $group_order_token;

        $checkout_resource = getCheckoutResourceFromOrderData($order_data,time());
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,time());
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;

        $goioma = new GroupOrderIndividualOrderMapsAdapter(getM());
        $record = $goioma->getRecord(array("group_order_id"=>$group_order_record['group_order_id'],"user_order_id"=>$order_id));
        $this->assertEquals('Submitted',$record['status']);


    }

    function testRemoteCancelOfSubmittedGroupOrder()
    {
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_resource->group_ordering_on = 1;
        $merchant_resource->save();
        $merchant_id = $merchant_resource->merchant_id;

        $user_resource1 = createNewUserWithCCNoCVV();
        $user_resource2 = createNewUserWithCCNoCVV();
        $user_resource3 = createNewUserWithCCNoCVV();

        $note = "sum dum note";
        $emails = "sumdumemail1@dummy.com,sumdumemail2@dummy.com,sumdumemail3@dummy.com,sumdumemail4@dummy.com";
        $user = logTestUserResourceIn($user_resource1);

        $request = new Request();
        $request->data = array("merchant_id" => $merchant_id, "notes" => $note, "participant_emails" => $emails, "group_order_type" => 2, "submit_at_ts" => (getTomorrowTwelveNoonTimeStampDenver() + 900));
        $request->url = "app2/apiv2/grouporders";
        $request->method = "POST";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $group_order_controller->current_time = getTomorrowTwelveNoonTimeStampDenver();
        $resource = $group_order_controller->processV2Request();
        $this->assertNull($resource->error);
        $this->assertNotNull($resource);

        $this->assertNotNull($resource->group_order_token);
        $this->assertTrue($resource->group_order_id > 1000, "shouljd have a group order id");
        $this->assertEquals($user['user_id'], $resource->admin_user_id);
        $this->assertTrue($resource->expires_at > (time() + (47 * 60 * 60)), "Should have an expiration timestamp that is greater then 47 hours from now");
        $this->assertTrue($resource->expires_at < (time() + (49 * 60 * 60)), "Should have an expiration timestamp that is less then 49 hours from now");
        $group_order_adapter = new GroupOrderAdapter(getM());
        $group_order_record = $group_order_adapter->getRecordFromPrimaryKey($resource->group_order_id);
        $this->assertEquals($note, $group_order_record['notes']);
        $this->assertEquals(2, $group_order_record['group_order_type']);
        $this->assertEquals('Pickup', $group_order_record['merchant_menu_type']);
        $this->assertEquals($emails, $group_order_record['participant_emails']);
        $base_order_data = OrderAdapter::staticGetRecordByPrimaryKey($resource->order_id, 'OrderAdapter');
        $this->assertEquals('R', $base_order_data['order_type']);
        $this->assertEquals('G', $base_order_data['status'],"Should have a status of G");
        $this->assertEquals(date('Y-m-d H:i:s',getTomorrowTwelveNoonTimeStampDenver()+3600),$base_order_data['pickup_dt_tm'],"pickup time should be set as 1pm");

        // now check the activity
        $activity_id = $resource->auto_send_activity_id;
        $this->assertTrue($activity_id>1000,"should have found a valid activity id");

        $activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
        $activity_resource = Resource::find($activity_history_adapter,"$activity_id");
        $this->assertNotNull($activity_resource);
        $group_order_record['activity_id'] = $activity_id;
        $group_order_record['order_id'] = $resource->order_id;

        $this->addToGroupOrder($group_order_record,$user_resource1);
        $this->addToGroupOrder($group_order_record,$user_resource2);
        $this->addToGroupOrder($group_order_record,$user_resource3);

        // now submit the group order
        $group_order_activity_id = $group_order_record['activity_id'];
        $send_group_order_activity = SplickitActivity::findActivityResourceAndReturnActivityObjectByActivityId($group_order_activity_id);
        $this->assertNotNull($send_group_order_activity);
        $class_name = get_class($send_group_order_activity);
        $this->assertEquals("SendGroupOrderActivity", $class_name);
        $this->assertTrue($send_group_order_activity->doit());
        $send_group_order_activity->markActivityExecuted();

        $group_order_base = CompleteOrder::getBaseOrderData($resource->order_id,getM());
        $mmha = new MerchantMessageHistoryAdapter(getM());

        $message_options[TONIC_FIND_BY_METADATA] = array("merchant_id"=>$merchant_id,"order_id"=>$resource->order_id,"message_type"=>'X');
        $message_resource = Resource::find($mmha,null,$message_options);
        $message_controller = ControllerFactory::generateFromMessageResource($message_resource);
        $this->assertTrue($message_controller->sendThisMessage($message_resource),"it should have sent the message");


        // ok!  now lets cancel it
        $ucid = $resource->ucid;

        // to test from standard api
//        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/orders/$ucid";
//        $response = $this->makeRequest($url,null,'DELETE');

        // to test controller function
//        $request = createRequestObject('/app2/pos/orders/'.$ucid,'delete');
//        $pos_controller = new PosController(getM(),null,$request,5);
//        $response = $pos_controller->processV2request();




        $_SERVER['SOAPAction'] = "CancelOrder";
        $soap_action = "http://www.xoikos.com/webservices/CancelOrder";
        $xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CancelOrder xmlns="http://www.xoikos.com/webservices/"><OrderID>'.$ucid.'</OrderID></CancelOrder></soap:Body></soap:Envelope>';
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/xoikos";
        $headers = array("SOAPAction: $soap_action");
        $response = $this->makePOSXMLRequest($url,"POST",$xml,$headers);

        $expected_response = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CancelOrderResponse xmlns="http://www.xoikos.com/webservices/"><CancelOrderResult><Success>true</Success><ErrorMessage>Group Order with ID '.$ucid.' has been canceled.</ErrorMessage></CancelOrderResult></CancelOrderResponse></soap:Body></soap:Envelope>';

        $this->assertEquals(200,$this->info['http_code']);

        $order = CompleteOrder::getBaseOrderData($resource->order_id);
        $this->assertEquals('C',$order['status'],"Status should have been set to 'C' since order was in an 'O' state");

        // check to see if order was refunded.
        $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
        $goioma = new GroupOrderIndividualOrderMapsAdapter(getM());
        $records = $goioma->getRecords(array("group_order_id"=>$group_order_record['group_order_id']));

        foreach ($records as $map_record) {
            $balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$map_record['user_order_id']));
            $this->assertCount(3,$balance_change_records);
            $bcr_hash = createHashmapFromArrayOfArraysByFieldName($balance_change_records,'process');
            $this->assertNotNull($bcr_hash['CCvoid'],'Could not find the CC void record for order_id: '.$map_record['user_order_id']);
            $this->assertEquals('Issuing a VioPaymentService VOID from the API: Group Order Cancelled',$bcr_hash['CCvoid']['notes']);
        }

        $this->assertEquals($expected_response,$response,"should have responded with the expected soap body");

    }

    function testInStoreRedemtionWithBlacklistedUser()
    {
        setContext('com.splickit.goodcentssubs');
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);

        $ubpma = new UserBrandPointsMapAdapter(getM());
        $ubpmr = Resource::find($ubpma,null,array(3=>array("user_id"=>$user['user_id'])));
        $ubpmr->dollar_balance = 10.00;
        $ubpmr->points = 1000;
        $ubpmr->save();

        $user_resource->flags = 'X000000001';
        $user_resource->save();


        clearAuthenticatedUserParametersForSession();
        $phone_number = cleanAllNonNumericCharactersFromString($user_resource->contact_no);
        $data['redemption_amount'] = 5.55;
        $remote_order_number = rand(111111,999999);
        $data['order_number'] = $remote_order_number;
        $data['phone_number'] = $phone_number;
        $data['location_id'] = '88888';

        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/loyalty/$phone_number";

//        $request = createRequestObject($url,'post',json_encode($data),'application/json');
//        $pos_controller = new PosController(getM(),null,$request);
//        $response = $pos_controller->processV2request();



        $response = $this->makeRequest($url,$up,'POST',$data);
        $this->assertEquals(422,$this->info['http_code']);

        $response_as_array = json_decode($response,true);
        $this->assertEquals(LoyaltyController::USER_IS_RESTRICTED_ERROR,$response_as_array['error']['error']);
        $this->assertEquals(LoyaltyController::USER_IS_RESTRICTED_ERROR_CODE,$response_as_array['error']['error_code']);

    }


    function testInStoreLoyaltyRdemptionWithExistingUserNotEnoughBalance()
    {
        setContext('com.splickit.goodcentssubs');
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);

        $ubpma = new UserBrandPointsMapAdapter(getM());
        $ubpmr = Resource::find($ubpma,null,array(3=>array("user_id"=>$user['user_id'])));
        $ubpmr->dollar_balance = 3.00;
        $ubpmr->points = 300;
        $ubpmr->save();

        clearAuthenticatedUserParametersForSession();
        $data['redemption_amount'] = 5.55;
        $remote_order_number = rand(111111,999999);
        $data['order_number'] = $remote_order_number;
        $data['phone_number'] = cleanAllNonNumericCharactersFromString($user_resource->contact_no);
        $data['location_id'] = '88888';
        $phone_number = cleanAllNonNumericCharactersFromString($user_resource->contact_no);
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/loyalty/$phone_number";

//        $request = createRequestObject($url,'post',json_encode($data),'application/json');
//        setContext('com.splickit.goodcentssubs');
//        $pos_controller = new PosController(getM(),null,$request);
//        $response = $pos_controller->processV2request();



        $response = $this->makeRequest($url,$up,'POST',$data);
        $this->assertEquals(422,$this->info['http_code']);

        $response_as_array = json_decode($response,true);
        $this->assertEquals(LoyaltyController::REDEMPTION_AMOUNT_GREATER_THEN_BALANCE_ERROR,$response_as_array['error']['error']);
        $this->assertEquals(LoyaltyController::REDEMPTION_AMOUNT_GREATER_THEN_BALANCE_ERROR_CODE,$response_as_array['error']['error_code']);

    }


    function testInStoreLoyaltyRdemptionWithExistingUser()
    {
        setContext('com.splickit.goodcentssubs');
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);

        $ubpma = new UserBrandPointsMapAdapter(getM());
        $ubpmr = Resource::find($ubpma,null,array(3=>array("user_id"=>$user['user_id'])));
        $ubpmr->dollar_balance = 10.00;
        $ubpmr->points = 1000;
        $ubpmr->save();

        clearAuthenticatedUserParametersForSession();
        $data['redemption_amount'] = 5.55;
        $remote_order_number = rand(111111,999999);
        $data['order_number'] = $remote_order_number;
        $data['phone_number'] = cleanAllNonNumericCharactersFromString($user_resource->contact_no);
        $data['location_id'] = '88888';
        $phone_number = cleanAllNonNumericCharactersFromString($user_resource->contact_no);
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/loyalty/$phone_number";

//        $request = createRequestObject($url,'post',json_encode($data),'application/json');
//        setContext('com.splickit.goodcentssubs');
//        $pos_controller = new PosController(getM(),null,$request);
//        $response = $pos_controller->processV2request();



        $response = $this->makeRequest($url,$up,'POST',$data);
        $this->assertEquals(200,$this->info['http_code']);

        //check to see if loyalty was updated
        $ubrm_record = UserBrandPointsMapAdapter::staticGetRecord(array("user_id"=>$user_resource->user_id),'UserBrandPointsMapAdapter');
        $this->assertEquals(445,$ubrm_record['points'],"it shoujld have recorded the points");
        $this->assertEquals(4.45,$ubrm_record['dollar_balance'],"it shoujld have recorded the points");

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$remote_order_number));
        $this->assertCount(1,$ublh_records,"There should be 1 record for this order");
        $this->assertEquals(LoyaltyController::IN_STORE_REDEMPTION_LABEL,$ublh_records[0]['process']);
        $this->assertEquals('location 88888',$ublh_records[0]['notes']);
        $this->assertEquals(555,$ublh_records[0]['points_redeemed']);
        $expected_action_date = date('Y-m-d');
        $this->assertEquals($expected_action_date,$ublh_records[0]['action_date'],"should have today's date as action date");
        $data['user'] = $user;
        return $data;
    }

    /**
     * @depends testInStoreLoyaltyRdemptionWithExistingUser
     */
    function testCancelRedmption($data)
    {
        setContext('com.splickit.goodcentssubs');
        $user = $data['user'];
        unset($data['user']);
        unset($data['redemption_amount']);
        $phone_number = $data['phone_number'];
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/loyalty/$phone_number/cancel";

//        $request = createRequestObject($url,'post',json_encode($data),'application/json');
//        clearAuthenticatedUserParametersForSession();
//        $pos_controller = new PosController(getM(),null,$request);
//        $response = $pos_controller->processV2request();
//        $this->assertEquals('true',$response->success);
//
//        $pos_controller = new PosController(getM(),null,$request);
//        $response = $pos_controller->processV2request();
//        $this->assertEquals(422,$response->http_code);
//        $this->assertEquals(LoyaltyController::ORDER_ALREADY_CANCELLED_ERROR,$response->error);


        $response = $this->makeRequest($url,null,'post',$data);
        $this->assertEquals(200,$this->info['http_code']);

        $response = $this->makeRequest($url,null,'post',$data);
        $this->assertEquals(422,$this->info['http_code']);
        $this->assertContains(LoyaltyController::ORDER_ALREADY_CANCELLED_ERROR,$response);


        //check to see if loyalty was updated
        $ubrm_record = UserBrandPointsMapAdapter::staticGetRecord(array("user_id"=>$user['user_id']),'UserBrandPointsMapAdapter');
        $this->assertEquals(1000,$ubrm_record['points'],"it shoujld have recorded the points");
        $this->assertEquals(10.00,$ubrm_record['dollar_balance'],"it shoujld have recorded the points");

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter(getM());
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$data['order_number']));
        $this->assertCount(2,$ublh_records,"There should be 2 records for this order");
        $this->assertEquals(LoyaltyController::IN_STORE_REDEMPTION_LABEL,$ublh_records[0]['process']);
        $this->assertEquals('location 88888',$ublh_records[0]['notes']);
        $this->assertEquals(555,$ublh_records[0]['points_redeemed']);
        $expected_action_date = date('Y-m-d');
        $this->assertEquals($expected_action_date,$ublh_records[0]['action_date'],"should have today's date as action date");

        $this->assertEquals(LoyaltyController::IN_STORE_CANCELLED_LABEL,$ublh_records[1]['process']);
        $this->assertEquals('location 88888',$ublh_records[1]['notes']);
        $this->assertEquals(-555,$ublh_records[1]['points_redeemed']);
        $expected_action_date = date('Y-m-d');
        $this->assertEquals($expected_action_date,$ublh_records[1]['action_date'],"should have today's date as action date");



    }

    function testBadUrl()
    {

        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/loyalty/0000000000";
        $response = $this->makePOSXMLRequest($url,'POST');
        $this->assertEquals('',$response,"we should get nothing back");
    }

//    function testBrinkBiBiBopImportThroughPOSEndpoint()
//    {
//        setContext('com.splickit.bibibop');
//        $merchant_resource = getOrCreateNewTestMerchantBasedOnExternalId('Lab');
//        Resource::createByData(new MerchantBrinkInfoMapsAdapter(getM()),array("merchant_id"=>$merchant_resource->merchant_id,"brink_location_token"=>"ppgH7PTz9kmac3W0xsp9MQ=="));
//        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/import/brinkbibibop/Lab";
//
////        $user = logTestUserIn(1);
////        $request = createRequestObject("/apiv2/pos/import/brinkbibibop/Lab",'POST');
////        $pos_controller = new PosController(getM(),$user,$request,5);
////        $resource = $pos_controller->processV2request();
//
//        $response = $this->makeRequest($url,null,'POST','');
//        $this->assertEquals(200,$this->info['http_code']);
//        $response_array = json_decode($response,true);
//        $this->assertContains('The import has been staged',$response_array['data']['message']);
//
//        $response = $this->makeRequest($url,'admin:welcome','POST','');
//        $this->assertEquals(200,$this->info['http_code']);
//        $response_array = json_decode($response,true);
//        $this->assertContains('The import has been completed',$response_array['data']['message']);
//    }

    function testInStoreLoyaltyEndpointBadData()
    {
        setContext('com.splickit.goodcentssubs');
        $user_resource = createNewUserWithCCNoCVV();
        clearAuthenticatedUserParametersForSession();
        $data = array("bad_field"=>"bum");
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/loyalty";
        $response = $this->makeRequest($url,$up,'POST',$data);
        $this->assertEquals(422,$this->info['http_code'],"It should have gotten back an unprocessable entity because no data was submitted");
    }

    function testInStoreLoyaltyWithNewFlagButLoyaltyNumberExists()
    {
        setContext('com.splickit.goodcentssubs');
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);

        clearAuthenticatedUserParametersForSession();
        $data['order_amount'] = 23.77;
        $remote_order_number = rand(111111,999999);
        $data['order_number'] = $remote_order_number;
        $data['phone_number'] = $user_resource->contact_no;
        $data['new_user'] = true;
        $phone_number = cleanAllNonNumericCharactersFromString($user_resource->contact_no);
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/loyalty/$phone_number";
        $response = $this->makeRequest($url,$up,'POST',$data);
        $this->assertEquals(422,$this->info['http_code']);
        $response_as_array = json_decode($response,true);
        $first_name = $user_resource->first_name;
        $last_name = $user_resource->last_name;
        $this->assertEquals(LoyaltyController::LOYALTY_NUMBER_EXISTS_FOR_REMOTE_JOIN_ERROR."$first_name $last_name",$response_as_array['error']['error']);
        $this->assertEquals(LoyaltyController::LOYALTY_NUMBER_EXISTS_FOR_REMOTE_JOIN_ERROR_CODE,$response_as_array['error']['error_code']);
    }



    function testInStoreLoyaltyEndpointWithExistingUser()
    {
        setContext('com.splickit.goodcentssubs');
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);

        clearAuthenticatedUserParametersForSession();
        $data['order_amount'] = 23.77;
        $remote_order_number = rand(111111,999999);
        $data['order_number'] = $remote_order_number;
        $data['phone_number'] = cleanAllNonNumericCharactersFromString($user_resource->contact_no);
        $data['location_id'] = '88888';
        $phone_number = cleanAllNonNumericCharactersFromString($user_resource->contact_no);
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/loyalty/$phone_number";
        $response = $this->makeRequest($url,$up,'POST',$data);
        $this->assertEquals(200,$this->info['http_code']);

        //check to see if loyalty was updated
        $ubrm_record = UserBrandPointsMapAdapter::staticGetRecord(array("user_id"=>$user_resource->user_id),'UserBrandPointsMapAdapter');
        $this->assertEquals(338,$ubrm_record['points'],"it shoujld have recorded the points");
        $this->assertEquals(3.38,$ubrm_record['dollar_balance'],"it shoujld have recorded the points");

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$remote_order_number));
        $this->assertCount(1,$ublh_records,"There should be 1 record for this order");
        $this->assertEquals(LoyaltyController::IN_STORE_PURCHASE_LABEL,$ublh_records[0]['process']);
        $this->assertEquals('location 88888',$ublh_records[0]['notes']);
        $this->assertEquals(238,$ublh_records[0]['points_added']);
        $expected_action_date = date('Y-m-d');
        $this->assertEquals($expected_action_date,$ublh_records[0]['action_date'],"should have today's date as action date");


        $response_array = json_decode($response,true);
        $this->assertEquals(200,$this->info['http_code']);
        $this->assertEquals("true",$response_array['data']['success']);
        return $user_resource->user_id;
    }

    function testRejectDuplicateOrderOnSameDay()
    {
        setContext('com.splickit.goodcentssubs');
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);
        clearAuthenticatedUserParametersForSession();
        $data['order_amount'] = 18.88;
        $remote_order_number = rand(111111,999999);
        $data['order_number'] = $remote_order_number;
        $data['phone_number'] = $user_resource->contact_no;
        $phone_number = cleanAllNonNumericCharactersFromString($user_resource->contact_no);
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/loyalty/$phone_number";
        $response = $this->makeRequest($url,$up,'POST',$data);
        $this->assertEquals(200,$this->info['http_code']);
        $response_array = json_decode($response,true);
        $this->assertEquals("true",$response_array['data']['success']);


        // now hit it a second time
        $response2 = $this->makeRequest($url,$up,'POST',$data);
        $this->assertEquals(409,$this->info['http_code']);
        $response_array = json_decode($response2,true);
        $this->assertEquals(LoyaltyController::DUPLICATE_INSTORE_ORDER_ID_ERROR,$response_array['error']['error']);
        $this->assertEquals(LoyaltyController::DUPLICATE_INSTORE_ORDER_ID_ERROR_CODE,$response_array['error']['error_code']);
    }

    function testInStoreLoyaltyEndpointWithExistingUserBadPhoneNumber()
    {
        setContext('com.splickit.goodcentssubs');
        $user_resource = createNewUserWithCCNoCVV();
        clearAuthenticatedUserParametersForSession();
        $data['order_amount'] = 10.77;
        $remote_order_number = rand(111111,999999);
        $data['order_number'] = $remote_order_number;
        $data['phone_number'] = "1235556666";
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/loyalty/1235556666";
        $response = $this->makeRequest($url,$up,'POST',$data);
        $this->assertEquals(422,$this->info['http_code']);
        $response_array = json_decode($response,true);
        $this->assertEquals(LoyaltyController::LOYALTY_ACCOUNT_DOES_NOT_EXIST_ERROR,$response_array['error']['error']);
        $this->assertEquals(LoyaltyController::LOYALTY_ACCOUNT_DOES_NOT_EXIST_ERROR_CODE,$response_array['error']['error_code']);
    }



    /**
     * @depends testInStoreLoyaltyEndpointWithExistingUser
     */
    function testGetLoyaltyBalance($user_id)
    {
        setContext('com.splickit.goodcentssubs');
        $loyalty_record = getStaticRecord(array("user_id"=>$user_id),'UserBrandPointsMapAdapter');
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/loyalty/".$loyalty_record['loyalty_number'];
        $response = $this->makeRequest($url,$up);
        $this->assertEquals(200,$this->info['http_code']);
        $response_array = json_decode($response,true);
        $data = $response_array['data'];
        $this->assertCount(3,$data);
        $this->assertEquals(338,$data['points']);
        $this->assertEquals(3.38,$data['dollar_balance']);

    }

    function testInStoreLoyaltyEndpointWithoutExistingUserNewFlag()
    {
        setContext('com.splickit.goodcentssubs');
        clearAuthenticatedUserParametersForSession();
        $data['order_amount'] = 7.77;
        $remote_order_number = rand(111111,999999);
        $data['order_number'] = $remote_order_number;
        $phone_number = rand(1111111111,9999999999);
        $data['phone_number'] = "$phone_number";
        $data['new_user'] = true;
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/loyalty/$phone_number";
        $response = $this->makeRequest($url,$up,'POST',$data);
        $this->assertEquals(200,$this->info['http_code']);
        $response_array = json_decode($response,true);
        $response_data = $response_array['data'];
        $this->assertEquals("true",$response_data['success']);
        $this->assertNotNull($response_data['id'],"It should have the id of the saved parking lot record");

        $loyalty_parking_lot_adapter = new LoyaltyParkingLotRecordsAdapter($m);
        $record = $loyalty_parking_lot_adapter->getRecord(array("brand_id"=>getBrandIdFromCurrentContext(),"phone_number"=>$phone_number));
        $this->assertEquals(7.77,$record['amount']);
        $this->assertEquals($remote_order_number,$record['remote_order_number']);

        $response = $this->makeRequest($url,$up,'POST',$data);
        $response_array = json_decode($response,true);
        $this->assertEquals(LoyaltyController::ORDER_ALREADY_PROCESSED_ERROR,$response_array['error']['error']);
        $this->assertEquals(LoyaltyController::ORDER_ALREADY_PROCESSED_ERROR_CODE,$response_array['error']['error_code']);
        $this->assertEquals(422,$this->info['http_code'],"It should have thrown an error");
        return $phone_number;
    }

    /**
     * @depends  testInStoreLoyaltyEndpointWithoutExistingUserNewFlag
     */
    function testInvitationToJoinWithTextMessageOrderIdInInfoSection($phone_number)
    {
        setContext('com.splickit.goodcentssubs');
        $loyalty_parking_lot_adapter = new LoyaltyParkingLotRecordsAdapter($m);
        $parking_lot_record = $loyalty_parking_lot_adapter->getRecord(array("brand_id"=>getBrandIdFromCurrentContext(),"phone_number"=>$phone_number));

        $mmha = new MerchantMessageHistoryAdapter($m);
        $text_message_record = $mmha->getRecord(array("message_delivery_addr"=>$phone_number));
        $info = $text_message_record['info'];
        $this->assertContains("remote_order_number=".$parking_lot_record['remote_order_number'],$info);
    }

    /**
     * @depends  testInStoreLoyaltyEndpointWithoutExistingUserNewFlag
     */
    function testBetterErrorMessageOnGetBalanceForParkingLotUser($phone_number)
    {
        setContext('com.splickit.goodcentssubs');
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/loyalty/$phone_number";
        $response = $this->makeRequest($url,$up);
        $this->assertEquals(422,$this->info['http_code']);
        $response_array = json_decode($response,true);
        $error = $response_array['error'];
        $this->assertEquals(LoyaltyController::INNACTIVE_ACCOUNT_ERROR,$error['error']);
        $this->assertEquals(LoyaltyController::INNACTIVE_ACCOUNT_ERROR_CODE,$error['error_code']);
    }

    /**
     * @depends  testInStoreLoyaltyEndpointWithoutExistingUserNewFlag
     */
    function testSendSecondInStoreLoyaltyPurchaseToParkingLotRecord($phone_number)
    {
        setContext('com.splickit.goodcentssubs');
        clearAuthenticatedUserParametersForSession();
        $data['order_amount'] = 8.88;
        $remote_order_number = rand(111111,999999);
        $data['order_number'] = $remote_order_number;
        $data['phone_number'] = "$phone_number";
        $data['new_user'] = true;
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/loyalty/$phone_number";
        $response = $this->makeRequest($url,$up,'POST',$data);
        $this->assertEquals(200,$this->info['http_code']);
        $response_array = json_decode($response,true);
        $this->assertNull($response_array['error'],"It should not have an error. just take another record in");
        $response_data = $response_array['data'];
        $this->assertEquals("true",$response_data['success']);
        $this->assertNotNull($response_data['id'],"It should have the id of the saved parking lot record");

        $loyalty_parking_lot_adapter = new LoyaltyParkingLotRecordsAdapter($m);
        $records = $loyalty_parking_lot_adapter->getRecords(array("brand_id"=>getBrandIdFromCurrentContext(),"phone_number"=>$phone_number));
        $this->assertCount(2,$records,"It should have found 2 records");
        $hash = createHashmapFromArrayOfArraysByFieldName($records,'remote_order_number');
        $this->assertEquals(8.88,$hash["$remote_order_number"]['amount']);
        return $phone_number;
    }

    /**
     * @depends  testSendSecondInStoreLoyaltyPurchaseToParkingLotRecord
     */
    function testMakeSureOnlyOneInviteTextMessageGetsCreatedForMultipleParkingLots($phone_number)
    {
        setContext('com.splickit.goodcentssubs');
        $mmha = new MerchantMessageHistoryAdapter($m);
        $text_message_records = $mmha->getRecords(array("message_delivery_addr"=>$phone_number));
        $this->assertCount(1,$text_message_records,"There should only be 1 text message created");

    }


    function testBadIdOnXoikosImport()
    {
        setContext('com.splickit.goodcentssubs');
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/import/xoikos/888888";
        $response = $this->makePOSXMLRequest($url,'POST');
        $response_array = json_decode($response,true);
        $this->assertEquals('No matching merchant id for: 888888  with brand: 430',$response_array['error']['error']);
    }

    function testUpdateLeadtimesOnMerchant()
    {
        $merchant_resource = createNewTestMerchantDelivery($this->ids['menu_id']);
        $merchant_resource->merchant_external_id = "8888";
        $merchant_resource->save();

        $merchant = CompleteMerchant::staticGetCompleteMerchant($merchant_resource->merchant_id,'delivery');
        $this->assertEquals(20,$merchant->lead_time);
        $this->assertEquals(45,$merchant->delivery_info['minimum_delivery_time']);

        $soap_action = "http://www.xoikos.com/webservices/UpdateLeadTime";
        $xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><UpdateLeadTime xmlns="http://www.xoikos.com/webservices/"><storeNumber>8888</storeNumber><pickupLeadTime>30</pickupLeadTime><deliveryLeadTime>90</deliveryLeadTime></UpdateLeadTime></soap:Body></soap:Envelope>';
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/xoikos";
        $headers = array("SOAPAction: $soap_action");
        $response = $this->makePOSXMLRequest($url,"POST",$xml,$headers);

        $expected_response = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><UpdateLeadTimeResponse xmlns="http://www.xoikos.com/webservices/"><UpdateLeadTimeResult><Success>true</Success><ErrorMessage>Store number 8888 had its lead times updated to Pickup: 30 minutes and Delivery: 90 minutes.</ErrorMessage></UpdateLeadTimeResult></UpdateLeadTimeResponse></soap:Body></soap:Envelope>';

        $this->assertEquals($expected_response,$response,"should have responded with the soap body");
        $this->assertEquals(200,$this->info['http_code']);
    }

    function testGetLeadTimesSoapRequest()
    {
        $soap_action = "http://www.xoikos.com/webservices/GetLeadTimes";
        $xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><GetLeadTimes xmlns="http://www.xoikos.com/webservices/"><storeNumber>8888</storeNumber></GetLeadTimes></soap:Body></soap:Envelope>';
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/xoikos";
        $headers = array("SOAPAction: $soap_action");
        $response = $this->makePOSXMLRequest($url,"POST",$xml,$headers);

        $expected_response = '<GetLeadTimesResult><Success>true</Success><PickupLeadTime>30</PickupLeadTime><DeliveryLeadTime>90</DeliveryLeadTime><ErrorMessage/></GetLeadTimesResult></GetLeadTimesResponse></soap:Body></soap:Envelope>';

        $this->assertContains($expected_response,$response,"should have responded with the soap body");
        $this->assertEquals(200,$this->info['http_code']);
    }

    function testCancelOrder()
    {
        $merchant_id = $this->ids['merchant_id'];
        $created_merchant_payment_type_map_id = $this->ids['merchant_payment_type_map_id'];

        $user = logTestUserIn($this->ids['user_id']);
        $user_id = $user['user_id'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',1);
        $order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertNull($order_resource->error);
        $this->assertTrue($order_resource->order_id > 1000);
        $this->assertEquals('VioPaymentService', $order_resource->payment_service_used);
        $ucid = $order_resource->ucid;

        $_SERVER['SOAPAction'] = "CancelOrder";
        $soap_action = "http://www.xoikos.com/webservices/CancelOrder";
        $xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CancelOrder xmlns="http://www.xoikos.com/webservices/"><OrderID>'.$ucid.'</OrderID></CancelOrder></soap:Body></soap:Envelope>';
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/xoikos";
        $headers = array("SOAPAction: $soap_action");
        $response = $this->makePOSXMLRequest($url,"POST",$xml,$headers);

        $expected_response = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CancelOrderResponse xmlns="http://www.xoikos.com/webservices/"><CancelOrderResult><Success>true</Success><ErrorMessage>Order with ID '.$ucid.' has been canceled.</ErrorMessage></CancelOrderResult></CancelOrderResponse></soap:Body></soap:Envelope>';

        $this->assertEquals($expected_response,$response,"should have responded with the soap body");
        $this->assertEquals(200,$this->info['http_code']);

        $order = CompleteOrder::getBaseOrderData($order_id);
        $this->assertEquals('C',$order['status'],"Status should have been set to 'C' since order was in an 'O' state");

        // check to see if order was refunded.
        $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
        if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
            $balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
        }

        $this->assertEquals($balance_change_rows_by_user_id["$user_id-CCvoid"]['charge_amt'], $order_resource->grand_total);
        $this->assertEquals($balance_change_rows_by_user_id["$user_id-CCvoid"]['notes'], 'Issuing a VioPaymentService VOID from the API: ');

        $adm_reversal_resource = Resource::find(new AdmOrderReversalAdapter($mimetypes),''.$refund_results['order_reversal_id']);
        $this->assertNull($adm_reversal_resource);

    }

    function testCancelOrderOnAuthorizedPayment()
    {
        $merchant_id = $this->ids['auth_merchant_id'];
        $created_merchant_payment_type_map_id = $this->ids['auth_merchant_payment_type_map_id'];

        $user = logTestUserIn($this->ids['user_id']);
        $user_id = $user['user_id'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',1);
        $order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertNull($order_resource->error);
        $this->assertTrue($order_resource->order_id > 1000);
        $this->assertEquals('VioPaymentService', $order_resource->payment_service_used);
        $ucid = $order_resource->ucid;

        $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
        $bcrs = $balance_change_adapter->getRecords(array("order_id"=>$order_id));
        $balance_change_record = $balance_change_adapter->getRecord(array("order_id"=>$order_id,"process"=>"Authorize"), $options);
        $this->assertEquals("PENDING",$balance_change_record['notes']);

        $_SERVER['SOAPAction'] = "CancelOrder";
        $soap_action = "http://www.xoikos.com/webservices/CancelOrder";
        $xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CancelOrder xmlns="http://www.xoikos.com/webservices/"><OrderID>'.$ucid.'</OrderID></CancelOrder></soap:Body></soap:Envelope>';
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/xoikos";
        $headers = array("SOAPAction: $soap_action");
        $response = $this->makePOSXMLRequest($url,"POST",$xml,$headers);

        $expected_response = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CancelOrderResponse xmlns="http://www.xoikos.com/webservices/"><CancelOrderResult><Success>true</Success><ErrorMessage>Order with ID '.$ucid.' has been canceled.</ErrorMessage></CancelOrderResult></CancelOrderResponse></soap:Body></soap:Envelope>';

        $this->assertEquals($expected_response,$response,"should have responded with the soap body");
        $this->assertEquals(200,$this->info['http_code']);

        $order = CompleteOrder::getBaseOrderData($order_id);
        $this->assertEquals('C',$order['status'],"Status should have been set to 'C'");

        $balance_change_record = $balance_change_adapter->getRecord(array("order_id"=>$order_id,"process"=>"Authorize"), $options);
        $this->assertEquals("CANCELED",$balance_change_record['notes']);
    }



    function testUpdateTipOnAuthorizedOrder()
    {
        setContext("com.splickit.goodcentssubs");
        $merchant_id = $this->ids['auth_merchant_id'];
        $created_merchant_payment_type_map_id = $this->ids['auth_merchant_payment_type_map_id'];

        $user = logTestUserIn($this->ids['user_id']);
        $user_id = $user['user_id'];
        $balance_before = $user['balance'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours');
        $order_data['tip'] = 0.00;
        $order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertNull($order_resource->error);
        $this->assertTrue($order_resource->order_id > 1000);
        $original_order_grand_total = $order_resource->grand_total;
        $this->assertEquals('VioPaymentService', $order_resource->payment_service_used);
        $ucid = $order_resource->ucid;

        $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
        if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
            $balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
        }
        $this->assertCount(2, $balance_change_rows_by_user_id);
        $this->assertTrue(isset($balance_change_rows_by_user_id["$user_id-Authorize"]),"Should have found the authorize row");

        $_SERVER['SOAPAction'] = "ApplyTipToOrder";
        $soap_action = "http://www.xoikos.com/webservices/ApplyTipToOrder";
        $tip_amt = 1.00;

        $xml_body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/webservices/"><OrderID>'.$ucid.'</OrderID><Amount>'.$tip_amt.'</Amount></ApplyTipToOrder></soap:Body></soap:Envelope>';
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/xoikos";
        $headers = array("SOAPAction: $soap_action");
        $response = $this->makePOSXMLRequest($url,"POST",$xml_body,$headers);

        $expected_response = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrderResponse xmlns="http://www.xoikos.com/webservices/"><ApplyTipToOrderResult><Success>true</Success><ErrorMessage>A 1 tip has been applied on Order with ID '.$ucid.'</ErrorMessage></ApplyTipToOrderResult></ApplyTipToOrderResponse></soap:Body></soap:Envelope>';

        $this->assertEquals($expected_response,$response,"should have responded with the soap body");
        $this->assertEquals(200,$this->info['http_code']);

        // try it again, should get another 200 but a fail
        $this->info = array();
        $response2 = $this->makePOSXMLRequest($url,"POST",$xml_body,$headers);
        $this->assertContains('<Success>false</Success>',$response2,"It should have contained the fail");
        $this->assertEquals(200,$this->info['http_code']);

        $balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),"BalanceChangeAdapter");
        $this->assertCount(3,$balance_change_records,"There should have been 3 balance change records");

        // now set tip to zero and try again, shoul succeed we now allow tips to be added after the order was captured
        $sql = "UPDATE Orders SET tip_amt = 0.00, grand_total = $original_order_grand_total WHERE order_id = $order_id";
        $order_adapter->_query($sql);
        $this->info = array();
        $response2 = $this->makePOSXMLRequest($url,"POST",$xml_body,$headers);
        $this->assertContains('<Success>true</Success>',$response2,"It should have added the tip");
        $this->assertEquals(200,$this->info['http_code']);
        
        // check balance change records
        $balance_change_records = BalanceChangeAdapter::staticGetRecords(array("order_id"=>$order_id),"BalanceChangeAdapter");
        $this->assertCount(4,$balance_change_records,"There should have been 4 balance change records");
    }

    function testCancelOrderOnCapturedPayment()
    {

    }

    function testUpdateTipWithNonAuthorizedCharge()
    {
        setContext("com.splickit.goodcentssubs");
        $merchant_id = $this->ids['merchant_id'];
        $created_merchant_payment_type_map_id = $this->ids['merchant_payment_type_map_id'];

        $user = logTestUserIn($this->ids['user_id']);
        $user_id = $user['user_id'];
        $order_adapter = new OrderAdapter($mimetypes);
        $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'skip hours',1);
        $order_data['tip'] = 0.00;
        $order_data['merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
        $order_resource = placeOrderFromOrderData($order_data, $time_stamp);
        $order_id = $order_resource->order_id;
        $this->assertNull($order_resource->error);
        $this->assertTrue($order_resource->order_id > 1000);
        $this->assertEquals('VioPaymentService', $order_resource->payment_service_used);
        $ucid = $order_resource->ucid;

        $balance_change_adapter = new BalanceChangeAdapter($mimetypes);
        if ($balance_change_records = $balance_change_adapter->getRecords(array("order_id"=>$order_id), $options)) {
            $balance_change_rows_by_user_id = setBalanceChangeHashFromBalanceChangeArrayFromOrder($balance_change_records);
        }
        $this->assertCount(2, $balance_change_rows_by_user_id);
        $this->assertTrue(isset($balance_change_rows_by_user_id["$user_id-CCpayment"]),"Should have found the authorize row");

        $_SERVER['SOAPAction'] = "ApplyTipToOrder";
        $soap_action = "http://www.xoikos.com/webservices/ApplyTipToOrder";
        $tip_amt = 1.00;

        $xml_body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/webservices/"><OrderID>'.$ucid.'</OrderID><Amount>'.$tip_amt.'</Amount></ApplyTipToOrder></soap:Body></soap:Envelope>';
        $url = "http://127.0.0.1:".$this->api_port."/app2/pos/xoikos";
        $headers = array("SOAPAction: $soap_action");
        $response = $this->makePOSXMLRequest($url,"POST",$xml_body,$headers);
        $this->assertEquals(200,$this->info['http_code']);
        $expected_response = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrderResponse xmlns="http://www.xoikos.com/webservices/"><ApplyTipToOrderResult><Success>false</Success><ErrorMessage>This order cannot be updated.</ErrorMessage></ApplyTipToOrderResult></ApplyTipToOrderResponse></soap:Body></soap:Envelope>';
        $this->assertEquals($expected_response,$response);

    }

    function makePOSXMLRequest($url,$method = 'GET',$body = null,$headers = array())
    {
        unset($this->info);
        $method = strtoupper($method);
        $curl = curl_init($url);
        $client_id = getPublicClientIdForContext();
        $headers = array_merge($headers,array("SPLICKIT_CLIENT_ID:$client_id","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:POSDispatchTest","NO_CC_CALL:true"));
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS,$body);
            $headers[] = 'Content-Type: application/xml';
            $headers[] = 'Content-Length: ' . strlen($body);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        logCurl($url,$method,$userpassword,$headers,$body);
        $result = curl_exec($curl);
        $this->info = curl_getinfo($curl);
        curl_close($curl);
        return $result;
    }


    function makeRequest($url,$userpassword,$method = 'GET',$data = null)
    {
        unset($this->info);
        $method = strtoupper($method);
        $curl = curl_init($url);
        if ($userpassword) {
            curl_setopt($curl, CURLOPT_USERPWD, $userpassword);
        }
        $client_id = getPublicClientIdForContext();
        $headers = array("SPLICKIT_CLIENT_ID:$client_id","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:APIDispatchTest","NO_CC_CALL:true");
        if ($authentication_token = $data['splickit_authentication_token']) {
            $headers[] = "splickit_authentication_token:$authentication_token";
        }
        if ($data['headers']) {
            $headers = $data['headers'];
        }
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            if ($data != null) {
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
        require_once "lib".DIRECTORY_SEPARATOR."mocks".DIRECTORY_SEPARATOR."viopaymentcurl.php";
        ini_set('max_execution_time',0);
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        ini_set('max_execution_time',300);
        //mysqli_query("BEGIN");

        $goodcents_skin_resource = getOrCreateSkinAndBrandIfNecessary("Goodcents Subs","Goodcents Subs",140,430);
        $brand_resource = Resource::find(new BrandAdapter($m),"".$goodcents_skin_resource->brand_id);
        $brand_resource->loyalty = 'Y';
        $brand_resource->save();

        $blra = new BrandLoyaltyRulesAdapter($m);
        $gcblr = array("brand_id"=>430);
        $goodcents_brand_loyalty_rules_resource = Resource::findOrCreateIfNotExistsByData($blra,$gcblr);
        $goodcents_brand_loyalty_rules_resource->starting_point_value = 100;
        $goodcents_brand_loyalty_rules_resource->earn_value_amount_multiplier = 10;
        $goodcents_brand_loyalty_rules_resource->loyalty_type = 'splickit_earn';
        $goodcents_brand_loyalty_rules_resource->save();

        $skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty("vtwoapi","vtwoapi",252, 101);
        $ids['skin_id'] = $skin_resource->skin_id;

        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $ids['menu_id'] = $menu_id;

        $user_resource = createNewUserWithCC();
        $ids['user_id'] = $user_resource->user_id;
        $ids['user'] = $user_resource->getDataFieldsReally();


        //creat merchant that does normal payment
        $merchant_resource_a = createNewTestMerchant($menu_id,array("new_payment"=>true));
        $ids['merchant_id'] = $merchant_resource_a->merchant_id;
        $ids['merchant_payment_type_map_id'] = $merchant_resource_a->merchant_payment_type_map_id;


        // create merchant that does authorization
        $merchant_resource = createNewTestMerchant($menu_id,array("no_payment"=>true));
        $merchant_id = $merchant_resource->merchant_id;

        $merchant_id_key = generateCode(10);
        $merchant_id_number = generateCode(5);
        $data['merchant_id_key'] = $merchant_id_key;
        $data['merchant_id_number'] = $merchant_id_number;
        $data['vio_selected_server'] = 'sage';
        $data['vio_merchant_id'] = $merchant_id;
        $data['name'] = "Test Billing Entity";
        $data['description'] = 'An entity to test with';
        $data['identifier'] = $merchant_resource->alphanumeric_id;
        $data['brand_id'] = $merchant_resource->brand_id;
        $data['type'] = $type;

        $card_gateway_controller = new CardGatewayController($mt, $u, $r);
        $resource = $card_gateway_controller->createPaymentGateway($data);
        $resource->process_type = 'authorize';
        $resource->save();
        $created_merchant_payment_type_map_id = $resource->merchant_payment_type_map->id;
        $ids['auth_merchant_id'] = $merchant_id;
        $ids['auth_merchant_payment_type_map_id'] = $created_merchant_payment_type_map_id;
        $ids['auth_billing_entity_external'] = $resource->external_id;

        $_SERVER['log_level'] = 5;
        $_SERVER['unit_test_ids'] = $ids;

        // need this for one of the tests
        $skin_resource = getOrCreateSkinAndBrandIfNecessary("bibibop","bibibop",150,437);
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
    POSDispatchTest::main();
}
?>