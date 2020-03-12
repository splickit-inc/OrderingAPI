<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/functions.inc';
require_once 'lib/utilities/unit_test_functions.inc';

class RequestTest extends PHPUnit_Framework_TestCase
{
	var $stamp;

	function setUp()
	{
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
    }
    
    function testRequestDataFormatXML()
    {
    	$xml_body = "<response><status>success</status><orders><order><order_id>123456</order_id></order></orders></response>";
    	$request = new Request();
    	$request->body = $xml_body;
    	$request->mimetype = 'Applicationxml';
    	$request->_parseRequestBody();
    	$data = $request->data;
    	$this->assertNotNull($data);
    	$this->assertEquals("success", $data['status']);
    	$this->assertEquals("123456", $data['orders']['order']['order_id']);
    }

	function testPullOutSoapBody()
	{
		$request = new Request();
		$request->body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/"><OrderID>1234-56789</OrderID><Amount>1.88</Amount></ApplyTipToOrder></soap:Body></soap:Envelope>';
		$request->extractSoapBody();
		$expected = '<soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/"><OrderID>1234-56789</OrderID><Amount>1.88</Amount></ApplyTipToOrder></soap:Body>';
		$this->assertEquals($expected,$request->body,"Should have pulled out the soap body");
	}

	function testReformatSoapRequest()
	{
		$xml_body = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><ApplyTipToOrder xmlns="http://www.xoikos.com/"><OrderID>1234-56789</OrderID><Amount>1.88</Amount></ApplyTipToOrder></soap:Body></soap:Envelope>';
		$soap_action = "ApplyTipToOrder";
		$_SERVER['SOAPAction'] = $soap_action;
		$request = new Request();

		$request->body = $xml_body;
		$request->mimetype = 'Applicationxml';

		$request->_parseRequestBody();
		$data = $request->data;
		$pos_controller = new PosController($mt,$u,$request,5);
		$pos_controller->reformatSoapRequest($request);

		$this->assertEquals("/app2/apiv2/pos/orders/1234-56789",$request->url,"Url should have been reformatted");
		$this->assertEquals("1.88",$request->data['tip_amt'],"A parameter of tip_amt should ahve been set");
	}
    
/*	function testRequestDataFormatBADXML()
    {
    	$xml_body = "<response><status>success</status><orders><order><order_id>123456</order_id></order></orders><response>";
    	$request = new Request();
    	$request->body = $xml_body;
    	$request->mimetype = 'Applicationxml';
    	try {
    		$request->_parseRequestBody();
    		$message = "all is groovy";
    	} catch (Exception $e) {
    		$message = $e->getMessage();
    	}
    	$this->assertEquals("String could not be parsed as XML", $message);
    	
    }
 */       
    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    RequestTest::main();
}

?>