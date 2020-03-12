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

class MessageDispatchTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;
    var $info;

    function setUp()
    {
        $_SERVER['no_new_merchant_payment'] = true;
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $_SERVER['DO_NOT_RUN_CC'] = 'true';
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

    function testPasswordProtectSomeMessages()
    {
        $password_skin_resource = getOrCreateSkinAndBrandIfNecessary("cloudrestrictiveskin",'cloudrestrictivebrand',$skin_id,$brand_id);
        $password_skin_resource->mobile_app_type = 'R';
        $password_skin_resource->password = 'nullit';
        $password_skin_resource->save();
        $public_client_id = $password_skin_resource->public_client_id;
        setContext('com.splickit.worldhq');
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $data['message_data'] = ["message_format"=>"J",'delivery_addr'=>"Ghost Kitchen"];
        $merchant_resource = createNewTestMerchant($menu_id,$data);
        $merchant_id = $merchant_resource->merchant_id;
        $merchant_numeric = $merchant_resource->numeric_id;
        SkinMerchantMapAdapter::createSkinMerchantMapRecord($merchant_resource->merchant_id,$password_skin_resource->skin_id);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data);
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00);
        $this->assertNull($order_resource->error);

        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_resource->order_id,'J');
        $message_resource->next_message_dt_tm = time()-660;
        $message_resource->save();

        $url = "http://127.0.0.1:".$this->api_port."/app2/messagemanager/getnextmessagebymerchantid/$merchant_numeric/json";

        $headers = ["X_SPLICKIT_CLIENT_ID: $public_client_id"];
        $response = $this->makeRequest($url,null,'GET',$headers);
        $this->assertEquals("Unauthorized Request",$response);
        $response = $this->makeRequest($url,'message_admin:test12345','GET',$headers);
        $this->assertEquals("Unauthorized Request",$response);
        $password_skin_resource->password = Encrypter::Encrypt('test12345');
        $password_skin_resource->save();
        $response = $this->makeRequest($url,'message_admin:test54321','GET',$headers);
        $this->assertEquals("Unauthorized Request",$response);
        $response = $this->makeRequest($url,'message_admin:test12345','GET',$headers);
        $this->assertNotEquals("Unauthorized Request",$response);
        $message_json_as_array = json_decode($response,true);
        $this->assertEquals($order_resource->order_id,$message_json_as_array['order_id'],'It should have retrieved the message for the order');
    }

    function testJsonController()
    {
        setContext('com.splickit.worldhq');
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $merchant_resource = createNewTestMerchant($menu_id);
        $merchant_id = $merchant_resource->merchant_id;
        $merchant_numeric = $merchant_resource->numeric_id;
        $map_resource = Resource::createByData(new MerchantMessageMapAdapter(getM()),array("merchant_id"=>$merchant_id,"message_format"=>'J',"delivery_addr"=>"Ghost Kitchen","message_type"=>"X"));

        $url = "http://127.0.0.1:".$this->api_port."/app2/messagemanager/getnextmessagebymerchantid/$merchant_numeric/json";
        $response = $this->makeRequest($url,null);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data);
        $this->assertNull($checkout_resource->error);
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,null);
        $this->assertNull($order_resource->error);


        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_resource->order_id,'J');
        $message_resource->next_message_dt_tm = time()-100;
        $message_resource->save();
        $expected_message_text = $message_resource->portal_order_json;

        $response = $this->makeRequest($url,null);

        $this->assertEquals($expected_message_text,cleanUpCRLFTFromString($response),"It should have the expected message text");
    }


    function testStarPrinter()
    {
        setContext('com.splickit.worldhq');
        $merchant_resource = createNewTestMerchant($this->ids['menu_id']);
        $merchant_numeric_id = $merchant_resource->numeric_id;
        $merchant_id = $merchant_resource->merchant_id;
        attachMerchantToSkin($merchant_resource->merchant_id, $this->ids['skin_id']);
        $map_resource = MerchantMessageMapAdapter::createMerchantMessageMap($merchant_resource->merchant_id,'RUC','Starmicros','X');

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/messagemanager/getnextmessagebymerchantid/$merchant_numeric_id/starmicros/message.txt";

        $body = '{"status": "23 6 0 0 0 4 0 0 0 ","printerMAC": "00:11:62:0d:77:9f","statusCode": "211%20OK%20Paper%20Low","printingInProgress": false,"clientAction": null}';
        $data = json_decode($body,true);
        $response = $this->makeRequest($url,null,'POST',NULL,$data);

        $this->assertEquals('{"jobReady": false,"mediaTypes": ["text/plain"]}',$response);

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_resource->merchant_id, 'pickup', 'sum dum note');
        $order_data['tip'] = 0.00;
        $order_resource = placeOrderFromOrderData($order_data, $time);
        $this->assertNull($order_resource->error);
        $order_id = $order_resource->order_id;
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_resource->order_id,'RUC');
        $message_resource->next_message_dt_tm = time() - 100;
        $message_resource->save();

        $response = $this->makeRequest($url,null,'POST',NULL,$data);
        $this->assertEquals('{"jobReady": true,"mediaTypes": ["text/plain"]}',$response);

        // validate that the message was not picked up
        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_resource->order_id,'RUC');
        $this->assertEquals('P',$message_resource->locked);

        $url = "http://127.0.0.1:" . $this->api_port . "/app2/messagemanager/getnextmessagebymerchantid/$merchant_numeric_id/starmicros/message.txt?mac=00:11:62:0d:77:9f&type=text/plain";
        $response = $this->makeRequest($url,'GET');

        $this->assertContains("Order Id:  $order_id",$response);

        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_resource->order_id,'RUC');
        $this->assertEquals('S',$message_resource->locked);
    }


    function testGetNextMessageBadAlphanumeric()
    {
        $url = "http://127.0.0.1:".$this->api_port."/app2/messagemanager/foundry/getnextmessagebymerchantid/4565434467/";
        $response = $this->makeRequest($url,$up);
        $this->assertEquals(422,$this->info['http_code'],"it should have sent back a unprocessable entitiy error");
        $this->assertEquals('No matching merchant for: 4565434467',$response);
    }

    function testGetSendMenuResponse()
    {
        $merchant_resource = createNewTestMerchant();
        $data['merchant_id'] = $merchant_resource->merchant_id;
        $data['menu_id'] = $this->ids['menu_id'];
        // we do it this way to prevent the creation of daughter records till the import
        Resource::createByData(new MerchantMenuMapAdapter($m),$data);
        $maps_resource = Resource::createByData(new MerchantFoundryInfoMapsAdapter($m),$data);

        $url = "http://127.0.0.1:".$this->api_port."/app2/messagemanager/foundry/getnextmessagebymerchantid?reqtype=01&siteid=".$merchant_resource->alphanumeric_id."&reqfmt=01";
        $response = makeLocalhostRequest($url,$up);
        $this->assertEquals("<SVRRESPONSE>1</SVRRESPONSE>",$response['result']);

        $maps_resource->get_menu = 1;
        $maps_resource->save();

        $url = "http://127.0.0.1:".$this->api_port."/app2/messagemanager/foundry/getnextmessagebymerchantid?reqtype=01&siteid=".$merchant_resource->alphanumeric_id."&reqfmt=01";
        $response = makeLocalhostRequest($url,$up);
        $expected = '<SVRRESPONSE REQTYPE="01" SITEID="'.$merchant_resource->alphanumeric_id.'" SVRREQ="1" PARM6="MENU EXTRACTOR FILTER PARAMETERS" >1</SVRRESPONSE>';
        $this->assertEquals("$expected",$response['result']);

        $maps_resource = $maps_resource->getRefreshedResource();
        $this->assertEquals(0,$maps_resource->get_menu,"shoudl have set the flag back to false");

    }


    function testCheckForFoundryMessagesReadyToBePickedUpNoMessageReady()
    {
        $merchant_id = $this->ids['merchant_alphanumeric_id'];
        $url = "http://127.0.0.1:".$this->api_port."/app2/messagemanager/foundry/getnextmessagebymerchantid?reqtype=01&siteid=$merchant_id&reqfmt=01";
        $response = $this->makeRequest($url,$up);
        $this->assertEquals("<SVRRESPONSE>1</SVRRESPONSE>",$response);

    }

    function testCheckForFoundryMessagesReadyToBePickedUpWithMessageReady()
    {
        $merchant_resource = createNewTestMerchant();
        $maps_resource = Resource::createByData(new MerchantFoundryInfoMapsAdapter($m),array("merchant_id"=>$merchant_resource->merchant_id));
        $url = "http://127.0.0.1:".$this->api_port."/app2/messagemanager/foundry/getnextmessagebymerchantid?reqtype=01&siteid=".$merchant_resource->alphanumeric_id."&reqfmt=01";
        $response = $this->makeRequest($url,$up);
        $this->assertEquals("<SVRRESPONSE>1</SVRRESPONSE>",$response);
        createMessages(1,$merchant_resource->merchant_id,'U','P');
        $url = "http://127.0.0.1:".$this->api_port."/app2/messagemanager/foundry/getnextmessagebymerchantid?reqtype=01&siteid=".$merchant_resource->alphanumeric_id."&reqfmt=01";
        $response = $this->makeRequest($url,$up);
        $this->assertEquals("<SVRRESPONSE>2</SVRRESPONSE>",$response);
    }

    function testPullFoundryMessage()
    {
        setContext('com.splickit.worldhq');
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1);
        $modifier_size_map = $modifier_group_resource->modifier_items[0]->modifier_size_map;
        $modifier_size_map->external_id = '12345:'.$modifier_size_map->external_id;
        //$modifier_size_map->external_id = '98765:2-12345:'.$modifier_size_map->external_id;
        $modifier_size_map->save();
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_resource->modifier_group_id, 0);

        $merchant_resource = createNewTestMerchant($menu_id);
        $maps_resource = Resource::createByData(new MerchantFoundryInfoMapsAdapter($m),array("merchant_id"=>$merchant_resource->merchant_id));
        MerchantMessageMapAdapter::createMerchantMessageMap($merchant_resource->merchant_id,'U','Foundry','X');
        $url = "http://127.0.0.1:".$this->api_port."/app2/messagemanager/foundry/getnextmessagebymerchantid?reqtype=01&siteid=".$merchant_resource->alphanumeric_id."&reqfmt=01";
        $response = $this->makeRequest($url,$up);
        $this->assertEquals("<SVRRESPONSE>1</SVRRESPONSE>",$response);

        $user = logTestUserIn($this->ids['user_id']);
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_resource->merchant_id, 'pickup', 'sum dum note');
        $order_data['tip'] = 0.00;
        $order_resource = placeOrderFromOrderData($order_data, $time);
        $this->assertNull($order_resource->error);
        sleep(2);
        $response = $this->makeRequest($url,$userpassword);
        $this->assertEquals("<SVRRESPONSE>2</SVRRESPONSE>",$response);

        $url = "http://127.0.0.1:".$this->api_port."/app2/messagemanager/foundry/getnextmessagebymerchantid?reqtype=02&siteid=".$merchant_resource->alphanumeric_id."&reqfmt=01";
        $order_message_payload = cleanUpDoubleSpacesCRLFTFromString($this->makeRequest($url,$userpassword));
        $order_message_payload = str_replace("> <","><",$order_message_payload);

        $expected_payload = cleanUpDoubleSpacesCRLFTFromString(file_get_contents("./unit_tests/resources/expected_Foundry_message_body.txt"));
        $order_id = $order_resource->order_id;
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
//
//        $expected_payload = str_replace("%%ready_time%%",$complete_order['pickup_time_military_with_seconds'],$expected_payload);
//        $expected_payload = str_replace("%%ready_date_time%%",$complete_order['pickup_date_time4'],$expected_payload);
//
//        $expected_payload = str_replace("%%order_id%%",$order_id,$expected_payload);
//        $expected_payload = str_replace("%%user_email%%",$user['email'],$expected_payload);
//        $expected_payload = str_replace("%%item_external_id%%",$complete_order['order_details'][0]['external_id'],$expected_payload);
//        $expected_payload = str_replace("%%modifier_external_id%%",$complete_order['order_details'][0]['order_detail_complete_modifier_list_no_holds'][0]['external_id'],$expected_payload);
        $expected_payload = str_replace("> <","><",$expected_payload);
        //$this->assertEquals($expected_payload,$order_message_payload);

        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'U');
        //$this->assertEquals($expected_payload,cleanUpDoubleSpacesCRLFTFromString($message_resource->message_text),"It should have saved the message text on the message resource");

        return $order_resource->order_id;
    }

    /**
     * @depends testPullFoundryMessage
     */
    function testFoundryCallBack($order_id)
    {
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
        $alphanumeric_id = $complete_order['merchant']['alphanumeric_id'];
        $url = "http://127.0.0.1:".$this->api_port."/app2/messagemanager/foundry/getnextmessagebymerchantid?reqtype=05&siteid=$alphanumeric_id&reqfmt=01";
        $call_back_payload = cleanUpDoubleSpacesCRLFTFromString('<POSRESPONSE EXTTERMID="" EXTREQUESTID="">
	<CHECKRESPONSES>
		<ADDCHECK ORDERID="'.$order_id.'" EXTCHECKID="SPLICKIT-'.$order_id.'" INTCHECKID="37" POSCHECKTOTAL="'.$complete_order['grand_total'].'" POSCHECKSUBTOTAL="'.$complete_order['order_amt'].'" POSCHECKTAX="'.$complete_order['total_tax'].'" ITEMERRORS="0" TENDERERRORS="0" SYSTEMERRORS="0" DONOTRESEND="TRUE">
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

        $result = makeLocalhostRequest($url,$up,'POST',$call_back_payload,'application/xml');
        $info = $result['curl_info'];
        $this->assertEquals(422,$info['http_code'],"It should have resulted in an unprocessable entitiry since reqtype=5 is not valid");

//        $curl = curl_init($url);
//        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//
//        curl_setopt($curl, CURLOPT_POSTFIELDS, $call_back_payload);
//        curl_setopt($curl, CURLOPT_POST, 1);
//        $headers[] = 'Content-type: application/xml; charset=utf-8';
//        $headers[] = 'Content-Length: '.strlen($call_back_payload);
//        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
//        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//        $result = curl_exec($curl);
//        $this->info = curl_getinfo($curl);
//        curl_close($curl);
        $url = "http://127.0.0.1:".$this->api_port."/app2/messagemanager/foundry/getnextmessagebymerchantid?reqtype=04&siteid=$alphanumeric_id&reqfmt=01";
        $result = makeLocalhostRequest($url,$up,'POST',$call_back_payload,'application/xml');
        $info = $result['curl_info'];
        $this->assertEquals(200,$info['http_code']);

        $message_resource = MerchantMessageHistoryAdapter::getMessageByOrderIdAndFormat($order_id,'U');
        $this->assertEquals($call_back_payload,$message_resource->response,"It should have saved the response on the message resource");
        $this->assertEquals('V',$message_resource->viewed);
    }

    function testFoundryCallBackBadPost($order_id)
    {
        $alphanumeric_id = $this->ids['merchant_resource']['alphanumeric_id'];
        $url = "http://127.0.0.1:".$this->api_port."/app2/messagemanager/foundry/getnextmessagebymerchantid?reqtype=04&siteid=$alphanumeric_id&reqfmt=01";
        $call_back_payload = cleanUpDoubleSpacesCRLFTFromString('<POSRESPONSE EXTTERMID="" EXTREQUESTID="">
	<CHECKRESPONSES>
		<ADDCHECK ORDERID="" EXTCHECKID="SPLICKIT-88888888" INTCHECKID="37" POSCHECKTOTAL="10.88" POSCHECKSUBTOTAL="5.99" POSCHECKTAX="5.77" ITEMERRORS="0" TENDERERRORS="0" SYSTEMERRORS="0" DONOTRESEND="TRUE">
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
        $result = makeLocalhostRequest($url,$up,'POST',$call_back_payload,'application/xml');
//        $curl = curl_init($url);
//        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//        curl_setopt($curl, CURLOPT_POSTFIELDS, $call_back_payload);
//        curl_setopt($curl, CURLOPT_POST, 1);
//        $headers[] = 'Content-type: application/xml; charset=utf-8';
//        $headers[] = 'Content-Length: '.strlen($call_back_payload);
//        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
//        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//        $result = curl_exec($curl);
//        $info = curl_getinfo($curl);
//        curl_close($curl);

        $this->assertEquals(422,$result['curl_info']['http_code']);
    }



    function makeRequest($url,$userpassword,$method = 'GET', $headers = array(),$data = null)
    {
        unset($this->info);
        $method = strtoupper($method);
        $curl = curl_init($url);
        if ($userpassword) {
            curl_setopt($curl, CURLOPT_USERPWD, $userpassword);
        }
        if ($method == 'POST') {
            $json = json_encode($data);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS,$json);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($json);
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
        createWorldHqSkin();

        $menu_id = createTestMenuWithOneItem('item_one');
        $ids['menu_id'] = $menu_id;
        $_SERVER['no_new_merchant_payment'] = true;
        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 1);
        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_resource->modifier_group_id, 0);

        $merchant_resource = createNewTestMerchant($menu_id);
        $maps_resource = Resource::createByData(new MerchantFoundryInfoMapsAdapter($m),array("merchant_id"=>$merchant_resource->merchant_id));
        $ids['merchant_id'] = $merchant_resource->merchant_id;
        $ids['merchant_alphanumeric_id'] = $merchant_resource->alphanumeric_id;
        $user_resource = createNewUser(array("flags"=>"1C20000001"));
        $ids['user_id'] = $user_resource->user_id;
        $ids['user'] = $user_resource->getDataFieldsReally();
        $_SERVER['log_level'] = 5;
        $_SERVER['unit_test_ids'] = $ids;
    }

    static function tearDownAfterClass()
    {
        date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
        $suite = new PHPUnit_Framework_TestSuite( __CLASS__);
        PHPUnit_TextUI_TestRunner::run( $suite);
    }



}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    MessageDispatchTest::main();
}

?>