<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class WindowsServiceControllerTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;

	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->ids = $_SERVER['unit_test_ids'];
		
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
    }
    
    function testWindowsXMLfunctions()
    {
    	$starting_order_id = rand(111111,999999);
    	$body = "<ExternalAnswer><Status>Succeeded</Status><ResponseCode>0</ResponseCode><RefNumber>ORDER $starting_order_id</RefNumber><CheckNumber>356665</CheckNumber><TableNumber>1024</TableNumber><PhoneNumber>1234567890</PhoneNumber><DateProcessed>2013-07-23</DateProcessed><TimeProcessed>15:15:58</TimeProcessed><SubTotal>6.65</SubTotal><Charge>1.00</Charge><Discount>0.00</Discount><Tax>0.00</Tax><Tips>0.00</Tips><Discrepancy>0.00</Discrepancy></ExternalAnswer>";
    	$windows_service_controller = new WindowsServiceController($mt, $u, $r,5);
    	$call_back_array = $windows_service_controller->parseCallBackXML($body);
    	$order_id = $windows_service_controller->getOrderIdFromCallBackData($call_back_array);
		$this->assertEquals($starting_order_id, $order_id);
    }
    
    function testWindowsXMLFunctionsNewFormat()
    {
    	$starting_order_id = rand(111111,999999);
    	//$body = "<ExternalAnswer><Status>Succeeded</Status><ResponseCode>0</ResponseCode><RefNumber>ORDER $starting_order_id</RefNumber><CheckNumber>356665</CheckNumber><TableNumber>1024</TableNumber><PhoneNumber>1234567890</PhoneNumber><DateProcessed>2013-07-23</DateProcessed><TimeProcessed>15:15:58</TimeProcessed><SubTotal>6.65</SubTotal><Charge>1.00</Charge><Discount>0.00</Discount><Tax>0.00</Tax><Tips>0.00</Tips><Discrepancy>0.00</Discrepancy></ExternalAnswer>";
    	$body = "<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\">
  <s:Header xmlns:s=\"http://schemas.xmlsoap.org/soap/envelope/\" />
  <soap:Body>
    <AddOrderResponse xmlns=\"http://www.maitredpos.com/webservices/MDMealZone/201202\">
      <AddOrderResult>
        <transactionDateTime>
          <transactionDateTime>2014-02-28T09:08:48.9885167-07:00</transactionDateTime>
        </transactionDateTime>
        <orderStatusHeader>
          <orderStatus>2</orderStatus>
          <status>Succeeded</status>
          <responseCode>0</responseCode>
          <refNumber>$starting_order_id</refNumber>
        </orderStatusHeader>
      </AddOrderResult>
    </AddOrderResponse>
  </soap:Body>
</soap:Envelope>";
    	//$xml = new SimpleXMLElement($body); 
//$xml->registerXPathNamespace("soap", "http://www.w3.org/2001/XMLSchema");
//$body = $xml->xpath("//soap:Body");
    	
    	$windows_service_controller = new WindowsServiceController($mt, $u, $r,5);
    	$call_back_array = $windows_service_controller->parseCallBackXML($body);
    	$order_id = $windows_service_controller->getOrderIdFromCallBackData($call_back_array);
		$this->assertEquals($starting_order_id, $order_id);
    }

    function testGetNextMessageByMerchantIdWindowsServiceForMatreDmerchant()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	logTestUserIn($this->ids['user_id']);

    	// create matre D message
    	$map_id = Resource::createByData(new MerchantMessageMapAdapter($mimetypes), array("merchant_id"=>$merchant_id,"message_format"=>"WM","delivery_addr"=>"MatreD","message_type"=>"O"));
    	$this->assertTrue($map_id > 0);
    	
    	$merchant_resource = SplickitController::getResourceFromId($merchant_id, 'Merchant');
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'the note');
    	//$json_encoded_data = $order_adapter->getSimpleOrderJSONByMerchantId(1080,'pickup','skip hours');
    	//$order_resource = placeTheOrder($json_encoded_data);
    	$time_stamp = getTodayTwelveNoonTimeStampDenver();
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNotNull($order_resource);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000,"Bad order id of: ".$order_resource->order_id);  
    	$order_id = $order_resource->order_id;
		$order_amt = $order_resource->order_amt;
    	$tax_amt = $order_resource->total_tax_amt;
    	$tip_amt = $order_resource->tip_amt;
    	//319e8v6335l4 is the alphanumeric for merchant 1080
    	$ws_controller = new WindowsServiceController($mt, $u, $r,5);
    	
    	$order_message_resources = $ws_controller->getAllMessagesForOrderId($order_id);
        foreach ($order_message_resources as $order_message_resource) {
            if ($order_message_resource->message_format == 'WM') {
                $order_message_resource->next_message_dt_tm = time()-10;
                $order_message_resource->save();
            }
        }
    	
    	$gpciha = new DeviceCallInHistoryAdapter($mimetypes);
    	$this->assertNull($gpciha->getRecord(array("merchant_id"=>$merchant_id), $options),'there should not be a record in the table yet');
    	
    	$complete_message_resource = $ws_controller->pullNextMessageResourceByMerchant($merchant_resource->alphanumeric_id);
    	$message_id = $complete_message_resource->message_id;
    	$ws_controller->markMessageDeliveredById($message_id);
    	
    	//check to see if a record was made in the call in table
    	$record = $gpciha->getRecord(array("merchant_id"=>$merchant_id), $options);
    	$this->assertNotNull($record,"should have found a record");

    	// get actual message resource to see if viewed is set to no
    	$message_resource = Resource::find(new MerchantMessageHistoryAdapter($mimetypes),''.$message_id);
    	$this->assertEquals('S', $message_resource->locked);
    	$this->assertEquals('N', $message_resource->viewed);
    	$message_text = $complete_message_resource->message_text;
    	
    	//now pull the order id out of the XML sent to the POS
    	$windows_service_controller = new WindowsServiceController($mt, $u, $r);
    	$order_array = $windows_service_controller->parseCallBackXML($message_text);
        $this->assertEquals($order_id, $order_array['Invoice']);
        $this->assertEquals('900',$order_array['WaiterNumber']);
        $this->assertEquals($order_amt, $order_array['Orders']['Order']['Item']['Price']);  
        $this->assertEquals($tax_amt, $order_array['Orders']['Taxes']['Tax']['Amount']);
        
        //simulate call back
        $return_xml = "<ExternalAnswer><Status>Succeeded</Status> <ResponseCode>1</ResponseCode> <RefNumber>ORDER $order_id</RefNumber> <CheckNumber>101513</CheckNumber> <TableNumber>301</TableNumber><PhoneNumber>555-1234</PhoneNumber><DateProcessed>2003-12-02</DateProcessed> <TimeProcessed>12:59:50</TimeProcessed> <SubTotal>3.94</SubTotal> <Charge>0.00</Charge> <Discount>0.49</Discount> <Tax>0.29</Tax> <Tips>0.00</Tips> <Discrepancy>0.00</Discrepancy></ExternalAnswer>";
        $request = new Request();
        $request->body = $return_xml;
    	$request->mimetype = 'application/xml';
    	$ws_controller2 = new WindowsServiceController($mt, $u, $request);
    	$result = $ws_controller2->callback($merchant_resource->alphanumeric_id);
    	$this->assertTrue($result);
    	
    	$message_resource2 = Resource::find(new MerchantMessageHistoryAdapter($mimetypes),''.$message_id);
    	$this->assertEquals('S', $message_resource2->locked);
    	$this->assertEquals('Y', $message_resource2->viewed);
    	
    	//no test with new format.  first set viewed back to 'N'
    	$message_resource2->viewed = 'N';
    	$message_resource2->save();
    	
    	$message_resource3 = Resource::find(new MerchantMessageHistoryAdapter($mimetypes),''.$message_id);
    	$this->assertEquals('N', $message_resource2->viewed);
    	
    	//simulate call back
		$return_xml = "<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\">
  <s:Header xmlns:s=\"http://schemas.xmlsoap.org/soap/envelope/\" />
  <soap:Body>
    <AddOrderResponse xmlns=\"http://www.maitredpos.com/webservices/MDMealZone/201202\">
      <AddOrderResult>
        <transactionDateTime>
          <transactionDateTime>2014-02-28T09:08:48.9885167-07:00</transactionDateTime>
        </transactionDateTime>
        <orderStatusHeader>
          <orderStatus>2</orderStatus>
          <status>Succeeded</status>
          <responseCode>0</responseCode>
          <refNumber>$order_id</refNumber>
        </orderStatusHeader>
      </AddOrderResult>
    </AddOrderResponse>
  </soap:Body>
