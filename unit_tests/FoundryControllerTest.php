<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class FoundryControllerTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];
        setContext("com.splickit.worldhq");
        //setContext("com.splickit.laparrilla");

    }

    function tearDown()
    {
        //delete your instance
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
    }

    function testPromoOnTemplate()
    {
        $user = logTestUserIn($this->ids['user_id']);
        $merchant_id = $this->ids['merchant_id'];
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'sum dum note',3);
        $order_data['promo_code'] = 'FoundryPromo';
        $checkout_resource = getCheckoutResourceFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($checkout_resource->error);
        $this->assertTrue($checkout_resource->promo_amt < 0, "It should have a valid promo amount'");

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_resource->order_id);
        $order_id = $order_resource->order_id;
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'U');
        $Foundry_controller = ControllerFactory::generateFromMessageResource($message_resource,$m,$u,$r,5);
        $message_to_send_resource = $Foundry_controller->prepMessageForSending($message_resource);
        $this->assertNotNull($message_to_send_resource,"Should have generated the message to send resource");
        $body = cleanUpDoubleSpacesCRLFTFromString($message_to_send_resource->message_text);
        $this->assertContains('<ADDITEM ITEMID="12345" PRICE="-1.00" QTY="1"/>',$body);


    }

    function testGetMenuPayload()
    {
        $merchant_resource = createNewTestMerchant();
        $alphanumeric = $merchant_resource->alphanumeric_id;
        $data['merchant_id'] = $merchant_resource->merchant_id;
        $data['menu_id'] = $this->ids['menu_id'];
        // we do it this way to prevent the creation of daughter records till the import
        Resource::createByData(new MerchantMenuMapAdapter(getM()),$data);
        $maps_resource = Resource::createByData(new MerchantFoundryInfoMapsAdapter($m),$data);

        $url = "/messagemanager/foundry/getnextmessagebymerchantid?reqtype=01&siteid=$alphanumeric&reqfmt=01";
        $request = new Request();
        $_GET = true;
        $_SERVER['QUERY_STRING'] = "reqtype=01&siteid=$alphanumeric&reqfmt=01";
        $request->_parseRequestBody();
        $foundry_controller = new FoundryController($m,$u,$request,5);
        $response = $foundry_controller->pullNextMessageResourceByMerchant($merchant_resource->alphanumeric_id);
        $this->assertEquals('<SVRRESPONSE>1</SVRRESPONSE>',$response->message_text,'It should return the no messages xml');

        $maps_resource->get_menu = 1;
        $maps_resource->save();

        $foundry_controller = new FoundryController($m,$u,$request,5);
        $response2 = $foundry_controller->pullNextMessageResourceByMerchant($merchant_resource->alphanumeric_id);
        $expected = '<SVRRESPONSE REQTYPE="01" SITEID="'.$merchant_resource->alphanumeric_id.'" SVRREQ="1" PARM6="MENU EXTRACTOR FILTER PARAMETERS" >1</SVRRESPONSE>';
        $this->assertEquals($expected,$response2->message_text,'It should return the no messages xml');

        // check to see if flag was reset
        $record = MerchantFoundryInfoMapsAdapter::staticGetRecord(array("merchant_id"=>$merchant_resource->merchant_id),'MerchantFoundryInfoMapsAdapter');
        $this->assertNotNull($record);
        $this->assertEquals("0",$record['get_menu']);

    }


    function testCreateFoundryMessageControllerFromFactory()
    {
        $message_resource = Resource::dummyfactory(array("message_format" => 'U'));
        $controller_name = ControllerFactory::getControllerNameFromMessageResource($message_resource);
        $this->assertEquals('Foundry',$controller_name,"It should return Foundry as the controller name");

        $controller = ControllerFactory::generateFromMessageResource($message_resource,$m,$u,$r,5);
        $this->assertEquals('FoundryController', get_class($controller), "It should return a Foundry Controller");
    }

    function testCreateFoundryMessageControllerFromUrl()
    {
        $url = "/getnextmessagebymerchantid/Foundry/3456wert3456ert";
        $controller = ControllerFactory::generateFromUrl($url,$m,$u,$r,5);
        $this->assertEquals('FoundryController', get_class($controller), "It should return a Foundry Controller");
    }

    function testGenerateTemplate()
    {
        $user = logTestUserIn($this->ids['user_id']);
        $merchant_id = $this->ids['merchant_id'];
        //$order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'sum dum note',3);
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'sum dum note',1);
        $order_data['tip'] = 0.00;
        $order_resource = placeOrderFromOrderData($order_data, getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_resource->order_id);
        $order_id = $order_resource->order_id;
        $messages = MerchantMessageHistoryAdapter::getAllOrderMessages($order_id);
        $messages_hash = createHashmapFromArrayOfResourcesByFieldName($messages, 'message_format');
        $this->assertNotNull($messages_hash['U'], "SHould have created a message with the Foundry format");
        return $messages_hash['U'];
    }

    /**
     * @depends testGenerateTemplate
     */
    function testCreateTemplateFromMessageResourece($message_resource)
    {
        $this->assertTrue(true);
        $expected_payload = cleanUpDoubleSpacesCRLFTFromString(file_get_contents("./unit_tests/resources/expected_Foundry_message_body.txt"));
        $order_id = $message_resource->order_id;
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
        $user = $complete_order['user'];

        $expected_payload = str_replace("%%ready_time%%",$complete_order['pickup_time_military_with_seconds'],$expected_payload);
        $expected_payload = str_replace("%%ready_date_time%%",$complete_order['pickup_date_time_foundry'],$expected_payload);
        $expected_payload = str_replace("%%pickup_time_ampm%%",$complete_order['pickup_time_ampm'],$expected_payload);
        $expected_payload = str_replace("%%order_id%%",$order_id,$expected_payload);
        $expected_payload = str_replace("%%user_email%%",$user['email'],$expected_payload);
        $expected_payload = str_replace("%%user_phone_no%%",$user['contact_no'],$expected_payload);
        $expected_payload = str_replace("%%item_external_id%%",$complete_order['order_details'][0]['external_id'],$expected_payload);

        $mods_stuff = explode(':',$complete_order['order_details'][0]['order_detail_complete_modifier_list_no_holds'][0]['external_id']);
        $expected_payload = str_replace("%%modifier_external_id%%",$mods_stuff[1],$expected_payload);
        $expected_payload = str_replace("%%modifier_group_external_id%%",$mods_stuff[0],$expected_payload);
        $Foundry_controller = ControllerFactory::generateFromMessageResource($message_resource,$m,$u,$r,5);
        $message_to_send_resource = $Foundry_controller->prepMessageForSending($message_resource);
        $this->assertNotNull($message_to_send_resource,"Should have generated the message to send resource");
        $body = cleanUpDoubleSpacesCRLFTFromString($message_to_send_resource->message_text);

        $expected_payload = str_replace("> <","><",$expected_payload);
        $this->assertEquals($expected_payload,$body,"should have created the expected Foundry payload");

        // now save the message as if it was sent
//        $message_resource->message_text = $body;
//        $message_resource->locked = 'S';
//        $message_resource->sent_dt_tm = date("Y-m-d H:i:s",time());
//        $message_resource->viewed = 'N';
//        $message_resource->stamp = $_SERVER['STAMP'];
//        $message_resource->tries = 1;
//        $message_resource->save();

        $message_resource->next_message_dt_tm = time() - 100;
        $message_resource->save();
        return $expected_payload;
    }

    /**
     * @depends testCreateTemplateFromMessageResourece
     */
    function testPullMessageResource($expected_payload)
    {

        $merchant_id = $this->ids['merchant_id'];
        $merchant_resource = $this->ids['merchant_resource'];
        $alphanumeric = $merchant_resource->alphanumeric_id;
        $request = new Request();
        $request->url = "/messagemanager/foundry/getnextmessagebymerchantid?reqtype=02&siteid=$alphanumeric&reqfmt=01";
        $_GET = true;
        $_SERVER['QUERY_STRING'] = "reqtype=02&siteid=$alphanumeric&reqfmt=01";
        $request->_parseRequestBody();
        $message_controller = ControllerFactory::generateFromUrl($request->url, $mimetypes, $user, $request, 5);

        $message_response_resource = $message_controller->pullNextMessageResourceByMerchant($alphanumeric);
        myerror_log("pulled message is: ".$message_response_resource->message_text);
        $clean_payload = cleanUpDoubleSpacesCRLFTFromString($message_response_resource->message_text);
        $this->assertEquals($expected_payload,$clean_payload);
        //$this->assertContains('', substr($message_resource->message_text));
        $message_controller->markMessageDelivered();

        $message = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($message_response_resource->order_id,'U');
        $this->assertEquals($message->message_text,$clean_payload);

        // make sure order is not marked as 'E' yet
        $order_record = CompleteOrder::getBaseOrderData($message->order_id,$m);
        $this->assertEquals('O',$order_record['status'],"Status shoudl still be open till the reply comes in");
        return $message->order_id;
    }

    /**
     * @depends testPullMessageResource
     */
    function testFoundryCallBack($order_id)
    {
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
        $alphanumeric_id = $complete_order['merchant']['alphanumeric_id'];
        $call_back_payload = cleanUpDoubleSpacesCRLFTFromString('<POSRESPONSE><ERRORS COUNT="0" /><DEBUGS COUNT="0" /><LOGS COUNT="0" /><CHECKRESPONSES>
        <ADDCHECK SYSTEMERRORS="0" ITEMERRORS="1" TENDERERRORS="0" EXTCHECKID="Wyatt1'.$order_id.'" ORDERID="'.$order_id.'" INTCHECKID="110017" POSCHECKSUBTOTAL="'.$complete_order['order_amt'].'" POSCHECKTAX="'.$complete_order['total_tax'].'" POSCHECKTOTAL="'.$complete_order['grand_total'].'" DONOTRESEND="TRUE">
			<LOGS COUNT="0" />
			<DEBUGS COUNT="2">
				<DEBUG TEXT="D150, Enhanced External Check ID: MYORDER-123" />
				<DEBUG TEXT="D150, MicrosTransaction.PostTransaction ChkNum=1924, ChkId=MYORDER-123, SubTtl=14.95, Tax=1.30, TtlDue=16.25" />
			</DEBUGS><ERRORS COUNT="0" />
		</ADDCHECK>
	</CHECKRESPONSES>
	<PRINTRESPONSES />
	<ERRORS COUNT="0" />
	<DEBUGS COUNT="2">
		<DEBUG TEXT="D150, Enhanced External Check ID: MYORDER-123" />
		<DEBUG TEXT="D150, MicrosTransaction.PostTransaction ChkNum=1924, ChkId=MYORDER-123, SubTtl=14.95, Tax=1.30, TtlDue=16.25" />
	</DEBUGS>
	<LOGS COUNT="0" />
</POSRESPONSE>');

        $request = new Request();
        $request->body = $call_back_payload;
        $request->method = 'POST';
        $request->mimetype = 'application/xml';
        $request->url = 'http://localhost/app2/messagemanager/foundry/getnextmessagebymerchantid?reqtype=04&siteid='.$alphanumeric_id.'&reqfmt=01';
        $_SERVER['QUERY_STRING'] = 'reqtype=04&siteid='.$alphanumeric_id.'&reqfmt=01';
        $request->_parseRequestBody();
        $message_controller = ControllerFactory::generateFromUrl($request->url, $mimetypes, $user, $request, 5);
        $message_controller->pullNextMessageResourceByMerchant($alphanumeric_id);
        $message = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'U');
        $this->assertEquals($call_back_payload,$message->response,"It should have saved the response on the message object");
        $this->assertEquals('V',$message->viewed,"It should have marked the message as viewed");

        // make sure order is now marked as 'E'
        $order_record = CompleteOrder::getBaseOrderData($message->order_id,$m);
        $this->assertEquals('E',$order_record['status'],"Status should have been marked as 'E'");

    }

    /**
     * @depends testPullMessageResource
     */
    function testFoundryCallBackFailure($order_id)
    {
        //$sql = "UPDATE Orders SET status = 'O' WHERE order_id = $order_id";
        //$order_adapter = new OrderAdapter($m);
        $this->assertTrue(OrderAdapter::updateOrderStatus('O',$order_id));
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'U');
        $message_resource->viewed = 'N';
        $message_resource->save();

        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
        $alphanumeric = $complete_order['merchant']['alphanumeric_id'];
        $this->assertEquals('O',$complete_order['status']);
        $call_back_payload = cleanUpDoubleSpacesCRLFTFromString('<?xml version="1.0" encoding="UTF-8"?>
<POSRESPONSE>
   <ERRORS COUNT="1">
      <ERROR TEXT="MicrosTransaction.PostTransaction Try: System.Web.Services.Protocols.SoapException: [-956235746] This item is not a combo meal main item, it was not found in the main item group &#xA;   at ResPosApiWeb.ResPosApiWebService.PostTransactionEx(ResPosAPI_GuestCheck&amp; pGuestCheck, ResPosAPI_MenuItem[]&amp; ppMenuItems, ResPosAPI_ComboMeal[]&amp; ppComboMeals, ResPosAPI_SvcCharge&amp; pServiceChg, ResPosAPI_Discount&amp; pSubTotalDiscount, ResPosAPI_TmedDetailItemEx&amp; pTmedDetail, ResPosAPI_TotalsResponse&amp; pTotalsResponse, String[]&amp; ppCheckPrintLines, String[]&amp; ppVoucherOutput)" ERRNUM="9" ERRORDATETIME="3/30/2016 18:33:39" />
   </ERRORS>
   <DEBUGS COUNT="13">
      <DEBUG TEXT="D150, Enhanced External Check ID: SP-12200688-318" />
      <DEBUG TEXT="D150, MicrosTransaction.IsComboMeal: ObjNum=32, GrpSeq=32, MainItem=8011001" />
      <DEBUG TEXT="D50, Compose Sides for Combo Meal: 8011002" />
      <DEBUG TEXT="D50, Adding Combo meal side item: 8026311" />
      <DEBUG TEXT="D50, Adding Combo meal side item: 8026002" />
      <DEBUG TEXT="D50, Adding Combo meal side item: 9100022" />
      <DEBUG TEXT="D50, Adding Combo meal side item: 8026005" />
      <DEBUG TEXT="D50, Adding Combo meal side item: 9100006" />
      <DEBUG TEXT="D50, Adding Combo meal side item: 8026007" />
      <DEBUG TEXT="D50, Adding Combo meal side item: 8005047" />
      <DEBUG TEXT="D50, Adding Combo meal side item: 8018002" />
      <DEBUG TEXT="D150, FullName: Test Do Not Make" />
      <DEBUG TEXT="D150, Email: store_tester@dummy.com" />
   </DEBUGS>
   <LOGS COUNT="0" />
   <CHECKRESPONSES>
      <ADDCHECK ORDERID="'.$order_id.'" EXTCHECKID="SP-'.$order_id.'-318" ITEMERRORS="0" TENDERERRORS="0" SYSTEMERRORS="1">
         <LOGS COUNT="0" />
         <DEBUGS COUNT="13">
            <DEBUG TEXT="D150, Enhanced External Check ID: SP-12200688-318" />
            <DEBUG TEXT="D150, MicrosTransaction.IsComboMeal: ObjNum=32, GrpSeq=32, MainItem=8011001" />
            <DEBUG TEXT="D50, Compose Sides for Combo Meal: 8011002" />
            <DEBUG TEXT="D50, Adding Combo meal side item: 8026311" />
            <DEBUG TEXT="D50, Adding Combo meal side item: 8026002" />
            <DEBUG TEXT="D50, Adding Combo meal side item: 9100022" />
            <DEBUG TEXT="D50, Adding Combo meal side item: 8026005" />
            <DEBUG TEXT="D50, Adding Combo meal side item: 9100006" />
            <DEBUG TEXT="D50, Adding Combo meal side item: 8026007" />
            <DEBUG TEXT="D50, Adding Combo meal side item: 8005047" />
            <DEBUG TEXT="D50, Adding Combo meal side item: 8018002" />
            <DEBUG TEXT="D150, FullName: Test Do Not Make" />
            <DEBUG TEXT="D150, Email: store_tester@dummy.com" />
         </DEBUGS>
         <ERRORS COUNT="1">
            <ERROR TEXT="MicrosTransaction.PostTransaction Try: System.Web.Services.Protocols.SoapException: [-956235746] This item is not a combo meal main item, it was not found in the main item group &#xA;   at ResPosApiWeb.ResPosApiWebService.PostTransactionEx(ResPosAPI_GuestCheck&amp; pGuestCheck, ResPosAPI_MenuItem[]&amp; ppMenuItems, ResPosAPI_ComboMeal[]&amp; ppComboMeals, ResPosAPI_SvcCharge&amp; pServiceChg, ResPosAPI_Discount&amp; pSubTotalDiscount, ResPosAPI_TmedDetailItemEx&amp; pTmedDetail, ResPosAPI_TotalsResponse&amp; pTotalsResponse, String[]&amp; ppCheckPrintLines, String[]&amp; ppVoucherOutput)" ERRNUM="9" ERRORDATETIME="3/30/2016 18:33:39" />
         </ERRORS>
      </ADDCHECK>
   </CHECKRESPONSES>
   <PRINTRESPONSES />
</POSRESPONSE>');

        $request = new Request();
        $request->body = $call_back_payload;
        $request->method = 'POST';
        $request->mimetype = 'application/xml';
        $request->url = 'http://localhost/app2/messagemanager/foundry/getnextmessagebymerchantid?reqtype=04&siteid=$alphanumeric_id&reqfmt=01';
        $_SERVER['QUERY_STRING'] = 'reqtype=04&siteid=$alphanumeric_id&reqfmt=01';
        $request->_parseRequestBody();
        $message_controller = ControllerFactory::generateFromUrl($request->url, $mimetypes, $user, $request, 5);
        $message_controller->pullNextMessageResourceByMerchant($alphanumeric);
        $message = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'U');
        $this->assertEquals($call_back_payload,$message->response,"It should have saved the response on the message object");
        $this->assertEquals('F',$message->viewed,"It should have marked the message viewed as FAILED");

        // make sure order stayed in the open state 'O'
        $order_record = CompleteOrder::getBaseOrderData($message->order_id,$m);
        $this->assertEquals('O',$order_record['status'],"Status should still be open 'O'");

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
        $mysqli->begin_transaction();

        createWorldHqSkin();
        setContext('com.splickit.worldhq');
