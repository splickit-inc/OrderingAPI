<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class ComboV2Test extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;

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
    }

    function testPriceAdjustmentBlockOfCompleteOrder()
    {
        $ids = $this->ids;
        $merchant_id = $ids['merchant_id'];
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);
        $cart_data = OrderAdapter::getSuperSimpleCartArrayByMerchantId($merchant_id);
        $checkout_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $order_resource = placeOrderFromCheckoutResource($checkout_resource,$user,$merchant_id,0.00,$time);
        $order_id = $order_resource->order_id;
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,getM());
        $order_detail = $complete_order['order_details'][0];
        $this->assertNull($order_detail['order_detal_price_adjustments']);
        $order_detail_id = $order_detail['order_detail_id'];
        $odma = new OrderDetailModifierAdapter(getM());
        $records = $odma->getRecords(['order_detail_id'=>$order_detail_id]);
        $this->assertCount(3,$records,"It should have 3 modifier records. 1 added, and 2 holds");
        $this->assertCount(2,$order_detail['order_detail_hold_it_modifiers']);
        $this->assertCount(1,$order_detail['order_detail_complete_modifier_list_no_holds']);
        $sql = "INSERT INTO Order_Detail_Modifier (order_detail_id,modifier_item_id,modifier_group_id,mod_name,mod_print_name,modifier_type,hold_it,mod_quantity,mod_price,mod_total_price,created)
				VAlUES ($order_detail_id,0, 0,'price adjustment','Combo 12345','A','N',1,-.50,-.50,NOW())";
		$odma->_query($sql);

		$records = $odma->getRecords(['order_detail_id'=>$order_detail_id]);
		$this->assertCount(4,$records,"It should now have 4 modifier records");

        $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,getM());
        $order_detail = $complete_order['order_details'][0];
        $this->assertNotNull($order_detail['order_detail_price_adjustments'],"It should have a price adjust node");
        $this->assertCount(1,$order_detail['order_detail_price_adjustments']);


    }

    function testGetCartNoCombos()
    {
        $ids = $this->ids;
        $merchant_id = $ids['merchant_id'];
        $sides = $ids['side_modifier_group'];
        $toppings = $ids['toppping_modifier_group'];
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSuperSimpleCartArrayByMerchantId($merchant_id);
        //add crink
        $cart_data['items'][0]['mods'][] = ['mod_quantity'=>1,'modifier_item_id'=>$this->ids['drink_id']];

        $cart_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals(2.50,$cart_resource->order_amt);

        $cart_data = OrderAdapter::getSuperSimpleCartArrayByMerchantId($merchant_id);
        //add chips and coookies (NOT A COMBO)
        $cart_data['items'][0]['mods'][] = ['mod_quantity'=>1,'modifier_item_id'=>$this->ids['chip_id']];
        $cart_data['items'][0]['mods'][] = ['mod_quantity'=>1,'modifier_item_id'=>$this->ids['cookie_id']];

        $cart_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());
        $this->assertEquals(3.00,$cart_resource->order_amt);
        return true;
    }

    /**
     * @depends testGetCartNoCombos
     */
    function testGetComboBonusForOrderDetailId($value)
    {
        $ids = $this->ids;
        $merchant_id = $ids['merchant_id'];
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSuperSimpleCartArrayByMerchantId($merchant_id);
        //add combo
        $cart_data['items'][0]['mods'][] = ['mod_quantity'=>1,'modifier_item_id'=>$this->ids['drink_id']];
        $cart_data['items'][0]['mods'][] = ['mod_quantity'=>1,'modifier_item_id'=>$this->ids['cookie_id']];
        $cart_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());

        $complete_order = CompleteOrder::getCompleteOrderAsResource($cart_resource->oid_test_only);
        $order_detail = $complete_order->order_details[0];
        $carts_adapter = new CartsAdapter(getM());
        $carts_adapter->merchant_id = $merchant_id;
        $discounts = $carts_adapter->getComboBonusesForOrderDetailId($order_detail['order_detail_id']);
        $discount = $discounts[0];
        $this->assertEquals(.16,$discount['price_adjustment'],"It should have found a .15 discount since the difference in the price of the mods individualy (1.00) and the combo (.84) is .16");
    }

    /**
     * @depends testGetCartNoCombos
     */
    function testGetComboBonusForOrderDetailIdMultiValue($value)
    {
        $ids = $this->ids;
        $merchant_id = $ids['merchant_id'];
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSuperSimpleCartArrayByMerchantId($merchant_id);
        //add combo
        $cart_data['items'][0]['mods'][] = ['mod_quantity'=>2,'modifier_item_id'=>$this->ids['drink_id']];
        $cart_data['items'][0]['mods'][] = ['mod_quantity'=>2,'modifier_item_id'=>$this->ids['cookie_id']];
        $cart_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());

        $complete_order = CompleteOrder::getCompleteOrderAsResource($cart_resource->oid_test_only);
        $order_detail = $complete_order->order_details[0];
        $carts_adapter = new CartsAdapter(getM());
        $carts_adapter->merchant_id = $merchant_id;
        $discounts = $carts_adapter->getComboBonusesForOrderDetailId($order_detail['order_detail_id']);
        $this->assertCount(2,$discounts,"It should have 2 records");
        $discount = $discounts[1];
        $this->assertEquals(.16,$discount['price_adjustment'],"It should have found a .16 discount since the difference in the price of the mods individualy (1.00) and the combo (.84) is .16");
    }

    /**
     * @depends testGetCartNoCombos
     */
    function testGetComboBonusForOrderDetailIdMultiValuePartTwo($value)
    {
        $ids = $this->ids;
        $merchant_id = $ids['merchant_id'];
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSuperSimpleCartArrayByMerchantId($merchant_id);
        //add combo
        $cart_data['items'][0]['mods'][] = ['mod_quantity'=>2,'modifier_item_id'=>$this->ids['drink_id']];
        $cart_data['items'][0]['mods'][] = ['mod_quantity'=>1,'modifier_item_id'=>$this->ids['cookie_id']];
        $cart_data['items'][0]['mods'][] = ['mod_quantity'=>1,'modifier_item_id'=>$this->ids['chip_id']];
        $cart_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());

        $complete_order = CompleteOrder::getCompleteOrderAsResource($cart_resource->oid_test_only);
        $order_detail = $complete_order->order_details[0];
        $carts_adapter = new CartsAdapter(getM());
        $carts_adapter->merchant_id = $merchant_id;
        $discounts = $carts_adapter->getComboBonusesForOrderDetailId($order_detail['order_detail_id']);
        $this->assertCount(2,$discounts,"It should have 2 records");
        $total_discount = $discounts[0]['price_adjustment'] + $discounts[1]['price_adjustment'];
        $this->assertEquals(.42,$total_discount,"It should have found a total discount of .40");
    }

    /**
     * @depends testGetCartNoCombos
     */
    function testGetCartWithComboPriceAdjust($value)
    {
        $ids = $this->ids;
        $merchant_id = $ids['merchant_id'];
        $sides = $ids['side_modifier_group'];
        $toppings = $ids['toppping_modifier_group'];
        $user_resource = createNewUserWithCCNoCVV();
        $user = logTestUserResourceIn($user_resource);

        $cart_data = OrderAdapter::getSuperSimpleCartArrayByMerchantId($merchant_id);
        //add crink
        $cart_data['items'][0]['mods'][] = ['mod_quantity'=>1,'modifier_item_id'=>$this->ids['drink_id']];
        $cart_data['items'][0]['mods'][] = ['mod_quantity'=>1,'modifier_item_id'=>$this->ids['cookie_id']];
        $cart_resource = getCheckoutResourceFromOrderData($cart_data,getTomorrowTwelveNoonTimeStampDenver());

        $complete_order = CompleteOrder::getCompleteOrderAsResource($cart_resource->oid_test_only);
        $order_detail = $complete_order->order_details[0];
        $this->assertEquals(2.84,$cart_resource->order_amt);
        $this->assertEquals(.284,$complete_order->item_tax_amt);
        $this->assertEquals(.28,$complete_order->total_tax_amt);
        $this->assertEquals(3.12,$complete_order->grand_total);

    }

    static function setUpBeforeClass()
    {
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        ini_set('max_execution_time',300);
        SplickitCache::flushAll();
//        $db = DataBase::getInstance();
//        $mysqli = $db->getConnection();
//        $mysqli->begin_transaction(); ;

        $skin_resource = createWorldHqSkin();
        $ids['skin_id'] = $skin_resource->skin_id;

        //map it to a menu
        $menu_id = createTestMenuWithNnumberOfItems(5);
        $ids['menu_id'] = $menu_id;

        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 5);
        $modifier_group_resource->modifier_type = 'T';
        $modifier_group_resource->save();

        $modifier_group_resource2 = createModifierGroupWithNnumberOfItems($menu_id, 3);
        $modifier_group_resource2->modifier_type = 'S';
        $modifier_group_resource2->save();

        $modifier_group_id = $modifier_group_resource->modifier_group_id;
        $modifier_group_id2 = $modifier_group_resource2->modifier_group_id;
        $ids['toppping_modifier_group'] = $modifier_group_resource;
        $ids['side_modifier_group'] = $modifier_group_resource2;
        foreach ($modifier_group_resource2->modifier_items as $index=>&$modifier_item_resource) {
            if ($index == 0) {
                $drink_id = $modifier_item_resource->modifier_item_id;
                $modifier_item_resource->modifier_item_name = "Drink";
                $modifier_item_resource->modifier_item_print_name = "Drink";
                $modifier_item_resource->save();
            } else if ($index == 1) {
                $cookie_id = $modifier_item_resource->modifier_item_id;
                $modifier_item_resource->modifier_item_name = "Cookie";
                $modifier_item_resource->modifier_item_print_name = "Cookie";
                $modifier_item_resource->save();
            } else {
                $chip_id = $modifier_item_resource->modifier_item_id;
                $modifier_item_resource->modifier_item_name = "Chip";
                $modifier_item_resource->modifier_item_print_name = "Chip";
                $modifier_item_resource->save();
            }

        }
        $ids['drink_id'] = $drink_id;
        $ids['chip_id'] = $chip_id;
        $ids['cookie_id'] = $cookie_id;
        $merchant_resource = createNewTestMerchant($menu_id);
        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
        $merchant_id = $merchant_resource->merchant_id;
        $ids['merchant_id'] = $merchant_id;


        $plu = generateAlphaCode(10);
        $menu_combo_adapter = new MenuComboAdapter(getM());
        $sql = "INSERT INTO Menu_Combo VALUES (NULL,$menu_id,'Drink Cookie Combo','Drink Cookie Combo','$plu','Y',NOW(),NOW(),'N')";
        $menu_combo_adapter->_query($sql);
        $id = $menu_combo_adapter->_insertId();


        $sql = "INSERT INTO Menu_Combo_Association VALUES (NULL,$id,'modifier_item',$drink_id,'',NOW(),NOW(),'N')";
        $menu_combo_adapter->_query($sql);
        $sql = "INSERT INTO Menu_Combo_Association VALUES (NULL,$id,'modifier_item',$cookie_id,'',NOW(),NOW(),'N')";
        $menu_combo_adapter->_query($sql);

        $sql = "INSERT INTO Menu_Combo_Price VALUES(NULL,$id,'$plu',$merchant_id,0.84,'Y',NOW(),NOW(),'N')";
        $menu_combo_adapter->_query($sql);



        $plu = generateAlphaCode(10);
        $sql = "INSERT INTO Menu_Combo VALUES (NULL,$menu_id,'Drink Chip Combo','Drink Chip Combo','$plu','Y',NOW(),NOW(),'N')";
        $menu_combo_adapter->_query($sql);
        $id = $menu_combo_adapter->_insertId();


        $sql = "INSERT INTO Menu_Combo_Association VALUES (NULL,$id,'modifier_item',$drink_id,'',NOW(),NOW(),'N')";
        $menu_combo_adapter->_query($sql);
        $sql = "INSERT INTO Menu_Combo_Association VALUES (NULL,$id,'modifier_item',$chip_id,'',NOW(),NOW(),'N')";
        $menu_combo_adapter->_query($sql);

        $sql = "INSERT INTO Menu_Combo_Price VALUES(NULL,$id,'$plu',$merchant_id,0.74,'Y',NOW(),NOW(),'N')";
        $menu_combo_adapter->_query($sql);





        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', getM());
        foreach ($item_records as $item_record) {
            $imgm_resource = assignModifierGroupToItemWithFirstNAsComesWith($item_record['item_id'], $modifier_group_id, 2);
            $imgm_resource->priority = 100;
            $imgm_resource->save();
            $imgm_resource2 = assignModifierGroupToItemWithFirstNAsComesWith($item_record['item_id'], $modifier_group_id2, 0);
            $imgm_resource2->priority = 50;
            $imgm_resource2->save();
        }



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
    ComboV2Test::main();
}

?>