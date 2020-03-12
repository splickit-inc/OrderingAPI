<?php

class MerchantLevelupInfoMapsAdapter extends MySQLAdapter
{

    function MerchantLevelupInfoMapsAdapter($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'Merchant_Levelup_Info_Maps',
            '%([0-9]{1,11})%',
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
?>