//        getOrCreateSkinAndBrandIfNecessary('laparrilla','laparrilla',203,484);
//        setContext('com.splickit.laparrilla');
        $ids['skin_id'] = getSkinIdForContext();

        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(3);
        $ids['menu_id'] = $menu_id;

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1);
        //$modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 2);
//        $modifier_group_resource2 = createModifierGroupWithNnumberOfItems($menu_id, 1);
//        $modifier_group_resource3 = createModifierGroupWithNnumberOfItems($menu_id, 1);
        $modifier_size_map = $modifier_group_resource->modifier_items[0]->modifier_size_map;
        $modifier_size_map->external_id = '12345:'.$modifier_size_map->external_id;
        //$modifier_size_map->external_id = '98765:2-12345:'.$modifier_size_map->external_id;
        $modifier_size_map->save();

        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_resource->modifier_group_id, 0);
//        assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_resource2->modifier_group_id, 0);
//        assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_resource3->modifier_group_id, 0);

        #create the test merchant
        $merchant_resource = createNewTestMerchant($menu_id,["authorize"=>true]);
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $ids['merchant_id'] = $merchant_resource->merchant_id;
        $ids['merchant_resource'] = $merchant_resource;
        $resource = Resource::createByData(new MerchantFoundryInfoMapsAdapter(getM()),array("merchant_id"=>$merchant_resource->merchant_id,"promo_id"=>12345));

        #create the Merchant_Message_Map resource for the foundty group. Lets go with 'U'
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_resource->merchant_id,'U','Foundry','X');

        #create the foundry brand tender_ids table
        $brand_id = getBrandIdFromCurrentContext();
        $sql = "INSERT INTO Foundry_Brand_Card_Tender_Ids (`brand_id`,`visa`,`master`,`american_express`,`discover`) VALUES ($brand_id,215,216,217,218)";
        $fbctia = new FoundryBrandCardTenderIdsAdapter(getM());
        $fbctia->_query($sql);
        $user_resource = createNewUser(array("flags"=>"1C20000001"));
        $ids['user_id'] = $user_resource->user_id;


        // create a promo
        //create the type 1 promo
        $promo_data = [];
        $key_word = "FoundryPromo";
        $promo_data['key_word'] = "$key_word";
        $promo_data['promo_type'] = 1;
        $promo_data['description'] = 'Get $ off';
        $promo_data['start_date'] = '2010-01-01';
        $promo_data['end_date'] = '2030-01-01';
        $promo_data['max_use'] = 100;
        $promo_data['allow_multiple_use_per_order'] = false;
        $promo_data['valid_on_first_order_only'] = 'N';
        $promo_data['order_type'] = 'pickup';
        $promo_data['merchant_id'] = 0;
        $promo_data['qualifying_amt'] = 1.00;
        $promo_data['promo_amt'] = 1.00;
        $promo_data['percent_off'] = 10;
        $promo_data['max_amt_off'] = 50.00;
        $promo_data['brand_id'] = getBrandIdFromCurrentContext();
        $request = createRequestObject("/app2/admin/promo",'POST',json_encode($promo_data));
        $promo_controller = new PromoController(getM(),null,$request,5);
        $response = $promo_controller->createPromo();
        $ids['promo_key_word'] = $key_word;



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
    FoundryControllerTest::main();
}

?>