</soap:Envelope>";
    	$request2 = new Request();
        $request2->body = $return_xml;
    	$request2->mimetype = 'application/xml';
    	$ws_controller3 = new WindowsServiceController($mt, $u, $request2, 5);
    	$result = $ws_controller3->callback($merchant_resource->alphanumeric_id);
    	$this->assertTrue($result);
    	
    	$message_resource4 = Resource::find(new MerchantMessageHistoryAdapter($mimetypes),''.$message_id);
    	$this->assertEquals('Y', $message_resource4->viewed);
    	
    }
    
    function testCallBackXMLWithExcption()
    {
    	$xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
<s:Header xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" />
<soap:Body>
<soap:Fault>
<faultcode>soap:Server</faultcode>
<faultstring>System.Web.Services.Protocols.SoapException: Server was unable to process request. ---&gt; System.InvalidCastException: Object cannot be cast from DBNull to other types.
at Posera.MaitreD.MDMealZoneWS.MDMealZoneWS.a(Exception A_0)
at Posera.MaitreD.MDMealZoneWS.MDMealZoneWS.AddOrder(ExternalOrder order)
--- End of inner exception stack trace ---</faultstring>
<detail />
</soap:Fault>
</soap:Body>
</soap:Envelope>';
    	
    	$windows_service_controller = new WindowsServiceController($mt, $u, $r, 5);
    	$hash_map = $windows_service_controller->parseCallBackXML($xml);
    	$this->assertNotNull($hash_map['faultstring']);
    	$expected = "System.Web.Services.Protocols.SoapException: Server was unable to process request. ---> System.InvalidCastException: Object cannot be cast from DBNull to other types.at Posera.MaitreD.MDMealZoneWS.MDMealZoneWS.a(Exception A_0)at Posera.MaitreD.MDMealZoneWS.MDMealZoneWS.AddOrder(ExternalOrder order)--- End of inner exception stack trace ---";
    	$this->assertEquals($expected, $hash_map['faultstring']);
    }
    
    function testBadCallbackMatreD()
    {
    	$merchant_id = $this->ids['merchant_id'];
    	logTestUserIn($this->ids['user_id']);

    	// create matre D message
    	$map_id = Resource::createByData(new MerchantMessageMapAdapter($mimetypes), array("merchant_id"=>$merchant_id,"message_format"=>"WM","delivery_addr"=>"MatreD","message_type"=>"O"));
    	$this->assertTrue($map_id > 0);
    	
    	$merchant_resource = SplickitController::getResourceFromId($merchant_id, 'Merchant');
    	$order_adapter = new OrderAdapter($mimetypes);
    	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id, 'pickup', 'the note');
    	//$json_encoded_data = $order_adapter->getSimpleOrderJSONByMerchantId(1080,'pickup','skip hours');
    	//$order_resource = placeTheOrder($json_encoded_data);
    	$time_stamp = getTodayTwelveNoonTimeStampDenver();
    	$order_resource = placeOrderFromOrderData($order_data, $time_stamp);
    	$this->assertNotNull($order_resource);
		$this->assertNull($order_resource->error);
		$this->assertTrue($order_resource->order_id > 1000,"Bad order id of: ".$order_resource->order_id);  
    	$order_id = $order_resource->order_id;
		$order_amt = $order_resource->order_amt;
    	$tax_amt = $order_resource->total_tax_amt;
    	$tip_amt = $order_resource->tip_amt;
    	//319e8v6335l4 is the alphanumeric for merchant 1080
    	$ws_controller = new WindowsServiceController($mt, $u, $r,5);
        $order_message_resources = $ws_controller->getAllMessagesForOrderId($order_id);
        foreach ($order_message_resources as $order_message_resource) {
            if ($order_message_resource->message_format == 'WM') {
                $order_message_resource->next_message_dt_tm = time()-10;
                $order_message_resource->save();
            }
        }

        $complete_message_resource = $ws_controller->pullNextMessageResourceByMerchant($merchant_resource->alphanumeric_id);
    	$message_id = $complete_message_resource->message_id;
    	$ws_controller->markMessageDeliveredById($message_id);
    	
    	// get actual message resource to see if viewed is set to no
    	$message_resource = Resource::find(new MerchantMessageHistoryAdapter($mimetypes),''.$message_id);
    	$this->assertEquals('S', $message_resource->locked);
    	$this->assertEquals('N', $message_resource->viewed);
    	$message_text = $complete_message_resource->message_text;
    	
    	//now pull the order id out of the XML sent to the POS
    	$windows_service_controller = new WindowsServiceController($mt, $u, $r);
    	$order_array = $windows_service_controller->parseCallBackXML($message_text);
        $this->assertEquals($order_id, $order_array['Invoice']);
        $this->assertEquals('900',$order_array['WaiterNumber']);
        $this->assertEquals($order_amt, $order_array['Orders']['Order']['Item']['Price']);  
        $this->assertEquals($tax_amt, $order_array['Orders']['Taxes']['Tax']['Amount']);
        
        //simulate call back
        $return_xml = "<ExternalAnswer><Status>Succeeded</Status> <ResponseCode>1</ResponseCode> <RefNumber>ORDER $order_id</RefNumber> <CheckNumber>101513</CheckNumber> <TableNumber>301</TableNumber><PhoneNumber>555-1234</PhoneNumber><DateProcessed>2003-12-02</DateProcessed> <TimeProcessed>12:59:50</TimeProcessed> <SubTotal>3.94</SubTotal> <Charge>0.00</Charge> <Discount>0.49</Discount> <Tax>0.29</Tax> <Tips>0.00</Tips> <Discrepancy>0.00</Discrepancy></ExternalAnswer>";
        $request = new Request();
        $request->body = $return_xml;
    	$request->mimetype = 'application/xml';
    	$ws_controller2 = new WindowsServiceController($mt, $u, $request);
    	$result = $ws_controller2->callback($merchant_resource->alphanumeric_id);
    	$this->assertTrue($result);
    	
    	$message_resource2 = Resource::find(new MerchantMessageHistoryAdapter($mimetypes),''.$message_id);
    	$this->assertEquals('S', $message_resource2->locked);
    	$this->assertEquals('Y', $message_resource2->viewed);
    	
    	//no test with new format.  first set viewed back to 'N'
    	$message_resource2->viewed = 'N';
    	$message_resource2->save();
    	
    	$message_resource3 = Resource::find(new MerchantMessageHistoryAdapter($mimetypes),''.$message_id);
    	$this->assertEquals('N', $message_resource2->viewed);
    	
    	//simulate call BAD back
    	$return_xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
