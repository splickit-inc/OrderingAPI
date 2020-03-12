<?php

/**
 * Created by PhpStorm.
 * User: Elvis
 * Date: 9/6/16
 * Time: 10:22 AM
 */
class PromoDeliveryAmountMapAdapter extends MySQLAdapter
{
    function PromoDeliveryAmountMapAdapter($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'Promo_Delivery_Amount_Map',
            '%([0-9]{4,15})%',
            '%d',
            array('id'),
            null,
            array('created', 'modified')
        );

        $this->allow_full_table_scan = false;

    }

    function &select($url, $options = NULL)
    {
        $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
        return parent::select($url, $options);
    }

}