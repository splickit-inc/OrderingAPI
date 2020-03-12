<?php

class CreditCardUpdateTrackingAdapter extends MySQLAdapter
{

    function CreditCardUpdateTrackingAdapter($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'Credit_Card_Update_Tracking',
            '%([0-9]{4,10})%',
            '%d',
            array('id'),
            null,
            array('created','modified')
        );

        $this->allow_full_table_scan = false;

    }

    static function recordCreditCardUpdate($user_id,$device_id,$last_four)
    {
        $ccuta = new CreditCardUpdateTrackingAdapter($m);
        $data['user_id'] = $user_id;
        $data['device_id'] = $device_id;
        $data['last_four'] = $last_four;
        return Resource::createByData($ccuta,$data);
    }

    static function recordCreditCardUpdateAndCheckForBlacklisting($user_id,$device_id,$last_four)
    {
        $ccuta = new CreditCardUpdateTrackingAdapter($m);

        // check for more than 4 different numbers in teh last 24hrs if so add to black list and disable account
        if ($ccuta->getNumberOfDifferentCardUpdatesInLastTimePeriod($user_id,$device_id) > 3) {
            DeviceBlacklistAdapter::addUserResourceToBlackList($user_id);
            return false;
        }
        return $ccuta->recordCreditCardUpdate($user_id,$device_id,$last_four);

    }

    function getNumberOfDifferentCardUpdatesInLastTimePeriodByUserResource($user_resource)
    {
        return $this->getNumberOfDifferentCardUpdatesInLastTimePeriod($user_resource->user_id,$user_resource->device_id);
    }

    function getNumberOfDifferentCardUpdatesInLastTimePeriod($user_id,$device_id)
    {
        $results_by_last_four = array();
        foreach ($this->getCardUpdatesInLastTimePeriod($user_id,$device_id) as $update_record) {
            $results_by_last_four[$update_record['last_four']] = $results_by_last_four[$update_record['last_four']] + 1;
        }
        return count($results_by_last_four);
    }

    function getCardUpdatesInLastTimePeriodByUserResource($user_resource)
    {
        return $this->getCardUpdatesInLastTimePeriod($user_resource->user_id,$user_resource->device_id);
    }

    function getCardUpdatesInLastTimePeriod($user_id,$device_id)
    {
        $time_period = ($value = getProperty('cc_fraud_time_period_in_hours'))? $value : 24;
        $dt_tm_for_sql = date('Y-m-d H:i:s',time()-($time_period*60*60));
        $data['created'] = array(">"=>$dt_tm_for_sql);
        if ($device_id == null || trim($device_id) == '') {
            $data['user_id'] = $user_id;
        } else {
            $data['device_id'] = $device_id;
        }
        return $this->getRecords($data);
    }

    /**
     * @param $user_id
     * @return Resource
     */
    function getLastCardUpdateForUserId($user_id)
    {
        $options[TONIC_FIND_BY_METADATA]['user_id'] = $user_id;
        $options[TONIC_FIND_TO] = 1;
        $options[TONIC_SORT_BY_METADATA] = " created desc ";
        return Resource::find($this,null,$options);
    }
}
?>