<?php

class SkinStsInfoMapsAdapter extends MySQLAdapter
{

    function SkinStsInfoMapsAdapter($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'Skin_Sts_Info_Maps',
            '%([0-9]{4,15})%',
            '%d',
            array('id'),
            null,
            array('created','modified')
        );

        $this->allow_full_table_scan = true;

    }

    function &select($url, $options = NULL)
    {
        $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
        return parent::select($url,$options);
    }

    function getStsInfo($skin_id)
    {
        if ($skin_id > 0) {
            if ($record = $this->getRecord(["skin_id"=>$skin_id])) {
                return $record;
            }
        }
        return null;
    }

    function isSkinStsSkin($skin_id)
    {
        if ($record = $this->getStsInfo($skin_id)) {
            return true;
        } else {
            return false;
        }
    }

}
?>