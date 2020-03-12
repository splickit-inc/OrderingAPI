<?php

class AuditApiPortalPostTrailAdapter extends MySQLAdapter
{

    function AuditApiPortalPostTrailAdapter($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'Audit_Api_Portal_Post_Trail',
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

    function auditTrail($sql)
    {
        return true;
    }


}
?>