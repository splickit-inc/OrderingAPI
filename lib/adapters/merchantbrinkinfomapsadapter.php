<?php

class MerchantBrinkInfoMapsAdapter extends MySQLAdapter
{

    function MerchantBrinkInfoMapsAdapter($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'Merchant_Brink_Info_Maps',
            '%([0-9]{4,15})%',
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

    static function isMechantBrinkMerchant($merchant_id)
    {
        $mbima = new MerchantBrinkInfoMapsAdapter($m);
        if ($record = $mbima->getRecord(array("merchant_id"=>$merchant_id))) {
            return true;
        } else {
            return false;
        }
    }

}
?>