<?php
class MoesLoginAdapter extends LoginAdapter
{
    var $token_name = 'Punch_authentication_token';

    function __construct($m)
    {
        parent::LoginAdapter($m);
        $this->header_token_names[] = $this->token_name;
    }

    function authorize($email,$password,$request_data = array())
    {
        if ($authentication_token = $request_data[$this->token_name]) {
            $punch_loyalty_service = new PunchLoyaltyService();
            if ($punch_loyalty_service->isValidAuthenticationToken($authentication_token)) {
                $punch_user_info = $punch_loyalty_service->getAuthUserInfo();
                // all is good lets get the user resource if it exists
                if ($user_resource = UserAdapter::doesUserExist($punch_user_info['user']['communicable_email'])) {
                    $loyalty_number = $punch_user_info['user']['id'].":".$punch_user_info['user']['authentication_token'];
                    $user_brand_points_map_resource = Resource::findOrCreateIfNotExistsByData(new UserBrandPointsMapAdapter($m),array("user_id"=>$user_resource->user_id,"brand_id"=>getBrandIdFromCurrentContext()));
                    $user_brand_points_map_resource->loyalty_number = $loyalty_number;
                    $user_brand_points_map_resource->save();
                    return $user_resource;
                } else {
                    // need to creat the user
                    $request = new Request();
                    $request->data['first_name'] = preg_replace("/[^A-Za-z\-]/", '', $punch_user_info['user']['first_name']);
                    $request->data['last_name'] = preg_replace("/[^A-Za-z\-]/", '', $punch_user_info['user']['last_name']);
                    $request->data['email'] = $punch_user_info['user']['communicable_email'];
                    $request->data['password'] = generateCode(10);
                    $request->data['contact_no'] = '1234567890';
                    $request->data['loyalty_number'] = $punch_user_info['user']['id'].":".$punch_user_info['user']['authentication_token'];
                    $user_controller = new UserController($m,$u,$request,5);
                    $user_resource = $user_controller->createUser();
                    if ($user_resource->hasError()) {
                        $this->error_resource = createErrorResourceWithHttpCode('Sorry, we cannot create your account. Ther is a problem with the data sent.', 422, 99, array('text_title' => 'Authentication Error'));
                        return false;
                    }
                    return $user_resource;
                }
            }
            $this->error_resource = createErrorResourceWithHttpCode('Sorry, we cannot validate your credentials.', 401, 99, array('text_title' => 'Authentication Error'));
            return false;
        } else {
            return parent::authorize($email,$password,$request_data);
        }

    }
}
?>