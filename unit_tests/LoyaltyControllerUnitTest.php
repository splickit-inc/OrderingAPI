<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class LoyaltyControllerUnitTest extends PHPUnit_Framework_TestCase
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

    function testDoNotUpdateLoyaltyNumberForUserRecreationOfExistingUser()
    {
        $user_resource = createNewUser();
        $phone_number = str_ireplace('-','',$user_resource->contact_no);
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);
        $user_brand_points_map_adapter = new UserBrandPointsMapAdapter(getM());
        $user_brand_points_record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertNotNull($user_brand_points_record,"Should have found a user brand loyalty record");
        $this->assertEquals("$phone_number",$user_brand_points_record['loyalty_number'],"It should have pushed the phone number to the loyalty record when it was created");

        $new_user_data = '{"password":"welcome","contact_no":"'.$phone_number.'","marketing_email_opt_in":"N","group_airport_employee":"N","last_name":"'.$user_resource->last_name.'","email":"'.$user_resource->email.'","birthday":"","first_name":"'.$user_resource->first_name.'"}';
        $request = createRequestObject("/app2/apiv2/users/".$user_resource->uuid,'POST',$new_user_data);
        $user_controller = new UserController(getM(),$user,$request,5);
        $result = $user_controller->processV2Request();
        $user_brand_points_record2 = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals($user_brand_points_record['loyalty_number'],$user_brand_points_record2['loyalty_number'],"it should have the same loyalty number");

    }

    function testRemoveLoyaltyEarnForCancelledOrder()
    {
        setContext("com.splickit.goodcentssubs");
        $user_resource = createNewUserWithCCNoCVV();
        $ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $ubpm_record1 = $ubpm_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));

        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);
        $loyalty_number = $user_session->brand_loyalty['loyalty_number'];

        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertNotNull($ubpm_record,"There should be a loyalty record");
        $this->assertEquals(100,$ubpm_record['points']);

        clearAuthenticatedUserParametersForSession();
        $user_id = $user_resource->user_id;
        $data['order_amount'] = 20.00;
        $remote_order_number = rand(111111,999999);
        $data['order_number'] = $remote_order_number;
        $data['phone_number'] = $loyalty_number;
        $data['location_id'] = '99999';
        $url = "http://localhost/app2/pos/loyalty/$phone";
        $request = createRequestObject($url,'POST',json_encode($data),'application/json');
        $pos_controller = new PosController($m,null,$request,5);
        $resource = $pos_controller->processV2request();
        $this->assertNull($resource->error);

        $ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals(300,$ubpm_record['points']);
        $this->assertEquals(3.00,$ubpm_record['dollar_balance']);

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$remote_order_number));
        $this->assertCount(1,$ublh_records,"There should be 1 record for this order");
        $ublh_record = $ublh_records[0];
        $this->assertEquals(LoyaltyController::IN_STORE_PURCHASE_LABEL,$ublh_record['process']);
        $this->assertEquals('location 99999',$ublh_record['notes']);
        $this->assertEquals(200,$ublh_record['points_added']);

        //now send a cancel order request
        $data['order_number'] = $remote_order_number;
        $data['phone_number'] = $loyalty_number;
        $url = "http://localhost/app2/pos/loyalty/$loyalty_number/orders/$remote_order_number";
        $request = createRequestObject($url,'DELETE');
        $pos_controller = new PosController($m,null,$request,5);
        $resource = $pos_controller->processV2request();
        $this->assertNull($resource->error);

        $ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals(100,$ubpm_record['points']);
        $this->assertEquals(1.00,$ubpm_record['dollar_balance']);

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$remote_order_number));
        $this->assertCount(2,$ublh_records,"There should be 2 records for this order");
        $ublh_record = $ublh_records[1];
        $this->assertEquals(LoyaltyController::IN_STORE_CANCELLED_LABEL,$ublh_record['process']);
        $this->assertEquals(-200,$ublh_record['points_added']);
        $this->assertEquals(0,$ublh_record['points_redeemed']);
        $this->assertEquals(100,$ublh_record['current_points']);
        $this->assertEquals(1.00,$ublh_record['current_dollar_balance']);

    }

    function testExistingUserLoginPullAnyParkingLotRecords()
    {
        setContext('com.splickit.goodcentssubs');
        clearAuthenticatedUserParametersForSession();
        $phone = rand(1111111111,9999999999);
        $data['order_amount'] = 8.88;
        $remote_order_number = rand(111111,999999);
        $data['order_number'] = $remote_order_number;
        $data['phone_number'] = $phone;
        $data['location_id'] = '1234567';
        $data['new_user'] = true;
        $url = "http://localhost/app2/pos/loyalty/$phone";
        $request = createRequestObject($url,'POST',json_encode($data),'application/json');
        $pos_controller = new PosController($m,$user,$request,5);
        $resource = $pos_controller->processV2request();
        $options[TONIC_FIND_BY_METADATA]['phone_number'] = $phone;
        $plra = new LoyaltyParkingLotRecordsAdapter();
        $parking_lot_resource = Resource::find($plra,"",$options);
        $this->assertNotNull($parking_lot_resource,"there should be a parking lot resource");
        $remote_order_number = $parking_lot_resource->remote_order_number;


//        $user_resource = createNewUserWithCCNoCVV(array("contact_no"=>$phone));
//        $user = logTestUserResourceIn($user_resource);
//
        $email = getCreateTestUserEmail(createCode(3));

        $uuid = createUUID();
        $sql = 'INSERT INTO User (`uuid`, `account_hash`, `first_name`, `last_name`, `email`, `password`, `contact_no`, `device_id`, `balance`, `flags`, `skin_name`, `skin_id`, `device_type`, `app_version`, `created`, `modified`) VALUES ("'.$uuid.'", "1495222314_rz8", "First", "Last", "'.$email.'", "$2y$10$m.9v4wSRR4JPdHKn9L6p2OqbDxcizqtI2.618ahsWW8yCtXtTDiMy", "'.$phone.'", "'.$uuid.'", 0, "1C20000001", "com.splickit.goodcentssubs", 140, "UnitTest", 1000, NOW(), NOW())';
        $user_adapter = new UserAdapter();
        $user_adapter->_query($sql);
        $user_resource = Resource::find(new UserAdapter(getM()),"".$user_adapter->_insertId());
        $user = logTestUserResourceIn($user_resource);
        //$user = $user_adapter->getRecordFromPrimaryKey($user_adapter->_insertId()));

        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);


