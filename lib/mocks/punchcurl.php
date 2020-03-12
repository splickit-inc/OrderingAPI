<?php
class PunchCurl extends SplickitCurl
{
    static function curlIt($url,$json,$headers,$user_password,$method = 'GET')
    {
        $service_name = 'Punch Loyalty Curl';
        $error_no = 0;
        $payload_array = json_decode($json,true);
        if ($url == 'https://intg.punchh.com/api/auth/sso') {
            $user = $_SERVER['punch_test_user_info'];
            if ($payload_array['security_token'] == 'new_user_token_1234567890') {
                $user = array_merge($user,createNewUserDataFields());
            } else if ($payload_array['security_token'] == 'new_user_token_badcharacters') {
                $user = array_merge($user,createNewUserDataFields());
                $user['first_name'] = $_SERVER['punch_test_user_info']['first_name'];
                $user['last_name'] = $_SERVER['punch_test_user_info']['last_name'];
            }
            $return_json = cleanUpDoubleSpacesCRLFTFromString('{
              "token":{
                "access_token":"e73cc321a9d53e6fa5627bb90db161f0c9f13841a13c718780ef1b92f6b08af3"
              },
              "user":{
                "anniversary":null,
                "avatar_remote_url":null,
                "birthday":null,
                "created_at":"2015-08-18T13:53:06Z",
                "email":"'.$user['email'].'",
                "communicable_email":"'.$user['email'].'",
                "fb_uid":"11002333",
                "first_name":"'.$user['first_name'].'",
                "gender":null,
                "id":'.$user['simulated_punch_user_id'].',
                "last_name":"'.$user['last_name'].'",
                "updated_at":"2015-08-18T13:53:06Z",
                "allow_multiple":false,
                "authentication_token":"'.$user['simulated_punch_authentication_token'].'",
                "favourite_locations":""
              }
            }');
            $response['raw_result'] = $return_json;
            $response['http_code'] = 200;
        } else if (substr_count($url,'api/auth/checkins/balance') > 0) {
            $return_json = '{"banked_rewards":"1.50","membership_level":"Fan","membership_level_id":34,"membership_program_id":null,"net_balance":0.0,"net_debits":0.0,"pending_points":0.0,"points_balance":89.0,"signup_anniversary_day":"04/08","total_credits":89.0,"total_debits":0.0,"total_point_credits":89.0,"total_redeemable_visits":1,"expired_membership_level":null,"total_visits":1,"initial_visits":null,"unredeemed_cards":0,"rewards":[],"redeemables":[]}';
            $response['raw_result'] = $return_json;
            $response['http_code'] = 200;
        } else if (substr_count($url,'api/auth/redemptions/online_order') > 0) {
            if ($payload_array['cc_last4'] == '8888') {
                $response['raw_result'] = '["Invalid Signature"]';
                $response['http_code'] = 412;
            } else if (true) {
                $return_json = '{"status":"Redeemed at April 12, 2016 15:39 by Tarek Dim at Moe\'s southwest grill. Please HONOR it.","redemption_amount":'.$payload_array['redeemed_points'].',"category":"redeemable","pos_discount_reference":null,"redemption_id":458952,"redemption_code":"9735679"}';
                $response['raw_result'] = $return_json;
                $response['http_code'] = 200;
            } else {
                $return_json = '["You are unable to redeem multiple times in one visit, please try again later"]';
                $response['raw_result'] = $return_json;
                $response['http_code'] = 422;
            }
        } else if (substr_count($url,'api/auth/checkins/online_order') > 0) {
            $payload_array = json_decode($json,true);
            $user = getLoggedInUser();
            $earned_amount = round(10*$payload_array['subtotal_amount']);
            $return_json = '{"first_name":"'.$user['first_name'].'","last_name":"'.$user['last_name'].'","checkins":39,"points":8888,"checkin":{"bar_code":"P146566990261P","created_at":"2016-06-11T18:31:46Z","external_uid":"8674-7d32b-viuiy-ib1ge","checkin_id":5194560,"pending_points":0,"pending_refresh":false,"points_earned":'.$earned_amount.'}}';
            $response['raw_result'] = $return_json;
            $response['http_code'] = 200;
        } else if (substr_count($url,'api/auth/redemptions') > 0 && strtoupper($method) == 'DELETE') {
            $response['raw_result'] = '';
            $response['http_code'] = 202;
        }
        $response['error_no'] = $error_no;
        $response['error'] = $error;
        return $response;
    }
}
?>