<s:Header xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" />
<soap:Body>
<soap:Fault>
<faultcode>soap:Server</faultcode>
<faultstring>System.Web.Services.Protocols.SoapException: Server was unable to process request. ---&gt; System.InvalidCastException: Object cannot be cast from DBNull to other types.
at Posera.MaitreD.MDMealZoneWS.MDMealZoneWS.a(Exception A_0)
at Posera.MaitreD.MDMealZoneWS.MDMealZoneWS.AddOrder(ExternalOrder order)
--- End of inner exception stack trace ---</faultstring>
<detail />
</soap:Fault>
</soap:Body>
</soap:Envelope>';
    	
		$request2 = new Request();
        $request2->body = $return_xml;
    	$request2->mimetype = 'application/xml';
    	$ws_controller3 = new WindowsServiceController($mt, $u, $request2, 5);
    	$result = $ws_controller3->callback($merchant_resource->alphanumeric_id);
    	$this->assertFalse($result);

    	//now verify that a support email was created
		$sql = "SELECT * FROM Merchant_Message_History WHERE message_format = 'E' AND info LIKE '%subject=Winapp Call Back Failure For merchant_id: $merchant_id%' ORDER BY map_id desc LIMIT 1";
		$options[TONIC_FIND_BY_SQL] = $sql;
		$mmha = new MerchantMessageHistoryAdapter($mimetypes);
		$support_message_resource = Resource::find($mmha,'',$options);
		$this->assertNotNUll($support_message_resource);
		$this->assertContains("The call back xml indicated an error on the initial order send", $support_message_resource->message_text);

    }

