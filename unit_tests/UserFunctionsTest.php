<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class UserFunctionsTest extends PHPUnit_Framework_TestCase
{
  var $abc;
  var $user1_email;
  var $ids;
  var $user_adapter;

  function setUp()
  {
    $_SERVER['HTTP_NO_CC_CALL'] = 'true';
    $this->stamp = $_SERVER['STAMP'];
    $_SERVER['STAMP'] = __CLASS__ . '-' . $_SERVER['STAMP'];
    $this->ids = $_SERVER['unit_test_ids'];
    setProperty('do_not_call_out_to_aws', 'true');
    $this->user_adapter = new UserAdapter();
  }

  function tearDown()
  {
    //delete your instance
    $_SERVER['STAMP'] = $this->stamp;
    unset($this->ids);
    setProperty('do_not_call_out_to_aws', 'true');
  }

  function testValidateBirthday()
  {
    $user_adapter = new UserAdapter($m);
    $date_of_birth = "06/08/1983";
    $this->assertTrue($user_adapter->isValidBirthday($date_of_birth));
    $date_of_birth = "6/8/1983";
    $this->assertTrue($user_adapter->isValidBirthday($date_of_birth));
    $date_of_birth = "6/8";
    $this->assertTrue($user_adapter->isValidBirthday($date_of_birth));
  }

  function testCreateUserWithValidBirthdayWithMarketingEmail(){
    $user_resource = createNewUser(array("birthday" => "01/08/1993", "marketing_email_opt_in" => "1"));
    $this->assertNull($user_resource->error);
    $user_id = $user_resource->user_id;
    $user_adapter = new UserAdapter($m);
    $record = $user_adapter->getRecord(array('user_id' => $user_id));
    $this->assertEquals("01/08/1993", $record['birthday']);
  }

  function testCreateUserWithValidBirthdayToUser(){
    $user_resource = createNewUser(array("birthday" => "01/08/1992"));
    $this->assertNull($user_resource->error);
    $user_id = $user_resource->user_id;
    $user_adapter = new UserAdapter($m);
    $record = $user_adapter->getRecord(array('user_id' => $user_id));
    $this->assertEquals("01/08/1992", $record['birthday']);
  }

  function testCreateUserWithValidBirthdayForMoes(){
    setContext("com.splickit.moes");
    $user_resource = createNewUser(array("birthday" => "01/08", "marketing_email_opt_in" => "1", "zipcode"=>'80302'));
    $this->assertNull($user_resource->error);
    $user_id = $user_resource->user_id;

    //in User table should save adding the current year
    $user_adapter = new UserAdapter($m);
    $record = $user_adapter->getRecord(array('user_id' => $user_id));
    $this->assertEquals("01/08/2000", $record['birthday']);//should save adding current year

    //in User_Extra_Data should save only MM/DD
    $user_extra_data_adapter = new UserExtraDataAdapter($m);
    $record = $user_extra_data_adapter->getRecord(array('user_id' => $user_id));
    $this->assertEquals("01/08", $record['birthdate']);
  }

  function testCreateUserWithInvalidBirthday(){
    $user_resource = createNewUser(array("birthday" => "0698"));
    $this->assertNotNull($user_resource->error);
    $this->assertEquals(UserAdapter::BIRTHDAY_ERROR_MESSAGE, $user_resource->error);

    $user_resource = createNewUser(array("birthday" => "08/989"));
    $this->assertNotNull($user_resource->error);
    $this->assertEquals(UserAdapter::BIRTHDAY_ERROR_MESSAGE, $user_resource->error);

    $user_resource = createNewUser(array("birthday" => "string"));
    $this->assertNotNull($user_resource->error);
    $this->assertEquals(UserAdapter::BIRTHDAY_ERROR_MESSAGE, $user_resource->error);
  }
  
  function testUpdateUserWithLongPHoneCheckFormat()
  {
      $user_resource = createNewUserWithCCNoCVV();
      $user = logTestUserResourceIn($user_resource);
      $data['contact_no'] = '13033334444';
      $request = createRequestObject('/apiv2/users/'.$user_resource->uuid,'POST',json_encode($data));
      $user_controller = new UserController($m,$user,$request);
      $user_controller->processV2Request();

      $expected_user_contact_no = '303-333-4444';
      $user_resource = $user_resource->refreshResource();
      $this->assertEquals($expected_user_contact_no,$user_resource->contact_no);
  }
  
  function createUser($data)
  {
    $password = "password";
    $offset = rand();
    $email = "boogers" . $offset . "@boogers.com";


    $data['password'] = $password;
    $data['email'] = $email;
    $data['first_name'] = "test";
    $data['last_name'] = "user";
    $data['contact_no'] = "1234567890";
    $user = Resource::createByData($this->user_adapter, $data);

    return $user;
  }

    function testCreateUserDuplicateEmailLogicallyDeleted()
    {
        setContext("com.splickit.order");
        $user_resource = createNewUserWithCCNoCVV();
        $user_resource->logical_delete = 'Y';
        $user_resource->save();
        $email = $user_resource->email;

        $user = logTestUserIn(1);

        $data['email'] = $email;
        $data['first_name'] = "first";
        $data['last_name'] = "last";
        $data['password'] = "hereiam";
        $data['contact_no'] = "1234567890";
        $request = createRequestObject("/apiv2/users","POST",json_encode($data),'application/json');
        $user_controller = new UserController($mt, $user, $request, 5);
        $response = $user_controller->processV2Request();
        $this->assertNotNull($response->error);
        $expected_error = "Sorry, there was an error. Please contact support.";
        $this->assertEquals($expected_error, $response->error);
    }

  function testShouldSendLiveOrder()
  {
    $ua = $this->user_adapter;
    $user = $this->createUser(array("ordering_action" => "send"));
    $this->assertTrue($ua->shouldSendLiveOrder($user), "A user with ordering_action = 'send' should send a live order. Value was " . $user->ordering_action);

    $user = $this->createUser(array("ordering_action" => "test"));
    $this->assertFalse($ua->shouldSendLiveOrder($user), "A user with ordering_action = 'test' should not send a live order. Value was " . $user->ordering_action);

    $user = $this->createUser(array("ordering_action" => "none"));
    $this->assertFalse($ua->shouldSendLiveOrder($user), "A user with ordering_action = 'none' should not send a live order. Value was " . $user->ordering_action);
  }

  function testShouldSendTestOrder()
  {
    $ua = $this->user_adapter;
    $user = $this->createUser(array("ordering_action" => "send"));
    $this->assertFalse($ua->shouldSendTestOrder($user), "A user with ordering_action = 'send' should not send a test order. Value was " . $user->ordering_action);

    $user = $this->createUser(array("ordering_action" => "test"));
    $this->assertTrue($ua->shouldSendTestOrder($user), "A user with ordering_action = 'test' should send a test order. Value was " . $user->ordering_action);

    $user = $this->createUser(array("ordering_action" => "none"));
    $this->assertFalse($ua->shouldSendTestOrder($user), "A user with ordering_action = 'none' should not send a test order. Value was " . $user->ordering_action);
  }

  function testShouldSendEmail()
  {
    $ua = $this->user_adapter;
    $user = $this->createUser(array("send_emails" => true));
    $this->assertTrue($ua->shouldSendEmail($user), "A user with send_emails = true should send emails. Value was " . $user->send_emails);

    $user = $this->createUser(array("send_emails" => false));
    $this->assertFalse($ua->shouldSendEmail($user), "A user with send_emails = false should not send emails. Value was " . $user->send_emails);
  }

  function testShouldSeeInactiveMerchants()
  {
    $ua = $this->user_adapter;
    $user = $this->createUser(array("see_inactive_merchants" => true));
    $this->assertTrue($ua->shouldSeeInactiveMerchants($user), "A user with see_inactive_merchants = true should see inactive merchants. Value was " . $user->see_inactive_merchants);

    $user = $this->createUser(array("see_inactive_merchants" => false));
    $this->assertFalse($ua->shouldSeeInactiveMerchants($user), "A user with send_emails = false should not see inactive merchants. Value was " . $user->see_inactive_merchants);
  }

  function testShouldSeeDemoMerchants()
  {
    $ua = $this->user_adapter;
    $user = $this->createUser(array("see_demo_merchants" => true));
    $this->assertTrue($ua->shouldSeeDemoMerchants($user), "A user with see_demo_merchants = true should see demo merchants (merchant_id < 1000). Value was " . $user->see_demo_merchants);

    $user = $this->createUser(array("see_demo_merchants" => false));
    $this->assertFalse($ua->shouldSeeDemoMerchants($user), "A user with see_demo_merchants = false should not see demo merchants (merchant_id < 1000). Value was " . $user->see_demo_merchants);
  }

  function testShouldBypassCache()
  {
    $ua = $this->user_adapter;
    $user = $this->createUser(array("caching_action" => "bypass"));
    $this->assertTrue($ua->shouldBypassCache($user), "A user with caching_action = 'bypass' should bypass the cache. Value was " . $user->caching_action);

    $user = $this->createUser(array("caching_action" => "refresh"));
    $this->assertTrue($ua->shouldBypassCache($user), "A user with caching_action = 'refresh' should bypass the cache. Value was " . $user->caching_action);

    $user = $this->createUser(array("caching_action" => "respect"));
    $this->assertFalse($ua->shouldBypassCache($user), "A user with caching_action = 'respect' should not bypass the cache. Value was " . $user->caching_action);
  }

  function testShouldRefreshCache()
  {
    $ua = $this->user_adapter;
    $user = $this->createUser(array("caching_action" => "bypass"));
    $this->assertFalse($ua->shouldRefreshCache($user), "A user with caching_action = 'bypass' should not refresh the cache. Value was " . $user->caching_action);

    $user = $this->createUser(array("caching_action" => "refresh"));
    $this->assertTrue($ua->shouldRefreshCache($user), "A user with caching_action = 'refresh' should refresh the cache. Value was " . $user->caching_action);

    $user = $this->createUser(array("caching_action" => "respect"));
    $this->assertFalse($ua->shouldRefreshCache($user), "A user with caching_action = 'respect' should not refresh the cache. Value was " . $user->caching_action);
  }

  function testShouldRespectCache()
  {
    $ua = $this->user_adapter;
    $user = $this->createUser(array("caching_action" => "bypass"));
    $this->assertFalse($ua->shouldRespectCache($user), "A user with caching_action = 'bypass' should not respect the cache. Value was " . $user->caching_action);

    $user = $this->createUser(array("caching_action" => "refresh"));
    $this->assertFalse($ua->shouldRespectCache($user), "A user with caching_action = 'refresh' should not respect the cache. Value was " . $user->caching_action);

    $user = $this->createUser(array("caching_action" => "respect"));
    $this->assertTrue($ua->shouldRespectCache($user), "A user with caching_action = 'respect' should respect the cache. Value was " . $user->caching_action);
  }

  function testDoNotSaveBlankPassword()
  {
    $user_resource = createNewUser();
    $initial_password = $user_resource->password;
    $user_resource->password = " ";
    $user_resource->first_name = "sumdumname";
    $user_adapter = new UserAdapter($mimetypes);
    $this->assertTrue($user_adapter->update($user_resource));

    $user = $user_adapter->getRecordFromPrimaryKey($user_resource->user_id);
    $this->assertEquals($initial_password, $user['password'], "password should not have changed since a blank was passed in");

  }

  function testUpdateEmailBadEmail()
  {
    $user_resource = createNewUser();
    $user_resource->email = 'ghdafoi489udasjk';
    $user_adapter = new UserAdapter($mimetypes);
    $this->assertFalse($user_adapter->update($user_resource));
    $this->assertEquals('Sorry but the email address you entered is not valid', $user_resource->error);
  }

  function testStripLoyaltyNumber()
  {
    $loyalty_number = '(303)-884-4983';
    $user_adapter = new UserAdapter($mimetypes);
    $this->assertEquals('3038844983', $user_adapter->stripLoyaltyNumber($loyalty_number));
  }

  function testFailUserInsertBadPassword()
  {
    $user_resource = createNewUser(array("password" => "12345678901234567890"));
    $this->assertEquals("Sorry but the password you entered is too long, maximum is 16 characters", $user_resource->error);
  }

  function testFailUserInsertBadEmail()
  {
    $user_resource = createNewUser(array("email" => "12345678901234567890"));
    $this->assertEquals("Sorry but the email address you entered is not valid", $user_resource->error);
  }

  function testFailUserInsertNoPassword()
  {
    $user_data = createNewUserDataFields();
    unset($user_data['password']);
    $user_adapter = new UserAdapter($mimetypes);
    $user_resource = Resource::factory($user_adapter, $user_data);
    $this->assertFalse($user_adapter->insert($user_resource));
    $this->assertEquals('No Password Submitted on User Creation!', $user_resource->error);
  }

  function testSetSkinOnLetterResource()
  {
    setContext('com.splickit.order');
    $letter_resource = new Resource($adapter, $data);
    $user_controller = new UserController($mt, $u, $r);
    $user_controller->setSkinStuffOnLetterResource($letter_resource);
    $this->assertEquals(getSkinForContext(), $letter_resource->skin);
    $this->assertEquals("Splickit", $letter_resource->skin_name);
  }

  function testSendWelcomeNoLetter()
  {
    $user_controller = new UserController($mt, $u, $r);
    $this->assertFalse($user_controller->sendWelcomeLetterToUser(null));
    $this->assertFalse($user_controller->sendWelcomeLetterToUser("somesillyfilename.txt"));
  }

  function testGetRefundError()
  {
    $user_controller = new UserController($mt, $u, $r);
    $error = "some refund error";
    $user_controller->refund_error = $error;
    $this->assertEquals($error, $user_controller->getRefundError());
  }

  function testNoMatchingUserId()
  {
    $this->assertFalse(UserController::getUserResourceFromUserId(123456789));
  }

  function testFlagsPositionValue()
  {
    $flags = '9C28000000';
    $this->assertTrue(doesFlagPositionNEqualX($flags, 4, '8'));
    $this->assertTrue(doesFlagPositionNEqualX($flags, 3, '2'));
    $this->assertTrue(doesFlagPositionNEqualX($flags, 2, 'C'));
    $this->assertTrue(doesFlagPositionNEqualX($flags, 1, '9'));
  }

  function testSetFlagPosition()
  {
    $user_adapter = new UserAdapter($mt);
    $flags = "1000000Y01";
    $flags = $user_adapter->setFlagPosition($flags, 4, '1');
    $this->assertEquals("1001000Y01", $flags);
  }

  function testSetFlagPositionForStoredCC()
  {
    $user_adapter = new UserAdapter($mt);
    $flags = "1000000X01";
    $flags = $user_adapter->setFlagsForSavedCreditCard($flags);
    $this->assertEquals("1C21000X01", $flags);
  }

  function testForgotPasswordBadEmail()
  {
    setContext('com.splickit.order');
    $request = new Request();
    $request->data = array("email" => "sumdumemail@email.com");
    $user_controller = new UserController($mt, $u, $request);
    $resource = $user_controller->forgotPassword();
    $this->assertEquals('Sorry, that email is not registered with us. Please check your entry.', $resource->error);
    $this->assertEquals(404, $resource->http_code);
    $this->assertNull($resource->token);
  }

  function testForgotPassword()
  {
    setContext('com.splickit.worldhq');
    $user_resource = createNewUser();
    $request = new Request();
    $request->data = array("email" => $user_resource->email);


    $user_controller = new UserController($mt, $u, $request);

    $resource = $user_controller->forgotPassword();
    $this->assertEquals('We have processed your request. Please check your email for reset instructions.', $resource->user_message);
    $this->assertNotNull($resource->token);
    $token1 = $resource->token;

    $message_record = MerchantMessageHistoryAdapter::staticGetRecord(array("message_format" => 'E', "message_delivery_addr" => $user_resource->email), "MerchantMessageHistoryAdapter");
    $this->assertContains("Here is a link for you to reset your password:", $message_record['message_text']);
    $this->assertContains("https://sumdum.domain.com/reset_password/" . $resource->token, $message_record['message_text']);

    // call it again to make sure the token gets reset
    $resource2 = $user_controller->forgotPassword();
    $this->assertEquals($token1, $resource2->token, "tokens should be equal");
  }

  function testForgotPasswordNoEmail()
  {
    setContext('com.splickit.order');
    $request = new Request();
    $user_controller = new UserController($mt, $u, $request);
    $resource = $user_controller->forgotPassword();
    $this->assertEquals(422, $resource->http_code);
    $this->assertEquals("Error! No email was passed", $resource->error);
  }

  function testUnlockAccountNotLocked()
  {
    $user_resource = createNewUser();
    $user = $user_resource->getDataFieldsReally();
    $user_controller = new UserController($mt, $user, $r);
    $this->assertFalse($user_controller->unlockAccount(), "Should have returned false because the users account is not locked");
  }

  function testUnlockAccount()
  {
    $user_resource = createNewUser(array("flags" => "2000000001"));
    $user = $user_resource->getDataFieldsReally();
    $user_controller = new UserController($mt, $user, $r);
    $this->assertTrue($user_controller->unlockAccount(), "Should have returned true because the users account got unlocked");
    $user_adapter = new UserAdapter($mimetypes);
    $new_user_resource = $user_adapter->getUserResourceFromId($user['user_id']);
    $this->assertEquals("1000000001", $new_user_resource->flags);
  }

  function testUnlockAccountNotBlacklisted()
  {
    $user_resource = createNewUser(array('email' => "amanda08duh@yahoo.com", 'logical_delete' => 'N'));
    $request = new Request();
    $request->data = array("email" => $user_resource->email);
    $user = $user_resource->getDataFieldsReally();
    $user_controller = new UserController($mt, $user, $request);
    $this->assertFalse($user_controller->undoBlacklisted(), "Should have returned false because the users account is not blacklisted");
  }

  function testUnlockAccountBlacklisted()
  {
    $user_resource = createNewBlacklistedUserNew(array('email' => "avnlilly@ymail.com"));
    $request = new Request();
    $request->data = array("email" => $user_resource->email);
    $user = $user_resource->getDataFieldsReally();
    $user_controller = new UserController($mt, $user, $request);
    $this->assertTrue($user_controller->undoBlacklisted());
    $user_adapter = new UserAdapter($mimetypes);
    $new_user_resource = $user_adapter->getUserResourceFromId($user['user_id']);
    $this->assertEquals('1000000001', $new_user_resource->flags);
    $this->assertEquals("avnlilly@ymail.com", $new_user_resource->email);
    $this->isNull($user_controller->getRefundError());
  }

  function testValidateDeliveryAddress()
  {
    $uc = new UserController();
    $data = array('address1' => '1000 Duran Drive', 'city' => 'Wolfsberg', 'state' => 'ME', 'zip' => '04402-0909', 'phone' => '207-555-5555');
    $this->assertNull($uc->validateDeliveryAddr($data), "No errors should be returned for an array containing address1, city, state, zip and phone keys.");

    $data = array('city' => 'Wolfsberg', 'state' => 'ME', 'zip' => '04402', 'phone' => '207-555-5555');
    $this->assertEquals($uc->validateDeliveryAddr($data), array('error' => 'Address cannot be null', 'error_code' => '11'), "No errors should be returned for an array containing address1, city, state, zip and phone keys.");

    $data = array('address1' => '1000 Duran Drive', 'state' => 'ME', 'zip' => '04402', 'phone' => '207-555-5555');
    $this->assertEquals($uc->validateDeliveryAddr($data), array('error' => 'City cannot be null', 'error_code' => '11'), "An error should be returned unless the array contains address1, city, state, zip and phone keys.");

    $data = array('address1' => '1000 Duran Drive', 'city' => 'Wolfsberg', 'zip' => '04402', 'phone' => '207-555-5555');
    $this->assertEquals($uc->validateDeliveryAddr($data), array('error' => 'State cannot be null', 'error_code' => '11'), "An error should be returned unless the array contains address1, city, state, zip and phone keys.");

    $data = array('address1' => '1000 Duran Drive', 'city' => 'Wolfsberg', 'state' => 'ME', 'phone' => '207-555-5555');
    $this->assertEquals($uc->validateDeliveryAddr($data), array('error' => 'Zip cannot be null', 'error_code' => '11'), "An error should be returned unless the array contains address1, city, state, zip and phone keys.");

    $data = array('address1' => '1000 Duran Drive', 'city' => 'Wolfsberg', 'state' => 'ME', 'zip' => '2', 'phone' => '207-555-5555');
    $this->assertEquals($uc->validateDeliveryAddr($data), array('error' => 'Zip must start with 5 consecutive digits', 'error_code' => '11'), "An error should be returned unless the array contains address1, city, state, zip and phone keys.");

    $data = array('address1' => '1000 Duran Drive', 'city' => 'Wolfsberg', 'state' => 'ME', 'zip' => 'ALPHABET', 'phone' => '207-555-5555');
    $this->assertEquals($uc->validateDeliveryAddr($data), array('error' => 'Zip must start with 5 consecutive digits', 'error_code' => '11'), "An error should be returned unless the array contains address1, city, state, zip and phone keys.");

    $data = array('address1' => '1000 Duran Drive', 'city' => 'Wolfsberg', 'state' => 'ME', 'zip' => 'ALPHABET04402', 'phone' => '207-555-5555');
    $this->assertEquals($uc->validateDeliveryAddr($data), array('error' => 'Zip must start with 5 consecutive digits', 'error_code' => '11'), "An error should be returned unless the array contains address1, city, state, zip and phone keys.");

    $data = array('address1' => '1000 Duran Drive', 'city' => 'Wolfsberg', 'state' => 'ME', 'zip' => '04402');
    $this->assertEquals($uc->validateDeliveryAddr($data), array('error' => 'You must enter a phone number for delivery', 'error_code' => '11'), "An error should be returned unless the array contains address1, city, state, zip and phone keys.");

    $data = array('address1' => '1000 Duran Drive', 'city' => 'Wolfsberg', 'state' => 'ME', 'zip' => '04402', 'phone' => '55-boogers');
    $this->assertEquals($uc->validateDeliveryAddr($data), array('error' => 'The phone number you entered is not valid', 'error_code' => '11'), "An error should be returned unless the array contains address1, city, state, zip and phone keys.");

    $data = array('address1' => '1000 Duran Drive', 'city' => 'Wolfsberg', 'state' => 'XX', 'zip' => '04402', 'phone' => '55-boogers');
    $this->assertEquals($uc->validateDeliveryAddr($data), array('error' => '\'xx\' is not a recognized state abbreviation.', 'error_code' => '11'), "An error should be returned unless the array contains address1, city, state, zip and phone keys.");
  }

  function testDeleteUserDeliveryAddressLocation()
  {
    $user_resource = createNewUser();
    $user = logTestUserResourceIn($user_resource);
    $json = '{"user_id":"' . $user['user_id'] . '","name":"","address1":"11 Riverside Drive","address2":"","city":"new york","state":"ny","zip":"12345","phone_no":"1234567890","lat":40.796202,"lng":-73.936635}';
    $request = new Request();
    $request->body = $json;
    $request->mimetype = "Application/json";
    $request->_parseRequestBody();
    $request->method = 'POST';
    $request->url = "/setuserdelivery";
    $user_controller = new UserController($mt, $user, $request, 5);
    $response = $user_controller->setDeliveryAddr();
    $this->assertNull($response->error, "should not have gotten a delivery save error but did");
    $user_address_id = $response->user_addr_id;

    $request = new Request();
    $request->mimetype = "Application/json";
    $json = '{"jsonVal":{}}';
    $request->url = "/deleteuserdelivery/$user_address_id";
    $user_controller = new UserController($mt, $user, $request, 5);
    $response = $user_controller->deleteDeliveryAddr();
    $this->assertNull($reponse->error);
    $this->assertEquals('success', $response->result);

  }

  function testSetCommunicationSettingWIthHAckedTHisSOrry()
  {
    $user_resource = createNewUser();
    $user = logTestUserResourceIn($user_resource);

    $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE_ID'] = 'hackedthissorry';

    $code = generateCode(50);
    $request = new Request();
    $request->data = array("token" => $code, "active" => 'Y', "messaging_type" => 'push');
    $user_controller = new UserController($mt, $user, $request, 5);
    $resource = $user_controller->setCommunication();
    $this->assertNotNull($resource->map_id);
    $this->assertEquals(substr($code, -20), $resource->device_id);
  }

  function testSetCommunicationRegularUser()
  {
    $user_resource = createNewUser();
    $user = logTestUserResourceIn($user_resource);

    $token = generateCode(20);
    $request = new Request();
    $request->method = 'POST';
    $request->data['messaging_type'] = 'Push';
    $request->data['active'] = 'Y';
    $request->data['token'] = $token;
    $user_controller = new UserController($mt, $user, $request, 5);
    $resource = $user_controller->setCommunication();
    $this->assertNull($resource->error);
    $this->assertTrue($resource->map_id > 1000);
    $this->assertEquals($token, $resource->token);
    $this->assertEquals($user['device_id'], $resource->device_id);
    $this->assertEquals('Push', $resource->messaging_type);
    $this->assertEquals('UnitTest', $resource->device_type);
  }

  function testSetCommunicationRegularUserGCM()
  {
    $user_resource = createNewUser();
    $user = logTestUserResourceIn($user_resource);

    $token = generateCode(20);
    $request = new Request();
    $request->method = 'POST';
    $request->data['messaging_type'] = 'Push';
    $request->data['active'] = 'Y';
    $request->data['token'] = $token;
    $request->data['gcm'] = true;
    $user_controller = new UserController($mt, $user, $request, 5);
    $resource = $user_controller->setCommunication();
    $this->assertNull($resource->error);
    $this->assertTrue($resource->map_id > 1000);
    $this->assertEquals($token, $resource->token);
    $this->assertEquals($user['device_id'], $resource->device_id);
    $this->assertEquals('Push', $resource->messaging_type);
    $this->assertEquals('gcm', $resource->device_type);
  }


  function testSetCommunicationAdminUser()
  {
    $user = logTestUserIn(101);

    $token = generateCode(20);
    $request = new Request();
    $request->method = 'POST';
    $request->data['messaging_type'] = 'Push';
    $request->data['active'] = 'Y';
    $request->data['token'] = $token;
    $user_controller = new UserController($mt, $user, $request, 5);
    $resource = $user_controller->setCommunication();
    $this->assertNull($resource->error);
    $this->assertNull($resource->map_id);
    $this->assertEquals('true', $resource->result);
  }

  function testDuplicateTempUserProblem()
  {
    setContext("com.splickit.order");
    $body = '{"password":"TlhKDMd8ni6M","device_id":"F71D850C-114C-484F-94A4-0C7092DAE865","first_name":"SpTemp","email":"F71D850C-114C-484F-94A4-0C7092DAE865@splickit.dum","last_name":"User"}';

    $user = logTestUserIn(1);
    $request = new Request();
    $request->method = "POST";
    $request->mimetype = 'Application/json';
    $request->body = $body;
    $user_controller = new UserController($mt, $user, $request, 5);
    $response = $user_controller->createUser();
    $this->assertNull($response->error);
    $this->assertTrue(is_a($response, 'Resource'));

    $user_controller2 = new UserController($mt, $user, $request, 5);
    $response2 = $user_controller2->createUser();
    $this->assertTrue(is_a($response2, 'Resource'));
    $this->assertNull($response2->error);
    $this->assertNull($response2->user_message);
    $expected_dont_show_message = "This account already exists, however, your password matched, so we logged in you anyway :)";
    $this->assertEquals($expected_dont_show_message, $response2->user_message_do_not_show);

  }

  function testValidateEmail()
  {
    $email = "ssssdddfff@gmail.com";
    $this->assertTrue($email == filter_var($email, FILTER_VALIDATE_EMAIL));
    $email = "sss_sdd_dfff@gmail.com";
    $this->assertTrue($email == filter_var($email, FILTER_VALIDATE_EMAIL));
    $email = "sss_sdd@dfff@gmail.com";
    $this->assertFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
    $email = "ssssdddfff@gmail.";
    $this->assertFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
    $email = "ssssdddfffgmail.com";
    $this->assertFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
    $email = "adf@gmailcom";
    $this->assertFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
    $email = "23456789";
    $this->assertFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
  }

  function testDoNotSendWelcomeLetterToTempUser()
  {
    setContext('com.splickit.moes');
    $user_resource = createNewUser();
    $this->assertFalse(isUserResourceATempUser($user_resource));
    $user_resource->first_name = 'Temp';
    $user_resource->last_name = 'User';
    $user_resource->email = "456-wert-2345-ert-agasfg@splickit.dum";
    $user_resource->save();

    $this->assertTrue(isUserResourceATempUser($user_resource));
    $user_controller = new UserController($mt, $u, $r, 5);
    $this->assertFalse($user_controller->sendWelcomeLetterToUserForContext($user_resource));
  }

  function testDoNotAllowTempUserToUpdateOrAddCreditCardInformation()
  {
    setContext('com.splickit.moes');
    $user_resource = createNewUser();
    $this->assertFalse(isUserResourceATempUser($user_resource));
    $user_resource->first_name = 'Temp';
    $user_resource->last_name = 'User';
    $code = generateCode(5);
    $user_resource->email = "456-wert-2345-ert-$code@splickit.dum";
    $user_resource->save();
    $user_id = $user_resource->user_id;

    $this->assertTrue(isUserResourceATempUser($user_resource));
    $user = logTestUserResourceIn($user_resource);

    $json_encoded_data = "{\"jsonVal\":{\"cc_exp_date\":\"12/2022\",\"cc_number\":\"4111111111111111\",\"cvv\":\"123\",\"zip\":\"12345\"}}";
    $request = new Request();
    $request->url = "/app2/phone/users/$user_id";
    $request->method = "post";
    $request->body = $json_encoded_data;
    $request->mimetype = 'Applicationjson';
    $request->_parseRequestBody();

    $user_controller = new UserController($mt, $user, $request, 5);
    // save cc
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $response_resource = $user_controller->updateUser();
    $this->assertNotNull($response_resource->error, 'Should have gotten a credit card error');
    $this->assertEquals("We're sorry, but your session has gotten corrupted. Please log out and start over. We apologize for the inconvenience.", $response_resource->error);

  }

  function testIsThisAnUpdateToARealUserFromATempUser()
  {
    $user_resource2 = createNewUser();
    $user_resource2->first_name = "SpTemp";
    $user_resource2->last_name = "User";
    $email = $user_resource2->email;
    $e = explode('@', $email);
    $temp_email = $e[0] . "@splickit.dum";
    $user_resource2->email = $temp_email;
    $result = $user_resource2->save();
    $this->assertTrue($result);

    $user2 = logTestUserIn($user_resource2->user_id);

    $new_email = 'mynewemail@dummy.com';
    $request = new Request();
    $request->method = "POST";
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $data['email'] = $new_email;
    $data['password'] = "somdumpassword";
    $data['first_name'] = "somedumguy";
    $data['last_name'] = "lastname";
    $request->data = $data;
    $user_controller = new UserController($mt, $user2, $request, 5);
    $result = $user_controller->isThisAnUpdateToARealUserFromATempUser($temp_email);
    $this->assertFalse($result);
    $result = $user_controller->isThisAnUpdateToARealUserFromATempUser($new_email);
    $this->assertTrue($result);
  }

  function testCreateUserWithCustomEmail()
  {
    setProperty('do_not_call_out_to_aws', 'false');
    setContext("com.splickit.snarfs");
    $merchant_resource = createNewTestMerchant();
    attachMerchantToSkin($merchant_resource->merchant_id, 5);
    $email = getCreateTestUserEmail();
    $user = logTestUserIn(1);
    $request = new Request();
    $request->method = "POST";
    $data['email'] = $email;
    $data['first_name'] = "first";
    $data['last_name'] = "last";
    $data['password'] = "hereiam";
    $data['contact_no'] = "1234567890";
    $request->data = $data;
    $_SERVER['HTTP_X_SPLICKIT_CLIENT_LATITUDE'] = 40.014881;
    $_SERVER['HTTP_X_SPLICKIT_CLIENT_LONGITUDE'] = -105.274330;
    $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'] = 'iphone';
    $user_controller = new UserController($mt, $user, $request, 5);
    $response = $user_controller->createUser();
    $this->assertNull($response->error);

    $mmha = new MerchantMessageHistoryAdapter($mimetypes);
    $record = $mmha->getRecord(array("message_delivery_addr" => $email, "message_format" => 'Ewel'));

    $this->assertNotNull($record, "should have found a welcome message in the db");
    $this->assertNotNull($record['message_text'], "welcome message should have message_text");

    $ucda = new UserCreationDataAdapter($mimetypes);
    $record = $ucda->getRecord(array("user_id" => $response->user_id));
    $this->assertEquals(5, $record['skin_id']);

  }



  function testNormalCreateUserDuplicateEmail()
  {
    setContext("com.splickit.order");
    $email = "mynewemail@email.com";
    $user_resource1 = createNewUser();
    $user_resource1->email = $email;
    $user_resource1->save();

    $user = logTestUserIn(1);
    $request = new Request();
    $request->method = "POST";
    $data['email'] = $email;
    $data['first_name'] = "first";
    $data['last_name'] = "last";
    $data['password'] = "hereiam";
    $data['contact_no'] = "1234567890";
    $request->data = $data;

    $user_controller = new UserController($mt, $user, $request, 5);
    $response = $user_controller->createUser();
    $this->assertNotNull($response->error);
    $expected_error = "Sorry, it appears this email address exists already with a different password. Please try logging in on the main screen.";
    $this->assertEquals($expected_error, $response->error);

    $data['password'] = "welcome";
    $request->data = $data;

    $user_controller2 = new UserController($mt, $user, $request, 5);
    $response = $user_controller2->createUser();
    $this->assertNull($response->error);
    $expected_message = "This account already exists, however, your password matched, so we logged in you anyway :)";
    $this->assertEquals($expected_message, $response->user_message);

  }

  function testUserUpdateToDuplicateEmailFromNonTempUser()
  {
    $user_resource1 = createNewUser();
    $email = $user_resource1->email;

    $user_resource2 = createNewUser();

    $user2 = logTestUserIn($user_resource2->user_id);

    $request = new Request();
    $data['email'] = $email;
    $data['password'] = "somdumpassword";
    $request->data = $data;

    $user_controller = new UserController($mt, $user2, $request, 5);
    $response = $user_controller->updateUser();
    $this->assertNotNull($response->error);
    $expected_error = "Sorry, it appears this email address exists already.";
    $this->assertEquals($expected_error, $response->error);

    $request = new Request();
    $data['email'] = $email;
    $data['password'] = "welcome";
    $request->data = $data;

    $user_controller2 = new UserController($mt, $user2, $request, 5);
    $response = $user_controller2->updateUser();
    $this->assertNotNull($response->error);
    $expected_error = "Sorry, it appears this email address exists already.";
    $this->assertEquals($expected_error, $response->error);

  }

  function testUserUpdateToDuplicateEmailFromTempUser()
  {
    //SpTemp User 5678@splickit.dum
    $email = "mynewemail@email.com";
    $user_resource1 = createNewUser();
    $user_resource1->email = $email;
    $user_resource1->save();

    $user_resource2 = createNewUser();
    $user_resource2->first_name = "SpTemp";
    $user_resource2->last_name = "User";
    $user_resource2->email = time() . "@splickit.dum";
    $result = $user_resource2->save();
    $this->assertTrue($result);

    $user2 = logTestUserIn($user_resource2->user_id);

    $request = new Request();
    $data['email'] = $email;
    $data['password'] = "somdumpassword";
    $request->data = $data;

    $user_controller = new UserController($mt, $user2, $request, 5);
    $response = $user_controller->updateUser();
    $this->assertNotNull($response->error);
    $expected_error = "Sorry, it appears this email address exists already with a different password. Please try logging in on the main screen.";
    $this->assertEquals($expected_error, $response->error);

    $request = new Request();
    $data['email'] = $email;
    $data['password'] = "welcome";
    $request->data = $data;

    $user_controller2 = new UserController($mt, $user2, $request, 5);
    $response = $user_controller2->updateUser();
    $this->assertNull($response->error);
    $expected_message = "This account already exists, however, your password matched, so we logged in you anyway :)";
    $this->assertEquals($expected_message, $response->user_message);

  }

  function testFormatCCto4numbers()
  {
    $expdate = '102016';
    $credit_card_functions = new CreditCardFunctions();
    $formatted = $credit_card_functions->formatExpDateTo4Numbers($expdate);
    $this->assertEquals('1016', $formatted);

    $expdate = '215';
    $formatted = $credit_card_functions->formatExpDateTo4Numbers($expdate);
    $this->assertEquals('0215', $formatted);

  }

  function testPasswordsTuff()
  {
    $login_adapter = new LoginAdapter($mimetypes);
    $password2 = 'qwHGT%^&U&&^$#$D^GJ)O:?><M_34567';
    $enc = Encrypter::Encrypt($password2);
    $this->assertTrue($login_adapter->verifyPasswordWithDbHash($password2, $enc));
//    	$dec = Encrypter::Decrypt($enc);
//   	$this->assertEquals($password2, $dec);

    $password = 'qwertyuiopdfghjkrtyuiofghjrtyuif';
    $enc = Encrypter::Encrypt($password);
    $this->assertTrue($login_adapter->verifyPasswordWithDbHash($password, $enc));
//    	$dec = Encrypter::Decrypt($enc);
//   	$this->assertEquals($password, $dec);

    $user_resource = createNewUser();
    $user = logTestUserIn($user_resource->user_id);
    $request = new Request();
    $request->data['password'] = $password;
    $user_controller = new UserController($mt, $user, $request, 5);
    $response = $user_controller->updateUser();
    $this->assertNull($response->error);

    $login_adapter = new LoginAdapter($mimetypes);
    $return = $login_adapter->authorize($user['email'], $password);
    $this->assertEquals($user['user_id'], $return->user_id);

    // now try password too long
    $request = new Request();
    $request->data['password'] = "qwertyuiopqwertyuiopqwertyuiopqwertyuiop";
    $user_controller = new UserController($mt, $user, $request, 5);
    $response = $user_controller->updateUser();
    $this->assertEquals('Sorry but the password you entered is too long, maximum is 32 characters', $response->error);

  }

//   function testCreateShorePointsAccount()
//    {
//    	setContext("com.splickit.jerseymikes");
//    	$_SERVER['REQUEST_METHOD'] = 'POST';
//    	$device_id = generateCode(10);
//		$new_user_data['device_id'] = $device_id;
//    	$user_resource = createNewUser($new_user_data);
//    	$user_id = $user_resource->user_id;
//		
//		$data['create_loyalty_account'] = 1;
//		$data['loyalty_phone_number'] = '14567';
//		
//		$request = new Request();
//    	$request->method = "post";
//    	$request->data = $data;
//    	$user['user_id'] = $user_resource->user_id;
//    	$user_controller = new UserController($mt, $user, $request,5);
//    	$user_resource = $user_controller->updateUser();
//    	$new_data_resource = Resource::find(new UserAdapter($mimetypes),''.$user_resource->user_id);
//    	$this->assertNotNull($user_resource->error,"should have gotten an error");
//		$this->assertEquals("The number provided was not a valid phone number.",$user_resource->error);		
//
//		$phone_number = rand(111111111, 999999999);
//		$data['loyalty_phone_number'] = '1'.$phone_number;
//		$request = new Request();
//    	$request->method = "post";
//    	$request->data = $data;
//    	$user['user_id'] = $user_resource->user_id;
//    	$user_controller = new UserController($mt, $user, $request,5);
//    	$user_resource = $user_controller->updateUser();
//    	$new_data_resource = Resource::find(new UserAdapter($mimetypes),''.$user_resource->user_id);
//		$this->assertNull($user_resource->error);
//		$created_loyalty_number = $user_resource->created_loyalty_number;
//		
//		$ubl_adapter = new UserBrandPointsMapAdapter($mimetypes);
//		$record = $ubl_adapter->getRecord(array("user_id"=>$user_id,"brand_id"=>326));
//		$this->assertNotNull($record);
//		$this->assertEquals($created_loyalty_number, $record['loyalty_number']);
//		
//    }
//	
//    function testChangeLoyaltyBadLoyaltyNumber()
//    {
//    	$_SERVER['REQUEST_METHOD'] = 'POST';
//    	setContext("com.splickit.jerseymikes");
//		$device_id = generateCode(10);
//		$new_user_data['device_id'] = $device_id;
//    	$ur = createNewUser($new_user_data);
//    	$user = logTestUserIn($ur->user_id);
//    	$data = array ('loyalty_number'=>'1234123412');
//    	$request = new Request();
//    	$request->method = "post";
//    	$request->data = $data;
//    	$user_controller = new UserController($mt, $user, $request,5);
//    	$user_resource = $user_controller->updateUser();
//    	$new_data_resource = Resource::find(new UserAdapter($mimetypes),''.$user_resource->user_id);
//    	//$this->assertFalse($new_data_resource->loyalty_number == '1234123412',"loyalty number should NOT have been set to 1234123412 but it was.");
//    	//$this->assertNotNull($user_resource->error);
//    	$user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
//    	$record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user['user_id']));
//    	$this->assertNull($record);
//    	
//    	// check that a row was added to the Brand_Loyalty_Fails table
//    	$blf_adapter = new BrandLoyaltyFailsAdapter($mimetypes);
//    	$record = $blf_adapter->getRecord(array("brand_id"=>326,"device_id"=>$device_id));
//    	$this->assertNotNull($record,"record should have been created in the brandloyaltyfails table");
//    	$this->assertEquals(1, $record['failed_attempts']);
//    	
//   		$data = array ('loyalty_number'=>'1234567890');
//    	$request = new Request();
//    	$request->data = $data;
//    	$user['user_id'] = $user_resource->user_id;
//    	$user_controller = new UserController($mt, $user, $request,5);
//    	$user_resource = $user_controller->updateUser();
//    	$new_data_resource = Resource::find(new UserAdapter($mimetypes),''.$user_resource->user_id);
//    	//$this->assertTrue($new_data_resource->loyalty_number == '1234567890',"loyalty number should have been set to 1234567890 but it was set to: ".$new_data_resource->loyalty_number);
//    	//$this->assertNull($user_resource->error);
//		$record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user['user_id']));
//    	$this->assertNotNull($record);
//		$this->assertEquals(326,$record['brand_id']);
//		$this->assertEquals("1234567890",$record['loyalty_number']);    	
//    }    
//
//    function testChangeLoyaltyBadLoyaltyNumber3TimeLocks()
//    {
//    	setContext("com.splickit.jerseymikes");
//    	$_SERVER['REQUEST_METHOD'] = 'POST';
//		$device_id = generateCode(10);
//		$new_user_data['device_id'] = $device_id;
//    	$ur = createNewUser($new_user_data);
//    	$user = logTestUserIn($ur->user_id);
//    	$data = array ('loyalty_number'=>'123412341234');
//    	$request = new Request();
//    	$request->data = $data;
//    	$request->method = "post";
//    	$user_controller = new UserController($mt, $user, $request,5);
//    	$user_resource = $user_controller->updateUser();
//    	$new_data_resource = Resource::find(new UserAdapter($mimetypes),''.$user_resource->user_id);
//    	$this->assertFalse($new_data_resource->loyalty_number == '123412341234',"loyalty number should NOT have been set to 123412341234 but it was.");
//    	$this->assertNotNull($user_resource->error);
//    	
//    	// check that a row was added to the Brand_Loyalty_Fails table
//    	$blf_adapter = new BrandLoyaltyFailsAdapter($mimetypes);
//    	$record = $blf_adapter->getRecord(array("brand_id"=>326,"device_id"=>$device_id));
//    	$this->assertNotNull($record,"record should have been created in the brandloyaltyfails table");
//    	$this->assertEquals(1, $record['failed_attempts']);
//    	
//    	$user_resource = $user_controller->updateUser();
//		$user_resource = $user_controller->updateUser();
//		$user_resource = $user_controller->updateUser();
//		$this->assertEquals($user_controller->getTooManyFailedLoyaltyAttemptsMessage(326), $user_resource->error);    	
//    	$record = $blf_adapter->getRecord(array("brand_id"=>326,"device_id"=>$device_id));
//    	$this->assertNotNull($record,"record should have found a record in the brandloyaltyfails table");
//    	$this->assertEquals(3, $record['failed_attempts']);
//    	
//    	// now try to update with a good one;
//   		$data = array ('loyalty_number'=>'1234567890');
//    	$request = new Request();
//    	$request->data = $data;
//    	$user['user_id'] = $user_resource->user_id;
//    	$user_controller = new UserController($mt, $user, $request,5);
//    	$user_resource = $user_controller->updateUser();
//    	$this->assertEquals($user_controller->getTooManyFailedLoyaltyAttemptsMessage(72), $user_resource->error);
//    	
//    	// now clear the lock
//    	$brand_loyalty_fails_adapter = new BrandLoyaltyFailsAdapter($mimetypes);
//    	$the_current_time = time()+3605;
//    	//$number_updated = $brand_loyalty_fails_adapter->unlockLoyaltyFails($the_current_time);
//    	//$this->assertTrue($number_updated > 0);
//    	
//    	$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
//    	$doit_ts = time()-2;
//		$info = 'object=BrandLoyaltyFailsAdapter;method=unlockLoyaltyFails;thefunctiondatastring='.$the_current_time;
//		$id = $activity_history_adapter->createActivity('ExecuteObjectFunction', $doit_ts, $info, $activity_text,3600);
//    	$aa_options[TONIC_FIND_BY_METADATA]['activity_id'] = $id;
//    	$activity = $activity_history_adapter->getNextActivityToDo($aha_options);
//		$this->assertNotNull($activity);
//		$this->assertEquals('ExecuteObjectFunctionActivity', get_class($activity));
//		$number_updated = $activity->doit();
//    	$this->assertTrue($number_updated > 0);
//    	
//   		// now try to update with a good one;
//   		$data = array ('loyalty_number'=>'1234567890');
//    	$request = new Request();
//    	$request->data = $data;
//    	$user['user_id'] = $user_resource->user_id;
//    	$user_controller = new UserController($mt, $user, $request,5);
//    	$user_resource = $user_controller->updateUser();
//    	$new_data_resource = Resource::find(new UserAdapter($mimetypes),''.$user_resource->user_id);
//    	$this->assertNull($user_resource->error);
//    	
//    	// old way of checking loyalty
//    	//$this->assertTrue($new_data_resource->loyalty_number == '1234567890',"loyalty number should have been set to 1234567890 but it was set to: ".$new_data_resource->loyalty_number);
//    	
//    	$user_brand_points_map_adapter = new UserBrandPointsMapAdapter($mimetypes);
//    	$record = $user_brand_points_map_adapter->getRecord(array("user_id"=>$user['user_id']));
//    	$this->assertNotNull($record);
//		$this->assertEquals(326,$record['brand_id']);
//		$this->assertEquals("1234567890",$record['loyalty_number']);    	
//    }
//    
//    function testCreateUserWithBadLoyalty()
//    {
//    	setContext("com.splickit.jerseymikes");
//    	$_SERVER['REQUEST_METHOD'] = 'POST';
//		
//    	$user_resource = createNewUser(array ('loyalty_number'=>'999999999'));
//    	$this->assertFalse($user_resource->_exists);
//    	$this->assertNotNull($user_resource->error);
//    	$this->assertEquals("Sorry but the loyalty number you entered, 999999999, is not valid", $user_resource->error);
//    	
//    	$user_data = createNewUserDataFields();
//    	$code = createUUID();
//		//i'm sure there's a better way to do this
//		$user_data['uuid'] = $code;
//		$user_data['skin_id'] = 72;
//		$user_data['skin_name'] = 'jersey mikes';
//		$user_data['device_type'] = 'unit_testing';
//		$user_data['app_version'] = '100.0.1';
//		$user_data['balance'] = 0.00;
//		$user_data['loyalty_number'] = '999999999';
//    	
//		$json_encoded = json_encode($user_data);
//		
//		$request = new Request();
//		$request->body = $json_encoded;
//		$request->method = 'POST';
//		$request->url = '/phone/users';
//		$request->mimetype = 'Application/json';
//		$user['user_id'] = 1;
//		$user_controller = new UserController($mt, $user, $request, 5);
//		$resource = $user_controller->createUser();
//		$this->assertEquals("Sorry but the loyalty number you entered, 999999999, is not valid",$resource->error);
//    }

  function testResetLastFourOnpasswordChange()
  {
    $user_resource = createNewUser();
    $user_id = $user_resource->user_id;
    $user_resource->flags = "1C20000001";
    $user_resource->last_four = "1234";
    $user_resource->save();
    $user = $user_resource->getDataFieldsReally();

    $user_controller = new UserController($mt, $user, $r);
    $token = $user_controller->getPasswordResetLink($user);
    $this->assertNotNull($token);

    $user_resource2 = UserAdapter::getUserResourceFromId($user_id);
    $this->assertEquals("10", substr($user_resource2->flags, 0, 2));
    $this->assertEquals(0, $user_resource2->last_four);

  }

//*    
  function testChangePassword()
  {
    // changing a passowrd for mikes marketer
    $new_password = 'Z2xTtj5w1qz';
    $data = array('password' => $new_password);
    $request = new Request();
    $request->data = $data;
    $user['user_id'] = 12;
    $user_controller = new UserController($mt, $user, $request, 5);
    $user_resource = $user_controller->updateUser();
    $this->assertNull($user_resource->error);
    $new_user_resource = Resource::find(new UserAdapter($mimetypes), '12');
    $login_adapter = new LoginAdapter($mimetypes);
    $this->assertTrue($login_adapter->verifyPasswordWithDbHash($new_password, $new_user_resource->password));
    //$passowrd = Encrypter::Decrypt($new_user_resource->password);
    error_log("mikes marketer password is: " . $new_password);

  }

//*/

  function testCreateUserBadPassword()
  {
    // create data for request.
    $ts = time();
    $email = "bob" . $ts . "x1@dummy.com";
    $new_user_data = array('email' => $email, 'first_name' => 'bob', 'last_name' => "roberts", 'password' => "wel,come");
    $user_resource = $this->applyDataToCreateUser($new_user_data);
    $this->assertEquals(12, $user_resource->error_code);
    $this->assertEquals('Sorry but the password you entered contains bad characters. Please use only numbers, letters, !, ., $, @, and _', $user_resource->error);
    $this->assertFalse($user_resource->_exists);
    $this->assertEquals(422, $user_resource->http_code);

    $new_user_data = array('email' => $email, 'first_name' => 'bob', 'last_name' => "roberts", 'password' => 'wel@co$me', 'contact_no' => '1234567890');
    $user_resource = $this->applyDataToCreateUser($new_user_data);
    $this->assertEquals(true, $user_resource->_exists);
    return $email;
  }

  function testCreateUser()
  {
    // create data for request.
    $ts = time();
    $email = "bob" . $ts . "x2@dummy.com";
    $new_user_data = array('email' => $email, 'first_name' => 'bob', 'last_name' => "roberts", 'password' => "welcome", 'contact_no' => '1234567890');
    $user_resource = $this->applyDataToCreateUser($new_user_data);

    $this->assertEquals(true, $user_resource->_exists);
    return $email;
  }

  /**
   * @depends testCreateUser
   */
  function testCreateUserWithSameEmail($email)
  {
    myerror_log("starting check same email with: $email");
    $new_user_data = array('email' => $email, 'first_name' => 'bob-donates', 'last_name' => 'roberts', 'password' => 'something', 'donation_active' => 'Y', 'donation_type' => 'R', 'contact_no' => '1234567890');
    $user_resource = $this->applyDataToCreateUser($new_user_data);
    $this->assertEquals('Sorry, it appears this email address exists already with a different password. Please try logging in on the main screen.', $user_resource->error);

    // now try with correct password
    myerror_log("starting check same email with: $email");
    $new_user_data = array('email' => $email, 'first_name' => 'bob-donates', 'last_name' => 'roberts', 'password' => 'welcome', 'donation_active' => 'Y', 'donation_type' => 'R', 'contact_no' => '1234567890');
    $user_resource = $this->applyDataToCreateUser($new_user_data);
    $this->assertEquals('This account already exists, however, your password matched, so we logged in you anyway :)', $user_resource->user_message);

  }

  function testCreateUserWithRoundUp()
  {
    // create data for request.
    setContext("com.splickit.worldhq");
    $ts = time();
    $email = "bob_donates" . $ts . "@dummy.com";
    $new_user_data = array('email' => $email, 'first_name' => 'bob-donates', 'last_name' => 'roberts', 'password' => 'welcome', 'donation_active' => 'Y', 'donation_type' => 'R', 'contact_no' => '1234567890');
    $user_resource = $this->applyDataToCreateUser($new_user_data);
    $this->assertEquals(true, $user_resource->_exists);
    $usda = new UserSkinDonationAdapter($mimetypes);
    $record = $usda->getRecord(array("user_id" => $user_resource->user_id));
    $this->assertEquals($_SERVER['SKIN_ID'], $record['skin_id']);
    return $user_resource;
  }

  /**
   *
   * @depends testCreateUserWithRoundUp
   * @param $user_resource
   */
  function testUploadCC($user_resource)
  {
    $data = array('cc_number' => '4111114567111111');
    $user_controller = $this->getUserController($data);
    $user_controller->setUser($user_resource);
    $user_resource = $user_controller->updateUser();
    $this->assertEquals('Credit Card save error, zip cannot be blank.', $user_resource->error);

    unset($user_resource->error);
    unset($user_resource->error_code);
    $data['zip'] = '12345';
    $user_controller = $this->getUserController($data);
    $user_controller->setUser($user_resource);
    $user_resource = $user_controller->updateUser();
    $this->assertEquals('Credit Card save error, expiration date cannot be blank.', $user_resource->error);

    unset($user_resource->error);
    unset($user_resource->error_code);
    $data['cc_exp_date'] = '0620';
    $user_controller = $this->getUserController($data);
    $user_controller->setUser($user_resource);
    $user_resource = $user_controller->updateUser();
    $this->assertEquals('Credit Card save error, CVV cannot be blank.', $user_resource->error);

    unset($user_resource->error);
    unset($user_resource->error_code);
    $data['cvv'] = '023';
    $data['cc_exp_date'] = '0612';
    $user_controller = $this->getUserController($data);
    $user_controller->setUser($user_resource);
    $user_resource = $user_controller->updateUser();
    $this->assertEquals('Credit Card save error, expired expiration date: 0612', $user_resource->error);

    unset($user_resource->error);
    unset($user_resource->error_code);
    $data['cc_exp_date'] = '0620';
    $user_controller = $this->getUserController($data);
    $user_controller->setUser($user_resource);
    $user_resource = $user_controller->updateUser();
    $this->assertNotNull($user_resource);
    $this->assertEquals('Error saving credit card info: CC number is not valid', $user_resource->error);

    unset($user_resource->error);
    unset($user_resource->error_code);
    $data['cc_number'] = '4111111111111111';
    $user_controller = $this->getUserController($data);
    $user_controller->setUser($user_resource);
    $user_resource = $user_controller->updateUser();
    $this->assertNotNull($user_resource);
    $this->assertNull($user_resource->error);

    // now make sure the record was added to the update tabel
    $record = CreditCardUpdateTrackingAdapter::staticGetRecord(array("user_id" => $user_resource->user_id), 'CreditCardUpdateTrackingAdapter');
    $this->assertEquals('1111', $record['last_four']);

    return $user_resource->user_id;
  }

  /**
   * @depends testUploadCC
   */
  function testChangeLastName($user_id)
  {
    $data = array('last_name' => 'changed');
    $request = new Request();
    $request->data = $data;
    $user['user_id'] = $user_id;
    $user_controller = new UserController($mt, $user, $request, 5);
    $user_resource = $user_controller->updateUser();
    $this->assertNull($user_resource->error);
    $this->assertEquals('changed', $user_resource->last_name);
  }

  function testMarkUsersFlagForRemoteCCSave()
  {
    $created_user_resource = createNewUser();
    $user = logTestUserResourceIn($created_user_resource);
    $this->assertEquals('1000000001', $created_user_resource->flags);
    $data = array('credit_card_saved_in_vault' => true);
    $request = new Request();
    $request->data = $data;
    $user_controller = new UserController($mt, $user, $request, 5);
    $user_resource = $user_controller->updateUser();
    $this->assertNull($user_resource->error);
    $this->assertEquals('1C21000001', $user_resource->flags);
    $this->assertEquals('1111', $user_resource->last_four, "last four shoudl have been saved by calling VIO after flag update");

    // now make sure the record was added to the update tabel
    $record = CreditCardUpdateTrackingAdapter::staticGetRecord(array("user_id" => $created_user_resource->user_id), 'CreditCardUpdateTrackingAdapter');
    $this->assertEquals('1111', $record['last_four']);
  }

  function testDoNotMarkUsersFlagIfFailureOnVIOcallForLastFourWIthSavedInVaultFlagSet()
  {
    $created_user_resource = createNewUserWithCC();
    $created_user_resource->uuid = 'FFFFF-FFFFF-FFFFF-FFFFF';
    $created_user_resource->save();
    $user = logTestUserResourceIn($created_user_resource);
    $this->assertEquals('1C21000001', $created_user_resource->flags);
    $data = array('credit_card_saved_in_vault' => true);
    $request = new Request();
    $request->data = $data;
    $user_controller = new UserController($mt, $user, $request, 5);
    $user_resource = $user_controller->updateUser();
    $this->assertNotNull($user_resource->error);
    $this->assertEquals("The credit card information did not get saved", $user_resource->error);
    $this->assertEquals('1000000001', $user_resource->flags);
    $this->assertFalse($user_resource->last_four, "last four shoudl not have been updated");
  }

  // device black list here

  function testGetPasswordResetLink()
  {
    $user_id = $this->ids['change_password_user_id'];

    $user_controller = new UserController($mt, $user, $r);
    $token = $user_controller->getPasswordResetLinkFromUserId($user_id);
    $this->assertNotNull($token);

    $user_resource = UserAdapter::getUserResourceFromId($user_id);
    //$this->assertEquals("10", substr($user_resource2->flags,0,2));
    $this->assertEquals(0, $user_resource->last_four);
    $this->assertEquals("1020000001", $user_resource->flags, "User flag for CC should have gotten reset");

    $request = new Request();
    $request->data['email'] = $user_resource->email;
    $user_controller2 = new UserController($mt, $u, $request);
    $token_resource = $user_controller2->retrievePasswordToken();

    $this->assertNull($token_resource->error);

    myerror_log("$token = " . $token_resource->token);
    $this->assertEquals($token, $token_resource->token, "tokens do not match");

    return $token;
  }

  /**
   * @depends testGetPasswordResetLink
   */
  function testResetPassword($token)
  {
    // create new password to reset it to
    $ts = time();
    $tstring = (String)time();
    $new_password = 'adam' . substr($tstring, -4);

    $request = new Request();
    $request->data['token'] = $token;
    $request->data['password'] = $new_password;
    $user_controller3 = new UserController($mt, $u, $request);
    $p_resource = $user_controller3->changePasswordWithToken();

    $this->assertNull($p_resource->error, 'ERROR should have been NULL but it was: ' . $p_resource->error);
    $this->assertEquals('success', $p_resource->result);

    // now we need to test to see if the token can be used again (shoujdln't be able to)
    $request4 = new Request();
    $request4->data['token'] = $token;
    $request4->data['password'] = $new_password;
    $user_controller4 = new UserController($mt, $u, $request4);
    $p_resource2 = $user_controller3->changePasswordWithToken();

    $this->assertNotNull($p_resource2->error, "ERROR was null for password retrieval and should have been valid");
    myerror_log("the error on bad token retrieval is: " . $p_resource2->error);
    $this->assertEquals(998, $p_resource2->error_code);

    return $new_password;
  }

  function testUserWelcomeLetter()
  {
    setContext("com.splickit.schlotzskys");
    $user['user_id'] = $this->ids['change_password_user_id'];
    $user_controller = new UserController($mt, $user, $r);
    $result = $user_controller->sendWelcomeLetterToUser('schlotzskys_new_user_welcome.html');
    $this->assertEquals(true, $result);
  }

  function testCreateUserSocialRecord()
  {
    $user_adapter = new UserAdapter($mimetypes);

    $twitter_user_id = rand(1000000000, 9999999999);
    $user_social['facebook_key'] = "AAACFq7mI1DYBAHr2R1guzXOIPfSPW4qBsX1m4V3JOMZCzQvZAFrIYZAcaUQPqUBIFIehIC0047yuZBqNngnK5rUJWAyPhc8UpAKV3QeamAZDZD";
    $user_social['twitter_user_id'] = $twitter_user_id;
    $user_social['facebook_user_id'] = "650096824";
    $user_social['twitter_consumer_key'] = "2eU7vBV0W86IKnXrXzPkQ";
    $user_social['twitter_consumer_secret'] = "b1Q1utMzpYY1KqiBUmNaoOnqkKc5u36gpDKxiHSQ";

    $user_data['user_id'] = $this->ids['change_password_user_id'];;
    $user_data['modified'] = time();
    $user_data['user_social'] = $user_social;

    $request = new Request();
    $request->data = $user_data;
    $user['user_id'] = $this->ids['change_password_user_id'];;
    $user_controller = new UserController($mt, $user, $request, 5);
    $user_resource = $user_controller->updateUser();
    $this->assertNull($user_resource->error);
    $this->assertNotNull($user_resource->user_social_id);

    $user_social_adapter = new UserSocialAdapter($mimetypes);
    $user_social_id = $user_resource->user_social_id;
    $user_social_resource = Resource::find($user_social_adapter, '' . $user_social_id);
    $this->assertNotNull($user_social_resource);
    $this->assertEquals($twitter_user_id, $user_social_resource->twitter_user_id);
  }

  function testUpdateUserSocial()
  {
    $facebook_id = rand(100000000, 999999999);
    $user_social['facebook_user_id'] = $facebook_id;

    $user_data['user_id'] = $this->ids['change_password_user_id'];;
    $user_data['modified'] = time() - 5;
    $user_data['last_name'] = generateCode(10);

    $user_data['user_social'] = $user_social;

    $request = new Request();
    $request->data = $user_data;
    $user['user_id'] = $this->ids['change_password_user_id'];;
    $user_controller = new UserController($mt, $user, $request, 5);
    $user_resource = $user_controller->updateUser();
    $this->assertNull($user_resource->error);
    $this->assertNotNull($user_resource->user_social_id);

    $user_social_adapter = new UserSocialAdapter($mimetypes);
    $user_social_id = $user_resource->user_social_id;
    $user_social_resource = Resource::find($user_social_adapter, '' . $user_social_id);
    $this->assertNotNull($user_social_resource);
    $this->assertEquals($facebook_id, $user_social_resource->facebook_user_id);

  }

  function testForInvalidPhoneNumber()
  {
    $user_controller = new UserController($mt, $u, $r);
    $phone = '1234567890';
    $this->assertFalse($user_controller->checkForInvalidPhoneNumber($phone));
    $phone = '(123)-456-7890';
    $this->assertFalse($user_controller->checkForInvalidPhoneNumber($phone));
    $phone = '123.456.7890';
    $this->assertFalse($user_controller->checkForInvalidPhoneNumber($phone));

    $phone = '(123)456-789';
    $this->assertTrue($user_controller->checkForInvalidPhoneNumber($phone));

    $phone = '12345A7890';
    $this->assertTrue($user_controller->checkForInvalidPhoneNumber($phone));

  }

  function testDeleteCardAssociationWithRequestData()
  {
    $user_resource = createNewUser(array("flags" => '1C20000001'));
    $user_id = $user_resource->user_id;
    $user = logTestUserIn($user_resource->user_id);
    $this->assertEquals("1C20000001", $user['flags']);

    $request = new Request();
    $request->url = '/app2/phone/users';
    $request->method = "post";
    $request->data = array("delete_cc_info" => "Y");

    $user_controller = new UserController($mt, $user, $request, 5);
    // save cc
    $resource = $user_controller->updateUser();
    $this->assertEquals('1000000001', $resource->flags);

  }

  function testDeleteCardAssociationAsUserFunction()
  {
    $user_resource = createNewUser(array("flags" => '1C20000001'));
    $user_id = $user_resource->user_id;
    $user = logTestUserIn($user_resource->user_id);
    $this->assertEquals("1C20000001", $user['flags']);

    $user_controller = new UserController($mt, $user, $request, 5);
    $resource = $user_controller->deleteCCInfo();
    $this->assertEquals('1000000001', $resource->flags);

  }

  function testLoadUserOrderHistory()
  {
    setContext('com.splickit.pitapit');
    $user_resource = createNewUser();
    $user = logTestUserResourceIn($user_resource);
    $uuid = $user['uuid'];
    $request = new Request();
    $request->url = "apiv2/users/$uuid/orderhistory";
    $request->method = 'GET';
    $_SERVER['REQUEST_METHOD'] = 'GET';

    $user_controller = new UserController($mt, $user, $request, 5);
    $order_history = $user_controller->processV2Request();
    $this->assertNull($order_history->error);
    $this->assertTrue(count($order_history) >= 0);
  }

  function testUserFavorites(){

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
    $menu = CompleteMenu::getCompleteMenu($menu_id, 'Y', 0, 2);
    $item_size = $menu['menu_types'][0]['menu_items'][0]['size_prices'][0];
    $ids['item_size'] = $item_size;
    $modifiers = CompleteMenu::getAllModifierItemSizesAsArray($menu_id, 'Y', 0);
    $modifier_size = $modifiers[0];
    $ids['modifier_size'] = $modifier_size;

    $user_resource = createNewUser(array("flags" => "1C20000001"));
    $ids['user_id'] = $user_resource->user_id;

    $ids['user_uuid'] = $user_resource->uuid;

    $fave_data['user_id'] = $ids['user_id'];
    $fave_data['menu_id'] = $ids['menu_id'];
    $fave_data['merchant_id'] = $ids['merchant_id'];
    $fave_data['favorite_name'] = "my favorite";
    $fave_data['favorite_json'] = '{"merchant_id":"' . $ids['merchant_id'] . '","note":"order note","items":[{"quantity":1,"note":"item note","item_id":"' . $item_size['item_id'] . '","size_id":"' . $item_size['size_id'] . '","sizeprice_id":"' . $item_size['item_size_id'] . '","mods":[{"modifier_item_id":"' . $modifier_size['modifier_item_id'] . '","mod_item_id":"' . $modifier_size['modifier_item_id'] . '","mod_sizeprice_id":"' . $modifier_size['modifier_size_id'] . '","mod_quantity":1,"quantity":1}]}],"user_id":"' . $ids['user_id'] . '","favorite_name":"burrito"}';
    $fave_resource = new Resource(new FavoriteAdapter($m), $fave_data);
    $fave_resource->save();

    //$expected_new_favorite_json = '{"merchant_id":"' . $ids['merchant_id'] . '","note":"order note","items":[{"quantity":1,"note":"item note","item_id":"' . $item_size['item_id'] . '","size_id":"' . $item_size['size_id'] . '","sizeprice_id":"' . $item_size['item_size_id'] . '","mods":[{"modifier_item_id":"' . $modifier_size['modifier_item_id'] . '","mod_item_id":"' . $modifier_size['modifier_item_id'] . '","mod_sizeprice_id":"' . $modifier_size['modifier_size_id'] . '","mod_quantity":1,"quantity":1}]}],"user_id":"' . $ids['user_id'] . '","favorite_name":"burrito"}';

    
    $user = logTestUserResourceIn($user_resource);
    $uuid = $user['uuid'];
    $request = createRequestObject("apiv2/users/$uuid/favorites?merchant_id=$merchant_resource->merchant_id&merchant_menu_type=pickup",'GET');

    $user_controller = new UserController($mt, $user, $request,5);
    $order_history = $user_controller->processV2Request();
    $this->assertNull($order_history->error);
    $this->assertTrue(count($order_history)>=0);
  }

  private function getUserController($data)
  {
    $request = new Request();
    $request->data = $data;
    $user = logTestUserIn(1);
    $user_controller = new UserController($mimetypes, $user, $request, 5);
    return $user_controller;
  }

  private function applyDataToCreateUser($data)
  {
    $user_controller = $this->getUserController($data);
    return $user_controller->createUser();
  }

  private function applyDataToUpdateUser($data)
  {
    $user_controller = $this->getUserController($data);
    return $user_controller->updateUser();
  }

  static function setUpBeforeClass()
  {
    $_SERVER['request_time1'] = microtime(true);
    $tz = date_default_timezone_get();
    $_SERVER['starting_tz'] = $tz;
    date_default_timezone_set(getProperty("default_server_timezone"));

    ini_set('max_execution_time', 300);
          SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();$mysqli->begin_transaction(); ;

    $skin_resource = createWorldHqSkin();
    $ids['skin_id'] = $skin_resource->skin_id;
    $skin_resource->base_url = "https://sumdum.domain.com";
    $skin_resource->save();

    $user_resource = createNewUser(array("flags" => "1C20000001"));
    $ids['change_password_user_id'] = $user_resource->user_id;

    $_SERVER['log_level'] = 5;
    $_SERVER['unit_test_ids'] = $ids;
  }

  static function tearDownAfterClass()
  {
    SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
  }

  /* mail method for testing */
  static function main()
  {
    $suite = new PHPUnit_Framework_TestSuite(__CLASS__);
    PHPUnit_TextUI_TestRunner::run($suite);
  }

}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
  UserFunctionsTest::main();
}

?>