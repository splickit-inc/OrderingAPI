<?php

class HomeGrownLoyaltyController extends LoyaltyController
{
    function __construct($mt, $user, $request, $l)
    {
        parent::__construct($mt, $user, $request, $l);
        $this->auto_join = true;
    }

    function setCurrentUserAsPrimaryAccount()
    {
        myerror_log("In the set current User as primary account");
        $current_loyalty_number = $this->local_account_info['loyalty_number'];
        if (! is_numeric($current_loyalty_number)) {
            myerror_log("cant make primary becuase loyalty nubmer is not a phone number");
            return false;
        }
        if (strlen($current_loyalty_number) <= 10) {
            myerror_log("current user is already primary record");
            return true;
        }
        $new_loyalty_number = substr($current_loyalty_number,0,10);
        // now find existing primary record
        $options[TONIC_FIND_BY_METADATA] = ['brand_id'=>$this->brand_id,'loyalty_number'=>$new_loyalty_number];
        if ($existing_primary_resource = Resource::find(new UserBrandPointsMapAdapter(getM()),null,$options)) {
            $append = rand(111,999);
            $existing_primary_resource->loyalty_number .= $append;
            if (! $existing_primary_resource->save()) {
                myerror_log("could not move old primary to new number. unique conflict on save. please try again");
                return false;
            }
        }
        // set primary
        $this->local_brand_points_map_resource->loyalty_number = $new_loyalty_number;
        if ($this->local_brand_points_map_resource->save()) {
            return true;
        } else {
            myerror_log("Could not save current as primary");
            myerror_log("error: ".$this->local_brand_points_map_resource->_adapter->getLastErrorText());
            return false;
        }

    }
}
?>