//        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
//        $ublh_records = $ublh_adapter->getRecords(array("user_id"=>$user['user_id']));
//        $remote_order

        clearAuthenticatedUserParametersForSession();
        setContext('com.splickit.goodcentssubs');


        $parking_lot_resource->_exists = false;
        unset($parking_lot_resource->id);
        $new_remote_order_number = rand(111111,999999);
        $parking_lot_resource->remote_order_number = "$new_remote_order_number";
        $parking_lot_resource->amount = "5.00";
        $two_days_ago = getTodayTwelveNoonTimeStampDenver() - 48*60*60;
        $parking_lot_resource->created = $two_days_ago;
        $parking_lot_resource->save();

        $sql = "UPDATE Loyalty_Parking_Lot_Records SET created = '".getMySqlFormattedDateTimeFromTimeStampAndTimeZone($two_days_ago)."' WHERE id = ".$parking_lot_resource->id;
        $plra->_query($sql);

        $s = date("Y-m-d",$two_days_ago);
        $parking_lot_resource = Resource::find(new LoyaltyParkingLotRecordsAdapter(),"",$options);
        $oa = date("Y-m-d",$parking_lot_resource->created);

        $ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user['user_id'],"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertNotNull($ubpm_record,"it should have created the loyalty record");
        $this->assertEquals($phone,$ubpm_record['loyalty_number'],"It should have set the loyalty number to the phone number");
        $this->assertEquals(189,$ubpm_record['points']);
        $this->assertEquals(1.89,$ubpm_record['dollar_balance']);

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$remote_order_number));
        $this->assertCount(1,$ublh_records,"There should be 1 record for this order");
        $ublh_record = $ublh_records[0];
        $this->assertEquals(LoyaltyController::IN_STORE_PURCHASE_LABEL,$ublh_record['process']);
        $this->assertEquals(89,$ublh_record['points_added']);
        $this->assertEquals('location 1234567',$ublh_record['notes']);

        //  now log in again and see if pull in the new record
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);

        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user['user_id'],"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals(239,$ubpm_record['points']);
        $this->assertEquals(2.39,$ubpm_record['dollar_balance']);

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$new_remote_order_number));
        $this->assertCount(1,$ublh_records,"There should be 1 record for this order");
        $ublh_record = $ublh_records[0];
        $this->assertEquals(LoyaltyController::IN_STORE_PURCHASE_LABEL,$ublh_record['process']);
        $this->assertEquals(50,$ublh_record['points_added']);

        $this->assertEquals(date('Y-m-d',$two_days_ago),$ublh_record['action_date'],"It shoujld have the correct action date");


    }

    function testCreateLoyaltyRecordForTempUserConversion()
    {
        setContext('com.splickit.goodcentssubs');
        $device_id = generateUUID();
        $body = '{"password":"TlhKDMd8ni6M","device_id":"'.$device_id.'","first_name":"SpTemp","email":"'.$device_id.'@splickit.dum","last_name":"User"}';

        $user = logTestUserIn(1);
        $request = createRequestObject('/phone/users/','POST',$body,'application/json');
        $user_controller = new UserController($mt, $user, $request, 5);
        $response = $user_controller->createUser();
        $this->assertNull($response->error);
        $user_as_temp = logTestUserIn($response->user_id);

        $record = getStaticRecord(array("user_id"=>$response->user_id),'UserBrandPointsMapAdapter');
        $this->assertNull($record,"It should not create loyalty for the temp user");

        $code = generateAlphaCode(10);
        $new_email = $code.'@dummy.com';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $contact_no = "(".rand(111,999).")-".rand(111,999).".".rand(1111,9999);
        $data['contact_no'] = $contact_no;
        $data['email'] = $new_email;
        $data['password'] = "somdumpassword";
        $data['first_name'] = "somedumguy";
        $data['last_name'] = "lastname";
        $request = createRequestObject('/phone/users/','POST',json_encode($data),'application/json');
        $user_controller = new UserController($mt, $user_as_temp, $request, 5);
        $response = $user_controller->updateUser();

        $record = getStaticRecord(array("user_id"=>$response->user_id),'UserBrandPointsMapAdapter');
        $this->assertNotNull($record,"It should create loyalty record for the updated user");

        $loyalty_number = cleanAllNonNumericCharactersFromString($contact_no);
        $this->assertEquals($loyalty_number,$record['loyalty_number']);
    }

    function testCreateCorrectLoyaltyNumberForInsertUser()
    {
        setContext('com.splickit.goodcentssubs');
        $device_id = generateUUID();
        $contact_no = "(".rand(111,999).")-".rand(111,999).".".rand(1111,9999);
        $code = generateAlphaCode(10);
        $body = '{"password":"welcome","contact_no":"'.$contact_no.'","marketing_email_opt_in":"Y","group_airport_employee":"N","last_name":"roberts","email":"'.$code.'@dummy.com","birthday":"03\/16\/1960","first_name":"bob"}';

        $user = logTestUserIn(1);
        $request = createRequestObject('/apiv2/users/','POST',$body,'application/json');
        $user_controller = new UserController($mt, $user, $request, 5);
        $response = $user_controller->createUser();
        $this->assertNull($response->error);

        $loyalty_number = str_replace('-','',$response->contact_no);
        $record = getStaticRecord(array("user_id"=>$response->user_id),'UserBrandPointsMapAdapter');
        $this->assertNotContains('splick',strtolower($record['loyalty_number']));
        $this->assertEquals($loyalty_number,$record['loyalty_number'],"It should have the loyalty number of the users contact number");
    }

    function testDoNotCreateLoyaltyForTempUsersAPIv1CreateUser()
    {
        $device_id = generateUUID();
        $body = '{"password":"TlhKDMd8ni6M","device_id":"'.$device_id.'","first_name":"SpTemp","email":"'.$device_id.'@splickit.dum","last_name":"User"}';

        $user = logTestUserIn(1);
        $request = createRequestObject('/phone/users/','POST',$body,'application/json');
        $user_controller = new UserController($mt, $user, $request, 5);
        $response = $user_controller->createUser();
        $this->assertNull($response->error);

        $record = getStaticRecord(array("user_id"=>$response->user_id),'UserBrandPointsMapAdapter');
        $this->assertNull($record,"It should not create loyalty for the temp user");

        $user = logTestUserIn($response->user_id);
        $user_resource = Resource::find(new UserAdapter(),$user['user_id']);
        $request = createRequestObject('http://localhost/app2/phone/usersession','GET');
        $user_session_controller = new UsersessionController($m,$user,$request,5);
        $user_session = $user_session_controller->getUserSession($user_resource);

        $record = getStaticRecord(array("user_id"=>$response->user_id),'UserBrandPointsMapAdapter');
        $this->assertNull($record,"It should not create loyalty for the temp user");
    }

    function testDoNotCreateLoyaltyForTempUsersAPIv1UserSession()
    {
        $device_id = generateUUID();
        $body = '{"password":"TlhKDMd8ni6M","device_id":"'.$device_id.'","first_name":"SpTemp","email":"'.$device_id.'@splickit.dum","last_name":"User"}';


        $user = logTestUserIn(1);
        $request = createRequestObject('/phone/users/','POST',$body,'application/json');
        $user_controller = new UserController($mt, $user, $request, 5);
        $response = $user_controller->createUser();
        $this->assertNull($response->error);
        $user = logTestUserIn($response->user_id);
        $user_id = $user['user_id'];

        $ubpma = new UserBrandPointsMapAdapter();
        $sql = "DELETE FROM User_Brand_Points_Map WHERE user_id = $user_id";
        $ubpma->_query($sql);

        $record = getStaticRecord(array("user_id"=>$response->user_id),'UserBrandPointsMapAdapter');
        $this->assertNull($record,"make sure no record exists");


        $user_resource = Resource::find(new UserAdapter(),$user_id);
        $request = createRequestObject('http://localhost/app2/phone/usersession','GET');
        $user_session_controller = new UsersessionController($m,$user,$request,5);
        $user_session = $user_session_controller->getUserSession($user_resource);

        $record = getStaticRecord(array("user_id"=>$response->user_id),'UserBrandPointsMapAdapter');
        $this->assertNull($record,"It should not create loyalty for the temp user");
    }

    function testGetLoyaltyAwardRecords()
    {
        $award_type_data = array();
        $award_type_data['brand_id'] = getBrandIdFromCurrentContext();
        $award_type_data['loyalty_award_trigger_type_id'] = 1000;
        $award_type_data['trigger_value'] = '10.00';
        $resource = Resource::createByData(new LoyaltyAwardBrandTriggerAmountsAdapter($m),$award_type_data);

        $brand_award_map_data = array();
        $brand_award_map_data['brand_id'] = getBrandIdFromCurrentContext();
        $brand_award_map_data['loyalty_award_brand_trigger_amounts_id'] = $resource->id;
        $brand_award_map_data['process_type'] = 'fixed';
        $brand_award_map_data['active'] = 0;
        $brand_award_map_data['value'] = 10;
        $resource2 = Resource::createByData(new LoyaltyBrandBehaviorAwardAmountMapsAdapter($m),$brand_award_map_data);

        // active flag should limit it
        $records = LoyaltyBrandBehaviorAwardAmountMapsAdapter::getBrandBehaviorAwardRecords(getBrandIdFromCurrentContext());
        $this->assertCount(0,$records);

        $resource2->active = 1;
        $resource2->save();

        $records = LoyaltyBrandBehaviorAwardAmountMapsAdapter::getBrandBehaviorAwardRecords(getBrandIdFromCurrentContext());
        $this->assertCount(1,$records);
        $record = $records[0];
        $this->assertEquals("Order_Minimum",$record['trigger_name']);
        $this->assertEquals("10.00",$record['trigger_value']);

        //try date limiting
        $loyalty_controller = new LoyaltyController($m,$user,$r,5);
        $this->assertTrue($loyalty_controller->isAwardWithinActiveDates($record,date('Y-m-d')));
        $resource2->last_date_available = '2016-08-01';
        $resource2->save();

        $records_for_expired_award = LoyaltyBrandBehaviorAwardAmountMapsAdapter::getBrandBehaviorAwardRecords(getBrandIdFromCurrentContext());
        $this->assertFalse($loyalty_controller->isAwardWithinActiveDates($records_for_expired_award[0],date('Y-m-d')));

        $resource2->last_date_available = '2050-08-01';
        $resource2->save();

        return true;
    }

    /**
     * @depends testGetLoyaltyAwardRecords
     */
    function testSurpriseAndDelightIsOrderMinimumMet($bool)
    {
        $loyalty_controller = new LoyaltyController($mt,$user,$request,5);
        $complete_order = array();
        $complete_order['order_id'] = 12345;
        $complete_order['order_amt'] = 8.00;

        $records = LoyaltyBrandBehaviorAwardAmountMapsAdapter::getBrandBehaviorAwardRecords(getBrandIdFromCurrentContext());
        $this->assertFalse($loyalty_controller->isAwardMet($records[0],$complete_order),"It should NOT have met the award");
        $complete_order['order_amt'] = 12.00;
        $this->assertTrue($loyalty_controller->isAwardMet($records[0],$complete_order),"It should have met the award");
    }

    function testGetAwardValue()
    {
        $loyalty_controller = new LoyaltyController($mt,$user,$request,5);
        $data['process_type'] = 'fixed';
        $data['value'] = 10;
        $this->assertEquals(10,$loyalty_controller->getAwardValue($data,80));

        $data['process_type'] = 'percent';
        $data['value'] = 50;
        $this->assertEquals(15,$loyalty_controller->getAwardValue($data,30));

        $data['process_type'] = 'multiplier';
        $data['value'] = 2;
        $this->assertEquals(30,$loyalty_controller->getAwardValue($data,30));
    }

    function testSubmitInstorePurchaseAmountToSplickitNewUserSaveInParkingLot()
    {
        clearAuthenticatedUserParametersForSession();
        $phone = '1'.rand(1111111111,9999999999);
        $data['order_amount'] = 8.88;
        $remote_order_number = rand(111111,999999);
        $data['order_number'] = $remote_order_number;
        $data['phone_number'] = $phone;
        $data['location_id'] = "99999";
        $data['new_user'] = true;
        $url = "http://localhost/app2/pos/loyalty/$phone";
        $request = createRequestObject($url,'POST',json_encode($data),'application/json');
        $pos_controller = new PosController($m,$user,$request,5);
        $resource = $pos_controller->processV2request();

        $lplra = new LoyaltyParkingLotRecordsAdapter($m);
        $expected_phone = substr($phone,1);
        $lplr_record = $lplra->getRecord(array("brand_id"=>getBrandIdFromCurrentContext(),"phone_number"=>$expected_phone));
        $this->assertNotNull($lplr_record,'It should have created the parking lot record');
        $this->assertEquals(8.88,$lplr_record['amount']);
        $this->assertEquals("InStore Purchase",$lplr_record['process']);
        $this->assertEquals("99999",$lplr_record['location']);

        // now try to submit again. should error out
        $pos_controller = new PosController($m,$user,$request,5);
        $resource = $pos_controller->processV2request();
        $this->assertNotNull($resource->error,"Should have thrown an error becuase the order had already been saved.");
        $this->assertEquals("This order has already been processed.",$resource->error);

        return $expected_phone;
    }

    /**
     * @depends testSubmitInstorePurchaseAmountToSplickitNewUserSaveInParkingLot
     */
    function testCreateTextMessageWithLinkForAppDownload($phone_number)
    {
        $message = getStaticRecord(array("message_delivery_addr"=>$phone_number),'MerchantMessageHistoryAdapter');
        $this->assertNotNull($message,"It should have found the text message");
        $message_text = $message['message_text'];
        $expected_message_text = str_replace('%%link%%',LoyaltyController::PARKING_LOT_LINK,LoyaltyController::PARKING_LOT_MESSAGE_TEXT);
        $expected_message_text = str_replace('%%skin_name%%',"loy unit skin",$expected_message_text);
        $expected_message_text = str_replace('%%skin_name_id%%',"loyunitskin",$expected_message_text);

//        $expected_message_text = LoyaltyController::PARKING_LOT_MESSAGE_TEXT.LoyaltyController::PARKING_LOT_LINK;
//        $expected_message_text = str_replace('%%skin_name%%','loyunitskin',$expected_message_text);
        $this->assertEquals($expected_message_text,$message_text,"It shoudl have the link with the brand_id");

        //validate that orderid is on record
        $loyalty_parking_lot_adapter = new LoyaltyParkingLotRecordsAdapter($m);
        $parking_lot_record = $loyalty_parking_lot_adapter->getRecord(array("brand_id"=>getBrandIdFromCurrentContext(),"phone_number"=>$phone_number));

        $this->assertContains("remote_order_number=".$parking_lot_record['remote_order_number'],$message['info']);
    }

    /**
     * @depends testSubmitInstorePurchaseAmountToSplickitNewUserSaveInParkingLot
     */
    function testBetterErrorMessageForParkingLotGetBalance($phone)
    {
        $request = createRequestObject("/app2/pos/loyalty/$phone");
        $loyalty_controller = new LoyaltyController($m,$user,$request,5);
        $response = $loyalty_controller->processRemoteRequest();
        $this->assertNotNull($response->error,"IT should throw an error");
        $this->assertEquals(LoyaltyController::INNACTIVE_ACCOUNT_ERROR,$response->error);
        $this->assertEquals(422,$response->http_code,"It should throw an unprocessable entity error");
        $this->assertEquals(LoyaltyController::INNACTIVE_ACCOUNT_ERROR_CODE,$response->error_code);

    }

    /**
     * @depends testSubmitInstorePurchaseAmountToSplickitNewUserSaveInParkingLot
     */
    function testJoinSplickitPullParkingLotInfoIntoNewUsersLoyalty($phone_number)
    {
        $lplra = new LoyaltyParkingLotRecordsAdapter($m);
        $lplr_record = $lplra->getRecord(array("brand_id"=>getBrandIdFromCurrentContext(),"phone_number"=>$phone_number));

        $user_resource = createNewUserWithCCNoCVV(array("contact_no"=>$phone_number));
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);
        $user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);

        $ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user['user_id'],"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertNotNull($ubpm_record,"it should have created the loyalty record");
        $this->assertEquals($phone_number,$ubpm_record['loyalty_number'],"It should have set the loyalty number to the phone number");
        $this->assertEquals(89,$ubpm_record['points']);
        $this->assertEquals(.89,$ubpm_record['dollar_balance']);

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$lplr_record['remote_order_number']));
        $this->assertCount(1,$ublh_records,"There should be 1 record for this order");
        $ublh_record = $ublh_records[0];
        $this->assertEquals(LoyaltyController::IN_STORE_PURCHASE_LABEL,$ublh_record['process']);
        $this->assertEquals(89,$ublh_record['points_added']);
        return $phone_number;
    }

    /**
     * @depends testJoinSplickitPullParkingLotInfoIntoNewUsersLoyalty
     */
    function testRemovalOfParkingLotRecordsAfterJoin($phone_number)
    {
        $lplra = new LoyaltyParkingLotRecordsAdapter($m);
        $lplr_records = $lplra->getRecords(array("brand_id"=>getBrandIdFromCurrentContext(),"phone_number"=>$phone_number));
        $this->assertCount(0,$lplr_records);
        return $phone_number;
    }

    function testGetLoyaltyInfo()
    {
        $user_resource = createNewUser();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);
        $user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $ubpm_resource = Resource::find($user_brand_points_map_adapter,'',array(3=>array("user_id"=>$user['user_id'])));
        $ubpm_resource->points = 1230;
        $ubpm_resource->dollar_balance = 12.30;
        $ubpm_resource->save();
        
        $user = logTestUserIn($user['user_id']);
        $request = createRequestObject("/app2/pos/loyalty/".$ubpm_resource->loyalty_number);
        $loyalty_controller = new LoyaltyController($m,$user,$request,5);
        $response = $loyalty_controller->processRemoteRequest();
        $this->assertEquals(1230,$response->points);
        $this->assertEquals(12.30,$response->dollar_balance);
        $this->assertEquals(getBrandIdFromCurrentContext(),$response->brand_id);
        return $user;
    }

    /**
     * @depends testGetLoyaltyInfo
     */
    function testSubmitInstorePurchaseAmountToSplickitNewUserButUserWithSamePhoneNumberExists($user)
    {
        clearAuthenticatedUserParametersForSession();
        $phone = str_replace('-','',$user['contact_no']);
        $data['order_amount'] = 3.33;
        $remote_order_number = rand(111111,999999);
        $data['order_number'] = $remote_order_number;
        $data['phone_number'] = $phone;
        $data['new_user'] = true;
        $url = "http://localhost/app2/pos/loyalty/$phone";
        $request = createRequestObject($url,'POST',json_encode($data),'application/json');
        $pos_controller = new PosController($m,$user,$request,5);
        $resource = $pos_controller->processV2request();
        $first_name = $user['first_name'];
        $last_name = $user['last_name'];
        $this->assertEquals(LoyaltyController::LOYALTY_NUMBER_EXISTS_FOR_REMOTE_JOIN_ERROR."$first_name $last_name",$resource->error);
        $this->assertEquals(LoyaltyController::LOYALTY_NUMBER_EXISTS_FOR_REMOTE_JOIN_ERROR_CODE,$resource->error_code);
    }

    function testConvertDuplicates()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = $user_resource->getDataFieldsReally();
        clearAuthenticatedUserParametersForSession();
        $new_user_resource = createNewUserWithCCNoCVV();
        $new_user_id = $new_user_resource->user_id;

        // now change loyalty number
        $options[TONIC_FIND_BY_METADATA] = array("user_id"=>$new_user_resource->user_id);
        $ubla = new UserBrandPointsMapAdapter();
        $ublr = Resource::find($ubla,null,$options);
        $ublr->loyalty_number = str_replace('-','',$user['contact_no']);
        $ublr->save();

        // now have two with same loyalty number

        clearAuthenticatedUserParametersForSession();
        $phone = $ublr->loyalty_number;
        $data['order_amount'] = 2.22;
        $remote_order_number = rand(111111,999999);
        $data['order_number'] = $remote_order_number;
        $data['phone_number'] = $phone;
        $url = "http://localhost/app2/pos/loyalty/$phone";
        $request = createRequestObject($url,'POST',json_encode($data),'application/json');
        $pos_controller = new PosController($m,$new_user_resource->getDataFieldsReally(),$request,5);
        $resource = $pos_controller->processV2request();
        $this->assertNull($resource->error);

        //now check to see if amount got added to second and first one got converted.
        $second_ubpm_record = $ubla->getRecord(array("user_id"=>$new_user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals(22,$second_ubpm_record['points']);
        $this->assertEquals(.22,$second_ubpm_record['dollar_balance']);

        $first_ubpm_record = $ubla->getRecord(array("user_id"=>$user['user_id'],"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals(0.00,$first_ubpm_record['dollar_balance']);
        $this->assertEquals($phone."10",$first_ubpm_record['loyalty_number']);

    }

    function testSetLoyaltyNumberToExternalStringOnHomeGrown()
    {
        $user_resource = createNewUser();
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);
        $user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $original_user_brand_points_record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertNotNull($original_user_brand_points_record,"Should have found a user brand loyalty record");
        $original_loyalty_number = $original_user_brand_points_record['loyalty_number'];
        $this->assertNotNull($original_loyalty_number,"there should be a loyalty nubmer");


        $loyalty_number = "123-abcd-xxxx";
        $data['loyalty_number'] = $loyalty_number;
        $request = createRequestObject("/apiv2/users/$uuid","POST",json_encode($data),"application/json");
        $user_controller = new UserController($m,$user,$request,5);
        $response_resource = $user_controller->processV2Request();
        $user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $user_brand_points_record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertNotNull($user_brand_points_record,"Should have found a user brand loyalty record");
        $this->assertEquals("$loyalty_number",$user_brand_points_record['loyalty_number'],"It should have added the custom loyalty number to the user account");
        $this->assertNotEquals($original_loyalty_number,$loyalty_number,"it should be different then the auto assigned loylaty");
    }

    function testAutoPhoneToLoyaltyAccount()
    {
        $user_resource = createNewUser(array("contact_no"=>"(123)-456-7890"));
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);
        $user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $user_brand_points_record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertNotNull($user_brand_points_record,"Should have found a user brand loyalty record");
        $this->assertEquals("1234567890",$user_brand_points_record['loyalty_number'],"It should have pushed the phone number to the loyalty record when it was created");
        return true;
    }

    /**
     * @depends testAutoPhoneToLoyaltyAccount
     */
    function testDuplicatePhoneNumberSetLoyaltyToSplickNumber($bool)
    {
        $user_resource = createNewUser(array("contact_no"=>"(123)-456-7890"));
        $user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $user_brand_points_record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertNotNull($user_brand_points_record,"Should have found a user brand loyalty record");
        $this->assertNotEquals("1234567890",$user_brand_points_record['loyalty_number'],"It should NOT have pushed the phone number to the loyalty record when it was created becuase of the duplicate");
        $loyalty_number = $user_brand_points_record['loyalty_number'];
        $this->assertContains("1234567890",$loyalty_number,"It should contain the phone number");
        $this->assertEquals(12,strlen($loyalty_number),"It should be a 12 digit number");
    }

    function testAddPhoneToLoyaltyAccount()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $uuid = $user_resource->uuid;
        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session = $user_session_controller->getUserSession($user_resource);
        $data['loyalty_phone_number'] = "(234) 654-4444";
        $request = createRequestObject("/apiv2/users/$uuid/loyalty","POST",json_encode($data),"application/json");
        $user_controller = new UserController($m,$user,$request,5);
        $response_resource = $user_controller->processV2Request();
        $user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $user_brand_points_record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertNotNull($user_brand_points_record,"Should have found a user brand loyalty record");
        $this->assertEquals("2346544444",$user_brand_points_record['loyalty_number'],"It should have added the phone number to the user account");
    }

    function testUpdateContactNoOnUserRecordShouldUpdatePHoneNumberOnLoyaltyRecord()
    {
        $contact_no = "(".rand(111,999).")-".rand(111,999).".".rand(1111,9999);
        $user_data['contact_no'] = $contact_no;
        $user_resource = createNewUserWithCCNoCVV($user_data);
        $uuid = $user_resource->uuid;
        $user = logTestUserResourceIn($user_resource);
        $user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $user_brand_points_record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertNotNull($user_brand_points_record,"Should have found a user brand loyalty record");
        $this->assertEquals(cleanAllNonNumericCharactersFromString($contact_no),$user_brand_points_record['loyalty_number'],"It should have added the phone number to the user account");

        //now update the user and see if hte phone nubmer propogates.
        $new_contact_number = "1231231234";
        $data['contact_no'] = $new_contact_number;
        $request = new Request();
        $request->url = "apiv2/users/$uuid";
        $request->method = 'POST';
        $request->data = $data;
        $user_controller = new UserController($mt, $user, $request);
        $resource = $user_controller->processV2Request();
        $this->assertEquals("123-123-1234",$resource->contact_no,"Contact number should have been updated");

        $user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $user_brand_points_record = $user_brand_points_map_adapter->getRecordFromPrimaryKey($user_brand_points_record['map_id']);
        $this->assertNotNull($user_brand_points_record,"Should have found a user brand loyalty record");
        $this->assertEquals($new_contact_number,$user_brand_points_record['loyalty_number'],"It should have added the phone number to the user account");
        return $new_contact_number;
    }

    /**
     * @depends testUpdateContactNoOnUserRecordShouldUpdatePHoneNumberOnLoyaltyRecord
     */
    function testUserMessageOnDuplicatePhoneNumberForLoyaltyForUpdate($phone_number)
    {
        $user_resource = createNewUserWithCCNoCVV($user_data);
        $uuid = $user_resource->uuid;
        $user = logTestUserResourceIn($user_resource);
        $user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $user_brand_points_record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));


        // now update and see if we get the correct message
        $data['contact_no'] = $phone_number;
        $request = createRequestObject("apiv2/users/$uuid",'POST',json_encode($data),'application/json');
        $user_controller = new UserController($mt, $user, $request);
        $resource = $user_controller->processV2Request();
        $this->assertNull($resource->error);

        $user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $user_brand_points_record = $user_brand_points_map_adapter->getRecordFromPrimaryKey($user_brand_points_record['map_id']);
        $loyalty_number = $user_brand_points_record['loyalty_number'];

        $this->assertEquals(LoyaltyController::LOYALTY_NUMBER_DUPLICATE_MESSAGE."$loyalty_number",$resource->user_message);

    }

    function testSubmitInstorePurchaseAmountToSplickitExistingUser()
    {
        setContext("com.splickit.goodcentssubs");
        $phone = '1234445555';
        $user_resource = createNewUserWithCCNoCVV(array("contact_no"=>$phone));
        $ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $ubpm_record1 = $ubpm_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));

        $user = logTestUserResourceIn($user_resource);
        $user_session_controller = new UsersessionController($m,$user,$r,5);
        $user_session_controller->getUserSession($user_resource);

        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertNotNull($ubpm_record,"There should be a loyalty record");
        $this->assertEquals(100,$ubpm_record['points']);

        clearAuthenticatedUserParametersForSession();
        $user_id = $user_resource->user_id;
        $data['order_amount'] = 23.77;
        $remote_order_number = rand(111111,999999);
        $data['order_number'] = $remote_order_number;
        $data['location_id'] = '99999';
        $data['phone_number'] = '1'.str_replace(' ','',$user_resource->contact_no);
        $url = "http://localhost/app2/pos/loyalty/1".$phone;
        $request = createRequestObject($url,'POST',json_encode($data),'application/json');
        $pos_controller = new PosController($m,$user_resource->getDataFieldsReally(),$request,5);
        $resource = $pos_controller->processV2request();
        $this->assertNull($resource->error);

        $ubpm_adapter = new UserBrandPointsMapAdapter($mimetypes);
        $ubpm_record = $ubpm_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>getBrandIdFromCurrentContext()));
        $this->assertEquals(338,$ubpm_record['points']);
        $this->assertEquals(3.38,$ubpm_record['dollar_balance']);

        $ublh_adapter = new UserBrandLoyaltyHistoryAdapter($mimetypes);
        $ublh_records = $ublh_adapter->getRecords(array("order_id"=>$remote_order_number));
        $this->assertCount(1,$ublh_records,"There should be 1 record for this order");
        $ublh_record = $ublh_records[0];
        $this->assertEquals(LoyaltyController::IN_STORE_PURCHASE_LABEL,$ublh_record['process']);
        $this->assertEquals('location 99999',$ublh_record['notes']);
        $this->assertEquals(238,$ublh_record['points_added']);

        //try to send a duplicate
        $request2 = createRequestObject($url,'POST',json_encode($data),'application/json');
        $pos_controller2 = new PosController($m,$user,$request2,5);
        $resource2 = $pos_controller2->processV2request();
        $this->assertNotNull($resource2->error);
        $this->assertEquals(LoyaltyController::DUPLICATE_INSTORE_ORDER_ID_ERROR,$resource2->error);
        $this->assertEquals(LoyaltyController::DUPLICATE_INSTORE_ORDER_ID_ERROR_CODE,$resource2->error_code);
        $ublh_records2 = $ublh_adapter->getRecords(array("order_id"=>$remote_order_number));
        $this->assertCount(1,$ublh_records2,"It should still only have 1 record");


    }



    static function setUpBeforeClass()
    {
        ini_set('max_execution_time',300);




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

        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;
        $_SERVER['request_time1'] = microtime(true);

        $brand_id = 88888;
        $skin_resource = getOrCreateSkinAndBrandIfNecessary("loy unit skin", "loyunitbrand", $skin_id, $brand_id);
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
        $merchant_id = $merchant_resource->merchant_id;
        $ids['merchant_id'] = $merchant_id;

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
    LoyaltyControllerUnitTest::main();
}

?>