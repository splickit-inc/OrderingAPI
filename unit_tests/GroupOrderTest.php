<?php
ini_set('max_execution_time', 300);
$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class GroupOrderTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
	var $user;
	var $merchant_id;
	var $merchant;
	var $users;
	var $ids;

	function setUp()
	{
		// we dont want to call to inspirepay
        setContext("com.splickit.pitapit");
        $_SERVER['SKIN']['show_notes_fields'] = true;
		$_SERVER['HTTP_NO_CC_CALL'] = 'true';
		$_SERVER['HTTP_X_SPLICKIT_CLIENT_VERSION'] = '100.0.0';
		$this->stamp = $_SERVER['STAMP'];
		$_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
		$this->users = $_SERVER['users'];
		$this->ids =  $_SERVER['unit_test_ids'];
		$user_id = $_SERVER['unit_test_ids']['user_id'];
		$user_resource = SplickitController::getResourceFromId($user_id, 'User');
		$this->user = $user_resource->getDataFieldsReally();
  	
		$this->merchant_id = $_SERVER['unit_test_ids']['merchant_id'];
  	    $this->merchant = SplickitController::getResourceFromId($this->merchant_id, 'Merchant');
  	    setProperty('do_not_call_out_to_aws','true');
	}
	
	function tearDown() {
		$_SERVER['STAMP'] = $this->stamp;
		setProperty('do_not_call_out_to_aws','true');
  }

    function createGroupOrder($user)
    {
        $request = new Request();
        $request->data = array("merchant_id"=>$this->merchant_id,"note"=>$note,"merchant_menu_type"=>'Pickup',"participant_emails"=>$emails);
        $request->url = "app2/apiv2/grouporders";
        $request->method = "POST";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $resource = $group_order_controller->processV2Request();
        $this->assertNull($resource->error);
        $group_order_token = $resource->group_order_token;
        $this->assertNotNull($group_order_token);
        return $group_order_token;
    }

    function createDummyGroupOrderResource()
    {
        $go = array();
        $go['admin_user_id'] = 12345;
    }

    function testIsGropuOrderAdminRequestingUserTrue()
    {
        $go = array();
        $go['admin_user_id'] = $this->ids['user_id'];
        $user = logTestUserIn($this->ids['user_id']);
        $goc = new GroupOrderController($m,$user,$r,5);
        $this->assertTrue($goc->isRequestingUserTheAdminForThisGroupOrder($go),"should have found the user is the same for ARRAY");
        $go_resource = Resource::dummyfactory($go);
        $this->assertTrue($goc->isRequestingUserTheAdminForThisGroupOrder($go_resource),"should have found the user is the same for RESOURCE");
    }

    function testIsGropuOrderAdminRequestingUserFalse()
    {
        $go = array();
        $go['admin_user_id'] = 12345;
        $user = logTestUserIn($this->ids['user_id']);
        $goc = new GroupOrderController($m,$user,$r,5);
        $this->assertFalse($goc->isRequestingUserTheAdminForThisGroupOrder($go),"should have found the user is NOT the same for ARRAY");
        $go_resource = Resource::dummyfactory($go);
        $this->assertFalse($goc->isRequestingUserTheAdminForThisGroupOrder($go_resource),"should have found the user NOT is the same for RESOURCE");
    }

    function testGetGroupOrderStatus()
    {
        setContext('com.splickit.snarfs');
        $user_resource = createNewUserWithCC();
        $user = logTestUserResourceIn($user_resource);
        $group_order_token = $this->createGroupOrder($user);
        $request = new Request();
        $request->url = "app2/apiv2/grouporders/$group_order_token";
        $request->method = "GET";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $resource = $group_order_controller->processV2Request();
        $this->assertNull($resource->error);
        $data = $resource->data;
        $this->assertEquals('active',$resource->status,"Group order should have the active flag set to 'Y'");

    }

  function testIsGroupOrderValidSent()
  {
    $group_order_resource = Resource::dummyfactory(array("sent_ts"=>'2014-11-11 00:00:00',"expires_at"=>time()+100,"status"=>'sent'));
    $group_order_controller = new GroupOrderController($mt,$u,$r);
    $this->assertFalse($group_order_controller->isGroupOrderActive($group_order_resource));
    $this->assertEquals($group_order_controller->group_order_submitted_message,$group_order_controller->error_resource->error);
  }

  function testIsGroupOrderValidExpires()
  {
    $group_order_resource = Resource::dummyfactory(array("sent_ts"=>'0000-00-00 00:00:00',"expires_at"=>time()-100,"status"=>'active'));
    $group_order_controller = new GroupOrderController($mt,$u,$r);
    $this->assertFalse($group_order_controller->isGroupOrderActive($group_order_resource));
    $this->assertEquals($group_order_controller->group_order_expired_message,$group_order_controller->error_resource->error);
  }

  function testIsGroupOrderValidCancelled()
  {
    $group_order_resource = Resource::dummyfactory(array("sent_ts"=>'0000-00-00 00:00:00',"expires_at"=>time()+100,"status"=>'cancelled'));
    $group_order_controller = new GroupOrderController($mt,$u,$r);
    $this->assertFalse($group_order_controller->isGroupOrderActive($group_order_resource));
    $this->assertEquals($group_order_controller->group_order_cancelled_message,$group_order_controller->error_resource->error);
  }

  function testIsGroupOrderValidNotActive()
  {
    $group_order_resource = Resource::dummyfactory(array("sent_ts"=>'0000-00-00 00:00:00',"expires_at"=>time()+100,"status"=>'inactive'));
    $group_order_controller = new GroupOrderController($mt,$u,$r);
    $this->assertFalse($group_order_controller->isGroupOrderActive($group_order_resource));
    $this->assertEquals($group_order_controller->group_order_not_active_message,$group_order_controller->error_resource->error);
  }

  function testDoesMerchantParticipateInGroupOrderingById()
  {
  	$group_order_controller = new GroupOrderController($mt, $u, $r);
  	$this->assertTrue($group_order_controller->doesMerchantParticipateInGroupOrderingById($this->ids['merchant_id']),"should have returned a true for group ordering");
  }
    
  function testGetMerchantWithGroupOrderFlag()
  {
      setContext('com.splickit.pitapit');
  	$r = new Request();
  	$r->url = ''.$this->ids['merchant_id'];
  	$merchant_controller = new MerchantController($mt,$u, $r);
  	$merchant_resource = $merchant_controller->getMerchant();
  	$this->assertNull($merchant_resource->error,"should not have recieved an error getting merchant");
  	$this->assertTrue(1 == $merchant_resource->group_ordering_on,"should have a flag field for group ordering and it should be set to 1");
  }
  
  function testCreateAdminEmail() {
    setContext('com.splickit.pitapit');
    $user = logTestUserIn($this->users[1]);
    $r = new Request();
    $r->url = ''.$this->ids['merchant_id'];
    $r->data['merchant_id'] = $this->ids['merchant_id'];
    $mmha = new MerchantMessageHistoryAdapter();

    $group_order_controller = new GroupOrderController($mt, $user, $r);    
    $group_order_controller->createGroupOrder();
    
    $records = $mmha->getRecords(array("info" => "subject=Your PitaPit Online Group Order Has Been Started!;from=PitaPit Group Ordering;"));
    
    $this->assertEquals(1, count($records), "There should be one admin e-mail queued.");
    $records = Resource::findAll($mmha, '', array(TONIC_FIND_BY_SQL => "SELECT * FROM Merchant_Message_History")); 
    
    foreach($records as $record) {
      $mmha->delete($record);
    }
  }
  
  function testCreateGroupEmails()
  {
      setProperty('do_not_call_out_to_aws','false');
    setContext('com.splickit.pitapit');
  	$emails = "sumdumemail1@dummy.com,sumdumemail2@dummy.com,sumdumemail3@dummy.com";
  	$user = logTestUserIn($this->users[1]);
  	$full_name = $user['first_name'].' '.$user['last_name'];
  	$group_order_controller = new GroupOrderController($mt, $user, $r);
  	$token = createCode(10);
    $merchant_id = '12345';
    $merchant_menu_type = "delivery";
    
  	$group_order_controller->sendGroupOrderInfoAsEmails(array('group_order_token' => $token, 'participant_emails' => $emails, 'merchant_id' => $merchant_id, 'merchant_menu_type' => $merchant_menu_type));
  	$mmha = new MerchantMessageHistoryAdapter($mimetypes);  	
  	$records = $mmha->getRecords(array("info"=>"subject=Invitation To A ".ucwords(getSkinNameForContext())." Group Order;from=".ucwords(getSkinNameForContext())." Group Ordering;"));
  	$this->assertCount(3, $records);
  	$this->assertContains($token, $records[1]['message_text']);
    $link = "https://pitapit.splickit.com/merchants/$merchant_id?order_type=$merchant_menu_type&group_order_token=$token";
  	$this->assertContains($link, $records[1]['message_text'],"Should have found the link in the email");
  }
    function testGroupOrderInvitationTemplate()
    {
        setProperty('do_not_call_out_to_aws','false');
        setContext('com.splickit.pitapit');
        $user = logTestUserIn($this->users[1]);
        $group_order_controller = new GroupOrderController($mt, $user, $r);
        $token = createCode(10);
        $merchant_id = $_SERVER['unit_test_ids']['merchant_id'];
        $merchant_menu_type = "delivery";
        $notes = "Email notes";
        $link = $group_order_controller->generateGroupOrderLink($merchant_id, $token,$merchant_menu_type);
        $skin_external_identifier= strtolower($_SERVER['HTTP_X_SPLICKIT_CLIENT_ID']);
        $skin_name = strtolower(getSkinNameForContext());
        $merchant = MerchantAdapter::staticGetRecordByPrimaryKey($merchant_id, 'MerchantAdapter');

        $merchant_name = $merchant['display_name'];
        $merchant_lat=$merchant['lat'];
        $merchant_lng=$merchant['lng'];
        $css_url_file = "https://s3.amazonaws.com/com.splickit.products/".$skin_external_identifier."/web/css/production.".$skin_name.".css";
        $color= $group_order_controller->parse_css($css_url_file);
        $skin_button_background = $color[',body button,body .button'][0]['background-color'];
        $skin_button_foreground = $color[',body button,body .button'][1]['color'];
        $email_data = array(
            "button_background_color" => $skin_button_background,
            "button_foreground_color" =>$skin_button_foreground,
            "merchant_lat" =>$merchant_lat,
            "merchant_lng" =>$merchant_lng,
            "merchant_name"=>$merchant_name,
            "skin_external_identifier" =>$skin_external_identifier,
            "skin_name"=> $skin_name,
            "admin_full_name" => $user['first_name'] . ' ' . $user['last_name'],
            "admin_first_name" => $user['first_name'],
            "notes" => $notes,
            "link" => $link
        );
        $body = $group_order_controller->getGroupOrderEmailBody($email_data);
        $this->assertNotNull($body);
    }

  function testCreateGroupOrderWithDummyOrderRow()
  {
    $user_resource = createNewUser();
    $user = logTestUserResourceIn($user_resource);

    $request = new Request();
    $request->data = array("merchant_id"=>$this->merchant_id,"note"=>$note,"merchant_menu_type"=>'Pickup',"participant_emails"=>$emails);
    $request->url = "app2/apiv2/grouporders";
    $request->method = "POST";
    $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
    $resource = $group_order_controller->processV2Request();
    $group_order_token = $resource->group_order_token;
    $cart = CartsAdapter::staticGetRecordByPrimaryKey($group_order_token,"CartsAdapter");
    $this->assertNotNull($cart);
    $this->assertNotNull($cart['order_id'],"order_id should not be null");
    $this->assertTrue($cart['order_id'] > 0,"should have found and order id");
    $order_id = $cart['order_id'];
    $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
    $this->assertNotNull($complete_order);
    $this->assertEquals('R',$complete_order['order_type'],"Order type should have been defaulted to 'R'");
    $this->assertNotNull($resource->order_id,"Should have added the order_id to the returned group order resource");
  }

  function testCreateGroupOrderWithDeliveryLocation()
  {
    $user_resource = createNewUser();
    $user = logTestUserResourceIn($user_resource);

    $json = '{"user_id":"'.$user['user_id'].'","name":"","address1":"1045 Pine Street","address2":"","city":"Boulder","state":"CO","zip":"80302","phone_no":"1234567890","lat":40.019785,"lng":-105.282509}';
    $request = new Request();
    $request->body = $json;
    $request->mimetype = "Application/json";
    $request->_parseRequestBody();
    $request->method = 'POST';
    $request->url = "/users/".$user['uuid']."/userdeliverylocation";
    $user_controller = new UserController($mt, $user, $request,5);
    $response = $user_controller->processV2Request();
    $this->assertNull($response->error,"should not have gotten a delivery save error but did");
    $this->assertNotNull($response->user_addr_id);
    $user_address_id = $response->user_addr_id;
    $this->user_addr_id = $user_address_id;


    $go_data = array("merchant_id"=>$this->merchant_id,"note"=>$note,"merchant_menu_type"=>'Delivery',"participant_emails"=>$emails,"user_addr_id"=>$user_address_id);
    $request = createRequestObject("app2/apiv2/grouporders","POST",json_encode($go_data),'application/json');
    $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
    $resource = $group_order_controller->processV2Request();
    $group_order_token = $resource->group_order_token;
    $cart = CartsAdapter::staticGetRecordByPrimaryKey($group_order_token,"CartsAdapter");
    $order_id = $cart['order_id'];
    $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
    $this->assertEquals('D',$complete_order['order_type'],"shoudl have created a delivery dummy order row");
    $this->assertEquals($user_address_id,$complete_order['user_delivery_location_id'],"Should have the user addr id on the order");
    $this->assertEquals(5.55,$complete_order['delivery_amt'],"shoudl have a delivery price on the dummy order");
  }

  function testCreateGroupOrderWithDeliveryLocationBadUserAddress()
  {
    $user_resource = createNewUser();
    $user = logTestUserResourceIn($user_resource);

    $json = '{"user_id":"'.$user['user_id'].'","name":"","address1":"332 east 116th street","address2":"","city":"new york","state":"ny","zip":"10029","phone_no":"1234567890","lat":40.796202,"lng":-73.936635}';
    $request = new Request();
    $request->body = $json;
    $request->mimetype = "Application/json";
    $request->_parseRequestBody();
    $request->method = 'POST';
    $request->url = "/users/".$user['uuid']."/userdeliverylocation";
    $user_controller = new UserController($mt, $user, $request,5);
    $response = $user_controller->processV2Request();
    $this->assertNull($response->error,"should not have gotten a delivery save error but did");
    $this->assertNotNull($response->user_addr_id);
    $user_address_id = $response->user_addr_id;
    $this->user_addr_id = $user_address_id;

    $request = new Request();
    $request->data = array("merchant_id"=>$this->merchant_id,"note"=>$note,"merchant_menu_type"=>'Delivery',"participant_emails"=>$emails,"user_addr_id"=>$user_address_id);
    $request->url = "app2/apiv2/grouporders";
    $request->method = "POST";
    $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
    $resource = $group_order_controller->processV2Request();
    $this->assertNotNull($resource->error,"Should have gotten a group order save error because address is outside of range");
    $this->assertEquals("We're sorry, this delivery address appears to be outside of our delivery range.", $resource->error);
  }

  function testCreateGroupOrder()
  {
      setProperty('do_not_call_out_to_aws','false');
    setContext("com.splickit.snarfs");
  	$note = "sum dum note";
  	$emails = "sumdumemail1@dummy.com,sumdumemail2@dummy.com,sumdumemail3@dummy.com,sumdumemail4@dummy.com";
  	$user = logTestUserIn($this->users[2]);

    $json = '{"user_id":"'.$user['user_id'].'","name":"","address1":"1045 Pine Street","address2":"","city":"Boulder","state":"CO","zip":"80302","phone_no":"1234567890","lat":40.019785,"lng":-105.282509}';
    $request = new Request();
    $request->body = $json;
    $request->mimetype = "Application/json";
    $request->_parseRequestBody();
    $request->method = 'POST';
    $request->url = "/users/".$user['uuid']."/userdeliverylocation";
    $user_controller = new UserController($mt, $user, $request,5);

    $response = $user_controller->processV2Request();
    $this->assertNull($response->error,"should not have gotten a delivery save error but did");
    $this->assertNotNull($response->user_addr_id);
    $user_address_id = $response->user_addr_id;
    $this->user_addr_id = $user_address_id;

    $request = new Request();
  	$request->data = array("merchant_id"=>$this->merchant_id,"note"=>$note,"merchant_menu_type"=>'Delivery',"participant_emails"=>$emails,"user_addr_id"=>$user_address_id);
  	$request->url = "app2/apiv2/grouporders";
  	$request->method = "POST"; 
  	$group_order_controller = new GroupOrderController($mt, $user, $request, 5);
  	$resource = $group_order_controller->processV2Request();
    $this->assertTrue($resource->user_addr_id > 1,"Should have gotten a user_addr_id on the group order object");

  	$this->assertNotNull($resource);
  	$this->assertNull($resource->error);
  	$this->assertNotNull($resource->group_order_token);
  	$this->assertTrue($resource->group_order_id > 1000,"shouljd have a group order id");
  	$this->assertEquals($user['user_id'], $resource->admin_user_id);
  	$this->assertTrue($resource->expires_at > (time()+(47*60*60)),"Should have an expiration timestamp that is greater then 47 hours from now");
  	$this->assertTrue($resource->expires_at < (time()+(49*60*60)),"Should have an expiration timestamp that is less then 49 hours from now");
  	$group_order_adapter = new GroupOrderAdapter($mimetypes);
  	$group_order_record = $group_order_adapter->getRecordFromPrimaryKey($resource->group_order_id);
  	$this->assertEquals($notes, $group_order_record['notes']);
  	$this->assertEquals('Delivery', $group_order_record['merchant_menu_type']);
  	$this->assertEquals($emails, $group_order_record['participant_emails']);
    $base_order_data = OrderAdapter::staticGetRecordByPrimaryKey($resource->order_id, 'OrderAdapter');
    $this->assertEquals('D',$base_order_data['order_type']);
  	return $resource->group_order_token;
  }

    /**
     * @depends testCreateGroupOrder
     */
    function testAnonymousUserCantDelete($group_order_token)
    {
        setContext("com.splickit.snarfs");
        $request = new Request();
        $request->url = "app2/apiv2/grouporders/$group_order_token";
        $request->method = "DELETE";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $resource = $group_order_controller->processV2request();
        $this->assertEquals(403,$resource->http_code, "non-admin user of group order cannot delete. 403.");
        $this->assertEquals("Unauthorized. You are not authorized to perform this action.", $resource->error, "A URL that isn't matched should have an error message set.");
    }



    /**
   * @depends testCreateGroupOrder
   */
  function testMakeSureCartCreatedWithSameTokenId($group_order_token)
  {
      setContext("com.splickit.snarfs");
  	$carts_adapter = new CartsAdapter($mimetypes);
  	$cart = $carts_adapter->getRecordFromPrimaryKey($group_order_token);
  	$this->assertNotNull($cart);
  	$this->assertEquals($group_order_token, $cart['ucid']);
    $this->assertEquals($this->users[2],$cart['user_id']);
    $this->assertEquals("sum dum note",$cart['note'],"Notes field should have been set");
  }
  
  /**
   * @depends testCreateGroupOrder
   */
  function testGroupOrderTokenOnUserSession($group_order_token)
  {
      setContext("com.splickit.snarfs");
  	$user = logTestUserIn($this->users[2]);
  	$user_resource = SplickitController::getResourceFromId($this->users[2], "User");
  	$user_session_controller = new UsersessionController($mt, $user, $r, 5);
	  $user_session_resource = $user_session_controller->getUserSession($user_resource);
  	$this->assertEquals("$group_order_token",$user_session_resource->group_order_token,"there should have been a group order token on the user session");
  }
  
  /**
   * @depends testCreateGroupOrder
   */
  function testValidateEmail($group_order_token)
  {
      setContext("com.splickit.snarfs");
  	$mmha = new MerchantMessageHistoryAdapter($mimetypes);
  	$user = logTestUserIn($this->users[2]);
  	$full_name = $user['first_name'].' '.$user['last_name'];  	  	
  	$records = $mmha->getRecords(array("info"=>"subject=Invitation To A Snarfs Group Order;from=Snarfs Group Ordering;"));
  	$this->assertCount(4, $records,"There should have been 4 emails created"); // JSB, 1/29/2015 - we should clean up the mmha records after these tests. This inflated count is confusing and dependent on other tests. 

    $link = "https://snarfs.splickit.com/merchants/".$this->merchant_id."?order_type=delivery&group_order_token=$group_order_token";
    $this->assertContains($link, $records[0]['message_text'],"Should have found the link in the email");
  }

  /**
   * @depends testCreateGroupOrder
   */
  function testAddToGroupOrderUsingCart($group_order_token)
  {
      setContext("com.splickit.snarfs");
  	$user_resource = createNewUserWithCC();
  	$user_resource->first_name = 'Rob';
  	$user_resource->last_name = 'Zombie';
  	$user_resource->save();
  	$user = logTestUserResourceIn($user_resource);
  	
  	$order_data = OrderAdapter::getSimpleCartArrayByMerchantId($this->ids['merchant_id']);
    $cart_note = "sum dum cart note";
    $order_data['note'] = $cart_note;

  	$json_encoded_data = json_encode($order_data); 
  	$request = new Request();
  	$request->url = '/app2/apiv2/cart';
  	$request->method = "post";
  	$request->body = $json_encoded_data;
  	$request->mimetype = 'application/json';
  	$request->_parseRequestBody();    	
  	$place_order_controller = new PlaceOrderController($mt, $user, $request);
  	$cart_resource = $place_order_controller->processV2Request();
  	$this->assertNotNull($cart_resource,"should have gotten a cart resource back");
    // validate note was added
    $submitted_cart_record = CartsAdapter::staticGetRecord(array("ucid"=>$cart_resource->ucid),'CartsAdapter');
    $order_id = $submitted_cart_record['order_id'];
    $order_record = OrderAdapter::staticGetRecordByPrimaryKey($order_id,'Order');
    $this->assertEquals($cart_note,$order_record['note'],"note should ahve been set on the cart order record");

    $cart_record = CartsAdapter::staticGetRecordByPrimaryKey("$group_order_token",'CartsAdapter');
    $order_id = $cart_record['order_id'];
    $bod = CompleteOrder::getBaseOrderData($order_id,$m);
  	// now add it to the group order
  	$data_for_cart_add_to_group_order['cart_ucid'] = $cart_resource->ucid;
      $request = createRequestObject("app2/apiv2/grouporders/$group_order_token","POST",json_encode($data_for_cart_add_to_group_order),'application/json');
  	$group_order_controller = new GroupOrderController($mt, $user, $request, 5);
  	$god_resource = $group_order_controller->processV2request();
  	$this->assertNotNull($god_resource,"should have a good reponse from add cart to group order");
  	$this->assertTrue(is_a($god_resource, 'Resource','should have gotten a resource back'));
  	$this->assertNull($god_resource->error,"should not have gotten an error");
  	$this->assertTrue($god_resource->group_order_detail_id > 100);
  	$group_order_detail_record = GroupOrderDetailAdapter::staticGetRecordByPrimaryKey($god_resource->group_order_detail_id, 'GroupOrderDetailAdapter');
  	$this->assertContains("Rob Z. - $cart_note", $group_order_detail_record['order_json']);

  	// now verify that the cart is set to G
  	$old_cart_record = CartsAdapter::staticGetRecordByPrimaryKey($submitted_cart_record['ucid'], 'Carts');
  	$this->assertEquals('G', $old_cart_record['status']);

    $cart_record = CartsAdapter::staticGetRecordByPrimaryKey("$group_order_token", 'Carts');
    $order_id = $cart_record['order_id'];
    $base_group_order_data = CompleteOrder::getBaseOrderData($order_id, $m);
    $this->assertEquals('D',$base_group_order_data['order_type'],"Order should be a delivery order");
    $this->assertEquals($this->user_addr_id,$base_group_order_data['user_addr_id']);
  	return $group_order_token;
  }

    /**
     * @depends testCreateGroupOrder
     */
    function testGetGroupOrderForSubmit($group_order_token)
    {
        setContext("com.splickit.snarfs");
        $group_order_record = GroupOrderAdapter::staticGetRecord(array("group_order_token"=>$group_order_token), 'GroupOrderAdapter');
        $user_id = $group_order_record['admin_user_id'];
        $user = UserAdapter::staticGetRecordByPrimaryKey($user_id, 'UserAdapter');

        $order_record = OrderAdapter::staticGetRecord(array("ucid"=>$group_order_token),'OrderAdapter');
        $complete_order = CompleteOrder::staticGetCompleteOrder($order_record['order_id'],$m);

        $request = new Request();
        $request->url = "app2/apiv2/grouporders/$group_order_token";
        $request->method = "GET";
        $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
        $go_resource = $group_order_controller->processV2request();

        $this->assertNotNull($go_resource->group_order_token,"should have been a group order toekn on the response");
        $this->assertNotNull($go_resource->order_summary);
        $this->assertCount(1,$go_resource->order_summary['cart_items'],"there should be one item in the group order");

    }


  /**
   * @depends testCreateGroupOrder
   */
  function testGroupOrderTokenOnUserSessionExpiredToken($group_order_token)
  {
      setContext("com.splickit.snarfs");
  	$group_order_adapter = new GroupOrderAdapter($mimetypes);
  	$group_order_resource = $group_order_adapter->getExactResourceFromData(array("group_order_token"=>$group_order_token));
  	$group_order_resource->expires_at = getTimeStampSecondsFromNow(-100);
  	$group_order_resource->save();
  	$user = logTestUserIn($this->users[2]);
  	$user_resource = SplickitController::getResourceFromId($this->users[2], "User");
  	$user_session_controller = new UsersessionController($mt, $user, $r, 5);
		$user_session_resource = $user_session_controller->getUserSession($user_resource);
		$this->assertFalse(isset($user_session_resource->group_order_token),"should not have found a group order token, since its expired");
		return $group_order_token;
  }
  
  /**
   * @depends testGroupOrderTokenOnUserSessionExpiredToken
   */
  function testGetGroupOrderExpired($group_order_token)
  {
  	$user = logTestUserIn($this->users[2]);
  	$request = new Request();
	  $request->url = "app2/apiv2/grouporders/$group_order_token";
  	$request->method = "GET"; 
  	$group_order_controller = new GroupOrderController($mt, $user, $request, 5);
  	$grouporder_resource = $group_order_controller->processV2request();
  	$this->assertNotNull($grouporder_resource->error);
  	$this->assertEquals($group_order_controller->group_order_expired_message,$grouporder_resource->error);
      $this->assertEquals(422,$grouporder_resource->http_code);
  }
  
  /**
   * @depends testGroupOrderTokenOnUserSessionExpiredToken
   */
  function testAddToGroupOrderExpired($group_order_token)
  {
  	$order_adapter = new OrderAdapter($mimetypes);
  	
  	$user = logTestUserIn($this->users[1]);
  	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->merchant_id,'pickup','skip hours');
  	$request = new Request();
  	$request->data = $order_data;
	  $request->url = "app2/apiv2/grouporders/$group_order_token";
  	$request->method = "POST"; 
  	$group_order_controller = new GroupOrderController($mt, $user, $request, 5);
  	$god_resource = $group_order_controller->processV2request();
  	$this->assertNotNull($god_resource->error,"should have gotten an error");
  	$this->assertEquals($group_order_controller->group_order_expired_message,$god_resource->error);
      $this->assertEquals(422,$god_resource->http_code);
  }



  /**
   * @depends testCreateGroupOrder
   */
  function testCancelGroupOrder($group_order_token)
  {
      setContext("com.splickit.snarfs");
      $user = logTestUserIn($this->users[2]);
    $group_order_adapter = new GroupOrderAdapter($mimetypes);
    $group_order_resource = $group_order_adapter->getExactResourceFromData(array("group_order_token"=>$group_order_token));
    $group_order_resource->expires_at = getTimeStampSecondsFromNow(10000);
    $group_order_resource->save();
    $request = new Request();
    $request->data = $order_data;
    $request->url = "app2/apiv2/grouporders/$group_order_token";
    $request->method = "DELETE";
    $group_order_controller = new GroupOrderController($mt, getLoggedInUser(), $request, 5);
    $go_resource = $group_order_controller->processV2request();
    $this->assertNull($go_resource->error,"should not have gotten an error");
    $group_order_record = GroupOrderAdapter::staticGetRecord(array("group_order_token"=>$group_order_token),'GroupOrderAdapter');
    $this->assertEquals('cancelled',$group_order_record['status'],"Group order should show as cancelled");
    $cart_record = CartsAdapter::staticGetRecordByPrimaryKey($group_order_token,'Carts');
    $this->assertEquals('C',$cart_record['status'],"Cart should show as cancelled");
    return $group_order_token;
  }

  /**
   * @depends testCancelGroupOrder
   */
  function testAddToCancelledGroupOrder($group_order_token)
  {
    $order_adapter = new OrderAdapter($mimetypes);

    $user = logTestUserIn($this->users[1]);
    $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($this->merchant_id,'pickup','skip hours');
    $request = new Request();
    $request->data = $order_data;
    $request->url = "app2/apiv2/grouporders/$group_order_token";
    $request->method = "POST";
    $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
    $grouporder_resource = $group_order_controller->processV2request();
    $this->assertNotNull($grouporder_resource->error,"should have gotten an error");
    $this->assertEquals($group_order_controller->group_order_cancelled_message,$grouporder_resource->error);
    $this->assertEquals(422,$grouporder_resource->http_code);
  }

  /**
   * @depends testCancelGroupOrder
   */
  function testGetCancelledGroupOrder($group_order_token)
  {
    $user = logTestUserIn($this->users[2]);
    $request = new Request();
    $request->url = "app2/apiv2/grouporders/$group_order_token";
    $request->method = "GET";
    $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
    $grouporder_resource = $group_order_controller->processV2request();
    $this->assertNotNull($grouporder_resource->error);
    $this->assertEquals($group_order_controller->group_order_cancelled_message,$grouporder_resource->error);
    $this->assertEquals(422,$grouporder_resource->http_code);
  }

  /**
   * @depends testCancelGroupOrder
   */
  function testSubmitCancelledGroupOrder($group_order_token)
  {
    $cart_record = CartsAdapter::staticGetRecordByPrimaryKey($group_order_token, 'Carts');
    $user = logTestUserIn($cart_record['user_id']);
    $user_id = $user['user_id'];

    $order_data['merchant_id'] = $this->merchant_id;
    $order_data['note'] = "the new cart note";
    $order_data['user_id'] = $user_id;
    $order_data['cart_ucid'] = $cart_record['ucid'];
    $order_data['tip'] = (rand(100, 1000))/100;

    $order_resource = placeOrderFromOrderData($order_data, getTodayTwelveNoonTimeStampDenver());
    $this->assertNotNull($order_resource->error);
    $this->assertEquals("Sorry, this cart is no longer active and cannot be submitted.",$order_resource->error);
    $this->assertEquals(422,$order_resource->http_code);
  }

  function testCreateGroupOrderWithAutoSendTimeNOTNOTNOT()
  {
      $user_resource = createNewUser(array('flags'=>'1C20000001','first_name'=>'bob','last_name'=>'roberts'));
  	$merchant_id = $this->merchant_id;
  	$user = logTestUserResourceIn($user_resource);
  	$request = new Request();
  	$order_data['merchant_id'] = $merchant_id;
  	//$order_data['submit_at_ts'] = time() + 300;
      $order_data['notes'] = "skip hours";
  	$request->data = $order_data;
	  $request->url = "app2/apiv2/grouporders";
  	$request->method = "POST"; 
  	$group_order_controller = new GroupOrderController($mt, $user, $request, 5);

  	//$resource = $group_order_controller->processV2Request();
//  	$this->assertNotNull($resource->error);
//  	$this->assertEquals("Sorry. You must choose a time that is more than 10 minutes from now for auto submit.", $resource->error);
  	
  	//$order_data['submit_at_ts'] = getTomorrowTwelveNoonTimeStampDenver() + 1000;
  	//$request->data = $order_data;
  	//$group_order_controller = new GroupOrderController($mt, $user, $request, 5);
  	$group_order_resource = $group_order_controller->processV2Request();
   	$this->assertNotNull($group_order_resource);
  	$this->assertTrue($group_order_resource->group_order_id > 1000);
  	$this->assertEquals($user['user_id'], $group_order_resource->admin_user_id);
  	$group_order_token = $group_order_resource->group_order_token;
  	$this->assertNotNull($group_order_token);
  	
  	// now check the activity
//  	$activity_id = $group_order_resource->auto_send_activity_id;
//  	$this->assertTrue($activity_id>1000,"should have found a valid activity id");
//
//  	$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
//  	$activity_resource = Resource::find($activity_history_adapter,"".$activity_id);
//  	$this->assertNotNull($activity_resource);

    $carts_adapter = new CartsAdapter($mimetypes);
    $cart = $carts_adapter->getRecordFromPrimaryKey($group_order_token);
    $this->assertEquals('Y',$cart['status']);

    return $group_order_resource;
  }

  /**
   * @depends testCreateGroupOrderWithAutoSendTimeNOTNOTNOT
   */
  function testAddToGroupOrderBadItemId($group_order_resource)
  {
    $merchant_id = $this->merchant_id;
    $group_order_token = $group_order_resource->group_order_token;

    $order_adapter = new OrderAdapter($mimetypes);
    $user = logTestUserIn($this->users[1]);
    $order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','skip hours');
    $order_data['items'][0]['item_id'] = 9999945;
    $request = new Request();
    $request->data = $order_data;
    $request->url = "app2/apiv2/grouporders/$group_order_token";
    $request->method = "POST";
    $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
    $god_resource = $group_order_controller->processV2request();
    $this->assertNotNull($god_resource->error,"should have gotten an error since the item id was bad");
    $this->assertEquals(422,$god_resource->http_code);
  }
  
  /**
   * @depends testCreateGroupOrderWithAutoSendTimeNOTNOTNOT
   */
  function testAddToGroupOrder($group_order_resource)
  {
  	$merchant_id = $this->merchant_id;
  	$group_order_token = $group_order_resource->group_order_token;
  	$group_order_activity_id = $group_order_resource->auto_send_activity_id;  	
  	$order_adapter = new OrderAdapter($mimetypes);
  	
  	$user = logTestUserIn($this->users[1]);
  	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','skip hours');
  	$request = new Request();
  	$request->data = $order_data;
    $request->url = "app2/apiv2/grouporders/$group_order_token";
  	$request->method = "POST"; 
  	$group_order_controller = new GroupOrderController($mt, $user, $request, 5);
  	$god_resource = $group_order_controller->processV2request();
  	$this->assertNull($god_resource->error,"should not have gotten an error");
  	$this->assertTrue($god_resource->group_order_detail_id > 100);
  	$group_order_detail_record = GroupOrderDetailAdapter::staticGetRecordByPrimaryKey($god_resource->group_order_detail_id, 'GroupOrderDetailAdapter');
  	$this->assertContains('Adam Z.', $group_order_detail_record['order_json']);
  	
  	$user = logTestUserIn($this->users[2]);
  	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','skip hours');
  	$request = new Request();
  	$request->data = $order_data;
	  $request->url = "app2/apiv2/grouporders/$group_order_token";
  	$request->method = "POST"; 
  	$group_order_controller = new GroupOrderController($mt, $user, $request, 5);
  	$god_resource = $group_order_controller->processV2request();
  	$this->assertNull($god_resource->error,"should not have gotten an error");
  	$this->assertTrue($god_resource->group_order_detail_id > 100);
  	$group_order_detail_record = GroupOrderDetailAdapter::staticGetRecordByPrimaryKey($god_resource->group_order_detail_id, 'GroupOrderDetailAdapter');
  	$this->assertContains('Rob Z.', $group_order_detail_record['order_json']);
  	
  	$user = logTestUserIn($this->users[3]);
  	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','skip hours');
  	$request = new Request();
  	$request->data = $order_data;
	  $request->url = "app2/apiv2/grouporders/$group_order_token";
  	$request->method = "POST"; 
  	$group_order_controller = new GroupOrderController($mt, $user, $request, 5);
  	$god_resource = $group_order_controller->processV2request();

  	$this->assertNull($god_resource->error,"should not have gotten an error");
  	$this->assertTrue($god_resource->group_order_detail_id > 100);
  	$group_order_detail_record = GroupOrderDetailAdapter::staticGetRecordByPrimaryKey($god_resource->group_order_detail_id, 'GroupOrderDetailAdapter');
  	$this->assertContains('Ty Z.', $group_order_detail_record['order_json']);
    return $group_order_activity_id;
  }
  
