<?php

class LeadTimeByDayPartMapsAdapter extends MySQLAdapter
{
    function __construct()
    {
        parent::MysqlAdapter(
            getM(),
            'Lead_Time_By_Day_Part_Maps',
            '%([0-9]{4,15})%',
            '%d',
            array('id'),
            null,
            array('created', 'modified')
        );
    }

    function &select($url, $options = NULL)
    {
        if ($options[TONIC_FIND_BY_METADATA]['logical_delete'] == null) {
            $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
        }
        return parent::select($url, $options);
    }

    function getLeadTimeByDayPartForDay($day_of_week,$merchant_id)
    {
        if ($day_of_week && $merchant_id) {
            if ($records = $this->getRecords(['day_of_week'=>$day_of_week,'merchant_id'=>$merchant_id])) {
                return $records;
            }
        } else {
            myerror_log("ERROR!!!! missing either day_of_week or merchant_id from getLeadTimeByDayPartForDay");
        }
    }

    function getLeadTimesByDayPartForMerchantId($merchant_id)
    {
        $results = [];
        if ($merchant_id) {
            if ($records = $this->getRecords(['merchant_id'=>$merchant_id])) {
                foreach ($records as $record) {
                    $results[$record['hour_type']][$record['day_of_week']][] = $record;
                }
                return $results;
            }
        } else {
            myerror_log("ERROR! No merchant id passed in to getLeadTimesByDayPartForMerchantId");
        }
    }


}

?>