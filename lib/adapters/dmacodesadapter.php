<?php

class DmaCodesAdapter extends MySQLAdapter
{

    function DmaCodesAdapter($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'adm_dma_codes',
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

    function getRegionCodeByData($data, $error_message = null, $http_code = null, $error_code = null)
    {
        $data['logical_delete'] = 'N';

        if ($results = $this->getRecords($data)) {
            if (sizeof($results) == 1) {
                return array_pop($results);
            } else {
                return createErrorResourceWithHttpCode($error_message, $http_code, $error_code);
            }
        } else {
            return null;
        }
    }

}

?>