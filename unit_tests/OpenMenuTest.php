<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/functions.inc';

class OpenMenuTest extends PHPUnit_Framework_TestCase
{
	
	function setUp()
	{
		;//$this->abc = new MessageController($mt, $u, $r,5);	
	}
	
	function tearDown() 
	{
        ;// delete your instance
       // unset($this->abc);
    }
    
    function testOpenMenu()
    {
    	$this->assertTrue(true);
    }
    
 /*  
  
  	function testOpenMenuImort()
    {
    	$open_menu_status_adapter = new OpenMenuStatusAdapter($mimetypes);
    	$karm_kafe_id = '20f21d5e-15bb-11e0-b40e-0018512e6b26';
    	$karm_kafe_id = 'abe6bc06-e578-11e1-bc8f-00163eeae34c';
    	$data['open_menu_id'] = $karm_kafe_id;
    	$resource = Resource::find($open_menu_status_adapter,$karm_kafe_id,$options);
    	$resource->last_updated = '12345';
    	$resource->save();
    	$open_menu_status_adapter->openMenuImport($karm_kafe_id);
    }
      
/*    function testGetOpenMenuMenu()
    {
    	$menu = CompleteMenu::getCompleteMenu(102410,'Y',103450);
    	myerror_log("got the ementu");
    }
*/    
    
	/* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (false && !defined('PHPUnit_MAIN_METHOD')) {
    OpenMenuTest::main();
}
    
?>    