//  /**
//   * @depends testAddToGroupOrder
//   */
//  function testSendGroupOrder($group_order_activity_id)
//  {
//  	$send_group_order_activity = SplickitActivity::findActivityResourceAndReturnActivityObjectByActivityId($group_order_activity_id);
//  	$this->assertNotNull($send_group_order_activity);
//  	$class_name = get_class($send_group_order_activity);
//  	$this->assertEquals("SendGroupOrderActivity",$class_name);
//  	$order_id = $send_group_order_activity->doit();
//  	$this->assertTrue($order_id > 1000);
//
//  	$complete_order = CompleteOrder::staticGetCompleteOrder($order_id, $mimetypes);
//  	$items = $complete_order['order_details'];
//  	$this->assertEquals(3, sizeof($items, $mode));
//  	$this->assertEquals('Adam Z.', $items[0]['note']);
//  	$this->assertEquals('Rob Z.', $items[1]['note']);
//  	$this->assertEquals('Ty Z.', $items[2]['note']);
//      $group_order_id = $send_group_order_activity->data['group_order_id'];
//      $group_order_record = getStaticRecord(array("group_order_id"=>$group_order_id),'GroupOrderAdapter');
//      $this->assertEquals('Submitted',$group_order_record['status']);
//
//  }
    
  function testCreateAndAddSamePost()
  {
  	$merchant_id = $this->merchant_id;
  	$user = logTestUserIn($this->users[4]);
  	
  	$order_adapter = new OrderAdapter($mimetypes);
  	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','skip hours');
  	$order_data['participant_emails'] = "email1@dummy.com,email2@dummy.com";
  	$order_data['merchant_menu_type'] = 'Pickup';
  	$order_data['notes'] = "Here is where we talk";
  	
  	$request = new Request();
  	$request->data = $order_data;
	  $request->url = "app2/apiv2/grouporders";
  	$request->method = "POST"; 
  	$group_order_controller = new GroupOrderController($mt, $user, $request, 5);
  	$go_resource = $group_order_controller->processV2request();
  	$this->assertNull($go_resource->error,"should not have gotten an error");
  	$this->assertNotNull($go_resource->group_order_token);
  	return $go_resource->group_order_token;
  }
  
  /**
   * @depends testCreateAndAddSamePost
   */
  function testAddToGroupOrderAlsoAddsToGroupOrderCart($group_order_token)
  {
      $group_order_record = getStaticRecord(array("group_order_token"=>$group_order_token),"GroupOrderAdapter");
      $user = getStaticRecord(array("user_id"=>$group_order_record['admin_user_id']),"UserAdapter");
      $request = createRequestObject("/apiv2/cart/$group_order_token","GET");
      $place_order_controller = new PlaceOrderController($mt, $user, $request);
      $cart_resource = $place_order_controller->processV2Request();
  	$items = $cart_resource->order_summary['cart_items'];
  	$this->assertCount(1,$items);  	
  }
  
  /**
   * @depends testCreateAndAddSamePost
   */
  function testGetGroupOrderAnonymous($group_order_token)
  {
  	$user = logTestUserIn($this->users[2]);
  	$request = new Request();
  	$request->url = "app2/apiv2/grouporders/$group_order_token";
  	$request->method = "GET"; 
  	$group_order_controller = new GroupOrderController($mt, $user, $request, 5);
  	$resource = $group_order_controller->processV2request();
  	$this->assertNull($resource->error);
    $this->assertEquals('active',$resource->status);
    $this->assertFalse(isset($resource->group_order_admin));
    $this->assertFalse(isset($resource->order_summary));
  	return $group_order_token;
  }
    
  /**
   * @depends testCreateAndAddSamePost
   */
  function testGetGroupOrder($group_order_token)
  {
  	$user = logTestUserIn($this->users[4]);
  	$request = new Request();
  	$request->url = "app2/apiv2/grouporders/$group_order_token";
  	$request->method = "GET"; 
  	$group_order_controller = new GroupOrderController($mt, $user, $request, 5);
  	$group_order_resource = $group_order_controller->processV2request();

  	$this->assertNull($group_order_resource->error);
    $this->assertNull($group_order_resource->items,"shouldn't have items on the get group order anymore");
  	$this->assertNotNull($group_order_resource->group_order_token);
  	$this->assertNotNull($group_order_resource->order_summary."should have found a cart order summary");
  	$order_summary = $group_order_resource->order_summary;
  	$this->assertCount(PlaceOrderController::ORDER_SUMMARY_SIZE, $order_summary);
  	$cart_items = $order_summary['cart_items'];
  	$this->assertCount(1, $cart_items);
  	$this->assertEquals("Here is where we talk", $group_order_resource->notes);
  	$emails = "email1@dummy.com,email2@dummy.com";
  	$this->assertEquals($emails, $group_order_resource->participant_emails);
  	$this->assertEquals('Pickup',$group_order_resource->merchant_menu_type);
  	$this->assertTrue($group_order_resource->expires_at > (time()+(23*60*60)),"should have an expiration time of at least 23 horus in the future");
  	return $group_order_token;
  }
    
  /**
   * @depends testCreateAndAddSamePost
   */
  function testAddToGroupOrderWrongMerchantId($group_order_token)
  {
  	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
  	$merchant_id = $merchant_resource->merchant_id;
  	
  	$user = logTestUserIn($this->users[3]);
  	$order_adapter = new OrderAdapter($mimetypes);
  	$order_data = $order_adapter->getSimpleOrderArrayByMerchantId($merchant_id,'pickup','skip hours');
  	$request = new Request();
  	$request->data = $order_data;
  	$request->url = "app2/apiv2/grouporders/$group_order_token";
  	$request->method = "POST"; 
  	$group_order_controller = new GroupOrderController($mt, $user, $request, 5);
  	$resource = $group_order_controller->processV2request();
  	$this->assertNotNull($resource->error);
  	$this->assertEquals(422, $resource->http_code);
  	$this->assertEquals("Sorry, something has gotten corrupted with this group order. You are submitting an order for the wrong merchant.", $resource->error);
  }
    
  /**
   * @depends testCreateAndAddSamePost
   */
  function testSubmitCartOrderCloseOutGroupOrder($group_order_token)
  {
  	$cart_record = CartsAdapter::staticGetRecordByPrimaryKey($group_order_token, 'Carts');
	  $user = logTestUserIn($cart_record['user_id']);
  	$user_id = $user['user_id'];
  	
  	$order_data['merchant_id'] = $this->merchant_id;
  	$order_data['note'] = "the new cart note";
  	$order_data['user_id'] = $user_id;
  	$order_data['cart_ucid'] = $cart_record['ucid'];
  	$order_data['tip'] = (rand(100, 1000))/100;
  	
  	$order_resource = placeOrderFromOrderData($order_data, getTodayTwelveNoonTimeStampDenver());
  	$this->assertNull($order_resource->error);
  	$order_id = $order_resource->order_id;
  	$this->assertTrue($order_id > 1000,"should have created a valid order id");
  	
  	$group_order_adapter = new GroupOrderAdapter($mimetypes);
  	$group_order = $group_order_adapter->getRecord(array("group_order_token"=>$group_order_token));
  	myerror_log("gropu order sent: ".$group_order['sent_ts']);
  	myerror_log("time: ".time());
  	$sent_time_to_stamp = strtotime($group_order['sent_ts']);
  	$this->assertTrue($sent_time_to_stamp > (time()-30),"sent time should have been updated");
  }

  function testEndpointNotBuilt() {
    $request = new Request();
    $request->url = "app2/apiv2/grouporders";
    $request->method = "GET";
    $group_order_controller = new GroupOrderController($mt, $user, $request, 5);
    $resource = $group_order_controller->processV2request();
    $this->assertEquals(404,$resource->http_code, "A URL that isn't matched should return a 404.");
    $this->assertEquals("GroupOrderController endpoint not built yet", $resource->error, "A URL that isn't matched should have an error message set.");
  }
    
  static function setUpBeforeClass()
  {
  	ini_set('max_execution_time',300);
      SplickitCache::flushAll();
      $db = DataBase::getInstance();
      $mysqli = $db->getConnection();
      $mysqli->begin_transaction(); ;
      setContext("com.splickit.pitapit");
  	$_SERVER['request_time1'] = microtime(true);    	
	  $menu_id = createTestMenuWithOneItem("Test Item 1");
  	$ids['menu_id'] = $menu_id;

    $merchant_resource = createNewTestMerchantDelivery($menu_id);
    $options[TONIC_FIND_BY_METADATA] = array("merchant_id"=>$merchant_resource->merchant_id);
    $mdpd_resource = Resource::find(new MerchantDeliveryPriceDistanceAdapter($m),'',$options);
    $mdpd_resource->price = 5.55;
    $mdpd_resource->save();
  	$merchant_resource->group_ordering_on = 1;
  	$merchant_resource->save();
  	$merchant_id = $merchant_resource->merchant_id;
  	$ids['merchant_id'] = $merchant_id;
  	
  	$user_resource = createNewUser(array('flags'=>'1C20000001','first_name'=>'adam','last_name'=>'zmopolis'));
  	$ids['user_id'] = $user_resource->user_id;
  	$users[1] = $user_resource->user_id;
  	$user_resource2 = createNewUser(array('flags'=>'1C20000001','first_name'=>'rob','last_name'=>'zmopolis'));
  	$user_resource3 = createNewUser(array('flags'=>'1C20000001','first_name'=>'ty','last_name'=>'zmopolis'));
  	$user_resource4 = createNewUser(array('flags'=>'1C20000001','first_name'=>'jason','last_name'=>'zmopolis'));
  	$users[2] = $user_resource2->user_id;
  	$users[3] = $user_resource3->user_id;
  	$users[4] = $user_resource4->user_id;
  	  	
  	$_SERVER['log_level'] = 5; 
  	$_SERVER['unit_test_ids'] = $ids;
  	$_SERVER['users'] = $users;  	
  }
    
  static function tearDownAfterClass() {
  	SplickitCache::flushAll();
  	$db = DataBase::getInstance();
  	$mysqli = $db->getConnection();
  	$mysqli->rollback();
  }
    
  static function main() {
	  $suite = new PHPUnit_Framework_TestSuite( __CLASS__);
		PHPUnit_TextUI_TestRunner::run( $suite);
 	}    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    GroupOrderTest::main();
}

?>