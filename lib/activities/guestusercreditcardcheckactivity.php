<?php

class GuestUserCreditCardCheckActivity extends SplickitActivity
{

    function doIt()
    {
        // get all guest users with CC info still saved and over the time limit
        //date_default_timezone_set(getProperty("defautl_serer_time_zone"))
        $guest_user_cc_save_time_in_minutes = getProperty("guest_user_cc_save_time_in_minutes");
        if ($guest_user_cc_save_time_in_minutes < 1) {
            $guest_user_cc_save_time_in_minutes = 59;
        }

        $user_adapter = new UserAdapter();
        $ccuta = new CreditCardUpdateTrackingAdapter();
        $created_before = getMySqlFormattedDateTimeFromTimeStampAndTimeZone(time() - ($guest_user_cc_save_time_in_minutes * 60));
        $sql = "SELECT b.* FROM User a JOIN Credit_Card_Update_Tracking b ON a.user_id = b.user_id WHERE a.flags = '1C21000021' AND b.created < '$created_before'";
        $options[TONIC_FIND_BY_SQL] = $sql;
        $updated_records = 0;
        foreach (Resource::findAll($ccuta,null,$options) as $guest_user_with_cc) {
            $user_id = $guest_user_with_cc->user_id;
            $user_resource = Resource::find($user_adapter,"$user_id");
            $user_resource->flags = "1000000021";
            $user_resource->last_four = 'nullit';
            if ($user_resource->save()) {
                $updated_records++;
            }
        }
        myerror_log("There were $updated_records guest user records with thier CC erased");
        return true;

    }
}