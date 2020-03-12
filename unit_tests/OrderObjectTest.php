<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class OrderObjectTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $ids;
    var $order_resource;

	function setUp()
	{
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->ids = $_SERVER['unit_test_ids'];
		
	}
	
	function tearDown() 
	{
		//delete your instance
		$_SERVER['STAMP'] = $this->stamp;
		unset($this->ids);
        setContext("com.splickit.worldhq");
    }

    function testTaxRound()
    {
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $merchant_resource = createNewTestMerchant($menu_id);
        $tax_resource = Resource::find(new TaxAdapter(),null,array(TONIC_FIND_BY_METADATA=>array("merchant_id"=>$merchant_resource->merchant_id)));
        $tax_resource->rate = 9.6;
        $tax_resource->save();

        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_resource->merchant_id,'pickup','sumdumnote',4);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());

        $complete_order = CompleteOrder::staticGetCompleteOrder($checkout_resource->ucid);
        $items = $complete_order['order_details'];
        $order_detail_resource = Resource::find(new OrderDetailAdapter(),$items[0]['order_detail_id']);
        $this->assertEquals(.144,$items[0]['item_tax'],'should have the 3 decimal place tax');

        $this->assertEquals(.58,$complete_order['total_tax_amt'],"should have the correct tax amount from the 4 items");

        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_resource->merchant_id,1.00,$t);
        $this->assertNull($order_resource->error);

        $this->assertEquals(.58,$order_resource->total_tax_amt,"should have the correct tax amount from the 4 items");
    }

    function placeOrder()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $merchant_id = $this->ids['merchant_id'];
        $order_data = OrderAdapter::staticGetSimpleOrderArrayByMerchantId($merchant_id,'pickup','the note',3);
        $order_resource = placeOrderFromOrderData($order_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertNull($order_resource->error);
        $this->order_resource = $order_resource;
        return $order_resource;
    }

    function testGetItemInfo()
    {
        $order_resource = $this->placeOrder();
        $order = new Order($order_resource->order_id);
        $order_item_info = $order->getOrderItemInfo();
        $this->assertCount(3,$order_item_info,"There should be 3 rows");
        $this->assertEquals(3,$order->getNumberOfEntreLevelItems());
    }

    function testSkipHours()
    {
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $merchant_id = $this->ids['merchant_id'];
        $order_data = OrderAdapter::getSimpleCartArrayByMerchantId($merchant_id,'pickup','the note',3);
        $order_data['items'][0]['note'] = 'skip hours';

        $url = '/app2/apiv2/cart';
        $request = createRequestObject($url,'post',json_encode($order_data),'application/json');
        $place_order_controller = new PlaceOrderController($mt, $user, $request);
        $cart_resource = $place_order_controller->processV2Request();
        $this->assertNull($cart_resource->error);

        $order = new Order($cart_resource->ucid);
        $this->assertTrue($order->skipHours(),"should skip hours because of note on item");

    }

    static function setUpBeforeClass()
    {
    	$_SERVER['request_time1'] = microtime(true);
    	$tz = date_default_timezone_get();
    	$_SERVER['starting_tz'] = $tz;
    	date_default_timezone_set(getProperty("default_server_timezone"));
    	ini_set('max_execution_time',300);
        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;

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
    	attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
    	$ids['merchant_id'] = $merchant_resource->merchant_id;
    	
    	$user_resource = createNewUser(array("flags"=>"1C20000001"));
    	$ids['user_id'] = $user_resource->user_id;
    	
    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;
    }
    
	static function tearDownAfterClass()
    {
        SplickitCache::flushAll();         $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->rollback();
        date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    OrderObjectTest::main();
}

?>