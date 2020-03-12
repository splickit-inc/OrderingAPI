<?php


class HoleHoursAdapter extends MySQLAdapter
{
    protected $ORDER_TYPE_VALUES_FOR_HOLE_HOURS = array(
        'R' => "Pickup",
        'D' => "Delivery"
    );

    function HoleHoursAdapter($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'Hole_Hours',
            '%([0-9]{1,15})%',
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

    function getByMerchantId($merchant_id, $options = NULL)
    {
        if (isset($merchant_id)) {
            $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
            return $this->select('', $options);
        } else {
            return returnErrorResource("couldn't get hole hour because the merchantId is null. ");
        }
    }

    function getByMerchantIdAndDayOfWeek($merchant_id, $day_of_week, $options = NULL)
    {
        if (isset($merchant_id) && isset($day_of_week)) {
            $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
            $options[TONIC_FIND_BY_METADATA]['day_of_week'] = $day_of_week;
            return $this->select('', $options);
        } else {
            return returnErrorResource("couldn't get hole hour because the merchantId is null ir day of week is null. ");
        }
    }

    function getByMerchantIdAndDayOfWeekAndOrderType($merchant_id, $day_of_week, $order_type = 'R', $data = array())
    {
        if (isset($merchant_id) && isset($day_of_week) && isset($order_type)) {
            $data['merchant_id'] = $merchant_id;
            $data['day_of_week'] = $day_of_week;
            $data['order_type'] = $this->ORDER_TYPE_VALUES_FOR_HOLE_HOURS[strtoupper($order_type)];;
            return $this->getRecord($data);
        } else {
            return returnErrorResource("couldn't get hole hour because the merchantId is null or day of week is null or order type is null. ");
        }
    }

    function getByMerchantIdAndOrderType($merchant_id, $order_type = 'R', $options = NULL)
    {
        if (isset($merchant_id) && isset($order_type)) {
            $options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
            $options[TONIC_FIND_BY_METADATA]['order_type'] = $this->ORDER_TYPE_VALUES_FOR_HOLE_HOURS[strtoupper($order_type)];;
            return $this->select('', $options);
        } else {
            return returnErrorResource("couldn't get hole hour because the merchantId is null or order type is null. ");
        }
    }

}

?>