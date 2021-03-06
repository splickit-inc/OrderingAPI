<?php

class DmaAdapter extends MySQLAdapter
{

    function DmaAdapter($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'adm_dma',
            '%([0-9]{4,15})%',
            '%d',
            array('id'),
            null,
            array('created', 'modified')
        );
    }

    function &select($url, $options = NULL)
    {
        $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
        return parent::select($url, $options);
    }
}

?>