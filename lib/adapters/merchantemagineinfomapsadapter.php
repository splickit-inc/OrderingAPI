<?php

/**
 * Created by PhpStorm.
 * User: Elvis
 * Date: 8/18/16
 * Time: 11:33 AM
 */
class MerchantEmagineInfoMapsAdapter extends MySQLAdapter
{
    function MerchantEmagineInfoMapsAdapter($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'Merchant_Emagine_Info_Maps',
            '%([0-9]{4,10})%',
            '%d',
            array('id'),
            null,
            array('created','modified')
        );

        $this->allow_full_table_scan = false;

    }

    function &select($url, $options = NULL)
    {
        $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
        return parent::select($url,$options);
    }
}