/*    function testGetWindowsServiceOrderTweb()
    {
    	$this->clearOutOldMessagesFor1080(); 
    	$tip = rand(100, 1000)/100;
    	$pickup_time = time()+900;
    	
		$json =  "{\"jsonVal\":{\"merchant_id\":\"1080\",\"items\":[{\"quantity\":1,\"note\":\"\",\"item_id\":\"212051\",\"size_id\":\"70349\",\"sizeprice_id\":\"722775\",\"mods\":[]}],\"total_points_used\":0,\"note\":\"\",\"lead_time\":15,\"promo_code\":\"\",\"tip\":\"$tip\",\"user_id\":\"20000\",\"loyalty_number\":null,\"sub_total\":\"1.95\",\"grand_total\":\"2.11\",\"actual_pickup_time\":\"$pickup_time\"}}";

		$url = "https://test.splickit.com/app2/phone/placeorder?log_level=5";
    	$curl = curl_init($url);
    	curl_setopt($curl, CURLOPT_USERPWD, "20000:welcome");
    	curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json","Content-Length: " . strlen($json),"X_SPLICKIT_CLIENT_ID:com.splickit.order","X_SPLICKIT_CLIENT_DEVICE:unit_testing","X_SPLICKIT_CLIENT:CheckoutDataTest","NO_CC_CALL:true")); 
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_VERBOSE,0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		//$headers = array('Content-Type: application/json','Content-Length: ' . strlen($json));
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS,$json);
		$result = curl_exec($curl);
		curl_close($curl);
		$order_data = json_decode($result,true);
		$this->assertNull($order_data['ERROR'],"error should be null on order");
		$order_id = $order_data['order_id'];
		$this->assertTrue($order_id > 1000,"should have created an order id");
		$order_amt = $order_data['order_amt'];
		$tip_amt = $order_data['tip_amt'];
		$tax_amt = $order_data['total_tax_amt'];
				
			$curl = curl_init("https://test.splickit.com/app2/messagemanager/getnextmessagebymerchantid/319e8v6335l4/windowsservice?log_level=5");
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_VERBOSE, 0);	
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			$message_text = curl_exec($curl);
			$this->assertNotNull($message_text);
			curl_close($curl);

    	//319e8v6335l4 is the alphanumeric for merchant 1080
    	//$ws_controller = new WindowsServiceController($mt, $u, $r);
    	///$message_resource = $ws_controller->pullNextMessageResourceByMerchant('319e8v6335l4');
    	//$message_text = $message_resource->message_text;
    	//myerror_log($message_text);
    	$xml = new SimpleXMLElement($message_text);
        $order_array = myToArray($xml);
        $this->assertEquals($order_id, $order_array['Invoice']);
        $this->assertEquals('900',$order_array['WaiterNumber']);
        $this->assertEquals($order_amt, $order_array['Orders']['Order']['Item']['Price']);  
        $this->assertEquals($tax_amt, $order_array['Orders']['Taxes']['Tax']['Amount']);
        $this->assertEquals("adam Rosenthal",$order_array['Customer']['Name']);

    }
*/    

    static function setUpBeforeClass()
    {
    	$_SERVER['log_level'] = 5; 
		$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['default_tz'] = $tz;
    	date_default_timezone_set("America/Denver");
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
    	attachMerchantToSkin($merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;
    	
    	$_SERVER['unit_test_ids'] = $ids;
    	
    }
    
	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
    }

    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    WindowsServiceControllerTest::main();
}

?>