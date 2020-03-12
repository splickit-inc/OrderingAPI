<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/functions.inc';
require_once 'lib/utilities/unit_test_functions.inc';

class ZZDataCleanTest extends PHPUnit_Framework_TestCase
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

    function testIt()
    {
    	$this->assertTrue(true);
    }
    
//*    
  	function testDataClean()
    {
    	$merchant_adapter = new MerchantAdapter($mimetypes);
    	if ($records = $merchant_adapter->getRecords(array("name"=>"Unit Test Merchant")))
    	{
    		myerror_log("we have merchant records to delete! WHY?????");
	    	foreach ($records as $record)
	    	{
	    		$merchant_id = $record['merchant_id'];
	    		$sql = "call SMAWSP_ADMIN_DEL_MERCHANT($merchant_id)";
	    		$merchant_adapter->_query($sql);
	    	}
    	} else {
    		myerror_log("there are no records to delete");
    	}
    	
    	$menu_adapter = new MenuAdapter($mimetypes);
    	
    	if ($menu_records = $menu_adapter->getRecords(array("name"=>"Unit Test Menu")))
    	{
    		myerror_log("we have menu records to delete! WHY?????");
	    	foreach ($menu_records as $menu_record)
	    	{
	    		$menu_id = $menu_record['menu_id'];
	    		$sql = "call SMAWSP_ADMIN_DEL_MENU($menu_id)";
	    		$menu_adapter->_query($sql);
	    		$sql = "DELETE FROM Menu WHERE menu_id = $menu_id LIMIT 1";
	    		$menu_adapter->_query($sql);
	    	}
    	} else {
    		myerror_log("there are no menu records to delete");
    	}
    	return true;
    }
// */   
     
    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (false && !defined('PHPUnit_MAIN_METHOD')) {
    ZZDataCleanTest::main();
}

?>